<?php
/* DỌN MỐC _ttplb_wstart_ TỰ ĐỘNG (24/09/2026) — chạy trong cron 5 phút.

   Plugin cầu nối ghi mốc onsite thẳng vào wp_options và chỉ xoá khi cấp được mã, nên lượt bỏ
   dở để lại mốc vĩnh viễn: 25.912 dòng (72% số dòng wp_options), mốc mới nhất 04/08.

   Bốn điều canh, chạy HÀM THẬT với $wpdb giả đóng vai bảng wp_options:
   1. Mẫu LIKE phải được THOÁT (\_) — để nguyên thì dấu _ là ký tự đại diện, MySQL quét toàn bảng.
   2. TUYỆT ĐỐI không đụng các option ttplb_* không có gạch dưới đầu (ttplb_widget_style,
      ttplb_secret, ttplb_map…) — page-unlock.php đang đọc chúng.
   3. Chỉ xoá mốc CŨ HƠN MỘT NGÀY; mốc của lượt đang chạy phải còn nguyên.
   4. Chia lô có trần: 10 lô x 500 = 5.000 mỗi lượt cron, tồn đọng lớn rút dần chứ không dồn tải. */

$__tm_goc = dirname( __DIR__, 2 );
$__tm_ma  = (string) file_get_contents( $__tm_goc . '/includes/cron-cleanup.php' );

