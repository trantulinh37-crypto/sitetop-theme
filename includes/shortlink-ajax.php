<?php
/**
 * SiteTop.net V2 - Frontend AJAX Handlers
 * Updated: uses session_id instead of visit_id
 * Section: 40+ AJAX actions
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Detect obvious non-browser / scripted HTTP clients by User-Agent.
 * Used to harden the anti-fraud flag-setting endpoints (C2): a legit widget runs
 * inside a REAL browser, which never sends these signatures. This raises the bar against
 * curl/python/headless scripts forging the Origin header to farm rewards. It is NOT a
 * complete defense (UA is spoofable) — IP daily limit + fraud scoring remain the backstop.
 * Conservative list: only unambiguous tool signatures + empty UA is treated as allowed
 * (some privacy proxies strip UA) to avoid false positives against real users.
 */
if ( ! function_exists( 'sitetop_is_scripted_client' ) ) {
    function sitetop_is_scripted_client() {
        $ua = strtolower( $_SERVER['HTTP_USER_AGENT'] ?? '' );
        if ( $ua === '' ) return false; // empty UA: don't hard-block (privacy strip) — backstops handle it
        $signatures = array(
            'curl/', 'wget', 'python-requests', 'python-urllib', 'urllib', 'aiohttp',
            'go-http-client', 'okhttp', 'libwww', 'lwp::', 'scrapy', 'apache-httpclient',
            'httpclient', 'java/', 'jakarta', 'postmanruntime', 'insomnia', 'guzzlehttp',
            'node-fetch', 'axios/', 'got (', 'restsharp', 'winhttp', 'powershell',
            'headlesschrome', 'phantomjs', 'selenium', 'puppeteer', 'playwright',
        );
        foreach ( $signatures as $sig ) {
            if ( strpos( $ua, $sig ) !== false ) return true;
        }
        return false;
    }
}

// Shorten URL (logged-in users only)
add_action('wp_ajax_sitetop_shorten_url', 'sitetop_ajax_shorten_url');
function sitetop_ajax_shorten_url() {
    check_ajax_referer('sitetop_nonce', 'nonce');
    if ( ! is_user_logged_in() ) wp_send_json_error('Vui lòng đăng nhập để tạo link');
    sitetop_block_advertiser_ajax(); // tài khoản quảng cáo không dùng khu publisher
    $url = esc_url_raw($_POST['url'] ?? '');
    if ( empty($url) || !filter_var($url, FILTER_VALIDATE_URL) ) wp_send_json_error('URL không hợp lệ');
    $rate = sitetop_rate_limit_check('shorten_url');
    if ( !$rate['allowed'] ) wp_send_json_error('Quá nhiều yêu cầu');
    $user_id = get_current_user_id();
    $alias = sanitize_text_field($_POST['alias'] ?? '');
    $result = sitetop_create_user_shortlink($user_id, $url, $alias, '', 'manual');
    if ( is_wp_error($result) ) wp_send_json_error($result->get_error_message());
    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    $sl = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}user_shortlinks WHERE id=%d", $result));
    wp_send_json_success(array('short_url'=>home_url('/'.($sl->alias ?: $sl->code)), 'code'=>$sl->code, 'alias'=>$sl->alias));
}

// Get code (by session_id) - public, no nonce (called by page-unlock + widget.js)
add_action('wp_ajax_sitetop_get_code', 'sitetop_ajax_get_code');
add_action('wp_ajax_nopriv_sitetop_get_code', 'sitetop_ajax_get_code');
function sitetop_ajax_get_code() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if (!$sid) wp_send_json_error('Missing session');
    $rate = sitetop_rate_limit_check('get_code');
    if (!$rate['allowed']) wp_send_json_error('Rate limited');
    $result = sitetop_get_widget_code($sid);
    if (is_wp_error($result)) wp_send_json_error(array('message'=>$result->get_error_message(),'data'=>$result->get_error_data()));
    wp_send_json_success(array('code'=>$result));
}

// Verify (by session_id) - public, no nonce
add_action('wp_ajax_sitetop_verify', 'sitetop_ajax_verify');
add_action('wp_ajax_nopriv_sitetop_verify', 'sitetop_ajax_verify');
function sitetop_ajax_verify() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    $code = sanitize_text_field($_POST['code'] ?? '');
    if (!$sid || !$code) wp_send_json_error('Thiếu thông tin');
    $rate = sitetop_rate_limit_check('verify_code');
    if (!$rate['allowed']) wp_send_json_error('Quá nhiều lần thử');
    $result = sitetop_verify_and_pay($sid, $code);
    if (is_wp_error($result)) wp_send_json_error(array('message'=>$result->get_error_message(),'data'=>$result->get_error_data()));
    wp_send_json_success($result);
}

// Heartbeat (by session_id) - public, no nonce
add_action('wp_ajax_sitetop_heartbeat', 'sitetop_ajax_heartbeat');
add_action('wp_ajax_nopriv_sitetop_heartbeat', 'sitetop_ajax_heartbeat');
function sitetop_ajax_heartbeat() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if (!$sid) wp_send_json_error('Missing');

    $rate = sitetop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');

    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    $ip = sitetop_get_real_ip();
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT v.*, kc.onsite_time as camp_onsite, kc.countdown_seconds, kc.traffic_type
         FROM {$p}shortlink_visits v LEFT JOIN {$p}keyword_campaigns kc ON v.campaign_id=kc.id
         WHERE v.session_id=%s AND v.ip_address=%s", $sid, $ip));
    if (!$visit) wp_send_json_error('Not found');
    $elapsed = strtotime(sitetop_current_time()) - strtotime($visit->created_at);
    $onsite = (int)($visit->camp_onsite ?? $visit->onsite_time ?? 70);
    $countdown = (int)($visit->countdown_seconds ?? 30);
    $is_nocode = ($visit->traffic_type ?? '1step') === 'nocode';
    $required = $is_nocode ? 0 : max($onsite - 5, 10);
    wp_send_json_success(array(
        'step'=>$visit->step, 'elapsed'=>$elapsed, 'countdown'=>$countdown,
        'onsite_time'=>$onsite, 'remaining'=>max(0, $required - $elapsed),
        'ready'=>$elapsed >= $required, 'traffic_type'=>$visit->traffic_type ?? '1step',
        'campaign_id'=>$visit->campaign_id,
    ));
}

// Report behavior analytics - public, no nonce
add_action('wp_ajax_sitetop_report_behavior', 'sitetop_ajax_report_behavior');
add_action('wp_ajax_nopriv_sitetop_report_behavior', 'sitetop_ajax_report_behavior');
function sitetop_ajax_report_behavior() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if (!$sid) wp_send_json_error('Missing');
    $rate = sitetop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');
    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    // Bind to the requester's own IP — prevents injecting adblock/behavior signals onto another
    // visitor's session by guessing/owning a session_id (fraud-score griefing protection).
    $ip = function_exists('sitetop_get_real_ip') ? sitetop_get_real_ip() : ( $_SERVER['REMOTE_ADDR'] ?? '' );
    $visit = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$p}shortlink_visits WHERE session_id=%s AND ip_address=%s", $sid, $ip));
    $visit_id = $visit ? $visit->id : 0;

    // Update visit flags — only adblock (client-detected, penalty flag)
    // from_google and url_matched are set SERVER-SIDE only (widget_verify_access)
    $updates = array();
    if (absint($_POST['adblock']??0)) $updates['adblock_detected'] = 1;
    if (!empty($updates) && $visit_id) {
        $wpdb->update("{$p}shortlink_visits", $updates, array('id'=>$visit_id));
    }

    // Save behavior analytics + fraud score (if function exists)
    if (function_exists('sitetop_save_behavior_analytics')) {
        // Support both formats: direct POST fields OR JSON in 'data' field
        $behavior_data = $_POST;
        if (!empty($_POST['data'])) {
            $decoded = json_decode(stripslashes($_POST['data']), true);
            if (is_array($decoded)) {
                $behavior_data = array_merge($behavior_data, $decoded);
            }
        }
        sitetop_save_behavior_analytics($visit_id, $sid, $behavior_data);
    }
    wp_send_json_success();
}

// Update visit step (google_clicked, target_visited) - public, no nonce
add_action('wp_ajax_sitetop_update_step', 'sitetop_ajax_update_step');
add_action('wp_ajax_nopriv_sitetop_update_step', 'sitetop_ajax_update_step');
function sitetop_ajax_update_step() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    $step = sanitize_text_field($_POST['step'] ?? '');
    if (!$sid || !$step) wp_send_json_error('Missing');
    $valid_steps = array('google_clicked','target_visited');
    if (!in_array($step, $valid_steps)) wp_send_json_error('Invalid step');
    if ( sitetop_is_scripted_client() ) wp_send_json_error('Forbidden');

    $rate = sitetop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');

    global $wpdb; $p = $wpdb->prefix . 'sitetop_';

    // Validate step progression: started→google_clicked→target_visited
    $allowed_from = array('google_clicked' => 'started', 'target_visited' => 'google_clicked');
    $ip = function_exists('sitetop_get_real_ip') ? sitetop_get_real_ip() : $_SERVER['REMOTE_ADDR'];
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT step FROM {$p}shortlink_visits WHERE session_id=%s AND ip_address=%s AND step != 'verified'", $sid, $ip));
    if ( ! $visit ) wp_send_json_error('Invalid');

    // Allow progression OR same step (idempotent)
    if ( $visit->step !== $allowed_from[$step] && $visit->step !== $step ) {
        wp_send_json_error('Invalid step progression');
    }

    if ($step === 'google_clicked') {
        set_transient('sitetop_google_clicked_'.$sid, 1, 1800);
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$p}shortlink_visits
             SET step = 'google_clicked',
                 google_clicked_at = COALESCE( google_clicked_at, %s )
             WHERE session_id = %s AND ip_address = %s",
            sitetop_current_time(), $sid, $ip
        ) );
    } else {
        /* target_visited_at chỉ ghi LẦN ĐẦU — cùng lý do với sitetop_ajax_track_direct_click().
           Chốt tiến trình ở trên cho phép gọi lại khi step ĐÃ là target_visited (idempotent),
           nên gọi hai lần là mốc bị đẩy về hiện tại. start_timer(step2) tính công theo mốc đó
           (credit = min(onsite, now - target_visited_at)) → công tụt gần 0 → user làm đủ giờ
           vẫn bị "Chưa đủ thời gian". */
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$p}shortlink_visits
             SET step = 'target_visited',
                 target_visited_at = COALESCE( target_visited_at, %s )
             WHERE session_id = %s AND ip_address = %s",
            sitetop_current_time(), $sid, $ip
        ) );
    }
    wp_send_json_success();
}

