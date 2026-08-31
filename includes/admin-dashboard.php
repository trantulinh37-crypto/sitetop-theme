<?php
/**
 * SiteTop.net V2 - Admin AJAX Handlers
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Load tab (lazy load)
add_action('wp_ajax_sitetop_admin_load_tab', 'sitetop_ajax_admin_load_tab');
function sitetop_ajax_admin_load_tab() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $tab = sanitize_text_field($_POST['tab'] ?? '');
    $allowed = array('campaigns','orders','users','withdrawals','visits','customers','settings','links','deposits','announcements','sources');
    if (!in_array($tab, $allowed)) wp_send_json_error('Invalid tab');
    $file = SITETOP_DIR . '/includes/admin/tabs/tab-' . $tab . '.php';
    if (!file_exists($file)) wp_send_json_error('Tab not found');
    ob_start(); include $file; wp_send_json_success(array('html'=>ob_get_clean()));
}

// Admin stats
add_action('wp_ajax_sitetop_admin_stats', 'sitetop_ajax_admin_stats');
function sitetop_ajax_admin_stats() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $today = date('Y-m-d', strtotime(sitetop_current_time()));
    wp_send_json_success(array(
        'total_links'        => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}user_shortlinks"),
        'total_clicks'       => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}shortlink_visits WHERE (step='verified' OR customer_paid=1)"),
        'today_clicks'       => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}shortlink_visits WHERE (step='verified' OR customer_paid=1) AND DATE(created_at)=%s", $today)),
        'active_campaigns'   => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}keyword_campaigns WHERE status='active'"),
        'total_paid'         => (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE type='shortlink_reward'"),
        'today_paid'         => (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE type='shortlink_reward' AND DATE(created_at)=%s", $today)),
        'pending_withdrawals'=> (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}withdrawals WHERE status='pending'"),
        'total_publishers'   => (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$p}user_shortlinks WHERE user_id > 0"),
        'total_customers'    => (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$p}customer_balance"),
    ));
}

// Admin chart data (monthly daily breakdown)
add_action('wp_ajax_sitetop_admin_chart_data', 'sitetop_ajax_admin_chart_data');
function sitetop_ajax_admin_chart_data() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;

    $month = sanitize_text_field($_POST['month'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m', strtotime(sitetop_current_time()));
    }
    $year = (int) substr($month, 0, 4);
    $mon  = (int) substr($month, 5, 2);
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $mon, $year);

    // Range-based WHERE (uses created_at index, no DATE() wrapper)
    $range_start = "$month-01 00:00:00";
    $next_mon = $mon < 12 ? $year . '-' . sprintf('%02d', $mon + 1) : ($year + 1) . '-01';
    $range_end = "$next_mon-01 00:00:00";

    // Single query for daily chart + monthly totals (JOIN for price_per_view)
    $daily = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(v.created_at) as d,
                COUNT(*) as total_visits,
                SUM(v.step='verified' OR v.customer_paid=1) as verified,
                COALESCE(SUM(CASE WHEN v.customer_paid=1 THEN COALESCE(kc.price_per_view, v.reward_amount) ELSE 0 END),0) as customer_paid_amount,
                COALESCE(SUM(CASE WHEN v.reward_paid=1 THEN v.reward_amount ELSE 0 END),0) as user_earned
         FROM {$p}shortlink_visits v
         LEFT JOIN {$p}keyword_campaigns kc ON kc.id = v.campaign_id
         WHERE v.created_at >= %s AND v.created_at < %s
         GROUP BY DATE(v.created_at)
         ORDER BY d ASC",
        $range_start, $range_end
    ));

    // Build daily data + accumulate monthly totals from same result
    $data = array();
    $map = array();
    $sum_visits = 0; $sum_verified = 0; $sum_user_earned = 0;
    foreach ($daily as $row) {
        $map[$row->d] = $row;
        $sum_visits += (int)$row->total_visits;
        $sum_verified += (int)$row->verified;
        $sum_user_earned += (float)$row->user_earned;
    }
    for ($i = 1; $i <= $days_in_month; $i++) {
        $d = sprintf('%s-%02d', $month, $i);
        $row = $map[$d] ?? null;
        $data[] = array(
            'date'          => $d,
            'total_visits'  => $row ? (int)$row->total_visits : 0,
            'verified'      => $row ? (int)$row->verified : 0,
            'customer_paid' => $row ? (float)$row->customer_paid_amount : 0,
            'user_earned'   => $row ? (float)$row->user_earned : 0,
        );
    }

    // 3 lightweight queries for summary (range-based, uses indexes)
    $customer_paid_total = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(ABS(SUM(amount)),0) FROM {$p}customer_transactions
         WHERE type='campaign_view' AND amount < 0 AND created_at >= %s AND created_at < %s",
        $range_start, $range_end
    ));

    $deposits_total = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount + bonus_amount),0) FROM {$p}customer_deposits
         WHERE status='approved' AND created_at >= %s AND created_at < %s",
        $range_start, $range_end
    ));

    $withdrawals_total = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}withdrawals
         WHERE status IN ('completed','approved') AND created_at >= %s AND created_at < %s",
        $range_start, $range_end
    ));

    $new_users = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= %s AND user_registered < %s",
        $range_start, $range_end
    ));

    wp_send_json_success(array(
        'daily'   => $data,
        'summary' => array(
            'total_visits'     => $sum_visits,
            'verified'         => $sum_verified,
            'customer_paid'    => $customer_paid_total,
            'user_earned'      => $sum_user_earned,
            'platform_revenue' => $customer_paid_total - $sum_user_earned,
            'deposits'         => $deposits_total,
            'withdrawals'      => $withdrawals_total,
            'new_users'        => $new_users,
        ),
    ));
}

// Campaign CRUD
add_action('wp_ajax_sitetop_admin_create_campaign', 'sitetop_ajax_admin_create_campaign');
function sitetop_ajax_admin_create_campaign() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $result = sitetop_create_keyword_campaign($_POST);
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    wp_send_json_success(array('campaign_id'=>$result));
}

add_action('wp_ajax_sitetop_admin_update_campaign', 'sitetop_ajax_admin_update_campaign');
function sitetop_ajax_admin_update_campaign() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $id = absint($_POST['campaign_id']??0);
    if (!$id) wp_send_json_error('Missing ID');

    // Screenshot URLs (already uploaded to ImgBB via AJAX)
    // Vị trí trang Google: kẹp 1–10 ngay tại đây, không tin số client gửi lên.
    if (isset($_POST['serp_page'])) {
        $_POST['serp_page'] = max(1, min(10, intval($_POST['serp_page'])));
    }
    foreach (array('screenshot_desktop_url', 'screenshot_mobile_url', 'nocode_screenshot_url', 'step2_image_url', 'step2_target_url') as $col) {
        if (!empty($_POST[$col])) {
            $_POST[$col] = esc_url_raw($_POST[$col]);
        }
    }

    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $camp = $wpdb->get_row($wpdb->prepare(
        "SELECT kc.*, co.task_type FROM {$p}keyword_campaigns kc LEFT JOIN {$p}customer_orders co ON co.id=kc.order_id WHERE kc.id=%d", $id));

    // Danh sách URL đích: chuẩn hoá rồi mới cho đi tiếp. URL đầu tiên đồng bộ vào
    // target_url để mọi chỗ đang đọc target_url không lệch với danh sách.
    if (isset($_POST['destination_urls'])) {
        $dest = sitetop_sanitize_destination_urls($_POST['destination_urls']);
        if ($dest['error']) wp_send_json_error($dest['error']);
        $_POST['destination_urls'] = wp_json_encode($dest['urls']);
        $_POST['target_url']       = $dest['urls'][0];
    }

    // Giá custom chỉ nhận khi camp Chờ duyệt — camp đang chạy giữ giá theo settings/loại
    if (isset($_POST['price_per_view'])) {
        $posted_price = floatval($_POST['price_per_view']);
        if (!$camp || $camp->status !== 'pending' || $posted_price <= 0) {
            unset($_POST['price_per_view']);
        } else {
            $_POST['price_per_view'] = $posted_price;
        }
    }

    // Auto-calculate price_per_view and user_reward from settings — chỉ khi loại traffic/onsite
    // thật sự đổi, để không reset giá custom của camp ở những lần sửa field khác
    if (isset($_POST['traffic_type']) && $camp) {
        $task_type = $camp->task_type ?? 'keyword_search';
        $tt = sanitize_text_field($_POST['traffic_type']);
        $os = intval($_POST['onsite_time'] ?? $camp->onsite_time ?? 70);
        $type_changed = ($tt !== ($camp->traffic_type ?? '1step')) || ($os !== intval($camp->onsite_time ?? 70));
        if ($type_changed) {
            $price_key = ($task_type === 'keyword_search') ? 'keyword_price_' : 'direct_price_';
            $reward_key = ($task_type === 'keyword_search') ? 'keyword_user_' : 'direct_user_';
            $onsite_extra = array(70=>(int)sitetop_get_option('onsite_extra_70',0),80=>(int)sitetop_get_option('onsite_extra_80',100),90=>(int)sitetop_get_option('onsite_extra_90',200),100=>(int)sitetop_get_option('onsite_extra_100',300),120=>(int)sitetop_get_option('onsite_extra_120',400),150=>(int)sitetop_get_option('onsite_extra_150',500));
            if (!isset($_POST['price_per_view'])) {
                $_POST['price_per_view'] = floatval(sitetop_get_option($price_key . $tt, 1200)) + ($onsite_extra[$os] ?? 0);
            }
            if (!isset($_POST['user_reward'])) {
                $user_onsite_extra = array(70=>(int)sitetop_get_option('user_onsite_extra_70',0),80=>(int)sitetop_get_option('user_onsite_extra_80',0),90=>(int)sitetop_get_option('user_onsite_extra_90',0),100=>(int)sitetop_get_option('user_onsite_extra_100',0),120=>(int)sitetop_get_option('user_onsite_extra_120',0),150=>(int)sitetop_get_option('user_onsite_extra_150',0));
                $_POST['user_reward'] = floatval(sitetop_get_option($reward_key . $tt, 800)) + ($user_onsite_extra[$os] ?? 0);
            }
        }
    }

    $result = sitetop_update_campaign($id, $_POST);
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    if ($result === false) wp_send_json_error('Update failed');

    // Sync giá hiển thị trên order khi giá campaign thay đổi (mirror customer-campaign-ajax.php)
    if (isset($_POST['price_per_view']) && $camp && !empty($camp->order_id)) {
        $wpdb->update("{$p}customer_orders",
            array('price_per_task' => floatval($_POST['price_per_view']), 'updated_at' => sitetop_current_time()),
            array('id' => $camp->order_id));
    }

    wp_send_json_success();
}

add_action('wp_ajax_sitetop_admin_get_campaigns', 'sitetop_ajax_admin_get_campaigns');

// Get single campaign detail (admin)
add_action('wp_ajax_sitetop_admin_get_campaign', 'sitetop_ajax_admin_get_campaign');
function sitetop_ajax_admin_get_campaign() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $id = absint($_POST['campaign_id'] ?? 0);
    if (!$id) wp_send_json_error('Missing ID');
    $c = $wpdb->get_row($wpdb->prepare(
        "SELECT kc.*, co.task_type, co.customer_username FROM {$p}keyword_campaigns kc
         LEFT JOIN {$p}customer_orders co ON co.id = kc.order_id WHERE kc.id=%d", $id
    ));
    if (!$c) wp_send_json_error('Not found');
    wp_send_json_success(array(
        'id'=>$c->id, 'title'=>$c->title, 'keyword'=>$c->keyword, 'target_url'=>$c->target_url,
        'destination_urls'=>sitetop_campaign_destinations($c),
        'task_type'=>$c->task_type??'keyword_search', 'traffic_type'=>$c->traffic_type,
        'onsite_time'=>$c->onsite_time, 'price_per_view'=>$c->price_per_view,
        'user_reward'=>$c->user_reward, 'daily_traffic'=>$c->daily_traffic, 'quantity'=>$c->quantity,
        'status'=>$c->status, 'customer_username'=>$c->customer_username,
        'screenshot_desktop_url'=>$c->screenshot_desktop_url, 'screenshot_mobile_url'=>$c->screenshot_mobile_url,
        'fixed_code'=>$c->fixed_code??'', 'nocode_screenshot_url'=>$c->nocode_screenshot_url??'',
        // Hàm này KHÔNG trả kc.* mà liệt kê từng trường — thiếu trường nào là modal sửa
        // không nhận được trường đó, hiện "Chưa có", rồi lần lưu sau ghi đè rỗng lên DB.
        'step2_image_url'=>$c->step2_image_url??'', 'step2_target_url'=>$c->step2_target_url??'',
        'serp_page'=>(int)($c->serp_page ?? 1),
    ));
}

// Update widget code status (Đã gắn / Chưa gắn widget.js trên web đích)
add_action('wp_ajax_sitetop_admin_update_widget_code_status', 'sitetop_ajax_admin_update_widget_code_status');
function sitetop_ajax_admin_update_widget_code_status() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $id = absint($_POST['campaign_id'] ?? 0);
    $status = sanitize_text_field($_POST['widget_code_status'] ?? '');
    if (!$id) wp_send_json_error('Missing ID');
    if (!in_array($status, array('attached','not_attached'))) wp_send_json_error('Invalid status');
    // Ensure column exists
    $col = $wpdb->get_results("SHOW COLUMNS FROM {$p}keyword_campaigns LIKE 'widget_code_status'");
    if (empty($col)) {
        $wpdb->query("ALTER TABLE {$p}keyword_campaigns ADD COLUMN widget_code_status varchar(20) NOT NULL DEFAULT 'not_attached' AFTER daily_traffic");
    }
    $wpdb->update("{$p}keyword_campaigns", array('widget_code_status' => $status), array('id' => $id));
    wp_send_json_success();
}

function sitetop_ajax_admin_get_campaigns() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $campaigns = sitetop_get_campaigns(array(
        'status'=>sanitize_text_field($_POST['status']??''),
        'search'=>sanitize_text_field($_POST['search']??''),
        'limit'=>absint($_POST['limit']??20), 'offset'=>absint($_POST['offset']??0),
    ));
    wp_send_json_success(array('campaigns'=>$campaigns));
}

// Withdrawal management
add_action('wp_ajax_sitetop_admin_process_withdrawal', 'sitetop_ajax_admin_process_withdrawal');
function sitetop_ajax_admin_process_withdrawal() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $result = sitetop_process_withdrawal(absint($_POST['withdrawal_id']??0), sanitize_text_field($_POST['new_status']??''), sanitize_text_field($_POST['admin_note']??''));
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    wp_send_json_success();
}

/* ============================================================
   CHI TIẾT LỆNH RÚT (31/08/2026)
   Trả dữ liệu THẬT đã tạo ra số tiền của ĐÚNG lệnh rút đó, trong đúng kỳ đã chốt.
   Chỉ ĐỌC, không ghi gì, không đụng logic rút tiền.
   ============================================================ */

