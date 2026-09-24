<?php
/**
 * Class CoachPro_Assistants_API
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Assistants_API {

    /**
     * Check whether the given user is allowed to use an assistant.
     * Admins (manage_options) always pass.
     * Regular users may use: active prebuilt assistants OR their own custom assistants.
     *
     * @param array $assistant Row from coachpro_assistants.
     * @param int   $user_id
     * @return bool
     */
    public static function user_can_use( array $assistant, int $user_id ) : bool {
        if ( user_can( $user_id, 'manage_options' ) ) {
            return true;
        }
        if ( (int) $assistant['is_prebuilt'] === 1 ) {
            return (int) $assistant['is_active'] === 1 && in_array( $assistant['id'], CoachPro_Credits::active_prebuilt_ids( $user_id ), true );
        }
        return (int) $assistant['owner_id'] === $user_id && (int)$assistant['is_active'] === 1;
    }

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

        if ( ! current_user_can( 'manage_options' ) ) {
            $effective = CoachPro_Credits::active_prebuilt_ids( $user_id );
            foreach ( $rows as &$row ) {
                if ( (int) $row['is_prebuilt'] !== 1 ) continue;
                $allowed = in_array( $row['id'], $effective, true );
                $row['is_activation_suspended'] = (int) ( ! $allowed && (int) $row['is_activated'] > 0 );
                $row['is_activated'] = (int) $allowed;
            }
            unset( $row );
        }
        return rest_ensure_response( $rows );
    }

    public static function create_assistant( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $params  = $request->get_json_params();

        if ( ! CoachPro_Credits::can_create_assistant( $user_id ) ) {
            return new WP_Error( 'limit_reached', __( 'Your plan limit has been reached. Please upgrade.', 'coachpro-ai' ), array( 'status' => 403 ) );
        }

        $name   = sanitize_text_field( $params['name'] ?? '' );
        $prompt = wp_kses_post( $params['system_prompt'] ?? '' );

        if ( empty( $name ) || empty( $prompt ) ) {
            return new WP_Error( 'missing_fields', __( 'Name and system_prompt are required.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        global $wpdb;
        $id = wp_generate_uuid4();
        CoachPro_DB::insert(
            CoachPro_DB::table( 'assistants' ),
            array(
                'id'              => $id,
                'owner_id'        => $user_id,
                'name'            => $name,
                'description'     => sanitize_textarea_field( $params['description'] ?? '' ),
                'system_prompt'   => $prompt,
                'conversation_starters' => wp_json_encode(array_values(array_filter(array_map('sanitize_text_field', (array)($params['conversation_starters'] ?? array()))))),
                'icon'            => sanitize_text_field( $params['icon'] ?? 'Bot' ),
                'category'        => sanitize_text_field( $params['category'] ?? '' ),
                'is_prebuilt'     => 0,
                'default_model_id'=> sanitize_text_field( $params['default_model_id'] ?? '' ),
                'is_active'       => 1,
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d' )
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

        if (isset($params['conversation_starters'])) $data['conversation_starters'] = wp_json_encode(array_values(array_filter(array_map('sanitize_text_field', (array)$params['conversation_starters']))));
        if (isset($params['default_model_id'])) $data['default_model_id'] = sanitize_text_field($params['default_model_id']);
        if ( isset( $params['name'] ) )          $data['name']            = sanitize_text_field( $params['name'] );
        if ( isset( $params['description'] ) )   $data['description']     = sanitize_textarea_field( $params['description'] );
        if ( isset( $params['system_prompt'] ) ) $data['system_prompt']   = wp_kses_post( $params['system_prompt'] );
        if ( isset( $params['icon'] ) )          $data['icon']            = sanitize_text_field( $params['icon'] );
        if ( isset( $params['category'] ) )      $data['category']        = sanitize_text_field( $params['category'] );

        if ( empty( $data ) ) {
            return new WP_Error( 'nothing_to_update', __( 'No data to update.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        global $wpdb;
        CoachPro_DB::update( CoachPro_DB::table( 'assistants' ), $data, array( 'id' => $id ) );
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
        if (CoachPro_DB::count('conversations', array('assistant_id'=>$id))) return new WP_Error('assistant_in_use', 'Delete this assistant?s conversations first, or keep the assistant for their history.', array('status'=>409));
        CoachPro_DB::delete(CoachPro_DB::table('user_active_assistants'), array('assistant_id'=>$id));
        CoachPro_DB::delete( CoachPro_DB::table( 'assistants' ), array( 'id' => $id ) );
        return rest_ensure_response( array( 'deleted' => true ) );
    }

    public static function activate_assistant( WP_REST_Request $request ) {
        $user_id      = get_current_user_id();
        $assistant_id = sanitize_text_field( $request->get_param( 'id' ) );

        $assistant = CoachPro_DB::get_row( 'assistants', $assistant_id );
        if ( ! $assistant ) {
            return new WP_Error( 'not_found', __( 'Assistant not found.', 'coachpro-ai' ), array( 'status' => 404 ) );
        }

        // user_can_use() includes an admin (manage_options) bypass, so this covers both regular users and admins.
        if ( ! $assistant['is_active'] || (!(int)$assistant['is_prebuilt'] && (int)$assistant['owner_id'] !== $user_id) ) {
            return new WP_Error( 'forbidden', __( 'You do not have access to this assistant.', 'coachpro-ai' ), array( 'status' => 403 ) );
        }

        if (CoachPro_DB::count('user_active_assistants', array('user_id'=>$user_id, 'assistant_id'=>$assistant_id))) {
            if ( (int) $assistant['is_prebuilt'] === 1 && ! self::user_can_use( $assistant, $user_id ) ) {
                return new WP_Error('limit_reached', 'This activation is paused by your current plan. Upgrade or deactivate another assistant.', array('status'=>403));
            }
            return rest_ensure_response(array('activated'=>true));
        }
        // Check configured activation limit
        if ( $assistant['is_prebuilt'] && ! CoachPro_Credits::can_activate_prebuilt( $user_id ) ) {
            return new WP_Error( 'limit_reached', __( 'Your active assistant limit has been reached. Upgrade to activate more.', 'coachpro-ai' ), array( 'status' => 403 ) );
        }

        global $wpdb;
        // Ignore duplicate
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `" . CoachPro_DB::table('user_active_assistants') . "` WHERE user_id = %d AND assistant_id = %s",
            $user_id, $assistant_id
        ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        if ( ! $existing ) {
            CoachPro_DB::insert(
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
        CoachPro_DB::delete(
            CoachPro_DB::table( 'user_active_assistants' ),
            array( 'user_id' => $user_id, 'assistant_id' => $assistant_id ),
            array( '%d', '%s' )
        );

        return rest_ensure_response( array( 'deactivated' => true ) );
    }
}
