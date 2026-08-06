<?php
/**
 * SiteTop.net V2 - Homepage
 * Nền tảng rút gọn link kiếm tiền & mua traffic website
 * Updated: 2026-04-05
 */
get_header();

global $wpdb;
$prefix = $wpdb->prefix . 'sitetop_';


$nonce     = wp_create_nonce( 'sitetop_nonce' );
$is_logged = is_user_logged_in();

// Reward rates from settings - lấy giá cao nhất mỗi loại
$rate_keyword = max(
    (int) sitetop_get_option( 'keyword_user_1step', 800 ),
    (int) sitetop_get_option( 'keyword_user_2step', 1000 ),
    (int) sitetop_get_option( 'keyword_user_nocode', 800 )
);
$rate_direct = max(
    (int) sitetop_get_option( 'direct_user_1step', 500 ),
    (int) sitetop_get_option( 'direct_user_2step', 700 ),
    (int) sitetop_get_option( 'direct_user_nocode', 800 )
);
$min_withdraw = (int) sitetop_get_option( 'min_withdrawal', 50000 );
$ref_enabled  = sitetop_get_option( 'referral_enabled', 0 );
$ref_pct      = (int) sitetop_get_option( 'referral_commission_percent', 20 );
?>
<style>
/* ── Hero ── */
.ln-hero{background:linear-gradient(160deg,#F7F9FE 0%,#EDF2FC 55%,#E3EAFA 100%);color:#0F172A;padding:80px 24px 60px;text-align:center;position:relative;overflow:hidden}
.ln-hero::before{content:'';position:absolute;width:260px;height:260px;background-image:radial-gradient(circle,rgba(59,102,177,.22) 1.6px,transparent 1.6px);background-size:22px 22px;top:36px;left:-40px;opacity:.9;transform:rotate(-6deg);z-index:1}
.ln-hero::after{content:'';position:absolute;width:220px;height:220px;background-image:radial-gradient(circle,rgba(59,102,177,.18) 1.6px,transparent 1.6px);background-size:22px 22px;top:70px;right:-20px;opacity:.75;transform:rotate(8deg);z-index:1}
.ln-hero-waves{position:absolute;left:0;right:0;bottom:0;height:240px;z-index:1;pointer-events:none;overflow:hidden}
.ln-hero-waves span{position:absolute;left:-15%;right:-15%;height:280px;border-radius:50%}
.ln-hero-waves span:nth-child(1){background:#E3EAFA;bottom:-170px;opacity:.9}
.ln-hero-waves span:nth-child(2){background:#D2E0F7;bottom:-190px;left:0;right:-25%;opacity:.85}
.ln-hero-waves span:nth-child(3){background:#BFD2F3;bottom:-210px;left:-25%;right:5%;opacity:.85}
.ln-hero>*:not(.ln-hero-waves){position:relative;z-index:2}
.ln-hero h1{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:44px;color:#0F172A;margin-bottom:12px;line-height:1.2}
.ln-hero h1 span{color:#2563EB}
.ln-hero .subtitle{font-size:17px;color:#64748B;max-width:600px;margin:0 auto 36px;line-height:1.7}

/* ── Shorten Box ── */
.ln-shorten-box{max-width:680px;margin:0 auto}
.ln-shorten-form{display:flex;gap:0;background:#fff;border-radius:16px;padding:6px;border:1px solid #E2E8F0;box-shadow:0 12px 30px rgba(30,64,150,.12)}
.ln-shorten-form input{flex:1;padding:16px 20px;background:transparent;border:none;border-radius:12px;font-family:'Inter',sans-serif;font-size:15px;color:#1E293B;outline:none}
.ln-shorten-form input::placeholder{color:#94A3B8}
.ln-shorten-form button{padding:16px 32px;background:#3B82F6;color:#fff;border:none;border-radius:12px;font-family:'Inter',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:all .25s;white-space:nowrap;margin-left:6px}
.ln-shorten-form button:hover{background:#2563EB;transform:scale(1.02)}
.ln-shorten-note{font-size:12px;color:#64748B;margin-top:12px}
.ln-shorten-note a{color:#2563EB}

/* Result */
.ln-result{display:none;margin-top:20px;background:#fff;border-radius:14px;padding:20px;border:1px solid #E2E8F0;box-shadow:0 8px 24px rgba(30,64,150,.1)}
.ln-result-url{display:flex;align-items:center;gap:10px}
.ln-result-url input{flex:1;padding:14px 16px;background:#F8FAFC;border:2px solid #3B82F6;border-radius:10px;font-family:'JetBrains Mono',monospace;font-size:14px;color:#1E293B;font-weight:600}
.ln-result-url button{padding:14px 20px;background:#059669;color:#fff;border:none;border-radius:10px;font-weight:700;cursor:pointer;font-family:'Inter',sans-serif;transition:all .2s}
.ln-result-url button:hover{background:#047857}
.ln-result-stats{display:flex;gap:24px;margin-top:14px;font-size:13px;color:#64748B}

/* ── Counter Stats ── */

/* ── How it works ── */
.ln-how{padding:80px 24px;background:#F8FAFC}
.ln-section-title{text-align:center;margin-bottom:48px}
.ln-section-title h2{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:34px;color:#0F172A;margin-bottom:8px}
.ln-section-title p{color:#64748B;font-size:15px}
.ln-steps{display:flex;gap:32px;max-width:1000px;margin:0 auto;justify-content:center;flex-wrap:wrap}
.ln-step{flex:1;min-width:200px;max-width:280px;text-align:center;position:relative}
.ln-step-num{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#3B82F6,#6366F1);color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:24px;margin:0 auto 16px;box-shadow:0 4px 14px rgba(59,130,246,.3)}
.ln-step h3{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:18px;color:#0F172A;margin-bottom:6px}
.ln-step p{font-size:13px;color:#64748B;line-height:1.6}
.ln-step-arrow{position:absolute;right:-20px;top:28px;color:#CBD5E1}.ln-step-arrow svg{width:20px;height:20px}

/* ── Earnings ── */
.ln-earnings{padding:80px 24px;background:#fff}
.ln-earn-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;max-width:700px;margin:0 auto}
.ln-earn-card{border:1px solid #E2E8F0;border-radius:14px;padding:28px;text-align:center;transition:all .3s}
.ln-earn-card:hover{box-shadow:0 6px 20px rgba(0,0,0,.06);transform:translateY(-2px)}
.ln-earn-card.featured{border-color:#3B82F6;background:linear-gradient(135deg,#EFF6FF,#DBEAFE);position:relative}
.ln-earn-card.featured::before{content:'Phổ biến';position:absolute;top:12px;right:12px;background:#3B82F6;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px}
.ln-earn-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px}
.ln-earn-type{font-weight:700;color:#0F172A;font-size:15px;margin-bottom:4px}
.ln-earn-price{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:28px;color:#1D4ED8}
.ln-earn-unit{font-size:12px;color:#94A3B8;margin-top:2px}
.ln-earn-desc{font-size:12px;color:#64748B;margin-top:8px;line-height:1.5}

/* ── Features ── */
.ln-features{padding:80px 24px;background:#F8FAFC}
.ln-feat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;max-width:1000px;margin:0 auto}
.ln-feat{background:#fff;border-radius:14px;padding:28px;border:1px solid #E2E8F0;text-align:center;transition:all .3s}
.ln-feat:hover{transform:translateY(-3px);box-shadow:0 6px 20px rgba(0,0,0,.05)}
.ln-feat-icon{margin-bottom:16px;display:flex;justify-content:center}.ln-feat-icon svg{width:40px;height:40px}
.ln-feat h3{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:18px;color:#0F172A;margin-bottom:6px}
.ln-feat p{font-size:13px;color:#64748B;line-height:1.6}

/* ── For Advertisers ── */
.ln-adv{padding:80px 24px;background:#fff}
.ln-adv-wrap{max-width:900px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center}
.ln-adv-content h2{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:32px;color:#0F172A;margin-bottom:12px;line-height:1.2}
.ln-adv-content p{color:#64748B;font-size:14px;line-height:1.7;margin-bottom:16px}
.ln-adv-list{list-style:none;padding:0;margin:0 0 24px}
.ln-adv-list li{display:flex;align-items:flex-start;gap:10px;margin-bottom:10px;font-size:14px;color:#334155}
.ln-adv-list li svg{flex-shrink:0;margin-top:2px;color:#3B82F6}
.ln-adv-visual{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.ln-adv-stat{background:#F8FAFC;border-radius:12px;padding:20px;text-align:center;border:1px solid #E2E8F0}
.ln-adv-stat-val{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:24px;color:#1D4ED8}
.ln-adv-stat-lbl{font-size:11px;color:#64748B;text-transform:uppercase;letter-spacing:.05em;margin-top:4px}

/* ── Referral ── */
.ln-referral{padding:60px 24px;background:linear-gradient(135deg,#0F172A,#1E293B);color:#fff;text-align:center}
.ln-referral h2{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:32px;color:#fff;margin-bottom:10px}
.ln-referral p{color:rgba(255,255,255,.6);font-size:15px;margin-bottom:24px;max-width:500px;margin-left:auto;margin-right:auto}
.ln-referral-highlight{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:48px;color:#60A5FA;margin-bottom:8px}

/* ── FAQ ── */
.ln-faq{padding:80px 24px;background:#F8FAFC}
.ln-faq-list{max-width:700px;margin:0 auto}
.ln-faq-item{border-bottom:1px solid #E2E8F0;overflow:hidden}
.ln-faq-item summary{padding:18px 0;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:15px;color:#0F172A;cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;gap:12px}
.ln-faq-item summary::-webkit-details-marker{display:none}
.ln-faq-item summary::after{content:'+';font-size:22px;font-weight:400;color:#94A3B8;flex-shrink:0;transition:transform .2s}
.ln-faq-item[open] summary::after{content:'-'}
.ln-faq-answer{padding:0 0 18px;font-size:14px;color:#64748B;line-height:1.7}

/* ── CTA ── */
.ln-cta{padding:80px 24px;background:#fff;text-align:center}
.ln-cta h2{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:32px;color:#0F172A;margin-bottom:8px}
.ln-cta p{color:#64748B;font-size:15px;margin-bottom:28px;max-width:500px;margin-left:auto;margin-right:auto}
.ln-cta-btn{display:inline-flex;align-items:center;padding:14px 36px;background:#3B82F6;color:#fff;border-radius:12px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:15px;text-decoration:none;transition:all .25s}
.ln-cta-btn:hover{background:#2563EB;transform:translateY(-2px)}
.ln-cta-btn-alt{display:inline-flex;align-items:center;padding:14px 36px;background:#0F172A;color:#fff;border-radius:12px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:15px;text-decoration:none;transition:all .25s;margin-left:12px}
.ln-cta-btn-alt:hover{background:#1E293B;transform:translateY(-2px)}

/* ── Responsive ── */
@media(max-width:768px){
    .ln-hero h1{font-size:30px}
    .ln-hero .subtitle{font-size:15px}
    .ln-shorten-form input{min-width:0;padding:14px 14px;font-size:14px}
    .ln-shorten-form button{padding:14px 20px;font-size:14px;margin-left:4px}
    .ln-feat-grid,.ln-earn-grid{grid-template-columns:1fr}
.ln-steps{flex-direction:column;align-items:center}
    .ln-step-arrow{display:none}
    .ln-adv-wrap{grid-template-columns:1fr}
    .ln-adv-visual{grid-template-columns:1fr 1fr}
    .ln-cta-btn-alt{margin-left:0;margin-top:12px}
    .ln-section-title h2{font-size:26px}
    .ln-earn-price{font-size:24px}
}
</style>

<!-- ═══ HERO + SHORTEN BOX ═══ -->
<section class="ln-hero">
    <div class="ln-hero-waves"><span></span><span></span><span></span></div>
    <h1>Nền tảng Traffic User<br><span>cho doanh nghiệp.</span></h1>
    <p class="subtitle">SiteTop.net kết nối người cung cấp traffic và doanh nghiệp cần đẩy từ khóa lên top Google. Traffic thật từ người dùng thực, giúp tăng thứ hạng SEO hiệu quả và bền vững.</p>

    <div class="ln-shorten-box">
        <div class="ln-shorten-form" id="shortenForm">
            <input type="url" id="longUrl" placeholder="Dán link cần rút gọn tại đây..." autocomplete="off" readonly onclick="goShorten()" onfocus="goShorten()">
            <button onclick="goShorten()">Rút gọn</button>
        </div>
        <p class="ln-shorten-note">
            Miễn phí, không giới hạn.
            <?php if ( ! $is_logged ) : ?>
                <a href="<?php echo home_url('/dang-ky'); ?>">Đăng ký</a> để quản lý link & rút tiền.
            <?php endif; ?>
        </p>
    </div>
</section>

<!-- ═══ HOW IT WORKS ═══ -->
<section class="ln-how">
    <div class="ln-section-title">
        <h2>Cách hoạt động</h2>
        <p>SiteTop.net là cầu nối giữa người cung cấp traffic và doanh nghiệp cần SEO</p>
    </div>
    <div class="ln-steps">
        <div class="ln-step">
            <div class="ln-step-num">1</div>
            <h3>Doanh nghiệp tạo chiến dịch</h3>
            <p>Nhà quảng cáo đặt từ khóa SEO, URL đích và số lượng traffic mong muốn mỗi ngày.</p>
            <span class="ln-step-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
        <div class="ln-step">
            <div class="ln-step-num">2</div>
            <h3>User thực hiện traffic</h3>
            <p>Người dùng tìm từ khóa trên Google, truy cập đúng website và ở lại trang đủ thời gian quy định.</p>
            <span class="ln-step-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
        <div class="ln-step">
            <div class="ln-step-num">3</div>
            <h3>Đôi bên cùng có lợi</h3>
            <p>Doanh nghiệp nhận traffic thật giúp tăng hạng SEO. User nhận thưởng cho mỗi lượt hoàn thành hợp lệ.</p>
        </div>
    </div>
</section>

<!-- ═══ EARNINGS ═══ -->
<section class="ln-earnings">
    <div class="ln-section-title">
        <h2>Mức thanh toán cho User</h2>
        <p>Nhận tiền cho mỗi lượt traffic hợp lệ bạn cung cấp</p>
    </div>
    <div class="ln-earn-grid">
        <div class="ln-earn-card featured">
            <div class="ln-earn-icon" style="background:#EBF5FF"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09A6.69 6.69 0 0 1 5.5 12c0-.72.12-1.42.35-2.09V7.07H2.18A11.1 11.1 0 0 0 1 12c0 1.78.42 3.47 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg></div>
            <div class="ln-earn-type">Traffic Keyword (SEO)</div>
            <div class="ln-earn-price"><?php echo sitetop_format_money( $rate_keyword * 1000 ); ?></div>
            <div class="ln-earn-unit">1.000 lượt hoàn thành</div>
            <div class="ln-earn-desc">Tìm từ khóa trên Google, truy cập website mục tiêu và ở lại đủ thời gian</div>
        </div>
        <div class="ln-earn-card">
            <div class="ln-earn-icon" style="background:#ECFDF5"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div>
            <div class="ln-earn-type">Traffic Direct</div>
            <div class="ln-earn-price"><?php echo sitetop_format_money( $rate_direct * 1000 ); ?></div>
            <div class="ln-earn-unit">1.000 lượt hoàn thành</div>
            <div class="ln-earn-desc">Truy cập trực tiếp website mục tiêu qua link và tương tác trên trang</div>
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

<!-- ═══ FOR ADVERTISERS ═══ -->
<section class="ln-adv">
    <div class="ln-adv-wrap">
        <div class="ln-adv-content">
            <h2>Dành cho doanh nghiệp cần SEO</h2>
            <p>Đẩy từ khóa lên top Google với traffic thật từ người dùng thực. Chi phí hợp lý, hiệu quả đo lường được qua Google Search Console.</p>
            <ul class="ln-adv-list">
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Traffic keyword: User tìm từ khóa trên Google → click vào website của bạn</li>
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Tăng CTR (Click-Through Rate) tự nhiên → Google đánh giá cao hơn</li>
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Phân phối đều traffic trong ngày, không spam, không bị phạt</li>
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Chỉ trả tiền khi có lượt truy cập hợp lệ, kiểm soát ngân sách linh hoạt</li>
            </ul>
            <a href="<?php echo $is_logged ? home_url('/customer') : home_url('/dang-ky'); ?>" class="ln-cta-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                Tạo chiến dịch SEO ngay
            </a>
        </div>
        <div class="ln-adv-visual">
            <div class="ln-adv-stat">
                <div class="ln-adv-stat-val"><?php echo sitetop_format_money( (int) sitetop_get_option( 'keyword_price_1step', 1200 ) ); ?></div>
                <div class="ln-adv-stat-lbl">Chi phí / lượt keyword</div>
            </div>
            <div class="ln-adv-stat">
                <div class="ln-adv-stat-val"><?php echo sitetop_format_money( (int) sitetop_get_option( 'direct_price_1step', 1200 ) ); ?></div>
                <div class="ln-adv-stat-lbl">Chi phí / lượt direct</div>
            </div>
            <div class="ln-adv-stat">
                <div class="ln-adv-stat-val">100%</div>
                <div class="ln-adv-stat-lbl">Người dùng thực</div>
            </div>
            <div class="ln-adv-stat">
                <div class="ln-adv-stat-val">24/7</div>
                <div class="ln-adv-stat-lbl">Phân phối tự động</div>
            </div>
        </div>
    </div>
</section>

<?php if ( $ref_enabled ) : ?>
<!-- ═══ REFERRAL CTA ═══ -->
<section class="ln-referral">
    <div class="ln-referral-highlight"><?php echo $ref_pct; ?>%</div>
    <h2>Chương trình giới thiệu</h2>
    <p>Mời bạn bè đăng ký SiteTop.net, nhận <?php echo $ref_pct; ?>% hoa hồng từ thu nhập của họ.</p>
    <?php if ( $is_logged ) : ?>
        <a href="<?php echo home_url( '/user' ); ?>" class="ln-cta-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Lấy link giới thiệu</a>
    <?php else : ?>
        <a href="<?php echo home_url('/dang-ky'); ?>" class="ln-cta-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>Đăng ký ngay</a>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- ═══ FAQ ═══ -->
<section class="ln-faq">
    <div class="ln-section-title">
        <h2>Câu hỏi thường gặp</h2>
        <p>Những thắc mắc phổ biến về SiteTop.net</p>
    </div>
    <div class="ln-faq-list">
        <details class="ln-faq-item">
            <summary>SiteTop.net là gì?</summary>
            <div class="ln-faq-answer">SiteTop.net là nền tảng trung gian kết nối doanh nghiệp cần tăng traffic User với người dùng thực sẵn sàng cung cấp traffic. Doanh nghiệp tạo chiến dịch từ khóa, user thực hiện tìm kiếm và truy cập website, cả hai bên đều nhận được giá trị.</div>
        </details>
        <details class="ln-faq-item">
            <summary>Traffic keyword SEO hoạt động như thế nào?</summary>
            <div class="ln-faq-answer">Doanh nghiệp đặt từ khóa và URL đích. User sẽ search từ khóa đó trên Google, tìm và click vào website của bạn, sau đó ở lại trang một khoảng thời gian nhất định. Điều này giúp tăng CTR (Click-Through Rate) tự nhiên cho từ khóa, từ đó cải thiện thứ hạng trên Google.</div>
        </details>
        <details class="ln-faq-item">
            <summary>User kiếm tiền bằng cách nào?</summary>
            <div class="ln-faq-answer">User rút gọn link và chia sẻ. Khi có người truy cập qua shortlink, họ sẽ thực hiện tác vụ traffic (tìm từ khóa, truy cập website). Mỗi lượt hoàn thành hợp lệ, user nhận lên đến <?php echo sitetop_format_money( $rate_keyword ); ?>. Rút tiền tối thiểu <?php echo sitetop_format_money( $min_withdraw ); ?> qua ngân hàng hoặc USDT.</div>
        </details>
        <details class="ln-faq-item">
            <summary>Chi phí cho doanh nghiệp là bao nhiêu?</summary>
            <div class="ln-faq-answer">Traffic keyword từ <?php echo sitetop_format_money( (int) sitetop_get_option( 'keyword_price_1step', 1200 ) ); ?>/lượt, traffic direct từ <?php echo sitetop_format_money( (int) sitetop_get_option( 'direct_price_1step', 1200 ) ); ?>/lượt. Bạn chỉ trả tiền khi có lượt truy cập hợp lệ, kiểm soát được ngân sách và số traffic hàng ngày. Nên chạy liên tục 15-30 ngày để đạt hiệu quả tốt nhất.</div>
        </details>
        <details class="ln-faq-item">
            <summary>Làm sao đảm bảo traffic là người thật?</summary>
            <div class="ln-faq-answer">SiteTop.net sử dụng hệ thống chống gian lận đa lớp: phát hiện VPN/Proxy, fraud scoring (0-100 điểm), fingerprint thiết bị, giới hạn IP hàng ngày, xác minh hành vi trên trang. Mọi lượt truy cập không hợp lệ đều bị loại bỏ và không tính phí.</div>
        </details>
    </div>
</section>

<!-- ═══ FINAL CTA ═══ -->
<section class="ln-cta">
    <h2>Bắt đầu ngay hôm nay</h2>
    <p>Doanh nghiệp cần SEO? User muốn kiếm thêm thu nhập? Tham gia SiteTop.net ngay.</p>
    <?php if ( $is_logged ) : ?>
        <a href="<?php echo home_url( '/user' ); ?>" class="ln-cta-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Vào Dashboard</a>
    <?php else : ?>
        <a href="<?php echo home_url('/dang-ky'); ?>" class="ln-cta-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>Đăng ký kiếm tiền</a>
        <a href="<?php echo home_url('/customer'); ?>" class="ln-cta-btn-alt"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><path d="M12 5v14"/><path d="M5 12h14"/></svg>Mua traffic User</a>
    <?php endif; ?>
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