/** Đọc tên thiết bị + trình duyệt từ user agent. Cột browser_fingerprint có trong
 *  bảng nhưng chưa bao giờ được ghi, nên chỗ này chỉ dựa vào user_agent. */
function sitetop_ua_device( $ua ) {
    $ua = (string) $ua;
    if ( $ua === '' ) return array( 'os' => 'Không rõ', 'browser' => '', 'label' => 'Không rõ' );

    $os = 'Không rõ';
    if ( preg_match( '/Android\s*([\d.]+)/i', $ua, $m ) )                 $os = 'Android ' . $m[1];
    elseif ( stripos( $ua, 'Android' ) !== false )                          $os = 'Android';
    elseif ( preg_match( '/iPhone OS ([\d_]+)/i', $ua, $m ) )              $os = 'iPhone iOS ' . str_replace( '_', '.', $m[1] );
    elseif ( stripos( $ua, 'iPhone' ) !== false )                           $os = 'iPhone';
    elseif ( stripos( $ua, 'iPad' ) !== false )                             $os = 'iPad';
    elseif ( stripos( $ua, 'Windows NT 10' ) !== false )                    $os = 'Windows 10/11';
    elseif ( stripos( $ua, 'Windows' ) !== false )                          $os = 'Windows';
    elseif ( stripos( $ua, 'Mac OS X' ) !== false )                         $os = 'macOS';
    elseif ( stripos( $ua, 'Linux' ) !== false )                            $os = 'Linux';

    $br = '';
    if ( stripos( $ua, 'Edg/' ) !== false )        $br = 'Edge';
    elseif ( stripos( $ua, 'OPR/' ) !== false )    $br = 'Opera';
    elseif ( stripos( $ua, 'CriOS' ) !== false )   $br = 'Chrome';
    elseif ( stripos( $ua, 'Chrome' ) !== false )  $br = 'Chrome';
    elseif ( stripos( $ua, 'FxiOS' ) !== false || stripos( $ua, 'Firefox' ) !== false ) $br = 'Firefox';
    elseif ( stripos( $ua, 'Safari' ) !== false )  $br = 'Safari';

    return array( 'os' => $os, 'browser' => $br, 'label' => trim( $os . ( $br ? ' · ' . $br : '' ) ) );
}

