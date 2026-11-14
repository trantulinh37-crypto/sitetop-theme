<?php
/* CHỦ SITE CHỐT 14/11/2026: bắt được tool KHÔNG khoá tài khoản chủ shortlink, KHÔNG khoá IP theo giờ.
   Chỉ chặn đúng LƯỢT (phiên) đang dùng tool; quay lại làm bình thường không tool là chạy lại ngay.
   Test này canh không ai lỡ thêm lại khoá dài hạn vào luồng chặn tool. */
$__kk_s = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/shortlink-ajax.php' );
foreach ( array( 'sitetop_khoa_ma_tool', 'sitetop_khoa_ma_ip_', 'sitetop_khoa_cap_ma_vi_tool' ) as $__kk_x )
    assert_true( strpos( $__kk_s, $__kk_x ) === false, 'Khong con khoa dai han: ' . $__kk_x );
// Hàm bắt tool chỉ gắn dấu theo PHIÊN, không đụng user meta.
$__kk_a = strpos( $__kk_s, 'function sitetop_canh_bao_gialap(' );
$__kk_b = strpos( $__kk_s, "\n    }\n}\n", (int) $__kk_a );
$__kk_f = substr( $__kk_s, (int) $__kk_a, $__kk_b - $__kk_a );
assert_true( $__kk_a !== false && strpos( $__kk_f, "set_transient( 'sitetop_gialap_' . \$sid" ) !== false, 'Gan dau theo phien' );
assert_true( strpos( $__kk_f, 'user_meta' ) === false, 'Khong ghi user meta khi bat tool' );
// get_code chặn theo dấu của CHÍNH phiên đó (phiên mới sạch không bị dính).
$__kk_g = substr( $__kk_s, strpos( $__kk_s, 'function sitetop_ajax_get_code()' ), 4000 );
assert_true( strpos( $__kk_g, "get_transient( 'sitetop_gialap_' . \$sid )" ) !== false, 'get_code chan theo dau cua phien' );
// Mở lại shortlink (phiên dùng lại cùng IP) xoá dấu tool của lượt trước -> quay lại làm bình thường là chạy.
$__kk_v = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/shortlink-functions.php' );
$__kk_c = strpos( $__kk_v, '$session_id = sitetop_create_visit_session( $shortlink, $ip );' );
$__kk_d = strpos( $__kk_v, "delete_transient( 'sitetop_gialap_' . \$session_id );", (int) $__kk_c );
assert_true( $__kk_c !== false && $__kk_d !== false && $__kk_d - $__kk_c < 1500, 'Mo lai link xoa dau tool cua phien dung lai' );
