<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'traffictop_';
$now_vn = traffictop_current_time();
$today = date('Y-m-d', strtotime($now_vn));
$visit_expiry = function_exists('traffictop_get_visit_expiry_seconds') ? traffictop_get_visit_expiry_seconds() : 600;
$expiry_cutoff = date('Y-m-d H:i:s', strtotime($now_vn) - $visit_expiry);

// Site host (cho self-detection, không hardcode domain)
$site_host = preg_replace('/^www\./', '', strtolower((string) parse_url(home_url(), PHP_URL_HOST)));

// SQL LIKE patterns cho từng "Nguồn shortlink" — anchor `//domain.` hoặc `.domain.`
// để tránh substring match (vd: t.com chứa t.co)
$slsource_map = array(
    'google'    => array('%//google.%','%.google.%'),
    'facebook'  => array('%//facebook.%','%.facebook.%','%//fb.com/%','%//fb.com','%//fb.me/%','%//fb.me'),
    'twitter'   => array('%//twitter.%','%.twitter.%','%//x.com/%','%//x.com','%.x.com/%','%//t.co/%'),
    'youtube'   => array('%//youtube.%','%.youtube.%','%//youtu.be/%','%//youtu.be'),
    'tiktok'    => array('%//tiktok.%','%.tiktok.%'),
    'instagram' => array('%//instagram.%','%.instagram.%'),
    'telegram'  => array('%//telegram.%','%.telegram.%','%//t.me/%'),
    'zalo'      => array('%//zalo.%','%.zalo.%','%//zaloapp.%'),
    'reddit'    => array('%//reddit.%','%.reddit.%'),
    'bing'      => array('%//bing.%','%.bing.%'),
);
if ($site_host !== '') {
    $slsource_map['dashboard'] = array(
        '%//' . $site_host . '/user%',  '%.' . $site_host . '/user%',
        '%//' . $site_host . '/customer%', '%.' . $site_host . '/customer%',
        '%//' . $site_host . '/wp-admin%', '%.' . $site_host . '/wp-admin%',
    );
    // Static pages (auth/legal): /dang-nhap, /dang-ky, /quen-mat-khau, /dieu-khoan
    $slsource_map['static_only'] = array(
        '%//' . $site_host . '/dang-nhap%', '%.' . $site_host . '/dang-nhap%',
        '%//' . $site_host . '/dang-ky%',   '%.' . $site_host . '/dang-ky%',
        '%//' . $site_host . '/quen-mat-khau%', '%.' . $site_host . '/quen-mat-khau%',
        '%//' . $site_host . '/dieu-khoan%', '%.' . $site_host . '/dieu-khoan%',
    );
    $slsource_map['internal'] = array('%//' . $site_host . '%', '%.' . $site_host . '%');

    // REGEXP patterns cho home_only và page_unlock (LIKE không express được)
    // Escape level: PHP source '\\.' → string `\.` (2 chars) → wpdb addslashes
    // → SQL `\\.` → MySQL parse `\.` → REGEXP literal dot.
    $host_re = str_replace('.', '\\.', $site_host); // 'traffictop.net' → 'traffictop\.net'
    // Home: path = '' or '/' (không có path nào sau host)
    $slsource_regex_home = '^https?://(www\\.)?' . $host_re . '/?(\\?.*)?$';
    // Page-unlock: exactly 6-char alphanumeric path
    $slsource_regex_page_unlock = '^https?://(www\\.)?' . $host_re . '/[A-Za-z0-9]{6}/?(\\?.*)?$';
}

$search_filter = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$step_filter = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$reason_filter = isset($_GET['reason']) ? sanitize_text_field($_GET['reason']) : '';
$traffic_filter = isset($_GET['traffic']) ? sanitize_text_field($_GET['traffic']) : '';
$slsource_filter = isset($_GET['slsource']) ? sanitize_text_field($_GET['slsource']) : '';
$dest_filter = isset($_GET['dest']) ? sanitize_text_field($_GET['dest']) : '';

