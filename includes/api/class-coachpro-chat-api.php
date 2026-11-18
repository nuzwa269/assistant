<?php
/**
 * Class CoachPro_Chat_API
 * POST /wp-json/coachpro/v1/chat
 *
 * Credits are charged by tokens: after the AI answers, the input and output
 * tokens reported by the provider are multiplied by the model's
 * "credits per 1,000 tokens" prices and deducted from the user's balance.
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Chat_API {

    /**
     * Handle a chat request: call AI, save messages, charge credits for tokens.
     */
    public static function handle_chat( WP_REST_Request $request ) {
        global $wpdb;
        // Provider calls can take up to CoachPro_AI_Provider::TIMEOUT seconds; shared hosts often stop PHP at 30s.
        if ( function_exists( 'set_time_limit' ) ) @set_time_limit( CoachPro_AI_Provider::TIMEOUT + 60 );
        $user_id = get_current_user_id();
        $conv_id = sanitize_text_field( $request->get_param('conversation_id') ?? '' );
        $lock = 'cp-chat-' . md5( $wpdb->prefix . $conv_id );
        if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock) ) ) return new WP_Error('chat_busy', 'A response is already being generated. Please wait.', array('status' => 409));
        try { return self::send( $request, $user_id, $conv_id ); }
        finally { $wpdb->get_var( $wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock) ); }
    }

    private static function send( WP_REST_Request $request, int $user_id, string $conv_id ) {
        global $wpdb;
        $prepared = self::prepare_request( $request, $user_id, $conv_id );
        if ( is_wp_error( $prepared ) ) return $prepared;
        extract( $prepared, EXTR_SKIP );

        $request_hash = hash( 'sha256', wp_json_encode( array( $model_id, $message, $assistant['id'] ) ) );
        $request_id   = sanitize_text_field( $request->get_param('request_id') ?: wp_generate_uuid4() );
        if ( ! preg_match('/^[a-f0-9-]{36}$/i', $request_id) ) return new WP_Error('invalid_request', 'Invalid request ID.', array('status' => 400));

        // Idempotency: a retried request returns the saved answer instead of charging twice.
        $existing = CoachPro_DB::get_row('chat_requests', $request_id);
        if ( $existing ) {
            if ( (int) $existing['user_id'] !== $user_id || $existing['conversation_id'] !== $conv_id ) return new WP_Error('conflict', 'Request ID already used.', array('status' => 409));
            if ( !empty($existing['payload_hash']) && !hash_equals($existing['payload_hash'], $request_hash) ) return new WP_Error('request_conflict', 'Request ID belongs to different message/model content.', array('status'=>409));
            if ( 'complete' === $existing['status'] ) return self::response($existing['message_id'], $user_id);
            if ( 'failed' === $existing['status'] ) return new WP_Error('request_failed', 'This request failed and no credits were charged. Please send the message again.', array('status'=>409));
            return new WP_Error('request_processed', 'This request is still being processed. Refresh the conversation before retrying.', array('status' => 409));
        }

        // Balance check before spending money at the provider.
        $rates   = CoachPro_Credits::model_rates( $model );
        $balance = CoachPro_Credits::get_balance_micro( $user_id );
        if ( $rates['input'] > 0 || $rates['output'] > 0 ) {
            $estimated_input = CoachPro_Usage::estimate_tokens( (string) wp_json_encode( $messages ) );
            $minimum = max( 1, CoachPro_Money::token_cost( $estimated_input, 0, $rates['input'], 0 ) );
            if ( $balance < $minimum ) {
                return new WP_Error( 'insufficient_credits', 'You do not have enough credits. Please buy more credits to continue.', array( 'status' => 402 ) );
            }
        }

        $message_id = wp_generate_uuid4();
        $inserted = $wpdb->insert( CoachPro_DB::table('chat_requests'), array( 'id'=>$request_id, 'user_id'=>$user_id, 'conversation_id'=>$conv_id, 'message_id'=>$message_id, 'credits'=>0, 'payload_hash'=>$request_hash, 'status'=>'pending', 'created_at'=>gmdate('Y-m-d H:i:s') ) );
        if ( false === $inserted ) return CoachPro_DB::write_error();

        try {
            CoachPro_Usage::$context = array( 'user_id'=>$user_id, 'model_id'=>$model_id, 'operation'=>'chat' );
            CoachPro_Usage::reset_tally();
            $reply = CoachPro_AI_Provider::call( $model_id, $messages, $user_id, $conv_id, $options );
            if ( is_wp_error( $reply ) ) {
                self::mark_failed( $request_id );
                $reply->add_data( array_merge( (array) $reply->get_error_data(), array( 'credits_charged' => 0 ) ) );
                return $reply;
            }
            if ( '' === trim( (string) $reply ) ) {
                self::mark_failed( $request_id );
                return new WP_Error( 'empty_response', 'The assistant could not finish this answer. No credits were charged. Please try again.', array( 'status'=>502 ) );
            }

            $usage = CoachPro_Usage::tally();
            $cost  = CoachPro_Money::token_cost( (int) $usage['input'], (int) $usage['output'], $rates['input'], $rates['output'] );
            $note  = sprintf( '%s: %d input + %d output tokens%s', $model['display_name'], $usage['input'], $usage['output'], $usage['estimated'] ? ' (estimated)' : '' );

            $saved = CoachPro_DB::transaction( function() use ( $conv_id, $user_id, $message, $reply, $model_id, $message_id, $request_id, $usage, $cost, $note ) {
                global $wpdb;
                $charged = 0;
                if ( $cost > 0 ) {
                    $charge = CoachPro_Credits::charge_usage( $user_id, $cost, $message_id, $model_id, $note );
                    if ( is_wp_error( $charge ) ) return $charge;
                    $charged = -$charge['amount'];
                }
                $table = CoachPro_DB::table('messages');
                // Microseconds provide a stable order when both messages are saved in one second.
                $now = microtime(true);
                $stamp = function( int $add ) use ( $now ) { return gmdate('Y-m-d H:i:s', (int)$now) . sprintf('.%06d', min(999999, (int)(($now-floor($now))*1000000) + $add)); };
                $rows = array(
                    array( 'id'=>wp_generate_uuid4(), 'role'=>'user', 'content'=>$message, 'credits_used'=>'0', 'input_tokens'=>0, 'output_tokens'=>0, 'created_at'=>$stamp(0) ),
                    array( 'id'=>$message_id, 'role'=>'assistant', 'content'=>$reply, 'credits_used'=>CoachPro_Money::format( $charged ), 'input_tokens'=>(int)$usage['input'], 'output_tokens'=>(int)$usage['output'], 'created_at'=>$stamp(1) ),
                );
                foreach ( $rows as $row ) {
                    if ( false === $wpdb->insert( $table, array_merge( $row, array( 'conversation_id'=>$conv_id, 'user_id'=>$user_id, 'model_id'=>$model_id ) ) ) ) return CoachPro_DB::write_error();
                }
                if ( false === $wpdb->update( CoachPro_DB::table('conversations'), array('updated_at'=>current_time('mysql')), array('id'=>$conv_id) ) ) return CoachPro_DB::write_error();
                return false === $wpdb->update( CoachPro_DB::table('chat_requests'), array( 'status'=>'complete', 'credits'=>CoachPro_Money::format( $charged ) ), array( 'id'=>$request_id, 'status'=>'pending' ) ) ? CoachPro_DB::write_error() : true;
            }, $user_id );
            if ( is_wp_error( $saved ) ) { self::mark_failed( $request_id ); return $saved; }
            CoachPro_AI_Provider::schedule_summary( $conv_id );
            return self::response( $message_id, $user_id );
        } catch ( Throwable $error ) {
            error_log( 'CoachPro chat failed: ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine() );
            self::mark_failed( $request_id );
            return new WP_Error( 'chat_failed', 'Unable to generate a response. No credits were charged.', array( 'status'=>502 ) );
        } finally { CoachPro_Usage::$context = array(); }
    }

    public static function prepare_request(WP_REST_Request $request,int $user_id,string $conv_id) {
        global $wpdb;
        $conv = CoachPro_DB::get_row('conversations', $conv_id);
        if ( ! $conv || (int) $conv['user_id'] !== $user_id ) return new WP_Error('forbidden', 'Conversation not found.', array('status' => 403));
        $assistant = CoachPro_DB::get_row('assistants', $conv['assistant_id']);
        if ( ! $assistant || ! CoachPro_Assistants_API::user_can_use($assistant, $user_id) ) return new WP_Error('forbidden', 'Assistant unavailable.', array('status' => 403));
        $message = self::clean_text( (string) ( $request->get_param('message') ?? '' ) );
        if ( '' === trim($message) || strlen($message) > 50000 ) return new WP_Error('invalid_message', 'Enter a message of up to 50,000 bytes.', array('status' => 400));
        $model_id = self::resolve_model_id($assistant, sanitize_text_field($request->get_param('model_id') ?? ''));
        $model = CoachPro_DB::get_row('ai_models', $model_id);
        if ( ! $model || ! $model['is_active'] || 'image' === $model['category'] ) return new WP_Error('invalid_model', 'Select an active text model.', array('status' => 400));
        if ( ! CoachPro_Credits::can_use_model($user_id, $model) ) return new WP_Error('plan_required', 'Your plan does not include this model.', array('status' => 403));
        $table=CoachPro_DB::table('messages');
        $history=$wpdb->get_results($wpdb->prepare("SELECT role,content FROM {$table} WHERE conversation_id=%s ORDER BY created_at DESC,id DESC LIMIT 20",$conv_id),ARRAY_A);
        $messages=array_merge(array(array('role'=>'system','content'=>$assistant['system_prompt'])),array_reverse((array)$history),array(array('role'=>'user','content'=>$message)));
        $options=array('temperature'=>$assistant['temperature'],'max_tokens'=>$assistant['max_tokens']);
        return compact('conv','assistant','message','model_id','model','messages','options');
    }

    /**
     * Keep the user's text exactly as typed (code, HTML and "<" included).
     * It is stored as data and always shown with textContent/escaping, so
     * stripping tags would only destroy code questions. Invalid UTF-8,
     * NUL and other control characters are removed.
     */
    public static function clean_text( string $text ) : string {
        $text = wp_check_invalid_utf8( $text, true );
        $text = str_replace( array( "\r\n", "\r" ), "\n", $text );
        return (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text );
    }

    private static function response(string $id, int $user_id) {
        $row = CoachPro_DB::get_row('messages', $id);
        if ( ! $row ) return new WP_Error('not_found', 'Response no longer exists.', array('status'=>404));
        return rest_ensure_response( array(
            'message_id'    => $id,
            'content'       => $row['content'],
            'model_id'      => $row['model_id'],
            'credits_used'  => (float) $row['credits_used'],
            'balance'       => CoachPro_Credits::get_balance( $user_id ),
        ) );
    }

    /** Nothing is charged before the answer is saved, so a failure only closes the request. */
    private static function mark_failed( string $id ) {
        global $wpdb;
        $wpdb->update( CoachPro_DB::table('chat_requests'), array('status'=>'failed'), array('id'=>$id, 'status'=>'pending') );
    }

    /** Kept for compatibility with older callers. */
    public static function refund( string $id ) {
        self::mark_failed( $id );
        return true;
    }

    /** Close requests interrupted by a crash or time-out (hourly maintenance). */
    public static function recover_pending() {
        global $wpdb;
        $table = CoachPro_DB::table('chat_requests');
        $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE status = 'pending' AND created_at < UTC_TIMESTAMP() - INTERVAL 10 MINUTE LIMIT 100", ARRAY_A);
        foreach ((array)$rows as $row) {
            $lock = 'cp-chat-' . md5($wpdb->prefix . $row['conversation_id']);
            if ('1' !== (string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock))) continue;
            try {
                // Requests from before 1.5.0 reserved credits up front; give those back.
                if ( (float) $row['credits'] > 0 ) CoachPro_Credits::add( (int) $row['user_id'], (string) $row['credits'], 'refund', $row['message_id'], 'Interrupted chat refunded' );
                self::mark_failed($row['id']);
            } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
        }
    }

    private static function resolve_model_id( array $assistant, string $requested_model_id ) : string {
        // Prefer a usable model (active and with a saved provider key) in this order:
        // the user's pick, the assistant's own model, then the global default.
        $first_active = '';
        foreach ( array( $requested_model_id, (string) ( $assistant['default_model_id'] ?? '' ) ) as $candidate_id ) {
            if ( ! $candidate_id ) continue;
            $candidate = CoachPro_DB::get_row( 'ai_models', $candidate_id );
            if ( ! $candidate || (int) $candidate['is_active'] !== 1 || 'image' === $candidate['category'] ) continue;
            if ( CoachPro_AI_Provider::has_key( $candidate ) ) return $candidate_id;
            if ( ! $first_active ) $first_active = $candidate_id;
        }

        $default = CoachPro_AI_Provider::get_default_model_id();
        $default_row = $default ? CoachPro_DB::get_row( 'ai_models', $default ) : null;
        if ( $default_row && CoachPro_AI_Provider::has_key( $default_row ) ) return $default;
        // Nothing has a key: keep the original choice so the error names the missing key.
        return $first_active ?: $default;
    }

    /** Models the current user may pick in chat. */
    public static function list_models( WP_REST_Request $request ) {
        $user  = get_current_user_id();
        $data  = array();
        foreach ( CoachPro_DB::get_rows( 'ai_models', array( 'is_active' => 1 ), 'created_at ASC', 200 ) as $model ) {
            // Never offer a model whose provider key is missing: picking it could only fail.
            if ( 'image' === $model['category'] || ! CoachPro_AI_Provider::has_key( $model ) || ! CoachPro_Credits::can_use_model( $user, $model ) ) continue;
            // Users only see the name; prices and provider details stay in the admin panel.
            $data[] = array( 'id' => $model['id'], 'name' => $model['display_name'] );
        }
        return rest_ensure_response( $data );
    }
}
