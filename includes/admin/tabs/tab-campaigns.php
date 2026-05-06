<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'traffictop_';

// Handle actions
if(isset($_POST['campaign_action']) && wp_verify_nonce($_POST['_wpnonce'],'traffictop_campaign_action')){
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    $action = sanitize_text_field($_POST['campaign_action']);

    // Fetch campaign to get order_id for syncing
    $campaign_row = ($action !== 'create') ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$prefix}keyword_campaigns WHERE id=%d", $campaign_id)) : null;

    if($action === 'approve'){
        if(!$campaign_row){ echo '<div class="notice notice-error"><p>Không tìm thấy chiến dịch.</p></div>'; }
        else {
            $result = traffictop_approve_campaign($campaign_id, get_current_user_id());
            if(is_wp_error($result)){
                echo '<div class="notice notice-error"><p>Lỗi: '.esc_html($result->get_error_message()).'</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>Chiến dịch #'.$campaign_id.' đã được duyệt.</p></div>';
            }
        }
    } elseif($action === 'pause'){
        if(!$campaign_row){ echo '<div class="notice notice-error"><p>Không tìm thấy chiến dịch.</p></div>'; }
        else {
            $now = traffictop_current_time();
            $wpdb->update($prefix.'keyword_campaigns', ['status'=>'paused','updated_at'=>$now], ['id'=>$campaign_id]);
            if($campaign_row->order_id) $wpdb->update($prefix.'customer_orders', ['status'=>'paused','updated_at'=>$now], ['id'=>$campaign_row->order_id]);
            delete_transient('traffictop_eligible_campaigns');
            echo '<div class="notice notice-warning"><p>Chiến dịch #'.$campaign_id.' đã tạm dừng.</p></div>';
        }
    } elseif($action === 'resume'){
        if(!$campaign_row){ echo '<div class="notice notice-error"><p>Không tìm thấy chiến dịch.</p></div>'; }
        else {
            $now = traffictop_current_time();
            $wpdb->update($prefix.'keyword_campaigns', ['status'=>'active','updated_at'=>$now], ['id'=>$campaign_id]);
            if($campaign_row->order_id) $wpdb->update($prefix.'customer_orders', ['status'=>'active','updated_at'=>$now], ['id'=>$campaign_row->order_id]);
            delete_transient('traffictop_eligible_campaigns');
            echo '<div class="notice notice-success"><p>Chiến dịch #'.$campaign_id.' đã tiếp tục.</p></div>';
        }
    } elseif($action === 'reject'){
        $reason = isset($_POST['reject_reason']) ? sanitize_text_field($_POST['reject_reason']) : '';
        if(!$campaign_row){ echo '<div class="notice notice-error"><p>Không tìm thấy chiến dịch.</p></div>'; }
        else {
            $now = traffictop_current_time();
            $wpdb->update($prefix.'keyword_campaigns', ['status'=>'rejected','reject_reason'=>$reason,'updated_at'=>$now], ['id'=>$campaign_id]);
            if($campaign_row->order_id) $wpdb->update($prefix.'customer_orders', ['status'=>'rejected','reject_reason'=>$reason,'updated_at'=>$now], ['id'=>$campaign_row->order_id]);
            echo '<div class="notice notice-error"><p>Chiến dịch #'.$campaign_id.' đã bị từ chối.</p></div>';
        }
    } elseif($action === 'delete'){
        if(!$campaign_row){ echo '<div class="notice notice-error"><p>Không tìm thấy chiến dịch.</p></div>'; }
        else {
            // Soft delete - preserve for financial audit trail
            $now = traffictop_current_time();
            $wpdb->update($prefix.'keyword_campaigns', ['status'=>'deleted','updated_at'=>$now], ['id'=>$campaign_id]);
            if($campaign_row->order_id) $wpdb->update($prefix.'customer_orders', ['status'=>'deleted','updated_at'=>$now], ['id'=>$campaign_row->order_id]);
            delete_transient('traffictop_eligible_campaigns');
            echo '<div class="notice notice-warning"><p>Chiến dịch #'.$campaign_id.' đã bị xóa.</p></div>';
        }
    } elseif($action === 'toggle_mobile'){
        $current = intval($campaign_row->mobile_only ?? 0);
        $wpdb->update($prefix.'keyword_campaigns', ['mobile_only' => $current ? 0 : 1, 'updated_at' => traffictop_current_time()], ['id' => $campaign_id]);
        delete_transient('traffictop_eligible_campaigns');
        echo '<div class="notice notice-success"><p>Chiến dịch #'.$campaign_id.': '.($current ? 'Đã tắt' : 'Đã bật').' chế độ chỉ điện thoại.</p></div>';
    } elseif($action === 'create'){
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $target_url = esc_url_raw($_POST['target_url'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $task_type = sanitize_text_field($_POST['task_type'] ?? 'keyword_search');
        $traffic_type = sanitize_text_field($_POST['traffic_type'] ?? '1step');
        $onsite_time = intval($_POST['onsite_time'] ?? 70);
        $daily_traffic = max(10, intval($_POST['daily_traffic'] ?? 100));
        $quantity = max(1, intval($_POST['quantity'] ?? 150));
        $price_per_view = floatval($_POST['price_per_view'] ?? 1200);
        $user_reward = floatval($_POST['user_reward'] ?? 800);
        $status = sanitize_text_field($_POST['camp_status'] ?? 'active');

        if(!$customer_id || !$target_url){
            echo '<div class="notice notice-error"><p>Thiếu thông tin bắt buộc.</p></div>';
        } elseif($task_type === 'keyword_search' && empty($keyword)){
            echo '<div class="notice notice-error"><p>Vui lòng nhập từ khóa.</p></div>';
        } elseif($traffic_type === 'nocode' && empty($_POST['fixed_code'])){
            echo '<div class="notice notice-error"><p>Vui lòng nhập mã cố định.</p></div>';
        } elseif($traffic_type === 'nocode' && empty($_POST['nocode_screenshot_url'])){
            echo '<div class="notice notice-error"><p>Vui lòng tải ảnh mô tả vị trí mã.</p></div>';
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
                'created_at' => traffictop_current_time(),
                'updated_at' => traffictop_current_time(),
            ]);
            $order_id = $wpdb->insert_id;

            // Screenshot URLs (already uploaded to ImgBB via AJAX)
            $screenshot_desktop_url = esc_url_raw($_POST['screenshot_desktop_url'] ?? '');
            $screenshot_mobile_url = esc_url_raw($_POST['screenshot_mobile_url'] ?? '');
            $nocode_screenshot_url = esc_url_raw($_POST['nocode_screenshot_url'] ?? '');

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
                'fixed_code' => ($traffic_type === 'nocode') ? sanitize_text_field($_POST['fixed_code'] ?? '') : null,
                'screenshot_desktop_url' => $screenshot_desktop_url,
                'screenshot_mobile_url' => $screenshot_mobile_url,
                'nocode_screenshot_url' => $nocode_screenshot_url,
                'status' => $status,
                'created_at' => traffictop_current_time(),
                'updated_at' => traffictop_current_time(),
            ]);
            echo '<div class="notice notice-success"><p>Đã tạo chiến dịch "'.$title.'" cho '.esc_html($customer?$customer->user_login:'#'.$customer_id).' (trạng thái: '.$status.')</p></div>';
        }
    }
}

