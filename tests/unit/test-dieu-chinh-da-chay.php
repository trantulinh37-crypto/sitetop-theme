<?php
/* ADMIN SỬA ĐÃ CHẠY → KHÁCH ĐỒNG BỘ THEO PHẦN CHÊNH (15/11/2026). Canh các chốt tiền: giao dịch + khoá dòng,
   chống gửi trùng, không hoàn quá số đã trừ, không sửa camp gương, ẩn khỏi lịch sử khách, và MỌI công thức
   "đã chi" dùng tổng RÒNG (dòng hoàn dương không được bị bỏ qua ở chỗ nào). */
$__d = dirname( __DIR__, 2 );
$__m = (string) file_get_contents( $__d . '/includes/dieu-chinh-da-chay.php' );
foreach ( array(
    "START TRANSACTION"                                   => 'Chay trong giao dich',
    "WHERE id = %d FOR UPDATE"                            => 'Khoa dong camp',
    "customer_balance WHERE user_id = %d FOR UPDATE"      => 'Khoa dong so du',
    "'sitetop_dc_req_' . md5( \$req )"                    => 'Chong gui trung theo ma req',
    "\$delta = \$moi - \$cu;"                             => 'Tinh theo PHAN CHENH so voi so admin dang thay',
    "if ( \$tien > 0 && \$so_du < \$tien )"               => 'Khong tru qua so du',
    "if ( -\$tien > \$da_tru + 0.5 )"                     => 'Khong hoan qua so camp da thuc tru',
    "sitetop_dc_chay_la_camp_guong( \$c )"                => 'Chan camp guong (.one nhan tu .net)',
    "'reference_type' => 'dieu_chinh_chay'"               => 'Dong so ghi danh dau dieu chinh',
    "current_user_can( 'manage_options' )"                => 'Chi admin',
    "check_ajax_referer( 'sitetop_admin_nonce', 'nonce' )"=> 'Kiem nonce',
) as $__k => $__t ) assert_true( strpos( $__m, $__k ) !== false, $__t );
assert_true( strpos( (string) file_get_contents( $__d . '/functions.php' ), "'dieu-chinh-da-chay'," ) !== false, 'Module duoc nap' );
// Không còn công thức "chỉ cộng dòng âm" của campaign_view ở bất kỳ file nào (sẽ bỏ sót dòng hoàn).
$__sot = array();
foreach ( array_merge( glob( $__d . '/*.php' ), glob( $__d . '/includes/*.php' ), glob( $__d . '/includes/admin/tabs/*.php' ) ) as $__f ) {
    if ( preg_match( "/type\s*=\s*'campaign_view'\s+AND\s+amount\s*</", (string) file_get_contents( $__f ) ) ) $__sot[] = basename( $__f );
}
assert_equals( array(), $__sot, 'Moi cong thuc da chi campaign_view tinh RONG' );
// Ẩn dòng điều chỉnh khỏi lịch sử giao dịch khách.
foreach ( array( '/page-customer-dashboard.php', '/includes/customer-load-more.php' ) as $__f ) {
    $__s = (string) file_get_contents( $__d . $__f );
    if ( preg_match_all( '/SELECT \* FROM \{\$prefix\}customer_transactions WHERE[^"]*"/', $__s, $__mm ) )
        foreach ( $__mm[0] as $__q ) assert_true( strpos( $__q, "reference_type <> 'dieu_chinh_chay'" ) !== false, 'An dong dieu chinh: ' . $__f );
}
// Phía khách cộng phần lượt điều chỉnh: Tổng views + Đã chạy từng camp + chi tiết camp.
assert_true( strpos( (string) file_get_contents( $__d . '/page-customer-dashboard.php' ), 'sitetop_dc_chay_cua_khach( $user_id )' ) !== false, 'Tong views + dieu chinh' );
assert_true( strpos( (string) file_get_contents( $__d . '/includes/customer-campaign-ajax.php' ), 'sitetop_dc_chay_cua_camp_nhieu( array( $campaign_id ) )' ) !== false, 'Chi tiet camp + dieu chinh' );
$__tab = (string) file_get_contents( $__d . '/includes/admin/tabs/tab-campaigns.php' );
assert_true( strpos( $__tab, 'function admSuaChay(' ) !== false && strpos( $__tab, 'function admSuaDaily(' ) !== false, 'Nut sua tai cho' );
assert_true( strpos( $__tab, "fd.append('req'," ) !== false, 'Moi lan Luu gui ma req rieng' );
