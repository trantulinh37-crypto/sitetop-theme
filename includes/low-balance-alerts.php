<?php
/**
 * LinkNgon V2 - Low Balance Alerts (hourly)
 * Email + popup when customer balance < threshold
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function linkngon_check_low_balance_alerts() {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $threshold_pct = (int) linkngon_get_option('low_balance_threshold', 20);
    $today = date('Y-m-d', strtotime(linkngon_current_time()));

    $customers = $wpdb->get_results(
        "SELECT cb.user_id, cb.balance, cb.total_deposited, u.user_email, u.display_name
         FROM {$p}customer_balance cb
         LEFT JOIN {$wpdb->users} u ON cb.user_id = u.ID
         WHERE cb.balance > 0"
    );

    foreach ( $customers as $c ) {
        if ( $c->total_deposited <= 0 ) continue;
        $threshold = $c->total_deposited * ($threshold_pct / 100);
        if ( $c->balance > $threshold ) continue;

        // Check if already alerted today
        $alerted_key = 'linkngon_low_bal_alert_' . $c->user_id . '_' . $today;
        if ( get_transient($alerted_key) ) continue;
        set_transient($alerted_key, 1, 86400);

        // Send email
        $subject = '[LinkNgon] Số dư tài khoản thấp';
        $body = "<p>Xin chào " . esc_html($c->display_name) . ",</p>";
        $body .= "<p>Số dư tài khoản của bạn hiện chỉ còn <strong>" . linkngon_format_money($c->balance) . "</strong>.</p>";
        $body .= "<p>Vui lòng nạp thêm để campaigns tiếp tục hoạt động.</p>";
        wp_mail($c->user_email, $subject, $body, array('Content-Type: text/html; charset=UTF-8'));

        // Create notification
        linkngon_create_notification($c->user_id, 'warning', 'Số dư thấp',
            'Số dư còn ' . linkngon_format_money($c->balance) . '. Nạp thêm để campaigns không bị tạm dừng.');
    }
}
