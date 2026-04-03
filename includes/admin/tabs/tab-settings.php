<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

if(isset($_POST['linkngon_save_settings']) && wp_verify_nonce($_POST['_wpnonce'],'linkngon_settings_save')){
    $fields = array(
        'min_withdrawal','min_deposit_amount','customer_min_balance',
        'keyword_price_1step','keyword_price_2step','keyword_price_nocode',
        'direct_price_1step','direct_price_2step','direct_price_nocode',
        'keyword_user_1step','keyword_user_2step','keyword_user_nocode',
        'direct_user_1step','direct_user_2step','direct_user_nocode',
        'shortlink_ip_limit_24h','verify_code_expiry','max_tasks_per_ip_per_day',
        'detect_vpn_proxy','block_proxy_ip','block_vpn_ip','block_datacenter_ip',
        'widget_default_countdown','cleanup_old_visits','inactive_user_days',
        'deposit_bank','deposit_account','deposit_holder',
        // DDoS
        'ddos_global_rate','ddos_burst_limit','ddos_sustained_limit',
        'ddos_violation_threshold','ddos_block_duration',
        // SMTP
        'smtp_enabled','smtp_host','smtp_port','smtp_encryption',
        'smtp_username','smtp_password','smtp_from_email','smtp_from_name',
        // Turnstile
        'turnstile_enabled','turnstile_site_key','turnstile_secret_key',
        // Referral
        'referral_enabled','referral_commission_percent','referral_min_payout','referral_duration_days',
        // Integrations
        'imgbb_api_key','contact_telegram','contact_zalo','contact_email',
    );
    foreach($fields as $f) if(isset($_POST[$f])) linkngon_update_option($f, sanitize_text_field($_POST[$f]));

    // DDoS whitelist (textarea)
    if(isset($_POST['ddos_whitelist'])) linkngon_update_option('ddos_whitelist', sanitize_textarea_field($_POST['ddos_whitelist']));

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
    linkngon_update_option('deposit_presets', json_encode($presets));

    echo '<div class="notice notice-success is-dismissible"><p>Đã lưu cài đặt!</p></div>';
}
function _lno($k,$d=''){return linkngon_get_option($k,$d);}
?>
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
</style>

<div class="wrap">
<h1>Cài đặt LinkNgon</h1>
<form method="post" class="ln-settings">
<?php wp_nonce_field('linkngon_settings_save'); ?>

<div class="ln-section">
    <h2>Giá khách hàng trả (đ/lượt)</h2>
    <div class="ln-grid">
        <div class="ln-field"><label>Keyword 1 bước</label><input type="number" name="keyword_price_1step" value="<?php echo _lno('keyword_price_1step',1200); ?>" step="100"></div>
        <div class="ln-field"><label>Keyword 2 bước</label><input type="number" name="keyword_price_2step" value="<?php echo _lno('keyword_price_2step',1500); ?>" step="100"></div>
        <div class="ln-field"><label>Keyword Không mã</label><input type="number" name="keyword_price_nocode" value="<?php echo _lno('keyword_price_nocode',1200); ?>" step="100"></div>
        <div class="ln-field"><label>Direct 1 bước</label><input type="number" name="direct_price_1step" value="<?php echo _lno('direct_price_1step',1200); ?>" step="100"></div>
        <div class="ln-field"><label>Direct 2 bước</label><input type="number" name="direct_price_2step" value="<?php echo _lno('direct_price_2step',1200); ?>" step="100"></div>
        <div class="ln-field"><label>Direct Không mã</label><input type="number" name="direct_price_nocode" value="<?php echo _lno('direct_price_nocode',1200); ?>" step="100"></div>
    </div>
</div>

<div class="ln-section">
    <h2>User nhận (đ/lượt)</h2>
    <div class="ln-grid">
        <div class="ln-field"><label>Keyword 1 bước</label><input type="number" name="keyword_user_1step" value="<?php echo _lno('keyword_user_1step',800); ?>" step="100"></div>
        <div class="ln-field"><label>Keyword 2 bước</label><input type="number" name="keyword_user_2step" value="<?php echo _lno('keyword_user_2step',1000); ?>" step="100"></div>
        <div class="ln-field"><label>Keyword Không mã</label><input type="number" name="keyword_user_nocode" value="<?php echo _lno('keyword_user_nocode',800); ?>" step="100"></div>
        <div class="ln-field"><label>Direct 1 bước</label><input type="number" name="direct_user_1step" value="<?php echo _lno('direct_user_1step',500); ?>" step="100"></div>
        <div class="ln-field"><label>Direct 2 bước</label><input type="number" name="direct_user_2step" value="<?php echo _lno('direct_user_2step',700); ?>" step="100"></div>
        <div class="ln-field"><label>Direct Không mã</label><input type="number" name="direct_user_nocode" value="<?php echo _lno('direct_user_nocode',800); ?>" step="100"></div>
    </div>
