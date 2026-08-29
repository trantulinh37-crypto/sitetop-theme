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
   Ảnh tĩnh, không animation (theo yêu cầu bỏ hiệu ứng trôi nổi) — scale(.92) cố định
   để vừa khung desktop hơn, khung ::before vẫn to hơn container 6% mỗi cạnh để scale
   xuống không lộ mép. */
.h2-hero{min-height:min(100vh,47vw);box-sizing:border-box;display:flex;align-items:center;background:#EAF1FF;position:relative;overflow:hidden;padding:120px 24px 60px}
/* 23/08/2026 — ảnh nền 3D (1991×932, tỉ lệ 2.14).
   Giữ 'cover' để không bao giờ hở mép. Ảnh bị cắt nhiều hay ít là do khung hero cao
   hơn tỉ lệ ảnh: hero cao 100vh nên trên màn hẹp-cao nó phóng ảnh lên rất mạnh.
   Cách chữa: khống chế chiều cao hero bám theo BỀ RỘNG (47vw = tỉ lệ ảnh 1991×932) — khung luôn gần tỉ lệ
   ảnh nên gần như không cắt, mà vẫn kín mép. 'contain' thì hở dải trắng ở màn rộng. */
.h2-hero::before{content:'';position:absolute;inset:0;z-index:0;background:url('<?php echo $hero_bg_url; ?>') no-repeat right center/cover}
/* Ảnh thu nhỏ nên lộ dải nền xanh đặc ở đáy ảnh gốc — phủ gradient mờ dần sang màu
   nền của khối kế tiếp để chuyển tiếp mượt, không còn viền cứng. Khối "Vì sao SEOer"
   đã gỡ 29/08/2026 nên khối kế tiếp giờ là thanh bản quyền (.ln-copyright:#EDF4FF). */
.h2-hero::after{content:'';position:absolute;left:0;right:0;bottom:0;height:160px;z-index:1;background:linear-gradient(180deg,transparent,#EDF4FF);pointer-events:none}
/* minmax(0,1fr) chứ KHÔNG phải 1fr: cột '1fr' có mức tối thiểu ngầm là 'auto' nên nó
   phình theo nội dung thay vì co lại — ở màn hẹp khối chữ rộng 614px trong khung 452px,
   tràn ra ngoài và bị overflow:hidden cắt mất. Lỗi có sẵn, lộ ra khi thêm huy hiệu. */
.h2-hero-grid{position:relative;z-index:2;max-width:1280px;margin:0 auto;width:100%;display:grid;grid-template-columns:minmax(0,1fr);gap:40px;align-items:center}
/* width:100% + min-width:0 — không có hai dòng này thì khối tự co giãn theo NỘI DUNG
   (đo được width tính ra 614px trong cột chỉ 452px) rồi tràn ra ngoài và bị
   overflow:hidden của hero cắt mất. Lỗi có sẵn, lộ ra khi thêm huy hiệu. */
.h2-left{width:100%;max-width:620px;min-width:0}

/* Huy hiệu trên tiêu đề — thêm 23/08/2026 theo mẫu người dùng gửi.
   Cờ vẽ bằng SVG chứ KHÔNG dùng emoji 🇻🇳: emoji bị mỗi hệ điều hành vẽ một kiểu,
   có nơi phóng to phá vỡ bố cục. */
.h2-badge{display:inline-flex;align-items:center;gap:9px;margin-bottom:18px;padding:8px 16px;
    border-radius:999px;background:#fff;border:1px solid #E3EAF6;
    box-shadow:0 2px 8px -3px rgba(15,23,42,.10);
    font-size:13px;font-weight:700;color:#1F2A44;line-height:1.35}
.h2-badge>svg:first-child{flex:none;color:#10B981}
.h2-badge .fl{flex:none;display:inline-block;vertical-align:-2px;border-radius:2px}
@media(max-width:900px){.h2-badge{font-size:12.2px;padding:7px 14px;gap:8px}}
/* Neo theo BỀ RỘNG MÀN HÌNH chứ không theo khối cha: .h2-left vốn đã tràn ở màn
   hẹp (lỗi có sẵn, production cũng vậy) nên max-width:100% sẽ ăn theo cái tràn đó
   và huy hiệu bị cắt mất lá cờ. */
.h2-badge{max-width:min(100%,calc(100vw - 48px))}
.h2-badge .tx{min-width:0}
/* Điện thoại: ép ĐÚNG 1 DÒNG. Cỡ chữ co theo bề rộng màn hình, công thức lấy từ số đo
   thật: huy hiệu = 73px cố định (icon + cờ + đệm + khoảng cách) + 26,5px cho mỗi 1px cỡ
   chữ. Chỗ trống = màn hình − 48 (đệm hero). Giải ra: cỡ chữ ≤ (100vw − 121) / 26,5
   ≈ 3,77vw − 4,6. Lấy 3,7vw − 4,6 cho dư một chút an toàn.
   Kiểm: màn 360px → 8,7px (cần ≤9,0) · 390px → 9,8px (cần ≤10,2) · 430px → 11,3px (cần ≤11,7). */
@media(max-width:600px){
    .h2-badge{white-space:nowrap;font-size:clamp(8.6px,calc(3.7vw - 4.6px),13px);
        padding:7px 12px;gap:7px;align-items:center}
    .h2-badge>svg:first-child{width:13px;height:13px}
}
.h2-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:clamp(30px,4vw,48px);line-height:1.2;color:#0F172A;margin-bottom:20px}
.h2-title .hl{color:#2563EB}
.h2-sub{font-size:16px;color:#334155;font-weight:500;line-height:1.7;margin-bottom:12px;max-width:480px}
/* Dòng lưu ý pháp lý. Tách khỏi .h2-sub vì .h2-sub bị ép white-space:nowrap ở
   mobile (mỗi câu đúng 1 dòng) — câu dài nhét vào đó sẽ tràn ngang màn hình. */
.h2-note{display:flex;align-items:flex-start;gap:7px;font-size:13.5px;color:#475569;line-height:1.6;max-width:480px;margin-bottom:26px}
.h2-note .ic-warn{width:16px;height:16px;flex-shrink:0;margin-top:2px}
/* Icon cuối mỗi dòng: SVG thay cho emoji ✅/💧 — emoji bị WordPress convert thành
   ảnh và màu xanh lá/xanh nước lạc tông so với bộ nhận diện xanh dương */
.h2-sub .ic{width:17px;height:17px;margin-left:6px;vertical-align:-3px}
/* WordPress tự chuyển emoji unicode thành <img class="emoji"> — ép về đúng cỡ chữ, tránh hiện to bất thường */
img.emoji{height:1em!important;width:1em!important;margin:0 .05em 0 .1em!important;vertical-align:-.1em!important;display:inline-block!important;background:none!important;border:none!important;padding:0!important;box-shadow:none!important;border-radius:0!important}

.h2-pills{display:flex;flex-wrap:nowrap;gap:8px;margin-bottom:32px;overflow-x:auto}
.h2-pill{display:inline-flex;align-items:center;gap:5px;background:#fff;border:1px solid #E2E8F0;border-radius:999px;padding:7px 12px;font-size:11.5px;font-weight:600;color:#1E293B;box-shadow:0 2px 8px rgba(30,64,150,.06);white-space:nowrap;flex-shrink:0}
.h2-pill svg{width:13px;height:13px;flex-shrink:0;color:#2563EB}

.h2-cta-row{display:flex;align-items:center;gap:24px;flex-wrap:wrap}
.h2-cta{display:inline-flex;align-items:center;gap:10px;background:linear-gradient(90deg,#2563EB,#3B82F6);color:#fff;font-weight:700;font-size:15px;padding:16px 28px;border-radius:999px;text-decoration:none;box-shadow:0 10px 24px rgba(37,99,235,.3);transition:all .25s}
.h2-cta:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(37,99,235,.4)}
.h2-cta svg{width:18px;height:18px;flex-shrink:0}
.h2-cta .arrow{transition:transform .25s}
.h2-cta:hover .arrow{transform:translateX(4px)}

/* Khối tin cậy: gom thành MỘT thẻ nền trắng bo tròn thay vì avatar + chữ trôi nổi.
   Cùng ngôn ngữ với .h2-pill ngay phía trên (nền trắng, viền #E2E8F0, bo tròn, đổ bóng
   xanh nhạt) nên nhìn ra là một hệ, và trên ảnh nền hero thì chữ luôn đọc được thay vì
   phụ thuộc chỗ ảnh sáng hay tối. */
.h2-social{display:inline-flex;align-items:center;gap:11px;background:rgba(255,255,255,.92);border:1px solid #E2E8F0;border-radius:999px;padding:6px 16px 6px 7px;box-shadow:0 3px 12px rgba(30,64,150,.08);backdrop-filter:blur(6px)}
.h2-avatars{display:flex;align-items:center}
/* Avatar khách hàng — ảnh do user cung cấp, đã cắt tròn sẵn ở assets/img/avatar-*.png */
.h2-avatars span{width:34px;height:34px;border-radius:50%;border:2px solid #fff;margin-left:-10px;display:inline-flex;align-items:center;justify-content:center;overflow:hidden;box-shadow:0 2px 6px rgba(30,64,150,.14)}
.h2-avatars span:first-child{margin-left:0}
.h2-avatars span img{width:100%;height:100%;display:block;object-fit:cover}
/* Chip "+" khép dãy avatar: nói rõ còn nhiều người nữa, không phải chỉ 3 người trong ảnh. */
.h2-avatars .h2-av-more{background:linear-gradient(135deg,#2563EB,#3B82F6);color:#fff;font-size:10.5px;font-weight:800;letter-spacing:-.02em}
.h2-social-text{font-size:12.5px;color:#475569;font-weight:500;line-height:1.25;text-align:left;white-space:nowrap}
.h2-social-text strong{display:block;font-size:15px;color:#0F172A;font-weight:800;letter-spacing:-.015em}
/* Chấm xanh nhấp nháy: tín hiệu "đang hoạt động", đúng thứ một nền tảng traffic cần
   khoe. Tôn trọng người tắt hiệu ứng chuyển động ở phần dưới. */
.h2-social-text strong::after{content:'';display:inline-block;width:6px;height:6px;border-radius:50%;background:#22C55E;margin-left:7px;vertical-align:middle;box-shadow:0 0 0 0 rgba(34,197,94,.6);animation:h2Pulse 2.2s ease-out infinite}
@keyframes h2Pulse{0%{box-shadow:0 0 0 0 rgba(34,197,94,.55)}70%{box-shadow:0 0 0 7px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
@media(prefers-reduced-motion:reduce){.h2-social-text strong::after{animation:none}}

@media(max-width:960px){
    .h2-hero-grid{text-align:center}
    .h2-left{margin-left:auto;margin-right:auto}
    .h2-sub{margin-left:auto;margin-right:auto}
    .h2-note{margin-left:auto;margin-right:auto;justify-content:center;text-align:left}
    .h2-pills,.h2-cta-row{justify-content:center}
    .h2-social-text{text-align:left}
    /* Màn hẹp: ảnh nền bị crop/zoom mạnh (cover + neo phải) nên minh hoạ dồn
       ngay sau chữ, dễ rối mắt — phủ thêm lớp trắng mờ dần lên trên ::before
       để chữ vẫn rõ, không đổi ảnh/nội dung, chỉ thêm 1 lớp gradient lên nền. */
    /* 23/08/2026 đổi sang ảnh nền 3D mới: vật thể to và đặc hơn ảnh cũ nên lớp phủ
       .9/.6/.3 không còn đủ — tăng độ đục và kéo dài xuống hết chiều cao. */
    .h2-hero::before{background-image:linear-gradient(180deg,rgba(248,250,255,.96) 0%,rgba(248,250,255,.9) 42%,rgba(248,250,255,.78) 70%,rgba(248,250,255,.66) 100%),url('<?php echo $hero_bg_url; ?>')}
}
@media(max-width:600px){
    /* Mobile: ép mỗi câu gọn đúng 1 dòng (tổng 2 dòng) thay vì bị ngắt giữa câu.
       Cỡ chữ co theo bề rộng màn hình — công thức lấy từ số đo thật: câu dài nhất
       rộng ~22.5×(cỡ chữ)px, chỗ trống = bề rộng màn hình − padding 48 − icon ~19. */
    .h2-sub{white-space:nowrap;font-size:clamp(11.5px,calc(4.2vw - 3px),15px)}
    .h2-sub .ic{width:14px;height:14px;margin-left:5px;vertical-align:-2px}
    /* Ép dòng lưu ý gọn đúng 1 dòng, cùng cách làm với .h2-sub ở trên: cỡ chữ co
       theo bề rộng màn hình. Câu ~44 ký tự rộng ~19.8×(cỡ chữ)px; chỗ trống = bề
       rộng màn hình − padding 48 − icon và khoảng cách ~20. */
    .h2-note{white-space:nowrap;font-size:clamp(11px,calc(4.6vw - 3px),13px);gap:6px;margin-bottom:22px}
    .h2-note .ic-warn{width:14px;height:14px}
}
@media(max-width:480px){
    .h2-pills{flex-wrap:wrap;overflow-x:visible}
}
@media(max-width:340px){
    /* Màn quá hẹp: cho xuống dòng lại, ép 1 dòng nữa thì chữ nhỏ khó đọc */
    .h2-sub{white-space:normal;font-size:13px}
    /* Dưới 340px ép 1 dòng nữa thì chữ nhỏ khó đọc — cho xuống dòng lại, như .h2-sub */
    .h2-note{white-space:normal;font-size:12px}
}

.ln-section-title{text-align:center;margin-bottom:52px;position:relative;z-index:1}
.ln-title-row{display:flex;align-items:center;justify-content:center;gap:22px;flex-wrap:wrap}
.ln-title-row h2{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:clamp(21px,2.5vw,30px);color:#0F172A;margin:0}
/* padding-right: chữ in nghiêng nhô ra ngoài hộp chữ, nếu không chừa chỗ thì
   background-clip:text sẽ cắt mất phần đuôi chữ "P" */
.ln-title-row h2 .brand{font-style:italic;padding-right:4px;background:linear-gradient(90deg,#0057FF,#00A9FF);-webkit-background-clip:text;background-clip:text;color:transparent}
.ln-deco{display:flex;align-items:center;gap:7px;flex-shrink:0}
.ln-deco i{display:block;width:46px;height:2px;border-radius:2px;background:linear-gradient(90deg,rgba(37,99,235,.12),#2563EB)}
.ln-deco b{display:block;width:7px;height:7px;border-radius:50%;background:#2563EB}
.ln-deco.right i{background:linear-gradient(90deg,#2563EB,rgba(37,99,235,.12))}
.ln-title-bar{width:54px;height:3px;border-radius:2px;background:#2563EB;margin:16px auto 0}

.ln-feat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:28px;max-width:1120px;margin:0 auto;position:relative;z-index:1}
.ln-feat{background:#fff;border-radius:18px;padding:38px 26px 32px;border:1px solid #EFF3FA;text-align:center;box-shadow:0 4px 20px rgba(37,99,235,.05);transition:transform .3s,box-shadow .3s}
.ln-feat:hover{transform:translateY(-4px);box-shadow:0 14px 34px rgba(37,99,235,.13)}
.ln-feat-icon{width:92px;height:92px;margin:0 auto 22px;border-radius:50%;background:radial-gradient(circle at 50% 42%,#E8F1FF 0%,#F6FAFF 72%);display:flex;align-items:center;justify-content:center;box-shadow:inset 0 2px 8px rgba(37,99,235,.07)}
.ln-feat-icon svg{width:47px;height:47px}
.ln-feat h3{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:15.5px;letter-spacing:.02em;text-transform:uppercase;color:#0F2A5C;margin-bottom:14px}
.ln-feat p{font-size:13.5px;color:#64748B;line-height:1.75}

/* Sau khi gỡ khối "Vì sao SEOer" (29/08/2026), trang chủ chỉ còn hero + thanh bản
   quyền. Trên màn rộng, hero bị khống chế chiều cao theo bề ngang (47vw) nên hai
   khối cộng lại thấp hơn khung nhìn, hở một mảng nền trống ở đáy. Xếp body theo cột
   rồi đẩy thanh bản quyền xuống đáy. An toàn: header và nút liên hệ đều position
   fixed nên không nằm trong luồng, các thẻ script/style thì display:none.
   Nền body đặt đúng #EDF4FF — trùng màu hero mờ dần tới và màu thanh bản quyền —
   để khoảng giữa liền một mạch, không lộ vệt phân cách. */
body.home{min-height:100vh;display:flex;flex-direction:column;background:#EDF4FF}
body.home .ln-copyright{margin-top:auto}

/* ── Copyright footer (trang chủ, footer chính đang ẩn cho single-screen hero) ── */
.ln-copyright{padding:20px 24px;text-align:center;font-size:13px;color:#94A3B8;background:#EDF4FF;border-top:1px solid #E2EAF7}

</style>

<!-- ═══ HERO: Website rút gọn link và kiếm tiền ═══ -->
<section class="h2-hero">
    <div class="h2-hero-grid">
        <div class="h2-left">
            <span class="h2-badge">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                <span class="tx">Nền tảng rút gọn link &amp; cung cấp Traffic User Việt Nam</span>
                <svg class="fl" width="18" height="12" viewBox="0 0 30 20" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Việt Nam"><rect width="30" height="20" rx="2.5" fill="#DA251D"/><path fill="#FF0" d="M15 4.6l1.55 4.77h5.02l-4.06 2.95 1.55 4.77L15 14.14l-4.06 2.95 1.55-4.77-4.06-2.95h5.02z"/></svg>
            </span>
            <h1 class="h2-title">Website <span class="hl">rút gọn link</span> và<br><span class="hl">kiếm tiền</span></h1>
            <p class="h2-sub">Nền tảng rút gọn link uy tín hàng đầu Việt Nam<svg class="ic" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="sub1" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#4DA3FF"/><stop offset="100%" stop-color="#0057FF"/></linearGradient></defs><circle cx="12" cy="12" r="10" fill="url(#sub1)"/><path fill="#fff" d="M10.6 16.4l-4-4L8 11l2.6 2.6L16 8.2l1.4 1.4-6.8 6.8z"/></svg><br>Payout linh hoạt, thống kê chi tiết, API mạnh mẽ<svg class="ic" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="sub2" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#4DA3FF"/><stop offset="100%" stop-color="#0057FF"/></linearGradient></defs><path fill="url(#sub2)" d="M13.2 2L3.6 13.4h7.1L9.4 22l9.8-11.6h-7.1L13.2 2z"/></svg></p>

            <p class="h2-note"><svg class="ic-warn" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><span>Lưu ý: Nghiêm cấm làm link vi phạm pháp luật.</span></p>
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
                    <?php
                    // Cache-bust theo mtime để đổi ảnh avatar là trình duyệt tải lại ngay
                    $av = function ( $n ) {
                        $path = SITETOP_DIR . "/assets/img/avatar-$n.png";
                        $ver  = file_exists( $path ) ? filemtime( $path ) : SITETOP_VERSION;
                        return esc_url( SITETOP_URL . "/assets/img/avatar-$n.png?v=$ver" );
                    };
                    ?>
                    <div class="h2-avatars">
                        <span><img src="<?php echo $av(1); ?>" alt="Người dùng SITETOP" width="160" height="160" loading="lazy"></span>
                        <span><img src="<?php echo $av(2); ?>" alt="Người dùng SITETOP" width="160" height="160" loading="lazy"></span>
                        <span><img src="<?php echo $av(3); ?>" alt="Người dùng SITETOP" width="160" height="160" loading="lazy"></span>
                        <span class="h2-av-more" aria-hidden="true">+</span>
                    </div>
                    <div class="h2-social-text"><strong>1.000+ người dùng</strong>đang tin tưởng SITETOP</div>
                </div>
            </div>
        </div>

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
