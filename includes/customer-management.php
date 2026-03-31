<?php
/**
 * LinkNgon V2 - Customer Management
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function linkngon_ban_customer( $user_id ) {
    update_user_meta($user_id, 'linkngon_customer_banned', 1);
    linkngon_auto_pause_customer_campaigns($user_id);
    return true;
}

function linkngon_unban_customer( $user_id ) {
    delete_user_meta($user_id, 'linkngon_customer_banned');
    return true;
}

/** Admin impersonation */
function linkngon_login_as_customer( $customer_id ) {
    if ( !current_user_can('manage_options') ) return false;
    $admin_id = get_current_user_id();
    update_user_meta($customer_id, 'switch_from_admin', $admin_id);
    wp_set_auth_cookie($customer_id);
    return true;
}

/** Hard delete all customer data */
function linkngon_permanent_delete_customer( $customer_id ) {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    // Delete in order: visits → transactions → orders → campaigns → deposits → balance
    $wpdb->query( $wpdb->prepare("DELETE FROM {$p}shortlink_visits WHERE campaign_id IN (SELECT id FROM {$p}keyword_campaigns WHERE customer_id=%d)", $customer_id));
    $wpdb->query( $wpdb->prepare("DELETE FROM {$p}customer_transactions WHERE customer_id=%d", $customer_id));
    $wpdb->query( $wpdb->prepare("DELETE FROM {$p}customer_orders WHERE customer_id=%d", $customer_id));
    $wpdb->query( $wpdb->prepare("DELETE FROM {$p}keyword_campaigns WHERE customer_id=%d", $customer_id));
    $wpdb->query( $wpdb->prepare("DELETE FROM {$p}customer_deposits WHERE customer_id=%d", $customer_id));
    $wpdb->query( $wpdb->prepare("DELETE FROM {$p}customer_balance WHERE user_id=%d", $customer_id));
    return true;
}

function linkngon_auto_delete_old_customers() {
    // Placeholder - implement based on business rules
}
