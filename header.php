<?php if (!defined('ABSPATH')) exit; ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=DM+Serif+Display&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header style="background:rgba(255,255,255,.95);backdrop-filter:blur(10px);border-bottom:1px solid #F0EDE6;position:sticky;top:0;z-index:50">
<div style="max-width:1200px;margin:0 auto;padding:14px 24px;display:flex;align-items:center;justify-content:space-between">
    <a href="<?php echo home_url(); ?>" style="font-family:'DM Serif Display',serif;font-size:20px;color:#0D4F4F;text-decoration:none">🔗 LinkNgon</a>
    <nav style="display:flex;gap:16px;align-items:center;font-size:13px">
        <?php if (is_user_logged_in()): $u = wp_get_current_user(); ?>
            <a href="<?php echo get_permalink(get_page_by_path('dashboard')); ?>" style="color:#2C2C3A;text-decoration:none">Dashboard</a>
            <span style="display:inline-flex;align-items:center;gap:6px"><span style="width:26px;height:26px;border-radius:50%;background:#0D4F4F;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700"><?php echo strtoupper(substr($u->display_name,0,1)); ?></span><?php echo esc_html($u->display_name); ?></span>
            <a href="<?php echo wp_logout_url(home_url()); ?>" style="color:#6B7280;text-decoration:none">Đăng xuất</a>
        <?php else: ?>
            <a href="<?php echo wp_login_url(); ?>" style="color:#2C2C3A;text-decoration:none">Đăng nhập</a>
            <a href="<?php echo wp_registration_url(); ?>" style="padding:7px 18px;background:#0D4F4F;color:#fff;border-radius:8px;font-weight:600;text-decoration:none">Đăng ký</a>
        <?php endif; ?>
    </nav>
</div>
</header>
