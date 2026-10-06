<?php
/* XOÁ NHIỀU USER BẰNG Ô TÍCH — chủ site yêu cầu 03/10/2026.

   Xoá user là việc KHÔNG HOÀN TÁC được: 17/09/2026 chủ site bấm nhầm nút Xóa một tài khoản,
   phải moi bản .wpress ngày 04/09 ra khôi phục. Cho tích hàng loạt thì một cú bấm nhầm hỏng
   hàng chục tài khoản, nên mọi lớp chắn phải được CHẠY THẬT trong test, không chỉ đọc chuỗi. */

$__xl_goc = dirname( __DIR__, 2 );
$__xl_ad  = (string) file_get_contents( $__xl_goc . '/includes/admin-dashboard.php' );
$__xl_tu  = (string) file_get_contents( $__xl_goc . '/includes/admin/tabs/tab-users.php' );

/* ---- 1. Hàm xoá dùng chung: chạy thật, xem nó đụng vào những gì ---- */
assert_true( preg_match( '#(function sitetop_admin_do_delete_user\( \$uid \) \{.*?\n\})#s', $__xl_ad, $__xl_m ) === 1,
    'Lay duoc ham xoa dung chung' );

$__xl_ban = <<<'BANTHU'
<?php
class WP_Error { public $ma; public $tin;
    function __construct($m='', $t='', $d=null){ $this->ma=$m; $this->tin=$t; }
    function get_error_message(){ return $this->tin; } }
function is_wp_error($x){ return $x instanceof WP_Error; }
define('SITETOP_PREFIX', 'sitetop_');
$VET = array();
class FakeDb {
    public $prefix = 'wpgd_';
    function prepare($q, ...$a){ foreach($a as $v) $q = preg_replace('/%d|%s/', (string)$v, $q, 1); return $q; }
    function get_results($q){ $GLOBALS['VET'][] = 'doc_lenh_rut'; return array((object)array('id'=>7), (object)array('id'=>8)); }
    function get_var($q){ return 0; }
    function delete($bang, $w){ $GLOBALS['VET'][] = 'xoa_bang:' . $bang; return 1; }
    function update($bang, $d, $w){ $GLOBALS['VET'][] = 'sua_bang:' . $bang . ':' . json_encode($d); return 1; }
}
$wpdb = new FakeDb();
$NGUOI = array(
    5  => array('login'=>'user_thuong', 'admin'=>false),
    9  => array('login'=>'sep',         'admin'=>true),
);
function get_userdata($id){ return isset($GLOBALS['NGUOI'][$id]) ? (object)array('user_login'=>$GLOBALS['NGUOI'][$id]['login']) : false; }
function user_can($id, $cap){ return ! empty($GLOBALS['NGUOI'][$id]['admin']); }
function absint($v){ return abs((int)$v); }
function sitetop_process_withdrawal($id, $tt, $ghi){ $GLOBALS['VET'][] = 'huy_lenh_rut:' . $id . ':' . $tt; return true; }
function update_user_meta($u, $k, $v){ $GLOBALS['VET'][] = 'danh_dau:' . $k; return true; }
function sitetop_current_time(){ return '2026-10-03 12:00:00'; }
function wp_delete_user($id){ $GLOBALS['VET'][] = 'wp_delete_user:' . $id; return true; }
__HAM__
$kq = array();
$VET = array(); $r = sitetop_admin_do_delete_user(5);
$kq['thuong'] = array('ok' => $r === true, 'vet' => $VET);
$VET = array(); $r = sitetop_admin_do_delete_user(9);
$kq['admin'] = array('loi' => is_wp_error($r) ? $r->ma : null, 'vet' => $VET);
$VET = array(); $r = sitetop_admin_do_delete_user(404);
$kq['khong_co'] = array('loi' => is_wp_error($r) ? $r->ma : null, 'vet' => $VET);
$VET = array(); $r = sitetop_admin_do_delete_user(0);
$kq['id_rong'] = array('loi' => is_wp_error($r) ? $r->ma : null, 'vet' => $VET);
echo json_encode($kq);
BANTHU;
$__xl_ban = str_replace( '__HAM__', $__xl_m[1], $__xl_ban );
$__xl_f = sys_get_temp_dir() . '/st-xoa-' . getmypid() . '.php';
file_put_contents( $__xl_f, $__xl_ban );
$__xl_r = json_decode( (string) shell_exec( 'php ' . escapeshellarg( $__xl_f ) . ' 2>&1' ), true );
@unlink( $__xl_f );
assert_true( is_array( $__xl_r ), 'Chay duoc ham xoa trong tien trinh rieng' );

