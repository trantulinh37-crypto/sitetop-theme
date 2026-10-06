<?php
/* TIỀN USER BẰNG USD — 06/10/2026.

   Chủ site yêu cầu: chuyển toàn bộ cơ chế thưởng user sang USD (rate USD / 1.000 view,
   số dư, lịch sử, lệnh rút), khách hàng giữ nguyên VNĐ. Ví dụ trong yêu cầu:
   rate $35 / 1.000 view → 1 view $0,035 · 10 view $0,35 · 100 view $3,50 · 1.000 view $35.
   Admin xem lệnh rút: USD kèm VNĐ quy đổi — $35 → 770.000đ ở tỷ giá 22.000.

   Những bẫy test này canh (đều có thật trong mã trước khi sửa):
   - sitetop_add_user_balance() làm absint($amount) + SQL %d → $0,035 thành 0;
   - hoa hồng (int) round() → $0,0035 thành 0;
   - lệnh rút absint + trừ số dư %d → rút $9,49 chỉ trừ 9;
   - sitetop_get_reward_amount() rơi về mặc định 800 → $800 MỖI VIEW.

   Test CHẠY THẬT: nạp file includes/tien-usd.php thật, trích hàm thật từ các file khác
   bằng tokenizer, chạy trong tiến trình PHP con với khung WordPress giả. */

$__us_goc = dirname( __DIR__, 2 );

$__us_ham = function ( $ma, $ten ) {
    $vt = strpos( $ma, 'function ' . $ten . '(' );
    if ( $vt === false ) return '';
    $tk = token_get_all( '<?php ' . substr( $ma, $vt ) );
    $out = ''; $d = 0; $open = false; $dau = true;
    foreach ( $tk as $t ) {
        if ( $dau ) { $dau = false; continue; }
        $out .= is_array( $t ) ? $t[1] : $t;
        $mo = ( $t === '{' ) || ( is_array( $t ) && in_array( $t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) );
        if ( $mo ) { $d++; $open = true; }
        elseif ( $t === '}' ) { $d--; if ( $open && $d === 0 ) break; }
    }
    return $out;
};

$__us_fn  = (string) file_get_contents( $__us_goc . '/functions.php' );
$__us_ver = (string) file_get_contents( $__us_goc . '/includes/shortlink-verification.php' );
$__us_ref = (string) file_get_contents( $__us_goc . '/includes/referral-management.php' );
$__us_wd  = (string) file_get_contents( $__us_goc . '/includes/withdrawal.php' );

$__us_trich = array(
    'sitetop_format_money'           => $__us_fn,
    'sitetop_get_reward_amount'      => $__us_fn,
    'sitetop_add_user_balance'       => $__us_ver,
    'sitetop_rate_rieng_khoa'        => $__us_ver,
    'sitetop_rate_rieng_cua_user'    => $__us_ver,
    'sitetop_pay_referral_commission'=> $__us_ref,
    'sitetop_submit_withdrawal'      => $__us_wd,
);
$__us_ma = '';
foreach ( $__us_trich as $__us_t => $__us_nguon ) {
    $__us_m = $__us_ham( $__us_nguon, $__us_t );
    assert_true( $__us_m !== '', 'Phai trich duoc ham that ' . $__us_t );
    $__us_ma .= $__us_m . "\n";
}

/* Chạy một kịch bản: $usd bật/tắt, $opt option, $than mã PHP in JSON ra stdout. */
$__us_chay = function ( $usd, $opt, $than ) use ( $__us_goc, $__us_ma ) {
    $nen = <<<'PHP'
define( 'ABSPATH', '/tmp/' );
define( 'SITETOP_PREFIX', 'sitetop_' ); define( 'SITETOP_USD_KHONG_CHO', 1 );
define( 'HOUR_IN_SECONDS', 3600 ); define( 'MINUTE_IN_SECONDS', 60 );
error_reporting( E_ALL );
class WP_Error { public $ma; public $tin;
    function __construct( $m = '', $t = '', $d = null ) { $this->ma = $m; $this->tin = $t; }
    function get_error_message() { return $this->tin; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['OPT'] ) ? $GLOBALS['OPT'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['OPT'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['OPT'][ $k ] ); return true; }
function sitetop_get_option( $k, $d = '' ) { return get_option( 'sitetop_' . $k, $d ); }
function add_action() {} function do_action( $h ) { $GLOBALS['HOOK'][] = func_get_args(); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $s ) { return trim( (string) $s ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function sitetop_current_time() { return '2026-10-06 12:00:00'; }
function get_user_meta( $u, $k, $s = false ) { return $GLOBALS['META'][ $u ][ $k ] ?? ''; }
function get_userdata( $u ) { return (object) array( 'user_registered' => '2026-01-01 00:00:00' ); }
function get_user_by( $f, $v ) { return (object) array( 'user_login' => 'nguoi' . $v ); }
function sitetop_get_active_referrer_id( $u ) { return 99; }
function sitetop_get_user_balance_amount( $u ) { return $GLOBALS['SO_DU'] ?? 0; }
function sitetop_sync_user_balance( $u ) {}
function is_user_logged_in() { return true; }
function update_user_meta( $u, $k, $v ) { return true; }
function sitetop_send_withdrawal_pending_email( $id ) { $GLOBALS['MAIL'][] = $id; }
class US_Wpdb {
    public $prefix = 'wpgd_'; public $sql = array(); public $them = array(); public $rows_affected = 1; public $insert_id = 501;
    public function prepare( $q, ...$a ) {
        if ( count( $a ) === 1 && is_array( $a[0] ) ) $a = $a[0];
        $i = 0;
        return preg_replace_callback( '/%[sdf]/', function ( $m ) use ( &$i, $a ) {
            $v = $a[ $i++ ] ?? null;
            if ( $m[0] === '%d' ) return (string) (int) $v;
            if ( $m[0] === '%f' ) return sprintf( '%F', (float) $v );
            return "'" . addslashes( (string) $v ) . "'";
        }, $q );
    }
    public function query( $q ) { $this->sql[] = preg_replace( '/\s+/', ' ', trim( $q ) ); return 1; }
    public function insert( $t, $d ) { $this->them[] = array( 'bang' => $t, 'du_lieu' => $d ); return 1; }
    public function update( $t, $d, $w ) { return 1; }
    public function get_var( $q ) { return 0; }
    public function get_row( $q ) { return (object) array( 'balance' => $GLOBALS['SO_DU'] ?? 0 ); }
    public function get_col( $q ) { return array(); }
}
$GLOBALS['wpdb'] = new US_Wpdb();
PHP;
    $ma = $nen . "\n"
        . '$GLOBALS["OPT"] = ' . var_export( $opt + array( 'sitetop_che_do_usd' => $usd ? 1 : 0 ), true ) . ";\n"
        . "require '" . $__us_goc . "/includes/tien-usd.php';\n"
        . $__us_ma . "\n" . $than;
    $p = proc_open( array( PHP_BINARY, '-d', 'display_errors=stderr', '-r', $ma ),
        array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $ong );
    $out = stream_get_contents( $ong[1] ); $err = stream_get_contents( $ong[2] );
    fclose( $ong[1] ); fclose( $ong[2] ); proc_close( $p );
    $j = json_decode( trim( $out ), true );
    return array( is_array( $j ) ? $j : array(), $err ?: $out );
};

$__us_rate35 = array( 'sitetop_usd_rate' => 22000,
    'sitetop_usd_user_keyword_1step' => 35, 'sitetop_usd_user_keyword_2step' => 40, 'sitetop_usd_user_keyword_nocode' => 30,
    'sitetop_usd_user_direct_1step'  => 25, 'sitetop_usd_user_direct_2step'  => 28, 'sitetop_usd_user_direct_nocode'  => 20,
    'sitetop_usd_user_onsite_extra_120' => 5,
    'sitetop_min_withdrawal_usd' => 5, 'sitetop_max_withdrawal_usd' => 50, 'sitetop_referral_commission_percent' => 10 );

/* ═══ A. ĐỊNH DẠNG — đúng cách chủ site viết ═══ */
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, 'echo json_encode( array_map( "sitetop_format_usd", array(
    "a" => 0.035, "b" => 0.35, "c" => 3.5, "d" => 35, "e" => 1234.5, "f" => 0.0227273, "g" => 9.4909, "h" => -3.5, "i" => 0,
    "j" => 0.02272727, "k" => 12.3, "m" => 1234.5678 ) ) );' );
assert_true( is_array( $__us_k ) && $__us_k, 'Chay duoc sitetop_format_usd. stderr: ' . $__us_e );
assert_equals( '$0,035',    $__us_k['a'] ?? '', '1 view $0,035 (khong duoc lam tron thanh $0,04)' );
assert_equals( '$0,35',     $__us_k['b'] ?? '', '10 view $0,35' );
assert_equals( '$3,50',     $__us_k['c'] ?? '', '100 view $3,50' );
assert_equals( '$35',       $__us_k['d'] ?? '', '1.000 view $35 — so chan khong in ,00' );
assert_equals( '$1.234,50', $__us_k['e'] ?? '', 'Hang nghin dung dau cham, phan le dau phay' );
assert_equals( '$0,0227273', $__us_k['f'] ?? '', 'KHONG LAM TRON: in dung 7 so le dang co' );
assert_equals( '$9,4909',    $__us_k['g'] ?? '', 'KHONG LAM TRON: $9,4909 khong duoc in thanh $9,49' );
assert_equals( '-$3,50',    $__us_k['h'] ?? '', 'So am' );
assert_equals( '$0',        $__us_k['i'] ?? '', 'So 0' );
assert_equals( '$0,02272727', $__us_k['j'] ?? '', 'KHONG LAM TRON: du 8 so le' );
assert_equals( '$12,30',      $__us_k['k'] ?? '', 'Toi thieu 2 so le' );
assert_equals( '$1.234,5678', $__us_k['m'] ?? '', 'Hang nghin + 4 so le, khong cat' );
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, 'echo json_encode( array_map( "sitetop_format_usd", array( "a" => 30, "b" => 30.5, "c" => 3.5, "d" => 1000 ) ) );' );
assert_equals( '$30',    $__us_k['a'] ?? '', 'Rate $30 in "$30", KHONG "$30,00" (chu site chot)' );
assert_equals( '$30,50', $__us_k['b'] ?? '', 'Co le thi giu toi thieu 2 so le' );
assert_equals( '$3,50',  $__us_k['c'] ?? '', '100 view = $3,50 (vi du trong yeu cau)' );
assert_equals( '$1.000', $__us_k['d'] ?? '', 'Hang nghin chan: "$1.000"' );

