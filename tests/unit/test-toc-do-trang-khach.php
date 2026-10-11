<?php
/* TRANG KHÁCH HÀNG NGHẼN CSDL (11/10/2026): khách lớn #426 tải đi tải lại trang tổng quan, mỗi lần ~70 truy vấn quét
   toàn bộ lịch sử (biểu đồ 30 ngày = 60 câu DATE(created_at)=ngày) → hết 30 kết nối CSDL → cả site ra trang bảo trì.
   Sửa cách tính, KHÔNG sửa con số. Test chạy MÃ THẬT (hàm trợ giúp + đúng các đoạn trích từ page-customer-dashboard.php
   và customer-load-more.php) trên SQLite trong bộ nhớ, rồi so với CÁC CÂU SQL CŨ giữ nguyên văn dưới đây:
   biểu đồ 30 ngày, thẻ "Hôm nay", total_completed + today_views từng camp (trang + "xem thêm"). Dữ liệu có lượt sát mép
   nửa đêm, camp đã xoá, camp của khách khác, giao dịch dương / loại khác — số phải y hệt từng ô. */

$__tk_goc = dirname( __DIR__, 2 );
$__tk_ad  = (string) file_get_contents( $__tk_goc . '/includes/admin-dashboard.php' );
$__tk_pg  = (string) file_get_contents( $__tk_goc . '/page-customer-dashboard.php' );
$__tk_lm  = (string) file_get_contents( $__tk_goc . '/includes/customer-load-more.php' );

$__tk_than = function ( $ma, $ten ) {
    $vt = strpos( $ma, 'function ' . $ten . '(' );
    if ( $vt === false ) return '';
    $tk = token_get_all( '<?php ' . substr( $ma, $vt ) );
    $out = ''; $d = 0; $open = false;
    foreach ( $tk as $t ) {
        $bo = is_array( $t ) && in_array( $t[0], array( T_OPEN_TAG, T_COMMENT, T_DOC_COMMENT ), true );
        if ( ! $bo ) $out .= is_array( $t ) ? $t[1] : $t;
        $mo = ( $t === '{' ) || ( is_array( $t ) && in_array( $t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) );
        if ( $mo ) { $d++; $open = true; }
        elseif ( $t === '}' ) { $d--; if ( $open && $d === 0 ) break; }
    }
    return $out;
};
/* Đoạn mã giữa hai mốc (gồm cả mốc cuối) — để chạy đúng code đang nằm trong trang, không chép lại. */
$__tk_doan = function ( $ma, $dau, $cuoi ) {
    $a = strpos( $ma, $dau );
    if ( $a === false ) return '';
    $b = strpos( $ma, $cuoi, $a );
    return $b === false ? '' : substr( $ma, $a, $b - $a + strlen( $cuoi ) );
};

$__tk_ham = '';
foreach ( array( 'sitetop_khoang_ngay', 'sitetop_so_lieu_admin', 'sitetop_camp_ids_cua_khach', 'sitetop_gan_so_view_camp', 'sitetop_bieu_do_khach' ) as $__tk_t ) {
    $__tk_x = $__tk_than( $__tk_ad, $__tk_t );
    assert_true( $__tk_x !== '', 'Tim thay ham ' . $__tk_t . ' trong admin-dashboard.php' );
    $__tk_ham .= $__tk_x . "\n";
}
$__tk_d_camp  = $__tk_doan( $__tk_pg, '$my_campaigns = $wpdb->get_results(', 'sitetop_gan_so_view_camp( $my_campaigns, $today );' );
$__tk_d_homnay = $__tk_doan( $__tk_pg, '$_kh_cids = sitetop_camp_ids_cua_khach( $user_id );', ') ) : 0;' );
$__tk_d_bd    = $__tk_doan( $__tk_pg, '$chart = sitetop_bieu_do_khach(', ';' );
$__tk_d_them  = $__tk_doan( $__tk_lm, '$rows = $wpdb->get_results( $wpdb->prepare(', 'sitetop_gan_so_view_camp( $rows, $today );' );
assert_true( $__tk_d_camp !== '' && $__tk_d_homnay !== '' && $__tk_d_bd !== '' && $__tk_d_them !== '', 'Trich duoc 4 doan ma that (danh sach camp, the hom nay, bieu do, xem them)' );

