<?php
/* CHỐT TỰ ĐỘNG: TỶ LỆ KHAI TÊN MÁY — 02/10/2026.

   Từ 2022 Chrome trên Android KHÔNG khai tên máy nữa: mọi điện thoại đều gửi đúng chuỗi
   "Android 10; K". Lưu lượng người thật vì vậy dồn vào chuỗi đó; công cụ giả lập phải tự bịa
   tên máy cho "đa dạng" — và chính sự đa dạng ấy tố cáo nó.

   Đo trên production 02/10 (14 ngày, 54 tài khoản ≥200 lượt): 10 tài khoản ở 94,9–99,9%,
   tất cả tài khoản thật đều dưới 11%, ở giữa không có ai.

   Test chạy THẬT câu truy vấn đếm trên SQLite với dữ liệu dựng sẵn, và chạy thật hàm xét cờ. */

$__ua_goc = dirname( __DIR__, 2 );
$__ua_bh  = (string) file_get_contents( $__ua_goc . '/includes/behavior-analytics.php' );
$__ua_ver = (string) file_get_contents( $__ua_goc . '/includes/shortlink-verification.php' );
$__ua_tu  = (string) file_get_contents( $__ua_goc . '/includes/admin/tabs/tab-users.php' );
$__ua_tv  = (string) file_get_contents( $__ua_goc . '/includes/admin/tabs/tab-visits.php' );

/* ---- 1. Chuỗi rút gọn phải đúng y nguyên ---- */
assert_true( strpos( $__ua_bh, "return 'Android 10; K)';" ) !== false,
    'Chuoi rut gon phai la "Android 10; K)" — do tren production: 16.900 luot/3 ngay dung dang nay' );

/* ---- 2. Câu đếm: CHẠY THẬT điều kiện lấy từ mã nguồn ---- */
assert_true( preg_match( '#SUM\( user_agent LIKE %s(.*?)\) AS khai#s', $__ua_bh, $__ua_m ) === 1,
    'Lay duoc dieu kien dem tu ma nguon' );

/* Dịch đúng hai chỗ MySQL mà SQLite không có, giữ nguyên phần còn lại:
     LOCATE(a, user_agent) -> INSTR(user_agent, a)      · AS UNSIGNED -> AS INTEGER
   SUBSTRING_INDEX thì khai hẳn một hàm PHP cùng cách chạy. Nhờ vậy test chạy ĐÚNG điều kiện
   trong mã nguồn, không phải một bản chép tay dễ lệch. */
$dk = $__ua_m[1];
$dk = preg_replace( '#LOCATE\(([^,]+), user_agent\)#', 'INSTR(user_agent, $1)', $dk );
$dk = str_replace( array( 'AS UNSIGNED', '%s' ), array( 'AS INTEGER', "'Android 10; K)'" ), $dk );
$dk = "user_agent LIKE '%Android%'" . $dk;

$db = new PDO( 'sqlite::memory:' );
$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$db->sqliteCreateFunction( 'SUBSTRING_INDEX', function ( $chuoi, $dau, $n ) {
    $phan = explode( $dau, (string) $chuoi );
    return $n < 0 ? implode( $dau, array_slice( $phan, $n ) ) : implode( $dau, array_slice( $phan, 0, $n ) );
}, 3 );
$db->exec( 'CREATE TABLE v (ua TEXT)' );

/* UA thật, chép từ production .net ngày 03/10 */
$mau = array(
    // [ user agent, có phải dấu hiệu không, mô tả ]
    array( 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36',
           0, 'Chrome rut gon — dung chuan, KHONG phai dau hieu' ),
    array( 'Mozilla/5.0 (Linux; Android 13; SM-A556B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.200 Mobile Safari/537.36',
           1, 'Chrome 131 ma van khai Android 13 — KHONG THE co that' ),
    array( 'Mozilla/5.0 (Linux; Android 14; 2311DRK48G) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Mobile Safari/537.36',
           1, 'Chrome 128 khai Android 14 — dau hieu' ),
    array( 'Mozilla/5.0 (Linux; Android 11; vivo 1906) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/131.0.6778.200 Mobile Safari/537.36 VivoBrowser/16.5.2.0',
           0, 'SONG CON: VivoBrowser khai doi Android that — nguoi Viet dung rat nhieu' ),
    array( 'Mozilla/5.0 (Linux; Android 13; Redmi Note 11 Build/TKQ1.221114.001) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.7049.79 Mobile Safari/537.36 XiaoMi/MiuiBrowser/14.54.0-gn',
           0, 'SONG CON: MiuiBrowser cua Xiaomi — hop le' ),
    array( 'Mozilla/5.0 (Linux; U; Android 13; vi-vn; CPH2365 Build/TP1A.220905.001) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.5970.168 Mobile Safari/537.36 HeyTapBrowser/45.14.7.1',
           0, 'SONG CON: HeyTapBrowser cua Oppo/Realme — hop le' ),
    array( 'Mozilla/5.0 (Linux; Android 12; SM-A032F) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/23.0 Chrome/115.0.0.0 Mobile Safari/537.36',
           0, 'Samsung Internet — hop le' ),
    array( 'Mozilla/5.0 (Android 13; Mobile; rv:131.0) Gecko/131.0 Firefox/131.0',
           0, 'Firefox khai doi Android that — hop le' ),
    array( 'Mozilla/5.0 (Linux; Android 9; vivo Y19 Build/PPR1.180610.011) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.61 Mobile Safari/537.36',
           0, 'SONG CON: Chrome 101 (truoc ban 110) khai doi that la dung — may cu, khong phai dau hieu' ),
    array( 'Mozilla/5.0 (Linux; Android 12; V2111 Build/SP1A; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/131.0.0.0 Mobile Safari/537.36',
           0, 'WebView trong app (Facebook, Zalo…) — hop le' ),
    array( 'Mozilla/5.0 (Linux; Android 13; SM-A556B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36 EdgA/131.0.2903.87',
           0, 'SONG CON: Edge tren Android khai doi that — hop le, phai lot qua' ),
    array( 'Mozilla/5.0 (Linux; Android 12; V2111) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36 OPR/89.0.0.0',
           0, 'SONG CON: Opera tren Android khai doi that — hop le, phai lot qua' ),
    array( 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 Version/18.7 Mobile/15E148 Safari/604.1',
           0, 'iPhone — khong phai Android, khong tinh' ),
    array( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36',
           0, 'May tinh — khong tinh' ),
);
$st = $db->prepare( 'INSERT INTO v (ua) VALUES (?)' );
foreach ( $mau as $m ) $st->execute( array( $m[0] ) );

