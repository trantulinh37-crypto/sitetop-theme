<?php
/* SỐ THIẾT BỊ DƯỚI 10 THÌ BÁO ĐỎ — chủ site chốt 03/10/2026.

   "Thiết bị" trong bảng chi tiết lệnh rút là số KIỂU máy + trình duyệt khác nhau, không
   phải số máy vật lý. Đo .net 02/10 (14 ngày, tài khoản từ 1.000 lượt): ba tài khoản dưới
   10 kiểu máy là yumimod 6, yuri08 6, Mimoncute 8 — cả ba khai tên máy 99,8–100%, tức bot.
   Tài khoản thật thấp nhất là 15 kiểu máy.

   Test CHẠY THẬT hàm dựng thẻ bằng node, không dò chuỗi. */

$__td_goc = dirname( __DIR__, 2 );
$__td_js  = (string) file_get_contents( $__td_goc . '/includes/admin/tabs/tab-withdrawals.php' );

assert_true( preg_match( '#(function wdTheThietBi\(x\)\{.*?\n\})#s', $__td_js, $__td_m ) === 1,
    'Lay duoc ham dung the tu tab-withdrawals.php' );

$__td_ban = <<<'BANTHU'
function wdNum(v){ return String(Number(v)||0); }
__HAM__
var ra = [];
[ {so_thiet_bi:6,  tong_luot:7616},   // yumimod — bot
  {so_thiet_bi:8,  tong_luot:5251},   // Mimoncute — bot
  {so_thiet_bi:9,  tong_luot:900},
  {so_thiet_bi:10, tong_luot:20853},  // tanh2010 — bot nhung dung 10, KHONG do
  {so_thiet_bi:15, tong_luot:1382},   // trongvipporo222 — nguoi that thap nhat
  {so_thiet_bi:139,tong_luot:18032},  // phongdepchai27 — nguoi that
  {so_thiet_bi:4,  tong_luot:120},    // ky it luot
  {so_thiet_bi:0,  tong_luot:0}       // ky rong
].forEach(function(x){
    var h = wdTheThietBi(x);
    ra.push({ sl:x.so_thiet_bi, luot:x.tong_luot,
              do: h.indexOf('wd-fraud-card do') !== -1,
              canh: h.indexOf('DƯỚI 10') !== -1,
              nhac_it_luot: h.indexOf('kỳ ít lượt') !== -1 || h.indexOf('chỉ có') !== -1,
              so_hien: h.indexOf('>' + x.so_thiet_bi) !== -1 });
});
console.log(JSON.stringify(ra));
BANTHU;

$__td_ban = str_replace( '__HAM__', $__td_m[1], $__td_ban );
$__td_f = sys_get_temp_dir() . '/st-thietbi-' . getmypid() . '.js';
file_put_contents( $__td_f, $__td_ban );
$__td_kq = json_decode( (string) shell_exec( 'node ' . escapeshellarg( $__td_f ) . ' 2>&1' ), true );
@unlink( $__td_f );
assert_true( is_array( $__td_kq ) && count( $__td_kq ) === 8, 'Chay duoc ham bang node' );

$lay = function ( $sl ) use ( $__td_kq ) {
    foreach ( $__td_kq as $r ) if ( (int) $r['sl'] === $sl ) return $r;
    return null;
};

// Đúng đề bài: dưới 10 là đỏ
assert_true( $lay( 6 )['do'] && $lay( 6 )['canh'], '6 thiet bi -> BAO DO' );
assert_true( $lay( 8 )['do'], '8 thiet bi -> BAO DO' );
assert_true( $lay( 9 )['do'], '9 thiet bi -> BAO DO (sat moc)' );

// Từ 10 trở lên thì thôi — không được đỏ lan sang tài khoản thật
assert_false( $lay( 10 )['do'],  'SONG CON: dung 10 -> KHONG do ("duoi 10" nghia la < 10)' );
assert_false( $lay( 15 )['do'],  '15 thiet bi (nguoi that thap nhat do duoc) -> KHONG do' );
assert_false( $lay( 139 )['do'], '139 thiet bi -> KHONG do' );
assert_false( $lay( 10 )['canh'], 'Tu 10 tro len thi khong co chu canh bao' );

// Kỳ rỗng không phải gian lận, chỉ là không có dữ liệu
assert_false( $lay( 0 )['do'], 'SONG CON: ky rong (0 thiet bi) -> KHONG do, khong co gi de soi' );

// Kỳ ít lượt vẫn đỏ theo đúng ý chủ site, nhưng phải kèm lời nhắc
assert_true( $lay( 4 )['do'], 'Ky it luot van bao do dung y chu site' );
assert_true( $lay( 4 )['nhac_it_luot'], 'Ky duoi 500 luot phai kem dong nhac de dung doc nham' );
assert_false( $lay( 6 )['nhac_it_luot'], 'Ky 7.616 luot thi KHONG duoc hien dong nhac it luot' );

// Con số vẫn phải hiện ra, đừng vì tô đỏ mà nuốt mất
foreach ( array( 6, 8, 9, 10, 15, 139 ) as $sl ) {
    assert_true( $lay( $sl )['so_hien'], "Van hien dung con so $sl" );
}
