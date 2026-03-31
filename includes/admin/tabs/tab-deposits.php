<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Handle actions
if(isset($_POST['deposit_action']) && wp_verify_nonce($_POST['_wpnonce'],'linkngon_deposit_action')){
    $deposit_id = intval($_POST['deposit_id']);
    $action = sanitize_text_field($_POST['deposit_action']);

    if($action === 'approve'){
        $deposit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}customer_deposits WHERE id = %d AND status = 'pending'", $deposit_id));
        if($deposit){
            $wpdb->query('START TRANSACTION');
            try {
                $total_credit = floatval($deposit->amount) + floatval($deposit->bonus_amount);
                $wpdb->update($prefix.'customer_deposits', [
                    'status' => 'approved',
                    'approved_by' => get_current_user_id(),
                    'approved_at' => linkngon_current_time()
                ], ['id' => $deposit_id]);

                // Update customer balance
                $exists = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$prefix}customer_balance WHERE user_id = %d", $deposit->customer_id));
                if($exists){
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$prefix}customer_balance SET balance = balance + %f, total_deposited = total_deposited + %f WHERE user_id = %d",
                        $total_credit, floatval($deposit->amount), $deposit->customer_id
                    ));
                } else {
                    $wpdb->insert($prefix.'customer_balance', [
                        'user_id' => $deposit->customer_id,
                        'balance' => $total_credit,
                        'total_deposited' => floatval($deposit->amount),
                        'total_spent' => 0
                    ]);
                }

                // Log transaction
                $wpdb->insert($prefix.'customer_transactions', [
                    'customer_id' => $deposit->customer_id,
                    'type' => 'deposit',
                    'amount' => $total_credit,
                    'description' => 'Duyệt đơn nạp #'.$deposit_id.' (+'.(floatval($deposit->bonus_amount) > 0 ? linkngon_format_money($deposit->bonus_amount).' thưởng' : 'không thưởng').')',
                    'reference_id' => $deposit_id,
                    'reference_type' => 'deposit',
                    'status' => 'completed',
                    'created_at' => linkngon_current_time()
                ]);

                $wpdb->query('COMMIT');
                echo '<div class="notice notice-success"><p>Đơn nạp #'.$deposit_id.' đã duyệt. Cộng '.linkngon_format_money($total_credit).'.</p></div>';
            } catch(Exception $e){
                $wpdb->query('ROLLBACK');
                echo '<div class="notice notice-error"><p>Lỗi: '.esc_html($e->getMessage()).'</p></div>';
            }
        }
    } elseif($action === 'reject'){
        $wpdb->update($prefix.'customer_deposits', ['status'=>'rejected'], ['id'=>$deposit_id, 'status'=>'pending']);
        echo '<div class="notice notice-warning"><p>Đơn nạp #'.$deposit_id.' đã từ chối.</p></div>';
    }
}

// Filters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$where = "WHERE 1=1";
$args = array();
if($status_filter) {
    $where .= " AND d.status = %s";
    $args[] = $status_filter;
}

$page_num = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

$count_sql = "SELECT COUNT(*) FROM {$prefix}customer_deposits d $where";
$total = !empty($args) ? $wpdb->get_var($wpdb->prepare($count_sql, $args)) : $wpdb->get_var($count_sql);

$args[] = $per_page;
$args[] = $offset;
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT d.*
     FROM {$prefix}customer_deposits d
     $where
     ORDER BY d.id DESC
     LIMIT %d OFFSET %d", $args
));

$total_pages = ceil($total / $per_page);
$counts = $wpdb->get_results("SELECT status, COUNT(*) as cnt FROM {$prefix}customer_deposits GROUP BY status", OBJECT_K);

$status_labels = [
    'pending' => 'Chờ duyệt',
    'approved' => 'Đã duyệt',
    'rejected' => 'Từ chối',
];
?>
<div class="wrap">
<h1>Đơn nạp tiền</h1>

<ul class="subsubsub">
    <li><a href="?page=linkngon-deposits" <?php echo !$status_filter?'class="current"':''; ?>>Tất cả <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
    <?php foreach(['pending','approved','rejected'] as $s): ?>
    <li><a href="?page=linkngon-deposits&status=<?php echo $s; ?>" <?php echo $status_filter===$s?'class="current"':''; ?>><?php echo $status_labels[$s]; ?> <span class="count">(<?php echo isset($counts[$s]) ? $counts[$s]->cnt : 0; ?>)</span></a><?php echo $s!=='rejected'?' |':''; ?></li>
    <?php endforeach; ?>
</ul>
<br class="clear">

<table class="widefat striped">
<thead>
<tr>
    <th>ID</th>
    <th>Khách hàng</th>
    <th>Số tiền</th>
    <th>% Thưởng</th>
    <th>Tiền thưởng</th>
    <th>Tổng cộng</th>
    <th>Phương thức</th>
    <th>Ghi chú</th>
    <th>Trạng thái</th>
    <th>Ngày tạo</th>
    <th>Thao tác</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="11">Không có dữ liệu.</td></tr>
<?php else: foreach($rows as $row):
    $status_colors = ['approved'=>'#46b450','pending'=>'#00a0d2','rejected'=>'#dc3232'];
    $color = isset($status_colors[$row->status]) ? $status_colors[$row->status] : '#82878c';
    $total_credit = floatval($row->amount) + floatval($row->bonus_amount);
?>
<tr>
    <td><?php echo intval($row->id); ?></td>
    <td><?php echo esc_html($row->customer_username ?? '---'); ?></td>
    <td><?php echo linkngon_format_money($row->amount); ?></td>
    <td><?php echo floatval($row->bonus_percent); ?>%</td>
    <td><?php echo linkngon_format_money($row->bonus_amount); ?></td>
    <td><strong><?php echo linkngon_format_money($total_credit); ?></strong></td>
    <td><?php echo esc_html(strtoupper($row->payment_method)); ?></td>
    <td><?php echo esc_html($row->note ?? ''); ?></td>
    <td><span style="color:<?php echo $color; ?>;font-weight:bold;"><?php echo $status_labels[$row->status] ?? ucfirst($row->status); ?></span></td>
    <td><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
    <td>
        <?php if($row->status === 'pending'): ?>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_deposit_action'); ?>
            <input type="hidden" name="deposit_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="deposit_action" value="approve" class="button button-small button-primary" onclick="return confirm('Duyệt đơn nạp <?php echo linkngon_format_money($total_credit); ?>?')">Duyệt</button>
            <button type="submit" name="deposit_action" value="reject" class="button button-small" onclick="return confirm('Từ chối đơn nạp này?')">Từ chối</button>
        </form>
        <?php elseif($row->status === 'approved' && !empty($row->approved_at)): ?>
            <small>Đã duyệt <?php echo date('d/m/Y H:i', strtotime($row->approved_at)); ?></small>
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
                <a class="button" href="?page=linkngon-deposits<?php echo $status_filter?"&status=$status_filter":""; ?>&paged=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

</div>
