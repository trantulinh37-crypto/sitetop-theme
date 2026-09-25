<?php
/* THỐNG KÊ CAMP THEO NGÀY (khu vực khách hàng) — 25/09/2026.

   Chủ site muốn: chọn camp + chọn ngày -> ra số view camp đó chạy được trong ngày, tính theo
   ĐÚNG logic thống kê sẵn có, không đổi gì khác.

   Bốn điều canh, chạy HÀM THẬT với $wpdb giả:
   1. CÔNG THỨC phải y hệt ô "Hôm nay" đang dùng: (step='verified' OR customer_paid=1) lọc theo
      DATE(created_at). Lệch công thức là hai chỗ ra hai số, khách mất lòng tin ngay.
   2. CHỦ QUYỀN: camp của khách khác -> trả null và TUYỆT ĐỐI không chạy câu đếm.
   2b. CAMP ĐÃ XOÁ -> cũng không tra được (chủ site chốt 25/09: xoá là biến mất). Chốt phải nằm
       trong câu SQL chủ quyền, không chỉ ẩn ở ô chọn — ẩn ngoài giao diện thì gõ tay
       ?ck_camp=<id đã xoá> là lại xem được.
   3. Ngày phải đúng dạng và CÓ THẬT (2026-02-31 đúng dạng nhưng không tồn tại).
   4. Giao diện: form đi chung cơ chế ?tab= sẵn có, không thêm cổng ajax mới. */

$__tk_goc = dirname( __DIR__, 2 );
$__tk_ma  = (string) file_get_contents( $__tk_goc . '/includes/customer-management.php' );

