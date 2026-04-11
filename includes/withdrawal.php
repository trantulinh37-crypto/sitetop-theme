<?php
/**
 * Traffictop.net V2 - Withdrawal Flow (CLAUDE.md Flow 5)
 * CHỐNG RÚT VƯỢT SỐ DƯ: FOR UPDATE lock + atomic WHERE balance >= amount
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function traffictop_submit_withdrawal( $user_id, $amount, $method, $bank_info = array() ) {
    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $amount = absint($amount); // VND is integer currency (no decimals)

    // Banned user check
    if ( get_user_meta( $user_id, 'traffictop_banned', true ) ) {
        return new WP_Error( 'banned', 'Tài khoản bị khóa' );
    }

    if ( $amount <= 0 ) return new WP_Error( 'invalid', 'Số tiền không hợp lệ' );

    $min = absint( traffictop_get_option('min_withdrawal', 50000) );
    if ( $amount < $min ) return new WP_Error('min_amount', 'Rút tối thiểu: ' . traffictop_format_money($min));

    $available = traffictop_get_user_balance_amount($user_id);
    if ( $amount > $available ) return new WP_Error('insufficient', 'Số dư không đủ: ' . traffictop_format_money($available));

    // Pre-check pending (fast fail, will be rechecked inside transaction)
    $pending = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}withdrawals WHERE user_id=%d AND status IN ('pending','approved')", $user_id ));
    if ( $pending > 0 ) return new WP_Error('pending_exists', 'Đang có yêu cầu chờ duyệt');

    $wpdb->query('START TRANSACTION');
    try {
        // Sync balance (fix drift)
        traffictop_sync_user_balance($user_id);
        // FOR UPDATE lock
        $bal_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$p}user_balance WHERE user_id=%d FOR UPDATE", $user_id ));
        if ( !$bal_row || $bal_row->balance < $amount ) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('insufficient', 'Số dư không đủ sau kiểm tra');
        }

        // Recheck pending INSIDE transaction after lock (prevent race condition)
        $pending_recheck = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}withdrawals WHERE user_id=%d AND status IN ('pending','approved')", $user_id ));
        if ( $pending_recheck > 0 ) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('pending_exists', 'Đang có yêu cầu chờ duyệt');
        }
        // Atomic deduct
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$p}user_balance SET balance=balance-%d, updated_at=%s WHERE user_id=%d AND balance>=%d",
            $amount, traffictop_current_time(), $user_id, $amount ));
        if ( !$updated ) { $wpdb->query('ROLLBACK'); return new WP_Error('race', 'Lỗi trừ số dư'); }

        // Create withdrawal (column names MUST match DB schema)
        $inserted = $wpdb->insert("{$p}withdrawals", array(
            'user_id'        => $user_id,
            'amount'         => $amount,
            'payment_method' => sanitize_text_field($method),
            'bank_name'      => sanitize_text_field($bank_info['bank_name'] ?? ''),
            'bank_account'   => sanitize_text_field($bank_info['bank_account'] ?? ''),
            'bank_holder'    => sanitize_text_field($bank_info['bank_holder'] ?? ''),
            'wallet_address' => sanitize_text_field($bank_info['wallet_address'] ?? ''),
            'status'         => 'pending',
            'created_at'     => traffictop_current_time(),
        ));
        if ( ! $inserted ) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 'Lỗi tạo lệnh rút tiền');
        }
        $wid = $wpdb->insert_id;

        // Log transaction — balance_after from source of truth
        $balance_after = traffictop_get_user_balance_amount($user_id);
        $wpdb->insert("{$p}transactions", array(
            'user_id'=>$user_id, 'amount'=>-$amount, 'type'=>'withdraw',
            'reference_id'=>$wid, 'reference_type'=>'withdrawal',
            'description'=>'Rút tiền #'.$wid, 'balance_after'=>$balance_after,
            'created_at'=>traffictop_current_time(),
        ));

        $wpdb->query('COMMIT');

        // Email admin
        traffictop_send_withdrawal_pending_email( $wid );

        // Save bank info for next time
        if ( ! empty( $bank_info['bank_name'] ) ) update_user_meta( $user_id, 'traffictop_bank_name', sanitize_text_field( $bank_info['bank_name'] ) );
        if ( ! empty( $bank_info['bank_account'] ) ) update_user_meta( $user_id, 'traffictop_bank_account', sanitize_text_field( $bank_info['bank_account'] ) );
        if ( ! empty( $bank_info['bank_holder'] ) ) update_user_meta( $user_id, 'traffictop_bank_holder', sanitize_text_field( $bank_info['bank_holder'] ) );

        return $wid;
    } catch (Exception $e) { $wpdb->query('ROLLBACK'); return new WP_Error('error', $e->getMessage()); }
}

function traffictop_process_withdrawal( $withdrawal_id, $new_status, $admin_note = '' ) {
    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;

    $w = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$p}withdrawals WHERE id=%d", $withdrawal_id));
    if (!$w) return new WP_Error('not_found', 'Không tìm thấy');

    $transitions = array(
        'pending'=>array('approved','rejected','completed','cancelled'), 'approved'=>array('completed','rejected','cancelled'),
        'completed'=>array('refunded'),
    );
    if ( !isset($transitions[$w->status]) || !in_array($new_status, $transitions[$w->status]) )
        return new WP_Error('invalid', "Không thể {$w->status} → {$new_status}");

    // Refund statuses need transaction for atomicity
    if ( in_array($new_status, array('rejected','refunded')) ) {
        $wpdb->query('START TRANSACTION');
        try {
            // Lock user_balance FOR UPDATE to prevent race condition with concurrent withdrawal
            $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$p}user_balance WHERE user_id = %d FOR UPDATE", $w->user_id
            ));

            // Lock withdrawal FOR UPDATE to prevent double-refund
            $w_locked = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$p}withdrawals WHERE id = %d FOR UPDATE", $withdrawal_id
            ));
            if ( ! $w_locked || ! in_array( $w_locked->status, array( 'pending', 'approved', 'completed' ) ) ) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('invalid', 'Withdrawal đã được xử lý');
            }

            $wpdb->update("{$p}withdrawals", array(
                'status'=>$new_status, 'admin_note'=>sanitize_text_field($admin_note), 'updated_at'=>traffictop_current_time()
            ), array('id'=>$withdrawal_id));

            // Restore balance
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$p}user_balance SET balance = balance + %d WHERE user_id = %d",
                absint($w->amount), $w->user_id
            ));

            // Log refund transaction — balance_after tính SAU khi đã restore balance
            $balance_after = traffictop_get_user_balance_amount($w->user_id);
            $wpdb->insert("{$p}transactions", array(
                'user_id'=>$w->user_id, 'amount'=>$w->amount, 'type'=>'refund',
                'reference_id'=>$withdrawal_id, 'reference_type'=>'withdrawal',
                'description'=>"Hoàn tiền rút #{$withdrawal_id} ({$new_status})",
                'balance_after'=>$balance_after, 'created_at'=>traffictop_current_time(),
            ));
            $wpdb->update("{$p}withdrawals", array('refund_amount'=>$w->amount), array('id'=>$withdrawal_id));

            $wpdb->query('COMMIT');
            traffictop_sync_user_balance($w->user_id);
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('error', $e->getMessage());
        }
    } else {
        // Non-refund status changes (approve, complete, cancel) — lock to prevent double-approve
        $wpdb->query('START TRANSACTION');
        $w_locked = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$p}withdrawals WHERE id = %d FOR UPDATE", $withdrawal_id
        ));
        if ( ! $w_locked || $w_locked->status !== $w->status ) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('conflict', 'Trạng thái đã thay đổi, vui lòng tải lại');
        }
        $wpdb->update("{$p}withdrawals", array(
            'status'=>$new_status, 'admin_note'=>sanitize_text_field($admin_note), 'updated_at'=>traffictop_current_time()
        ), array('id'=>$withdrawal_id));
        $wpdb->query('COMMIT');
    }

    // Email user about status change
    traffictop_send_withdrawal_status_email( $withdrawal_id, $new_status );

    return true;
}
