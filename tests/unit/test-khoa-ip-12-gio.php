<?php
/* KHOÁ IP TỰ ĐỘNG 12 GIỜ — chủ site rút từ 24 xuống 12 ngày 19/09/2026, cho cả khoá gian
   lận hành vi lẫn khoá VPN / proxy / 1.1.1.1.

   Ba điều canh:
   1. LỜI HỨA KHỚP VỚI KHOÁ: trang "IP của bạn đang bị tạm khoá" in "tự hết sau N giờ",
      khoá thật (behavior-analytics.php) phải đúng N giờ. Bản cũ ghi cứng "24" ở hai nơi
      riêng rẽ — đổi một nơi quên nơi kia là trang nói sai với user.
   2. Khoá VPN/proxy (ip-fraud.php) cùng N giờ.
   3. Kết quả ip-api BỊ GẮN CỜ chỉ được nhớ N giờ — xem mục 5.

   Chạy CHÍNH hàm thật trong tiến trình PHP CON: sitetop_show_block_page() kết thúc bằng
   exit nên không gọi thẳng trong bộ chạy được; tiến trình con còn cho phép định nghĩa
   hằng số với giá trị khác (phép thử lệch) mà không làm bẩn các test khác.
   $wpdb giả thay placeholder y như WordPress, nên đọc được đúng câu SQL sẽ chạy. */

$__k12_goc = dirname( __DIR__, 2 );

/* Trích NGUYÊN VĂN một hàm (giữ cả ?> ... <?php bên trong thân — trang chặn là HTML
   xen PHP). Chỉ bỏ đúng thẻ <?php ghép vào đầu để token hoá. */
