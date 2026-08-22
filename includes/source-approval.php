<?php
/**
 * Duyệt "Nguồn file gốc" (source approval)
 * ------------------------------------------------------------------
 * User khai báo DANH SÁCH nguồn file gốc; mỗi nguồn có trạng thái riêng
 * (chờ duyệt / đã duyệt / từ chối). User tự thêm nguồn mới và xoá nguồn cũ.
 *
 * QUY TẮC CHO PHÉP RÚT GỌN LINK:
 *   còn ÍT NHẤT 1 nguồn ĐÃ DUYỆT → được rút gọn link + dùng API.
 *   Thêm nguồn mới KHÔNG làm mất quyền đang có (tránh phạt user vì khai thêm);
 *   nhưng xoá hết nguồn đã duyệt thì bị khoá ngay.
 *
 * Lưu bằng user meta, KHÔNG đụng schema DB.
 *   sitetop_src_items   mảng nguồn (nguồn chuẩn, quyết định mọi thứ)
 *   sitetop_src_status  trạng thái tổng hợp — ghi kèm để admin lọc bằng SQL
 *   sitetop_src_pending '1' khi còn nguồn chờ duyệt — hàng đợi của admin
 *
 * Tạo 22/08/2026. Chuyển từ 1 ô văn bản sang danh sách 22/08/2026.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

const SITETOP_SRC_ITEMS     = 'sitetop_src_items';
const SITETOP_SRC_STATUS    = 'sitetop_src_status';   // tổng hợp, cho admin lọc
const SITETOP_SRC_PENDING   = 'sitetop_src_pending';  // '1' nếu còn nguồn chờ duyệt
const SITETOP_SRC_MAX       = 10;                     // trần số nguồn mỗi user
const SITETOP_SRC_MAX_LEN   = 20000;                  // trần kỹ thuật chống nhồi dữ liệu, KHÔNG phải giới hạn dùng

/* ── cột cũ (bản 1 ô văn bản) — chỉ còn dùng để chuyển đổi dữ liệu ── */
const SITETOP_SRC_LEGACY_VALUE = 'sitetop_src_value';
const SITETOP_SRC_LEGACY_NOTE  = 'sitetop_src_note';

/* ============================================================
   CẤU HÌNH
   ============================================================ */
function sitetop_source_telegram() {
    $tg = trim( (string) sitetop_get_option( 'source_telegram', '@sitetopnet' ) );
    if ( $tg === '' ) $tg = '@sitetopnet';
    return ltrim( $tg, '@' );
}

function sitetop_source_hint_text() {
    return 'Muốn hoạt động nhanh, Inbox Admin Telegram @' . sitetop_source_telegram() . ' để được duyệt nguồn.';
}

function sitetop_source_gate_enabled() {
    return (int) sitetop_get_option( 'require_source_approval', 1 ) === 1;
}

/**
 * Tài khoản KHÔNG thuộc diện duyệt nguồn: Admin (nếu áp thì admin tự khoá mình
 * khỏi hệ thống) và tài khoản quảng cáo (họ mua traffic, không rút gọn link).
 */
function sitetop_source_is_exempt( $user_id = 0 ) {
    $user_id = $user_id ?: get_current_user_id();
    if ( ! $user_id ) return false;
    if ( user_can( $user_id, 'manage_options' ) ) return true;
    if ( function_exists( 'sitetop_is_advertiser_account' ) ) {
        $u = get_user_by( 'id', $user_id );
        if ( $u && sitetop_is_advertiser_account( $u ) ) return true;
    }
    return false;
}

/* ============================================================
   DANH SÁCH NGUỒN
   ============================================================ */
/**
 * Đọc danh sách nguồn. Tự chuyển đổi dữ liệu bản cũ (1 ô văn bản nhiều dòng)
 * sang danh sách ở lần đọc đầu tiên — user cũ không mất nguồn đã khai.
 */
