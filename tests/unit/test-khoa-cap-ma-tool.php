<?php
/* KHOÁ CẤP MÃ CHO NGƯỜI DÙNG TOOL (14/11/2026): bắt được tool một lần -> tài khoản bị dừng cấp mã N giờ,
   IP bị dừng M giờ; option khoa_ma_tool_gio = 0 tắt hẳn. Chạy THẬT 4 hàm trích từ shortlink-ajax.php
   với CSDL/option/meta giả trong bộ nhớ. */
$__km_s = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/shortlink-ajax.php' );
$__km_a = strpos( $__km_s, "if ( ! function_exists( 'sitetop_khoa_ma_gio' ) )" );
$__km_b = strpos( $__km_s, '/* Cảnh báo Telegram khi bắt widget chạy trong iframe', (int) $__km_a );
assert_true( $__km_a !== false && $__km_b !== false, 'Co bo ham khoa cap ma' );
$GLOBALS['__km'] = array( 'opt' => array(), 'meta' => array(), 'tr' => array(), 'ip' => '1.2.3.4', 'uid' => array( 'S1' => 7, 'S2' => 7, 'S3' => 9 ) );
eval( 'namespace KM; ' . <<<'PHP'
const HOUR_IN_SECONDS = 3600;
function sitetop_get_option( $k, $d = null ) { return $GLOBALS['__km']['opt'][ $k ] ?? $d; }
function sitetop_get_real_ip() { return $GLOBALS['__km']['ip']; }
function set_transient( $k, $v, $t ) { $GLOBALS['__km']['tr'][ $k ] = $v; }
function get_transient( $k ) { return $GLOBALS['__km']['tr'][ $k ] ?? false; }
function update_user_meta( $u, $k, $v ) { $GLOBALS['__km']['meta'][ $u ][ $k ] = $v; }
function get_user_meta( $u, $k, $s ) { return $GLOBALS['__km']['meta'][ $u ][ $k ] ?? ''; }
function current_time( $f ) { return '2026-11-14 22:00:00'; }
class DB { public $prefix = 'wp_'; function prepare( $q, $a ) { return $a; } function get_var( $sid ) { return $GLOBALS['__km']['uid'][ $sid ] ?? 0; } }
$GLOBALS['wpdb'] = new DB();
PHP
. str_replace( "function_exists( 'sitetop_get_real_ip' )", 'true', substr( $__km_s, $__km_a, $__km_b - $__km_a ) ) );  // hàm IP nằm trong namespace KM
assert_equals( '', \KM\sitetop_dang_khoa_cap_ma( 'S1' ), 'Chua bat → khong khoa' );
assert_equals( 7, \KM\sitetop_khoa_cap_ma_vi_tool( 'S1', 'bang_tool' ), 'Bat tool phien S1 → khoa user #7' );
assert_equals( 'user', \KM\sitetop_dang_khoa_cap_ma( 'S2' ), 'Phien KHAC cua cung user #7 → van bi khoa cap ma' );
$GLOBALS['__km']['ip'] = '5.6.7.8';
assert_equals( 'user', \KM\sitetop_dang_khoa_cap_ma( 'S2' ), 'Doi IP van bi khoa (khoa theo tai khoan)' );
assert_equals( '', \KM\sitetop_dang_khoa_cap_ma( 'S3' ), 'User khac, IP khac → khong anh huong' );
$GLOBALS['__km']['ip'] = '1.2.3.4';
assert_equals( 'ip', \KM\sitetop_dang_khoa_cap_ma( 'S3' ), 'Cung IP vua bi bat → khoa theo IP (ngan han)' );
$m = $GLOBALS['__km']['meta'][7]['sitetop_khoa_ma_tool'];
assert_true( $m['den'] - time() > 23 * 3600 && $m['den'] - time() <= 24 * 3600, 'Mac dinh khoa user 24 gio' );
assert_true( $GLOBALS['__km']['tr'][ 'sitetop_khoa_ma_ip_' . md5( '1.2.3.4' ) ] - time() <= 2 * 3600, 'Mac dinh khoa IP 2 gio' );
$GLOBALS['__km']['meta'][7]['sitetop_khoa_ma_tool']['den'] = time() - 1;
$GLOBALS['__km']['ip'] = '9.9.9.9';
assert_equals( '', \KM\sitetop_dang_khoa_cap_ma( 'S2' ), 'Het han → tu mo' );
$GLOBALS['__km']['meta'][7]['sitetop_khoa_ma_tool']['den'] = time() + 999;
$GLOBALS['__km']['opt']['khoa_ma_tool_gio'] = 0;
assert_equals( '', \KM\sitetop_dang_khoa_cap_ma( 'S2' ), 'khoa_ma_tool_gio=0 → tat han ke ca khoa dang hieu luc' );
assert_equals( 0, \KM\sitetop_khoa_cap_ma_vi_tool( 'S3', 'x' ), 'Tat thi khong khoa them' );
// get_code gọi khoá và báo widget dừng hẳn (không dội cổng 3 giây/lần).
$__km_g = substr( $__km_s, strpos( $__km_s, 'function sitetop_ajax_get_code()' ), 4000 );
$__km_x = strpos( $__km_g, 'sitetop_dang_khoa_cap_ma( $sid )' ); $__km_y = strpos( $__km_g, 'sitetop_get_widget_code($sid)' );
assert_true( $__km_x !== false && $__km_x < $__km_y, 'get_code kiem khoa truoc khi cap ma' );
assert_true( strpos( $__km_g, "'chan_tuagio' => 1" ) !== false, 'Bao widget dung han' );
