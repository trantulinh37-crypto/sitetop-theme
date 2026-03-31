<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Auto-add visible column if missing
$has_visible = $wpdb->get_results("SHOW COLUMNS FROM {$prefix}customer_deposits LIKE 'visible'");
if(empty($has_visible)){
    $wpdb->query("ALTER TABLE {$prefix}customer_deposits ADD COLUMN `visible` TINYINT(1) NOT NULL DEFAULT 1");
    // Default: negative amounts hidden
    $wpdb->query("UPDATE {$prefix}customer_deposits SET visible = 0 WHERE amount < 0");
}

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
    } elseif($action === 'update_note'){
        $note = sanitize_text_field($_POST['note'] ?? '');
        $wpdb->update($prefix.'customer_deposits', ['note'=>$note], ['id'=>$deposit_id]);
        echo '<div class="notice notice-success is-dismissible"><p>Đã cập nhật ghi chú #'.$deposit_id.'</p></div>';
    } elseif($action === 'admin_deposit'){
        // Admin nạp/trừ tiền trực tiếp cho khách
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $dep_amount = floatval($_POST['dep_amount'] ?? 0);
        $note = sanitize_text_field($_POST['note'] ?? '');
        if(!$customer_id || !$dep_amount){
            echo '<div class="notice notice-error"><p>Thiếu thông tin.</p></div>';
        } else {
            $customer = get_user_by('ID', $customer_id);
            $wpdb->query('START TRANSACTION');
            try {
                // Insert deposit record
                $is_add = ($dep_amount > 0);
                $wpdb->insert($prefix.'customer_deposits', [
                    'customer_id' => $customer_id,
                    'customer_username' => $customer ? $customer->user_login : 'unknown',
                    'amount' => $dep_amount,
                    'bonus_percent' => 0,
                    'bonus_amount' => 0,
                    'payment_method' => 'admin',
                    'note' => $note ?: ($is_add ? 'Admin nạp tiền' : 'Admin trừ tiền'),
                    'status' => 'approved',
                    'visible' => $is_add ? 1 : 0,
                    'approved_by' => get_current_user_id(),
                    'approved_at' => linkngon_current_time(),
                    'created_at' => linkngon_current_time(),
                ]);
                // Update balance
                $exists = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$prefix}customer_balance WHERE user_id=%d", $customer_id));
                if($exists){
                    $wpdb->query($wpdb->prepare("UPDATE {$prefix}customer_balance SET balance = balance + %f" . ($is_add ? ", total_deposited = total_deposited + %f" : "") . " WHERE user_id = %d",
                        $is_add ? array($dep_amount, $dep_amount, $customer_id) : array($dep_amount, $customer_id)
                    ));
                } else {
                    $wpdb->insert($prefix.'customer_balance', ['user_id'=>$customer_id,'balance'=>$dep_amount,'total_deposited'=>max(0,$dep_amount),'total_spent'=>0]);
                }
                // Log transaction
                $wpdb->insert($prefix.'customer_transactions', [
                    'customer_id' => $customer_id,
                    'type' => $is_add ? 'deposit' : 'deduction',
                    'amount' => $dep_amount,
                    'description' => $note ?: ($is_add ? 'Admin nạp tiền' : 'Admin trừ tiền'),
                    'status' => 'completed',
                    'created_at' => linkngon_current_time(),
                ]);
                $wpdb->query('COMMIT');
                echo '<div class="notice notice-success"><p>Đã '.($is_add?'nạp':'trừ').' '.linkngon_format_money(abs($dep_amount)).' cho '.esc_html($customer?$customer->user_login:'#'.$customer_id).'</p></div>';
            } catch(Exception $e){
                $wpdb->query('ROLLBACK');
                echo '<div class="notice notice-error"><p>Lỗi: '.esc_html($e->getMessage()).'</p></div>';
            }
        }
    } elseif($action === 'toggle_visible'){
        $current = $wpdb->get_var($wpdb->prepare("SELECT visible FROM {$prefix}customer_deposits WHERE id=%d", $deposit_id));
        $new_val = ($current === '0') ? 1 : 0;
        $wpdb->update($prefix.'customer_deposits', ['visible'=>$new_val], ['id'=>$deposit_id]);
        echo '<div class="notice notice-success is-dismissible"><p>Đơn #'.$deposit_id.' đã '.($new_val?'hiện':'ẩn').'.</p></div>';
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

<!-- Admin nạp/trừ tiền -->
<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;max-width:600px">
    <h3 style="margin:0 0 12px;font-size:15px">Admin nạp/trừ tiền cho khách hàng</h3>
    <form method="post">
        <?php wp_nonce_field('linkngon_deposit_action'); ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">Khách hàng</label>
                <select name="customer_id" required style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:4px">
                    <option value="">-- Chọn --</option>
                    <?php
                    $customers = $wpdb->get_results("SELECT u.ID, u.user_login FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} um ON um.user_id=u.ID AND um.meta_key='{$wpdb->prefix}capabilities' WHERE um.meta_value LIKE '%customer%' ORDER BY u.user_login");
                    foreach($customers as $c): ?>
                    <option value="<?php echo $c->ID; ?>"><?php echo esc_html($c->user_login); ?> (#<?php echo $c->ID; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">Số tiền (VNĐ)</label>
                <input type="number" name="dep_amount" required style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:4px" placeholder="VD: 1000000">
                <div style="font-size:10px;color:#787c82;margin-top:2px">Dương = nạp, âm = trừ</div>
            </div>
        </div>
        <div style="display:flex;gap:10px;align-items:end">
            <div style="flex:1">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">Ghi chú</label>
                <input type="text" name="note" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:4px" placeholder="VD: Admin nạp tiền">
            </div>
            <button type="submit" name="deposit_action" value="admin_deposit" class="button button-primary" style="padding:8px 20px" onclick="return confirm('Xác nhận?')">Thực hiện</button>
        </div>
    </form>
</div>

<ul class="subsubsub">
    <li><a href="?page=linkngon-deposits" <?php echo !$status_filter?'class="current"':''; ?>>Tất cả <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
    <?php foreach(['pending','approved','rejected'] as $s): ?>
    <li><a href="?page=linkngon-deposits&status=<?php echo $s; ?>" <?php echo $status_filter===$s?'class="current"':''; ?>><?php echo $status_labels[$s]; ?> <span class="count">(<?php echo isset($counts[$s]) ? $counts[$s]->cnt : 0; ?>)</span></a><?php echo $s!=='rejected'?' |':''; ?></li>
    <?php endforeach; ?>
</ul>
<br class="clear">

<div style="overflow-x:auto"><table class="widefat striped">
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
    <th>Hiển thị</th>
    <th>Ngày tạo</th>
    <th>Thao tác</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="12">Không có dữ liệu.</td></tr>
<?php else: foreach($rows as $row):
    $status_colors = ['approved'=>'#46b450','pending'=>'#00a0d2','rejected'=>'#dc3232'];
    $color = isset($status_colors[$row->status]) ? $status_colors[$row->status] : '#82878c';
    $total_credit = floatval($row->amount) + floatval($row->bonus_amount);
?>
<?php $is_visible = isset($row->visible) ? (int)$row->visible : (floatval($row->amount) >= 0 ? 1 : 0); ?>
<tr<?php echo !$is_visible ? ' style="opacity:.5"' : ''; ?>>
    <td><?php echo intval($row->id); ?></td>
    <td><?php echo esc_html($row->customer_username ?? '---'); ?></td>
    <td style="color:<?php echo floatval($row->amount)>=0?'#46b450':'#dc3232'; ?>;font-weight:600"><?php echo (floatval($row->amount)>=0?'+':'').linkngon_format_money($row->amount); ?></td>
    <td><?php echo floatval($row->bonus_percent); ?>%</td>
    <td><?php echo linkngon_format_money($row->bonus_amount); ?></td>
    <td><strong><?php echo linkngon_format_money($total_credit); ?></strong></td>
    <td><?php echo esc_html(strtoupper($row->payment_method)); ?></td>
    <td><form method="post" style="display:flex;gap:3px"><?php wp_nonce_field('linkngon_deposit_action'); ?><input type="hidden" name="deposit_id" value="<?php echo $row->id; ?>"><input type="text" name="note" value="<?php echo esc_attr($row->note ?? ''); ?>" style="width:90px;padding:3px 5px;font-size:11px;border:1px solid #ddd;border-radius:3px" placeholder="Ghi chú"><button type="submit" name="deposit_action" value="update_note" class="button button-small">OK</button></form></td>
    <td><span style="color:<?php echo $color; ?>;font-weight:bold;"><?php echo $status_labels[$row->status] ?? ucfirst($row->status); ?></span></td>
    <td><form method="post" style="display:inline"><?php wp_nonce_field('linkngon_deposit_action'); ?><input type="hidden" name="deposit_id" value="<?php echo $row->id; ?>"><?php if($is_visible): ?><button type="submit" name="deposit_action" value="toggle_visible" class="button button-small" style="color:#46b450">Hiện</button><?php else: ?><button type="submit" name="deposit_action" value="toggle_visible" class="button button-small" style="color:#dc3232">Ẩn</button><?php endif; ?></form></td>
    <td><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
    <td>
        <?php if($row->status === 'pending'): ?>
        <form method="post" style="display:inline"><?php wp_nonce_field('linkngon_deposit_action'); ?><input type="hidden" name="deposit_id" value="<?php echo $row->id; ?>"><button type="submit" name="deposit_action" value="approve" class="button button-small button-primary" onclick="return confirm('Duyệt?')">Duyệt</button> <button type="submit" name="deposit_action" value="reject" class="button button-small" onclick="return confirm('Từ chối?')">Từ chối</button></form>
        <?php elseif($row->status === 'approved' && !empty($row->approved_at)): ?>
            <small>Duyệt <?php echo date('d/m H:i', strtotime($row->approved_at)); ?></small>
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
                <a class="button" href="?page=linkngon-deposits<?php echo $status_filter?"&status=$status_filter":""; ?>&paged=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

</div>
