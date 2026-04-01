<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Handle actions
if(isset($_POST['withdrawal_action']) && wp_verify_nonce($_POST['_wpnonce'],'linkngon_withdrawal_action')){
    $withdrawal_id = intval($_POST['withdrawal_id']);
    $action = sanitize_text_field($_POST['withdrawal_action']);

    $withdrawal = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}withdrawals WHERE id = %d", $withdrawal_id));
    if($withdrawal){
        if($action === 'approve' && $withdrawal->status === 'pending'){
            $wpdb->update($prefix.'withdrawals', [
                'status' => 'approved',
                'processed_at' => linkngon_current_time()
            ], ['id' => $withdrawal_id]);
            echo '<div class="notice notice-success"><p>Lệnh rút #'.$withdrawal_id.' đã duyệt.</p></div>';

        } elseif($action === 'complete' && in_array($withdrawal->status, ['pending','approved'])){
            $wpdb->update($prefix.'withdrawals', [
                'status' => 'completed',
                'processed_at' => linkngon_current_time()
            ], ['id' => $withdrawal_id]);
            echo '<div class="notice notice-success"><p>Lệnh rút #'.$withdrawal_id.' đã hoàn thành.</p></div>';

        } elseif($action === 'reject' && in_array($withdrawal->status, ['pending','approved'])){
            $admin_note = isset($_POST['admin_note']) ? sanitize_text_field($_POST['admin_note']) : '';
            $wpdb->query('START TRANSACTION');
            try {
                $wpdb->update($prefix.'withdrawals', [
                    'status' => 'rejected',
                    'admin_note' => $admin_note,
                    'processed_at' => linkngon_current_time()
                ], ['id' => $withdrawal_id]);

                // Hoàn tiền
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$prefix}user_balance SET balance = balance + %f WHERE user_id = %d",
                    floatval($withdrawal->amount), $withdrawal->user_id
                ));

                // Ghi nhận giao dịch hoàn tiền
                $wpdb->insert($prefix.'transactions', [
                    'user_id' => $withdrawal->user_id,
                    'type' => 'refund',
                    'amount' => floatval($withdrawal->amount),
                    'description' => 'Hoàn tiền lệnh rút #'.$withdrawal_id.' (từ chối)',
                    'reference_id' => $withdrawal_id,
                    'reference_type' => 'withdrawal',
                    'status' => 'completed',
                    'created_at' => linkngon_current_time()
                ]);

                $wpdb->query('COMMIT');
                echo '<div class="notice notice-warning"><p>Lệnh rút #'.$withdrawal_id.' đã từ chối. Hoàn '.linkngon_format_money($withdrawal->amount).'.</p></div>';
            } catch(Exception $e){
                $wpdb->query('ROLLBACK');
                echo '<div class="notice notice-error"><p>Lỗi: '.esc_html($e->getMessage()).'</p></div>';
            }
        }
    }
}

// Filters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$where = "WHERE 1=1";
$args = array();
if($status_filter) {
    $where .= " AND w.status = %s";
    $args[] = $status_filter;
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
</style>
<div class="wd-stats">
    <div class="wd-stat ws1"><div><div class="wd-val"><?php echo $stats_pending_cnt; ?></div><div class="wd-label">Chờ xử lý</div></div><div class="wd-ico wi1"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div></div>
    <div class="wd-stat ws2"><div><div class="wd-val"><?php echo linkngon_format_money($stats_balance); ?></div><div class="wd-label">Số dư khả dụng</div></div><div class="wd-ico wi2"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div></div>
    <div class="wd-stat ws3"><div><div class="wd-val"><?php echo linkngon_format_money($stats_pending_amt + $stats_approved_amt); ?></div><div class="wd-label">Đang chờ rút</div></div><div class="wd-ico wi3"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
    <div class="wd-stat ws4"><div><div class="wd-val"><?php echo linkngon_format_money($stats_completed); ?></div><div class="wd-label">Đã rút</div></div><div class="wd-ico wi4"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
</div>

<ul class="subsubsub">
    <li><a href="?page=linkngon-withdrawals" <?php echo !$status_filter?'class="current"':''; ?>>Tất cả <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
    <?php foreach(['pending','approved','completed','rejected','cancelled','refunded'] as $s): ?>
    <li><a href="?page=linkngon-withdrawals&status=<?php echo $s; ?>" <?php echo $status_filter===$s?'class="current"':''; ?>><?php echo $status_labels[$s]; ?> <span class="count">(<?php echo isset($counts[$s]) ? $counts[$s]->cnt : 0; ?>)</span></a><?php echo $s!=='refunded'?' |':''; ?></li>
    <?php endforeach; ?>
</ul>
<br class="clear">

<div style="overflow-x:auto"><table class="widefat striped">
<thead>
<tr>
    <th>ID</th>
    <th>Người dùng</th>
    <th>Số tiền</th>
    <th>Phương thức</th>
    <th>TK ngân hàng/Ví</th>
    <th>Trạng thái</th>
    <th>Ghi chú admin</th>
    <th>Ngày tạo</th>
    <th>Thao tác</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="9">Không có dữ liệu.</td></tr>
<?php else: foreach($rows as $row):
    $status_colors = ['completed'=>'#46b450','approved'=>'#46b450','pending'=>'#00a0d2','rejected'=>'#dc3232','cancelled'=>'#82878c','refunded'=>'#ffb900'];
    $color = isset($status_colors[$row->status]) ? $status_colors[$row->status] : '#82878c';
    $bank_info = $row->payment_method === 'usdt' ? ($row->wallet_address ?? '') : ($row->bank_account ?? '');
?>
<tr>
    <td><?php echo intval($row->id); ?></td>
    <td>
        <strong><?php echo esc_html($row->display_name ?? 'User #'.$row->user_id); ?></strong>
        <?php if(!empty($row->user_email)): ?><br><small><?php echo esc_html($row->user_email); ?></small><?php endif; ?>
    </td>
    <td><strong><?php echo linkngon_format_money($row->amount); ?></strong></td>
    <td><?php echo esc_html(strtoupper($row->payment_method)); ?></td>
    <td><small><?php echo esc_html($bank_info); ?></small></td>
    <td><span style="color:<?php echo $color; ?>;font-weight:bold;"><?php echo $status_labels[$row->status] ?? ucfirst($row->status); ?></span></td>
    <td><small><?php echo esc_html($row->admin_note ?? ''); ?></small></td>
    <td><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
    <td>
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
                <a class="button" href="?page=linkngon-withdrawals<?php echo $status_filter?"&status=$status_filter":""; ?>&paged=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

</div>
