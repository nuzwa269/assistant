<?php
/**
 * Class CoachPro_Payments_API
 * Public price lists and manual payment details (display only).
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Payments_API {

    public static function list_plans( WP_REST_Request $request ) {
        global $wpdb;
        $t    = CoachPro_DB::table( 'plans' );
        $rows = $wpdb->get_results( "SELECT * FROM `{$t}` WHERE is_active = 1 ORDER BY sort_order ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        foreach ($rows as &$plan) {
            if ($plan['id']==='free') {
                $plan['monthly_credits']=0;
                $features=json_decode($plan['features'] ?? '[]',true);
                $features=array_values(array_filter((array)$features,function($f){return !preg_match('/credits?\s*\/\s*month|monthly.*credits?/i',(string)$f);}));
                array_unshift($features,'20 credits once on signup; no recurring Free credits');
                $plan['features']=wp_json_encode($features);
            }
        } unset($plan);
        return rest_ensure_response( $rows );
    }

    public static function list_credit_packs( WP_REST_Request $request ) {
        global $wpdb;
        $t    = CoachPro_DB::table( 'credit_packs' );
        $rows = $wpdb->get_results( "SELECT * FROM `{$t}` WHERE is_active = 1 ORDER BY sort_order ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return rest_ensure_response( $rows );
    }

    /**
     * Where and how to pay. Payments are made personally (JazzCash, EasyPaisa or
     * bank transfer); the administrator then adds credits or assigns a plan by hand.
     */
    public static function payment_info( WP_REST_Request $request ) {
        return rest_ensure_response( self::payment_details() );
    }

    public static function payment_details() : array {
        return array(
            'jazzcash'      => (string) get_option( 'coachpro_jazzcash_no', '' ),
            'easypaisa'     => (string) get_option( 'coachpro_easypaisa_no', '' ),
            'bank_transfer' => (string) get_option( 'coachpro_bank_details', '' ),
            'contact'       => (string) get_option( 'coachpro_payment_contact', '' ),
            'instructions'  => (string) get_option( 'coachpro_payment_instructions', '' ),
        );
    }
}
