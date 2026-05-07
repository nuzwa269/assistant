<?php
/**
 * Class CoachPro_Admin
 * WordPress admin panel integration.
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Admin {

    // -------------------------------------------------------------------------
    // Menu
    // -------------------------------------------------------------------------
    public static function add_menu() {
        add_menu_page(
            __( 'CoachPro AI', 'coachpro-ai' ),
            __( 'CoachPro AI', 'coachpro-ai' ),
            'manage_options',
            'coachpro-ai',
            array( __CLASS__, 'page_dashboard' ),
            'dashicons-awards',
            56
        );

        add_submenu_page( 'coachpro-ai', __( 'Dashboard', 'coachpro-ai' ),  __( 'Dashboard', 'coachpro-ai' ),  'manage_options', 'coachpro-ai',           array( __CLASS__, 'page_dashboard' ) );
        add_submenu_page( 'coachpro-ai', __( 'Users', 'coachpro-ai' ),      __( 'Users', 'coachpro-ai' ),      'manage_options', 'coachpro-users',         array( __CLASS__, 'page_users' ) );
        add_submenu_page( 'coachpro-ai', __( 'Payments', 'coachpro-ai' ),   __( 'Payments', 'coachpro-ai' ),   'manage_options', 'coachpro-payments',      array( __CLASS__, 'page_payments' ) );
        add_submenu_page( 'coachpro-ai', __( 'AI Models', 'coachpro-ai' ),  __( 'AI Models', 'coachpro-ai' ),  'manage_options', 'coachpro-models',        array( __CLASS__, 'page_models' ) );
        add_submenu_page( 'coachpro-ai', __( 'Assistants', 'coachpro-ai' ), __( 'Assistants', 'coachpro-ai' ), 'manage_options', 'coachpro-assistants',    array( __CLASS__, 'page_assistants' ) );
        add_submenu_page( 'coachpro-ai', __( 'Plans & Packs', 'coachpro-ai' ), __( 'Plans & Packs', 'coachpro-ai' ), 'manage_options', 'coachpro-plans',   array( __CLASS__, 'page_plans' ) );
        add_submenu_page( 'coachpro-ai', __( 'Settings', 'coachpro-ai' ),   __( 'Settings', 'coachpro-ai' ),   'manage_options', 'coachpro-settings',     array( __CLASS__, 'page_settings' ) );
    }

    // -------------------------------------------------------------------------
    // Settings registration
    // -------------------------------------------------------------------------
    public static function register_settings() {
        $settings = array(
            'coachpro_openai_key',
            'coachpro_anthropic_key',
            'coachpro_openrouter_key',
            'coachpro_google_client_id',
            'coachpro_google_client_secret',
            'coachpro_jazzcash_no',
            'coachpro_easypaisa_no',
            'coachpro_bank_details',
            'coachpro_signup_bonus',
        );
        foreach ( $settings as $key ) {
            register_setting( 'coachpro_settings_group', $key, array( 'sanitize_callback' => 'sanitize_text_field' ) );
        }
    }

    // -------------------------------------------------------------------------
    // Page callbacks
    // -------------------------------------------------------------------------
    public static function page_dashboard() {
        require_once COACHPRO_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    public static function page_users() {
        require_once COACHPRO_PLUGIN_DIR . 'admin/views/users.php';
    }
    public static function page_payments() {
        require_once COACHPRO_PLUGIN_DIR . 'admin/views/payments.php';
    }
    public static function page_models() {
        require_once COACHPRO_PLUGIN_DIR . 'admin/views/models.php';
    }
    public static function page_assistants() {
        require_once COACHPRO_PLUGIN_DIR . 'admin/views/assistants.php';
    }
    public static function page_plans() {
        require_once COACHPRO_PLUGIN_DIR . 'admin/views/plans.php';
    }
    public static function page_settings() {
        require_once COACHPRO_PLUGIN_DIR . 'admin/views/settings.php';
    }

    // -------------------------------------------------------------------------
    // admin-post handlers
    // -------------------------------------------------------------------------
    public static function handle_approve_payment() {
        check_admin_referer( 'coachpro_approve_payment' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $id = sanitize_text_field( wp_unslash( $_POST['payment_id'] ?? '' ) );
        if ( $id ) {
            $req = new WP_REST_Request( 'POST' );
            $req->set_param( 'id', $id );
            $req->set_body_params( array( 'admin_notes' => sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) ) ) );
            CoachPro_Admin_API::approve_payment( $req );
        }

        wp_redirect( admin_url( 'admin.php?page=coachpro-payments&message=approved' ) );
        exit;
    }

    public static function handle_reject_payment() {
        check_admin_referer( 'coachpro_reject_payment' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $id = sanitize_text_field( wp_unslash( $_POST['payment_id'] ?? '' ) );
        if ( $id ) {
            $req = new WP_REST_Request( 'POST' );
            $req->set_param( 'id', $id );
            $req->set_body_params( array( 'admin_notes' => sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) ) ) );
            CoachPro_Admin_API::reject_payment( $req );
        }

        wp_redirect( admin_url( 'admin.php?page=coachpro-payments&message=rejected' ) );
        exit;
    }

    public static function handle_adjust_credits() {
        check_admin_referer( 'coachpro_adjust_credits' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $user_id     = absint( wp_unslash( $_POST['user_id'] ?? 0 ) );
        $new_credits = absint( wp_unslash( $_POST['credits'] ?? 0 ) );
        $notes       = sanitize_text_field( wp_unslash( $_POST['notes'] ?? '' ) );

        if ( $user_id ) {
            CoachPro_Credits::set( $user_id, $new_credits, $notes ?: 'Admin manual adjustment' );
        }

        wp_redirect( admin_url( 'admin.php?page=coachpro-users&message=adjusted' ) );
        exit;
    }
}
