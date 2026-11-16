<?php
/** Lịch sử đăng nhập tài khoản (16/11/2026) — includes/lich-su-dang-nhap.php + nút ở tab Khách hàng. */
$__d = dirname( __DIR__, 2 );
$__l = (string) file_get_contents( $__d . '/includes/lich-su-dang-nhap.php' );
if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', '/' );
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
require_once $__d . '/includes/lich-su-dang-nhap.php';
assert_true( strpos( (string) file_get_contents( $__d . '/functions.php' ), "'lich-su-dang-nhap'," ) !== false, 'Module lich su dang nhap duoc nap' );
assert_true( strpos( $__l, "add_action( 'wp_login'" ) !== false, 'Ghi khi dang nhap thanh cong (hook wp_login)' );
assert_true( strpos( $__l, "check_ajax_referer( 'sitetop_admin_nonce', 'nonce' )" ) !== false && strpos( $__l, "current_user_can( 'manage_options' )" ) !== false, 'Ajax xem lich su chi admin' );
assert_true( strpos( $__l, "update_user_meta( \$user_id, 'sitetop_last_login', \$gio )" ) !== false, 'Ghi sitetop_last_login (o dang nhap hom nay)' );
assert_true( strpos( $__l, 'array_slice( $ds, 0, SITETOP_LSDN_TOI_DA )' ) !== false, 'Gioi han so dong luu' );
assert_equals( 'iPhone · Chrome', sitetop_lsdn_thiet_bi( 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 CriOS/120 Mobile Safari/604.1' ), 'Nhan dien iPhone Chrome' );
assert_equals( 'Windows · Cốc Cốc', sitetop_lsdn_thiet_bi( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) coc_coc_browser/120 Chrome/120 Safari/537.36' ), 'Nhan dien Coc Coc' );
assert_equals( 'Android · Samsung Internet', sitetop_lsdn_thiet_bi( 'Mozilla/5.0 (Linux; Android 14) SamsungBrowser/24.0 Chrome/117 Mobile Safari/537.36' ), 'Nhan dien Samsung' );
assert_equals( 'Không rõ', sitetop_lsdn_thiet_bi( '' ), 'UA rong' );
foreach ( array( 'includes/admin-dashboard.php', 'includes/customer-management.php' ) as $__f ) {
    assert_true( strpos( (string) file_get_contents( $__d . '/' . $__f ), "sitetop_lsdn_ghi(" ) !== false && strpos( (string) file_get_contents( $__d . '/' . $__f ), "'admin', \$admin_id )" ) !== false, 'Admin dang nhap ho duoc ghi rieng: ' . $__f );
}
$__t = (string) file_get_contents( $__d . '/includes/admin/tabs/tab-customers.php' );
assert_true( strpos( $__t, 'onclick="lsdnOpen(' ) !== false && strpos( $__t, "fd.append('action','sitetop_admin_lich_su_dn')" ) !== false, 'Tab Khach hang co nut + hop xem lich su' );
// 16/11 chiều: ghi MỖI LƯỢT khách vào tài khoản, không ghi admin xem hộ, không ghi CSDL mỗi lần tải trang.
assert_true( strpos( $__l, 'add_action( \'template_redirect\'' ) !== false && strpos( $__l, 'in_array( \'customer\', (array) $u->roles, true )' ) !== false, 'Ghi luot khach vao tai khoan' );
assert_true( strpos( $__l, 'if ( ! empty( $_COOKIE[\'sitetop_admin_ho\'] ) ) return;' ) !== false, 'Admin xem ho khong bi ghi la khach' );
assert_true( strpos( $__l, 'if ( $bay_gio - $cuoi >= 60 ) {' ) !== false, 'Cung luot: toi da 1 lan ghi / 60 giay' );
assert_true( strpos( $__t, '(d.k===\'dn\'?\'Đăng nhập\':\'Truy cập\')' ) !== false, 'Bang hien loai Dang nhap / Truy cap' );
