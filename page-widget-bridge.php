<?php
/**
 * Widget Bridge — cross-origin session_id reader (sitetop.net)
 *
 * Widget của SITE NGUỒN (dethitoanthpt.com…) chạy trên trang đích (cross-origin) không tự đọc
 * được session của sitetop. Trang này được nhúng vào iframe ẩn từ widget đó → script chạy
 * same-origin với sitetop.net → đọc session → postMessage {type:'ln_session', sid} về cha
 * → widget biết CHÍNH XÁC phiên page-unlock của khách → cầu nối hỏi đúng pool, cấp đúng mã
 * (hết cảnh khách sitetop nhận mã của hệ thống khác → "Code chưa sẵn sàng").
 *
 * sid lấy từ 2 nguồn:
 *   1) localStorage 'tn_unlock_session' (page-unlock ghi) — như lentop.one.
 *   2) Cookie 'sitetop_sid' (SameSite=None; Secure — shortlink-functions.php:258), echo
 *      server-side: sống được cả khi browser PHÂN VÙNG localStorage bên thứ ba (Chrome storage
 *      partitioning / Safari ITP) — trường hợp làm session-match trượt lâu nay.
 *
 * LƯU Ý CACHE: vì echo cookie nên HTML là per-visitor — BẮT BUỘC no-store (khác bridge của
 * lentop.one đang cache public: bản đó chỉ đọc localStorage nên HTML giống nhau cho mọi người).
 */
if ( ! defined( 'ABSPATH' ) ) {
    require_once dirname( __FILE__ ) . '/../../../wp-load.php';
}

// Cho phép nhúng iframe cross-origin từ mọi trang đích (đồng bộ page-widget-captcha.php).
header( 'X-Frame-Options: ALLOWALL' );
header( 'Content-Security-Policy: frame-ancestors *' );
header( 'Cache-Control: private, no-store, max-age=0' );
header( 'Content-Type: text/html; charset=UTF-8' );

// Cookie sid do page-unlock set — chỉ nhận đúng định dạng 32 hex (session_id chuẩn của theme).
$cookie_sid = '';
if ( ! empty( $_COOKIE['sitetop_sid'] ) && preg_match( '/^[a-f0-9]{32}$/', (string) $_COOKIE['sitetop_sid'] ) ) {
    $cookie_sid = (string) $_COOKIE['sitetop_sid'];
}
?><!doctype html>
<html><head><meta charset="utf-8"><title>Bridge</title></head>
<body>
<script>
(function(){
    var sid = '';
    try { sid = localStorage.getItem('tn_unlock_session') || ''; } catch(e){}
    if ( ! sid ) { sid = '<?php echo esc_js( $cookie_sid ); ?>'; }
    // postMessage '*': widget nằm trên trang đích bất kỳ (cross-origin). session_id không phải
    // bí mật (hiện sẵn trên URL page-unlock); verify phía server vẫn tự kiểm tra mọi điều kiện.
    if ( window.parent && window.parent !== window ) {
        window.parent.postMessage({ type: 'ln_session', sid: sid }, '*');
    }
})();
</script>
</body></html>
<?php exit; ?>
