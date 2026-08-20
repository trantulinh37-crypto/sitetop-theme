<?php
/**
 * Referral commission engine — TÍNH và TRẢ hoa hồng khi người được giới thiệu kiếm tiền.
 *
 * TRẠNG THÁI TRƯỚC KHI CÓ FILE NÀY (tới 20/08/2026): trang Referral trong dashboard,
 * 4 setting "Bật Referral / Hoa hồng % / Rút tối thiểu referral / Thời hạn hoa hồng",
 * và việc lưu sitetop_referred_by lúc đăng ký đều đã có — nhưng KHÔNG NƠI NÀO đọc lại
 * sitetop_referred_by để thực sự trả hoa hồng. Tab thống kê referral tự ghi "sẽ được
 * cập nhật". Đây là phần lõi còn thiếu.
 *
 * MÔ HÌNH: CỘNG THÊM, không trừ của ai. Người được giới thiệu vẫn nhận đủ 100% thưởng
 * như bình thường; người giới thiệu nhận thêm referral_commission_percent% tính trên
 * đúng số đó, do hệ thống trả riêng — giống affiliate thông thường.
 *
 * SỔ RIÊNG, KHÔNG GỘP VÀO SỐ DƯ NHIỆM VỤ: bản đầu tiên của file này (20/08/2026) gộp
 * referral_commission vào balance/total_earned chung — rồi phát hiện ngay khi bàn thiết
 * kế: làm vậy thì "Rút tối thiểu referral" vô nghĩa, vì rút qua nút thường (min_withdrawal
 * chung) vẫn lấy được tiền hoa hồng, không qua cổng riêng. Đã sửa lại: hoa hồng có sổ
 * riêng — sitetop_get_referral_balance_amount() tính sống từ transactions (KHÔNG cộng
 * vào cache user_balance.balance/.total_earned, xem nhánh `if ($type !== 'referral_commission')`
 * trong sitetop_add_user_balance()), rút qua sitetop_submit_referral_withdrawal() riêng,
 * đánh dấu bằng cột wp_sitetop_withdrawals.source='referral' (mặc định 'task' cho mọi
 * lệnh rút tiền nhiệm vụ, kể cả các dòng có từ trước khi thêm cột). Khoá bằng chung
 * FOR UPDATE trên đúng dòng user_balance mà luồng rút tiền nhiệm vụ đã dùng — hai luồng
 * rút không thể đua nhau, dù không đụng chung một cột số dư.
 *
 * MÓC VÀO ĐÂU: hook 'sitetop_user_balance_added' (bắn ở cuối sitetop_add_user_balance(),
 * xem includes/shortlink-verification.php) — KHÔNG sửa bất kỳ điều kiện `if` nào trong
 * luồng chấm thưởng keyword/1 bước/2 bước đang chạy đúng. Hàm ở đây chỉ lắng nghe sự
 * kiện "đã cộng tiền xong", không tham gia quyết định có cộng hay không.
 *
 * CHỈ MỘT TẦNG: sitetop_referred_by chỉ lưu người giới thiệu trực tiếp, không có khái
 * niệm giới thiệu của giới thiệu (không đa cấp). Vì hàm trả hoa hồng cũng gọi qua
 * sitetop_add_user_balance() nên nó tự bắn lại hook này với type='referral_commission' —
 * bộ lọc "$type !== 'shortlink_reward' -> bỏ qua" bên dưới chặn đứng vòng lặp đó, không
 * cần cờ chống đệ quy riêng.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Referrer_id đang hoạt động cho $user_id, hoặc 0 nếu không đủ điều kiện trả hoa hồng
 * (chưa bật referral, không được ai giới thiệu, referrer đã bị xoá, hoặc đã quá
 * referral_duration_days ngày kể từ lúc được giới thiệu).
 */
