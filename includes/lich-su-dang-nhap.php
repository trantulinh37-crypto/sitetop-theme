<?php
/**
 * LỊCH SỬ ĐĂNG NHẬP TÀI KHOẢN (16/11/2026, chủ site: "thêm mục xem lịch sử đăng nhập của khách hàng").
 *
 * Ghi: mỗi lần đăng nhập thành công (hook wp_login — form /dang-nhap gọi wp_signon) và mỗi lần
 * admin "Đăng nhập với tư cách" (ghi riêng kiểu 'admin' để không lẫn với khách tự vào).
 * Thêm từ 16/11 chiều: mỗi lượt khách VÀO tài khoản (sitetop_lsdn_truy_cap, xem dưới).
 * Lưu ở usermeta `sitetop_lich_su_dn` (mảng, mới nhất trước, tối đa SITETOP_LSDN_TOI_DA dòng)
 * → không thêm bảng, không đổi cấu trúc CSDL. Đồng thời ghi `sitetop_last_login` — ô
 * "đăng nhập hôm nay" ở tab Khách hàng / Người dùng đọc trường này nhưng trước nay chưa từng
 * có chỗ ghi (luôn 0).
 *
 * Xem: tab Khách hàng → nút đồng hồ ở cột Thao tác (ajax sitetop_admin_lich_su_dn, chỉ admin).
 * File dùng chung .net và .one.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'SITETOP_LSDN_TOI_DA' ) ) define( 'SITETOP_LSDN_TOI_DA', 200 );
/** Hai lần vào cách nhau ≥ chừng này giây (hoặc đổi IP/thiết bị) thì tính là lần truy cập MỚI. */
if ( ! defined( 'SITETOP_LSDN_KHOANG' ) ) define( 'SITETOP_LSDN_KHOANG', 30 * 60 );

/** Tên thiết bị + trình duyệt gọn từ User-Agent (chỉ để admin đọc, không dùng để chặn). */
function sitetop_lsdn_thiet_bi( $ua ) {
    $ua = (string) $ua;
    if ( $ua === '' ) return 'Không rõ';
    $os = 'Khác';
    if ( preg_match( '/iPad/i', $ua ) ) $os = 'iPad';
    elseif ( preg_match( '/iPhone|iPod/i', $ua ) ) $os = 'iPhone';
    elseif ( preg_match( '/Android/i', $ua ) ) $os = 'Android';
    elseif ( preg_match( '/Windows/i', $ua ) ) $os = 'Windows';
    elseif ( preg_match( '/Macintosh|Mac OS X/i', $ua ) ) $os = 'Mac';
    elseif ( preg_match( '/CrOS/i', $ua ) ) $os = 'ChromeOS';
    elseif ( preg_match( '/Linux/i', $ua ) ) $os = 'Linux';
    $tb = 'Khác';
    if ( preg_match( '/coc_coc|CocCoc/i', $ua ) ) $tb = 'Cốc Cốc';
    elseif ( preg_match( '/Edg(e|A|iOS)?\//i', $ua ) ) $tb = 'Edge';
    elseif ( preg_match( '/OPR\/|Opera/i', $ua ) ) $tb = 'Opera';
    elseif ( preg_match( '/SamsungBrowser/i', $ua ) ) $tb = 'Samsung Internet';
    elseif ( preg_match( '/Zalo/i', $ua ) ) $tb = 'Zalo';
    elseif ( preg_match( '/FBAN|FBAV|FB_IAB/i', $ua ) ) $tb = 'Facebook';
    elseif ( preg_match( '/Firefox|FxiOS/i', $ua ) ) $tb = 'Firefox';
    elseif ( preg_match( '/Chrome|CriOS/i', $ua ) ) $tb = 'Chrome';
    elseif ( preg_match( '/Safari/i', $ua ) ) $tb = 'Safari';
    return $os . ' · ' . $tb;
}

/** Ghi một dòng lịch sử. $kieu: 'dn' = tự đăng nhập, 'admin' = admin đăng nhập hộ. */
function sitetop_lsdn_ghi( $user_id, $kieu = 'dn', $admin_id = 0 ) {
    $user_id = (int) $user_id;
    if ( $user_id <= 0 ) return;
    $gio = function_exists( 'sitetop_current_time' ) ? sitetop_current_time() : current_time( 'mysql' );
    $ip  = function_exists( 'sitetop_get_real_ip' ) ? sitetop_get_real_ip() : sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
    $ua  = substr( sanitize_text_field( wp_unslash( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) ), 0, 300 );
    $dong = array( 't' => $gio, 'c' => $gio, 'ip' => $ip, 'ua' => $ua, 'k' => $kieu === 'admin' ? 'admin' : 'dn' );
    if ( $kieu === 'admin' && $admin_id ) $dong['a'] = (int) $admin_id;

    $ds = get_user_meta( $user_id, 'sitetop_lich_su_dn', true );
    if ( ! is_array( $ds ) ) $ds = array();
    array_unshift( $ds, $dong );
    if ( count( $ds ) > SITETOP_LSDN_TOI_DA ) $ds = array_slice( $ds, 0, SITETOP_LSDN_TOI_DA );
    update_user_meta( $user_id, 'sitetop_lich_su_dn', $ds );
    // Admin đăng nhập hộ KHÔNG tính là khách đăng nhập (ô "đăng nhập hôm nay").
    if ( $kieu !== 'admin' ) update_user_meta( $user_id, 'sitetop_last_login', $gio );
}

