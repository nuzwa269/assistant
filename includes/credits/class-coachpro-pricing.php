<?php
/**
 * Pricing calculator maths (admin only).
 *
 *   API cost of 1,000 tokens (Rs) = (USD per 1M tokens ÷ 1000) × USD rate
 *   Allowed API cost per credit   = cheapest selling price of 1 credit × (1 − profit %)
 *   Credits per 1,000 tokens      = API cost of 1,000 tokens ÷ allowed API cost per credit
 *
 * Using the cheapest plan/pack price guarantees at least the target profit on
 * every plan and pack. The same formulas run in the calculator page (JavaScript).
 *
 * @package CoachPro_AI_Assistant
 */
if ( ! defined( 'ABSPATH' ) ) exit;

final class CoachPro_Pricing {

    const OPTION = 'coachpro_pricing_settings';

    /** Official list prices (USD per 1M tokens) checked November 2026, used to pre-fill models. */
    const KNOWN_API_PRICES = array(
        'claude-haiku-5-5'      => array( 0.10, 0.50 ),
        'gpt-6-luna'            => array( 0.10, 0.50 ),
        'claude-sonnet-5-5'     => array( 2.00, 10.00 ),
        'gpt-6.1-sol'           => array( 2.00, 10.00 ),
        'claude-opus-5-5'       => array( 4.00, 20.00 ),
        'gemini-3.5-flash-lite' => array( 0.30, 2.50 ),
        'gemini-3.8-flash'      => array( 1.50, 7.50 ), // price from 1 Jan 2027
    );

    public static function settings() : array {
        $saved = (array) get_option( self::OPTION, array() );
        return array(
            'usd_rate'      => isset( $saved['usd_rate'] ) ? (float) $saved['usd_rate'] : 280.0,
            'profit_pct'    => isset( $saved['profit_pct'] ) ? (float) $saved['profit_pct'] : 50.0,
            'sample_input'  => isset( $saved['sample_input'] ) ? (int) $saved['sample_input'] : 6000,
            'sample_output' => isset( $saved['sample_output'] ) ? (int) $saved['sample_output'] : 2000,
        );
    }

    public static function save_settings( array $input ) : array {
        $clean = array(
            'usd_rate'      => max( 1, min( 100000, (float) ( $input['usd_rate'] ?? 280 ) ) ),
            'profit_pct'    => max( 0, min( 95, (float) ( $input['profit_pct'] ?? 50 ) ) ),
            'sample_input'  => max( 0, min( 1000000, (int) ( $input['sample_input'] ?? 6000 ) ) ),
            'sample_output' => max( 0, min( 1000000, (int) ( $input['sample_output'] ?? 2000 ) ) ),
        );
        update_option( self::OPTION, $clean, false );
        return $clean;
    }

    /** Every active paid plan and credit pack with its price per credit (Rs). */
    public static function offers() : array {
        global $wpdb;
        $rows = array();
        foreach ( (array) $wpdb->get_results( 'SELECT id, name, price_pkr, monthly_credits AS credits FROM ' . CoachPro_DB::table( 'plans' ) . ' WHERE is_active = 1 ORDER BY sort_order ASC', ARRAY_A ) as $p ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            if ( (int) $p['price_pkr'] > 0 && (int) $p['credits'] > 0 ) $rows[] = array( 'type' => 'Plan (monthly)', 'name' => $p['name'], 'credits' => (int) $p['credits'], 'price' => (int) $p['price_pkr'] );
        }
        foreach ( (array) $wpdb->get_results( 'SELECT id, name, price_pkr, credits FROM ' . CoachPro_DB::table( 'credit_packs' ) . ' WHERE is_active = 1 ORDER BY sort_order ASC', ARRAY_A ) as $p ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            if ( (int) $p['price_pkr'] > 0 && (int) $p['credits'] > 0 ) $rows[] = array( 'type' => 'Credit pack', 'name' => $p['name'], 'credits' => (int) $p['credits'], 'price' => (int) $p['price_pkr'] );
        }
        return $rows;
    }

    /** Lowest Rs a user ever pays for one credit (fallback Rs 1.25). */
    public static function cheapest_credit_price( ?array $offers = null ) : float {
        $min = null;
        foreach ( $offers ?? self::offers() as $o ) {
            $per = $o['price'] / $o['credits'];
            if ( null === $min || $per < $min ) $min = $per;
        }
        return $min ?: 1.25;
    }

    /** Maximum API cost (Rs) that one credit may cover while keeping the target profit. */
    public static function cost_per_credit( ?array $settings = null, ?array $offers = null ) : float {
        $settings = $settings ?? self::settings();
        return self::cheapest_credit_price( $offers ) * ( 1 - $settings['profit_pct'] / 100 );
    }

    /** Recommended credits per 1,000 tokens for a USD-per-1M price (rounded up, 4 decimals). */
    public static function credits_per_1k( float $usd_per_million, ?array $settings = null, ?float $cost_per_credit = null ) : float {
        $settings = $settings ?? self::settings();
        $cpc = $cost_per_credit ?? self::cost_per_credit( $settings );
        if ( $cpc <= 0 || $usd_per_million <= 0 ) return 0.0;
        $rs_per_1k = $usd_per_million / 1000 * $settings['usd_rate'];
        return ceil( $rs_per_1k / $cpc * 10000 ) / 10000;
    }
}