/* ═══ B. VÍ DỤ CỦA CHỦ SITE: rate $35/1.000 view, cộng dồn từng view ═══ */
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, '
    $camp = (object) array( "campaign_type" => "keyword_search", "traffic_type" => "1step", "user_reward" => 0 );
    $mot = sitetop_get_reward_amount( $camp );
    $tong = function ( $n ) use ( $mot ) { $t = 0; for ( $i = 0; $i < $n; $i++ ) $t = round( $t + $mot, 8 ); return $t; };
    echo json_encode( array( "mot" => $mot, "10" => $tong( 10 ), "100" => $tong( 100 ), "1000" => $tong( 1000 ),
        "in10" => sitetop_format_usd( $tong( 10 ) ), "in100" => sitetop_format_usd( $tong( 100 ) ), "in1000" => sitetop_format_usd( $tong( 1000 ) ) ) );' );
assert_equals( 0.035, (float) ( $__us_k['mot'] ?? -1 ), '1 view = $0,035 (rate $35/1.000). stderr: ' . $__us_e );
assert_equals( '$0,35',  $__us_k['in10'] ?? '', '10 view = $0,35' );
assert_equals( '$3,50',  $__us_k['in100'] ?? '', '100 view = $3,50' );
assert_equals( '$35', $__us_k['in1000'] ?? '', '1.000 view = $35' );

/* ═══ C. KHÔNG ĐƯỢC RƠI VỀ MẶC ĐỊNH 800 ═══ */
list( $__us_k, $__us_e ) = $__us_chay( true, array( 'sitetop_usd_rate' => 22000 ), '
    $camp = (object) array( "campaign_type" => "keyword_search", "traffic_type" => "1step", "user_reward" => 0 );
    echo json_encode( array( "r" => sitetop_get_reward_amount( $camp ) ) );' );
assert_equals( 0.0, (float) ( $__us_k['r'] ?? -1 ),
    'SONG CON: che do USD chua cai rate thi tra 0 — KHONG duoc roi ve 800 (= $800/view). stderr: ' . $__us_e );
// Camp đã có mức đóng băng (đã quy đổi) thì dùng đúng mức đó.
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, '
    $camp = (object) array( "campaign_type" => "keyword_search", "traffic_type" => "1step", "user_reward" => 0.02272727 );
    echo json_encode( array( "r" => sitetop_get_reward_amount( $camp ) ) );' );
assert_equals( 0.02272727, (float) ( $__us_k['r'] ?? -1 ), 'Camp da quy doi: dung dung muc dong bang USD/view' );
// Chế độ VNĐ: y hệt cũ.
list( $__us_k, $__us_e ) = $__us_chay( false, array( 'sitetop_keyword_user_1step' => 500 ), '
    $camp = (object) array( "campaign_type" => "keyword_search", "traffic_type" => "1step", "user_reward" => 0 );
    echo json_encode( array( "r" => sitetop_get_reward_amount( $camp ) ) );' );
assert_equals( 500.0, (float) ( $__us_k['r'] ?? -1 ), 'Che do VND: van tra 500d nhu cu. stderr: ' . $__us_e );

/* ═══ D. MỨC THƯỞNG ĐÓNG BĂNG VÀO CAMP MỚI (gom từ 4 chỗ chép tay) ═══ */
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, 'echo json_encode( array(
    "kw70"  => sitetop_user_reward_cho_camp( "keyword_search", "1step", 70 ),
    "kw120" => sitetop_user_reward_cho_camp( "keyword_search", "1step", 120 ),
    "dr2"   => sitetop_user_reward_cho_camp( "traffic_direct", "2step", 70 ) ) );' );
assert_equals( 0.035, (float) ( $__us_k['kw70'] ?? -1 ),  'Camp keyword 1 buoc 70s: $35/1000 = $0,035/view. stderr: ' . $__us_e );
assert_equals( 0.04,  (float) ( $__us_k['kw120'] ?? -1 ), 'Phu phi onsite 120s $5/1000 cong them: $0,04/view' );
assert_equals( 0.028, (float) ( $__us_k['dr2'] ?? -1 ),   'Direct 2 buoc: $28/1000 = $0,028/view' );
list( $__us_k, $__us_e ) = $__us_chay( false, array( 'sitetop_keyword_user_1step' => 500, 'sitetop_user_onsite_extra_120' => 50 ), 'echo json_encode( array(
    "kw70" => sitetop_user_reward_cho_camp( "keyword_search", "1step", 70 ),
    "kw120" => sitetop_user_reward_cho_camp( "keyword_search", "1step", 120 ) ) );' );
assert_equals( 500.0, (float) ( $__us_k['kw70'] ?? -1 ),  'VND: dung cong thuc cu (rate). stderr: ' . $__us_e );
assert_equals( 550.0, (float) ( $__us_k['kw120'] ?? -1 ), 'VND: dung cong thuc cu (rate + phu phi onsite)' );

/* ═══ E. CỘNG SỐ DƯ — bẫy absint + %d ═══ */
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, '
    sitetop_add_user_balance( 7, 0.035, "shortlink_reward", "test" );
    echo json_encode( array( "sql" => $GLOBALS["wpdb"]->sql, "them" => $GLOBALS["wpdb"]->them ) );' );
$__us_sql = implode( ' | ', (array) ( $__us_k['sql'] ?? array() ) );
assert_true( strpos( $__us_sql, 'balance = balance + 0.03500000' ) !== false,
    'SONG CON: cong so du $0,035 phai ra 0.03500000 — absint + %d lam no thanh 0. SQL: ' . $__us_sql . ' stderr: ' . $__us_e );
$__us_dong = $__us_k['them'][0]['du_lieu'] ?? array();
assert_equals( 0.035, (float) ( $__us_dong['amount'] ?? -1 ), 'So cai (transactions) phai ghi dung $0,035' );
// Bẫy %f: wpdb::prepare đổi %f → %F 6 số lẻ. 0,02272727 (rate $22,727273/1.000 view) phải vào sổ ĐỦ 8 số lẻ.
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, '
    sitetop_add_user_balance( 7, 0.02272727, "shortlink_reward", "test" );
    echo json_encode( array( "sql" => $GLOBALS["wpdb"]->sql, "them" => $GLOBALS["wpdb"]->them ) );' );
$__us_sql = implode( ' | ', (array) ( $__us_k['sql'] ?? array() ) );
assert_true( strpos( $__us_sql, 'balance = balance + 0.02272727,' ) !== false && strpos( $__us_sql, '0.022727,' ) === false,
    'KHONG LAM TRON: $0,02272727 phai vao SQL du 8 so le, khong duoc bi %f cat con 0.022727. SQL: ' . $__us_sql . ' stderr: ' . $__us_e );
