<?php
/* CHỐNG TUA ĐỒNG HỒ BẰNG CONSOLE — 27/09/2026.

   Script đang lan trên mạng: ghi đè window.Date + setTimeout + setInterval để nhân tốc độ
   50 lần. Widget đếm ngược theo NHỊP (mỗi tick trừ 1 giây) nên 70 giây đốt xong trong hơn
   một giây.

   Test này KHÔNG dò chuỗi: nó lấy đúng đoạn mã chống tua trong widget.js.php, dán NGUYÊN VĂN
   script tua của kẻ gian vào cùng một môi trường, rồi ĐẾM XEM countdown trôi bao nhiêu giây.
   Sửa hỏng chốt là con số lệch ngay. */

$__tg_goc = dirname( __DIR__, 2 );
$__tg_wid = (string) file_get_contents( $__tg_goc . '/widget.js.php' );

/* ---- 1. Lấy đoạn mã thật ---- */
/* Cắt tới ngay trước _tuaGioGhiNhan — phần đuôi là chú thích, để nguyên vẫn là JS hợp lệ.
   (Bản đầu đòi '\n}\n' dính liền nên trượt vì giữa hai hàm có một khối chú thích.) */
assert_true( preg_match( '#(var _cdMocThat=null.*?)function _tuaGioGhiNhan#s', $__tg_wid, $__tg_m ) === 1,
    'Lay duoc ham dong ho that + _nhipSom tu widget.js.php' );
$__tg_ma = $__tg_m[1];
assert_true( strpos( $__tg_ma, 'window.performance.now()' ) !== false,
    'SONG CON: phai do bang performance.now() — Date da bi script ghi de' );

/* Chốt phải nằm TRƯỚC chỗ trừ giây, nếu không thì trừ xong mới kiểm là vô nghĩa. */
$__tg_chot = strpos( $__tg_wid, "if(_nhipSom('cd')){_tuaGioGhiNhan();return;}" );
$__tg_tru  = strpos( $__tg_wid, 'state.remaining--;' );
assert_true( $__tg_chot !== false && $__tg_tru !== false && $__tg_chot < $__tg_tru,
    'Chot nhip som phai dung TRUOC state.remaining--' );
assert_true( strpos( $__tg_wid, "timers.behavior=setInterval(function(){ if(_nhipSom('bh'))return; bdata.time++; },1000);" ) !== false,
    'Bo dem time_on_page cung phai di qua dong ho that' );

/* ---- 2. Chạy thật: script tua của kẻ gian vs mã của mình ---- */
$__tg_node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
assert_true( $__tg_node !== '', 'Can node de chay doan JS that (khong co node thi test nay mu)' );

$__tg_tua = <<<'JS'
(function() {
  const RealDate = Date;
  const realNow = Date.now;
  const t0 = realNow.call(RealDate);
  const speed = 50;
  function FakeDate(...args) {
    if (!(this instanceof FakeDate)) return new RealDate(t0 + (realNow.call(RealDate) - t0) * speed).toString();
    if (args.length === 0) return new RealDate(t0 + (realNow.call(RealDate) - t0) * speed);
    return new RealDate(...args);
  }
  FakeDate.now = () => t0 + (realNow.call(RealDate) - t0) * speed;
  FakeDate.prototype = RealDate.prototype;
  window.Date = FakeDate;
  const _setTimeout = window.setTimeout;
  window.setTimeout = (fn, delay = 0, ...a) => _setTimeout(fn, delay / speed, ...a);
  const _setInterval = window.setInterval;
  window.setInterval = (fn, delay = 0, ...a) => _setInterval(fn, Math.max(delay / speed, 1), ...a);
})();
JS;

$__tg_kb = <<<JS
// Đồng hồ THẬT ảo — thứ duy nhất test điều khiển; performance.now() đọc nó.
let thatMs = 0;
global.window = global;
global.performance = { now: () => thatMs };
// Bắt lấy khoảng nhịp mà script tua truyền xuống tầng dưới.
let nhipThat = null;
global.setInterval = function (fn, d) { nhipThat = d; return 0; };
global.setTimeout  = function (fn, d) { return 0; };

