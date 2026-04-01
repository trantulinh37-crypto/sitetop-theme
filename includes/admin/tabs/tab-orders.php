<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Handle actions
if(isset($_POST['order_action']) && wp_verify_nonce($_POST['_wpnonce'],'linkngon_order_action')){
    $order_id = intval($_POST['order_id'] ?? 0);
    $action = sanitize_text_field($_POST['order_action']);

    if($action === 'approve' && $order_id){
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}customer_orders WHERE id=%d AND status='pending'", $order_id));
        if($order){
            $wpdb->update($prefix.'customer_orders', [
                'status' => 'active',
                'approved_by' => get_current_user_id(),
                'approved_at' => linkngon_current_time()
            ], ['id' => $order_id]);
            // Also activate campaign
            $wpdb->update($prefix.'keyword_campaigns', ['status'=>'active'], ['order_id'=>$order_id]);
            echo '<div class="notice notice-success"><p>Đơn hàng #'.$order_id.' đã duyệt.</p></div>';
        }
    } elseif($action === 'reject' && $order_id){
        $reason = isset($_POST['reject_reason']) ? sanitize_text_field($_POST['reject_reason']) : '';
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}customer_orders WHERE id=%d AND status='pending'", $order_id));
        if($order){
            $wpdb->update($prefix.'customer_orders', [
                'status' => 'rejected',
                'reject_reason' => $reason
            ], ['id' => $order_id]);
            $wpdb->update($prefix.'keyword_campaigns', ['status'=>'rejected','reject_reason'=>$reason], ['order_id'=>$order_id]);
            echo '<div class="notice notice-warning"><p>Đơn hàng #'.$order_id.' đã từ chối.</p></div>';
        }
    } elseif($action === 'pause' && $order_id){
        $wpdb->update($prefix.'customer_orders', ['status'=>'paused'], ['id'=>$order_id]);
        $wpdb->update($prefix.'keyword_campaigns', ['status'=>'paused'], ['order_id'=>$order_id]);
        echo '<div class="notice notice-info"><p>Đơn hàng #'.$order_id.' đã tạm dừng.</p></div>';
    } elseif($action === 'resume' && $order_id){
        $wpdb->update($prefix.'customer_orders', ['status'=>'active'], ['id'=>$order_id]);
        $wpdb->update($prefix.'keyword_campaigns', ['status'=>'active'], ['order_id'=>$order_id]);
        echo '<div class="notice notice-success"><p>Đơn hàng #'.$order_id.' đã kích hoạt lại.</p></div>';
    }
}

// Filters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

$where = "WHERE 1=1";
$args = array();
if($status_filter) {
    $where .= " AND co.status = %s";
    $args[] = $status_filter;
}
if($search){
    $where .= " AND (co.title LIKE %s OR co.customer_username LIKE %s OR co.task_url LIKE %s)";
    $args[] = '%'.$search.'%';
    $args[] = '%'.$search.'%';
    $args[] = '%'.$search.'%';
}

$page_num = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

$count_sql = "SELECT COUNT(*) FROM {$prefix}customer_orders co $where";
$total = !empty($args) ? $wpdb->get_var($wpdb->prepare($count_sql, $args)) : $wpdb->get_var($count_sql);

$args[] = $per_page;
$args[] = $offset;
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT co.*, u.display_name
     FROM {$prefix}customer_orders co
     LEFT JOIN {$wpdb->users} u ON u.ID = co.customer_id
     $where
     ORDER BY co.id DESC
     LIMIT %d OFFSET %d", $args
));

$total_pages = ceil($total / $per_page);
$counts = $wpdb->get_results("SELECT status, COUNT(*) as cnt FROM {$prefix}customer_orders GROUP BY status", OBJECT_K);

$status_labels = [
    'pending' => 'Chờ duyệt',
    'active' => 'Đang chạy',
    'paused' => 'Tạm dừng',
    'completed' => 'Hoàn thành',
    'rejected' => 'Từ chối',
];
?>
<div class="wrap">
<h1>Đơn hàng</h1>

