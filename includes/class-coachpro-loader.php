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
        // Custom roles
        add_action( 'init', array( 'CoachPro_Auth', 'register_roles' ) );

        // REST API
        add_action( 'rest_api_init', array( 'CoachPro_REST_API', 'register_routes' ) );

        // Shortcodes
        add_action( 'init', array( 'CoachPro_Shortcodes', 'register' ) );

        // AJAX handlers (auth)
        add_action( 'wp_ajax_nopriv_coachpro_login',        array( 'CoachPro_Auth', 'ajax_login' ) );
        add_action( 'wp_ajax_nopriv_coachpro_register',     array( 'CoachPro_Auth', 'ajax_register' ) );
        add_action( 'wp_ajax_coachpro_logout',              array( 'CoachPro_Auth', 'ajax_logout' ) );
        add_action( 'wp_ajax_coachpro_check_auth',          array( 'CoachPro_Auth', 'ajax_check_auth' ) );
        add_action( 'wp_ajax_nopriv_coachpro_check_auth',   array( 'CoachPro_Auth', 'ajax_check_auth' ) );

        // On new user registration: bonus credits + role
        add_action( 'user_register', array( 'CoachPro_Auth', 'on_user_register' ) );

        // Admin panel
        if ( is_admin() ) {
            add_action( 'admin_menu', array( 'CoachPro_Admin', 'add_menu' ) );
            add_action( 'admin_init', array( 'CoachPro_Admin', 'register_settings' ) );
            add_action( 'admin_post_coachpro_approve_payment', array( 'CoachPro_Admin', 'handle_approve_payment' ) );
            add_action( 'admin_post_coachpro_reject_payment',  array( 'CoachPro_Admin', 'handle_reject_payment' ) );
            add_action( 'admin_post_coachpro_adjust_credits',  array( 'CoachPro_Admin', 'handle_adjust_credits' ) );
        }

        // Cron: rolling summary
        add_action( 'coachpro_summarize', array( 'CoachPro_AI_Provider', 'run_summary_cron' ) );
    }
}
