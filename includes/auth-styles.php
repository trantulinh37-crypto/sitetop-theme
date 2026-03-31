<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<style>
*{box-sizing:border-box!important;margin:0;padding:0}
html{width:100%!important;max-width:100vw!important;overflow-x:hidden!important}
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;background:#083838;-webkit-font-smoothing:antialiased;width:100%!important;max-width:100vw!important;overflow-x:hidden!important;padding:0!important;margin:0!important}

.auth-split{display:flex;width:100%;min-height:100vh}

.auth-brand{flex:0 0 45%;background:linear-gradient(160deg,#062E2E 0%,#0D4F4F 50%,#1A7A7A 100%);display:flex;flex-direction:column;justify-content:center;padding:60px;position:relative;overflow:hidden}
.auth-brand::before{content:'';position:absolute;width:400px;height:400px;border-radius:50%;background:rgba(232,168,56,.06);top:-100px;right:-100px}
.auth-brand::after{content:'';position:absolute;width:300px;height:300px;border-radius:50%;background:rgba(232,168,56,.04);bottom:-80px;left:-60px}
.auth-brand *{position:relative;z-index:1}
.auth-brand-logo{display:inline-flex;align-items:center;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:28px;color:#fff;text-decoration:none;margin-bottom:48px}
.auth-brand-logo svg{margin-right:8px}
.auth-brand h1{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:36px;color:#fff;line-height:1.3;margin-bottom:16px}
.auth-brand h1 span{color:#E8A838}
.auth-brand p{color:rgba(255,255,255,.55);font-size:15px;line-height:1.7;max-width:380px}

.auth-features{margin-top:48px;display:flex;flex-direction:column;gap:20px}
.auth-feat{display:flex;align-items:flex-start;gap:14px}
.auth-feat-icon{width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.auth-feat-icon svg{width:20px;height:20px}
.auth-feat-text h4{font-size:14px;font-weight:600;color:#fff;margin-bottom:2px}
.auth-feat-text p{font-size:12px;color:rgba(255,255,255,.45);line-height:1.5}

.auth-form-panel{flex:1;display:flex;align-items:center;justify-content:center;padding:40px 20px;background:#fff;min-width:0}
.auth-form-wrap{width:100%;max-width:400px}
.auth-form-wrap.wide{max-width:520px}
form{width:100%}

.auth-form-header{margin-bottom:32px}
.auth-form-header h2{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:26px;color:#083838;margin-bottom:6px}
.auth-form-header p{font-size:14px;color:#6B7280}
.auth-form-header p a{color:#0D4F4F;font-weight:600;text-decoration:none}
.auth-form-header p a:hover{text-decoration:underline}

.auth-mobile-logo{display:none;text-align:center;margin-bottom:28px}
.auth-mobile-logo a{display:inline-flex;align-items:center;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:22px;color:#0D4F4F;text-decoration:none}
.auth-mobile-logo a svg{margin-right:6px}

.fg-row{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
.fg{margin-bottom:18px;min-width:0}
.fg input[type="tel"]{
    width:100%;padding:13px 16px 13px 44px;border:1.5px solid #E5E2DB;border-radius:10px;
    font-family:'Inter',sans-serif;font-size:14px;color:#2C2C3A;transition:all .2s;background:#FAFAF8;
}
.fg input[type="tel"]:focus{outline:none;border-color:#0D4F4F;box-shadow:0 0 0 3px rgba(13,79,79,.08);background:#fff}
.fg input[type="tel"]::placeholder{color:#B0B5BC}
.fg label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#2C2C3A}
.fg-input-wrap{position:relative;max-width:100%}
.fg-input-wrap>svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#9CA3AF;pointer-events:none}
.fg input[type="text"],
.fg input[type="email"],
.fg input[type="password"]{
    width:100%;padding:13px 16px 13px 44px;border:1.5px solid #E5E2DB;border-radius:10px;
    font-family:'Inter',sans-serif;font-size:14px;color:#2C2C3A;transition:all .2s;background:#FAFAF8;
}
.fg input:focus{outline:none;border-color:#0D4F4F;box-shadow:0 0 0 3px rgba(13,79,79,.08);background:#fff}
.fg input::placeholder{color:#B0B5BC}

.pw-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9CA3AF;padding:0}
.pw-toggle:hover{color:#6B7280}

.remember-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;font-size:13px}
.remember-left{display:flex;align-items:center;gap:8px;color:#6B7280}
.remember-left input[type="checkbox"]{width:16px;height:16px;accent-color:#0D4F4F;border-radius:4px}
.remember-left label{margin:0;font-weight:400;cursor:pointer}

.auth-btn{
    width:100%;padding:14px;background:linear-gradient(135deg,#0D4F4F,#1A7A7A);color:#fff;border:none;
    border-radius:10px;font-family:'Inter',sans-serif;font-size:15px;font-weight:600;
    cursor:pointer;transition:all .25s;display:flex;align-items:center;justify-content:center;gap:8px;
}
.auth-btn:hover{background:linear-gradient(135deg,#1A7A7A,#228B8B);transform:translateY(-1px);box-shadow:0 4px 16px rgba(13,79,79,.2)}
.auth-btn:active{transform:translateY(0)}

.auth-divider{display:flex;align-items:center;gap:12px;margin:24px 0;color:#D1CEC7;font-size:12px}
.auth-divider::before,.auth-divider::after{content:'';flex:1;height:1px;background:#E5E2DB}

.auth-error{display:flex;align-items:center;gap:10px;background:#FEF2F2;border:1px solid #FEE2E2;color:#991B1B;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;line-height:1.5}
.auth-error svg{flex-shrink:0}
.auth-success{display:flex;align-items:center;gap:10px;background:#F0FDF4;border:1px solid #BBF7D0;color:#166534;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;line-height:1.5}
.auth-success svg{flex-shrink:0}
.forgot-link{font-size:13px;color:#0D4F4F;text-decoration:none;font-weight:500}
.forgot-link:hover{text-decoration:underline}

.auth-footer{text-align:center;margin-top:24px;font-size:13px;color:#9CA3AF}
.auth-footer a{color:#0D4F4F;font-weight:600;text-decoration:none}
.auth-footer a:hover{text-decoration:underline}

@media(max-width:900px){
    .auth-brand{display:none}
    .auth-mobile-logo{display:block}
    .auth-form-panel{padding:32px 16px!important;width:100%!important}
    .auth-form-wrap,.auth-form-wrap.wide{max-width:100%!important;width:100%!important}
    .fg-row{gap:0 12px}
    .fg input{font-size:16px!important}
}
</style>
