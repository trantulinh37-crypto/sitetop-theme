<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<script>
function togglePw(id,btn){
    var inp=document.getElementById(id);
    if(inp.type==='password'){
        inp.type='text';
        btn.innerHTML='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    }else{
        inp.type='password';
        btn.innerHTML='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    }
}
// Biến thể "Hiện mật khẩu" dạng checkbox riêng (mẫu linkx.me) — dùng cho page-login.php
// và page-register.php. KHÔNG đụng togglePw() ở trên.
function togglePwChk(id,chk){
    document.getElementById(id).type = chk.checked ? 'text' : 'password';
}
// Như trên nhưng bật/tắt NHIỀU ô cùng lúc bằng 1 checkbox — dùng ở page-forgot-password.php
// (bước đặt mật khẩu mới có 2 ô: mật khẩu mới + xác nhận, 1 checkbox điều khiển cả hai).
function togglePwChkMulti(ids,chk){
    ids.split(',').forEach(function(id){ document.getElementById(id).type = chk.checked ? 'text' : 'password'; });
}
</script>