// Filters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$search_filter = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$where = "WHERE 1=1";
$args = array();
if($status_filter) {
    $where .= " AND kc.status = %s";
    $args[] = $status_filter;
} else {
    // Hide deleted campaigns by default
    $where .= " AND kc.status != 'deleted'";
}
if($search_filter) {
    $like = '%' . $wpdb->esc_like($search_filter) . '%';
    $where .= " AND (kc.keyword LIKE %s OR kc.target_url LIKE %s OR co.customer_username LIKE %s OR kc.title LIKE %s)";
    $args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like;
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
$today_camp_date = date('Y-m-d', strtotime(traffictop_current_time()));
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
    'rejected' => 'Từ chối',
    'deleted' => 'Đã xóa',
];
?>
<div class="wrap">
<h1>Chiến dịch</h1>

<?php
$camp_active = isset($counts['active']) ? (int)$counts['active']->cnt : 0;
$camp_pending = isset($counts['pending']) ? (int)$counts['pending']->cnt : 0;
$today_camp = date('Y-m-d', strtotime(traffictop_current_time()));
$camp_today_completed = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE step='verified' AND DATE(created_at)=%s", $today_camp));
$camp_today_total = (int) $wpdb->get_var("SELECT COALESCE(SUM(daily_traffic), 0) FROM {$prefix}keyword_campaigns WHERE status = 'active'");
$month_start_camp = date('Y-m-01', strtotime(traffictop_current_time()));
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
input[type=search]{padding:0 10px !important}
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
        <?php wp_nonce_field('traffictop_campaign_action'); ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
            <div><label <?php echo $lbl; ?>>Khách hàng <span style="color:red">*</span></label><select name="customer_id" required <?php echo $inp; ?>><option value="">-- Chọn --</option><?php foreach($all_customers as $c) echo '<option value="'.$c->ID.'">'.esc_html($c->user_login).'</option>'; ?></select></div>
            <div><label <?php echo $lbl; ?>>Loại dịch vụ</label><select name="task_type" id="adm_task_type" <?php echo $inp; ?> onchange="admUpdatePrice()"><option value="keyword_search">Traffic từ khóa</option><option value="traffic_direct">Traffic Direct</option></select></div>
            <div id="admCreateKwWrap"><label <?php echo $lbl; ?>>Từ khóa <span style="color:red">*</span></label><input name="keyword" id="adm_keyword" <?php echo $inp; ?> placeholder="Từ khóa SEO"></div>
            <div><label <?php echo $lbl; ?>>URL đích <span style="color:red">*</span></label><input name="target_url" type="url" required <?php echo $inp; ?> placeholder="https://..."></div>
            <div><label <?php echo $lbl; ?>>Loại traffic</label><select name="traffic_type" id="adm_traffic_type" <?php echo $inp; ?> onchange="admUpdatePrice()"><option value="1step">1 bước</option><option value="2step">2 bước</option><option value="nocode">Mã cố định</option></select></div>
            <?php
