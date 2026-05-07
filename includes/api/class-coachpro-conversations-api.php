<?php
/**
 * Class CoachPro_Conversations_API
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Conversations_API {

    public static function list_conversations( WP_REST_Request $request ) {
        $user_id    = get_current_user_id();
        $project_id = sanitize_text_field( $request->get_param( 'project_id' ) ?? '' );

        $where = array( 'user_id' => $user_id );
        if ( $project_id ) {
            $where['project_id'] = $project_id;
        }

        $rows = CoachPro_DB::get_rows( 'conversations', $where, 'updated_at DESC' );
        return rest_ensure_response( $rows );
    }

    public static function create_conversation( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $params  = $request->get_json_params();

        $project_id   = sanitize_text_field( $params['project_id'] ?? '' );
        $assistant_id = sanitize_text_field( $params['assistant_id'] ?? '' );
        $title        = sanitize_text_field( $params['title'] ?? 'New conversation' );

        if ( empty( $project_id ) || empty( $assistant_id ) ) {
            return new WP_Error( 'missing_fields', __( 'project_id and assistant_id are required.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        // Verify project belongs to user
        $project = CoachPro_DB::get_row( 'projects', $project_id );
        if ( ! $project || (int) $project['user_id'] !== $user_id ) {
            return new WP_Error( 'forbidden', __( 'Project not found.', 'coachpro-ai' ), array( 'status' => 403 ) );
        }

        global $wpdb;
        $id = wp_generate_uuid4();
        $wpdb->insert(
            CoachPro_DB::table( 'conversations' ),
            array(
                'id'           => $id,
                'user_id'      => $user_id,
                'project_id'   => $project_id,
                'assistant_id' => $assistant_id,
                'title'        => $title,
            ),
            array( '%s', '%d', '%s', '%s', '%s' )
        );

        return rest_ensure_response( CoachPro_DB::get_row( 'conversations', $id ) );
    }

    public static function update_conversation( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $id      = sanitize_text_field( $request->get_param( 'id' ) );
        $row     = CoachPro_DB::get_row( 'conversations', $id );

        if ( ! $row || (int) $row['user_id'] !== $user_id ) {
            return new WP_Error( 'not_found', __( 'Conversation not found.', 'coachpro-ai' ), array( 'status' => 404 ) );
        }

        $params = $request->get_json_params();
        $data   = array();

        if ( isset( $params['title'] ) ) {
            $data['title'] = sanitize_text_field( $params['title'] );
        }
        if ( empty( $data ) ) {
            return new WP_Error( 'nothing_to_update', __( 'No data to update.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        global $wpdb;
        $wpdb->update( CoachPro_DB::table( 'conversations' ), $data, array( 'id' => $id ) );
        return rest_ensure_response( CoachPro_DB::get_row( 'conversations', $id ) );
    }

    public static function delete_conversation( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $id      = sanitize_text_field( $request->get_param( 'id' ) );
        $row     = CoachPro_DB::get_row( 'conversations', $id );

        if ( ! $row || (int) $row['user_id'] !== $user_id ) {
            return new WP_Error( 'not_found', __( 'Conversation not found.', 'coachpro-ai' ), array( 'status' => 404 ) );
        }

        global $wpdb;
        $wpdb->delete( CoachPro_DB::table( 'conversations' ), array( 'id' => $id ) );
        $wpdb->delete( CoachPro_DB::table( 'messages' ),      array( 'conversation_id' => $id ) );
        $wpdb->delete( CoachPro_DB::table( 'conv_summaries' ),array( 'conversation_id' => $id ) );
        return rest_ensure_response( array( 'deleted' => true ) );
    }

    public static function get_messages( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $id      = sanitize_text_field( $request->get_param( 'id' ) );
        $row     = CoachPro_DB::get_row( 'conversations', $id );

        if ( ! $row || (int) $row['user_id'] !== $user_id ) {
            return new WP_Error( 'not_found', __( 'Conversation not found.', 'coachpro-ai' ), array( 'status' => 404 ) );
        }

        $rows = CoachPro_DB::get_rows( 'messages', array( 'conversation_id' => $id ), 'created_at ASC', 200 );
        return rest_ensure_response( $rows );
    }

    public static function add_message( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $conv_id = sanitize_text_field( $request->get_param( 'id' ) );
        $params  = $request->get_json_params();

        $row = CoachPro_DB::get_row( 'conversations', $conv_id );
        if ( ! $row || (int) $row['user_id'] !== $user_id ) {
            return new WP_Error( 'not_found', __( 'Conversation not found.', 'coachpro-ai' ), array( 'status' => 404 ) );
        }

        $role    = in_array( $params['role'] ?? '', array( 'user', 'assistant', 'system' ), true ) ? $params['role'] : 'user';
        $content = wp_kses_post( $params['content'] ?? '' );

        if ( empty( $content ) ) {
            return new WP_Error( 'missing_content', __( 'Message content is required.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        global $wpdb;
        $id = wp_generate_uuid4();
        $wpdb->insert(
            CoachPro_DB::table( 'messages' ),
            array(
                'id'              => $id,
                'conversation_id' => $conv_id,
                'user_id'         => $user_id,
                'role'            => $role,
                'content'         => $content,
                'model_id'        => sanitize_text_field( $params['model_id'] ?? '' ),
                'credits_used'    => absint( $params['credits_used'] ?? 0 ),
            ),
            array( '%s', '%s', '%d', '%s', '%s', '%s', '%d' )
        );

        return rest_ensure_response( CoachPro_DB::get_row( 'messages', $id ) );
    }
}
