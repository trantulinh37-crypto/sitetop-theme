<?php
/**
 * CẦU NỐI sitetop.net (NGUỒN) ⇄ sitetop.one (POOL) — 07/10/2026, chủ site yêu cầu:
 *   1. Tại .one có công tắc "Nhận nhiệm vụ từ .net" ON/OFF.
 *   2. ON: camp đang ACTIVE bên .net và được đánh dấu "cho phép nhận nguồn" được đồng bộ sang .one
 *      (vào bảng chiến dịch của .one dưới tài khoản khách hàng liên kết).
 *   3. User .one làm nhiệm vụ như bình thường — tracking, xác nhận, trả thưởng theo đúng cơ chế .one.
 *   4+5. Camp shortlink: mã do HỆ THỐNG .NET cấp (widget .net trên web khách), trạng thái hoàn thành
 *      và mã lấy từ .net — .one chỉ xác nhận lại với .net rồi mới trả thưởng user.
 *   6. OFF: mọi camp .net đang hiện trên .one tạm dừng ngay; camp nội bộ .one không đụng.
 *
 * MÔ HÌNH "PHIÊN GƯƠNG" (chọn sau khi đọc docs/BRIDGE-LESSONS.md + plugin ttp-lentop-bridge cũ):
 *   - .one tạo lượt như thường. Nếu camp là camp .net thì gọi .net (server-to-server, ký HMAC) đăng ký
 *     ĐÚNG session_id đó thành một lượt trên .net, thuộc tài khoản "pool_sitetop_one" + shortlink nội bộ.
 *   - Widget .net trên web khách khớp lượt theo IP như mọi lượt khác → đồng hồ, chống gian lận, cấp mã
 *     100% theo luồng .net. Cấp mã xong .net báo .one "phiên này đã có mã" (không gửi mã).
 *   - Khách gõ mã ở trang nhiệm vụ .one → .one hỏi .net xác minh mã (sitetop_verify_and_pay bên .net
 *     trừ tiền khách .net, cộng view; KHÔNG trả thưởng cho tài khoản pool). .net gật thì .one ghi mã +
 *     cờ vào lượt của mình rồi chạy nguyên sitetop_verify_and_pay của .one → trả thưởng user theo .one.
 *   - Hai sổ tiền độc lập: .net trừ khách hàng của .net; .one trừ "khách hàng liên kết" (tài khoản nội
 *     bộ, admin nạp qua form Nạp tiền) và trả user. Giống plugin cũ, không phát minh thêm.
 *
 * BẢO MẬT: HMAC-SHA256 ký `ts.body`, cửa sổ 10 phút, so host đối tác; phản hồi phải mang dấu st=1
 * (không tin HTTP 200 trần — bài học 6); 415/403 thì gửi lại dạng form `payload=` (bài học WAF).
 * Khoá bí mật chỉ nằm trong wp_options của HAI site, không bao giờ in ra chat/log.
 *
 * File này GIỐNG HỆT ở hai theme. Vai trò quyết định bằng hằng SITETOP_CN_VAI_TRO (functions.php).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'SITETOP_CN_NS' ) ) define( 'SITETOP_CN_NS', 'sitetop-cn/v1' );
if ( ! defined( 'SITETOP_CN_UA' ) ) {
    // Bài học 3 của cầu nối cũ: WAF chặn UA "WordPress/x" của wp_remote_*; UA trình duyệt thì cho qua.
    define( 'SITETOP_CN_UA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 SiteTopBridge/1.0' );
}

/* ============================================================
   CẤU HÌNH CHUNG
   ============================================================ */

/** 'nguon' (sitetop.net — có camp, có widget) hay 'pool' (sitetop.one — nhận camp, trả thưởng user). */
function sitetop_cn_vai_tro() {
    if ( defined( 'SITETOP_CN_VAI_TRO' ) ) return SITETOP_CN_VAI_TRO === 'pool' ? 'pool' : 'nguon';
    $host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    return ( strpos( $host, 'sitetop.one' ) !== false ) ? 'pool' : 'nguon';
}
function sitetop_cn_la_pool()  { return sitetop_cn_vai_tro() === 'pool'; }
function sitetop_cn_la_nguon() { return sitetop_cn_vai_tro() === 'nguon'; }

function sitetop_cn_secret() {
    return trim( (string) get_option( 'sitetop_cn_secret', '' ) );
}

/** URL gốc của site bên kia (mặc định theo vai trò; đổi được ở Cài đặt — local test trỏ về localhost). */
function sitetop_cn_doi_tac_url() {
    $u = trim( (string) get_option( 'sitetop_cn_doi_tac_url', '' ) );
    if ( $u === '' ) $u = sitetop_cn_la_pool() ? 'https://sitetop.net' : 'https://sitetop.one';
    return untrailingslashit( $u );
}
function sitetop_cn_doi_tac_host() {
    return strtolower( (string) wp_parse_url( sitetop_cn_doi_tac_url(), PHP_URL_HOST ) );
}
function sitetop_cn_self_host() {
    return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
}

/** Pool: công tắc "Nhận nhiệm vụ từ .net". Nguồn: công tắc "Cho sitetop.one nhận camp". */
function sitetop_cn_nhan() { return (int) get_option( 'sitetop_cn_nhan', 0 ) === 1; }
function sitetop_cn_nguon_bat() { return (int) get_option( 'sitetop_cn_bat', 1 ) === 1; }

/** Nhật ký ngắn cho bảng admin (50 dòng gần nhất) — KHÔNG ghi khoá, không ghi mã. */
function sitetop_cn_log( $dong ) {
    $nk = get_option( 'sitetop_cn_nhat_ky', array() );
    if ( ! is_array( $nk ) ) $nk = array();
    $nk[] = sitetop_current_time() . ' ' . mb_substr( (string) $dong, 0, 300 );
    if ( count( $nk ) > 50 ) $nk = array_slice( $nk, -50 );
    update_option( 'sitetop_cn_nhat_ky', $nk, false );
}

/* ============================================================
   KÝ / GỌI / XÁC THỰC
   ============================================================ */

function sitetop_cn_ky( $ts, $body, $secret = null ) {
    if ( null === $secret ) $secret = sitetop_cn_secret();
    return hash_hmac( 'sha256', $ts . '.' . $body, $secret );
}

/**
 * Gọi sang site bên kia. Trả mảng JSON (đã có dấu st=1) hoặc WP_Error.
 * $blocking=false: bắn rồi quên (chỉ dùng cho tín hiệu không quan trọng).
 */
function sitetop_cn_goi( $duong, $payload, $blocking = true, $timeout = 4 ) {
    $secret = sitetop_cn_secret();
    if ( $secret === '' ) return new WP_Error( 'cn_chua_cau_hinh', 'Cầu nối chưa có khoá bí mật' );

    $url  = sitetop_cn_doi_tac_url() . '/wp-json/' . SITETOP_CN_NS . '/' . ltrim( (string) $duong, '/' );
    $body = wp_json_encode( $payload );
    $ts   = (string) time();
    $args = array(
        'timeout'    => $blocking ? $timeout : 0.5,
        'blocking'   => (bool) $blocking,
        'user-agent' => SITETOP_CN_UA,
        'headers'    => array(
            'Content-Type'    => 'application/json; charset=utf-8',
            'Accept'          => 'application/json',
            'Accept-Language' => 'vi,en;q=0.8',
            'X-St-Ts'         => $ts,
            'X-St-Sign'       => sitetop_cn_ky( $ts, $body, $secret ),
            'X-St-Host'       => sitetop_cn_self_host(),
        ),
        'body'       => $body,
    );
    $r = wp_remote_post( $url, $args );
    if ( ! $blocking ) return true;
    if ( is_wp_error( $r ) ) return $r;

    $code = (int) wp_remote_retrieve_response_code( $r );
    if ( in_array( $code, array( 415, 403, 406 ), true ) ) {
        // WAF từ chối JSON vào wp-json (415/403 openresty/ModSecurity) → gửi lại dạng form,
        // chữ ký vẫn ký trên chuỗi JSON gốc nên bên nhận xác thực y nguyên.
        $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
        $args['body'] = array( 'payload' => $body );
        $r = wp_remote_post( $url, $args );
        if ( is_wp_error( $r ) ) return $r;
        $code = (int) wp_remote_retrieve_response_code( $r );
    }
    $data = json_decode( (string) wp_remote_retrieve_body( $r ), true );
    if ( $code < 200 || $code >= 300 ) {
        $msg = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : ( 'HTTP ' . $code );
        return new WP_Error( 'cn_http_' . $code, $msg, $data );
    }
    if ( ! is_array( $data ) || (int) ( $data['st'] ?? 0 ) !== 1 ) {
        return new WP_Error( 'cn_khong_dau', 'Phản hồi không có dấu xác nhận của cầu nối (cache/WAF?)' );
    }
    return $data;
}

