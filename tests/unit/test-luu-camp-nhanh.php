<?php
/* BẤM "LƯU" CAMP Ở ADMIN CHẬM — chủ site 11/10/2026: "tôi bấm lưu camp khi chỉnh sửa cho khách hàng load hơi chậm".
   Đo + đọc mã ra ba chỗ, test chạy THẬT cả ba:
   1. Mỗi lần lưu, sitetop_update_campaign báo .one đồng bộ ở shutdown (form admin luôn gửi cho_phep_nguon) bằng lệnh
      CHỜ tới 10 giây. Hàm hậu kỳ chỉ trả phản hồi sớm khi có fastcgi_finish_request() — mà hosting chạy LiteSpeed (LSAPI):
      PHP 8.1 ở đó chỉ có litespeed_finish_request() → người bấm phải chờ cả chuỗi .net → .one → .net.
      Nay: có cái nào gọi cái đó, rồi mới làm việc hậu kỳ.
   2. Lưu xong JS gọi THÊM một request lấy lại camp (~1,2–1,6 giây trên production, 2/4 lần đo dính 503) → nay máy chủ
      trả kèm dữ liệu camp trong phản hồi lưu; chỉ khi thiếu mới gọi lấy camp (đường cũ giữ nguyên làm đường lùi).
   3. Gặp 503 (CSDL chạm trần kết nối) thì nút kẹt "Đang lưu..." mãi / cả trang tải lại → nay gửi lại tối đa 2 lần,
      hết lượt thì báo và mở lại nút. */

$__lc2_goc = dirname( __DIR__, 2 );
$__lc2_tc  = (string) file_get_contents( $__lc2_goc . '/includes/admin/tabs/tab-campaigns.php' );
$__lc2_ad  = (string) file_get_contents( $__lc2_goc . '/includes/admin-dashboard.php' );
$__lc2_cn  = (string) file_get_contents( $__lc2_goc . '/includes/cau-noi-net-one.php' );

/* Cắt trọn một hàm (JS hoặc PHP) bằng đếm ngoặc, bỏ qua ngoặc nằm trong chuỗi '…' / "…". */
$__lc2_ham = function ( $ma, $dau ) {
    $p = strpos( $ma, $dau );
    if ( $p === false ) return '';
    $out = ''; $d = 0; $mo = false; $chuoi = '';
    for ( $i = $p, $n = strlen( $ma ); $i < $n; $i++ ) {
        $c = $ma[ $i ]; $out .= $c;
        if ( $chuoi !== '' ) { if ( $c === '\\' ) { $out .= $ma[ ++$i ]; continue; } if ( $c === $chuoi ) $chuoi = ''; continue; }
        if ( $c === "'" || $c === '"' ) { $chuoi = $c; continue; }
        if ( $c === '{' ) { $d++; $mo = true; }
        elseif ( $c === '}' ) { $d--; if ( $mo && $d === 0 ) break; }
    }
    return $out;
};

/* ============ 1. Hậu kỳ cầu nối: trả phản hồi TRƯỚC, gọi site bên kia SAU ============ */
$__lc2_hk = $__lc2_ham( $__lc2_cn, 'function sitetop_cn_hau_ky(' );
assert_true( $__lc2_hk !== '', 'Tim thay sitetop_cn_hau_ky' );
$__lc2_chay_hk = function ( $co_ham ) use ( $__lc2_hk ) {
    $ban = '<?php
$HOOK = array(); $VET = array();
function add_action( $h, $cb, $uu = 10 ) { $GLOBALS["HOOK"][] = array( $h, $cb, $uu ); }
' . ( $co_ham === 'litespeed' ? 'function litespeed_finish_request() { $GLOBALS["VET"][] = "tra_som_litespeed"; return true; }' : '' ) . '
' . ( $co_ham === 'fastcgi' ? 'function fastcgi_finish_request() { $GLOBALS["VET"][] = "tra_som_fastcgi"; return true; }' : '' ) . '
' . $__lc2_hk . '
sitetop_cn_hau_ky( function () { $GLOBALS["VET"][] = "goi_dong_bo"; } );
sitetop_cn_hau_ky( function () { $GLOBALS["VET"][] = "goi_san_sang"; } );
$so_hook = count( $HOOK ); $hook = $HOOK[0];
call_user_func( $hook[1] );
echo json_encode( array( "so_hook" => $so_hook, "ten" => $hook[0], "uu" => $hook[2], "vet" => $VET ) );';
    $f = sys_get_temp_dir() . '/st-lc2-hk-' . getmypid() . '-' . $co_ham . '.php';
    file_put_contents( $f, $ban );
    $r = (string) shell_exec( 'php ' . escapeshellarg( $f ) . ' 2>&1' );
    @unlink( $f );
    return json_decode( trim( $r ), true ) ?: $r;
};
$__lc2_ls = $__lc2_chay_hk( 'litespeed' );
assert_equals( array( 'so_hook' => 1, 'ten' => 'shutdown', 'uu' => 99, 'vet' => array( 'tra_som_litespeed', 'goi_dong_bo', 'goi_san_sang' ) ), $__lc2_ls,
    'LiteSpeed (production): litespeed_finish_request() chay TRUOC cac viec goi .one; mot hook shutdown cho ca request' );
