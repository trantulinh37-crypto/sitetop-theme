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
    $allowed = array('campaigns','orders','users','withdrawals','visits','customers','settings','links','deposits','announcements');
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

    // Handle screenshot uploads
    if (!function_exists('wp_handle_upload')) require_once ABSPATH . 'wp-admin/includes/file.php';
    $upload_overrides = array('test_form' => false);
    foreach (array('screenshot_desktop' => 'screenshot_desktop_url', 'screenshot_mobile' => 'screenshot_mobile_url') as $field => $col) {
        if (!empty($_FILES[$field]['name'])) {
            $uploaded = wp_handle_upload($_FILES[$field], $upload_overrides);
            if ($uploaded && !isset($uploaded['error'])) {
                $_POST[$col] = $uploaded['url'];
            }
        }
    }

    // Auto-calculate price_per_view and user_reward from settings
    if (isset($_POST['traffic_type'])) {
        global $wpdb; $p = $wpdb->prefix . LINKNGON_PREFIX;
        $camp = $wpdb->get_row($wpdb->prepare(
            "SELECT kc.*, co.task_type FROM {$p}keyword_campaigns kc LEFT JOIN {$p}customer_orders co ON co.id=kc.order_id WHERE kc.id=%d", $id));
        if ($camp) {
            $task_type = $camp->task_type ?? 'keyword_search';
            $tt = sanitize_text_field($_POST['traffic_type']);
            $os = intval($_POST['onsite_time'] ?? $camp->onsite_time ?? 70);
            $price_key = ($task_type === 'keyword_search') ? 'keyword_price_' : 'direct_price_';
            $reward_key = ($task_type === 'keyword_search') ? 'keyword_user_' : 'direct_user_';
            $onsite_extra = array(70=>0,80=>0,90=>100,100=>200,120=>250,150=>300);
            if (!isset($_POST['price_per_view'])) {
                $_POST['price_per_view'] = floatval(linkngon_get_option($price_key . $tt, 1200)) + ($onsite_extra[$os] ?? 0);
            }
            if (!isset($_POST['user_reward'])) {
                $_POST['user_reward'] = floatval(linkngon_get_option($reward_key . $tt, 800));
            }
        }
    }

    $result = linkngon_update_campaign($id, $_POST);
    if ($result === false) wp_send_json_error('Update failed');
    wp_send_json_success();
}

add_action('wp_ajax_linkngon_admin_get_campaigns', 'linkngon_ajax_admin_get_campaigns');

// Get single campaign detail (admin)
add_action('wp_ajax_linkngon_admin_get_campaign', 'linkngon_ajax_admin_get_campaign');
function linkngon_ajax_admin_get_campaign() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . LINKNGON_PREFIX;
    $id = absint($_POST['campaign_id'] ?? 0);
    if (!$id) wp_send_json_error('Missing ID');
    $c = $wpdb->get_row($wpdb->prepare(
        "SELECT kc.*, co.task_type, co.customer_username FROM {$p}keyword_campaigns kc
         LEFT JOIN {$p}customer_orders co ON co.id = kc.order_id WHERE kc.id=%d", $id
    ));
    if (!$c) wp_send_json_error('Not found');
    wp_send_json_success(array(
        'id'=>$c->id, 'title'=>$c->title, 'keyword'=>$c->keyword, 'target_url'=>$c->target_url,
        'task_type'=>$c->task_type??'keyword_search', 'traffic_type'=>$c->traffic_type,
        'onsite_time'=>$c->onsite_time, 'price_per_view'=>$c->price_per_view,
        'user_reward'=>$c->user_reward, 'daily_traffic'=>$c->daily_traffic, 'quantity'=>$c->quantity,
        'status'=>$c->status, 'customer_username'=>$c->customer_username,
        'screenshot_desktop_url'=>$c->screenshot_desktop_url, 'screenshot_mobile_url'=>$c->screenshot_mobile_url,
    ));
}

// Update campaign screenshot status
add_action('wp_ajax_linkngon_admin_update_screenshot_status', 'linkngon_ajax_admin_update_screenshot_status');
function linkngon_ajax_admin_update_screenshot_status() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . LINKNGON_PREFIX;
    $id = absint($_POST['campaign_id'] ?? 0);
    $status = sanitize_text_field($_POST['screenshot_status'] ?? '');
    if (!$id) wp_send_json_error('Missing ID');
    if (!in_array($status, array('attached','not_attached'))) wp_send_json_error('Invalid status');
    if ($status === 'attached') {
        // Mark as attached by setting a placeholder if no real screenshot exists
        $row = $wpdb->get_row($wpdb->prepare("SELECT screenshot_desktop_url, screenshot_mobile_url FROM {$p}keyword_campaigns WHERE id=%d", $id));
        if ($row && empty($row->screenshot_desktop_url) && empty($row->screenshot_mobile_url)) {
            $wpdb->update("{$p}keyword_campaigns", array('screenshot_desktop_url' => 'attached'), array('id' => $id));
        }
    } else {
        // Clear screenshots
        $wpdb->update("{$p}keyword_campaigns", array('screenshot_desktop_url' => '', 'screenshot_mobile_url' => ''), array('id' => $id));
    }
    wp_send_json_success();
}

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

