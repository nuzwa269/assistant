<?php
/**
 * Class CoachPro_Profile_API
 * Profile, transactions, and saved responses endpoints.
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Profile_API {

    private static function owns_or_admin( $resource_user_id ) : bool {
        $current = get_current_user_id();
        if ( (int) $resource_user_id === $current ) {
            return true;
        }
        if ( current_user_can( 'coachpro_admin' ) || current_user_can( 'manage_options' ) ) {
            return true;
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Profile
    // -------------------------------------------------------------------------
    public static function get_profile( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        return rest_ensure_response( CoachPro_Auth::user_data( $user_id ) );
    }

    public static function update_profile( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $params  = $request->get_json_params();

        $args = array( 'ID' => $user_id );

        if ( ! empty( $params['display_name'] ) ) {
            $args['display_name'] = sanitize_text_field( $params['display_name'] );
        }
        if ( ! empty( $params['email'] ) && is_email( $params['email'] ) ) {
            $args['user_email'] = sanitize_email( $params['email'] );
        }
        if ( ! empty( $params['password'] ) ) {
            $args['user_pass'] = $params['password'];
        }

        $result = wp_update_user( $args );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( CoachPro_Auth::user_data( $user_id ) );
    }

    // -------------------------------------------------------------------------
    // Transactions
    // -------------------------------------------------------------------------
    public static function get_transactions( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $where   = ( current_user_can( 'coachpro_admin' ) || current_user_can( 'manage_options' ) )
            ? array()
            : array( 'user_id' => $user_id );
        $rows    = CoachPro_DB::get_rows( 'transactions', $where, 'created_at DESC', 50 );
        return rest_ensure_response( $rows );
    }

    // -------------------------------------------------------------------------
    // Saved Responses
    // -------------------------------------------------------------------------
    public static function get_saved_responses( WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();

        // Join with messages to get content
        $t_saved = CoachPro_DB::table( 'saved_responses' );
        $t_msg   = CoachPro_DB::table( 'messages' );

        if ( current_user_can( 'coachpro_admin' ) || current_user_can( 'manage_options' ) ) {
            $rows = $wpdb->get_results(
                "SELECT sr.*, m.content, m.role, m.model_id, m.created_at AS message_created_at
                 FROM `{$t_saved}` sr
                 LEFT JOIN `{$t_msg}` m ON m.id = sr.message_id
                 ORDER BY sr.created_at DESC
                 LIMIT 100",
                ARRAY_A
            ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        } else {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT sr.*, m.content, m.role, m.model_id, m.created_at AS message_created_at
                 FROM `{$t_saved}` sr
                 LEFT JOIN `{$t_msg}` m ON m.id = sr.message_id
                 WHERE sr.user_id = %d
                 ORDER BY sr.created_at DESC
                 LIMIT 100",
                $user_id
            ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        return rest_ensure_response( $rows );
    }

    public static function save_response( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $params  = $request->get_json_params();

        if ( ! CoachPro_Credits::can_save_response( $user_id ) ) {
            return new WP_Error( 'limit_reached', __( 'Free plan allows max 10 saved responses. Please upgrade.', 'coachpro-ai' ), array( 'status' => 403 ) );
        }

        $message_id = sanitize_text_field( $params['message_id'] ?? '' );
        if ( empty( $message_id ) ) {
            return new WP_Error( 'missing_fields', __( 'message_id is required.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        $message = CoachPro_DB::get_row( 'messages', $message_id );
        if ( ! $message ) {
            return new WP_Error( 'not_found', __( 'Message not found.', 'coachpro-ai' ), array( 'status' => 404 ) );
        }
        if ( ! self::owns_or_admin( $message['user_id'] ) ) {
            return new WP_Error( 'forbidden', __( 'Access denied.', 'coachpro-ai' ), array( 'status' => 403 ) );
        }

        global $wpdb;
        $id = wp_generate_uuid4();
        $result = $wpdb->insert(
            CoachPro_DB::table( 'saved_responses' ),
            array(
                'id'         => $id,
                'user_id'    => $user_id,
                'message_id' => $message_id,
                'project_id' => sanitize_text_field( $params['project_id'] ?? '' ) ?: null,
                'note'       => sanitize_textarea_field( $params['note'] ?? '' ),
            ),
            array( '%s', '%d', '%s', '%s', '%s' )
        );

        if ( false === $result ) {
            return new WP_Error( 'already_saved', __( 'Response already saved.', 'coachpro-ai' ), array( 'status' => 409 ) );
        }

        return rest_ensure_response( CoachPro_DB::get_row( 'saved_responses', $id ) );
    }

    public static function delete_saved_response( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $id      = sanitize_text_field( $request->get_param( 'id' ) );
        $row     = CoachPro_DB::get_row( 'saved_responses', $id );

        if ( ! $row ) {
            return new WP_Error( 'not_found', __( 'Saved response not found.', 'coachpro-ai' ), array( 'status' => 404 ) );
        }
        if ( ! self::owns_or_admin( $row['user_id'] ) ) {
            return new WP_Error( 'forbidden', __( 'Access denied.', 'coachpro-ai' ), array( 'status' => 403 ) );
        }

        global $wpdb;
        $wpdb->delete( CoachPro_DB::table( 'saved_responses' ), array( 'id' => $id ) );
        return rest_ensure_response( array( 'deleted' => true ) );
    }
}
