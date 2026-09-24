<?php
/**
 * Class CoachPro_Chat_API
 * POST /wp-json/coachpro/v1/chat
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Chat_API {

    /**
     * Handle a chat request: call AI, save messages, deduct credits.
     */
    public static function handle_chat( WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $conv_id = sanitize_text_field( $request->get_param('conversation_id') ?? '' );
        $lock = 'cp-chat-' . md5( $wpdb->prefix . $conv_id );
        if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock) ) ) return new WP_Error('chat_busy', 'A response is already being generated. Please wait.', array('status' => 409));
        try { return self::send( $request, $user_id, $conv_id ); }
        finally { $wpdb->get_var( $wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock) ); }
    }

    private static function send( WP_REST_Request $request, int $user_id, string $conv_id ) {
        global $wpdb;
        $conv = CoachPro_DB::get_row('conversations', $conv_id);
        if ( ! $conv || (int) $conv['user_id'] !== $user_id ) return new WP_Error('forbidden', 'Conversation not found.', array('status' => 403));
        $assistant = CoachPro_DB::get_row('assistants', $conv['assistant_id']);
        if ( ! $assistant || ! CoachPro_Assistants_API::user_can_use($assistant, $user_id) ) return new WP_Error('forbidden', 'Assistant unavailable.', array('status' => 403));
        $message = sanitize_textarea_field( $request->get_param('message') ?? '' );
        if ( '' === trim($message) || strlen($message) > 50000 ) return new WP_Error('invalid_message', 'Enter a message of up to 50,000 bytes.', array('status' => 400));
        $model_id = self::resolve_model_id($assistant, sanitize_text_field($request->get_param('model_id') ?? ''));
        $model = CoachPro_DB::get_row('ai_models', $model_id);
        if ( ! $model || ! $model['is_active'] || 'image' === $model['category'] ) return new WP_Error('invalid_model', 'Select an active text model.', array('status' => 400));
        if ( ! CoachPro_Credits::can_use_model($user_id, $model) ) return new WP_Error('plan_required', 'Your plan does not include this model.', array('status' => 403));
        $request_id = sanitize_text_field($request->get_param('request_id') ?: wp_generate_uuid4());
        if ( ! preg_match('/^[a-f0-9-]{36}$/i', $request_id) ) return new WP_Error('invalid_request', 'Invalid request ID.', array('status' => 400));
        $existing = CoachPro_DB::get_row('chat_requests', $request_id);
        if ( $existing ) {
            if ( (int) $existing['user_id'] !== $user_id || $existing['conversation_id'] !== $conv_id ) return new WP_Error('conflict', 'Request ID already used.', array('status' => 409));
            if ( 'complete' === $existing['status'] ) return self::response($existing['message_id'], $user_id);
            if ( 'failed' === $existing['status'] ) return new WP_Error('request_failed', 'This request failed and any reserved credits were refunded. You may retry with a new request ID.', array('status'=>409));
            return new WP_Error('request_processed', 'This request is pending or failed. Refresh the conversation before retrying.', array('status' => 409));
        }
        $message_id = wp_generate_uuid4();
        $cost = max(0, (int) $model['credits_cost']);
        $reserved = CoachPro_DB::transaction(function() use ($user_id, $cost, $message_id, $model_id, $request_id, $conv_id) {
            global $wpdb;
            $result = CoachPro_Credits::deduct($user_id, $cost, $message_id, $model_id);
            if ( is_wp_error($result) ) return $result;
            $result = $wpdb->insert(CoachPro_DB::table('chat_requests'), array('id'=>$request_id, 'user_id'=>$user_id, 'conversation_id'=>$conv_id, 'message_id'=>$message_id, 'credits'=>$cost, 'status'=>'pending', 'created_at'=>gmdate('Y-m-d H:i:s')));
            return false === $result ? CoachPro_DB::write_error() : true;
        }, $user_id);
        if ( is_wp_error($reserved) ) return $reserved;
        try {
            $table = CoachPro_DB::table('messages');
            $history = $wpdb->get_results($wpdb->prepare("SELECT role, content FROM {$table} WHERE conversation_id = %s ORDER BY created_at DESC, id DESC LIMIT 20", $conv_id), ARRAY_A);
            $messages = array_merge(array(array('role'=>'system', 'content'=>$assistant['system_prompt'])), array_reverse((array) $history), array(array('role'=>'user', 'content'=>$message)));
            $reply = CoachPro_AI_Provider::call($model_id, $messages, $user_id, $conv_id, array('temperature'=>$assistant['temperature'], 'max_tokens'=>$assistant['max_tokens']));
            if ( is_wp_error($reply) ) { self::refund($request_id); return $reply; }
            if ( '' === trim($reply) ) { self::refund($request_id); return new WP_Error('empty_response', 'The provider returned no text. Credits refunded.', array('status'=>502)); }
            $saved = CoachPro_DB::transaction(function() use ($conv_id, $user_id, $message, $reply, $model_id, $cost, $message_id, $request_id) {
                global $wpdb;
                $table = CoachPro_DB::table('messages');
                // Microseconds provide a stable order when both messages are saved in one second.
                $now = microtime(true);
                foreach (array(array('id'=>wp_generate_uuid4(), 'role'=>'user', 'content'=>$message, 'credits_used'=>0, 'created_at'=>gmdate('Y-m-d H:i:s', (int)$now) . sprintf('.%06d', (int)(($now-floor($now))*1000000))), array('id'=>$message_id, 'role'=>'assistant', 'content'=>$reply, 'credits_used'=>$cost, 'created_at'=>gmdate('Y-m-d H:i:s', (int)$now) . sprintf('.%06d', min(999999, (int)(($now-floor($now))*1000000)+1)))) as $row) {
                    if ( false === $wpdb->insert($table, array_merge($row, array('conversation_id'=>$conv_id, 'user_id'=>$user_id, 'model_id'=>$model_id))) ) return CoachPro_DB::write_error();
                }
                if ( false === $wpdb->update(CoachPro_DB::table('conversations'), array('updated_at'=>current_time('mysql')), array('id'=>$conv_id)) ) return CoachPro_DB::write_error();
                return false === $wpdb->update(CoachPro_DB::table('chat_requests'), array('status'=>'complete'), array('id'=>$request_id, 'status'=>'pending')) ? CoachPro_DB::write_error() : true;
            }, $user_id);
            if ( is_wp_error($saved) ) { self::refund($request_id); return $saved; }
            CoachPro_AI_Provider::schedule_summary($conv_id);
            return self::response($message_id, $user_id);
        } catch (Throwable $error) {
            self::refund($request_id);
            return new WP_Error('chat_failed', 'Unable to generate a response. Please try again.', array('status'=>502));
        }
    }
    private static function response(string $id, int $user_id) {
        $row = CoachPro_DB::get_row('messages', $id);
        if ( ! $row ) return new WP_Error('not_found', 'Response no longer exists.', array('status'=>404));
        return rest_ensure_response(array('message_id'=>$id, 'content'=>$row['content'], 'model_id'=>$row['model_id'], 'credits_used'=>(int)$row['credits_used'], 'balance'=>CoachPro_Credits::get_balance($user_id)));
    }
    public static function refund(string $id) {
        $row = CoachPro_DB::get_row('chat_requests', $id);
        if ( ! $row ) return;
        return CoachPro_DB::transaction(function() use($id, $row) {
            global $wpdb;
            $table = CoachPro_DB::table('chat_requests');
            $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %s FOR UPDATE", $id), ARRAY_A);
            if ( ! $current || 'pending' !== $current['status'] ) return true;
            $result = CoachPro_Credits::add((int)$row['user_id'], (int)$row['credits'], 'refund', $row['message_id'], 'Failed chat refunded');
            if ( is_wp_error($result) ) return $result;
            return false === $wpdb->update($table, array('status'=>'failed'), array('id'=>$id)) ? CoachPro_DB::write_error() : true;
        }, (int)$row['user_id']);
    }
    public static function recover_pending() {
        global $wpdb;
        $table = CoachPro_DB::table('chat_requests');
        $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE status = 'pending' AND created_at < UTC_TIMESTAMP() - INTERVAL 10 MINUTE LIMIT 100", ARRAY_A);
        foreach ((array)$rows as $row) {
            $lock = 'cp-chat-' . md5($wpdb->prefix . $row['conversation_id']);
            if ('1' !== (string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock))) continue;
            try { self::refund($row['id']); } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
        }
    }

    private static function resolve_model_id( array $assistant, string $requested_model_id ) : string {
        if ( $requested_model_id ) {
            $requested_model = CoachPro_DB::get_row( 'ai_models', $requested_model_id );
            if ( $requested_model && (int) $requested_model['is_active'] === 1 ) {
                return $requested_model_id;
            }
        }

        if ( ! empty( $assistant['default_model_id'] ) ) {
            $assistant_model = CoachPro_DB::get_row( 'ai_models', $assistant['default_model_id'] );
            if ( $assistant_model && (int) $assistant_model['is_active'] === 1 ) {
                return (string) $assistant['default_model_id'];
            }
        }

        return CoachPro_AI_Provider::get_default_model_id();
    }
}
