<?php
/* Bảng báo lỗi user — canh để không tái diễn kiểu hỏng LẶNG LẼ.
   Trước 10/09/2026 cổng ghi báo lỗi có đủ lệnh insert nhưng bảng chưa từng được tạo, mà
   lệnh lại bọc trong "SHOW TABLES LIKE" nên không tồn tại thì bỏ qua không kêu. Kết quả:
   mọi báo lỗi user gửi suốt thời gian đó mất sạch, và khi cần dùng để xét một nguồn traffic
   có người thật hay không thì không có gì để tra. */
$__goc = dirname( __DIR__, 2 );
$__db  = file_get_contents( $__goc . '/includes/database-setup.php' );
$__aj  = file_get_contents( $__goc . '/includes/shortlink-ajax.php' );

// 1) Bảng phải được tạo
assert_true( strpos( $__db, 'CREATE TABLE {$p}shortlink_reports' ) !== false,
    'Bang shortlink_reports PHAI duoc tao trong database-setup' );

// 2) Mọi cột lệnh insert dùng đều phải có trong định nghĩa bảng
$__vt = strpos( $__aj, '$wpdb->insert($table, array(' );
assert_true( $__vt !== false, 'Phai tim thay lenh ghi bao loi' );
if ( $__vt !== false ) {
    $__khoi = substr( $__aj, $__vt, 320 );
    preg_match_all( "/'([a-z_]+)'\s*=>/", $__khoi, $__m );
    $__cot = array_unique( $__m[1] );
    assert_true( count( $__cot ) >= 4, 'Phai doc duoc danh sach cot dang ghi (' . count( $__cot ) . ')' );
    // lấy đúng khối CREATE TABLE của bảng này
    $__b = strpos( $__db, 'CREATE TABLE {$p}shortlink_reports' );
    $__def = $__b === false ? '' : substr( $__db, $__b, 700 );
    foreach ( $__cot as $__c ) {
        assert_true( strpos( $__def, $__c ) !== false,
            'Cot "' . $__c . '" co trong lenh ghi thi PHAI co trong dinh nghia bang' );
    }
}

// 3) Trang xem phải tồn tại và được gắn vào menu
assert_true( is_file( $__goc . '/includes/admin/tabs/tab-reports.php' ),
    'Phai co trang xem bao loi' );
$__fn = file_get_contents( $__goc . '/functions.php' );
assert_true( strpos( $__fn, "'sitetop-reports'" ) !== false,
    'Trang bao loi PHAI duoc gan vao menu admin' );

// 4) Trang xem phải chặn quyền — dữ liệu này có IP của user
$__tr = file_get_contents( $__goc . '/includes/admin/tabs/tab-reports.php' );
assert_true( strpos( $__tr, 'current_user_can' ) !== false,
    'Trang bao loi PHAI kiem quyen' );
assert_true( strpos( $__tr, "\$wpdb->prepare" ) !== false,
    'Truy van PHAI dung prepare (co tham so tu URL)' );
