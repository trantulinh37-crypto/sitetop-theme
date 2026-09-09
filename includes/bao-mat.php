<?php
/**
 * Siết bảo mật WordPress — 09/09/2026.
 *
 * NGUYÊN TẮC: KHÔNG ĐỘNG TỚI ĐƯỜNG ĐANG VẬN HÀNH.
 * Widget, iframe captcha, trang nhiệm vụ, /api, /st, admin-ajax đều không được thêm header
 * hay ràng buộc gì mới. Mọi header bảo mật dưới đây chỉ gửi ở HAI nơi: trong wp-admin, và
 * khi đang render đúng ba template đăng nhập. Lý do rất cụ thể:
 *   - page-widget-captcha.php và page-widget-bridge.php CỐ Ý đặt X-Frame-Options: ALLOWALL
 *     + frame-ancestors * để nhúng được vào web khách. Đặt SAMEORIGIN toàn site là chặn
 *     chính iframe captcha của mình -> không phiên nào ghi được cờ captcha -> toàn bộ user
 *     làm đúng vẫn mất thưởng, mà hỏng âm thầm không ai báo lỗi.
 *   - Referrer-Policy chặt sẽ cắt bớt referrer gửi sang web đích, trong khi hệ thống dùng
 *     document.referrer / HTTP_REFERER để nhận diện Google và web đích.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── 1. KHOÁ TRÌNH SỬA FILE TRONG WP-ADMIN ──────────────────────────────────────────
   Đo 09/09/2026: theme-editor.php và plugin-editor.php đều trả 200, tức ai vào được admin
   là viết PHP tuỳ ý ngay trong trình duyệt — chiếm hẳn web. Đây là thứ khuếch đại mọi lỗ
   khác, khoá trước tiên. Chỉ ảnh hưởng khu quản trị, không đụng gì phía ngoài. */
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
    define( 'DISALLOW_FILE_EDIT', true );
}

/* ── 2. CHẶN REST API LIỆT KÊ TÀI KHOẢN ─────────────────────────────────────────────
   /wp-json/wp/v2/users đang trả công khai id + username của tài khoản quản trị. Kẻ tấn
   công có username hợp lệ là xong nửa việc dò mật khẩu.
   Đã kiểm: không có chỗ nào trong theme dùng wp/v2/users (API riêng đi qua /api, /st và
   admin-ajax), nên gỡ route này khi CHƯA đăng nhập là an toàn. Người đã đăng nhập vẫn
   dùng bình thường để admin không vỡ. */
add_filter( 'rest_endpoints', function( $routes ) {
    if ( is_user_logged_in() ) return $routes;
    unset( $routes['/wp/v2/users'] );
    unset( $routes['/wp/v2/users/(?P<id>[\d]+)'] );
    return $routes;
} );

/* ── 3. THÔI KHOE PHIÊN BẢN ─────────────────────────────────────────────────────────
   Thẻ <meta generator> đang ghi rõ "WordPress 7.1" và header khoe "PHP/8.1.34". Biết đúng
   phiên bản là biết đúng bộ khai thác nào đáng thử. Bỏ đi không đổi hành vi gì. */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );
add_action( 'send_headers', function() {
    if ( ! headers_sent() ) @header_remove( 'X-Powered-By' );
}, 0 );

/* ── 4. HEADER BẢO MẬT — CHỈ wp-admin VÀ TRANG ĐĂNG NHẬP ────────────────────────────
   Cố ý KHÔNG đặt toàn site (xem ghi chú đầu file). Cũng cố ý bỏ qua admin-ajax: widget
   trên web khách gọi vào đó, không được thêm ràng buộc nào lên đường đó. */
function sitetop_gui_header_bao_mat() {
    if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) return;
    if ( headers_sent() ) return;
    @header( 'X-Frame-Options: SAMEORIGIN' );                       // chống nhúng khung để lừa bấm
    @header( 'X-Content-Type-Options: nosniff' );                   // chống đoán kiểu file
    @header( 'Referrer-Policy: strict-origin-when-cross-origin' );  // thôi rò URL quản trị ra ngoài
    @header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
}
add_action( 'admin_init', 'sitetop_gui_header_bao_mat' );

/* Phía ngoài: bám theo TEMPLATE THẬT đang được render, không đoán theo slug — slug đổi được
   còn tên file thì không. Chỉ ba template đăng nhập, không chạm template nào khác. */
add_filter( 'template_include', function( $tpl ) {
    $ten = basename( (string) $tpl );
    if ( in_array( $ten, array( 'page-login.php', 'page-register.php', 'page-forgot-password.php' ), true ) ) {
        sitetop_gui_header_bao_mat();
    }
    return $tpl;
}, 99 );

/* ── 5. ĐÓNG wp-links-opml.php ──────────────────────────────────────────────────────
   File lõi này liệt kê chuyên mục liên kết, không dùng tới nhưng vẫn trả 200. Đóng lại.
   Nhận diện theo SCRIPT_FILENAME nên không đụng nhầm đường nào khác. */
add_action( 'init', function() {
    if ( basename( (string) ( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) === 'wp-links-opml.php' ) {
        status_header( 403 );
        exit;
    }
}, 0 );

/* ── 6. XOÁ readme.html VÀ license.txt Ở GỐC WORDPRESS ─────────────────────────────
   Hai file này web server phục vụ THẲNG, không qua PHP, nên không chặn được bằng hook —
   phải xoá hẳn. Chúng chỉ là tài liệu, không có vai trò chạy gì; readme.html còn ghi rõ
   số phiên bản WordPress, tức chỉ luôn cho kẻ tấn công nên thử bộ khai thác nào.

   AN TOÀN: danh sách tên file cắm cứng, ghép thẳng vào ABSPATH, và còn kiểm lại basename
   trước khi xoá — không nhận tham số từ đâu cả nên không có đường nào lái sang file khác.
   Chỉ chạy trong wp-admin và tối đa MỘT LẦN MỖI NGÀY, nên không đụng gì tới đường phục vụ
   user. Chạy lại được: WordPress cập nhật lõi sẽ dựng lại hai file này, hôm sau tự dọn tiếp. */
add_action( 'admin_init', function() {
    if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) return;
    if ( get_transient( 'sitetop_da_don_file_lo' ) ) return;
    set_transient( 'sitetop_da_don_file_lo', 1, DAY_IN_SECONDS );

    foreach ( array( 'readme.html', 'license.txt' ) as $_ten ) {
        if ( basename( $_ten ) !== $_ten ) continue;          // chốt thừa, cho chắc
        $_duong = ABSPATH . $_ten;
        if ( is_file( $_duong ) && is_writable( $_duong ) ) {
            @unlink( $_duong );
        }
    }
}, 5 );
