<?php
/* ICON NÚT LẤY MÃ ĐÚNG CỠ THEO DPR (14/10/2026, chủ site: "hãy thật nét… logo khi cài web khách phải nét").
   Nút tròn 50px: trình duyệt thu ảnh 256px về 50/100/150px theo tỷ lệ lẻ nên viền và dấu ST mềm đi. Nay theme
   có sẵn sitetop-logo-50/100/150/200.png và widget gắn srcset 1x–4x khi icon là ảnh mặc định của theme.
   Chạy CHÍNH sitetop_widget_icon_srcset() (trích từ functions.php) và CHÍNH câu dựng thẻ <img> của widget bằng node. */

$__is_goc = dirname( __DIR__, 2 );
$__is_ham = function ( $ma, $ten ) {
    $vt = strpos( $ma, 'function ' . $ten . '(' );
    if ( $vt === false ) return '';
    $tk = token_get_all( '<?php ' . substr( $ma, $vt ) );
    $out = ''; $d = 0; $open = false; $dau = true;
    foreach ( $tk as $t ) {
        if ( $dau ) { $dau = false; continue; }
        $out .= is_array( $t ) ? $t[1] : $t;
        $mo = ( $t === '{' ) || ( is_array( $t ) && in_array( $t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) );
        if ( $mo ) { $d++; $open = true; }
        elseif ( $t === '}' ) { $d--; if ( $open && $d === 0 ) break; }
    }
    return $out;
};
$__is_src = $__is_ham( (string) file_get_contents( $__is_goc . '/functions.php' ), 'sitetop_widget_icon_srcset' );
assert_true( $__is_src !== '', 'Phai trich duoc sitetop_widget_icon_srcset' );

// 4 file ảnh đúng cỡ phải có thật trong theme, đúng kích thước
foreach ( array( 50, 100, 150, 200 ) as $__is_px ) {
    $__is_f = $__is_goc . '/assets/img/sitetop-logo-' . $__is_px . '.png';
    $__is_kt = file_exists( $__is_f ) ? getimagesize( $__is_f ) : false;
    assert_true( $__is_kt && $__is_kt[0] === $__is_px && $__is_kt[1] === $__is_px, "Co file sitetop-logo-{$__is_px}.png dung {$__is_px}x{$__is_px}" );
}
$__is_kt = getimagesize( $__is_goc . '/assets/img/sitetop-logo.png' );
assert_true( $__is_kt && $__is_kt[0] === 256 && $__is_kt[1] === 256, 'Anh chinh sitetop-logo.png van 256x256' );

