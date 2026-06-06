<?php
/**
 * Traffictop.net V2 - Email Notifications
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   PASSWORD RESET EMAIL (override WordPress default)
   ============================================================ */

add_filter( 'retrieve_password_message', 'traffictop_custom_reset_password_email', 10, 4 );
add_filter( 'retrieve_password_title', 'traffictop_custom_reset_password_title', 10, 3 );

function traffictop_custom_reset_password_title( $title, $user_login, $user_data ) {
    $site_name = get_bloginfo( 'name' );
    return "[{$site_name}] Đặt lại mật khẩu";
}

function traffictop_custom_reset_password_email( $message, $key, $user_login, $user_data ) {
    $reset_url = add_query_arg( array(
        'key'   => $key,
        'login' => rawurlencode( $user_login ),
    ), home_url( '/quen-mat-khau' ) );

    $site_name = get_bloginfo( 'name' );
    $content = '<p style="color:#475569;line-height:1.6">Xin chào <strong>' . esc_html( $user_login ) . '</strong>,</p>';
    $content .= '<p style="color:#475569;line-height:1.6">Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn tại <strong>' . esc_html( $site_name ) . '</strong>.</p>';
    $content .= '<p style="color:#475569;line-height:1.6">Bấm nút bên dưới để đặt mật khẩu mới:</p>';
    $content .= '<div style="text-align:center;margin:28px 0">';
    $content .= '<a href="' . esc_url( $reset_url ) . '" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:14px 36px;border-radius:10px;font-weight:700;font-size:15px">Đặt lại mật khẩu</a>';
    $content .= '</div>';
    $content .= '<p style="color:#94a3b8;font-size:12px;margin:20px 0 0">Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này.<br>Link có hiệu lực trong 24 giờ.</p>';

    return traffictop_email_wrap( 'Đặt lại mật khẩu', $content );
}

add_filter( 'wp_mail', 'traffictop_reset_password_html_header' );
function traffictop_reset_password_html_header( $args ) {
    $site_name = get_bloginfo( 'name' );
    if ( strpos( $args['subject'], "[{$site_name}] Đặt lại mật khẩu" ) !== false ) {
        if ( ! is_array( $args['headers'] ) ) {
            $args['headers'] = array_filter( array( $args['headers'] ) );
        }
        $args['headers'][] = 'Content-Type: text/html; charset=UTF-8';
    }
    return $args;
}

/* ============================================================
   EMAIL VERIFICATION
   ============================================================ */

/**
 * Send verification email to newly registered user
 */
