<?php
/* KHÔNG YÊU CẦU CHUYỂN ĐỘNG — cờ riêng từng camp (04/10/2026).

   Chủ site yêu cầu: thêm nút ON/OFF cho TỪNG camp. OFF giữ nguyên 100% luồng hiện tại
   (user vào web đích → bị đòi cuộn trang / chạm / click → đủ điều kiện → hiện mã). ON thì
   user chỉ cần vào web, bấm nút widget xác minh, đồng hồ chạy thẳng hết onsite của camp rồi
   hiện mã như cũ — không đòi thao tác nào.

   Hai chỗ phải tắt, thiếu một cái là tính năng vô nghĩa:
   (1) kịch bản chốt thao tác `_bhInit` — 8 chặng cuộn/chạm, mỗi chặng ĐÓNG đồng hồ tới khi
       user làm đúng thao tác;
   (2) chốt "bỏ máy" `_checkMouseIdle` — 30 giây không cử động là DỪNG đồng hồ. Camp này user
       được phép ngồi yên, không tắt thì đồng hồ không bao giờ về 0 → không bao giờ có mã.

   Và một cái bẫy phải tránh: bộ chấm điểm gian lận cộng 30 (không chuột) + 10 (không cuộn) +
   20 (không click) + 15 (ngồi yên) = 75 điểm cho đúng người làm ĐÚNG LUẬT của camp này, mà
   3 lượt ≥ 70 điểm trong 60 phút là khoá IP 12 giờ.

   Test CHẠY THẬT: mã widget chạy trong node, hàm chấm điểm chạy trong tiến trình PHP con. */

$__kc_goc = dirname( __DIR__, 2 );
$__kc_wid = (string) file_get_contents( $__kc_goc . '/widget.js.php' );
$__kc_bha = (string) file_get_contents( $__kc_goc . '/includes/behavior-analytics.php' );
$__kc_ajx = (string) file_get_contents( $__kc_goc . '/includes/shortlink-ajax.php' );
$__kc_fns = (string) file_get_contents( $__kc_goc . '/functions.php' );

/* ═══════════ A. WIDGET — chạy thật trong node ═══════════ */
$__kc_js = function ( $ma, $ten ) {
    $vt = strpos( $ma, 'function ' . $ten . '(' );
    if ( $vt === false ) return '';
    $d = 0; $mo = false; $n = strlen( $ma );
    for ( $i = $vt; $i < $n; $i++ ) {
        if ( $ma[ $i ] === '{' ) { $d++; $mo = true; }
        elseif ( $ma[ $i ] === '}' ) { $d--; if ( $mo && $d === 0 ) return substr( $ma, $vt, $i - $vt + 1 ); }
    }
    return '';
};
$__kc_ham = '';
foreach ( array( '_bhRnd', '_bhMinTotal', '_bhNext', '_bhHide', '_bhForceHide', '_bhInit', '_checkMouseIdle' ) as $__kc_t ) {
    $__kc_m = $__kc_js( $__kc_wid, $__kc_t );
    assert_true( $__kc_m !== '', 'Phai trich duoc ham widget ' . $__kc_t );
    $__kc_ham .= $__kc_m . "\n";
}

$__kc_node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
assert_true( $__kc_node !== '', 'Can node de chay ma widget that' );

$__kc_chay = function ( $khong_cd ) use ( $__kc_ham ) {
    $khung = 'var _bh={on:false,stages:[],gate:null,i:-1,left:0,idle:false,firstDone:false,pre:null,satisfied:false,warnUntil:0};' . "\n"
        . 'var _bhResume=0, _bhListenerAdded=true, _cdPaused=false, _lastMouseMove=0, _mouseIdleLimit=30000;' . "\n"
        . 'var _pauseGoi=0; function _pauseCountdown(r){_pauseGoi++;}' . "\n"
        . 'function _mocGio(){return 999999;}' . "\n"   /* đã 999 giây không cử động */
        . 'var state={sessionId:"S1",remaining:80,countdownStarted:true,codeReady:false,khongCD:'
        . ( $khong_cd ? 'true' : 'false' ) . '};' . "\n"
        . 'var document={getElementById:function(){return null;},addEventListener:function(){}};' . "\n"
        . 'var localStorage={getItem:function(){return null;},setItem:function(){},removeItem:function(){}};' . "\n"
        . $__kc_ham . "\n"
        . '_bhInit(); _checkMouseIdle();' . "\n"
        . 'console.log(JSON.stringify({on:_bh.on,soChang:_bh.stages.length,pause:_pauseGoi}));';
    $f = sys_get_temp_dir() . '/st-kc-' . getmypid() . ( $khong_cd ? '-on' : '-off' ) . '.js';
    file_put_contents( $f, $khung );
    $ra = (string) shell_exec( 'node ' . escapeshellarg( $f ) . ' 2>&1' );
    @unlink( $f );
    return array( json_decode( trim( $ra ), true ), $ra );
};

