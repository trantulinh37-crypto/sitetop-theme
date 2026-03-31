<?php
/**
 * Template Name: Unlock Page
 * LinkNgon V2 - Trang countdown khi click vào shortlink
 * 
 * Flow: Visitor click shortlink → thấy trang này → đợi countdown → nhập code → redirect link gốc
 * Publisher kiếm tiền từ mỗi lượt view hợp lệ trên trang này
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$visit_id  = absint( $_GET['v'] ?? 0 );
$shortcode = sanitize_text_field( $_GET['s'] ?? '' );

if ( ! $visit_id || ! $shortcode ) { wp_redirect( home_url() ); exit; }

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';

$visit = $wpdb->get_row( $wpdb->prepare(
    "SELECT v.*, kc.countdown_seconds, kc.target_url, kc.name as campaign_name
     FROM {$prefix}shortlink_visits v
     LEFT JOIN {$prefix}keyword_campaigns kc ON v.campaign_id = kc.id
     WHERE v.id = %d AND v.shortcode = %s",
    $visit_id, $shortcode
) );

if ( ! $visit ) { wp_redirect( home_url() ); exit; }
if ( $visit->step === 'verified' ) { wp_redirect( $visit->target_url ); exit; }

$countdown = (int) ( $visit->countdown_seconds ?? 30 );
$target_domain = parse_url( $visit->target_url, PHP_URL_HOST );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đang chuyển hướng... - <?php bloginfo('name'); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=DM+Serif+Display&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<?php wp_head(); ?>
<style>
:root{--p:#0D4F4F;--pl:#1A7A7A;--pd:#083838;--a:#E8A838;--bg:#F7F5F0;--card:#fff;--txt:#2C2C3A;--txtl:#6B7280;--txtm:#9CA3AF;--brd:#E5E2DB;--ok:#059669;--err:#DC2626;--font:'Be Vietnam Pro',sans-serif;--fonth:'DM Serif Display',serif;--mono:'JetBrains Mono',monospace}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font);min-height:100vh;background:var(--bg);color:var(--txt);display:flex;flex-direction:column}

/* Top bar */
.un-top{background:var(--pd);color:#fff;padding:10px 24px;display:flex;align-items:center;justify-content:space-between;font-size:13px}
.un-top .logo{font-family:var(--fonth);font-size:18px;color:var(--a);text-decoration:none}
.un-top .dest{color:rgba(255,255,255,.5);font-size:12px}
.un-top .dest span{color:rgba(255,255,255,.8)}

/* Ad zone */
.un-ad{flex:1;display:flex;align-items:center;justify-content:center;padding:20px;background:linear-gradient(180deg,#EEEAE3 0%,var(--bg) 100%)}
.un-ad-inner{max-width:728px;width:100%;min-height:200px;background:var(--card);border:1px dashed var(--brd);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--txtm);font-size:14px;text-align:center;padding:24px}

/* Bottom unlock bar */
.un-bottom{background:var(--card);border-top:1px solid var(--brd);padding:20px 24px;box-shadow:0 -4px 20px rgba(0,0,0,.04)}
.un-bottom-inner{max-width:700px;margin:0 auto;display:flex;align-items:center;gap:20px}

/* Countdown circle */
.un-ring-wrap{flex-shrink:0;position:relative;width:80px;height:80px}
.un-ring-wrap svg{transform:rotate(-90deg)}
.un-ring-bg{fill:none;stroke:var(--brd);stroke-width:5}
.un-ring-progress{fill:none;stroke:var(--p);stroke-width:5;stroke-linecap:round;transition:stroke-dashoffset .8s linear}
.un-ring-num{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-family:var(--fonth);font-size:28px;color:var(--pd)}

/* Info section */
.un-info{flex:1}
.un-info h3{font-family:var(--fonth);font-size:16px;color:var(--pd);margin-bottom:4px}
.un-info p{font-size:13px;color:var(--txtl);line-height:1.5}
.un-info .dest-url{font-family:var(--mono);font-size:11px;color:var(--p);margin-top:4px;word-break:break-all}

/* Code area */
.un-code{display:none;flex-shrink:0}
.un-code-display{font-family:var(--mono);font-size:24px;letter-spacing:.2em;color:var(--p);padding:10px 16px;background:#F0F9F9;border-radius:8px;border:2px dashed var(--p);margin-bottom:8px;text-align:center}
.un-verify{display:flex;gap:6px}
.un-verify input{width:120px;padding:10px;border:2px solid var(--brd);border-radius:8px;font-family:var(--mono);font-size:14px;text-align:center;letter-spacing:.15em;text-transform:uppercase}
.un-verify input:focus{outline:none;border-color:var(--p);box-shadow:0 0 0 3px rgba(13,79,79,.1)}
.un-verify button{padding:10px 18px;background:var(--p);color:#fff;border:none;border-radius:8px;font-family:var(--font);font-weight:600;font-size:13px;cursor:pointer;white-space:nowrap}
.un-verify button:hover{background:var(--pl)}
.un-verify button:disabled{opacity:.5;cursor:not-allowed}
.un-msg{font-size:12px;margin-top:4px;min-height:16px}
.un-msg-err{color:var(--err)}

/* Success */
.un-success{display:none;text-align:center;padding:8px}
.un-success .icon{font-size:36px;margin-bottom:6px}
.un-success p{color:var(--ok);font-weight:600;font-size:14px}
.un-success .redir{color:var(--txtm);font-size:12px;margin-top:4px}

/* Progress bar (alternative to ring for mobile) */
.un-progress{display:none;height:4px;background:var(--brd);border-radius:2px;margin-top:8px;overflow:hidden}
.un-progress-fill{height:100%;background:linear-gradient(90deg,var(--p),var(--a));border-radius:2px;transition:width 1s linear;width:0}

@media(max-width:600px){
    .un-bottom-inner{flex-direction:column;text-align:center}
    .un-ring-wrap{width:64px;height:64px}
    .un-ring-num{font-size:22px}
    .un-verify{justify-content:center}
    .un-ad-inner{min-height:150px}
}
</style>
</head>
<body>

<!-- Top bar -->
<div class="un-top">
    <a href="<?php echo home_url(); ?>" class="logo">🔗 LinkNgon</a>
    <div class="dest">Đang chuyển đến: <span><?php echo esc_html( $target_domain ); ?></span></div>
</div>

<!-- Ad zone (placeholder - có thể đặt quảng cáo ở đây) -->
<div class="un-ad">
    <div class="un-ad-inner">
        <div>
            <div style="font-size:32px;margin-bottom:8px">📢</div>
            <p>Khu vực quảng cáo</p>
            <p style="font-size:12px;color:var(--txtm);margin-top:4px">Nội dung quảng cáo sẽ hiển thị tại đây</p>
        </div>
    </div>
</div>

<!-- Bottom unlock bar -->
<div class="un-bottom">
    <div class="un-bottom-inner">

        <!-- Countdown ring -->
        <div class="un-ring-wrap" id="ringWrap">
            <svg viewBox="0 0 80 80" width="80" height="80">
                <circle cx="40" cy="40" r="35" class="un-ring-bg"/>
                <circle cx="40" cy="40" r="35" class="un-ring-progress" id="ringEl"/>
            </svg>
            <span class="un-ring-num" id="timerNum"><?php echo $countdown; ?></span>
        </div>

        <!-- Info -->
        <div class="un-info" id="infoArea">
            <h3>Vui lòng đợi <?php echo $countdown; ?> giây</h3>
            <p>Link sẽ sẵn sàng sau khi hết thời gian chờ. Bạn đang được chuyển đến:</p>
            <div class="dest-url"><?php echo esc_html( $visit->target_url ); ?></div>
        </div>

        <!-- Code area (shown after countdown) -->
        <div class="un-code" id="codeArea">
            <div class="un-code-display" id="codeDisplay">------</div>
            <div class="un-verify">
                <input type="text" id="codeInput" placeholder="Nhập mã" maxlength="6" autocomplete="off">
                <button id="verifyBtn">Xác minh →</button>
            </div>
            <div class="un-msg" id="verifyMsg"></div>
        </div>

        <!-- Success -->
        <div class="un-success" id="successArea">
            <div class="icon">✅</div>
            <p>Đang chuyển hướng...</p>
            <div class="redir">→ <?php echo esc_html( $target_domain ); ?></div>
        </div>
    </div>

    <!-- Mobile progress bar -->
    <div class="un-progress" id="progressBar">
        <div class="un-progress-fill" id="progressFill"></div>
    </div>
</div>

<script>
(function(){
    var AJAX = '<?php echo admin_url("admin-ajax.php"); ?>';
    var NONCE = '<?php echo wp_create_nonce("linkngon_nonce"); ?>';
    var VID = <?php echo $visit_id; ?>;
    var CD = <?php echo $countdown; ?>;
    var rem = CD;

    var ring = document.getElementById('ringEl');
    var circ = 2 * Math.PI * 35;
    ring.style.strokeDasharray = circ;
    ring.style.strokeDashoffset = 0;

    // Countdown
    var timer = setInterval(function(){
        rem--;
        document.getElementById('timerNum').textContent = Math.max(0, rem);
        var pct = 1 - (rem / CD);
        ring.style.strokeDashoffset = circ * (1 - pct);
        // Progress bar
        var pf = document.getElementById('progressFill');
        if(pf) pf.style.width = (pct * 100) + '%';

        if(rem <= 0){
            clearInterval(timer);
            getCode();
        }
    }, 1000);

    // Heartbeat 15s
    var hb = setInterval(function(){
        post('linkngon_heartbeat', {visit_id:VID}, function(r){
            if(r.success && r.data.ready){ clearInterval(timer); getCode(); }
        });
    }, 15000);

    function getCode(){
        post('linkngon_get_code', {visit_id:VID}, function(r){
            if(r.success){
                document.getElementById('ringWrap').style.display='none';
                document.getElementById('infoArea').style.display='none';
                document.getElementById('codeArea').style.display='block';
                document.getElementById('codeDisplay').textContent = r.data.code;
                document.getElementById('codeInput').focus();
            } else {
                setTimeout(getCode, 3000);
            }
        });
    }

    // Verify
    document.getElementById('verifyBtn').addEventListener('click', doVerify);
    document.getElementById('codeInput').addEventListener('keypress', function(e){ if(e.key==='Enter') doVerify(); });

    function doVerify(){
        var inp = document.getElementById('codeInput');
        var msg = document.getElementById('verifyMsg');
        var btn = document.getElementById('verifyBtn');
        if(!inp.value.trim()){ msg.textContent='Vui lòng nhập mã'; msg.className='un-msg un-msg-err'; return; }
        btn.disabled=true; btn.textContent='Đang xác minh...';

        post('linkngon_verify', {visit_id:VID, code:inp.value.trim()}, function(r){
            if(r.success){
                document.getElementById('codeArea').style.display='none';
                document.getElementById('successArea').style.display='block';
                clearInterval(hb);
                setTimeout(function(){ window.location.href = r.data.target_url; }, 1500);
            } else {
                msg.textContent = (r.data && r.data.message) ? r.data.message : 'Mã không đúng';
                msg.className='un-msg un-msg-err';
                btn.disabled=false; btn.textContent='Xác minh →';
            }
        });
    }

    function post(action, data, cb){
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', NONCE);
        for(var k in data) fd.append(k, data[k]);
        fetch(AJAX, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){return r.json()}).then(cb)
            .catch(function(e){console.error('LN:',e)});
    }
})();
</script>
</body>
</html>