// User withdraw
add_action('wp_ajax_sitetop_user_withdraw', 'sitetop_ajax_user_withdraw');
function sitetop_ajax_user_withdraw() {
    check_ajax_referer('sitetop_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error('Chưa đăng nhập');
    sitetop_block_advertiser_ajax(); // tài khoản quảng cáo không dùng khu publisher
    $result = sitetop_submit_withdrawal(get_current_user_id(), floatval($_POST['amount']??0),
        sanitize_text_field($_POST['method']??'bank'), array(
        'bank_name'=>sanitize_text_field($_POST['bank_name']??''),
        'bank_account'=>sanitize_text_field($_POST['bank_account']??''),
        'bank_holder'=>sanitize_text_field($_POST['bank_holder']??''),
        'wallet_address'=>sanitize_text_field($_POST['wallet_address']??''),
    ));
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    wp_send_json_success(array('withdrawal_id'=>$result));
}

// User stats
add_action('wp_ajax_sitetop_user_stats', 'sitetop_ajax_user_stats');
function sitetop_ajax_user_stats() {
    check_ajax_referer('sitetop_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error('Chưa đăng nhập');
    sitetop_block_advertiser_ajax(); // tài khoản quảng cáo không dùng khu publisher
    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    $uid = get_current_user_id();
    $today = date('Y-m-d', strtotime(sitetop_current_time()));
    wp_send_json_success(array(
        'balance'=>sitetop_get_user_balance_amount($uid),
        'today_earned'=>(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE user_id=%d AND type='shortlink_reward' AND DATE(created_at)=%s",$uid,$today)),
        'total_earned'=>(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE user_id=%d AND type IN ('shortlink_reward','earn')",$uid)),
        'total_links'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}user_shortlinks WHERE user_id=%d",$uid)),
        'total_clicks'=>(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total_clicks),0) FROM {$p}user_shortlinks WHERE user_id=%d",$uid)),
    ));
}

/* ============================================================
   PAGE-UNLOCK AJAX HANDLERS
   These are called by page-unlock.php JavaScript
   ============================================================ */

// Track adblock detection
add_action('wp_ajax_sitetop_track_adblock', 'sitetop_ajax_track_adblock');
add_action('wp_ajax_nopriv_sitetop_track_adblock', 'sitetop_ajax_track_adblock');
function sitetop_ajax_track_adblock() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    $adblock = absint($_POST['adblock'] ?? 1);
    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    // Bind to requester IP (parity with track_adblock_mode2 / track_google_click).
    $ip = function_exists('sitetop_get_real_ip') ? sitetop_get_real_ip() : ( $_SERVER['REMOTE_ADDR'] ?? '' );
    $wpdb->update("{$p}shortlink_visits", array('adblock_detected' => $adblock ? 1 : 0), array('session_id' => $sid, 'ip_address' => $ip));
    wp_send_json_success();
}

// Track adblock mode 2 (widget.js blocked entirely)
add_action('wp_ajax_sitetop_track_adblock_mode2', 'sitetop_ajax_track_adblock_mode2');
add_action('wp_ajax_nopriv_sitetop_track_adblock_mode2', 'sitetop_ajax_track_adblock_mode2');
function sitetop_ajax_track_adblock_mode2() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    $rate = sitetop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');
    $ip = sitetop_get_real_ip();
    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    $has_col = $wpdb->get_results( "SHOW COLUMNS FROM {$p}shortlink_visits LIKE 'adblock_mode2'" );
    if ( empty( $has_col ) ) {
        $wpdb->query( "ALTER TABLE {$p}shortlink_visits ADD COLUMN adblock_mode2 TINYINT(1) NOT NULL DEFAULT 0" );
    }
    $wpdb->update( "{$p}shortlink_visits",
        array( 'adblock_mode2' => 1 ),
        array( 'session_id' => $sid, 'ip_address' => $ip ) );
    wp_send_json_success();
}

// Track Google click
add_action('wp_ajax_sitetop_track_google_click', 'sitetop_ajax_track_google_click');
add_action('wp_ajax_nopriv_sitetop_track_google_click', 'sitetop_ajax_track_google_click');
function sitetop_ajax_track_google_click() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    if ( sitetop_is_scripted_client() ) wp_send_json_error('Forbidden');
    $rate = sitetop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');
    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    // Bind to the requester's own IP — prevents injecting from_google onto another
    // visitor's session by guessing/owning a session_id (anti-fraud flag protection).
    $ip = function_exists('sitetop_get_real_ip') ? sitetop_get_real_ip() : ( $_SERVER['REMOTE_ADDR'] ?? '' );
    $wpdb->update("{$p}shortlink_visits", array(
        'from_google' => 1,
        'step' => 'google_clicked',
        'google_clicked_at' => sitetop_current_time(),
    ), array('session_id' => $sid, 'ip_address' => $ip));
    set_transient('sitetop_google_clicked_' . $sid, 1, 1800);
    wp_send_json_success();
}

// Track direct click
add_action('wp_ajax_sitetop_track_direct_click', 'sitetop_ajax_track_direct_click');
add_action('wp_ajax_nopriv_sitetop_track_direct_click', 'sitetop_ajax_track_direct_click');
function sitetop_ajax_track_direct_click() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    if ( sitetop_is_scripted_client() ) wp_send_json_error('Forbidden');
    $rate = sitetop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');
    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    // url_matched is set server-side by widget_verify_access only.
    // Bind to the requester's own IP — prevents advancing another visitor's session step.
    $ip = function_exists('sitetop_get_real_ip') ? sitetop_get_real_ip() : ( $_SERVER['REMOTE_ADDR'] ?? '' );
    /* target_visited_at chỉ ghi LẦN ĐẦU (COALESCE), không được ghi đè.
       Widget gọi hàm này 2 giây sau khi tải xong ở MỌI trang có phiên — kể cả trang thứ
       hai của camp 2 bước. Ghi đè là mốc "bắt đầu ở lại trang đích" bị đẩy về hiện tại,
       trong khi start_timer(step2) tính công theo đúng mốc đó:
           credit = min(onsite, now - target_visited_at)
       Mốc vừa bị reset → credit ≈ 0 → created_at lùi về gần hiện tại → 15 giây sau
       get_code kêu "Chưa đủ thời gian" dù user đã ở trang đích hơn 70 giây.
       Giữ mốc đầu tiên cũng đúng nghĩa hơn: đó là lúc user thật sự đặt chân tới. */
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}shortlink_visits
         SET step = 'target_visited',
             target_visited_at = COALESCE( target_visited_at, %s )
         WHERE session_id = %s AND ip_address = %s",
        sitetop_current_time(), $sid, $ip
    ) );
    wp_send_json_success();
}

/* ------------------------------------------------------------------
   BÀN GIAO NHIỆM VỤ (task hand-off)
   Trang nhiệm vụ gọi khi user THỰC SỰ lấy URL đích (bấm Copy / bôi đen
   copy tay). Đây là bằng chứng DUY NHẤT chạy được trên mọi trình duyệt
   rằng user đang đi từ nhiệm vụ sang trang đích: user dán URL nên trình
   duyệt không gửi referer, còn cookie/IP thì có sẵn từ lúc mở shortlink
   nên không phân biệt được "dán từ nhiệm vụ" với "tự gõ URL".
   Không có bàn giao → widget_verify_access không gắn phiên → không đếm ngược.
   ------------------------------------------------------------------ */
add_action('wp_ajax_sitetop_task_handoff', 'sitetop_ajax_task_handoff');
add_action('wp_ajax_nopriv_sitetop_task_handoff', 'sitetop_ajax_task_handoff');
/* Ghi lại VÌ SAO một lượt cấp dấu bàn giao bị từ chối.
   Cảnh báo "Nhiệm vụ bị chặn ở bước gắn phiên" chỉ nói được là KHÔNG có dấu, chứ
   không nói vì sao — mà "trang nhiệm vụ chưa hề gọi cấp dấu" với "gọi rồi nhưng bị
   từ chối vì lý do X" là hai chuyện khác hẳn nhau, cách sửa cũng khác. Ghi một dòng
   ngắn sống bằng đúng cửa sổ bàn giao để tin cảnh báo đọc kèm. */
function sitetop_ghi_loi_ban_giao( $sid, $ly_do ) {
    if ( $sid ) set_transient( 'sitetop_hoff_loi_' . $sid, $ly_do, SITETOP_HANDOFF_TTL );
}

function sitetop_ajax_task_handoff() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error('Missing session');
    if ( sitetop_is_scripted_client() ) {
        sitetop_ghi_loi_ban_giao( $sid, 'bị coi là bot (User-Agent: '
            . substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 80 ) . ')' );
        wp_send_json_error('Forbidden');
    }
    $rate = sitetop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) {
        sitetop_ghi_loi_ban_giao( $sid, 'chạm hạn mức shortlink_click (30 lượt/phút cho mỗi IP)' );
        wp_send_json_error('Rate limited');
    }

    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    $visit = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, ip_address FROM {$p}shortlink_visits
         WHERE session_id = %s AND step != 'verified' AND reward_paid = 0 LIMIT 1", $sid
    ));
    if ( ! $visit ) {
        sitetop_ghi_loi_ban_giao( $sid, 'không tìm thấy lượt của phiên này (đã xong hoặc đã trả thưởng)' );
        wp_send_json_error('Invalid session');
    }

    // Phải chứng minh là CHÍNH trình duyệt đã mở shortlink, không cho bàn giao hộ phiên
    // người khác. Nhận 1 trong 3 bằng chứng — chỉ đòi khớp IP là quá giòn: điện thoại
    // hay nhảy IPv4/IPv6 giữa 2 request cách nhau vài giây, user thật sẽ bị chặn oan.
    $ip = function_exists('sitetop_get_real_ip') ? sitetop_get_real_ip() : ( $_SERVER['REMOTE_ADDR'] ?? '' );
    $ok = ( $visit->ip_address === $ip );                                   // cùng IP
    if ( ! $ok && ! empty( $_COOKIE['sitetop_sid'] ) ) {                    // cookie first-party của trang nhiệm vụ
        $ok = ( sanitize_text_field( $_COOKIE['sitetop_sid'] ) === $sid );
    }
    if ( ! $ok ) {                                                          // PHP session set lúc mở shortlink
        if ( ! session_id() ) @session_start();
        $ok = ( ( $_SESSION['sitetop_session_id'] ?? '' ) === $sid );
    }
    if ( ! $ok ) {
        /* Ghi cả hai IP: điện thoại nhảy IPv4/IPv6 giữa hai request thì nhìn hai
           dòng này là thấy ngay, khỏi phải đoán. */
        sitetop_ghi_loi_ban_giao( $sid, 'không khớp cả 3 bằng chứng — IP lúc mở link: '
            . (string) $visit->ip_address . ' | IP lúc cấp dấu: ' . (string) $ip
            . ' | cookie: ' . ( empty( $_COOKIE['sitetop_sid'] ) ? 'không có' : 'có nhưng khác' ) );
        wp_send_json_error('IP mismatch');
    }

    $wpdb->update("{$p}shortlink_visits", array( 'unlock_active' => 1 ), array( 'id' => (int) $visit->id ));
    set_transient( 'sitetop_handoff_' . $sid, time(), SITETOP_HANDOFF_TTL );
    delete_transient( 'sitetop_hoff_loi_' . $sid );   // cấp được rồi thì lỗi cũ vô nghĩa
    wp_send_json_success();
}

