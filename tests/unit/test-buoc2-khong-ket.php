<?php
/* BƯỚC 2 KHÔNG ĐƯỢC KẸT — 04/10/2026.

   Chủ site báo: làm xong bước 1, bấm link nội bộ sang bước 2, bấm nút mã thì ĐÔI LÚC khựng
   và báo "Vui lòng truy cập link nhiệm vụ sitetop.one".

   Đọc mã tìm ra ba lỗi trong initStep2Return():
   (1) Hàm XOÁ SẠCH cờ bước 2 ngay đầu, rồi mới `if(!btn) return` — nút chưa kịp vào DOM là
       phiên mất sạch đường cứu, tải lại trang cũng không nhận ra bước 2 nữa.
   (2) Hàm không đặt state.sessionId / state.sessionReady dù đang cầm sẵn session id → mọi
       trục trặc nhỏ đều rơi xuống nhánh "chưa khớp phiên nào" và báo đúng câu lỗi trên.
   (3) btn.onclick=null ngay cú bấm đầu → bấm lần hai trong 15 giây là nút câm hẳn.
   Và một chỗ mù: trang bước 2 không gọi gì cho tới lúc xin mã nên lượt kẹt không để lại dấu
   vết nào — nay ghi một dòng 'vaobuoc2'.

   Test CHẠY THẬT hàm initStep2Return trong node với DOM giả. */

$__b2_goc = dirname( __DIR__, 2 );
$__b2_wid = (string) file_get_contents( $__b2_goc . '/widget.js.php' );
$__b2_ajx = (string) file_get_contents( $__b2_goc . '/includes/shortlink-ajax.php' );

$__b2_js = function ( $ma, $ten ) {
    $vt = strpos( $ma, 'function ' . $ten . '(' );
    if ( $vt === false ) return '';
    $d = 0; $mo = false; $n = strlen( $ma );
    for ( $i = $vt; $i < $n; $i++ ) {
        if ( $ma[ $i ] === '{' ) { $d++; $mo = true; }
        elseif ( $ma[ $i ] === '}' ) { $d--; if ( $mo && $d === 0 ) return substr( $ma, $vt, $i - $vt + 1 ); }
    }
    return '';
};
$__b2_ham = $__b2_js( $__b2_wid, 'initStep2Return' );
assert_true( $__b2_ham !== '', 'Phai trich duoc initStep2Return' );
assert_true( trim( (string) shell_exec( 'command -v node 2>/dev/null' ) ) !== '', 'Can node' );

/* $kich: 'co_nut' | 'nut_toi_muon' | 'khong_co_nut'; $bam: số lần bấm nút. */
$__b2_chay = function ( $kich, $bam = 0 ) use ( $__b2_ham ) {
    $khung = <<<'JS'
var GOI=[], TOAST=[], LS={tn_step2_waiting:'1',tn_step2_sid:'S1',tn_step2_time:String(Date.now()),
                        tn_link_clicked:'1',tn_step2_from:'https://web-khach.vn/a',tn_session_id:'S1'};
var localStorage={ getItem:function(k){return LS[k]===undefined?null:LS[k];},
                   setItem:function(k,v){LS[k]=String(v);}, removeItem:function(k){delete LS[k];} };
var _nut=null;
function _taoNut(){ return {innerHTML:'',onclick:null,classList:{add:function(){},remove:function(){}}}; }
var document={ visibilityState:'visible',
    getElementById:function(id){ if(id==='tn-btn')return _nut; if(id==='tn-cd')return {style:{},textContent:''}; return null; },
    addEventListener:function(){} };
var state={sessionId:'',sessionReady:false,step2Mode:false,codeReady:false};
function ajax(a,d,cb){ GOI.push({a:a,d:d}); }
function showToast(m){ TOAST.push(String(m)); }
function showCode(){}
function sendVerifyAccess(){ GOI.push({a:'xacminh_lai'}); }
function _khungChinh(){ return 1; }
JS;
    $sau = "\n" . $__b2_ham . "\n";
    if ( $kich === 'co_nut' ) {
        $sau .= "_nut=_taoNut();\ninitStep2Return('S1','co');\n";
    } elseif ( $kich === 'nut_toi_muon' ) {
        $sau .= "initStep2Return('S1','co');\nvar coSau=JSON.parse(JSON.stringify(Object.keys(LS)));\n"
              . "setTimeout(function(){ _nut=_taoNut(); },500);\n";
    } else {
        $sau .= "initStep2Return('S1','co');\n";
    }
    $bam_js = '';
    for ( $i = 0; $i < $bam; $i++ ) $bam_js .= "try{ if(_nut&&_nut.onclick)_nut.onclick(); }catch(e){ TOAST.push('LOI:'+e.message); }\n";
    $sau .= "setTimeout(function(){\n" . $bam_js
          . "  console.log(JSON.stringify({ sid:state.sessionId, san:state.sessionReady, s2:state.step2Mode,\n"
          . "    coHandler:!!(_nut&&_nut.onclick), coCo:!!LS.tn_step2_waiting, goi:GOI, toast:TOAST }));\n"
          . "},1200);\n";
    $f = sys_get_temp_dir() . '/st-b2-' . getmypid() . '-' . $kich . $bam . '.js';
    file_put_contents( $f, $khung . $sau );
    $ra = (string) shell_exec( 'node ' . escapeshellarg( $f ) . ' 2>&1' );
    @unlink( $f );
    return array( json_decode( trim( $ra ), true ), $ra );
};
$__b2_cong = function ( $kq, $ten ) {
    foreach ( (array) ( $kq['goi'] ?? array() ) as $g ) if ( ( $g['a'] ?? '' ) === $ten ) return $g;
    return null;
};

