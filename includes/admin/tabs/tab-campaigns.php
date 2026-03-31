<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Handle actions
if(isset($_POST['campaign_action']) && wp_verify_nonce($_POST['_wpnonce'],'linkngon_campaign_action')){
    $campaign_id = intval($_POST['campaign_id']);
    $action = sanitize_text_field($_POST['campaign_action']);

    if($action === 'approve'){
        $wpdb->update($prefix.'keyword_campaigns', ['status'=>'active','updated_at'=>linkngon_current_time()], ['id'=>$campaign_id]);
        $wpdb->update($prefix.'customer_orders', ['status'=>'active','approved_at'=>linkngon_current_time()], ['task_id'=>$campaign_id]);
        echo '<div class="notice notice-success"><p>Chiến dịch #'.$campaign_id.' đã được duyệt.</p></div>';
    } elseif($action === 'pause'){
        $wpdb->update($prefix.'keyword_campaigns', ['status'=>'paused','updated_at'=>linkngon_current_time()], ['id'=>$campaign_id]);
        $wpdb->update($prefix.'customer_orders', ['status'=>'paused'], ['task_id'=>$campaign_id]);
        echo '<div class="notice notice-warning"><p>Chiến dịch #'.$campaign_id.' đã tạm dừng.</p></div>';
    } elseif($action === 'resume'){
        $wpdb->update($prefix.'keyword_campaigns', ['status'=>'active','updated_at'=>linkngon_current_time()], ['id'=>$campaign_id]);
        $wpdb->update($prefix.'customer_orders', ['status'=>'active'], ['task_id'=>$campaign_id]);
        echo '<div class="notice notice-success"><p>Chiến dịch #'.$campaign_id.' đã tiếp tục.</p></div>';
    } elseif($action === 'reject'){
        $reason = isset($_POST['reject_reason']) ? sanitize_text_field($_POST['reject_reason']) : '';
        $wpdb->update($prefix.'keyword_campaigns', ['status'=>'rejected','reject_reason'=>$reason,'updated_at'=>linkngon_current_time()], ['id'=>$campaign_id]);
        $wpdb->update($prefix.'customer_orders', ['status'=>'rejected','reject_reason'=>$reason], ['task_id'=>$campaign_id]);
        echo '<div class="notice notice-error"><p>Chiến dịch #'.$campaign_id.' đã bị từ chối.</p></div>';
    }
    } elseif($action === 'create'){
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $target_url = esc_url_raw($_POST['target_url'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $task_type = sanitize_text_field($_POST['task_type'] ?? 'keyword_search');
        $traffic_type = sanitize_text_field($_POST['traffic_type'] ?? '1step');
        $onsite_time = intval($_POST['onsite_time'] ?? 70);
        $daily_traffic = max(1, intval($_POST['daily_traffic'] ?? 10));
        $quantity = max(1, intval($_POST['quantity'] ?? 150));
        $price_per_view = floatval($_POST['price_per_view'] ?? 1200);
        $user_reward = floatval($_POST['user_reward'] ?? 800);
        $status = sanitize_text_field($_POST['camp_status'] ?? 'active');

        if(!$customer_id || !$target_url){
            echo '<div class="notice notice-error"><p>Thiếu thông tin bắt buộc.</p></div>';
        } else {
            if(empty($title)) $title = $keyword ?: parse_url($target_url, PHP_URL_HOST);
            $customer = get_user_by('ID', $customer_id);

            // Create order
            $wpdb->insert($prefix.'customer_orders', [
                'customer_id' => $customer_id,
                'customer_username' => $customer ? $customer->user_login : '',
                'task_type' => $task_type,
                'title' => $title,
                'task_url' => $target_url,
                'quantity' => $quantity,
                'completed' => 0,
                'price_per_task' => $price_per_view,
                'total_amount' => $price_per_view * $quantity,
                'amount_spent' => 0,
                'status' => $status,
                'created_at' => linkngon_current_time(),
                'updated_at' => linkngon_current_time(),
            ]);
            $order_id = $wpdb->insert_id;

            // Create campaign
            $wpdb->insert($prefix.'keyword_campaigns', [
                'customer_id' => $customer_id,
                'order_id' => $order_id,
                'title' => $title,
                'keyword' => $keyword,
                'target_url' => $target_url,
                'traffic_type' => $traffic_type,
                'onsite_time' => $onsite_time,
                'quantity' => $quantity,
                'completed' => 0,
                'price_per_view' => $price_per_view,
                'user_reward' => $user_reward,
                'daily_traffic' => $daily_traffic,
                'status' => $status,
                'created_at' => linkngon_current_time(),
                'updated_at' => linkngon_current_time(),
            ]);
            echo '<div class="notice notice-success"><p>Đã tạo chiến dịch "'.$title.'" cho '.esc_html($customer?$customer->user_login:'#'.$customer_id).' (trạng thái: '.$status.')</p></div>';
        }
    }
}

// Filters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$where = "WHERE 1=1";
$args = array();
if($status_filter) {
    $where .= " AND kc.status = %s";
    $args[] = $status_filter;
}

$page_num = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($page_num - 1) * $per_page;

$count_sql = "SELECT COUNT(*) FROM {$prefix}keyword_campaigns kc LEFT JOIN {$prefix}customer_orders co ON co.id = kc.order_id $where";
$total = !empty($args) ? $wpdb->get_var($wpdb->prepare($count_sql, $args)) : $wpdb->get_var($count_sql);

$args[] = $per_page;
$args[] = $offset;
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT kc.*, co.task_type, co.customer_username, co.quantity as order_quantity
     FROM {$prefix}keyword_campaigns kc
     LEFT JOIN {$prefix}customer_orders co ON co.id = kc.order_id
     $where
     ORDER BY kc.id DESC
     LIMIT %d OFFSET %d", $args
));