// Track social click
add_action('wp_ajax_sitetop_track_social_click', 'sitetop_ajax_track_social_click');
add_action('wp_ajax_nopriv_sitetop_track_social_click', 'sitetop_ajax_track_social_click');
function sitetop_ajax_track_social_click() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    if ( sitetop_is_scripted_client() ) wp_send_json_error('Forbidden');
    $rate = sitetop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');
    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    // Bind to requester IP — prevents advancing another visitor's session step (parity with track_direct).
    $ip = function_exists('sitetop_get_real_ip') ? sitetop_get_real_ip() : ( $_SERVER['REMOTE_ADDR'] ?? '' );
    // target_visited_at chỉ ghi LẦN ĐẦU — cùng lý do với sitetop_ajax_track_direct_click().
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}shortlink_visits
         SET social_clicked = 1,
             step = 'target_visited',
             target_visited_at = COALESCE( target_visited_at, %s )
         WHERE session_id = %s AND ip_address = %s",
        sitetop_current_time(), $sid, $ip
    ) );
    wp_send_json_success();
}

// Verify shortlink code (wrapper for page-unlock verify form)
add_action('wp_ajax_sitetop_verify_shortlink_code', 'sitetop_ajax_verify_shortlink_code');
add_action('wp_ajax_nopriv_sitetop_verify_shortlink_code', 'sitetop_ajax_verify_shortlink_code');
function sitetop_ajax_verify_shortlink_code() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    $code = sanitize_text_field($_POST['code'] ?? '');
    if ( ! $sid || ! $code ) wp_send_json_error(array('message' => 'Thiếu thông tin'));

    $ip = sitetop_get_real_ip();
    $rate = sitetop_rate_limit_check('verify_code', $ip);
    if ( ! $rate['allowed'] ) wp_send_json_error(array('message' => 'Quá nhiều lần thử, vui lòng đợi.'));

    /* Captcha cổng TIẾP TỤC — tách công tắc riêng (unlock_captcha_enabled) khỏi captcha
       của widget (widget_captcha_enabled) để bật/tắt độc lập. Khi công tắc tắt hoặc chưa
       cắm site key/secret key thì sitetop_verify_turnstile() trả true → luồng cũ y nguyên.
       Chặn TRƯỚC sitetop_verify_and_pay() để bot không dò được mã. */
    if ( ! sitetop_verify_turnstile( sanitize_text_field($_POST['captcha_token'] ?? ''), $ip, 'unlock_captcha_enabled' ) ) {
        wp_send_json_error(array(
            'message'      => 'Xác minh chưa xong hoặc đã hết hạn. Vui lòng tích lại ô xác minh rồi bấm TIẾP TỤC.',
            'need_captcha' => true,
        ));
    }

    $result = sitetop_verify_and_pay($sid, $code);
    if ( is_wp_error($result) ) {
        wp_send_json_error(array('message' => $result->get_error_message(), 'data' => $result->get_error_data()));
    }
    wp_send_json_success($result);
}

// Check if code is ready (widget polling)
add_action('wp_ajax_sitetop_check_code_ready', 'sitetop_ajax_check_code_ready');
add_action('wp_ajax_nopriv_sitetop_check_code_ready', 'sitetop_ajax_check_code_ready');
function sitetop_ajax_check_code_ready() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    $rate = sitetop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');
    $ready = get_transient('sitetop_widget_code_ready_' . $sid);

    // Chỉ trả mã về trang unlock SAU KHI user đã bấm copy trên trang đích (widget báo về).
    // Không có cờ này thì vẫn phải tự gõ tay như cũ — tránh việc mở trang unlock là có mã sẵn.
    $code = '';
    if ( ! empty( $ready ) && get_transient( 'sitetop_code_copied_' . $sid ) ) {
        global $wpdb; $p = $wpdb->prefix . 'sitetop_';
        $visit = $wpdb->get_row( $wpdb->prepare(
            "SELECT verify_code, verified_at, ip_address FROM {$p}shortlink_visits WHERE session_id = %s", $sid ) );
        // Giữ đúng ràng buộc như sitetop_ajax_unlock_heartbeat: chỉ lộ mã cho ĐÚNG IP đã tạo phiên
        // và chỉ khi phiên chưa verify.
        if ( $visit && $visit->ip_address === sitetop_get_real_ip()
             && empty( $visit->verified_at ) && ! empty( $visit->verify_code ) ) {
            $code = $visit->verify_code;
        }
    }
    wp_send_json_success(array('code_ready' => ! empty($ready), 'code' => $code));
}

// Widget báo "user đã bấm copy mã" → mở cờ cho check_code_ready trả mã về trang unlock.
add_action('wp_ajax_sitetop_code_copied', 'sitetop_ajax_code_copied');
add_action('wp_ajax_nopriv_sitetop_code_copied', 'sitetop_ajax_code_copied');
function sitetop_ajax_code_copied() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    $rate = sitetop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');
    // Chỉ đặt cờ khi mã đã thực sự được cấp — không cho gọi khống để lấy mã sớm.
    if ( ! get_transient('sitetop_widget_code_ready_' . $sid) ) wp_send_json_error('Chưa có mã');
    set_transient('sitetop_code_copied_' . $sid, 1, 2 * HOUR_IN_SECONDS);
    wp_send_json_success();
}

/* ------------------------------------------------------------------
   NHỊP HIỆN DIỆN — "trang đích còn đang mở hay không"
   ------------------------------------------------------------------
   Nhịp tim cũ (unlock_heartbeat) CHỈ chạy sau khi đồng hồ về 0, nên trong
   suốt lúc đếm ngược máy chủ không biết user còn ngồi đó hay đã đóng tab.
   Thiếu tín hiệu này thì rời hẳn website rồi quay lại vẫn được tính giờ —
   giờ vẫn trôi trong lúc user đi vắng.

   Nhịp này CHỈ ghi một dấu thời gian, không cấp mã, không đổi trạng thái —
   cố ý tách khỏi unlock_heartbeat để không đụng vào luồng cấp mã đang chạy
   đúng (nhất là chốt bước 2 trong đó).
   ------------------------------------------------------------------ */
/* ------------------------------------------------------------------
   BÁO RỜI TRANG — chính xác tới từng giây
   ------------------------------------------------------------------
   Nhịp hiện diện 10 giây/lần chỉ đủ để biết "còn hay không", không đủ để
   bắt mốc 10 giây: khoảng cách giữa hai nhịp tự nó đã là 10 giây.
   Trình duyệt bắn pagehide/visibilitychange ĐÚNG lúc user đóng tab hoặc
   chuyển đi, nên ghi thẳng mốc đó rồi so khi quay lại.
   Gửi bằng sendBeacon nên vẫn tới nơi kể cả khi tab đang đóng.
   ------------------------------------------------------------------ */
add_action('wp_ajax_sitetop_widget_left', 'sitetop_ajax_widget_left');
add_action('wp_ajax_nopriv_sitetop_widget_left', 'sitetop_ajax_widget_left');
function sitetop_ajax_widget_left() {
    $sid = sanitize_text_field( $_POST['session_id'] ?? '' );
    if ( ! $sid || ! preg_match( '/^[A-Za-z0-9]{8,32}$/', $sid ) ) wp_send_json_error();
    set_transient( 'sitetop_left_' . $sid, time(), 2 * HOUR_IN_SECONDS );
    wp_send_json_success();
}

add_action('wp_ajax_sitetop_widget_ping', 'sitetop_ajax_widget_ping');
add_action('wp_ajax_nopriv_sitetop_widget_ping', 'sitetop_ajax_widget_ping');
function sitetop_ajax_widget_ping() {
    $sid = sanitize_text_field( $_POST['session_id'] ?? '' );
    if ( ! $sid || ! preg_match( '/^[A-Za-z0-9]{8,32}$/', $sid ) ) wp_send_json_error();
    if ( function_exists( 'sitetop_is_scripted_client' ) && sitetop_is_scripted_client() ) wp_send_json_error();
    set_transient( 'sitetop_seen_' . $sid, time(), 10 * MINUTE_IN_SECONDS );
    delete_transient( 'sitetop_left_' . $sid );   // đang ở đây thì mốc rời trang cũ vô nghĩa
    wp_send_json_success();
}

// Unlock heartbeat (activity monitor on unlock page)
add_action('wp_ajax_sitetop_unlock_heartbeat', 'sitetop_ajax_unlock_heartbeat');
add_action('wp_ajax_nopriv_sitetop_unlock_heartbeat', 'sitetop_ajax_unlock_heartbeat');
function sitetop_ajax_unlock_heartbeat() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();

    $rate = sitetop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');

    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    $ip = sitetop_get_real_ip();
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT v.step, v.created_at, v.verify_code, v.verified_at, v.ip_address,
                kc.onsite_time as camp_onsite, kc.traffic_type
         FROM {$p}shortlink_visits v
         LEFT JOIN {$p}keyword_campaigns kc ON v.campaign_id = kc.id
         WHERE v.session_id = %s", $sid));
    if ( ! $visit ) wp_send_json_error('Session not found');

    // Nhịp tim = user CÒN ĐANG MỞ trang nhiệm vụ → gia hạn chốt bàn giao.
    // Chốt này chỉ sống SITETOP_HANDOFF_TTL kể từ lúc mở trang. Ai đọc hướng dẫn lâu
    // (camp direct: đọc xong mới copy URL, dán sang tab khác) là chốt hết hạn, sang
    // trang đích bị chặn ngay dù đang ngồi ngay trên trang nhiệm vụ.
    set_transient( 'sitetop_handoff_' . $sid, time(), SITETOP_HANDOFF_TTL );

    $elapsed = strtotime(sitetop_current_time()) - strtotime($visit->created_at);
    $onsite = (int) ($visit->camp_onsite ?? 70);
    $is_nocode = ($visit->traffic_type ?? '1step') === 'nocode';
    $required = $is_nocode ? 0 : max($onsite - 5, 10);

    // Only expose verify_code to original visitor IP
    $is_owner = ( $visit->ip_address === $ip );
    $code_to_return = '';
    if ( $is_owner && empty( $visit->verified_at ) && ! empty( $visit->verify_code ) ) {
        $code_to_return = $visit->verify_code;
    }

    wp_send_json_success(array(
        'step' => $visit->step,
        'elapsed' => $elapsed,
        'onsite_time' => $onsite,
        'remaining' => max(0, $required - $elapsed),
        'ready' => $is_nocode || $elapsed >= $required,
        'has_code' => ! empty($visit->verify_code),
        'verify_code' => $code_to_return,
        'traffic_type' => $visit->traffic_type ?? '1step',
    ));
}