function traffictop_send_verification_email( $user_id ) {
    $user = get_user_by( 'ID', $user_id );
    if ( ! $user ) return false;

    $token = bin2hex( random_bytes(32) );
    $expiry = time() + 86400; // 24 hours
    update_user_meta( $user_id, 'traffictop_email_verify_token', $token );
    update_user_meta( $user_id, 'traffictop_email_verify_expiry', $expiry );

    $verify_url = add_query_arg( array(
        'action' => 'verify_email',
        'token'  => $token,
        'uid'    => $user_id,
    ), home_url( '/dang-nhap' ) );

    $site_name = get_bloginfo( 'name' );
    $subject = "[{$site_name}] Xác nhận email đăng ký";

    $body = '
    <div style="max-width:520px;margin:0 auto;font-family:Inter,sans-serif;color:#1e293b">
        <div style="text-align:center;padding:30px 0 20px">
            <h2 style="margin:0;font-size:22px;color:#0f172a">' . esc_html( $site_name ) . '</h2>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:32px">
            <h3 style="margin:0 0 8px;font-size:18px">Xin chào ' . esc_html( $user->user_login ) . ',</h3>
            <p style="color:#475569;line-height:1.6;margin:0 0 24px">Cảm ơn bạn đã đăng ký tài khoản. Vui lòng bấm nút bên dưới để xác nhận email và kích hoạt tài khoản.</p>
            <div style="text-align:center;margin:24px 0">
                <a href="' . esc_url( $verify_url ) . '" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:14px 36px;border-radius:10px;font-weight:700;font-size:15px">Xác nhận email</a>
            </div>
            <p style="color:#94a3b8;font-size:12px;margin:20px 0 0;text-align:center">Link xác nhận có hiệu lực trong 24 giờ.<br>Nếu bạn không đăng ký tài khoản này, vui lòng bỏ qua email này.</p>
        </div>
        <p style="text-align:center;color:#94a3b8;font-size:11px;margin-top:20px">&copy; ' . date('Y') . ' ' . esc_html( $site_name ) . '</p>
    </div>';

    return wp_mail( $user->user_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
}

/**
 * Verify email token
 * Returns: true on success, string error message on failure
 */
function traffictop_verify_email_token( $user_id, $token ) {
    $stored_token  = get_user_meta( $user_id, 'traffictop_email_verify_token', true );
    $stored_expiry = (int) get_user_meta( $user_id, 'traffictop_email_verify_expiry', true );

    if ( empty( $stored_token ) || $stored_token !== $token ) {
        return 'Link xác nhận không hợp lệ';
    }
    if ( time() > $stored_expiry ) {
        return 'Link xác nhận đã hết hạn. Vui lòng đăng nhập để gửi lại email xác nhận.';
    }

    // Mark email as verified
    update_user_meta( $user_id, 'traffictop_email_verified', '1' );
    delete_user_meta( $user_id, 'traffictop_email_verify_token' );
    delete_user_meta( $user_id, 'traffictop_email_verify_expiry' );

    return true;
}

/**
 * Check if user's email is verified
 */
function traffictop_is_email_verified( $user_id ) {
    // Admins are always verified
    if ( user_can( $user_id, 'manage_options' ) ) return true;
    return get_user_meta( $user_id, 'traffictop_email_verified', true ) === '1';
}

/**
 * Resend verification email (AJAX)
 */
add_action( 'wp_ajax_nopriv_traffictop_resend_verification', 'traffictop_ajax_resend_verification' );
add_action( 'wp_ajax_traffictop_resend_verification', 'traffictop_ajax_resend_verification' );
function traffictop_ajax_resend_verification() {
    $rate = traffictop_rate_limit_check( 'report_issue' );
    if ( ! $rate['allowed'] ) wp_send_json_error( 'Vui lòng thử lại sau' );
    $username = sanitize_text_field( $_POST['username'] ?? '' );
    if ( empty( $username ) ) wp_send_json_error( 'Thiếu thông tin' );

    $user = get_user_by( 'login', $username );
    if ( ! $user ) $user = get_user_by( 'email', $username );
    if ( ! $user ) wp_send_json_error( 'Không tìm thấy tài khoản' );

    if ( traffictop_is_email_verified( $user->ID ) ) {
        wp_send_json_error( 'Email đã được xác nhận' );
    }

    // Rate limit: 1 resend per 60 seconds
    $last_sent = (int) get_user_meta( $user->ID, 'traffictop_verify_last_sent', true );
    if ( time() - $last_sent < 60 ) {
        wp_send_json_error( 'Vui lòng đợi 60 giây trước khi gửi lại' );
    }

    $sent = traffictop_send_verification_email( $user->ID );
    if ( $sent ) {
        update_user_meta( $user->ID, 'traffictop_verify_last_sent', time() );
        wp_send_json_success( 'Đã gửi lại email xác nhận đến ' . $user->user_email );
    } else {
        wp_send_json_error( 'Không thể gửi email. Vui lòng liên hệ admin.' );
    }
}

/**
 * Admin: Resend verification email (AJAX)
 */
add_action( 'wp_ajax_traffictop_admin_resend_verification', 'traffictop_ajax_admin_resend_verification' );
function traffictop_ajax_admin_resend_verification() {
    check_ajax_referer( 'traffictop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $user_id = absint( $_POST['user_id'] ?? 0 );
    if ( ! $user_id ) wp_send_json_error( 'Thiếu user_id' );

    $user = get_user_by( 'ID', $user_id );
    if ( ! $user ) wp_send_json_error( 'Không tìm thấy user' );

    if ( traffictop_is_email_verified( $user_id ) ) {
        wp_send_json_error( 'Email đã được xác nhận' );
    }

    $sent = traffictop_send_verification_email( $user_id );
    if ( $sent ) {
        update_user_meta( $user_id, 'traffictop_verify_last_sent', time() );
        wp_send_json_success( 'Đã gửi email xác nhận đến ' . $user->user_email );
    } else {
        wp_send_json_error( 'Không thể gửi email' );
    }
}

/* ============================================================
   HELPER: Email template wrapper
   ============================================================ */

function traffictop_email_wrap( $title, $content ) {
    $site_name = get_bloginfo( 'name' );
    return '
    <div style="max-width:560px;margin:0 auto;font-family:Inter,sans-serif;color:#1e293b">
        <div style="text-align:center;padding:30px 0 20px">
            <h2 style="margin:0;font-size:22px;color:#0f172a">' . esc_html( $site_name ) . '</h2>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:32px">
            <h3 style="margin:0 0 16px;font-size:18px;color:#0f172a">' . $title . '</h3>
            ' . $content . '
        </div>
        <p style="text-align:center;color:#94a3b8;font-size:11px;margin-top:20px">&copy; ' . date('Y') . ' ' . esc_html( $site_name ) . '</p>
    </div>';
}

/* ============================================================
   DEPOSIT EMAILS
   ============================================================ */

/**
 * Email admin khi KH nạp tiền (pending)
 */
function traffictop_send_deposit_email( $deposit_id ) {
    if ( ! traffictop_get_option( 'email_deposit_pending', 1 ) ) return;

    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $dep = $wpdb->get_row( $wpdb->prepare("SELECT d.*, u.user_email, u.display_name FROM {$p}customer_deposits d LEFT JOIN {$wpdb->users} u ON d.customer_id = u.ID WHERE d.id=%d", $deposit_id));
    if ( !$dep || !$dep->user_email ) return;

    $admin_email = get_option('admin_email');
    $subject = '[Traffictop.net] Yêu cầu nạp tiền mới - ' . traffictop_format_money($dep->amount);
    $content = '<table style="width:100%;border-collapse:collapse;font-size:14px">';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Khách hàng</td><td style="padding:8px 0;font-weight:600">' . esc_html($dep->display_name) . ' (' . esc_html($dep->user_email) . ')</td></tr>';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Số tiền</td><td style="padding:8px 0;font-weight:600;color:#059669">' . traffictop_format_money($dep->amount) . '</td></tr>';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Phương thức</td><td style="padding:8px 0">' . esc_html(strtoupper($dep->payment_method ?? '')) . '</td></tr>';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Thời gian</td><td style="padding:8px 0">' . esc_html($dep->created_at) . '</td></tr>';
    $content .= '</table>';
    $content .= '<p style="margin:20px 0 0;font-size:13px;color:#64748b">Vui lòng kiểm tra và duyệt trong trang quản trị.</p>';

    wp_mail($admin_email, $subject, traffictop_email_wrap('Yêu cầu nạp tiền mới', $content), array('Content-Type: text/html; charset=UTF-8'));
}

/**
 * Email KH khi nạp tiền được duyệt
 */
function traffictop_send_deposit_approved_email( $deposit_id ) {
    if ( ! traffictop_get_option( 'email_deposit_approved', 1 ) ) return;

    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $dep = $wpdb->get_row( $wpdb->prepare("SELECT d.*, u.user_email, u.display_name FROM {$p}customer_deposits d LEFT JOIN {$wpdb->users} u ON d.customer_id = u.ID WHERE d.id=%d", $deposit_id));
    if ( !$dep || !$dep->user_email ) return;

    $total = floatval($dep->amount) + floatval($dep->bonus_amount);
    $subject = '[Traffictop.net] Nạp tiền thành công - ' . traffictop_format_money($dep->amount);
    $content = '<p style="color:#475569;line-height:1.6">Xin chào <strong>' . esc_html($dep->display_name) . '</strong>,</p>';
    $content .= '<p style="color:#475569;line-height:1.6">Yêu cầu nạp tiền của bạn đã được duyệt thành công.</p>';
    $content .= '<table style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0">';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Số tiền nạp</td><td style="padding:8px 0;font-weight:600">' . traffictop_format_money($dep->amount) . '</td></tr>';
    if ( floatval($dep->bonus_amount) > 0 ) {
        $content .= '<tr><td style="padding:8px 0;color:#64748b">Bonus</td><td style="padding:8px 0;font-weight:600;color:#059669">+' . traffictop_format_money($dep->bonus_amount) . '</td></tr>';
    }
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Tổng cộng</td><td style="padding:8px 0;font-weight:700;color:#059669">' . traffictop_format_money($total) . '</td></tr>';
    $content .= '</table>';
    $content .= '<p style="font-size:13px;color:#64748b">Số dư đã được cộng vào tài khoản của bạn. Bạn có thể tạo chiến dịch ngay.</p>';

    wp_mail($dep->user_email, $subject, traffictop_email_wrap('Nạp tiền thành công', $content), array('Content-Type: text/html; charset=UTF-8'));
}

/**
 * Email KH khi nạp tiền bị từ chối
 */
function traffictop_send_deposit_rejected_email( $deposit_id ) {
    if ( ! traffictop_get_option( 'email_deposit_rejected', 1 ) ) return;

    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $dep = $wpdb->get_row( $wpdb->prepare("SELECT d.*, u.user_email, u.display_name FROM {$p}customer_deposits d LEFT JOIN {$wpdb->users} u ON d.customer_id = u.ID WHERE d.id=%d", $deposit_id));
    if ( !$dep || !$dep->user_email ) return;

    $subject = '[Traffictop.net] Yêu cầu nạp tiền bị từ chối';
    $content = '<p style="color:#475569;line-height:1.6">Xin chào <strong>' . esc_html($dep->display_name) . '</strong>,</p>';
    $content .= '<p style="color:#475569;line-height:1.6">Rất tiếc, yêu cầu nạp tiền <strong>' . traffictop_format_money($dep->amount) . '</strong> của bạn đã bị từ chối.</p>';
    $content .= '<p style="color:#475569;line-height:1.6">Nếu bạn cho rằng đây là nhầm lẫn, vui lòng liên hệ hỗ trợ.</p>';

    wp_mail($dep->user_email, $subject, traffictop_email_wrap('Yêu cầu nạp tiền bị từ chối', $content), array('Content-Type: text/html; charset=UTF-8'));
}

/* ============================================================
   WITHDRAWAL EMAILS
   ============================================================ */

/**
 * Email admin khi user yêu cầu rút tiền (pending)
 */
function traffictop_send_withdrawal_pending_email( $withdrawal_id ) {
    if ( ! traffictop_get_option( 'email_withdrawal_pending', 1 ) ) return;

    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $w = $wpdb->get_row( $wpdb->prepare("SELECT w.*, u.display_name, u.user_email FROM {$p}withdrawals w LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID WHERE w.id=%d", $withdrawal_id));
    if ( !$w ) return;

    $admin_email = get_option('admin_email');
    $subject = '[Traffictop.net] Yêu cầu rút tiền mới - ' . traffictop_format_money($w->amount);
    $content = '<table style="width:100%;border-collapse:collapse;font-size:14px">';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">User</td><td style="padding:8px 0;font-weight:600">' . esc_html($w->display_name) . ' (' . esc_html($w->user_email) . ')</td></tr>';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Số tiền</td><td style="padding:8px 0;font-weight:700;color:#dc2626">' . traffictop_format_money($w->amount) . '</td></tr>';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Phương thức</td><td style="padding:8px 0">' . esc_html(strtoupper($w->payment_method ?? '')) . '</td></tr>';
    if ( ! empty($w->bank_name) ) {
        $content .= '<tr><td style="padding:8px 0;color:#64748b">Ngân hàng</td><td style="padding:8px 0">' . esc_html($w->bank_name) . '</td></tr>';
        $content .= '<tr><td style="padding:8px 0;color:#64748b">STK</td><td style="padding:8px 0;font-weight:600">' . esc_html($w->bank_account) . '</td></tr>';
        $content .= '<tr><td style="padding:8px 0;color:#64748b">Chủ TK</td><td style="padding:8px 0">' . esc_html($w->bank_holder) . '</td></tr>';
    }
    if ( ! empty($w->wallet_address) ) {
        $content .= '<tr><td style="padding:8px 0;color:#64748b">Ví USDT</td><td style="padding:8px 0;font-size:12px;word-break:break-all">' . esc_html($w->wallet_address) . '</td></tr>';
    }
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Thời gian</td><td style="padding:8px 0">' . esc_html($w->created_at) . '</td></tr>';
    $content .= '</table>';
    $content .= '<p style="margin:20px 0 0;font-size:13px;color:#64748b">Vui lòng xử lý trong trang quản trị.</p>';

    wp_mail($admin_email, $subject, traffictop_email_wrap('Yêu cầu rút tiền mới', $content), array('Content-Type: text/html; charset=UTF-8'));
}

/**
 * Email user khi withdrawal thay đổi trạng thái
 */
function traffictop_send_withdrawal_status_email( $withdrawal_id, $new_status ) {
    $setting_map = array(
        'approved'  => 'email_withdrawal_approved',
        'rejected'  => 'email_withdrawal_rejected',
        'completed' => 'email_withdrawal_completed',
    );
    if ( ! isset( $setting_map[$new_status] ) ) return;
    if ( ! traffictop_get_option( $setting_map[$new_status], 1 ) ) return;

    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $w = $wpdb->get_row( $wpdb->prepare("SELECT w.*, u.display_name, u.user_email FROM {$p}withdrawals w LEFT JOIN {$wpdb->users} u ON w.user_id = u.ID WHERE w.id=%d", $withdrawal_id));
    if ( !$w || !$w->user_email ) return;

    $status_info = array(
        'approved'  => array('title' => 'Yêu cầu rút tiền đã được duyệt', 'color' => '#2563eb', 'label' => 'Đã duyệt', 'desc' => 'Yêu cầu rút tiền của bạn đã được duyệt. Chúng tôi sẽ xử lý chuyển tiền trong thời gian sớm nhất.'),
        'rejected'  => array('title' => 'Yêu cầu rút tiền bị từ chối', 'color' => '#dc2626', 'label' => 'Từ chối', 'desc' => 'Rất tiếc, yêu cầu rút tiền của bạn đã bị từ chối. Số tiền đã được hoàn lại vào số dư tài khoản.'),
        'completed' => array('title' => 'Rút tiền thành công', 'color' => '#059669', 'label' => 'Hoàn thành', 'desc' => 'Chúng tôi đã chuyển tiền thành công. Vui lòng kiểm tra tài khoản nhận tiền của bạn.'),
    );
    $info = $status_info[$new_status];

    $subject = '[Traffictop.net] ' . $info['title'] . ' - ' . traffictop_format_money($w->amount);
    $content = '<p style="color:#475569;line-height:1.6">Xin chào <strong>' . esc_html($w->display_name) . '</strong>,</p>';
    $content .= '<p style="color:#475569;line-height:1.6">' . $info['desc'] . '</p>';
    $content .= '<table style="width:100%;border-collapse:collapse;font-size:14px;margin:16px 0">';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Mã lệnh</td><td style="padding:8px 0">#' . $w->id . '</td></tr>';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Số tiền</td><td style="padding:8px 0;font-weight:700">' . traffictop_format_money($w->amount) . '</td></tr>';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Trạng thái</td><td style="padding:8px 0"><span style="background:' . $info['color'] . ';color:#fff;padding:3px 10px;border-radius:4px;font-size:12px;font-weight:600">' . $info['label'] . '</span></td></tr>';
    if ( $new_status === 'rejected' && ! empty($w->admin_note) ) {
        $content .= '<tr><td style="padding:8px 0;color:#64748b">Lý do</td><td style="padding:8px 0;color:#dc2626">' . esc_html($w->admin_note) . '</td></tr>';
    }
    $content .= '</table>';

    wp_mail($w->user_email, $subject, traffictop_email_wrap($info['title'], $content), array('Content-Type: text/html; charset=UTF-8'));
}

/* ============================================================
   REPORT ERROR EMAIL
   ============================================================ */

/**
 * Email admin khi user báo lỗi mã xác minh
 */
function traffictop_send_report_error_email( $session_id, $error_type, $error_message ) {
    if ( ! traffictop_get_option( 'email_report_error', 1 ) ) return;

    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $visit = $wpdb->get_row( $wpdb->prepare(
        "SELECT v.*, u.display_name, u.user_login, kc.title as campaign_title, kc.target_url
         FROM {$p}shortlink_visits v
         LEFT JOIN {$wpdb->users} u ON v.user_id = u.ID
         LEFT JOIN {$p}keyword_campaigns kc ON v.campaign_id = kc.id
         WHERE v.session_id = %s", $session_id ));

    $admin_email = get_option('admin_email');
    $subject = '[Traffictop.net] User báo lỗi mã - ' . esc_html($error_type);
    $content = '<table style="width:100%;border-collapse:collapse;font-size:14px">';
    if ( $visit ) {
        $content .= '<tr><td style="padding:8px 0;color:#64748b">User</td><td style="padding:8px 0;font-weight:600">' . esc_html($visit->display_name ?: $visit->user_login ?: 'Khách') . '</td></tr>';
        $content .= '<tr><td style="padding:8px 0;color:#64748b">Session</td><td style="padding:8px 0;font-size:12px;font-family:monospace">' . esc_html($session_id) . '</td></tr>';
        $content .= '<tr><td style="padding:8px 0;color:#64748b">Chiến dịch</td><td style="padding:8px 0">' . esc_html($visit->campaign_title ?: '—') . '</td></tr>';
        $content .= '<tr><td style="padding:8px 0;color:#64748b">Step</td><td style="padding:8px 0">' . esc_html($visit->step) . '</td></tr>';
        $content .= '<tr><td style="padding:8px 0;color:#64748b">IP</td><td style="padding:8px 0">' . esc_html($visit->ip_address) . '</td></tr>';
    }
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Loại lỗi</td><td style="padding:8px 0;font-weight:600;color:#dc2626">' . esc_html($error_type) . '</td></tr>';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Nội dung</td><td style="padding:8px 0">' . esc_html($error_message) . '</td></tr>';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Thời gian</td><td style="padding:8px 0">' . traffictop_current_time() . '</td></tr>';
    $content .= '</table>';

    wp_mail($admin_email, $subject, traffictop_email_wrap('User báo lỗi mã xác minh', $content), array('Content-Type: text/html; charset=UTF-8'));
}

/* ============================================================
   CAMPAIGN EMAIL
   ============================================================ */

/**
 * Email admin khi KH tạo chiến dịch mới
 */
function traffictop_send_new_campaign_email( $campaign_id ) {
    if ( ! traffictop_get_option( 'email_campaign_new', 1 ) ) return;

    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $c = $wpdb->get_row( $wpdb->prepare(
        "SELECT kc.*, u.display_name, u.user_email FROM {$p}keyword_campaigns kc LEFT JOIN {$wpdb->users} u ON kc.customer_id = u.ID WHERE kc.id=%d", $campaign_id ));
    if ( !$c ) return;

    $type_labels = array( 'keyword_search' => 'Keyword Search', 'traffic_direct' => 'Direct Traffic', 'traffic_social' => 'Social Traffic' );
    $traffic_labels = array( '1step' => '1 bước', '2step' => '2 bước', 'nocode' => 'Mã cố định' );

    $admin_email = get_option('admin_email');
    $subject = '[Traffictop.net] Chiến dịch mới cần duyệt - ' . esc_html($c->title);
    $content = '<table style="width:100%;border-collapse:collapse;font-size:14px">';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Khách hàng</td><td style="padding:8px 0;font-weight:600">' . esc_html($c->display_name) . '</td></tr>';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Tên chiến dịch</td><td style="padding:8px 0;font-weight:600">' . esc_html($c->title) . '</td></tr>';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">URL đích</td><td style="padding:8px 0;font-size:12px;word-break:break-all"><a href="' . esc_url($c->target_url) . '">' . esc_html($c->target_url) . '</a></td></tr>';
    if ( ! empty($c->keyword) ) {
        $content .= '<tr><td style="padding:8px 0;color:#64748b">Từ khóa</td><td style="padding:8px 0">' . esc_html($c->keyword) . '</td></tr>';
    }
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Loại</td><td style="padding:8px 0">' . esc_html($type_labels[$c->campaign_type ?? ''] ?? $c->campaign_type) . ' / ' . esc_html($traffic_labels[$c->traffic_type ?? ''] ?? $c->traffic_type) . '</td></tr>';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Số lượng</td><td style="padding:8px 0">' . number_format($c->quantity) . ' views</td></tr>';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Giá/view</td><td style="padding:8px 0;font-weight:600">' . traffictop_format_money($c->price_per_view) . '</td></tr>';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Traffic/ngày</td><td style="padding:8px 0">' . number_format($c->daily_traffic) . '</td></tr>';
    $content .= '<tr><td style="padding:8px 0;color:#64748b">Thời gian</td><td style="padding:8px 0">' . esc_html($c->created_at) . '</td></tr>';
    $content .= '</table>';
    $content .= '<p style="margin:20px 0 0;font-size:13px;color:#64748b">Vui lòng kiểm tra và duyệt chiến dịch trong trang quản trị.</p>';

    wp_mail($admin_email, $subject, traffictop_email_wrap('Chiến dịch mới cần duyệt', $content), array('Content-Type: text/html; charset=UTF-8'));
}
