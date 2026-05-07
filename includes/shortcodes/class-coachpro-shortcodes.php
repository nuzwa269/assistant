<?php
/**
 * Class CoachPro_Shortcodes
 * Registers all [coachpro_*] shortcodes.
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Shortcodes {

    public static function register() {
        $shortcodes = array(
            'coachpro'              => 'dashboard',
            'coachpro_dashboard'    => 'dashboard',
            'coachpro_chat'         => 'chat',
            'coachpro_projects'     => 'projects',
            'coachpro_assistants'   => 'assistants',
            'coachpro_saved'        => 'saved',
            'coachpro_buy_credits'  => 'buy_credits',
            'coachpro_settings'     => 'settings',
            'coachpro_login'        => 'login',
            'coachpro_register'     => 'register',
            'coachpro_transactions' => 'transactions',
        );

        foreach ( $shortcodes as $tag => $view ) {
            add_shortcode( $tag, function( $atts ) use ( $view ) {
                return CoachPro_Shortcodes::render( $view, $atts );
            } );
        }
    }

    /**
     * Render a shortcode container.
     *
     * @param string $view  View name.
     * @param array  $atts  Shortcode attributes.
     * @return string HTML
     */
    public static function render( string $view, $atts = array() ) : string {
        $atts = shortcode_atts( array(
            'theme' => 'light',
        ), (array) $atts );

        // Enqueue frontend assets
        wp_enqueue_style(
            'coachpro-frontend',
            COACHPRO_PLUGIN_URL . 'public/css/coachpro-frontend.css',
            array(),
            COACHPRO_VERSION
        );
        wp_enqueue_script(
            'coachpro-frontend',
            COACHPRO_PLUGIN_URL . 'public/js/coachpro-frontend.js',
            array(),
            COACHPRO_VERSION,
            true
        );

        $user_id = get_current_user_id();
        $nonce   = wp_create_nonce( 'wp_rest' );

        $config = wp_json_encode( array(
            'wpUserId'    => $user_id,
            'wpNonce'     => $nonce,
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'restUrl'     => rest_url( 'coachpro/v1' ),
            'loginUrl'    => wp_login_url( get_permalink() ),
            'view'        => $view,
            'theme'       => sanitize_text_field( $atts['theme'] ),
            'supabaseUrl' => null,
            'pluginUrl'   => COACHPRO_PLUGIN_URL,
        ) );

        return sprintf(
            '<div class="coachpro-app" data-view="%s" data-theme="%s" data-config=\'%s\'></div>',
            esc_attr( $view ),
            esc_attr( $atts['theme'] ),
            $config
        );
    }
}
