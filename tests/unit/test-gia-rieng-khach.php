<?php
/* GIÁ RIÊNG TỪNG KHÁCH HÀNG — chủ site 08/10/2026: admin đặt giá riêng theo 6 loại camp cho một tài khoản khách; giá gốc
   của hệ thống giữ nguyên; tạo/sửa camp tự áp giá riêng nếu có; khách này không ảnh hưởng khách khác.
   Chạy THẬT includes/gia-rieng-khach.php trong tiến trình riêng với WP giả (user meta, option, AJAX) — không dò chuỗi. */

$__gr_goc = dirname( __DIR__, 2 );

$__gr_ban = <<<'BANTHU'
<?php
define('ABSPATH','/');
$OPT=array(); function sitetop_get_option($k,$d=''){return array_key_exists($k,$GLOBALS['OPT'])?$GLOBALS['OPT'][$k]:$d;}
$META=array(); $VET=array();
function get_user_meta($u,$k,$s=false){return $GLOBALS['META'][$u][$k]??'';}
function update_user_meta($u,$k,$v){$GLOBALS['META'][$u][$k]=$v;$GLOBALS['VET'][]='luu:'.$u;return true;}
function delete_user_meta($u,$k){unset($GLOBALS['META'][$u][$k]);$GLOBALS['VET'][]='xoa:'.$u;return true;}
function absint($v){return abs((int)$v);} function add_action(){} function check_ajax_referer(){return true;} function current_user_can(){return true;}
function get_userdata($id){return $id===7||$id===8?(object)array('ID'=>$id,'user_login'=>'khach'.$id):false;}
class KetThuc extends Exception {}
function wp_send_json_error($m){throw new KetThuc('ERR:'.(is_string($m)?$m:json_encode($m)));}
function wp_send_json_success($d){throw new KetThuc('OK:'.json_encode($d,JSON_UNESCAPED_UNICODE));}
require $argv[1];
$OPT=array('keyword_price_1step'=>1300,'direct_price_2step'=>1700,'onsite_extra_90'=>250); // stub sitetop_get_option nhận khoá KHÔNG tiền tố (hàm thật tự thêm 'sitetop_')
$META[7]=array('sitetop_gia_rieng'=>array('keyword_1step'=>2000,'direct_1step'=>2500));
$out=array();
$out['goc']=array(sitetop_gia_goc('keyword_search','1step'),sitetop_gia_goc('keyword_search','2step'),sitetop_gia_goc('traffic_direct','2step'),sitetop_gia_goc('traffic_direct','nocode'),sitetop_gia_goc('keyword_search','la'));
$out['co_ban']=array('k7_kw1'=>sitetop_gia_co_ban_cho_khach(7,'keyword_search','1step'),'k7_kw2'=>sitetop_gia_co_ban_cho_khach(7,'keyword_search','2step'),'k7_d1'=>sitetop_gia_co_ban_cho_khach(7,'traffic_direct','1step'),'k8_kw1'=>sitetop_gia_co_ban_cho_khach(8,'keyword_search','1step'),'k0_kw1'=>sitetop_gia_co_ban_cho_khach(0,'keyword_search','1step'));
$out['rieng']=array(sitetop_gia_rieng_cua_khach(7,'keyword_search','1step'),sitetop_gia_rieng_cua_khach(7,'keyword_search','2step'),sitetop_gia_rieng_cua_khach(8,'keyword_search','1step'));
$out['onsite']=array(sitetop_gia_cho_khach(7,'keyword_search','1step',90),sitetop_gia_cho_khach(7,'keyword_search','1step',70),sitetop_gia_cho_khach(7,'keyword_search','1step',999),sitetop_phu_phi_onsite(120));
$out['bang7']=sitetop_bang_gia_cho_khach(7); $out['bang8']=sitetop_bang_gia_cho_khach(8);
$out['co_rieng']=array(sitetop_khach_co_gia_rieng(7),sitetop_khach_co_gia_rieng(8),sitetop_khach_co_gia_rieng(0));
$out['nhieu_khach']=array_keys(sitetop_bang_gia_khach_co_rieng(array(7,8,0)));
function ajax($post){ $_POST=$post; try{ sitetop_ajax_admin_gia_rieng(); return 'KHONG_KET_THUC'; } catch(KetThuc $e){ return $e->getMessage(); } }
$VET=array(); $out['ajax_luu']=ajax(array('user_id'=>8,'gia_keyword_1step'=>'2.500','gia_direct_2step'=>'0','gia_keyword_nocode'=>'abc','gia_direct_nocode'=>' 3000 '));
$out['meta8']=$META[8]['sitetop_gia_rieng']??null;
$out['ajax_xoa']=ajax(array('user_id'=>8)); $out['meta8_sau_xoa']=$META[8]['sitetop_gia_rieng']??null;
$out['ajax_qua_tran']=ajax(array('user_id'=>8,'gia_keyword_1step'=>'2000000'));
$out['ajax_khong_co_user']=ajax(array('user_id'=>99,'gia_keyword_1step'=>'2000'));
$out['meta7_nguyen']=$META[7]['sitetop_gia_rieng'];
echo json_encode($out, JSON_UNESCAPED_UNICODE);
BANTHU;
$__gr_f = sys_get_temp_dir() . '/st-gia-rieng-' . getmypid() . '.php';
file_put_contents( $__gr_f, $__gr_ban );
$__gr_r = (string) shell_exec( 'php ' . escapeshellarg( $__gr_f ) . ' ' . escapeshellarg( $__gr_goc . '/includes/gia-rieng-khach.php' ) . ' 2>&1' );
@unlink( $__gr_f );
$G = json_decode( trim( $__gr_r ), true );
assert_true( is_array( $G ), 'Chay duoc module gia rieng khach trong tien trinh rieng. Ra: ' . substr( $__gr_r, 0, 500 ) );
if ( is_array( $G ) ) {
    assert_equals( array( 1300, 1500, 1700, 1200, 1300 ), $G['goc'], 'Gia goc: doc option keyword_price_/direct_price_, mac dinh 1200/1500/1200 nhu cu; loai la → 1step' );
    assert_equals( array( 'k7_kw1' => 2000, 'k7_kw2' => 1500, 'k7_d1' => 2500, 'k8_kw1' => 1300, 'k0_kw1' => 1300 ), $G['co_ban'], 'Khach 7 co gia rieng → dung gia rieng dung loai; loai chua dat → gia goc; khach 8 (khong dat) va id 0 → gia goc. Ra: ' . json_encode( $G['co_ban'] ) );
    assert_equals( array( 2000, 0, 0 ), $G['rieng'], 'sitetop_gia_rieng_cua_khach: 0 khi chua dat' );
    assert_equals( array( 2250, 2000, 2000, 400 ), $G['onsite'], 'Phu phi onsite cong them nhu cu (option onsite_extra_90=250; 70 → 0; onsite la → 0; 120 mac dinh 400)' );
    assert_equals( array( 'keyword_search' => array( '1step' => 2000, '2step' => 1500, 'nocode' => 1200 ), 'traffic_direct' => array( '1step' => 2500, '2step' => 1700, 'nocode' => 1200 ) ), $G['bang7'], 'Bang gia JS cua khach 7 tron gia rieng + gia goc. Ra: ' . json_encode( $G['bang7'] ) );
    assert_equals( array( 'keyword_search' => array( '1step' => 1300, '2step' => 1500, 'nocode' => 1200 ), 'traffic_direct' => array( '1step' => 1200, '2step' => 1700, 'nocode' => 1200 ) ), $G['bang8'], 'Bang gia JS cua khach 8 = toan gia goc' );
    assert_equals( array( true, false, false ), $G['co_rieng'], 'khach_co_gia_rieng' );
    assert_equals( array( 7 ), $G['nhieu_khach'], 'Form admin: chi khach CO gia rieng moi co muc trong bang' );
    assert_true( strpos( (string) $G['ajax_luu'], 'OK:' ) === 0 && strpos( (string) $G['ajax_luu'], '2 loại camp' ) !== false, 'AJAX luu: nhan "2.500" va " 3000 ", bo "0"/"abc" → 2 loai. Ra: ' . $G['ajax_luu'] );
    assert_equals( array( 'keyword_1step' => 2500, 'direct_nocode' => 3000 ), $G['meta8'], 'Meta luu dung: dau cham ngan cach bi bo, khoang trang bi cat' );
    assert_true( strpos( (string) $G['ajax_xoa'], 'OK:' ) === 0 && $G['meta8_sau_xoa'] === null, 'AJAX bo trong het → xoa meta, ve gia goc' );
    assert_true( strpos( (string) $G['ajax_qua_tran'], 'ERR:Giá tối đa' ) === 0, 'Qua tran 1.000.000d/luot → tu choi' );
    assert_true( strpos( (string) $G['ajax_khong_co_user'], 'ERR:Không tìm thấy' ) === 0, 'User khong ton tai → tu choi' );
    assert_equals( array( 'keyword_1step' => 2000, 'direct_1step' => 2500 ), $G['meta7_nguyen'], 'Sua khach 8 KHONG dung khach 7 (khach nay khong anh huong khach khac)' );
}