$__k12_ham = function ( $ma, $ten ) {
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

$__k12_con = function ( $ma ) {
    $p = proc_open( array( PHP_BINARY, '-d', 'display_errors=stderr', '-r', $ma ),
        array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $ong );
    if ( ! is_resource( $p ) ) return array( '', 'khong mo duoc tien trinh con', -1 );
    $out = stream_get_contents( $ong[1] ); $err = stream_get_contents( $ong[2] );
    fclose( $ong[1] ); fclose( $ong[2] );
    return array( $out, $err, proc_close( $p ) );
};

$__k12_nen = <<<'PHP'
error_reporting( E_ALL );
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function wp_kses( $s, $a = array() ) { return (string) $s; }
function sitetop_current_time() { return '2026-09-19 10:00:00'; }
function sitetop_get_real_ip() { return '203.0.113.9'; }
function sitetop_get_option( $k, $d = null ) { return array_key_exists( $k, $GLOBALS['OPT'] ) ? $GLOBALS['OPT'][ $k ] : $d; }
$GLOBALS['OPT'] = array();
class K12_Wpdb {
    public $prefix = 'wpgd_'; public $log = array(); public $var = 0;
    public function prepare( $q, ...$a ) {
        if ( count( $a ) === 1 && is_array( $a[0] ) ) $a = $a[0];
        $i = 0;
        return preg_replace_callback( '/%[sdf]/', function ( $m ) use ( &$i, $a ) {
            $v = $a[ $i++ ] ?? null;
            if ( $m[0] === '%d' ) return (string) (int) $v;
            if ( $m[0] === '%f' ) return (string) (float) $v;
            return "'" . addslashes( (string) $v ) . "'";
        }, $q );
    }
    public function query( $q ) { $this->log[] = $q; return 1; }
    public function get_var( $q ) { return $this->var; }
    public function get_row( $q ) { return null; }
    public function update( ...$a ) { return 1; }
    public function insert( ...$a ) { return 1; }
}
$GLOBALS['wpdb'] = new K12_Wpdb();
PHP;

$__k12_trang_src = $__k12_ham( (string) file_get_contents( $__k12_goc . '/includes/shortlink-functions.php' ), 'sitetop_show_block_page' );
$__k12_hanh_src  = $__k12_ham( (string) file_get_contents( $__k12_goc . '/includes/behavior-analytics.php' ), 'sitetop_save_behavior_analytics' );
$__k12_ipapi_src = $__k12_ham( (string) file_get_contents( $__k12_goc . '/includes/ip-fraud.php' ), 'sitetop_check_ip_api' );
assert_true( $__k12_trang_src !== '', 'Phai trich duoc sitetop_show_block_page' );
assert_true( $__k12_hanh_src  !== '', 'Phai trich duoc sitetop_save_behavior_analytics' );
assert_true( $__k12_ipapi_src !== '', 'Phai trich duoc sitetop_check_ip_api' );

/* Trang 'ip_blocked' hứa bao nhiêu giờ. $gio = null: KHÔNG định nghĩa hằng số. */
$__k12_trang_hua = function ( $gio ) use ( $__k12_con, $__k12_nen, $__k12_trang_src ) {
    $ma = $__k12_nen
        . ( $gio === null ? '' : "define( 'SITETOP_IP_KHOA_GIO', " . (int) $gio . " );\n" )
        . $__k12_trang_src . "\nsitetop_show_block_page( 'ip_blocked' );\n";
    list( $out, $err ) = $__k12_con( $ma );
    $chu = html_entity_decode( strip_tags( $out ), ENT_QUOTES, 'UTF-8' );
    $dung_trang = strpos( $chu, 'IP của bạn đang bị tạm khoá' ) !== false;
    $n = preg_match( '/tự hết sau\s+(\d+)\s+giờ/u', $chu, $m ) ? (int) $m[1] : null;
    return array( $dung_trang, $n, $err );
};

/* Khoá thật do gian lận hành vi: trả về các số giờ trong câu SQL ghi ip_reputation. */
$__k12_khoa_hanh_vi = function ( $gio ) use ( $__k12_con, $__k12_nen, $__k12_hanh_src ) {
    $ma = $__k12_nen
        . ( $gio === null ? '' : "define( 'SITETOP_IP_KHOA_GIO', " . (int) $gio . " );\n" ) . <<<'PHP'
function sitetop_calculate_fraud_score( $d ) { return array( 'fraud_score' => 85, 'fraud_reasons' => array( 'bot' ), 'risk_level' => 'high' ); }
function get_current_user_id() { return 0; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $v ) { return trim( (string) $v ); }
function sitetop_save_device_fingerprint( $d ) {}
PHP
        . $__k12_hanh_src . <<<'PHP'

// Luật nới 22/09/2026: lần vi phạm thứ 3 trong 60 phút, lượt đo đủ 10 giây -> tới ngưỡng khoá
// (canh luật đó ở test-noi-long-khoa-hanh-vi.php; ở đây chỉ canh SỐ GIỜ khoá).
$GLOBALS['wpdb']->var = 3;
sitetop_save_behavior_analytics( 7, 'abcDEF123456', array( 'time_on_page' => 30 ) );
echo json_encode( array_values( array_filter( $GLOBALS['wpdb']->log,
    function ( $s ) { return strpos( $s, 'ip_reputation' ) !== false; } ) ) );
PHP;
    list( $out, $err ) = $__k12_con( $ma );
    $sql = json_decode( $out, true );
    if ( ! is_array( $sql ) ) return array( null, null, $err ?: $out );
    $gio_sql = array();
    foreach ( $sql as $s ) {
        preg_match_all( '/INTERVAL\s+(\d+)\s+HOUR/i', $s, $m );
        foreach ( $m[1] as $g ) $gio_sql[] = (int) $g;
    }
    return array( count( $sql ), $gio_sql, $err );
};

// ---- 1. Hằng số trong functions.php = 12 (đọc trên mã ĐÃ LỘT COMMENT) ----
$__k12_fn_ma = '';
foreach ( token_get_all( (string) file_get_contents( $__k12_goc . '/functions.php' ) ) as $t ) {
    if ( is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) continue;
    $__k12_fn_ma .= is_array( $t ) ? $t[1] : $t;
}
$__k12_gio = preg_match( "/define\(\s*'SITETOP_IP_KHOA_GIO'\s*,\s*(\d+)\s*\)/", $__k12_fn_ma, $__k12_m ) ? (int) $__k12_m[1] : null;
assert_equals( 12, $__k12_gio, 'functions.php phai define SITETOP_IP_KHOA_GIO = 12 (chu site chot 19/09/2026)' );

// ---- 2. Đúng giá trị thật: khoá 12 giờ, trang hứa 12 giờ ----
list( $__k12_so, $__k12_gios, $__k12_err ) = $__k12_khoa_hanh_vi( $__k12_gio );
assert_equals( 1, $__k12_so, 'Gian lan hanh vi lan 2 phai ghi DUNG 1 cau khoa vao ip_reputation. stderr: ' . $__k12_err );
/* So bằng chuỗi JSON: assert_equals ghép mảng vào câu báo lỗi thành "Array" kèm cảnh báo PHP. */
assert_equals( '[12,12]', json_encode( $__k12_gios ),
    'Khoa hanh vi phai 12 gio o CA HAI nhanh (INSERT moi + ON DUPLICATE KEY UPDATE)' );

list( $__k12_dung, $__k12_hua, $__k12_err ) = $__k12_trang_hua( $__k12_gio );
assert_true( $__k12_dung, 'Phai dung trang "IP cua ban dang bi tam khoa" (khong roi vao trang mac dinh). stderr: ' . $__k12_err );
assert_equals( 12, $__k12_hua, 'Trang tam khoa phai hua "tu het sau 12 gio"' );

/* ---- 3. Phép thử LỆCH: đổi hằng số sang 7 thì CẢ khoá lẫn lời hứa cùng đi theo.
   Bắt đúng cái lỗi đã có: một bên đọc hằng số, bên kia ghi cứng. ---- */
list( , $__k12_gios7 ) = $__k12_khoa_hanh_vi( 7 );
assert_equals( '[7,7]', json_encode( $__k12_gios7 ), 'Khoa hanh vi phai DOC hang so SITETOP_IP_KHOA_GIO, khong ghi cung' );
list( , $__k12_hua7 ) = $__k12_trang_hua( 7 );
assert_equals( 7, $__k12_hua7, 'Trang tam khoa phai DOC hang so SITETOP_IP_KHOA_GIO, khong ghi cung' );

// ---- 4. Thiếu hằng số (file nạp lẻ ngoài functions.php): số dự phòng hai bên vẫn khớp nhau ----
list( , $__k12_gios0 ) = $__k12_khoa_hanh_vi( null );
list( , $__k12_hua0 )  = $__k12_trang_hua( null );
assert_true( $__k12_hua0 !== null && $__k12_gios0 === array( $__k12_hua0, $__k12_hua0 ),
    'Thieu hang so: so du phong cua khoa (' . json_encode( $__k12_gios0 ) . ') va trang (' . var_export( $__k12_hua0, true ) . ') phai khop' );

/* ---- 5. Khoá VPN / proxy / 1.1.1.1 (ip-fraud.php) cùng N giờ — và ĐIỀU DỄ SÓT: thời
   gian nhớ kết quả ip-api. Cờ VPN/proxy tự nó chặn user ở mỗi lần vào link, nên kết quả
   BỊ GẮN CỜ mà nhớ 24 giờ thì khoá N giờ vẫn thành chặn 24 giờ. Kết quả SẠCH vẫn nhớ 24 giờ
   (đỡ tốn lượt ip-api). Đếm số lần gọi ip-api với bản ghi cũ từng ấy giờ. ---- */
$__k12_ipfraud = function ( $gio ) use ( $__k12_con, $__k12_nen, $__k12_ipapi_src ) {
    $ma = $__k12_nen
        . ( $gio === null ? '' : "define( 'SITETOP_IP_KHOA_GIO', " . (int) $gio . " );\n" ) . <<<'PHP'
date_default_timezone_set( 'Asia/Ho_Chi_Minh' );   // như functions.php
function sitetop_is_ip_whitelisted( $ip ) { return false; }
function sitetop_get_ip_reputation( $ip ) { return $GLOBALS['REP']; }
function get_transient( $k ) { return 0; }
function set_transient( $k, $v, $t = 0 ) { return true; }
function wp_remote_get( $u, $a = array() ) { $GLOBALS['API']++; return array( 'body' => $GLOBALS['BODY'] ); }
function is_wp_error( $x ) { return false; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
PHP
        . $__k12_ipapi_src . <<<'PHP'

$GLOBALS['OPT'] = array( 'ipapi_enabled' => 1, 'block_proxy_ip' => 1, 'block_vpn_ip' => 1, 'block_datacenter_ip' => 1 );
$GLOBALS['REP'] = null; $GLOBALS['API'] = 0;
$khoa = array();
foreach ( array( 0, 1 ) as $proxy ) foreach ( array( 0, 1 ) as $hosting ) foreach ( array( 0, 1 ) as $mobile )
foreach ( array( 'Viettel Group', 'NordVPN Hosting', 'Cloudflare WARP' ) as $isp ) {
    $GLOBALS['BODY'] = json_encode( array( 'status' => 'success', 'proxy' => (bool) $proxy, 'hosting' => (bool) $hosting,
        'mobile' => (bool) $mobile, 'isp' => $isp, 'org' => '', 'as' => '' ) );
    $GLOBALS['wpdb']->log = array();
    sitetop_check_ip_api( '203.0.113.9' );
    foreach ( $GLOBALS['wpdb']->log as $s ) {
        if ( strpos( $s, 'SET blocked=1' ) === false ) continue;
        $khoa[] = preg_match( '/INTERVAL\s+(\d+)\s+HOUR/i', $s, $m ) ? (int) $m[1] : -1;
    }
}
$GLOBALS['BODY'] = json_encode( array( 'status' => 'success', 'proxy' => false, 'hosting' => false,
    'mobile' => true, 'isp' => 'Viettel Group', 'org' => '', 'as' => '' ) );
$nho = array();
foreach ( array( 'sach' => array( 0, 0, 0 ), 'vpn' => array( 1, 0, 0 ), 'proxy' => array( 0, 1, 0 ), 'may_chu' => array( 0, 0, 1 ) ) as $ten => $c )
foreach ( array( 6, 8, 11, 13, 23, 25 ) as $tuoi ) {
    $GLOBALS['REP'] = (object) array( 'is_vpn' => $c[0], 'is_proxy' => $c[1], 'is_hosting' => $c[2], 'is_mobile' => 0,
        'risk_score' => 90, 'country_code' => 'VN', 'isp' => 'x', 'org' => 'x',
        'checked_at' => date( 'Y-m-d H:i:s', time() - $tuoi * 3600 ) );
    $GLOBALS['API'] = 0;
    sitetop_check_ip_api( '203.0.113.9' );
    $nho[ $ten . '@' . $tuoi . 'h' ] = $GLOBALS['API'];
}
echo json_encode( array( 'khoa' => $khoa, 'nho' => $nho ) );
PHP;
    list( $out, $err ) = $__k12_con( $ma );
    $kq = json_decode( $out, true );
    return array( is_array( $kq ) ? $kq : array( 'khoa' => array(), 'nho' => array() ), $err ?: ( is_array( $kq ) ? '' : $out ) );
};
$__k12_gio_khoa = function ( $kq ) { return json_encode( array_values( array_unique( $kq['khoa'] ) ) ); };

list( $__k12_ip, $__k12_err ) = $__k12_ipfraud( $__k12_gio );
assert_true( count( $__k12_ip['khoa'] ) >= 1,
    'Phai co it nhat 1 to hop bi khoa (vd proxy + may chu), khong thi phep canh vo nghia. stderr: ' . $__k12_err );
assert_equals( '[12]', $__k12_gio_khoa( $__k12_ip ), 'Khoa VPN/proxy cua ip-fraud phai 12 gio o MOI to hop' );
foreach ( array(
    'vpn@11h' => 0, 'vpn@13h' => 1, 'proxy@11h' => 0, 'proxy@13h' => 1, 'may_chu@11h' => 0, 'may_chu@13h' => 1,
) as $__k12_ten => $__k12_goi ) {
    assert_equals( $__k12_goi, $__k12_ip['nho'][ $__k12_ten ] ?? null,
        'Ket qua BI GAN CO phai tra lai ip-api sau 12 gio, khong thi khoa 12 gio van chan 24 gio: ' . $__k12_ten );
}
foreach ( array( 'sach@13h' => 0, 'sach@23h' => 0, 'sach@25h' => 1 ) as $__k12_ten => $__k12_goi ) {
    assert_equals( $__k12_goi, $__k12_ip['nho'][ $__k12_ten ] ?? null,
        'Ket qua SACH van nho 24 gio (do ton luot ip-api): ' . $__k12_ten );
}

// Phép thử LỆCH cho ip-fraud: khoá và thời gian nhớ kết quả bị gắn cờ đều ĐỌC hằng số.
list( $__k12_ip7 ) = $__k12_ipfraud( 7 );
assert_equals( '[7]', $__k12_gio_khoa( $__k12_ip7 ), 'Khoa ip-fraud phai DOC hang so SITETOP_IP_KHOA_GIO, khong ghi cung' );
assert_equals( 0, $__k12_ip7['nho']['proxy@6h'] ?? null, 'Hang so 7: ket qua bi gan co 6 gio tuoi van duoc nho' );
assert_equals( 1, $__k12_ip7['nho']['proxy@8h'] ?? null, 'Thoi gian nho ket qua bi gan co phai DOC hang so, khong ghi cung' );
assert_equals( 0, $__k12_ip7['nho']['sach@8h'] ?? null, 'Ket qua sach khong duoc phu thuoc hang so khoa' );

// Thiếu hằng số: số dự phòng của ip-fraud khớp với trang và khoá hành vi.
list( $__k12_ip0 ) = $__k12_ipfraud( null );
assert_equals( json_encode( array( $__k12_hua0 ) ), $__k12_gio_khoa( $__k12_ip0 ),
    'Thieu hang so: so du phong cua ip-fraud phai khop voi trang tam khoa' );
