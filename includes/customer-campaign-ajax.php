<?php
/**
 * AJAX: Customer campaign CRUD (create, toggle, get detail, edit, delete)
 * + Edit shortlink, get link visits, reset API token, update profile, change password
 * Tách từ functions.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Block a banned customer from mutating campaigns/deposits. Mirrors the withdrawal-side
 * traffictop_banned enforcement, which the customer campaign/deposit handlers were missing.
 * Emits a JSON error and halts when the user is banned (customer or user level).
 */
function traffictop_block_banned_customer( $user_id ) {
    if ( get_user_meta( $user_id, 'customer_banned', true ) || get_user_meta( $user_id, 'traffictop_banned', true ) ) {
        wp_send_json_error( 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.' );
    }
}

/* ============================================================
   AJAX: Customer Create Campaign
   ============================================================ */
add_action( 'wp_ajax_traffictop_customer_create_campaign', function() {
    check_ajax_referer( 'traffictop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    $user_id = get_current_user_id();
    // Admin can create for another customer
    $admin_cust_id = intval( $_POST['admin_customer_id'] ?? 0 );
    if ( $admin_cust_id && current_user_can( 'manage_options' ) ) {
        $user_id = $admin_cust_id;
    }
    global $wpdb;
    $prefix = $wpdb->prefix . 'traffictop_';

    $is_admin_create = ( $admin_cust_id && current_user_can( 'manage_options' ) );
    if ( ! $is_admin_create ) {
        // B1: banned customers cannot create campaigns.
        traffictop_block_banned_customer( $user_id );
        // B2: throttle creation (per-customer) + cap pending queue to prevent spam/DB flood.
        $rl = traffictop_rate_limit_check( 'create_campaign', 'cust_' . $user_id );
        if ( empty( $rl['allowed'] ) ) wp_send_json_error( 'Bạn tạo chiến dịch quá nhanh, vui lòng thử lại sau.' );
        $pending_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}keyword_campaigns WHERE customer_id=%d AND status='pending'", $user_id
        ) );
        if ( $pending_count >= 30 ) wp_send_json_error( 'Bạn có quá nhiều chiến dịch đang chờ duyệt. Vui lòng đợi admin xử lý.' );
    }

    $task_type    = sanitize_text_field( $_POST['task_type'] ?? 'keyword_search' );
    $keyword      = sanitize_text_field( $_POST['keyword'] ?? '' );
    $target_url   = esc_url_raw( $_POST['target_url'] ?? '' );
    $title        = sanitize_text_field( $_POST['title'] ?? '' );
    $traffic_type = sanitize_text_field( $_POST['traffic_type'] ?? '1step' );
    $onsite_time  = intval( $_POST['onsite_time'] ?? 70 );
    $daily_traffic = max( 10, min( 5000, intval( $_POST['daily_traffic'] ?? 100 ) ) );
    $days         = max( 1, min( 90, intval( $_POST['days'] ?? 15 ) ) );
    $quantity     = $daily_traffic * $days;

    if ( empty( $target_url ) ) wp_send_json_error( 'Vui lòng nhập URL' );
    if ( $task_type === 'keyword_search' && empty( $keyword ) ) wp_send_json_error( 'Vui lòng nhập từ khóa' );
    if ( $traffic_type === 'nocode' && empty( $_POST['fixed_code'] ) ) wp_send_json_error( 'Vui lòng nhập mã xác nhận cố định' );
    if ( $traffic_type === 'nocode' && empty( $_POST['nocode_screenshot_url'] ) ) wp_send_json_error( 'Vui lòng tải ảnh mô tả vị trí mã cố định' );
    if ( empty( $title ) ) $title = $keyword ?: parse_url( $target_url, PHP_URL_HOST );

    // Check customer balance
    $min_balance = floatval( traffictop_get_option( 'customer_min_balance', 20000 ) );
    if ( function_exists( 'traffictop_get_customer_balance_amount' ) ) {
        $balance = traffictop_get_customer_balance_amount( $user_id );
        // M-failopen: treat a balance-lookup error (false) as "cannot verify → reject", NOT skip.
        if ( $balance === false ) {
            wp_send_json_error( 'Không thể xác minh số dư, vui lòng thử lại sau.' );
        }
        if ( $balance < $min_balance ) {
            wp_send_json_error( 'Số dư không đủ. Yêu cầu tối thiểu ' . traffictop_format_money( $min_balance ) );
        }
    }

    // Get price
    $price_key = '';
    if ( $task_type === 'keyword_search' ) $price_key = 'keyword_price_' . $traffic_type;
    else $price_key = 'direct_price_' . $traffic_type;
    $price_per_view = floatval( traffictop_get_option( $price_key, 1200 ) );

    // Onsite extra cost
    $onsite_extra = array(70=>(int)traffictop_get_option('onsite_extra_70',0),80=>(int)traffictop_get_option('onsite_extra_80',100),90=>(int)traffictop_get_option('onsite_extra_90',200),100=>(int)traffictop_get_option('onsite_extra_100',300),120=>(int)traffictop_get_option('onsite_extra_120',400),150=>(int)traffictop_get_option('onsite_extra_150',500));
    $price_per_view += $onsite_extra[ $onsite_time ] ?? 0;

    // User reward (base + onsite extra for user)
    $reward_key = ($task_type === 'keyword_search') ? 'keyword_user_' : 'direct_user_';
    $user_reward_base = floatval( traffictop_get_option( $reward_key . $traffic_type, 800 ) );
    $user_onsite_extra = array(70=>(int)traffictop_get_option('user_onsite_extra_70',0),80=>(int)traffictop_get_option('user_onsite_extra_80',0),90=>(int)traffictop_get_option('user_onsite_extra_90',0),100=>(int)traffictop_get_option('user_onsite_extra_100',0),120=>(int)traffictop_get_option('user_onsite_extra_120',0),150=>(int)traffictop_get_option('user_onsite_extra_150',0));
    $user_reward = $user_reward_base + ($user_onsite_extra[$onsite_time] ?? 0);

    // Create order
    $wpdb->insert( $prefix . 'customer_orders', array(
        'customer_id'       => $user_id,
        'customer_username' => wp_get_current_user()->user_login,
        'task_type'         => $task_type,
        'title'             => $title,
        'task_url'          => $target_url,
        'quantity'          => $quantity,
        'completed'         => 0,
        'price_per_task'    => $price_per_view,
        'total_amount'      => $price_per_view * $quantity,
        'amount_spent'      => 0,
        'status'            => 'pending',
        'created_at'        => traffictop_current_time(),
        'updated_at'        => traffictop_current_time(),
    ));
    $order_id = $wpdb->insert_id;
    if ( ! $order_id ) wp_send_json_error( 'Lỗi tạo đơn hàng' );

    // Screenshot URLs (already uploaded to ImgBB via AJAX)
    $screenshot_desktop_url = esc_url_raw( $_POST['screenshot_desktop_url'] ?? '' );
    $screenshot_mobile_url  = esc_url_raw( $_POST['screenshot_mobile_url'] ?? '' );
    $nocode_screenshot_url  = esc_url_raw( $_POST['nocode_screenshot_url'] ?? '' );

    // Create campaign
    $wpdb->insert( $prefix . 'keyword_campaigns', array(
        'customer_id'            => $user_id,
        'order_id'               => $order_id,
        'title'                  => $title,
        'keyword'                => $keyword,
        'target_url'             => $target_url,
        'traffic_type'           => $traffic_type,
        'campaign_type'          => $task_type,
        'onsite_time'            => $onsite_time,
        'quantity'               => $quantity,
        'completed'              => 0,
        'price_per_view'         => $price_per_view,
        'user_reward'            => $user_reward,
        'daily_traffic'          => $daily_traffic,
        'fixed_code'             => ( $traffic_type === 'nocode' ) ? sanitize_text_field( $_POST['fixed_code'] ?? '' ) : null,
        'screenshot_desktop_url' => $screenshot_desktop_url,
        'screenshot_mobile_url'  => $screenshot_mobile_url,
        'nocode_screenshot_url'  => $nocode_screenshot_url,
        'status'                 => 'pending',
        'created_at'             => traffictop_current_time(),
        'updated_at'             => traffictop_current_time(),
    ));

    $new_campaign_id = (int) $wpdb->insert_id;
    if ( ! $new_campaign_id ) wp_send_json_error( 'Lỗi tạo chiến dịch' );

    // Thông báo admin (Telegram nếu bật, ngược lại email) — đây là đường tạo campaign của KHÁCH
    // qua dashboard; trước đây KHÔNG gọi notify nên admin "im lặng không biết" (lesson #4).
    if ( function_exists( 'traffictop_send_new_campaign_email' ) ) {
        traffictop_send_new_campaign_email( $new_campaign_id );
    }

    delete_transient( 'traffictop_eligible_campaigns' );
    wp_send_json_success( 'Chiến dịch đã được tạo thành công' );
});

