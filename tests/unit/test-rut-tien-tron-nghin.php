<?php
/* RÚT TIỀN: CHỈ SỐ TRÒN 1.000đ + LƯU VÍ USDT BEP20 — chủ site chốt 28/09/2026.

   Chốt số tròn nghìn phải nằm Ở MÁY CHỦ. Thuộc tính step của ô nhập chỉ là gợi ý cho trình
   duyệt: user sửa bằng công cụ hoặc gọi thẳng admin-ajax là qua mặt được.

   Test cắt đúng đoạn chốt trong sitetop_submit_withdrawal() rồi CHẠY nó với từng số tiền —
   không dò chuỗi. */

$__rt_goc = dirname( __DIR__, 2 );
$__rt_wd  = (string) file_get_contents( $__rt_goc . '/includes/withdrawal.php' );
$__rt_ui  = (string) file_get_contents( $__rt_goc . '/page-user-dashboard.php' );

/* ---- 1. Chạy thật đoạn chốt tròn nghìn ---- */
assert_true( preg_match( '#(\$buoc_nghin = 1000;.*?\n    \})#s', $__rt_wd, $__rt_m ) === 1,
    'Lay duoc doan chot tron nghin tu withdrawal.php' );

/* CHẠY TRONG TIẾN TRÌNH RIÊNG: bộ test khác đã khai sẵn một lớp WP_Error rút gọn (chỉ giữ
   mã lỗi, bỏ lời báo), dùng chung là không đọc được nội dung thông báo — và khai đè thì lại
   hỏng bộ kia. Tách hẳn ra cho sạch. */
$__rt_ban = <<<'BANTHU'
<?php
class WP_Error { public $ma; public $tin;
    function __construct($m='', $t='', $d=null){ $this->ma=$m; $this->tin=$t; } }
function sitetop_format_money($v){ return number_format((float)$v, 0, ',', '.') . 'đ'; }
/* Chế độ tiền user (06/10/2026): khung này kiểm hành vi VNĐ — công tắc USD tắt. */
function sitetop_che_do_usd(){ return false; }
function sitetop_format_tien_user($v){ return sitetop_format_money($v); }
function thu($amount, $min){ $usd = false; /* chế độ VNĐ — chốt tròn nghìn chỉ chạy ở VNĐ (06/10/2026) */ __CHOT__ return null; }
$kq = array();
foreach (array(133000,133500,133550,133999,50000,100000,1000000,2000000,50001,99999,123456,1000001) as $so) {
    $r = thu($so, 50000);
    $kq['so_' . $so] = ($r instanceof WP_Error) ? $r->tin : null;
}
$r2 = thu(50500, 50000); $kq['le_50500'] = $r2 instanceof WP_Error ? $r2->tin : null;
$r3 = thu(500, 50000);   $kq['le_500']   = $r3 instanceof WP_Error ? $r3->tin : null;
echo json_encode($kq, JSON_UNESCAPED_UNICODE);
BANTHU;

$__rt_ban = str_replace( '__CHOT__', $__rt_m[1], $__rt_ban );
$__rt_f = sys_get_temp_dir() . '/st-rut-' . getmypid() . '.php';
file_put_contents( $__rt_f, $__rt_ban );
$__rt_kq = json_decode( (string) shell_exec( 'php ' . escapeshellarg( $__rt_f ) . ' 2>&1' ), true );
@unlink( $__rt_f );
assert_true( is_array( $__rt_kq ), 'Chay duoc ban thu trong tien trinh rieng' );

// Đúng đề bài của chủ site
assert_true( $__rt_kq['so_133000'] === null, '133.000d -> duoc rut' );
assert_true( $__rt_kq['so_133500'] !== null, '133.500d -> KHONG duoc rut' );
assert_true( $__rt_kq['so_133550'] !== null, '133.550d -> KHONG duoc rut' );
assert_true( $__rt_kq['so_133999'] !== null, '133.999d -> KHONG duoc rut' );
foreach ( array( 50000, 100000, 1000000, 2000000 ) as $__rt_ok ) {
    assert_true( $__rt_kq[ 'so_' . $__rt_ok ] === null, $__rt_ok . 'd -> duoc rut' );
}
foreach ( array( 50001, 99999, 123456, 1000001 ) as $__rt_no ) {
    assert_true( $__rt_kq[ 'so_' . $__rt_no ] !== null, $__rt_no . 'd -> bi tu choi' );
}

