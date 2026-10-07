<?php
/* CẦU NỐI sitetop.net (NGUỒN) ⇄ sitetop.one (POOL) — chủ site 07/10/2026.
   Chạy THẬT module includes/cau-noi-net-one.php trong hai tiến trình riêng (vai trò pool / nguồn) với
   WordPress giả: option/transient trong bộ nhớ, wpdb ghi lại mọi câu lệnh, wp_remote_post trả theo
   kịch bản. Không dò chuỗi tên hàm — gọi hàm và xem nó làm gì (bài học kiem-chung-widget-phai-do-that). */

$__cn_goc = dirname( __DIR__, 2 );

$__cn_khung = <<<'KHUNG'
<?php
define('ABSPATH','/'); define('SITETOP_PREFIX','sitetop_'); define('SITETOP_CN_VAI_TRO', $argv[1]);
class WP_Error { public $ma,$tin,$du; function __construct($m='',$t='',$d=null){$this->ma=$m;$this->tin=$t;$this->du=$d;}
    function get_error_code(){return $this->ma;} function get_error_message(){return $this->tin;} function get_error_data(){return $this->du;} }
function is_wp_error($x){return $x instanceof WP_Error;}
$OPT=array(); function get_option($k,$d=false){return array_key_exists($k,$GLOBALS['OPT'])?$GLOBALS['OPT'][$k]:$d;} function update_option($k,$v,$a=null){$GLOBALS['OPT'][$k]=$v;return true;}
$TR=array(); function get_transient($k){return array_key_exists($k,$GLOBALS['TR'])?$GLOBALS['TR'][$k]:false;} function set_transient($k,$v,$e=0){$GLOBALS['TR'][$k]=$v;return true;} function delete_transient($k){unset($GLOBALS['TR'][$k]);return true;}
function sitetop_current_time(){return '2026-10-07 15:00:00';} function sitetop_get_option($k,$d=''){return get_option('sitetop_'.$k,$d);}
function home_url(){return $GLOBALS['HOME'];} function wp_parse_url($u,$c=-1){return parse_url($u,$c);} function untrailingslashit($s){return rtrim($s,'/');}
function wp_json_encode($v,$f=0){return json_encode($v,$f);}
$HOOKS=array(); function add_action($h,$cb=null){$GLOBALS['HOOKS'][$h][]=$cb;} function add_filter(){} function remove_filter(){} function register_rest_route(){} function rest_ensure_response($d){return $d;}
function sanitize_text_field($s){return trim(strip_tags((string)$s));} function sanitize_textarea_field($s){return trim(strip_tags((string)$s));} function esc_url_raw($u){return (string)$u;} function wp_unslash($s){return is_string($s)?stripslashes($s):$s;} function esc_sql($s){return addslashes((string)$s);}
function current_user_can(){return true;} function get_current_user_id(){return 1;}
$USERS=array(); $VET=array();
function get_userdata($id){return isset($GLOBALS['USERS'][$id])?(object)$GLOBALS['USERS'][$id]:false;}
function get_user_by($f,$v){foreach($GLOBALS['USERS'] as $id=>$u){if($u['user_login']===$v)return (object)$u;}return false;}
function wp_insert_user($a){$id=max(array_merge(array(100),array_keys($GLOBALS['USERS'])))+1;$GLOBALS['USERS'][$id]=array('ID'=>$id,'user_login'=>$a['user_login'],'role'=>$a['role']);$GLOBALS['VET'][]='tao_user:'.$a['user_login'].':'.$a['role'];return $id;}
function update_user_meta($u,$k,$v){$GLOBALS['VET'][]='meta:'.$u.':'.$k;return true;} function wp_generate_password(){return 'x';}
function sitetop_sync_customer_balance($id){$GLOBALS['VET'][]='sync_bal:'.$id;}
function sitetop_ghi_vet($sid,$cong,$them=''){$GLOBALS['VET'][]='vet:'.$cong.':'.$them;}
$HTTP=array(); $GOI=array();
function wp_remote_post($url,$args){$GLOBALS['GOI'][]=array('url'=>$url,'args'=>$args); if(empty($GLOBALS['HTTP']))return new WP_Error('http','khong co phan hoi gia'); return array_shift($GLOBALS['HTTP']);}
function wp_remote_retrieve_response_code($r){return is_array($r)?$r['code']:0;} function wp_remote_retrieve_body($r){return is_array($r)?$r['body']:'';}
function tra_ok($d){return array('code'=>200,'body'=>json_encode(array('st'=>1)+$d));}
class FakeDb { public $prefix='wp_'; public $insert_id=0; public $log=array(); public $rules=array();
    function prepare($q,...$a){ if(count($a)===1&&is_array($a[0]))$a=$a[0]; foreach($a as $v){$q=preg_replace_callback('/%d|%s|%f/',function($m)use($v){return $m[0]==='%d'?(string)(int)$v:($m[0]==='%f'?(string)(float)$v:"'".addslashes((string)$v)."'");},$q,1);} return $q; }
    function esc_like($s){return addcslashes($s,'_%\\');} function hide_errors(){} function show_errors(){}
    private function tim($q){ foreach($this->rules as $r){ if(preg_match($r[0],$q)) return is_callable($r[1])?call_user_func($r[1],$q):$r[1]; } return null; }
    function get_row($q){$this->log[]='row:'.$q; return $this->tim($q);} function get_var($q){$this->log[]='var:'.$q; $v=$this->tim($q); return $v;}
    function get_results($q){$this->log[]='results:'.$q; $v=$this->tim($q); return $v===null?array():$v;} function get_col($q){$this->log[]='col:'.$q; $v=$this->tim($q); return $v===null?array():$v;}
    function insert($t,$d){$this->log[]='insert:'.$t.':'.json_encode($d); $this->insert_id=++$GLOBALS['ID_MOI']; return 1;}
    function update($t,$d,$w){$this->log[]='update:'.$t.':'.json_encode($d).':'.json_encode($w); return 1;}
    function query($q){$this->log[]='query:'.$q; return 1;}
}
$ID_MOI=600; $wpdb=new FakeDb();
class FakeReq { public $h=array(); public $body=''; public $p=array(); function get_header($k){return $this->h[$k]??'';} function get_body(){return $this->body;} function get_param($k){return $this->p[$k]??null;} }
function req_ky($payload,$host,$secret,$ts=null,$dang_form=false){ $r=new FakeReq(); $body=json_encode($payload); $ts=$ts??(string)time(); $r->h=array('x_st_ts'=>$ts,'x_st_sign'=>hash_hmac('sha256',$ts.'.'.$body,$secret),'x_st_host'=>$host); if($dang_form){$r->p['payload']=$body;}else{$r->body=$body;} return $r; }
function tim_log($db,$re){ $o=array(); foreach($db->log as $l){ if(preg_match($re,$l)) $o[]=$l; } return $o; }
KHUNG;

