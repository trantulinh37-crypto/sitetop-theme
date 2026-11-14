<?php
/* CHẶN CỨNG KHI NHẬP MÃ BẰNG TOOL (14/11/2026): máy chủ chỉ nhận mã kèm dấu "nhập thật" (nt) của đúng
   phiên, và từ chối mã của phiên đã bị bắt dùng tool. Trích THẬT hai hàm dấu từ shortlink-ajax.php. */
$__nt_goc = dirname( __DIR__, 2 );
$__nt_s   = (string) file_get_contents( $__nt_goc . '/includes/shortlink-ajax.php' );
$__nt_a = strpos( $__nt_s, "if ( ! function_exists( 'sitetop_dau_nhap_that' ) )" );
$__nt_b = strpos( $__nt_s, '// Verify shortlink code', (int) $__nt_a );
assert_true( $__nt_a !== false && $__nt_b !== false, 'Co ham dau nhap that' );
if ( ! function_exists( 'wp_hash' ) ) { function wp_hash( $d ) { return hash_hmac( 'md5', $d, 'muoi-test' ); } }
eval( substr( $__nt_s, $__nt_a, $__nt_b - $__nt_a ) );
$__nt_k = sitetop_dau_nhap_that( 'phienA' );
assert_true( (bool) preg_match( '/^[a-f0-9]{12}$/', $__nt_k ), 'Khoa 12 ky tu hex' );
assert_true( sitetop_nhap_ma_that_hop_le( 'phienA', $__nt_k . '.3' ), 'Dung khoa + co lan nhap → hop le' );
assert_true( ! sitetop_nhap_ma_that_hop_le( 'phienA', '' ), 'Thieu nt (tool tu fetch) → tu choi' );
assert_true( ! sitetop_nhap_ma_that_hop_le( 'phienA', $__nt_k ), 'Thieu so lan → tu choi' );
assert_true( ! sitetop_nhap_ma_that_hop_le( 'phienA', $__nt_k . '.0' ), 'So lan 0 → tu choi' );
assert_true( ! sitetop_nhap_ma_that_hop_le( 'phienB', $__nt_k . '.2' ), 'Khoa cua phien khac → tu choi' );
assert_true( ! sitetop_nhap_ma_that_hop_le( 'phienA', array( 'x' ) ) || true, 'Mang khong lam vo ham' );

// Cả hai cổng nhập mã (trang nhiệm vụ + cổng cũ sitetop_verify) gọi chốt TRƯỚC captcha và verify_and_pay.
foreach ( array( 'function sitetop_ajax_verify_shortlink_code()', 'function sitetop_ajax_verify()' ) as $__nt_ten ) {
    $__nt_h = strpos( $__nt_s, $__nt_ten );
    $__nt_e = strpos( $__nt_s, "\n}\n", (int) $__nt_h );
    $__nt_than = substr( $__nt_s, (int) $__nt_h, $__nt_e - $__nt_h );
    $__nt_c = strpos( $__nt_than, 'sitetop_chot_nhap_ma( $sid );' );
    $__nt_v = strpos( $__nt_than, 'sitetop_verify_and_pay($sid, $code)' );
    assert_true( $__nt_h !== false && $__nt_c !== false && $__nt_v !== false && $__nt_c < $__nt_v, $__nt_ten . ' goi chot nhap ma truoc tra thuong' );
    $__nt_t = strpos( $__nt_than, 'sitetop_verify_turnstile(' );
    if ( $__nt_t !== false ) assert_true( $__nt_c < $__nt_t, $__nt_ten . ' chot truoc captcha' );
}
// Chốt nhập mã từ chối mã của phiên đã bị bắt dùng tool.
$__nt_f = substr( $__nt_s, strpos( $__nt_s, "function sitetop_chot_nhap_ma(" ), 2500 );
assert_true( strpos( $__nt_f, "sitetop_gialap_" ) !== false, 'Chot nhap ma xet phien bi bat dung tool' );

// Trang nhiệm vụ gửi nt và doUnlockSubmit tự kiểm (tool gọi thẳng cũng bị chặn).
$__nt_p = (string) file_get_contents( $__nt_goc . '/page-unlock.php' );
assert_true( strpos( $__nt_p, "fd.append('nt', _nhapThat.dau());" ) !== false, 'Trang nhiem vu gui nt' );
assert_true( (bool) preg_match( '/function doUnlockSubmit\(code\) \{\s*if \(!code\) return;\s*if \(!_nhapThat\.co\(\)\)/', $__nt_p ), 'doUnlockSubmit tu kiem nhap that' );
assert_true( strpos( $__nt_p, '_maNhapThat' ) === false, 'Khong con co toan cuc tool gan duoc' );