$__tk_ban = <<<'BANTHU'
<?php
$T = array();
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['T'] ) ? $GLOBALS['T'][ $k ] : false; }
function set_transient( $k, $v, $s ) { $GLOBALS['T'][ $k ] = $v; return true; }
function sitetop_current_time() { return $GLOBALS['BAY_GIO']; }
class W {
    public $prefix = 'wp_'; public $pdo; public $so_cau = 0; public $cau = array();
    function __construct() { $this->pdo = new PDO( 'sqlite::memory:' ); $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION ); }
    function prepare( $q, ...$a ) {
        if ( count( $a ) === 1 && is_array( $a[0] ) ) $a = $a[0];
        $i = 0; $pdo = $this->pdo;
        return preg_replace_callback( '/%[dsf]/', function ( $m ) use ( &$i, $a, $pdo ) {
            $v = $a[ $i++ ];
            if ( $m[0] === '%d' ) return (string) (int) $v;
            if ( $m[0] === '%f' ) return (string) (float) $v;
            return $pdo->quote( (string) $v );
        }, $q );
    }
    function chay( $q ) { $this->so_cau++; $this->cau[] = $q; return $this->pdo->query( $q ); }
    function get_results( $q ) { return $this->chay( $q )->fetchAll( PDO::FETCH_OBJ ); }
    function get_col( $q ) { return $this->chay( $q )->fetchAll( PDO::FETCH_COLUMN, 0 ); }
    function get_var( $q ) { $r = $this->chay( $q )->fetch( PDO::FETCH_NUM ); return $r ? $r[0] : null; }
}
$wpdb = new W(); $GLOBALS['wpdb'] = $wpdb;
$db = $wpdb->pdo;
$db->exec( "CREATE TABLE wp_sitetop_keyword_campaigns (id INTEGER PRIMARY KEY, customer_id INT, status TEXT, order_id INT, created_at TEXT, title TEXT)" );
$db->exec( "CREATE TABLE wp_sitetop_customer_orders (id INTEGER PRIMARY KEY, task_type TEXT)" );
$db->exec( "CREATE TABLE wp_sitetop_shortlink_visits (id INTEGER PRIMARY KEY, campaign_id INT, created_at TEXT, step TEXT, customer_paid INT)" );
$db->exec( "CREATE TABLE wp_sitetop_customer_transactions (id INTEGER PRIMARY KEY, customer_id INT, type TEXT, amount REAL, created_at TEXT)" );
/* Khách 7: camp 1, 2, 3 (3 đã xoá — vẫn tính vào biểu đồ/thẻ như phép JOIN cũ), 10; khách 8: camp 4, 5; khách 9: camp 6 không lượt nào. */
foreach ( array( array( 1, 7, 'active' ), array( 2, 7, 'paused' ), array( 3, 7, 'deleted' ), array( 10, 7, 'pending' ), array( 4, 8, 'active' ), array( 5, 8, 'active' ), array( 6, 9, 'active' ) ) as $k => $c ) {
    $db->exec( "INSERT INTO wp_sitetop_keyword_campaigns VALUES ({$c[0]}, {$c[1]}, '{$c[2]}', {$c[0]}, '2026-09-0" . ( $k + 1 ) . " 10:00:00', 'camp {$c[0]}')" );
    $db->exec( "INSERT INTO wp_sitetop_customer_orders VALUES ({$c[0]}, 'keyword_search')" );
}
mt_srand( 20261011 );
$buoc = array( 'verified', 'started', 'code_shown', 'rejected', 'target_visited', 'verified' );
$camps = array( 1, 2, 3, 10, 4, 5, 6, 0, 99 ); // 0 / 99: lượt lạc (không thuộc camp nào của khách)
$sv = $db->prepare( 'INSERT INTO wp_sitetop_shortlink_visits (campaign_id, created_at, step, customer_paid) VALUES (?,?,?,?)' );
$st = $db->prepare( 'INSERT INTO wp_sitetop_customer_transactions (customer_id, type, amount, created_at) VALUES (?,?,?,?)' );
$goc = strtotime( '2026-10-12 00:00:00' );
for ( $ngay = -42; $ngay <= 1; $ngay++ ) {
    $nen = $goc + $ngay * 86400;
    foreach ( array( 0, 1, 59, 3600 * 7 + 13, 43200, 86399 ) as $giay ) {           // sát mép nửa đêm hai đầu
        for ( $l = 0; $l < 3; $l++ ) {
            $sv->execute( array( $camps[ mt_rand( 0, 8 ) ], date( 'Y-m-d H:i:s', $nen + $giay ), $buoc[ mt_rand( 0, 5 ) ], mt_rand( 0, 2 ) === 0 ? 1 : 0 ) );
            $kh = array( 7, 8, 9, 7 )[ mt_rand( 0, 3 ) ];
            $loai = array( 'campaign_view', 'campaign_view', 'campaign_view', 'deposit', 'refund' )[ mt_rand( 0, 4 ) ];
            $tien = ( mt_rand( 0, 5 ) === 0 ? 1 : -1 ) * ( mt_rand( 1, 40 ) * 100 + ( mt_rand( 0, 1 ) ? 0.5 : 0 ) );
            $st->execute( array( $kh, $loai, $tien, date( 'Y-m-d H:i:s', $nen + $giay ) ) );
        }
    }
}