$__lc2_fc = $__lc2_chay_hk( 'fastcgi' );
assert_equals( array( 'tra_som_fastcgi', 'goi_dong_bo', 'goi_san_sang' ), is_array( $__lc2_fc ) ? $__lc2_fc['vet'] : $__lc2_fc,
    'PHP-FPM: van dung fastcgi_finish_request() nhu cu' );
$__lc2_kh = $__lc2_chay_hk( 'khong' );
assert_equals( array( 'goi_dong_bo', 'goi_san_sang' ), is_array( $__lc2_kh ) ? $__lc2_kh['vet'] : $__lc2_kh,
    'Khong co ham nao (vd php -S): viec hau ky van chay, khong loi' );

/* ============ 2. Dữ liệu camp dùng chung — đủ trường như "lấy camp" cũ ============ */
$__lc2_du = $__lc2_ham( $__lc2_ad, 'function sitetop_admin_camp_du_lieu(' );
assert_true( $__lc2_du !== '', 'Tim thay sitetop_admin_camp_du_lieu' );
$__lc2_ban_du = '<?php
define( "SITETOP_PREFIX", "sitetop_" );
class W { public $prefix = "wp_"; public $q = array(); public $row = null;
  function prepare( $q, ...$a ) { return vsprintf( str_replace( "%d", "%s", $q ), $a ); }
  function get_row( $q ) { $this->q[] = $q; return $this->row; } }
$wpdb = new W();
function sitetop_campaign_destinations( $c ) { return array( $c->target_url ); }
function sitetop_bang_gia_cho_khach( $id ) { return array( "khach" => (int) $id ); }
' . $__lc2_du . '
$wpdb->row = (object) array( "id" => "42", "title" => "T", "keyword" => "kw", "target_url" => "https://a.vn", "task_type" => "keyword_search",
  "traffic_type" => "2step", "onsite_time" => "90", "price_per_view" => "1700.00", "user_reward" => "0.03", "daily_traffic" => "50",
  "quantity" => "1000", "status" => "paused", "customer_username" => "kh", "customer_id" => "7", "screenshot_desktop_url" => "d",
  "screenshot_mobile_url" => "m", "fixed_code" => "", "nocode_screenshot_url" => "", "step2_image_url" => "s2i", "step2_target_url" => "s2t",
  "kw_bat_go_tay" => "1", "khong_doi_cd" => "0", "cho_phep_nguon" => "1", "serp_page" => "3" );
