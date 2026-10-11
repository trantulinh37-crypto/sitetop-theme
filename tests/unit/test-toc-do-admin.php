<?php
/* TỐC ĐỘ TRANG ADMIN (11/10/2026, chủ site: "tối ưu lại tốc độ load trang admin .net").
   Sửa cách TÍNH, không sửa con số. Canh ba điều:
   1. sitetop_khoang_ngay(): DATE(created_at) = ngày  ⇔  created_at ∈ [ngày 00:00:00, ngày kế 00:00:00) — chạy SQL THẬT
      (SQLite trong bộ nhớ) với các lượt sát mép nửa đêm, cuối tháng, cuối năm; câu cũ và câu mới phải đếm y hệt.
      "View hôm nay" của tab Chiến dịch: truy vấn con từng camp (cũ) và truy vấn gộp cả trang (mới) ra cùng số từng camp.
   2. sitetop_so_lieu_admin(): lần đầu tính, lần sau trả số đã giữ (không chạy lại), ?lam_moi=1 tính lại ngay, đúng TTL.
   3. Vị trí sửa trong các tab: không còn DATE(created_at)=%s ở những chỗ đã đổi; cột TIỀN tab Người dùng vẫn tính tươi. */

$__td_goc = dirname( __DIR__, 2 );
$__td_ad  = (string) file_get_contents( $__td_goc . '/includes/admin-dashboard.php' );

$__td_than = function ( $ma, $ten ) {
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
$__td_ham = $__td_than( $__td_ad, 'sitetop_khoang_ngay' ) . "\n" . $__td_than( $__td_ad, 'sitetop_so_lieu_admin' );
assert_true( strpos( $__td_ham, 'function sitetop_khoang_ngay' ) !== false && strpos( $__td_ham, 'function sitetop_so_lieu_admin' ) !== false, 'Tim thay hai ham tro giup toc do trong admin-dashboard.php' );

$__td_ban = <<<'BANTHU'
<?php
$T = array(); $TTL = array(); $SO_LAN = 0;
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['T'] ) ? $GLOBALS['T'][ $k ] : false; }
function set_transient( $k, $v, $s ) { $GLOBALS['T'][ $k ] = $v; $GLOBALS['TTL'][ $k ] = $s; return true; }
eval( file_get_contents( $argv[1] ) );
$out = array();
foreach ( array( '2026-10-11', '2026-10-31', '2026-12-31', '2026-02-28', '2028-02-28', '2026-10-11 15:30:00' ) as $n ) $out['khoang'][ $n ] = sitetop_khoang_ngay( $n );

/* SQL thật: lượt sát mép nửa đêm / cuối tháng / cuối năm. */
$db = new PDO( 'sqlite::memory:' );
$db->exec( "CREATE TABLE v (id INTEGER PRIMARY KEY, campaign_id INT, created_at TEXT, step TEXT, customer_paid INT)" );
$moc = array( '2026-10-10 23:59:59', '2026-10-11 00:00:00', '2026-10-11 00:00:01', '2026-10-11 12:00:00', '2026-10-11 23:59:59',
              '2026-10-12 00:00:00', '2026-10-31 23:59:59', '2026-11-01 00:00:00', '2026-12-31 23:59:59', '2027-01-01 00:00:00' );
