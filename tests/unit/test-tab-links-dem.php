<?php
/* TAB SHORTLINKS — ĐẾM MỘT LẦN RỒI SUY RA (11/10/2026, "admin chuyển mục load hơi chậm").
   user_shortlinks ~441 nghìn dòng, không có chỉ mục created_at: tab từng chạy 4 câu đếm quét cả bảng mỗi lần mở.
   Nay đếm theo trạng thái MỘT lần (giữ 5 phút) rồi suy ra "Tổng link" + số phân trang khi không tìm kiếm.
   Test chạy ĐÚNG đoạn mã trích từ tab-links.php trên SQLite, với đủ loại trạng thái (active / disabled / deleted /
   NULL / chuỗi rỗng / lạ) và so với câu COUNT(*) cũ (LEFT JOIN users, mặc định status != 'deleted') ở mọi kiểu lọc. */

$__tl_goc = dirname( __DIR__, 2 );
$__tl_ma  = (string) file_get_contents( $__tl_goc . '/includes/admin/tabs/tab-links.php' );
$__tl_a   = strpos( $__tl_ma, "\$counts = sitetop_so_lieu_admin( 'sl_dem_trang_thai'" );
$__tl_b   = strpos( $__tl_ma, "\$args[] = \$per_page;", (int) $__tl_a );
$__tl_doan1 = ( $__tl_a !== false && $__tl_b !== false ) ? substr( $__tl_ma, $__tl_a, $__tl_b - $__tl_a ) : '';
$__tl_c   = strpos( $__tl_ma, '$sl_total = 0;' );
$__tl_d   = strpos( $__tl_ma, '$today = date(', (int) $__tl_c );
$__tl_doan2 = ( $__tl_c !== false && $__tl_d !== false ) ? substr( $__tl_ma, $__tl_c, $__tl_d - $__tl_c ) : '';
assert_true( $__tl_doan1 !== '' && $__tl_doan2 !== '', 'Trich duoc doan dem theo trang thai + suy ra tong trong tab-links.php' );

