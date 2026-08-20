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
<style>
/* ── Đồng bộ với page-login.php / page-register.php (thiết kế tối giản theo mẫu
   tham khảo): wordmark chữ mảnh phía trên card, card trắng phẳng, icon field bên
   PHẢI, "Hiện mật khẩu" là checkbox riêng thay vì nút icon trong ô. Ghi đè
   auth-styles.php — toàn bộ field/name/id/logic PHP giữ nguyên. ── */
body{background:#DADEE7}
body::before,body::after{display:none}
.auth-page{padding:60px 20px;display:flex;flex-direction:column;align-items:center}

.auth-wordmark{margin-bottom:30px;text-align:center}
.auth-wordmark a{display:inline-block;font-family:'Inter',sans-serif;font-weight:400;font-size:38px;letter-spacing:.01em;color:#3D4451;text-decoration:none;line-height:1}
.auth-wordmark a:hover{color:#1E293B}
.auth-wordmark a .tld{color:#94A3B8}

.auth-card{max-width:460px;width:100%;border-radius:8px;box-shadow:0 4px 18px rgba(15,23,42,.10);padding:34px 36px 30px}
.auth-logo{display:none} /* logo chuyển ra .auth-wordmark ngoài card */

.card-head{display:flex;align-items:center;justify-content:center;gap:9px;font-size:16px;font-weight:600;color:#1E293B;margin-bottom:14px}
.card-head svg{color:#334155;flex-shrink:0}
.card-sub{text-align:center;font-size:13px;color:#64748B;margin:0 0 20px;line-height:1.5}
.card-hr{height:1px;background:#E7EAF0;margin:0 0 22px}

.fg{margin-bottom:16px}
.fg label{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;padding:0;margin:-1px}
.fg-input-wrap>svg{left:auto;right:15px;color:#64748B}
.fg input[type="text"],.fg input[type="password"]{
    padding:13px 42px 13px 15px;border:1.3px solid #DDE2EA;border-radius:6px;background:#fff;font-size:14.5px;color:#1E293B;
}
.fg input:focus{border-color:#5B8FE0;box-shadow:0 0 0 3px rgba(91,143,224,.15)}
.fg input::placeholder{color:#94A3B8}

.chk-row{display:flex;align-items:center;gap:9px;font-size:13.5px;color:#334155;margin-bottom:16px}
.chk-row input[type="checkbox"]{width:16px;height:16px;accent-color:#3B7DDD;cursor:pointer;flex-shrink:0}
.chk-row svg{color:#334155;flex-shrink:0}
.chk-row label{margin:0;font-weight:400;cursor:pointer}

.auth-btn{background:#3B7DDD;border-radius:6px;font-size:14.5px;font-weight:600;text-transform:none;letter-spacing:normal;padding:13px;gap:9px;box-shadow:none;margin-top:4px}
.auth-btn:hover{background:#2F6BC4;transform:none;box-shadow:none}
.auth-divider{display:none}
.auth-footer{display:none} /* thay bằng .auth-links-below bên dưới card */

.auth-links-below{margin-top:20px;display:flex;flex-direction:column;gap:11px;align-items:flex-start;width:100%;max-width:460px}
.auth-links-below a{display:inline-flex;align-items:center;gap:8px;font-size:13.5px;color:#3B7DDD;text-decoration:none;font-weight:500}
.auth-links-below a:hover{text-decoration:underline}
.auth-links-below svg{flex-shrink:0}

@media(max-width:480px){
    .auth-page{padding:36px 16px}
    .auth-wordmark a{font-size:30px}
    .auth-card{padding:26px 22px 24px;border-radius:8px}
}
</style>
</head>
<body>

<div class="auth-page">
  <div class="auth-wordmark">
        <a href="<?php echo home_url(); ?>">SITETOP<span class="tld">.net</span></a>
  </div>

  <div class="auth-card">

            <?php if ( $step === 'reset' ) : ?>
                <div class="card-head">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Đặt mật khẩu mới
                </div>
                <p class="card-sub">Nhập mật khẩu mới cho tài khoản của bạn</p>
                <div class="card-hr"></div>

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
                            <input type="password" id="new-password" name="new_password" required minlength="6" placeholder="Mật khẩu mới (tối thiểu 6 ký tự)" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="fg">
                        <label for="confirm-password">Xác nhận mật khẩu</label>
                        <div class="fg-input-wrap">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <input type="password" id="confirm-password" name="confirm_password" required minlength="6" placeholder="Nhập lại mật khẩu mới" autocomplete="new-password">
                        </div>
                    </div>

                    <div class="chk-row">
                        <input type="checkbox" id="showpw-reset" onchange="togglePwChkMulti('new-password,confirm-password',this)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <label for="showpw-reset">Hiện mật khẩu</label>
                    </div>

                    <button type="submit" name="reset_password_submit" value="1" class="auth-btn">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        Đặt lại mật khẩu
                    </button>
                </form>

            <?php elseif ( $step === 'done' ) : ?>
                <div class="card-head">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Thành công!
                </div>
                <div class="card-hr"></div>
                <div class="auth-success" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <?php echo esc_html( $success ); ?>
                </div>
                <a href="<?php echo home_url('/dang-nhap'); ?>" class="auth-btn" style="text-decoration:none">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    Đăng nhập ngay
                </a>

            <?php else : ?>
                <div class="card-head">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L19 4l2 2-2 2 2 2-3 3-2-2-1.5 1.5"/></svg>
                    Quên mật khẩu
                </div>
                <p class="card-sub">Nhập email hoặc tên đăng nhập để nhận link đặt lại mật khẩu</p>
                <div class="card-hr"></div>

                <?php if ( $error ) : ?>
                    <div class="auth-error">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <?php echo esc_html( $error ); ?>
                    </div>
                <?php endif; ?>

                <?php if ( $success ) : ?>
                    <div class="auth-success" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:12px 16px;border-radius:8px;font-size:13px;display:flex;align-items:center;gap:8px">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <?php echo esc_html( $success ); ?>
                    </div>
                <?php else : ?>
                    <form method="post">
                        <?php wp_nonce_field( 'sitetop_forgot_password' ); ?>
                        <div class="fg">
                            <label for="user-login">Email hoặc tên đăng nhập</label>
                            <div class="fg-input-wrap">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <input type="text" id="user-login" name="user_login" required placeholder="Email hoặc tên đăng nhập" autocomplete="username" value="<?php echo esc_attr( $_POST['user_login'] ?? '' ); ?>">
                            </div>
                        </div>
                        <button type="submit" class="auth-btn">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            Gửi link đặt lại
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

  </div><!-- /.auth-card -->

  <div class="auth-links-below">
        <a href="<?php echo home_url('/dang-nhap'); ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            Quay lại đăng nhập
        </a>
  </div>
</div>

<?php include get_template_directory() . '/includes/auth-scripts.php'; ?>
<?php wp_footer(); ?>
</body>
</html>