/**
 * @param int    $uid          user
 * @param string $period_start mốc mở kỳ (loại trừ)
 * @param string $period_end   mốc chốt kỳ (bao gồm)
 */
function sitetop_wd_period_detail( $uid, $period_start, $period_end ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

    /* Lấy MỌI lượt trong kỳ, không chỉ lượt được trả tiền — admin cần thấy cả lượt
       hỏng để hiểu vì sao tỷ lệ hoàn thành như vậy. */
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT v.id, v.created_at, v.code_shown_at, v.verified_at, v.completion_time,
                v.ip_address, v.original_ip, v.ip_changed, v.user_agent, v.step,
                v.reward_paid, v.reward_amount, v.is_bypass, v.ip_limit_exceeded,
                v.adblock_detected, v.skip_reasons,
                kc.title AS camp_title, kc.traffic_type, kc.campaign_type,
                us.code AS sl_code
           FROM {$p}shortlink_visits v
           LEFT JOIN {$p}keyword_campaigns kc ON kc.id = v.campaign_id
           LEFT JOIN {$p}user_shortlinks  us ON us.id = v.shortlink_id
          WHERE v.user_id = %d AND v.created_at > %s AND v.created_at <= %s
          ORDER BY v.created_at ASC",
        $uid, $period_start, $period_end
    ) );

    $ip_map = array(); $dev_map = array(); $tasks = array();
    $tong_tien = 0.0; $so_tra_tien = 0;
    $tt_labels = array( '1step' => '1 bước', '2step' => '2 bước', 'nocode' => 'Mã cố định' );

    foreach ( (array) $rows as $r ) {
        $paid   = (int) $r->reward_paid === 1;
        $tien   = $paid ? (float) $r->reward_amount : 0.0;
        $ip     = $r->ip_address ?: '—';
        $dev    = sitetop_ua_device( $r->user_agent );

        if ( $paid ) { $tong_tien += $tien; $so_tra_tien++; }

        if ( ! isset( $ip_map[ $ip ] ) ) {
            $ip_map[ $ip ] = array( 'ip' => $ip, 'luot' => 0, 'tra_tien' => 0, 'tien' => 0.0,
                                    'dau' => $r->created_at, 'cuoi' => $r->created_at, 'thiet_bi' => array() );
        }
        $ip_map[ $ip ]['luot']++;
        if ( $paid ) { $ip_map[ $ip ]['tra_tien']++; $ip_map[ $ip ]['tien'] += $tien; }
        $ip_map[ $ip ]['cuoi'] = $r->created_at;
        $ip_map[ $ip ]['thiet_bi'][ $dev['label'] ] = 1;

        if ( ! isset( $dev_map[ $dev['label'] ] ) ) {
            $dev_map[ $dev['label'] ] = array( 'ten' => $dev['label'], 'os' => $dev['os'],
                                               'trinh_duyet' => $dev['browser'], 'luot' => 0, 'tra_tien' => 0, 'ip' => array() );
        }
        $dev_map[ $dev['label'] ]['luot']++;
        if ( $paid ) $dev_map[ $dev['label'] ]['tra_tien']++;
        $dev_map[ $dev['label'] ]['ip'][ $ip ] = 1;

        // Lý do không được trả tiền — đọc từ skip_reasons, dịch sang tiếng Việt
        $ly_do = '';
        if ( ! $paid ) {
            if ( (int) $r->ip_limit_exceeded === 1 )      $ly_do = 'IP đã hết hạn mức ngày';
            elseif ( (int) $r->is_bypass === 1 )          $ly_do = 'Chưa đủ thời gian onsite';
            elseif ( $r->step !== 'verified' )            $ly_do = 'Chưa hoàn thành';
            else                                          $ly_do = 'Không đủ điều kiện';
        }

        $tasks[] = array(
            'id'        => (int) $r->id,
            'bat_dau'   => $r->created_at,
            'hien_ma'   => $r->code_shown_at,
            'hoan_thanh'=> $r->verified_at,
            'giay'      => (int) $r->completion_time,
            'camp'      => $r->camp_title ?: '—',
            'loai'      => $tt_labels[ $r->traffic_type ] ?? ( $r->traffic_type ?: '—' ),
            'shortlink' => $r->sl_code ?: '—',
            'ip'        => $ip,
            'doi_ip'    => (int) $r->ip_changed === 1,
            'thiet_bi'  => $dev['label'],
            'tra_tien'  => $paid,
            'tien'      => $tien,
            'ly_do'     => $ly_do,
        );
    }

    foreach ( $ip_map as $k => $v ) {
        $ip_map[ $k ]['thiet_bi'] = implode( ', ', array_keys( $v['thiet_bi'] ) );
    }
    foreach ( $dev_map as $k => $v ) {
        $dev_map[ $k ]['so_ip'] = count( $v['ip'] );
        unset( $dev_map[ $k ]['ip'] );
    }
    /* Tách làm hai câu lệnh: usort nhận tham chiếu nên KHÔNG truyền được biểu thức gán. */
    $ip_map  = array_values( $ip_map );
    $dev_map = array_values( $dev_map );
    usort( $ip_map,  function( $a, $b ) { return $b['luot'] <=> $a['luot']; } );
    usort( $dev_map, function( $a, $b ) { return $b['luot'] <=> $a['luot']; } );

    return array(
        'tong_luot'    => count( $tasks ),
        'so_tra_tien'  => $so_tra_tien,
        'tong_tien'    => round( $tong_tien, 2 ),
        'so_ip'        => count( $ip_map ),
        'so_thiet_bi'  => count( $dev_map ),
        'moc_dau'      => $tasks ? $tasks[0]['bat_dau'] : null,
        'moc_cuoi'     => $tasks ? end( $tasks )['bat_dau'] : null,
        'ips'          => $ip_map,
        'thiet_bi'     => $dev_map,
        'nhiem_vu'     => $tasks,
    );
}