/* ── 1. Nút có sẵn: nhận phiên, gắn handler, báo máy chủ, xoá cờ ── */
list( $__b2_k, $__b2_r ) = $__b2_chay( 'co_nut' );
assert_true( is_array( $__b2_k ), 'Chay duoc initStep2Return bang node. Ra: ' . $__b2_r );
assert_equals( 'S1', $__b2_k['sid'] ?? '', 'Phai nhan session id ngay (khong thi bao sai "chua khop phien")' );
assert_true( ! empty( $__b2_k['san'] ), 'Phai dat sessionReady = true' );
assert_true( ! empty( $__b2_k['coHandler'] ), 'Phai gan duoc handler cho nut' );
assert_true( empty( $__b2_k['coCo'] ), 'Gan xong roi thi moi xoa co buoc 2' );
assert_true( $__b2_cong( $__b2_k, 'sitetop_widget_buoc2_vao' ) !== null,
    'Phai bao may chu mot lan de dau vet thay duoc buoc 2' );

/* ── 2. Bấm nút: chạy start_timer bước 2; bấm lần hai KHÔNG làm chết nút ── */
list( $__b2_k2, $__b2_r2 ) = $__b2_chay( 'co_nut', 2 );
$__b2_st = $__b2_cong( $__b2_k2, 'sitetop_widget_start_timer' );
assert_true( $__b2_st !== null && ( $__b2_st['d']['step2'] ?? '' ) === '1',
    'Bam nut phai goi start_timer voi step2=1. Ra: ' . $__b2_r2 );
assert_true( ! empty( $__b2_k2['coHandler'] ),
    'Bam lan hai KHONG duoc lam chet nut (ban cu dat onclick=null nen nut cam han)' );
$__b2_sl = 0;
foreach ( (array) $__b2_k2['goi'] as $g ) if ( ( $g['a'] ?? '' ) === 'sitetop_widget_start_timer' ) $__b2_sl++;
assert_equals( 1, $__b2_sl, 'Bam hai lan chi duoc chay dong ho MOT lan' );
assert_true( count( (array) $__b2_k2['toast'] ) > 0, 'Bam trung phai nhac "dang dem gio", khong im lang' );

/* ── 3. Nút tới muộn: vẫn gắn được, và CHƯA xoá cờ khi chưa gắn ── */
list( $__b2_k3, $__b2_r3 ) = $__b2_chay( 'nut_toi_muon' );
assert_true( ! empty( $__b2_k3['coHandler'] ),
    'Nut vao DOM muon van phai gan duoc handler (ban cu bo cuoc ngay). Ra: ' . $__b2_r3 );
assert_equals( 'S1', $__b2_k3['sid'] ?? '', 'Nut toi muon: van phai nhan phien ngay tu dau' );

/* ── 4. Nút không bao giờ có: PHẢI GIỮ cờ để tải lại trang còn cứu được ── */
list( $__b2_k4, $__b2_r4 ) = $__b2_chay( 'khong_co_nut' );
assert_true( ! empty( $__b2_k4['coCo'] ),
    'Khong gan duoc nut thi PHAI GIU co buoc 2 — xoa la tai lai trang cung mat dau buoc 2. Ra: ' . $__b2_r4 );

/* ── 5. Cổng ghi dấu: chỉ ghi vết, không đụng gì khác ── */
$__b2_cong_php = $__b2_js( $__b2_ajx, 'sitetop_ajax_widget_buoc2_vao' );
assert_true( $__b2_cong_php !== '', 'Phai co cong sitetop_ajax_widget_buoc2_vao' );
assert_true( strpos( $__b2_ajx, "add_action('wp_ajax_nopriv_sitetop_widget_buoc2_vao'" ) !== false,
    'Cong phai nhan ca khach chua dang nhap (widget chay tren web khach)' );
assert_true( strpos( $__b2_cong_php, 'sitetop_ghi_vet' ) !== false, 'Cong phai ghi dau vet' );
assert_true( strpos( $__b2_cong_php, 'sitetop_rate_limit_check' ) !== false, 'Cong phai co ro han muc' );
foreach ( array( 'UPDATE', 'balance', 'reward', 'customer_paid', "'step'" ) as $__b2_cam ) {
    assert_true( stripos( $__b2_cong_php, $__b2_cam ) === false,
        'Cong ghi dau KHONG duoc dung toi "' . $__b2_cam . '" — no chi duoc ghi vet' );
}