</div>

<div class="ln-section">
    <h2>Tài chính</h2>
    <div class="ln-grid">
        <div class="ln-field"><label>Rút tiền tối thiểu</label><input type="number" name="min_withdrawal" value="<?php echo _lno('min_withdrawal',50000); ?>" step="1000"><div class="unit">VNĐ</div></div>
        <div class="ln-field"><label>Nạp tiền tối thiểu</label><input type="number" name="min_deposit_amount" value="<?php echo _lno('min_deposit_amount',50000); ?>" step="1000"><div class="unit">VNĐ</div></div>
        <div class="ln-field"><label>Số dư tối thiểu KH</label><input type="number" name="customer_min_balance" value="<?php echo _lno('customer_min_balance',20000); ?>" step="1000"><div class="unit">VNĐ - để campaign hoạt động</div></div>
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
            <td><input type="number" name="preset_amount[]" value="<?php echo $p['amount']; ?>" step="100000" style="width:100%"></td>
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
        tr.innerHTML='<td><input type="number" name="preset_amount[]" value="" step="100000" style="width:100%" placeholder="VD: 5000000"></td><td><input type="number" name="preset_bonus[]" value="0" min="0" max="100" style="width:100%"></td><td><button type="button" class="button button-small" onclick="this.closest(\'tr\').remove()" style="color:#dc3232">Xóa</button></td>';
        tbody.appendChild(tr);
    }
    </script>
</div>

<div class="ln-section">
    <h2>Thông tin chuyển khoản</h2>
    <div class="ln-grid">
        <div class="ln-field"><label>Ngân hàng</label><input type="text" name="deposit_bank" value="<?php echo esc_attr(_lno('deposit_bank','Vietcombank')); ?>"></div>
        <div class="ln-field"><label>Số tài khoản</label><input type="text" name="deposit_account" value="<?php echo esc_attr(_lno('deposit_account','')); ?>"></div>
        <div class="ln-field"><label>Chủ tài khoản</label><input type="text" name="deposit_holder" value="<?php echo esc_attr(_lno('deposit_holder','')); ?>"></div>
    </div>
</div>

