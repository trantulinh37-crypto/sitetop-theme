<?php
/**
 * Custom Database Error Page for Traffictop.net
 * File này được copy vào wp-content/db-error.php bởi theme
 * WordPress tự động load file này khi không kết nối được DB
 */
header('HTTP/1.1 503 Service Temporarily Unavailable');
header('Retry-After: 30');
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Traffictop.net - Bảo trì hệ thống</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#f5f7fa 0%,#e4e9f0 100%);padding:20px}
.card{background:#fff;border-radius:20px;padding:48px 36px;max-width:460px;width:100%;text-align:center;box-shadow:0 8px 40px rgba(0,0,0,.08)}
.icon{width:80px;height:80px;border-radius:50%;background:#FEF3C7;display:inline-flex;align-items:center;justify-content:center;margin-bottom:24px}
h1{font-size:22px;font-weight:700;color:#1a1a1a;margin-bottom:12px}
p{font-size:15px;color:#6b7280;line-height:1.7;margin-bottom:20px}
.timer{font-size:13px;color:#9ca3af;margin-bottom:24px}
.timer span{font-weight:700;color:#f59e0b}
.btn{display:inline-block;padding:12px 32px;background:#0D4F4F;color:#fff;border-radius:10px;text-decoration:none;font-weight:600;font-size:14px;transition:transform .2s,box-shadow .2s}
.btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(13,79,79,.3)}
.logo{font-size:18px;font-weight:800;color:#0D4F4F;margin-bottom:32px;letter-spacing:-0.5px}
</style>
</head>
<body>
<div class="card">
    <div class="logo">Traffictop.net</div>
    <div class="icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
    </div>
    <h1>Hệ thống đang bảo trì</h1>
    <p>Chúng tôi đang cập nhật hệ thống để mang đến trải nghiệm tốt hơn. Vui lòng quay lại sau ít phút.</p>
    <div class="timer">Tự động tải lại sau <span id="cd">30</span> giây</div>
    <a href="javascript:location.reload()" class="btn">Thử lại ngay</a>
</div>
<script>
var c=30,t=document.getElementById('cd');
setInterval(function(){c--;if(c<=0)location.reload();else t.textContent=c},1000);
</script>
</body>
</html>
