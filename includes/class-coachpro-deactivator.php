<?php
/**
 * Class CoachPro_Deactivator
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Deactivator {

    public static function deactivate() {
        // Unschedule rolling summary cron
        $timestamp = wp_next_scheduled( 'coachpro_summarize' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'coachpro_summarize' );
        }
        wp_clear_scheduled_hook( 'coachpro_summarize' );
    }
}