$cau = $db->query( 'SELECT ua, (' . str_replace( 'user_agent', 'ua', $dk ) . ') AS khai FROM v' );
$ket = array();
foreach ( $cau as $r ) $ket[ $r['ua'] ] = (int) $r['khai'];
foreach ( $mau as $m ) {
    assert_equals( $m[1], $ket[ $m[0] ], $m[2] );
}

/* Tỷ lệ gộp theo tài khoản: bot 99%+, người thật vài phần trăm */
$db->exec( 'CREATE TABLE u (uid INT, ua TEXT)' );
$st = $db->prepare( 'INSERT INTO u (uid, ua) VALUES (?,?)' );
$bot  = $mau[1][0]; $that = $mau[0][0]; $vivo = $mau[3][0];
for ( $i = 0; $i < 990; $i++ ) $st->execute( array( 2, $bot ) );
for ( $i = 0; $i < 10;  $i++ ) $st->execute( array( 2, $that ) );
for ( $i = 0; $i < 900; $i++ ) $st->execute( array( 1, $that ) );
for ( $i = 0; $i < 100; $i++ ) $st->execute( array( 1, $vivo ) );   // người thật xài máy Vivo
for ( $i = 0; $i < 50;  $i++ ) $st->execute( array( 3, $bot ) );    // ít lượt
$sql = 'SELECT uid, COUNT(*) luot, SUM(' . str_replace( 'user_agent', 'ua', $dk ) . ') khai
          FROM u GROUP BY uid HAVING luot >= 1000';
$ty = array();
foreach ( $db->query( $sql ) as $r ) $ty[ (int) $r['uid'] ] = round( 100 * $r['khai'] / $r['luot'], 1 );
assert_equals( 99.0, $ty[2], 'Tai khoan bot -> 99%' );
assert_equals( 0.0,  $ty[1], 'Nguoi that xai may Vivo -> 0% (ban dau tinh ca VivoBrowser thi ho bi doi len oan)' );
assert_true( ! isset( $ty[3] ), 'Tai khoan duoi san luot KHONG duoc tinh' );

/* ---- 3. Hàm xét cờ: chạy thật ---- */
assert_true( preg_match( '#(function sitetop_ua_bot_co_co\( \$user_id \) \{.*?\n\})#s', $__ua_bh, $__ua_m2 ) === 1,
    'Lay duoc ham xet co' );

/* CHẠY TRONG TIẾN TRÌNH RIÊNG: các bộ test khác đã khai sẵn sitetop_get_option và
   get_user_meta theo kho dữ liệu của riêng chúng; dùng chung thì hàm này đọc nhầm kho,
   mà khai đè thì hỏng bộ kia. */
