<?php
/**
 * Template Name: Đăng nhập
 * SiteTop.net V2 - Login Page
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( is_user_logged_in() ) {
    wp_redirect( sitetop_get_dashboard_url() );
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
    $result = sitetop_verify_email_token( $uid, $token );
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
        if ( function_exists( 'sitetop_pending_notice_html' ) ) {
            $pending_notice = sitetop_pending_notice_html( false );
        }
    } else {
        $success = 'Đăng ký thành công! Vui lòng kiểm tra email để xác nhận tài khoản.';
    }
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'sitetop_login' ) ) {
    // H1: brute-force throttle — per-IP, 10 attempts / 5 min. Per-IP (not per-username)
    // so an attacker can't lock out a victim by spamming their username.
    $login_rate = function_exists( 'sitetop_rate_limit_check' ) ? sitetop_rate_limit_check( 'login' ) : array( 'allowed' => true );
    $login_ip   = function_exists( 'sitetop_get_real_ip' ) ? sitetop_get_real_ip() : ( $_SERVER['REMOTE_ADDR'] ?? '' );
    if ( empty( $login_rate['allowed'] ) ) {
        $error = 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau ít phút.';
    } elseif ( ! sitetop_verify_turnstile( $_POST['cf-turnstile-response'] ?? '', $login_ip ) ) {
        // sitetop_verify_turnstile() (định nghĩa trong functions.php) tự trả true khi
        // Turnstile chưa bật/chưa cấu hình đủ site+secret key → không ảnh hưởng đăng
        // nhập nếu admin chưa bật. Fail-open khi Cloudflare lỗi mạng/timeout, chỉ chặn
        // khi Cloudflare xác nhận rõ ràng token sai — giống hệt cổng đã dùng ở đăng ký.
        $error = 'Vui lòng xác nhận bạn không phải robot';
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
        if ( $code === 'sitetop_banned' || $code === 'sitetop_customer_banned' ) {
            $error = 'Tài khoản đã bị cấm. Vui lòng liên hệ quản trị viên.';
        } else {
            $error = 'Sai tên đăng nhập hoặc mật khẩu';
        }
    } else {
        // Check email verification
        if ( ! sitetop_is_email_verified( $user->ID ) ) {
            wp_logout();
            $error = 'Email chưa được xác nhận. Vui lòng kiểm tra hộp thư của bạn.';
            $need_verify = true;
            $verify_username = $login_username;
        } else {
            // M2: validate redirect target to same host (else fall back to dashboard) — no open redirect.
            $redirect = wp_validate_redirect( $_GET['redirect_to'] ?? '', sitetop_get_dashboard_url( $user ) );
            wp_safe_redirect( $redirect );
            exit;
        }
    }
    } // end rate-limit / turnstile gate else
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
<style>
/* ── Thiết kế tối giản theo mẫu tham khảo (linkx.me): wordmark chữ mảnh phía trên
   card, card trắng phẳng, icon field nằm bên PHẢI, không nhãn nổi trên ô nhập
   (placeholder làm nhãn), không nền hoạ tiết/gradient, "Hiện mật khẩu" tách thành
   checkbox riêng thay vì nút icon trong ô. Ghi đè auth-styles.php — toàn bộ
   field/name/id/logic PHP giữ nguyên. ── */
body{background:#DADEE7}
body::before,body::after{display:none}
.auth-page{padding:60px 20px;display:flex;flex-direction:column;align-items:center}

.auth-wordmark{margin-bottom:30px;text-align:center}
.auth-wordmark a{display:inline-block;font-family:'Inter',sans-serif;font-weight:400;font-size:38px;letter-spacing:.01em;color:#3D4451;text-decoration:none;line-height:1}
.auth-wordmark a:hover{color:#1E293B}
.auth-wordmark a .tld{color:#94A3B8}

.auth-card{max-width:460px;width:100%;border-radius:8px;box-shadow:0 4px 18px rgba(15,23,42,.10);padding:34px 36px 30px}
.auth-logo{display:none} /* logo chuyển ra .auth-wordmark ngoài card, giống mẫu */

.card-head{display:flex;align-items:center;justify-content:center;gap:9px;font-size:16px;font-weight:600;color:#1E293B;margin-bottom:20px}
.card-head svg{color:#334155;flex-shrink:0}
.card-hr{height:1px;background:#E7EAF0;margin:0 0 22px}

.fg{margin-bottom:16px}
/* Nhãn vẫn tồn tại cho trình đọc màn hình, chỉ ẩn hiển thị — placeholder đã làm
   nhãn thị giác đúng như mẫu, không xoá hẳn label để không mất khả năng tiếp cận. */
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
.auth-divider{display:none} /* mẫu không có lựa chọn đăng nhập phụ, bỏ vạch "hoặc" */

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

        <div class="card-head">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            Đăng nhập để bắt đầu phiên
        </div>
        <div class="card-hr"></div>

            <?php if ( $success ) : ?>
                <div class="auth-success" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:8px">
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
                <?php wp_nonce_field( 'sitetop_login' ); ?>
                <div class="fg">
                    <label for="login-username">Tên đăng nhập hoặc Email</label>
                    <div class="fg-input-wrap">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" id="login-username" name="username" required autocomplete="username" placeholder="Tên đăng nhập hoặc địa chỉ email">
                    </div>
                </div>
                <div class="fg">
                    <label for="login-password">Mật khẩu</label>
                    <div class="fg-input-wrap">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="login-password" name="password" required autocomplete="current-password" placeholder="Mật khẩu">
                    </div>
                </div>

                <div class="chk-row">
                    <input type="checkbox" id="showpw-login" onchange="togglePwChk('login-password',this)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <label for="showpw-login">Hiện mật khẩu</label>
                </div>
                <div class="chk-row">
                    <input type="checkbox" name="remember" id="remember" checked>
                    <label for="remember">Ghi nhớ tài khoản</label>
                </div>

<?php
                $ts_enabled = sitetop_get_option( 'turnstile_enabled', 0 );
                $ts_site    = sitetop_get_option( 'turnstile_site_key', '' );
                if ( $ts_enabled && ! empty( $ts_site ) ) : ?>
                <div style="margin-bottom:18px">
                    <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $ts_site ); ?>"></div>
                </div>
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                <?php endif; ?>

                <button type="submit" class="auth-btn">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    Đăng nhập
                </button>
            </form>

    </div><!-- /.auth-card -->

    <div class="auth-links-below">
        <a href="<?php echo home_url('/dang-ky'); ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
            Đăng ký thành viên mới
        </a>
        <a href="<?php echo home_url('/quen-mat-khau'); ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L19 4l2 2-2 2 2 2-3 3-2-2-1.5 1.5"/></svg>
            Quên mật khẩu
        </a>
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
    fd.append('action','sitetop_resend_verification');
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
