<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

// Drop all plugin tables
$tables = array(
    'coachpro_ai_models',
    'coachpro_assistants',
    'coachpro_user_active_assistants',
    'coachpro_projects',
    'coachpro_conversations',
    'coachpro_messages',
    'coachpro_conv_summaries',
    'coachpro_saved_responses',
    'coachpro_plans',
    'coachpro_credit_packs',
    'coachpro_payments',
    'coachpro_transactions',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete all plugin options
$options = array(
    'coachpro_openai_key',
    'coachpro_anthropic_key',
    'coachpro_openrouter_key',
    'coachpro_jazzcash_no',
    'coachpro_easypaisa_no',
    'coachpro_bank_details',
    'coachpro_signup_bonus',
    'coachpro_db_version',
);
foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove all user meta with coachpro_ prefix
$wpdb->query( "DELETE FROM `{$wpdb->usermeta}` WHERE meta_key LIKE 'coachpro_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Remove custom roles
remove_role( 'coachpro_user' );
remove_role( 'coachpro_admin' );
