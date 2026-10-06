<?php
/* NGUỒN GIẢ: KHÔNG TRẢ USER, KHÔNG TRỪ KHÁCH, KHÔNG TÍNH VIEW — 22/09/2026.

   Chủ site thấy lượt "Nguồn giả" (mã 373FAE12) vẫn hiện "Hoàn thành". CSDL: 82 lượt nguồn giả
   từ 20/09 — user nhận 0đ, khách bị trừ 0 lần (tiền đã đúng) — nhưng 2 lượt chốt ở step
   'verified', mà cron đồng bộ bộ đếm 07:19 + ~40 chỗ thống kê đếm view theo step = 'verified'
   → khách vẫn thấy +1 view và lượt giả còn ăn vào hạn mức ngày của camp.
   Sửa: nguồn giả ở mức 2 chốt ở step 'rejected' (admin hiện "Bị chặn"); chốt khoá hàng nhận
   verified_at để lượt đã chốt không gửi lại mã được.

   Chạy CHÍNH sitetop_verify_and_pay() trong tiến trình PHP con với $wpdb giả ghi lại mọi lệnh
   ghi tiền/đếm — hàm thật, không đọc chữ. */

$__nv_goc = dirname( __DIR__, 2 );
$__nv_ham = function ( $ma, $ten ) {
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
$__nv_src = $__nv_ham( (string) file_get_contents( $__nv_goc . '/includes/shortlink-verification.php' ), 'sitetop_verify_and_pay' );
assert_true( $__nv_src !== '', 'Phai trich duoc sitetop_verify_and_pay' );

$__nv_nen = <<<'PHP'
error_reporting( E_ALL );
class WP_Error { public $code; public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; } }
function sitetop_get_real_ip() { return '14.0.0.9'; }
function sitetop_is_ip_blocked( $ip ) { return false; }
function get_user_meta( $u, $k, $s = false ) { return ''; }
function sitetop_bridge_rescue_code( $v, $s, $c ) {}
function sitetop_current_time() { return '2026-09-22 20:38:56'; }
function sitetop_get_visit_expiry_seconds() { return 600; }
function get_transient( $k ) { return $GLOBALS['TR'][ $k ] ?? false; }
function delete_transient( $k ) { unset( $GLOBALS['TR'][ $k ] ); return true; }
function sitetop_get_option( $k, $d = null ) { return array_key_exists( $k, $GLOBALS['OPT'] ) ? $GLOBALS['OPT'][ $k ] : $d; }
function get_option( $k, $d = false ) { return $k === 'sitetop_migration_skip_reasons_v2' ? 1 : $d; }
function sitetop_ip_view_quota( $ip, $sl ) { return array( 'same_link' => false, 'used' => 0, 'limit' => 2, 'allowed' => true ); }
function sitetop_get_customer_balance_amount( $c ) { return 1000000; }
function sitetop_sync_customer_balance( $c ) {}
function sitetop_auto_pause_customer_campaigns( $c ) { $GLOBALS['GHI'][] = 'pause_camp'; }
/* Rate riêng từng user (06/10/2026) — bộ test này chạy đoạn trả thưởng thật nên phải
   khai sẵn. Trả 0 = không có rate riêng: bộ test này soi việc khác. */
