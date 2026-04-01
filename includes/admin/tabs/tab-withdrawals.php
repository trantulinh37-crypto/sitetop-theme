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
$stats_rejected_amt = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE status='rejected'");
$stats_pending_cnt = isset($counts['pending']) ? (int)$counts['pending']->cnt : 0;
$stats_approved_cnt = isset($counts['approved']) ? (int)$counts['approved']->cnt : 0;
$stats_balance = (float) $wpdb->get_var("SELECT COALESCE(SUM(balance),0) FROM {$prefix}user_balance WHERE balance > 0");
?>
<style>
.wd-stats { display:flex; gap:16px; margin-bottom:16px; flex-wrap:wrap; }
.wd-stat { background:#fff; border:1px solid #ddd; border-radius:8px; padding:14px 20px; min-width:160px; border-left:4px solid #ccc; }
.wd-stat.s-pending { border-left-color:#00a0d2; }
.wd-stat.s-approved { border-left-color:#ffb900; }
.wd-stat.s-completed { border-left-color:#46b450; }
.wd-stat.s-rejected { border-left-color:#dc3232; }
.wd-stat.s-balance { border-left-color:#2271b1; }
.wd-stat .wd-label { font-size:12px; color:#646970; text-transform:uppercase; letter-spacing:0.03em; margin-bottom:4px; }
.wd-stat .wd-value { font-size:20px; font-weight:700; color:#1d2327; }
.wd-stat .wd-count { font-size:12px; color:#787c82; margin-top:2px; }
</style>
<div class="wd-stats">
    <div class="wd-stat s-pending">
        <div class="wd-label">Chờ duyệt</div>
        <div class="wd-value"><?php echo linkngon_format_money($stats_pending_amt); ?></div>
        <div class="wd-count"><?php echo $stats_pending_cnt; ?> lệnh</div>
    </div>
    <div class="wd-stat s-approved">
        <div class="wd-label">Đã duyệt</div>
        <div class="wd-value"><?php echo linkngon_format_money($stats_approved_amt); ?></div>
        <div class="wd-count"><?php echo $stats_approved_cnt; ?> lệnh</div>
    </div>
    <div class="wd-stat s-completed">
        <div class="wd-label">Đã chuyển</div>
        <div class="wd-value"><?php echo linkngon_format_money($stats_completed); ?></div>
    </div>
    <div class="wd-stat s-rejected">
        <div class="wd-label">Đã từ chối</div>
        <div class="wd-value"><?php echo linkngon_format_money($stats_rejected_amt); ?></div>
    </div>
    <div class="wd-stat s-balance">
        <div class="wd-label">Tổng số dư users</div>
        <div class="wd-value"><?php echo linkngon_format_money($stats_balance); ?></div>
    </div>
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
