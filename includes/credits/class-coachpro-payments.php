<?php
/** Shared payment review for REST and WordPress admin forms. */
if ( ! defined( 'ABSPATH' ) ) exit;
class CoachPro_Payments {
    public static function review( string $id, string $status, string $notes = '' ) {
        global $wpdb;
        if ( ! CoachPro_REST_API::is_coachpro_admin() ) return new WP_Error( 'forbidden', 'Access denied.', array( 'status' => 403 ) );
        if ( ! in_array( $status, array( 'approved', 'rejected' ), true ) ) return new WP_Error( 'invalid_status', 'Invalid review.', array( 'status' => 400 ) );
        $payment = CoachPro_DB::get_row( 'payments', $id );
        if ( ! $payment ) return new WP_Error( 'not_found', 'Payment not found.', array( 'status' => 404 ) );
        return CoachPro_DB::transaction( function() use ( $id, $status, $notes, $payment ) {
            global $wpdb;
            $table = CoachPro_DB::table( 'payments' );
            $payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s FOR UPDATE", $id ), ARRAY_A );
            if ( ! $payment || 'pending' !== $payment['status'] ) return new WP_Error( 'already_processed', 'Payment already processed.', array( 'status' => 409 ) );
            $user_id = (int) $payment['user_id'];
            if ( 'approved' === $status ) {
                $subscription = 'subscription' === $payment['kind'];
                $item = CoachPro_DB::get_row( $subscription ? 'plans' : 'credit_packs', (string) ( $subscription ? $payment['plan_id'] : $payment['pack_id'] ) );
                if ( ! $item ) return new WP_Error( 'missing_product', 'The purchased plan or pack no longer exists.', array( 'status' => 409 ) );
                $grant = null !== $payment['credits_grant'] ? (int) $payment['credits_grant'] : (int) $item[$subscription ? 'monthly_credits' : 'credits'];
                if ( $subscription ) {
                    $current_plan = get_user_meta( $user_id, 'coachpro_plan', true );
                    $expiry = get_user_meta( $user_id, 'coachpro_plan_renews', true );
                    $base = $current_plan === $payment['plan_id'] && $expiry ? max( time(), (int) strtotime( $expiry . ' UTC' ) ) : time();
                    foreach ( array( 'coachpro_plan' => $payment['plan_id'], 'coachpro_plan_renews' => gmdate( 'Y-m-d H:i:s', $base + 30 * DAY_IN_SECONDS ) ) as $key => $value ) {
                        $result = CoachPro_DB::set_meta( $user_id, $key, $value );
                        if ( is_wp_error( $result ) ) return $result;
                    }
                }
                $result = CoachPro_Credits::add( $user_id, $grant, $subscription ? 'subscription_grant' : 'pack_purchase', $id, 'Payment approved' );
                if ( is_wp_error( $result ) ) return $result;
            }
            $updated = $wpdb->update( $table, array( 'status' => $status, 'reviewed_by' => get_current_user_id(), 'reviewed_at' => current_time('mysql'), 'admin_notes' => $notes ), array( 'id' => $id, 'status' => 'pending' ) );
            return 1 === $updated ? array( $status => true ) : CoachPro_DB::write_error();
        }, (int) $payment['user_id'] );
    }
}
