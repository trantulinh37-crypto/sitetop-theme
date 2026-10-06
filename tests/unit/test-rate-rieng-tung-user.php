<?php
/* RATE RIÊNG CHO TỪNG USER — chủ site yêu cầu 06/10/2026:
   "Tôi chỉ cần chức năng tự chỉnh cho mỗi user rate theo ý của tôi. Còn khách hàng vẫn
   nguyên không thay đổi gì hết."

   Mức thưởng bình thường đóng băng vào camp lúc tạo, ai làm cũng nhận như nhau. Rate
   riêng là mức dành cho MỘT tài khoản, theo từng loại camp.

   Test chạy thật: hàm đọc rate, khối áp rate cắt nguyên văn từ khâu trả thưởng, và
   endpoint lưu. */

$__rr_goc = dirname( __DIR__, 2 );
$__rr_ver = (string) file_get_contents( $__rr_goc . '/includes/shortlink-verification.php' );
$__rr_ad  = (string) file_get_contents( $__rr_goc . '/includes/admin-dashboard.php' );
$__rr_fn  = (string) file_get_contents( $__rr_goc . '/functions.php' );
$__rr_tu  = (string) file_get_contents( $__rr_goc . '/includes/admin/tabs/tab-users.php' );

/* ---- 1. Đọc rate + áp vào khâu trả thưởng ---- */
assert_true( preg_match( '#(function sitetop_rate_rieng_khoa\(.*?\n\})#s', $__rr_ver, $__rr_m1 ) === 1, 'Lay duoc ham dung khoa' );
assert_true( preg_match( '#(function sitetop_rate_rieng_cua_user\(.*?\n\})#s', $__rr_ver, $__rr_m2 ) === 1, 'Lay duoc ham doc rate' );
assert_true( preg_match( '#(function sitetop_get_reward_amount\( \$campaign \) \{.*?\n\})#s', $__rr_fn, $__rr_m3 ) === 1, 'Lay duoc ham quyet thuong' );
/* Lấy khối theo MỐC ĐẦU và MỐC CUỐI, không ghim vào dạng của điều kiện bên trong —
   ghim thì đổi điều kiện là test báo "không lấy được khối" thay vì báo sai kết quả. */
assert_true( preg_match( '#(\n *\$rate_rieng = sitetop_rate_rieng_cua_user\(.*?)\n *\$reward_amount = sitetop_get_reward_amount#s', $__rr_ver, $__rr_m4 ) === 1,
    'Lay duoc khoi ap rate o khau tra thuong' );

$__rr_ban = <<<'BANTHU'
<?php
$META = array(); $CFG = array('keyword_user_1step'=>500,'keyword_user_2step'=>550,'direct_user_2step'=>400);
function get_user_meta($uid,$k,$single=false){ return $GLOBALS['META'][$uid][$k] ?? ''; }
function sitetop_get_option($k,$d=null){ return $GLOBALS['CFG'][$k] ?? $d; }
__HAM1__
__HAM2__
__HAM3__
function tra_thuong($uid, $camp_dong_bang, $loai_camp, $loai_traffic) {
    $visit = (object) array('user_id'=>$uid);
    $camp_obj = (object) array(
        'user_reward'   => $camp_dong_bang,
        'traffic_type'  => $loai_traffic,
        'campaign_type' => $loai_camp,
    );
__KHOI__
    return sitetop_get_reward_amount( $camp_obj );
}
$META[7]['sitetop_rate_rieng'] = array('keyword_2step'=>700, 'direct_2step'=>250);
$META[8]['sitetop_rate_rieng'] = array();           // mảng rỗng
$META[9]['sitetop_rate_rieng'] = 'hỏng';            // không phải mảng
$META[10]['sitetop_rate_rieng'] = '700';           // chuỗi số: không có chốt is_array thì
                                                   // '700'['keyword_2step'] ra '7' -> trả 7đ
$kq = array();
// user 7 có rate riêng
$kq['u7_kw2']     = tra_thuong(7, 500, 'keyword_search', '2step');   // 500 -> 700
$kq['u7_direct2'] = tra_thuong(7, 400, 'traffic_direct', '2step');   // 400 -> 250 (giảm)
$kq['u7_kw1']     = tra_thuong(7, 500, 'keyword_search', '1step');   // không đặt -> 500
$kq['u7_nocode']  = tra_thuong(7, 400, 'keyword_search', 'nocode');  // không đặt -> 400
// user khác KHÔNG bị ảnh hưởng
$kq['u5_kw2']     = tra_thuong(5, 500, 'keyword_search', '2step');
$kq['u8_kw2']     = tra_thuong(8, 500, 'keyword_search', '2step');
$kq['u9_kw2']     = tra_thuong(9, 500, 'keyword_search', '2step');
$kq['u10_kw2']    = tra_thuong(10, 500, 'keyword_search', '2step');
$kq['khach_vang'] = tra_thuong(0, 500, 'keyword_search', '2step');
// khoá dựng đúng chưa
$kq['khoa_kw']  = sitetop_rate_rieng_khoa('keyword_search','2step');
$kq['khoa_dir'] = sitetop_rate_rieng_khoa('traffic_direct','nocode');
$kq['khoa_la']  = sitetop_rate_rieng_khoa('traffic_social','1step');
echo json_encode($kq, JSON_UNESCAPED_UNICODE);
BANTHU;
$__rr_ban = str_replace( array('__HAM1__','__HAM2__','__HAM3__','__KHOI__'),
                         array($__rr_m1[1], $__rr_m2[1], $__rr_m3[1], $__rr_m4[1]), $__rr_ban );