assert_equals( 0.02272727, (float) ( $__us_k['them'][0]['du_lieu']['amount'] ?? -1 ), 'So cai ghi du 8 so le' );
// Chế độ VNĐ: y hệt cũ (số nguyên, %d).
list( $__us_k, $__us_e ) = $__us_chay( false, array(), '
    sitetop_add_user_balance( 7, 500, "shortlink_reward", "test" );
    echo json_encode( array( "sql" => $GLOBALS["wpdb"]->sql ) );' );
assert_true( strpos( implode( ' | ', (array) ( $__us_k['sql'] ?? array() ) ), 'balance = balance + 500,' ) !== false,
    'Che do VND: cong so du van la so nguyen nhu cu. stderr: ' . $__us_e );

/* ═══ F. HOA HỒNG GIỚI THIỆU — bẫy (int) round() ═══ */
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, '
    sitetop_pay_referral_commission( 7, 0.035, "shortlink_reward" );
    echo json_encode( array( "them" => $GLOBALS["wpdb"]->them ) );' );
$__us_hh = null;
foreach ( (array) ( $__us_k['them'] ?? array() ) as $__us_r ) {
    if ( ( $__us_r['du_lieu']['type'] ?? '' ) === 'referral_commission' ) $__us_hh = (float) $__us_r['du_lieu']['amount'];
}
assert_equals( 0.0035, $__us_hh, 'SONG CON: 10% cua $0,035 = $0,0035 — (int) round() lam no thanh 0. stderr: ' . $__us_e );

/* ═══ G. LỆNH RÚT — bẫy absint, luật tròn nghìn, trừ số dư %d ═══ */
$__us_rut = function ( $so_tien, $so_du ) use ( $__us_chay, $__us_rate35 ) {
    return $__us_chay( true, $__us_rate35, '
        $GLOBALS["SO_DU"] = ' . var_export( $so_du, true ) . ';
        $r = sitetop_submit_withdrawal( 7, ' . var_export( $so_tien, true ) . ', "bank", array() );
        echo json_encode( array( "loi" => is_wp_error( $r ) ? $r->tin : "", "sql" => $GLOBALS["wpdb"]->sql, "them" => $GLOBALS["wpdb"]->them ) );' );
};
// Rút ĐÚNG toàn bộ số dư $9,49 — không bị %d trừ 9; literal phải đủ 8 số lẻ (%f chỉ in 6).
list( $__us_k, $__us_e ) = $__us_rut( 9.49, 9.49 );
$__us_sql = implode( ' | ', (array) ( $__us_k['sql'] ?? array() ) );
assert_true( strpos( $__us_sql, 'balance=balance-9.49000000' ) !== false && strpos( $__us_sql, 'balance>=9.49000000' ) !== false,
    'SONG CON: rut $9,49 phai tru dung 9.49000000 — %d tru 9. SQL: ' . $__us_sql . ' Loi: ' . ( $__us_k['loi'] ?? '' ) . ' stderr: ' . $__us_e );
$__us_lenh = null;
foreach ( (array) ( $__us_k['them'] ?? array() ) as $__us_r ) {
    if ( strpos( $__us_r['bang'], 'withdrawals' ) !== false ) $__us_lenh = (float) $__us_r['du_lieu']['amount'];
}
assert_equals( 9.49, $__us_lenh, 'Lenh rut ghi dung $9,49' );
// Luật 06/10 chiều: CHỈ NHẬN TỐI ĐA 2 SỐ LẺ — từ 3 số lẻ là từ chối (không làm tròn hộ), không đụng CSDL.
foreach ( array( 9.4909, 12.34567891, 30.123 ) as $__us_x ) {
    list( $__us_k, $__us_e ) = $__us_rut( $__us_x, 100 );
    assert_true( strpos( (string) ( $__us_k['loi'] ?? '' ), '2 số lẻ' ) !== false
              && strpos( implode( ' | ', (array) ( $__us_k['sql'] ?? array() ) ), 'balance=balance-' ) === false,
        'Rut ' . $__us_x . ' (qua 2 so le) phai bi tu choi, khong tru tien. Loi: ' . ( $__us_k['loi'] ?? '' ) . ' stderr: ' . $__us_e );
}
list( $__us_k, $__us_e ) = $__us_rut( 30.12, 100 );
assert_true( strpos( implode( ' | ', (array) ( $__us_k['sql'] ?? array() ) ), 'balance=balance-30.12000000' ) !== false,
    'Rut $30,12 (dung 2 so le) phai duoc nhan, tru dung 30.12000000. Loi: ' . ( $__us_k['loi'] ?? '' ) . ' stderr: ' . $__us_e );
list( $__us_k, $__us_e ) = $__us_rut( 4.99, 100 );
assert_true( strpos( (string) ( $__us_k['loi'] ?? '' ), 'Rút tối thiểu: $5' ) !== false,
    'Duoi muc toi thieu USD phai bi chan va bao bang USD. Loi: ' . ( $__us_k['loi'] ?? '' ) . ' stderr: ' . $__us_e );
list( $__us_k, $__us_e ) = $__us_rut( 50.01, 100 );
assert_true( strpos( (string) ( $__us_k['loi'] ?? '' ), 'tối đa $50' ) !== false,
    'Vuot tran USD phai bi chan. Loi: ' . ( $__us_k['loi'] ?? '' ) );
list( $__us_k, $__us_e ) = $__us_rut( 12.35, 100 );
assert_true( strpos( (string) ( $__us_k['loi'] ?? '' ), '1.000đ' ) === false,
    'Luat tron 1.000d KHONG ap cho USD. Loi: ' . ( $__us_k['loi'] ?? '' ) . ' stderr: ' . $__us_e );

/* ═══ H. RATE RIÊNG TỪNG USER — khoá USD, đơn vị USD / 1.000 view ═══ */
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, '
    $GLOBALS["META"][7]["sitetop_rate_rieng_usd"] = array( "keyword_1step" => 50 );
    $GLOBALS["META"][7]["sitetop_rate_rieng"]     = array( "keyword_1step" => 700 );
    echo json_encode( array( "r" => sitetop_rate_rieng_cua_user( 7, "keyword_search", "1step" ) ) );' );
assert_equals( 0.05, (float) ( $__us_k['r'] ?? -1 ),
    'USD: rate rieng doc khoa USD ($50/1000 = $0,05/view), KHONG doc khoa VND cu. stderr: ' . $__us_e );

/* ═══ I. ADMIN XEM LỆNH RÚT — ví dụ của chủ site ═══ */
list( $__us_k, $__us_e ) = $__us_chay( true, array( 'sitetop_usd_rate' => 22000 ), 'echo json_encode( array(
    "a" => sitetop_format_rut_cho_admin( 35 ), "vnd" => sitetop_usd_sang_vnd( 35 ) ) );' );
assert_equals( '$35 (≈ 770.000đ)', $__us_k['a'] ?? '', 'Admin thay $35 kem 770.000d (ty gia 22.000). stderr: ' . $__us_e );
assert_equals( 770000, (int) ( $__us_k['vnd'] ?? 0 ), 'Quy doi $35 x 22.000 = 770.000d' );
list( $__us_k, $__us_e ) = $__us_chay( false, array(), 'echo json_encode( array( "a" => sitetop_format_rut_cho_admin( 233000 ) ) );' );
assert_equals( '233.000đ', $__us_k['a'] ?? '', 'Che do VND: admin van thay VND nhu cu. stderr: ' . $__us_e );

/* ═══ J. TẠM KHOÁ TIỀN tự mở sau hạn (script chuyển có chết giữa chừng cũng không treo) ═══ */
list( $__us_k, $__us_e ) = $__us_chay( false, array( 'sitetop_tam_khoa_tien' => time() + 60 ), 'echo json_encode( array( "k" => sitetop_tam_khoa_tien() ) );' );
assert_true( ! empty( $__us_k['k'] ), 'Moc khoa con han thi dang khoa. stderr: ' . $__us_e );
list( $__us_k, $__us_e ) = $__us_chay( false, array( 'sitetop_tam_khoa_tien' => time() - 1 ), 'echo json_encode( array( "k" => sitetop_tam_khoa_tien() ) );' );
assert_true( isset( $__us_k['k'] ) && $__us_k['k'] === false, 'Qua han thi tu mo khoa. stderr: ' . $__us_e );

/* ═══ K. TIỀN KHÁCH HÀNG KHÔNG BỊ ĐỘNG ═══ */
// Doanh thu nền tảng phải là VNĐ − VNĐ (thưởng user quy đổi), không phải VNĐ − USD.
$__us_ad = (string) file_get_contents( $__us_goc . '/includes/admin-dashboard.php' );
assert_true( strpos( $__us_ad, "'platform_revenue' => \$customer_paid_total - \$sum_user_earned * \$_R," ) !== false,
    'Doanh thu nen tang phai quy thuong user ve VND truoc khi tru' );
// Phía khách: cổng tạo/sửa camp không được in giá khách bằng USD.
$__us_cca = (string) file_get_contents( $__us_goc . '/includes/customer-campaign-ajax.php' );
// Ngoại lệ duy nhất: cột "+thưởng" trong bảng lượt xem là tiền USER (reward_amount) → in theo đơn vị user (06/10 tối).
assert_equals( 1, substr_count( $__us_cca, 'sitetop_format_tien_user' ), 'Ben khach hang: dinh dang tien user CHI o cot thuong user (reward_amount)' );
assert_true( strpos( $__us_cca, 'sitetop_format_tien_user($v->reward_amount)' ) !== false, 'Cho duy nhat do la $v->reward_amount' );
// Giá/view của khách vẫn trừ bằng %d như cũ (sổ khách là VNĐ nguyên).
assert_true( strpos( $__us_ver, 'UPDATE {$p}customer_balance SET balance = balance - %d' ) !== false,
    'So du khach hang van tru so nguyen VND nhu cu' );

/* ═══ L. TIỀN HIỆN Ở BẢNG ĐIỀU KHIỂN — KHÔNG HIỆN TRÊN TRANG NHIỆM VỤ (chủ site chốt 06/10 chiều) ═══ */
$__us_ajx = (string) file_get_contents( $__us_goc . '/includes/shortlink-ajax.php' );
$__us_vt  = strpos( $__us_ajx, 'function sitetop_ajax_verify_shortlink_code' );
$__us_than = substr( $__us_ajx, $__us_vt, 3000 );
assert_true( strpos( $__us_than, 'reward_text' ) === false,
    'Cong xac minh ma KHONG kem so tien da dinh dang — nguoi lam nhiem vu tren shortlink khong duoc thay $' );
$__us_pu = (string) file_get_contents( $__us_goc . '/page-unlock.php' );
assert_true( strpos( $__us_pu, 'reward_text' ) === false && strpos( $__us_pu, 'đã cộng vào số dư' ) === false,
    'Trang nhiem vu KHONG bao "+$..." khi nhap ma xong' );
assert_true( strpos( $__us_pu, "showToast('Thành công! Đang chuyển hướng...', 'success');" ) !== false
          && strpos( $__us_pu, "setTimeout(function() { window.location.href = url; }, 1200);" ) !== false,
    'Thong bao thanh cong giu nguyen chu cu, chuyen huong sau 1,2 giay nhu cu' );
// Bộ định dạng tiền user (bảng điều khiển, lịch sử) vẫn in đúng 1 view $0,035.
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, 'echo json_encode( array( "t" => sitetop_format_tien_user( 0.035 ) ) );' );
assert_equals( '$0,035', $__us_k['t'] ?? '', 'Bang dieu khien in 1 view la "$0,035". stderr: ' . $__us_e );

/* ═══ M. HÀM CHUYỂN / LÙI — thứ tự an toàn và nhân ngược đúng tỷ giá ═══ */
$__us_tu = (string) file_get_contents( $__us_goc . '/includes/tien-usd.php' );
$__us_vt0 = strpos( $__us_tu, 'function sitetop_chuyen_sang_usd(' );
$__us_than = substr( $__us_tu, $__us_vt0 );
$__us_p_commit = strpos( $__us_than, "\$wpdb->query( 'COMMIT' );" );
$__us_p_bat    = strpos( $__us_than, "update_option( 'sitetop_che_do_usd', 1 );" );
$__us_p_rate   = strpos( $__us_than, "\$dat = function" );
assert_true( $__us_p_commit !== false && $__us_p_bat !== false && $__us_p_rate !== false
          && $__us_p_commit < $__us_p_bat && $__us_p_bat < $__us_p_rate,
    'SONG CON: cong tac USD phai bat NGAY SAU COMMIT, truoc moi viec phu — PHP chet giua chung la he chay VND tren so USD' );
assert_true( substr_count( $__us_than, "update_option( 'sitetop_che_do_usd', 1 );" ) === 1, 'Chi bat cong tac mot lan, dung cho' );

