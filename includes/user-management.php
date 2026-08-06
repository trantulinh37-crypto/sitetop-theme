<?php
/**
 * SiteTop.net V2 - User Management
 * Ban/unban, notifications, inactive cleanup
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Block banned accounts from logging in */
add_filter('wp_authenticate_user', 'sitetop_block_banned_login', 30, 2);
function sitetop_block_banned_login($user, $password) {
    if (is_wp_error($user) || !($user instanceof WP_User)) return $user;
    if (get_user_meta($user->ID, 'sitetop_banned', true)) {
        return new WP_Error('sitetop_banned',
            '<strong>Tài khoản đã bị cấm.</strong> Vui lòng liên hệ quản trị viên.');
    }
    if (get_user_meta($user->ID, 'customer_banned', true)) {
        return new WP_Error('sitetop_customer_banned',
            '<strong>Tài khoản đã bị cấm.</strong> Vui lòng liên hệ quản trị viên.');
    }
    return $user;
}

/** Ban user - auto reject pending/approved withdrawals */
function sitetop_ban_user( $user_id ) {
    global $wpdb;
    $p = $wpdb->prefix . SITETOP_PREFIX;

    update_user_meta($user_id, 'sitetop_banned', 1);
    update_user_meta($user_id, 'sitetop_banned_at', sitetop_current_time());

    // Reject all pending/approved withdrawals + refund
    $pending = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$p}withdrawals WHERE user_id=%d AND status IN ('pending','approved')", $user_id ));
    foreach ( $pending as $w ) {
        sitetop_process_withdrawal($w->id, 'rejected', 'Auto-rejected: user banned');
    }
    return true;
}

function sitetop_unban_user( $user_id ) {
    delete_user_meta($user_id, 'sitetop_banned');
    delete_user_meta($user_id, 'sitetop_banned_at');
    return true;
}

/** Create notification (XSS sanitized) */
function sitetop_create_notification( $user_id, $type, $title, $message, $data = array() ) {
    global $wpdb;
    $p = $wpdb->prefix . SITETOP_PREFIX;

    $wpdb->insert("{$p}notifications", array(
        'user_id'=>$user_id, 'type'=>sanitize_text_field($type),
        'title'=>sanitize_text_field($title), 'message'=>wp_kses_post($message),
        'data'=>wp_json_encode($data), 'is_read'=>0, 'created_at'=>sitetop_current_time(),
    ));
    return $wpdb->insert_id;
}

function sitetop_get_user_notifications( $user_id, $unread_only = false, $limit = 20 ) {
    global $wpdb;
    $p = $wpdb->prefix . SITETOP_PREFIX;
    $where = $unread_only ? 'AND is_read = 0' : '';
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$p}notifications WHERE user_id=%d {$where} ORDER BY created_at DESC LIMIT %d", $user_id, $limit ));
}

function sitetop_mark_all_notifications_read( $user_id ) {
    global $wpdb;
    $p = $wpdb->prefix . SITETOP_PREFIX;
    $wpdb->update("{$p}notifications", array('is_read'=>1), array('user_id'=>$user_id, 'is_read'=>0));
}

/** Cleanup inactive users - preserves all financial data */
function sitetop_cleanup_inactive_users() {
    global $wpdb;
    $p = $wpdb->prefix . SITETOP_PREFIX;
    $days = (int) sitetop_get_option('inactive_user_days', 10);
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days", strtotime(sitetop_current_time())));

    // Only delete users with ZERO financial activity (no transactions at all, no withdrawals)
    $users = $wpdb->get_results( $wpdb->prepare(
        "SELECT u.ID FROM {$wpdb->users} u
         LEFT JOIN {$p}transactions t ON u.ID = t.user_id
         LEFT JOIN {$p}withdrawals w ON u.ID = w.user_id
         WHERE u.user_registered < %s AND t.id IS NULL AND w.id IS NULL
         AND u.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'sitetop_banned')
         AND u.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'sitetop_deleted')
         AND u.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '{$wpdb->prefix}capabilities' AND meta_value LIKE '%administrator%')
         AND u.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '{$wpdb->prefix}capabilities' AND meta_value LIKE '%customer%')
         AND NOT EXISTS (SELECT 1 FROM {$p}shortlink_visits sv WHERE sv.user_id = u.ID AND sv.reward_paid = 1)
         AND NOT EXISTS (SELECT 1 FROM {$p}user_balance ub WHERE ub.user_id = u.ID AND (ub.total_earned > 0 OR ub.balance > 0))",
        $cutoff ));

    foreach ( $users as $u ) {
        // Double-check balance
        $balance = sitetop_get_user_balance_amount($u->ID);
        if ( $balance <= 0 ) {
            // Clean up non-financial data only
            $wpdb->delete("{$p}notifications", array('user_id'=>$u->ID));
            wp_delete_user($u->ID);
        }
    }
}
