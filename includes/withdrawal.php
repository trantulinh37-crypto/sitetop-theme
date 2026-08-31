<?php
/**
 * SiteTop.net V2 - Withdrawal Flow (CLAUDE.md Flow 5)
 * CHỐNG RÚT VƯỢT SỐ DƯ: FOR UPDATE lock + atomic WHERE balance >= amount
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function sitetop_submit_withdrawal( $user_id, $amount, $method, $bank_info = array() ) {
    global $wpdb;
    $p = $wpdb->prefix . SITETOP_PREFIX;
    $amount = absint($amount); // VND is integer currency (no decimals)

    // Banned user check
    if ( get_user_meta( $user_id, 'sitetop_banned', true ) ) {
        return new WP_Error( 'banned', 'Tài khoản bị khóa' );
    }

    // Chốt tuổi tài khoản (chống tạo hàng loạt tài khoản ảo rồi rút ngay).
    // Tính theo GIỜ chứ không theo ngày, để đặt được mốc 48 giờ cho chính xác.
    // user_registered lưu theo UTC nên so với time() cũng UTC.
    //
    // Chốt này chỉ chặn được lần rút ĐẦU: qua mốc rồi thì tài khoản luôn đủ tuổi,
    // không cần đếm số lệnh đã rút.
    $min_age_hours = (int) sitetop_get_option( 'min_account_age_hours', 48 );
    $udata = get_userdata( $user_id );
    if ( $min_age_hours > 0 && $udata && ! empty( $udata->user_registered ) ) {
        $registered_ts = strtotime( $udata->user_registered . ' UTC' );
        $elapsed = $registered_ts ? ( time() - $registered_ts ) : PHP_INT_MAX;
        if ( $registered_ts && $elapsed < ( $min_age_hours * HOUR_IN_SECONDS ) ) {
            // Báo số thời gian còn lại thay vì chỉ nói "chưa đủ" — user biết khi nào quay lại.
            $left = ( $min_age_hours * HOUR_IN_SECONDS ) - $elapsed;
            $h = floor( $left / HOUR_IN_SECONDS );
            $m = floor( ( $left % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
            $con = $h > 0 ? ( $h . ' giờ ' . $m . ' phút' ) : ( $m . ' phút' );
            return new WP_Error( 'too_new',
                'Tài khoản mới cần đủ ' . $min_age_hours . ' giờ kể từ lúc đăng ký mới rút được. Còn ' . $con . '.' );
        }
    }

    if ( $amount <= 0 ) return new WP_Error( 'invalid', 'Số tiền không hợp lệ' );

    $min = absint( sitetop_get_option('min_withdrawal', 50000) );
    if ( $amount < $min ) return new WP_Error('min_amount', 'Rút tối thiểu: ' . sitetop_format_money($min));

    /* Trần mỗi lần rút. 0 = không giới hạn (giữ nguyên cách chạy cũ nếu admin chưa đặt).
       Chặn ở đây chứ không chỉ ở thuộc tính max của ô nhập, vì thuộc tính đó user sửa
       được bằng công cụ trình duyệt. */
    $max = absint( sitetop_get_option('max_withdrawal', 0) );
    if ( $max > 0 && $amount > $max ) {
        return new WP_Error('max_amount', 'Mỗi lần rút tối đa ' . sitetop_format_money($max)
            . '. Vui lòng chia thành nhiều lần.');
    }

    $available = sitetop_get_user_balance_amount($user_id);
    if ( $amount > $available ) return new WP_Error('insufficient', 'Số dư không đủ: ' . sitetop_format_money($available));

    // Pre-check pending (fast fail, will be rechecked inside transaction)
    $pending = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}withdrawals WHERE user_id=%d AND source='task' AND status IN ('pending','approved')", $user_id ));
    if ( $pending > 0 ) return new WP_Error('pending_exists', 'Đang có yêu cầu chờ duyệt');

    $wpdb->query('START TRANSACTION');
    try {
        // Acquire the row lock FIRST to serialize concurrent withdrawals for this user,
        // THEN sync balance from source-of-truth under the lock, THEN read the synced value.
        // (Locking before the sync write avoids a TOCTOU on the cache field.)
        $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$p}user_balance WHERE user_id=%d FOR UPDATE", $user_id ));
        sitetop_sync_user_balance($user_id);
        $bal_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$p}user_balance WHERE user_id=%d", $user_id ));
        if ( !$bal_row || $bal_row->balance < $amount ) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('insufficient', 'Số dư không đủ sau kiểm tra');
        }

        // Recheck pending INSIDE transaction after lock (prevent race condition)
        $pending_recheck = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}withdrawals WHERE user_id=%d AND source='task' AND status IN ('pending','approved')", $user_id ));
        if ( $pending_recheck > 0 ) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('pending_exists', 'Đang có yêu cầu chờ duyệt');
        }
        // Atomic deduct
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$p}user_balance SET balance=balance-%d, updated_at=%s WHERE user_id=%d AND balance>=%d",
            $amount, sitetop_current_time(), $user_id, $amount ));
        if ( !$updated ) { $wpdb->query('ROLLBACK'); return new WP_Error('race', 'Lỗi trừ số dư'); }

        /* CHỐT KỲ ngay lúc đặt lệnh (31/08/2026). period_end là chính thời điểm này;
           period_start là mốc kết thúc của lệnh liền trước, lấy theo MỌI trạng thái —
           kể cả lệnh đã bị từ chối — để kỳ không bao giờ nới rộng về sau. Chưa có lệnh
           nào thì lấy lượt truy cập đầu tiên của user.
           Ghi hai cột này chỉ để soi chi tiết; KHÔNG tham gia tính tiền. */
        $now_wd  = sitetop_current_time();
        $has_col = in_array( 'period_start', $wpdb->get_col( "SHOW COLUMNS FROM {$p}withdrawals" ), true );
        $wd_row  = array(
            'user_id'        => $user_id,
            'amount'         => $amount,
            'payment_method' => sanitize_text_field($method),
            'bank_name'      => sanitize_text_field($bank_info['bank_name'] ?? ''),
            'bank_account'   => sanitize_text_field($bank_info['bank_account'] ?? ''),
            'bank_holder'    => sanitize_text_field($bank_info['bank_holder'] ?? ''),
            'wallet_address' => sanitize_text_field($bank_info['wallet_address'] ?? ''),
            'status'         => 'pending',
            'created_at'     => $now_wd,
        );
        if ( $has_col ) {
            $prev_end = $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(MAX(period_end), MAX(created_at)) FROM {$p}withdrawals WHERE user_id=%d", $user_id ) );
            if ( ! $prev_end ) {
                $prev_end = $wpdb->get_var( $wpdb->prepare(
                    "SELECT MIN(created_at) FROM {$p}shortlink_visits WHERE user_id=%d", $user_id ) );
            }
            $wd_row['period_start'] = $prev_end ?: '1970-01-01 00:00:00';
            $wd_row['period_end']   = $now_wd;
        }

        // Create withdrawal (column names MUST match DB schema)
        $inserted = $wpdb->insert("{$p}withdrawals", $wd_row);

        if ( ! $inserted ) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 'Lỗi tạo lệnh rút tiền');
        }
        $wid = $wpdb->insert_id;

        // Log transaction — balance_after from source of truth
        $balance_after = sitetop_get_user_balance_amount($user_id);
        $wpdb->insert("{$p}transactions", array(
            'user_id'=>$user_id, 'amount'=>-$amount, 'type'=>'withdraw',
            'reference_id'=>$wid, 'reference_type'=>'withdrawal',
            'description'=>'Rút tiền #'.$wid, 'balance_after'=>$balance_after,
            'created_at'=>sitetop_current_time(),
        ));

        $wpdb->query('COMMIT');

        // Email admin
        sitetop_send_withdrawal_pending_email( $wid );

        // Save bank info for next time
        if ( ! empty( $bank_info['bank_name'] ) ) update_user_meta( $user_id, 'sitetop_bank_name', sanitize_text_field( $bank_info['bank_name'] ) );
        if ( ! empty( $bank_info['bank_account'] ) ) update_user_meta( $user_id, 'sitetop_bank_account', sanitize_text_field( $bank_info['bank_account'] ) );
        if ( ! empty( $bank_info['bank_holder'] ) ) update_user_meta( $user_id, 'sitetop_bank_holder', sanitize_text_field( $bank_info['bank_holder'] ) );

        return $wid;
    } catch (Exception $e) { $wpdb->query('ROLLBACK'); return new WP_Error('error', $e->getMessage()); }
}

