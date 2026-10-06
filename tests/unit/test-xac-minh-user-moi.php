<?php
/* XÁC MINH TÀI KHOẢN CHO USER MỚI — 06/10/2026.

   Chủ site yêu cầu: user mới đăng ký xong thấy thẻ "Xác minh tài khoản — Bước 1/2", khai
   nguồn traffic (mỗi nguồn MỘT DÒNG), gửi xong chuyển sang "Đang chờ xét duyệt" và yêu cầu
   vào thẳng tab Duyệt nguồn của admin. Ràng buộc gắt nhất: "User đang hoạt động hiện tại
   giữ nguyên hoàn toàn" — đo 06/10 trên .one: 44 user đã khai nguồn, 13 đang chờ duyệt.

   Bản đầu cổng so theo MỐC ĐĂNG KÝ để user cũ không bị động. 06/10 tối chủ site chốt lại: "đã có
   1 nguồn duyệt là vào bình thường, chưa khai báo nguồn sẽ bị [gác]" → cổng so theo TRẠNG THÁI NGUỒN,
   không còn mốc; .one 2 và .net 15 user cũ chưa có nguồn duyệt sẽ qua cổng cho tới khi được duyệt.

   Test NẠP THẲNG includes/source-approval.php (file thật) với khung WordPress giả, rồi gọi
   chính các hàm thật — không chép lại logic. */

$__xm_goc = dirname( __DIR__, 2 );