/** Body hiệu lực của request đến: JSON thô, hoặc field 'payload' khi bên gửi phải đổi sang form. */
function sitetop_cn_than( $req ) {
    $raw = (string) $req->get_body();
    if ( $raw !== '' && null !== json_decode( $raw, true ) ) return $raw;
    $pl = $req->get_param( 'payload' );
    if ( is_string( $pl ) && $pl !== '' ) {
        if ( null !== json_decode( $pl, true ) ) return $pl;
        $un = wp_unslash( $pl );
        if ( null !== json_decode( $un, true ) ) return $un;
    }
    return $raw;
}

/** Xác thực request đến. Trả mảng payload hoặc WP_Error (kèm status HTTP). */
function sitetop_cn_xac_thuc( $req ) {
    $secret = sitetop_cn_secret();
    if ( $secret === '' ) return new WP_Error( 'cn_tat', 'Cầu nối chưa cấu hình khoá.', array( 'status' => 503 ) );
    $ts   = (string) $req->get_header( 'x_st_ts' );
    $sign = (string) $req->get_header( 'x_st_sign' );
    $body = sitetop_cn_than( $req );
    if ( $ts === '' || $sign === '' ) return new WP_Error( 'cn_no_sign', 'Thiếu chữ ký.', array( 'status' => 401 ) );
    if ( abs( time() - (int) $ts ) > 600 ) return new WP_Error( 'cn_stale', 'Chữ ký hết hạn.', array( 'status' => 401 ) );
    if ( ! hash_equals( sitetop_cn_ky( $ts, $body, $secret ), $sign ) ) {
        return new WP_Error( 'cn_bad_sign', 'Chữ ký không hợp lệ.', array( 'status' => 401 ) );
    }
    $host = strtolower( (string) $req->get_header( 'x_st_host' ) );
    $peer = sitetop_cn_doi_tac_host();
    if ( $peer !== '' && $host !== $peer ) {
        return new WP_Error( 'cn_nguon_la', 'Không phải site đối tác.', array( 'status' => 403 ) );
    }
    $p = json_decode( $body, true );
    return is_array( $p ) ? $p : array();
}

/** Phản hồi chuẩn: luôn có dấu st=1 để bên gọi biết handler THẬT đã chạy. */
function sitetop_cn_tra( $data ) {
    return rest_ensure_response( array_merge( array( 'st' => 1 ), (array) $data ) );
}

/** Việc làm SAU khi đã trả lời khách (không kéo dài request): xếp hàng, chạy ở shutdown. */
function sitetop_cn_hau_ky( $viec ) {
    static $hang = array(); static $gan = false;
    $hang[] = $viec;
    if ( ! $gan ) {
        $gan = true;
        add_action( 'shutdown', function () use ( &$hang ) {
            if ( function_exists( 'fastcgi_finish_request' ) ) @fastcgi_finish_request();
            foreach ( $hang as $v ) { try { call_user_func( $v ); } catch ( \Throwable $e ) { error_log( 'SiteTop cầu nối hậu kỳ: ' . $e->getMessage() ); } }
        }, 99 );
    }
}

/* ============================================================
   ĐĂNG KÝ REST — hai vai trò, hai bộ cổng
   ============================================================ */
add_action( 'rest_api_init', function () {
    $pub = array( 'permission_callback' => '__return_true', 'methods' => 'POST' );
    register_rest_route( SITETOP_CN_NS, '/ping', $pub + array( 'callback' => 'sitetop_cn_rest_ping' ) );
    if ( sitetop_cn_la_nguon() ) {
        register_rest_route( SITETOP_CN_NS, '/camps',      $pub + array( 'callback' => 'sitetop_cn_rest_camps' ) );
        register_rest_route( SITETOP_CN_NS, '/phien',      $pub + array( 'callback' => 'sitetop_cn_rest_phien' ) );
        register_rest_route( SITETOP_CN_NS, '/xac-minh',   $pub + array( 'callback' => 'sitetop_cn_rest_xac_minh' ) );
        register_rest_route( SITETOP_CN_NS, '/trang-thai', $pub + array( 'callback' => 'sitetop_cn_rest_trang_thai' ) );
    } else {
        register_rest_route( SITETOP_CN_NS, '/san-sang',   $pub + array( 'callback' => 'sitetop_cn_rest_san_sang' ) );
    }
} );

function sitetop_cn_rest_ping( $req ) {
    $p = sitetop_cn_xac_thuc( $req );
    if ( is_wp_error( $p ) ) return $p;
    return sitetop_cn_tra( array( 'ok' => true, 'vai_tro' => sitetop_cn_vai_tro(), 'host' => sitetop_cn_self_host(), 'gio' => sitetop_current_time() ) );
}

/* ============================================================
   NGUỒN (.net): cột cho_phep_nguon, tài khoản pool, cổng REST, báo "có mã"
   ============================================================ */

/** Migration cột cho_phep_nguon — y hệt cách thêm khong_doi_cd (cờ chỉ đặt khi SHOW COLUMNS thấy cột). */
add_action( 'init', function () {
    if ( ! sitetop_cn_la_nguon() ) return;
    if ( get_option( 'sitetop_migration_cho_phep_nguon_v1' ) ) return;
    global $wpdb;
    $bang = $wpdb->prefix . SITETOP_PREFIX . 'keyword_campaigns';
    $wpdb->hide_errors();
    $cot = $wpdb->get_col( "SHOW COLUMNS FROM {$bang}" );
    if ( empty( $cot ) ) { $wpdb->show_errors(); return; }
    if ( ! in_array( 'cho_phep_nguon', $cot, true ) ) {
        $wpdb->query( "ALTER TABLE {$bang} ADD COLUMN cho_phep_nguon TINYINT(1) NOT NULL DEFAULT 0" );
        $cot = $wpdb->get_col( "SHOW COLUMNS FROM {$bang}" );
    }
    $wpdb->show_errors();
    if ( in_array( 'cho_phep_nguon', $cot, true ) ) update_option( 'sitetop_migration_cho_phep_nguon_v1', time(), false );
}, 24 );

function sitetop_cn_co_cot_cho_phep() { return (bool) get_option( 'sitetop_migration_cho_phep_nguon_v1' ); }

/** Tài khoản user "pool_sitetop_one" trên .net — chủ của mọi lượt gương. Tạo lười, nhớ id vào option. */
function sitetop_cn_pool_user_id() {
    $id = (int) get_option( 'sitetop_cn_pool_user', 0 );
    if ( $id > 0 && get_userdata( $id ) ) return $id;
    $u = get_user_by( 'login', 'pool_sitetop_one' );
    if ( $u ) {
        $id = (int) $u->ID;
    } else {
        add_filter( 'pre_wp_mail', '__return_false', 99 );
        $id = wp_insert_user( array(
            'user_login'   => 'pool_sitetop_one',
            'user_pass'    => wp_generate_password( 40, true, true ),
            'user_email'   => 'pool-sitetop-one@' . sitetop_cn_self_host(),
            'display_name' => 'Pool sitetop.one',
            'role'         => 'subscriber',
        ) );
        remove_filter( 'pre_wp_mail', '__return_false', 99 );
        if ( is_wp_error( $id ) ) { sitetop_cn_log( 'Không tạo được user pool: ' . $id->get_error_message() ); return 0; }
        $id = (int) $id;
    }
    update_user_meta( $id, 'sitetop_cn_pool', 1 );   // cron dọn user inactive phải chừa tài khoản này
    update_option( 'sitetop_cn_pool_user', $id, false );
    return $id;
}