$a = sitetop_admin_camp_du_lieu( 42 );
$wpdb->row = null; $b = sitetop_admin_camp_du_lieu( 43 );
echo json_encode( array( "a" => $a, "b" => $b, "q" => $wpdb->q ) );';
$__lc2_f = sys_get_temp_dir() . '/st-lc2-du-' . getmypid() . '.php';
file_put_contents( $__lc2_f, $__lc2_ban_du );
$__lc2_r = (string) shell_exec( 'php ' . escapeshellarg( $__lc2_f ) . ' 2>&1' );
@unlink( $__lc2_f );
$__lc2_D = json_decode( trim( $__lc2_r ), true );
assert_true( is_array( $__lc2_D ), 'Chay duoc sitetop_admin_camp_du_lieu. Ra: ' . substr( $__lc2_r, 0, 400 ) );
if ( is_array( $__lc2_D ) ) {
    /* Đúng danh sách trường mà sitetop_admin_get_campaign trả trước 11/10/2026 — thiếu trường nào là modal sửa hiện "Chưa có"
       rồi lần lưu sau ghi đè rỗng lên DB (bài học step2_*). */
    assert_equals( array( 'id', 'title', 'keyword', 'target_url', 'destination_urls', 'task_type', 'traffic_type', 'onsite_time', 'price_per_view',
        'user_reward', 'daily_traffic', 'quantity', 'status', 'customer_username', 'gia_khach', 'screenshot_desktop_url', 'screenshot_mobile_url',
        'fixed_code', 'nocode_screenshot_url', 'step2_image_url', 'step2_target_url', 'kw_bat_go_tay', 'khong_doi_cd', 'cho_phep_nguon', 'serp_page' ),
        array_keys( (array) $__lc2_D['a'] ), 'Du lieu camp du truong nhu sitetop_admin_get_campaign cu, dung thu tu' );
    assert_true( $__lc2_D['a']['gia_khach'] === array( 'khach' => 7 ) && $__lc2_D['a']['serp_page'] === 3 && $__lc2_D['a']['cho_phep_nguon'] === 1 && $__lc2_D['a']['step2_target_url'] === 's2t',
        'Gia tri: bang gia theo khach chu camp, cac co ep int, step2_* giu nguyen' );
    assert_true( $__lc2_D['b'] === null && strpos( (string) ( $__lc2_D['q'][1] ?? '' ), 'kc.id=43' ) !== false, 'Khong co camp → null (AJAX lay camp bao Not found)' );
}
$__lc2_get = $__lc2_ham( $__lc2_ad, 'function sitetop_ajax_admin_get_campaign(' );
$__lc2_upd = $__lc2_ham( $__lc2_ad, 'function sitetop_ajax_admin_update_campaign(' );
assert_true( strpos( $__lc2_get, '$d = sitetop_admin_camp_du_lieu($id);' ) !== false && strpos( $__lc2_get, "if (!\$d) wp_send_json_error('Not found');" ) !== false
          && strpos( $__lc2_get, 'wp_send_json_success($d);' ) !== false, 'Lay camp: dung ham chung, khong co camp → Not found' );
assert_true( strpos( $__lc2_upd, "wp_send_json_success(array('camp' => sitetop_admin_camp_du_lieu(\$id)));" ) !== false
          && (int) strpos( $__lc2_upd, '$result = sitetop_update_campaign($id, $_POST);' ) < (int) strpos( $__lc2_upd, "sitetop_admin_camp_du_lieu(\$id)" ),
    'Luu camp: tra kem du lieu camp DOC SAU KHI da ghi (so tu may chu, gom gia/thuong may chu tu tinh lai)' );

