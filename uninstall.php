<?php
/** Retain data by default; permanent cleanup is an explicit site setting. */
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
wp_unschedule_hook('coachpro_summarize');
wp_unschedule_hook('coachpro_maintenance');
if (!get_option('coachpro_delete_data_on_uninstall', 0)) return;
global $wpdb;
$tables = array('ai_models','assistants','user_active_assistants','projects','conversations','messages','conv_summaries','saved_responses','plans','credit_packs','payments','transactions','chat_requests');
foreach ($tables as $table) $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}coachpro_{$table}`");
$pages = get_posts(array('post_type'=>'page','post_status'=>'any','numberposts'=>-1,'meta_key'=>'_coachpro_created_page','meta_value'=>'1','fields'=>'ids'));
foreach ($pages as $page) wp_delete_post($page, true);
$prefix = $wpdb->esc_like('coachpro_') . '%';
foreach ($wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix)) as $option) delete_option($option);
$users = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $prefix));
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $prefix));
foreach ($users as $id) {
    $user = new WP_User($id);
    $user->remove_role('coachpro_user');
    $user->remove_role('coachpro_admin');
    wp_cache_delete($id, 'user_meta');
}
$admin = get_role('administrator');
if ($admin) $admin->remove_cap('coachpro_admin');
remove_role('coachpro_user');
remove_role('coachpro_admin');
