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

<form method="get" style="float:right;margin-bottom:10px;">
    <input type="hidden" name="page" value="linkngon-links">
    <?php if($status_filter): ?><input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>"><?php endif; ?>
    <p class="search-box">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Tìm mã, alias, URL, user...">
        <input type="submit" class="button" value="Tìm kiếm">
    </p>
</form>

<ul class="subsubsub">
    <li><a href="?page=linkngon-links" <?php echo !$status_filter?'class="current"':''; ?>>Tất cả <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
    <?php foreach(['active','disabled'] as $s): ?>
    <li><a href="?page=linkngon-links&status=<?php echo $s; ?>" <?php echo $status_filter===$s?'class="current"':''; ?>><?php echo $status_labels[$s]; ?> <span class="count">(<?php echo isset($counts[$s]) ? $counts[$s]->cnt : 0; ?>)</span></a><?php echo $s!=='disabled'?' |':''; ?></li>
    <?php endforeach; ?>
</ul>
<br class="clear">

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
