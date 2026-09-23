<?php
/**
 * Class CoachPro_Loader
 * Initialises all plugin hooks and registers routes.
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Loader {

    public function run() {
        add_action( 'init', array( 'CoachPro_Activator', 'maybe_upgrade' ) );

        // Custom roles
        add_action( 'init', array( 'CoachPro_Auth', 'register_roles' ) );

        // REST API
        add_action( 'rest_api_init', array( 'CoachPro_REST_API', 'register_routes' ) );

        // Shortcodes
        add_action( 'init', array( 'CoachPro_Shortcodes', 'register' ) );
        add_action( 'template_redirect', array( 'CoachPro_Loader', 'maybe_redirect_to_login' ) );

        // AJAX handlers (auth)
        add_action( 'wp_ajax_nopriv_coachpro_login',        array( 'CoachPro_Auth', 'ajax_login' ) );
        add_action( 'wp_ajax_nopriv_coachpro_register',     array( 'CoachPro_Auth', 'ajax_register' ) );
        add_action( 'wp_ajax_nopriv_coachpro_forgot_password', array( 'CoachPro_Auth', 'ajax_forgot_password' ) );
        add_action( 'wp_ajax_coachpro_forgot_password',     array( 'CoachPro_Auth', 'ajax_forgot_password' ) );
        add_action( 'wp_ajax_coachpro_logout',              array( 'CoachPro_Auth', 'ajax_logout' ) );
        add_action( 'wp_ajax_coachpro_check_auth',          array( 'CoachPro_Auth', 'ajax_check_auth' ) );
        add_action( 'wp_ajax_nopriv_coachpro_check_auth',   array( 'CoachPro_Auth', 'ajax_check_auth' ) );

        // On new user registration: bonus credits + role
        add_action( 'user_register', array( 'CoachPro_Auth', 'on_user_register' ) );

        // Admin panel
        if ( is_admin() ) {
            add_action( 'admin_menu', array( 'CoachPro_Admin', 'add_menu' ) );
            add_action( 'admin_init', array( 'CoachPro_Admin', 'register_settings' ) );
            add_action( 'admin_enqueue_scripts', array( 'CoachPro_Admin', 'enqueue_assets' ) );
            add_action( 'admin_post_coachpro_approve_payment', array( 'CoachPro_Admin', 'handle_approve_payment' ) );
            add_action( 'admin_post_coachpro_reject_payment',  array( 'CoachPro_Admin', 'handle_reject_payment' ) );
            add_action( 'admin_post_coachpro_adjust_credits',  array( 'CoachPro_Admin', 'handle_adjust_credits' ) );
        }

        // Cron: rolling summary
        add_action( 'coachpro_summarize', array( 'CoachPro_AI_Provider', 'run_summary_cron' ) );
    }

    public static function maybe_redirect_to_login() {
        // Skip AJAX, REST, admin, cron
        if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
            return;
        }

        if ( is_user_logged_in() ) {
            return;
        }

        if ( function_exists( 'is_login_page' ) && is_login_page() ) {
            return;
        }

        if ( function_exists( 'is_register_page' ) && is_register_page() ) {
            return;
        }

        // Determine login and register page IDs
        $login_page_id    = (int) get_option( 'coachpro_page_login', 0 );
        $register_page_id = (int) get_option( 'coachpro_page_register', 0 );

        // If no login/register page configured, try slug fallback.
        if ( ! $login_page_id ) {
            $login_page    = get_page_by_path( 'login' );
            $login_page_id = $login_page ? (int) $login_page->ID : 0;
        }
        if ( ! $register_page_id ) {
            $register_page    = get_page_by_path( 'register' );
            $register_page_id = $register_page ? (int) $register_page->ID : 0;
        }

        // Don't redirect if already on login or register page
        $current_id = (int) get_queried_object_id();
        if ( $login_page_id && $current_id === $login_page_id ) {
            return;
        }
        if ( $register_page_id && $current_id === $register_page_id ) {
            return;
        }

        // Only redirect on CoachPro-specific pages; never lock down the whole site.
        // Collect all page IDs configured for CoachPro.
        $coachpro_page_options = array(
            'coachpro_page_login', 'coachpro_page_register', 'coachpro_page_dashboard',
            'coachpro_page_projects', 'coachpro_page_chat', 'coachpro_page_assistants',
            'coachpro_page_saved', 'coachpro_page_buy_credits', 'coachpro_page_settings',
            'coachpro_page_transactions', 'coachpro_page_help',
        );
        $coachpro_page_ids = array();
        foreach ( $coachpro_page_options as $opt ) {
            $pid = (int) get_option( $opt, 0 );
            if ( $pid ) {
                $coachpro_page_ids[] = $pid;
            }
        }

        // If the current page is not a CoachPro page, do not redirect.
        if ( $current_id && ! in_array( $current_id, $coachpro_page_ids, true ) ) {
            // Also check if the page content contains a CoachPro shortcode as a fallback.
            $post = get_post( $current_id );
            if ( ! $post || ! self::page_has_coachpro_shortcode( $post->post_content ) ) {
                return;
            }
        } elseif ( ! $current_id ) {
            // Non-singular context (archive, search, etc.) — don't redirect.
            return;
        }

        /**
         * Filter whether unauthenticated users should be redirected to the CoachPro login page.
         *
         * @param bool $should_redirect  Whether to redirect.
         * @param int  $current_id       Current queried object ID.
         * @param int  $login_page_id    CoachPro login page ID.
         * @param int  $register_page_id CoachPro register page ID.
         */
        $should_redirect = apply_filters( 'coachpro_should_redirect_to_login', true, $current_id, $login_page_id, $register_page_id );
        if ( ! $should_redirect ) {
            return;
        }

        // Build login URL
        if ( $login_page_id ) {
            $login_url = get_permalink( $login_page_id );
        } else {
            $login_url = home_url( '/login' );
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
        if ( ! is_string( $request_uri ) || '' === $request_uri ) {
            $request_uri = '/';
        }
        $request_uri = esc_url_raw( $request_uri );
        if ( '' === $request_uri ) {
            $request_uri = '/';
        }
        $request_parts = wp_parse_url( $request_uri );
        $request_path  = isset( $request_parts['path'] ) && is_string( $request_parts['path'] ) ? $request_parts['path'] : '/';
        $request_query = isset( $request_parts['query'] ) && is_string( $request_parts['query'] ) ? $request_parts['query'] : '';
        $request_path  = '/' . ltrim( $request_path, '/' );
        $request_uri   = $request_query ? $request_path . '?' . $request_query : $request_path;

        $redirect_to = esc_url_raw( home_url( $request_uri ) );
        if ( ! $redirect_to ) {
            $redirect_to = home_url();
        }

        $login_url = add_query_arg( 'redirect_to', $redirect_to, $login_url );

        wp_safe_redirect( $login_url );
        exit;
    }

    /**
     * Check whether a post's content contains any CoachPro shortcode.
     *
     * @param string $content Post content.
     * @return bool
     */
    private static function page_has_coachpro_shortcode( string $content ) : bool {
        $coachpro_shortcodes = array(
            'coachpro', 'coachpro_dashboard', 'coachpro_chat', 'coachpro_projects',
            'coachpro_assistants', 'coachpro_saved', 'coachpro_buy_credits',
            'coachpro_settings', 'coachpro_login', 'coachpro_register',
            'coachpro_transactions', 'coachpro_help',
        );
        foreach ( $coachpro_shortcodes as $tag ) {
            if ( has_shortcode( $content, $tag ) ) {
                return true;
            }
        }
        return false;
    }
}