/* Lời báo phải chỉ ra số nên nhập, và trấn an phần lẻ không mất. */
assert_true( strpos( (string) $__rt_kq['so_133500'], '133.000' ) !== false, 'Phai goi y so tron gan nhat (133.000d)' );
assert_true( strpos( (string) $__rt_kq['so_133500'], 'vẫn nằm trong ví' ) !== false, 'Phai noi ro phan le khong mat' );
assert_true( strpos( (string) $__rt_kq['le_50500'], '50.000' ) !== false, 'Goi y 50.000d vi van dat muc toi thieu' );
assert_true( strpos( (string) $__rt_kq['le_500'], 'hãy nhập' ) === false,
    'Khong duoc goi y so duoi muc toi thieu (500d -> 0d la vo nghia)' );

/* ---- 2. Thứ tự chốt: tối thiểu/tối đa phải xét TRƯỚC ---- */
$__rt_p_min  = strpos( $__rt_wd, "return new WP_Error('min_amount'" );
$__rt_p_max  = strpos( $__rt_wd, "return new WP_Error('max_amount'" );
$__rt_p_ngan = strpos( $__rt_wd, '$buoc_nghin = 1000;' );
assert_true( $__rt_p_min < $__rt_p_ngan && $__rt_p_max < $__rt_p_ngan,
    'Nhap 500d thi phai bao "rut toi thieu", khong bao lac sang chuyen tron nghin' );

/* ---- 3. Lưu ví USDT BEP20 ---- */
assert_true( strpos( $__rt_wd, "if ( ! empty( \$bank_info['wallet_address'] ) ) update_user_meta( \$user_id, 'sitetop_wallet_address', sanitize_text_field( \$bank_info['wallet_address'] ) );" ) !== false,
    'Rut xong phai luu vi USDT vao user meta — y het cach dang lam voi ba o ngan hang' );
assert_true( strpos( $__rt_ui, "\$saved_wallet  = get_user_meta(\$user_id, 'sitetop_wallet_address', true);" ) !== false,
    'Form phai doc lai vi da luu' );
assert_true( strpos( $__rt_ui, 'name="wallet_address" placeholder="0x..." value="<?php echo esc_attr($saved_wallet); ?>"' ) !== false,
    'O nhap vi phai dien san vi da luu' );
assert_true( strpos( $__rt_ui, 'USDT (BEP20)' ) !== false, 'Phai hien ro phuong thuc USDT (BEP20)' );

/* ---- 4. Giao diện khớp luật ---- */
/* 06/10/2026: giao diện chọn theo chế độ tiền. VNĐ giữ NGUYÊN luật tròn nghìn bên dưới;
   USD rút theo cent nên làm tròn xuống $0,01 thay cho 1.000đ. */
assert_true( strpos( $__rt_ui, 'step="1000" placeholder="0" required>' ) !== false
          && strpos( $__rt_ui, 'inputmode="decimal" autocomplete="off" id="wdAmount"' ) !== false,
    'O nhap so tien: VND nhay theo 1.000; USD la o CHU nhan dung so (khong lam tron)' );
assert_true( strpos( $__rt_ui, '$wd_cap     = $usd_mode ? sitetop_usd_cat_le( $wd_cap, 2 ) : (int) ( floor( $wd_cap / 1000 ) * 1000 );' ) !== false,
    'Tran rut: VND lam tron XUONG nghin; USD rut duoc DUNG so du, khong floor' );
assert_true( strpos( $__rt_ui, ":Math.floor(v/1000)*1000" ) !== false && strpos( $__rt_ui, "?wdHienSo(v):Math.floor(v/1000)*1000" ) !== false,
    'Nut dien nhanh: VND tron nghin, USD dien dung so' );
$__rt_usd = strpos( $__rt_ui, "if(typeof ST_USD!=='undefined'&&ST_USD){" );
$__rt_vnd = strpos( $__rt_ui, "if(_st%1000!==0){" );
assert_true( $__rt_usd !== false && $__rt_vnd !== false && $__rt_usd < $__rt_vnd
          && strpos( $__rt_ui, "}else{\nvar _st=parseInt(fd.get('amount'),10)||0;" ) !== false,
    'Bao ngay tai cho cho khoi mat mot vong goi — luat tron nghin CHI o nhanh VND' );

/* ---- 5. KHÔNG đụng luồng khác ---- */
/* Rút hoa hồng referral là sổ riêng, chủ site chỉ yêu cầu sửa phần Rút tiền. */
$__rt_ref = (string) file_get_contents( $__rt_goc . '/includes/referral-management.php' );
assert_true( strpos( $__rt_ref, '$buoc_nghin' ) === false, 'Khong tu y doi luong rut hoa hong referral' );
foreach ( array( 'min_withdrawal', 'max_withdrawal', 'min_account_age_hours', 'pending_exists', 'FOR UPDATE' ) as $__rt_giu ) {
    assert_true( strpos( $__rt_wd, $__rt_giu ) !== false, 'Chot cu phai con nguyen: ' . $__rt_giu );
}
