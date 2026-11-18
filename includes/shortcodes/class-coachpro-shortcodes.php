<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Shortcodes {

    public static function register() {
        // Presentation only: leave theme templates and toolbars intact everywhere else.
        add_filter( 'template_include', array( __CLASS__, 'workspace_template' ), 99 );
        add_filter( 'show_admin_bar', array( __CLASS__, 'workspace_admin_bar' ), 99 );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'workspace_assets' ) );
        $map = array(
            'coachpro'              => 'dashboard',
            'coachpro_landing'      => 'landing',
            'coachpro_home'         => 'landing',
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
            'coachpro_help'         => 'help',
        );
        foreach ( $map as $tag => $view ) {
            add_shortcode( $tag, function( $atts ) use ( $view ) {
                return CoachPro_Shortcodes::render( $view, $atts );
            });
        }
    }

    public static function is_workspace_request() : bool {
        if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST )
            || ! is_user_logged_in() || ! is_singular() ) {
            return false;
        }
        $post = get_queried_object();
        if ( ! ( $post instanceof WP_Post ) ) return false;
        foreach ( array( 'coachpro_landing', 'coachpro_home', 'coachpro_login', 'coachpro_register' ) as $tag ) {
            if ( has_shortcode( $post->post_content, $tag ) ) return false;
        }
        foreach ( array( 'coachpro', 'coachpro_dashboard', 'coachpro_projects', 'coachpro_assistants',
            'coachpro_saved', 'coachpro_buy_credits', 'coachpro_help', 'coachpro_settings',
            'coachpro_chat', 'coachpro_transactions' ) as $tag ) {
            if ( has_shortcode( $post->post_content, $tag ) ) return true;
        }
        return false;
    }

    public static function workspace_template( $template ) {
        return self::is_workspace_request()
            ? COACHPRO_PLUGIN_DIR . 'public/partials/coachpro-workspace.php' : $template;
    }

    public static function workspace_admin_bar( $show ) {
        return self::is_workspace_request() ? false : $show;
    }

    public static function workspace_assets() {
        if ( ! self::is_workspace_request() ) return;
        wp_enqueue_style( 'coachpro-frontend', COACHPRO_PLUGIN_URL . 'public/css/coachpro-frontend.css',
            array(), filemtime( COACHPRO_PLUGIN_DIR . 'public/css/coachpro-frontend.css' ) );
        wp_enqueue_script( 'coachpro-frontend', COACHPRO_PLUGIN_URL . 'public/js/coachpro-frontend.js',
            array(), filemtime( COACHPRO_PLUGIN_DIR . 'public/js/coachpro-frontend.js' ), true );
    }

    public static function render( string $view, $atts = array() ) : string {
        // Public presentation is isolated from the authenticated SPA and its assets.
        if ( 'landing' === $view ) {
            wp_enqueue_style( 'coachpro-landing', COACHPRO_PLUGIN_URL . 'public/css/coachpro-landing.css', array(), filemtime( COACHPRO_PLUGIN_DIR . 'public/css/coachpro-landing.css' ) );
            wp_enqueue_style( 'coachpro-motion', COACHPRO_PLUGIN_URL . 'public/css/coachpro-motion.css', array( 'coachpro-landing' ), filemtime( COACHPRO_PLUGIN_DIR . 'public/css/coachpro-motion.css' ) );
            wp_enqueue_style( 'coachpro-marquee', COACHPRO_PLUGIN_URL . 'public/css/coachpro-marquee.css', array( 'coachpro-landing' ), filemtime( COACHPRO_PLUGIN_DIR . 'public/css/coachpro-marquee.css' ) );
            wp_enqueue_style( 'coachpro-typography', COACHPRO_PLUGIN_URL . 'public/css/coachpro-typography.css', array( 'coachpro-landing' ), filemtime( COACHPRO_PLUGIN_DIR . 'public/css/coachpro-typography.css' ) );
            wp_enqueue_script( 'coachpro-landing', COACHPRO_PLUGIN_URL . 'public/js/coachpro-landing.js', array(), filemtime( COACHPRO_PLUGIN_DIR . 'public/js/coachpro-landing.js' ), true );
            wp_enqueue_script( 'coachpro-motion', COACHPRO_PLUGIN_URL . 'public/js/coachpro-motion.js', array( 'coachpro-landing' ), filemtime( COACHPRO_PLUGIN_DIR . 'public/js/coachpro-motion.js' ), true );
            wp_enqueue_script( 'coachpro-marquee', COACHPRO_PLUGIN_URL . 'public/js/coachpro-marquee.js', array( 'coachpro-landing' ), filemtime( COACHPRO_PLUGIN_DIR . 'public/js/coachpro-marquee.js' ), true );
            ob_start();
            include COACHPRO_PLUGIN_DIR . 'public/partials/coachpro-landing.php';
            return ob_get_clean();
        }
        $atts = shortcode_atts( array(
            'theme'      => get_option( 'coachpro_default_theme', 'light' ),
            'height'     => '',
            'project_id' => '',
        ), (array) $atts );

        // ── Auth Guard ──────────────────────────────────────────────
        $public_views = array( 'login', 'register', 'landing' );
        if ( ! is_user_logged_in() && ! in_array( $view, $public_views, true ) ) {
            // Redirect to login page
            $login_page_id = get_option( 'coachpro_page_login' );
            if ( $login_page_id ) {
                $login_url = get_permalink( $login_page_id );
            } else {
                // Try to find a page with slug 'login'
                $login_page = get_page_by_path( 'login' );
                $login_url  = $login_page ? get_permalink( $login_page->ID ) : home_url( '/login' );
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
            $redirect_to   = esc_url_raw( home_url( $request_uri ) );
            if ( ! $redirect_to ) {
                $redirect_to = home_url();
            }
            $login_url     = add_query_arg( 'redirect_to', $redirect_to, $login_url );
            // Cannot use wp_redirect() inside a shortcode — headers already sent.
            // Return a JS-based redirect instead.
            return sprintf(
                '<script>window.location.href=%s;</script>',
                wp_json_encode( $login_url )
            );
        }

        // ── Logged-in redirect for login/register only (landing remains public) ──
        if ( is_user_logged_in() && in_array( $view, array( 'login', 'register' ), true ) ) {
            $dash_id  = get_option( 'coachpro_page_dashboard' );
            $dash_url = $dash_id ? get_permalink( $dash_id ) : home_url( '/dashboard' );
            // Cannot use wp_redirect() inside a shortcode — headers already sent.
            return sprintf(
                '<script>window.location.href=%s;</script>',
                wp_json_encode( $dash_url )
            );
        }

        // ── Assets ─────────────────────────────────────────────────
        wp_enqueue_style(
            'coachpro-frontend',
            COACHPRO_PLUGIN_URL . 'public/css/coachpro-frontend.css',
            array(), COACHPRO_VERSION
        );
        wp_enqueue_script(
            'coachpro-frontend',
            COACHPRO_PLUGIN_URL . 'public/js/coachpro-frontend.js',
            array(), COACHPRO_VERSION, true
        );

        // ── Config ─────────────────────────────────────────────────
        $user_id = get_current_user_id();
        $config  = wp_json_encode( array(
            'wpUserId'       => $user_id ?: null,
            'wpNonce'        => wp_create_nonce( 'wp_rest' ),
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'restUrl'        => rest_url( 'coachpro/v1' ),
            'defaultModelId' => CoachPro_AI_Provider::get_default_model_id(),
            'view'           => $view,
            'theme'          => sanitize_text_field( $atts['theme'] ),
            'projectId'      => sanitize_text_field( $atts['project_id'] ),
            'supabaseUrl'    => null,
            'pluginUrl'      => COACHPRO_PLUGIN_URL,
            'paymentDetails' => CoachPro_Payments_API::payment_details(),
            'googleClientId' => ! empty( get_option( 'coachpro_google_client_id', '' ) ),
            // Page URLs for JS navigation
            'pageUrls'       => array(
                'landing'      => get_permalink( get_option('coachpro_page_landing') ) ?: home_url( '/' ),
                'login'        => get_permalink( get_option('coachpro_page_login') ) ?: '',
                'register'     => get_permalink( get_option('coachpro_page_register') ) ?: '',
                'dashboard'    => get_permalink( get_option('coachpro_page_dashboard') ) ?: '',
                'projects'     => get_permalink( get_option('coachpro_page_projects') ) ?: '',
                'chat'         => get_permalink( get_option('coachpro_page_chat') ) ?: '',
                'assistants'   => get_permalink( get_option('coachpro_page_assistants') ) ?: '',
                'saved'        => get_permalink( get_option('coachpro_page_saved') ) ?: '',
                'buy_credits'  => get_permalink( get_option('coachpro_page_buy_credits') ) ?: '',
                'settings'     => get_permalink( get_option('coachpro_page_settings') ) ?: '',
                'transactions' => get_permalink( get_option('coachpro_page_transactions') ) ?: '',
                'help'         => get_permalink( get_option('coachpro_page_help') ) ?: '',
            ),
        ));

        $style = $atts['height'] ? 'style="min-height:' . esc_attr($atts['height']) . '"' : '';
        return sprintf(
            '<div class="coachpro-app" data-view="%s" data-theme="%s" data-config=\'%s\' %s></div>',
            esc_attr( $view ),
            esc_attr( $atts['theme'] ),
            esc_attr($config),
            $style
        );
    }
}