$where = "WHERE 1=1";
$args = array();
if($search_filter){
    $like = '%' . $wpdb->esc_like($search_filter) . '%';
    $where .= " AND (u.user_login LIKE %s OR v.ip_address LIKE %s OR kc.keyword LIKE %s OR us.code LIKE %s)";
    $args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like;
}
if($step_filter){ $where .= " AND v.step = %s"; $args[] = $step_filter; }
if($status_filter === 'verified'){ $where .= " AND v.step = 'verified'"; }
elseif($status_filter === 'in_progress'){ $where .= $wpdb->prepare(" AND v.step != 'verified' AND v.created_at > %s", $expiry_cutoff); }
elseif($status_filter === 'expired'){ $where .= $wpdb->prepare(" AND v.step != 'verified' AND v.created_at <= %s", $expiry_cutoff); }
if($reason_filter === 'earned'){ $where .= " AND v.reward_paid = 1"; }
elseif($reason_filter === 'bypass'){ $where .= " AND v.is_bypass = 1"; }
elseif($reason_filter === 'change_ip'){ $where .= " AND v.step='verified' AND v.reward_paid=0 AND v.ip_changed = 1"; }
elseif($reason_filter === 'max_ip'){ $where .= " AND v.step='verified' AND v.reward_paid=0 AND v.ip_limit_exceeded = 1"; }
elseif($reason_filter === 'adblock'){ $where .= " AND v.step='verified' AND v.reward_paid=0 AND v.adblock_detected = 1"; }
elseif($reason_filter === 'no_google'){ $where .= " AND v.step='verified' AND v.reward_paid=0 AND v.from_google=0"; }
elseif($reason_filter === 'no_url_match'){ $where .= " AND v.step='verified' AND v.reward_paid=0 AND v.url_matched=0"; }
elseif($reason_filter === 'no_code'){ $where .= $wpdb->prepare(" AND v.step='target_visited' AND (v.verify_code IS NULL OR v.verify_code='') AND v.created_at <= %s", $expiry_cutoff); }
elseif($reason_filter === 'code_expired'){ $where .= $wpdb->prepare(" AND v.step='code_shown' AND (v.verify_code IS NULL OR v.verify_code='') AND v.created_at <= %s", $expiry_cutoff); }
elseif($reason_filter === 'adblock_mode2'){ $where .= " AND v.adblock_mode2 = 1"; }
elseif($reason_filter === 'self_click' && isset($slsource_map['dashboard'])){
    $patterns = $slsource_map['dashboard'];
    $or = implode(' OR ', array_fill(0, count($patterns), 'v.referer LIKE %s'));
    $where .= " AND ($or)";
    foreach ($patterns as $p) $args[] = $p;
}
if($traffic_filter){ $where .= " AND kc.traffic_type = %s"; $args[] = $traffic_filter; }

// Filter "Nguồn shortlink" — match referer host
// Sub-buckets: direct, social/search providers, dashboard, home_only, page_unlock,
// static_only, internal (= "Nội bộ khác"), other.
// "internal" và "other" PHẢI exclude TẤT CẢ sub-bucket trên để khớp 1-1 với badge UI.
if ($slsource_filter === 'direct') {
    $where .= " AND (v.referer IS NULL OR v.referer = '')";
} elseif ($slsource_filter === 'home_only' && !empty($slsource_regex_home)) {
    $where .= " AND v.referer REGEXP %s";
    $args[] = $slsource_regex_home;
} elseif ($slsource_filter === 'page_unlock' && !empty($slsource_regex_page_unlock)) {
    $where .= " AND v.referer REGEXP %s";
    $args[] = $slsource_regex_page_unlock;
} elseif (isset($slsource_map[$slsource_filter])) {
    $patterns = $slsource_map[$slsource_filter];
    $or = implode(' OR ', array_fill(0, count($patterns), 'v.referer LIKE %s'));
    $where .= " AND ($or)";
    foreach ($patterns as $p) $args[] = $p;
    // 'internal' (Nội bộ khác) phải EXCLUDE TẤT CẢ sub-bucket internal
    if ($slsource_filter === 'internal') {
        // Exclude dashboard + static (LIKE patterns)
        foreach (array('dashboard','static_only') as $sub) {
            if (isset($slsource_map[$sub])) {
                foreach ($slsource_map[$sub] as $p) {
                    $where .= " AND v.referer NOT LIKE %s";
                    $args[] = $p;
                }
            }
        }
        // Exclude home_only + page_unlock (REGEXP)
        if (!empty($slsource_regex_home)) {
            $where .= " AND v.referer NOT REGEXP %s";
            $args[] = $slsource_regex_home;
        }
        if (!empty($slsource_regex_page_unlock)) {
            $where .= " AND v.referer NOT REGEXP %s";
            $args[] = $slsource_regex_page_unlock;
        }
    }
} elseif ($slsource_filter === 'other') {
    // EXCLUDE đầy đủ: tất cả LIKE bucket + 2 REGEXP bucket
    $where .= " AND v.referer IS NOT NULL AND v.referer != ''";
    foreach ($slsource_map as $patterns) {
        foreach ($patterns as $p) {
            $where .= " AND v.referer NOT LIKE %s";
            $args[] = $p;
        }
    }
    if (!empty($slsource_regex_home)) {
        $where .= " AND v.referer NOT REGEXP %s";
        $args[] = $slsource_regex_home;
    }
    if (!empty($slsource_regex_page_unlock)) {
        $where .= " AND v.referer NOT REGEXP %s";
        $args[] = $slsource_regex_page_unlock;
    }
}

