<?php
if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Handle actions
if(isset($_POST['user_action']) && wp_verify_nonce($_POST['_wpnonce'],'linkngon_user_action')){
    $target_id = intval($_POST['target_user_id']);
    $action = sanitize_text_field($_POST['user_action']);

    if($action === 'ban'){
        update_user_meta($target_id, 'linkngon_banned', true);
        // Reject pending withdrawals
        $pending_wds = $wpdb->get_results($wpdb->prepare(
            "SELECT id, amount FROM {$prefix}withdrawals WHERE user_id=%d AND status IN ('pending','approved') FOR UPDATE",
            $target_id
        ));
        foreach($pending_wds as $wd){
            $wpdb->update("{$prefix}withdrawals", array('status'=>'rejected','admin_note'=>'Tự động hủy do tài khoản bị cấm','processed_at'=>linkngon_current_time()), array('id'=>$wd->id));
            $wpdb->insert("{$prefix}transactions", array('user_id'=>$target_id,'type'=>'refund','amount'=>$wd->amount,'description'=>'Hoàn tiền withdrawal #'.$wd->id.' (tài khoản bị cấm)','reference_id'=>$wd->id,'reference_type'=>'withdrawal','status'=>'completed','created_at'=>linkngon_current_time()));
        }
        echo '<div class="notice notice-warning"><p>User #'.$target_id.' đã bị cấm.</p></div>';
    } elseif($action === 'unban'){
        delete_user_meta($target_id, 'linkngon_banned');
        echo '<div class="notice notice-success"><p>User #'.$target_id.' đã được bỏ cấm.</p></div>';
    } elseif($action === 'delete'){
        if(function_exists('linkngon_admin_do_delete_user')) linkngon_admin_do_delete_user($target_id);
        else wp_delete_user($target_id);
        echo '<div class="notice notice-warning"><p>User #'.$target_id.' đã bị xóa.</p></div>';
    }
}

// Search & pagination (GET-based like customers)
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

// Count total
$count_query = "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
    INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = %s
    WHERE (um.meta_value LIKE %s OR um.meta_value LIKE %s) {$search_sql}";
$count_args = array_merge(array($cap_key, '%subscriber%', '%administrator%'), $search_args);
$total = $wpdb->get_var($wpdb->prepare($count_query, $count_args));

// Summary stats
$total_balance_all = (float) $wpdb->get_var("SELECT COALESCE(SUM(balance),0) FROM {$prefix}user_balance WHERE balance > 0");
$today_str = date('Y-m-d', strtotime(linkngon_current_time()));
$new_today = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
     INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = %s
     WHERE (um.meta_value LIKE %s OR um.meta_value LIKE %s) AND DATE(u.user_registered) = %s",
    $cap_key, '%subscriber%', '%administrator%', $today_str
));
$week_ago = date('Y-m-d', strtotime('-7 days', strtotime(linkngon_current_time())));
$new_week = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
     INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = %s
     WHERE (um.meta_value LIKE %s OR um.meta_value LIKE %s) AND u.user_registered >= %s",
    $cap_key, '%subscriber%', '%administrator%', $week_ago
));
$login_today = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'linkngon_last_login' AND meta_value >= %s", $today_str
));

// Get users with data
$data_query = "SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
        COALESCE(ub.balance, 0) as balance,
        (SELECT COALESCE(SUM(amount),0) FROM {$prefix}transactions WHERE user_id = u.ID AND type='shortlink_reward') as earned,
        (SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE user_id = u.ID AND status IN ('completed','cancelled')) as withdrawn,
        (SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE user_id = u.ID AND status IN ('pending','approved')) as pending_withdrawal,
        (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE user_id = u.ID AND step='verified' AND reward_paid=1) as completed
     FROM {$wpdb->users} u
     INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = %s
     LEFT JOIN {$prefix}user_balance ub ON ub.user_id = u.ID
     WHERE (um.meta_value LIKE %s OR um.meta_value LIKE %s) {$search_sql}
     ORDER BY u.ID DESC LIMIT %d OFFSET %d";
$data_args = array_merge(array($cap_key, '%subscriber%', '%administrator%'), $search_args, array($per_page, $offset));
$rows = $wpdb->get_results($wpdb->prepare($data_query, $data_args));

$total_pages = ceil($total / $per_page);
?>
<div class="wrap">
<h1>Người dùng</h1>

