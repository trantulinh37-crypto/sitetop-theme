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
function sanitize_key($s){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$s));} function absint($v){return abs((int)$v);}
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
  array('id'=>11,'widget'=>array('text'=>'LẤY MÃ NET','color'=>'#112233','tcolor'=>'#ffffff','icon'=>'https://sitetop.net/icon.png'),'title'=>'Camp tu khoa','keyword'=>'mua xe','target_url'=>'https://a.com/x','destination_urls'=>'["https://a.com/x"]','traffic_type'=>'1step','campaign_type'=>'keyword_search','price_per_view'=>1500,'onsite_time'=>70,'countdown_seconds'=>30,'daily_traffic'=>40,'quantity'=>1000,'kw_bat_go_tay'=>1,'serp_page'=>2),
  array('id'=>13,'widget'=>array('text'=>'LẤY MÃ NET','color'=>'#112233','tcolor'=>'#ffffff','icon'=>'https://sitetop.net/icon.png','la'=>'bo'),'title'=>'Camp direct','keyword'=>'','target_url'=>'https://b.com/','traffic_type'=>'2step','campaign_type'=>'traffic_direct','price_per_view'=>2000,'onsite_time'=>80,'countdown_seconds'=>20,'daily_traffic'=>5,'quantity'=>50,'step2_target_url'=>'https://b.com/2','khong_doi_cd'=>1),
);
$HTTP=array(tra_ok(array('ok'=>true,'bat'=>true,'camps'=>$camps))); $wpdb->log=array(); $TR['sitetop_eligible_campaigns']=array('x'); $VET=array();
$r=sitetop_cn_dong_bo('tay');
$out['on']=array('kq'=>$r,'tao'=>$TAO,'sua'=>$SUA,'map'=>$OPT['sitetop_cn_map'],'kieu_nut'=>$OPT['ttplb_widget_style']??null,'pause'=>tim_log($wpdb,'/query:UPDATE .*paused.* WHERE id IN \(502\)/'),'transient_con'=>array_key_exists('sitetop_eligible_campaigns',$TR),'vet'=>$VET,'fed'=>$OPT['sitetop_cn_fed_customer']);
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
// o) nguồn báo "camp vừa đổi" → cổng /dong-bo chạy đồng bộ ngay (ký thật)
$OPT['sitetop_cn_map']=array(11=>501,13=>777); $OPT['sitetop_cn_nhan']=1; $TAO=array(); $SUA=array(); $wpdb->log=array();
$wpdb->rules=array(array('/SELECT id FROM wp_sitetop_keyword_campaigns WHERE id = 501/',501),array('/SELECT id FROM wp_sitetop_keyword_campaigns WHERE id = 777/',777),array('/title LIKE/',null));
$HTTP=array(tra_ok(array('ok'=>true,'bat'=>true,'camps'=>$camps)));
$r=sitetop_cn_rest_dong_bo(req_ky(array('ly_do'=>'update_campaign'),'sitetop.net',$S)); $out['dong_bo_tu_nguon']=array('kq'=>$r,'sua'=>count($SUA),'tao'=>count($TAO));
$r=sitetop_cn_rest_dong_bo(req_ky(array('ly_do'=>'x'),'sitetop.net','khoa-sai')); $out['dong_bo_sai_khoa']=is_wp_error($r)?$r->ma:'lot';
// p) GƯƠNG CHỐT SỚM (12/10/2026) — chạy thật với wpdb giả: đủ nhánh
function sitetop_get_customer_balance_amount($id){return $id===300?900000:($id===301?1000:false);}
$V=function($id,$sid,$cp=0,$step='code_shown',$title='[sitetop.net#11] Camp tu khoa',$kh=300,$cid=501){return array('/SELECT v.id, v.campaign_id, v.shortlink_id, v.step, v.customer_paid, v.verified_at, v.code_shown_at,.*WHERE v.session_id = \''.$sid.'\'/s',(object)array('id'=>$id,'campaign_id'=>$cid,'shortlink_id'=>7,'step'=>$step,'customer_paid'=>$cp,'verified_at'=>null,'code_shown_at'=>'2026-10-07 14:59:00','camp_title'=>$title,'customer_id'=>$kh,'price_per_view'=>1500,'order_id'=>61));};
$wpdb->rules=array($V(41,'SIDCS000001'),array('/WHERE id = 41 FOR UPDATE/',(object)array('id'=>41,'customer_paid'=>0,'step'=>'code_shown','verified_at'=>null)),array('/customer_balance WHERE user_id = 300 FOR UPDATE/',(object)array('balance'=>900000)),
  $V(42,'SIDCS000002',1),$V(43,'SIDCS000003',0,'code_shown','Camp noi bo',300,900),$V(44,'SIDCS000004',0,'code_shown','[sitetop.net#11] x',301),array('/WHERE id = 44 FOR UPDATE/',(object)array('id'=>44,'customer_paid'=>0,'step'=>'code_shown','verified_at'=>null)),
  $V(45,'SIDCS000005'),array('/WHERE id = 45 FOR UPDATE/',(object)array('id'=>45,'customer_paid'=>1,'step'=>'code_shown','verified_at'=>null)),
  array('/SELECT id, campaign_id, step, code_shown_at FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDCS000001\'/',(object)array('id'=>41,'campaign_id'=>501,'step'=>'started','code_shown_at'=>null)));
