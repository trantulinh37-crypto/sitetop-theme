<?php
/**
 * Duyệt "Nguồn file gốc" (source approval)
 * ------------------------------------------------------------------
 * User phải khai báo nguồn file gốc và được Admin duyệt trước khi được
 * rút gọn link (cả trong dashboard lẫn qua API).
 *
 *   Trạng thái:  none (chưa gửi) → pending (chờ duyệt) → approved | rejected
 *
 * Lưu bằng user meta, KHÔNG đụng schema DB — tránh rủi ro migrate trên
 * production. Cổng chặn đặt tại sitetop_create_user_shortlink() (điểm chốt
 * duy nhất của cả AJAX lẫn API) + thêm một lớp ở rest-api.php để chặn luôn
 * đường /st reuse link cũ.
 *
 * Tạo 22/08/2026.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

const SITETOP_SRC_META        = 'sitetop_src_value';
const SITETOP_SRC_STATUS      = 'sitetop_src_status';
const SITETOP_SRC_NOTE        = 'sitetop_src_note';
const SITETOP_SRC_SUBMITTED   = 'sitetop_src_submitted_at';
const SITETOP_SRC_REVIEWED    = 'sitetop_src_reviewed_at';
const SITETOP_SRC_REVIEWER    = 'sitetop_src_reviewed_by';

/** Telegram admin để user liên hệ duyệt nhanh (đổi được trong Cài đặt TT). */
function sitetop_source_telegram() {
    $tg = trim( (string) sitetop_get_option( 'source_telegram', '@sitetopnet' ) );
    if ( $tg === '' ) $tg = '@sitetopnet';
    return ltrim( $tg, '@' );
}

/** Câu nhắc dùng chung ở mọi nơi (dashboard, AJAX, API). */
function sitetop_source_hint_text() {
    return 'Muốn hoạt động nhanh, Inbox Admin Telegram @' . sitetop_source_telegram() . ' để được duyệt nguồn.';
}

/** Bật/tắt toàn cục — van an toàn để Admin gỡ chặn ngay mà không cần deploy. */
function sitetop_source_gate_enabled() {
    return (int) sitetop_get_option( 'require_source_approval', 1 ) === 1;
}

/** Trạng thái nguồn của 1 user: none | pending | approved | rejected */
function sitetop_get_source_status( $user_id = 0 ) {
    $user_id = $user_id ?: get_current_user_id();
    if ( ! $user_id ) return 'none';
    $st = (string) get_user_meta( $user_id, SITETOP_SRC_STATUS, true );
    return in_array( $st, array( 'pending', 'approved', 'rejected' ), true ) ? $st : 'none';
}

/** Nguồn user đã khai (chuỗi thô, mỗi dòng 1 link/mô tả). */
function sitetop_get_source_value( $user_id = 0 ) {
    $user_id = $user_id ?: get_current_user_id();
    return (string) get_user_meta( $user_id, SITETOP_SRC_META, true );
}

/** Lý do Admin từ chối (nếu có). */
function sitetop_get_source_note( $user_id = 0 ) {
    $user_id = $user_id ?: get_current_user_id();
    return (string) get_user_meta( $user_id, SITETOP_SRC_NOTE, true );
}

/**
 * Được phép rút gọn link chưa?
 * Admin và tài khoản quảng cáo không thuộc diện duyệt nguồn.
 */
function sitetop_source_is_approved( $user_id = 0 ) {
    if ( ! sitetop_source_gate_enabled() ) return true;
    $user_id = $user_id ?: get_current_user_id();
    if ( ! $user_id ) return false;
    if ( user_can( $user_id, 'manage_options' ) ) return true;
    if ( function_exists( 'sitetop_is_advertiser_account' ) ) {
        $u = get_user_by( 'id', $user_id );
        if ( $u && sitetop_is_advertiser_account( $u ) ) return true;
    }
    return sitetop_get_source_status( $user_id ) === 'approved';
}

/** Thông báo hiển thị khi bị chặn — theo đúng trạng thái. */
function sitetop_source_block_message( $user_id = 0 ) {
    $hint = sitetop_source_hint_text();
    switch ( sitetop_get_source_status( $user_id ) ) {
        case 'pending':
            return 'Nguồn file gốc của bạn đang chờ Admin duyệt. ' . $hint;
        case 'rejected':
            $note = sitetop_get_source_note( $user_id );
            return 'Nguồn file gốc đã bị từ chối' . ( $note ? ': ' . $note : '' ) . '. Vui lòng cập nhật lại nguồn. ' . $hint;
        default:
            return 'Bạn cần khai báo Nguồn file gốc và được Admin duyệt trước khi rút gọn link. ' . $hint;
    }
}

/* ============================================================
   USER GỬI / CẬP NHẬT NGUỒN
   ============================================================ */
