<?php
if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'traffictop_';

// Handle actions
if(isset($_POST['user_action']) && wp_verify_nonce($_POST['_wpnonce'],'traffictop_user_action')){
    $target_id = intval($_POST['target_user_id']);
    $action = sanitize_text_field($_POST['user_action']);

    if($action === 'ban'){
        update_user_meta($target_id, 'traffictop_banned', true);
        // Reject pending withdrawals
        $pending_wds = $wpdb->get_results($wpdb->prepare(
            "SELECT id, amount FROM {$prefix}withdrawals WHERE user_id=%d AND status IN ('pending','approved') FOR UPDATE",
            $target_id
        ));
        foreach($pending_wds as $wd){
            $wpdb->update("{$prefix}withdrawals", array('status'=>'rejected','admin_note'=>'Tự động hủy do tài khoản bị cấm','processed_at'=>traffictop_current_time()), array('id'=>$wd->id));
            $wpdb->insert("{$prefix}transactions", array('user_id'=>$target_id,'type'=>'refund','amount'=>$wd->amount,'description'=>'Hoàn tiền withdrawal #'.$wd->id.' (tài khoản bị cấm)','reference_id'=>$wd->id,'reference_type'=>'withdrawal','status'=>'completed','created_at'=>traffictop_current_time()));
        }
        echo '<div class="notice notice-warning"><p>User #'.$target_id.' đã bị cấm.</p></div>';
    } elseif($action === 'unban'){
        delete_user_meta($target_id, 'traffictop_banned');
        echo '<div class="notice notice-success"><p>User #'.$target_id.' đã được bỏ cấm.</p></div>';
    } elseif($action === 'delete'){
        if(function_exists('traffictop_admin_do_delete_user')) traffictop_admin_do_delete_user($target_id);
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
$today_str = date('Y-m-d', strtotime(traffictop_current_time()));
$new_today = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
     INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = %s
     WHERE (um.meta_value LIKE %s OR um.meta_value LIKE %s) AND DATE(u.user_registered) = %s",
    $cap_key, '%subscriber%', '%administrator%', $today_str
));
$week_ago = date('Y-m-d', strtotime('-7 days', strtotime(traffictop_current_time())));
$new_week = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
     INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = %s
     WHERE (um.meta_value LIKE %s OR um.meta_value LIKE %s) AND u.user_registered >= %s",
    $cap_key, '%subscriber%', '%administrator%', $week_ago
));
$login_today = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'traffictop_last_login' AND meta_value >= %s", $today_str
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
    <div class="usr-stat us3"><div><div class="usr-val"><?php echo traffictop_format_money($total_balance_all); ?></div><div class="usr-lbl">Số dư chưa rút</div></div><div class="usr-ico ui3"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
    <div class="usr-stat us4"><div><div class="usr-val"><?php echo number_format($login_today); ?></div><div class="usr-lbl">Đăng nhập hôm nay</div></div><div class="usr-ico ui4"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg></div></div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px;margin-bottom:6px">
