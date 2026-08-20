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
    );
}