/* ============================================================
   AJAX: Customer Toggle Campaign (pause/resume)
   ============================================================ */
add_action( 'wp_ajax_traffictop_customer_toggle_campaign', function() {
    check_ajax_referer( 'traffictop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    global $wpdb;
    $prefix      = $wpdb->prefix . 'traffictop_';
    $user_id     = get_current_user_id();
    traffictop_block_banned_customer( $user_id );
    $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
    $new_status  = sanitize_text_field( $_POST['status'] ?? '' );

    if ( ! $campaign_id ) wp_send_json_error( 'Thiếu campaign ID' );
    if ( ! in_array( $new_status, array( 'active', 'paused' ) ) ) wp_send_json_error( 'Trạng thái không hợp lệ' );

    $campaign = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$prefix}keyword_campaigns WHERE id=%d AND customer_id=%d", $campaign_id, $user_id
    ) );
    if ( ! $campaign ) wp_send_json_error( 'Chiến dịch không tồn tại' );

    // Only allow toggle between active <-> paused
    if ( $new_status === 'active' && $campaign->status !== 'paused' ) wp_send_json_error( 'Chỉ có thể tiếp tục chiến dịch đang tạm dừng' );
    if ( $new_status === 'paused' && $campaign->status !== 'active' ) wp_send_json_error( 'Chỉ có thể tạm dừng chiến dịch đang chạy' );

    // If resuming, check customer balance
    if ( $new_status === 'active' && function_exists( 'traffictop_get_customer_balance_amount' ) ) {
        $balance = traffictop_get_customer_balance_amount( $user_id );
        $min_balance = floatval( traffictop_get_option( 'customer_min_balance', 20000 ) );
        $required = $min_balance + max( floatval( $campaign->price_per_view ), 5000 );
        if ( $balance === false ) {
            wp_send_json_error( 'Không thể xác minh số dư, vui lòng thử lại sau.' );
        }
        if ( $balance <= $required ) {
            wp_send_json_error( 'Số dư không đủ để tiếp tục chiến dịch. Cần tối thiểu ' . traffictop_format_money( $required ) );
        }
    }

    $wpdb->update( $prefix . 'keyword_campaigns', array( 'status' => $new_status ), array( 'id' => $campaign_id ) );
    // Sync order status
    if ( $campaign->order_id ) {
        $wpdb->update( $prefix . 'customer_orders', array( 'status' => $new_status ), array( 'id' => $campaign->order_id ) );
    }

    $label = $new_status === 'paused' ? 'Đã tạm dừng chiến dịch' : 'Đã tiếp tục chiến dịch';
    wp_send_json_success( $label );
});

