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
<style>
/* ── Thiết kế theo mẫu: logo + tiêu đề nằm NGOÀI card, card trắng chỉ chứa form.
   Ghi đè auth-styles.php — toàn bộ field/name/id/logic PHP giữ nguyên. ── */
body{background:#F1F6FF}
.auth-page{padding:34px 20px;position:relative;overflow:hidden}
/* Hoạ tiết nền: chấm bi góc trên phải + khối tròn mờ hai bên */
.auth-page::before{content:'';position:absolute;top:34px;right:6%;width:160px;height:150px;background-image:radial-gradient(circle,#C8DAF4 2px,transparent 2px);background-size:19px 19px;opacity:.75;pointer-events:none}
.auth-page::after{content:'';position:absolute;top:-90px;left:-70px;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle at 35% 35%,#DCE9FF,#EDF3FF);opacity:.8;pointer-events:none}
.login-wrap{position:relative;z-index:1;width:100%;max-width:440px;margin:0 auto}

/* Logo + tiêu đề ngoài card */
.login-wrap .auth-logo{margin-bottom:26px}
.login-head{text-align:center;margin-bottom:22px}
.login-title{display:flex;align-items:center;justify-content:center;gap:14px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:clamp(26px,7vw,36px);letter-spacing:.01em;text-transform:uppercase;color:#0F172A;margin:0 0 10px;line-height:1.15}
.login-title svg{flex-shrink:0;color:#2F86FF}
.login-head p{font-size:14px;color:#64748B;margin:0}
.login-head p a{color:#2563EB;font-weight:600;text-decoration:none}
.login-head p a:hover{text-decoration:underline}

/* Card chỉ chứa form */
.auth-card{max-width:100%;padding:32px 30px 26px;border-radius:22px;box-shadow:0 18px 50px rgba(30,64,150,.12)}
.fg{margin-bottom:18px}
.fg label{font-size:12.5px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;color:#0F172A;margin-bottom:9px}
.fg input[type="text"],.fg input[type="password"]{padding:15px 16px 15px 46px;border:1.5px solid #DFE7F3;border-radius:13px;background:#fff;font-size:14.5px}
.fg input:focus{border-color:#2563EB;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.fg-input-wrap>svg{left:16px;color:#2563EB}
.pw-toggle{right:14px;color:#2563EB}
.remember-row{margin-bottom:20px}
.remember-left input[type="checkbox"]{width:18px;height:18px}
.forgot-link{font-weight:600}

/* Nút đăng nhập: icon mũi tên trong vòng tròn + chữ IN HOA */
.auth-btn{padding:15px;border-radius:14px;font-size:15.5px;font-weight:800;letter-spacing:.03em;text-transform:uppercase;background:linear-gradient(90deg,#1D4ED8,#2F86FF);gap:12px;box-shadow:0 10px 24px rgba(37,99,235,.28)}
.auth-btn:hover{background:linear-gradient(90deg,#1A44BF,#2578EE)}
.auth-btn .btn-ring{width:34px;height:34px;border-radius:50%;border:1.5px solid rgba(255,255,255,.55);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.auth-btn .btn-dots{display:flex;gap:4px;flex-shrink:0}
.auth-btn .btn-dots i{width:4px;height:4px;border-radius:50%;background:rgba(255,255,255,.6)}

.auth-divider{margin:22px 0 4px}

/* Cụm dưới card */
.login-below{text-align:center;margin-top:22px;display:flex;flex-direction:column;gap:14px;align-items:center}
.login-below .safe{display:inline-flex;align-items:center;gap:8px;font-size:13px;color:#64748B}
.login-below .safe svg{color:#2563EB}
.login-below a{display:inline-flex;align-items:center;gap:8px;font-size:14px;color:#2563EB;font-weight:600;text-decoration:none}
.login-below a:hover{text-decoration:underline}

@media(max-width:480px){
    .auth-page{padding:22px 14px}
    .auth-page::before{display:none}
    .auth-card{padding:26px 20px 22px;border-radius:18px}
    .login-title{gap:10px}
}
</style>
</head>
<body>

<div class="auth-page">
  <div class="login-wrap">
        <div class="auth-logo">
            <?php $ln_icon = get_option('sitetop_widget_icon',''); ?>
            <a href="<?php echo home_url(); ?>">
                <img src="<?php echo esc_url( $ln_icon ?: sitetop_logo_url('sitetop-logo.png') ); ?>" width="28" height="28" alt="" style="border-radius:50%">
                <span><span class="lgd">SITE</span><span class="lgb">TOP</span></span>
            </a>
        </div>

        <div class="login-head">
            <?php
            // Tia trang trí 2 bên tiêu đề (theo mẫu)
            $spark_l = '<svg width="26" height="34" viewBox="0 0 26 34" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M22 5 12 10"/><path d="M20 17H8"/><path d="M22 29l-10-5"/></svg>';
            $spark_r = '<svg width="26" height="34" viewBox="0 0 26 34" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M4 5l10 5"/><path d="M6 17h12"/><path d="M4 29l10-5"/></svg>';
            ?>
            <h2 class="login-title"><?php echo $spark_l; ?>Đăng nhập<?php echo $spark_r; ?></h2>
            <p>Chưa có tài khoản? <a href="<?php echo home_url('/dang-ky'); ?>">Đăng ký miễn phí</a></p>
        </div>

    <div class="auth-card">

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
                <?php wp_nonce_field( 'sitetop_login' ); ?>
                <div class="fg">
                    <label for="login-username">Tên đăng nhập hoặc Email</label>
                    <div class="fg-input-wrap">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" id="login-username" name="username" required autocomplete="username" placeholder="Nhập tên đăng nhập hoặc email">
                    </div>
                </div>
                <div class="fg">
                    <label for="login-password">Mật khẩu</label>
                    <div class="fg-input-wrap">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="login-password" name="password" required autocomplete="current-password" placeholder="Nhập mật khẩu">
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
                    <span class="btn-ring"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                    Đăng nhập
                    <span class="btn-dots"><i></i><i></i><i></i></span>
                </button>
            </form>

        <div class="auth-divider">hoặc</div>
    </div><!-- /.auth-card -->

    <div class="login-below">
        <span class="safe">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Bảo mật &amp; quyền riêng tư
        </span>
        <a href="<?php echo home_url(); ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Quay về trang chủ
        </a>
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