/** Shortlink nội bộ (status 'disabled' — không ai ghé được) để lượt gương có shortlink_id hợp lệ. */
function sitetop_cn_pool_shortlink_id() {
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $id = (int) get_option( 'sitetop_cn_pool_shortlink', 0 );
    if ( $id > 0 && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}user_shortlinks WHERE id = %d", $id ) ) ) return $id;
    $uid = sitetop_cn_pool_user_id();
    if ( ! $uid ) return 0;
    $id = (int) $wpdb->get_var( "SELECT id FROM {$p}user_shortlinks WHERE code = 'pool-one' LIMIT 1" );
    if ( ! $id ) {
        $wpdb->insert( "{$p}user_shortlinks", array(
            'user_id' => $uid, 'code' => 'pool-one', 'alias' => null,
            'original_url' => sitetop_cn_doi_tac_url(), 'fallback_url' => '',
            'status' => 'disabled', 'created_via' => 'cau_noi', 'created_at' => sitetop_current_time(),
        ) );
        $id = (int) $wpdb->insert_id;
    }
    if ( $id ) update_option( 'sitetop_cn_pool_shortlink', $id, false );
    return $id;
}

/** Lượt này có phải lượt gương của pool không (shortlink nội bộ)? Dùng trong sitetop_verify_and_pay. */
function sitetop_cn_la_luot_pool( $visit ) {
    if ( ! sitetop_cn_la_nguon() || ! is_object( $visit ) ) return false;
    $sl = (int) get_option( 'sitetop_cn_pool_shortlink', 0 );
    return $sl > 0 && (int) ( $visit->shortlink_id ?? 0 ) === $sl;
}

/** Dữ liệu camp gửi sang pool — chỉ các trường pool cần để hiện nhiệm vụ y hệt. */
function sitetop_cn_camp_ra( $kc, $daily_con_lai ) {
    return array(
        'id'                    => (int) $kc->id,
        'title'                 => (string) $kc->title,
        'keyword'               => (string) $kc->keyword,
        'target_url'            => (string) $kc->target_url,
        'destination_urls'      => (string) ( $kc->destination_urls ?? '' ),
        'target_title'          => (string) ( $kc->target_title ?? '' ),
        'target_description'    => (string) ( $kc->target_description ?? '' ),
        'screenshot_desktop_url'=> (string) ( $kc->screenshot_desktop_url ?? '' ),
        'screenshot_mobile_url' => (string) ( $kc->screenshot_mobile_url ?? '' ),
        'nocode_screenshot_url' => (string) ( $kc->nocode_screenshot_url ?? '' ),
        'step2_image_url'       => (string) ( $kc->step2_image_url ?? '' ),
        'step2_target_url'      => (string) ( $kc->step2_target_url ?? '' ),
        'kw_bat_go_tay'         => (int) ( $kc->kw_bat_go_tay ?? 0 ),
        'khong_doi_cd'          => (int) ( $kc->khong_doi_cd ?? 0 ),
        'serp_page'             => (int) ( $kc->serp_page ?? 1 ),
        'price_per_view'        => (float) $kc->price_per_view,
        'countdown_seconds'     => (int) $kc->countdown_seconds,
        'traffic_type'          => (string) $kc->traffic_type,
        'campaign_type'         => (string) ( $kc->task_type ?: $kc->campaign_type ),
        'onsite_time'           => (int) $kc->onsite_time,
        'fixed_code'            => (string) ( $kc->fixed_code ?? '' ),
        'daily_traffic'         => (int) $daily_con_lai,
        'quantity'              => (int) $kc->quantity,
        'completed'             => (int) $kc->completed,
        'updated_at'            => (string) $kc->updated_at,
    );
}

/** Danh sách camp .net đang active + cho phép nhận nguồn + còn hạn mức hôm nay + khách còn tiền. */
function sitetop_cn_camps_cho_pool() {
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    if ( ! sitetop_cn_co_cot_cho_phep() ) return array();
    $today = date( 'Y-m-d', strtotime( sitetop_current_time() ) );
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT kc.*, co.task_type, co.daily_traffic AS order_daily_traffic
         FROM {$p}keyword_campaigns kc
         INNER JOIN {$p}customer_orders co ON co.id = kc.order_id
         WHERE kc.status = 'active' AND co.status = 'active' AND kc.cho_phep_nguon = 1
           AND (kc.start_date IS NULL OR kc.start_date <= %s)
           AND (kc.end_date IS NULL OR kc.end_date >= %s)
         ORDER BY kc.id ASC", $today, $today ) );
    $min_balance = (int) sitetop_get_option( 'customer_min_balance', 20000 );
    $out = array();
    foreach ( (array) $rows as $kc ) {
        $loai = $kc->task_type ?: $kc->campaign_type;
        if ( $loai === 'keyword_search' && trim( (string) $kc->keyword ) === '' ) continue;
        $bal = function_exists( 'sitetop_get_customer_balance_amount' ) ? sitetop_get_customer_balance_amount( (int) $kc->customer_id ) : 0;
        if ( $bal === false || (float) $bal <= $min_balance + max( (float) $kc->price_per_view, 5000 ) ) continue;
        $daily = (int) $kc->daily_traffic > 0 ? (int) $kc->daily_traffic : ( (int) $kc->order_daily_traffic > 0 ? (int) $kc->order_daily_traffic : 10 );
        $done  = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}shortlink_visits WHERE campaign_id = %d AND (step = 'verified' OR customer_paid = 1) AND DATE(created_at) = %s",
            (int) $kc->id, $today ) );
        $con = $daily - $done;                      // bài học 14: trần ngày tính TOÀN HỆ (nguồn + pool)
        if ( $con <= 0 ) continue;
        $out[] = sitetop_cn_camp_ra( $kc, $con );
    }
    return $out;
}

function sitetop_cn_rest_camps( $req ) {
    $p = sitetop_cn_xac_thuc( $req );
    if ( is_wp_error( $p ) ) return $p;
    if ( ! sitetop_cn_nguon_bat() ) return sitetop_cn_tra( array( 'ok' => true, 'bat' => false, 'camps' => array() ) );
    return sitetop_cn_tra( array( 'ok' => true, 'bat' => true, 'camps' => sitetop_cn_camps_cho_pool(), 'gio' => sitetop_current_time() ) );
}

