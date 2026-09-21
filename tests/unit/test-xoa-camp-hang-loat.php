<?php
/* XOÁ CHIẾN DỊCH HÀNG LOẠT (21/09/2026) — ô tích ở tab Tạm dừng và Đã xóa.

   Xoá vĩnh viễn là thao tác KHÔNG hoàn tác được, nên canh chặt bốn điều:
   1. Hàm xoá vĩnh viễn TỰ đòi trạng thái 'deleted' — không phụ thuộc giao diện có lọc đúng
      hay không. Camp đang chạy mà lọt vào đây thì không được đụng một dòng nào.
   2. Nó chỉ xoá keyword_campaigns + customer_orders, TUYỆT ĐỐI không đụng giao dịch tiền
      (số dư khách tính LIVE từ customer_transactions) hay lịch sử lượt xem.
   3. Xoá mềm hàng loạt chỉ nhận camp đang TẠM DỪNG — request tự chế gửi ID camp đang chạy
      vào cũng bị bỏ qua.
   4. Nút xoá từng camp và thao tác hàng loạt đi qua CÙNG một hàm — không lệch nhau được.
   Hàm chạy THẬT với $wpdb giả; phép canh đấu dây đọc trên mã đã lột comment. */

$__xc_goc = dirname( __DIR__, 2 );
$__xc_cm  = (string) file_get_contents( $__xc_goc . '/includes/campaign-management.php' );
$__xc_tab = (string) file_get_contents( $__xc_goc . '/includes/admin/tabs/tab-campaigns.php' );

$__xc_than = function ( $ma, $ten ) {
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
$__xc_lot = function ( $ma ) {
    $out = '';
    foreach ( token_get_all( $ma ) as $t ) {
        if ( is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) continue;
        $out .= is_array( $t ) ? $t[1] : $t;
    }
    return $out;
};

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error { public $code; public $msg; function __construct( $c = '', $m = '' ) { $this->code = $c; $this->msg = $m; } }
}
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $x ) { return $x instanceof WP_Error; } }

/* $wpdb giả: một bảng camp trong bộ nhớ, ghi lại MỌI lệnh update/delete kèm tên bảng. */
class XC_Wpdb {
    public $prefix = 'wpgd_'; public $camp = array(); public $lenh = array(); public $_a = array();
    public function prepare( $q, ...$a ) { $this->_a = $a; return $q; }
    public function get_row( $q ) {
        $id = (int) ( $this->_a[0] ?? 0 );
        return isset( $this->camp[ $id ] ) ? (object) $this->camp[ $id ] : null;
    }
    public function update( $bang, $du_lieu, $dk ) { $this->lenh[] = array( 'update', $bang, $du_lieu, $dk ); return 1; }
    public function delete( $bang, $dk ) { $this->lenh[] = array( 'delete', $bang, $dk ); return 1; }
}

foreach ( array( 'sitetop_xoa_mem_campaign', 'sitetop_xoa_vinh_vien_campaign' ) as $__xc_ten ) {
    if ( function_exists( $__xc_ten ) ) continue;
    $__xc_ma = $__xc_than( $__xc_cm, $__xc_ten );
    if ( $__xc_ma === '' ) { assert_true( false, 'Khong trich duoc ' . $__xc_ten ); return; }
    eval( $__xc_ma );
}

$__xc_moi = function ( $camp ) {
    $GLOBALS['wpdb'] = new XC_Wpdb();
    $GLOBALS['wpdb']->camp = $camp;
    return $GLOBALS['wpdb'];
};
$__xc_bang = function ( $db, $loai ) {
    $ds = array();
    foreach ( $db->lenh as $l ) if ( $l[0] === $loai ) $ds[] = $l[1];
    return $ds;
};

/* ---- 1. XOÁ VĨNH VIỄN ---- */
// Camp ĐANG CHẠY lọt vào (request tự chế / bấm nhầm): không được đụng một dòng nào.
$__xc_db = $__xc_moi( array( 7 => array( 'id' => 7, 'order_id' => 70, 'status' => 'active' ) ) );
$__xc_kq = sitetop_xoa_vinh_vien_campaign( 7 );
assert_true( is_wp_error( $__xc_kq ), 'Xoa vinh vien camp DANG CHAY phai bi tu choi' );
assert_equals( 0, count( $__xc_db->lenh ), 'Camp dang chay: KHONG duoc chay lenh xoa/sua nao' );

foreach ( array( 'paused', 'pending', 'rejected' ) as $__xc_st ) {
    $__xc_db = $__xc_moi( array( 8 => array( 'id' => 8, 'order_id' => 0, 'status' => $__xc_st ) ) );
    assert_true( is_wp_error( sitetop_xoa_vinh_vien_campaign( 8 ) ) && ! $__xc_db->lenh,
        'Chi camp DA XOA MEM moi duoc xoa vinh vien — trang thai ' . $__xc_st . ' phai bi tu choi' );
}

$__xc_db = $__xc_moi( array() );
assert_true( is_wp_error( sitetop_xoa_vinh_vien_campaign( 99 ) ), 'Camp khong ton tai -> bao loi, khong xoa gi' );

