<?php
/**
 * Class CoachPro_Payments_API
 *
 * @package CoachPro_AI_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CoachPro_Payments_API {

    public static function list_plans( WP_REST_Request $request ) {
        global $wpdb;
        $t    = CoachPro_DB::table( 'plans' );
        $rows = $wpdb->get_results( "SELECT * FROM `{$t}` WHERE is_active = 1 ORDER BY sort_order ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return rest_ensure_response( $rows );
    }

    public static function list_credit_packs( WP_REST_Request $request ) {
        global $wpdb;
        $t    = CoachPro_DB::table( 'credit_packs' );
        $rows = $wpdb->get_results( "SELECT * FROM `{$t}` WHERE is_active = 1 ORDER BY sort_order ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return rest_ensure_response( $rows );
    }

    public static function list_payments( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $rows    = CoachPro_DB::get_rows( 'payments', array( 'user_id' => $user_id ), 'created_at DESC' );
        return rest_ensure_response( $rows );
    }

    public static function create_payment( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $params  = $request->get_json_params();

        $kind   = in_array( $params['kind'] ?? '', array( 'subscription', 'credit_pack' ), true ) ? $params['kind'] : '';
        $method = in_array( $params['method'] ?? '', array( 'jazzcash', 'easypaisa', 'bank_transfer', 'whatsapp' ), true ) ? $params['method'] : '';

        if ( empty( $kind ) || empty( $method ) ) {
            return new WP_Error( 'missing_fields', __( 'kind and method are required.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        $amount_pkr = absint( $params['amount_pkr'] ?? 0 );
        if ( ! $amount_pkr ) {
            return new WP_Error( 'missing_amount', __( 'amount_pkr is required.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        global $wpdb;
        $id = wp_generate_uuid4();
        $wpdb->insert(
            CoachPro_DB::table( 'payments' ),
            array(
                'id'           => $id,
                'user_id'      => $user_id,
                'kind'         => $kind,
                'plan_id'      => sanitize_text_field( $params['plan_id'] ?? '' ) ?: null,
                'pack_id'      => sanitize_text_field( $params['pack_id'] ?? '' ) ?: null,
                'amount_pkr'   => $amount_pkr,
                'method'       => $method,
                'sender_name'  => sanitize_text_field( $params['sender_name'] ?? '' ),
                'sender_phone' => sanitize_text_field( $params['sender_phone'] ?? '' ),
                'reference_no' => sanitize_text_field( $params['reference_no'] ?? '' ),
                'notes'        => sanitize_textarea_field( $params['notes'] ?? '' ),
                'status'       => 'pending',
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        return rest_ensure_response( CoachPro_DB::get_row( 'payments', $id ) );
    }

    public static function upload_proof( WP_REST_Request $request ) {
        $user_id    = get_current_user_id();
        $payment_id = sanitize_text_field( $request->get_param( 'id' ) );
        $payment    = CoachPro_DB::get_row( 'payments', $payment_id );

        if ( ! $payment || (int) $payment['user_id'] !== $user_id ) {
            return new WP_Error( 'not_found', __( 'Payment not found.', 'coachpro-ai' ), array( 'status' => 404 ) );
        }

        if ( empty( $_FILES['proof'] ) ) {
            return new WP_Error( 'missing_file', __( 'No file uploaded.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $file     = $_FILES['proof']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $uploaded = wp_handle_upload( $file, array( 'test_form' => false ) );

        if ( isset( $uploaded['error'] ) ) {
            return new WP_Error( 'upload_failed', $uploaded['error'], array( 'status' => 500 ) );
        }

        global $wpdb;
        $wpdb->update(
            CoachPro_DB::table( 'payments' ),
            array( 'proof_url' => esc_url_raw( $uploaded['url'] ) ),
            array( 'id' => $payment_id ),
            array( '%s' ),
            array( '%s' )
        );

        return rest_ensure_response( array( 'proof_url' => $uploaded['url'] ) );
    }
}
