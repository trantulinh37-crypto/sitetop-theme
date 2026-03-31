<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

// Handle save
if(isset($_POST['linkngon_save_settings']) && wp_verify_nonce($_POST['_wpnonce'],'linkngon_settings_save')){
    $fields = [
        // Financial
        'min_withdrawal', 'min_deposit_amount', 'customer_min_balance',
        // Pricing - Keyword
        'keyword_price_1step', 'keyword_price_2step', 'keyword_price_nocode',
        // Pricing - Direct & Social
        'direct_price_1step', 'social_price_1step',
        // User rewards
        'keyword_user_1step', 'keyword_user_2step', 'keyword_user_nocode',
        'direct_user_1step', 'social_user_1step',
        'keyword_user_reward_percent',
        // Security
        'shortlink_ip_limit_24h', 'verify_code_expiry', 'max_tasks_per_ip_per_day',
        'detect_vpn_proxy', 'block_proxy_ip', 'block_vpn_ip', 'block_datacenter_ip',
        // Widget & Campaign
        'widget_default_countdown',
        // Cleanup
        'cleanup_old_visits', 'inactive_user_days',
    ];

    foreach($fields as $f){
        if(isset($_POST[$f])){
            linkngon_update_option($f, sanitize_text_field($_POST[$f]));
        }
    }
    echo '<div class="notice notice-success is-dismissible"><p>Đã lưu cài đặt thành công.</p></div>';
}

// Helper to get option with default
function _lno($key, $default = '') {
    return linkngon_get_option($key, $default);
}
?>
<div class="wrap">
<h1>Cài đặt</h1>

<form method="post">
<?php wp_nonce_field('linkngon_settings_save'); ?>

<h2 class="title">Cài đặt tài chính</h2>
<table class="form-table">
    <tr>
        <th>Số tiền rút tối thiểu</th>
        <td><input type="number" name="min_withdrawal" value="<?php echo esc_attr(_lno('min_withdrawal', 50000)); ?>" min="0" step="1000"> <span class="description">VND</span></td>
    </tr>
    <tr>
        <th>Số tiền nạp tối thiểu</th>
        <td><input type="number" name="min_deposit_amount" value="<?php echo esc_attr(_lno('min_deposit_amount', 50000)); ?>" min="0" step="1000"> <span class="description">VND</span></td>
    </tr>
    <tr>
        <th>Số dư tối thiểu khách hàng</th>
        <td><input type="number" name="customer_min_balance" value="<?php echo esc_attr(_lno('customer_min_balance', 20000)); ?>" min="0" step="1000"> <span class="description">VND - Số dư tối thiểu để chiến dịch hoạt động</span></td>
    </tr>
</table>

<h2 class="title">Giá - Khách hàng trả mỗi lượt xem</h2>
<table class="form-table">
    <tr>
        <th>Keyword 1step</th>
        <td><input type="number" name="keyword_price_1step" value="<?php echo esc_attr(_lno('keyword_price_1step', 1200)); ?>" min="0" step="100"> <span class="description">VND</span></td>
    </tr>
    <tr>
        <th>Keyword 2step</th>
        <td><input type="number" name="keyword_price_2step" value="<?php echo esc_attr(_lno('keyword_price_2step', 1500)); ?>" min="0" step="100"> <span class="description">VND</span></td>
    </tr>
    <tr>
        <th>Keyword nocode</th>
        <td><input type="number" name="keyword_price_nocode" value="<?php echo esc_attr(_lno('keyword_price_nocode', 1200)); ?>" min="0" step="100"> <span class="description">VND</span></td>
    </tr>
    <tr>
        <th>Direct 1step</th>
        <td><input type="number" name="direct_price_1step" value="<?php echo esc_attr(_lno('direct_price_1step', 1200)); ?>" min="0" step="100"> <span class="description">VND</span></td>
    </tr>
    <tr>
        <th>Social 1step</th>
        <td><input type="number" name="social_price_1step" value="<?php echo esc_attr(_lno('social_price_1step', 1200)); ?>" min="0" step="100"> <span class="description">VND</span></td>
    </tr>
</table>