assert_true( $__xl_r['thuong']['ok'], 'User thuong -> xoa duoc' );
$vet = $__xl_r['thuong']['vet'];
assert_true( in_array( 'huy_lenh_rut:7:rejected', $vet, true ) && in_array( 'huy_lenh_rut:8:rejected', $vet, true ),
    'Phai huy MOI lenh rut dang cho truoc khi xoa' );
assert_true( array_search( 'huy_lenh_rut:7:rejected', $vet, true ) < array_search( 'wp_delete_user:5', $vet, true ),
    'SONG CON: huy lenh rut phai chay TRUOC wp_delete_user — xoa user roi thi khong hoan tien duoc nua' );
assert_true( in_array( 'danh_dau:sitetop_deleted', $vet, true ), 'Phai danh dau da xoa de con lan vet' );
$sua = implode( ' | ', $vet );
assert_true( strpos( $sua, 'sua_bang:wpgd_sitetop_user_shortlinks' ) !== false, 'Phai tat shortlink dang chay' );
assert_true( strpos( $sua, 'xoa_bang:wpgd_sitetop_notifications' ) !== false, 'Phai don thong bao' );
/* Sổ sách tiền PHẢI còn nguyên */
assert_true( strpos( $sua, 'xoa_bang:wpgd_sitetop_transactions' ) === false,  'SONG CON: KHONG duoc xoa so giao dich' );
assert_true( strpos( $sua, 'xoa_bang:wpgd_sitetop_withdrawals' ) === false,   'SONG CON: KHONG duoc xoa bang lenh rut' );
assert_true( strpos( $sua, 'xoa_bang:wpgd_sitetop_user_balance' ) === false,  'SONG CON: KHONG duoc xoa bang so du' );
assert_true( strpos( $sua, 'xoa_bang:wpgd_sitetop_shortlink_visits' ) === false, 'SONG CON: KHONG duoc xoa lich su luot' );

assert_equals( 'la_admin', $__xl_r['admin']['loi'], 'SONG CON: tai khoan quan tri KHONG xoa duoc' );
assert_equals( array(), $__xl_r['admin']['vet'], 'Gap admin thi dung lai ngay, khong dung gi het' );
assert_equals( 'khong_co', $__xl_r['khong_co']['loi'], 'ID khong ton tai -> bao loi' );
assert_equals( 'thieu_id', $__xl_r['id_rong']['loi'], 'ID rong -> bao loi' );

/* ---- 2. Bộ xử lý hàng loạt: chạy thật ---- */
assert_true( preg_match( '#(\$ids = array_slice\(.*?)\n        if\( \$da_xoa \)#s', $__xl_tu, $__xl_m2 ) === 1,
    'Lay duoc doan xu ly hang loat' );

$__xl_ban2 = <<<'BANTHU'
<?php
class WP_Error { public $tin; function __construct($m='',$t=''){ $this->tin=$t; }
    function get_error_message(){ return $this->tin; } }