$__tk_than = function ( $ma, $ten ) {
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

/* $wpdb giả: câu hỏi "camp này của ai" trả $la_cua_minh; câu đếm trả $so_view. Ghi lại mọi SQL. */
class TK_Wpdb {
    public $prefix = 'wpgd_'; public $cau = array(); public $la_cua_minh = 1; public $so_view = 0;
    public function prepare( $q, ...$a ) {
        if ( count( $a ) === 1 && is_array( $a[0] ) ) $a = $a[0];
        $i = 0;
        return preg_replace_callback( '/%[sdf]/', function ( $m ) use ( &$i, $a ) {
            $v = $a[ $i++ ] ?? null;
            return $m[0] === '%s' ? "'" . addslashes( (string) $v ) . "'" : (string) (int) $v;
        }, $q );
    }
    public function get_var( $q ) {
        $this->cau[] = $q;
        return strpos( $q, 'keyword_campaigns' ) !== false ? $this->la_cua_minh : $this->so_view;
    }
}

if ( ! function_exists( 'sitetop_customer_camp_views_ngay' ) ) {
    $__tk_f = $__tk_than( $__tk_ma, 'sitetop_customer_camp_views_ngay' );
    if ( $__tk_f === '' ) { assert_true( false, 'Khong trich duoc sitetop_customer_camp_views_ngay' ); return; }
    eval( $__tk_f );
}

$__tk_chay = function ( $cust, $camp, $ngay, $la_cua_minh = 1, $so_view = 137 ) {
    $GLOBALS['wpdb'] = new TK_Wpdb();
    $GLOBALS['wpdb']->la_cua_minh = $la_cua_minh;
    $GLOBALS['wpdb']->so_view     = $so_view;
    $kq = sitetop_customer_camp_views_ngay( $cust, $camp, $ngay );
    return array( $kq, $GLOBALS['wpdb']->cau );
};

// ---- 1. Ca thường: camp của mình, ngày hợp lệ ----
list( $__tk_kq, $__tk_cau ) = $__tk_chay( 503, 424, '2026-09-24' );
assert_equals( 137, $__tk_kq, 'Camp cua chinh khach + ngay hop le -> tra dung so view' );
$__tk_dem = '';
foreach ( $__tk_cau as $__tk_c ) if ( strpos( $__tk_c, 'shortlink_visits' ) !== false ) { $__tk_dem = preg_replace( '/\s+/', ' ', $__tk_c ); break; }
assert_true( $__tk_dem !== '', 'Phai co cau dem tren shortlink_visits' );
assert_true( strpos( $__tk_dem, "(step='verified' OR customer_paid=1)" ) !== false,
    'SONG CON: phai dung DUNG cong thuc cua o "Hom nay" — (step=verified OR customer_paid=1). Cau: ' . substr( $__tk_dem, 0, 150 ) );
assert_true( strpos( $__tk_dem, "DATE(created_at) = '2026-09-24'" ) !== false,
    'Phai loc theo DATE(created_at) dung ngay duoc chon' );
assert_true( strpos( $__tk_dem, 'campaign_id = 424' ) !== false, 'Phai dem dung campaign_id duoc chon' );

// ---- 2. CHỦ QUYỀN: camp của khách khác ----
list( $__tk_kq, $__tk_cau ) = $__tk_chay( 503, 999, '2026-09-24', 0 );
assert_true( $__tk_kq === null, 'Camp KHONG phai cua khach nay -> phai tra null' );
$__tk_co_dem = false;
foreach ( $__tk_cau as $__tk_c ) if ( strpos( $__tk_c, 'shortlink_visits' ) !== false ) $__tk_co_dem = true;
assert_true( ! $__tk_co_dem, 'SONG CON: camp cua nguoi khac thi KHONG duoc chay cau dem (khong lo so lieu)' );

// ---- 2b. CAMP ĐÃ XOÁ: chốt phải nằm trong SQL, không chỉ ẩn ngoài giao diện ----
list( , $__tk_cau ) = $__tk_chay( 503, 424, '2026-09-24' );
$__tk_chu = '';
foreach ( $__tk_cau as $__tk_c ) if ( strpos( $__tk_c, 'keyword_campaigns' ) !== false ) { $__tk_chu = preg_replace( '/\s+/', ' ', $__tk_c ); break; }
assert_true( $__tk_chu !== '', 'Phai co cau hoi chu quyen tren keyword_campaigns' );
assert_true( strpos( $__tk_chu, "status != 'deleted'" ) !== false,
    'SONG CON: cau chu quyen PHAI loai camp da xoa — an o o chon thoi thi go tay ?ck_camp=<id> van xem duoc. Cau: ' . substr( $__tk_chu, 0, 140 ) );

// ---- 3. Ngày sai định dạng / không có thật ----
foreach ( array( '24/09/2026', '2026-9-4', 'hom qua', '', '2026-02-31', '0000-00-00' ) as $__tk_ng ) {
    list( $__tk_kq, ) = $__tk_chay( 503, 424, $__tk_ng );
    assert_true( $__tk_kq === null, 'Ngay khong hop le phai tra null: "' . $__tk_ng . '"' );
}
// Ngày có thật nhưng là 29/02 năm nhuận -> phải CHẤP NHẬN
list( $__tk_kq, ) = $__tk_chay( 503, 424, '2024-02-29' );
assert_equals( 137, $__tk_kq, 'Ngay 29/02 nam nhuan la ngay co that -> phai chap nhan' );

// ---- 4. Tham số rác ----
foreach ( array( array( 0, 424 ), array( 503, 0 ), array( -1, 424 ), array( 503, -5 ) ) as $__tk_p ) {
    list( $__tk_kq, ) = $__tk_chay( $__tk_p[0], $__tk_p[1], '2026-09-24' );
    assert_true( $__tk_kq === null, 'Tham so <= 0 phai tra null (' . $__tk_p[0] . ',' . $__tk_p[1] . ')' );
}

// ---- 5. Giao diện: form GET đi chung ?tab=, không thêm cổng ajax ----
$__tk_tpl = (string) file_get_contents( $__tk_goc . '/page-customer-dashboard.php' );
assert_true( strpos( $__tk_tpl, 'sitetop_customer_camp_views_ngay' ) !== false, 'Trang khach hang phai goi ham dem' );
assert_true( strpos( $__tk_tpl, 'name="ck_camp"' ) !== false && strpos( $__tk_tpl, 'name="ck_date"' ) !== false,
    'Phai co o chon camp va o chon ngay' );
assert_true( preg_match( '#<form method="get"[^>]*>\s*<input type="hidden" name="tab" value="campaigns">#s', $__tk_tpl ) === 1,
    'Form phai di chung co che ?tab= san co (khong reload sang tab khac)' );
assert_true( strpos( $__tk_tpl, 'wp_ajax_sitetop_customer_camp' ) === false,
    'Khong duoc them cong ajax moi cho viec nay' );
assert_true( preg_match( "#SELECT id, title, keyword FROM \{\\\$prefix\}keyword_campaigns WHERE customer_id=%d AND status != 'deleted'#", $__tk_tpl ) === 1,
    'O chon camp PHAI loai camp da xoa (giong bang chien dich ngay ben duoi)' );
// Không đụng chức năng cũ: các mốc sẵn có của tab Chiến dịch phải còn nguyên.
foreach ( array( 'filterCampStatus', 'camp-pills', 'id="p-campaigns"' ) as $__tk_moc ) {
    assert_true( strpos( $__tk_tpl, $__tk_moc ) !== false, 'Chuc nang cu cua tab Chien dich phai con nguyen: ' . $__tk_moc );
}