function sitetop_get_source_items( $user_id = 0 ) {
    $user_id = $user_id ?: get_current_user_id();
    if ( ! $user_id ) return array();

    $items = get_user_meta( $user_id, SITETOP_SRC_ITEMS, true );
    if ( is_array( $items ) ) return $items;

    // ── chuyển đổi từ bản cũ ──
    $legacy = (string) get_user_meta( $user_id, SITETOP_SRC_LEGACY_VALUE, true );
    if ( trim( $legacy ) === '' ) return array();

    $status = (string) get_user_meta( $user_id, SITETOP_SRC_STATUS, true );
    if ( ! in_array( $status, array( 'pending', 'approved', 'rejected' ), true ) ) $status = 'pending';
    $note   = (string) get_user_meta( $user_id, SITETOP_SRC_LEGACY_NOTE, true );
    $when   = (string) get_user_meta( $user_id, 'sitetop_src_submitted_at', true ) ?: sitetop_current_time();

    $items = array();
    foreach ( preg_split( '/\R/u', $legacy ) as $line ) {
        $line = trim( $line );
        if ( $line === '' ) continue;
        $items[] = array(
            'id'       => sitetop_source_new_id(),
            'text'     => $line,
            'status'   => $status,
            'added_at' => $when,
            'note'     => $status === 'rejected' ? $note : '',
        );
    }
    if ( ! $items ) return array();

    update_user_meta( $user_id, SITETOP_SRC_ITEMS, $items );
    sitetop_sync_source_status( $user_id );
    return $items;
}

function sitetop_source_new_id() {
    return substr( md5( uniqid( '', true ) ), 0, 10 );
}

function sitetop_save_source_items( $user_id, $items ) {
    update_user_meta( $user_id, SITETOP_SRC_ITEMS, array_values( $items ) );
    sitetop_sync_source_status( $user_id );
}

/**
 * Ghi lại trạng thái tổng hợp ra meta để trang admin lọc/đếm bằng SQL được.
 * Trạng thái tổng hợp phản ánh CÁI USER QUAN TÂM: có làm việc được không.
 */
function sitetop_sync_source_status( $user_id ) {
    $items = get_user_meta( $user_id, SITETOP_SRC_ITEMS, true );
    $items = is_array( $items ) ? $items : array();

    $has = array( 'approved' => false, 'pending' => false, 'rejected' => false );
    foreach ( $items as $it ) {
        $st = $it['status'] ?? 'pending';
        if ( isset( $has[ $st ] ) ) $has[ $st ] = true;
    }

    if ( ! $items )               $status = 'none';
    elseif ( $has['approved'] )   $status = 'approved';   // còn nguồn duyệt → vẫn làm việc được
    elseif ( $has['pending'] )    $status = 'pending';
    else                          $status = 'rejected';

    update_user_meta( $user_id, SITETOP_SRC_STATUS, $status );

    if ( $has['pending'] ) update_user_meta( $user_id, SITETOP_SRC_PENDING, '1' );
    else                   delete_user_meta( $user_id, SITETOP_SRC_PENDING );

    return $status;
}

/** Trạng thái tổng hợp: none | pending | approved | rejected */
function sitetop_get_source_status( $user_id = 0 ) {
    $user_id = $user_id ?: get_current_user_id();
    if ( ! $user_id ) return 'none';
    sitetop_get_source_items( $user_id );   // kích hoạt chuyển đổi nếu cần
    $st = (string) get_user_meta( $user_id, SITETOP_SRC_STATUS, true );
    return in_array( $st, array( 'pending', 'approved', 'rejected' ), true ) ? $st : 'none';
}

/** Còn nguồn nào đang chờ duyệt không (hàng đợi admin). */
function sitetop_source_has_pending( $user_id = 0 ) {
    $user_id = $user_id ?: get_current_user_id();
    foreach ( sitetop_get_source_items( $user_id ) as $it ) {
        if ( ( $it['status'] ?? '' ) === 'pending' ) return true;
    }
    return false;
}

/** Gộp danh sách nguồn thành chuỗi (dùng cho tin nhắn Telegram). */
function sitetop_get_source_value( $user_id = 0 ) {
    $lines = array();
    foreach ( sitetop_get_source_items( $user_id ) as $it ) $lines[] = $it['text'];
    return implode( "\n", $lines );
}