function is_wp_error($x){ return $x instanceof WP_Error; }
$NGUOI = array();
foreach (range(1, 120) as $i) $NGUOI[$i] = array('login'=>'u'.$i, 'admin'=>false, 'tien'=>0, 'cho'=>0);
$NGUOI[9]['admin'] = true;              // quản trị
$NGUOI[11]['tien'] = 250000;            // còn tiền
$NGUOI[12]['cho']  = 1;                 // đang có lệnh rút
function get_userdata($id){ return isset($GLOBALS['NGUOI'][$id]) ? (object)array('user_login'=>$GLOBALS['NGUOI'][$id]['login']) : false; }
function user_can($id,$c){ return ! empty($GLOBALS['NGUOI'][$id]['admin']); }
function sitetop_admin_so_du($id){ return (float) ($GLOBALS['NGUOI'][$id]['tien'] ?? 0); }
function sitetop_admin_co_lenh_cho($id){ return (int) ($GLOBALS['NGUOI'][$id]['cho'] ?? 0); }
function sitetop_format_money($v){ return number_format((float)$v,0,',','.') . 'đ'; }
/* Tab Người dùng in tiền user qua sitetop_format_tien_user() từ 06/10/2026 (chế độ VNĐ ở đây). */
function sitetop_format_tien_user($v){ return sitetop_format_money($v); }
function sitetop_admin_do_delete_user($id){ $GLOBALS['DA'][] = $id; return true; }
function wp_delete_user($id){ $GLOBALS['DA'][] = 'tho_' . $id; return true; }
function esc_html($t){ return $t; }
function thu($chuoi_id, $ca_tien){
    $GLOBALS['DA'] = array();
    $_POST = array('bulk_ids' => $chuoi_id);
    if ($ca_tien) $_POST['bulk_force_money'] = '1';
    __DOAN__
    return array('xoa'=>$da_xoa, 'bo_qua'=>$bo_qua, 'goi'=>$GLOBALS['DA']);
}
$kq = array();
$kq['thuong']    = thu('1,2,3', false);
$kq['co_admin']  = thu('1,9,2', false);
$kq['co_tien']   = thu('1,11,12', false);
$kq['ep_ca_tien']= thu('1,11,12', true);
$kq['trung_lap'] = thu('4,4,4,5', false);
$kq['rac']       = thu('abc,,0,-3,7', false);
$kq['qua_100']   = thu(implode(',', range(1,120)), false);
$kq['rong']      = thu('', false);
echo json_encode($kq);
BANTHU;
$__xl_ban2 = str_replace( '__DOAN__', $__xl_m2[1], $__xl_ban2 );
$__xl_f2 = sys_get_temp_dir() . '/st-xoaloat-' . getmypid() . '.php';
file_put_contents( $__xl_f2, $__xl_ban2 );
$__xl_b = json_decode( (string) shell_exec( 'php ' . escapeshellarg( $__xl_f2 ) . ' 2>&1' ), true );
@unlink( $__xl_f2 );
assert_true( is_array( $__xl_b ), 'Chay duoc bo xu ly hang loat' );

assert_equals( array( 'u1', 'u2', 'u3' ), $__xl_b['thuong']['xoa'], 'Ba user thuong -> xoa ca ba' );
assert_equals( array( 1, 2, 3 ), $__xl_b['thuong']['goi'], 'Phai goi ham xoa dung chung, khong goi wp_delete_user tho' );

assert_equals( array( 'u1', 'u2' ), $__xl_b['co_admin']['xoa'], 'Co admin trong danh sach -> van xoa nhung nguoi con lai' );
assert_equals( array( 'u9 (quản trị)' ), $__xl_b['co_admin']['bo_qua'], 'SONG CON: admin bi bo qua va noi ro ly do' );

assert_equals( array( 'u1' ), $__xl_b['co_tien']['xoa'], 'SONG CON: tai khoan con tien / dang cho rut -> KHONG xoa' );
assert_equals( 2, count( $__xl_b['co_tien']['bo_qua'] ), 'Bo qua ca hai va bao lai' );
assert_true( strpos( $__xl_b['co_tien']['bo_qua'][0], '250.000đ' ) !== false, 'Noi ro con bao nhieu tien' );
assert_true( strpos( $__xl_b['co_tien']['bo_qua'][1], 'lệnh rút' ) !== false, 'Noi ro dang co lenh rut' );

assert_equals( array( 'u1', 'u11', 'u12' ), $__xl_b['ep_ca_tien']['xoa'],
    'Tick o "xoa ca tai khoan con so du" thi moi xoa — phai do admin tu tay chon' );

