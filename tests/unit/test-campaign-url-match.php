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

   Bản dưới là bản sao logic của functions.php (harness không nạp WordPress) — sửa bên
   kia thì sửa cả bên này. */

$host_of = function ( $url ) {
    $url = str_replace( array( "\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBB\xBF" ), ' ', (string) $url );
    $host = parse_url( trim( $url ), PHP_URL_HOST );
    return $host ? preg_replace( '/^www\./', '', strtolower( $host ) ) : '';
};

$allows = function ( $dests, $current ) use ( $host_of ) {
    $h = $host_of( $current );
    if ( $h === '' ) return false;
    foreach ( (array) $dests as $u ) {
        $d = $host_of( $u );
        if ( $d === '' ) continue;
        if ( $h === $d ) return true;   // so ĐÚNG BẰNG, không còn nhánh tên miền con
    }
    return false;
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

echo "  ✓ campaign url match (CHI dung ten mien, khong ten mien con)\n";
