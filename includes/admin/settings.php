<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) return;

// Save
if (isset($_POST['linkngon_save']) && wp_verify_nonce($_POST['_wpnonce'], 'linkngon_settings')) {
    $fields = array(
        'min_withdrawal', 'min_deposit_amount', 'customer_min_balance',
        'keyword_price_1step', 'keyword_price_2step', 'keyword_price_nocode',
        'direct_price_1step', 'direct_price_2step', 'direct_price_nocode',
        'keyword_user_1step', 'keyword_user_2step', 'keyword_user_nocode',
        'direct_user_1step', 'direct_user_2step', 'direct_user_nocode',
        'keyword_user_reward_percent',
        'shortlink_ip_limit_24h', 'verify_code_expiry', 'widget_default_countdown',
        'detect_vpn_proxy', 'detect_ip_change', 'block_proxy_ip', 'block_vpn_ip',
        'deposit_bank', 'deposit_account', 'deposit_holder',
        'inactive_user_days',
    );
    foreach ($fields as $f) {
        if (isset($_POST[$f])) linkngon_update_option($f, sanitize_text_field($_POST[$f]));
    }
    echo '<div class="notice notice-success is-dismissible"><p>Đã lưu cài đặt!</p></div>';
}

function ln_opt($key, $default = '') { return linkngon_get_option($key, $default); }
?>
<div class="wrap">
<h1>Cài đặt LinkNgon</h1>
<form method="post">
<?php wp_nonce_field('linkngon_settings'); ?>

<h2 class="title">Giá Customer trả (VNĐ/view)</h2>
<table class="form-table">
<tr><th>Keyword 1-Step</th><td><input name="keyword_price_1step" type="number" value="<?php echo ln_opt('keyword_price_1step', 1200); ?>" class="small-text"> đ</td></tr>
<tr><th>Keyword 2-Step</th><td><input name="keyword_price_2step" type="number" value="<?php echo ln_opt('keyword_price_2step', 1500); ?>" class="small-text"> đ</td></tr>
<tr><th>Keyword No-Code</th><td><input name="keyword_price_nocode" type="number" value="<?php echo ln_opt('keyword_price_nocode', 1200); ?>" class="small-text"> đ</td></tr>
<tr><th>Direct 1-Step</th><td><input name="direct_price_1step" type="number" value="<?php echo ln_opt('direct_price_1step', 1200); ?>" class="small-text"> đ</td></tr>
<tr><th>Direct 2-Step</th><td><input name="direct_price_2step" type="number" value="<?php echo ln_opt('direct_price_2step', 1200); ?>" class="small-text"> đ</td></tr>
<tr><th>Direct No-Code</th><td><input name="direct_price_nocode" type="number" value="<?php echo ln_opt('direct_price_nocode', 1200); ?>" class="small-text"> đ</td></tr>
</table>

<h2 class="title">User nhận (VNĐ/view)</h2>
<table class="form-table">
<tr><th>Keyword 1-Step</th><td><input name="keyword_user_1step" type="number" value="<?php echo ln_opt('keyword_user_1step', 800); ?>" class="small-text"> đ</td></tr>
<tr><th>Keyword 2-Step</th><td><input name="keyword_user_2step" type="number" value="<?php echo ln_opt('keyword_user_2step', 1000); ?>" class="small-text"> đ</td></tr>
<tr><th>Keyword No-Code</th><td><input name="keyword_user_nocode" type="number" value="<?php echo ln_opt('keyword_user_nocode', 800); ?>" class="small-text"> đ</td></tr>
<tr><th>Direct 1-Step</th><td><input name="direct_user_1step" type="number" value="<?php echo ln_opt('direct_user_1step', 500); ?>" class="small-text"> đ</td></tr>
<tr><th>Direct 2-Step</th><td><input name="direct_user_2step" type="number" value="<?php echo ln_opt('direct_user_2step', 700); ?>" class="small-text"> đ</td></tr>
<tr><th>Direct No-Code</th><td><input name="direct_user_nocode" type="number" value="<?php echo ln_opt('direct_user_nocode', 800); ?>" class="small-text"> đ</td></tr>
<tr><th>% reward từ price</th><td><input name="keyword_user_reward_percent" type="number" value="<?php echo ln_opt('keyword_user_reward_percent', 80); ?>" class="small-text" min="0" max="100"> %</td></tr>
</table>

