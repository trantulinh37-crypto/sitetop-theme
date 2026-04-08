<?php
/**
 * AJAX: Admin get/process deposits + Customer create deposit
 * Tách từ functions.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   AJAX: Admin Get Deposits
   ============================================================ */
add_action( 'wp_ajax_linkngon_admin_get_deposits', function() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Không có quyền' );

    global $wpdb;
    $prefix = $wpdb->prefix . 'linkngon_';
    $status = sanitize_text_field( $_POST['status'] ?? '' );

    $where = '';
    if ( $status ) $where = $wpdb->prepare( ' WHERE status = %s', $status );

    $deposits = $wpdb->get_results( "SELECT * FROM {$prefix}customer_deposits{$where} ORDER BY created_at DESC LIMIT 50" );
    wp_send_json_success( array( 'deposits' => $deposits ) );
});

/* ============================================================
   AJAX: Admin Process Deposit
   ============================================================ */
add_action( 'wp_ajax_linkngon_admin_process_deposit', function() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Không có quyền' );

    global $wpdb;
    $prefix     = $wpdb->prefix . 'linkngon_';
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
            'created_at'   => linkngon_current_time(),
        ));
    }

    $wpdb->update( $prefix . 'customer_deposits', array(
        'status'      => $new_status,
        'approved_by' => get_current_user_id(),
        'approved_at' => linkngon_current_time(),
    ), array( 'id' => $deposit_id ) );

    $wpdb->query( 'COMMIT' );

    // Email notifications
    if ( $new_status === 'approved' ) {
        linkngon_send_deposit_approved_email( $deposit_id );
    } elseif ( $new_status === 'rejected' ) {
        linkngon_send_deposit_rejected_email( $deposit_id );
    }

    wp_send_json_success( 'Đã xử lý đơn nạp #' . $deposit_id );
});

/* ============================================================
   AJAX: Customer Create Deposit
   ============================================================ */
add_action( 'wp_ajax_linkngon_customer_deposit', function() {
    check_ajax_referer( 'linkngon_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    $user_id = get_current_user_id();
    $user    = wp_get_current_user();
    $amount  = floatval( $_POST['amount'] ?? 0 );

    $min = floatval( linkngon_get_option( 'min_deposit_amount', 50000 ) );
    $max = 100000000;

    if ( $amount < $min ) wp_send_json_error( 'Số tiền tối thiểu ' . linkngon_format_money( $min ) );
    if ( $amount > $max ) wp_send_json_error( 'Số tiền tối đa ' . linkngon_format_money( $max ) );

    // Calculate bonus
    $bonus_percent = 0;
    $tiers = json_decode( linkngon_get_option( 'deposit_presets', '[]' ), true );
    if ( is_array( $tiers ) ) {
        usort( $tiers, function( $a, $b ) { return $a['amount'] - $b['amount']; } );
        foreach ( $tiers as $tier ) {
            if ( $amount >= $tier['amount'] ) $bonus_percent = $tier['bonus'];
        }
    }
    $bonus_amount = floor( $amount * $bonus_percent / 100 );

    global $wpdb;
    $prefix = $wpdb->prefix . 'linkngon_';

    $wpdb->insert( $prefix . 'customer_deposits', array(
        'customer_id'       => $user_id,
        'customer_username' => $user->user_login,
        'amount'            => $amount,
        'bonus_percent'     => $bonus_percent,
        'bonus_amount'      => $bonus_amount,
        'payment_method'    => in_array($_POST['payment_method'] ?? 'bank', array('bank','usdt')) ? $_POST['payment_method'] : 'bank',
        'status'            => 'pending',
        'created_at'        => linkngon_current_time(),
    ));

    if ( ! $wpdb->insert_id ) wp_send_json_error( 'Lỗi tạo đơn nạp tiền' );

    wp_send_json_success( 'Đơn nạp tiền #' . $wpdb->insert_id . ' đã tạo thành công' );
});
