<?php
/**
 * SiteTop.net V2 - Settings Management
 * Admin AJAX handlers for saving settings
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ─── General Settings ───
add_action( 'wp_ajax_sitetop_save_settings', 'sitetop_save_settings' );
function sitetop_save_settings() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $options = array(
        // Withdrawal
        'min_withdrawal'       => 'int',
        'max_withdrawal'       => 'int',
        // Duyệt nguồn file gốc
        'require_source_approval'    => 'bool',
        'source_telegram'            => 'text',
        // IP Protection
        'shortlink_ip_limit_24h'     => 'int',
        'detect_ip_change'           => 'bool',
        'detect_vpn_proxy'           => 'bool',
        'block_proxy_ip'             => 'bool',
        'block_vpn_ip'               => 'bool',
        'block_datacenter_ip'        => 'bool',
        'block_fraud_reward'         => 'bool',
        // Tự tạm dừng camp khi bị nhiều IP báo lỗi
        'report_autopause_enabled'   => 'bool',
        'report_autopause_threshold' => 'int',
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
        update_option( 'sitetop_' . $key, $val );
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
            update_option( 'sitetop_deposit_presets', wp_json_encode( $clean ) );
        }
    }

    wp_send_json_success( 'Đã lưu cài đặt' );
}

// ─── Keyword Traffic Settings ───
add_action( 'wp_ajax_sitetop_save_keyword_settings', 'sitetop_save_keyword_settings' );
function sitetop_save_keyword_settings() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
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
        update_option( 'sitetop_' . $k, $v );
    }

    // Onsite time options (JSON array)
    if ( isset( $_POST['keyword_onsite_times'] ) ) {
        $times = json_decode( stripslashes( $_POST['keyword_onsite_times'] ), true );
        if ( is_array( $times ) ) update_option( 'sitetop_keyword_onsite_times', wp_json_encode( $times ) );
    }

    wp_send_json_success( 'Đã lưu' );
}

// ─── Direct Traffic Settings ───
add_action( 'wp_ajax_sitetop_save_direct_settings', 'sitetop_save_direct_settings' );
function sitetop_save_direct_settings() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $keys = array(
        'direct_price_1step', 'direct_price_2step', 'direct_price_nocode',
        'direct_user_1step', 'direct_user_2step', 'direct_user_nocode',
    );
    foreach ( $keys as $k ) {
        if ( isset( $_POST[ $k ] ) ) update_option( 'sitetop_' . $k, max( 0, floatval( $_POST[ $k ] ) ) );
    }

    wp_send_json_success( 'Đã lưu' );
}

// ─── Turnstile / Captcha Settings ───
add_action( 'wp_ajax_sitetop_save_turnstile_settings', 'sitetop_save_turnstile_settings' );
function sitetop_save_turnstile_settings() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $keys = array( 'turnstile_enabled' => 'bool', 'turnstile_site_key' => 'text', 'turnstile_secret_key' => 'text',
                   'widget_captcha_enabled' => 'bool', 'unlock_captcha_enabled' => 'bool' );
    foreach ( $keys as $k => $type ) {
        if ( ! isset( $_POST[ $k ] ) ) continue;
        $val = $type === 'bool' ? ( $_POST[ $k ] ? '1' : '0' ) : sanitize_text_field( $_POST[ $k ] );
        update_option( 'sitetop_' . $k, $val );
    }

    wp_send_json_success( 'Đã lưu' );
}

// ─── Widget Icon Upload ───
add_action( 'wp_ajax_sitetop_upload_widget_icon', 'sitetop_upload_widget_icon' );
function sitetop_upload_widget_icon() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    if ( ! isset( $_FILES['icon'] ) ) wp_send_json_error( 'No file' );

    if ( ! function_exists( 'wp_handle_upload' ) ) require_once ABSPATH . 'wp-admin/includes/file.php';
    $uploaded = wp_handle_upload( $_FILES['icon'], array( 'test_form' => false ) );
    if ( $uploaded && ! isset( $uploaded['error'] ) ) {
        update_option( 'sitetop_widget_icon', $uploaded['url'] );
        wp_send_json_success( array( 'url' => $uploaded['url'] ) );
    }
    wp_send_json_error( $uploaded['error'] ?? 'Upload failed' );
}

// ─── ImgBB Test ───
add_action( 'wp_ajax_sitetop_test_imgbb', function() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
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

// ─── Test email hệ thống: đi ĐÚNG đường code thật (không tự cấu hình SMTP tại chỗ) ───
// Nút "Test SMTP" bên dưới tự set phpmailer_init ngay trong handler nên luôn dùng SMTP,
// không phản ánh việc email thật (xác thực tài khoản, rút tiền...) có đi qua SMTP hay không.
// Handler này gọi thẳng sitetop_send_verification_email() — chính hàm gửi mail xác thực.
add_action( 'wp_ajax_sitetop_test_system_email', 'sitetop_test_system_email' );
function sitetop_test_system_email() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $to = sanitize_email( $_POST['test_email'] ?? '' );
    if ( ! $to ) wp_send_json_error( 'Email không hợp lệ' );

    // Trạng thái cấu hình đang thực sự áp dụng
    $enabled = sitetop_get_option( 'smtp_enabled', '0' );
    $state   = sprintf(
        'SMTP: %s | host: %s | user: %s | from: %s',
        $enabled === '1' ? 'BẬT' : 'TẮT (đang dùng PHP mail)',
        sitetop_get_option( 'smtp_host', '' ) ?: '(trống)',
        sitetop_get_option( 'smtp_username', '' ) ? 'đã điền' : '(TRỐNG)',
        sitetop_get_option( 'smtp_from_email', '' ) ?: '(TRỐNG)'
    );

    $mail_error = '';
    add_action( 'wp_mail_failed', function( $wp_err ) use ( &$mail_error ) {
        $mail_error = $wp_err->get_error_message();
    } );

    // Gửi qua wp_mail thuần — hook phpmailer_init toàn cục (nếu có) sẽ tự áp dụng,
    // giống hệt lúc gửi email xác thực tài khoản.
    $sent = wp_mail(
        $to,
        '[SiteTop.net] Test email hệ thống',
        '<p>Đây là email test đi qua <b>đúng đường code</b> của email xác thực tài khoản.</p><p>Nhận được email này nghĩa là chức năng xác thực email đang hoạt động.</p>',
        array( 'Content-Type: text/html; charset=UTF-8' )
    );

    if ( $sent ) wp_send_json_success( 'Đã gửi thành công. ' . $state );
    wp_send_json_error( 'Gửi thất bại: ' . ( $mail_error ?: 'không có chi tiết' ) . ' — ' . $state );
}

// ─── SMTP Test ───
add_action( 'wp_ajax_sitetop_test_smtp', 'sitetop_test_smtp' );
function sitetop_test_smtp() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $to = sanitize_email( $_POST['test_email'] ?? '' );
    if ( ! $to ) wp_send_json_error( 'Email không hợp lệ' );

    // Temporarily configure SMTP
    $host = sitetop_get_option( 'smtp_host', '' );
    $port = (int) sitetop_get_option( 'smtp_port', 587 );
    $enc  = sitetop_get_option( 'smtp_encryption', 'tls' );
    $user = sitetop_get_option( 'smtp_username', '' );
    $pass = sitetop_get_option( 'smtp_password', '' );
    $from = sitetop_get_option( 'smtp_from_email', get_option( 'admin_email' ) );
    $name = sitetop_get_option( 'smtp_from_name', get_bloginfo( 'name' ) );

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

    // Bắt lỗi thật từ PHPMailer — không có cái này thì chỉ biết "thất bại" mà không
    // biết do sai mật khẩu, hosting chặn cổng, hay sai host/encryption.
    $mail_error = '';
    add_action( 'wp_mail_failed', function( $wp_err ) use ( &$mail_error ) {
        $mail_error = $wp_err->get_error_message();
    } );

    $sent = wp_mail( $to, '[SiteTop.net] Test SMTP', 'Email test thành công từ SiteTop.net.', array( 'Content-Type: text/html; charset=UTF-8' ) );
    if ( $sent ) {
        wp_send_json_success( 'Email đã gửi thành công' );
    }

    // Dịch các lỗi SMTP hay gặp sang tiếng Việt kèm cách xử lý
    $hint = '';
    $low  = strtolower( $mail_error );
    if ( strpos( $low, 'could not authenticate' ) !== false || strpos( $low, 'username and password not accepted' ) !== false ) {
        $hint = ' → Sai tài khoản/mật khẩu. Với Gmail phải dùng App Password 16 ký tự (cần bật xác minh 2 bước), không dùng mật khẩu đăng nhập thường.';
    } elseif ( strpos( $low, 'could not connect' ) !== false || strpos( $low, 'connection refused' ) !== false || strpos( $low, 'timed out' ) !== false ) {
        $hint = ' → Không kết nối được tới máy chủ SMTP. Nhiều hosting chặn cổng 587/465 ra ngoài, cần liên hệ hosting mở, hoặc đổi sang cổng khác.';
    } elseif ( strpos( $low, 'invalid address' ) !== false ) {
        $hint = ' → Địa chỉ From Email không hợp lệ hoặc để trống.';
    }

    wp_send_json_error( 'Gửi email thất bại' . ( $mail_error ? ': ' . $mail_error : ' (máy chủ không trả về chi tiết lỗi)' ) . $hint );
}

// ─── Configure SMTP for production emails ───
// Khối này CHỈ chạy khi không có plugin gửi mail chuyên dụng nào đang bật.
//
// Lý do: wp_mail() chạy filter wp_mail_from TRƯỚC, rồi mới bắn action phpmailer_init
// ngay trước khi gửi. Khối dưới nằm ở phpmailer_init nên ghi đè thẳng $phpmailer->From,
// nuốt mất địa chỉ mà WP Mail SMTP đã đặt qua wp_mail_from — kể cả khi bật "Force From
// Email", vì đây là hook KHÁC chạy sau chứ không phải cuộc đua priority. Hệ quả cũ:
// Brevo nhận From = admin_email thay vì địa chỉ đã cấu hình. Có plugin mailer thì
// nhường hẳn quyền cấu hình cho nó.
function sitetop_external_mailer_active() {
    return defined( 'WPMS_PLUGIN_VER' )          // WP Mail SMTP
        || function_exists( 'wp_mail_smtp' )     // WP Mail SMTP (bản cũ)
        || defined( 'POST_SMTP_VER' )            // Post SMTP
        || defined( 'FLUENTMAIL_PLUGIN_FILE' );  // FluentSMTP
}

// Địa chỉ gửi mặc định khi chưa cấu hình. KHÔNG lấy admin_email: đó là hòm thư cá nhân
// của quản trị viên, không phải địa chỉ gửi của hệ thống — và chính fallback đó là
// nguồn gốc việc From bị hiện sai.
function sitetop_default_from_email() {
    $host = preg_replace( '/^www\./i', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    return $host ? 'noreply@' . $host : get_option( 'admin_email' );
}

if ( sitetop_get_option( 'smtp_enabled', '0' ) === '1' && ! sitetop_external_mailer_active() ) {
    add_action( 'phpmailer_init', function( $phpmailer ) {
        $host = sitetop_get_option( 'smtp_host', '' );
        if ( empty( $host ) ) return;
        $phpmailer->isSMTP();
        $phpmailer->Host       = $host;
        $phpmailer->Port       = (int) sitetop_get_option( 'smtp_port', 587 );
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = sitetop_get_option( 'smtp_username', '' );
        $phpmailer->Password   = sitetop_get_option( 'smtp_password', '' );
        $phpmailer->SMTPSecure = sitetop_get_option( 'smtp_encryption', 'tls' );
        $phpmailer->From       = sitetop_get_option( 'smtp_from_email', '' ) ?: sitetop_default_from_email();
        $phpmailer->FromName   = sitetop_get_option( 'smtp_from_name', '' ) ?: get_bloginfo( 'name' );
    });
}

// ─── Image Upload ───
// Dùng chung sitetop_upload_file(): nó có allow-list đuôi file + MIME thật, và thứ tự
// lưu đúng (máy chủ site trước, ImgBB dự phòng). Bản cũ ở đây gọi
// sitetop_upload_to_imgbb($_FILES[...]['tmp_name']) — truyền ĐƯỜNG DẪN vào tham số
// nhận DỮ LIỆU NHỊ PHÂN, nên nếu có nơi gọi thì sẽ ghi ra một "ảnh" chứa chuỗi đường dẫn.
add_action( 'wp_ajax_sitetop_ajax_upload_image', 'sitetop_ajax_upload_image' );
function sitetop_ajax_upload_image() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    if ( ! isset( $_FILES['image'] ) ) wp_send_json_error( 'No file' );

    $url = sitetop_upload_file( $_FILES['image'] );
    if ( $url ) wp_send_json_success( array( 'url' => $url ) );
    wp_send_json_error( 'Upload thất bại' );
}