/* ============================================================
   AJAX: Customer Get Campaign Detail
   ============================================================ */
add_action( 'wp_ajax_traffictop_customer_get_campaign', function() {
    check_ajax_referer( 'traffictop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    global $wpdb;
    $prefix      = $wpdb->prefix . 'traffictop_';
    $user_id     = get_current_user_id();
    $campaign_id = absint( $_POST['campaign_id'] ?? 0 );

    $c = $wpdb->get_row( $wpdb->prepare(
        "SELECT kc.*, co.task_type FROM {$prefix}keyword_campaigns kc
         LEFT JOIN {$prefix}customer_orders co ON kc.order_id = co.id
         WHERE kc.id=%d AND kc.customer_id=%d", $campaign_id, $user_id
    ) );
    if ( ! $c ) wp_send_json_error( 'Không tìm thấy chiến dịch' );

    $today = date( 'Y-m-d', strtotime( traffictop_current_time() ) );
    $today_views = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=%d AND step='verified' AND DATE(created_at)=%s", $campaign_id, $today
    ) );
    $total_completed = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=%d AND step='verified'", $campaign_id
    ) );

    wp_send_json_success( array(
        'id'              => $c->id,
        'title'           => $c->title,
        'keyword'         => $c->keyword,
        'target_url'      => $c->target_url,
        'task_type'       => $c->task_type ?? 'keyword_search',
        'traffic_type'    => $c->traffic_type,
        'onsite_time'     => $c->onsite_time,
        'price_per_view'  => $c->price_per_view,
        'daily_traffic'   => $c->daily_traffic,
        'quantity'        => $c->quantity,
        'completed'       => $total_completed,
        'today_views'     => $today_views,
        'status'          => $c->status,
        'screenshot_desktop_url' => $c->screenshot_desktop_url,
        'screenshot_mobile_url'  => $c->screenshot_mobile_url,
        'created_at'      => $c->created_at,
        'reject_reason'   => $c->reject_reason,
        'fixed_code'      => $c->fixed_code,
    ) );
});

