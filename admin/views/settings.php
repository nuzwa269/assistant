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
                <td><input type="password" id="coachpro_openrouter_key" name="coachpro_openrouter_key" value="<?php echo esc_attr( get_option( 'coachpro_openrouter_key' ) ); ?>" class="regular-text" autocomplete="new-password" /></td>
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

        <h2><?php esc_html_e( 'Page Assignments', 'coachpro-ai' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Assign WordPress pages to each CoachPro view. Make sure each page contains the correct shortcode.', 'coachpro-ai' ); ?>
        </p>
        <table class="form-table">
            <?php
            $page_assignments = array(
                'coachpro_page_login'        => array( 'label' => __( 'Login Page', 'coachpro-ai' ), 'shortcode' => '[coachpro_login]' ),
                'coachpro_page_register'     => array( 'label' => __( 'Register Page', 'coachpro-ai' ), 'shortcode' => '[coachpro_register]' ),
                'coachpro_page_dashboard'    => array( 'label' => __( 'Dashboard Page', 'coachpro-ai' ), 'shortcode' => '[coachpro_dashboard]' ),
                'coachpro_page_chat'         => array( 'label' => __( 'Chat Page', 'coachpro-ai' ), 'shortcode' => '[coachpro_chat]' ),
                'coachpro_page_projects'     => array( 'label' => __( 'Projects Page', 'coachpro-ai' ), 'shortcode' => '[coachpro_projects]' ),
                'coachpro_page_assistants'   => array( 'label' => __( 'Assistants Page', 'coachpro-ai' ), 'shortcode' => '[coachpro_assistants]' ),
                'coachpro_page_saved'        => array( 'label' => __( 'Saved Page', 'coachpro-ai' ), 'shortcode' => '[coachpro_saved]' ),
                'coachpro_page_buy_credits'  => array( 'label' => __( 'Buy Credits Page', 'coachpro-ai' ), 'shortcode' => '[coachpro_buy_credits]' ),
                'coachpro_page_settings'     => array( 'label' => __( 'Settings Page', 'coachpro-ai' ), 'shortcode' => '[coachpro_settings]' ),
                'coachpro_page_transactions' => array( 'label' => __( 'Transactions Page', 'coachpro-ai' ), 'shortcode' => '[coachpro_transactions]' ),
                'coachpro_page_help'         => array( 'label' => __( 'Help Page', 'coachpro-ai' ), 'shortcode' => '[coachpro_help]' ),
            );
            foreach ( $page_assignments as $option_key => $assignment ) :
                ?>
                <tr>
                    <th><label for="<?php echo esc_attr( $option_key ); ?>"><?php echo esc_html( $assignment['label'] ); ?></label></th>
                    <td>
                        <?php
                        wp_dropdown_pages(
                            array(
                                'name'              => $option_key,
                                'id'                => $option_key,
                                'selected'          => absint( get_option( $option_key, 0 ) ),
                                'show_option_none'  => __( '— Select a page —', 'coachpro-ai' ),
                                'option_none_value' => 0,
                            )
                        );
                        ?>
                        <p class="description">
                            <?php
                            /* translators: %s: shortcode name. */
                            printf( esc_html__( 'Page with %s shortcode.', 'coachpro-ai' ), esc_html( $assignment['shortcode'] ) );
                            ?>
                        </p>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
