<?php
/* ADMIN CỘNG / TRỪ SỐ DƯ USER — chủ site yêu cầu 06/10/2026.

   Yêu cầu gắt nhất của chủ site: "phải độc lập hoàn toàn với số liệu/số dư của Khách hàng".
   Nên test không chỉ xem kết quả — nó GHI LẠI MỌI BẢNG mà hàm đụng vào, rồi khẳng định
   không có bảng customer_* nào bị chạm.

   Hai sổ tiền của hệ thống:
     User   sitetop_transactions / sitetop_withdrawals / sitetop_user_balance
     Khách  sitetop_customer_transactions / sitetop_customer_deposits / sitetop_customer_balance */

$__sd_goc = dirname( __DIR__, 2 );
$__sd_ad  = (string) file_get_contents( $__sd_goc . '/includes/admin-dashboard.php' );
$__sd_ver = (string) file_get_contents( $__sd_goc . '/includes/shortlink-verification.php' );
$__sd_tu  = (string) file_get_contents( $__sd_goc . '/includes/admin/tabs/tab-users.php' );

assert_true( preg_match( '#(function sitetop_ajax_admin_sodu_user\(\) \{.*?\n\})#s', $__sd_ad, $__sd_m ) === 1,
    'Lay duoc ham xu ly tu ma nguon' );

$__sd_ban = <<<'BANTHU'
<?php
define('SITETOP_PREFIX','sitetop_');
$BANG = array();            // mọi bảng bị chạm
$SO   = array();            // sổ giao dịch giả
$DONG_BO = 0;
class Thoat extends Exception { public $data; public $ok;
    function __construct($ok,$d){ $this->ok=$ok; $this->data=$d; parent::__construct('thoat'); } }
