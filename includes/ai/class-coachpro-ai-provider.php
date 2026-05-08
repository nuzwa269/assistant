<?php
/**
 * Class CoachPro_AI_Provider
 * Dispatches AI chat requests to OpenAI-compatible, Anthropic, or other endpoints.
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_AI_Provider {

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
        global $wpdb;

        $model = CoachPro_DB::get_row( 'ai_models', $model_id );
        if ( ! $model ) {
            return new WP_Error( 'model_not_found', __( 'AI model not found.', 'coachpro-ai' ) );
        }

        $api_key = self::get_api_key( $model['api_key_secret_name'] );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', __( 'API key not configured for this model.', 'coachpro-ai' ) );
        }

        // Prepend rolling summary if available
        if ( $conv_id ) {
            $messages = self::prepend_summary( $conv_id, $messages );
        }

        switch ( $model['provider_type'] ) {
            case 'anthropic':
                $result = self::call_anthropic( $model['api_base_url'], $api_key, $model['api_model_name'], $messages, $options );
                break;
            case 'gemini':
                $result = self::call_gemini( $model['api_base_url'], $api_key, $model['api_model_name'], $messages, $options );
                break;
            case 'openai_compatible':
            default:
                $result = self::call_openai_compatible( $model['api_base_url'], $api_key, $model['api_model_name'], $messages, $options );
                break;
        }

        // Context overflow detection: retry with aggressive summarization
        if ( is_wp_error( $result ) && false !== strpos( $result->get_error_message(), 'context' ) && $conv_id ) {
            $messages = self::force_summarize_and_trim( $conv_id, $messages );
            switch ( $model['provider_type'] ) {
                case 'anthropic':
                    $result = self::call_anthropic( $model['api_base_url'], $api_key, $model['api_model_name'], $messages, $options );
                    break;
                case 'gemini':
                    $result = self::call_gemini( $model['api_base_url'], $api_key, $model['api_model_name'], $messages, $options );
                    break;
                default:
                    $result = self::call_openai_compatible( $model['api_base_url'], $api_key, $model['api_model_name'], $messages, $options );
                    break;
            }
        }

        // Schedule background summary if conversation is growing
        if ( $conv_id && ! is_wp_error( $result ) ) {
            $count = CoachPro_DB::count( 'messages', array( 'conversation_id' => $conv_id ) );
            if ( $count > 0 && 0 === $count % 20 ) {
                wp_schedule_single_event( time() + 30, 'coachpro_summarize', array( $conv_id ) );
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // OpenAI-compatible (also covers OpenRouter)
    // -------------------------------------------------------------------------
    public static function get_default_model() : ?array {
        global $wpdb;
        $table = CoachPro_DB::table( 'ai_models' );
        $row   = $wpdb->get_row( "SELECT * FROM `{$table}` WHERE is_active = 1 AND is_default = 1 ORDER BY created_at ASC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ( ! $row ) {
            $row = $wpdb->get_row( "SELECT * FROM `{$table}` WHERE is_active = 1 ORDER BY credits_cost ASC, created_at ASC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        return $row ?: null;
    }

    public static function get_default_model_id() : string {
        $model = self::get_default_model();
        return $model['id'] ?? '';
    }

    public static function call_openai_compatible( string $base_url, string $api_key, string $model_name, array $messages, array $options = array() ) {
        $body = array(
            'model'    => $model_name,
            'messages' => $messages,
        );
        if ( isset( $options['temperature'] ) && '' !== $options['temperature'] && null !== $options['temperature'] ) {
            $body['temperature'] = (float) $options['temperature'];
        }
        if ( ! empty( $options['max_tokens'] ) ) {
            $body['max_tokens'] = absint( $options['max_tokens'] );
        }

        $response = wp_remote_post(
            trailingslashit( $base_url ) . 'chat/completions',
            array(
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
            )
        );

        return self::parse_openai_response( $response );
    }

    // -------------------------------------------------------------------------
    // Anthropic
    // -------------------------------------------------------------------------
    public static function call_anthropic( string $base_url, string $api_key, string $model_name, array $messages, array $options = array() ) {
        // Anthropic expects system message separate from messages array
        $system   = '';
        $filtered = array();
        foreach ( $messages as $msg ) {
            if ( 'system' === $msg['role'] ) {
                $system = $msg['content'];
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
            $body['temperature'] = (float) $options['temperature'];
        }

        $response = wp_remote_post(
            trailingslashit( $base_url ) . 'v1/messages',
            array(
                'timeout' => 60,
                'headers' => array(
                    'x-api-key'         => $api_key,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type'      => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
            )
        );

        return self::parse_anthropic_response( $response );
    }

    public static function call_gemini( string $base_url, string $api_key, string $model_name, array $messages, array $options = array() ) {
        $system   = '';
        $contents = array();

        foreach ( $messages as $message ) {
            $content = (string) ( $message['content'] ?? '' );
            if ( '' === $content ) {
                continue;
            }

            if ( 'system' === ( $message['role'] ?? '' ) ) {
                $system = $content;
                continue;
            }

            $contents[] = array(
                'role'  => 'assistant' === ( $message['role'] ?? '' ) ? 'model' : 'user',
                'parts' => array(
                    array(
                        'text' => $content,
                    ),
                ),
            );
        }

        $body = array(
            'contents' => $contents,
        );
        if ( $system ) {
            $body['systemInstruction'] = array(
                'parts' => array(
                    array(
                        'text' => $system,
                    ),
                ),
            );
        }

        $generation_config = array();
        if ( isset( $options['temperature'] ) && '' !== $options['temperature'] && null !== $options['temperature'] ) {
            $generation_config['temperature'] = (float) $options['temperature'];
        }
        if ( ! empty( $options['max_tokens'] ) ) {
            $generation_config['maxOutputTokens'] = absint( $options['max_tokens'] );
        }
        if ( ! empty( $generation_config ) ) {
            $body['generationConfig'] = $generation_config;
        }

        $endpoint = trailingslashit( $base_url ) . 'models/' . rawurlencode( $model_name ) . ':generateContent?key=' . rawurlencode( $api_key );
        $response = wp_remote_post(
            $endpoint,
            array(
                'timeout' => 60,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
            )
        );

        return self::parse_gemini_response( $response );
    }

    // -------------------------------------------------------------------------
    // Cron: rolling summary
    // -------------------------------------------------------------------------
    public static function run_summary_cron( string $conv_id ) {
        self::generate_summary( $conv_id );
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------
    private static function get_api_key( string $secret_name ) : string {
        return (string) get_option( $secret_name, '' );
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
        self::generate_summary( $conv_id );
        // Keep only last 10 messages + summary
        $recent = array_slice( $messages, -10 );
        return self::prepend_summary( $conv_id, $recent );
    }

    private static function generate_summary( string $conv_id ) : void {
        global $wpdb;
        $t_msg = CoachPro_DB::table( 'messages' );

        // Get last 40 messages
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->prepare( "SELECT role, content FROM `{$t_msg}` WHERE conversation_id = %s ORDER BY created_at ASC LIMIT 40", $conv_id ),
            ARRAY_A
        );

        if ( empty( $rows ) ) return;

        $text = '';
        foreach ( $rows as $r ) {
            $text .= strtoupper( $r['role'] ) . ': ' . $r['content'] . "\n\n";
        }

        // Use the cheapest active model for summarization
        $t_models = CoachPro_DB::table( 'ai_models' );
        $model    = $wpdb->get_row( "SELECT * FROM `{$t_models}` WHERE is_active = 1 ORDER BY credits_cost ASC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        if ( ! $model ) return;

        $prompt = "Summarize this coaching conversation in 2-3 sentences. Also list any important facts (goals, names, dates) as bullet points.\n\nConversation:\n" . $text;
        $messages = array(
            array( 'role' => 'user', 'content' => $prompt ),
        );

        $api_key = (string) get_option( $model['api_key_secret_name'], '' );
        if ( empty( $api_key ) ) return;

        if ( 'anthropic' === $model['provider_type'] ) {
            $result = self::call_anthropic( $model['api_base_url'], $api_key, $model['api_model_name'], $messages );
        } elseif ( 'gemini' === $model['provider_type'] ) {
            $result = self::call_gemini( $model['api_base_url'], $api_key, $model['api_model_name'], $messages );
        } else {
            $result = self::call_openai_compatible( $model['api_base_url'], $api_key, $model['api_model_name'], $messages );
        }

        if ( is_wp_error( $result ) ) return;

        $t_sum = CoachPro_DB::table( 'conv_summaries' );
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t_sum}` WHERE conversation_id = %s", $conv_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ( $existing ) {
            $wpdb->update( $t_sum, array( 'summary' => $result, 'message_count_at_summary' => count( $rows ) ), array( 'conversation_id' => $conv_id ) );
        } else {
            $wpdb->insert( $t_sum, array(
                'id'                            => wp_generate_uuid4(),
                'conversation_id'               => $conv_id,
                'summary'                       => $result,
                'message_count_at_summary'      => count( $rows ),
            ) );
        }
    }

    private static function parse_openai_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? 'Unknown error from AI provider.';
            return new WP_Error( 'ai_error', $msg, array( 'status' => $code ) );
        }

        return $body['choices'][0]['message']['content'] ?? '';
    }

    private static function parse_anthropic_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? 'Unknown error from Anthropic.';
            return new WP_Error( 'ai_error', $msg, array( 'status' => $code ) );
        }

        return $body['content'][0]['text'] ?? '';
    }

    private static function parse_gemini_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? 'Unknown error from Google Gemini.';
            return new WP_Error( 'ai_error', $msg, array( 'status' => $code ) );
        }

        $parts = $body['candidates'][0]['content']['parts'] ?? array();
        $texts = array();
        foreach ( $parts as $part ) {
            if ( ! empty( $part['text'] ) ) {
                $texts[] = $part['text'];
            }
        }

        return trim( implode( "\n", $texts ) );
    }
}
