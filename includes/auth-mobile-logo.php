<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="auth-mobile-logo">
    <?php $ln_icon = get_option('traffictop_widget_icon',''); ?>
    <a href="<?php echo home_url(); ?>">
        <?php if($ln_icon): ?><img src="<?php echo esc_url($ln_icon); ?>" width="22" height="22" alt="" style="margin-right:6px"><?php else: ?><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><?php endif; ?>
        Traffictop.net
    </a>
</div>
