<?php
/* CRON PHẢI DÙNG ĐÚNG CỘT CÓ THẬT — 24/09/2026.

   Lỗi đã xảy ra: cron dọn dẹp chạy "DELETE FROM ip_reputation WHERE blocked = 0 AND
   updated_at < ..." trong khi bảng đó KHÔNG có cột updated_at (cột thời gian là checked_at).
   Câu lệnh hỏng âm thầm suốt nhiều tháng, ngày nào error_log cũng có "Unknown column
   'updated_at'" lúc 07:20, và việc dọn mà nó định làm thì không bao giờ chạy.

   Test này đọc CẤU TRÚC BẢNG THẬT trong database-setup.php rồi soi mọi câu SQL trong
   cron-cleanup.php: cột nào không có trong bảng là đỏ. Nó canh cả lớp lỗi, không riêng một ca.

   Canh thêm một điều đã suýt làm sai lúc chữa: mọi câu XOÁ trên ip_reputation phải có
   permanent_block = 0 — IP khoá vĩnh viễn không bao giờ được cron tự xoá. */

$__cc_goc   = dirname( __DIR__, 2 );
$__cc_setup = (string) file_get_contents( $__cc_goc . '/includes/database-setup.php' );
$__cc_cron  = (string) file_get_contents( $__cc_goc . '/includes/cron-cleanup.php' );

/* 1. Cột thật của từng bảng, đọc từ các khối CREATE TABLE. */
$__cc_bang = array();
// Đuôi khối khai bảng trong file này có hai kiểu: ") $c;" và ") $charset;" — nhận cả hai.
preg_match_all( '/CREATE TABLE \{\$p\}([a-z_]+) \((.*?)\)\s*\$[a-z_]+;/s', $__cc_setup, $__cc_m, PREG_SET_ORDER );
foreach ( $__cc_m as $__cc_kh ) {
    $__cc_cot = array();
    preg_match_all(
        '/^\s+([a-z_][a-z0-9_]*)\s+(?:bigint|int|smallint|mediumint|tinyint|varchar|char|text|longtext|mediumtext|datetime|timestamp|date|decimal|float|double|enum)/mi',
        $__cc_kh[2], $__cc_mc );
    foreach ( $__cc_mc[1] as $__cc_c ) $__cc_cot[ strtolower( $__cc_c ) ] = 1;
    $__cc_bang[ $__cc_kh[1] ] = $__cc_cot;
}
assert_true( count( $__cc_bang ) >= 10, 'Doc duoc cau truc cac bang (' . count( $__cc_bang ) . ' bang)' );
assert_true( isset( $__cc_bang['ip_reputation']['checked_at'] ), 'ip_reputation co cot checked_at' );
assert_true( ! isset( $__cc_bang['ip_reputation']['updated_at'] ), 'ip_reputation KHONG co cot updated_at (goc cua loi)' );

/* 2. Mọi câu SQL trong cron-cleanup.php: cột được nhắc tới phải có thật.
      Từ khoá/hàm SQL và placeholder được loại ra; tên cột có tiền tố bí danh (v.step) thì
      cắt tiền tố rồi mới tra. */
$__cc_bo = array_flip( array(
    'select','from','where','and','or','set','values','interval','day','hour','minute','second',
    'delete','update','insert','into','limit','order','by','group','is','null','not','in','on','as',
    'join','left','inner','outer','count','sum','max','min','avg','coalesce','ifnull','if','date',
    'date_sub','date_add','now','unix_timestamp','concat','substring','replace','length','distinct',
    'duplicate','key','table','case','when','then','else','end','like','between','desc','asc','exists',
) );
$__cc_loi = array();
preg_match_all( '/"((?:[^"\\\\]|\\\\.)*\{\$p\}(?:[^"\\\\]|\\\\.)*)"/s', $__cc_cron, $__cc_sql );
assert_true( count( $__cc_sql[1] ) >= 8, 'Quet duoc cac cau SQL trong cron (' . count( $__cc_sql[1] ) . ' cau)' );
foreach ( $__cc_sql[1] as $__cc_cau ) {
    preg_match_all( '/\{\$p\}([a-z_]+)/', $__cc_cau, $__cc_mt );
    $__cc_chophep = array();
    foreach ( array_unique( $__cc_mt[1] ) as $__cc_t ) {
        if ( isset( $__cc_bang[ $__cc_t ] ) ) $__cc_chophep += $__cc_bang[ $__cc_t ];
    }
    if ( ! $__cc_chophep ) continue;                 // câu không đụng bảng nào đọc được
    // Tên đứng ngay trước phép so sánh hoặc phép gán = một cột.
    preg_match_all( '/([a-z_][a-z0-9_]*\.)?([a-z_][a-z0-9_]*)\s*(?:<=|>=|!=|<>|=|<|>)/i', $__cc_cau, $__cc_mr, PREG_SET_ORDER );
    foreach ( $__cc_mr as $__cc_r ) {
        $__cc_ten = strtolower( $__cc_r[2] );
        if ( isset( $__cc_bo[ $__cc_ten ] ) ) continue;
        if ( ! isset( $__cc_chophep[ $__cc_ten ] ) ) {
            $__cc_loi[] = $__cc_ten . '  (bang: ' . implode( ',', array_unique( $__cc_mt[1] ) ) . ')';
        }
    }
}
assert_true( empty( $__cc_loi ), 'Cron dung cot KHONG CO THAT: ' . implode( ' | ', array_unique( $__cc_loi ) ) );

/* 3. Cron tuyệt đối không được tự xoá IP khoá vĩnh viễn. */
foreach ( $__cc_sql[1] as $__cc_cau ) {
    $__cc_1dong = preg_replace( '/\s+/', ' ', $__cc_cau );
    if ( stripos( $__cc_1dong, 'DELETE' ) === 0 && strpos( $__cc_1dong, '{$p}ip_reputation' ) !== false ) {
        assert_true( strpos( $__cc_1dong, 'permanent_block = 0' ) !== false || strpos( $__cc_1dong, 'permanent_block=0' ) !== false,
            'Cau XOA tren ip_reputation PHAI co permanent_block = 0 — khoa vinh vien khong duoc tu xoa: ' . substr( $__cc_1dong, 0, 120 ) );
    }
}
