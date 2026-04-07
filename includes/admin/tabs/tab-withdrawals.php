<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Handle actions
if(isset($_POST['withdrawal_action']) && wp_verify_nonce($_POST['_wpnonce'],'linkngon_withdrawal_action')){
    $withdrawal_id = intval($_POST['withdrawal_id']);
    $action = sanitize_text_field($_POST['withdrawal_action']);

    $admin_note = isset($_POST['admin_note']) ? sanitize_text_field($_POST['admin_note']) : '';
    $status_map = ['approve'=>'approved', 'complete'=>'completed', 'reject'=>'rejected'];
    $new_status = $status_map[$action] ?? '';
    if($new_status){
        $result = linkngon_process_withdrawal($withdrawal_id, $new_status, $admin_note);
        if(is_wp_error($result)){
            echo '<div class="notice notice-error"><p>Lỗi: '.esc_html($result->get_error_message()).'</p></div>';
        } else {
            $msgs = ['approved'=>'đã duyệt','completed'=>'đã hoàn thành','rejected'=>'đã từ chối'];
            $cls = $new_status==='rejected' ? 'notice-warning' : 'notice-success';
            echo '<div class="notice '.$cls.'"><p>Lệnh rút #'.$withdrawal_id.' '.$msgs[$new_status].'.</p></div>';
        }
    }
}

// Filters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$search_filter = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$method_filter = isset($_GET['method']) ? sanitize_text_field($_GET['method']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
$where = "WHERE 1=1";
$args = array();
if($status_filter) {
    $where .= " AND w.status = %s";
    $args[] = $status_filter;
}
if($search_filter) {
    $like = '%' . $wpdb->esc_like($search_filter) . '%';
    $where .= " AND (u.display_name LIKE %s OR u.user_email LIKE %s OR w.bank_account LIKE %s OR w.bank_name LIKE %s OR w.bank_holder LIKE %s OR w.wallet_address LIKE %s OR w.admin_note LIKE %s)";
    $args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like;
}
if($method_filter) {
    $where .= " AND w.payment_method = %s";
    $args[] = $method_filter;
}
if($date_from) {
    $where .= " AND w.created_at >= %s";
    $args[] = $date_from . ' 00:00:00';
}
if($date_to) {
    $where .= " AND w.created_at <= %s";
    $args[] = $date_to . ' 23:59:59';
}

$page_num = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

$count_sql = "SELECT COUNT(*) FROM {$prefix}withdrawals w $where";
$total = !empty($args) ? $wpdb->get_var($wpdb->prepare($count_sql, $args)) : $wpdb->get_var($count_sql);

$args[] = $per_page;
$args[] = $offset;
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT w.*, u.display_name, u.user_email
     FROM {$prefix}withdrawals w
     LEFT JOIN {$wpdb->users} u ON u.ID = w.user_id
     $where
     ORDER BY w.id DESC
     LIMIT %d OFFSET %d", $args
));

$total_pages = ceil($total / $per_page);
$counts = $wpdb->get_results("SELECT status, COUNT(*) as cnt FROM {$prefix}withdrawals GROUP BY status", OBJECT_K);

$status_labels = [
    'pending' => 'Chờ duyệt',
    'approved' => 'Đã duyệt',
    'completed' => 'Hoàn thành',
    'rejected' => 'Từ chối',
    'cancelled' => 'Đã hủy',
    'refunded' => 'Đã hoàn tiền',
];
?>
<div class="wrap">
<h1>Lệnh rút tiền</h1>

