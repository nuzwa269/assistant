<?php
/** Isolated integration bootstrap. Never reads the installed site's wp-config.php. */
$root = dirname(__DIR__);
$core = getenv('COACHPRO_WP_ROOT') ?: 'C:/xampp/htdocs/ai-assistant/';
$host = getenv('COACHPRO_TEST_DB_HOST') ?: '127.0.0.1:3308';
if (!in_array($host, array('127.0.0.1:3308', 'localhost:3308'), true)) throw new RuntimeException('Tests require the isolated database on port 3308.');
// WordPress's database error page exits with status 0; fail before loading it.
mysqli_report(MYSQLI_REPORT_OFF);
$connection = @mysqli_connect(explode(':', $host)[0], 'root', '', 'coachpro_integration_test', 3308);
if (!$connection) throw new RuntimeException('Start the isolated test database on port 3308 before running tests.');
mysqli_close($connection);
define('ABSPATH', rtrim($core, '/\\') . '/');
define('WP_INSTALLING', true);
define('DB_NAME', 'coachpro_integration_test');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_HOST', $host);
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', true);
define('DISABLE_WP_CRON', true);
define('WP_HOME', 'http://localhost:8099');
define('WP_SITEURL', WP_HOME);
define('WP_CONTENT_DIR', $root . '/.test-runtime/content');
define('WP_CONTENT_URL', WP_HOME . '/wp-content');
foreach (array('', '/plugins', '/themes', '/uploads') as $dir) if (!is_dir(WP_CONTENT_DIR.$dir)) mkdir(WP_CONTENT_DIR.$dir, 0777, true);
$_SERVER['HTTP_HOST'] = 'localhost:8099';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$table_prefix = 'cpt_';
require ABSPATH . 'wp-settings.php';
add_filter('pre_wp_mail', '__return_true');
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if (!get_option('siteurl')) wp_install('CoachPro tests', 'testadmin', 'test@example.test', true, '', 'Integration-tests-only!');
require $root . '/coachpro-ai-assistant.php';
CoachPro_Auth::register_roles();
