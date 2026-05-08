<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'CoachPro AI — Settings', 'coachpro-ai' ); ?></h1>

    <?php settings_errors(); ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'coachpro_settings_group' ); ?>

        <h2><?php esc_html_e( 'AI Provider Keys', 'coachpro-ai' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Provider API keys and model assignments are now managed from the dedicated AI Providers page.', 'coachpro-ai' ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=coachpro-ai-providers' ) ); ?>"><?php esc_html_e( 'Open AI Providers', 'coachpro-ai' ); ?></a>
        </p>
        <table class="form-table">
            <tr>
                <th><label for="coachpro_openrouter_key"><?php esc_html_e( 'OpenRouter API Key', 'coachpro-ai' ); ?></label></th>
                <td><input type="password" id="coachpro_openrouter_key" name="coachpro_openrouter_key" value="<?php echo esc_attr( get_option( 'coachpro_openrouter_key' ) ); ?>" class="regular-text" autocomplete="off" /></td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Google Sign-In (OAuth 2.0)', 'coachpro-ai' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Optional. To enable "Continue with Google" on the login page:', 'coachpro-ai' ); ?>
        </p>
        <ol style="list-style:decimal;padding-left:20px;margin-top:8px;">
            <li><?php esc_html_e( 'Go to Google Cloud Console → APIs & Services → Credentials', 'coachpro-ai' ); ?></li>
            <li><?php esc_html_e( 'Create an OAuth 2.0 Client ID (Web application type)', 'coachpro-ai' ); ?></li>
            <li>
                <?php esc_html_e( 'Add this Authorized redirect URI:', 'coachpro-ai' ); ?>
                <code style="background:#f0f0f1;padding:2px 6px;border-radius:3px;">
                    <?php echo esc_url( rest_url( 'coachpro/v1/auth/google/callback' ) ); ?>
                </code>
            </li>
            <li><?php esc_html_e( 'Paste Client ID and Client Secret below.', 'coachpro-ai' ); ?></li>
        </ol>
        <table class="form-table">
            <tr>
                <th><label for="coachpro_google_client_id"><?php esc_html_e( 'Google Client ID', 'coachpro-ai' ); ?></label></th>
                <td><input type="text" id="coachpro_google_client_id" name="coachpro_google_client_id"
                           value="<?php echo esc_attr( get_option( 'coachpro_google_client_id' ) ); ?>"
                           class="regular-text" placeholder="123456789-xxxx.apps.googleusercontent.com" /></td>
            </tr>
            <tr>
                <th><label for="coachpro_google_client_secret"><?php esc_html_e( 'Google Client Secret', 'coachpro-ai' ); ?></label></th>
                <td><input type="password" id="coachpro_google_client_secret" name="coachpro_google_client_secret"
                           value="<?php echo esc_attr( get_option( 'coachpro_google_client_secret' ) ); ?>"
                           class="regular-text" autocomplete="new-password" /></td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Payment Methods', 'coachpro-ai' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="coachpro_jazzcash_no"><?php esc_html_e( 'JazzCash Number', 'coachpro-ai' ); ?></label></th>
                <td><input type="text" id="coachpro_jazzcash_no" name="coachpro_jazzcash_no" value="<?php echo esc_attr( get_option( 'coachpro_jazzcash_no' ) ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="coachpro_easypaisa_no"><?php esc_html_e( 'EasyPaisa Number', 'coachpro-ai' ); ?></label></th>
                <td><input type="text" id="coachpro_easypaisa_no" name="coachpro_easypaisa_no" value="<?php echo esc_attr( get_option( 'coachpro_easypaisa_no' ) ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="coachpro_bank_details"><?php esc_html_e( 'Bank Account Details', 'coachpro-ai' ); ?></label></th>
                <td><textarea id="coachpro_bank_details" name="coachpro_bank_details" class="large-text" rows="4"><?php echo esc_textarea( get_option( 'coachpro_bank_details' ) ); ?></textarea></td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'General', 'coachpro-ai' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="coachpro_signup_bonus"><?php esc_html_e( 'Signup Bonus Credits', 'coachpro-ai' ); ?></label></th>
                <td>
                    <input type="number" id="coachpro_signup_bonus" name="coachpro_signup_bonus" value="<?php echo esc_attr( get_option( 'coachpro_signup_bonus', 20 ) ); ?>" class="small-text" min="0" />
                    <p class="description"><?php esc_html_e( 'Credits given to each new user on registration.', 'coachpro-ai' ); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>

    <hr>
    <h2>📋 Available Shortcodes & Pages</h2>
    <p>Plugin activate ہونے پر یہ pages خودکار بن جاتے ہیں:</p>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Shortcode</th>
                <th>Page URL</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $shortcode_map = array(
                'coachpro_page_login'        => array( '[coachpro_login]',        'Login Form' ),
                'coachpro_page_register'     => array( '[coachpro_register]',     'Register Form' ),
                'coachpro_page_dashboard'    => array( '[coachpro_dashboard]',    'Main Dashboard' ),
                'coachpro_page_projects'     => array( '[coachpro_projects]',     'Projects List' ),
                'coachpro_page_chat'         => array( '[coachpro_chat]',         'AI Chat Workspace' ),
                'coachpro_page_assistants'   => array( '[coachpro_assistants]',   'Assistants Manager' ),
                'coachpro_page_saved'        => array( '[coachpro_saved]',        'Saved Responses' ),
                'coachpro_page_buy_credits'  => array( '[coachpro_buy_credits]',  'Buy Credits / Plans' ),
                'coachpro_page_transactions' => array( '[coachpro_transactions]', 'Credit History' ),
                'coachpro_page_settings'     => array( '[coachpro_settings]',     'User Settings' ),
                'coachpro_page_help'         => array( '[coachpro_help]',         'Help & Guide' ),
            );
            foreach ( $shortcode_map as $option => $info ) :
                $page_id  = get_option( $option );
                $page_url = $page_id ? get_permalink( $page_id ) : '<em>Not created yet</em>';
            ?>
            <tr>
                <td><code><?php echo esc_html( $info[0] ); ?></code></td>
                <td><?php echo $page_id ? '<a href="' . esc_url( $page_url ) . '" target="_blank">' . esc_url( $page_url ) . '</a>' : '<em>Not created yet</em>'; ?></td>
                <td><?php echo esc_html( $info[1] ); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3 style="margin-top:20px;">🔗 Navigation Flow</h3>
    <p>
        Login → Dashboard → Projects → Chat<br>
        Dashboard → Assistants → Chat<br>
        Dashboard → Buy Credits → Transactions<br>
        Chat → Saved Responses
    </p>
</div>
