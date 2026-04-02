<?php
/**
 * Widget Captcha - Cloudflare Turnstile
 * Iframe chạy trên domain linkngon.top, embed từ widget trên web đích
 */
if ( ! defined( 'ABSPATH' ) ) {
    require_once dirname( __FILE__ ) . '/../../../wp-load.php';
}

$site_key   = get_option( 'linkngon_turnstile_site_key', '' );
$session_id = sanitize_text_field( $_GET['session_id'] ?? '' );
$origin     = esc_url_raw( $_GET['origin'] ?? '' );
if ( $origin && ! preg_match( '/^https?:\/\//i', $origin ) ) $origin = '';

header( 'X-Frame-Options: ALLOWALL' );
header( 'Content-Security-Policy: frame-ancestors *' );
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;background:transparent}
#turnstile-widget{display:inline-block;transform:scale(0.75);transform-origin:center}
</style>
</head>
<body>
<?php if ( $site_key ) : ?>
<div id="turnstile-widget"></div>
<script>
var parentOrigin='<?php echo esc_js( $origin ); ?>'||'*';
function onTurnstileReady(){
    turnstile.render('#turnstile-widget',{
        sitekey:'<?php echo esc_js( $site_key ); ?>',
        callback:function(token){
            if(window.parent!==window)window.parent.postMessage({type:'captcha_success',token:token},parentOrigin);
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
