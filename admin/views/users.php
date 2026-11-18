<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'coachpro_admin' ) ) wp_die( 'Unauthorized' );

$message = sanitize_text_field( wp_unslash( $_GET['message'] ?? '' ) );
$search  = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );

$args = array( 'number' => 200, 'orderby' => 'registered', 'order' => 'DESC' );
if ( '' !== $search ) {
    $args['search']         = '*' . $search . '*';
    $args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
}
$users = get_users( $args );

global $wpdb;
$plans = (array) $wpdb->get_results( 'SELECT id, name, price_pkr, monthly_credits FROM ' . CoachPro_DB::table( 'plans' ) . ' ORDER BY sort_order ASC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
?>
<div class="wrap">
    <h1><?php esc_html_e( 'CoachPro AI — Users', 'coachpro-ai' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Payments are received personally (JazzCash / EasyPaisa / bank). After you receive a payment, add the credits or assign the plan here.', 'coachpro-ai' ); ?></p>

    <?php if ( 'adjusted' === $message ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Credits updated successfully.', 'coachpro-ai' ); ?></p></div>
    <?php elseif ( 'plan' === $message ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Plan updated successfully.', 'coachpro-ai' ); ?></p></div>
    <?php endif; ?>

    <form method="get" style="margin:12px 0;">
        <input type="hidden" name="page" value="coachpro-users" />
        <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, username or email', 'coachpro-ai' ); ?>" />
        <button class="button"><?php esc_html_e( 'Search', 'coachpro-ai' ); ?></button>
    </form>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Username', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Email', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Plan', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Credits', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Joined', 'coachpro-ai' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'coachpro-ai' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $users as $user ) :
            $plan    = get_user_meta( $user->ID, 'coachpro_plan', true ) ?: 'free';
            $renews  = get_user_meta( $user->ID, 'coachpro_plan_renews', true );
            $credits = CoachPro_Credits::get_balance( $user->ID );
        ?>
            <tr>
                <td><?php echo esc_html( $user->ID ); ?></td>
                <td><?php echo esc_html( $user->user_login ); ?></td>
                <td><?php echo esc_html( $user->user_email ); ?></td>
                <td>
                    <span class="coachpro-badge-<?php echo esc_attr( $plan ); ?>"><?php echo esc_html( strtoupper( $plan ) ); ?></span>
                    <?php if ( $renews ) : ?><br><small><?php echo esc_html( sprintf( __( 'until %s', 'coachpro-ai' ), $renews ) ); ?></small><?php endif; ?>
                </td>
                <td><?php echo esc_html( number_format( $credits, 2, '.', '' ) ); ?></td>
                <td><?php echo esc_html( $user->user_registered ); ?></td>
                <td>
                    <details>
                        <summary><?php esc_html_e( 'Add credits (payment received)', 'coachpro-ai' ); ?></summary>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px;">
                            <?php wp_nonce_field( 'coachpro_adjust_credits' ); ?>
                            <input type="hidden" name="action" value="coachpro_adjust_credits" />
                            <input type="hidden" name="mode" value="add" />
                            <input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>" />
                            <label><?php esc_html_e( 'Credits to add:', 'coachpro-ai' ); ?>
                                <input type="number" name="credits" step="0.000001" min="0" required style="width:100px;" />
                            </label>
                            <label><?php esc_html_e( 'Note:', 'coachpro-ai' ); ?>
                                <input type="text" name="notes" placeholder="<?php esc_attr_e( 'e.g. JazzCash Rs 500, TID 12345', 'coachpro-ai' ); ?>" />
                            </label>
                            <button class="button button-primary button-small" type="submit"><?php esc_html_e( 'Add', 'coachpro-ai' ); ?></button>
                        </form>
                    </details>
                    <details>
                        <summary><?php esc_html_e( 'Assign plan', 'coachpro-ai' ); ?></summary>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px;">
                            <?php wp_nonce_field( 'coachpro_assign_plan' ); ?>
                            <input type="hidden" name="action" value="coachpro_assign_plan" />
                            <input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>" />
                            <select name="plan_id">
                                <?php foreach ( $plans as $p ) : ?>
                                    <option value="<?php echo esc_attr( $p['id'] ); ?>" <?php selected( $plan, $p['id'] ); ?>><?php echo esc_html( $p['name'] . ( (int) $p['price_pkr'] ? ' — Rs ' . $p['price_pkr'] : '' ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label><input type="checkbox" name="grant_credits" value="1" checked /> <?php esc_html_e( "Also add the plan's monthly credits", 'coachpro-ai' ); ?></label>
                            <button class="button button-small" type="submit"><?php esc_html_e( 'Save plan', 'coachpro-ai' ); ?></button>
                            <p class="description"><?php esc_html_e( 'Paid plans run for 30 days (renewing the same plan extends from the current end date).', 'coachpro-ai' ); ?></p>
                        </form>
                    </details>
                    <details>
                        <summary><?php esc_html_e( 'Credit history (admin only)', 'coachpro-ai' ); ?></summary>
                        <?php
                        $history = $wpdb->get_results( $wpdb->prepare( 'SELECT created_at, kind, amount, balance_after, notes FROM ' . CoachPro_DB::table( 'transactions' ) . ' WHERE user_id = %d ORDER BY created_at DESC LIMIT 25', $user->ID ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                        if ( ! $history ) : ?>
                            <p><?php esc_html_e( 'No credit changes yet.', 'coachpro-ai' ); ?></p>
                        <?php else : ?>
                            <table class="widefat" style="margin-top:6px;font-size:12px;">
                                <thead><tr><th><?php esc_html_e( 'Date', 'coachpro-ai' ); ?></th><th><?php esc_html_e( 'Type', 'coachpro-ai' ); ?></th><th><?php esc_html_e( 'Credits', 'coachpro-ai' ); ?></th><th><?php esc_html_e( 'Balance', 'coachpro-ai' ); ?></th><th><?php esc_html_e( 'Details (model, tokens)', 'coachpro-ai' ); ?></th></tr></thead>
                                <tbody>
                                <?php foreach ( $history as $h ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( substr( $h['created_at'], 0, 16 ) ); ?></td>
                                        <td><?php echo esc_html( $h['kind'] ); ?></td>
                                        <td><?php echo esc_html( (string) ( 0 + $h['amount'] ) ); ?></td>
                                        <td><?php echo esc_html( (string) ( 0 + $h['balance_after'] ) ); ?></td>
                                        <td><?php echo esc_html( (string) $h['notes'] ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </details>
                    <details>
                        <summary><?php esc_html_e( 'Set exact balance', 'coachpro-ai' ); ?></summary>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px;">
                            <?php wp_nonce_field( 'coachpro_adjust_credits' ); ?>
                            <input type="hidden" name="action" value="coachpro_adjust_credits" />
                            <input type="hidden" name="mode" value="set" />
                            <input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>" />
                            <label><?php esc_html_e( 'New balance:', 'coachpro-ai' ); ?>
                                <input type="number" name="credits" step="0.000001" value="<?php echo esc_attr( $credits ); ?>" min="0" style="width:100px;" />
                            </label>
                            <label><?php esc_html_e( 'Notes:', 'coachpro-ai' ); ?>
                                <input type="text" name="notes" placeholder="<?php esc_attr_e( 'Reason...', 'coachpro-ai' ); ?>" />
                            </label>
                            <button class="button button-small" type="submit"><?php esc_html_e( 'Save', 'coachpro-ai' ); ?></button>
                        </form>
                    </details>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