function sitetop_process_withdrawal( $withdrawal_id, $new_status, $admin_note = '' ) {
    global $wpdb;
    $p = $wpdb->prefix . SITETOP_PREFIX;

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
                'status'=>$new_status, 'admin_note'=>sanitize_text_field($admin_note), 'updated_at'=>sitetop_current_time()
            ), array('id'=>$withdrawal_id));

            // Restore balance by RE-DERIVING from source-of-truth, NOT by hand-editing the
            // cache field. After the status change above, the withdrawal leaves the
            // pending/approved (and completed/cancelled) deduction bucket, so the formula
            // already reflects the restored amount. Avoids a second source of truth that
            // could drift or double-count.
            // balance_after reflects the restored balance (status already changed above).
            // source='referral' (xem includes/referral-management.php): sổ hoa hồng KHÔNG có
            // cache, nên không cần re-sync — công thức đọc sống, tự đúng ngay khi status vừa
            // đổi ở trên. Chỉ balance_after cần đọc đúng sổ để không ghi nhầm số của sổ kia.
            $balance_after = ( $w->source === 'referral' && function_exists( 'sitetop_get_referral_balance_amount' ) )
                ? sitetop_get_referral_balance_amount( $w->user_id )
                : sitetop_get_user_balance_amount( $w->user_id );
            $wpdb->insert("{$p}transactions", array(
                'user_id'=>$w->user_id, 'amount'=>$w->amount, 'type'=>'refund',
                'reference_id'=>$withdrawal_id, 'reference_type'=>'withdrawal',
                'description'=>"Hoàn tiền rút #{$withdrawal_id} ({$new_status})",
                'balance_after'=>$balance_after, 'created_at'=>sitetop_current_time(),
            ));
            $wpdb->update("{$p}withdrawals", array('refund_amount'=>$w->amount), array('id'=>$withdrawal_id));

            // Sync cache = formula atomically inside the locked transaction. Vô hại khi
            // source='referral' — chỉ re-sync đúng giá trị sổ nhiệm vụ vốn không hề đổi.
            sitetop_sync_user_balance($w->user_id);

            $wpdb->query('COMMIT');
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
            'status'=>$new_status, 'admin_note'=>sanitize_text_field($admin_note), 'updated_at'=>sitetop_current_time()
        ), array('id'=>$withdrawal_id));
        $wpdb->query('COMMIT');
    }

    // Email user about status change
    sitetop_send_withdrawal_status_email( $withdrawal_id, $new_status );

    return true;
}
