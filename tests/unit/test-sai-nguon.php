<?php
/* get_code + widget_verify_access chỉ hợp lệ khi widget gọi từ WEB KHÁCH (cross-site).
   Bộ test này canh hai điều sống còn:
   - KHÔNG được chặn nhầm widget thật (cross-site) và trình duyệt cũ (thiếu header)
   - KHÔNG được chặn 'same-site' vì tên miền con của mình rơi vào đó */
if ( ! function_exists( 'sitetop_get_option' ) ) {
    function sitetop_get_option( $k, $d = null ) { return $GLOBALS['__opt'][$k] ?? $d; }
}
if ( ! function_exists( 'sitetop_sai_nguon_muc' ) ) {
    $__src = file_get_contents( dirname(__DIR__, 2) . '/includes/shortlink-ajax.php' );
    $tk = token_get_all( $__src ); $n = count( $tk ); $__fn = null;
    for ( $i = 0; $i < $n; $i++ ) {
        if ( ! is_array( $tk[$i] ) || $tk[$i][0] !== T_FUNCTION ) continue;
        $j = $i + 1;
        while ( $j < $n && is_array( $tk[$j] ) && in_array( $tk[$j][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) $j++;
        if ( $j >= $n || ! is_array( $tk[$j] ) || $tk[$j][1] !== 'sitetop_sai_nguon_muc' ) continue;
        $out=''; $d=0; $open=false;
        for ( $k = $i; $k < $n; $k++ ) {
            $t=$tk[$k]; $out .= is_array($t)?$t[1]:$t;
            $mo = ($t==='{')||(is_array($t)&&in_array($t[0],array(T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES),true));
            if($mo){$d++;$open=true;} elseif($t==='}'){$d--; if($open&&$d===0)break;}
        }
        $__fn=$out; break;
    }
    if ( $__fn === null ) { $GLOBALS['test_results']['failed']++; $GLOBALS['test_results']['errors'][]='Khong trich duoc sitetop_sai_nguon_muc'; return; }
    eval( $__fn );
}
$dat = function ( $sfs, $opt = null ) {
    if ( $sfs === null ) unset( $_SERVER['HTTP_SEC_FETCH_SITE'] ); else $_SERVER['HTTP_SEC_FETCH_SITE'] = $sfs;
    $GLOBALS['__opt'] = ($opt === null) ? array() : array( 'sai_nguon_muc' => $opt );
    return sitetop_sai_nguon_muc();
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

/* ---- Canh ĐẤU DÂY trong mã nguồn thật ----
   Lỗi nguy hiểm nhất không phải hàm sai, mà là cắm nhầm chỗ:
   - thiếu ở 1 trong 2 cổng widget  -> công cụ vẫn moi được link hoặc mã
   - thừa ở verify_shortlink_code   -> chặn oan user thật gõ mã trên trang nhiệm vụ */
$__ma  = file_get_contents( dirname(__DIR__, 2) . '/includes/shortlink-ajax.php' );
$__than = function ( $ten ) use ( $__ma ) {
    $vt = strpos( $__ma, 'function ' . $ten . '(' );
    if ( $vt === false ) return '';
    $tk = token_get_all( '<?php ' . substr( $__ma, $vt ) );
    $out = ''; $d = 0; $open = false;
    foreach ( $tk as $t ) {
        // BỎ chú thích: tên hàm nằm trong chú thích KHÔNG tính là có đấu dây.
        // (đã dính đúng bẫy này 08/09/2026 — test xanh trong khi lớp đã bị gỡ)
        $la_chu_thich = is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true );
        if ( ! $la_chu_thich ) $out .= is_array( $t ) ? $t[1] : $t;
        $mo = ( $t === '{' ) || ( is_array( $t ) && in_array( $t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) );
        if ( $mo ) { $d++; $open = true; }
        elseif ( $t === '}' ) { $d--; if ( $open && $d === 0 ) break; }
    }
    return $out;
};
$__canh = function ( $ten, $phai_co ) use ( $__than ) {
    $than = $__than( $ten );
    // Hàm không tìm thấy = mã đã đổi, phép canh mất tác dụng -> phải BÁO LỖI, không im lặng cho qua.
    assert_true( $than !== '', 'Phai tim thay ham ' . $ten . ' de canh dau day' );
    if ( $than === '' ) return;
    $co = strpos( $than, 'sitetop_sai_nguon_muc' ) !== false;
    assert_equals( $phai_co, $co, ($phai_co ? 'PHAI cam lop nguon trong ' : 'KHONG duoc cam lop nguon trong ') . $ten );
};
// Hai cổng widget-only: bắt buộc có
$__canh( 'sitetop_ajax_get_code', true );
$__canh( 'sitetop_ajax_widget_verify_access', true );
// Ba cổng page-unlock gọi same-origin hợp lệ: cấm cắm, cắm vào là chặn oan user thật
$__canh( 'sitetop_ajax_verify_shortlink_code', false );
$__canh( 'sitetop_ajax_change_keyword', false );
$__canh( 'sitetop_ajax_check_code_ready', false );
