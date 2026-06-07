<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'traffictop_';

// Handle actions
if(isset($_POST['withdrawal_action']) && wp_verify_nonce($_POST['_wpnonce'],'traffictop_withdrawal_action')){
    $withdrawal_id = intval($_POST['withdrawal_id']);
    $action = sanitize_text_field($_POST['withdrawal_action']);

    $admin_note = isset($_POST['admin_note']) ? sanitize_text_field($_POST['admin_note']) : '';
    $status_map = ['approve'=>'approved', 'complete'=>'completed', 'reject'=>'rejected', 'cancel'=>'cancelled'];
    $new_status = $status_map[$action] ?? '';
    if($new_status){
        $result = traffictop_process_withdrawal($withdrawal_id, $new_status, $admin_note);
        if(is_wp_error($result)){
            echo '<div class="notice notice-error"><p>Lỗi: '.esc_html($result->get_error_message()).'</p></div>';
        } else {
            $msgs = ['approved'=>'đã duyệt','completed'=>'đã hoàn thành','rejected'=>'đã từ chối','cancelled'=>'đã huỷ'];
            $cls = in_array($new_status, ['rejected','cancelled']) ? 'notice-warning' : 'notice-success';
            echo '<div class="notice '.$cls.'"><p>Lệnh rút #'.$withdrawal_id.' '.$msgs[$new_status].'.</p></div>';
        }
    }
}

// Handle note update
if(isset($_POST['withdrawal_note_action']) && $_POST['withdrawal_note_action'] === 'update_note' && wp_verify_nonce($_POST['_wpnonce'],'traffictop_withdrawal_note')){
    $withdrawal_id = intval($_POST['withdrawal_id']);
    $admin_note = sanitize_text_field($_POST['admin_note']);
    $wpdb->update("{$prefix}withdrawals", array('admin_note'=>$admin_note, 'updated_at'=>traffictop_current_time()), array('id'=>$withdrawal_id));
    echo '<div class="notice notice-success"><p>Đã cập nhật ghi chú cho lệnh rút #'.$withdrawal_id.'.</p></div>';
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

$count_sql = "SELECT COUNT(*) FROM {$prefix}withdrawals w LEFT JOIN {$wpdb->users} u ON u.ID = w.user_id $where";
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
    'cancelled' => 'Đã huỷ',
    'refunded' => 'Đã hoàn tiền',
];
?>
<div class="wrap">
<h1>Lệnh rút tiền</h1>