// Filter "Nguồn đích" — anti-fraud check theo service type
$svc_expr = "COALESCE(co.task_type, kc.campaign_type, 'keyword_search')";
if ($dest_filter === 'none') {
    $where .= " AND v.step = 'started'";
} elseif ($dest_filter === 'google_ok') {
    $where .= " AND v.step != 'started' AND $svc_expr = 'keyword_search' AND v.from_google = 1";
} elseif ($dest_filter === 'target_ok') {
    $where .= " AND v.step != 'started' AND $svc_expr = 'traffic_direct' AND v.url_matched = 1";
} elseif ($dest_filter === 'suspicious') {
    $where .= " AND v.step != 'started'
                AND NOT ($svc_expr = 'keyword_search' AND v.from_google = 1)
                AND NOT ($svc_expr = 'traffic_direct' AND v.url_matched = 1)";
}

$page_num = max(1, intval($_GET['paged'] ?? 1));
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

$wpdb->suppress_errors(true);
$service_filter = isset($_GET['service']) ? sanitize_text_field($_GET['service']) : '';
if($service_filter){ $where .= " AND COALESCE(co.task_type, kc.campaign_type, 'keyword_search') = %s"; $args[] = $service_filter; }

$count_sql = "SELECT COUNT(*) FROM {$prefix}shortlink_visits v LEFT JOIN {$prefix}keyword_campaigns kc ON kc.id=v.campaign_id LEFT JOIN {$prefix}customer_orders co ON co.id=kc.order_id LEFT JOIN {$wpdb->users} u ON u.ID=v.user_id LEFT JOIN {$prefix}user_shortlinks us ON us.id=v.shortlink_id $where";
$total = !empty($args) ? (int)$wpdb->get_var($wpdb->prepare($count_sql, $args)) : (int)$wpdb->get_var($count_sql);

$data_args = $args;
$data_args[] = $per_page;
$data_args[] = $offset;
// Defensive: chỉ SELECT created_via nếu cột tồn tại (compat installs cũ)
$has_created_via_col = (bool) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'created_via'",
    $wpdb->prefix . 'traffictop_user_shortlinks'));
$created_via_select = $has_created_via_col ? ', us.created_via as sl_created_via' : '';
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT v.*, kc.title as camp_title, kc.keyword, kc.target_url as camp_url, kc.traffic_type,
            kc.price_per_view, kc.fixed_code as camp_fixed_code, kc.onsite_time as camp_onsite,
            COALESCE(co.task_type, kc.campaign_type, 'keyword_search') as service_type,
            u.user_login, us.code as shortcode, us.original_url as sl_original_url {$created_via_select}
     FROM {$prefix}shortlink_visits v
     LEFT JOIN {$prefix}keyword_campaigns kc ON kc.id = v.campaign_id
     LEFT JOIN {$prefix}customer_orders co ON co.id = kc.order_id
     LEFT JOIN {$wpdb->users} u ON u.ID = v.user_id
     LEFT JOIN {$prefix}user_shortlinks us ON us.id = v.shortlink_id
     $where ORDER BY v.id DESC LIMIT %d OFFSET %d", $data_args
));
if(!is_array($rows)) $rows = array();

// Stats (always show totals, no date filter)
$stats = $wpdb->get_row($wpdb->prepare(
    "SELECT COUNT(*) as total,
        SUM(CASE WHEN step='verified' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN step IN ('started','google_clicked','target_visited','code_shown') AND created_at > %s THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN step != 'verified' AND created_at <= %s THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN is_bypass=1 THEN 1 ELSE 0 END) as bypass
     FROM {$prefix}shortlink_visits", $expiry_cutoff, $expiry_cutoff
));
$wpdb->suppress_errors(false);

$total_pages = ceil(max(1,$total) / $per_page);
?>
<div class="wrap">
<h1>Lượt truy cập</h1>

<!-- Stats cards -->
<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px">
    <div style="padding:8px 16px;border:1px solid #ddd;border-radius:6px;background:#fff;font-size:13px">Tổng: <strong><?php echo number_format($stats->total ?? 0); ?></strong></div>
    <div style="padding:8px 16px;border:1px solid #d4edda;border-radius:6px;background:#d4edda;font-size:13px;color:#155724">Hoàn thành: <strong><?php echo number_format($stats->completed ?? 0); ?></strong></div>
    <div style="padding:8px 16px;border:1px solid #fff3cd;border-radius:6px;background:#fff3cd;font-size:13px;color:#856404">Đang làm: <strong><?php echo number_format($stats->in_progress ?? 0); ?></strong></div>
    <div style="padding:8px 16px;border:1px solid #f8d7da;border-radius:6px;background:#f8d7da;font-size:13px;color:#721c24">Hết hạn: <strong><?php echo number_format($stats->expired ?? 0); ?></strong></div>
    <div style="padding:8px 16px;border:1px solid #e2e3e5;border-radius:6px;background:#e2e3e5;font-size:13px;color:#383d41">Bypass: <strong><?php echo number_format($stats->bypass ?? 0); ?></strong></div>
</div>

