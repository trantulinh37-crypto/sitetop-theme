<?php
/* DỌN TRANSIENT HẾT HẠN (viết lại 22/09/2026) — chạy 5 phút/lần trong cron.

   Bản cũ quét TOÀN BỘ wp_options mỗi 5 phút vì mẫu LIKE '_transient_sitetop_%' mở đầu
   bằng ký tự đại diện '_' (MySQL không dùng được chỉ mục), lại là DELETE nên khoá dần các
   dòng đã quét — .net nghẽn từng đợt, admin chuyển trang chậm.

   Canh bốn điều, chạy hàm THẬT với $wpdb giả đóng vai bảng wp_options:
   1. Mẫu LIKE phải được THOÁT (\_) — để MySQL quét đúng khoảng trên chỉ mục.
   2. Tách đọc và xoá; mỗi lô có trần — không lệnh nào kéo dài.
   3. Chỉ xoá transient sitetop_ ĐÃ HẾT HẠN, xoá CẢ dòng giá trị lẫn dòng hạn.
   4. Transient còn hạn, transient không phải sitetop_, và option thường: KHÔNG được đụng. */

$__dt_goc = dirname( __DIR__, 2 );
$__dt_ma  = (string) file_get_contents( $__dt_goc . '/includes/cron-cleanup.php' );