/* ============================================================
   CỔNG CHẶN
   ============================================================ */
/** Được rút gọn link chưa? — còn ít nhất 1 nguồn ĐÃ DUYỆT là được. */
function sitetop_source_is_approved( $user_id = 0 ) {
    if ( ! sitetop_source_gate_enabled() ) return true;
    $user_id = $user_id ?: get_current_user_id();
    if ( ! $user_id ) return false;
    if ( sitetop_source_is_exempt( $user_id ) ) return true;

    foreach ( sitetop_get_source_items( $user_id ) as $it ) {
        if ( ( $it['status'] ?? '' ) === 'approved' ) return true;
    }
    return false;
}

/** Thông báo khi bị chặn — theo đúng tình trạng danh sách nguồn. */
function sitetop_source_block_message( $user_id = 0 ) {
    $hint = sitetop_source_hint_text();
    switch ( sitetop_get_source_status( $user_id ) ) {
        case 'pending':
            return 'Nguồn file gốc của bạn đang chờ Admin duyệt. ' . $hint;
        case 'rejected':
            $note = '';
            foreach ( sitetop_get_source_items( $user_id ) as $it ) {
                if ( ( $it['status'] ?? '' ) === 'rejected' && ! empty( $it['note'] ) ) { $note = $it['note']; break; }
            }
            return 'Nguồn file gốc đã bị từ chối' . ( $note ? ': ' . $note : '' ) . '. Vui lòng khai nguồn khác. ' . $hint;
        default:
            return 'Bạn cần khai báo Nguồn file gốc và được Admin duyệt trước khi rút gọn link. ' . $hint;
    }
}

/* ============================================================
   USER: THÊM / XOÁ NGUỒN
   ============================================================ */
function sitetop_add_source_item( $user_id, $text ) {
    $user_id = (int) $user_id;
    if ( ! $user_id ) return new WP_Error( 'no_user', 'Chưa đăng nhập' );

    $text = trim( wp_strip_all_tags( (string) $text ) );
    $text = preg_replace( '/\s+/u', ' ', $text );
    if ( mb_strlen( $text ) < 8 ) {
        return new WP_Error( 'too_short', 'Nguồn quá ngắn (tối thiểu 8 ký tự) — nhập link fanpage, group, website hoặc kênh của bạn.' );
    }
    // Không giới hạn độ dài nguồn (theo yêu cầu 22/08/2026). Vẫn giữ một trần kỹ
    // thuật rất cao để một POST hỏng/cố ý không nhét được vài MB vào usermeta —
    // ngưỡng này nằm ngoài mọi nhu cầu khai báo thật.
    if ( mb_strlen( $text ) > SITETOP_SRC_MAX_LEN ) {
        return new WP_Error( 'too_long', 'Nguồn dài bất thường. Vui lòng rút gọn lại.' );
    }

    $items = sitetop_get_source_items( $user_id );
    if ( count( $items ) >= SITETOP_SRC_MAX ) {
        return new WP_Error( 'too_many', 'Tối đa ' . SITETOP_SRC_MAX . ' nguồn. Xoá bớt nguồn cũ trước khi thêm mới.' );
    }
    foreach ( $items as $it ) {
        if ( mb_strtolower( $it['text'] ) === mb_strtolower( $text ) ) {
            return new WP_Error( 'duplicate', 'Nguồn này bạn đã khai rồi.' );
        }
    }

    $items[] = array(
        'id'       => sitetop_source_new_id(),
        'text'     => $text,
        'status'   => 'pending',
        'added_at' => sitetop_current_time(),
        'note'     => '',
    );
    sitetop_save_source_items( $user_id, $items );

    do_action( 'sitetop_source_submitted', $user_id, $text );
    return true;
}

