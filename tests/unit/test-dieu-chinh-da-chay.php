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
assert_true( strpos( $__tab, 'function admSuaChay(' ) !== false && strpos( $__tab, 'function admSuaHomNay(' ) !== false && strpos( $__tab, 'admSuaDaily' ) === false, 'Nut sua Da chay + view hom nay; khong con nut sua max/ngay' );
assert_true( strpos( $__tab, "fd.append('req'," ) !== false, 'Moi lan Luu gui ma req rieng' );
// VIEW HÔM NAY điều chỉnh phải được cộng vào MỌI chỗ đếm view hôm nay — nhất là các chốt hạn mức ngày.
$__v = (string) file_get_contents( $__d . '/includes/shortlink-verification.php' );
$__x = strpos( $__v, 'sitetop_dc_hn_cua_camp( $visit->camp_id, $today )' ); $__y = strpos( $__v, 'if ( $daily_completed >= $visit->daily_traffic )' );
assert_true( $__x !== false && $__y !== false && $__x < $__y, 'Chot han muc ngay luc tra tien tinh ca view hom nay dieu chinh' );
$__ds = (string) file_get_contents( $__d . '/includes/shortlink-distribution.php' );
assert_true( strpos( $__ds, "\$today_done = max( 0, \$today_done + (int) ( \$dc_hn[ (int) \$c->id ] ?? 0 ) );\n        if ( \$today_done >= \$daily_limit ) continue;" ) !== false, 'Vong chon camp tinh view hom nay dieu chinh' );
assert_true( strpos( $__ds, "\$done = max( 0, \$done + (int) ( \$dc_hn[ (int) \$c->id ] ?? 0 ) );" ) !== false, 'Chia deu theo gio tinh view hom nay dieu chinh' );
assert_true( strpos( (string) file_get_contents( $__d . '/includes/cau-noi-net-one.php' ), "sitetop_dc_hn_cua_camp( (int) \$camp_id, \$ngay )" ) !== false, 'So lieu gui .one tinh view hom nay dieu chinh' );
assert_true( strpos( $__m, "\$hom_nay = ! empty( \$_POST['hom_nay'] );" ) !== false && strpos( $__m, "'View hôm nay không thể âm'" ) !== false, 'Che do sua view hom nay' );
// BIỂU ĐỒ 30 NGÀY phía khách: mọi lần sửa ghi phần chênh theo ngày sửa, biểu đồ cộng vào đường Traffic.
assert_true( strpos( $__m, "'sitetop_dc_bd_' . \$id . '_'" ) !== false, 'Moi lan sua ghi phan chenh theo ngay cho bieu do' );
assert_true( strpos( $__m, "delete_transient( 'sitetop_sl_kh_bd_' . \$kh" ) !== false, 'Xoa bo dem bieu do sau khi sua' );
$__bd = (string) file_get_contents( $__d . ( file_exists( $__d . '/includes/admin-dashboard.php' ) && strpos( (string) file_get_contents( $__d . '/includes/admin-dashboard.php' ), 'function sitetop_bieu_do_khach' ) !== false ? '/includes/admin-dashboard.php' : '/page-customer-dashboard.php' ) );
assert_true( strpos( $__bd, 'sitetop_dc_bd_cua_khach(' ) !== false, 'Bieu do 30 ngay cong luot tang/giam' );