// Helper: compute fraud stats for user trong period (default all-time)
// Trả array 18 keys + extras: views, paid_views, clicks, risk_reasons, total_earned,
// completion_rate, completion_fit, ip_conc_fit, risk_level, bypass, change_ip, max_ip,
// adblock, ip_over_3, top_ips, sources, top_referers, shortlinks (+ risk_label, risk_score,
// unique_paid_ips, self_click_count, has_utm để frontend dùng).
function sitetop_compute_user_fraud_stats($uid, $period_start = '1970-01-01 00:00:00', $period_end = null) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    if ($period_end === null) {
        $period_end = function_exists('sitetop_current_time') ? sitetop_current_time() : current_time('mysql');
    }

    // Detect UTM column
    $has_utm = (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'utm_source'",
        $wpdb->prefix . 'sitetop_shortlink_visits'
    ));

    // Classify referer (PHP version, mirror frontend logic)
    $classify_referer = function($referer) {
        if (empty($referer)) return 'Trực tiếp';
        $host = strtolower(parse_url($referer, PHP_URL_HOST) ?: '');
        if ($host === '') return 'Khác';
        $is_host = function($h, $needles) {
            foreach ((array)$needles as $n) {
                if ($h === $n || substr($h, -strlen($n)-1) === '.'.$n) return true;
            }
            return false;
        };
        if (preg_match('/^(.+\.)?google\.[a-z.]+$/', $host)) return 'Google';
        if ($is_host($host, ['facebook.com','fb.com','fb.me'])) return 'Facebook';
        if ($is_host($host, ['twitter.com','x.com','t.co'])) return 'Twitter/X';
        if ($is_host($host, ['youtube.com','youtu.be'])) return 'YouTube';
        if ($is_host($host, 'tiktok.com')) return 'TikTok';
        if ($is_host($host, 'instagram.com')) return 'Instagram';
        if ($is_host($host, ['telegram.org','telegram.me','t.me'])) return 'Telegram';
        if ($is_host($host, ['zalo.me','zalo.vn','zaloapp.com'])) return 'Zalo';
        if ($is_host($host, 'reddit.com')) return 'Reddit';
        if ($is_host($host, 'bing.com')) return 'Bing';
        $self_host = strtolower(parse_url(home_url(), PHP_URL_HOST) ?: '');
        if ($self_host && ($host === $self_host || substr($host, -strlen($self_host)-1) === '.'.$self_host)) return 'Nội bộ';
        return preg_replace('/^www\./', '', $host);
    };

    // Period-scoped counters
    $clicks = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits
         WHERE user_id=%d AND created_at > %s AND created_at <= %s",
        $uid, $period_start, $period_end));

    $views = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits
         WHERE user_id=%d AND (step='verified' OR customer_paid=1)
         AND created_at > %s AND created_at <= %s",
        $uid, $period_start, $period_end));

    $paid_views = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits
         WHERE user_id=%d AND reward_paid=1
         AND created_at > %s AND created_at <= %s",
        $uid, $period_start, $period_end));

    $completion_rate = $clicks > 0 ? round($paid_views / $clicks * 100, 1) : 0;

    $change_ip = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits
         WHERE user_id=%d AND ip_changed=1
         AND created_at > %s AND created_at <= %s",
        $uid, $period_start, $period_end));

    $bypass = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits
         WHERE user_id=%d AND is_bypass=1
         AND created_at > %s AND created_at <= %s",
        $uid, $period_start, $period_end));

    $adblock = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits
         WHERE user_id=%d AND adblock_detected=1
         AND created_at > %s AND created_at <= %s",
        $uid, $period_start, $period_end));

    $max_ip = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits
         WHERE user_id=%d AND ip_limit_exceeded=1
         AND created_at > %s AND created_at <= %s",
        $uid, $period_start, $period_end));

    $ip_over_3 = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM (
            SELECT ip_address, COUNT(*) as cnt
            FROM {$p}shortlink_visits
            WHERE user_id=%d AND reward_paid=1
            AND created_at > %s AND created_at <= %s
            GROUP BY ip_address HAVING cnt > 3
        ) t", $uid, $period_start, $period_end));

    $unique_paid_ips = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT ip_address) FROM {$p}shortlink_visits
         WHERE user_id=%d AND reward_paid=1
         AND created_at > %s AND created_at <= %s",
        $uid, $period_start, $period_end));

    // Self-click
    $self_host = parse_url(home_url(), PHP_URL_HOST) ?: '';
    $self_host = preg_replace('/^www\./', '', strtolower($self_host));
    $self_click_count = 0;
    if ($self_host !== '') {
        $patterns = array(
            '%//' . $self_host . '/user%',     '%.' . $self_host . '/user%',
            '%//' . $self_host . '/customer%', '%.' . $self_host . '/customer%',
            '%//' . $self_host . '/wp-admin%', '%.' . $self_host . '/wp-admin%',
        );
        $or = implode(' OR ', array_fill(0, count($patterns), 'referer LIKE %s'));
        $params = array_merge(array($uid, $period_start, $period_end), $patterns);
        $self_click_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}shortlink_visits
             WHERE user_id=%d AND reward_paid=1
             AND created_at > %s AND created_at <= %s
             AND ($or)",
            $params));
    }

    // Sybil signal 1: device fingerprint shared across multiple accounts.
    // Among fingerprints THIS user used, the max number of distinct accounts sharing any of
    // them. >1 => same physical device logged into multiple publisher accounts. (canvas_hash
    // is client-spoofable, so this catches naive farmers; combine with IP signal below.)
    $shared_device_accounts = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(MAX(c),0) FROM (
            SELECT COUNT(DISTINCT df2.user_id) AS c
            FROM {$p}device_fingerprints df1
            INNER JOIN {$p}device_fingerprints df2 ON df1.fingerprint = df2.fingerprint
            WHERE df1.user_id = %d
            GROUP BY df1.fingerprint
        ) t",
        $uid ));

    // Sybil signal 2: distinct paid accounts sharing an IP this user earned from (server-side,
    // NOT spoofable by the client). High => account-farm behind one IP. NAT/CGNAT can legitimately
    // have a few, so thresholds below are conservative.
    $accounts_per_ip = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(MAX(c),0) FROM (
            SELECT COUNT(DISTINCT user_id) AS c
            FROM {$p}shortlink_visits
            WHERE reward_paid=1 AND created_at > %s AND created_at <= %s
            AND ip_address IN (
                SELECT ip_address FROM (
                    SELECT DISTINCT ip_address FROM {$p}shortlink_visits
                    WHERE user_id=%d AND reward_paid=1 AND created_at > %s AND created_at <= %s
                ) my_ips
            )
            GROUP BY ip_address
        ) t",
        $period_start, $period_end, $uid, $period_start, $period_end ));

    // Earned from transactions
    $total_earned = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}transactions
         WHERE user_id=%d AND type='shortlink_reward'
         AND created_at > %s AND created_at <= %s",
        $uid, $period_start, $period_end));

    // Top IPs
    $top_ips = $wpdb->get_results($wpdb->prepare(
        "SELECT ip_address, COUNT(*) as cnt, COALESCE(SUM(reward_amount),0) as earned
         FROM {$p}shortlink_visits
         WHERE user_id=%d AND reward_paid=1
         AND created_at > %s AND created_at <= %s
         GROUP BY ip_address ORDER BY cnt DESC LIMIT 1000",
        $uid, $period_start, $period_end));

    // Source badges
    $sources_raw = $wpdb->get_results($wpdb->prepare(
        "SELECT referer, COUNT(*) as cnt
         FROM {$p}shortlink_visits
         WHERE user_id=%d AND reward_paid=1
         AND created_at > %s AND created_at <= %s
         GROUP BY referer ORDER BY cnt DESC LIMIT 1000",
        $uid, $period_start, $period_end));
    $sources_map = array();
    foreach ($sources_raw as $r) {
        $label = $classify_referer($r->referer);
        if (!isset($sources_map[$label])) $sources_map[$label] = 0;
        $sources_map[$label] += (int)$r->cnt;
    }
    arsort($sources_map);

    // Top referer URLs (with UTM)
    $utm_select = $has_utm ? ', utm_source, utm_medium, utm_campaign' : '';
    $utm_group  = $has_utm ? ', utm_source, utm_medium, utm_campaign' : '';
    $top_referers = $wpdb->get_results($wpdb->prepare(
        "SELECT referer{$utm_select}, COUNT(*) as cnt, COALESCE(SUM(reward_amount),0) as earned
         FROM {$p}shortlink_visits
         WHERE user_id=%d AND reward_paid=1
         AND created_at > %s AND created_at <= %s
         GROUP BY referer{$utm_group}
         ORDER BY cnt DESC LIMIT 1000",
        $uid, $period_start, $period_end));

    // Shortlinks
    $shortlinks = $wpdb->get_results($wpdb->prepare(
        "SELECT us.code, us.original_url, COUNT(*) as views, COALESCE(SUM(v.reward_amount),0) as earned
         FROM {$p}shortlink_visits v
         INNER JOIN {$p}user_shortlinks us ON v.shortlink_id=us.id
         WHERE v.user_id=%d AND v.reward_paid=1
         AND v.created_at > %s AND v.created_at <= %s
         GROUP BY us.id, us.original_url ORDER BY views DESC LIMIT 1000",
        $uid, $period_start, $period_end));

    // Per-shortlink source breakdown
    $sl_refs_raw = $wpdb->get_results($wpdb->prepare(
        "SELECT us.code, v.referer, COUNT(*) as cnt
         FROM {$p}shortlink_visits v
         INNER JOIN {$p}user_shortlinks us ON v.shortlink_id=us.id
         WHERE v.user_id=%d AND v.reward_paid=1
         AND v.created_at > %s AND v.created_at <= %s
         GROUP BY us.code, v.referer
         ORDER BY us.code, cnt DESC LIMIT 10000",
        $uid, $period_start, $period_end));
    $sl_sources_map = array();
    foreach ($sl_refs_raw as $r) {
        $label = $classify_referer($r->referer);
        if (!isset($sl_sources_map[$r->code])) $sl_sources_map[$r->code] = array();
        if (!isset($sl_sources_map[$r->code][$label])) {
            $sl_sources_map[$r->code][$label] = array('count' => 0, 'urls' => array());
        }
        $sl_sources_map[$r->code][$label]['count'] += (int)$r->cnt;
        if (count($sl_sources_map[$r->code][$label]['urls']) < 3 && !empty($r->referer)) {
            $sl_sources_map[$r->code][$label]['urls'][] = $r->referer;
        }
    }

    // IP concentration
    $ip_conc = $unique_paid_ips > 0 ? round(($ip_over_3 / $unique_paid_ips) * 100, 1) : 0;

    // Fit analysis
    if ($paid_views < 20) {
        $completion_fit = 'mẫu nhỏ, chưa đủ để đánh giá';
    } elseif ($completion_rate < 10) {
        $completion_fit = 'rất thấp — đáng nghi (vùng tự nhiên 30-90%)';
    } elseif ($completion_rate < 20) {
        $completion_fit = 'thấp — bất thường (vùng tự nhiên 30-90%)';
    } elseif ($completion_rate < 30) {
        $completion_fit = 'dưới trung bình (vùng tự nhiên 30-90%)';
    } elseif ($completion_rate <= 90) {
        $completion_fit = 'phù hợp (vùng tự nhiên 30-90%)';
    } elseif ($completion_rate <= 95) {
        $completion_fit = 'quá cao — bất thường (người dùng thật thường 50-80%)';
    } else {
        $completion_fit = 'cực cao — bot quá hoàn hảo (người dùng thật không đạt được)';
    }

    if ($unique_paid_ips < 20) {
        $ip_conc_fit = 'mẫu nhỏ, chưa đủ để đánh giá';
    } elseif ($ip_conc > 50) {
        $ip_conc_fit = "cực cao ({$ip_conc}% tổng IP) — đặc trưng trại bot";
    } elseif ($ip_conc > 25) {
        $ip_conc_fit = "vượt vùng tự nhiên ({$ip_conc}% tổng IP, ngưỡng ≤25%)";
    } else {
        $ip_conc_fit = "phù hợp ({$ip_conc}% tổng IP, vùng tự nhiên ≤25%)";
    }

    // Anomaly-based risk scoring
    $risk_score = 0;
    $risk_reasons = array();

    // 1. Completion rate
    if ($paid_views >= 20) {
        if ($completion_rate <= 10) {
            $risk_score += 4;
            $risk_reasons[] = "Tỷ lệ hoàn thành rất thấp: {$completion_rate}% (vùng tự nhiên 30-90%)";
        } elseif ($completion_rate < 20) {
            $risk_score += 2;
            $risk_reasons[] = "Tỷ lệ hoàn thành thấp: {$completion_rate}%";
        } elseif ($completion_rate < 30) {
            $risk_score += 1;
            $risk_reasons[] = "Tỷ lệ hoàn thành dưới trung bình: {$completion_rate}%";
        } elseif ($completion_rate > 90) {
            $risk_score += 1;
            $risk_reasons[] = "Tỷ lệ hoàn thành quá cao: {$completion_rate}% (>90% — đáng nghi bot, người dùng thật khó đạt)";
        }
    }

    // 2. Change IP %
    if ($paid_views >= 50) {
        $change_ratio = round(($change_ip / $paid_views) * 100, 1);
        if ($change_ratio > 30) {
            $risk_score += 4;
            $risk_reasons[] = "Change IP={$change_ip}/View={$paid_views} (tỷ lệ {$change_ratio}%) — đổi VPN/proxy liên tục";
        } elseif ($change_ratio > 15) {
            $risk_score += 2;
            $risk_reasons[] = "Change IP={$change_ip}/View={$paid_views} (tỷ lệ {$change_ratio}%) — cao bất thường";
        } elseif ($change_ratio > 7) {
            $risk_score += 1;
            $risk_reasons[] = "Change IP={$change_ip}/View={$paid_views} (tỷ lệ {$change_ratio}%) — hơi cao";
        }
    }

    // 3. Max IP %
    if ($paid_views >= 100) {
        $max_ratio = round(($max_ip / $paid_views) * 100, 1);
        if ($max_ratio > 30) {
            $risk_score += 2;
            $risk_reasons[] = "Max IP={$max_ip}/View={$paid_views} (tỷ lệ {$max_ratio}%) — quá nhiều IP đạt giới hạn ngày";
        } elseif ($max_ratio > 15) {
            $risk_score += 1;
            $risk_reasons[] = "Max IP={$max_ip}/View={$paid_views} (tỷ lệ {$max_ratio}%) — nhiều IP đạt giới hạn ngày";
        }
    }

    // 4. Reuse ratio
    if ($max_ip >= 30 && $ip_over_3 > 0) {
        $reuse_ratio = round($max_ip / $ip_over_3, 1);
        if ($reuse_ratio > 30) {
            $risk_score += 5;
            $risk_reasons[] = "Tập trung IP cực cao: Max IP={$max_ip} chỉ từ IP >3={$ip_over_3} (trung bình {$reuse_ratio} lượt/IP) — trại bot";
        } elseif ($reuse_ratio > 15) {
            $risk_score += 2;
            $risk_reasons[] = "Tập trung IP cao: Max IP={$max_ip}, IP >3={$ip_over_3} (trung bình {$reuse_ratio} lượt/IP) — đáng nghi";
        } elseif ($reuse_ratio > 8) {
            $risk_score += 1;
            $risk_reasons[] = "IP bị tái sử dụng nhiều: Max IP={$max_ip}, IP >3={$ip_over_3} (trung bình {$reuse_ratio} lượt/IP)";
        }
    }

    // 5. IP concentration (silent at moderate)
    if ($unique_paid_ips >= 20) {
        if ($ip_conc > 50) {
            $risk_score += 3;
            $risk_reasons[] = "IP trùng lặp cực cao: IP >3={$ip_over_3} (chiếm {$ip_conc}% tổng IP) — đặc trưng trại bot";
        } elseif ($ip_conc > 25) {
            $risk_score += 1; // silent
        }
    }

    // 4c. IP pool / clickfarm (silent)
    if ($paid_views >= 100 && $ip_over_3 >= 10 && $ip_over_3 > $max_ip) {
        if ($change_ip == 0) {
            $risk_score += 3;
        } else {
            $ip3_to_change = $ip_over_3 / $change_ip;
            if ($ip3_to_change >= 10)     $risk_score += 3;
            elseif ($ip3_to_change >= 5)  $risk_score += 2;
            elseif ($ip3_to_change >= 3)  $risk_score += 1;
        }
    }

    // 4d. Traffic nhiệm vụ
    if ($paid_views >= 200 && $max_ip >= 30 && $ip_over_3 >= 30) {
        $tw_change_ratio = ($change_ip / $paid_views) * 100;
        if ($tw_change_ratio < 3) {
            $tw_share = round((($max_ip + $ip_over_3) / $paid_views) * 100, 1);
            if ($tw_share >= 20) {
                $risk_score += 3;
                $risk_reasons[] = "⚠ Traffic nhiệm vụ: IP >3={$ip_over_3} + Max IP={$max_ip} ({$tw_share}% View) — người làm thuê hàng ngày HOẶC dải IP quy mô lớn dùng tới hạn (không phải lưu lượng tự nhiên)";
            } elseif ($tw_share >= 10) {
                $risk_score += 2;
                $risk_reasons[] = "⚠ Có dấu hiệu của traffic nhiệm vụ: IP >3={$ip_over_3} + Max IP={$max_ip} ({$tw_share}% View) — đặc trưng dải IP / người làm thuê";
            }
        }
    }

    // 6. Self-click
    if ($paid_views >= 20) {
        $self_ratio = round(($self_click_count / $paid_views) * 100, 1);
        if ($self_ratio > 50) {
            $risk_score += 5;
            $risk_reasons[] = "Tự click rất cao: {$self_ratio}% View từ trang quản trị ({$self_click_count}/{$paid_views}) — chủ shortlink tự click";
        } elseif ($self_ratio > 20) {
            $risk_score += 2;
            $risk_reasons[] = "Tự click: {$self_ratio}% View từ trang quản trị ({$self_click_count}/{$paid_views})";
        } elseif ($self_ratio > 5) {
            $risk_score += 1;
            $risk_reasons[] = "Tự click nhẹ: {$self_ratio}% View từ trang quản trị ({$self_click_count}/{$paid_views})";
        }
    }

    // 7. "Quá sạch" syndrome
    if ($paid_views > 100) {
        $zero_signals = 0;
        $zero_list = array();
        if ($change_ip == 0) { $zero_signals++; $zero_list[] = 'Change IP'; }
        if ($ip_over_3 == 0) { $zero_signals++; $zero_list[] = 'IP >3'; }
        if ($max_ip == 0)    { $zero_signals++; $zero_list[] = 'Max IP'; }
        if ($zero_signals >= 2) {
            $risk_score += 2;
            $risk_reasons[] = "Đặc trưng quá sạch: {$zero_signals}/3 chỉ số = 0 (".implode(', ', $zero_list).") — bot không tạo nhiễu tự nhiên";
        }
    }

    // 9. Multi-account / Sybil (server-side signals)
    if ($shared_device_accounts > 2) {
        $risk_score += 3;
        $risk_reasons[] = "Thiết bị dùng chung {$shared_device_accounts} tài khoản (cùng device fingerprint) — nghi đa tài khoản farm";
    } elseif ($shared_device_accounts == 2) {
        $risk_score += 1;
        $risk_reasons[] = "Thiết bị dùng chung 2 tài khoản (cùng fingerprint) — có thể đa tài khoản hoặc dùng chung máy";
    }
    if ($paid_views >= 20) {
        if ($accounts_per_ip > 5) {
            $risk_score += 3;
            $risk_reasons[] = "Tối đa {$accounts_per_ip} tài khoản cùng kiếm tiền trên 1 IP — nghi trại tài khoản (Sybil)";
        } elseif ($accounts_per_ip > 2) {
            $risk_score += 1;
            $risk_reasons[] = "Có {$accounts_per_ip} tài khoản cùng kiếm tiền trên 1 IP — lưu ý (NAT/CGNAT bình thường có thể vài tài khoản)";
        }
    }

    // 8. Adblock = TRUST BONUS
    if ($paid_views >= 50 && $adblock > 0) {
        $risk_score = max(0, $risk_score - 1);
        $risk_reasons[] = "✓ Adblock={$adblock} — dấu hiệu người dùng thật (giảm 1 điểm rủi ro)";
    }

    if     ($risk_score >= 5) $risk_level = 'high';
    elseif ($risk_score >= 3) $risk_level = 'medium';
    elseif ($risk_score >= 1) $risk_level = 'low';
    else                      $risk_level = 'safe';

    $risk_labels = array(
        'safe'   => 'AN TOÀN',
        'low'    => 'RỦI RO THẤP',
        'medium' => 'RỦI RO TRUNG BÌNH',
        'high'   => 'RỦI RO CAO',
    );

    // Build output structures
    $out_top_ips = array();
    foreach ($top_ips as $r) {
        $out_top_ips[] = array(
            'ip'     => $r->ip_address,
            'cnt'    => (int)$r->cnt,
            'earned' => (float)$r->earned,
        );
    }

    $out_sources = array();
    foreach ($sources_map as $label => $cnt) {
        $out_sources[] = array('label' => $label, 'cnt' => (int)$cnt);
    }

    $out_referers = array();
    foreach ($top_referers as $r) {
        $out_referers[] = array(
            'referer'      => $r->referer,
            'label'        => $classify_referer($r->referer),
            'cnt'          => (int)$r->cnt,
            'earned'       => (float)$r->earned,
            'utm_source'   => $has_utm ? ($r->utm_source   ?? '') : '',
            'utm_medium'   => $has_utm ? ($r->utm_medium   ?? '') : '',
            'utm_campaign' => $has_utm ? ($r->utm_campaign ?? '') : '',
        );
    }

    $out_shortlinks = array();
    foreach ($shortlinks as $sl) {
        $sources_list = array();
        if (isset($sl_sources_map[$sl->code])) {
            $arr = $sl_sources_map[$sl->code];
            uasort($arr, function($a, $b){ return $b['count'] - $a['count']; });
            $i = 0;
            foreach ($arr as $label => $info) {
                if ($i++ >= 5) break;
                $sources_list[] = array(
                    'label' => $label,
                    'count' => (int)$info['count'],
                    'urls'  => array_values($info['urls']),
                );
            }
        }
        $out_shortlinks[] = array(
            'code'         => $sl->code,
            // X1 (server-side defense-in-depth): esc_url_raw strips javascript:/data: protocols,
            // so a malicious publisher original_url can never reach the admin modal as a live href.
            'original_url' => esc_url_raw( (string) $sl->original_url ),
            'views'        => (int)$sl->views,
            'earned'       => (float)$sl->earned,
            'sources'      => $sources_list,
        );
    }

    return array(
        'views'            => $views,
        'paid_views'       => $paid_views,
        'clicks'           => $clicks,
        'risk_reasons'     => array_values($risk_reasons),
        'total_earned'     => $total_earned,
        'completion_rate'  => $completion_rate,
        'completion_fit'   => $completion_fit,
        'ip_conc_fit'      => $ip_conc_fit,
        'risk_level'       => $risk_level,
        'bypass'           => $bypass,
        'change_ip'        => $change_ip,
        'max_ip'           => $max_ip ?: 0,
        'adblock'          => $adblock,
        'ip_over_3'        => $ip_over_3,
        'top_ips'          => $out_top_ips,
        'sources'          => $out_sources,
        'top_referers'     => $out_referers,
        'shortlinks'       => $out_shortlinks,
        // Extras for backward-compat with existing frontend
        'risk'             => $risk_level,
        'risk_label'       => $risk_labels[$risk_level],
        'risk_score'       => $risk_score,
        'unique_paid_ips'  => $unique_paid_ips,
        'self_click_count' => $self_click_count,
        'shared_device_accounts' => $shared_device_accounts,
        'accounts_per_ip'  => $accounts_per_ip,
        'has_utm'          => $has_utm,
    );
}

