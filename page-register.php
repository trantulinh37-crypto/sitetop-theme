<?php
/**
 * Template Name: Đăng ký
 * SiteTop.net V2 - Register Page
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( is_user_logged_in() ) {
    wp_redirect( sitetop_get_dashboard_url() );
    exit;
}

$error = '';

/**
 * Normalize an email for duplicate detection (anti Gmail alias/dot evasion).
 * For gmail.com/googlemail.com: strip everything after '+' in local part and remove all dots.
 */
if ( ! function_exists( 'sitetop_normalize_email' ) ) {
    function sitetop_normalize_email( $email ) {
        $email = strtolower( trim( (string) $email ) );
        if ( strpos( $email, '@' ) === false ) return $email;
        list( $local, $domain ) = explode( '@', $email, 2 );
        if ( in_array( $domain, array( 'gmail.com', 'googlemail.com' ), true ) ) {
            $plus = strpos( $local, '+' );
            if ( $plus !== false ) $local = substr( $local, 0, $plus );
            $local = str_replace( '.', '', $local );
            $domain = 'gmail.com';
        }
        return $local . '@' . $domain;
    }
}

/**
 * Verify a Cloudflare Turnstile token server-side.
 * No-op (returns true) when Turnstile is not enabled / not fully configured — so registration
 * is unaffected unless an admin has set it up. Fails OPEN on network/transport error so a
 * Cloudflare outage can't block all signups; only a definitive "not success" blocks.
 */
if ( ! function_exists( 'sitetop_verify_turnstile' ) ) {
    function sitetop_verify_turnstile( $token, $ip = '' ) {
        $enabled = sitetop_get_option( 'turnstile_enabled', 0 );
        $secret  = sitetop_get_option( 'turnstile_secret_key', '' );
        $site    = sitetop_get_option( 'turnstile_site_key', '' );
        if ( ! $enabled || empty( $secret ) || empty( $site ) ) return true; // not configured → skip
        if ( empty( $token ) ) return false; // enabled but no token submitted
        $resp = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
            'timeout' => 8,
            'body'    => array( 'secret' => $secret, 'response' => $token, 'remoteip' => $ip ),
        ) );
        if ( is_wp_error( $resp ) ) return true; // network error → fail open (availability)
        if ( (int) wp_remote_retrieve_response_code( $resp ) !== 200 ) return true; // transport issue → fail open
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
        return ! empty( $body['success'] );
    }
}

