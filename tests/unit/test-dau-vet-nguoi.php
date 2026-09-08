<?php
/* Lớp "dấu vết người thật": chạy HÀM THẬT sitetop_nguoithat_muc() với nhiều bộ $_POST.
   Nguyên tắc sống còn: chỉ kết luận khi CẢ HAI cờ = 0, và bỏ qua hoàn toàn khi widget
   chưa gửi wv — thiếu điều đó là chặn oan web khách chưa cập nhật widget. */
if ( ! function_exists( 'sitetop_get_option' ) ) {
    function sitetop_get_option( $k, $d = null ) { return $GLOBALS['__opt'][$k] ?? $d; }
}
if ( ! function_exists( 'sitetop_nguoithat_muc' ) ) {
    $__src = file_get_contents( dirname(__DIR__, 2) . '/includes/shortlink-ajax.php' );
    $tk = token_get_all( $__src ); $n = count( $tk ); $__fn = null;
    for ( $i = 0; $i < $n; $i++ ) {
        if ( ! is_array( $tk[$i] ) || $tk[$i][0] !== T_FUNCTION ) continue;
        $j = $i + 1;
        while ( $j < $n && is_array( $tk[$j] ) && in_array( $tk[$j][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) $j++;
        if ( $j >= $n || ! is_array( $tk[$j] ) || $tk[$j][1] !== 'sitetop_nguoithat_muc' ) continue;
        $out=''; $d=0; $open=false;
        for ( $k = $i; $k < $n; $k++ ) {
            $t=$tk[$k]; $out .= is_array($t)?$t[1]:$t;
            $mo = ($t==='{')||(is_array($t)&&in_array($t[0],array(T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES),true));
            if($mo){$d++;$open=true;} elseif($t==='}'){$d--; if($open&&$d===0)break;}
        }
        $__fn=$out; break;
    }
    if ( $__fn === null ) { $GLOBALS['test_results']['failed']++; $GLOBALS['test_results']['errors'][]='Khong trich duoc sitetop_nguoithat_muc'; return; }
    eval( $__fn );
}

$dat = function ( $post, $opt = null ) {
    $_POST = $post;
    $GLOBALS['__opt'] = ($opt === null) ? array() : array( 'nguoithat_muc' => $opt );
    return sitetop_nguoithat_muc();
};

// Widget CŨ (không gửi wv) -> tuyệt đối bỏ qua, dù thiếu mọi cờ
assert_equals( 0, $dat( array(), 2 ),                                  'Widget cu (thieu wv) -> bo qua du muc 2' );
assert_equals( 0, $dat( array('bam'=>0,'tt'=>0), 2 ),                  'Thieu wv + hai co = 0 -> van bo qua' );

// Người thật: có bấm HOẶC có tương tác -> không bao giờ bị nghi
assert_equals( 0, $dat( array('wv'=>2,'bam'=>1,'tt'=>0), 2 ),          'Co bam that -> khong nghi' );
assert_equals( 0, $dat( array('wv'=>2,'bam'=>0,'tt'=>5), 2 ),          'Co tuong tac -> khong nghi' );
assert_equals( 0, $dat( array('wv'=>2,'bam'=>1,'tt'=>9), 2 ),          'Co ca hai -> khong nghi' );

// Công cụ tab nền: cả hai đều 0
assert_equals( 1, $dat( array('wv'=>2,'bam'=>0,'tt'=>0), 1 ),          'Ca hai = 0 + muc 1 -> quan sat' );
assert_equals( 2, $dat( array('wv'=>2,'bam'=>0,'tt'=>0), 2 ),          'Ca hai = 0 + muc 2 -> chan' );
assert_equals( 0, $dat( array('wv'=>2,'bam'=>0,'tt'=>0), 0 ),          'Ca hai = 0 + muc 0 -> tat han' );
assert_equals( 1, $dat( array('wv'=>2,'bam'=>0,'tt'=>0), null ),       'Mac dinh khong cau hinh -> quan sat (1)' );

$_POST = array();
echo "  ✓ dau-vet-nguoi (bam that + tuong tac that)\n";
