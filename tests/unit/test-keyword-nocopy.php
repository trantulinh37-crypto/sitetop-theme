<?php
/* Lịch sử: 19/08/2026 từ khoá <= 11 ký tự tự bắt gõ tay. 06/10/2026 chủ site chốt lại: bắt gõ tay CHỈ theo
   nút gạt của camp (kw_bat_go_tay) — luật độ dài đã bỏ (xem test-kw-bat-go-tay.php). Phần đếm KÝ TỰ (không
   đếm byte) giữ lại vì page-unlock.php vẫn đếm để chẩn đoán. */
$kw_len = function ( $kw ) {
    return function_exists( 'mb_strlen' ) ? mb_strlen( $kw, 'UTF-8' ) : preg_match_all( '/./u', $kw );
};
assert_equals( 8,  $kw_len( 'cửa cuốn' ),   '"cua cuon" = 8 ky tu (12 byte)' );
assert_equals( 12, strlen( 'cửa cuốn' ),    '... dem byte ra 12 - KHONG duoc dung so nay' );

// Nhánh dự phòng khi thiếu mbstring phải cho cùng kết quả
foreach ( array( 'seo', 'cửa cuốn', 'cửa cuốn vn', 'cửa cuốn vip', 'nệm cao su Hà Nội' ) as $k ) {
    assert_equals( mb_strlen( $k, 'UTF-8' ), preg_match_all( '/./u', $k ),
        'Nhanh du phong khop mb_strlen: ' . $k );
}
/* CỠ CHỮ Ô TỪ KHOÁ: 21/09/2026 chủ site chốt 28px máy tính / 18px điện thoại; 14/10/2026 chủ site
   đổi theo mẫu thanh tìm kiếm Google thật và "giảm nhỏ text từ khoá": 20px cơ bản, 16px điện thoại
   (luật PC 28px từng có nhưng đặt trước luật gốc nên chưa bao giờ ăn — đã gỡ). Và KHÔNG cắt bằng ba chấm: user phải đọc hết từ khoá mới gõ đúng vào
   Google. Chốt lại vì cỡ chữ rất dễ bị kéo về cũ mà nhìn qua không ai để ý. */
$__pu2 = file_get_contents( dirname( __DIR__, 2 ) . '/page-unlock.php' );
assert_true( strpos( $__pu2, '.g-mock-typed{flex:1;min-width:0;font-size:20px;line-height:1.35' ) !== false,
    'Co ban: chu tu khoa 20px (14/10/2026 giam nho)' );
assert_true( strpos( $__pu2, '.g-mock-typed{font-size:16px}' ) !== false,
    'Dien thoai: chu tu khoa 16px' );
assert_true( strpos( $__pu2, 'white-space:normal;word-break:break-word' ) !== false,
    'Tu khoa dai xuong dong du chu, KHONG cat bang ba cham' );
assert_false( strpos( $__pu2, 'font-size:12.5px;color:#202124;font-weight:600;overflow:hidden' ) !== false,
    'Khong con co chu tu khoa cu 12.5px' );

/* 14/10/2026 chủ site: bật gõ tay KHÔNG hiện dòng chữ "Vui lòng gõ tay" nữa (ô từ khoá lẫn ô URL Direct). */
assert_equals( 0, substr_count( $__pu2, 'g-mock-hint' ), 'Khong con dong nhac "Vui long go tay" (HTML lan CSS)' );
assert_equals( 0, substr_count( $__pu2, '$sitetop_nhac_go_tay' ), 'Khong con bien dong nhac cua camp Direct' );
assert_true( strpos( $__pu2, "'Vui lòng gõ tay URL vào trình duyệt' : 'Vui lòng gõ tay vào Google'" ) !== false,
    'Van giu loi nhac khi user THAT SU thu copy (toast), voi 2 ban: URL va Google' );

echo "  ✓ keyword nocopy\n";
