<?php
/* CHỐNG XẢ DỒN — khối "Script polling check code ready status" trong page-unlock.php.

   Đo trên sitetop.net 18/09/2026: 87% request admin-ajax là thăm dò của trang nhiệm vụ.
   Chrome Android GIỮ các request tạo ra lúc trang chạy nền rồi xả dồn 13–44 cái trong 1
   giây khi user quay lại; vài user trùng giây là vượt trần ~100 request/giây của hosting
   → LiteSpeed trả 503 (kể cả request widget trên web khách).

   Ba chốt phải giữ:
     1. tab ẩn thì KHÔNG hỏi mã, KHÔNG gửi nhịp tim;
     2. request kiểm tra mã trước chưa về thì không gửi chồng (quá 10 giây coi như treo);
     3. quay lại tab thì hỏi ngay 1 lần (mã vẫn tự điền gần như tức thì).
   Hành vi thật đã chạy thử bằng node với đồng hồ giả (tab ẩn 60s: cũ 30+12 request,
   mới 0+0). Test này khoá phần mã để không ai vô tình gỡ mất.

   Bỏ CHÚ THÍCH trước khi dò — chú thích nhắc đúng những câu lệnh này, dò không bỏ là
   test xanh dù lệnh thật đã bị xoá. */

$__src = file_get_contents( dirname( __DIR__, 2 ) . '/page-unlock.php' );
$__i   = strpos( $__src, '<!-- Script polling check code ready status -->' );
assert_true( $__i !== false, 'Tim thay khoi tham do trong page-unlock.php' );
if ( $__i === false ) return;
$__a  = strpos( $__src, '<script>', $__i ) + 8;
$__js = substr( $__src, $__a, strpos( $__src, '</script>', $__a ) - $__a );
$__js = preg_replace( '#/\*.*?\*/#s', '', $__js );          // chú thích khối
$__js = preg_replace( '#(^|[^:\'"])//[^\n]*#', '$1', $__js ); // chú thích dòng (chừa http://)

// Tách thân checkCodeReady và thân callback nhịp tim
$__ck = preg_match( '#function checkCodeReady\(\) \{(.*?)\n        \}\n#s', $__js, $__m ) ? $__m[1] : '';
$__hb = preg_match( '#var heartbeatInterval = setInterval\(function\(\) \{(.*?)\}, 5000\);#s', $__js, $__m2 ) ? $__m2[1] : '';
assert_true( $__ck !== '', 'Tach duoc than checkCodeReady' );
assert_true( $__hb !== '', 'Tach duoc than callback nhip tim 5s' );

// 1) Tab ẩn: không hỏi, không gửi nhịp tim — và chốt phải đứng TRƯỚC lệnh gửi
$__p_an  = strpos( $__ck, 'if (document.hidden) return;' );
$__p_gui = strpos( $__ck, 'fetch(' );
assert_true( $__p_an !== false && $__p_gui !== false && $__p_an < $__p_gui, 'checkCodeReady: tab an thi return TRUOC khi fetch' );
$__p_an2 = strpos( $__hb, 'if (document.hidden) return;' );
$__p_bc  = strpos( $__hb, 'sendBeacon(' );
assert_true( $__p_an2 !== false && $__p_bc !== false && $__p_an2 < $__p_bc, 'Nhip tim: tab an thi return TRUOC khi sendBeacon' );

// 2) Không gửi chồng, có lối thoát khi treo, và mọi đường về đều nhả cờ
assert_true( strpos( $__ck, 'if (_dangHoi && Date.now() - _dangHoi < 10000) return;' ) !== false, 'Chan gui chong, qua 10s thi cho gui lai' );
assert_true( strpos( $__ck, '_dangHoi = Date.now();' ) !== false && strpos( $__ck, '_dangHoi = Date.now();' ) < $__p_gui, 'Danh dau dang bay TRUOC khi fetch' );
assert_true( substr_count( $__ck, '_dangHoi = 0;' ) >= 2, 'Nha co o CA nhanh then lan nhanh catch (khong thi ket vinh vien)' );
$__p_then = strpos( $__ck, '.then(function(data) {' );
assert_true( $__p_then !== false && strpos( $__ck, '_dangHoi = 0;', $__p_then ) !== false
    && strpos( $__ck, '_dangHoi = 0;', $__p_then ) < strpos( $__ck, 'return;', $__p_then ),
    'Nhanh then nha co TRUOC lenh return som (ma chua san sang)' );

// 3) Quay lại tab: hỏi ngay 1 lần, chỉ khi còn trong 10 phút thăm dò
assert_true( (bool) preg_match( "#document\.addEventListener\('visibilitychange', function\(\) \{\s*if \(!document\.hidden && checkInterval\) checkCodeReady\(\);#", $__js ),
    'Quay lai tab: hoi ngay 1 lan (khi con trong 10 phut)' );

// Những gì KHÔNG được đổi: nhịp 2s / 5s, hỏi ngay lúc mở trang, dừng sau 10 phút
assert_true( strpos( $__js, 'checkInterval = setInterval(checkCodeReady, 2000);' ) !== false, 'Giu nhip hoi ma 2 giay' );
assert_true( strpos( $__js, '}, 5000);' ) !== false, 'Giu nhip tim 5 giay' );
assert_true( (bool) preg_match( '#checkInterval = setInterval\(checkCodeReady, 2000\);\s*checkCodeReady\(\);#', $__js ), 'Van hoi ngay 1 lan luc mo trang' );
assert_true( strpos( $__js, '}, 600000);' ) !== false, 'Van dung tham do sau 10 phut' );

echo "  ✓ tham do an tab (chong xa don request)\n";