/* ============ 3. JS: gửi lại khi 503, vẽ dòng từ dữ liệu trả kèm (chạy bằng node) ============ */
$__lc2_js_gui = $__lc2_ham( $__lc2_tc, 'function admGuiAjax(' );
$__lc2_js_dong = $__lc2_ham( $__lc2_tc, 'function admCapNhatDongCamp(' );
assert_true( $__lc2_js_gui !== '' && $__lc2_js_dong !== '', 'Tim thay admGuiAjax + admCapNhatDongCamp trong tab-campaigns.php' );
assert_true( trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ) !== '', 'Can node' );
$__lc2_khung = <<<'JS'
var ADM_AJAX = '/wp-admin/admin-ajax.php', ADM_NONCE = 'n';
var HANG = [], GOI = [], NAP_LAI = 0, CHO = [];
global.setTimeout = function (f, ms) { CHO.push(ms); f(); return 0; };   // chạy ngay, ghi lại thời gian chờ
global.fetch = function (url, o) {
  var hanh = (o && o.body && o.body.get) ? o.body.get('action') : '?';
  GOI.push(hanh);
  var t = HANG.shift() || { status: 500, html: true };
  return Promise.resolve({ status: t.status, json: function () { return t.html ? Promise.reject(new SyntaxError('HTML')) : Promise.resolve(t.body); } });
};
var CO_DONG = true;
var DONG = { style: {}, querySelector: function () { return null; } };
global.document = { querySelector: function () { return CO_DONG ? DONG : null; } };
global.location = { reload: function () { NAP_LAI++; } };
var KQ = {};
function moi() { HANG = []; GOI = []; NAP_LAI = 0; CHO = []; CO_DONG = true; }
async function chay() {
  // a) 503, 503 rồi 200 → nhận kết quả, 3 lần gửi, chờ tăng dần
  moi(); HANG = [{ status: 503, html: true }, { status: 503, html: true }, { status: 200, body: { success: true, data: { camp: { id: '9' } } } }];
  var fd = new FormData(); fd.append('action', 'sitetop_admin_update_campaign');
  KQ.a = { kq: await admGuiAjax(fd), goi: GOI.length, cho: CHO.slice() };
  // b) 503 ba lần → từ chối (để .catch báo lỗi + mở lại nút), đúng 3 lần gửi
  moi(); HANG = [{ status: 503, html: true }, { status: 503, html: true }, { status: 503, html: true }, { status: 200, body: { success: true } }];
  try { await admGuiAjax(fd); KQ.b = 'KHONG_TU_CHOI'; } catch (e) { KQ.b = { tu_choi: true, goi: GOI.length }; }
  // c) 200 ngay → một lần gửi
  moi(); HANG = [{ status: 200, body: { success: true } }];
  KQ.c = { kq: await admGuiAjax(fd), goi: GOI.length };
  // d) lỗi khác (500 trang HTML) → không gửi lại, từ chối
  moi(); HANG = [{ status: 500, html: true }];
  try { await admGuiAjax(fd); KQ.d = 'KHONG_TU_CHOI'; } catch (e) { KQ.d = { tu_choi: true, goi: GOI.length }; }
  // e) vẽ dòng bằng dữ liệu máy chủ trả kèm → KHÔNG gọi thêm request, gọi xong()
  moi(); var xong = 0;
  admCapNhatDongCamp('9', function () { xong++; }, { id: '9', daily_traffic: 12, traffic_type: '2step', onsite_time: 90 });
  await new Promise(function (r) { setImmediate(r); });
  KQ.e = { goi: GOI.slice(), xong: xong, nap_lai: NAP_LAI };
  // f) không có dữ liệu trả kèm → gọi lấy camp (503 rồi 200) → vẽ, không nạp lại trang
  moi(); xong = 0; HANG = [{ status: 503, html: true }, { status: 200, body: { success: true, data: { id: '9' } } }];
  admCapNhatDongCamp('9', function () { xong++; });
  await new Promise(function (r) { setTimeout(r, 0); }); for (var i = 0; i < 20; i++) await new Promise(function (r) { setImmediate(r); });
  KQ.f = { goi: GOI.slice(), xong: xong, nap_lai: NAP_LAI };
  // g) lấy camp hỏng → nạp lại trang (đường lùi cũ)
  moi(); xong = 0; HANG = [{ status: 200, body: { success: false } }];
  admCapNhatDongCamp('9', function () { xong++; });
  for (var j = 0; j < 20; j++) await new Promise(function (r) { setImmediate(r); });
  KQ.g = { xong: xong, nap_lai: NAP_LAI };
  // h) không thấy dòng → nạp lại trang, không gọi gì
  moi(); CO_DONG = false;
  admCapNhatDongCamp('9', function () {}, { id: '9' });
  KQ.h = { goi: GOI.length, nap_lai: NAP_LAI };
  console.log(JSON.stringify(KQ));
}
JS;
$__lc2_fj = sys_get_temp_dir() . '/st-lc2-js-' . getmypid() . '.js';
file_put_contents( $__lc2_fj, $__lc2_khung . "\n" . $__lc2_js_gui . "\n" . $__lc2_js_dong . "\nchay().catch(function(e){ console.log('LOI ' + e); });\n" );
$__lc2_rj = (string) shell_exec( 'node ' . escapeshellarg( $__lc2_fj ) . ' 2>&1' );
@unlink( $__lc2_fj );
$__lc2_J = json_decode( trim( $__lc2_rj ), true );
assert_true( is_array( $__lc2_J ), 'Chay duoc JS luu camp bang node. Ra: ' . substr( $__lc2_rj, 0, 500 ) );
if ( is_array( $__lc2_J ) ) {
    assert_true( ( $__lc2_J['a']['kq']['success'] ?? false ) === true && $__lc2_J['a']['goi'] === 3 && $__lc2_J['a']['cho'] === array( 1200, 2400 ),
        '503, 503 roi 200 → nhan ket qua sau 3 lan gui, cho 1,2s roi 2,4s. Ra: ' . json_encode( $__lc2_J['a'] ) );
    assert_equals( array( 'tu_choi' => true, 'goi' => 3 ), $__lc2_J['b'], '503 ca 3 lan → tu choi (de bao loi + mo lai nut), khong gui qua 3 lan' );
    assert_equals( 1, $__lc2_J['c']['goi'], 'Thanh cong ngay → gui 1 lan' );
    assert_equals( array( 'tu_choi' => true, 'goi' => 1 ), $__lc2_J['d'], 'Loi khac 503 (vd 500) → KHONG gui lai' );
    assert_equals( array( 'goi' => array(), 'xong' => 1, 'nap_lai' => 0 ), $__lc2_J['e'], 'Co du lieu tra kem → ve dong ngay, KHONG goi them request, khong nap lai trang' );
    assert_equals( array( 'goi' => array( 'sitetop_admin_get_campaign', 'sitetop_admin_get_campaign' ), 'xong' => 1, 'nap_lai' => 0 ), $__lc2_J['f'],
        'Thieu du lieu tra kem → lay camp (gap 503 thi gui lai), ve dong, khong nap lai trang' );
    assert_equals( array( 'xong' => 0, 'nap_lai' => 1 ), $__lc2_J['g'], 'Lay camp hong → nap lai trang (duong lui cu)' );
    assert_equals( array( 'goi' => 0, 'nap_lai' => 1 ), $__lc2_J['h'], 'Khong thay dong → nap lai trang, khong goi gi' );
}