// Change keyword (get different campaign)
add_action('wp_ajax_sitetop_change_keyword', 'sitetop_ajax_change_keyword');
add_action('wp_ajax_nopriv_sitetop_change_keyword', 'sitetop_ajax_change_keyword');
function sitetop_ajax_change_keyword() {
    $sid = sanitize_text_field($_REQUEST['session_id'] ?? '');
    $exclude_id = absint($_REQUEST['exclude_id'] ?? 0);
    if ( ! $sid ) wp_send_json_error('Missing session');

    $rate = sitetop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');

    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    $ip = sitetop_get_real_ip();
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}shortlink_visits WHERE session_id=%s AND ip_address=%s", $sid, $ip));
    if ( ! $visit ) wp_send_json_error('Visit not found');

    $campaign = sitetop_get_random_active_campaign($ip, $exclude_id);

    if ( ! $campaign ) wp_send_json_error(array('message' => 'Không có chiến dịch khác phù hợp'));

    $wpdb->update("{$p}shortlink_visits", array(
        'campaign_id' => $campaign->id,
        'order_id' => $campaign->order_id ?? 0,
        'step' => 'started',
        'created_at' => sitetop_current_time(),
        'verify_code' => null,
        'code_shown_at' => null,
        'from_google' => 0,
        'url_matched' => 0,
    ), array('session_id' => $sid, 'ip_address' => $ip));

    // Clear old transients
    delete_transient('sitetop_widget_code_ready_' . $sid);
    delete_transient('sitetop_code_copied_' . $sid);
    delete_transient('sitetop_verify_code_' . $sid);
    delete_transient('sitetop_google_clicked_' . $sid);

    wp_send_json_success(array(
        'campaign_id' => $campaign->id,
        'keyword' => $campaign->keyword,
        'target_url' => $campaign->target_url,
        'target_title' => $campaign->target_title ?? '',
        'target_description' => $campaign->target_description ?? '',
        'traffic_type' => $campaign->traffic_type ?? '1step',
        'onsite_time' => $campaign->onsite_time ?? 70,
        'countdown_seconds' => $campaign->countdown_seconds ?? 30,
        'screenshot_desktop_url' => $campaign->screenshot_desktop_url ?? '',
        'screenshot_mobile_url' => $campaign->screenshot_mobile_url ?? '',
        'is_nocode' => ($campaign->traffic_type ?? '') === 'nocode',
    ));
}

/* Số giây còn lại trước khi IP này được bấm "Báo lỗi mã" lần nữa. 0 = bấm được ngay.
   Một nguồn duy nhất cho cả chốt chặn lúc gửi lẫn endpoint hỏi giờ của cái nút, để
   hai nơi không bao giờ nói hai con số khác nhau. */
function sitetop_baoloi_khoa_key( $ip = null ) {
    if ( $ip === null ) $ip = sitetop_get_real_ip();
    return 'sitetop_baoloi_' . md5( (string) $ip );
}
function sitetop_baoloi_con_lai( $ip = null ) {
    $moc = get_transient( sitetop_baoloi_khoa_key( $ip ) );
    if ( ! $moc ) return 0;
    return max( 0, SITETOP_REPORT_GAP - ( time() - (int) $moc ) );
}
function sitetop_baoloi_cau_doi( $giay ) {
    $giay = max( 1, (int) $giay );
    $phut = intdiv( $giay, 60 );
    $du   = $giay % 60;
    return sprintf(
        'Bạn vừa báo lỗi rồi. Vui lòng đợi %s nữa mới báo tiếp được.',
        $phut > 0 ? ( $phut . ' phút ' . $du . ' giây' ) : ( $du . ' giây' )
    );
}

/* Nút hỏi giờ TRƯỚC khi mở bảng, để user khỏi gõ xong mới bị từ chối.
   Chỉ đọc, không ghi gì — bấm bao nhiêu lần cũng không tự khoá mình. */
add_action('wp_ajax_sitetop_report_cooldown', 'sitetop_ajax_report_cooldown');
add_action('wp_ajax_nopriv_sitetop_report_cooldown', 'sitetop_ajax_report_cooldown');
function sitetop_ajax_report_cooldown() {
    $con = sitetop_baoloi_con_lai();
    wp_send_json_success( array(
        'remaining' => $con,
        'message'   => $con > 0 ? sitetop_baoloi_cau_doi( $con ) : '',
    ) );
}

/* KHOÁ 30 PHÚT KHI MỞ SHORTLINK BẰNG TRÌNH DUYỆT ẨN DANH.
   Dùng dấu riêng, KHÔNG ghi vào ip_reputation: bảng đó là hồ sơ gian lận thật
   (proxy, fake IP, 1.1.1.1) và vẫn khoá 24 giờ — trộn ẩn danh vào vừa làm bẩn dữ
   liệu, vừa vô tình nâng án 30 phút thành 24 giờ.

   KHOÁ THEO IP + THIẾT BỊ, KHÔNG PHẢI IP TRẦN.
   Mạng di động Việt Nam thiếu IPv4 nên dùng CGNAT: hàng trăm thuê bao chung một
   địa chỉ công cộng. Khoá IP trần là một người ẩn danh kéo theo cả đám cùng chết
   30 phút — người bị vạ chẳng làm gì sai và cũng không hiểu vì sao.

   Thêm User-Agent và Accept-Language vào khoá. Hai thứ này có sẵn trong MỌI
   request ở phía máy chủ, không cần JavaScript — nên vẫn kiểm được ngay lúc dựng
   trang, trước khi có dòng JS nào chạy. User-Agent của Chrome Android mang theo
   phiên bản Android, phiên bản trình duyệt và thường cả tên máy; hai người ngẫu
   nhiên sau cùng một IP trùng khít cả chuỗi đó là rất hiếm.
   IPv6 vốn đã chính xác sẵn (mỗi thuê bao một khối /64 riêng), thêm thiết bị vào
   cũng không hại gì. */
function sitetop_andanh_khoa_key( $ip = null, $ua = null, $lang = null ) {
    if ( $ip === null )   $ip   = sitetop_get_real_ip();
    if ( $ua === null )   $ua   = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
    if ( $lang === null ) $lang = (string) ( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '' );
    return 'sitetop_andanh_' . md5( $ip . '|' . $ua . '|' . $lang );
}
function sitetop_andanh_dang_khoa( $ip = null ) {
    return (bool) get_transient( sitetop_andanh_khoa_key( $ip ) );
}

add_action('wp_ajax_sitetop_bao_an_danh', 'sitetop_ajax_bao_an_danh');
add_action('wp_ajax_nopriv_sitetop_bao_an_danh', 'sitetop_ajax_bao_an_danh');
function sitetop_ajax_bao_an_danh() {
    /* PHẢI kèm phiên đang mở. Trước đây endpoint nhận request rỗng cũng khoá —
       nghĩa là bất kỳ ai cũng gọi được để tự khoá mình, và trên IP dùng chung thì
       khoá lây sang người khác. Bắt buộc có session_id của một lượt CÓ THẬT, chưa
       xong, mới nhận: kẻ phá muốn khoá phải tự đi mở shortlink thật đã. */
    $sid = sanitize_text_field( $_POST['session_id'] ?? '' );
    if ( ! $sid || ! preg_match( '/^[A-Za-z0-9]{8,64}$/', $sid ) ) {
        wp_send_json_error( 'Thiếu phiên' );
    }
    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    $co = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits
          WHERE session_id = %s AND reward_paid = 0
            AND created_at > DATE_SUB(%s, INTERVAL 2 HOUR)",
        $sid, sitetop_current_time()
    ) );
    if ( ! $co ) {
        wp_send_json_error( 'Phiên không hợp lệ' );
    }

    set_transient(
        sitetop_andanh_khoa_key(),
        time(),
        SITETOP_ANDANH_BLOCK_MINUTES * MINUTE_IN_SECONDS
    );
    wp_send_json_success();
}

/* GỠ KHOÁ SỚM KHI ĐÃ QUAY LẠI CỬA SỔ THƯỜNG.
   Khoá gồm IP + máy + ngôn ngữ, mà tắt ẩn danh không đổi thứ nào trong ba — nên
   người lỡ mở nhầm, làm đúng ngay, vẫn phải ngồi chờ đủ 30 phút. Endpoint này cho
   họ vào lại ngay khi bộ dò xác nhận không còn ẩn danh.

   Không nới lỏng gì cho người cố tình: khoá chỉ được gỡ khi trình duyệt KHÔNG còn
   ẩn danh, tức họ đã phải làm đúng điều hệ thống muốn. Mức độ tin cậy đúng bằng
   chiều ngược lại — cả hai đều do trình duyệt tự khai; ai muốn lách thì đã chặn
   luôn bộ dò từ đầu, chẳng cần tới đây.

   Khoá được tính từ CHÍNH request này (IP, User-Agent, ngôn ngữ của người gọi) nên
   không ai gỡ hộ được cho người khác — cùng lắm là tự gỡ cho mình. */
add_action('wp_ajax_sitetop_go_an_danh', 'sitetop_ajax_go_an_danh');
add_action('wp_ajax_nopriv_sitetop_go_an_danh', 'sitetop_ajax_go_an_danh');
function sitetop_ajax_go_an_danh() {
    if ( function_exists( 'sitetop_rate_limit_check' ) ) {
        $rate = sitetop_rate_limit_check( 'shortlink_click' );
        if ( empty( $rate['allowed'] ) ) wp_send_json_error( 'Thử lại sau' );
    }
    delete_transient( sitetop_andanh_khoa_key() );
    wp_send_json_success();
}

