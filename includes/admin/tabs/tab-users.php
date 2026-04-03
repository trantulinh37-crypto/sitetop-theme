<?php
if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Search & pagination
$search = isset($_POST['user_search']) ? sanitize_text_field($_POST['user_search']) : '';
$page_num = max(1, intval($_POST['user_page'] ?? 1));
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

// Summary totals
$total_earned_all = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$prefix}transactions WHERE type='shortlink_reward'");
$total_balance_all = (float) $wpdb->get_var("SELECT COALESCE(SUM(balance),0) FROM {$prefix}user_balance WHERE balance > 0");
$today_str = date('Y-m-d', strtotime(linkngon_current_time()));
$new_today = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
     INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = %s
     WHERE (um.meta_value LIKE %s OR um.meta_value LIKE %s) AND DATE(u.user_registered) = %s",
    $cap_key, '%subscriber%', '%administrator%', $today_str
));
$total_commission = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$prefix}transactions WHERE type IN ('earn','bonus') AND amount > 0");

// Get users with all data
$data_query = "SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
        COALESCE(ub.balance, 0) as balance,
        COALESCE(ub.total_earned, 0) as total_earned,
        (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE user_id = u.ID AND step='verified') as completed,
        (SELECT COALESCE(SUM(amount),0) FROM {$prefix}transactions WHERE user_id = u.ID AND type='shortlink_reward') as earned,
        (SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE user_id = u.ID AND status IN ('completed','cancelled')) as withdrawn,
        (SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE user_id = u.ID AND status IN ('pending','approved')) as pending_withdrawal
     FROM {$wpdb->users} u
     INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = %s
     LEFT JOIN {$prefix}user_balance ub ON ub.user_id = u.ID
     WHERE (um.meta_value LIKE %s OR um.meta_value LIKE %s) {$search_sql}
     ORDER BY u.ID DESC LIMIT %d OFFSET %d";
$data_args = array_merge(array($cap_key, '%subscriber%', '%administrator%'), $search_args, array($per_page, $offset));
$rows = $wpdb->get_results($wpdb->prepare($data_query, $data_args));

$total_pages = ceil($total / $per_page);
?>