$total_pages = ceil($total / $per_page);

// Status counts
$counts = $wpdb->get_results("SELECT status, COUNT(*) as cnt FROM {$prefix}keyword_campaigns GROUP BY status", OBJECT_K);

$status_labels = [
    'pending' => 'Chờ duyệt',
    'active' => 'Hoạt động',
    'paused' => 'Tạm dừng',
    'completed' => 'Hoàn thành',
    'rejected' => 'Từ chối',
];
?>
<div class="wrap">
<h1>Chiến dịch</h1>

<!-- Tạo chiến dịch mới -->
<details style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:0;margin-bottom:20px;max-width:700px">
<summary style="padding:14px 20px;cursor:pointer;font-weight:600;font-size:14px;color:#1d2327">+ Tạo chiến dịch cho khách hàng</summary>
<div style="padding:0 20px 20px">
    <form method="post">
        <?php wp_nonce_field('linkngon_campaign_action'); ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">Khách hàng <span style="color:red">*</span></label>
                <select name="customer_id" required style="width:100%;height:36px;border:1px solid #ddd;border-radius:4px;padding:0 8px">
                    <option value="">-- Chọn --</option>
                    <?php
                    $cust_list = $wpdb->get_results("SELECT u.ID, u.user_login FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} um ON um.user_id=u.ID AND um.meta_key='{$wpdb->prefix}capabilities' WHERE um.meta_value LIKE '%customer%' ORDER BY u.user_login");
                    foreach($cust_list as $c): ?>
                    <option value="<?php echo $c->ID; ?>"><?php echo esc_html($c->user_login); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">Loại dịch vụ</label>
                <select name="task_type" style="width:100%;height:36px;border:1px solid #ddd;border-radius:4px;padding:0 8px">
                    <option value="keyword_search">Traffic từ khóa</option>
                    <option value="traffic_direct">Traffic Direct</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">Từ khóa</label>
                <input name="keyword" style="width:100%;height:36px;border:1px solid #ddd;border-radius:4px;padding:0 8px" placeholder="Từ khóa SEO">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">URL đích <span style="color:red">*</span></label>
                <input name="target_url" type="url" required style="width:100%;height:36px;border:1px solid #ddd;border-radius:4px;padding:0 8px" placeholder="https://...">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">Tiêu đề</label>
                <input name="title" style="width:100%;height:36px;border:1px solid #ddd;border-radius:4px;padding:0 8px" placeholder="Tên chiến dịch">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">Loại traffic</label>
                <select name="traffic_type" style="width:100%;height:36px;border:1px solid #ddd;border-radius:4px;padding:0 8px">
                    <option value="1step">1 bước</option>
                    <option value="2step">2 bước</option>
                    <option value="nocode">Không mã</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">Onsite (giây)</label>
                <input name="onsite_time" type="number" value="70" min="30" style="width:100%;height:36px;border:1px solid #ddd;border-radius:4px;padding:0 8px">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">Traffic/ngày</label>
                <input name="daily_traffic" type="number" value="10" min="1" style="width:100%;height:36px;border:1px solid #ddd;border-radius:4px;padding:0 8px">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">Tổng số lượt</label>
                <input name="quantity" type="number" value="150" min="1" style="width:100%;height:36px;border:1px solid #ddd;border-radius:4px;padding:0 8px">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">Giá/lượt (KH trả)</label>
                <input name="price_per_view" type="number" value="<?php echo linkngon_get_option('keyword_price_1step',1200); ?>" style="width:100%;height:36px;border:1px solid #ddd;border-radius:4px;padding:0 8px">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">User nhận/lượt</label>
                <input name="user_reward" type="number" value="<?php echo linkngon_get_option('keyword_user_1step',800); ?>" style="width:100%;height:36px;border:1px solid #ddd;border-radius:4px;padding:0 8px">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">Trạng thái</label>
                <select name="camp_status" style="width:100%;height:36px;border:1px solid #ddd;border-radius:4px;padding:0 8px">
                    <option value="active">Hoạt động ngay</option>
                    <option value="pending">Chờ duyệt</option>
                    <option value="paused">Tạm dừng</option>
                </select>
            </div>
        </div>
        <button type="submit" name="campaign_action" value="create" class="button button-primary" onclick="return confirm('Tạo chiến dịch?')">Tạo chiến dịch</button>
    </form>