function sitetop_delete_source_item( $user_id, $item_id ) {
    $user_id = (int) $user_id;
    if ( ! $user_id ) return new WP_Error( 'no_user', 'Chưa đăng nhập' );

    $items = sitetop_get_source_items( $user_id );
    $kept  = array();
    $gone  = null;
    foreach ( $items as $it ) {
        if ( ( $it['id'] ?? '' ) === $item_id ) { $gone = $it; continue; }
        $kept[] = $it;
    }
    if ( ! $gone ) return new WP_Error( 'not_found', 'Không tìm thấy nguồn này.' );

    sitetop_save_source_items( $user_id, $kept );

    do_action( 'sitetop_source_deleted', $user_id, $gone );
    return array(
        'status'       => sitetop_get_source_status( $user_id ),
        'can_shorten'  => sitetop_source_is_approved( $user_id ),
    );
}

/* ── AJAX: user thêm nguồn ── */
add_action( 'wp_ajax_sitetop_add_source', 'sitetop_ajax_add_source' );
function sitetop_ajax_add_source() {
    check_ajax_referer( 'sitetop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );
    if ( function_exists( 'sitetop_block_advertiser_ajax' ) ) sitetop_block_advertiser_ajax();

    $rate = sitetop_rate_limit_check( 'report_issue' );
    if ( empty( $rate['allowed'] ) ) wp_send_json_error( 'Quá nhiều yêu cầu, thử lại sau ít phút.' );

    $r = sitetop_add_source_item( get_current_user_id(), wp_unslash( $_POST['source'] ?? '' ) );
    if ( is_wp_error( $r ) ) wp_send_json_error( $r->get_error_message() );

    wp_send_json_success( array( 'message' => 'Đã thêm nguồn, đang chờ Admin duyệt. ' . sitetop_source_hint_text() ) );
}

/* ── AJAX: user xoá nguồn ── */
add_action( 'wp_ajax_sitetop_delete_source', 'sitetop_ajax_delete_source' );
function sitetop_ajax_delete_source() {
    check_ajax_referer( 'sitetop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );
    if ( function_exists( 'sitetop_block_advertiser_ajax' ) ) sitetop_block_advertiser_ajax();

    $r = sitetop_delete_source_item( get_current_user_id(), sanitize_text_field( $_POST['item_id'] ?? '' ) );
    if ( is_wp_error( $r ) ) wp_send_json_error( $r->get_error_message() );

    $msg = $r['can_shorten']
        ? 'Đã xoá nguồn.'
        : 'Đã xoá nguồn. Bạn không còn nguồn nào được duyệt nên tạm thời không rút gọn link được — hãy thêm nguồn mới.';
    wp_send_json_success( array( 'message' => $msg, 'can_shorten' => $r['can_shorten'] ) );
}

/* ============================================================
   ADMIN DUYỆT / TỪ CHỐI
   ============================================================ */
/**
 * $item_id rỗng = xử lý TẤT CẢ nguồn đang chờ duyệt của user đó (duyệt gộp).
 */
function sitetop_review_source( $user_id, $decision, $note = '', $item_id = '' ) {
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_sitetop_users' ) ) {
        return new WP_Error( 'forbidden', 'Không có quyền' );
    }
    if ( ! in_array( $decision, array( 'approve', 'reject' ), true ) ) {
        return new WP_Error( 'bad_decision', 'Hành động không hợp lệ' );
    }
    $user_id = (int) $user_id;
    if ( ! $user_id || ! get_user_by( 'id', $user_id ) ) return new WP_Error( 'no_user', 'User không tồn tại' );

    $items = sitetop_get_source_items( $user_id );
    if ( ! $items ) return new WP_Error( 'no_items', 'User chưa khai nguồn nào.' );

    $note    = trim( wp_strip_all_tags( (string) $note ) );
    $new_st  = $decision === 'approve' ? 'approved' : 'rejected';
    $touched = 0;

    foreach ( $items as &$it ) {
        $match = $item_id ? ( ( $it['id'] ?? '' ) === $item_id ) : ( ( $it['status'] ?? '' ) === 'pending' );
        if ( ! $match ) continue;
        $it['status']      = $new_st;
        $it['note']        = $decision === 'reject' ? $note : '';
        $it['reviewed_at'] = sitetop_current_time();
        $it['reviewed_by'] = get_current_user_id();
        $touched++;
    }
    unset( $it );

    if ( ! $touched ) return new WP_Error( 'nothing', 'Không có nguồn nào phù hợp để xử lý.' );

    sitetop_save_source_items( $user_id, $items );
    do_action( 'sitetop_source_reviewed', $user_id, $decision, $note, $touched );
    return $touched;
}

add_action( 'wp_ajax_sitetop_admin_review_source', 'sitetop_ajax_admin_review_source' );
function sitetop_ajax_admin_review_source() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    $r = sitetop_review_source(
        (int) ( $_POST['user_id'] ?? 0 ),
        sanitize_text_field( $_POST['decision'] ?? '' ),
        wp_unslash( $_POST['note'] ?? '' ),
        sanitize_text_field( $_POST['item_id'] ?? '' )
    );
    if ( is_wp_error( $r ) ) wp_send_json_error( $r->get_error_message() );
    wp_send_json_success( array( 'message' => 'Đã cập nhật ' . $r . ' nguồn.' ) );
}