/* Vị trí trong form: lưu + mở khung sửa đều đi qua admGuiAjax và có .catch (không còn kẹt nút) */
$__lc2_submit = substr( $__lc2_tc, (int) strpos( $__lc2_tc, "document.getElementById('admEditCampForm').addEventListener('submit'" ), 6000 );
assert_true( strpos( $__lc2_submit, "admGuiAjax(fd).then(function(r){" ) !== false && strpos( $__lc2_submit, '}, r.data && r.data.camp);' ) !== false
          && strpos( $__lc2_submit, "btn.disabled = false; btn.textContent = 'Lưu thay đổi';\n    });\n});" ) !== false,
    'Nut Luu: gui qua admGuiAjax, truyen du lieu tra kem, .catch mo lai nut' );
$__lc2_mo = $__lc2_ham( $__lc2_tc, 'function openAdminEditCamp(' );
assert_true( strpos( $__lc2_mo, 'admGuiAjax(fd).then(function(r){' ) !== false && strpos( $__lc2_mo, '}).catch(function(){ alert(' ) !== false,
    'Mo khung sua: gui qua admGuiAjax (gap 503 tu gui lai), loi thi bao thay vi im lang' );

/* ============ 4. Tải ảnh camp (chủ site 11/10/2026: "tải ảnh vào camp lên lần 1 sẽ bị lỗi") ============
   Log production: các lượt "Lỗi kết nối" là 503 (db-error, CSDL chạm trần kết nối — ảnh CHƯA được lưu), lần sau thì qua.
   Nay mọi lời gọi AJAX của tab đi qua admGuiAjax (gặp 503 tự gửi lại); hỏng hẳn thì xoá ô chọn file để chọn lại đúng
   file đó vẫn tải lên được, và ô trạng thái widget không còn bị khoá mãi. */
assert_equals( 1, substr_count( $__lc2_tc, 'fetch(' ), 'Tab Chien dich: fetch( chi con DUY NHAT trong admGuiAjax — moi loi goi AJAX deu duoc gui lai khi 503' );
$__lc2_up1 = $__lc2_ham( $__lc2_tc, 'function admImgbbUpload(' );
$__lc2_up2 = $__lc2_ham( $__lc2_tc, 'function admEditImgbbUpload(' );
$__lc2_wg  = $__lc2_ham( $__lc2_tc, 'function updateWidgetCodeStatus(' );
assert_true( strpos( $__lc2_up1, 'admGuiAjax(fd).then(' ) !== false && substr_count( $__lc2_up1, "input.value='';" ) === 2, 'Tai anh form tao camp: qua admGuiAjax, loi thi xoa o chon file (2 nhanh loi)' );
assert_true( strpos( $__lc2_up2, 'admGuiAjax(fd).then(' ) !== false && substr_count( $__lc2_up2, "input.value = '';" ) === 2, 'Tai anh khung sua: qua admGuiAjax, loi thi xoa o chon file (2 nhanh loi)' );
assert_true( strpos( $__lc2_wg, 'admGuiAjax(fd)' ) !== false && strpos( $__lc2_wg, ".catch(function(){" ) !== false && substr_count( $__lc2_wg, 'sel.disabled = false;' ) === 2,
    'Trang thai gan widget: qua admGuiAjax; loi mang/503 het luot → mo khoa o chon' );

$__lc2_khung2 = <<<'JS'
var ADM_AJAX = '/wp-admin/admin-ajax.php', ADM_NONCE = 'n';
var HANG = [], GOI = 0;
global.setTimeout = function (f) { f(); return 0; };
global.fetch = function () { GOI++; var t = HANG.shift() || { status: 500, html: true };
  return Promise.resolve({ status: t.status, json: function () { return t.html ? Promise.reject(new SyntaxError('HTML')) : Promise.resolve(t.body); } }); };