/* ================= KỊCH BẢN POOL (.one) ================= */
$__cn_pool = <<<'POOL'
$HOME='https://sitetop.one'; $OPT['sitetop_cn_secret']='KhoaTestCauNoi_2026_abc'; $OPT['sitetop_cn_nhan']=1;
require $argv[2];
$out=array();
$out['vai_tro']=sitetop_cn_vai_tro(); $out['doi_tac']=sitetop_cn_doi_tac_url();
// a) ký / xác thực
$S=sitetop_cn_secret();
$r=sitetop_cn_xac_thuc(req_ky(array('x'=>1),'sitetop.net',$S)); $out['xt_ok']=is_wp_error($r)?$r->ma:$r;
$r=sitetop_cn_xac_thuc(req_ky(array('x'=>1),'sitetop.net','khoa-sai')); $out['xt_sai_khoa']=is_wp_error($r)?$r->ma:'lot';
$r=sitetop_cn_xac_thuc(req_ky(array('x'=>1),'sitetop.net',$S,(string)(time()-700))); $out['xt_cu']=is_wp_error($r)?$r->ma:'lot';
$r=sitetop_cn_xac_thuc(req_ky(array('x'=>1),'ke-la.com',$S)); $out['xt_host_la']=is_wp_error($r)?$r->ma:'lot';
$r=sitetop_cn_xac_thuc(req_ky(array('x'=>2),'sitetop.net',$S,null,true)); $out['xt_form']=is_wp_error($r)?$r->ma:$r;
$OPT['sitetop_cn_secret']=''; $r=sitetop_cn_xac_thuc(req_ky(array('x'=>1),'sitetop.net',$S)); $out['xt_chua_khoa']=is_wp_error($r)?$r->ma:'lot'; $OPT['sitetop_cn_secret']=$S;
// b) gọi ra
$HTTP=array(array('code'=>200,'body'=>json_encode(array('ok'=>true)))); $r=sitetop_cn_goi('camps',array('a'=>1)); $out['goi_khong_dau']=is_wp_error($r)?$r->ma:'lot';
$HTTP=array(tra_ok(array('ok'=>true,'x'=>5))); $GOI=array(); $r=sitetop_cn_goi('camps',array('a'=>1)); $out['goi_ok']=$r; $g=$GOI[0];
$out['goi_url']=$g['url']; $out['goi_ky_dung']=hash_equals(hash_hmac('sha256',$g['args']['headers']['X-St-Ts'].'.'.$g['args']['body'],$S),$g['args']['headers']['X-St-Sign']); $out['goi_host']=$g['args']['headers']['X-St-Host']; $out['goi_ua_trinh_duyet']=strpos($g['args']['user-agent'],'Mozilla/5.0')===0;
$HTTP=array(array('code'=>415,'body'=>''),tra_ok(array('ok'=>true))); $GOI=array(); $r=sitetop_cn_goi('camps',array('a'=>1)); $out['goi_415']=array('ok'=>!is_wp_error($r),'so_lan'=>count($GOI),'lan2_form'=>is_array($GOI[1]['args']['body'])&&isset($GOI[1]['args']['body']['payload']));
$HTTP=array(array('code'=>500,'body'=>json_encode(array('message'=>'hong')))); $r=sitetop_cn_goi('camps',array()); $out['goi_500']=is_wp_error($r)?array($r->ma,$r->tin):'lot';
// c) đồng bộ khi OFF
$OPT['sitetop_cn_map']=array(11=>501,12=>502); $OPT['sitetop_cn_nhan']=0; $wpdb->log=array(); $GOI=array(); $TR['sitetop_eligible_campaigns']=array('x');
$r=sitetop_cn_dong_bo('tat'); $out['off']=array('kq'=>$r,'goi'=>count($GOI),'pause'=>tim_log($wpdb,'/query:UPDATE .*paused.* WHERE id IN \(501,502\)/'),'transient_con'=>array_key_exists('sitetop_eligible_campaigns',$TR));
// d) đồng bộ khi ON
$OPT['sitetop_cn_nhan']=1; $TAO=array(); $SUA=array();
function sitetop_create_keyword_campaign($d){$GLOBALS['TAO'][]=$d;return 777;} function sitetop_update_campaign($id,$d){$GLOBALS['SUA'][]=array($id,$d);return 1;}
$wpdb->rules=array(array('/SELECT id FROM wp_sitetop_keyword_campaigns WHERE id = 501/',501),array('/title LIKE/',null));
$camps=array(
  array('id'=>11,'title'=>'Camp tu khoa','keyword'=>'mua xe','target_url'=>'https://a.com/x','destination_urls'=>'["https://a.com/x"]','traffic_type'=>'1step','campaign_type'=>'keyword_search','price_per_view'=>1500,'onsite_time'=>70,'countdown_seconds'=>30,'daily_traffic'=>40,'quantity'=>1000,'kw_bat_go_tay'=>1,'serp_page'=>2),
  array('id'=>13,'title'=>'Camp direct','keyword'=>'','target_url'=>'https://b.com/','traffic_type'=>'2step','campaign_type'=>'traffic_direct','price_per_view'=>2000,'onsite_time'=>80,'countdown_seconds'=>20,'daily_traffic'=>5,'quantity'=>50,'step2_target_url'=>'https://b.com/2','khong_doi_cd'=>1),
);
$HTTP=array(tra_ok(array('ok'=>true,'bat'=>true,'camps'=>$camps))); $wpdb->log=array(); $TR['sitetop_eligible_campaigns']=array('x'); $VET=array();
$r=sitetop_cn_dong_bo('tay');
$out['on']=array('kq'=>$r,'tao'=>$TAO,'sua'=>$SUA,'map'=>$OPT['sitetop_cn_map'],'pause'=>tim_log($wpdb,'/query:UPDATE .*paused.* WHERE id IN \(502\)/'),'transient_con'=>array_key_exists('sitetop_eligible_campaigns',$TR),'vet'=>$VET,'fed'=>$OPT['sitetop_cn_fed_customer']);
// d2) nguồn tắt cầu nối → coi như không còn camp
$HTTP=array(tra_ok(array('ok'=>true,'bat'=>false,'camps'=>$camps))); $wpdb->log=array(); $TAO=array();
$r=sitetop_cn_dong_bo('cron'); $out['nguon_tat']=array('so_camp'=>$r['so_camp']??null,'tao'=>count($TAO),'pause'=>count(tim_log($wpdb,'/query:UPDATE .*paused.* WHERE id IN/')));
// e) trước xác minh
$OPT['sitetop_cn_map']=array(11=>501,13=>777); $OPT['sitetop_che_do_usd']=1;
$v=(object)array('campaign_id'=>501,'camp_title'=>'[sitetop.net#11] Camp tu khoa','step'=>'started','code_shown_at'=>null,'verify_code'=>null,'from_google'=>0,'url_matched'=>0);
$HTTP=array(tra_ok(array('ok'=>true,'trang_thai'=>'verified'))); $wpdb->log=array(); $TR=array(); $GOI=array();
$r=sitetop_cn_truoc_xac_minh($v,'SIDPOOL0001','abcd1234');
$out['xm_ok']=array('kq'=>$r,'goi_than'=>json_decode($GOI[0]['args']['body'],true),'goi_url'=>$GOI[0]['url'],'update'=>tim_log($wpdb,'/^update:/'),'tr'=>array_keys($TR),'visit'=>array($v->verify_code,$v->from_google,$v->url_matched,$v->step,$v->code_shown_at));
$v2=(object)array('campaign_id'=>501,'camp_title'=>'x','step'=>'started','code_shown_at'=>null,'verify_code'=>null);
$HTTP=array(tra_ok(array('ok'=>false,'ma_loi'=>'too_fast','thong_bao'=>'Chưa đủ thời gian','du_lieu'=>array('remaining'=>12)))); $wpdb->log=array(); $TR=array();
$r=sitetop_cn_truoc_xac_minh($v2,'SIDPOOL0002','abcd1234'); $out['xm_nguon_lac']=array('ma'=>is_wp_error($r)?$r->ma:'lot','tin'=>is_wp_error($r)?$r->tin:'','du'=>is_wp_error($r)?$r->du:null,'update'=>count(tim_log($wpdb,'/^update:/')),'tr'=>count($TR),'verify_code'=>$v2->verify_code);
$HTTP=array(); $r=sitetop_cn_truoc_xac_minh($v2,'SIDPOOL0002','abcd1234'); $out['xm_mat_mang']=is_wp_error($r)?$r->ma:'lot';
$GOI=array(); $v3=(object)array('campaign_id'=>900,'camp_title'=>'Camp noi bo'); $r=sitetop_cn_truoc_xac_minh($v3,'SIDPOOL0003','abcd1234'); $out['xm_noi_bo']=array('kq'=>$r,'goi'=>count($GOI));
$r=sitetop_cn_truoc_xac_minh($v2,'SIDPOOL0002','  '); $out['xm_ma_rong']=is_wp_error($r)?$r->ma:'lot';
// f) gán camp cho lượt
$camp501=(object)array('id'=>501,'title'=>'[sitetop.net#11] Camp tu khoa','order_id'=>61); $camp900=(object)array('id'=>900,'title'=>'Noi bo','order_id'=>62);
$_SERVER['HTTP_USER_AGENT']='UA-test';
$HTTP=array(tra_ok(array('ok'=>true,'trang_thai'=>'moi'))); $GOI=array(); $wpdb->log=array();
$r=sitetop_cn_gan_camp_pool($camp501,'SIDPOOL0004','1.2.3.4',(object)array('id'=>7)); $out['gan_ok']=array('id'=>$r?$r->id:null,'than'=>json_decode($GOI[0]['args']['body'],true),'update'=>count(tim_log($wpdb,'/^update:/')));
function sitetop_get_random_active_campaign($ip,$exclude=0){$GLOBALS['VET'][]='chon_lai:tru_'.$exclude; return $GLOBALS['CAMP_KHAC'];}
$CAMP_KHAC=$camp900; $HTTP=array(tra_ok(array('ok'=>false,'ma_loi'=>'camp_khong_nhan','thong_bao'=>'khong nhan'))); $GOI=array(); $wpdb->log=array(); $VET=array();
$r=sitetop_cn_gan_camp_pool($camp501,'SIDPOOL0005','1.2.3.4',(object)array('id'=>7)); $out['gan_doi']=array('id'=>$r?$r->id:null,'vet'=>$VET,'pause501'=>tim_log($wpdb,'/^update:wp_sitetop_keyword_campaigns.*paused.*"id":501/'),'visit_doi'=>tim_log($wpdb,'/^update:wp_sitetop_shortlink_visits.*"campaign_id":900/'));
$CAMP_KHAC=null; $HTTP=array(); $r=sitetop_cn_gan_camp_pool($camp501,'SIDPOOL0006','1.2.3.4',(object)array('id'=>7)); $out['gan_het']=$r;
$OPT['sitetop_cn_nhan']=0; $GOI=array(); $CAMP_KHAC=$camp900; $r=sitetop_cn_gan_camp_pool($camp501,'SIDPOOL0007','1.2.3.4',(object)array('id'=>7)); $out['gan_off']=array('id'=>$r?$r->id:null,'goi'=>count($GOI)); $OPT['sitetop_cn_nhan']=1;
$GOI=array(); $r=sitetop_cn_gan_camp_pool($camp900,'SIDPOOL0008','1.2.3.4',(object)array('id'=>7)); $out['gan_noi_bo']=array('id'=>$r?$r->id:null,'goi'=>count($GOI));
// g) hỏi có mã + h) đánh dấu
$wpdb->rules=array(array('/SELECT campaign_id FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDPOOL0009\'/',501),array('/SELECT id, campaign_id, step, code_shown_at FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDPOOL0009\'/',(object)array('id'=>1,'campaign_id'=>501,'step'=>'started','code_shown_at'=>null)));
$TR=array(); $HTTP=array(tra_ok(array('ok'=>true,'co_ma'=>true))); $GOI=array(); $wpdb->log=array();
sitetop_cn_hoi_co_ma('SIDPOOL0009'); $out['hoi']=array('goi'=>count($GOI),'ready'=>!empty($TR['sitetop_widget_code_ready_SIDPOOL0009']),'marker'=>!empty($TR['sitetop_cn_ma_SIDPOOL0009']),'code_shown'=>count(tim_log($wpdb,'/^update:wp_sitetop_shortlink_visits.*code_shown/')));
unset($TR['sitetop_widget_code_ready_SIDPOOL0009']); $GOI=array(); sitetop_cn_hoi_co_ma('SIDPOOL0009'); $out['hoi_throttle']=count($GOI);
$wpdb->rules=array(array('/session_id = \'SIDNOIBO1\'/',(object)array('id'=>2,'campaign_id'=>900,'step'=>'started','code_shown_at'=>null))); $TR=array();
$out['danh_dau_noi_bo']=array('kq'=>sitetop_cn_danh_dau_co_ma('SIDNOIBO1'),'tr'=>count($TR));
echo json_encode($out, JSON_UNESCAPED_UNICODE);
POOL;

