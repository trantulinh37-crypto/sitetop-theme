<?php
/* Bước 2 CHÉO TÊN MIỀN — sitetop_buoc2_cheo_web() trong functions.php.

   Luồng chủ site yêu cầu (18/09/2026):
     Web A → đủ 70s → hiện ảnh hướng dẫn → bấm sang Web B → CHỈ 15s → hiện mã.
   Web A = tên miền của URL đích ĐẦU TIÊN. Web B = tên miền khác, nằm trong
   "+ Thêm URL đích", đã cài mã.

   Kiểm HÀM THẬT: trích nguyên văn từ functions.php bằng tokenizer rồi eval — KHÔNG
   chép lại logic. Chép lại thì test chỉ kiểm bản chép, sửa mã thật mà quên sửa bản
   chép là test vẫn xanh trong khi site đã sai.

   Hai nửa đều quan trọng ngang nhau:
     - PHẢI bật đúng ca Web A → Web B;
     - KHÔNG được bật ở mọi ca khác — đó là lời hứa "giữ nguyên logic cũ".

   Bản .net (chuyển từ .one cùng ngày, sau khi chủ site test đạt bên .one) có thêm
   sitetop_camp_co_web_b() và hai chốt function_exists — xem phần cuối file. */

$__trich = function ( $tep, $ten ) {
    $src = file_get_contents( dirname( __DIR__, 2 ) . '/' . $tep );
    $tk = token_get_all( $src ); $n = count( $tk );
    for ( $i = 0; $i < $n; $i++ ) {
        if ( ! is_array( $tk[$i] ) || $tk[$i][0] !== T_FUNCTION ) continue;
        $j = $i + 1;
        while ( $j < $n && is_array( $tk[$j] ) && $tk[$j][0] === T_WHITESPACE ) $j++;
        if ( $j >= $n || ! is_array( $tk[$j] ) || $tk[$j][1] !== $ten ) continue;
        $out = ''; $d = 0; $mo = false;
        for ( $k = $i; $k < $n; $k++ ) {
            $t = $tk[$k]; $out .= is_array( $t ) ? $t[1] : $t;
            $open = ( $t === '{' ) || ( is_array( $t ) && in_array( $t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) );
            if ( $open ) { $d++; $mo = true; } elseif ( $t === '}' ) { $d--; if ( $mo && $d === 0 ) break; }
        }
        return $out;
    }
    return null;
};
foreach ( array( 'sitetop_clean_url_text', 'sitetop_them_scheme', 'sitetop_host_of', 'sitetop_campaign_destinations', 'sitetop_buoc2_cheo_web', 'sitetop_camp_co_web_b' ) as $__f ) {
    if ( function_exists( $__f ) ) continue;
    $__code = $__trich( 'functions.php', $__f );
    if ( $__code === null ) {
        $GLOBALS['test_results']['failed']++;
        $GLOBALS['test_results']['errors'][] = "Khong trich duoc $__f tu functions.php";
        return;
    }
    eval( $__code );
}

// Camp mẫu: A là URL đích đầu tiên, B được thêm ở "+ Thêm URL đích"
$camp = function ( $sua = array() ) {
    return (object) array_merge( array(
        'traffic_type'     => '2step',
        'url_matched'      => 1,
        'target_url'       => 'https://web-a.com/',
        'destination_urls' => json_encode( array( 'https://web-a.com/', 'https://web-b.com/' ) ),
        'onsite_time'      => 70,
    ), $sua );
};
$f = 'sitetop_buoc2_cheo_web';

// ── PHẢI BẬT: đúng luồng chủ site mô tả ─────────────────────────────────
assert_true(  $f( $camp(), 'https://web-b.com/',            'web-a.com', 70 ), 'A xong 70s, sang B -> BAT (15s)' );
assert_true(  $f( $camp(), 'https://web-b.com/choi-ngay',   'web-a.com', 90 ), 'Sang trang khac cua B -> BAT' );
assert_true(  $f( $camp(), 'https://www.web-b.com/?r=1#x',  'web-a.com', 70 ), 'B co www + tham so -> BAT' );
assert_true(  $f( $camp(), 'https://web-b.com/',            'web-a.com', 65 ), 'Dung nguong 65s (70-5) -> BAT' );
$nhieuB = $camp( array( 'destination_urls' => json_encode( array( 'https://web-a.com/', 'https://web-b.com/', 'https://web-c.com/' ) ) ) );
assert_true(  $f( $nhieuB, 'https://web-c.com/', 'web-a.com', 70 ), 'Co nhieu Web B, sang cai thu hai -> BAT' );

