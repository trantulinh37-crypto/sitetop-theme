<?php
/* SCRIPT TUA ĐỜI 3 + LỖ HỔNG BƯỚC 1 — 28/09/2026.

   Chủ site gửi nguyên mã script: nó vá Date, Date.now, setTimeout, setInterval,
   requestAnimationFrame, WebSocket, Function.prototype.toString (để giấu mình), VÀ
   performance.now + performance.timeOrigin.

   ĐIỂM SƠ HỞ CỦA NÓ: vá performance.now bằng
        Object.defineProperty(performance, 'now', {...})
   tức chỉ đặt thuộc tính RIÊNG trên object `performance`. `Performance.prototype.now` vẫn là
   hàm gốc → gọi qua prototype là lấy được giờ thật. Hai đồng hồ đó của trình duyệt bình
   thường luôn bằng nhau, nên lệch = bắt quả tang.

   Test chạy CHÍNH script đó lên mã widget, trong tiến trình node riêng. */

$__t3_goc = dirname( __DIR__, 2 );
$__t3_wid = (string) file_get_contents( $__t3_goc . '/widget.js.php' );
$__t3_ajax = (string) file_get_contents( $__t3_goc . '/includes/shortlink-ajax.php' );

/* ---- 1. Đồng hồ phải lấy từ PROTOTYPE ---- */
assert_true( strpos( $__t3_wid, 'return Performance.prototype.now.call(window.performance);' ) !== false,
    'SONG CON: phai goi qua Performance.prototype.now — script tua chi va thuoc tinh rieng tren object' );
assert_true( strpos( $__t3_wid, 'document.timeline.currentTime' ) !== false,
    'Phai co du phong thu hai: dong ho cua Animation API' );
assert_true( strpos( $__t3_wid, 'function _dhSoiVa(){' ) !== false, 'Phai co ham soi dong ho bi va' );
assert_true( strpos( $__t3_wid, "_dhSoiVa();\n        if(_nhipSom('cd'))" ) !== false,
    'Phai soi moi nhip dem, truoc khi tru giay' );

/* ---- 2. Chạy thật script đời 3 lên mã widget ---- */
$__t3_node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
assert_true( $__t3_node !== '', 'Can node de chay doan JS that' );

$__t3_dir = sys_get_temp_dir() . '/st-tw3-' . getmypid();
@mkdir( $__t3_dir );
/* Bản rút gọn của script chủ site gửi — giữ nguyên CÁCH VÁ, bỏ phần WebSocket/serverCheck
   không liên quan tới đồng hồ. */
file_put_contents( $__t3_dir . '/warp.js', <<<'JS'
(() => {
  const _Date = Date, _dateNow = Date.now;
  const _perfNow = performance.now, _perfTO = performance.timeOrigin;
  const _setTimeout = window.setTimeout, _setInterval = window.setInterval;
  const _fToString = Function.prototype.toString;
  const t0Real = _dateNow.call(_Date), t0Perf = _perfNow.call(performance);
  let speed = 50;
  const sNow  = () => t0Real + (_dateNow.call(_Date) - t0Real) * speed;
  const sPerf = () => t0Perf + (_perfNow.call(performance) - t0Perf) * speed;
  const nativeMap = new WeakMap();
  const mark = (fn, s) => { nativeMap.set(fn, s); return fn; };
  const fakeToStr = function toString(){ return nativeMap.has(this) ? nativeMap.get(this) : _fToString.call(this); };
  Object.defineProperty(Function.prototype, 'toString', { value: fakeToStr, writable: true, configurable: true });
  const dateNowShim = mark(function now(){ return sNow(); }, 'function now() { [native code] }');
  window.Date = new Proxy(_Date, {
    construct(t,a,nt){ return a.length===0 ? Reflect.construct(t,[sNow()],nt) : Reflect.construct(t,a,nt); },
    apply(){ return new _Date(sNow()).toString(); },
    get(t,p,r){ if(p==='now') return dateNowShim; return Reflect.get(t,p,r); },
  });
  const perfNowShim = mark(function now(){ return sPerf(); }, 'function now() { [native code] }');
  try {
    Object.defineProperty(performance, 'now', { value: perfNowShim, writable: true, configurable: true });
    Object.defineProperty(performance, 'timeOrigin', { get: () => _perfTO, configurable: true });
  } catch (_) {}
  window.setTimeout  = mark(function setTimeout(fn,d=0,...a){ return _setTimeout.call(window,fn,Math.max(d/speed,0),...a); },'x');
  window.setInterval = mark(function setInterval(fn,d=0,...a){ return _setInterval.call(window,fn,Math.max(d/speed,1),...a); },'x');
})();
JS
);

/* BIẾN THỂ CHỈ VÁ ĐỒNG HỒ, không vá setTimeout/setInterval. Nhịp đếm khi đó tới đúng giờ
   nên chốt "nhịp sớm" KHÔNG nổ — chỉ còn lớp soi đồng hồ bắt được. Có biến thể này thì việc
   gỡ lớp soi mới làm test đỏ (bản đầu thiếu nó nên gỡ lớp soi mà test vẫn xanh). */