/* ================= KỊCH BẢN NGUỒN (.net) ================= */
$__cn_nguon = <<<'NGUON'
$HOME='https://sitetop.net'; $OPT['sitetop_cn_secret']='KhoaTestCauNoi_2026_abc'; $OPT['sitetop_cn_bat']=1;
$OPT['sitetop_migration_cho_phep_nguon_v1']=1; $OPT['sitetop_cn_pool_shortlink']=44; $OPT['sitetop_cn_pool_user']=9; $USERS[9]=array('ID'=>9,'user_login'=>'pool_sitetop_one','role'=>'subscriber');
require $argv[2];
$out=array(); $out['vai_tro']=sitetop_cn_vai_tro(); $out['doi_tac']=sitetop_cn_doi_tac_url();
// i) lượt pool
$out['la_pool']=array(sitetop_cn_la_luot_pool((object)array('shortlink_id'=>44)),sitetop_cn_la_luot_pool((object)array('shortlink_id'=>45)),sitetop_cn_la_luot_pool(null));
// j) đăng ký lượt gương
$CAMP=(object)array('id'=>11,'order_id'=>5);
$wpdb->rules=array(array('/SELECT id FROM wp_sitetop_user_shortlinks WHERE id = 44/',44),array('/kc.cho_phep_nguon = 1/',function($q){return $GLOBALS['CAMP'];}),array('/FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDMOI00001\'/',null));
$wpdb->log=array(); $VET=array(); $r=sitetop_cn_dang_ky_luot_guong('SIDMOI00001',11,'1.2.3.4','UA x','https://sitetop.one/abc');
$out['dk_moi']=array('kq'=>$r,'insert'=>tim_log($wpdb,'/^insert:wp_sitetop_shortlink_visits/'),'vet'=>$VET);
$wpdb->rules[]=array('/session_id = \'SIDCOMA0001\'/',(object)array('id'=>21,'shortlink_id'=>44,'step'=>'code_shown','reward_paid'=>0,'verified_at'=>null,'verify_code'=>'ABCD1234'));
$wpdb->log=array(); $r=sitetop_cn_dang_ky_luot_guong('SIDCOMA0001',11,'1.2.3.4','UA','ref'); $out['dk_co_ma']=array('kq'=>$r,'update'=>count(tim_log($wpdb,'/^update:/')));
$wpdb->rules[]=array('/session_id = \'SIDXONG0001\'/',(object)array('id'=>22,'shortlink_id'=>44,'step'=>'verified','reward_paid'=>0,'verified_at'=>'2026-10-07 14:00:00','verify_code'=>'ABCD1234'));
$r=sitetop_cn_dang_ky_luot_guong('SIDXONG0001',11,'1.2.3.4','UA','ref'); $out['dk_xong']=$r;
$wpdb->rules[]=array('/session_id = \'SIDBATDAU01\'/',(object)array('id'=>23,'shortlink_id'=>44,'step'=>'started','reward_paid'=>0,'verified_at'=>null,'verify_code'=>null));
$TR['sitetop_widget_code_ready_SIDBATDAU01']=1; $TR['sitetop_verify_code_SIDBATDAU01']='x'; $wpdb->log=array();
$r=sitetop_cn_dang_ky_luot_guong('SIDBATDAU01',11,'5.6.7.8','UA','ref'); $out['dk_dat_lai']=array('kq'=>$r,'update'=>tim_log($wpdb,'/^update:wp_sitetop_shortlink_visits/'),'tr_con'=>array_keys($TR));
$wpdb->rules[]=array('/session_id = \'SIDNOIBO001\'/',(object)array('id'=>24,'shortlink_id'=>45,'step'=>'started','reward_paid'=>0,'verified_at'=>null,'verify_code'=>null));
$r=sitetop_cn_dang_ky_luot_guong('SIDNOIBO001',11,'1.2.3.4','UA','ref'); $out['dk_phien_noi_bo']=is_wp_error($r)?$r->ma:'lot';
$CAMP=null; $r=sitetop_cn_dang_ky_luot_guong('SIDMOI00002',11,'1.2.3.4','UA','ref'); $out['dk_camp_khong_nhan']=is_wp_error($r)?$r->ma:'lot'; $CAMP=(object)array('id'=>11,'order_id'=>5);
$r=sitetop_cn_dang_ky_luot_guong('sid co dau cach',11,'1.2.3.4','UA','ref'); $out['dk_sid_sai']=is_wp_error($r)?$r->ma:'lot';
$r=sitetop_cn_dang_ky_luot_guong('SIDMOI00003',11,'khong-phai-ip','UA','ref'); $out['dk_ip_sai']=is_wp_error($r)?$r->ma:'lot';
// k) xác minh lượt gương
$KQ_VERIFY=null; function sitetop_verify_and_pay($sid,$code,$co=false){$GLOBALS['VET'][]='verify:'.$sid.':'.$code.':'.($co?'1':'0'); return $GLOBALS['KQ_VERIFY'];}
$wpdb->rules[]=array('/SELECT id, shortlink_id, verify_code, step FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDXM000001\'/',(object)array('id'=>31,'shortlink_id'=>44,'verify_code'=>'ABCD1234','step'=>'code_shown'));
$wpdb->rules[]=array('/SELECT step, customer_paid, completion_time FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDXM000001\'/',(object)array('step'=>'verified','customer_paid'=>1,'completion_time'=>88));
$KQ_VERIFY=array('success'=>true); $VET=array(); $out['xm_ok']=array('kq'=>sitetop_cn_xac_minh_luot_guong('SIDXM000001','abcd1234'),'vet'=>$VET);
$KQ_VERIFY=new WP_Error('wrong_code','Mã xác minh không đúng'); $out['xm_sai']=sitetop_cn_xac_minh_luot_guong('SIDXM000001','zzzz');
$KQ_VERIFY=new WP_Error('already_used','Đã xác minh'); $out['xm_lai_dung_ma']=sitetop_cn_xac_minh_luot_guong('SIDXM000001','ABCD1234'); $out['xm_lai_sai_ma']=sitetop_cn_xac_minh_luot_guong('SIDXM000001','KHAC');
$wpdb->rules[]=array('/SELECT id, shortlink_id, verify_code, step FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDXM000002\'/',(object)array('id'=>32,'shortlink_id'=>45,'verify_code'=>'ABCD1234','step'=>'code_shown'));
$VET=array(); $out['xm_khong_pool']=array('kq'=>sitetop_cn_xac_minh_luot_guong('SIDXM000002','ABCD1234'),'goi_verify'=>count($VET));
// l) camp cho pool
$rows=array(
 (object)array('id'=>11,'title'=>'KW ok','keyword'=>'mua xe','target_url'=>'https://a.com','destination_urls'=>null,'traffic_type'=>'1step','campaign_type'=>'keyword_search','task_type'=>'keyword_search','price_per_view'=>1500,'countdown_seconds'=>30,'onsite_time'=>70,'fixed_code'=>null,'daily_traffic'=>40,'order_daily_traffic'=>40,'quantity'=>1000,'completed'=>3,'updated_at'=>'2026-10-07','customer_id'=>201),
 (object)array('id'=>12,'title'=>'KW rong','keyword'=>'  ','target_url'=>'https://a.com','destination_urls'=>null,'traffic_type'=>'1step','campaign_type'=>'keyword_search','task_type'=>'keyword_search','price_per_view'=>1500,'countdown_seconds'=>30,'onsite_time'=>70,'fixed_code'=>null,'daily_traffic'=>40,'order_daily_traffic'=>40,'quantity'=>1,'completed'=>0,'updated_at'=>'','customer_id'=>201),
 (object)array('id'=>13,'title'=>'Direct ngheo','keyword'=>'','target_url'=>'https://b.com','destination_urls'=>null,'traffic_type'=>'1step','campaign_type'=>'traffic_direct','task_type'=>'traffic_direct','price_per_view'=>2000,'countdown_seconds'=>30,'onsite_time'=>70,'fixed_code'=>null,'daily_traffic'=>40,'order_daily_traffic'=>40,'quantity'=>1,'completed'=>0,'updated_at'=>'','customer_id'=>202),
 (object)array('id'=>14,'title'=>'Direct het han muc','keyword'=>'','target_url'=>'https://c.com','destination_urls'=>null,'traffic_type'=>'1step','campaign_type'=>'traffic_direct','task_type'=>'traffic_direct','price_per_view'=>2000,'countdown_seconds'=>30,'onsite_time'=>70,'fixed_code'=>null,'daily_traffic'=>5,'order_daily_traffic'=>5,'quantity'=>1,'completed'=>0,'updated_at'=>'','customer_id'=>201),
);
function sitetop_get_customer_balance_amount($id){return $id===201?900000:1000;}
$wpdb->rules=array(array('/kc.cho_phep_nguon = 1\s+AND \(kc.start_date/',$rows),array('/COUNT\(\*\) FROM wp_sitetop_shortlink_visits WHERE campaign_id = 11 /',3),array('/COUNT\(\*\) FROM wp_sitetop_shortlink_visits WHERE campaign_id = 14 /',5));
$out['camps']=sitetop_cn_camps_cho_pool();
$OPT['sitetop_cn_bat']=0; $r=sitetop_cn_rest_camps(req_ky(array(),'sitetop.one',sitetop_cn_secret())); $out['camps_tat']=$r; $OPT['sitetop_cn_bat']=1;
$r=sitetop_cn_rest_camps(req_ky(array(),'sitetop.one','khoa-sai')); $out['camps_sai_khoa']=is_wp_error($r)?$r->ma:'lot';
// n) báo pool có mã — chạy ở shutdown
$wpdb->rules=array(array('/SELECT shortlink_id FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDBAO00001\'/',(object)array('shortlink_id'=>44)),array('/SELECT shortlink_id FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDBAO00002\'/',(object)array('shortlink_id'=>45)));
$HOOKS=array(); $GOI=array(); $HTTP=array(tra_ok(array('ok'=>true)));
sitetop_cn_bao_pool_co_ma('SIDBAO00002'); $out['bao_noi_bo']=isset($HOOKS['shutdown'])?count($HOOKS['shutdown']):0;
sitetop_cn_bao_pool_co_ma('SIDBAO00001'); $cb=$HOOKS['shutdown'][0]??null; if($cb)call_user_func($cb);
$out['bao_pool']=array('goi'=>count($GOI),'url'=>$GOI[0]['url']??'','than'=>isset($GOI[0])?json_decode($GOI[0]['args']['body'],true):null);
echo json_encode($out, JSON_UNESCAPED_UNICODE);
NGUON;