$__xm_chay = function ( $kich ) use ( $__xm_goc ) {
    $nen = <<<'PHP'
define( 'ABSPATH', '/tmp/' );
error_reporting( E_ALL );
class WP_Error {
    public $code, $msg;
    public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->msg = $m; }
    public function get_error_message() { return $this->msg; }
    public function get_error_code() { return $this->code; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
$GLOBALS['OPT']=array(); $GLOBALS['UMETA']=array(); $GLOBALS['HOOK']=array(); $GLOBALS['BAN']=array();
function get_option( $k, $d = false ) { return array_key_exists($k,$GLOBALS['OPT']) ? $GLOBALS['OPT'][$k] : $d; }
function update_option( $k, $v, $a = true ) { $GLOBALS['OPT'][$k] = $v; return true; }
function sitetop_get_option( $k, $d = null ) { return array_key_exists($k,$GLOBALS['OPT']) ? $GLOBALS['OPT'][$k] : $d; }
function get_user_meta( $u, $k, $single = false ) { return $GLOBALS['UMETA'][$u][$k] ?? ( $single ? '' : array() ); }
function update_user_meta( $u, $k, $v ) { $GLOBALS['UMETA'][$u][$k] = $v; return true; }
function delete_user_meta( $u, $k ) { unset( $GLOBALS['UMETA'][$u][$k] ); return true; }
function get_user_by( $f, $id ) { return $GLOBALS['USERS'][$id] ?? false; }
function user_can( $u, $cap ) { return ! empty( $GLOBALS['ADMIN'][ is_object($u) ? $u->ID : $u ] ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function sitetop_current_time() { return '2026-10-06 10:00:00'; }
function add_action( $h, $f, $p = 10, $n = 1 ) { $GLOBALS['HOOK'][$h][] = $f; }
function remove_action( $h, $f, $p = 10 ) {
    if ( empty( $GLOBALS['HOOK'][$h] ) ) return false;
    $GLOBALS['HOOK'][$h] = array_values( array_diff( $GLOBALS['HOOK'][$h], array( $f ) ) );
    return true;
}
function has_action( $h, $f = false ) {
    if ( $f === false ) return ! empty( $GLOBALS['HOOK'][$h] );
    return in_array( $f, $GLOBALS['HOOK'][$h] ?? array(), true ) ? 10 : false;
}
function do_action( $h, $a = null, $b = null ) {
    $GLOBALS['BAN'][] = array( 'hook' => $h, 'a' => $a, 'b' => $b );
    foreach ( $GLOBALS['HOOK'][$h] ?? array() as $f ) if ( function_exists( $f ) ) $f( $a, $b );
}
function is_user_logged_in() { return true; }
function get_current_user_id() { return 7; }
function check_ajax_referer( $a, $b ) { return true; }
/* Ném lỗi thay vì exit: có ca cần chạy TIẾP sau khi cổng từ chối để kiểm dữ liệu còn nguyên. */
function wp_send_json_error( $m = null ) { echo json_encode( array( 'ok' => false, 'm' => $m ) ); throw new Exception( '__json_exit' ); }
function wp_send_json_success( $m = null ) { echo json_encode( array( 'ok' => true, 'd' => $m ) ); throw new Exception( '__json_exit' ); }
function sitetop_rate_limit_check( $k ) { return array( 'allowed' => true ); }
function sitetop_telegram_notify_admin( $t, $f = array() ) { $GLOBALS['TELE'][] = $t; return true; }
function sitetop_report_telegram_configured() { return true; }
function admin_url( $p = '' ) { return 'https://site/wp-admin/' . $p; }
class XM_Wpdb {
    public $usermeta = 'wpgd_usermeta';
    public function prepare( $q, ...$a ) { return $q; }
    public function get_var( $q ) { return 1; }
}
$GLOBALS['wpdb'] = new XM_Wpdb();
PHP;
    $ma = $nen . "\n"
        . '$GLOBALS["USERS"] = array();' . "\n"
        . $kich['truoc'] . "\n"
        . "require '" . $__xm_goc . "/includes/source-approval.php';\n"
        . $kich['sau'];
    $p = proc_open( array( PHP_BINARY, '-d', 'display_errors=stderr', '-r', $ma ),
        array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $ong );
    $out = stream_get_contents( $ong[1] ); $err = stream_get_contents( $ong[2] );
    fclose( $ong[1] ); fclose( $ong[2] ); proc_close( $p );
    $j = json_decode( trim( $out ), true );
    return array( is_array( $j ) ? $j : array(), $err ?: $out );
};

/* ═══ A. CỔNG "USER MỚI" ═══ */
$__xm_cong = function ( $dang_ky, $them = '' ) use ( $__xm_chay ) {
    return $__xm_chay( array(
        'truoc' => '$GLOBALS["OPT"]["sitetop_onboard_src_since"] = 1000;' . "\n"
                 . '$GLOBALS["USERS"][7] = (object) array( "ID" => 7, "display_name" => "nguoi_test", "user_login" => "nguoi_test", "user_email" => "a@b.c", "user_registered" => gmdate("Y-m-d H:i:s", ' . $dang_ky . ') );' . "\n"
                 . $them,
        'sau'   => 'echo json_encode( array( "moi" => sitetop_la_user_moi_khai_nguon( 7 ) ) );',
    ) );
};
// A1. Đăng ký SAU mốc → vào luồng xác minh mới.
list( $__xm_k, $__xm_e ) = $__xm_cong( 2000 );
assert_true( ! empty( $__xm_k['moi'] ), 'User dang ky SAU moc phai vao luong xac minh moi. stderr: ' . $__xm_e );
// A2. Đăng ký TRƯỚC mốc nhưng CHƯA có nguồn được duyệt → cũng qua cổng (chủ site chốt lại 06/10 tối).
list( $__xm_k, $__xm_e ) = $__xm_cong( 500 );
assert_true( ! empty( $__xm_k['moi'] ), 'User cu chua co nguon duyet cung phai qua cong (het cat theo moc). stderr: ' . $__xm_e );
// A2b. Màn chờ chỉ khi còn nguồn ĐANG CHỜ; chỉ có nguồn bị từ chối → hiện lại form để khai nguồn khác.
$__xm_ud = (string) file_get_contents( dirname( __DIR__, 2 ) . '/page-user-dashboard.php' );
assert_true( strpos( $__xm_ud, "\$xm_cho   = (bool) array_filter( \$xm_items, function ( \$i ) { return ( \$i['status'] ?? '' ) === 'pending'; } );" ) !== false,
    'xm_cho = con nguon pending; chi co nguon bi tu choi thi thay form (khong ket o man cho)' );
// A3. Đã có nguồn được duyệt → thôi onboarding, về luồng cũ.
list( $__xm_k, $__xm_e ) = $__xm_cong( 2000,
    '$GLOBALS["UMETA"][7]["sitetop_src_items"] = array( array("id"=>"a","text"=>"https://facebook.com/abc","status"=>"approved") );' );
assert_true( empty( $__xm_k['moi'] ), 'Da duoc duyet nguon thi khong giu trong man onboarding. stderr: ' . $__xm_e );
// A4. Admin được miễn.
list( $__xm_k, $__xm_e ) = $__xm_cong( 2000, '$GLOBALS["ADMIN"][7] = 1;' );
assert_true( empty( $__xm_k['moi'] ), 'Admin duoc mien duyet nguon. stderr: ' . $__xm_e );
// A5. Tắt cổng duyệt nguồn thì không hiện gì.
list( $__xm_k, $__xm_e ) = $__xm_cong( 2000, '$GLOBALS["OPT"]["require_source_approval"] = 0;' );
assert_true( empty( $__xm_k['moi'] ), 'Tat cong duyet nguon thi khong hien man xac minh. stderr: ' . $__xm_e );

/* ═══ B. KHAI NHIỀU NGUỒN MỘT LẦN ═══ */
$__xm_khai = function ( $text, $truoc = '' ) use ( $__xm_chay ) {
    return $__xm_chay( array(
        'truoc' => '$GLOBALS["USERS"][7] = (object) array( "ID" => 7, "display_name" => "nguoi_test", "user_login" => "nguoi_test", "user_email" => "a@b.c", "user_registered" => "2026-10-06 09:00:00" );' . "\n" . $truoc,
        'sau'   => '$r = sitetop_add_source_many( 7, ' . var_export( $text, true ) . ' );' . "\n"
                 . '$ban = 0; foreach ( $GLOBALS["BAN"] as $b ) if ( $b["hook"] === "sitetop_source_submitted" ) $ban++;' . "\n"
                 . 'echo json_encode( array(' . "\n"
                 . '  "loi" => is_wp_error($r) ? $r->get_error_message() : "",' . "\n"
                 . '  "them" => is_wp_error($r) ? 0 : $r["them"],' . "\n"
                 . '  "items" => array_map( function($i){ return $i["text"]; }, sitetop_get_source_items(7) ),' . "\n"
                 . '  "trangthai" => sitetop_get_source_status(7),' . "\n"
                 . '  "cho_duyet" => sitetop_source_has_pending(7),' . "\n"
                 . '  "duoc_rut_gon" => sitetop_source_is_approved(7),' . "\n"
                 . '  "so_lan_bao" => $ban, "tele" => $GLOBALS["TELE"] ?? array() ) );',
    ) );
};
// B1. Bốn dòng → bốn nguồn chờ duyệt, và CHỈ BÁO ADMIN MỘT LẦN.
list( $__xm_k, $__xm_e ) = $__xm_khai( "https://facebook.com/trangcuatoi\nhttps://youtube.com/@kenh\nhttps://tiktok.com/@abc\nhttps://website-cua-toi.com" );
assert_equals( 4, (int) ( $__xm_k['them'] ?? 0 ), 'Bon dong phai thanh bon nguon. stderr: ' . $__xm_e );
assert_equals( 'pending', $__xm_k['trangthai'] ?? '', 'Khai xong phai la "cho duyet" — vao hang doi tab Duyet nguon' );
assert_true( ! empty( $__xm_k['cho_duyet'] ), 'Phai bat co pending de admin thay yeu cau moi' );
assert_true( empty( $__xm_k['duoc_rut_gon'] ), 'Chua duyet thi chua duoc rut gon link' );
/* Đếm TIN BÁO THẬT gửi admin, không đếm số lần bắn hook: hook vẫn bắn cho từng nguồn để
   các chỗ nghe khác không bị hụt, chỉ riêng hàm báo Telegram bị gỡ ra rồi gọi gộp một lần. */
assert_equals( 1, count( (array) ( $__xm_k['tele'] ?? array() ) ),
    'Chi duoc gui admin MOT tin cho ca lan khai — khong thi khai 4 dong la 4 tin Telegram' );

// B2. Dòng trống và khoảng trắng bị bỏ qua, không tạo nguồn rác.
list( $__xm_k, $__xm_e ) = $__xm_khai( "\n\nhttps://facebook.com/trangcuatoi\n   \n\nhttps://youtube.com/@kenh\n\n" );
assert_equals( 2, (int) ( $__xm_k['them'] ?? 0 ), 'Dong trong phai bi bo qua. stderr: ' . $__xm_e );

// B3. Trùng nhau chỉ tính một.
list( $__xm_k, $__xm_e ) = $__xm_khai( "https://facebook.com/trangcuatoi\nhttps://facebook.com/trangcuatoi" );
assert_equals( 1, (int) ( $__xm_k['them'] ?? 0 ), 'Nguon trung chi tinh mot. stderr: ' . $__xm_e );

// B4. Không nhập gì → báo lỗi, không tạo nguồn rỗng.
list( $__xm_k, $__xm_e ) = $__xm_khai( "\n   \n" );
assert_equals( 0, (int) ( $__xm_k['them'] ?? 0 ), 'Khong nhap gi thi khong tao nguon nao' );
assert_true( ( $__xm_k['loi'] ?? '' ) !== '', 'Khong nhap gi phai bao loi ro rang' );

// B5. Quá 2000 ký tự → chặn (đúng con số ghi trên giao diện).
list( $__xm_k, $__xm_e ) = $__xm_khai( str_repeat( 'a', 2001 ) );
assert_true( strpos( (string) ( $__xm_k['loi'] ?? '' ), '2000' ) !== false,
    'Qua 2000 ky tu phai bao dung gioi han ghi tren giao dien. stderr: ' . $__xm_e );

// B6. Trần 10 nguồn của hệ thống vẫn giữ nguyên.
list( $__xm_k, $__xm_e ) = $__xm_khai( implode( "\n", array_map( function ( $i ) { return 'https://nguon-so-' . $i . '.com'; }, range( 1, 14 ) ) ) );
assert_equals( 10, count( (array) ( $__xm_k['items'] ?? array() ) ),
    'Tran 10 nguon cua he thong phai giu nguyen. stderr: ' . $__xm_e );

/* ═══ C. CỔNG CHẶN + KHÔNG ĐỤNG LUỒNG CŨ ═══ */
// C1. Thêm một nguồn kiểu cũ vẫn chạy y nguyên (hồi quy).
list( $__xm_k, $__xm_e ) = $__xm_chay( array(
    'truoc' => '$GLOBALS["USERS"][7] = (object) array( "ID" => 7, "display_name" => "n", "user_login" => "n", "user_email" => "a@b.c", "user_registered" => "2026-10-06 09:00:00" );',
    'sau'   => '$r = sitetop_add_source_item( 7, "https://facebook.com/trangcuatoi" );' . "\n"
             . 'echo json_encode( array( "ok" => $r === true, "so" => count( sitetop_get_source_items(7) ),'
             . ' "trangthai" => sitetop_get_source_status(7) ) );',
) );
assert_true( ! empty( $__xm_k['ok'] ) && (int) $__xm_k['so'] === 1 && $__xm_k['trangthai'] === 'pending',
    'Luong them tung nguon (o cu) phai chay y nguyen. stderr: ' . $__xm_e );

$__xm_dash = (string) file_get_contents( $__xm_goc . '/page-user-dashboard.php' );
$__xm_trang = (string) file_get_contents( $__xm_goc . '/includes/trang-xac-minh.php' );

/* C2. PHẢI LÀ CỔNG CHẶN, KHÔNG PHẢI THẺ NHẮC — chủ site chốt 06/10: "Duyệt mới được phép
   truy cập vào hệ thống". Ẩn bằng CSS là mở F12 vẫn đọc được số dư, link, rate. */
$__xm_vt_cong = strpos( $__xm_dash, 'sitetop_la_user_moi_khai_nguon( $user_id )' );
assert_true( $__xm_vt_cong !== false, 'Dashboard phai hoi cong "user moi"' );
$__xm_sau_cong = substr( $__xm_dash, $__xm_vt_cong, 700 );
assert_true( strpos( $__xm_sau_cong, "include get_template_directory() . '/includes/trang-xac-minh.php';" ) !== false
          && strpos( $__xm_sau_cong, 'exit;' ) !== false,
    'SONG CON: chua duyet thi dung trang rieng roi EXIT — khong duoc render dashboard phia sau' );

// C3. Cổng phải đứng TRƯỚC mọi truy vấn thống kê (không chạy 8 câu SQL cho người chưa vào được).
$__xm_vt_sql = strpos( $__xm_dash, "\$prefix = \$wpdb->prefix . 'sitetop_';" );
assert_true( $__xm_vt_sql !== false && $__xm_vt_cong < $__xm_vt_sql,
    'Cong phai dat TRUOC cac truy van thong ke cua dashboard' );

/* C4. DUYỆT XONG GIỮ NGUYÊN Ô "NGUỒN FILE GỐC" — chủ site chốt thêm 06/10: user đổi nguồn
   thì vẫn phải tự thêm / xoá nguồn được. Điều kiện ô đó phải y như bản gốc. */
assert_true( strpos( $__xm_dash, 'if ( ! $src_exempt && ( $src_gate || $src_items ) ) :' ) !== false,
    'O "Nguon file goc" phai giu nguyen dieu kien goc — duyet xong user van them/xoa nguon duoc' );
assert_true( strpos( $__xm_dash, '$ob_moi' ) === false,
    'Khong duoc con bien $ob_moi sot lai trong dashboard (the cu da chuyen thanh trang cong)' );
assert_true( strpos( $__xm_dash, 'Thêm nguồn' ) !== false, 'Nut "Them nguon" cua o cu phai con' );

// C5. Trang cổng có đủ hai trạng thái đúng như ảnh chủ site gửi.
foreach ( array( 'Xác minh tài khoản', 'Bước 1/2', 'Khai báo nguồn traffic để bắt đầu sử dụng',
                 'NGUỒN VIEW / TRAFFIC', 'Mỗi nguồn một dòng · Tối đa 2000 ký tự', 'Gửi yêu cầu xác minh',
                 'Đang chờ xét duyệt', 'Yêu cầu đang được xử lý', 'Chờ admin xác minh', 'Hỗ trợ 24/7',
                 // Câu chữ chủ site chốt 06/10 — sửa chữ thì sửa cả ở đây cho khỏi trôi.
                 'Bạn đã đăng ký nguồn View Nhiệm Vụ bên dưới. Hãy liên hệ Admin Gửi Email để được xác minh nhanh hơn.' ) as $__xm_chu ) {
    assert_true( strpos( $__xm_trang, $__xm_chu ) !== false, 'Trang cong thieu chu: "' . $__xm_chu . '"' );
}
// Dòng thông báo chờ duyệt phải màu ĐỎ (chủ site chốt 06/10).
assert_true( preg_match( '/\.xm-wait p\{[^}]*color:var\(--err\)/', $__xm_trang ) === 1,
    'Dong thong bao cho duyet phai de mau do' );
// Link Telegram lấy từ cấu hình, không gắn cứng.
assert_true( strpos( $__xm_trang, 'https://t.me/<?php echo esc_attr( $xm_tg ); ?>' ) !== false,
    'Link Telegram phai lay tu cau hinh source_telegram' );
// Có đường thoát: user bị chặn vẫn đăng xuất được, không bị nhốt.
assert_true( strpos( $__xm_trang, 'wp_logout_url' ) !== false,
    'Trang cong phai co nut Dang xuat — khong duoc nhot user' );
// Trang cổng KHÔNG được in số liệu của dashboard.
foreach ( array( 'Số dư', 'balance', 'total_earned', 'Rút tiền' ) as $__xm_cam ) {
    assert_true( strpos( $__xm_trang, $__xm_cam ) === false,
        'Trang cong khong duoc lo du lieu dashboard: "' . $__xm_cam . '"' );
}

/* ═══ C6. CÂU CHỮ Ô "NGUỒN FILE GỐC" — chủ site chốt 06/10/2026 ═══
   Hai chỗ in cùng một nội dung (markup trong dashboard + sitetop_source_hint_text() dùng cho
   thông báo sau khi thêm nguồn). Lệch nhau là user đọc hai kiểu khác nhau. */
assert_true( strpos( $__xm_dash, 'Muốn duyệt nguồn mới, Xoá. Inbox Admin gửi Email Telegram' ) !== false,
    'O "Nguon file goc" phai dung cau chu moi (dong 1)' );
assert_true( strpos( $__xm_dash, '<b class="src-tip-nhan">Muốn Thêm Nguồn quay video gửi về admin</b>' ) !== false,
    'Dong 2 phai in dam dung cau chu moi' );
assert_true( strpos( $__xm_dash, 'Kèm Video ngắn chứng minh chủ nguồn' ) === false
          && strpos( $__xm_dash, 'Muốn hoạt động nhanh' ) === false,
    'Khong duoc con sot cau chu cu' );

list( $__xm_k, $__xm_e ) = $__xm_chay( array(
    'truoc' => '$GLOBALS["OPT"]["source_telegram"] = "sitetopnet";',
    'sau'   => 'echo json_encode( array( "chu" => sitetop_source_hint_text() ) );',
) );
$__xm_chu_nhac = (string) ( $__xm_k['chu'] ?? '' );
assert_true( strpos( $__xm_chu_nhac, 'Muốn duyệt nguồn mới, Xoá. Inbox Admin gửi Email Telegram @sitetopnet' ) !== false,
    'sitetop_source_hint_text() phai dung cau chu moi + lay telegram tu cau hinh. Ra: ' . $__xm_chu_nhac . ' stderr: ' . $__xm_e );
assert_true( strpos( $__xm_chu_nhac, 'Muốn Thêm Nguồn quay video gửi về admin' ) !== false,
    'sitetop_source_hint_text() phai co ca ve sau. Ra: ' . $__xm_chu_nhac );

/* ═══ C7. USER KHÔNG ĐƯỢC TỰ XOÁ NGUỒN — chủ site chốt 06/10/2026 ═══
   Chặn ở MÁY CHỦ, không chỉ ẩn nút: ẩn nút thì gọi thẳng admin-ajax vẫn xoá được. */
assert_true( strpos( $__xm_dash, 'src-del' ) === false && strpos( $__xm_dash, 'deleteSource' ) === false,
    'Dashboard khong duoc con nut xoa nguon (markup, JS, CSS)' );

list( $__xm_k, $__xm_e ) = $__xm_chay( array(
    'truoc' => '$GLOBALS["USERS"][7] = (object) array( "ID" => 7, "display_name" => "n", "user_login" => "n", "user_email" => "a@b.c", "user_registered" => "2026-10-06 09:00:00" );' . "\n"
             . '$GLOBALS["UMETA"][7]["sitetop_src_items"] = array( array("id"=>"i1","text"=>"https://facebook.com/abc","status"=>"approved","added_at"=>"","note"=>""), array("id"=>"i2","text"=>"https://youtube.com/@x","status"=>"pending","added_at"=>"","note"=>"") );',
    'sau'   => '$_POST["item_id"] = "i1";' . "\n"
             . 'ob_start(); $thoat = false;' . "\n"
             . 'try { sitetop_ajax_delete_source(); } catch ( Throwable $e ) { $thoat = true; }' . "\n"
             . 'echo ob_get_clean();',
) );
assert_true( isset( $__xm_k['ok'] ) && $__xm_k['ok'] === false,
    'Cong xoa nguon phai TU CHOI user. Ra: ' . json_encode( $__xm_k ) . ' stderr: ' . $__xm_e );
assert_true( strpos( (string) ( $__xm_k['m'] ?? '' ), 'liên hệ Admin' ) !== false,
    'Loi bao phai chi duong: lien he Admin. Ra: ' . ( $__xm_k['m'] ?? '' ) );

// Nguồn vẫn còn nguyên sau khi bị từ chối.
list( $__xm_k2, $__xm_e2 ) = $__xm_chay( array(
    'truoc' => '$GLOBALS["USERS"][7] = (object) array( "ID" => 7, "display_name" => "n", "user_login" => "n", "user_email" => "a@b.c", "user_registered" => "2026-10-06 09:00:00" );' . "\n"
             . '$GLOBALS["UMETA"][7]["sitetop_src_items"] = array( array("id"=>"i1","text"=>"https://facebook.com/abc","status"=>"approved","added_at"=>"","note"=>"") );',
    'sau'   => '$_POST["item_id"] = "i1";' . "\n"
             . 'ob_start(); try { sitetop_ajax_delete_source(); } catch ( Throwable $e ) {} ob_end_clean();' . "\n"
             . 'echo json_encode( array( "con" => count( sitetop_get_source_items(7) ), "duoc_rut_gon" => sitetop_source_is_approved(7) ) );',
) );
assert_equals( 1, (int) ( $__xm_k2['con'] ?? 0 ),
    'Bi tu choi thi nguon phai CON NGUYEN. stderr: ' . $__xm_e2 );
assert_true( ! empty( $__xm_k2['duoc_rut_gon'] ), 'Nguon da duyet van con -> van rut gon link duoc' );

// Hàm xoá vẫn giữ cho đường admin / dọn dữ liệu.
list( $__xm_k3, $__xm_e3 ) = $__xm_chay( array(
    'truoc' => '$GLOBALS["USERS"][7] = (object) array( "ID" => 7, "display_name" => "n", "user_login" => "n", "user_email" => "a@b.c", "user_registered" => "2026-10-06 09:00:00" );' . "\n"
             . '$GLOBALS["UMETA"][7]["sitetop_src_items"] = array( array("id"=>"i1","text"=>"https://facebook.com/abc","status"=>"approved","added_at"=>"","note"=>"") );',
    'sau'   => '$r = sitetop_delete_source_item( 7, "i1" );' . "\n"
             . 'echo json_encode( array( "ok" => ! is_wp_error( $r ), "con" => count( sitetop_get_source_items(7) ) ) );',
) );
assert_true( ! empty( $__xm_k3['ok'] ) && (int) $__xm_k3['con'] === 0,
    'Ham xoa van phai dung duoc cho duong admin. stderr: ' . $__xm_e3 );

/* ═══ D. CỔNG AJAX ═══ */
$__xm_src = (string) file_get_contents( $__xm_goc . '/includes/source-approval.php' );
$__xm_vt  = strpos( $__xm_src, 'function sitetop_ajax_submit_sources()' );
assert_true( $__xm_vt !== false, 'Phai co cong sitetop_submit_sources' );
$__xm_than = substr( $__xm_src, $__xm_vt, 900 );
foreach ( array( 'check_ajax_referer', 'is_user_logged_in', 'sitetop_rate_limit_check' ) as $__xm_chot ) {
    assert_true( strpos( $__xm_than, $__xm_chot ) !== false, 'Cong gui nguon phai co chot: ' . $__xm_chot );
}
assert_true( strpos( $__xm_src, "add_action( 'wp_ajax_sitetop_submit_sources'" ) !== false, 'Phai dang ky cong' );
assert_true( strpos( $__xm_src, "wp_ajax_nopriv_sitetop_submit_sources" ) === false,
    'KHONG duoc mo cong cho khach chua dang nhap' );