// Report shortlink error
add_action('wp_ajax_sitetop_report_shortlink_error', 'sitetop_ajax_report_error');
add_action('wp_ajax_nopriv_sitetop_report_shortlink_error', 'sitetop_ajax_report_error');
function sitetop_ajax_report_error() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    $error = sanitize_textarea_field($_POST['error_message'] ?? $_POST['message'] ?? '');
    $type = sanitize_text_field($_POST['error_type'] ?? 'general');
    if ( ! $sid ) wp_send_json_error();

    /* MỖI IP CHỈ BÁO LỖI 1 LẦN TRONG 5 PHÚT — áp toàn hệ thống.
       Khoá theo ĐÚNG địa chỉ IP, không kèm session, task hay từ khoá: đổi nhiệm vụ,
       đổi từ khoá, nhảy giữa Search Key và Direct, hay tải lại trang đều KHÔNG gỡ
       được khoá.

       Không dùng chung rổ 'report_issue' (5 lần/5 phút) vì rổ đó còn phục vụ chức
       năng thêm Nguồn file gốc — siết nó lại là chặn oan chỗ khác. Dùng dấu riêng
       để còn tính được số giây còn lại mà báo cho user. */
    $_con_bl = sitetop_baoloi_con_lai();
    if ( $_con_bl > 0 ) {
        wp_send_json_error( sitetop_baoloi_cau_doi( $_con_bl ) );
        return;
    }

    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    $table = "{$p}shortlink_reports";
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ( $exists ) {
        $wpdb->insert($table, array(
            'session_id' => $sid, 'error_type' => $type,
            'error_message' => $error, 'ip_address' => sitetop_get_real_ip(),
            'created_at' => sitetop_current_time(),
        ));
    }

    /* Ghi dấu SAU khi đã nhận báo cáo. Đặt trước thì một lần bị từ chối vì lý do
       khác cũng khoá mất 5 phút của user. */
    set_transient( sitetop_baoloi_khoa_key(), time(), SITETOP_REPORT_GAP );

    // Email admin
    sitetop_send_report_error_email( $sid, $type, $error );

    // Tự tạm dừng camp khi bị nhiều IP báo lỗi trong 1 giờ + báo Telegram admin.
    if ( function_exists( 'sitetop_report_autopause_on_report' ) ) {
        sitetop_report_autopause_on_report( $sid, sitetop_get_real_ip() );
    }

    wp_send_json_success();
}

// Mark visit expired
add_action('wp_ajax_sitetop_mark_visit_expired', 'sitetop_ajax_mark_visit_expired');
add_action('wp_ajax_nopriv_sitetop_mark_visit_expired', 'sitetop_ajax_mark_visit_expired');
function sitetop_ajax_mark_visit_expired() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();

    $rate = sitetop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');

    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    $ip = function_exists('sitetop_get_real_ip') ? sitetop_get_real_ip() : $_SERVER['REMOTE_ADDR'];
    $wpdb->query($wpdb->prepare(
        "UPDATE {$p}shortlink_visits SET step = 'expired' WHERE session_id = %s AND ip_address = %s AND step != 'verified'",
        $sid, $ip));
    wp_send_json_success();
}

// Widget start timer: reset created_at so onsite_time counts from click moment
add_action('wp_ajax_sitetop_widget_start_timer', 'sitetop_ajax_widget_start_timer');
add_action('wp_ajax_nopriv_sitetop_widget_start_timer', 'sitetop_ajax_widget_start_timer');
function sitetop_ajax_widget_start_timer() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();

    $rate = sitetop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');

    global $wpdb; $p = $wpdb->prefix . 'sitetop_';

    // Validate visit exists + IP matches original visitor
    $ip = function_exists('sitetop_get_real_ip') ? sitetop_get_real_ip() : $_SERVER['REMOTE_ADDR'];
    $visit = $wpdb->get_row( $wpdb->prepare(
        "SELECT v.*, kc.onsite_time FROM {$p}shortlink_visits v
         LEFT JOIN {$p}keyword_campaigns kc ON v.campaign_id = kc.id
         WHERE v.session_id = %s AND v.step != 'verified'", $sid
    ));
    if ( ! $visit ) wp_send_json_error('Invalid session');
    if ( $visit->ip_address !== $ip ) wp_send_json_error('IP mismatch');

    $is_step2 = ! empty( $_POST['step2'] );

    if ( $is_step2 && $visit->step === 'target_visited' ) {
        // Step2 return: credit ONLY the real wall-clock time the visitor actually spent on
        // the target site (server-recorded target_visited_at), capped at onsite_time. This
        // closes the countdown-bypass: a client can NO LONGER backdate created_at to satisfy
        // the full onsite instantly — real time must elapse between target_visited and this
        // call. Legit 2-step users (who spent >= onsite on target) still get full credit.
        $onsite = (int) ( $visit->onsite_time ?? 70 );
        $now_ts = strtotime( sitetop_current_time() );
        $visited_ts = ! empty( $visit->target_visited_at ) ? strtotime( $visit->target_visited_at ) : 0;
        $spent_on_target = $visited_ts ? max( 0, $now_ts - $visited_ts ) : 0;
        $credit = min( $onsite, $spent_on_target );
        $past_time = date( 'Y-m-d H:i:s', $now_ts - $credit );
        $wpdb->update("{$p}shortlink_visits", array(
            'created_at' => $past_time,
            'verify_code' => null,
            'code_shown_at' => null,
        ), array('session_id' => $sid, 'step' => 'target_visited'));
    }
    /* HAI VIỆC KHÁC NHAU, ĐỪNG GỘP:
       (a) dấu "user ĐÃ bấm nút bắt đầu" — quyết định lượt có được TỰ CHẠY TIẾP khi
           chuyển URL hay không;
       (b) mốc giờ created_at — quyết định đếm từ giây nào.

       Bản 03/09 dời cả hai ra sau captcha để giờ không bị đốt lúc xác minh. Nhưng
       kéo theo (a) là sai: chưa qua captcha thì không có dấu, chuyển URL giữa chừng
       mất quyền tự chạy, user phải bấm lại và xác minh lại từ đầu.
       Giờ (a) đặt ngay lúc bấm (mark=1, KHÔNG đụng created_at), (b) vẫn chỉ đặt sau
       khi xác minh xong. */
    set_transient( 'sitetop_timer_' . $sid, 1, 2 * HOUR_IN_SECONDS );

    if ( ! empty( $_POST['mark'] ) ) {
        wp_send_json_success( 'marked' );   // chỉ ghi dấu, chưa bấm đồng hồ
        return;
    }

    if ( ! $is_step2 ) {
        $wpdb->update("{$p}shortlink_visits", array(
            'created_at' => sitetop_current_time(),
            'verify_code' => null,
            'code_shown_at' => null,
        ), array('session_id' => $sid));
    }

    delete_transient('sitetop_widget_code_ready_' . $sid);
    delete_transient('sitetop_code_copied_' . $sid);
    delete_transient('sitetop_verify_code_' . $sid);
    delete_transient('sitetop_widget_code_' . $sid);
    wp_send_json_success();
}

/* ============================================================
   WIDGET VERIFY ACCESS
   Called by widget.js on target website.
   Matches visit by: IP + campaign target_url domain
   ============================================================ */
