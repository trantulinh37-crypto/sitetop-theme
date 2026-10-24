<?php
/* CHẶN TOOL TỰ CUỘN / TỰ CHẠM / TỰ DÁN MÃ (24/10/2026, chủ site gửi video tool "hỗ trợ sitetop": tự lướt qua chốt hành vi,
   tự dán mã, tự bấm). Chạy THẬT các hàm trích từ widget.js.php và page-unlock.php bằng node với sự kiện giả có / không
   isTrusted — đúng cách trình duyệt phân biệt thao tác người với script. */
$__ct_goc = dirname( __DIR__, 2 );
$__ct_w = (string) file_get_contents( $__ct_goc . '/widget.js.php' );
$__ct_cat = function ( $ma, $dau ) {
    $a = strpos( $ma, $dau ); if ( $a === false ) return '';
    $d = 0; $mo = false;
    for ( $i = $a; $i < strlen( $ma ); $i++ ) {
        if ( $ma[ $i ] === '{' ) { $d++; $mo = true; }
        elseif ( $ma[ $i ] === '}' ) { $d--; if ( $mo && $d === 0 ) return substr( $ma, $a, $i - $a + 1 ); }
    }
    return '';
};
$__ct_a = strpos( $__ct_w, '/* CHẶN TOOL TỰ CUỘN' ); $__ct_b = strpos( $__ct_w, 'function _onMouseMove(e){', (int) $__ct_a );
assert_true( $__ct_a !== false && $__ct_b !== false, 'Widget co khoi chan tool tu cuon truoc _onMouseMove' );
$__ct_dau = substr( $__ct_w, $__ct_a, $__ct_b - $__ct_a );
$__ct_js = $__ct_dau . "\n" . $__ct_cat( $__ct_w, 'function _onMouseMove(e){' ) . "\n" . $__ct_cat( $__ct_w, 'function _bhOnAct(e){' ) . "\n" . $__ct_cat( $__ct_w, 'function _bhOnScroll(){' );
$__ct_khung = <<<'JS'
var NGHE = {}; var T = 100000;
global.document = { addEventListener: function (t, f) { (NGHE[t] = NGHE[t] || []).push(f); } };
function phat(t, that) { (NGHE[t] || []).forEach(function (f) { f({ type: t, isTrusted: that }); }); }
function _mocGio() { return T; }
var _lastMouseMove = 0, _cdPaused = false; function _resumeCountdown() {}
var _bh = { gate: null, pre: null }, _prevScrollY = 0, Y = 0, _tooFastUntil = 0, QUA = 0, SOM = 0;
function _scrollY() { return Y; } function _scrollPct() { return Y / 1000; }
function _bhPass() { QUA++; _bh.gate = null; } function _bhEarly() { SOM++; } function _bhNagPop() {}
JS;
$__ct_kb = <<<'JS'
var KQ = {};
// 1) Chốt "lướt xuống": tool cuộn bằng script (scroll không kèm chạm thật) → KHÔNG qua
_bh.gate = 'half'; Y = 300; phat('scroll', true); _bhOnScroll(); KQ.cuon_gia = QUA;
// 2) Ngón tay thật (touchmove isTrusted) rồi cuộn → qua
phat('touchmove', true); Y = 600; _bhOnScroll(); KQ.cuon_that = QUA;
// 3) Quá 3 giây sau thao tác thật, cuộn tiếp do quán tính/script → không tính
_bh.gate = 'half'; T += 3500; Y = 900; _bhOnScroll(); KQ.qua_3s = QUA;
// 4) Chạm giả (dispatchEvent) vào chốt "chạm" → không qua; chạm thật → qua
_bh.gate = 'tap'; _bhOnAct({ isTrusted: false }); KQ.cham_gia = QUA; _bhOnAct({ isTrusted: true }); KQ.cham_that = QUA;
// 5) Chống bỏ máy: sự kiện giả / scroll không kèm thao tác thật không làm mới mốc hoạt động
T += 5000; _lastMouseMove = 0; _onMouseMove({ type: 'mousemove', isTrusted: false }); KQ.hd_gia = _lastMouseMove;
_onMouseMove({ type: 'scroll', isTrusted: true }); KQ.hd_scroll_khong_tay = _lastMouseMove;
phat('pointerdown', true); _onMouseMove({ type: 'scroll', isTrusted: true }); KQ.hd_that = _lastMouseMove === T;
// 6) Sự kiện giả không làm "có thao tác thật"
T += 5000; phat('touchstart', false); KQ.co_that_sau_gia = _tnCoThat();
console.log(JSON.stringify(KQ));
JS;
$__ct_f = sys_get_temp_dir() . '/st-ct-' . getmypid() . '.js';
file_put_contents( $__ct_f, $__ct_khung . "\n" . $__ct_js . "\n" . $__ct_kb );
$__ct_r = (string) shell_exec( 'node ' . escapeshellarg( $__ct_f ) . ' 2>&1' ); @unlink( $__ct_f );
$K = json_decode( trim( $__ct_r ), true );
assert_true( is_array( $K ), 'Chay duoc ham chot hanh vi cua widget bang node. Ra: ' . substr( $__ct_r, 0, 400 ) );
if ( is_array( $K ) ) {
    assert_equals( 0, $K['cuon_gia'], 'Tool cuon bang script (khong cham that) → KHONG qua chot luot' );
    assert_equals( 1, $K['cuon_that'], 'Ngon tay that + cuon → qua chot' );
    assert_equals( 1, $K['qua_3s'], 'Qua 3 giay sau thao tac that → cuon khong tinh' );
    assert_true( $K['cham_gia'] === 1 && $K['cham_that'] === 2, 'Cham gia (isTrusted=false) khong qua chot cham; cham that thi qua' );
    assert_true( $K['hd_gia'] === 0 && $K['hd_scroll_khong_tay'] === 0 && $K['hd_that'] === true, 'Chong bo may: chi thao tac that moi lam moi moc hoat dong' );
    assert_equals( false, $K['co_that_sau_gia'], 'Su kien gia khong duoc tinh la thao tac that' );
}