$OPT['sitetop_cn_map']=array(11=>501); $wpdb->log=array(); $VET=array(); $TR=array();
$r=sitetop_cn_rest_san_sang(req_ky(array('sid'=>'SIDCS000001','chot_som'=>1),'sitetop.net',$S));
$out['cs_san_sang']=array('kq'=>$r,'ready'=>!empty($TR['sitetop_widget_code_ready_SIDCS000001']),'log'=>array_values(array_filter($wpdb->log,function($l){return preg_match('/^(query|insert|update):/',$l) && !preg_match('/update:wp_sitetop_shortlink_visits:\{"step":"code_shown"/',$l);})),'vet'=>$VET);
$wpdb->log=array(); $out['cs_da_tinh']=array('kq'=>sitetop_cn_chot_som_guong('SIDCS000002'),'ghi'=>count(tim_log($wpdb,'/^(query|insert|update):/')));
$wpdb->log=array(); $out['cs_noi_bo']=array('kq'=>sitetop_cn_chot_som_guong('SIDCS000003'),'ghi'=>count(tim_log($wpdb,'/^(query|insert|update):/')));
$wpdb->log=array(); $out['cs_ngheo']=array('kq'=>sitetop_cn_chot_som_guong('SIDCS000004'),'log'=>tim_log($wpdb,'/^(query|insert|update):/'));
$wpdb->log=array(); $out['cs_khoa_da_tinh']=array('kq'=>sitetop_cn_chot_som_guong('SIDCS000005'),'log'=>tim_log($wpdb,'/^(query|insert|update):/'));
$out['cs_khong_phien']=sitetop_cn_chot_som_guong('SIDKHONGCO1'); $out['cs_sid_sai']=sitetop_cn_chot_som_guong('sid sai');
$wpdb->log=array(); $r=sitetop_cn_rest_san_sang(req_ky(array('sid'=>'SIDCS000001'),'sitetop.net',$S)); $out['cs_san_sang_khong_co']=array('cs'=>$r['chot_som']??null,'giao_dich'=>count(tim_log($wpdb,'/START TRANSACTION/')));
// p2) hỏi trạng thái → nguồn báo customer_paid → gương (nhận ra qua câu SELECT của hàm gương)
$wpdb->rules[]=array('/SELECT campaign_id FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDCS000003\'/',501);
$TR=array(); $HTTP=array(tra_ok(array('ok'=>true,'co_ma'=>true,'customer_paid'=>1))); $wpdb->log=array();
sitetop_cn_hoi_co_ma('SIDCS000003'); $out['hoi_cs']=count(tim_log($wpdb,'/row:SELECT v.id, v.campaign_id.*SIDCS000003/s'));
// p3) đồng bộ: lưu số liệu + đối soát phiên chốt sớm
$OPT['sitetop_cn_nhan']=1; $OPT['sitetop_cn_map']=array(11=>501); $TAO=array(); $SUA=array();
$wpdb->rules[]=array('/SELECT id FROM wp_sitetop_keyword_campaigns WHERE id = 501/',501); $wpdb->rules[]=array('/SELECT completed FROM wp_sitetop_keyword_campaigns WHERE id = 501/',7);
$wpdb->rules[]=array('/SELECT session_id FROM wp_sitetop_shortlink_visits WHERE campaign_id = 501 AND session_id IN \(\'SIDCS000001\',\'SIDCS000002\'\) AND customer_paid = 0/',array('SIDCS000001'));
$sl=array('ngay'=>'2026-10-07','gio'=>'2026-10-07 14:58:00','daily'=>500,'noi_bo'=>53,'pool'=>313,'tong'=>366,'chot_som'=>11,'completed'=>361,'con'=>134,'sids'=>array('SIDCS000001','SIDCS000002','sid xau'));
$camps2=array(array('id'=>11,'title'=>'Camp tu khoa','keyword'=>'mua xe','target_url'=>'https://a.com','traffic_type'=>'1step','campaign_type'=>'keyword_search','price_per_view'=>1500,'countdown_seconds'=>30,'onsite_time'=>70,'daily_traffic'=>447,'quantity'=>900,'completed'=>361,'so_lieu'=>$sl));
$HTTP=array(tra_ok(array('ok'=>true,'bat'=>true,'camps'=>$camps2))); $wpdb->log=array(); unset($OPT['sitetop_cn_so_lieu']);
$r=sitetop_cn_dong_bo('cron'); $out['sl_luu']=array('kq_ok'=>$r['ket_qua']??null,'opt'=>$OPT['sitetop_cn_so_lieu']??null,'guong'=>count(tim_log($wpdb,'/UPDATE wp_sitetop_customer_balance SET balance = balance - 1500.*user_id = 300/')),'daily_cot'=>$SUA[0][1]['daily_traffic']??null);
// p4) hiển thị
$out['ht']=sitetop_cn_so_lieu_hien_thi(501,313,305); $out['ht_khong']=sitetop_cn_so_lieu_hien_thi(999,1,1);
$OPT['sitetop_cn_so_lieu'][501]['ngay']='2026-10-06'; $out['ht_ngay_moi']=sitetop_cn_so_lieu_hien_thi(501,2,305);
// p5) nguồn báo hết hạn mức → tạm dừng camp gương, chọn camp khác
$CAMP_KHAC=$camp900; $HTTP=array(tra_ok(array('ok'=>false,'ma_loi'=>'het_han_muc','thong_bao'=>'du han'))); $GOI=array(); $wpdb->log=array(); $VET=array();
$r=sitetop_cn_gan_camp_pool($camp501,'SIDPOOL0010','1.2.3.4',(object)array('id'=>7)); $out['gan_het_han']=array('id'=>$r?$r->id:null,'vet'=>$VET,'pause501'=>count(tim_log($wpdb,'/^update:wp_sitetop_keyword_campaigns:\{"status":"paused"\}:\{"id":501/')));
echo json_encode($out, JSON_UNESCAPED_UNICODE);
POOL;

