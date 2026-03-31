<?php
/**
 * LinkNgon V2 - User Management
 * Ban/unban, notifications, inactive cleanup
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Ban user - auto reject pending/approved withdrawals */
function linkngon_ban_user( $user_id ) {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;

    update_user_meta($user_id, 'linkngon_banned', 1);
    update_user_meta($user_id, 'linkngon_banned_at', linkngon_current_time());

    // Reject all pending/approved withdrawals + refund
    $pending = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$p}withdrawals WHERE user_id=%d AND status IN ('pending','approved')", $user_id ));
    foreach ( $pending as $w ) {
        linkngon_process_withdrawal($w->id, 'rejected', 'Auto-rejected: user banned');
    }
    return true;
}

function linkngon_unban_user( $user_id ) {
    delete_user_meta($user_id, 'linkngon_banned');
    delete_user_meta($user_id, 'linkngon_banned_at');
    return true;
}

/** Create notification (XSS sanitized) */
function linkngon_create_notification( $user_id, $type, $title, $message, $data = array() ) {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;

    $wpdb->insert("{$p}notifications", array(
        'user_id'=>$user_id, 'type'=>sanitize_text_field($type),
        'title'=>sanitize_text_field($title), 'message'=>wp_kses_post($message),
        'data'=>wp_json_encode($data), 'is_read'=>0, 'created_at'=>linkngon_current_time(),
    ));
    return $wpdb->insert_id;
}

function linkngon_get_user_notifications( $user_id, $unread_only = false, $limit = 20 ) {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $where = $unread_only ? 'AND is_read = 0' : '';
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$p}notifications WHERE user_id=%d {$where} ORDER BY created_at DESC LIMIT %d", $user_id, $limit ));
}

function linkngon_mark_all_notifications_read( $user_id ) {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $wpdb->update("{$p}notifications", array('is_read'=>1), array('user_id'=>$user_id, 'is_read'=>0));
}

/** Cleanup inactive users */
function linkngon_cleanup_inactive_users() {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $days = (int) linkngon_get_option('inactive_user_days', 10);
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days", strtotime(linkngon_current_time())));

    $users = $wpdb->get_results( $wpdb->prepare(
        "SELECT u.ID FROM {$wpdb->users} u
         LEFT JOIN {$p}transactions t ON u.ID = t.user_id AND t.type = 'shortlink_reward'
         LEFT JOIN {$p}withdrawals w ON u.ID = w.user_id AND w.status = 'completed'
         WHERE u.user_registered < %s AND t.id IS NULL AND w.id IS NULL
         AND u.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'linkngon_banned')
         AND u.ID NOT IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '{$wpdb->prefix}capabilities' AND meta_value LIKE '%administrator%')",
        $cutoff ));

    foreach ( $users as $u ) {
        // Double-check balance
        $balance = linkngon_get_user_balance_amount($u->ID);
        if ( $balance <= 0 ) {
            wp_delete_user($u->ID);
        }
    }
}
