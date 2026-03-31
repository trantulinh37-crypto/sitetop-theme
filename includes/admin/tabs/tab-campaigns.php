<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Handle actions
if(isset($_POST['campaign_action']) && wp_verify_nonce($_POST['_wpnonce'],'linkngon_campaign_action')){
    $campaign_id = intval($_POST['campaign_id']);
    $action = sanitize_text_field($_POST['campaign_action']);

    if($action === 'approve'){
        $wpdb->update($prefix.'keyword_campaigns', ['status'=>'active','updated_at'=>linkngon_current_time()], ['id'=>$campaign_id]);
        $wpdb->update($prefix.'customer_orders', ['status'=>'active','approved_at'=>linkngon_current_time()], ['task_id'=>$campaign_id]);
        echo '<div class="notice notice-success"><p>Campaign #'.$campaign_id.' approved.</p></div>';
    } elseif($action === 'pause'){
        $wpdb->update($prefix.'keyword_campaigns', ['status'=>'paused','updated_at'=>linkngon_current_time()], ['id'=>$campaign_id]);
        $wpdb->update($prefix.'customer_orders', ['status'=>'paused'], ['task_id'=>$campaign_id]);
        echo '<div class="notice notice-warning"><p>Campaign #'.$campaign_id.' paused.</p></div>';
    } elseif($action === 'resume'){
        $wpdb->update($prefix.'keyword_campaigns', ['status'=>'active','updated_at'=>linkngon_current_time()], ['id'=>$campaign_id]);
        $wpdb->update($prefix.'customer_orders', ['status'=>'active'], ['task_id'=>$campaign_id]);
        echo '<div class="notice notice-success"><p>Campaign #'.$campaign_id.' resumed.</p></div>';
    } elseif($action === 'reject'){
        $reason = isset($_POST['reject_reason']) ? sanitize_text_field($_POST['reject_reason']) : '';
        $wpdb->update($prefix.'keyword_campaigns', ['status'=>'rejected','reject_reason'=>$reason,'updated_at'=>linkngon_current_time()], ['id'=>$campaign_id]);
        $wpdb->update($prefix.'customer_orders', ['status'=>'rejected','reject_reason'=>$reason], ['task_id'=>$campaign_id]);
        echo '<div class="notice notice-error"><p>Campaign #'.$campaign_id.' rejected.</p></div>';
    }
}

// Filters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$where = "WHERE 1=1";
if($status_filter) $where .= $wpdb->prepare(" AND kc.status = %s", $status_filter);

$page_num = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

$total = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}keyword_campaigns kc LEFT JOIN {$prefix}customer_orders co ON co.id = kc.order_id $where");
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT kc.*, co.task_type, co.customer_username, co.quantity as order_quantity
     FROM {$prefix}keyword_campaigns kc
     LEFT JOIN {$prefix}customer_orders co ON co.id = kc.order_id
     $where
     ORDER BY kc.id DESC
     LIMIT %d OFFSET %d", $per_page, $offset
));

$total_pages = ceil($total / $per_page);

// Status counts
$counts = $wpdb->get_results("SELECT status, COUNT(*) as cnt FROM {$prefix}keyword_campaigns GROUP BY status", OBJECT_K);
?>
<div class="wrap">
<h1>Campaigns</h1>

<ul class="subsubsub">
    <li><a href="?page=linkngon-admin&tab=campaigns" <?php echo !$status_filter?'class="current"':''; ?>>All <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
    <?php foreach(['pending','active','paused','completed','rejected'] as $s): ?>
    <li><a href="?page=linkngon-admin&tab=campaigns&status=<?php echo $s; ?>" <?php echo $status_filter===$s?'class="current"':''; ?>><?php echo ucfirst($s); ?> <span class="count">(<?php echo isset($counts[$s]) ? $counts[$s]->cnt : 0; ?>)</span></a><?php echo $s!=='rejected'?' |':''; ?></li>
    <?php endforeach; ?>
</ul>
<br class="clear">

<table class="widefat striped">
<thead>
<tr>
    <th>ID</th>
    <th>Customer</th>
    <th>Title / Keyword</th>
    <th>Target URL</th>
    <th>Traffic Type</th>
    <th>Task Type</th>
    <th>Price/View</th>
    <th>Daily Limit</th>
    <th>Progress</th>
    <th>Status</th>
    <th>Created</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="12">No campaigns found.</td></tr>
<?php else: foreach($rows as $row):
    $status_colors = ['active'=>'#46b450','paused'=>'#ffb900','pending'=>'#00a0d2','completed'=>'#82878c','rejected'=>'#dc3232'];
    $color = isset($status_colors[$row->status]) ? $status_colors[$row->status] : '#82878c';
?>
<tr>
    <td><?php echo intval($row->id); ?></td>
    <td><?php echo esc_html($row->customer_username ?? '—'); ?></td>
    <td>
        <strong><?php echo esc_html($row->title); ?></strong>
        <?php if(!empty($row->keyword)): ?><br><code><?php echo esc_html($row->keyword); ?></code><?php endif; ?>
    </td>
    <td><a href="<?php echo esc_url($row->target_url); ?>" target="_blank" title="<?php echo esc_attr($row->target_url); ?>"><?php echo esc_html(mb_strimwidth($row->target_url,0,40,'...')); ?></a></td>
    <td><?php echo esc_html($row->traffic_type ?: '1step'); ?></td>
    <td><?php echo esc_html($row->task_type ?? 'keyword_search'); ?></td>
    <td><?php echo linkngon_format_money($row->price_per_view); ?></td>
    <td><?php echo intval($row->daily_traffic); ?></td>
    <td><?php echo intval($row->completed); ?>/<?php echo intval($row->quantity); ?></td>
    <td><span style="color:<?php echo $color; ?>;font-weight:bold;"><?php echo ucfirst($row->status); ?></span></td>
    <td><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
    <td>
        <?php if($row->status === 'pending'): ?>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_campaign_action'); ?>
            <input type="hidden" name="campaign_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="campaign_action" value="approve" class="button button-small button-primary">Approve</button>
            <button type="button" class="button button-small" onclick="this.nextElementSibling.style.display='inline'">Reject</button>
            <span style="display:none;">
                <input type="text" name="reject_reason" placeholder="Reason..." style="width:120px;">
                <button type="submit" name="campaign_action" value="reject" class="button button-small">Confirm Reject</button>
            </span>
        </form>
        <?php elseif($row->status === 'active'): ?>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_campaign_action'); ?>
            <input type="hidden" name="campaign_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="campaign_action" value="pause" class="button button-small">Pause</button>
        </form>
        <?php elseif($row->status === 'paused'): ?>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_campaign_action'); ?>
            <input type="hidden" name="campaign_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="campaign_action" value="resume" class="button button-small button-primary">Resume</button>
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
                <a class="button" href="?page=linkngon-admin&tab=campaigns<?php echo $status_filter?"&status=$status_filter":""; ?>&paged=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

</div>