function wp_send_json_error($d=null){ throw new Thoat(false,$d); }
function wp_send_json_success($d=null){ throw new Thoat(true,$d); }
function check_ajax_referer($a,$b){ return true; }
function current_user_can($c){ return $GLOBALS['LA_ADMIN']; }
function absint($v){ return abs((int)$v); }
function sanitize_text_field($v){ return trim(strip_tags((string)$v)); }
function get_userdata($id){ return isset($GLOBALS['NGUOI'][$id]) ? (object)array('ID'=>$id,'user_login'=>$GLOBALS['NGUOI'][$id]) : false; }
function wp_get_current_user(){ return (object)array('ID'=>387,'user_login'=>'Admin'); }
function sitetop_format_money($v){ return number_format((float)$v,0,',','.') . 'đ'; }
/* Chế độ tiền user (06/10/2026): khung này kiểm hành vi VNĐ — công tắc USD tắt. */
function sitetop_che_do_usd(){ return false; }
function sitetop_format_tien_user($v){ return sitetop_format_money($v); }
function sitetop_current_time(){ return '2026-10-06 10:00:00'; }
function sitetop_sync_user_balance($uid){ $GLOBALS['DONG_BO']++; }
function ghi_bang($b){ $GLOBALS['BANG'][$b] = true; }
/* Số dư user tính ĐÚNG công thức thật: earn/shortlink_reward trừ đi rút và trừ tay. */
function sitetop_get_user_balance_amount($uid){
    $cong = 0; $tru = 0;
    foreach ($GLOBALS['SO'] as $d) {
        if ((int)$d['user_id'] !== (int)$uid) continue;
        if (in_array($d['type'], array('shortlink_reward','earn'), true)) $cong += $d['amount'];
        if ($d['type'] === 'withdraw' && (empty($d['reference_type']) || $d['reference_type'] !== 'withdrawal')) $tru += abs($d['amount']);
    }
    return max(0, $cong - $tru - ($GLOBALS['DA_RUT'][$uid] ?? 0));
}
class FakeDb {
    public $prefix = 'wpgd_'; public $insert_id = 0;
    function insert($bang,$d){ ghi_bang($bang); $GLOBALS['SO'][] = $d; $this->insert_id = count($GLOBALS['SO']); return 1; }
    function update($bang,$d,$w){ ghi_bang($bang); return 1; }
    function get_var($q){ ghi_bang('get_var'); return 0; }
    function prepare($q, ...$a){ return $q; }
}
$wpdb = new FakeDb();
__HAM__
function thu($post, $la_admin = true){
    $GLOBALS['BANG'] = array(); $GLOBALS['DONG_BO'] = 0; $GLOBALS['LA_ADMIN'] = $la_admin;
    $_POST = $post;
    $truoc = count($GLOBALS['SO']);
    try { sitetop_ajax_admin_sodu_user(); return array('ok'=>null); }
    catch (Thoat $e) {
        return array('ok'=>$e->ok, 'data'=>$e->data, 'bang'=>array_keys($GLOBALS['BANG']),
                     'them_dong'=>count($GLOBALS['SO'])-$truoc, 'dong_bo'=>$GLOBALS['DONG_BO'],
                     'dong_cuoi'=>end($GLOBALS['SO']) ?: null);
    }
}
$NGUOI = array(5=>'user_thuong'); $DA_RUT = array();
$kq = array();
$kq['cong']       = thu(array('user_id'=>5,'huong'=>'cong','so_tien'=>100000,'ly_do'=>'bù lượt lỗi'));
$kq['so_du_1']    = sitetop_get_user_balance_amount(5);
$kq['tru']        = thu(array('user_id'=>5,'huong'=>'tru','so_tien'=>30000,'ly_do'=>'thu hồi nhầm'));
$kq['so_du_2']    = sitetop_get_user_balance_amount(5);
$kq['tru_qua']    = thu(array('user_id'=>5,'huong'=>'tru','so_tien'=>999999,'ly_do'=>'thử vượt'));
$kq['so_du_3']    = sitetop_get_user_balance_amount(5);
$kq['so_0']       = thu(array('user_id'=>5,'huong'=>'cong','so_tien'=>0,'ly_do'=>'không có tiền'));
$kq['qua_tran']   = thu(array('user_id'=>5,'huong'=>'cong','so_tien'=>60000000,'ly_do'=>'gõ thừa số 0'));
$kq['dung_tran']  = thu(array('user_id'=>5,'huong'=>'cong','so_tien'=>50000000,'ly_do'=>'đúng trần'));
$kq['thieu_ly_do']= thu(array('user_id'=>5,'huong'=>'cong','so_tien'=>1000,'ly_do'=>'ok'));
$kq['khong_co_user']= thu(array('user_id'=>999,'huong'=>'cong','so_tien'=>1000,'ly_do'=>'ma nào'));
$kq['khong_quyen'] = thu(array('user_id'=>5,'huong'=>'cong','so_tien'=>1000,'ly_do'=>'không phải admin'), false);
$kq['huong_la']   = thu(array('user_id'=>5,'huong'=>'xoá_sạch','so_tien'=>1000,'ly_do'=>'hướng lạ'));
echo json_encode($kq, JSON_UNESCAPED_UNICODE);
BANTHU;
$__sd_ban = str_replace( '__HAM__', $__sd_m[1], $__sd_ban );
$__sd_f = sys_get_temp_dir() . '/st-sodu-' . getmypid() . '.php';
file_put_contents( $__sd_f, $__sd_ban );
/* Nuốt stderr: một cảnh báo PHP lọt vào stdout là hỏng JSON, và test sẽ chết bằng lỗi
   khó đọc thay vì đỏ một dòng rõ ràng. */
$__sd_r = json_decode( (string) shell_exec( 'php ' . escapeshellarg( $__sd_f ) . ' 2>/dev/null' ), true );
@unlink( $__sd_f );
assert_true( is_array( $__sd_r ), 'Chay duoc ham trong tien trinh rieng' );
foreach ( array( 'cong', 'tru', 'tru_qua', 'so_0', 'qua_tran', 'dung_tran', 'thieu_ly_do',
                 'khong_co_user', 'khong_quyen', 'huong_la' ) as $__sd_k ) {
    assert_true( isset( $__sd_r[ $__sd_k ]['ok'] ), "Co ket qua cho ca '$__sd_k'" );
}

/* ---- 1. Cộng và trừ phải đúng số ---- */
assert_true( $__sd_r['cong']['ok'], 'Cong 100.000d -> thanh cong' );
assert_equals( 100000, $__sd_r['so_du_1'], 'Sau khi cong: so du 100.000d' );
assert_true( $__sd_r['tru']['ok'], 'Tru 30.000d -> thanh cong' );
assert_equals( 70000, $__sd_r['so_du_2'], 'Sau khi tru: so du 70.000d' );