$__tl_ban = <<<'BANTHU'
<?php
define( 'OBJECT_K', 'OBJECT_K' );
class W {
    public $prefix = 'wp_'; public $users = 'wp_users'; public $pdo; public $so = 0;
    function __construct() { $this->pdo = new PDO( 'sqlite::memory:' ); $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION ); }
    function prepare( $q, ...$a ) { if ( count( $a ) === 1 && is_array( $a[0] ) ) $a = $a[0]; $i = 0; $pdo = $this->pdo;
        return preg_replace_callback( '/%[dsf]/', function ( $m ) use ( &$i, $a, $pdo ) { $v = $a[ $i++ ]; return $m[0] === '%d' ? (string) (int) $v : $pdo->quote( (string) $v ); }, $q ); }
    function get_var( $q ) { $this->so++; $r = $this->pdo->query( $q )->fetch( PDO::FETCH_NUM ); return $r ? $r[0] : null; }
    function get_results( $q, $kieu = null ) { $this->so++; $rows = $this->pdo->query( $q )->fetchAll( PDO::FETCH_OBJ );
        if ( $kieu !== OBJECT_K ) return $rows; $o = array(); foreach ( $rows as $r ) { $k = array_values( (array) $r )[0]; if ( ! isset( $o[ $k ] ) ) $o[ $k ] = $r; } return $o; }
}
$T = array();
function sitetop_so_lieu_admin( $k, $s, $f ) { if ( ! array_key_exists( $k, $GLOBALS['T'] ) ) $GLOBALS['T'][ $k ] = $f(); return $GLOBALS['T'][ $k ]; }
$wpdb = new W(); $prefix = 'wp_sitetop_';
$wpdb->pdo->exec( "CREATE TABLE wp_users (ID INTEGER PRIMARY KEY, user_login TEXT)" );
$wpdb->pdo->exec( "CREATE TABLE wp_sitetop_user_shortlinks (id INTEGER PRIMARY KEY, user_id INT, status TEXT, code TEXT, alias TEXT, original_url TEXT, created_at TEXT)" );
$wpdb->pdo->exec( "INSERT INTO wp_users VALUES (1,'an'),(2,'binh')" );
$st = $wpdb->pdo->prepare( 'INSERT INTO wp_sitetop_user_shortlinks (user_id, status, code, alias, original_url, created_at) VALUES (?,?,?,?,?,?)' );
$i = 0;
foreach ( array( array( 'active', 7 ), array( 'disabled', 3 ), array( 'deleted', 5 ), array( null, 4 ), array( '', 2 ), array( 'la', 1 ) ) as $x ) {
    for ( $k = 0; $k < $x[1]; $k++ ) { $i++; $st->execute( array( $i % 3, $x[0], 'c' . $i, 'a' . $i, 'https://x/' . $i, '2026-10-11 10:00:00' ) ); }
}
$doan1 = file_get_contents( $argv[1] ); $doan2 = file_get_contents( $argv[2] );
$out = array();
foreach ( array( '', 'active', 'disabled', 'deleted', 'la', 'khongco' ) as $loc ) {
    $T = array(); $status_filter = $loc; $search = '';
    $where = "WHERE 1=1"; $args = array();
    if ( $status_filter ) { $where .= " AND sl.status = %s"; $args[] = $status_filter; } else { $where .= " AND sl.status != 'deleted'"; }
    $count_sql = "SELECT COUNT(*) FROM {$prefix}user_shortlinks sl LEFT JOIN {$wpdb->users} u ON u.ID = sl.user_id $where";
    $cu = (int) ( ! empty( $args ) ? $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : $wpdb->get_var( $count_sql ) );
    $total = null; eval( $doan1 ); eval( $doan2 );
    $out['loc'][ $loc === '' ? '(mac dinh)' : $loc ] = array( 'cu' => $cu, 'moi' => (int) $total );
    $out['tong'] = array( 'cu' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}user_shortlinks" ), 'moi' => $sl_total );
    $out['dem_khoa'] = array_keys( $counts );
}
// Có tìm kiếm → vẫn chạy câu COUNT thật (không suy từ bảng đếm)
$T = array(); $status_filter = ''; $search = 'c1'; $where = "WHERE 1=1 AND sl.status != 'deleted' AND (sl.code LIKE %s)"; $args = array( '%c1%' );
$count_sql = "SELECT COUNT(*) FROM {$prefix}user_shortlinks sl LEFT JOIN {$wpdb->users} u ON u.ID = sl.user_id $where";
$wpdb->so = 0; $total = null; eval( $doan1 );
$out['tim'] = array( 'total' => (int) $total, 'that' => (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ), 'so_cau' => $wpdb->so );
echo json_encode( $out );
BANTHU;
$__tl_f  = sys_get_temp_dir() . '/st-tl-' . getmypid() . '.php';
$__tl_f1 = sys_get_temp_dir() . '/st-tl1-' . getmypid() . '.php';
$__tl_f2 = sys_get_temp_dir() . '/st-tl2-' . getmypid() . '.php';
file_put_contents( $__tl_f, $__tl_ban ); file_put_contents( $__tl_f1, $__tl_doan1 ); file_put_contents( $__tl_f2, $__tl_doan2 );
$__tl_r = (string) shell_exec( 'php ' . escapeshellarg( $__tl_f ) . ' ' . escapeshellarg( $__tl_f1 ) . ' ' . escapeshellarg( $__tl_f2 ) . ' 2>&1' );
@unlink( $__tl_f ); @unlink( $__tl_f1 ); @unlink( $__tl_f2 );
$TL = json_decode( trim( $__tl_r ), true );
assert_true( is_array( $TL ), 'Chay duoc doan dem tab Shortlinks tren SQLite. Ra: ' . substr( $__tl_r, 0, 500 ) );
if ( is_array( $TL ) ) {
    foreach ( $TL['loc'] as $__tl_k => $__tl_x ) {
        assert_equals( $__tl_x['cu'], $__tl_x['moi'], "So phan trang khi loc '$__tl_k': suy tu bang dem = cau COUNT cu" );
    }
    assert_equals( 13, $TL['loc']['(mac dinh)']['cu'], 'Du lieu thu du loai: mac dinh = active 7 + disabled 3 + rong 2 + la 1 = 13 (NULL 4 va deleted 5 bi loai nhu cau cu)' );
    assert_equals( $TL['tong']['cu'], $TL['tong']['moi'], '"Tong link" = COUNT(*) ca bang (gom NULL, rong, da xoa)' );
    assert_true( in_array( '__null__', $TL['dem_khoa'], true ) && in_array( '', $TL['dem_khoa'], true ), 'NULL tach rieng nhom __null__, khong lan voi chuoi rong' );
    assert_true( $TL['tim']['total'] === $TL['tim']['that'] && $TL['tim']['so_cau'] >= 2, 'Co tim kiem → chay cau COUNT that (khong suy tu bang dem)' );
}
