<?php
/* CHẤM CHẤT LƯỢNG NGUỒN Ở BẢNG DUYỆT RÚT TIỀN — chủ site yêu cầu 03/10/2026:
   "nhìn qua thông số chi tiết là dễ biết nguồn nào chuẩn chất lượng hơn".

   Hai thứ phải đúng:
   1. Hàm PHP sitetop_ua_chrome_gia() phải cho CÙNG kết quả với điều kiện trong câu SQL của
      sitetop_do_ua_khai_may(). Hai bản chạy ở hai chỗ khác nhau, lệch nhau là admin đọc một
      đằng chốt tự động làm một nẻo.
   2. Hàm chấm phải xếp đúng mức trên số liệu THẬT đo được ngày 03/10. */

$__cn_goc = dirname( __DIR__, 2 );
$__cn_bh  = (string) file_get_contents( $__cn_goc . '/includes/behavior-analytics.php' );
$__cn_ad  = (string) file_get_contents( $__cn_goc . '/includes/admin-dashboard.php' );
$__cn_tw  = (string) file_get_contents( $__cn_goc . '/includes/admin/tabs/tab-withdrawals.php' );

/* ---- Bộ user-agent thật, chép từ production .net ---- */
$__cn_ua = array(
    array( 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36', 0, 'Chrome rut gon' ),
    array( 'Mozilla/5.0 (Linux; Android 13; SM-A556B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.200 Mobile Safari/537.36', 1, 'Chrome 131 khai Android 13' ),
    array( 'Mozilla/5.0 (Linux; Android 14; 2311DRK48G) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Mobile Safari/537.36', 1, 'Chrome 128 khai Android 14' ),
    array( 'Mozilla/5.0 (Linux; Android 11; vivo 1906) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/131.0.6778.200 Mobile Safari/537.36 VivoBrowser/16.5.2.0', 0, 'VivoBrowser' ),
    array( 'Mozilla/5.0 (Linux; Android 13; Redmi Note 11 Build/TKQ1.221114.001) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.7049.79 Mobile Safari/537.36 XiaoMi/MiuiBrowser/14.54.0-gn', 0, 'MiuiBrowser' ),
    array( 'Mozilla/5.0 (Linux; U; Android 13; vi-vn; CPH2365 Build/TP1A.220905.001) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.5970.168 Mobile Safari/537.36 HeyTapBrowser/45.14.7.1', 0, 'HeyTapBrowser' ),
    array( 'Mozilla/5.0 (Linux; Android 12; SM-A032F) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/23.0 Chrome/115.0.0.0 Mobile Safari/537.36', 0, 'Samsung Internet' ),
    array( 'Mozilla/5.0 (Android 13; Mobile; rv:131.0) Gecko/131.0 Firefox/131.0', 0, 'Firefox' ),
    array( 'Mozilla/5.0 (Linux; Android 9; vivo Y19 Build/PPR1.180610.011) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.61 Mobile Safari/537.36', 0, 'Chrome 101 — may cu' ),
    array( 'Mozilla/5.0 (Linux; Android 12; V2111 Build/SP1A; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/131.0.0.0 Mobile Safari/537.36', 0, 'WebView trong app' ),
    array( 'Mozilla/5.0 (Linux; Android 13; SM-A556B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36 EdgA/131.0.2903.87', 0, 'Edge Android' ),
    array( 'Mozilla/5.0 (Linux; Android 12; V2111) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36 OPR/89.0.0.0', 0, 'Opera Android' ),
    array( 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 Version/18.7 Mobile/15E148 Safari/604.1', 0, 'iPhone' ),
    array( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36', 0, 'May tinh' ),
    /* Chuỗi có HAI mẩu "Chrome/". MySQL lấy mẩu CUỐI (SUBSTRING_INDEX ... -1), nên PHP phải
       dùng strripos chứ không phải stripos — đọc nhầm mẩu đầu thì ra bản 101 và bỏ lọt. */
    array( 'Mozilla/5.0 (Linux; Android 13; SM-A556B) AppleWebKit/537.36 Chrome/101.0.0.0 Mobile Safari/537.36 Wrapper/Chrome/131.0.0.0', 1, 'Hai mau Chrome/ — phai doc mau cuoi' ),
);

/* ---- 1. Hàm PHP: chạy thật trong tiến trình riêng ---- */
assert_true( preg_match( '#(function sitetop_ua_chrome_gia\( \$ua \) \{.*?\n\})#s', $__cn_bh, $__cn_m ) === 1,
    'Lay duoc ham sitetop_ua_chrome_gia tu ma nguon' );

$__cn_ban = "<?php\n" . $__cn_m[1] . "\n\$ra = array();\nforeach (" . var_export( array_column( $__cn_ua, 0 ), true ) .
            " as \$ua) \$ra[] = sitetop_ua_chrome_gia(\$ua) ? 1 : 0;\necho json_encode(\$ra);\n";
$__cn_f = sys_get_temp_dir() . '/st-uagia-' . getmypid() . '.php';
file_put_contents( $__cn_f, $__cn_ban );
$__cn_php = json_decode( (string) shell_exec( 'php ' . escapeshellarg( $__cn_f ) . ' 2>&1' ), true );
@unlink( $__cn_f );
assert_true( is_array( $__cn_php ) && count( $__cn_php ) === count( $__cn_ua ), 'Chay duoc ham PHP' );
foreach ( $__cn_ua as $i => $u ) {
    assert_equals( $u[1], $__cn_php[ $i ], 'PHP: ' . $u[2] );
}

/* ---- 2. PHP và SQL phải khớp nhau từng chuỗi một ---- */
assert_true( preg_match( '#SUM\( user_agent LIKE %s(.*?)\) AS khai#s', $__cn_bh, $__cn_m2 ) === 1,
    'Lay duoc dieu kien SQL' );
$dk = preg_replace( '#LOCATE\(([^,]+), user_agent\)#', 'INSTR(user_agent, $1)', $__cn_m2[1] );
$dk = str_replace( array( 'AS UNSIGNED', '%s' ), array( 'AS INTEGER', "'Android 10; K)'" ), $dk );
$dk = "user_agent LIKE '%Android%'" . $dk;

$db = new PDO( 'sqlite::memory:' );
$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$db->sqliteCreateFunction( 'SUBSTRING_INDEX', function ( $c, $d, $n ) {
    $ph = explode( $d, (string) $c );
    return $n < 0 ? implode( $d, array_slice( $ph, $n ) ) : implode( $d, array_slice( $ph, 0, $n ) );
}, 3 );
$db->exec( 'CREATE TABLE v (ua TEXT)' );
$st = $db->prepare( 'INSERT INTO v (ua) VALUES (?)' );
foreach ( $__cn_ua as $u ) $st->execute( array( $u[0] ) );
$sql = array();
foreach ( $db->query( 'SELECT ua, (' . str_replace( 'user_agent', 'ua', $dk ) . ') AS khai FROM v' ) as $r ) {
    $sql[ $r['ua'] ] = (int) $r['khai'];
}
foreach ( $__cn_ua as $i => $u ) {
    assert_equals( $__cn_php[ $i ], $sql[ $u[0] ],
        'SONG CON: PHP va SQL phai khop — ' . $u[2] );
}

/* ---- 3. Hàm chấm: chạy thật với số liệu đo được 03/10 ---- */
assert_true( preg_match( '#(function sitetop_wd_cham_nguon\(.*?\n\})\n\n#s', $__cn_ad, $__cn_m3 ) === 1,
    'Lay duoc ham cham' );
$__cn_ban2 = "<?php\n" . $__cn_m3[1] . "
\$ca = array(
  'monpro'         => array(76802, 76265, 66049, 19),
  'yumimod'        => array(6958, 6958, 6888, 6),
  'Hoanghieu123'   => array(3587, 1431, 1542, 55),
  'Pminh2011'      => array(8629, 172, 4832, 54),
  'phongdepchai27' => array(18019, 36, 8468, 139),
  'trongvipporo222'=> array(1382, 15, 1174, 15),
  'ky_be'          => array(150, 0, 90, 4),
  'ky_vua_du'      => array(300, 0, 150, 5),
);
\$ra = array();
foreach (\$ca as \$ten => \$c) { \$k = sitetop_wd_cham_nguon(\$c[0], \$c[1], \$c[2], \$c[3]);
  \$ra[\$ten] = array('muc'=>\$k['muc'], 'nhan'=>\$k['nhan'], 'so_dau'=>count(\$k['dau']),
                     'ua_pc'=>\$k['ua_pc'], 'ip_luot'=>\$k['ip_luot'],
                     'dau_muc'=>array_column(\$k['dau'],'muc')); }
echo json_encode(\$ra);
";
$__cn_f2 = sys_get_temp_dir() . '/st-cham-' . getmypid() . '.php';
file_put_contents( $__cn_f2, $__cn_ban2 );
$__cn_c = json_decode( (string) shell_exec( 'php ' . escapeshellarg( $__cn_f2 ) . ' 2>&1' ), true );
@unlink( $__cn_f2 );
assert_true( is_array( $__cn_c ), 'Chay duoc ham cham' );

assert_equals( 'xau', $__cn_c['monpro']['muc'],  'monpro 99,3% khai gia -> do' );
assert_equals( 'xau', $__cn_c['yumimod']['muc'], 'yumimod 100% + 6 kieu may -> do' );
assert_equals( 'xau', $__cn_c['Hoanghieu123']['muc'], 'Hoanghieu123 39,9% -> do (tai khoan pha)' );
assert_equals( 'dep', $__cn_c['Pminh2011']['muc'], 'SONG CON: Pminh2011 2,0% -> XANH, khong duoc cham oan nguoi that' );
assert_equals( 'dep', $__cn_c['phongdepchai27']['muc'], 'phongdepchai27 0,2% -> xanh' );
assert_equals( 'ngo', $__cn_c['trongvipporo222']['muc'],
    'trongvipporo222: khai gia 1,1% nhung IP/luot 0,85 -> vang, KHONG do (dau IP la dau yeu)' );
assert_equals( 'it',  $__cn_c['ky_be']['muc'], 'Ky 150 luot -> noi thang la chua du du lieu' );
assert_equals( 0,     $__cn_c['ky_be']['so_dau'], 'Ky qua be thi khong liet ke dau nao' );
assert_equals( 'dep', $__cn_c['ky_vua_du']['muc'], 'Ky 300 luot, 5 kieu may -> van xanh (duoi 500 luot khong xet kieu may)' );

// Con số hiện ra phải đúng
assert_equals( 99.3, $__cn_c['monpro']['ua_pc'], 'Ty le khai gia cua monpro' );
assert_equals( 0.86, $__cn_c['monpro']['ip_luot'], 'IP tren luot cua monpro' );
assert_equals( 3, $__cn_c['monpro']['so_dau'], 'Du ba dau' );

/* ---- 4. Trang admin phải vẽ bảng chấm, và vẽ từ số máy chủ gửi xuống ---- */
assert_true( strpos( $__cn_tw, 'h += wdChamNguon(x.cham);' ) !== false, 'Thieu bang cham o bang chi tiet' );
assert_true( strpos( $__cn_ad, "'cham'         => \$cham," ) !== false, 'May chu phai gui ket qua cham xuong' );
/* Chấm chỉ được làm ở MỘT chỗ. Nếu lời văn và mốc số bị chép sang JS thì sau này sửa một
   bên quên bên kia, admin đọc ra hai kết quả khác nhau cho cùng một kỳ. */
assert_true( strpos( $__cn_tw, 'Nghi lưu lượng giả lập' ) === false,
    'SONG CON: loi cham phai nam o may chu, KHONG chep sang JS' );
assert_true( strpos( $__cn_tw, 'Nguồn chuẩn' ) === false,
    'SONG CON: nhan "Nguon chuan" cung phai den tu may chu' );
assert_true( strpos( $__cn_ad, 'Nghi lưu lượng giả lập' ) !== false, 'Loi cham nam o admin-dashboard.php' );

/* Nối dây: đếm trong vòng lặp rồi đưa vào hàm chấm. Thiếu một trong hai thì bảng chấm vẫn
   hiện nhưng luôn báo 0% — tức luôn xanh, sai nguy hiểm hơn là không có gì. */
assert_true( strpos( $__cn_ad, "sitetop_ua_chrome_gia( \$r->user_agent ) ) \$ua_gia++;" ) !== false,
    'SONG CON: phai dem luot khai gia trong vong lap' );
assert_true( strpos( $__cn_ad, 'sitetop_wd_cham_nguon( count( $tasks ), $ua_gia, count( $ip_map ), count( $dev_map ) )' ) !== false,
    'SONG CON: phai dua so dem vao ham cham, dung thu tu tham so' );
