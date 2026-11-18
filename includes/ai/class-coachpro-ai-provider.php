<?php
/**
 * Class CoachPro_AI_Provider
 * Dispatches AI chat requests to OpenAI-compatible, Anthropic, or other endpoints.
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_AI_Provider {

    /** Seconds to wait for a provider. Reasoning models can be slow. */
    const TIMEOUT = 120;

    /**
     * Main dispatcher. Selects provider based on model record.
     *
     * @param string $model_id   ID from coachpro_ai_models table.
     * @param array  $messages   Array of {role, content} objects.
     * @param int    $user_id    For context-overflow retry logging.
     * @param string $conv_id    Conversation ID (for summary lookup).
     * @return string|WP_Error   AI response text.
     */
    public static function call( string $model_id, array $messages, int $user_id = 0, string $conv_id = '', array $options = array() ) {
        $model = CoachPro_DB::get_row( 'ai_models', $model_id );
        if ( ! $model ) {
            return new WP_Error( 'model_not_found', __( 'AI model not found.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        $api_key = self::get_api_key( $model['api_key_secret_name'], (string) $model['api_base_url'] );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', CoachPro_Usage::is_admin() ? __( 'API key not configured for this model. Save it under CoachPro AI → AI Providers.', 'coachpro-ai' ) : CoachPro_Usage::USER_UNAVAILABLE, array( 'status' => 503 ) );
        }

        CoachPro_Usage::$context = array_merge( CoachPro_Usage::$context, array( 'user_id' => $user_id, 'model_id' => $model_id ) );

        // Prepend rolling summary if available
        if ( $conv_id ) {
            $messages = self::prepend_summary( $conv_id, $messages );
        }

        $result = self::dispatch( $model, $api_key, $messages, $options );

        // Context overflow detection: retry once with aggressive summarization.
        if ( is_wp_error( $result ) && 'context_limit' === $result->get_error_code() && $conv_id ) {
            $messages = self::force_summarize_and_trim( $conv_id, $messages );
            CoachPro_Usage::$context['operation'] = 'context_retry';
            $result = self::dispatch( $model, $api_key, $messages, $options );
        }

        return $result;
    }

    /** Send one request using the protocol stored on the model record. */
    public static function dispatch( array $model, string $api_key, array $messages, array $options = array() ) {
        switch ( $model['provider_type'] ) {
            case 'anthropic':
                return self::call_anthropic( (string) $model['api_base_url'], $api_key, (string) $model['api_model_name'], $messages, $options );
            case 'gemini':
                return self::call_gemini( (string) $model['api_base_url'], $api_key, (string) $model['api_model_name'], $messages, $options );
            case 'openai_compatible':
            default:
                return self::call_openai_compatible( (string) $model['api_base_url'], $api_key, (string) $model['api_model_name'], $messages, $options );
        }
    }

    /**
     * Admin-only live check: sends a tiny real prompt to the model and returns
     * the reply or the provider's exact error. Bypasses customer billing.
     */
    public static function test_model( array $model ) {
        $api_key = self::get_api_key( $model['api_key_secret_name'], (string) $model['api_base_url'] );
        if ( '' === $api_key ) {
            return new WP_Error( 'no_api_key', sprintf( 'No API key is saved in option "%s". Save the key under AI Providers first.', $model['api_key_secret_name'] ), array( 'status' => 400 ) );
        }
        $old_context = CoachPro_Usage::$context;
        CoachPro_Usage::$admin_test = true;
        CoachPro_Usage::$context = array( 'user_id' => get_current_user_id(), 'model_id' => $model['id'], 'operation' => 'admin_test' );
        try {
            $messages = array(
                array( 'role' => 'system', 'content' => 'You are a connection test. Reply briefly.' ),
                array( 'role' => 'user', 'content' => 'Reply with the single word: OK' ),
            );
            return self::dispatch( $model, $api_key, $messages, array( 'max_tokens' => 64, 'temperature' => 0.7 ) );
        } finally {
            CoachPro_Usage::$admin_test = false;
            CoachPro_Usage::$context = $old_context;
        }
    }

    // -------------------------------------------------------------------------
    // OpenAI-compatible (also covers OpenRouter)
    // -------------------------------------------------------------------------
    /** True when an API key is saved for this model's provider. */
    public static function has_key( array $model ) : bool {
        return '' !== self::get_api_key( (string) ( $model['api_key_secret_name'] ?? '' ), (string) ( $model['api_base_url'] ?? '' ) );
    }

    /**
     * Global fallback model. The flagged default wins when its provider key is
     * saved; otherwise the cheapest active text model that does have a key is
     * used, so saving only (say) a Gemini key does not leave every assistant
     * pointing at an OpenAI model with no key.
     */
    public static function get_default_model() : ?array {
        global $wpdb;
        $table = CoachPro_DB::table( 'ai_models' );
        $row   = $wpdb->get_row( "SELECT * FROM `{$table}` WHERE is_active = 1 AND is_default = 1 ORDER BY created_at ASC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $row && self::has_key( $row ) ) return $row;

        $candidates = (array) $wpdb->get_results( "SELECT * FROM `{$table}` WHERE is_active = 1 AND category <> 'image' ORDER BY output_credits_per_1k ASC, created_at ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        foreach ( $candidates as $candidate ) {
            if ( self::has_key( $candidate ) ) return $candidate;
        }

        return $row ?: ( $candidates[0] ?? null );
    }

    public static function get_default_model_id() : string {
        $model = self::get_default_model();
        return $model['id'] ?? '';
    }

    /** OpenAI reasoning families reject custom temperature and spend output budget on hidden reasoning. */
    public static function is_openai_reasoning_model( string $model_name ) : bool {
        $bare = strtolower( preg_replace( '#^openai/#i', '', trim( $model_name ) ) );
        return (bool) preg_match( '#^(?:o[1-9](?:$|[-.])|gpt-(?:[5-9]|[1-9][0-9])(?:$|[-.]))#', $bare );
    }

    /** Gemini 2.5+ models "think" inside maxOutputTokens. */
    private static function is_gemini_thinking_model( string $model_name ) : bool {
        return (bool) preg_match( '#^gemini-(?:2\.5|[3-9])#i', $model_name );
    }

    /**
     * Thinking models can use the whole budget before writing visible text,
     * which shows up as an "empty response", so give them a safe floor.
     */
    private static function output_budget( int $requested, bool $thinking ) : int {
        $requested = $requested > 0 ? $requested : 4096;
        if ( $thinking ) {
            $requested = max( $requested, 4096 );
        }
        return $requested;
    }

    /** Accept base URLs entered with or without /v1 or a pasted full endpoint. */
    private static function openai_endpoint( string $base_url ) : string {
        $base = trim( $base_url );
        if ( '' === $base ) {
            $base = (string) get_option( 'coachpro_custom_base_url', '' );
        }
        $base = preg_replace( '#/chat/completions/?$#i', '', $base );
        return trailingslashit( $base ) . 'chat/completions';
    }

    public static function call_openai_compatible( string $base_url, string $api_key, string $model_name, array $messages, array $options = array() ) {
        $endpoint = self::openai_endpoint( $base_url );
        if ( ! wp_http_validate_url( $endpoint ) ) {
            return new WP_Error( 'invalid_base_url', CoachPro_Usage::is_admin() ? 'This model has no valid API Base URL. Edit the model (or the Custom provider) and enter e.g. https://openrouter.ai/api/v1' : CoachPro_Usage::USER_UNAVAILABLE, array( 'status' => 503 ) );
        }
        $native    = 'api.openai.com' === strtolower( (string) parse_url( $endpoint, PHP_URL_HOST ) );
        $reasoning = self::is_openai_reasoning_model( $model_name );
        $messages  = self::clean_messages( $messages, false );

        $body = array(
            'model'    => $model_name,
            'messages' => $messages,
        );
        // Claude via OpenRouter etc. has the same temperature restriction as the native API.
        $no_temperature = $reasoning || ( false !== stripos( $model_name, 'claude' ) && ! self::anthropic_accepts_temperature( $model_name ) );
        if ( ! $no_temperature && isset( $options['temperature'] ) && '' !== $options['temperature'] && null !== $options['temperature'] ) {
            $body['temperature'] = max( 0, min( 2, (float) $options['temperature'] ) );
        }
        $limit = self::output_budget( absint( $options['max_tokens'] ?? 0 ), $reasoning );

        if ( $native ) {
            // api.openai.com: max_tokens is deprecated and rejected by o-series / GPT-5+ models.
            $body['max_completion_tokens'] = $limit;
            if ( preg_match( '#(?:^|/)gpt-6#', $model_name ) ) $body['prompt_cache_options'] = array( 'ttl' => '30m' );
        } else {
            // OpenRouter, Groq, Mistral, DeepSeek, Ollama etc. all accept max_tokens.
            $body['max_tokens'] = $limit;
        }

        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        );
        if ( false !== stripos( $endpoint, 'openrouter.ai' ) ) {
            $headers['HTTP-Referer'] = home_url( '/' );
            $headers['X-Title']      = 'CoachPro AI';
        }

        return CoachPro_Usage::request( $base_url, $model_name, 'openai_compatible', $body, function() use ( $endpoint, $headers, $body ) {
            return CoachPro_Usage::parse( wp_remote_post(
                $endpoint,
                array(
                    'timeout' => self::TIMEOUT,
                    'headers' => $headers,
                    'body'    => wp_json_encode( $body ),
                )
            ), 'openai_compatible' );
        } );
    }

    // -------------------------------------------------------------------------
    // Anthropic
    // -------------------------------------------------------------------------
    public static function call_anthropic( string $base_url, string $api_key, string $model_name, array $messages, array $options = array() ) {
        // Anthropic expects system message separate from messages array
        $system   = '';
        $filtered = array();
        foreach ( self::clean_messages( $messages, true ) as $msg ) {
            if ( 'system' === $msg['role'] ) {
                $system .= ( $system ? "\n\n" : '' ) . $msg['content'];
            } else {
                $filtered[] = $msg;
            }
        }

        $body = array(
            'model'      => $model_name,
            'max_tokens' => ! empty( $options['max_tokens'] ) ? absint( $options['max_tokens'] ) : 4096,
            'messages'   => $filtered,
        );
        if ( $system ) {
            $body['system'] = $system;
        }
        if ( isset( $options['temperature'] ) && '' !== $options['temperature'] && null !== $options['temperature'] ) {
            // Anthropic accepts 0–1 only; assistants allow up to 2.
            $body['temperature'] = max( 0, min( 1, (float) $options['temperature'] ) );
        }

        // Claude 4.7 and later (Opus 4.7/4.8, Sonnet 5, 5.5, Haiku 5.5, Fable, Mythos…) answer HTTP 400
        // to any non-default temperature. Only older models keep the assistant's temperature.
        if ( ! self::anthropic_accepts_temperature( $model_name ) ) unset( $body['temperature'] );

        $base     = preg_replace( '#/(?:v1/messages|v1)/?$#i', '', trim( $base_url ) ) ?: 'https://api.anthropic.com';
        $endpoint = trailingslashit( $base ) . 'v1/messages';
        return CoachPro_Usage::request( $base_url, $model_name, 'anthropic', $body, function() use ( $endpoint, $api_key, $body ) {
            return CoachPro_Usage::parse( wp_remote_post(
                $endpoint,
                array(
                    'timeout' => self::TIMEOUT,
                    'headers' => array(
                        'x-api-key'         => $api_key,
                        'anthropic-version' => '2023-06-01',
                        'Content-Type'      => 'application/json',
                    ),
                    'body'    => wp_json_encode( $body ),
                )
            ), 'anthropic' );
        } );
    }

    private static function anthropic_accepts_temperature( string $model_name ) : bool {
        $bare = strtolower( preg_replace( '#^anthropic/#i', '', trim( $model_name ) ) );
        return (bool) preg_match( '#^claude-(?:3(?:-[57])?-(?:opus|sonnet|haiku)|(?:opus|sonnet|haiku)-4(?:-[0-6])?)(?:-\d{8}|-latest)?$#', $bare );
    }

    /** Store a bare ID; the REST endpoint supplies the models/ resource prefix. */
    public static function normalize_gemini_model_name( string $model_name ) {
        $model_name = preg_replace( '#^models/#', '', trim( $model_name ) );
        if ( ! preg_match( '/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/D', $model_name ) ) {
            return new WP_Error( 'invalid_gemini_model', 'Enter a Gemini API model ID or models/<model-id>, not a display name, provider prefix, or URL.', array( 'status' => 400 ) );
        }
        return $model_name;
    }

    public static function call_gemini( string $base_url, string $api_key, string $model_name, array $messages, array $options = array() ) {
        // Normalize on dispatch too, so existing prefixed records keep working.
        $model_name = self::normalize_gemini_model_name( $model_name );
        if ( is_wp_error( $model_name ) ) return $model_name;
        $system   = '';
        $contents = array();

        foreach ( self::clean_messages( $messages, true ) as $message ) {
            if ( 'system' === $message['role'] ) {
                $system .= ( $system ? "\n\n" : '' ) . $message['content'];
                continue;
            }
            $contents[] = array(
                'role'  => 'assistant' === $message['role'] ? 'model' : 'user',
                'parts' => array( array( 'text' => $message['content'] ) ),
            );
        }

        $body = array( 'contents' => $contents );
        if ( $system ) {
            $body['systemInstruction'] = array( 'parts' => array( array( 'text' => $system ) ) );
        }

        $generation_config = array();
        if ( isset( $options['temperature'] ) && '' !== $options['temperature'] && null !== $options['temperature'] ) {
            $generation_config['temperature'] = max( 0, min( 2, (float) $options['temperature'] ) );
        }
        $generation_config['maxOutputTokens'] = self::output_budget( absint( $options['max_tokens'] ?? 0 ), self::is_gemini_thinking_model( $model_name ) );
        $body['generationConfig'] = $generation_config;

        $base     = trim( $base_url ) ?: 'https://generativelanguage.googleapis.com/v1beta';
        $base     = preg_replace( '#/models(?:/.*)?$#i', '', $base );
        $endpoint = trailingslashit( $base ) . 'models/' . rawurlencode( $model_name ) . ':generateContent';
        return CoachPro_Usage::request( $base_url, $model_name, 'gemini', $body, function() use ( $endpoint, $api_key, $body ) {
            return CoachPro_Usage::parse( wp_remote_post(
                $endpoint,
                array(
                    'timeout' => self::TIMEOUT,
                    'headers' => array(
                        // Header instead of ?key= so the key never lands in proxy/server logs.
                        'x-goog-api-key' => $api_key,
                        'Content-Type'   => 'application/json',
                    ),
                    'body'    => wp_json_encode( $body ),
                )
            ), 'gemini' );
        } );
    }

    /**
     * Normalise roles, drop empty turns and, for strict providers, make the
     * dialogue start with a user turn and alternate user/assistant.
     */
    public static function clean_messages( array $messages, bool $strict ) : array {
        $system = array();
        $turns  = array();
        foreach ( $messages as $m ) {
            $role    = in_array( $m['role'] ?? '', array( 'system', 'user', 'assistant' ), true ) ? $m['role'] : 'user';
            $content = trim( (string) ( $m['content'] ?? '' ) );
            if ( '' === $content ) continue;
            if ( 'system' === $role ) { $system[] = array( 'role' => 'system', 'content' => $content ); continue; }
            if ( $strict && $turns && end( $turns )['role'] === $role ) {
                $turns[ count( $turns ) - 1 ]['content'] .= "\n\n" . $content;
                continue;
            }
            $turns[] = array( 'role' => $role, 'content' => $content );
        }
        if ( $strict ) {
            while ( $turns && 'user' !== $turns[0]['role'] ) array_shift( $turns );
        }
        return array_merge( $system, $turns );
    }

    // -------------------------------------------------------------------------
    // Cron: rolling summary
    // -------------------------------------------------------------------------
    public static function run_summary_cron( string $conv_id ) {
        global $wpdb;
        $lock = 'cp-summary-' . md5($wpdb->prefix . $conv_id);
        if ('1' !== (string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock))) return;
        try { self::generate_summary($conv_id); } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
    }
    public static function schedule_summary(string $conv_id) {
        global $wpdb;
        $count = CoachPro_DB::count('messages', array('conversation_id'=>$conv_id));
        $table = CoachPro_DB::table('conv_summaries');
        $done = (int)$wpdb->get_var($wpdb->prepare("SELECT message_count_at_summary FROM {$table} WHERE conversation_id = %s", $conv_id));
        if ($count - $done >= 20 && !wp_next_scheduled('coachpro_summarize', array($conv_id))) wp_schedule_single_event(time()+30, 'coachpro_summarize', array($conv_id));
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------
    /**
     * Read the key stored under the model's option name. The Settings page
     * stores OpenRouter keys separately from the Custom provider, so either
     * one is accepted for an openrouter.ai model.
     */
    public static function get_api_key( string $secret_name, string $base_url = '' ) : string {
        $key = trim( (string) get_option( $secret_name, '' ) );
        if ( '' === $key && false !== stripos( $base_url ?: (string) get_option( 'coachpro_custom_base_url', '' ), 'openrouter.ai' ) ) {
            foreach ( array( 'coachpro_openrouter_key', 'coachpro_custom_key' ) as $alt ) {
                $key = trim( (string) get_option( $alt, '' ) );
                if ( '' !== $key ) break;
            }
        }
        return $key;
    }

    private static function prepend_summary( string $conv_id, array $messages ) : array {
        global $wpdb;
        $t   = CoachPro_DB::table( 'conv_summaries' );
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT summary, durable_facts FROM `{$t}` WHERE conversation_id = %s LIMIT 1", $conv_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $row && ( $row['summary'] || $row['durable_facts'] ) ) {
            $summary_text  = "Conversation summary so far:\n" . $row['summary'];
            $summary_text .= $row['durable_facts'] ? "\n\nKey facts:\n" . $row['durable_facts'] : '';
            array_unshift( $messages, array( 'role' => 'system', 'content' => $summary_text ) );
        }
        return $messages;
    }

    private static function force_summarize_and_trim( string $conv_id, array $messages ) : array {
        self::run_summary_cron( $conv_id );
        $systems = array_values(array_filter($messages, function($m) { return 'system' === $m['role'] && 0 !== strpos($m['content'], 'Conversation summary so far:'); }));
        $recent = array_slice(array_values(array_filter($messages, function($m) { return 'system' !== $m['role']; })), -10);
        return self::prepend_summary($conv_id, array_merge($systems, $recent));
    }

    private static function generate_summary( string $conv_id ) : void {
        global $wpdb;
        $t_msg = CoachPro_DB::table( 'messages' );

        if (!CoachPro_DB::get_row('conversations', $conv_id)) return;
        $t_sum = CoachPro_DB::table('conv_summaries');
        $previous = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t_sum} WHERE conversation_id = %s", $conv_id), ARRAY_A);
        $offset = $previous ? (int)$previous['message_count_at_summary'] : 0;
        // Process only the next batch, carrying prior memory forward.
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->prepare( "SELECT role, content FROM `{$t_msg}` WHERE conversation_id = %s ORDER BY created_at ASC, id ASC LIMIT 40 OFFSET %d", $conv_id, $offset ),
            ARRAY_A
        );

        if ( empty( $rows ) ) return;

        $text = $previous ? 'Previous summary: ' . $previous['summary'] . "\n\n" : '';
        foreach ( $rows as $r ) {
            $text .= strtoupper( $r['role'] ) . ': ' . $r['content'] . "\n\n";
        }

        // Use the cheapest active model for summarization
        $t_models = CoachPro_DB::table( 'ai_models' );
        $model    = null;
        foreach ( (array) $wpdb->get_results( "SELECT * FROM `{$t_models}` WHERE is_active = 1 AND category <> 'image' ORDER BY output_credits_per_1k ASC, created_at ASC", ARRAY_A ) as $candidate ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            if ( self::has_key( $candidate ) ) { $model = $candidate; break; }
        }
        if ( ! $model ) return;
        $old_context=CoachPro_Usage::$context;
        CoachPro_Usage::$context=array('model_id'=>$model['id'],'operation'=>'summary');
        $summary_options=array('max_tokens'=>1024);

        $prompt = "Summarize this coaching conversation in 2-3 sentences. Also list any important facts (goals, names, dates) as bullet points.\n\nConversation:\n" . $text;
        $messages = array(
            array( 'role' => 'user', 'content' => $prompt ),
        );

        $api_key = self::get_api_key( (string) $model['api_key_secret_name'], (string) $model['api_base_url'] );
        if ( empty( $api_key ) ) { CoachPro_Usage::$context=$old_context; return; }

        $result = self::dispatch( $model, $api_key, $messages, $summary_options );

        CoachPro_Usage::$context=$old_context;
        if ( is_wp_error( $result ) || '' === trim($result) || !CoachPro_DB::get_row('conversations', $conv_id) ) return;

        $t_sum = CoachPro_DB::table( 'conv_summaries' );
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t_sum}` WHERE conversation_id = %s", $conv_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ( $existing ) {
            $summary_saved=$wpdb->update( $t_sum, array( 'summary' => $result, 'message_count_at_summary' => $offset + count( $rows ) ), array( 'conversation_id' => $conv_id ) );
        } else {
            $summary_saved=$wpdb->insert( $t_sum, array(
                'id'                            => wp_generate_uuid4(),
                'conversation_id'               => $conv_id,
                'summary'                       => $result,
                'message_count_at_summary'      => $offset + count( $rows ),
            ) );
        }
        if (false !== $summary_saved) self::schedule_summary($conv_id);
    }
}