// Fraud check for withdrawal — period-scoped + anomaly-based scoring
add_action('wp_ajax_sitetop_admin_fraud_check', 'sitetop_ajax_admin_fraud_check');
function sitetop_ajax_admin_fraud_check() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $user_id = absint($_POST['user_id'] ?? 0);
    $wid = absint($_POST['withdrawal_id'] ?? 0);
    if (!$user_id) wp_send_json_error('Missing user_id');

    $w = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}withdrawals WHERE id=%d", $wid));
    if (!$w) wp_send_json_error('Withdrawal not found');

    /* Period scope: visits trong (period_start, period_end] đóng góp cho LỆNH RÚT NÀY.
       ƯU TIÊN hai cột đã CHỐT lúc đặt lệnh. Chỉ suy ra khi cột rỗng (lệnh cũ chưa bù,
       hoặc migration chưa chạy được).
       Phép suy ra dự phòng KHÔNG lọc theo status nữa: lọc theo status là nguyên nhân
       khiến kỳ nới rộng mỗi khi admin từ chối một lệnh trước đó. */
    $period_start = ! empty( $w->period_start ) ? $w->period_start : null;
    $period_end   = ! empty( $w->period_end )   ? $w->period_end   : $w->created_at;

    if ( ! $period_start ) {
        $prev_wd_at = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(created_at) FROM {$p}withdrawals WHERE user_id=%d AND id < %d",
            $user_id, $wid));
        if (!$prev_wd_at) {
            $prev_wd_at = $wpdb->get_var($wpdb->prepare(
                "SELECT MIN(created_at) FROM {$p}shortlink_visits WHERE user_id=%d AND (step='verified' OR customer_paid=1)", $user_id));
        }
        $period_start = $prev_wd_at ?: '1970-01-01 00:00:00';
    }

    $stats = sitetop_compute_user_fraud_stats($user_id, $period_start, $period_end);
    wp_send_json_success(array_merge($stats, array(
        'amount'        => (float)$w->amount,
        'period_start'  => $period_start,
        'period_end'    => $period_end,
        'period_pinned' => ! empty( $w->period_start ),
        'detail'        => sitetop_wd_period_detail($user_id, $period_start, $period_end),
    )));
}
// Deposit management
add_action('wp_ajax_sitetop_admin_approve_deposit', 'sitetop_ajax_admin_approve_deposit');
function sitetop_ajax_admin_approve_deposit() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $result = sitetop_approve_deposit(absint($_POST['deposit_id']??0), sanitize_text_field($_POST['admin_note']??''));
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());
    wp_send_json_success();
}

