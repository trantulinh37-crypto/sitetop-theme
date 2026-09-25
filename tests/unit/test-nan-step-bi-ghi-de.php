<?php
/* CHỐT CỬA + NẮN LẠI step BỊ GHI ĐÈ — 25/09/2026.

   Lượt đã chốt xong (step='verified') bị hai chỗ ghi đè ngược:
     - track_direct_click / track_social_click: widget ping lại sau mỗi lần tải trang;
     - change_keyword: nút "Đổi nhiệm vụ" đóng phiên cũ bằng step='expired'.
   Tiền hai đầu vẫn đúng, view camp vẫn đếm đủ (nhờ vế OR customer_paid), nhưng nhãn sai và
   cột Tổng thu nhập hụt.

   Test này KHÔNG chỉ dò chuỗi: câu UPDATE nắn dữ liệu được LẤY THẲNG từ mã nguồn rồi CHẠY
   THẬT trên một CSDL SQLite dựng tại chỗ, với đủ các ca cần phân biệt. Đổi một chữ trong
   điều kiện là hàng bị nắn sai ngay. */

$__ns_goc = dirname( __DIR__, 2 );
$__ns_ajax = (string) file_get_contents( $__ns_goc . '/includes/shortlink-ajax.php' );
$__ns_cron = (string) file_get_contents( $__ns_goc . '/includes/cron-cleanup.php' );

/* ---- 1. Ba chốt cửa ---- */
assert_true( substr_count( $__ns_ajax,
    "WHERE session_id = %s AND ip_address = %s\n           AND step <> 'verified' AND verified_at IS NULL" ) === 2,
    'Ca track_direct_click VA track_social_click deu phai chua phien da chot' );
assert_true( strpos( $__ns_ajax,
    "\"UPDATE {\$p}shortlink_visits SET step = 'expired'\n         WHERE id = %d AND verified_at IS NULL AND reward_paid = 0\"" ) !== false,
    'Doi nhiem vu KHONG duoc dong phien da chot xong' );
assert_true( strpos( $__ns_ajax, "\$wpdb->update( \"{\$p}shortlink_visits\", array( 'step' => 'expired' ), array( 'id' => (int) \$visit->id ) );" ) === false,
    'Cau dong phien cu khong chot phai bi go han' );

/* SỐNG CÒN: chốt phải theo verified_at, KHÔNG được siết thành danh sách trắng kiểu
   step IN ('started','google_clicked'). Bước 2 của camp 2 bước cần step nằm đúng ở
   'target_visited' thì start_timer mới tính công (shortlink-ajax.php: $is_step2 &&
   $visit->step === 'target_visited'); siết quá tay là user làm đủ giờ vẫn báo thiếu giờ. */
assert_true( strpos( $__ns_ajax, "if ( \$is_step2 && \$visit->step === 'target_visited' ) {" ) !== false,
    'Buoc 2 van doc step target_visited — chot cua khong duoc chan duong nay' );
assert_true( preg_match( "#step = 'target_visited',\s*\n\s*target_visited_at = COALESCE#", $__ns_ajax ) === 1,
    'Van giu COALESCE cho target_visited_at (khong day moc ve hien tai)' );

/* ---- 2. Công thức tiền không còn phụ thuộc step ---- */
assert_true( strpos( $__ns_cron, "step = 'verified' AND reward_paid = 1" ) === false,
    'Tong thu nhap khong duoc phu thuoc step nua — step bi ghi de la tien hut' );
assert_true( substr_count( $__ns_cron, "SUM(reward_amount) FROM {\$p}shortlink_visits WHERE shortlink_id = sl.id AND reward_paid = 1" ) === 1
          && substr_count( $__ns_cron, "SUM(reward_amount) FROM {\$p}shortlink_visits WHERE campaign_id = kc.id AND reward_paid = 1" ) === 1,
    'Ca hai cong thuc tien deu tinh theo reward_paid = 1' );
// Đếm LƯỢT thì giữ nguyên công thức cũ — đây là chỗ giữ cho view/ngân sách không lệch.
assert_true( substr_count( $__ns_cron, "(step = 'verified' OR customer_paid = 1)" ) === 2,
    'Cong thuc DEM LUOT phai giu nguyen (verified HOAC khach da tra)' );

/* ---- 3. Câu nắn dữ liệu: chạy thật trên SQLite ---- */
assert_true( preg_match( '#"(UPDATE \{\$bang\}\s+SET step = \'verified\'.*?LIMIT 500)"#s', $__ns_cron, $__ns_m ) === 1,
    'Lay duoc cau UPDATE nan du lieu tu ma nguon' );
$__ns_sql = $__ns_m[1];
assert_true( strpos( $__ns_sql, 'LIMIT 500' ) !== false, 'Phai nan theo lo 500 dong, khong quet mot phat ca bang' );

$db = new PDO( 'sqlite::memory:' );
$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$db->exec( "CREATE TABLE v (id INTEGER PRIMARY KEY, step TEXT, verified_at TEXT, customer_paid INT,
            reward_paid INT, skip_reasons TEXT)" );
