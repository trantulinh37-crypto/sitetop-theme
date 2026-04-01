<?php
/**
 * LinkNgon V2 - Admin AJAX Handlers
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Load tab (lazy load)
add_action('wp_ajax_linkngon_admin_load_tab', 'linkngon_ajax_admin_load_tab');
function linkngon_ajax_admin_load_tab() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $tab = sanitize_text_field($_POST['tab'] ?? '');
    $allowed = array('campaigns','orders','users','withdrawals','visits','customers','settings','links','deposits');
    if (!in_array($tab, $allowed)) wp_send_json_error('Invalid tab');
    $file = LINKNGON_DIR . '/includes/admin/tabs/tab-' . $tab . '.php';
    if (!file_exists($file)) wp_send_json_error('Tab not found');
    ob_start(); include $file; wp_send_json_success(array('html'=>ob_get_clean()));
}

// Admin stats
add_action('wp_ajax_linkngon_admin_stats', 'linkngon_ajax_admin_stats');
function linkngon_ajax_admin_stats() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . LINKNGON_PREFIX;
    $today = date('Y-m-d', strtotime(linkngon_current_time()));
    wp_send_json_success(array(
        'total_links'        => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}user_shortlinks"),
        'total_clicks'       => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}shortlink_visits WHERE step='verified'"),
        'today_clicks'       => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}shortlink_visits WHERE step='verified' AND DATE(created_at)=%s", $today)),
        'active_campaigns'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}keyword_campaigns WHERE status='active'"),
        'total_paid'         => (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE type='shortlink_reward'"),
        'today_paid'         => (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE type='shortlink_reward' AND DATE(created_at)=%s", $today)),
        'pending_withdrawals'=> (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}withdrawals WHERE status='pending'"),
        'total_publishers'   => (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$p}user_shortlinks WHERE user_id > 0"),
        'total_customers'    => (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$p}customer_balance"),
    ));
}

// Campaign CRUD
add_action('wp_ajax_linkngon_admin_create_campaign', 'linkngon_ajax_admin_create_campaign');
function linkngon_ajax_admin_create_campaign() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $result = linkngon_create_keyword_campaign($_POST);
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    wp_send_json_success(array('campaign_id'=>$result));
}

add_action('wp_ajax_linkngon_admin_update_campaign', 'linkngon_ajax_admin_update_campaign');
function linkngon_ajax_admin_update_campaign() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $id = absint($_POST['campaign_id']??0);
    if (!$id) wp_send_json_error('Missing ID');
    $result = linkngon_update_campaign($id, $_POST);
    if ($result === false) wp_send_json_error('Update failed');
    wp_send_json_success();
}

add_action('wp_ajax_linkngon_admin_get_campaigns', 'linkngon_ajax_admin_get_campaigns');
function linkngon_ajax_admin_get_campaigns() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $campaigns = linkngon_get_campaigns(array(
        'status'=>sanitize_text_field($_POST['status']??''),
        'search'=>sanitize_text_field($_POST['search']??''),
        'limit'=>absint($_POST['limit']??20), 'offset'=>absint($_POST['offset']??0),
    ));
    wp_send_json_success(array('campaigns'=>$campaigns));
}

// Withdrawal management
add_action('wp_ajax_linkngon_admin_process_withdrawal', 'linkngon_ajax_admin_process_withdrawal');
function linkngon_ajax_admin_process_withdrawal() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $result = linkngon_process_withdrawal(absint($_POST['withdrawal_id']??0), sanitize_text_field($_POST['new_status']??''), sanitize_text_field($_POST['admin_note']??''));
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    wp_send_json_success();
}

// Deposit management
add_action('wp_ajax_linkngon_admin_approve_deposit', 'linkngon_ajax_admin_approve_deposit');
function linkngon_ajax_admin_approve_deposit() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $result = linkngon_approve_deposit(absint($_POST['deposit_id']??0), sanitize_text_field($_POST['admin_note']??''));
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    wp_send_json_success();
}

// Ban/unban user
add_action('wp_ajax_linkngon_admin_ban_user', 'linkngon_ajax_admin_ban_user');
function linkngon_ajax_admin_ban_user() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $uid = absint($_POST['user_id']??0);
    $action = sanitize_text_field($_POST['ban_action']??'ban');
    if ($action === 'ban') linkngon_ban_user($uid);
    else linkngon_unban_user($uid);
    wp_send_json_success();
}

// User stats (for modal)
add_action('wp_ajax_linkngon_admin_user_stats', 'linkngon_ajax_admin_user_stats');
function linkngon_ajax_admin_user_stats() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . LINKNGON_PREFIX;
    $uid = absint($_POST['user_id']??0);
    if (!$uid) wp_send_json_error('Missing user_id');

    $user = get_userdata($uid);
    if (!$user) wp_send_json_error('User not found');

    $now = linkngon_current_time();
    $today = date('Y-m-d', strtotime($now));
    $month_start = date('Y-m-01', strtotime($now));

    // Balance from transactions (source of truth)
    $earned = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE user_id=%d AND type='shortlink_reward'", $uid));
    $withdrawn = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}withdrawals WHERE user_id=%d AND status IN ('completed','cancelled')", $uid));
    $pending_w = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}withdrawals WHERE user_id=%d AND status IN ('pending','approved')", $uid));
    $other_ded = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE user_id=%d AND type='withdraw' AND (reference_type IS NULL OR reference_type != 'withdrawal')", $uid));
    $balance = $earned - $withdrawn - $pending_w - $other_ded;
    if ($balance < 0) $balance = 0;

    // Total load (all visits)
    $total_load = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits WHERE user_id=%d", $uid));

    // Month views (verified this month)
    $month_views = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits WHERE user_id=%d AND step='verified' AND created_at >= %s", $uid, $month_start));
    $month_load = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits WHERE user_id=%d AND created_at >= %s", $uid, $month_start));
    $month_rate = $month_load > 0 ? round(($month_views / $month_load) * 100, 2) : 0;

    // IP change count
    $change_ip = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits WHERE user_id=%d AND ip_changed=1", $uid));

    // Max IP per day
    $max_ip = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(cnt) FROM (SELECT DATE(created_at) as d, COUNT(DISTINCT ip_address) as cnt FROM {$p}shortlink_visits WHERE user_id=%d GROUP BY DATE(created_at)) t", $uid));

    // IPs appearing > 3 times
    $ip_over_3 = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM (SELECT ip_address, COUNT(*) as cnt FROM {$p}shortlink_visits WHERE user_id=%d AND step='verified' GROUP BY ip_address HAVING cnt > 3) t", $uid));

    // Top IPs (> 3 occurrences)
    $top_ips = $wpdb->get_results($wpdb->prepare(
        "SELECT ip_address as ip, COUNT(*) as count FROM {$p}shortlink_visits WHERE user_id=%d AND step='verified' GROUP BY ip_address HAVING count > 3 ORDER BY count DESC LIMIT 10", $uid));

    // Monthly stats (last 6 months)
    $monthly = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE_FORMAT(created_at, '%%m/%%Y') as month,
                COUNT(*) as total_load,
                SUM(CASE WHEN step='verified' THEN 1 ELSE 0 END) as views,
                COALESCE(SUM(CASE WHEN step='verified' AND reward_paid=1 THEN reward_amount ELSE 0 END),0) as earned
         FROM {$p}shortlink_visits WHERE user_id=%d
         GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
         ORDER BY DATE_FORMAT(created_at, '%%Y-%%m') DESC LIMIT 6", $uid));

    $monthly_data = array();
    foreach ($monthly as $m) {
        $monthly_data[] = array(
            'month' => $m->month,
            'load' => (int)$m->total_load,
            'views' => (int)$m->views,
            'earned' => (float)$m->earned,
        );
    }

    wp_send_json_success(array(
        'balance' => $balance,
        'registered' => date('H:i d/m/Y', strtotime($user->user_registered)),
        'total_load' => $total_load,
        'month_views' => $month_views,
        'month_rate' => $month_rate,
        'change_ip' => $change_ip,
        'max_ip' => $max_ip ?: 0,
        'ip_over_3' => $ip_over_3,
        'top_ips' => $top_ips ?: array(),
        'monthly' => $monthly_data,
    ));
}

// Login as user (admin impersonation)
add_action('wp_ajax_linkngon_admin_login_as_user', 'linkngon_ajax_admin_login_as_user');
function linkngon_ajax_admin_login_as_user() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $uid = absint($_POST['user_id']??0);
    if (!$uid) wp_send_json_error('Missing user_id');
    $user = get_userdata($uid);
    if (!$user) wp_send_json_error('User not found');
    $admin_id = get_current_user_id();
    update_user_meta($uid, 'switch_from_admin', $admin_id);
    wp_set_auth_cookie($uid);
    wp_send_json_success(array('redirect' => home_url()));
}

// Delete user
add_action('wp_ajax_linkngon_admin_delete_user', 'linkngon_ajax_admin_delete_user');
function linkngon_ajax_admin_delete_user() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $uid = absint($_POST['user_id']??0);
    if (!$uid) wp_send_json_error('Missing user_id');
    $user = get_userdata($uid);
    if (!$user) wp_send_json_error('User not found');
    if (user_can($uid, 'manage_options')) wp_send_json_error('Không thể xóa admin');

    global $wpdb; $p = $wpdb->prefix . LINKNGON_PREFIX;
    // Clean up linkngon data
    $wpdb->delete("{$p}user_balance", array('user_id'=>$uid));
    $wpdb->delete("{$p}transactions", array('user_id'=>$uid));
    $wpdb->delete("{$p}withdrawals", array('user_id'=>$uid));
    $wpdb->delete("{$p}notifications", array('user_id'=>$uid));
    $wpdb->delete("{$p}daily_checkins", array('user_id'=>$uid));
    wp_delete_user($uid);
    wp_send_json_success();
}

// Run unit tests
add_action('wp_ajax_linkngon_admin_run_tests', 'linkngon_ajax_admin_run_tests');
function linkngon_ajax_admin_run_tests() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $test_file = LINKNGON_DIR . '/tests/unit/run.php';
    if (!file_exists($test_file)) wp_send_json_error('Tests not found');
    $output = '';
    if (function_exists('exec')) { exec(PHP_BINARY.' '.escapeshellarg($test_file).' 2>&1', $lines); $output=implode("\n",$lines); }
    elseif (function_exists('shell_exec')) { $output=shell_exec(PHP_BINARY.' '.escapeshellarg($test_file).' 2>&1'); }
    else { ob_start(); include $test_file; $output=ob_get_clean(); }
    wp_send_json_success(array('output'=>$output));
}

// Recreate DB
add_action('wp_ajax_linkngon_admin_recreate_db', 'linkngon_ajax_admin_recreate_db');
function linkngon_ajax_admin_recreate_db() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    linkngon_create_tables();
    wp_send_json_success();
}
