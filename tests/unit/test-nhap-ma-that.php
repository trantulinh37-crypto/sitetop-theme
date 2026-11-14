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

// Thứ tự trong handler: chốt nt + chốt phiên bị bắt đứng TRƯỚC captcha và verify_and_pay.
$__nt_h = strpos( $__nt_s, 'function sitetop_ajax_verify_shortlink_code()' );
$__nt_c = strpos( $__nt_s, 'sitetop_nhap_ma_that_hop_le( $sid', (int) $__nt_h );
$__nt_g = strpos( $__nt_s, "get_transient( 'sitetop_gialap_' . \$sid )", (int) $__nt_h );
$__nt_v = strpos( $__nt_s, 'sitetop_verify_and_pay($sid, $code)', (int) $__nt_h );
$__nt_t = strpos( $__nt_s, 'sitetop_verify_turnstile(', (int) $__nt_h );
assert_true( $__nt_c && $__nt_g && $__nt_c < $__nt_t && $__nt_g < $__nt_v, 'Chot nhap that va chot phien bi bat chay truoc captcha/tra thuong' );

// Trang nhiệm vụ gửi nt và doUnlockSubmit tự kiểm (tool gọi thẳng cũng bị chặn).
$__nt_p = (string) file_get_contents( $__nt_goc . '/page-unlock.php' );
assert_true( strpos( $__nt_p, "fd.append('nt', _nhapThat.dau());" ) !== false, 'Trang nhiem vu gui nt' );
assert_true( (bool) preg_match( '/function doUnlockSubmit\(code\) \{\s*if \(!code\) return;\s*if \(!_nhapThat\.co\(\)\)/', $__nt_p ), 'doUnlockSubmit tu kiem nhap that' );
assert_true( strpos( $__nt_p, '_maNhapThat' ) === false, 'Khong con co toan cuc tool gan duoc' );
