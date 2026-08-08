<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="auth-mobile-logo">
    <?php $ln_icon = get_option('sitetop_widget_icon',''); ?>
    <a href="<?php echo esc_url( home_url() ); ?>">
        <img src="<?php echo esc_url( $ln_icon ?: sitetop_logo_url('sitetop-logo.png') ); ?>" width="22" height="22" alt="" style="margin-right:6px;border-radius:50%">
        SiteTop.net
    </a>
</div>