<h2 class="title">Thưởng người dùng mỗi lượt xem</h2>
<table class="form-table">
    <tr>
        <th>Keyword 1step</th>
        <td><input type="number" name="keyword_user_1step" value="<?php echo esc_attr(_lno('keyword_user_1step', 800)); ?>" min="0" step="100"> <span class="description">VND</span></td>
    </tr>
    <tr>
        <th>Keyword 2step</th>
        <td><input type="number" name="keyword_user_2step" value="<?php echo esc_attr(_lno('keyword_user_2step', 1000)); ?>" min="0" step="100"> <span class="description">VND</span></td>
    </tr>
    <tr>
        <th>Keyword nocode</th>
        <td><input type="number" name="keyword_user_nocode" value="<?php echo esc_attr(_lno('keyword_user_nocode', 800)); ?>" min="0" step="100"> <span class="description">VND</span></td>
    </tr>
    <tr>
        <th>Direct 1step</th>
        <td><input type="number" name="direct_user_1step" value="<?php echo esc_attr(_lno('direct_user_1step', 500)); ?>" min="0" step="100"> <span class="description">VND</span></td>
    </tr>
    <tr>
        <th>Social 1step</th>
        <td><input type="number" name="social_user_1step" value="<?php echo esc_attr(_lno('social_user_1step', 700)); ?>" min="0" step="100"> <span class="description">VND</span></td>
    </tr>
    <tr>
        <th>Tỷ lệ thưởng người dùng</th>
        <td><input type="number" name="keyword_user_reward_percent" value="<?php echo esc_attr(_lno('keyword_user_reward_percent', 80)); ?>" min="0" max="100"> <span class="description">% của giá mỗi lượt dùng để tính thưởng cho chiến dịch mới</span></td>
    </tr>
</table>

<h2 class="title">Bảo mật & IP</h2>
<table class="form-table">
    <tr>
        <th>Giới hạn IP/ngày (lượt verified)</th>
        <td><input type="number" name="shortlink_ip_limit_24h" value="<?php echo esc_attr(_lno('shortlink_ip_limit_24h', 5)); ?>" min="1" max="100"> <span class="description">Số lượt verified tối đa mỗi IP mỗi ngày</span></td>
    </tr>
    <tr>
        <th>Số nhiệm vụ tối đa/IP/ngày</th>
        <td><input type="number" name="max_tasks_per_ip_per_day" value="<?php echo esc_attr(_lno('max_tasks_per_ip_per_day', 10)); ?>" min="1" max="100"></td>
    </tr>
    <tr>
        <th>Thời gian hết hạn mã xác minh</th>
        <td><input type="number" name="verify_code_expiry" value="<?php echo esc_attr(_lno('verify_code_expiry', 600)); ?>" min="60" step="60"> <span class="description">giây (mặc định 600 = 10 phút)</span></td>
    </tr>
    <tr>
        <th>Phát hiện VPN/Proxy</th>
        <td>
            <select name="detect_vpn_proxy">
                <option value="1" <?php selected(_lno('detect_vpn_proxy', 1), 1); ?>>Bật</option>
                <option value="0" <?php selected(_lno('detect_vpn_proxy', 1), 0); ?>>Tắt</option>
            </select>
        </td>
    </tr>
    <tr>
        <th>Chặn IP Proxy</th>
        <td>
            <select name="block_proxy_ip">
                <option value="1" <?php selected(_lno('block_proxy_ip', 1), 1); ?>>Có</option>
                <option value="0" <?php selected(_lno('block_proxy_ip', 1), 0); ?>>Không</option>
            </select>
        </td>
    </tr>
    <tr>
        <th>Chặn IP VPN</th>
        <td>
            <select name="block_vpn_ip">
                <option value="1" <?php selected(_lno('block_vpn_ip', 1), 1); ?>>Có</option>
                <option value="0" <?php selected(_lno('block_vpn_ip', 1), 0); ?>>Không</option>
            </select>
        </td>
    </tr>
    <tr>
        <th>Chặn IP Datacenter</th>
        <td>
            <select name="block_datacenter_ip">
                <option value="0" <?php selected(_lno('block_datacenter_ip', 0), 0); ?>>Không</option>
                <option value="1" <?php selected(_lno('block_datacenter_ip', 0), 1); ?>>Có</option>
            </select>
        </td>
    </tr>
</table>

<h2 class="title">Widget & Chiến dịch</h2>
<table class="form-table">
    <tr>
        <th>Đếm ngược mặc định của Widget</th>
        <td><input type="number" name="widget_default_countdown" value="<?php echo esc_attr(_lno('widget_default_countdown', 30)); ?>" min="10" max="300"> <span class="description">giây (hiển thị trên widget)</span></td>
    </tr>
</table>

<h2 class="title">Dọn dẹp & Bảo trì</h2>
<table class="form-table">
    <tr>
        <th>Lưu trữ lượt truy cập cũ</th>
        <td><input type="number" name="cleanup_old_visits" value="<?php echo esc_attr(_lno('cleanup_old_visits', 30)); ?>" min="7" max="365"> <span class="description">ngày (chỉ lượt không liên quan tài chính)</span></td>
    </tr>
    <tr>
        <th>Dọn dẹp user không hoạt động</th>
        <td><input type="number" name="inactive_user_days" value="<?php echo esc_attr(_lno('inactive_user_days', 10)); ?>" min="5" max="365"> <span class="description">ngày sau khi đăng ký (không có hoạt động)</span></td>
    </tr>
</table>

<p class="submit">
    <input type="submit" name="linkngon_save_settings" class="button-primary" value="Lưu cài đặt">
</p>
</form>

</div>