// Ban/unban user
add_action('wp_ajax_sitetop_admin_ban_user', 'sitetop_ajax_admin_ban_user');
function sitetop_ajax_admin_ban_user() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $uid = absint($_POST['user_id']??0);
    $action = sanitize_text_field($_POST['ban_action']??'ban');
    if ($action === 'ban') sitetop_ban_user($uid);
    else sitetop_unban_user($uid);
    wp_send_json_success();
}

// User stats (for modal)
add_action('wp_ajax_sitetop_admin_user_stats', 'sitetop_ajax_admin_user_stats');
function sitetop_ajax_admin_user_stats() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $uid = absint($_POST['user_id']??0);
    if (!$uid) wp_send_json_error('Missing user_id');

    $user = get_userdata($uid);
    if (!$user) wp_send_json_error('User not found');

    // Balance from transactions (source of truth)
    $earned = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE user_id=%d AND type='shortlink_reward'", $uid));
    $withdrawn = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}withdrawals WHERE user_id=%d AND status IN ('completed','cancelled')", $uid));
    $pending_w = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}withdrawals WHERE user_id=%d AND status IN ('pending','approved')", $uid));
    $other_ded = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE user_id=%d AND type='withdraw' AND (reference_type IS NULL OR reference_type != 'withdrawal')", $uid));
    $balance = max(0, $earned - $withdrawn - $pending_w - $other_ded);

    // Monthly stats (last 6 months) — riêng cho user_stats popup
    $monthly = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE_FORMAT(created_at, '%%m/%%Y') as month,
                COUNT(*) as total_load,
                SUM(CASE WHEN step='verified' OR customer_paid=1 THEN 1 ELSE 0 END) as views,
                COALESCE(SUM(CASE WHEN step='verified' AND reward_paid=1 THEN reward_amount ELSE 0 END),0) as earned
         FROM {$p}shortlink_visits WHERE user_id=%d
         GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
         ORDER BY DATE_FORMAT(created_at, '%%Y-%%m') DESC LIMIT 6", $uid));

    $monthly_data = array();
    foreach ($monthly as $m) {
        $monthly_data[] = array(
            'month'  => $m->month,
            'load'   => (int)$m->total_load,
            'views'  => (int)$m->views,
            'earned' => (float)$m->earned,
        );
    }

    // Fraud check stats (all-time scope)
    $stats = sitetop_compute_user_fraud_stats($uid);

    wp_send_json_success(array_merge($stats, array(
        'balance'    => $balance,
        'registered' => date('H:i d/m/Y', strtotime($user->user_registered)),
        'username'   => $user->user_login,
        'monthly'    => $monthly_data,
    )));
}