/* ================= KHẲNG ĐỊNH POOL — gương chốt sớm + số liệu chuẩn (12/10/2026) ================= */
function tim_log_arr( $arr, $re ) { $o = array(); foreach ( (array) $arr as $l ) if ( preg_match( $re, $l ) ) $o[] = $l; return $o; }
function __cn_kd_pool_so_lieu( $P ) {
    $cs = $P['cs_san_sang'];
    assert_true( ( $cs['kq']['ok'] ?? false ) === true && ( $cs['kq']['chot_som'] ?? '' ) === 'da_chot' && $cs['ready'] === true, 'Cong /san-sang + chot_som=1 → danh dau co ma VA guong chot som. Ra: ' . json_encode( $cs['kq'] ) );
    $log = implode( "\n", $cs['log'] );
    $thu_tu = array( 'query:START TRANSACTION', 'UPDATE wp_sitetop_customer_balance SET balance = balance - 1500, total_spent = total_spent + 1500', 'user_id = 300 AND balance >= 1500', 'insert:wp_sitetop_customer_transactions:', '"amount":-1500', '"reference_id":41', 'UPDATE wp_sitetop_customer_orders SET amount_spent = amount_spent + 1500, completed = completed + 1', 'WHERE id = 61', 'UPDATE wp_sitetop_keyword_campaigns SET completed = completed + 1 WHERE id = 501', 'UPDATE wp_sitetop_user_shortlinks SET total_completed = total_completed + 1 WHERE id = 7', 'update:wp_sitetop_shortlink_visits:{"customer_paid":1}:{"id":41}', 'query:COMMIT' );
    $vt = -1; $dung = true;
    foreach ( $thu_tu as $m ) { $x = strpos( $log, $m, $vt + 1 ); if ( $x === false ) { $dung = false; break; } $vt = $x; }
    assert_true( $dung, 'Guong chot som: DUNG TUNG BUOC nhu sitetop_verify_and_pay khi tru khach (khoa, tru so du co chot >= cost, so giao dich, don, completed camp, total_completed link, customer_paid=1, COMMIT). Log: ' . substr( $log, 0, 900 ) );
    assert_true( in_array( 'vet:guong_chot_som:1500', $cs['vet'], true ), 'Ghi dau vet guong_chot_som' );
    assert_true( $P['cs_da_tinh']['kq'] === 'da_tinh' && $P['cs_da_tinh']['ghi'] === 0, 'Da customer_paid=1 → da_tinh, KHONG ghi gi (idempotent)' );
    assert_true( $P['cs_noi_bo']['kq'] === 'khong_phai_nguon' && $P['cs_noi_bo']['ghi'] === 0, 'Camp noi bo cua pool → khong dung' );
    assert_true( $P['cs_ngheo']['kq'] === 'khong_du_tien' && end( $P['cs_ngheo']['log'] ) === 'query:ROLLBACK' && count( tim_log_arr( $P['cs_ngheo']['log'], '/insert:|balance - /' ) ) === 0, 'KH lien ket khong du tien → ROLLBACK, khong tru, khong ghi so. Log: ' . json_encode( $P['cs_ngheo']['log'] ) );
    assert_true( $P['cs_khoa_da_tinh']['kq'] === 'da_tinh' && end( $P['cs_khoa_da_tinh']['log'] ) === 'query:ROLLBACK', 'Doc lai SAU khoa FOR UPDATE thay da tinh (2 request cung luc) → ROLLBACK, khong tru hai lan' );
    assert_true( $P['cs_khong_phien'] === 'khong_co_phien' && $P['cs_sid_sai'] === 'khong_co_phien', 'Khong co phien / sid sai → khong lam gi' );
    assert_true( $P['cs_san_sang_khong_co']['cs'] === '' && $P['cs_san_sang_khong_co']['giao_dich'] === 0, '/san-sang KHONG co chot_som → chi danh dau co ma, khong guong' );
    assert_equals( 1, $P['hoi_cs'], 'Hoi trang thai, nguon bao customer_paid=1 → goi guong chot som' );
    $l = $P['sl_luu'];
    assert_true( $l['kq_ok'] === 'ok' && $l['daily_cot'] === 447, 'Dong bo van chay nhu cu, daily_traffic cot cua pool = phan nguon chia (447)' );
    assert_equals( array( 'ngay' => '2026-10-07', 'gio' => '2026-10-07 14:58:00', 'daily' => 500, 'noi_bo' => 53, 'pool' => 313, 'tong' => 366, 'chot_som' => 11, 'completed' => 361, 'pool_completed' => 7 ), $l['opt'][501] ?? null, 'Luu so lieu chuan theo id camp pool + moc completed cua pool luc dong bo' );
    assert_equals( 1, $l['guong'], 'Doi soat: phien nguon da chot ma pool chua tinh (SIDCS000001) → guong; phien da tinh (SIDCS000002) va sid xau → bo qua' );
    assert_equals( array( 'hom_nay' => 366, 'daily' => 500, 'con_lai' => 134, 'noi_bo' => 53, 'pool' => 313, 'completed' => 659, 'gio' => '2026-10-07 14:58:00' ), $P['ht'], 'Hien thi camp guong = DUNG SO CUA NGUON: noi bo 53 + pool tu dem 313 = 366/500; completed = 361 nguon + (305 - 7) pool lam them. Ra: ' . json_encode( $P['ht'] ) );
    assert_true( $P['ht_khong'] === null, 'Camp khong co so lieu → null (hien nhu cu)' );
    assert_true( $P['ht_ngay_moi']['hom_nay'] === 2 && $P['ht_ngay_moi']['con_lai'] === 498, 'SANG NGAY MOI chua dong bo: noi bo cu khong tinh, chi con phan pool hom nay (2/500)' );
    $g = $P['gan_het_han'];
    assert_true( $g['id'] === 900 && $g['pause501'] === 1 && in_array( 'chon_lai:tru_501', $g['vet'], true ), 'Nguon bao het_han_muc → tam dung camp guong, chon camp khac. Ra: ' . json_encode( $g ) );
}

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
$wpdb->rules[]=array('/SELECT id, shortlink_id, verify_code, step, verified_at FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDXM000001\'/',(object)array('id'=>31,'shortlink_id'=>44,'verify_code'=>'ABCD1234','step'=>'code_shown','verified_at'=>null));
$wpdb->rules[]=array('/SELECT step, customer_paid, completion_time FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDXM000001\'/',(object)array('step'=>'verified','customer_paid'=>1,'completion_time'=>88));
$KQ_VERIFY=array('success'=>true); $VET=array(); $out['xm_ok']=array('kq'=>sitetop_cn_xac_minh_luot_guong('SIDXM000001','abcd1234'),'vet'=>$VET);
$KQ_VERIFY=new WP_Error('wrong_code','Mã xác minh không đúng'); $out['xm_sai']=sitetop_cn_xac_minh_luot_guong('SIDXM000001','zzzz');
$KQ_VERIFY=new WP_Error('already_used','Đã xác minh'); $out['xm_lai_dung_ma']=sitetop_cn_xac_minh_luot_guong('SIDXM000001','ABCD1234'); $out['xm_lai_sai_ma']=sitetop_cn_xac_minh_luot_guong('SIDXM000001','KHAC');
$wpdb->rules[]=array('/SELECT id, shortlink_id, verify_code, step, verified_at FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDXM000002\'/',(object)array('id'=>32,'shortlink_id'=>45,'verify_code'=>'ABCD1234','step'=>'code_shown','verified_at'=>null));
$wpdb->rules[]=array('/SELECT id, shortlink_id, verify_code, step, verified_at FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDXM000003\'/',(object)array('id'=>33,'shortlink_id'=>44,'verify_code'=>'ABCD1234','step'=>'verified','verified_at'=>'2026-10-07 23:17:45'));
$KQ_VERIFY=new WP_Error('code_not_ready','Code chưa sẵn sàng'); $VET=array(); $out['xm_da_chot_dung_ma']=array('kq'=>sitetop_cn_xac_minh_luot_guong('SIDXM000003','abcd1234'),'goi_verify'=>count($VET)); $out['xm_da_chot_sai_ma']=sitetop_cn_xac_minh_luot_guong('SIDXM000003','SAI');
$VET=array(); $out['xm_khong_pool']=array('kq'=>sitetop_cn_xac_minh_luot_guong('SIDXM000002','ABCD1234'),'goi_verify'=>count($VET));
// l) camp cho pool
$rows=array(
 (object)array('id'=>11,'title'=>'KW ok','keyword'=>'mua xe','target_url'=>'https://a.com','destination_urls'=>null,'traffic_type'=>'1step','campaign_type'=>'keyword_search','task_type'=>'keyword_search','price_per_view'=>1500,'countdown_seconds'=>30,'onsite_time'=>70,'fixed_code'=>null,'daily_traffic'=>40,'order_daily_traffic'=>40,'quantity'=>1000,'completed'=>3,'updated_at'=>'2026-10-07','customer_id'=>201),
 (object)array('id'=>12,'title'=>'KW rong','keyword'=>'  ','target_url'=>'https://a.com','destination_urls'=>null,'traffic_type'=>'1step','campaign_type'=>'keyword_search','task_type'=>'keyword_search','price_per_view'=>1500,'countdown_seconds'=>30,'onsite_time'=>70,'fixed_code'=>null,'daily_traffic'=>40,'order_daily_traffic'=>40,'quantity'=>1,'completed'=>0,'updated_at'=>'','customer_id'=>201),
 (object)array('id'=>13,'title'=>'Direct ngheo','keyword'=>'','target_url'=>'https://b.com','destination_urls'=>null,'traffic_type'=>'1step','campaign_type'=>'traffic_direct','task_type'=>'traffic_direct','price_per_view'=>2000,'countdown_seconds'=>30,'onsite_time'=>70,'fixed_code'=>null,'daily_traffic'=>40,'order_daily_traffic'=>40,'quantity'=>1,'completed'=>0,'updated_at'=>'','customer_id'=>202),
 (object)array('id'=>14,'title'=>'Direct het han muc','keyword'=>'','target_url'=>'https://c.com','destination_urls'=>null,'traffic_type'=>'1step','campaign_type'=>'traffic_direct','task_type'=>'traffic_direct','price_per_view'=>2000,'countdown_seconds'=>30,'onsite_time'=>70,'fixed_code'=>null,'daily_traffic'=>5,'order_daily_traffic'=>5,'quantity'=>1,'completed'=>0,'updated_at'=>'','customer_id'=>201),
);
function sitetop_get_customer_balance_amount($id){return $id===201?900000:1000;}
$SL=function($nb,$po,$cs){return (object)array('noi_bo'=>$nb,'pool'=>$po,'tong'=>$nb+$po,'chot_som'=>$cs);};
$wpdb->rules=array(array('/kc.cho_phep_nguon = 1\s+AND \(kc.start_date/',$rows),
  array('/SUM\(\(step = \'verified\' OR customer_paid = 1\) AND shortlink_id <> 44\).*WHERE campaign_id = 11 AND created_at >= \'2026-10-07 00:00:00\' AND created_at < \'2026-10-08 00:00:00\'/s',$SL(3,0,0)),
  array('/SUM\(.*WHERE campaign_id = 14 AND created_at >= /s',$SL(2,3,0)));
