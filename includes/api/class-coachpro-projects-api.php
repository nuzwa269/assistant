<?php
/**
 * Class CoachPro_Projects_API
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Projects_API {

    private static function owns_or_admin( $resource_user_id ) : bool {
        $current = get_current_user_id();
        if ( (int) $resource_user_id === $current ) {
            return true;
        }
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        return false;
    }

    public static function list_projects( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $where   = current_user_can( 'manage_options' )
            ? array()
            : array( 'user_id' => $user_id );
        $rows    = CoachPro_DB::get_rows( 'projects', $where, 'created_at DESC' );
        return rest_ensure_response( $rows );
    }

    public static function create_project( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $params  = $request->get_json_params();

        if ( ! CoachPro_Credits::can_create_project( $user_id ) ) {
            return new WP_Error( 'limit_reached', __( 'Free plan allows max 3 projects. Please upgrade.', 'coachpro-ai' ), array( 'status' => 403 ) );
        }

        $name = sanitize_text_field( $params['name'] ?? '' );
        if ( empty( $name ) ) {
            return new WP_Error( 'missing_name', __( 'Project name is required.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        global $wpdb;
        $id = wp_generate_uuid4();
        $wpdb->insert(
            CoachPro_DB::table( 'projects' ),
            array(
                'id'          => $id,
                'user_id'     => $user_id,
                'name'        => $name,
                'description' => sanitize_textarea_field( $params['description'] ?? '' ),
            ),
            array( '%s', '%d', '%s', '%s' )
        );

        return rest_ensure_response( CoachPro_DB::get_row( 'projects', $id ) );
    }

    public static function update_project( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $id      = sanitize_text_field( $request->get_param( 'id' ) );
        $row     = CoachPro_DB::get_row( 'projects', $id );

        if ( ! $row ) {
            return new WP_Error( 'not_found', __( 'Project not found.', 'coachpro-ai' ), array( 'status' => 404 ) );
        }
        if ( ! self::owns_or_admin( $row['user_id'] ) ) {
            return new WP_Error( 'forbidden', __( 'Access denied.', 'coachpro-ai' ), array( 'status' => 403 ) );
        }

        $params = $request->get_json_params();
        $data   = array();

        if ( isset( $params['name'] ) ) {
            $data['name'] = sanitize_text_field( $params['name'] );
        }
        if ( isset( $params['description'] ) ) {
            $data['description'] = sanitize_textarea_field( $params['description'] );
        }
        if ( empty( $data ) ) {
            return new WP_Error( 'nothing_to_update', __( 'No data to update.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        global $wpdb;
        $wpdb->update( CoachPro_DB::table( 'projects' ), $data, array( 'id' => $id ) );
        return rest_ensure_response( CoachPro_DB::get_row( 'projects', $id ) );
    }

    public static function delete_project( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $id      = sanitize_text_field( $request->get_param( 'id' ) );
        $row     = CoachPro_DB::get_row( 'projects', $id );

        if ( ! $row ) {
            return new WP_Error( 'not_found', __( 'Project not found.', 'coachpro-ai' ), array( 'status' => 404 ) );
        }
        if ( ! self::owns_or_admin( $row['user_id'] ) ) {
            return new WP_Error( 'forbidden', __( 'Access denied.', 'coachpro-ai' ), array( 'status' => 403 ) );
        }

        global $wpdb;
        $wpdb->delete( CoachPro_DB::table( 'projects' ), array( 'id' => $id ) );
        return rest_ensure_response( array( 'deleted' => true ) );
    }
}
