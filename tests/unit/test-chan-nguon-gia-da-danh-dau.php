<?php
/* PHIÊN ĐÃ BỊ GẮN DẤU NGUỒN GIẢ THÌ KHÔNG CẤP MÃ — chủ site chốt 04/10/2026.

   Lỗ hổng: mỗi cổng chỉ soi header của chính cú gọi đó. Gọi giả một nhát ở cổng xác minh,
   bị gắn dấu, rồi quay lại làm tử tế — cổng xin mã thấy sạch nên vẫn phát mã.
   Vết lượt 780752 (28/09) ghi đúng cảnh đó.

   Test CHẠY THẬT hàm đọc dấu với từng mức cài đặt, và kiểm thứ tự chốt trong cổng xin mã. */

$__nd_goc = dirname( __DIR__, 2 );
$__nd_aj  = (string) file_get_contents( $__nd_goc . '/includes/shortlink-ajax.php' );

assert_true( preg_match( '#(function sitetop_nguon_gia_da_danh_dau\( \$sid \) \{.*?\n    \})#s', $__nd_aj, $__nd_m ) === 1,
    'Lay duoc ham doc dau cu' );

/* Tiến trình riêng: các bộ test khác đã khai sẵn get_transient / sitetop_get_option theo kho
   của riêng chúng. */
$__nd_ban = <<<'BANTHU'
<?php
$CFG = array(); $KHO = array();
function sitetop_get_option($k, $d = null) { return $GLOBALS['CFG'][$k] ?? $d; }
function get_transient($k) { return $GLOBALS['KHO'][$k] ?? false; }
__HAM__
$KHO['sitetop_nguongia_s_chac'] = 'chac';
/* Khoá cụt — nếu ai đó từng gọi set_transient với session rỗng thì khoá này tồn tại. Hàm
   phải chặn ở $sid rỗng TRƯỚC khi đụng tới transient, nếu không mọi phiên không có session
   đều bị chặn oan. */
$KHO['sitetop_nguongia_'] = 'chac';
$KHO['sitetop_nguongia_s_ngo']  = 'ngo';
$kq = array();
$kq['chac']        = sitetop_nguon_gia_da_danh_dau('s_chac');
$kq['ngo']         = sitetop_nguon_gia_da_danh_dau('s_ngo');
$kq['sach']        = sitetop_nguon_gia_da_danh_dau('s_khong_co');
$kq['sid_rong']    = sitetop_nguon_gia_da_danh_dau('');
$kq['sid_null']    = sitetop_nguon_gia_da_danh_dau(null);
$CFG['nguon_gia_muc'] = 1; $kq['muc_1'] = sitetop_nguon_gia_da_danh_dau('s_chac');
$CFG['nguon_gia_muc'] = 0; $kq['muc_0'] = sitetop_nguon_gia_da_danh_dau('s_chac');
$CFG['nguon_gia_muc'] = 2; $kq['muc_2'] = sitetop_nguon_gia_da_danh_dau('s_chac');
echo json_encode($kq);
BANTHU;
$__nd_ban = str_replace( '__HAM__', $__nd_m[1], $__nd_ban );
$__nd_f = sys_get_temp_dir() . '/st-ngiacu-' . getmypid() . '.php';
file_put_contents( $__nd_f, $__nd_ban );
$__nd_r = json_decode( (string) shell_exec( 'php ' . escapeshellarg( $__nd_f ) . ' 2>&1' ), true );
@unlink( $__nd_f );
assert_true( is_array( $__nd_r ), 'Chay duoc ham trong tien trinh rieng' );

assert_true(  $__nd_r['chac'],     'Phien da bi gan dau "chac" -> chan' );
assert_false( $__nd_r['ngo'],      'SONG CON: dau yeu "ngo" KHONG chan — giu nguyen quyet dinh 20/09, khong chan nguoi that vi dau yeu' );
assert_false( $__nd_r['sach'],     'Phien sach -> khong chan' );
assert_false( $__nd_r['sid_rong'], 'Session rong -> khong chan' );
assert_false( $__nd_r['sid_null'], 'Session null -> khong chan, khong vo' );
assert_false( $__nd_r['muc_1'],    'SONG CON: muc 1 (chi gan nhan) -> KHONG duoc chan' );
assert_false( $__nd_r['muc_0'],    'Muc 0 (tat) -> khong chan' );
assert_true(  $__nd_r['muc_2'],    'Muc 2 (mac dinh) -> chan' );

/* ---- Vị trí chốt trong cổng xin mã ---- */
assert_true( preg_match( '#function sitetop_ajax_get_code\(\) \{(.*?)\n\}#s', $__nd_aj, $__nd_c ) === 1,
    'Lay duoc than cong xin ma' );
$than = $__nd_c[1];

assert_true( strpos( $than, 'sitetop_nguon_gia_da_danh_dau( $sid )' ) !== false,
    'Cong xin ma phai doc dau cu' );

$vt_dau   = strpos( $than, 'sitetop_nguon_gia_da_danh_dau( $sid )' );
$vt_ma    = strpos( $than, 'mamoi' );
assert_true( $vt_ma === false || $vt_dau < $vt_ma,
    'SONG CON: chot phai dung TRUOC cho phat ma, khong phai chan sau khi da dua ma' );

$vt_rate  = strpos( $than, "sitetop_rate_limit_check('get_code')" );
assert_true( $vt_rate !== false && $vt_dau < $vt_rate,
    'Chan truoc khi dem han muc — lan goi hong khong an vao han muc cua nguoi that' );

/* Câu báo lỗi phải TRÙNG với lớp chặn ngay trên, để kẻ gian không biết luật nào đã bắt. */
assert_true( preg_match( '#sitetop_nguon_gia_da_danh_dau\( \$sid \) \) \{(.*?)\n    \}#s', $than, $__nd_k ) === 1,
    'Lay duoc khoi chan moi' );
assert_true( strpos( $__nd_k[1], "'message' => 'Hãy mở trang đích để lấy mã.'" ) !== false,
    'SONG CON: phai dung CUNG cau bao loi voi lop tren — khac cau la lo ra luat nao da bat' );

/* Phải để lại vết cho admin soi */
assert_true( strpos( $than, "sitetop_ghi_vet( \$sid, 'chan_nguongia_cu', 'xinma' )" ) !== false,
    'Phai ghi vet de con soi o tab Visit' );
