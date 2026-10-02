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

/* ---- 2. Câu đếm: chạy thật trên SQLite ---- */
assert_true( preg_match( '#"SELECT user_id,\s*\n\s*COUNT\(\*\) AS luot,.*?HAVING luot >= %d"#s', $__ua_bh, $__ua_m ) === 1,
    'Lay duoc cau dem tu ma nguon' );
$__ua_sql = $__ua_m[0];
assert_true( strpos( $__ua_sql, 'LOCATE(%s, user_agent) = 0' ) !== false,
    'Phai dung LOCATE de so chuoi rut gon — LIKE thi dau ngoac va % trong chuoi gay phien' );

$db = new PDO( 'sqlite::memory:' );
$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$db->exec( "CREATE TABLE v (user_id INT, user_agent TEXT)" );
$them = function ( $uid, $ua, $n ) use ( $db ) {
    $st = $db->prepare( "INSERT INTO v (user_id, user_agent) VALUES (?,?)" );
    for ( $i = 0; $i < $n; $i++ ) $st->execute( array( $uid, $ua ) );
};
$RUT = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 Chrome/131 Mobile';
// user 1 — người thật: hầu hết chuỗi rút gọn, lác đác iPhone và máy khai tên
$them( 1, $RUT, 900 );
$them( 1, 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X)', 80 );
$them( 1, 'Mozilla/5.0 (Linux; Android 13; SM-A556B) AppleWebKit/537.36', 20 );
// user 2 — bot: khai tên máy gần như toàn bộ
$them( 2, 'Mozilla/5.0 (Linux; Android 14; 2311DRK48G) AppleWebKit/537.36', 700 );
$them( 2, 'Mozilla/5.0 (Linux; Android 13; CPH2551) AppleWebKit/537.36', 700 );
$them( 2, $RUT, 10 );
// user 3 — ít lượt, dù khai 100% cũng không được tính
$them( 3, 'Mozilla/5.0 (Linux; Android 15; Pixel 7) AppleWebKit/537.36', 50 );

$sql = "SELECT user_id, COUNT(*) AS luot,
          SUM( CASE WHEN user_agent LIKE '%Android%' AND INSTR(user_agent, 'Android 10; K)') = 0
                    THEN 1 ELSE 0 END ) AS khai
        FROM v GROUP BY user_id HAVING luot >= 1000";
$kq = array();
foreach ( $db->query( $sql ) as $r ) $kq[ (int) $r['user_id'] ] = round( 100 * $r['khai'] / $r['luot'], 1 );

assert_true( isset( $kq[1] ) && isset( $kq[2] ), 'Hai tai khoan du luot phai duoc tinh' );
assert_true( ! isset( $kq[3] ), 'Tai khoan duoi nguong luot KHONG duoc tinh — mot luot le khai ten may khong co y nghia gi' );
assert_equals( 2.0,  $kq[1], 'Nguoi that: ~2% khai ten may (iPhone khong tinh vao day)' );
assert_equals( 99.3, $kq[2], 'Bot: 99,3% khai ten may' );

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
assert_true( strpos( $__ua_tu, 'colspan="13"' ) !== false && strpos( $__ua_tu, 'colspan="12"' ) === false,
    'Dong "khong co du lieu" phai doi colspan theo' );
/* Trang KHÔNG được tự đếm — câu quét 14 ngày quá nặng cho mỗi lần dựng trang. */
assert_true( strpos( $__ua_tu, 'sitetop_do_ua_khai_may' ) === false,
    'SONG CON: tab Nguoi dung KHONG duoc goi ham quet khi dung trang' );
assert_true( strpos( $__ua_tv, "'ua_gia_lap'               =>" ) !== false, 'Thieu nhan o cot Ly do' );
assert_true( strpos( $__ua_tv, "\$reason_filter === 'ua_gia_lap'" ) !== false, 'Thieu bo loc' );

/* ---- 6. Cron: 6 giờ một lần, không phải giờ nào cũng quét ---- */
assert_true( strpos( $__ua_bh, "( time() - \$truoc ) < 6 * HOUR_IN_SECONDS ) return;" ) !== false,
    'Phai gian ra 6 gio mot lan — cau quet 14 ngay kha nang' );
assert_true( strpos( $__ua_bh, "add_action( 'sitetop_hourly_cron'" ) !== false, 'Phai moc vao cron gio' );
