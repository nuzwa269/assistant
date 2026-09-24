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
        list($limit, $offset) = CoachPro_DB::pagination($request);
        $user_id = get_current_user_id();
        $rows    = CoachPro_DB::get_rows( 'payments', array( 'user_id' => $user_id ), 'created_at DESC', $limit, $offset );
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

        $plan_id = 'subscription' === $kind ? sanitize_text_field($params['plan_id'] ?? '') : null;
        $pack_id = 'credit_pack' === $kind ? sanitize_text_field($params['pack_id'] ?? '') : null;
        $item = CoachPro_DB::get_row('subscription' === $kind ? 'plans' : 'credit_packs', (string)($plan_id ?: $pack_id));
        if ( ! $item || ! $item['is_active'] || (int)$item['price_pkr'] <= 0 ) return new WP_Error('invalid_product', 'Choose an active paid plan or credit pack.', array('status'=>400));
        if (empty($params['reference_no']) || empty($params['sender_name'])) return new WP_Error('missing_reference', 'Sender name and payment reference are required.', array('status'=>400));
        $amount_pkr = (int)$item['price_pkr'];
        $credits_grant = (int)$item['subscription' === $kind ? 'monthly_credits' : 'credits'];

        global $wpdb;
        $id = wp_generate_uuid4();
        CoachPro_DB::insert(
            CoachPro_DB::table( 'payments' ),
            array(
                'id'           => $id,
                'user_id'      => $user_id,
                'kind'         => $kind,
                'plan_id'      => $plan_id,
                'pack_id'      => $pack_id,
                'amount_pkr'   => $amount_pkr,
                'credits_grant' => $credits_grant,
                'method'       => $method,
                'sender_name'  => sanitize_text_field( $params['sender_name'] ?? '' ),
                'sender_phone' => sanitize_text_field( $params['sender_phone'] ?? '' ),
                'reference_no' => sanitize_text_field( $params['reference_no'] ?? '' ),
                'notes'        => sanitize_textarea_field( $params['notes'] ?? '' ),
                'status'       => 'pending',
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
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

        if ('pending' !== $payment['status']) return new WP_Error('already_processed', 'Only pending payments accept proof uploads.', array('status'=>409));

        if ( empty( $_FILES['proof'] ) ) {
            return new WP_Error( 'missing_file', __( 'No file uploaded.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $file     = $_FILES['proof']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        // Restrict to safe image/PDF types and enforce a 5 MB size limit.
        $allowed_mimes = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'pdf'          => 'application/pdf',
        );
        $max_size_bytes = 5 * 1024 * 1024; // 5 MB

        if ( isset( $file['size'] ) && $file['size'] > $max_size_bytes ) {
            return new WP_Error( 'file_too_large', __( 'File must be smaller than 5 MB.', 'coachpro-ai' ), array( 'status' => 400 ) );
        }

        $uploaded = wp_handle_upload( $file, array(
            'test_form' => false,
            'mimes'     => $allowed_mimes,
        ) );

        if ( isset( $uploaded['error'] ) ) {
            return new WP_Error( 'upload_failed', $uploaded['error'], array( 'status' => 500 ) );
        }

        global $wpdb;
        CoachPro_DB::update(
            CoachPro_DB::table( 'payments' ),
            array( 'proof_url' => esc_url_raw( $uploaded['url'] ) ),
            array( 'id' => $payment_id ),
            array( '%s' ),
            array( '%s' )
        );

        return rest_ensure_response( array( 'proof_url' => $uploaded['url'] ) );
    }
}
