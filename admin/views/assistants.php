<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

global $wpdb;
$t          = CoachPro_DB::table( 'assistants' );
$assistants = $wpdb->get_results( "SELECT * FROM `{$t}` WHERE is_prebuilt = 1 ORDER BY created_at ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
?>
<div class="wrap">
    <h1><?php esc_html_e( 'CoachPro AI — Prebuilt Assistants', 'coachpro-ai' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Manage prebuilt assistants available to all users.', 'coachpro-ai' ); ?></p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Icon', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Name', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Category', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Description', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Active', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'System Prompt (excerpt)', 'coachpro-ai' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( (array) $assistants as $a ) : ?>
            <tr>
                <td><?php echo esc_html( $a['icon'] ); ?></td>
                <td><strong><?php echo esc_html( $a['name'] ); ?></strong></td>
                <td><?php echo esc_html( $a['category'] ); ?></td>
                <td><?php echo esc_html( $a['description'] ); ?></td>
                <td><?php echo $a['is_active'] ? '✅' : '❌'; ?></td>
                <td><em><?php echo esc_html( mb_substr( $a['system_prompt'], 0, 120 ) ); ?>…</em></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2 style="margin-top:30px;"><?php esc_html_e( 'Add / Edit via REST API', 'coachpro-ai' ); ?></h2>
    <p><?php
        printf(
            /* translators: %s: REST URL */
            esc_html__( 'Send POST/PUT to %s to manage prebuilt assistants.', 'coachpro-ai' ),
            '<code>' . esc_html( rest_url( 'coachpro/v1/admin/assistants' ) ) . '</code>'
        );
    ?></p>
</div>