/** Camp còn nhận lượt từ pool không (active + cho phép + đơn active). */
function sitetop_cn_camp_nhan_duoc( $camp_id ) {
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    if ( ! sitetop_cn_co_cot_cho_phep() ) return null;
    return $wpdb->get_row( $wpdb->prepare(
        "SELECT kc.id, kc.order_id FROM {$p}keyword_campaigns kc INNER JOIN {$p}customer_orders co ON co.id = kc.order_id
         WHERE kc.id = %d AND kc.status = 'active' AND co.status = 'active' AND kc.cho_phep_nguon = 1", (int) $camp_id ) );
}

/**
 * Đăng ký (hoặc đặt lại) lượt gương. Chính sách với lượt đã có:
 *   - đã verified / đã trả: 'da_xong' (không đụng);
 *   - ĐÃ CÓ MÃ: 'co_ma' (không đặt lại — khách đang cầm mã quay về trang .one, đặt lại là mã vô hiệu);
 *   - chưa có mã: đặt lại cờ như nhánh tái dùng của sitetop_create_visit_session, GIỮ created_at.
 */
function sitetop_cn_dang_ky_luot_guong( $sid, $camp_id, $ip, $ua, $referer ) {
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    if ( ! preg_match( '/^[A-Za-z0-9]{8,32}$/', (string) $sid ) ) return new WP_Error( 'sid_sai', 'Session không hợp lệ' );
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) return new WP_Error( 'ip_sai', 'IP không hợp lệ' );
    $camp = sitetop_cn_camp_nhan_duoc( $camp_id );
    if ( ! $camp ) return new WP_Error( 'camp_khong_nhan', 'Chiến dịch không còn nhận lượt từ pool' );
    $sl = sitetop_cn_pool_shortlink_id();
    $uid = sitetop_cn_pool_user_id();
    if ( ! $sl || ! $uid ) return new WP_Error( 'thieu_tai_khoan', 'Chưa tạo được tài khoản pool trên nguồn' );

    $cu = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}shortlink_visits WHERE session_id = %s", $sid ) );
    $now = sitetop_current_time();
    if ( $cu ) {
        if ( (int) $cu->shortlink_id !== (int) $sl ) return new WP_Error( 'phien_khac', 'Session trùng với lượt nội bộ' );
        if ( $cu->step === 'verified' || (int) $cu->reward_paid === 1 || ! empty( $cu->verified_at ) ) return array( 'trang_thai' => 'da_xong' );
        if ( ! empty( $cu->verify_code ) ) return array( 'trang_thai' => 'co_ma' );
        foreach ( array( 'widget_code_ready_', 'widget_cd_', 'widget_code_', 'verify_code_', 'google_clicked_' ) as $k ) delete_transient( 'sitetop_' . $k . $sid );
        $wpdb->update( "{$p}shortlink_visits", array(
            'campaign_id' => (int) $camp->id, 'order_id' => (int) $camp->order_id,
            'step' => 'started', 'verify_code' => null, 'code_shown_at' => null,
            'from_google' => 0, 'url_matched' => 0, 'google_clicked_at' => null, 'target_visited_at' => null,
            'ip_address' => $ip,
        ), array( 'id' => (int) $cu->id ) );
        return array( 'trang_thai' => 'dat_lai' );
    }
    $ok = $wpdb->insert( "{$p}shortlink_visits", array(
        'shortlink_id' => $sl, 'campaign_id' => (int) $camp->id, 'order_id' => (int) $camp->order_id,
        'user_id' => $uid, 'session_id' => $sid,
        'ip_address' => $ip, 'original_ip' => $ip,
        'user_agent' => mb_substr( (string) $ua, 0, 500 ),
        'referer' => 'pool:' . mb_substr( (string) $referer, 0, 200 ),
        'step' => 'started', 'created_at' => $now,
    ) );
    if ( ! $ok ) return new WP_Error( 'db', 'Không ghi được lượt gương' );
    if ( function_exists( 'sitetop_ghi_vet' ) ) sitetop_ghi_vet( $sid, 'tu_pool', sitetop_cn_doi_tac_host() );
    return array( 'trang_thai' => 'moi' );
}

function sitetop_cn_rest_phien( $req ) {
    $p = sitetop_cn_xac_thuc( $req );
    if ( is_wp_error( $p ) ) return $p;
    if ( ! sitetop_cn_nguon_bat() ) return sitetop_cn_tra( array( 'ok' => false, 'ma_loi' => 'tat', 'thong_bao' => 'Nguồn đang tắt cầu nối' ) );
    $r = sitetop_cn_dang_ky_luot_guong(
        sanitize_text_field( $p['sid'] ?? '' ), (int) ( $p['camp_id'] ?? 0 ),
        sanitize_text_field( $p['ip'] ?? '' ), (string) ( $p['ua'] ?? '' ), (string) ( $p['referer'] ?? '' ) );
    if ( is_wp_error( $r ) ) return sitetop_cn_tra( array( 'ok' => false, 'ma_loi' => $r->get_error_code(), 'thong_bao' => $r->get_error_message() ) );
    return sitetop_cn_tra( array( 'ok' => true ) + $r );
}

/** Pool hỏi: phiên này đã có mã chưa / xong chưa. KHÔNG trả mã. */
function sitetop_cn_rest_trang_thai( $req ) {
    $p = sitetop_cn_xac_thuc( $req );
    if ( is_wp_error( $p ) ) return $p;
    global $wpdb; $pre = $wpdb->prefix . SITETOP_PREFIX;
    $sid = sanitize_text_field( $p['sid'] ?? '' );
    $v = $wpdb->get_row( $wpdb->prepare( "SELECT shortlink_id, step, verify_code, verified_at, customer_paid FROM {$pre}shortlink_visits WHERE session_id = %s", $sid ) );
    if ( ! $v || ! sitetop_cn_la_luot_pool( $v ) ) return sitetop_cn_tra( array( 'ok' => false, 'ma_loi' => 'khong_co_phien' ) );
    return sitetop_cn_tra( array(
        'ok' => true, 'step' => $v->step,
        'co_ma' => ! empty( $v->verify_code ),
        'xong' => ( $v->step === 'verified' || ! empty( $v->verified_at ) ),
        'customer_paid' => (int) $v->customer_paid,
    ) );
}

/**
 * Pool gửi mã khách gõ → chạy NGUYÊN sitetop_verify_and_pay của .net (trừ tiền khách .net, cộng view,
 * không trả thưởng tài khoản pool — xem nhánh sitetop_cn_la_luot_pool trong hàm đó).
 * Idempotent: lần hai với CÙNG mã đúng → 'da_xong_truoc' (ok) để pool chạy tiếp phần trả thưởng của nó.
 */
function sitetop_cn_xac_minh_luot_guong( $sid, $code ) {
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $v = $wpdb->get_row( $wpdb->prepare( "SELECT id, shortlink_id, verify_code, step, verified_at FROM {$p}shortlink_visits WHERE session_id = %s", $sid ) );
    if ( ! $v ) return array( 'ok' => false, 'ma_loi' => 'khong_co_phien', 'thong_bao' => 'Không tìm thấy phiên bên nguồn' );
    if ( ! sitetop_cn_la_luot_pool( $v ) ) return array( 'ok' => false, 'ma_loi' => 'khong_phai_pool', 'thong_bao' => 'Phiên không thuộc pool' );
    /* ĐÃ CHỐT TỪ TRƯỚC (pool gọi lại vì mất phản hồi/timeout): lượt pool không trả thưởng nên reward_paid = 0 và
       sitetop_verify_and_pay không rơi vào 'already_used' mà rơi vào 'code_not_ready' (transient đã xoá lúc chốt) —
       đo e2e local 07/10. Chốt ở đây: đã verified + ĐÚNG mã → ok, để pool chạy tiếp phần thưởng của nó (pool tự chống
       trả hai lần); sai mã → từ chối. KHÔNG gọi lại verify_and_pay nên khách .net không bị đụng lần hai. */
    if ( $v->step === 'verified' || ! empty( $v->verified_at ) ) {
        if ( ! empty( $v->verify_code ) && 0 === strcasecmp( (string) $v->verify_code, (string) $code ) ) {
            return array( 'ok' => true, 'trang_thai' => 'da_xong_truoc' );
        }
        return array( 'ok' => false, 'ma_loi' => 'wrong_code', 'thong_bao' => 'Mã xác minh không đúng' );
    }
    if ( ! function_exists( 'sitetop_verify_and_pay' ) ) return array( 'ok' => false, 'ma_loi' => 'thieu_ham', 'thong_bao' => 'Nguồn thiếu hàm xác minh' );
    $r = sitetop_verify_and_pay( $sid, $code );
    if ( is_wp_error( $r ) ) {
        if ( $r->get_error_code() === 'already_used' && ! empty( $v->verify_code ) && 0 === strcasecmp( (string) $v->verify_code, (string) $code ) ) {
            return array( 'ok' => true, 'trang_thai' => 'da_xong_truoc' );
        }
        return array( 'ok' => false, 'ma_loi' => $r->get_error_code(), 'thong_bao' => $r->get_error_message(), 'du_lieu' => $r->get_error_data() );
    }
    $sau = $wpdb->get_row( $wpdb->prepare( "SELECT step, customer_paid, completion_time FROM {$p}shortlink_visits WHERE session_id = %s", $sid ) );
    return array(
        'ok' => true, 'trang_thai' => 'verified',
        'step' => $sau ? $sau->step : '', 'customer_paid' => $sau ? (int) $sau->customer_paid : 0,
        'completion_time' => $sau ? (int) $sau->completion_time : 0,
    );
}