// ── KHÔNG ĐƯỢC BẬT: đây là lời hứa giữ nguyên logic cũ ──────────────────
assert_false( $f( $camp(), 'https://web-a.com/lien-he',     'web-a.com', 70 ), 'Van o Web A (link noi bo) -> KHONG, luong cu lo' );

// Chốt then chốt: vào thẳng Web B làm bước 1 thì phải chạy y như trước
assert_false( $f( $camp(), 'https://web-b.com/',            'web-b.com', 70 ), 'Buoc 1 lam ngay tren B -> KHONG (giu luong cu)' );
assert_false( $f( $camp(), 'https://web-b.com/',            'web-b.com', 999 ), '... du dung B rat lau cung KHONG' );

// Hỏng mềm: thiếu dấu nơi làm bước 1 thì rơi về luồng cũ, không cấp nhầm
assert_false( $f( $camp(), 'https://web-b.com/',            false,       70 ), 'Mat dau buoc 1 (transient bi xoa) -> KHONG' );
assert_false( $f( $camp(), 'https://web-b.com/',            '',          70 ), 'Dau buoc 1 rong -> KHONG' );

// Chưa đủ giờ bước 1 thì không cho đi tắt
assert_false( $f( $camp(), 'https://web-b.com/',            'web-a.com', 64 ), 'Thieu 1 giay (64s) -> KHONG' );
assert_false( $f( $camp(), 'https://web-b.com/',            'web-a.com', 5 ),  'Nhay sang B ngay -> KHONG' );

// Loại camp và trạng thái lượt
assert_false( $f( $camp( array( 'traffic_type' => '1step' ) ), 'https://web-b.com/', 'web-a.com', 70 ), 'Camp 1 buoc -> KHONG' );
assert_false( $f( $camp( array( 'traffic_type' => 'direct' ) ), 'https://web-b.com/', 'web-a.com', 70 ), 'Camp direct -> KHONG' );
assert_false( $f( $camp( array( 'url_matched' => 0 ) ), 'https://web-b.com/', 'web-a.com', 70 ), 'Chua tung dung dung web dich -> KHONG' );

// Chỉ những tên miền ĐÃ THÊM mới là Web B
assert_false( $f( $camp(), 'https://web-c.com/',            'web-a.com', 70 ), 'Web khong co trong danh sach -> KHONG' );
assert_false( $f( $camp(), 'https://m.web-b.com/',          'web-a.com', 70 ), 'Ten mien con cua B chua them -> KHONG' );
assert_false( $f( $camp(), 'https://web-b.com.evil.net/',   'web-a.com', 70 ), 'Ten mien gia dinh duoi -> KHONG' );
assert_false( $f( $camp(), 'https://notweb-b.com/',         'web-a.com', 70 ), 'Chuoi giong nhung khac -> KHONG' );

// Camp chỉ có Web A (không thêm URL đích nào) — mọi camp hiện có kiểu này phải y nguyên
$chiA = $camp( array( 'destination_urls' => '' ) );
assert_false( $f( $chiA, 'https://web-b.com/', 'web-a.com', 70 ), 'Camp chi co Web A -> KHONG bao gio bat' );

// Đầu vào hỏng
assert_false( $f( $camp(), '',                              'web-a.com', 70 ), 'URL rong -> KHONG' );
assert_false( $f( null,    'https://web-b.com/',            'web-a.com', 70 ), 'Khong co luot -> KHONG' );

/* ── NỐI DÂY trong verify_access ─────────────────────────────────────────
   Hàm đúng mà không được gọi, hoặc gọi SAI CHỖ, thì site vẫn chạy lại 70s ở Web B.
   Thứ tự là bẫy thật: khối "rời website" bên dưới đặt lại created_at khi user vắng
   quá 10 giây, mà web B tải lâu là vắng quá 10 giây. Gọi hàm SAU khối đó là số giây
   bước 1 đã bị xoá về 0 → điều kiện đủ giờ không bao giờ đạt. */