// Lùi về VNĐ chạy THẬT với CSDL giả: nhân đúng tỷ giá đã chuyển, tắt công tắc, trả option cũ.
list( $__us_k, $__us_e ) = $__us_chay( true, array(
        'sitetop_usd_chuyen_luc' => array( 'luc' => 1, 'ty_gia' => 22000 ),
        'sitetop_usd_sao_luu'    => array( 'opt' => array( 'keyword_user_1step' => 500, 'min_withdrawal' => 100000 ) ),
        'sitetop_keyword_user_1step' => 'rac' ), '
    $r = sitetop_lui_ve_vnd();
    echo json_encode( array( "kq" => $r, "sql" => $GLOBALS["wpdb"]->sql, "che_do" => get_option("sitetop_che_do_usd"),
        "rate_cu" => get_option("sitetop_keyword_user_1step"), "min_cu" => get_option("sitetop_min_withdrawal") ) );' );
$__us_sql = implode( ' | ', (array) ( $__us_k['sql'] ?? array() ) );
assert_true( strpos( $__us_sql, 'balance = ROUND(balance * 22000, 2)' ) !== false,
    'Lui phai NHAN NGUOC dung ty gia da chuyen (22000). SQL: ' . substr( $__us_sql, 0, 200 ) . ' stderr: ' . $__us_e );
assert_equals( 0, (int) ( $__us_k['che_do'] ?? 1 ), 'Lui xong phai tat cong tac USD' );
assert_equals( 500, (int) ( $__us_k['rate_cu'] ?? 0 ), 'Lui phai tra lai rate VND cu tu snapshot' );
assert_equals( 100000, (int) ( $__us_k['min_cu'] ?? 0 ), 'Lui phai tra lai nguong rut VND cu' );
// Không có tỷ giá đã chuyển thì KHÔNG lùi mù.
list( $__us_k, $__us_e ) = $__us_chay( true, array(), 'echo json_encode( array( "kq" => sitetop_lui_ve_vnd(), "sql" => $GLOBALS["wpdb"]->sql ) );' );
assert_true( ! empty( $__us_k['kq']['loi'] ) && empty( $__us_k['sql'] ),
    'Thieu ty gia da chuyen thi phai tu choi lui, khong duoc dong toi CSDL. stderr: ' . $__us_e );
// Đang ở VNĐ thì chuyển-sang-USD mới chạy; đang USD thì từ chối chuyển lại.
list( $__us_k, $__us_e ) = $__us_chay( true, array(), 'echo json_encode( array( "kq" => sitetop_chuyen_sang_usd( false ) ) );' );
assert_true( ! empty( $__us_k['kq']['loi'] ), 'Dang USD roi thi khong duoc chuyen lan nua. stderr: ' . $__us_e );

/* ═══ N. FORM RÚT TIỀN — chủ site chốt 06/10: bỏ dòng luật VNĐ, ký hiệu đơn vị đúng chế độ ═══ */
$__us_ud = (string) file_get_contents( $__us_goc . '/page-user-dashboard.php' );
assert_true( strpos( $__us_ud, 'class="wd-hint"' ) === false && strpos( $__us_ud, 'tr&#242;n 1.000&#273;</b> (ph&#7847;' ) === false,
    'Bo dong "Toi thieu … · Toi da … · chi nhan so tron 1.000d (phan le giu lai trong vi)"' );
assert_true( strpos( $__us_ud, '.wd-hint{' ) === false, 'CSS cua dong nhac cung phai don' );
assert_true( strpos( $__us_ud, "<span><?php echo \$usd_mode ? '$' : '&#273;'; ?></span>" ) !== false,
    'Ky hieu don vi o nhap rut tien phai theo che do: $ khi USD, d khi VND' );

/* ═══ O. Ô RÚT TIỀN USD (chủ site chốt 06/10 chiều): chữ mờ "30,00 $" theo cấu hình admin, nhận "30,5",
   chặn dưới mức tối thiểu / vượt số dư NGAY TẠI CHỖ, máy chủ hiểu dấu phẩy; khối tỉ giá dưới thẻ rate ═══ */
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, 'echo json_encode( array_map( "sitetop_usd_so", array(
    "a" => 30, "b" => 4.55, "c" => 2.27272727, "d" => 0.5, "e" => 30.1, "f" => 1234.5, "g" => 30.123 ) ) );' );
assert_equals( '30,00',      $__us_k['a'] ?? '', 'Chu mo muc toi thieu $30 la "30,00" dung kieu chu site viet. stderr: ' . $__us_e );
assert_equals( '4,55',       $__us_k['b'] ?? '', '4.55 → "4,55"' );
assert_equals( '2,27272727', $__us_k['c'] ?? '', 'Giu du 8 so le dang co, KHONG lam tron' );
assert_equals( '0,50',       $__us_k['d'] ?? '', '0.5 → "0,50" (toi thieu 2 so le)' );
assert_equals( '30,10',      $__us_k['e'] ?? '', '30.1 → "30,10"' );
assert_equals( '1234,50',    $__us_k['f'] ?? '', 'KHONG cham hang nghin — user go lai y nguyen van doc duoc' );
assert_equals( '30,123',     $__us_k['g'] ?? '', '30.123 → "30,123": chi bo so 0 thua' );

// Giao diện: ô chữ, chữ mờ lấy từ cấu hình admin (không cố định), ngưỡng trong data-*, báo lỗi dưới ô.
$__us_ud = (string) file_get_contents( $__us_goc . '/page-user-dashboard.php' );
assert_true( strpos( $__us_ud, 'placeholder="<?php echo esc_attr( sitetop_usd_so( $min_wd ) . \' $\' ); ?>"' ) !== false,
    'Chu mo = muc toi thieu ADMIN CAI + " $" — khong co so nao co dinh trong ma' );
assert_true( strpos( $__us_ud, '<input type="text" inputmode="decimal" autocomplete="off" id="wdAmount"' ) !== false,
    'USD: o CHU (go duoc "30,5"), ban phim so thap phan tren dien thoai' );
assert_true( strpos( $__us_ud, 'data-min="<?php echo esc_attr( number_format( $min_wd, 8' ) !== false
          && strpos( $__us_ud, 'data-bal="<?php echo esc_attr( number_format( (float) $balance, 8' ) !== false
          && strpos( $__us_ud, 'data-max="<?php echo esc_attr( number_format( $max_wd, 8' ) !== false,
    'Nguong toi thieu / so du / tran dua vao data-* du 8 so le cho JS kiem' );
assert_true( strpos( $__us_ud, '<div class="wd-amt-err" id="wdAmtErr" role="alert"></div>' ) !== false
          && strpos( $__us_ud, '.wd-amt-err.on{display:block}' ) !== false,
    'Co cho bao loi do ngay duoi o nhap' );
assert_true( strpos( $__us_ud, '<form id="wdForm"<?php echo $usd_mode ? \' novalidate\' : \'\'; ?>>' ) !== false,
    'USD: tat bong bong loi tieng Anh cua trinh duyet, JS bao tieng Viet thay; VND giu nguyen' );
assert_true( strpos( $__us_ud, '.wd-amount.usd input:placeholder-shown+span{display:none}' ) !== false,
    'Chu mo "30,00 $" dang hien thi khong in them dau $ thu hai ben phai' );
assert_true( strpos( $__us_ud, "if(!wdKiemTra(true)){" ) !== false && strpos( $__us_ud, "fd.set('amount',String(wdDocSo(fd.get('amount'))));" ) !== false,
    'Gui form: kiem lan cuoi, loi thi KHONG gui; "30,5" doi thanh "30.5" truoc khi gui' );
// Khối tỉ giá: dưới thẻ rate, chỉ ở chế độ USD, đọc tỉ giá admin cài, đúng chữ chủ site đưa.
$__us_vt_tg = strpos( $__us_ud, 'class="tygia-box"' );
assert_true( $__us_vt_tg !== false, 'Co khoi "Ti gia $ tai Sitetop"' );
assert_true( strpos( $__us_ud, 'Tỉ giá quy đổi từ $ sang VNĐ là: <b>1$ = <?php echo sitetop_format_money( sitetop_usd_rate() ); ?></b>' ) !== false,
    'Dong ti gia: bo "tai SITETOP" (chu site chot 06/10 chieu); so lay tu option usd_rate' );
assert_equals( 1, substr_count( substr( $__us_ud, $__us_vt_tg, 600 ), '<span class="brand">Sitetop</span>' ), 'Chi con MOT nhan SITETOP (o tieu de)' );
assert_true( strpos( $__us_ud, 'class="rate-box"' ) < $__us_vt_tg
          && substr_count( substr( $__us_ud, strrpos( substr( $__us_ud, 0, $__us_vt_tg ), '<?php if ( $usd_mode ) : ?>' ), 400 ), 'tygia-box' ) >= 1,
    'Khoi ti gia nam DUOI the rate va chi hien o che do USD' );

// Chạy THẬT bộ kiểm JS trong node — hàm thuần trích từ trang.
$__us_js_a = strpos( $__us_ud, 'function wdDocSo(' );
$__us_js_b = strpos( $__us_ud, 'function wdKiemTra(' );
assert_true( $__us_js_a !== false && $__us_js_b !== false && $__us_js_b > $__us_js_a, 'Trich duoc wdDocSo/wdHienSo/wdLoiSoTien' );
$__us_js = substr( $__us_ud, $__us_js_a, $__us_js_b - $__us_js_a ) . <<<'JS'

