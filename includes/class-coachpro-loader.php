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
        $redirect_to = home_url( $request_uri );

        $login_url = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $login_url );

        wp_safe_redirect( $login_url );
        exit;
    }
}