<style>
.users-summary { display:flex; gap:24px; align-items:center; padding:12px 0; font-size:14px; flex-wrap:wrap; }
.users-summary strong { font-size:16px; }
.users-summary .sum-earned { color:var(--ok); font-weight:700; font-size:16px; }
.users-summary .sum-balance { color:var(--pd); font-weight:700; font-size:16px; }
.users-search { display:flex; gap:0; margin-left:auto; }
.users-search input { padding:6px 12px; border:1px solid #8c8f94; border-radius:4px 0 0 4px; font-size:14px; min-width:220px; background:#fff; color:#2c3338; }
.users-search input:focus { border-color:#2271b1; box-shadow:0 0 0 1px #2271b1; outline:none; }
.users-search button { padding:6px 16px; border:1px solid #8c8f94; border-left:none; border-radius:0 4px 4px 0; background:#f6f7f7; color:#2c3338; font-weight:600; cursor:pointer; font-size:14px; }
.users-search button:hover { background:#f0f0f1; border-color:#0a4b78; color:#0a4b78; }

.users-table-wrap { width:100%; overflow-x:auto; }
table.users-table { width:100%; border-collapse:collapse; font-size:12px; border:1px solid #d1d5db; }
table.users-table thead th { background:#f3f4f6; padding:8px 10px; text-align:left; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:0.03em; color:#6b7280; border:1px solid #d1d5db; white-space:nowrap; }
table.users-table tbody td { padding:8px 10px; border:1px solid #e5e7eb; vertical-align:middle; white-space:nowrap; }
.users-table tbody tr:hover { background:rgba(13,79,79,0.02); }
.users-table .user-name { font-weight:700; color:var(--txt); }
.users-table .user-email { font-size:12px; color:var(--txtl); }
.users-table .money { font-weight:600; }
.users-table .money.green { color:var(--ok); }
.users-table .money.red { color:var(--err); }
.users-table .money.blue { color:var(--info); }

.balance-badge { display:inline-block; padding:2px 8px; border-radius:4px; font-weight:700; font-size:12px; color:#fff; }
.balance-badge.positive { background:linear-gradient(135deg,#10b981,#059669); }
.balance-badge.zero { color:var(--txtm); background:transparent; font-weight:400; }

.status-badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; white-space:nowrap; }
.status-badge.active { background:#D1FAE5; color:#065F46; }
.status-badge.banned { background:#FEE2E2; color:#991B1B; }
.status-badge.unverified { background:#FEF3C7; color:#92400E; }

.action-btns { display:flex; gap:4px; flex-wrap:nowrap; }
.action-btn { width:34px; height:34px; border-radius:8px; border:none; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; color:#fff; font-size:14px; transition:transform 0.15s,opacity 0.15s; }
.action-btn:hover { transform:scale(1.1); opacity:0.9; }
.action-btn.stats { background:#2563EB; }
.action-btn.login { background:#F59E0B; }
.action-btn.ban { background:#EF4444; }
.action-btn.unban { background:#10B981; }
.action-btn.delete { background:#DC2626; }

.users-pagination { display:flex; gap:4px; justify-content:center; margin-top:16px; flex-wrap:wrap; }
.users-pagination button { padding:6px 12px; border:1px solid var(--brd); border-radius:6px; background:#fff; cursor:pointer; font-size:13px; }
.users-pagination button.active { background:var(--p); color:#fff; border-color:var(--p); }
.users-pagination button:hover:not(.active) { background:var(--bg); }

/* Stats Modal */
.user-stats-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:99999; display:flex; align-items:flex-start; justify-content:center; padding-top:60px; animation:fadeIn 0.2s; }
.user-stats-modal { background:#fff; border-radius:16px; width:95%; max-width:900px; max-height:80vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.3); }
.user-stats-header { display:flex; align-items:center; justify-content:space-between; padding:20px 24px; background:linear-gradient(135deg,#1e40af,#3b82f6); color:#fff; border-radius:16px 16px 0 0; }
.user-stats-header h3 { font-size:18px; margin:0; }
.user-stats-close { width:32px; height:32px; border-radius:50%; background:rgba(255,255,255,0.2); border:none; color:#fff; font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.user-stats-close:hover { background:rgba(255,255,255,0.3); }
.user-stats-body { padding:24px; }
.stats-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.stats-box { border:1px solid #e5e7eb; border-radius:12px; padding:16px; }
.stats-box h4 { margin:0 0 12px; font-size:14px; display:flex; align-items:center; gap:6px; }
.stats-box table { width:100%; font-size:13px; border-collapse:collapse; }
.stats-box table td, .stats-box table th { padding:6px 8px; border-bottom:1px solid #f3f4f6; }
.stats-box table th { font-weight:600; text-align:left; font-size:12px; color:#6b7280; }
.stats-box .stat-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #f3f4f6; }
.stats-box .stat-label-s { color:#6b7280; font-size:13px; }
.stats-box .stat-val { font-weight:600; font-size:14px; }
.stats-box .stat-val.green { color:#059669; }
.stats-box .stat-val.red { color:#dc2626; }
.stats-box .stat-val.blue { color:#2563eb; }
.ip-count { display:inline-block; padding:2px 8px; border-radius:10px; background:#fef3c7; color:#92400e; font-weight:600; font-size:12px; }
@media(max-width:768px){ .stats-grid-2 { grid-template-columns:1fr; } }
</style>

<div class="wrap">
<h1>Người dùng</h1>

<!-- Stats cards -->
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
@media(max-width:600px){table.users-table thead th,table.users-table tbody td{padding:4px 5px;font-size:11px}
table.users-table thead th{font-size:10px}
.users-table .col-id{width:30px;text-align:center}
.users-table .col-user{min-width:110px}
.users-table .col-num{white-space:nowrap;text-align:right}
.users-table .col-status span{white-space:nowrap}
.users-table .col-actions{min-width:100px}
.users-table .col-cb{width:22px;padding:2px!important}
.users-table .col-cb input[type=checkbox]{width:16px;height:16px}
.users-table .action-btn{width:26px;height:26px;font-size:11px;border-radius:6px}
.users-table .action-btns{gap:3px}
}
</style>
<div class="usr-stats">
    <div class="usr-stat us1"><div><div class="usr-val"><?php echo number_format($total); ?></div><div class="usr-lbl">User</div></div><div class="usr-ico ui1"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div></div>
    <div class="usr-stat us2"><div><div class="usr-val"><?php echo number_format($new_today); ?></div><div class="usr-lbl">Đăng ký mới</div></div><div class="usr-ico ui2"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div></div>
    <div class="usr-stat us3"><div><div class="usr-val"><?php echo linkngon_format_money($total_balance_all); ?></div><div class="usr-lbl">Số dư chưa rút</div></div><div class="usr-ico ui3"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
    <div class="usr-stat us4"><div><div class="usr-val"><?php echo linkngon_format_money($total_commission); ?></div><div class="usr-lbl">Hoa hồng</div></div><div class="usr-ico ui4"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
</div>

<!-- Search bar -->
<div class="users-summary">
    <div class="users-search">
        <input type="text" id="userSearchInput" placeholder="Tìm username, email.." value="<?php echo esc_attr($search); ?>">
        <button onclick="searchUsers()">Tìm kiếm</button>
    </div>
</div>

<!-- Users table -->
<div class="users-table-wrap">
<table class="users-table">
<thead>
<tr>
    <th class="col-cb"><input type="checkbox" id="checkAllUsers"></th>
    <th class="col-id">ID</th>
    <th class="col-user">User</th>
    <th>SĐT</th>
    <th class="col-num">Hoàn thành</th>
    <th class="col-num">Đã kiếm</th>
    <th class="col-num">Đã rút</th>
    <th class="col-num">Chờ rút</th>
    <th class="col-num">Số dư</th>
    <th class="col-status">Trạng thái</th>
    <th>Ngày ĐK</th>
    <th>Login cuối</th>
    <th class="col-actions">Thao tác</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="13" style="text-align:center;color:var(--txtm);padding:40px;">Không có người dùng nào.</td></tr>
<?php else: foreach($rows as $row):
    $is_banned = get_user_meta($row->ID, 'linkngon_banned', true);
    $phone = get_user_meta($row->ID, 'phone', true);
    $last_login = get_user_meta($row->ID, 'linkngon_last_login', true);
    $balance = (float)$row->balance;
    $earned = (float)$row->earned;
    $withdrawn = (float)$row->withdrawn;
    $pending_w = (float)$row->pending_withdrawal;
    $available = $earned - $withdrawn - $pending_w;
    if($available < 0) $available = 0;
?>
<tr>
    <td class="col-cb"><input type="checkbox" class="user-check" value="<?php echo $row->ID; ?>"></td>
    <td><?php echo $row->ID; ?></td>
    <td>
        <span class="user-name"><?php echo esc_html($row->user_login); ?></span><br>
        <span class="user-email"><?php echo esc_html($row->user_email); ?></span>
    </td>
    <td><?php echo esc_html($phone ?: '—'); ?></td>
    <td style="text-align:center">
        <?php if($row->completed > 0): ?>
            <span class="money green"><?php echo number_format($row->completed); ?></span>
        <?php else: ?>
            <span style="color:var(--txtm)">0</span>
        <?php endif; ?>
    </td>
    <td><span class="money green"><?php echo linkngon_format_money($earned); ?></span></td>
    <td><span class="money"><?php echo linkngon_format_money($withdrawn); ?></span></td>
    <td><span class="money"><?php echo linkngon_format_money($pending_w); ?></span></td>
    <td>
        <?php if($available > 0): ?>
            <span class="balance-badge positive"><?php echo linkngon_format_money($available); ?></span>
        <?php else: ?>
            <span class="balance-badge zero"><?php echo linkngon_format_money(0); ?></span>
        <?php endif; ?>
    </td>
    <td>
        <?php if($is_banned): ?>
            <span class="status-badge banned">Đã cấm</span>
        <?php else: ?>
            <span class="status-badge active">Hoạt động</span>
        <?php endif; ?>
    </td>
    <td style="white-space:nowrap"><?php echo date('d/m/Y', strtotime($row->user_registered)); ?></td>
    <td style="white-space:nowrap;font-size:12px;">
        <?php echo $last_login ? date('d/m/Y H:i', strtotime($last_login)) : '<span style="color:var(--txtm)">—</span>'; ?>
    </td>
    <td>
        <div class="action-btns">
            <button class="action-btn stats" title="Thống kê" onclick="showUserStats(<?php echo $row->ID; ?>,'<?php echo esc_js($row->user_login); ?>')">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M7 17V13"/><path d="M11 17V9"/><path d="M15 17V5"/><path d="M19 17v-4"/></svg>
            </button>
            <button class="action-btn login" title="Đăng nhập" onclick="loginAsUser(<?php echo $row->ID; ?>)">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            </button>
            <?php if($is_banned): ?>
            <button class="action-btn unban" title="Bỏ cấm" onclick="toggleBanUser(<?php echo $row->ID; ?>,'unban')">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </button>
            <?php else: ?>
            <button class="action-btn ban" title="Cấm" onclick="toggleBanUser(<?php echo $row->ID; ?>,'ban')">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
            </button>
            <?php endif; ?>
            <button class="action-btn delete" title="Xóa" onclick="deleteUser(<?php echo $row->ID; ?>,'<?php echo esc_js($row->user_login); ?>')">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
            </button>
        </div>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>

<!-- Pagination -->
<?php if($total_pages > 1): ?>
<div class="users-pagination">
    <?php for($i = 1; $i <= $total_pages; $i++): ?>
        <button class="<?php echo $i === $page_num ? 'active' : ''; ?>" onclick="loadUsersPage(<?php echo $i; ?>)"><?php echo $i; ?></button>
    <?php endfor; ?>
</div>
<?php endif; ?>

<!-- Stats Modal Container -->
<div id="userStatsModal"></div>

<script>
/* Fallback helpers (khi load ở wp-admin, không có page-admin-dashboard.php) */
if(typeof AJAX==='undefined') var AJAX='<?php echo admin_url("admin-ajax.php"); ?>';
if(typeof NONCE==='undefined') var NONCE='<?php echo wp_create_nonce("linkngon_admin_nonce"); ?>';
if(typeof ajax!=='function'){
    function ajax(action,data,cb){
        data.action=action; data.nonce=NONCE;
        var fd=new FormData(); for(var k in data) fd.append(k,data[k]);
        fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(cb).catch(function(e){alert('Lỗi: '+e.message);});
    }
}
if(typeof formatMoney!=='function'){ function formatMoney(n){return new Intl.NumberFormat('vi-VN').format(n||0)+'đ';} }
if(typeof escHtml!=='function'){ function escHtml(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;} }
if(typeof toast!=='function'){
    function toast(msg,type){
        var c=document.getElementById('toastContainer');
        if(!c){c=document.createElement('div');c.id='toastContainer';c.style.cssText='position:fixed;top:16px;right:24px;z-index:10000;display:flex;flex-direction:column;gap:8px;';document.body.appendChild(c);}
        var t=document.createElement('div');t.style.cssText='padding:14px 20px;border-radius:8px;font-size:14px;font-weight:500;color:#fff;box-shadow:0 10px 25px rgba(0,0,0,0.12);min-width:300px;background:'+(type==='err'?'#dc2626':'#059669')+';';
        t.textContent=msg;c.appendChild(t);setTimeout(function(){t.remove();},4000);
    }
}

(function(){
    // Check all
    var ca = document.getElementById('checkAllUsers');
    if(ca) ca.addEventListener('change', function(){ document.querySelectorAll('.user-check').forEach(function(c){ c.checked = ca.checked; }); });

    // Search on Enter
    var si = document.getElementById('userSearchInput');
    if(si) si.addEventListener('keydown', function(e){ if(e.key==='Enter') searchUsers(); });
})();

function searchUsers(){ loadUsersPage(1); }

function loadUsersPage(page){
    var search = document.getElementById('userSearchInput')?.value || '';
    ajax('linkngon_admin_load_tab', { tab:'users', user_search: search, user_page: page }, function(r){
        if(r.success){
            var el = document.getElementById('tab-users');
            if(el) el.innerHTML = r.data.html;
        }
    });
}

function toggleBanUser(uid, action){
    var msg = action === 'ban' ? 'Cấm người dùng #'+uid+'?' : 'Bỏ cấm người dùng #'+uid+'?';
    if(!confirm(msg)) return;
    ajax('linkngon_admin_ban_user', { user_id: uid, ban_action: action }, function(r){
        if(r.success){ toast(action==='ban'?'Đã cấm user':'Đã bỏ cấm user','ok'); loadUsersPage(<?php echo $page_num; ?>); }
        else toast(r.data||'Lỗi','err');
    });
}

function loginAsUser(uid){
    if(!confirm('Đăng nhập với tư cách user #'+uid+'?')) return;
    ajax('linkngon_admin_login_as_user', { user_id: uid }, function(r){
        if(r.success) window.open(r.data.redirect || '<?php echo home_url(); ?>', '_blank');
        else toast(r.data||'Lỗi','err');
    });
}

function deleteUser(uid, username){
    if(!confirm('XÓA VĨNH VIỄN user "'+username+'" (#'+uid+')?\n\nHành động này KHÔNG THỂ hoàn tác!')) return;
    if(!confirm('Xác nhận lần 2: Bạn chắc chắn muốn xóa user "'+username+'"?')) return;
    ajax('linkngon_admin_delete_user', { user_id: uid }, function(r){
        if(r.success){ toast('Đã xóa user','ok'); loadUsersPage(<?php echo $page_num; ?>); }
        else toast(r.data||'Lỗi','err');
    });
}

function showUserStats(uid, username){
    var container = document.getElementById('userStatsModal');
    container.innerHTML = '<div class="user-stats-overlay" onclick="if(event.target===this)closeUserStats()"><div class="user-stats-modal"><div class="user-stats-header"><h3>Thống kê: '+escHtml(username)+'</h3><button class="user-stats-close" onclick="closeUserStats()">&times;</button></div><div class="user-stats-body" style="text-align:center;padding:40px;color:#6b7280;">Đang tải...</div></div></div>';
    ajax('linkngon_admin_user_stats', { user_id: uid }, function(r){
        if(!r.success){ closeUserStats(); toast(r.data||'Lỗi','err'); return; }
        var d = r.data;
        var html = '<div class="user-stats-overlay" onclick="if(event.target===this)closeUserStats()">';
        html += '<div class="user-stats-modal">';
        html += '<div class="user-stats-header"><h3>Thống kê: '+escHtml(username)+'</h3><button class="user-stats-close" onclick="closeUserStats()">&times;</button></div>';
        html += '<div class="user-stats-body"><div class="stats-grid-2">';

        // General stats
        html += '<div class="stats-box"><h4>&#x1F4CA; Thống kê chung</h4>';
        html += '<div class="stat-row"><span class="stat-label-s">Số dư khả dụng</span><span class="stat-val green">'+formatMoney(d.balance)+'</span></div>';
        html += '<div class="stat-row"><span class="stat-label-s">Ngày tham gia</span><span class="stat-val">'+escHtml(d.registered)+'</span></div>';
        html += '<div class="stat-row"><span class="stat-label-s">TOTAL_LOAD</span><span class="stat-val">'+d.total_load.toLocaleString()+'</span></div>';
        html += '<div class="stat-row"><span class="stat-label-s">VIEW THÁNG</span><span class="stat-val blue">'+d.month_views+' ('+d.month_rate+'%)</span></div>';
        html += '<div class="stat-row"><span class="stat-label-s">CHANGE_IP</span><span class="stat-val red">'+d.change_ip+'</span></div>';
        html += '<div class="stat-row"><span class="stat-label-s">MAX_IP</span><span class="stat-val">'+d.max_ip+'</span></div>';
        html += '<div class="stat-row"><span class="stat-label-s">IP >3 LẦN</span><span class="stat-val red">'+d.ip_over_3+'</span></div>';
        html += '</div>';

        // IP frequency
        html += '<div class="stats-box"><h4 style="color:#dc2626;">&#x26A0; IP xuất hiện > 3 lần</h4>';
        if(d.top_ips && d.top_ips.length){
            html += '<table><thead><tr><th>IP</th><th style="text-align:right">Lần</th></tr></thead><tbody>';
            d.top_ips.forEach(function(ip){ html += '<tr><td style="font-family:var(--mono);font-size:12px;">'+escHtml(ip.ip)+'</td><td style="text-align:right"><span class="ip-count">'+ip.count+'</span></td></tr>'; });
            html += '</tbody></table>';
        } else { html += '<p style="color:#9ca3af;font-size:13px;">Không có IP nào > 3 lần</p>'; }
        html += '</div>';

        html += '</div>'; // close grid

        // Monthly stats
        html += '<div class="stats-box" style="margin-top:20px;"><h4>&#x1F4C5; Thống kê theo tháng</h4>';
        if(d.monthly && d.monthly.length){
            html += '<table><thead><tr><th>Tháng</th><th style="text-align:center">Load</th><th style="text-align:center">View</th><th style="text-align:center">Tỷ lệ</th><th style="text-align:right">Thu nhập</th></tr></thead><tbody>';
            d.monthly.forEach(function(m){
                var rate = m.load > 0 ? ((m.views/m.load)*100).toFixed(1) : '0.0';
                html += '<tr><td>'+escHtml(m.month)+'</td><td style="text-align:center">'+m.load.toLocaleString()+'</td><td style="text-align:center;color:var(--info);font-weight:600;">'+m.views.toLocaleString()+'</td><td style="text-align:center;color:var(--err);font-weight:600;">'+rate+'%</td><td style="text-align:right;font-weight:600;">'+formatMoney(m.earned)+'</td></tr>';
            });
            html += '</tbody></table>';
        } else { html += '<p style="color:#9ca3af;">Chưa có dữ liệu</p>'; }
        html += '</div>';

        html += '</div></div></div>'; // close body, modal, overlay
        container.innerHTML = html;
    });
}

function closeUserStats(){ document.getElementById('userStatsModal').innerHTML = ''; }
</script>
</div><!-- .wrap -->
