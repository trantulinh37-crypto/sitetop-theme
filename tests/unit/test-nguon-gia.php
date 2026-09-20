<?php
/* NGUỒN GỌI GIẢ — chặn công cụ bypass, KHÔNG được chạm luồng người thật (20/09/2026).

   Dựng trên số đo HAI PHÍA trên chính production, cùng một khuôn dấu vết:
     lượt THẬT   (8983440F): xacminh/capco/batgio/nhip/xinma/mamoi — TẤT CẢ cross-site.
     lượt CÔNG CỤ (27C11DE5): TẤT CẢ none; và nó đổi tên miền khai báo 2 lần trong 1 giây.
   Mọi trường khác (d=empty, kf=1, vis=visible, sid_gui=khong, có nhịp) giống hệt nhau.

   Bộ test canh bốn điều:
   1. Bảng chân trị của sitetop_nguon_gia_loai() — đặc biệt hai ca KHÔNG ĐƯỢC kết luận:
      thiếu header, và cross-site.
   2. Chỉ bộ ba none+cors+empty mới bị CHẶN; các ca ngờ khác chỉ cắt tiền.
   3. Cắm đúng ba cổng widget-only; TUYỆT ĐỐI không cắm vào cổng trang nhiệm vụ gọi.
   4. Khâu trả thưởng cắt tiền CẢ HAI phía, và công tắc phải có trong giao diện. */

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

if ( ! function_exists( 'sitetop_nguon_gia_loai' ) ) {
    $__ng_ma = $__ng_than( $__ng_ajax, 'sitetop_nguon_gia_loai' );
    if ( $__ng_ma === '' ) { assert_true( false, 'Khong trich duoc sitetop_nguon_gia_loai' ); return; }
    eval( $__ng_ma );
}

$__ng_do = function ( $site, $mode = null, $dest = null ) {
    foreach ( array( 'HTTP_SEC_FETCH_SITE', 'HTTP_SEC_FETCH_MODE', 'HTTP_SEC_FETCH_DEST' ) as $k ) unset( $_SERVER[ $k ] );
    if ( $site !== null ) $_SERVER['HTTP_SEC_FETCH_SITE'] = $site;
    if ( $mode !== null ) $_SERVER['HTTP_SEC_FETCH_MODE'] = $mode;
    if ( $dest !== null ) $_SERVER['HTTP_SEC_FETCH_DEST'] = $dest;
    return sitetop_nguon_gia_loai();
};

/* ---- 1. NGƯỜI THẬT — đo được trên production, tuyệt đối không được kết luận gì ---- */
assert_equals( '', $__ng_do( 'cross-site', 'cors', 'empty' ),
    'LUOT THAT (8983440F): widget tren web khach gui cross-site+cors+empty -> KHONG duoc dung toi' );
assert_equals( '', $__ng_do( 'same-site', 'cors', 'empty' ),
    'same-site (ten mien con) -> co y khong dung toi' );
assert_equals( '', $__ng_do( null ),
    'THIEU header (trinh duyet doi cu, proxy cat) -> im lang cho qua, khong bao gio vi thieu du lieu ma chan' );
assert_equals( '', $__ng_do( null, 'cors', 'empty' ),
    'Thieu rieng Sec-Fetch-Site -> van khong ket luan' );

/* ---- 2. CÔNG CỤ — bộ ba "không có nơi khởi phát" ---- */
assert_equals( 'chac', $__ng_do( 'none', 'cors', 'empty' ),
    'LUOT CONG CU (27C11DE5): none+cors+empty = XHR tu khai khong co noi khoi phat -> CHAN' );
assert_equals( 'chac', $__ng_do( 'NONE', 'CORS', 'EMPTY' ),
    'Viet hoa khong duoc lam lot luat (ham tu ha chu)' );

/* ---- 3. NGỜ nhưng CHƯA chắc — chỉ cắt tiền, KHÔNG chặn ----
   Đây là chỗ khác hẳn bản hôm qua đã phải gỡ: hôm qua chặn thẳng mọi 'none'. */
assert_equals( 'ngo', $__ng_do( 'none', 'navigate', 'document' ),
    'none + navigate + document = cu dieu huong nguoi dung tu mo -> chi cat tien, KHONG chan' );
assert_equals( 'ngo', $__ng_do( 'none' ),
    'none ma thieu Mode/Dest -> chua du chac chan de chan' );