<?php
// Thong ke rut tien
$stats_total = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE status IN ('completed','approved','pending')");
$stats_completed = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE status='completed'");
$stats_pending_amt = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE status='pending'");
$stats_approved_amt = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE status='approved'");
$stats_total_earned = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$prefix}transactions WHERE type='shortlink_reward'");
$stats_pending_cnt = isset($counts['pending']) ? (int)$counts['pending']->cnt : 0;
$stats_approved_cnt = isset($counts['approved']) ? (int)$counts['approved']->cnt : 0;
$stats_balance = (float) $wpdb->get_var("SELECT COALESCE(SUM(balance),0) FROM {$prefix}user_balance WHERE balance > 0");
$month_start = date('Y-m-01', strtotime(traffictop_current_time()));
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
.wd-tbl th{white-space:nowrap;font-size:13px} .wd-tbl td{font-size:13px;vertical-align:middle}
.wd-tbl .col-id{width:30px;text-align:center}
.wd-tbl .col-num{white-space:nowrap;text-align:right}
.wd-tbl .col-status span{white-space:nowrap}
.wd-tbl .col-status{white-space:nowrap;min-width:90px}
.wd-tbl .col-holder{white-space:nowrap;min-width:100px}
.wd-tbl .col-note{min-width:110px}
.wd-tbl .col-acct{cursor:pointer;transition:color .15s}
.wd-tbl .col-acct.copied{color:#16a34a}
.wd-act-btns{display:flex;gap:3px;flex-wrap:nowrap;align-items:center}
.wd-act-btns .button-small{font-size:11px!important;padding:2px 7px!important;min-height:22px!important;line-height:18px!important;height:22px!important}
.wd-status-done{color:#16a34a;font-weight:600;font-size:12px;white-space:nowrap}
.wd-status-rejected{color:#dc2626;font-weight:600;font-size:12px;white-space:nowrap}
.wd-status-cancelled{color:#6b7280;font-weight:600;font-size:12px;white-space:nowrap}
.wd-status-refunded{color:#d97706;font-weight:600;font-size:12px;white-space:nowrap}
/* Fraud detail button */
.wd-detail-btn{display:inline-flex;align-items:center;justify-content:center;width:24px;height:22px;background:#0ea5e9;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;line-height:1;padding:0}
.wd-detail-btn:hover{background:#0284c7}
/* Note edit button */
.wd-note-edit{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;background:#f3f4f6;color:#6b7280;border:1px solid #d1d5db;border-radius:4px;cursor:pointer;font-size:13px;line-height:1;padding:0;margin-left:4px;vertical-align:middle}
.wd-note-edit:hover{background:#e5e7eb;color:#374151}
/* Toast */
.wd-toast{position:fixed;top:40px;left:50%;transform:translateX(-50%);background:#16a34a;color:#fff;padding:8px 20px;border-radius:6px;font-size:13px;font-weight:600;z-index:999999;opacity:0;transition:opacity .2s;pointer-events:none}
.wd-toast.show{opacity:1}
/* Modal */
.wd-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100000;align-items:center;justify-content:center}
.wd-modal-overlay.active{display:flex}
.wd-modal{background:#fff;border-radius:12px;width:95%;max-width:1100px;max-height:92vh;overflow-y:auto;padding:24px;position:relative}
.wd-note-modal{max-width:480px!important}
.wd-modal-close{position:absolute;top:12px;right:16px;font-size:22px;cursor:pointer;color:#6b7280;background:none;border:none;line-height:1}
.wd-modal h3{margin:0 0 16px;font-size:16px}
.wd-modal-loading{text-align:center;padding:40px;color:#6b7280}
/* Note modal */
.wd-note-modal{max-width:480px}
.wd-note-modal textarea{width:100%;min-height:80px;border:1px solid #d1d5db;border-radius:6px;padding:8px 12px;font-size:13px;resize:vertical}
.wd-note-modal .button{margin-top:10px}
/* Fraud detail sections */
.wd-fraud-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px}
.wd-fraud-card{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px}
.wd-fraud-card h4{font-size:13px;margin:0 0 8px;color:#374151}
.wd-fraud-card .val{font-size:18px;font-weight:700}
.wd-fraud-risk{padding:8px 14px;border-radius:6px;font-weight:700;font-size:13px;display:inline-block;margin-bottom:16px}
.wd-fraud-risk.safe{background:#dcfce7;color:#166534}
.wd-fraud-risk.low{background:#fef9c3;color:#854d0e}
.wd-fraud-risk.medium{background:#fed7aa;color:#9a3412}
.wd-fraud-risk.high{background:#fecaca;color:#991b1b}
.wd-fraud-tbl{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:16px}
.wd-fraud-tbl th{background:#f3f4f6;padding:6px 8px;text-align:left;font-size:11px;font-weight:600;color:#6b7280}
.wd-fraud-tbl td{padding:5px 8px;border-bottom:1px solid #f3f4f6}
@media(max-width:600px){
.wd-tbl th,.wd-tbl td{padding:4px 5px}
.wd-tbl .col-user{min-width:110px}
.wd-tbl .col-bank{min-width:80px}
.wd-fraud-grid{grid-template-columns:1fr}
}
</style>
<div class="wd-stats">
    <div class="wd-stat ws1"><div><div class="wd-val"><?php echo $stats_pending_cnt; ?></div><div class="wd-label">Chờ xử lý</div></div><div class="wd-ico wi1"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div></div>
    <div class="wd-stat ws2"><div><div class="wd-val"><?php echo traffictop_format_money($stats_balance); ?></div><div class="wd-label">Số dư khả dụng</div></div><div class="wd-ico wi2"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div></div>
    <div class="wd-stat ws3"><div><div class="wd-val"><?php echo traffictop_format_money($stats_pending_amt + $stats_approved_amt); ?></div><div class="wd-label">Đang chờ rút</div></div><div class="wd-ico wi3"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
    <div class="wd-stat ws4"><div><div class="wd-val"><?php echo traffictop_format_money($stats_completed); ?></div><div class="wd-label">Đã rút</div></div><div class="wd-ico wi4"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
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
        <li><a href="?page=traffictop-withdrawals<?php echo $filter_qs; ?>" <?php echo !$status_filter?'class="current"':''; ?>>Tất cả <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
        <?php foreach(['pending','approved','completed','rejected','cancelled','refunded'] as $s): ?>
        <li><a href="?page=traffictop-withdrawals&status=<?php echo $s; ?><?php echo $filter_qs; ?>" <?php echo $status_filter===$s?'class="current"':''; ?>><?php echo $status_labels[$s]; ?> <span class="count">(<?php echo isset($counts[$s]) ? $counts[$s]->cnt : 0; ?>)</span></a><?php echo $s!=='refunded'?' |':''; ?></li>
        <?php endforeach; ?>
    </ul>
    <form method="get" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="page" value="traffictop-withdrawals">
        <?php if($status_filter): ?><input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>"><?php endif; ?>
        <input type="search" name="s" value="<?php echo esc_attr($search_filter); ?>" placeholder="Tìm tên, email, TK ngân hàng, ghi chú..." style="padding:0 10px;min-width:220px;height:32px">
        <select name="method" style="height:32px;padding:0 8px">
            <option value="">Phương thức</option>
            <option value="bank" <?php selected($method_filter, 'bank'); ?>>Bank</option>
            <option value="usdt" <?php selected($method_filter, 'usdt'); ?>>USDT</option>
        </select>
        <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" style="height:32px;padding:0 8px" title="Từ ngày">
        <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" style="height:32px;padding:0 8px" title="Đến ngày">
        <input type="submit" class="button" value="Lọc">
        <?php if($search_filter || $method_filter || $date_from || $date_to): ?>
        <a href="?page=traffictop-withdrawals<?php echo $status_filter ? '&status='.$status_filter : ''; ?>" class="button">Xoá lọc</a>
        <?php endif; ?>
    </form>
</div>

<div style="overflow-x:auto"><table class="widefat striped wd-tbl">
<thead>
<tr>
    <th class="col-id">ID</th>
    <th class="col-user">Người dùng</th>
    <th class="col-num">Số tiền</th>
    <th>PT</th>
    <th class="col-bank">Ngân hàng</th>
    <th>Số TK/Ví</th>
    <th class="col-holder">Chủ TK</th>
    <th>Chi tiết</th>
    <th class="col-status">Trạng thái</th>
    <th>Thao tác</th>
    <th class="col-note">Ghi chú admin</th>
    <th>Ngày tạo</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="12">Không có dữ liệu.</td></tr>
<?php else: foreach($rows as $row):
    $status_colors = ['completed'=>'#46b450','approved'=>'#46b450','pending'=>'#00a0d2','rejected'=>'#dc3232','cancelled'=>'#82878c','refunded'=>'#ffb900'];
    $color = isset($status_colors[$row->status]) ? $status_colors[$row->status] : '#82878c';
    $is_usdt = $row->payment_method === 'usdt';
    $bank_name = $is_usdt ? '—' : esc_html($row->bank_name ?? '');
    $acct_display = $is_usdt ? esc_html($row->wallet_address ?? '') : esc_html($row->bank_account ?? '');
    $holder_display = $is_usdt ? '—' : esc_html($row->bank_holder ?? '');
?>
<tr>
    <td class="col-id"><?php echo intval($row->id); ?></td>
    <td class="col-user">
        <strong><?php echo esc_html($row->display_name ?? 'User #'.$row->user_id); ?></strong>
        <?php if(!empty($row->user_email)): ?><br><small><?php echo esc_html($row->user_email); ?></small><?php endif; ?>
    </td>
    <td class="col-num"><strong><?php echo traffictop_format_money($row->amount); ?></strong></td>
    <td><?php echo esc_html(strtoupper($row->payment_method)); ?></td>
    <td class="col-bank"><small><?php echo $bank_name; ?></small></td>
    <td class="col-acct" onclick="wdCopyAcct(this)" data-copy="<?php echo esc_attr($acct_display); ?>"><small><?php echo $acct_display; ?></small></td>
    <td class="col-holder"><small><?php echo $holder_display; ?></small></td>
    <td><button type="button" class="wd-detail-btn" onclick="wdShowFraud(<?php echo intval($row->user_id); ?>, <?php echo intval($row->id); ?>)" title="Kiểm tra gian lận">&#128269;</button></td>
    <td class="col-status"><span style="color:<?php echo $color; ?>;font-weight:bold;"><?php echo $status_labels[$row->status] ?? ucfirst($row->status); ?></span></td>
    <td>
        <?php if($row->status === 'pending'): ?>
        <form method="post" style="display:inline;" class="wd-act-btns">
            <?php wp_nonce_field('traffictop_withdrawal_action'); ?>
            <input type="hidden" name="withdrawal_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="withdrawal_action" value="approve" class="button button-small button-primary" onclick="return confirm('Duyệt lệnh rút #<?php echo $row->id; ?>?')">Duyệt</button>
            <button type="submit" name="withdrawal_action" value="reject" class="button button-small" style="color:#dc2626;border-color:#dc2626" onclick="return confirm('Từ chối lệnh rút #<?php echo $row->id; ?>? Tiền sẽ được hoàn lại.')">Từ chối</button>
            <button type="submit" name="withdrawal_action" value="cancel" class="button button-small" onclick="return confirm('Huỷ lệnh rút #<?php echo $row->id; ?>? Tiền sẽ KHÔNG được hoàn lại.')">Huỷ</button>
        </form>
        <?php elseif($row->status === 'approved'): ?>
        <form method="post" style="display:inline;" class="wd-act-btns">
            <?php wp_nonce_field('traffictop_withdrawal_action'); ?>
            <input type="hidden" name="withdrawal_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="withdrawal_action" value="complete" class="button button-small button-primary" onclick="return confirm('Xác nhận đã chuyển tiền cho lệnh rút #<?php echo $row->id; ?>?')">Xong</button>
            <button type="submit" name="withdrawal_action" value="reject" class="button button-small" style="color:#dc2626;border-color:#dc2626" onclick="return confirm('Từ chối lệnh rút #<?php echo $row->id; ?>? Tiền sẽ được hoàn lại.')">Từ chối</button>
            <button type="submit" name="withdrawal_action" value="cancel" class="button button-small" onclick="return confirm('Huỷ lệnh rút #<?php echo $row->id; ?>? Tiền sẽ KHÔNG được hoàn lại.')">Huỷ</button>
        </form>
        <?php elseif($row->status === 'completed'): ?>
        <span class="wd-status-done">&#10004; Xong</span>
        <?php elseif($row->status === 'rejected'): ?>
        <span class="wd-status-rejected">&#10005; Đã từ chối</span>
        <?php elseif($row->status === 'cancelled'): ?>
        <span class="wd-status-cancelled">&#8856; Đã huỷ</span>
        <?php elseif($row->status === 'refunded'): ?>
        <span class="wd-status-refunded">&#8617; Đã hoàn tiền</span>
        <?php endif; ?>
    </td>
    <td class="col-note">
        <small><?php echo esc_html($row->admin_note ?? ''); ?></small>
        <button type="button" class="wd-note-edit" onclick="wdEditNote(<?php echo intval($row->id); ?>, this)" title="Chỉnh sửa ghi chú">&#9998;</button>
    </td>
    <td><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table></div>

<?php if($total_pages > 1):
    $pag_params = array('page' => 'traffictop-withdrawals');
    if($status_filter) $pag_params['status'] = $status_filter;
    if($search_filter) $pag_params['s'] = $search_filter;
    if($method_filter) $pag_params['method'] = $method_filter;
    if($date_from) $pag_params['date_from'] = $date_from;
    if($date_to) $pag_params['date_to'] = $date_to;
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

</div>

<!-- Toast notification -->
<div class="wd-toast" id="wdToast">Đã sao chép!</div>

<!-- Fraud check modal -->
<div class="wd-modal-overlay" id="wdFraudModal">
    <div class="wd-modal">
        <button class="wd-modal-close" onclick="wdCloseFraud()">&times;</button>
        <h3>Kiểm tra gian lận</h3>
        <div id="wdFraudContent"><div class="wd-modal-loading">Đang tải...</div></div>
    </div>
</div>

<!-- Note edit modal -->
<div class="wd-modal-overlay" id="wdNoteModal">
    <div class="wd-modal wd-note-modal">
        <button class="wd-modal-close" onclick="wdCloseNote()">&times;</button>
        <h3>Chỉnh sửa ghi chú admin</h3>
        <form method="post" id="wdNoteForm">
            <?php wp_nonce_field('traffictop_withdrawal_note'); ?>
            <input type="hidden" name="withdrawal_note_action" value="update_note">
            <input type="hidden" name="withdrawal_id" id="wdNoteWid" value="">
            <textarea name="admin_note" id="wdNoteText" placeholder="Nhập ghi chú..."></textarea>
            <button type="submit" class="button button-primary">Lưu ghi chú</button>
        </form>
    </div>
</div>

<script>
// Copy account number
function wdCopyAcct(el) {
    var text = el.getAttribute('data-copy');
    if (!text) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() { wdShowToast(el); });
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        wdShowToast(el);
    }
}
function wdShowToast(el) {
    if (el) { el.classList.add('copied'); setTimeout(function() { el.classList.remove('copied'); }, 1200); }
    var toast = document.getElementById('wdToast');
    toast.classList.add('show');
    setTimeout(function() { toast.classList.remove('show'); }, 1200);
}

// Fraud check modal
function wdEsc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function wdMoney(n){ try{ return Math.round(Number(n||0)).toLocaleString('vi-VN')+'đ'; }catch(e){ return n+'đ'; } }
function wdNum(n){ try{ return Number(n||0).toLocaleString('vi-VN'); }catch(e){ return String(n); } }
function wdRenderFraud(d){
    var h = '';
    // Period info
    var ps = d.period_start && d.period_start !== '1970-01-01 00:00:00' ? d.period_start : '—';
    h += '<div style="background:#f3f4f6;border-radius:6px;padding:8px 12px;margin-bottom:12px;font-size:11px;color:#6b7280">';
    h += 'Phạm vi tính cho lệnh rút này: <b>'+wdEsc(ps)+'</b> → <b>'+wdEsc(d.period_end||'')+'</b>';
    h += '</div>';
    // Summary cards
    h += '<div class="wd-fraud-grid">';
    h += '<div class="wd-fraud-card"><h4>Số tiền rút</h4><div class="val" style="color:#dc2626">'+wdMoney(d.amount)+'</div></div>';
    h += '<div class="wd-fraud-card"><h4>View trả tiền</h4><div class="val" style="color:#2563eb">'+wdNum(d.paid_views)+'</div></div>';
    h += '<div class="wd-fraud-card"><h4>Tổng tiền kiếm được</h4><div class="val" style="color:#059669">'+wdMoney(d.total_earned)+'</div></div>';
    h += '<div class="wd-fraud-card"><h4>Unique IP trả tiền</h4><div class="val">'+wdNum(d.unique_paid_ips)+'</div></div>';
    h += '</div>';
    // Risk badge with reasons
    var rcolor = {safe:'#dcfce7',low:'#fef9c3',medium:'#fed7aa',high:'#fecaca'}[d.risk]||'#f3f4f6';
    var rtxt   = {safe:'#166534',low:'#854d0e',medium:'#9a3412',high:'#991b1b'}[d.risk]||'#374151';
    var ricon  = {safe:'✓',low:'!',medium:'⚠',high:'✕'}[d.risk]||'?';
    h += '<div style="background:'+rcolor+';padding:12px 16px;border-radius:8px;border-left:4px solid '+rtxt+';margin-bottom:16px">';
    h += '<div style="margin-bottom:8px">';
    h += '<span style="font-weight:800;color:'+rtxt+';font-size:14px">'+ricon+' '+wdEsc(d.risk_label||'')+'</span>';
    h += '</div>';
    // 2 metric chính LUÔN hiện đầu list (kèm fit label), sau đó append risk_reasons
    h += '<div style="font-size:12px;font-weight:600;margin-bottom:4px;color:'+rtxt+'">Lý do:</div>';
    h += '<ul style="margin:0;padding-left:20px;font-size:12px;line-height:1.7;color:'+rtxt+'">';
    h += '<li>Tỷ lệ hoàn thành: '+d.completion_rate+'% — '+wdEsc(d.completion_fit||'')+'</li>';
    h += '<li>IP trùng lặp: '+d.ip_over_3+' IP >3 — '+wdEsc(d.ip_conc_fit||'')+'</li>';
    if (d.risk_reasons && d.risk_reasons.length) {
        for (var ri=0; ri<d.risk_reasons.length; ri++) h += '<li>'+wdEsc(d.risk_reasons[ri])+'</li>';
    }
    h += '</ul>';
    h += '</div>';
    // Stats table
    h += '<h4 style="margin:0 0 8px;font-size:13px">Thống kê (trong phạm vi lệnh rút)</h4>';
    h += '<table class="wd-fraud-tbl"><thead><tr><th>Click</th><th>View trả tiền (%)</th><th>Bypass</th><th>Change IP</th><th>Max IP</th><th>Adblock</th><th>IP &gt;3</th></tr></thead><tbody><tr>';
    h += '<td>'+wdNum(d.clicks)+'</td>';
    h += '<td>'+wdNum(d.paid_views)+' ('+d.completion_rate+'%)</td>';
    h += '<td>'+(d.bypass>0?'<span style="color:#dc2626;font-weight:600">'+d.bypass+'</span>':'0')+'</td>';
    h += '<td>'+(d.change_ip>0?'<span style="color:#d97706;font-weight:600">'+d.change_ip+'</span>':'0')+'</td>';
    h += '<td>'+(d.max_ip>0?'<span style="color:#dc2626;font-weight:600">'+d.max_ip+'</span>':'0')+'</td>';
    h += '<td>'+(d.adblock>0?'<span style="color:#d97706;font-weight:600">'+d.adblock+'</span>':'0')+'</td>';
    h += '<td>'+(d.ip_over_3>0?'<span style="color:#dc2626;font-weight:600">'+d.ip_over_3+'</span>':'0')+'</td>';
    h += '</tr></tbody></table>';
    // Source badges
    if (d.sources && d.sources.length) {
        h += '<h4 style="margin:0 0 8px;font-size:13px">Nguồn (badge)</h4>';
        var src_total = 0; for (var si=0; si<d.sources.length; si++) src_total += d.sources[si].cnt;
        h += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px">';
        for (var si2=0; si2<d.sources.length; si2++) {
            var s = d.sources[si2];
            var pct = src_total>0 ? Math.round(s.cnt/src_total*1000)/10 : 0;
            h += '<span style="background:#eff6ff;color:#1e40af;padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600">'+wdEsc(s.label)+' '+wdNum(s.cnt)+' ('+pct+'%)</span>';
        }
        h += '</div>';
    }
    // Top referers (URL detail) — scrollable
    if (d.top_referers && d.top_referers.length) {
        var ref_total = 0; for (var ri2=0; ri2<d.top_referers.length; ri2++) ref_total += d.top_referers[ri2].cnt;
        h += '<h4 style="margin:0 0 8px;font-size:13px">Nguồn URL chi tiết ('+d.top_referers.length+' URL)</h4>';
        h += '<div style="max-height:360px;overflow-y:auto;border:1px solid #f3f4f6;border-radius:6px;margin-bottom:14px">';
        h += '<table class="wd-fraud-tbl" style="margin:0">';
        h += '<thead style="position:sticky;top:0;background:#fff;z-index:1"><tr><th style="width:90px">Loại</th><th>URL</th><th style="width:60px;text-align:right">Lần</th><th style="width:50px;text-align:right">%</th><th style="width:90px;text-align:right">Tiền</th></tr></thead>';
        h += '<tbody>';
        for (var ri3=0; ri3<d.top_referers.length; ri3++) {
            var r = d.top_referers[ri3];
            var pct = ref_total>0 ? Math.round(r.cnt/ref_total*1000)/10 : 0;
            var url = r.referer || '(trực tiếp)';
            h += '<tr>';
            h += '<td><span style="background:#f3f4f6;padding:2px 6px;border-radius:3px;font-size:10px">'+wdEsc(r.label)+'</span></td>';
            h += '<td style="word-break:break-all;font-family:monospace;font-size:11px;line-height:1.4">'+wdEsc(url);
            if (r.utm_source || r.utm_medium || r.utm_campaign) {
                h += '<div style="margin-top:3px;padding:3px 6px;background:#fef9c3;color:#713f12;border-radius:3px;font-size:10px">';
                h += '🏷';
                if (r.utm_source)   h += ' source: '+wdEsc(r.utm_source);
                if (r.utm_medium)   h += ' · medium: '+wdEsc(r.utm_medium);
                if (r.utm_campaign) h += ' · campaign: '+wdEsc(r.utm_campaign);
                h += '</div>';
            }
            h += '</td>';
            h += '<td style="text-align:right">'+wdNum(r.cnt)+'</td>';
            h += '<td style="text-align:right">'+pct+'%</td>';
            h += '<td style="text-align:right">'+wdMoney(r.earned)+'</td>';
            h += '</tr>';
        }
        h += '</tbody></table></div>';
    }
    // Top IPs — scrollable
    if (d.top_ips && d.top_ips.length) {
        var ip_total = 0; for (var ii=0; ii<d.top_ips.length; ii++) ip_total += d.top_ips[ii].cnt;
        h += '<h4 style="margin:0 0 8px;font-size:13px">IP ('+d.top_ips.length+' IP / '+wdNum(d.paid_views)+' view trả tiền)</h4>';
        h += '<div style="max-height:360px;overflow-y:auto;border:1px solid #f3f4f6;border-radius:6px;margin-bottom:14px">';
        h += '<table class="wd-fraud-tbl" style="margin:0">';
        h += '<thead style="position:sticky;top:0;background:#fff;z-index:1"><tr><th>IP</th><th style="width:70px;text-align:right">Số lần</th><th style="width:50px;text-align:right">%</th><th style="width:100px;text-align:right">Tiền</th></tr></thead>';
        h += '<tbody>';
        for (var ii2=0; ii2<d.top_ips.length; ii2++) {
            var ip = d.top_ips[ii2];
            var pct = ip_total>0 ? Math.round(ip.cnt/ip_total*1000)/10 : 0;
            h += '<tr>';
            h += '<td><code style="font-size:11px">'+wdEsc(ip.ip)+'</code></td>';
            h += '<td style="text-align:right">'+wdNum(ip.cnt)+'</td>';
            h += '<td style="text-align:right">'+pct+'%</td>';
            h += '<td style="text-align:right">'+wdMoney(ip.earned)+'</td>';
            h += '</tr>';
        }
        h += '</tbody></table></div>';
    }
    // Shortlinks with per-link source breakdown — scrollable
    if (d.shortlinks && d.shortlinks.length) {
        h += '<h4 style="margin:0 0 8px;font-size:13px">Shortlink ('+d.shortlinks.length+')</h4>';
        h += '<div style="max-height:360px;overflow-y:auto;border:1px solid #f3f4f6;border-radius:6px">';
        h += '<table class="wd-fraud-tbl" style="margin:0">';
        h += '<thead style="position:sticky;top:0;background:#fff;z-index:1"><tr><th>Code</th><th style="text-align:left;padding:6px 8px;width:180px">Link gốc</th><th>Nguồn (top 5)</th><th style="width:70px;text-align:right">Views</th><th style="width:100px;text-align:right">Tiền</th></tr></thead>';
        h += '<tbody>';
        for (var li=0; li<d.shortlinks.length; li++) {
            var lk = d.shortlinks[li];
            // Link gốc: hostname tối đa 22 ký tự + ↗, hover xem full URL
            var origHtml = '—';
            if (lk.original_url) {
                var rawUrl = String(lk.original_url);
                // X1: only emit an href for http(s) URLs — a publisher-supplied javascript:/data: URL
                // must NOT become a clickable link in the admin's browser (stored XSS).
                var safeUrl = /^https?:\/\//i.test(rawUrl) ? rawUrl.replace(/"/g,'&quot;') : '';
                var host = rawUrl.replace(/^https?:\/\//,'').replace(/^www\./,'').split('/')[0].split('?')[0];
                if (host.length > 22) host = host.substring(0,22) + '…';
                origHtml = safeUrl
                    ? '<a href="'+safeUrl+'" target="_blank" rel="noopener noreferrer" title="'+safeUrl+'" style="color:#2563eb;text-decoration:none;font-size:11px">'+wdEsc(host)+' ↗</a>'
                    : wdEsc(host);
            }
            var srcHtml = '';
            if (lk.sources && lk.sources.length) {
                for (var si3=0; si3<lk.sources.length; si3++) {
                    var s2 = lk.sources[si3];
                    var tip = (s2.urls && s2.urls.length) ? s2.urls.join('\n').replace(/"/g,'&quot;') : '';
                    srcHtml += '<span title="'+tip+'" style="background:#f3f4f6;padding:2px 6px;border-radius:3px;font-size:10px;margin-right:3px;display:inline-block;cursor:help">'+wdEsc(s2.label);
                    if (s2.count>1) srcHtml += ' ('+s2.count+')';
                    srcHtml += '</span>';
                }
            } else {
                srcHtml = '<span style="color:#9ca3af;font-size:10px">—</span>';
            }
            h += '<tr>';
            h += '<td><code style="font-size:11px">'+wdEsc(lk.code)+'</code></td>';
            h += '<td style="padding:5px 8px;white-space:nowrap">'+origHtml+'</td>';
            h += '<td>'+srcHtml+'</td>';
            h += '<td style="text-align:right">'+wdNum(lk.views)+'</td>';
            h += '<td style="text-align:right">'+wdMoney(lk.earned)+'</td>';
            h += '</tr>';
        }
        h += '</tbody></table></div>';
    }
    return h;
}
function wdShowFraud(userId, wid) {
    var modal = document.getElementById('wdFraudModal');
    var content = document.getElementById('wdFraudContent');
    content.innerHTML = '<div class="wd-modal-loading">Đang tải...</div>';
    modal.classList.add('active');
    var fd = new FormData();
    fd.append('action', 'traffictop_admin_fraud_check');
    fd.append('nonce', '<?php echo wp_create_nonce("traffictop_admin_nonce"); ?>');
    fd.append('user_id', userId);
    fd.append('withdrawal_id', wid);
    fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) { content.innerHTML = wdRenderFraud(res.data); }
            else { content.innerHTML = '<p style="color:#dc2626">Lỗi: ' + wdEsc(res.data || 'Unknown') + '</p>'; }
        })
        .catch(function() { content.innerHTML = '<p style="color:#dc2626">Lỗi kết nối</p>'; });
}
function wdCloseFraud() { document.getElementById('wdFraudModal').classList.remove('active'); }

// Note edit modal
function wdEditNote(wid, btn) {
    var noteCell = btn.parentElement;
    var currentNote = noteCell.querySelector('small') ? noteCell.querySelector('small').textContent : '';
    document.getElementById('wdNoteWid').value = wid;
    document.getElementById('wdNoteText').value = currentNote;
    document.getElementById('wdNoteModal').classList.add('active');
}
function wdCloseNote() { document.getElementById('wdNoteModal').classList.remove('active'); }

// Close modals on overlay click
document.getElementById('wdFraudModal').addEventListener('click', function(e) { if (e.target === this) wdCloseFraud(); });
document.getElementById('wdNoteModal').addEventListener('click', function(e) { if (e.target === this) wdCloseNote(); });
</script>
