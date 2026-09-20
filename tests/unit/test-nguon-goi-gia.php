<?php
/* NGUỒN GỌI GIẢ — chặn công cụ bypass bằng Sec-Fetch-Site (20/09/2026).

   Dấu vết ĐO ĐƯỢC trên production lúc 13:05: mọi request của công cụ mang
   `sfs=none` kèm `o=kizi1.in r=kizi1.in kf=1 vis=visible nhip=86s/6s` — nó giả được
   Origin, referer, cờ khung, trạng thái tab và cả nhịp hiện diện, nhưng Sec-Fetch-Site
   thì không. Widget thật trên web khách gọi XHR chéo tên miền nên trình duyệt luôn gắn
   `cross-site`.

   Canh ba điều:
   1. Bảng quyết định: none/same-origin -> chặn; cross-site/same-site -> cho qua;
      THIẾU header -> im lặng cho qua (trình duyệt cũ, proxy cắt header — không được oan).
   2. Chốt nằm ở ĐÚNG ba cổng chỉ widget thật gọi.
   3. RANH GIỚI: tuyệt đối không nằm ở các cổng mà TRANG NHIỆM VỤ gọi same-origin hợp lệ
      (check_code_ready, unlock_heartbeat, task_handoff, verify_shortlink_code,
      change_keyword, verify) — cắm vào đó là chặn sạch user thật. */

$__ng_goc  = dirname( __DIR__, 2 );
$__ng_ajax = (string) file_get_contents( $__ng_goc . '/includes/shortlink-ajax.php' );

$__ng_than = function ( $ma, $ten ) {
    $vt = strpos( $ma, 'function ' . $ten . '(' );
    if ( $vt === false ) return '';
    $tk = token_get_all( '<?php ' . substr( $ma, $vt ) );
    $out = ''; $d = 0; $open = false;
    foreach ( $tk as $t ) {
        $bo = is_array( $t ) && in_array( $t[0], array( T_OPEN_TAG, T_COMMENT, T_DOC_COMMENT ), true );
        if ( ! $bo ) $out .= is_array( $t ) ? $t[1] : $t;
        $mo = ( $t === '{' ) || ( is_array( $t ) && in_array( $t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) );
        if ( $mo ) { $d++; $open = true; }
        elseif ( $t === '}' ) { $d--; if ( $open && $d === 0 ) break; }
    }
    return $out;
};

if ( ! isset( $GLOBALS['__opt'] ) ) $GLOBALS['__opt'] = array();
if ( ! function_exists( 'sitetop_get_option' ) ) {
    function sitetop_get_option( $k, $d = null ) { return array_key_exists( $k, $GLOBALS['__opt'] ) ? $GLOBALS['__opt'][$k] : $d; }
}
if ( ! function_exists( 'sitetop_nguon_gia_muc' ) ) {
    $__ng_ma = $__ng_than( $__ng_ajax, 'sitetop_nguon_gia_muc' );
    if ( $__ng_ma === '' ) { assert_true( false, 'Khong trich duoc sitetop_nguon_gia_muc' ); return; }
    eval( $__ng_ma );
}

$__ng_chay = function ( $sfs, $opt = array() ) {
    $GLOBALS['__opt'] = $opt;
    unset( $_SERVER['HTTP_SEC_FETCH_SITE'] );
    if ( $sfs !== null ) $_SERVER['HTTP_SEC_FETCH_SITE'] = $sfs;
    return sitetop_nguon_gia_muc();
};

// ---- 1. Bảng quyết định ----
assert_equals( 2, $__ng_chay( 'none' ),
    'CONG CU (do duoc 13:05): sfs=none -> chan, mac dinh muc 2' );
assert_equals( 2, $__ng_chay( 'same-origin' ),
    'Goi tu chinh trang nhiem vu (same-origin) o cong widget -> chan' );
assert_equals( 0, $__ng_chay( 'cross-site' ),
    'WIDGET THAT tren web khach luon la cross-site -> phai cho qua' );
