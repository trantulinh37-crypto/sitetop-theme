<?php if (!defined('ABSPATH')) exit; ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',sans-serif;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.ln-switcher-menu{display:none;position:absolute;top:calc(100% + 8px);right:0;background:#fff;border:1px solid #E5E2DB;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.1);min-width:160px;z-index:100;overflow:hidden}
.ln-switcher-wrap.open .ln-switcher-menu{display:block}
.ln-switcher-menu a{display:flex;align-items:center;gap:10px;padding:10px 16px;font-size:13px;color:#2C2C3A;text-decoration:none;font-weight:500;transition:background .15s}
.ln-switcher-menu a:hover{background:#F7F5F0}
.ln-switcher-menu a svg{color:#6B7280;flex-shrink:0}
</style>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header style="background:rgba(255,255,255,.95);backdrop-filter:blur(10px);border-bottom:1px solid #F0EDE6;position:sticky;top:0;z-index:50">
<div style="max-width:1200px;margin:0 auto;padding:14px 24px;display:flex;align-items:center;justify-content:space-between">
    <?php $ln_icon = get_option('traffictop_widget_icon',''); ?>
    <a href="<?php echo home_url(); ?>" style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:17px;color:#0D4F4F;text-decoration:none;display:inline-flex;align-items:center"><?php if($ln_icon): ?><img src="<?php echo esc_url($ln_icon); ?>" width="20" height="20" alt="" style="vertical-align:middle;margin-right:4px"><?php else: ?><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><?php endif; ?>Traffictop.net</a>
    <nav style="display:flex;gap:16px;align-items:center;font-size:13px">
        <?php if (is_user_logged_in()): $u = wp_get_current_user(); $is_admin = in_array('administrator', (array) $u->roles, true); ?>
            <?php if ( $is_admin ) : ?>
            <div style="position:relative" class="ln-switcher-wrap">
                <button onclick="this.parentElement.classList.toggle('open')" style="display:inline-flex;align-items:center;gap:5px;background:#0D4F4F;color:#fff;border:none;padding:6px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    Dashboard
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="ln-switcher-menu">
                    <a href="<?php echo admin_url(); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Admin</a>
                    <a href="<?php echo home_url('/nguoi-dung'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>User</a>
                    <a href="<?php echo home_url('/khach-hang'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Customer</a>
                </div>
            </div>
            <?php else : ?>
            <a href="<?php echo traffictop_get_dashboard_url(); ?>" style="color:#2C2C3A;text-decoration:none">Dashboard</a>
            <?php endif; ?>
            <span style="display:inline-flex;align-items:center;gap:6px"><span style="width:26px;height:26px;border-radius:50%;background:#0D4F4F;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700"><?php echo strtoupper(substr($u->display_name,0,1)); ?></span><?php echo esc_html($u->display_name); ?></span>
            <a href="<?php echo wp_logout_url(home_url()); ?>" style="color:#6B7280;text-decoration:none">Đăng xuất</a>
        <?php else: ?>
            <a href="<?php echo home_url('/dang-nhap'); ?>" style="color:#2C2C3A;text-decoration:none;font-weight:500">Đăng nhập</a>
            <a href="<?php echo home_url('/dang-ky'); ?>" style="padding:8px 20px;background:#0D4F4F;color:#fff;border-radius:8px;font-weight:600;text-decoration:none;font-size:13px">Đăng ký</a>
        <?php endif; ?>
    </nav>
</div>
</header>
<script>document.addEventListener('click',function(e){document.querySelectorAll('.ln-switcher-wrap.open').forEach(function(el){if(!el.contains(e.target))el.classList.remove('open')})});</script>
