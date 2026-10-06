<?php
/* Ô CHỌN KHÁCH HÀNG CỦA ADMIN KHÔNG HIỆN KHÁCH ĐÃ XOÁ — chủ site 06/10/2026 tối:
   "Khách hàng đã xoá tài khoản rồi thì không cần hiện đâu nhé" (tab Nạp tiền) và "phần chọn khách hàng này
   cũng vậy" (form Tạo chiến dịch). Xoá khách là xoá MỀM (chỉ gắn meta sitetop_customer_deleted, giữ WP user để
   còn sổ tiền), nên câu "mọi user có vai customer" vẫn kéo khách đã xoá vào ô chọn.
   Test CHẠY THẬT: gọi hàm với wpdb giả để bắt câu SQL, rồi chạy đúng câu SQL đó trên SQLite có dữ liệu mẫu. */

$__ck_goc = dirname( __DIR__, 2 );
$__ck_cm  = (string) file_get_contents( $__ck_goc . '/includes/customer-management.php' );
$__ck_dep = (string) file_get_contents( $__ck_goc . '/includes/admin/tabs/tab-deposits.php' );
$__ck_cam = (string) file_get_contents( $__ck_goc . '/includes/admin/tabs/tab-campaigns.php' );

assert_true( preg_match( '#(function sitetop_khach_hang_cho_o_chon\(\) \{.*?\n\})#s', $__ck_cm, $__ck_m ) === 1,
    'Lay duoc ham danh sach khach cho o chon' );

/* ---- 1. Hai ô chọn phải gọi hàm chung, không còn câu SQL thô lấy mọi khách ---- */
assert_equals( 1, substr_count( $__ck_dep, 'sitetop_khach_hang_cho_o_chon()' ), 'Tab Nap tien dung ham chung' );
assert_equals( 1, substr_count( $__ck_cam, 'sitetop_khach_hang_cho_o_chon()' ), 'Form Tao chien dich dung ham chung' );
assert_false( strpos( $__ck_dep, "LIKE '%customer%' ORDER BY u.user_login" ), 'Tab Nap tien khong con cau SQL tho' );
assert_false( strpos( $__ck_cam, "LIKE '%customer%' ORDER BY u.user_login" ), 'Form Tao chien dich khong con cau SQL tho' );

/* ---- 2. Chạy thật hàm → bắt SQL → chạy SQL trên SQLite ---- */
$__ck_ban = <<<'BANTHU'
<?php
class FakeDb {
    public $prefix = 'wp_'; public $users = 'wp_users'; public $usermeta = 'wp_usermeta'; public $sql = '';
    function prepare($q, ...$a){
        foreach($a as $v) $q = preg_replace('/%d|%s/', is_int($v) ? (string)$v : "'" . str_replace("'", "''", (string)$v) . "'", $q, 1);
        return $q;
    }
    function get_results($q){ $this->sql = $q; return array(); }
}
$wpdb = new FakeDb();
__HAM__
sitetop_khach_hang_cho_o_chon();
$out = array('sql' => $wpdb->sql, 'sqlite' => 'khong co PDO sqlite');
if ( class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true) ) {
    $db = new PDO('sqlite::memory:');
    $db->exec("CREATE TABLE wp_users (ID INTEGER PRIMARY KEY, user_login TEXT)");
    $db->exec("CREATE TABLE wp_usermeta (umeta_id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, meta_key TEXT, meta_value TEXT)");
    $db->exec("INSERT INTO wp_users VALUES (1,'zeta_con_song'),(2,'alpha_da_xoa'),(3,'beta_con_song'),(4,'user_thuong'),(5,'gamma_bi_cam')");
    $cap = 'a:1:{s:8:\"customer\";b:1;}';
    $db->exec("INSERT INTO wp_usermeta (user_id,meta_key,meta_value) VALUES
        (1,'wp_capabilities','$cap'),
        (2,'wp_capabilities','$cap'),(2,'sitetop_customer_deleted','1'),(2,'sitetop_customer_deleted_at','2026-10-06 20:00:00'),
        (3,'wp_capabilities','$cap'),
        (4,'wp_capabilities','a:1:{s:10:\"subscriber\";b:1;}'),
        (5,'wp_capabilities','$cap'),(5,'customer_banned','1')");
    $st = $db->query($wpdb->sql);
    $out['sqlite'] = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : array('loi' => $db->errorInfo());
}
echo json_encode($out);
BANTHU;
$__ck_ban = str_replace( '__HAM__', $__ck_m[1], $__ck_ban );
$__ck_f = sys_get_temp_dir() . '/st-chon-khach-' . getmypid() . '.php';
file_put_contents( $__ck_f, $__ck_ban );
$__ck_r = json_decode( (string) shell_exec( 'php ' . escapeshellarg( $__ck_f ) . ' 2>&1' ), true );
@unlink( $__ck_f );
assert_true( is_array( $__ck_r ) && ! empty( $__ck_r['sql'] ), 'Chay duoc ham trong tien trinh rieng va bat duoc SQL' );
$__ck_sql = (string) ( $__ck_r['sql'] ?? '' );
assert_true( stripos( $__ck_sql, "meta_key = 'sitetop_customer_deleted'" ) !== false, 'SQL co noi voi dau da xoa' );
assert_true( stripos( $__ck_sql, 'umeta_id IS NULL' ) !== false, 'SQL chi lay dong KHONG co dau da xoa (y het tab Khach hang)' );
assert_true( stripos( $__ck_sql, "LIKE '%customer%'" ) !== false, 'SQL van loc dung vai customer' );
assert_true( stripos( $__ck_sql, 'ORDER BY u.user_login' ) !== false, 'Van xep theo ten dang nhap nhu cu' );

if ( is_array( $__ck_r['sqlite'] ?? null ) && ! isset( $__ck_r['sqlite']['loi'] ) ) {
    $__ck_logins = array_column( $__ck_r['sqlite'], 'user_login' );
    assert_equals( 'beta_con_song,gamma_bi_cam,zeta_con_song', implode( ',', $__ck_logins ),
        'SQLite: khach da xoa BIEN MAT; khach bi cam + khach thuong VAN HIEN; user thuong khong lot; xep theo ten' );
    assert_false( in_array( 'alpha_da_xoa', $__ck_logins, true ), 'Khach da xoa khong co trong o chon' );
} else {
    assert_true( false, 'SQLite khong chay duoc cau SQL: ' . json_encode( $__ck_r['sqlite'] ?? null ) );
}
