<?php
/* Tách rổ hạn mức — canh đúng lỗi đã ăn mất nhiệm vụ của user thật (09/09/2026).
   Trang nhiệm vụ tự gọi check_code_ready 30 lượt/phút + unlock_heartbeat 12 lượt/phút =
   42, trong khi rổ chung shortlink_click chỉ cho 30. Rổ cạn thì task_handoff — dấu chứng
   minh user ĐÃ đi qua link nhiệm vụ — bị từ chối, máy chủ tưởng user vào thẳng trang đích
   và chặn nhiệm vụ. Ai gộp lại là tái hiện đúng lỗi đó. */
$__goc = dirname( __DIR__, 2 );
$__ip  = file_get_contents( $__goc . '/includes/shortlink-ip.php' );
$__aj  = file_get_contents( $__goc . '/includes/shortlink-ajax.php' );

$__than = function ( $ma, $ten ) {
    $vt = strpos( $ma, 'function ' . $ten . '(' );
    if ( $vt === false ) return '';
    $tk = token_get_all( '<?php ' . substr( $ma, $vt ) );
    $out = ''; $d = 0; $open = false;
    foreach ( $tk as $t ) {
        $bo = is_array( $t ) && in_array( $t[0], array( T_OPEN_TAG, T_COMMENT, T_DOC_COMMENT ), true );
        if ( ! $bo ) $out .= is_array( $t ) ? $t[1] : $t;
        $mo = ( $t === '{' ) || ( is_array( $t ) && in_array( $t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) );
        if ( $mo ) { $d++; $open = true; }
        elseif ( $t === '}' ) { $d--; if ( $open && $d === 0 ) break; }
    }
    return $out;
};

/* --- Ba rổ riêng phải TỒN TẠI trong bảng, không được rơi về 'default' lặng lẽ --- */
foreach ( array( 'check_code_ready', 'unlock_hb', 'task_handoff' ) as $__ro ) {
    assert_true( strpos( $__ip, "'" . $__ro . "'" ) !== false,
        "Ro '" . $__ro . "' PHAI co trong bang han muc" );
}

/* --- Rổ cho hai cổng POLL phải RỘNG HƠN nhịp gọi thật của chúng ---
   check_code_ready poll 2 giây/lần = 30 lượt/phút; unlock_heartbeat 5 giây/lần = 12. */
if ( preg_match( "/'check_code_ready'\s*=>\s*array\(\s*'max'\s*=>\s*(\d+),\s*'window'\s*=>\s*(\d+)/", $__ip, $m ) ) {
    $__phut = (int) $m[1] * 60 / max( 1, (int) $m[2] );
    assert_true( $__phut > 30, 'Ro check_code_ready phai rong hon 30 luot/phut (nhip poll that), dang la ' . $__phut );
} else { assert_true( false, 'Khong doc duoc cau hinh ro check_code_ready' ); }
if ( preg_match( "/'unlock_hb'\s*=>\s*array\(\s*'max'\s*=>\s*(\d+),\s*'window'\s*=>\s*(\d+)/", $__ip, $m2 ) ) {
    $__phut2 = (int) $m2[1] * 60 / max( 1, (int) $m2[2] );
    assert_true( $__phut2 > 12, 'Ro unlock_hb phai rong hon 12 luot/phut, dang la ' . $__phut2 );
} else { assert_true( false, 'Khong doc duoc cau hinh ro unlock_hb' ); }

/* --- Ba cổng phải dùng ĐÚNG rổ riêng, TUYỆT ĐỐI không quay về rổ chung --- */
$__cap = array(
    'sitetop_ajax_check_code_ready' => 'check_code_ready',
    'sitetop_ajax_unlock_heartbeat' => 'unlock_hb',
    'sitetop_ajax_task_handoff'     => 'task_handoff',
);
foreach ( $__cap as $__ham => $__ro ) {
    $__t = $__than( $__aj, $__ham );
    assert_true( $__t !== '', 'Phai tim thay ' . $__ham );
    if ( $__t === '' ) continue;
    assert_true( strpos( $__t, "sitetop_rate_limit_check('" . $__ro . "')" ) !== false,
        $__ham . " PHAI dung ro rieng '" . $__ro . "'" );
    assert_true( strpos( $__t, "sitetop_rate_limit_check('shortlink_click')" ) === false,
        $__ham . ' TUYET DOI khong duoc quay lai ro chung shortlink_click (tai hien loi an mat nhiem vu)' );
}

/* --- Nới chốt bàn giao: phải có cửa sổ thời gian, tắt được, và KHÔNG bỏ chốt --- */
$__vt = strpos( $__aj, "handoff_noi_giay" );
assert_true( $__vt !== false, 'Phai co option handoff_noi_giay de tat/chinh cua so noi' );
$__noi = $__vt === false ? '' : substr( $__aj, $__vt - 400, 900 );
assert_true( strpos( $__noi, '$_noi_giay > 0' ) !== false,
    'Dat option ve 0 phai TAT han viec noi' );
assert_true( strpos( $__noi, 'created_at' ) !== false,
    'Phai so theo tuoi cua luot (created_at), khong noi vo dieu kien' );
assert_true( strpos( $__noi, '<= $_noi_giay' ) !== false,
    'Phai chan tren bang cua so thoi gian' );
// Chốt gốc vẫn phải còn: không được xoá điều kiện đọc dấu bàn giao
assert_true( strpos( $__aj, "get_transient( 'sitetop_handoff_' . \$visit->session_id )" ) !== false,
    'Van phai doc dau ban giao that truoc khi xet noi' );
