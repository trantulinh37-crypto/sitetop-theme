<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) return;
if (isset($_POST['linkngon_save']) && wp_verify_nonce($_POST['_wpnonce'],'linkngon_settings')) {
    foreach(array('min_withdrawal','keyword_user_1step','keyword_user_2step','keyword_user_nocode','direct_user_1step','detect_vpn_proxy','fraud_block_threshold','deposit_bank','deposit_account','deposit_holder') as $f)
        if(isset($_POST[$f])) linkngon_update_option($f, sanitize_text_field($_POST[$f]));
    echo '<div class="notice notice-success"><p>Saved!</p></div>';
}
?>
<div class="wrap"><h1>LinkNgon V2 Settings</h1>
<form method="post"><?php wp_nonce_field('linkngon_settings'); ?>
<table class="form-table">
<tr><th>KW 1step reward</th><td><input name="keyword_user_1step" type="number" value="<?php echo linkngon_get_option('keyword_user_1step',800); ?>"></td></tr>
<tr><th>KW 2step reward</th><td><input name="keyword_user_2step" type="number" value="<?php echo linkngon_get_option('keyword_user_2step',1000); ?>"></td></tr>
<tr><th>Min withdrawal</th><td><input name="min_withdrawal" type="number" value="<?php echo linkngon_get_option('min_withdrawal',50000); ?>"></td></tr>
<tr><th>VPN Detect</th><td><select name="detect_vpn_proxy"><option value="1" <?php selected(linkngon_get_option('detect_vpn_proxy',1),1); ?>>On</option><option value="0">Off</option></select></td></tr>
<tr><th>Deposit Bank</th><td><input name="deposit_bank" class="regular-text" value="<?php echo esc_attr(linkngon_get_option('deposit_bank','Vietcombank')); ?>"></td></tr>
<tr><th>Deposit Account</th><td><input name="deposit_account" class="regular-text" value="<?php echo esc_attr(linkngon_get_option('deposit_account','')); ?>"></td></tr>
<tr><th>Deposit Holder</th><td><input name="deposit_holder" class="regular-text" value="<?php echo esc_attr(linkngon_get_option('deposit_holder','')); ?>"></td></tr>
</table>
<p class="submit"><input type="submit" name="linkngon_save" class="button-primary" value="Save"></p></form></div>
