<?php
/**
 * Plugin Name: CoachPro AI Assistant
 * Plugin URI:  https://github.com/nuzwa269/coachpro-ai-assistant
 * Description: AI coaching with chat, projects, custom assistants, token-based credits, manual payments and a WordPress admin panel.
 * Version:     1.5.3
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/nuzwa269/coachpro-ai-assistant
 * Author:      nuzwa269
 * Text Domain: coachpro-ai
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'COACHPRO_VERSION',     '1.5.3' );
define( 'COACHPRO_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'COACHPRO_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'COACHPRO_PLUGIN_FILE', __FILE__ );

require_once COACHPRO_PLUGIN_DIR . 'includes/class-coachpro-loader.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/class-coachpro-activator.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/class-coachpro-deactivator.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/database/class-coachpro-db.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/auth/class-coachpro-auth.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/credits/class-coachpro-money.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/credits/class-coachpro-credits.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/credits/class-coachpro-pricing.php';
require_once COACHPRO_PLUGIN_DIR . 'includes/ai/class-coachpro-usage.php';
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