<?php
// Thống kê rút tiền
$stats_total = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE status IN ('completed','approved','pending')");
$stats_completed = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE status='completed'");
$stats_pending_amt = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE status='pending'");
$stats_approved_amt = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE status='approved'");
$stats_total_earned = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$prefix}transactions WHERE type='shortlink_reward'");
$stats_pending_cnt = isset($counts['pending']) ? (int)$counts['pending']->cnt : 0;
$stats_approved_cnt = isset($counts['approved']) ? (int)$counts['approved']->cnt : 0;
$stats_balance = (float) $wpdb->get_var("SELECT COALESCE(SUM(balance),0) FROM {$prefix}user_balance WHERE balance > 0");
$month_start = date('Y-m-01', strtotime(linkngon_current_time()));
$stats_month = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE status='completed' AND processed_at >= %s", $month_start));
$stats_month_cnt = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}withdrawals WHERE status='completed' AND processed_at >= %s", $month_start));
?>
<style>
.wd-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
.wd-stat{border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px}
.wd-stat.ws1{background:#eff6ff;border:2px solid #bfdbfe} .wd-stat.ws2{background:#eff6ff;border:2px solid #bfdbfe}
.wd-stat.ws3{background:#fef2f2;border:2px solid #fecaca} .wd-stat.ws4{background:#fffbeb;border:2px solid #fde68a}
.wd-val{font-size:22px;font-weight:700;line-height:1.2}
.wd-stat.ws1 .wd-val{color:#1e40af} .wd-stat.ws2 .wd-val{color:#1e40af}
.wd-stat.ws3 .wd-val{color:#991b1b} .wd-stat.ws4 .wd-val{color:#92400e}
.wd-label{font-size:12px;color:#6b7280}
.wd-ico{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center}
.wd-ico.wi1{background:#dbeafe;color:#2563eb} .wd-ico.wi2{background:#dbeafe;color:#6b7280}
.wd-ico.wi3{background:#fecaca;color:#dc2626} .wd-ico.wi4{background:#fde68a;color:#d97706}
@media(max-width:600px){.wd-stats{grid-template-columns:repeat(2,1fr)} .wd-val{font-size:16px} .wd-stat{padding:12px 14px} .wd-ico{width:38px;height:38px} .wd-ico svg{width:20px;height:20px}}
.wd-tbl th{white-space:nowrap;font-size:13px} .wd-tbl td{font-size:13px}
@media(max-width:600px){.wd-tbl th,.wd-tbl td{padding:4px 5px}
.wd-tbl .col-id{width:30px;text-align:center}
.wd-tbl .col-user{min-width:110px}
.wd-tbl .col-num{white-space:nowrap;text-align:right}
.wd-tbl .col-bank{min-width:150px;word-break:break-all}
.wd-tbl .col-status span{white-space:nowrap}
.wd-tbl .col-note{min-width:110px}
.wd-tbl .button-small{font-size:11px;padding:2px 6px;min-height:auto;line-height:1.4}
}
</style>
<div class="wd-stats">
    <div class="wd-stat ws1"><div><div class="wd-val"><?php echo $stats_pending_cnt; ?></div><div class="wd-label">Chờ xử lý</div></div><div class="wd-ico wi1"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div></div>
    <div class="wd-stat ws2"><div><div class="wd-val"><?php echo linkngon_format_money($stats_balance); ?></div><div class="wd-label">Số dư khả dụng</div></div><div class="wd-ico wi2"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div></div>
    <div class="wd-stat ws3"><div><div class="wd-val"><?php echo linkngon_format_money($stats_pending_amt + $stats_approved_amt); ?></div><div class="wd-label">Đang chờ rút</div></div><div class="wd-ico wi3"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
    <div class="wd-stat ws4"><div><div class="wd-val"><?php echo linkngon_format_money($stats_completed); ?></div><div class="wd-label">Đã rút</div></div><div class="wd-ico wi4"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
</div>

<?php
// Build filter query string for preserving filters in links
$filter_qs = '';
if($search_filter) $filter_qs .= '&s=' . urlencode($search_filter);
if($method_filter) $filter_qs .= '&method=' . urlencode($method_filter);
if($date_from) $filter_qs .= '&date_from=' . urlencode($date_from);
if($date_to) $filter_qs .= '&date_to=' . urlencode($date_to);
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px">
    <ul class="subsubsub" style="margin:0;float:none">
        <li><a href="?page=linkngon-withdrawals<?php echo $filter_qs; ?>" <?php echo !$status_filter?'class="current"':''; ?>>Tất cả <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
        <?php foreach(['pending','approved','completed','rejected','cancelled','refunded'] as $s): ?>
        <li><a href="?page=linkngon-withdrawals&status=<?php echo $s; ?><?php echo $filter_qs; ?>" <?php echo $status_filter===$s?'class="current"':''; ?>><?php echo $status_labels[$s]; ?> <span class="count">(<?php echo isset($counts[$s]) ? $counts[$s]->cnt : 0; ?>)</span></a><?php echo $s!=='refunded'?' |':''; ?></li>
        <?php endforeach; ?>
    </ul>
    <form method="get" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="page" value="linkngon-withdrawals">
        <?php if($status_filter): ?><input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>"><?php endif; ?>
        <input type="search" name="s" value="<?php echo esc_attr($search_filter); ?>" placeholder="Tìm tên, email, TK ngân hàng, ghi chú..." style="padding:0 10px;min-width:220px;height:32px;-webkit-appearance:textfield">
        <select name="method" style="height:32px;padding:0 8px">
            <option value="">-- PT thanh toán --</option>
            <option value="bank" <?php selected($method_filter, 'bank'); ?>>Bank</option>
            <option value="usdt" <?php selected($method_filter, 'usdt'); ?>>USDT</option>
        </select>
        <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" style="height:32px;padding:0 8px" title="Từ ngày">
        <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" style="height:32px;padding:0 8px" title="Đến ngày">
        <input type="submit" class="button" value="Lọc">
        <?php if($search_filter || $method_filter || $date_from || $date_to): ?>
        <a href="?page=linkngon-withdrawals<?php echo $status_filter ? '&status='.$status_filter : ''; ?>" class="button">Xoá lọc</a>
        <?php endif; ?>
    </form>
</div>

<div style="overflow-x:auto"><table class="widefat striped wd-tbl">
<thead>
<tr>
    <th class="col-id">ID</th>
    <th class="col-user">Người dùng</th>
    <th class="col-num">Số tiền</th>
    <th>Phương thức</th>
    <th class="col-bank">TK ngân hàng/Ví</th>
    <th class="col-status">Trạng thái</th>
    <th class="col-note">Ghi chú admin</th>
    <th>Ngày tạo</th>
    <th class="col-actions">Thao tác</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="9">Không có dữ liệu.</td></tr>
<?php else: foreach($rows as $row):
    $status_colors = ['completed'=>'#46b450','approved'=>'#46b450','pending'=>'#00a0d2','rejected'=>'#dc3232','cancelled'=>'#82878c','refunded'=>'#ffb900'];
    $color = isset($status_colors[$row->status]) ? $status_colors[$row->status] : '#82878c';
    $bank_display = $row->payment_method === 'usdt' ? ($row->wallet_address ?? '') : trim(($row->bank_name ?? '') . ' - ' . ($row->bank_account ?? '') . ($row->bank_holder ? ' (' . $row->bank_holder . ')' : ''), ' -');
?>
<tr>
    <td><?php echo intval($row->id); ?></td>
    <td>
        <strong><?php echo esc_html($row->display_name ?? 'User #'.$row->user_id); ?></strong>
        <?php if(!empty($row->user_email)): ?><br><small><?php echo esc_html($row->user_email); ?></small><?php endif; ?>
    </td>
    <td><strong><?php echo linkngon_format_money($row->amount); ?></strong></td>
    <td><?php echo esc_html(strtoupper($row->payment_method)); ?></td>
    <td><small><?php echo esc_html($bank_display); ?></small></td>
    <td><span style="color:<?php echo $color; ?>;font-weight:bold;"><?php echo $status_labels[$row->status] ?? ucfirst($row->status); ?></span></td>
    <td><small><?php echo esc_html($row->admin_note ?? ''); ?></small></td>
    <td><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
    <td class="col-actions">
        <?php if($row->status === 'pending'): ?>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_withdrawal_action'); ?>
            <input type="hidden" name="withdrawal_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="withdrawal_action" value="approve" class="button button-small button-primary">Duyệt</button>
            <button type="submit" name="withdrawal_action" value="complete" class="button button-small" style="background:#46b450;color:#fff;border-color:#46b450;" onclick="return confirm('Xác nhận đã chuyển tiền?')">Hoàn thành</button>
            <button type="button" class="button button-small" onclick="this.nextElementSibling.style.display='inline'">Từ chối</button>
            <span style="display:none;">
                <input type="text" name="admin_note" placeholder="Lý do..." style="width:120px;">
                <button type="submit" name="withdrawal_action" value="reject" class="button button-small">Xác nhận từ chối</button>
            </span>
        </form>
        <?php elseif($row->status === 'approved'): ?>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_withdrawal_action'); ?>
            <input type="hidden" name="withdrawal_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="withdrawal_action" value="complete" class="button button-small button-primary" onclick="return confirm('Xác nhận đã chuyển tiền?')">Hoàn thành</button>
            <button type="button" class="button button-small" onclick="this.nextElementSibling.style.display='inline'">Từ chối</button>
            <span style="display:none;">
                <input type="text" name="admin_note" placeholder="Lý do..." style="width:120px;">
                <button type="submit" name="withdrawal_action" value="reject" class="button button-small">Xác nhận từ chối</button>
            </span>
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
                <a class="button" href="?page=linkngon-withdrawals<?php echo $status_filter?"&status=$status_filter":""; ?><?php echo $filter_qs; ?>&paged=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

</div>
