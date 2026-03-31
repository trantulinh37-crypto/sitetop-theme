<?php
/**
 * Template Name: Quên mật khẩu
 * LinkNgon V2 - Forgot Password Page
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( is_user_logged_in() ) {
    wp_redirect( home_url( '/dashboard' ) );
    exit;
}

$error = '';
$success = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $user_login = sanitize_text_field( $_POST['user_login'] ?? '' );

    if ( empty( $user_login ) ) {
        $error = 'Vui lòng nhập email hoặc tên đăng nhập';
    } else {
        // Find user by email or username
        $user = is_email( $user_login ) ? get_user_by( 'email', $user_login ) : get_user_by( 'login', $user_login );

        if ( ! $user ) {
            $error = 'Không tìm thấy tài khoản với thông tin này';
        } else {
            $result = retrieve_password( $user->user_login );
            if ( is_wp_error( $result ) ) {
                $error = $result->get_error_message();
            } else {
                $success = 'Link đặt lại mật khẩu đã được gửi đến email của bạn. Vui lòng kiểm tra hộp thư (và cả spam).';
            }
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

<div class="auth-split">
    <?php include get_template_directory() . '/includes/auth-brand.php'; ?>

    <div class="auth-form-panel">
        <div class="auth-form-wrap">
            <?php include get_template_directory() . '/includes/auth-mobile-logo.php'; ?>

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
            <?php endif; ?>

            <?php if ( ! $success ) : ?>
            <form method="post">
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

            <div class="auth-divider">hoặc</div>
            <div class="auth-footer">
                <a href="<?php echo home_url('/dang-nhap'); ?>">Quay lại đăng nhập</a>
            </div>
        </div>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
