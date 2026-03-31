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

$is_register = ( $action === 'register' );
$page_url = get_permalink();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $is_register ? 'Đăng ký' : 'Đăng nhập'; ?> - <?php bloginfo( 'name' ); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
<?php wp_head(); ?>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;background:#083838;-webkit-font-smoothing:antialiased}

/* ── Split layout ── */
.auth-split{display:flex;width:100%;min-height:100vh}

/* Left panel - branding */
.auth-brand{flex:0 0 45%;background:linear-gradient(160deg,#062E2E 0%,#0D4F4F 50%,#1A7A7A 100%);display:flex;flex-direction:column;justify-content:center;padding:60px;position:relative;overflow:hidden}
.auth-brand::before{content:'';position:absolute;width:400px;height:400px;border-radius:50%;background:rgba(232,168,56,.06);top:-100px;right:-100px}
.auth-brand::after{content:'';position:absolute;width:300px;height:300px;border-radius:50%;background:rgba(232,168,56,.04);bottom:-80px;left:-60px}
.auth-brand *{position:relative;z-index:1}
.auth-brand-logo{display:inline-flex;align-items:center;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:28px;color:#fff;text-decoration:none;margin-bottom:48px}
.auth-brand-logo svg{margin-right:8px}
.auth-brand h1{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:36px;color:#fff;line-height:1.3;margin-bottom:16px}
.auth-brand h1 span{color:#E8A838}
.auth-brand p{color:rgba(255,255,255,.55);font-size:15px;line-height:1.7;max-width:380px}

.auth-features{margin-top:48px;display:flex;flex-direction:column;gap:20px}
.auth-feat{display:flex;align-items:flex-start;gap:14px}
.auth-feat-icon{width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.auth-feat-icon svg{width:20px;height:20px}
.auth-feat-text h4{font-size:14px;font-weight:600;color:#fff;margin-bottom:2px}
.auth-feat-text p{font-size:12px;color:rgba(255,255,255,.45);line-height:1.5}

/* Right panel - form */
.auth-form-panel{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 24px;background:#fff}
.auth-form-wrap{width:100%;max-width:400px}

.auth-form-header{margin-bottom:32px}
.auth-form-header h2{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:26px;color:#083838;margin-bottom:6px}
.auth-form-header p{font-size:14px;color:#6B7280}
.auth-form-header p a{color:#0D4F4F;font-weight:600;text-decoration:none}
.auth-form-header p a:hover{text-decoration:underline}

/* Mobile logo (hidden on desktop) */
.auth-mobile-logo{display:none;text-align:center;margin-bottom:28px}
.auth-mobile-logo a{display:inline-flex;align-items:center;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:22px;color:#0D4F4F;text-decoration:none}
.auth-mobile-logo a svg{margin-right:6px}

/* Form */
.fg{margin-bottom:18px}
.fg label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#2C2C3A}
.fg-input-wrap{position:relative}
.fg-input-wrap svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9CA3AF}
.fg input[type="text"],
.fg input[type="email"],
.fg input[type="password"]{
    width:100%;padding:13px 16px 13px 44px;border:1.5px solid #E5E2DB;border-radius:10px;
    font-family:'Inter',sans-serif;font-size:14px;color:#2C2C3A;transition:all .2s;
    background:#FAFAF8;
}
.fg input:focus{outline:none;border-color:#0D4F4F;box-shadow:0 0 0 3px rgba(13,79,79,.08);background:#fff}
.fg input::placeholder{color:#B0B5BC}

.pw-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9CA3AF;padding:0}
.pw-toggle:hover{color:#6B7280}

.remember-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;font-size:13px}
.remember-left{display:flex;align-items:center;gap:8px;color:#6B7280}
.remember-left input[type="checkbox"]{width:16px;height:16px;accent-color:#0D4F4F;border-radius:4px}
.remember-left label{margin:0;font-weight:400;cursor:pointer}

.auth-btn{
    width:100%;padding:14px;background:linear-gradient(135deg,#0D4F4F,#1A7A7A);color:#fff;border:none;
    border-radius:10px;font-family:'Inter',sans-serif;font-size:15px;font-weight:600;
    cursor:pointer;transition:all .25s;display:flex;align-items:center;justify-content:center;gap:8px;
}
.auth-btn:hover{background:linear-gradient(135deg,#1A7A7A,#228B8B);transform:translateY(-1px);box-shadow:0 4px 16px rgba(13,79,79,.2)}
.auth-btn:active{transform:translateY(0)}

.auth-divider{display:flex;align-items:center;gap:12px;margin:24px 0;color:#D1CEC7;font-size:12px}
.auth-divider::before,.auth-divider::after{content:'';flex:1;height:1px;background:#E5E2DB}

.auth-error{display:flex;align-items:center;gap:10px;background:#FEF2F2;border:1px solid #FEE2E2;color:#991B1B;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;line-height:1.5}
.auth-error svg{flex-shrink:0}

.auth-footer{text-align:center;margin-top:24px;font-size:13px;color:#9CA3AF}
.auth-footer a{color:#0D4F4F;font-weight:600;text-decoration:none}
.auth-footer a:hover{text-decoration:underline}

/* ── Responsive ── */
@media(max-width:900px){
    .auth-brand{display:none}
    .auth-mobile-logo{display:block}
    .auth-form-panel{padding:32px 20px}
}
@media(max-width:480px){
    .auth-form-wrap{max-width:100%}
}
</style>
</head>
<body>

<div class="auth-split">
    <!-- Left: Branding -->
    <div class="auth-brand">
        <a href="<?php echo home_url(); ?>" class="auth-brand-logo">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E8A838" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            LinkNgon
        </a>
        <h1>Rút gọn link.<br><span>Kiếm tiền.</span></h1>
        <p>Nền tảng rút gọn link hàng đầu Việt Nam. Chia sẻ link và nhận tiền từ mỗi lượt click hợp lệ.</p>

        <div class="auth-features">
            <div class="auth-feat">
                <div class="auth-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#E8A838" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                <div class="auth-feat-text">
                    <h4>Thanh toán nhanh</h4>
                    <p>Rút tiền về ngân hàng hoặc MoMo, tối thiểu 50.000đ</p>
                </div>
            </div>
            <div class="auth-feat">
                <div class="auth-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#E8A838" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></div>
                <div class="auth-feat-text">
                    <h4>Thống kê real-time</h4>
                    <p>Theo dõi clicks, thu nhập, quốc gia theo thời gian thực</p>
                </div>
            </div>
            <div class="auth-feat">
                <div class="auth-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#E8A838" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                <div class="auth-feat-text">
                    <h4>Referral 20% trọn đời</h4>
                    <p>Giới thiệu bạn bè và nhận 20% thu nhập vĩnh viễn</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Form -->
    <div class="auth-form-panel">
        <div class="auth-form-wrap">
            <div class="auth-mobile-logo">
                <a href="<?php echo home_url(); ?>">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    LinkNgon
                </a>
            </div>

            <?php if ( $is_register ) : ?>

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
                <div class="fg">
                    <label for="reg-username">Tên đăng nhập</label>
                    <div class="fg-input-wrap">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" id="reg-username" name="username" required placeholder="vd: nguyenvana" autocomplete="username" value="<?php echo esc_attr( $_POST['username'] ?? '' ); ?>">
                    </div>
                </div>
                <div class="fg">
                    <label for="reg-email">Email</label>
                    <div class="fg-input-wrap">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <input type="email" id="reg-email" name="email" required placeholder="email@example.com" autocomplete="email" value="<?php echo esc_attr( $_POST['email'] ?? '' ); ?>">
                    </div>
                </div>
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
                <button type="submit" class="auth-btn">
                    Tạo tài khoản
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>

            <div class="auth-divider">hoặc</div>
            <div class="auth-footer">
                <a href="<?php echo home_url(); ?>">Quay về trang chủ</a>
            </div>

            <?php else : ?>

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

            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function togglePw(id,btn){
    var inp=document.getElementById(id);
    if(inp.type==='password'){
        inp.type='text';
        btn.innerHTML='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    }else{
        inp.type='password';
        btn.innerHTML='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    }
}
</script>
<?php wp_footer(); ?>
</body>
</html>