$buoc = array( array( 'verified', 0 ), array( 'started', 1 ), array( 'started', 0 ), array( 'code_shown', 0 ), array( 'rejected', 1 ) );
$i = 0;
foreach ( $moc as $m ) foreach ( $buoc as $b ) foreach ( array( 5, 6, 7 ) as $c ) {
    $i++; if ( $i % 4 === 0 ) continue; // lệch đều để mỗi camp có số khác nhau
    $st = $db->prepare( 'INSERT INTO v (campaign_id, created_at, step, customer_paid) VALUES (?,?,?,?)' );
    $st->execute( array( $c, $m, $b[0], $b[1] ) );
}
$dem = function ( $sql, $a ) use ( $db ) { $s = $db->prepare( $sql ); $s->execute( $a ); return (int) $s->fetchColumn(); };
foreach ( array( '2026-10-10', '2026-10-11', '2026-10-12', '2026-10-31', '2026-11-01', '2026-12-31', '2027-01-01' ) as $n ) {
    $k = sitetop_khoang_ngay( $n );
    $out['dem'][ $n ] = array(
        'cu'  => $dem( "SELECT COUNT(*) FROM v WHERE (step='verified' OR customer_paid=1) AND DATE(created_at)=?", array( $n ) ),
        'moi' => $dem( "SELECT COUNT(*) FROM v WHERE (step='verified' OR customer_paid=1) AND created_at >= ? AND created_at < ?", $k ),
    );
    $cu = array();
    foreach ( array( 5, 6, 7, 8 ) as $c ) $cu[ $c ] = $dem( "SELECT COUNT(*) FROM v WHERE campaign_id=? AND (step='verified' OR customer_paid=1) AND DATE(created_at)=?", array( $c, $n ) );
    $moi = array( 5 => 0, 6 => 0, 7 => 0, 8 => 0 );
    $s = $db->prepare( "SELECT campaign_id, COUNT(*) AS n FROM v WHERE created_at >= ? AND created_at < ? AND (step='verified' OR customer_paid=1) AND campaign_id IN (5,6,7,8) GROUP BY campaign_id" );
    $s->execute( $k );
    foreach ( $s->fetchAll( PDO::FETCH_ASSOC ) as $r ) $moi[ (int) $r['campaign_id'] ] = (int) $r['n'];
    $out['camp'][ $n ] = array( 'cu' => $cu, 'moi' => $moi );
}

/* Giữ số liệu. */
$tinh = function () { $GLOBALS['SO_LAN']++; return array( 'so' => 100 + $GLOBALS['SO_LAN'] ); };
$_GET = array(); $_POST = array();
$a = sitetop_so_lieu_admin( 'thu', 600, $tinh );
$b = sitetop_so_lieu_admin( 'thu', 600, $tinh );
$_GET['lam_moi'] = '1';
$c = sitetop_so_lieu_admin( 'thu', 600, $tinh );
$_GET = array();
$d = sitetop_so_lieu_admin( 'thu', 600, $tinh );
$e = sitetop_so_lieu_admin( 'khong', 300, function () { $GLOBALS['SO_LAN']++; return 0; } ); // số 0 vẫn được giữ (khác false)
$f = sitetop_so_lieu_admin( 'khong', 300, function () { $GLOBALS['SO_LAN']++; return 0; } );
$out['giu'] = array( 'a' => $a, 'b' => $b, 'c' => $c, 'd' => $d, 'e' => $e, 'f' => $f, 'so_lan' => $SO_LAN, 'khoa' => array_keys( $T ), 'ttl' => $TTL );
echo json_encode( $out );
BANTHU;

$__td_f1 = sys_get_temp_dir() . '/st-toc-do-ham-' . getmypid() . '.php';
$__td_f2 = sys_get_temp_dir() . '/st-toc-do-ban-' . getmypid() . '.php';
file_put_contents( $__td_f1, $__td_ham );
file_put_contents( $__td_f2, $__td_ban );
$__td_r = (string) shell_exec( 'php ' . escapeshellarg( $__td_f2 ) . ' ' . escapeshellarg( $__td_f1 ) . ' 2>&1' );
@unlink( $__td_f1 ); @unlink( $__td_f2 );
$TD = json_decode( trim( $__td_r ), true );
assert_true( is_array( $TD ), 'Chay duoc hai ham toc do trong tien trinh rieng. Ra: ' . substr( $__td_r, 0, 500 ) );

