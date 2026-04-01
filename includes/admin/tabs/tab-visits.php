<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';
$today = date('Y-m-d', strtotime(linkngon_current_time()));
$date_filter = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : $today;
$step_filter = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : '';

$where = "WHERE 1=1";
$args = array();
if($date_filter){ $where .= " AND DATE(v.created_at) = %s"; $args[] = $date_filter; }
if($step_filter){ $where .= " AND v.step = %s"; $args[] = $step_filter; }

$page_num = max(1, intval($_GET['paged'] ?? 1));
$per_page = 50;
$offset = ($page_num - 1) * $per_page;

$wpdb->suppress_errors(true);
$count_sql = "SELECT COUNT(*) FROM {$prefix}shortlink_visits v $where";
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
            SUM(CASE WHEN step IN ('started','google_clicked','target_visited','code_shown') AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE) THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN step != 'verified' AND created_at <= DATE_SUB(NOW(), INTERVAL 10 MINUTE) THEN 1 ELSE 0 END) as expired,
            SUM(CASE WHEN is_bypass=1 THEN 1 ELSE 0 END) as bypass
     FROM {$prefix}shortlink_visits WHERE DATE(created_at) = %s", $date_filter
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
<form method="get" style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
    <input type="hidden" name="page" value="linkngon-visits">
    <input type="date" name="date" value="<?php echo esc_attr($date_filter); ?>" style="padding:5px 8px">
    <select name="step" style="padding:5px 8px">
        <option value="">Tất cả</option>
        <?php foreach(['started','google_clicked','target_visited','code_shown','verified'] as $s): ?>
        <option value="<?php echo $s; ?>" <?php selected($step_filter, $s); ?>><?php echo $s; ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="button">Lọc</button>
</form>

<!-- Table -->
<div style="overflow-x:auto"><table class="widefat striped">
<thead><tr>
    <th>Bắt đầu</th>
    <th>Kết thúc</th>
    <th>User</th>
    <th>Shortlink</th>
    <th>Nguồn</th>
    <th>Loại traffic</th>
    <th>Từ khóa / URL</th>
    <th>Giá</th>
    <th>Mã xác nhận</th>
    <th>Trạng thái</th>
    <th>IP</th>
    <th>Thiết bị</th>
</tr></thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="12">Không có dữ liệu.</td></tr>
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
    $is_expired = (!$is_verified && strtotime($row->created_at) < time() - 600);
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
    <td>
        <?php if($row->keyword): ?>
            <strong style="font-size:12px"><?php echo esc_html(mb_strimwidth($row->keyword, 0, 20, '...')); ?></strong>
            <?php if($camp_domain): ?><br><small style="color:#787c82"><?php echo esc_html($camp_domain); ?></small><?php endif; ?>
        <?php elseif($row->camp_title): ?>
            <small><?php echo esc_html(mb_strimwidth($row->camp_title, 0, 20, '...')); ?></small>
        <?php else: ?>—<?php endif; ?>
    </td>
    <td style="font-weight:600;color:<?php echo $row->reward_paid ? '#46b450' : '#787c82'; ?>"><?php echo $row->reward_paid ? linkngon_format_money($row->reward_amount) : '—'; ?></td>
    <td><code style="font-size:10px"><?php echo esc_html($row->verify_code ?? '—'); ?></code></td>
    <td><span style="display:inline-block;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:600;background:<?php echo $st_bg; ?>;color:<?php echo $st_color; ?>"><?php echo $st_label; ?></span></td>
    <td><code style="font-size:10px"><?php echo esc_html(mb_strimwidth($row->ip_address ?? '', 0, 18, '...')); ?></code><?php if(!empty($row->ip_changed)): ?><br><small style="color:#dc3232">Đã đổi</small><?php endif; ?></td>
    <td style="font-size:11px"><?php echo esc_html($device); ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table></div>

<?php if($total_pages > 1): ?>
<div class="tablenav bottom"><div class="tablenav-pages">
    <span style="font-size:12px;color:#787c82;margin-right:10px">Trang <?php echo $page_num; ?>/<?php echo $total_pages; ?></span>
    <?php if($page_num > 1): ?><a class="button" href="?page=linkngon-visits&date=<?php echo $date_filter; ?><?php echo $step_filter?"&step=$step_filter":""; ?>&paged=<?php echo $page_num-1; ?>">« Trước</a><?php endif; ?>
    <?php if($page_num < $total_pages): ?><a class="button" href="?page=linkngon-visits&date=<?php echo $date_filter; ?><?php echo $step_filter?"&step=$step_filter":""; ?>&paged=<?php echo $page_num+1; ?>">Sau »</a><?php endif; ?>
</div></div>
<?php endif; ?>

</div>