<p style="margin:0">Tổng: <strong><?php echo intval($total); ?></strong> người dùng</p>
<form method="get" style="margin:0">
    <input type="hidden" name="page" value="traffictop-users">
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
    $is_banned = get_user_meta($row->ID, 'traffictop_banned', true);
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
    <td class="col-num"><strong style="color:#46b450"><?php echo traffictop_format_money($earned); ?></strong></td>
    <td class="col-num"><?php echo traffictop_format_money($withdrawn); ?></td>
    <td class="col-num"><?php echo traffictop_format_money($pending_w); ?></td>
    <td class="col-num"><strong style="color:<?php echo $available > 0 ? '#46b450' : '#82878c'; ?>"><?php echo traffictop_format_money($available); ?></strong></td>
    <td>
        <?php if($is_banned): ?>
            <span style="color:#dc3232;font-weight:bold;">Đã cấm</span>
        <?php elseif(!traffictop_is_email_verified($row->ID)): ?>
            <span style="color:#f59e0b;font-weight:bold;">Chưa xác nhận</span>
        <?php else: ?>
            <span style="color:#46b450;font-weight:bold;">Hoạt động</span>
        <?php endif; ?>
    </td>
    <td style="white-space:nowrap"><?php echo date('d/m/Y H:i', strtotime($row->user_registered)); ?></td>
    <td class="col-actions" style="white-space:nowrap">
        <button type="button" class="button button-small" onclick="showUserStats(<?php echo $row->ID; ?>,'<?php echo esc_js($row->user_login); ?>')" title="Thống kê" style="margin-right:4px"><span class="dashicons dashicons-chart-bar" style="vertical-align:middle;font-size:14px;width:14px;height:14px;line-height:14px"></span></button>
        <button type="button" class="button button-small" onclick="editUserOpen(<?php echo $row->ID; ?>,'<?php echo esc_js($row->user_login); ?>','<?php echo esc_js($row->display_name); ?>','<?php echo esc_js($row->user_email); ?>','<?php echo esc_js($phone); ?>')" title="Sửa thông tin" style="background:#2563eb;color:#fff;border-color:#2563eb;margin-right:4px"><span class="dashicons dashicons-edit" style="vertical-align:middle;font-size:14px;width:14px;height:14px;line-height:14px"></span></button>
        <button type="button" class="button button-small" onclick="loginAsUser(<?php echo $row->ID; ?>,'<?php echo esc_js($row->user_login); ?>')" title="Đăng nhập" style="margin-right:4px"><span class="dashicons dashicons-admin-users" style="vertical-align:middle;font-size:14px;width:14px;height:14px;line-height:14px"></span></button>
        <?php if(!traffictop_is_email_verified($row->ID)): ?>
        <button type="button" class="button button-small" onclick="activateUser(<?php echo $row->ID; ?>,'<?php echo esc_js($row->user_login); ?>',this)" title="Kích hoạt tài khoản (bỏ qua xác nhận email)" style="margin-right:4px;color:#059669;border-color:#059669"><span class="dashicons dashicons-yes" style="vertical-align:middle;font-size:14px;width:14px;height:14px;line-height:14px"></span></button>
        <button type="button" class="button button-small" onclick="resendVerify(<?php echo $row->ID; ?>,'<?php echo esc_js($row->user_login); ?>')" title="Gửi lại email xác nhận" style="background:#f59e0b;color:#fff;border-color:#f59e0b;margin-right:4px"><span class="dashicons dashicons-email" style="vertical-align:middle;font-size:14px;width:14px;height:14px;line-height:14px"></span></button>
        <?php endif; ?>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('traffictop_user_action'); ?>
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

<?php if($total_pages > 1):
    $pag_params = array('page' => 'traffictop-users');
    if($search) $pag_params['s'] = $search;
?>
<div class="tablenav bottom"><div class="tablenav-pages">
    <span style="font-size:12px;color:#787c82;margin-right:10px">
        Trang <?php echo $page_num; ?>/<?php echo $total_pages; ?>
        (<?php echo number_format($total); ?> kết quả)
    </span>
    <?php if($page_num > 1): ?>
        <a class="button" href="?<?php echo esc_attr(http_build_query(array_merge($pag_params, array('paged'=>$page_num-1)))); ?>">« Trước</a>
    <?php endif; ?>
    <?php if($page_num < $total_pages): ?>
        <a class="button" href="?<?php echo esc_attr(http_build_query(array_merge($pag_params, array('paged'=>$page_num+1)))); ?>">Sau »</a>
    <?php endif; ?>
</div></div>
<?php endif; ?>

<div id="userStatsModal"></div>
<div id="editUserModal"></div>

<script>
var AJAX_URL='<?php echo admin_url("admin-ajax.php"); ?>';
var ADMIN_NONCE='<?php echo wp_create_nonce("traffictop_admin_nonce"); ?>';
function formatMoney(n){return new Intl.NumberFormat('vi-VN').format(n||0)+'đ';}
function escHtml(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}

