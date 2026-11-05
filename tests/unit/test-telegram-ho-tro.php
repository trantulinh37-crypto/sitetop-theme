<?php
/* Ô "Telegram Hỗ trợ (Admin)" (05/11/2026): đổi một chỗ là mọi nút Hỗ trợ / lời nhắc theo.
   Chạy THẬT sitetop_source_telegram() trong tiến trình riêng với option giả. */
$__tg_goc = dirname( __DIR__, 2 );
$__tg_src = (string) file_get_contents( $__tg_goc . '/includes/source-approval.php' );
$__tg_a = strpos( $__tg_src, 'function sitetop_source_telegram() {' );
$__tg_b = $__tg_a === false ? false : strpos( $__tg_src, "\n}\n", $__tg_a );
assert_true( $__tg_a !== false && $__tg_b !== false, 'Co ham sitetop_source_telegram' );
$__tg_ham = substr( $__tg_src, $__tg_a, $__tg_b - $__tg_a + 2 );
foreach ( array(
    '@moiadmin' => 'moiadmin', 'moiadmin' => 'moiadmin', 'https://t.me/moiadmin' => 'moiadmin',
    't.me/moiadmin/' => 'moiadmin', ' @moiadmin ' => 'moiadmin', '' => 'sitetopnet',
) as $__tg_vao => $__tg_ra ) {
    $f = sys_get_temp_dir() . '/st-tg-' . getmypid() . '-' . mt_rand() . '.php';
    file_put_contents( $f, '<?php function sitetop_get_option($k,$d=""){ return ' . var_export( $__tg_vao, true ) . '; } ' . $__tg_ham . ' echo sitetop_source_telegram();' );
    $r = (string) shell_exec( 'php ' . escapeshellarg( $f ) . ' 2>&1' ); @unlink( $f );
    assert_equals( $__tg_ra, $r, 'O cai dat "' . $__tg_vao . '" → username ' . $__tg_ra );
}
$__tg_cd = (string) file_get_contents( $__tg_goc . '/includes/admin/tabs/tab-settings.php' );
assert_equals( 1, substr_count( $__tg_cd, 'name="source_telegram"' ), 'Tab Cai dat co dung mot o Telegram Ho tro' );
assert_true( strpos( $__tg_cd, "'source_telegram'" ) !== false, 'O Telegram Ho tro duoc luu' );
$__tg_u = (string) file_get_contents( $__tg_goc . '/page-unlock.php' );
assert_true( strpos( $__tg_u, 'https://t.me/sitetopnet' ) === false && strpos( $__tg_u, 'sitetop_source_telegram()' ) !== false, 'Trang nhiem vu khong con ghi cung username' );
$__tg_x = (string) file_get_contents( $__tg_goc . '/includes/trang-xac-minh.php' );
assert_true( strpos( $__tg_x, 'https://t.me/<?php echo esc_attr( $xm_tg ); ?>" target="_blank" rel="noopener">Hỗ trợ</a>' ) !== false, 'Nut Ho tro trang xac minh lay tu o cai dat' );