<!-- Filter -->
<form method="get" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end;margin-bottom:12px">
    <input type="hidden" name="page" value="traffictop-visits">
    <div><label style="display:block;font-size:10px;font-weight:600;color:#787c82;margin-bottom:2px">TÌM KIẾM</label><input type="search" name="s" value="<?php echo esc_attr($search_filter); ?>" placeholder="User, IP, từ khóa, shortlink..." style="padding:5px 8px;height:34px;min-width:200px"></div>
    <div><label style="display:block;font-size:10px;font-weight:600;color:#787c82;margin-bottom:2px">BƯỚC</label><select name="step" style="padding:5px 8px;height:34px">
        <option value="">Tất cả</option>
        <option value="started" <?php selected($step_filter,'started'); ?>>Bắt đầu</option>
        <option value="google_clicked" <?php selected($step_filter,'google_clicked'); ?>>Click Google</option>
        <option value="target_visited" <?php selected($step_filter,'target_visited'); ?>>Đã truy cập</option>
        <option value="code_shown" <?php selected($step_filter,'code_shown'); ?>>Hiện mã</option>
        <option value="verified" <?php selected($step_filter,'verified'); ?>>Đã xác minh</option>
    </select></div>
    <div><label style="display:block;font-size:10px;font-weight:600;color:#787c82;margin-bottom:2px">DỊCH VỤ</label><select name="service" style="padding:5px 8px;height:34px">
        <option value="">Tất cả</option>
        <option value="keyword_search" <?php selected($service_filter,'keyword_search'); ?>>Keyword</option>
        <option value="traffic_direct" <?php selected($service_filter,'traffic_direct'); ?>>Direct</option>
    </select></div>
    <div><label style="display:block;font-size:10px;font-weight:600;color:#787c82;margin-bottom:2px">LOẠI TRAFFIC</label><select name="traffic" style="padding:5px 8px;height:34px">
        <option value="">Tất cả</option>
        <option value="1step" <?php selected($traffic_filter,'1step'); ?>>1 bước</option>
        <option value="2step" <?php selected($traffic_filter,'2step'); ?>>2 bước</option>
        <option value="nocode" <?php selected($traffic_filter,'nocode'); ?>>Mã cố định</option>
    </select></div>
    <div><label style="display:block;font-size:10px;font-weight:600;color:#787c82;margin-bottom:2px">TRẠNG THÁI</label><select name="status" style="padding:5px 8px;height:34px">
        <option value="">Tất cả</option>
        <option value="verified" <?php selected($status_filter,'verified'); ?>>Hoàn thành</option>
        <option value="in_progress" <?php selected($status_filter,'in_progress'); ?>>Đang làm</option>
        <option value="expired" <?php selected($status_filter,'expired'); ?>>Hết hạn</option>
    </select></div>
    <div><label style="display:block;font-size:10px;font-weight:600;color:#787c82;margin-bottom:2px">LÝ DO</label><select name="reason" style="padding:5px 8px;height:34px">
        <option value="">Tất cả</option>
        <option value="earned" <?php selected($reason_filter,'earned'); ?>>Earned</option>
        <option value="self_click" <?php selected($reason_filter,'self_click'); ?>>⚠ Self-click</option>
        <option value="bypass" <?php selected($reason_filter,'bypass'); ?>>Bypass</option>
        <option value="change_ip" <?php selected($reason_filter,'change_ip'); ?>>Đổi IP</option>
        <option value="max_ip" <?php selected($reason_filter,'max_ip'); ?>>IP limit</option>
        <option value="adblock" <?php selected($reason_filter,'adblock'); ?>>Adblock</option>
        <option value="no_google" <?php selected($reason_filter,'no_google'); ?>>Chưa qua Google</option>
        <option value="no_url_match" <?php selected($reason_filter,'no_url_match'); ?>>Chưa khớp URL</option>
        <option value="no_code" <?php selected($reason_filter,'no_code'); ?>>Không lấy mã</option>
        <option value="code_expired" <?php selected($reason_filter,'code_expired'); ?>>Mã hết hạn</option>
        <option value="adblock_mode2" <?php selected($reason_filter,'adblock_mode2'); ?>>Adblock chặn widget</option>
    </select></div>
    <div><label style="display:block;font-size:10px;font-weight:600;color:#787c82;margin-bottom:2px">NGUỒN SHORTLINK</label><select name="slsource" style="padding:5px 8px;height:34px">
        <option value="">Tất cả</option>
        <option value="direct"    <?php selected($slsource_filter,'direct'); ?>>Trực tiếp</option>
        <option value="google"    <?php selected($slsource_filter,'google'); ?>>Google</option>
        <option value="facebook"  <?php selected($slsource_filter,'facebook'); ?>>Facebook</option>
        <option value="twitter"   <?php selected($slsource_filter,'twitter'); ?>>Twitter/X</option>
        <option value="youtube"   <?php selected($slsource_filter,'youtube'); ?>>YouTube</option>
        <option value="tiktok"    <?php selected($slsource_filter,'tiktok'); ?>>TikTok</option>
        <option value="instagram" <?php selected($slsource_filter,'instagram'); ?>>Instagram</option>
        <option value="telegram"  <?php selected($slsource_filter,'telegram'); ?>>Telegram</option>
        <option value="zalo"      <?php selected($slsource_filter,'zalo'); ?>>Zalo</option>
        <option value="reddit"    <?php selected($slsource_filter,'reddit'); ?>>Reddit</option>
        <option value="bing"      <?php selected($slsource_filter,'bing'); ?>>Bing</option>
        <?php if (isset($slsource_map['dashboard'])): ?>
        <option value="dashboard"   <?php selected($slsource_filter,'dashboard'); ?>>⚠ Dashboard (self-click)</option>
        <option value="home_only"   <?php selected($slsource_filter,'home_only'); ?>>Trang chủ</option>
        <option value="page_unlock" <?php selected($slsource_filter,'page_unlock'); ?>>Page-unlock</option>
        <option value="static_only" <?php selected($slsource_filter,'static_only'); ?>>Trang tĩnh</option>
        <option value="internal"    <?php selected($slsource_filter,'internal'); ?>>Nội bộ khác</option>
        <?php endif; ?>
        <option value="other"       <?php selected($slsource_filter,'other'); ?>>Khác</option>
    </select></div>
    <div><label style="display:block;font-size:10px;font-weight:600;color:#787c82;margin-bottom:2px">NGUỒN ĐÍCH</label><select name="dest" style="padding:5px 8px;height:34px">
        <option value="">Tất cả</option>
        <option value="none"       <?php selected($dest_filter,'none'); ?>>Chưa truy cập (—)</option>
        <option value="google_ok"  <?php selected($dest_filter,'google_ok'); ?>>Google (keyword OK)</option>
        <option value="target_ok"  <?php selected($dest_filter,'target_ok'); ?>>Đã vào target (direct OK)</option>
        <option value="suspicious" <?php selected($dest_filter,'suspicious'); ?>>⚠ Nghi gian lận</option>
    </select></div>
    <button type="submit" class="button button-primary" style="height:34px">Lọc</button>
    <a href="?page=traffictop-visits" class="button" style="height:34px">Reset</a>
