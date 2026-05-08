<?php
/**
 * Plugin Name: CoachPro AI Assistant
 * Plugin URI:  https://github.com/nuzwa269/coachpro-ai-assistant
 * Description: Complete AI Coaching Assistant — chat, projects, assistants, credits, payments. 100% WordPress native.
 * Version:     1.1.0
 * Author:      nuzwa269
 * Text Domain: coachpro-ai
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'COACHPRO_VERSION',     '1.1.0' );
define( 'COACHPRO_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'COACHPRO_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'COACHPRO_PLUGIN_FILE', __FILE__ );

require_once COACHPRO_PLUGIN_DIR . 'includes/class-coachpro-loader.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/class-coachpro-activator.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/class-coachpro-deactivator.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/database/class-coachpro-db.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/auth/class-coachpro-auth.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/credits/class-coachpro-credits.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/ai/class-coachpro-ai-provider.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/api/class-coachpro-rest-api.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/api/class-coachpro-chat-api.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/api/class-coachpro-projects-api.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/api/class-coachpro-assistants-api.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/api/class-coachpro-conversations-api.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/api/class-coachpro-payments-api.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/api/class-coachpro-profile-api.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/api/class-coachpro-admin-api.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/shortcodes/class-coachpro-shortcodes.php';
require_once COACHPRO_PLUGIN_DIR . 'admin/class-coachpro-admin.php';

register_activation_hook(   __FILE__, array( 'CoachPro_Activator',   'activate' ) );
register_deactivation_hook( __FILE__, array( 'CoachPro_Deactivator', 'deactivate' ) );

function coachpro_run() {
    $loader = new CoachPro_Loader();
    $loader->run();
}
coachpro_run();
