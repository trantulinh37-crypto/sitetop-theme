<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Handle actions
if(isset($_POST['user_action']) && wp_verify_nonce($_POST['_wpnonce'],'linkngon_user_action')){
    $target_user_id = intval($_POST['target_user_id']);
    $action = sanitize_text_field($_POST['user_action']);
    if($action === 'ban'){
        update_user_meta($target_user_id, 'linkngon_banned', true);
        echo '<div class="notice notice-warning is-dismissible"><p>Đã cấm user #'.$target_user_id.'</p></div>';
    } elseif($action === 'unban'){
        delete_user_meta($target_user_id, 'linkngon_banned');
        echo '<div class="notice notice-success is-dismissible"><p>Đã bỏ cấm user #'.$target_user_id.'</p></div>';
    }
}

$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

$search_sql = '';
$search_args = array();
if($search){
    $like = '%' . $wpdb->esc_like($search) . '%';
    $search_sql = " AND (u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)";
    $search_args = array($like, $like, $like);
}

$cap_key = $wpdb->prefix . 'capabilities';

$count_query = "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
    INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = %s
    WHERE (um.meta_value LIKE %s OR um.meta_value LIKE %s) {$search_sql}";
$count_args = array_merge(array($cap_key, '%subscriber%', '%administrator%'), $search_args);
$total = $wpdb->get_var($wpdb->prepare($count_query, $count_args));

$per_page = 20;
$page_num = max(1, intval($_GET['paged'] ?? 1));
$offset = ($page_num - 1) * $per_page;

$data_query = "SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
        ub.balance, ub.total_earned,
        (SELECT COUNT(*) FROM {$prefix}user_shortlinks WHERE user_id = u.ID) as total_links
     FROM {$wpdb->users} u
     INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = %s
     LEFT JOIN {$prefix}user_balance ub ON ub.user_id = u.ID
     WHERE (um.meta_value LIKE %s OR um.meta_value LIKE %s) {$search_sql}
     ORDER BY u.ID DESC LIMIT %d OFFSET %d";
$data_args = array_merge(array($cap_key, '%subscriber%', '%administrator%'), $search_args, array($per_page, $offset));
$rows = $wpdb->get_results($wpdb->prepare($data_query, $data_args));

$total_pages = ceil($total / $per_page);
$base_url = 'admin.php?page=linkngon-users';
?>
<div class="wrap">
<h1>Người dùng</h1>

<form method="get" style="margin-bottom:10px;">
    <input type="hidden" name="page" value="linkngon-users">
    <p class="search-box">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Tìm username, email...">
        <input type="submit" class="button" value="Tìm kiếm">
    </p>
</form>
<br class="clear">

<p>Tổng: <strong><?php echo intval($total); ?></strong> người dùng</p>

<table class="widefat striped">
<thead>
<tr>
    <th>ID</th>
    <th>Tên đăng nhập</th>
    <th>Email</th>
    <th>Điện thoại</th>
    <th>Số dư</th>
    <th>Tổng thu nhập</th>
    <th>Số links</th>
    <th>Trạng thái</th>
    <th>Ngày ĐK</th>
    <th>Thao tác</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="10">Không có người dùng nào.</td></tr>
<?php else: foreach($rows as $row):
    $is_banned = get_user_meta($row->ID, 'linkngon_banned', true);
    $phone = get_user_meta($row->ID, 'phone', true);
?>
<tr>
    <td><?php echo $row->ID; ?></td>
    <td><strong><?php echo esc_html($row->user_login); ?></strong><br><small><?php echo esc_html($row->display_name); ?></small></td>
    <td><?php echo esc_html($row->user_email); ?></td>
    <td><?php echo esc_html($phone ?: '—'); ?></td>
    <td><?php echo linkngon_format_money($row->balance ?? 0); ?></td>
    <td><?php echo linkngon_format_money($row->total_earned ?? 0); ?></td>
    <td><?php echo intval($row->total_links); ?></td>
    <td>
        <?php if($is_banned): ?>
            <span style="color:#dc3232;font-weight:600">Đã cấm</span>
        <?php else: ?>
            <span style="color:#46b450;font-weight:600">Hoạt động</span>
        <?php endif; ?>
    </td>
    <td><?php echo date('d/m/Y', strtotime($row->user_registered)); ?></td>
    <td>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_user_action'); ?>
            <input type="hidden" name="target_user_id" value="<?php echo $row->ID; ?>">
            <?php if($is_banned): ?>
                <button type="submit" name="user_action" value="unban" class="button button-small button-primary">Bỏ cấm</button>
            <?php else: ?>
                <button type="submit" name="user_action" value="ban" class="button button-small" onclick="return confirm('Cấm người dùng này?')">Cấm</button>
            <?php endif; ?>
        </form>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>

<?php if($total_pages > 1): ?>
<div class="tablenav bottom"><div class="tablenav-pages">
<?php for($i=1;$i<=$total_pages;$i++):
    $url = add_query_arg(array('page'=>'linkngon-users','s'=>$search,'paged'=>$i), admin_url('admin.php'));
    if($i===$page_num): ?><span class="tablenav-pages-navspan button disabled"><?php echo $i; ?></span>
    <?php else: ?><a class="button" href="<?php echo esc_url($url); ?>"><?php echo $i; ?></a>
<?php endif; endfor; ?>
</div></div>
<?php endif; ?>
</div>
