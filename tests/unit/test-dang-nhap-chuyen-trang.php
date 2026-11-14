<?php
/* ĐĂNG NHẬP XONG PHẢI CHUYỂN TRANG NGAY (14/11/2026). Lỗi cũ: wp_validate_redirect('', $fallback) trả
   CHUỖI RỖNG (fallback chỉ dùng khi host lạ) → wp_safe_redirect('') không chuyển → trang trắng 200, người
   dùng phải tải lại. Và: bấm Đăng nhập khi Cloudflare chưa xác minh xong thì giữ lại, xác minh xong tự gửi. */
$__dn = (string) file_get_contents( dirname( __DIR__, 2 ) . '/page-login.php' );
assert_true( strpos( $__dn, "if ( \$redirect === '' ) \$redirect = \$dashboard;" ) !== false, 'redirect_to rong → ve bang dieu khien (khong trang trang)' );
$__dn_a = strpos( $__dn, '$redirect  = wp_validate_redirect(' );
$__dn_b = strpos( $__dn, 'wp_safe_redirect( $redirect );', (int) $__dn_a );
$__dn_c = strpos( $__dn, "if ( \$redirect === '' )", (int) $__dn_a );
assert_true( $__dn_a !== false && $__dn_c > $__dn_a && $__dn_c < $__dn_b, 'Chot chuoi rong nam giua validate va redirect' );
assert_true( strpos( $__dn, 'data-callback="stTsOk"' ) !== false && strpos( $__dn, "window.stTsOk" ) !== false, 'Turnstile xong goi stTsOk' );
assert_true( strpos( $__dn, "nut('Đang xác minh Cloudflare…', true)" ) !== false, 'Bam som → cho xac minh, khong gui form rong' );
assert_true( strpos( $__dn, 'name="username"' ) !== false && strpos( $__dn, "\$_POST['username'] ?? ''" ) !== false, 'Loi thi giu lai ten dang nhap' );
// Hành vi THẬT của logic chặn bấm sớm: chạy đoạn JS bằng node với form/nút giả.
$__dn_js_a = strpos( $__dn, '(function(){' . "\n" . "    var f = document.getElementById('stLoginForm')" );
$__dn_js_b = strpos( $__dn, '})();', (int) $__dn_js_a ) + 5;
assert_true( $__dn_js_a !== false, 'Tim thay doan JS dang nhap' );
$__dn_h = <<<'JS'
var NGHE={}, GUI=0, NUT={innerHTML:'Đăng nhập',disabled:false,style:{}}, MSG={textContent:'',style:{}};
var F={querySelector:function(){return {};},addEventListener:function(t,fn){NGHE[t]=fn;},reportValidity:function(){return true;},submit:function(){GUI++;}};
global.window=global; global.document={getElementById:function(i){return i==='stLoginForm'?F:(i==='stLoginBtn'?NUT:MSG);}};
global.setTimeout=function(){};
function bam(){ var bi=false; NGHE.submit({preventDefault:function(){bi=true;}}); return bi; }
JS;
$__dn_k = <<<'JS'
var K={};
K.chanSom = bam(); K.nutKhiCho = NUT.innerHTML; K.guiTruoc = GUI;
stTsOk(); K.guiSauXacMinh = GUI; K.nutSau = NUT.innerHTML;
console.log(JSON.stringify(K));
JS;
$__dn_f = sys_get_temp_dir() . '/st-dn-' . getmypid() . '.js';
file_put_contents( $__dn_f, $__dn_h . "\n" . substr( $__dn, $__dn_js_a, $__dn_js_b - $__dn_js_a ) . "\n" . $__dn_k );
$__dn_r = (string) shell_exec( 'node ' . escapeshellarg( $__dn_f ) . ' 2>&1' ); @unlink( $__dn_f );
$K = json_decode( trim( $__dn_r ), true );
assert_true( is_array( $K ), 'Chay duoc JS dang nhap. Ra: ' . substr( $__dn_r, 0, 300 ) );
if ( is_array( $K ) ) {
    assert_true( $K['chanSom'] === true && $K['guiTruoc'] === 0, 'Bam khi chua xac minh → KHONG gui form' );
    assert_equals( 'Đang xác minh Cloudflare…', $K['nutKhiCho'], 'Nut bao dang xac minh' );
    assert_equals( 1, $K['guiSauXacMinh'], 'Xac minh xong → tu gui dung 1 lan' );
}
