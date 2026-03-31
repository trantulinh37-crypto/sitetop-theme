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
