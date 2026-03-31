<?php
/**
 * Template Name: Đăng nhập
 * LinkNgon V2 - Login Page
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( is_user_logged_in() ) {
    wp_redirect( home_url( '/dashboard' ) );
    exit;
}

$error = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $creds = array(
        'user_login'    => sanitize_text_field( $_POST['username'] ?? '' ),
        'user_password' => $_POST['password'] ?? '',
        'remember'      => ! empty( $_POST['remember'] ),
    );
    $user = wp_signon( $creds, is_ssl() );
    if ( is_wp_error( $user ) ) {
        $error = 'Sai tên đăng nhập hoặc mật khẩu';
    } else {
        $redirect = $_GET['redirect_to'] ?? home_url( '/dashboard' );
        wp_redirect( $redirect );
        exit;
    }
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

<div class="auth-split">
    <?php include get_template_directory() . '/includes/auth-brand.php'; ?>

    <div class="auth-form-panel">
        <div class="auth-form-wrap">
            <?php include get_template_directory() . '/includes/auth-mobile-logo.php'; ?>

            <div class="auth-form-header">
                <h2>Chào mừng trở lại</h2>
                <p>Chưa có tài khoản? <a href="<?php echo home_url('/dang-ky'); ?>">Đăng ký miễn phí</a></p>
            </div>

            <?php if ( $error ) : ?>
                <div class="auth-error">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php echo esc_html( $error ); ?>
                </div>
            <?php endif; ?>

            <form method="post">
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
</div>

<?php include get_template_directory() . '/includes/auth-scripts.php'; ?>
<?php wp_footer(); ?>
</body>
</html>
