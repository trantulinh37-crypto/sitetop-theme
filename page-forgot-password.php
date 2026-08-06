<?php
/**
 * Template Name: Quên mật khẩu
 * SiteTop.net V2 - Forgot Password Page
 * Handles both: request reset link AND set new password (from email link)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( is_user_logged_in() ) {
    wp_redirect( sitetop_get_dashboard_url() );
    exit;
}

$error = '';
$success = '';
$step = 'request'; // request | reset | done

// Step 2: User clicked reset link from email (?key=...&login=...)
if ( isset( $_GET['key'] ) && isset( $_GET['login'] ) ) {
    $reset_key = sanitize_text_field( $_GET['key'] );
    $user_login = sanitize_text_field( $_GET['login'] );
    $user = check_password_reset_key( $reset_key, $user_login );

    if ( is_wp_error( $user ) ) {
        $error = 'Link đặt lại mật khẩu không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu link mới.';
    } else {
        $step = 'reset';
    }
}

// Step 2b: Handle new password submission
if ( isset( $_POST['reset_password_submit'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'sitetop_reset_password' ) ) {
    $reset_key = sanitize_text_field( $_POST['reset_key'] ?? '' );
    $user_login = sanitize_text_field( $_POST['user_login'] ?? '' );
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $user = check_password_reset_key( $reset_key, $user_login );

    if ( is_wp_error( $user ) ) {
        $error = 'Link đặt lại đã hết hạn. Vui lòng yêu cầu link mới.';
    } elseif ( strlen( $new_password ) < 6 ) {
        $error = 'Mật khẩu tối thiểu 6 ký tự';
        $step = 'reset';
    } elseif ( $new_password !== $confirm_password ) {
        $error = 'Mật khẩu xác nhận không khớp';
        $step = 'reset';
    } else {
        reset_password( $user, $new_password );
        // Evict any existing sessions (e.g. an attacker already logged in) after a reset.
        WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
        $step = 'done';
        $success = 'Mật khẩu đã được đặt lại thành công!';
    }
}

// Step 1: Request reset link
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ! isset( $_POST['reset_password_submit'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'sitetop_forgot_password' ) ) {
    $user_login = sanitize_text_field( $_POST['user_login'] ?? '' );

    if ( empty( $user_login ) ) {
        $error = 'Vui lòng nhập email hoặc tên đăng nhập';
    } else {
        // Rate-limit reset requests (per-IP) to curb enumeration + email bombing.
        $fp_rate = function_exists( 'sitetop_rate_limit_check' ) ? sitetop_rate_limit_check( 'forgot_password' ) : array( 'allowed' => true );
        if ( empty( $fp_rate['allowed'] ) ) {
            $error = 'Bạn đã yêu cầu quá nhiều lần. Vui lòng thử lại sau ít phút.';
        } else {
            // H2: do NOT reveal whether the account exists. Send the email only if it does,
            // but always return the same generic message either way.
            $user = is_email( $user_login ) ? get_user_by( 'email', $user_login ) : get_user_by( 'login', $user_login );
            if ( $user ) {
                retrieve_password( $user->user_login );
            }
            $success = 'Nếu tài khoản tồn tại, link đặt lại mật khẩu đã được gửi đến email. Vui lòng kiểm tra hộp thư (và cả spam).';
        }
    }
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quên mật khẩu - <?php bloginfo( 'name' ); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
<?php wp_head(); ?>
<?php include get_template_directory() . '/includes/auth-styles.php'; ?>
</head>
<body>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <?php $fp_icon = get_option('sitetop_widget_icon',''); ?>
            <?php if($fp_icon): ?><img src="<?php echo esc_url($fp_icon); ?>" width="36" height="36" alt="" style="border-radius:8px"><?php else: ?><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><?php endif; ?>
            <span>SiteTop.net</span>
        </div>

            <?php if ( $step === 'reset' ) : ?>
                <div class="auth-form-header">
                    <h2>Đặt mật khẩu mới</h2>
                    <p>Nhập mật khẩu mới cho tài khoản của bạn</p>
                </div>

                <?php if ( $error ) : ?>
                    <div class="auth-error">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <?php echo esc_html( $error ); ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <?php wp_nonce_field( 'sitetop_reset_password' ); ?>
                    <input type="hidden" name="reset_key" value="<?php echo esc_attr( $reset_key ?? '' ); ?>">
                    <input type="hidden" name="user_login" value="<?php echo esc_attr( $user_login ?? '' ); ?>">

                    <div class="fg">
                        <label for="new-password">Mật khẩu mới</label>
                        <div class="fg-input-wrap">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" id="new-password" name="new_password" required minlength="6" placeholder="Tối thiểu 6 ký tự" autocomplete="new-password">
                            <button type="button" class="pw-toggle" onclick="togglePw('new-password',this)" aria-label="Hiện mật khẩu">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="fg">
                        <label for="confirm-password">Xác nhận mật khẩu</label>
                        <div class="fg-input-wrap">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <input type="password" id="confirm-password" name="confirm_password" required minlength="6" placeholder="Nhập lại mật khẩu" autocomplete="new-password">
                            <button type="button" class="pw-toggle" onclick="togglePw('confirm-password',this)" aria-label="Hiện mật khẩu">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" name="reset_password_submit" value="1" class="auth-btn">
                        Đặt lại mật khẩu
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>
                </form>

            <?php elseif ( $step === 'done' ) : ?>
                <div class="auth-form-header">
                    <h2>Thành công!</h2>
                </div>
                <div class="auth-success">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <?php echo esc_html( $success ); ?>
                </div>
                <div style="text-align:center;margin-top:20px;">
                    <a href="<?php echo home_url('/dang-nhap'); ?>" class="auth-btn" style="display:inline-flex;text-decoration:none;">
                        Đăng nhập ngay
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                </div>

            <?php else : ?>
                <div class="auth-form-header">
                    <h2>Quên mật khẩu</h2>
                    <p>Nhập email hoặc tên đăng nhập để nhận link đặt lại mật khẩu</p>
                </div>

                <?php if ( $error ) : ?>
                    <div class="auth-error">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <?php echo esc_html( $error ); ?>
                    </div>
                <?php endif; ?>

                <?php if ( $success ) : ?>
                    <div class="auth-success">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <?php echo esc_html( $success ); ?>
                    </div>
                <?php else : ?>
                    <form method="post">
                        <?php wp_nonce_field( 'sitetop_forgot_password' ); ?>
                        <div class="fg">
                            <label for="user-login">Email hoặc tên đăng nhập</label>
                            <div class="fg-input-wrap">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                <input type="text" id="user-login" name="user_login" required placeholder="Email hoặc tên đăng nhập" autocomplete="username" value="<?php echo esc_attr( $_POST['user_login'] ?? '' ); ?>">
                            </div>
                        </div>
                        <button type="submit" class="auth-btn">
                            Gửi link đặt lại
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <div class="auth-divider">hoặc</div>
            <div class="auth-footer">
                <a href="<?php echo home_url('/dang-nhap'); ?>">Quay lại đăng nhập</a>
            </div>
    </div>
</div>

<?php include get_template_directory() . '/includes/auth-scripts.php'; ?>
<?php wp_footer(); ?>
</body>
</html>