<h2 class="title">Tài chính</h2>
<table class="form-table">
<tr><th>Rút tiền tối thiểu</th><td><input name="min_withdrawal" type="number" value="<?php echo ln_opt('min_withdrawal', 50000); ?>" class="small-text"> đ</td></tr>
<tr><th>Nạp tiền tối thiểu</th><td><input name="min_deposit_amount" type="number" value="<?php echo ln_opt('min_deposit_amount', 50000); ?>" class="small-text"> đ</td></tr>
<tr><th>Balance tối thiểu KH</th><td><input name="customer_min_balance" type="number" value="<?php echo ln_opt('customer_min_balance', 20000); ?>" class="small-text"> đ</td></tr>
</table>

<h2 class="title">Thông tin chuyển khoản</h2>
<table class="form-table">
<tr><th>Ngân hàng</th><td><input name="deposit_bank" class="regular-text" value="<?php echo esc_attr(ln_opt('deposit_bank', 'Vietcombank')); ?>"></td></tr>
<tr><th>Số tài khoản</th><td><input name="deposit_account" class="regular-text" value="<?php echo esc_attr(ln_opt('deposit_account', '')); ?>"></td></tr>
<tr><th>Chủ tài khoản</th><td><input name="deposit_holder" class="regular-text" value="<?php echo esc_attr(ln_opt('deposit_holder', '')); ?>"></td></tr>
</table>

<h2 class="title">Bảo mật & IP</h2>
<table class="form-table">
<tr><th>IP limit/ngày</th><td><input name="shortlink_ip_limit_24h" type="number" value="<?php echo ln_opt('shortlink_ip_limit_24h', 2); ?>" class="small-text"></td></tr>
<tr><th>Code hết hạn (giây)</th><td><input name="verify_code_expiry" type="number" value="<?php echo ln_opt('verify_code_expiry', 600); ?>" class="small-text"> giây</td></tr>
<tr><th>Countdown mặc định</th><td><input name="widget_default_countdown" type="number" value="<?php echo ln_opt('widget_default_countdown', 30); ?>" class="small-text"> giây</td></tr>
<tr><th>Detect VPN/Proxy</th><td><select name="detect_vpn_proxy"><option value="1" <?php selected(ln_opt('detect_vpn_proxy', 1), 1); ?>>Bật</option><option value="0" <?php selected(ln_opt('detect_vpn_proxy', 1), 0); ?>>Tắt</option></select></td></tr>
<tr><th>Detect IP thay đổi</th><td><select name="detect_ip_change"><option value="1" <?php selected(ln_opt('detect_ip_change', 1), 1); ?>>Bật</option><option value="0" <?php selected(ln_opt('detect_ip_change', 1), 0); ?>>Tắt</option></select></td></tr>
<tr><th>Block Proxy IP</th><td><select name="block_proxy_ip"><option value="1" <?php selected(ln_opt('block_proxy_ip', 1), 1); ?>>Bật</option><option value="0" <?php selected(ln_opt('block_proxy_ip', 1), 0); ?>>Tắt</option></select></td></tr>
<tr><th>Block VPN IP</th><td><select name="block_vpn_ip"><option value="1" <?php selected(ln_opt('block_vpn_ip', 1), 1); ?>>Bật</option><option value="0" <?php selected(ln_opt('block_vpn_ip', 1), 0); ?>>Tắt</option></select></td></tr>
</table>

<h2 class="title">Khác</h2>
<table class="form-table">
<tr><th>Xóa user inactive sau</th><td><input name="inactive_user_days" type="number" value="<?php echo ln_opt('inactive_user_days', 10); ?>" class="small-text"> ngày</td></tr>
</table>

<p class="submit"><input type="submit" name="linkngon_save" class="button-primary" value="Lưu cài đặt"></p>
</form>
</div>
