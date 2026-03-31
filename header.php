<?php if (!defined('ABSPATH')) exit; ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}</style>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header style="background:rgba(255,255,255,.95);backdrop-filter:blur(10px);border-bottom:1px solid #F0EDE6;position:sticky;top:0;z-index:50">
<div style="max-width:1200px;margin:0 auto;padding:14px 24px;display:flex;align-items:center;justify-content:space-between">
    <a href="<?php echo home_url(); ?>" style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:20px;color:#0D4F4F;text-decoration:none;display:inline-flex;align-items:center"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>LinkNgon</a>
    <nav style="display:flex;gap:16px;align-items:center;font-size:13px">
        <?php if (is_user_logged_in()): $u = wp_get_current_user(); ?>
            <a href="<?php echo home_url( '/dashboard' ); ?>" style="color:#2C2C3A;text-decoration:none">Dashboard</a>
            <span style="display:inline-flex;align-items:center;gap:6px"><span style="width:26px;height:26px;border-radius:50%;background:#0D4F4F;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700"><?php echo strtoupper(substr($u->display_name,0,1)); ?></span><?php echo esc_html($u->display_name); ?></span>
            <a href="<?php echo wp_logout_url(home_url()); ?>" style="color:#6B7280;text-decoration:none">Đăng xuất</a>
        <?php else: ?>
            <a href="<?php echo home_url('/dang-nhap'); ?>" style="color:#2C2C3A;text-decoration:none;font-weight:500">Đăng nhập</a>
            <a href="<?php echo home_url('/dang-ky'); ?>" style="padding:8px 20px;background:#0D4F4F;color:#fff;border-radius:8px;font-weight:600;text-decoration:none;font-size:13px">Đăng ký</a>
        <?php endif; ?>
    </nav>
</div>
</header>
