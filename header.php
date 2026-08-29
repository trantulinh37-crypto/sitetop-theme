<?php if (!defined('ABSPATH')) exit; ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;background:#050A18;color:#E2E8F0}

/* ── Header ── */
/* Header trong suốt (29/08/2026): bỏ nền trắng 92% và đường kẻ dưới, logo và nút
   nổi thẳng trên ảnh hero. Chủ site chốt CHỈ sửa giao diện, giữ nguyên file logo —
   logo vẫn là PNG nền trắng nên trên header trong suốt sẽ thấy một hộp trắng quanh nó.
   Trạng thái .scrolled bên dưới GIỮ nền trắng: khi trang cuộn, nội dung chạy dưới
   header cần một nền đặc để chữ không chồng lên nhau. Hiệu ứng làm mờ nền cũng
   chuyển xuống .scrolled — ở trạng thái trong suốt thì làm mờ không có tác dụng gì. */
.tt-header{position:fixed;top:0;left:0;right:0;z-index:100;transition:all .4s cubic-bezier(.4,0,.2,1);background:transparent;border-bottom:none}
/* Admin bar của WP (z-index:99999, fixed top:0, cao 32px/46px mobile) đè lên header
   custom (top:0) làm logo bị cắt phần trên — đẩy header xuống đúng bằng chiều cao
   admin bar khi đang đăng nhập. body.admin-bar là class WP tự thêm, không cần PHP riêng. */
body.admin-bar .tt-header{top:32px}
@media(max-width:782px){body.admin-bar .tt-header{top:46px}}
.tt-header-inner{max-width:1200px;margin:0 auto;padding:10px 24px;display:flex;align-items:center;justify-content:space-between}
.tt-header.scrolled{background:rgba(255,255,255,.98);backdrop-filter:blur(14px) saturate(1.6);box-shadow:0 4px 20px rgba(15,23,42,.06)}
.tt-logo{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:18px;color:#0F172A;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:opacity .2s}
.tt-logo:hover{opacity:.85}
.tt-logo-text{letter-spacing:.01em}
.tt-logo-site{color:#0F172A}
.tt-logo-top{background:linear-gradient(120deg,#2563EB,#38BDF8);-webkit-background-clip:text;background-clip:text;color:transparent}
.tt-logo-icon{width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,#6366F1,#8B5CF6);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(99,102,241,.35)}
.tt-nav{display:flex;gap:12px;align-items:center}
.tt-nav-link{color:#475569;text-decoration:none;font-size:13px;font-weight:500;padding:8px 16px;border-radius:10px;transition:all .25s}
.tt-nav-link:hover{color:#0F172A;background:rgba(15,23,42,.05)}

/* Dashboard Dropdown */
.tt-dropdown{position:relative}
.tt-dropdown-btn{display:inline-flex;align-items:center;gap:6px;background:linear-gradient(90deg,#0A1F44,#1E90FF,#00C6FF);color:#fff;border:none;padding:8px 18px;border-radius:10px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;transition:all .3s;box-shadow:0 2px 12px rgba(30,144,255,.3)}
.tt-dropdown-btn:hover{background:linear-gradient(90deg,#0A1F44,#3399FF,#33D6FF);box-shadow:0 4px 20px rgba(30,144,255,.45);transform:translateY(-1px)}
.tt-dropdown-menu{display:none;position:absolute;top:calc(100% + 8px);right:0;background:rgba(15,20,40,.95);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.08);border-radius:12px;box-shadow:0 16px 48px rgba(0,0,0,.4);min-width:180px;z-index:100;overflow:hidden;padding:4px}
.tt-dropdown.open .tt-dropdown-menu{display:block}
.tt-dropdown-menu a{display:flex;align-items:center;gap:10px;padding:10px 14px;font-size:13px;color:rgba(255,255,255,.7);text-decoration:none;font-weight:500;transition:all .2s;border-radius:8px}
.tt-dropdown-menu a:hover{background:rgba(99,102,241,.15);color:#fff}
.tt-dropdown-menu a svg{color:rgba(255,255,255,.4);flex-shrink:0}

/* User Avatar */
.tt-user-info{display:inline-flex;align-items:center;gap:8px;font-size:13px;color:#475569;font-weight:500}
.tt-avatar{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#6366F1,#8B5CF6);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;box-shadow:0 2px 8px rgba(99,102,241,.3)}

@media(max-width:768px){
    .tt-header-inner{padding:8px 16px}
    .tt-nav{gap:4px}
    .tt-nav-link{padding:6px 10px;font-size:12px}
    .tt-user-info span:not(.tt-avatar){display:none}
}
</style>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="tt-header" id="ttHeader">
<div class="tt-header-inner">
    <a href="<?php echo home_url(); ?>" class="tt-logo">
        <img src="<?php echo esc_url( sitetop_logo_url( 'sitetop-logo-full.png' ) ); ?>" alt="Logo SITETOP" style="height:44px; width:auto;">
    </a>
    <nav class="tt-nav">
        <?php if (is_user_logged_in()): $u = wp_get_current_user(); $is_admin = in_array('administrator', (array) $u->roles, true); ?>
            <?php if ( $is_admin ) : ?>
            <div class="tt-dropdown">
                <button onclick="this.parentElement.classList.toggle('open')" class="tt-dropdown-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    Dashboard
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="tt-dropdown-menu">
                    <a href="<?php echo admin_url(); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Admin</a>
                    <a href="<?php echo home_url('/user'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>User</a>
                    <a href="<?php echo home_url('/customer'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Customer</a>
                </div>
            </div>
            <?php else : ?>
            <a href="<?php echo sitetop_get_dashboard_url(); ?>" class="tt-nav-link">Dashboard</a>
            <?php endif; ?>
            <span class="tt-user-info"><span class="tt-avatar"><?php echo strtoupper(substr($u->display_name,0,1)); ?></span><span><?php echo esc_html($u->display_name); ?></span></span>
            <a href="<?php echo wp_logout_url(home_url()); ?>" class="tt-nav-link" style="color:#94A3B8">Thoát</a>
        <?php else: ?>
            <?php
            /* Gộp Đăng nhập/Đăng ký thành 1 nút QUẢN LÝ, dùng lại NGUYÊN cơ chế dropdown
               của nút Dashboard admin phía trên: cùng .tt-dropdown-btn/.tt-dropdown-menu,
               và JS đóng-khi-click-ra-ngoài ở cuối file vốn quét TẤT CẢ .tt-dropdown.open
               nên tự nhận dropdown này — không cần thêm CSS/JS nào. */
            ?>
            <div class="tt-dropdown">
                <button onclick="this.parentElement.classList.toggle('open')" class="tt-dropdown-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    QUẢN LÝ
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="tt-dropdown-menu">
                    <a href="<?php echo home_url('/dang-nhap'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>Đăng nhập</a>
                    <a href="<?php echo home_url('/dang-ky'); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>Đăng ký</a>
                </div>
            </div>
        <?php endif; ?>
    </nav>
</div>
</header>

<script>
(function(){
    var h=document.getElementById('ttHeader');
    window.addEventListener('scroll',function(){h.classList.toggle('scrolled',window.scrollY>20)});
    document.addEventListener('click',function(e){document.querySelectorAll('.tt-dropdown.open').forEach(function(el){if(!el.contains(e.target))el.classList.remove('open')})});
})();
</script>