function sitetop_get_active_referrer_id( $user_id ) {
    if ( ! sitetop_get_option( 'referral_enabled', 0 ) ) return 0;

    $referrer_id = (int) get_user_meta( $user_id, 'sitetop_referred_by', true );
    if ( $referrer_id <= 0 || $referrer_id === (int) $user_id ) return 0;
    if ( ! get_user_by( 'id', $referrer_id ) ) return 0; // referrer đã bị xoá tài khoản

    $days = (int) sitetop_get_option( 'referral_duration_days', 0 );
    if ( $days > 0 ) {
        $referred_at = get_user_meta( $user_id, 'sitetop_referred_at', true );
        if ( $referred_at && ( strtotime( sitetop_current_time() ) - strtotime( $referred_at ) ) > $days * DAY_IN_SECONDS ) {
            return 0; // hết hạn cửa sổ hưởng hoa hồng, referred_at vẫn giữ nguyên để tra cứu
        }
    }
    return $referrer_id;
}

/**
 * Trả hoa hồng cho người giới thiệu khi người được giới thiệu vừa nhận thưởng shortlink.
 * Chỉ phản ứng với type='shortlink_reward' — đây là khoản THU NHẬP THẬT của publisher
 * (khớp đúng câu quảng cáo trên dashboard: "khi bạn bè đăng ký và kiếm tiền"). Bỏ qua mọi
 * type khác (withdraw, refund, và cả referral_commission của chính nó) để không trả hoa
 * hồng trên hoa hồng, không trả khi tiền bị trừ/hoàn.
 */
add_action( 'sitetop_user_balance_added', 'sitetop_pay_referral_commission', 10, 5 );
function sitetop_pay_referral_commission( $user_id, $amount, $type, $ref_id = null, $ref_type = null ) {
    if ( $type !== 'shortlink_reward' ) return;

    $referrer_id = sitetop_get_active_referrer_id( $user_id );
    if ( ! $referrer_id ) return;

    $pct = (int) sitetop_get_option( 'referral_commission_percent', 20 );
    if ( $pct <= 0 ) return;

    $commission = (int) round( $amount * $pct / 100 );
    if ( $commission <= 0 ) return;

    $referred_user = get_user_by( 'id', $user_id );
    $referred_name = $referred_user ? $referred_user->user_login : "user#{$user_id}";

    sitetop_add_user_balance(
        $referrer_id, $commission, 'referral_commission',
        sprintf( 'Hoa hồng %d%% giới thiệu — %s kiếm được %s', $pct, $referred_name, sitetop_format_money( $amount ) ),
        $ref_id, $ref_type
    );
}

/**
 * Thống kê cho tab Referral trong dashboard: đã giới thiệu bao nhiêu người, tổng hoa
 * hồng đã nhận. Đọc trực tiếp usermeta + bảng transactions (SOURCE OF TRUTH), không
 * cần bảng mới.
 */
function sitetop_get_referral_stats( $user_id ) {
    global $wpdb;
    $prefix = $wpdb->prefix . 'sitetop_';

    $total_referred = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'sitetop_referred_by' AND meta_value = %d",
        $user_id
    ) );
    $total_commission = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$prefix}transactions WHERE user_id = %d AND type = 'referral_commission'",
        $user_id
    ) );

    return array(
        'total_referred'   => $total_referred,
        'total_commission' => $total_commission,
        'available'        => sitetop_get_referral_balance_amount( $user_id ),
    );
}

/**
 * Số hoa hồng CÒN RÚT ĐƯỢC — tính sống, không cache. Cố ý không có "sync" như
 * sitetop_sync_user_balance(): không có cột cache nào để lệch, nên không có gì phải
 * đồng bộ lại. Mọi thay đổi trạng thái withdrawal (duyệt/từ chối/hoàn tiền) tự động
 * phản ánh đúng ngay ở lần gọi kế tiếp, vì công thức đọc thẳng WHERE status=...
 */
function sitetop_get_referral_balance_amount( $user_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

    $earned = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE user_id=%d AND type='referral_commission'", $user_id ));
    $withdrawn = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}withdrawals WHERE user_id=%d AND source='referral' AND status IN ('completed','cancelled')", $user_id ));
    $pending = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}withdrawals WHERE user_id=%d AND source='referral' AND status IN ('pending','approved')", $user_id ));

    return max( 0, $earned - $withdrawn - $pending );
}