/* Lượt + giao dịch CỐ ĐỊNH đúng mép nửa đêm cho mọi camp / khách (không phó mặc cho ngẫu nhiên): đầu hôm nay, cuối hôm nay,
   đầu ngày mai, cuối hôm qua; và mép đầu / cuối cửa sổ 30 ngày của biểu đồ. */
foreach ( array( 1, 2, 3, 10, 4, 5 ) as $c ) {
    foreach ( array( '2026-10-11 00:00:00', '2026-10-11 23:59:59', '2026-10-12 00:00:00', '2026-10-10 23:59:59', '2026-09-12 00:00:00', '2026-09-11 23:59:59' ) as $m ) {
        $sv->execute( array( $c, $m, 'verified', 0 ) );
        $sv->execute( array( $c, $m, 'code_shown', 1 ) );
    }
}
foreach ( array( 7, 8 ) as $kh ) {
    foreach ( array( '2026-10-11 00:00:00', '2026-10-11 23:59:59', '2026-10-12 00:00:00', '2026-09-12 00:00:00', '2026-09-11 23:59:59' ) as $m ) {
        $st->execute( array( $kh, 'campaign_view', -1234.5, $m ) );
    }
}

$BAY_GIO = '2026-10-11 15:04:05';
$prefix = 'wp_sitetop_';
$out = array();

/* ===== CŨ: nguyên văn trước 11/10/2026 ===== */
$cu = function ( $user_id ) use ( $wpdb, $prefix ) {
    $today = date( 'Y-m-d', strtotime( sitetop_current_time() ) );
    $r = array();
    $chart=array();
    for($i=29;$i>=0;$i--){
        $d=date('Y-m-d',strtotime("-{$i} days",strtotime(sitetop_current_time())));
        $v=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}shortlink_visits v INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id=kc.id WHERE kc.customer_id=%d AND (v.step='verified' OR v.customer_paid=1) AND DATE(v.created_at)=%s",$user_id,$d));
        $spent=(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(ABS(SUM(amount)),0) FROM {$prefix}customer_transactions WHERE customer_id=%d AND type='campaign_view' AND amount<0 AND DATE(created_at)=%s",$user_id,$d));
        $chart[]=array('date'=>date('d/m',strtotime($d)),'views'=>$v,'spent'=>$spent);
    }
    $r['chart'] = $chart;
    $r['hom_nay'] = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}shortlink_visits v INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id=kc.id WHERE kc.customer_id=%d AND (v.step='verified' OR v.customer_paid=1) AND DATE(v.created_at)=%s", $user_id, $today ) );
    $camp_per = 20; $camp_offset = 0;
    $my_campaigns = $wpdb->get_results( $wpdb->prepare(
        "SELECT kc.*, co.task_type,
                (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND (step='verified' OR customer_paid=1)) as total_completed,
                (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND (step='verified' OR customer_paid=1) AND DATE(created_at)=%s) as today_views
         FROM {$prefix}keyword_campaigns kc
         LEFT JOIN {$prefix}customer_orders co ON kc.order_id = co.id
         WHERE kc.customer_id = %d AND kc.status != 'deleted'
         ORDER BY kc.created_at DESC LIMIT %d OFFSET %d", $today, $user_id, $camp_per, $camp_offset ) );
    foreach ( $my_campaigns as $c ) $r['camp'][ (int) $c->id ] = array( (int) $c->total_completed, (int) $c->today_views );
    $limit = 10; $offset = 0;
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT kc.*, co.task_type,
                (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND (step='verified' OR customer_paid=1)) as total_completed,
                (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND (step='verified' OR customer_paid=1) AND DATE(created_at)=%s) as today_views
         FROM {$prefix}keyword_campaigns kc
         LEFT JOIN {$prefix}customer_orders co ON kc.order_id = co.id
         WHERE kc.customer_id = %d
         ORDER BY kc.created_at DESC LIMIT %d OFFSET %d",
        $today, $user_id, $limit, $offset
    ) );
    foreach ( $rows as $c ) $r['them'][ (int) $c->id ] = array( (int) $c->total_completed, (int) $c->today_views );
    return $r;
};

