<?php
/**
 * Traffictop.net V2 - Admin AJAX Handlers
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Load tab (lazy load)
add_action('wp_ajax_traffictop_admin_load_tab', 'traffictop_ajax_admin_load_tab');
function traffictop_ajax_admin_load_tab() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $tab = sanitize_text_field($_POST['tab'] ?? '');
    $allowed = array('campaigns','orders','users','withdrawals','visits','customers','settings','links','deposits','announcements');
    if (!in_array($tab, $allowed)) wp_send_json_error('Invalid tab');
    $file = TRAFFICTOP_DIR . '/includes/admin/tabs/tab-' . $tab . '.php';
    if (!file_exists($file)) wp_send_json_error('Tab not found');
    ob_start(); include $file; wp_send_json_success(array('html'=>ob_get_clean()));
}

// Admin stats
add_action('wp_ajax_traffictop_admin_stats', 'traffictop_ajax_admin_stats');
function traffictop_ajax_admin_stats() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $today = date('Y-m-d', strtotime(traffictop_current_time()));
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

// Admin chart data (monthly daily breakdown)
add_action('wp_ajax_traffictop_admin_chart_data', 'traffictop_ajax_admin_chart_data');
function traffictop_ajax_admin_chart_data() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . TRAFFICTOP_PREFIX;

    $month = sanitize_text_field($_POST['month'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m', strtotime(traffictop_current_time()));
    }
    $year = (int) substr($month, 0, 4);
    $mon  = (int) substr($month, 5, 2);
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $mon, $year);

    // Range-based WHERE (uses created_at index, no DATE() wrapper)
    $range_start = "$month-01 00:00:00";
    $next_mon = $mon < 12 ? $year . '-' . sprintf('%02d', $mon + 1) : ($year + 1) . '-01';
    $range_end = "$next_mon-01 00:00:00";

    // Single query for daily chart + monthly totals (JOIN for price_per_view)
    $daily = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(v.created_at) as d,
                COUNT(*) as total_visits,
                SUM(v.step='verified') as verified,
                COALESCE(SUM(CASE WHEN v.customer_paid=1 THEN COALESCE(kc.price_per_view, v.reward_amount) ELSE 0 END),0) as customer_paid_amount,
                COALESCE(SUM(CASE WHEN v.reward_paid=1 THEN v.reward_amount ELSE 0 END),0) as user_earned
         FROM {$p}shortlink_visits v
         LEFT JOIN {$p}keyword_campaigns kc ON kc.id = v.campaign_id
         WHERE v.created_at >= %s AND v.created_at < %s
         GROUP BY DATE(v.created_at)
         ORDER BY d ASC",
        $range_start, $range_end
    ));

    // Build daily data + accumulate monthly totals from same result
    $data = array();
    $map = array();
    $sum_visits = 0; $sum_verified = 0; $sum_user_earned = 0;
    foreach ($daily as $row) {
        $map[$row->d] = $row;
        $sum_visits += (int)$row->total_visits;
        $sum_verified += (int)$row->verified;
        $sum_user_earned += (float)$row->user_earned;
    }
    for ($i = 1; $i <= $days_in_month; $i++) {
        $d = sprintf('%s-%02d', $month, $i);
        $row = $map[$d] ?? null;
        $data[] = array(
            'date'          => $d,
            'total_visits'  => $row ? (int)$row->total_visits : 0,
            'verified'      => $row ? (int)$row->verified : 0,
            'customer_paid' => $row ? (float)$row->customer_paid_amount : 0,
            'user_earned'   => $row ? (float)$row->user_earned : 0,
        );
    }

    // 3 lightweight queries for summary (range-based, uses indexes)
    $customer_paid_total = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(ABS(SUM(amount)),0) FROM {$p}customer_transactions
         WHERE type='campaign_view' AND amount < 0 AND created_at >= %s AND created_at < %s",
        $range_start, $range_end
    ));

    $deposits_total = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount + bonus_amount),0) FROM {$p}customer_deposits
         WHERE status='approved' AND created_at >= %s AND created_at < %s",
        $range_start, $range_end
    ));

    $withdrawals_total = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}withdrawals
         WHERE status IN ('completed','approved') AND created_at >= %s AND created_at < %s",
        $range_start, $range_end
    ));

    $new_users = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= %s AND user_registered < %s",
        $range_start, $range_end
    ));

    wp_send_json_success(array(
        'daily'   => $data,
        'summary' => array(
            'total_visits'     => $sum_visits,
            'verified'         => $sum_verified,
            'customer_paid'    => $customer_paid_total,
            'user_earned'      => $sum_user_earned,
            'platform_revenue' => $customer_paid_total - $sum_user_earned,
            'deposits'         => $deposits_total,
            'withdrawals'      => $withdrawals_total,
            'new_users'        => $new_users,
        ),
    ));
}

// Campaign CRUD
add_action('wp_ajax_traffictop_admin_create_campaign', 'traffictop_ajax_admin_create_campaign');
function traffictop_ajax_admin_create_campaign() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $result = traffictop_create_keyword_campaign($_POST);
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    wp_send_json_success(array('campaign_id'=>$result));
}

add_action('wp_ajax_traffictop_admin_update_campaign', 'traffictop_ajax_admin_update_campaign');
function traffictop_ajax_admin_update_campaign() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $id = absint($_POST['campaign_id']??0);
    if (!$id) wp_send_json_error('Missing ID');

    // Screenshot URLs (already uploaded to ImgBB via AJAX)
    foreach (array('screenshot_desktop_url', 'screenshot_mobile_url', 'nocode_screenshot_url') as $col) {
        if (!empty($_POST[$col])) {
            $_POST[$col] = esc_url_raw($_POST[$col]);
        }
    }

    // Auto-calculate price_per_view and user_reward from settings
    if (isset($_POST['traffic_type'])) {
        global $wpdb; $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
        $camp = $wpdb->get_row($wpdb->prepare(
            "SELECT kc.*, co.task_type FROM {$p}keyword_campaigns kc LEFT JOIN {$p}customer_orders co ON co.id=kc.order_id WHERE kc.id=%d", $id));
        if ($camp) {
            $task_type = $camp->task_type ?? 'keyword_search';
            $tt = sanitize_text_field($_POST['traffic_type']);
            $os = intval($_POST['onsite_time'] ?? $camp->onsite_time ?? 70);
            $price_key = ($task_type === 'keyword_search') ? 'keyword_price_' : 'direct_price_';
            $reward_key = ($task_type === 'keyword_search') ? 'keyword_user_' : 'direct_user_';
            $onsite_extra = array(70=>(int)traffictop_get_option('onsite_extra_70',0),80=>(int)traffictop_get_option('onsite_extra_80',100),90=>(int)traffictop_get_option('onsite_extra_90',200),100=>(int)traffictop_get_option('onsite_extra_100',300),120=>(int)traffictop_get_option('onsite_extra_120',400),150=>(int)traffictop_get_option('onsite_extra_150',500));
            if (!isset($_POST['price_per_view'])) {
                $_POST['price_per_view'] = floatval(traffictop_get_option($price_key . $tt, 1200)) + ($onsite_extra[$os] ?? 0);
            }
            if (!isset($_POST['user_reward'])) {
                $user_onsite_extra = array(70=>(int)traffictop_get_option('user_onsite_extra_70',0),80=>(int)traffictop_get_option('user_onsite_extra_80',0),90=>(int)traffictop_get_option('user_onsite_extra_90',0),100=>(int)traffictop_get_option('user_onsite_extra_100',0),120=>(int)traffictop_get_option('user_onsite_extra_120',0),150=>(int)traffictop_get_option('user_onsite_extra_150',0));
                $_POST['user_reward'] = floatval(traffictop_get_option($reward_key . $tt, 800)) + ($user_onsite_extra[$os] ?? 0);
            }
        }
    }

    $result = traffictop_update_campaign($id, $_POST);
    if ($result === false) wp_send_json_error('Update failed');
    wp_send_json_success();
}

add_action('wp_ajax_traffictop_admin_get_campaigns', 'traffictop_ajax_admin_get_campaigns');

// Get single campaign detail (admin)
add_action('wp_ajax_traffictop_admin_get_campaign', 'traffictop_ajax_admin_get_campaign');
function traffictop_ajax_admin_get_campaign() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
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
        'fixed_code'=>$c->fixed_code??'', 'nocode_screenshot_url'=>$c->nocode_screenshot_url??'',
    ));
}

// Update widget code status (Đã gắn / Chưa gắn widget.js trên web đích)
add_action('wp_ajax_traffictop_admin_update_widget_code_status', 'traffictop_ajax_admin_update_widget_code_status');
function traffictop_ajax_admin_update_widget_code_status() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $id = absint($_POST['campaign_id'] ?? 0);
    $status = sanitize_text_field($_POST['widget_code_status'] ?? '');
    if (!$id) wp_send_json_error('Missing ID');
    if (!in_array($status, array('attached','not_attached'))) wp_send_json_error('Invalid status');
    // Ensure column exists
    $col = $wpdb->get_results("SHOW COLUMNS FROM {$p}keyword_campaigns LIKE 'widget_code_status'");
    if (empty($col)) {
        $wpdb->query("ALTER TABLE {$p}keyword_campaigns ADD COLUMN widget_code_status varchar(20) NOT NULL DEFAULT 'not_attached' AFTER daily_traffic");
    }
    $wpdb->update("{$p}keyword_campaigns", array('widget_code_status' => $status), array('id' => $id));
    wp_send_json_success();
}

function traffictop_ajax_admin_get_campaigns() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $campaigns = traffictop_get_campaigns(array(
        'status'=>sanitize_text_field($_POST['status']??''),
        'search'=>sanitize_text_field($_POST['search']??''),
        'limit'=>absint($_POST['limit']??20), 'offset'=>absint($_POST['offset']??0),
    ));
    wp_send_json_success(array('campaigns'=>$campaigns));
}

// Withdrawal management
add_action('wp_ajax_traffictop_admin_process_withdrawal', 'traffictop_ajax_admin_process_withdrawal');
function traffictop_ajax_admin_process_withdrawal() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $result = traffictop_process_withdrawal(absint($_POST['withdrawal_id']??0), sanitize_text_field($_POST['new_status']??''), sanitize_text_field($_POST['admin_note']??''));
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    wp_send_json_success();
}

// Fraud check for withdrawal
add_action('wp_ajax_traffictop_admin_fraud_check', 'traffictop_ajax_admin_fraud_check');
function traffictop_ajax_admin_fraud_check() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';
    $user_id = absint($_POST['user_id'] ?? 0);
    $wid = absint($_POST['withdrawal_id'] ?? 0);
    if (!$user_id) wp_send_json_error('Missing user_id');

    $w = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}withdrawals WHERE id=%d", $wid));
    $user = get_userdata($user_id);
    $display_name = $user ? $user->display_name : 'User #'.$user_id;

    // Total earned from transactions (source of truth)
    $total_earned = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE user_id=%d AND type='shortlink_reward'", $user_id));

    // Valid views (verified + reward_paid)
    $valid_views = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits v
         INNER JOIN {$p}user_shortlinks us ON v.shortlink_id=us.id
         WHERE us.user_id=%d AND v.step='verified' AND v.reward_paid=1", $user_id));

    // Total clicks
    $total_clicks = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits v
         INNER JOIN {$p}user_shortlinks us ON v.shortlink_id=us.id
         WHERE us.user_id=%d", $user_id));

    $completion_rate = $total_clicks > 0 ? round($valid_views / $total_clicks * 100, 1) : 0;

    // Flags
    $bypass_cnt = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits v
         INNER JOIN {$p}user_shortlinks us ON v.shortlink_id=us.id
         WHERE us.user_id=%d AND v.is_bypass=1", $user_id));

    $ip_changed_cnt = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits v
         INNER JOIN {$p}user_shortlinks us ON v.shortlink_id=us.id
         WHERE us.user_id=%d AND v.ip_changed=1", $user_id));

    $ip_limit_cnt = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits v
         INNER JOIN {$p}user_shortlinks us ON v.shortlink_id=us.id
         WHERE us.user_id=%d AND v.ip_limit_exceeded=1", $user_id));

    $adblock_cnt = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits v
         INNER JOIN {$p}user_shortlinks us ON v.shortlink_id=us.id
         WHERE us.user_id=%d AND v.adblock_detected=1", $user_id));

    // IPs with >3 visits
    $ip_gt3 = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM (
            SELECT v.ip_address, COUNT(*) as cnt
            FROM {$p}shortlink_visits v
            INNER JOIN {$p}user_shortlinks us ON v.shortlink_id=us.id
            WHERE us.user_id=%d AND v.step='verified'
            GROUP BY v.ip_address HAVING cnt > 3
        ) t", $user_id));

    // Activity duration
    $first_visit = $wpdb->get_var($wpdb->prepare(
        "SELECT MIN(v.created_at) FROM {$p}shortlink_visits v
         INNER JOIN {$p}user_shortlinks us ON v.shortlink_id=us.id
         WHERE us.user_id=%d AND v.step='verified'", $user_id));
    $activity_days = $first_visit ? max(1, floor((strtotime(traffictop_current_time()) - strtotime($first_visit)) / 86400)) : 0;

    // Risk assessment
    $risk = 'safe';
    $risk_reasons = [];
    if ($completion_rate > 0 && $completion_rate < 30) { $risk = 'medium'; $risk_reasons[] = 'Tỷ lệ hoàn thành thấp (' . $completion_rate . '%)'; }
    if ($bypass_cnt > 0) { $risk = max($risk === 'high' ? 'high' : 'medium', 'medium'); $risk_reasons[] = $bypass_cnt . ' bypass'; }
    if ($ip_changed_cnt > 2) { $risk = 'medium'; $risk_reasons[] = $ip_changed_cnt . ' IP changed'; }
    if ($ip_limit_cnt > 3) { $risk = 'high'; $risk_reasons[] = $ip_limit_cnt . ' IP limit exceeded'; }
    if ($ip_gt3 > 5) { $risk = 'high'; $risk_reasons[] = $ip_gt3 . ' IP có >3 visits'; }
    if ($total_earned > 0 && $activity_days > 0 && $total_earned / $activity_days > 100000) { $risk = 'high'; $risk_reasons[] = 'Thu nhập cao bất thường'; }
    // Upgrade risk based on flag counts
    $flag_total = $bypass_cnt + $ip_changed_cnt + $ip_limit_cnt + $adblock_cnt;
    if ($flag_total > 10 && $risk !== 'high') { $risk = 'high'; }
    elseif ($flag_total > 5 && $risk === 'safe') { $risk = 'low'; }
    elseif ($flag_total > 0 && $risk === 'safe') { $risk = 'low'; }

    $risk_labels = ['safe' => 'An toàn', 'low' => 'Rủi ro thấp', 'medium' => 'Rủi ro trung bình', 'high' => 'Rủi ro cao'];

    // Traffic sources (referer domains)
    $sources = $wpdb->get_results($wpdb->prepare(
        "SELECT
            CASE
                WHEN v.referer IS NULL OR v.referer='' THEN 'Direct'
                WHEN v.referer LIKE '%%youtube.com%%' THEN 'YouTube'
                WHEN v.referer LIKE '%%facebook.com%%' THEN 'Facebook'
                WHEN v.referer LIKE '%%google.com%%' OR v.referer LIKE '%%google.com.vn%%' THEN 'Google'
                WHEN v.referer LIKE '%%tiktok.com%%' THEN 'TikTok'
                WHEN v.referer LIKE '%%zalo.me%%' OR v.referer LIKE '%%zalo.vn%%' THEN 'Zalo'
                ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(v.referer,'https://',''),'http://',''),'/',1),'?',1)
            END as source,
            COUNT(*) as cnt
         FROM {$p}shortlink_visits v
         INNER JOIN {$p}user_shortlinks us ON v.shortlink_id=us.id
         WHERE us.user_id=%d AND v.step='verified'
         GROUP BY source ORDER BY cnt DESC LIMIT 10", $user_id));

    // Top 10 IPs
    $top_ips = $wpdb->get_results($wpdb->prepare(
        "SELECT v.ip_address, COUNT(*) as cnt,
                COALESCE(SUM(v.reward_amount),0) as earned
         FROM {$p}shortlink_visits v
         INNER JOIN {$p}user_shortlinks us ON v.shortlink_id=us.id
         WHERE us.user_id=%d AND v.step='verified'
         GROUP BY v.ip_address ORDER BY cnt DESC LIMIT 10", $user_id));

    // Top 10 Shortlinks
    $top_links = $wpdb->get_results($wpdb->prepare(
        "SELECT us.code, COUNT(*) as views,
                COALESCE(SUM(v.reward_amount),0) as earned
         FROM {$p}shortlink_visits v
         INNER JOIN {$p}user_shortlinks us ON v.shortlink_id=us.id
         WHERE us.user_id=%d AND v.step='verified' AND v.reward_paid=1
         GROUP BY us.id ORDER BY views DESC LIMIT 10", $user_id));

    // Build HTML
    $html = '';

    // Summary cards
    $html .= '<div class="wd-fraud-grid">';
    $html .= '<div class="wd-fraud-card"><h4>Số tiền rút</h4><div class="val" style="color:#dc2626">' . ($w ? traffictop_format_money($w->amount) : '—') . '</div></div>';
    $html .= '<div class="wd-fraud-card"><h4>View hợp lệ</h4><div class="val" style="color:#2563eb">' . number_format($valid_views) . '</div></div>';
    $html .= '<div class="wd-fraud-card"><h4>Tổng tiền kiếm được</h4><div class="val" style="color:#059669">' . traffictop_format_money($total_earned) . '</div></div>';
    $html .= '<div class="wd-fraud-card"><h4>Thời gian hoạt động</h4><div class="val">' . $activity_days . ' ngày</div></div>';
    $html .= '</div>';

    // Risk assessment
    $html .= '<div class="wd-fraud-risk ' . esc_attr($risk) . '">' . $risk_labels[$risk];
    if (!empty($risk_reasons)) $html .= ' — ' . esc_html(implode(', ', $risk_reasons));
    $html .= '</div>';

    // Stats table
    $html .= '<h4 style="margin:0 0 8px;font-size:13px">Thống kê</h4>';
    $html .= '<table class="wd-fraud-tbl"><thead><tr><th>Click</th><th>View (%)</th><th>Bypass</th><th>Change IP</th><th>Max IP</th><th>Adblock</th><th>IP &gt;3</th></tr></thead><tbody><tr>';
    $html .= '<td>' . number_format($total_clicks) . '</td>';
    $html .= '<td>' . number_format($valid_views) . ' (' . $completion_rate . '%)</td>';
    $html .= '<td>' . ($bypass_cnt > 0 ? '<span style="color:#dc2626;font-weight:600">' . $bypass_cnt . '</span>' : '0') . '</td>';
    $html .= '<td>' . ($ip_changed_cnt > 0 ? '<span style="color:#d97706;font-weight:600">' . $ip_changed_cnt . '</span>' : '0') . '</td>';
    $html .= '<td>' . ($ip_limit_cnt > 0 ? '<span style="color:#dc2626;font-weight:600">' . $ip_limit_cnt . '</span>' : '0') . '</td>';
    $html .= '<td>' . ($adblock_cnt > 0 ? '<span style="color:#d97706;font-weight:600">' . $adblock_cnt . '</span>' : '0') . '</td>';
    $html .= '<td>' . ($ip_gt3 > 0 ? '<span style="color:#dc2626;font-weight:600">' . $ip_gt3 . '</span>' : '0') . '</td>';
    $html .= '</tr></tbody></table>';

    // Traffic sources
    if (!empty($sources)) {
        $html .= '<h4 style="margin:0 0 8px;font-size:13px">Nguồn traffic</h4>';
        $html .= '<table class="wd-fraud-tbl"><thead><tr><th>Nguồn</th><th>Lượt</th><th>%</th></tr></thead><tbody>';
        $source_total = array_sum(array_column($sources, 'cnt'));
        foreach ($sources as $src) {
            $pct = $source_total > 0 ? round($src->cnt / $source_total * 100, 1) : 0;
            $html .= '<tr><td>' . esc_html($src->source) . '</td><td>' . number_format($src->cnt) . '</td><td>' . $pct . '%</td></tr>';
        }
        $html .= '</tbody></table>';
    }

    // Top IPs
    if (!empty($top_ips)) {
        $ip_total = array_sum(array_column($top_ips, 'cnt'));
        $html .= '<h4 style="margin:0 0 8px;font-size:13px">Top 10 IP</h4>';
        $html .= '<table class="wd-fraud-tbl"><thead><tr><th>IP</th><th>Số lần</th><th>%</th><th>Tiền kiếm được</th></tr></thead><tbody>';
        foreach ($top_ips as $ip) {
            $pct = $ip_total > 0 ? round($ip->cnt / $ip_total * 100, 1) : 0;
            $html .= '<tr><td><code style="font-size:11px">' . esc_html($ip->ip_address) . '</code></td><td>' . number_format($ip->cnt) . '</td><td>' . $pct . '%</td><td>' . traffictop_format_money($ip->earned) . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }

    // Top Shortlinks
    if (!empty($top_links)) {
        $html .= '<h4 style="margin:0 0 8px;font-size:13px">Top 10 Shortlink</h4>';
        $html .= '<table class="wd-fraud-tbl"><thead><tr><th>Code</th><th>Views</th><th>Tiền kiếm được</th></tr></thead><tbody>';
        foreach ($top_links as $lk) {
            $html .= '<tr><td><code style="font-size:11px">' . esc_html($lk->code) . '</code></td><td>' . number_format($lk->views) . '</td><td>' . traffictop_format_money($lk->earned) . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }

    wp_send_json_success(array('html' => $html));
}

// Deposit management
add_action('wp_ajax_traffictop_admin_approve_deposit', 'traffictop_ajax_admin_approve_deposit');
function traffictop_ajax_admin_approve_deposit() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $result = traffictop_approve_deposit(absint($_POST['deposit_id']??0), sanitize_text_field($_POST['admin_note']??''));
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    wp_send_json_success();
}

// Ban/unban user
add_action('wp_ajax_traffictop_admin_ban_user', 'traffictop_ajax_admin_ban_user');
function traffictop_ajax_admin_ban_user() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $uid = absint($_POST['user_id']??0);
    $action = sanitize_text_field($_POST['ban_action']??'ban');
    if ($action === 'ban') traffictop_ban_user($uid);
    else traffictop_unban_user($uid);
    wp_send_json_success();
}

// User stats (for modal)
add_action('wp_ajax_traffictop_admin_user_stats', 'traffictop_ajax_admin_user_stats');
function traffictop_ajax_admin_user_stats() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $uid = absint($_POST['user_id']??0);
    if (!$uid) wp_send_json_error('Missing user_id');

    $user = get_userdata($uid);
    if (!$user) wp_send_json_error('User not found');

    $now = traffictop_current_time();
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
add_action('wp_ajax_traffictop_admin_login_as_user', 'traffictop_ajax_admin_login_as_user');
function traffictop_ajax_admin_login_as_user() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
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

// Edit user (name/email/phone/password)
add_action('wp_ajax_traffictop_admin_edit_user', 'traffictop_ajax_admin_edit_user');
function traffictop_ajax_admin_edit_user() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $uid = absint($_POST['user_id'] ?? 0);
    if (!$uid) wp_send_json_error('Thiếu user_id');
    $user = get_userdata($uid);
    if (!$user) wp_send_json_error('Không tìm thấy user');

    $display_name = sanitize_text_field($_POST['display_name'] ?? '');
    $email        = sanitize_email($_POST['email'] ?? '');
    $phone        = sanitize_text_field($_POST['phone'] ?? '');
    $password     = (string) ($_POST['password'] ?? '');

    if (empty($display_name)) wp_send_json_error('Tên hiển thị không được để trống');
    if (empty($email) || !is_email($email)) wp_send_json_error('Email không hợp lệ');

    // Check email unique (nếu khác email hiện tại)
    if (strtolower($email) !== strtolower($user->user_email)) {
        $exists = get_user_by('email', $email);
        if ($exists && (int) $exists->ID !== $uid) {
            wp_send_json_error('Email đã được sử dụng bởi tài khoản khác');
        }
    }

    if ($password !== '' && strlen($password) < 6) {
        wp_send_json_error('Mật khẩu phải từ 6 ký tự trở lên');
    }

    $update = array(
        'ID'           => $uid,
        'display_name' => $display_name,
        'user_email'   => $email,
    );
    if ($password !== '') $update['user_pass'] = $password;

    $result = wp_update_user($update);
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());

    if ($phone !== '') {
        update_user_meta($uid, 'phone', $phone);
    } else {
        delete_user_meta($uid, 'phone');
    }

    wp_send_json_success(array('message' => 'Cập nhật thành công'));
}

// Delete user
add_action('wp_ajax_traffictop_admin_delete_user', 'traffictop_ajax_admin_delete_user');
function traffictop_ajax_admin_delete_user() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $uid = absint($_POST['user_id']??0);
    if (!$uid) wp_send_json_error('Missing user_id');
    $user = get_userdata($uid);
    if (!$user) wp_send_json_error('User not found');
    if (user_can($uid, 'manage_options')) wp_send_json_error('Không thể xóa admin');

    global $wpdb; $p = $wpdb->prefix . TRAFFICTOP_PREFIX;

    // Reject pending withdrawals first (refund to balance)
    $pending_wds = $wpdb->get_results( $wpdb->prepare(
        "SELECT id FROM {$p}withdrawals WHERE user_id=%d AND status IN ('pending','approved')", $uid ));
    foreach ( $pending_wds as $w ) {
        traffictop_process_withdrawal($w->id, 'rejected', 'Auto-rejected: user deleted');
    }

    // Only clean up NON-financial data
    // KEEP: transactions, withdrawals, user_balance (financial audit trail)
    $wpdb->delete("{$p}notifications", array('user_id'=>$uid));

    // Soft-delete shortlinks (preserve for financial reference)
    $wpdb->update("{$p}user_shortlinks", array('status'=>'disabled'), array('user_id'=>$uid, 'status'=>'active'));

    // Mark user as deleted in balance table for audit
    update_user_meta($uid, 'traffictop_deleted', 1);
    update_user_meta($uid, 'traffictop_deleted_at', traffictop_current_time());

    wp_delete_user($uid);
    wp_send_json_success(array('message' => 'User deleted. Financial data preserved.'));
}

// Run unit tests
add_action('wp_ajax_traffictop_admin_run_tests', 'traffictop_ajax_admin_run_tests');
function traffictop_ajax_admin_run_tests() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $test_file = TRAFFICTOP_DIR . '/tests/unit/run.php';
    if (!file_exists($test_file)) wp_send_json_error('Tests not found');
    $output = '';
    if (function_exists('exec')) { exec(PHP_BINARY.' '.escapeshellarg($test_file).' 2>&1', $lines); $output=implode("\n",$lines); }
    elseif (function_exists('shell_exec')) { $output=shell_exec(PHP_BINARY.' '.escapeshellarg($test_file).' 2>&1'); }
    else { ob_start(); include $test_file; $output=ob_get_clean(); }
    wp_send_json_success(array('output'=>$output));
}

// Recreate DB
add_action('wp_ajax_traffictop_admin_recreate_db', 'traffictop_ajax_admin_recreate_db');
function traffictop_ajax_admin_recreate_db() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    traffictop_create_tables();
    wp_send_json_success(array('output' => 'Đã tạo lại bảng DB thành công.'));
}

// ─── Announcements CRUD ───
add_action('wp_ajax_traffictop_admin_get_announcements', 'traffictop_ajax_admin_get_announcements');
function traffictop_ajax_admin_get_announcements() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $rows = $wpdb->get_results("SELECT * FROM {$p}announcements ORDER BY is_pinned DESC, created_at DESC LIMIT 50");
    wp_send_json_success(array('announcements' => $rows));
}

add_action('wp_ajax_traffictop_admin_create_announcement', 'traffictop_ajax_admin_create_announcement');
function traffictop_ajax_admin_create_announcement() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
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
        'status' => 'active', 'created_at' => traffictop_current_time(),
    ));
    wp_send_json_success(array('id' => $wpdb->insert_id));
}

add_action('wp_ajax_traffictop_admin_update_announcement', 'traffictop_ajax_admin_update_announcement');
function traffictop_ajax_admin_update_announcement() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
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

add_action('wp_ajax_traffictop_admin_delete_announcement', 'traffictop_ajax_admin_delete_announcement');
function traffictop_ajax_admin_delete_announcement() {
    check_ajax_referer('traffictop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $id = absint($_POST['id'] ?? 0);
    if (!$id) wp_send_json_error('Missing ID');
    $wpdb->delete("{$p}announcements", array('id' => $id));
    wp_send_json_success();
}

// Public: get active announcements for dashboard
add_action('wp_ajax_traffictop_get_announcements', 'traffictop_ajax_get_announcements');
add_action('wp_ajax_nopriv_traffictop_get_announcements', 'traffictop_ajax_get_announcements');
function traffictop_ajax_get_announcements() {
    check_ajax_referer('traffictop_nonce', 'nonce');
    global $wpdb; $p = $wpdb->prefix . TRAFFICTOP_PREFIX;

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

