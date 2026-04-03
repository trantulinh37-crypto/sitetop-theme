<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Handle actions
if(isset($_POST['campaign_action']) && wp_verify_nonce($_POST['_wpnonce'],'linkngon_campaign_action')){
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    $action = sanitize_text_field($_POST['campaign_action']);

    // Fetch campaign to get order_id for syncing
    $campaign_row = ($action !== 'create') ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}keyword_campaigns WHERE id=%d", $campaign_id)) : null;

    if($action === 'approve'){
        if(!$campaign_row){ echo '<div class="notice notice-error"><p>Không tìm thấy chiến dịch.</p></div>'; }
        else {
            $result = linkngon_approve_campaign($campaign_id, get_current_user_id());
            if(is_wp_error($result)){
                echo '<div class="notice notice-error"><p>Lỗi: '.esc_html($result->get_error_message()).'</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>Chiến dịch #'.$campaign_id.' đã được duyệt.</p></div>';
            }
        }
    } elseif($action === 'pause'){
        if(!$campaign_row){ echo '<div class="notice notice-error"><p>Không tìm thấy chiến dịch.</p></div>'; }
        else {
            $now = linkngon_current_time();
            $wpdb->update($prefix.'keyword_campaigns', ['status'=>'paused','updated_at'=>$now], ['id'=>$campaign_id]);
            if($campaign_row->order_id) $wpdb->update($prefix.'customer_orders', ['status'=>'paused','updated_at'=>$now], ['id'=>$campaign_row->order_id]);
            delete_transient('linkngon_eligible_campaigns');
            echo '<div class="notice notice-warning"><p>Chiến dịch #'.$campaign_id.' đã tạm dừng.</p></div>';
        }
    } elseif($action === 'resume'){
        if(!$campaign_row){ echo '<div class="notice notice-error"><p>Không tìm thấy chiến dịch.</p></div>'; }
        else {
            $now = linkngon_current_time();
            $wpdb->update($prefix.'keyword_campaigns', ['status'=>'active','updated_at'=>$now], ['id'=>$campaign_id]);
            if($campaign_row->order_id) $wpdb->update($prefix.'customer_orders', ['status'=>'active','updated_at'=>$now], ['id'=>$campaign_row->order_id]);
            delete_transient('linkngon_eligible_campaigns');
            echo '<div class="notice notice-success"><p>Chiến dịch #'.$campaign_id.' đã tiếp tục.</p></div>';
        }
    } elseif($action === 'reject'){
        $reason = isset($_POST['reject_reason']) ? sanitize_text_field($_POST['reject_reason']) : '';
        if(!$campaign_row){ echo '<div class="notice notice-error"><p>Không tìm thấy chiến dịch.</p></div>'; }
        else {
            $now = linkngon_current_time();
            $wpdb->update($prefix.'keyword_campaigns', ['status'=>'rejected','reject_reason'=>$reason,'updated_at'=>$now], ['id'=>$campaign_id]);
            if($campaign_row->order_id) $wpdb->update($prefix.'customer_orders', ['status'=>'rejected','reject_reason'=>$reason,'updated_at'=>$now], ['id'=>$campaign_row->order_id]);
            echo '<div class="notice notice-error"><p>Chiến dịch #'.$campaign_id.' đã bị từ chối.</p></div>';
        }
    } elseif($action === 'delete'){
        if(!$campaign_row){ echo '<div class="notice notice-error"><p>Không tìm thấy chiến dịch.</p></div>'; }
        else {
            // Soft delete - preserve for financial audit trail
            $now = linkngon_current_time();
            $wpdb->update($prefix.'keyword_campaigns', ['status'=>'deleted','updated_at'=>$now], ['id'=>$campaign_id]);
            if($campaign_row->order_id) $wpdb->update($prefix.'customer_orders', ['status'=>'deleted','updated_at'=>$now], ['id'=>$campaign_row->order_id]);
            delete_transient('linkngon_eligible_campaigns');
            echo '<div class="notice notice-warning"><p>Chiến dịch #'.$campaign_id.' đã bị xóa.</p></div>';
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
                'campaign_type' => $task_type,
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
} else {
    // Hide deleted campaigns by default
    $where .= " AND kc.status != 'deleted'";
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
$today_camp_date = date('Y-m-d', strtotime(linkngon_current_time()));
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT kc.*, co.task_type, co.customer_username, co.quantity as order_quantity,
            (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND step='verified' AND DATE(created_at)=%s) as today_views
     FROM {$prefix}keyword_campaigns kc
     LEFT JOIN {$prefix}customer_orders co ON co.id = kc.order_id
     $where
     ORDER BY kc.id DESC
     LIMIT %d OFFSET %d", array_merge([$today_camp_date], $data_args)
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
.camp-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}
.camp-stat{border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px}
.camp-stat.cs1{background:#eff6ff;border:2px solid #bfdbfe} .camp-stat.cs2{background:#ede9fe;border:2px solid #c4b5fd}
.camp-stat.cs3{background:#fef2f2;border:2px solid #fecaca} .camp-stat.cs4{background:#fffbeb;border:2px solid #fde68a}
.camp-val{font-size:22px;font-weight:700;line-height:1.2}
.camp-stat.cs1 .camp-val{color:#1e40af} .camp-stat.cs2 .camp-val{color:#5b21b6}
.camp-stat.cs3 .camp-val{color:#991b1b} .camp-stat.cs4 .camp-val{color:#92400e}
.camp-label{font-size:12px;color:#6b7280}
.camp-ico{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center}
.camp-ico.ci1{background:#dbeafe;color:#2563eb} .camp-ico.ci2{background:#c4b5fd;color:#7c3aed}
.camp-ico.ci3{background:#fecaca;color:#dc2626} .camp-ico.ci4{background:#fde68a;color:#d97706}
@media(max-width:600px){.camp-stats{grid-template-columns:repeat(2,1fr)} .camp-val{font-size:16px} .camp-stat{padding:12px 14px} .camp-ico{width:38px;height:38px} .camp-ico svg{width:20px;height:20px}}
</style>
<div class="camp-stats">
    <div class="camp-stat cs1"><div><div class="camp-val"><?php echo $camp_active; ?>/<?php echo intval($total); ?></div><div class="camp-label">Từ khoá</div></div><div class="camp-ico ci1"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg></div></div>
    <div class="camp-stat cs2"><div><div class="camp-val"><?php echo $camp_pending; ?></div><div class="camp-label">Chờ duyệt</div></div><div class="camp-ico ci2"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div></div>
    <div class="camp-stat cs3"><div><div class="camp-val"><?php echo number_format($camp_today_completed); ?>/<?php echo number_format($camp_today_total); ?></div><div class="camp-label">Đã chạy hôm nay</div></div><div class="camp-ico ci3"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div></div>
    <div class="camp-stat cs4"><div><div class="camp-val"><?php echo number_format($camp_total_completed); ?></div><div class="camp-label">Tổng đã chạy</div></div><div class="camp-ico ci4"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div></div>
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
    <th>Trạng thái mã</th>
    <th>Trạng thái</th>
    <th>Thao tác</th>
    <th>Thời gian</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="10">Không có dữ liệu.</td></tr>
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
        <div style="font-weight:600"><span style="color:#dba617"><?php echo intval($row->today_views ?? 0); ?></span>/<?php echo intval($row->daily_traffic ?? 10); ?></div>
    </td>
    <td>
        <div style="font-weight:600"><?php echo $completed; ?></div>
        <?php if($pct > 0): ?><div style="height:4px;background:#eee;border-radius:2px;margin-top:3px;width:60px"><div style="height:100%;border-radius:2px;width:<?php echo $pct; ?>%;background:<?php echo $pct>=100?'#46b450':($pct>=50?'#dba617':'#2271b1'); ?>"></div></div><?php endif; ?>
        <small style="color:#787c82">= <?php echo linkngon_format_money($spent); ?></small>
    </td>
    <td>
        <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:<?php echo $traffic_bg[$tt] ?? '#f5f5f5'; ?>;color:<?php echo $traffic_colors[$tt] ?? '#787c82'; ?>"><?php echo $traffic_labels[$tt] ?? $tt; ?></span>
        <div style="font-size:10px;color:#787c82;margin-top:2px"><?php echo intval($row->onsite_time ?? 70); ?>s</div>
    </td>
    <td><?php
        if($tt === 'nocode'):
            echo '<span style="display:inline-block;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:600;background:#f5f5f5;color:#787c82">Không cần</span>';
        else:
            $wcs = $row->widget_code_status ?? 'not_attached';
            $wcs_attached = ($wcs === 'attached');
            $wcs_bg = $wcs_attached ? '#edf7ed' : '#fff8e1';
            $wcs_color = $wcs_attached ? '#46b450' : '#dba617';
        ?>
            <select onchange="updateWidgetCodeStatus(<?php echo $row->id; ?>, this.value)" style="padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600;border:1px solid <?php echo $wcs_color; ?>;background:<?php echo $wcs_bg; ?>;color:<?php echo $wcs_color; ?>;cursor:pointer;appearance:auto">
                <option value="not_attached" <?php echo !$wcs_attached ? 'selected' : ''; ?>>Chưa gắn</option>
                <option value="attached" <?php echo $wcs_attached ? 'selected' : ''; ?>>Đã gắn</option>
            </select>
        <?php endif;
    ?></td>
    <td><span style="display:inline-block;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:600;background:<?php echo $bg; ?>;color:<?php echo $color; ?>"><?php echo $status_labels[$row->status] ?? $row->status; ?></span></td>
    <?php $bs='width:28px;height:28px;border-radius:6px;border:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center'; ?>
    <td style="white-space:nowrap">
        <div style="display:inline-flex;gap:3px;align-items:center">
            <button type="button" onclick="openAdminEditCamp(<?php echo $row->id; ?>)" title="Chỉnh sửa" style="<?php echo $bs; ?>;background:#DBEAFE;color:#2563EB"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
            <form method="post" style="display:inline-flex;gap:3px;align-items:center">
                <?php wp_nonce_field('linkngon_campaign_action'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo $row->id; ?>">
                <?php if($row->status === 'pending'): ?>
                <button type="submit" name="campaign_action" value="approve" title="Duyệt" style="<?php echo $bs; ?>;background:#46b450;color:#fff"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></button>
                <button type="submit" name="campaign_action" value="reject" title="Từ chối" style="<?php echo $bs; ?>;background:#dba617;color:#fff" onclick="return confirm('Từ chối #<?php echo $row->id; ?>?')"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                <button type="submit" name="campaign_action" value="delete" title="Xóa" style="<?php echo $bs; ?>;background:#fde8e8;color:#dc3232" onclick="return confirm('Xóa #<?php echo $row->id; ?>?')"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg></button>
                <?php elseif($row->status === 'active'): ?>
                <button type="submit" name="campaign_action" value="pause" title="Tạm dừng" style="<?php echo $bs; ?>;background:#dba617;color:#fff"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg></button>
                <?php elseif($row->status === 'paused'): ?>
                <button type="submit" name="campaign_action" value="resume" title="Tiếp tục" style="<?php echo $bs; ?>;background:#46b450;color:#fff"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg></button>
                <button type="submit" name="campaign_action" value="delete" title="Xóa" style="<?php echo $bs; ?>;background:#fde8e8;color:#dc3232" onclick="return confirm('Xóa #<?php echo $row->id; ?>?')"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg></button>
                <?php endif; ?>
            </form>
        </div>
    </td>
    <td style="font-size:11px;color:#787c82;white-space:nowrap"><?php echo date('d/m/Y H:i', strtotime($row->created_at)); ?></td>
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

<!-- Admin Edit Campaign Modal -->
<div id="adminEditCampModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.45);z-index:10000;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;overflow-y:auto">
<div style="background:#fff;border-radius:12px;width:100%;max-width:620px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);margin:auto">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e5e7eb">
        <h3 style="font-size:16px;font-weight:700;color:#1d2327;margin:0">Chỉnh sửa chiến dịch <span id="admEditCampLabel" style="color:#2271b1"></span></h3>
        <button onclick="document.getElementById('adminEditCampModal').style.display='none'" style="width:28px;height:28px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;font-size:16px;color:#9ca3af;display:flex;align-items:center;justify-content:center">&times;</button>
    </div>
    <style>#admEditCampForm .eg{display:grid;gap:12px;margin-bottom:12px}@media(max-width:480px){#admEditCampForm .eg{grid-template-columns:1fr!important}}</style>
    <form id="admEditCampForm" style="padding:20px" enctype="multipart/form-data">
        <input type="hidden" id="admEditId">
        <div class="eg" style="grid-template-columns:1fr 100px">
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Từ khóa</label><input id="admEditKw" style="width:100%;height:36px;border:1px solid #ddd;border-radius:6px;padding:0 10px;font-size:13px"></div>
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Traffic/ngày</label><input id="admEditDaily" type="number" min="1" max="100" style="width:100%;height:36px;border:1px solid #ddd;border-radius:6px;padding:0 10px;font-size:13px"></div>
        </div>
        <div class="eg" style="grid-template-columns:1fr 1fr">
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">URL đích <span style="color:red">*</span></label><input id="admEditUrl" type="url" required style="width:100%;height:36px;border:1px solid #ddd;border-radius:6px;padding:0 10px;font-size:13px"></div>
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Tiêu đề</label><input id="admEditTitle" style="width:100%;height:36px;border:1px solid #ddd;border-radius:6px;padding:0 10px;font-size:13px"></div>
        </div>
        <div class="eg" style="grid-template-columns:1fr 1fr 1fr">
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Loại traffic</label><select id="admEditTT" style="width:100%;height:36px;border:1px solid #ddd;border-radius:6px;padding:0 8px;font-size:13px"><option value="1step">1 bước</option><option value="2step">2 bước</option><option value="nocode">Mã cố định</option></select></div>
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Giá/view (KH trả)</label><div id="admEditPrice" style="height:36px;border:1px solid #ddd;border-radius:6px;padding:0 10px;font-size:13px;font-weight:700;color:#0073aa;display:flex;align-items:center;background:#f7f5f0"></div></div>
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Onsite</label><select id="admEditOnsite" style="width:100%;height:36px;border:1px solid #ddd;border-radius:6px;padding:0 8px;font-size:13px"><option value="70">70s</option><option value="80">80s</option><option value="90">90s</option><option value="100">100s</option><option value="120">120s</option><option value="150">150s</option></select></div>
        </div>
        <div class="eg" style="grid-template-columns:1fr 1fr">
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">User nhận/view</label><div id="admEditReward" style="height:36px;border:1px solid #ddd;border-radius:6px;padding:0 10px;font-size:13px;font-weight:600;color:#46b450;display:flex;align-items:center;background:#f7f5f0"></div></div>
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Tổng số lượt</label><input id="admEditQty" type="number" min="1" style="width:100%;height:36px;border:1px solid #ddd;border-radius:6px;padding:0 10px;font-size:13px"></div>
        </div>
        <div class="eg" style="grid-template-columns:1fr 1fr;margin-bottom:16px">
            <div style="min-width:0"><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Ảnh Desktop</label><div id="admEditSsDPrev" style="height:100px;background:#f7f5f0;border-radius:6px;display:flex;align-items:center;justify-content:center;margin-bottom:6px;overflow:hidden"><span style="font-size:11px;color:#9ca3af">Chưa có</span></div><label style="display:flex;align-items:center;justify-content:center;gap:6px;padding:8px;background:#2271b1;color:#fff;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Tải ảnh<input type="file" id="admEditSsD" accept="image/*" style="display:none" onchange="admPreviewSS(this,'admEditSsDPrev')"></label></div>
            <div style="min-width:0"><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Ảnh Mobile</label><div id="admEditSsMPrev" style="height:100px;background:#f7f5f0;border-radius:6px;display:flex;align-items:center;justify-content:center;margin-bottom:6px;overflow:hidden"><span style="font-size:11px;color:#9ca3af">Chưa có</span></div><label style="display:flex;align-items:center;justify-content:center;gap:6px;padding:8px;background:#2271b1;color:#fff;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Tải ảnh<input type="file" id="admEditSsM" accept="image/*" style="display:none" onchange="admPreviewSS(this,'admEditSsMPrev')"></label></div>
        </div>
        <div id="admEditMsg" style="min-height:18px;margin-bottom:8px;font-size:13px;text-align:center"></div>
        <button type="submit" id="admEditBtn" class="button button-primary" style="width:100%;height:38px;font-size:14px">Lưu thay đổi</button>
    </form>
</div>
</div>

<script>
var ADM_AJAX = '<?php echo admin_url("admin-ajax.php"); ?>';
var ADM_NONCE = '<?php echo wp_create_nonce("linkngon_admin_nonce"); ?>';
var ADM_PRICE_SETTINGS = {
    keyword_search: {'1step':<?php echo (int)linkngon_get_option('keyword_price_1step',1200); ?>,'2step':<?php echo (int)linkngon_get_option('keyword_price_2step',1500); ?>,'nocode':<?php echo (int)linkngon_get_option('keyword_price_nocode',1200); ?>},
    traffic_direct: {'1step':<?php echo (int)linkngon_get_option('direct_price_1step',1200); ?>,'2step':<?php echo (int)linkngon_get_option('direct_price_2step',1200); ?>,'nocode':<?php echo (int)linkngon_get_option('direct_price_nocode',1200); ?>}
};
var ADM_REWARD_SETTINGS = {
    keyword_search: {'1step':<?php echo (int)linkngon_get_option('keyword_user_1step',800); ?>,'2step':<?php echo (int)linkngon_get_option('keyword_user_2step',1000); ?>,'nocode':<?php echo (int)linkngon_get_option('keyword_user_nocode',800); ?>},
    traffic_direct: {'1step':<?php echo (int)linkngon_get_option('direct_user_1step',500); ?>,'2step':<?php echo (int)linkngon_get_option('direct_user_2step',700); ?>,'nocode':<?php echo (int)linkngon_get_option('direct_user_nocode',800); ?>}
};
var ADM_ONSITE_EXTRA = {70:0,80:0,90:100,100:200,120:250,150:300};
var _admEditTaskType = 'keyword_search';

function admCalcPriceReward() {
    var tt = document.getElementById('admEditTT').value;
    var os = parseInt(document.getElementById('admEditOnsite').value);
    var prices = ADM_PRICE_SETTINGS[_admEditTaskType] || ADM_PRICE_SETTINGS.keyword_search;
    var rewards = ADM_REWARD_SETTINGS[_admEditTaskType] || ADM_REWARD_SETTINGS.keyword_search;
    var price = (prices[tt] || 1200) + (ADM_ONSITE_EXTRA[os] || 0);
    var reward = rewards[tt] || 800;
    document.getElementById('admEditPrice').textContent = price.toLocaleString('vi-VN') + 'đ';
    document.getElementById('admEditReward').textContent = reward.toLocaleString('vi-VN') + 'đ';
}

document.getElementById('admEditTT').addEventListener('change', admCalcPriceReward);
document.getElementById('admEditOnsite').addEventListener('change', admCalcPriceReward);

function admPreviewSS(input, prevId) {
    var f = input.files[0]; if (!f) return;
    var r = new FileReader();
    r.onload = function(e) { document.getElementById(prevId).innerHTML = '<img src="'+e.target.result+'" style="max-height:100px;max-width:100%;object-fit:contain;border-radius:4px">'; };
    r.readAsDataURL(f);
}

function openAdminEditCamp(id) {
    var fd = new FormData();
    fd.append('action','linkngon_admin_get_campaign');
    fd.append('nonce',ADM_NONCE);
    fd.append('campaign_id',id);
    fetch(ADM_AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if (!r.success) { alert(r.data||'Lỗi'); return; }
        var c = r.data;
        document.getElementById('admEditCampLabel').textContent = '#'+c.id+' ('+c.customer_username+')';
        document.getElementById('admEditId').value = c.id;
        document.getElementById('admEditKw').value = c.keyword||'';
        document.getElementById('admEditDaily').value = c.daily_traffic||10;
        document.getElementById('admEditUrl').value = c.target_url||'';
        document.getElementById('admEditTitle').value = c.title||'';
        document.getElementById('admEditTT').value = c.traffic_type||'1step';
        document.getElementById('admEditOnsite').value = String(c.onsite_time||70);
        document.getElementById('admEditPrice').value = parseFloat(c.price_per_view)||1200;
        document.getElementById('admEditQty').value = c.quantity||150;
        _admEditTaskType = c.task_type || 'keyword_search';
        admCalcPriceReward();
        // Screenshots
        var dp = document.getElementById('admEditSsDPrev');
        var mp = document.getElementById('admEditSsMPrev');
        var imgStyle = 'max-height:100px;max-width:100%;object-fit:contain;border-radius:4px';
        var noImg = '<span style="font-size:11px;color:#9ca3af">Chưa có</span>';
        dp.innerHTML = (c.screenshot_desktop_url && c.screenshot_desktop_url.length > 5 && c.screenshot_desktop_url !== 'attached') ? '<img src="'+c.screenshot_desktop_url+'" style="'+imgStyle+'">' : noImg;
        mp.innerHTML = (c.screenshot_mobile_url && c.screenshot_mobile_url.length > 5) ? '<img src="'+c.screenshot_mobile_url+'" style="'+imgStyle+'">' : noImg;
        document.getElementById('admEditSsD').value = '';
        document.getElementById('admEditSsM').value = '';
        document.getElementById('admEditMsg').innerHTML = '';
        document.getElementById('admEditBtn').disabled = false;
        document.getElementById('admEditBtn').textContent = 'Lưu thay đổi';
        document.getElementById('adminEditCampModal').style.display = 'flex';
    });
}

document.getElementById('admEditCampForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('admEditBtn');
    var msg = document.getElementById('admEditMsg');
    btn.disabled = true; btn.textContent = 'Đang lưu...';
    var fd = new FormData();
    fd.append('action','linkngon_admin_update_campaign');
    fd.append('nonce',ADM_NONCE);
    fd.append('campaign_id', document.getElementById('admEditId').value);
    fd.append('keyword', document.getElementById('admEditKw').value);
    fd.append('target_url', document.getElementById('admEditUrl').value);
    fd.append('title', document.getElementById('admEditTitle').value);
    fd.append('daily_traffic', document.getElementById('admEditDaily').value);
    fd.append('traffic_type', document.getElementById('admEditTT').value);
    fd.append('onsite_time', document.getElementById('admEditOnsite').value);
    fd.append('quantity', document.getElementById('admEditQty').value);
    // Screenshots via separate upload if files selected
    var ssD = document.getElementById('admEditSsD').files[0];
    var ssM = document.getElementById('admEditSsM').files[0];
    if (ssD) fd.append('screenshot_desktop', ssD);
    if (ssM) fd.append('screenshot_mobile', ssM);
    fetch(ADM_AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if (r.success) {
            msg.innerHTML = '<span style="color:#46b450">Đã lưu!</span>';
            setTimeout(function(){ location.reload(); }, 800);
        } else {
            msg.innerHTML = '<span style="color:#dc3232">'+(r.data||'Lỗi')+'</span>';
            btn.disabled = false; btn.textContent = 'Lưu thay đổi';
        }
    });
});

function updateWidgetCodeStatus(campaignId, status) {
    var sel = event.target;
    sel.disabled = true;
    var fd = new FormData();
    fd.append('action', 'linkngon_admin_update_widget_code_status');
    fd.append('nonce', '<?php echo wp_create_nonce("linkngon_admin_nonce"); ?>');
    fd.append('campaign_id', campaignId);
    fd.append('widget_code_status', status);
    fetch('<?php echo admin_url("admin-ajax.php"); ?>', {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(r){
            sel.disabled = false;
            if (r.success) {
                var isAttached = status === 'attached';
                sel.style.background = isAttached ? '#edf7ed' : '#fff8e1';
                sel.style.color = isAttached ? '#46b450' : '#dba617';
                sel.style.borderColor = isAttached ? '#46b450' : '#dba617';
            } else {
                alert(r.data || 'Lỗi');
                sel.value = status === 'attached' ? 'not_attached' : 'attached';
            }
        });
}
</script>

</div>
