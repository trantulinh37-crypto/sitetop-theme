<?php
/**
 * Traffictop.net V2 - Settings Management
 * Admin AJAX handlers for saving settings
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ─── General Settings ───
add_action( 'wp_ajax_traffictop_save_settings', 'traffictop_save_settings' );
function traffictop_save_settings() {
    check_ajax_referer( 'traffictop_admin_nonce', 'nonce' );
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
        'widget_color'               => 'hexcolor',
        'widget_text_color'          => 'hexcolor',
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
            case 'int':      $val = max( 0, intval( $val ) ); break; // non-negative limits/counters
            case 'bool':     $val = $val ? '1' : '0'; break;
            case 'email':    $val = sanitize_email( $val ); break;
            case 'textarea': $val = sanitize_textarea_field( $val ); break;
            case 'hexcolor':
                $c = sanitize_hex_color( $val );
                if ( $c === null || $c === '' ) continue 2; // invalid hex → don't overwrite
                $val = $c;
                break;
            default:         $val = sanitize_text_field( $val );
        }
        update_option( 'traffictop_' . $key, $val );
    }

    // Deposit presets (JSON) — validate each tier: amount >= 0, bonus clamped 0–100.
    if ( isset( $_POST['deposit_presets'] ) ) {
        $presets = json_decode( stripslashes( $_POST['deposit_presets'] ), true );
        if ( is_array( $presets ) ) {
            $clean = array();
            foreach ( $presets as $tier ) {
                if ( ! is_array( $tier ) ) continue;
                $amt   = max( 0, intval( $tier['amount'] ?? 0 ) );
                $bonus = max( 0, min( 100, intval( $tier['bonus'] ?? 0 ) ) );
                if ( $amt > 0 ) $clean[] = array( 'amount' => $amt, 'bonus' => $bonus );
            }
            update_option( 'traffictop_deposit_presets', wp_json_encode( $clean ) );
        }
    }

    wp_send_json_success( 'Đã lưu cài đặt' );
}

// ─── Keyword Traffic Settings ───
add_action( 'wp_ajax_traffictop_save_keyword_settings', 'traffictop_save_keyword_settings' );
function traffictop_save_keyword_settings() {
    check_ajax_referer( 'traffictop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $keys = array(
        'keyword_price_1step', 'keyword_price_2step', 'keyword_price_nocode',
        'keyword_user_1step', 'keyword_user_2step', 'keyword_user_nocode',
        'keyword_user_reward_percent',
    );
    foreach ( $keys as $k ) {
        if ( ! isset( $_POST[ $k ] ) ) continue;
        $v = floatval( $_POST[ $k ] );
        // Reward percent clamped 0–100; prices/rewards non-negative.
        $v = ( $k === 'keyword_user_reward_percent' ) ? max( 0, min( 100, $v ) ) : max( 0, $v );
        update_option( 'traffictop_' . $k, $v );
    }

    // Onsite time options (JSON array)
    if ( isset( $_POST['keyword_onsite_times'] ) ) {
        $times = json_decode( stripslashes( $_POST['keyword_onsite_times'] ), true );
        if ( is_array( $times ) ) update_option( 'traffictop_keyword_onsite_times', wp_json_encode( $times ) );
    }

    wp_send_json_success( 'Đã lưu' );
}

// ─── Direct Traffic Settings ───
add_action( 'wp_ajax_traffictop_save_direct_settings', 'traffictop_save_direct_settings' );
function traffictop_save_direct_settings() {
    check_ajax_referer( 'traffictop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $keys = array(
        'direct_price_1step', 'direct_price_2step', 'direct_price_nocode',
        'direct_user_1step', 'direct_user_2step', 'direct_user_nocode',
    );
    foreach ( $keys as $k ) {
        if ( isset( $_POST[ $k ] ) ) update_option( 'traffictop_' . $k, max( 0, floatval( $_POST[ $k ] ) ) );
    }

    wp_send_json_success( 'Đã lưu' );
}

// ─── Turnstile / Captcha Settings ───
add_action( 'wp_ajax_traffictop_save_turnstile_settings', 'traffictop_save_turnstile_settings' );
function traffictop_save_turnstile_settings() {
    check_ajax_referer( 'traffictop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $keys = array( 'turnstile_enabled' => 'bool', 'turnstile_site_key' => 'text', 'turnstile_secret_key' => 'text' );
    foreach ( $keys as $k => $type ) {
        if ( ! isset( $_POST[ $k ] ) ) continue;
        $val = $type === 'bool' ? ( $_POST[ $k ] ? '1' : '0' ) : sanitize_text_field( $_POST[ $k ] );
        update_option( 'traffictop_' . $k, $val );
    }

    wp_send_json_success( 'Đã lưu' );
}

// ─── Widget Icon Upload ───
add_action( 'wp_ajax_traffictop_upload_widget_icon', 'traffictop_upload_widget_icon' );
function traffictop_upload_widget_icon() {
    check_ajax_referer( 'traffictop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    if ( ! isset( $_FILES['icon'] ) ) wp_send_json_error( 'No file' );

    if ( ! function_exists( 'wp_handle_upload' ) ) require_once ABSPATH . 'wp-admin/includes/file.php';
    $uploaded = wp_handle_upload( $_FILES['icon'], array( 'test_form' => false ) );
    if ( $uploaded && ! isset( $uploaded['error'] ) ) {
        update_option( 'traffictop_widget_icon', $uploaded['url'] );
        wp_send_json_success( array( 'url' => $uploaded['url'] ) );
    }
    wp_send_json_error( $uploaded['error'] ?? 'Upload failed' );
}

// ─── ImgBB Test ───
add_action( 'wp_ajax_traffictop_test_imgbb', function() {
    check_ajax_referer( 'traffictop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    $key = sanitize_text_field( $_POST['api_key'] ?? '' );
    if ( empty($key) ) wp_send_json_error( 'Thiếu API key' );
    // Upload 1x1 pixel test image
    $pixel = base64_encode( hex2bin('89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c489000000' .
        '0a49444154789c626000000002000198e195280000000049454e44ae426082') );
    $resp = wp_remote_post( 'https://api.imgbb.com/1/upload', array(
        'body' => array( 'key' => $key, 'image' => $pixel ), 'timeout' => 15,
    ));
    if ( is_wp_error($resp) ) wp_send_json_error( $resp->get_error_message() );
    $body = json_decode( wp_remote_retrieve_body($resp), true );
    if ( !empty($body['data']['url']) ) wp_send_json_success( $body['data']['url'] );
    wp_send_json_error( $body['error']['message'] ?? 'API trả về lỗi' );
});

// ─── SMTP Test ───
add_action( 'wp_ajax_traffictop_test_smtp', 'traffictop_test_smtp' );
function traffictop_test_smtp() {
    check_ajax_referer( 'traffictop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $to = sanitize_email( $_POST['test_email'] ?? '' );
    if ( ! $to ) wp_send_json_error( 'Email không hợp lệ' );

    // Temporarily configure SMTP
    $host = traffictop_get_option( 'smtp_host', '' );
    $port = (int) traffictop_get_option( 'smtp_port', 587 );
    $enc  = traffictop_get_option( 'smtp_encryption', 'tls' );
    $user = traffictop_get_option( 'smtp_username', '' );
    $pass = traffictop_get_option( 'smtp_password', '' );
    $from = traffictop_get_option( 'smtp_from_email', get_option( 'admin_email' ) );
    $name = traffictop_get_option( 'smtp_from_name', get_bloginfo( 'name' ) );

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

    $sent = wp_mail( $to, '[Traffictop.net] Test SMTP', 'Email test thành công từ Traffictop.net.', array( 'Content-Type: text/html; charset=UTF-8' ) );
    if ( $sent ) wp_send_json_success( 'Email đã gửi thành công' );
    else wp_send_json_error( 'Gửi email thất bại' );
}

// ─── Configure SMTP for production emails ───
if ( traffictop_get_option( 'smtp_enabled', '0' ) === '1' ) {
    add_action( 'phpmailer_init', function( $phpmailer ) {
        $host = traffictop_get_option( 'smtp_host', '' );
        if ( empty( $host ) ) return;
        $phpmailer->isSMTP();
        $phpmailer->Host       = $host;
        $phpmailer->Port       = (int) traffictop_get_option( 'smtp_port', 587 );
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = traffictop_get_option( 'smtp_username', '' );
        $phpmailer->Password   = traffictop_get_option( 'smtp_password', '' );
        $phpmailer->SMTPSecure = traffictop_get_option( 'smtp_encryption', 'tls' );
        $phpmailer->From       = traffictop_get_option( 'smtp_from_email', get_option( 'admin_email' ) );
        $phpmailer->FromName   = traffictop_get_option( 'smtp_from_name', get_bloginfo( 'name' ) );
    });
}

// ─── Image Upload (ImgBB + fallback) ───
add_action( 'wp_ajax_traffictop_ajax_upload_image', 'traffictop_ajax_upload_image' );
function traffictop_ajax_upload_image() {
    check_ajax_referer( 'traffictop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    if ( ! isset( $_FILES['image'] ) ) wp_send_json_error( 'No file' );

    // Try ImgBB first
    $api_key = traffictop_get_option( 'imgbb_api_key', '' );
    if ( $api_key && function_exists( 'traffictop_upload_to_imgbb' ) ) {
        $result = traffictop_upload_to_imgbb( $_FILES['image']['tmp_name'], $api_key );
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
