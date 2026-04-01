<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Handle actions
if(isset($_POST['link_action']) && wp_verify_nonce($_POST['_wpnonce'],'linkngon_link_action')){
    $link_id = intval($_POST['link_id'] ?? 0);
    $action = sanitize_text_field($_POST['link_action']);
    if($action === 'delete' && $link_id){
        $wpdb->delete($prefix.'user_shortlinks', ['id'=>$link_id]);
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
$sl_active = isset($counts['active']) ? (int)$counts['active']->cnt : 0;
$today = date('Y-m-d', strtotime(linkngon_current_time()));
$sl_today = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE step='verified' AND DATE(created_at)=%s", $today));
$sl_clicks = (int) $wpdb->get_var("SELECT COALESCE(SUM(total_clicks),0) FROM {$prefix}user_shortlinks");
$sl_completed = (int) $wpdb->get_var("SELECT COALESCE(SUM(total_completed),0) FROM {$prefix}user_shortlinks");
?>
<style>
.sl-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:16px}
.sl-stat{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:14px}
.sl-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px}
.sl-icon.i1{background:#dbeafe;color:#2563eb} .sl-icon.i2{background:#d1fae5;color:#059669}
.sl-icon.i3{background:#ede9fe;color:#7c3aed} .sl-icon.i4{background:#fef3c7;color:#d97706}
.sl-icon.i5{background:#fce7f3;color:#db2777}
.sl-val{font-size:22px;font-weight:700;color:#1d2327;line-height:1.2}
.sl-label{font-size:12px;color:#6b7280}
@media(max-width:600px){.sl-stats{grid-template-columns:repeat(2,1fr)}}
</style>
<div class="sl-stats">
    <div class="sl-stat"><div class="sl-icon i1">&#x1F517;</div><div><div class="sl-val"><?php echo number_format($sl_total); ?></div><div class="sl-label">Tổng link</div></div></div>
    <div class="sl-stat"><div class="sl-icon i2">&#x2705;</div><div><div class="sl-val"><?php echo number_format($sl_active); ?></div><div class="sl-label">Link hoạt động</div></div></div>
    <div class="sl-stat"><div class="sl-icon i3">&#x1F4C5;</div><div><div class="sl-val"><?php echo number_format($sl_today); ?></div><div class="sl-label">Hoàn thành hôm nay</div></div></div>
    <div class="sl-stat"><div class="sl-icon i4">&#x1F441;</div><div><div class="sl-val"><?php echo number_format($sl_clicks); ?></div><div class="sl-label">Tổng clicks</div></div></div>
    <div class="sl-stat"><div class="sl-icon i5">&#x2714;</div><div><div class="sl-val"><?php echo number_format($sl_completed); ?></div><div class="sl-label">Tổng hoàn thành</div></div></div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px">
    <ul class="subsubsub" style="margin:0;float:none">
        <li><a href="?page=linkngon-links" <?php echo !$status_filter?'class="current"':''; ?>>Tất cả <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
        <?php foreach(['active','disabled'] as $s): ?>
        <li><a href="?page=linkngon-links&status=<?php echo $s; ?>" <?php echo $status_filter===$s?'class="current"':''; ?>><?php echo $status_labels[$s]; ?> <span class="count">(<?php echo isset($counts[$s]) ? $counts[$s]->cnt : 0; ?>)</span></a><?php echo $s!=='disabled'?' |':''; ?></li>
        <?php endforeach; ?>
    </ul>
    <form method="get" style="display:flex;gap:6px;align-items:center">
        <input type="hidden" name="page" value="linkngon-links">
        <?php if($status_filter): ?><input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>"><?php endif; ?>
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Tìm mã, alias, URL, user..." style="padding:6px 10px;min-width:200px">
        <input type="submit" class="button" value="Tìm kiếm">
    </form>
</div>

<div style="overflow-x:auto"><table class="widefat striped">
<thead>
<tr>
    <th>ID</th>
    <th>Shortlink</th>
    <th>URL gốc</th>
    <th>Link dự phòng</th>
    <th>Người dùng</th>
    <th>Clicks</th>
    <th>Hoàn thành</th>
    <th>Kiếm được</th>
    <th>Trạng thái</th>
    <th>Ngày tạo</th>
    <th>Hành động</th>
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
    <td><a href="<?php echo esc_url($row->original_url); ?>" target="_blank" style="font-size:12px" title="<?php echo esc_attr($row->original_url); ?>"><?php echo esc_html(mb_strimwidth($row->original_url, 0, 40, '...')); ?></a></td>
    <td style="font-size:12px"><?php echo !empty($row->fallback_url) ? '<a href="'.esc_url($row->fallback_url).'" target="_blank" title="'.esc_attr($row->fallback_url).'">'.esc_html(mb_strimwidth($row->fallback_url, 0, 30, '...')).'</a>' : '<span style="color:#ccc">—</span>'; ?></td>
    <td><?php echo esc_html($row->user_login ?? 'User #'.$row->user_id); ?></td>
    <td style="font-weight:600"><?php echo intval($row->total_clicks); ?></td>
    <td style="font-weight:600"><?php echo intval($row->total_completed); ?></td>
    <td style="font-weight:600;color:<?php echo $row->total_earnings > 0 ? '#46b450' : '#82878c'; ?>"><?php echo linkngon_format_money($row->total_earnings); ?></td>
    <td><span style="color:<?php echo $color; ?>;font-weight:bold;"><?php echo $status_labels[$row->status] ?? ucfirst($row->status); ?></span></td>
    <td style="font-size:12px"><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
    <td style="white-space:nowrap">
        <form method="post" style="display:inline"><?php wp_nonce_field('linkngon_link_action'); ?><input type="hidden" name="link_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="link_action" value="toggle" class="button button-small" title="<?php echo $row->status==='active'?'Vô hiệu':'Kích hoạt'; ?>"><?php echo $row->status==='active'?'Tắt':'Bật'; ?></button>
            <button type="submit" name="link_action" value="delete" class="button button-small" style="color:#dc3232" onclick="return confirm('Xóa shortlink này?')">Xóa</button>
        </form>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table></div>

<?php if($total_pages > 1): ?>
<div class="tablenav bottom">
    <div class="tablenav-pages">
        <?php for($i=1;$i<=$total_pages;$i++): ?>
            <?php if($i===$page_num): ?>
                <span class="tablenav-pages-navspan button disabled"><?php echo $i; ?></span>
            <?php else: ?>
                <a class="button" href="?page=linkngon-links<?php echo $status_filter?"&status=$status_filter":""; ?><?php echo $search?"&s=".urlencode($search):""; ?>&paged=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

</div>