<style>
.usr-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
.usr-stat{border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px}
.usr-stat.us1{background:#eff6ff;border:2px solid #bfdbfe} .usr-stat.us2{background:#fef2f2;border:2px solid #fecaca}
.usr-stat.us3{background:#ede9fe;border:2px solid #c4b5fd} .usr-stat.us4{background:#fffbeb;border:2px solid #fde68a}
.usr-val{font-size:22px;font-weight:700;line-height:1.2}
.usr-stat.us1 .usr-val{color:#1e40af} .usr-stat.us2 .usr-val{color:#991b1b}
.usr-stat.us3 .usr-val{color:#5b21b6} .usr-stat.us4 .usr-val{color:#92400e}
.usr-lbl{font-size:12px;color:#6b7280}
.usr-ico{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center}
.usr-ico.ui1{background:#dbeafe;color:#2563eb} .usr-ico.ui2{background:#fecaca;color:#dc2626}
.usr-ico.ui3{background:#c4b5fd;color:#7c3aed} .usr-ico.ui4{background:#fde68a;color:#d97706}
@media(max-width:600px){.usr-stats{grid-template-columns:repeat(2,1fr)} .usr-val{font-size:16px} .usr-stat{padding:12px 14px} .usr-ico{width:38px;height:38px} .usr-ico svg{width:20px;height:20px}}
.usr-tbl th{white-space:nowrap;font-size:13px} .usr-tbl td{font-size:13px}
.usr-tbl .col-id{width:30px;text-align:center}
.usr-tbl .col-name{min-width:110px}
.usr-tbl .col-num{white-space:nowrap;text-align:right}
@media(max-width:600px){.usr-tbl th,.usr-tbl td{padding:4px 5px}
.usr-tbl .col-actions .button-small{font-size:11px;padding:2px 6px;min-height:auto;line-height:1.4}
}
</style>

<div class="usr-stats">
    <div class="usr-stat us1"><div><div class="usr-val"><?php echo number_format($total); ?></div><div class="usr-lbl">User</div></div><div class="usr-ico ui1"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div></div>
    <div class="usr-stat us2"><div><div class="usr-val"><?php echo number_format($new_week); ?></div><div class="usr-lbl">Đăng ký mới (7 ngày)</div></div><div class="usr-ico ui2"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div></div>
    <div class="usr-stat us3"><div><div class="usr-val"><?php echo linkngon_format_money($total_balance_all); ?></div><div class="usr-lbl">Số dư chưa rút</div></div><div class="usr-ico ui3"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
    <div class="usr-stat us4"><div><div class="usr-val"><?php echo number_format($login_today); ?></div><div class="usr-lbl">Đăng nhập hôm nay</div></div><div class="usr-ico ui4"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg></div></div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;margin-bottom:6px">
<p style="margin:0">Tổng: <strong><?php echo intval($total); ?></strong> người dùng</p>
<form method="get" style="margin:0">
    <input type="hidden" name="page" value="linkngon-users">
    <p class="search-box">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Tìm username, email, SĐT...">
        <input type="submit" class="button" value="Tìm kiếm">
    </p>
</form>
</div>

<div style="overflow-x:auto"><table class="widefat striped usr-tbl">
<thead>
<tr>
    <th class="col-id">ID</th>
    <th class="col-name">User</th>
    <th>Email</th>
    <th>SĐT</th>
    <th class="col-num">Hoàn thành</th>
    <th class="col-num">Đã kiếm</th>
    <th class="col-num">Đã rút</th>
    <th class="col-num">Chờ rút</th>
    <th class="col-num">Số dư</th>
    <th>Trạng thái</th>
    <th>Ngày ĐK</th>
    <th class="col-actions">Thao tác</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="12">Không có dữ liệu.</td></tr>
<?php else: foreach($rows as $row):
    $is_banned = get_user_meta($row->ID, 'linkngon_banned', true);
    $phone = get_user_meta($row->ID, 'phone', true);
    $earned = (float)$row->earned;
    $withdrawn = (float)$row->withdrawn;
    $pending_w = (float)$row->pending_withdrawal;
    $available = $earned - $withdrawn - $pending_w;
    if($available < 0) $available = 0;
?>
<tr>
    <td><?php echo intval($row->ID); ?></td>
    <td><strong><?php echo esc_html($row->user_login); ?></strong></td>
    <td><?php echo esc_html($row->user_email); ?></td>
    <td><?php echo esc_html($phone ?: '—'); ?></td>
    <td class="col-num"><?php echo number_format($row->completed); ?></td>
    <td class="col-num"><strong style="color:#46b450"><?php echo linkngon_format_money($earned); ?></strong></td>
    <td class="col-num"><?php echo linkngon_format_money($withdrawn); ?></td>
    <td class="col-num"><?php echo linkngon_format_money($pending_w); ?></td>
    <td class="col-num"><strong style="color:<?php echo $available > 0 ? '#46b450' : '#82878c'; ?>"><?php echo linkngon_format_money($available); ?></strong></td>
    <td>
        <?php if($is_banned): ?>
            <span style="color:#dc3232;font-weight:bold;">Đã cấm</span>
        <?php else: ?>
            <span style="color:#46b450;font-weight:bold;">Hoạt động</span>
        <?php endif; ?>
    </td>
    <td style="white-space:nowrap"><?php echo date('d/m/Y H:i', strtotime($row->user_registered)); ?></td>
    <td class="col-actions" style="white-space:nowrap">
        <button type="button" class="button button-small" onclick="showUserStats(<?php echo $row->ID; ?>,'<?php echo esc_js($row->user_login); ?>')" title="Thống kê" style="margin-right:4px"><span class="dashicons dashicons-chart-bar" style="vertical-align:middle;font-size:14px;width:14px;height:14px;line-height:14px"></span></button>
        <button type="button" class="button button-small" onclick="loginAsUser(<?php echo $row->ID; ?>,'<?php echo esc_js($row->user_login); ?>')" title="Đăng nhập" style="margin-right:4px"><span class="dashicons dashicons-admin-users" style="vertical-align:middle;font-size:14px;width:14px;height:14px;line-height:14px"></span></button>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_user_action'); ?>
            <input type="hidden" name="target_user_id" value="<?php echo $row->ID; ?>">
            <?php if($is_banned): ?>
                <button type="submit" name="user_action" value="unban" class="button button-small button-primary">Bỏ cấm</button>
            <?php else: ?>
                <button type="submit" name="user_action" value="ban" class="button button-small" onclick="return confirm('Cấm user này?\nCác lệnh rút tiền đang chờ sẽ bị từ chối và hoàn tiền.')">Cấm</button>
            <?php endif; ?>
            <button type="submit" name="user_action" value="delete" class="button button-small" style="color:#dc3232" onclick="return confirm('Xóa user <?php echo esc_js($row->user_login); ?>?\nHành động này KHÔNG THỂ hoàn tác!')">Xóa</button>
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
                <a class="button" href="?page=linkngon-users<?php echo $search?"&s=".urlencode($search):""; ?>&paged=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

<div id="userStatsModal"></div>

<script>
var AJAX_URL='<?php echo admin_url("admin-ajax.php"); ?>';
var ADMIN_NONCE='<?php echo wp_create_nonce("linkngon_admin_nonce"); ?>';
function formatMoney(n){return new Intl.NumberFormat('vi-VN').format(n||0)+'đ';}
function escHtml(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}

function showUserStats(uid, username){
    var c=document.getElementById('userStatsModal');
    c.innerHTML='<div style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;display:flex;align-items:flex-start;justify-content:center;padding-top:60px" onclick="if(event.target===this)closeUserStats()"><div style="background:#fff;border-radius:12px;width:95%;max-width:900px;max-height:80vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)"><div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;border-radius:12px 12px 0 0"><h3 style="margin:0;font-size:16px">Thống kê: '+escHtml(username)+'</h3><button onclick="closeUserStats()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:16px">&times;</button></div><div style="padding:20px;text-align:center;color:#6b7280">Đang tải...</div></div></div>';
    var fd=new FormData();fd.append('action','linkngon_admin_user_stats');fd.append('nonce',ADMIN_NONCE);fd.append('user_id',uid);
    fetch(AJAX_URL,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if(!r.success){closeUserStats();alert(r.data||'Lỗi');return;}
        var d=r.data;
        var h='<div style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;display:flex;align-items:flex-start;justify-content:center;padding-top:60px" onclick="if(event.target===this)closeUserStats()"><div style="background:#fff;border-radius:12px;width:95%;max-width:900px;max-height:80vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)">';
        h+='<div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;border-radius:12px 12px 0 0"><h3 style="margin:0;font-size:16px">Thống kê: '+escHtml(username)+'</h3><button onclick="closeUserStats()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:16px">&times;</button></div>';
        h+='<div style="padding:20px"><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">';
        h+='<div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px"><h4 style="margin:0 0 10px;font-size:14px">Thống kê chung</h4>';
        h+='<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f3f4f6"><span style="color:#6b7280;font-size:13px">Số dư</span><span style="font-weight:600;color:#059669">'+formatMoney(d.balance)+'</span></div>';
        h+='<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f3f4f6"><span style="color:#6b7280;font-size:13px">Ngày tham gia</span><span style="font-weight:600">'+escHtml(d.registered)+'</span></div>';
        h+='<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f3f4f6"><span style="color:#6b7280;font-size:13px">TOTAL_LOAD</span><span style="font-weight:600">'+(d.total_load||0).toLocaleString()+'</span></div>';
        h+='<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f3f4f6"><span style="color:#6b7280;font-size:13px">VIEW THÁNG</span><span style="font-weight:600;color:#2563eb">'+(d.month_views||0)+' ('+(d.month_rate||0)+'%)</span></div>';
        h+='<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f3f4f6"><span style="color:#6b7280;font-size:13px">CHANGE_IP</span><span style="font-weight:600;color:#dc2626">'+(d.change_ip||0)+'</span></div>';
        h+='<div style="display:flex;justify-content:space-between;padding:5px 0"><span style="color:#6b7280;font-size:13px">IP >3 LẦN</span><span style="font-weight:600;color:#dc2626">'+(d.ip_over_3||0)+'</span></div>';
        h+='</div>';
        h+='<div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px"><h4 style="margin:0 0 10px;font-size:14px;color:#dc2626">IP xuất hiện > 3 lần</h4>';
        if(d.top_ips&&d.top_ips.length){h+='<table style="width:100%;font-size:12px;border-collapse:collapse"><thead><tr><th style="text-align:left;padding:4px;border-bottom:1px solid #e5e7eb">IP</th><th style="text-align:right;padding:4px;border-bottom:1px solid #e5e7eb">Lần</th></tr></thead><tbody>';d.top_ips.forEach(function(ip){h+='<tr><td style="padding:4px;font-family:monospace;font-size:11px">'+escHtml(ip.ip)+'</td><td style="text-align:right;padding:4px"><span style="background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:8px;font-size:11px;font-weight:600">'+ip.count+'</span></td></tr>';});h+='</tbody></table>';}
        else{h+='<p style="color:#9ca3af;font-size:13px">Không có IP nào > 3 lần</p>';}
        h+='</div></div>';
        if(d.monthly&&d.monthly.length){h+='<div style="border:1px solid #e5e7eb;border-radius:10px;padding:14px;margin-top:16px"><h4 style="margin:0 0 10px;font-size:14px">Thống kê theo tháng</h4><table style="width:100%;font-size:12px;border-collapse:collapse"><thead><tr><th style="text-align:left;padding:5px">Tháng</th><th style="text-align:center;padding:5px">Load</th><th style="text-align:center;padding:5px">View</th><th style="text-align:center;padding:5px">Tỷ lệ</th><th style="text-align:right;padding:5px">Thu nhập</th></tr></thead><tbody>';d.monthly.forEach(function(m){var rate=m.load>0?((m.views/m.load)*100).toFixed(1):'0.0';h+='<tr><td style="padding:5px">'+escHtml(m.month)+'</td><td style="text-align:center;padding:5px">'+m.load.toLocaleString()+'</td><td style="text-align:center;padding:5px;color:#2563eb;font-weight:600">'+m.views.toLocaleString()+'</td><td style="text-align:center;padding:5px;color:#dc2626;font-weight:600">'+rate+'%</td><td style="text-align:right;padding:5px;font-weight:600">'+formatMoney(m.earned)+'</td></tr>';});h+='</tbody></table></div>';}
        h+='</div></div></div>';
        c.innerHTML=h;
    }).catch(function(){closeUserStats();alert('Lỗi kết nối');});
}
function closeUserStats(){document.getElementById('userStatsModal').innerHTML='';}

function loginAsUser(uid, name){
    if(!confirm('Đăng nhập với tư cách user "'+name+'"?')) return;
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