$__va = $__trich( 'includes/shortlink-ajax.php', 'sitetop_ajax_widget_verify_access' );
assert_true( $__va !== null, 'Trich duoc ham verify_access' );
if ( $__va !== null ) {
    /* Bỏ CHÚ THÍCH trước khi dò: chú thích có nhắc tên hàm, strpos khớp vào đó thì
       test xanh dù lời gọi thật đã bị xoá hay dời sai chỗ. Bẫy này đã xảy ra thật
       lúc viết test này. */
    $__khong_ct = '';
    foreach ( token_get_all( '<?php ' . $__va ) as $__t ) {
        if ( is_array( $__t ) && in_array( $__t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) continue;
        $__khong_ct .= is_array( $__t ) ? $__t[1] : $__t;
    }
    $__va = $__khong_ct;
    $_goi   = strpos( $__va, 'sitetop_buoc2_cheo_web(' );
    $_s2cu  = strpos( $__va, 'sitetop_url_key( $client_url )' );
    $_reset = strpos( $__va, "get_transient( 'sitetop_left_'" );
    assert_true( $_goi !== false, 'verify_access CO goi sitetop_buoc2_cheo_web()' );
    assert_true( $_goi !== false && $_s2cu !== false && $_s2cu < $_goi,
        'Goi SAU khoi nhan dien buoc 2 cu (chi bu khi khoi cu khong nhan ra)' );
    assert_true( $_goi !== false && $_reset !== false && $_goi < $_reset,
        'Goi TRUOC khoi "roi website" (doc created_at chua bi dat lai)' );
    assert_true( strpos( $__va, "! \$step2_cheo && empty( \$visit->code_shown_at )" ) !== false,
        'Khoi "roi website" mien cho ca cheo ten mien' );
    // Dấu nơi làm bước 1 chỉ ghi LẦN ĐẦU — ghi đè là sang B nó thành B, điều kiện 3 hỏng.
    assert_true( (bool) preg_match( "/if \\( get_transient\\( \\\$_k1 \\) === false \\) \\{\\s*set_transient\\( \\\$_k1,/", $__va ),
        'sitetop_s1host_ chi ghi lan dau, khong ghi de' );

    /* RIÊNG .net — hai chốt an toàn cho site đông.
       (a) function_exists: hàm mới ở functions.php, lời gọi ở includes/. Lúc deploy hai
           file không đổi cùng một khắc; thiếu chốt này là có khoảnh khắc mọi verify_access
           chết vì "Call to undefined function".
       (b) Chỉ đọc/ghi dấu bước 1 cho camp có Web B — camp một tên miền không tốn thêm
           truy vấn nào. Chốt (b) mà sai (vd luôn false) thì Web B lặng lẽ chạy lại 70s,
           nên phần đơn vị ngay dưới kiểm kỹ sitetop_camp_co_web_b(). */
    assert_true( strpos( $__va, "! \$step2_continue && function_exists( 'sitetop_buoc2_cheo_web' ) && sitetop_camp_co_web_b( \$visit )" ) !== false,
        'Loi goi duoc che bang function_exists + chi camp co Web B' );
    assert_true( (bool) preg_match( "/if \\( function_exists\\( 'sitetop_camp_co_web_b' \\) && sitetop_camp_co_web_b\\( \\\$visit \\) \\) \\{\\s*\\\$_k1 = 'sitetop_s1host_'/", $__va ),
        'Ghi dau buoc 1 duoc che bang function_exists + chi camp co Web B' );
}

// ── sitetop_camp_co_web_b(): camp nào mới có Web B ──────────────────────────
$g = 'sitetop_camp_co_web_b';
assert_true(  $g( $camp() ),  'Camp 2 buoc co A + B -> CO Web B' );
assert_true(  $g( $nhieuB ),  'Camp 2 buoc co A + 2 Web B -> CO' );
assert_false( $g( $chiA ),    'Camp chi co Web A (destination rong) -> KHONG' );
assert_false( $g( $camp( array( 'destination_urls' => json_encode( array( 'https://web-a.com/', 'https://web-a.com/trang-2' ) ) ) ) ),
    'Nhieu URL nhung CUNG mot ten mien -> KHONG' );
assert_false( $g( $camp( array( 'destination_urls' => json_encode( array( 'https://web-a.com/', 'https://www.web-a.com/x' ) ) ) ) ),
    'web-a.com va www.web-a.com la MOT ten mien -> KHONG' );
assert_false( $g( $camp( array( 'traffic_type' => '1step' ) ) ),  'Camp 1 buoc du nhieu ten mien -> KHONG' );
assert_false( $g( $camp( array( 'traffic_type' => 'direct' ) ) ), 'Camp direct -> KHONG' );
assert_false( $g( $camp( array( 'destination_urls' => json_encode( array( 'https://web-a.com/', 'khong-phai-url' ) ) ) ) ),
    'URL thu hai hong (khong co ten mien) -> KHONG tinh la Web B' );
assert_false( $g( null ), 'Khong co luot -> KHONG' );

echo "  ✓ buoc 2 cheo ten mien (Web A -> Web B chi 15s)\n";
