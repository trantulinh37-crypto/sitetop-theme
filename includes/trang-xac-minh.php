<?php
/**
 * TRANG CỔNG "XÁC MINH TÀI KHOẢN" — user mới đăng ký, chưa được duyệt nguồn.
 *
 * Chủ site chốt 06/10/2026: duyệt rồi mới được vào hệ thống. Nên đây là trang ĐỘC LẬP,
 * page-user-dashboard.php include rồi exit — không render dashboard phía sau (ẩn bằng CSS
 * thì mở F12 vẫn đọc được số dư, danh sách link, rate).
 *
 * Duyệt xong user đi thẳng vào dashboard và thấy lại ô "Nguồn file gốc" như cũ để thêm /
 * xoá nguồn khi đổi nguồn — phần đó KHÔNG đụng tới.
 *
 * Biến nhận từ template gọi: $xm_items, $xm_tg, $xm_cho, $xm_nonce.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Xác minh tài khoản - <?php bloginfo( 'name' ); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php wp_head(); ?>
<style>
:root{--p:#2F5E96;--pl:#4E80B4;--card:#fff;--dark:#0A1633;--brd:#DFE5F3;--err:#E0364B;--am:#9a6b12;--amb:#fdf3e2;--amv:#f0dcb4}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',-apple-system,"Segoe UI",Roboto,sans-serif;background:#2b3444;color:var(--dark);line-height:1.6;
     min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px 16px}
.xm-wrap{width:100%;max-width:560px}
.xm-card{background:var(--card);border-radius:16px;overflow:hidden;border-left:5px solid var(--p);box-shadow:0 18px 50px rgba(0,0,0,.28)}
.xm-card.cho{border-left-color:#d9a441}
.xm-h{display:flex;align-items:center;gap:15px;padding:20px 22px;border-bottom:1px solid var(--brd)}
.xm-ic{flex:none;width:56px;height:56px;border-radius:13px;background:#eef3fb;color:var(--p);display:flex;align-items:center;justify-content:center}
.xm-card.cho .xm-ic{background:var(--amb);color:var(--am)}
.xm-ht{flex:1;min-width:0}
.xm-ht b{display:block;font-size:19px;line-height:1.3;color:var(--dark)}
.xm-ht span{font-size:13.5px;color:#6b7280}
.xm-step{flex:none;font-size:12.5px;font-weight:600;padding:6px 13px;border-radius:999px;background:#eef3fb;color:var(--p);border:1px solid #d6e3f5;white-space:nowrap}
.xm-card.cho .xm-step{background:var(--amb);color:var(--am);border-color:var(--amv)}
.xm-b{padding:22px}
.xm-note{display:flex;gap:11px;background:#eef3fb;border-left:4px solid var(--p);border-radius:9px;padding:14px 15px;font-size:14px;line-height:1.6;color:#33415a;margin-bottom:20px}
.xm-note svg{flex:none;margin-top:3px}
.xm-lb{display:block;font-size:12.5px;font-weight:700;letter-spacing:.5px;color:#4b5563;margin-bottom:9px}
.xm-lb i{color:var(--err);font-style:normal}
.xm-ta{width:100%;min-height:180px;border:1.5px solid var(--p);border-radius:11px;padding:14px 15px;font:inherit;font-size:15px;line-height:1.75;resize:vertical;color:var(--dark)}
.xm-ta:focus{outline:2px solid #cfe0f3;outline-offset:1px}
.xm-hint{font-size:13px;color:#6b7280;margin:8px 2px 18px}
.xm-btn{width:100%;border:0;border-radius:11px;background:var(--p);color:#fff;font-size:16px;font-weight:600;padding:16px;cursor:pointer}
.xm-btn:hover{background:#27507f}
.xm-btn:disabled{opacity:.6;cursor:default}
.xm-msg{font-size:13.5px;margin-top:11px;min-height:19px}
.xm-wait{text-align:center;padding:8px 0 2px}
.xm-big{width:92px;height:92px;border-radius:50%;background:var(--amb);color:var(--am);display:flex;align-items:center;justify-content:center;margin:0 auto 18px}
.xm-wait b{display:block;font-size:18px;color:var(--dark);margin-bottom:9px}
/* Dòng thông báo để ĐỎ — chủ site chốt 06/10, cho user đọc là thấy ngay việc phải làm. */
.xm-wait p{font-size:14.5px;color:var(--err);font-weight:500;line-height:1.65;margin:0 auto 18px;max-width:440px}
.xm-tg{display:block;background:#f6f7f9;border:1px solid var(--brd);border-radius:11px;padding:15px;font-size:15px;color:var(--p);text-decoration:none;word-break:break-all;margin-bottom:15px}
.xm-wbtn{display:inline-block;border:1px solid var(--amv);background:var(--amb);color:var(--am);border-radius:999px;padding:12px 24px;font-size:14.5px;font-weight:600}
.xm-ds{text-align:left;margin-top:18px;padding:15px;background:#f9fafb;border:1px solid var(--brd);border-radius:11px}
.xm-ds b{display:block;font-size:12px;font-weight:700;letter-spacing:.4px;color:#4b5563;margin-bottom:9px}
.xm-ds div{font-size:14px;color:var(--dark);padding:4px 0;word-break:break-all}
.xm-f{display:flex;justify-content:space-between;align-items:center;padding:14px 22px;border-top:1px solid var(--brd);font-size:13.5px}
.xm-f a{color:var(--p);text-decoration:none}
.xm-f span{color:#9ca3af}
.xm-out{text-align:center;margin-top:16px;font-size:13.5px}
.xm-out a{color:#c6d2e4;text-decoration:none}
.xm-out a:hover{color:#fff}
@media(max-width:560px){.xm-h{padding:16px;gap:12px}.xm-b{padding:16px}.xm-ic{width:46px;height:46px}.xm-ht b{font-size:16.5px}.xm-step{font-size:11.5px;padding:5px 10px}}
</style>
</head>
<body>
<div class="xm-wrap">
<div class="xm-card<?php echo $xm_cho ? ' cho' : ''; ?>">
    <div class="xm-h">
        <div class="xm-ic">
        <?php if ( $xm_cho ) : ?>
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14M5 2h14M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"/></svg>
        <?php else : ?>
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
        <?php endif; ?>
        </div>
        <div class="xm-ht">
            <b><?php echo $xm_cho ? 'Đang chờ xét duyệt' : 'Xác minh tài khoản'; ?></b>
            <span><?php echo $xm_cho ? 'Admin sẽ xác minh trong thời gian sớm nhất' : 'Khai báo nguồn traffic để bắt đầu sử dụng'; ?></span>
        </div>
        <div class="xm-step"><?php echo $xm_cho ? '⏳ Đang duyệt' : 'Bước 1/2'; ?></div>
    </div>

    <div class="xm-b">
    <?php if ( ! $xm_cho ) : ?>
        <div class="xm-note">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01" stroke-linecap="round"/></svg>
            <span>Để sử dụng hệ thống, bạn cần khai báo nguồn traffic. Nếu có nhiều nguồn, hãy xuống dòng để liệt kê từng nguồn.</span>
        </div>
        <label class="xm-lb" for="xmTa">NGUỒN VIEW / TRAFFIC <i>*</i></label>
        <textarea id="xmTa" class="xm-ta" maxlength="2000" placeholder="VD:&#10;Facebook&#10;YouTube&#10;TikTok&#10;Website cá nhân"></textarea>
        <div class="xm-hint">Mỗi nguồn một dòng · Tối đa 2000 ký tự</div>
        <button type="button" class="xm-btn" id="xmBtn">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:7px"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
            Gửi yêu cầu xác minh
        </button>
        <div class="xm-msg" id="xmMsg"></div>
    <?php else : ?>
        <div class="xm-wait">
            <div class="xm-big">
                <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14M5 2h14M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"/></svg>
            </div>
            <b>Yêu cầu đang được xử lý</b>
            <p>Bạn đã đăng ký nguồn View Nhiệm Vụ bên dưới. Hãy liên hệ Admin Gửi Email để được xác minh nhanh hơn.</p>
            <a class="xm-tg" href="https://t.me/<?php echo esc_attr( $xm_tg ); ?>" target="_blank" rel="noopener">https://t.me/<?php echo esc_html( $xm_tg ); ?></a>
            <div class="xm-wbtn">⏳ Chờ admin xác minh</div>
            <div class="xm-ds">
                <b>NGUỒN ĐÃ GỬI</b>
                <?php foreach ( $xm_items as $xm_it ) : ?>
                    <div>• <?php echo esc_html( $xm_it['text'] ?? '' ); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    </div>

    <div class="xm-f">
        <a href="https://t.me/<?php echo esc_attr( $xm_tg ); ?>" target="_blank" rel="noopener">✈ Hỗ trợ</a>
        <span>Hỗ trợ 24/7</span>
    </div>
</div>
<div class="xm-out"><a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">Đăng xuất</a></div>
</div>

<?php if ( ! $xm_cho ) : ?>
<script>
(function(){
    var AJAX = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    var NONCE = <?php echo wp_json_encode( $xm_nonce ); ?>;
    var ta = document.getElementById('xmTa'), btn = document.getElementById('xmBtn'), msg = document.getElementById('xmMsg');
    function loi(t){ msg.innerHTML = '<span style="color:var(--err)">' + t + '</span>'; }
    btn.addEventListener('click', function(){
        var val = (ta.value || '').trim();
        if (!val) { loi('Vui lòng nhập ít nhất một nguồn.'); ta.focus(); return; }
        if (val.length > 2000) { loi('Tối đa 2000 ký tự.'); return; }
        btn.disabled = true; var cu = btn.innerHTML; btn.textContent = 'Đang gửi...'; msg.textContent = '';
        var body = 'action=sitetop_submit_sources&nonce=' + encodeURIComponent(NONCE) + '&sources=' + encodeURIComponent(val);
        fetch(AJAX, { method:'POST', credentials:'same-origin',
                      headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body })
        .then(function(r){ return r.json(); })
        .then(function(r){
            if (r && r.success) { location.reload(); return; }
            loi((r && r.data) || 'Lỗi, vui lòng thử lại'); btn.disabled = false; btn.innerHTML = cu;
        })
        .catch(function(){ loi('Lỗi mạng, vui lòng thử lại'); btn.disabled = false; btn.innerHTML = cu; });
    });
})();
</script>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
