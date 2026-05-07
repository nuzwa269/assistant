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
        <table class="form-table">
            <tr>
                <th><label for="coachpro_openai_key"><?php esc_html_e( 'OpenAI API Key', 'coachpro-ai' ); ?></label></th>
                <td><input type="password" id="coachpro_openai_key" name="coachpro_openai_key" value="<?php echo esc_attr( get_option( 'coachpro_openai_key' ) ); ?>" class="regular-text" autocomplete="off" /></td>
            </tr>
            <tr>
                <th><label for="coachpro_anthropic_key"><?php esc_html_e( 'Anthropic API Key', 'coachpro-ai' ); ?></label></th>
                <td><input type="password" id="coachpro_anthropic_key" name="coachpro_anthropic_key" value="<?php echo esc_attr( get_option( 'coachpro_anthropic_key' ) ); ?>" class="regular-text" autocomplete="off" /></td>
            </tr>
            <tr>
                <th><label for="coachpro_openrouter_key"><?php esc_html_e( 'OpenRouter API Key', 'coachpro-ai' ); ?></label></th>
                <td><input type="password" id="coachpro_openrouter_key" name="coachpro_openrouter_key" value="<?php echo esc_attr( get_option( 'coachpro_openrouter_key' ) ); ?>" class="regular-text" autocomplete="off" /></td>
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
</div>
