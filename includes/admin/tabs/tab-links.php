<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Filters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

$where = "WHERE 1=1";
if($status_filter) $where .= $wpdb->prepare(" AND sl.status = %s", $status_filter);
if($search){
    $where .= $wpdb->prepare(" AND (sl.code LIKE %s OR sl.alias LIKE %s OR sl.original_url LIKE %s OR u.user_login LIKE %s)",
        '%'.$search.'%', '%'.$search.'%', '%'.$search.'%', '%'.$search.'%');
}

$page_num = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

$total = $wpdb->get_var(
    "SELECT COUNT(*)
     FROM {$prefix}user_shortlinks sl
     LEFT JOIN {$wpdb->users} u ON u.ID = sl.user_id
     $where"
);

$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT sl.*, u.user_login, u.display_name
     FROM {$prefix}user_shortlinks sl
     LEFT JOIN {$wpdb->users} u ON u.ID = sl.user_id
     $where
     ORDER BY sl.id DESC
     LIMIT %d OFFSET %d", $per_page, $offset
));

$total_pages = ceil($total / $per_page);
$counts = $wpdb->get_results("SELECT status, COUNT(*) as cnt FROM {$prefix}user_shortlinks GROUP BY status", OBJECT_K);
?>
<div class="wrap">
<h1>Shortlinks</h1>

<form method="get" style="float:right;margin-bottom:10px;">
    <input type="hidden" name="page" value="linkngon-admin">
    <input type="hidden" name="tab" value="links">
    <?php if($status_filter): ?><input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>"><?php endif; ?>
    <p class="search-box">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search code, alias, URL, user...">
        <input type="submit" class="button" value="Search">
    </p>
</form>

<ul class="subsubsub">
    <li><a href="?page=linkngon-admin&tab=links" <?php echo !$status_filter?'class="current"':''; ?>>All <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
    <?php foreach(['active','disabled'] as $s): ?>
    <li><a href="?page=linkngon-admin&tab=links&status=<?php echo $s; ?>" <?php echo $status_filter===$s?'class="current"':''; ?>><?php echo ucfirst($s); ?> <span class="count">(<?php echo isset($counts[$s]) ? $counts[$s]->cnt : 0; ?>)</span></a><?php echo $s!=='disabled'?' |':''; ?></li>
    <?php endforeach; ?>
</ul>
<br class="clear">

<table class="widefat striped">
<thead>
<tr>
    <th>ID</th>
    <th>Code</th>
    <th>Alias</th>
    <th>Original URL</th>
    <th>User</th>
    <th>Clicks</th>
    <th>Completed</th>
    <th>Earnings</th>
    <th>Status</th>
    <th>Created</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="10">No shortlinks found.</td></tr>
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
    <td><span style="color:<?php echo $color; ?>;font-weight:bold;"><?php echo ucfirst($row->status); ?></span></td>
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
                <a class="button" href="?page=linkngon-admin&tab=links<?php echo $status_filter?"&status=$status_filter":""; ?><?php echo $search?"&s=".urlencode($search):""; ?>&paged=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

</div>
