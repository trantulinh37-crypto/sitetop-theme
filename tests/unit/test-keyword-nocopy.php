<?php
/* Từ khoá <= 11 ký tự thì chặn copy, bắt user gõ tay vào Google (page-unlock.php:263).
   19/08/2026 — chủ site dời ngưỡng 10 -> 11: 11 ký tự gõ tay, 12 ký tự trở lên cho copy.
   Điểm dễ hỏng: đếm BYTE thay vì KÝ TỰ. Tiếng Việt có dấu tốn 2-3 byte mỗi chữ, đếm byte
   là từ khoá ngắn bị coi như dài rồi cho copy — đúng cái luật này muốn chặn. */
$kw_len = function ( $kw ) {
    return function_exists( 'mb_strlen' ) ? mb_strlen( $kw, 'UTF-8' ) : preg_match_all( '/./u', $kw );
};
$nocopy = function ( $kw ) use ( $kw_len ) { return $kw_len( $kw ) <= 11; };

// Ranh giới 11 / 12
assert_true(  $nocopy( '12345678901' ),  '11 ky tu -> bat go tay' );
assert_false( $nocopy( '123456789012' ), '12 ky tu -> cho copy' );
assert_true(  $nocopy( '1234567890' ),   '10 ky tu -> van bat go tay' );
assert_true(  $nocopy( 'seo' ),          'Tu khoa rat ngan -> bat go tay' );

// Tiếng Việt có dấu: phải đếm ký tự, không đếm byte
assert_equals( 8,  $kw_len( 'cửa cuốn' ),   '"cua cuon" = 8 ky tu (12 byte)' );
assert_equals( 12, strlen( 'cửa cuốn' ),    '... dem byte ra 12 - KHONG duoc dung so nay' );
assert_true(  $nocopy( 'cửa cuốn' ),        'Tu khoa Viet 8 ky tu -> bat go tay' );
assert_true(  $nocopy( 'nệm cao su' ),      'Tu khoa Viet 10 ky tu -> bat go tay' );
assert_true(  $nocopy( 'cửa cuốn vn' ),     'Tu khoa Viet 11 ky tu -> bat go tay' );
assert_false( $nocopy( 'cửa cuốn vip' ),    'Tu khoa Viet 12 ky tu -> cho copy' );
assert_false( $nocopy( 'nệm cao su Hà Nội' ), 'Tu khoa Viet dai -> cho copy' );

// Nếu lỡ đếm byte thì luật đảo ngược — chốt lại để không ai đổi nhầm
assert_true( strlen( 'nệm cao su' ) > 11, 'Dem byte se cho copy nham tu khoa 10 ky tu' );

// Nhánh dự phòng khi thiếu mbstring phải cho cùng kết quả
foreach ( array( 'seo', 'cửa cuốn', 'cửa cuốn vn', 'cửa cuốn vip', 'nệm cao su Hà Nội' ) as $k ) {
    assert_equals( mb_strlen( $k, 'UTF-8' ), preg_match_all( '/./u', $k ),
        'Nhanh du phong khop mb_strlen: ' . $k );
}
/* CỠ CHỮ Ô TỪ KHOÁ (chủ site chốt 21/09/2026): bằng ô URL camp Direct — 28px máy tính,
   18px điện thoại. Và KHÔNG cắt bằng ba chấm: user phải đọc hết từ khoá mới gõ đúng vào
   Google. Chốt lại vì cỡ chữ rất dễ bị kéo về cũ mà nhìn qua không ai để ý. */
$__pu2 = file_get_contents( dirname( __DIR__, 2 ) . '/page-unlock.php' );
assert_true( strpos( $__pu2, '.g-mock-typed{flex:1;min-width:0;font-size:28px;line-height:1.35' ) !== false,
    'May tinh: chu tu khoa 28px' );
assert_true( strpos( $__pu2, '.g-mock-typed{font-size:18px}' ) !== false,
    'Dien thoai: chu tu khoa 18px' );
assert_true( strpos( $__pu2, 'white-space:normal;word-break:break-word' ) !== false,
    'Tu khoa dai xuong dong du chu, KHONG cat bang ba cham' );
assert_false( strpos( $__pu2, 'font-size:12.5px;color:#202124;font-weight:600;overflow:hidden' ) !== false,
    'Khong con co chu tu khoa cu 12.5px' );

/* .net GIỮ dòng nhắc "Vui lòng gõ tay" khi bật gõ tay (chủ site chốt 21/09/2026) — đây là
   chỗ .net CỐ Ý KHÁC .one (bên đó đã bỏ). Chốt lại để lần đồng bộ sau không xoá nhầm. */
assert_equals( 2, substr_count( $__pu2, '<div class="g-mock-hint">Vui lòng gõ tay</div>' ),
    'Giu dong nhac duoi o tu khoa o CA HAI nhanh camp tu khoa' );
assert_true( strpos( $__pu2, '.g-mock-hint{margin-top:7px' ) !== false, 'Giu CSS cua dong nhac' );
assert_true( strpos( $__pu2, "\$url_nocopy ? '<div class=\"g-mock-hint\" style=\"text-align:center\">Vui lòng gõ tay</div>' : ''" ) !== false,
    'Camp Direct bat go tay: cung hien dong nhac do' );
assert_equals( 2, substr_count( $__pu2, '<?php echo $sitetop_nhac_go_tay; ?>' ), 'Dong nhac echo o ca hai nhanh Direct' );
assert_true( strpos( $__pu2, "'Vui lòng gõ tay URL vào trình duyệt' : 'Vui lòng gõ tay vào Google'" ) !== false,
    'Van giu loi nhac khi user THAT SU thu copy (toast), voi 2 ban: URL va Google' );

echo "  ✓ keyword nocopy\n";
