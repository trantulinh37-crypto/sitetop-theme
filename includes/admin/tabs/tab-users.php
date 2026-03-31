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
        echo '<div class="notice notice-warning"><p>User #'.$target_user_id.' banned.</p></div>';
    } elseif($action === 'unban'){
        delete_user_meta($target_user_id, 'linkngon_banned');
        echo '<div class="notice notice-success"><p>User #'.$target_user_id.' unbanned.</p></div>';
    }
}

// Search
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$page_num = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

// Get subscribers
$search_where = '';
if($search){
    $search_where = $wpdb->prepare(" AND (u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)", '%'.$search.'%', '%'.$search.'%', '%'.$search.'%');
}

$total = $wpdb->get_var(
    "SELECT COUNT(DISTINCT u.ID)
     FROM {$wpdb->users} u
     INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = '{$wpdb->prefix}capabilities'
     WHERE um.meta_value LIKE '%subscriber%'
     $search_where"
);

$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
            ub.balance, ub.total_earned,
            (SELECT COUNT(*) FROM {$prefix}user_shortlinks WHERE user_id = u.ID) as total_links
     FROM {$wpdb->users} u
     INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = '{$wpdb->prefix}capabilities'
     LEFT JOIN {$prefix}user_balance ub ON ub.user_id = u.ID
     WHERE um.meta_value LIKE '%subscriber%'
     $search_where
     ORDER BY u.ID DESC
     LIMIT %d OFFSET %d", $per_page, $offset
));

$total_pages = ceil($total / $per_page);
?>
<div class="wrap">
<h1>Users (Subscribers)</h1>

<form method="get" style="margin-bottom:10px;">
    <input type="hidden" name="page" value="linkngon-admin">
    <input type="hidden" name="tab" value="users">
    <p class="search-box">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search username, email...">
        <input type="submit" class="button" value="Search">
    </p>
</form>
<br class="clear">

<p>Total: <strong><?php echo intval($total); ?></strong> subscribers</p>

<table class="widefat striped">
<thead>
<tr>
    <th>ID</th>
    <th>Username</th>
    <th>Email</th>
    <th>Phone</th>
    <th>Balance</th>
    <th>Total Earned</th>
    <th>Links</th>
    <th>Status</th>
    <th>Registered</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="10">No users found.</td></tr>
<?php else: foreach($rows as $row):
    $is_banned = get_user_meta($row->ID, 'linkngon_banned', true);
    $phone = get_user_meta($row->ID, 'phone', true);
?>
<tr>
    <td><?php echo intval($row->ID); ?></td>
    <td><strong><?php echo esc_html($row->user_login); ?></strong></td>
    <td><?php echo esc_html($row->user_email); ?></td>
    <td><?php echo esc_html($phone ?: '---'); ?></td>
    <td><?php echo linkngon_format_money($row->balance ?? 0); ?></td>
    <td><?php echo linkngon_format_money($row->total_earned ?? 0); ?></td>
    <td><?php echo intval($row->total_links); ?></td>
    <td>
        <?php if($is_banned): ?>
            <span style="color:#dc3232;font-weight:bold;">Banned</span>
        <?php else: ?>
            <span style="color:#46b450;font-weight:bold;">Active</span>
        <?php endif; ?>
    </td>
    <td><?php echo date('d/m/Y H:i', strtotime($row->user_registered)); ?></td>
    <td>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_user_action'); ?>
            <input type="hidden" name="target_user_id" value="<?php echo $row->ID; ?>">
            <?php if($is_banned): ?>
                <button type="submit" name="user_action" value="unban" class="button button-small button-primary">Unban</button>
            <?php else: ?>
                <button type="submit" name="user_action" value="ban" class="button button-small" onclick="return confirm('Ban this user?')">Ban</button>
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
                <a class="button" href="?page=linkngon-admin&tab=users<?php echo $search?"&s=".urlencode($search):""; ?>&paged=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

</div>
