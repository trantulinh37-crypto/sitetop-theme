<?php
/**
 * Traffictop.net V2 - User Management
 * Ban/unban, notifications, inactive cleanup
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Ban user - auto reject pending/approved withdrawals */
function traffictop_ban_user( $user_id ) {
    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;

    update_user_meta($user_id, 'traffictop_banned', 1);
    update_user_meta($user_id, 'traffictop_banned_at', traffictop_current_time());

    // Reject all pending/approved withdrawals + refund
    $pending = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$p}withdrawals WHERE user_id=%d AND status IN ('pending','approved')", $user_id ));
    foreach ( $pending as $w ) {
        traffictop_process_withdrawal($w->id, 'rejected', 'Auto-rejected: user banned');
    }
    return true;
}

function traffictop_unban_user( $user_id ) {
    delete_user_meta($user_id, 'traffictop_banned');
    delete_user_meta($user_id, 'traffictop_banned_at');
    return true;
}

/** Create notification (XSS sanitized) */
function traffictop_create_notification( $user_id, $type, $title, $message, $data = array() ) {
    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;

    $wpdb->insert("{$p}notifications", array(
        'user_id'=>$user_id, 'type'=>sanitize_text_field($type),
        'title'=>sanitize_text_field($title), 'message'=>wp_kses_post($message),
        'data'=>wp_json_encode($data), 'is_read'=>0, 'created_at'=>traffictop_current_time(),
    ));
    return $wpdb->insert_id;
}

function traffictop_get_user_notifications( $user_id, $unread_only = false, $limit = 20 ) {
    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $where = $unread_only ? 'AND is_read = 0' : '';
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$p}notifications WHERE user_id=%d {$where} ORDER BY created_at DESC LIMIT %d", $user_id, $limit ));
}

function traffictop_mark_all_notifications_read( $user_id ) {
    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $wpdb->update("{$p}notifications", array('is_read'=>1), array('user_id'=>$user_id, 'is_read'=>0));
}

/** Cleanup inactive users - preserves all financial data */
function traffictop_cleanup_inactive_users() {
    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $days = (int) traffictop_get_option('inactive_user_days', 10);
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days", strtotime(traffictop_current_time())));

    // Only delete users with ZERO financial activity (no transactions at all, no withdrawals)
    $users = $wpdb->get_results( $wpdb->prepare(
        "SELECT u.ID FROM {$wpdb->users} u
         LEFT JOIN {$p}transactions t ON u.ID = t.user_id
         LEFT JOIN {$p}withdrawals w ON u.ID = w.user_id
         WHERE u.user_registered < %s AND t.id IS NULL AND w.id IS NULL
         AND u.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'traffictop_banned')
         AND u.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '{$wpdb->prefix}capabilities' AND meta_value LIKE '%administrator%')
         AND u.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '{$wpdb->prefix}capabilities' AND meta_value LIKE '%customer%')",
        $cutoff ));

    foreach ( $users as $u ) {
        // Double-check balance
        $balance = traffictop_get_user_balance_amount($u->ID);
        if ( $balance <= 0 ) {
            // Clean up non-financial data only
            $wpdb->delete("{$p}notifications", array('user_id'=>$u->ID));
            wp_delete_user($u->ID);
        }
    }
}
