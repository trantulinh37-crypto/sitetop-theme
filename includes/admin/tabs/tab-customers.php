<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Handle actions
if(isset($_POST['customer_action']) && wp_verify_nonce($_POST['_wpnonce'],'linkngon_customer_action')){
    $target_id = intval($_POST['target_customer_id']);
    $action = sanitize_text_field($_POST['customer_action']);

    if($action === 'ban'){
        update_user_meta($target_id, 'customer_banned', true);
        echo '<div class="notice notice-warning"><p>Khách hàng #'.$target_id.' đã bị cấm.</p></div>';
    } elseif($action === 'unban'){
        delete_user_meta($target_id, 'customer_banned');
        echo '<div class="notice notice-success"><p>Khách hàng #'.$target_id.' đã được bỏ cấm.</p></div>';
    }
}

// Search
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$page_num = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

$search_sql = '';
$search_args = array();
if($search){
    $like = '%' . $wpdb->esc_like($search) . '%';
    $search_sql = " AND (u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)";
    $search_args = array($like, $like, $like);
}

$cap_key = $wpdb->prefix . 'capabilities';

$count_q = "SELECT COUNT(DISTINCT u.ID)
     FROM {$wpdb->users} u
     INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = %s
     WHERE um.meta_value LIKE %s {$search_sql}";
$count_args = array_merge(array($cap_key, '%customer%'), $search_args);
$total = $wpdb->get_var($wpdb->prepare($count_q, $count_args));

$data_q = "SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
            cb.balance, cb.total_deposited, cb.total_spent,
            (SELECT COUNT(*) FROM {$prefix}keyword_campaigns WHERE customer_id = u.ID AND status = 'active') as active_campaigns
     FROM {$wpdb->users} u
     INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = %s
     LEFT JOIN {$prefix}customer_balance cb ON cb.user_id = u.ID
     WHERE um.meta_value LIKE %s {$search_sql}
     ORDER BY u.ID DESC LIMIT %d OFFSET %d";
$data_args = array_merge(array($cap_key, '%customer%'), $search_args, array($per_page, $offset));
$rows = $wpdb->get_results($wpdb->prepare($data_q, $data_args));

$total_pages = ceil($total / $per_page);
?>
<div class="wrap">
<h1>Khách hàng (Nhà quảng cáo)</h1>

<form method="get" style="margin-bottom:10px;">
    <input type="hidden" name="page" value="linkngon-customers">
    <p class="search-box">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Tìm tên đăng nhập, email...">
        <input type="submit" class="button" value="Tìm kiếm">
    </p>
</form>
<br class="clear">

<p>Tổng: <strong><?php echo intval($total); ?></strong> khách hàng</p>

<table class="widefat striped">
<thead>
<tr>
    <th>ID</th>
    <th>Tên đăng nhập</th>
    <th>Email</th>
    <th>Số dư</th>
    <th>Tổng nạp</th>
    <th>Tổng chi</th>
    <th>Chiến dịch hoạt động</th>
    <th>Trạng thái</th>
    <th>Ngày đăng ký</th>
    <th>Thao tác</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="10">Không có dữ liệu.</td></tr>
<?php else: foreach($rows as $row):
    $is_banned = get_user_meta($row->ID, 'customer_banned', true);
?>
<tr>
    <td><?php echo intval($row->ID); ?></td>
    <td><strong><?php echo esc_html($row->user_login); ?></strong></td>
    <td><?php echo esc_html($row->user_email); ?></td>
    <td><strong><?php echo linkngon_format_money($row->balance ?? 0); ?></strong></td>
    <td><?php echo linkngon_format_money($row->total_deposited ?? 0); ?></td>
    <td><?php echo linkngon_format_money($row->total_spent ?? 0); ?></td>
    <td><?php echo intval($row->active_campaigns); ?></td>
    <td>
        <?php if($is_banned): ?>
            <span style="color:#dc3232;font-weight:bold;">Đã cấm</span>
        <?php else: ?>
            <span style="color:#46b450;font-weight:bold;">Hoạt động</span>
        <?php endif; ?>
    </td>
    <td><?php echo date('d/m/Y H:i', strtotime($row->user_registered)); ?></td>
    <td>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_customer_action'); ?>
            <input type="hidden" name="target_customer_id" value="<?php echo $row->ID; ?>">
            <?php if($is_banned): ?>
                <button type="submit" name="customer_action" value="unban" class="button button-small button-primary">Bỏ cấm</button>
            <?php else: ?>
                <button type="submit" name="customer_action" value="ban" class="button button-small" onclick="return confirm('Cấm khách hàng này?')">Cấm</button>
            <?php endif; ?>
        </form>
    </td>
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
                <a class="button" href="?page=linkngon-customers<?php echo $search?"&s=".urlencode($search):""; ?>&paged=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

</div>
