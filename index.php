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

// Cache-bust ảnh nền Hero bằng mtime thật của file — mỗi lần thay ảnh (cùng tên
// hero-bg.jpg) trình duyệt/CDN sẽ tự tải bản mới thay vì giữ cache cũ theo URL cũ.
$hero_bg_path = SITETOP_DIR . '/assets/img/hero-bg.jpg';
$hero_bg_ver  = file_exists( $hero_bg_path ) ? filemtime( $hero_bg_path ) : SITETOP_VERSION;
$hero_bg_url  = esc_url( SITETOP_URL . '/assets/img/hero-bg.jpg?v=' . $hero_bg_ver );
?>
<style>
body{overflow-x:hidden}
footer{display:none!important}

/* ── Hero: "Website rút gọn link và kiếm tiền" ── */
/* Nền = ảnh minh hoạ đầy đủ (sóng + chấm bi + đồng xu/phone/bubble) do user cung cấp.
   Ảnh được đặt trên ::before (to hơn khung 6% mỗi cạnh) rồi cho trôi nổi nhẹ bằng
   transform — nhờ ảnh dôi ra sẵn nên khi dịch chuyển không bao giờ lộ mép/khoảng hở.
   Tách icon rời để bay riêng lẻ đã thử (inpainting xoá icon) nhưng nền bị rách khi
   xoá — cụm minh hoạ dày đặc, không đủ vùng nền trống xung quanh để vá liền mạch,
   nên chọn cách an toàn: trôi nổi cả khối. */
.h2-hero{min-height:100vh;box-sizing:border-box;display:flex;align-items:center;background:#EAF1FF;position:relative;overflow:hidden;padding:120px 24px 60px}
.h2-hero::before{content:'';position:absolute;inset:-6%;z-index:0;background:url('<?php echo $hero_bg_url; ?>') no-repeat right center/cover;animation:h2BgFloat 10s ease-in-out infinite;will-change:transform}
/* Thu nhỏ ảnh cho vừa khung desktop hơn: scale nghỉ 0.92 thay vì 1 — vẫn an toàn không
   lộ mép vì khung ::before đã to hơn container 6% mỗi cạnh (112%), 112%×0.92≈103%
   vẫn thừa để phủ hết + còn dư biên cho phần trôi nổi translate. */
@keyframes h2BgFloat{0%,100%{transform:translate(0,0) scale(.92)}50%{transform:translate(-10px,-14px) scale(.935)}}
@media(prefers-reduced-motion:reduce){.h2-hero::before{animation:none}}
/* Ảnh thu nhỏ nên lộ dải nền xanh đặc ở đáy ảnh gốc — phủ gradient mờ dần sang màu
   nền của section kế tiếp (.ln-features:#F8FAFC) để chuyển tiếp mượt, không còn viền cứng. */
.h2-hero::after{content:'';position:absolute;left:0;right:0;bottom:0;height:160px;z-index:1;background:linear-gradient(180deg,transparent,#F8FAFC);pointer-events:none}
.h2-hero-grid{position:relative;z-index:2;max-width:1280px;margin:0 auto;width:100%;display:grid;grid-template-columns:1fr;gap:40px;align-items:center}
.h2-left{max-width:620px}

.h2-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:clamp(30px,4vw,48px);line-height:1.2;color:#0F172A;margin-bottom:20px}
.h2-title .hl{color:#2563EB}
.h2-sub{font-size:16px;color:#475569;line-height:1.7;margin-bottom:28px;max-width:480px}
/* WordPress tự chuyển emoji unicode thành <img class="emoji"> — ép về đúng cỡ chữ, tránh hiện to bất thường */
img.emoji{height:1em!important;width:1em!important;margin:0 .05em 0 .1em!important;vertical-align:-.1em!important;display:inline-block!important;background:none!important;border:none!important;padding:0!important;box-shadow:none!important;border-radius:0!important}

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

@media(max-width:960px){
    .h2-hero-grid{text-align:center}
    .h2-left{margin-left:auto;margin-right:auto}
    .h2-sub{margin-left:auto;margin-right:auto}
    .h2-pills,.h2-cta-row{justify-content:center}
    .h2-social-text{text-align:left}
    /* Màn hẹp: ảnh nền bị crop/zoom mạnh (cover + neo phải) nên minh hoạ dồn
       ngay sau chữ, dễ rối mắt — phủ thêm lớp trắng mờ dần lên trên ::before
       để chữ vẫn rõ, không đổi ảnh/nội dung, chỉ thêm 1 lớp gradient lên nền. */
    .h2-hero::before{background-image:linear-gradient(180deg,rgba(248,250,255,.9),rgba(248,250,255,.6) 50%,rgba(248,250,255,.3)),url('<?php echo $hero_bg_url; ?>')}
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
                    Bắt đầu miễn phí
                    <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
                <div class="h2-social">
                    <div class="h2-avatars"><span>A</span><span>B</span><span>C</span></div>
                    <div class="h2-social-text"><strong>10.000+ người dùng</strong><br>đang tin tưởng</div>
                </div>
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
