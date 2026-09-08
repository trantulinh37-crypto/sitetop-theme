<?php
/* Captcha phải giải xong mới được cấp mã.
   Bộ này canh hai chiều, chiều nào sai cũng chết người:
   - KHÔNG được chặn khi captcha tắt / thiếu khoá / đã có cờ / là visit cầu nối
     (lệch một cờ là cắt mã của TOÀN BỘ user thật)
   - PHẢI chặn khi captcha đang bật đủ mà phiên không có cờ (dấu vết của công cụ) */
if ( ! function_exists( 'sitetop_get_option' ) ) {
    function sitetop_get_option( $k, $d = null ) { return array_key_exists($k, $GLOBALS['__opt']) ? $GLOBALS['__opt'][$k] : $d; }
}
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $k ) { return $GLOBALS['__tr'][$k] ?? false; }
}
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $k, $d = false ) { return array_key_exists($k, $GLOBALS['__wp']) ? $GLOBALS['__wp'][$k] : $d; }
}
$__ma = file_get_contents( dirname(__DIR__, 2) . '/includes/shortlink-ajax.php' );

/* Trích thân hàm THẬT từ mã nguồn (bỏ chú thích: tên hàm trong chú thích không tính). */
$__than = function ( $ten ) use ( $__ma ) {
    $vt = strpos( $__ma, 'function ' . $ten . '(' );
    if ( $vt === false ) return '';
    $tk = token_get_all( '<?php ' . substr( $__ma, $vt ) );
    $out = ''; $d = 0; $open = false;
    foreach ( $tk as $t ) {
        // Bỏ thẻ mở PHP (thêm vào chỉ để tokenize) và bỏ chú thích — tên hàm nằm trong
        // chú thích KHÔNG được tính là có đấu dây.
        $bo = is_array( $t ) && in_array( $t[0], array( T_OPEN_TAG, T_COMMENT, T_DOC_COMMENT ), true );
        if ( ! $bo ) $out .= is_array( $t ) ? $t[1] : $t;
        $mo = ( $t === '{' ) || ( is_array( $t ) && in_array( $t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) );
        if ( $mo ) { $d++; $open = true; }
        elseif ( $t === '}' ) { $d--; if ( $open && $d === 0 ) break; }
    }
    return $out;
};
if ( ! function_exists( 'sitetop_captcha_chua_giai' ) ) {
    $__fn = $__than( 'sitetop_captcha_chua_giai' );
    if ( $__fn === '' ) {
        $GLOBALS['test_results']['failed']++;
        $GLOBALS['test_results']['errors'][] = 'Khong trich duoc sitetop_captcha_chua_giai';
        return;
    }
    eval( $__fn );
}

/* Mặc định: captcha BẬT đủ khoá, phiên chưa có cờ nào -> đây là ca cần chặn. */
$dat = function ( $opt = array(), $tr = array() ) {
    $GLOBALS['__opt'] = array_merge( array(
        'widget_captcha_enabled' => 1,
        'turnstile_site_key'     => 'sk',
        'turnstile_secret_key'   => 'ss',
    ), $opt );
    $GLOBALS['__tr'] = $tr;
    // Cách đọc THÔ mà widget.js dùng — mặc định khớp với cách đọc lỏng ở trên.
    $GLOBALS['__wp'] = array_merge( array(
        'sitetop_widget_captcha_enabled' => '1',
        'sitetop_turnstile_site_key'     => 'sk',
    ), $GLOBALS['__wp_ghi_de'] ?? array() );
    $GLOBALS['__wp_ghi_de'] = array();
    return sitetop_captcha_chua_giai( 'phien123' );
};

// ---- Chiều 1: TUYỆT ĐỐI không được chặn oan ----
assert_equals( 0, $dat( array( 'widget_captcha_enabled' => 0 ) ),
    'Captcha tat toan site -> KHONG chan' );
assert_equals( 0, $dat( array( 'widget_captcha_enabled' => '' ) ),
    'Captcha tat (chuoi rong) -> KHONG chan' );
assert_equals( 0, $dat( array( 'turnstile_site_key' => '' ) ),
    'Thieu site_key -> KHONG chan' );
assert_equals( 0, $dat( array( 'turnstile_secret_key' => '' ) ),
    'Thieu secret_key -> KHONG chan' );
assert_equals( 0, $dat( array(), array( 'sitetop_captcha_ok_phien123' => 1 ) ),
    'Da giai captcha -> KHONG chan' );
assert_equals( 0, $dat( array(), array( 'lentop_widget_code_ready_phien123' => 1 ) ),
    'Visit cau noi lentop -> KHONG chan' );
assert_equals( 0, $dat( array(), array( 'trafficop_widget_code_ready_phien123' => 1 ) ),
    'Visit cau noi trafficop -> KHONG chan' );
// Cờ của phiên KHÁC không được tính là đã giải
assert_equals( 2, $dat( array(), array( 'sitetop_captcha_ok_phienKHAC' => 1 ) ),
    'Co cua phien khac -> VAN chan' );

/* Lệch pha giữa hai cách đọc: widget KHÔNG hiện captcha -> không ai có cờ.
   Bắt buộc phải rơi về KHÔNG chặn, nếu không là cắt mã toàn bộ user. */
$GLOBALS['__wp_ghi_de'] = array( 'sitetop_widget_captcha_enabled' => 'yes' );
assert_equals( 0, $dat(), 'Lech pha (DB luu "yes") -> KHONG chan' );
$GLOBALS['__wp_ghi_de'] = array( 'sitetop_widget_captcha_enabled' => 1 );
assert_equals( 0, $dat(), 'Lech pha (DB luu so 1) -> KHONG chan' );
$GLOBALS['__wp_ghi_de'] = array( 'sitetop_turnstile_site_key' => '' );
assert_equals( 0, $dat(), 'Widget khong co tsKey -> KHONG chan' );

// ---- Chiều 2: phải chặn đúng ca công cụ ----
assert_equals( 2, $dat(), 'Captcha bat du, phien khong co co -> CHAN (mac dinh 2)' );
assert_equals( 1, $dat( array( 'captcha_truoc_ma' => 1 ) ), 'Muc 1 -> chi quan sat' );
assert_equals( 0, $dat( array( 'captcha_truoc_ma' => 0 ) ), 'Muc 0 -> tat han' );

/* ---- Canh ĐẤU DÂY: cắm đúng chỗ, và tuyệt đối vắng ở cổng page-unlock gọi ---- */
$__canh = function ( $ten, $phai_co ) use ( $__than ) {
    $than = $__than( $ten );
    assert_true( $than !== '', 'Phai tim thay ham ' . $ten . ' de canh dau day' );
    if ( $than === '' ) return;
    $co = strpos( $than, 'sitetop_captcha_chua_giai' ) !== false;
    assert_equals( $phai_co, $co, ( $phai_co ? 'PHAI cam trong ' : 'KHONG duoc cam trong ' ) . $ten );
};
$__canh( 'sitetop_ajax_get_code', true );
// verify_shortlink_code: page-unlock gọi khi user gõ mã — cắm vào là chặn oan user thật
$__canh( 'sitetop_ajax_verify_shortlink_code', false );
$__canh( 'sitetop_ajax_change_keyword', false );
$__canh( 'sitetop_ajax_check_code_ready', false );
// widget_verify_access: captcha giải SAU khi cổng này chạy -> cắm vào là chặn oan
$__canh( 'sitetop_ajax_widget_verify_access', false );
