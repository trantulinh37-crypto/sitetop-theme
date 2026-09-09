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
/* Neo theo THÂN HÀM, không theo cửa sổ ký tự: chèn thêm khối vào giữa là cửa sổ lệch
   ngay và phép canh hỏng oan (đã dính khi thêm phần nới theo URL). */
$__vf0 = $__than( $__aj, 'sitetop_ajax_widget_verify_access' );
assert_true( strpos( $__vf0, 'handoff_noi_giay' ) !== false,
    'Phai co option handoff_noi_giay de tat/chinh cua so noi' );
assert_true( strpos( $__vf0, '$_noi_giay > 0' ) !== false,
    'Dat option ve 0 phai TAT han viec noi' );
assert_true( strpos( $__vf0, '$visit->created_at' ) !== false,
    'Phai so theo tuoi cua luot (created_at), khong noi vo dieu kien' );
assert_true( strpos( $__vf0, '<= $_noi_giay' ) !== false,
    'Phai chan tren bang cua so thoi gian' );
// Chốt gốc vẫn phải còn: không được xoá điều kiện đọc dấu bàn giao
assert_true( strpos( $__aj, "get_transient( 'sitetop_handoff_' . \$visit->session_id )" ) !== false,
    'Van phai doc dau ban giao that truoc khi xet noi' );

/* --- Nhãn cảnh báo không được KHẲNG ĐỊNH nguyên nhân sai ---
   Bản cũ ghi cứng "Vào thẳng trang đích, không đi qua link nhiệm vụ" trong khi chính dữ
   liệu trong tin nói ngược ("Mở link nhiệm vụ: 3 giây trước", URL khớp). Câu sai bản chất
   làm người đọc đi sai hướng. */
$__cu = 'Vào thẳng trang đích, không đi qua link nhiệm vụ';
assert_true( strpos( $__aj, "'no_handoff'      => '" . $__cu . "'" ) === false,
    'Nhan no_handoff KHONG duoc ghi cung mot nguyen nhan' );
$__ta = $__than( $__aj, 'sitetop_alert_task_blocked' );
assert_true( $__ta !== '', 'Phai tim thay sitetop_alert_task_blocked' );
assert_true( strpos( $__ta, '$_nhan_hoff' ) !== false,
    'Nhan no_handoff phai duoc suy ra, khong ghi cung' );
assert_true( strpos( $__ta, '$_tuoi >= 0 && $_tuoi <= 120' ) !== false,
    'Phai tach nhan theo TUOI cua luot (vua mo link vs mo da lau)' );
// $_tuoi phải được gán TRƯỚC khi dùng cho nhãn
$__g = strpos( $__ta, '$_tuoi = strtotime(' );
$__d = strpos( $__ta, '$_nhan_hoff' );
assert_true( $__g !== false && $__d !== false && $__g < $__d,
    'Phai gan $_tuoi TRUOC khi dung no de chon nhan' );

/* --- Nới chốt bàn giao theo URL ĐÍCH (chủ site chốt 09/09/2026) ---
   Khớp URL mới là bằng chứng user đang làm nhiệm vụ thật; dấu bàn giao chỉ nói họ tới bằng
   đường nào, mà cái đó mất được vì lỗi phía mình. NHƯNG chặn SAI DOMAIN phải giữ nguyên. */
$__vf = $__than( $__aj, 'sitetop_ajax_widget_verify_access' );
assert_true( $__vf !== '', 'Phai tim thay sitetop_ajax_widget_verify_access' );

assert_true( strpos( $__vf, 'handoff_noi_url' ) !== false,
    'Phai co option handoff_noi_url de tat/bat noi theo URL' );
/* Neo vào OPTION + transient ghi dấu, không neo vào giá trị gán — giá trị đó đã phải đổi
   từ chuỗi sang time() để không vỡ phép kiểm hạn ngay dưới. */
assert_true( strpos( $__vf, "'sitetop_handoff_noi_' . \$visit->session_id, 'url'" ) !== false,
    'Phai co nhanh noi khi URL khop (ghi dau \'url\')' );
