<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

global $wpdb;
$t        = CoachPro_DB::table( 'payments' );
$message  = sanitize_text_field( wp_unslash( $_GET['message'] ?? '' ) );
$payments = $wpdb->get_results( "SELECT * FROM `{$t}` ORDER BY created_at DESC LIMIT 200", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
?>
<div class="wrap">
    <h1><?php esc_html_e( 'CoachPro AI — Payments', 'coachpro-ai' ); ?></h1>

    <?php if ( 'approved' === $message ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Payment approved and credits/plan granted.', 'coachpro-ai' ); ?></p></div>
    <?php elseif ( 'rejected' === $message ) : ?>
        <div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Payment rejected.', 'coachpro-ai' ); ?></p></div>
    <?php endif; ?>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Date', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'User', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Kind', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Amount (PKR)', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Method', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Reference', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Proof', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Status', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'coachpro-ai' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( (array) $payments as $p ) :
            $user = get_userdata( (int) $p['user_id'] );
        ?>
            <tr>
                <td><?php echo esc_html( $p['created_at'] ); ?></td>
                <td><?php echo $user ? esc_html( $user->user_login ) : esc_html( $p['user_id'] ); ?></td>
                <td><?php echo esc_html( $p['kind'] ); ?> <?php echo esc_html( $p['plan_id'] ?: $p['pack_id'] ); ?></td>
                <td><?php echo esc_html( number_format( (int) $p['amount_pkr'] ) ); ?></td>
                <td><?php echo esc_html( $p['method'] ); ?></td>
                <td><?php echo esc_html( $p['reference_no'] ); ?></td>
                <td>
                    <?php if ( $p['proof_url'] ) : ?>
                        <a href="<?php echo esc_url( $p['proof_url'] ); ?>" target="_blank"><?php esc_html_e( 'View', 'coachpro-ai' ); ?></a>
                    <?php else : ?>
                        &mdash;
                    <?php endif; ?>
                </td>
                <td><strong><?php echo esc_html( strtoupper( $p['status'] ) ); ?></strong></td>
                <td>
                    <?php if ( 'pending' === $p['status'] ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                            <?php wp_nonce_field( 'coachpro_approve_payment' ); ?>
                            <input type="hidden" name="action" value="coachpro_approve_payment" />
                            <input type="hidden" name="payment_id" value="<?php echo esc_attr( $p['id'] ); ?>" />
                            <button class="button button-primary button-small" type="submit"><?php esc_html_e( 'Approve', 'coachpro-ai' ); ?></button>
                        </form>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                            <?php wp_nonce_field( 'coachpro_reject_payment' ); ?>
                            <input type="hidden" name="action" value="coachpro_reject_payment" />
                            <input type="hidden" name="payment_id" value="<?php echo esc_attr( $p['id'] ); ?>" />
                            <button class="button button-small" type="submit" style="color:#c00;"><?php esc_html_e( 'Reject', 'coachpro-ai' ); ?></button>
                        </form>
                    <?php else : ?>
                        <?php echo esc_html( $p['admin_notes'] ); ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
