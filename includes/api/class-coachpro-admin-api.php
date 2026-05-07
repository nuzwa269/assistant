<?php
/**
 * Class CoachPro_Admin_API
 * Admin-only REST endpoints.
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Admin_API {

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
        $rows = $wpdb->get_results( "SELECT * FROM `{$t}` ORDER BY credits_cost ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return rest_ensure_response( $rows );
    }

    public static function create_model( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $id     = sanitize_text_field( $params['id'] ?? wp_generate_uuid4() );

        global $wpdb;
        $wpdb->replace(
            CoachPro_DB::table( 'ai_models' ),
            array(
                'id'                  => $id,
                'display_name'        => sanitize_text_field( $params['display_name'] ?? '' ),
                'provider'            => sanitize_text_field( $params['provider'] ?? '' ),
                'provider_type'       => in_array( $params['provider_type'] ?? '', array( 'openai_compatible', 'anthropic', 'lovable' ), true ) ? $params['provider_type'] : 'openai_compatible',
                'category'            => in_array( $params['category'] ?? '', array( 'text', 'image', 'reasoning' ), true ) ? $params['category'] : 'text',
                'credits_cost'        => absint( $params['credits_cost'] ?? 1 ),
                'min_plan'            => in_array( $params['min_plan'] ?? '', array( 'free', 'basic', 'pro' ), true ) ? $params['min_plan'] : 'free',
                'api_key_secret_name' => sanitize_text_field( $params['api_key_secret_name'] ?? '' ),
                'api_base_url'        => esc_url_raw( $params['api_base_url'] ?? '' ),
                'api_model_name'      => sanitize_text_field( $params['api_model_name'] ?? '' ),
                'is_active'           => isset( $params['is_active'] ) ? (int) $params['is_active'] : 1,
                'description'         => sanitize_textarea_field( $params['description'] ?? '' ),
            )
        );

        return rest_ensure_response( CoachPro_DB::get_row( 'ai_models', $id ) );
    }

    public static function update_model( WP_REST_Request $request ) {
        $id     = sanitize_text_field( $request->get_param( 'id' ) );
        $row    = CoachPro_DB::get_row( 'ai_models', $id );
        if ( ! $row ) return new WP_Error( 'not_found', 'Model not found.', array( 'status' => 404 ) );

        $params = $request->get_json_params();
        $data   = array();

        $text_fields = array( 'display_name', 'provider', 'api_key_secret_name', 'api_model_name', 'description' );
        foreach ( $text_fields as $f ) {
            if ( isset( $params[ $f ] ) ) $data[ $f ] = sanitize_text_field( $params[ $f ] );
        }
        if ( isset( $params['api_base_url'] ) )  $data['api_base_url']  = esc_url_raw( $params['api_base_url'] );
        if ( isset( $params['credits_cost'] ) )  $data['credits_cost']  = absint( $params['credits_cost'] );
        if ( isset( $params['is_active'] ) )     $data['is_active']     = (int) $params['is_active'];
        if ( isset( $params['provider_type'] ) && in_array( $params['provider_type'], array( 'openai_compatible', 'anthropic', 'lovable' ), true ) ) {
            $data['provider_type'] = $params['provider_type'];
        }
        if ( isset( $params['min_plan'] ) && in_array( $params['min_plan'], array( 'free', 'basic', 'pro' ), true ) ) {
            $data['min_plan'] = $params['min_plan'];
        }

        if ( empty( $data ) ) return new WP_Error( 'nothing_to_update', 'No data.', array( 'status' => 400 ) );

        global $wpdb;
        $wpdb->update( CoachPro_DB::table( 'ai_models' ), $data, array( 'id' => $id ) );
        return rest_ensure_response( CoachPro_DB::get_row( 'ai_models', $id ) );
    }

    public static function delete_model( WP_REST_Request $request ) {
        $id = sanitize_text_field( $request->get_param( 'id' ) );
        global $wpdb;
        $wpdb->delete( CoachPro_DB::table( 'ai_models' ), array( 'id' => $id ) );
        return rest_ensure_response( array( 'deleted' => true ) );
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
        $params = $request->get_json_params();
        $id     = wp_generate_uuid4();

        global $wpdb;
        $wpdb->insert(
            CoachPro_DB::table( 'assistants' ),
            array(
                'id'              => $id,
                'owner_id'        => null,
                'name'            => sanitize_text_field( $params['name'] ?? '' ),
                'description'     => sanitize_textarea_field( $params['description'] ?? '' ),
                'system_prompt'   => wp_kses_post( $params['system_prompt'] ?? '' ),
                'icon'            => sanitize_text_field( $params['icon'] ?? 'Bot' ),
                'category'        => sanitize_text_field( $params['category'] ?? '' ),
                'is_prebuilt'     => 1,
                'default_model_id'=> sanitize_text_field( $params['default_model_id'] ?? '' ),
                'is_active'       => 1,
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d' )
        );

        return rest_ensure_response( CoachPro_DB::get_row( 'assistants', $id ) );
    }

    public static function update_assistant( WP_REST_Request $request ) {
        $id     = sanitize_text_field( $request->get_param( 'id' ) );
        $row    = CoachPro_DB::get_row( 'assistants', $id );
        if ( ! $row ) return new WP_Error( 'not_found', 'Assistant not found.', array( 'status' => 404 ) );

        $params = $request->get_json_params();
        $data   = array();

        if ( isset( $params['name'] ) )          $data['name']            = sanitize_text_field( $params['name'] );
        if ( isset( $params['description'] ) )   $data['description']     = sanitize_textarea_field( $params['description'] );
        if ( isset( $params['system_prompt'] ) ) $data['system_prompt']   = wp_kses_post( $params['system_prompt'] );
        if ( isset( $params['icon'] ) )          $data['icon']            = sanitize_text_field( $params['icon'] );
        if ( isset( $params['category'] ) )      $data['category']        = sanitize_text_field( $params['category'] );
        if ( isset( $params['is_active'] ) )     $data['is_active']       = (int) $params['is_active'];

        if ( empty( $data ) ) return new WP_Error( 'nothing_to_update', 'No data.', array( 'status' => 400 ) );

        global $wpdb;
        $wpdb->update( CoachPro_DB::table( 'assistants' ), $data, array( 'id' => $id ) );
        return rest_ensure_response( CoachPro_DB::get_row( 'assistants', $id ) );
    }

    public static function delete_assistant( WP_REST_Request $request ) {
        $id = sanitize_text_field( $request->get_param( 'id' ) );
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