function stUsd(a){return '$'+String(a).replace('.',',')}
console.log(JSON.stringify({
  ok:[wdLoiSoTien('30',30,100,500),wdLoiSoTien('30,5',30,100,500),wdLoiSoTien('30.5',30,100,500),wdLoiSoTien('100',30,100,0),wdLoiSoTien(' 45 $ ',30,100,500),wdLoiSoTien('30,12',30,100,500)],
  duoi:wdLoiSoTien('29,99',30,100,500), vuot:wdLoiSoTien('150',30,100,500), tran:wdLoiSoTien('600',30,1000,500),
  rong:wdLoiSoTien('',30,100,500), chu:wdLoiSoTien('abc',30,100,500), hai:wdLoiSoTien('1.000,50',30,5000,0),
  le9:wdLoiSoTien('30,123456789',30,100,500), le3:wdLoiSoTien('30,123',30,100,500), am:wdLoiSoTien('-5',30,100,500), khong:wdLoiSoTien('0',30,100,500),
  doc:[wdDocSo('30,5'),wdDocSo('30.5'),wdDocSo('30 $'),String(wdDocSo('abc')),String(wdDocSo('1e3'))],
  hien:[wdHienSo(30),wdHienSo(100.12345678),wdHienSo(0.5),wdHienSo(100),wdHienSo(10.5)]
}));
JS;
$__us_f = sys_get_temp_dir() . '/st-usd-wd-' . getmypid() . '.js';
file_put_contents( $__us_f, $__us_js );
$__us_r = (string) shell_exec( 'node ' . escapeshellarg( $__us_f ) . ' 2>&1' );
@unlink( $__us_f );
$__us_k = json_decode( trim( $__us_r ), true );
assert_true( is_array( $__us_k ), 'Chay duoc bo kiem o rut tien bang node. Ra: ' . $__us_r );
assert_equals( array( '', '', '', '', '', '' ), $__us_k['ok'] ?? null, 'Hop le: 30 · "30,5" · "30.5" · dung bang so du · co khoang trang/$ · 30,12 — deu KHONG bao loi. Ra: ' . json_encode( $__us_k['ok'] ?? null, JSON_UNESCAPED_UNICODE ) );
assert_true( strpos( (string) ( $__us_k['duoi'] ?? '' ), 'tối thiểu $30' ) !== false, 'Duoi muc toi thieu → bao ro "Rut toi thieu $30". Ra: ' . ( $__us_k['duoi'] ?? '' ) );
assert_true( strpos( (string) ( $__us_k['vuot'] ?? '' ), 'số dư' ) !== false && strpos( (string) ( $__us_k['vuot'] ?? '' ), '$100' ) !== false, 'Vuot so du → bao ro so du dang co. Ra: ' . ( $__us_k['vuot'] ?? '' ) );
assert_true( strpos( (string) ( $__us_k['tran'] ?? '' ), 'tối đa $500' ) !== false, 'Vuot tran moi lan → bao tran. Ra: ' . ( $__us_k['tran'] ?? '' ) );
assert_true( strpos( (string) ( $__us_k['rong'] ?? '' ), 'Nhập số tiền' ) !== false, 'Bo trong → nhac nhap' );
foreach ( array( 'chu', 'hai', 'am', 'khong' ) as $__us_c )
    assert_true( strpos( (string) ( $__us_k[ $__us_c ] ?? '' ), 'không hợp lệ' ) !== false, 'Khong phai so (' . $__us_c . ') → "khong hop le". Ra: ' . ( $__us_k[ $__us_c ] ?? '' ) );
assert_true( strpos( (string) ( $__us_k['le9'] ?? '' ), '2 số lẻ' ) !== false && strpos( (string) ( $__us_k['le3'] ?? '' ), '2 số lẻ' ) !== false,
    'Qua 2 so le (30,123) → tu choi, bao ro "toi da 2 so le" (chu site chot 06/10: chi rut toi cent). Ra: ' . ( $__us_k['le3'] ?? '' ) );
assert_equals( array( 30.5, 30.5, 30, 'NaN', 'NaN' ), $__us_k['doc'] ?? null, 'wdDocSo: phay va cham deu la thap phan; chu, 1e3 → NaN. Ra: ' . json_encode( $__us_k['doc'] ?? null ) );
assert_equals( array( '30', '100,12345678', '0,5', '100', '10,5' ), $__us_k['hien'] ?? null, 'wdHienSo: dien nut nhanh dang Viet, khong so 0 thua, khong e-8. Ra: ' . json_encode( $__us_k['hien'] ?? null ) );

// Chạy THẬT nút điền nhanh + kiểm tại chỗ với DOM giả. (Đột biến "String(Number(v))" từng LỌT vì test
// chỉ dò tên hàm "wdHienSo(v)" — chuỗi ấy còn nằm ở định nghĩa hàm. Phải chạy, không dò chữ.)
$__us_js_a0 = strpos( $__us_ud, 'function wdSetAmount(' );
$__us_js_c  = strpos( $__us_ud, 'function ajax(' );
assert_true( $__us_js_a0 !== false && $__us_js_c !== false && $__us_js_c > $__us_js_a0, 'Trich duoc wdSetAmount..wdKiemTra' );
$__us_js = substr( $__us_ud, $__us_js_a0, $__us_js_c - $__us_js_a0 ) . <<<'JS'

var ST_USD=1; function stUsd(a){return '$'+String(a).replace('.',',')}
var _i={value:'',dataset:{min:'30.00000000',bal:'100.12345678',max:'500.00000000'},focus:function(){},classList:{bad:false,toggle:function(c,v){this.bad=!!v}}};
var _e={textContent:'',classList:{on:false,toggle:function(c,v){this.on=!!v}}};
var document={getElementById:function(id){return id==='wdAmount'?_i:(id==='wdAmtErr'?_e:null)}};
wdSetAmount(100.12); var a=_i.value, aErr=_e.textContent;
wdSetAmount(30); var b=_i.value, bErr=_e.textContent;
wdSetAmount(0.5); var b2=_i.value, b2Err=_e.textContent;
_i.value='29,99'; wdKiemTra(); var cErr=_e.textContent, cOn=_e.classList.on, cBad=_i.classList.bad;
_i.value=''; var dGo=wdKiemTra(); var dErr=_e.textContent; var dGui=wdKiemTra(true); var dErr2=_e.textContent;
_i.value='30,5'; var eGui=wdKiemTra(true); var eErr=_e.textContent, eOn=_e.classList.on;
console.log(JSON.stringify({a:a,aErr:aErr,b:b,bErr:bErr,b2:b2,b2Err:b2Err,cErr:cErr,cOn:cOn,cBad:cBad,dGo:dGo,dErr:dErr,dGui:dGui,dErr2:dErr2,eGui:eGui,eErr:eErr,eOn:eOn}));
JS;
$__us_f = sys_get_temp_dir() . '/st-usd-wd2-' . getmypid() . '.js';
file_put_contents( $__us_f, $__us_js );
$__us_r = (string) shell_exec( 'node ' . escapeshellarg( $__us_f ) . ' 2>&1' );
@unlink( $__us_f );
$__us_k = json_decode( trim( $__us_r ), true );
assert_true( is_array( $__us_k ), 'Chay duoc wdSetAmount/wdKiemTra bang node. Ra: ' . $__us_r );
assert_equals( '100,12', $__us_k['a'] ?? '', 'Nut "Toan bo so du" dien tran da cat 2 so le, dau phay kieu Viet (khong phai 100.12). Ra: ' . json_encode( $__us_k, JSON_UNESCAPED_UNICODE ) );
assert_equals( '', $__us_k['aErr'] ?? 'x', 'Dien dung bang so du thi khong bao loi' );
assert_equals( '30', $__us_k['b'] ?? '', 'Nut $30 dien "30"' );
assert_equals( '0,5', $__us_k['b2'] ?? '', 'So le dien dang Viet "0,5"' );
assert_true( strpos( (string) ( $__us_k['b2Err'] ?? '' ), 'tối thiểu' ) !== false, 'Dien nhanh duoi muc toi thieu thi bao ngay' );
assert_true( ! empty( $__us_k['cOn'] ) && ! empty( $__us_k['cBad'] ) && strpos( (string) ( $__us_k['cErr'] ?? '' ), 'tối thiểu $30' ) !== false, 'Go 29,99: hien dong do + vien do' );
assert_true( ! empty( $__us_k['dGo'] ) && ( $__us_k['dErr'] ?? 'x' ) === '', 'Dang go ma o trong thi KHONG bao loi' );
assert_true( empty( $__us_k['dGui'] ) && strpos( (string) ( $__us_k['dErr2'] ?? '' ), 'Nhập số tiền' ) !== false, 'Bam gui ma o trong thi chan + nhac nhap' );
assert_true( ! empty( $__us_k['eGui'] ) && ( $__us_k['eErr'] ?? 'x' ) === '' && empty( $__us_k['eOn'] ), 'Go 30,5 hop le: cho gui, tat dong do' );

// Máy chủ hiểu dấu phẩy: cổng ajax nhận "30,5" → trừ đúng 30.50000000 (floatval("30,5") = 30 là rút thiếu).
$__us_wd_ajax = $__us_ham( $__us_ajx, 'sitetop_ajax_user_withdraw' );
assert_true( $__us_wd_ajax !== '', 'Trich duoc sitetop_ajax_user_withdraw' );
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, $__us_wd_ajax . '
    function check_ajax_referer() {} function sitetop_block_advertiser_ajax() {} function get_current_user_id() { return 7; }
    function wp_send_json_success( $d ) { echo json_encode( array( "ok" => $d, "sql" => $GLOBALS["wpdb"]->sql ) ); exit; }
    function wp_send_json_error( $d ) { echo json_encode( array( "loi" => $d, "sql" => $GLOBALS["wpdb"]->sql ) ); exit; }
    $GLOBALS["SO_DU"] = 100;
    $_POST = array( "amount" => "30,5", "method" => "bank", "bank_name" => "VCB", "bank_account" => "123", "bank_holder" => "A" );
    sitetop_ajax_user_withdraw();' );
$__us_sql = implode( ' | ', (array) ( $__us_k['sql'] ?? array() ) );
assert_true( ! empty( $__us_k['ok'] ), 'Rut "30,5" phai duoc nhan. Ra: ' . json_encode( $__us_k, JSON_UNESCAPED_UNICODE ) . ' stderr: ' . $__us_e );
assert_true( strpos( $__us_sql, '30.50000000' ) !== false && strpos( $__us_sql, '- 30.00000000' ) === false,
    '"30,5" phai tru dung 30.5 — khong phai 30. SQL: ' . substr( $__us_sql, 0, 300 ) );
// "30,123" (3 số lẻ) → cổng từ chối, không đụng CSDL — máy chủ không tin JS.
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, $__us_wd_ajax . '
    function check_ajax_referer() {} function sitetop_block_advertiser_ajax() {} function get_current_user_id() { return 7; }
    function wp_send_json_success( $d ) { echo json_encode( array( "ok" => $d, "sql" => $GLOBALS["wpdb"]->sql ) ); exit; }
    function wp_send_json_error( $d ) { echo json_encode( array( "loi" => $d, "sql" => $GLOBALS["wpdb"]->sql ) ); exit; }
    $GLOBALS["SO_DU"] = 100;
    $_POST = array( "amount" => "30,123", "method" => "bank", "bank_name" => "VCB", "bank_account" => "123", "bank_holder" => "A" );
    sitetop_ajax_user_withdraw();' );
assert_true( strpos( (string) ( $__us_k['loi'] ?? '' ), '2 số lẻ' ) !== false
          && strpos( implode( ' | ', (array) ( $__us_k['sql'] ?? array() ) ), 'balance=balance-' ) === false,
    'MAY CHU chan 3 so le ("30,123"), khong tru tien. Ra: ' . json_encode( $__us_k, JSON_UNESCAPED_UNICODE ) . ' stderr: ' . $__us_e );

