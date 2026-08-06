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

/* ── Hero (single-screen) ── */
.ln-hero{height:100vh;height:100dvh;box-sizing:border-box;display:flex;flex-direction:column;align-items:center;justify-content:center;background:linear-gradient(160deg,#F7F9FE 0%,#EDF2FC 55%,#E3EAFA 100%);color:#0F172A;padding:96px 24px 40px;text-align:center;position:relative;overflow:hidden}
.ln-hero::before{content:'';position:absolute;width:260px;height:260px;background-image:radial-gradient(circle,rgba(59,102,177,.22) 1.6px,transparent 1.6px);background-size:22px 22px;top:36px;left:-40px;opacity:.9;transform:rotate(-6deg);z-index:1}
.ln-hero::after{content:'';position:absolute;width:220px;height:220px;background-image:radial-gradient(circle,rgba(59,102,177,.18) 1.6px,transparent 1.6px);background-size:22px 22px;top:70px;right:-20px;opacity:.75;transform:rotate(8deg);z-index:1}
.ln-hero-waves{position:absolute;left:0;right:0;bottom:0;height:240px;z-index:1;pointer-events:none;overflow:hidden}
.ln-hero-waves span{position:absolute;left:-15%;right:-15%;height:280px;border-radius:50%}
.ln-hero-waves span:nth-child(1){background:#E3EAFA;bottom:-170px;opacity:.9}
.ln-hero-waves span:nth-child(2){background:#D2E0F7;bottom:-190px;left:0;right:-25%;opacity:.85}
.ln-hero-waves span:nth-child(3){background:#BFD2F3;bottom:-210px;left:-25%;right:5%;opacity:.85}

/* Fade-in nhẹ cho nội dung hero */
.ln-hero>*:not(.ln-hero-waves){position:relative;z-index:2}
.ln-hero>.ln-shorten-box{opacity:0;animation:lnFadeUp .7s ease-out .4s forwards}
@keyframes lnFadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}

/* ============================================================
   WELCOME BANNER — "Chào mừng bạn / Đã đến với / SITETOP"
   (chuyển từ file sitetop-welcome-banner.html, đã fix hết nhoè:
   không transform:skewX (bug raster + background-clip:text ở size
   lớn), text-shadow không blur-radius — chỉ bóng đổ 0-blur)
   ============================================================ */
.banner{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:clamp(10px,1.6vw,26px);width:100%;font-family:'Arial Black','Helvetica Neue',Arial,sans-serif;text-align:center}

.line1-row{display:flex;align-items:center;justify-content:center;gap:clamp(14px,2vw,32px)}
.line1-row .glow-bar{width:clamp(40px,6vw,110px);height:3px;border-radius:2px;background:linear-gradient(90deg,transparent,#1e90ff);box-shadow:0 0 8px 1px rgba(30,144,255,.8)}
.line1-row .glow-bar.right{background:linear-gradient(90deg,#1e90ff,transparent)}
.line1{font-size:clamp(18px,3vw,46px);font-weight:900;text-transform:uppercase;font-style:italic;letter-spacing:.03em;background:linear-gradient(180deg,#0b1a33 0%,#071226 100%);-webkit-background-clip:text;background-clip:text;color:transparent;-webkit-text-stroke:1px rgba(255,255,255,.12);text-shadow:0 1px 3px rgba(0,0,0,.25)}

.line2-row{display:flex;align-items:center;justify-content:center;gap:clamp(10px,1.6vw,22px)}
.line2-row .dot-line{display:flex;align-items:center;gap:6px}
.line2-row .dot-line .dot{width:6px;height:6px;border-radius:50%;background:#3bb6ff;box-shadow:0 0 6px 2px rgba(59,182,255,.9)}
.line2-row .dot-line .bar{width:clamp(30px,4.5vw,80px);height:2px;background:rgba(30,144,255,.7);box-shadow:0 0 6px rgba(30,144,255,.6)}
.line2-row .dot-line.left{flex-direction:row}
.line2-row .dot-line.right{flex-direction:row-reverse}
.line2{font-size:clamp(13px,1.7vw,27px);font-weight:800;text-transform:uppercase;font-style:italic;letter-spacing:.06em;background:linear-gradient(90deg,#1e90ff,#3bb6ff);-webkit-background-clip:text;background-clip:text;color:transparent;text-shadow:0 1px 3px rgba(30,144,255,.35)}

.line3{width:70%;max-width:1000px;font-size:clamp(44px,7.6vw,132px);font-weight:900;text-transform:uppercase;font-style:italic;line-height:1;letter-spacing:-.01em;white-space:nowrap}
.line3 .site{background:linear-gradient(180deg,#0b1a33 0%,#050d1c 100%);-webkit-background-clip:text;background-clip:text;color:transparent;text-shadow:0 1px 3px rgba(0,0,0,.25)}
.line3 .top{background:linear-gradient(100deg,#0057ff 0%,#22d3ff 100%);-webkit-background-clip:text;background-clip:text;color:transparent;text-shadow:0 1px 3px rgba(0,0,0,.2)}

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
    .welcome-text{font-size:26px}
    .sub-text{font-size:14px;margin-bottom:20px}
    .ln-shorten-form input{padding:14px 14px;font-size:14px}
    .ln-shorten-form button{padding:14px 20px;font-size:14px;margin-left:4px}
    .ln-feat-grid{grid-template-columns:1fr}
    .ln-section-title h2{font-size:26px}
}
/* ── Responsive: height thấp (landscape / màn nhỏ) ── */
@media(max-height:700px){
    .ln-hero{padding-top:76px}
    .welcome-text{font-size:24px;margin-bottom:8px}
    .sub-text{font-size:13px;margin-bottom:16px;line-height:1.5}
}
@media(max-height:560px){
    .ln-hero-waves{display:none}
    .welcome-text{font-size:20px}
    .sub-text{display:none}
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
