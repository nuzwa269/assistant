<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'CoachPro AI — AI Providers', 'coachpro-ai' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Manage provider API keys, test connections, and configure the models used by assistants.', 'coachpro-ai' ); ?></p>
    <div id="coachpro-ai-providers-admin" class="coachpro-admin-app"></div>
    <noscript>
        <p><?php esc_html_e( 'JavaScript is required to manage AI providers and models from this page.', 'coachpro-ai' ); ?></p>
    </noscript>
</div>