/* ---- Các chỗ tính giá PHẢI đi qua giá-cho-khách (đọc đúng vị trí trong hàm) ---- */
$__gr_cc = (string) file_get_contents( $__gr_goc . '/includes/customer-campaign-ajax.php' );
$__gr_ad = (string) file_get_contents( $__gr_goc . '/includes/admin-dashboard.php' );
$__gr_sf = (string) file_get_contents( $__gr_goc . '/includes/shortlink-functions.php' );
$__gr_cd = (string) file_get_contents( $__gr_goc . '/page-customer-dashboard.php' );
$__gr_tc = (string) file_get_contents( $__gr_goc . '/includes/admin/tabs/tab-customers.php' );
$__gr_tk = (string) file_get_contents( $__gr_goc . '/includes/admin/tabs/tab-campaigns.php' );
$__gr_fn = (string) file_get_contents( $__gr_goc . '/functions.php' );
assert_true( strpos( $__gr_fn, "'gia-rieng-khach'," ) !== false, 'functions.php nap module gia rieng khach' );
assert_equals( 2, substr_count( $__gr_cc, "sitetop_gia_co_ban_cho_khach( \$user_id, \$task_type, \$traffic_type )" ), 'Khach TAO va SUA camp: gia co ban lay theo khach dang dang nhap (2 cho)' );
assert_true( strpos( $__gr_ad, "sitetop_gia_co_ban_cho_khach( (int) \$camp->customer_id, \$task_type, \$tt )" ) !== false, 'Admin sua camp doi loai/onsite: gia tinh lai theo khach chu camp' );
assert_true( strpos( $__gr_ad, "'gia_khach'=>" ) !== false, 'JSON mo modal sua camp kem bang gia cua khach chu camp' );
assert_true( strpos( $__gr_sf, "sitetop_gia_co_ban_cho_khach( (int) ( \$data['customer_id'] ?? 0 ), \$task_type, \$traffic_type )" ) !== false, 'sitetop_create_keyword_campaign: khong truyen gia → gia theo khach' );
assert_true( substr_count( $__gr_cd, 'sitetop_gia_co_ban_cho_khach( $user_id' ) >= 5 && strpos( $__gr_cd, 'sitetop_bang_gia_cho_khach( $user_id )' ) !== false, 'Trang khach hang: moi cho hien gia + bang PRICES cua JS deu theo khach dang dang nhap' );
assert_true( strpos( $__gr_tc, 'giaOpen(' ) !== false && strpos( $__gr_tc, "sitetop_admin_gia_rieng" ) !== false && strpos( $__gr_tc, 'id="giaModal"' ) !== false, 'Tab Khach hang: nut + modal Gia rieng, goi dung AJAX' );
assert_true( strpos( $__gr_tk, 'ADM_GIA_KHACH' ) !== false && strpos( $__gr_tk, '_admEditGiaKhach' ) !== false && strpos( $__gr_tk, 'onchange="admUpdatePrice()"' ) !== false, 'Tab Chien dich (admin): form tao doi khach → goi y gia theo khach; modal sua tinh lai gia theo khach chu camp' );