</form>

<!-- Table -->
<style>
.ln-visits-tbl th{white-space:nowrap;font-size:13px}
.ln-visits-tbl td{font-size:13px}
.ln-visits-tbl .col-reason{white-space:nowrap}
.ln-visits-tbl .col-kw{min-width:160px;max-width:220px;word-break:break-all}
.ln-visits-tbl .col-type{white-space:nowrap;min-width:110px}
.ln-visits-tbl td code{white-space:nowrap}
@media(max-width:600px){.ln-visits-tbl th,.ln-visits-tbl td{padding:4px 5px}
.ln-visits-tbl .col-num{white-space:nowrap;text-align:right}
.ln-visits-tbl .col-kw{min-width:130px;max-width:160px}
.ln-visits-tbl .col-ip{font-size:10px}
.ln-visits-tbl .col-status span,.ln-visits-tbl .col-reason span{white-space:nowrap}
}
</style>
<div style="overflow-x:auto"><table class="widefat striped ln-visits-tbl">
<thead><tr>
    <th>Bắt đầu</th>
    <th>Kết thúc</th>
    <th>User</th>
    <th class="col-link">Shortlink</th>
    <th title="Link gốc của shortlink (URL đích publisher rút gọn)">Link gốc</th>
    <th title="Nguồn truy cập shortlink (HTTP_REFERER lúc click)">Nguồn shortlink</th>
    <th title="Nguồn truy cập URL đích (Google / target — anti-fraud)">Nguồn đích</th>
    <th>Dịch vụ</th>
    <th class="col-type">Loại</th>
    <th class="col-kw">Từ khóa / URL</th>
    <th class="col-num">KH trả</th>
    <th class="col-num">User nhận</th>
    <th class="col-code">Mã</th>
    <th class="col-status">Trạng thái</th>
    <th class="col-reason">Lý do</th>
    <th class="col-ip">IP</th>
    <th>TB</th>
