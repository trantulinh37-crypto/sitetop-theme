<?php
/**
 * LinkNgon V2 - Email Notifications
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   EMAIL VERIFICATION
   ============================================================ */

/**
 * Send verification email to newly registered user
 */
function linkngon_send_verification_email( $user_id ) {
    $user = get_user_by( 'ID', $user_id );
    if ( ! $user ) return false;

    $token = bin2hex( random_bytes(32) );
    $expiry = time() + 86400; // 24 hours
    update_user_meta( $user_id, 'linkngon_email_verify_token', $token );
    update_user_meta( $user_id, 'linkngon_email_verify_expiry', $expiry );

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
function linkngon_verify_email_token( $user_id, $token ) {
    $stored_token  = get_user_meta( $user_id, 'linkngon_email_verify_token', true );
    $stored_expiry = (int) get_user_meta( $user_id, 'linkngon_email_verify_expiry', true );

    if ( empty( $stored_token ) || $stored_token !== $token ) {
        return 'Link xác nhận không hợp lệ';
    }
    if ( time() > $stored_expiry ) {
        return 'Link xác nhận đã hết hạn. Vui lòng đăng nhập để gửi lại email xác nhận.';
    }

    // Mark email as verified
    update_user_meta( $user_id, 'linkngon_email_verified', '1' );
    delete_user_meta( $user_id, 'linkngon_email_verify_token' );
    delete_user_meta( $user_id, 'linkngon_email_verify_expiry' );

    return true;
}

/**
 * Check if user's email is verified
 */
function linkngon_is_email_verified( $user_id ) {
    // Admins are always verified
    if ( user_can( $user_id, 'manage_options' ) ) return true;
    return get_user_meta( $user_id, 'linkngon_email_verified', true ) === '1';
}

/**
 * Resend verification email (AJAX)
 */
add_action( 'wp_ajax_nopriv_linkngon_resend_verification', 'linkngon_ajax_resend_verification' );
add_action( 'wp_ajax_linkngon_resend_verification', 'linkngon_ajax_resend_verification' );
function linkngon_ajax_resend_verification() {
    $username = sanitize_text_field( $_POST['username'] ?? '' );
    if ( empty( $username ) ) wp_send_json_error( 'Thiếu thông tin' );

    $user = get_user_by( 'login', $username );
    if ( ! $user ) $user = get_user_by( 'email', $username );
    if ( ! $user ) wp_send_json_error( 'Không tìm thấy tài khoản' );

    if ( linkngon_is_email_verified( $user->ID ) ) {
        wp_send_json_error( 'Email đã được xác nhận' );
    }

    // Rate limit: 1 resend per 60 seconds
    $last_sent = (int) get_user_meta( $user->ID, 'linkngon_verify_last_sent', true );
    if ( time() - $last_sent < 60 ) {
        wp_send_json_error( 'Vui lòng đợi 60 giây trước khi gửi lại' );
    }

    $sent = linkngon_send_verification_email( $user->ID );
    if ( $sent ) {
        update_user_meta( $user->ID, 'linkngon_verify_last_sent', time() );
        wp_send_json_success( 'Đã gửi lại email xác nhận đến ' . $user->user_email );
    } else {
        wp_send_json_error( 'Không thể gửi email. Vui lòng liên hệ admin.' );
    }
}

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
