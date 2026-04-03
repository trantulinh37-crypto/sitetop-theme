<?php
/**
 * LinkNgon V2 - Settings Management
 * Admin AJAX handlers for saving settings
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ─── General Settings ───
add_action( 'wp_ajax_linkngon_save_settings', 'linkngon_save_settings' );
function linkngon_save_settings() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $options = array(
        // Withdrawal
        'min_withdrawal'       => 'int',
        // IP Protection
        'shortlink_ip_limit_24h'     => 'int',
        'detect_ip_change'           => 'bool',
        'detect_vpn_proxy'           => 'bool',
        'block_proxy_ip'             => 'bool',
        'block_vpn_ip'               => 'bool',
        'block_datacenter_ip'        => 'bool',
        'block_fraud_reward'         => 'bool',
        'trust_reverse_proxy'        => 'bool',
        // Security
        'verify_code_expiry'         => 'int',
        // SMTP
        'smtp_enabled'               => 'bool',
        'smtp_host'                  => 'text',
        'smtp_port'                  => 'int',
        'smtp_encryption'            => 'text',
        'smtp_username'              => 'text',
        'smtp_password'              => 'text',
        'smtp_from_email'            => 'email',
        'smtp_from_name'             => 'text',
        // Upload
        'imgbb_api_key'              => 'text',
        // Cleanup retention (days)
        'cleanup_old_visits'         => 'int',
        'cleanup_read_notifications' => 'int',
        'cleanup_old_behavior'       => 'int',
        'inactive_user_days'         => 'int',
        // DDoS
        'ddos_global_rate'           => 'int',
        'ddos_burst_limit'           => 'int',
        'ddos_sustained_limit'       => 'int',
        'ddos_violation_threshold'   => 'int',
        'ddos_block_duration'        => 'int',
        'ddos_whitelist'             => 'textarea',
        'blocked_referrers'          => 'textarea',
        // Distribution
        'customer_min_balance'       => 'int',
        // Widget
        'widget_default_countdown'   => 'int',
        'widget_color'               => 'text',
        'widget_text_color'          => 'text',
        'site_short'                 => 'text',
        // Low balance alerts
        'low_balance_alert_enabled'  => 'bool',
        'low_balance_threshold'      => 'int',
        // Referral
        'referral_enabled'               => 'bool',
        'referral_commission_percent'    => 'int',
        'referral_min_payout'            => 'int',
        'referral_duration_days'         => 'int',
        // Contact
        'contact_telegram'           => 'text',
        'contact_zalo'               => 'text',
        'contact_email'              => 'email',
    );

    foreach ( $options as $key => $type ) {
        if ( ! isset( $_POST[ $key ] ) ) continue;
        $val = $_POST[ $key ];
        switch ( $type ) {
            case 'int':      $val = intval( $val ); break;
            case 'bool':     $val = $val ? '1' : '0'; break;
            case 'email':    $val = sanitize_email( $val ); break;
            case 'textarea': $val = sanitize_textarea_field( $val ); break;
            default:         $val = sanitize_text_field( $val );
        }
        update_option( 'linkngon_' . $key, $val );
    }

    // Deposit tiers (JSON)
    if ( isset( $_POST['deposit_tiers'] ) ) {
        $tiers = json_decode( stripslashes( $_POST['deposit_tiers'] ), true );
        if ( is_array( $tiers ) ) {
            update_option( 'linkngon_deposit_tiers', wp_json_encode( $tiers ) );
        }
    }

    wp_send_json_success( 'Đã lưu cài đặt' );
}

// ─── Keyword Traffic Settings ───
add_action( 'wp_ajax_linkngon_save_keyword_settings', 'linkngon_save_keyword_settings' );
function linkngon_save_keyword_settings() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $keys = array(
        'keyword_price_1step', 'keyword_price_2step', 'keyword_price_nocode',
        'keyword_user_1step', 'keyword_user_2step', 'keyword_user_nocode',
        'keyword_user_reward_percent',
    );
    foreach ( $keys as $k ) {
        if ( isset( $_POST[ $k ] ) ) update_option( 'linkngon_' . $k, floatval( $_POST[ $k ] ) );
    }

    // Onsite time options (JSON array)
    if ( isset( $_POST['keyword_onsite_times'] ) ) {
        $times = json_decode( stripslashes( $_POST['keyword_onsite_times'] ), true );
        if ( is_array( $times ) ) update_option( 'linkngon_keyword_onsite_times', wp_json_encode( $times ) );
    }

    wp_send_json_success( 'Đã lưu' );
}

// ─── Direct Traffic Settings ───
add_action( 'wp_ajax_linkngon_save_direct_settings', 'linkngon_save_direct_settings' );
function linkngon_save_direct_settings() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $keys = array(
        'direct_price_1step', 'direct_price_2step', 'direct_price_nocode',
        'direct_user_1step', 'direct_user_2step', 'direct_user_nocode',
    );
    foreach ( $keys as $k ) {
        if ( isset( $_POST[ $k ] ) ) update_option( 'linkngon_' . $k, floatval( $_POST[ $k ] ) );
    }

    wp_send_json_success( 'Đã lưu' );
}

// ─── Turnstile / Captcha Settings ───
add_action( 'wp_ajax_linkngon_save_turnstile_settings', 'linkngon_save_turnstile_settings' );
function linkngon_save_turnstile_settings() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $keys = array( 'turnstile_enabled' => 'bool', 'turnstile_site_key' => 'text', 'turnstile_secret_key' => 'text' );
    foreach ( $keys as $k => $type ) {
        if ( ! isset( $_POST[ $k ] ) ) continue;
        $val = $type === 'bool' ? ( $_POST[ $k ] ? '1' : '0' ) : sanitize_text_field( $_POST[ $k ] );
        update_option( 'linkngon_' . $k, $val );
    }

    wp_send_json_success( 'Đã lưu' );
}

// ─── Widget Icon Upload ───
add_action( 'wp_ajax_linkngon_upload_widget_icon', 'linkngon_upload_widget_icon' );
function linkngon_upload_widget_icon() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    if ( ! isset( $_FILES['icon'] ) ) wp_send_json_error( 'No file' );

    if ( ! function_exists( 'wp_handle_upload' ) ) require_once ABSPATH . 'wp-admin/includes/file.php';
    $uploaded = wp_handle_upload( $_FILES['icon'], array( 'test_form' => false ) );
    if ( $uploaded && ! isset( $uploaded['error'] ) ) {
        update_option( 'linkngon_widget_icon', $uploaded['url'] );
        wp_send_json_success( array( 'url' => $uploaded['url'] ) );
    }
    wp_send_json_error( $uploaded['error'] ?? 'Upload failed' );
}

// ─── SMTP Test ───
add_action( 'wp_ajax_linkngon_test_smtp', 'linkngon_test_smtp' );
function linkngon_test_smtp() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $to = sanitize_email( $_POST['test_email'] ?? '' );
    if ( ! $to ) wp_send_json_error( 'Email không hợp lệ' );

    // Temporarily configure SMTP
    $host = linkngon_get_option( 'smtp_host', '' );
    $port = (int) linkngon_get_option( 'smtp_port', 587 );
    $enc  = linkngon_get_option( 'smtp_encryption', 'tls' );
    $user = linkngon_get_option( 'smtp_username', '' );
    $pass = linkngon_get_option( 'smtp_password', '' );
    $from = linkngon_get_option( 'smtp_from_email', get_option( 'admin_email' ) );
    $name = linkngon_get_option( 'smtp_from_name', get_bloginfo( 'name' ) );

    if ( empty( $host ) || empty( $user ) ) wp_send_json_error( 'Chưa cấu hình SMTP' );

    add_action( 'phpmailer_init', function( $phpmailer ) use ( $host, $port, $enc, $user, $pass, $from, $name ) {
        $phpmailer->isSMTP();
        $phpmailer->Host = $host;
        $phpmailer->Port = $port;
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $user;
        $phpmailer->Password = $pass;
        $phpmailer->SMTPSecure = $enc;
        $phpmailer->From = $from;
        $phpmailer->FromName = $name;
    });

    $sent = wp_mail( $to, '[LinkNgon] Test SMTP', 'Email test thành công từ LinkNgon.', array( 'Content-Type: text/html; charset=UTF-8' ) );
    if ( $sent ) wp_send_json_success( 'Email đã gửi thành công' );
    else wp_send_json_error( 'Gửi email thất bại' );
}

// ─── Configure SMTP for production emails ───
if ( linkngon_get_option( 'smtp_enabled', '0' ) === '1' ) {
    add_action( 'phpmailer_init', function( $phpmailer ) {
        $host = linkngon_get_option( 'smtp_host', '' );
        if ( empty( $host ) ) return;
        $phpmailer->isSMTP();
        $phpmailer->Host       = $host;
        $phpmailer->Port       = (int) linkngon_get_option( 'smtp_port', 587 );
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = linkngon_get_option( 'smtp_username', '' );
        $phpmailer->Password   = linkngon_get_option( 'smtp_password', '' );
        $phpmailer->SMTPSecure = linkngon_get_option( 'smtp_encryption', 'tls' );
        $phpmailer->From       = linkngon_get_option( 'smtp_from_email', get_option( 'admin_email' ) );
        $phpmailer->FromName   = linkngon_get_option( 'smtp_from_name', get_bloginfo( 'name' ) );
    });
}

// ─── Image Upload (ImgBB + fallback) ───
add_action( 'wp_ajax_linkngon_ajax_upload_image', 'linkngon_ajax_upload_image' );
function linkngon_ajax_upload_image() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    if ( ! isset( $_FILES['image'] ) ) wp_send_json_error( 'No file' );

    // Try ImgBB first
    $api_key = linkngon_get_option( 'imgbb_api_key', '' );
    if ( $api_key && function_exists( 'linkngon_upload_to_imgbb' ) ) {
        $result = linkngon_upload_to_imgbb( $_FILES['image']['tmp_name'], $api_key );
        if ( $result ) { wp_send_json_success( array( 'url' => $result ) ); return; }
    }

    // Fallback: WordPress media
    if ( ! function_exists( 'wp_handle_upload' ) ) require_once ABSPATH . 'wp-admin/includes/file.php';
    $uploaded = wp_handle_upload( $_FILES['image'], array( 'test_form' => false ) );
    if ( $uploaded && ! isset( $uploaded['error'] ) ) {
        wp_send_json_success( array( 'url' => $uploaded['url'] ) );
    }
    wp_send_json_error( $uploaded['error'] ?? 'Upload failed' );
}
