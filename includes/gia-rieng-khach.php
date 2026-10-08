<?php
/**
 * GIÁ RIÊNG TỪNG KHÁCH HÀNG — 08/10/2026 (chủ site: "Thêm chức năng cài Rate riêng cho từng tài khoản khách hàng").
 *
 *  - Giá mặc định của hệ thống (Cài đặt → "Giá khách hàng trả (đ/lượt)") vẫn là GIÁ GỐC.
 *  - Admin đặt giá riêng cho MỘT tài khoản khách theo 6 loại camp (Keyword / Direct × 1 bước / 2 bước / Mã cố định),
 *    đơn vị đ/lượt, CHƯA gồm phụ phí onsite (phụ phí vẫn cộng như cũ). Bỏ trống loại nào thì loại đó ăn giá gốc.
 *    Lưu ở user meta `sitetop_gia_rieng` của tài khoản khách — khách này không ảnh hưởng khách khác.
 *  - Khi tạo camp (khách tự tạo, admin tạo hộ) hay sửa đổi loại/onsite, máy chủ tự áp giá riêng nếu có, không thì giá
 *    gốc. Giá vẫn đóng băng vào camp (price_per_view) như trước: đổi giá riêng không đụng camp đã tạo.
 *  - Thưởng user (sitetop_user_reward_cho_camp) không đổi; mọi luồng khác giữ nguyên.
 *
 * Cùng khuôn với "Rate riêng từng user" (includes/admin-dashboard.php, 06/10/2026) nhưng ở sổ KHÁCH HÀNG (VNĐ).
 * Giao diện: tab Khách hàng → nút "Giá riêng" (includes/admin/tabs/tab-customers.php). File này giống nhau hai theme.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Sáu loại camp — khoá trùng với "rate riêng user" để admin quen tay. */
function sitetop_gia_rieng_cac_loai() {
    return array(
        'keyword_1step'  => 'Keyword 1 bước',
        'keyword_2step'  => 'Keyword 2 bước',
        'keyword_nocode' => 'Keyword Mã cố định',
        'direct_1step'   => 'Direct 1 bước',
        'direct_2step'   => 'Direct 2 bước',
        'direct_nocode'  => 'Direct Mã cố định',
    );
}

/** keyword_search/traffic_direct + 1step/2step/nocode → 'keyword_1step'… (loại lạ → 1step, như mọi chỗ khác). */
function sitetop_gia_rieng_khoa( $task_type, $traffic_type ) {
    $tt = in_array( (string) $traffic_type, array( '1step', '2step', 'nocode' ), true ) ? (string) $traffic_type : '1step';
    return ( $task_type === 'traffic_direct' ? 'direct' : 'keyword' ) . '_' . $tt;
}

/** GIÁ GỐC của hệ thống (đ/lượt, chưa phụ phí onsite) — đúng option và mặc định các chỗ cũ đang dùng. */
function sitetop_gia_goc( $task_type, $traffic_type ) {
    $khoa = sitetop_gia_rieng_khoa( $task_type, $traffic_type );
    $nhom = substr( $khoa, 0, strpos( $khoa, '_' ) );
    $tt   = substr( $khoa, strpos( $khoa, '_' ) + 1 );
    $mac  = array( '1step' => 1200, '2step' => ( $nhom === 'keyword' ? 1500 : 1200 ), 'nocode' => 1200 );
    return (float) sitetop_get_option( $nhom . '_price_' . $tt, $mac[ $tt ] );
}

/** Bảng giá riêng đã đặt cho một khách (mảng khoá → đ/lượt), rỗng nếu chưa đặt. */
function sitetop_gia_rieng_bang( $customer_id ) {
    if ( (int) $customer_id <= 0 ) return array();
    $b = get_user_meta( (int) $customer_id, 'sitetop_gia_rieng', true );
    return is_array( $b ) ? $b : array();
}

/** Giá riêng của khách cho một loại camp. 0 = chưa đặt (dùng giá gốc). */
function sitetop_gia_rieng_cua_khach( $customer_id, $task_type, $traffic_type ) {
    $bang = sitetop_gia_rieng_bang( $customer_id );
    $khoa = sitetop_gia_rieng_khoa( $task_type, $traffic_type );
    $muc  = isset( $bang[ $khoa ] ) ? (float) $bang[ $khoa ] : 0.0;
    return $muc > 0 ? $muc : 0.0;
}

function sitetop_khach_co_gia_rieng( $customer_id ) {
    foreach ( sitetop_gia_rieng_bang( $customer_id ) as $v ) if ( (float) $v > 0 ) return true;
    return false;
}