/** Đếm user còn nguồn chờ duyệt — cho badge trên menu admin. */
function sitetop_count_pending_sources() {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = '1'",
        SITETOP_SRC_PENDING
    ) );
}

/* ============================================================
   BÁO TELEGRAM ADMIN
   Dùng lại bot ở includes/telegram-notifications.php (cùng token/chat_id với
   báo nạp tiền, rút tiền, chiến dịch). Gửi non-blocking → không làm chậm user.
   ============================================================ */
add_action( 'sitetop_source_submitted', 'sitetop_notify_source_submitted', 10, 2 );
function sitetop_notify_source_submitted( $user_id, $text ) {
    if ( ! function_exists( 'sitetop_report_telegram_configured' ) ) return;
    if ( ! sitetop_report_telegram_configured() ) return;
    $u = get_user_by( 'id', $user_id );
    if ( ! $u ) return;

    if ( mb_strlen( $text ) > 350 ) $text = mb_substr( $text, 0, 350 ) . '…';

    sitetop_telegram_notify_admin( '📄 Nguồn file gốc mới cần duyệt', array(
        'User'      => ( $u->display_name ?: $u->user_login ) . ' (#' . $user_id . ')',
        'Email'     => $u->user_email,
        'Nguồn mới' => "\n" . $text,
        'Đang chờ'  => sitetop_count_pending_sources() . ' user',
        'Duyệt tại' => admin_url( 'admin.php?page=sitetop-sources' ),
    ) );
}

add_action( 'sitetop_source_deleted', 'sitetop_notify_source_deleted', 10, 2 );
function sitetop_notify_source_deleted( $user_id, $item ) {
    if ( ! function_exists( 'sitetop_report_telegram_configured' ) ) return;
    if ( ! sitetop_report_telegram_configured() ) return;
    $u = get_user_by( 'id', $user_id );
    if ( ! $u ) return;

    $labels = array( 'approved' => 'Đã duyệt', 'pending' => 'Chờ duyệt', 'rejected' => 'Từ chối' );
    $text   = (string) ( $item['text'] ?? '' );
    if ( mb_strlen( $text ) > 350 ) $text = mb_substr( $text, 0, 350 ) . '…';

    $rows = array(
        'User'          => ( $u->display_name ?: $u->user_login ) . ' (#' . $user_id . ')',
        'Nguồn bị xoá'  => "\n" . $text,
        'Trạng thái cũ' => $labels[ $item['status'] ?? '' ] ?? ( $item['status'] ?? '' ),
    );
    // Xoá hết nguồn đã duyệt = user tự khoá mình → nêu rõ để admin để mắt.
    if ( ! sitetop_source_is_approved( $user_id ) ) {
        $rows['Cảnh báo'] = 'Không còn nguồn đã duyệt — user đang bị khoá rút gọn link.';
    }
    $rows['Xem tại'] = admin_url( 'admin.php?page=sitetop-sources&src=approved' );

    sitetop_telegram_notify_admin( '🗑 User vừa xoá một nguồn file gốc', $rows );
}
