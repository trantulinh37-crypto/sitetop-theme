<?php
/**
 * Template Name: Đăng ký
 * LinkNgon V2 - Register Page
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( isset( $_GET['debug'] ) ) {
    error_reporting( E_ALL );
    ini_set( 'display_errors', 1 );
    echo '<!-- REGISTER TEMPLATE LOADED -->';
}

if ( is_user_logged_in() ) {
    wp_redirect( linkngon_get_dashboard_url() );
    exit;
}

$error = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'linkngon_register' ) ) {
    $username = sanitize_user( $_POST['username'] ?? '' );
    $email    = sanitize_email( $_POST['email'] ?? '' );
    $phone    = sanitize_text_field( $_POST['phone'] ?? '' );
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ( empty( $username ) || empty( $email ) || empty( $password ) ) {
        $error = 'Vui lòng điền đầy đủ thông tin';
    } elseif ( username_exists( $username ) ) {
        $error = 'Tên đăng nhập đã tồn tại';
    } elseif ( email_exists( $email ) ) {
        $error = 'Email đã được sử dụng';
    } elseif ( strlen( $password ) < 6 ) {
        $error = 'Mật khẩu tối thiểu 6 ký tự';
    } elseif ( $password !== $password2 ) {
        $error = 'Mật khẩu xác nhận không khớp';
    } else {
        $user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            $error = $user_id->get_error_message();
        } else {
            if ( ! empty( $phone ) ) {
                update_user_meta( $user_id, 'phone', $phone );
            }
            wp_set_auth_cookie( $user_id );
            wp_redirect( linkngon_get_dashboard_url() );
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đăng ký - <?php bloginfo( 'name' ); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
<?php wp_head(); ?>
<?php include get_template_directory() . '/includes/auth-styles.php'; ?>
</head>
<body>

<div class="auth-split">
    <?php include get_template_directory() . '/includes/auth-brand.php'; ?>

    <div class="auth-form-panel">
        <div class="auth-form-wrap wide">
            <?php include get_template_directory() . '/includes/auth-mobile-logo.php'; ?>

            <div class="auth-form-header">
                <h2>Tạo tài khoản</h2>
                <p>Đã có tài khoản? <a href="<?php echo home_url('/dang-nhap'); ?>">Đăng nhập</a></p>
            </div>

            <?php if ( $error ) : ?>
                <div class="auth-error">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php echo esc_html( $error ); ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( 'linkngon_register' ); ?>
                <div class="fg-row">
                    <div class="fg">
                        <label for="reg-username">Tên đăng nhập</label>
                        <div class="fg-input-wrap">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <input type="text" id="reg-username" name="username" required placeholder="vd: nguyenvana" autocomplete="username" value="<?php echo esc_attr( $_POST['username'] ?? '' ); ?>">
                        </div>
                    </div>
                    <div class="fg">
                        <label for="reg-phone">Số điện thoại</label>
                        <div class="fg-input-wrap">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            <input type="tel" id="reg-phone" name="phone" placeholder="0912 345 678" autocomplete="tel" value="<?php echo esc_attr( $_POST['phone'] ?? '' ); ?>">
                        </div>
                    </div>
                </div>

                <div class="fg">
                    <label for="reg-email">Email</label>
                    <div class="fg-input-wrap">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <input type="email" id="reg-email" name="email" required placeholder="email@example.com" autocomplete="email" value="<?php echo esc_attr( $_POST['email'] ?? '' ); ?>">
                    </div>
                </div>

                <div class="fg-row">
                    <div class="fg">
                        <label for="reg-password">Mật khẩu</label>
                        <div class="fg-input-wrap">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" id="reg-password" name="password" required minlength="6" placeholder="Tối thiểu 6 ký tự" autocomplete="new-password">
                            <button type="button" class="pw-toggle" onclick="togglePw('reg-password',this)" aria-label="Hiện mật khẩu">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="fg">
                        <label for="reg-password2">Xác nhận mật khẩu</label>
                        <div class="fg-input-wrap">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <input type="password" id="reg-password2" name="password2" required minlength="6" placeholder="Nhập lại mật khẩu" autocomplete="new-password">
                            <button type="button" class="pw-toggle" onclick="togglePw('reg-password2',this)" aria-label="Hiện mật khẩu">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="auth-btn">
                    Tạo tài khoản
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