add_action( 'wp_login', function ( $user_login, $user = null ) {
    if ( $user instanceof WP_User ) sitetop_lsdn_ghi( $user->ID, 'dn' );
    // Đăng nhập thật bằng form → bỏ dấu "admin đang xem hộ" (nếu trình duyệt này từng dùng nút đăng nhập hộ).
    if ( ! empty( $_COOKIE['sitetop_admin_ho'] ) && ! headers_sent() ) setcookie( 'sitetop_admin_ho', '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
}, 10, 2 );

/** Admin bấm "đăng nhập với tư cách khách": đánh dấu trình duyệt ADMIN để các lần vào sau không bị ghi là khách truy cập. */
function sitetop_lsdn_danh_dau_admin_ho() {
    if ( headers_sent() ) return;
    setcookie( 'sitetop_admin_ho', '1', 0, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
    $_COOKIE['sitetop_admin_ho'] = '1';
}

/* 16/11/2026 (chủ site: "khách truy cập tài khoản lúc giờ phút giây nào cũng phải hiện") — khách giữ đăng nhập
   tới 14 ngày nên rất ít khi qua form. Ghi thêm MỖI LẦN KHÁCH VÀO TÀI KHOẢN (mọi trang web khi đang đăng nhập):
   - lần vào mới (cách lần trước ≥ 30 phút, hoặc khác IP / thiết bị) → thêm 1 dòng 'tc' với giờ bắt đầu;
   - còn đang trong cùng lượt → chỉ cập nhật giờ "đến" (c) của dòng đầu, tối đa 1 lần ghi / 60 giây
     (có khách tải /customer/ 15–30 lần/phút — xem sự cố 11/10, không được ghi CSDL mỗi lần tải).
   Không ghi cho ajax/REST/cron, tài khoản admin, hay khi admin đang xem hộ (cookie sitetop_admin_ho). */
function sitetop_lsdn_truy_cap( $user_id ) {
    $user_id = (int) $user_id;
    $gio = function_exists( 'sitetop_current_time' ) ? sitetop_current_time() : current_time( 'mysql' );
    $bay_gio = strtotime( $gio );
    $ip  = function_exists( 'sitetop_get_real_ip' ) ? sitetop_get_real_ip() : sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
    $ua  = substr( sanitize_text_field( wp_unslash( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) ), 0, 300 );

    $ds = get_user_meta( $user_id, 'sitetop_lich_su_dn', true );
    if ( ! is_array( $ds ) ) $ds = array();
    $dau = $ds[0] ?? null;
    if ( $dau && ( $dau['k'] ?? '' ) !== 'admin' && ( $dau['ip'] ?? '' ) === $ip && ( $dau['ua'] ?? '' ) === $ua ) {
        $cuoi = strtotime( (string) ( $dau['c'] ?? $dau['t'] ?? '' ) );
        if ( $cuoi && $bay_gio - $cuoi < SITETOP_LSDN_KHOANG ) {
            if ( $bay_gio - $cuoi >= 60 ) {             // cùng lượt: chỉ dời giờ "đến"
                $ds[0]['c'] = $gio;
                update_user_meta( $user_id, 'sitetop_lich_su_dn', $ds );
            }
            return;
        }
    }
    array_unshift( $ds, array( 't' => $gio, 'c' => $gio, 'ip' => $ip, 'ua' => $ua, 'k' => 'tc' ) );
    if ( count( $ds ) > SITETOP_LSDN_TOI_DA ) $ds = array_slice( $ds, 0, SITETOP_LSDN_TOI_DA );
    update_user_meta( $user_id, 'sitetop_lich_su_dn', $ds );
    update_user_meta( $user_id, 'sitetop_last_login', $gio );
}

add_action( 'template_redirect', function () {
    if ( ! is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) return;
    if ( ! empty( $_COOKIE['sitetop_admin_ho'] ) ) return;
    $u = wp_get_current_user();
    if ( ! in_array( 'customer', (array) $u->roles, true ) || user_can( $u, 'manage_options' ) ) return;
    sitetop_lsdn_truy_cap( $u->ID );
}, 1 );

/** Admin xem lịch sử của một tài khoản. */
add_action( 'wp_ajax_sitetop_admin_lich_su_dn', function () {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Không có quyền' );
    $uid = absint( $_POST['user_id'] ?? 0 );
    if ( ! $uid || ! get_userdata( $uid ) ) wp_send_json_error( 'Không tìm thấy tài khoản' );

    $ds = get_user_meta( $uid, 'sitetop_lich_su_dn', true );
    if ( ! is_array( $ds ) ) $ds = array();
    $ra = array(); $ip_khac = array();
    foreach ( $ds as $d ) {
        $ten_admin = '';
        if ( ( $d['k'] ?? '' ) === 'admin' && ! empty( $d['a'] ) ) {
            $a = get_userdata( (int) $d['a'] );
            $ten_admin = $a ? $a->user_login : ( '#' . (int) $d['a'] );
        }
        if ( ( $d['k'] ?? '' ) !== 'admin' && ! empty( $d['ip'] ) ) $ip_khac[ $d['ip'] ] = 1;
        $ra[] = array(
            't'     => (string) ( $d['t'] ?? '' ),
            'c'     => (string) ( $d['c'] ?? $d['t'] ?? '' ),
            'k'     => (string) ( $d['k'] ?? 'dn' ),
            'ip'    => (string) ( $d['ip'] ?? '' ),
            'tb'    => sitetop_lsdn_thiet_bi( $d['ua'] ?? '' ),
            'ua'    => (string) ( $d['ua'] ?? '' ),
            'admin' => ( $d['k'] ?? '' ) === 'admin' ? ( $ten_admin !== '' ? $ten_admin : 'admin' ) : '',
        );
    }
    wp_send_json_success( array( 'ds' => $ra, 'so_ip' => count( $ip_khac ), 'toi_da' => SITETOP_LSDN_TOI_DA ) );
} );
