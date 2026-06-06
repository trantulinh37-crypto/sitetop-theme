<?php
/**
 * Traffictop.net V2 - Frontend AJAX Handlers
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
if ( ! function_exists( 'traffictop_is_scripted_client' ) ) {
    function traffictop_is_scripted_client() {
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
add_action('wp_ajax_traffictop_shorten_url', 'traffictop_ajax_shorten_url');
function traffictop_ajax_shorten_url() {
    check_ajax_referer('traffictop_nonce', 'nonce');
    if ( ! is_user_logged_in() ) wp_send_json_error('Vui lòng đăng nhập để tạo link');
    $url = esc_url_raw($_POST['url'] ?? '');
    if ( empty($url) || !filter_var($url, FILTER_VALIDATE_URL) ) wp_send_json_error('URL không hợp lệ');
    $rate = traffictop_rate_limit_check('shorten_url');
    if ( !$rate['allowed'] ) wp_send_json_error('Quá nhiều yêu cầu');
    $user_id = get_current_user_id();
    $alias = sanitize_text_field($_POST['alias'] ?? '');
    $result = traffictop_create_user_shortlink($user_id, $url, $alias, '', 'manual');
    if ( is_wp_error($result) ) wp_send_json_error($result->get_error_message());
    global $wpdb; $p = $wpdb->prefix . 'traffictop_';
    $sl = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}user_shortlinks WHERE id=%d", $result));
    wp_send_json_success(array('short_url'=>home_url('/'.($sl->alias ?: $sl->code)), 'code'=>$sl->code, 'alias'=>$sl->alias));
}

// Get code (by session_id) - public, no nonce (called by page-unlock + widget.js)
add_action('wp_ajax_traffictop_get_code', 'traffictop_ajax_get_code');
add_action('wp_ajax_nopriv_traffictop_get_code', 'traffictop_ajax_get_code');
function traffictop_ajax_get_code() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if (!$sid) wp_send_json_error('Missing session');
    $rate = traffictop_rate_limit_check('get_code');
    if (!$rate['allowed']) wp_send_json_error('Rate limited');
    $result = traffictop_get_widget_code($sid);
    if (is_wp_error($result)) wp_send_json_error(array('message'=>$result->get_error_message(),'data'=>$result->get_error_data()));
    wp_send_json_success(array('code'=>$result));
}

// Verify (by session_id) - public, no nonce
add_action('wp_ajax_traffictop_verify', 'traffictop_ajax_verify');
add_action('wp_ajax_nopriv_traffictop_verify', 'traffictop_ajax_verify');
function traffictop_ajax_verify() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    $code = sanitize_text_field($_POST['code'] ?? '');
    if (!$sid || !$code) wp_send_json_error('Thiếu thông tin');
    $rate = traffictop_rate_limit_check('verify_code');
    if (!$rate['allowed']) wp_send_json_error('Quá nhiều lần thử');
    $result = traffictop_verify_and_pay($sid, $code);
    if (is_wp_error($result)) wp_send_json_error(array('message'=>$result->get_error_message(),'data'=>$result->get_error_data()));
    wp_send_json_success($result);
}

// Heartbeat (by session_id) - public, no nonce
add_action('wp_ajax_traffictop_heartbeat', 'traffictop_ajax_heartbeat');
add_action('wp_ajax_nopriv_traffictop_heartbeat', 'traffictop_ajax_heartbeat');
function traffictop_ajax_heartbeat() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if (!$sid) wp_send_json_error('Missing');

    $rate = traffictop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');

    global $wpdb; $p = $wpdb->prefix . 'traffictop_';
    $ip = traffictop_get_real_ip();
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT v.*, kc.onsite_time as camp_onsite, kc.countdown_seconds, kc.traffic_type
         FROM {$p}shortlink_visits v LEFT JOIN {$p}keyword_campaigns kc ON v.campaign_id=kc.id
         WHERE v.session_id=%s AND v.ip_address=%s", $sid, $ip));
    if (!$visit) wp_send_json_error('Not found');
    $elapsed = strtotime(traffictop_current_time()) - strtotime($visit->created_at);
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
add_action('wp_ajax_traffictop_report_behavior', 'traffictop_ajax_report_behavior');
add_action('wp_ajax_nopriv_traffictop_report_behavior', 'traffictop_ajax_report_behavior');
function traffictop_ajax_report_behavior() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if (!$sid) wp_send_json_error('Missing');
    $rate = traffictop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');
    global $wpdb; $p = $wpdb->prefix . 'traffictop_';
    $visit = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$p}shortlink_visits WHERE session_id=%s", $sid));
    $visit_id = $visit ? $visit->id : 0;

    // Update visit flags — only adblock (client-detected, penalty flag)
    // from_google and url_matched are set SERVER-SIDE only (widget_verify_access)
    $updates = array();
    if (absint($_POST['adblock']??0)) $updates['adblock_detected'] = 1;
    if (!empty($updates) && $visit_id) {
        $wpdb->update("{$p}shortlink_visits", $updates, array('id'=>$visit_id));
    }

    // Save behavior analytics + fraud score (if function exists)
    if (function_exists('traffictop_save_behavior_analytics')) {
        // Support both formats: direct POST fields OR JSON in 'data' field
        $behavior_data = $_POST;
        if (!empty($_POST['data'])) {
            $decoded = json_decode(stripslashes($_POST['data']), true);
            if (is_array($decoded)) {
                $behavior_data = array_merge($behavior_data, $decoded);
            }
        }
        traffictop_save_behavior_analytics($visit_id, $sid, $behavior_data);
    }
    wp_send_json_success();
}

// Update visit step (google_clicked, target_visited) - public, no nonce
add_action('wp_ajax_traffictop_update_step', 'traffictop_ajax_update_step');
add_action('wp_ajax_nopriv_traffictop_update_step', 'traffictop_ajax_update_step');
function traffictop_ajax_update_step() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    $step = sanitize_text_field($_POST['step'] ?? '');
    if (!$sid || !$step) wp_send_json_error('Missing');
    $valid_steps = array('google_clicked','target_visited');
    if (!in_array($step, $valid_steps)) wp_send_json_error('Invalid step');
    if ( traffictop_is_scripted_client() ) wp_send_json_error('Forbidden');

    $rate = traffictop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');

    global $wpdb; $p = $wpdb->prefix . 'traffictop_';

    // Validate step progression: started→google_clicked→target_visited
    $allowed_from = array('google_clicked' => 'started', 'target_visited' => 'google_clicked');
    $ip = function_exists('traffictop_get_real_ip') ? traffictop_get_real_ip() : $_SERVER['REMOTE_ADDR'];
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT step FROM {$p}shortlink_visits WHERE session_id=%s AND ip_address=%s AND step != 'verified'", $sid, $ip));
    if ( ! $visit ) wp_send_json_error('Invalid');

    // Allow progression OR same step (idempotent)
    if ( $visit->step !== $allowed_from[$step] && $visit->step !== $step ) {
        wp_send_json_error('Invalid step progression');
    }

    $data = array('step' => $step);
    if ($step === 'google_clicked') {
        $data['google_clicked_at'] = traffictop_current_time();
        set_transient('traffictop_google_clicked_'.$sid, 1, 1800);
    }
    if ($step === 'target_visited') $data['target_visited_at'] = traffictop_current_time();

    $wpdb->update("{$p}shortlink_visits", $data, array('session_id'=>$sid, 'ip_address'=>$ip));
    wp_send_json_success();
}

// User withdraw
add_action('wp_ajax_traffictop_user_withdraw', 'traffictop_ajax_user_withdraw');
function traffictop_ajax_user_withdraw() {
    check_ajax_referer('traffictop_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error('Chưa đăng nhập');
    $result = traffictop_submit_withdrawal(get_current_user_id(), floatval($_POST['amount']??0),
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
add_action('wp_ajax_traffictop_user_stats', 'traffictop_ajax_user_stats');
function traffictop_ajax_user_stats() {
    check_ajax_referer('traffictop_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error('Chưa đăng nhập');
    global $wpdb; $p = $wpdb->prefix . 'traffictop_';
    $uid = get_current_user_id();
    $today = date('Y-m-d', strtotime(traffictop_current_time()));
    wp_send_json_success(array(
        'balance'=>traffictop_get_user_balance_amount($uid),
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
add_action('wp_ajax_traffictop_track_adblock', 'traffictop_ajax_track_adblock');
add_action('wp_ajax_nopriv_traffictop_track_adblock', 'traffictop_ajax_track_adblock');
function traffictop_ajax_track_adblock() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    $adblock = absint($_POST['adblock'] ?? 1);
    global $wpdb; $p = $wpdb->prefix . 'traffictop_';
    $wpdb->update("{$p}shortlink_visits", array('adblock_detected' => $adblock ? 1 : 0), array('session_id' => $sid));
    wp_send_json_success();
}

// Track adblock mode 2 (widget.js blocked entirely)
add_action('wp_ajax_traffictop_track_adblock_mode2', 'traffictop_ajax_track_adblock_mode2');
add_action('wp_ajax_nopriv_traffictop_track_adblock_mode2', 'traffictop_ajax_track_adblock_mode2');
function traffictop_ajax_track_adblock_mode2() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    $rate = traffictop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');
    $ip = traffictop_get_real_ip();
    global $wpdb; $p = $wpdb->prefix . 'traffictop_';
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
add_action('wp_ajax_traffictop_track_google_click', 'traffictop_ajax_track_google_click');
add_action('wp_ajax_nopriv_traffictop_track_google_click', 'traffictop_ajax_track_google_click');
function traffictop_ajax_track_google_click() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    if ( traffictop_is_scripted_client() ) wp_send_json_error('Forbidden');
    $rate = traffictop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');
    global $wpdb; $p = $wpdb->prefix . 'traffictop_';
    // Bind to the requester's own IP — prevents injecting from_google onto another
    // visitor's session by guessing/owning a session_id (anti-fraud flag protection).
    $ip = function_exists('traffictop_get_real_ip') ? traffictop_get_real_ip() : ( $_SERVER['REMOTE_ADDR'] ?? '' );
    $wpdb->update("{$p}shortlink_visits", array(
        'from_google' => 1,
        'step' => 'google_clicked',
        'google_clicked_at' => traffictop_current_time(),
    ), array('session_id' => $sid, 'ip_address' => $ip));
    set_transient('traffictop_google_clicked_' . $sid, 1, 1800);
    wp_send_json_success();
}

// Track direct click
add_action('wp_ajax_traffictop_track_direct_click', 'traffictop_ajax_track_direct_click');
add_action('wp_ajax_nopriv_traffictop_track_direct_click', 'traffictop_ajax_track_direct_click');
function traffictop_ajax_track_direct_click() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    if ( traffictop_is_scripted_client() ) wp_send_json_error('Forbidden');
    $rate = traffictop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');
    global $wpdb; $p = $wpdb->prefix . 'traffictop_';
    // url_matched is set server-side by widget_verify_access only.
    // Bind to the requester's own IP — prevents advancing another visitor's session step.
    $ip = function_exists('traffictop_get_real_ip') ? traffictop_get_real_ip() : ( $_SERVER['REMOTE_ADDR'] ?? '' );
    $wpdb->update("{$p}shortlink_visits", array(
        'step' => 'target_visited',
        'target_visited_at' => traffictop_current_time(),
    ), array('session_id' => $sid, 'ip_address' => $ip));
    wp_send_json_success();
}

// Track social click
add_action('wp_ajax_traffictop_track_social_click', 'traffictop_ajax_track_social_click');
add_action('wp_ajax_nopriv_traffictop_track_social_click', 'traffictop_ajax_track_social_click');
function traffictop_ajax_track_social_click() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    $rate = traffictop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');
    global $wpdb; $p = $wpdb->prefix . 'traffictop_';
    $wpdb->update("{$p}shortlink_visits", array(
        'social_clicked' => 1,
        'step' => 'target_visited',
        'target_visited_at' => traffictop_current_time(),
    ), array('session_id' => $sid));
    wp_send_json_success();
}

// Verify shortlink code (wrapper for page-unlock verify form)
add_action('wp_ajax_traffictop_verify_shortlink_code', 'traffictop_ajax_verify_shortlink_code');
add_action('wp_ajax_nopriv_traffictop_verify_shortlink_code', 'traffictop_ajax_verify_shortlink_code');
function traffictop_ajax_verify_shortlink_code() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    $code = sanitize_text_field($_POST['code'] ?? '');
    if ( ! $sid || ! $code ) wp_send_json_error(array('message' => 'Thiếu thông tin'));

    $ip = traffictop_get_real_ip();
    $rate = traffictop_rate_limit_check('verify_code', $ip);
    if ( ! $rate['allowed'] ) wp_send_json_error(array('message' => 'Quá nhiều lần thử, vui lòng đợi.'));

    $result = traffictop_verify_and_pay($sid, $code);
    if ( is_wp_error($result) ) {
        wp_send_json_error(array('message' => $result->get_error_message(), 'data' => $result->get_error_data()));
    }
    wp_send_json_success($result);
}

// Check if code is ready (widget polling)
add_action('wp_ajax_traffictop_check_code_ready', 'traffictop_ajax_check_code_ready');
add_action('wp_ajax_nopriv_traffictop_check_code_ready', 'traffictop_ajax_check_code_ready');
function traffictop_ajax_check_code_ready() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    $rate = traffictop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');
    $ready = get_transient('traffictop_widget_code_ready_' . $sid);
    wp_send_json_success(array('code_ready' => ! empty($ready)));
}

// Unlock heartbeat (activity monitor on unlock page)
add_action('wp_ajax_traffictop_unlock_heartbeat', 'traffictop_ajax_unlock_heartbeat');
add_action('wp_ajax_nopriv_traffictop_unlock_heartbeat', 'traffictop_ajax_unlock_heartbeat');
function traffictop_ajax_unlock_heartbeat() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();

    $rate = traffictop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');

    global $wpdb; $p = $wpdb->prefix . 'traffictop_';
    $ip = traffictop_get_real_ip();
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT v.step, v.created_at, v.verify_code, v.verified_at, v.ip_address,
                kc.onsite_time as camp_onsite, kc.traffic_type
         FROM {$p}shortlink_visits v
         LEFT JOIN {$p}keyword_campaigns kc ON v.campaign_id = kc.id
         WHERE v.session_id = %s", $sid));
    if ( ! $visit ) wp_send_json_error('Session not found');

    $elapsed = strtotime(traffictop_current_time()) - strtotime($visit->created_at);
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
add_action('wp_ajax_traffictop_change_keyword', 'traffictop_ajax_change_keyword');
add_action('wp_ajax_nopriv_traffictop_change_keyword', 'traffictop_ajax_change_keyword');
function traffictop_ajax_change_keyword() {
    $sid = sanitize_text_field($_REQUEST['session_id'] ?? '');
    $exclude_id = absint($_REQUEST['exclude_id'] ?? 0);
    if ( ! $sid ) wp_send_json_error('Missing session');

    $rate = traffictop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');

    global $wpdb; $p = $wpdb->prefix . 'traffictop_';
    $ip = traffictop_get_real_ip();
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}shortlink_visits WHERE session_id=%s AND ip_address=%s", $sid, $ip));
    if ( ! $visit ) wp_send_json_error('Visit not found');

    $campaign = traffictop_get_random_active_campaign($ip, $exclude_id);

    if ( ! $campaign ) wp_send_json_error(array('message' => 'Không có chiến dịch khác phù hợp'));

    $wpdb->update("{$p}shortlink_visits", array(
        'campaign_id' => $campaign->id,
        'order_id' => $campaign->order_id ?? 0,
        'step' => 'started',
        'created_at' => traffictop_current_time(),
        'verify_code' => null,
        'code_shown_at' => null,
        'from_google' => 0,
        'url_matched' => 0,
    ), array('session_id' => $sid, 'ip_address' => $ip));

    // Clear old transients
    delete_transient('traffictop_widget_code_ready_' . $sid);
    delete_transient('traffictop_verify_code_' . $sid);
    delete_transient('traffictop_google_clicked_' . $sid);

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

// Report shortlink error
add_action('wp_ajax_traffictop_report_shortlink_error', 'traffictop_ajax_report_error');
add_action('wp_ajax_nopriv_traffictop_report_shortlink_error', 'traffictop_ajax_report_error');
function traffictop_ajax_report_error() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    $error = sanitize_textarea_field($_POST['error_message'] ?? $_POST['message'] ?? '');
    $type = sanitize_text_field($_POST['error_type'] ?? 'general');
    if ( ! $sid ) wp_send_json_error();
    $rate = traffictop_rate_limit_check('report_issue');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');

    global $wpdb; $p = $wpdb->prefix . 'traffictop_';
    $table = "{$p}shortlink_reports";
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ( $exists ) {
        $wpdb->insert($table, array(
            'session_id' => $sid, 'error_type' => $type,
            'error_message' => $error, 'ip_address' => traffictop_get_real_ip(),
            'created_at' => traffictop_current_time(),
        ));
    }

    // Email admin
    traffictop_send_report_error_email( $sid, $type, $error );

    wp_send_json_success();
}

// Mark visit expired
add_action('wp_ajax_traffictop_mark_visit_expired', 'traffictop_ajax_mark_visit_expired');
add_action('wp_ajax_nopriv_traffictop_mark_visit_expired', 'traffictop_ajax_mark_visit_expired');
function traffictop_ajax_mark_visit_expired() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();

    $rate = traffictop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');

    global $wpdb; $p = $wpdb->prefix . 'traffictop_';
    $ip = function_exists('traffictop_get_real_ip') ? traffictop_get_real_ip() : $_SERVER['REMOTE_ADDR'];
    $wpdb->query($wpdb->prepare(
        "UPDATE {$p}shortlink_visits SET step = 'expired' WHERE session_id = %s AND ip_address = %s AND step != 'verified'",
        $sid, $ip));
    wp_send_json_success();
}

// Widget start timer: reset created_at so onsite_time counts from click moment
add_action('wp_ajax_traffictop_widget_start_timer', 'traffictop_ajax_widget_start_timer');
add_action('wp_ajax_nopriv_traffictop_widget_start_timer', 'traffictop_ajax_widget_start_timer');
function traffictop_ajax_widget_start_timer() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();

    $rate = traffictop_rate_limit_check('shortlink_click');
    if ( ! $rate['allowed'] ) wp_send_json_error('Rate limited');

    global $wpdb; $p = $wpdb->prefix . 'traffictop_';

    // Validate visit exists + IP matches original visitor
    $ip = function_exists('traffictop_get_real_ip') ? traffictop_get_real_ip() : $_SERVER['REMOTE_ADDR'];
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
        $now_ts = strtotime( traffictop_current_time() );
        $visited_ts = ! empty( $visit->target_visited_at ) ? strtotime( $visit->target_visited_at ) : 0;
        $spent_on_target = $visited_ts ? max( 0, $now_ts - $visited_ts ) : 0;
        $credit = min( $onsite, $spent_on_target );
        $past_time = date( 'Y-m-d H:i:s', $now_ts - $credit );
        $wpdb->update("{$p}shortlink_visits", array(
            'created_at' => $past_time,
            'verify_code' => null,
            'code_shown_at' => null,
        ), array('session_id' => $sid, 'step' => 'target_visited'));
    } else if ( ! $is_step2 ) {
        $wpdb->update("{$p}shortlink_visits", array(
            'created_at' => traffictop_current_time(),
            'verify_code' => null,
            'code_shown_at' => null,
        ), array('session_id' => $sid));
    }

    delete_transient('traffictop_widget_code_ready_' . $sid);
    delete_transient('traffictop_verify_code_' . $sid);
    delete_transient('traffictop_widget_code_' . $sid);
    wp_send_json_success();
}

/* ============================================================
   WIDGET VERIFY ACCESS
   Called by widget.js on target website.
   Matches visit by: IP + campaign target_url domain
   ============================================================ */
