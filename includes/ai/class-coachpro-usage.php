<?php
/**
 * Provider response parsing and token counting.
 * Never stores prompts, responses or keys.
 *
 * @package CoachPro_AI_Assistant
 */
if ( ! defined( 'ABSPATH' ) ) exit;

final class CoachPro_Usage {

    /** Free-form context of the current operation (user/model/operation). */
    public static $context = array();
    /** Token usage of the last parsed response. */
    public static $last = null;
    /** Set only by the admin "Test model" action. Kept for compatibility. */
    public static $admin_test = false;
    /** Tokens added up across every successful provider call since reset_tally(). */
    private static $tally = array( 'input' => 0, 'output' => 0, 'estimated' => false, 'calls' => 0 );

    public static function reset_tally() : void {
        self::$tally = array( 'input' => 0, 'output' => 0, 'estimated' => false, 'calls' => 0 );
    }

    public static function tally() : array {
        return self::$tally;
    }

    /** Rough token count when a provider does not report usage (≈4 characters per token). */
    public static function estimate_tokens( string $text ) : int {
        $text = trim( $text );
        return '' === $text ? 0 : (int) ceil( mb_strlen( $text, 'UTF-8' ) / 4 );
    }

    /**
     * Read billable tokens from a provider response body.
     * input  = every prompt token (including cached ones);
     * output = every generated token (including hidden reasoning/thinking).
     */
    public static function tokens( array $body, string $protocol ) : ?array {
        $int = function( $v ) { return is_numeric( $v ) ? max( 0, (int) $v ) : 0; };
        if ( 'gemini' === $protocol ) {
            $u = $body['usageMetadata'] ?? null;
            if ( ! is_array( $u ) || ! isset( $u['promptTokenCount'] ) ) return null;
            return array(
                'input'  => $int( $u['promptTokenCount'] ) + $int( $u['toolUsePromptTokenCount'] ?? 0 ),
                'output' => $int( $u['candidatesTokenCount'] ?? 0 ) + $int( $u['thoughtsTokenCount'] ?? 0 ),
            );
        }
        $u = $body['usage'] ?? null;
        if ( ! is_array( $u ) ) return null;
        if ( 'anthropic' === $protocol ) {
            if ( ! isset( $u['input_tokens'] ) && ! isset( $u['output_tokens'] ) ) return null;
            return array(
                'input'  => $int( $u['input_tokens'] ?? 0 ) + $int( $u['cache_read_input_tokens'] ?? 0 ) + $int( $u['cache_creation_input_tokens'] ?? 0 ),
                'output' => $int( $u['output_tokens'] ?? 0 ),
            );
        }
        if ( ! isset( $u['prompt_tokens'] ) && ! isset( $u['completion_tokens'] ) ) return null;
        // OpenAI-compatible: prompt_tokens already includes cached tokens; completion includes reasoning.
        return array(
            'input'  => $int( $u['prompt_tokens'] ?? 0 ),
            'output' => $int( $u['completion_tokens'] ?? 0 ),
        );
    }

    public static function parse( $response, string $protocol ) {
        self::$last = null;
        if ( is_wp_error( $response ) ) {
            error_log( 'CoachPro AI transport error (' . $protocol . '): ' . $response->get_error_message() );
            return new WP_Error( 'provider_transport', self::is_admin() ? 'Could not reach the AI provider (network error or timeout).' . self::detail( $response->get_error_message() ) : self::USER_RETRY, array( 'status' => 504 ) );
        }
        $code     = (int) wp_remote_retrieve_response_code( $response );
        $raw_body = (string) wp_remote_retrieve_body( $response );
        $body     = json_decode( $raw_body, true );
        if ( ! is_array( $body ) ) {
            error_log( 'CoachPro AI non-JSON response (' . $protocol . ', HTTP ' . $code . '): ' . substr( $raw_body, 0, 300 ) );
            return new WP_Error( 'provider_response', self::is_admin() ? 'The AI provider returned an invalid response (HTTP ' . $code . '). Check the model\'s API Base URL.' . self::detail( wp_strip_all_tags( substr( $raw_body, 0, 200 ) ) ) : self::USER_UNAVAILABLE, array( 'status' => 502 ) );
        }
        if ( $code < 200 || $code >= 300 ) return self::provider_error( $code, $body, $protocol );

        $texts  = array();
        $finish = '';
        if ( 'anthropic' === $protocol ) {
            foreach ( ( $body['content'] ?? array() ) as $part ) if ( ( $part['type'] ?? '' ) === 'text' && is_string( $part['text'] ?? null ) ) $texts[] = $part['text'];
            $finish = (string) ( $body['stop_reason'] ?? '' );
        } elseif ( 'gemini' === $protocol ) {
            foreach ( ( $body['candidates'][0]['content']['parts'] ?? array() ) as $part ) if ( empty( $part['thought'] ) && is_string( $part['text'] ?? null ) ) $texts[] = $part['text'];
            $finish = (string) ( $body['candidates'][0]['finishReason'] ?? ( $body['promptFeedback']['blockReason'] ?? '' ) );
        } else {
            if ( ! empty( $body['error'] ) ) return self::provider_error( $code, $body, $protocol ); // Some gateways send errors with HTTP 200.
            $content = $body['choices'][0]['message']['content'] ?? '';
            if ( is_string( $content ) ) $texts[] = $content;
            elseif ( is_array( $content ) ) foreach ( $content as $part ) if ( is_string( $part['text'] ?? null ) ) $texts[] = $part['text'];
            $finish = (string) ( $body['choices'][0]['finish_reason'] ?? '' );
            // Reasoning text, tool calls and signatures are ignored on purpose.
        }
        $text = implode( "\n", $texts );

        // The provider has done (and billed) the work even when the text is empty.
        self::$last = self::tokens( $body, $protocol );
        self::$last = self::$last ? self::$last + array( 'estimated' => false ) : null;

        if ( '' === trim( $text ) ) {
            $f = strtolower( $finish );
            if ( in_array( $f, array( 'length', 'max_tokens', 'max_output_tokens' ), true ) ) $why = 'The model used its whole output limit (often on internal reasoning) before writing an answer. Increase the assistant\'s Max tokens.';
            elseif ( in_array( $f, array( 'safety', 'content_filter', 'prohibited_content', 'blocklist', 'spii', 'refusal', 'recitation' ), true ) ) $why = 'The provider blocked this response by its safety filter.';
            else $why = 'The AI provider returned no text.';
            error_log( 'CoachPro AI empty response (' . $protocol . '), finish reason: ' . $finish );
            return new WP_Error( 'empty_response', self::is_admin() ? $why . ( $finish ? ' [' . $finish . ']' : '' ) : 'The assistant could not finish this answer. Please try again or ask in a shorter way.', array( 'status' => 502 ) );
        }
        return $text;
    }

