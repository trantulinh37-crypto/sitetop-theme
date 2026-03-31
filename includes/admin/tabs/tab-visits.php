<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Date filter (default today in Vietnam timezone)
$today = date('Y-m-d', strtotime(linkngon_current_time()));
$date_filter = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : $today;
$step_filter = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : '';

$where = "WHERE 1=1";
if($date_filter){
    $where .= $wpdb->prepare(" AND DATE(v.created_at) = %s", $date_filter);
}
if($step_filter){
    $where .= $wpdb->prepare(" AND v.step = %s", $step_filter);
}

$page_num = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 100;
$offset = ($page_num - 1) * $per_page;

$total = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}shortlink_visits v $where");
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT v.*, kc.title as campaign_title, u.user_login
     FROM {$prefix}shortlink_visits v
     LEFT JOIN {$prefix}keyword_campaigns kc ON kc.id = v.campaign_id
     LEFT JOIN {$wpdb->users} u ON u.ID = v.user_id
     $where
     ORDER BY v.id DESC
     LIMIT %d OFFSET %d", $per_page, $offset
));

$total_pages = ceil($total / $per_page);

// Step counts for today
$step_counts = $wpdb->get_results($wpdb->prepare(
    "SELECT step, COUNT(*) as cnt FROM {$prefix}shortlink_visits WHERE DATE(created_at) = %s GROUP BY step", $date_filter
), OBJECT_K);
?>
<div class="wrap">
<h1>Visits</h1>

<form method="get" style="margin-bottom:10px;">
    <input type="hidden" name="page" value="linkngon-admin">
    <input type="hidden" name="tab" value="visits">
    <label>Date: <input type="date" name="date" value="<?php echo esc_attr($date_filter); ?>"></label>
    <select name="step">
        <option value="">All Steps</option>
        <?php foreach(['started','google_clicked','target_visited','code_shown','verified'] as $s): ?>
        <option value="<?php echo $s; ?>" <?php selected($step_filter, $s); ?>><?php echo $s; ?></option>
        <?php endforeach; ?>
    </select>
    <input type="submit" class="button" value="Filter">
</form>

<ul class="subsubsub">
    <li><a href="?page=linkngon-admin&tab=visits&date=<?php echo $date_filter; ?>" <?php echo !$step_filter?'class="current"':''; ?>>All <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
    <?php foreach(['started','google_clicked','target_visited','code_shown','verified'] as $s): ?>
    <li><a href="?page=linkngon-admin&tab=visits&date=<?php echo $date_filter; ?>&step=<?php echo $s; ?>" <?php echo $step_filter===$s?'class="current"':''; ?>><?php echo $s; ?> <span class="count">(<?php echo isset($step_counts[$s]) ? $step_counts[$s]->cnt : 0; ?>)</span></a><?php echo $s!=='verified'?' |':''; ?></li>
    <?php endforeach; ?>
</ul>
<br class="clear">

<table class="widefat striped">
<thead>
<tr>
    <th>ID</th>
    <th>Session</th>
    <th>Campaign</th>
    <th>User</th>
    <th>IP</th>
    <th>Step</th>
    <th>Reward Paid</th>
    <th>Reward Amount</th>
    <th>Customer Paid</th>
    <th>Created</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="10">No visits found.</td></tr>
<?php else: foreach($rows as $row):
    $step_colors = ['started'=>'#82878c','google_clicked'=>'#00a0d2','target_visited'=>'#ffb900','code_shown'=>'#826eb4','verified'=>'#46b450'];
    $color = isset($step_colors[$row->step]) ? $step_colors[$row->step] : '#82878c';
?>
<tr>
    <td><?php echo intval($row->id); ?></td>
    <td><code title="<?php echo esc_attr($row->session_id); ?>"><?php echo esc_html(substr($row->session_id, 0, 8)); ?>...</code></td>
    <td><?php echo esc_html($row->campaign_title ?? 'Campaign #'.intval($row->campaign_id)); ?></td>
    <td><?php echo esc_html($row->user_login ?? 'Guest'); ?></td>
    <td>
        <code><?php echo esc_html($row->ip_address); ?></code>
        <?php if(!empty($row->ip_changed)): ?><br><small style="color:#dc3232;">IP changed</small><?php endif; ?>
    </td>
    <td><span style="color:<?php echo $color; ?>;font-weight:bold;"><?php echo esc_html($row->step); ?></span></td>
    <td><?php echo $row->reward_paid ? '<span style="color:#46b450;">Yes</span>' : '<span style="color:#82878c;">No</span>'; ?></td>
    <td><?php echo $row->reward_amount > 0 ? linkngon_format_money($row->reward_amount) : '---'; ?></td>
    <td><?php echo $row->customer_paid ? '<span style="color:#46b450;">Yes</span>' : '<span style="color:#82878c;">No</span>'; ?></td>
    <td><?php echo date('d/m/Y H:i:s', strtotime($row->created_at)); ?></td>
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
                <a class="button" href="?page=linkngon-admin&tab=visits&date=<?php echo $date_filter; ?><?php echo $step_filter?"&step=$step_filter":""; ?>&paged=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

</div>