function __cn_chay( $khung, $kich_ban, $vai_tro, $module ) {
    $f = sys_get_temp_dir() . '/st-cn-' . $vai_tro . '-' . getmypid() . '.php';
    file_put_contents( $f, $khung . "\n" . $kich_ban );
    $r = (string) shell_exec( 'php ' . escapeshellarg( $f ) . ' ' . escapeshellarg( $vai_tro ) . ' ' . escapeshellarg( $module ) . ' 2>&1' );
    @unlink( $f );
    $k = json_decode( trim( $r ), true );
    return array( $k, $r );
}

list( $P, $__cn_raw_p ) = __cn_chay( $__cn_khung, $__cn_pool,  'pool',  $__cn_goc . '/includes/cau-noi-net-one.php' );
assert_true( is_array( $P ), 'POOL: chay duoc module trong tien trinh rieng. Ra: ' . substr( $__cn_raw_p, 0, 600 ) );
if ( is_array( $P ) ) {
    assert_equals( 'pool', $P['vai_tro'], 'Vai tro pool theo hang' );
    assert_equals( 'https://sitetop.net', $P['doi_tac'], 'Pool mac dinh tro ve sitetop.net' );
    // a) ký/xác thực
    assert_equals( array( 'x' => 1 ), $P['xt_ok'], 'Chu ky dung + host dung → nhan payload' );
    assert_equals( 'cn_bad_sign', $P['xt_sai_khoa'], 'Khoa sai → tu choi' );
    assert_equals( 'cn_stale', $P['xt_cu'], 'Chu ky qua 10 phut → tu choi' );
    assert_equals( 'cn_nguon_la', $P['xt_host_la'], 'Host khong phai doi tac → tu choi' );
    assert_equals( array( 'x' => 2 ), $P['xt_form'], 'Body dang form payload= (WAF chan JSON) van xac thuc duoc' );
    assert_equals( 'cn_tat', $P['xt_chua_khoa'], 'Chua co khoa → 503, khong bao gio nhan' );
    // b) gọi ra
    assert_equals( 'cn_khong_dau', $P['goi_khong_dau'], 'HTTP 200 nhung thieu dau st=1 → coi la that bai (bai hoc 6)' );
    assert_equals( 5, $P['goi_ok']['x'] ?? null, 'Phan hoi co dau → tra mang' );
    assert_equals( 'https://sitetop.net/wp-json/sitetop-cn/v1/camps', $P['goi_url'], 'URL cong REST cua doi tac' );
    assert_true( $P['goi_ky_dung'], 'Chu ky gui di = HMAC(ts.body) voi khoa chung' );
    assert_equals( 'sitetop.one', $P['goi_host'], 'Khai host cua minh de ben kia doi chieu' );
    assert_true( $P['goi_ua_trinh_duyet'], 'UA gia trinh duyet (WAF chan UA WordPress/)' );
    assert_true( $P['goi_415']['ok'] && $P['goi_415']['so_lan'] === 2 && $P['goi_415']['lan2_form'], 'HTTP 415 → gui lai dang form payload=, lan 2 thanh cong. Ra: ' . json_encode( $P['goi_415'] ) );
    assert_equals( array( 'cn_http_500', 'hong' ), $P['goi_500'], 'HTTP 500 → WP_Error mang message cua ben kia' );
    // c) OFF
    assert_true( ! empty( $P['off']['kq']['off'] ) && $P['off']['goi'] === 0, 'OFF: khong goi nguon' );
    assert_equals( 1, count( $P['off']['pause'] ), 'OFF: tam dung NGAY moi camp .net dang active (UPDATE ... IN (501,502))' );
    assert_false( $P['off']['transient_con'], 'OFF: xoa cache camp du dieu kien de phan phoi doi ngay' );
    // d) ON
    $on = $P['on'];
    assert_equals( 'ok', $on['kq']['ket_qua'] ?? '', 'ON: dong bo thanh cong' );
    assert_equals( 1, count( $on['tao'] ), 'Camp #13 chua co → TAO 1 (camp #11 da co → cap nhat)' );
    $t = $on['tao'][0] ?? array();
    assert_equals( '[sitetop.net#13] Camp direct', $t['title'] ?? '', 'Tieu de camp tao moi mang tien to [sitetop.net#id] (quy uoc camp cau noi)' );
    assert_equals( 'traffic_direct', $t['task_type'] ?? '', 'Loai camp giu dung' );
    assert_equals( '2step', $t['traffic_type'] ?? '', 'Traffic type giu dung' );
    assert_equals( 2000, $t['price_per_view'] ?? 0, 'Gia KH lien ket tra = gia nguon' );
    assert_equals( $on['fed'], $t['customer_id'] ?? 0, 'Camp thuoc tai khoan lien ket nguon_sitetop_net' );
    assert_true( in_array( 'tao_user:nguon_sitetop_net:customer', $on['vet'], true ) && in_array( 'sync_bal:' . $on['fed'], $on['vet'], true ), 'Tu tao tai khoan khach hang lien ket + dong customer_balance (phan phoi moi thay camp). Vet: ' . json_encode( $on['vet'] ) );
    assert_equals( 2, count( $on['sua'] ), 'Cap nhat 2 camp (501 cu + 777 moi) qua sitetop_update_campaign' );
    $ids = array_map( function ( $x ) { return $x[0]; }, $on['sua'] );
    assert_true( in_array( 501, $ids, true ) && in_array( 777, $ids, true ), 'Dung id camp pool. Ra: ' . json_encode( $ids ) );
    $s501 = null; foreach ( $on['sua'] as $x ) if ( $x[0] === 501 ) $s501 = $x[1];
    assert_equals( 'active', $s501['status'] ?? '', 'Camp dang nhan → status active' );
    assert_equals( '[sitetop.net#11] Camp tu khoa', $s501['title'] ?? '', 'Tieu de cap nhat theo nguon, giu tien to' );
    assert_equals( 1, $s501['kw_bat_go_tay'] ?? 0, 'Co bat go tay sao chep theo nguon' );
    assert_equals( 2, $s501['serp_page'] ?? 0, 'serp_page sao chep theo nguon' );
    assert_equals( '["https://a.com/x"]', $s501['destination_urls'] ?? '', 'destination_urls sao chep theo nguon' );
    assert_equals( array( 11 => 501, 12 => 502, 13 => 777 ), $on['map'], 'Map net_id → one_id luu dung (12 giu de con tam dung/ bat lai). Ra: ' . json_encode( $on['map'] ) );
    assert_equals( 1, count( $on['pause'] ), 'Camp #12 khong con trong danh sach nguon → tam dung ngay (UPDATE ... IN (502))' );
    assert_false( $on['transient_con'], 'ON: xoa cache camp du dieu kien' );
    assert_true( $P['nguon_tat']['so_camp'] === 0 && $P['nguon_tat']['tao'] === 0 && $P['nguon_tat']['pause'] === 1, 'Nguon tat cau noi (bat=false) → khong tao gi, tam dung het. Ra: ' . json_encode( $P['nguon_tat'] ) );
    // e) trước xác minh
    $x = $P['xm_ok'];
    assert_true( $x['kq'] === true, 'Nguon gat → true (luong .one chay tiep)' );
    assert_equals( array( 'sid' => 'SIDPOOL0001', 'code' => 'abcd1234' ), $x['goi_than'], 'Gui dung sid + ma khach go sang nguon' );
    assert_equals( 'https://sitetop.net/wp-json/sitetop-cn/v1/xac-minh', $x['goi_url'], 'Goi dung cong xac-minh' );
    assert_true( count( $x['update'] ) === 1 && strpos( $x['update'][0], '"verify_code":"abcd1234"' ) !== false && strpos( $x['update'][0], '"from_google":1' ) !== false && strpos( $x['update'][0], '"url_matched":1' ) !== false && strpos( $x['update'][0], '"step":"code_shown"' ) !== false, 'Ghi ma + co from_google/url_matched/code_shown vao luot .one. Ra: ' . json_encode( $x['update'] ) );
    sort( $x['tr'] );
    assert_equals( array( 'sitetop_cn_ma_SIDPOOL0001', 'sitetop_verify_code_SIDPOOL0001', 'sitetop_widget_code_ready_SIDPOOL0001' ), $x['tr'], 'Arm du 3 transient: code_ready, verify_code, marker ma-do-nguon-cap' );
    assert_equals( array( 'abcd1234', 1, 1, 'code_shown', '2026-10-07 15:00:00' ), $x['visit'], 'Mutate $visit tai cho de cac chot sau dung gia tri moi' );
    $l = $P['xm_nguon_lac'];
    assert_true( $l['ma'] === 'too_fast' && $l['tin'] === 'Chưa đủ thời gian' && ( $l['du']['remaining'] ?? 0 ) === 12, 'Nguon lac → tra DUNG ma loi + thong bao + du lieu cua nguon. Ra: ' . json_encode( $l, JSON_UNESCAPED_UNICODE ) );
    assert_true( $l['update'] === 0 && $l['tr'] === 0 && $l['verify_code'] === null, 'Nguon lac → KHONG ghi gi vao luot .one' );
    assert_equals( 'cn_loi', $P['xm_mat_mang'], 'Mat mang → loi rieng, bao khach thu lai' );
    assert_true( $P['xm_noi_bo']['kq'] === null && $P['xm_noi_bo']['goi'] === 0, 'Camp noi bo → null, khong goi di dau' );
    assert_equals( 'wrong_code', $P['xm_ma_rong'], 'Ma rong → tu choi tai cho, khong goi nguon' );
    // f) gán camp
    $g = $P['gan_ok'];
    assert_true( $g['id'] === 501 && $g['update'] === 0, 'Nguon nhan phien → giu nguyen camp .net' );
    assert_true( ( $g['than']['camp_id'] ?? 0 ) === 11 && ( $g['than']['sid'] ?? '' ) === 'SIDPOOL0004' && ( $g['than']['ip'] ?? '' ) === '1.2.3.4' && ( $g['than']['ua'] ?? '' ) === 'UA-test', 'Dang ky phien bang ID CAMP BEN NGUON (11), cung sid, IP + UA khach. Ra: ' . json_encode( $g['than'] ) );
    $d = $P['gan_doi'];
    assert_true( $d['id'] === 900, 'Nguon khong nhan → doi sang camp khac' );
    assert_true( in_array( 'chon_lai:tru_501', $d['vet'], true ), 'Chon lai co LOAI camp vua hong' );
    assert_equals( 1, count( $d['pause501'] ), 'Nguon bao camp_khong_nhan → tam dung camp do tai pool ngay' );
    assert_equals( 1, count( $d['visit_doi'] ), 'Luot duoc gan lai campaign_id = camp moi' );
    assert_true( $P['gan_het'] === null, 'Khong con camp nao → null (goi redirect ve link goc)' );
    assert_true( $P['gan_off']['id'] === 900 && $P['gan_off']['goi'] === 0, 'Cong tac OFF → khong goi nguon, doi camp' );
    assert_true( $P['gan_noi_bo']['id'] === 900 && $P['gan_noi_bo']['goi'] === 0, 'Camp noi bo → tra nguyen, khong goi' );
    // g/h
    $h = $P['hoi'];
    assert_true( $h['goi'] === 1 && $h['ready'] && $h['marker'] && $h['code_shown'] === 1, 'Hoi nguon: co_ma → arm code_ready + marker + step code_shown. Ra: ' . json_encode( $h ) );
    assert_equals( 0, $P['hoi_throttle'], 'Hoi lai trong 6 giay → khong goi nguon (throttle)' );
    assert_true( $P['danh_dau_noi_bo']['kq'] === false && $P['danh_dau_noi_bo']['tr'] === 0, 'Nguon bao sang-sang cho phien camp NOI BO → bo qua (khong ai mo cua bang tin hieu la)' );
}

