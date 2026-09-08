<?php
/* Kiểm hàm THẬT sitetop_dau_hieu_cong_cu() — trích nguyên văn từ shortlink-ajax.php
   bằng tokenizer rồi chạy với nhiều bộ $_SERVER, không viết lại logic. */
if ( ! function_exists( 'sitetop_dau_hieu_cong_cu' ) ) {
    $__src = file_get_contents( dirname(__DIR__, 2) . '/includes/shortlink-ajax.php' );
    $tk = token_get_all( $__src ); $n = count( $tk ); $__fn = null;
    for ( $i = 0; $i < $n; $i++ ) {
        if ( ! is_array( $tk[$i] ) || $tk[$i][0] !== T_FUNCTION ) continue;
        $j = $i + 1;
        while ( $j < $n && is_array( $tk[$j] ) && in_array( $tk[$j][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) $j++;
        if ( $j >= $n || ! is_array( $tk[$j] ) || $tk[$j][1] !== 'sitetop_dau_hieu_cong_cu' ) continue;
        $out = ''; $d = 0; $open = false;
        for ( $k = $i; $k < $n; $k++ ) {
            $t = $tk[$k]; $out .= is_array( $t ) ? $t[1] : $t;
            $mo = ( $t === '{' ) || ( is_array($t) && in_array($t[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true) );
            if ( $mo ) { $d++; $open = true; } elseif ( $t === '}' ) { $d--; if ( $open && $d === 0 ) break; }
        }
        $__fn = $out; break;
    }
    if ( $__fn === null ) { $GLOBALS['test_results']['failed']++; $GLOBALS['test_results']['errors'][] = 'Khong trich duoc sitetop_dau_hieu_cong_cu'; return; }
    eval( $__fn );
}

$CH  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
$GSA = 'Mozilla/5.0 (Linux; Android 13; SM-A125F; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/126.0.6478.71 Mobile Safari/537.36 GSA/15.24';
$SAF = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';
$FF  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0';

$run = function ( $ua, $secfetch ) {
    $_SERVER['HTTP_USER_AGENT'] = $ua;
    unset( $_SERVER['HTTP_SEC_FETCH_MODE'] );
    if ( $secfetch !== null ) $_SERVER['HTTP_SEC_FETCH_MODE'] = $secfetch;
    return sitetop_dau_hieu_cong_cu();
};

// Người thật: Chrome đời mới LUÔN có Sec-Fetch -> không cờ
assert_false( $run($CH,  'cors'),        'Chrome 126 + Sec-Fetch=cors -> nguoi that' );
assert_false( $run($CH,  'same-origin'), 'Chrome 126 + Sec-Fetch=same-origin -> nguoi that' );
// Công cụ: UA Chrome đời mới nhưng THIẾU Sec-Fetch -> cờ
assert_true(  $run($CH,  null),          'Chrome 126 thieu Sec-Fetch -> cong cu' );
// Ngưỡng phiên bản: dưới 90 không kết luận (chừa biên cho máy cũ)
assert_false( $run(str_replace('126.0.0.0','88.0.0.0',$CH), null), 'Chrome 88 thieu Sec-Fetch -> KHONG ket luan' );
assert_true(  $run(str_replace('126.0.0.0','90.0.0.0',$CH), null), 'Chrome 90 thieu Sec-Fetch -> cong cu (dung nguong)' );
// Edge Chromium cũng thuộc diện xét
assert_true(  $run(str_replace('Chrome/126.0.0.0 Safari/537.36','Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',$CH), null), 'Edge 120 thieu Sec-Fetch -> cong cu' );
// Webview app Google (Chrome 126) gửi Sec-Fetch bình thường -> KHÔNG oan
assert_false( $run($GSA, 'cors'),        'App Google webview + Sec-Fetch -> khong oan' );
// Không phải Chromium -> không xét, dù thiếu Sec-Fetch
assert_false( $run($SAF, null),          'Safari iOS thieu Sec-Fetch -> khong xet' );
assert_false( $run($FF,  null),          'Firefox thieu Sec-Fetch -> khong xet' );
// UA rỗng -> không xét
assert_false( $run('',   null),          'UA rong -> khong xet' );

// Khối chặn thưởng trong sitetop_verify_and_pay (B3): CHỈ chặn thưởng khi guard bật
// VÀ phiên đã bị gắn cờ công cụ. Không cờ -> không đụng gì (người thật an toàn).
$chan_thuong = function ( $should_pay, $guard_on, $co_co ) {
    $reasons = array();
    if ( $should_pay && $guard_on ) {
        if ( $co_co ) { $should_pay = false; $reasons[] = 'cong_cu_bypass'; }
    }
    return array( $should_pay, $reasons );
};
list($pay,$rs) = $chan_thuong( true,  true,  true );
assert_false( $pay,                       'Guard bat + co co -> chan thuong' );
assert_true(  in_array('cong_cu_bypass',$rs), 'Guard bat + co co -> ghi ly do' );
list($pay,$rs) = $chan_thuong( true,  true,  false );
assert_true(  $pay,                       'Guard bat + KHONG co -> nguoi that van duoc thuong' );
list($pay,$rs) = $chan_thuong( true,  false, true );
assert_true(  $pay,                       'Guard TAT -> khong chan du co co (cong tac an toan)' );

// --- Mức xử lý công cụ (sitetop_congcu_muc): 0 tắt / 1 quan sát / 2 chặn cứng ---
if ( ! function_exists( 'sitetop_get_option' ) ) {
    function sitetop_get_option( $k, $d = null ) { return $GLOBALS['__opt'][$k] ?? $d; }
}
if ( ! function_exists( 'sitetop_congcu_muc' ) ) {
    $__src2 = file_get_contents( dirname(__DIR__, 2) . '/includes/shortlink-ajax.php' );
    $tk = token_get_all( $__src2 ); $n = count( $tk ); $__fn = null;
    for ( $i = 0; $i < $n; $i++ ) {
        if ( ! is_array( $tk[$i] ) || $tk[$i][0] !== T_FUNCTION ) continue;
        $j = $i + 1;
        while ( $j < $n && is_array( $tk[$j] ) && in_array( $tk[$j][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) $j++;
        if ( $j >= $n || ! is_array( $tk[$j] ) || $tk[$j][1] !== 'sitetop_congcu_muc' ) continue;
        $out=''; $d=0; $open=false;
        for ( $k = $i; $k < $n; $k++ ) {
            $t=$tk[$k]; $out .= is_array($t)?$t[1]:$t;
            $mo = ($t==='{') || (is_array($t) && in_array($t[0], array(T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES), true));
            if($mo){$d++;$open=true;} elseif($t==='}'){$d--; if($open&&$d===0)break;}
        }
        $__fn=$out; break;
    }
    if ( $__fn ) eval( $__fn );
}
$CHR = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
$dat_muc = function ( $ua, $secfetch, $opt ) {
    $_SERVER['HTTP_USER_AGENT'] = $ua;
    unset( $_SERVER['HTTP_SEC_FETCH_MODE'] );
    if ( $secfetch !== null ) $_SERVER['HTTP_SEC_FETCH_MODE'] = $secfetch;
    $GLOBALS['__opt'] = array( 'congcu_hard_block' => $opt );
    return sitetop_congcu_muc();
};
// Người thật (có Sec-Fetch): luôn 0 dù option bao nhiêu
assert_equals( 0, $dat_muc($CHR,'cors',2), 'Nguoi that -> muc 0 du option=2' );
// Công cụ (thiếu Sec-Fetch): theo option
assert_equals( 1, $dat_muc($CHR,null,1), 'Cong cu + option 1 -> quan sat' );
assert_equals( 2, $dat_muc($CHR,null,2), 'Cong cu + option 2 -> chan cung' );
assert_equals( 0, $dat_muc($CHR,null,0), 'Cong cu + option 0 -> tat' );
// Mặc định (option chưa set) = 1 (quan sát)
$_SERVER['HTTP_USER_AGENT']=$CHR; unset($_SERVER['HTTP_SEC_FETCH_MODE']); $GLOBALS['__opt']=array();
assert_equals( 2, sitetop_congcu_muc(), 'Cong cu + option mac dinh -> chan cung (2)' );
unset( $_SERVER['HTTP_SEC_FETCH_MODE'], $_SERVER['HTTP_USER_AGENT'] );

// --- sitetop_iframe_muc: kf=0 (iframe) mới xử lý; kf=1/thiếu bỏ qua (fail-open) ---
if ( ! function_exists( 'sitetop_iframe_muc' ) ) {
    $__s3 = file_get_contents( dirname(__DIR__, 2) . '/includes/shortlink-ajax.php' );
    $tk = token_get_all( $__s3 ); $n = count( $tk ); $__fn = null;
    for ( $i = 0; $i < $n; $i++ ) {
        if ( ! is_array( $tk[$i] ) || $tk[$i][0] !== T_FUNCTION ) continue;
        $j=$i+1;
        while ( $j<$n && is_array($tk[$j]) && in_array($tk[$j][0],array(T_WHITESPACE,T_COMMENT,T_DOC_COMMENT),true) ) $j++;
        if ( $j>=$n || ! is_array($tk[$j]) || $tk[$j][1] !== 'sitetop_iframe_muc' ) continue;
        $out=''; $d=0; $open=false;
        for ( $k=$i; $k<$n; $k++ ) {
            $t=$tk[$k]; $out .= is_array($t)?$t[1]:$t;
            $mo=($t==='{')||(is_array($t)&&in_array($t[0],array(T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES),true));
            if($mo){$d++;$open=true;} elseif($t==='}'){$d--; if($open&&$d===0)break;}
        }
        $__fn=$out; break;
    }
    if ( $__fn ) eval( $__fn );
}
$dat_if = function ( $kf, $opt ) {
    if ( $kf === null ) unset( $_POST['kf'] ); else $_POST['kf'] = $kf;
    $GLOBALS['__opt'] = array( 'iframe_hard_block' => $opt );
    return sitetop_iframe_muc();
};
assert_equals( 1, $dat_if('0',1), 'iframe kf=0 + opt1 -> quan sat' );
assert_equals( 2, $dat_if('0',2), 'iframe kf=0 + opt2 -> chan' );
assert_equals( 0, $dat_if('0',0), 'iframe kf=0 + opt0 -> tat' );
assert_equals( 0, $dat_if('1',2), 'khung chinh kf=1 -> bo qua (khong oan)' );
assert_equals( 0, $dat_if(null,2), 'kf thieu (widget cu) -> bo qua (fail-open)' );
unset( $_POST['kf'] );

echo "  ✓ bypass-detect (Sec-Fetch)\n";
unset( $_SERVER['HTTP_SEC_FETCH_MODE'], $_SERVER['HTTP_USER_AGENT'] );

/* ---- DẤU QUAN SÁT iframe_an TUYỆT ĐỐI KHÔNG ĐƯỢC ĐỤNG TỚI TIỀN ----
   Lớp iframe đang ở mức quan sát; dấu này chỉ để đếm xem ai đang dính trước khi quyết nâng
   lên chặn cứng. Nếu ai đó lỡ gán $should_pay_* trong khối này thì mọi lượt dính kf=0 mất
   thưởng ngay mà không ai báo lỗi — đúng kiểu hỏng âm thầm. */
$__xm = file_get_contents( dirname(__DIR__, 2) . '/includes/shortlink-verification.php' );
$__vt = strpos( $__xm, "if ( get_transient( 'sitetop_iframe_'" );
assert_true( $__vt !== false, 'Phai tim thay khoi dau quan sat iframe_an' );
if ( $__vt !== false ) {
    // Thân khối: từ dấu { đầu tiên tới } khớp cặp
    $__b = strpos( $__xm, '{', $__vt );
    $__d = 0; $__than_khoi = '';
    for ( $__i = $__b; $__i < strlen( $__xm ); $__i++ ) {
        $__c = $__xm[ $__i ]; $__than_khoi .= $__c;
        if ( $__c === '{' ) $__d++;
        elseif ( $__c === '}' ) { $__d--; if ( $__d === 0 ) break; }
    }
    assert_true( strpos( $__than_khoi, 'skip_reasons' ) !== false,
        'Khoi iframe_an phai ghi skip_reasons' );
    assert_true( strpos( $__than_khoi, 'should_pay' ) === false,
        'Khoi iframe_an KHONG duoc dung toi should_pay (dau quan sat, khong duoc cat tien)' );
    assert_true( strpos( $__than_khoi, 'wp_send_json' ) === false,
        'Khoi iframe_an KHONG duoc chan luot' );
}
