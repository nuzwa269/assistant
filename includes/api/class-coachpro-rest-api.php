<?php
/**
 * Class CoachPro_REST_API — registers all REST routes.
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_REST_API {

    const NS = 'coachpro/v1';

    private static function route( $namespace, $path, $definitions ) {
        $single = isset($definitions['callback']);
        $routes = $single ? array($definitions) : $definitions;
        foreach ($routes as &$route) {
            $callback = $route['callback'];
            if ( 'GET' !== $route['methods'] && '/chat' !== $path ) {
                $route['callback'] = function($request) use ($callback) {
                    return CoachPro_DB::transaction(function() use ($callback, $request) {
                        return call_user_func($callback, $request);
                    }, get_current_user_id());
                };
            }
        }
        unset($route);
        register_rest_route($namespace, $path, $single ? $routes[0] : $routes);
    }

    public static function register_routes() {
        // Auth
        self::route( self::NS, '/auth/me', array(
            'methods'             => 'GET',
            'callback'            => array( 'CoachPro_Auth', 'rest_me' ),
            'permission_callback' => '__return_true',
        ) );

        // Profile
        self::route( self::NS, '/profile', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( 'CoachPro_Profile_API', 'get_profile' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
            array(
                'methods'             => 'PUT',
                'callback'            => array( 'CoachPro_Profile_API', 'update_profile' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
        ) );
        self::route( self::NS, '/transactions', array(
            'methods'             => 'GET',
            'callback'            => array( 'CoachPro_Profile_API', 'get_transactions' ),
            'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
        ) );

        // Projects
        self::route( self::NS, '/projects', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( 'CoachPro_Projects_API', 'list_projects' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( 'CoachPro_Projects_API', 'create_project' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
        ) );
        self::route( self::NS, '/projects/(?P<id>[a-z0-9\-]+)', array(
            array(
                'methods'             => 'PUT',
                'callback'            => array( 'CoachPro_Projects_API', 'update_project' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( 'CoachPro_Projects_API', 'delete_project' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
        ) );

        // Assistants
        self::route( self::NS, '/assistants', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( 'CoachPro_Assistants_API', 'list_assistants' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( 'CoachPro_Assistants_API', 'create_assistant' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
        ) );
        self::route( self::NS, '/assistants/(?P<id>[a-z0-9\-]+)', array(
            array(
                'methods'             => 'PUT',
                'callback'            => array( 'CoachPro_Assistants_API', 'update_assistant' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( 'CoachPro_Assistants_API', 'delete_assistant' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
        ) );
        self::route( self::NS, '/assistants/(?P<id>[a-z0-9\-]+)/activate', array(
            array(
                'methods'             => 'POST',
                'callback'            => array( 'CoachPro_Assistants_API', 'activate_assistant' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( 'CoachPro_Assistants_API', 'deactivate_assistant' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
        ) );

        // Conversations
        self::route( self::NS, '/conversations', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( 'CoachPro_Conversations_API', 'list_conversations' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( 'CoachPro_Conversations_API', 'create_conversation' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
        ) );
        self::route( self::NS, '/conversations/(?P<id>[a-z0-9\-]+)', array(
            array(
                'methods'             => 'PUT',
                'callback'            => array( 'CoachPro_Conversations_API', 'update_conversation' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( 'CoachPro_Conversations_API', 'delete_conversation' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
        ) );
        self::route( self::NS, '/conversations/(?P<id>[a-z0-9\-]+)/messages', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( 'CoachPro_Conversations_API', 'get_messages' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( 'CoachPro_Conversations_API', 'add_message' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
        ) );

        // Chat (AI call)
        self::route( self::NS, '/chat', array(
            'methods'             => 'POST',
            'callback'            => array( 'CoachPro_Chat_API', 'handle_chat' ),
            'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
        ) );

        // Saved Responses
        self::route( self::NS, '/saved-responses', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( 'CoachPro_Profile_API', 'get_saved_responses' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( 'CoachPro_Profile_API', 'save_response' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
        ) );
        self::route( self::NS, '/saved-responses/(?P<id>[a-z0-9\-]+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( 'CoachPro_Profile_API', 'delete_saved_response' ),
            'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
        ) );

        // Plans & Payments
        self::route( self::NS, '/plans', array(
            'methods'             => 'GET',
            'callback'            => array( 'CoachPro_Payments_API', 'list_plans' ),
            'permission_callback' => '__return_true',
        ) );
        self::route( self::NS, '/credit-packs', array(
            'methods'             => 'GET',
            'callback'            => array( 'CoachPro_Payments_API', 'list_credit_packs' ),
            'permission_callback' => '__return_true',
        ) );
        self::route( self::NS, '/payments', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( 'CoachPro_Payments_API', 'list_payments' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( 'CoachPro_Payments_API', 'create_payment' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
            ),
        ) );
        self::route( self::NS, '/payments/(?P<id>[a-z0-9\-]+)/upload-proof', array(
            'methods'             => 'POST',
            'callback'            => array( 'CoachPro_Payments_API', 'upload_proof' ),
            'permission_callback' => array( 'CoachPro_REST_API', 'is_logged_in' ),
        ) );

        // Admin
        self::route( self::NS, '/admin/stats', array(
            'methods'             => 'GET',
            'callback'            => array( 'CoachPro_Admin_API', 'get_stats' ),
            'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
        ) );
        self::route( self::NS, '/admin/users', array(
            'methods'             => 'GET',
            'callback'            => array( 'CoachPro_Admin_API', 'list_users' ),
            'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
        ) );
        self::route( self::NS, '/admin/users/(?P<id>[0-9]+)', array(
            'methods'             => 'PUT',
            'callback'            => array( 'CoachPro_Admin_API', 'update_user' ),
            'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
        ) );
        self::route( self::NS, '/admin/payments', array(
            'methods'             => 'GET',
            'callback'            => array( 'CoachPro_Admin_API', 'list_payments' ),
            'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
        ) );
        self::route( self::NS, '/admin/payments/(?P<id>[a-z0-9\-]+)/approve', array(
            'methods'             => 'POST',
            'callback'            => array( 'CoachPro_Admin_API', 'approve_payment' ),
            'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
        ) );
        self::route( self::NS, '/admin/payments/(?P<id>[a-z0-9\-]+)/reject', array(
            'methods'             => 'POST',
            'callback'            => array( 'CoachPro_Admin_API', 'reject_payment' ),
            'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
        ) );
        self::route( self::NS, '/admin/models', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( 'CoachPro_Admin_API', 'list_models' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( 'CoachPro_Admin_API', 'create_model' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
        ) );
        self::route( self::NS, '/admin/models/(?P<id>[^/]+)', array(
            array(
                'methods'             => 'PUT',
                'callback'            => array( 'CoachPro_Admin_API', 'update_model' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( 'CoachPro_Admin_API', 'delete_model' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
        ) );
        self::route( self::NS, '/admin/provider-settings', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( 'CoachPro_Admin_API', 'get_provider_settings' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( 'CoachPro_Admin_API', 'update_provider_settings' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
        ) );
        self::route( self::NS, '/admin/provider-settings/test', array(
            'methods'             => 'POST',
            'callback'            => array( 'CoachPro_Admin_API', 'test_provider_connection' ),
            'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
        ) );
        self::route( self::NS, '/admin/assistants', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( 'CoachPro_Admin_API', 'list_assistants' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( 'CoachPro_Admin_API', 'create_assistant' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
        ) );
        self::route( self::NS, '/admin/assistants/(?P<id>[a-z0-9\-]+)', array(
            array(
                'methods'             => 'PUT',
                'callback'            => array( 'CoachPro_Admin_API', 'update_assistant' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( 'CoachPro_Admin_API', 'delete_assistant' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
        ) );
        self::route( self::NS, '/admin/plans', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( 'CoachPro_Admin_API', 'list_plans' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( 'CoachPro_Admin_API', 'create_plan' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
        ) );
        self::route( self::NS, '/admin/plans/(?P<id>[a-z0-9\-]+)', array(
            array(
                'methods'             => 'PUT',
                'callback'            => array( 'CoachPro_Admin_API', 'update_plan' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( 'CoachPro_Admin_API', 'delete_plan' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
        ) );
        self::route( self::NS, '/admin/packs', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( 'CoachPro_Admin_API', 'list_packs' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( 'CoachPro_Admin_API', 'create_pack' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
        ) );
        self::route( self::NS, '/admin/packs/(?P<id>[a-z0-9\-]+)', array(
            array(
                'methods'             => 'PUT',
                'callback'            => array( 'CoachPro_Admin_API', 'update_pack' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( 'CoachPro_Admin_API', 'delete_pack' ),
                'permission_callback' => array( 'CoachPro_REST_API', 'is_coachpro_admin' ),
            ),
        ) );

        // Google OAuth
        self::route( self::NS, '/auth/google', array(
            'methods'             => 'GET',
            'callback'            => array( 'CoachPro_Auth', 'rest_google_oauth' ),
            'permission_callback' => '__return_true',
        ) );
        self::route( self::NS, '/auth/google/callback', array(
            'methods'             => 'GET',
            'callback'            => array( 'CoachPro_Auth', 'rest_google_callback' ),
            'permission_callback' => '__return_true',
        ) );
    }

    // -------------------------------------------------------------------------
    // Permission callbacks
    // -------------------------------------------------------------------------
    public static function is_logged_in() : bool {
        return is_user_logged_in();
    }

    public static function is_coachpro_admin() : bool {
        return current_user_can( 'manage_options' ) || current_user_can( 'coachpro_admin' );
    }
}