</div>
</details>

<ul class="subsubsub">
    <li><a href="?page=linkngon-campaigns" <?php echo !$status_filter?'class="current"':''; ?>>Tất cả <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
    <?php foreach(['pending','active','paused','completed','rejected'] as $s): ?>
    <li><a href="?page=linkngon-campaigns&status=<?php echo $s; ?>" <?php echo $status_filter===$s?'class="current"':''; ?>><?php echo $status_labels[$s]; ?> <span class="count">(<?php echo isset($counts[$s]) ? $counts[$s]->cnt : 0; ?>)</span></a><?php echo $s!=='rejected'?' |':''; ?></li>
    <?php endforeach; ?>
</ul>
<br class="clear">

<div style="overflow-x:auto"><table class="widefat striped">
<thead>
<tr>
    <th>ID</th>
    <th>Khách hàng</th>
    <th>Tiêu đề / Từ khóa</th>
    <th>URL đích</th>
    <th>Loại traffic</th>
    <th>Loại nhiệm vụ</th>
    <th>Giá/lượt</th>
    <th>Giới hạn/ngày</th>
    <th>Tiến độ</th>
    <th>Trạng thái</th>
    <th>Ngày tạo</th>
    <th>Thao tác</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="12">Không có dữ liệu.</td></tr>
<?php else: foreach($rows as $row):
    $status_colors = ['active'=>'#46b450','paused'=>'#ffb900','pending'=>'#00a0d2','completed'=>'#82878c','rejected'=>'#dc3232'];
    $color = isset($status_colors[$row->status]) ? $status_colors[$row->status] : '#82878c';
?>
<tr>
    <td><?php echo intval($row->id); ?></td>
    <td><?php echo esc_html($row->customer_username ?? '—'); ?></td>
    <td>
        <strong><?php echo esc_html($row->title); ?></strong>
        <?php if(!empty($row->keyword)): ?><br><code><?php echo esc_html($row->keyword); ?></code><?php endif; ?>
    </td>
    <td><a href="<?php echo esc_url($row->target_url); ?>" target="_blank" title="<?php echo esc_attr($row->target_url); ?>"><?php echo esc_html(mb_strimwidth($row->target_url,0,40,'...')); ?></a></td>
    <td><?php echo esc_html($row->traffic_type ?: '1step'); ?></td>
    <td><?php echo esc_html($row->task_type ?? 'keyword_search'); ?></td>
    <td><?php echo linkngon_format_money($row->price_per_view); ?></td>
    <td><?php echo intval($row->daily_traffic); ?></td>
    <td><?php echo intval($row->completed); ?>/<?php echo intval($row->quantity); ?></td>
    <td><span style="color:<?php echo $color; ?>;font-weight:bold;"><?php echo $status_labels[$row->status] ?? ucfirst($row->status); ?></span></td>
    <td><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
    <td>
        <?php if($row->status === 'pending'): ?>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_campaign_action'); ?>
            <input type="hidden" name="campaign_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="campaign_action" value="approve" class="button button-small button-primary">Duyệt</button>
            <button type="button" class="button button-small" onclick="this.nextElementSibling.style.display='inline'">Từ chối</button>
            <span style="display:none;">
                <input type="text" name="reject_reason" placeholder="Lý do..." style="width:120px;">
                <button type="submit" name="campaign_action" value="reject" class="button button-small">Xác nhận từ chối</button>
            </span>
        </form>
        <?php elseif($row->status === 'active'): ?>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_campaign_action'); ?>
            <input type="hidden" name="campaign_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="campaign_action" value="pause" class="button button-small">Tạm dừng</button>
        </form>
        <?php elseif($row->status === 'paused'): ?>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_campaign_action'); ?>
            <input type="hidden" name="campaign_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="campaign_action" value="resume" class="button button-small button-primary">Tiếp tục</button>
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
                <a class="button" href="?page=linkngon-campaigns<?php echo $status_filter?"&status=$status_filter":""; ?>&paged=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

</div>
