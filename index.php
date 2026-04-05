<?php
/**
 * LinkNgon V2 - Homepage
 * Nền tảng rút gọn link kiếm tiền & mua traffic website
 */
get_header();

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Public stats
$wpdb->suppress_errors( true );
$total_links  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}user_shortlinks" );
$total_clicks = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE step = 'verified'" );
$total_earned = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM {$prefix}transactions WHERE type = 'shortlink_reward'" );
$total_users  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
$wpdb->suppress_errors( false );

$nonce     = wp_create_nonce( 'linkngon_nonce' );
$is_logged = is_user_logged_in();

// Reward rates from settings
$rate_keyword = (int) linkngon_get_option( 'keyword_user_1step', 800 );
$rate_direct  = (int) linkngon_get_option( 'direct_user_1step', 500 );
$rate_social  = (int) linkngon_get_option( 'social_user_1step', 700 );
$min_withdraw = (int) linkngon_get_option( 'min_withdrawal', 50000 );
$ref_enabled  = linkngon_get_option( 'referral_enabled', 0 );
$ref_pct      = (int) linkngon_get_option( 'referral_commission_percent', 20 );
?>
<style>
/* ── Hero ── */
.ln-hero{background:linear-gradient(135deg,#083838 0%,#0D4F4F 40%,#1A7A7A 100%);color:#fff;padding:80px 24px 60px;text-align:center;position:relative;overflow:hidden}
.ln-hero::before{content:'';position:absolute;width:500px;height:500px;border-radius:50%;background:rgba(232,168,56,.06);top:-200px;right:-150px}
.ln-hero::after{content:'';position:absolute;width:350px;height:350px;border-radius:50%;background:rgba(232,168,56,.04);bottom:-150px;left:-100px}
.ln-hero *{position:relative;z-index:1}
.ln-hero h1{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:44px;color:#fff;margin-bottom:12px;line-height:1.2}
.ln-hero h1 span{color:#E8A838}
.ln-hero .subtitle{font-size:17px;color:rgba(255,255,255,.65);max-width:600px;margin:0 auto 36px;line-height:1.7}

/* ── Shorten Box ── */
.ln-shorten-box{max-width:680px;margin:0 auto}
.ln-shorten-form{display:flex;gap:0;background:rgba(255,255,255,.12);backdrop-filter:blur(10px);border-radius:16px;padding:6px;border:1px solid rgba(255,255,255,.15)}
.ln-shorten-form input{flex:1;padding:16px 20px;background:rgba(255,255,255,.95);border:none;border-radius:12px;font-family:'Inter',sans-serif;font-size:15px;color:#2C2C3A;outline:none}
.ln-shorten-form input::placeholder{color:#9CA3AF}
.ln-shorten-form button{padding:16px 32px;background:#E8A838;color:#083838;border:none;border-radius:12px;font-family:'Inter',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:all .25s;white-space:nowrap;margin-left:6px}
.ln-shorten-form button:hover{background:#F0C060;transform:scale(1.02)}
.ln-shorten-note{font-size:12px;color:rgba(255,255,255,.45);margin-top:12px}
.ln-shorten-note a{color:#E8A838}

/* Result */
.ln-result{display:none;margin-top:20px;background:rgba(255,255,255,.1);backdrop-filter:blur(10px);border-radius:14px;padding:20px;border:1px solid rgba(255,255,255,.12)}
.ln-result-url{display:flex;align-items:center;gap:10px}
.ln-result-url input{flex:1;padding:14px 16px;background:rgba(255,255,255,.95);border:2px solid #E8A838;border-radius:10px;font-family:'JetBrains Mono',monospace;font-size:14px;color:#0D4F4F;font-weight:600}
.ln-result-url button{padding:14px 20px;background:#059669;color:#fff;border:none;border-radius:10px;font-weight:700;cursor:pointer;font-family:'Inter',sans-serif;transition:all .2s}
.ln-result-url button:hover{background:#047857}
.ln-result-stats{display:flex;gap:24px;margin-top:14px;font-size:13px;color:rgba(255,255,255,.6)}

/* ── Counter Stats ── */
.ln-counters{display:flex;justify-content:center;gap:48px;margin-top:48px;flex-wrap:wrap}
.ln-counter{text-align:center}
.ln-counter-value{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:32px;color:#E8A838}
.ln-counter-label{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.5);margin-top:2px}

/* ── How it works ── */
.ln-how{padding:80px 24px;background:#F7F5F0}
.ln-section-title{text-align:center;margin-bottom:48px}
.ln-section-title h2{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:34px;color:#083838;margin-bottom:8px}
.ln-section-title p{color:#6B7280;font-size:15px}
.ln-steps{display:flex;gap:32px;max-width:1000px;margin:0 auto;justify-content:center;flex-wrap:wrap}
.ln-step{flex:1;min-width:200px;max-width:280px;text-align:center;position:relative}
.ln-step-num{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#0D4F4F,#1A7A7A);color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:24px;margin:0 auto 16px;box-shadow:0 4px 14px rgba(13,79,79,.2)}
.ln-step h3{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:18px;color:#083838;margin-bottom:6px}
.ln-step p{font-size:13px;color:#6B7280;line-height:1.6}
.ln-step-arrow{position:absolute;right:-20px;top:28px;color:#D1CEC7}.ln-step-arrow svg{width:20px;height:20px}

/* ── Earnings ── */
.ln-earnings{padding:80px 24px;background:#fff}
.ln-earn-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;max-width:900px;margin:0 auto}
.ln-earn-card{border:1px solid #F0EDE6;border-radius:14px;padding:28px;text-align:center;transition:all .3s}
.ln-earn-card:hover{box-shadow:0 6px 20px rgba(0,0,0,.05);transform:translateY(-2px)}
.ln-earn-card.featured{border-color:#E8A838;background:linear-gradient(135deg,#FFF9E6,#FFF5D6);position:relative}
.ln-earn-card.featured::before{content:'Phổ biến';position:absolute;top:12px;right:12px;background:#E8A838;color:#083838;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px}
.ln-earn-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px}
.ln-earn-type{font-weight:700;color:#083838;font-size:15px;margin-bottom:4px}
.ln-earn-price{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:28px;color:#0D4F4F}
.ln-earn-unit{font-size:12px;color:#9CA3AF;margin-top:2px}
.ln-earn-desc{font-size:12px;color:#6B7280;margin-top:8px;line-height:1.5}

/* ── Features ── */
.ln-features{padding:80px 24px;background:#F7F5F0}
.ln-feat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;max-width:1000px;margin:0 auto}
.ln-feat{background:#fff;border-radius:14px;padding:28px;border:1px solid #F0EDE6;text-align:center;transition:all .3s}
.ln-feat:hover{transform:translateY(-3px);box-shadow:0 6px 20px rgba(0,0,0,.04)}
.ln-feat-icon{margin-bottom:16px;display:flex;justify-content:center}.ln-feat-icon svg{width:40px;height:40px}
.ln-feat h3{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:18px;color:#083838;margin-bottom:6px}
.ln-feat p{font-size:13px;color:#6B7280;line-height:1.6}

/* ── For Advertisers ── */
.ln-adv{padding:80px 24px;background:#fff}
.ln-adv-wrap{max-width:900px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center}
.ln-adv-content h2{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:32px;color:#083838;margin-bottom:12px;line-height:1.2}
.ln-adv-content p{color:#6B7280;font-size:14px;line-height:1.7;margin-bottom:16px}
.ln-adv-list{list-style:none;padding:0;margin:0 0 24px}
.ln-adv-list li{display:flex;align-items:flex-start;gap:10px;margin-bottom:10px;font-size:14px;color:#2C2C3A}
.ln-adv-list li svg{flex-shrink:0;margin-top:2px;color:#059669}
.ln-adv-visual{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.ln-adv-stat{background:#F7F5F0;border-radius:12px;padding:20px;text-align:center}
.ln-adv-stat-val{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:24px;color:#0D4F4F}
.ln-adv-stat-lbl{font-size:11px;color:#6B7280;text-transform:uppercase;letter-spacing:.05em;margin-top:4px}

/* ── Referral ── */
.ln-referral{padding:60px 24px;background:linear-gradient(135deg,#083838,#0D4F4F);color:#fff;text-align:center}
.ln-referral h2{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:32px;color:#fff;margin-bottom:10px}
.ln-referral p{color:rgba(255,255,255,.6);font-size:15px;margin-bottom:24px;max-width:500px;margin-left:auto;margin-right:auto}
.ln-referral-highlight{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:48px;color:#E8A838;margin-bottom:8px}

/* ── CTA ── */
.ln-cta{padding:80px 24px;background:#F7F5F0;text-align:center}
.ln-cta h2{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:32px;color:#083838;margin-bottom:8px}
.ln-cta p{color:#6B7280;font-size:15px;margin-bottom:28px;max-width:500px;margin-left:auto;margin-right:auto}
.ln-cta-btn{display:inline-flex;align-items:center;padding:14px 36px;background:#E8A838;color:#083838;border-radius:12px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:15px;text-decoration:none;transition:all .25s}
.ln-cta-btn:hover{background:#F0C060;transform:translateY(-2px)}
.ln-cta-btn-alt{display:inline-flex;align-items:center;padding:14px 36px;background:#0D4F4F;color:#fff;border-radius:12px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:15px;text-decoration:none;transition:all .25s;margin-left:12px}
.ln-cta-btn-alt:hover{background:#1A7A7A;transform:translateY(-2px)}

/* ── Responsive ── */
@media(max-width:768px){
    .ln-hero h1{font-size:30px}
    .ln-hero .subtitle{font-size:15px}
    .ln-shorten-form input{min-width:0;padding:14px 14px;font-size:14px}
    .ln-shorten-form button{padding:14px 20px;font-size:14px;margin-left:4px}
    .ln-feat-grid,.ln-earn-grid{grid-template-columns:1fr}
    .ln-counters{gap:24px}
    .ln-counter-value{font-size:24px}
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
    <h1>Rút gọn link.<br><span>Kiếm tiền thật.</span></h1>
    <p class="subtitle">Dán link dài, nhận shortlink ngắn gọn. Mỗi lượt truy cập hợp lệ qua shortlink, bạn được thanh toán lên đến <strong style="color:#E8A838"><?php echo linkngon_format_money( $rate_keyword ); ?>/lượt</strong>.</p>

    <div class="ln-shorten-box">
        <div class="ln-shorten-form" id="shortenForm">
            <input type="url" id="longUrl" placeholder="Dán link cần rút gọn tại đây..." autocomplete="off">
            <button onclick="shortenLink()">Rút gọn</button>
        </div>
        <p class="ln-shorten-note">
            Miễn phí, không giới hạn.
            <?php if ( ! $is_logged ) : ?>
                <a href="<?php echo home_url('/dang-ky'); ?>">Đăng ký</a> để quản lý link & rút tiền.
            <?php endif; ?>
        </p>

        <!-- Result -->
        <div class="ln-result" id="shortenResult">
            <div class="ln-result-url">
                <input type="text" id="shortUrlOutput" readonly>
                <button onclick="copyShortlink()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>Copy</button>
            </div>
            <div class="ln-result-stats">
                <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><path d="M20 6L9 17l-5-5"/></svg>Link đã sẵn sàng</span>
                <span id="resultExtra"></span>
            </div>
        </div>
    </div>

    <!-- Counters -->
    <div class="ln-counters">
        <div class="ln-counter">
            <div class="ln-counter-value"><?php echo number_format( $total_links ); ?></div>
            <div class="ln-counter-label">Links tạo</div>
        </div>
        <div class="ln-counter">
            <div class="ln-counter-value"><?php echo number_format( $total_clicks ); ?></div>
            <div class="ln-counter-label">Lượt hoàn thành</div>
        </div>
        <div class="ln-counter">
            <div class="ln-counter-value"><?php echo number_format( $total_users ); ?></div>
            <div class="ln-counter-label">Thành viên</div>
        </div>
        <div class="ln-counter">
            <div class="ln-counter-value"><?php echo linkngon_format_money( $total_earned ); ?></div>
            <div class="ln-counter-label">Đã thanh toán</div>
        </div>
    </div>
</section>

<!-- ═══ HOW IT WORKS ═══ -->
<section class="ln-how">
    <div class="ln-section-title">
        <h2>Cách hoạt động</h2>
        <p>3 bước đơn giản, bắt đầu kiếm tiền ngay hôm nay</p>
    </div>
    <div class="ln-steps">
        <div class="ln-step">
            <div class="ln-step-num">1</div>
            <h3>Rút gọn link</h3>
            <p>Dán bất kỳ link nào vào ô phía trên. Hệ thống tạo shortlink ngắn gọn, dễ chia sẻ trong vài giây.</p>
            <span class="ln-step-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
        <div class="ln-step">
            <div class="ln-step-num">2</div>
            <h3>Chia sẻ khắp nơi</h3>
            <p>Đăng shortlink lên Facebook, YouTube, blog, forum, TikTok... Càng nhiều người click, bạn càng kiếm được nhiều.</p>
            <span class="ln-step-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
        <div class="ln-step">
            <div class="ln-step-num">3</div>
            <h3>Nhận tiền</h3>
            <p>Mỗi lượt truy cập hợp lệ đều được tính tiền. Rút về tài khoản ngân hàng khi đạt <?php echo linkngon_format_money( $min_withdraw ); ?>.</p>
        </div>
    </div>
</section>

<!-- ═══ EARNINGS ═══ -->
<section class="ln-earnings">
    <div class="ln-section-title">
        <h2>Mức thanh toán</h2>
        <p>Nhận tiền cho mỗi lượt truy cập hợp lệ qua shortlink của bạn</p>
    </div>
    <div class="ln-earn-grid">
        <div class="ln-earn-card featured">
            <div class="ln-earn-icon" style="background:#EBF5FF"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
            <div class="ln-earn-type">Keyword Search</div>
            <div class="ln-earn-price"><?php echo linkngon_format_money( $rate_keyword ); ?></div>
            <div class="ln-earn-unit">mỗi lượt hoàn thành</div>
            <div class="ln-earn-desc">Người truy cập tìm từ khóa trên Google và truy cập website mục tiêu</div>
        </div>
        <div class="ln-earn-card">
            <div class="ln-earn-icon" style="background:#F0FDF4"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></div>
            <div class="ln-earn-type">Traffic Social</div>
            <div class="ln-earn-price"><?php echo linkngon_format_money( $rate_social ); ?></div>
            <div class="ln-earn-unit">mỗi lượt hoàn thành</div>
            <div class="ln-earn-desc">Người truy cập click link trực tiếp từ mạng xã hội đến website</div>
        </div>
        <div class="ln-earn-card">
            <div class="ln-earn-icon" style="background:#FEF2F2"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></div>
            <div class="ln-earn-type">Traffic Direct</div>
            <div class="ln-earn-price"><?php echo linkngon_format_money( $rate_direct ); ?></div>
            <div class="ln-earn-unit">mỗi lượt hoàn thành</div>
            <div class="ln-earn-desc">Người truy cập vào trực tiếp website mục tiêu qua link</div>
        </div>
    </div>
</section>

<!-- ═══ FEATURES ═══ -->
<section class="ln-features">
    <div class="ln-section-title">
        <h2>Tại sao chọn LinkNgon?</h2>
        <p>Nền tảng rút gọn link kiếm tiền hàng đầu Việt Nam</p>
    </div>
    <div class="ln-feat-grid">
        <div class="ln-feat">
            <div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <h3>Thanh toán nhanh</h3>
            <p>Rút tiền tối thiểu <?php echo linkngon_format_money( $min_withdraw ); ?>. Chuyển khoản ngân hàng hoặc USDT nhanh chóng.</p>
        </div>
        <div class="ln-feat">
            <div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></div>
            <h3>Thống kê realtime</h3>
            <p>Theo dõi clicks, lượt hoàn thành, thu nhập chi tiết theo thời gian thực trên dashboard.</p>
        </div>
        <div class="ln-feat">
            <div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><circle cx="12" cy="16" r="1"/></svg></div>
            <h3>Chống gian lận</h3>
            <p>Hệ thống phát hiện fraud, VPN, bot tự động. Đảm bảo traffic chất lượng cho nhà quảng cáo.</p>
        </div>
        <div class="ln-feat">
            <div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></div>
            <h3>Tạo link tức thì</h3>
            <p>Không giới hạn số lượng link. Tạo shortlink trong vài giây, chia sẻ ngay lập tức.</p>
        </div>
        <div class="ln-feat">
            <div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div>
            <h3>Mọi thiết bị</h3>
            <p>Shortlink hoạt động trên điện thoại, máy tính, tablet. Dashboard responsive dễ dùng.</p>
        </div>
        <?php if ( $ref_enabled ) : ?>
        <div class="ln-feat">
            <div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
            <h3>Giới thiệu <?php echo $ref_pct; ?>%</h3>
            <p>Mời bạn bè tham gia và nhận <?php echo $ref_pct; ?>% hoa hồng từ thu nhập của họ.</p>
        </div>
        <?php else : ?>
        <div class="ln-feat">
            <div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
            <h3>Link an toàn</h3>
            <p>Chống spam, malware. Shortlink luôn hoạt động ổn định, không lo bị chặn hay gián đoạn.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ═══ FOR ADVERTISERS ═══ -->
<section class="ln-adv">
    <div class="ln-adv-wrap">
        <div class="ln-adv-content">
            <h2>Dành cho nhà quảng cáo</h2>
            <p>Tăng traffic thật cho website của bạn với chi phí hợp lý. Người dùng thực sự truy cập và tương tác với trang web của bạn.</p>
            <ul class="ln-adv-list">
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Traffic từ Google Search, mạng xã hội, truy cập trực tiếp</li>
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Xác minh người dùng thực, chống bot và VPN</li>
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Kiểm soát ngân sách, chỉ trả tiền khi có lượt truy cập thật</li>
                <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>Tùy chỉnh số lượng traffic mỗi ngày, tự động phân phối đều</li>
            </ul>
            <a href="<?php echo $is_logged ? home_url('/khach-hang') : home_url('/dang-ky'); ?>" class="ln-cta-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                Tạo chiến dịch ngay
            </a>
        </div>
        <div class="ln-adv-visual">
            <div class="ln-adv-stat">
                <div class="ln-adv-stat-val"><?php echo linkngon_format_money( (int) linkngon_get_option( 'keyword_price_1step', 1200 ) ); ?></div>
                <div class="ln-adv-stat-lbl">Chi phí / lượt keyword</div>
            </div>
            <div class="ln-adv-stat">
                <div class="ln-adv-stat-val"><?php echo linkngon_format_money( (int) linkngon_get_option( 'direct_price_1step', 1200 ) ); ?></div>
                <div class="ln-adv-stat-lbl">Chi phí / lượt direct</div>
            </div>
            <div class="ln-adv-stat">
                <div class="ln-adv-stat-val">100%</div>
                <div class="ln-adv-stat-lbl">Traffic thật</div>
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
    <p>Mời bạn bè đăng ký LinkNgon, nhận <?php echo $ref_pct; ?>% hoa hồng từ thu nhập của họ.</p>
    <?php if ( $is_logged ) : ?>
        <a href="<?php echo home_url( '/nguoi-dung' ); ?>" class="ln-cta-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Lấy link giới thiệu</a>
    <?php else : ?>
        <a href="<?php echo home_url('/dang-ky'); ?>" class="ln-cta-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>Đăng ký ngay</a>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- ═══ FINAL CTA ═══ -->
<section class="ln-cta">
    <h2>Bắt đầu kiếm tiền ngay hôm nay</h2>
    <p>Đăng ký miễn phí, tạo shortlink đầu tiên trong vài giây và bắt đầu kiếm tiền từ mỗi lượt truy cập.</p>
    <?php if ( $is_logged ) : ?>
        <a href="<?php echo home_url( '/nguoi-dung' ); ?>" class="ln-cta-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Vào Dashboard</a>
    <?php else : ?>
        <a href="<?php echo home_url('/dang-ky'); ?>" class="ln-cta-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>Đăng ký miễn phí</a>
        <a href="<?php echo home_url('/khach-hang'); ?>" class="ln-cta-btn-alt"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px"><path d="M12 5v14"/><path d="M5 12h14"/></svg>Mua traffic</a>
    <?php endif; ?>
</section>

<script>
function shortenLink() {
    var url = document.getElementById('longUrl').value.trim();
    if (!url) { alert('Vui lòng nhập link'); return; }
    if (!/^https?:\/\//i.test(url)) url = 'https://' + url;

    var btn = document.querySelector('.ln-shorten-form button');
    btn.textContent = 'Đang rút gọn...'; btn.disabled = true;

    var fd = new FormData();
    fd.append('action', 'linkngon_shorten_url');
    fd.append('nonce', '<?php echo $nonce; ?>');
    fd.append('url', url);

    fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(r) {
            btn.textContent = 'Rút gọn'; btn.disabled = false;
            if (r.success) {
                document.getElementById('shortUrlOutput').value = r.data.short_url;
                document.getElementById('shortenResult').style.display = 'block';
                document.getElementById('resultExtra').textContent = <?php echo $is_logged ? "'Chia sẻ link này để kiếm tiền!'" : "'Đăng ký để theo dõi thu nhập'" ?>;
            } else {
                alert(r.data || 'Lỗi, thử lại');
            }
        })
        .catch(function() { btn.textContent = 'Rút gọn'; btn.disabled = false; alert('Lỗi kết nối'); });
}

function copyShortlink() {
    var input = document.getElementById('shortUrlOutput');
    input.select();
    navigator.clipboard.writeText(input.value).then(function() {
        var btn = input.nextElementSibling;
        btn.innerHTML = 'Copied!';
        setTimeout(function() { btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>Copy'; }, 2000);
    });
}
</script>

<?php get_footer(); ?>