add_action('wp_ajax_sitetop_widget_verify_access', 'sitetop_ajax_widget_verify_access');
add_action('wp_ajax_nopriv_sitetop_widget_verify_access', 'sitetop_ajax_widget_verify_access');
function sitetop_ajax_widget_verify_access() {
    $rate = sitetop_rate_limit_check('widget_verify');
    if ( ! $rate['allowed'] ) { wp_send_json_error('Rate limited'); return; }

    // C2 hardening: a legit widget runs in a real browser. Reject obvious scripted clients
    // (curl/python/headless) that forge the Origin header to set url_matched/from_google.
    if ( sitetop_is_scripted_client() ) { wp_send_json_error('Forbidden'); return; }

    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $ip = sitetop_get_real_ip();

    // Validate Origin header (server-trusted, browser enforces for cross-origin POST)
    $http_origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( $_SERVER['HTTP_ORIGIN'] ) : '';
    $client_url = esc_url_raw( $_POST['current_url'] ?? '' );
    $client_referer = esc_url_raw( $_POST['referer'] ?? '' );

    $origin_host = $http_origin ? parse_url( $http_origin, PHP_URL_HOST ) : '';
    $client_host = parse_url( $client_url, PHP_URL_HOST );
    $origin_host = $origin_host ? preg_replace( '/^www\./', '', strtolower( $origin_host ) ) : '';
    $client_host = $client_host ? preg_replace( '/^www\./', '', strtolower( $client_host ) ) : '';

    $result = array(
        'session_valid' => false, 'url_valid' => false, 'session_id' => '',
        'countdown' => (int) sitetop_get_option( 'widget_default_countdown', 30 ),
        'hide_code_widget' => false,
    );

    /* Bốn nhánh dưới đây đều trả session_valid = false, nhưng vì LÝ DO KHÁC NHAU.
       Trước đây widget chỉ thấy "không khớp phiên" nên báo chung một câu "Truy cập sai
       URL, ra xem lại ảnh" — đổ oan cho user ở 3/4 trường hợp: họ dán ĐÚNG URL trong
       hướng dẫn mà vẫn bị bảo là sai. Gắn mã lý do để widget nói đúng việc phải làm. */

    // Origin must match client_url host (prevents curl forgery)
    if ( empty( $origin_host ) || empty( $client_host ) || $origin_host !== $client_host ) {
        $result['reason'] = 'origin';
        wp_send_json_success( $result ); return;
    }
    $current_domain = $client_host;

    // IPv6 prefix
    $ip_pattern = $ip;
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
        $parts = explode( ':', $ip );
        if ( count( $parts ) >= 4 ) $ip_pattern = $parts[0] . ':' . $parts[1] . ':' . $parts[2] . ':' . $parts[3] . ':%';
    }

    // Find recent visit matching IP — no campaign status filter.
    // Visit already exists and user should be able to complete it regardless of
    // campaign status changes. verify_and_pay() handles payment logic.
    /* Lấy VÀI lượt đang chờ gần nhất chứ không chỉ 1.
       Bản cũ LIMIT 1 = luôn lấy lượt mới nhất. Một IP có thể đang giữ nhiều lượt chưa
       xong (mở vài shortlink liên tiếp — chính là cách anh test). Nếu lượt mới nhất
       thuộc camp có URL đích KHÁC, nó chiếm chỗ và bị loại ở bước so URL → báo "sai
       URL" trong khi lượt đúng vẫn đang chờ ngay bên dưới. Camp keyword ít lộ vì user
       tới bằng kết quả Google; camp direct dán URL bằng tay nên dính thẳng.

       Vẫn duyệt MỚI NHẤT TRƯỚC: lượt mới nhất mà hợp URL thì được chọn y như trước,
       nên luồng đang chạy đúng không đổi hành vi. Không nới lỏng kiểm tra nào — mọi
       ứng viên vẫn phải qua chốt bàn giao và so URL ở dưới. */
    $candidates = $wpdb->get_results( $wpdb->prepare(
        "SELECT v.*, c.target_url, c.destination_urls, c.traffic_type, c.campaign_type, c.countdown_seconds, c.onsite_time, c.fixed_code, c.keyword, c.step2_image_url, c.step2_target_url
         FROM {$p}shortlink_visits v
         INNER JOIN {$p}keyword_campaigns c ON v.campaign_id = c.id
         WHERE v.ip_address LIKE %s
         AND v.reward_paid = 0 AND v.step != 'verified'
         AND v.created_at > DATE_SUB(%s, INTERVAL 2 HOUR)
         ORDER BY v.created_at DESC LIMIT 5",
        $ip_pattern, sitetop_current_time()
    ));

    /* Cookie sitetop_sid gửi kèm được vì widget gọi bằng withCredentials sang chính
       sitetop.net (SameSite=None). Đưa lượt khớp cookie VÀO danh sách ứng viên thay vì
       chỉ dùng khi danh sách rỗng: IP đổi giữa trang nhiệm vụ và trang đích (4G nhảy
       IPv4/IPv6) mà IP mới lại đang có lượt khác, thì lượt đúng bị chiếm chỗ y như cũ.
       Xếp CUỐI để thứ tự ưu tiên theo IP giữ nguyên — thuần bổ sung, không đổi hành vi
       của trường hợp đang chạy đúng. */
    $candidates = (array) $candidates;

    /* ƯU TIÊN PHIÊN MÀ WIDGET ĐANG LÀM (sửa 03/09/2026).
       Trước đây hàm này KHÔNG hề đọc session_id widget gửi lên — chọn lượt thuần
       theo IP, lấy lượt mới nhất khớp URL. Một IP đang giữ nhiều lượt dở (user test
       đi test lại, hoặc nhiều tab) là chọn nhầm lượt của lần trước: lượt đó đã quá
       giờ nên bị coi là "xong bước 1", server bật step2_return và đẩy user vào nhánh
       chờ 15 giây giữa lúc đang đếm dở.

       Widget biết chính xác nó đang làm phiên nào, nên đưa lượt đó lên ĐẦU danh sách.
       Không nới lỏng gì: lượt vẫn phải qua đúng mọi chốt bên dưới như mọi ứng viên
       khác, chỉ là thứ tự ưu tiên đúng hơn. */
    $post_sid = sanitize_text_field( $_POST['session_id'] ?? '' );
    if ( $post_sid !== '' && preg_match( '/^[A-Za-z0-9]{8,32}$/', $post_sid ) ) {
        $seen_sid = wp_list_pluck( $candidates, 'session_id' );
        $idx = array_search( $post_sid, (array) $seen_sid, true );
        if ( $idx !== false ) {
            $uu = $candidates[ $idx ];
            unset( $candidates[ $idx ] );
            array_unshift( $candidates, $uu );
            $candidates = array_values( $candidates );
        } else {
            $by_post = $wpdb->get_row( $wpdb->prepare(
                "SELECT v.*, c.target_url, c.destination_urls, c.traffic_type, c.campaign_type, c.countdown_seconds, c.onsite_time, c.fixed_code, c.keyword, c.step2_image
                 FROM {$p}shortlink_visits v
                 INNER JOIN {$p}keyword_campaigns c ON v.campaign_id = c.id
                 WHERE v.session_id = %s
                   AND v.reward_paid = 0 AND v.step != 'verified'
                   AND v.created_at > DATE_SUB(%s, INTERVAL 2 HOUR)
                 LIMIT 1",
                $post_sid, sitetop_current_time()
            ) );
            if ( $by_post ) array_unshift( $candidates, $by_post );
        }
    }

    if ( ! empty( $_COOKIE['sitetop_sid'] ) ) {
        $cookie_sid = sanitize_text_field( $_COOKIE['sitetop_sid'] );
        $seen = wp_list_pluck( $candidates, 'session_id' );
        if ( ! in_array( $cookie_sid, (array) $seen, true ) ) {
            $by_cookie = $wpdb->get_row( $wpdb->prepare(
                "SELECT v.*, c.target_url, c.destination_urls, c.traffic_type, c.campaign_type, c.countdown_seconds, c.onsite_time, c.fixed_code, c.keyword, c.step2_image_url, c.step2_target_url
                 FROM {$p}shortlink_visits v
                 INNER JOIN {$p}keyword_campaigns c ON v.campaign_id = c.id
                 WHERE v.session_id = %s
                 AND v.reward_paid = 0 AND v.step != 'verified'
                 AND v.created_at > DATE_SUB(%s, INTERVAL 2 HOUR)
                 LIMIT 1",
                $cookie_sid, sitetop_current_time()
            ));
            if ( $by_cookie ) $candidates[] = $by_cookie;
        }
    }

    $visit = null;
    foreach ( $candidates as $cand ) {
        if ( sitetop_campaign_allows_url( $cand, $client_url ) ) { $visit = $cand; break; }
    }

    /* BƯỚC 2 ĐANG DỞ — không phải "sai URL".
       Camp 2 bước BẮT BUỘC user rời trang đích sang một trang khác cùng site. Trang đó
       đương nhiên không nằm trong danh sách URL đích, nên so khớp chặt sẽ kêu "sai URL"
       ngay giữa lúc user đang làm đúng.

       Bình thường widget tự nhận ra bằng cờ trong localStorage nên không gọi tới đây;
       nhưng cờ đó phụ thuộc việc bắt được cú click nên thỉnh thoảng trượt — đúng cái
       "chập chờn" đang gặp. Server tự nhận ra thì hết phụ thuộc.

       KHÔNG nới lỏng bước 1: chỉ nhận khi lượt đó ĐÃ có url_matched = 1, tức từng đứng
       ĐÚNG URL đích qua một lần verify hợp lệ (cờ này chỉ trình duyệt thật vượt được
       kiểm tra Origin mới ghi được). Và chỉ chấp nhận trang CÙNG TÊN MIỀN với URL đích. */
    /* MỞ RỘNG 03/09/2026 — GIỮ NHIỆM VỤ KHI USER ĐI TRONG CÙNG SITE.
       Trước đây ngoại lệ này chỉ dành cho camp 2 bước. Camp 1 bước / direct /
       social mà user lỡ bấm một link nội bộ là rơi thẳng vào "sai URL", mất
       phiên, phải mò về đúng URL cũ mới chạy tiếp — dù họ đã đứng đủ 55/70 giây.
       Giờ áp cho MỌI loại camp.

       CỔNG VÀO KHÔNG NỚI MỘT LY: vẫn bắt buộc user đáp ĐÚNG URL đích đã khai
       trong camp, vì chỉ lần verify hợp lệ tại đúng URL đó mới ghi được cờ
       url_matched = 1 (cờ này chỉ trình duyệt thật vượt được kiểm tra Origin
       mới đặt được). Không có cờ đó thì không có ngoại lệ nào cả.
       Và trang đang đứng vẫn phải CÙNG TÊN MIỀN với URL đích — nhảy sang
       domain khác vẫn chặn y như cũ. */
    $onsite_continue = false;   // đang đi trong site, vẫn tính là làm nhiệm vụ
    $step2_continue  = false;   // RIÊNG camp 2 bước — điều khiển nhánh 15 giây của widget
    if ( ! $visit ) {
        $cur_host = sitetop_host_of( $client_url );
        foreach ( $candidates as $cand ) {
            if ( empty( $cand->url_matched ) ) continue;
            foreach ( sitetop_campaign_destinations( $cand ) as $d ) {
                if ( $cur_host !== '' && sitetop_host_of( $d ) === $cur_host ) {
                    $visit = $cand;
                    $onsite_continue = true;
                    /* Chỉ camp 2 bước mới bật cờ này. Bật cho camp 1 bước là đẩy
                       user sang nhánh "quay lại bước 2" (chờ 15 giây rồi lấy mã),
                       sai hẳn luồng.

                       VÀ PHẢI ĐÃ ĐỦ GIỜ BƯỚC 1 (sửa 03/09/2026). Thiếu điều kiện này
                       thì user camp 2 bước lỡ bấm link nội bộ GIỮA CHỪNG cũng bị đẩy
                       vào nhánh 15 giây — trong khi đồng hồ chưa chạy hết và ảnh yêu
                       cầu chưa hề hiện. Bước 2 chỉ bắt đầu SAU khi bước 1 xong. */
                    $_ons  = (int) ( $cand->onsite_time ?? 70 );
                    $_req  = max( $_ons - 5, 10 );
                    $_troi = strtotime( sitetop_current_time() ) - strtotime( $cand->created_at );
                    $step2_continue = ( ( $cand->traffic_type ?? '' ) === '2step' ) && ( $_troi >= $_req );
                    break 2;
                }
            }
        }
    }

    // Không lượt nào hợp URL → giữ lượt mới nhất để phần dưới báo lỗi đúng như cũ.
    if ( ! $visit && ! empty( $candidates ) ) $visit = $candidates[0];

    // Không tìm được lượt nào: IP đổi VÀ trình duyệt chặn cookie bên thứ ba (Chrome
    // đang siết dần) → không còn cách nào nhận ra người dùng. Không phải lỗi thao tác.
    if ( ! $visit ) { $result['reason'] = 'no_visit'; wp_send_json_success( $result ); return; }

    /* ── CHỐT BÀN GIAO ────────────────────────────────────────────────────────
       Tìm được visit mới chỉ chứng minh "IP này có mở shortlink trong 2 giờ",
       KHÔNG chứng minh lượt xem trang này đến từ nhiệm vụ. Bắt buộc phải có bàn
       giao (user bấm Copy URL đích trên trang nhiệm vụ) thì mới gắn phiên.
       Ai vào thẳng trang đích mà không qua nhiệm vụ sẽ rơi vào đây → widget báo
       "Vui lòng truy cập qua link nhiệm vụ để lấy mã!" và KHÔNG chạy đếm ngược.
       Gia hạn TTL mỗi lần verify để user điều hướng trong trang đích không bị đứt.
       Visit tạo TRƯỚC khi chốt này lên (mốc sitetop_handoff_gate_since) được
       miễn — không cắt ngang người đang làm dở lúc deploy, họ vẫn nhận thưởng. */
    $gate_since = (int) get_option( 'sitetop_handoff_gate_since', 0 );
    if ( $gate_since && strtotime( $visit->created_at ) > $gate_since ) {
        $granted = get_transient( 'sitetop_handoff_' . $visit->session_id );
        if ( ! $granted ) {
            // Vào thẳng trang đích mà không đi qua trang nhiệm vụ.
            $result['reason'] = 'no_handoff';
            /* CHỈ báo Telegram khi lượt còn MỚI. Truy vấn ứng viên quét ngược 2 GIỜ,
               còn dấu bàn giao chỉ sống 15 PHÚT — nên từ phút thứ 15 tới giờ thứ 2, bất
               kỳ ai từng mở link nhiệm vụ rồi bỏ dở mà sau đó vào lại web khách một cách
               bình thường đều rơi vào nhánh này. Chặn thì đúng (không được chạy đồng hồ
               cho lượt vào thẳng), nhưng báo động thì sai: chẳng có ai đang kẹt cả.
               Ngoài cửa sổ bàn giao thì im lặng; trong cửa sổ mới là bất thường thật —
               vừa mở link nhiệm vụ xong mà không có dấu bàn giao. */
            $_tuoi = strtotime( sitetop_current_time() ) - strtotime( $visit->created_at );
            if ( $_tuoi <= SITETOP_HANDOFF_TTL ) {
                sitetop_alert_task_blocked( 'no_handoff', $visit, $client_url );
            }
            wp_send_json_success( $result ); return;
        }

        /* CỬA SỔ BÀN GIAO KHÔNG ĐƯỢC TRƯỢT VÔ HẠN.
           Bản cũ ghi đè mốc bằng time() ở MỖI lần verify thành công, mà verify chạy ngay
           khi mở trang đích. Nên chỉ cần mở shortlink MỘT lần rồi bỏ dở, sau đó vào thẳng
           trang đích (không qua shortlink nữa) là cửa sổ tự đẩy thêm 15 phút mỗi lần —
           giữ mở vô thời hạn, bấm nút lúc nào đồng hồ cũng chạy.

           Giờ giữ NGUYÊN mốc được cấp và chặn cứng theo mốc đó. Vẫn set_transient lại để
           bản ghi không hết hạn giữa chừng khi user điều hướng trong trang đích, nhưng ghi
           lại ĐÚNG mốc cũ chứ không phải thời điểm hiện tại.
           Chỉ nhịp tim của TRANG NHIỆM VỤ mới được cấp mốc mới — đó mới là bằng chứng
           user đang thật sự đứng ở vạch xuất phát. */
        if ( time() - (int) $granted > SITETOP_HANDOFF_TTL ) {
            $result['reason'] = 'handoff_expired';
            sitetop_alert_task_blocked( 'handoff_expired', $visit, $client_url );
            wp_send_json_success( $result ); return;
        }
        set_transient( 'sitetop_handoff_' . $visit->session_id, (int) $granted, SITETOP_HANDOFF_TTL );
    }

    // Camp có thể có NHIỀU URL đích ở nhiều domain khác nhau. Hợp lệ khi URL hiện tại
    // TRÙNG một trong các URL đã thêm — so cả domain lẫn đường dẫn, nên vào trang khác
    // cùng domain vẫn báo lỗi như cũ.
    if ( ! $onsite_continue && ! sitetop_campaign_allows_url( $visit, $client_url ) ) {
        // ĐÚNG nghĩa "sai URL": có phiên, có bàn giao, nhưng đang đứng ở URL khác.
        $result['reason']      = 'wrong_url';
        $result['want_url']    = (string) ( $visit->target_url ?? '' );
        // Danh sách THẬT dùng để so khớp. target_url chỉ là URL đầu tiên của danh sách
        // này; nếu hai thứ lệch nhau thì lỗi nằm ở dữ liệu camp chứ không phải ở user.
        $result['want_list']   = sitetop_campaign_destinations( $visit );
        $result['camp_id']     = (int) ( $visit->campaign_id ?? 0 );
        $result['current_url'] = $client_url;
        sitetop_alert_task_blocked( 'wrong_url', $visit, $client_url );
        wp_send_json_success( $result ); return;
    }

    // Giữ 2 biến này cho phần dưới (trả về cho widget + ghi cờ url_matched).
    $url_path_matched = true;
    $target_path  = sitetop_normalize_dest_path( $visit, $current_domain );
    $current_path = rtrim( parse_url( $client_url, PHP_URL_PATH ) ?: '/', '/' );

    // Keyword campaign: check Google referrer from document.referrer (POST)
    // Dùng campaign_type (cột chuyên dụng) thay vì heuristic !empty(keyword) —
    // traffic_direct cũng có thể có field keyword được lưu, sẽ bị block sai.
    $campaign_type = $visit->campaign_type ?? 'keyword_search';
    $is_keyword = ( $campaign_type === 'keyword_search' );
    $is_nocode = ( $visit->traffic_type === 'nocode' );
    $google_required = ( $is_keyword && ! $is_nocode );
    $google_verified = true;
    $referer_from_google = false;
    // Khởi tạo ngoài khối if: dòng debug bên dưới đọc $referer_host kể cả khi
    // $google_required = false (camp traffic_direct) → tránh PHP warning.
    $referer_host = '';

    if ( $google_required ) {
        /* Lấy host từ chuỗi referer GỐC, không dùng $client_referer.
           $client_referer đi qua esc_url_raw(), mà hàm đó chỉ cho qua các giao thức
           trong wp_allowed_protocols() — không có 'android-app'. Tìm từ khoá ở thanh
           Chrome/Google trên Android cho referer dạng
               android-app://com.google.android.googlequicksearchbox
           nên esc_url_raw() trả về RỖNG → chốt tưởng không có referer và chặn user,
           dù họ vừa tìm đúng từ khoá trên Google xong. */
        $raw_ref = trim( (string) wp_unslash( $_POST['referer'] ?? '' ) );
        $referer_host = '';
        if ( preg_match( '#^[a-z][a-z0-9+.\-]*://([^/?\#]+)#i', $raw_ref, $_rm ) ) {
            $referer_host = strtolower( sanitize_text_field( $_rm[1] ) );
        }
        // CHỈ nhận Google (google.com / google.com.vn) và thanh tìm kiếm Chrome trên
        // Android — xem sitetop_is_google_referer().
        $referer_from_google = $referer_host ? sitetop_is_google_referer( $referer_host ) : false;
        $db_already_verified = ( (int) $visit->from_google === 1 );

        /* Chỉ hai đường vào được công nhận:
           (1) Referer đúng google.com — tìm từ khoá thẳng trên Google Chrome.
           (2) DB đã có from_google=1 — lượt này ĐÃ qua (1) ở lần verify trước. Cần giữ
               để user điều hướng trong trang đích và làm bước 2 không bị đứt giữa chừng;
               nó không mở thêm đường vào nào vì cờ đó chỉ (1) mới tạo được.

           BỎ nhánh "referer rỗng thì cho qua" (chủ site chốt 15/08/2026: ngoài google.com
           ra không nhận). Nhánh đó vốn để đỡ cho trình duyệt xoá referer — Safari iOS mặc
           định, Brave, chế độ riêng tư — nhưng đồng thời là một đường vào không cần qua
           Google. Siết lại đồng nghĩa nhóm trình duyệt đó không làm được camp từ khoá. */
        $google_verified = $referer_from_google || $db_already_verified;

        // (4) CHẶN F5: tải lại trang KHÔNG BAO GIỜ là một lượt đến mới từ Google.
        // Kịch bản bị lợi dụng: shortlink 1 và 2 cùng đích A.com. User làm xong nhiệm vụ 1,
        // đang đứng trên A.com, mở tiếp shortlink 2 rồi chỉ F5 lại A.com là lấy được mã —
        // không phải search Google lần nữa. Xét referer không bắt được vì trình duyệt GIỮ
        // NGUYÊN document.referrer qua lần tải lại, nên referer vẫn là Google.
        // Lượt nào CHƯA có from_google trong DB thì reload/back không được phép tạo cờ đó.
        // Lượt đã có rồi thì miễn — user điều hướng nội bộ hoặc F5 giữa chừng vẫn chạy tiếp.
        $nav_type = sanitize_text_field( $_POST['nav_type'] ?? '' );
        if ( ! $db_already_verified && in_array( $nav_type, array( 'reload', 'back_forward' ), true ) ) {
            $google_verified = false;
        }
    }

    /* RỜI HẲN WEBSITE RỒI QUAY LẠI → ĐẾM LẠI TỪ ĐẦU.
       Widget gửi nhịp hiện diện 10 giây một lần suốt lúc trang đích còn mở.
       Vắng quá ngưỡng nghĩa là tab đã đóng hoặc user đã sang site khác — không
       thể tính những giây user đi vắng vào thời gian xem trang, vì đó chính là
       thứ khách hàng bỏ tiền mua.

       Chuyển URL NỘI BỘ chỉ đứt vài giây lúc tải trang nên không chạm ngưỡng —
       luồng vừa mở ở bản trước giữ nguyên.

       KHÔNG đụng lượt đã lấy được mã (code_shown_at khác rỗng) hay đã trả
       thưởng: công đã xong rồi, thu lại là oan. */
    if ( empty( $visit->code_shown_at ) && empty( $visit->reward_paid ) ) {
        $seen  = get_transient( 'sitetop_seen_' . $visit->session_id );
        $_ons0 = (int) ( $visit->onsite_time ?? 70 );
        $_req0 = max( $_ons0 - 5, 10 );
        $_tr0  = strtotime( sitetop_current_time() ) - strtotime( $visit->created_at );

        /* HAI ĐƯỜNG NHẬN RA "ĐÃ RỜI HẲN WEBSITE":
           (1) Có nhịp nhưng đã cũ quá ngưỡng — đường chính.
           (2) KHÔNG có nhịp nào mà giờ đã trôi quá xa mốc cần — đường dự phòng.

           Cần (2) vì (1) phụ thuộc hoàn toàn vào nhịp tới được máy chủ. Nhịp hụt
           một lần là chốt câm: created_at giữ nguyên, user quay lại sau vài phút
           là elapsed đã vượt ngưỡng, đồng hồ về 0 tức thì — camp 2 bước bung thẳng
           ảnh bước 2, camp 1 bước cấp mã luôn, đều không phải ngồi xem giây nào.

           Nhịp CÒN MỚI thì KHÔNG reset dù elapsed lớn: đó là user đang ngồi đó mà
           đồng hồ tạm dừng vì ẩn tab hoặc bất động — phạt họ là oan. */
        /* BA ĐƯỜNG NHẬN RA "ĐÃ RỜI TRANG", theo thứ tự tin cậy giảm dần:
           (1) Trình duyệt tự báo lúc đóng tab / chuyển đi, kèm mốc chính xác.
               Vắng quá SITETOP_AWAY_GAP là tính rời — đây là đường chuẩn, bắt được
               cả trường hợp mới đi 10 giây. Chuyển URL nội bộ cũng bắn tín hiệu này
               nhưng trang mới tải trong 1-2 giây nên không chạm ngưỡng.
           (2) Có nhịp nhưng đã cũ quá SITETOP_PRESENCE_GAP.
           (3) Không có nhịp nào mà giờ đã trôi quá xa mốc cần — lưới cuối, phòng khi
               cả hai tín hiệu trên đều hụt (tab bị buộc tắt, mạng rớt). */
        $_left = get_transient( 'sitetop_left_' . $visit->session_id );
        $_vang = ( $_left && ( time() - (int) $_left ) > SITETOP_AWAY_GAP )
              || ( $seen && ( time() - (int) $seen ) > SITETOP_PRESENCE_GAP )
              || ( ! $seen && $_tr0 > $_req0 + SITETOP_PRESENCE_GAP );
        if ( $_vang ) {
            $lai = sitetop_current_time();
            $wpdb->update( "{$p}shortlink_visits",
                array( 'created_at' => $lai, 'verify_code' => null, 'code_shown_at' => null ),
                array( 'session_id' => $visit->session_id, 'reward_paid' => 0 )
            );
            $visit->created_at = $lai;
            delete_transient( 'sitetop_seen_' . $visit->session_id );
            /* Xoá luôn dấu "đã bấm chạy đồng hồ". Giữ lại là cờ resume_countdown vẫn
               bật, widget tự chạy tiếp và BỎ QUA captcha — trong khi rời hẳn website
               rồi quay lại phải bị coi như bắt đầu lại: bấm nút, xác minh Cloudflare,
               rồi mới đếm đủ gói thời gian của camp. */
            delete_transient( 'sitetop_timer_' . $visit->session_id );
            delete_transient( 'sitetop_left_'  . $visit->session_id );
        }
    }

    $elapsed = strtotime( sitetop_current_time() ) - strtotime( $visit->created_at );
    $onsite = (int) ( $visit->onsite_time ?? 70 );
    $required = $is_nocode ? 0 : max( $onsite - 5, 10 );

    // Enhancement 1: code_ready only when flags pass (prevent "Hoàn thành nhưng Chưa trả")
    $flags_ok = $url_path_matched && ( ! $google_required || $google_verified );
    $time_ok = $is_nocode || $elapsed >= $required;

    $result['session_valid'] = true;
    $result['url_valid'] = true;
    $result['session_id'] = $visit->session_id;
    $result['countdown'] = (int) ( $visit->countdown_seconds ?? 30 );
    $result['traffic_type'] = $visit->traffic_type ?? '1step';
    $result['onsite_time'] = $onsite;
    /* Số giây HIỆN CHO USER phải tính theo TRỌN thời lượng camp, KHÔNG theo $required.
       $required = onsite - 5 là ngưỡng chấp nhận của server, cố ý thấp hơn 5 giây để
       user làm thật luôn vượt qua khi widget đếm xong (xem chú thích ở
       shortlink-functions.php quanh bộ đếm 'too_fast'). Trả $required - $elapsed cho
       widget là xoá mất biên đó: đồng hồ về 0 đúng lúc server vừa đủ, chậm mạng một
       nhịp là user làm thật bị đếm vào sổ tua giờ rồi bị phạt lúc trả thưởng. */
    $result['remaining'] = max( 0, $onsite - $elapsed );
    $result['code_ready'] = $is_nocode || ( $time_ok && $flags_ok );
    /* TỰ CHẠY TIẾP SAU KHI CHUYỂN URL NỘI BỘ.
       Đồng hồ không tự chạy khi tải trang — user phải bấm nút, mà cú bấm đó gọi
       start_timer và ĐẶT LẠI created_at, tức mất sạch số giây đã đứng. Cờ này cho
       widget chạy tiếp mà KHÔNG bấm, nên không gọi start_timer, nên không reset.
       Chỉ bật cho lượt ĐANG DỞ: đã từng bấm chạy, chưa hết giờ, chưa có mã. */
    $result['resume_countdown'] = ( (bool) get_transient( 'sitetop_timer_' . $visit->session_id ) )
        && ! $is_nocode && $elapsed < $required && empty( $visit->code_shown_at );
    $result['hide_code_widget'] = $is_nocode && ! empty( $visit->fixed_code );
    /* Đang ở trang thứ hai của camp 2 bước. Widget phải chạy nhánh "quay lại từ bước 2"
       (đợi 15 giây rồi lấy mã) chứ KHÔNG đếm lại 70 giây từ đầu — đếm lại là user quay
       vòng vô tận: hết giờ lại hiện hướng dẫn bước 2, bấm link lại sang trang mới. */
    $result['step2_return'] = $step2_continue;
    $result['google_required'] = $google_required;
    $result['google_verified'] = $google_verified;
    $result['url_path_matched'] = $url_path_matched;
    // Ảnh bước 2 do admin cấu hình. target_url để trống → widget tự dùng link nội bộ
    // đầu tiên dò được, nên chỉ cần có ảnh là đủ điều kiện hiển thị.
    // Ảnh chết (bị xoá trên ImgBB, đổi tài khoản ImgBB...) thì KHÔNG gửi sang widget:
    // gửi đi user chỉ thấy ô "image not found", không biết bấm link nào, tắc nhiệm vụ.
    // Bỏ ảnh ra thì widget tự rơi về danh sách link nội bộ — user vẫn làm xong được.
    $step2_img     = ! empty( $visit->step2_image_url ) ? $visit->step2_image_url : '';
    $step2_img_ok  = $step2_img && function_exists( 'sitetop_image_url_alive' )
        ? sitetop_image_url_alive( $step2_img )
        : (bool) $step2_img;
    if ( $step2_img && ! $step2_img_ok ) {
        sitetop_alert_dead_step2_image( (int) ( $visit->campaign_id ?? 0 ), $step2_img );
    }
    $result['step2_image'] = $step2_img_ok
        ? array(
            'image_url'  => $step2_img,
            'target_url' => $visit->step2_target_url ?: '',
          )
        : null;
    // Debug info cho widget khi sai URL — giúp admin/user biết phải đi đâu
    $result['target_url'] = $visit->target_url;
    $result['target_path'] = $target_path;
    $result['current_path'] = $current_path;
    // Debug info cho Google check — giúp diagnose nếu user báo "Cần tìm Google" sai
    $result['referer_received'] = $client_referer ?: '(empty)';
    $result['referer_host'] = $referer_host ?: '(empty)';

    // Update visit flags server-side only
    $visit_updates = array();
    if ( $url_path_matched ) $visit_updates['url_matched'] = 1;
    // Set from_google=1 khi google check pass (kể cả referer empty trust path) —
    // để get_widget_code (gọi sau khi click "Lấy mã") không reject ở DB check.
    if ( $google_required && $google_verified ) $visit_updates['from_google'] = 1;
    if ( ! empty( $visit_updates ) ) {
        $wpdb->update( "{$p}shortlink_visits", $visit_updates, array( 'id' => $visit->id ) );
    }

    wp_send_json_success( $result );
}

