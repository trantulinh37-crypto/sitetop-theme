<?php
/* CACHE BIÊN cho /top.js + /widget.js — 13/09/2026.

   Điều sống còn mà bộ test này canh: THỨ TỰ trong sitetop_serve_widget_js().
   nocache_headers() phải chạy TRƯỚC include widget.js.php, và header cache-được phải
   đặt SAU include. Nhờ thứ tự đó, 8 lối thoát sớm của widget.js.php (chặn IP spam,
   quá tải, thử thách DDoS) exit ngay trong include và giữ nguyên `no-store` — không
   bao giờ lọt vào cache biên. Đảo thứ tự lại là một phản hồi rỗng `(function(){})();`
   sinh ra cho MỘT ip bị chặn sẽ được Cloudflare phát cho MỌI khách trên MỌI web
   khách suốt s-maxage giây. Widget biến mất toàn hệ thống, hỏng âm thầm.

   BẪY khi viết test ở đây: khối comment trong functions.php có chứa nguyên văn
   `max-age=14400`, `no-store`, `s-maxage=120`, `private`. strpos trên mã thô sẽ khớp
   vào COMMENT và xanh giả. Nên mọi khẳng định dưới đây chạy trên bản ĐÃ LỘT COMMENT. */

$__fn_src = file_get_contents( dirname( __DIR__, 2 ) . '/functions.php' );
assert_true( $__fn_src !== '' && $__fn_src !== false, 'Phai doc duoc functions.php' );

// --- Cắt đúng thân hàm sitetop_serve_widget_js ---
$__bat = strpos( $__fn_src, 'function sitetop_serve_widget_js()' );
assert_true( $__bat !== false, 'Phai co ham sitetop_serve_widget_js' );
$__het = strpos( $__fn_src, "\n}", $__bat );
assert_true( $__het !== false, 'Phai tim duoc cuoi than ham' );
$__than_tho = substr( $__fn_src, $__bat, $__het - $__bat );

// --- Lột comment: /* ... */ và // ... ---
$__than = preg_replace( '#/\*.*?\*/#s', '', $__than_tho );
$__than = preg_replace( '#//[^\n]*#', '', $__than );

// Tự kiểm: bản lột phải thật sự sạch, không thì mọi khẳng định dưới đây vô nghĩa.
assert_true( strpos( $__than_tho, 'max-age=14400' ) !== false,
    'Ban THO phai con chuoi max-age=14400 trong comment (neu khong, phep tu kiem nay het y nghia)' );
assert_true( strpos( $__than, 'max-age=14400' ) === false,
    'Ban DA LOT COMMENT tuyet doi khong duoc con max-age=14400 — lot comment that bai' );

// --- Ba mốc thứ tự, neo trên dạng gọi THẬT (có dấu ;) ---
$__p_nocache = strpos( $__than, 'nocache_headers();' );
$__p_include = strpos( $__than, "include SITETOP_DIR . '/widget.js.php';" );
$__p_cache   = strpos( $__than, "header( 'Cache-Control: public," );

assert_true( $__p_nocache !== false, 'PHAI goi nocache_headers();' );
assert_true( $__p_include !== false, 'PHAI include widget.js.php' );
assert_true( $__p_cache   !== false, 'PHAI co header Cache-Control cache-duoc' );

assert_true( $__p_nocache < $__p_include,
    'SONG CON: nocache_headers() phai dat TRUOC include widget.js.php' );
assert_true( $__p_include < $__p_cache,
    'SONG CON: header cache-duoc phai dat SAU include — de loi thoat som giu no-store' );

// --- Header cache-được: đúng từng thành phần ---
$__co_hdr = preg_match(
    "#header\(\s*'Cache-Control:\s*public,\s*max-age=0,\s*s-maxage=(\d+),\s*must-revalidate'\s*\);#",
    $__than, $__m );
assert_true( $__co_hdr === 1,
    'Header cache-duoc phai dung dang: public, max-age=0, s-maxage=N, must-revalidate' );

if ( $__co_hdr === 1 ) {
    assert_true( (int) $__m[1] > 0 && (int) $__m[1] <= 300,
        's-maxage phai trong khoang 1..300 giay — dai hon la cai dat widget lan qua cham' );
}

// max-age cho TRÌNH DUYỆT bắt buộc = 0: khác 0 là web khách dong bang ban cu.
assert_true( preg_match( "#'Cache-Control: public, max-age=(?!0,)#", $__than ) === 0,
    'TUYET DOI: max-age cho trinh duyet phai la 0, khac 0 la dong bang web khach' );

// --- CHẶNG 1: header cache-được phải còn nằm sau điều kiện tham số thử ---
$__p_if = strpos( $__than, "isset( \$_GET['thu_cache_bien'] )" );
assert_true( $__p_if !== false,
    'CHANG 1: header cache-duoc phai con khoa sau ?thu_cache_bien' );
assert_true( $__p_if !== false && $__p_if < $__p_cache,
    'CHANG 1: dieu kien thu_cache_bien phai dung TRUOC header cache-duoc' );

// --- Nhánh mặc định (khách thật) vẫn phải là private ---
assert_true( strpos( $__than, "header( 'Cache-Control: private, no-cache, must-revalidate, max-age=0' );" ) !== false,
    'Khach that PHAI van nhan private, no-cache' );

// --- widget.js.php tuyệt đối không được tự đặt header cache-được ---
$__wjs = file_get_contents( dirname( __DIR__, 2 ) . '/widget.js.php' );
$__wjs_code = preg_replace( '#/\*.*?\*/#s', '', $__wjs );
$__wjs_code = preg_replace( '#//[^\n]*#', '', $__wjs_code );
assert_true( strpos( $__wjs_code, 's-maxage' ) === false,
    'widget.js.php TUYET DOI khong duoc dat s-maxage — loi thoat som se bi cache va phat cho moi khach' );
assert_true( strpos( $__wjs_code, "Cache-Control: public" ) === false,
    'widget.js.php TUYET DOI khong duoc dat Cache-Control: public' );
