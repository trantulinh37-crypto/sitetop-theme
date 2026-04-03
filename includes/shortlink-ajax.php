<?php
/**
 * LinkNgon V2 - Frontend AJAX Handlers
 * Updated: uses session_id instead of visit_id
 * Section: 40+ AJAX actions
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Shorten URL (logged-in users only)
add_action('wp_ajax_linkngon_shorten_url', 'linkngon_ajax_shorten_url');
function linkngon_ajax_shorten_url() {
    check_ajax_referer('linkngon_nonce', 'nonce');
    if ( ! is_user_logged_in() ) wp_send_json_error('Vui lòng đăng nhập để tạo link');
    $url = esc_url_raw($_POST['url'] ?? '');
    if ( empty($url) || !filter_var($url, FILTER_VALIDATE_URL) ) wp_send_json_error('URL không hợp lệ');
    $rate = linkngon_rate_limit_check('shorten_url');
    if ( !$rate['allowed'] ) wp_send_json_error('Quá nhiều yêu cầu');
    $user_id = get_current_user_id();
    $alias = sanitize_text_field($_POST['alias'] ?? '');
    $result = linkngon_create_user_shortlink($user_id, $url, $alias);
    if ( is_wp_error($result) ) wp_send_json_error($result->get_error_message());
    global $wpdb; $p = $wpdb->prefix . 'linkngon_';
    $sl = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}user_shortlinks WHERE id=%d", $result));
    wp_send_json_success(array('short_url'=>home_url('/'.($sl->alias ?: $sl->code)), 'code'=>$sl->code, 'alias'=>$sl->alias));
}

// Get code (by session_id) - public, no nonce (called by page-unlock + widget.js)
add_action('wp_ajax_linkngon_get_code', 'linkngon_ajax_get_code');
add_action('wp_ajax_nopriv_linkngon_get_code', 'linkngon_ajax_get_code');
function linkngon_ajax_get_code() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if (!$sid) wp_send_json_error('Missing session');
    $rate = linkngon_rate_limit_check('get_code');
    if (!$rate['allowed']) wp_send_json_error('Rate limited');
    $result = linkngon_get_widget_code($sid);
    if (is_wp_error($result)) wp_send_json_error(array('message'=>$result->get_error_message(),'data'=>$result->get_error_data()));
    wp_send_json_success(array('code'=>$result));
}

// Verify (by session_id) - public, no nonce
add_action('wp_ajax_linkngon_verify', 'linkngon_ajax_verify');
add_action('wp_ajax_nopriv_linkngon_verify', 'linkngon_ajax_verify');
function linkngon_ajax_verify() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    $code = sanitize_text_field($_POST['code'] ?? '');
    if (!$sid || !$code) wp_send_json_error('Thiếu thông tin');
    $rate = linkngon_rate_limit_check('verify_code');
    if (!$rate['allowed']) wp_send_json_error('Quá nhiều lần thử');
    $result = linkngon_verify_and_pay($sid, $code);
    if (is_wp_error($result)) wp_send_json_error(array('message'=>$result->get_error_message(),'data'=>$result->get_error_data()));
    wp_send_json_success($result);
}

// Heartbeat (by session_id) - public, no nonce
add_action('wp_ajax_linkngon_heartbeat', 'linkngon_ajax_heartbeat');
add_action('wp_ajax_nopriv_linkngon_heartbeat', 'linkngon_ajax_heartbeat');
function linkngon_ajax_heartbeat() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if (!$sid) wp_send_json_error('Missing');
    global $wpdb; $p = $wpdb->prefix . 'linkngon_';
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT v.*, kc.onsite_time as camp_onsite, kc.countdown_seconds, kc.traffic_type
         FROM {$p}shortlink_visits v LEFT JOIN {$p}keyword_campaigns kc ON v.campaign_id=kc.id
         WHERE v.session_id=%s", $sid));
    if (!$visit) wp_send_json_error('Not found');
    $elapsed = strtotime(linkngon_current_time()) - strtotime($visit->created_at);
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
add_action('wp_ajax_linkngon_report_behavior', 'linkngon_ajax_report_behavior');
add_action('wp_ajax_nopriv_linkngon_report_behavior', 'linkngon_ajax_report_behavior');
function linkngon_ajax_report_behavior() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if (!$sid) wp_send_json_error('Missing');
    global $wpdb; $p = $wpdb->prefix . 'linkngon_';
    $visit = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$p}shortlink_visits WHERE session_id=%s", $sid));
    $visit_id = $visit ? $visit->id : 0;

    // Update visit flags
    $updates = array();
    if (absint($_POST['adblock']??0)) $updates['adblock_detected'] = 1;
    if (absint($_POST['from_google']??0)) $updates['from_google'] = 1;
    if (absint($_POST['url_matched']??0)) $updates['url_matched'] = 1;
    if (absint($_POST['social_clicked']??0)) $updates['social_clicked'] = 1;
    if (!empty($updates) && $visit_id) {
        $wpdb->update("{$p}shortlink_visits", $updates, array('id'=>$visit_id));
    }

    // Save behavior analytics + fraud score (if function exists)
    if (function_exists('linkngon_save_behavior_analytics')) {
        linkngon_save_behavior_analytics($visit_id, $sid, $_POST);
    }
    wp_send_json_success();
}

// Update visit step (google_clicked, target_visited) - public, no nonce
add_action('wp_ajax_linkngon_update_step', 'linkngon_ajax_update_step');
add_action('wp_ajax_nopriv_linkngon_update_step', 'linkngon_ajax_update_step');
function linkngon_ajax_update_step() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    $step = sanitize_text_field($_POST['step'] ?? '');
    if (!$sid || !$step) wp_send_json_error('Missing');
    $valid_steps = array('google_clicked','target_visited');
    if (!in_array($step, $valid_steps)) wp_send_json_error('Invalid step');

    global $wpdb; $p = $wpdb->prefix . 'linkngon_';
    $data = array('step' => $step);
    if ($step === 'google_clicked') {
        $data['google_clicked_at'] = linkngon_current_time();
        set_transient('linkngon_google_clicked_'.$sid, 1, 1800);
    }
    if ($step === 'target_visited') $data['target_visited_at'] = linkngon_current_time();

    $wpdb->update("{$p}shortlink_visits", $data, array('session_id'=>$sid));
    wp_send_json_success();
}

// User withdraw
add_action('wp_ajax_linkngon_user_withdraw', 'linkngon_ajax_user_withdraw');
function linkngon_ajax_user_withdraw() {
    check_ajax_referer('linkngon_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error('Chưa đăng nhập');
    $result = linkngon_submit_withdrawal(get_current_user_id(), floatval($_POST['amount']??0),
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
add_action('wp_ajax_linkngon_user_stats', 'linkngon_ajax_user_stats');
function linkngon_ajax_user_stats() {
    check_ajax_referer('linkngon_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error('Chưa đăng nhập');
    global $wpdb; $p = $wpdb->prefix . 'linkngon_';
    $uid = get_current_user_id();
    $today = date('Y-m-d', strtotime(linkngon_current_time()));
    wp_send_json_success(array(
        'balance'=>linkngon_get_user_balance_amount($uid),
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
add_action('wp_ajax_linkngon_track_adblock', 'linkngon_ajax_track_adblock');
add_action('wp_ajax_nopriv_linkngon_track_adblock', 'linkngon_ajax_track_adblock');
function linkngon_ajax_track_adblock() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    global $wpdb; $p = $wpdb->prefix . 'linkngon_';
    $wpdb->update("{$p}shortlink_visits", array('adblock_detected' => 1), array('session_id' => $sid));
    wp_send_json_success();
}

// Track Google click
add_action('wp_ajax_linkngon_track_google_click', 'linkngon_ajax_track_google_click');
add_action('wp_ajax_nopriv_linkngon_track_google_click', 'linkngon_ajax_track_google_click');
function linkngon_ajax_track_google_click() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    global $wpdb; $p = $wpdb->prefix . 'linkngon_';
    $wpdb->update("{$p}shortlink_visits", array(
        'from_google' => 1,
        'step' => 'google_clicked',
        'google_clicked_at' => linkngon_current_time(),
    ), array('session_id' => $sid));
    set_transient('linkngon_google_clicked_' . $sid, 1, 1800);
    wp_send_json_success();
}

// Track direct click
add_action('wp_ajax_linkngon_track_direct_click', 'linkngon_ajax_track_direct_click');
add_action('wp_ajax_nopriv_linkngon_track_direct_click', 'linkngon_ajax_track_direct_click');
function linkngon_ajax_track_direct_click() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    global $wpdb; $p = $wpdb->prefix . 'linkngon_';
    $updates = array('step' => 'target_visited', 'target_visited_at' => linkngon_current_time());
    if ( absint($_POST['url_matched'] ?? 0) ) $updates['url_matched'] = 1;
    $wpdb->update("{$p}shortlink_visits", $updates, array('session_id' => $sid));
    wp_send_json_success();
}

// Track social click
add_action('wp_ajax_linkngon_track_social_click', 'linkngon_ajax_track_social_click');
add_action('wp_ajax_nopriv_linkngon_track_social_click', 'linkngon_ajax_track_social_click');
function linkngon_ajax_track_social_click() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    global $wpdb; $p = $wpdb->prefix . 'linkngon_';
    $updates = array('social_clicked' => 1, 'step' => 'target_visited', 'target_visited_at' => linkngon_current_time());
    if ( absint($_POST['url_matched'] ?? 0) ) $updates['url_matched'] = 1;
    $wpdb->update("{$p}shortlink_visits", $updates, array('session_id' => $sid));
    wp_send_json_success();
}

// Verify shortlink code (wrapper for page-unlock verify form)
add_action('wp_ajax_linkngon_verify_shortlink_code', 'linkngon_ajax_verify_shortlink_code');
add_action('wp_ajax_nopriv_linkngon_verify_shortlink_code', 'linkngon_ajax_verify_shortlink_code');
function linkngon_ajax_verify_shortlink_code() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    $code = sanitize_text_field($_POST['code'] ?? '');
    if ( ! $sid || ! $code ) wp_send_json_error(array('message' => 'Thiếu thông tin'));

    $ip = linkngon_get_real_ip();
    $rate = linkngon_rate_limit_check('verify_code', $ip);
    if ( ! $rate['allowed'] ) wp_send_json_error(array('message' => 'Quá nhiều lần thử, vui lòng đợi.'));

    $result = linkngon_verify_and_pay($sid, $code);
    if ( is_wp_error($result) ) {
        wp_send_json_error(array('message' => $result->get_error_message(), 'data' => $result->get_error_data()));
    }
    wp_send_json_success($result);
}

// Check if code is ready (widget polling)
add_action('wp_ajax_linkngon_check_code_ready', 'linkngon_ajax_check_code_ready');
add_action('wp_ajax_nopriv_linkngon_check_code_ready', 'linkngon_ajax_check_code_ready');
function linkngon_ajax_check_code_ready() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    $ready = get_transient('linkngon_widget_code_ready_' . $sid);
    wp_send_json_success(array('code_ready' => ! empty($ready)));
}

// Unlock heartbeat (activity monitor on unlock page)
add_action('wp_ajax_linkngon_unlock_heartbeat', 'linkngon_ajax_unlock_heartbeat');
add_action('wp_ajax_nopriv_linkngon_unlock_heartbeat', 'linkngon_ajax_unlock_heartbeat');
function linkngon_ajax_unlock_heartbeat() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    global $wpdb; $p = $wpdb->prefix . 'linkngon_';
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT v.step, v.created_at, v.verify_code, kc.onsite_time as camp_onsite, kc.traffic_type
         FROM {$p}shortlink_visits v
         LEFT JOIN {$p}keyword_campaigns kc ON v.campaign_id = kc.id
         WHERE v.session_id = %s", $sid));
    if ( ! $visit ) wp_send_json_error('Session not found');

    $elapsed = strtotime(linkngon_current_time()) - strtotime($visit->created_at);
    $onsite = (int) ($visit->camp_onsite ?? 70);
    $is_nocode = ($visit->traffic_type ?? '1step') === 'nocode';
    $required = $is_nocode ? 0 : max($onsite - 5, 10);

    wp_send_json_success(array(
        'step' => $visit->step,
        'elapsed' => $elapsed,
        'onsite_time' => $onsite,
        'remaining' => max(0, $required - $elapsed),
        'ready' => $is_nocode || $elapsed >= $required,
        'has_code' => ! empty($visit->verify_code),
        'traffic_type' => $visit->traffic_type ?? '1step',
    ));
}

// Change keyword (get different campaign)
add_action('wp_ajax_linkngon_change_keyword', 'linkngon_ajax_change_keyword');
add_action('wp_ajax_nopriv_linkngon_change_keyword', 'linkngon_ajax_change_keyword');
function linkngon_ajax_change_keyword() {
    $sid = sanitize_text_field($_REQUEST['session_id'] ?? '');
    $exclude_id = absint($_REQUEST['exclude_id'] ?? 0);
    if ( ! $sid ) wp_send_json_error('Missing session');

    global $wpdb; $p = $wpdb->prefix . 'linkngon_';
    $visit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}shortlink_visits WHERE session_id=%s", $sid));
    if ( ! $visit ) wp_send_json_error('Visit not found');

    $ip = linkngon_get_real_ip();
    $campaign = linkngon_get_random_active_campaign($ip);

    // Try to avoid same campaign
    if ( $campaign && $campaign->id == $exclude_id ) {
        $retry = linkngon_get_random_active_campaign($ip);
        if ( $retry && $retry->id != $exclude_id ) $campaign = $retry;
    }

    if ( ! $campaign ) wp_send_json_error('Không có chiến dịch phù hợp');

    // Update visit with new campaign
    $wpdb->update("{$p}shortlink_visits", array(
        'campaign_id' => $campaign->id,
        'order_id' => $campaign->order_id ?? 0,
        'step' => 'started',
        'created_at' => linkngon_current_time(),
        'verify_code' => null,
        'code_shown_at' => null,
        'from_google' => 0,
        'url_matched' => 0,
    ), array('session_id' => $sid));

    // Clear old transients
    delete_transient('linkngon_widget_code_ready_' . $sid);
    delete_transient('linkngon_verify_code_' . $sid);
    delete_transient('linkngon_google_clicked_' . $sid);

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
        'fixed_code' => $campaign->fixed_code ?? '',
    ));
}

// Report shortlink error
add_action('wp_ajax_linkngon_report_shortlink_error', 'linkngon_ajax_report_error');
add_action('wp_ajax_nopriv_linkngon_report_shortlink_error', 'linkngon_ajax_report_error');
function linkngon_ajax_report_error() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    $error = sanitize_textarea_field($_POST['error_message'] ?? $_POST['message'] ?? '');
    $type = sanitize_text_field($_POST['error_type'] ?? 'general');
    if ( ! $sid ) wp_send_json_error();

    global $wpdb; $p = $wpdb->prefix . 'linkngon_';
    $table = "{$p}shortlink_reports";
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ( $exists ) {
        $wpdb->insert($table, array(
            'session_id' => $sid, 'error_type' => $type,
            'error_message' => $error, 'ip_address' => linkngon_get_real_ip(),
            'created_at' => linkngon_current_time(),
        ));
    }
    wp_send_json_success();
}

// Mark visit expired
add_action('wp_ajax_linkngon_mark_visit_expired', 'linkngon_ajax_mark_visit_expired');
add_action('wp_ajax_nopriv_linkngon_mark_visit_expired', 'linkngon_ajax_mark_visit_expired');
function linkngon_ajax_mark_visit_expired() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    global $wpdb; $p = $wpdb->prefix . 'linkngon_';
    $wpdb->query($wpdb->prepare(
        "UPDATE {$p}shortlink_visits SET step = 'expired' WHERE session_id = %s AND step != 'verified'", $sid));
    wp_send_json_success();
}

// Widget start timer: reset created_at so onsite_time counts from click moment
add_action('wp_ajax_linkngon_widget_start_timer', 'linkngon_ajax_widget_start_timer');
add_action('wp_ajax_nopriv_linkngon_widget_start_timer', 'linkngon_ajax_widget_start_timer');
function linkngon_ajax_widget_start_timer() {
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if ( ! $sid ) wp_send_json_error();
    global $wpdb; $p = $wpdb->prefix . 'linkngon_';
    // Reset created_at + clear old verify_code (force new code generation after countdown)
    $wpdb->update("{$p}shortlink_visits", array(
        'created_at' => linkngon_current_time(),
        'verify_code' => null,
        'code_shown_at' => null,
    ), array('session_id' => $sid));
    delete_transient('linkngon_widget_code_ready_' . $sid);
    delete_transient('linkngon_verify_code_' . $sid);
    delete_transient('linkngon_widget_code_' . $sid);
    wp_send_json_success();
}

/* ============================================================
   WIDGET VERIFY ACCESS
   Called by widget.js on target website.
   Matches visit by: IP + campaign target_url domain
   ============================================================ */
