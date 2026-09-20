<?php
/* MÁY ĐO DẤU VẾT PHIÊN (20/09/2026) — chỉ ghi, không chặn ai.

   Canh ba điều:
   1. Nội dung ghi đủ thứ cần để nhận ra công cụ: Sec-Fetch-Site, có thiếu Sec-Fetch-Mode
      không, Origin, referer, tham số widget, tình trạng nhịp hiện diện.
   2. TẢI: đúng MỘT truy vấn mỗi lần ghi, và câu SQL phải mang điều kiện chỉ-ghi-lần-đầu.
      Thiếu điều kiện đó là cổng thăm dò (3 giây/lần) tự đánh sập máy chủ — sự cố 19/09.
   3. Đấu dây: các cổng then chốt đều gọi máy đo (đọc trên mã ĐÃ LỘT COMMENT, vì chú thích
      có nhắc nguyên văn tên hàm). */

$__dv_goc  = dirname( __DIR__, 2 );
$__dv_ajax = (string) file_get_contents( $__dv_goc . '/includes/shortlink-ajax.php' );
$__dv_func = (string) file_get_contents( $__dv_goc . '/includes/shortlink-functions.php' );

$__dv_than = function ( $ma, $ten ) {
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

if ( ! isset( $GLOBALS['__tr'] ) )  $GLOBALS['__tr']  = array();
if ( ! isset( $GLOBALS['__opt'] ) ) $GLOBALS['__opt'] = array();
if ( ! function_exists( 'get_transient' ) ) { function get_transient( $k ) { return $GLOBALS['__tr'][$k] ?? false; } }
if ( ! function_exists( 'sitetop_get_option' ) ) {
    function sitetop_get_option( $k, $d = null ) { return array_key_exists( $k, $GLOBALS['__opt'] ) ? $GLOBALS['__opt'][$k] : $d; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( (string) $v ); } }

class DV_Wpdb {
    public $prefix = 'wpgd_'; public $cau = array();
    public function prepare( $q, ...$a ) {
        if ( count( $a ) === 1 && is_array( $a[0] ) ) $a = $a[0];
        $i = 0;
        return preg_replace_callback( '/%[sdf]/', function ( $m ) use ( &$i, $a ) {
            $v = $a[ $i++ ] ?? null;
            return $m[0] === '%d' ? (string) (int) $v : "'" . addslashes( (string) $v ) . "'";
        }, $q );
    }
    public function query( $q ) { $this->cau[] = $q; return 1; }
    public function esc_like( $t ) { return addcslashes( (string) $t, '_%\\' ); }
}

if ( ! function_exists( 'sitetop_ghi_vet' ) ) {
    $__dv_ma = $__dv_than( $__dv_ajax, 'sitetop_ghi_vet' );
    if ( $__dv_ma === '' ) { assert_true( false, 'Khong trich duoc sitetop_ghi_vet' ); return; }
    eval( $__dv_ma );
}
if ( ! function_exists( 'sitetop_vet_nhip' ) ) {
    $__dv_ma2 = $__dv_than( $__dv_ajax, 'sitetop_vet_nhip' );
    if ( $__dv_ma2 === '' ) { assert_true( false, 'Khong trich duoc sitetop_vet_nhip' ); return; }
    eval( $__dv_ma2 );
}

/* ---- 1. Nội dung ghi ---- */
$__dv_chay = function ( $server, $post, $opt = array(), $them = '' ) {
    $GLOBALS['wpdb'] = new DV_Wpdb();
    $GLOBALS['__opt'] = $opt;
    $_SERVER = array_merge( array( 'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/140' ), $server );
    $_POST = $post;
    sitetop_ghi_vet( 'abc12345', 'xinma', $them );
    return $GLOBALS['wpdb']->cau;
};

// Widget THẬT trên web khách: gọi chéo tên miền, có đủ Sec-Fetch, kèm tham số khung.
$__dv_that = $__dv_chay( array(
    'HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_SEC_FETCH_MODE' => 'cors',
    'HTTP_ORIGIN' => 'https://tylenhacaii.in', 'HTTP_REFERER' => 'https://www.tylenhacaii.in/bai-viet',
), array( 'kf' => '1' ) );
assert_equals( 1, count( $__dv_that ), 'Moi lan ghi phai la DUNG MOT truy van' );
$__dv_q = $__dv_that[0] ?? '';
foreach ( array( 'xinma[', 'sfs=cross-site', 'o=tylenhacaii.in', 'r=tylenhacaii.in', 'kf=1' ) as $__dv_can ) {
    assert_true( strpos( $__dv_q, $__dv_can ) !== false, 'Dau vet phai ghi ' . $__dv_can );
}
assert_true( strpos( $__dv_q, 'sfm=THIEU' ) === false, 'Co Sec-Fetch-Mode thi KHONG duoc ghi sfm=THIEU' );
assert_true( strpos( $__dv_q, 'www.' ) === false, 'Phai bo tien to www de so sanh ten mien cho gon' );

// Công cụ: gọi từ chính trang nhiệm vụ, hoặc bằng GM_xmlhttpRequest (thiếu Sec-Fetch).
$__dv_cc = $__dv_chay( array( 'HTTP_ORIGIN' => 'https://sitetop.net', 'HTTP_REFERER' => 'https://sitetop.net/WQrcNQ/' ), array() );
$__dv_q2 = $__dv_cc[0] ?? '';
assert_true( strpos( $__dv_q2, 'sfs=THIEU' ) !== false, 'Thieu Sec-Fetch-Site phai ghi ro THIEU' );
assert_true( strpos( $__dv_q2, 'sfm=THIEU' ) !== false, 'Thieu Sec-Fetch-Mode phai ghi ro THIEU — day la dau GM_xmlhttpRequest' );
assert_true( strpos( $__dv_q2, 'o=sitetop.net' ) !== false && strpos( $__dv_q2, 'r=sitetop.net' ) !== false,
    'Phai ghi Origin/referer that, de lo viec goi tu chinh trang nhiem vu' );

/* ---- 2. Tải: chỉ ghi lần đầu, có trần độ dài, tắt được ---- */
assert_true( strpos( $__dv_q, 'NOT LIKE' ) !== false,
    'SONG CON: cau SQL phai co dieu kien chi-ghi-lan-dau (NOT LIKE), khong thi cong tham do 3 giay/lan se tu danh sap may chu' );
assert_true( strpos( $__dv_q, 'CHAR_LENGTH' ) !== false, 'Phai co tran do dai de mot phien la khong phinh cot' );
assert_true( strpos( $__dv_q, 'CONCAT' ) !== false, 'Phai noi chuoi ngay trong UPDATE, khong doc truoc roi ghi (2 truy van)' );
assert_equals( 0, count( $__dv_chay( array(), array(), array( 'do_vet' => 0 ) ) ),
    'Cong tac do_vet = 0 phai tat han may do' );
assert_equals( 0, count( (function () { $GLOBALS['wpdb'] = new DV_Wpdb(); $GLOBALS['__opt'] = array();
    sitetop_ghi_vet( 'x', 'xinma' ); return $GLOBALS['wpdb']->cau; })() ),
    'Session_id khong hop le -> khong ghi gi' );

/* ---- 3. Nhịp hiện diện ---- */
$GLOBALS['__tr'] = array();
assert_equals( 'nhip=KHONG', sitetop_vet_nhip( 'abc12345' ),
    'Phien chua tung co nhip -> phai ghi ro KHONG (day la dau cua cong cu)' );
$GLOBALS['__tr'] = array( 'sitetop_nhip1_abc12345' => time() - 70, 'sitetop_seen_abc12345' => time() - 3 );
assert_equals( 'nhip=70s/3s', sitetop_vet_nhip( 'abc12345' ),
    'Co nhip -> ghi tuoi nhip dau / nhip cuoi' );

/* ---- 4. Đấu dây: các cổng then chốt đều gọi máy đo ---- */
foreach ( array(
    'sitetop_ajax_widget_verify_access' => 'xacminh',
    'sitetop_ajax_widget_start_timer'   => 'batgio',
    'sitetop_ajax_widget_ping'          => 'nhip',
    'sitetop_ajax_check_code_ready'     => 'hoima',
    'sitetop_ajax_unlock_heartbeat'     => 'nhiptrang',
    'sitetop_ajax_get_code'             => 'xinma',
    'sitetop_ajax_verify'               => 'nopma',
) as $__dv_cong => $__dv_ten ) {
    $__dv_body = $__dv_than( $__dv_ajax, $__dv_cong );
    assert_true( $__dv_body !== '' && strpos( $__dv_body, "sitetop_ghi_vet(" ) !== false,
        'Cong ' . $__dv_cong . ' phai goi may do' );
    assert_true( strpos( $__dv_body, "'" . $__dv_ten . "'" ) !== false,
        'Cong ' . $__dv_cong . ' phai ghi dung ten moc "' . $__dv_ten . '"' );
}

// Hàm cấp mã: phải ghi CẢ hai đường (mã mới / trả lại mã cũ) kèm tình trạng nhịp.
$__dv_cap = $__dv_than( $__dv_func, 'sitetop_get_widget_code' );
assert_true( strpos( $__dv_cap, "'mamoi'" ) !== false, 'Phai ghi moc mamoi khi sinh ma moi' );
assert_true( strpos( $__dv_cap, "'macu'" ) !== false,
    'Phai ghi moc macu khi tra lai ma cu — duong nay bo qua MOI chot, phai biet cong cu co di khong' );
assert_true( substr_count( $__dv_cap, 'sitetop_vet_nhip(' ) >= 2,
    'Ca hai duong cap ma deu phai kem tinh trang nhip hien dien' );
assert_true( strpos( $__dv_cap, "'tuchoi_gio'" ) !== false && strpos( $__dv_cap, "'tuchoi_url'" ) !== false,
    'Phai ghi ca cac lan TU CHOI cap ma, khong thi khong biet chot nao da no' );

/* ---- 5. ĐIỂM MÙ ĐÃ VÁ (20/09/2026): cổng xacminh phải ghi SAU khi dò ra lượt ----
   Widget thật và công cụ bypass đều gọi verify_access lần đầu KHÔNG kèm session_id
   (máy chủ tự dò theo IP), nên dòng ghi ở đầu hàm luôn trượt — ba dấu vết công cụ thu
   được trong ngày đều thiếu đúng dòng của cổng quyết định. Phải ghi bằng session_id
   của lượt ĐÃ DÒ RA, và phải nằm SAU chốt no_visit (trước đó chưa có lượt để gắn). */
$__dv_va = $__dv_than( $__dv_ajax, 'sitetop_ajax_widget_verify_access' );
assert_true( $__dv_va !== '', 'Phai trich duoc sitetop_ajax_widget_verify_access' );
$__dv_p_novisit = strpos( $__dv_va, "'no_visit'" );
/* Nháy ĐƠN: trong chuỗi nháy kép, PHP nội suy $visit->session_id thành rỗng và phép
   canh này biến thành vô nghĩa — đã dính đúng bẫy đó khi viết test lần đầu. */
$__dv_p_ghi     = strpos( $__dv_va, 'sitetop_ghi_vet( $visit->session_id, \'xacminh\'' );
assert_true( $__dv_p_ghi !== false,
    'Cong xacminh PHAI ghi dau vet bang session_id cua luot da do ra (khong phai tu $_POST)' );
assert_true( $__dv_p_novisit !== false && $__dv_p_ghi > $__dv_p_novisit,
    'Dong ghi phai nam SAU chot no_visit — truoc do chua co luot nao de gan dau vet' );
assert_true( strpos( $__dv_va, "'xacminh_som'" ) !== false,
    'Dong ghi o dau ham phai doi ten moc (xacminh_som), khong thi no chiem cho dong quan trong' );
assert_equals( 3, substr_count( $__dv_va, "'xacminh_tuchoi'" ),
    'Phai ghi ca 3 kieu TU CHOI (no_handoff, handoff_expired, wrong_url) — de thay nguoi that hong o khau nao' );
assert_true( strpos( $__dv_va, "'capco'" ) !== false,
    'Phai ghi luc CAP CO url_matched/from_google — mat xich cuoi cua may do' );

// Sec-Fetch-Dest: trinh duyet that gui 'empty' cho XHR; nen ghi lai de doi chieu.
$__dv_dest = $__dv_chay( array( 'HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_SEC_FETCH_MODE' => 'cors',
    'HTTP_SEC_FETCH_DEST' => 'empty', 'HTTP_ORIGIN' => 'https://a.in' ), array() );
assert_true( strpos( $__dv_dest[0] ?? '', 'd=empty' ) !== false, 'Dau vet phai ghi Sec-Fetch-Dest' );
$__dv_dest2 = $__dv_chay( array( 'HTTP_SEC_FETCH_SITE' => 'none' ), array() );
assert_true( strpos( $__dv_dest2[0] ?? '', 'd=THIEU' ) !== false, 'Thieu Sec-Fetch-Dest cung phai ghi ro' );

/* ---- 6. Cột lưu + chỗ xem trong admin ---- */
$__dv_db = (string) file_get_contents( $__dv_goc . '/includes/database-setup.php' );
assert_true( strpos( $__dv_db, 'dau_vet text' ) !== false, 'Bang shortlink_visits phai co cot dau_vet' );
$__dv_ad = (string) file_get_contents( $__dv_goc . '/includes/admin/tabs/tab-visits.php' );
assert_true( strpos( $__dv_ad, 'dau_vet' ) !== false, 'Tab Luot truy cap phai hien cot Dau vet' );