assert_equals( 'ngo', $__ng_do( 'same-origin', 'cors', 'empty' ),
    'same-origin o cong widget = goi tu chinh trang nhiem vu -> cat tien, khong chan' );

/* ---- 4. Đấu dây: đúng ba cổng widget-only ---- */
foreach ( array( 'sitetop_ajax_widget_verify_access', 'sitetop_ajax_widget_start_timer', 'sitetop_ajax_get_code' ) as $__ng_cong ) {
    $__ng_body = $__ng_than( $__ng_ajax, $__ng_cong );
    assert_true( $__ng_body !== '' && strpos( $__ng_body, 'sitetop_nguon_gia_xu_ly(' ) !== false,
        'Cong widget-only ' . $__ng_cong . ' PHAI goi chot nguon gia' );
    assert_true( strpos( $__ng_body, "=== 'chan'" ) !== false,
        'Cong ' . $__ng_cong . ' chi duoc chan khi chot tra ve chan' );
}

/* ---- 5. RANH GIỚI: trang nhiệm vụ gọi các cổng này same-origin HỢP LỆ ----
   Cắm chốt vào đây là chặn sạch user thật — chính cái bẫy đã ghi trong sổ 08/09. */
foreach ( array(
    'sitetop_ajax_check_code_ready', 'sitetop_ajax_unlock_heartbeat', 'sitetop_ajax_task_handoff',
    'sitetop_ajax_verify_shortlink_code', 'sitetop_ajax_change_keyword', 'sitetop_ajax_verify',
    'sitetop_ajax_track_google_click', 'sitetop_ajax_report_behavior', 'sitetop_ajax_widget_ping',
) as $__ng_cam ) {
    $__ng_body = $__ng_than( $__ng_ajax, $__ng_cam );
    if ( $__ng_body === '' ) continue;
    assert_true( strpos( $__ng_body, 'sitetop_nguon_gia_xu_ly' ) === false,
        'TUYET DOI khong cam chot vao ' . $__ng_cam . ' — trang nhiem vu goi cong nay same-origin hop le' );
}

/* ---- 6. Khâu trả thưởng: cắt tiền CẢ HAI phía, và chỉ khi mức >= 2 ---- */
$__ng_ver = (string) file_get_contents( $__ng_goc . '/includes/shortlink-verification.php' );
$__ng_vma = '';
foreach ( token_get_all( $__ng_ver ) as $t ) {
    if ( is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) continue;
    $__ng_vma .= is_array( $t ) ? $t[1] : $t;
}
$__ng_p = strpos( $__ng_vma, "get_transient( 'sitetop_nguongia_'" );
assert_true( $__ng_p !== false, 'Khau tra thuong PHAI doc dau phien nguon gia' );
$__ng_khoi = substr( $__ng_vma, $__ng_p, 420 );
assert_true( strpos( $__ng_khoi, "\$skip_reasons[] = 'nguon_gia';" ) !== false,
    'Phai ghi nhan "Nguon gia" de admin nhin thay, ke ca khi chi gan nhan' );
assert_true( strpos( $__ng_khoi, '$should_pay_reward   = false;' ) !== false,
    'Muc 2: user khong duoc nhan thuong' );
assert_true( strpos( $__ng_khoi, '$should_pay_customer = false;' ) !== false,
    'Muc 2: KHACH HANG KHONG BI TRU — luot nay khong co ai ghe web khach that' );
assert_true( strpos( $__ng_khoi, "sitetop_get_option( 'nguon_gia_muc', 2 ) >= 2" ) !== false,
    'Cat tien phai nam sau cong tac muc >= 2, de ha ve 1 la chi con gan nhan' );

/* ---- 7. Công tắc + bộ lọc trong admin ---- */
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
assert_true( strpos( $__ng_set, 'name="nguon_gia_muc"' ) !== false, 'Phai co o chon trong giao dien' );

$__ng_tab = (string) file_get_contents( $__ng_goc . '/includes/admin/tabs/tab-visits.php' );
assert_true( strpos( $__ng_tab, "'nguon_gia'                =>" ) !== false,
    'Tab Luot truy cap phai co nhan "Nguon gia"' );
assert_true( preg_match( "#reason_filter === 'nguon_gia'.{0,200}dau_vet LIKE#s", $__ng_tab ) === 1,
    'Bo loc phai soi cot dau_vet — o muc chan, phien khong toi khau tra thuong nen skip_reasons rong' );