// A1. OFF — giữ nguyên hành vi hiện tại: dựng đủ chặng, chốt "bỏ máy" vẫn dừng đồng hồ.
list( $__kc_k, $__kc_r ) = $__kc_chay( false );
assert_true( is_array( $__kc_k ), 'Chay duoc ma widget bang node. Ra: ' . $__kc_r );
assert_true( ! empty( $__kc_k['on'] ) && (int) $__kc_k['soChang'] > 0,
    'OFF: kich ban chot thao tac PHAI chay nhu cu (dung cac chang). Ra: ' . $__kc_r );
assert_equals( 1, (int) ( $__kc_k['pause'] ?? 0 ),
    'OFF: ngoi yen qua 30 giay van PHAI dung dong ho nhu hien tai. Ra: ' . $__kc_r );

// A2. ON — không chặng nào, và ngồi yên KHÔNG bị dừng đồng hồ.
list( $__kc_k, $__kc_r ) = $__kc_chay( true );
assert_true( is_array( $__kc_k ), 'Chay duoc ma widget bang node (ON). Ra: ' . $__kc_r );
assert_equals( 0, (int) $__kc_k['soChang'],
    'ON: KHONG duoc dung chang thao tac nao. Ra: ' . $__kc_r );
assert_true( empty( $__kc_k['on'] ), 'ON: _bh.on phai tat han. Ra: ' . $__kc_r );
assert_equals( 0, (int) ( $__kc_k['pause'] ?? -1 ),
    'ON: ngoi yen KHONG duoc dung dong ho — dung la dong ho khong bao gio ve 0, user khong co ma. Ra: ' . $__kc_r );

/* ═══════════ B. CHẤM ĐIỂM GIAN LẬN — chạy thật hàm PHP ═══════════ */
$__kc_php = function ( $ma, $ten ) {
    $vt = strpos( $ma, 'function ' . $ten . '(' );
    if ( $vt === false ) return '';
    $tk = token_get_all( '<?php ' . substr( $ma, $vt ) );
    $out = ''; $d = 0; $open = false; $dau = true;
    foreach ( $tk as $t ) {
        if ( $dau ) { $dau = false; continue; }
        $out .= is_array( $t ) ? $t[1] : $t;
        $mo = ( $t === '{' ) || ( is_array( $t ) && in_array( $t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) );
        if ( $mo ) { $d++; $open = true; }
        elseif ( $t === '}' ) { $d--; if ( $open && $d === 0 ) break; }
    }
    return $out;
};
$__kc_fs = $__kc_php( $__kc_bha, 'sitetop_calculate_fraud_score' );
assert_true( $__kc_fs !== '', 'Phai trich duoc sitetop_calculate_fraud_score' );

$__kc_cham = function ( $data ) use ( $__kc_fs ) {
    $ma = 'error_reporting(E_ALL); function wp_is_mobile(){ return false; }' . "\n"
        . $__kc_fs . "\n"
        . '$kq = sitetop_calculate_fraud_score(' . var_export( $data, true ) . ');' . "\n"
        . 'echo json_encode($kq);';
    $p = proc_open( array( PHP_BINARY, '-d', 'display_errors=stderr', '-r', $ma ),
        array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $ong );
    $out = stream_get_contents( $ong[1] ); $err = stream_get_contents( $ong[2] );
    fclose( $ong[1] ); fclose( $ong[2] ); proc_close( $p );
    return array( json_decode( $out, true ), $err ?: $out );
};
/* Đúng hồ sơ của người làm ĐÚNG LUẬT camp "không yêu cầu chuyển động": vào trang, ngồi yên
   đủ 85 giây, không chuột, không cuộn, không click. */
$__kc_nguoi_that = array( 'mouse_movements' => 0, 'scroll_depth' => 0, 'clicks' => 0,
    'time_on_page' => 85, 'idle_time' => 84 );