/* id → [step, verified_at, customer_paid, reward_paid, skip_reasons, CÓ ĐƯỢC NẮN KHÔNG] */
$ca = array(
    1  => array( 'target_visited', '2026-09-25 19:23:55', 1, 1, null,                    true,  'lượt đã chốt bị widget đè — ca chính' ),
    2  => array( 'expired',        '2026-09-25 18:00:00', 1, 1, null,                    true,  'lượt đã chốt bị nút Đổi nhiệm vụ đè' ),
    3  => array( 'code_shown',     '2026-09-25 18:00:00', 1, 0, '["customer_not_paid"]', true,  'khách đã trả, chỉ user không được thưởng' ),
    4  => array( 'target_visited', '2026-09-25 18:00:00', 0, 0, '["daily_limit_reached"]', false, 'khách KHÔNG trả — nắn là cộng thêm view miễn phí' ),
    5  => array( 'expired',        '2026-09-25 18:00:00', 0, 0, '["nguon_gia"]',         false, 'nguồn giả — tuyệt đối không nâng thành hợp lệ' ),
    6  => array( 'expired',        '2026-09-25 18:00:00', 1, 0, '["ref_lech"]',          false, 'referer lệch — kể cả khách đã bị trừ cũng không nâng' ),
    7  => array( 'code_shown',     null,                  1, 0, null,                    false, 'chốt sớm: phiên còn sống, chưa chốt đầy đủ' ),
    8  => array( 'rejected',       '2026-09-25 18:00:00', 1, 0, '["nguon_gia"]',         false, 'đang là rejected — không được đụng' ),
    9  => array( 'verified',       '2026-09-25 18:00:00', 1, 1, null,                    false, 'vốn đã đúng — nắn cũng không đổi gì' ),
    10 => array( 'started',        null,                  0, 0, null,                    false, 'phiên mới mở, chưa chốt' ),
    /* Lượt bị chặn mà KHÔNG có skip_reasons: có thật, vì skip_reasons chỉ được ghi khi cờ
       sitetop_migration_skip_reasons_v2 đã bật (shortlink-verification.php). Lúc đó thứ duy
       nhất chặn không cho nâng nó thành 'verified' là danh sách trắng step. */
    11 => array( 'rejected',       '2026-09-25 18:00:00', 1, 0, null,                    false, 'bị chặn nhưng không có dấu lý do — danh sách trắng step phải đỡ' ),
);
foreach ( $ca as $id => $c ) {
    $st = $db->prepare( "INSERT INTO v (id, step, verified_at, customer_paid, reward_paid, skip_reasons) VALUES (?,?,?,?,?,?)" );
    $st->execute( array( $id, $c[0], $c[1], $c[2], $c[3], $c[4] ) );
}

// SQLite không có UPDATE ... LIMIT — bỏ LIMIT ở đây, phần theo lô đã canh riêng ở trên.
$db->exec( str_replace( array( '{$bang}', 'LIMIT 500' ), array( 'v', '' ), $__ns_sql ) );

foreach ( $ca as $id => $c ) {
    $sau = $db->query( "SELECT step FROM v WHERE id = $id" )->fetchColumn();
    if ( $c[5] ) {
        assert_equals( 'verified', $sau, "Phai nan ve verified — ca #$id: {$c[6]}" );
    } else {
        assert_equals( $c[0], $sau, "PHAI GIU NGUYEN — ca #$id: {$c[6]}" );
    }
}

/* ---- 4. Chạy lại lần nữa không đổi gì thêm (an toàn khi cron/init gọi lại) ---- */
$truoc = $db->query( "SELECT COUNT(*) FROM v WHERE step = 'verified'" )->fetchColumn();
$db->exec( str_replace( array( '{$bang}', 'LIMIT 500' ), array( 'v', '' ), $__ns_sql ) );
$sau = $db->query( "SELECT COUNT(*) FROM v WHERE step = 'verified'" )->fetchColumn();
assert_equals( $truoc, $sau, 'Chay lai lan hai khong duoc nan them hang nao' );

/* ---- 5. Cờ chạy một lần + khoá chống hai request cùng lúc ---- */
foreach ( array(
    "get_option( 'sitetop_migration_nan_step_v1' )",
    "get_transient( 'sitetop_nan_step_dang_chay' )",
    "if ( false === \$n ) break;",
    "update_option( 'sitetop_migration_nan_step_so_dong'",
) as $moc ) {
    assert_true( strpos( $__ns_cron, $moc ) !== false, 'Thieu moc an toan cua lan nan: ' . $moc );
}

/* ---- 6. Đồng bộ lại cột đếm một lần sau khi đổi công thức tiền ---- */
assert_true( strpos( $__ns_cron, "if ( get_option( 'sitetop_dongbo_tien_sau_nan_v1' ) ) return;" ) !== false,
    'Lan dong bo bu phai co co chay-mot-lan' );
assert_true( strpos( $__ns_cron, "if ( ! get_option( 'sitetop_migration_nan_step_v1' ) ) return;" ) !== false,
    'Phai cho nan du lieu xong moi chot lai so — khong thi chot tren du lieu chua dung' );
/* Hai câu sync quét TOÀN BẢNG shortlink_visits. Treo vào init của request người dùng là
   khách phải chờ hết câu quét — nên phải nằm trong cron. */
assert_true( preg_match( "#add_action\( 'sitetop_5min_cron', function \(\) \{\s*if \( get_option\( 'sitetop_dongbo_tien_sau_nan_v1' \)#", $__ns_cron ) === 1,
    'Lan dong bo bu phai chay trong cron 5 phut, KHONG chay o init cua request nguoi dung' );
assert_true( strpos( $__ns_cron, "    sitetop_sync_shortlink_counters();\n    sitetop_sync_campaign_counters();\n    update_option( 'sitetop_dongbo_tien_sau_nan_v1'" ) !== false
          || preg_match( "#sitetop_sync_shortlink_counters\(\);\s*\n\s*sitetop_sync_campaign_counters\(\);\s*\n\s*update_option\( 'sitetop_dongbo_tien_sau_nan_v1'#", $__ns_cron ) === 1,
    'Phai dong bo CA hai bang roi moi dat co' );
