<?php
/* Siết bảo mật — canh đúng một điều sống còn: header bảo mật KHÔNG được áp toàn site.
   page-widget-captcha.php và page-widget-bridge.php cố ý đặt X-Frame-Options: ALLOWALL để
   nhúng được vào web khách. Nếu ai đó móc sitetop_gui_header_bao_mat() vào send_headers hay
   template_redirect thì SAMEORIGIN đè lên iframe captcha -> không phiên nào ghi được cờ
   captcha -> toàn bộ user làm đúng vẫn mất thưởng, hỏng âm thầm không ai báo lỗi. */
$__bm = file_get_contents( dirname( __DIR__, 2 ) . '/includes/bao-mat.php' );

assert_true( $__bm !== '' && $__bm !== false, 'Phai doc duoc includes/bao-mat.php' );

// --- Có đủ 5 lớp ---
assert_true( strpos( $__bm, "define( 'DISALLOW_FILE_EDIT', true )" ) !== false,
    'PHAI khoa trinh sua file (DISALLOW_FILE_EDIT)' );
assert_true( strpos( $__bm, "unset( \$routes['/wp/v2/users'] )" ) !== false,
    'PHAI chan REST liet ke tai khoan' );
assert_true( strpos( $__bm, "remove_action( 'wp_head', 'wp_generator' )" ) !== false,
    'PHAI bo thẻ generator' );
assert_true( strpos( $__bm, "header_remove( 'X-Powered-By' )" ) !== false,
    'PHAI bo header X-Powered-By' );
assert_true( strpos( $__bm, 'wp-links-opml.php' ) !== false,
    'PHAI dong wp-links-opml.php' );

// --- ĐIỀU SỐNG CÒN: header chỉ ở admin + 3 template đăng nhập ---
assert_true( strpos( $__bm, "add_action( 'admin_init', 'sitetop_gui_header_bao_mat' )" ) !== false,
    'Header PHAI gan vao admin_init' );
assert_true( strpos( $__bm, "add_action( 'send_headers', 'sitetop_gui_header_bao_mat' )" ) === false,
    'Header TUYET DOI khong duoc gan vao send_headers (ap toan site -> vo hieu iframe captcha)' );
assert_true( strpos( $__bm, "add_action( 'template_redirect', 'sitetop_gui_header_bao_mat' )" ) === false,
    'Header TUYET DOI khong duoc gan vao template_redirect (ap toan site)' );
assert_true( strpos( $__bm, "add_action( 'init', 'sitetop_gui_header_bao_mat' )" ) === false,
    'Header TUYET DOI khong duoc gan vao init' );
assert_true( strpos( $__bm, "add_action( 'wp', 'sitetop_gui_header_bao_mat' )" ) === false,
    'Header TUYET DOI khong duoc gan vao wp' );

// Danh sách template được phép nhận header: đúng 3 trang đăng nhập, không hơn
foreach ( array( 'page-login.php', 'page-register.php', 'page-forgot-password.php' ) as $__t ) {
    assert_true( strpos( $__bm, "'" . $__t . "'" ) !== false, 'Template duoc phep phai co ' . $__t );
}
foreach ( array( 'page-widget-captcha.php', 'page-widget-bridge.php', 'page-unlock.php', 'widget.js.php' ) as $__c ) {
    assert_true( strpos( $__bm, "'" . $__c . "'" ) === false,
        'TUYET DOI khong duoc dua ' . $__c . ' vao dien nhan header' );
}

// admin-ajax phải được loại trừ: widget trên web khách gọi vào đó
$__vt_fn = strpos( $__bm, 'function sitetop_gui_header_bao_mat()' );
$__than  = $__vt_fn === false ? '' : substr( $__bm, $__vt_fn, 700 );
assert_true( strpos( $__than, 'wp_doing_ajax()' ) !== false,
    'Ham gui header PHAI bo qua admin-ajax (duong widget goi vao)' );
$__vt_ajax = strpos( $__than, 'wp_doing_ajax()' );
$__vt_hdr  = strpos( $__than, 'X-Frame-Options' );
assert_true( $__vt_hdr !== false && $__vt_ajax < $__vt_hdr,
    'Kiem admin-ajax phai dung TRUOC khi gui header' );

// --- Hai file phải giữ nguyên quyền cho nhúng iframe ---
foreach ( array( 'page-widget-captcha.php', 'page-widget-bridge.php' ) as $__f ) {
    $__n = file_get_contents( dirname( __DIR__, 2 ) . '/' . $__f );
    assert_true( strpos( $__n, 'X-Frame-Options: ALLOWALL' ) !== false,
        $__f . ' PHAI giu X-Frame-Options: ALLOWALL de nhung duoc vao web khach' );
    assert_true( strpos( $__n, 'frame-ancestors *' ) !== false,
        $__f . ' PHAI giu frame-ancestors *' );
}