$__dt_than = function ( $ma, $ten ) {
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

/* wp_options giả: mảng tên => giá trị. prepare() thay tham số; get_col() thực thi đúng
   ngữ nghĩa "LIKE <mẫu đã thoát> AND option_value < N LIMIT 500"; query() thực thi
   "DELETE ... WHERE option_name IN (...)". Ghi lại mọi câu SQL để soi. */
class DT_Wpdb {
    public $options = 'wp_options'; public $bang = array(); public $cau = array(); public $_a = array();
    public function esc_like( $t ) { return addcslashes( (string) $t, '_%\\' ); }
    public function prepare( $q, ...$a ) {
        if ( count( $a ) === 1 && is_array( $a[0] ) ) $a = $a[0];
        $this->_a = $a; return $q;
    }
    public function get_col( $q ) {
        $this->cau[] = array( $q, $this->_a );
        list( $mau, $truoc ) = $this->_a;
        // Diễn lại LIKE của MySQL: '\_' là dấu gạch thật, '%' cuối là "bất kỳ".
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
        if ( stripos( $q, 'DELETE' ) === 0 || stripos( trim( $q ), 'DELETE' ) === 0 ) {
            foreach ( $this->_a as $ten ) unset( $this->bang[ $ten ] );
        }
        return 1;
    }
}

if ( ! function_exists( 'sitetop_cleanup_expired_transients' ) ) {
    $__dt_f = $__dt_than( $__dt_ma, 'sitetop_cleanup_expired_transients' );
    if ( $__dt_f === '' ) { assert_true( false, 'Khong trich duoc sitetop_cleanup_expired_transients' ); return; }
    eval( $__dt_f );
}

$__dt_now = time();
$GLOBALS['wpdb'] = new DT_Wpdb();
$GLOBALS['wpdb']->bang = array(
    // sitetop_ HẾT hạn -> phải xoá cả 2 dòng
    '_transient_timeout_sitetop_seen_abc' => $__dt_now - 60,  '_transient_sitetop_seen_abc' => '123',
    '_transient_timeout_sitetop_left_xyz' => $__dt_now - 5,   '_transient_sitetop_left_xyz' => '456',
    // sitetop_ CÒN hạn -> giữ
    '_transient_timeout_sitetop_timer_ok' => $__dt_now + 600, '_transient_sitetop_timer_ok' => '1',
    // transient KHÔNG phải sitetop_ (plugin khác) dù hết hạn -> giữ, không phải việc của ta
    '_transient_timeout_woo_cart_1' => $__dt_now - 60,        '_transient_woo_cart_1' => 'x',
    // option thường có tên na ná (dấu _ đại diện từng làm khớp nhầm kiểu này) -> giữ
    'Xtransient_timeout_sitetop_gia' => $__dt_now - 60,
    'siteurl' => 'https://sitetop.net',
);
sitetop_cleanup_expired_transients();
$__dt_b = $GLOBALS['wpdb']->bang;

// ---- 3. Xoá đúng: cả dòng giá trị lẫn dòng hạn của transient hết hạn ----
foreach ( array( 'seen_abc', 'left_xyz' ) as $__dt_k ) {
    assert_true( ! isset( $__dt_b[ '_transient_timeout_sitetop_' . $__dt_k ] ), 'Phai xoa dong HAN cua transient het han: ' . $__dt_k );
    assert_true( ! isset( $__dt_b[ '_transient_sitetop_' . $__dt_k ] ), 'Phai xoa dong GIA TRI cua transient het han: ' . $__dt_k );
}
// ---- 4. Không đụng thứ khác ----
assert_true( isset( $__dt_b['_transient_timeout_sitetop_timer_ok'], $__dt_b['_transient_sitetop_timer_ok'] ),
    'Transient CON HAN tuyet doi khong duoc xoa' );
assert_true( isset( $__dt_b['_transient_timeout_woo_cart_1'], $__dt_b['_transient_woo_cart_1'] ),
    'Transient khong phai sitetop_ khong phai viec cua ta — khong duoc xoa' );
assert_true( isset( $__dt_b['Xtransient_timeout_sitetop_gia'] ),
    'Option co ten na na (vi dau _ dai dien) khong duoc xoa nham' );
assert_true( isset( $__dt_b['siteurl'] ), 'Option thuong khong duoc dung' );

// ---- 1 + 2. Hình dạng câu SQL ----
$__dt_sel = null; $__dt_del = null;
foreach ( $GLOBALS['wpdb']->cau as $__dt_c ) {
    if ( stripos( trim( $__dt_c[0] ), 'SELECT' ) === 0 && ! $__dt_sel ) $__dt_sel = $__dt_c;
    if ( stripos( trim( $__dt_c[0] ), 'DELETE' ) === 0 && ! $__dt_del ) $__dt_del = $__dt_c;
}
assert_true( $__dt_sel !== null, 'Phai TACH buoc doc (SELECT) ra khoi buoc xoa' );
assert_equals( '\\_transient\\_timeout\\_sitetop\\_%', $__dt_sel[1][0] ?? '',
    'SONG CON: mau LIKE phai THOAT dau _ — de nguyen la ky tu dai dien, MySQL quet toan bang wp_options' );
assert_true( stripos( $__dt_sel[0], 'LIMIT 500' ) !== false, 'Moi lo doc phai co tran LIMIT 500' );
/* $wpdb giả không đọc phép so sánh trong SQL (nó dùng tham số), nên phải canh nguyên văn:
   sửa '<' thành '<=' hay cộng thêm số vào đây là xoá luôn transient CÒN HẠN mà test hành vi
   không thấy — đã thử phá đúng kiểu đó và nó lọt, nên mới có dòng này. */
assert_true( preg_match( '#AND option_value < %d\s+LIMIT 500#', $__dt_sel[0] ) === 1,
    'Dieu kien het han phai dung nguyen van "option_value < %d" (khong duoc xoa transient con han)' );
assert_true( $__dt_del !== null && stripos( $__dt_del[0], 'WHERE option_name IN (' ) !== false,
    'Xoa phai theo DUNG TEN khoa (IN), chi khoa nhung dong sap xoa' );
assert_true( stripos( $__dt_del[0], 'JOIN' ) === false && stripos( $__dt_del[0], 'LIKE' ) === false,
    'Lenh DELETE khong duoc tu noi bang hay quet LIKE nua' );

// ---- Tồn đọng lớn: rút dần theo lô, có trần số lô mỗi lượt ----
$GLOBALS['wpdb'] = new DT_Wpdb();
for ( $__dt_i = 0; $__dt_i < 12000; $__dt_i++ ) {
    $GLOBALS['wpdb']->bang[ '_transient_timeout_sitetop_x' . $__dt_i ] = $__dt_now - 10;
    $GLOBALS['wpdb']->bang[ '_transient_sitetop_x' . $__dt_i ] = '1';
}
sitetop_cleanup_expired_transients();
$__dt_con = 0;
foreach ( $GLOBALS['wpdb']->bang as $__dt_t => $__dt_v ) if ( strpos( $__dt_t, '_transient_timeout_sitetop_' ) === 0 ) $__dt_con++;
assert_equals( 2000, $__dt_con, 'Moi luot xoa toi da 20 lo x 500 = 10.000; ton dong 12.000 thi con 2.000 cho luot sau' );
