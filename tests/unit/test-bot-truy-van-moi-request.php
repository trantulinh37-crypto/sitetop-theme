<?php
/* BỚT TRUY VẤN Ở MỌI REQUEST (11/10/2026, chủ site: "admin thao tác chuyển mục load hơi chậm").
   Đo production: CSDL hosting trả ~25–64 ms cho cả câu đọc option đơn giản nhất (cả user .net lẫn .one) — mỗi câu bớt
   được là bớt chừng ấy cho MỌI request, kể cả các nhịp thăm dò 2 giây/lần của trang nhiệm vụ (đa số lưu lượng).
   Đo local, nhịp check_code_ready 15 câu thì 8 câu là thừa:
   - get_users(administrator) ở after_setup_theme chạy MỌI request (3 câu) để gán vai trò customer cho admin;
   - 4 cờ sitetop_migration_*_v1 + sitetop_onboard_src_since ghi autoload=false → mỗi cờ một câu đọc riêng.
   Test chạy THẬT các đoạn mã (trích từ file) với WP giả: rà vai trò 12 giờ/lần + gán ngay khi cấp quyền admin;
   đổi một lần sang nạp sẵn; chỗ ghi cờ dùng autoload=true. */

$__bt_goc = dirname( __DIR__, 2 );
$__bt_fn  = (string) file_get_contents( $__bt_goc . '/functions.php' );

/* Cắt thân hàm (đếm ngoặc, bỏ qua ngoặc trong chuỗi) bắt đầu từ chuỗi $dau. */
$__bt_cat = function ( $ma, $dau ) {
    $p = strpos( $ma, $dau );
    if ( $p === false ) return '';
    $q = strpos( $ma, '{', $p );
    $out = ''; $d = 0; $chuoi = '';
    for ( $i = $q, $n = strlen( $ma ); $i < $n; $i++ ) {
        $c = $ma[ $i ]; $out .= $c;
        if ( $chuoi !== '' ) { if ( $c === '\\' ) { $out .= $ma[ ++$i ]; continue; } if ( $c === $chuoi ) $chuoi = ''; continue; }
        if ( $c === "'" || $c === '"' ) { $chuoi = $c; continue; }
        if ( $c === '{' ) $d++;
        elseif ( $c === '}' ) { $d--; if ( $d === 0 ) break; }
    }
    return $out;   // gồm cả { … }
};
$__bt_vaitro = $__bt_cat( $__bt_fn, "add_action( 'after_setup_theme', function() {\n    // Customer role (advertiser)" );
$__bt_kem    = $__bt_cat( $__bt_fn, 'function sitetop_admin_kem_vai_tro_khach(' );
$__bt_ds     = $__bt_cat( $__bt_fn, 'function sitetop_tocdo_cac_option_nap_san(' );
$__bt_doi    = $__bt_cat( $__bt_fn, "add_action( 'init', function () {\n    if ( get_option( 'sitetop_tocdo_nap_san_v1' ) ) return;" );
assert_true( $__bt_vaitro !== '' && $__bt_kem !== '' && $__bt_ds !== '' && $__bt_doi !== '', 'Trich duoc 4 doan ma (ra vai tro, gan ngay, danh sach option, doi mot lan)' );

