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

        $wpdb->query( "UPDATE `{$table}` SET is_default = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->update(
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
        $id    = $wpdb->get_var( "SELECT id FROM `{$table}` WHERE is_active = 1 ORDER BY credits_cost ASC, created_at ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $id ) {
            self::set_default_model( $id );
        }
    }

    private static function apply_model_provider_defaults( array $params, array $data ) : array {
        $provider = $params['provider_name'] ?? $params['provider'] ?? $data['provider'] ?? '';
        $def      = self::get_provider_definition( (string) $provider );

        if ( empty( $def ) ) {
            return $data;
        }

        $data['provider']            = $def['provider'];
        $data['provider_type']       = $def['provider_type'];
        $data['api_key_secret_name'] = $def['api_key_option'];
        $data['api_base_url']        = $def['api_base_url'];

        return $data;
    }

    private static function get_provider_settings_response() : array {
        $providers = array();
        foreach ( self::get_provider_definitions() as $definition ) {
            $api_key     = (string) get_option( $definition['api_key_option'], '' );
            $providers[] = array(
                'id'          => $definition['id'],
                'label'       => $definition['label'],
                'configured'  => '' !== $api_key,
                'masked_key'  => '' !== $api_key ? self::mask_api_key( $api_key ) : '',
                'option_name' => $definition['api_key_option'],
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
        $t_pay          = CoachPro_DB::table( 'payments' );
        $t_tx           = CoachPro_DB::table( 'transactions' );

        $messages_today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t_msg}` WHERE DATE(created_at) = %s", $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $pending_pays   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$t_pay}` WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_credits  = (int) $wpdb->get_var( "SELECT SUM(amount) FROM `{$t_tx}` WHERE amount > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return rest_ensure_response( array(
            'total_users'    => $total_users,
            'messages_today' => $messages_today,
            'pending_pays'   => $pending_pays,
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
                'credits'  => (int) get_user_meta( $user->ID, 'coachpro_credits', true ),
                'roles'    => $user->roles,
                'joined'   => $user->user_registered,
            );
        }
        return rest_ensure_response( $data );
    }

    public static function update_user( WP_REST_Request $request ) {
        $id     = absint( $request->get_param( 'id' ) );
        $params = $request->get_json_params();

        if ( isset( $params['plan'] ) && in_array( $params['plan'], array( 'free', 'basic', 'pro' ), true ) ) {
            update_user_meta( $id, 'coachpro_plan', $params['plan'] );
        }
        if ( isset( $params['credits'] ) ) {
            $new_credits = absint( $params['credits'] );
            CoachPro_Credits::set( $id, $new_credits, 'Admin adjustment via REST API' );
        }

        return rest_ensure_response( array( 'updated' => true ) );
    }

    // -------------------------------------------------------------------------
    // Payments
    // -------------------------------------------------------------------------
    public static function list_payments( WP_REST_Request $request ) {
        global $wpdb;
        $t    = CoachPro_DB::table( 'payments' );
        $rows = $wpdb->get_results( "SELECT * FROM `{$t}` ORDER BY created_at DESC LIMIT 200", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return rest_ensure_response( $rows );
    }

    public static function approve_payment( WP_REST_Request $request ) {
        $id      = sanitize_text_field( $request->get_param( 'id' ) );
        $payment = CoachPro_DB::get_row( 'payments', $id );

        if ( ! $payment ) {
            return new WP_Error( 'not_found', 'Payment not found.', array( 'status' => 404 ) );
        }
        if ( 'pending' !== $payment['status'] ) {
            return new WP_Error( 'already_processed', 'Payment already processed.', array( 'status' => 409 ) );
        }

        global $wpdb;
        $admin_id    = get_current_user_id();
        $params      = $request->get_json_params();
        $admin_notes = sanitize_textarea_field( $params['admin_notes'] ?? '' );

        $wpdb->update(
            CoachPro_DB::table( 'payments' ),
            array(
                'status'      => 'approved',
                'reviewed_by' => $admin_id,
                'reviewed_at' => current_time( 'mysql' ),
                'admin_notes' => $admin_notes,
            ),
            array( 'id' => $id ),
            array( '%s', '%d', '%s', '%s' ),
            array( '%s' )
        );

        // Grant credits or activate plan
        $user_id = (int) $payment['user_id'];
        if ( 'credit_pack' === $payment['kind'] && $payment['pack_id'] ) {
            $pack = CoachPro_DB::get_row( 'credit_packs', $payment['pack_id'] );
            if ( $pack ) {
                CoachPro_Credits::add( $user_id, (int) $pack['credits'], 'pack_purchase', $id, 'Credit pack purchase approved' );
            }
        } elseif ( 'subscription' === $payment['kind'] && $payment['plan_id'] ) {
            $plan = CoachPro_DB::get_row( 'plans', $payment['plan_id'] );
            update_user_meta( $user_id, 'coachpro_plan', $payment['plan_id'] );
            update_user_meta( $user_id, 'coachpro_plan_renews', gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) ) );
            if ( $plan ) {
                CoachPro_Credits::add( $user_id, (int) $plan['monthly_credits'], 'subscription_grant', $id, 'Subscription activated: ' . $payment['plan_id'] );
            }
        }

        return rest_ensure_response( array( 'approved' => true ) );
    }

    public static function reject_payment( WP_REST_Request $request ) {
        $id      = sanitize_text_field( $request->get_param( 'id' ) );
        $payment = CoachPro_DB::get_row( 'payments', $id );

        if ( ! $payment ) {
            return new WP_Error( 'not_found', 'Payment not found.', array( 'status' => 404 ) );
        }

        $params      = $request->get_json_params();
        $admin_notes = sanitize_textarea_field( $params['admin_notes'] ?? '' );

        global $wpdb;
        $wpdb->update(
            CoachPro_DB::table( 'payments' ),
            array(
                'status'      => 'rejected',
                'reviewed_by' => get_current_user_id(),
                'reviewed_at' => current_time( 'mysql' ),
                'admin_notes' => $admin_notes,
            ),
            array( 'id' => $id ),
            array( '%s', '%d', '%s', '%s' ),
            array( '%s' )
        );

        return rest_ensure_response( array( 'rejected' => true ) );
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
            'provider_type'       => in_array( $params['provider_type'] ?? '', array( 'openai_compatible', 'anthropic', 'gemini', 'lovable' ), true ) ? $params['provider_type'] : 'openai_compatible',
            'category'            => in_array( $params['category'] ?? '', array( 'text', 'image', 'reasoning' ), true ) ? $params['category'] : 'text',
            'credits_cost'        => absint( $params['credits_cost'] ?? 1 ),
            'min_plan'            => in_array( $params['min_plan'] ?? '', array( 'free', 'basic', 'pro' ), true ) ? $params['min_plan'] : 'free',
            'api_key_secret_name' => sanitize_text_field( $params['api_key_secret_name'] ?? '' ),
            'api_base_url'        => esc_url_raw( $params['api_base_url'] ?? '' ),
            'api_model_name'      => sanitize_text_field( $params['api_model_name'] ?? $params['model_id'] ?? $id ),
            'is_active'           => isset( $params['is_active'] ) ? (int) ! empty( $params['is_active'] ) : 1,
            'is_default'          => isset( $params['is_default'] ) ? (int) ! empty( $params['is_default'] ) : 0,
            'description'         => sanitize_textarea_field( $params['description'] ?? '' ),
        );
        $data = self::apply_model_provider_defaults( $params, $data );
        if ( $data['is_default'] ) {
            $data['is_active'] = 1;
        }

        global $wpdb;
        $wpdb->replace(
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

        $id     = sanitize_text_field( $request->get_param( 'id' ) );
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
        if ( isset( $params['is_active'] ) ) $data['is_active'] = (int) ! empty( $params['is_active'] );
        if ( isset( $params['provider_type'] ) && in_array( $params['provider_type'], array( 'openai_compatible', 'anthropic', 'gemini', 'lovable' ), true ) ) {
            $data['provider_type'] = $params['provider_type'];
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

        if ( empty( $data ) ) return new WP_Error( 'nothing_to_update', 'No data.', array( 'status' => 400 ) );

        global $wpdb;
        $wpdb->update( CoachPro_DB::table( 'ai_models' ), $data, array( 'id' => $id ) );
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
        $wpdb->delete( CoachPro_DB::table( 'ai_models' ), array( 'id' => $id ) );
        if ( $row && ! empty( $row['is_default'] ) ) {
            self::ensure_default_model();
        }
        return rest_ensure_response( array( 'deleted' => true ) );
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
            }
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
            'icon'             => sanitize_text_field( $params['icon'] ?? 'Bot' ),
            'category'         => sanitize_text_field( $params['category'] ?? '' ),
            'is_prebuilt'      => 1,
            'provider'         => sanitize_text_field( $params['provider'] ?? '' ),
            'default_model_id' => sanitize_text_field( $params['default_model_id'] ?? $params['model'] ?? '' ),
            'temperature'      => self::sanitize_temperature( $params['temperature'] ?? 0.7 ),
            'max_tokens'       => self::sanitize_max_tokens( $params['max_tokens'] ?? 1024 ),
            'is_active'        => isset( $params['is_active'] ) ? (int) ! empty( $params['is_active'] ) : 1,
        );
        $data = self::hydrate_assistant_provider( $data );
        if ( '' === $data['name'] || '' === $data['system_prompt'] ) {
            return new WP_Error( 'missing_fields', __( 'Name and system_prompt are required.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        global $wpdb;
        $wpdb->insert(
            CoachPro_DB::table( 'assistants' ),
            $data,
            array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%f', '%d', '%d' )
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
        $wpdb->update( CoachPro_DB::table( 'assistants' ), $data, array( 'id' => $id ) );
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
        $wpdb->delete( CoachPro_DB::table( 'assistants' ), array( 'id' => $id ) );
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
        $wpdb->replace(
            CoachPro_DB::table( 'plans' ),
            array(
                'id'                    => $id,
                'name'                  => sanitize_text_field( $params['name'] ?? '' ),
                'price_pkr'             => absint( $params['price_pkr'] ?? 0 ),
                'monthly_credits'       => absint( $params['monthly_credits'] ?? 0 ),
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

        if ( isset( $params['name'] ) )            $data['name']            = sanitize_text_field( $params['name'] );
        if ( isset( $params['price_pkr'] ) )       $data['price_pkr']       = absint( $params['price_pkr'] );
        if ( isset( $params['monthly_credits'] ) ) $data['monthly_credits'] = absint( $params['monthly_credits'] );
        if ( isset( $params['is_popular'] ) )      $data['is_popular']      = (int) $params['is_popular'];
        if ( isset( $params['is_active'] ) )       $data['is_active']       = (int) $params['is_active'];
        if ( isset( $params['sort_order'] ) )      $data['sort_order']      = absint( $params['sort_order'] );
        if ( isset( $params['features'] ) )        $data['features']        = wp_json_encode( $params['features'] );

        if ( empty( $data ) ) return new WP_Error( 'nothing_to_update', 'No data.', array( 'status' => 400 ) );

        global $wpdb;
        $wpdb->update( CoachPro_DB::table( 'plans' ), $data, array( 'id' => $id ) );
        return rest_ensure_response( CoachPro_DB::get_row( 'plans', $id ) );
    }
}
