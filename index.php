<?php
/**
 * SiteTop.net V2 - Homepage
 * Nền tảng rút gọn link kiếm tiền & mua traffic website
 * Hero (single-screen 100vh) + Features — Updated: 2026-08-06
 */
get_header();

global $wpdb;
$prefix = $wpdb->prefix . 'sitetop_';

$nonce        = wp_create_nonce( 'sitetop_nonce' );
$is_logged    = is_user_logged_in();
$min_withdraw = (int) sitetop_get_option( 'min_withdrawal', 50000 );
$ref_enabled  = sitetop_get_option( 'referral_enabled', 0 );
$ref_pct      = (int) sitetop_get_option( 'referral_commission_percent', 20 );
?>
<style>
body{overflow-x:hidden}
footer{display:none!important}

/* ── Hero (single-screen) — theo prompt thiết kế màu chuẩn ── */
.ln-hero{
    --c-title-1:#071126;--c-title-2:#0B1833;--c-title-3:#0E2144;
    --c-sub-1:#58B7FF;--c-sub-2:#2D7CFF;--c-sub-3:#0F55FF;
    --c-site-1:#071126;--c-site-2:#0A1730;--c-site-3:#101E3D;--c-site-4:#162B56;
    --c-top-1:#1E56FF;--c-top-2:#146CFF;--c-top-3:#168DFF;--c-top-4:#22C2FF;
    --c-line-glow:#3AA7FF;--c-line-glow-2:#6FD4FF;
    --c-sub-line:#4FAEFF;--c-sub-dot:#2F8FFF;
    --c-desc-1:#314B76;
    --c-wave-1:#3B82FF;--c-wave-2:#5EA6FF;--c-wave-3:#8CC7FF;
    --c-glowbar:#5EC4FF;
    height:100vh;height:100dvh;box-sizing:border-box;display:flex;flex-direction:column;align-items:center;justify-content:center;
    background:radial-gradient(circle at 50% 32%,#FFFFFF 0%,#F6F9FF 38%,#EEF4FF 100%);
    color:#0F172A;padding:96px 24px 40px;text-align:center;position:relative;overflow:hidden
}
.ln-hero-waves{position:absolute;left:0;right:0;bottom:0;height:240px;z-index:1;pointer-events:none;overflow:hidden;opacity:.55}
.ln-hero-waves span{position:absolute;left:-15%;right:-15%;height:280px;border-radius:50%}
.ln-hero-waves span:nth-child(1){background:var(--c-wave-3);bottom:-170px}
.ln-hero-waves span:nth-child(2){background:var(--c-wave-2);bottom:-190px;left:0;right:-25%}
.ln-hero-waves span:nth-child(3){background:var(--c-wave-1);bottom:-210px;left:-25%;right:5%}

/* Fade-in nhẹ cho nội dung hero */
.ln-hero>*:not(.ln-hero-waves){position:relative;z-index:2}
.ln-hero>.ln-shorten-box{opacity:0;animation:lnFadeUp .7s ease-out .4s forwards}
@keyframes lnFadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}

/* ============================================================
   WELCOME BANNER — "Chào mừng bạn / Đã đến với / SITETOP"
   Màu/gradient/glow theo prompt thiết kế chuẩn (xem CSS var ở .ln-hero)
   ============================================================ */
.banner{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:clamp(8px,1.3vw,20px);width:100%;font-family:'Arial Black','Helvetica Neue',Arial,sans-serif;text-align:center;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}