$__bt_ban = '<?php
define( "HOUR_IN_SECONDS", 3600 );
$OPT = array(); $VET = array(); $ROLES = array();
function get_option( $k, $d = false ) { $GLOBALS["VET"][] = "get:" . $k; return array_key_exists( $k, $GLOBALS["OPT"] ) ? $GLOBALS["OPT"][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS["VET"][] = "set:" . $k . ":" . var_export( $a, true ); $GLOBALS["OPT"][ $k ] = $v; return true; }
function wp_set_option_autoload_values( $a ) { $GLOBALS["VET"][] = "autoload:" . json_encode( $a ); return array(); }
class R { public $caps = array( "manage_sitetop" => 1, "manage_sitetop_users" => 1, "manage_sitetop_customers" => 1, "manage_sitetop_campaigns" => 1, "manage_sitetop_withdrawals" => 1, "manage_sitetop_deposits" => 1, "manage_sitetop_settings" => 1 );
  function has_cap( $c ) { return isset( $this->caps[ $c ] ); } function add_cap( $c ) { $this->caps[ $c ] = 1; } }
function get_role( $r ) { return new R(); } function add_role() {}
class U { public $ID; public $roles; function __construct( $id, $roles ) { $this->ID = $id; $this->roles = $roles; }
  function add_role( $r ) { $GLOBALS["VET"][] = "add_role:" . $this->ID . ":" . $r; $this->roles[] = $r; } }
function get_users( $a ) { $GLOBALS["VET"][] = "get_users"; return array( new U( 1, array( "administrator", "customer" ) ), new U( 2, array( "administrator" ) ) ); }
function get_userdata( $id ) { return isset( $GLOBALS["ROLES"][ $id ] ) ? new U( $id, $GLOBALS["ROLES"][ $id ] ) : false; }
$ra_vai_tro = function () ' . $__bt_vaitro . ';
function sitetop_admin_kem_vai_tro_khach( $user_id, $role ) ' . $__bt_kem . '
function sitetop_tocdo_cac_option_nap_san() ' . $__bt_ds . '
$doi_mot_lan = function () ' . $__bt_doi . ';
$out = array(); $bay_gio = time();
// a) mốc rà còn mới (1 giờ trước) → KHÔNG get_users
$OPT = array( "sitetop_ra_vai_tro_admin" => $bay_gio - 3600 ); $VET = array(); $ra_vai_tro(); $out["a"] = $VET;
// b) mốc cũ (13 giờ) → ghi mốc mới (nạp sẵn) TRƯỚC, rồi get_users, gán customer cho admin #2 (thiếu), không đụng #1
$OPT = array( "sitetop_ra_vai_tro_admin" => $bay_gio - 13 * 3600 ); $VET = array(); $ra_vai_tro(); $out["b"] = $VET; $out["b_moc"] = $OPT["sitetop_ra_vai_tro_admin"] >= $bay_gio;
// c) chưa có mốc (lần đầu sau deploy) → rà ngay
$OPT = array(); $VET = array(); $ra_vai_tro(); $out["c"] = in_array( "get_users", $VET, true );
// d) cấp quyền admin → gán customer ngay; vai trò khác / đã có customer / user không tồn tại → không làm gì
$ROLES = array( 7 => array( "administrator" ), 8 => array( "administrator", "customer" ), 9 => array( "subscriber" ) );
$VET = array(); sitetop_admin_kem_vai_tro_khach( 7, "administrator" ); sitetop_admin_kem_vai_tro_khach( 8, "administrator" );
sitetop_admin_kem_vai_tro_khach( 9, "subscriber" ); sitetop_admin_kem_vai_tro_khach( 99, "administrator" ); sitetop_admin_kem_vai_tro_khach( 7, "customer" ); $out["d"] = $VET;
// e) đổi một lần: chưa có cờ → đổi nạp sẵn đúng danh sách rồi ghi cờ (nạp sẵn); lần sau có cờ → không làm gì
$OPT = array(); $VET = array(); $doi_mot_lan(); $out["e1"] = $VET;
$VET = array(); $doi_mot_lan(); $out["e2"] = $VET;
echo json_encode( $out );';
$__bt_f = sys_get_temp_dir() . '/st-bt-' . getmypid() . '.php';
file_put_contents( $__bt_f, $__bt_ban );
$__bt_r = (string) shell_exec( 'php ' . escapeshellarg( $__bt_f ) . ' 2>&1' );
@unlink( $__bt_f );
$B = json_decode( trim( $__bt_r ), true );
assert_true( is_array( $B ), 'Chay duoc cac doan ma bot truy van. Ra: ' . substr( $__bt_r, 0, 600 ) );
if ( is_array( $B ) ) {
    assert_true( ! in_array( 'get_users', $B['a'], true ), 'Moc ra vai tro con moi → KHONG goi get_users (khong ton 3 truy van moi request). Ra: ' . json_encode( $B['a'] ) );
    assert_equals( array( 'get:sitetop_ra_vai_tro_admin', 'set:sitetop_ra_vai_tro_admin:true', 'get_users', 'add_role:2:customer' ), $B['b'],
        'Moc cu → ghi moc moi (nap san) TRUOC, roi get_users, chi gan customer cho admin con thieu' );
    assert_true( $B['b_moc'] === true && $B['c'] === true, 'Moc moi = bay gio; chua co moc (lan dau sau deploy) → ra ngay' );
    assert_equals( array( 'add_role:7:customer' ), $B['d'], 'Cap quyen admin → gan customer NGAY; vai tro khac / da co customer / user khong ton tai → khong lam gi' );
    assert_equals( array( 'get:sitetop_tocdo_nap_san_v1',
        'autoload:' . json_encode( array( 'sitetop_migration_nan_step_v1' => true, 'sitetop_migration_wd_bypass_v1' => true, 'sitetop_migration_khong_doi_cd_v1' => true,
                                          'sitetop_migration_cho_phep_nguon_v1' => true, 'sitetop_onboard_src_since' => true, 'wp_mail_smtp' => true ) ),
        'set:sitetop_tocdo_nap_san_v1:true' ), $B['e1'], 'Doi mot lan: dung danh sach option sang nap san, roi ghi co (nap san)' );
    assert_equals( array( 'get:sitetop_tocdo_nap_san_v1' ), $B['e2'], 'Da doi roi → chi doc co (nap san, khong ton truy van), khong lam gi them' );
}

/* Chỗ GHI cờ: không còn autoload=false — ghi sai là cờ lại thành một câu đọc riêng ở mọi request. */
foreach ( array( 'includes/cron-cleanup.php', 'includes/cau-noi-net-one.php', 'includes/source-approval.php', 'functions.php' ) as $__bt_tep ) {
    $__bt_ma = (string) file_get_contents( $__bt_goc . '/' . $__bt_tep );
    assert_equals( 0, preg_match_all( "/update_option\(\s*'sitetop_(migration_[a-z_]+_v1|onboard_src_since)'\s*,[^;]*,\s*false\s*\)/", $__bt_ma ), $__bt_tep . ': khong con ghi co migration / onboard_src_since voi autoload=false' );
}
$__bt_cc = (string) file_get_contents( $__bt_goc . '/includes/cron-cleanup.php' );
assert_equals( 4, substr_count( $__bt_cc, "time(), true ); // nạp sẵn: đọc ở mọi request (11/10/2026)" ), 'cron-cleanup: 4 cho ghi co (nan_step x2, wd_bypass, khong_doi_cd) deu nap san' );
assert_true( strpos( $__bt_fn, "add_action( 'set_user_role', 'sitetop_admin_kem_vai_tro_khach', 10, 2 );" ) !== false
          && strpos( $__bt_fn, "add_action( 'add_user_role', 'sitetop_admin_kem_vai_tro_khach', 10, 2 );" ) !== false, 'Moc gan customer ngay khi cap quyen admin (set_user_role + add_user_role)' );

/* ---- Bong bóng "Bình luận" ở admin: giữ số đếm toàn site 10 phút (5 câu COUNT ở MỌI trang admin) ---- */
$__bt_bl = $__bt_cat( $__bt_fn, 'function sitetop_dem_binh_luan_giu(' );
assert_true( $__bt_bl !== '', 'Tim thay sitetop_dem_binh_luan_giu' );
$__bt_ban2 = '<?php
$OPT = array(); $DEM = 0; $LOC = array();
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS["OPT"] ) ? $GLOBALS["OPT"][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS["OPT"][ $k ] = $v; $GLOBALS["NAP"] = $a; return true; }
function delete_option( $k ) { unset( $GLOBALS["OPT"][ $k ] ); return true; }
function sitetop_dem_binh_luan_giu( $dem, $post_id ) ' . $__bt_bl . '
function sitetop_dem_binh_luan_bo_giu() { delete_option( "sitetop_dem_binh_luan" ); }
/* wp_count_comments giả đúng khuôn lõi WP: áp filter trước, rỗng thì tự đếm */
function wp_count_comments( $post_id = 0 ) {
    $f = sitetop_dem_binh_luan_giu( array(), $post_id );
    if ( ! empty( $f ) ) return $f;
    $GLOBALS["DEM"]++;
    return (object) array( "approved" => $GLOBALS["THAT"], "moderated" => 0, "spam" => 0, "trash" => 0, "post-trashed" => 0, "total_comments" => $GLOBALS["THAT"], "all" => $GLOBALS["THAT"] );
}
$out = array(); $THAT = 1;
$a = wp_count_comments(); $b = wp_count_comments();
$out["dem_2_lan"] = $DEM; $out["giong"] = ( (array) $a == (array) $b ); $out["post_trashed"] = $b->{"post-trashed"}; $out["nap_san"] = $GLOBALS["NAP"];
$THAT = 2; $c = wp_count_comments(); $out["chua_bo_giu"] = $c->approved;          // trong 10 phút: vẫn số giữ
sitetop_dem_binh_luan_bo_giu(); $d = wp_count_comments(); $out["sau_bo_giu"] = $d->approved;   // có bình luận mới → đếm lại
$OPT["sitetop_dem_binh_luan"]["t"] = time() - 601; $THAT = 3; $e = wp_count_comments(); $out["het_10_phut"] = $e->approved;
$DEM = 0; wp_count_comments( 5 ); wp_count_comments( 5 ); $out["theo_bai_khong_giu"] = $DEM;
echo json_encode( $out );';
$__bt_f2 = sys_get_temp_dir() . '/st-bt2-' . getmypid() . '.php';
file_put_contents( $__bt_f2, $__bt_ban2 );
$__bt_r2 = (string) shell_exec( 'php ' . escapeshellarg( $__bt_f2 ) . ' 2>&1' );
@unlink( $__bt_f2 );
$B2 = json_decode( trim( $__bt_r2 ), true );
assert_true( is_array( $B2 ), 'Chay duoc bo dem binh luan. Ra: ' . substr( $__bt_r2, 0, 400 ) );
if ( is_array( $B2 ) ) {
    assert_true( $B2['dem_2_lan'] === 1 && $B2['giong'] === true && $B2['post_trashed'] === 0 && $B2['nap_san'] === true,
        'Hai lan goi → dem that 1 lan, lan 2 tu so giu (giong het, ke ca truong post-trashed), so giu nap san' );
    assert_true( $B2['chua_bo_giu'] === 1 && $B2['sau_bo_giu'] === 2 && $B2['het_10_phut'] === 3, 'Trong 10 phut dung so giu; bo giu (binh luan moi) hoac het 10 phut → dem lai' );
    assert_equals( 2, $B2['theo_bai_khong_giu'], 'Dem theo TUNG BAI (post_id > 0) khong dung so giu' );
}
foreach ( array( 'wp_insert_comment', 'transition_comment_status', 'deleted_comment', 'trashed_comment', 'spammed_comment' ) as $__bt_moc ) {
    assert_true( strpos( $__bt_fn, "'" . $__bt_moc . "'" ) !== false, 'Bo so giu khi: ' . $__bt_moc );
}
$__bt_bm = (string) file_get_contents( $__bt_goc . '/includes/bao-mat.php' );
assert_true( strpos( $__bt_bm, "get_transient( 'sitetop_da_don_file_lo' )" ) === false && strpos( $__bt_bm, "update_option( 'sitetop_moc_don_file_lo', time(), true );" ) !== false,
    'bao-mat: moc don file lo dung option nap san, khong con transient (1 cau doc rieng moi trang admin)' );