/**
 * Rút hoa hồng referral — bản song song của sitetop_submit_withdrawal() (withdrawal.php),
 * KHÔNG sửa hàm đó để tránh mọi rủi ro cho luồng rút tiền nhiệm vụ đang chạy đúng. Cùng
 * các lớp bảo vệ (banned, tuổi tài khoản, khoá FOR UPDATE chống đua), chỉ khác nguồn số
 * dư (referral_balance thay vì user_balance) và ngưỡng tối thiểu (referral_min_payout
 * thay vì min_withdrawal). Ghi vào ĐÚNG bảng withdrawals dùng chung (source='referral')
 * để admin vẫn duyệt/từ chối ở một chỗ duy nhất — không có bảng/luồng duyệt riêng.
 */
function sitetop_submit_referral_withdrawal( $user_id, $amount, $method, $bank_info = array() ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $amount = absint( $amount );

    if ( get_user_meta( $user_id, 'sitetop_banned', true ) ) {
        return new WP_Error( 'banned', 'Tài khoản bị khóa' );
    }

    // Cùng chốt tuổi tài khoản với rút tiền nhiệm vụ — hoa hồng referral là "tiền miễn phí"
    // chỉ cần được giới thiệu, nên càng cần chặn tạo tài khoản ảo rồi rút ngay.
    $min_age_hours = (int) sitetop_get_option( 'min_account_age_hours', 48 );
    $udata = get_userdata( $user_id );
    if ( $min_age_hours > 0 && $udata && ! empty( $udata->user_registered ) ) {
        $registered_ts = strtotime( $udata->user_registered . ' UTC' );
        $elapsed = $registered_ts ? ( time() - $registered_ts ) : PHP_INT_MAX;
        if ( $registered_ts && $elapsed < ( $min_age_hours * HOUR_IN_SECONDS ) ) {
            $left = ( $min_age_hours * HOUR_IN_SECONDS ) - $elapsed;
            $h = floor( $left / HOUR_IN_SECONDS );
            $m = floor( ( $left % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
            $con = $h > 0 ? ( $h . ' giờ ' . $m . ' phút' ) : ( $m . ' phút' );
            return new WP_Error( 'too_new', 'Tài khoản mới cần đủ ' . $min_age_hours . ' giờ kể từ lúc đăng ký mới rút được. Còn ' . $con . '.' );
        }
    }

    if ( $amount <= 0 ) return new WP_Error( 'invalid', 'Số tiền không hợp lệ' );

    $min = absint( sitetop_get_option( 'referral_min_payout', 50000 ) );
    if ( $amount < $min ) return new WP_Error( 'min_amount', 'Rút hoa hồng tối thiểu: ' . sitetop_format_money( $min ) );

    $available = sitetop_get_referral_balance_amount( $user_id );
    if ( $amount > $available ) return new WP_Error( 'insufficient', 'Hoa hồng khả dụng không đủ: ' . sitetop_format_money( $available ) );

    // Chỉ chặn khi ĐANG có lệnh rút HOA HỒNG chờ duyệt — cố ý không chặn chéo với lệnh rút
    // tiền nhiệm vụ đang chờ (2 sổ riêng), khác quy tắc "1 lệnh chờ duy nhất" của rút thường.
    $pending = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}withdrawals WHERE user_id=%d AND source='referral' AND status IN ('pending','approved')", $user_id ));
    if ( $pending > 0 ) return new WP_Error( 'pending_exists', 'Đang có yêu cầu rút hoa hồng chờ duyệt' );

    $wpdb->query( 'START TRANSACTION' );
    try {
        // Khoá CHUNG dòng user_balance với luồng rút tiền nhiệm vụ — không cần cột riêng
        // để khoá, chỉ cần một điểm khoá chung cho user này là đủ tránh 2 lệnh rút (hoa
        // hồng + hoa hồng, hoặc hoa hồng + nhiệm vụ) đọc trùng một số dư rồi cùng ghi.
        $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}user_balance WHERE user_id=%d FOR UPDATE", $user_id ) );

        // Đọc lại available BÊN TRONG khoá — chống đua giữa lúc pre-check ở trên và lúc này.
        $available_locked = sitetop_get_referral_balance_amount( $user_id );
        if ( $available_locked < $amount ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'insufficient', 'Hoa hồng khả dụng không đủ sau kiểm tra' );
        }
        $pending_recheck = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}withdrawals WHERE user_id=%d AND source='referral' AND status IN ('pending','approved')", $user_id ));
        if ( $pending_recheck > 0 ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'pending_exists', 'Đang có yêu cầu rút hoa hồng chờ duyệt' );
        }

        $inserted = $wpdb->insert( "{$p}withdrawals", array(
            'user_id'        => $user_id,
            'amount'         => $amount,
            'payment_method' => sanitize_text_field( $method ),
            'bank_name'      => sanitize_text_field( $bank_info['bank_name'] ?? '' ),
            'bank_account'   => sanitize_text_field( $bank_info['bank_account'] ?? '' ),
            'bank_holder'    => sanitize_text_field( $bank_info['bank_holder'] ?? '' ),
            'wallet_address' => sanitize_text_field( $bank_info['wallet_address'] ?? '' ),
            'status'         => 'pending',
            'source'         => 'referral',
            'created_at'     => sitetop_current_time(),
        ) );
        if ( ! $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'db_error', 'Lỗi tạo lệnh rút hoa hồng' );
        }
        $wid = $wpdb->insert_id;

        $wpdb->insert( "{$p}transactions", array(
            'user_id' => $user_id, 'amount' => -$amount, 'type' => 'withdraw',
            'reference_id' => $wid, 'reference_type' => 'withdrawal',
            'description' => 'Rút hoa hồng referral #' . $wid,
            'balance_after' => sitetop_get_user_balance_amount( $user_id ), // sổ nhiệm vụ, không đổi — chỉ để khớp quy ước cột này ở nơi khác
            'created_at' => sitetop_current_time(),
        ) );

        $wpdb->query( 'COMMIT' );

        if ( function_exists( 'sitetop_send_withdrawal_pending_email' ) ) {
            sitetop_send_withdrawal_pending_email( $wid );
        }
        if ( ! empty( $bank_info['bank_name'] ) ) update_user_meta( $user_id, 'sitetop_bank_name', sanitize_text_field( $bank_info['bank_name'] ) );
        if ( ! empty( $bank_info['bank_account'] ) ) update_user_meta( $user_id, 'sitetop_bank_account', sanitize_text_field( $bank_info['bank_account'] ) );
        if ( ! empty( $bank_info['bank_holder'] ) ) update_user_meta( $user_id, 'sitetop_bank_holder', sanitize_text_field( $bank_info['bank_holder'] ) );

        return $wid;
    } catch ( Exception $e ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'error', $e->getMessage() );
    }
}

add_action( 'wp_ajax_sitetop_referral_withdraw', 'sitetop_ajax_referral_withdraw' );
function sitetop_ajax_referral_withdraw() {
    check_ajax_referer( 'sitetop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );
    if ( function_exists( 'sitetop_block_advertiser_ajax' ) ) sitetop_block_advertiser_ajax();

    $result = sitetop_submit_referral_withdrawal( get_current_user_id(), floatval( $_POST['amount'] ?? 0 ),
        sanitize_text_field( $_POST['method'] ?? 'bank' ), array(
            'bank_name'      => sanitize_text_field( $_POST['bank_name'] ?? '' ),
            'bank_account'   => sanitize_text_field( $_POST['bank_account'] ?? '' ),
            'bank_holder'    => sanitize_text_field( $_POST['bank_holder'] ?? '' ),
            'wallet_address' => sanitize_text_field( $_POST['wallet_address'] ?? '' ),
        ) );
    if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );
    wp_send_json_success( array( 'withdrawal_id' => $result ) );
}