/* ============================================================
   AJAX: Customer Edit Campaign
   ============================================================ */
add_action( 'wp_ajax_traffictop_customer_edit_campaign', function() {
    check_ajax_referer( 'traffictop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    global $wpdb;
    $prefix      = $wpdb->prefix . 'traffictop_';
    $user_id     = get_current_user_id();
    traffictop_block_banned_customer( $user_id );
    $campaign_id = absint( $_POST['campaign_id'] ?? 0 );

    if ( ! $campaign_id ) wp_send_json_error( 'Thiếu campaign ID' );

    $campaign = $wpdb->get_row( $wpdb->prepare(
        "SELECT kc.*, co.task_type FROM {$prefix}keyword_campaigns kc
         LEFT JOIN {$prefix}customer_orders co ON co.id = kc.order_id
         WHERE kc.id=%d AND kc.customer_id=%d", $campaign_id, $user_id
    ) );
    if ( ! $campaign ) wp_send_json_error( 'Chiến dịch không tồn tại' );
    if ( ! in_array( $campaign->status, array( 'pending', 'active', 'paused' ) ) ) {
        wp_send_json_error( 'Không thể chỉnh sửa chiến dịch ở trạng thái này' );
    }

    $data = array( 'updated_at' => traffictop_current_time() );
    $needs_reapproval = false;

    $task_type = $campaign->task_type ?? 'keyword_search';

    // Fields that require re-approval
    if ( isset( $_POST['keyword'] ) ) {
        $new_keyword = sanitize_text_field( $_POST['keyword'] );
        if ( $task_type === 'keyword_search' && trim( $new_keyword ) === '' ) {
            wp_send_json_error( 'Từ khóa không được để trống' );
        }
        if ( $new_keyword !== ( $campaign->keyword ?? '' ) ) { $needs_reapproval = true; }
        $data['keyword'] = $new_keyword;
    }
    if ( isset( $_POST['target_url'] ) ) {
        $url = esc_url_raw( $_POST['target_url'] );
        if ( empty( $url ) ) wp_send_json_error( 'URL không hợp lệ' );
        if ( $url !== ( $campaign->target_url ?? '' ) ) { $needs_reapproval = true; }
        $data['target_url'] = $url;
    }
    if ( isset( $_POST['title'] ) ) {
        $new_title = sanitize_text_field( $_POST['title'] );
        if ( $new_title !== ( $campaign->title ?? '' ) ) { $needs_reapproval = true; }
        $data['title'] = $new_title;
    }
    if ( isset( $_POST['traffic_type'] ) ) {
        $new_tt = sanitize_text_field( $_POST['traffic_type'] );
        if ( in_array( $new_tt, array( '1step', '2step', 'nocode' ) ) ) {
            if ( $new_tt !== ( $campaign->traffic_type ?? '1step' ) ) { $needs_reapproval = true; }
            $data['traffic_type'] = $new_tt;
        }
    }
    if ( isset( $_POST['onsite_time'] ) ) {
        $new_os = intval( $_POST['onsite_time'] );
        $allowed_os = array( 70, 80, 90, 100, 120, 150 );
        if ( in_array( $new_os, $allowed_os ) ) {
            if ( $new_os !== intval( $campaign->onsite_time ?? 70 ) ) { $needs_reapproval = true; }
            $data['onsite_time'] = $new_os;
        }
    }

    // Daily traffic — đổi quota → bắt admin duyệt lại (chống lách quota)
    if ( isset( $_POST['daily_traffic'] ) ) {
        $new_dt = max( 1, min( 5000, intval( $_POST['daily_traffic'] ) ) );
        if ( $new_dt !== intval( $campaign->daily_traffic ?? 0 ) ) { $needs_reapproval = true; }
        $data['daily_traffic'] = $new_dt;
    }

    // Screenshot URLs (already uploaded to ImgBB via AJAX) require re-approval
    foreach ( array( 'screenshot_desktop_url', 'screenshot_mobile_url' ) as $col ) {
        if ( ! empty( $_POST[ $col ] ) ) {
            $data[ $col ] = esc_url_raw( $_POST[ $col ] );
            $needs_reapproval = true;
        }
    }

    // Recalculate price if traffic_type or onsite_time changed
    $traffic_type = $data['traffic_type'] ?? $campaign->traffic_type ?? '1step';
    $onsite_time  = $data['onsite_time'] ?? intval( $campaign->onsite_time ?? 70 );

    $price_key = ( $task_type === 'keyword_search' ) ? 'keyword_price_' : 'direct_price_';
    $price_per_view = floatval( traffictop_get_option( $price_key . $traffic_type, 1200 ) );
    $onsite_extra = array(70=>(int)traffictop_get_option('onsite_extra_70',0),80=>(int)traffictop_get_option('onsite_extra_80',100),90=>(int)traffictop_get_option('onsite_extra_90',200),100=>(int)traffictop_get_option('onsite_extra_100',300),120=>(int)traffictop_get_option('onsite_extra_120',400),150=>(int)traffictop_get_option('onsite_extra_150',500));
    $price_per_view += $onsite_extra[ $onsite_time ] ?? 0;

    $reward_key2 = ($task_type === 'keyword_search') ? 'keyword_user_' : 'direct_user_';
    $user_reward_base2 = floatval( traffictop_get_option( $reward_key2 . $traffic_type, 800 ) );
    $user_onsite_extra2 = array(70=>(int)traffictop_get_option('user_onsite_extra_70',0),80=>(int)traffictop_get_option('user_onsite_extra_80',0),90=>(int)traffictop_get_option('user_onsite_extra_90',0),100=>(int)traffictop_get_option('user_onsite_extra_100',0),120=>(int)traffictop_get_option('user_onsite_extra_120',0),150=>(int)traffictop_get_option('user_onsite_extra_150',0));
    $user_reward = $user_reward_base2 + ($user_onsite_extra2[$onsite_time] ?? 0);

    $data['price_per_view'] = $price_per_view;
    $data['user_reward']    = $user_reward;

    // Set to pending if significant changes
    if ( $needs_reapproval && $campaign->status !== 'pending' ) {
        $data['status'] = 'pending';
    }

    $wpdb->update( $prefix . 'keyword_campaigns', $data, array( 'id' => $campaign_id ) );

    // Sync order
    if ( $campaign->order_id ) {
        $order_data = array( 'updated_at' => traffictop_current_time(), 'price_per_task' => $price_per_view );
        if ( isset( $data['title'] ) )      $order_data['title']    = $data['title'];
        if ( isset( $data['target_url'] ) )  $order_data['task_url'] = $data['target_url'];
        if ( isset( $data['status'] ) )      $order_data['status']   = $data['status'];
        $wpdb->update( $prefix . 'customer_orders', $order_data, array( 'id' => $campaign->order_id ) );
    }

    delete_transient( 'traffictop_eligible_campaigns' );
    $msg = $needs_reapproval && $campaign->status !== 'pending'
        ? 'Đã cập nhật. Chiến dịch chuyển về Chờ duyệt.'
        : 'Đã cập nhật chiến dịch';
    wp_send_json_success( $msg );
});

/* ============================================================
   AJAX: Customer Delete Campaign (only paused)
   ============================================================ */
add_action( 'wp_ajax_traffictop_customer_delete_campaign', function() {
    check_ajax_referer( 'traffictop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    global $wpdb;
    $prefix      = $wpdb->prefix . 'traffictop_';
    $user_id     = get_current_user_id();
    $campaign_id = absint( $_POST['campaign_id'] ?? 0 );

    if ( ! $campaign_id ) wp_send_json_error( 'Thiếu campaign ID' );

    $campaign = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$prefix}keyword_campaigns WHERE id=%d AND customer_id=%d", $campaign_id, $user_id
    ) );
    if ( ! $campaign ) wp_send_json_error( 'Chiến dịch không tồn tại' );
    if ( $campaign->status !== 'paused' ) wp_send_json_error( 'Chỉ có thể xóa chiến dịch đang tạm dừng' );

    // Soft delete - preserve for financial audit trail
    $now = traffictop_current_time();
    $wpdb->update( $prefix . 'keyword_campaigns', array( 'status' => 'deleted', 'updated_at' => $now ), array( 'id' => $campaign_id ) );
    if ( $campaign->order_id ) {
        $wpdb->update( $prefix . 'customer_orders', array( 'status' => 'deleted', 'updated_at' => $now ), array( 'id' => $campaign->order_id ) );
    }
    delete_transient( 'traffictop_eligible_campaigns' );
    wp_send_json_success( 'Đã xóa chiến dịch' );
});

