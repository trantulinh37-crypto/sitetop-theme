<?php
/* NHỊP HIỆN DIỆN CỦA WIDGET — chốt chặn công cụ "SiteTop Bypass" (20/09/2026).

   Công cụ đếm giờ ngay trên trang nhiệm vụ, không mở web khách, nên phiên của nó KHÔNG
   có nhịp sitetop_widget_ping nào. Chốt: không có nhịp đủ trải thì không cấp mã.

   Bộ test này canh ba thứ:
   1. Hàm quyết định (sitetop_nhip_muc) — chạy hàm THẬT, giả lập transient + option.
   2. Cổng ping ghi mốc nhịp đầu ĐÚNG MỘT LẦN (ghi đè mỗi 10 giây là thêm tải, đúng thứ
      đã gây 503 hôm 19/09).
   3. Đấu dây: chốt nằm trong sitetop_get_widget_code, sau các chốt cũ, trước lúc sinh mã —
      và TUYỆT ĐỐI không nằm ở các cổng mà TRANG NHIỆM VỤ gọi (check_code_ready,
      unlock_heartbeat, verify_shortlink_code, task_handoff, change_keyword): cắm vào đó
      là chặn oan toàn bộ user thật. Mọi phép canh đấu dây chạy trên bản ĐÃ LỘT COMMENT,
      vì chú thích trong mã có nhắc nguyên văn tên hàm. */

$__nh_goc = dirname( __DIR__, 2 );
$__nh_ajax = (string) file_get_contents( $__nh_goc . '/includes/shortlink-ajax.php' );
$__nh_func = (string) file_get_contents( $__nh_goc . '/includes/shortlink-functions.php' );

/* Trích thân hàm, BỎ chú thích (tên hàm trong chú thích không tính là đấu dây). */
$__nh_than = function ( $ma, $ten ) {
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

/* ---- Giả lập tối thiểu (dùng chung quy ước với các bộ test khác: __tr, __opt) ---- */
if ( ! defined( 'HOUR_IN_SECONDS' ) )   define( 'HOUR_IN_SECONDS', 3600 );
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) define( 'MINUTE_IN_SECONDS', 60 );
if ( ! isset( $GLOBALS['__tr'] ) )  $GLOBALS['__tr']  = array();
if ( ! isset( $GLOBALS['__opt'] ) ) $GLOBALS['__opt'] = array();
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $k ) { return $GLOBALS['__tr'][$k] ?? false; }
}
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['__tr'][$k] = $v; $GLOBALS['__ttl'][$k] = $ttl; return true; }
}
if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $k ) { unset( $GLOBALS['__tr'][$k] ); return true; }
}
if ( ! function_exists( 'sitetop_get_option' ) ) {
    function sitetop_get_option( $k, $d = null ) { return array_key_exists( $k, $GLOBALS['__opt'] ) ? $GLOBALS['__opt'][$k] : $d; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $v ) { return trim( (string) $v ); }
}
if ( ! function_exists( 'sitetop_is_scripted_client' ) ) {
    function sitetop_is_scripted_client() { return false; }
}
class NH_Dung extends Exception {}   // thay cho wp_send_json_* (vốn kết thúc request)
if ( ! function_exists( 'wp_send_json_success' ) ) {
    function wp_send_json_success( $d = null ) { throw new NH_Dung( 'ok' ); }
}
if ( ! function_exists( 'wp_send_json_error' ) ) {
    function wp_send_json_error( $d = null ) { throw new NH_Dung( 'loi' ); }
}

foreach ( array( 'sitetop_nhip_can', 'sitetop_nhip_muc', 'sitetop_ajax_widget_ping' ) as $__nh_ten ) {
    if ( function_exists( $__nh_ten ) ) continue;
    $__nh_ma = $__nh_than( $__nh_ajax, $__nh_ten );
    if ( $__nh_ma === '' ) { assert_true( false, 'Khong trich duoc ' . $__nh_ten ); return; }
    eval( $__nh_ma );
}

// ---- 1. Ngưỡng trải nhịp theo thời lượng camp ----
assert_equals( 26, sitetop_nhip_can( 80 ),  'Camp 80s: nguong trai nhip = 1/3 = 26 giay' );
assert_equals( 10, sitetop_nhip_can( 30 ),  'Camp 30s: kep san 10 giay' );
assert_equals( 30, sitetop_nhip_can( 200 ), 'Camp rat dai: kep tran 30 giay' );

