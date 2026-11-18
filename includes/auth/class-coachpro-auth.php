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
    // Helper: OAuth redirect_uri. Built from the site address, never from the
    // request's Host header, which a visitor can forge.
    // -------------------------------------------------------------------------
    private static function google_callback_uri() {
        return set_url_scheme( rest_url( 'coachpro/v1/auth/google/callback' ), 'https' );
    }

    /** Log only fixed stage/reason codes and numeric HTTP status; never raw responses. */
    private static function google_diagnostic( string $stage, string $reason, int $status = 0 ) {
        error_log( sprintf( 'CoachPro Google OAuth: stage=%s reason=%s http_status=%d', $stage, $reason, $status ) );
        // A single expiring record remains available when hosting logs are inaccessible.
        // Callers supply fixed codes only; never retain request or response contents.
        set_transient( 'coachpro_google_oauth_last_error', array(
            'stage' => $stage,
            'reason' => $reason,
            'http_status' => $status,
            'recorded_at' => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
        ), HOUR_IN_SECONDS );
    }

    /** Expose redacted diagnostics only in the existing administrator Site Health view. */
    public static function google_debug_information( array $info ) : array {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $info;
        }
        $last_error = get_transient( 'coachpro_google_oauth_last_error' );
        $fields = array();
        if ( is_array( $last_error ) ) {
            foreach ( array( 'recorded_at' => 'Recorded at', 'stage' => 'Failure stage',
                'reason' => 'Error code', 'http_status' => 'HTTP status (0 means no response)' ) as $key => $label ) {
                $fields[$key] = array( 'label' => $label, 'value' => $last_error[$key] ?? '', 'private' => true );
            }
        } else {
            $fields['status'] = array( 'label' => 'Diagnostic status',
                'value' => 'No OAuth failure retained. Retry Google Sign-In, then refresh this page within one hour.', 'private' => true );
        }
        $info['coachpro-google-oauth'] = array(
            'label' => 'CoachPro Google OAuth',
            'description' => 'Latest failure only; automatically expires after one hour. No credentials or personal data are retained.',
            'fields' => $fields,
        );
        return $info;
    }

    /** Google error descriptions can contain sensitive values. Only allow known codes. */
    private static function google_error_code( $data ) : string {
        $allowed = array( 'invalid_request', 'invalid_client', 'invalid_grant', 'unauthorized_client',
            'unsupported_grant_type', 'invalid_scope', 'redirect_uri_mismatch', 'access_denied',
            'temporarily_unavailable', 'server_error', 'disallowed_useragent', 'org_internal' );
        $code = is_array( $data ) ? ( $data['error'] ?? null ) : null;
        return is_string( $code ) && in_array( $code, $allowed, true ) ? $code : 'unrecognized_google_error';
    }

    // -------------------------------------------------------------------------
    // Role registration (called on 'init')
    // -------------------------------------------------------------------------
    public static function register_roles() {
        $admin = get_role('administrator');
        if ($admin && ! $admin->has_cap('coachpro_admin')) $admin->add_cap('coachpro_admin');
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

        // Registration hooks can be replayed. Never reset an existing wallet/plan.
        return CoachPro_Credits::signup_once( $user_id );
    }

    // -------------------------------------------------------------------------
    // AJAX: Login
    // -------------------------------------------------------------------------
    public static function ajax_login() {
        check_ajax_referer( 'wp_rest', 'nonce' );

        $username = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
        $password = wp_unslash( $_POST['password'] ?? '' );

        if ( empty( $username ) || empty( $password ) ) {
            wp_send_json_error( array( 'message' => __( 'Username and password required.', 'coachpro-ai' ) ), 400 );
        }

        // Brute-force protection: 5 failed attempts per account+IP, 20 per IP, then a 15-minute pause.
        $ip       = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        $keys     = array( 'cp_login_' . md5( strtolower( $username ) . '|' . $ip ) => 5, 'cp_login_ip_' . md5( $ip ) => 20 );
        foreach ( $keys as $key => $limit ) {
            if ( (int) get_transient( $key ) >= $limit ) {
                wp_send_json_error( array( 'message' => __( 'Too many failed sign-in attempts. Please wait 15 minutes and try again.', 'coachpro-ai' ) ), 429 );
            }
        }

        $user = wp_authenticate( $username, $password );

        if ( is_wp_error( $user ) ) {
            foreach ( $keys as $key => $limit ) set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );
            // One message for wrong username or password, so accounts cannot be discovered.
            wp_send_json_error( array( 'message' => __( 'Incorrect username/email or password.', 'coachpro-ai' ) ), 401 );
        }
        delete_transient( array_key_first( $keys ) );

        wp_set_current_user( $user->ID );
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

        $password_error = self::password_problem( $password );
        if ( $password_error ) {
            wp_send_json_error( array( 'message' => $password_error ), 400 );
        }

        // Every account receives free signup credits, so limit sign-ups per IP (5 per hour).
        $ip_key = 'cp_register_ip_' . md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
        if ( (int) get_transient( $ip_key ) >= 5 ) {
            wp_send_json_error( array( 'message' => __( 'Too many accounts were created from this network. Please try again later.', 'coachpro-ai' ) ), 429 );
        }

        $user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( array( 'message' => $user_id->get_error_message() ), 409 );
        }
        set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, HOUR_IN_SECONDS );

        wp_set_current_user( $user_id );
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
            self::google_diagnostic( 'initiation', 'missing_client_id' );
            return new WP_Error( 'google_not_configured', 'Google Sign-In is not configured.', array( 'status' => 501 ) );
        }

        // Callback URL comes from the site address (see google_callback_uri()).
        $redirect_uri = self::google_callback_uri();
        $redirect_to  = wp_validate_redirect( esc_url_raw( (string) ( $request->get_param( 'redirect' ) ?: home_url() ) ), home_url() );
        // One-time random state (a WordPress nonce is identical for every logged-out visitor).
        $token = wp_generate_password( 40, false, false );
        set_transient( 'coachpro_oauth_state_' . $token, $redirect_to, 10 * MINUTE_IN_SECONDS );
        $state = $token;
        $params       = http_build_query( array(
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => base64_encode( $state ),
            'access_type'   => 'online',
        ) );

        wp_redirect( 'https://accounts.google.com/o/oauth2/v2/auth?' . $params ); // phpcs:ignore WordPress.Security.SafeRedirect -- fixed external host
        exit;
    }

    // -------------------------------------------------------------------------
    // REST: GET /wp-json/coachpro/v1/auth/google/callback
    // -------------------------------------------------------------------------
    public static function rest_google_callback( WP_REST_Request $request ) {
        $code      = sanitize_text_field( (string) $request->get_param( 'code' ) );
        $raw_state = base64_decode( (string) ( $request->get_param( 'state' ) ?: '' ), true );
        $token     = is_string( $raw_state ) && preg_match( '/^[A-Za-z0-9]{40}$/D', $raw_state ) ? $raw_state : '';
        $redirect_to = $token ? get_transient( 'coachpro_oauth_state_' . $token ) : false;
        if ( $token ) delete_transient( 'coachpro_oauth_state_' . $token ); // single use
        if ( false === $redirect_to ) {
            self::google_diagnostic( 'state', 'invalid_or_expired_state' );
            wp_die( 'Security check failed or the sign-in link expired. Please try again.', 'CoachPro Error', array( 'response' => 403 ) );
        }
        $redirect_to = wp_validate_redirect( (string) $redirect_to, home_url() );

        if ( empty( $code ) ) {
            self::google_diagnostic( 'authorization', $request->get_param( 'error' ) ? self::google_error_code( array( 'error' => $request->get_param( 'error' ) ) ) : 'missing_code' );
            wp_die( 'Google authentication failed. Missing authorization code.', 'CoachPro Error', array( 'response' => 400 ) );
        }

        $client_id     = get_option( 'coachpro_google_client_id', '' );
        $client_secret = get_option( 'coachpro_google_client_secret', '' );

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            self::google_diagnostic( 'configuration', empty( $client_id ) ? 'missing_client_id' : 'missing_client_secret' );
            wp_die( 'Google authentication failed. Please try again.', 'CoachPro Error' );
        }

        // Must match exactly what was sent during the OAuth initiation
        $redirect_uri  = self::google_callback_uri();

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
            self::google_diagnostic( 'token_exchange', 'transport_error' );
            wp_die( 'Failed to connect to Google. Please try again.', 'CoachPro Error' );
        }

        $token_status = (int) wp_remote_retrieve_response_code( $token_resp );
        $token_data   = json_decode( wp_remote_retrieve_body( $token_resp ), true );
        if ( $token_status < 200 || $token_status >= 300 || ! is_array( $token_data ) || isset( $token_data['error'] ) ) {
            $reason = is_array( $token_data ) && isset( $token_data['error'] )
                ? self::google_error_code( $token_data ) : ( is_array( $token_data ) ? 'http_error' : 'invalid_json' );
            self::google_diagnostic( 'token_exchange', $reason, $token_status );
            wp_die( 'Google authentication failed. Please try again.', 'CoachPro Error' );
        }
        $access_token = $token_data['access_token'] ?? '';
        if ( ! is_string( $access_token ) || '' === $access_token ) {
            self::google_diagnostic( 'token_exchange', 'missing_access_token', $token_status );
            wp_die( 'Google authentication failed. Please try again.', 'CoachPro Error' );
        }

        $user_resp = wp_remote_get( 'https://www.googleapis.com/oauth2/v3/userinfo', array(
            'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
        ) );

        if ( is_wp_error( $user_resp ) ) {
            self::google_diagnostic( 'userinfo', 'transport_error' );
            wp_die( 'Failed to fetch Google profile.', 'CoachPro Error' );
        }

        $user_status = (int) wp_remote_retrieve_response_code( $user_resp );
        $google_user = json_decode( wp_remote_retrieve_body( $user_resp ), true );
        if ( $user_status < 200 || $user_status >= 300 || ! is_array( $google_user ) || isset( $google_user['error'] ) ) {
            self::google_diagnostic( 'userinfo', is_array( $google_user ) ? 'http_or_api_error' : 'invalid_json', $user_status );
            wp_die( 'Failed to fetch Google profile.', 'CoachPro Error' );
        }
        if ( true !== ( $google_user['email_verified'] ?? false )
            || ! is_string( $google_user['sub'] ?? null ) || '' === $google_user['sub']
            || ! is_string( $google_user['email'] ?? null ) || ! is_email( $google_user['email'] ) ) {
            self::google_diagnostic( 'identity', 'unverified_or_invalid_identity' );
            wp_die( 'Could not verify Google identity. Please try again.', 'CoachPro Error' );
        }
        $email       = sanitize_email( $google_user['email'] ?? '' );
        $name        = sanitize_text_field( $google_user['name'] ?? '' );
        $google_id   = sanitize_text_field( $google_user['sub'] ?? '' );

        if ( empty( $email ) ) {
            self::google_diagnostic( 'identity', 'missing_email' );
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
                self::google_diagnostic( 'account', 'creation_failed' );
                wp_die( 'Account creation failed. Please try again.', 'CoachPro Error' );
            }

            wp_update_user( array( 'ID' => $user_id, 'display_name' => $name ) );
            update_user_meta( $user_id, 'coachpro_google_id', $google_id );
        } else {
            $user_id = $user->ID;
            $linked_google_id = (string) get_user_meta( $user_id, 'coachpro_google_id', true );
            if ( '' !== $linked_google_id && ! hash_equals( $linked_google_id, $google_id ) ) {
                self::google_diagnostic( 'identity', 'subject_mismatch' );
                wp_die( 'Could not verify Google identity. Please try again.', 'CoachPro Error' );
            }
            if ( '' === $linked_google_id ) {
                update_user_meta( $user_id, 'coachpro_google_id', $google_id );
            }
        }

        if ( headers_sent() ) {
            self::google_diagnostic( 'session', 'headers_already_sent' );
            wp_die( 'Google authentication failed. Please try again.', 'CoachPro Error' );
        }
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, true );
        wp_safe_redirect( $redirect_to );
        exit;
    }

    /** Returns an error message for a weak password, or '' when it is acceptable. */
    public static function password_problem( string $password ) : string {
        if ( strlen( $password ) < 8 ) return __( 'Password must be at least 8 characters.', 'coachpro-ai' );
        if ( strlen( $password ) > 200 ) return __( 'Password is too long.', 'coachpro-ai' );
        return '';
    }

    // -------------------------------------------------------------------------
    // Helper: build user data array
    // -------------------------------------------------------------------------
    public static function user_data( int $user_id ) : array {
        CoachPro_Credits::refresh_plan($user_id);
        $user = get_userdata( $user_id );
        return array(
            'id'          => $user_id,
            'username'    => $user->user_login,
            'email'       => $user->user_email,
            'name'        => $user->display_name,
            'plan'        => get_user_meta( $user_id, 'coachpro_plan', true ) ?: 'free',
            'credits'     => CoachPro_Credits::get_balance($user_id),
            'plan_renews' => get_user_meta( $user_id, 'coachpro_plan_renews', true ),
            'avatar_url'  => CoachPro_Profile_API::avatar_url( $user_id ),
            'is_admin'    => user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'coachpro_admin' ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
        );
    }
}
