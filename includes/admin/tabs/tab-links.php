<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'traffictop_';

// Handle actions
if(isset($_POST['link_action']) && wp_verify_nonce($_POST['_wpnonce'],'traffictop_link_action')){
    $link_id = intval($_POST['link_id'] ?? 0);
    $action = sanitize_text_field($_POST['link_action']);
    if($action === 'delete' && $link_id){
        // Soft delete - preserve for financial audit trail (visits, earnings)
        $wpdb->update($prefix.'user_shortlinks', ['status'=>'deleted'], ['id'=>$link_id]);
        echo '<div class="notice notice-warning is-dismissible"><p>Đã xóa shortlink #'.$link_id.'</p></div>';
    } elseif($action === 'toggle' && $link_id){
        $current = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$prefix}user_shortlinks WHERE id=%d", $link_id));
        $new = ($current === 'active') ? 'disabled' : 'active';
        $wpdb->update($prefix.'user_shortlinks', ['status'=>$new], ['id'=>$link_id]);
        echo '<div class="notice notice-success is-dismissible"><p>Shortlink #'.$link_id.' đã '.($new==='active'?'kích hoạt':'vô hiệu').'</p></div>';
    }
}

// Filters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

$where = "WHERE 1=1";
$args = array();
if($status_filter) {
    $where .= " AND sl.status = %s";
    $args[] = $status_filter;
} else {
    // Hide deleted shortlinks by default
    $where .= " AND sl.status != 'deleted'";
}
if($search){
    $where .= " AND (sl.code LIKE %s OR sl.alias LIKE %s OR sl.original_url LIKE %s OR u.user_login LIKE %s)";
    $args[] = '%'.$search.'%';
    $args[] = '%'.$search.'%';
    $args[] = '%'.$search.'%';
    $args[] = '%'.$search.'%';
}

$page_num = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

$count_sql = "SELECT COUNT(*) FROM {$prefix}user_shortlinks sl LEFT JOIN {$wpdb->users} u ON u.ID = sl.user_id $where";
$total = !empty($args) ? $wpdb->get_var($wpdb->prepare($count_sql, $args)) : $wpdb->get_var($count_sql);

$args[] = $per_page;
$args[] = $offset;
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT sl.*, u.user_login, u.display_name
     FROM {$prefix}user_shortlinks sl
     LEFT JOIN {$wpdb->users} u ON u.ID = sl.user_id
     $where
     ORDER BY sl.id DESC
     LIMIT %d OFFSET %d", $args
));

$total_pages = ceil($total / $per_page);
$counts = $wpdb->get_results("SELECT status, COUNT(*) as cnt FROM {$prefix}user_shortlinks GROUP BY status", OBJECT_K);

$status_labels = [
    'active' => 'Hoạt động',
    'disabled' => 'Tắt',
];
?>
<div class="wrap">
<h1>Shortlink</h1>