$OPT['sitetop_widget_button_text']='TFT'; $OPT['sitetop_widget_color']='#47aaf5'; $OPT['sitetop_widget_icon']='https://sitetop.net/wp-content/themes/sitetop-theme/assets/img/widget-code-icon.png';
$wpdb->log=array(); $out['camps']=sitetop_cn_camps_cho_pool(); $out['camps_completed_row']=(int)$rows[0]->completed;
$out['camps_sql_loai_pool']=count(tim_log($wpdb,'/row:SELECT SUM\\(\\(step = \'verified\' OR customer_paid = 1\\) AND shortlink_id <> 44\\) AS noi_bo,.*WHERE campaign_id = 11 AND created_at >= /s'));
// l2) số liệu chuẩn: có chốt sớm → kèm danh sách phiên; cờ kem_sids=false → không kéo danh sách
$wpdb->rules[]=array('/SUM\(.*WHERE campaign_id = 15 AND created_at >= /s',$SL(10,20,2));
$wpdb->rules[]=array('/SELECT session_id FROM wp_sitetop_shortlink_visits\s+WHERE campaign_id = 15 AND created_at >= .*customer_paid = 1 AND step <> \'verified\' AND verified_at IS NULL AND shortlink_id = 44/s',array('SIDCS000001','SIDCS000002'));
$wpdb->log=array(); $out['so_lieu']=sitetop_cn_so_lieu_nguon(15,50,123); $out['so_lieu_cau']=count($wpdb->log);
$wpdb->log=array(); $out['so_lieu_khong_sids']=sitetop_cn_so_lieu_nguon(15,50,123,false); $out['so_lieu_khong_sids_cau']=count($wpdb->log);
// l3) cổng hạn ngày khi đăng ký lượt gương MỚI
$CAMP=(object)array('id'=>16,'order_id'=>5,'daily_traffic'=>30,'completed'=>0,'order_daily_traffic'=>0);
$wpdb->rules[]=array('/WHERE kc.id = 16 AND kc.status = \'active\'/',function($q){return $GLOBALS['CAMP'];}); $wpdb->rules[]=array('/SELECT id FROM wp_sitetop_user_shortlinks WHERE id = 44/',44);
$wpdb->rules[]=array('/FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDGATE0001\'/',null); $wpdb->rules[]=array('/FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDGATE0002\'/',null);
$wpdb->rules[]=array('/SUM\(.*WHERE campaign_id = 16 AND created_at >= /s',$SL(12,18,1));
$wpdb->log=array(); $r=sitetop_cn_dang_ky_luot_guong('SIDGATE0001',16,'1.2.3.4','UA','ref'); $out['gate_day']=array('ma'=>is_wp_error($r)?$r->ma:'lot','insert'=>count(tim_log($wpdb,'/^insert:/')),'keo_sids'=>count(tim_log($wpdb,'/SELECT session_id FROM/')));
$CAMP=(object)array('id'=>16,'order_id'=>5,'daily_traffic'=>31,'completed'=>0,'order_daily_traffic'=>0);
$wpdb->log=array(); $r=sitetop_cn_dang_ky_luot_guong('SIDGATE0002',16,'1.2.3.4','UA','ref'); $out['gate_con']=array('kq'=>$r,'insert'=>count(tim_log($wpdb,'/^insert:/')));
$CAMP=(object)array('id'=>11,'order_id'=>5);
$wpdb->rules[]=array('/SELECT cho_phep_nguon FROM wp_sitetop_keyword_campaigns WHERE id = 11/',1); $wpdb->rules[]=array('/SELECT cho_phep_nguon FROM wp_sitetop_keyword_campaigns WHERE id = 12/',0);
$out['duoc_chia']=array(sitetop_cn_camp_duoc_chia(11), sitetop_cn_camp_duoc_chia(12), sitetop_cn_camp_duoc_chia(0));
$OPT['sitetop_cn_bat']=0; $r=sitetop_cn_rest_camps(req_ky(array(),'sitetop.one',sitetop_cn_secret())); $out['camps_tat']=$r; $OPT['sitetop_cn_bat']=1;
$r=sitetop_cn_rest_camps(req_ky(array(),'sitetop.one','khoa-sai')); $out['camps_sai_khoa']=is_wp_error($r)?$r->ma:'lot';
// n) báo pool có mã — chạy ở shutdown
$wpdb->rules=array(array('/SELECT shortlink_id FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDBAO00001\'/',(object)array('shortlink_id'=>44)),array('/SELECT shortlink_id FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDBAO00002\'/',(object)array('shortlink_id'=>45)));
$wpdb->rules[]=array('/SELECT customer_paid FROM wp_sitetop_shortlink_visits WHERE session_id = \'SIDBAO00001\'/',1);
$HOOKS=array(); $GOI=array(); $HTTP=array(tra_ok(array('ok'=>true)));
sitetop_cn_bao_pool_co_ma('SIDBAO00002'); $out['bao_noi_bo']=isset($HOOKS['shutdown'])?count($HOOKS['shutdown']):0;
sitetop_cn_bao_pool_co_ma('SIDBAO00001'); $cb=$HOOKS['shutdown'][0]??null; if($cb)call_user_func($cb);
$out['bao_pool']=array('goi'=>count($GOI),'url'=>$GOI[0]['url']??'','than'=>isset($GOI[0])?json_decode($GOI[0]['args']['body'],true):null);
// o) camp đổi → báo pool đồng bộ ngay (ở shutdown, gộp), tắt công tắc thì im
$GOI=array(); $TR=array(); $HTTP=array(tra_ok(array('ok'=>true)), tra_ok(array('ok'=>true,'so_camp'=>1)));
$b1=sitetop_cn_nguon_bao_doi('update_campaign'); $b2=sitetop_cn_nguon_bao_doi('duyet');
if($cb)call_user_func($cb);
$db=null; foreach($GOI as $g){ if(substr($g['url'],-8)==='/dong-bo') $db=$g; }
$out['bao_doi']=array('b1'=>$b1,'b2'=>$b2,'so_goi_dong_bo'=>count(array_filter($GOI,function($g){return substr($g['url'],-8)==='/dong-bo';})),'url'=>$db['url']??'','than'=>$db?json_decode($db['args']['body'],true):null,'throttle'=>!empty($TR['sitetop_cn_bao_doi']));
$OPT['sitetop_cn_bat']=0; $out['bao_doi_tat']=sitetop_cn_nguon_bao_doi('x'); $OPT['sitetop_cn_bat']=1;
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
    assert_equals( array( 501 => array( 'text' => 'LẤY MÃ NET', 'color' => '#112233', 'tcolor' => '#ffffff', 'icon' => 'https://sitetop.net/icon.png' ), 777 => array( 'text' => 'LẤY MÃ NET', 'color' => '#112233', 'tcolor' => '#ffffff', 'icon' => 'https://sitetop.net/icon.png' ) ), $on['kieu_nut'], 'Kieu nut widget cua NGUON luu vao ttplb_widget_style[cid] — dung cho page-unlock ve nut .net o buoc "click vao nut". Ra: ' . json_encode( $on['kieu_nut'] ) );
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
    $db = $P['dong_bo_tu_nguon'];
    assert_true( ( $db['kq']['ok'] ?? false ) === true && ( $db['kq']['so_camp'] ?? 0 ) === 2 && ( $db['kq']['ly_do'] ?? '' ) === 'nguon_bao:update_campaign' && $db['sua'] === 2 && $db['tao'] === 0, 'Nguon bao "camp doi" → pool dong bo NGAY qua cong /dong-bo, cap nhat 2 camp. Ra: ' . json_encode( $db ) );
    assert_equals( 'cn_bad_sign', $P['dong_bo_sai_khoa'], 'Cong /dong-bo doi chu ky dung' );
    __cn_kd_pool_so_lieu( $P );
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
    assert_true( ( $N['xm_da_chot_dung_ma']['kq']['ok'] ?? false ) === true && $N['xm_da_chot_dung_ma']['kq']['trang_thai'] === 'da_xong_truoc' && $N['xm_da_chot_dung_ma']['goi_verify'] === 0, 'Luot da verified + dung ma (pool goi lai sau timeout) → ok ngay, KHONG goi lai verify_and_pay (khong dung khach .net lan hai). Ra: ' . json_encode( $N['xm_da_chot_dung_ma'] ) );
    assert_true( $N['xm_da_chot_sai_ma']['ok'] === false && $N['xm_da_chot_sai_ma']['ma_loi'] === 'wrong_code', 'Luot da verified + SAI ma → tu choi' );
    $c = $N['camps'];
    assert_equals( 1, count( $c ), 'Danh sach camp cho pool: chi camp #11 (12 rong keyword, 13 khach het tien, 14 het han muc ngay theo TONG noi bo 2 + pool 3 = daily 5). Ra: ' . json_encode( array_column( $c, 'id' ) ) );
    assert_equals( array( 'ngay' => '2026-10-07', 'daily' => 40, 'noi_bo' => 3, 'pool' => 0, 'tong' => 3, 'chot_som' => 0, 'completed' => $N['camps_completed_row'], 'con' => 37, 'sids' => array() ), array_diff_key( (array) ( $c[0]['so_lieu'] ?? array() ), array( 'gio' => 1 ) ), 'Payload kem so lieu CHUAN cua nguon (12/10/2026): completed = cot completed cua camp nguon. Ra: ' . json_encode( $c[0]['so_lieu'] ?? null ) );
    $sl = $N['so_lieu'];
    assert_true( $sl['noi_bo'] === 10 && $sl['pool'] === 20 && $sl['tong'] === 30 && $sl['chot_som'] === 2 && $sl['con'] === 20 && $sl['completed'] === 123 && $sl['sids'] === array( 'SIDCS000001', 'SIDCS000002' ) && $N['so_lieu_cau'] === 2, 'So lieu chuan: con = daily - TONG (gom pool); co chot som → keo danh sach phien (2 cau). Ra: ' . json_encode( $sl ) );
    assert_true( $N['so_lieu_khong_sids']['sids'] === array() && $N['so_lieu_khong_sids_cau'] === 1, 'kem_sids=false → khong keo danh sach (1 cau)' );
    assert_true( $N['gate_day']['ma'] === 'het_han_muc' && $N['gate_day']['insert'] === 0 && $N['gate_day']['keo_sids'] === 0, 'CONG HAN NGAY: tong hom nay (12+18=30) >= daily 30 → tu choi phien moi, khong ghi, khong keo danh sach. Ra: ' . json_encode( $N['gate_day'] ) );
    assert_true( ( $N['gate_con']['kq']['trang_thai'] ?? '' ) === 'moi' && $N['gate_con']['insert'] === 1, 'Con han (30 < 31) → nhan phien moi' );
    assert_true( ( $c[0]['id'] ?? 0 ) === 11 && ( $c[0]['daily_traffic'] ?? 0 ) === 37 && ( $c[0]['campaign_type'] ?? '' ) === 'keyword_search' && ( $c[0]['price_per_view'] ?? 0 ) == 1500, 'Camp gui di: daily_traffic = con lai hom nay (40-3), loai + gia dung. Ra: ' . json_encode( $c[0] ?? null ) );
    assert_equals( array( 'text' => 'TFT', 'color' => '#47aaf5', 'tcolor' => '#ffffff', 'icon' => 'https://sitetop.net/wp-content/themes/sitetop-theme/assets/img/widget-code-icon.png' ), $c[0]['widget'] ?? null, 'Camp gui di kem kieu nut widget cua nguon (chu, mau, mau chu, icon). Ra: ' . json_encode( $c[0]['widget'] ?? null ) );
    assert_equals( 1, $N['camps_sql_loai_pool'], 'Han muc ngay gui pool = daily - view NOI BO cua nguon (SQL tach noi bo bang shortlink_id <> 44), khong tru hai lan view do pool lam' );
    assert_equals( array( true, false, false ), $N['duoc_chia'], 'sitetop_cn_camp_duoc_chia: co 1 → true; 0 / id rong → false' );
    assert_true( ( $N['camps_tat']['bat'] ?? true ) === false && empty( $N['camps_tat']['camps'] ), 'Cong tac nguon OFF → bat=false, khong gui camp' );
    assert_equals( 'cn_bad_sign', $N['camps_sai_khoa'], 'Cong camps doi chu ky dung' );
    assert_equals( 0, $N['bao_noi_bo'], 'Cap ma cho luot NOI BO → khong bao pool' );
    assert_equals( 1, $N['bao_pool']['than']['chot_som'] ?? null, 'Bao pool co ma KEM co chot_som doc LUC GUI (sau khi chot som da chay) — 12/10/2026' );
    assert_true( $N['bao_pool']['goi'] === 1 && $N['bao_pool']['url'] === 'https://sitetop.one/wp-json/sitetop-cn/v1/san-sang' && ( $N['bao_pool']['than']['sid'] ?? '' ) === 'SIDBAO00001' && ! isset( $N['bao_pool']['than']['code'] ), 'Cap ma cho luot pool → bao pool o shutdown, chi gui sid, KHONG gui ma. Ra: ' . json_encode( $N['bao_pool'] ) );
    $bd = $N['bao_doi'];
    assert_true( $bd['b1'] === true && $bd['b2'] === true && $bd['so_goi_dong_bo'] === 1, 'Hai thay doi trong mot request → bao pool DUNG MOT LAN o shutdown. Ra: ' . json_encode( $bd ) );
    assert_true( $bd['url'] === 'https://sitetop.one/wp-json/sitetop-cn/v1/dong-bo' && ( $bd['than']['ly_do'] ?? '' ) === 'update_campaign' && $bd['throttle'], 'Goi dung cong /dong-bo cua pool, kem ly do, dat throttle 3 giay' );
    assert_true( $N['bao_doi_tat'] === false, 'Cong tac nguon OFF → khong bao pool' );
}

