<?php
/**
 * Template Name: Đăng nhập / Đăng ký
 * LinkNgon V2 - Custom Login Page
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( is_user_logged_in() ) {
    wp_redirect( home_url( '/dashboard' ) );
    exit;
}

$action = $_GET['action'] ?? 'login';
$error = '';
$success = '';

// Handle registration
if ( $action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $username = sanitize_user( $_POST['username'] ?? '' );
    $email    = sanitize_email( $_POST['email'] ?? '' );
    $password = $_POST['password'] ?? '';

    if ( empty( $username ) || empty( $email ) || empty( $password ) ) {
        $error = 'Vui lòng điền đầy đủ thông tin';
    } elseif ( username_exists( $username ) ) {
        $error = 'Tên đăng nhập đã tồn tại';
    } elseif ( email_exists( $email ) ) {
        $error = 'Email đã được sử dụng';
    } elseif ( strlen( $password ) < 6 ) {
        $error = 'Mật khẩu tối thiểu 6 ký tự';
    } else {
        $user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            $error = $user_id->get_error_message();
        } else {
            wp_set_auth_cookie( $user_id );
            wp_redirect( home_url( '/dashboard' ) );
            exit;
        }
    }
}

// Handle login
if ( $action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
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
<title><?php echo $action === 'register' ? 'Đăng ký' : 'Đăng nhập'; ?> - <?php bloginfo( 'name' ); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<?php wp_head(); ?>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Inter', sans-serif;
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    background: #F7F5F0;
    background-image:
        radial-gradient(circle at 15% 85%, rgba(13,79,79,0.04) 0%, transparent 50%),
        radial-gradient(circle at 85% 15%, rgba(232,168,56,0.04) 0%, transparent 50%);
    padding: 20px;
}
.auth-card {
    background: #fff; border-radius: 20px; padding: 48px 40px;
    max-width: 420px; width: 100%;
    box-shadow: 0 10px 40px rgba(0,0,0,0.06);
    position: relative; overflow: hidden;
}
.auth-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, #0D4F4F, #1A7A7A, #E8A838);
}
.auth-logo {
    text-align: center; margin-bottom: 32px;
}
.auth-logo a {
    font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 24px; color: #0D4F4F; text-decoration: none;
}
.auth-tabs {
    display: flex; gap: 4px; background: #F7F5F0; padding: 4px; border-radius: 10px; margin-bottom: 28px;
}
.auth-tab {
    flex: 1; padding: 10px; text-align: center; border-radius: 8px;
    font-size: 14px; font-weight: 600; color: #6B7280; text-decoration: none; transition: all 0.2s;
}
.auth-tab.active { background: #0D4F4F; color: #fff; }
.auth-tab:hover:not(.active) { color: #2C2C3A; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #2C2C3A; }
.form-group input {
    width: 100%; padding: 12px 16px; border: 1px solid #E5E2DB; border-radius: 10px;
    font-family: 'Inter', sans-serif; font-size: 14px; transition: border-color 0.2s;
}
.form-group input:focus { outline: none; border-color: #0D4F4F; box-shadow: 0 0 0 3px rgba(13,79,79,0.1); }
.auth-btn {
    width: 100%; padding: 14px; background: #0D4F4F; color: #fff; border: none;
    border-radius: 10px; font-family: 'Inter', sans-serif;
    font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.25s;
}
.auth-btn:hover { background: #1A7A7A; transform: translateY(-1px); }
.auth-error { background: #FEE2E2; color: #991B1B; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; }
.auth-footer { text-align: center; margin-top: 24px; font-size: 13px; color: #9CA3AF; }
.auth-footer a { color: #0D4F4F; font-weight: 600; }
.remember-row { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; font-size: 13px; color: #6B7280; }
@media(max-width:480px) { .auth-card { padding: 32px 24px; } }
</style>
</head>
<body>
<div class="auth-card">
    <div class="auth-logo">
        <a href="<?php echo home_url(); ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>LinkNgon</a>
    </div>

    <div class="auth-tabs">
        <a href="?action=login" class="auth-tab <?php echo $action === 'login' ? 'active' : ''; ?>">Đăng nhập</a>
        <a href="?action=register" class="auth-tab <?php echo $action === 'register' ? 'active' : ''; ?>">Đăng ký</a>
    </div>

    <?php if ( $error ) : ?>
        <div class="auth-error"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> <?php echo esc_html( $error ); ?></div>
    <?php endif; ?>

    <?php if ( $action === 'register' ) : ?>
    <form method="post">
        <div class="form-group">
            <label>Tên đăng nhập</label>
            <input type="text" name="username" required placeholder="vd: nguyenvana" autocomplete="username">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required placeholder="email@example.com" autocomplete="email">
        </div>
        <div class="form-group">
            <label>Mật khẩu</label>
            <input type="password" name="password" required minlength="6" placeholder="Tối thiểu 6 ký tự" autocomplete="new-password">
        </div>
        <button type="submit" class="auth-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>Tạo tài khoản</button>
    </form>
    <div class="auth-footer">Đã có tài khoản? <a href="?action=login">Đăng nhập</a></div>

    <?php else : ?>
    <form method="post">
        <div class="form-group">
            <label>Tên đăng nhập hoặc Email</label>
            <input type="text" name="username" required autocomplete="username">
        </div>
        <div class="form-group">
            <label>Mật khẩu</label>
            <input type="password" name="password" required autocomplete="current-password">
        </div>
        <div class="remember-row">
            <input type="checkbox" name="remember" id="remember">
            <label for="remember" style="margin:0;font-weight:400;">Ghi nhớ đăng nhập</label>
        </div>
        <button type="submit" class="auth-btn">Đăng nhập</button>
    </form>
    <div class="auth-footer">Chưa có tài khoản? <a href="?action=register">Đăng ký ngay</a></div>
    <?php endif; ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
