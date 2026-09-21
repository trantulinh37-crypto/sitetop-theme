<?php
/* TRAFFIC DIRECT — hai việc chủ site chốt 21/09/2026:
     1. Nút ON/OFF "Bắt gõ tay" cho camp Direct. Camp Direct KHÔNG có từ khoá, thứ user
        phải nhập là URL ĐÍCH, nên ON = trang nhiệm vụ bỏ nút Copy + chặn bôi đen URL.
        Dùng chung cột kw_bat_go_tay với camp Search, không thêm cột mới.
     2. URL đích khai gọn được: "weba.com" cũng như "https://weba.com".

   Ràng buộc chủ site đặt ra: KHÔNG đụng Search và các loại traffic khác. Nửa "không được
   bật" của bộ test này canh đúng lời hứa đó.

   Phần URL kiểm HÀM THẬT (trích từ functions.php bằng tokenizer). esc_url_raw/esc_html là
   hàm của WordPress nên phải dựng bản giả — bản giả bắt chước đúng một hành vi đã ĐO trên
   server: esc_url_raw("weba.com") = "http://weba.com" (xem chú thích trong hàm thật). */

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
if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $u ) {
        $u = trim( str_replace( ' ', '%20', (string) $u ) );
        if ( $u !== '' && ! preg_match( '#^[a-z0-9-]+:#i', $u ) ) $u = 'http://' . ltrim( $u, '/' );
        return $u;
    }
}
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); } }
foreach ( array( 'sitetop_clean_url_text', 'sitetop_them_scheme', 'sitetop_host_of', 'sitetop_url_key', 'sitetop_campaign_destinations', 'sitetop_campaign_allows_url', 'sitetop_sanitize_destination_urls' ) as $__f ) {
    if ( function_exists( $__f ) ) continue;
    $__code = $__trich( 'functions.php', $__f );
    if ( $__code === null ) {
        $GLOBALS['test_results']['failed']++;
        $GLOBALS['test_results']['errors'][] = "Khong trich duoc $__f tu functions.php";
        return;
    }
    eval( $__code );
}

// ── 1. URL gọn: CHỈ khi chỗ gọi cho phép (camp Direct) ──────────────────
$mot = function ( $u, $direct ) { return sitetop_sanitize_destination_urls( array( $u ), $direct ); };

$r = $mot( 'weba.com', true );
assert_equals( 'weba.com', implode( '', $r['urls'] ), 'Direct: khai "weba.com" -> LUU DUNG "weba.com" (khong tu them https)' );
assert_equals( '', $r['error'], 'Direct: khai gon khong bao loi' );

$r = $mot( 'weba.com/abc?x=1', true );
assert_equals( 'weba.com/abc?x=1', implode( '', $r['urls'] ), 'Direct: khai gon co duong dan -> luu y nguyen' );

$r = $mot( 'https://weba.com/abc', true );
assert_equals( 'https://weba.com/abc', implode( '', $r['urls'] ), 'Direct: URL day du -> y nguyen' );

$r = $mot( 'http://weba.com', true );
assert_equals( 'http://weba.com', implode( '', $r['urls'] ), 'Direct: http:// nguoi ta co y khai -> KHONG doi thanh https' );

// Nửa quan trọng nhất: loại camp khác KHÔNG đổi hành vi
$r = $mot( 'weba.com', false );
assert_equals( 'http://weba.com', implode( '', $r['urls'] ), 'Search/Social: y nguyen hanh vi cu (esc_url_raw tu them http)' );
$r = $mot( 'https://weba.com', false );
assert_equals( 'https://weba.com', implode( '', $r['urls'] ), 'Search/Social: URL day du -> y nguyen' );

// Chốt an toàn: khai gọn KHÔNG được mở đường cho rác
assert_true(  $mot( 'javascript:alert(1)', true )['error'] !== '', 'javascript: -> van CHAN (khong tu them https)' );
assert_true(  $mot( 'abcxyz', true )['error'] !== '',              'Go nham "abcxyz" (khong co dau cham) -> van CHAN' );
assert_true(  $mot( 'mailto:a@b.com', true )['error'] !== '',      'mailto: -> van CHAN' );
assert_true(  $mot( 'ftp://weba.com', true )['error'] !== '',      'ftp:// -> van CHAN' );
$r = sitetop_sanitize_destination_urls( array( 'weba.com', 'https://weba.com' ), true );
assert_equals( 1, count( $r['urls'] ), 'Khai gon va khai day du cung mot URL -> chi luu 1 (bo trung)' );
$r = sitetop_sanitize_destination_urls( array( '', 'weba.com' ), true );
assert_equals( 'weba.com', implode( '', $r['urls'] ), 'Dong trong van bi bo qua nhu cu' );