file_put_contents( $__t3_dir . '/warp_dh.js', <<<'JS'
(() => {
  const _Date = Date, _dateNow = Date.now;
  const _perfNow = performance.now;
  const t0Real = _dateNow.call(_Date), t0Perf = _perfNow.call(performance);
  let speed = 50;
  const sNow  = () => t0Real + (_dateNow.call(_Date) - t0Real) * speed;
  const sPerf = () => t0Perf + (_perfNow.call(performance) - t0Perf) * speed;
  window.Date = new Proxy(_Date, {
    construct(t,a,nt){ return a.length===0 ? Reflect.construct(t,[sNow()],nt) : Reflect.construct(t,a,nt); },
    get(t,p,r){ if(p==='now') return function now(){ return sNow(); }; return Reflect.get(t,p,r); },
  });
  try { Object.defineProperty(performance, 'now', { value: function now(){ return sPerf(); }, writable: true, configurable: true }); } catch (_) {}
})();
JS
);

$__t3_i = strpos( $__t3_wid, 'var _cdMocThat=null' );
$__t3_j = strpos( $__t3_wid, 'function _startCountdownInterval' );
$__t3_k = strpos( $__t3_wid, "},1000);\n}", $__t3_j ) + strlen( "},1000);\n}" );
assert_true( $__t3_i !== false && $__t3_k > $__t3_i, 'Cat duoc cum dong ho + vong dem tu widget' );
file_put_contents( $__t3_dir . '/cd.js', substr( $__t3_wid, $__t3_i, $__t3_k - $__t3_i ) );

file_put_contents( $__t3_dir . '/chay.js', str_replace( '__DIR__', $__t3_dir, <<<'JS'
let thatMs = 0;
global.window = global;
// Mô hình đúng như trình duyệt: now nằm trên PROTOTYPE, performance là instance.
function Performance(){}
Performance.prototype.now = function(){ return thatMs; };
global.Performance = Performance;
global.performance = Object.create(Performance.prototype);
Object.defineProperty(global.performance, 'timeOrigin', { value: 1700000000000, configurable: true });
global.document = { hidden:false, addEventListener(){}, timeline:{ get currentTime(){ return thatMs; } } };
let nhip=null, ham=null;
global.setInterval=function(fn,d){nhip=d;ham=fn;return 1;}; global.clearInterval=function(){};
global.setTimeout=function(){return 0;};
if (process.argv[2]==='tua')    { eval(require('fs').readFileSync('__DIR__/warp.js','utf8')); }
if (process.argv[2]==='tua_dh') { eval(require('fs').readFileSync('__DIR__/warp_dh.js','utf8')); }
var xong=null, bao=0;
var state={remaining:70,trafficType:'1step',step2Done:false,codeReady:false,tuaGio:0};
var timers={countdown:null}, _mouseCheckTimer=null, _lastMouseMove=0, _mouseIdleLimit=Number.MAX_VALUE;
var _bh={finalShown:false,firstDone:false};
function _pauseCountdown(){} function updateCountdownUI(){} function _bhTick(){}
function _bhShow(){} function _bhForceHide(){} function showStep2Guide(){ xong=thatMs; }
function getCode(){ xong=thatMs; } function reportBehavior(){ bao++; }
eval(require('fs').readFileSync('__DIR__/cd.js','utf8'));
_startCountdownInterval();
while (thatMs < 200000 && xong === null) { thatMs += nhip; ham(); }
console.log(JSON.stringify({ nhip:nhip, giay:xong===null?null:Math.round(xong/1000),
  nhip_som:_cdTuaDem, bat_duoc:state.tuaGio===1, bao:bao }));
JS
) );

$__t3_chay = function ( $che ) use ( $__t3_node, $__t3_dir ) {
    return json_decode( (string) shell_exec(
        escapeshellarg( $__t3_node ) . ' ' . escapeshellarg( $__t3_dir . '/chay.js' ) . ' ' . $che . ' 2>&1' ), true );
};
$__t3_thuong = $__t3_chay( 'thuong' );
$__t3_gian   = $__t3_chay( 'tua' );
$__t3_dh     = $__t3_chay( 'tua_dh' );
@unlink( $__t3_dir . '/warp_dh.js' );
@unlink( $__t3_dir . '/warp.js' ); @unlink( $__t3_dir . '/cd.js' ); @unlink( $__t3_dir . '/chay.js' ); @rmdir( $__t3_dir );

assert_true( is_array( $__t3_thuong ) && is_array( $__t3_gian ), 'Chay duoc ca hai kich ban' );
assert_equals( 1000, $__t3_thuong['nhip'], 'Khong tua: nhip 1000ms' );
assert_equals( 70, $__t3_thuong['giay'], 'Nguoi that: 70 giay that cho 70 giay dem nguoc' );
assert_false( $__t3_thuong['bat_duoc'], 'Nguoi that KHONG duoc bi bao oan' );
assert_equals( 0, $__t3_thuong['nhip_som'], 'Nguoi that khong co nhip som nao' );