function sitetop_cn_rest_xac_minh( $req ) {
    $p = sitetop_cn_xac_thuc( $req );
    if ( is_wp_error( $p ) ) return $p;
    $sid  = sanitize_text_field( $p['sid'] ?? '' );
    $code = sanitize_text_field( $p['code'] ?? '' );
    if ( $sid === '' || $code === '' ) return sitetop_cn_tra( array( 'ok' => false, 'ma_loi' => 'thieu', 'thong_bao' => 'Thiếu phiên hoặc mã' ) );
    return sitetop_cn_tra( sitetop_cn_xac_minh_luot_guong( $sid, $code ) );
}

/** Nguồn vừa cấp mã cho lượt gương → báo pool (sau khi đã trả lời widget, không kéo dài request). */
function sitetop_cn_bao_pool_co_ma( $sid ) {
    if ( ! sitetop_cn_la_nguon() ) return;
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $v = $wpdb->get_row( $wpdb->prepare( "SELECT shortlink_id FROM {$p}shortlink_visits WHERE session_id = %s", $sid ) );
    if ( ! $v || ! sitetop_cn_la_luot_pool( $v ) ) return;
    sitetop_cn_hau_ky( function () use ( $sid ) {
        $r = sitetop_cn_goi( 'san-sang', array( 'sid' => $sid ), true, 3 );
        if ( is_wp_error( $r ) ) sitetop_cn_log( 'Báo pool có mã thất bại (' . $sid . '): ' . $r->get_error_message() );
    } );
}

/* ============================================================
   POOL (.one): tài khoản liên kết, đồng bộ camp, lượt gương, xác minh qua nguồn
   ============================================================ */

/** Tiền tố tiêu đề đánh dấu camp .net (cùng quy ước '[host#ref]' của cầu nối cũ → sitetop_is_bridge_campaign nhận ra). */
function sitetop_cn_tien_to() { return '[' . sitetop_cn_doi_tac_host() . '#'; }

function sitetop_cn_map() { $m = get_option( 'sitetop_cn_map', array() ); return is_array( $m ) ? $m : array(); }
function sitetop_cn_map_set( $m ) { update_option( 'sitetop_cn_map', $m, false ); }

/** Camp (object hoặc id) có phải camp .net không. */
function sitetop_cn_la_camp_nguon( $camp ) {
    if ( ! sitetop_cn_la_pool() ) return false;
    $id = is_object( $camp ) ? (int) ( $camp->id ?? 0 ) : (int) $camp;
    if ( $id > 0 && in_array( $id, array_map( 'intval', sitetop_cn_map() ), true ) ) return true;
    if ( is_object( $camp ) && isset( $camp->title ) && 0 === strpos( (string) $camp->title, sitetop_cn_tien_to() ) ) return true;
    return false;
}

/** Tài khoản khách hàng liên kết "nguon_sitetop_net" — mọi camp .net trên .one thuộc tài khoản này. */
function sitetop_cn_fed_customer_id() {
    $id = (int) get_option( 'sitetop_cn_fed_customer', 0 );
    if ( $id > 0 && get_userdata( $id ) ) return $id;
    $u = get_user_by( 'login', 'nguon_sitetop_net' );
    if ( $u ) {
        $id = (int) $u->ID;
    } else {
        add_filter( 'pre_wp_mail', '__return_false', 99 );
        $id = wp_insert_user( array(
            'user_login'   => 'nguon_sitetop_net',
            'user_pass'    => wp_generate_password( 40, true, true ),
            'user_email'   => 'nguon-sitetop-net@' . sitetop_cn_self_host(),
            'display_name' => 'Nguồn sitetop.net',
            'role'         => 'customer',
        ) );
        remove_filter( 'pre_wp_mail', '__return_false', 99 );
        if ( is_wp_error( $id ) ) { sitetop_cn_log( 'Không tạo được tài khoản liên kết: ' . $id->get_error_message() ); return 0; }
        $id = (int) $id;
    }
    update_user_meta( $id, 'sitetop_cn_fed', 1 );
    update_option( 'sitetop_cn_fed_customer', $id, false );
    if ( function_exists( 'sitetop_sync_customer_balance' ) ) sitetop_sync_customer_balance( $id ); // phải có dòng customer_balance thì phân phối mới thấy camp
    return $id;
}

/** Tạm dừng NGAY mọi camp .net đang active trên .one (công tắc OFF / nguồn không còn gửi). */
function sitetop_cn_tam_dung_tat_ca( $ly_do = 'tat' ) {
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $ids = array_map( 'intval', array_values( sitetop_cn_map() ) );
    if ( empty( $ids ) ) return 0;
    $in = implode( ',', $ids );
    $n = $wpdb->query( "UPDATE {$p}keyword_campaigns SET status = 'paused', updated_at = '" . esc_sql( sitetop_current_time() ) . "' WHERE id IN ({$in}) AND status = 'active'" );
    delete_transient( 'sitetop_eligible_campaigns' );
    if ( $n ) sitetop_cn_log( "Tạm dừng {$n} camp .net ({$ly_do})" );
    return (int) $n;
}

/** Trường cập nhật từ camp nguồn → camp pool (qua sitetop_update_campaign, chỉ cột trong danh sách cho phép). */
function sitetop_cn_truong_cap_nhat( $c ) {
    $d = array(
        'title'                  => sitetop_cn_tien_to() . (int) $c['id'] . '] ' . sanitize_text_field( $c['title'] ?? '' ),
        'keyword'                => sanitize_text_field( $c['keyword'] ?? '' ),
        'target_url'             => esc_url_raw( $c['target_url'] ?? '' ),
        'traffic_type'           => in_array( $c['traffic_type'] ?? '', array( '1step', '2step', 'nocode' ), true ) ? $c['traffic_type'] : '1step',
        'price_per_view'         => (float) ( $c['price_per_view'] ?? 0 ),
        'quantity'               => max( 1, (int) ( $c['quantity'] ?? 0 ) ),
        'daily_traffic'          => max( 1, (int) ( $c['daily_traffic'] ?? 1 ) ),
        'onsite_time'            => max( 10, (int) ( $c['onsite_time'] ?? 70 ) ),
        'countdown_seconds'      => max( 5, (int) ( $c['countdown_seconds'] ?? 30 ) ),
        'fixed_code'             => sanitize_text_field( $c['fixed_code'] ?? '' ),
        'screenshot_desktop_url' => esc_url_raw( $c['screenshot_desktop_url'] ?? '' ),
        'screenshot_mobile_url'  => esc_url_raw( $c['screenshot_mobile_url'] ?? '' ),
        'nocode_screenshot_url'  => esc_url_raw( $c['nocode_screenshot_url'] ?? '' ),
        'step2_image_url'        => esc_url_raw( $c['step2_image_url'] ?? '' ),
        'step2_target_url'       => esc_url_raw( $c['step2_target_url'] ?? '' ),
        'kw_bat_go_tay'          => (int) ! empty( $c['kw_bat_go_tay'] ),
        'serp_page'              => max( 1, min( 10, (int) ( $c['serp_page'] ?? 1 ) ) ),
        'status'                 => 'active',
    );
    if ( ! empty( $c['destination_urls'] ) ) $d['destination_urls'] = (string) $c['destination_urls'];
    if ( get_option( 'sitetop_migration_khong_doi_cd_v1' ) ) $d['khong_doi_cd'] = (int) ! empty( $c['khong_doi_cd'] );
    return $d;
}

/**
 * Đồng bộ camp từ nguồn. Gọi từ cron 5 phút, lúc bật công tắc, nút "Đồng bộ ngay".
 * Trả mảng thống kê hoặc WP_Error.
 */