// B1. Camp THƯỜNG: hồ sơ đó vẫn bị chấm nặng như cũ (không được nới tay cho camp thường).
list( $__kc_k, $__kc_e ) = $__kc_cham( $__kc_nguoi_that );
assert_true( is_array( $__kc_k ), 'Chay duoc ham cham diem. stderr: ' . $__kc_e );
assert_true( (int) $__kc_k['fraud_score'] >= 70,
    'Camp thuong: khong chuot/cuon/click van phai >= 70 diem nhu cu (duoc ' . ( $__kc_k['fraud_score'] ?? '?' ) . ')' );

// B2. Camp BẬT cờ: KHÔNG chấm nhóm thao tác nữa → dưới ngưỡng khoá IP.
list( $__kc_k2, $__kc_e2 ) = $__kc_cham( array_merge( $__kc_nguoi_that, array( 'khong_cd' => 1 ) ) );
assert_true( is_array( $__kc_k2 ), 'Chay duoc ham cham diem (co co). stderr: ' . $__kc_e2 );
assert_true( (int) $__kc_k2['fraud_score'] < 70,
    'Camp bat co: nguoi lam dung luat KHONG duoc cham >= 70 (khoa IP 12 gio). Duoc '
    . ( $__kc_k2['fraud_score'] ?? '?' ) . ' diem, ly do: ' . implode( ',', (array) ( $__kc_k2['fraud_reasons'] ?? array() ) ) );
foreach ( array( 'no_mouse', 'no_scroll', 'no_clicks', 'idle_gt_95pct' ) as $__kc_x ) {
    assert_true( ! in_array( $__kc_x, (array) ( $__kc_k2['fraud_reasons'] ?? array() ), true ),
        'Camp bat co: khong duoc gan ly do "' . $__kc_x . '"' );
}

// B3. Các nhóm chấm KHÁC giữ nguyên — bật cờ không phải là mở cửa cho bot.
list( $__kc_k3, $__kc_e3 ) = $__kc_cham( array_merge( $__kc_nguoi_that,
    array( 'khong_cd' => 1, 'is_bot' => 1, 'is_datacenter' => 1 ) ) );
assert_true( (int) $__kc_k3['fraud_score'] >= 70,
    'Bat co KHONG duoc tha bot: is_bot + datacenter van phai >= 70. Duoc ' . ( $__kc_k3['fraud_score'] ?? '?' ) . '. stderr: ' . $__kc_e3 );

/* ═══════════ C. AN TOÀN LÚC DEPLOY — chưa có cột thì trả 0 ═══════════ */
$__kc_hm = $__kc_php( $__kc_fns, 'sitetop_camp_khong_doi_cd' );
assert_true( $__kc_hm !== '', 'Phai trich duoc sitetop_camp_khong_doi_cd' );
$__kc_doc = function ( $co_mig ) use ( $__kc_hm ) {
    $ma = 'error_reporting(E_ALL);' . "\n"
        . 'function get_option($k,$d=false){ return ' . ( $co_mig ? '1234567' : 'false' ) . '; }' . "\n"
        . 'class KC_Wpdb { public $prefix="wpgd_"; public $goi=0;
             public function prepare($q,...$a){ return $q; }
             public function get_var($q){ $this->goi++; return 1; } }' . "\n"
        . '$GLOBALS["wpdb"] = new KC_Wpdb();' . "\n"
        . $__kc_hm . "\n"
        . 'echo json_encode(array("kq"=>sitetop_camp_khong_doi_cd(9),"truy_van"=>$GLOBALS["wpdb"]->goi));';
    $p = proc_open( array( PHP_BINARY, '-d', 'display_errors=stderr', '-r', $ma ),
        array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $ong );
    $out = stream_get_contents( $ong[1] ); $err = stream_get_contents( $ong[2] );
    fclose( $ong[1] ); fclose( $ong[2] ); proc_close( $p );
    return array( json_decode( $out, true ), $err ?: $out );
};
list( $__kc_c1, $__kc_ce1 ) = $__kc_doc( false );
assert_equals( 0, (int) ( $__kc_c1['kq'] ?? -1 ),
    'Chua chay migration: phai tra 0 (y het hanh vi cu). stderr: ' . $__kc_ce1 );
assert_equals( 0, (int) ( $__kc_c1['truy_van'] ?? -1 ),
    'Chua chay migration: KHONG duoc hoi CSDL cot chua ton tai — cau SELECT do se loi' );
list( $__kc_c2, $__kc_ce2 ) = $__kc_doc( true );
assert_equals( 1, (int) ( $__kc_c2['kq'] ?? -1 ), 'Co migration: doc duoc gia tri cot. stderr: ' . $__kc_ce2 );

