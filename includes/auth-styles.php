<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<style>
*{box-sizing:border-box!important;margin:0;padding:0}
html{width:100%!important;max-width:100vw!important;overflow-x:hidden!important}
/* flex-direction:column là BẮT BUỘC, không phải tuỳ chọn thẩm mỹ.
   body là flex container, nên MỌI phần tử script chèn thêm vào <body> — widget
   SiteTop (#tn-w), nút chat, mã quảng cáo — đều thành flex item. Với hướng row
   mặc định, chúng đứng CẠNH form và ăn mất bề ngang: trên mobile 375px, #tn-w
   chiếm 180px làm form bị bóp còn 195px, chữ trong ô nhập và nút bấm vỡ hết.
   Đổi sang column thì phần tử lạ xếp xuống dưới, không tranh chỗ ngang. */
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;-webkit-font-smoothing:antialiased;width:100%!important;max-width:100vw!important;overflow-x:hidden!important;padding:0!important;margin:0!important;background:linear-gradient(135deg,#F0F4FF 0%,#E0E7FF 100%);position:relative;overflow:hidden}

/* Chốt thứ hai: dù có phần tử lạ nào chen vào body, khung form vẫn rộng hết cỡ.
   align-items:center ở trên khiến flex item co theo nội dung, nên phải ép width. */
.auth-page{width:100%}

/* Decorative background circles */
body::before{content:'';position:fixed;width:500px;height:500px;border-radius:50%;background:rgba(59,130,246,.08);top:-150px;right:-100px;pointer-events:none;filter:blur(60px)}
body::after{content:'';position:fixed;width:400px;height:400px;border-radius:50%;background:rgba(99,102,241,.07);bottom:-120px;left:-80px;pointer-events:none;filter:blur(60px)}

.auth-page{display:flex;align-items:center;justify-content:center;min-height:100vh;width:100%;padding:40px 20px;position:relative;z-index:1}

.auth-card{width:100%;max-width:440px;background:#fff;border-radius:20px;box-shadow:0 4px 24px rgba(0,0,0,.06),0 1px 4px rgba(0,0,0,.04);padding:40px;position:relative}
.auth-card.wide{max-width:520px}

.auth-logo{display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:28px}
.auth-logo a{display:inline-flex;align-items:center;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:22px;color:#0F172A;text-decoration:none;gap:8px}
.auth-logo a img{border-radius:6px}
.auth-logo>span{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:22px}
.auth-logo .lgd{color:#0F172A}
.auth-logo .lgb{background:linear-gradient(120deg,#2563EB,#38BDF8);-webkit-background-clip:text;background-clip:text;color:transparent}

form{width:100%}

.auth-form-header{margin-bottom:28px;text-align:center}
.auth-form-header h2{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:24px;color:#0F172A;margin-bottom:6px}
.auth-form-header p{font-size:14px;color:#64748B}
.auth-form-header p a{color:#3B82F6;font-weight:600;text-decoration:none}
.auth-form-header p a:hover{text-decoration:underline}

.fg-row{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
.fg{margin-bottom:18px;min-width:0}
.fg input[type="tel"]{
    width:100%;padding:13px 16px 13px 44px;border:1.5px solid #E2E8F0;border-radius:10px;
    font-family:'Inter',sans-serif;font-size:14px;color:#0F172A;transition:all .2s;background:#F8FAFC;
}
.fg input[type="tel"]:focus{outline:none;border-color:#3B82F6;box-shadow:0 0 0 3px rgba(59,130,246,.12);background:#fff}
.fg input[type="tel"]::placeholder{color:#94A3B8}
.fg label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#334155}
.fg-input-wrap{position:relative;max-width:100%}
.fg-input-wrap>svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94A3B8;pointer-events:none}
.fg input[type="text"],
.fg input[type="email"],
.fg input[type="password"]{
    width:100%;padding:13px 16px 13px 44px;border:1.5px solid #E2E8F0;border-radius:10px;
    font-family:'Inter',sans-serif;font-size:14px;color:#0F172A;transition:all .2s;background:#F8FAFC;
}
.fg input:focus{outline:none;border-color:#3B82F6;box-shadow:0 0 0 3px rgba(59,130,246,.12);background:#fff}
.fg input::placeholder{color:#94A3B8}

.pw-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94A3B8;padding:4px}
.pw-toggle:hover{color:#64748B}
.fg-input-wrap .pw-toggle~input,.fg-input-wrap input[type="password"]{padding-right:42px}

.remember-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;font-size:13px}
.remember-left{display:flex;align-items:center;gap:8px;color:#64748B}
.remember-left input[type="checkbox"]{width:16px;height:16px;accent-color:#3B82F6;border-radius:4px}
.remember-left label{margin:0;font-weight:400;cursor:pointer}

.auth-btn{
    width:100%;padding:14px;background:linear-gradient(135deg,#3B82F6,#2563EB);color:#fff;border:none;
    border-radius:12px;font-family:'Inter',sans-serif;font-size:15px;font-weight:600;
    cursor:pointer;transition:all .25s;display:flex;align-items:center;justify-content:center;gap:8px;
}
.auth-btn:hover{background:linear-gradient(135deg,#2563EB,#1D4ED8);transform:translateY(-1px);box-shadow:0 4px 16px rgba(59,130,246,.3)}
.auth-btn:active{transform:translateY(0)}

.auth-divider{display:flex;align-items:center;gap:12px;margin:24px 0;color:#CBD5E1;font-size:12px}
.auth-divider::before,.auth-divider::after{content:'';flex:1;height:1px;background:#E2E8F0}

.auth-error{display:flex;align-items:center;gap:10px;background:#FEF2F2;border:1px solid #FEE2E2;color:#991B1B;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;line-height:1.5}
.auth-error svg{flex-shrink:0}
.auth-success{display:flex;align-items:center;gap:10px;background:#F0FDF4;border:1px solid #BBF7D0;color:#166534;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;line-height:1.5}
.auth-success svg{flex-shrink:0}
.forgot-link{font-size:13px;color:#3B82F6;text-decoration:none;font-weight:500}
.forgot-link:hover{text-decoration:underline}

.auth-footer{text-align:center;margin-top:24px;font-size:13px;color:#94A3B8}
.auth-footer a{color:#3B82F6;font-weight:600;text-decoration:none}
.auth-footer a:hover{text-decoration:underline}

@media(max-width:560px){
    .auth-page{padding:16px 12px;align-items:flex-start;padding-top:24px}
    .auth-card,.auth-card.wide{max-width:100%!important;width:100%!important;padding:28px 20px;border-radius:16px}
    .fg-row{gap:0 12px}
    .fg input{font-size:16px!important}
    .auth-form-header h2{font-size:22px}
}
</style>
