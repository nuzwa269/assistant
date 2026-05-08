<?php
/**
 * Class CoachPro_Assistants_API
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Assistants_API {

    public static function list_assistants( WP_REST_Request $request ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $t = CoachPro_DB::table( 'assistants' );

        // Admins see all prebuilt assistants; regular users see only active ones.
        if ( current_user_can( 'manage_options' ) ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT a.*, (SELECT COUNT(*) FROM `" . CoachPro_DB::table('user_active_assistants') . "` uaa WHERE uaa.assistant_id = a.id AND uaa.user_id = %d) AS is_activated
                 FROM `{$t}` a
                 WHERE a.is_prebuilt = 1 OR a.owner_id = %d
                 ORDER BY a.is_prebuilt DESC, a.created_at ASC",
                $user_id, $user_id
            ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        } else {
            // Regular users: active prebuilt assistants + their own custom assistants only.
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT a.*, (SELECT COUNT(*) FROM `" . CoachPro_DB::table('user_active_assistants') . "` uaa WHERE uaa.assistant_id = a.id AND uaa.user_id = %d) AS is_activated
                 FROM `{$t}` a
                 WHERE (a.is_prebuilt = 1 AND a.is_active = 1) OR a.owner_id = %d
                 ORDER BY a.is_prebuilt DESC, a.created_at ASC",
                $user_id, $user_id
            ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        return rest_ensure_response( $rows );
    }

    public static function create_assistant( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $params  = $request->get_json_params();

        if ( ! CoachPro_Credits::can_create_assistant( $user_id ) ) {
            return new WP_Error( 'limit_reached', __( 'Free plan allows max 1 custom assistant. Please upgrade.', 'coachpro-ai' ), array( 'status' => 403 ) );
        }

        $name   = sanitize_text_field( $params['name'] ?? '' );
        $prompt = wp_kses_post( $params['system_prompt'] ?? '' );

        if ( empty( $name ) || empty( $prompt ) ) {
            return new WP_Error( 'missing_fields', __( 'Name and system_prompt are required.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        global $wpdb;
        $id = wp_generate_uuid4();
        $wpdb->insert(
            CoachPro_DB::table( 'assistants' ),
            array(
                'id'              => $id,
                'owner_id'        => $user_id,
                'name'            => $name,
                'description'     => sanitize_textarea_field( $params['description'] ?? '' ),
                'system_prompt'   => $prompt,
                'icon'            => sanitize_text_field( $params['icon'] ?? 'Bot' ),
                'category'        => sanitize_text_field( $params['category'] ?? '' ),
                'is_prebuilt'     => 0,
                'default_model_id'=> sanitize_text_field( $params['default_model_id'] ?? '' ),
                'is_active'       => 1,
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d' )
        );

        return rest_ensure_response( CoachPro_DB::get_row( 'assistants', $id ) );
    }

    public static function update_assistant( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $id      = sanitize_text_field( $request->get_param( 'id' ) );
        $row     = CoachPro_DB::get_row( 'assistants', $id );

        if ( ! $row || ( (int) $row['owner_id'] !== $user_id && ! current_user_can( 'manage_options' ) ) ) {
            return new WP_Error( 'forbidden', __( 'You cannot edit this assistant.', 'coachpro-ai' ), array( 'status' => 403 ) );
        }

        $params = $request->get_json_params();
        $data   = array();

        if ( isset( $params['name'] ) )          $data['name']            = sanitize_text_field( $params['name'] );
        if ( isset( $params['description'] ) )   $data['description']     = sanitize_textarea_field( $params['description'] );
        if ( isset( $params['system_prompt'] ) ) $data['system_prompt']   = wp_kses_post( $params['system_prompt'] );
        if ( isset( $params['icon'] ) )          $data['icon']            = sanitize_text_field( $params['icon'] );
        if ( isset( $params['category'] ) )      $data['category']        = sanitize_text_field( $params['category'] );

        if ( empty( $data ) ) {
            return new WP_Error( 'nothing_to_update', __( 'No data to update.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        global $wpdb;
        $wpdb->update( CoachPro_DB::table( 'assistants' ), $data, array( 'id' => $id ) );
        return rest_ensure_response( CoachPro_DB::get_row( 'assistants', $id ) );
    }

    public static function delete_assistant( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $id      = sanitize_text_field( $request->get_param( 'id' ) );
        $row     = CoachPro_DB::get_row( 'assistants', $id );

        if ( ! $row || ( (int) $row['owner_id'] !== $user_id && ! current_user_can( 'manage_options' ) ) ) {
            return new WP_Error( 'forbidden', __( 'You cannot delete this assistant.', 'coachpro-ai' ), array( 'status' => 403 ) );
        }

        global $wpdb;
        $wpdb->delete( CoachPro_DB::table( 'assistants' ), array( 'id' => $id ) );
        return rest_ensure_response( array( 'deleted' => true ) );
    }

    public static function activate_assistant( WP_REST_Request $request ) {
        $user_id      = get_current_user_id();
        $assistant_id = sanitize_text_field( $request->get_param( 'id' ) );

        $assistant = CoachPro_DB::get_row( 'assistants', $assistant_id );
        if ( ! $assistant ) {
            return new WP_Error( 'not_found', __( 'Assistant not found.', 'coachpro-ai' ), array( 'status' => 404 ) );
        }

        // Non-admins may only activate: active prebuilt assistants OR their own custom assistants.
        if ( ! current_user_can( 'manage_options' ) ) {
            if ( (int) $assistant['is_prebuilt'] === 1 ) {
                if ( ! (int) $assistant['is_active'] ) {
                    return new WP_Error( 'forbidden', __( 'This assistant is not available.', 'coachpro-ai' ), array( 'status' => 403 ) );
                }
            } else {
                // Custom assistant: must be owned by the current user.
                if ( (int) $assistant['owner_id'] !== $user_id ) {
                    return new WP_Error( 'forbidden', __( 'You do not have access to this assistant.', 'coachpro-ai' ), array( 'status' => 403 ) );
                }
            }
        }

        // Check prebuilt activation limit for free users
        if ( $assistant['is_prebuilt'] && ! CoachPro_Credits::can_activate_prebuilt( $user_id ) ) {
            return new WP_Error( 'limit_reached', __( 'Free plan allows 1 active prebuilt assistant. Upgrade to activate more.', 'coachpro-ai' ), array( 'status' => 403 ) );
        }

        global $wpdb;
        // Ignore duplicate
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `" . CoachPro_DB::table('user_active_assistants') . "` WHERE user_id = %d AND assistant_id = %s",
            $user_id, $assistant_id
        ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        if ( ! $existing ) {
            $wpdb->insert(
                CoachPro_DB::table( 'user_active_assistants' ),
                array(
                    'id'           => wp_generate_uuid4(),
                    'user_id'      => $user_id,
                    'assistant_id' => $assistant_id,
                ),
                array( '%s', '%d', '%s' )
            );
        }

        return rest_ensure_response( array( 'activated' => true ) );
    }

    public static function deactivate_assistant( WP_REST_Request $request ) {
        $user_id      = get_current_user_id();
        $assistant_id = sanitize_text_field( $request->get_param( 'id' ) );

        global $wpdb;
        $wpdb->delete(
            CoachPro_DB::table( 'user_active_assistants' ),
            array( 'user_id' => $user_id, 'assistant_id' => $assistant_id ),
            array( '%d', '%s' )
        );

        return rest_ensure_response( array( 'deactivated' => true ) );
    }
}
