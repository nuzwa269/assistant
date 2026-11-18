<?php
/**
 * Credit balance, credit history and plan entitlements.
 *
 * The balance lives in the user meta "coachpro_credits" (6 decimal places).
 * Every change is written together with a row in the transactions table
 * inside one database transaction, so the history always matches the balance.
 *
 * @package CoachPro_AI_Assistant
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Credits {

    public static function get_balance( int $user_id ) : float {
        return self::get_balance_micro( $user_id ) / CoachPro_Money::MICRO;
    }

    public static function get_balance_micro( int $user_id ) : int {
        try { return max( 0, CoachPro_Money::to_micro( (string) get_user_meta( $user_id, 'coachpro_credits', true ) ) ); }
        catch ( Throwable $e ) { return 0; }
    }

    /**
     * Apply a change in micro-credits.
     *
     * @param bool $absolute      Treat $micro as the new balance instead of a delta.
     * @param bool $allow_partial For usage charges: if the cost is larger than the
     *                            balance, take what is left (balance becomes 0)
     *                            instead of failing after the AI already answered.
     * @return array|WP_Error     array( 'balance' => micro, 'amount' => micro actually applied )
     */
    public static function change_micro( int $user_id, int $micro, string $kind, ?string $reference_id = null, ?string $model_id = null, ?string $notes = null, bool $absolute = false, bool $allow_partial = false ) {
        return CoachPro_DB::transaction( function() use ( $user_id, $micro, $kind, $reference_id, $model_id, $notes, $absolute, $allow_partial ) {
            global $wpdb;
            $old   = self::get_balance_micro( $user_id );
            $delta = $absolute ? max( 0, $micro ) - $old : $micro;
            if ( $old + $delta < 0 ) {
                if ( ! $allow_partial ) return new WP_Error( 'insufficient_credits', 'Insufficient credits.', array( 'status' => 402 ) );
                $notes = trim( (string) $notes . ' (cost ' . CoachPro_Money::format( -$delta ) . ', charged remaining balance)' );
                $delta = -$old;
            }
            $new = $old + $delta;
            if ( $new > CoachPro_Money::LIMIT ) return new WP_Error( 'invalid_amount', 'Credit amount is too large.', array( 'status' => 400 ) );
            if ( 0 === $delta ) return array( 'balance' => $old, 'amount' => 0 );

            $saved = CoachPro_DB::set_meta( $user_id, 'coachpro_credits', CoachPro_Money::format( $new ) );
            if ( is_wp_error( $saved ) ) return $saved;
            $written = $wpdb->insert( CoachPro_DB::table( 'transactions' ), array(
                'id'            => wp_generate_uuid4(),
                'user_id'       => $user_id,
                'amount'        => CoachPro_Money::format( $delta ),
                'kind'          => $kind,
                'balance_after' => CoachPro_Money::format( $new ),
                'reference_id'  => $reference_id,
                'model_id'      => $model_id,
                'notes'         => $notes,
            ) );
            if ( false === $written ) return CoachPro_DB::write_error();
            return array( 'balance' => $new, 'amount' => $delta );
        }, $user_id );
    }

    /** Add whole or fractional credits (signup bonus, plan grant, pack, refund). */
    public static function add( int $user_id, $amount, string $kind, ?string $reference_id = null, ?string $notes = null ) {
        try { $micro = max( 0, CoachPro_Money::to_micro( $amount ) ); }
        catch ( Throwable $e ) { return new WP_Error( 'invalid_amount', $e->getMessage(), array( 'status' => 400 ) ); }
        $result = self::change_micro( $user_id, $micro, $kind, $reference_id, null, $notes );
        return is_wp_error( $result ) ? $result : $result['balance'] / CoachPro_Money::MICRO;
    }

    /** Charge AI usage. Never fails for lack of balance: it stops at zero. */
    public static function charge_usage( int $user_id, int $cost_micro, string $message_id, string $model_id, string $notes ) {
        return self::change_micro( $user_id, -max( 0, $cost_micro ), 'message_deduct', $message_id, $model_id, $notes, false, true );
    }

    /** Administrator sets an exact balance. */
    public static function set( int $user_id, $new_balance, string $notes = '' ) {
        try { $micro = CoachPro_Money::to_micro( $new_balance ); }
        catch ( Throwable $e ) { return new WP_Error( 'invalid_balance', $e->getMessage(), array( 'status' => 400 ) ); }
        if ( $micro < 0 ) return new WP_Error( 'invalid_balance', 'Balance cannot be negative.', array( 'status' => 400 ) );
        $result = self::change_micro( $user_id, $micro, 'admin_adjust', null, null, $notes ?: 'Administrator set the balance', true );
        return is_wp_error( $result ) ? $result : $result['balance'] / CoachPro_Money::MICRO;
    }

    /** Paid plans renew manually; membership expiry never grants free credits. */
    public static function refresh_plan( int $user_id ) {
        return CoachPro_DB::transaction( function() use ( $user_id ) {
            $plan_id = get_user_meta( $user_id, 'coachpro_plan', true ) ?: 'free';
            $expiry = get_user_meta( $user_id, 'coachpro_plan_renews', true );
            $plan = CoachPro_DB::get_row( 'plans', $plan_id );
            if ( 'free' !== $plan_id && ( ! $plan || ! $expiry || strtotime( $expiry . ' UTC' ) <= time() ) ) {
                update_user_meta( $user_id, 'coachpro_plan', 'free' );
                update_user_meta( $user_id, 'coachpro_plan_renews', '' );
                $plan_id = 'free';
            }
            return CoachPro_DB::get_row( 'plans', $plan_id );
        }, $user_id );
    }

    /**
     * Administrator assigns a plan after receiving a manual payment.
     * Paid plans run for 30 days (extended from the current expiry when renewing
     * the same plan). Optionally adds the plan's monthly credits.
     */
    public static function assign_plan( int $user_id, string $plan_id, bool $grant_credits, string $notes = '' ) {
        $plan = CoachPro_DB::get_row( 'plans', $plan_id );
        if ( ! $plan ) return new WP_Error( 'invalid_plan', 'Plan not found.', array( 'status' => 400 ) );
        return CoachPro_DB::transaction( function() use ( $user_id, $plan, $grant_credits, $notes ) {
            $renews = '';
            if ( 'free' !== $plan['id'] ) {
                $current = get_user_meta( $user_id, 'coachpro_plan', true );
                $expiry  = get_user_meta( $user_id, 'coachpro_plan_renews', true );
                $base    = $current === $plan['id'] && $expiry ? max( time(), (int) strtotime( $expiry . ' UTC' ) ) : time();
                $renews  = gmdate( 'Y-m-d H:i:s', $base + 30 * DAY_IN_SECONDS );
            }
            foreach ( array( 'coachpro_plan' => $plan['id'], 'coachpro_plan_renews' => $renews ) as $key => $value ) {
                $result = CoachPro_DB::set_meta( $user_id, $key, $value );
                if ( is_wp_error( $result ) ) return $result;
            }
            if ( $grant_credits && 'free' !== $plan['id'] && (int) $plan['monthly_credits'] > 0 ) {
                $result = self::change_micro( $user_id, (int) $plan['monthly_credits'] * CoachPro_Money::MICRO, 'subscription_grant', $plan['id'], null, $notes ?: ( $plan['name'] . ' plan credits (manual payment)' ) );
                if ( is_wp_error( $result ) ) return $result;
            }
            return true;
        }, $user_id );
    }

    /** One grant per genuinely new account; old wallets/ledger entries are never re-granted. */
    public static function signup_once( int $user_id ) {
        return CoachPro_DB::transaction( function() use ($user_id) {
            global $wpdb;
            if (get_user_meta($user_id, 'coachpro_signup_awarded', true)) return true;
            $old = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . CoachPro_DB::table('transactions') . ' WHERE user_id = %d LIMIT 1', $user_id));
            if ($old || metadata_exists('user', $user_id, 'coachpro_credits')) {
                return CoachPro_DB::set_meta($user_id, 'coachpro_signup_awarded', 'protected-existing');
            }
            foreach (array('coachpro_plan'=>'free', 'coachpro_plan_renews'=>'') as $key=>$value) {
                if (!metadata_exists('user', $user_id, $key)) {
                    $result=CoachPro_DB::set_meta($user_id,$key,$value);
                    if (is_wp_error($result)) return $result;
                }
            }
            $result=self::add($user_id,20,'signup_bonus',null,'One-time welcome: 20 credits');
            if (is_wp_error($result)) return $result;
            return CoachPro_DB::set_meta($user_id, 'coachpro_signup_awarded', '20-v1');
        },$user_id);
    }
    private static function within_limit( int $user_id, string $field, string $table, array $where ) : bool {
        $plan = self::refresh_plan( $user_id );
        if ( is_wp_error( $plan ) || ! $plan ) return false;
        return null === $plan[$field] || CoachPro_DB::count( $table, $where ) < (int) $plan[$field];
    }
    public static function can_create_project( int $id ) : bool {
        return self::within_limit( $id, 'max_projects', 'projects', array( 'user_id' => $id ) );
    }
    public static function can_create_assistant( int $id ) : bool {
        return self::within_limit( $id, 'max_custom_assistants', 'assistants', array( 'owner_id' => $id, 'is_prebuilt' => 0 ) );
    }
    public static function can_save_response( int $id ) : bool {
        return self::within_limit( $id, 'max_saved_responses', 'saved_responses', array( 'user_id' => $id ) );
    }
    /** Effective activations only; retain excess rows so upgrades restore access. */
    public static function active_prebuilt_ids( int $user_id ) : array {
        global $wpdb;
        $plan = self::refresh_plan( $user_id );
        if ( is_wp_error( $plan ) || ! $plan ) return array();
        $limit = $plan['max_active_assistants'];
        if ( null !== $limit && (int) $limit <= 0 ) return array();
        $sql = $wpdb->prepare(
            'SELECT ua.assistant_id FROM ' . CoachPro_DB::table('user_active_assistants') . ' ua INNER JOIN ' . CoachPro_DB::table('assistants') . ' a ON a.id = ua.assistant_id WHERE ua.user_id = %d AND a.is_prebuilt = 1 AND a.is_active = 1 ORDER BY ua.activated_at ASC, ua.id ASC',
            $user_id
        );
        if ( null !== $limit ) $sql .= $wpdb->prepare( ' LIMIT %d', max( 0, (int) $limit ) );
        return (array) $wpdb->get_col( $sql );
    }
    public static function can_activate_prebuilt( int $id ) : bool {
        global $wpdb;
        $plan = self::refresh_plan( $id );
        if ( is_wp_error( $plan ) || ! $plan ) return false;
        if ( null === $plan['max_active_assistants'] ) return true;
        $count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . CoachPro_DB::table('user_active_assistants') . ' ua INNER JOIN ' . CoachPro_DB::table('assistants') . ' a ON a.id = ua.assistant_id WHERE ua.user_id = %d AND a.is_prebuilt = 1', $id ) );
        return (int) $count < (int) $plan['max_active_assistants'];
    }
    public static function can_use_model( int $user_id, array $model ) : bool {
        $plan = self::refresh_plan( $user_id );
        if ( is_wp_error( $plan ) || ! $plan ) return false;
        $ranks = array( 'free' => 0, 'basic' => 1, 'pro' => 2 );
        return (int) $plan['model_access_level'] >= ( $ranks[$model['min_plan']] ?? 2 );
    }

    /** Model token prices in micro-credits per 1,000 tokens. */
    public static function model_rates( array $model ) : array {
        try {
            return array(
                'input'  => max( 0, CoachPro_Money::to_micro( (string) ( $model['input_credits_per_1k'] ?? '0' ) ) ),
                'output' => max( 0, CoachPro_Money::to_micro( (string) ( $model['output_credits_per_1k'] ?? '0' ) ) ),
            );
        } catch ( Throwable $e ) {
            return array( 'input' => 0, 'output' => 0 );
        }
    }
}