/** Giá cơ bản áp cho khách này: giá riêng nếu đã đặt, không thì giá gốc. ĐÂY là hàm mọi chỗ tính giá phải gọi. */
function sitetop_gia_co_ban_cho_khach( $customer_id, $task_type, $traffic_type ) {
    $rieng = sitetop_gia_rieng_cua_khach( $customer_id, $task_type, $traffic_type );
    return $rieng > 0 ? $rieng : sitetop_gia_goc( $task_type, $traffic_type );
}

/** Phụ phí onsite (đ/lượt) — cùng option và mặc định với bảng cũ ở customer-campaign-ajax.php. */
function sitetop_phu_phi_onsite( $onsite ) {
    $mac    = array( 70 => 0, 80 => 100, 90 => 200, 100 => 300, 120 => 400, 150 => 500 );
    $onsite = (int) $onsite;
    if ( ! isset( $mac[ $onsite ] ) ) return 0;
    return (int) sitetop_get_option( 'onsite_extra_' . $onsite, $mac[ $onsite ] );
}

/** Giá/lượt trọn (cơ bản + phụ phí onsite) cho khách này. */
function sitetop_gia_cho_khach( $customer_id, $task_type, $traffic_type, $onsite ) {
    return sitetop_gia_co_ban_cho_khach( $customer_id, $task_type, $traffic_type ) + sitetop_phu_phi_onsite( $onsite );
}

/** Bảng giá cơ bản cho JS (trang khách / modal admin): ['keyword_search'=>['1step'=>…,…],'traffic_direct'=>[…]] (int). */
function sitetop_bang_gia_cho_khach( $customer_id ) {
    $ra = array();
    foreach ( array( 'keyword_search', 'traffic_direct' ) as $ct ) {
        foreach ( array( '1step', '2step', 'nocode' ) as $tt ) {
            $ra[ $ct ][ $tt ] = (int) round( sitetop_gia_co_ban_cho_khach( $customer_id, $ct, $tt ) );
        }
    }
    return $ra;
}

/** Form tạo camp của admin: bảng giá của những khách CÓ giá riêng, theo id. Khách thường không có mục → JS dùng bảng gốc. */
function sitetop_bang_gia_khach_co_rieng( $ids ) {
    $ra = array();
    foreach ( (array) $ids as $id ) {
        $id = (int) $id;
        if ( $id > 0 && sitetop_khach_co_gia_rieng( $id ) ) $ra[ $id ] = sitetop_bang_gia_cho_khach( $id );
    }
    return $ra;
}

/* ============================================================
   ADMIN LƯU GIÁ RIÊNG (AJAX từ modal ở tab Khách hàng)
   ============================================================ */
add_action( 'wp_ajax_sitetop_admin_gia_rieng', 'sitetop_ajax_admin_gia_rieng' );
function sitetop_ajax_admin_gia_rieng() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Không có quyền' );

    $uid = absint( $_POST['user_id'] ?? 0 );
    $u   = $uid ? get_userdata( $uid ) : false;
    if ( ! $u ) wp_send_json_error( 'Không tìm thấy tài khoản' );

    $moi = array();
    foreach ( array_keys( sitetop_gia_rieng_cac_loai() ) as $khoa ) {
        $v = isset( $_POST[ 'gia_' . $khoa ] ) ? trim( (string) $_POST[ 'gia_' . $khoa ] ) : '';
        if ( $v === '' ) continue;                                   // bỏ trống = theo giá gốc
        $v = absint( str_replace( array( '.', ',', ' ' ), '', $v ) ); // nhận "2.000" lẫn "2000"
        if ( $v < 1 ) continue;                                      // 0 cũng là bỏ đặt
        /* Trần một lần đặt — gõ thừa số 0 là khách bị trừ gấp mười mỗi lượt; chặn ở đây rẻ hơn đi hoàn tiền. */
        if ( $v > 1000000 ) wp_send_json_error( 'Giá tối đa 1.000.000đ/lượt' );
        $moi[ $khoa ] = $v;
    }

    if ( $moi ) update_user_meta( $uid, 'sitetop_gia_rieng', $moi );
    else        delete_user_meta( $uid, 'sitetop_gia_rieng' );

    wp_send_json_success( array(
        'so_loai' => count( $moi ),
        'tin'     => $moi
                     ? sprintf( 'Đã đặt giá riêng cho %s (%d loại camp). Áp cho camp tạo/sửa từ giờ; camp cũ giữ giá đã chốt.', $u->user_login, count( $moi ) )
                     : sprintf( 'Đã bỏ giá riêng của %s — về giá gốc.', $u->user_login ),
    ) );
}
