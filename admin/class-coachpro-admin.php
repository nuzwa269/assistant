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
            'coachpro_admin',
            'coachpro-ai',
            array( __CLASS__, 'page_dashboard' ),
            'dashicons-awards',
            56
        );

        add_submenu_page( 'coachpro-ai', __( 'Dashboard', 'coachpro-ai' ),  __( 'Dashboard', 'coachpro-ai' ),  'coachpro_admin', 'coachpro-ai',           array( __CLASS__, 'page_dashboard' ) );
        add_submenu_page( 'coachpro-ai', __( 'Users', 'coachpro-ai' ),      __( 'Users', 'coachpro-ai' ),      'coachpro_admin', 'coachpro-users',         array( __CLASS__, 'page_users' ) );
        add_submenu_page( 'coachpro-ai', __( 'AI Models', 'coachpro-ai' ),  __( 'AI Models', 'coachpro-ai' ),  'coachpro_admin', 'coachpro-models',        array( __CLASS__, 'page_models' ) );
        add_submenu_page( 'coachpro-ai', __( 'Prebuilt Assistants', 'coachpro-ai' ), __( 'Prebuilt Assistants', 'coachpro-ai' ), 'coachpro_admin', 'coachpro-assistants', array( __CLASS__, 'page_assistants' ) );
        add_submenu_page( 'coachpro-ai', __( 'AI Providers', 'coachpro-ai' ), __( 'AI Providers', 'coachpro-ai' ), 'coachpro_admin', 'coachpro-ai-providers', array( __CLASS__, 'page_ai_providers' ) );
        add_submenu_page( 'coachpro-ai', __( 'Pricing Calculator', 'coachpro-ai' ), __( 'Pricing Calculator', 'coachpro-ai' ), 'coachpro_admin', 'coachpro-pricing', array( __CLASS__, 'page_pricing' ) );
        add_submenu_page( 'coachpro-ai', __( 'Plans & Packs', 'coachpro-ai' ), __( 'Plans & Packs', 'coachpro-ai' ), 'coachpro_admin', 'coachpro-plans',   array( __CLASS__, 'page_plans' ) );
        add_submenu_page( 'coachpro-ai', __( 'Settings', 'coachpro-ai' ),   __( 'Settings', 'coachpro-ai' ),   'coachpro_admin', 'coachpro-settings',     array( __CLASS__, 'page_settings' ) );
    }

    // -------------------------------------------------------------------------
    // Settings registration
    // -------------------------------------------------------------------------
    public static function register_settings() {
        // An empty secret field means "keep the saved secret" (it is never printed in the form).
        register_setting( 'coachpro_settings_group', 'coachpro_google_client_secret', array(
            'sanitize_callback' => function ( $value ) {
                $value = trim( sanitize_text_field( (string) $value ) );
                return '' === $value ? (string) get_option( 'coachpro_google_client_secret', '' ) : $value;
            },
        ) );

        $settings = array(
            'coachpro_google_client_id',
            'coachpro_jazzcash_no',
            'coachpro_easypaisa_no',
            'coachpro_payment_contact',
        );
        foreach ( $settings as $key ) {
            register_setting( 'coachpro_settings_group', $key, array( 'sanitize_callback' => 'sanitize_text_field' ) );
        }
        // Multi-line payment texts keep their line breaks.
        foreach ( array( 'coachpro_bank_details', 'coachpro_payment_instructions' ) as $key ) {
            register_setting( 'coachpro_settings_group', $key, array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
        }

        register_setting('coachpro_settings_group', 'coachpro_delete_data_on_uninstall', array('sanitize_callback'=>'absint', 'default'=>0));

        $page_settings = array(
            'coachpro_page_login',
            'coachpro_page_register',
            'coachpro_page_dashboard',
            'coachpro_page_chat',
            'coachpro_page_projects',
            'coachpro_page_assistants',
            'coachpro_page_saved',
            'coachpro_page_buy_credits',
            'coachpro_page_settings',
            'coachpro_page_transactions',
            'coachpro_page_help',
        );
        foreach ( $page_settings as $key ) {
            register_setting( 'coachpro_settings_group', $key, array( 'sanitize_callback' => 'absint' ) );
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
    public static function page_models() {
        require_once COACHPRO_PLUGIN_DIR . 'admin/views/models.php';
    }
    public static function page_assistants() {
        require_once COACHPRO_PLUGIN_DIR . 'admin/views/assistants.php';
    }
    public static function page_ai_providers() {
        require_once COACHPRO_PLUGIN_DIR . 'admin/views/ai-providers.php';
    }
    public static function page_pricing() {
        require_once COACHPRO_PLUGIN_DIR . 'admin/views/pricing.php';
    }
    public static function page_plans() {
        require_once COACHPRO_PLUGIN_DIR . 'admin/views/plans.php';
    }
    public static function page_settings() {
        require_once COACHPRO_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public static function enqueue_assets() {
        if ( ! current_user_can( 'coachpro_admin' ) ) {
            return;
        }

        $page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
        if ( ! in_array( $page, array( 'coachpro-assistants', 'coachpro-ai-providers', 'coachpro-plans' ), true ) ) {
            return;
        }

        wp_enqueue_style(
            'coachpro-admin',
            COACHPRO_PLUGIN_URL . 'admin/css/coachpro-admin.css',
            array(),
            COACHPRO_VERSION
        );
        wp_enqueue_script(
            'coachpro-admin',
            COACHPRO_PLUGIN_URL . 'admin/js/coachpro-admin.js',
            array(),
            COACHPRO_VERSION,
            true
        );
        wp_localize_script(
            'coachpro-admin',
            'coachproAdmin',
            array(
                'page'                => $page,
                'nonce'               => wp_create_nonce( 'wp_rest' ),
                'restUrl'             => rest_url( 'coachpro/v1/admin' ),
                'defaultModelId'      => CoachPro_AI_Provider::get_default_model_id(),
                'providerDefinitions' => CoachPro_Admin_API::get_provider_definitions(),
                'pageUrls'            => array(
                    'assistants'   => admin_url( 'admin.php?page=coachpro-assistants' ),
                    'ai_providers' => admin_url( 'admin.php?page=coachpro-ai-providers' ),
                    'plans'        => admin_url( 'admin.php?page=coachpro-plans' ),
                ),
            )
        );
    }

    // -------------------------------------------------------------------------
    // admin-post handlers
    // -------------------------------------------------------------------------
    public static function handle_adjust_credits() {
        check_admin_referer( 'coachpro_adjust_credits' );
        if ( ! current_user_can( 'coachpro_admin' ) ) wp_die( 'Unauthorized' );

        $user_id = absint( wp_unslash( $_POST['user_id'] ?? 0 ) );
        $amount  = sanitize_text_field( wp_unslash( $_POST['credits'] ?? '0' ) );
        $notes   = sanitize_text_field( wp_unslash( $_POST['notes'] ?? '' ) );
        $mode    = sanitize_key( wp_unslash( $_POST['mode'] ?? 'set' ) );

        if ( $user_id ) {
            if ( 'add' === $mode ) {
                // Credits bought with a manual JazzCash / EasyPaisa / bank payment.
                $result = CoachPro_Credits::add( $user_id, $amount, 'pack_purchase', null, $notes ?: 'Credits added by admin (manual payment)' );
            } else {
                $result = CoachPro_Credits::set( $user_id, $amount, $notes ?: 'Admin manual adjustment' );
            }
            if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()));
        }

        wp_safe_redirect( admin_url( 'admin.php?page=coachpro-users&message=adjusted' ) );
        exit;
    }

    /** Assign a plan after a manual payment, optionally adding the plan's monthly credits. */
    public static function handle_assign_plan() {
        check_admin_referer( 'coachpro_assign_plan' );
        if ( ! current_user_can( 'coachpro_admin' ) ) wp_die( 'Unauthorized' );
        $user_id = absint( wp_unslash( $_POST['user_id'] ?? 0 ) );
        $plan_id = sanitize_text_field( wp_unslash( $_POST['plan_id'] ?? '' ) );
        $grant   = ! empty( $_POST['grant_credits'] );
        if ( $user_id ) {
            $result = CoachPro_Credits::assign_plan( $user_id, $plan_id, $grant );
            if ( is_wp_error( $result ) ) wp_die( esc_html( $result->get_error_message() ) );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=coachpro-users&message=plan' ) );
        exit;
    }
}
