<?php
/** Credit ledger and plan entitlements. */
if ( ! defined( 'ABSPATH' ) ) exit;
class CoachPro_Credits {
    public static function get_balance( int $user_id ) : int {
        return (int) get_user_meta( $user_id, 'coachpro_credits', true );
    }
    private static function change( int $user_id, int $amount, string $kind, ?string $reference_id, ?string $model_id, ?string $notes, bool $absolute = false ) {
        return CoachPro_DB::transaction( function() use ( $user_id, $amount, $kind, $reference_id, $model_id, $notes, $absolute ) {
            global $wpdb;
            $old = self::get_balance( $user_id );
            $balance = $absolute ? max( 0, $amount ) : $old + $amount;
            if ( $balance < 0 ) return new WP_Error( 'insufficient_credits', 'Insufficient credits.', array( 'status' => 402 ) );
            $delta = $balance - $old;
            if ( 0 === $delta ) return $balance;
            if ( false === update_user_meta( $user_id, 'coachpro_credits', $balance ) ) return CoachPro_DB::write_error();
            $written = $wpdb->insert( CoachPro_DB::table( 'transactions' ), array(
                'id' => wp_generate_uuid4(), 'user_id' => $user_id, 'amount' => $delta,
                'kind' => $kind, 'balance_after' => $balance, 'reference_id' => $reference_id,
                'model_id' => $model_id, 'notes' => $notes,
            ) );
            return false === $written ? CoachPro_DB::write_error() : $balance;
        }, $user_id );
    }
    public static function add( int $user_id, int $amount, string $kind, ?string $reference_id = null, ?string $notes = null ) {
        return self::change( $user_id, max( 0, $amount ), $kind, $reference_id, null, $notes );
    }
    public static function deduct( int $user_id, int $cost, string $message_id, string $model_id ) {
        return self::change( $user_id, -max( 0, $cost ), 'message_deduct', $message_id, $model_id, null );
    }
    public static function set( int $user_id, int $new_balance, string $notes = '' ) {
        return self::change( $user_id, $new_balance, 'admin_adjust', null, null, $notes, true );
    }
    /** Paid plans renew manually; free allowances are granted once per current 30-day cycle, without backfill. */
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
            if ( 'free' === $plan_id ) {
                $next = (int) get_user_meta( $user_id, 'coachpro_free_credits_next', true );
                if ( $next <= time() ) {
                    $free = CoachPro_DB::get_row( 'plans', 'free' );
                    if ( $free ) {
                        $result = self::add( $user_id, (int) $free['monthly_credits'], 'subscription_grant', null, 'Free plan monthly allowance' );
                        if ( is_wp_error( $result ) ) return $result;
                    }
                    if ( false === update_user_meta( $user_id, 'coachpro_free_credits_next', time() + 30 * DAY_IN_SECONDS ) ) return CoachPro_DB::write_error();
                }
            }
            return CoachPro_DB::get_row( 'plans', $plan_id );
        }, $user_id );
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
}