function sitetop_submit_source( $user_id, $value ) {
    $user_id = (int) $user_id;
    if ( ! $user_id ) return new WP_Error( 'no_user', 'Chưa đăng nhập' );

    $value = trim( wp_strip_all_tags( (string) $value ) );
    if ( mb_strlen( $value ) < 8 ) {
        return new WP_Error( 'too_short', 'Vui lòng nhập nguồn file gốc (tối thiểu 8 ký tự) — link fanpage, group, website hoặc kênh của bạn.' );
    }
    if ( mb_strlen( $value ) > 1000 ) {
        return new WP_Error( 'too_long', 'Nguồn file gốc tối đa 1000 ký tự.' );
    }
    // Đã duyệt rồi thì không cho tự sửa (tránh duyệt nguồn sạch xong đổi sang nguồn bẩn).
    if ( sitetop_get_source_status( $user_id ) === 'approved' ) {
        return new WP_Error( 'already_approved', 'Nguồn của bạn đã được duyệt. Cần đổi nguồn, vui lòng liên hệ Admin Telegram @' . sitetop_source_telegram() . '.' );
    }

    update_user_meta( $user_id, SITETOP_SRC_META, $value );
    update_user_meta( $user_id, SITETOP_SRC_STATUS, 'pending' );
    update_user_meta( $user_id, SITETOP_SRC_SUBMITTED, sitetop_current_time() );
    delete_user_meta( $user_id, SITETOP_SRC_NOTE );

    do_action( 'sitetop_source_submitted', $user_id, $value );
    return true;
}

add_action( 'wp_ajax_sitetop_submit_source', 'sitetop_ajax_submit_source' );
function sitetop_ajax_submit_source() {
    check_ajax_referer( 'sitetop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );
    if ( function_exists( 'sitetop_block_advertiser_ajax' ) ) sitetop_block_advertiser_ajax();

    $rate = sitetop_rate_limit_check( 'report_issue' ); // 5 lần / 5 phút — đủ rộng, chặn spam
    if ( empty( $rate['allowed'] ) ) wp_send_json_error( 'Quá nhiều yêu cầu, thử lại sau ít phút.' );

    $result = sitetop_submit_source( get_current_user_id(), wp_unslash( $_POST['source'] ?? '' ) );
    if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

    wp_send_json_success( array(
        'status'  => 'pending',
        'message' => 'Đã gửi nguồn file gốc. ' . sitetop_source_hint_text(),
    ) );
}

/* ============================================================
   ADMIN DUYỆT / TỪ CHỐI
   ============================================================ */
function sitetop_review_source( $user_id, $decision, $note = '' ) {
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_sitetop_users' ) ) {
        return new WP_Error( 'forbidden', 'Không có quyền' );
    }
    $user_id = (int) $user_id;
    if ( ! $user_id || ! get_user_by( 'id', $user_id ) ) return new WP_Error( 'no_user', 'User không tồn tại' );

    if ( $decision === 'approve' ) {
        update_user_meta( $user_id, SITETOP_SRC_STATUS, 'approved' );
        delete_user_meta( $user_id, SITETOP_SRC_NOTE );
    } elseif ( $decision === 'reject' ) {
        update_user_meta( $user_id, SITETOP_SRC_STATUS, 'rejected' );
        update_user_meta( $user_id, SITETOP_SRC_NOTE, trim( wp_strip_all_tags( (string) $note ) ) );
    } else {
        return new WP_Error( 'bad_decision', 'Hành động không hợp lệ' );
    }

    update_user_meta( $user_id, SITETOP_SRC_REVIEWED, sitetop_current_time() );
    update_user_meta( $user_id, SITETOP_SRC_REVIEWER, get_current_user_id() );

    do_action( 'sitetop_source_reviewed', $user_id, $decision, $note );
    return true;
}

add_action( 'wp_ajax_sitetop_admin_review_source', 'sitetop_ajax_admin_review_source' );
function sitetop_ajax_admin_review_source() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    $result = sitetop_review_source(
        (int) ( $_POST['user_id'] ?? 0 ),
        sanitize_text_field( $_POST['decision'] ?? '' ),
        wp_unslash( $_POST['note'] ?? '' )
    );
    if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );
    wp_send_json_success( array( 'message' => 'Đã cập nhật trạng thái nguồn.' ) );
}

/** Đếm số nguồn đang chờ duyệt — dùng cho badge trên menu admin. */
function sitetop_count_pending_sources() {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = 'pending'",
        SITETOP_SRC_STATUS
    ) );
}

/* ============================================================
   BÁO TELEGRAM ADMIN KHI USER GỬI NGUỒN
   Dùng lại bot ở includes/telegram-notifications.php (cùng token/chat_id
   với báo nạp tiền, rút tiền, chiến dịch chờ duyệt). Gửi non-blocking nên
   KHÔNG làm chậm request của user.
   ============================================================ */
add_action( 'sitetop_source_submitted', 'sitetop_notify_source_submitted', 10, 2 );
function sitetop_notify_source_submitted( $user_id, $value ) {
    if ( ! function_exists( 'sitetop_report_telegram_configured' ) ) return;
    if ( ! sitetop_report_telegram_configured() ) return;

    $u = get_user_by( 'id', $user_id );
    if ( ! $u ) return;

    // Nguồn có thể dài 1000 ký tự — cắt bớt cho tin nhắn dễ đọc, bản đầy đủ xem ở trang duyệt.
    $short = trim( preg_replace( '/[ \t]+/', ' ', $value ) );
    if ( mb_strlen( $short ) > 350 ) $short = mb_substr( $short, 0, 350 ) . '…';

    $pending = sitetop_count_pending_sources();

    sitetop_telegram_notify_admin( '📄 Nguồn file gốc mới cần duyệt', array(
        'User'       => ( $u->display_name ?: $u->user_login ) . ' (#' . $user_id . ')',
        'Email'      => $u->user_email,
        'Nguồn'      => "\n" . $short,
        'Đang chờ'   => $pending . ' nguồn',
        'Duyệt tại'  => admin_url( 'admin.php?page=sitetop-sources' ),
    ) );
}