/* ---- 2. KHÔNG ĐỤNG SỔ KHÁCH HÀNG — yêu cầu gắt nhất của chủ site ---- */
foreach ( array( 'cong', 'tru' ) as $ca ) {
    $bang = implode( ' | ', (array) ( $__sd_r[ $ca ]['bang'] ?? array() ) );
    assert_true( strpos( $bang, 'customer' ) === false,
        "SONG CON: khong duoc cham bang nao cua khach hang ($ca) — dang cham: $bang" );
    assert_true( strpos( $bang, 'wpgd_sitetop_transactions' ) !== false,
        "Phai ghi vao so giao dich cua user ($ca)" );
    assert_true( strpos( $bang, 'wpgd_sitetop_withdrawals' ) === false,
        "Khong duoc ghi vao bang lenh rut ($ca)" );
}

/* ---- 3. Ghi SỔ, không sửa tay ---- */
assert_equals( 1, $__sd_r['cong']['them_dong'], 'Moi lan chinh chi them DUNG MOT dong so' );
assert_equals( 'earn', $__sd_r['cong']['dong_cuoi']['type'], 'Cong -> type earn' );
assert_equals( 'withdraw', $__sd_r['tru']['dong_cuoi']['type'], 'Tru -> type withdraw' );
assert_equals( 'admin_adjust', $__sd_r['tru']['dong_cuoi']['reference_type'],
    'SONG CON: tru phai mang reference_type admin_adjust — de "withdrawal" la cong thuc so du dem nham vao lenh rut' );
assert_equals( 387, $__sd_r['cong']['dong_cuoi']['reference_id'], 'Phai luu lai admin nao lam' );
/* Dấu của số tiền: lịch sử giao dịch của user hiện +/- theo amount >= 0, nên khoản trừ
   phải là số ÂM, nếu không user thấy "+30.000đ" cho một lần bị trừ. */
assert_equals( 100000,  $__sd_r['cong']['dong_cuoi']['amount'], 'Cong -> ghi so duong' );
assert_equals( -30000,  $__sd_r['tru']['dong_cuoi']['amount'],
    'SONG CON: tru -> ghi so AM, de lich su cua user hien dau tru' );
assert_true( strpos( $__sd_r['cong']['dong_cuoi']['description'], 'bù lượt lỗi' ) !== false, 'Ly do phai vao mo ta' );
assert_true( strpos( $__sd_r['cong']['dong_cuoi']['description'], 'Admin' ) !== false, 'Mo ta phai ghi ten admin' );
assert_equals( 1, $__sd_r['cong']['dong_bo'], 'Phai dong bo lai cot cache cho khoi lech' );

/* ---- 4. Các chốt ---- */
assert_false( $__sd_r['tru_qua']['ok'], 'SONG CON: tru qua so du -> TU CHOI' );
assert_equals( 70000, $__sd_r['so_du_3'], 'SONG CON: lan tu choi do KHONG duoc dong vao so' );
assert_equals( 0, $__sd_r['tru_qua']['them_dong'], 'Tu choi thi khong ghi dong nao' );
assert_false( $__sd_r['so_0']['ok'], 'So tien 0 -> tu choi' );
assert_false( $__sd_r['qua_tran']['ok'], 'SONG CON: 60.000.000d vuot tran -> tu choi (go thua so 0)' );
assert_true(  $__sd_r['dung_tran']['ok'], 'Dung 50.000.000d -> van cho' );
assert_false( $__sd_r['thieu_ly_do']['ok'], 'Ly do 2 ky tu -> tu choi' );
assert_false( $__sd_r['khong_co_user']['ok'], 'User khong ton tai -> tu choi' );
assert_false( $__sd_r['khong_quyen']['ok'], 'SONG CON: khong phai admin -> tu choi' );
assert_equals( 0, $__sd_r['khong_quyen']['them_dong'], 'Khong phai admin thi khong ghi gi' );
assert_true(  $__sd_r['huong_la']['ok'], 'Huong la -> coi nhu cong, khong vo' );
assert_equals( 'earn', $__sd_r['huong_la']['dong_cuoi']['type'], 'SONG CON: huong la phai ngam ve CONG, khong duoc thanh tru' );

