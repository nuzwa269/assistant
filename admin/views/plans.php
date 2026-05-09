<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'CoachPro AI — Plans & Credit Packs', 'coachpro-ai' ); ?></h1>
    <div id="coachpro-plans-admin"></div>
</div>