/**
 * Báo Telegram khi phát hiện ảnh bước 2 của một campaign đã chết.
 *
 * Widget tự rơi về danh sách link nên user không bị tắc, nhưng admin vẫn phải biết
 * để tải lại ảnh — nếu không campaign đó cứ chạy sai lặng lẽ. Chặn 1 lần/ngày cho
 * mỗi URL để không spam nhóm khi campaign đang có nhiều lượt truy cập.
 *
 * @param int    $campaign_id
 * @param string $url
 */
/**
 * Báo Telegram khi một lượt ĐANG CHỜ bị chặn ở bước gắn phiên.
 *
 * KHÔNG gọi ở nhánh no_visit/origin: hai nhánh đó chạy cho MỌI khách vãng lai của
 * mọi web khách, báo hết thì ngập nhóm.
 *
 * Lưu ý: tìm được lượt của IP này KHÔNG có nghĩa là người đó đang làm nhiệm vụ dở —
 * truy vấn ứng viên quét ngược 2 giờ. Nhánh gọi phải tự lọc theo tuổi lượt trước khi
 * gọi vào đây, nếu không sẽ báo động cho cả khách vãng lai bình thường.
 *
 * Chặn 1 lần/10 phút cho mỗi cặp (lý do + campaign) để không spam khi camp đang chạy.
 *
 * @param string $reason
 * @param object $visit
 * @param string $client_url
 */
