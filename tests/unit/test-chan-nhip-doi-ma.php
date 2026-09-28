<?php
/* CHẶN TUA ĐỒNG HỒ THEO NHỊP ĐÒI MÃ — chạy thật đoạn mã, 28/09/2026.

   Script tua đời mới ghi đè cả performance.now() nên chốt bên widget hết tin được. Lớp này
   đo bằng đồng hồ của MÁY CHỦ: đòi mã sớm bao nhiêu lần trong 60 giây thật.

   Test cắt đúng hai đoạn mã thật (bộ đếm nhịp + chốt chặn cấp mã) rồi CHẠY chúng trong một
   TIẾN TRÌNH PHP RIÊNG. Phải tách tiến trình vì bàn thử cần khai get_transient/set_transient/
   sitetop_get_option giả — khai trong tiến trình chung là rò sang các bộ test khác và làm
   hỏng chúng (đã dính: 23 phép của bộ khác đỏ oan). */

$__nd_goc = dirname( __DIR__, 2 );
$__nd_ma  = (string) file_get_contents( $__nd_goc . '/includes/shortlink-functions.php' );

assert_true( preg_match( '#(\$nhanh_key = \'sitetop_nhipnhanh_\'.*?\n            \})#s', $__nd_ma, $__nd_m1 ) === 1,
    'Lay duoc doan dem nhip doi ma' );
assert_true( preg_match( '#if \( \(int\) sitetop_get_option\( \'tua_gio_muc\', 3 \) >= 3\s*\n\s*&& get_transient\( \'sitetop_tuagio_\' \. \$session_id \) \) \{#', $__nd_ma ) === 1,
    'Lay duoc dieu kien chot chan cap ma' );

$__nd_ban = <<<'BANTHU'
<?php
$GLOBALS['__kho'] = array(); $GLOBALS['__caidat'] = array(); $GLOBALS['__vet'] = array();
function get_transient($k){ return $GLOBALS['__kho'][$k] ?? false; }
function set_transient($k,$v,$t=0){ $GLOBALS['__kho'][$k]=$v; return true; }
function sitetop_get_option($k,$d=null){ return $GLOBALS['__caidat'][$k] ?? $d; }
function sitetop_ghi_vet($s,$l,$n=''){ $GLOBALS['__vet'][] = $l.':'.$n; }
define('MINUTE_IN_SECONDS',60); define('HOUR_IN_SECONDS',3600);

function doi_ma_som($session_id){ __DEM__ }
/* Chốt thật ở đầu sitetop_get_widget_code: có cờ + mức >= 3 thì chặn. */
function bi_chan($session_id){
    return ( (int) sitetop_get_option('tua_gio_muc', 3) >= 3
             && get_transient('sitetop_tuagio_' . $session_id) ) ? true : false;
}
$kq = array();

// Người thật: đo 3 ngày, 319 phiên đòi sớm, không phiên nào quá 1 nhịp.
$GLOBALS['__kho']=array();
doi_ma_som('that'); $kq['that_1_lan'] = bi_chan('that');
doi_ma_som('that'); doi_ma_som('that'); doi_ma_som('that');
$kq['that_4_lan'] = bi_chan('that');

// Kẻ tua: đốt remaining trong tích tắc rồi hỏi lại liên tục.
$GLOBALS['__kho']=array(); $GLOBALS['__vet']=array();
for($i=0;$i<5;$i++) doi_ma_som('tua');
$kq['tua_5_lan'] = bi_chan('tua');
$kq['vet'] = implode('|', $GLOBALS['__vet']);
for($i=0;$i<20;$i++) doi_ma_som('tua');
$kq['tua_25_lan'] = bi_chan('tua');

// Cửa sổ 60 giây hết hạn -> đếm lại từ đầu, không cộng dồn cả phiên.
$GLOBALS['__kho']=array();
for($i=0;$i<4;$i++) doi_ma_som('het_han');
unset($GLOBALS['__kho']['sitetop_nhipnhanh_het_han']);
doi_ma_som('het_han'); $kq['sau_khi_het_cua_so'] = bi_chan('het_han');

// Đặt ngưỡng 0 = tắt hẳn.
$GLOBALS['__kho']=array(); $GLOBALS['__caidat']=array('tua_nhip_nhanh'=>0);
for($i=0;$i<30;$i++) doi_ma_som('tat');
$kq['dat_nguong_0'] = bi_chan('tat');

// Mức 2 = vẫn cấp mã, chỉ cắt thưởng.
$GLOBALS['__kho']=array(); $GLOBALS['__caidat']=array('tua_gio_muc'=>2);
for($i=0;$i<10;$i++) doi_ma_som('muc2');
$kq['muc_2'] = bi_chan('muc2');

echo json_encode($kq);
BANTHU;

$__nd_ban = str_replace( '__DEM__', $__nd_m1[1], $__nd_ban );
$__nd_f = sys_get_temp_dir() . '/st-nhip-' . getmypid() . '.php';
file_put_contents( $__nd_f, $__nd_ban );
$__nd_out = (string) shell_exec( 'php ' . escapeshellarg( $__nd_f ) . ' 2>&1' );
@unlink( $__nd_f );
$__nd_kq = json_decode( $__nd_out, true );
assert_true( is_array( $__nd_kq ), 'Chay duoc ban thu trong tien trinh rieng. Ket qua: ' . substr( $__nd_out, 0, 200 ) );

assert_false( $__nd_kq['that_1_lan'] ?? true,  'Mot lan doi som KHONG duoc chan' );
assert_false( $__nd_kq['that_4_lan'] ?? true,  'Bon lan van chua cham nguong' );
assert_true(  $__nd_kq['tua_5_lan'] ?? false,  'Den lan thu 5 trong 60 giay -> PHAI chan cap ma' );
assert_true(  strpos( $__nd_kq['vet'] ?? '', 'nhip_doi_ma=5/60s' ) !== false, 'Phai ghi vet ro nhip bat duoc' );
assert_true(  $__nd_kq['tua_25_lan'] ?? false, 'Hoi them nua van bi chan' );
assert_false( $__nd_kq['sau_khi_het_cua_so'] ?? true, 'Qua 60 giay thi dem lai tu dau — khong cong don ca phien' );
assert_false( $__nd_kq['dat_nguong_0'] ?? true, 'Dat nguong 0 thi tat han lop nay' );
assert_false( $__nd_kq['muc_2'] ?? true, 'Muc 2 thi van cap ma, chi cat thuong' );
