<?php
/**
 * Class CoachPro_Auth
 * Handles authentication, custom roles, and AJAX auth actions.
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Auth {

    // -------------------------------------------------------------------------
    // Role registration (called on 'init')
    // -------------------------------------------------------------------------
    public static function register_roles() {
        if ( ! get_role( 'coachpro_user' ) ) {
            add_role( 'coachpro_user', __( 'CoachPro User', 'coachpro-ai' ), array( 'read' => true ) );
        }
        if ( ! get_role( 'coachpro_admin' ) ) {
            add_role( 'coachpro_admin', __( 'CoachPro Admin', 'coachpro-ai' ), array( 'read' => true, 'coachpro_admin' => true ) );
        }
    }

    // -------------------------------------------------------------------------
    // Hook: new user registered → bonus credits + role
    // -------------------------------------------------------------------------
    public static function on_user_register( int $user_id ) {
        // Assign coachpro_user role
        $user = new WP_User( $user_id );
        $user->add_role( 'coachpro_user' );

        // Set default meta
        update_user_meta( $user_id, 'coachpro_plan', 'free' );
        update_user_meta( $user_id, 'coachpro_credits', 0 );
        update_user_meta( $user_id, 'coachpro_plan_renews', '' );

        // Add signup bonus
        $bonus = (int) get_option( 'coachpro_signup_bonus', 20 );
        CoachPro_Credits::add( $user_id, $bonus, 'signup_bonus', null, 'Welcome bonus' );
    }

    // -------------------------------------------------------------------------
    // AJAX: Login
    // -------------------------------------------------------------------------
    public static function ajax_login() {
        check_ajax_referer( 'wp_rest', 'nonce' );

        $username = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
        $password = sanitize_text_field( wp_unslash( $_POST['password'] ?? '' ) );

        if ( empty( $username ) || empty( $password ) ) {
            wp_send_json_error( array( 'message' => __( 'Username and password required.', 'coachpro-ai' ) ), 400 );
        }

        $user = wp_authenticate( $username, $password );

        if ( is_wp_error( $user ) ) {
            wp_send_json_error( array( 'message' => $user->get_error_message() ), 401 );
        }

        wp_set_auth_cookie( $user->ID, true );
        wp_send_json_success( self::user_data( $user->ID ) );
    }

    // -------------------------------------------------------------------------
    // AJAX: Register
    // -------------------------------------------------------------------------
    public static function ajax_register() {
        check_ajax_referer( 'wp_rest', 'nonce' );

        $username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ) );
        $email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $password = wp_unslash( $_POST['password'] ?? '' ); // hashed by wp_create_user

        if ( empty( $username ) || empty( $email ) || empty( $password ) ) {
            wp_send_json_error( array( 'message' => __( 'All fields are required.', 'coachpro-ai' ) ), 400 );
        }

        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'coachpro-ai' ) ), 400 );
        }

        $user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( array( 'message' => $user_id->get_error_message() ), 409 );
        }

        wp_set_auth_cookie( $user_id, true );
        wp_send_json_success( self::user_data( $user_id ) );
    }

    // -------------------------------------------------------------------------
    // AJAX: Logout
    // -------------------------------------------------------------------------
    public static function ajax_logout() {
        check_ajax_referer( 'wp_rest', 'nonce' );
        wp_logout();
        wp_send_json_success( array( 'message' => 'Logged out.' ) );
    }

    // -------------------------------------------------------------------------
    // AJAX: Check auth status
    // -------------------------------------------------------------------------
    public static function ajax_check_auth() {
        if ( is_user_logged_in() ) {
            wp_send_json_success( self::user_data( get_current_user_id() ) );
        } else {
            wp_send_json_error( array( 'logged_in' => false ), 401 );
        }
    }

    // -------------------------------------------------------------------------
    // REST: GET /wp-json/coachpro/v1/auth/me
    // -------------------------------------------------------------------------
    public static function rest_me( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'unauthenticated', 'Not logged in.', array( 'status' => 401 ) );
        }
        return rest_ensure_response( self::user_data( $user_id ) );
    }

    // -------------------------------------------------------------------------
    // Helper: build user data array
    // -------------------------------------------------------------------------
    public static function user_data( int $user_id ) : array {
        $user = get_userdata( $user_id );
        return array(
            'id'          => $user_id,
            'username'    => $user->user_login,
            'email'       => $user->user_email,
            'name'        => $user->display_name,
            'plan'        => get_user_meta( $user_id, 'coachpro_plan', true ) ?: 'free',
            'credits'     => (int) get_user_meta( $user_id, 'coachpro_credits', true ),
            'plan_renews' => get_user_meta( $user_id, 'coachpro_plan_renews', true ),
            'is_admin'    => user_can( $user_id, 'coachpro_admin' ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
        );
    }
}