// --- REST: người ĐÃ đăng nhập vẫn phải dùng được, kẻo vỡ admin ---
$__vt_rest = strpos( $__bm, "add_filter( 'rest_endpoints'" );
$__rest    = $__vt_rest === false ? '' : substr( $__bm, $__vt_rest, 400 );
assert_true( strpos( $__rest, 'is_user_logged_in()' ) !== false,
    'Chan REST phai chua duong thoat cho nguoi da dang nhap' );
$__vt_login = strpos( $__rest, 'is_user_logged_in()' );
$__vt_unset = strpos( $__rest, 'unset(' );
assert_true( $__vt_unset !== false && $__vt_login < $__vt_unset,
    'Kiem dang nhap phai dung TRUOC khi go route' );

/* ---- Phần dọn file: canh cho nó KHÔNG BAO GIỜ xoá nhầm ---- */
$__vt_don = strpos( $__bm, "array( 'readme.html', 'license.txt' )" );
assert_true( $__vt_don !== false, 'Danh sach file xoa phai cam cung dung 2 ten' );
$__khoi = $__vt_don === false ? '' : substr( $__bm, $__vt_don - 900, 1400 );
assert_true( strpos( $__khoi, 'ABSPATH . $_ten' ) !== false,
    'Chi duoc ghep vao ABSPATH, khong nhan duong dan tu ngoai' );
assert_true( strpos( $__khoi, 'basename( $_ten ) !== $_ten' ) !== false,
    'Phai kiem basename truoc khi xoa' );
assert_true( strpos( $__khoi, 'is_file(' ) !== false && strpos( $__khoi, 'is_writable(' ) !== false,
    'Phai kiem is_file va is_writable truoc khi unlink' );
assert_true( strpos( $__khoi, "add_action( 'admin_init'" ) !== false,
    'Chi chay trong wp-admin' );
assert_true( strpos( $__khoi, 'wp_doing_ajax()' ) !== false,
    'Phai bo qua admin-ajax' );
assert_true( strpos( $__khoi, 'get_transient(' ) !== false,
    'Phai tiet luu, khong chay moi request' );
// Tuyệt đối không được đụng file lõi có vai trò chạy
foreach ( array( 'wp-config.php', 'index.php', '.htaccess', 'wp-load.php', 'wp-settings.php' ) as $__nguyhiem ) {
    assert_true( strpos( $__khoi, "'" . $__nguyhiem . "'" ) === false,
        'TUYET DOI khong duoc dua ' . $__nguyhiem . ' vao danh sach xoa' );
}

/* ---- Chặn dò username qua trang tác giả ---- */
assert_true( strpos( $__bm, "add_action( 'parse_request'" ) !== false,
    'Phai cat o parse_request (template_redirect la da muon, canonical da 301 lo ten)' );
$__vt_tg = strpos( $__bm, "add_action( 'parse_request'" );
$__tg    = $__vt_tg === false ? '' : substr( $__bm, $__vt_tg, 700 );
/* Neo vào ĐÚNG DẠNG LỆNH KIỂM TRA, không tìm chữ trần: 'author_name' còn xuất hiện ở dòng
   unset() nên strpos trần vẫn thấy dù điều kiện phát hiện đã bị gỡ — thử phá lần đầu lọt
   đúng vì lý do này. */
assert_true( strpos( $__tg, 'isset( $wp->query_vars[\'author\'] )' ) !== false,
    'Phai CO LENH isset kiem query var author' );
assert_true( strpos( $__tg, 'isset( $wp->query_vars[\'author_name\'] )' ) !== false,
    'Phai CO LENH isset kiem author_name (duong /author/<ten>/)' );
assert_true( strpos( $__tg, 'isset( $_GET[\'author\'] )' ) !== false,
    'Phai CO LENH isset kiem ?author= tren URL' );
assert_true( strpos( $__tg, "'error'" ) !== false && strpos( $__tg, '404' ) !== false,
    'Phai tra 404, khong duoc chuyen huong' );
assert_true( strpos( $__tg, 'wp_redirect' ) === false && strpos( $__tg, 'wp_safe_redirect' ) === false,
    'TUYET DOI khong duoc chuyen huong (Location se khai username)' );
assert_true( strpos( $__tg, 'is_user_logged_in()' ) !== false,
    'Nguoi da dang nhap phai di qua duoc, keo vo khu quan tri' );
// Không được gỡ redirect_canonical toàn cục — hỏng chuẩn hoá URL của cả site
assert_true( strpos( $__bm, "remove_action( 'template_redirect', 'redirect_canonical'" ) === false,
    'KHONG duoc go redirect_canonical toan cuc' );