function sitetop_alert_task_blocked( $reason, $visit, $client_url ) {
    if ( ! function_exists( 'sitetop_telegram_notify_admin' ) ) return;
    $cid = (int) ( $visit->campaign_id ?? 0 );
    $key = 'st_blk_' . md5( $reason . '|' . $cid );
    if ( get_transient( $key ) ) return;
    set_transient( $key, 1, 10 * MINUTE_IN_SECONDS );

    $labels = array(
        'no_handoff'      => 'Vào thẳng trang đích, không đi qua link nhiệm vụ',
        'handoff_expired' => 'Quá hạn bàn giao — mở link nhiệm vụ đã lâu mới vào trang đích',
        'wrong_url'  => 'URL đang đứng không nằm trong danh sách URL đích',
    );
    // Gửi cả danh sách URL THẬT dùng để so khớp và dạng đã chuẩn hoá của hai bên —
    // nhìn hai dòng cuối là biết ngay lệch ở đâu, khỏi phải mở console trên máy user.
    $dests = sitetop_campaign_destinations( $visit );
    $_tuoi  = strtotime( sitetop_current_time() ) - strtotime( (string) ( $visit->created_at ?? '' ) );
    $_tuoi_chu = $_tuoi < 60
        ? ( $_tuoi . ' giây trước' )
        : ( intdiv( $_tuoi, 60 ) . ' phút ' . ( $_tuoi % 60 ) . ' giây trước' );
    $_loi_hoff = get_transient( 'sitetop_hoff_loi_' . ( $visit->session_id ?? '' ) );
    $rows  = array(
        'Lý do'        => $labels[ $reason ] ?? $reason,
        'Vì sao thiếu dấu' => $_loi_hoff ? $_loi_hoff : 'trang nhiệm vụ chưa hề gọi cấp dấu',
        'Mở link nhiệm vụ' => $_tuoi_chu,
        'IP'           => (string) ( $visit->ip_address ?? '' ),
        'Campaign ID'  => $cid ?: '(không rõ)',
        'Loại camp'    => (string) ( $visit->campaign_type ?? '' ) . ' / ' . (string) ( $visit->traffic_type ?? '' ),
        'URL đích'     => (string) ( $visit->target_url ?? '' ),
        'URL user vào' => $client_url,
    );
    if ( 'wrong_url' === $reason ) {
        $rows['Danh sách URL đích'] = implode( ' | ', $dests );
        $rows['So khớp — cần']      = implode( ' | ', array_map( 'sitetop_url_key', $dests ) );
        $rows['So khớp — đang có']  = sitetop_url_key( $client_url );
    }
    sitetop_telegram_notify_admin( '🚧 Nhiệm vụ bị chặn ở bước gắn phiên', $rows );
}

function sitetop_alert_dead_step2_image( $campaign_id, $url ) {
    if ( ! function_exists( 'sitetop_telegram_notify_admin' ) ) return;
    $key = 'st_s2img_alert_' . md5( $url );
    if ( get_transient( $key ) ) return;
    set_transient( $key, 1, DAY_IN_SECONDS );
    sitetop_telegram_notify_admin( '🖼 Ảnh bước 2 đã chết', array(
        'Campaign ID' => $campaign_id ?: '(không rõ)',
        'URL ảnh'     => $url,
        'Hậu quả'     => 'Nhiệm vụ tạm chuyển về danh sách link để user vẫn làm được',
        'Cần làm'     => 'Campaigns → sửa campaign → Ảnh bước 2 → Tải ảnh lại',
    ) );
}