.line1-row{display:flex;align-items:center;justify-content:center;gap:clamp(12px,1.8vw,28px)}
.line1-row .glow-bar{width:clamp(36px,5vw,90px);height:3px;border-radius:2px;background:linear-gradient(90deg,rgba(255,255,255,0) 0%,var(--c-line-glow) 35%,var(--c-line-glow-2) 50%,var(--c-line-glow) 65%,rgba(255,255,255,0) 100%);box-shadow:0 0 10px rgba(58,167,255,.45)}
.line1{font-size:clamp(17px,2.9vw,42px);font-weight:900;text-transform:uppercase;font-style:italic;letter-spacing:1px;background:linear-gradient(180deg,var(--c-title-1) 0%,var(--c-title-2) 52%,var(--c-title-3) 100%);-webkit-background-clip:text;background-clip:text;color:transparent;-webkit-text-stroke:.6px rgba(255,255,255,.12);text-shadow:0 1px 0 rgba(255,255,255,.2),0 2px 4px rgba(0,0,0,.14)}

.line2-row{display:flex;align-items:center;justify-content:center;gap:clamp(8px,1.3vw,18px)}
.line2-row .dot-line{display:flex;align-items:center;gap:6px}
.line2-row .dot-line .dot{width:5px;height:5px;border-radius:50%;background:var(--c-sub-dot);box-shadow:0 0 6px 2px rgba(47,143,255,.7)}
.line2-row .dot-line .bar{width:clamp(24px,3.6vw,64px);height:2px;background:var(--c-sub-line);box-shadow:0 0 6px rgba(79,174,255,.6)}
.line2-row .dot-line.left{flex-direction:row}
.line2-row .dot-line.right{flex-direction:row-reverse}
.line2{font-size:clamp(12px,1.8vw,25px);font-weight:800;text-transform:uppercase;font-style:italic;letter-spacing:1.2px;background:linear-gradient(180deg,var(--c-sub-1) 0%,var(--c-sub-2) 48%,var(--c-sub-3) 100%);-webkit-background-clip:text;background-clip:text;color:transparent;text-shadow:0 0 5px rgba(53,143,255,.3),0 1px 3px rgba(0,0,0,.08)}

