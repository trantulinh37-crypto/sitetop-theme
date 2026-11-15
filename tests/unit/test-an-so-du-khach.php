<?php
/* SỐ DƯ QUẢNG CÁO ẨN MẶC ĐỊNH (15/11/2026): mọi chỗ trang khách hiện số dư in "**.***.***đ", nút Xem/Ẩn bật tắt,
   không lưu trạng thái (tải lại là ẩn). Canh không ai in thẳng số dư ra trang nữa. */
$__s = (string) file_get_contents( dirname( __DIR__, 2 ) . '/page-customer-dashboard.php' );
assert_equals( 0, substr_count( $__s, 'sitetop_format_money($cust_balance)' ), 'Khong con cho nao in thang so du' );
assert_equals( 3, substr_count( $__s, '<span class="st-sodu" data-v="' ), 'Ca 3 cho (the vi, thanh mobile, tab Tai khoan) deu an' );
assert_equals( 3, substr_count( $__s, '">**.***.***đ</span>' ), 'Mac dinh hien **.***.***d' );
assert_true( strpos( $__s, 'function stXemSoDu(' ) !== false && strpos( $__s, 'class="st-sodu-nut"' ) !== false, 'Co nut Xem/An' );
assert_true( strpos( $__s, 'localStorage' ) === false || strpos( substr( $__s, strpos( $__s, 'function stXemSoDu(' ), 900 ), 'localStorage' ) === false, 'Khong luu trang thai: tai lai la an' );
