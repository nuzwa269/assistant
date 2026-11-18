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
        wp_unschedule_hook('coachpro_maintenance');
        wp_unschedule_hook('coachpro_summarize');
        $timestamp = wp_next_scheduled( 'coachpro_summarize' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'coachpro_summarize' );
        }
        wp_clear_scheduled_hook( 'coachpro_summarize' );
    }
}