function sitetop_cn_dong_bo( $ly_do = 'cron' ) {
    if ( ! sitetop_cn_la_pool() ) return new WP_Error( 'vai_tro', 'Không phải pool' );
    if ( ! sitetop_cn_nhan() ) {
        $n = sitetop_cn_tam_dung_tat_ca( 'cong_tac_off' );
        update_option( 'sitetop_cn_lan_cuoi', array( 'gio' => sitetop_current_time(), 'ly_do' => $ly_do, 'ket_qua' => 'off', 'tam_dung' => $n ), false );
        return array( 'off' => true, 'tam_dung' => $n );
    }
    $fed = sitetop_cn_fed_customer_id();
    if ( ! $fed ) return new WP_Error( 'fed', 'Chưa tạo được tài khoản liên kết' );

    $r = sitetop_cn_goi( 'camps', array( 'tu' => sitetop_cn_self_host() ), true, 8 );
    if ( is_wp_error( $r ) ) {
        sitetop_cn_log( 'Đồng bộ lỗi (' . $ly_do . '): ' . $r->get_error_message() );
        update_option( 'sitetop_cn_lan_cuoi', array( 'gio' => sitetop_current_time(), 'ly_do' => $ly_do, 'ket_qua' => 'loi', 'loi' => $r->get_error_message() ), false );
        return $r;
    }
    $camps = is_array( $r['camps'] ?? null ) ? $r['camps'] : array();
    if ( empty( $r['bat'] ) ) $camps = array();   // nguồn tắt cầu nối → coi như không còn camp nào

    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $map = sitetop_cn_map();
    $fed_user = get_userdata( $fed );
    $tao = $sua = $loi = 0; $con = array();
    add_filter( 'pre_wp_mail', '__return_false', 99 );   // không gửi email "camp mới" cho từng camp đồng bộ
    foreach ( $camps as $c ) {
        $nid = (int) ( $c['id'] ?? 0 );
        if ( $nid <= 0 ) continue;
        $cid = isset( $map[ $nid ] ) ? (int) $map[ $nid ] : 0;
        if ( $cid && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}keyword_campaigns WHERE id = %d", $cid ) ) ) $cid = 0;
        if ( ! $cid ) {
            // Tìm lại theo tiền tố tiêu đề (map mất) trước khi tạo mới — chống tạo trùng.
            $cid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}keyword_campaigns WHERE title LIKE %s ORDER BY id DESC LIMIT 1",
                $wpdb->esc_like( sitetop_cn_tien_to() . $nid . ']' ) . '%' ) );
        }
        $truong = sitetop_cn_truong_cap_nhat( $c );
        if ( ! $cid ) {
            $moi = sitetop_create_keyword_campaign( array(
                'customer_id'       => $fed,
                'customer_username' => $fed_user ? $fed_user->user_login : 'nguon_sitetop_net',
                'title'             => $truong['title'],
                'keyword'           => $truong['keyword'],
                'target_url'        => $truong['target_url'],
                'target_title'      => sanitize_text_field( $c['target_title'] ?? '' ),
                'target_description'=> sanitize_textarea_field( $c['target_description'] ?? '' ),
                'task_type'         => ( $c['campaign_type'] ?? '' ) === 'traffic_direct' ? 'traffic_direct' : 'keyword_search',
                'traffic_type'      => $truong['traffic_type'] ?? '1step',
                'quantity'          => $truong['quantity'],
                'daily_traffic'     => $truong['daily_traffic'],
                'onsite_time'       => $truong['onsite_time'],
                'countdown_seconds' => $truong['countdown_seconds'],
                'fixed_code'        => $truong['fixed_code'],
                'price_per_view'    => $truong['price_per_view'],   // KH (liên kết) trả = giá nguồn; user nhận = chuẩn .one (hàm tạo tự tính)
            ) );
            if ( is_wp_error( $moi ) || ! $moi ) { $loi++; sitetop_cn_log( 'Tạo camp #' . $nid . ' lỗi: ' . ( is_wp_error( $moi ) ? $moi->get_error_message() : 'db' ) ); continue; }
            $cid = (int) $moi; $tao++;
        } else {
            $sua++;
        }
        $map[ $nid ] = $cid;
        sitetop_update_campaign( $cid, $truong );
        $con[] = $cid;
    }
    remove_filter( 'pre_wp_mail', '__return_false', 99 );
    sitetop_cn_map_set( $map );

    // Camp .net không còn trong danh sách (nguồn dừng/hết tiền/hết hạn mức/bỏ cho phép) → tạm dừng ngay.
    $dung = 0;
    $cu = array_diff( array_map( 'intval', array_values( $map ) ), $con );
    if ( ! empty( $cu ) ) {
        $in = implode( ',', $cu );
        $dung = (int) $wpdb->query( "UPDATE {$p}keyword_campaigns SET status = 'paused', updated_at = '" . esc_sql( sitetop_current_time() ) . "' WHERE id IN ({$in}) AND status = 'active'" );
    }
    delete_transient( 'sitetop_eligible_campaigns' );
    $kq = array( 'gio' => sitetop_current_time(), 'ly_do' => $ly_do, 'ket_qua' => 'ok', 'so_camp' => count( $con ), 'tao' => $tao, 'sua' => $sua, 'tam_dung' => $dung, 'loi' => $loi );
    update_option( 'sitetop_cn_lan_cuoi', $kq, false );
    sitetop_cn_log( "Đồng bộ ({$ly_do}): {$tao} tạo, {$sua} cập nhật, {$dung} tạm dừng, {$loi} lỗi" );
    return $kq;
}
add_action( 'sitetop_5min_cron', function () { if ( sitetop_cn_la_pool() && sitetop_cn_secret() !== '' ) sitetop_cn_dong_bo( 'cron' ); } );

/**
 * Lượt mới trên .one vừa được gán camp. Camp .net → đăng ký lượt gương bên nguồn.
 * Nguồn từ chối/không liên lạc được → đổi sang camp khác (tránh khách kẹt vì widget .net không có phiên).
 * Trả camp cuối cùng (có thể đã đổi) hoặc null (không còn camp nào).
 */