/* ═══ P. CON SỐ TO Ở ĐẦU TRANG HIỆN GỌN (chủ site chốt 06/10 chiều): "$100,02272727" → "$100,022" ═══ */
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, 'echo json_encode( array_map( "sitetop_format_usd_gon", array(
    "a" => 100.02272727, "b" => 100.0229, "c" => 100, "d" => 100.5, "e" => 1.005, "f" => 0.02272727, "g" => -2.3456, "h" => 0.3 ) ) );' );
assert_equals( '$100,022', $__us_k['a'] ?? '', 'So du to: $100,02272727 chi hien $100,022. stderr: ' . $__us_e );
assert_equals( '$100,022', $__us_k['b'] ?? '', 'CAT chu KHONG lam tron: 100,0229 → 100,022 (khong phai 100,023)' );
assert_equals( '$100',     $__us_k['c'] ?? '', 'So chan van bo ,00' );
assert_equals( '$100,50',  $__us_k['d'] ?? '', 'Van giu toi thieu 2 so le' );
assert_equals( '$1,005',   $__us_k['e'] ?? '', 'Khong dinh sai so float: 1.005 → $1,005 (nhan 1000 roi floor se ra 1,004)' );
assert_equals( '$0,022',   $__us_k['f'] ?? '', 'So nho cung cat 3 so le' );
assert_equals( '-$2,345',  $__us_k['g'] ?? '', 'So am giu dau, cat ve 0' );
assert_equals( '$0,30',    $__us_k['h'] ?? '', '0.3 khong bi thanh 0,299' );
// Bản gọn dùng đúng 3 chỗ trên thẻ ví: số to + chip "Hôm nay" + chip "Tổng thu nhập" (chủ site chốt 06/10 chiều); chỗ khác in đủ.
$__us_ud = (string) file_get_contents( $__us_goc . '/page-user-dashboard.php' );
assert_true( strpos( $__us_ud, '<div class="wallet-v"><?php echo sitetop_format_tien_user_gon($balance); ?></div>' ) !== false,
    'Con so to "So du kha dung" dung ban gon 3 so le' );
assert_equals( 4, substr_count( $__us_ud, 'sitetop_format_tien_user_gon(' ), 'Ban gon dung DUNG 4 cho: the vi (3) + o "Co the rut", khong lan sang bang/lich su' );
// Ô "Có thể rút" (tab Rút tiền) = số rút được thật theo luật 2 số lẻ: $100,02272727 → $100,02.
assert_true( strpos( $__us_ud, '<div class="t-v"><?php echo sitetop_format_tien_user_gon($balance, 2); ?></div>' ) !== false,
    'O "Co the rut" cat 2 so le (dung bang so rut duoc thuc te)' );
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, 'echo json_encode( array( "a" => sitetop_format_tien_user_gon( 100.02272727, 2 ), "b" => sitetop_format_tien_user_gon( 100.02272727 ), "c" => sitetop_format_tien_user_gon( 100.5, 2 ), "d" => sitetop_format_tien_user_gon( 100.999, 2 ) ) );' );
assert_equals( '$100,02', $__us_k['a'] ?? '', '"Co the rut" $100,02272727 → $100,02. stderr: ' . $__us_e );
assert_equals( '$100,022', $__us_k['b'] ?? '', 'Mac dinh van 3 so le cho the vi' );
assert_equals( '$100,50', $__us_k['c'] ?? '', '100,5 → $100,50' );
assert_equals( '$100,99', $__us_k['d'] ?? '', '100,999 → $100,99 — cat, khong len $101' );
assert_true( strpos( $__us_ud, 'H&#244;m nay <b>+<?php echo sitetop_format_tien_user_gon($today_earned); ?></b>' ) !== false
          && strpos( $__us_ud, 'T&#7893;ng thu nh&#7853;p <b><?php echo sitetop_format_tien_user_gon($total_earned); ?></b>' ) !== false,
    'Hai chip "Hom nay" / "Tong thu nhap" cung in gon 3 so le ($0,02272727 → $0,022)' );
assert_true( strpos( $__us_ud, 'R&#250;t t&#7889;i thi&#7875;u <b><?php echo sitetop_format_tien_user($min_wd); ?></b>' ) !== false,
    'Chip "Rut toi thieu" giu ban in du (nguong admin cai, khong cat)' );
// Chế độ VNĐ: bản gọn in y như cũ.
list( $__us_k, $__us_e ) = $__us_chay( false, array(), 'echo json_encode( array( "t" => sitetop_format_tien_user_gon( 133500 ) ) );' );
assert_equals( '133.500đ', $__us_k['t'] ?? '', 'VND: ban gon = sitetop_format_money nhu cu. stderr: ' . $__us_e );
// Hàm cắt dùng cho trần "Toàn bộ số dư": đúng 2 số lẻ, không làm tròn, không dính sai số float.
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, 'echo json_encode( array_map( "sitetop_usd_cat_le", array(
    "a" => 100.12345678, "b" => 0.29, "c" => 1.005, "d" => 30, "e" => -2.345, "f" => 100.999 ) ) );' );
assert_equals( 100.12, $__us_k['a'] ?? null, 'Tran "Toan bo so du" 100,12345678 → 100,12 (phan le o lai vi). stderr: ' . $__us_e );
assert_equals( 0.29,   $__us_k['b'] ?? null, '0,29 phai ra 0,29 — floor(0.29*100) ra 28 la sai' );
assert_equals( 1,      $__us_k['c'] ?? null, '1,005 → 1,00 (cat, khong lam tron len 1,01)' );
assert_equals( 30,     $__us_k['d'] ?? null, 'So chan giu nguyen' );
assert_equals( -2.34,  $__us_k['e'] ?? null, 'So am cat ve 0: -2,345 → -2,34' );
assert_equals( 100.99, $__us_k['f'] ?? null, '100,999 → 100,99 (khong thanh 101)' );
assert_true( strpos( $__us_ud, '$wd_cap     = $usd_mode ? sitetop_usd_cat_le( $wd_cap, 2 )' ) !== false,
    'Tran o rut USD phai cat 2 so le — khong thi nut "Toan bo so du" dien 8 so le roi bi chinh luat 2 so le chan' );
assert_true( strpos( $__us_ud, "<b><?php echo \$usd_mode ? 'S&#7889; USDT mu&#7889;n r&#250;t' : 'S&#7889; ti&#7873;n mu&#7889;n r&#250;t'; ?></b>" ) !== false,
    'Nhan buoc 1: "So USDT muon rut" o che do USD, chu cu o VND' );

/* ═══ Q. THẺ THỐNG KÊ ADMIN TAB RÚT TIỀN — "thu nhỏ chữ lại" (chủ site 06/10 chiều) ═══ */
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, 'echo json_encode( array( "a" => sitetop_format_rut_cho_admin_html( 35 ), "b" => sitetop_format_rut_cho_admin_html( 260.23862701 ) ) );' );
assert_equals( '$35 <small>≈ 770.000đ</small>', $__us_k['a'] ?? '', 'USD dung truoc, VND quy doi trong <small> de CSS thu nho. stderr: ' . $__us_e );
assert_equals( '$260,23862701 <small>≈ 5.725.250đ</small>', $__us_k['b'] ?? '', 'So le dai van in du, VND lam tron dong nhu ban chu' );
list( $__us_k, $__us_e ) = $__us_chay( false, array(), 'echo json_encode( array( "a" => sitetop_format_rut_cho_admin_html( 770000 ) ) );' );
assert_equals( '770.000đ', $__us_k['a'] ?? '', 'VND thuan: khong co <small>, in nhu cu' );
$__us_tw = (string) file_get_contents( $__us_goc . '/includes/admin/tabs/tab-withdrawals.php' );
assert_equals( 3, substr_count( $__us_tw, 'sitetop_format_rut_cho_admin_html(' ), 'Ba the tien (so du, cho rut, da rut) dung ban HTML' );
assert_true( strpos( $__us_tw, '.wd-val{font-size:clamp(13px,1.35vw,17px)' ) !== false && strpos( $__us_tw, 'white-space:nowrap' ) !== false
          && strpos( $__us_tw, '.wd-val small{display:block' ) !== false && strpos( $__us_tw, '@media(max-width:900px){.wd-stats{grid-template-columns:repeat(2,1fr)}' ) !== false,
    'Chu thu nho (toi da 17px, co theo be rong), KHONG be so giua chung, hep thi xuong 2 cot; phan VND dong rieng nho hon' );

/* ═══ R. TỔNG QUAN ADMIN — cùng thiết kế thẻ (chủ site 06/10: "thiết kế phải bảng tổng quan luôn") ═══ */
$__us_ov = (string) file_get_contents( $__us_goc . '/includes/admin/tabs/tab-overview.php' );
assert_true( strpos( $__us_ov, 'sitetop_format_rut_cho_admin_html($total_user_earned)' ) !== false, 'The "User kiem duoc (all-time)" dung ban HTML' );
assert_true( strpos( $__us_ov, '.ov-val{font-size:clamp(13px,1.35vw,17px)' ) !== false && strpos( $__us_ov, '.ov-sv{font-size:clamp(13px,1.2vw,16px)' ) !== false
          && substr_count( $__us_ov, 'white-space:nowrap' ) >= 2 && strpos( $__us_ov, '@media(max-width:900px){.ov-stats,.ov-summary{grid-template-columns:repeat(2,1fr)}' ) !== false,
    'Chu co theo be rong, KHONG be so, hep thi 2 cot — ca hang all-time lan hang thang' );
assert_true( strpos( $__us_ov, "getElementById('smUearn').innerHTML = (s.user_earned_usd !== null && s.user_earned_usd !== undefined) ? stRutAdminHtml(s.user_earned_usd)" ) !== false
          && strpos( $__us_ov, "getElementById('smWithdrawals').innerHTML = (s.withdrawals_usd !== null && s.withdrawals_usd !== undefined) ? stRutAdminHtml(s.withdrawals_usd)" ) !== false,
    'Hai the thang (User kiem, Rut tien) in ban HTML co <small>' );