<?php
$sl_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}user_shortlinks");
$today = date('Y-m-d', strtotime(traffictop_current_time()));
$sl_created_today = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}user_shortlinks WHERE DATE(created_at)=%s", $today));
$sl_load_today = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE DATE(created_at)=%s", $today));
$month_start = date('Y-m-01', strtotime(traffictop_current_time()));
$sl_load_month = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE created_at >= %s", $month_start));
?>
<style>
.sl-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
.sl-stat{border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px}
.sl-stat.ss1{background:#eff6ff;border:2px solid #bfdbfe} .sl-stat.ss2{background:#fef2f2;border:2px solid #fecaca}
.sl-stat.ss3{background:#eff6ff;border:2px solid #bfdbfe} .sl-stat.ss4{background:#fffbeb;border:2px solid #fde68a}
.sl-val{font-size:22px;font-weight:700;line-height:1.2}
.sl-stat.ss1 .sl-val{color:#1e40af} .sl-stat.ss2 .sl-val{color:#991b1b}
.sl-stat.ss3 .sl-val{color:#1e40af} .sl-stat.ss4 .sl-val{color:#92400e}
.sl-label{font-size:12px;color:#6b7280}
.sl-ico{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center}
.sl-ico.si1{background:#dbeafe;color:#2563eb} .sl-ico.si2{background:#fecaca;color:#dc2626}
.sl-ico.si3{background:#dbeafe;color:#2563eb} .sl-ico.si4{background:#fde68a;color:#d97706}
@media(max-width:600px){.sl-stats{grid-template-columns:repeat(2,1fr)} .sl-val{font-size:16px} .sl-stat{padding:12px 14px} .sl-ico{width:38px;height:38px} .sl-ico svg{width:20px;height:20px}}
.sl-tbl th{white-space:nowrap;font-size:13px} .sl-tbl td{font-size:13px}
.sl-tbl .col-url td,.sl-tbl td.col-url-td{max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sl-tbl .col-status,.sl-tbl .col-status span,.sl-tbl td.col-date{white-space:nowrap}
@media(max-width:600px){.sl-tbl th,.sl-tbl td{padding:4px 5px}
.sl-tbl .col-id{width:30px;text-align:center}
.sl-tbl .col-url td,.sl-tbl td.col-url-td{max-width:140px}
.sl-tbl .col-num{white-space:nowrap;text-align:right}
.sl-tbl .button-small{font-size:11px;padding:2px 6px;min-height:auto;line-height:1.4}
}
</style>
<div class="sl-stats">
    <div class="sl-stat ss1"><div><div class="sl-val"><?php echo number_format($sl_total); ?></div><div class="sl-label">Tổng link</div></div><div class="sl-ico si1"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg></div></div>
    <div class="sl-stat ss2"><div><div class="sl-val"><?php echo number_format($sl_created_today); ?></div><div class="sl-label">Tạo hôm nay</div></div><div class="sl-ico si2"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg></div></div>
    <div class="sl-stat ss3"><div><div class="sl-val"><?php echo number_format($sl_load_today); ?></div><div class="sl-label">Load hôm nay</div></div><div class="sl-ico si3"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div></div>
    <div class="sl-stat ss4"><div><div class="sl-val"><?php echo number_format($sl_load_month); ?></div><div class="sl-label">Load tháng này</div></div><div class="sl-ico si4"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div></div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px">
    <ul class="subsubsub" style="margin:0;float:none">
        <li><a href="?page=traffictop-links" <?php echo !$status_filter?'class="current"':''; ?>>Tất cả <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
        <?php foreach(['active','disabled'] as $s): ?>
        <li><a href="?page=traffictop-links&status=<?php echo $s; ?>" <?php echo $status_filter===$s?'class="current"':''; ?>><?php echo $status_labels[$s]; ?> <span class="count">(<?php echo isset($counts[$s]) ? $counts[$s]->cnt : 0; ?>)</span></a><?php echo $s!=='disabled'?' |':''; ?></li>
        <?php endforeach; ?>
    </ul>
    <form method="get" style="display:flex;gap:6px;align-items:center">
        <input type="hidden" name="page" value="traffictop-links">
        <?php if($status_filter): ?><input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>"><?php endif; ?>
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Tìm mã, alias, URL, user..." style="padding:0 10px;min-width:200px;-webkit-appearance:textfield">
        <input type="submit" class="button" value="Tìm kiếm">
    </form>
</div>

<div style="overflow-x:auto"><table class="widefat striped sl-tbl">
<thead>
<tr>
    <th class="col-id">ID</th>
    <th>Shortlink</th>
    <th class="col-url">URL gốc</th>
    <th class="col-url">Link dự phòng</th>
    <th>User</th>
    <th class="col-num">Clicks</th>
    <th class="col-num">Hoàn thành</th>
    <th class="col-num">Kiếm được</th>
    <th class="col-status">Trạng thái</th>
    <th>Ngày tạo</th>
    <th class="col-actions">Thao tác</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="11">Không có dữ liệu.</td></tr>
<?php else: foreach($rows as $row):
    $color = $row->status === 'active' ? '#46b450' : '#82878c';
?>
<tr>
    <?php $short_url = home_url('/' . ($row->alias ?: $row->code)); ?>
    <td><?php echo intval($row->id); ?></td>
    <td><a href="<?php echo esc_url($short_url); ?>" target="_blank" style="font-family:monospace;font-size:12px;color:#0073aa"><?php echo esc_html($short_url); ?></a></td>
    <td class="col-url-td"><a href="<?php echo esc_url($row->original_url); ?>" target="_blank" style="font-size:12px" title="<?php echo esc_attr($row->original_url); ?>"><?php echo esc_html($row->original_url); ?></a></td>
    <td class="col-url-td" style="font-size:12px"><?php echo !empty($row->fallback_url) ? '<a href="'.esc_url($row->fallback_url).'" target="_blank" title="'.esc_attr($row->fallback_url).'">'.esc_html($row->fallback_url).'</a>' : '<span style="color:#ccc">—</span>'; ?></td>
    <td><?php echo esc_html($row->user_login ?? 'User #'.$row->user_id); ?></td>
    <td style="font-weight:600"><?php echo intval($row->total_clicks); ?></td>
    <td style="font-weight:600"><?php echo intval($row->total_completed); ?></td>
    <td style="font-weight:600;color:<?php echo $row->total_earnings > 0 ? '#46b450' : '#82878c'; ?>"><?php echo traffictop_format_money($row->total_earnings); ?></td>
    <td class="col-status"><span style="color:<?php echo $color; ?>;font-weight:bold;"><?php echo $status_labels[$row->status] ?? ucfirst($row->status); ?></span></td>
    <td class="col-date" style="font-size:12px"><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
    <td class="col-actions" style="white-space:nowrap">
        <form method="post" style="display:inline"><?php wp_nonce_field('traffictop_link_action'); ?><input type="hidden" name="link_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="link_action" value="toggle" class="button button-small" title="<?php echo $row->status==='active'?'Vô hiệu':'Kích hoạt'; ?>"><?php echo $row->status==='active'?'Tắt':'Bật'; ?></button>
            <button type="submit" name="link_action" value="delete" class="button button-small" style="color:#dc3232" onclick="return confirm('Xóa shortlink này?')">Xóa</button>
        </form>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table></div>

<?php if($total_pages > 1):
    $pag_params = array('page' => 'traffictop-links');
    if($status_filter) $pag_params['status'] = $status_filter;
    if($search) $pag_params['s'] = $search;
?>
<div class="tablenav bottom"><div class="tablenav-pages">
    <span style="font-size:12px;color:#787c82;margin-right:10px">
        Trang <?php echo $page_num; ?>/<?php echo $total_pages; ?>
        (<?php echo number_format($total); ?> kết quả)
    </span>
    <?php if($page_num > 1): ?>
        <a class="button" href="?<?php echo esc_attr(http_build_query(array_merge($pag_params, array('paged'=>$page_num-1)))); ?>">« Trước</a>
    <?php endif; ?>
    <?php if($page_num < $total_pages): ?>
        <a class="button" href="?<?php echo esc_attr(http_build_query(array_merge($pag_params, array('paged'=>$page_num+1)))); ?>">Sau »</a>
    <?php endif; ?>
</div></div>
<?php endif; ?>

</div>
