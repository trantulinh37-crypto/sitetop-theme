<?php
/* Chốt chặn "đúng URL mới cho lấy mã" — sitetop_campaign_allows_url() trong functions.php.

   Nới lỏng: sitetop.one 05/09/2026, sitetop.net 08/09/2026:
     (1) chỉ so DOMAIN, bỏ qua đường dẫn — test.com/abc hợp lệ với camp đặt test.com/;
     (2) tên miền con: mở 05/09 → siết còn 1 cấp 08/09 → 08/09 ĐÓNG HẲN.
   Nay CHỈ đúng tên miền mới hợp lệ. Muốn nhận tên miền con nào thì khai thẳng tên
   miền đó vào danh sách URL đích. 'www.' không tính là tên miền con (bị gột trước khi so).
   https://test.vn/ vẫn bị chặn. Trước đó so cả host lẫn path nên phải vào đúng y nguyên URL.

   Đây là luật quyết định AI ĐƯỢC TRẢ TIỀN, nên chốt lại bằng test: nới quá tay thì mất
   tiền oan, siết lại thì user làm đúng vẫn bị chặn.

   Trước đây file này CHÉP LẠI logic của functions.php. 21/09/2026 đổi sang trích HÀM THẬT
   bằng tokenizer: bản chép không thấy được thay đổi bên kia — đúng lúc sitetop_host_of()
   được dạy hiểu URL khai gọn "test.com" (camp Direct lưu y như khách gõ) thì bản chép vẫn
   xanh trong khi hàm thật đã khác. */

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
foreach ( array( 'sitetop_clean_url_text', 'sitetop_them_scheme', 'sitetop_host_of', 'sitetop_campaign_destinations', 'sitetop_campaign_allows_url' ) as $__f ) {
    if ( function_exists( $__f ) ) continue;
    $__code = $__trich( 'functions.php', $__f );
    if ( $__code === null ) {
        $GLOBALS['test_results']['failed']++;
        $GLOBALS['test_results']['errors'][] = "Khong trich duoc $__f tu functions.php";
        return;
    }
    eval( $__code );
}
$host_of = 'sitetop_host_of';
$allows = function ( $dests, $current ) {
    return sitetop_campaign_allows_url( (object) array( 'destination_urls' => json_encode( (array) $dests ) ), $current );
};

$camp = array( 'https://test.com/' );

// ── Đúng yêu cầu chủ site ────────────────────────────────────────────────
assert_true(  $allows( $camp, 'https://test.com/' ),        'Vao dung URL goc -> cho' );
assert_true(  $allows( $camp, 'https://test.com/abc/' ),    'Cung domain, khac duong dan -> CHO (day la phan noi long)' );
assert_true(  $allows( $camp, 'https://test.com/a/b/c?x=1#y' ), 'Duong dan sau + query + hash -> cho' );
assert_false( $allows( $camp, 'https://test.vn/' ),         'Khac domain -> CHAN' );

// ── www và chữ hoa phải coi như một ──────────────────────────────────────
assert_true(  $allows( $camp, 'https://www.test.com/abc' ), 'www.test.com = test.com' );
assert_true(  $allows( $camp, 'HTTPS://TEST.COM/ABC' ),     'Chu hoa -> van khop' );
assert_true(  $allows( array( 'https://www.test.com/x' ), 'https://test.com/y' ), 'Camp dat www, user vao khong www -> cho' );
assert_true(  $allows( $camp, 'http://test.com/abc' ),      'http vs https -> khong xet giao thuc' );

// ── Tên miền con: ĐÓNG HẲN (chủ site chốt 08/09/2026) ────────────────────
assert_false( $allows( $camp, 'https://blog.test.com/abc' ),   'Ten mien con 1 cap -> CHAN' );
assert_false( $allows( $camp, 'https://m.test.com/' ),         'Ten mien con mobile -> CHAN' );
assert_false( $allows( $camp, 'https://a.b.test.com/x' ),      'Ten mien con 2 cap -> CHAN' );
assert_false( $allows( $camp, 'https://blog.tin.test.com/x' ), 'blog.tin.test.com -> CHAN' );

