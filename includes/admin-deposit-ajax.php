<?php
/**
 * AJAX: Admin get/process deposits + Customer create deposit
 * Tách từ functions.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   AJAX: Admin Get Deposits
   ============================================================ */
add_action( 'wp_ajax_traffictop_admin_get_deposits', function() {
    check_ajax_referer( 'traffictop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Không có quyền' );

    global $wpdb;
    $prefix = $wpdb->prefix . 'traffictop_';
    $status = sanitize_text_field( $_POST['status'] ?? '' );

    $where = '';
    if ( $status ) $where = $wpdb->prepare( ' WHERE status = %s', $status );

    $deposits = $wpdb->get_results( "SELECT * FROM {$prefix}customer_deposits{$where} ORDER BY created_at DESC LIMIT 50" );
    wp_send_json_success( array( 'deposits' => $deposits ) );
});

/* ============================================================
   AJAX: Admin Process Deposit
   ============================================================ */
add_action( 'wp_ajax_traffictop_admin_process_deposit', function() {
    check_ajax_referer( 'traffictop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Không có quyền' );

    global $wpdb;
    $prefix     = $wpdb->prefix . 'traffictop_';
    $deposit_id = intval( $_POST['deposit_id'] ?? 0 );
    $new_status = sanitize_text_field( $_POST['new_status'] ?? '' );

    if ( ! $deposit_id || ! in_array( $new_status, array( 'approved', 'rejected' ), true ) ) {
        wp_send_json_error( 'Tham số không hợp lệ' );
    }

    $wpdb->query( 'START TRANSACTION' );

    $deposit = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$prefix}customer_deposits WHERE id = %d FOR UPDATE", $deposit_id
    ));

    if ( ! $deposit || $deposit->status !== 'pending' ) {
        $wpdb->query( 'ROLLBACK' );
        wp_send_json_error( 'Đơn không hợp lệ hoặc đã xử lý' );
    }

    if ( $new_status === 'approved' ) {
        $total = (float) $deposit->amount + (float) ( $deposit->bonus_amount ?? 0 );

        // Update customer balance
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$prefix}customer_balance WHERE user_id = %d FOR UPDATE", $deposit->customer_id
        ));
        if ( $exists ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$prefix}customer_balance SET balance = balance + %f, total_deposited = total_deposited + %f WHERE user_id = %d",
                $total, $total, $deposit->customer_id
            ));
        } else {
            $wpdb->insert( $prefix . 'customer_balance', array(
                'user_id' => $deposit->customer_id, 'balance' => $total, 'total_deposited' => $total, 'total_spent' => 0,
            ));
        }

        // Log transaction
        $wpdb->insert( $prefix . 'customer_transactions', array(
            'customer_id'  => $deposit->customer_id,
            'type'         => 'deposit',
            'amount'       => $total,
            'description'  => 'Nạp tiền #' . $deposit_id,
            'reference_id' => $deposit_id,
            'reference_type' => 'deposit',
            'status'       => 'completed',
            'created_at'   => traffictop_current_time(),
        ));
    }

    $wpdb->update( $prefix . 'customer_deposits', array(
        'status'      => $new_status,
        'approved_by' => get_current_user_id(),
        'approved_at' => traffictop_current_time(),
    ), array( 'id' => $deposit_id ) );

    $wpdb->query( 'COMMIT' );

    // Email notifications
    if ( $new_status === 'approved' ) {
        traffictop_send_deposit_approved_email( $deposit_id );
    } elseif ( $new_status === 'rejected' ) {
        traffictop_send_deposit_rejected_email( $deposit_id );
    }

    wp_send_json_success( 'Đã xử lý đơn nạp #' . $deposit_id );
});

/* ============================================================
   AJAX: Customer Create Deposit
   ============================================================ */
add_action( 'wp_ajax_traffictop_customer_deposit', function() {
    check_ajax_referer( 'traffictop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );
    // Chỉ role customer (hoặc admin) được tạo đơn nạp — trước đây publisher thường cũng tạo được
    // (incident 02/07/2026: user alonemmo #134 tạo đơn nạp #17 dù không phải khách hàng).
    traffictop_require_customer_role();

    $user_id = get_current_user_id();
    // B1: banned customers cannot create deposits (parity with withdrawal/ campaign handlers).
    if ( function_exists( 'traffictop_block_banned_customer' ) ) {
        traffictop_block_banned_customer( $user_id );
    }
    // Rate-limit deposit creation (3/min per customer) per CLAUDE.md deposit policy.
    if ( function_exists( 'traffictop_rate_limit_check' ) ) {
        $dep_rl = traffictop_rate_limit_check( 'deposit', 'cust_' . $user_id );
        if ( empty( $dep_rl['allowed'] ) ) wp_send_json_error( 'Bạn thao tác quá nhanh, vui lòng thử lại sau.' );
    }
    $user    = wp_get_current_user();
    $amount  = absint( $_POST['amount'] ?? 0 );

    $min = floatval( traffictop_get_option( 'min_deposit_amount', 50000 ) );
    $max = 100000000;

    if ( $amount < $min ) wp_send_json_error( 'Số tiền tối thiểu ' . traffictop_format_money( $min ) );
    if ( $amount > $max ) wp_send_json_error( 'Số tiền tối đa ' . traffictop_format_money( $max ) );

    // Calculate bonus — use shared helper so customer + admin paths stay identical
    $bonus_result = traffictop_calculate_deposit_bonus( $amount );
    $bonus_percent = $bonus_result['percent'];
    $bonus_amount  = $bonus_result['amount'];

    global $wpdb;
    $prefix = $wpdb->prefix . 'traffictop_';

    $wpdb->insert( $prefix . 'customer_deposits', array(
        'customer_id'       => $user_id,
        'customer_username' => $user->user_login,
        'amount'            => $amount,
        'bonus_percent'     => $bonus_percent,
        'bonus_amount'      => $bonus_amount,
        'payment_method'    => in_array($_POST['payment_method'] ?? 'bank', array('bank','usdt')) ? $_POST['payment_method'] : 'bank',
        'status'            => 'pending',
        'created_at'        => traffictop_current_time(),
    ));

    $new_deposit_id = (int) $wpdb->insert_id;
    if ( ! $new_deposit_id ) wp_send_json_error( 'Lỗi tạo đơn nạp tiền' );

    // Thông báo admin (Telegram nếu bật, ngược lại email) — đường nạp tiền của KHÁCH; trước đây
    // traffictop_send_deposit_email() KHÔNG được gọi từ đâu cả nên admin không hề nhận (lesson #4).
    if ( function_exists( 'traffictop_send_deposit_email' ) ) {
        traffictop_send_deposit_email( $new_deposit_id );
    }

    wp_send_json_success( 'Đơn nạp tiền #' . $new_deposit_id . ' đã tạo thành công' );
});