assert_equals( array( 'u4', 'u5' ), $__xl_b['trung_lap']['xoa'], 'ID trung lap chi xoa mot lan' );
assert_equals( array( 'u7' ), $__xl_b['rac']['xoa'], 'Chuoi rac, so 0 va so am deu bi loai' );
/* 120 id gửi lên, chỉ 100 id đầu được xét; trong 100 đó có 1 admin + 1 còn tiền + 1 đang
   chờ rút nên chỉ 97 bị xoá. Con số 97 chính là bằng chứng trần 100 có hiệu lực — bỏ trần
   thì thành 117. */
assert_equals( 97, count( $__xl_b['qua_100']['goi'] ), 'SONG CON: moi lan xet toi da 100 tai khoan' );
assert_equals( array(), $__xl_b['rong']['xoa'], 'Khong chon ai thi khong xoa ai' );

/* ---- 3. Hộp xác nhận: phải gõ đúng số lượng ---- */
assert_true( preg_match( '#(function usrBulkXacNhan\(\)\{.*?\n\})#s', $__xl_tu, $__xl_m3 ) === 1,
    'Lay duoc ham xac nhan' );
$__xl_js = "var TICK = []; var TRA = null;\n" .
    "function usrTicked(){ return TICK; }\n" .
    "function alert(t){}\n" .
    "function prompt(t){ return TRA; }\n" .
    $__xl_m3[1] . "\n" .
    "function thu(n, tra){ TICK = []; for(var i=0;i<n;i++) TICK.push({dataset:{ten:'u'+i}}); TRA = tra; return usrBulkXacNhan(); }\n" .
    "console.log(JSON.stringify({ dung: thu(12,'12'), dung_co_khoang: thu(12,' 12 '), sai: thu(12,'11'),\n" .
    "  bo_trong: thu(12,''), bam_huy: thu(12,null), chu: thu(12,'xoa'), khong_chon: thu(0,'0') }));";
$__xl_f3 = sys_get_temp_dir() . '/st-xacnhan-' . getmypid() . '.js';
file_put_contents( $__xl_f3, $__xl_js );
$__xl_j = json_decode( (string) shell_exec( 'node ' . escapeshellarg( $__xl_f3 ) . ' 2>&1' ), true );
@unlink( $__xl_f3 );
assert_true( is_array( $__xl_j ), 'Chay duoc hop xac nhan bang node' );

assert_true(  $__xl_j['dung'],           'Go dung so 12 -> cho qua' );
assert_true(  $__xl_j['dung_co_khoang'], 'Go " 12 " co khoang trang -> van cho qua' );
assert_false( $__xl_j['sai'],            'SONG CON: go sai so -> CHAN' );
assert_false( $__xl_j['bo_trong'],       'SONG CON: bo trong -> CHAN' );
assert_false( $__xl_j['bam_huy'],        'SONG CON: bam Huy -> CHAN' );
assert_false( $__xl_j['chu'],            'Go chu linh tinh -> chan' );
assert_false( $__xl_j['khong_chon'],     'Khong chon ai -> chan ngay' );

/* ---- 4. Trang: ô tích và colspan ---- */
assert_true( strpos( $__xl_tu, 'class="usr-tick"' ) !== false, 'Thieu o tich o moi hang' );
assert_true( strpos( $__xl_tu, 'id="usrTickAll"' ) !== false, 'Thieu o tich chon tat ca' );
assert_true( strpos( $__xl_tu, 'colspan="14"' ) !== false && strpos( $__xl_tu, 'colspan="13"' ) === false,
    'Them mot cot thi colspan phai thanh 14' );
/* Form hàng loạt phải nằm NGOÀI bảng: mỗi hàng đã có form riêng cho nút Cấm/Xóa, lồng nhau
   là trình duyệt bỏ form bên trong, nút Cấm sẽ ngừng chạy. */
assert_true( strpos( $__xl_tu, '</form>' ) < strpos( $__xl_tu, '<div style="overflow-x:auto"><table' ),
    'SONG CON: form xoa hang loat phai dong TRUOC bang, khong duoc long form' );