// Khai THẲNG tên miền con vào danh sách đích thì lại hợp lệ — đó là đường đi đúng.
assert_true(  $allows( array( 'https://blog.test.com/' ), 'https://blog.test.com/bai-1' ),
    'Khai thang ten mien con vao danh sach -> CHO' );
assert_false( $allows( array( 'https://blog.test.com/' ), 'https://test.com/' ),
    '...nhung khi do ten mien cha lai bi CHAN' );

// 'www.' KHONG phai ten mien con — bi got o CA HAI VE truoc khi so.
assert_true(  $allows( $camp, 'https://www.test.com/abc' ),      'www.test.com = test.com -> CHO' );
assert_false( $allows( $camp, 'https://www.blog.test.com/x' ),   'www + ten mien con -> CHAN (got www con lai blog.test.com)' );

// ── ...NHUNG khong duoc lot domain gia. Day la phan de sai nhat:
//    - Dung "ket thuc bang test.com" thay vi hau to ".test.com" -> nottest.com va
//      eviltest.com LOT (hai dong giua).
//    - Dung "co chua test.com" -> them test.com.evil.net LOT (dong dau).
//    Chi hau to co dau cham ngan moi chan duoc ca ba.
assert_false( $allows( $camp, 'https://test.com.evil.net/' ), 'Domain gia dinh duoi -> CHAN' );
assert_false( $allows( $camp, 'https://nottest.com/' ),      'Domain chua chuoi giong -> CHAN' );
assert_false( $allows( $camp, 'https://eviltest.com/' ),     'Tien to dinh lien -> CHAN' );
assert_false( $allows( $camp, 'https://test.com.vn/' ),      'Cung goc khac duoi -> CHAN' );

assert_false( $allows( array( 'https://blog.test.com/' ), 'https://shop.test.com/' ),
    'Hai ten mien con anh em -> CHAN' );

// ── Camp nhiều URL đích ở nhiều domain ───────────────────────────────────
$nhieu = array( 'https://a.com/trang-1', 'https://b.org/muc/2' );
assert_true(  $allows( $nhieu, 'https://a.com/bat-ky' ), 'Khop domain thu nhat' );
assert_true(  $allows( $nhieu, 'https://b.org/khac' ),   'Khop domain thu hai' );
assert_false( $allows( $nhieu, 'https://cdn.a.com/anh' ),    'Ten mien con cua domain thu nhat -> CHAN' );
assert_false( $allows( $nhieu, 'https://c.net/' ),       'Khong domain nao khop -> CHAN' );

// ── Rác vô hình dán từ Word/Zalo không được làm hỏng so khớp ─────────────
assert_true( $allows( array( "  https://test.com/x\xC2\xA0" ), 'https://test.com/y' ), 'URL dich dinh khoang trang khong ngat -> van khop' );
assert_true( $allows( $camp, "\xEF\xBB\xBFhttps://test.com/z" ), 'URL hien tai dinh BOM -> van khop' );

// ── Đầu vào hỏng thì phải CHẶN, không được cho qua ───────────────────────
assert_false( $allows( $camp, '' ),           'URL rong -> CHAN' );
assert_false( $allows( $camp, 'khong-phai-url' ), 'Chuoi khong phai URL -> CHAN' );
assert_false( $allows( array(), 'https://test.com/' ), 'Camp khong co URL dich nao -> CHAN' );

// ── Camp Direct lưu URL KHAI GỌN (21/09/2026) — cổng vẫn phải so đúng tên miền ──
assert_true(  $allows( array( 'test.com' ), 'https://test.com/abc' ),   'Camp luu gon "test.com" -> user vao https://test.com/abc: CHO' );
assert_true(  $allows( array( 'test.com/abc' ), 'https://www.test.com/' ), 'Camp luu gon co duong dan -> van so theo ten mien' );
assert_false( $allows( array( 'test.com' ), 'https://test.vn/' ),       'Camp luu gon -> khac ten mien van CHAN' );
assert_false( $allows( array( 'test.com' ), 'https://blog.test.com/' ), 'Camp luu gon -> ten mien con van CHAN' );
assert_false( $allows( array( 'test.com' ), 'https://test.com.evil.net/' ), 'Camp luu gon -> ten mien gia dinh duoi van CHAN' );

echo "  ✓ campaign url match (CHI dung ten mien, khong ten mien con)\n";
