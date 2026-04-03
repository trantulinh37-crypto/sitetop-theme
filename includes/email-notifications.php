<?php
/**
 * LinkNgon V2 - Email Notifications
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function linkngon_send_deposit_email( $deposit_id ) {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $dep = $wpdb->get_row( $wpdb->prepare("SELECT d.*, u.user_email, u.display_name FROM {$p}customer_deposits d LEFT JOIN {$wpdb->users} u ON d.customer_id = u.ID WHERE d.id=%d", $deposit_id));
    if ( !$dep || !$dep->user_email ) return;

    $admin_email = get_option('admin_email');
    $subject = '[LinkNgon] Yêu cầu nạp tiền mới - ' . linkngon_format_money($dep->amount);
    $body = "<h2>Yêu cầu nạp tiền mới</h2>";
    $body .= "<p><strong>Khách hàng:</strong> " . esc_html($dep->display_name) . " (" . esc_html($dep->user_email) . ")</p>";
    $body .= "<p><strong>Số tiền:</strong> " . linkngon_format_money($dep->amount) . "</p>";
    $body .= "<p><strong>Phương thức:</strong> " . esc_html($dep->payment_method ?? '') . "</p>";
    $body .= "<p><strong>Thời gian:</strong> " . esc_html($dep->created_at) . "</p>";

    wp_mail($admin_email, $subject, $body, array('Content-Type: text/html; charset=UTF-8'));
}
