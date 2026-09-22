<?php
/* NỚI LỎNG KHOÁ IP DO HÀNH VI — 22/09/2026.

   Chủ site báo: user bị trang báo lỗi, tải lại trang là bị khoá IP 12 giờ ("IP của bạn đang bị
   tạm khoá — dấu hiệu bất thường"). CSDL xác nhận: IP 14.169.26.16, điện thoại Android, hai lần
   tải lại cách nhau 19 giây -> 100 rồi 75 điểm -> đủ "2 lần >= 70" -> khoá.

   Hai lỗi, canh cả hai bằng HÀM THẬT chạy trong tiến trình PHP con (khỏi đụng hàm giả của test khác):
   A. Bộ chấm điểm phạt những trường widget KHÔNG BAO GIỜ gửi (màn hình, gõ phím, canvas, webgl)
      và coi điện thoại là máy tính (is_mobile không gửi) -> ai cũng mang sẵn 50–80 điểm.
   B. Luật khoá: 2 lần >= 70 bất kỳ lúc nào -> nay 3 lần trong 60 phút, và lượt đo dưới 10 giây
      (tải lại / thoát ngay) không tính là vi phạm. */

$__nl_goc = dirname( __DIR__, 2 );
$__nl_src = (string) file_get_contents( $__nl_goc . '/includes/behavior-analytics.php' );