// ─── Announcements CRUD ───
add_action('wp_ajax_linkngon_admin_get_announcements', 'linkngon_ajax_admin_get_announcements');
function linkngon_ajax_admin_get_announcements() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . LINKNGON_PREFIX;
    $rows = $wpdb->get_results("SELECT * FROM {$p}announcements ORDER BY is_pinned DESC, created_at DESC LIMIT 50");
    wp_send_json_success(array('announcements' => $rows));
}

add_action('wp_ajax_linkngon_admin_create_announcement', 'linkngon_ajax_admin_create_announcement');
function linkngon_ajax_admin_create_announcement() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . LINKNGON_PREFIX;
    $title   = sanitize_text_field($_POST['title'] ?? '');
    $message = wp_kses_post($_POST['message'] ?? '');
    $target  = in_array($_POST['target'] ?? '', array('all','user','customer')) ? $_POST['target'] : 'all';
    $type    = in_array($_POST['type'] ?? '', array('info','warning','success')) ? $_POST['type'] : 'info';
    $pinned  = absint($_POST['is_pinned'] ?? 0) ? 1 : 0;
    if (empty($title)) wp_send_json_error('Tiêu đề không được để trống');
    if (empty($message)) wp_send_json_error('Nội dung không được để trống');
    $wpdb->insert("{$p}announcements", array(
        'target' => $target, 'type' => $type, 'title' => $title,
        'message' => $message, 'is_pinned' => $pinned,
        'status' => 'active', 'created_at' => linkngon_current_time(),
    ));
    wp_send_json_success(array('id' => $wpdb->insert_id));
}

add_action('wp_ajax_linkngon_admin_update_announcement', 'linkngon_ajax_admin_update_announcement');
function linkngon_ajax_admin_update_announcement() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . LINKNGON_PREFIX;
    $id = absint($_POST['id'] ?? 0);
    if (!$id) wp_send_json_error('Missing ID');
    $data = array();
    if (isset($_POST['title']))     $data['title']     = sanitize_text_field($_POST['title']);
    if (isset($_POST['message']))   $data['message']   = wp_kses_post($_POST['message']);
    if (isset($_POST['target']))    $data['target']     = in_array($_POST['target'], array('all','user','customer')) ? $_POST['target'] : 'all';
    if (isset($_POST['type']))      $data['type']       = in_array($_POST['type'], array('info','warning','success')) ? $_POST['type'] : 'info';
    if (isset($_POST['is_pinned'])) $data['is_pinned']  = absint($_POST['is_pinned']) ? 1 : 0;
    if (isset($_POST['status']))    $data['status']     = in_array($_POST['status'], array('active','hidden')) ? $_POST['status'] : 'active';
    if (empty($data)) wp_send_json_error('Nothing to update');
    $wpdb->update("{$p}announcements", $data, array('id' => $id));
    wp_send_json_success();
}

add_action('wp_ajax_linkngon_admin_delete_announcement', 'linkngon_ajax_admin_delete_announcement');
function linkngon_ajax_admin_delete_announcement() {
    check_ajax_referer('linkngon_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . LINKNGON_PREFIX;
    $id = absint($_POST['id'] ?? 0);
    if (!$id) wp_send_json_error('Missing ID');
    $wpdb->delete("{$p}announcements", array('id' => $id));
    wp_send_json_success();
}

// Public: get active announcements for dashboard
add_action('wp_ajax_linkngon_get_announcements', 'linkngon_ajax_get_announcements');
add_action('wp_ajax_nopriv_linkngon_get_announcements', 'linkngon_ajax_get_announcements');
function linkngon_ajax_get_announcements() {
    check_ajax_referer('linkngon_nonce', 'nonce');
    global $wpdb; $p = $wpdb->prefix . LINKNGON_PREFIX;

    // Check if table exists
    $table = $p . 'announcements';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
        wp_send_json_success(array('announcements' => array()));
        return;
    }

    $target = sanitize_text_field($_POST['target'] ?? 'user');
    if (!in_array($target, array('user','customer'))) $target = 'user';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, type, title, message, is_pinned, created_at FROM {$p}announcements
         WHERE status='active' AND target IN ('all', %s)
         ORDER BY is_pinned DESC, created_at DESC LIMIT 10", $target
    ));
    wp_send_json_success(array('announcements' => $rows));
}
