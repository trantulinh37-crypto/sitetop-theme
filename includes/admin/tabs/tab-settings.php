<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

if(isset($_POST['sitetop_save_settings']) && wp_verify_nonce($_POST['_wpnonce'],'sitetop_settings_save')){
    $fields = array(
        'min_withdrawal','max_withdrawal','min_deposit_amount','customer_min_balance','min_account_age_hours',
        // Tự tạm dừng camp khi bị báo lỗi
        'report_autopause_enabled','report_autopause_threshold',
        'keyword_price_1step','keyword_price_2step','keyword_price_nocode',
        'direct_price_1step','direct_price_2step','direct_price_nocode',
        'keyword_user_1step','keyword_user_2step','keyword_user_nocode',
        'direct_user_1step','direct_user_2step','direct_user_nocode',
        'onsite_extra_70','onsite_extra_80','onsite_extra_90','onsite_extra_100','onsite_extra_120','onsite_extra_150',
        'user_onsite_extra_70','user_onsite_extra_80','user_onsite_extra_90','user_onsite_extra_100','user_onsite_extra_120','user_onsite_extra_150',
        'shortlink_ip_limit_24h','verify_code_expiry','max_tasks_per_ip_per_day','shortlink_test_whitelist_ips',
        'detect_vpn_proxy','block_proxy_ip','block_vpn_ip','block_datacenter_ip',
        'widget_default_countdown','cleanup_old_visits','inactive_user_days',
        'deposit_bank','deposit_account','deposit_holder','deposit_usdt_erc20','deposit_usdt_trc20','deposit_usdt_rate',
        'deposit_show_bank','deposit_show_erc20','deposit_show_trc20',
        // DDoS
        'ddos_global_rate','ddos_burst_limit','ddos_sustained_limit',
        'ddos_violation_threshold','ddos_block_duration',
        // 4-layer DDoS (permanent block)
        'ddos_burst_perm_threshold','ddos_burst_perm_window',
        'ddos_hourly_limit','ddos_daily_limit','ddos_range_hourly_limit',
        'ddos_burst_enabled','ddos_hourly_enabled','ddos_daily_enabled','ddos_range_hourly_enabled',
        // SMTP
        'smtp_enabled','smtp_host','smtp_port','smtp_encryption',
        'smtp_username','smtp_password','smtp_from_email','smtp_from_name',
        // Turnstile
        'turnstile_enabled','turnstile_site_key','turnstile_secret_key','widget_captcha_enabled','unlock_captcha_enabled',
        // Referral
        'referral_enabled','referral_commission_percent','referral_min_payout','referral_duration_days',
        // Duyệt nguồn file gốc
        'require_source_approval','source_telegram',
        // Chặn trình duyệt ẩn danh
        'block_incognito',
        // Email notifications
        'email_withdrawal_pending','email_withdrawal_approved','email_withdrawal_rejected','email_withdrawal_completed',
        'email_deposit_pending','email_deposit_approved','email_deposit_rejected',
        'email_report_error','email_campaign_new',
        // Telegram admin notifications (token + chat id)
        'report_telegram_bot_token','report_telegram_chat_id',
        // Integrations
        'imgbb_api_key','contact_telegram','contact_signal','contact_zalo','contact_email',
        // Page Unlock
        'unlock_tutorial_video',
    );
    foreach($fields as $f) if(isset($_POST[$f])) sitetop_update_option($f, sanitize_text_field($_POST[$f]));

    // Widget button settings (stored with sitetop_ prefix in wp_options)
    if(isset($_POST['widget_color'])) update_option('sitetop_widget_color', sanitize_hex_color($_POST['widget_color']));
    if(isset($_POST['widget_text_color'])) update_option('sitetop_widget_text_color', sanitize_hex_color($_POST['widget_text_color']));
    if(isset($_POST['widget_icon'])) update_option('sitetop_widget_icon', esc_url_raw($_POST['widget_icon']));
    if(isset($_POST['widget_button_text'])) update_option('sitetop_widget_button_text', sanitize_text_field($_POST['widget_button_text']));

    // DDoS whitelist (textarea)
    if(isset($_POST['ddos_whitelist'])) sitetop_update_option('ddos_whitelist', sanitize_textarea_field($_POST['ddos_whitelist']));
    // VPN/Proxy whitelist (textarea)
    if(isset($_POST['vpn_ip_whitelist'])) sitetop_update_option('vpn_ip_whitelist', sanitize_textarea_field($_POST['vpn_ip_whitelist']));

    // Save deposit presets (dynamic rows)
    $presets = array();
    if(!empty($_POST['preset_amount']) && is_array($_POST['preset_amount'])){
        foreach($_POST['preset_amount'] as $i => $amt){
            $amt = intval($amt);
            $bonus = intval($_POST['preset_bonus'][$i] ?? 0);
            if($amt > 0) $presets[] = array('amount' => $amt, 'bonus' => $bonus);
        }
    }
    usort($presets, function($a,$b){ return $a['amount'] - $b['amount']; });
    sitetop_update_option('deposit_presets', json_encode($presets));

    echo '<div class="notice notice-success is-dismissible"><p>Đã lưu cài đặt!</p></div>';
}
function _lno($k,$d=''){return sitetop_get_option($k,$d);}
?>
<div class="wrap">
<style>
.ln-settings{max-width:900px}
.ln-section{background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px 24px;margin-bottom:20px}
.ln-section h2{margin:0 0 16px;font-size:15px;font-weight:700;color:#1d2327;padding-bottom:10px;border-bottom:1px solid #eee}
.ln-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px 20px}
.ln-grid.g2{grid-template-columns:1fr 1fr}
.ln-field label{display:block;font-size:12px;font-weight:600;color:#50575e;margin-bottom:4px}
.ln-field input,.ln-field select{width:100%;padding:8px 10px;border:1px solid #c3c4c7;border-radius:4px;font-size:13px}
.ln-field input:focus,.ln-field select:focus{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;outline:none}
.ln-field .unit{font-size:11px;color:#787c82;margin-top:2px}
@media(max-width:600px){.ln-grid{grid-template-columns:repeat(2,1fr)} .ln-grid.g2{grid-template-columns:repeat(2,1fr)}}
/* DDoS 4-layer toggle list — override global .ln-field input width:100% */
.ddos-toggles .ddos-toggle-list{display:flex;flex-direction:column;gap:6px;padding-top:4px}
.ddos-toggles .ddos-toggle{display:flex;align-items:center;gap:8px;font-size:13px;color:#1d2327;cursor:pointer;padding:4px 0}
.ddos-toggles .ddos-toggle input[type=checkbox]{width:16px!important;height:16px!important;margin:0!important;padding:0!important;border:1px solid #8c8f94;border-radius:3px;flex:none;cursor:pointer}
.ddos-toggles .ddos-toggle input[type=hidden]{display:none}
.ddos-toggles .ddos-toggle span{user-select:none}
.ddos-toggles .ddos-toggle:hover{color:#2271b1}
/* Xem trước nút LẤY MÃ — dựng lại đúng hình dạng thật của #tn-btn trong widget.js.php
   (vòng tròn 46px nằm trong footer trang đích, logo phủ kín nút, pill khi hiện mã) */
.wbtn-prev{margin-top:12px}
.wbtn-prev>label{display:block;font-size:12px;font-weight:600;color:#50575e;margin-bottom:6px}
.wbtn-page{border:1px solid #E3E8F2;border-radius:8px;overflow:hidden;background:#fff;max-width:420px}
.wbtn-body{padding:14px 16px 4px;display:flex;flex-direction:column;gap:7px}
.wbtn-ln{height:8px;border-radius:4px;background:#EDF0F5}
.wbtn-ln.m{width:84%}
.wbtn-ln.s{width:58%}
.wbtn-foot{margin-top:10px;background:#0F172A;padding:16px 16px 12px;text-align:center}
#widget-preview-btn{display:inline-flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;width:46px;height:46px;border-radius:50%;box-sizing:border-box;padding:0;overflow:hidden;font-size:9.5px;font-weight:800;letter-spacing:.4px;line-height:1.05;text-align:center;box-shadow:0 3px 10px rgba(0,0,0,.2)}
#widget-preview-btn svg,#widget-preview-btn img{width:16px;height:16px;display:block}
#widget-preview-btn.tn-logo img{width:100%;height:100%;object-fit:cover;border-radius:50%}
/* Có logo thì nút thật để nền trong suốt (tránh vòng màu lộ ra ở mép logo) — bản xem
   trước phải giống, không thì admin chọn màu xong lại thấy khác lúc chạy thật.
   !important để thắng inline style do JS đặt màu. */
#widget-preview-btn.tn-logo{background:transparent!important}
#widget-preview-text:empty{display:none}
.wbtn-cp{margin-top:14px;font-size:10px;color:rgba(255,255,255,.42)}
.wbtn-states{display:flex;gap:26px;align-items:center;flex-wrap:wrap;margin-top:12px}
.wbtn-st{display:flex;align-items:center;gap:8px;font-size:11px;color:#787c82}
.wbtn-cd{width:46px;height:46px;border-radius:50%;box-sizing:border-box;display:flex;align-items:center;justify-content:center;font-size:23px;font-weight:600;color:#fff;box-shadow:0 3px 10px rgba(0,0,0,.2)}
.wbtn-pill{display:inline-flex;align-items:center;gap:7px;border-radius:20px;padding:9px 15px;font-size:12px;font-weight:700;box-shadow:0 3px 10px rgba(0,0,0,.2)}
.wbtn-pill span{letter-spacing:2px}
.wbtn-pill svg{width:14px;height:14px;flex-shrink:0;opacity:.85}
</style>

<h1>Cài đặt SiteTop.net</h1>
<form method="post" class="ln-settings">
<?php wp_nonce_field('sitetop_settings_save'); ?>

<div class="ln-section">
    <h2>Giá khách hàng trả (đ/lượt)</h2>
    <div class="ln-grid">
        <div class="ln-field"><label>Keyword 1 bước</label><input type="number" name="keyword_price_1step" value="<?php echo _lno('keyword_price_1step',1200); ?>" step="1"></div>
        <div class="ln-field"><label>Keyword 2 bước</label><input type="number" name="keyword_price_2step" value="<?php echo _lno('keyword_price_2step',1500); ?>" step="1"></div>
        <div class="ln-field"><label>Keyword Mã cố định</label><input type="number" name="keyword_price_nocode" value="<?php echo _lno('keyword_price_nocode',1200); ?>" step="1"></div>
        <div class="ln-field"><label>Direct 1 bước</label><input type="number" name="direct_price_1step" value="<?php echo _lno('direct_price_1step',1200); ?>" step="1"></div>
        <div class="ln-field"><label>Direct 2 bước</label><input type="number" name="direct_price_2step" value="<?php echo _lno('direct_price_2step',1200); ?>" step="1"></div>
        <div class="ln-field"><label>Direct Mã cố định</label><input type="number" name="direct_price_nocode" value="<?php echo _lno('direct_price_nocode',1200); ?>" step="1"></div>
    </div>
</div>

<div class="ln-section">
    <h2>User nhận (đ/lượt)</h2>
    <div class="ln-grid">
        <div class="ln-field"><label>Keyword 1 bước</label><input type="number" name="keyword_user_1step" value="<?php echo _lno('keyword_user_1step',800); ?>" step="1"></div>
        <div class="ln-field"><label>Keyword 2 bước</label><input type="number" name="keyword_user_2step" value="<?php echo _lno('keyword_user_2step',1000); ?>" step="1"></div>
        <div class="ln-field"><label>Keyword Mã cố định</label><input type="number" name="keyword_user_nocode" value="<?php echo _lno('keyword_user_nocode',800); ?>" step="1"></div>
        <div class="ln-field"><label>Direct 1 bước</label><input type="number" name="direct_user_1step" value="<?php echo _lno('direct_user_1step',500); ?>" step="1"></div>
        <div class="ln-field"><label>Direct 2 bước</label><input type="number" name="direct_user_2step" value="<?php echo _lno('direct_user_2step',700); ?>" step="1"></div>
        <div class="ln-field"><label>Direct Mã cố định</label><input type="number" name="direct_user_nocode" value="<?php echo _lno('direct_user_nocode',800); ?>" step="1"></div>
    </div>
</div>

<div class="ln-section">
    <h2>Phụ phí Onsite (đ cộng thêm vào giá/lượt)</h2>
    <div class="ln-grid">
        <div class="ln-field"><label>70s</label><input type="number" name="onsite_extra_70" value="<?php echo _lno('onsite_extra_70',0); ?>" step="1"></div>
        <div class="ln-field"><label>80s</label><input type="number" name="onsite_extra_80" value="<?php echo _lno('onsite_extra_80',100); ?>" step="1"></div>
        <div class="ln-field"><label>90s</label><input type="number" name="onsite_extra_90" value="<?php echo _lno('onsite_extra_90',200); ?>" step="1"></div>
        <div class="ln-field"><label>100s</label><input type="number" name="onsite_extra_100" value="<?php echo _lno('onsite_extra_100',300); ?>" step="1"></div>
        <div class="ln-field"><label>120s</label><input type="number" name="onsite_extra_120" value="<?php echo _lno('onsite_extra_120',400); ?>" step="1"></div>
        <div class="ln-field"><label>150s</label><input type="number" name="onsite_extra_150" value="<?php echo _lno('onsite_extra_150',500); ?>" step="1"></div>
    </div>
</div>

<div class="ln-section">
    <h2>Phụ phí Onsite User (đ cộng thêm vào reward user)</h2>
    <div class="ln-grid">
        <div class="ln-field"><label>70s</label><input type="number" name="user_onsite_extra_70" value="<?php echo _lno('user_onsite_extra_70',0); ?>" step="1"></div>
        <div class="ln-field"><label>80s</label><input type="number" name="user_onsite_extra_80" value="<?php echo _lno('user_onsite_extra_80',0); ?>" step="1"></div>
        <div class="ln-field"><label>90s</label><input type="number" name="user_onsite_extra_90" value="<?php echo _lno('user_onsite_extra_90',0); ?>" step="1"></div>
        <div class="ln-field"><label>100s</label><input type="number" name="user_onsite_extra_100" value="<?php echo _lno('user_onsite_extra_100',0); ?>" step="1"></div>
        <div class="ln-field"><label>120s</label><input type="number" name="user_onsite_extra_120" value="<?php echo _lno('user_onsite_extra_120',0); ?>" step="1"></div>
        <div class="ln-field"><label>150s</label><input type="number" name="user_onsite_extra_150" value="<?php echo _lno('user_onsite_extra_150',0); ?>" step="1"></div>
    </div>
</div>

<div class="ln-section">
    <h2>Tài chính</h2>
    <div class="ln-grid">
        <div class="ln-field"><label>Rút tiền tối thiểu</label><input type="number" name="min_withdrawal" value="<?php echo _lno('min_withdrawal',50000); ?>" step="1"><div class="unit">VNĐ</div></div>
        <div class="ln-field"><label>Rút tiền tối đa / lần</label><input type="number" name="max_withdrawal" value="<?php echo _lno('max_withdrawal',0); ?>" step="1" min="0"><div class="unit">VNĐ — để <b>0</b> là không giới hạn</div></div>
        <div class="ln-field"><label>Chờ trước lần rút đầu</label><input type="number" name="min_account_age_hours" value="<?php echo _lno('min_account_age_hours',48); ?>" min="0" step="1"><div class="unit">giờ, tính từ lúc user đăng ký. Đặt 0 để tắt</div></div>
        <div class="ln-field"><label>Nạp tiền tối thiểu</label><input type="number" name="min_deposit_amount" value="<?php echo _lno('min_deposit_amount',50000); ?>" step="1"><div class="unit">VNĐ</div></div>
        <div class="ln-field"><label>Số dư tối thiểu KH</label><input type="number" name="customer_min_balance" value="<?php echo _lno('customer_min_balance',20000); ?>" step="1"><div class="unit">VNĐ - để campaign hoạt động</div></div>
    </div>
</div>

<div class="ln-section">
    <h2>Mức nạp nhanh & Khuyến mãi</h2>
    <p style="font-size:12px;color:#787c82;margin-bottom:14px">Cài đặt các mức nạp hiển thị trên trang nạp tiền của khách hàng. Bonus % sẽ được cộng thêm vào số dư.</p>
    <table class="widefat" id="presetTable" style="max-width:500px">
        <thead><tr><th>Số tiền (VNĐ)</th><th>Bonus %</th><th style="width:60px"></th></tr></thead>
        <tbody>
        <?php
        $presets = json_decode(_lno('deposit_presets','[]'), true);
        if(empty($presets)) $presets = array(
            array('amount'=>500000,'bonus'=>0),
            array('amount'=>1000000,'bonus'=>0),
            array('amount'=>5000000,'bonus'=>0),
            array('amount'=>10000000,'bonus'=>5),
            array('amount'=>20000000,'bonus'=>5),
            array('amount'=>50000000,'bonus'=>10),
        );
        foreach($presets as $i => $p):
        ?>
        <tr>
            <td><input type="number" name="preset_amount[]" value="<?php echo $p['amount']; ?>" step="1" style="width:100%"></td>
            <td><input type="number" name="preset_bonus[]" value="<?php echo $p['bonus']; ?>" min="0" max="100" style="width:100%"></td>
            <td><button type="button" class="button button-small" onclick="this.closest('tr').remove()" style="color:#dc3232">Xóa</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <button type="button" class="button" onclick="addPresetRow()" style="margin-top:8px">+ Thêm mức nạp</button>
    <script>
    function addPresetRow(){
        var tbody=document.querySelector('#presetTable tbody');
        var tr=document.createElement('tr');
        tr.innerHTML='<td><input type="number" name="preset_amount[]" value="" step="1" style="width:100%" placeholder="VD: 5000000"></td><td><input type="number" name="preset_bonus[]" value="0" min="0" max="100" style="width:100%"></td><td><button type="button" class="button button-small" onclick="this.closest(\'tr\').remove()" style="color:#dc3232">Xóa</button></td>';
        tbody.appendChild(tr);
    }
    </script>
</div>

<div class="ln-section">
    <h2>Thông tin chuyển khoản</h2>
    <div class="ln-grid">
        <div class="ln-field"><label>Hiện Ngân hàng</label><select name="deposit_show_bank"><option value="1" <?php selected(_lno('deposit_show_bank',1),1); ?>>Hiện</option><option value="0" <?php selected(_lno('deposit_show_bank',1),0); ?>>Ẩn</option></select></div>
        <div class="ln-field"><label>Hiện USDT (ERC20)</label><select name="deposit_show_erc20"><option value="1" <?php selected(_lno('deposit_show_erc20',1),1); ?>>Hiện</option><option value="0" <?php selected(_lno('deposit_show_erc20',1),0); ?>>Ẩn</option></select></div>
        <div class="ln-field"><label>Hiện USDT (TRC20)</label><select name="deposit_show_trc20"><option value="1" <?php selected(_lno('deposit_show_trc20',1),1); ?>>Hiện</option><option value="0" <?php selected(_lno('deposit_show_trc20',1),0); ?>>Ẩn</option></select></div>
        <div class="ln-field"><label>Ngân hàng</label><input type="text" name="deposit_bank" value="<?php echo esc_attr(_lno('deposit_bank','Vietcombank')); ?>"></div>
        <div class="ln-field"><label>Số tài khoản</label><input type="text" name="deposit_account" value="<?php echo esc_attr(_lno('deposit_account','')); ?>"></div>
        <div class="ln-field"><label>Chủ tài khoản</label><input type="text" name="deposit_holder" value="<?php echo esc_attr(_lno('deposit_holder','')); ?>"></div>
        <div class="ln-field"><label>USDT (ERC20)</label><input type="text" name="deposit_usdt_erc20" value="<?php echo esc_attr(_lno('deposit_usdt_erc20','')); ?>" placeholder="0x..."></div>
        <div class="ln-field"><label>USDT (TRC20)</label><input type="text" name="deposit_usdt_trc20" value="<?php echo esc_attr(_lno('deposit_usdt_trc20','')); ?>" placeholder="T..."></div>
        <div class="ln-field"><label>Tỷ giá USDT/VND</label><input type="number" name="deposit_usdt_rate" value="<?php echo esc_attr(_lno('deposit_usdt_rate','25000')); ?>" min="1" step="1"><div class="unit">VND/USDT</div></div>
    </div>
</div>

<div class="ln-section">
    <h2>Bảo mật & IP</h2>
    <div class="ln-grid">
        <div class="ln-field"><label>IP limit/ngày</label><input type="number" name="shortlink_ip_limit_24h" value="<?php echo _lno('shortlink_ip_limit_24h',2); ?>" min="1" max="100"><div class="unit">Lượt verified/IP/ngày</div></div>
        <div class="ln-field" style="grid-column:1/-1"><label>IP test (bỏ qua giới hạn IP)</label><textarea name="shortlink_test_whitelist_ips" rows="2" style="width:100%" placeholder="Mỗi dòng/dấu phẩy 1 IP — hậu tố * khớp tiền tố (vd 2001:ee0:*)"><?php echo esc_textarea( (string) _lno('shortlink_test_whitelist_ips','') ); ?></textarea><div class="unit">IP trong danh sách (hoặc admin đang đăng nhập) bỏ qua MỌI giới hạn IP: xoay-camp, trần thưởng/ngày, chặn trùng camp, chặn đổi IP — để test. Lượt vẫn tính tiền như khách thật.</div></div>
        <div class="ln-field"><label>Tasks/IP/ngày</label><input type="number" name="max_tasks_per_ip_per_day" value="<?php echo _lno('max_tasks_per_ip_per_day',10); ?>" min="1" max="100"></div>
        <div class="ln-field"><label>Code hết hạn</label><input type="number" name="verify_code_expiry" value="<?php echo _lno('verify_code_expiry',600); ?>" min="60" step="60"><div class="unit">giây (600 = 10 phút)</div></div>
        <div class="ln-field"><label>Detect VPN/Proxy</label><select name="detect_vpn_proxy"><option value="1" <?php selected(_lno('detect_vpn_proxy',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('detect_vpn_proxy',1),0); ?>>Tắt</option></select></div>
        <div class="ln-field"><label>Chặn Proxy</label><select name="block_proxy_ip"><option value="1" <?php selected(_lno('block_proxy_ip',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('block_proxy_ip',1),0); ?>>Tắt</option></select></div>
        <div class="ln-field"><label>Chặn VPN</label><select name="block_vpn_ip"><option value="1" <?php selected(_lno('block_vpn_ip',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('block_vpn_ip',1),0); ?>>Tắt</option></select></div>
        <div class="ln-field"><label>Tự dừng camp khi bị báo lỗi</label><select name="report_autopause_enabled"><option value="1" <?php selected(_lno('report_autopause_enabled',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('report_autopause_enabled',1),0); ?>>Tắt</option></select><div class="unit">Tự chuyển camp sang Tạm dừng + báo Telegram</div></div>
        <div class="ln-field"><label>Ngưỡng báo lỗi</label><input type="number" name="report_autopause_threshold" value="<?php echo _lno('report_autopause_threshold',5); ?>" min="1" step="1"><div class="unit">Số <b>IP khác nhau</b> cùng báo trong 1 giờ. Một người bấm nhiều lần chỉ tính là 1.</div></div>
        <div class="ln-field"><label>Chặn Datacenter</label><select name="block_datacenter_ip"><option value="0" <?php selected(_lno('block_datacenter_ip',0),0); ?>>Tắt</option><option value="1" <?php selected(_lno('block_datacenter_ip',0),1); ?>>Bật</option></select></div>
        <div class="ln-field" style="grid-column:1/-1">
            <label>Whitelist IP VPN/Proxy</label>
            <textarea name="vpn_ip_whitelist" rows="3" style="width:100%;font-family:monospace;font-size:12px"><?php echo esc_textarea(_lno('vpn_ip_whitelist','')); ?></textarea>
            <div class="unit">1 IP/dòng — các IP này bỏ qua mọi check VPN/Proxy/Datacenter/Fraud</div>
        </div>
    </div>
</div>

<div class="ln-section">
    <h2>Duyệt nguồn file gốc</h2>
    <p style="font-size:12px;color:#787c82;margin-bottom:14px">Bắt user khai báo nguồn file gốc và chờ Admin duyệt trước khi được rút gọn link / dùng API. Duyệt tại menu <b>Duyệt nguồn file</b>.</p>
    <div class="ln-grid">
        <div class="ln-field"><label>Chặn trình duyệt ẩn danh</label><select name="block_incognito"><option value="1" <?php selected(_lno('block_incognito',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('block_incognito',1),0); ?>>Tắt</option></select><div class="unit">Không có API chuẩn để nhận ra ẩn danh — hệ thống suy ra từ dấu vết trình duyệt nên CÓ THỂ chặn nhầm. User báo bị chặn oan thì tắt ô này là gỡ ngay, không cần deploy.</div></div>
        <div class="ln-field"><label>Bắt buộc duyệt nguồn</label><select name="require_source_approval"><option value="1" <?php selected(_lno('require_source_approval',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('require_source_approval',1),0); ?>>Tắt</option></select><div class="unit">Tắt = mọi user rút gọn link bình thường</div></div>
        <div class="ln-field"><label>Telegram Admin</label><input type="text" name="source_telegram" value="<?php echo esc_attr(sitetop_get_option('source_telegram','@sitetopnet')); ?>" placeholder="@sitetopnet"><div class="unit">Hiện trong lời nhắc gửi user</div></div>
    </div>
</div>

<div class="ln-section">
    <h2>Referral (Giới thiệu)</h2>
    <p style="font-size:12px;color:#787c82;margin-bottom:14px">Cài đặt hoa hồng khi người dùng giới thiệu bạn bè đăng ký và kiếm tiền.</p>
    <div class="ln-grid">
        <div class="ln-field"><label>Bật Referral</label><select name="referral_enabled"><option value="1" <?php selected(_lno('referral_enabled',0),1); ?>>Bật</option><option value="0" <?php selected(_lno('referral_enabled',0),0); ?>>Tắt</option></select></div>
        <div class="ln-field"><label>Hoa hồng %</label><input type="number" name="referral_commission_percent" value="<?php echo _lno('referral_commission_percent',20); ?>" min="0" max="100" step="1"><div class="unit">% thu nhập của người được giới thiệu</div></div>
        <div class="ln-field"><label>Rút tối thiểu referral</label><input type="number" name="referral_min_payout" value="<?php echo _lno('referral_min_payout',50000); ?>" step="1"><div class="unit">VNĐ</div></div>
        <div class="ln-field"><label>Thời hạn hoa hồng</label><input type="number" name="referral_duration_days" value="<?php echo _lno('referral_duration_days',0); ?>" min="0"><div class="unit">ngày (0 = vĩnh viễn)</div></div>
    </div>
</div>

<div class="ln-section">
    <h2>Cloudflare Turnstile (Captcha)</h2>
    <p style="font-size:12px;color:#787c82;margin-bottom:14px">Chống bot tự động verify. Lấy key tại <a href="https://dash.cloudflare.com/sign-up?to=/:account/turnstile" target="_blank">Cloudflare Turnstile</a></p>
    <div class="ln-grid">
        <div class="ln-field"><label>Bật Turnstile</label><select name="turnstile_enabled"><option value="0" <?php selected(_lno('turnstile_enabled',0),0); ?>>Tắt</option><option value="1" <?php selected(_lno('turnstile_enabled',0),1); ?>>Bật</option></select></div>
        <div class="ln-field"><label>Site Key</label><input type="text" name="turnstile_site_key" value="<?php echo esc_attr(_lno('turnstile_site_key','')); ?>" placeholder="0x..."></div>
        <div class="ln-field"><label>Secret Key</label><input type="password" name="turnstile_secret_key" value="<?php echo esc_attr(_lno('turnstile_secret_key','')); ?>" placeholder="0x..."></div>
        <div class="ln-field"><label>Captcha trong nút LẤY MÃ</label><select name="widget_captcha_enabled"><option value="1" <?php selected(_lno('widget_captcha_enabled',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('widget_captcha_enabled',1),0); ?>>Tắt</option></select><div style="font-size:11px;color:#787c82;margin-top:4px">Công tắc riêng cho widget trên web khách: user bấm nút lấy mã phải giải captcha Cloudflare rồi đồng hồ mới chạy. Áp dụng cho cả camp từ khoá lẫn camp direct. Cần điền Site Key + Secret Key ở trên. Nếu khung captcha không tải được (mạng chập, theme khách chặn), nút tự trả về sau 12 giây để user bấm lại chứ không kẹt. Ô "Bật Turnstile" phía trên chỉ áp dụng cho trang đăng nhập/đăng ký.</div></div>
        <div class="ln-field"><label>Captcha nút TIẾP TỤC (shortlink)</label><select name="unlock_captcha_enabled"><option value="0" <?php selected(_lno('unlock_captcha_enabled',0),0); ?>>Tắt</option><option value="1" <?php selected(_lno('unlock_captcha_enabled',0),1); ?>>Bật</option></select><div style="font-size:11px;color:#787c82;margin-top:4px">Bắt user giải captcha ngay khi bấm TIẾP TỤC ở trang nhiệm vụ, trước khi mã được gửi đi. Cần Site Key + Secret Key ở trên. <b>Lưu ý:</b> user chặn Cloudflare (adblock/DNS lọc) sẽ không nộp được mã — bật rồi nên theo dõi tỉ lệ hoàn thành.</div></div>
    </div>
</div>

<div class="ln-section">
    <h2>Widget & Hệ thống</h2>
    <div class="ln-grid">
        <div class="ln-field"><label>Countdown mặc định</label><input type="number" name="widget_default_countdown" value="<?php echo _lno('widget_default_countdown',30); ?>" min="10" max="300"><div class="unit">giây</div></div>
        <div class="ln-field"><label>Giữ visits cũ</label><input type="number" name="cleanup_old_visits" value="<?php echo _lno('cleanup_old_visits',30); ?>" min="7" max="365"><div class="unit">ngày</div></div>
        <div class="ln-field"><label>Xóa user inactive</label><input type="number" name="inactive_user_days" value="<?php echo _lno('inactive_user_days',10); ?>" min="5" max="365"><div class="unit">ngày sau ĐK</div></div>
    </div>
    <h3 style="margin-top:16px;font-size:14px;color:#555">Video hướng dẫn (Page Unlock)</h3>
    <div class="ln-grid">
        <div class="ln-field" style="grid-column:1/-1"><label>URL video hướng dẫn</label><input type="text" name="unlock_tutorial_video" value="<?php echo esc_attr(_lno('unlock_tutorial_video','')); ?>" placeholder="https://www.youtube.com/embed/VIDEO_ID hoặc URL video trực tiếp"><div class="unit">Để trống = ẩn video. Hỗ trợ YouTube embed, Google Drive, hoặc link .mp4</div></div>
    </div>
    <h3 style="margin-top:16px;font-size:14px;color:#555">Tuỳ chỉnh nút LẤY MÃ</h3>
    <p style="font-size:12px;color:#787c82;margin:0 0 10px;line-height:1.6">
        Nút được gắn <b>trong footer trang đích</b> (nằm trong luồng trang, không dính màn hình) — user phải cuộn xuống cuối trang mới thấy, đúng bước 1 của kịch bản hành vi.
        Nút là <b>vòng tròn 46px trên desktop, 40px trên mobile</b>: có Icon URL thì logo phủ kín mặt nút và ẩn chữ; bỏ trống thì hiện icon khoá mặc định + chữ bên dưới.
    </p>
    <div class="ln-grid">
        <div class="ln-field"><label>Text nút</label><input type="text" name="widget_button_text" value="<?php echo esc_attr(get_option('sitetop_widget_button_text','LẤY MÃ')); ?>" placeholder="LẤY MÃ"><div class="unit">Chỉ hiện khi bỏ trống Icon URL — nút tròn nhỏ, nên ≤ 6 ký tự</div></div>
        <div class="ln-field"><label>Màu nền</label><input type="color" name="widget_color" value="<?php echo esc_attr(get_option('sitetop_widget_color','#1E5EFF')); ?>" style="height:36px;padding:2px"><div class="unit">Nền cả 3 trạng thái nút</div></div>
        <div class="ln-field"><label>Màu chữ</label><input type="color" name="widget_text_color" value="<?php echo esc_attr(get_option('sitetop_widget_text_color','#ffffff')); ?>" style="height:36px;padding:2px"><div class="unit">Chữ + icon khoá và mã (số đếm ngược luôn trắng)</div></div>
        <div class="ln-field"><label>Icon URL</label><input type="text" name="widget_icon" value="<?php echo esc_attr(get_option('sitetop_widget_icon','')); ?>" placeholder="https://... (để trống = icon khoá mặc định)"><div class="unit">Ảnh vuông, nên ≥ 96×96 — logo phủ kín mặt nút tròn</div></div>
    </div>
    <div class="wbtn-prev">
        <label>Xem trước — nút nằm trong footer trang đích</label>
        <div class="wbtn-page">
            <div class="wbtn-body"><div class="wbtn-ln"></div><div class="wbtn-ln m"></div><div class="wbtn-ln s"></div></div>
            <div class="wbtn-foot">
                <div id="widget-preview-btn">
                    <span id="widget-preview-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="14" rx="2"/><path d="M12 8V5a3 3 0 0 0-3-3h0a3 3 0 0 0-3 3v0"/><path d="M18 8V5a3 3 0 0 0-3-3h0a3 3 0 0 0-3 3v0"/><line x1="12" y1="8" x2="12" y2="22"/></svg></span>
                    <span id="widget-preview-text"></span>
                </div>
                <div class="wbtn-cp">© Footer trang đích</div>
            </div>
        </div>
        <div class="wbtn-states">
            <div class="wbtn-st"><div class="wbtn-cd" id="widget-preview-cd">12</div><span>Đang đếm ngược</span></div>
            <div class="wbtn-st"><div class="wbtn-pill" id="widget-preview-pill"><span>A1B2C3</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></div><span>Hiện mã — bấm để copy</span></div>
        </div>
    </div>
    <script>
    (function(){
        var btn=document.getElementById('widget-preview-btn'),txt=document.getElementById('widget-preview-text'),ico=document.getElementById('widget-preview-icon');
        var cd=document.getElementById('widget-preview-cd'),pill=document.getElementById('widget-preview-pill');
        var iText=document.querySelector('input[name="widget_button_text"]'),iColor=document.querySelector('input[name="widget_color"]'),iTxtColor=document.querySelector('input[name="widget_text_color"]'),iIcon=document.querySelector('input[name="widget_icon"]');
        var defSvg=ico.innerHTML;
        function upd(){
            var bg=iColor.value,fg=iTxtColor.value,icon=(iIcon.value||'').trim();
            btn.style.background=bg;btn.style.color=fg;
            cd.style.background=bg;
            pill.style.background=bg;pill.style.color=fg;
            // Giống widget.js.php: có icon → class tn-logo, logo phủ kín nút và chữ để RỖNG.
            if(icon){
                btn.classList.add('tn-logo');
                ico.textContent='';
                var im=document.createElement('img');im.src=icon;im.alt='';ico.appendChild(im);
                txt.textContent='';
            }else{
                btn.classList.remove('tn-logo');
                ico.innerHTML=defSvg;
                txt.textContent=iText.value||'LẤY MÃ';
            }
        }
        upd();
        [iText,iColor,iTxtColor,iIcon].forEach(function(el){el.addEventListener('input',upd);});
    })();
    </script>
</div>

<div class="ln-section">
    <h2>DDoS Protection</h2>
    <div class="ln-grid">
        <div class="ln-field"><label>Global rate</label><input type="number" name="ddos_global_rate" value="<?php echo _lno('ddos_global_rate',10); ?>" min="1"><div class="unit">req/giây/IP</div></div>
        <div class="ln-field"><label>Burst limit</label><input type="number" name="ddos_burst_limit" value="<?php echo _lno('ddos_burst_limit',30); ?>" min="1"><div class="unit">req/10 giây/IP</div></div>
        <div class="ln-field"><label>Sustained limit</label><input type="number" name="ddos_sustained_limit" value="<?php echo _lno('ddos_sustained_limit',300); ?>" min="1"><div class="unit">req/60 giây/IP</div></div>
        <div class="ln-field"><label>Violation threshold</label><input type="number" name="ddos_violation_threshold" value="<?php echo _lno('ddos_violation_threshold',5); ?>" min="1"><div class="unit">lần trước khi block</div></div>
        <div class="ln-field"><label>Block duration</label><input type="number" name="ddos_block_duration" value="<?php echo _lno('ddos_block_duration',300); ?>" min="60" step="60"><div class="unit">giây (lần đầu)</div></div>
        <div class="ln-field"><label>Whitelist IP</label><textarea name="ddos_whitelist" rows="3" style="width:100%;font-size:12px;border:1px solid #c3c4c7;border-radius:4px;padding:6px 10px"><?php echo esc_textarea(_lno('ddos_whitelist','')); ?></textarea><div class="unit">1 IP/dòng</div></div>
        <div class="ln-field"><label>Quản lý IP</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button type="button" class="btn-sm btn-primary" onclick="ddosWhitelistMyIp()">Whitelist IP hiện tại</button>
                <button type="button" class="btn-sm" onclick="ddosResetBlocks()">Reset tất cả IP block</button>
                <span id="ddos-ip-result" style="font-size:12px;line-height:28px;color:#46b450"></span>
            </div>
        </div>
    </div>
</div>
<script>
function ddosWhitelistMyIp(){
    var r=document.getElementById('ddos-ip-result');
    r.textContent='Đang xử lý...';r.style.color='#999';
    var fd=new FormData();fd.append('action','sitetop_ddos_whitelist_my_ip');fd.append('nonce','<?php echo wp_create_nonce("sitetop_admin_nonce"); ?>');
    fetch(ajaxurl,{method:'POST',body:fd}).then(function(x){return x.json()}).then(function(d){
        if(d.success){r.textContent=d.data.message;r.style.color='#46b450';
            var ta=document.querySelector('textarea[name="ddos_whitelist"]');
            if(ta&&d.data.ip&&ta.value.indexOf(d.data.ip)===-1){ta.value=ta.value?(ta.value+'\n'+d.data.ip):d.data.ip;}
        }else{r.textContent='Lỗi';r.style.color='#dc3232';}
    });
}
function ddosResetBlocks(){
    if(!confirm('Xóa tất cả IP bị block tạm thời?'))return;
    var r=document.getElementById('ddos-ip-result');
    r.textContent='Đang xử lý...';r.style.color='#999';
    var fd=new FormData();fd.append('action','sitetop_ddos_reset_all');fd.append('nonce','<?php echo wp_create_nonce("sitetop_admin_nonce"); ?>');
    fetch(ajaxurl,{method:'POST',body:fd}).then(function(x){return x.json()}).then(function(d){
        if(d.success){r.textContent=d.data.message;r.style.color='#46b450';}
        else{r.textContent='Lỗi';r.style.color='#dc3232';}
    });
}
</script>

<div class="ln-section">
    <h2>🛡️ Anti-DDoS 4 lớp <span style="font-weight:400;font-size:13px;color:#646970">— Burst: khoá vĩnh viễn. Hourly/Daily/Range: khoá tạm, tự hết hạn</span></h2>
    <p style="margin:0 0 12px;font-size:12px;color:#646970">Block VĨNH VIỄN khi vượt threshold. Layer 4 bắt botnet xoay IP cùng dải /24 (IPv4) hoặc /48 (IPv6). Skip logged-in users + admins.</p>
    <div class="ln-grid g2">
        <div class="ln-field"><label>Layer 1 — Burst threshold</label>
            <input type="number" name="ddos_burst_perm_threshold" value="<?php echo _lno('ddos_burst_perm_threshold',60); ?>" min="0">
            <div class="unit">hits/IP trong window. Vượt → PERMANENT block</div></div>
        <div class="ln-field"><label>Layer 1 — Burst window (giây)</label>
            <input type="number" name="ddos_burst_perm_window" value="<?php echo _lno('ddos_burst_perm_window',60); ?>" min="10">
            <div class="unit">Sliding window đo burst</div></div>
        <div class="ln-field"><label>Layer 2 — Hourly limit/IP</label>
            <input type="number" name="ddos_hourly_limit" value="<?php echo _lno('ddos_hourly_limit',500); ?>" min="0">
            <div class="unit">hits/giờ/IP. IPv6 group theo /64 prefix</div></div>
        <div class="ln-field"><label>Layer 3 — Daily limit/IP</label>
            <input type="number" name="ddos_daily_limit" value="<?php echo _lno('ddos_daily_limit',2000); ?>" min="0">
            <div class="unit">hits/ngày/IP</div></div>
        <div class="ln-field"><label>Layer 4 — Range hourly /24-/48</label>
            <input type="number" name="ddos_range_hourly_limit" value="<?php echo _lno('ddos_range_hourly_limit',1000); ?>" min="0">
            <div class="unit">hits/giờ cộng dồn cả dải. Bắt botnet xoay IP</div></div>
        <div class="ln-field ddos-toggles"><label>Bật/Tắt layers</label>
            <div class="ddos-toggle-list">
                <label class="ddos-toggle"><input type="hidden" name="ddos_burst_enabled" value="0"><input type="checkbox" name="ddos_burst_enabled" value="1" <?php checked(_lno('ddos_burst_enabled',1),1); ?>><span>Layer 1 Burst</span></label>
                <label class="ddos-toggle"><input type="hidden" name="ddos_hourly_enabled" value="0"><input type="checkbox" name="ddos_hourly_enabled" value="1" <?php checked(_lno('ddos_hourly_enabled',1),1); ?>><span>Layer 2 Hourly</span></label>
                <label class="ddos-toggle"><input type="hidden" name="ddos_daily_enabled" value="0"><input type="checkbox" name="ddos_daily_enabled" value="1" <?php checked(_lno('ddos_daily_enabled',1),1); ?>><span>Layer 3 Daily</span></label>
                <label class="ddos-toggle"><input type="hidden" name="ddos_range_hourly_enabled" value="0"><input type="checkbox" name="ddos_range_hourly_enabled" value="1" <?php checked(_lno('ddos_range_hourly_enabled',1),1); ?>><span>Layer 4 Range</span></label>
            </div>
        </div>
    </div>
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid #e5e7eb">
        <h3 style="font-size:13px;margin:0 0 8px">Quản lý Permanent Blocks</h3>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <input type="text" id="ddosPermIp" placeholder="vd: 1.2.3.4 hoặc 2402:800::/48 hoặc 1.2.3.0/24" style="flex:1;min-width:280px;padding:6px 10px;border:1px solid #c3c4c7;border-radius:4px;font-size:13px;font-family:monospace">
            <button type="button" class="btn-sm" style="background:#dc3232;color:#fff;border:none" onclick="ddosPermAdd()">Block vĩnh viễn</button>
            <button type="button" class="btn-sm btn-primary" onclick="ddosLoadPermList()">Xem danh sách</button>
        </div>
        <div id="ddosPermList" style="margin-top:12px"></div>
    </div>
</div>
<script>
function ddosLoadPermList(){
    var box=document.getElementById('ddosPermList');
    box.innerHTML='<em style="color:#646970">Đang tải...</em>';
    var fd=new FormData();fd.append('action','sitetop_ddos_permanent_list');fd.append('nonce','<?php echo wp_create_nonce("sitetop_admin_nonce"); ?>');
    fetch(ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(x){return x.json()}).then(function(d){
        if(!d.success){box.innerHTML='<span style="color:#dc3232">Lỗi: '+(d.data||'unknown')+'</span>';return;}
        var b=(d.data&&d.data.blocks)||[];
        if(!b.length){box.innerHTML='<em style="color:#646970">Chưa có IP nào permanent block</em>';return;}
        var esc=function(s){return String(s||'').replace(/[<>&"\x27]/g,function(c){return '&#'+c.charCodeAt(0)+';';});};
        var h='<table style="width:100%;font-size:12px;border-collapse:collapse"><thead><tr style="background:#f3f4f6"><th style="text-align:left;padding:6px 8px">IP/Prefix</th><th style="text-align:left;padding:6px 8px">Lý do</th><th style="text-align:right;padding:6px 8px">Count</th><th style="text-align:left;padding:6px 8px">Thời gian</th><th style="padding:6px 8px"></th></tr></thead><tbody>';
        for(var i=0;i<b.length;i++){
            var x=b[i];
            var ip=esc(x.ip_address);
            h+='<tr style="border-bottom:1px solid #f3f4f6"><td style="padding:5px 8px;font-family:monospace">'+ip+'</td><td style="padding:5px 8px">'+esc(x.violation_types)+'</td><td style="padding:5px 8px;text-align:right">'+(x.violation_count||0)+'</td><td style="padding:5px 8px;font-size:11px;color:#646970">'+esc(x.updated_at||x.created_at)+'</td><td style="padding:5px 8px"><button type="button" class="btn-sm" onclick="ddosPermUnblock(this,\''+ip.replace(/\x27/g,"\\x27")+'\')">Unblock</button></td></tr>';
        }
        h+='</tbody></table>';
        box.innerHTML=h;
    });
}
function ddosPermAdd(){
    var ip=document.getElementById('ddosPermIp').value.trim();
    if(!ip)return;
    if(!confirm('Permanent block "'+ip+'"?\n\nIP/prefix này sẽ bị block vĩnh viễn cho đến khi admin unblock.'))return;
    var fd=new FormData();fd.append('action','sitetop_ddos_permanent_add');fd.append('nonce','<?php echo wp_create_nonce("sitetop_admin_nonce"); ?>');fd.append('ip',ip);
    fetch(ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(x){return x.json()}).then(function(d){
        if(d.success){document.getElementById('ddosPermIp').value='';ddosLoadPermList();}
        else alert(d.data||'Lỗi');
    });
}
function ddosPermUnblock(btn,ip){
    if(!confirm('Unblock "'+ip+'"?'))return;
    btn.disabled=true;
    var fd=new FormData();fd.append('action','sitetop_ddos_unblock_ip');fd.append('nonce','<?php echo wp_create_nonce("sitetop_admin_nonce"); ?>');fd.append('ip',ip);
    fetch(ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(x){return x.json()}).then(function(d){
        if(d.success)ddosLoadPermList(); else{alert(d.data||'Lỗi');btn.disabled=false;}
    });
}
</script>

<div class="ln-section">
    <h2>SMTP Email</h2>
    <div class="ln-grid g2">
        <div class="ln-field"><label>Bật SMTP</label><select name="smtp_enabled"><option value="0" <?php selected(_lno('smtp_enabled',0),0); ?>>Tắt (dùng PHP mail)</option><option value="1" <?php selected(_lno('smtp_enabled',0),1); ?>>Bật</option></select></div>
        <div class="ln-field"><label>Host</label><input type="text" name="smtp_host" value="<?php echo esc_attr(_lno('smtp_host','')); ?>" placeholder="smtp.gmail.com"></div>
        <div class="ln-field"><label>Port</label><input type="number" name="smtp_port" value="<?php echo _lno('smtp_port',587); ?>"></div>
        <div class="ln-field"><label>Encryption</label><select name="smtp_encryption"><option value="tls" <?php selected(_lno('smtp_encryption','tls'),'tls'); ?>>TLS</option><option value="ssl" <?php selected(_lno('smtp_encryption','tls'),'ssl'); ?>>SSL</option></select></div>
        <div class="ln-field"><label>Username</label><input type="text" name="smtp_username" value="<?php echo esc_attr(_lno('smtp_username','')); ?>"></div>
        <div class="ln-field"><label>Password</label><input type="password" name="smtp_password" value="<?php echo esc_attr(_lno('smtp_password','')); ?>"></div>
        <div class="ln-field"><label>From Email</label><input type="email" name="smtp_from_email" value="<?php echo esc_attr(_lno('smtp_from_email','')); ?>"></div>
        <div class="ln-field"><label>From Name</label><input type="text" name="smtp_from_name" value="<?php echo esc_attr(_lno('smtp_from_name','')); ?>"></div>
    </div>
    <div style="margin-top:12px">
        <input type="email" id="testSmtpEmail" placeholder="Email test" style="padding:6px 10px;border:1px solid #c3c4c7;border-radius:4px;font-size:13px;width:250px">
        <button type="button" class="button" onclick="testSmtp()">Test SMTP</button>
        <button type="button" class="button button-primary" onclick="testSystemEmail()">Test email hệ thống</button>
        <span id="smtpResult" style="font-size:12px;margin-left:8px"></span>
    </div>
    <p style="font-size:12px;color:#787c82;margin-top:8px;line-height:1.6">
        <b>Test SMTP</b>: chỉ kiểm tra kết nối tới máy chủ SMTP (tự cấu hình riêng khi test).<br>
        <b>Test email hệ thống</b>: gửi đúng theo đường code của email xác thực tài khoản — nếu nút này chạy được thì email đăng ký/rút tiền cũng chạy được.
    </p>
</div>

<div class="ln-section">
    <h2>Email thông báo</h2>
    <p style="font-size:12px;color:#787c82;margin-bottom:14px">Bật/tắt gửi email thông báo cho từng sự kiện. Cần cấu hình SMTP ở trên để gửi email.</p>

    <h3 style="margin:0 0 10px;font-size:13px;font-weight:700;color:#1d2327">Rút tiền (Withdrawal)</h3>
    <div class="ln-grid">
        <div class="ln-field"><label>Yêu cầu rút tiền mới</label><select name="email_withdrawal_pending"><option value="1" <?php selected(_lno('email_withdrawal_pending',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('email_withdrawal_pending',1),0); ?>>Tắt</option></select><div class="unit">Gửi cho Admin khi user yêu cầu rút tiền</div></div>
        <div class="ln-field"><label>Rút tiền được duyệt</label><select name="email_withdrawal_approved"><option value="1" <?php selected(_lno('email_withdrawal_approved',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('email_withdrawal_approved',1),0); ?>>Tắt</option></select><div class="unit">Gửi cho User khi được duyệt</div></div>
        <div class="ln-field"><label>Rút tiền bị từ chối</label><select name="email_withdrawal_rejected"><option value="1" <?php selected(_lno('email_withdrawal_rejected',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('email_withdrawal_rejected',1),0); ?>>Tắt</option></select><div class="unit">Gửi cho User khi bị từ chối</div></div>
        <div class="ln-field"><label>Rút tiền hoàn thành</label><select name="email_withdrawal_completed"><option value="1" <?php selected(_lno('email_withdrawal_completed',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('email_withdrawal_completed',1),0); ?>>Tắt</option></select><div class="unit">Gửi cho User khi đã chuyển tiền</div></div>
    </div>

    <h3 style="margin:20px 0 10px;font-size:13px;font-weight:700;color:#1d2327">Nạp tiền (Deposit)</h3>
    <div class="ln-grid">
        <div class="ln-field"><label>Yêu cầu nạp tiền mới</label><select name="email_deposit_pending"><option value="1" <?php selected(_lno('email_deposit_pending',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('email_deposit_pending',1),0); ?>>Tắt</option></select><div class="unit">Gửi cho Admin khi KH nạp tiền</div></div>
        <div class="ln-field"><label>Nạp tiền được duyệt</label><select name="email_deposit_approved"><option value="1" <?php selected(_lno('email_deposit_approved',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('email_deposit_approved',1),0); ?>>Tắt</option></select><div class="unit">Gửi cho KH khi được duyệt</div></div>
        <div class="ln-field"><label>Nạp tiền bị từ chối</label><select name="email_deposit_rejected"><option value="1" <?php selected(_lno('email_deposit_rejected',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('email_deposit_rejected',1),0); ?>>Tắt</option></select><div class="unit">Gửi cho KH khi bị từ chối</div></div>
    </div>

    <h3 style="margin:20px 0 10px;font-size:13px;font-weight:700;color:#1d2327">Báo lỗi mã (Report)</h3>
    <div class="ln-grid">
        <div class="ln-field"><label>User báo lỗi mã</label><select name="email_report_error"><option value="1" <?php selected(_lno('email_report_error',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('email_report_error',1),0); ?>>Tắt</option></select><div class="unit">Gửi cho Admin khi user báo lỗi mã xác minh</div></div>
    </div>

    <h3 style="margin:20px 0 10px;font-size:13px;font-weight:700;color:#1d2327">Chiến dịch (Campaign)</h3>
    <div class="ln-grid">
        <div class="ln-field"><label>Chiến dịch mới</label><select name="email_campaign_new"><option value="1" <?php selected(_lno('email_campaign_new',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('email_campaign_new',1),0); ?>>Tắt</option></select><div class="unit">Gửi cho Admin khi KH tạo chiến dịch mới</div></div>
    </div>
</div>

<div class="ln-section">
    <h2>Thông báo Telegram (Admin)</h2>
    <p style="font-size:12px;color:#787c82;margin-bottom:14px">Khi cấu hình bot (cả Token + Chat ID), các thông báo dành cho <strong>Admin</strong> (báo lỗi mã, chiến dịch mới, nạp tiền, rút tiền) sẽ gửi về Telegram <strong>thay cho email</strong>. Để trống = vẫn dùng email như cũ. Thông báo cho khách (xác nhận đăng ký, duyệt...) luôn đi qua email.</p>
    <div class="ln-grid g2">
        <div class="ln-field"><label>Bot Token</label><input type="text" name="report_telegram_bot_token" id="tg_bot_token" value="<?php echo esc_attr(_lno('report_telegram_bot_token','')); ?>" placeholder="123456789:ABCdef..."><div class="unit">Lấy từ @BotFather → /newbot</div></div>
        <div class="ln-field"><label>Chat ID</label><input type="text" name="report_telegram_chat_id" id="tg_chat_id" value="<?php echo esc_attr(_lno('report_telegram_chat_id','')); ?>" placeholder="VD: 123456789 hoặc -100..."><div class="unit">Lấy từ @userinfobot (cá nhân) hoặc -100... (group/channel)</div></div>
    </div>
    <div style="display:flex;gap:8px;margin-top:6px;align-items:center;flex-wrap:wrap">
        <button type="button" onclick="testTelegram()" style="padding:4px 12px;font-size:12px;border:1px solid #2271b1;background:#2271b1;color:#fff;border-radius:4px;cursor:pointer">Test gửi</button>
        <a href="https://t.me/BotFather" target="_blank" rel="noreferrer" style="padding:4px 12px;font-size:12px;border:1px solid #ddd;background:#f6f7f7;color:#2271b1;border-radius:4px;text-decoration:none;display:inline-block">Mở @BotFather</a>
        <span id="tg_test_result" style="font-size:12px;line-height:28px"></span>
    </div>
    <div style="font-size:11px;color:#787c82;margin-top:10px;line-height:1.7">
        <strong>Hướng dẫn:</strong> @BotFather → <code>/newbot</code> → lấy <em>Token</em> → bấm <code>/start</code> bot của bạn → lấy <em>Chat ID</em> (@userinfobot cho cá nhân; group/channel dùng <code>-100...</code> và phải thêm bot vào, channel cần bot làm admin) → dán vào trên + <em>Test gửi</em> → Lưu.<br>
        <strong>Lưu ý:</strong> Chat ID <u>khác</u> ID của bot (số trước dấu <code>:</code> trong token) — dán nhầm sẽ báo <em>"bot can't send messages to the bot"</em>.
    </div>
</div>

<div class="ln-section">
    <h2>Integrations</h2>
    <div class="ln-grid g2">
        <div class="ln-field"><label>ImgBB API Key <span style="font-weight:400;color:#787c82">(dự phòng)</span></label><div style="font-size:12px;color:#787c82;margin-bottom:6px">Ảnh giờ lưu thẳng trên máy chủ sitetop.net. Key này chỉ dùng khi máy chủ không ghi được file (hết dung lượng, sai quyền thư mục) — để trống cũng được.</div><input type="text" name="imgbb_api_key" id="imgbb_api_key" value="<?php echo esc_attr(_lno('imgbb_api_key','')); ?>" placeholder="Để trống = chỉ lưu trên máy chủ site"><div style="display:flex;gap:8px;margin-top:6px"><button type="button" onclick="testImgbb()" style="padding:4px 12px;font-size:12px;border:1px solid #2271b1;background:#2271b1;color:#fff;border-radius:4px;cursor:pointer">Test</button><a href="https://api.imgbb.com/" target="_blank" rel="noreferrer" style="padding:4px 12px;font-size:12px;border:1px solid #ddd;background:#f6f7f7;color:#2271b1;border-radius:4px;text-decoration:none;display:inline-block">Lấy API Key</a><span id="imgbb_test_result" style="font-size:12px;line-height:28px"></span></div></div>
        <div class="ln-field"><label>Liên hệ Telegram</label><input type="text" name="contact_telegram" value="<?php echo esc_attr(_lno('contact_telegram','')); ?>" placeholder="@username"></div>
        <div class="ln-field"><label>Liên hệ Signal</label><input type="text" name="contact_signal" value="<?php echo esc_attr(_lno('contact_signal','')); ?>" placeholder="Dán link từ Signal app"></div>
        <div class="ln-field"><label>Liên hệ Zalo</label><input type="text" name="contact_zalo" value="<?php echo esc_attr(_lno('contact_zalo','')); ?>" placeholder="Số Zalo"></div>
        <div class="ln-field"><label>Liên hệ Email</label><input type="email" name="contact_email" value="<?php echo esc_attr(_lno('contact_email','')); ?>"></div>
    </div>
</div>

<div class="ln-section">
    <h2>Database Tools</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button type="button" class="button" onclick="runAction('sitetop_admin_recreate_db','Tạo lại bảng...')">Tạo lại bảng DB</button>
        <button type="button" class="button" onclick="runAction('sitetop_admin_run_tests','Đang chạy tests...')">Chạy Unit Tests</button>
        <button type="button" class="button" onclick="if(confirm('Xóa toàn bộ file cache rate limit + DDoS?\nAn toàn — cache sẽ tự build lại.'))runAction('sitetop_admin_purge_cache','Đang xóa cache...')">Xóa cache file (ratelimit + DDoS)</button>
    </div>
    <pre id="toolOutput" style="margin-top:12px;background:#f8f8f8;border:1px solid #ddd;border-radius:4px;padding:10px;font-size:12px;max-height:300px;overflow:auto;display:none"></pre>
</div>

<p class="submit"><input type="submit" name="sitetop_save_settings" class="button-primary button-hero" value="Lưu cài đặt"></p>
</form>
</div>
<script>
function testImgbb(){
    var key=document.getElementById('imgbb_api_key').value.trim();
    var r=document.getElementById('imgbb_test_result');
    if(!key){r.textContent='Nhập API key trước';r.style.color='#dc3232';return;}
    r.textContent='Đang test...';r.style.color='#666';
    var fd=new FormData();fd.append('action','sitetop_test_imgbb');fd.append('nonce','<?php echo wp_create_nonce("sitetop_admin_nonce"); ?>');fd.append('api_key',key);
    fetch('<?php echo admin_url("admin-ajax.php"); ?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(x){return x.json()}).then(function(x){
        r.textContent=x.success?'OK — API key hợp lệ':'Lỗi: '+(x.data||'Không kết nối được');r.style.color=x.success?'#46b450':'#dc3232';
    }).catch(function(){r.textContent='Lỗi kết nối';r.style.color='#dc3232';});
}
function testTelegram(){
    var token=document.getElementById('tg_bot_token').value.trim();
    var chat=document.getElementById('tg_chat_id').value.trim();
    var r=document.getElementById('tg_test_result');
    if(!token||!chat){r.textContent='Nhập đủ Bot Token và Chat ID';r.style.color='#dc3232';return;}
    r.textContent='Đang gửi...';r.style.color='#666';
    var fd=new FormData();fd.append('action','sitetop_test_telegram');fd.append('nonce','<?php echo wp_create_nonce("sitetop_admin_nonce"); ?>');fd.append('token',token);fd.append('chat_id',chat);
    fetch('<?php echo admin_url("admin-ajax.php"); ?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(x){return x.json()}).then(function(x){
        r.textContent=x.success?('✓ '+x.data):('✗ '+(x.data||'Lỗi'));r.style.color=x.success?'#46b450':'#dc3232';
    }).catch(function(){r.textContent='Lỗi kết nối';r.style.color='#dc3232';});
}
function testSmtp(){
    var email=document.getElementById('testSmtpEmail').value;
    if(!email){alert('Nhập email test');return;}
    var r=document.getElementById('smtpResult');r.textContent='Đang gửi...';r.style.color='#666';
    var fd=new FormData();fd.append('action','sitetop_test_smtp');fd.append('nonce','<?php echo wp_create_nonce("sitetop_admin_nonce"); ?>');fd.append('test_email',email);
    fetch('<?php echo admin_url("admin-ajax.php"); ?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(x){return x.json()}).then(function(x){
        r.textContent=x.success?'✓ '+x.data:'✗ '+(x.data||'Lỗi');r.style.color=x.success?'#46b450':'#dc3232';
    }).catch(function(){r.textContent='Lỗi kết nối';r.style.color='#dc3232';});
}
// Test đi đúng đường code của email xác thực tài khoản (không tự cấu hình SMTP)
function testSystemEmail(){
    var email=document.getElementById('testSmtpEmail').value;
    if(!email){alert('Nhập email test');return;}
    var r=document.getElementById('smtpResult');r.textContent='Đang gửi...';r.style.color='#666';
    var fd=new FormData();fd.append('action','sitetop_test_system_email');fd.append('nonce','<?php echo wp_create_nonce("sitetop_admin_nonce"); ?>');fd.append('test_email',email);
    fetch('<?php echo admin_url("admin-ajax.php"); ?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(x){return x.json()}).then(function(x){
        r.textContent=x.success?'✓ '+x.data:'✗ '+(x.data||'Lỗi');r.style.color=x.success?'#46b450':'#dc3232';
    }).catch(function(){r.textContent='Lỗi kết nối';r.style.color='#dc3232';});
}
function runAction(action,msg){
    var out=document.getElementById('toolOutput');out.style.display='block';out.textContent=msg;
    var fd=new FormData();fd.append('action',action);fd.append('nonce','<?php echo wp_create_nonce("sitetop_admin_nonce"); ?>');
    fetch('<?php echo admin_url("admin-ajax.php"); ?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(x){
        if(!x||typeof x.data==='undefined'){out.textContent='Lỗi: response không hợp lệ';return;}
        out.textContent=x.success?(typeof x.data==='object'&&x.data.output?x.data.output:(typeof x.data==='string'?x.data:'OK')):(x.data||'Lỗi');
    }).catch(function(e){out.textContent='Lỗi: '+e.message;});
}
</script>
