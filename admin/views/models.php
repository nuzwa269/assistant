<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

global $wpdb;
$t      = CoachPro_DB::table( 'ai_models' );
$models = $wpdb->get_results( "SELECT * FROM `{$t}` ORDER BY credits_cost ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
?>
<div class="wrap">
    <h1><?php esc_html_e( 'CoachPro AI — AI Models', 'coachpro-ai' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Manage AI models via the REST API or edit the table below. Use the Admin REST endpoint POST /wp-json/coachpro/v1/admin/models to add models programmatically.', 'coachpro-ai' ); ?></p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Display Name', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Provider', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Type', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Category', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Credits/msg', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Min Plan', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Active', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'API Model Name', 'coachpro-ai' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( (array) $models as $m ) : ?>
            <tr>
                <td><code><?php echo esc_html( $m['id'] ); ?></code></td>
                <td><?php echo esc_html( $m['display_name'] ); ?></td>
                <td><?php echo esc_html( $m['provider'] ); ?></td>
                <td><?php echo esc_html( $m['provider_type'] ); ?></td>
                <td><?php echo esc_html( $m['category'] ); ?></td>
                <td><?php echo esc_html( $m['credits_cost'] ); ?></td>
                <td><?php echo esc_html( strtoupper( $m['min_plan'] ) ); ?></td>
                <td><?php echo $m['is_active'] ? '✅' : '❌'; ?></td>
                <td><code><?php echo esc_html( $m['api_model_name'] ); ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2 style="margin-top:30px;"><?php esc_html_e( 'Add / Update Model (via REST)', 'coachpro-ai' ); ?></h2>
    <p><?php
        printf(
            /* translators: %s: REST URL */
            esc_html__( 'Send a POST request to %s with the model data. Authentication required (admin).', 'coachpro-ai' ),
            '<code>' . esc_html( rest_url( 'coachpro/v1/admin/models' ) ) . '</code>'
        );
    ?></p>
</div>