</tr></thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="17">Không có dữ liệu.</td></tr>
<?php else: foreach($rows as $row):
    // Parse device
    $ua = $row->user_agent ?? '';
    $device = '—';
    if(stripos($ua,'Android')!==false) $device = 'Android';
    elseif(stripos($ua,'iPhone')!==false) $device = 'iPhone';
    elseif(stripos($ua,'Windows NT 10')!==false) $device = 'Win10/11';
    elseif(stripos($ua,'Windows')!==false) $device = 'Windows';
    elseif(stripos($ua,'Mac')!==false) $device = 'Mac';

    // ── (A.2) Nguồn shortlink: classify referer host (parse_url, không stripos)
    $sl_src = 'Trực tiếp';
    $sl_color = '#787c82';
    $sl_full = $row->referer ?? '';
    $is_self_click = false;
    if (!empty($row->referer)) {
        $host = strtolower((string) parse_url($row->referer, PHP_URL_HOST));
        $path = strtolower((string) parse_url($row->referer, PHP_URL_PATH));
        if ($host === '') {
            $sl_src = 'Khác'; $sl_color = '#374151';
        } else {
            $is_host = function($h, $needles) {
                foreach ((array)$needles as $n) {
                    if ($h === $n || substr($h, -strlen($n)-1) === '.'.$n) return true;
                }
                return false;
            };
            if (preg_match('/^(.+\.)?google\.[a-z.]+$/', $host)) { $sl_src='Google'; $sl_color='#46b450'; }
            elseif ($is_host($host, ['facebook.com','fb.com','fb.me'])) { $sl_src='Facebook'; $sl_color='#1877f2'; }
            elseif ($is_host($host, ['twitter.com','x.com','t.co'])) { $sl_src='Twitter/X'; $sl_color='#1da1f2'; }
            elseif ($is_host($host, ['youtube.com','youtu.be'])) { $sl_src='YouTube'; $sl_color='#ff0000'; }
            elseif ($is_host($host, 'tiktok.com')) { $sl_src='TikTok'; $sl_color='#000'; }
            elseif ($is_host($host, 'instagram.com')) { $sl_src='Instagram'; $sl_color='#e4405f'; }
            elseif ($is_host($host, ['telegram.org','telegram.me','t.me'])) { $sl_src='Telegram'; $sl_color='#0088cc'; }
            elseif ($is_host($host, ['zalo.me','zalo.vn','zaloapp.com'])) { $sl_src='Zalo'; $sl_color='#0068ff'; }
            elseif ($is_host($host, 'reddit.com')) { $sl_src='Reddit'; $sl_color='#ff4500'; }
            elseif ($is_host($host, 'bing.com')) { $sl_src='Bing'; $sl_color='#008373'; }
            elseif ($site_host !== '' && ($host === $site_host || substr($host, -strlen($site_host)-1) === '.'.$site_host)) {
                // (A.3) Sub-categorize internal
                if (preg_match('#^/(user|customer|wp-admin)#', $path)) {
                    $sl_src='Dashboard'; $sl_color='#dc3232'; $is_self_click = true;
                } elseif ($path === '' || $path === '/') {
                    $sl_src='Trang chủ'; $sl_color='#7c3aed';
                } elseif (preg_match('#^/[A-Za-z0-9]{6}/?$#', $path)) {
                    $sl_src='Page-unlock'; $sl_color='#0891b2';
                } elseif (preg_match('#^/(dang-nhap|dang-ky|quen-mat-khau|dieu-khoan)#', $path)) {
                    $sl_src='Trang tĩnh'; $sl_color='#a78bfa';
                } else {
                    $sl_src='Nội bộ khác'; $sl_color='#9333ea';
                }
            }
            else { $sl_src = preg_replace('/^www\./', '', $host); $sl_color='#374151'; }
        }
    }

    // Service type (Keyword/Direct)
    $svc = $row->service_type ?? 'keyword_search';

    // ── (A.6) Nguồn đích: anti-fraud check theo service type
    //   keyword_search: from_google=1 → Google (xanh), else —
    //   traffic_direct: url_matched=1 → {target_domain} (xanh), else —
    $source = '—';
    $source_color = '#787c82';
    if (($row->step ?? 'started') !== 'started') {
        if ($svc === 'keyword_search' && !empty($row->from_google)) {
            $source = 'Google'; $source_color = '#46b450';
        } elseif ($svc === 'traffic_direct' && !empty($row->url_matched)) {
            $tdom = parse_url($row->camp_url ?? '', PHP_URL_HOST);
            $source = $tdom ? preg_replace('/^www\./i', '', $tdom) : 'Đã vào target';
            $source_color = '#46b450';
        }
    }
    // Traffic type (1step/2step/nocode)
    $tt = $row->traffic_type ?? '';
    $tt_labels = ['1step'=>'1 bước','2step'=>'2 bước','nocode'=>'Mã cố định'];
    $tt_base = $tt_labels[$tt] ?? ($tt ? ucfirst($tt) : '—');
    $camp_onsite = (int)($row->camp_onsite ?? 0);
    $tt_label = $camp_onsite > 0 ? ($tt_base . ' · ' . $camp_onsite . 's') : $tt_base;
    $tt_color = '#787c82'; $tt_bg = '#f5f5f5';
    if($tt === '1step'){ $tt_color='#2271b1'; $tt_bg='#e7f3ff'; }
    elseif($tt === '2step'){ $tt_color='#dba617'; $tt_bg='#fff8e1'; }
    elseif($tt === 'nocode'){ $tt_color='#8c5e2a'; $tt_bg='#fef3e2'; }

    // Status
    $step = $row->step ?? 'started';
    $is_verified = ($step === 'verified');
    $is_expired = (!$is_verified && strtotime($row->created_at) < strtotime($now_vn) - $visit_expiry);
    if($is_verified){ $st_label='Hoàn thành'; $st_color='#155724'; $st_bg='#d4edda'; }
    elseif($is_expired){ $st_label='Hết hạn'; $st_color='#721c24'; $st_bg='#f8d7da'; }
    else{ $st_label='Đang làm'; $st_color='#856404'; $st_bg='#fff3cd'; }

    // Keyword/URL
    $camp_domain = parse_url($row->camp_url ?? '', PHP_URL_HOST);
