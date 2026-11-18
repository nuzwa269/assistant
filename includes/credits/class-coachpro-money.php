<?php
/**
 * Exact credit arithmetic. Balances are stored with 6 decimal places and
 * handled internally as integer "micro-credits" (1 credit = 1,000,000 micro)
 * so fractional token charges never suffer from float rounding.
 *
 * @package CoachPro_AI_Assistant
 */
if ( ! defined( 'ABSPATH' ) ) exit;

final class CoachPro_Money {
    const MICRO = 1000000;
    const LIMIT = 9000000000000000;

    /** Parse "12", "12.5", "12.345678" (or a numeric value) into micro-credits. */
    public static function to_micro( $value ) : int {
        if ( is_int( $value ) ) return $value * self::MICRO;
        $text = trim( (string) $value );
        if ( '' === $text ) return 0;
        if ( preg_match( '/^(-)?(\d{1,12})(?:\.(\d+))?$/D', $text, $m ) ) {
            $fraction = substr( str_pad( $m[3] ?? '', 7, '0' ), 0, 7 );
            $micro    = (int) $m[2] * self::MICRO + intdiv( (int) $fraction + 5, 10 ); // round half up at 6 dp
            return '-' === $m[1] ? -$micro : $micro;
        }
        if ( is_numeric( $text ) ) return (int) round( (float) $text * self::MICRO );
        throw new InvalidArgumentException( 'Enter a credit amount such as 10 or 2.5.' );
    }

    /** Micro-credits to a fixed 6-decimal string, e.g. 1500000 => "1.500000". */
    public static function format( int $micro ) : string {
        $sign  = $micro < 0 ? '-' : '';
        $micro = abs( $micro );
        return $sign . intdiv( $micro, self::MICRO ) . '.' . str_pad( (string) ( $micro % self::MICRO ), 6, '0', STR_PAD_LEFT );
    }

    /**
     * Credits charged for a reply: tokens × (credits per 1,000 tokens), rounded
     * up to the nearest micro-credit.
     */
    public static function token_cost( int $input_tokens, int $output_tokens, int $input_rate_micro, int $output_rate_micro ) : int {
        $input_tokens  = max( 0, min( 10000000, $input_tokens ) );
        $output_tokens = max( 0, min( 10000000, $output_tokens ) );
        $total = $input_tokens * max( 0, $input_rate_micro ) + $output_tokens * max( 0, $output_rate_micro );
        return intdiv( $total + 999, 1000 );
    }
}
