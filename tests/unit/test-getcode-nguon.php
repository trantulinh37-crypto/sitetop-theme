<?php
/* get_code chỉ hợp lệ khi widget gọi từ WEB KHÁCH (cross-site).
   Bộ test này canh hai điều sống còn:
   - KHÔNG được chặn nhầm widget thật (cross-site) và trình duyệt cũ (thiếu header)
   - KHÔNG được chặn 'same-site' vì tên miền con của mình rơi vào đó */
if ( ! function_exists( 'sitetop_get_option' ) ) {
    function sitetop_get_option( $k, $d = null ) { return $GLOBALS['__opt'][$k] ?? $d; }
}
if ( ! function_exists( 'sitetop_getcode_sai_nguon' ) ) {
    $__src = file_get_contents( dirname(__DIR__, 2) . '/includes/shortlink-ajax.php' );
    $tk = token_get_all( $__src ); $n = count( $tk ); $__fn = null;
    for ( $i = 0; $i < $n; $i++ ) {
        if ( ! is_array( $tk[$i] ) || $tk[$i][0] !== T_FUNCTION ) continue;
        $j = $i + 1;
        while ( $j < $n && is_array( $tk[$j] ) && in_array( $tk[$j][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) $j++;
        if ( $j >= $n || ! is_array( $tk[$j] ) || $tk[$j][1] !== 'sitetop_getcode_sai_nguon' ) continue;
        $out=''; $d=0; $open=false;
        for ( $k = $i; $k < $n; $k++ ) {
            $t=$tk[$k]; $out .= is_array($t)?$t[1]:$t;
            $mo = ($t==='{')||(is_array($t)&&in_array($t[0],array(T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES),true));
            if($mo){$d++;$open=true;} elseif($t==='}'){$d--; if($open&&$d===0)break;}
        }
        $__fn=$out; break;
    }
    if ( $__fn === null ) { $GLOBALS['test_results']['failed']++; $GLOBALS['test_results']['errors'][]='Khong trich duoc sitetop_getcode_sai_nguon'; return; }
    eval( $__fn );
}
$dat = function ( $sfs, $opt = null ) {
    if ( $sfs === null ) unset( $_SERVER['HTTP_SEC_FETCH_SITE'] ); else $_SERVER['HTTP_SEC_FETCH_SITE'] = $sfs;
    $GLOBALS['__opt'] = ($opt === null) ? array() : array( 'getcode_nguon_muc' => $opt );
    return sitetop_getcode_sai_nguon();
};

// Widget THẬT trên web khách -> tuyệt đối không đụng
assert_equals( 0, $dat('cross-site', 2), 'Widget that (cross-site) -> KHONG chan' );
// Tên miền con của mình -> không đụng
assert_equals( 0, $dat('same-site', 2),  'Ten mien con (same-site) -> KHONG chan' );
// Trình duyệt cũ không gửi header -> không đụng
assert_equals( 0, $dat(null, 2),         'Thieu header (trinh duyet cu) -> KHONG chan' );
assert_equals( 0, $dat('', 2),           'Header rong -> KHONG chan' );
// Điều hướng trực tiếp (gõ URL) -> không phải widget, nhưng cũng không chặn ở đây
assert_equals( 0, $dat('none', 2),       'none -> KHONG chan' );
// Script chạy trên chính sitetop.net -> đúng đối tượng cần chặn
assert_equals( 2, $dat('same-origin', 2),   'Script tren chinh sitetop.net -> CHAN' );
assert_equals( 1, $dat('same-origin', 1),   'same-origin + muc 1 -> quan sat' );
assert_equals( 0, $dat('same-origin', 0),   'same-origin + muc 0 -> tat han' );
assert_equals( 2, $dat('same-origin', null),'Mac dinh -> chan (2)' );

unset( $_SERVER['HTTP_SEC_FETCH_SITE'] );
echo "  ✓ getcode-nguon (chi chan same-origin)\n";
