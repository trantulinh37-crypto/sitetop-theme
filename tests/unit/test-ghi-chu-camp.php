<?php
/* GHI CHÚ ADMIN cho camp (14/11/2026): chỉ admin thấy. Không được nằm trong bảng keyword_campaigns
   (trang khách đọc SELECT kc.* → lộ ghi chú), lưu wp_options autoload=no. Chạy THẬT hàm lưu/đọc với stub. */
$__gc_goc = dirname( __DIR__, 2 );
$__gc_d = (string) file_get_contents( $__gc_goc . '/includes/admin-dashboard.php' );
$__gc_t = (string) file_get_contents( $__gc_goc . '/includes/admin/tabs/tab-campaigns.php' );
assert_true( strpos( $__gc_d, "'ghi_chu'=>function_exists('sitetop_ghi_chu_camp_lay') ? sitetop_ghi_chu_camp_lay(" ) !== false, 'Khung sua nhan ghi chu' );
assert_true( strpos( $__gc_d, "add_action('wp_ajax_sitetop_admin_luu_ghi_chu_camp'" ) !== false, 'Co cong luu ghi chu rieng (camp dang chay van ghi duoc)' );
assert_true( strpos( $__gc_t, "<th>Thao tác</th>\n    <th>Ghi chú</th>" ) !== false, 'Cot Ghi chu nam ngay sau cot Thao tac' );
assert_true( strpos( $__gc_t, 'data-camp-gc=' ) !== false && strpos( $__gc_t, 'function admGhiChuMo(' ) !== false, 'O ghi chu tren dong + sua tai cho' );
assert_true( strpos( $__gc_d, "'completed'=>(int)(\$c->completed ?? 0)" ) !== false, 'Khung sua nhan so luot da chay (goi y So ngay)' );
assert_true( strpos( $__gc_d, "sitetop_ghi_chu_camp_luu(\$id, \$_POST['ghi_chu'])" ) !== false, 'Luu camp co luu ghi chu' );
assert_true( strpos( $__gc_t, 'id="admEditGhiChu"' ) !== false && strpos( $__gc_t, "fd.append('ghi_chu'" ) !== false, 'Khung sua co o ghi chu va gui len' );
foreach ( glob( $__gc_goc . '/includes/*.php' ) as $__gc_f ) {
    $__gc_x = (string) file_get_contents( $__gc_f );
    assert_true( ! preg_match( '/ALTER TABLE[^;]*ghi_chu/i', $__gc_x ), 'Khong them cot ghi_chu vao bang (tranh lo sang khach): ' . basename( $__gc_f ) );
}
$__gc_a = strpos( $__gc_d, 'function sitetop_ghi_chu_camp_lay(' );
$__gc_b = strpos( $__gc_d, 'function sitetop_ghi_chu_camp_nhieu(' );
$GLOBALS['__gc'] = array();
eval( 'namespace GC; ' . <<<'PHP'
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['__gc'] ) ? $GLOBALS['__gc'][ $k ][0] : $d; }
function add_option( $k, $v, $x, $a ) { $GLOBALS['__gc'][ $k ] = array( $v, $a ); }
function update_option( $k, $v, $a ) { $GLOBALS['__gc'][ $k ] = array( $v, $a ? 'yes' : 'no' ); }
function delete_option( $k ) { unset( $GLOBALS['__gc'][ $k ] ); }
function sanitize_textarea_field( $s ) { return strip_tags( $s ); }
function wp_unslash( $s ) { return stripslashes( $s ); }
function add_action() {}
PHP
. substr( $__gc_d, $__gc_a, $__gc_b - $__gc_a ) );
\GC\sitetop_ghi_chu_camp_luu( 7, "  Gia hạn 30/11 <b>x</b>  " );
assert_equals( 'Gia hạn 30/11 x', \GC\sitetop_ghi_chu_camp_lay( 7 ), 'Luu + doc ghi chu (cat khoang trang, bo the HTML)' );
assert_equals( 'no', $GLOBALS['__gc']['sitetop_ghi_chu_camp_7'][1], 'Ghi chu KHONG nap san moi request (autoload=no)' );
\GC\sitetop_ghi_chu_camp_luu( 7, str_repeat( 'a', 1500 ) );
assert_equals( 1000, strlen( \GC\sitetop_ghi_chu_camp_lay( 7 ) ), 'Toi da 1000 ky tu' );
\GC\sitetop_ghi_chu_camp_luu( 7, '   ' );
assert_true( ! isset( $GLOBALS['__gc']['sitetop_ghi_chu_camp_7'] ), 'Xoa trang → xoa han dong ghi chu' );