/* ===== MỚI: hàm trợ giúp + đúng các đoạn mã trong trang ===== */
eval( file_get_contents( $argv[1] ) );
$doan = json_decode( file_get_contents( $argv[2] ), true );
$moi = function ( $user_id ) use ( $wpdb, $prefix, $doan ) {
    $today = date( 'Y-m-d', strtotime( sitetop_current_time() ) );
    $r = array();
    eval( $doan['bd'] );                          // $chart = sitetop_bieu_do_khach( $user_id, strtotime( sitetop_current_time() ) );
    $r['chart'] = $chart;
    eval( $doan['homnay'] );                      // $_kh_cids … $today_views = …
    $r['hom_nay'] = $today_views;
    $camp_per = 20; $camp_offset = 0;
    eval( $doan['camp'] );                        // $my_campaigns = … ; sitetop_gan_so_view_camp( $my_campaigns, $today );
    foreach ( $my_campaigns as $c ) $r['camp'][ (int) $c->id ] = array( $c->total_completed, $c->today_views );
    $limit = 10; $offset = 0;
    eval( $doan['them'] );                        // $rows = … ; sitetop_gan_so_view_camp( $rows, $today );
    foreach ( $rows as $c ) $r['them'][ (int) $c->id ] = array( $c->total_completed, $c->today_views );
    return $r;
};

$cau_moi = array();
foreach ( array( 7, 8, 9, 55 ) as $uid ) {
    $wpdb->so_cau = 0; $a = $cu( $uid ); $so_cu = $wpdb->so_cau;
    $wpdb->so_cau = 0; $wpdb->cau = array(); $b = $moi( $uid ); $so_moi = $wpdb->so_cau; $cau_moi = array_merge( $cau_moi, $wpdb->cau );
    $out['so'][ $uid ] = array( 'cu' => $a, 'moi' => $b, 'so_cau_cu' => $so_cu, 'so_cau_moi' => $so_moi );
}
/* Giữ biểu đồ: gọi lại cùng ngày không chạy truy vấn nào; sang ngày khác thì tính lại. */
$wpdb->so_cau = 0; $c1 = sitetop_bieu_do_khach( 7, strtotime( $BAY_GIO ) ); $out['giu_lan2'] = $wpdb->so_cau;
$out['giu_bang'] = ( $c1 === $out['so'][7]['moi']['chart'] );
$wpdb->so_cau = 0; $c2 = sitetop_bieu_do_khach( 7, strtotime( '2026-10-12 08:00:00' ) ); $out['ngay_moi_cau'] = $wpdb->so_cau;
$out['ngay_moi_cuoi'] = end( $c2 )['date'];
$out['khoa'] = array_keys( $T );
/* Câu mới có thật sự lọc khoảng created_at (không còn DATE(...)=ngày) */
$out['cau_moi_co_date_bang'] = 0;
$out['so_cau_moi_tong'] = count( $cau_moi );
foreach ( $cau_moi as $q ) if ( preg_match( "/DATE\((v\.)?created_at\)\s*=\s*'/", $q ) ) $out['cau_moi_co_date_bang']++;
echo json_encode( $out );
BANTHU;