/* Lưu gọn nhưng CỔNG SO KHỚP không được hỏng — đây là chỗ nguy hiểm nhất của thay đổi này:
   URL thiếu giao thức thì parse_url trả host rỗng, host rỗng là user làm đúng vẫn bị chặn. */
$__campGon = (object) array( 'destination_urls' => json_encode( array( 'weba.com' ) ) );
assert_true(  sitetop_campaign_allows_url( $__campGon, 'https://weba.com/abc' ),   'Camp luu gon: user vao https://weba.com/abc -> CHO' );
assert_true(  sitetop_campaign_allows_url( $__campGon, 'https://www.weba.com/' ),  'Camp luu gon: co www -> CHO' );
assert_false( sitetop_campaign_allows_url( $__campGon, 'https://weba.vn/' ),       'Camp luu gon: khac ten mien -> CHAN' );
assert_false( sitetop_campaign_allows_url( $__campGon, 'https://blog.weba.com/' ), 'Camp luu gon: ten mien con -> CHAN (nhu luat hien hanh)' );
assert_equals( 'weba.com', sitetop_host_of( 'weba.com' ),        'host_of hieu URL khai gon' );
assert_equals( 'weba.com', sitetop_host_of( 'weba.com/abc' ),    'host_of hieu URL khai gon co duong dan' );
assert_equals( '',         sitetop_host_of( 'javascript:x' ),    'host_of KHONG bia host cho javascript:' );
/* Chuỗi gõ nhầm KHÔNG được biến thành tên miền: phần host phải có dấu chấm mới thêm https://.
   Thiếu chốt này thì camp có URL hỏng bị tính là "có thêm một tên miền" (bên .net bắt được). */
assert_equals( '',         sitetop_host_of( 'khong-phai-url' ), 'Chuoi khong co dau cham -> host RONG nhu truoc nay' );
assert_equals( '',         sitetop_host_of( 'abcxyz/trang' ),   'Go nham kem duong dan -> van host RONG' );
assert_equals( 'a.com',    sitetop_host_of( 'a.com/khong-phai-url' ), 'Co dau cham o ten mien -> van hieu binh thuong' );
assert_equals( sitetop_url_key( 'https://weba.com/abc' ), sitetop_url_key( 'weba.com/abc' ), 'url_key: khai gon va khai du la MOT' );

// ── 2. Chỗ gọi: chỉ camp Direct mới bật cờ ──────────────────────────────
$goi = array(
    'includes/admin/tabs/tab-campaigns.php' => '$dest = sitetop_sanitize_destination_urls($dest_in, $task_type === \'traffic_direct\');',
    'includes/admin-dashboard.php'          => '$dest = sitetop_sanitize_destination_urls($_POST[\'destination_urls\'], ($camp->campaign_type ?? \'\') === \'traffic_direct\');',
);
foreach ( $goi as $tep => $can ) {
    assert_true( strpos( file_get_contents( dirname( __DIR__, 2 ) . '/' . $tep ), $can ) !== false, "Cho goi bat co dung dieu kien Direct: $tep" );
}
$__cust = file_get_contents( dirname( __DIR__, 2 ) . '/includes/customer-campaign-ajax.php' );
assert_equals( 2, substr_count( $__cust, "sitetop_sanitize_destination_urls( \$_POST['destination_urls']" ), 'Ben khach hang co dung 2 cho goi (tao + sua)' );
assert_equals( 2, substr_count( $__cust, "\$task_type === 'traffic_direct' )" ), '...ca hai deu truyen dieu kien Direct' );

// ── 3. Trang nhiệm vụ: bắt gõ tay URL ───────────────────────────────────
$__pu = file_get_contents( dirname( __DIR__, 2 ) . '/page-unlock.php' );
assert_true( strpos( $__pu, "\$url_nocopy = ( \$campaign_type === 'traffic_direct' ) && ! empty( \$campaign->kw_bat_go_tay );" ) !== false,
    'Chi bat go tay URL khi VUA la camp Direct VUA bat co' );
// Luật của camp Search phải còn nguyên văn
assert_true( strpos( $__pu, '$kw_nocopy = ( $sitetop_kw_len <= 11 ) || ! empty( $campaign->kw_bat_go_tay );' ) !== false,
    'Luat chan copy TU KHOA cua camp Search giu nguyen' );
