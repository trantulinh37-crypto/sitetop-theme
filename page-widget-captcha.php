<?php
/**
 * Widget Captcha - Cloudflare Turnstile
 * Iframe chạy trên domain traffictop.net, embed từ widget trên web đích
 */
if ( ! defined( 'ABSPATH' ) ) {
    require_once dirname( __FILE__ ) . '/../../../wp-load.php';
}

$site_key   = get_option( 'traffictop_turnstile_site_key', '' );
$session_id = sanitize_text_field( $_GET['session_id'] ?? '' );
$origin     = esc_url_raw( $_GET['origin'] ?? '' );
if ( $origin && ! preg_match( '/^https?:\/\//i', $origin ) ) $origin = '';

header( 'X-Frame-Options: ALLOWALL' );
header( 'Content-Security-Policy: frame-ancestors *' );
// Chống page-cache (LiteSpeed/CDN) giữ bản HTML cũ — ajaxUrl từng là URL tuyệt đối
// cross-origin gây mất thưởng; bản cache cũ sẽ tái nhiễm bug dù code đã fix.
header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
header( 'X-LiteSpeed-Cache-Control: no-cache' );
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,sans-serif;display:flex;justify-content:center;align-items:center;min-height:auto;background:transparent;padding:0;margin:0;overflow:hidden}
#turnstile-widget{display:inline-block;transform:scale(0.6);transform-origin:center center;margin:-8px 0}
</style>
</head>
<body>
<?php if ( $site_key ) : ?>
<div id="turnstile-widget"></div>
<script>
var parentOrigin='<?php echo esc_js( $origin ); ?>'||'*';
// ajaxUrl PHẢI relative (same-origin với domain đang serve iframe). Iframe này load theo
// C.api của widget (domain trong script src — có thể là alias: dethitoanthpt.com,
// linkngon.top...), còn admin_url() trả về domain gốc WP (traffictop.net) → fetch
// cross-origin bị chặn (CORS/tracker-blocker/mixed-content) → transient captcha_ok không
// được set → mọi visit qua alias domain bị captcha_unverified: user mất thưởng dù làm
// đúng, khách hàng vẫn bị trừ tiền. Cùng bài học 13/04/2026 (không hardcode home_url).
var ajaxUrl='<?php echo esc_js( admin_url( 'admin-ajax.php', 'relative' ) ); ?>';
var sessionId='<?php echo esc_js( $session_id ); ?>';
function onTurnstileReady(){
    turnstile.render('#turnstile-widget',{
        sitekey:'<?php echo esc_js( $site_key ); ?>',
        callback:function(token){
            // Verify server-side (same-origin) so the visit is marked captcha-cleared in the DB/transient.
            // Only signal the parent widget to proceed once the server confirms.
            // Retry on network hiccup / non-JSON response (vd anti-DDoS trả HTML): nếu transient
            // không được set mà UI vẫn đi tiếp thì verify_and_pay sẽ cho captcha_unverified →
            // user làm đúng vẫn mất thưởng trong khi khách hàng bị trừ tiền.
            function sendToken(attempt){
                try{
                    var fd=new FormData();
                    fd.append('action','traffictop_widget_captcha');
                    fd.append('session_id',sessionId);
                    fd.append('token',token);
                    fetch(ajaxUrl,{method:'POST',body:fd,credentials:'include'})
                    .then(function(r){return r.json()})
                    .then(function(d){
                        if(d&&d.success){
                            if(window.parent!==window)window.parent.postMessage({type:'captcha_success',token:token},parentOrigin);
                        }else{
                            if(window.parent!==window)window.parent.postMessage({type:'captcha_error'},parentOrigin);
                        }
                    })
                    .catch(function(){
                        if(attempt<2){setTimeout(function(){sendToken(attempt+1);},1000*(attempt+1));return;}
                        // Hết retry → vẫn cho UI đi tiếp NHƯNG kèm token: widget (parent) sẽ tự
                        // gửi token qua kênh AJAX riêng của nó (kênh đã chạy tốt trên mọi domain
                        // nhúng) để verify + set transient — belt thứ 2 chống mất thưởng.
                        if(window.parent!==window)window.parent.postMessage({type:'captcha_success',token:token},parentOrigin);
                    });
                }catch(e){
                    if(window.parent!==window)window.parent.postMessage({type:'captcha_success',token:token},parentOrigin);
                }
            }
            sendToken(0);
        },
        'error-callback':function(){
            if(window.parent!==window)window.parent.postMessage({type:'captcha_error'},parentOrigin);
        },
        'expired-callback':function(){
            if(window.parent!==window)window.parent.postMessage({type:'captcha_expired'},parentOrigin);
        }
    });
}
if(typeof turnstile!=='undefined')onTurnstileReady();
else{var ci=setInterval(function(){if(typeof turnstile!=='undefined'){clearInterval(ci);onTurnstileReady();}},100);setTimeout(function(){clearInterval(ci);if(typeof turnstile==='undefined'&&window.parent!==window)window.parent.postMessage({type:'captcha_error'},parentOrigin);},10000);}
</script>
<?php else : ?>
<p style="color:#ef4444;font-size:12px">Captcha chưa cấu hình</p>
<?php endif; ?>
</body>
</html>
<?php exit; ?>
