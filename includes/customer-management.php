<?php
/**
 * SiteTop.net V2 - Customer Management
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function sitetop_ban_customer( $user_id ) {
    update_user_meta($user_id, 'sitetop_customer_banned', 1);
    sitetop_auto_pause_customer_campaigns($user_id);
    return true;
}

function sitetop_unban_customer( $user_id ) {
    delete_user_meta($user_id, 'sitetop_customer_banned');
    return true;
}

/** Admin impersonation */
function sitetop_login_as_customer( $customer_id ) {
    if ( !current_user_can('manage_options') ) return false;
    $admin_id = get_current_user_id();
    update_user_meta($customer_id, 'switch_from_admin', $admin_id);
    wp_set_auth_cookie($customer_id);
    return true;
}

/** Delete customer - preserve all financial data */
function sitetop_permanent_delete_customer( $customer_id ) {
    global $wpdb;
    $p = $wpdb->prefix . SITETOP_PREFIX;

    // Soft-delete campaigns and orders (NOT hard delete)
    $now = sitetop_current_time();
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}keyword_campaigns SET status='deleted', updated_at=%s WHERE customer_id=%d AND status != 'deleted'",
        $now, $customer_id ));
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}customer_orders SET status='deleted', updated_at=%s WHERE customer_id=%d AND status != 'deleted'",
        $now, $customer_id ));

    // Invalidate campaign cache
    delete_transient('sitetop_eligible_campaigns');

    // KEEP: customer_transactions, customer_deposits, customer_balance, shortlink_visits
    // These are financial audit trail and MUST be preserved

    // Mark customer as deleted
    update_user_meta($customer_id, 'sitetop_customer_deleted', 1);
    update_user_meta($customer_id, 'sitetop_customer_deleted_at', $now);

    return true;
}

function sitetop_auto_delete_old_customers() {
    // Placeholder - implement based on business rules
}

/**
 * SỐ VIEW HỢP LỆ CỦA MỘT CHIẾN DỊCH TRONG MỘT NGÀY — khu vực khách hàng (25/09/2026).
 *
 * Dùng ĐÚNG công thức thống kê sẵn có của khu vực khách, không tự nghĩ công thức mới:
 * `(step='verified' OR customer_paid=1)` lọc theo `DATE(created_at)` — y hệt ô "Hôm nay"
 * trong bảng chiến dịch (page-customer-dashboard.php) và ô today_views của
 * sitetop_customer_get_campaign. Nhờ vậy số ở bộ lọc mới không bao giờ lệch với số khách
 * vẫn thấy hằng ngày.
 *
 * CHỦ QUYỀN: chỉ đếm khi chiến dịch THUỘC ĐÚNG khách đang đăng nhập. Thiếu chốt này thì
 * khách A gõ ID camp của khách B là xem được lưu lượng của người khác.
 *
 * CAMP ĐÃ XOÁ THÌ KHÔNG TRA LẠI ĐƯỢC (chủ site chốt 25/09/2026): xoá là biến mất khỏi khu vực
 * khách, kể cả phần thống kê. Chốt đặt ngay trong câu hỏi chủ quyền chứ không chỉ ẩn ở ô chọn —
 * ẩn ngoài giao diện thì gõ tay ?ck_camp=<id đã xoá> là lại xem được.
 *
 * @return int|null  null = ngày sai định dạng, camp không phải của khách này, hoặc camp đã xoá.
 */
function sitetop_customer_camp_views_ngay( $customer_id, $campaign_id, $ngay ) {
    global $wpdb;
    $prefix      = $wpdb->prefix . 'sitetop_';
    $customer_id = (int) $customer_id;
    $campaign_id = (int) $campaign_id;
    $ngay        = trim( (string) $ngay );
    if ( $customer_id <= 0 || $campaign_id <= 0 ) return null;

    // Ngày phải đúng dạng Y-m-d VÀ có thật: '2026-02-31' đúng dạng nhưng không tồn tại.
    $d = DateTime::createFromFormat( 'Y-m-d', $ngay );
    if ( ! $d || $d->format( 'Y-m-d' ) !== $ngay ) return null;

    $cua_minh = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}keyword_campaigns WHERE id = %d AND customer_id = %d AND status != 'deleted'",
        $campaign_id, $customer_id ) );
    if ( $cua_minh < 1 ) return null;

    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}shortlink_visits
          WHERE campaign_id = %d AND (step='verified' OR customer_paid=1) AND DATE(created_at) = %s",
        $campaign_id, $ngay ) );
}
