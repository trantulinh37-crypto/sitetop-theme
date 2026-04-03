<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';
$now_vn = linkngon_current_time();
$today = date('Y-m-d', strtotime($now_vn));
$ten_min_ago = date('Y-m-d H:i:s', strtotime($now_vn) - 600);
$date_filter = !empty($_GET['date']) ? sanitize_text_field($_GET['date']) : $today;
$step_filter = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$reason_filter = isset($_GET['reason']) ? sanitize_text_field($_GET['reason']) : '';
$traffic_filter = isset($_GET['traffic']) ? sanitize_text_field($_GET['traffic']) : '';

$where = "WHERE 1=1";
$args = array();
if($date_filter){ $where .= " AND DATE(v.created_at) = %s"; $args[] = $date_filter; }
if($step_filter){ $where .= " AND v.step = %s"; $args[] = $step_filter; }
if($status_filter === 'verified'){ $where .= " AND v.step = 'verified'"; }
elseif($status_filter === 'in_progress'){ $where .= $wpdb->prepare(" AND v.step != 'verified' AND v.created_at > %s", $ten_min_ago); }
elseif($status_filter === 'expired'){ $where .= $wpdb->prepare(" AND v.step != 'verified' AND v.created_at <= %s", $ten_min_ago); }
if($reason_filter === 'earned'){ $where .= " AND v.reward_paid = 1"; }
elseif($reason_filter === 'bypass'){ $where .= " AND v.is_bypass = 1"; }
elseif($reason_filter === 'change_ip'){ $where .= " AND v.ip_changed = 1"; }
elseif($reason_filter === 'max_ip'){ $where .= " AND v.ip_limit_exceeded = 1"; }
elseif($reason_filter === 'adblock'){ $where .= " AND v.adblock_detected = 1"; }
if($traffic_filter){ $where .= " AND kc.traffic_type = %s"; $args[] = $traffic_filter; }

$page_num = max(1, intval($_GET['paged'] ?? 1));
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

$wpdb->suppress_errors(true);
$count_sql = "SELECT COUNT(*) FROM {$prefix}shortlink_visits v LEFT JOIN {$prefix}keyword_campaigns kc ON kc.id=v.campaign_id $where";
$total = !empty($args) ? (int)$wpdb->get_var($wpdb->prepare($count_sql, $args)) : (int)$wpdb->get_var($count_sql);

$data_args = $args;
$data_args[] = $per_page;
$data_args[] = $offset;
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT v.*, kc.title as camp_title, kc.keyword, kc.target_url as camp_url, kc.traffic_type,
            kc.price_per_view, u.user_login, us.code as shortcode
     FROM {$prefix}shortlink_visits v
     LEFT JOIN {$prefix}keyword_campaigns kc ON kc.id = v.campaign_id
     LEFT JOIN {$wpdb->users} u ON u.ID = v.user_id
     LEFT JOIN {$prefix}user_shortlinks us ON us.id = v.shortlink_id
     $where ORDER BY v.id DESC LIMIT %d OFFSET %d", $data_args
));
if(!is_array($rows)) $rows = array();