?>
<tr<?php echo $is_verified ? ' style="background:#f0fff0"' : ''; ?>>
    <td style="font-size:12px;white-space:nowrap"><?php echo date('H:i:s', strtotime($row->created_at)); ?><br><small style="color:#787c82"><?php echo date('d/m/Y', strtotime($row->created_at)); ?></small></td>
    <td style="font-size:12px;white-space:nowrap"><?php echo $row->verified_at ? date('H:i:s', strtotime($row->verified_at)).'<br><small style="color:#787c82">'.date('d/m/Y', strtotime($row->verified_at)).'</small>' : '—'; ?></td>
    <td><strong><?php echo esc_html($row->user_login ?? 'Khách'); ?></strong></td>
    <td>
        <?php if ($row->shortcode): ?>
            <code style="padding:2px 6px;background:#e7f3ff;border-radius:3px;font-size:11px"><?php echo esc_html($row->shortcode); ?></code>
            <?php $cv = $row->sl_created_via ?? null; ?>
            <?php if ($cv === 'api'): ?>
                <span title="Tạo qua API endpoint" style="background:#fef3c7;color:#92400e;padding:1px 5px;border-radius:3px;font-size:10px;font-weight:600;margin-left:3px;white-space:nowrap">API</span>
            <?php elseif ($cv === 'manual'): ?>
                <span title="Tạo thủ công qua dashboard" style="background:#e0e7ff;color:#3730a3;padding:1px 5px;border-radius:3px;font-size:10px;font-weight:600;margin-left:3px;white-space:nowrap">Thủ công</span>
            <?php elseif ($has_created_via_col): ?>
                <span title="Tạo trước khi migrate — không xác định" style="background:#f3f4f6;color:#6b7280;padding:1px 5px;border-radius:3px;font-size:10px;font-weight:600;margin-left:3px;white-space:nowrap">Cũ</span>
            <?php endif; ?>
        <?php else: ?>—<?php endif; ?>
    </td>
    <td style="font-size:11px;white-space:nowrap"><?php $orig = $row->sl_original_url ?? ''; if ($orig) { $host = preg_replace('/^https?:\/\//', '', $orig); $host = preg_replace('/^www\./', '', $host); $host = strtok($host, '/?'); if (strlen($host) > 22) $host = substr($host, 0, 22) . '…'; echo '<a href="' . esc_url($orig) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr($orig) . '" style="color:#2563eb;text-decoration:none">' . esc_html($host) . ' ↗</a>'; } else { echo '—'; } ?></td>
    <td style="font-size:12px;color:<?php echo esc_attr($sl_color); ?>;font-weight:600;white-space:nowrap" title="<?php echo esc_attr($sl_full ?: 'Truy cập trực tiếp'); ?>"><?php echo esc_html($sl_src); ?></td>
    <td style="font-size:12px;color:<?php echo esc_attr($source_color); ?>;font-weight:600;white-space:nowrap"><?php echo esc_html($source); ?></td>
    <td><?php if($svc === 'keyword_search'): ?><span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#DEF7EC;color:#046C4E">Keyword</span><?php else: ?><span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#EDE9FE;color:#6D28D9">Direct</span><?php endif; ?></td>
    <td><?php if($tt_label!=='—'): ?><span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;white-space:nowrap;background:<?php echo $tt_bg; ?>;color:<?php echo $tt_color; ?>"><?php echo $tt_label; ?></span><?php else: ?>—<?php endif; ?></td>
    <td class="col-kw">
        <?php if($row->keyword): ?>
            <strong style="font-size:12px"><?php echo esc_html($row->keyword); ?></strong>
            <?php if($camp_domain): ?><br><small style="color:#787c82"><?php echo esc_html($camp_domain); ?></small><?php endif; ?>
        <?php elseif($row->camp_title): ?>
            <small><?php echo esc_html($row->camp_title); ?></small>
        <?php else: ?>—<?php endif; ?>
    </td>
    <td style="font-weight:600;color:<?php echo $row->customer_paid ? '#dc3232' : '#787c82'; ?>"><?php echo $row->customer_paid && $row->price_per_view ? traffictop_format_money($row->price_per_view) : '—'; ?></td>
    <td style="font-weight:600;color:<?php echo $row->reward_paid ? '#46b450' : '#787c82'; ?>"><?php echo $row->reward_paid ? traffictop_format_money($row->reward_amount) : ($row->customer_paid ? '<span style="color:#dc3232">Chưa trả</span>' : '—'); ?></td>
    <td><code style="font-size:10px"><?php echo esc_html(($row->traffic_type === 'nocode' && !empty($row->camp_fixed_code)) ? $row->camp_fixed_code : ($row->verify_code ?? '—')); ?></code></td>
    <td><span style="display:inline-block;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:600;background:<?php echo $st_bg; ?>;color:<?php echo $st_color; ?>"><?php echo $st_label; ?></span></td>
    <?php $is_adblock_m2 = ! empty( $row->adblock_mode2 ); ?>
    <td class="col-reason" style="font-size:11px"><?php
        if ($is_self_click) {
            echo '<span style="color:#dc3232;font-weight:700" title="Referer là dashboard nội bộ">⚠ Self-click</span><br>';
        }
        if ($row->reward_paid) { echo '<span style="color:#46b450;font-weight:600">Đã trả</span>'; }
        elseif ($is_expired) {
            if ($is_adblock_m2) { echo '<span style="color:#dc3232;font-weight:600">Adblock chặn widget</span>'; }
            elseif (!empty($row->verify_code)) { echo '<span style="color:#dc3232;font-weight:600">Có mã, không nhập</span>'; }
            elseif ($step === 'code_shown') { echo '<span style="color:#dc3232;font-weight:600">Mã hết hạn</span>'; }
            elseif ($step === 'target_visited') { echo '<span style="color:#856404;font-weight:600">Không lấy mã</span>'; }
            elseif ($step === 'google_clicked') { echo '<span style="color:#856404;font-weight:600">Chưa vào trang</span>'; }
            elseif ($step === 'started') { echo '<span style="color:#787c82;font-weight:600">Bỏ giữa chừng</span>'; }
            else { echo '<span style="color:#787c82">Hết hạn</span>'; }
        }
        elseif (!$is_verified) {
            if ($is_adblock_m2) { echo '<span style="color:#dc3232;font-weight:600">Adblock chặn widget</span>'; }
            else { echo '—'; }
        }
        else {
            $reasons = array();
            if ($is_adblock_m2) $reasons[] = '<span style="color:#dc3232">Adblock chặn widget</span>';
            if (!empty($row->is_bypass)) $reasons[] = '<span style="color:#dc3232">Bypass</span>';
            if (!empty($row->ip_changed)) $reasons[] = '<span style="color:#dc3232">Đổi IP</span>';
            if (!empty($row->ip_limit_exceeded)) $reasons[] = '<span style="color:#dc3232">IP limit</span>';
            if (!empty($row->adblock_detected)) $reasons[] = '<span style="color:#dc3232">Adblock</span>';
            if (!$row->customer_paid) $reasons[] = '<span style="color:#856404">KH chưa trả</span>';
            if (empty($row->from_google) && !empty($row->keyword)) $reasons[] = '<span style="color:#dc3232">Chưa qua Google</span>';
            $vtt = $row->traffic_type ?? '';
            if ($vtt !== 'nocode' && empty($row->url_matched)) $reasons[] = '<span style="color:#dc3232">Chưa khớp URL</span>';
            echo $reasons ? implode(', ', $reasons) : '<span style="color:#787c82">Không rõ</span>';
        }
    ?></td>
    <td style="min-width:120px"><code style="font-size:11px;word-break:break-all"><?php echo esc_html($row->ip_address ?? ''); ?></code><?php if(!empty($row->ip_changed)): ?><br><small style="color:#dc3232">Đã đổi</small><?php endif; ?></td>
    <td style="font-size:11px"><?php echo esc_html($device); ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table></div>