<?php
// Stats
$ord_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}customer_orders");
$ord_pending = isset($counts['pending']) ? (int)$counts['pending']->cnt : 0;
$ord_active = isset($counts['active']) ? (int)$counts['active']->cnt : 0;
$ord_revenue = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount_spent),0) FROM {$prefix}customer_orders WHERE status IN ('active','completed','paused')");
?>
<style>
.ord-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
.ord-stat{border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px}
.ord-stat.os1{background:#eff6ff;border:2px solid #bfdbfe} .ord-stat.os2{background:#fffbeb;border:2px solid #fde68a}
.ord-stat.os3{background:#f0fdf4;border:2px solid #bbf7d0} .ord-stat.os4{background:#fef2f2;border:2px solid #fecaca}
.ord-val{font-size:22px;font-weight:700;line-height:1.2}
.ord-stat.os1 .ord-val{color:#1e40af} .ord-stat.os2 .ord-val{color:#92400e}
.ord-stat.os3 .ord-val{color:#166534} .ord-stat.os4 .ord-val{color:#991b1b}
.ord-label{font-size:12px;color:#6b7280}
.ord-ico{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center}
.ord-ico.oi1{background:#dbeafe;color:#2563eb} .ord-ico.oi2{background:#fde68a;color:#d97706}
.ord-ico.oi3{background:#bbf7d0;color:#16a34a} .ord-ico.oi4{background:#fecaca;color:#dc2626}
@media(max-width:600px){.ord-stats{grid-template-columns:repeat(2,1fr)} .ord-val{font-size:16px} .ord-stat{padding:12px 14px} .ord-ico{width:38px;height:38px} .ord-ico svg{width:20px;height:20px}}
</style>
<div class="ord-stats">
    <div class="ord-stat os1"><div><div class="ord-val"><?php echo number_format($ord_total); ?></div><div class="ord-label">Tổng đơn hàng</div></div><div class="ord-ico oi1"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></div></div>
    <div class="ord-stat os2"><div><div class="ord-val"><?php echo $ord_pending; ?></div><div class="ord-label">Chờ duyệt</div></div><div class="ord-ico oi2"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
    <div class="ord-stat os3"><div><div class="ord-val"><?php echo $ord_active; ?></div><div class="ord-label">Đang chạy</div></div><div class="ord-ico oi3"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div></div>
    <div class="ord-stat os4"><div><div class="ord-val"><?php echo linkngon_format_money($ord_revenue); ?></div><div class="ord-label">Doanh thu</div></div><div class="ord-ico oi4"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px">
    <ul class="subsubsub" style="margin:0;float:none">
        <li><a href="?page=linkngon-orders" <?php echo !$status_filter?'class="current"':''; ?>>Tất cả <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
        <?php foreach(['pending','active','paused','completed','rejected'] as $s): ?>
        <li><a href="?page=linkngon-orders&status=<?php echo $s; ?>" <?php echo $status_filter===$s?'class="current"':''; ?>><?php echo $status_labels[$s]; ?> <span class="count">(<?php echo isset($counts[$s]) ? $counts[$s]->cnt : 0; ?>)</span></a><?php echo $s!=='rejected'?' |':''; ?></li>
        <?php endforeach; ?>
    </ul>
    <form method="get" style="display:flex;gap:6px;align-items:center">
        <input type="hidden" name="page" value="linkngon-orders">
        <?php if($status_filter): ?><input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>"><?php endif; ?>
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Tìm tiêu đề, username, URL..." style="padding:6px 10px;min-width:200px">
        <input type="submit" class="button" value="Tìm kiếm">
    </form>
</div>

<div style="overflow-x:auto"><table class="widefat striped">
<thead>
<tr>
    <th>ID</th>
    <th>Khách hàng</th>
    <th>Tiêu đề</th>
    <th>Loại</th>
    <th>URL</th>
    <th>SL đặt</th>
    <th>Đã chạy</th>
    <th>Giá/view</th>
    <th>Đã chi</th>
    <th>Trạng thái</th>
    <th>Ngày tạo</th>
    <th>Thao tác</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="12">Không có dữ liệu.</td></tr>
<?php else: foreach($rows as $row):
    $status_colors = ['active'=>'#46b450','pending'=>'#00a0d2','paused'=>'#ffb900','completed'=>'#46b450','rejected'=>'#dc3232'];
    $color = isset($status_colors[$row->status]) ? $status_colors[$row->status] : '#82878c';
    $task_type_labels = ['keyword_search'=>'Từ khoá','traffic_direct'=>'Direct','traffic_social'=>'Social'];
    $type_label = $task_type_labels[$row->task_type ?? ''] ?? ucfirst($row->task_type ?? 'N/A');
?>
<tr>
    <td><?php echo intval($row->id); ?></td>
    <td>
        <strong><?php echo esc_html($row->display_name ?? $row->customer_username ?? 'Customer #'.$row->customer_id); ?></strong>
        <?php if(!empty($row->customer_username)): ?><br><small><?php echo esc_html($row->customer_username); ?></small><?php endif; ?>
    </td>
    <td style="max-width:200px"><small><?php echo esc_html(mb_strimwidth($row->title ?? '', 0, 50, '...')); ?></small></td>
    <td><small><?php echo esc_html($type_label); ?></small></td>
    <td style="max-width:180px"><a href="<?php echo esc_url($row->task_url ?? ''); ?>" target="_blank" style="font-size:12px"><?php echo esc_html(mb_strimwidth($row->task_url ?? '', 0, 35, '...')); ?></a></td>
    <td style="font-weight:600"><?php echo intval($row->quantity); ?></td>
    <td style="font-weight:600"><?php echo intval($row->completed); ?></td>
    <td><?php echo linkngon_format_money($row->price_per_task ?? 0); ?></td>
    <td style="font-weight:600;color:<?php echo ($row->amount_spent ?? 0) > 0 ? '#46b450' : '#82878c'; ?>"><?php echo linkngon_format_money($row->amount_spent ?? 0); ?></td>
    <td><span style="color:<?php echo $color; ?>;font-weight:bold;"><?php echo $status_labels[$row->status] ?? ucfirst($row->status); ?></span></td>
    <td style="font-size:12px"><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
    <td style="white-space:nowrap">
        <?php if($row->status === 'pending'): ?>
        <form method="post" style="display:inline"><?php wp_nonce_field('linkngon_order_action'); ?><input type="hidden" name="order_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="order_action" value="approve" class="button button-small button-primary">Duyệt</button>
            <button type="button" class="button button-small" onclick="this.nextElementSibling.style.display='inline'">Từ chối</button>
            <span style="display:none;">
                <input type="text" name="reject_reason" placeholder="Lý do..." style="width:120px;">
                <button type="submit" name="order_action" value="reject" class="button button-small">Xác nhận</button>
            </span>
        </form>
        <?php elseif($row->status === 'active'): ?>
        <form method="post" style="display:inline"><?php wp_nonce_field('linkngon_order_action'); ?><input type="hidden" name="order_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="order_action" value="pause" class="button button-small">Tạm dừng</button>
        </form>
        <?php elseif($row->status === 'paused'): ?>
        <form method="post" style="display:inline"><?php wp_nonce_field('linkngon_order_action'); ?><input type="hidden" name="order_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="order_action" value="resume" class="button button-small button-primary">Kích hoạt</button>
        </form>
        <?php endif; ?>
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
                <a class="button" href="?page=linkngon-orders<?php echo $status_filter?"&status=$status_filter":""; ?><?php echo $search?"&s=".urlencode($search):""; ?>&paged=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

</div>
