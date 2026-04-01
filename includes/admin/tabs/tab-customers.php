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

<?php
$cust_total = (int) $total;
$cust_balance = (float) $wpdb->get_var("SELECT COALESCE(SUM(balance),0) FROM {$prefix}customer_balance WHERE balance > 0");
$week_ago = date('Y-m-d', strtotime('-7 days', strtotime(linkngon_current_time())));
$cust_new_week = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
     INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = %s
     WHERE um.meta_value LIKE %s AND u.user_registered >= %s",
    $cap_key, '%customer%', $week_ago
));
$today_str = date('Y-m-d', strtotime(linkngon_current_time()));
$cust_login_today = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'linkngon_last_login' AND meta_value >= %s",
    $today_str
));
?>
<style>
.cust-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
.cust-stat{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:14px}
.cust-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center}
.cust-icon.ci1{background:#dbeafe;color:#2563eb} .cust-icon.ci2{background:#d1fae5;color:#059669}
.cust-icon.ci3{background:#ede9fe;color:#7c3aed} .cust-icon.ci4{background:#fef3c7;color:#d97706}
.cust-val{font-size:22px;font-weight:700;color:#1d2327;line-height:1.2}
.cust-label{font-size:12px;color:#6b7280}
@media(max-width:600px){.cust-stats{grid-template-columns:repeat(2,1fr)}}
</style>
<div class="cust-stats">
    <div class="cust-stat"><div class="cust-icon ci1"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div><div><div class="cust-val"><?php echo number_format($cust_total); ?></div><div class="cust-label">Khách Hàng</div></div></div>
    <div class="cust-stat"><div class="cust-icon ci2"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div><div><div class="cust-val"><?php echo number_format($cust_balance); ?></div><div class="cust-label">Số Dư</div></div></div>
    <div class="cust-stat"><div class="cust-icon ci3"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div><div><div class="cust-val"><?php echo number_format($cust_new_week); ?></div><div class="cust-label">Đăng Ký Mới Tuần</div></div></div>
    <div class="cust-stat"><div class="cust-icon ci4"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg></div><div><div class="cust-val"><?php echo number_format($cust_login_today); ?></div><div class="cust-label">Đăng Nhập Hôm Nay</div></div></div>
</div>

<form method="get" style="margin-bottom:10px;">
    <input type="hidden" name="page" value="linkngon-customers">
    <p class="search-box">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Tìm tên đăng nhập, email...">
        <input type="submit" class="button" value="Tìm kiếm">
    </p>
</form>
<br class="clear">

<p>Tổng: <strong><?php echo intval($total); ?></strong> khách hàng</p>

<div style="overflow-x:auto"><table class="widefat striped">
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
</table></div>

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