    /** Map provider HTTP errors to clear messages; the provider's own text is shown to admins only. */
    private static function provider_error( int $code, array $body, string $protocol ) {
        $err = $body['error'] ?? array();
        $msg = is_array( $err ) ? (string) ( $err['message'] ?? '' ) : (string) $err;
        if ( '' === $msg && isset( $body[0]['error']['message'] ) ) $msg = (string) $body[0]['error']['message'];
        $msg = sanitize_text_field( substr( $msg, 0, 400 ) );
        error_log( 'CoachPro AI provider error (' . $protocol . ', HTTP ' . $code . '): ' . $msg );
        $lower = strtolower( $msg );
        if ( ( false !== strpos( $lower, 'context' ) && ( false !== strpos( $lower, 'length' ) || false !== strpos( $lower, 'window' ) || false !== strpos( $lower, 'limit' ) || false !== strpos( $lower, 'exceed' ) ) ) || false !== strpos( $lower, 'too many tokens' ) || false !== strpos( $lower, 'prompt is too long' ) ) {
            return new WP_Error( 'context_limit', self::is_admin() ? 'AI provider context limit exceeded.' . self::detail( $msg ) : 'This chat has become too long. Please start a new chat.', array( 'status' => 502, 'provider_status' => $code ) );
        }
        switch ( $code ) {
            case 401: case 403: $text = 'The AI provider rejected the API key (invalid, revoked, or without access to this model).'; $c = 'provider_auth'; break;
            case 402: $text = 'The AI provider account has no credit/billing balance.'; $c = 'provider_billing'; break;
            case 404: $text = 'The AI provider does not recognise this model name or endpoint. Check the API Model Name and Base URL.'; $c = 'provider_not_found'; break;
            case 429: $text = 'The AI provider rate limit or quota was reached. Please wait a moment and retry.'; $c = 'provider_rate_limit'; break;
            case 400: case 422: $text = 'The AI provider rejected the request parameters.'; $c = 'provider_bad_request'; break;
            default: $text = $code >= 500 ? 'The AI provider is temporarily unavailable. Please retry shortly.' : 'AI provider rejected the request.'; $c = 'ai_error';
        }
        if ( ! self::is_admin() ) $text = 429 === $code ? 'The assistant is busy right now. Please wait a moment and try again.' : self::USER_UNAVAILABLE;
        else $text .= ' (HTTP ' . $code . ')' . self::detail( $msg );
        return new WP_Error( $c, $text, array( 'status' => ( 429 === $code ? 429 : 502 ) ) );
    }

    const USER_UNAVAILABLE = 'The assistant is temporarily unavailable. Please try again later.';
    const USER_RETRY       = 'Could not reach the assistant. Please try again.';

    /** Technical details (provider, keys, tokens) are shown to administrators only. */
    public static function is_admin() : bool {
        return function_exists( 'current_user_can' ) && ( current_user_can( 'manage_options' ) || current_user_can( 'coachpro_admin' ) );
    }

    private static function detail( string $msg ) : string {
        if ( '' === trim( $msg ) || ! self::is_admin() ) return '';
        return ' Provider says: ' . $msg;
    }

    /**
     * Run one provider call and add its tokens to the tally. When the provider
     * reports no usage, tokens are estimated from the request and reply text.
     */
    public static function request( string $base, string $model, string $protocol, array $body, callable $network ) {
        try {
            $reply = $network();
        } catch ( Throwable $e ) {
            error_log( 'CoachPro AI request exception: ' . $e->getMessage() );
            self::$last = null;
            return new WP_Error( 'provider_transport', self::USER_RETRY, array( 'status' => 502 ) );
        }
        $used = self::$last;
        if ( ! $used && is_string( $reply ) ) {
            $used = array(
                'input'     => self::estimate_tokens( (string) wp_json_encode( $body['messages'] ?? $body['contents'] ?? $body ) . ( is_string( $body['system'] ?? null ) ? $body['system'] : '' ) ),
                'output'    => self::estimate_tokens( $reply ),
                'estimated' => true,
            );
        }
        if ( $used ) {
            self::$tally['input']    += (int) $used['input'];
            self::$tally['output']   += (int) $used['output'];
            self::$tally['estimated'] = self::$tally['estimated'] || ! empty( $used['estimated'] );
            self::$tally['calls']++;
        }
        return $reply;
    }
}
