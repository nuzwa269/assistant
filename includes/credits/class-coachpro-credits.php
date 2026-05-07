<?php
/**
 * Class CoachPro_Credits
 * Atomic credits management with transaction logging.
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Credits {

    /**
     * Get current credit balance for a user.
     */
    public static function get_balance( int $user_id ) : int {
        return (int) get_user_meta( $user_id, 'coachpro_credits', true );
    }

    /**
     * Add credits to a user's balance and log a transaction.
     *
     * @param int         $user_id
     * @param int         $amount       Positive integer.
     * @param string      $kind         Transaction kind.
     * @param string|null $reference_id Related payment / pack ID.
     * @param string|null $notes
     * @return int New balance.
     */
    public static function add( int $user_id, int $amount, string $kind, ?string $reference_id = null, ?string $notes = null ) : int {
        global $wpdb;

        // Lock via DB transaction for atomicity
        $wpdb->query( 'START TRANSACTION' );

        $current = self::get_balance( $user_id );
        $new_balance = $current + abs( $amount );
        update_user_meta( $user_id, 'coachpro_credits', $new_balance );
        self::log_transaction( $user_id, abs( $amount ), $kind, $new_balance, $reference_id, null, $notes );

        $wpdb->query( 'COMMIT' );

        return $new_balance;
    }

    /**
     * Deduct credits. Returns false if insufficient balance.
     *
     * @param int    $user_id
     * @param int    $cost
     * @param string $message_id UUID of the message.
     * @param string $model_id
     * @return bool
     */
    public static function deduct( int $user_id, int $cost, string $message_id, string $model_id ) : bool {
        global $wpdb;

        $wpdb->query( 'START TRANSACTION' );

        $current = self::get_balance( $user_id );
        if ( $current < $cost ) {
            $wpdb->query( 'ROLLBACK' );
            return false;
        }

        $new_balance = $current - $cost;
        update_user_meta( $user_id, 'coachpro_credits', $new_balance );
        self::log_transaction( $user_id, -$cost, 'message_deduct', $new_balance, $message_id, $model_id, null );

        $wpdb->query( 'COMMIT' );

        return true;
    }

    /**
     * Directly set credits (admin adjust).
     */
    public static function set( int $user_id, int $new_balance, string $notes = '' ) : void {
        $old = self::get_balance( $user_id );
        $diff = $new_balance - $old;
        update_user_meta( $user_id, 'coachpro_credits', $new_balance );
        self::log_transaction( $user_id, $diff, 'admin_adjust', $new_balance, null, null, $notes );
    }

    /**
     * Log a transaction to the transactions table.
     */
    public static function log_transaction( int $user_id, int $amount, string $kind, int $balance_after, ?string $reference_id, ?string $model_id, ?string $notes ) : void {
        global $wpdb;
        $wpdb->insert(
            CoachPro_DB::table( 'transactions' ),
            array(
                'id'           => wp_generate_uuid4(),
                'user_id'      => $user_id,
                'amount'       => $amount,
                'kind'         => $kind,
                'balance_after'=> $balance_after,
                'reference_id' => $reference_id,
                'model_id'     => $model_id,
                'notes'        => $notes,
            ),
            array( '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s' )
        );
    }

    // -------------------------------------------------------------------------
    // Plan limit checks
    // -------------------------------------------------------------------------

    /**
     * Check if user can create more projects.
     */
    public static function can_create_project( int $user_id ) : bool {
        $plan = get_user_meta( $user_id, 'coachpro_plan', true ) ?: 'free';
        if ( 'free' !== $plan ) return true;
        $count = CoachPro_DB::count( 'projects', array( 'user_id' => $user_id ) );
        return $count < 3;
    }

    /**
     * Check if user can create more custom assistants.
     */
    public static function can_create_assistant( int $user_id ) : bool {
        $plan = get_user_meta( $user_id, 'coachpro_plan', true ) ?: 'free';
        if ( 'free' !== $plan ) return true;
        $count = CoachPro_DB::count( 'assistants', array( 'owner_id' => $user_id, 'is_prebuilt' => 0 ) );
        return $count < 1;
    }

    /**
     * Check if user can save more responses.
     */
    public static function can_save_response( int $user_id ) : bool {
        $plan = get_user_meta( $user_id, 'coachpro_plan', true ) ?: 'free';
        if ( 'free' !== $plan ) return true;
        $count = CoachPro_DB::count( 'saved_responses', array( 'user_id' => $user_id ) );
        return $count < 10;
    }

    /**
     * Check if user can activate more prebuilt assistants (free: max 1).
     */
    public static function can_activate_prebuilt( int $user_id ) : bool {
        $plan = get_user_meta( $user_id, 'coachpro_plan', true ) ?: 'free';
        if ( 'free' !== $plan ) return true;
        $count = CoachPro_DB::count( 'user_active_assistants', array( 'user_id' => $user_id ) );
        return $count < 1;
    }
}