function sitetop_cn_gan_camp_pool( $campaign, $session_id, $ip, $shortlink ) {
    if ( ! $campaign || ! sitetop_cn_la_pool() || ! sitetop_cn_la_camp_nguon( $campaign ) ) return $campaign;
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $thu = 0;
    while ( $campaign && $thu < 2 ) {
        $thu++;
        $ok = false; $r = null;
        if ( sitetop_cn_nhan() ) {
            $nid = array_search( (int) $campaign->id, array_map( 'intval', sitetop_cn_map() ), true );
            if ( $nid !== false ) {
                $r = sitetop_cn_goi( 'phien', array(
                    'sid' => $session_id, 'camp_id' => (int) $nid, 'ip' => $ip,
                    'ua' => (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 'referer' => (string) ( $_SERVER['HTTP_REFERER'] ?? '' ),
                ), true, 4 );
                $ok = ! is_wp_error( $r ) && ! empty( $r['ok'] );
                if ( ! $ok ) sitetop_cn_log( 'Đăng ký phiên #' . $nid . ' thất bại: ' . ( is_wp_error( $r ) ? $r->get_error_message() : ( $r['thong_bao'] ?? $r['ma_loi'] ?? '?' ) ) );
            }
        }
        if ( $ok ) return $campaign;
        // Camp .net này không dùng được lúc này → tạm dừng nó tại pool nếu nguồn báo không nhận nữa, rồi chọn camp khác.
        if ( isset( $r ) && ! is_wp_error( $r ) && in_array( $r['ma_loi'] ?? '', array( 'camp_khong_nhan', 'tat' ), true ) ) {
            $wpdb->update( "{$p}keyword_campaigns", array( 'status' => 'paused' ), array( 'id' => (int) $campaign->id, 'status' => 'active' ) );
            delete_transient( 'sitetop_eligible_campaigns' );
        }
        $khac = function_exists( 'sitetop_get_random_active_campaign' ) ? sitetop_get_random_active_campaign( $ip, (int) $campaign->id ) : null;
        if ( ! $khac ) return null;
        $wpdb->update( "{$p}shortlink_visits", array( 'campaign_id' => (int) $khac->id, 'order_id' => (int) ( $khac->order_id ?? 0 ) ), array( 'session_id' => $session_id ) );
        $campaign = $khac;
        if ( ! sitetop_cn_la_camp_nguon( $campaign ) ) return $campaign;   // camp nội bộ: xong
    }
    return null;
}

/** Nguồn báo "phiên này đã có mã" → bật cờ sẵn sàng cho trang nhiệm vụ .one (mã KHÔNG đi qua đây). */
function sitetop_cn_danh_dau_co_ma( $sid ) {
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $v = $wpdb->get_row( $wpdb->prepare( "SELECT id, campaign_id, step, code_shown_at FROM {$p}shortlink_visits WHERE session_id = %s", $sid ) );
    if ( ! $v || ! sitetop_cn_la_camp_nguon( (int) $v->campaign_id ) ) return false;
    $expiry = max( 60, (int) sitetop_get_option( 'verify_code_expiry', 600 ) );
    set_transient( 'sitetop_widget_code_ready_' . $sid, 1, $expiry );
    set_transient( 'sitetop_cn_ma_' . $sid, 1, $expiry );   // marker "mã do nguồn cấp" — miễn cổng captcha widget (pool có captcha riêng ở trang nhiệm vụ)
    if ( $v->step !== 'verified' && empty( $v->code_shown_at ) ) {
        $wpdb->update( "{$p}shortlink_visits", array( 'step' => 'code_shown', 'code_shown_at' => sitetop_current_time() ), array( 'id' => (int) $v->id ) );
    }
    return true;
}

function sitetop_cn_rest_san_sang( $req ) {
    $p = sitetop_cn_xac_thuc( $req );
    if ( is_wp_error( $p ) ) return $p;
    $sid = sanitize_text_field( $p['sid'] ?? '' );
    if ( ! preg_match( '/^[A-Za-z0-9]{8,32}$/', $sid ) ) return sitetop_cn_tra( array( 'ok' => false, 'ma_loi' => 'sid_sai' ) );
    return sitetop_cn_tra( array( 'ok' => (bool) sitetop_cn_danh_dau_co_ma( $sid ) ) );
}

/** Trang nhiệm vụ .one hỏi "mã sẵn sàng chưa" mà cờ chưa có → hỏi nguồn, tối đa 1 lần / 6 giây / phiên. */
function sitetop_cn_hoi_co_ma( $sid ) {
    if ( ! sitetop_cn_la_pool() || ! preg_match( '/^[A-Za-z0-9]{8,32}$/', (string) $sid ) ) return;
    if ( get_transient( 'sitetop_widget_code_ready_' . $sid ) ) return;
    if ( get_transient( 'sitetop_cn_hoi_' . $sid ) ) return;
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $cid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT campaign_id FROM {$p}shortlink_visits WHERE session_id = %s", $sid ) );
    if ( ! $cid || ! sitetop_cn_la_camp_nguon( $cid ) ) return;
    set_transient( 'sitetop_cn_hoi_' . $sid, 1, 6 );
    $r = sitetop_cn_goi( 'trang-thai', array( 'sid' => $sid ), true, 3 );
    if ( ! is_wp_error( $r ) && ! empty( $r['ok'] ) && ! empty( $r['co_ma'] ) ) sitetop_cn_danh_dau_co_ma( $sid );
}

/**
 * Chạy trong sitetop_verify_and_pay (pool), NGAY SAU sitetop_bridge_rescue_code và TRƯỚC mọi chốt mã.
 * Camp .net: hỏi nguồn xác minh mã. Nguồn gật → ghi mã + cờ vào lượt .one (như rescue), arm transient,
 * rồi để luồng .one chạy tiếp y nguyên (trả thưởng theo luật .one). Nguồn lắc → trả đúng lỗi của nguồn.
 * Trả null khi không phải việc của mình (camp nội bộ / không phải pool).
 */
function sitetop_cn_truoc_xac_minh( $visit, $session_id, $code ) {
    if ( ! sitetop_cn_la_pool() || ! is_object( $visit ) ) return null;
    if ( ! sitetop_cn_la_camp_nguon( (object) array( 'id' => (int) ( $visit->campaign_id ?? 0 ), 'title' => (string) ( $visit->camp_title ?? '' ) ) ) ) return null;
    $code = trim( (string) $code );
    if ( $code === '' ) return new WP_Error( 'wrong_code', 'Mã xác minh không đúng' );

    $r = sitetop_cn_goi( 'xac-minh', array( 'sid' => $session_id, 'code' => $code ), true, 8 );
    if ( is_wp_error( $r ) ) {
        sitetop_cn_log( 'Xác minh qua nguồn lỗi (' . $session_id . '): ' . $r->get_error_message() );
        return new WP_Error( 'cn_loi', 'Chưa liên lạc được với hệ thống cấp mã, vui lòng thử lại sau vài giây.' );
    }
    if ( empty( $r['ok'] ) ) {
        $ma = (string) ( $r['ma_loi'] ?? 'wrong_code' );
        $tb = (string) ( $r['thong_bao'] ?? 'Mã xác minh không đúng' );
        return new WP_Error( $ma !== '' ? $ma : 'wrong_code', $tb, $r['du_lieu'] ?? null );
    }

    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $now = sitetop_current_time();
    $cap = array( 'verify_code' => $code, 'from_google' => 1, 'url_matched' => 1 );
    if ( $visit->step !== 'verified' ) $cap['step'] = 'code_shown';
    if ( empty( $visit->code_shown_at ) ) $cap['code_shown_at'] = $now;
    $wpdb->update( "{$p}shortlink_visits", $cap, array( 'session_id' => $session_id ) );

    $expiry = max( 60, (int) sitetop_get_option( 'verify_code_expiry', 600 ) );
    set_transient( 'sitetop_widget_code_ready_' . $session_id, 1, $expiry );
    set_transient( 'sitetop_verify_code_' . $session_id, $code, $expiry );
    set_transient( 'sitetop_cn_ma_' . $session_id, 1, $expiry );

    $visit->verify_code = $code;
    $visit->from_google = 1;
    $visit->url_matched = 1;
    if ( $visit->step !== 'verified' ) $visit->step = 'code_shown';
    if ( empty( $visit->code_shown_at ) ) $visit->code_shown_at = $now;
    if ( function_exists( 'sitetop_ghi_vet' ) ) sitetop_ghi_vet( $session_id, 'nguon_gat', (string) ( $r['trang_thai'] ?? '' ) );
    return true;
}

/* ============================================================
   ADMIN: khối Cài đặt (cả hai vai trò) + lưu
   ============================================================ */

function sitetop_cn_luu_cai_dat() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( isset( $_POST['sitetop_cn_secret'] ) ) {
        $s = trim( (string) wp_unslash( $_POST['sitetop_cn_secret'] ) );
        if ( $s === '' || preg_match( '/^[A-Za-z0-9_\-]{16,128}$/', $s ) ) update_option( 'sitetop_cn_secret', $s, false );
    }
    if ( isset( $_POST['sitetop_cn_doi_tac_url'] ) ) {
        $u = esc_url_raw( trim( (string) wp_unslash( $_POST['sitetop_cn_doi_tac_url'] ) ) );
        update_option( 'sitetop_cn_doi_tac_url', $u, false );
    }
    if ( sitetop_cn_la_pool() ) {
        if ( isset( $_POST['sitetop_cn_nhan'] ) ) {
            $moi = ( (string) $_POST['sitetop_cn_nhan'] === '1' ) ? 1 : 0;
            $cu  = (int) get_option( 'sitetop_cn_nhan', 0 );
            update_option( 'sitetop_cn_nhan', $moi, false );
            if ( $moi !== $cu ) {
                sitetop_cn_log( $moi ? 'Bật nhận nhiệm vụ từ .net' : 'TẮT nhận nhiệm vụ từ .net' );
                sitetop_cn_dong_bo( $moi ? 'bat' : 'tat' );   // OFF → tạm dừng ngay; ON → kéo camp ngay
            }
        }
    } else {
        if ( isset( $_POST['sitetop_cn_bat'] ) ) update_option( 'sitetop_cn_bat', ( (string) $_POST['sitetop_cn_bat'] === '1' ) ? 1 : 0, false );
    }
}