// Chạy THẬT bộ JS admin in ra từ sitetop_in_js_tien_user() + fmtMoney của tab trong node.
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, 'ob_start(); sitetop_in_js_tien_user(); echo json_encode( array( "js" => ob_get_clean() ) );' );
$__us_js_adm = preg_replace( '/^<script>|<\/script>$/', '', trim( (string) ( $__us_k['js'] ?? '' ) ) );
assert_true( strpos( $__us_js_adm, 'function stRutAdminHtml(' ) !== false, 'Bo JS admin phai co stRutAdminHtml. stderr: ' . $__us_e );
preg_match( '/function fmtMoney\(n\) \{[^\n]*\}/', $__us_ov, $__us_m );
assert_true( ! empty( $__us_m[0] ), 'Trich duoc fmtMoney cua tab tong quan' );
$__us_f = sys_get_temp_dir() . '/st-usd-ov-' . getmypid() . '.js';
file_put_contents( $__us_f, $__us_js_adm . "\n" . ( $__us_m[0] ?? '' ) . "\n" . 'console.log(JSON.stringify({a:stRutAdminHtml(185.0727273), b:stRutAdminHtml(0), c:fmtMoney(11037850.074), d:fmtMoney(15319100)}));' );
$__us_r = (string) shell_exec( 'node ' . escapeshellarg( $__us_f ) . ' 2>&1' ); @unlink( $__us_f );
$__us_k = json_decode( trim( $__us_r ), true );
assert_true( is_array( $__us_k ), 'Chay duoc JS admin bang node. Ra: ' . $__us_r );
assert_equals( '$185,0727273 <small>≈ 4.071.600đ</small>', $__us_k['a'] ?? '', 'stRutAdminHtml: USD + <small>≈ VND</small>. Ra: ' . json_encode( $__us_k, JSON_UNESCAPED_UNICODE ) );
assert_equals( '$0 <small>≈ 0đ</small>', $__us_k['b'] ?? '', 'So 0' );
assert_equals( '11.037.850đ', $__us_k['c'] ?? '', 'Loi nhuan nen tang: VND lam tron ve dong, khong in "11.037.850,074đ"' );
assert_equals( '15.319.100đ', $__us_k['d'] ?? '', 'So chan giu nguyen' );
// Chế độ VNĐ: stRutAdminHtml in VNĐ thuần.
list( $__us_k, $__us_e ) = $__us_chay( false, array(), 'ob_start(); sitetop_in_js_tien_user(); echo json_encode( array( "js" => ob_get_clean() ) );' );
$__us_js_adm = preg_replace( '/^<script>|<\/script>$/', '', trim( (string) ( $__us_k['js'] ?? '' ) ) );
file_put_contents( $__us_f, $__us_js_adm . "\n" . 'console.log(JSON.stringify({a:stRutAdminHtml(4071600)}));' );
$__us_r = (string) shell_exec( 'node ' . escapeshellarg( $__us_f ) . ' 2>&1' ); @unlink( $__us_f );
$__us_k = json_decode( trim( $__us_r ), true );
assert_equals( '4.071.600đ', $__us_k['a'] ?? '', 'VND thuan: khong <small>. Ra: ' . $__us_r );

/* ═══ S. CHUYỂN DỮ LIỆU CỠ LỚN (.net 06/10: visits 746k dòng / 888 MB) — bảng lô, khoá đặt sau việc nặng ═══
   CSDL giả: MAX(id) = 45000, cột đang decimal(12,2); hàm số dư trả 1.000đ trước và 1000/22000 sau khi
   START TRANSACTION (giả lập "cùng transaction nên thấy số đã chia"). */
$__us_khung_lon = '
    if ( ! defined( "OBJECT_K" ) ) define( "OBJECT_K", "OBJECT_K" );
    if ( ! function_exists( "maybe_unserialize" ) ) { function maybe_unserialize( $v ) { return $v; } }
    class US_WpdbLon extends US_Wpdb {
        public $usd_sau = 0.04545455;
        public function get_var( $q ) { return ( stripos( $q, "MAX(id)" ) !== false ) ? 45000 : 0; }
        public function get_col( $q ) { return array( 7 ); }
        public function get_results( $q, $o = null ) {
            if ( stripos( $q, "SHOW COLUMNS" ) !== false ) {
                $r = array();
                foreach ( array( "balance","total_earned","amount","balance_after","refund_amount","reward_amount","user_reward","total_earnings" ) as $c )
                    $r[ $c ] = (object) array( "Field" => $c, "Type" => "decimal(12,2)", "Null" => "NO", "Default" => "0.00" );
                return $r;
            }
            return array();
        }
        public function query( $q ) {
            if ( $q === "START TRANSACTION" ) $GLOBALS["SO_DU"] = $this->usd_sau;
            if ( $q === "ROLLBACK" ) $GLOBALS["SO_DU"] = 1000;
            return parent::query( $q );
        }
    }
    $GLOBALS["wpdb"] = new US_WpdbLon(); $GLOBALS["SO_DU"] = 1000;
    function sitetop_sync_user_balance_x() {}
';
$__us_in = function ( $sql ) { return implode( "\n", (array) $sql ); };
$__us_vt = function ( $sql, $mau ) { $i = 0; foreach ( (array) $sql as $s ) { if ( stripos( $s, $mau ) !== false ) return $i; $i++; } return -1; };

