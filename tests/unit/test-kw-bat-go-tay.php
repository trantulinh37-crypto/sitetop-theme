<?php
/* Bắt gõ tay keyword theo TỪNG CAMP — 15/09/2026.

   Luật cũ GIỮ NGUYÊN: từ khoá <= 11 ký tự tự bắt gõ tay + chặn copy (page-unlock.php).
   Cờ kw_bat_go_tay riêng từng camp: ON thì mọi từ khoá của camp đó đều bắt gõ tay, dài bao
   nhiêu cũng vậy. OFF (mặc định) thì y như cũ.

   KHÔNG tạo cơ chế mới: cờ chỉ được OR vào đúng biến $kw_nocopy — biến đó vốn điều khiển
   class .kw-nocopy ở 2 chỗ hiển thị, CSS chặn bôi đen và JS chặn copy/cut.

   Mặc định OFF là điều kiện sống còn: camp đang chạy không được đổi hành vi.

   BẪY: comment trong page-unlock.php chứa nguyên văn "kw_bat_go_tay" và "<= 11". Mọi khẳng
   định trên mã nguồn chạy ở bản ĐÃ LỘT COMMENT (bằng tokenizer — xem ghi chú ở $__lot). */

$__goc = dirname( __DIR__, 2 );
// Lột comment bằng TOKENIZER của PHP, KHÔNG dùng regex. tab-campaigns.php có chuỗi HTML
// accept="image/*" — regex bắt comment kiểu /* */ coi dấu mở trong đó là đầu comment rồi
// nuốt hàng trăm dòng phía sau, xoá mất cả modal sửa, test đỏ oan. Tokenizer chỉ gỡ
// T_COMMENT/T_DOC_COMMENT trong mã PHP, để nguyên HTML/JS. (Ghi chú này cố ý dùng //:
// viết trong khối /* */ thì chính cái regex được nhắc tới sẽ đóng comment sớm.)
$__lot = function ( $s ) {
    $out = '';
    foreach ( token_get_all( $s ) as $t ) {
        if ( is_array( $t ) && ( $t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT ) ) continue;
        $out .= is_array( $t ) ? $t[1] : $t;
    }
    return $out;
};
$__doc = function ( $f ) use ( $__goc ) { return file_get_contents( $__goc . '/' . $f ); };

/* ---------- 1. Hành vi: đúng luật như dòng trong page-unlock.php ---------- */
$__nocopy = function ( $kw, $campaign ) {
    $sitetop_kw_len = function_exists( 'mb_strlen' ) ? mb_strlen( $kw, 'UTF-8' ) : preg_match_all( '/./u', $kw );
    return ( $sitetop_kw_len <= 11 ) || ! empty( $campaign->kw_bat_go_tay );
};
$__off = (object) array( 'kw_bat_go_tay' => '0' );   // wpdb trả CHUỖI, không phải số
$__on  = (object) array( 'kw_bat_go_tay' => '1' );
$__cu  = (object) array();                           // camp cũ, chưa có cột

// OFF: luật cũ nguyên vẹn
assert_true(  $__nocopy( '12345678901', $__off ),        'OFF: 11 ky tu -> van bat go tay' );
assert_false( $__nocopy( '123456789012', $__off ),       'OFF: 12 ky tu -> cho copy nhu cu' );
assert_true(  $__nocopy( 'cửa cuốn', $__off ),           'OFF: tu khoa Viet ngan -> bat go tay' );
assert_false( $__nocopy( 'tỷ lệ nhà cái', $__off ),      'OFF: "ty le nha cai" 13 ky tu -> cho copy nhu cu' );
// ON: dài bao nhiêu cũng bắt gõ tay
assert_true(  $__nocopy( '123456789012', $__on ),        'ON: 12 ky tu -> bat go tay' );
assert_true(  $__nocopy( 'tỷ lệ nhà cái', $__on ),       'ON: "ty le nha cai" 13 ky tu -> bat go tay' );
assert_true(  $__nocopy( 'nệm cao su Hà Nội giá rẻ nhất', $__on ), 'ON: tu khoa rat dai -> bat go tay' );
assert_true(  $__nocopy( 'seo', $__on ),                 'ON: tu khoa ngan -> van bat go tay' );
// Camp cũ chưa có cột: rơi về luật độ dài, không lỗi
assert_false( $__nocopy( 'tỷ lệ nhà cái', $__cu ),       'Camp cu chua co cot -> giu luat do dai' );
assert_true(  $__nocopy( 'cửa cuốn', $__cu ),            'Camp cu, tu khoa ngan -> van bat go tay' );
assert_false( $__nocopy( 'tỷ lệ nhà cái', null ) === null, 'campaign null khong duoc lam hong' );

