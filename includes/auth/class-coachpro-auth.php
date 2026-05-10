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
    // AJAX: Forgot password
    // -------------------------------------------------------------------------
    public static function ajax_forgot_password() {
        check_ajax_referer( 'wp_rest', 'nonce' );

        $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'coachpro-ai' ) ), 400 );
        }

        $user = get_user_by( 'email', $email );
        if ( $user ) {
            $result = retrieve_password( $user->user_login );
            if ( is_wp_error( $result ) || true !== $result ) {
                error_log( 'CoachPro forgot password: failed to trigger password reset email for an existing account.' );
            }
        }

        wp_send_json_success( array(
            'message' => __( 'If an account with that email exists, a password reset link has been sent.', 'coachpro-ai' ),
        ) );
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
    // REST: GET /wp-json/coachpro/v1/auth/google — initiate OAuth
    // -------------------------------------------------------------------------
    public static function rest_google_oauth( WP_REST_Request $request ) {
        $client_id = get_option( 'coachpro_google_client_id', '' );
        if ( empty( $client_id ) ) {
            return new WP_Error( 'google_not_configured', 'Google Sign-In is not configured.', array( 'status' => 501 ) );
        }

        $redirect_uri = rest_url( 'coachpro/v1/auth/google/callback' );
        $redirect_to  = esc_url_raw( $request->get_param( 'redirect' ) ?: home_url() );
        $state        = wp_create_nonce( 'coachpro_google_oauth' ) . '|' . $redirect_to;
        $params       = http_build_query( array(
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => base64_encode( $state ),
            'access_type'   => 'online',
        ) );

        wp_redirect( 'https://accounts.google.com/o/oauth2/v2/auth?' . $params );
        exit;
    }

    // -------------------------------------------------------------------------
    // REST: GET /wp-json/coachpro/v1/auth/google/callback
    // -------------------------------------------------------------------------
    public static function rest_google_callback( WP_REST_Request $request ) {
        $code       = sanitize_text_field( (string) $request->get_param( 'code' ) );
        $raw_state  = base64_decode( (string) ( $request->get_param( 'state' ) ?: '' ), true );
        if ( false === $raw_state ) {
            wp_die( 'Invalid OAuth state. Please try again.', 'CoachPro Error', array( 'response' => 400 ) );
        }
        $parts      = explode( '|', $raw_state, 2 );
        $nonce_val  = $parts[0] ?? '';
        $redirect_to = esc_url_raw( $parts[1] ?? home_url() );

        if ( empty( $code ) ) {
            wp_die( 'Google authentication failed. Missing authorization code.', 'CoachPro Error', array( 'response' => 400 ) );
        }

        if ( ! wp_verify_nonce( $nonce_val, 'coachpro_google_oauth' ) ) {
            wp_die( 'Security check failed. Please try again.', 'CoachPro Error', array( 'response' => 403 ) );
        }

        $client_id     = get_option( 'coachpro_google_client_id', '' );
        $client_secret = get_option( 'coachpro_google_client_secret', '' );
        $redirect_uri  = rest_url( 'coachpro/v1/auth/google/callback' );

        $token_resp = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
            'body' => array(
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code',
            ),
        ) );

        if ( is_wp_error( $token_resp ) ) {
            wp_die( 'Failed to connect to Google. Please try again.', 'CoachPro Error' );
        }

        $token_data   = json_decode( wp_remote_retrieve_body( $token_resp ), true );
        $access_token = $token_data['access_token'] ?? '';

        if ( empty( $access_token ) ) {
            wp_die( 'Google authentication failed. Please try again.', 'CoachPro Error' );
        }

        $user_resp = wp_remote_get( 'https://www.googleapis.com/oauth2/v3/userinfo', array(
            'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
        ) );

        if ( is_wp_error( $user_resp ) ) {
            wp_die( 'Failed to fetch Google profile.', 'CoachPro Error' );
        }

        $google_user = json_decode( wp_remote_retrieve_body( $user_resp ), true );
        $email       = sanitize_email( $google_user['email'] ?? '' );
        $name        = sanitize_text_field( $google_user['name'] ?? '' );
        $google_id   = sanitize_text_field( $google_user['sub'] ?? '' );

        if ( empty( $email ) ) {
            wp_die( 'Could not retrieve email from Google.', 'CoachPro Error' );
        }

        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            $email_parts = explode( '@', $email );
            $username    = sanitize_user( $email_parts[0] ?? 'coachprouser', true ) ?: 'coachprouser';
            $base        = $username;
            $i           = 1;

            while ( username_exists( $username ) ) {
                $username = $base . $i++;
            }

            $user_id = wp_create_user( $username, wp_generate_password( 24, true, true ), $email );
            if ( is_wp_error( $user_id ) ) {
                wp_die( 'Account creation failed: ' . esc_html( $user_id->get_error_message() ), 'CoachPro Error' );
            }

            wp_update_user( array( 'ID' => $user_id, 'display_name' => $name ) );
            update_user_meta( $user_id, 'coachpro_google_id', $google_id );
        } else {
            $user_id = $user->ID;
            if ( ! get_user_meta( $user_id, 'coachpro_google_id', true ) ) {
                update_user_meta( $user_id, 'coachpro_google_id', $google_id );
            }
            if ( $name ) {
                wp_update_user( array( 'ID' => $user_id, 'display_name' => $name ) );
            }
        }

        wp_set_auth_cookie( $user_id, true );
        wp_safe_redirect( $redirect_to );
        exit;
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
            'is_admin'    => user_can( $user_id, 'manage_options' ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
        );
    }
}
