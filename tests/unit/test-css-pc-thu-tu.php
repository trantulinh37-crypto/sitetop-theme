<?php
/* THỨ TỰ CSS BẢN PC TRANG NHIỆM VỤ (14/10/2026). Khối @media(min-width:1100px) từng nằm ở ĐẦU stylesheet nên mọi
   khai báo trùng thuộc tính với luật gốc phía sau (cùng độ ưu tiên) đều thua — đo trên trình duyệt chỉ .container và cỡ
   chữ chip ăn, còn chữ các bước / tiêu đề / ô Google… vẫn cỡ gốc trong khi mã "trông như" đã phóng to.
   Test: với MỖI bộ chọn trong khối PC, luật gốc cùng bộ chọn (ngoài mọi @media) phải nằm TRƯỚC khối PC. */

$__ct_ma  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/page-unlock.php' );
$__ct_a   = strpos( $__ct_ma, '@media(min-width:1100px){' );
assert_true( $__ct_a !== false, 'Co khoi @media(min-width:1100px) trong page-unlock.php' );
if ( $__ct_a !== false ) {
    // cắt thân khối media theo cặp ngoặc
    $__ct_d = 0; $__ct_e = $__ct_a;
    for ( $__ct_i = strpos( $__ct_ma, '{', $__ct_a ); $__ct_i < strlen( $__ct_ma ); $__ct_i++ ) {
        if ( $__ct_ma[ $__ct_i ] === '{' ) $__ct_d++;
        elseif ( $__ct_ma[ $__ct_i ] === '}' ) { $__ct_d--; if ( $__ct_d === 0 ) { $__ct_e = $__ct_i; break; } }
    }
    $__ct_than = substr( $__ct_ma, strpos( $__ct_ma, '{', $__ct_a ) + 1, $__ct_e - strpos( $__ct_ma, '{', $__ct_a ) - 1 );
    preg_match_all( '/([^{}]+)\{[^{}]*\}/', preg_replace( '#/\*.*?\*/#s', '', $__ct_than ), $__ct_m );
    $__ct_sel = array_values( array_unique( array_map( 'trim', $__ct_m[1] ) ) );
    assert_true( count( $__ct_sel ) >= 3, 'Khoi PC co it nhat 3 bo chon. Ra: ' . json_encode( $__ct_sel ) );
    assert_true( in_array( '.step-content p', $__ct_sel, true ) && in_array( '.step-num', $__ct_sel, true ), 'Khoi PC co chu cac buoc' );
    assert_true( strpos( $__ct_than, '.step-content p{font-size:20px}' ) !== false && strpos( $__ct_than, '.step-num{font-size:20px}' ) !== false,
        'Chu cac buoc ban PC = 20px (goc 15px + 5px, chu site 14/10)' );
    foreach ( $__ct_sel as $__ct_s ) {
        // luật gốc cùng bộ chọn nằm SAU khối PC (bất kể ở đâu sau đó) là lỗi thứ tự
        $__ct_sau = strpos( $__ct_ma, $__ct_s . '{', $__ct_e );
        assert_true( $__ct_sau === false, 'Luat goc "' . $__ct_s . '{" KHONG duoc nam sau khoi PC (se de mat luat PC)' );
    }
}
