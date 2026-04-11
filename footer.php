<?php if (!defined('ABSPATH')) exit; ?>
<footer style="background:#0F172A;color:rgba(255,255,255,.4);padding:48px 0 32px;margin-top:48px">
<div style="max-width:1200px;margin:0 auto;padding:0 24px">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:32px;margin-bottom:32px">
        <div style="max-width:320px">
            <?php $ft_icon = get_option('traffictop_widget_icon',''); ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                <?php if($ft_icon): ?><img src="<?php echo esc_url($ft_icon); ?>" width="24" height="24" alt="" style="border-radius:6px"><?php else: ?><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#60A5FA" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><?php endif; ?>
                <span style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:17px;color:#fff">Traffictop.net</span>
            </div>
            <p style="font-size:13px;line-height:1.7;color:rgba(255,255,255,.45)">Nền tảng trung gian kết nối người cung cấp traffic và doanh nghiệp cần đẩy SEO từ khóa lên top Google.</p>
        </div>
        <div style="display:flex;gap:48px;flex-wrap:wrap">
            <div>
                <div style="font-weight:700;font-size:13px;color:rgba(255,255,255,.7);margin-bottom:12px">Dành cho User</div>
                <div style="display:flex;flex-direction:column;gap:8px;font-size:13px">
                    <a href="<?php echo home_url('/dang-ky'); ?>" style="color:rgba(255,255,255,.4);text-decoration:none">Đăng ký kiếm tiền</a>
                    <a href="<?php echo home_url('/nguoi-dung'); ?>" style="color:rgba(255,255,255,.4);text-decoration:none">Dashboard</a>
                    <a href="<?php echo home_url('/dieu-khoan'); ?>" style="color:rgba(255,255,255,.4);text-decoration:none">Điều khoản</a>
                </div>
            </div>
            <div>
                <div style="font-weight:700;font-size:13px;color:rgba(255,255,255,.7);margin-bottom:12px">Dành cho Doanh nghiệp</div>
                <div style="display:flex;flex-direction:column;gap:8px;font-size:13px">
                    <a href="<?php echo home_url('/dang-ky?type=customer'); ?>" style="color:rgba(255,255,255,.4);text-decoration:none">Đăng ký mua traffic</a>
                    <a href="<?php echo home_url('/khach-hang'); ?>" style="color:rgba(255,255,255,.4);text-decoration:none">Tạo chiến dịch SEO</a>
                </div>
            </div>
        </div>
    </div>
    <div style="border-top:1px solid rgba(255,255,255,.08);padding-top:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <p style="font-size:12px">&copy; <?php echo date('Y'); ?> Traffictop.net. All rights reserved.</p>
        <p style="font-size:12px">Traffic SEO &middot; Keyword Ranking &middot; Real Users</p>
    </div>
</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
