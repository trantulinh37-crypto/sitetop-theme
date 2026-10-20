<?php
/**
 * 404 Page Template
 * Handles missing pages + shortlink not found
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$request_uri = trim( $_SERVER['REQUEST_URI'] ?? '', '/' );
$request_uri = strtok( $request_uri, '?' );

$is_shortlink = false;
if ( preg_match( '/^[a-zA-Z0-9]{6}$/', $request_uri ) ) {
    $is_shortlink = true;
} elseif ( preg_match( '/^[a-zA-Z0-9][a-zA-Z0-9-]{1,49}$/', $request_uri ) ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$p}user_shortlinks WHERE code = %s OR alias = %s LIMIT 1",
        $request_uri, $request_uri
    ));
    if ( $exists ) $is_shortlink = true;
}

$site_name = get_bloginfo( 'name' );
/* 20/10/2026 chủ site: thiết kế lại theo ảnh mẫu (thẻ trắng giữa nền xám nhạt, hình minh hoạ tài liệu + kính lúp link
   gãy, 404 đậm, nút đen "Về trang chủ"); mô tả chung cho mọi trường hợp: "Trang này không khả dụng". */
$msg = 'Trang này không khả dụng';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>404 - <?php echo esc_html( $site_name ); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;background:#F3F3F3;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;color:#1F1F1F}
.c{background:#fff;border-radius:14px;padding:44px 40px 36px;max-width:440px;width:100%;text-align:center;box-shadow:0 18px 50px -18px rgba(0,0,0,.22),0 2px 6px rgba(0,0,0,.04)}
.hinh{display:block;width:230px;max-width:80%;height:auto;margin:0 auto 6px}
.c h1{font-size:76px;font-weight:800;line-height:1;letter-spacing:-1px;color:#2B2B2B;margin-bottom:12px}
.c h2{font-size:26px;font-weight:700;color:#1F1F1F;margin-bottom:12px}
.c p{color:#6B6B6B;line-height:1.6;margin-bottom:26px;font-size:16px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-width:170px;background:#232323;color:#fff;padding:14px 30px;border-radius:10px;text-decoration:none;font-weight:600;font-size:15px;transition:background .15s,transform .15s}
.btn:hover{background:#000;transform:translateY(-1px)}
.btn svg{width:18px;height:18px;flex:none}
@media(max-width:420px){.c{padding:34px 22px 28px}.c h1{font-size:62px}.c h2{font-size:22px}.c p{font-size:15px}}
</style>
</head>
<body>
<div class="c">
    <svg class="hinh" viewBox="0 0 240 130" aria-hidden="true">
        <ellipse cx="120" cy="120" rx="80" ry="5" fill="#EDEDED"/>
        <path d="M18 88c0-22 22-32 40-26 8-18 34-20 44-6 14-10 40-6 46 14 18-6 40 4 40 22z" fill="#F0F0F0"/>
        <circle cx="30" cy="92" r="5" fill="#E2E2E2"/><circle cx="214" cy="104" r="5" fill="#E2E2E2"/>
        <path d="M74 14h50l18 18v84a4 4 0 0 1-4 4H74a4 4 0 0 1-4-4V18a4 4 0 0 1 4-4z" fill="#fff" stroke="#3A3A3A" stroke-width="3" stroke-linejoin="round"/>
        <path d="M124 14v14a4 4 0 0 0 4 4h14" fill="#E9E9E9" stroke="#3A3A3A" stroke-width="3" stroke-linejoin="round"/>
        <path d="M88 44l7 7m0-7l-7 7M108 44l7 7m0-7l-7 7" stroke="#2B2B2B" stroke-width="3" stroke-linecap="round"/>
        <path d="M94 66q7-6 14 0" fill="none" stroke="#2B2B2B" stroke-width="3" stroke-linecap="round"/>
        <rect x="84" y="84" width="40" height="5" rx="2.5" fill="#E4E4E4"/><rect x="84" y="95" width="26" height="5" rx="2.5" fill="#E4E4E4"/>
        <path d="M164 30l5-10M174 38l9-5M176 50l9 0" stroke="#3A3A3A" stroke-width="3" stroke-linecap="round"/>
        <path d="M158 22l1-4" stroke="#BDBDBD" stroke-width="3" stroke-linecap="round"/>
        <line x1="170" y1="102" x2="186" y2="118" stroke="#2B2B2B" stroke-width="9" stroke-linecap="round"/>
        <circle cx="150" cy="80" r="27" fill="#F4F4F4" stroke="#2B2B2B" stroke-width="6"/>
        <circle cx="150" cy="80" r="20" fill="#E6E6E6"/>
        <g fill="none" stroke="#7A7A7A" stroke-width="3.2" stroke-linecap="round">
            <path d="M146 86l-4 4a5 5 0 0 1-7-7l4-4"/><path d="M154 74l4-4a5 5 0 0 1 7 7l-4 4"/>
            <path d="M143 75l-3-3M157 85l3 3"/>
        </g>
    </svg>
    <h1>404</h1>
    <h2>Không tìm thấy</h2>
    <p><?php echo esc_html( $msg ); ?></p>
    <a href="<?php echo esc_url( home_url() ); ?>" class="btn"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3.2 2.5 11.3a1 1 0 0 0 1.3 1.5L5 11.8V20a1 1 0 0 0 1 1h4.5v-6h3v6H18a1 1 0 0 0 1-1v-8.2l1.2 1a1 1 0 0 0 1.3-1.5z"/></svg>Về trang chủ</a>
</div>
</body>
</html>