// ---- Trang nhiệm vụ: chỉ nhận mã do người gõ/dán + cú bấm thật ----
$__ct_p = (string) file_get_contents( $__ct_goc . '/page-unlock.php' );
$__ct_a = strpos( $__ct_p, 'var _maNhapThat = false;' ); $__ct_b = strpos( $__ct_p, 'function doUnlockSubmit(code)', (int) $__ct_a );
assert_true( $__ct_a !== false && $__ct_b !== false, 'Trang nhiem vu co khoi chan tu dan ma' );
$__ct_u = substr( $__ct_p, $__ct_a, $__ct_b - $__ct_a );
$__ct_khung2 = <<<'JS'
var NGHE = {}, O = { value: '', addEventListener: function (t, f) { (NGHE[t] = NGHE[t] || []).push(f); }, focus: function () {} };
global.document = { getElementById: function () { return O; } };
var TOAST = [], GUI = []; function showToast(m) { TOAST.push(m); } function doUnlockSubmit(c) { GUI.push(c); }
var unlockCaptchaOn = false, unlockCaptchaTok = ''; function openUnlockCaptcha() {}
function phat(t, that) { (NGHE[t] || []).forEach(function (f) { f({ type: t, isTrusted: that }); }); }
JS;
$__ct_kb2 = <<<'JS'
var KQ = {};
O.value = 'B920C789'; unlockLink({ isTrusted: true }); KQ.gan_thang = { gui: GUI.length, toast: TOAST[TOAST.length - 1] };
phat('input', false); unlockLink({ isTrusted: true }); KQ.input_gia = GUI.length;
phat('paste', true); unlockLink({ isTrusted: false }); KQ.bam_gia = GUI.length;
unlockLink({ isTrusted: true }); KQ.that = GUI.length;
console.log(JSON.stringify(KQ));
JS;
$__ct_f = sys_get_temp_dir() . '/st-ct2-' . getmypid() . '.js';
file_put_contents( $__ct_f, $__ct_khung2 . "\n" . $__ct_u . "\n" . $__ct_kb2 );
$__ct_r = (string) shell_exec( 'node ' . escapeshellarg( $__ct_f ) . ' 2>&1' ); @unlink( $__ct_f );
$U = json_decode( trim( $__ct_r ), true );
assert_true( is_array( $U ), 'Chay duoc unlockLink bang node. Ra: ' . substr( $__ct_r, 0, 400 ) );
if ( is_array( $U ) ) {
    assert_true( $U['gan_thang']['gui'] === 0 && $U['gan_thang']['toast'] === 'Vui lòng tự gõ hoặc dán mã vào ô', 'Tool gan thang .value → KHONG gui, bao tu go/dan' );
    assert_equals( 0, $U['input_gia'], 'dispatchEvent input gia → van khong gui' );
    assert_equals( 0, $U['bam_gia'], 'Da dan that nhung cu bam la gia → khong gui' );
    assert_equals( 1, $U['that'], 'Dan that + bam that → gui ma' );
}
