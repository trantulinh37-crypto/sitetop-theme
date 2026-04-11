<?php
/**
 * Template Name: Đăng ký
 * Traffictop.net V2 - Register Page
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( is_user_logged_in() ) {
    wp_redirect( traffictop_get_dashboard_url() );
    exit;
}

$error = '';
$posted_type = sanitize_text_field( $_POST['account_type'] ?? ( $_GET['type'] ?? 'user' ) );
if ( ! in_array( $posted_type, array( 'user', 'customer' ), true ) ) $posted_type = 'user';
$ref_code = sanitize_user( $_POST['ref'] ?? ( $_GET['ref'] ?? '' ) );

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'traffictop_register' ) ) {
    $username     = sanitize_user( $_POST['username'] ?? '' );
    $email        = sanitize_email( $_POST['email'] ?? '' );
    $phone        = sanitize_text_field( $_POST['phone'] ?? '' );
    $password     = $_POST['password'] ?? '';
    $password2    = $_POST['password2'] ?? '';
    $account_type = $posted_type;

    if ( empty( $username ) || empty( $email ) || empty( $password ) ) {
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
    } elseif ( strlen( $password ) < 6 ) {
        $error = 'Mật khẩu tối thiểu 6 ký tự';
    } elseif ( $password !== $password2 ) {
        $error = 'Mật khẩu xác nhận không khớp';
    } else {
        $user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            $error = $user_id->get_error_message();
        } else {
            update_user_meta( $user_id, 'phone', $phone );

            // Save referral info if ref param provided
            if ( ! empty( $ref_code ) && traffictop_get_option( 'referral_enabled', 0 ) ) {
                $referrer = get_user_by( 'login', $ref_code );
                if ( $referrer && $referrer->ID !== $user_id ) {
                    update_user_meta( $user_id, 'traffictop_referred_by', $referrer->ID );
                    update_user_meta( $user_id, 'traffictop_referred_at', traffictop_current_time() );
                }
            }

            // Set role based on account type
            $user = new WP_User( $user_id );
            if ( $account_type === 'customer' ) {
                $user->set_role( 'customer' );
                // Initialize customer balance
                global $wpdb;
                $p = $wpdb->prefix . 'traffictop_';
                $wpdb->insert( "{$p}customer_balance", array(
                    'user_id' => $user_id, 'balance' => 0, 'total_deposited' => 0, 'total_spent' => 0,
                ));
            }

            // Send verification email
            traffictop_send_verification_email( $user_id );
            update_user_meta( $user_id, 'traffictop_verify_last_sent', time() );

            // Redirect to login with success message
            wp_redirect( home_url( '/dang-nhap?registered=1' ) );
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
.atype-label{font-weight:600;font-size:14px;margin-bottom:10px;color:#1e293b;display:flex;align-items:center;gap:6px}
.atype-label .req{color:#ef4444;font-size:16px}
.atype-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px}
.atype-card{position:relative;border:2px solid #e2e8f0;border-radius:14px;padding:16px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:12px;background:#fff}
.atype-card:hover{border-color:#93c5fd;background:#f8faff}
.atype-card.active{border-color:#3b82f6;background:#eff6ff;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.atype-card input{position:absolute;opacity:0;pointer-events:none}
.atype-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.atype-icon.ic-user{background:#059669}
.atype-icon.ic-cust{background:#d97706}
.atype-info{flex:1;min-width:0}
.atype-name{font-weight:700;font-size:14px;color:#1e293b}
.atype-desc{font-size:11px;color:#64748b;margin-top:1px}
.atype-check{position:absolute;top:8px;right:8px;width:22px;height:22px;border-radius:50%;background:#3b82f6;display:none;align-items:center;justify-content:center}
.atype-card.active .atype-check{display:flex}
@media(max-width:400px){.atype-row{grid-template-columns:1fr} .atype-card{padding:12px}}
</style>
</head>
<body>

<div class="auth-page">
    <div class="auth-card wide">
        <div class="auth-logo">
            <?php $ln_icon = get_option('traffictop_widget_icon',''); ?>
            <a href="<?php echo home_url(); ?>">
                <?php if($ln_icon): ?><img src="<?php echo esc_url($ln_icon); ?>" width="28" height="28" alt=""><?php else: ?><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><?php endif; ?>
                Traffictop.net
            </a>
        </div>

        <div class="auth-form-header">
            <h2>Tạo tài khoản</h2>
            <p id="regSubtitle"><?php echo $posted_type === 'customer' ? 'Tăng traffic website với người dùng thật 100%' : 'Tạo tài khoản miễn phí và bắt đầu kiếm tiền ngay hôm nay'; ?></p>
        </div>

            <?php if ( $error ) : ?>
                <div class="auth-error">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php echo esc_html( $error ); ?>
                </div>
            <?php endif; ?>

            <form method="post" id="regForm">
                <?php wp_nonce_field( 'traffictop_register' ); ?>
                <?php if ( ! empty( $ref_code ) ) : ?>
                <input type="hidden" name="ref" value="<?php echo esc_attr( $ref_code ); ?>">
                <?php endif; ?>

                <!-- Loại tài khoản -->
                <div class="atype-label">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                    Loại tài khoản <span class="req">*</span>
                </div>
                <div class="atype-row">
                    <label class="atype-card<?php echo $posted_type === 'user' ? ' active' : ''; ?>" id="cardUser" onclick="pickType('user')">
                        <input type="radio" name="account_type" value="user" <?php checked( $posted_type, 'user' ); ?>>
                        <div class="atype-icon ic-user">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M6 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><line x1="12" y1="14" x2="12" y2="18"/><circle cx="8" cy="18" r="2"/><circle cx="16" cy="18" r="2"/></svg>
                        </div>
                        <div class="atype-info">
                            <div class="atype-name">Người kiếm tiền</div>
                            <div class="atype-desc">Chia sẻ link, nhận thưởng mỗi lượt view</div>
                        </div>
                        <div class="atype-check">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                    </label>
                    <label class="atype-card<?php echo $posted_type === 'customer' ? ' active' : ''; ?>" id="cardCustomer" onclick="pickType('customer')">
                        <input type="radio" name="account_type" value="customer" <?php checked( $posted_type, 'customer' ); ?>>
                        <div class="atype-icon ic-cust">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                        </div>
                        <div class="atype-info">
                            <div class="atype-name">Nhà quảng cáo</div>
                            <div class="atype-desc">Mua traffic thật cho website của bạn</div>
                        </div>
                        <div class="atype-check">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                    </label>
                </div>

                <div class="fg-row">
                    <div class="fg">
                        <label for="reg-username">Tên đăng nhập <span style="color:#ef4444">*</span></label>
                        <div class="fg-input-wrap">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <input type="text" id="reg-username" name="username" required placeholder="Tên đăng nhập" autocomplete="username" pattern="[a-zA-Z0-9]+" minlength="3" maxlength="30" title="Chỉ chữ cái và số, 3-30 ký tự" value="<?php echo esc_attr( $_POST['username'] ?? '' ); ?>">
                        </div>
                    </div>
                    <div class="fg">
                        <label for="reg-phone">Số điện thoại <span style="color:#ef4444">*</span></label>
                        <div class="fg-input-wrap">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            <input type="tel" id="reg-phone" name="phone" required placeholder="0123456789" autocomplete="tel" value="<?php echo esc_attr( $_POST['phone'] ?? '' ); ?>">
                        </div>
                    </div>
                </div>

                <div class="fg">
                    <label for="reg-email">Email <span style="color:#ef4444">*</span></label>
                    <div class="fg-input-wrap">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <input type="email" id="reg-email" name="email" required placeholder="email@example.com" autocomplete="email" value="<?php echo esc_attr( $_POST['email'] ?? '' ); ?>">
                    </div>
                </div>

                <div class="fg-row">
                    <div class="fg">
                        <label for="reg-password">Mật khẩu <span style="color:#ef4444">*</span></label>
                        <div class="fg-input-wrap">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" id="reg-password" name="password" required minlength="6" placeholder="Tối thiểu 6 ký tự" autocomplete="new-password">
                            <button type="button" class="pw-toggle" onclick="togglePw('reg-password',this)" aria-label="Hiện mật khẩu">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="fg">
                        <label for="reg-password2">Xác nhận mật khẩu <span style="color:#ef4444">*</span></label>
                        <div class="fg-input-wrap">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <input type="password" id="reg-password2" name="password2" required minlength="6" placeholder="Nhập lại mật khẩu" autocomplete="new-password">
                            <button type="button" class="pw-toggle" onclick="togglePw('reg-password2',this)" aria-label="Hiện mật khẩu">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:8px;margin-bottom:18px">
                    <input type="checkbox" id="reg-terms" name="terms" required style="width:18px;height:18px;accent-color:#3b82f6;cursor:pointer">
                    <label for="reg-terms" style="font-size:13px;color:#475569;cursor:pointer">Tôi đồng ý với <a href="<?php echo home_url('/dieu-khoan'); ?>" target="_blank" style="color:#3b82f6;font-weight:600;text-decoration:none">Điều khoản sử dụng</a></label>
                </div>

                <button type="submit" class="auth-btn" id="regBtn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
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