assert_equals( 0, $__ng_chay( 'same-site' ),
    'same-site (ten mien con) -> co y khong dung toi' );
assert_equals( 0, $__ng_chay( null ),
    'THIEU header (trinh duyet cu, proxy cat) -> im lang cho qua, khong duoc ket luan' );
assert_equals( 0, $__ng_chay( 'none', array( 'nguon_gia_muc' => 0 ) ), 'Cong tac 0 -> tat han' );
assert_equals( 1, $__ng_chay( 'none', array( 'nguon_gia_muc' => 1 ) ), 'Cong tac 1 -> chi quan sat' );

/* Chữ hoa: header HTTP không phân biệt hoa thường về giá trị? Trình duyệt luôn gửi chữ
   thường, nhưng hàm phải tự hạ chữ để kẻ giả không lách bằng "None". */
$__ng_hoa = $__ng_chay( 'NONE' );
assert_equals( 2, $__ng_hoa, 'Gia bang "NONE" viet hoa van phai bi chan (ham tu ha chu)' );

// ---- 2. Chốt nằm ở đúng ba cổng widget-only ----
foreach ( array( 'sitetop_ajax_widget_verify_access', 'sitetop_ajax_widget_start_timer', 'sitetop_ajax_get_code' ) as $__ng_cong ) {
    $__ng_body = $__ng_than( $__ng_ajax, $__ng_cong );
    assert_true( $__ng_body !== '' && strpos( $__ng_body, 'sitetop_nguon_gia_muc()' ) !== false,
        'Cong widget-only ' . $__ng_cong . ' PHAI co chot nguon goi gia' );
    assert_true( strpos( $__ng_body, 'sitetop_canh_bao_nguon_gia(' ) !== false,
        'Muc 1 phai bao Telegram + ghi dau vet o ' . $__ng_cong );
}

/* ---- 3. RANH GIỚI — trang nhiệm vụ gọi những cổng này same-origin HỢP LỆ ---- */
foreach ( array(
    'sitetop_ajax_check_code_ready', 'sitetop_ajax_unlock_heartbeat', 'sitetop_ajax_task_handoff',
    'sitetop_ajax_verify_shortlink_code', 'sitetop_ajax_change_keyword', 'sitetop_ajax_verify',
    'sitetop_ajax_track_google_click', 'sitetop_ajax_report_behavior',
) as $__ng_cam ) {
    $__ng_body = $__ng_than( $__ng_ajax, $__ng_cam );
    if ( $__ng_body === '' ) continue;
    assert_true( strpos( $__ng_body, 'sitetop_nguon_gia_muc' ) === false,
        'TUYET DOI khong duoc cam chot nguon gia vao ' . $__ng_cam . ' — trang nhiem vu goi cong nay same-origin hop le, chan la oan sach user that' );
}

// ---- 4. Công tắc phải có trong giao diện admin (tắt khẩn cấp không cần deploy) ----
$__ng_set = (string) file_get_contents( $__ng_goc . '/includes/admin/tabs/tab-settings.php' );
$__ng_sma = '';
foreach ( token_get_all( $__ng_set ) as $t ) {
    if ( is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) continue;
    $__ng_sma .= is_array( $t ) ? $t[1] : $t;
}
$__ng_p1 = strpos( $__ng_sma, '$fields = array(' );
$__ng_p2 = $__ng_p1 !== false ? strpos( $__ng_sma, ');', $__ng_p1 ) : false;
$__ng_ds = ( $__ng_p1 !== false && $__ng_p2 !== false ) ? substr( $__ng_sma, $__ng_p1, $__ng_p2 - $__ng_p1 ) : '';
assert_true( strpos( $__ng_ds, "'nguon_gia_muc'" ) !== false,
    'nguon_gia_muc phai nam trong DANH SACH $fields duoc luu (khong thi bam Luu khong an)' );
assert_true( strpos( $__ng_set, 'name="nguon_gia_muc"' ) !== false,
    'Phai co o chon nguon_gia_muc trong giao dien' );
