<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Handle actions
if(isset($_POST['campaign_action']) && wp_verify_nonce($_POST['_wpnonce'],'linkngon_campaign_action')){
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
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

            // Upload screenshots
            $screenshot_desktop_url = '';
            $screenshot_mobile_url = '';
            if (!function_exists('wp_handle_upload')) require_once ABSPATH . 'wp-admin/includes/file.php';
            $upload_overrides = array('test_form' => false);
            foreach (array('screenshot_desktop' => 'screenshot_desktop_url', 'screenshot_mobile' => 'screenshot_mobile_url') as $field => $var) {
                if (!empty($_FILES[$field]['name'])) {
                    $uploaded = wp_handle_upload($_FILES[$field], $upload_overrides);
                    if ($uploaded && !isset($uploaded['error'])) $$var = $uploaded['url'];
                }
            }

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
                'screenshot_desktop_url' => $screenshot_desktop_url,
                'screenshot_mobile_url' => $screenshot_mobile_url,
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

// Suppress errors if table doesn't exist
$wpdb->suppress_errors(true);
$count_sql = "SELECT COUNT(*) FROM {$prefix}keyword_campaigns kc LEFT JOIN {$prefix}customer_orders co ON co.id = kc.order_id $where";
$total = !empty($args) ? (int)$wpdb->get_var($wpdb->prepare($count_sql, $args)) : (int)$wpdb->get_var($count_sql);

$data_args = $args;
$data_args[] = $per_page;
$data_args[] = $offset;
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT kc.*, co.task_type, co.customer_username, co.quantity as order_quantity
     FROM {$prefix}keyword_campaigns kc
     LEFT JOIN {$prefix}customer_orders co ON co.id = kc.order_id
     $where
     ORDER BY kc.id DESC
     LIMIT %d OFFSET %d", $data_args
));
if(!is_array($rows)) $rows = array();
$wpdb->suppress_errors(false);

$total_pages = ceil(max(1,$total) / $per_page);

// Status counts (suppress errors if table missing)
$wpdb->suppress_errors(true);
$counts = $wpdb->get_results("SELECT status, COUNT(*) as cnt FROM {$prefix}keyword_campaigns GROUP BY status", OBJECT_K);
$wpdb->suppress_errors(false);
if(!is_array($counts)) $counts = array();

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