/* ---- 5. Cột Số dư ở tab Người dùng phải khớp công thức thật ---- */
assert_true( preg_match( '#function sitetop_get_user_balance_amount.*?return max\( 0, (.*?) \);#s', $__sd_ver, $__sd_ct ) === 1,
    'Lay duoc cong thuc so du that' );
foreach ( array( 'shortlink_reward', 'earn' ) as $loai ) {
    assert_true( strpos( $__sd_tu, "type IN ('shortlink_reward','earn')" ) !== false,
        'SONG CON: cot Da kiem phai dem ca "earn", neu khong admin cong tien ma cot So du dung im' );
}
assert_true( strpos( $__sd_tu, "type='withdraw' AND (reference_type IS NULL OR reference_type <> 'withdrawal')" ) !== false,
    'SONG CON: cot So du phai tru ca khoan tru tay' );
assert_true( strpos( $__sd_tu, '$available = $earned - $withdrawn - $pending_w - abs($tru_tay);' ) !== false,
    'Cong thuc cot So du phai khop tung ve voi ham that' );

/* ---- 6. Không đi qua sitetop_add_user_balance (tránh bắn hook hoa hồng) ---- */
assert_true( strpos( $__sd_m[1], 'sitetop_add_user_balance' ) === false,
    'SONG CON: KHONG duoc goi sitetop_add_user_balance — ham do ban hook sitetop_user_balance_added' );

/* ---- 7. Khoản admin TRỪ phải ẨN bên tài khoản user ---- */
/* Chủ site chốt 06/10: user không được nhìn thấy dòng bị trừ. Nó vẫn nằm trong sổ và vẫn
   trừ vào số dư — chỉ không bày ra. Khoản admin CỘNG thì vẫn hiện. */
$__sd_lm = (string) file_get_contents( $__sd_goc . '/includes/admin-load-more.php' );
$__sd_ud = (string) file_get_contents( $__sd_goc . '/page-user-dashboard.php' );
/* Lấy ĐIỀU KIỆN THẬT từ mã nguồn rồi chạy nó, thay vì chép tay một bản vào test — chép
   tay thì sửa sai ở mã nguồn mà test vẫn xanh. */
assert_true( preg_match( '#AND NOT \(([^\n]*amount < 0)\)#', $__sd_lm, $__sd_mm ) === 1,
    'SONG CON: danh sach "Xem them" ben tai khoan user phai loai khoan admin tru' );
$__sd_an = 'NOT (' . $__sd_mm[1] . ')';
assert_true( preg_match( '#AND NOT \([^\n]*amount < 0\)#', $__sd_ud ) === 1,
    'SONG CON: cau doc giao dich o trang user cung phai loai khoan admin tru' );

/* CHẠY THẬT điều kiện ẩn trên SQLite với đúng bốn loại dòng có thể gặp. */
$db = new PDO( 'sqlite::memory:' );
$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$db->exec( 'CREATE TABLE t (nhan TEXT, type TEXT, amount INT, reference_type TEXT)' );
$st = $db->prepare( 'INSERT INTO t VALUES (?,?,?,?)' );
$st->execute( array( 'thuong_nhiem_vu', 'shortlink_reward', 500,     null ) );
$st->execute( array( 'admin_cong',      'earn',            100000,  'admin_adjust' ) );
$st->execute( array( 'admin_tru',       'withdraw',        -30000,  'admin_adjust' ) );
$st->execute( array( 'rut_tien_that',   'withdraw',        -235000, 'withdrawal' ) );
$st->execute( array( 'hoa_hong',        'referral_commission', 2000, null ) );

$hien = array();
foreach ( $db->query( 'SELECT nhan FROM t WHERE ' . $__sd_an ) as $r ) $hien[] = $r['nhan'];

assert_true(  in_array( 'thuong_nhiem_vu', $hien, true ), 'Thuong nhiem vu -> user van thay' );
assert_true(  in_array( 'admin_cong', $hien, true ),      'Admin CONG tien -> user van thay' );
assert_false( in_array( 'admin_tru', $hien, true ),       'SONG CON: admin TRU tien -> user KHONG thay' );
assert_true(  in_array( 'rut_tien_that', $hien, true ),   'SONG CON: lenh rut that van phai hien — no cung am, dung an nham' );
assert_true(  in_array( 'hoa_hong', $hien, true ),        'Hoa hong referral -> van thay' );
assert_equals( 4, count( $hien ), 'Dung mot dong bi an' );