const COTUA = process.argv[2] === 'tua';
if (COTUA) { $__tg_tua }

{$__tg_ma}

// Đăng ký vòng đếm y như widget làm: setInterval(..., 1000)
_cdMocThat = _dongHoThat();
window.setInterval(function(){}, 1000);
const nhip = nhipThat;            // có tua: 20ms. không tua: 1000ms.

let conLai = 70, daTru = 0;
// Chạy đúng 70 GIÂY ĐỜI THẬT, nhịp tới theo tốc độ mà script tua áp đặt.
while (thatMs < 70000 && conLai > 0) {
    thatMs += nhip;
    if (_nhipSom('cd')) { _cdTuaDem++; continue; }   // đúng nhánh widget bỏ qua nhịp
    conLai--; daTru++;
}
console.log(JSON.stringify({ nhip: nhip, daTru: daTru, tuaDem: _cdTuaDem }));
JS;

$__tg_f = sys_get_temp_dir() . '/st-tua-' . getmypid() . '.js';
file_put_contents( $__tg_f, $__tg_kb );

$__tg_thuong = json_decode( (string) shell_exec( escapeshellarg( $__tg_node ) . ' ' . escapeshellarg( $__tg_f ) . ' thuong 2>&1' ), true );
$__tg_gian   = json_decode( (string) shell_exec( escapeshellarg( $__tg_node ) . ' ' . escapeshellarg( $__tg_f ) . ' tua 2>&1' ), true );
@unlink( $__tg_f );

assert_true( is_array( $__tg_thuong ) && is_array( $__tg_gian ), 'Chay duoc ca hai kich ban trong node' );

// Trình duyệt thường: nhịp 1000ms, 70 giây thật trôi đúng 70 giây countdown, không báo oan.
assert_equals( 1000, $__tg_thuong['nhip'], 'Khong tua: nhip van la 1000ms' );
assert_equals( 70, $__tg_thuong['daTru'], 'Nguoi dung THAT phai dem du 70 giay trong 70 giay' );
assert_equals( 0, $__tg_thuong['tuaDem'], 'Nguoi dung that KHONG duoc bi bao oan la tua gio' );

// Có script tua: nhịp bị ép xuống 20ms, nhưng countdown vẫn chỉ trôi theo đời thật.
assert_equals( 20, $__tg_gian['nhip'], 'Script tua ep nhip xuong 20ms (1000/50)' );
assert_true( $__tg_gian['daTru'] <= 74 && $__tg_gian['daTru'] >= 66,
    'CHAN DUOC: tua 50 lan van chi troi ~70 giay trong 70 giay that, dem duoc: ' . $__tg_gian['daTru'] );
assert_true( $__tg_gian['tuaDem'] > 1000,
    'Phai dem duoc rat nhieu nhip toi som de bao server, dem duoc: ' . $__tg_gian['tuaDem'] );
// Nếu KHÔNG có chốt thì 70 giây thật sẽ đốt hết 70 giây countdown trong 1,4 giây → daTru = 70
// nhưng thatMs mới có 1400. Phép so dưới đây mới là thứ phân biệt chốt sống hay chết.
assert_true( $__tg_gian['daTru'] * 1000 <= 75000,
    'Countdown khong duoc chay nhanh hon doi thuc' );

/* ---- 3. Máy chủ: chốt không giả được ---- */
$__tg_ajax = (string) file_get_contents( $__tg_goc . '/includes/shortlink-ajax.php' );
assert_true( strpos( $__tg_ajax, 'if ( $tuoi_phien >= 0 && $khai > ( $tuoi_phien * 3 + 30 ) ) $tua_lech = true;' ) !== false,
    'May chu phai tu so so giay khai ra voi tuoi that cua phien' );