/* ============================================================
   AJAX: Edit Shortlink
   ============================================================ */
add_action( 'wp_ajax_traffictop_edit_shortlink', function() {
    check_ajax_referer( 'traffictop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    global $wpdb;
    $prefix  = $wpdb->prefix . 'traffictop_';
    $link_id = intval( $_POST['link_id'] ?? 0 );
    $user_id = get_current_user_id();

    $link = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prefix}user_shortlinks WHERE id=%d AND user_id=%d", $link_id, $user_id ) );
    if ( ! $link ) wp_send_json_error( 'Link không tồn tại' );

    $data = array();
    if ( isset( $_POST['url'] ) ) $data['original_url'] = esc_url_raw( $_POST['url'] );
    if ( isset( $_POST['fallback_url'] ) ) $data['fallback_url'] = esc_url_raw( $_POST['fallback_url'] );
    if ( isset( $_POST['alias'] ) ) {
        $alias = sanitize_title( $_POST['alias'] );
        if ( $alias && $alias !== $link->alias ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$prefix}user_shortlinks WHERE alias=%s AND id!=%d", $alias, $link_id ) );
            if ( $exists ) wp_send_json_error( 'Bí danh đã tồn tại' );
            $data['alias'] = $alias;
        } elseif ( empty( $_POST['alias'] ) ) {
            $data['alias'] = null;
        }
    }

    if ( ! empty( $data ) ) {
        $wpdb->update( $prefix . 'user_shortlinks', $data, array( 'id' => $link_id ) );
    }
    wp_send_json_success( 'Đã cập nhật' );
});

