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
        add_submenu_page( 'coachpro-ai', __( 'Prebuilt Assistants', 'coachpro-ai' ), __( 'Prebuilt Assistants', 'coachpro-ai' ), 'manage_options', 'coachpro-assistants', array( __CLASS__, 'page_assistants' ) );
        add_submenu_page( 'coachpro-ai', __( 'AI Providers', 'coachpro-ai' ), __( 'AI Providers', 'coachpro-ai' ), 'manage_options', 'coachpro-ai-providers', array( __CLASS__, 'page_ai_providers' ) );
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
            'coachpro_gemini_key',
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
    public static function page_ai_providers() {
        require_once COACHPRO_PLUGIN_DIR . 'admin/views/ai-providers.php';
    }
    public static function page_plans() {
        require_once COACHPRO_PLUGIN_DIR . 'admin/views/plans.php';
    }
    public static function page_settings() {
        require_once COACHPRO_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public static function enqueue_assets() {
        if ( ! current_user_can( 'manage_options' ) ) {
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
    public static function handle_approve_payment() {
        check_admin_referer( 'coachpro_approve_payment' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $id = sanitize_text_field( wp_unslash( $_POST['payment_id'] ?? '' ) );
        if ( $id ) {
            global $wpdb;
            $t_pay   = CoachPro_DB::table( 'payments' );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t_pay}` WHERE id = %s", $id ), ARRAY_A );

            if ( $payment && 'pending' === $payment['status'] ) {
                $admin_notes = sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) );
                $wpdb->update(
                    $t_pay,
                    array(
                        'status'      => 'approved',
                        'reviewed_by' => get_current_user_id(),
                        'reviewed_at' => current_time( 'mysql' ),
                        'admin_notes' => $admin_notes,
                    ),
                    array( 'id' => $id ),
                    array( '%s', '%d', '%s', '%s' ),
                    array( '%s' )
                );
                $user_id = (int) $payment['user_id'];
                if ( 'credit_pack' === $payment['kind'] && $payment['pack_id'] ) {
                    $pack = CoachPro_DB::get_row( 'credit_packs', $payment['pack_id'] );
                    if ( $pack ) {
                        CoachPro_Credits::add( $user_id, (int) $pack['credits'], 'pack_purchase', $id, 'Credit pack purchase approved' );
                    }
                } elseif ( 'subscription' === $payment['kind'] && $payment['plan_id'] ) {
                    $plan = CoachPro_DB::get_row( 'plans', $payment['plan_id'] );
                    update_user_meta( $user_id, 'coachpro_plan', $payment['plan_id'] );
                    update_user_meta( $user_id, 'coachpro_plan_renews', gmdate( 'Y-m-d H:i:s', time() + ( 30 * DAY_IN_SECONDS ) ) );
                    if ( $plan ) {
                        CoachPro_Credits::add( $user_id, (int) $plan['monthly_credits'], 'subscription_grant', $id, 'Subscription activated: ' . $payment['plan_id'] );
                    }
                }
            }
        }

        wp_redirect( admin_url( 'admin.php?page=coachpro-payments&message=approved' ) );
        exit;
    }

    public static function handle_reject_payment() {
        check_admin_referer( 'coachpro_reject_payment' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $id = sanitize_text_field( wp_unslash( $_POST['payment_id'] ?? '' ) );
        if ( $id ) {
            global $wpdb;
            $t_pay   = CoachPro_DB::table( 'payments' );
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t_pay}` WHERE id = %s", $id ), ARRAY_A );
            if ( $payment && 'pending' === $payment['status'] ) {
                $admin_notes = sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) );
                $wpdb->update(
                    $t_pay,
                    array(
                        'status'      => 'rejected',
                        'reviewed_by' => get_current_user_id(),
                        'reviewed_at' => current_time( 'mysql' ),
                        'admin_notes' => $admin_notes,
                    ),
                    array( 'id' => $id ),
                    array( '%s', '%d', '%s', '%s' ),
                    array( '%s' )
                );
            }
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