// Stats for the date
$stats = $wpdb->get_row($wpdb->prepare(
    "SELECT COUNT(*) as total,
            SUM(CASE WHEN step='verified' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN step IN ('started','google_clicked','target_visited','code_shown') AND created_at > %s THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN step != 'verified' AND created_at <= %s THEN 1 ELSE 0 END) as expired,
            SUM(CASE WHEN is_bypass=1 THEN 1 ELSE 0 END) as bypass
     FROM {$prefix}shortlink_visits WHERE DATE(created_at) = %s", $ten_min_ago, $ten_min_ago, $date_filter
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
    <input type="hidden" name="page" value="linkngon-visits">
    <div><label style="display:block;font-size:10px;font-weight:600;color:#787c82;margin-bottom:2px">NGÀY</label><input type="date" name="date" value="<?php echo esc_attr($date_filter); ?>" style="padding:5px 8px;height:34px"></div>
    <div><label style="display:block;font-size:10px;font-weight:600;color:#787c82;margin-bottom:2px">BƯỚC</label><select name="step" style="padding:5px 8px;height:34px">
        <option value="">Tất cả</option>
        <option value="started" <?php selected($step_filter,'started'); ?>>Bắt đầu</option>
        <option value="google_clicked" <?php selected($step_filter,'google_clicked'); ?>>Click Google</option>
        <option value="target_visited" <?php selected($step_filter,'target_visited'); ?>>Đã truy cập</option>
        <option value="code_shown" <?php selected($step_filter,'code_shown'); ?>>Hiện mã</option>
        <option value="verified" <?php selected($step_filter,'verified'); ?>>Đã xác minh</option>
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
        <option value="bypass" <?php selected($reason_filter,'bypass'); ?>>Bypass</option>
        <option value="change_ip" <?php selected($reason_filter,'change_ip'); ?>>Change IP</option>
        <option value="max_ip" <?php selected($reason_filter,'max_ip'); ?>>Max IP</option>
        <option value="adblock" <?php selected($reason_filter,'adblock'); ?>>Adblock</option>
    </select></div>
    <button type="submit" class="button button-primary" style="height:34px">Lọc</button>
    <a href="?page=linkngon-visits" class="button" style="height:34px">Reset</a>
</form>

<!-- Table -->
<style>
.ln-visits-tbl th{white-space:nowrap;font-size:12px}
.ln-visits-tbl td{font-size:12px}
.ln-visits-tbl .col-kw{min-width:160px;word-break:break-word}
</style>
<div style="overflow-x:auto"><table class="widefat striped ln-visits-tbl">
<thead><tr>
    <th>Bắt đầu</th>
    <th>Kết thúc</th>
    <th>User</th>
    <th>Shortlink</th>
    <th>Nguồn</th>
    <th>Loại traffic</th>
    <th class="col-kw">Từ khóa / URL</th>
    <th>Giá KH</th>
    <th>User nhận</th>
    <th>Mã xác nhận</th>
    <th>Trạng thái</th>
    <th>Lý do</th>
    <th style="min-width:120px">IP</th>
    <th>Thiết bị</th>
</tr></thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="14">Không có dữ liệu.</td></tr>
<?php else: foreach($rows as $row):
    // Parse device
    $ua = $row->user_agent ?? '';
    $device = '—';
    if(stripos($ua,'Android')!==false) $device = 'Android';
    elseif(stripos($ua,'iPhone')!==false) $device = 'iPhone';
    elseif(stripos($ua,'Windows NT 10')!==false) $device = 'Win10/11';
    elseif(stripos($ua,'Windows')!==false) $device = 'Windows';
    elseif(stripos($ua,'Mac')!==false) $device = 'Mac';

    // Source
    $source = '—';
    $source_color = '#787c82';
    if(!empty($row->from_google)){ $source = 'Google'; $source_color = '#46b450'; }
    elseif(!empty($row->referer)){
        if(stripos($row->referer,'twitter')!==false||stripos($row->referer,'x.com')!==false){ $source = 'Twitter/X'; $source_color = '#1da1f2'; }
        elseif(stripos($row->referer,'facebook')!==false){ $source = 'Facebook'; $source_color = '#1877f2'; }
        else { $domain_ref = parse_url($row->referer, PHP_URL_HOST); $source = $domain_ref ?: 'Direct'; $source_color = '#787c82'; }
    } else { $source = 'Direct'; }

    // Traffic type
    $tt = $row->traffic_type ?? '';
    $tt_label = ['keyword_search'=>'Keyword','traffic_direct'=>'Direct','traffic_social'=>'Social'][$row->task_type ?? ''] ?? ($tt ? ucfirst($tt) : '—');
    $tt_color = '#787c82'; $tt_bg = '#f5f5f5';
    if(strpos($tt_label,'Keyword')!==false){ $tt_color='#2271b1'; $tt_bg='#e7f3ff'; }
    elseif(strpos($tt_label,'Direct')!==false){ $tt_color='#787c82'; $tt_bg='#f5f5f5'; }

    // Status
    $step = $row->step ?? 'started';
    $is_verified = ($step === 'verified');
    $is_expired = (!$is_verified && strtotime($row->created_at) < strtotime($now_vn) - 600);
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
    <td><?php echo $row->shortcode ? '<code style="padding:2px 6px;background:#e7f3ff;border-radius:3px;font-size:11px">'.esc_html($row->shortcode).'</code>' : '—'; ?></td>
    <td style="font-size:12px;color:<?php echo $source_color; ?>;font-weight:600"><?php echo esc_html($source); ?></td>
    <td><?php if($tt_label!=='—'): ?><span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:<?php echo $tt_bg; ?>;color:<?php echo $tt_color; ?>"><?php echo $tt_label; ?></span><?php else: ?>—<?php endif; ?></td>
    <td class="col-kw">
        <?php if($row->keyword): ?>
            <strong style="font-size:12px"><?php echo esc_html($row->keyword); ?></strong>
            <?php if($camp_domain): ?><br><small style="color:#787c82"><?php echo esc_html($camp_domain); ?></small><?php endif; ?>
        <?php elseif($row->camp_title): ?>
            <small><?php echo esc_html($row->camp_title); ?></small>
        <?php else: ?>—<?php endif; ?>
    </td>
    <td style="font-weight:600;color:#dc3232"><?php echo $row->price_per_view ? linkngon_format_money($row->price_per_view) : '—'; ?></td>
    <td style="font-weight:600;color:<?php echo $row->reward_paid ? '#46b450' : '#787c82'; ?>"><?php echo $row->reward_paid ? linkngon_format_money($row->reward_amount) : ($row->customer_paid ? '<span style="color:#dc3232">Chưa trả</span>' : '—'); ?></td>
    <td><code style="font-size:10px"><?php echo esc_html($row->verify_code ?? '—'); ?></code></td>
    <td><span style="display:inline-block;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:600;background:<?php echo $st_bg; ?>;color:<?php echo $st_color; ?>"><?php echo $st_label; ?></span></td>
    <td style="font-size:11px"><?php
        if ($row->reward_paid) { echo '<span style="color:#46b450;font-weight:600">Đã trả</span>'; }
        elseif (!$is_verified) { echo '—'; }
        else {
            $reasons = array();
            if (!empty($row->is_bypass)) $reasons[] = '<span style="color:#dc3232">Bypass</span>';
            if (!empty($row->ip_changed)) $reasons[] = '<span style="color:#dc3232">Đổi IP</span>';
            if (!empty($row->ip_limit_exceeded)) $reasons[] = '<span style="color:#dc3232">IP limit</span>';
            if (!empty($row->adblock_detected)) $reasons[] = '<span style="color:#dc3232">Adblock</span>';
            if (!$row->customer_paid) $reasons[] = '<span style="color:#856404">KH chưa trả</span>';
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
    $pag_params = array('page'=>'linkngon-visits');
    if($date_filter) $pag_params['date'] = $date_filter;
    if($step_filter) $pag_params['step'] = $step_filter;
    if($status_filter) $pag_params['status'] = $status_filter;
    if($reason_filter) $pag_params['reason'] = $reason_filter;
    if($traffic_filter) $pag_params['traffic'] = $traffic_filter;
?>
<div class="tablenav bottom"><div class="tablenav-pages">
    <span style="font-size:12px;color:#787c82;margin-right:10px">Trang <?php echo $page_num; ?>/<?php echo $total_pages; ?> (<?php echo number_format($total); ?> kết quả)</span>
    <?php if($page_num > 1): ?><a class="button" href="?<?php echo http_build_query(array_merge($pag_params, array('paged'=>$page_num-1))); ?>">« Trước</a><?php endif; ?>
    <?php if($page_num < $total_pages): ?><a class="button" href="?<?php echo http_build_query(array_merge($pag_params, array('paged'=>$page_num+1))); ?>">Sau »</a><?php endif; ?>
</div></div>
<?php endif; ?>

</div>
