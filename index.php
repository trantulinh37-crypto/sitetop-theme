<?php
/**
 * LinkNgon V2 - Homepage
 * Website rút gọn link kiếm tiền
 * Core: Ô paste link để rút gọn ngay
 */
get_header();

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

// Public stats (safe: suppress errors if tables not yet created)
$wpdb->suppress_errors( true );
$total_links = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}user_shortlinks" );
$total_clicks = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE step = 'verified'" );
$total_earned = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM {$prefix}transactions WHERE type = 'shortlink_reward'" );
$total_users = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
$wpdb->suppress_errors( false );

$nonce = wp_create_nonce( 'linkngon_nonce' );
$is_logged = is_user_logged_in();
?>
<style>
/* ── Hero ── */
.ln-hero{background:linear-gradient(135deg,#083838 0%,#0D4F4F 40%,#1A7A7A 100%);color:#fff;padding:80px 24px 60px;text-align:center;position:relative;overflow:hidden}
.ln-hero::before{content:'';position:absolute;width:500px;height:500px;border-radius:50%;background:rgba(232,168,56,.06);top:-200px;right:-150px}
.ln-hero::after{content:'';position:absolute;width:350px;height:350px;border-radius:50%;background:rgba(232,168,56,.04);bottom:-150px;left:-100px}
.ln-hero *{position:relative;z-index:1}
.ln-hero h1{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:44px;color:#fff;margin-bottom:12px;line-height:1.2}
.ln-hero h1 span{color:#E8A838}
.ln-hero .subtitle{font-size:17px;color:rgba(255,255,255,.65);max-width:560px;margin:0 auto 36px;line-height:1.7}

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

/* ── Payout rates ── */
.ln-rates{padding:80px 24px;background:#fff}
.ln-rates-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;max-width:900px;margin:0 auto}
.ln-rate-card{border:1px solid #F0EDE6;border-radius:14px;padding:24px;text-align:center;transition:all .3s}
.ln-rate-card:hover{box-shadow:0 6px 20px rgba(0,0,0,.05);transform:translateY(-2px)}
.ln-rate-card.featured{border-color:#E8A838;background:linear-gradient(135deg,#FFF9E6,#FFF5D6);position:relative}
.ln-rate-card.featured::before{content:'HOT';position:absolute;top:12px;right:12px;background:#DC2626;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px}
.ln-rate-flag{margin-bottom:8px;display:flex;justify-content:center}.ln-rate-flag svg{width:36px;height:36px}
.ln-rate-country{font-weight:600;color:#083838;font-size:15px;margin-bottom:4px}
.ln-rate-price{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:28px;color:#0D4F4F}
.ln-rate-unit{font-size:12px;color:#9CA3AF}

/* ── Features ── */
.ln-features{padding:80px 24px;background:#F7F5F0}
.ln-feat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;max-width:1000px;margin:0 auto}
.ln-feat{background:#fff;border-radius:14px;padding:28px;border:1px solid #F0EDE6;text-align:center;transition:all .3s}
.ln-feat:hover{transform:translateY(-3px);box-shadow:0 6px 20px rgba(0,0,0,.04)}
.ln-feat-icon{margin-bottom:16px;display:flex;justify-content:center}.ln-feat-icon svg{width:40px;height:40px}
.ln-feat h3{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:18px;color:#083838;margin-bottom:6px}
.ln-feat p{font-size:13px;color:#6B7280;line-height:1.6}

/* ── Referral ── */
.ln-referral{padding:60px 24px;background:linear-gradient(135deg,#083838,#0D4F4F);color:#fff;text-align:center}
.ln-referral h2{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:32px;color:#fff;margin-bottom:10px}
.ln-referral p{color:rgba(255,255,255,.6);font-size:15px;margin-bottom:24px;max-width:500px;margin-left:auto;margin-right:auto}
.ln-referral-highlight{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:48px;color:#E8A838;margin-bottom:8px}
.ln-cta-btn{display:inline-flex;align-items:center;padding:14px 36px;background:#E8A838;color:#083838;border-radius:12px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:15px;text-decoration:none;transition:all .25s}
.ln-cta-btn:hover{background:#F0C060;transform:translateY(-2px)}

/* ── Responsive ── */
@media(max-width:768px){
    .ln-hero h1{font-size:30px}
    .ln-shorten-form{flex-direction:row}
    .ln-shorten-form input{min-width:0;padding:14px 14px;font-size:14px}
    .ln-shorten-form button{padding:14px 20px;font-size:14px;margin-left:4px}
    .ln-feat-grid{grid-template-columns:1fr}
    .ln-counters{gap:24px}
    .ln-steps{flex-direction:column;align-items:center}
    .ln-step-arrow{display:none}
}
</style>

<!-- ═══ HERO + SHORTEN BOX ═══ -->
<section class="ln-hero">
    <h1>Rút gọn link.<br><span>Kiếm tiền.</span></h1>
    <p class="subtitle">Paste link dài → nhận shortlink ngắn. Chia sẻ lên blog, YouTube, Facebook — mỗi lượt click hợp lệ bạn đều được trả tiền.</p>

    <div class="ln-shorten-box">
        <div class="ln-shorten-form" id="shortenForm">
            <input type="url" id="longUrl" placeholder="Dán link cần rút gọn tại đây..." autocomplete="off">
            <button onclick="shortenLink()">Rút gọn</button>
        </div>
        <p class="ln-shorten-note">
            Bằng việc sử dụng, bạn đồng ý với <a href="#">Điều khoản</a>.
            <?php if ( ! $is_logged ) : ?>
                <a href="<?php echo home_url('/dang-ky'); ?>">Đăng ký</a> để quản lý links & kiếm tiền.
            <?php endif; ?>
        </p>

        <!-- Result -->
        <div class="ln-result" id="shortenResult">
            <div class="ln-result-url">
                <input type="text" id="shortUrlOutput" readonly>
                <button onclick="copyShortlink()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>Copy</button>
            </div>
            <div class="ln-result-stats">
                <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><path d="M20 6L9 17l-5-5"/></svg>Link đã được rút gọn</span>
                <span id="resultExtra"></span>
            </div>
        </div>
    </div>

    <!-- Counters -->
    <div class="ln-counters">
        <div class="ln-counter">
            <div class="ln-counter-value"><?php echo number_format( $total_links ); ?></div>
            <div class="ln-counter-label">Links đã tạo</div>
        </div>
        <div class="ln-counter">
            <div class="ln-counter-value"><?php echo number_format( $total_clicks ); ?></div>
            <div class="ln-counter-label">Lượt click</div>
        </div>
        <div class="ln-counter">
            <div class="ln-counter-value"><?php echo number_format( $total_users ); ?></div>
            <div class="ln-counter-label">Publishers</div>
        </div>
        <div class="ln-counter">
            <div class="ln-counter-value"><?php echo linkngon_format_money( $total_earned ); ?></div>
            <div class="ln-counter-label">Đã trả cho publishers</div>
        </div>
    </div>
</section>

<!-- ═══ HOW IT WORKS ═══ -->
<section class="ln-how">
    <div class="ln-section-title">
        <h2>Cách hoạt động</h2>
        <p>Chỉ 3 bước đơn giản để bắt đầu kiếm tiền</p>
    </div>
    <div class="ln-steps">
        <div class="ln-step">
            <div class="ln-step-num">1</div>
            <h3>Tạo tài khoản</h3>
            <p>Đăng ký miễn phí, chỉ cần email. Bắt đầu ngay trong 30 giây.</p>
            <span class="ln-step-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
        <div class="ln-step">
            <div class="ln-step-num">2</div>
            <h3>Rút gọn & chia sẻ</h3>
            <p>Paste bất kỳ link nào để rút gọn. Chia sẻ lên blog, YouTube, Facebook, forum...</p>
            <span class="ln-step-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
        <div class="ln-step">
            <div class="ln-step-num">3</div>
            <h3>Kiếm tiền</h3>
            <p>Mỗi lượt click hợp lệ bạn đều được trả tiền. Rút về ngân hàng hoặc ví điện tử.</p>
        </div>
    </div>
</section>

<!-- ═══ PAYOUT RATES ═══ -->
<section class="ln-rates">
    <div class="ln-section-title">
        <h2>Bảng giá thanh toán</h2>
        <p>CPM (cost per 1000 views) — tỷ lệ cao nhất thị trường</p>
    </div>
    <div class="ln-rates-grid">
        <div class="ln-rate-card featured">
            <div class="ln-rate-flag"><svg viewBox="0 0 36 36"><rect width="36" height="36" rx="6" fill="#DA251D"/><polygon points="18,8 20.5,14.5 27.5,14.5 21.8,18.5 24,25 18,21 12,25 14.2,18.5 8.5,14.5 15.5,14.5" fill="#FFCD00"/></svg></div>
            <div class="ln-rate-country">Việt Nam</div>
            <div class="ln-rate-price"><?php echo linkngon_format_money( linkngon_get_option( 'rate_vn', 30000 ) ); ?></div>
            <div class="ln-rate-unit">/ 1000 views</div>
        </div>
        <div class="ln-rate-card">
            <div class="ln-rate-flag"><svg viewBox="0 0 36 36"><rect width="36" height="36" rx="6" fill="#B22234"/><rect y="5.5" width="36" height="2.8" fill="#fff"/><rect y="11" width="36" height="2.8" fill="#fff"/><rect y="16.5" width="36" height="2.8" fill="#fff"/><rect y="22" width="36" height="2.8" fill="#fff"/><rect y="27.5" width="36" height="2.8" fill="#fff"/><rect width="16" height="19.4" rx="2" fill="#3C3B6E"/><circle cx="4" cy="4" r="1" fill="#fff"/><circle cx="8" cy="4" r="1" fill="#fff"/><circle cx="12" cy="4" r="1" fill="#fff"/><circle cx="6" cy="7" r="1" fill="#fff"/><circle cx="10" cy="7" r="1" fill="#fff"/><circle cx="4" cy="10" r="1" fill="#fff"/><circle cx="8" cy="10" r="1" fill="#fff"/><circle cx="12" cy="10" r="1" fill="#fff"/><circle cx="6" cy="13" r="1" fill="#fff"/><circle cx="10" cy="13" r="1" fill="#fff"/><circle cx="4" cy="16" r="1" fill="#fff"/><circle cx="8" cy="16" r="1" fill="#fff"/><circle cx="12" cy="16" r="1" fill="#fff"/></svg></div>
            <div class="ln-rate-country">United States</div>
            <div class="ln-rate-price">$3.50</div>
            <div class="ln-rate-unit">/ 1000 views</div>
        </div>
        <div class="ln-rate-card">
            <div class="ln-rate-flag"><svg viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div>
            <div class="ln-rate-country">Các nước khác</div>
            <div class="ln-rate-price">$1.50</div>
            <div class="ln-rate-unit">/ 1000 views</div>
        </div>
    </div>
</section>

<!-- ═══ FEATURES ═══ -->
<section class="ln-features">
    <div class="ln-section-title">
        <h2>Tính năng nổi bật</h2>
        <p>Công cụ rút gọn link mạnh mẽ nhất</p>
    </div>
    <div class="ln-feat-grid">
        <div class="ln-feat"><div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></div><h3>Thống kê chi tiết</h3><p>Theo dõi clicks, quốc gia, thiết bị, referer theo thời gian thực.</p></div>
        <div class="ln-feat"><div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></div><h3>API rút gọn</h3><p>API nhanh để tích hợp vào website, app hoặc script tự động.</p></div>
        <div class="ln-feat"><div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><circle cx="12" cy="16" r="1"/></svg></div><h3>Link an toàn</h3><p>Chống spam, malware. Link của bạn luôn hoạt động ổn định.</p></div>
        <div class="ln-feat"><div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><h3>Rút tiền nhanh</h3><p>Rút tối thiểu <?php echo linkngon_format_money( linkngon_get_option( 'min_withdrawal', 50000 ) ); ?>. Chuyển khoản ngân hàng hoặc MoMo.</p></div>
        <div class="ln-feat"><div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div><h3>Referral 20%</h3><p>Giới thiệu bạn bè và nhận 20% thu nhập của họ — vĩnh viễn!</p></div>
        <div class="ln-feat"><div class="ln-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#0D4F4F" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div><h3>Đa nền tảng</h3><p>Hoạt động trên mọi thiết bị. Link rút gọn tương thích mọi nơi.</p></div>
    </div>
</section>

<!-- ═══ REFERRAL CTA ═══ -->
<section class="ln-referral">
    <div class="ln-referral-highlight">20%</div>
    <h2>Chương trình giới thiệu</h2>
    <p>Giới thiệu bạn bè đăng ký LinkNgon và nhận 20% thu nhập của họ — trọn đời!</p>
    <?php if ( $is_logged ) : ?>
        <a href="<?php echo get_permalink( get_page_by_path('dashboard') ); ?>" class="ln-cta-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Vào Dashboard</a>
    <?php else : ?>
        <a href="<?php echo home_url('/dang-ky'); ?>" class="ln-cta-btn"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>Đăng ký ngay — Miễn phí</a>
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
                document.getElementById('resultExtra').textContent = <?php echo $is_logged ? "'Bạn sẽ kiếm tiền từ mỗi lượt click!'" : "'Đăng ký để kiếm tiền từ link này'" ?>;
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