$__tm_than = function ( $ma, $ten ) {
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

/* wp_options giả: tên => giá trị. get_col() diễn lại đúng ngữ nghĩa
   "LIKE <mẫu đã thoát> AND CAST(option_value AS UNSIGNED) < N LIMIT 500". */
class TM_Wpdb {
    public $options = 'wp_options'; public $bang = array(); public $cau = array(); public $_a = array();
    public function esc_like( $t ) { return addcslashes( (string) $t, '_%\\' ); }
    public function prepare( $q, ...$a ) {
        if ( count( $a ) === 1 && is_array( $a[0] ) ) $a = $a[0];
        $this->_a = $a; return $q;
    }
    public function get_col( $q ) {
        $this->cau[] = array( $q, $this->_a );
        list( $mau, $truoc ) = $this->_a;
        $re = '#^' . str_replace( array( '\\\\_', '%' ), array( '_', '.*' ), preg_quote( $mau, '#' ) ) . '$#';
        $re = str_replace( '\\\\_', '_', $re );
        $kq = array();
        foreach ( $this->bang as $ten => $gia_tri ) {
            if ( preg_match( $re, $ten ) && (int) $gia_tri < (int) $truoc ) $kq[] = $ten;
            if ( count( $kq ) >= 500 ) break;
        }
        return $kq;
    }
    public function query( $q ) {
        $this->cau[] = array( $q, $this->_a );
        if ( stripos( trim( $q ), 'DELETE' ) === 0 ) foreach ( $this->_a as $ten ) unset( $this->bang[ $ten ] );
        return 1;
    }
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) define( 'DAY_IN_SECONDS', 86400 );
if ( ! function_exists( 'sitetop_don_moc_ttplb' ) ) {
    $__tm_f = $__tm_than( $__tm_ma, 'sitetop_don_moc_ttplb' );
    if ( $__tm_f === '' ) { assert_true( false, 'Khong trich duoc sitetop_don_moc_ttplb' ); return; }
    eval( $__tm_f );
}

$__tm_now = time();
$GLOBALS['wpdb'] = new TM_Wpdb();
$GLOBALS['wpdb']->bang = array(
    // mốc CŨ (quá 1 ngày) -> phải xoá
    '_ttplb_wstart_aaaa1111' => $__tm_now - 90000,
    '_ttplb_wstart_bbbb2222' => 1782553628,          // mốc thật kiểu 27/06/2026
    // mốc MỚI (lượt đang chạy) -> phải giữ
    '_ttplb_wstart_cccc3333' => $__tm_now - 300,
    // option ttplb_ ĐANG DÙNG (không có gạch dưới đầu) -> tuyệt đối không đụng
    'ttplb_widget_style'     => 'a:1:{}',
    'ttplb_secret'           => 'khoa-bi-mat',
    'ttplb_map'              => 'a:0:{}',
    // hàng xóm khác -> giữ
    '_transient_sitetop_x'   => '1',
    'Xttplb_wstart_gia'      => '1',                 // tên na ná (bẫy dấu _ đại diện)
    'siteurl'                => 'https://sitetop.net',
);
sitetop_don_moc_ttplb();
$__tm_b = $GLOBALS['wpdb']->bang;

// ---- 3. Xoá đúng mốc cũ, giữ mốc đang chạy ----
assert_true( ! isset( $__tm_b['_ttplb_wstart_aaaa1111'] ), 'Moc qua 1 ngay phai bi xoa' );
assert_true( ! isset( $__tm_b['_ttplb_wstart_bbbb2222'] ), 'Moc tu 27/06 phai bi xoa' );
assert_true( isset( $__tm_b['_ttplb_wstart_cccc3333'] ), 'Moc cua luot DANG CHAY (5 phut truoc) KHONG duoc xoa' );

// ---- 2. Không đụng option đang dùng ----
foreach ( array( 'ttplb_widget_style', 'ttplb_secret', 'ttplb_map' ) as $__tm_k ) {
    assert_true( isset( $__tm_b[ $__tm_k ] ), 'SONG CON: option dang dung cua cau noi khong duoc xoa: ' . $__tm_k );
}
assert_true( isset( $__tm_b['_transient_sitetop_x'], $__tm_b['Xttplb_wstart_gia'], $__tm_b['siteurl'] ),
    'Khong duoc dung toi hang xom (transient, ten na na, option thuong)' );

// ---- 1. Hình dạng câu SQL ($wpdb giả không đọc phép so sánh nên phải canh nguyên văn) ----
$__tm_sel = null; $__tm_del = null;
foreach ( $GLOBALS['wpdb']->cau as $__tm_c ) {
    if ( stripos( trim( $__tm_c[0] ), 'SELECT' ) === 0 && ! $__tm_sel ) $__tm_sel = $__tm_c;
    if ( stripos( trim( $__tm_c[0] ), 'DELETE' ) === 0 && ! $__tm_del ) $__tm_del = $__tm_c;
}
assert_true( $__tm_sel !== null, 'Phai TACH buoc doc (SELECT) ra khoi buoc xoa' );
assert_equals( '\\_ttplb\\_wstart\\_%', $__tm_sel[1][0] ?? '',
    'SONG CON: mau LIKE phai THOAT dau _ — de nguyen la MySQL quet toan bang wp_options' );
assert_true( preg_match( '#CAST\( *option_value AS UNSIGNED *\) < %d#', $__tm_sel[0] ) === 1,
    'Dieu kien tuoi phai la CAST(option_value AS UNSIGNED) < %d (gia tri moc la Unix time dang chuoi)' );
assert_true( stripos( $__tm_sel[0], 'LIMIT 500' ) !== false, 'Moi lo doc phai co tran LIMIT 500' );
assert_true( $__tm_del !== null && stripos( $__tm_del[0], 'WHERE option_name IN (' ) !== false,
    'Xoa phai theo DUNG TEN khoa (IN), chi khoa nhung dong sap xoa' );
assert_true( stripos( $__tm_del[0], 'LIKE' ) === false, 'Lenh DELETE khong duoc quet LIKE' );
// Mốc 1 ngày: đổi thành giờ/phút là xoá nhầm mốc của lượt đang chạy.
assert_true( strpos( preg_replace( '/\s+/', ' ', $__tm_than( $__tm_ma, 'sitetop_don_moc_ttplb' ) ), 'time() - DAY_IN_SECONDS' ) !== false,
    'Nguong tuoi phai la DAY_IN_SECONDS (mot ngay)' );

// ---- 4. Tồn đọng lớn: rút dần theo lô, có trần mỗi lượt cron ----
$GLOBALS['wpdb'] = new TM_Wpdb();
for ( $__tm_i = 0; $__tm_i < 12000; $__tm_i++ ) {
    $GLOBALS['wpdb']->bang[ '_ttplb_wstart_x' . $__tm_i ] = $__tm_now - 90000;
}
sitetop_don_moc_ttplb();
assert_equals( 7000, count( $GLOBALS['wpdb']->bang ),
    'Moi luot cron xoa toi da 10 lo x 500 = 5.000; ton dong 12.000 thi con 7.000 cho luot sau' );

// Cron 5 phút phải THỰC SỰ gọi hàm này, không thì viết xong nằm im.
$__tm_fn = (string) file_get_contents( $__tm_goc . '/functions.php' );
$__tm_sach = '';
foreach ( token_get_all( $__tm_fn ) as $__tm_t ) {
    if ( is_array( $__tm_t ) && in_array( $__tm_t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) continue;
    $__tm_sach .= is_array( $__tm_t ) ? $__tm_t[1] : $__tm_t;
}
$__tm_p = strpos( $__tm_sach, "add_action( 'sitetop_5min_cron'" );
assert_true( $__tm_p !== false, 'Tim duoc cron 5 phut' );
assert_true( strpos( substr( $__tm_sach, $__tm_p, 700 ), 'sitetop_don_moc_ttplb()' ) !== false,
    'Cron 5 phut PHAI goi sitetop_don_moc_ttplb()' );