// Hai nhánh Direct dùng CHUNG một ô URL — không còn markup chép đôi
assert_equals( 2, substr_count( $__pu, '<?php echo $sitetop_o_url_dich; ?>' ), 'Ca hai nhanh Direct dung chung o URL dung san' );
assert_equals( 1, substr_count( $__pu, 'onclick="copyTargetUrl()">' ), 'Nut Copy chi con dung 1 cho (trong bien dung san), khong chep doi trong HTML' );
$__on  = substr( $__pu, strpos( $__pu, 'if ( $url_nocopy ) {' ), 1200 );
$__tren = substr( $__on, 0, strpos( $__on, '} else {' ) );
$__duoi = substr( $__on, strpos( $__on, '} else {' ) );
assert_true( strpos( $__tren, 'kw-nocopy' ) !== false && strpos( $__tren, '<span class="url-display' ) !== false,
    'Ban go tay: URL la the <span> co kw-nocopy (chan copy doc duoc vung chon)' );
assert_true( strpos( $__tren, 'copyTargetUrl' ) === false && strpos( $__tren, '<button' ) === false,
    'Ban go tay: KHONG co nut nao ca (chu site chot 21/09: bo ca nut "Da go xong")' );
assert_true( strpos( $__duoi, 'copyTargetUrl' ) !== false && strpos( $__duoi, 'id="target-url-input"' ) !== false,
    'Ban thuong (OFF): giu nguyen o nhap + nut Copy nhu cu' );
/* Bỏ nút thì mất chỗ báo server "user đã nhận URL" (mốc target_visited_at — camp 2 bước
   lấy đó tính công bước 1). Thay bằng lần ĐẦU user rời trang, và chỉ cho camp bật cờ. */
assert_true( strpos( $__pu, 'if (document.hidden && !_daBaoGoTay) { _daBaoGoTay = true; trackDirect(); }' ) !== false,
    'Roi trang lan dau -> bao server thay cho cu bam nut' );
assert_true( strpos( $__pu, 'var _daBaoGoTay = false;' ) !== false,
    'Co PHAI bat dau bang false — dat true la khong bao gio bao server' );
$__vtBao = strpos( $__pu, '_daBaoGoTay' );
$__vtIf  = strpos( $__pu, '<?php if ( $url_nocopy ): ?>' );
assert_true( $__vtIf !== false && $__vtIf < $__vtBao,
    'Tin hieu do CHI chay cho camp bat go tay (nam trong if $url_nocopy)' );
assert_equals( 0, substr_count( $__pu, 'daGoTayUrl' ), 'Khong con dau vet nut "Da go xong"' );

// Phóng to cho bản điện thoại + KHÔNG cắt bớt URL (user phải đọc hết mới gõ được)
assert_equals( 2, substr_count( $__pu, "omni<?php echo \$url_nocopy ? ' go-tay' : ''; ?>" ),
    'Ca hai nhanh Direct deu gan class go-tay de CSS phong to rieng' );
/* Chủ site chốt 21/09/2026: BẬT hay TẮT nút gạt thì ô URL cũng to bằng nhau, nên cỡ chữ
   khai ở quy tắc CHUNG .url-copy-box.omni .url-display (máy tính 28px, điện thoại 18px). */
assert_true( strpos( $__pu, '.url-copy-box.omni .url-display{border:none;background:transparent;padding:10px 8px 10px 0;font-family:inherit;font-size:28px' ) !== false,
    'May tinh: ca hai ban URL deu 28px' );
assert_true( strpos( $__pu, '.url-copy-box.omni .url-display{font-size:18px;padding:8px 6px 8px 0}' ) !== false,
    'Dien thoai: ca hai ban URL deu 18px' );
assert_false( strpos( $__pu, 'font-size:13.5px;color:#202124' ) !== false, 'Khong con co chu cu 13.5px o o URL' );
/* Cỡ chữ PHẢI khai kèm .url-copy-box.omni.go-tay — quy tắc cũ .url-copy-box.omni .url-display
   nặng 3 class, viết "span.url-display{font-size...}" là thua độ ưu tiên và cỡ chữ không đổi
   (đã mắc thật 21/09: tưởng đã tăng lên 18px mà thực tế màn hình vẫn 13.5px). */
assert_true( strpos( $__pu, '.url-copy-box.omni.go-tay span.url-display{line-height:1.4;font-weight:600' ) !== false,
    'Ban go tay: chu dam + xuong dong, co chu lay tu quy tac chung' );