// Phải DÙNG LẠI hàm so khớp sẵn có, không tự viết phép so riêng
/* Neo vào CHÍNH nhánh nới: đếm tổng số lần gọi là không đủ — hàm này còn được gọi ở
   vòng tìm ứng viên, nên thay phép so trong nhánh nới bằng phép tự chế vẫn đủ số đếm. */
$__vt_nu = strpos( $__vf, "handoff_noi_url" );
$__khoi_nu = $__vt_nu === false ? '' : substr( $__vf, max( 0, $__vt_nu - 60 ), 420 );
assert_true( strpos( $__khoi_nu, 'sitetop_campaign_allows_url( $visit, $client_url )' ) !== false,
    'Nhanh noi PHAI dung lai sitetop_campaign_allows_url, khong tu che phep so' );

/* CHỐT SAI DOMAIN PHẢI CÒN, và phải nằm SAU chốt bàn giao — nới bàn giao mà mất luôn
   chốt URL là mở toang: ai vào web bất kỳ cũng chạy được đồng hồ. */
$__vt_noi   = strpos( $__vf, "handoff_noi_url" );
$__vt_wrong = strpos( $__vf, "'wrong_url'" );
/* Canh ĐÚNG câu lệnh gác, không phải chuỗi 'wrong_url' trần — chuỗi đó còn nằm ở lời gọi
   cảnh báo nên gỡ mất câu gác mà phép canh vẫn xanh. */
assert_true( strpos( $__vf, '! sitetop_campaign_allows_url( $visit, $client_url )' ) !== false,
    'Chot sai domain PHAI con (lenh gac ! sitetop_campaign_allows_url)' );
assert_true( $__vt_wrong !== false, 'Chot wrong_url PHAI con' );
assert_true( $__vt_noi !== false && $__vt_wrong > $__vt_noi,
    'Chot wrong_url phai nam SAU phan noi ban giao (noi xong van phai qua chot URL)' );

/* Tắt được: đặt option về 0 phải hết nới */
$__vt_opt = strpos( $__vf, 'handoff_noi_url' );
$__quanh  = substr( $__vf, max( 0, $__vt_opt - 120 ), 320 );
assert_true( strpos( $__quanh, 'sitetop_get_option' ) !== false,
    'Noi theo URL phai doc qua option, khong ghi cung' );

/* --- $granted PHẢI LUÔN LÀ MỐC THỜI GIAN ---
   Ngay dưới chốt bàn giao có phép kiểm `time() - (int) $granted > SITETOP_HANDOFF_TTL`.
   Gán chuỗi (vd 'noi_theo_url') thì (int) ra 0 -> phiên vừa được nới lại bị chặn ngay với
   lý do "Quá hạn bàn giao". Lỗi thật, gây ra 09/09/2026 lúc 13:42 và chủ site bắt được qua
   tin báo "Mở link nhiệm vụ: 1 phút 39 giây trước" mà vẫn kêu quá hạn — điều KHÔNG THỂ xảy
   ra với logic gốc, vì logic gốc đòi mốc cấp phải cũ hơn 15 phút. */
if ( preg_match_all( '/\$granted\s*=\s*([^;]+);/', $__vf, $__mg ) ) {
    foreach ( $__mg[1] as $__gt ) {
        $__gt = trim( $__gt );
        if ( strpos( $__gt, 'get_transient' ) === 0 ) continue;   // đọc từ transient thì hợp lệ
        assert_true( strpos( $__gt, "'" ) === false && strpos( $__gt, '"' ) === false,
            'Gan $granted PHAI la moc thoi gian, khong duoc la chuoi: ' . substr( $__gt, 0, 40 ) );
    }
    assert_true( count( $__mg[1] ) >= 3, 'Phai quet duoc cac cho gan $granted (' . count( $__mg[1] ) . ')' );
} else {
    assert_true( false, 'Khong quet duoc cho gan $granted' );
}
/* Và phép kiểm hạn phải còn — không được gỡ nó đi để né lỗi trên */
assert_true( strpos( $__vf, 'time() - (int) $granted > SITETOP_HANDOFF_TTL' ) !== false,
    'Phep kiem han ban giao PHAI con nguyen' );