<div class="ln-section">
    <h2>Bảo mật & IP</h2>
    <div class="ln-grid">
        <div class="ln-field"><label>IP limit/ngày</label><input type="number" name="shortlink_ip_limit_24h" value="<?php echo _lno('shortlink_ip_limit_24h',5); ?>" min="1" max="100"><div class="unit">Lượt verified/IP/ngày</div></div>
        <div class="ln-field"><label>Tasks/IP/ngày</label><input type="number" name="max_tasks_per_ip_per_day" value="<?php echo _lno('max_tasks_per_ip_per_day',10); ?>" min="1" max="100"></div>
        <div class="ln-field"><label>Code hết hạn</label><input type="number" name="verify_code_expiry" value="<?php echo _lno('verify_code_expiry',600); ?>" min="60" step="60"><div class="unit">giây (600 = 10 phút)</div></div>
        <div class="ln-field"><label>Detect VPN/Proxy</label><select name="detect_vpn_proxy"><option value="1" <?php selected(_lno('detect_vpn_proxy',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('detect_vpn_proxy',1),0); ?>>Tắt</option></select></div>
        <div class="ln-field"><label>Chặn Proxy</label><select name="block_proxy_ip"><option value="1" <?php selected(_lno('block_proxy_ip',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('block_proxy_ip',1),0); ?>>Tắt</option></select></div>
        <div class="ln-field"><label>Chặn VPN</label><select name="block_vpn_ip"><option value="1" <?php selected(_lno('block_vpn_ip',1),1); ?>>Bật</option><option value="0" <?php selected(_lno('block_vpn_ip',1),0); ?>>Tắt</option></select></div>
        <div class="ln-field"><label>Chặn Datacenter</label><select name="block_datacenter_ip"><option value="0" <?php selected(_lno('block_datacenter_ip',0),0); ?>>Tắt</option><option value="1" <?php selected(_lno('block_datacenter_ip',0),1); ?>>Bật</option></select></div>
    </div>
</div>

<div class="ln-section">
    <h2>Referral (Giới thiệu)</h2>
    <p style="font-size:12px;color:#787c82;margin-bottom:14px">Cài đặt hoa hồng khi người dùng giới thiệu bạn bè đăng ký và kiếm tiền.</p>
    <div class="ln-grid">
        <div class="ln-field"><label>Bật Referral</label><select name="referral_enabled"><option value="1" <?php selected(_lno('referral_enabled',0),1); ?>>Bật</option><option value="0" <?php selected(_lno('referral_enabled',0),0); ?>>Tắt</option></select></div>
        <div class="ln-field"><label>Hoa hồng %</label><input type="number" name="referral_commission_percent" value="<?php echo _lno('referral_commission_percent',20); ?>" min="0" max="100" step="1"><div class="unit">% thu nhập của người được giới thiệu</div></div>
        <div class="ln-field"><label>Rút tối thiểu referral</label><input type="number" name="referral_min_payout" value="<?php echo _lno('referral_min_payout',50000); ?>" step="1000"><div class="unit">VNĐ</div></div>
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
    </div>
</div>

<div class="ln-section">
    <h2>Widget & Hệ thống</h2>
    <div class="ln-grid">
        <div class="ln-field"><label>Countdown mặc định</label><input type="number" name="widget_default_countdown" value="<?php echo _lno('widget_default_countdown',30); ?>" min="10" max="300"><div class="unit">giây</div></div>
        <div class="ln-field"><label>Giữ visits cũ</label><input type="number" name="cleanup_old_visits" value="<?php echo _lno('cleanup_old_visits',30); ?>" min="7" max="365"><div class="unit">ngày</div></div>
        <div class="ln-field"><label>Xóa user inactive</label><input type="number" name="inactive_user_days" value="<?php echo _lno('inactive_user_days',10); ?>" min="5" max="365"><div class="unit">ngày sau ĐK</div></div>
    </div>
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
    </div>
</div>

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
        <span id="smtpResult" style="font-size:12px;margin-left:8px"></span>
    </div>
</div>

<div class="ln-section">
    <h2>Integrations</h2>
    <div class="ln-grid g2">
        <div class="ln-field"><label>ImgBB API Key</label><input type="text" name="imgbb_api_key" value="<?php echo esc_attr(_lno('imgbb_api_key','')); ?>" placeholder="Để trống = upload lên WordPress"><div class="unit">Dùng cho upload ảnh screenshot</div></div>
        <div class="ln-field"><label>Liên hệ Telegram</label><input type="text" name="contact_telegram" value="<?php echo esc_attr(_lno('contact_telegram','')); ?>" placeholder="@username"></div>
        <div class="ln-field"><label>Liên hệ Zalo</label><input type="text" name="contact_zalo" value="<?php echo esc_attr(_lno('contact_zalo','')); ?>" placeholder="Số Zalo"></div>
        <div class="ln-field"><label>Liên hệ Email</label><input type="email" name="contact_email" value="<?php echo esc_attr(_lno('contact_email','')); ?>"></div>
    </div>
</div>

<div class="ln-section">
    <h2>Database Tools</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button type="button" class="button" onclick="runAction('linkngon_admin_recreate_db','Tạo lại bảng...')">Tạo lại bảng DB</button>
        <button type="button" class="button" onclick="runAction('linkngon_admin_run_tests','Đang chạy tests...')">Chạy Unit Tests</button>
    </div>
    <pre id="toolOutput" style="margin-top:12px;background:#f8f8f8;border:1px solid #ddd;border-radius:4px;padding:10px;font-size:12px;max-height:300px;overflow:auto;display:none"></pre>
</div>

<p class="submit"><input type="submit" name="linkngon_save_settings" class="button-primary button-hero" value="Lưu cài đặt"></p>
</form>
</div>
<script>
function testSmtp(){
    var email=document.getElementById('testSmtpEmail').value;
    if(!email){alert('Nhập email test');return;}
    var r=document.getElementById('smtpResult');r.textContent='Đang gửi...';r.style.color='#666';
    var fd=new FormData();fd.append('action','linkngon_test_smtp');fd.append('nonce','<?php echo wp_create_nonce("linkngon_admin_nonce"); ?>');fd.append('test_email',email);
    fetch('<?php echo admin_url("admin-ajax.php"); ?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(x){return x.json()}).then(function(x){
        r.textContent=x.success?'✓ '+x.data:'✗ '+(x.data||'Lỗi');r.style.color=x.success?'#46b450':'#dc3232';
    }).catch(function(){r.textContent='Lỗi kết nối';r.style.color='#dc3232';});
}
function runAction(action,msg){
    var out=document.getElementById('toolOutput');out.style.display='block';out.textContent=msg;
    var fd=new FormData();fd.append('action',action);fd.append('nonce','<?php echo wp_create_nonce("linkngon_admin_nonce"); ?>');
    fetch('<?php echo admin_url("admin-ajax.php"); ?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(x){
        if(!x||typeof x.data==='undefined'){out.textContent='Lỗi: response không hợp lệ';return;}
        out.textContent=x.success?(typeof x.data==='object'&&x.data.output?x.data.output:(typeof x.data==='string'?x.data:'OK')):(x.data||'Lỗi');
    }).catch(function(e){out.textContent='Lỗi: '+e.message;});
}
</script>