if ( is_array( $TD ) ) {
    assert_equals( array(
        '2026-10-11'          => array( '2026-10-11 00:00:00', '2026-10-12 00:00:00' ),
        '2026-10-31'          => array( '2026-10-31 00:00:00', '2026-11-01 00:00:00' ),
        '2026-12-31'          => array( '2026-12-31 00:00:00', '2027-01-01 00:00:00' ),
        '2026-02-28'          => array( '2026-02-28 00:00:00', '2026-03-01 00:00:00' ),
        '2028-02-28'          => array( '2028-02-28 00:00:00', '2028-02-29 00:00:00' ),
        '2026-10-11 15:30:00' => array( '2026-10-11 00:00:00', '2026-10-12 00:00:00' ),
    ), $TD['khoang'], 'sitetop_khoang_ngay: [ngay 00:00:00, ngay ke 00:00:00) — qua cuoi thang, cuoi nam, nam nhuan; nhan ca chuoi co gio' );

    $lech = array(); $co_so = 0;
    foreach ( $TD['dem'] as $n => $x ) { if ( $x['cu'] !== $x['moi'] ) $lech[] = $n; if ( $x['cu'] > 0 ) $co_so++; }
    assert_true( ! $lech && $co_so >= 6, 'Dem ca ngay: DATE(created_at)=ngay (cu) va khoang created_at (moi) ra Y HET o moi ngay sat mep. Lech: ' . json_encode( $lech ) . ' Ra: ' . json_encode( $TD['dem'] ) );

    $lech = array(); $co_so = 0;
    foreach ( $TD['camp'] as $n => $x ) { if ( $x['cu'] != $x['moi'] ) $lech[] = $n; $co_so += array_sum( $x['cu'] ); }
    assert_true( ! $lech && $co_so > 0, 'View hom nay tung camp: truy van con (cu) va truy van gop ca trang (moi) ra cung so tung camp, camp khong co luot = 0. Lech: ' . json_encode( $lech ) );

    $G = $TD['giu'];
    assert_equals( array( 'so' => 101 ), $G['a'], 'Lan dau: tinh that' );
    assert_equals( array( 'so' => 101 ), $G['b'], 'Lan hai: tra so da giu, KHONG tinh lai' );
    assert_equals( array( 'so' => 102 ), $G['c'], '?lam_moi=1: tinh lai ngay' );
    assert_equals( array( 'so' => 102 ), $G['d'], 'Sau lam moi: giu so moi' );
    assert_true( $G['e'] === 0 && $G['f'] === 0 && $G['so_lan'] === 3, 'So 0 van duoc giu (khong tinh lai moi lan). so_lan=' . $G['so_lan'] );
    assert_equals( array( 'sitetop_sl_thu', 'sitetop_sl_khong' ), $G['khoa'], 'Khoa transient co tien to sitetop_sl_ (cron don transient het han se don)' );
    assert_equals( array( 'sitetop_sl_thu' => 600, 'sitetop_sl_khong' => 300 ), $G['ttl'], 'Dung TTL truyen vao' );
}

/* ---- Vị trí sửa trong các tab (đọc đúng chỗ, không chỉ dò tên hàm) ---- */
$__td_ov = (string) file_get_contents( $__td_goc . '/includes/admin/tabs/tab-overview.php' );
$__td_ca = (string) file_get_contents( $__td_goc . '/includes/admin/tabs/tab-campaigns.php' );
$__td_vi = (string) file_get_contents( $__td_goc . '/includes/admin/tabs/tab-visits.php' );
$__td_us = (string) file_get_contents( $__td_goc . '/includes/admin/tabs/tab-users.php' );
$__td_cu = (string) file_get_contents( $__td_goc . '/includes/admin/tabs/tab-customers.php' );
$__td_so = (string) file_get_contents( $__td_goc . '/includes/admin/tabs/tab-sources.php' );

foreach ( array( 'tab-overview' => $__td_ov, 'tab-campaigns' => $__td_ca ) as $ten => $ma ) {
    assert_true( strpos( $ma, 'DATE(created_at)=%s' ) === false, $ten . ': khong con loc DATE(created_at)=%s (bo chi muc)' );
}
assert_true( strpos( $__td_ov, "sitetop_so_lieu_admin( 'ov_tong_view', 600" ) !== false
          && strpos( $__td_ov, "sitetop_so_lieu_admin( 'ov_khach_tra', 600" ) !== false
          && strpos( $__td_ov, "sitetop_so_lieu_admin( 'ov_user_kiem', 600" ) !== false, 'Tong quan: ba so toan thoi gian giu 10 phut' );
assert_true( strpos( $__td_ov, '$today_verified = (int) $wpdb->get_var' ) !== false, 'Tong quan: so HOM NAY van tinh tuoi (khong giu)' );

$__td_rows = substr( $__td_ca, (int) strpos( $__td_ca, '$rows = $wpdb->get_results($wpdb->prepare(' ), 600 );
assert_true( strpos( $__td_rows, 'shortlink_visits' ) === false, 'Chien dich: cau lay danh sach camp KHONG con truy van con dem visits tung camp' );
assert_true( strpos( $__td_ca, "WHERE created_at >= %s AND created_at < %s AND (step='verified' OR customer_paid=1)\n           AND campaign_id IN (" ) !== false
          && strpos( $__td_ca, '$_kn_camp = sitetop_khoang_ngay( $today_camp_date );' ) !== false, 'Chien dich: truy van gop dung DIEU KIEN cu (verified hoac khach da tra) trong khoang hom nay' );