list( $N, $__cn_raw_n ) = __cn_chay( $__cn_khung, $__cn_nguon, 'nguon', $__cn_goc . '/includes/cau-noi-net-one.php' );
assert_true( is_array( $N ), 'NGUON: chay duoc module trong tien trinh rieng. Ra: ' . substr( $__cn_raw_n, 0, 600 ) );
if ( is_array( $N ) ) {
    assert_equals( 'nguon', $N['vai_tro'], 'Vai tro nguon theo hang' );
    assert_equals( 'https://sitetop.one', $N['doi_tac'], 'Nguon mac dinh tro ve sitetop.one' );
    assert_equals( array( true, false, false ), $N['la_pool'], 'Luot pool = dung shortlink noi bo (44); 45/null → khong' );
    $m = $N['dk_moi'];
    assert_equals( 'moi', $m['kq']['trang_thai'] ?? '', 'Phien chua co → tao luot guong' );
    assert_true( count( $m['insert'] ) === 1 && strpos( $m['insert'][0], '"shortlink_id":44' ) !== false && strpos( $m['insert'][0], '"user_id":9' ) !== false && strpos( $m['insert'][0], '"campaign_id":11' ) !== false && strpos( $m['insert'][0], '"order_id":5' ) !== false && strpos( $m['insert'][0], '"ip_address":"1.2.3.4"' ) !== false && strpos( $m['insert'][0], '"original_ip":"1.2.3.4"' ) !== false && strpos( $m['insert'][0], '"session_id":"SIDMOI00001"' ) !== false && strpos( $m['insert'][0], '"step":"started"' ) !== false, 'Luot guong: shortlink/user pool, camp + don, IP KHACH (khong phai IP may chu pool), cung session_id. Ra: ' . json_encode( $m['insert'] ) );
    assert_true( in_array( 'vet:tu_pool:sitetop.one', $m['vet'], true ), 'Ghi dau vet tu_pool de admin soi' );
    assert_true( $N['dk_co_ma']['kq']['trang_thai'] === 'co_ma' && $N['dk_co_ma']['update'] === 0, 'Phien DA CO MA → khong dat lai (khach dang cam ma)' );
    assert_equals( 'da_xong', $N['dk_xong']['trang_thai'] ?? '', 'Phien da verified → da_xong, khong dung' );
    $dl = $N['dk_dat_lai'];
    assert_true( $dl['kq']['trang_thai'] === 'dat_lai' && count( $dl['update'] ) === 1 && strpos( $dl['update'][0], '"step":"started"' ) !== false && strpos( $dl['update'][0], '"url_matched":0' ) !== false && strpos( $dl['update'][0], '"ip_address":"5.6.7.8"' ) !== false && strpos( $dl['update'][0], 'created_at' ) === false, 'Phien chua co ma → dat lai co nhu nhanh tai dung, GIU created_at. Ra: ' . json_encode( $dl['update'] ) );
    assert_equals( array(), $dl['tr_con'], 'Dat lai → xoa transient ma/cd cua phien' );
    assert_equals( 'phien_khac', $N['dk_phien_noi_bo'], 'Session trung voi luot NOI BO cua .net → tu choi (khong de pool chiem luot that)' );
    assert_equals( 'camp_khong_nhan', $N['dk_camp_khong_nhan'], 'Camp khong active/khong cho phep → tu choi' );
    assert_equals( 'sid_sai', $N['dk_sid_sai'], 'sid lung tung → tu choi' );
    assert_equals( 'ip_sai', $N['dk_ip_sai'], 'IP khong hop le → tu choi' );
    $x = $N['xm_ok'];
    assert_true( ( $x['kq']['ok'] ?? false ) === true && ( $x['kq']['trang_thai'] ?? '' ) === 'verified' && ( $x['kq']['customer_paid'] ?? 0 ) === 1, 'Xac minh ok → ok + verified + customer_paid. Ra: ' . json_encode( $x['kq'] ) );
    assert_true( in_array( 'verify:SIDXM000001:abcd1234:0', $x['vet'], true ), 'Goi NGUYEN sitetop_verify_and_pay(sid, ma) cua .net (khong phai che do chot som)' );
    assert_true( $N['xm_sai']['ok'] === false && $N['xm_sai']['ma_loi'] === 'wrong_code', 'Ma sai → tra loi cua verify_and_pay' );
    assert_true( $N['xm_lai_dung_ma']['ok'] === true && $N['xm_lai_dung_ma']['trang_thai'] === 'da_xong_truoc', 'already_used + CUNG ma dung → ok (idempotent khi pool goi lai)' );
    assert_true( $N['xm_lai_sai_ma']['ok'] === false && $N['xm_lai_sai_ma']['ma_loi'] === 'already_used', 'already_used + ma khac → khong ok' );
    assert_true( $N['xm_khong_pool']['kq']['ma_loi'] === 'khong_phai_pool' && $N['xm_khong_pool']['goi_verify'] === 0, 'Pool KHONG xac minh duoc luot noi bo cua .net' );
    $c = $N['camps'];
    assert_equals( 1, count( $c ), 'Danh sach camp cho pool: chi camp #11 (12 rong keyword, 13 khach het tien, 14 het han muc ngay). Ra: ' . json_encode( array_column( $c, 'id' ) ) );
    assert_true( ( $c[0]['id'] ?? 0 ) === 11 && ( $c[0]['daily_traffic'] ?? 0 ) === 37 && ( $c[0]['campaign_type'] ?? '' ) === 'keyword_search' && ( $c[0]['price_per_view'] ?? 0 ) == 1500, 'Camp gui di: daily_traffic = con lai hom nay (40-3), loai + gia dung. Ra: ' . json_encode( $c[0] ?? null ) );
    assert_true( ( $N['camps_tat']['bat'] ?? true ) === false && empty( $N['camps_tat']['camps'] ), 'Cong tac nguon OFF → bat=false, khong gui camp' );
    assert_equals( 'cn_bad_sign', $N['camps_sai_khoa'], 'Cong camps doi chu ky dung' );
    assert_equals( 0, $N['bao_noi_bo'], 'Cap ma cho luot NOI BO → khong bao pool' );
    assert_true( $N['bao_pool']['goi'] === 1 && $N['bao_pool']['url'] === 'https://sitetop.one/wp-json/sitetop-cn/v1/san-sang' && ( $N['bao_pool']['than']['sid'] ?? '' ) === 'SIDBAO00001' && ! isset( $N['bao_pool']['than']['code'] ), 'Cap ma cho luot pool → bao pool o shutdown, chi gui sid, KHONG gui ma. Ra: ' . json_encode( $N['bao_pool'] ) );
}