// Login as user (admin impersonation)
add_action('wp_ajax_sitetop_admin_login_as_user', 'sitetop_ajax_admin_login_as_user');
function sitetop_ajax_admin_login_as_user() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $uid = absint($_POST['user_id']??0);
    if (!$uid) wp_send_json_error('Missing user_id');
    $user = get_userdata($uid);
    if (!$user) wp_send_json_error('User not found');
    $admin_id = get_current_user_id();
    update_user_meta($uid, 'switch_from_admin', $admin_id);
    wp_set_auth_cookie($uid);
    wp_send_json_success(array('redirect' => home_url()));
}

// Edit user (name/email/phone/password)
add_action('wp_ajax_sitetop_admin_edit_user', 'sitetop_ajax_admin_edit_user');
function sitetop_ajax_admin_edit_user() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $uid = absint($_POST['user_id'] ?? 0);
    if (!$uid) wp_send_json_error('Thiếu user_id');
    $user = get_userdata($uid);
    if (!$user) wp_send_json_error('Không tìm thấy user');

    $display_name = sanitize_text_field($_POST['display_name'] ?? '');
    $email        = sanitize_email($_POST['email'] ?? '');
    $phone        = sanitize_text_field($_POST['phone'] ?? '');
    $password     = (string) ($_POST['password'] ?? '');

    if (empty($display_name)) wp_send_json_error('Tên hiển thị không được để trống');
    if (empty($email) || !is_email($email)) wp_send_json_error('Email không hợp lệ');

    // Check email unique (nếu khác email hiện tại)
    if (strtolower($email) !== strtolower($user->user_email)) {
        $exists = get_user_by('email', $email);
        if ($exists && (int) $exists->ID !== $uid) {
            wp_send_json_error('Email đã được sử dụng bởi tài khoản khác');
        }
    }

    if ($password !== '' && strlen($password) < 6) {
        wp_send_json_error('Mật khẩu phải từ 6 ký tự trở lên');
    }

    $update = array(
        'ID'           => $uid,
        'display_name' => $display_name,
        'user_email'   => $email,
    );
    if ($password !== '') $update['user_pass'] = $password;

    $result = wp_update_user($update);
    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());

    if ($phone !== '') {
        update_user_meta($uid, 'phone', $phone);
    } else {
        delete_user_meta($uid, 'phone');
    }

    wp_send_json_success(array('message' => 'Cập nhật thành công'));
}

// Activate user (bỏ qua xác nhận email)
add_action('wp_ajax_sitetop_admin_activate_user', 'sitetop_ajax_admin_activate_user');
function sitetop_ajax_admin_activate_user() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $uid = absint($_POST['user_id'] ?? 0);
    if (!$uid) wp_send_json_error('Thiếu user_id');
    $user = get_userdata($uid);
    if (!$user) wp_send_json_error('Không tìm thấy user');

    update_user_meta($uid, 'sitetop_email_verified', '1');
    delete_user_meta($uid, 'sitetop_email_verify_token');
    delete_user_meta($uid, 'sitetop_email_verify_expiry');
    // Khách hàng chờ kích hoạt thủ công → mở khóa dashboard luôn.
    delete_user_meta($uid, 'sitetop_customer_pending');

    wp_send_json_success(array('message' => 'Đã kích hoạt tài khoản'));
}