function sitetop_rate_rieng_cua_user( $u, $ct, $tt ) { return 0.0; }
function sitetop_get_reward_amount( $o ) { return 700; }
function sitetop_add_user_balance( $u, $a, ...$r ) { $GLOBALS['GHI'][] = 'TRA_USER ' . $a; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function absint( $v ) { return abs( (int) $v ); }
/* Chế độ tiền user (06/10/2026): khung kiểm hành vi VNĐ — công tắc USD tắt. */
function sitetop_che_do_usd() { return false; }
function sitetop_lam_tron_tien_user( $a ) { return absint( $a ); }
class NV_Wpdb {
    public $prefix = 'wpgd_'; public $visit; public $locked; public $cap_nhat = null;
    public function prepare( $q, ...$a ) {
        if ( count( $a ) === 1 && is_array( $a[0] ) ) $a = $a[0];
        $i = 0;
        return preg_replace_callback( '/%[sdf]/', function ( $m ) use ( &$i, $a ) {
            $v = $a[ $i++ ] ?? null;
            return $m[0] === '%s' ? "'" . addslashes( (string) $v ) . "'" : (string) (int) $v;
        }, $q );
    }
    public function get_row( $q ) {
        if ( strpos( $q, 'FOR UPDATE' ) !== false && strpos( $q, 'shortlink_visits' ) !== false ) return $this->locked;
        if ( strpos( $q, 'customer_balance' ) !== false ) return (object) array( 'user_id' => 9, 'balance' => 1000000 );
        return $this->visit;
    }
    public function get_var( $q ) { return 0; }
    public function query( $q ) {
        $q = preg_replace( '/\s+/', ' ', trim( $q ) );
        if ( stripos( $q, 'customer_balance SET balance = balance -' ) !== false ) $GLOBALS['GHI'][] = 'TRU_KHACH';
        elseif ( stripos( $q, 'keyword_campaigns SET completed = completed + 1' ) !== false ) $GLOBALS['GHI'][] = 'CONG_VIEW_CAMP';
        elseif ( stripos( $q, 'user_shortlinks SET total_completed' ) !== false ) $GLOBALS['GHI'][] = 'CONG_LINK';
        elseif ( in_array( $q, array( 'START TRANSACTION', 'COMMIT', 'ROLLBACK' ), true ) ) $GLOBALS['GHI'][] = $q;
        return 1;
    }
    public function insert( $t, $d ) { $GLOBALS['GHI'][] = 'INSERT ' . $t; return 1; }
    public function update( $t, $d, $w = array() ) { if ( strpos( $t, 'shortlink_visits' ) !== false ) $this->cap_nhat = $d; return 1; }
}
PHP;

$__nv_chay = function ( $ca ) use ( $__nv_nen, $__nv_src ) {
    $ma = $__nv_nen . "\n" . $__nv_src . "\n"
        . '$ca = ' . var_export( $ca, true ) . ";\n" . <<<'PHP'
$GLOBALS['GHI'] = array();
$GLOBALS['OPT'] = array( 'nguon_gia_muc' => $ca['muc'], 'widget_captcha_enabled' => 0 );
$GLOBALS['TR']  = array( 'sitetop_widget_code_ready_S1' => 1, 'sitetop_verify_code_S1' => '373FAE12' );
if ( $ca['nguon_gia'] ) $GLOBALS['TR']['sitetop_nguongia_S1'] = 'ngo';
$v = array( 'id' => 607395, 'session_id' => 'S1', 'reward_paid' => 0, 'customer_paid' => 0, 'user_id' => 723,
    'created_at' => '2026-09-22 20:37:18', 'price_per_view' => 1000, 'camp_user_reward' => 700, 'camp_onsite' => 70,
    'onsite_time' => 70, 'traffic_type' => '2step', 'customer_id' => 9, 'daily_traffic' => 0, 'camp_status' => 'active',
    'fixed_code' => '', 'camp_id' => 309, 'camp_order_id' => 0, 'campaign_type' => 'traffic_direct', 'camp_title' => 't',
    'original_url' => 'https://tylenhacai.in/', 'sl_id' => 239667, 'shortlink_id' => 239667, 'campaign_id' => 309,
    'verify_code' => '373FAE12', 'from_google' => 1, 'url_matched' => 1, 'ip_changed' => 0, 'original_ip' => '14.0.0.9',
    'ip_address' => '14.0.0.9', 'adblock_detected' => 0, 'verified_at' => null, 'step' => 'code_shown' );
$GLOBALS['wpdb'] = new NV_Wpdb();
$GLOBALS['wpdb']->visit  = (object) $v;
$GLOBALS['wpdb']->locked = (object) array_merge( $v, $ca['khoa'] );
$kq = sitetop_verify_and_pay( 'S1', '373FAE12', $ca['chot_som'] );
echo json_encode( array( 'kq' => is_array( $kq ) ? $kq : array( 'loi' => $kq->code ?? '?' ),
    'cap_nhat' => $GLOBALS['wpdb']->cap_nhat, 'ghi' => $GLOBALS['GHI'] ) );
PHP;
    $p = proc_open( array( PHP_BINARY, '-d', 'display_errors=stderr', '-r', $ma ),
        array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $ong );
    $out = stream_get_contents( $ong[1] ); $err = stream_get_contents( $ong[2] );
    fclose( $ong[1] ); fclose( $ong[2] ); proc_close( $p );
    $j = json_decode( $out, true );
    return array( is_array( $j ) ? $j : array(), $err ?: ( is_array( $j ) ? '' : $out ) );
};
$__nv_co = function ( $kq, $viec ) { return in_array( $viec, (array) ( $kq['ghi'] ?? array() ), true ); };

// V1. Lượt THƯỜNG (không nguồn giả): verified, trừ khách, trả user, cộng view — hồi quy luồng tiền.
list( $__nv_k, $__nv_e ) = $__nv_chay( array( 'muc' => 2, 'nguon_gia' => false, 'khoa' => array(), 'chot_som' => false ) );
assert_equals( 'verified', $__nv_k['cap_nhat']['step'] ?? null, 'Luot thuong van chot o verified. stderr: ' . $__nv_e );
assert_true( $__nv_co( $__nv_k, 'TRU_KHACH' ) && $__nv_co( $__nv_k, 'TRA_USER 700' ) && $__nv_co( $__nv_k, 'CONG_VIEW_CAMP' ),
    'Luot thuong: tru khach + tra user 700 + cong view camp nhu cu' );
assert_equals( 1, $__nv_k['cap_nhat']['customer_paid'] ?? null, 'Luot thuong: customer_paid = 1' );

// V2. NGUỒN GIẢ mức 2 (ca thật 373FAE12): rejected, KHÔNG một đồng nào, KHÔNG cộng view.
list( $__nv_k, $__nv_e ) = $__nv_chay( array( 'muc' => 2, 'nguon_gia' => true, 'khoa' => array(), 'chot_som' => false ) );
assert_equals( 'rejected', $__nv_k['cap_nhat']['step'] ?? null,
    'Nguon gia muc 2 phai chot o rejected — verified la bi dem view o cron 07:19 + ~40 cho thong ke. stderr: ' . $__nv_e );
foreach ( array( 'TRU_KHACH', 'TRA_USER 700', 'CONG_VIEW_CAMP', 'CONG_LINK', 'INSERT wpgd_sitetop_customer_transactions' ) as $__nv_x ) {
    assert_true( ! $__nv_co( $__nv_k, $__nv_x ), 'Nguon gia: KHONG duoc ' . $__nv_x );
}
assert_equals( '0|0|0', ( $__nv_k['cap_nhat']['reward_paid'] ?? '?' ) . '|' . ( $__nv_k['cap_nhat']['customer_paid'] ?? '?' ) . '|' . ( $__nv_k['cap_nhat']['reward_amount'] ?? '?' ),
    'Nguon gia: reward_paid 0, customer_paid 0, reward_amount 0' );
assert_true( ! empty( $__nv_k['cap_nhat']['verified_at'] ), 'Nguon gia van ghi verified_at — dau "da chot" de chan gui lai ma' );
assert_true( strpos( (string) ( $__nv_k['cap_nhat']['skip_reasons'] ?? '' ), 'nguon_gia' ) !== false, 'Van ghi ly do nguon_gia cho admin loc' );
assert_equals( 'ok|0', ( ! empty( $__nv_k['kq']['success'] ) ? 'ok' : 'loi' ) . '|' . (int) ( $__nv_k['kq']['reward'] ?? -1 ),
    'Nguon gia: nguoi that lo dinh van qua trang binh thuong, thuong 0' );

// V3. Mức 1 = CHỈ GẮN NHÃN: vẫn verified + trả tiền như cũ (hạ mức về 1 phải còn đường lùi).
list( $__nv_k, $__nv_e ) = $__nv_chay( array( 'muc' => 1, 'nguon_gia' => true, 'khoa' => array(), 'chot_som' => false ) );
assert_equals( 'verified', $__nv_k['cap_nhat']['step'] ?? null, 'Muc 1 chi gan nhan: van verified. stderr: ' . $__nv_e );
assert_true( $__nv_co( $__nv_k, 'TRU_KHACH' ) && $__nv_co( $__nv_k, 'TRA_USER 700' ), 'Muc 1: tien van chay nhu cu' );

// V4. GỬI LẠI MÃ sau khi đã chốt rejected (dấu nguồn giả đã hết hạn 2 giờ): KHÔNG được trả tiền.
list( $__nv_k, $__nv_e ) = $__nv_chay( array( 'muc' => 2, 'nguon_gia' => false,
    'khoa' => array( 'step' => 'rejected', 'verified_at' => '2026-09-22 20:38:56' ), 'chot_som' => false ) );
assert_true( ! $__nv_co( $__nv_k, 'TRU_KHACH' ) && ! $__nv_co( $__nv_k, 'TRA_USER 700' ) && ! $__nv_co( $__nv_k, 'CONG_VIEW_CAMP' ),
    'Luot da chot rejected gui lai ma: KHONG tru khach, KHONG tra user, KHONG cong view. stderr: ' . $__nv_e );
assert_true( $__nv_co( $__nv_k, 'ROLLBACK' ) && $__nv_k['cap_nhat'] === null, 'Gui lai ma: ROLLBACK, khong ghi de luot' );

// V5. Chốt sớm (customer_only) khi có nguồn giả: giữ code_shown, không trừ khách.
list( $__nv_k, $__nv_e ) = $__nv_chay( array( 'muc' => 2, 'nguon_gia' => true, 'khoa' => array(), 'chot_som' => true ) );
assert_equals( 'code_shown', $__nv_k['cap_nhat']['step'] ?? null, 'Chot som van giu code_shown (phien con mo). stderr: ' . $__nv_e );
assert_true( ! $__nv_co( $__nv_k, 'TRU_KHACH' ), 'Chot som + nguon gia: khach khong bi tru' );

/* ===== NHÃN TRẠNG THÁI — CHẠY THẬT ĐOẠN DỰNG NHÃN (26/09/2026) =====
   Chủ site báo tiếp: lượt "Nguồn giả" trên .one vẫn hiện "Hoàn thành". Đo CSDL 26/09: tiền
   hai đầu ĐÃ đúng (user 0đ, khách 0đ, không +view) — sai chỉ ở NHÃN. Nguyên nhân: lượt bị
   chặn chốt ở 'rejected', rồi user đóng tab, beacon sitetop_mark_visit_expired ghi đè thành
   'expired' (.one 3/3 lượt, .net 9/12 lượt đã mất dấu) — nhãn đọc MỖI step nên hoá "đã chốt".
   Nay nhãn đọc dấu bền vững: step 'rejected' HOẶC (có nguon_gia/ref_lech mà hai đầu 0đ).
   Trước đây test chỉ so chuỗi một dòng elseif — không thấy được cả đường đi này. */
$__nv_tab = (string) file_get_contents( $__nv_goc . '/includes/admin/tabs/tab-visits.php' );
assert_true( preg_match(
    '#(\$step = \$row->step \?\? \'started\';.*?else\{ \$st_label=\'Đang làm\';[^\n]*\})#s',
    $__nv_tab, $__nv_mt ) === 1, 'Lay duoc doan quyet dinh nhan tu tab-visits.php' );
$__nv_nhan = function ( array $hang ) use ( $__nv_mt ) {
    $now_vn = '2026-09-26 21:00:00'; $visit_expiry = 600;
    $row = (object) array_merge( array( 'step' => 'started', 'verified_at' => null, 'reward_paid' => 0,
        'customer_paid' => 0, 'skip_reasons' => null, 'created_at' => '2026-09-26 20:28:56' ), $hang );
    $st_label = $st_color = $st_bg = '';
    eval( $__nv_mt[1] );
    return $st_label;
};

// N1. Lượt chốt đúng ở 'rejected' (dấu còn nguyên).
assert_equals( 'Bị chặn', $__nv_nhan( array( 'step' => 'rejected', 'verified_at' => '2026-09-26 20:30:41',
    'skip_reasons' => '["nguon_gia"]' ) ), 'step rejected phai la "Bi chan"' );

// N2. CA THẬT chủ site gửi (mã 19D8FD68, .one 26/09): dấu 'rejected' đã bị beacon xoá.
assert_equals( 'Bị chặn', $__nv_nhan( array( 'step' => 'expired', 'verified_at' => '2026-09-26 20:30:41',
    'skip_reasons' => '["nguon_gia"]' ) ),
    'Luot nguon gia bi beacon ghi de step: PHAI van la "Bi chan", KHONG duoc hien "Hoan thanh"' );

// N3. Lớp referer lệch origin, dấu bị ghi đè thành target_visited.
assert_equals( 'Bị chặn', $__nv_nhan( array( 'step' => 'target_visited', 'verified_at' => '2026-09-26 20:30:41',
    'skip_reasons' => '["ref_lech"]' ) ), 'Luot ref_lech mat dau cung phai la "Bi chan"' );

// N4. HỒI QUY: lượt làm thật ĐÃ TRẢ TIỀN mà step bị ghi đè vẫn là "Hoàn thành" (luật 25/09).
assert_equals( 'Hoàn thành', $__nv_nhan( array( 'step' => 'expired', 'verified_at' => '2026-09-26 20:30:41',
    'reward_paid' => 1, 'customer_paid' => 1 ) ), 'Luot da tra tien van la "Hoan thanh"' );

// N5. HỒI QUY: mức 1 chỉ gắn nhãn — tiền vẫn chạy nên lượt vẫn "Hoàn thành".
assert_equals( 'Hoàn thành', $__nv_nhan( array( 'step' => 'verified', 'verified_at' => '2026-09-26 20:30:41',
    'reward_paid' => 1, 'customer_paid' => 1, 'skip_reasons' => '["nguon_gia"]' ) ),
    'Muc 1 (van tra tien) khong duoc doi thanh "Bi chan"' );

/* N7. Lượt nguồn giả CŨ còn đọng ở step 'verified' mà không trả ai đồng nào (bản trước
   22/09 chốt như vậy): "bị chặn" phải thắng cả step 'verified' — 0đ hai đầu thì không có
   lượt nào "hoàn thành" ở đây cả. */
assert_equals( 'Bị chặn', $__nv_nhan( array( 'step' => 'verified', 'verified_at' => '2026-09-26 20:30:41',
    'skip_reasons' => '["nguon_gia"]' ) ),
    'Luot nguon gia 0d hai dau, du step verified, PHAI la "Bi chan"' );

// N6. HỒI QUY: lượt thường bỏ dở, quá giờ → "Hết hạn" như cũ.
assert_equals( 'Hết hạn', $__nv_nhan( array( 'step' => 'code_shown', 'created_at' => '2026-09-26 18:00:00' ) ),
    'Luot thuong qua gio van la "Het han"' );

/* ===== BEACON ĐÓNG TAB KHÔNG ĐƯỢC XOÁ DẤU 'rejected' — chạy THẬT hàm ajax =====
   page-unlock.php gắn markVisitExpired() vào beforeunload + pagehide; lượt bị chặn không bao
   giờ "hoàn thành" nên beacon luôn bắn. WHERE của nó trước đây chỉ chừa 'verified'. */
$__nv_bc = $__nv_ham( (string) file_get_contents( $__nv_goc . '/includes/shortlink-ajax.php' ),
    'sitetop_ajax_mark_visit_expired' );
assert_true( $__nv_bc !== '', 'Phai trich duoc sitetop_ajax_mark_visit_expired' );
$__nv_bc_kq = (function ( $src ) {
    $ma = <<<'PHP'
error_reporting( E_ALL );
function sanitize_text_field( $s ) { return $s; }
function wp_send_json_error( $m = null ) { echo json_encode( array( 'sql' => '', 'loi' => (string) $m ) ); exit; }
function wp_send_json_success( $m = null ) { echo json_encode( array( 'sql' => $GLOBALS['wpdb']->sql, 'loi' => '' ) ); exit; }
function sitetop_rate_limit_check( $k ) { return array( 'allowed' => true ); }
function sitetop_get_real_ip() { return '27.76.215.139'; }
class MV_Wpdb {
    public $prefix = 'wpgd_'; public $sql = '';
    public function prepare( $q, ...$a ) {
        if ( count( $a ) === 1 && is_array( $a[0] ) ) $a = $a[0];
        $i = 0;
        return preg_replace_callback( '/%[sdf]/', function ( $m ) use ( &$i, $a ) {
            $v = $a[ $i++ ] ?? null;
            return $m[0] === '%s' ? "'" . addslashes( (string) $v ) . "'" : (string) (int) $v;
        }, $q );
    }
    public function query( $q ) { $this->sql = preg_replace( '/\s+/', ' ', trim( $q ) ); return 1; }
}
$GLOBALS['wpdb'] = new MV_Wpdb();
$_POST['session_id'] = 'S1';
PHP;
    $ma .= "
" . $src . "
sitetop_ajax_mark_visit_expired();";
    $p = proc_open( array( PHP_BINARY, '-d', 'display_errors=stderr', '-r', $ma ),
        array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $ong );
    $out = stream_get_contents( $ong[1] ); $err = stream_get_contents( $ong[2] );
    fclose( $ong[1] ); fclose( $ong[2] ); proc_close( $p );
    $j = json_decode( $out, true );
    return array( is_array( $j ) ? $j : array(), $err ?: ( is_array( $j ) ? '' : $out ) );
})( $__nv_bc );
$__nv_sql = (string) ( $__nv_bc_kq[0]['sql'] ?? '' );
assert_true( $__nv_sql !== '' && stripos( $__nv_sql, "SET step = 'expired'" ) !== false,
    'Phai chay that duoc beacon va bat duoc cau UPDATE. Ra: ' . $__nv_sql . ' stderr: ' . $__nv_bc_kq[1] );
$__nv_where = substr( $__nv_sql, (int) stripos( $__nv_sql, 'WHERE' ) );
assert_true( stripos( $__nv_where, 'rejected' ) !== false,
    'Beacon dong tab PHAI chua lai step rejected — day la cho xoa dau luot bi chan. WHERE: ' . $__nv_where );
assert_true( stripos( $__nv_where, 'verified' ) !== false,
    'Beacon van phai chua lai step verified nhu cu. WHERE: ' . $__nv_where );

/* ===== CỘT LÝ DO: chạy THẬT đoạn kết xuất, không đọc chữ =====
   22/09/2026 chủ site báo: lượt nguồn giả rời khỏi 'verified' xong thì cột Lý do quay ra dán
   nhãn theo trạng thái ("Có mã, không nhập") — nhìn admin không còn biết lượt nào là nguồn giả. */
$__nv_con = function ( $ma ) {
    $p = proc_open( array( PHP_BINARY, '-d', 'display_errors=stderr', '-r', $ma ),
        array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $ong );
    $out = stream_get_contents( $ong[1] ); $err = stream_get_contents( $ong[2] );
    fclose( $ong[1] ); fclose( $ong[2] ); proc_close( $p );
    $j = json_decode( $out, true );
    return array( is_array( $j ) ? $j : array(), $err ?: ( is_array( $j ) ? '' : $out ) );
};
$__nv_vt = strpos( $__nv_tab, '<td class="col-reason"' );
$__nv_p1 = $__nv_vt !== false ? strpos( $__nv_tab, '<?php', $__nv_vt ) + 5 : false;
$__nv_p2 = $__nv_p1 ? strpos( $__nv_tab, '?></td>', $__nv_p1 ) : false;
assert_true( $__nv_p1 && $__nv_p2, 'Phai trich duoc doan kết xuat cot Ly do' );
$__nv_cot = substr( $__nv_tab, $__nv_p1, $__nv_p2 - $__nv_p1 );

/* 26/09/2026: chạy CẢ HAI khối thật — khối dựng nhãn (nó tính $bi_chan, $is_verified,
   $is_expired) rồi mới tới khối cột Lý do. Test KHÔNG được chép lại luật "bị chặn": chép là
   đúng giả khi luật đổi. Tham số vào chỉ còn dữ liệu hàng + tuổi phiên. */
$__nv_ve = function ( $row, $step, $tuoi_giay = 9000 ) use ( $__nv_con, $__nv_cot, $__nv_mt ) {
    $now_vn = '2026-09-26 21:00:00';
    $row = array_merge( $row, array( 'step' => $step,
        'created_at' => date( 'Y-m-d H:i:s', strtotime( $now_vn ) - $tuoi_giay ) ) );
    $ma = 'error_reporting( E_ALL ); function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }' . "\n"
        . '$row = (object) ' . var_export( $row, true ) . ";\n"
        . '$now_vn = ' . var_export( $now_vn, true ) . '; $visit_expiry = 600;'
        . ' $st_label = $st_color = $st_bg = \'\';' . "\n"
        . '$nhan = ' . var_export( $__nv_mt[1], true ) . "; eval( \$nhan );\n"
        . '$is_self_click = false; $is_adblock_m2 = false;' . "\n"
        /* CHẠY THẬT đoạn mã bằng eval — KHÔNG được ghép kiểu "?> <mã> <?php": với php -r thì
           phần giữa hai thẻ bị IN NGUYÊN VĂN, bản ra chứa cả chữ trong mã nguồn nên mọi phép
           tìm chuỗi đều đúng giả (đã dính đúng bẫy này lúc viết test). */
        . '$cot = ' . var_export( $__nv_cot, true ) . ";\n"
        . 'ob_start(); eval( $cot ); echo json_encode( array( "ra" => trim( ob_get_clean() ) ) );';
    return $__nv_con( $ma );
};
$__nv_luot = array( 'reward_paid' => 0, 'customer_paid' => 0, 'verify_code' => '373FAE12', 'skip_reasons' => '["nguon_gia"]',
    'is_bypass' => 0, 'ip_changed' => 0, 'ip_limit_exceeded' => 0, 'adblock_detected' => 0, 'from_google' => 1,
    'keyword' => '', 'url_matched' => 1, 'traffic_type' => '2step' );

// L1. Lượt nguồn giả ĐÃ CHỐT ở 'rejected', đã quá hạn: phải hiện "Nguồn giả", KHÔNG phải "Có mã, không nhập".
list( $__nv_k, $__nv_e ) = $__nv_ve( $__nv_luot, 'rejected', 9000 );
assert_true( strpos( (string) ( $__nv_k['ra'] ?? '' ), 'Nguồn giả' ) !== false,
    'Cot Ly do cua luot "Bi chan" PHAI hien "Nguon gia". Ra: ' . ( $__nv_k['ra'] ?? '' ) . ' stderr: ' . $__nv_e );
assert_true( strpos( (string) ( $__nv_k['ra'] ?? '' ), 'Có mã, không nhập' ) === false,
    'Luot "Bi chan" khong duoc dan nhan theo trang thai ("Co ma, khong nhap")' );

// L2. Lượt thường hết hạn, có mã mà không nhập: giữ nguyên nhãn cũ (hồi quy).
$__nv_thuong = array_merge( $__nv_luot, array( 'skip_reasons' => null ) );
list( $__nv_k, $__nv_e ) = $__nv_ve( $__nv_thuong, 'code_shown', 9000 );
assert_true( strpos( (string) ( $__nv_k['ra'] ?? '' ), 'Có mã, không nhập' ) !== false,
    'Luot thuong het han van hien "Co ma, khong nhap". Ra: ' . ( $__nv_k['ra'] ?? '' ) . ' stderr: ' . $__nv_e );

// L4. Lượt nguồn giả VỪA XẢY RA (chưa quá hạn): cũng phải hiện "Nguồn giả", không phải "—".
list( $__nv_k, $__nv_e ) = $__nv_ve( $__nv_luot, 'rejected', 60 );
assert_true( strpos( (string) ( $__nv_k['ra'] ?? '' ), 'Nguồn giả' ) !== false,
    'Luot "Bi chan" con moi cung phai hien "Nguon gia" (khong phai dau gach). Ra: ' . ( $__nv_k['ra'] ?? '' ) . ' stderr: ' . $__nv_e );

// L5. CA THẬT 19D8FD68 (.one 26/09): dấu 'rejected' bị beacon xoá thành 'expired'.
list( $__nv_k, $__nv_e ) = $__nv_ve( array_merge( $__nv_luot, array( 'verified_at' => '2026-09-26 20:30:41' ) ), 'expired', 9000 );
assert_true( strpos( (string) ( $__nv_k['ra'] ?? '' ), 'Nguồn giả' ) !== false,
    'Luot nguon gia mat dau rejected: cot Ly do van phai hien "Nguon gia". Ra: ' . ( $__nv_k['ra'] ?? '' ) . ' stderr: ' . $__nv_e );

// L3. Lượt nguồn giả CŨ (còn 'verified', chưa đổi trạng thái): vẫn hiện "Nguồn giả".
list( $__nv_k, $__nv_e ) = $__nv_ve( $__nv_luot, 'verified', 60 );
assert_true( strpos( (string) ( $__nv_k['ra'] ?? '' ), 'Nguồn giả' ) !== false,
    'Luot nguon gia cu (verified) van hien "Nguon gia". Ra: ' . ( $__nv_k['ra'] ?? '' ) . ' stderr: ' . $__nv_e );