function showUserStats(uid, username){
    var c=document.getElementById('userStatsModal');
    c.innerHTML='<div style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;display:flex;align-items:flex-start;justify-content:center;padding-top:40px" onclick="if(event.target===this)closeUserStats()"><div style="background:#fff;border-radius:12px;width:95%;max-width:1100px;max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)"><div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff;border-radius:12px 12px 0 0"><h3 style="margin:0;font-size:16px">🔍 Kiểm tra gian lận: '+escHtml(username)+' (toàn thời gian)</h3><button onclick="closeUserStats()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:16px">&times;</button></div><div id="userStatsBody" style="padding:20px;text-align:center;color:#6b7280">Đang tải...</div></div></div>';
    var fd=new FormData();fd.append('action','traffictop_admin_user_stats');fd.append('nonce',ADMIN_NONCE);fd.append('user_id',uid);
    fetch(AJAX_URL,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if(!r.success){closeUserStats();alert(r.data||'Lỗi');return;}
        var d=r.data;
        var body=document.getElementById('userStatsBody');
        if(!body){return;}
        body.style.textAlign='left';body.style.color='#111';
        var fm=function(n){return Number(n||0).toLocaleString('vi-VN')+'đ';};
        var fn=function(n){return Number(n||0).toLocaleString('vi-VN');};
        var escr=function(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');};
        var h='';
        // Summary card 4 cell — riêng cho user_stats
        h+='<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px">';
        h+='<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px"><h4 style="font-size:13px;margin:0 0 8px;color:#374151">Số dư</h4><div style="font-size:18px;font-weight:700;color:#059669">'+fm(d.balance)+'</div></div>';
        h+='<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px"><h4 style="font-size:13px;margin:0 0 8px;color:#374151">View hợp lệ</h4><div style="font-size:18px;font-weight:700;color:#2563eb">'+fn(d.views)+'</div></div>';
        h+='<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px"><h4 style="font-size:13px;margin:0 0 8px;color:#374151">Tổng thu nhập</h4><div style="font-size:18px;font-weight:700;color:#059669">'+fm(d.total_earned)+'</div></div>';
        h+='<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px"><h4 style="font-size:13px;margin:0 0 8px;color:#374151">Ngày tham gia</h4><div style="font-size:14px;font-weight:600">'+escr(d.registered)+'</div></div>';
        h+='</div>';
        // Risk badge with reasons (giống wd popup)
        var rl=d.risk_level||d.risk||'safe';
        var rcolor={safe:'#dcfce7',low:'#fef9c3',medium:'#fed7aa',high:'#fecaca'}[rl]||'#f3f4f6';
        var rtxt={safe:'#166534',low:'#854d0e',medium:'#9a3412',high:'#991b1b'}[rl]||'#374151';
        var ricon={safe:'✓',low:'!',medium:'⚠',high:'✕'}[rl]||'?';
        var rlbl={safe:'AN TOÀN',low:'RỦI RO THẤP',medium:'RỦI RO TRUNG BÌNH',high:'RỦI RO CAO'}[rl]||rl.toUpperCase();
        h+='<div style="background:'+rcolor+';padding:12px 16px;border-radius:8px;border-left:4px solid '+rtxt+';margin-bottom:16px">';
        h+='<div style="margin-bottom:8px"><span style="font-weight:800;color:'+rtxt+';font-size:14px">'+ricon+' '+rlbl+'</span></div>';
        h+='<div style="font-size:12px;font-weight:600;margin-bottom:4px;color:'+rtxt+'">Lý do:</div>';
        h+='<ul style="margin:0;padding-left:20px;font-size:12px;line-height:1.7;color:'+rtxt+'">';
        h+='<li>Tỷ lệ hoàn thành: '+(d.completion_rate||0)+'% — '+escr(d.completion_fit||'')+'</li>';
        h+='<li>IP trùng lặp: '+(d.ip_over_3||0)+' IP >3 — '+escr(d.ip_conc_fit||'')+'</li>';
        if(d.risk_reasons&&d.risk_reasons.length){for(var ri=0;ri<d.risk_reasons.length;ri++)h+='<li>'+escr(d.risk_reasons[ri])+'</li>';}
        h+='</ul></div>';
        // Stats table
        h+='<h4 style="margin:0 0 8px;font-size:13px">Thống kê (toàn thời gian)</h4>';
        h+='<table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:14px">';
        h+='<thead><tr><th style="background:#f3f4f6;padding:6px 8px;text-align:left">Click</th><th style="background:#f3f4f6;padding:6px 8px;text-align:left">View trả tiền (%)</th><th style="background:#f3f4f6;padding:6px 8px;text-align:left">Bypass</th><th style="background:#f3f4f6;padding:6px 8px;text-align:left">Change IP</th><th style="background:#f3f4f6;padding:6px 8px;text-align:left">Max IP</th><th style="background:#f3f4f6;padding:6px 8px;text-align:left">Adblock</th><th style="background:#f3f4f6;padding:6px 8px;text-align:left">IP &gt;3</th></tr></thead>';
        h+='<tbody><tr>';
        h+='<td style="padding:5px 8px">'+fn(d.clicks)+'</td>';
        h+='<td style="padding:5px 8px">'+fn(d.paid_views)+' ('+(d.completion_rate||0)+'%)</td>';
        h+='<td style="padding:5px 8px">'+(d.bypass>0?'<span style="color:#dc2626;font-weight:600">'+d.bypass+'</span>':'0')+'</td>';
        h+='<td style="padding:5px 8px">'+(d.change_ip>0?'<span style="color:#d97706;font-weight:600">'+d.change_ip+'</span>':'0')+'</td>';
        h+='<td style="padding:5px 8px">'+(d.max_ip>0?'<span style="color:#dc2626;font-weight:600">'+d.max_ip+'</span>':'0')+'</td>';
        h+='<td style="padding:5px 8px">'+(d.adblock>0?'<span style="color:#d97706;font-weight:600">'+d.adblock+'</span>':'0')+'</td>';
        h+='<td style="padding:5px 8px">'+(d.ip_over_3>0?'<span style="color:#dc2626;font-weight:600">'+d.ip_over_3+'</span>':'0')+'</td>';
        h+='</tr></tbody></table>';
        // Source badges
        if(d.sources&&d.sources.length){
            var src_total=0;for(var si=0;si<d.sources.length;si++)src_total+=d.sources[si].cnt;
            h+='<h4 style="margin:0 0 8px;font-size:13px">Nguồn (badge)</h4>';
            h+='<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px">';
            for(var si2=0;si2<d.sources.length;si2++){
                var s=d.sources[si2];
                var pct=src_total>0?Math.round(s.cnt/src_total*1000)/10:0;
                h+='<span style="background:#eff6ff;color:#1e40af;padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600">'+escr(s.label)+' '+fn(s.cnt)+' ('+pct+'%)</span>';
            }
            h+='</div>';
        }
        // Top referer URLs (with UTM)
        if(d.top_referers&&d.top_referers.length){
            var ref_total=0;for(var ri2=0;ri2<d.top_referers.length;ri2++)ref_total+=d.top_referers[ri2].cnt;
            h+='<h4 style="margin:0 0 8px;font-size:13px">Nguồn URL chi tiết ('+d.top_referers.length+' URL)</h4>';
            h+='<div style="max-height:360px;overflow-y:auto;border:1px solid #f3f4f6;border-radius:6px;margin-bottom:14px">';
            h+='<table style="width:100%;border-collapse:collapse;font-size:12px;margin:0">';
            h+='<thead style="position:sticky;top:0;background:#fff;z-index:1"><tr><th style="background:#f3f4f6;padding:6px 8px;text-align:left;width:90px">Loại</th><th style="background:#f3f4f6;padding:6px 8px;text-align:left">URL</th><th style="background:#f3f4f6;padding:6px 8px;text-align:right;width:60px">Lần</th><th style="background:#f3f4f6;padding:6px 8px;text-align:right;width:50px">%</th><th style="background:#f3f4f6;padding:6px 8px;text-align:right;width:90px">Tiền</th></tr></thead><tbody>';
            for(var ri3=0;ri3<d.top_referers.length;ri3++){
                var r=d.top_referers[ri3];
                var pct=ref_total>0?Math.round(r.cnt/ref_total*1000)/10:0;
                var url=r.referer||'(trực tiếp)';
                h+='<tr>';
                h+='<td style="padding:5px 8px"><span style="background:#f3f4f6;padding:2px 6px;border-radius:3px;font-size:10px">'+escr(r.label)+'</span></td>';
                h+='<td style="padding:5px 8px;word-break:break-all;font-family:monospace;font-size:11px;line-height:1.4">'+escr(url);
                if(r.utm_source||r.utm_medium||r.utm_campaign){
                    h+='<div style="margin-top:3px;padding:3px 6px;background:#fef9c3;color:#713f12;border-radius:3px;font-size:10px">🏷';
                    if(r.utm_source)h+=' source: '+escr(r.utm_source);
                    if(r.utm_medium)h+=' · medium: '+escr(r.utm_medium);
                    if(r.utm_campaign)h+=' · campaign: '+escr(r.utm_campaign);
                    h+='</div>';
                }
                h+='</td>';
                h+='<td style="padding:5px 8px;text-align:right">'+fn(r.cnt)+'</td>';
                h+='<td style="padding:5px 8px;text-align:right">'+pct+'%</td>';
                h+='<td style="padding:5px 8px;text-align:right">'+fm(r.earned)+'</td>';
                h+='</tr>';
            }
            h+='</tbody></table></div>';
        }
        // Top IPs
        if(d.top_ips&&d.top_ips.length){
            var ip_total=0;for(var ii=0;ii<d.top_ips.length;ii++)ip_total+=d.top_ips[ii].cnt;
            h+='<h4 style="margin:0 0 8px;font-size:13px">IP ('+d.top_ips.length+' IP / '+fn(d.paid_views)+' View trả tiền)</h4>';
            h+='<div style="max-height:360px;overflow-y:auto;border:1px solid #f3f4f6;border-radius:6px;margin-bottom:14px">';
            h+='<table style="width:100%;border-collapse:collapse;font-size:12px;margin:0">';
            h+='<thead style="position:sticky;top:0;background:#fff;z-index:1"><tr><th style="background:#f3f4f6;padding:6px 8px;text-align:left">IP</th><th style="background:#f3f4f6;padding:6px 8px;text-align:right;width:70px">Số lần</th><th style="background:#f3f4f6;padding:6px 8px;text-align:right;width:50px">%</th><th style="background:#f3f4f6;padding:6px 8px;text-align:right;width:100px">Tiền</th></tr></thead><tbody>';
            for(var ii2=0;ii2<d.top_ips.length;ii2++){
                var ip=d.top_ips[ii2];
                var pct=ip_total>0?Math.round(ip.cnt/ip_total*1000)/10:0;
                h+='<tr><td style="padding:5px 8px"><code style="font-size:11px">'+escr(ip.ip)+'</code></td>';
                h+='<td style="padding:5px 8px;text-align:right">'+fn(ip.cnt)+'</td>';
                h+='<td style="padding:5px 8px;text-align:right">'+pct+'%</td>';
                h+='<td style="padding:5px 8px;text-align:right">'+fm(ip.earned)+'</td></tr>';
            }
            h+='</tbody></table></div>';
        }
        // Shortlinks with Link gốc
        if(d.shortlinks&&d.shortlinks.length){
            h+='<h4 style="margin:0 0 8px;font-size:13px">Shortlink ('+d.shortlinks.length+')</h4>';
            h+='<div style="max-height:360px;overflow-y:auto;border:1px solid #f3f4f6;border-radius:6px;margin-bottom:14px">';
            h+='<table style="width:100%;border-collapse:collapse;font-size:12px;margin:0">';
            h+='<thead style="position:sticky;top:0;background:#fff;z-index:1"><tr><th style="background:#f3f4f6;padding:6px 8px;text-align:left">Code</th><th style="background:#f3f4f6;padding:6px 8px;text-align:left;width:180px">Link gốc</th><th style="background:#f3f4f6;padding:6px 8px;text-align:left">Nguồn (top 5)</th><th style="background:#f3f4f6;padding:6px 8px;text-align:right;width:70px">Views</th><th style="background:#f3f4f6;padding:6px 8px;text-align:right;width:100px">Tiền</th></tr></thead><tbody>';
            for(var li=0;li<d.shortlinks.length;li++){
                var lk=d.shortlinks[li];
                var origHtml='—';
                if(lk.original_url){
                    var rawUrl=String(lk.original_url);
                    // X1: only emit an href for http(s) URLs (block javascript:/data: stored XSS in admin browser).
                    var safeUrl=/^https?:\/\//i.test(rawUrl)?rawUrl.replace(/"/g,'&quot;'):'';
                    var host=rawUrl.replace(/^https?:\/\//,'').replace(/^www\./,'').split('/')[0].split('?')[0];
                    if(host.length>22)host=host.substring(0,22)+'…';
                    origHtml=safeUrl
                        ? '<a href="'+safeUrl+'" target="_blank" rel="noopener noreferrer" title="'+safeUrl+'" style="color:#2563eb;text-decoration:none;font-size:11px">'+escr(host)+' ↗</a>'
                        : escr(host);
                }
                var srcHtml='';
                if(lk.sources&&lk.sources.length){
                    for(var si3=0;si3<lk.sources.length;si3++){
                        var s2=lk.sources[si3];
                        var tip=(s2.urls&&s2.urls.length)?s2.urls.join('\n').replace(/"/g,'&quot;'):'';
                        srcHtml+='<span title="'+tip+'" style="background:#f3f4f6;padding:2px 6px;border-radius:3px;font-size:10px;margin-right:3px;display:inline-block;cursor:help">'+escr(s2.label);
                        if(s2.count>1)srcHtml+=' ('+s2.count+')';
                        srcHtml+='</span>';
                    }
                }else{srcHtml='<span style="color:#9ca3af;font-size:10px">—</span>';}
                h+='<tr><td style="padding:5px 8px"><code style="font-size:11px">'+escr(lk.code)+'</code></td>';
                h+='<td style="padding:5px 8px;white-space:nowrap">'+origHtml+'</td>';
                h+='<td style="padding:5px 8px">'+srcHtml+'</td>';
                h+='<td style="padding:5px 8px;text-align:right">'+fn(lk.views)+'</td>';
                h+='<td style="padding:5px 8px;text-align:right">'+fm(lk.earned)+'</td></tr>';
            }
            h+='</tbody></table></div>';
        }
        // Monthly stats — riêng cho user_stats popup, append CUỐI cùng
        if(d.monthly&&d.monthly.length){
            h+='<h4 style="margin:0 0 8px;font-size:13px">Thống kê theo tháng (6 tháng gần nhất)</h4>';
            h+='<table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:14px">';
            h+='<thead><tr><th style="background:#f3f4f6;padding:6px 8px;text-align:left">Tháng</th><th style="background:#f3f4f6;padding:6px 8px;text-align:center">Load</th><th style="background:#f3f4f6;padding:6px 8px;text-align:center">View</th><th style="background:#f3f4f6;padding:6px 8px;text-align:center">Tỷ lệ</th><th style="background:#f3f4f6;padding:6px 8px;text-align:right">Thu nhập</th></tr></thead><tbody>';
            d.monthly.forEach(function(m){
                var rate=m.load>0?((m.views/m.load)*100).toFixed(1):'0.0';
                h+='<tr><td style="padding:5px 8px">'+escr(m.month)+'</td>';
                h+='<td style="padding:5px 8px;text-align:center">'+fn(m.load)+'</td>';
                h+='<td style="padding:5px 8px;text-align:center;color:#2563eb;font-weight:600">'+fn(m.views)+'</td>';
                h+='<td style="padding:5px 8px;text-align:center;color:#dc2626;font-weight:600">'+rate+'%</td>';
                h+='<td style="padding:5px 8px;text-align:right;font-weight:600">'+fm(m.earned)+'</td></tr>';
            });
            h+='</tbody></table>';
        }
        body.innerHTML=h;
    }).catch(function(){closeUserStats();alert('Lỗi kết nối');});
}
function closeUserStats(){document.getElementById('userStatsModal').innerHTML='';}

function editUserEsc(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
function editUserOpen(uid, login, displayName, email, phone){
    var c=document.getElementById('editUserModal');
    var h='<div style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;display:flex;align-items:flex-start;justify-content:center;padding-top:60px" onclick="if(event.target===this)editUserClose()">';
    h+='<div style="background:#fff;border-radius:12px;width:95%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,.3)">';
    h+='<div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:linear-gradient(135deg,#2563eb,#3b82f6);color:#fff;border-radius:12px 12px 0 0">';
    h+='<h3 style="margin:0;font-size:16px">Sửa thông tin: '+editUserEsc(login)+'</h3>';
    h+='<button onclick="editUserClose()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:16px">&times;</button></div>';
    h+='<form id="editUserForm" onsubmit="editUserSubmit(event,'+uid+')" style="padding:20px">';
    h+='<div style="margin-bottom:14px"><label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">Tên hiển thị</label>';
    h+='<input type="text" name="display_name" required value="'+editUserEsc(displayName)+'" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px"></div>';
    h+='<div style="margin-bottom:14px"><label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">Email</label>';
    h+='<input type="email" name="email" required value="'+editUserEsc(email)+'" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px"></div>';
    h+='<div style="margin-bottom:14px"><label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">Số điện thoại</label>';
    h+='<input type="text" name="phone" value="'+editUserEsc(phone)+'" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px"></div>';
    h+='<div style="margin-bottom:16px"><label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px">Mật khẩu mới <span style="color:#6b7280;font-weight:400">(để trống nếu không đổi)</span></label>';
    h+='<input type="password" name="password" minlength="6" autocomplete="new-password" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px"></div>';
    h+='<div id="editUserMsg" style="font-size:13px;margin-bottom:10px"></div>';
    h+='<div style="display:flex;gap:8px;justify-content:flex-end">';
    h+='<button type="button" onclick="editUserClose()" class="button">Hủy</button>';
    h+='<button type="submit" class="button button-primary">Lưu</button></div>';
    h+='</form></div></div>';
    c.innerHTML=h;
}
function editUserClose(){document.getElementById('editUserModal').innerHTML='';}
function editUserSubmit(e, uid){
    e.preventDefault();
    var form=e.target;
    var msg=document.getElementById('editUserMsg');
    var btn=form.querySelector('button[type=submit]');
    btn.disabled=true;btn.textContent='Đang lưu...';
    var fd=new FormData(form);
    fd.append('action','traffictop_admin_edit_user');
    fd.append('nonce',ADMIN_NONCE);
    fd.append('user_id',uid);
    fetch(AJAX_URL,{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){return r.json()})
    .then(function(r){
        if(r.success){msg.style.color='#059669';msg.textContent='Đã lưu. Đang tải lại...';setTimeout(function(){location.reload();},600);}
        else{btn.disabled=false;btn.textContent='Lưu';msg.style.color='#dc2626';msg.textContent='Lỗi: '+(r.data||'Không thể cập nhật');}
    })
    .catch(function(){btn.disabled=false;btn.textContent='Lưu';msg.style.color='#dc2626';msg.textContent='Lỗi kết nối';});
}

function activateUser(uid, name, btn){
    if(!confirm('Kích hoạt tài khoản "'+name+'" mà không cần xác nhận email?')) return;
    btn.disabled=true;
    var fd=new FormData();
    fd.append('action','traffictop_admin_activate_user');
    fd.append('nonce',ADMIN_NONCE);
    fd.append('user_id',uid);
    fetch(AJAX_URL,{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){return r.json()})
    .then(function(r){
        if(r.success){location.reload();}
        else{alert(r.data||'Lỗi');btn.disabled=false;}
    })
    .catch(function(){alert('Lỗi kết nối');btn.disabled=false;});
}

function resendVerify(uid, name){
    if(!confirm('Gửi lại email xác nhận cho "'+name+'"?')) return;
    var fd=new FormData();
    fd.append('action','traffictop_admin_resend_verification');
    fd.append('nonce',ADMIN_NONCE);
    fd.append('user_id',uid);
    fetch(AJAX_URL,{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){return r.json()})
    .then(function(r){
        if(r.success) alert('Đã gửi lại email xác nhận cho "'+name+'"');
        else alert('Lỗi: '+(r.data||'Không thể gửi'));
    }).catch(function(){alert('Lỗi kết nối');});
}

function loginAsUser(uid, name){
    if(!confirm('Đăng nhập với tư cách user "'+name+'"?')) return;
    var fd=new FormData();
    fd.append('action','traffictop_admin_login_as_user');
    fd.append('nonce','<?php echo wp_create_nonce("traffictop_admin_nonce"); ?>');
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
