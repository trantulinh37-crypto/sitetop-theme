<?php
/**
 * Template Name: Đăng nhập
 * Traffictop.net V2 - Login Page
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( is_user_logged_in() ) {
    wp_redirect( traffictop_get_dashboard_url() );
    exit;
}

$error = '';
$success = '';
$need_verify = false;
$verify_username = '';

// Handle email verification link
if ( isset( $_GET['action'] ) && $_GET['action'] === 'verify_email' && isset( $_GET['token'], $_GET['uid'] ) ) {
    $uid = intval( $_GET['uid'] );
    $token = sanitize_text_field( $_GET['token'] );
    $result = traffictop_verify_email_token( $uid, $token );
    if ( $result === true ) {
        $success = 'Email đã được xác nhận thành công! Bạn có thể đăng nhập ngay.';
    } else {
        $error = $result;
    }
}

// Show message after registration
$pending_notice = '';
if ( isset( $_GET['registered'] ) ) {
    if ( isset( $_GET['pending'] ) ) {
        // Khách hàng: dùng kích hoạt thủ công (không email). Hiện hướng dẫn liên hệ Admin.
        $success = 'Đăng ký thành công! Đăng nhập để xem trạng thái tài khoản.';
        if ( function_exists( 'traffictop_pending_notice_html' ) ) {
            $pending_notice = traffictop_pending_notice_html( false );
        }
    } else {
        $success = 'Đăng ký thành công! Vui lòng kiểm tra email để xác nhận tài khoản.';
    }
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'traffictop_login' ) ) {
    // H1: brute-force throttle — per-IP, 10 attempts / 5 min. Per-IP (not per-username)
    // so an attacker can't lock out a victim by spamming their username.
    $login_rate = function_exists( 'traffictop_rate_limit_check' ) ? traffictop_rate_limit_check( 'login' ) : array( 'allowed' => true );
    if ( empty( $login_rate['allowed'] ) ) {
        $error = 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau ít phút.';
    } else {
    $login_username = sanitize_text_field( $_POST['username'] ?? '' );
    $creds = array(
        'user_login'    => $login_username,
        'user_password' => $_POST['password'] ?? '',
        'remember'      => ! empty( $_POST['remember'] ),
    );
    $user = wp_signon( $creds, is_ssl() );
    if ( is_wp_error( $user ) ) {
        $code = $user->get_error_code();
        if ( $code === 'traffictop_banned' || $code === 'traffictop_customer_banned' ) {
            $error = 'Tài khoản đã bị cấm. Vui lòng liên hệ quản trị viên.';
        } else {
            $error = 'Sai tên đăng nhập hoặc mật khẩu';
        }
    } else {
        // Check email verification
        if ( ! traffictop_is_email_verified( $user->ID ) ) {
            wp_logout();
            $error = 'Email chưa được xác nhận. Vui lòng kiểm tra hộp thư của bạn.';
            $need_verify = true;
            $verify_username = $login_username;
        } else {
            // M2: validate redirect target to same host (else fall back to dashboard) — no open redirect.
            $redirect = wp_validate_redirect( $_GET['redirect_to'] ?? '', traffictop_get_dashboard_url( $user ) );
            wp_safe_redirect( $redirect );
            exit;
        }
    }
    } // end H1 rate-limit else
} elseif ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $error = 'Phiên làm việc hết hạn, vui lòng thử lại';
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đăng nhập - <?php bloginfo( 'name' ); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
<?php wp_head(); ?>
<?php include get_template_directory() . '/includes/auth-styles.php'; ?>
</head>
<body>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <?php $ln_icon = get_option('traffictop_widget_icon',''); ?>
            <a href="<?php echo home_url(); ?>">
                <?php if($ln_icon): ?><img src="<?php echo esc_url($ln_icon); ?>" width="28" height="28" alt=""><?php else: ?><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><?php endif; ?>
                Traffictop.net
            </a>
        </div>

        <div class="auth-form-header">
            <h2>Đăng nhập</h2>
            <p>Chưa có tài khoản? <a href="<?php echo home_url('/dang-ky'); ?>">Đăng ký miễn phí</a></p>
        </div>

            <?php if ( $success ) : ?>
                <div class="auth-success" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:8px">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <?php echo esc_html( $success ); ?>
                </div>
            <?php endif; ?>

            <?php if ( $pending_notice ) { echo $pending_notice; /* đã escape trong helper */ } ?>

            <?php if ( $error ) : ?>
                <div class="auth-error">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php echo esc_html( $error ); ?>
                </div>
                <?php if ( $need_verify ) : ?>
                <div style="text-align:center;margin-bottom:16px">
                    <button type="button" id="resendBtn" onclick="resendVerification()" style="background:none;border:none;color:#2563eb;font-weight:600;font-size:13px;cursor:pointer;text-decoration:underline">Gửi lại email xác nhận</button>
                    <div id="resendMsg" style="font-size:12px;margin-top:6px;color:#64748b"></div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( 'traffictop_login' ); ?>
                <div class="fg">
                    <label for="login-username">Tên đăng nhập hoặc Email</label>
                    <div class="fg-input-wrap">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" id="login-username" name="username" required autocomplete="username">
                    </div>
                </div>
                <div class="fg">
                    <label for="login-password">Mật khẩu</label>
                    <div class="fg-input-wrap">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="login-password" name="password" required autocomplete="current-password">
                        <button type="button" class="pw-toggle" onclick="togglePw('login-password',this)" aria-label="Hiện mật khẩu">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                <div class="remember-row">
                    <div class="remember-left">
                        <input type="checkbox" name="remember" id="remember">
                        <label for="remember">Ghi nhớ đăng nhập</label>
                    </div>
                    <a href="<?php echo home_url('/quen-mat-khau'); ?>" class="forgot-link">Quên mật khẩu?</a>
                </div>
                <button type="submit" class="auth-btn">
                    Đăng nhập
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>

        <div class="auth-divider">hoặc</div>
        <div class="auth-footer">
            <a href="<?php echo home_url(); ?>">Quay về trang chủ</a>
        </div>
    </div>
</div>

<?php include get_template_directory() . '/includes/auth-scripts.php'; ?>
<?php if ( $need_verify ) : ?>
<script>
function resendVerification(){
    var btn=document.getElementById('resendBtn');
    var msg=document.getElementById('resendMsg');
    btn.disabled=true;btn.textContent='Đang gửi...';
    var fd=new FormData();
    fd.append('action','traffictop_resend_verification');
    fd.append('username','<?php echo esc_js( $verify_username ); ?>');
    fetch('<?php echo admin_url("admin-ajax.php"); ?>',{method:'POST',body:fd})
    .then(function(r){return r.json()})
    .then(function(d){
        btn.disabled=false;
        if(d.success){
            msg.style.color='#166534';msg.textContent=d.data;
            btn.textContent='Đã gửi';btn.disabled=true;
            setTimeout(function(){btn.disabled=false;btn.textContent='Gửi lại email xác nhận';},60000);
        } else {
            msg.style.color='#dc2626';msg.textContent=d.data;
            btn.textContent='Gửi lại email xác nhận';
        }
    })
    .catch(function(){btn.disabled=false;btn.textContent='Gửi lại email xác nhận';msg.style.color='#dc2626';msg.textContent='Lỗi kết nối';});
}
</script>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
