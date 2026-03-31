<?php
/**
 * LinkNgon V2 - Frontend AJAX Handlers
 * Updated: uses session_id instead of visit_id
 * Section: 40+ AJAX actions
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Shorten URL (public)
add_action('wp_ajax_linkngon_shorten_url', 'linkngon_ajax_shorten_url');
add_action('wp_ajax_nopriv_linkngon_shorten_url', 'linkngon_ajax_shorten_url');
function linkngon_ajax_shorten_url() {
    check_ajax_referer('linkngon_nonce', 'nonce');
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

// Get code (by session_id)
add_action('wp_ajax_linkngon_get_code', 'linkngon_ajax_get_code');
add_action('wp_ajax_nopriv_linkngon_get_code', 'linkngon_ajax_get_code');
function linkngon_ajax_get_code() {
    check_ajax_referer('linkngon_nonce', 'nonce');
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    if (!$sid) wp_send_json_error('Missing session');
    $rate = linkngon_rate_limit_check('get_code');
    if (!$rate['allowed']) wp_send_json_error('Rate limited');
    $result = linkngon_get_widget_code($sid);
    if (is_wp_error($result)) wp_send_json_error(array('message'=>$result->get_error_message(),'data'=>$result->get_error_data()));
    wp_send_json_success(array('code'=>$result));
}

// Verify (by session_id)
add_action('wp_ajax_linkngon_verify', 'linkngon_ajax_verify');
add_action('wp_ajax_nopriv_linkngon_verify', 'linkngon_ajax_verify');
function linkngon_ajax_verify() {
    check_ajax_referer('linkngon_nonce', 'nonce');
    $sid = sanitize_text_field($_POST['session_id'] ?? '');
    $code = sanitize_text_field($_POST['code'] ?? '');
    if (!$sid || !$code) wp_send_json_error('Thiếu thông tin');
    $rate = linkngon_rate_limit_check('verify_code');
    if (!$rate['allowed']) wp_send_json_error('Quá nhiều lần thử');
    $result = linkngon_verify_and_pay($sid, $code);
    if (is_wp_error($result)) wp_send_json_error(array('message'=>$result->get_error_message(),'data'=>$result->get_error_data()));
    wp_send_json_success($result);
}

// Heartbeat (by session_id)
add_action('wp_ajax_linkngon_heartbeat', 'linkngon_ajax_heartbeat');
add_action('wp_ajax_nopriv_linkngon_heartbeat', 'linkngon_ajax_heartbeat');
function linkngon_ajax_heartbeat() {
    check_ajax_referer('linkngon_nonce', 'nonce');
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

// Report behavior analytics
add_action('wp_ajax_linkngon_report_behavior', 'linkngon_ajax_report_behavior');
add_action('wp_ajax_nopriv_linkngon_report_behavior', 'linkngon_ajax_report_behavior');
function linkngon_ajax_report_behavior() {
    check_ajax_referer('linkngon_nonce', 'nonce');
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

// Update visit step (google_clicked, target_visited)
add_action('wp_ajax_linkngon_update_step', 'linkngon_ajax_update_step');
add_action('wp_ajax_nopriv_linkngon_update_step', 'linkngon_ajax_update_step');
function linkngon_ajax_update_step() {
    check_ajax_referer('linkngon_nonce', 'nonce');
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
        'bank_account'=>sanitize_text_field($_POST['bank_account']??''),
        'wallet_address'=>sanitize_text_field($_POST['wallet_address']??''),
    ));
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    wp_send_json_success(array('withdrawal_id'=>$result));
}

// Daily check-in
add_action('wp_ajax_linkngon_checkin', 'linkngon_ajax_checkin');
function linkngon_ajax_checkin() {
    check_ajax_referer('linkngon_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error('Chưa đăng nhập');
    $result = linkngon_daily_checkin(get_current_user_id());
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    wp_send_json_success($result);
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
