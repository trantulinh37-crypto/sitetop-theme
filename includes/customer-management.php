<?php
/**
 * Traffictop.net V2 - Customer Management
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function traffictop_ban_customer( $user_id ) {
    update_user_meta($user_id, 'traffictop_customer_banned', 1);
    traffictop_auto_pause_customer_campaigns($user_id);
    return true;
}

function traffictop_unban_customer( $user_id ) {
    delete_user_meta($user_id, 'traffictop_customer_banned');
    return true;
}

/** Admin impersonation */
function traffictop_login_as_customer( $customer_id ) {
    if ( !current_user_can('manage_options') ) return false;
    $admin_id = get_current_user_id();
    update_user_meta($customer_id, 'switch_from_admin', $admin_id);
    wp_set_auth_cookie($customer_id);
    return true;
}

/** Delete customer - preserve all financial data */
function traffictop_permanent_delete_customer( $customer_id ) {
    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;

    // Soft-delete campaigns and orders (NOT hard delete)
    $now = traffictop_current_time();
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}keyword_campaigns SET status='deleted', updated_at=%s WHERE customer_id=%d AND status != 'deleted'",
        $now, $customer_id ));
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}customer_orders SET status='deleted', updated_at=%s WHERE customer_id=%d AND status != 'deleted'",
        $now, $customer_id ));

    // Invalidate campaign cache
    delete_transient('traffictop_eligible_campaigns');

    // KEEP: customer_transactions, customer_deposits, customer_balance, shortlink_visits
    // These are financial audit trail and MUST be preserved

    // Mark customer as deleted
    update_user_meta($customer_id, 'traffictop_customer_deleted', 1);
    update_user_meta($customer_id, 'traffictop_customer_deleted_at', $now);

    return true;
}

function traffictop_auto_delete_old_customers() {
    // Placeholder - implement based on business rules
}