/* ---- 5 ĐIỂM MÓC vào luồng nhiệm vụ (chủ site duyệt 07/10/2026) — kiểm đúng VỊ TRÍ trong hàm, không dò chuỗi toàn file ---- */
$__cn_sf = (string) file_get_contents( $__cn_goc . '/includes/shortlink-functions.php' );
$__cn_sv = (string) file_get_contents( $__cn_goc . '/includes/shortlink-verification.php' );
$__cn_sa = (string) file_get_contents( $__cn_goc . '/includes/shortlink-ajax.php' );
function __cn_doan( $s, $tu, $den ) { $a = strpos( $s, $tu ); $b = $a === false ? false : strpos( $s, $den, $a ); return ( $a === false || $b === false ) ? '' : substr( $s, $a, $b - $a ); }
$__cn_hv = __cn_doan( $__cn_sf, 'function sitetop_handle_shortlink_visit(', "include get_template_directory() . '/page-unlock.php'" );
$__cn_i1 = strpos( $__cn_hv, 'sitetop_get_random_active_campaign( $ip )' ); $__cn_i2 = strpos( $__cn_hv, 'sitetop_cn_gan_camp_pool( $campaign, $session_id, $ip, $shortlink )' ); $__cn_i3 = strpos( $__cn_hv, "\$_SESSION['sitetop_campaign']" );
assert_true( $__cn_i1 !== false && $__cn_i2 !== false && $__cn_i3 !== false && $__cn_i1 < $__cn_i2 && $__cn_i2 < $__cn_i3, 'Moc 1: gan camp pool nam SAU khi chon camp va TRUOC khi luu session' );
assert_true( strpos( $__cn_hv, "if ( ! \$campaign ) {\n            wp_redirect(" ) !== false, 'Moc 1: khong con camp → redirect ve link goc, khong hien nhiem vu trong' );
$__cn_gw = __cn_doan( $__cn_sf, 'function sitetop_get_widget_code(', '// Alias' );
$__cn_j1 = strpos( $__cn_gw, "'verify_code'   => \$code," ); $__cn_j2 = strpos( $__cn_gw, 'sitetop_cn_bao_pool_co_ma( $session_id )' ); $__cn_j3 = strpos( $__cn_gw, 'sitetop_verify_and_pay( $session_id, $code, true )' );
assert_true( $__cn_j1 !== false && $__cn_j2 !== false && $__cn_j3 !== false && $__cn_j1 < $__cn_j2 && $__cn_j2 < $__cn_j3, 'Moc 2: bao pool co ma nam SAU khi luu ma vao DB (truoc chot som)' );
$__cn_vp = __cn_doan( $__cn_sv, 'function sitetop_verify_and_pay(', 'function sitetop_get_user_balance_amount(' );
$__cn_k1 = strpos( $__cn_vp, 'sitetop_bridge_rescue_code( $visit, $session_id, $code );' ); $__cn_k2 = strpos( $__cn_vp, 'sitetop_cn_truoc_xac_minh( $visit, $session_id, $code )' ); $__cn_k3 = strpos( $__cn_vp, "'code_not_ready'" );
$__cn_k6 = strpos( $__cn_vp, '$tuoi_ma > $visit_expiry' );
assert_true( $__cn_k1 !== false && $__cn_k2 !== false && $__cn_k3 !== false && $__cn_k6 !== false && $__cn_k1 < $__cn_k6 && $__cn_k6 < $__cn_k2 && $__cn_k2 < $__cn_k3, 'Moc 3: hoi nguon nam SAU rescue + SAU hai chot het han cua .one, TRUOC chot code_not_ready (luot .one het han thi khong hoi nguon)' );
assert_true( strpos( $__cn_vp, 'if ( is_wp_error( $cn_kq ) ) return $cn_kq;' ) !== false, 'Moc 3: nguon lac → tra loi ngay, khong tra thuong' );
$__cn_cap = __cn_doan( $__cn_vp, "\$captcha_ok      = (bool) get_transient( 'sitetop_captcha_ok_'", "'captcha_unverified'" );
assert_true( strpos( $__cn_cap, "get_transient( 'sitetop_cn_ma_' . \$session_id )" ) !== false && strpos( $__cn_cap, 'sitetop_cn_la_luot_pool( $visit )' ) !== false, 'Moc 4a: cong captcha widget mien cho ma-do-nguon-cap (pool) va luot guong (nguon)' );
$__cn_k4 = strpos( $__cn_vp, "\$skip_reasons[] = 'pool_sitetop_one';" ); $__cn_k5 = strpos( $__cn_vp, "\$wpdb->query( 'START TRANSACTION' );" );
assert_true( $__cn_k4 !== false && $__cn_k5 !== false && $__cn_k4 < $__cn_k5, 'Moc 4b: luot guong tren nguon → khong thuong tai khoan pool, dat TRUOC transaction' );
$__cn_cr = __cn_doan( $__cn_sa, 'function sitetop_ajax_check_code_ready(', 'wp_send_json_success' );
$__cn_m1 = strpos( $__cn_cr, 'sitetop_cn_hoi_co_ma( $sid )' ); $__cn_m2 = strpos( $__cn_cr, "\$ready = get_transient('sitetop_widget_code_ready_'" );
assert_true( $__cn_m1 !== false && $__cn_m2 !== false && $__cn_m1 < $__cn_m2, 'Moc 5: hoi nguon TRUOC khi doc co san sang' );

