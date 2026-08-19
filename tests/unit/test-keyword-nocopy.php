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
echo "  ✓ keyword nocopy\n";
