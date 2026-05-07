<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

$message = sanitize_text_field( wp_unslash( $_GET['message'] ?? '' ) );

$users = get_users( array( 'number' => 200, 'orderby' => 'registered', 'order' => 'DESC' ) );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'CoachPro AI — Users', 'coachpro-ai' ); ?></h1>

    <?php if ( 'adjusted' === $message ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Credits adjusted successfully.', 'coachpro-ai' ); ?></p></div>
    <?php endif; ?>

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
            $credits = (int) get_user_meta( $user->ID, 'coachpro_credits', true );
        ?>
            <tr>
                <td><?php echo esc_html( $user->ID ); ?></td>
                <td><?php echo esc_html( $user->user_login ); ?></td>
                <td><?php echo esc_html( $user->user_email ); ?></td>
                <td><span class="coachpro-badge-<?php echo esc_attr( $plan ); ?>"><?php echo esc_html( strtoupper( $plan ) ); ?></span></td>
                <td><?php echo esc_html( number_format( $credits ) ); ?></td>
                <td><?php echo esc_html( $user->user_registered ); ?></td>
                <td>
                    <details>
                        <summary><?php esc_html_e( 'Adjust Credits', 'coachpro-ai' ); ?></summary>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px;">
                            <?php wp_nonce_field( 'coachpro_adjust_credits' ); ?>
                            <input type="hidden" name="action" value="coachpro_adjust_credits" />
                            <input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>" />
                            <label><?php esc_html_e( 'New balance:', 'coachpro-ai' ); ?>
                                <input type="number" name="credits" value="<?php echo esc_attr( $credits ); ?>" min="0" style="width:80px;" />
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
