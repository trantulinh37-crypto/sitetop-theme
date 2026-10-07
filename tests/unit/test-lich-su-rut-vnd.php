<?php
/* THẺ LỊCH SỬ RÚT TIỀN KÈM SỐ VNĐ KHI RÚT VỀ NGÂN HÀNG — chủ site 07/10/2026, chốt sau 3 vòng:
   "chỉ cần hiện số vnđ user được nhận ở phương thức bank" → "40$ => Vnđ luôn" → "rút bank tôi vẫn muốn hiện $ và vnđ luôn".
   Kết luận: Bank in "$40 ≈ 880.000đ"; rút USDT chỉ in USD; chế độ VNĐ (chưa bật USD) giữ nguyên như cũ. Chạy THẬT hàm dựng thẻ trong tiến trình riêng,
   ghép hàm tỷ giá thật (sitetop_usd_rate/sitetop_usd_sang_vnd) và sitetop_format_money thật. */

$__ls_goc = dirname( __DIR__, 2 );
$__ls_lm  = (string) file_get_contents( $__ls_goc . '/includes/admin-load-more.php' );
$__ls_tu  = (string) file_get_contents( $__ls_goc . '/includes/tien-usd.php' );
$__ls_fn  = (string) file_get_contents( $__ls_goc . '/functions.php' );
$__ls_ud  = (string) file_get_contents( $__ls_goc . '/page-user-dashboard.php' );

assert_true( preg_match( '#(function sitetop_render_withdrawal_item\( \$w \) \{.*?\n\})#s', $__ls_lm, $__ls_m1 ) === 1, 'Lay duoc ham dung the' );
assert_true( preg_match( '#(function sitetop_usd_rate\(\) \{.*?\n\})#s', $__ls_tu, $__ls_m2 ) === 1, 'Lay duoc sitetop_usd_rate' );
assert_true( preg_match( '#(function sitetop_usd_sang_vnd\( \$usd \) \{.*?\n\})#s', $__ls_tu, $__ls_m3 ) === 1, 'Lay duoc sitetop_usd_sang_vnd' );
assert_true( preg_match( '#(function sitetop_format_money\( \$amount \) \{.*?\n\})#s', $__ls_fn, $__ls_m4 ) === 1, 'Lay duoc sitetop_format_money' );

/* Hai nơi hiển thị (trang + "Xem thêm") phải cùng đi qua một hàm — không thì sửa một nơi lệch nơi kia */
assert_true( strpos( $__ls_ud, 'sitetop_render_withdrawal_item( $w )' ) !== false, 'Trang dashboard dung ham chung' );
assert_true( substr_count( $__ls_lm, 'sitetop_render_withdrawal_item(' ) >= 2, 'AJAX Xem them cung dung ham chung' );

$__ls_ban = <<<'BANTHU'
<?php
$CHE_DO_USD = true; $RATE = 22000;
function sitetop_che_do_usd(){ return $GLOBALS['CHE_DO_USD']; }
function sitetop_get_option($k, $d = null){ return $k === 'usd_rate' ? $GLOBALS['RATE'] : $d; }
function sitetop_format_tien_user($a){ return 'TIEN_USER(' . $a . ')'; }
function esc_attr($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function esc_html($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
__HAM__
function the($method, $amount){
    $w = (object) array('id'=>1,'amount'=>$amount,'payment_method'=>$method,'bank_name'=>'abc','bank_account'=>'12345111','bank_holder'=>'nguyen van a','wallet_address'=>'0xabc','status'=>'pending','source'=>'task','created_at'=>'2026-10-07 14:01:00','admin_note'=>'');
    $h = sitetop_render_withdrawal_item($w);
    preg_match('#<span class="wdi-amount">(.*?)</span>#', $h, $m);
    return array('so' => $m[1] ?? null, 'co_tag_bank' => strpos($h, '>Ngân hàng<') !== false, 'co_tag_usdt' => strpos($h, '>USDT-BEP20<') !== false);
}
$kq = array();
$kq['bank40']   = the('bank', '40.00');
$kq['bank3012'] = the('bank', '30.12');
$kq['usdt40']   = the('usdt', '40.00');
$RATE = 22350; $kq['bank_rate_khac'] = the('bank', '30.12');
$RATE = 22000; $CHE_DO_USD = false; $kq['vnd_mode_bank'] = the('bank', '880000'); $kq['vnd_mode_usdt'] = the('usdt', '880000');
echo json_encode($kq, JSON_UNESCAPED_UNICODE);
BANTHU;
$__ls_ban = str_replace( '__HAM__', $__ls_m2[1] . "\n" . $__ls_m3[1] . "\n" . $__ls_m4[1] . "\n" . $__ls_m1[1], $__ls_ban );
$__ls_f = sys_get_temp_dir() . '/st-lich-su-rut-' . getmypid() . '.php';
file_put_contents( $__ls_f, $__ls_ban );
$__ls_r = json_decode( (string) shell_exec( 'php ' . escapeshellarg( $__ls_f ) . ' 2>&1' ), true );
@unlink( $__ls_f );
assert_true( is_array( $__ls_r ), 'Chay duoc ham dung the trong tien trinh rieng' );

assert_equals( 'TIEN_USER(40.00) <small>≈ 880.000đ</small>', $__ls_r['bank40']['so'] ?? null, 'Bank + che do USD: in USD roi kem VND quy doi trong <small> (chu site chot: "hien $ va VND luon"). Ra: ' . json_encode( $__ls_r['bank40'] ?? null, JSON_UNESCAPED_UNICODE ) );
assert_true( ! empty( $__ls_r['bank40']['co_tag_bank'] ), 'Van co nhan "Ngan hang"' );
assert_true( strpos( (string) ( $__ls_r['bank3012']['so'] ?? '' ), '≈ 662.640đ' ) !== false, '$30,12 → kem 662.640đ, lam tron toi dong. Ra: ' . ( $__ls_r['bank3012']['so'] ?? '' ) );
assert_equals( 'TIEN_USER(40.00)', $__ls_r['usdt40']['so'] ?? null, 'USDT + che do USD: chi in USD, KHONG kem VND (nhan USDT thi khong quy doi)' );
assert_true( strpos( (string) ( $__ls_r['bank_rate_khac']['so'] ?? '' ), '≈ 673.182đ' ) !== false, 'Ty gia admin doi (22.350) → nhan theo ty gia moi, khong viet cung 22.000. Ra: ' . ( $__ls_r['bank_rate_khac']['so'] ?? '' ) );
assert_equals( 'TIEN_USER(880000)', $__ls_r['vnd_mode_bank']['so'] ?? null, 'Che do VND (chua bat USD): Bank in nhu cu, khong nhan ty gia' );
assert_equals( 'TIEN_USER(880000)', $__ls_r['vnd_mode_usdt']['so'] ?? null, 'Che do VND: USDT in nhu cu' );
