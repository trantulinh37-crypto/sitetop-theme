<?php
/**
 * LinkNgon V2 - Withdrawal Flow (CLAUDE.md Flow 5)
 * CHỐNG RÚT VƯỢT SỐ DƯ: FOR UPDATE lock + atomic WHERE balance >= amount
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function linkngon_submit_withdrawal( $user_id, $amount, $method, $bank_info = array() ) {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $amount = floatval($amount);

    // Banned user check
    if ( get_user_meta( $user_id, 'linkngon_banned', true ) ) {
        return new WP_Error( 'banned', 'Tài khoản bị khóa' );
    }

    if ( $amount <= 0 ) return new WP_Error( 'invalid', 'Số tiền không hợp lệ' );

    $min = floatval( linkngon_get_option('min_withdrawal', 50000) );
    if ( $amount < $min ) return new WP_Error('min_amount', 'Rút tối thiểu: ' . linkngon_format_money($min));

    $available = linkngon_get_user_balance_amount($user_id);
    if ( $amount > $available ) return new WP_Error('insufficient', 'Số dư không đủ: ' . linkngon_format_money($available));

    // Check pending
    $pending = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}withdrawals WHERE user_id=%d AND status IN ('pending','approved')", $user_id ));
    if ( $pending > 0 ) return new WP_Error('pending_exists', 'Đang có yêu cầu chờ duyệt');

    $wpdb->query('START TRANSACTION');
    try {
        // Sync balance (fix drift)
        linkngon_sync_user_balance($user_id);
        // FOR UPDATE lock
        $bal_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$p}user_balance WHERE user_id=%d FOR UPDATE", $user_id ));
        if ( !$bal_row || $bal_row->balance < $amount ) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('insufficient', 'Số dư không đủ sau kiểm tra');
        }
        // Atomic deduct
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$p}user_balance SET balance=balance-%f, updated_at=%s WHERE user_id=%d AND balance>=%f",
            $amount, linkngon_current_time(), $user_id, $amount ));
        if ( !$updated ) { $wpdb->query('ROLLBACK'); return new WP_Error('race', 'Lỗi trừ số dư'); }

        // Create withdrawal
        $wpdb->insert("{$p}withdrawals", array(
            'user_id'=>$user_id, 'amount'=>$amount, 'method'=>sanitize_text_field($method),
            'bank_name'=>sanitize_text_field($bank_info['bank_name']??''),
            'bank_account'=>sanitize_text_field($bank_info['bank_account']??''),
            'bank_holder'=>sanitize_text_field($bank_info['bank_holder']??''),
            'status'=>'pending', 'created_at'=>linkngon_current_time(),
        ));
        $wid = $wpdb->insert_id;

        // Log transaction
        $wpdb->insert("{$p}transactions", array(
            'user_id'=>$user_id, 'amount'=>-$amount, 'type'=>'withdraw',
            'reference_id'=>$wid, 'reference_type'=>'withdrawal',
            'description'=>'Rút tiền #'.$wid, 'balance_after'=>$bal_row->balance - $amount,
            'created_at'=>linkngon_current_time(),
        ));

        $wpdb->query('COMMIT');
        return $wid;
    } catch (Exception $e) { $wpdb->query('ROLLBACK'); return new WP_Error('error', $e->getMessage()); }
}

function linkngon_process_withdrawal( $withdrawal_id, $new_status, $admin_note = '' ) {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;

    $w = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$p}withdrawals WHERE id=%d", $withdrawal_id));
    if (!$w) return new WP_Error('not_found', 'Không tìm thấy');

    $transitions = array(
        'pending'=>array('approved','rejected'), 'approved'=>array('completed','cancelled'),
        'completed'=>array('refunded'),
    );
    if ( !isset($transitions[$w->status]) || !in_array($new_status, $transitions[$w->status]) )
        return new WP_Error('invalid', "Không thể {$w->status} → {$new_status}");

    $wpdb->update("{$p}withdrawals", array(
        'status'=>$new_status, 'admin_note'=>sanitize_text_field($admin_note), 'updated_at'=>linkngon_current_time()
    ), array('id'=>$withdrawal_id));

    // Rejected → auto refund
    if ( in_array($new_status, array('rejected','refunded')) ) {
        $balance = linkngon_get_user_balance_amount($w->user_id);
        $wpdb->insert("{$p}transactions", array(
            'user_id'=>$w->user_id, 'amount'=>$w->amount, 'type'=>'refund',
            'reference_id'=>$withdrawal_id, 'reference_type'=>'withdrawal',
            'description'=>"Hoàn tiền rút #{$withdrawal_id} ({$new_status})",
            'balance_after'=>$balance + $w->amount, 'created_at'=>linkngon_current_time(),
        ));
        $wpdb->update("{$p}withdrawals", array('refund_amount'=>$w->amount), array('id'=>$withdrawal_id));
        linkngon_sync_user_balance($w->user_id);
    }
    return true;
}