$posted_type = sanitize_text_field( $_POST['account_type'] ?? ( $_GET['type'] ?? 'user' ) );
if ( ! in_array( $posted_type, array( 'user', 'customer' ), true ) ) $posted_type = 'user';
$ref_code = sanitize_user( $_POST['ref'] ?? ( $_GET['ref'] ?? '' ) );

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'sitetop_register' ) ) {
    $username     = sanitize_user( $_POST['username'] ?? '' );
    $email        = sanitize_email( $_POST['email'] ?? '' );
    $phone        = sanitize_text_field( $_POST['phone'] ?? '' );
    $password     = $_POST['password'] ?? '';
    $account_type = $posted_type;

    // Per-IP registration rate limit: max 5 / IP / hour
    $reg_ip       = function_exists( 'sitetop_get_real_ip' ) ? sitetop_get_real_ip() : ( $_SERVER['REMOTE_ADDR'] ?? '' );
    $reg_rate_key = 'sitetop_reg_rate_' . md5( $reg_ip );
    $reg_count    = (int) get_transient( $reg_rate_key );

    // Disposable email domains blocked at registration
    $disposable_domains = array(
        'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com',
        'yopmail.com', 'trashmail.com', 'getnada.com', 'temp-mail.org',
        'sharklasers.com', 'maildrop.cc', 'throwawaymail.com',
    );
    $email_domain     = ( strpos( $email, '@' ) !== false ) ? strtolower( substr( strrchr( $email, '@' ), 1 ) ) : '';
    $email_normalized = sitetop_normalize_email( $email );
    $phone_normalized = preg_replace( '/\D/', '', $phone );

    if ( $reg_count >= 5 ) {
        $error = 'Bạn đã đăng ký quá nhiều lần, vui lòng thử lại sau.';
    } elseif ( empty( $username ) || empty( $email ) || empty( $password ) ) {
        $error = 'Vui lòng điền đầy đủ thông tin';
    } elseif ( ! preg_match( '/^[a-zA-Z0-9]+$/', $username ) ) {
        $error = 'Tên đăng nhập chỉ được chứa chữ cái và số, không có ký tự đặc biệt';
    } elseif ( strlen( $username ) < 3 || strlen( $username ) > 30 ) {
        $error = 'Tên đăng nhập phải từ 3 đến 30 ký tự';
    } elseif ( empty( $phone ) ) {
        $error = 'Vui lòng nhập số điện thoại';
    } elseif ( username_exists( $username ) ) {
        $error = 'Tên đăng nhập đã tồn tại';
    } elseif ( email_exists( $email ) ) {
        $error = 'Email đã được sử dụng';
    } elseif ( $email_domain && in_array( $email_domain, $disposable_domains, true ) ) {
        $error = 'Email tạm thời không được chấp nhận, vui lòng dùng email thật';
    } elseif ( ! empty( $email_normalized ) && (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare(
            "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->usermeta} WHERE meta_key = 'sitetop_email_normalized' AND meta_value = %s",
            $email_normalized ) ) > 0 ) {
        $error = 'Email này đã được sử dụng';
    } elseif ( ! empty( $phone_normalized ) && (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare(
            "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->usermeta} WHERE meta_key = 'phone_normalized' AND meta_value = %s",
            $phone_normalized ) ) > 0 ) {
        $error = 'Số điện thoại đã được sử dụng';
    } elseif ( strlen( $password ) < 6 ) {
        $error = 'Mật khẩu tối thiểu 6 ký tự';
    } elseif ( ! sitetop_verify_turnstile( $_POST['cf-turnstile-response'] ?? '', $reg_ip ) ) {
        $error = 'Vui lòng xác nhận bạn không phải robot';
    } else {
        $user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            $error = $user_id->get_error_message();
        } else {
            update_user_meta( $user_id, 'phone', $phone );
            update_user_meta( $user_id, 'phone_normalized', $phone_normalized );
            update_user_meta( $user_id, 'sitetop_email_normalized', $email_normalized );

            // Count this successful registration against the per-IP hourly limit
            set_transient( $reg_rate_key, $reg_count + 1, 3600 );

            // Save referral info if ref param provided
            if ( ! empty( $ref_code ) && sitetop_get_option( 'referral_enabled', 0 ) ) {
                $referrer = get_user_by( 'login', $ref_code );
                if ( $referrer && $referrer->ID !== $user_id ) {
                    update_user_meta( $user_id, 'sitetop_referred_by', $referrer->ID );
                    update_user_meta( $user_id, 'sitetop_referred_at', sitetop_current_time() );
                }
            }

            // Set role based on account type
            $user = new WP_User( $user_id );
            $is_customer = ( $account_type === 'customer' );
            if ( $is_customer ) {
                $user->set_role( 'customer' );
                // Initialize customer balance
                global $wpdb;
                $p = $wpdb->prefix . 'sitetop_';
                $wpdb->insert( "{$p}customer_balance", array(
                    'user_id' => $user_id, 'balance' => 0, 'total_deposited' => 0, 'total_spent' => 0,
                ));
                // Khách hàng: KÍCH HOẠT THỦ CÔNG. Bỏ qua xác nhận email (email_verified=1 để qua được
                // cổng đăng nhập), thay bằng "chờ Admin kích hoạt" → khóa dashboard tới khi duyệt.
                update_user_meta( $user_id, 'sitetop_email_verified', '1' );
                update_user_meta( $user_id, 'sitetop_customer_pending', '1' );
            }

            // Publisher: gửi email xác nhận như cũ. Customer đã bỏ qua email (dùng manual activation).
            if ( ! $is_customer ) {
                sitetop_send_verification_email( $user_id );
                update_user_meta( $user_id, 'sitetop_verify_last_sent', time() );
            }

            // Redirect to login (customer thêm cờ pending=1 để hiện hướng dẫn liên hệ Admin).
            $redir = $is_customer ? '/dang-nhap?registered=1&pending=1' : '/dang-nhap?registered=1';
            wp_redirect( home_url( $redir ) );
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
<style>
/* ── Thiết kế tối giản theo mẫu tham khảo (linkx.me) — đồng bộ với page-login.php.
   Khác 2 chỗ so với mẫu theo yêu cầu: vẫn giữ Số điện thoại (bắt buộc) và mục chọn
   Loại tài khoản (thu gọn thành nút gạt 2 lựa chọn thay vì thẻ minh hoạ lớn để hợp
   với phong cách phẳng mới). Ghi đè auth-styles.php — toàn bộ field/name/id/logic
   PHP giữ nguyên. ── */
body{background:#DADEE7}
body::before,body::after{display:none}
.auth-page{padding:60px 20px;display:flex;flex-direction:column;align-items:center}

.auth-wordmark{margin-bottom:30px;text-align:center}
.auth-wordmark a{display:inline-block;font-family:'Inter',sans-serif;font-weight:400;font-size:38px;letter-spacing:.01em;color:#3D4451;text-decoration:none;line-height:1}
.auth-wordmark a:hover{color:#1E293B}
.auth-wordmark a .tld{color:#94A3B8}

.auth-card,.auth-card.wide{max-width:480px;width:100%;border-radius:8px;box-shadow:0 4px 18px rgba(15,23,42,.10);padding:34px 36px 30px}
.auth-logo{display:none} /* logo chuyển ra .auth-wordmark ngoài card, giống mẫu */

.card-head{display:flex;align-items:center;justify-content:center;gap:9px;font-size:16px;font-weight:600;color:#1E293B;margin-bottom:20px}
.card-head svg{color:#334155;flex-shrink:0}
.card-hr{height:1px;background:#E7EAF0;margin:0 0 22px}

/* Mục chọn loại tài khoản — nút gạt 2 lựa chọn, gọn hơn thẻ minh hoạ cũ để hợp
   phong cách phẳng, nhưng vẫn là radio name="account_type" y hệt trước. */
.atype-label{display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:9px}
.atype-toggle{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px}
.atype-opt{position:relative;display:flex;align-items:center;justify-content:center;gap:7px;border:1.3px solid #DDE2EA;border-radius:6px;padding:11px 8px;font-size:13.5px;font-weight:500;color:#475569;cursor:pointer;transition:all .15s;text-align:center}
.atype-opt input{position:absolute;opacity:0;pointer-events:none}
.atype-opt svg{flex-shrink:0;color:#64748B}
.atype-opt.active{border-color:#3B7DDD;background:#EFF5FE;color:#1E3A8A;font-weight:600}
.atype-opt.active svg{color:#3B7DDD}

.fg-row{grid-template-columns:1fr;gap:0} /* 1 cột, xếp chồng như mẫu thay vì lưới 2 cột cũ */
.fg{margin-bottom:16px}
.fg label{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;padding:0;margin:-1px}
.fg-input-wrap>svg{left:auto;right:15px;color:#64748B}
.fg input[type="text"],.fg input[type="email"],.fg input[type="password"],.fg input[type="tel"]{
    padding:13px 42px 13px 15px;border:1.3px solid #DDE2EA;border-radius:6px;background:#fff;font-size:14.5px;color:#1E293B;
}
.fg input:focus{border-color:#5B8FE0;box-shadow:0 0 0 3px rgba(91,143,224,.15)}
.fg input::placeholder{color:#94A3B8}

.chk-row{display:flex;align-items:flex-start;gap:9px;font-size:13.5px;color:#334155;margin-bottom:16px}
.chk-row input[type="checkbox"]{width:16px;height:16px;accent-color:#3B7DDD;cursor:pointer;flex-shrink:0;margin-top:1px}
.chk-row svg{color:#334155;flex-shrink:0;margin-top:1px}
.chk-row label{margin:0;font-weight:400;cursor:pointer;line-height:1.5}
.chk-row a{color:#3B7DDD;font-weight:600;text-decoration:none}
.chk-row a:hover{text-decoration:underline}

.auth-btn{background:#3B7DDD;border-radius:6px;font-size:14.5px;font-weight:600;text-transform:none;letter-spacing:normal;padding:13px;gap:9px;box-shadow:none;margin-top:4px}
.auth-btn:hover{background:#2F6BC4;transform:none;box-shadow:none}
.auth-divider{display:none} /* mẫu không có lựa chọn đăng ký phụ, bỏ vạch "hoặc" */
.auth-footer{display:none} /* thay bằng .auth-links-below bên dưới card, giống mẫu */

.auth-links-below{margin-top:20px;display:flex;flex-direction:column;gap:11px;align-items:flex-start;width:100%;max-width:480px}
.auth-links-below a{display:inline-flex;align-items:center;gap:8px;font-size:13.5px;color:#3B7DDD;text-decoration:none;font-weight:500}
.auth-links-below a:hover{text-decoration:underline}
.auth-links-below svg{flex-shrink:0}

@media(max-width:480px){
    .auth-page{padding:36px 16px}
    .auth-wordmark a{font-size:30px}
    .auth-card,.auth-card.wide{padding:26px 22px 24px;border-radius:8px}
    .atype-toggle{gap:8px}
    .atype-opt{font-size:12.5px;padding:10px 6px}
}
</style>
</head>
<body>

<div class="auth-page">
  <div class="auth-wordmark">
        <a href="<?php echo home_url(); ?>">SITETOP<span class="tld">.net</span></a>
  </div>

    <div class="auth-card wide">

        <div class="card-head">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
            Đăng ký thành viên mới
        </div>
        <div class="card-hr"></div>

            <?php if ( $error ) : ?>
                <div class="auth-error">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php echo esc_html( $error ); ?>
                </div>
            <?php endif; ?>

            <form method="post" id="regForm">
                <?php wp_nonce_field( 'sitetop_register' ); ?>
                <?php if ( ! empty( $ref_code ) ) : ?>
                <input type="hidden" name="ref" value="<?php echo esc_attr( $ref_code ); ?>">
                <?php endif; ?>

                <!-- Loại tài khoản: nút gạt 2 lựa chọn, vẫn là radio name="account_type" -->
                <span class="atype-label">Loại tài khoản</span>
                <div class="atype-toggle">
                    <label class="atype-opt<?php echo $posted_type === 'user' ? ' active' : ''; ?>" id="cardUser" onclick="pickType('user')">
                        <input type="radio" name="account_type" value="user" <?php checked( $posted_type, 'user' ); ?>>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        Người kiếm tiền
                    </label>
                    <label class="atype-opt<?php echo $posted_type === 'customer' ? ' active' : ''; ?>" id="cardCustomer" onclick="pickType('customer')">
                        <input type="radio" name="account_type" value="customer" <?php checked( $posted_type, 'customer' ); ?>>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                        Nhà quảng cáo
                    </label>
                </div>

                <div class="fg">
                    <label for="reg-email">Email</label>
                    <div class="fg-input-wrap">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <input type="email" id="reg-email" name="email" required placeholder="Email" autocomplete="email" value="<?php echo esc_attr( $_POST['email'] ?? '' ); ?>">
                    </div>
                </div>

                <div class="fg">
                    <label for="reg-username">Tên đăng nhập</label>
                    <div class="fg-input-wrap">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" id="reg-username" name="username" required placeholder="Tên đăng nhập" autocomplete="username" pattern="[a-zA-Z0-9]+" minlength="3" maxlength="30" title="Chỉ chữ cái và số, 3-30 ký tự" value="<?php echo esc_attr( $_POST['username'] ?? '' ); ?>">
                    </div>
                </div>

                <div class="fg">
                    <label for="reg-phone">Số điện thoại</label>
                    <div class="fg-input-wrap">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <input type="tel" id="reg-phone" name="phone" required placeholder="Số điện thoại" autocomplete="tel" value="<?php echo esc_attr( $_POST['phone'] ?? '' ); ?>">
                    </div>
                </div>

                <div class="fg">
                    <label for="reg-password">Mật khẩu</label>
                    <div class="fg-input-wrap">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" id="reg-password" name="password" required minlength="6" placeholder="Mật khẩu (tối thiểu 6 ký tự)" autocomplete="new-password">
                    </div>
                </div>

                <div class="chk-row">
                    <input type="checkbox" id="showpw-reg" onchange="togglePwChk('reg-password',this)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <label for="showpw-reg">Hiện mật khẩu</label>
                </div>

                <div class="chk-row">
                    <input type="checkbox" id="reg-terms" name="terms" required checked>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    <label for="reg-terms">Bằng cách đăng ký, bạn đồng ý <a href="<?php echo home_url('/dieu-khoan'); ?>" target="_blank">Điều khoản Sử dụng</a> và <a href="<?php echo home_url('/dieu-khoan#bao-mat'); ?>" target="_blank">Chính sách bảo mật</a>.</label>
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

                <button type="submit" class="auth-btn" id="regBtn">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    <span id="regBtnText"><?php echo $posted_type === 'customer' ? 'Đăng ký Nhà quảng cáo' : 'Đăng ký'; ?></span>
                </button>
            </form>

    </div><!-- /.auth-card -->

    <div class="auth-links-below">
        <a href="<?php echo home_url('/dang-nhap'); ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            Tôi đã là thành viên
        </a>
    </div>
</div>

<?php include get_template_directory() . '/includes/auth-scripts.php'; ?>
<script>
// Chuyển đổi Người kiếm tiền / Nhà quảng cáo — chỉ đổi trạng thái nút gạt + chữ nút
// Đăng ký, KHÔNG còn panel thương hiệu minh hoạ bên cạnh (đã bỏ theo thiết kế mới).
function pickType(type){
    var cu=document.getElementById('cardUser'),cc=document.getElementById('cardCustomer');
    var btn=document.getElementById('regBtnText');
    cu.classList.remove('active');cc.classList.remove('active');
    if(type==='customer'){
        cc.classList.add('active');cc.querySelector('input').checked=true;
        btn.textContent='Đăng ký Nhà quảng cáo';
    } else {
        cu.classList.add('active');cu.querySelector('input').checked=true;
        btn.textContent='Đăng ký';
    }
}
<?php if ( $posted_type === 'customer' ): ?>
pickType('customer');
<?php endif; ?>
</script>
<?php wp_footer(); ?>
</body>
</html>