$__rr_f = sys_get_temp_dir() . '/st-rate-' . getmypid() . '.php';
file_put_contents( $__rr_f, $__rr_ban );
$__rr_r = json_decode( (string) shell_exec( 'php ' . escapeshellarg( $__rr_f ) . ' 2>/dev/null' ), true );
@unlink( $__rr_f );
assert_true( is_array( $__rr_r ), 'Chay duoc trong tien trinh rieng' );

assert_equals( 700, $__rr_r['u7_kw2'],     'Dung vi du chu site: user co rate rieng 700 -> nhan 700' );
assert_equals( 250, $__rr_r['u7_direct2'], 'SONG CON: rate rieng phai GIAM duoc, khong chi tang (400 -> 250)' );
assert_equals( 500, $__rr_r['u7_kw1'],     'Loai camp khong dat rate -> van muc cu 500' );
assert_equals( 400, $__rr_r['u7_nocode'],  'Loai camp khong dat rate -> van muc cu 400' );
assert_equals( 500, $__rr_r['u5_kw2'],     'SONG CON: user KHAC khong he bi doi' );
assert_equals( 500, $__rr_r['u8_kw2'],     'Mang rong -> khong doi' );
assert_equals( 500, $__rr_r['u9_kw2'],     'Du lieu hong (khong phai mang) -> khong doi, khong vo' );
assert_equals( 500, $__rr_r['u10_kw2'],
    'SONG CON: meta luu nham kieu chuoi "700" -> phai BO QUA, khong duoc doc thanh 7d/luot' );
assert_equals( 500, $__rr_r['khach_vang'], 'Luot khong co user -> khong doi' );
assert_equals( 'keyword_2step', $__rr_r['khoa_kw'],  'Khoa keyword dung' );
assert_equals( 'direct_nocode', $__rr_r['khoa_dir'], 'Khoa direct dung' );
assert_equals( 'keyword_1step', $__rr_r['khoa_la'],  'Loai camp la -> gom ve keyword, khong vo' );

/* ---- 2. Không được dính vào tiền của khách ---- */
foreach ( array( 'price_per_view', 'customer_balance', 'customer_paid', 'cost', 'customer_transactions', 'customer_id' ) as $cam ) {
    assert_true( strpos( $__rr_m4[1], $cam ) === false,
        "SONG CON: khoi ap rate KHONG duoc dung toi '$cam' — tien cua khach phai giu nguyen" );
}
$vt_tru_khach = strpos( $__rr_ver, 'UPDATE {$p}customer_balance SET balance = balance - %d' );
$vt_rate      = strpos( $__rr_ver, '$rate_rieng = sitetop_rate_rieng_cua_user(' );
assert_true( $vt_tru_khach !== false && $vt_rate > $vt_tru_khach,
    'SONG CON: khoi ap rate phai nam SAU khau tru tien khach' );

/* ---- 3. Endpoint lưu rate: chạy thật ---- */
assert_true( preg_match( '#(function sitetop_ajax_admin_rate_rieng\(\) \{.*?\n\})#s', $__rr_ad, $__rr_m5 ) === 1, 'Lay duoc endpoint luu' );
assert_true( preg_match( '#(function sitetop_rate_rieng_cac_loai\(\) \{.*?\n\})#s', $__rr_ad, $__rr_m6 ) === 1, 'Lay duoc danh sach loai camp' );

$__rr_ban2 = <<<'BANTHU'
<?php
class Thoat extends Exception { public $ok; public $data;
    function __construct($ok,$d){ $this->ok=$ok; $this->data=$d; parent::__construct('x'); } }