/* ============================================================
   AJAX: Get Link Visits
   ============================================================ */
add_action( 'wp_ajax_traffictop_get_link_visits', function() {
    check_ajax_referer( 'traffictop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    global $wpdb;
    $prefix  = $wpdb->prefix . 'traffictop_';
    $link_id = intval( $_POST['link_id'] ?? 0 );
    $user_id = get_current_user_id();

    $link = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prefix}user_shortlinks WHERE id=%d AND user_id=%d", $link_id, $user_id ) );
    if ( ! $link ) wp_send_json_error( 'Link không tồn tại' );

    $visits = $wpdb->get_results( $wpdb->prepare(
        "SELECT v.created_at, v.ip_address, v.user_agent, v.step, v.reward_paid, v.reward_amount
         FROM {$prefix}shortlink_visits v WHERE v.shortlink_id=%d AND v.reward_paid=1 ORDER BY v.created_at DESC LIMIT 20", $link_id
    ) );

    if ( empty( $visits ) ) {
        wp_send_json_success( array( 'html' => '' ) );
    }

    $html = '<div style="font-size:12px;color:#6B7280;margin-bottom:8px"><strong>' . count( $visits ) . '</strong> lượt gần nhất</div>';
    $html .= '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:12px;white-space:nowrap"><thead><tr style="background:#F7F5F0">';
    $html .= '<th style="padding:8px;text-align:left;white-space:nowrap">Thời gian</th>';
    $html .= '<th style="padding:8px;white-space:nowrap">IP</th>';
    $html .= '<th style="padding:8px;white-space:nowrap">Thiết bị</th>';
    $html .= '<th style="padding:8px;white-space:nowrap">Kết quả</th>';
    $html .= '<th style="padding:8px;white-space:nowrap">Tiền</th>';
    $html .= '</tr></thead><tbody>';
    foreach ( $visits as $v ) {
        $ua = $v->user_agent ?? '';
        $device = 'Unknown';
        if ( stripos($ua,'Android') !== false ) $device = 'Android';
        elseif ( stripos($ua,'iPhone') !== false ) $device = 'iPhone';
        elseif ( stripos($ua,'Windows') !== false ) $device = 'Windows';
        elseif ( stripos($ua,'Mac') !== false ) $device = 'macOS';

        $html .= '<tr style="border-bottom:1px solid #F0EDE6">';
        $html .= '<td style="padding:8px;white-space:nowrap">' . date('d/m H:i', strtotime($v->created_at)) . '</td>';
        $html .= '<td style="padding:8px;white-space:nowrap"><code style="font-size:10px">' . esc_html( substr($v->ip_address, 0, 20) ) . '</code></td>';
        $html .= '<td style="padding:8px;white-space:nowrap">' . esc_html($device) . '</td>';
        $html .= '<td style="padding:8px;white-space:nowrap"><span style="color:#059669;font-weight:600;white-space:nowrap">Hoàn thành</span></td>';
        $html .= '<td style="padding:8px;white-space:nowrap"><span style="color:#059669;font-weight:600;white-space:nowrap">+' . traffictop_format_money($v->reward_amount) . '</span></td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table></div>';

    wp_send_json_success( array( 'html' => $html ) );
});

/* ============================================================
   AJAX: Reset API Token
   ============================================================ */
add_action( 'wp_ajax_traffictop_reset_api_token', function() {
    check_ajax_referer( 'traffictop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    $token = wp_generate_password( 24, false );
    update_user_meta( get_current_user_id(), 'traffictop_api_token', $token );
    wp_send_json_success( array( 'token' => $token ) );
});

/* ============================================================
   AJAX: Update Profile (email + phone)
   ============================================================ */
add_action( 'wp_ajax_traffictop_update_profile', function() {
    check_ajax_referer( 'traffictop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    $user_id = get_current_user_id();
    $email   = sanitize_email( $_POST['email'] ?? '' );
    $phone   = sanitize_text_field( $_POST['phone'] ?? '' );

    if ( empty( $email ) || ! is_email( $email ) ) {
        wp_send_json_error( 'Email không hợp lệ' );
    }

    // Check email uniqueness
    $existing = email_exists( $email );
    if ( $existing && $existing !== $user_id ) {
        wp_send_json_error( 'Email đã được sử dụng bởi tài khoản khác' );
    }

    wp_update_user( array( 'ID' => $user_id, 'user_email' => $email ) );
    update_user_meta( $user_id, 'phone', $phone );

    wp_send_json_success( 'Cập nhật thành công' );
});

/* ============================================================
   AJAX: Change Password
   ============================================================ */
add_action( 'wp_ajax_traffictop_change_password', function() {
    check_ajax_referer( 'traffictop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    $user = wp_get_current_user();
    $current  = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
        wp_send_json_error( 'Mật khẩu hiện tại không đúng' );
    }
    if ( strlen( $new_pass ) < 6 ) {
        wp_send_json_error( 'Mật khẩu mới tối thiểu 6 ký tự' );
    }
    if ( $new_pass !== $confirm ) {
        wp_send_json_error( 'Mật khẩu xác nhận không khớp' );
    }

    wp_set_password( $new_pass, $user->ID );
    // Re-login after password change
    wp_set_auth_cookie( $user->ID );

    wp_send_json_success( 'Đổi mật khẩu thành công' );
});
