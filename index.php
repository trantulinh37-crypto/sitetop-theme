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

/* ── Hero: "Website rút gọn link và kiếm tiền" (2 cột) ── */
.h2-hero{min-height:100vh;box-sizing:border-box;display:flex;align-items:center;background:linear-gradient(135deg,#F5F8FF 0%,#EAF1FF 45%,#DCE9FF 100%);position:relative;overflow:hidden;padding:120px 24px 60px}
.h2-hero-fx{position:absolute;inset:0;pointer-events:none;z-index:0;
    background-image:radial-gradient(circle,#fff 0 2px,transparent 2px),radial-gradient(circle,#fff 0 1.5px,transparent 1.5px),radial-gradient(circle,#fff 0 1.5px,transparent 1.5px),radial-gradient(circle,#fff 0 1px,transparent 1px),radial-gradient(circle,#fff 0 1.5px,transparent 1.5px);
    background-repeat:no-repeat;
    background-position:10% 20%,20% 72%,55% 85%,75% 15%,90% 55%;
    opacity:.8
}
.h2-hero-grid{position:relative;z-index:1;max-width:1280px;margin:0 auto;width:100%;display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center}

.h2-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:clamp(30px,4vw,48px);line-height:1.2;color:#0F172A;margin-bottom:20px}
.h2-title .hl{color:#2563EB}
.h2-sub{font-size:16px;color:#475569;line-height:1.7;margin-bottom:28px;max-width:480px}
/* WordPress tự chuyển emoji unicode thành <img class="emoji"> — ép về đúng cỡ chữ, tránh hiện to bất thường */
img.emoji{height:1em!important;width:1em!important;margin:0 .05em 0 .1em!important;vertical-align:-.1em!important;display:inline-block!important;background:none!important;border:none!important;padding:0!important;box-shadow:none!important;border-radius:0!important}
.h2-bubble img.emoji{height:16px!important;width:16px!important;margin:0!important}

.h2-pills{display:flex;flex-wrap:nowrap;gap:8px;margin-bottom:32px;overflow-x:auto}
.h2-pill{display:inline-flex;align-items:center;gap:5px;background:#fff;border:1px solid #E2E8F0;border-radius:999px;padding:7px 12px;font-size:11.5px;font-weight:600;color:#334155;box-shadow:0 2px 8px rgba(30,64,150,.06);white-space:nowrap;flex-shrink:0}
.h2-pill svg{width:13px;height:13px;flex-shrink:0;color:#2563EB}

.h2-cta-row{display:flex;align-items:center;gap:24px;flex-wrap:wrap}
.h2-cta{display:inline-flex;align-items:center;gap:10px;background:linear-gradient(90deg,#2563EB,#3B82F6);color:#fff;font-weight:700;font-size:15px;padding:16px 28px;border-radius:999px;text-decoration:none;box-shadow:0 10px 24px rgba(37,99,235,.3);transition:all .25s}
.h2-cta:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(37,99,235,.4)}
.h2-cta svg{width:18px;height:18px;flex-shrink:0}
.h2-cta .arrow{transition:transform .25s}
.h2-cta:hover .arrow{transform:translateX(4px)}

.h2-social{display:flex;align-items:center;gap:12px}
.h2-avatars{display:flex}
.h2-avatars span{width:36px;height:36px;border-radius:50%;border:2px solid #fff;margin-left:-10px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff}
.h2-avatars span:first-child{margin-left:0}
.h2-avatars span:nth-child(1){background:linear-gradient(135deg,#60A5FA,#2563EB)}
.h2-avatars span:nth-child(2){background:linear-gradient(135deg,#818CF8,#4F46E5)}
.h2-avatars span:nth-child(3){background:linear-gradient(135deg,#38BDF8,#0EA5E9)}
.h2-social-text{font-size:13px;color:#475569;line-height:1.4;text-align:left}
.h2-social-text strong{color:#0F172A;font-weight:700}

/* Illustration bên phải — mô phỏng bằng CSS thuần (không dùng ảnh) */
.h2-illus{position:relative;height:420px;display:flex;align-items:center;justify-content:center}
.h2-illus-dots{position:absolute;top:0;right:6%;width:110px;height:110px;background-image:radial-gradient(circle,#93C5FD 1.6px,transparent 1.6px);background-size:16px 16px;opacity:.55;pointer-events:none}
.h2-coin{position:absolute;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-family:Georgia,'Times New Roman',serif;color:#fff;background:radial-gradient(circle at 34% 28%,#DBEAFE 0%,#60A5FA 45%,#1D4ED8 100%);box-shadow:0 16px 32px rgba(37,99,235,.32),inset 0 3px 6px rgba(255,255,255,.75),inset 0 -8px 14px rgba(29,78,216,.45);border:2px dashed rgba(255,255,255,.55)}
.h2-coin.c1{width:120px;height:120px;font-size:44px;top:10px;right:60px;animation:h2Float 5s ease-in-out infinite}
.h2-coin.c2{width:76px;height:76px;font-size:28px;bottom:100px;right:196px;animation:h2Float 6s ease-in-out infinite .5s}
.h2-coin.c3{width:60px;height:60px;font-size:22px;bottom:66px;right:118px;animation:h2Float 4.5s ease-in-out infinite 1s}
.h2-bubble{position:absolute;border-radius:50%;background:radial-gradient(circle at 32% 28%,#fff 0%,#DBEAFE 45%,#93C5FD 100%);box-shadow:0 10px 22px rgba(37,99,235,.22),inset 0 3px 6px rgba(255,255,255,.85);display:flex;align-items:center;justify-content:center}
.h2-bubble svg{width:46%;height:46%;color:#3B82F6}
.h2-bubble.b1{width:56px;height:56px;top:40px;left:20px;animation:h2Float 5.5s ease-in-out infinite .3s}
.h2-bubble.b2{width:40px;height:40px;top:150px;right:20px;animation:h2Float 6.5s ease-in-out infinite .8s}
.h2-bubble.b3{width:70px;height:70px;bottom:20px;left:60px;animation:h2Float 5s ease-in-out infinite 1.2s}
.h2-stripe{position:absolute;width:160px;height:160px;border-radius:50%;bottom:-30px;right:-10px;background:radial-gradient(circle at 30% 26%,rgba(255,255,255,.6),transparent 55%),repeating-linear-gradient(50deg,#BFDBFE 0 14px,#fff 14px 28px);box-shadow:0 20px 40px rgba(37,99,235,.22),inset 0 -12px 22px rgba(29,78,216,.25)}
.h2-phone{position:relative;width:150px;height:280px;background:linear-gradient(155deg,rgba(255,255,255,.7),rgba(191,219,254,.35));border:2px solid rgba(255,255,255,.85);border-radius:26px;transform:rotate(-16deg);box-shadow:0 20px 40px rgba(37,99,235,.22),inset 0 2px 10px rgba(255,255,255,.65)}
.h2-phone::before{content:'';position:absolute;top:14px;left:50%;transform:translateX(-50%);width:34px;height:5px;border-radius:3px;background:rgba(255,255,255,.65)}
@keyframes h2Float{0%,100%{transform:translateY(0)}50%{transform:translateY(-14px)}}

@media(max-width:960px){
    .h2-hero-grid{grid-template-columns:1fr;text-align:center}
    .h2-sub{margin-left:auto;margin-right:auto}
    .h2-pills,.h2-cta-row{justify-content:center}
    .h2-social-text{text-align:left}
    .h2-illus{height:280px;margin-top:10px;transform:scale(.8)}
}
@media(max-width:480px){
    .h2-pills{flex-wrap:wrap;overflow-x:visible}
}

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

/* ── Copyright footer (trang chủ, footer chính đang ẩn cho single-screen hero) ── */
.ln-copyright{padding:20px 24px;text-align:center;font-size:13px;color:#94A3B8;background:#F8FAFC;border-top:1px solid #E2E8F0}

/* ── Responsive: width ── */
@media(max-width:768px){
    .ln-feat-grid{grid-template-columns:1fr}
    .ln-section-title h2{font-size:26px}
}
</style>

<!-- ═══ HERO: Website rút gọn link và kiếm tiền ═══ -->
<section class="h2-hero">
    <div class="h2-hero-fx"></div>
    <div class="h2-hero-grid">
        <div class="h2-left">
            <h1 class="h2-title">Website <span class="hl">rút gọn link</span> và<br><span class="hl">kiếm tiền</span></h1>
            <p class="h2-sub">Nền tảng rút gọn link uy tín hàng đầu Việt Nam. ✅<br>Payout linh hoạt, thống kê chi tiết, API mạnh mẽ. 💧</p>

            <div class="h2-pills">
                <span class="h2-pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>Rút gọn nhanh chóng</span>
                <span class="h2-pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v12M15 9.5c0-1.4-1.3-2.5-3-2.5s-3 1.1-3 2.5 1.3 2.3 3 2.5c1.7.2 3 1.1 3 2.5s-1.3 2.5-3 2.5-3-1.1-3-2.5"/></svg>Kiếm tiền hiệu quả</span>
                <span class="h2-pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>Thống kê chi tiết</span>
                <span class="h2-pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>An toàn &amp; bảo mật</span>
            </div>

            <div class="h2-cta-row">
                <a href="<?php echo $is_logged ? home_url('/user') : home_url('/dang-ky'); ?>" class="h2-cta">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
                    Liên hệ ngay
                    <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
                <div class="h2-social">
                    <div class="h2-avatars"><span>A</span><span>B</span><span>C</span></div>
                    <div class="h2-social-text"><strong>10.000+ người dùng</strong><br>đang tin tưởng</div>
                </div>
            </div>
        </div>

        <div class="h2-right">
            <div class="h2-illus">
                <div class="h2-illus-dots"></div>
                <div class="h2-phone"></div>
                <div class="h2-stripe"></div>
                <div class="h2-coin c1">$</div>
                <div class="h2-coin c2">$</div>
                <div class="h2-coin c3">$</div>
                <?php $heart = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-6.7-4.35-9.3-8.05C1.1 10.2 1.9 6.8 5 5.6c2-.8 4 .1 5 1.9 1-1.8 3-2.7 5-1.9 3.1 1.2 3.9 4.6 2.3 7.35C18.7 16.65 12 21 12 21z"/></svg>'; ?>
                <div class="h2-bubble b1"><?php echo $heart; ?></div>
                <div class="h2-bubble b2"><?php echo $heart; ?></div>
                <div class="h2-bubble b3"><?php echo $heart; ?></div>
            </div>
        </div>
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

<!-- ═══ COPYRIGHT ═══ -->
<div class="ln-copyright">Copyright &copy;sitetop.net 2025</div>

<script>
// Form trang chủ chỉ là CTA — click → redirect tới dashboard (logged-in) hoặc
// đăng ký (guest). Tránh user tự tạo + click shortlink ngay tại đây sinh
// referer "Trang chủ" làm nhiễu analytics.
function goShorten() {
    window.location.href = <?php echo $is_logged ? "'" . esc_js( home_url('/user') ) . "'" : "'" . esc_js( home_url('/dang-ky') ) . "'"; ?>;
}
</script>

<?php get_footer(); ?>