add_action('wp_ajax_traffictop_widget_verify_access', 'traffictop_ajax_widget_verify_access');
add_action('wp_ajax_nopriv_traffictop_widget_verify_access', 'traffictop_ajax_widget_verify_access');
function traffictop_ajax_widget_verify_access() {
    $rate = traffictop_rate_limit_check('widget_verify');
    if ( ! $rate['allowed'] ) { wp_send_json_error('Rate limited'); return; }

    // C2 hardening: a legit widget runs in a real browser. Reject obvious scripted clients
    // (curl/python/headless) that forge the Origin header to set url_matched/from_google.
    if ( traffictop_is_scripted_client() ) { wp_send_json_error('Forbidden'); return; }

    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';
    $ip = traffictop_get_real_ip();

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
        'countdown' => (int) traffictop_get_option( 'widget_default_countdown', 30 ),
        'hide_code_widget' => false,
    );

    // Origin must match client_url host (prevents curl forgery)
    if ( empty( $origin_host ) || empty( $client_host ) || $origin_host !== $client_host ) {
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
    $visit = $wpdb->get_row( $wpdb->prepare(
        "SELECT v.*, c.target_url, c.traffic_type, c.campaign_type, c.countdown_seconds, c.onsite_time, c.fixed_code, c.keyword
         FROM {$p}shortlink_visits v
         INNER JOIN {$p}keyword_campaigns c ON v.campaign_id = c.id
         WHERE v.ip_address LIKE %s
         AND v.reward_paid = 0 AND v.step != 'verified'
         AND v.created_at > DATE_SUB(%s, INTERVAL 2 HOUR)
         ORDER BY v.created_at DESC LIMIT 1",
        $ip_pattern, traffictop_current_time()
    ));

    // Fallback: match by cookie session_id (handles dual-stack IPv4/IPv6 mismatch)
    if ( ! $visit && ! empty( $_COOKIE['traffictop_sid'] ) ) {
        $cookie_sid = sanitize_text_field( $_COOKIE['traffictop_sid'] );
        $visit = $wpdb->get_row( $wpdb->prepare(
            "SELECT v.*, c.target_url, c.traffic_type, c.campaign_type, c.countdown_seconds, c.onsite_time, c.fixed_code, c.keyword
             FROM {$p}shortlink_visits v
             INNER JOIN {$p}keyword_campaigns c ON v.campaign_id = c.id
             WHERE v.session_id = %s
             AND v.reward_paid = 0 AND v.step != 'verified'
             AND v.created_at > DATE_SUB(%s, INTERVAL 2 HOUR)
             LIMIT 1",
            $cookie_sid, traffictop_current_time()
        ));
    }

    if ( ! $visit ) { wp_send_json_success( $result ); return; }

    // Validate URL domain match
    $target_host = parse_url( $visit->target_url ?? '', PHP_URL_HOST );
    $target_domain = $target_host ? preg_replace( '/^www\./', '', strtolower( $target_host ) ) : '';
    if ( $current_domain !== $target_domain ) { wp_send_json_success( $result ); return; }

    // URL path match (stricter than domain-only)
    $target_path = rtrim( parse_url( $visit->target_url ?? '', PHP_URL_PATH ) ?: '/', '/' );
    $current_path = rtrim( parse_url( $client_url, PHP_URL_PATH ) ?: '/', '/' );
    $url_path_matched = ( strtolower( $current_path ) === strtolower( $target_path ) );

    // Keyword campaign: check Google referrer from document.referrer (POST)
    // Dùng campaign_type (cột chuyên dụng) thay vì heuristic !empty(keyword) —
    // traffic_direct cũng có thể có field keyword được lưu, sẽ bị block sai.
    $campaign_type = $visit->campaign_type ?? 'keyword_search';
    $is_keyword = ( $campaign_type === 'keyword_search' );
    $is_nocode = ( $visit->traffic_type === 'nocode' );
    $google_required = ( $is_keyword && ! $is_nocode );
    $google_verified = true;
    $referer_from_google = false;

    if ( $google_required ) {
        $referer_host = $client_referer ? parse_url( $client_referer, PHP_URL_HOST ) : '';
        $referer_from_google = $referer_host ? (bool) preg_match( '/(^|\.)google\./i', $referer_host ) : false;
        $referer_empty = empty( $referer_host );
        $db_already_verified = ( (int) $visit->from_google === 1 );

        // Verify pass nếu:
        // (1) Referer là Google — real users với normal browser (Chrome desktop)
        // (2) Referer EMPTY — privacy browser strip (iOS Safari default, Brave,
        //     Lockdown Mode). Trust user — anti-fraud rules catch bots.
        // (3) DB đã có from_google=1 — user verified ở call TRƯỚC (case user
        //     navigate internal trên target site sau khi đã đến từ Google →
        //     subsequent verify call có referer=target_site, không phải Google).
        $google_verified = $referer_from_google || $referer_empty || $db_already_verified;
    }

    $elapsed = strtotime( traffictop_current_time() ) - strtotime( $visit->created_at );
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
    $result['remaining'] = max( 0, $required - $elapsed );
    $result['code_ready'] = $is_nocode || ( $time_ok && $flags_ok );
    $result['hide_code_widget'] = $is_nocode && ! empty( $visit->fixed_code );
    $result['google_required'] = $google_required;
    $result['google_verified'] = $google_verified;
    $result['url_path_matched'] = $url_path_matched;
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