assert_equals( 20, $__t3_gian['nhip'], 'Script doi 3 ep nhip xuong 20ms' );
assert_true( $__t3_gian['giay'] >= 60, 'CHAN DUOC: van phai mat ~67 giay that, do duoc: ' . $__t3_gian['giay'] );
assert_true( $__t3_gian['bat_duoc'], 'Phai BAT QUA TANG dong ho bi va (so prototype voi object)' );
assert_true( $__t3_gian['bao'] >= 1, 'Phai bao server' );

/* Biến thể chỉ vá đồng hồ: nhịp tới đúng giờ nên chốt "nhịp sớm" im lặng — chỉ lớp soi đồng
   hồ (so prototype với object) mới bắt được. Đây là phép DUY NHẤT canh lớp đó. */
assert_true( is_array( $__t3_dh ), 'Chay duoc kich ban chi va dong ho' );
assert_equals( 0, $__t3_dh['nhip_som'], 'Chi va dong ho thi nhip van dung gio — chot nhip som im lang' );
assert_true( $__t3_dh['bat_duoc'], 'Lop soi dong ho PHAI bat duoc bien the chi va performance.now' );
assert_true( $__t3_dh['bao'] >= 1, 'Va phai bao server' );

/* ---- 3. Bước 1 phải đủ giờ THẬT mới cho sang bước 2 ---- */
assert_true( strpos( $__t3_ajax, '$can_co = max( 10, $onsite - 5 );' ) !== false,
    'Phai doi du gio o trang thu nhat truoc khi mo buoc 2' );
assert_true( strpos( $__t3_ajax, 'if ( $spent_on_target < $can_co ) {' ) !== false,
    'Chua du gio thi KHONG duoc mo buoc 2' );
assert_true( strpos( $__t3_ajax, "sitetop_ghi_vet( \$sid, 'tuagio', 'buoc1_thieu=' . \$spent_on_target . '/' . \$can_co . 's' );" ) !== false,
    'Phai ghi vet de admin soi duoc' );
assert_true( strpos( $__t3_ajax, "'message' => 'Chưa đủ thời gian ở trang thứ nhất — còn thiếu '" ) !== false,
    'Phai bao ro con thieu bao nhieu giay' );
/* Chốt phải dựa trên target_visited_at — mốc MÁY CHỦ ghi, console không chạm tới. */
assert_true( strpos( $__t3_ajax, '$spent_on_target = $visited_ts ? max( 0, $now_ts - $visited_ts ) : 0;' ) !== false,
    'Gio o trang thu nhat phai do bang moc target_visited_at cua may chu' );

/* ---- 4. Mục theo dõi ở tab Lượt truy cập ---- */
$__t3_tab = (string) file_get_contents( $__t3_goc . '/includes/admin/tabs/tab-visits.php' );
assert_true( strpos( $__t3_tab, "preg_match( '/tuagio\\[([^\\]]*)\\]/', \$row->dau_vet, \$_tg_m )" ) !== false,
    'Cot Ly do phai doc vet tuagio de hien ca khi luot bi chan ngay tai cong' );
foreach ( array( 'bỏ bước 1', 'nhịp đòi mã', 'đồng hồ bị vá' ) as $__t3_nhan ) {
    assert_true( strpos( $__t3_tab, "'" . $__t3_nhan . "'" ) !== false,
        'Phai phan biet duoc duong bat: ' . $__t3_nhan );
}
/* Chuỗi điều kiện SQL có cả nháy đơn lẫn nháy kép — soi bằng hai mảnh ngắn cho khỏi phải
   thoát chuỗi lằng nhằng (bản đầu viết liền một mảnh dài làm vỡ cú pháp chính test này). */
assert_true( strpos( $__t3_tab, "\$reason_filter === 'tua_buoc1'" ) !== false,
    'Phai co nhanh loc rieng nhom bo buoc 1' );
assert_true( strpos( $__t3_tab, "LOCATE('buoc1_thieu', COALESCE(v.dau_vet,''))" ) !== false,
    'Nhanh do phai soi vet buoc1_thieu' );
assert_true( strpos( $__t3_tab, '>🕹 Tua giờ · bỏ bước 1</option>' ) !== false, 'Thieu o chon trong bo loc' );
/* Dùng LOCATE chứ không LIKE: gạch dưới trong 'tua_gio' là ký tự đại diện của LIKE. */
assert_true( strpos( $__t3_tab, "LOCATE('tua_gio', COALESCE(v.skip_reasons,'')) > 0 OR LOCATE('tuagio[', COALESCE(v.dau_vet,'')) > 0" ) !== false,
    'Bo loc tua gio phai dung LOCATE' );