add_action('wp_ajax_linkngon_widget_verify_access', 'linkngon_ajax_widget_verify_access');
add_action('wp_ajax_nopriv_linkngon_widget_verify_access', 'linkngon_ajax_widget_verify_access');
function linkngon_ajax_widget_verify_access() {
    $rate = linkngon_rate_limit_check('widget_verify');
    if ( ! $rate['allowed'] ) { wp_send_json_error('Rate limited'); return; }

    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';
    $ip = linkngon_get_real_ip();
    $current_url = esc_url_raw( $_POST['current_url'] ?? '' );

    $current_host = parse_url( $current_url, PHP_URL_HOST );
    $current_domain = $current_host ? preg_replace( '/^www\./', '', strtolower( $current_host ) ) : '';

    $result = array(
        'session_valid' => false, 'url_valid' => false, 'session_id' => '',
        'countdown' => (int) linkngon_get_option( 'widget_default_countdown', 30 ),
        'hide_code_widget' => false,
    );

    if ( empty( $current_domain ) ) { wp_send_json_success( $result ); return; }

    // IPv6 prefix
    $ip_pattern = $ip;
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
        $parts = explode( ':', $ip );
        if ( count( $parts ) >= 4 ) $ip_pattern = $parts[0] . ':' . $parts[1] . ':' . $parts[2] . ':' . $parts[3] . ':%';
    }

    // Find recent visit matching IP + active campaign
    $visit = $wpdb->get_row( $wpdb->prepare(
        "SELECT v.*, c.target_url, c.traffic_type, c.countdown_seconds, c.onsite_time, c.fixed_code
         FROM {$p}shortlink_visits v
         INNER JOIN {$p}keyword_campaigns c ON v.campaign_id = c.id
         WHERE v.ip_address LIKE %s AND c.status = 'active'
         AND v.reward_paid = 0 AND v.step != 'verified'
         AND v.created_at > DATE_SUB(%s, INTERVAL 2 HOUR)
         ORDER BY v.created_at DESC LIMIT 1",
        $ip_pattern, linkngon_current_time()
    ));

    if ( ! $visit ) { wp_send_json_success( $result ); return; }

    // Validate URL domain match
    $target_host = parse_url( $visit->target_url ?? '', PHP_URL_HOST );
    $target_domain = $target_host ? preg_replace( '/^www\./', '', strtolower( $target_host ) ) : '';
    if ( $current_domain !== $target_domain ) { wp_send_json_success( $result ); return; }

    $is_nocode = ( $visit->traffic_type === 'nocode' );
    $elapsed = strtotime( linkngon_current_time() ) - strtotime( $visit->created_at );
    $onsite = (int) ( $visit->onsite_time ?? 70 );
    $required = $is_nocode ? 0 : max( $onsite - 5, 10 );

    $result['session_valid'] = true;
    $result['url_valid'] = true;
    $result['session_id'] = $visit->session_id;
    $result['countdown'] = (int) ( $visit->countdown_seconds ?? 30 );
    $result['traffic_type'] = $visit->traffic_type ?? '1step';
    $result['onsite_time'] = $onsite;
    $result['remaining'] = max( 0, $required - $elapsed );
    $result['code_ready'] = $is_nocode || $elapsed >= $required;
    $result['hide_code_widget'] = $is_nocode && ! empty( $visit->fixed_code );

    // Mark url_matched
    $wpdb->update( "{$p}shortlink_visits", array( 'url_matched' => 1 ), array( 'id' => $visit->id ) );

    wp_send_json_success( $result );
}