function wp_send_json_error($d=null){ throw new Thoat(false,$d); }
function wp_send_json_success($d=null){ throw new Thoat(true,$d); }
function check_ajax_referer($a,$b){ return true; }
function current_user_can($c){ return $GLOBALS['LA_ADMIN']; }
function absint($v){ return abs((int)$v); }
function get_userdata($id){ return $id === 7 ? (object)array('user_login'=>'user7') : false; }
$LUU = array(); $XOA = 0;
function update_user_meta($u,$k,$v){ $GLOBALS['LUU'] = array($u,$k,$v); return true; }
function delete_user_meta($u,$k){ $GLOBALS['XOA']++; return true; }
__HAM6__
__HAM5__
function thu($post, $admin = true){
    $GLOBALS['LUU'] = array(); $GLOBALS['XOA'] = 0; $GLOBALS['LA_ADMIN'] = $admin;
    $_POST = $post;
    try { sitetop_ajax_admin_rate_rieng(); return array('ok'=>null); }
    catch (Thoat $e) { return array('ok'=>$e->ok,'data'=>$e->data,'luu'=>$GLOBALS['LUU'],'xoa'=>$GLOBALS['XOA']); }
}
$kq = array();
$kq['dat']       = thu(array('user_id'=>7,'rate_keyword_2step'=>'700','rate_direct_2step'=>'250','rate_keyword_1step'=>''));
$kq['bo_trong']  = thu(array('user_id'=>7,'rate_keyword_2step'=>'','rate_direct_2step'=>''));
$kq['so_0']      = thu(array('user_id'=>7,'rate_keyword_2step'=>'0'));
$kq['qua_tran']  = thu(array('user_id'=>7,'rate_keyword_2step'=>'1000000'));
$kq['dung_tran'] = thu(array('user_id'=>7,'rate_keyword_2step'=>'100000'));
$kq['khong_user']= thu(array('user_id'=>999,'rate_keyword_2step'=>'700'));
$kq['khong_quyen']=thu(array('user_id'=>7,'rate_keyword_2step'=>'700'), false);
$kq['khoa_la']   = thu(array('user_id'=>7,'rate_khach_hang'=>'999999','rate_keyword_2step'=>'600'));
echo json_encode($kq, JSON_UNESCAPED_UNICODE);
BANTHU;
$__rr_ban2 = str_replace( array('__HAM5__','__HAM6__'), array($__rr_m5[1], $__rr_m6[1]), $__rr_ban2 );
$__rr_f2 = sys_get_temp_dir() . '/st-rate2-' . getmypid() . '.php';
file_put_contents( $__rr_f2, $__rr_ban2 );
$__rr_e = json_decode( (string) shell_exec( 'php ' . escapeshellarg( $__rr_f2 ) . ' 2>/dev/null' ), true );
@unlink( $__rr_f2 );
assert_true( is_array( $__rr_e ), 'Chay duoc endpoint trong tien trinh rieng' );

assert_true( $__rr_e['dat']['ok'], 'Dat rate -> thanh cong' );
assert_equals( array( 'keyword_2step' => 700, 'direct_2step' => 250 ), $__rr_e['dat']['luu'][2],
    'Luu dung hai loai da nhap, o bo trong khong luu' );
assert_equals( 'sitetop_rate_rieng', $__rr_e['dat']['luu'][1], 'Luu dung khoa user meta' );
assert_equals( 0, $__rr_e['dat']['xoa'], 'Co rate thi khong xoa' );

assert_true( $__rr_e['bo_trong']['ok'], 'Bo trong het -> thanh cong' );
assert_equals( 1, $__rr_e['bo_trong']['xoa'], 'SONG CON: bo trong het -> XOA meta, ve muc mac dinh' );
assert_equals( 1, $__rr_e['so_0']['xoa'],     'Dien 0 cung la bo dat' );

assert_false( $__rr_e['qua_tran']['ok'],  'SONG CON: 1.000.000d/luot vuot tran -> tu choi (go thua so 0)' );
assert_true(  $__rr_e['dung_tran']['ok'], 'Dung 100.000d -> van cho' );
assert_false( $__rr_e['khong_user']['ok'],  'User khong ton tai -> tu choi' );
assert_false( $__rr_e['khong_quyen']['ok'], 'SONG CON: khong phai admin -> tu choi' );
assert_equals( 0, $__rr_e['khong_quyen']['xoa'], 'Khong phai admin thi khong dung gi' );
assert_equals( array( 'keyword_2step' => 600 ), $__rr_e['khoa_la']['luu'][2],
    'SONG CON: khoa la gui kem bi bo qua, chi nhan 6 loai da biet' );

/* ---- 4. Trang admin ---- */
assert_true( strpos( $__rr_tu, 'onclick=\'rateOpen(' ) !== false, 'Thieu nut mo hop rate o tab Nguoi dung' );
/* 06/10/2026: chọn khoá theo chế độ tiền — VNĐ đọc 'sitetop_rate_rieng', USD đọc 'sitetop_rate_rieng_usd'. */
assert_true( strpos( $__rr_tu, "get_user_meta(\$row->ID, sitetop_che_do_usd() ? 'sitetop_rate_rieng_usd' : 'sitetop_rate_rieng', true)" ) !== false, 'Phai doc rate dang co de do san vao hop (dung khoa theo che do tien)' );
assert_true( strpos( $__rr_tu, 'id="rateModal"' ) !== false, 'Thieu cho chua hop' );
assert_true( strpos( $__rr_tu, "action','sitetop_admin_rate_rieng'" ) !== false, 'Hop phai goi dung endpoint' );