var EL = {};
function el(id) { return EL[id] || (EL[id] = { id: id, innerHTML: '', value: '', textContent: '', style: {} }); }
global.document = { getElementById: function (id) { return el(id); } };
async function doi() { for (var i = 0; i < 30; i++) await new Promise(function (r) { setImmediate(r); }); }
async function chay() {
  var KQ = {};
  // a) 503 rồi thành công → hiện ảnh, gán URL vào ô ẩn, mở lại nút, 2 lần gửi
  EL = {}; GOI = 0; HANG = [{ status: 503, html: true }, { status: 200, body: { success: true, data: { url: 'https://sitetop.net/wp-content/uploads/a.png' } } }];
  var inp = { files: [{ name: 'a.png' }], value: 'C:\\fakepath\\a.png' };
  admEditImgbbUpload(inp, 'xem', 'an', 'nut'); await doi();
  KQ.a = { goi: GOI, xem: el('xem').innerHTML.indexOf('<img src="https://sitetop.net/wp-content/uploads/a.png"') === 0, an: el('an').value, txt: el('anTxt').textContent, nut: el('nut').style.pointerEvents, o_file: inp.value };
  // b) 503 cả ba lần → báo lỗi, mở lại nút, xoá ô chọn file, đúng 3 lần gửi, ô ẩn không bị ghi
  EL = {}; GOI = 0; HANG = [{ status: 503, html: true }, { status: 503, html: true }, { status: 503, html: true }];
  var inp2 = { files: [{ name: 'b.png' }], value: 'C:\\fakepath\\b.png' };
  admEditImgbbUpload(inp2, 'xem', 'an', 'nut'); await doi();
  KQ.b = { goi: GOI, xem: el('xem').innerHTML.indexOf('Lỗi kết nối') !== -1, an: el('an').value, nut: el('nut').style.pointerEvents, o_file: inp2.value };
  // c) máy chủ từ chối (vd file không phải ảnh) → hiện lý do, xoá ô chọn file, không gửi lại
  EL = {}; GOI = 0; HANG = [{ status: 200, body: { success: false, data: 'File không phải ảnh hợp lệ' } }];
  var inp3 = { files: [{ name: 'c.txt' }], value: 'c.txt' };
  admEditImgbbUpload(inp3, 'xem', 'an', 'nut'); await doi();
  KQ.c = { goi: GOI, xem: el('xem').innerHTML.indexOf('File không phải ảnh hợp lệ') !== -1, o_file: inp3.value };
  console.log(JSON.stringify(KQ));
}
JS;
$__lc2_fj2 = sys_get_temp_dir() . '/st-lc2-anh-' . getmypid() . '.js';
file_put_contents( $__lc2_fj2, $__lc2_khung2 . "\n" . $__lc2_js_gui . "\n" . $__lc2_up2 . "\nchay().catch(function(e){ console.log('LOI ' + e); });\n" );
$__lc2_ra2 = (string) shell_exec( 'node ' . escapeshellarg( $__lc2_fj2 ) . ' 2>&1' );
@unlink( $__lc2_fj2 );
$__lc2_A = json_decode( trim( $__lc2_ra2 ), true );
assert_true( is_array( $__lc2_A ), 'Chay duoc ham tai anh bang node. Ra: ' . substr( $__lc2_ra2, 0, 400 ) );
if ( is_array( $__lc2_A ) ) {
    assert_equals( array( 'goi' => 2, 'xem' => true, 'an' => 'https://sitetop.net/wp-content/uploads/a.png', 'txt' => 'https://sitetop.net/wp-content/uploads/a.png', 'nut' => '', 'o_file' => 'C:\\fakepath\\a.png' ),
        $__lc2_A['a'], 'Tai anh: lan 1 dinh 503 → tu gui lai, lan 2 qua: hien anh, ghi URL, mo lai nut' );
    assert_equals( array( 'goi' => 3, 'xem' => true, 'an' => '', 'nut' => '', 'o_file' => '' ), $__lc2_A['b'],
        'Tai anh: 503 ca 3 lan → bao "Loi ket noi", mo lai nut, xoa o chon file (chon lai dung file van tai len), khong ghi URL' );
    assert_equals( array( 'goi' => 1, 'xem' => true, 'o_file' => '' ), $__lc2_A['c'], 'May chu tu choi file → hien ly do, khong gui lai, xoa o chon file' );
}
