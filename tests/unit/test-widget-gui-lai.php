<?php
/* WIDGET GỬI LẠI KHI LỖI TẠM THỜI (11/10/2026). Chủ site: "user báo lỗi xác minh xong đứng im time không chạy".
   Thử thật trên production sau khi hết quá tải CSDL: luồng chạy đúng (Cloudflare tự qua, đồng hồ chạy, dừng ở chốt hành vi
   có hộp nhắc). Báo lỗi dồn vào khung CSDL quá tải 09:30–16:00 — khi đó widget gặp 503 (gọi chéo tên miền thì thành 0 vì
   trang lỗi không có CORS) là BỎ QUA IM LẶNG. Nay ajax() + sendVerifyAccess gửi lại tối đa 3 lần (1,5 → 3 → 6 giây) cho lỗi
   tạm thời, chỉ với lệnh luồng phụ thuộc và an toàn khi lặp.
   Test CHẠY THẬT các hàm trích từ widget.js.php bằng node, với XMLHttpRequest giả. */

$__wg_goc = dirname( __DIR__, 2 );
$__wg_ma  = (string) file_get_contents( $__wg_goc . '/widget.js.php' );
$__wg_dong = explode( "\n", $__wg_ma );
/* Cắt từ dòng mở hàm tới dòng "}" đầu tiên ở cột 0 (hàm cấp cao nhất của widget viết đúng kiểu đó). */
$__wg_cat = function ( $dau ) use ( $__wg_dong ) {
    $out = array(); $dang = false;
    foreach ( $__wg_dong as $d ) {
        if ( ! $dang && strpos( $d, $dau ) === 0 ) $dang = true;
        if ( $dang ) { $out[] = $d; if ( rtrim( $d ) === '}' || ( count( $out ) === 1 && substr( rtrim( $d ), -1 ) === '}' ) ) break; }
    }
    return implode( "\n", $out );
};
$__wg_js = '';
foreach ( array( 'function _stLoiTamThoi(', 'function _stGiayChoLai(', 'function _stGuiLaiDuoc(', 'function ajax(', 'function sendVerifyAccess(' ) as $__wg_t ) {
    $__wg_x = $__wg_cat( $__wg_t );
    assert_true( $__wg_x !== '', 'Tim thay ' . $__wg_t . ' trong widget.js.php' );
    $__wg_js .= $__wg_x . "\n";
}
$__wg_js = preg_replace( '/<\?php.*?\?>/s', 'NONCE', $__wg_js );

