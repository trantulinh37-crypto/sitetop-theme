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
    echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
}

// Helper to get option with default
function _lno($key, $default = '') {
    return linkngon_get_option($key, $default);
}
?>
<div class="wrap">
<h1>Settings</h1>

<form method="post">
<?php wp_nonce_field('linkngon_settings_save'); ?>

<h2 class="title">Financial Settings</h2>
<table class="form-table">
    <tr>
        <th>Min Withdrawal Amount</th>
        <td><input type="number" name="min_withdrawal" value="<?php echo esc_attr(_lno('min_withdrawal', 50000)); ?>" min="0" step="1000"> <span class="description">VND</span></td>
    </tr>
    <tr>
        <th>Min Deposit Amount</th>
        <td><input type="number" name="min_deposit_amount" value="<?php echo esc_attr(_lno('min_deposit_amount', 50000)); ?>" min="0" step="1000"> <span class="description">VND</span></td>
    </tr>
    <tr>
        <th>Customer Min Balance</th>
        <td><input type="number" name="customer_min_balance" value="<?php echo esc_attr(_lno('customer_min_balance', 20000)); ?>" min="0" step="1000"> <span class="description">VND - Min balance for campaigns to stay active</span></td>
    </tr>
</table>

<h2 class="title">Pricing - Customer Pays per View</h2>
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

<h2 class="title">User Rewards per View</h2>
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
        <th>User Reward Percent</th>
        <td><input type="number" name="keyword_user_reward_percent" value="<?php echo esc_attr(_lno('keyword_user_reward_percent', 80)); ?>" min="0" max="100"> <span class="description">% of price_per_view used for new campaign user_reward</span></td>
    </tr>
</table>

<h2 class="title">Security & IP Settings</h2>
<table class="form-table">
    <tr>
        <th>IP Daily Limit (verified visits)</th>
        <td><input type="number" name="shortlink_ip_limit_24h" value="<?php echo esc_attr(_lno('shortlink_ip_limit_24h', 5)); ?>" min="1" max="100"> <span class="description">Max verified visits per IP per day</span></td>
    </tr>
    <tr>
        <th>Max Tasks per IP per Day</th>
        <td><input type="number" name="max_tasks_per_ip_per_day" value="<?php echo esc_attr(_lno('max_tasks_per_ip_per_day', 10)); ?>" min="1" max="100"></td>
    </tr>
    <tr>
        <th>Verify Code Expiry</th>
        <td><input type="number" name="verify_code_expiry" value="<?php echo esc_attr(_lno('verify_code_expiry', 600)); ?>" min="60" step="60"> <span class="description">seconds (default 600 = 10 min)</span></td>
    </tr>
    <tr>
        <th>Detect VPN/Proxy</th>
        <td>
            <select name="detect_vpn_proxy">
                <option value="1" <?php selected(_lno('detect_vpn_proxy', 1), 1); ?>>Enabled</option>
                <option value="0" <?php selected(_lno('detect_vpn_proxy', 1), 0); ?>>Disabled</option>
            </select>
        </td>
    </tr>
    <tr>
        <th>Block Proxy IPs</th>
        <td>
            <select name="block_proxy_ip">
                <option value="1" <?php selected(_lno('block_proxy_ip', 1), 1); ?>>Yes</option>
                <option value="0" <?php selected(_lno('block_proxy_ip', 1), 0); ?>>No</option>
            </select>
        </td>
    </tr>
    <tr>
        <th>Block VPN IPs</th>
        <td>
            <select name="block_vpn_ip">
                <option value="1" <?php selected(_lno('block_vpn_ip', 1), 1); ?>>Yes</option>
                <option value="0" <?php selected(_lno('block_vpn_ip', 1), 0); ?>>No</option>
            </select>
        </td>
    </tr>
    <tr>
        <th>Block Datacenter IPs</th>
        <td>
            <select name="block_datacenter_ip">
                <option value="0" <?php selected(_lno('block_datacenter_ip', 0), 0); ?>>No</option>
                <option value="1" <?php selected(_lno('block_datacenter_ip', 0), 1); ?>>Yes</option>
            </select>
        </td>
    </tr>
</table>

<h2 class="title">Widget & Campaign</h2>
<table class="form-table">
    <tr>
        <th>Widget Default Countdown</th>
        <td><input type="number" name="widget_default_countdown" value="<?php echo esc_attr(_lno('widget_default_countdown', 30)); ?>" min="10" max="300"> <span class="description">seconds (displayed on widget)</span></td>
    </tr>
</table>

<h2 class="title">Cleanup & Maintenance</h2>
<table class="form-table">
    <tr>
        <th>Old Visits Retention</th>
        <td><input type="number" name="cleanup_old_visits" value="<?php echo esc_attr(_lno('cleanup_old_visits', 30)); ?>" min="7" max="365"> <span class="description">days (non-financial visits only)</span></td>
    </tr>
    <tr>
        <th>Inactive User Cleanup</th>
        <td><input type="number" name="inactive_user_days" value="<?php echo esc_attr(_lno('inactive_user_days', 10)); ?>" min="5" max="365"> <span class="description">days after registration (no activity)</span></td>
    </tr>
</table>

<p class="submit">
    <input type="submit" name="linkngon_save_settings" class="button-primary" value="Save Settings">
</p>
</form>

</div>