/* ---- 2. Quyết định chặn / cho qua ----
   $nhip_truoc = nhịp đầu tiên cách ĐÂY bao nhiêu giây (null = chưa có nhịp nào). */
$__nh_chay = function ( $nhip_truoc, $onsite = 80, $opt = array(), $tr_them = array() ) {
    $GLOBALS['__tr']  = $tr_them;
    $GLOBALS['__opt'] = $opt;
    if ( $nhip_truoc !== null ) $GLOBALS['__tr']['sitetop_nhip1_abc12345'] = time() - (int) $nhip_truoc;
    return sitetop_nhip_muc( 'abc12345', $onsite );
};

assert_equals( 2, $__nh_chay( null ),
    'CONG CU: khong co nhip nao, camp 80s -> chan cap ma (muc mac dinh 2)' );
assert_equals( 0, $__nh_chay( 75 ),
    'NGUOI THAT: nhip dau tu 75 giay truoc -> cho qua' );
assert_equals( 0, $__nh_chay( 26 ),
    'Dung bang nguong 26 giay -> cho qua' );
assert_equals( 2, $__nh_chay( 20 ),
    'Moi co nhip 20 giay (chua du trai) tren camp 80s -> chan' );
assert_equals( 0, $__nh_chay( null, 20 ),
    'Camp NGAN duoi 25 giay -> bo qua chot, khong the oan' );
assert_equals( 0, $__nh_chay( null, 80, array(), array( 'lentop_widget_code_ready_abc12345' => 1 ) ),
    'Visit CAU NOI (widget site nguon) -> bo qua chot' );
assert_equals( 0, $__nh_chay( null, 80, array(), array( 'trafficop_widget_code_ready_abc12345' => 1 ) ),
    'Visit cau noi tien to trafficop -> bo qua chot' );
assert_equals( 0, $__nh_chay( null, 80, array( 'nhip_widget_muc' => 0 ) ),
    'Cong tac 0 -> tat han, khong chan ai' );
assert_equals( 1, $__nh_chay( null, 80, array( 'nhip_widget_muc' => 1 ) ),
    'Cong tac 1 -> chi quan sat (canh bao), khong chan' );
assert_equals( 0, sitetop_nhip_muc( '', 80 ), 'Thieu session_id -> khong ket luan' );
assert_equals( 0, $__nh_chay( null, 80, array(), array( 'sitetop_seen_abc12345' => time() ) ),
    'Phien dang chay do luc deploy (co dau da thay, chua co moc dau) -> cho qua, khong chan oan' );
assert_equals( 2, $__nh_chay( 20, 80, array(), array( 'sitetop_seen_abc12345' => time() ) ),
    'DA co moc dau thi dau "da thay" KHONG duoc cuu: van phai du do trai' );

/* ---- 3. Cổng ping: ghi mốc nhịp đầu ĐÚNG MỘT LẦN ---- */
$GLOBALS['__tr'] = array();
$_POST = array( 'session_id' => 'abc12345' );
try { sitetop_ajax_widget_ping(); } catch ( NH_Dung $e ) {}
$__nh_dau = $GLOBALS['__tr']['sitetop_nhip1_abc12345'] ?? null;
assert_true( is_int( $__nh_dau ) && abs( time() - $__nh_dau ) <= 5,
    'Ping dau tien phai ghi moc sitetop_nhip1_ bang thoi diem hien tai' );

$GLOBALS['__tr']['sitetop_nhip1_abc12345'] = $__nh_dau - 60;   // giả vờ nhịp đầu 60 giây trước
try { sitetop_ajax_widget_ping(); } catch ( NH_Dung $e ) {}
assert_equals( $__nh_dau - 60, $GLOBALS['__tr']['sitetop_nhip1_abc12345'],
    'Ping sau KHONG duoc ghi de moc nhip dau (ghi de = mat bang chung + them tai moi 10 giay)' );

