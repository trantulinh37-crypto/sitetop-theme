<?php
/** 16/11/2026 chủ site: admin sửa thông tin khách / chỉnh ngân sách thì KHÔNG gửi email cho khách. */
$__d = dirname( __DIR__, 2 );
$__a = (string) file_get_contents( $__d . '/includes/admin-dashboard.php' );
$__i = strpos( $__a, '$result = wp_update_user($update);' );
$__tr = substr( $__a, max( 0, $__i - 400 ), 400 );
assert_true( $__i !== false && strpos( $__tr, "add_filter('send_email_change_email', '__return_false', 99);" ) !== false && strpos( $__tr, "add_filter('send_password_change_email', '__return_false', 99);" ) !== false, 'Admin sua email/mat khau khach: tat email loi WP' );
$__l = (string) file_get_contents( $__d . '/includes/low-balance-alerts.php' );
assert_true( strpos( $__l, "reference_type = 'dieu_chinh_chay'" ) !== false && strpos( $__l, '// thấp chỉ vì admin chỉnh' ) !== false, 'Canh bao so du thap bo qua phan admin chinh ngan sach' );
assert_true( strpos( (string) file_get_contents( $__d . '/includes/dieu-chinh-da-chay.php' ), 'wp_mail' ) === false, 'Sua Da chay khong goi wp_mail' );
