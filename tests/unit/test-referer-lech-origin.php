<?php
/* REFERER LỆCH ORIGIN — lớp chặn bot gọi thẳng API (24/09/2026).

   Ca thật: camp 424 "zowin zowin.lifestyle" CHƯA gắn top.js trên web khách, vậy mà tài khoản
   trongvipporo222 lấy mã 8 lần lúc 08h. Log máy chủ: IP đó gửi 96 request, 0 lần tải /top.js,
   chỉ 1 file tĩnh — không có trình duyệt nào mở trang đích. Nó gọi thẳng admin-ajax và tự đặt
   header: sfs=cross-site, Origin=zowin.lifestyle, NHƯNG Referer=google.com.

   Widget thật nằm TRÊN trang đích nên trình duyệt đặt Referer = chính trang đích:
   host(Referer) == host(Origin). Đo 24 giờ ở cổng xacminh: 26.010 lượt khớp (54 tài khoản)
   / 414 lượt lệch, chỉ thuộc ĐÚNG 2 tài khoản.

   Canh: (A) luật phân loại, (B) xử lý theo mức, (C) khâu tiền, (D) cắm đúng cổng — chạy HÀM
   THẬT trong tiến trình PHP con. */

$__rl_goc = dirname( __DIR__, 2 );
$__rl_ham = function ( $ma, $ten ) {
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
$__rl_con = function ( $ma ) {
    $p = proc_open( array( PHP_BINARY, '-d', 'display_errors=stderr', '-r', $ma ),
        array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $ong );
    $out = stream_get_contents( $ong[1] ); $err = stream_get_contents( $ong[2] );
    fclose( $ong[1] ); fclose( $ong[2] ); proc_close( $p );
    $j = json_decode( $out, true );
    return array( is_array( $j ) ? $j : array(), $err ?: ( is_array( $j ) ? '' : $out ) );
};

$__rl_ajax = (string) file_get_contents( $__rl_goc . '/includes/shortlink-ajax.php' );
$__rl_host_src = $__rl_ham( $__rl_ajax, 'sitetop_host_header' );
$__rl_loai_src = $__rl_ham( $__rl_ajax, 'sitetop_ref_lech_loai' );
$__rl_xuly_src = $__rl_ham( $__rl_ajax, 'sitetop_ref_lech_xu_ly' );
assert_true( $__rl_host_src !== '' && $__rl_loai_src !== '' && $__rl_xuly_src !== '',
    'Phai trich duoc sitetop_host_header / sitetop_ref_lech_loai / sitetop_ref_lech_xu_ly' );

/* ================= A. LUẬT PHÂN LOẠI ================= */
$__rl_xet = function ( $origin, $referer ) use ( $__rl_con, $__rl_host_src, $__rl_loai_src ) {
    $ma = 'error_reporting( E_ALL );' . "\n" . $__rl_host_src . "\n" . $__rl_loai_src . "\n"
        . ( $origin === null ? '' : '$_SERVER["HTTP_ORIGIN"] = ' . var_export( $origin, true ) . ";\n" )
        . ( $referer === null ? '' : '$_SERVER["HTTP_REFERER"] = ' . var_export( $referer, true ) . ";\n" )
        . 'echo json_encode( array( "loai" => sitetop_ref_lech_loai() ) );';
    return $__rl_con( $ma );
};
$__rl_ca = array(
    // [origin, referer, kết quả mong đợi, vì sao]
    array( 'https://zowin.lifestyle', 'https://www.google.com/', 'lech', 'CA THAT: bot khai Origin web khach, Referer google' ),
    array( 'https://zowin.lifestyle', 'https://zowin.lifestyle/vi-vn/', '', 'WIDGET THAT: referer la chinh trang dich -> khong dung toi' ),
    array( 'https://zowin.lifestyle', null, '', 'THIEU referer (trinh duyet cat) -> KHONG ket luan' ),
    array( null, 'https://zowin.lifestyle/', '', 'THIEU origin -> KHONG ket luan' ),
    array( 'https://www.abc.com', 'https://abc.com/trang?x=1', '', 'www va khong www la MOT trang -> khong lech' ),
    array( 'HTTPS://ABC.COM', 'https://abc.com/', '', 'Chu hoa chu thuong -> khong lech' ),
    array( 'https://abc.com', 'https://sub.abc.com/', 'lech', 'Ten mien phu KHAC host -> lech' ),
    array( '', '', '', 'Ca hai rong -> khong ket luan' ),
);
foreach ( $__rl_ca as $__rl_i => $__rl_c ) {
    list( $__rl_o, $__rl_r, $__rl_mong, $__rl_vs ) = $__rl_c;
    list( $__rl_k, $__rl_e ) = $__rl_xet( $__rl_o, $__rl_r );
    assert_equals( $__rl_mong, $__rl_k['loai'] ?? '(loi)', 'Luat phan loai ca ' . ( $__rl_i + 1 ) . ': ' . $__rl_vs . ' | stderr: ' . $__rl_e );
}

/* ================= B. XỬ LÝ THEO MỨC ================= */
$__rl_chay = function ( $origin, $referer, $muc ) use ( $__rl_con, $__rl_host_src, $__rl_loai_src, $__rl_xuly_src ) {
    $ma = 'error_reporting( E_ALL );' . "\n" . <<<'PHP'
$GLOBALS['TR'] = array(); $GLOBALS['VET'] = array(); $GLOBALS['BAO'] = 0;
function sitetop_get_option( $k, $d = null ) { return $k === 'ref_lech_muc' ? $GLOBALS['MUC'] : $d; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['TR'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['TR'][ $k ] ?? false; }
function sitetop_ghi_vet( $sid, $cong, $them = '' ) { $GLOBALS['VET'][] = $cong . ' ' . $them; }
function sitetop_canh_bao_ref_lech( $sid, $cong, $o, $r ) { $GLOBALS['BAO']++; }
define( 'HOUR_IN_SECONDS', 3600 );
PHP
        . "\n" . '$GLOBALS["MUC"] = ' . (int) $muc . ";\n"
        . ( $origin === null ? '' : '$_SERVER["HTTP_ORIGIN"] = ' . var_export( $origin, true ) . ";\n" )
        . ( $referer === null ? '' : '$_SERVER["HTTP_REFERER"] = ' . var_export( $referer, true ) . ";\n" )
        . $__rl_host_src . "\n" . $__rl_loai_src . "\n" . $__rl_xuly_src . "\n"
        . '$kq = sitetop_ref_lech_xu_ly( "SID1", "xacminh" );' . "\n"
        . 'echo json_encode( array( "tra" => $kq, "co_dau_phien" => isset( $GLOBALS["TR"]["sitetop_reflech_SID1"] ), "vet" => $GLOBALS["VET"], "bao" => $GLOBALS["BAO"] ) );';
    return $__rl_con( $ma );
};
$__rl_bot = array( 'https://zowin.lifestyle', 'https://www.google.com/' );
$__rl_that = array( 'https://zowin.lifestyle', 'https://zowin.lifestyle/vi-vn/' );

// B1. Mức 2 + lệch -> CHẶN, có dấu phiên (để khâu tiền cắt), có dấu vết, có cảnh báo.
list( $__rl_k, $__rl_e ) = $__rl_chay( $__rl_bot[0], $__rl_bot[1], 2 );
assert_equals( 'chan', $__rl_k['tra'] ?? null, 'Muc 2 + lech phai tra "chan". stderr: ' . $__rl_e );
assert_true( ! empty( $__rl_k['co_dau_phien'] ), 'Muc 2 phai gan dau phien de khau tra thuong cat tien' );
assert_true( strpos( implode( '|', (array) ( $__rl_k['vet'] ?? array() ) ), 'o=zowin.lifestyle r=google.com' ) !== false,
    'Dau vet phai ghi ro o= va r= de admin soi lai' );
assert_equals( 1, $__rl_k['bao'] ?? 0, 'Phai bao Telegram' );

// B2. Mức 1 = chỉ ghi nhận: KHÔNG chặn, nhưng vẫn gắn dấu + cảnh báo (để soi trước khi siết).
list( $__rl_k, $__rl_e ) = $__rl_chay( $__rl_bot[0], $__rl_bot[1], 1 );
assert_equals( '', $__rl_k['tra'] ?? null, 'Muc 1 KHONG duoc chan. stderr: ' . $__rl_e );
assert_true( ! empty( $__rl_k['co_dau_phien'] ), 'Muc 1 van gan dau phien (khau tien tu xet muc cua no)' );

// B3. Mức 0 = tắt hẳn: không chặn, không dấu, không cảnh báo.
list( $__rl_k, $__rl_e ) = $__rl_chay( $__rl_bot[0], $__rl_bot[1], 0 );
assert_equals( '||0', ( $__rl_k['tra'] ?? '?' ) . '|' . ( empty( $__rl_k['co_dau_phien'] ) ? '' : 'co' ) . '|' . ( $__rl_k['bao'] ?? '?' ),
    'Muc 0 phai im lang hoan toan. stderr: ' . $__rl_e );

// B4. WIDGET THẬT ở mức 2: không chặn, KHÔNG gắn dấu, KHÔNG báo — đây là chốt chống oan.
list( $__rl_k, $__rl_e ) = $__rl_chay( $__rl_that[0], $__rl_that[1], 2 );
assert_equals( '||0', ( $__rl_k['tra'] ?? '?' ) . '|' . ( empty( $__rl_k['co_dau_phien'] ) ? '' : 'co' ) . '|' . ( $__rl_k['bao'] ?? '?' ),
    'Widget that (Referer = Origin) tuyet doi khong duoc dung toi. stderr: ' . $__rl_e );

// B5. Thiếu referer ở mức 2: cũng không được đụng (trình duyệt cắt header).
list( $__rl_k, $__rl_e ) = $__rl_chay( 'https://zowin.lifestyle', null, 2 );
assert_equals( '||0', ( $__rl_k['tra'] ?? '?' ) . '|' . ( empty( $__rl_k['co_dau_phien'] ) ? '' : 'co' ) . '|' . ( $__rl_k['bao'] ?? '?' ),
    'Thieu referer -> khong ket luan, khong chan. stderr: ' . $__rl_e );

/* ================= C. KHÂU TIỀN ================= */
$__rl_ver = $__rl_ham( (string) file_get_contents( $__rl_goc . '/includes/shortlink-verification.php' ), 'sitetop_verify_and_pay' );
assert_true( $__rl_ver !== '', 'Phai trich duoc sitetop_verify_and_pay' );
$__rl_tien = function ( $muc ) use ( $__rl_con, $__rl_ver ) {
    $ma = <<<'PHP'
error_reporting( E_ALL );
class WP_Error { public $code; public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; } }
function sitetop_get_real_ip() { return '14.0.0.9'; }
function sitetop_is_ip_blocked( $ip ) { return false; }
function get_user_meta( $u, $k, $s = false ) { return ''; }
function sitetop_bridge_rescue_code( $v, $s, $c ) {}
function sitetop_current_time() { return '2026-09-24 08:11:40'; }
function sitetop_get_visit_expiry_seconds() { return 600; }
function get_transient( $k ) { return $GLOBALS['TR'][ $k ] ?? false; }
function delete_transient( $k ) { unset( $GLOBALS['TR'][ $k ] ); return true; }
function sitetop_get_option( $k, $d = null ) { return array_key_exists( $k, $GLOBALS['OPT'] ) ? $GLOBALS['OPT'][ $k ] : $d; }
function get_option( $k, $d = false ) { return $k === 'sitetop_migration_skip_reasons_v2' ? 1 : $d; }
function sitetop_ip_view_quota( $ip, $sl ) { return array( 'same_link' => false, 'used' => 0, 'limit' => 2, 'allowed' => true ); }
function sitetop_get_customer_balance_amount( $c ) { return 1000000; }
function sitetop_sync_customer_balance( $c ) {}
function sitetop_auto_pause_customer_campaigns( $c ) {}
function sitetop_get_reward_amount( $o ) { return 500; }
function sitetop_add_user_balance( $u, $a, ...$r ) { $GLOBALS['GHI'][] = 'TRA_USER ' . $a; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function absint( $v ) { return abs( (int) $v ); }
class RL_Wpdb {
    public $prefix = 'wpgd_'; public $visit; public $locked; public $cap_nhat = null;
    public function prepare( $q, ...$a ) { if ( count( $a ) === 1 && is_array( $a[0] ) ) $a = $a[0]; $i = 0;
        return preg_replace_callback( '/%[sdf]/', function ( $m ) use ( &$i, $a ) { $v = $a[ $i++ ] ?? null;
            return $m[0] === '%s' ? "'" . addslashes( (string) $v ) . "'" : (string) (int) $v; }, $q ); }
    public function get_row( $q ) {
        if ( strpos( $q, 'FOR UPDATE' ) !== false && strpos( $q, 'shortlink_visits' ) !== false ) return $this->locked;
        if ( strpos( $q, 'customer_balance' ) !== false ) return (object) array( 'user_id' => 503, 'balance' => 1000000 );
        return $this->visit; }
    public function get_var( $q ) { return 0; }
    public function query( $q ) { $q = preg_replace( '/\s+/', ' ', trim( $q ) );
        if ( stripos( $q, 'customer_balance SET balance = balance -' ) !== false ) $GLOBALS['GHI'][] = 'TRU_KHACH';
        elseif ( stripos( $q, 'keyword_campaigns SET completed = completed + 1' ) !== false ) $GLOBALS['GHI'][] = 'CONG_VIEW';
        return 1; }
    public function insert( $t, $d ) { return 1; }
    public function update( $t, $d, $w = array() ) { if ( strpos( $t, 'shortlink_visits' ) !== false ) $this->cap_nhat = $d; return 1; }
}
PHP
        . "\n" . $__rl_ver . "\n" . <<<'PHP'
$GLOBALS['GHI'] = array();
$GLOBALS['OPT'] = array( 'ref_lech_muc' => MUC_TEST, 'nguon_gia_muc' => 2, 'widget_captcha_enabled' => 0 );
$GLOBALS['TR']  = array( 'sitetop_widget_code_ready_S1' => 1, 'sitetop_verify_code_S1' => '4B5C6D4F', 'sitetop_reflech_S1' => 1 );
$v = array( 'id' => 656808, 'session_id' => 'S1', 'reward_paid' => 0, 'customer_paid' => 0, 'user_id' => 1045,
    'created_at' => '2026-09-24 08:10:13', 'price_per_view' => 1300, 'camp_user_reward' => 500, 'camp_onsite' => 70,
    'onsite_time' => 70, 'traffic_type' => '1step', 'customer_id' => 503, 'daily_traffic' => 0, 'camp_status' => 'active',
    'fixed_code' => '', 'camp_id' => 424, 'camp_order_id' => 0, 'campaign_type' => 'traffic_direct', 'camp_title' => 'zowin',
    'original_url' => 'https://zowin.lifestyle/', 'sl_id' => 1, 'shortlink_id' => 1, 'campaign_id' => 424,
    'verify_code' => '4B5C6D4F', 'from_google' => 1, 'url_matched' => 1, 'ip_changed' => 0, 'original_ip' => '14.0.0.9',
    'ip_address' => '14.0.0.9', 'adblock_detected' => 0, 'verified_at' => null, 'step' => 'code_shown' );
$GLOBALS['wpdb'] = new RL_Wpdb();
$GLOBALS['wpdb']->visit = (object) $v; $GLOBALS['wpdb']->locked = (object) $v;
$kq = sitetop_verify_and_pay( 'S1', '4B5C6D4F', false );
echo json_encode( array( 'cap_nhat' => $GLOBALS['wpdb']->cap_nhat, 'ghi' => $GLOBALS['GHI'] ) );
PHP;
    return $__rl_con( str_replace( 'MUC_TEST', (int) $muc, $ma ) );
};

// C1. Mức 2: lượt chốt 'rejected', không trả user, không trừ khách, không cộng view.
list( $__rl_k, $__rl_e ) = $__rl_tien( 2 );
assert_equals( 'rejected', $__rl_k['cap_nhat']['step'] ?? null,
    'Referer lech muc 2: luot phai chot o rejected (khong tinh view). stderr: ' . $__rl_e );
assert_equals( '', implode( ',', (array) ( $__rl_k['ghi'] ?? array( 'x' ) ) ),
    'Referer lech muc 2: KHONG tru khach, KHONG tra user, KHONG cong view' );
assert_true( strpos( (string) ( $__rl_k['cap_nhat']['skip_reasons'] ?? '' ), 'ref_lech' ) !== false,
    'Phai ghi ly do ref_lech cho admin loc' );

// C2. Mức 1: chỉ gắn nhãn — tiền vẫn chạy như cũ (đường lùi khi nghi bắt oan).
list( $__rl_k, $__rl_e ) = $__rl_tien( 1 );
assert_equals( 'verified', $__rl_k['cap_nhat']['step'] ?? null, 'Muc 1 chi gan nhan: van verified. stderr: ' . $__rl_e );
assert_true( in_array( 'TRU_KHACH', (array) ( $__rl_k['ghi'] ?? array() ), true ), 'Muc 1: tien van chay nhu cu' );

/* ================= D. CẮM ĐÚNG CHỖ ================= */
$__rl_sach = '';
foreach ( token_get_all( $__rl_ajax ) as $__rl_t ) {
    if ( is_array( $__rl_t ) && in_array( $__rl_t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) continue;
    $__rl_sach .= is_array( $__rl_t ) ? $__rl_t[1] : $__rl_t;
}
foreach ( array( 'xacminh', 'batgio', 'xinma' ) as $__rl_cong ) {
    assert_true( strpos( $__rl_sach, "sitetop_ref_lech_xu_ly( \$sid, '" . $__rl_cong . "' )" ) !== false
              || strpos( $__rl_sach, "sitetop_ref_lech_xu_ly( \$visit->session_id, '" . $__rl_cong . "' )" ) !== false,
        'Phai cam lop nay o cong widget-only: ' . $__rl_cong );
}
/* Cổng thăm dò chạy 3–10 giây/lần: KHÔNG được cắm (bài học 22/09 — máy đo gắn vào đó làm
   nghẽn máy chủ), và chúng cũng do trang nhiệm vụ gọi same-origin nên sẽ chặn oan. */
foreach ( array( 'hoima', 'nhiptrang', 'nhip' ) as $__rl_tham ) {
    assert_true( strpos( $__rl_sach, "sitetop_ref_lech_xu_ly( \$sid, '" . $__rl_tham . "' )" ) === false,
        'TUYET DOI khong cam vao cong tham do: ' . $__rl_tham );
}
// Công tắc phải lưu được và có trong giao diện, không thì bấm Lưu không ăn.
$__rl_set = (string) file_get_contents( $__rl_goc . '/includes/admin/tabs/tab-settings.php' );
assert_true( preg_match( "/\\\$fields = array\\(.*?'ref_lech_muc'.*?\\);/s", $__rl_set ) === 1,
    'ref_lech_muc phai nam trong danh sach $fields duoc luu' );
assert_true( strpos( $__rl_set, 'name="ref_lech_muc"' ) !== false, 'Phai co o chon trong Cai dat' );
// Admin phải đọc được: nhãn + bộ lọc riêng.
$__rl_tab = (string) file_get_contents( $__rl_goc . '/includes/admin/tabs/tab-visits.php' );
assert_true( strpos( $__rl_tab, "'ref_lech'                 =>" ) !== false, 'Tab Luot truy cap phai co nhan "Referer lech"' );
assert_true( preg_match( "#reason_filter === 'ref_lech'.{0,160}dau_vet LIKE#s", $__rl_tab ) === 1,
    'Phai co bo loc rieng cho ref_lech' );
