<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

global $wpdb;
$t_msg = CoachPro_DB::table( 'messages' );
$t_pay = CoachPro_DB::table( 'payments' );
$t_tx  = CoachPro_DB::table( 'transactions' );

$total_users    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->users}`" );
$today          = current_time( 'Y-m-d' );
$messages_today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t_msg}` WHERE DATE(created_at) = %s", $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$pending_pays   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$t_pay}` WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$total_credits  = (int) $wpdb->get_var( "SELECT SUM(amount) FROM `{$t_tx}` WHERE amount > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
?>
<div class="wrap">
    <h1><?php esc_html_e( 'CoachPro AI — Dashboard', 'coachpro-ai' ); ?></h1>
    <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:20px;">
        <div class="postbox" style="min-width:160px;padding:20px;text-align:center;">
            <h2 style="font-size:2em;margin:0;"><?php echo esc_html( $total_users ); ?></h2>
            <p><?php esc_html_e( 'Total Users', 'coachpro-ai' ); ?></p>
        </div>
        <div class="postbox" style="min-width:160px;padding:20px;text-align:center;">
            <h2 style="font-size:2em;margin:0;"><?php echo esc_html( $messages_today ); ?></h2>
            <p><?php esc_html_e( 'Messages Today', 'coachpro-ai' ); ?></p>
        </div>
        <div class="postbox" style="min-width:160px;padding:20px;text-align:center;">
            <h2 style="font-size:2em;margin:0;"><?php echo esc_html( $pending_pays ); ?></h2>
            <p><?php esc_html_e( 'Pending Payments', 'coachpro-ai' ); ?></p>
        </div>
        <div class="postbox" style="min-width:160px;padding:20px;text-align:center;">
            <h2 style="font-size:2em;margin:0;"><?php echo esc_html( number_format( $total_credits ) ); ?></h2>
            <p><?php esc_html_e( 'Total Credits Issued', 'coachpro-ai' ); ?></p>
        </div>
    </div>

    <h2><?php esc_html_e( 'Quick Links', 'coachpro-ai' ); ?></h2>
    <p>
        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=coachpro-payments' ) ); ?>"><?php esc_html_e( 'Review Payments', 'coachpro-ai' ); ?></a>
        &nbsp;
        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=coachpro-users' ) ); ?>"><?php esc_html_e( 'Manage Users', 'coachpro-ai' ); ?></a>
        &nbsp;
        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=coachpro-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'coachpro-ai' ); ?></a>
    </p>
</div>
