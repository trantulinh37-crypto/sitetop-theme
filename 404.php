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
$msg = $is_shortlink ? 'Link rút gọn này không tồn tại hoặc đã bị vô hiệu hóa.' : 'Trang bạn đang tìm kiếm không tồn tại hoặc đã được di chuyển.';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>404 - <?php echo esc_html( $site_name ); ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;background:#083838;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.c{background:#fff;border-radius:16px;padding:40px;max-width:420px;width:100%;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,.3)}
.c h1{font-size:64px;font-weight:800;color:#0D4F4F;margin-bottom:8px}
.c h2{font-size:18px;color:#333;margin-bottom:16px}
.c p{color:#666;line-height:1.6;margin-bottom:24px;font-size:14px}
.btn{display:inline-block;background:#0D4F4F;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;transition:transform .15s}
.btn:hover{transform:translateY(-2px)}
.ft{margin-top:24px;font-size:12px;color:#999}
</style>
</head>
<body>
<div class="c">
    <h1>404</h1>
    <h2>Không tìm thấy</h2>
    <p><?php echo esc_html( $msg ); ?></p>
    <a href="<?php echo esc_url( home_url() ); ?>" class="btn">Về trang chủ</a>
    <div class="ft"><?php echo esc_html( $site_name ); ?></div>
</div>
</body>
</html>