/* ═══════════ D. CỜ PHẢI DO MÁY CHỦ ĐẶT, ĐÈ LÊN CLIENT ═══════════ */
$__kc_rb = $__kc_php( $__kc_ajx, 'sitetop_ajax_report_behavior' );
assert_true( $__kc_rb !== '', 'Phai trich duoc sitetop_ajax_report_behavior' );
$__kc_vt_post = strpos( $__kc_rb, '$behavior_data = $_POST;' );
$__kc_vt_dat  = strpos( $__kc_rb, "\$behavior_data['khong_cd']" );
assert_true( $__kc_vt_post !== false && $__kc_vt_dat !== false && $__kc_vt_post < $__kc_vt_dat,
    'SONG CON: phai dat khong_cd SAU khi copy $_POST — dat truoc thi client tu khai khong_cd=1 la ne duoc cham diem' );
assert_true( strpos( $__kc_rb, 'sitetop_camp_khong_doi_cd(' ) !== false,
    'Co phai lay tu camp trong CSDL, khong lay tu POST' );

/* ═══════════ E. CAMP ĐANG CHẠY KHÔNG ĐƯỢC ĐỘNG TỚI ═══════════ */
$__kc_cron = (string) file_get_contents( $__kc_goc . '/includes/cron-cleanup.php' );
assert_true( strpos( $__kc_cron, 'ADD COLUMN khong_doi_cd TINYINT(1) NOT NULL DEFAULT 0' ) !== false,
    'Cot moi PHAI mac dinh 0 — moi camp dang chay giu nguyen hanh vi cu' );
assert_true( strpos( $__kc_cron, "if ( in_array( 'khong_doi_cd', \$cot, true ) ) {" ) !== false,
    'Chi dat co migration khi SHOW COLUMNS xac nhan cot co that' );

// Ghi cột chỉ khi migration xong — cả lúc TẠO và lúc SỬA camp.
$__kc_tab = (string) file_get_contents( $__kc_goc . '/includes/admin/tabs/tab-campaigns.php' );
assert_true( strpos( $__kc_tab, "if (get_option('sitetop_migration_khong_doi_cd_v1')) \$camp_data['khong_doi_cd']" ) !== false,
    'Tao camp: chi ghi cot khi migration xong, khong thi hong ca cau INSERT' );
$__kc_sf = (string) file_get_contents( $__kc_goc . '/includes/shortlink-functions.php' );
assert_true( strpos( $__kc_sf, "if ( get_option( 'sitetop_migration_khong_doi_cd_v1' ) ) \$allowed['khong_doi_cd'] = '%d';" ) !== false,
    'Sua camp: chi ghi cot khi migration xong' );

// Widget phải nhận cờ TỪ MÁY CHỦ ở cổng xác minh, không tự đoán.
assert_true( strpos( $__kc_ajx, "\$result['khong_cd'] = sitetop_camp_khong_doi_cd(" ) !== false,
    'verify_access phai tra co ve cho widget' );
assert_true( strpos( $__kc_wid, 'state.khongCD=!!(d.data.khong_cd);' ) !== false,
    'Widget phai doc co tu phan hoi cua may chu' );

/* ═══════════ F. CHỈ ADMIN — chủ site chốt 04/10/2026 ═══════════
   Không chỉ là "ẩn nút": phía khách hàng phải KHÔNG GHI ĐƯỢC cờ. Tạo/sửa camp bên khách
   dựng mảng theo từng cột nên tự nó an toàn — test này canh để sau này ai thêm vòng lặp
   đổ thẳng $_POST vào là đỏ ngay. */
foreach ( array( 'includes/customer-campaign-ajax.php', 'page-customer-dashboard.php', 'includes/customer-load-more.php' ) as $__kc_f ) {
    $__kc_n = $__kc_goc . '/' . $__kc_f;
    if ( ! file_exists( $__kc_n ) ) continue;
    assert_true( strpos( (string) file_get_contents( $__kc_n ), 'khong_doi_cd' ) === false,
        'CHI ADMIN: ' . $__kc_f . ' khong duoc dong toi co khong_doi_cd' );
}
// Cổng sửa camp của admin vẫn phải đòi quyền quản trị.
$__kc_adm = (string) file_get_contents( $__kc_goc . '/includes/admin-dashboard.php' );
$__kc_vt = strpos( $__kc_adm, 'function sitetop_ajax_admin_update_campaign()' );
assert_true( $__kc_vt !== false && strpos( substr( $__kc_adm, $__kc_vt, 400 ), "current_user_can('manage_options')" ) !== false,
    'Cong sua camp phai doi quyen manage_options' );