<?php
$camp_active = isset($counts['active']) ? (int)$counts['active']->cnt : 0;
$camp_pending = isset($counts['pending']) ? (int)$counts['pending']->cnt : 0;
$today_camp = date('Y-m-d', strtotime(linkngon_current_time()));
$camp_today_completed = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE step='verified' AND DATE(created_at)=%s", $today_camp));
$camp_today_total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE DATE(created_at)=%s", $today_camp));
$month_start_camp = date('Y-m-01', strtotime(linkngon_current_time()));
$camp_month = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE step='verified' AND created_at >= %s", $month_start_camp));
$camp_total_completed = (int) $wpdb->get_var("SELECT COALESCE(SUM(completed),0) FROM {$prefix}keyword_campaigns");
?>
<style>
.camp-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:16px}
.camp-stat{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:14px}
.camp-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center}
.camp-icon.cp1{background:#dbeafe;color:#2563eb} .camp-icon.cp2{background:#fef3c7;color:#d97706}
.camp-icon.cp3{background:#ede9fe;color:#7c3aed} .camp-icon.cp4{background:#e0e7ff;color:#4338ca}
.camp-icon.cp5{background:#d1fae5;color:#059669}
.camp-val{font-size:22px;font-weight:700;color:#1d2327;line-height:1.2}
.camp-label{font-size:12px;color:#6b7280}
@media(max-width:600px){.camp-stats{grid-template-columns:repeat(2,1fr)} .camp-val{font-size:16px} .camp-stat{padding:12px 14px;gap:10px} .camp-icon{width:38px;height:38px} .camp-icon svg{width:20px;height:20px}}
</style>
<div class="camp-stats">
    <div class="camp-stat"><div class="camp-icon cp1"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg></div><div><div class="camp-val"><?php echo $camp_active; ?>/<?php echo intval($total); ?></div><div class="camp-label">Từ Khoá</div></div></div>
    <div class="camp-stat"><div class="camp-icon cp2"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><div><div class="camp-val"><?php echo $camp_pending; ?></div><div class="camp-label">Chờ Duyệt</div></div></div>
    <div class="camp-stat"><div class="camp-icon cp3"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div><div><div class="camp-val"><?php echo number_format($camp_today_completed); ?>/<?php echo number_format($camp_today_total); ?></div><div class="camp-label">Chạy hôm nay</div></div></div>
    <div class="camp-stat"><div class="camp-icon cp4"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><rect x="7" y="13" width="3" height="3"/><rect x="14" y="13" width="3" height="3"/></svg></div><div><div class="camp-val"><?php echo number_format($camp_month); ?></div><div class="camp-label">Chạy tháng này</div></div></div>
    <div class="camp-stat"><div class="camp-icon cp5"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><div><div class="camp-val"><?php echo number_format($camp_total_completed); ?></div><div class="camp-label">Tổng Đã Chạy</div></div></div>
</div>

<!-- Tạo chiến dịch -->
<?php
$all_customers = $wpdb->get_results("SELECT u.ID, u.user_login FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} um ON um.user_id=u.ID AND um.meta_key='{$wpdb->prefix}capabilities' WHERE um.meta_value LIKE '%customer%' ORDER BY u.user_login");
$inp='style="width:100%;height:36px;border:1px solid #ddd;border-radius:4px;padding:0 8px;font-size:13px"';
$lbl='style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;color:#50575e"';
?>
<details style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:0;margin-bottom:20px">
<summary style="padding:14px 20px;cursor:pointer;font-weight:600;font-size:14px;color:#1d2327">+ Tạo chiến dịch cho khách hàng</summary>
<div style="padding:0 20px 20px">
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('linkngon_campaign_action'); ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
            <div><label <?php echo $lbl; ?>>Khách hàng <span style="color:red">*</span></label><select name="customer_id" required <?php echo $inp; ?>><option value="">-- Chọn --</option><?php foreach($all_customers as $c) echo '<option value="'.$c->ID.'">'.esc_html($c->user_login).'</option>'; ?></select></div>
            <div><label <?php echo $lbl; ?>>Loại dịch vụ</label><select name="task_type" id="adm_task_type" <?php echo $inp; ?> onchange="admUpdatePrice()"><option value="keyword_search">Traffic từ khóa</option><option value="traffic_direct">Traffic Direct</option></select></div>
            <div><label <?php echo $lbl; ?>>Từ khóa</label><input name="keyword" <?php echo $inp; ?> placeholder="Từ khóa SEO"></div>
            <div><label <?php echo $lbl; ?>>URL đích <span style="color:red">*</span></label><input name="target_url" type="url" required <?php echo $inp; ?> placeholder="https://..."></div>
            <div><label <?php echo $lbl; ?>>Tiêu đề</label><input name="title" <?php echo $inp; ?> placeholder="Tên chiến dịch"></div>
            <div><label <?php echo $lbl; ?>>Loại traffic</label><select name="traffic_type" id="adm_traffic_type" <?php echo $inp; ?> onchange="admUpdatePrice()"><option value="1step">1 bước</option><option value="2step">2 bước</option><option value="nocode">Không mã</option></select></div>
            <div><label <?php echo $lbl; ?>>Ảnh kết quả Desktop</label><input name="screenshot_desktop" type="file" accept="image/*" style="width:100%;font-size:13px;padding:6px 0"></div>
            <div><label <?php echo $lbl; ?>>Ảnh kết quả Mobile</label><input name="screenshot_mobile" type="file" accept="image/*" style="width:100%;font-size:13px;padding:6px 0"></div>
            <div><label <?php echo $lbl; ?>>Onsite (giây)</label><select name="onsite_time" id="adm_onsite" <?php echo $inp; ?> onchange="admUpdatePrice()"><option value="70">70s</option><option value="80">80s</option><option value="90">90s (+100đ)</option><option value="100">100s (+200đ)</option><option value="120">120s (+250đ)</option><option value="150">150s (+300đ)</option></select></div>
            <div><label <?php echo $lbl; ?>>Traffic/ngày</label><input name="daily_traffic" type="number" value="10" min="1" <?php echo $inp; ?>></div>
            <div><label <?php echo $lbl; ?>>Tổng số lượt</label><input name="quantity" type="number" value="150" min="1" <?php echo $inp; ?>></div>
            <div><label <?php echo $lbl; ?>>Giá/lượt (KH trả)</label><input name="price_per_view" id="adm_price" type="number" value="<?php echo linkngon_get_option('keyword_price_1step',1200); ?>" <?php echo $inp; ?> style="width:100%;height:36px;border:2px solid #0073aa;border-radius:4px;padding:0 8px;font-size:13px;font-weight:700;color:#0073aa"></div>
            <div><label <?php echo $lbl; ?>>User nhận/lượt</label><input name="user_reward" id="adm_reward" type="number" value="<?php echo linkngon_get_option('keyword_user_1step',800); ?>" <?php echo $inp; ?>></div>
            <div><label <?php echo $lbl; ?>>Trạng thái</label><select name="camp_status" <?php echo $inp; ?>><option value="active">Hoạt động ngay</option><option value="pending">Chờ duyệt</option><option value="paused">Tạm dừng</option></select></div>
        </div>
        <button type="submit" name="campaign_action" value="create" class="button button-primary" onclick="return confirm('Tạo chiến dịch?')">Tạo chiến dịch</button>
    </form>
    <script>
    var ADM_PRICES={
        keyword_search:{'1step':<?php echo (int)linkngon_get_option('keyword_price_1step',1200); ?>,'2step':<?php echo (int)linkngon_get_option('keyword_price_2step',1500); ?>,'nocode':<?php echo (int)linkngon_get_option('keyword_price_nocode',1200); ?>},
        traffic_direct:{'1step':<?php echo (int)linkngon_get_option('direct_price_1step',1200); ?>,'2step':<?php echo (int)linkngon_get_option('direct_price_2step',1200); ?>,'nocode':<?php echo (int)linkngon_get_option('direct_price_nocode',1200); ?>}
    };
    var ADM_REWARDS={
        keyword_search:{'1step':<?php echo (int)linkngon_get_option('keyword_user_1step',800); ?>,'2step':<?php echo (int)linkngon_get_option('keyword_user_2step',1000); ?>,'nocode':<?php echo (int)linkngon_get_option('keyword_user_nocode',800); ?>},
        traffic_direct:{'1step':<?php echo (int)linkngon_get_option('direct_user_1step',500); ?>,'2step':<?php echo (int)linkngon_get_option('direct_user_2step',700); ?>,'nocode':<?php echo (int)linkngon_get_option('direct_user_nocode',800); ?>}
    };
    var ADM_ONSITE_EXTRA={70:0,80:0,90:100,100:200,120:250,150:300};
    function admUpdatePrice(){
        var t=document.getElementById('adm_task_type').value;
        var tt=document.getElementById('adm_traffic_type').value;
        var os=parseInt(document.getElementById('adm_onsite').value);
        var base=(ADM_PRICES[t]||ADM_PRICES.keyword_search)[tt]||1200;
        var extra=ADM_ONSITE_EXTRA[os]||0;
        document.getElementById('adm_price').value=base+extra;
        var reward=(ADM_REWARDS[t]||ADM_REWARDS.keyword_search)[tt]||800;
        document.getElementById('adm_reward').value=reward;
    }
    </script>
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
    <th style="min-width:200px">Từ khóa / URL</th>
    <th>Traffic/ngày</th>
    <th>Đã chạy</th>
    <th>Loại/Onsite</th>
    <th>Trạng thái</th>
    <th>Thời gian</th>
    <th>Thao tác</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="9">Không có dữ liệu.</td></tr>
<?php else: foreach($rows as $row):
    $status_colors = ['active'=>'#46b450','paused'=>'#ffb900','pending'=>'#00a0d2','completed'=>'#82878c','rejected'=>'#dc3232'];
    $status_bg = ['active'=>'#edf7ed','paused'=>'#fff8e1','pending'=>'#fff3cd','completed'=>'#f5f5f5','rejected'=>'#fdecea'];
    $color = $status_colors[$row->status] ?? '#82878c';
    $bg = $status_bg[$row->status] ?? '#f5f5f5';
    $traffic_labels = ['1step'=>'1 bước','2step'=>'2 bước','nocode'=>'Mã cố định'];
    $traffic_colors = ['1step'=>'#2271b1','2step'=>'#dba617','nocode'=>'#8c5e2a'];
    $traffic_bg = ['1step'=>'#e7f3ff','2step'=>'#fff8e1','nocode'=>'#fef3e2'];
    $domain = parse_url($row->target_url ?? '', PHP_URL_HOST);
    $completed = intval($row->completed);
    $quantity = intval($row->quantity);
    $pct = $quantity > 0 ? min(100, round($completed/$quantity*100)) : 0;
    $spent = $completed * floatval($row->price_per_view);
    // Time ago
    $diff = time() - strtotime($row->created_at);
    if($diff < 3600) $ago = intval($diff/60).' phút';
    elseif($diff < 86400) $ago = intval($diff/3600).' giờ';
    else $ago = intval($diff/86400).' ngày';
    $tt = $row->traffic_type ?? '1step';
?>
<tr>
    <td><strong style="color:#2271b1">#<?php echo $row->id; ?></strong></td>
    <td><strong><?php echo esc_html($row->customer_username ?? '—'); ?></strong></td>
    <td>
        <?php if(!empty($row->keyword)): ?>
        <div style="font-weight:600;font-size:13px"><?php echo esc_html($row->keyword); ?></div>
        <?php endif; ?>
        <a href="<?php echo esc_url($row->target_url); ?>" target="_blank" style="font-size:11px;color:#787c82"><?php echo esc_html($domain); ?></a>
    </td>
    <td>
        <div style="font-weight:600;color:<?php echo $completed>0?'#dba617':'#787c82'; ?>"><?php echo $completed; ?>/<?php echo $quantity; ?></div>
        <?php if($pct > 0): ?><div style="height:4px;background:#eee;border-radius:2px;margin-top:3px;width:60px"><div style="height:100%;border-radius:2px;width:<?php echo $pct; ?>%;background:<?php echo $pct>=100?'#46b450':($pct>=50?'#dba617':'#2271b1'); ?>"></div></div><?php endif; ?>
    </td>
    <td>
        <div style="font-weight:600"><?php echo $completed; ?></div>
        <small style="color:#787c82">= <?php echo linkngon_format_money($spent); ?></small>
    </td>
    <td>
        <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:<?php echo $traffic_bg[$tt] ?? '#f5f5f5'; ?>;color:<?php echo $traffic_colors[$tt] ?? '#787c82'; ?>"><?php echo $traffic_labels[$tt] ?? $tt; ?></span>
        <div style="font-size:10px;color:#787c82;margin-top:2px"><?php echo intval($row->onsite_time ?? 70); ?>s</div>
    </td>
    <td><span style="display:inline-block;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:600;background:<?php echo $bg; ?>;color:<?php echo $color; ?>"><?php echo $status_labels[$row->status] ?? $row->status; ?></span></td>
    <td style="font-size:12px;color:#787c82"><?php echo $ago; ?></td>
    <td style="white-space:nowrap">
        <?php if($row->status === 'pending'): ?>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_campaign_action'); ?>
            <input type="hidden" name="campaign_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="campaign_action" value="approve" class="button button-small button-primary" title="Duyệt">Duyệt</button>
            <button type="submit" name="campaign_action" value="reject" class="button button-small" style="color:#dc3232" title="Từ chối" onclick="return confirm('Từ chối chiến dịch #<?php echo $row->id; ?>?')">Từ chối</button>
        </form>
        <?php elseif($row->status === 'active'): ?>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_campaign_action'); ?>
            <input type="hidden" name="campaign_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="campaign_action" value="pause" class="button button-small" title="Tạm dừng">Dừng</button>
        </form>
        <?php elseif($row->status === 'paused'): ?>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('linkngon_campaign_action'); ?>
            <input type="hidden" name="campaign_id" value="<?php echo $row->id; ?>">
            <button type="submit" name="campaign_action" value="resume" class="button button-small button-primary" title="Tiếp tục">Chạy</button>
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