/** Nút "Đồng bộ ngay" / "Kiểm tra kết nối" dùng GET có nonce để không lẫn với form lưu. */
add_action( 'admin_init', function () {
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'sitetop-settings' || ! current_user_can( 'manage_options' ) ) return;
    if ( isset( $_GET['cn_dong_bo'] ) && wp_verify_nonce( (string) ( $_GET['_cnn'] ?? '' ), 'sitetop_cn_dong_bo' ) ) {
        $r = sitetop_cn_la_pool() ? sitetop_cn_dong_bo( 'tay' ) : sitetop_cn_goi( 'ping', array( 'tu' => sitetop_cn_self_host() ) );
        set_transient( 'sitetop_cn_thong_bao_' . get_current_user_id(), is_wp_error( $r ) ? 'Lỗi: ' . $r->get_error_message() : 'OK: ' . wp_json_encode( $r, JSON_UNESCAPED_UNICODE ), 60 );
        wp_safe_redirect( remove_query_arg( array( 'cn_dong_bo', '_cnn' ) ) ); exit;
    }
} );

function sitetop_cn_in_cai_dat() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $pool = sitetop_cn_la_pool();
    $tb   = get_transient( 'sitetop_cn_thong_bao_' . get_current_user_id() );
    if ( $tb ) delete_transient( 'sitetop_cn_thong_bao_' . get_current_user_id() );
    $lc   = get_option( 'sitetop_cn_lan_cuoi', array() );
    $nk   = array_reverse( (array) get_option( 'sitetop_cn_nhat_ky', array() ) );
    $url_dong_bo = wp_nonce_url( add_query_arg( array( 'page' => 'sitetop-settings', 'cn_dong_bo' => 1 ), admin_url( 'admin.php' ) ), 'sitetop_cn_dong_bo', '_cnn' );
    ?>
<div class="ln-section" id="cau-noi" style="border-left:4px solid #2F6FEB">
    <h2><?php echo $pool ? 'Nhận nhiệm vụ từ sitetop.net' : 'Cho sitetop.one nhận chiến dịch'; ?>
        <span style="font-size:12px;font-weight:600;padding:2px 8px;border-radius:4px;margin-left:6px;background:#EEF2F7;color:#334155">cầu nối</span></h2>
    <?php if ( $tb ) : ?><div class="notice notice-info inline" style="margin:0 0 12px"><p><?php echo esc_html( $tb ); ?></p></div><?php endif; ?>
    <p style="font-size:12px;color:#787c82;margin-bottom:14px">
        <?php if ( $pool ) : ?>
        ON: camp đang chạy bên .net (được đánh dấu "cho phép nhận nguồn") tự đồng bộ sang đây mỗi 5 phút, chạy dưới tài khoản khách hàng liên kết
        <b>nguon_sitetop_net</b> — nạp tiền cho tài khoản này ở tab Nạp tiền để camp được phân phối (sổ nội bộ). User làm nhiệm vụ, nhận thưởng theo đúng cơ chế .one;
        mã do widget .net cấp và được xác minh với .net trước khi trả thưởng. OFF: mọi camp .net tạm dừng ngay, camp nội bộ không đổi.
        <?php else : ?>
        Chỉ camp ĐANG CHẠY có bật "Cho phép sitetop.one nhận camp này" (sửa camp ở tab Chiến dịch) mới được gửi sang .one. Lượt từ .one hiện ở tab Lượt truy cập
        dưới user <b>pool_sitetop_one</b>; khách hàng .net vẫn bị trừ tiền theo luật .net, tài khoản pool không nhận thưởng.
        <?php endif; ?>
    </p>
    <div class="ln-grid">
        <?php if ( $pool ) : ?>
        <div class="ln-field"><label>Nhận nhiệm vụ từ .net</label>
            <select name="sitetop_cn_nhan"><option value="0" <?php selected( sitetop_cn_nhan(), false ); ?>>OFF — tạm dừng mọi camp .net</option><option value="1" <?php selected( sitetop_cn_nhan(), true ); ?>>ON — đồng bộ & phục vụ camp .net</option></select></div>
        <?php else : ?>
        <div class="ln-field"><label>Cho .one nhận camp</label>
            <select name="sitetop_cn_bat"><option value="1" <?php selected( sitetop_cn_nguon_bat(), true ); ?>>ON</option><option value="0" <?php selected( sitetop_cn_nguon_bat(), false ); ?>>OFF — không gửi camp, không nhận phiên</option></select></div>
        <?php endif; ?>
        <div class="ln-field"><label>URL site <?php echo $pool ? 'nguồn (.net)' : 'pool (.one)'; ?></label>
            <input type="text" name="sitetop_cn_doi_tac_url" value="<?php echo esc_attr( get_option( 'sitetop_cn_doi_tac_url', '' ) ); ?>" placeholder="<?php echo $pool ? 'https://sitetop.net' : 'https://sitetop.one'; ?>"></div>
        <div class="ln-field"><label>Khoá bí mật (GIỐNG HỆT ở hai site)</label>
            <input type="password" name="sitetop_cn_secret" id="sitetop_cn_secret" value="<?php echo esc_attr( sitetop_cn_secret() ); ?>" autocomplete="new-password" placeholder="16–128 ký tự chữ/số">
            <div class="unit"><a href="#" onclick="var a='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789',s='',r=new Uint32Array(48);crypto.getRandomValues(r);for(var i=0;i<48;i++)s+=a[r[i]%a.length];var o=document.getElementById('sitetop_cn_secret');o.type='text';o.value=s;return false;">Tạo khoá ngẫu nhiên</a> rồi dán sang site kia, bấm Lưu ở cả hai.</div></div>
    </div>
    <p style="margin:12px 0 0;font-size:12px">
        <a class="button button-small" href="<?php echo esc_url( $url_dong_bo ); ?>"><?php echo $pool ? 'Đồng bộ ngay' : 'Kiểm tra kết nối'; ?></a>
        <?php if ( $pool && ! empty( $lc ) ) : ?>
            &nbsp; Lần cuối: <b><?php echo esc_html( $lc['gio'] ?? '' ); ?></b> (<?php echo esc_html( $lc['ly_do'] ?? '' ); ?>) —
            <?php if ( ( $lc['ket_qua'] ?? '' ) === 'ok' ) : ?>
                <?php echo (int) ( $lc['so_camp'] ?? 0 ); ?> camp đang nhận, <?php echo (int) ( $lc['tao'] ?? 0 ); ?> tạo, <?php echo (int) ( $lc['sua'] ?? 0 ); ?> cập nhật, <?php echo (int) ( $lc['tam_dung'] ?? 0 ); ?> tạm dừng
            <?php else : ?>
                <span style="color:#b32d2e"><?php echo esc_html( $lc['ket_qua'] === 'off' ? 'công tắc OFF' : ( $lc['loi'] ?? 'lỗi' ) ); ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </p>
    <?php if ( $nk ) : ?>
    <details style="margin-top:10px"><summary style="cursor:pointer;font-size:12px;color:#50575e">Nhật ký cầu nối (<?php echo count( $nk ); ?> dòng gần nhất)</summary>
        <pre style="font-size:11px;line-height:1.5;background:#F8FAFB;padding:10px;border-radius:6px;max-height:220px;overflow:auto;margin:6px 0 0"><?php echo esc_html( implode( "\n", array_slice( $nk, 0, 50 ) ) ); ?></pre></details>
    <?php endif; ?>
</div>
    <?php
}