// (1) CHẠY THẬT với bảng lô: thứ tự + số lô + khoá
list( $__us_k, $__us_e ) = $__us_chay( false, array( 'sitetop_usd_rate' => 22000 ), $__us_khung_lon . '
    $r = sitetop_chuyen_sang_usd( true, array( "bang_lo" => array( "shortlink_visits", "user_shortlinks" ), "co_lo" => 20000, "khoa_giay" => 900 ) );
    echo json_encode( array( "kq" => $r, "sql" => $GLOBALS["wpdb"]->sql, "khoa" => get_option( "sitetop_tam_khoa_tien" ), "che_do" => get_option( "sitetop_che_do_usd" ) ) );' );
$__us_sql = (array) ( $__us_k['sql'] ?? array() );
assert_true( ( $__us_k['kq']['ket_qua'] ?? '' ) === 'ĐÃ CHUYỂN SANG USD', 'Chay that voi bang lo phai CHUYEN duoc. kq: ' . json_encode( $__us_k['kq'] ?? null, JSON_UNESCAPED_UNICODE ) . ' stderr: ' . $__us_e );
$__us_i_alter = $__us_vt( $__us_sql, 'ALTER TABLE wpgd_sitetop_shortlink_visits MODIFY COLUMN reward_amount DECIMAL(20,8)' );
$__us_i_bak   = $__us_vt( $__us_sql, 'CREATE TABLE wpgd_sitetop_shortlink_visits_bak_vnd LIKE' );
$__us_i_lo1   = $__us_vt( $__us_sql, 'UPDATE wpgd_sitetop_shortlink_visits SET reward_amount = ROUND(reward_amount / 22000, 8) WHERE id BETWEEN 1 AND 20000' );
$__us_i_lo3   = $__us_vt( $__us_sql, 'WHERE id BETWEEN 40001 AND 45000' );
$__us_i_tx    = $__us_vt( $__us_sql, 'START TRANSACTION' );
$__us_i_rc    = $__us_vt( $__us_sql, 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED' );
assert_true( $__us_i_alter >= 0 && $__us_i_bak > $__us_i_alter && $__us_i_lo1 > $__us_i_bak && $__us_i_lo3 > $__us_i_lo1 && $__us_i_tx > $__us_i_lo3,
    'THU TU: noi cot → sao luu → chia lo (1-20000 … 40001-45000) → START TRANSACTION. SQL: ' . substr( $__us_in( $__us_sql ), 0, 1500 ) );
assert_true( $__us_i_rc >= 0 && $__us_i_rc < $__us_i_bak, 'Sao luu doc READ COMMITTED de khong khoa dong bang goc' );
assert_equals( 3, substr_count( $__us_in( $__us_sql ), 'UPDATE wpgd_sitetop_shortlink_visits SET' ), 'visits 45000 dong / lo 20000 = DUNG 3 lo, khong co UPDATE toan bang' );
assert_equals( 3, substr_count( $__us_in( $__us_sql ), 'UPDATE wpgd_sitetop_user_shortlinks SET' ), 'user_shortlinks cung 3 lo' );
assert_true( strpos( $__us_in( $__us_sql ), 'UPDATE wpgd_sitetop_shortlink_visits SET reward_amount = ROUND(reward_amount / 22000, 8)' . "\n" ) === false
          && strpos( $__us_in( array_slice( $__us_sql, $__us_i_tx ) ), 'wpgd_sitetop_shortlink_visits' ) === false,
    'Trong transaction lon KHONG duoc dung bang lo' );
assert_true( strpos( $__us_in( array_slice( $__us_sql, $__us_i_tx ) ), 'UPDATE wpgd_sitetop_transactions SET amount = ROUND(amount / 22000, 8)' ) !== false, 'transactions (nguon so du) chia TRONG transaction' );
assert_true( (int) ( $__us_k['khoa'] ?? 0 ) === 0 && (int) ( $__us_k['che_do'] ?? 0 ) === 1, 'Xong: mo khoa, bat cong tac' );
assert_equals( 45000, $__us_k['kq']['bang_lo_dong']['shortlink_visits'] ?? 0, 'Bao so dong bang lo' );

// (2) Khoá phải đặt SAU nới cột/sao lưu và TRƯỚC chia lô — kiểm bằng vị trí ghi option trong dãy SQL không được,
//     nên soi mã: update_option tam_khoa_tien nằm sau sitetop_mo_rong_cot_tien_user() và sau INSERT INTO bak.
$__us_tu = (string) file_get_contents( $__us_goc . '/includes/tien-usd.php' );
$__us_than = substr( $__us_tu, strpos( $__us_tu, 'function sitetop_chuyen_sang_usd(' ) );
$__us_p_noi  = strpos( $__us_than, "\$bc['noi_cot'] = sitetop_mo_rong_cot_tien_user();" );
$__us_p_bak  = strpos( $__us_than, 'INSERT INTO {$bak} SELECT * FROM' );
$__us_p_khoa = strpos( $__us_than, "update_option( 'sitetop_tam_khoa_tien', time() + (int) \$tuy['khoa_giay'], false );" );
$__us_p_lo   = strpos( $__us_than, 'WHERE id BETWEEN {$tu} AND {$den}' );
assert_true( $__us_p_noi !== false && $__us_p_bak > $__us_p_noi && $__us_p_khoa > $__us_p_bak && $__us_p_lo > $__us_p_khoa,
    'Khoa tien dat SAU viec nang (noi cot, sao luu) va TRUOC chia lo — khoa ngan nhat co the' );

// (3) CHẠY THỬ: không đụng bảng lô, vẫn đối soát các bảng còn lại, ROLLBACK
list( $__us_k, $__us_e ) = $__us_chay( false, array( 'sitetop_usd_rate' => 22000 ), $__us_khung_lon . '
    $r = sitetop_chuyen_sang_usd( false, array( "bang_lo" => array( "shortlink_visits", "user_shortlinks" ) ) );
    echo json_encode( array( "kq" => $r, "sql" => $GLOBALS["wpdb"]->sql ) );' );
$__us_sql = $__us_in( $__us_k['sql'] ?? array() );
assert_true( strpos( (string) ( $__us_k['kq']['ket_qua'] ?? '' ), 'chạy thử, mọi user khớp' ) !== false, 'Chay thu phai khop. kq: ' . json_encode( $__us_k['kq'] ?? null, JSON_UNESCAPED_UNICODE ) . ' stderr: ' . $__us_e );
assert_true( strpos( $__us_sql, 'UPDATE wpgd_sitetop_shortlink_visits' ) === false && strpos( $__us_sql, 'UPDATE wpgd_sitetop_user_shortlinks' ) === false,
    'Chay thu KHONG dung bang lo (746k dong — qua nang va khong anh huong doi soat)' );
assert_true( strpos( $__us_sql, '_bak_vnd' ) === false && strpos( $__us_sql, 'ROLLBACK' ) !== false, 'Chay thu khong sao luu, co ROLLBACK' );
assert_true( strpos( $__us_sql, 'ALTER TABLE wpgd_sitetop_transactions MODIFY COLUMN amount DECIMAL(20,8)' ) !== false, 'Chay thu van noi cot (de so chia 8 so le khop)' );

// (4) LỆCH khi chạy thật: ROLLBACK + nhân ngược đúng khoảng id của bảng lô + mở khoá + KHÔNG bật công tắc
list( $__us_k, $__us_e ) = $__us_chay( false, array( 'sitetop_usd_rate' => 22000 ), $__us_khung_lon . '
    $GLOBALS["wpdb"]->usd_sau = 0.05;   // sai: 0,05 × 22000 = 1.100 ≠ 1.000
    $r = sitetop_chuyen_sang_usd( true, array( "bang_lo" => array( "shortlink_visits" ), "co_lo" => 20000 ) );
    echo json_encode( array( "kq" => $r, "sql" => $GLOBALS["wpdb"]->sql, "khoa" => get_option( "sitetop_tam_khoa_tien" ), "che_do" => get_option( "sitetop_che_do_usd", 0 ) ) );' );
$__us_sql = $__us_in( $__us_k['sql'] ?? array() );
assert_true( strpos( (string) ( $__us_k['kq']['ket_qua'] ?? '' ), 'có user lệch' ) !== false, 'Lech thi KHONG chuyen. kq: ' . json_encode( $__us_k['kq'] ?? null, JSON_UNESCAPED_UNICODE ) . ' stderr: ' . $__us_e );
assert_equals( 3, substr_count( $__us_sql, 'UPDATE wpgd_sitetop_shortlink_visits SET reward_amount = ROUND(reward_amount * 22000, 2) WHERE id BETWEEN' ), 'Bang lo da chia phai duoc NHAN NGUOC dung 3 lo' );
assert_true( strpos( $__us_sql, 'ROLLBACK' ) !== false && (int) ( $__us_k['che_do'] ?? 0 ) === 0 && (int) ( $__us_k['khoa'] ?? 0 ) === 0, 'ROLLBACK, cong tac van TAT, mo khoa' );

/* ═══ T. RÀ TOÀN BỘ TÍNH TIỀN USER (chủ site 06/10 tối: "kiểm tra lại … có nhầm chỗ nào không") — 5 chỗ bắt được ═══ */
$__us_sf  = (string) file_get_contents( $__us_goc . '/includes/shortlink-functions.php' );
$__us_upd = $__us_ham( $__us_sf, 'sitetop_update_campaign' );
$__us_cre = $__us_ham( $__us_sf, 'sitetop_create_keyword_campaign' );
assert_true( $__us_upd !== '' && $__us_cre !== '', 'Trich duoc sitetop_update_campaign / sitetop_create_keyword_campaign' );
$__us_wpdb_camp = '
    class US_WpdbCamp extends US_Wpdb { public $capnhat = array();
        public function update( $t, $d, $w, $f = null, $wf = null ) { $this->capnhat[] = array( "bang" => $t, "du_lieu" => $d, "format" => $f ); return 1; } }
    $GLOBALS["wpdb"] = new US_WpdbCamp();
    if ( ! function_exists( "sanitize_textarea_field" ) ) { function sanitize_textarea_field( $s ) { return (string) $s; } }
    if ( ! function_exists( "esc_url_raw" ) ) { function esc_url_raw( $u ) { return (string) $u; } }
    if ( ! function_exists( "wp_mail" ) ) { function wp_mail() { return true; } }
    if ( ! function_exists( "sitetop_send_new_campaign_email" ) ) { function sitetop_send_new_campaign_email( $id ) {} }
';
// (2) Sửa camp ở USD: user_reward đi qua %s dạng chuỗi 8 số lẻ — KHÔNG qua %f (thành %F 6 số lẻ).
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, $__us_upd . $__us_wpdb_camp . '
    sitetop_update_campaign( 5, array( "user_reward" => 0.02272727, "quantity" => 100 ) );
    echo json_encode( array( "cap" => $GLOBALS["wpdb"]->capnhat ) );' );
$__us_c  = $__us_k['cap'][0] ?? array();
$__us_vt = array_search( 'user_reward', array_keys( (array) ( $__us_c['du_lieu'] ?? array() ) ), true );
assert_true( ( $__us_c['du_lieu']['user_reward'] ?? null ) === '0.02272727', 'Sua camp (USD): user_reward ghi dang CHUOI du 8 so le "0.02272727". Ra: ' . json_encode( $__us_k ) . ' stderr: ' . $__us_e );
assert_equals( '%s', ( $__us_c['format'] ?? array() )[ $__us_vt ] ?? null, 'Sua camp (USD): format user_reward la %s — %f se thanh %F 6 so le, cat mat 2 so' );
list( $__us_k, $__us_e ) = $__us_chay( false, array(), $__us_upd . $__us_wpdb_camp . '
    sitetop_update_campaign( 5, array( "user_reward" => 500 ) );
    echo json_encode( array( "cap" => $GLOBALS["wpdb"]->capnhat ) );' );
assert_equals( '%f', ( $__us_k['cap'][0]['format'] ?? array() )[0] ?? null, 'Sua camp (VND): giu %f nhu cu. stderr: ' . $__us_e );
// (3) Tạo camp KHÔNG truyền user_reward ở USD → công thức máy chủ ($35/1000 = 0,035), không phải "80% giá khách" (960).
list( $__us_k, $__us_e ) = $__us_chay( true, $__us_rate35, $__us_cre . $__us_wpdb_camp . '
    $r = sitetop_create_keyword_campaign( array( "title" => "t", "keyword" => "abc", "target_url" => "https://a.vn", "task_type" => "keyword_search", "traffic_type" => "1step", "quantity" => 10, "user_reward" => 500 ) );
    $camp = null; foreach ( $GLOBALS["wpdb"]->them as $t ) { if ( strpos( $t["bang"], "keyword_campaigns" ) !== false ) $camp = $t["du_lieu"]; }
    echo json_encode( array( "r" => is_wp_error( $r ) ? $r->tin : $r, "camp" => $camp ) );' );
assert_true( abs( (float) ( $__us_k['camp']['user_reward'] ?? -1 ) - 0.035 ) < 1e-9, 'Tao camp qua cong AJAX (POST user_reward=500 VND cu) o USD → may chu TU TINH 0,035, khong tin 500 ($500/view). Ra: ' . json_encode( $__us_k ) . ' stderr: ' . $__us_e );
// (1) Phía khách: bảng lượt xem in thưởng user theo đơn vị user.
$__us_cc = (string) file_get_contents( $__us_goc . '/includes/customer-campaign-ajax.php' );
assert_true( strpos( $__us_cc, "+' . sitetop_format_tien_user(\$v->reward_amount) . '" ) !== false && strpos( $__us_cc, 'sitetop_format_money($v->reward_amount)' ) === false,
    'Bang luot xem phia khach: thuong user in theo don vi user ($), khong con "+0đ"' );
// (4) Form tạo camp admin: thưởng mặc định + cảnh báo "giá < thưởng" theo đúng đơn vị.
$__us_tc = (string) file_get_contents( $__us_goc . '/includes/admin/tabs/tab-campaigns.php' );
assert_true( strpos( $__us_tc, "id=\"adm_reward\" value=\"<?php echo esc_attr( sitetop_user_reward_cho_camp( 'keyword_search', '1step', 70 ) ); ?>\"" ) !== false,
    'adm_reward mac dinh tu cong thuc may chu, khong phai option VND 800' );
assert_true( strpos( $__us_tc, "reward=reward*ST_TYGIA" ) !== false && strpos( $__us_tc, "ADM_USER_REWARD_TINH[t]" ) !== false,
    'Canh bao gia < thuong: rate lay tu bang may chu, USD quy ve VND bang ty gia roi moi so voi gia khach' );
// (5) Biểu đồ dashboard user: nhãn / trục / tick theo đơn vị.
$__us_ud = (string) file_get_contents( $__us_goc . '/page-user-dashboard.php' );
assert_true( strpos( $__us_ud, "label: 'Kiếm được (' + ((typeof ST_USD!=='undefined'&&ST_USD) ? '$' : 'đ') + ')'" ) !== false
          && strpos( $__us_ud, "text: (typeof ST_USD!=='undefined'&&ST_USD) ? 'USD' : 'VNĐ'" ) !== false
          && strpos( $__us_ud, "return (typeof ST_USD!=='undefined'&&ST_USD) ? stUsd(v) : fmt(v);" ) !== false,
    'Bieu do dashboard: nhan "Kiem duoc ($)", truc USD, tick USD dung stUsd' );