/* ---- TÍN HIỆU ĐẨY khi camp đổi (08/10/2026, chủ site: "tối ưu nhanh hơn") — mọi đường đổi trạng thái/cờ phải báo pool ---- */
$__cn_cm2 = (string) file_get_contents( $__cn_goc . '/includes/campaign-management.php' );
$__cn_sd2 = (string) file_get_contents( $__cn_goc . '/includes/shortlink-distribution.php' );
$__cn_cc2 = (string) file_get_contents( $__cn_goc . '/includes/customer-campaign-ajax.php' );
$__cn_uc = __cn_doan( $__cn_sf, 'function sitetop_update_campaign(', 'function sitetop_get_campaigns(' );
assert_true( strpos( $__cn_uc, "isset( \$update['status'] ) || isset( \$update['cho_phep_nguon'] )" ) !== false && strpos( $__cn_uc, "sitetop_cn_nguon_bao_doi( 'update_campaign' )" ) !== false && strpos( $__cn_uc, '$kq !== false' ) !== false, 'sitetop_update_campaign: doi status/cho_phep_nguon va UPDATE thanh cong → bao pool' );
assert_true( strpos( $__cn_uc, "sitetop_cn_nguon_bao_doi( 'sua_camp_chia' )" ) !== false && strpos( $__cn_uc, 'sitetop_cn_camp_duoc_chia( $id )' ) !== false, 'sitetop_update_campaign: camp DANG CHIA sua bat ky truong nao (daily, tu khoa, URL…) → bao pool' );
assert_true( strpos( $__cn_cc2, "sitetop_cn_nguon_bao_doi( 'khach_sua' )" ) !== false && strpos( $__cn_cc2, 'sitetop_cn_camp_duoc_chia( $campaign_id )' ) !== false, 'Khach sua camp dang chia → bao pool' );
foreach ( array( 'duyet' => 'function sitetop_approve_campaign(', 'tu_choi' => 'function sitetop_reject_campaign(', 'xoa_mem' => 'function sitetop_xoa_mem_campaign(' ) as $__cn_ly => $__cn_ham ) {
    $__cn_khoi = __cn_doan( $__cn_cm2, $__cn_ham, "\n}\n" );
    assert_true( strpos( $__cn_khoi, "sitetop_cn_nguon_bao_doi( '" . $__cn_ly . "' )" ) !== false, 'campaign-management ' . $__cn_ham . ' bao pool (' . $__cn_ly . ')' );
}
assert_true( strpos( __cn_doan( $__cn_sv, 'function sitetop_auto_pause_customer_campaigns(', "\n}\n" ), "sitetop_cn_nguon_bao_doi( 'khach_het_tien' )" ) !== false, 'Khach het tien → tu dong dung camp → bao pool' );
assert_true( strpos( __cn_doan( $__cn_sd2, 'function sitetop_auto_resume_paused_campaigns(', "\n}\n" ), "sitetop_cn_nguon_bao_doi( 'chay_lai' )" ) !== false, 'Camp completed → active (cron) → bao pool' );
assert_true( strpos( $__cn_cc2, "sitetop_cn_nguon_bao_doi( 'khach_xoa' )" ) !== false, 'Khach tu xoa camp → bao pool' );
if ( basename( $__cn_goc ) === 'sitetop-theme' ) {
    $__cn_tc2 = (string) file_get_contents( $__cn_goc . '/includes/admin/tabs/tab-campaigns.php' );
    foreach ( array( 'admin_dung', 'admin_tu_choi', 'admin_tao' ) as $__cn_ly ) assert_true( strpos( $__cn_tc2, "sitetop_cn_nguon_bao_doi('" . $__cn_ly . "')" ) !== false, 'Tab Chien dich .net bao pool (' . $__cn_ly . ')' );
}