.line3{width:64%;max-width:820px;font-size:clamp(36px,6.4vw,90px);font-weight:900;text-transform:uppercase;font-style:italic;line-height:1;letter-spacing:0;white-space:nowrap}
.line3 .site,.line3 .top{position:relative;display:inline-block}
.line3 .site{background:linear-gradient(180deg,var(--c-site-1) 0%,var(--c-site-2) 32%,var(--c-site-3) 65%,#1E3A6B 100%);-webkit-background-clip:text;background-clip:text;color:transparent;-webkit-text-stroke:.5px rgba(255,255,255,.1);text-shadow:0 2px 0 rgba(255,255,255,.12),0 4px 8px rgba(0,0,0,.18)}
.line3 .top{
    background-image:linear-gradient(180deg,rgba(255,255,255,.55) 0%,rgba(255,255,255,.15) 24%,rgba(255,255,255,0) 50%),linear-gradient(90deg,var(--c-top-1) 0%,var(--c-top-2) 28%,var(--c-top-3) 58%,var(--c-top-4) 100%);
    -webkit-background-clip:text;background-clip:text;color:transparent;
    text-shadow:0 3px 6px rgba(0,0,0,.14),0 1px 0 rgba(255,255,255,.15)
}
.line3-glow-bar{width:min(220px,55%);height:3px;border-radius:2px;margin:10px auto 0;background:linear-gradient(90deg,rgba(255,255,255,0) 0%,var(--c-glowbar) 50%,rgba(255,255,255,0) 100%);box-shadow:0 0 12px rgba(94,196,255,.55),0 0 24px rgba(94,196,255,.18)}

.ln-hero-subtitle{font-family:'Inter',sans-serif;font-size:clamp(13px,1.4vw,18px);font-weight:500;color:var(--c-desc-1);opacity:.96;max-width:560px;margin:12px auto 0;line-height:1.6;text-shadow:0 1px 0 rgba(255,255,255,.55)}

@media(max-width:480px){
    .line1-row .glow-bar,.line2-row .dot-line .bar{display:none}
}

/* ── Shorten Box ── */
.ln-shorten-box{max-width:680px;margin:0 auto;width:100%}
.ln-shorten-form{display:flex;gap:0;background:#fff;border-radius:16px;padding:6px;border:1px solid #E2E8F0;box-shadow:0 12px 30px rgba(30,64,150,.12)}
.ln-shorten-form input{flex:1;min-width:0;padding:16px 20px;background:transparent;border:none;border-radius:12px;font-family:'Inter',sans-serif;font-size:15px;color:#1E293B;outline:none}
.ln-shorten-form input::placeholder{color:#94A3B8}
.ln-shorten-form button{padding:16px 32px;background:#3B82F6;color:#fff;border:none;border-radius:12px;font-family:'Inter',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:all .25s;white-space:nowrap;margin-left:6px}
.ln-shorten-form button:hover{background:#2563EB;transform:scale(1.02)}
.ln-shorten-note{font-size:12px;color:#64748B;margin-top:12px}
.ln-shorten-note a{color:#2563EB}

/* Result (giữ CSS phòng khi có JS dùng sau — hiện không kích hoạt) */
.ln-result{display:none;margin-top:20px;background:#fff;border-radius:14px;padding:20px;border:1px solid #E2E8F0;box-shadow:0 8px 24px rgba(30,64,150,.1)}
.ln-result-url{display:flex;align-items:center;gap:10px}
.ln-result-url input{flex:1;padding:14px 16px;background:#F8FAFC;border:2px solid #3B82F6;border-radius:10px;font-family:'JetBrains Mono',monospace;font-size:14px;color:#1E293B;font-weight:600}
.ln-result-url button{padding:14px 20px;background:#059669;color:#fff;border:none;border-radius:10px;font-weight:700;cursor:pointer;font-family:'Inter',sans-serif;transition:all .2s}
.ln-result-url button:hover{background:#047857}
.ln-result-stats{display:flex;gap:24px;margin-top:14px;font-size:13px;color:#64748B}

/* ── Features ── */
.ln-features{padding:80px 24px;background:#F8FAFC}
.ln-section-title{text-align:center;margin-bottom:48px}
.ln-section-title h2{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:34px;color:#0F172A;margin-bottom:8px}
.ln-section-title p{color:#64748B;font-size:15px}
.ln-feat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;max-width:1000px;margin:0 auto}
.ln-feat{background:#fff;border-radius:14px;padding:28px;border:1px solid #E2E8F0;text-align:center;transition:all .3s}
.ln-feat:hover{transform:translateY(-3px);box-shadow:0 6px 20px rgba(0,0,0,.05)}
.ln-feat-icon{margin-bottom:16px;display:flex;justify-content:center}.ln-feat-icon svg{width:40px;height:40px}
.ln-feat h3{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:18px;color:#0F172A;margin-bottom:6px}
.ln-feat p{font-size:13px;color:#64748B;line-height:1.6}

/* ── Responsive: width ── */
@media(max-width:768px){
    .ln-hero{padding:88px 20px 28px}
    .ln-shorten-form input{padding:14px 14px;font-size:14px}
    .ln-shorten-form button{padding:14px 20px;font-size:14px;margin-left:4px}
    .ln-feat-grid{grid-template-columns:1fr}
    .ln-section-title h2{font-size:26px}
}
/* ── Responsive: height thấp (landscape / màn nhỏ) ── */
@media(max-height:700px){
    .ln-hero{padding-top:76px}
    .line1{margin-bottom:0}
    .ln-hero-subtitle{font-size:13px;margin-top:10px;line-height:1.5}
}
@media(max-height:560px){
    .ln-hero-waves{display:none}
    .ln-hero-subtitle{display:none}
}
</style>

<!-- ═══ HERO + SHORTEN BOX (single-screen) ═══ -->
<section class="ln-hero">
    <div class="ln-hero-waves"><span></span><span></span><span></span></div>

    <div class="banner">
        <!-- Dòng 1: CHÀO MỪNG BẠN + 2 vạch sáng 2 bên -->
        <div class="line1-row">
            <span class="glow-bar left"></span>
            <h1 class="line1">Chào mừng bạn</h1>
            <span class="glow-bar right"></span>
        </div>

        <!-- Dòng 2: ĐÃ ĐẾN VỚI + line đối xứng có chấm tròn đầu line -->
        <div class="line2-row">
            <span class="dot-line left"><span class="dot"></span><span class="bar"></span></span>
            <h2 class="line2">Đã đến với</h2>
            <span class="dot-line right"><span class="bar"></span><span class="dot"></span></span>
        </div>

        <!-- Dòng 3: SITETOP — điểm nhấn chính -->
        <h1 class="line3"><span class="site">SITE</span><span class="top">TOP</span></h1>
        <div class="line3-glow-bar"></div>

        <p class="ln-hero-subtitle">Nền tảng Traffic User giúp doanh nghiệp bứt phá thứ hạng SEO và tiếp cận khách hàng hiệu quả</p>
    </div>
</section>

<!-- ═══ FEATURES ═══ -->
<section class="ln-features">
    <div class="ln-section-title">
        <h2>Tại sao chọn SiteTop.net?</h2>
        <p>Nền tảng trung gian traffic User uy tín hàng đầu Việt Nam</p>
    </div>
    <div class="ln-feat-grid">
        <div class="ln-feat">
            <div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></div>
            <h3>Traffic người thật</h3>
            <p>100% traffic từ người dùng thực, không bot. Hệ thống xác minh danh tính, chống gian lận đa lớp.</p>
        </div>
        <div class="ln-feat">
            <div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></div>
            <h3>Tăng hạng SEO</h3>
            <p>Traffic keyword giúp tăng CTR trên Google, cải thiện thứ hạng từ khóa một cách tự nhiên và bền vững.</p>
        </div>
        <div class="ln-feat">
            <div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><circle cx="12" cy="16" r="1"/></svg></div>
            <h3>Chống gian lận</h3>
            <p>Hệ thống fraud scoring, phát hiện VPN/Proxy, fingerprint thiết bị. Đảm bảo chất lượng traffic cho nhà quảng cáo.</p>
        </div>
        <div class="ln-feat">
            <div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <h3>Thanh toán nhanh</h3>
            <p>User rút tiền tối thiểu <?php echo sitetop_format_money( $min_withdraw ); ?>. Hỗ trợ chuyển khoản ngân hàng và USDT.</p>
        </div>
        <div class="ln-feat">
            <div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
            <h3>Phân phối thông minh</h3>
            <p>Thuật toán tự động phân phối traffic đều trong ngày, mô phỏng hành vi truy cập tự nhiên.</p>
        </div>
        <?php if ( $ref_enabled ) : ?>
        <div class="ln-feat">
            <div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
            <h3>Giới thiệu <?php echo $ref_pct; ?>%</h3>
            <p>Mời bạn bè tham gia và nhận <?php echo $ref_pct; ?>% hoa hồng từ thu nhập của họ.</p>
        </div>
        <?php else : ?>
        <div class="ln-feat">
            <div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div>
            <h3>Dashboard trực quan</h3>
            <p>Theo dõi traffic, thu nhập, chiến dịch realtime. Giao diện thân thiện trên mọi thiết bị.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
// Form trang chủ chỉ là CTA — click → redirect tới dashboard (logged-in) hoặc
// đăng ký (guest). Tránh user tự tạo + click shortlink ngay tại đây sinh
// referer "Trang chủ" làm nhiễu analytics.
function goShorten() {
    window.location.href = <?php echo $is_logged ? "'" . esc_js( home_url('/user') ) . "'" : "'" . esc_js( home_url('/dang-ky') ) . "'"; ?>;
}
</script>

<?php get_footer(); ?>
