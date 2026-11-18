<?php
/**
 * Class CoachPro_Admin_API
 * Admin-only REST endpoints.
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Admin_API {

    public static function get_provider_definitions() : array {
        return array(
            'openai' => array(
                'id'             => 'openai',
                'label'          => 'OpenAI',
                'provider'       => 'OpenAI',
                'provider_type'  => 'openai_compatible',
                'api_key_option' => 'coachpro_openai_key',
                'api_base_url'   => 'https://api.openai.com/v1',
            ),
            'anthropic' => array(
                'id'             => 'anthropic',
                'label'          => 'Anthropic',
                'provider'       => 'Anthropic',
                'provider_type'  => 'anthropic',
                'api_key_option' => 'coachpro_anthropic_key',
                'api_base_url'   => 'https://api.anthropic.com',
            ),
            'gemini' => array(
                'id'             => 'gemini',
                'label'          => 'Google Gemini',
                'provider'       => 'Google Gemini',
                'provider_type'  => 'gemini',
                'api_key_option' => 'coachpro_gemini_key',
                'api_base_url'   => 'https://generativelanguage.googleapis.com/v1beta',
            ),
            'custom' => array(
                'id'                => 'custom',
                'label'             => 'Custom / Other',
                'provider'          => 'Custom',
                'provider_type'     => 'openai_compatible',
                'api_key_option'    => 'coachpro_custom_key',
                'api_base_url'      => '',
                'requires_base_url' => true,
            ),
        );
    }

    private static function verify_admin_nonce( WP_REST_Request $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce ) {
            $nonce = $request->get_param( '_wpnonce' );
        }

        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid or expired nonce.', 'coachpro-ai' ), array( 'status' => 403 ) );
        }

        return true;
    }

    private static function normalize_provider_key( string $provider ) : string {
        $normalized = strtolower( trim( $provider ) );

        if ( in_array( $normalized, array( 'openai', 'open-ai' ), true ) ) {
            return 'openai';
        }
        if ( in_array( $normalized, array( 'anthropic', 'claude' ), true ) ) {
            return 'anthropic';
        }
        if ( in_array( $normalized, array( 'gemini', 'google', 'google gemini', 'google-gemini' ), true ) ) {
            return 'gemini';
        }
        if ( in_array( $normalized, array( 'custom', 'other', 'custom / other', 'custom/other' ), true ) ) {
            return 'custom';
        }

        return '';
    }

    private static function get_provider_definition( string $provider ) : array {
        $key         = self::normalize_provider_key( $provider );
        $definitions = self::get_provider_definitions();
        return $definitions[ $key ] ?? array();
    }

    private static function sanitize_temperature( $temperature ) : float {
        $temperature = is_numeric( $temperature ) ? (float) $temperature : 0.7;
        return max( 0, min( 2, $temperature ) );
    }

    private static function sanitize_max_tokens( $max_tokens ) : ?int {
        if ( '' === $max_tokens || null === $max_tokens ) {
            return null;
        }

        $max_tokens = absint( $max_tokens );
        return $max_tokens > 0 ? $max_tokens : null;
    }

    /** Credits per 1,000 tokens: 0 – 100000 with up to 6 decimals. */
    private static function sanitize_rate( $rate ) : string {
        try { $micro = CoachPro_Money::to_micro( is_numeric( $rate ) ? (string) $rate : '0' ); }
        catch ( Throwable $e ) { $micro = 0; }
        return CoachPro_Money::format( max( 0, min( 100000 * CoachPro_Money::MICRO, $micro ) ) );
    }

    /** Provider list price in USD per 1M tokens (0 – 10000). */
    private static function sanitize_usd( $usd ) : float {
        return round( max( 0, min( 10000, is_numeric( $usd ) ? (float) $usd : 0 ) ), 4 );
    }

    /** Pricing calculator settings: USD rate, profit %, sample message size. */
    public static function save_pricing_settings( WP_REST_Request $request ) {
        $nonce_check = self::verify_admin_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) return $nonce_check;
        return rest_ensure_response( CoachPro_Pricing::save_settings( (array) $request->get_json_params() ) );
    }

    private static function mask_api_key( string $api_key ) : string {
        $length = strlen( $api_key );
        if ( $length <= 4 ) {
            return str_repeat( '•', max( 4, $length ) );
        }

        return str_repeat( '•', max( 4, $length - 4 ) ) . substr( $api_key, -4 );
    }

    private static function set_default_model( string $model_id ) : void {
        global $wpdb;
        $table = CoachPro_DB::table( 'ai_models' );

        CoachPro_DB::query( "UPDATE `{$table}` SET is_default = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        CoachPro_DB::update(
            $table,
            array(
                'is_default' => 1,
                'is_active'  => 1,
            ),
            array( 'id' => $model_id ),
            array( '%d', '%d' ),
            array( '%s' )
        );
    }

    private static function ensure_default_model() : void {
        if ( CoachPro_AI_Provider::get_default_model_id() ) {
            return;
        }

        global $wpdb;
        $table = CoachPro_DB::table( 'ai_models' );
        $id    = $wpdb->get_var( "SELECT id FROM `{$table}` WHERE is_active = 1 ORDER BY output_credits_per_1k ASC, created_at ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $id ) {
            self::set_default_model( $id );
        }
    }

    private static function apply_model_provider_defaults( array $params, array $data ) : array {
        $provider = $params['provider_name'] ?? $params['provider'] ?? $data['provider'] ?? '';
        $key      = self::normalize_provider_key( (string) $provider );
        $def      = self::get_provider_definition( (string) $provider );

        if ( empty( $def ) ) {
            return $data;
        }

        if ( 'custom' === $key ) {
            $custom_label = sanitize_text_field( (string) ( $params['custom_provider_label'] ?? '' ) );
            if ( '' !== $custom_label ) {
                $data['provider'] = $custom_label;
            } elseif ( empty( $data['provider'] ) || 'custom' === self::normalize_provider_key( (string) $data['provider'] ) ) {
                $data['provider'] = $def['provider'];
            }

            $provider_type = $params['provider_type'] ?? $data['provider_type'] ?? $def['provider_type'];
            if ( ! in_array( $provider_type, array( 'openai_compatible', 'anthropic', 'gemini' ), true ) ) {
                $provider_type = 'openai_compatible';
            }

            $data['provider_type']       = $provider_type;
            $data['api_key_secret_name'] = sanitize_text_field( (string) ( $params['api_key_secret_name'] ?? $data['api_key_secret_name'] ?? '' ) );
            $data['api_base_url']        = esc_url_raw( (string) ( $params['api_base_url'] ?? $data['api_base_url'] ?? '' ) );
            $data['api_model_name']      = sanitize_text_field( (string) ( $params['api_model_name'] ?? $data['api_model_name'] ?? '' ) );

            if ( '' === $data['api_key_secret_name'] ) {
                $data['api_key_secret_name'] = $def['api_key_option'];
            }
            if ( '' === $data['api_base_url'] && 'openai_compatible' === $provider_type ) {
                $data['api_base_url'] = esc_url_raw( (string) get_option( 'coachpro_custom_base_url', '' ) );
            }

            return $data;
        }

        $data['provider']            = $def['provider'];
        $data['provider_type']       = $def['provider_type'];
        $data['api_key_secret_name'] = $def['api_key_option'];
        $data['api_base_url']        = $def['api_base_url'];

        return $data;
    }

    /**
     * A model that uses the Custom provider key must call the Custom provider's
     * Base URL. Sending that key to api.openai.com (an easy mix-up in the form)
     * makes every chat fail with HTTP 401, so the saved Custom URL is used.
     *
     * @param array $data   Fields being written.
     * @param array $merged The full record after the write.
     */
    private static function match_key_and_base_url( array $data, array $merged ) : array {
        if ( 'coachpro_custom_key' !== ( $merged['api_key_secret_name'] ?? '' ) || 'openai_compatible' !== ( $merged['provider_type'] ?? 'openai_compatible' ) ) {
            return $data;
        }
        $custom_base = esc_url_raw( (string) get_option( 'coachpro_custom_base_url', '' ) );
        $host        = strtolower( (string) wp_parse_url( (string) ( $merged['api_base_url'] ?? '' ), PHP_URL_HOST ) );
        if ( '' !== $custom_base && ( '' === $host || 'api.openai.com' === $host ) ) {
            $data['api_base_url'] = $custom_base;
        }
        return $data;
    }

    private static function get_provider_settings_response() : array {
        $providers = array();
        foreach ( self::get_provider_definitions() as $definition ) {
            $api_key     = (string) get_option( $definition['api_key_option'], '' );
            $providers[] = array(
                'id'                => $definition['id'],
                'label'             => $definition['label'],
                'configured'        => '' !== $api_key,
                'masked_key'        => '' !== $api_key ? self::mask_api_key( $api_key ) : '',
                'option_name'       => $definition['api_key_option'],
                'requires_base_url' => ! empty( $definition['requires_base_url'] ),
                'base_url'          => 'custom' === $definition['id'] ? (string) get_option( 'coachpro_custom_base_url', '' ) : '',
            );
        }

        return array(
            'providers'        => $providers,
            'default_model_id' => CoachPro_AI_Provider::get_default_model_id(),
        );
    }

    private static function hydrate_assistant_provider( array $data ) : array {
        if ( ! empty( $data['default_model_id'] ) ) {
            $model = CoachPro_DB::get_row( 'ai_models', $data['default_model_id'] );
            if ( $model ) {
                $data['provider'] = $model['provider'];
            }
        }

        return $data;
    }

    // -------------------------------------------------------------------------
    // Stats
    // -------------------------------------------------------------------------
    public static function get_stats( WP_REST_Request $request ) {
        global $wpdb;

        $total_users    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->users}`" );
        $today          = current_time( 'Y-m-d' );
        $t_msg          = CoachPro_DB::table( 'messages' );
        $t_tx           = CoachPro_DB::table( 'transactions' );

        $messages_today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t_msg}` WHERE DATE(created_at) = %s", $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $used_today     = (float) $wpdb->get_var( $wpdb->prepare( "SELECT -COALESCE(SUM(amount),0) FROM `{$t_tx}` WHERE kind = 'message_deduct' AND DATE(created_at) = %s", $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_credits  = (float) $wpdb->get_var( "SELECT SUM(amount) FROM `{$t_tx}` WHERE amount > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return rest_ensure_response( array(
            'total_users'    => $total_users,
            'messages_today' => $messages_today,
            'credits_used_today' => $used_today,
            'total_credits'  => $total_credits,
        ) );
    }

    // -------------------------------------------------------------------------
    // Users
    // -------------------------------------------------------------------------
    public static function list_users( WP_REST_Request $request ) {
        $users = get_users( array( 'number' => 100, 'orderby' => 'registered', 'order' => 'DESC' ) );
        $data  = array();
        foreach ( $users as $user ) {
            $data[] = array(
                'id'       => $user->ID,
                'username' => $user->user_login,
                'email'    => $user->user_email,
                'name'     => $user->display_name,
                'plan'     => get_user_meta( $user->ID, 'coachpro_plan', true ) ?: 'free',
                'credits'  => CoachPro_Credits::get_balance($user->ID),
                'roles'    => $user->roles,
                'joined'   => $user->user_registered,
            );
        }
        return rest_ensure_response( $data );
    }

    public static function update_user( WP_REST_Request $request ) {
        $id     = absint( $request->get_param( 'id' ) );
        $params = $request->get_json_params();

        if ( isset( $params['plan'] ) ) {
            $result = CoachPro_Credits::assign_plan( $id, sanitize_text_field( (string) $params['plan'] ), ! empty( $params['grant_plan_credits'] ) );
            if ( is_wp_error( $result ) ) return $result;
        }
        if ( isset( $params['credits'] ) ) {
            $new_credits = sanitize_text_field( (string)$params['credits'] );
            $result = CoachPro_Credits::set( $id, $new_credits, 'Admin adjustment via REST API' );
            if (is_wp_error($result)) return $result;
        }

        return rest_ensure_response( array( 'updated' => true ) );
    }

    // -------------------------------------------------------------------------
    // AI Models
    // -------------------------------------------------------------------------
    public static function list_models( WP_REST_Request $request ) {
        global $wpdb;
        $t    = CoachPro_DB::table( 'ai_models' );
        $rows = $wpdb->get_results( "SELECT * FROM `{$t}` ORDER BY is_default DESC, provider ASC, display_name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return rest_ensure_response( $rows );
    }

    public static function create_model( WP_REST_Request $request ) {
        $nonce_check = self::verify_admin_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }

        $params = $request->get_json_params();
        $id     = sanitize_text_field( $params['id'] ?? $params['model_id'] ?? wp_generate_uuid4() );

        $data = array(
            'id'                  => $id,
            'display_name'        => sanitize_text_field( $params['display_name'] ?? $id ),
            'provider'            => sanitize_text_field( $params['provider'] ?? $params['provider_name'] ?? '' ),
            'provider_type'       => in_array( $params['provider_type'] ?? '', array( 'openai_compatible', 'anthropic', 'gemini' ), true ) ? $params['provider_type'] : 'openai_compatible',
            'category'            => in_array( $params['category'] ?? '', array( 'text', 'image', 'reasoning' ), true ) ? $params['category'] : 'text',
            'credits_cost'        => absint( $params['credits_cost'] ?? 1 ),
            'input_credits_per_1k'  => self::sanitize_rate( $params['input_credits_per_1k'] ?? '0' ),
            'output_credits_per_1k' => self::sanitize_rate( $params['output_credits_per_1k'] ?? '0' ),
            'api_input_usd_per_1m'  => self::sanitize_usd( $params['api_input_usd_per_1m'] ?? 0 ),
            'api_output_usd_per_1m' => self::sanitize_usd( $params['api_output_usd_per_1m'] ?? 0 ),
            'min_plan'            => in_array( $params['min_plan'] ?? '', array( 'free', 'basic', 'pro' ), true ) ? $params['min_plan'] : 'free',
            'api_key_secret_name' => sanitize_text_field( $params['api_key_secret_name'] ?? '' ),
            'api_base_url'        => esc_url_raw( $params['api_base_url'] ?? '' ),
            'api_model_name'      => sanitize_text_field( $params['api_model_name'] ?? $params['model_id'] ?? $id ),
            'is_active'           => isset( $params['is_active'] ) ? (int) ! empty( $params['is_active'] ) : 1,
            'is_default'          => isset( $params['is_default'] ) ? (int) ! empty( $params['is_default'] ) : 0,
            'description'         => sanitize_textarea_field( $params['description'] ?? '' ),
        );
        $data = self::apply_model_provider_defaults( $params, $data );
        $data = self::match_key_and_base_url( $data, $data );
        if ( 'gemini' === $data['provider_type'] ) {
            $name = CoachPro_AI_Provider::normalize_gemini_model_name( $data['api_model_name'] );
            if ( is_wp_error( $name ) ) return $name;
            $data['api_model_name'] = $name;
        }
        if ( $data['is_default'] ) {
            $data['is_active'] = 1;
        }

        global $wpdb;
        CoachPro_DB::replace(
            CoachPro_DB::table( 'ai_models' ),
            $data
        );
        if ( $data['is_default'] ) {
            self::set_default_model( $id );
        } else {
            self::ensure_default_model();
        }

        return rest_ensure_response( CoachPro_DB::get_row( 'ai_models', $id ) );
    }

    public static function update_model( WP_REST_Request $request ) {
        $nonce_check = self::verify_admin_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }

        // The route identifies the existing record; JSON/query IDs are editable
        // input and must never redirect an update. Decode the route segment once
        // before lookup (text sanitization strips percent-encoded sequences).
        $url_params = $request->get_url_params();
        $id     = rawurldecode( (string) ( $url_params['id'] ?? '' ) );
        $row    = CoachPro_DB::get_row( 'ai_models', $id );
        if ( ! $row ) return new WP_Error( 'not_found', 'Model not found.', array( 'status' => 404 ) );

        $params = $request->get_json_params();
        $data   = array();

        $text_fields = array( 'display_name', 'provider', 'api_key_secret_name', 'api_model_name', 'description' );
        foreach ( $text_fields as $f ) {
            if ( isset( $params[ $f ] ) ) $data[ $f ] = sanitize_text_field( $params[ $f ] );
        }
        if ( isset( $params['api_base_url'] ) ) $data['api_base_url'] = esc_url_raw( $params['api_base_url'] );
        if ( isset( $params['credits_cost'] ) ) $data['credits_cost'] = absint( $params['credits_cost'] );
        if ( isset( $params['input_credits_per_1k'] ) )  $data['input_credits_per_1k']  = self::sanitize_rate( $params['input_credits_per_1k'] );
        if ( isset( $params['output_credits_per_1k'] ) ) $data['output_credits_per_1k'] = self::sanitize_rate( $params['output_credits_per_1k'] );
        if ( isset( $params['api_input_usd_per_1m'] ) )  $data['api_input_usd_per_1m']  = self::sanitize_usd( $params['api_input_usd_per_1m'] );
        if ( isset( $params['api_output_usd_per_1m'] ) ) $data['api_output_usd_per_1m'] = self::sanitize_usd( $params['api_output_usd_per_1m'] );
        if ( isset( $params['is_active'] ) ) $data['is_active'] = (int) ! empty( $params['is_active'] );
        if ( isset( $params['provider_type'] ) && in_array( $params['provider_type'], array( 'openai_compatible', 'anthropic', 'gemini' ), true ) ) {
            $data['provider_type'] = $params['provider_type'];
        }
        if ( isset( $params['category'] ) && in_array( $params['category'], array( 'text', 'image', 'reasoning' ), true ) ) {
            $data['category'] = $params['category'];
        }
        if ( isset( $params['min_plan'] ) && in_array( $params['min_plan'], array( 'free', 'basic', 'pro' ), true ) ) {
            $data['min_plan'] = $params['min_plan'];
        }
        if ( isset( $params['is_default'] ) ) {
            $data['is_default'] = (int) ! empty( $params['is_default'] );
            if ( $data['is_default'] ) {
                $data['is_active'] = 1;
            }
        }

        $data = self::apply_model_provider_defaults( $params, $data );
        $data = self::match_key_and_base_url( $data, array_merge( $row, $data ) );

        if ( 'gemini' === ( $data['provider_type'] ?? $row['provider_type'] ) ) {
            $name = CoachPro_AI_Provider::normalize_gemini_model_name( $data['api_model_name'] ?? $row['api_model_name'] );
            if ( is_wp_error( $name ) ) return $name;
            $data['api_model_name'] = $name;
        }

        if ( empty( $data ) ) return new WP_Error( 'nothing_to_update', 'No data.', array( 'status' => 400 ) );

        global $wpdb;
        CoachPro_DB::update( CoachPro_DB::table( 'ai_models' ), $data, array( 'id' => $id ) );
        if ( ! empty( $data['is_default'] ) ) {
            self::set_default_model( $id );
        } else {
            self::ensure_default_model();
        }
        return rest_ensure_response( CoachPro_DB::get_row( 'ai_models', $id ) );
    }

    public static function delete_model( WP_REST_Request $request ) {
        $nonce_check = self::verify_admin_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }

        $id = sanitize_text_field( $request->get_param( 'id' ) );
        $row = CoachPro_DB::get_row( 'ai_models', $id );
        global $wpdb;
        CoachPro_DB::delete( CoachPro_DB::table( 'ai_models' ), array( 'id' => $id ) );
        if ( $row && ! empty( $row['is_default'] ) ) {
            self::ensure_default_model();
        }
        return rest_ensure_response( array( 'deleted' => true ) );
    }

    /** Send a real one-line prompt through the exact chat code path and report the result. */
    public static function test_model( WP_REST_Request $request ) {
        $nonce_check = self::verify_admin_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) return $nonce_check;
        $url_params = $request->get_url_params();
        $model = CoachPro_DB::get_row( 'ai_models', rawurldecode( (string) ( $url_params['id'] ?? '' ) ) );
        if ( ! $model ) return new WP_Error( 'not_found', 'Model not found.', array( 'status' => 404 ) );
        if ( 'image' === $model['category'] ) return new WP_Error( 'image_model', 'Image models cannot be tested with a chat prompt.', array( 'status' => 400 ) );
        if ( function_exists( 'set_time_limit' ) ) @set_time_limit( CoachPro_AI_Provider::TIMEOUT + 30 );
        $started = microtime( true );
        $reply   = CoachPro_AI_Provider::test_model( $model );
        if ( is_wp_error( $reply ) ) return $reply;
        return rest_ensure_response( array(
            'success' => true,
            'message' => sprintf( '%s replied in %.1fs: "%s"', $model['display_name'], microtime( true ) - $started, wp_trim_words( sanitize_text_field( $reply ), 20 ) ),
        ) );
    }

    public static function get_provider_settings( WP_REST_Request $request ) {
        return rest_ensure_response( self::get_provider_settings_response() );
    }

    public static function update_provider_settings( WP_REST_Request $request ) {
        $nonce_check = self::verify_admin_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }

        $params    = $request->get_json_params();
        $providers = isset( $params['providers'] ) && is_array( $params['providers'] ) ? $params['providers'] : array();

        foreach ( self::get_provider_definitions() as $key => $definition ) {
            $api_key = trim( (string) ( $providers[ $key ]['api_key'] ?? '' ) );
            if ( '' !== $api_key ) {
                update_option( $definition['api_key_option'], sanitize_text_field( $api_key ), false );
            } elseif ( ! empty( $providers[ $key ]['clear_key'] ) ) {
                // Explicit empty string submitted for this provider \u2014 clear the stored key.
                delete_option( $definition['api_key_option'] );
            }
        }
        $old_base = (string) get_option( 'coachpro_custom_base_url', '' );
        $new_base = esc_url_raw( trim( (string) ( $providers['custom']['base_url'] ?? '' ) ) );
        update_option( 'coachpro_custom_base_url', $new_base, false );
        // Models that followed the old Custom URL move with it, so they keep working.
        if ( '' !== $new_base && $new_base !== $old_base ) {
            global $wpdb;
            $t = CoachPro_DB::table( 'ai_models' );
            $wpdb->query( $wpdb->prepare( "UPDATE `{$t}` SET api_base_url = %s WHERE api_key_secret_name = 'coachpro_custom_key' AND ( api_base_url = %s OR api_base_url = '' )", $new_base, $old_base ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        return rest_ensure_response( self::get_provider_settings_response() );
    }

    public static function test_provider_connection( WP_REST_Request $request ) {
        $nonce_check = self::verify_admin_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }

        $provider_key = self::normalize_provider_key( sanitize_text_field( $request->get_param( 'provider' ) ) );
        $definition   = self::get_provider_definitions()[ $provider_key ] ?? array();
        if ( empty( $definition ) ) {
            return new WP_Error( 'invalid_provider', __( 'Provider not supported.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        $api_key = (string) get_option( $definition['api_key_option'], '' );
        if ( '' === $api_key ) {
            return new WP_Error( 'missing_api_key', __( 'Save an API key before testing the connection.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        switch ( $provider_key ) {
            case 'anthropic':
                $response = wp_remote_get(
                    trailingslashit( $definition['api_base_url'] ) . 'v1/models',
                    array(
                        'timeout' => 20,
                        'headers' => array(
                            'x-api-key'         => $api_key,
                            'anthropic-version' => '2023-06-01',
                        ),
                    )
                );
                break;
            case 'gemini':
                $response = wp_remote_get(
                    trailingslashit( $definition['api_base_url'] ) . 'models?key=' . rawurlencode( $api_key ),
                    array(
                        'timeout' => 20,
                    )
                );
                break;
            case 'custom':
                $base_url = (string) get_option( 'coachpro_custom_base_url', '' );
                if ( empty( $base_url ) ) {
                    return new WP_Error( 'missing_base_url', __( 'Save a Base URL for the Custom provider before testing.', 'coachpro-ai' ), array( 'status' => 400 ) );
                }
                $response = wp_remote_get(
                    trailingslashit( $base_url ) . 'models',
                    array(
                        'timeout' => 20,
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $api_key,
                        ),
                    )
                );
                break;
            case 'openai':
            default:
                $response = wp_remote_get(
                    trailingslashit( $definition['api_base_url'] ) . 'models',
                    array(
                        'timeout' => 20,
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $api_key,
                        ),
                    )
                );
                break;
        }

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'provider_connection_failed', $response->get_error_message(), array( 'status' => 502 ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code < 200 || $code >= 300 ) {
            $message = sanitize_text_field( $body['error']['message'] ?? __( 'Connection failed.', 'coachpro-ai' ) );
            return new WP_Error( 'provider_connection_failed', $message, array( 'status' => $code ?: 502 ) );
        }

        return rest_ensure_response( array(
            'success' => true,
            'message' => sprintf( __( '%s connection successful.', 'coachpro-ai' ), $definition['label'] ),
        ) );
    }

    // -------------------------------------------------------------------------
    // Prebuilt Assistants (admin)
    // -------------------------------------------------------------------------
    public static function list_assistants( WP_REST_Request $request ) {
        global $wpdb;
        $t    = CoachPro_DB::table( 'assistants' );
        $rows = $wpdb->get_results( "SELECT * FROM `{$t}` WHERE is_prebuilt = 1 ORDER BY created_at ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return rest_ensure_response( $rows );
    }

    public static function create_assistant( WP_REST_Request $request ) {
        $nonce_check = self::verify_admin_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }

        $params = $request->get_json_params();
        $id     = wp_generate_uuid4();

        $data = array(
            'id'               => $id,
            'owner_id'         => null,
            'name'             => sanitize_text_field( $params['name'] ?? '' ),
            'description'      => sanitize_textarea_field( $params['description'] ?? '' ),
            'system_prompt'    => wp_kses_post( $params['system_prompt'] ?? '' ),
            'conversation_starters' => wp_json_encode(array_values(array_filter(array_map('sanitize_text_field', (array)($params['conversation_starters'] ?? array()))))),
            'icon'             => sanitize_text_field( $params['icon'] ?? 'Bot' ),
            'category'         => sanitize_text_field( $params['category'] ?? '' ),
            'is_prebuilt'      => 1,
            'provider'         => sanitize_text_field( $params['provider'] ?? '' ),
            'default_model_id' => sanitize_text_field( $params['default_model_id'] ?? $params['model'] ?? '' ),
            'temperature'      => self::sanitize_temperature( $params['temperature'] ?? 0.7 ),
            'max_tokens'       => self::sanitize_max_tokens( $params['max_tokens'] ?? 4096 ),
            'is_active'        => isset( $params['is_active'] ) ? (int) ! empty( $params['is_active'] ) : 1,
        );
        $data = self::hydrate_assistant_provider( $data );
        if ( '' === $data['name'] || '' === $data['system_prompt'] ) {
            return new WP_Error( 'missing_fields', __( 'Name and system_prompt are required.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        global $wpdb;
        CoachPro_DB::insert(
            CoachPro_DB::table( 'assistants' ),
            $data,
            array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%f', '%d', '%d' )
        );

        return rest_ensure_response( CoachPro_DB::get_row( 'assistants', $id ) );
    }

    public static function update_assistant( WP_REST_Request $request ) {
        $nonce_check = self::verify_admin_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }

        $id     = sanitize_text_field( $request->get_param( 'id' ) );
        $row    = CoachPro_DB::get_row( 'assistants', $id );
        if ( ! $row ) return new WP_Error( 'not_found', 'Assistant not found.', array( 'status' => 404 ) );
        if ( (int) $row['is_prebuilt'] !== 1 ) return new WP_Error( 'forbidden', 'Only prebuilt assistants can be edited here.', array( 'status' => 403 ) );

        $params = $request->get_json_params();
        $data   = array();

        if ( isset( $params['name'] ) )          $data['name']            = sanitize_text_field( $params['name'] );
        if ( isset( $params['description'] ) )   $data['description']     = sanitize_textarea_field( $params['description'] );
        if (isset($params['conversation_starters'])) $data['conversation_starters'] = wp_json_encode(array_values(array_filter(array_map('sanitize_text_field', (array)$params['conversation_starters']))));
        if ( isset( $params['system_prompt'] ) ) $data['system_prompt']   = wp_kses_post( $params['system_prompt'] );
        if ( isset( $params['icon'] ) )          $data['icon']            = sanitize_text_field( $params['icon'] );
        if ( isset( $params['category'] ) )      $data['category']        = sanitize_text_field( $params['category'] );
        if ( isset( $params['provider'] ) )      $data['provider']        = sanitize_text_field( $params['provider'] );
        if ( isset( $params['default_model_id'] ) || isset( $params['model'] ) ) {
            $data['default_model_id'] = sanitize_text_field( $params['default_model_id'] ?? $params['model'] );
        }
        if ( isset( $params['temperature'] ) )   $data['temperature']     = self::sanitize_temperature( $params['temperature'] );
        if ( isset( $params['max_tokens'] ) )    $data['max_tokens']      = self::sanitize_max_tokens( $params['max_tokens'] );
        if ( isset( $params['is_active'] ) )     $data['is_active']       = (int) ! empty( $params['is_active'] );

        $data = self::hydrate_assistant_provider( $data );

        if ( empty( $data ) ) return new WP_Error( 'nothing_to_update', 'No data.', array( 'status' => 400 ) );

        global $wpdb;
        CoachPro_DB::update( CoachPro_DB::table( 'assistants' ), $data, array( 'id' => $id ) );
        return rest_ensure_response( CoachPro_DB::get_row( 'assistants', $id ) );
    }

    public static function delete_assistant( WP_REST_Request $request ) {
        $nonce_check = self::verify_admin_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }

        $id = sanitize_text_field( $request->get_param( 'id' ) );
        $row = CoachPro_DB::get_row( 'assistants', $id );
        if ( ! $row ) {
            return new WP_Error( 'not_found', 'Assistant not found.', array( 'status' => 404 ) );
        }
        if ( (int) $row['is_prebuilt'] !== 1 ) {
            return new WP_Error( 'forbidden', 'Only prebuilt assistants can be deleted here.', array( 'status' => 403 ) );
        }
        global $wpdb;
        if (CoachPro_DB::count('conversations', array('assistant_id'=>$id))) return new WP_Error('assistant_in_use', 'Deactivate assistants with existing conversations instead of deleting them.', array('status'=>409));
        CoachPro_DB::delete(CoachPro_DB::table('user_active_assistants'), array('assistant_id'=>$id));
        CoachPro_DB::delete( CoachPro_DB::table( 'assistants' ), array( 'id' => $id ) );
        return rest_ensure_response( array( 'deleted' => true ) );
    }

    // -------------------------------------------------------------------------
    // Plans
    // -------------------------------------------------------------------------
    public static function list_plans( WP_REST_Request $request ) {
        global $wpdb;
        $t    = CoachPro_DB::table( 'plans' );
        $rows = $wpdb->get_results( "SELECT * FROM `{$t}` ORDER BY sort_order ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return rest_ensure_response( $rows );
    }

    public static function create_plan( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $id     = sanitize_text_field( $params['id'] ?? wp_generate_uuid4() );

        global $wpdb;
        CoachPro_DB::replace(
            CoachPro_DB::table( 'plans' ),
            array(
                'id'                    => $id,
                'name'                  => sanitize_text_field( $params['name'] ?? '' ),
                'price_pkr'             => absint( $params['price_pkr'] ?? 0 ),
                'monthly_credits'       => absint( $params['monthly_credits'] ?? 0 ),
                'max_active_assistants' => isset($params['max_active_assistants']) ? absint($params['max_active_assistants']) : null,
                'model_access_level' => max(0, min(2, (int)($params['model_access_level'] ?? 0))),
                'max_projects'          => isset( $params['max_projects'] ) ? absint( $params['max_projects'] ) : null,
                'max_custom_assistants' => isset( $params['max_custom_assistants'] ) ? absint( $params['max_custom_assistants'] ) : null,
                'max_saved_responses'   => isset( $params['max_saved_responses'] ) ? absint( $params['max_saved_responses'] ) : null,
                'features'              => isset( $params['features'] ) ? wp_json_encode( $params['features'] ) : null,
                'is_popular'            => isset( $params['is_popular'] ) ? (int) $params['is_popular'] : 0,
                'is_active'             => isset( $params['is_active'] ) ? (int) $params['is_active'] : 1,
                'sort_order'            => absint( $params['sort_order'] ?? 0 ),
            )
        );

        return rest_ensure_response( CoachPro_DB::get_row( 'plans', $id ) );
    }

    public static function update_plan( WP_REST_Request $request ) {
        $id     = sanitize_text_field( $request->get_param( 'id' ) );
        $row    = CoachPro_DB::get_row( 'plans', $id );
        if ( ! $row ) return new WP_Error( 'not_found', 'Plan not found.', array( 'status' => 404 ) );

        $params = $request->get_json_params();
        $data   = array();
        foreach (array('max_projects', 'max_custom_assistants', 'max_saved_responses', 'max_active_assistants') as $field) {
            if (array_key_exists($field, $params)) $data[$field] = null === $params[$field] || '' === $params[$field] ? null : absint($params[$field]);
        }
        if (isset($params['model_access_level'])) $data['model_access_level'] = max(0, min(2, (int)$params['model_access_level']));

        if ( isset( $params['name'] ) )            $data['name']            = sanitize_text_field( $params['name'] );
        if ( isset( $params['price_pkr'] ) )       $data['price_pkr']       = absint( $params['price_pkr'] );
        if ( isset( $params['monthly_credits'] ) ) $data['monthly_credits'] = absint( $params['monthly_credits'] );
        if ( isset( $params['is_popular'] ) )      $data['is_popular']      = (int) $params['is_popular'];
        if ( isset( $params['is_active'] ) )       $data['is_active']       = (int) $params['is_active'];
        if ( isset( $params['sort_order'] ) )      $data['sort_order']      = absint( $params['sort_order'] );
        if ( isset( $params['features'] ) )        $data['features']        = wp_json_encode( $params['features'] );

        if ( empty( $data ) ) return new WP_Error( 'nothing_to_update', 'No data.', array( 'status' => 400 ) );

        if ('free'===$id && isset($data['monthly_credits']) && (int)$data['monthly_credits']!==0) return new WP_Error('free_credit_policy','Free credits are exactly 20 once on signup. Recurring Free grants are disabled.',array('status'=>409));
        global $wpdb;
        CoachPro_DB::update( CoachPro_DB::table( 'plans' ), $data, array( 'id' => $id ) );
        return rest_ensure_response( CoachPro_DB::get_row( 'plans', $id ) );
    }

    public static function delete_plan( WP_REST_Request $request ) {
        $nonce_check = self::verify_admin_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) return $nonce_check;
        $id  = sanitize_text_field( $request->get_param( 'id' ) );
        $row = CoachPro_DB::get_row( 'plans', $id );
        if ( ! $row ) return new WP_Error( 'not_found', 'Plan not found.', array( 'status' => 404 ) );
        global $wpdb;
        if ('free' === $id || CoachPro_DB::count('payments', array('plan_id'=>$id)) || get_users(array('meta_key'=>'coachpro_plan', 'meta_value'=>$id, 'number'=>1, 'fields'=>'ID'))) return new WP_Error('plan_in_use', 'This plan is in use. Deactivate it to stop new purchases.', array('status'=>409));
        CoachPro_DB::delete( CoachPro_DB::table( 'plans' ), array( 'id' => $id ) );
        return rest_ensure_response( array( 'deleted' => true ) );
    }

    // -------------------------------------------------------------------------
    // Credit Packs (admin)
    // -------------------------------------------------------------------------
    public static function list_packs( WP_REST_Request $request ) {
        global $wpdb;
        $t    = CoachPro_DB::table( 'credit_packs' );
        $rows = $wpdb->get_results( "SELECT * FROM `{$t}` ORDER BY sort_order ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return rest_ensure_response( $rows );
    }

    public static function create_pack( WP_REST_Request $request ) {
        $nonce_check = self::verify_admin_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) return $nonce_check;
        $params = $request->get_json_params();
        $id     = sanitize_text_field( $params['id'] ?? wp_generate_uuid4() );
        global $wpdb;
        CoachPro_DB::replace(
            CoachPro_DB::table( 'credit_packs' ),
            array(
                'id'         => $id,
                'name'       => sanitize_text_field( $params['name'] ?? '' ),
                'credits'    => absint( $params['credits'] ?? 0 ),
                'price_pkr'  => absint( $params['price_pkr'] ?? 0 ),
                'is_popular' => isset( $params['is_popular'] ) ? (int) $params['is_popular'] : 0,
                'is_active'  => isset( $params['is_active'] ) ? (int) $params['is_active'] : 1,
                'sort_order' => absint( $params['sort_order'] ?? 0 ),
            )
        );
        return rest_ensure_response( CoachPro_DB::get_row( 'credit_packs', $id ) );
    }

    public static function update_pack( WP_REST_Request $request ) {
        $nonce_check = self::verify_admin_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) return $nonce_check;
        $id  = sanitize_text_field( $request->get_param( 'id' ) );
        $row = CoachPro_DB::get_row( 'credit_packs', $id );
        if ( ! $row ) return new WP_Error( 'not_found', 'Pack not found.', array( 'status' => 404 ) );
        $params = $request->get_json_params();
        $data   = array();
        if ( isset( $params['name'] ) )       $data['name']       = sanitize_text_field( $params['name'] );
        if ( isset( $params['credits'] ) )    $data['credits']    = absint( $params['credits'] );
        if ( isset( $params['price_pkr'] ) )  $data['price_pkr']  = absint( $params['price_pkr'] );
        if ( isset( $params['is_popular'] ) ) $data['is_popular'] = (int) $params['is_popular'];
        if ( isset( $params['is_active'] ) )  $data['is_active']  = (int) $params['is_active'];
        if ( isset( $params['sort_order'] ) ) $data['sort_order'] = absint( $params['sort_order'] );
        if ( empty( $data ) ) return new WP_Error( 'nothing_to_update', 'No data.', array( 'status' => 400 ) );
        global $wpdb;
        CoachPro_DB::update( CoachPro_DB::table( 'credit_packs' ), $data, array( 'id' => $id ) );
        return rest_ensure_response( CoachPro_DB::get_row( 'credit_packs', $id ) );
    }

    public static function delete_pack( WP_REST_Request $request ) {
        $nonce_check = self::verify_admin_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) return $nonce_check;
        $id  = sanitize_text_field( $request->get_param( 'id' ) );
        $row = CoachPro_DB::get_row( 'credit_packs', $id );
        if ( ! $row ) return new WP_Error( 'not_found', 'Pack not found.', array( 'status' => 404 ) );
        global $wpdb;
        if (CoachPro_DB::count('payments', array('pack_id'=>$id))) return new WP_Error('pack_in_use', 'This pack has payment history. Deactivate it instead.', array('status'=>409));
        CoachPro_DB::delete( CoachPro_DB::table( 'credit_packs' ), array( 'id' => $id ) );
        return rest_ensure_response( array( 'deleted' => true ) );
    }
}