// Delete user
add_action('wp_ajax_sitetop_admin_delete_user', 'sitetop_ajax_admin_delete_user');
function sitetop_ajax_admin_delete_user() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $uid = absint($_POST['user_id']??0);
    if (!$uid) wp_send_json_error('Missing user_id');
    $user = get_userdata($uid);
    if (!$user) wp_send_json_error('User not found');
    if (user_can($uid, 'manage_options')) wp_send_json_error('Không thể xóa admin');

    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;

    // Reject pending withdrawals first (refund to balance)
    $pending_wds = $wpdb->get_results( $wpdb->prepare(
        "SELECT id FROM {$p}withdrawals WHERE user_id=%d AND status IN ('pending','approved')", $uid ));
    foreach ( $pending_wds as $w ) {
        sitetop_process_withdrawal($w->id, 'rejected', 'Auto-rejected: user deleted');
    }

    // Only clean up NON-financial data
    // KEEP: transactions, withdrawals, user_balance (financial audit trail)
    $wpdb->delete("{$p}notifications", array('user_id'=>$uid));

    // Soft-delete shortlinks (preserve for financial reference)
    $wpdb->update("{$p}user_shortlinks", array('status'=>'disabled'), array('user_id'=>$uid, 'status'=>'active'));

    // Mark user as deleted in balance table for audit
    update_user_meta($uid, 'sitetop_deleted', 1);
    update_user_meta($uid, 'sitetop_deleted_at', sitetop_current_time());

    wp_delete_user($uid);
    wp_send_json_success(array('message' => 'User deleted. Financial data preserved.'));
}

// Purge file cache (ratelimit + ddos)
add_action('wp_ajax_sitetop_admin_purge_cache', 'sitetop_ajax_admin_purge_cache');
function sitetop_ajax_admin_purge_cache() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $deleted = 0;
    $dirs = array(
        SITETOP_DIR . '/cache/ratelimit/',
        SITETOP_DIR . '/cache/ddos/',
    );
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;
        $dh = @opendir($dir);
        if (!$dh) continue;
        while (($entry = readdir($dh)) !== false) {
            if ($entry[0] === '.') continue;
            if (@unlink($dir . $entry)) $deleted++;
        }
        closedir($dh);
    }
    wp_send_json_success(array('output' => 'Đã xóa ' . number_format($deleted) . ' file cache'));
}

// Run unit tests
add_action('wp_ajax_sitetop_admin_run_tests', 'sitetop_ajax_admin_run_tests');
function sitetop_ajax_admin_run_tests() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $test_file = SITETOP_DIR . '/tests/unit/run.php';
    if (!file_exists($test_file)) wp_send_json_error('Tests not found');
    $output = '';
    if (function_exists('exec')) { exec(PHP_BINARY.' '.escapeshellarg($test_file).' 2>&1', $lines); $output=implode("\n",$lines); }
    elseif (function_exists('shell_exec')) { $output=shell_exec(PHP_BINARY.' '.escapeshellarg($test_file).' 2>&1'); }
    else { ob_start(); include $test_file; $output=ob_get_clean(); }
    wp_send_json_success(array('output'=>$output));
}

// Recreate DB
add_action('wp_ajax_sitetop_admin_recreate_db', 'sitetop_ajax_admin_recreate_db');
function sitetop_ajax_admin_recreate_db() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    sitetop_create_tables();
    wp_send_json_success(array('output' => 'Đã tạo lại bảng DB thành công.'));
}

// ─── Announcements CRUD ───
add_action('wp_ajax_sitetop_admin_get_announcements', 'sitetop_ajax_admin_get_announcements');
function sitetop_ajax_admin_get_announcements() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $rows = $wpdb->get_results("SELECT * FROM {$p}announcements ORDER BY is_pinned DESC, created_at DESC LIMIT 50");
    wp_send_json_success(array('announcements' => $rows));
}

add_action('wp_ajax_sitetop_admin_create_announcement', 'sitetop_ajax_admin_create_announcement');
function sitetop_ajax_admin_create_announcement() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $title   = sanitize_text_field($_POST['title'] ?? '');
    $message = wp_kses_post($_POST['message'] ?? '');
    $target  = in_array($_POST['target'] ?? '', array('all','user','customer')) ? $_POST['target'] : 'all';
    $type    = in_array($_POST['type'] ?? '', array('info','warning','success')) ? $_POST['type'] : 'info';
    $pinned  = absint($_POST['is_pinned'] ?? 0) ? 1 : 0;
    if (empty($title)) wp_send_json_error('Tiêu đề không được để trống');
    if (empty($message)) wp_send_json_error('Nội dung không được để trống');
    $wpdb->insert("{$p}announcements", array(
        'target' => $target, 'type' => $type, 'title' => $title,
        'message' => $message, 'is_pinned' => $pinned,
        'status' => 'active', 'created_at' => sitetop_current_time(),
    ));
    wp_send_json_success(array('id' => $wpdb->insert_id));
}

add_action('wp_ajax_sitetop_admin_update_announcement', 'sitetop_ajax_admin_update_announcement');
function sitetop_ajax_admin_update_announcement() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $id = absint($_POST['id'] ?? 0);
    if (!$id) wp_send_json_error('Missing ID');
    $data = array();
    if (isset($_POST['title']))     $data['title']     = sanitize_text_field($_POST['title']);
    if (isset($_POST['message']))   $data['message']   = wp_kses_post($_POST['message']);
    if (isset($_POST['target']))    $data['target']     = in_array($_POST['target'], array('all','user','customer')) ? $_POST['target'] : 'all';
    if (isset($_POST['type']))      $data['type']       = in_array($_POST['type'], array('info','warning','success')) ? $_POST['type'] : 'info';
    if (isset($_POST['is_pinned'])) $data['is_pinned']  = absint($_POST['is_pinned']) ? 1 : 0;
    if (isset($_POST['status']))    $data['status']     = in_array($_POST['status'], array('active','hidden')) ? $_POST['status'] : 'active';
    if (empty($data)) wp_send_json_error('Nothing to update');
    $wpdb->update("{$p}announcements", $data, array('id' => $id));
    wp_send_json_success();
}

add_action('wp_ajax_sitetop_admin_delete_announcement', 'sitetop_ajax_admin_delete_announcement');
function sitetop_ajax_admin_delete_announcement() {
    check_ajax_referer('sitetop_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;
    $id = absint($_POST['id'] ?? 0);
    if (!$id) wp_send_json_error('Missing ID');
    $wpdb->delete("{$p}announcements", array('id' => $id));
    wp_send_json_success();
}

// Public: get active announcements for dashboard
add_action('wp_ajax_sitetop_get_announcements', 'sitetop_ajax_get_announcements');
add_action('wp_ajax_nopriv_sitetop_get_announcements', 'sitetop_ajax_get_announcements');
function sitetop_ajax_get_announcements() {
    check_ajax_referer('sitetop_nonce', 'nonce');
    global $wpdb; $p = $wpdb->prefix . SITETOP_PREFIX;

    // Check if table exists
    $table = $p . 'announcements';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
        wp_send_json_success(array('announcements' => array()));
        return;
    }

    $target = sanitize_text_field($_POST['target'] ?? 'user');
    if (!in_array($target, array('user','customer'))) $target = 'user';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, type, title, message, is_pinned, created_at FROM {$p}announcements
         WHERE status='active' AND target IN ('all', %s)
         ORDER BY is_pinned DESC, created_at DESC LIMIT 10", $target
    ));
    wp_send_json_success(array('announcements' => $rows));
}