// Chạy hàm thật trong tiến trình con với thư mục ảnh giả (để thử trường hợp thiếu file)
$__is_chay = function ( $url, $thieu = false ) use ( $__is_src ) {
    $tmp = sys_get_temp_dir() . '/st-is-' . getmypid() . '-' . ( $thieu ? 't' : 'd' );
    @mkdir( $tmp . '/assets/img', 0777, true );
    foreach ( array( 50, 100, 150, 200 ) as $px ) { if ( $thieu && $px === 150 ) continue; file_put_contents( $tmp . '/assets/img/sitetop-logo-' . $px . '.png', 'x' ); }
    $ma = "define('SITETOP_URL','https://sitetop.net/wp-content/themes/sitetop-theme'); define('SITETOP_DIR'," . var_export( $tmp, true ) . ");\n"
        . "function sitetop_logo_url(\$f){ return SITETOP_URL . '/assets/img/' . \$f . '?v=' . strlen(\$f); }\n" . $__is_src . "\necho json_encode(sitetop_widget_icon_srcset(" . var_export( $url, true ) . "));";
    $p = proc_open( array( PHP_BINARY, '-d', 'display_errors=stderr', '-r', $ma ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $ong );
    $out = stream_get_contents( $ong[1] ); $err = stream_get_contents( $ong[2] ); fclose( $ong[1] ); fclose( $ong[2] ); proc_close( $p );
    foreach ( glob( $tmp . '/assets/img/*' ) as $f ) @unlink( $f ); @rmdir( $tmp . '/assets/img' ); @rmdir( $tmp . '/assets' ); @rmdir( $tmp );
    return array( json_decode( $out, true ), $err );
};
list( $__is_k, $__is_e ) = $__is_chay( 'https://sitetop.net/wp-content/themes/sitetop-theme/assets/img/sitetop-logo.png?v=1791969032' );
$__is_mong = array();
foreach ( array( 50 => '1x', 100 => '2x', 150 => '3x', 200 => '4x' ) as $__is_px => $__is_dpr ) { $__is_file = 'sitetop-logo-' . $__is_px . '.png'; $__is_mong[] = 'https://sitetop.net/wp-content/themes/sitetop-theme/assets/img/' . $__is_file . '?v=' . strlen( $__is_file ) . ' ' . $__is_dpr; }
assert_equals( implode( ', ', $__is_mong ), $__is_k, 'Icon mac dinh cua theme (co ?v=) → srcset 4 muc 1x..4x theo thu tu, URL qua sitetop_logo_url (tu doi ?v= theo file). Ra: ' . json_encode( $__is_k ) . ' stderr: ' . $__is_e );
list( $__is_k, $__is_e ) = $__is_chay( 'https://i.ibb.co/abc/logo-khach.png' );
assert_equals( '', $__is_k, 'Admin dat Icon URL khac → rong, widget dung src nhu cu' );
list( $__is_k, $__is_e ) = $__is_chay( '' );
assert_equals( '', $__is_k, 'Khong co icon → rong' );
list( $__is_k, $__is_e ) = $__is_chay( 'https://sitetop.net/wp-content/themes/sitetop-theme/assets/img/sitetop-logo.png', true );
assert_equals( '', $__is_k, 'Thieu mot file co → rong (khong gui srcset nua nua)' );

// Câu dựng <img> trong widget: chạy thật bằng node với C giả
$__is_w = (string) file_get_contents( $__is_goc . '/widget.js.php' );
$__is_dong = '';
foreach ( explode( "\n", $__is_w ) as $__is_l ) { if ( strpos( $__is_l, 'var iconHtml=C.icon?' ) !== false ) { $__is_dong = trim( $__is_l ); break; } }
assert_true( $__is_dong !== '' && substr( $__is_dong, -1 ) === ';', 'Tim thay cau var iconHtml=... (mot dong, ket thuc ;)' );
$__is_js = "function chay(C){ " . $__is_dong . " return iconHtml; }\n"
    . "console.log(JSON.stringify({ co: chay({icon:'https://a/b.png?v=1', iconSet:'https://a/b-50.png?v=2 1x, https://a/b-100.png?v=3 2x'}), khong: chay({icon:'https://a/b.png?v=1', iconSet:''}), mac_dinh: chay({icon:'', iconSet:''}) }));";
$__is_f = sys_get_temp_dir() . '/st-is-' . getmypid() . '.js';
file_put_contents( $__is_f, $__is_js );
$__is_r = (string) shell_exec( 'node ' . escapeshellarg( $__is_f ) . ' 2>&1' ); @unlink( $__is_f );
$__is_j = json_decode( trim( $__is_r ), true );
assert_true( is_array( $__is_j ), 'Chay duoc cau dung iconHtml bang node. Ra: ' . substr( $__is_r, 0, 300 ) );
if ( is_array( $__is_j ) ) {
    assert_equals( '<img src="https://a/b.png?v=1" srcset="https://a/b-50.png?v=2 1x, https://a/b-100.png?v=3 2x" style="width:16px;height:16px">', $__is_j['co'], 'Co iconSet → the img co srcset dung chuoi' );
    assert_equals( '<img src="https://a/b.png?v=1" style="width:16px;height:16px">', $__is_j['khong'], 'Khong iconSet → the img nhu cu (khong srcset)' );
    assert_true( strpos( $__is_j['mac_dinh'], '<svg' ) === 0, 'Khong icon → icon khoa SVG mac dinh' );
}