$oe = array(70=>(int)traffictop_get_option('onsite_extra_70',0),80=>(int)traffictop_get_option('onsite_extra_80',100),90=>(int)traffictop_get_option('onsite_extra_90',200),100=>(int)traffictop_get_option('onsite_extra_100',300),120=>(int)traffictop_get_option('onsite_extra_120',400),150=>(int)traffictop_get_option('onsite_extra_150',500));
?>
            <div><label <?php echo $lbl; ?>>Onsite (giây)</label><select name="onsite_time" id="adm_onsite" <?php echo $inp; ?> onchange="admUpdatePrice()"><?php foreach($oe as $s=>$e): ?><option value="<?php echo $s; ?>"<?php if($s===70) echo ' selected'; ?>><?php echo $s; ?>s<?php if($e>0) echo ' (+'.number_format($e).'đ)'; ?></option><?php endforeach; ?></select></div>
            <div style="min-width:0"><label <?php echo $lbl; ?>>Ảnh kết quả Desktop</label><div id="admCreateSsDPrev" style="height:80px;background:#f7f5f0;border-radius:6px;display:flex;align-items:center;justify-content:center;margin-bottom:6px;overflow:hidden"><span style="font-size:11px;color:#9ca3af">Chưa có</span></div><label id="admCreateSsDBtn" style="display:flex;align-items:center;justify-content:center;gap:6px;padding:7px;background:#2271b1;color:#fff;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Tải ảnh<input type="file" accept="image/*" style="display:none" onchange="admImgbbUpload(this,'admCreateSsDPrev','screenshot_desktop_url','admCreateSsDBtn')"></label><input type="hidden" name="screenshot_desktop_url" id="admCreateSsDUrl"></div>
            <div style="min-width:0"><label <?php echo $lbl; ?>>Ảnh kết quả Mobile</label><div id="admCreateSsMPrev" style="height:80px;background:#f7f5f0;border-radius:6px;display:flex;align-items:center;justify-content:center;margin-bottom:6px;overflow:hidden"><span style="font-size:11px;color:#9ca3af">Chưa có</span></div><label id="admCreateSsMBtn" style="display:flex;align-items:center;justify-content:center;gap:6px;padding:7px;background:#2271b1;color:#fff;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Tải ảnh<input type="file" accept="image/*" style="display:none" onchange="admImgbbUpload(this,'admCreateSsMPrev','screenshot_mobile_url','admCreateSsMBtn')"></label><input type="hidden" name="screenshot_mobile_url" id="admCreateSsMUrl"></div>
        </div>
        <div id="admCreateNocodeSection" style="display:none;margin-bottom:12px;padding:12px;background:#f0f6ff;border:1px solid #c3d9f0;border-radius:8px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div><label <?php echo $lbl; ?>>Mã cố định <span style="color:red">*</span></label><input name="fixed_code" id="adm_fixed_code" <?php echo $inp; ?> placeholder="VD: ABC123"></div>
                <div style="min-width:0"><label <?php echo $lbl; ?>>Ảnh mô tả vị trí mã <span style="color:red">*</span></label><div id="admCreateSsNocodePrev" style="height:80px;background:#f7f5f0;border-radius:6px;display:flex;align-items:center;justify-content:center;margin-bottom:6px;overflow:hidden"><span style="font-size:11px;color:#9ca3af">Chưa có</span></div><label id="admCreateSsNocodeBtn" style="display:flex;align-items:center;justify-content:center;gap:6px;padding:7px;background:#2271b1;color:#fff;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Tải ảnh<input type="file" accept="image/*" style="display:none" onchange="admImgbbUpload(this,'admCreateSsNocodePrev','nocode_screenshot_url','admCreateSsNocodeBtn')"></label><input type="hidden" name="nocode_screenshot_url" id="admCreateSsNocodeUrl"></div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
            <div><label <?php echo $lbl; ?>>Traffic/ngày</label><input name="daily_traffic" id="adm_daily" type="number" value="100" min="1" <?php echo $inp; ?> onchange="admUpdateEstimate()"></div>
            <div><label <?php echo $lbl; ?>>Số ngày</label><input name="days" id="adm_days" type="number" value="30" min="1" <?php echo $inp; ?> onchange="admUpdateEstimate()"></div>
            <div><label <?php echo $lbl; ?>>Tổng số lượt</label><input name="quantity" id="adm_qty" type="number" value="3000" min="1" <?php echo $inp; ?> onchange="admUpdateEstimate()"></div>
            <div><label <?php echo $lbl; ?>>Giá/lượt (KH trả)</label><input name="price_per_view" id="adm_price" type="number" value="<?php echo traffictop_get_option('keyword_price_1step',1200); ?>" readonly style="width:100%;height:36px;border:1px solid #ddd;border-radius:4px;padding:0 8px;font-size:13px;font-weight:700;color:#0073aa;background:#f7f5f0"></div>
            <input type="hidden" name="user_reward" id="adm_reward" value="<?php echo traffictop_get_option('keyword_user_1step',800); ?>">
            <input type="hidden" name="camp_status" value="pending">
        </div>
        <div id="admEstimate" style="margin-bottom:12px;padding:10px 14px;background:#f0f6ff;border:1px solid #c3d9f0;border-radius:6px;font-size:13px;color:#1d2327"><strong>Ước tính chi phí:</strong> <span id="admEstimateVal">3,600,000đ</span> <span style="color:#787c82;font-size:11px">(3000 lượt × 1,200đ)</span></div>
        <button type="submit" name="campaign_action" value="create" class="button button-primary" onclick="return confirm('Tạo chiến dịch?')">Tạo chiến dịch</button>
    </form>
    <script>
    var ADM_PRICES={
        keyword_search:{'1step':<?php echo (int)traffictop_get_option('keyword_price_1step',1200); ?>,'2step':<?php echo (int)traffictop_get_option('keyword_price_2step',1500); ?>,'nocode':<?php echo (int)traffictop_get_option('keyword_price_nocode',1200); ?>},
        traffic_direct:{'1step':<?php echo (int)traffictop_get_option('direct_price_1step',1200); ?>,'2step':<?php echo (int)traffictop_get_option('direct_price_2step',1200); ?>,'nocode':<?php echo (int)traffictop_get_option('direct_price_nocode',1200); ?>}
    };
    var ADM_REWARDS={
        keyword_search:{'1step':<?php echo (int)traffictop_get_option('keyword_user_1step',800); ?>,'2step':<?php echo (int)traffictop_get_option('keyword_user_2step',1000); ?>,'nocode':<?php echo (int)traffictop_get_option('keyword_user_nocode',800); ?>},
        traffic_direct:{'1step':<?php echo (int)traffictop_get_option('direct_user_1step',500); ?>,'2step':<?php echo (int)traffictop_get_option('direct_user_2step',700); ?>,'nocode':<?php echo (int)traffictop_get_option('direct_user_nocode',800); ?>}
    };
    var ADM_ONSITE_EXTRA={70:<?php echo (int)traffictop_get_option('onsite_extra_70',0); ?>,80:<?php echo (int)traffictop_get_option('onsite_extra_80',100); ?>,90:<?php echo (int)traffictop_get_option('onsite_extra_90',200); ?>,100:<?php echo (int)traffictop_get_option('onsite_extra_100',300); ?>,120:<?php echo (int)traffictop_get_option('onsite_extra_120',400); ?>,150:<?php echo (int)traffictop_get_option('onsite_extra_150',500); ?>};
    var ADM_USER_ONSITE_EXTRA={70:<?php echo (int)traffictop_get_option('user_onsite_extra_70',0); ?>,80:<?php echo (int)traffictop_get_option('user_onsite_extra_80',0); ?>,90:<?php echo (int)traffictop_get_option('user_onsite_extra_90',0); ?>,100:<?php echo (int)traffictop_get_option('user_onsite_extra_100',0); ?>,120:<?php echo (int)traffictop_get_option('user_onsite_extra_120',0); ?>,150:<?php echo (int)traffictop_get_option('user_onsite_extra_150',0); ?>};
    function admUpdatePrice(){
        var t=document.getElementById('adm_task_type').value;
        var tt=document.getElementById('adm_traffic_type').value;
        var os=parseInt(document.getElementById('adm_onsite').value);
        var base=(ADM_PRICES[t]||ADM_PRICES.keyword_search)[tt]||1200;
        var extra=ADM_ONSITE_EXTRA[os]||0;
        document.getElementById('adm_price').value=base+extra;
        var reward=(ADM_REWARDS[t]||ADM_REWARDS.keyword_search)[tt]||800;
        document.getElementById('adm_reward').value=reward+(ADM_USER_ONSITE_EXTRA[os]||0);
        document.getElementById('admCreateNocodeSection').style.display=tt==='nocode'?'block':'none';
        var kwWrap=document.getElementById('admCreateKwWrap');
        var kwInput=document.getElementById('adm_keyword');
        if(t==='keyword_search'){kwWrap.style.display='';if(kwInput)kwInput.required=true;}
        else{kwWrap.style.display='none';if(kwInput){kwInput.required=false;kwInput.value='';}}
        admUpdateEstimate();
    }
    function admUpdateEstimate(){
        var daily=parseInt(document.getElementById('adm_daily').value)||100;
        var days=parseInt(document.getElementById('adm_days').value)||30;
        var qty=daily*days;
        document.getElementById('adm_qty').value=qty;
        var price=parseInt(document.getElementById('adm_price').value)||1200;
        var total=qty*price;
        document.getElementById('admEstimateVal').textContent=total.toLocaleString('vi-VN')+'đ';
        document.getElementById('admEstimate').querySelector('span:last-child').textContent='('+qty.toLocaleString('vi-VN')+' lượt × '+price.toLocaleString('vi-VN')+'đ)';
    }
    function admImgbbUpload(input,prevId,hiddenName,btnId){
        var f=input.files[0];if(!f)return;
        var prev=document.getElementById(prevId);
        var btn=document.getElementById(btnId);
        var hiddenInput=document.querySelector('input[name="'+hiddenName+'"]');
        prev.innerHTML='<span style="font-size:11px;color:#9ca3af">Đang tải lên...</span>';
        if(btn){btn.style.opacity='0.6';btn.style.pointerEvents='none';}
        var fd=new FormData();
        fd.append('action','traffictop_upload_screenshot');
        fd.append('nonce','<?php echo wp_create_nonce("traffictop_admin_nonce"); ?>');
        fd.append('file',f);
        fetch('<?php echo admin_url("admin-ajax.php"); ?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
            if(btn){btn.style.opacity='';btn.style.pointerEvents='';}
            if(r.success&&r.data.url){
                prev.innerHTML='<img src="'+r.data.url+'" style="max-height:80px;max-width:100%;object-fit:contain;border-radius:4px">';
                if(hiddenInput)hiddenInput.value=r.data.url;
            }else{
                prev.innerHTML='<span style="font-size:11px;color:#dc3232">'+(r.data||'Upload lỗi')+'</span>';
            }
        }).catch(function(){
            if(btn){btn.style.opacity='';btn.style.pointerEvents='';}
            prev.innerHTML='<span style="font-size:11px;color:#dc3232">Lỗi kết nối</span>';
        });
    }
    admUpdatePrice();
    </script>
</div>
</details>

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;margin-bottom:6px">
<ul class="subsubsub" style="margin:0;float:none">
    <li><a href="?page=traffictop-campaigns<?php echo $search_filter?'&s='.urlencode($search_filter):''; ?>" <?php echo !$status_filter?'class="current"':''; ?>>Tất cả <span class="count">(<?php echo intval($total); ?>)</span></a> |</li>
    <?php foreach(['pending','active','paused','rejected','deleted'] as $s): ?>
    <li><a href="?page=traffictop-campaigns&status=<?php echo $s; ?><?php echo $search_filter?'&s='.urlencode($search_filter):''; ?>" <?php echo $status_filter===$s?'class="current"':''; ?>><?php echo $status_labels[$s]; ?> <span class="count">(<?php echo isset($counts[$s]) ? $counts[$s]->cnt : 0; ?>)</span></a><?php echo $s!=='deleted'?' |':''; ?></li>
    <?php endforeach; ?>
</ul>
<form method="get">
    <input type="hidden" name="page" value="traffictop-campaigns">
    <?php if($status_filter): ?><input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>"><?php endif; ?>
    <p class="search-box">
        <input type="search" name="s" value="<?php echo esc_attr($search_filter); ?>" placeholder="Tìm từ khóa, URL, khách hàng...">
        <input type="submit" class="button" value="Tìm kiếm">
    </p>
</form>
</div>
<br class="clear">

<style>
.camp-tbl th{white-space:nowrap;font-size:13px} .camp-tbl td{font-size:13px}
.camp-tbl .col-kw{max-width:220px;word-break:break-all}
.camp-tbl .col-kw a{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;word-break:break-all;line-height:1.3}
@media(max-width:600px){.camp-tbl th,.camp-tbl td{padding:5px 6px}
.camp-tbl .col-id{width:36px;text-align:center}
.camp-tbl .col-kw{max-width:160px}
.camp-tbl .col-num{white-space:nowrap;text-align:right}
.camp-tbl .col-status span{white-space:nowrap}
}
</style>
<div style="overflow-x:auto"><table class="widefat striped camp-tbl">
<thead>
<tr>
    <th class="col-id">ID</th>
    <th>Khách hàng</th>
    <th>Dịch vụ</th>
    <th class="col-kw">Từ khóa / URL</th>
    <th class="col-num">Traffic/ngày</th>
    <th class="col-num">Đã chạy</th>
    <th style="min-width:120px">Loại/Onsite</th>
    <th>Trạng thái mã</th>
    <th class="col-status" style="min-width:80px">Trạng thái</th>
    <th>Thao tác</th>
    <th>Thời gian</th>
</tr>
</thead>
<tbody>
<?php if(empty($rows)): ?>
<tr><td colspan="11">Không có dữ liệu.</td></tr>
<?php else: foreach($rows as $row):
    $status_colors = ['active'=>'#46b450','paused'=>'#ffb900','pending'=>'#00a0d2','rejected'=>'#dc3232','deleted'=>'#82878c'];
    $status_bg = ['active'=>'#edf7ed','paused'=>'#fff8e1','pending'=>'#fff3cd','rejected'=>'#fdecea','deleted'=>'#f3f4f6'];
    $color = $status_colors[$row->status] ?? '#82878c';
    $bg = $status_bg[$row->status] ?? '#f5f5f5';
    $traffic_labels = ['1step'=>'1 bước','2step'=>'2 bước','nocode'=>'Mã cố định'];
    $traffic_colors = ['1step'=>'#2271b1','2step'=>'#dba617','nocode'=>'#8c5e2a'];
    $traffic_bg = ['1step'=>'#e7f3ff','2step'=>'#fff8e1','nocode'=>'#fef3e2'];
    $domain = parse_url($row->target_url ?? '', PHP_URL_HOST);
    $completed = intval($row->completed);
    $spent = $completed * floatval($row->price_per_view);
    $tt = $row->traffic_type ?? '1step';
?>
<tr>
    <td><strong style="color:#2271b1">#<?php echo $row->id; ?></strong></td>
    <td><strong><?php echo esc_html($row->customer_username ?? '—'); ?></strong></td>
    <td><?php
        $svc = $row->task_type ?? $row->campaign_type ?? 'keyword_search';
        if ($svc === 'keyword_search') { echo '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#DEF7EC;color:#046C4E">Keyword</span>'; }
        else { echo '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#EDE9FE;color:#6D28D9">Direct</span>'; }
    ?></td>
    <td class="col-kw">
        <?php if(!empty($row->keyword)): ?>
        <div style="font-weight:600;font-size:13px;word-break:break-all"><?php echo esc_html($row->keyword); ?></div>
        <?php endif; ?>
        <a href="<?php echo esc_url($row->target_url); ?>" target="_blank" title="<?php echo esc_attr($domain); ?>" style="font-size:11px;color:#787c82"><?php echo esc_html($domain); ?></a>
    </td>
    <td>
        <div style="font-weight:600"><span style="color:#dba617"><?php echo intval($row->today_views ?? 0); ?></span>/<?php echo intval($row->daily_traffic ?? 10); ?></div>
    </td>
    <td>
        <div style="font-weight:600"><?php echo number_format($completed); ?></div>
        <small style="color:#787c82"><?php echo traffictop_format_money($spent); ?></small>
    </td>
    <td>
        <?php
            $onsite = (int)($row->onsite_time ?? 70);
            $tt_full = ($traffic_labels[$tt] ?? $tt) . ($onsite > 0 ? " · {$onsite}s" : '');
        ?>
        <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;white-space:nowrap;background:<?php echo $traffic_bg[$tt] ?? '#f5f5f5'; ?>;color:<?php echo $traffic_colors[$tt] ?? '#787c82'; ?>"><?php echo $tt_full; ?></span>
        <?php if($tt === 'nocode' && !empty($row->fixed_code)): ?>
        <div style="font-size:10px;color:#d63638;font-weight:600;margin-top:2px"><?php echo esc_html($row->fixed_code); ?></div>
        <?php endif; ?>
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
                <?php wp_nonce_field('traffictop_campaign_action'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo $row->id; ?>">
                <?php $is_mobile = intval($row->mobile_only ?? 0); ?>
                <button type="submit" name="campaign_action" value="toggle_mobile" title="<?php echo $is_mobile ? 'Tắt chế độ mobile' : 'Chỉ hiện trên điện thoại'; ?>" style="<?php echo $bs; ?>;background:<?php echo $is_mobile ? '#ef4444' : '#e5e7eb'; ?>;color:<?php echo $is_mobile ? '#fff' : '#6b7280'; ?>"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18.01" stroke-width="3" stroke-linecap="round"/></svg></button>
                <?php if($row->status === 'pending'): ?>
                <button type="submit" name="campaign_action" value="approve" title="Duyệt" style="<?php echo $bs; ?>;background:#46b450;color:#fff"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></button>
                <button type="submit" name="campaign_action" value="reject" title="Từ chối" style="<?php echo $bs; ?>;background:#dba617;color:#fff" onclick="return confirm('Từ chối #<?php echo $row->id; ?>?')"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                <button type="submit" name="campaign_action" value="delete" title="Xóa" style="<?php echo $bs; ?>;background:#fde8e8;color:#dc3232" onclick="return confirm('Xóa #<?php echo $row->id; ?>?')"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg></button>
                <?php elseif($row->status === 'active'): ?>
                <button type="submit" name="campaign_action" value="pause" title="Tạm dừng" style="<?php echo $bs; ?>;background:#dba617;color:#fff"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg></button>
                <?php elseif($row->status === 'paused'): ?>
                <button type="submit" name="campaign_action" value="resume" title="Tiếp tục" style="<?php echo $bs; ?>;background:#46b450;color:#fff"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg></button>
                <button type="submit" name="campaign_action" value="delete" title="Xóa" style="<?php echo $bs; ?>;background:#fde8e8;color:#dc3232" onclick="return confirm('Xóa #<?php echo $row->id; ?>?')"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg></button>
                <?php elseif($row->status === 'rejected'): ?>
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

<?php if($total_pages > 1):
    $pag_params = array('page' => 'traffictop-campaigns');
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

<!-- Admin Edit Campaign Modal -->
<div id="adminEditCampModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.45);z-index:10000;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;overflow-y:auto">
<div style="background:#fff;border-radius:12px;width:100%;max-width:620px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);margin:auto">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e5e7eb">
        <h3 style="font-size:16px;font-weight:700;color:#1d2327;margin:0">Chỉnh sửa chiến dịch <span id="admEditCampLabel" style="color:#2271b1"></span></h3>
        <button onclick="document.getElementById('adminEditCampModal').style.display='none'" style="width:28px;height:28px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;font-size:16px;color:#9ca3af;display:flex;align-items:center;justify-content:center">&times;</button>
    </div>
    <form id="admEditCampForm" style="padding:20px" enctype="multipart/form-data">
        <input type="hidden" id="admEditId">
        <div id="admEditKwRow" style="display:grid;grid-template-columns:1fr 100px;gap:12px;margin-bottom:12px">
            <div id="admEditKwCell"><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Từ khóa <span id="admEditKwReq" style="color:red">*</span></label><input id="admEditKw" style="width:100%;height:36px;border:1px solid #ddd;border-radius:6px;padding:0 10px;font-size:13px"></div>
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Traffic/ngày</label><input id="admEditDaily" type="number" min="1" max="5000" style="width:100%;height:36px;border:1px solid #ddd;border-radius:6px;padding:0 10px;font-size:13px"></div>
        </div>
        <div style="margin-bottom:12px">
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">URL đích <span style="color:red">*</span></label><input id="admEditUrl" type="url" required style="width:100%;height:36px;border:1px solid #ddd;border-radius:6px;padding:0 10px;font-size:13px"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px">
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Loại traffic</label><select id="admEditTT" style="width:100%;height:36px;border:1px solid #ddd;border-radius:6px;padding:0 8px;font-size:13px"><option value="1step">1 bước</option><option value="2step">2 bước</option><option value="nocode">Mã cố định</option></select></div>
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Giá/view (KH trả)</label><div id="admEditPrice" style="height:36px;border:1px solid #ddd;border-radius:6px;padding:0 10px;font-size:13px;font-weight:700;color:#0073aa;display:flex;align-items:center;background:#f7f5f0"></div></div>
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Onsite</label><select id="admEditOnsite" style="width:100%;height:36px;border:1px solid #ddd;border-radius:6px;padding:0 8px;font-size:13px"><?php foreach($oe as $s=>$e): ?><option value="<?php echo $s; ?>"><?php echo $s; ?>s<?php if($e>0) echo ' (+'.number_format($e).'đ)'; ?></option><?php endforeach; ?></select></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">User nhận/view</label><div id="admEditReward" style="height:36px;border:1px solid #ddd;border-radius:6px;padding:0 10px;font-size:13px;font-weight:600;color:#46b450;display:flex;align-items:center;background:#f7f5f0"></div></div>
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Tổng số lượt</label><input id="admEditQty" type="number" min="1" style="width:100%;height:36px;border:1px solid #ddd;border-radius:6px;padding:0 10px;font-size:13px"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
            <div style="min-width:0"><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Ảnh Desktop</label><div id="admEditSsDPrev" style="height:100px;background:#f7f5f0;border-radius:6px;display:flex;align-items:center;justify-content:center;margin-bottom:6px;overflow:hidden"><span style="font-size:11px;color:#9ca3af">Chưa có</span></div><label id="admEditSsDBtn" style="display:flex;align-items:center;justify-content:center;gap:6px;padding:8px;background:#2271b1;color:#fff;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Tải ảnh<input type="file" id="admEditSsD" accept="image/*" style="display:none" onchange="admEditImgbbUpload(this,'admEditSsDPrev','admEditSsDUrl','admEditSsDBtn')"></label><input type="hidden" id="admEditSsDUrl"></div>
            <div style="min-width:0"><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Ảnh Mobile</label><div id="admEditSsMPrev" style="height:100px;background:#f7f5f0;border-radius:6px;display:flex;align-items:center;justify-content:center;margin-bottom:6px;overflow:hidden"><span style="font-size:11px;color:#9ca3af">Chưa có</span></div><label id="admEditSsMBtn" style="display:flex;align-items:center;justify-content:center;gap:6px;padding:8px;background:#2271b1;color:#fff;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Tải ảnh<input type="file" id="admEditSsM" accept="image/*" style="display:none" onchange="admEditImgbbUpload(this,'admEditSsMPrev','admEditSsMUrl','admEditSsMBtn')"></label><input type="hidden" id="admEditSsMUrl"></div>
        </div>
        <div id="admEditNocodeSection" style="display:none;margin-bottom:12px;padding:12px;background:#f0f6ff;border:1px solid #c3d9f0;border-radius:8px">
            <div style="margin-bottom:10px"><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Mã cố định (KH đặt)</label><input id="admEditFixedCode" style="width:100%;height:36px;border:1px solid #ddd;border-radius:6px;padding:0 10px;font-size:14px;font-weight:700;color:#d63638;letter-spacing:1px"></div>
            <div><label style="display:block;font-size:11px;font-weight:600;color:#50575e;margin-bottom:3px">Ảnh mô tả vị trí mã</label><div id="admEditNocodeSsPrev" style="max-height:200px;background:#f7f5f0;border-radius:6px;display:flex;align-items:center;justify-content:center;margin-bottom:6px;overflow:hidden"><span style="font-size:11px;color:#9ca3af">Chưa có</span></div><label id="admEditSsNocodeBtn" style="display:flex;align-items:center;justify-content:center;gap:6px;padding:8px;background:#2271b1;color:#fff;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>Tải ảnh<input type="file" id="admEditSsNocode" accept="image/*" style="display:none" onchange="admEditImgbbUpload(this,'admEditNocodeSsPrev','admEditSsNocodeUrl','admEditSsNocodeBtn')"></label><input type="hidden" id="admEditSsNocodeUrl"></div>
        </div>
        <div id="admEditMsg" style="min-height:18px;margin-bottom:8px;font-size:13px;text-align:center"></div>
        <button type="submit" id="admEditBtn" class="button button-primary" style="width:100%;height:38px;font-size:14px">Lưu thay đổi</button>
    </form>
</div>
</div>

<script>
var ADM_AJAX = '<?php echo admin_url("admin-ajax.php"); ?>';
var ADM_NONCE = '<?php echo wp_create_nonce("traffictop_admin_nonce"); ?>';
var ADM_PRICE_SETTINGS = {
    keyword_search: {'1step':<?php echo (int)traffictop_get_option('keyword_price_1step',1200); ?>,'2step':<?php echo (int)traffictop_get_option('keyword_price_2step',1500); ?>,'nocode':<?php echo (int)traffictop_get_option('keyword_price_nocode',1200); ?>},
    traffic_direct: {'1step':<?php echo (int)traffictop_get_option('direct_price_1step',1200); ?>,'2step':<?php echo (int)traffictop_get_option('direct_price_2step',1200); ?>,'nocode':<?php echo (int)traffictop_get_option('direct_price_nocode',1200); ?>}
};
var ADM_REWARD_SETTINGS = {
    keyword_search: {'1step':<?php echo (int)traffictop_get_option('keyword_user_1step',800); ?>,'2step':<?php echo (int)traffictop_get_option('keyword_user_2step',1000); ?>,'nocode':<?php echo (int)traffictop_get_option('keyword_user_nocode',800); ?>},
    traffic_direct: {'1step':<?php echo (int)traffictop_get_option('direct_user_1step',500); ?>,'2step':<?php echo (int)traffictop_get_option('direct_user_2step',700); ?>,'nocode':<?php echo (int)traffictop_get_option('direct_user_nocode',800); ?>}
};
var ADM_ONSITE_EXTRA = {70:<?php echo (int)traffictop_get_option('onsite_extra_70',0); ?>,80:<?php echo (int)traffictop_get_option('onsite_extra_80',100); ?>,90:<?php echo (int)traffictop_get_option('onsite_extra_90',200); ?>,100:<?php echo (int)traffictop_get_option('onsite_extra_100',300); ?>,120:<?php echo (int)traffictop_get_option('onsite_extra_120',400); ?>,150:<?php echo (int)traffictop_get_option('onsite_extra_150',500); ?>};
var ADM_USER_ONSITE_EXTRA2 = {70:<?php echo (int)traffictop_get_option('user_onsite_extra_70',0); ?>,80:<?php echo (int)traffictop_get_option('user_onsite_extra_80',0); ?>,90:<?php echo (int)traffictop_get_option('user_onsite_extra_90',0); ?>,100:<?php echo (int)traffictop_get_option('user_onsite_extra_100',0); ?>,120:<?php echo (int)traffictop_get_option('user_onsite_extra_120',0); ?>,150:<?php echo (int)traffictop_get_option('user_onsite_extra_150',0); ?>};
var _admEditTaskType = 'keyword_search';

function admCalcPriceReward() {
    var tt = document.getElementById('admEditTT').value;
    var os = parseInt(document.getElementById('admEditOnsite').value);
    var prices = ADM_PRICE_SETTINGS[_admEditTaskType] || ADM_PRICE_SETTINGS.keyword_search;
    var rewards = ADM_REWARD_SETTINGS[_admEditTaskType] || ADM_REWARD_SETTINGS.keyword_search;
    var price = (prices[tt] || 1200) + (ADM_ONSITE_EXTRA[os] || 0);
    var reward = (rewards[tt] || 800) + (ADM_USER_ONSITE_EXTRA2[os] || 0);
    document.getElementById('admEditPrice').textContent = price.toLocaleString('vi-VN') + 'đ';
    document.getElementById('admEditReward').textContent = reward.toLocaleString('vi-VN') + 'đ';
}

document.getElementById('admEditTT').addEventListener('change', function(){
    admCalcPriceReward();
    document.getElementById('admEditNocodeSection').style.display = this.value === 'nocode' ? 'block' : 'none';
});
document.getElementById('admEditOnsite').addEventListener('change', admCalcPriceReward);

function admEditImgbbUpload(input, prevId, hiddenId, btnId) {
    var f = input.files[0]; if (!f) return;
    var prev = document.getElementById(prevId);
    var btn = document.getElementById(btnId);
    var hidden = document.getElementById(hiddenId);
    prev.innerHTML = '<span style="font-size:11px;color:#9ca3af">Đang tải lên...</span>';
    if (btn) { btn.style.opacity = '0.6'; btn.style.pointerEvents = 'none'; }
    var fd = new FormData();
    fd.append('action', 'traffictop_upload_screenshot');
    fd.append('nonce', ADM_NONCE);
    fd.append('file', f);
    fetch(ADM_AJAX, {method:'POST', body:fd, credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if (btn) { btn.style.opacity = ''; btn.style.pointerEvents = ''; }
        if (r.success && r.data.url) {
            prev.innerHTML = '<img src="'+r.data.url+'" style="max-height:100px;max-width:100%;object-fit:contain;border-radius:4px">';
            if (hidden) hidden.value = r.data.url;
        } else {
            prev.innerHTML = '<span style="font-size:11px;color:#dc3232">'+(r.data||'Upload lỗi')+'</span>';
        }
    }).catch(function(){
        if (btn) { btn.style.opacity = ''; btn.style.pointerEvents = ''; }
        prev.innerHTML = '<span style="font-size:11px;color:#dc3232">Lỗi kết nối</span>';
    });
}

function openAdminEditCamp(id) {
    var fd = new FormData();
    fd.append('action','traffictop_admin_get_campaign');
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
        document.getElementById('admEditTT').value = c.traffic_type||'1step';
        document.getElementById('admEditOnsite').value = String(c.onsite_time||70);
        document.getElementById('admEditPrice').value = parseFloat(c.price_per_view)||1200;
        document.getElementById('admEditQty').value = c.quantity||150;
        _admEditTaskType = c.task_type || 'keyword_search';
        if (_admEditTaskType === 'traffic_direct') {
            document.getElementById('admEditKwCell').style.display = 'none';
            document.getElementById('admEditKwRow').style.gridTemplateColumns = '1fr';
            document.getElementById('admEditKw').value = '';
        } else {
            document.getElementById('admEditKwCell').style.display = '';
            document.getElementById('admEditKwRow').style.gridTemplateColumns = '1fr 100px';
        }
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
        document.getElementById('admEditSsDUrl').value = '';
        document.getElementById('admEditSsMUrl').value = '';
        document.getElementById('admEditSsNocodeUrl').value = '';
        // Nocode section
        var nocodeSection = document.getElementById('admEditNocodeSection');
        if ((c.traffic_type||'') === 'nocode') {
            nocodeSection.style.display = 'block';
            document.getElementById('admEditFixedCode').value = c.fixed_code || '';
            var nsPrev = document.getElementById('admEditNocodeSsPrev');
            nsPrev.innerHTML = (c.nocode_screenshot_url && c.nocode_screenshot_url.length > 5) ? '<img src="'+c.nocode_screenshot_url+'" style="max-width:100%;max-height:200px;object-fit:contain;border-radius:4px">' : '<span style="font-size:11px;color:#9ca3af">Chưa có</span>';
        } else {
            nocodeSection.style.display = 'none';
        }
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

    var kwVal = (document.getElementById('admEditKw').value || '').trim();
    if (_admEditTaskType === 'keyword_search' && kwVal === '') {
        msg.innerHTML = '<span style="color:#dc3232">Từ khóa không được để trống</span>';
        document.getElementById('admEditKw').focus();
        return;
    }

    btn.disabled = true; btn.textContent = 'Đang lưu...';
    var fd = new FormData();
    fd.append('action','traffictop_admin_update_campaign');
    fd.append('nonce',ADM_NONCE);
    fd.append('campaign_id', document.getElementById('admEditId').value);
    if (_admEditTaskType !== 'traffic_direct') fd.append('keyword', document.getElementById('admEditKw').value);
    fd.append('target_url', document.getElementById('admEditUrl').value);
    fd.append('daily_traffic', document.getElementById('admEditDaily').value);
    fd.append('traffic_type', document.getElementById('admEditTT').value);
    fd.append('onsite_time', document.getElementById('admEditOnsite').value);
    fd.append('quantity', document.getElementById('admEditQty').value);
    var fc = document.getElementById('admEditFixedCode').value;
    if (document.getElementById('admEditTT').value === 'nocode') fd.append('fixed_code', fc);
    // Screenshots via ImgBB URLs (already uploaded)
    var ssDUrl = document.getElementById('admEditSsDUrl').value;
    var ssMUrl = document.getElementById('admEditSsMUrl').value;
    var ssNUrl = document.getElementById('admEditSsNocodeUrl').value;
    if (ssDUrl) fd.append('screenshot_desktop_url', ssDUrl);
    if (ssMUrl) fd.append('screenshot_mobile_url', ssMUrl);
    if (ssNUrl) fd.append('nocode_screenshot_url', ssNUrl);
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
    fd.append('action', 'traffictop_admin_update_widget_code_status');
    fd.append('nonce', '<?php echo wp_create_nonce("traffictop_admin_nonce"); ?>');
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
