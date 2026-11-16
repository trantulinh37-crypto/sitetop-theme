<?php
/**
 * SiteTop.net V2 - Low Balance Alerts (hourly)
 * Email + popup when customer balance < threshold
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function sitetop_check_low_balance_alerts() {
    global $wpdb;
    $p = $wpdb->prefix . SITETOP_PREFIX;
    $threshold_pct = (int) sitetop_get_option('low_balance_threshold', 20);
    $today = date('Y-m-d', strtotime(sitetop_current_time()));

    $customers = $wpdb->get_results(
        "SELECT cb.user_id, cb.balance, cb.total_deposited, u.user_email, u.display_name
         FROM {$p}customer_balance cb
         LEFT JOIN {$wpdb->users} u ON cb.user_id = u.ID
         WHERE cb.balance > 0"
    );

    /* 16/11/2026 chủ site: admin chỉnh ngân sách (sửa Đã chạy / view hôm nay — dòng sổ reference_type
       'dieu_chinh_chay') KHÔNG được làm khách nhận email/thông báo. Nên khi xét "số dư thấp" thì cộng trả
       phần admin đã trừ: chỉ chi tiêu thật mới kích cảnh báo. */
    $dc_tru = array();
    foreach ( (array) $wpdb->get_results( "SELECT customer_id, -SUM(amount) AS tru FROM {$p}customer_transactions WHERE reference_type = 'dieu_chinh_chay' GROUP BY customer_id" ) as $r ) {
        $dc_tru[ (int) $r->customer_id ] = (float) $r->tru;
    }

    foreach ( $customers as $c ) {
        if ( $c->total_deposited <= 0 ) continue;
        $threshold = $c->total_deposited * ($threshold_pct / 100);
        if ( $c->balance > $threshold ) continue;
        if ( ( (float) $c->balance + max( 0, $dc_tru[ (int) $c->user_id ] ?? 0 ) ) > $threshold ) continue;   // thấp chỉ vì admin chỉnh

        // Check if already alerted today
        $alerted_key = 'sitetop_low_bal_alert_' . $c->user_id . '_' . $today;
        if ( get_transient($alerted_key) ) continue;
        set_transient($alerted_key, 1, 86400);

        // Send email
        $subject = '[SiteTop.net] Số dư tài khoản thấp';
        $body = "<p>Xin chào " . esc_html($c->display_name) . ",</p>";
        $body .= "<p>Số dư tài khoản của bạn hiện chỉ còn <strong>" . sitetop_format_money($c->balance) . "</strong>.</p>";
        $body .= "<p>Vui lòng nạp thêm để campaigns tiếp tục hoạt động.</p>";
        wp_mail($c->user_email, $subject, $body, array('Content-Type: text/html; charset=UTF-8'));

        // Create notification
        sitetop_create_notification($c->user_id, 'warning', 'Số dư thấp',
            'Số dư còn ' . sitetop_format_money($c->balance) . '. Nạp thêm để campaigns không bị tạm dừng.');
    }
}
