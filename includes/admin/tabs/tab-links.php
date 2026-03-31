<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

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

<table class="widefat striped">
<thead>
<tr>
    <th>ID</th>
    <th>Mã</th>
    <th>Alias</th>
    <th>URL gốc</th>
    <th>Người dùng</th>
    <th>Lượt click</th>
    <th>Hoàn thành</th>
    <th>Thu nhập</th>
    <th>Trạng thái</th>
    <th>Ngày tạo</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="10">Không có dữ liệu.</td></tr>
<?php else: foreach($rows as $row):
    $color = $row->status === 'active' ? '#46b450' : '#82878c';
?>
<tr>
    <td><?php echo intval($row->id); ?></td>
    <td><code><?php echo esc_html($row->code); ?></code></td>
    <td><?php echo $row->alias ? '<code>'.esc_html($row->alias).'</code>' : '---'; ?></td>
    <td><a href="<?php echo esc_url($row->original_url); ?>" target="_blank" title="<?php echo esc_attr($row->original_url); ?>"><?php echo esc_html(mb_strimwidth($row->original_url, 0, 50, '...')); ?></a></td>
    <td><?php echo esc_html($row->user_login ?? 'User #'.$row->user_id); ?></td>
    <td><?php echo intval($row->total_clicks); ?></td>
    <td><?php echo intval($row->total_completed); ?></td>
    <td><?php echo linkngon_format_money($row->total_earnings); ?></td>
    <td><span style="color:<?php echo $color; ?>;font-weight:bold;"><?php echo $status_labels[$row->status] ?? ucfirst($row->status); ?></span></td>
    <td><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>

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
