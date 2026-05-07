<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

global $wpdb;
$t_plans = CoachPro_DB::table( 'plans' );
$t_packs = CoachPro_DB::table( 'credit_packs' );
$plans   = $wpdb->get_results( "SELECT * FROM `{$t_plans}` ORDER BY sort_order ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$packs   = $wpdb->get_results( "SELECT * FROM `{$t_packs}` ORDER BY sort_order ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
?>
<div class="wrap">
    <h1><?php esc_html_e( 'CoachPro AI — Plans & Credit Packs', 'coachpro-ai' ); ?></h1>

    <h2><?php esc_html_e( 'Subscription Plans', 'coachpro-ai' ); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Name', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Price (PKR)', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Monthly Credits', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Max Projects', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Max Assistants', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Max Saved', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Popular', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Active', 'coachpro-ai' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( (array) $plans as $plan ) : ?>
            <tr>
                <td><code><?php echo esc_html( $plan['id'] ); ?></code></td>
                <td><?php echo esc_html( $plan['name'] ); ?></td>
                <td><?php echo esc_html( number_format( (int) $plan['price_pkr'] ) ); ?></td>
                <td><?php echo esc_html( number_format( (int) $plan['monthly_credits'] ) ); ?></td>
                <td><?php echo null === $plan['max_projects'] ? esc_html__( 'Unlimited', 'coachpro-ai' ) : esc_html( $plan['max_projects'] ); ?></td>
                <td><?php echo null === $plan['max_custom_assistants'] ? esc_html__( 'Unlimited', 'coachpro-ai' ) : esc_html( $plan['max_custom_assistants'] ); ?></td>
                <td><?php echo null === $plan['max_saved_responses'] ? esc_html__( 'Unlimited', 'coachpro-ai' ) : esc_html( $plan['max_saved_responses'] ); ?></td>
                <td><?php echo $plan['is_popular'] ? '⭐' : ''; ?></td>
                <td><?php echo $plan['is_active'] ? '✅' : '❌'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2 style="margin-top:30px;"><?php esc_html_e( 'Credit Packs', 'coachpro-ai' ); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Name', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Credits', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Price (PKR)', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Popular', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Active', 'coachpro-ai' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( (array) $packs as $pack ) : ?>
            <tr>
                <td><?php echo esc_html( $pack['name'] ); ?></td>
                <td><?php echo esc_html( number_format( (int) $pack['credits'] ) ); ?></td>
                <td><?php echo esc_html( number_format( (int) $pack['price_pkr'] ) ); ?></td>
                <td><?php echo $pack['is_popular'] ? '⭐' : ''; ?></td>
                <td><?php echo $pack['is_active'] ? '✅' : '❌'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top:20px;"><?php
        printf(
            /* translators: %s: REST URL */
            esc_html__( 'Manage plans/packs via REST: %s', 'coachpro-ai' ),
            '<code>' . esc_html( rest_url( 'coachpro/v1/admin/plans' ) ) . '</code>'
        );
    ?></p>
</div>