assert_false( (bool) preg_match( '#\n\s*span\.url-display\{[^}]*font-size#', $__pu ),
    'KHONG duoc khai co chu o quy tac span.url-display tran (thua do uu tien)' );
assert_true( strpos( $__pu, 'span.url-display{display:block;white-space:normal;word-break:break-all' ) !== false,
    'URL xuong dong het, KHONG cat bang ba cham — cat la user khong go lai duoc' );
assert_true( strpos( $__pu, "\$url_nocopy ? 'Gõ địa chỉ sau vào trình duyệt:' : 'Copy URL sau và dán vào trình duyệt:'" ) !== false,
    'Chu huong dan doi theo: bat go tay thi khong noi "Copy URL"' );
assert_true( strpos( $__pu, 'span.url-display.kw-nocopy{user-select:none' ) !== false, 'Co CSS chan boi den cho URL dang chu' );
/* Camp Direct có thể lưu URL khai gọn -> mọi chỗ ĐỌC tên miền từ target_url phải đi qua
   sitetop_them_scheme(), không thì trang nhiệm vụ hiện "Tìm kết quả từ" bỏ trống. */
assert_true( strpos( $__pu, "parse_url(sitetop_them_scheme(\$campaign->target_url ?? ''), PHP_URL_HOST)" ) !== false,
    'Trang nhiem vu: doc ten mien qua sitetop_them_scheme' );
foreach ( array( 'page-customer-dashboard.php', 'includes/customer-load-more.php', 'includes/admin/tabs/tab-campaigns.php', 'includes/customer-campaign-ajax.php' ) as $__t ) {
    $__n = file_get_contents( dirname( __DIR__, 2 ) . '/' . $__t );
    assert_equals( 0, preg_match_all( '#parse_url\(\s*\$(c|vh|row)->target_url#', $__n ) + preg_match_all( '#parse_url\(\s*\$target_url,#', $__n ),
        "Khong con cho nao doc thang target_url bang parse_url: $__t" );
}

// ── 4. Giao diện admin: nút gạt hiện cho CẢ Search lẫn Direct ───────────
$__tc = file_get_contents( dirname( __DIR__, 2 ) . '/includes/admin/tabs/tab-campaigns.php' );
assert_true( strpos( $__tc, "\$kw_bat_go_tay = (in_array(\$task_type, array('keyword_search','traffic_direct'), true) && !empty(\$_POST['kw_bat_go_tay'])) ? 1 : 0;" ) !== false,
    'Tao camp: nhan co cho CA keyword_search lan traffic_direct' );
assert_true( strpos( $__tc, "if (_admEditTaskType === 'keyword_search' || _admEditTaskType === 'traffic_direct') fd.append('kw_bat_go_tay'" ) !== false,
    'Sua camp: gui co cho ca hai loai (truoc day Direct bi bo qua)' );
assert_true( strpos( $__tc, "traffic_direct:['Bắt gõ tay URL đích'" ) !== false, 'Camp Direct doi chu thanh "Bat go tay URL dich"' );
assert_true( strpos( $__tc, "keyword_search:['Bắt gõ tay keyword'" ) !== false, 'Camp Search giu nguyen chu cu' );
assert_true( strpos( $__tc, "id=\"admCreateGoTayWrap\"" ) !== false && strpos( $__tc, "id=\"admEditGoTayWrap\"" ) !== false,
    'Nut gat tach khoi o Tu khoa o ca form tao lan form sua (Direct an o Tu khoa)' );
// Loại khác (social...) không được có nút này: bảng ADM_GO_TAY_TXT chỉ khai đúng 2 loại
assert_equals( 0, substr_count( $__tc, "traffic_social:['Bắt gõ tay" ), 'Camp social KHONG co nut gat' );
assert_true( strpos( $__tc, "inp.name='destination_urls[]'; admSetDestInput(inp, admDestLaDirect(listId));" ) !== false,
    'O URL dat kieu theo loai camp (Direct: text, con lai: url)' );

$__cd = file_get_contents( dirname( __DIR__, 2 ) . '/page-customer-dashboard.php' );
assert_true( strpos( $__cd, 'cfSetDestInput(inp, cfDestLaDirect(listId));' ) !== false, 'Form khach hang: o URL cung dat kieu theo loai camp' );
assert_true( strpos( $__cd, "inp.type = laDirect ? 'text' : 'url';" ) !== false, 'Form khach hang: chi Direct moi doi sang text' );

echo "  ✓ traffic direct: bat go tay URL + khai URL gon\n";
