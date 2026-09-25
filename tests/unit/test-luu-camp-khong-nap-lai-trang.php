<?php
/* LƯU CAMP XONG KHÔNG NẠP LẠI CẢ TRANG ADMIN — 25/09/2026.

   Chủ site báo "tải ảnh camp xong bấm lưu load chậm". Đo ra: câu lệnh lưu chỉ là một UPDATE
   (vài mili giây), nhưng lưu xong JS chờ 0,8 giây rồi location.reload() — tức mỗi lần lưu là
   một lượt tải TOÀN BỘ trang admin, trong khi cổng admin-ajax đang gánh ~16 request/giây
   (đỉnh 45) do trang nhiệm vụ thăm dò 2 giây/lần, cộng chuyện khựng ở cửa hosting.

   Nay: cập nhật đúng dòng vừa sửa. Bốn điều canh:
   1. Dòng và các ô có mốc nhận dạng để JS tìm đúng chỗ.
   2. Lấy số từ MÁY CHỦ, không lấy từ ô nhập (máy chủ có thể tự tính lại giá/thưởng).
   3. Luôn có đường lùi: không thấy dòng / lấy dữ liệu hỏng thì nạp lại trang, để không bao
      giờ hiện số cũ.
   4. Bảng nhãn + màu bên JS phải KHỚP bảng bên PHP — lệch là dòng vừa sửa hiện sai loại. */

$__lc_goc = dirname( __DIR__, 2 );
$__lc_ma  = (string) file_get_contents( $__lc_goc . '/includes/admin/tabs/tab-campaigns.php' );

/* ---- 1. Mốc nhận dạng trong bảng ---- */
assert_true( strpos( $__lc_ma, '<tr data-camp="<?php echo (int) $row->id; ?>">' ) !== false,
    'Moi dong camp phai co data-camp de JS tim dung dong' );
foreach ( array( 'col-kw', 'col-daily', 'so-ngay', 'col-tt', 'tt-nhan', 'tt-macodinh' ) as $__lc_moc ) {
    assert_true( strpos( $__lc_ma, $__lc_moc ) !== false, 'Thieu moc nhan dang o bang: ' . $__lc_moc );
}

/* ---- 2 + 3. Luồng sau khi lưu ---- */
$__lc_p = strpos( $__lc_ma, 'function admCapNhatDongCamp' );
assert_true( $__lc_p !== false, 'Phai co ham cap nhat dong tai cho' );
/* Cắt TRỌN thân hàm bằng cách đếm ngoặc — cắt cứng theo số ký tự thì hàm dài thêm một dòng là
   test tự đỏ oan (đã dính: cắt 2600 trong khi hàm dài 2924). */
$__lc_ham = ''; $__lc_sau = 0; $__lc_mo = false;
for ( $__lc_i = $__lc_p; $__lc_i < strlen( $__lc_ma ); $__lc_i++ ) {
    $__lc_ch = $__lc_ma[ $__lc_i ];
    $__lc_ham .= $__lc_ch;
    if ( $__lc_ch === '{' ) { $__lc_sau++; $__lc_mo = true; }
    elseif ( $__lc_ch === '}' ) { $__lc_sau--; if ( $__lc_mo && $__lc_sau === 0 ) break; }
}

assert_true( strpos( $__lc_ma, 'setTimeout(function(){ location.reload(); }, 800);' ) === false,
    'Khong duoc nap lai ca trang admin sau moi lan luu nua' );
assert_true( preg_match( '#if \(r\.success\) \{.{0,200}admCapNhatDongCamp\(#s', $__lc_ma ) === 1,
    'Luu xong phai goi admCapNhatDongCamp (cap nhat dong), khong phai reload' );
assert_true( strpos( $__lc_ma, "document.getElementById('adminEditCampModal').style.display = 'none';" ) !== false,
    'Luu xong phai dong khung sua' );

assert_true( strpos( $__lc_ham, "fd.append('action', 'sitetop_admin_get_campaign');" ) !== false,
    'SONG CON: so hien thi phai lay tu MAY CHU (sitetop_admin_get_campaign), khong lay tu o nhap — may chu co the tu tinh lai gia/thuong' );
assert_true( preg_match( "#admEdit(Kw|Daily|Onsite|Price)'\)\.value#", $__lc_ham ) === 0,
    'Ham cap nhat dong KHONG duoc doc gia tri tu o nhap cua form' );

// Ba đường lùi: không thấy dòng, dữ liệu hỏng, mạng lỗi.
assert_true( substr_count( $__lc_ham, 'location.reload();' ) >= 3,
    'Phai co du duong lui (khong thay dong / du lieu hong / loi mang) — thieu la hien so cu' );
assert_true( preg_match( '#if \(!tr\) \{ location\.reload\(\); return; \}#', $__lc_ham ) === 1,
    'Khong tim thay dong thi PHAI nap lai trang' );
assert_true( strpos( $__lc_ham, '.catch(function(){ location.reload(); });' ) !== false,
    'Loi mang thi PHAI nap lai trang' );

/* ---- 4. Bảng nhãn/màu hai bên phải khớp ---- */
$__lc_doc_php = function ( $ma, $ten ) {
    if ( ! preg_match( '/\$' . $ten . '\s*=\s*\[(.*?)\];/s', $ma, $m ) ) return array();
    preg_match_all( "/'([a-z0-9]+)'\s*=>\s*'([^']*)'/i", $m[1], $mm, PREG_SET_ORDER );
    $r = array(); foreach ( $mm as $x ) $r[ $x[1] ] = $x[2];
    return $r;
};
$__lc_doc_js = function ( $ma, $ten ) {
    if ( ! preg_match( '/var ' . $ten . '\s*=\s*\{(.*?)\};/s', $ma, $m ) ) return array();
    preg_match_all( "/'([a-z0-9]+)'\s*:\s*'([^']*)'/i", $m[1], $mm, PREG_SET_ORDER );
    $r = array(); foreach ( $mm as $x ) $r[ $x[1] ] = $x[2];
    return $r;
};
foreach ( array(
    array( 'traffic_labels', 'nhan', 'nhan loai nhiem vu' ),
    array( 'traffic_bg',     'nen',  'mau nen' ),
    array( 'traffic_colors', 'mau',  'mau chu' ),
) as $__lc_cap ) {
    $__lc_php = $__lc_doc_php( $__lc_ma, $__lc_cap[0] );
    $__lc_js  = $__lc_doc_js( $__lc_ham, $__lc_cap[1] );
    assert_true( ! empty( $__lc_php ) && ! empty( $__lc_js ), 'Doc duoc ca hai bang: ' . $__lc_cap[2] );
    assert_equals( json_encode( $__lc_php ), json_encode( $__lc_js ),
        'Bang ' . $__lc_cap[2] . ' ben JS phai KHOP ben PHP — lech la dong vua sua hien sai' );
}

/* ---- 5. Không đụng phần khác của tab ---- */
foreach ( array( 'camp-bulk', 'campDem', 'updateWidgetCodeStatus', 'admEditCampForm' ) as $__lc_cu ) {
    assert_true( strpos( $__lc_ma, $__lc_cu ) !== false, 'Chuc nang cu phai con nguyen: ' . $__lc_cu );
}
