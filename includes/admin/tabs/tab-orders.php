<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Handle actions
if(isset($_POST['order_action']) && wp_verify_nonce($_POST['_wpnonce'],'linkngon_order_action')){
    $order_id = intval($_POST['order_id']);
    $action = sanitize_text_field($_POST['order_action']);

    if($action === 'approve'){
        $wpdb->update($prefix.'customer_orders', ['status'=>'active','approved_by'=>get_current_user_id(),'approved_at'=>linkngon_current_time(),'updated_at'=>linkngon_current_time()], ['id'=>$order_id]);
        $wpdb->update($prefix.'keyword_campaigns', ['status'=>'active','updated_at'=>linkngon_current_time()], ['order_id'=>$order_id]);
        echo '<div class="notice notice-success"><p>Order #'.$order_id.' approved.</p></div>';
    } elseif($action === 'reject'){
        $reason = isset($_POST['reject_reason']) ? sanitize_text_field($_POST['reject_reason']) : '';
        $wpdb->update($prefix.'customer_orders', ['status'=>'rejected','reject_reason'=>$reason,'updated_at'=>linkngon_current_time()], ['id'=>$order_id]);
        $wpdb->update($prefix.'keyword_campaigns', ['status'=>'rejected','reject_reason'=>$reason,'updated_at'=>linkngon_current_time()], ['order_id'=>$order_id]);
        echo '<div class="notice notice-error"><p>Order #'.$order_id.' rejected.</p></div>';
    }
}

// Filters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$where = "WHERE 1=1";
if($status_filter) $where .= $wpdb->prepare(" AND co.status = %s", $status_filter);

$page_num = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

$total = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}customer_orders co $where");
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT co.*
     FROM {$prefix}customer_orders co
     $where
     ORDER BY co.id DESC
     LIMIT %d OFFSET %d", $per_page, $offset
));

$total_pages = ceil($total / $per_page);
$counts = $wpdb->get_results("SELECT status, COUNT(*) as cnt FROM {$prefix}customer_orders GROUP BY status", OBJECT_K);
?>
<div class="wrap">
<h1>Orders</h1>

<ul class="subsubsub">
    <li><a href="?page=linkngon-admin&tab=orders" <?php echo !$status_filter?'class="current"':''; ?>>All <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
    <?php foreach(['pending','active','paused','completed','rejected'] as $s): ?>
    <li><a href="?page=linkngon-admin&tab=orders&status=<?php echo $s; ?>" <?php echo $status_filter===$s?'class="current"':''; ?>><?php echo ucfirst($s); ?> <span class="count">(<?php echo isset($counts[$s]) ? $counts[$s]->cnt : 0; ?>)</span></a><?php echo $s!=='rejected'?' |':''; ?></li>
    <?php endforeach; ?>
</ul>
<br class="clear">

<table class="widefat striped">
<thead>
<tr>
    <th>ID</th>
    <th>Customer</th>
    <th>Task Type</th>
    <th>Title</th>
    <th>Quantity</th>
    <th>Completed</th>
    <th>Price/Task</th>
    <th>Total</th>
    <th>Amount Spent</th>
    <th>Status</th>
    <th>Created</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="12">No orders found.</td></tr>
<?php else: foreach($rows as $row):
    $status_colors = ['active'=>'#46b450','paused'=>'#ffb900','pending'=>'#00a0d2','completed'=>'#82878c','rejected'=>'#dc3232'];
    $color = isset($status_colors[$row->status]) ? $status_colors[$row->status] : '#82878c';
?>
<tr>
    <td><?php echo intval($row->id); ?></td>
    <td><?php echo esc_html($row->customer_username ?? '---'); ?></td>
    <td><?php echo esc_html($row->task_type); ?></td>
    <td><strong><?php echo esc_html($row->title); ?></strong></td>
    <td><?php echo intval($row->quantity); ?></td>
    <td><?php echo intval($row->completed); ?></td>
    <td><?php echo linkngon_format_money($row->price_per_task); ?></td>
    <td><?php echo linkngon_format_money($row->total_amount); ?></td>
    <td><?php echo linkngon_format_money($row->amount_spent); ?></td>
    <td><span style="color:<?php echo $color; ?>;font-weight:bold;"><?php echo ucfirst($row->status); ?></span></td>
    <td><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
    <td>
        <?php if($row->status === 'pending'): ?>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_order_action'); ?>
            <input type="hidden" name="order_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="order_action" value="approve" class="button button-small button-primary">Approve</button>
            <button type="button" class="button button-small" onclick="this.nextElementSibling.style.display='inline'">Reject</button>
            <span style="display:none;">
                <input type="text" name="reject_reason" placeholder="Reason..." style="width:120px;">
                <button type="submit" name="order_action" value="reject" class="button button-small">Confirm</button>
            </span>
        </form>
        <?php endif; ?>
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
                <a class="button" href="?page=linkngon-admin&tab=orders<?php echo $status_filter?"&status=$status_filter":""; ?>&paged=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

</div>
