<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'traffictop_';

// Auto-add visible column if missing
$has_visible = $wpdb->get_results("SHOW COLUMNS FROM {$prefix}customer_deposits LIKE 'visible'");
if(empty($has_visible)){
    $wpdb->query("ALTER TABLE {$prefix}customer_deposits ADD COLUMN `visible` TINYINT(1) NOT NULL DEFAULT 1");
    // Default: negative amounts hidden
    $wpdb->query("UPDATE {$prefix}customer_deposits SET visible = 0 WHERE amount < 0");
}

// Handle actions
if(isset($_POST['deposit_action']) && wp_verify_nonce($_POST['_wpnonce'],'traffictop_deposit_action')){
    $deposit_id = intval($_POST['deposit_id'] ?? 0);
    $action = sanitize_text_field($_POST['deposit_action']);

    if($action === 'approve'){
        $deposit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}customer_deposits WHERE id = %d AND status = 'pending'", $deposit_id));
        if($deposit){
            $wpdb->query('START TRANSACTION');
            try {
                $total_credit = absint($deposit->amount) + absint($deposit->bonus_amount);

                // Lock deposit FOR UPDATE to prevent double-approve
                $dep_locked = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$prefix}customer_deposits WHERE id = %d FOR UPDATE", $deposit_id));
                if(!$dep_locked || $dep_locked->status !== 'pending'){
                    $wpdb->query('ROLLBACK');
                    echo '<div class="notice notice-error"><p>Đơn nạp đã được xử lý.</p></div>';
                    return;
                }

                $wpdb->update($prefix.'customer_deposits', [
                    'status' => 'approved',
                    'approved_by' => get_current_user_id(),
                    'approved_at' => traffictop_current_time()
                ], ['id' => $deposit_id]);

                // Lock customer_balance FOR UPDATE to prevent race condition
                $cbal = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$prefix}customer_balance WHERE user_id = %d FOR UPDATE", $deposit->customer_id));
                if($cbal){
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$prefix}customer_balance SET balance = balance + %d, total_deposited = total_deposited + %d WHERE user_id = %d",
                        $total_credit, absint($deposit->amount), $deposit->customer_id
                    ));
                } else {
                    $wpdb->insert($prefix.'customer_balance', [
                        'user_id' => $deposit->customer_id,
                        'balance' => $total_credit,
                        'total_deposited' => absint($deposit->amount),
                        'total_spent' => 0
                    ]);
                }

                // Log transaction
                $wpdb->insert($prefix.'customer_transactions', [
                    'customer_id' => $deposit->customer_id,
                    'type' => 'deposit',
                    'amount' => $total_credit,
                    'description' => 'Duyệt đơn nạp #'.$deposit_id.' (+'.(floatval($deposit->bonus_amount) > 0 ? traffictop_format_money($deposit->bonus_amount).' thưởng' : 'không thưởng').')',
                    'reference_id' => $deposit_id,
                    'reference_type' => 'deposit',
                    'status' => 'completed',
                    'created_at' => traffictop_current_time()
                ]);

                $wpdb->query('COMMIT');
                traffictop_send_deposit_approved_email( $deposit_id );
                echo '<div class="notice notice-success"><p>Đơn nạp #'.$deposit_id.' đã duyệt. Cộng '.traffictop_format_money($total_credit).'.</p></div>';
            } catch(Exception $e){
                $wpdb->query('ROLLBACK');
                echo '<div class="notice notice-error"><p>Lỗi: '.esc_html($e->getMessage()).'</p></div>';
            }
        }
    } elseif($action === 'reject'){
        $wpdb->update($prefix.'customer_deposits', ['status'=>'rejected'], ['id'=>$deposit_id, 'status'=>'pending']);
        traffictop_send_deposit_rejected_email( $deposit_id );
        echo '<div class="notice notice-warning"><p>Đơn nạp #'.$deposit_id.' đã từ chối.</p></div>';
    } elseif($action === 'update_note'){
        $note = sanitize_text_field($_POST['note'] ?? '');
        $wpdb->update($prefix.'customer_deposits', ['note'=>$note], ['id'=>$deposit_id]);
        echo '<div class="notice notice-success is-dismissible"><p>Đã cập nhật ghi chú #'.$deposit_id.'</p></div>';
    } elseif($action === 'admin_deposit'){
        // Admin nạp/trừ tiền trực tiếp cho khách
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $dep_amount = intval($_POST['dep_amount'] ?? 0); // VND integer
        $note = sanitize_text_field($_POST['note'] ?? '');
        if(!$customer_id || !$dep_amount){
            echo '<div class="notice notice-error"><p>Thiếu thông tin.</p></div>';
        } else {
            $customer = get_user_by('ID', $customer_id);
            $wpdb->query('START TRANSACTION');
            try {
                // Insert deposit record
                $is_add = ($dep_amount > 0);
                $ins = $wpdb->insert($prefix.'customer_deposits', [
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
                    'approved_at' => traffictop_current_time(),
                    'created_at' => traffictop_current_time(),
                ]);
                if ($ins === false) {
                    throw new Exception('Insert thất bại: ' . ($wpdb->last_error ?: 'unknown'));
                }
                $new_dep_id = $wpdb->insert_id;
                // Lock customer_balance FOR UPDATE to prevent race condition
                $cbal = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$prefix}customer_balance WHERE user_id=%d FOR UPDATE", $customer_id));
                if($cbal){
                    if($is_add){
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$prefix}customer_balance SET balance = balance + %d, total_deposited = total_deposited + %d WHERE user_id = %d",
                            $dep_amount, $dep_amount, $customer_id));
                    } else {
                        $abs_amount = abs($dep_amount);
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$prefix}customer_balance SET balance = balance + %d, total_spent = total_spent + %d WHERE user_id = %d",
                            $dep_amount, $abs_amount, $customer_id));
                    }
                } else {
                    $wpdb->insert($prefix.'customer_balance', [
                        'user_id'=>$customer_id,
                        'balance'=>$dep_amount,
                        'total_deposited'=>max(0,$dep_amount),
                        'total_spent'=>$is_add ? 0 : abs($dep_amount),
                    ]);
                }
                $wpdb->query('COMMIT');
                echo '<div class="notice notice-success"><p>Đã '.($is_add?'nạp':'trừ').' '.traffictop_format_money(abs($dep_amount)).' cho '.esc_html($customer?$customer->user_login:'#'.$customer_id).' — Bản ghi #'.intval($new_dep_id).' đã được tạo.</p></div>';
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
$search_filter = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$where = "WHERE 1=1";
$args = array();
if($status_filter) {
    $where .= " AND d.status = %s";
    $args[] = $status_filter;
}
if($search_filter) {
    $like = '%' . $wpdb->esc_like($search_filter) . '%';
    $where .= " AND (d.customer_username LIKE %s OR d.note LIKE %s OR d.payment_method LIKE %s)";
    $args[] = $like; $args[] = $like; $args[] = $like;
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

<?php
$dep_pending_cnt = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}customer_deposits WHERE status='pending'");
$dep_total_approved = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$prefix}customer_deposits WHERE status='approved' AND amount > 0");
$dep_total_bonus = (float) $wpdb->get_var("SELECT COALESCE(SUM(bonus_amount),0) FROM {$prefix}customer_deposits WHERE status='approved' AND bonus_amount > 0");
$dep_cust_balance = (float) $wpdb->get_var("SELECT COALESCE(SUM(balance),0) FROM {$prefix}customer_balance WHERE balance > 0");
?>
<style>
.dep-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
.dep-stat{border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px}
.dep-stat.ds1{background:#eff6ff;border:2px solid #bfdbfe} .dep-stat.ds2{background:#f0fdf4;border:2px solid #bbf7d0}
.dep-stat.ds3{background:#fef2f2;border:2px solid #fecaca} .dep-stat.ds4{background:#fffbeb;border:2px solid #fde68a}
.dep-val{font-size:22px;font-weight:700;line-height:1.2}
.dep-stat.ds1 .dep-val{color:#1e40af} .dep-stat.ds2 .dep-val{color:#166534}
.dep-stat.ds3 .dep-val{color:#991b1b} .dep-stat.ds4 .dep-val{color:#92400e}
.dep-label{font-size:12px;color:#6b7280}
.dep-ico{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center}
.dep-ico.di1{background:#dbeafe;color:#2563eb} .dep-ico.di2{background:#d1fae5;color:#059669}
.dep-ico.di3{background:#fecaca;color:#dc2626} .dep-ico.di4{background:#fde68a;color:#d97706}
@media(max-width:600px){.dep-stats{grid-template-columns:repeat(2,1fr)} .dep-val{font-size:16px} .dep-stat{padding:12px 14px} .dep-ico{width:38px;height:38px} .dep-ico svg{width:20px;height:20px}}
.dep-tbl th{white-space:nowrap;font-size:13px} .dep-tbl td{font-size:13px}
.dep-tbl .col-note input[type=text]{padding:2px 4px;font-size:11px;height:24px}
.dep-tbl .col-note .button-small,.dep-tbl .col-vis .button-small{font-size:11px;padding:1px 6px;min-height:24px;line-height:1.4}
@media(max-width:600px){.dep-tbl th,.dep-tbl td{padding:4px 5px}
.dep-tbl .col-id{width:30px;text-align:center}
.dep-tbl .col-cust{min-width:110px}
.dep-tbl .col-num{white-space:nowrap;text-align:right}
.dep-tbl .col-note{min-width:120px}
.dep-tbl .col-status span{white-space:nowrap}
.dep-tbl .button-small{font-size:11px;padding:2px 6px;min-height:auto;line-height:1.4}
}
</style>
<div class="dep-stats">
    <div class="dep-stat ds1"><div><div class="dep-val"><?php echo $dep_pending_cnt; ?></div><div class="dep-label">Chờ thanh toán</div></div><div class="dep-ico di1"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div></div>
    <div class="dep-stat ds2"><div><div class="dep-val"><?php echo traffictop_format_money($dep_total_approved); ?></div><div class="dep-label">Đã nạp</div></div><div class="dep-ico di2"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
    <div class="dep-stat ds3"><div><div class="dep-val"><?php echo traffictop_format_money($dep_total_bonus); ?></div><div class="dep-label">Khuyến mãi</div></div><div class="dep-ico di3"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
    <div class="dep-stat ds4"><div><div class="dep-val"><?php echo traffictop_format_money($dep_cust_balance); ?></div><div class="dep-label">Số dư</div></div><div class="dep-ico di4"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
</div>

<!-- Admin nạp/trừ tiền -->
<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;max-width:600px">
    <h3 style="margin:0 0 12px;font-size:15px">Admin nạp/trừ tiền cho khách hàng</h3>
    <form method="post">
        <?php wp_nonce_field('traffictop_deposit_action'); ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">Khách hàng</label>
                <select name="customer_id" required style="width:100%;height:38px;padding:0 10px;border:1px solid #ddd;border-radius:4px;font-size:14px">
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
                <input type="text" name="dep_amount_display" id="dep_amount_display" required style="width:100%;height:38px;padding:0 10px;border:1px solid #ddd;border-radius:4px;font-size:14px" placeholder="VD: 3.000.000" inputmode="numeric">
                <input type="hidden" name="dep_amount" id="dep_amount_real">
                <div style="font-size:10px;color:#787c82;margin-top:2px">Dương = nạp, âm = trừ</div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">Ghi chú</label>
                <input type="text" name="note" style="width:100%;height:38px;padding:0 10px;border:1px solid #ddd;border-radius:4px;font-size:14px" placeholder="VD: Admin nạp tiền">
            </div>
            <button type="submit" name="deposit_action" value="admin_deposit" class="button button-primary" style="height:38px;padding:0 20px" onclick="return confirm('Xác nhận?')">Thực hiện</button>
        </div>
    </form>
    <script>
    (function(){
        var display = document.getElementById('dep_amount_display');
        var real = document.getElementById('dep_amount_real');
        function formatVND(val){
            var neg = val.charAt(0)==='-';
            var num = val.replace(/[^0-9]/g,'');
            if(!num) return neg?'-':'';
            return (neg?'-':'')+num.replace(/\B(?=(\d{3})+(?!\d))/g,'.');
        }
        display.addEventListener('input',function(){
            var pos = this.selectionStart;
            var oldLen = this.value.length;
            this.value = formatVND(this.value);
            var newLen = this.value.length;
            this.setSelectionRange(pos+newLen-oldLen, pos+newLen-oldLen);
            real.value = this.value.replace(/\./g,'');
        });
        display.closest('form').addEventListener('submit',function(){
            real.value = display.value.replace(/\./g,'');
        });
    })();
    </script>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px">
    <ul class="subsubsub" style="margin:0;float:none">
        <li><a href="?page=traffictop-deposits<?php echo $search_filter?'&s='.urlencode($search_filter):''; ?>" <?php echo !$status_filter?'class="current"':''; ?>>Tất cả <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
        <?php foreach(['pending','approved','rejected'] as $s): ?>
        <li><a href="?page=traffictop-deposits&status=<?php echo $s; ?><?php echo $search_filter?'&s='.urlencode($search_filter):''; ?>" <?php echo $status_filter===$s?'class="current"':''; ?>><?php echo $status_labels[$s]; ?> <span class="count">(<?php echo isset($counts[$s]) ? $counts[$s]->cnt : 0; ?>)</span></a><?php echo $s!=='rejected'?' |':''; ?></li>
        <?php endforeach; ?>
    </ul>
    <form method="get" style="display:flex;gap:6px;align-items:center">
        <input type="hidden" name="page" value="traffictop-deposits">
        <?php if($status_filter): ?><input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>"><?php endif; ?>
        <input type="search" name="s" value="<?php echo esc_attr($search_filter); ?>" placeholder="Tìm username, ghi chú, PT thanh toán..." style="padding:0 10px;min-width:200px;-webkit-appearance:textfield">
        <input type="submit" class="button" value="Tìm kiếm">
    </form>
</div>

<div style="overflow-x:auto"><table class="widefat striped dep-tbl">
<thead>
<tr>
    <th class="col-id">ID</th>
    <th class="col-cust">Khách hàng</th>
    <th class="col-num">Số tiền</th>
    <th class="col-num">% Thưởng</th>
    <th class="col-num">Tiền thưởng</th>
    <th class="col-num">Tổng cộng</th>
    <th>Phương thức</th>
    <th class="col-note">Ghi chú</th>
    <th class="col-status">Trạng thái</th>
    <th>Hiển thị</th>
    <th>Ngày tạo</th>
    <th class="col-actions">Thao tác</th>
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
    <td style="color:<?php echo floatval($row->amount)>=0?'#46b450':'#dc3232'; ?>;font-weight:600"><?php echo (floatval($row->amount)>=0?'+':'').traffictop_format_money($row->amount); ?></td>
    <td><?php echo floatval($row->bonus_percent); ?>%</td>
    <td><?php echo traffictop_format_money($row->bonus_amount); ?></td>
    <td><strong><?php echo traffictop_format_money($total_credit); ?></strong></td>
    <td><?php
        $pm = strtoupper($row->payment_method);
        if ($pm === 'USDT') {
            $usdt_rate = intval(traffictop_get_option('deposit_usdt_rate', 25000));
            $usdt_amt = ($usdt_rate > 0) ? (float)$row->amount / $usdt_rate : 0;
            echo '<span style="font-weight:600;color:#2563eb">' . number_format($usdt_amt, 1) . ' USDT</span>';
        } else {
            echo esc_html($pm);
        }
    ?></td>
    <td class="col-note"><form method="post" style="display:flex;gap:3px;align-items:center"><?php wp_nonce_field('traffictop_deposit_action'); ?><input type="hidden" name="deposit_id" value="<?php echo $row->id; ?>"><input type="text" name="note" value="<?php echo esc_attr($row->note ?? ''); ?>" style="width:90px;border:1px solid #ddd;border-radius:3px" placeholder="Ghi chú"><button type="submit" name="deposit_action" value="update_note" class="button button-small">OK</button></form></td>
    <td><span style="color:<?php echo $color; ?>;font-weight:bold;"><?php echo $status_labels[$row->status] ?? ucfirst($row->status); ?></span></td>
    <td class="col-vis"><form method="post" style="display:inline"><?php wp_nonce_field('traffictop_deposit_action'); ?><input type="hidden" name="deposit_id" value="<?php echo $row->id; ?>"><?php if($is_visible): ?><button type="submit" name="deposit_action" value="toggle_visible" class="button button-small" style="color:#46b450">Hiện</button><?php else: ?><button type="submit" name="deposit_action" value="toggle_visible" class="button button-small" style="color:#dc3232">Ẩn</button><?php endif; ?></form></td>
    <td><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
    <td class="col-actions">
        <?php if($row->status === 'pending'): ?>
        <form method="post" style="display:inline"><?php wp_nonce_field('traffictop_deposit_action'); ?><input type="hidden" name="deposit_id" value="<?php echo $row->id; ?>"><button type="submit" name="deposit_action" value="approve" class="button button-small button-primary" style="padding:2px 10px;font-size:11px;min-height:26px;line-height:24px" onclick="return confirm('Duyệt?')">Duyệt</button> <button type="submit" name="deposit_action" value="reject" class="button button-small" style="padding:2px 10px;font-size:11px;min-height:26px;line-height:24px" onclick="return confirm('Từ chối?')">Từ chối</button></form>
        <?php elseif($row->status === 'approved' && !empty($row->approved_at)): ?>
            <small>Duyệt <?php echo date('d/m H:i', strtotime($row->approved_at)); ?></small>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table></div>

<?php if($total_pages > 1):
    $pag_params = array('page' => 'traffictop-deposits');
    if($status_filter) $pag_params['status'] = $status_filter;
    if($search_filter) $pag_params['s'] = $search_filter;
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
