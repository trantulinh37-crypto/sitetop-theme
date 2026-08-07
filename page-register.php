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
/* ── Thiết kế theo mẫu: card đơn rộng, badge số bước, thẻ loại tài khoản có
   hình minh hoạ, label + icon nằm TRÊN ô nhập. Ghi đè auth-styles.php —
   toàn bộ field/name/id/logic PHP giữ nguyên. ── */
body{background:#F3F7FF}
/* Hoạ tiết nền: chấm bi 2 góc + khối tròn mờ (theo mẫu). Đặt trên ::before/::after
   của .auth-page vì body::before/after đã dùng cho 2 khối blur ở auth-styles.php */
.auth-page{padding:32px 20px;position:relative;overflow:hidden}
.auth-page::before,.auth-page::after{content:'';position:absolute;width:150px;height:190px;background-image:radial-gradient(circle,#C3D8F5 1.7px,transparent 1.7px);background-size:16px 16px;opacity:.6;pointer-events:none;z-index:0}
.auth-page::before{top:38px;right:5%}
.auth-page::after{bottom:38px;left:5%}
.auth-card{z-index:1}
/* Bằng đúng kích thước card trang đăng nhập (440px) */
.auth-card.wide{max-width:440px;padding:34px 30px 28px;border-radius:20px;box-shadow:0 18px 50px rgba(30,64,150,.12)}
.auth-logo{margin-bottom:20px}
.auth-form-header{margin-bottom:26px}
/* Tiêu đề IN HOA 2 tông + đường phân cách hình thoi (theo mẫu).
   padding-bottom: chừa chỗ cho chân chữ có dấu khỏi bị background-clip cắt. */
.auth-form-header h2{font-size:clamp(24px,6.4vw,34px);letter-spacing:.01em;text-transform:uppercase;color:#0F172A;line-height:1.15}
.auth-form-header h2 .hl{
    padding-bottom:5px;
    background:linear-gradient(95deg,#1D4ED8 0%,#2F86FF 55%,#00B2FF 100%);
    -webkit-background-clip:text;background-clip:text;color:transparent;
}
.reg-divider{display:flex;align-items:center;justify-content:center;gap:10px;margin:12px 0 12px}
.reg-divider i{display:block;width:64px;height:1.5px;background:linear-gradient(90deg,transparent,#BFD4F2)}
.reg-divider i:last-child{background:linear-gradient(90deg,#BFD4F2,transparent)}
.reg-divider b{display:block;width:9px;height:9px;background:#2F86FF;transform:rotate(45deg);border-radius:1.5px}
/* Phụ đề gọn đúng 1 dòng: ở 14px chữ rộng 384px trong khi khung chỉ 380px nên bị
   ngắt dòng — hạ cỡ chữ và cho co theo bề rộng màn hình (chữ rộng ~27.4x cỡ chữ). */
.auth-form-header p{white-space:nowrap;font-size:clamp(10.5px,2.85vw,13px)}

/* Thanh bước dạng pill (theo mẫu) */
.reg-step{display:flex;align-items:center;gap:11px;margin:0 0 14px;background:#fff;border:1px solid #E5EAF3;border-radius:999px;padding:8px 14px 8px 8px;box-shadow:0 2px 10px rgba(30,64,150,.05)}
.reg-step b{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#2563EB,#1D4ED8);color:#fff;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.reg-step span{flex:1;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:13.5px;letter-spacing:.02em;text-transform:uppercase;color:#0F172A}
.reg-step svg{color:#93A6C4;flex-shrink:0}
.reg-step.mt{margin-top:20px}

/* Thẻ chọn loại tài khoản — nút radio tròn bên phải (theo mẫu) */
.atype-row{display:grid;grid-template-columns:1fr;gap:12px;margin-bottom:6px}
.atype-card{position:relative;border:1.5px solid #E5EAF3;border-radius:16px;padding:16px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:14px;background:#fff}
.atype-card:hover{border-color:#A9CBFB;background:#FAFCFF}
.atype-card.active{border-color:#2563EB;background:#F7FAFF;box-shadow:0 0 0 3px rgba(37,99,235,.1)}
.atype-card input{position:absolute;opacity:0;pointer-events:none}
.atype-art{width:70px;height:70px;flex-shrink:0}
.atype-art svg{width:100%;height:100%;display:block}
.atype-info{flex:1;min-width:0}
.atype-name{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:14px;letter-spacing:.02em;text-transform:uppercase;color:#0F172A;margin-bottom:5px}
.atype-desc{font-size:12.5px;color:#64748b;line-height:1.55}
/* Nút radio: vòng tròn rỗng khi chưa chọn, tròn xanh có tick khi đã chọn */
.atype-check{width:28px;height:28px;border-radius:50%;border:2px solid #DCE3EF;background:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s}
.atype-check svg{opacity:0;transition:opacity .15s}
.atype-card.active .atype-check{background:#2563EB;border-color:#2563EB}
.atype-card.active .atype-check svg{opacity:1}

/* Field: label + icon nằm trên, ô nhập trơn — 1 cột cho vừa khổ hẹp */
.fg-row{grid-template-columns:1fr;gap:0}
.fg{margin-bottom:15px}
.fg label{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;color:#0F172A;margin-bottom:9px}
.fg label svg{color:#334155;flex-shrink:0}
.fg-input-wrap>svg{display:none}
.fg input[type="text"],.fg input[type="email"],.fg input[type="password"],.fg input[type="tel"]{
    padding:14px 16px;border:1.5px solid #E5EAF3;border-radius:12px;background:#fff;font-size:14.5px;
}
.fg input:focus{border-color:#2563EB;box-shadow:0 0 0 3px rgba(37,99,235,.12);background:#fff}
.fg-input-wrap input[type="password"]{padding-right:46px}
.pw-toggle{right:14px}

/* Điều khoản + nút gửi */
.reg-terms{display:flex;align-items:center;gap:9px;margin:2px 0 20px;font-size:13.5px;color:#475569}
.reg-terms input{width:18px;height:18px;accent-color:#2563EB;cursor:pointer;flex-shrink:0}
.reg-terms a{color:#2563EB;font-weight:600;text-decoration:none}
.reg-terms a:hover{text-decoration:underline}
.auth-btn{padding:16px;border-radius:14px;font-size:16px;font-weight:700;background:linear-gradient(90deg,#2563EB,#3B82F6);gap:10px}
.auth-btn:hover{background:linear-gradient(90deg,#1D4ED8,#2563EB)}

@media(max-width:350px){
    /* Màn quá hẹp: ép 1 dòng thì chữ nhỏ khó đọc -> cho xuống dòng lại */
    .auth-form-header p{white-space:normal;font-size:12px}
}
@media(max-width:480px){
    .auth-page{padding:16px 12px}
    .auth-card.wide{padding:26px 18px 22px;border-radius:16px}
    .atype-art{width:56px;height:56px}
}
</style>
</head>
<body>

<div class="auth-page">
    <div class="auth-card wide">
        <div class="auth-logo">
            <?php $ln_icon = get_option('sitetop_widget_icon',''); ?>
            <a href="<?php echo home_url(); ?>">
                <?php if($ln_icon): ?><img src="<?php echo esc_url($ln_icon); ?>" width="28" height="28" alt=""><?php else: ?><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><?php endif; ?>
                <span><span class="lgd">SITE</span><span class="lgb">TOP</span></span>
            </a>
        </div>

        <div class="auth-form-header">
            <h2>Tạo <span class="hl">tài khoản</span></h2>
            <div class="reg-divider"><i></i><b></b><i></i></div>
            <p id="regSubtitle"><?php echo $posted_type === 'customer' ? 'Tăng traffic website với người dùng thật 100%' : 'Tạo tài khoản miễn phí và bắt đầu kiếm tiền ngay hôm nay'; ?></p>
        </div>

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

                <!-- Bước 1: loại tài khoản -->
                <div class="reg-step"><b>1</b><span>Chọn loại tài khoản</span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="8 9 12 5 16 9"/><polyline points="16 15 12 19 8 15"/></svg></div>
                <div class="atype-row">
                    <label class="atype-card<?php echo $posted_type === 'user' ? ' active' : ''; ?>" id="cardUser" onclick="pickType('user')">
                        <input type="radio" name="account_type" value="user" <?php checked( $posted_type, 'user' ); ?>>
                        <div class="atype-art">
                            <svg viewBox="0 0 96 96" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <defs>
                                    <linearGradient id="artGold" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#FDE68A"/><stop offset="55%" stop-color="#FBBF24"/><stop offset="100%" stop-color="#D97706"/></linearGradient>
                                    <linearGradient id="artBase" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#93C5FD"/><stop offset="100%" stop-color="#2563EB"/></linearGradient>
                                </defs>
                                <ellipse cx="48" cy="83" rx="27" ry="7" fill="#2563EB" opacity=".16"/>
                                <path d="M21 70h54v6a5 5 0 0 1-5 5H26a5 5 0 0 1-5-5z" fill="url(#artBase)"/>
                                <ellipse cx="48" cy="70" rx="27" ry="8" fill="#BFDBFE"/>
                                <ellipse cx="48" cy="68" rx="27" ry="8" fill="#DBEAFE"/>
                                <circle cx="48" cy="42" r="24" fill="#B45309" opacity=".25"/>
                                <circle cx="47" cy="40" r="24" fill="url(#artGold)"/>
                                <circle cx="47" cy="40" r="18" fill="none" stroke="#FDE68A" stroke-width="2.5" opacity=".85"/>
                                <text x="47" y="50" text-anchor="middle" font-family="Georgia,serif" font-size="26" font-weight="bold" fill="#B45309">$</text>
                                <path d="M22 24l1.6 4.4L28 30l-4.4 1.6L22 36l-1.6-4.4L16 30l4.4-1.6z" fill="#FBBF24"/>
                                <path d="M76 16l1.2 3.3L80.5 21l-3.3 1.2L76 25.5l-1.2-3.3L71.5 21l3.3-1.2z" fill="#FCD34D"/>
                            </svg>
                        </div>
                        <div class="atype-info">
                            <div class="atype-name">Người kiếm tiền</div>
                            <div class="atype-desc">Chia sẻ link, nhận thưởng mỗi lượt view</div>
                        </div>
                        <span class="atype-check" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                    </label>
                    <label class="atype-card<?php echo $posted_type === 'customer' ? ' active' : ''; ?>" id="cardCustomer" onclick="pickType('customer')">
                        <input type="radio" name="account_type" value="customer" <?php checked( $posted_type, 'customer' ); ?>>
                        <div class="atype-art">
                            <svg viewBox="0 0 96 96" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <defs>
                                    <linearGradient id="artBlue" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#60A5FA"/><stop offset="100%" stop-color="#1D4ED8"/></linearGradient>
                                    <linearGradient id="artBase2" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#E5EDFA"/><stop offset="100%" stop-color="#C7D9F5"/></linearGradient>
                                </defs>
                                <ellipse cx="48" cy="83" rx="27" ry="7" fill="#2563EB" opacity=".14"/>
                                <path d="M21 70h54v6a5 5 0 0 1-5 5H26a5 5 0 0 1-5-5z" fill="url(#artBase2)"/>
                                <ellipse cx="48" cy="70" rx="27" ry="8" fill="#F1F6FE"/>
                                <rect x="52" y="48" width="8" height="18" rx="2.5" fill="#93C5FD"/>
                                <rect x="63" y="38" width="8" height="28" rx="2.5" fill="#3B82F6"/>
                                <path d="M52 34l9-9 5 5 9-9" fill="none" stroke="#1D4ED8" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M69 20h8v8" fill="none" stroke="#1D4ED8" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <rect x="36" y="52" width="8" height="20" rx="4" transform="rotate(-45 40 62)" fill="#1D4ED8"/>
                                <circle cx="33" cy="42" r="16" fill="#fff"/>
                                <circle cx="33" cy="42" r="16" fill="none" stroke="url(#artBlue)" stroke-width="5"/>
                                <circle cx="33" cy="42" r="9" fill="#DBEAFE" opacity=".7"/>
                            </svg>
                        </div>
                        <div class="atype-info">
                            <div class="atype-name">Nhà quảng cáo</div>
                            <div class="atype-desc">Đưa website lên top tìm kiếm, tăng traffic chất lượng</div>
                        </div>
                        <span class="atype-check" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                    </label>
                </div>

                <!-- Bước 2: thông tin cá nhân -->
                <div class="reg-step mt"><b>2</b><span>Thông tin cá nhân</span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="8 9 12 5 16 9"/><polyline points="16 15 12 19 8 15"/></svg></div>

                <div class="fg-row">
                    <div class="fg">
                        <label for="reg-username"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Tên đăng nhập <span style="color:#ef4444">*</span></label>
                        <div class="fg-input-wrap">
                            <input type="text" id="reg-username" name="username" required placeholder="Nhập tên đăng nhập" autocomplete="username" pattern="[a-zA-Z0-9]+" minlength="3" maxlength="30" title="Chỉ chữ cái và số, 3-30 ký tự" value="<?php echo esc_attr( $_POST['username'] ?? '' ); ?>">
                        </div>
                    </div>
                    <div class="fg">
                        <label for="reg-phone"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>Số điện thoại <span style="color:#ef4444">*</span></label>
                        <div class="fg-input-wrap">
                            <input type="tel" id="reg-phone" name="phone" required placeholder="Nhập số điện thoại" autocomplete="tel" value="<?php echo esc_attr( $_POST['phone'] ?? '' ); ?>">
                        </div>
                    </div>
                </div>

                <div class="fg">
                    <label for="reg-email"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>Email <span style="color:#ef4444">*</span></label>
                    <div class="fg-input-wrap">
                        <input type="email" id="reg-email" name="email" required placeholder="Nhập email của bạn" autocomplete="email" value="<?php echo esc_attr( $_POST['email'] ?? '' ); ?>">
                    </div>
                </div>

                <div class="fg">
                    <label for="reg-password"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Mật khẩu <span style="color:#ef4444">*</span></label>
                    <div class="fg-input-wrap">
                        <input type="password" id="reg-password" name="password" required minlength="6" placeholder="Tối thiểu 6 ký tự" autocomplete="new-password">
                        <button type="button" class="pw-toggle" onclick="togglePw('reg-password',this)" aria-label="Hiện mật khẩu">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="reg-terms">
                    <input type="checkbox" id="reg-terms" name="terms" required>
                    <label for="reg-terms" style="cursor:pointer;margin:0;font-weight:400;font-size:13.5px;color:#475569;display:inline">Tôi đồng ý với <a href="<?php echo home_url('/dieu-khoan'); ?>" target="_blank">Điều khoản sử dụng</a> và <a href="<?php echo home_url('/dieu-khoan#bao-mat'); ?>" target="_blank">Chính sách bảo mật</a></label>
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
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="8" y1="12" x2="15" y2="12"/><polyline points="12 9 15 12 12 15"/></svg>
                    <span id="regBtnText"><?php echo $posted_type === 'customer' ? 'Đăng ký Nhà quảng cáo' : 'Đăng ký ngay'; ?></span>
                </button>
            </form>

        <div class="auth-divider">hoặc</div>
        <div class="auth-footer">
            <p>Đã có tài khoản? <a href="<?php echo home_url('/dang-nhap'); ?>">Đăng nhập</a></p>
        </div>
    </div>
</div>

<?php include get_template_directory() . '/includes/auth-scripts.php'; ?>
<script>
var brandContent={
    user:{
        title:'Chia sẻ link.<br><span>Nhận thưởng.</span>',
        desc:'Nền tảng tăng traffic từ người dùng thật. Rút gọn link để kiếm tiền hoặc mua traffic chất lượng cho website của bạn.',
        feats:[
            {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="#E8A838" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',h:'Rút tiền nhanh chóng',p:'Thanh toán qua ngân hàng hoặc USDT, xử lý trong 24h'},
            {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="#E8A838" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',h:'Traffic người dùng thật',p:'100% lượt truy cập từ người thật, chống gian lận tự động'},
            {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="#E8A838" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>',h:'Thống kê chi tiết',p:'Dashboard trực quan, theo dõi thu nhập và chiến dịch real-time'}
        ]
    },
    customer:{
        title:'Tăng traffic.<br><span>Tăng doanh thu.</span>',
        desc:'Mua traffic chất lượng cao từ người dùng thật 100%. Tối ưu SEO, tăng thứ hạng Google với chi phí hợp lý.',
        feats:[
            {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="#E8A838" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>',h:'Người dùng thật 100%',p:'Traffic từ người thật, không bot, tăng CTR và giảm bounce rate'},
            {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="#E8A838" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',h:'Phân phối đều trong ngày',p:'Thuật toán tự động phân bổ traffic đều đặn, tự nhiên như organic'},
            {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="#E8A838" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>',h:'Báo cáo minh bạch',p:'Theo dõi chi tiết từng lượt view, chi phí và tiến độ chiến dịch'}
        ]
    }
};
function pickType(type){
    var cu=document.getElementById('cardUser'),cc=document.getElementById('cardCustomer');
    var sub=document.getElementById('regSubtitle'),btn=document.getElementById('regBtnText');
    cu.classList.remove('active');cc.classList.remove('active');
    if(type==='customer'){
        cc.classList.add('active');cc.querySelector('input').checked=true;
        sub.textContent='Tăng traffic website với người dùng thật 100%';
        btn.textContent='Đăng ký Nhà quảng cáo';
    } else {
        cu.classList.add('active');cu.querySelector('input').checked=true;
        sub.textContent='Tạo tài khoản miễn phí và bắt đầu kiếm tiền ngay hôm nay';
        btn.textContent='Đăng ký ngay';
    }
    // Update brand panel
    var bc=brandContent[type];
    var bt=document.getElementById('brandTitle');
    var bd=document.getElementById('brandDesc');
    var bf=document.getElementById('brandFeatures');
    if(bt)bt.innerHTML=bc.title;
    if(bd)bd.textContent=bc.desc;
    if(bf){
        var html='';
        bc.feats.forEach(function(f){
            html+='<div class="auth-feat"><div class="auth-feat-icon">'+f.icon+'</div><div class="auth-feat-text"><h4>'+f.h+'</h4><p>'+f.p+'</p></div></div>';
        });
        bf.innerHTML=html;
    }
}
<?php if ( $posted_type === 'customer' ): ?>
pickType('customer');
<?php endif; ?>
</script>
<?php wp_footer(); ?>
</body>
</html>