/* ---------- 2. Trang nhiệm vụ: dùng lại đúng cơ chế cũ ---------- */
$__pu = $__lot( $__doc( 'page-unlock.php' ) );
assert_true( strpos( $__pu, '$kw_nocopy = ( $sitetop_kw_len <= 11 ) || ! empty( $campaign->kw_bat_go_tay );' ) !== false,
    'SONG CON: $kw_nocopy PHAI = luat do dai HOAC co CAMP' );
assert_equals( 1, preg_match_all( '#\$kw_nocopy\s*=#', $__pu ),
    'Chi duoc gan $kw_nocopy DUNG MOT LAN — gan lan hai la de mat co CAMP' );
assert_equals( 2, substr_count( $__pu, "\$kw_nocopy ? ' kw-nocopy' : ''" ),
    'Ca 2 cho hien thi van phai lay class tu $kw_nocopy — dung lai co che cu' );
assert_true( strpos( $__pu, "document.querySelectorAll('.kw-nocopy')" ) !== false,
    'Chot chan copy/cut o JS van phai bam vao .kw-nocopy' );

/* ---------- 3. DB + đường lưu ---------- */
assert_true( strpos( $__doc( 'includes/database-setup.php' ), 'kw_bat_go_tay tinyint(1) NOT NULL DEFAULT 0' ) !== false,
    'Cot PHAI mac dinh 0 — camp hien tai khong duoc doi hanh vi' );
assert_true( strpos( $__doc( 'includes/shortlink-functions.php' ), "'kw_bat_go_tay'=>'%d'" ) !== false,
    'Phai cho phep luu kw_bat_go_tay khi sua camp' );
$__ad = $__lot( $__doc( 'includes/admin-dashboard.php' ) );
assert_true( strpos( $__ad, "'kw_bat_go_tay'=>(int)(\$c->kw_bat_go_tay ?? 0)" ) !== false,
    'Modal sua PHAI nhan lai trang thai — thieu la lan luu sau ghi de 0' );
assert_true( strpos( $__ad, "\$_POST['kw_bat_go_tay'] = (\$_POST['kw_bat_go_tay'] === '1') ? 1 : 0;" ) !== false,
    'Luu PHAI chi nhan dung 0/1' );

/* ---------- 4. Giao diện admin ---------- */
$__tab = $__lot( $__doc( 'includes/admin/tabs/tab-campaigns.php' ) );
assert_true( strpos( $__tab, 'accept="image/*"' ) !== false,
    'Lot comment KHONG duoc nuot HTML — chuoi accept="image/*" tung lam regex xoa mat modal sua' );
assert_true( strpos( $__tab, "\$kw_bat_go_tay = (\$task_type === 'keyword_search' && !empty(\$_POST['kw_bat_go_tay'])) ? 1 : 0;" ) !== false,
    'Tao camp: khong tick = 0, chi co nghia voi traffic tu khoa' );
assert_true( strpos( $__tab, "'kw_bat_go_tay' => \$kw_bat_go_tay," ) !== false,
    'Tao camp PHAI ghi co vao DB' );
assert_true( strpos( $__tab, "getElementById('admEditKwGoTay').checked = String(c.kw_bat_go_tay || '0') === '1';" ) !== false,
    'Modal sua PHAI nap trang thai nut gat' );
assert_true( strpos( $__tab, "fd.append('kw_bat_go_tay', document.getElementById('admEditKwGoTay').checked ? '1' : '0');" ) !== false,
    'Modal sua PHAI gui trang thai khi luu' );

// Nút gạt mặc định TẮT: không input nào của nó được có sẵn "checked"
preg_match_all( '#<input type="checkbox" value="1" [^>]*(adm_kw_go_tay|admEditKwGoTay)[^>]*>#', $__tab, $__nuts );
assert_equals( 2, count( $__nuts[0] ), 'Phai co dung 2 nut gat: form tao + modal sua' );
foreach ( $__nuts[0] as $__n ) {
    assert_true( strpos( $__n, 'checked' ) === false, 'Nut gat KHONG duoc bat san: ' . $__n );
}
// Nút gạt nằm TRONG khối từ khoá -> tự ẩn khi Traffic Direct
foreach ( array( 'id="admCreateKwWrap"' => 'id="adm_kw_go_tay"', 'id="admEditKwCell"' => 'id="admEditKwGoTay"' ) as $__vo => $__ruot ) {
    $__a = strpos( $__tab, $__vo ); $__b = strpos( $__tab, $__ruot );
    assert_true( $__a !== false && $__b !== false && $__a < $__b
        && strpos( substr( $__tab, $__a, $__b - $__a ), '</div>' ) === false,
        'Nut gat ' . $__ruot . ' PHAI nam trong ' . $__vo . ' de an theo khi Traffic Direct' );
}
