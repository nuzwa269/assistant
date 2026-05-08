<?php
/**
 * Class CoachPro_Chat_API
 * POST /wp-json/coachpro/v1/chat
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Chat_API {

    /**
     * Handle a chat request: call AI, save messages, deduct credits.
     */
    public static function handle_chat( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $params  = $request->get_json_params();

        $conv_id    = sanitize_text_field( $params['conversation_id'] ?? '' );
        $model_id   = sanitize_text_field( $params['model_id'] ?? '' );
        $user_msg   = wp_kses_post( $params['message'] ?? '' );
        $messages   = isset( $params['messages'] ) && is_array( $params['messages'] ) ? $params['messages'] : array();

        if ( empty( $conv_id ) || empty( $model_id ) || empty( $user_msg ) ) {
            return new WP_Error( 'missing_params', __( 'conversation_id, model_id, and message are required.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        // Verify conversation belongs to user
        $conv = CoachPro_DB::get_row( 'conversations', $conv_id );
        if ( ! $conv || (int) $conv['user_id'] !== $user_id ) {
            return new WP_Error( 'forbidden', __( 'Conversation not found.', 'coachpro-ai' ), array( 'status' => 403 ) );
        }

        // Get model to know credit cost
        $model = CoachPro_DB::get_row( 'ai_models', $model_id );
        if ( ! $model || ! $model['is_active'] ) {
            return new WP_Error( 'invalid_model', __( 'Model not available.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        $cost = (int) $model['credits_cost'];

        // Check balance
        if ( CoachPro_Credits::get_balance( $user_id ) < $cost ) {
            return new WP_Error( 'insufficient_credits', __( 'Insufficient credits.', 'coachpro-ai' ), array( 'status' => 402 ) );
        }

        // Save user message
        $user_msg_id = wp_generate_uuid4();
        global $wpdb;
        $wpdb->insert(
            CoachPro_DB::table( 'messages' ),
            array(
                'id'              => $user_msg_id,
                'conversation_id' => $conv_id,
                'user_id'         => $user_id,
                'role'            => 'user',
                'content'         => $user_msg,
                'model_id'        => $model_id,
                'credits_used'    => 0,
            ),
            array( '%s', '%s', '%d', '%s', '%s', '%s', '%d' )
        );

        // Build messages array for AI call (get assistant system prompt)
        $assistant = CoachPro_DB::get_row( 'assistants', $conv['assistant_id'] );

        // Verify the assistant is still accessible to the user.
        if ( ! current_user_can( 'manage_options' ) ) {
            if ( ! $assistant ) {
                return new WP_Error( 'not_found', __( 'Assistant not found.', 'coachpro-ai' ), array( 'status' => 404 ) );
            }
            if ( ! CoachPro_Assistants_API::user_can_use( $assistant, $user_id ) ) {
                return new WP_Error( 'forbidden', __( 'You do not have access to this assistant.', 'coachpro-ai' ), array( 'status' => 403 ) );
            }
        }

        $ai_messages = array();

        if ( $assistant && ! empty( $assistant['system_prompt'] ) ) {
            $ai_messages[] = array( 'role' => 'system', 'content' => $assistant['system_prompt'] );
        }

        // Append recent conversation history (last 20 messages)
        if ( empty( $messages ) ) {
            $t_msg = CoachPro_DB::table( 'messages' );
            $history = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT role, content FROM `{$t_msg}` WHERE conversation_id = %s ORDER BY created_at ASC LIMIT 20",
                $conv_id
            ), ARRAY_A );

            foreach ( (array) $history as $h ) {
                $ai_messages[] = array( 'role' => $h['role'], 'content' => $h['content'] );
            }
        } else {
            foreach ( $messages as $m ) {
                $ai_messages[] = array(
                    'role'    => sanitize_text_field( $m['role'] ?? 'user' ),
                    'content' => wp_kses_post( $m['content'] ?? '' ),
                );
            }
        }

        // Call AI
        $ai_response = CoachPro_AI_Provider::call( $model_id, $ai_messages, $user_id, $conv_id );

        if ( is_wp_error( $ai_response ) ) {
            return $ai_response;
        }

        // Save assistant message
        $asst_msg_id = wp_generate_uuid4();
        $wpdb->insert(
            CoachPro_DB::table( 'messages' ),
            array(
                'id'              => $asst_msg_id,
                'conversation_id' => $conv_id,
                'user_id'         => $user_id,
                'role'            => 'assistant',
                'content'         => $ai_response,
                'model_id'        => $model_id,
                'credits_used'    => $cost,
            ),
            array( '%s', '%s', '%d', '%s', '%s', '%s', '%d' )
        );

        // Update conversation updated_at
        $wpdb->update(
            CoachPro_DB::table( 'conversations' ),
            array( 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $conv_id ),
            array( '%s' ),
            array( '%s' )
        );

        // Deduct credits
        CoachPro_Credits::deduct( $user_id, $cost, $asst_msg_id, $model_id );

        return rest_ensure_response( array(
            'message_id'  => $asst_msg_id,
            'content'     => $ai_response,
            'model_id'    => $model_id,
            'credits_used'=> $cost,
            'balance'     => CoachPro_Credits::get_balance( $user_id ),
        ) );
    }
}