$__tk_f1 = sys_get_temp_dir() . '/st-tk-ham-' . getmypid() . '.php';
$__tk_f2 = sys_get_temp_dir() . '/st-tk-doan-' . getmypid() . '.json';
$__tk_f3 = sys_get_temp_dir() . '/st-tk-ban-' . getmypid() . '.php';
file_put_contents( $__tk_f1, $__tk_ham );
file_put_contents( $__tk_f2, json_encode( array( 'bd' => $__tk_d_bd, 'homnay' => $__tk_d_homnay, 'camp' => $__tk_d_camp, 'them' => $__tk_d_them ) ) );
file_put_contents( $__tk_f3, $__tk_ban );
$__tk_r = (string) shell_exec( 'php ' . escapeshellarg( $__tk_f3 ) . ' ' . escapeshellarg( $__tk_f1 ) . ' ' . escapeshellarg( $__tk_f2 ) . ' 2>&1' );
@unlink( $__tk_f1 ); @unlink( $__tk_f2 ); @unlink( $__tk_f3 );
$TK = json_decode( trim( $__tk_r ), true );
assert_true( is_array( $TK ), 'Chay duoc ma trang khach tren SQLite. Ra: ' . substr( $__tk_r, 0, 700 ) );

if ( is_array( $TK ) ) {
    foreach ( $TK['so'] as $uid => $x ) {
        $a = $x['cu']; $b = $x['moi'];
        assert_equals( $a['chart'], $b['chart'], "Khach $uid: bieu do 30 ngay moi = cu tung o (ngay, views, spent)" );
        assert_equals( $a['hom_nay'], $b['hom_nay'], "Khach $uid: the 'Hom nay' moi = cu" );
        assert_equals( $a['camp'] ?? array(), $b['camp'] ?? array(), "Khach $uid: total_completed + today_views tung camp (trang) moi = cu" );
        assert_equals( $a['them'] ?? array(), $b['them'] ?? array(), "Khach $uid: total_completed + today_views tung camp (xem them) moi = cu" );
    }
    $x7 = $TK['so'][7];
    $co_so = 0; foreach ( $x7['cu']['chart'] as $o ) if ( $o['views'] > 0 && $o['spent'] > 0 ) $co_so++;
    assert_true( $co_so >= 20 && $x7['cu']['hom_nay'] > 0 && count( $x7['cu']['camp'] ) === 3 && count( $x7['cu']['them'] ) === 4, 'Du lieu thu du day (khong so hai mang rong): ngay co so=' . $co_so . ', hom nay=' . $x7['cu']['hom_nay'] );
    /* Mới: biểu đồ 3 (id camp + 2 gộp) + thẻ hôm nay 1 + danh sách 3 + xem thêm 3 = 10, không phụ thuộc số camp / số ngày. */
    assert_true( $x7['so_cau_cu'] >= 63 && $x7['so_cau_moi'] <= 10, 'So truy van khach 7: cu ' . $x7['so_cau_cu'] . ' → moi ' . $x7['so_cau_moi'] );
    assert_true( $TK['giu_lan2'] === 0 && $TK['giu_bang'] === true, 'Bieu do: goi lai cung ngay → 0 truy van, cung ket qua' );
    assert_true( $TK['ngay_moi_cau'] >= 1 && $TK['ngay_moi_cuoi'] === '12/10', 'Bieu do: sang ngay moi → tinh lai, cot cuoi la ngay moi' );
    assert_true( in_array( 'sitetop_sl_kh_bd_7_2026-10-11', $TK['khoa'], true ), 'Khoa giu bieu do theo khach + ngay, tien to sitetop_sl_ (cron don het han)' );
    assert_true( $TK['so_cau_moi_tong'] > 0 && $TK['cau_moi_co_date_bang'] === 0, 'Cac cau moi (' . $TK['so_cau_moi_tong'] . ' cau) khong con DATE(created_at)=ngay (bo chi muc); dem duoc: ' . $TK['cau_moi_co_date_bang'] );
}

assert_true( strpos( $__tk_pg, 'DATE(v.created_at)=%s' ) === false && strpos( $__tk_pg, 'DATE(created_at)=%s' ) === false, 'page-customer-dashboard: khong con loc DATE(created_at)=%s' );
assert_true( strpos( $__tk_lm, 'DATE(created_at)=%s' ) === false, 'customer-load-more: khong con loc DATE(created_at)=%s' );
