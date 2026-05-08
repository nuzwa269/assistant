<?php
/**
 * Class CoachPro_Activator
 * Creates all database tables and inserts default data on plugin activation.
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Activator {

    public static function activate() {
        self::create_tables();
        self::insert_defaults();
        self::create_roles();
        self::create_pages();
        update_option( 'coachpro_db_version', COACHPRO_VERSION );
        flush_rewrite_rules();
    }

    public static function maybe_upgrade() {
        $db_version = (string) get_option( 'coachpro_db_version', '0.0.0' );
        if ( version_compare( $db_version, COACHPRO_VERSION, '>=' ) ) {
            return;
        }

        self::create_tables();
        self::seed_default_models( false );
        self::ensure_default_model_flag();
        update_option( 'coachpro_db_version', COACHPRO_VERSION );
    }

    // -------------------------------------------------------------------------
    // Table creation via dbDelta
    // -------------------------------------------------------------------------
    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $p       = $wpdb->prefix;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = array();

        // 1. AI Models
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}coachpro_ai_models` (
            id VARCHAR(100) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            provider VARCHAR(100) NOT NULL,
            provider_type ENUM('openai_compatible','anthropic','gemini','lovable') NOT NULL DEFAULT 'openai_compatible',
            category ENUM('text','image','reasoning') NOT NULL DEFAULT 'text',
            credits_cost INT NOT NULL DEFAULT 1,
            min_plan ENUM('free','basic','pro') NOT NULL DEFAULT 'free',
            api_key_secret_name VARCHAR(100) DEFAULT NULL,
            api_base_url VARCHAR(500) DEFAULT NULL,
            api_model_name VARCHAR(200) DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            description TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        // 2. Assistants
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}coachpro_assistants` (
            id CHAR(36) NOT NULL,
            owner_id BIGINT UNSIGNED DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            system_prompt LONGTEXT NOT NULL,
            icon VARCHAR(100) NOT NULL DEFAULT 'Bot',
            category VARCHAR(100) DEFAULT NULL,
            is_prebuilt TINYINT(1) NOT NULL DEFAULT 0,
            provider VARCHAR(100) DEFAULT NULL,
            default_model_id VARCHAR(100) DEFAULT NULL,
            temperature DECIMAL(4,2) DEFAULT 0.70,
            max_tokens INT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_owner (owner_id),
            KEY idx_prebuilt (is_prebuilt)
        ) $charset;";

        // 3. User Active Assistants
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}coachpro_user_active_assistants` (
            id CHAR(36) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            assistant_id CHAR(36) NOT NULL,
            activated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_ua (user_id, assistant_id),
            KEY idx_user (user_id)
        ) $charset;";

        // 4. Projects
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}coachpro_projects` (
            id CHAR(36) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id)
        ) $charset;";

        // 5. Conversations
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}coachpro_conversations` (
            id CHAR(36) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            project_id CHAR(36) NOT NULL,
            assistant_id CHAR(36) NOT NULL,
            title VARCHAR(500) NOT NULL DEFAULT 'New conversation',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_project (project_id),
            KEY idx_user (user_id)
        ) $charset;";

        // 6. Messages
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}coachpro_messages` (
            id CHAR(36) NOT NULL,
            conversation_id CHAR(36) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role ENUM('user','assistant','system') NOT NULL,
            content LONGTEXT NOT NULL,
            model_id VARCHAR(100) DEFAULT NULL,
            credits_used INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_conv (conversation_id),
            KEY idx_user (user_id)
        ) $charset;";

        // 7. Conversation Summaries
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}coachpro_conv_summaries` (
            id CHAR(36) NOT NULL,
            conversation_id CHAR(36) NOT NULL,
            summary LONGTEXT DEFAULT NULL,
            durable_facts LONGTEXT DEFAULT NULL,
            summarized_up_to_message_id CHAR(36) DEFAULT NULL,
            message_count_at_summary INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_conv (conversation_id)
        ) $charset;";

        // 8. Saved Responses
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}coachpro_saved_responses` (
            id CHAR(36) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            message_id CHAR(36) NOT NULL,
            project_id CHAR(36) DEFAULT NULL,
            note TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_um (user_id, message_id),
            KEY idx_user (user_id)
        ) $charset;";

        // 9. Plans
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}coachpro_plans` (
            id VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            price_pkr INT NOT NULL DEFAULT 0,
            monthly_credits INT NOT NULL DEFAULT 0,
            max_projects INT DEFAULT NULL,
            max_custom_assistants INT DEFAULT NULL,
            max_saved_responses INT DEFAULT NULL,
            features LONGTEXT DEFAULT NULL,
            is_popular TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        // 10. Credit Packs
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}coachpro_credit_packs` (
            id CHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            credits INT NOT NULL,
            price_pkr INT NOT NULL,
            is_popular TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        // 11. Payments
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}coachpro_payments` (
            id CHAR(36) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            kind ENUM('subscription','credit_pack') NOT NULL,
            plan_id VARCHAR(50) DEFAULT NULL,
            pack_id CHAR(36) DEFAULT NULL,
            amount_pkr INT NOT NULL,
            method ENUM('jazzcash','easypaisa','bank_transfer','whatsapp') NOT NULL,
            sender_name VARCHAR(255) DEFAULT NULL,
            sender_phone VARCHAR(50) DEFAULT NULL,
            reference_no VARCHAR(255) DEFAULT NULL,
            proof_url TEXT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            reviewed_by BIGINT UNSIGNED DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            admin_notes TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_status (status)
        ) $charset;";

        // 12. Transactions
        $sql[] = "CREATE TABLE IF NOT EXISTS `{$p}coachpro_transactions` (
            id CHAR(36) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            amount INT NOT NULL,
            kind ENUM('signup_bonus','subscription_grant','pack_purchase','message_deduct','admin_adjust','refund') NOT NULL,
            balance_after INT NOT NULL,
            reference_id CHAR(36) DEFAULT NULL,
            model_id VARCHAR(100) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_date (user_id, created_at)
        ) $charset;";

        foreach ( $sql as $query ) {
            dbDelta( $query );
        }
    }

    // -------------------------------------------------------------------------
    // Default data
    // -------------------------------------------------------------------------
    private static function insert_defaults() {
        global $wpdb;
        $p = $wpdb->prefix;

        // ---- Plans ----
        $plans = array(
            array(
                'id'                   => 'free',
                'name'                 => 'Free',
                'price_pkr'            => 0,
                'monthly_credits'      => 50,
                'max_projects'         => 3,
                'max_custom_assistants'=> 1,
                'max_saved_responses'  => 10,
                'features'             => wp_json_encode( array( '50 credits/month', '3 projects', '1 custom assistant', '10 saved responses' ) ),
                'is_popular'           => 0,
                'is_active'            => 1,
                'sort_order'           => 1,
            ),
            array(
                'id'                   => 'basic',
                'name'                 => 'Basic',
                'price_pkr'            => 999,
                'monthly_credits'      => 500,
                'max_projects'         => null,
                'max_custom_assistants'=> null,
                'max_saved_responses'  => null,
                'features'             => wp_json_encode( array( '500 credits/month', 'Unlimited projects', 'Unlimited assistants', 'Unlimited saved responses' ) ),
                'is_popular'           => 1,
                'is_active'            => 1,
                'sort_order'           => 2,
            ),
            array(
                'id'                   => 'pro',
                'name'                 => 'Pro',
                'price_pkr'            => 2499,
                'monthly_credits'      => 2000,
                'max_projects'         => null,
                'max_custom_assistants'=> null,
                'max_saved_responses'  => null,
                'features'             => wp_json_encode( array( '2000 credits/month', 'Unlimited everything', 'Priority support', 'Access to reasoning models' ) ),
                'is_popular'           => 0,
                'is_active'            => 1,
                'sort_order'           => 3,
            ),
        );
        foreach ( $plans as $plan ) {
            $wpdb->replace( "{$p}coachpro_plans", $plan );
        }

        // ---- Credit Packs ----
        $packs = array(
            array( 'id' => wp_generate_uuid4(), 'name' => 'Starter Pack',    'credits' => 100,  'price_pkr' => 299,  'is_popular' => 0, 'is_active' => 1, 'sort_order' => 1 ),
            array( 'id' => wp_generate_uuid4(), 'name' => 'Value Pack',      'credits' => 500,  'price_pkr' => 999,  'is_popular' => 1, 'is_active' => 1, 'sort_order' => 2 ),
            array( 'id' => wp_generate_uuid4(), 'name' => 'Power Pack',      'credits' => 1500, 'price_pkr' => 2499, 'is_popular' => 0, 'is_active' => 1, 'sort_order' => 3 ),
        );
        foreach ( $packs as $pack ) {
            $wpdb->insert( "{$p}coachpro_credit_packs", $pack );
        }

        // ---- Prebuilt Assistants ----
        $assistants = array(
            array(
                'id'            => wp_generate_uuid4(),
                'owner_id'      => null,
                'name'          => 'Life Coach',
                'description'   => 'Personal development and goal-setting expert',
                'system_prompt' => 'You are an empathetic and motivating Life Coach. Help users with personal development, goal-setting, work-life balance, overcoming obstacles, and finding purpose. Ask powerful questions. Keep responses practical and actionable.',
                'icon'          => 'GraduationCap',
                'category'      => 'Lifestyle',
                'is_prebuilt'   => 1,
                'provider'      => 'OpenAI',
                'default_model_id' => 'gpt-4o-mini',
                'temperature'   => 0.70,
                'max_tokens'    => 1024,
                'is_active'     => 1,
            ),
            array(
                'id'            => wp_generate_uuid4(),
                'owner_id'      => null,
                'name'          => 'Business Coach',
                'description'   => 'Startup strategy and business growth advisor',
                'system_prompt' => 'You are an experienced Business Coach specialising in startups, growth strategy, sales, marketing, and leadership. Provide actionable business advice grounded in real-world experience. Be concise yet comprehensive.',
                'icon'          => 'Building2',
                'category'      => 'Business',
                'is_prebuilt'   => 1,
                'provider'      => 'OpenAI',
                'default_model_id' => 'gpt-4o-mini',
                'temperature'   => 0.70,
                'max_tokens'    => 1024,
                'is_active'     => 1,
            ),
            array(
                'id'            => wp_generate_uuid4(),
                'owner_id'      => null,
                'name'          => 'Fitness Coach',
                'description'   => 'Health, fitness, and nutrition guidance',
                'system_prompt' => 'You are a knowledgeable Fitness Coach and nutritionist. Help users with workout plans, nutrition advice, healthy habits, and fitness goals. Always recommend consulting a healthcare professional for medical concerns.',
                'icon'          => 'Lightbulb',
                'category'      => 'Health',
                'is_prebuilt'   => 1,
                'provider'      => 'OpenAI',
                'default_model_id' => 'gpt-4o-mini',
                'temperature'   => 0.70,
                'max_tokens'    => 1024,
                'is_active'     => 1,
            ),
        );
        foreach ( $assistants as $assistant ) {
            $wpdb->insert( "{$p}coachpro_assistants", $assistant );
        }

        self::seed_default_models();
        self::ensure_default_model_flag();
    }

    private static function seed_default_models( bool $replace = true ) {
        global $wpdb;
        $p = $wpdb->prefix;

        $models = array(
            array(
                'id'                  => 'gpt-4o-mini',
                'display_name'        => 'GPT-4o Mini',
                'provider'            => 'OpenAI',
                'provider_type'       => 'openai_compatible',
                'category'            => 'text',
                'credits_cost'        => 1,
                'min_plan'            => 'free',
                'api_key_secret_name' => 'coachpro_openai_key',
                'api_base_url'        => 'https://api.openai.com/v1',
                'api_model_name'      => 'gpt-4o-mini',
                'is_active'           => 1,
                'is_default'          => 1,
                'description'         => 'Fast and affordable model for most tasks.',
            ),
            array(
                'id'                  => 'gpt-4o',
                'display_name'        => 'GPT-4o',
                'provider'            => 'OpenAI',
                'provider_type'       => 'openai_compatible',
                'category'            => 'text',
                'credits_cost'        => 5,
                'min_plan'            => 'basic',
                'api_key_secret_name' => 'coachpro_openai_key',
                'api_base_url'        => 'https://api.openai.com/v1',
                'api_model_name'      => 'gpt-4o',
                'is_active'           => 1,
                'is_default'          => 0,
                'description'         => 'Most capable GPT-4 class model.',
            ),
            array(
                'id'                  => 'claude-3-5-haiku',
                'display_name'        => 'Claude 3.5 Haiku',
                'provider'            => 'Anthropic',
                'provider_type'       => 'anthropic',
                'category'            => 'text',
                'credits_cost'        => 2,
                'min_plan'            => 'free',
                'api_key_secret_name' => 'coachpro_anthropic_key',
                'api_base_url'        => 'https://api.anthropic.com',
                'api_model_name'      => 'claude-3-5-haiku-20241022',
                'is_active'           => 1,
                'is_default'          => 0,
                'description'         => 'Fast and affordable Claude model.',
            ),
            array(
                'id'                  => 'gemini-1.5-flash',
                'display_name'        => 'Gemini 1.5 Flash',
                'provider'            => 'Google Gemini',
                'provider_type'       => 'gemini',
                'category'            => 'text',
                'credits_cost'        => 1,
                'min_plan'            => 'free',
                'api_key_secret_name' => 'coachpro_gemini_key',
                'api_base_url'        => 'https://generativelanguage.googleapis.com/v1beta',
                'api_model_name'      => 'gemini-1.5-flash',
                'is_active'           => 1,
                'is_default'          => 0,
                'description'         => 'Google Gemini model for fast everyday responses.',
            ),
        );
        foreach ( $models as $model ) {
            if ( $replace ) {
                $wpdb->replace( "{$p}coachpro_ai_models", $model );
                continue;
            }

            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$p}coachpro_ai_models` WHERE id = %s LIMIT 1", $model['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ( ! $exists ) {
                $wpdb->insert( "{$p}coachpro_ai_models", $model );
            }
        }
    }

    private static function ensure_default_model_flag() {
        global $wpdb;
        $table = "{$wpdb->prefix}coachpro_ai_models";
        $default_id = $wpdb->get_var( "SELECT id FROM `{$table}` WHERE is_default = 1 LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ( $default_id ) {
            return;
        }

        $fallback_id = $wpdb->get_var( "SELECT id FROM `{$table}` WHERE is_active = 1 ORDER BY credits_cost ASC, created_at ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $fallback_id ) {
            $wpdb->update(
                $table,
                array( 'is_default' => 1 ),
                array( 'id' => $fallback_id ),
                array( '%d' ),
                array( '%s' )
            );
        }
    }

    // -------------------------------------------------------------------------
    // WordPress roles
    // -------------------------------------------------------------------------
    private static function create_roles() {
        add_role(
            'coachpro_user',
            __( 'CoachPro User', 'coachpro-ai' ),
            array( 'read' => true )
        );

        add_role(
            'coachpro_admin',
            __( 'CoachPro Admin', 'coachpro-ai' ),
            array(
                'read'            => true,
                'coachpro_admin'  => true,
            )
        );
    }

    private static function create_pages() {
        $pages = array(
            'coachpro_page_login'        => array( 'slug' => 'coachpro-login',    'title' => 'Login',           'shortcode' => '[coachpro_login]' ),
            'coachpro_page_register'     => array( 'slug' => 'coachpro-register', 'title' => 'Register',        'shortcode' => '[coachpro_register]' ),
            'coachpro_page_dashboard'    => array( 'slug' => 'dashboard',         'title' => 'Dashboard',       'shortcode' => '[coachpro_dashboard]' ),
            'coachpro_page_projects'     => array( 'slug' => 'projects',          'title' => 'My Projects',     'shortcode' => '[coachpro_projects]' ),
            'coachpro_page_chat'         => array( 'slug' => 'chat',              'title' => 'Chat',            'shortcode' => '[coachpro_chat]' ),
            'coachpro_page_assistants'   => array( 'slug' => 'assistants',        'title' => 'Assistants',      'shortcode' => '[coachpro_assistants]' ),
            'coachpro_page_saved'        => array( 'slug' => 'saved-responses',   'title' => 'Saved Responses', 'shortcode' => '[coachpro_saved]' ),
            'coachpro_page_buy_credits'  => array( 'slug' => 'buy-credits',       'title' => 'Buy Credits',     'shortcode' => '[coachpro_buy_credits]' ),
            'coachpro_page_transactions' => array( 'slug' => 'transactions',      'title' => 'Transactions',    'shortcode' => '[coachpro_transactions]' ),
            'coachpro_page_settings'     => array( 'slug' => 'coachpro-settings', 'title' => 'Settings',        'shortcode' => '[coachpro_settings]' ),
            'coachpro_page_help'         => array( 'slug' => 'coachpro-help',     'title' => 'Help',            'shortcode' => '[coachpro_help]' ),
        );

        foreach ( $pages as $option_key => $page ) {
            // اگر option پہلے سے موجود ہو اور page موجود ہو تو skip
            $existing_id = get_option( $option_key );
            if ( $existing_id && get_post( $existing_id ) ) {
                continue;
            }
            // Slug سے تلاش کریں
            $existing = get_page_by_path( $page['slug'] );
            if ( $existing ) {
                update_option( $option_key, $existing->ID );
                continue;
            }
            // نیا page بنائیں
            $page_id = wp_insert_post( array(
                'post_title'   => $page['title'],
                'post_name'    => $page['slug'],
                'post_content' => $page['shortcode'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => 1,
            ));
            if ( $page_id && ! is_wp_error( $page_id ) ) {
                update_option( $option_key, $page_id );
            }
        }
    }
}
