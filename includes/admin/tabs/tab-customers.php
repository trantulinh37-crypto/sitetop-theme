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
.cust-stat{border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px}
.cust-stat.cs1{background:#eff6ff;border:2px solid #bfdbfe} .cust-stat.cs2{background:#ede9fe;border:2px solid #c4b5fd}
.cust-stat.cs3{background:#fef2f2;border:2px solid #fecaca} .cust-stat.cs4{background:#fffbeb;border:2px solid #fde68a}
.cust-val{font-size:22px;font-weight:700;line-height:1.2}
.cust-stat.cs1 .cust-val{color:#1e40af} .cust-stat.cs2 .cust-val{color:#5b21b6}
.cust-stat.cs3 .cust-val{color:#991b1b} .cust-stat.cs4 .cust-val{color:#92400e}
.cust-label{font-size:12px;color:#6b7280}
.cust-ico{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center}
.cust-ico.ci1{background:#dbeafe;color:#2563eb} .cust-ico.ci2{background:#c4b5fd;color:#7c3aed}
.cust-ico.ci3{background:#fecaca;color:#dc2626} .cust-ico.ci4{background:#fde68a;color:#d97706}
@media(max-width:600px){.cust-stats{grid-template-columns:repeat(2,1fr)} .cust-val{font-size:16px} .cust-stat{padding:12px 14px} .cust-ico{width:38px;height:38px} .cust-ico svg{width:20px;height:20px}}
</style>
<div class="cust-stats">
    <div class="cust-stat cs1"><div><div class="cust-val"><?php echo number_format($cust_total); ?></div><div class="cust-label">Khách hàng</div></div><div class="cust-ico ci1"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div></div>
    <div class="cust-stat cs2"><div><div class="cust-val"><?php echo linkngon_format_money($cust_balance); ?></div><div class="cust-label">Số dư</div></div><div class="cust-ico ci2"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
    <div class="cust-stat cs3"><div><div class="cust-val"><?php echo number_format($cust_new_week); ?></div><div class="cust-label">Đăng ký mới</div></div><div class="cust-ico ci3"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg></div></div>
    <div class="cust-stat cs4"><div><div class="cust-val"><?php echo number_format($cust_login_today); ?></div><div class="cust-label">Đăng nhập hôm nay</div></div><div class="cust-ico ci4"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div></div>
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
    <td style="white-space:nowrap">
        <button type="button" class="button button-small" onclick="loginAsCustomer(<?php echo $row->ID; ?>,'<?php echo esc_js($row->user_login); ?>')" title="Đăng nhập với tư cách khách hàng" style="margin-right:4px"><span class="dashicons dashicons-admin-users" style="vertical-align:middle;font-size:14px;width:14px;height:14px;line-height:14px"></span></button>
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

<script>
function loginAsCustomer(uid, name){
    if(!confirm('Đăng nhập với tư cách khách hàng "'+name+'"?')) return;
    var fd=new FormData();
    fd.append('action','linkngon_admin_login_as_user');
    fd.append('nonce','<?php echo wp_create_nonce("linkngon_admin_nonce"); ?>');
    fd.append('user_id',uid);
    fetch('<?php echo admin_url("admin-ajax.php"); ?>',{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){return r.json()})
    .then(function(r){
        if(r.success) window.open(r.data.redirect||'<?php echo home_url(); ?>','_blank');
        else alert(r.data||'Lỗi');
    });
}
</script>
</div>