assert_true( strpos( $__td_ca, "AND created_at >= %s AND created_at < %s\", \$_kn_camp2[0], \$_kn_camp2[1]" ) !== false, 'Chien dich: the "Hoan thanh hom nay" loc khoang ngay' );
assert_true( strpos( $__td_ov, "WHERE (step='verified' OR customer_paid=1) AND created_at >= %s AND created_at < %s\", \$_kn_hom_nay[0], \$_kn_hom_nay[1]" ) !== false, 'Tong quan: so hom nay loc khoang ngay, cung dieu kien' );
assert_true( strpos( $__td_ca, 'foreach ( $rows as $row ) $row->today_views = $_ca_hom_nay[ (int) $row->id ] ?? 0;' ) !== false
          && strpos( $__td_ca, 'intval($row->today_views ?? 0)' ) !== false, 'Chien dich: today_views gan lai tu truy van gop, cot hien thi doc dung truong' );

assert_true( strpos( $__td_vi, "if ( \$where === 'WHERE 1=1' && empty( \$args ) ) {" ) !== false
          && strpos( $__td_vi, "\$stats = sitetop_so_lieu_admin( 'vi_the', 300" ) !== false
          && strpos( $__td_vi, '$stats->in_progress = (int) $wpdb->get_var' ) !== false, 'Visits: the thong ke giu 5 phut, "Dang lam" tinh tuoi; khong loc thi tong = the Tong' );
assert_true( (int) strpos( $__td_vi, "\$stats = sitetop_so_lieu_admin( 'vi_the'" ) < (int) strpos( $__td_vi, '$total = (int) $stats->total;' ), 'Visits: the thong ke tinh TRUOC khi dung lam tong' );
assert_true( substr_count( $__td_vi, 'SELECT COUNT(*) as total,' ) === 1, 'Visits: khong con cau thong ke thu hai (quet ca bang lan nua)' );

$__td_dq = substr( $__td_us, (int) strpos( $__td_us, '$data_query = "SELECT' ), 1800 );
$__td_dq = substr( $__td_dq, 0, (int) strpos( $__td_dq, '$data_args' ) );
assert_true( strpos( $__td_dq, 'shortlink_visits' ) === false, 'Nguoi dung: cau du lieu chinh KHONG con dem visits tung user' );
foreach ( array(
    "(SELECT COALESCE(SUM(amount),0) FROM {\$prefix}transactions WHERE user_id = u.ID AND type IN ('shortlink_reward','earn')) as earned",
    "(SELECT COALESCE(SUM(amount),0) FROM {\$prefix}transactions WHERE user_id = u.ID AND type='withdraw' AND (reference_type IS NULL OR reference_type <> 'withdrawal')) as tru_tay",
    "(SELECT COALESCE(SUM(amount),0) FROM {\$prefix}withdrawals WHERE user_id = u.ID AND status IN ('completed','cancelled')) as withdrawn",
    "(SELECT COALESCE(SUM(amount),0) FROM {\$prefix}withdrawals WHERE user_id = u.ID AND status IN ('pending','approved')) as pending_withdrawal",
) as $cot ) {
    assert_true( strpos( $__td_dq, $cot ) !== false, 'Nguoi dung: cot tien van tinh tuoi, nguyen cau cu: ' . substr( $cot, -22 ) );
}
assert_true( strpos( $__td_us, "sitetop_so_lieu_admin( 'us_xong_'" ) !== false && strpos( $__td_us, "step='verified' AND reward_paid=1" ) !== false
          && strpos( $__td_us, '$row->completed = (int)' ) !== false, 'Nguoi dung: cot Hoan thanh dem gop ca trang, cung dieu kien verified + reward_paid=1' );
foreach ( array( 'tab-users' => $__td_us, 'tab-customers' => $__td_cu, 'tab-sources' => $__td_so ) as $ten => $ma ) {
    assert_true( strpos( $ma, "update_meta_cache( 'user'," ) !== false, $ten . ': nap meta ca trang mot luot' );
}