/* SỐNG CÒN — bài học 28/09: mốc so sánh phải là mốc SỚM NHẤT máy chủ từng thấy, không phải
   created_at. start_timer bước 2 ghi created_at = now - công, tức dời mốc VỀ PHÍA TRƯỚC;
   lấy created_at làm mốc đã chặn oan 41/44 lượt người thật (lệch trung bình 162 giây). */
assert_true( strpos( $__tg_ajax, "SELECT id, created_at, target_visited_at FROM {\$p}shortlink_visits WHERE session_id=%s AND ip_address=%s" ) !== false,
    'Phai lay ca target_visited_at de lam moc' );
assert_true( strpos( $__tg_ajax, "if ( ! empty( \$visit->target_visited_at )
             && strtotime( \$visit->target_visited_at ) < strtotime( \$moc ) ) {" ) !== false,
    'Phai lay moc SOM HON trong hai cai (created_at vs target_visited_at)' );
assert_true( strpos( $__tg_ajax, '$tuoi_phien = strtotime( sitetop_current_time() ) - strtotime( $moc );' ) !== false,
    'Tuoi phien phai tinh tu moc som nhat do' );
/* Chỉ soi TRONG khối nhận biết tua giờ — các hàm khác trong file vẫn đo từ created_at một
   cách chính đáng (đó là mốc tính giờ onsite của phiên). */
assert_true( preg_match( '#(\$tua_bao  = ! empty.*?\$tua_lech = true;\n    \})#s', $__tg_ajax, $__tg_kb ) === 1,
    'Lay duoc khoi nhan biet tua gio' );
assert_true( strpos( $__tg_kb[1], 'strtotime( $visit->created_at )' ) === false,
    'Trong khoi nay KHONG duoc do thang tu created_at nua' );
assert_true( strpos( $__tg_ajax, "set_transient( 'sitetop_tuagio_' . \$sid, 1, 2 * HOUR_IN_SECONDS );" ) !== false,
    'Dinh co vao transient de luc tra thuong doc lai' );
// Quên dọn là nhiệm vụ mới thừa hưởng cờ của nhiệm vụ cũ — lỗi đã từng dính với ref_lech.
assert_true( strpos( $__tg_ajax, "'sitetop_tuagio_'," ) !== false,
    'Doi nhiem vu phai don co tua gio' );

$__tg_ver = (string) file_get_contents( $__tg_goc . '/includes/shortlink-verification.php' );
assert_true( strpos( $__tg_ver, "if ( \$tg_muc > 0 && get_transient( 'sitetop_tuagio_' . \$session_id ) ) {" ) !== false,
    'Luc tra thuong phai doc co tua gio' );
assert_true( strpos( $__tg_ver, "if ( \$tg_muc >= 2 ) \$should_pay_reward = false;" ) !== false,
    'Muc 2 = khong tra thuong' );
assert_true( strpos( $__tg_ver, "\$skip_reasons[] = 'tua_gio';" ) !== false, 'Phai ghi ly do de admin soi' );
assert_true( strpos( $__tg_ver, "delete_transient( 'sitetop_tuagio_' . \$session_id );" ) !== false,
    'Chot xong phai xoa co' );
// Giữ đúng lối cũ: KHÔNG chặn cấp mã (kẻ gian không biết mình lộ).
assert_true( strpos( $__tg_ver, "tua_gio" ) < strpos( $__tg_ver, "'step'            =>" ),
    'Chot tua gio nam trong nhanh tinh tien, khong phai nhanh chan cap ma' );

/* ---- 4. Admin nhìn thấy ---- */
$__tg_tab = (string) file_get_contents( $__tg_goc . '/includes/admin/tabs/tab-visits.php' );
assert_true( strpos( $__tg_tab, "'tua_gio'                  =>" ) !== false, 'Phai co nhan trong bang Ly do' );
assert_true( strpos( $__tg_tab, "\$reason_filter === 'tua_gio'" ) !== false, 'Phai loc rieng duoc nhom nay' );
$__tg_set = (string) file_get_contents( $__tg_goc . '/includes/admin/tabs/tab-settings.php' );
assert_true( substr_count( $__tg_set, 'tua_gio_muc' ) >= 4, 'Phai co o chinh muc trong Cai dat' );

/* ---- 5. MỨC 3: chặn cấp mã (chủ site chốt 28/09/2026) ---- */
$__tg_fn = (string) file_get_contents( $__tg_goc . '/includes/shortlink-functions.php' );
assert_true( strpos( $__tg_fn, "if ( (int) sitetop_get_option( 'tua_gio_muc', 3 ) >= 3\n         && get_transient( 'sitetop_tuagio_' . \$session_id ) ) {" ) !== false,
    'Muc 3 phai chan ngay trong sitetop_get_widget_code' );
assert_true( strpos( $__tg_fn, "array( 'chan_tuagio' => 1 )" ) !== false,
    'Phai gui co chan_tuagio de widget DUNG HAN vong goi lai' );

/* Chốt phải đứng TRƯỚC nhánh trả lại mã đã có trong CSDL, nếu không thì kẻ tua xin lại
   lần hai là lấy được mã cũ. */
$__tg_p_chan = strpos( $__tg_fn, "sitetop_ghi_vet( \$session_id, 'chan_tuagio', 'muc3' );" );
$__tg_p_cache = strpos( $__tg_fn, 'If code already exists in DB' );
assert_true( $__tg_p_chan !== false && $__tg_p_cache !== false && $__tg_p_chan < $__tg_p_cache,
    'Chot chan phai dung TRUOC nhanh tra lai ma da co trong CSDL' );
/* ... và trước chốt thời gian, để không làm nhiễu bộ đếm đòi-mã-sớm. */
assert_true( $__tg_p_chan < strpos( $__tg_fn, "\$tm_key = 'sitetop_toofast_' . \$session_id;" ),
    'Chot chan phai dung truoc bo dem doi-ma-som' );

/* Widget phải dừng hẳn — KHÔNG được rơi vào nhánh hẹn gọi lại 3 giây. Dưới script tua 50x,
   3 giây thành 60ms: giữ nguyên là biến máy kẻ gian thành cỗ máy dội cổng admin-ajax. */
assert_true( strpos( $__tg_wid, "}else if(r.data&&r.data.data&&r.data.data.chan_tuagio){" ) !== false,
    'Widget phai nhan biet co chan_tuagio' );
assert_true( preg_match( '#chan_tuagio\)\{.*?_chanVinhVien\(msg\);#s', $__tg_wid ) === 1,
    'Nhan co xong phai goi _chanVinhVien' );
assert_true( preg_match( '#function _chanVinhVien\(msg\)\{(.*?)\n\}#s', $__tg_wid, $__tg_cv ) === 1,
    'Phai co ham dung han phien' );
assert_true( strpos( $__tg_cv[1], 'setTimeout' ) === false && strpos( $__tg_cv[1], 'setInterval' ) === false,
    'SONG CON: ham dung han KHONG duoc hen goi lai bat cu thu gi' );
assert_true( strpos( $__tg_cv[1], 'clearInterval(timers[k])' ) !== false,
    'Phai don sach moi bo dem dang chay' );

/* Ba mức phải cùng một mặc định, lệch nhau là nơi chặn nơi không. */
assert_true( substr_count( $__tg_fn, "sitetop_get_option( 'tua_gio_muc', 3 )" ) === 1
          && substr_count( $__tg_ver, "sitetop_get_option( 'tua_gio_muc', 3 )" ) === 1,
    'Mac dinh muc 3 phai giong nhau o ca cho chan lan cho tra thuong' );
$__tg_set2 = (string) file_get_contents( $__tg_goc . '/includes/admin/tabs/tab-settings.php' );
assert_true( strpos( $__tg_set2, "selected(_lno('tua_gio_muc',3),3)" ) !== false, 'O chon phai co muc 3' );
assert_true( strpos( $__tg_set2, "_lno('tua_gio_muc',2)" ) === false, 'Khong duoc con mac dinh cu la 2 trong o chon' );