// Camp đã xoá mềm: xoá ĐÚNG hai bảng, không thêm bảng nào.
$__xc_db = $__xc_moi( array( 9 => array( 'id' => 9, 'order_id' => 90, 'status' => 'deleted' ) ) );
assert_true( sitetop_xoa_vinh_vien_campaign( 9 ) === true, 'Camp da xoa mem -> xoa vinh vien duoc' );
assert_equals( json_encode( array( 'wpgd_sitetop_keyword_campaigns', 'wpgd_sitetop_customer_orders' ) ),
    json_encode( $__xc_bang( $__xc_db, 'delete' ) ),
    'Xoa vinh vien chi duoc xoa DUNG 2 bang: chien dich + don hang' );
foreach ( $__xc_db->lenh as $__xc_l ) {
    assert_true( strpos( $__xc_l[1], 'transactions' ) === false && strpos( $__xc_l[1], 'visits' ) === false
        && strpos( $__xc_l[1], 'reports' ) === false,
        'TUYET DOI khong dung giao dich tien / lich su luot xem: ' . $__xc_l[1] );
}

// Camp không có đơn hàng: chỉ xoá bảng chiến dịch.
$__xc_db = $__xc_moi( array( 10 => array( 'id' => 10, 'order_id' => 0, 'status' => 'deleted' ) ) );
sitetop_xoa_vinh_vien_campaign( 10 );
assert_equals( json_encode( array( 'wpgd_sitetop_keyword_campaigns' ) ), json_encode( $__xc_bang( $__xc_db, 'delete' ) ),
    'Camp khong co don hang -> chi xoa bang chien dich' );

/* ---- 2. XOÁ MỀM: chỉ đổi trạng thái, không xoá dòng nào ---- */
$__xc_db = $__xc_moi( array( 11 => array( 'id' => 11, 'order_id' => 110, 'status' => 'paused' ) ) );
assert_true( sitetop_xoa_mem_campaign( 11 ) === true, 'Xoa mem camp tam dung phai thanh cong' );
assert_equals( 0, count( $__xc_bang( $__xc_db, 'delete' ) ), 'Xoa mem KHONG duoc xoa dong nao' );
assert_equals( 2, count( $__xc_bang( $__xc_db, 'update' ) ), 'Xoa mem doi trang thai ca chien dich lan don hang' );
foreach ( $__xc_db->lenh as $__xc_l ) {
    assert_equals( 'deleted', $__xc_l[2]['status'] ?? '', 'Xoa mem phai dat trang thai deleted cho ' . $__xc_l[1] );
}

/* ---- 3. XỬ LÝ HÀNG LOẠT trong tab (đọc trên mã đã lột comment) ---- */
$__xc_tma = $__xc_lot( $__xc_tab );
$__xc_p   = strpos( $__xc_tma, "isset(\$_POST['campaign_bulk_action'])" );
assert_true( $__xc_p !== false, 'Phai co khoi xu ly xoa hang loat' );
$__xc_khoi = $__xc_p !== false ? substr( $__xc_tma, $__xc_p, 1800 ) : '';
assert_true( strpos( $__xc_khoi, "wp_verify_nonce(\$_POST['_wpnonce'] ?? '','sitetop_campaign_bulk')" ) !== false,
    'Xoa hang loat PHAI kiem nonce rieng sitetop_campaign_bulk' );
assert_true( strpos( $__xc_khoi, "(\$st === 'paused') ? sitetop_xoa_mem_campaign(\$cid)" ) !== false,
    'Xoa mem hang loat CHI nhan camp dang TAM DUNG — request tu che khong xoa duoc camp dang chay' );
assert_true( strpos( $__xc_khoi, 'sitetop_xoa_vinh_vien_campaign($cid)' ) !== false,
    'Xoa vinh vien hang loat phai di qua ham chung (ham tu chot trang thai deleted)' );
assert_true( strpos( $__xc_khoi, "array_map('intval'" ) !== false && strpos( $__xc_khoi, ', 0, 500)' ) !== false,
    'Danh sach ID phai ep so nguyen va toi da 500 mot lan' );

/* ---- 4. Nút từng camp và hàng loạt dùng CÙNG hàm — không còn SQL xoá chép tay ---- */
assert_true( substr_count( $__xc_tma, 'sitetop_xoa_mem_campaign(' ) >= 2,
    'Nut Xoa tung camp va xoa hang loat phai cung goi sitetop_xoa_mem_campaign' );
assert_true( substr_count( $__xc_tma, 'sitetop_xoa_vinh_vien_campaign(' ) >= 2,
    'Nut Xoa vinh vien tung camp va hang loat phai cung goi sitetop_xoa_vinh_vien_campaign' );
assert_true( strpos( $__xc_tma, "\$wpdb->delete(\$prefix.'keyword_campaigns'" ) === false,
    'Khong duoc con lenh xoa chien dich chep tay trong tab — phai qua ham chung' );

/* ---- 5. Giao diện ---- */
assert_true( strpos( $__xc_tma, "in_array(\$status_filter, array('paused','deleted'), true) ? 200 : 20" ) !== false,
    'Tab Tam dung / Da xoa hien toi 200 camp mot trang de chon tat ca phu het trong mot lan' );
assert_true( strpos( $__xc_tab, 'form="camp-bulk"' ) !== false,
    'O tich tung dong phai thuoc form camp-bulk (moi dong da co form rieng, HTML khong cho long form)' );
assert_true( strpos( $__xc_tab, "\$bulk_tab = in_array(\$status_filter, array('paused','deleted'), true)" ) !== false,
    'O tich CHI hien o tab Tam dung va Da xoa' );
