<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'CoachPro AI — Prebuilt Assistants', 'coachpro-ai' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Create, edit, activate, and delete the prebuilt assistants that are available to frontend users.', 'coachpro-ai' ); ?></p>
    <div id="coachpro-prebuilt-assistants-admin" class="coachpro-admin-app"></div>
    <noscript>
        <p><?php esc_html_e( 'JavaScript is required to manage prebuilt assistants from this page.', 'coachpro-ai' ); ?></p>
    </noscript>
</div>
