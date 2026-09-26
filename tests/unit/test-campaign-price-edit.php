<?php
/* SỬA GIÁ/VIEW CỦA CAMP — ai được sửa, lúc nào.
   26/09/2026: chủ site yêu cầu mở thêm trạng thái TẠM DỪNG (trước chỉ Chờ duyệt).

   Bản test cũ là "logic-replica" — chép lại luật rồi kiểm bản chép, nên mã thật đổi mà test
   vẫn xanh. Nay CẮT THẲNG đoạn chốt từ includes/admin-dashboard.php rồi chạy nó. */

$__gp_goc  = dirname( __DIR__, 2 );
$__gp_adm  = (string) file_get_contents( $__gp_goc . '/includes/admin-dashboard.php' );
$__gp_tab  = (string) file_get_contents( $__gp_goc . '/includes/admin/tabs/tab-campaigns.php' );

/* ---- 1. Chốt bên máy chủ: chạy thật ---- */
assert_true( preg_match(
    "#(if \(isset\(\\\$_POST\['price_per_view'\]\)\) \{.*?\n    \})#s", $__gp_adm, $__gp_m ) === 1,
    'Lay duoc doan chot gia tu admin-dashboard.php' );
$__gp_chot = $__gp_m[1];

$nhan_gia = function ( $trang_thai, $gia_gui, $co_camp = true ) use ( $__gp_chot ) {
    $_POST = array( 'price_per_view' => $gia_gui );
    $camp  = $co_camp ? (object) array( 'status' => $trang_thai ) : null;
    eval( $__gp_chot );
    return array_key_exists( 'price_per_view', $_POST ) ? $_POST['price_per_view'] : null;
};

// Được sửa
assert_equals( 1400.0, $nhan_gia( 'pending', '1400' ), 'Cho duyet + gia hop le -> nhan' );
assert_equals( 1500.0, $nhan_gia( 'paused', '1500' ),  'MOI 26/09: Tam dung + gia hop le -> nhan' );
assert_equals( 2000.0, $nhan_gia( 'paused', 2000 ),    'Tam dung + gia dang so -> nhan' );

// KHÔNG được sửa — camp đang chạy thì lượt đang làm dở sẽ bị trừ theo giá mới giữa chừng.
assert_equals( null, $nhan_gia( 'active', '1400' ),    'Dang chay -> KHONG duoc doi gia' );
assert_equals( null, $nhan_gia( 'rejected', '1400' ),  'Bi tu choi -> khong' );
assert_equals( null, $nhan_gia( 'deleted', '1400' ),   'Da xoa -> khong' );
assert_equals( null, $nhan_gia( 'completed', '1400' ), 'Da xong -> khong' );

// Giá không hợp lệ thì trạng thái nào cũng bỏ qua
foreach ( array( 'pending', 'paused' ) as $tt ) {
    assert_equals( null, $nhan_gia( $tt, '0' ),    "Gia 0 -> bo qua ($tt)" );
    assert_equals( null, $nhan_gia( $tt, '-500' ), "Gia am -> bo qua ($tt)" );
    assert_equals( null, $nhan_gia( $tt, 'abc' ),  "Gia khong phai so -> bo qua ($tt)" );
}
assert_equals( null, $nhan_gia( 'paused', '1400', false ), 'Khong tim thay camp -> bo qua' );

/* ---- 2. Giao diện phải MỞ ĐÚNG BẰNG máy chủ ----
   Mở ở giao diện mà quên mở bên máy chủ thì admin gõ giá mới, bấm lưu, không báo lỗi gì cả
   nhưng giá cũ vẫn nguyên — kiểu hỏng khó thấy nhất. Canh cả hai chiều. */
assert_true( substr_count( $__gp_tab, "_admEditStatus === 'pending' || _admEditStatus === 'paused'" ) === 2,
    'Ca hai cho ben JS (mo o nhap + luc gui) deu phai cho Cho duyet lan Tam dung' );
assert_true( strpos( $__gp_tab, "var priceEditable = (_admEditStatus === 'pending' || _admEditStatus === 'paused');" ) !== false,
    'O nhap gia phai mo khi Tam dung' );
assert_true( strpos( $__gp_tab, 'Chỉ chỉnh được khi camp Chờ duyệt hoặc Tạm dừng' ) !== false,
    'Dong nhac duoi o gia phai noi dung dieu kien moi' );
assert_true( strpos( $__gp_tab, "_admEditStatus === 'active'" ) === false,
    'Khong duoc mo o gia cho camp dang chay' );
// Máy chủ cũng chỉ đúng hai trạng thái đó, không nhiều hơn.
assert_true( strpos( $__gp_adm, "in_array( \$camp->status, array( 'pending', 'paused' ), true )" ) !== false,
    'Danh sach ben may chu phai dung hai trang thai: pending + paused' );

/* ---- 3. Đổi giá xong phải đồng bộ sang đơn hàng ---- */
assert_true( strpos( $__gp_adm, "array('price_per_task' => floatval(\$_POST['price_per_view']), 'updated_at' => sitetop_current_time())" ) !== false,
    'Gia moi phai duoc dong bo sang customer_orders.price_per_task' );

/* ---- 4. Tính lại giá từ settings CHỈ khi đổi loại traffic/onsite ---- */
/* Giữ nguyên luật cũ: sửa field khác không được reset giá custom vừa gõ tay. */
assert_true( strpos( $__gp_adm, "\$type_changed = (\$tt !== (\$camp->traffic_type ?? '1step')) || (\$os !== intval(\$camp->onsite_time ?? 70));" ) !== false,
    'Dieu kien tinh lai gia phai giu nguyen' );
assert_true( strpos( $__gp_adm, "if (!isset(\$_POST['price_per_view'])) {" ) !== false,
    'Gia gui tay phai thang gia tinh lai tu settings' );