$__nl_ham = function ( $ma, $ten ) {
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

$__nl_cham_src = $__nl_ham( $__nl_src, 'sitetop_calculate_fraud_score' );
$__nl_luu_src  = $__nl_ham( $__nl_src, 'sitetop_save_behavior_analytics' );
assert_true( $__nl_cham_src !== '', 'Phai trich duoc sitetop_calculate_fraud_score' );
assert_true( $__nl_luu_src !== '', 'Phai trich duoc sitetop_save_behavior_analytics' );

$__nl_con = function ( $ma ) {
    $p = proc_open( array( PHP_BINARY, '-d', 'display_errors=stderr', '-r', $ma ),
        array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $ong );
    if ( ! is_resource( $p ) ) return array( null, 'khong mo duoc tien trinh con' );
    $out = stream_get_contents( $ong[1] ); $err = stream_get_contents( $ong[2] );
    fclose( $ong[1] ); fclose( $ong[2] ); proc_close( $p );
    $kq = json_decode( $out, true );
    return array( $kq, $err ?: ( is_array( $kq ) ? '' : $out ) );
};

/* Nền chung cho tiến trình con. wp_is_mobile() giả đọc đúng thứ WordPress đọc: User-Agent. */
$__nl_nen = <<<'PHP'
error_reporting( E_ALL );
function wp_is_mobile() { return (bool) preg_match( '/Mobile|Android|iPhone|iPad/', $_SERVER['HTTP_USER_AGENT'] ?? '' ); }
function sitetop_get_real_ip() { return '14.169.26.16'; }
function sitetop_is_ip_whitelisted( $ip ) { return false; }
function sitetop_current_time() { return '2026-09-22 22:19:31'; }
function get_current_user_id() { return 0; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $v ) { return trim( (string) $v ); }
function sitetop_save_device_fingerprint( $d ) {}
PHP;

/* ================= A. BỘ CHẤM ĐIỂM ================= */
$__nl_cham = function ( $data, $ua ) use ( $__nl_con, $__nl_nen, $__nl_cham_src ) {
    $ma = $__nl_nen . "\n" . '$_SERVER["HTTP_USER_AGENT"] = ' . var_export( $ua, true ) . ";\n"
        . $__nl_cham_src . "\n"
        . 'echo json_encode( sitetop_calculate_fraud_score( ' . var_export( $data, true ) . ' ) );';
    return $__nl_con( $ma );
};
$__nl_android = 'Mozilla/5.0 (Linux; Android 14; SM-A155F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Mobile Safari/537.36';
$__nl_pc      = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';
/* Đúng 5 số widget.js.php gửi (reportBehavior) — cộng session_id như $_POST thật. */
$__nl_widget = function ( $mouse, $scroll, $time, $clicks ) {
    return array( 'action' => 'sitetop_report_behavior', 'session_id' => 'abc12345',
        'mouse_movements' => $mouse, 'scroll_depth' => $scroll, 'time_on_page' => $time,
        'tab_switches' => 0, 'clicks' => $clicks );
};

// A1. Ca thật 22:19:12 — Android, tải lại sau 2 giây, chưa chạm gì. Bản cũ: 100 điểm.
list( $__nl_k, $__nl_e ) = $__nl_cham( $__nl_widget( 0, 0, 2, 0 ), $__nl_android );
assert_equals( 55, $__nl_k['fraud_score'] ?? null,
    'Ca that 22:19:12 (Android, tai lai sau 2 giay): chi no_scroll+no_clicks+time_lt_5s = 55 (ban cu 100). stderr: ' . $__nl_e );
foreach ( array( 'no_screen_size', 'no_keystrokes', 'no_canvas', 'no_webgl', 'no_mouse', 'no_touch_mobile' ) as $__nl_ly ) {
    assert_true( ! in_array( $__nl_ly, (array) ( $__nl_k['fraud_reasons'] ?? array() ), true ),
        'Widget KHONG gui truong nay / dien thoai khong co chuot -> khong duoc phat: ' . $__nl_ly );
}

// A2. Ca thật 22:19:31 — Android, 7 giây, bấm 3, cuộn hết trang, 2 lần mousemove giả lập từ chạm. Bản cũ: 75.
list( $__nl_k, $__nl_e ) = $__nl_cham( $__nl_widget( 2, 100, 7, 3 ), $__nl_android );
assert_equals( 10, $__nl_k['fraud_score'] ?? null,
    'Ca that 22:19:31 (Android 7 giay, co bam + cuon): chi time_lt_10s = 10 (ban cu 75). stderr: ' . $__nl_e );

// A3. Máy tính, người thật đọc trang 80 giây — sạch.
list( $__nl_k, $__nl_e ) = $__nl_cham( $__nl_widget( 60, 70, 80, 2 ), $__nl_pc );
assert_equals( 0, $__nl_k['fraud_score'] ?? null, 'May tinh, nguoi that 80 giay co di chuot/cuon/bam -> 0 diem. stderr: ' . $__nl_e );

// A4. Máy tính KHÔNG đụng chuột vẫn bị luật chuột (điện thoại mới được miễn).
list( $__nl_k, $__nl_e ) = $__nl_cham( $__nl_widget( 0, 0, 30, 0 ), $__nl_pc );
assert_equals( 60, $__nl_k['fraud_score'] ?? null, 'May tinh 30 giay khong chuot/cuon/bam -> no_mouse+no_scroll+no_clicks = 60. stderr: ' . $__nl_e );

// A5. Client gửi ĐỦ trường (định dạng cũ) -> luật cũ chạy nguyên: thiếu màn hình/canvas/webgl/gõ phím vẫn bị phạt.
$__nl_du = array( 'mouse_movements' => 0, 'scroll_depth' => 0, 'time_on_page' => 30, 'clicks' => 0,
    'keystrokes' => 0, 'touch_events' => 0, 'is_mobile' => 0, 'screen_width' => 0, 'screen_height' => 0,
    'canvas_hash' => '', 'webgl_vendor' => '' );
list( $__nl_k, $__nl_e ) = $__nl_cham( $__nl_du, $__nl_android );
assert_equals( 100, $__nl_k['fraud_score'] ?? null,
    'Client gui DU truong: is_mobile=0 tu khai -> luat may tinh, + man hinh/canvas/webgl/go phim = 100 (luat cu giu nguyen). stderr: ' . $__nl_e );
assert_equals( '["no_screen_size","no_mouse","no_scroll","no_clicks","no_keystrokes","no_canvas","no_webgl"]',
    json_encode( $__nl_k['fraud_reasons'] ?? null ), 'Client gui du truong: dung 7 ly do cua luat cu' );

// A6. Tự khai is_mobile=1 + có đếm chạm = 0 + không chuột -> luật "không chạm" vẫn chạy.
list( $__nl_k, $__nl_e ) = $__nl_cham( array( 'is_mobile' => 1, 'touch_events' => 0, 'mouse_movements' => 0,
    'scroll_depth' => 50, 'time_on_page' => 30, 'clicks' => 1 ), $__nl_pc );
assert_equals( 25, $__nl_k['fraud_score'] ?? null, 'Client tu khai dien thoai + gui touch_events=0 -> no_touch_mobile 25 (UA khong de len tu khai). stderr: ' . $__nl_e );

/* ================= B. LUẬT TỰ KHOÁ ================= */
/* $wpdb giả: prepare thay placeholder như WordPress; get_var trả số lần vi phạm đặt sẵn;
   ghi lại mọi câu SQL. Điểm lấy từ bộ chấm THẬT. */
$__nl_khoa = function ( $data, $ua, $so_lan ) use ( $__nl_con, $__nl_nen, $__nl_cham_src, $__nl_luu_src ) {
    $ma = $__nl_nen . "\n" . 'define( "SITETOP_IP_KHOA_GIO", 12 );' . "\n"
        . '$_SERVER["HTTP_USER_AGENT"] = ' . var_export( $ua, true ) . ";\n" . <<<'PHP'
class NL_Wpdb {
    public $prefix = 'wpgd_'; public $log = array(); public $dem = array(); public $var = 0;
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
    public function get_var( $q ) { $this->dem[] = $q; return $this->var; }
    public function get_row( $q ) { return null; }
    public function update( ...$a ) { return 1; }
}
$GLOBALS['wpdb'] = new NL_Wpdb();
PHP
        . "\n" . $__nl_cham_src . "\n" . $__nl_luu_src . "\n"
        . '$GLOBALS["wpdb"]->var = ' . (int) $so_lan . ";\n"
        . 'sitetop_save_behavior_analytics( 609848, "508ac9ba", ' . var_export( $data, true ) . " );\n" . <<<'PHP'
$khoa = array_values( array_filter( $GLOBALS['wpdb']->log, function ( $s ) { return strpos( $s, 'ip_reputation' ) !== false; } ) );
echo json_encode( array( 'khoa' => count( $khoa ), 'dem' => $GLOBALS['wpdb']->dem, 'sql_khoa' => $khoa ) );
PHP;
    return $__nl_con( $ma );
};

// B1. Máy tính bấm F5 sau 2 giây (85 điểm >= 70) — dưới 10 giây: KHÔNG tính vi phạm, không đếm, không khoá.
list( $__nl_k, $__nl_e ) = $__nl_khoa( $__nl_widget( 0, 0, 2, 0 ), $__nl_pc, 99 );
assert_equals( 0, $__nl_k['khoa'] ?? null, 'Tai lai sau 2 giay (du 85 diem) KHONG duoc khoa IP. stderr: ' . $__nl_e );
assert_equals( 0, count( (array) ( $__nl_k['dem'] ?? array( 'x' ) ) ), 'Luot duoi 10 giay khong phai vi pham -> khong can dem so lan' );

// B2. Bot tự khai (is_bot) ở 30 giây: vi phạm thật, nhưng mới là lần thứ 2 trong 60 phút -> chưa khoá.
$__nl_bot = array_merge( $__nl_widget( 0, 0, 30, 0 ), array( 'is_bot' => 1 ) );
list( $__nl_k, $__nl_e ) = $__nl_khoa( $__nl_bot, $__nl_pc, 2 );
assert_equals( 0, $__nl_k['khoa'] ?? null, 'Lan vi pham thu 2 trong 60 phut: CHUA khoa (ban cu khoa o lan 2). stderr: ' . $__nl_e );
assert_equals( 1, count( (array) ( $__nl_k['dem'] ?? array() ) ), 'Vi pham that (>= 70, >= 10 giay) phai dem so lan' );

// B3. Lần thứ 3 trong 60 phút -> khoá, đúng 12 giờ.
list( $__nl_k, $__nl_e ) = $__nl_khoa( $__nl_bot, $__nl_pc, 3 );
assert_equals( 1, $__nl_k['khoa'] ?? null, 'Lan vi pham thu 3 trong 60 phut PHAI khoa IP. stderr: ' . $__nl_e );
assert_true( preg_match_all( '/INTERVAL 12 HOUR/', (string) ( $__nl_k['sql_khoa'][0] ?? '' ) ) === 2, 'Khoa van dung 12 gio (ca INSERT lan ON DUPLICATE)' );

// B4. Câu ĐẾM phải có đủ điều kiện — $wpdb giả không đọc SQL nên canh nguyên văn (bỏ một điều kiện
//     là test hành vi ở trên vẫn xanh mà luật đã lỏng/gắt sai).
$__nl_c = preg_replace( '/\s+/', ' ', (string) ( $__nl_k['dem'][0] ?? '' ) );
assert_true( strpos( $__nl_c, "ip_address = '14.169.26.16'" ) !== false, 'Dem theo dung IP' );
assert_true( strpos( $__nl_c, 'fraud_score >= 70' ) !== false, 'Chi dem luot >= 70 diem' );
assert_true( strpos( $__nl_c, 'time_on_page >= 10' ) !== false, 'Luot tai lai duoi 10 giay da luu tu truoc cung KHONG duoc dem' );
assert_true( strpos( $__nl_c, "created_at >= DATE_SUB('2026-09-22 22:19:31', INTERVAL 60 MINUTE)" ) !== false,
    'Chi dem trong 60 phut gan nhat (ban cu dem ca 14 ngay du lieu)' );

// B5. Điểm dưới 70 -> không đếm, không khoá (ca thật 22:19:31 nay chỉ 10 điểm).
list( $__nl_k, $__nl_e ) = $__nl_khoa( $__nl_widget( 2, 100, 12, 3 ), $__nl_android, 99 );
assert_equals( 0, $__nl_k['khoa'] ?? null, 'Duoi 70 diem thi khong bao gio khoa. stderr: ' . $__nl_e );