$__wg_khung = <<<'JS'
var C = { api: 'https://sitetop.net' }, state = {};
var HANG = [], GUI = [], CHO = [];
global.setTimeout = function (f, ms) { CHO.push(ms); setImmediate(f); return 0; };
function XMLHttpRequest() { this.readyState = 0; this.status = 0; this.responseText = ''; }
XMLHttpRequest.prototype.open = function () {};
XMLHttpRequest.prototype.setRequestHeader = function () {};
XMLHttpRequest.prototype.send = function (body) {
  var x = this; GUI.push(String(body));
  var t = HANG.shift() || { st: 500, txt: '' };
  setImmediate(function () { x.readyState = 4; x.status = t.st; x.responseText = t.txt; x.onreadystatechange(); });
};
global.XMLHttpRequest = XMLHttpRequest;
global.localStorage = { getItem: function () { return ''; }, setItem: function () {} };
global.document = { referrer: '', getElementById: function () { return null; } };
global.window = { location: { href: 'https://khach.vn/' } };
function _navType() { return 'navigate'; } function _khungChinh() { return 1; }   // hàm phụ widget dùng khi ghép dữ liệu gửi
async function doi() { for (var i = 0; i < 60; i++) await new Promise(function (r) { setImmediate(r); }); }
function moi(h) { HANG = h; GUI = []; CHO = []; }
async function chay() {
  var KQ = {}, goi;
  moi([{ st: 503 }, { st: 0 }, { st: 200, txt: '{"success":true,"data":"marked"}' }]); goi = [];
  ajax('sitetop_widget_start_timer', { session_id: 's1' }, function (r) { goi.push(r); }); await doi();
  KQ.a = { gui: GUI.length, cho: CHO, cb: goi };
  moi([{ st: 0 }, { st: 0 }, { st: 0 }, { st: 0 }, { st: 200, txt: '{"success":true}' }]); goi = [];
  ajax('sitetop_get_code', { session_id: 's1' }, function (r) { goi.push(r); }); await doi();
  KQ.b = { gui: GUI.length, cho: CHO, cb: goi.length };
  moi([{ st: 503 }, { st: 200, txt: '{}' }]); goi = [];
  ajax('sitetop_widget_ping', { session_id: 's1' }, function (r) { goi.push(r); }); await doi();
  KQ.c = { gui: GUI.length, cb: goi.length };
  moi([{ st: 403 }, { st: 200, txt: '{}' }]); goi = [];
  ajax('sitetop_get_code', { session_id: 's1' }, function (r) { goi.push(r); }); await doi();
  KQ.d = { gui: GUI.length, cb: goi.length };
  moi([{ st: 502 }, { st: 200, txt: '{"success":true,"data":"ok"}' }]); goi = [];
  ajax('sitetop_widget_captcha', { session_id: 's1', token: 't' }, function (r) { goi.push(r); }); await doi();
  KQ.e = { gui: GUI.length, cb: goi.length, giu_tham_so: GUI.every(function (b) { return /token=t/.test(b) && /session_id=s1/.test(b); }) };
  moi([{ st: 504 }, { st: 200, txt: '{}' }]); goi = [];
  ajax('sitetop_unlock_heartbeat', { session_id: 's1' }, function (r) { goi.push(r); }); await doi();
  KQ.f = { gui: GUI.length };
  moi([{ st: 503 }, { st: 0 }, { st: 200, txt: '{"success":false}' }]);
  sendVerifyAccess('PHIEN9', '123', '1', 'keyword_search'); await doi();
  KQ.g = { gui: GUI.length, cho: CHO, giu_tham_so: GUI.every(function (b) { return /unlock_session=PHIEN9/.test(b) && /action=sitetop_widget_verify_access/.test(b); }) };
  moi([{ st: 0 }, { st: 0 }, { st: 0 }, { st: 0 }, { st: 0 }]);
  sendVerifyAccess('', '', '', ''); await doi();
  KQ.h = { gui: GUI.length };
  moi([{ st: 404 }]);
  sendVerifyAccess('', '', '', ''); await doi();
  KQ.i = { gui: GUI.length };
  console.log(JSON.stringify(KQ));
}
JS;
$__wg_f = sys_get_temp_dir() . '/st-wg-' . getmypid() . '.js';
file_put_contents( $__wg_f, $__wg_khung . "\n" . $__wg_js . "\nchay().catch(function(e){ console.log('LOI ' + e); });\n" );
$__wg_r = (string) shell_exec( 'node ' . escapeshellarg( $__wg_f ) . ' 2>&1' );
@unlink( $__wg_f );
$W = json_decode( trim( $__wg_r ), true );
assert_true( is_array( $W ), 'Chay duoc ajax/sendVerifyAccess cua widget bang node. Ra: ' . substr( $__wg_r, 0, 500 ) );
if ( is_array( $W ) ) {
    assert_equals( array( 'gui' => 3, 'cho' => array( 1500, 3000 ), 'cb' => array( array( 'success' => true, 'data' => 'marked' ) ) ), $W['a'],
        'start_timer: 503 roi 0 (loi CORS cua trang 503) roi 200 → gui lai 2 lan (1,5s, 3s), goi cb DUNG MOT LAN' );
    assert_equals( array( 'gui' => 4, 'cho' => array( 1500, 3000, 6000 ), 'cb' => 0 ), $W['b'], 'get_code: loi ca 4 lan → toi da 3 lan gui lai (1,5 → 3 → 6s) roi thoi' );
    assert_equals( array( 'gui' => 1, 'cb' => 0 ), $W['c'], 'ping: tu lap san → KHONG gui lai' );
    assert_equals( array( 'gui' => 1, 'cb' => 0 ), $W['d'], 'Loi khong tam thoi (403) → KHONG gui lai' );
    assert_true( $W['e']['gui'] === 2 && $W['e']['cb'] === 1 && $W['e']['giu_tham_so'] === true, 'widget_captcha: 502 → gui lai, giu nguyen token + phien' );
    assert_equals( 1, $W['f']['gui'], 'heartbeat: tu lap 10s/lan → KHONG gui lai' );
    assert_true( $W['g']['gui'] === 3 && $W['g']['cho'] === array( 1500, 3000 ) && $W['g']['giu_tham_so'] === true,
        'sendVerifyAccess: 503, 0 roi 200 → gui lai, giu nguyen phien nhiem vu. Ra: ' . json_encode( $W['g'] ) );
    assert_equals( 4, $W['h']['gui'], 'sendVerifyAccess: loi mai → toi da 3 lan gui lai' );
    assert_equals( 1, $W['i']['gui'], 'sendVerifyAccess: 404 → khong gui lai' );
}