/* ---- 4. Đấu dây: chốt nằm đúng chỗ trong sitetop_get_widget_code ---- */
$__nh_cap = $__nh_than( $__nh_func, 'sitetop_get_widget_code' );
assert_true( $__nh_cap !== '', 'Phai trich duoc sitetop_get_widget_code' );
$__nh_p_nocode = strpos( $__nh_cap, '$is_nocode' );
$__nh_p_chot   = strpos( $__nh_cap, 'sitetop_nhip_muc(' );
$__nh_p_sinh   = strpos( $__nh_cap, 'sitetop_generate_visit_verify_code' );
assert_true( $__nh_p_chot !== false, 'CHOT PHAI duoc cam vao sitetop_get_widget_code' );
assert_true( $__nh_p_sinh !== false, 'Phai tim duoc cho sinh ma' );
assert_true( $__nh_p_chot !== false && $__nh_p_chot < $__nh_p_sinh,
    'Chot phai dung TRUOC luc sinh ma' );
assert_true( $__nh_p_nocode !== false && $__nh_p_nocode < $__nh_p_chot,
    'Chot phai nam trong nhanh ! $is_nocode (nocode khong co dong ho, khong co nhip)' );
assert_true( strpos( $__nh_cap, 'sitetop_canh_bao_thieu_nhip(' ) !== false,
    'Muc 1 phai bao Telegram, khong thi chay quan sat ma khong biet ai dinh' );

/* ---- 5. RANH GIỚI: tuyệt đối không cắm vào cổng mà TRANG NHIỆM VỤ gọi ----
   Trang nhiệm vụ gọi những cổng này same-origin HỢP LỆ và KHÔNG hề có nhịp widget.
   Cắm chốt vào đó là chặn sạch user thật — đúng cái bẫy đã ghi trong test-sai-nguon. */
foreach ( array(
    'sitetop_ajax_check_code_ready', 'sitetop_ajax_unlock_heartbeat', 'sitetop_ajax_verify_shortlink_code',
    'sitetop_ajax_task_handoff', 'sitetop_ajax_change_keyword', 'sitetop_ajax_verify',
) as $__nh_cong ) {
    $__nh_body = $__nh_than( $__nh_ajax, $__nh_cong );
    if ( $__nh_body === '' ) continue;   // cổng không tồn tại thì không có gì để canh
    assert_true( strpos( $__nh_body, 'sitetop_nhip_muc' ) === false,
        'TUYET DOI khong duoc cam chot nhip vao ' . $__nh_cong . ' — trang nhiem vu goi cong nay, chan la oan user that' );
}

/* ---- 6. Công tắc phải có trong giao diện admin: tắt khẩn cấp không cần deploy ---- */
$__nh_setting = (string) file_get_contents( $__nh_goc . '/includes/admin/tabs/tab-settings.php' );
/* Phải soi ĐÚNG danh sách $fields được lưu, không phải cả file: chuỗi 'nhip_widget_muc'
   còn xuất hiện trong ô chọn nữa, nên canh trên cả file là xanh giả — gỡ khỏi danh sách
   lưu thì ô chọn vẫn hiện mà bấm Lưu không ăn, tắt khẩn cấp coi như hỏng. */
$__nh_sma = '';
foreach ( token_get_all( $__nh_setting ) as $t ) {
    if ( is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) continue;
    $__nh_sma .= is_array( $t ) ? $t[1] : $t;
}
$__nh_p1 = strpos( $__nh_sma, '$fields = array(' );
$__nh_p2 = $__nh_p1 !== false ? strpos( $__nh_sma, ');', $__nh_p1 ) : false;
assert_true( $__nh_p1 !== false && $__nh_p2 !== false, 'Phai tim duoc danh sach $fields cua tab Cai dat' );
$__nh_ds = ( $__nh_p1 !== false && $__nh_p2 !== false ) ? substr( $__nh_sma, $__nh_p1, $__nh_p2 - $__nh_p1 ) : '';
assert_true( strpos( $__nh_ds, "'nhip_widget_muc'" ) !== false,
    'nhip_widget_muc phai nam trong DANH SACH $fields duoc luu cua tab Cai dat' );
assert_true( strpos( $__nh_setting, 'name="nhip_widget_muc"' ) !== false,
    'Phai co o chon nhip_widget_muc trong giao dien (tat khan cap khong can deploy)' );