<?php if($total_pages > 1):
    $pag_params = array('page'=>'traffictop-visits');
    if($search_filter) $pag_params['s'] = $search_filter;
    if($step_filter) $pag_params['step'] = $step_filter;
    if($status_filter) $pag_params['status'] = $status_filter;
    if($reason_filter) $pag_params['reason'] = $reason_filter;
    if($traffic_filter) $pag_params['traffic'] = $traffic_filter;
    if($service_filter) $pag_params['service'] = $service_filter;
    if($slsource_filter) $pag_params['slsource'] = $slsource_filter;
    if($dest_filter) $pag_params['dest'] = $dest_filter;
?>
<div class="tablenav bottom"><div class="tablenav-pages">
    <span style="font-size:12px;color:#787c82;margin-right:10px">Trang <?php echo $page_num; ?>/<?php echo $total_pages; ?> (<?php echo number_format($total); ?> kết quả)</span>
    <?php if($page_num > 1): ?><a class="button" href="?<?php echo http_build_query(array_merge($pag_params, array('paged'=>$page_num-1))); ?>">« Trước</a><?php endif; ?>
    <?php if($page_num < $total_pages): ?><a class="button" href="?<?php echo http_build_query(array_merge($pag_params, array('paged'=>$page_num+1))); ?>">Sau »</a><?php endif; ?>
</div></div>
<?php endif; ?>

</div>