$__ua_ban = <<<'BANTHU'
<?php
$cfg = array(); $meta = array();
function sitetop_get_option($k, $d = null) { return $GLOBALS['cfg'][$k] ?? $d; }
function get_user_meta($uid, $key, $single = false) { return $GLOBALS['meta'][$uid][$key] ?? ''; }
function sitetop_ua_bot_muc() { return (int) sitetop_get_option('ua_bot_muc', 1); }
__HAM__
function dat($uid, $ty_le, $luot) {
    $GLOBALS['meta'][$uid]['sitetop_ua_khai_may'] = array(
        'ty_le' => $ty_le, 'luot' => $luot, 'ngay' => 14, 'luc' => '2026-10-02 12:00:00');
}
dat(10, 99.3, 42000);   // bot
dat(11, 10.9, 8477);    // người thật
dat(12, 94.9, 17499);   // ngay trên ngưỡng
dat(13, 99.0, 300);     // tỷ lệ cao nhưng quá ít lượt
$kq = array();
foreach (array(10, 11, 12, 13, 99) as $u) $kq['u' . $u] = (bool) sitetop_ua_bot_co_co($u);
$cfg['ua_bot_muc'] = 0;      $kq['muc0']     = (bool) sitetop_ua_bot_co_co(10);
$cfg = array();
$cfg['ua_bot_nguong'] = 99.5; $kq['nguong']   = (bool) sitetop_ua_bot_co_co(10);
$cfg = array();
$cfg['ua_bot_luot'] = 50000;  $kq['san_luot'] = (bool) sitetop_ua_bot_co_co(10);
echo json_encode($kq);
BANTHU;
$__ua_ban = str_replace( '__HAM__', $__ua_m2[1], $__ua_ban );
$__ua_f = sys_get_temp_dir() . '/st-ua-' . getmypid() . '.php';
file_put_contents( $__ua_f, $__ua_ban );
$__ua_r = json_decode( (string) shell_exec( 'php ' . escapeshellarg( $__ua_f ) . ' 2>&1' ), true );
@unlink( $__ua_f );
assert_true( is_array( $__ua_r ), 'Chay duoc ban thu trong tien trinh rieng' );

assert_true(  $__ua_r['u10'], 'Bot 99,3% / 42.000 luot -> gan co' );
assert_false( $__ua_r['u11'], 'Nguoi that 10,9% -> KHONG gan co' );
assert_true(  $__ua_r['u12'], '94,9% -> gan co (sat nguong duoi cua nhom bot do duoc)' );
assert_false( $__ua_r['u13'], 'SONG CON: ty le cao nhung it luot -> KHONG gan co' );
assert_false( $__ua_r['u99'], 'Tai khoan chua tinh (khong co so) -> KHONG gan co' );
assert_false( $__ua_r['muc0'], 'Dat muc 0 thi tat han' );
assert_false( $__ua_r['nguong'], 'Nang nguong len 99,5 thi 99,3 khong con dinh' );
assert_false( $__ua_r['san_luot'], 'Nang san luot len 50.000 thi 42.000 khong con dinh' );

/* ---- 4. Mặc định phải là MỨC 1 — chỉ gắn nhãn, không đụng tiền ai ---- */
assert_true( strpos( $__ua_bh, "sitetop_get_option( 'ua_bot_muc', 1 )" ) !== false,
    'Mac dinh phai la muc 1 (chi gan nhan) — bai hoc 28/09: lop nhan biet moi phai do truoc khi dung vao tien' );
assert_true( strpos( $__ua_ver, "(int) sitetop_get_option( 'ua_bot_muc', 1 ) >= 2" ) !== false,
    'Chi muc 2 moi cat thuong' );
assert_true( strpos( $__ua_ver, "\$skip_reasons[]    = 'ua_gia_lap';" ) !== false, 'Phai ghi ly do de soi' );

/* ---- 5. Trang admin ---- */
assert_true( strpos( $__ua_tu, "get_user_meta(\$row->ID, 'sitetop_ua_khai_may', true)" ) !== false,
    'Tab Nguoi dung phai doc so da tinh san' );
assert_true( strpos( $__ua_tu, 'Giả lập</th>' ) !== false, 'Thieu tieu de cot' );
/* Số cột đổi theo thời gian (03/10 thêm ô tích để xoá hàng loạt). Đếm thẳng số <th> rồi so
   với colspan, thay vì ghim một con số rồi mỗi lần thêm cột lại phải sửa test. */
preg_match( '#<thead>(.*?)</thead>#s', $__ua_tu, $__ua_head );   // chỉ bảng chính, file còn vài bảng phụ
preg_match_all( '#<th[ >]#', (string) ( $__ua_head[1] ?? '' ), $__ua_th );
preg_match( '#colspan="(\d+)"#', $__ua_tu, $__ua_cs );
assert_equals( count( $__ua_th[0] ), (int) ( $__ua_cs[1] ?? 0 ),
    'Dong "khong co du lieu" phai trai dung bang so cot cua bang' );
/* Trang KHÔNG được tự đếm — câu quét 14 ngày quá nặng cho mỗi lần dựng trang. */
assert_true( strpos( $__ua_tu, 'sitetop_do_ua_khai_may' ) === false,
    'SONG CON: tab Nguoi dung KHONG duoc goi ham quet khi dung trang' );
assert_true( strpos( $__ua_tv, "'ua_gia_lap'               =>" ) !== false, 'Thieu nhan o cot Ly do' );
assert_true( strpos( $__ua_tv, "\$reason_filter === 'ua_gia_lap'" ) !== false, 'Thieu bo loc' );

/* ---- 6. Cron: 6 giờ một lần, không phải giờ nào cũng quét ---- */
assert_true( strpos( $__ua_bh, "( time() - \$truoc ) < 6 * HOUR_IN_SECONDS ) return;" ) !== false,
    'Phai gian ra 6 gio mot lan — cau quet 14 ngay kha nang' );
assert_true( strpos( $__ua_bh, "add_action( 'sitetop_hourly_cron'" ) !== false, 'Phai moc vao cron gio' );
