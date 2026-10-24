<?php
/* CHỐT VÀO WEB QUÁ NHANH (24/10/2026): tool giữ bảng từ khoá → tên miền, tự mở web đích qua
   Google vài giây sau khi trang nhiệm vụ tải. Chạy THẬT khối (5) trích từ widget_verify_access
   trong tiến trình PHP riêng, với transient/option giả. */
$__qn_src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/shortlink-ajax.php' );
$__qn_a = strpos( $__qn_src, '/* (5) VÀO WEB QUÁ NHANH' );
$__qn_b = $__qn_a === false ? false : strpos( $__qn_src, "\n    }\n", $__qn_a );   // dấu đóng khối if ( $google_required )
assert_true( $__qn_a !== false && $__qn_b !== false, 'Co khoi (5) vao web qua nhanh trong widget_verify_access' );
$__qn_khoi = substr( $__qn_src, $__qn_a, $__qn_b - $__qn_a );
$__qn_chay = function ( $giay_truoc, $tu_google, $da_xac_minh, $option = null, $co_handoff = true ) use ( $__qn_khoi ) {
    $f = sys_get_temp_dir() . '/st-qn-' . getmypid() . '-' . mt_rand() . '.php';
    $ma = '<?php $OPT=' . var_export( $option, true ) . '; $HOFF=' . ( $co_handoff ? 'time()-' . (int) $giay_truoc : '0' ) . '; $VET=array();
function get_option($k,$d=false){ global $OPT; return $OPT===null?$d:$OPT; }
function get_transient($k){ global $HOFF; return $k==="sitetop_handoff_S1" ? $HOFF : false; }
function sitetop_ghi_vet($s,$b,$x=""){ global $VET; $VET[]="$b $x"; }
$visit=(object)array("session_id"=>"S1"); $result=array();
$db_already_verified=' . var_export( $da_xac_minh, true ) . '; $google_verified=' . var_export( $tu_google, true ) . ';
' . $__qn_khoi . '
echo json_encode(array("gv"=>$google_verified,"qn"=>!empty($result["qua_nhanh"]),"vet"=>$VET));';
    file_put_contents( $f, $ma );
    $r = json_decode( (string) shell_exec( 'php ' . escapeshellarg( $f ) . ' 2>&1' ), true ); @unlink( $f );
    return $r;
};
$r = $__qn_chay( 5, true, false );
assert_true( is_array( $r ) && $r['gv'] === false && $r['qn'] === true, 'Vao web 5 giay sau khi tai trang nhiem vu → KHONG qua chot, bao qua_nhanh' );
assert_true( is_array( $r ) && strpos( (string) ( $r['vet'][0] ?? '' ), 'ly_do=qua_nhanh giay=5/12' ) !== false, 'Ghi dau vet xacminh_tuchoi qua_nhanh kem so giay' );
$r = $__qn_chay( 20, true, false );
assert_true( is_array( $r ) && $r['gv'] === true && $r['qn'] === false, 'Vao web sau 20 giay → qua binh thuong' );
$r = $__qn_chay( 3, true, true );
assert_true( is_array( $r ) && $r['gv'] === true, 'Luot DA qua chot Google (dieu huong noi bo / buoc 2) → khong xet lai' );
$r = $__qn_chay( 3, false, false );
assert_true( is_array( $r ) && $r['gv'] === false && $r['qn'] === false, 'Khong tu Google → van bi chan nhu cu, khong gan nham qua_nhanh' );
$r = $__qn_chay( 3, true, false, 0 );
assert_true( is_array( $r ) && $r['gv'] === true, 'Option = 0 → tat chot' );
$r = $__qn_chay( 8, true, false, 5 );
assert_true( is_array( $r ) && $r['gv'] === true, 'Option = 5 giay → 8 giay la qua' );
$r = $__qn_chay( 0, true, false, null, false );
assert_true( is_array( $r ) && $r['gv'] === true, 'Khong co moc ban giao (phien guong cau noi) → khong chan' );

$__qn_w = (string) file_get_contents( dirname( __DIR__, 2 ) . '/widget.js.php' );
assert_true( strpos( $__qn_w, 'state.quaNhanh=!!d.data.qua_nhanh;' ) !== false
    && strpos( $__qn_w, "Bạn vào web quá nhanh. Hãy quay lại Google" ) !== false, 'Widget doc co qua_nhanh va bao user quay lai Google' );
