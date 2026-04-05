<?php
/**
 * LinkNgon V2 - Widget JavaScript
 * Embed trên website đích để hiện countdown + get code
 *
 * Flow:
 * 1. Widget gọi linkngon_widget_verify_access (gửi URL, IP, unlock_session)
 * 2. Server tìm visit trong DB match IP + target_url
 * 3. Trả session_id + countdown info
 * 4. Widget hiện countdown → get code → copy
 *
 * CLAUDE.md: KHÔNG ĐƯỢC thay đổi logic ẩn/hiện widget
 */
header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');

$site_url = home_url();
$default_countdown = intval(get_option('linkngon_widget_default_countdown', 30));
$widget_color = get_option('linkngon_widget_color', '#0D4F4F');
$widget_text_color = get_option('linkngon_widget_text_color', '#ffffff');
$widget_icon = get_option('linkngon_widget_icon', '');
$ts_enabled = get_option('linkngon_turnstile_enabled', '0');
$ts_site_key = get_option('linkngon_turnstile_site_key', '');
$ts_key = ($ts_enabled === '1' && !empty($ts_site_key)) ? $ts_site_key : '';
?>
(function(){'use strict';
var C={
    api:'<?php echo esc_js($site_url); ?>',
    cd:<?php echo $default_countdown; ?>,
    clr:'<?php echo esc_js($widget_color); ?>',
    txtClr:'<?php echo esc_js($widget_text_color); ?>',
    icon:'<?php echo esc_js($widget_icon); ?>',
    tsKey:'<?php echo esc_js($ts_key); ?>'
};
var state={sessionId:'',countdown:C.cd,onsiteTime:70,trafficType:'1step',remaining:C.cd,codeReady:false,code:null,sessionReady:false,countdownStarted:false,captchaToken:null,isIncognito:false};
var timers={countdown:null,heartbeat:null,behavior:null};
var bdata={mouse:0,scroll:0,time:0,tabs:0,clicks:0};

// Detect incognito/private browsing
function detectIncognito(cb){
    var ua=navigator.userAgent;

    // Firefox: service worker disabled in private mode
    if(/Firefox/i.test(ua)){
        return cb(!('serviceWorker' in navigator));
    }

    // Safari: openDatabase fails in private mode
    if(/Safari/i.test(ua)&&!/Chrome/i.test(ua)){
        if(typeof window.openDatabase==='function'){
            try{window.openDatabase('_lnT','1','t',1);return cb(false);}catch(e){return cb(true);}
        }
        try{
            var r=indexedDB.open('_lnT');
            r.onerror=function(){cb(true);};
            r.onsuccess=function(){r.result.close();indexedDB.deleteDatabase('_lnT');cb(false);};
        }catch(e){cb(true);}
        return;
    }

    // Chrome/Chromium: try multiple signals
    var signals=0,checks=0,total=2;
    function evaluate(){checks++;if(checks>=total)cb(signals>0);}

    // Signal 1: persistent-storage permission = 'denied' in incognito
    if(navigator.permissions&&navigator.permissions.query){
        navigator.permissions.query({name:'persistent-storage'}).then(function(ps){
            if(ps.state==='denied')signals++;
            evaluate();
        }).catch(function(){evaluate();});
    }else{evaluate();}

    // Signal 2: storage estimate quota (< 120MB = old Chrome incognito)
    if(navigator.storage&&navigator.storage.estimate){
        navigator.storage.estimate().then(function(e){
            if(e.quota&&e.quota<120000000)signals++;
            evaluate();
        }).catch(function(){evaluate();});
    }else{evaluate();}
}

// ================================================================
// INIT: Verify access via server (match IP + URL)
// ================================================================
function init(){
    // Widget LUÔN HIỆN khi embed
    createWidget();
    trackBehavior();
    detectAdblock();
    detectIncognito(function(yes){state.isIncognito=yes;});

    // Try to find active session via server
    var unlockSession='',unlockTime='',unlockActive='',campaignType='';
    try{
        unlockSession=localStorage.getItem('tn_unlock_session')||'';
        unlockTime=localStorage.getItem('tn_unlock_time')||'';
        campaignType=localStorage.getItem('tn_campaign_type')||'';
        unlockActive=sessionStorage.getItem('tn_unlock_active')||'';
    }catch(e){}

    var x=new XMLHttpRequest();
    x.open('POST',C.api+'/wp-admin/admin-ajax.php',true);
    x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    x.onreadystatechange=function(){
        if(x.readyState!==4)return;
        if(x.status!==200)return;
        try{
            var d=JSON.parse(x.responseText);
            if(!d.success||!d.data||!d.data.session_valid||!d.data.url_valid)return;
            if(d.data.hide_code_widget)return;

            state.sessionId=d.data.session_id||'';
            if(!state.sessionId)return;

            if(d.data.countdown)state.countdown=parseInt(d.data.countdown);
            if(d.data.traffic_type)state.trafficType=d.data.traffic_type;
            if(d.data.onsite_time)state.onsiteTime=parseInt(d.data.onsite_time);

            // Save session
            try{
                localStorage.setItem('tn_session_id',state.sessionId);
                localStorage.setItem('tn_traffic_type',state.trafficType);
            }catch(e){}

            state.remaining=parseInt(d.data.onsite_time)||70;
            state.sessionReady=true;
            state.codeIsReady=false;

            // Register captcha message listener early (but don't load iframe yet)
            if(C.tsKey){
                window.addEventListener('message',function(e){
                    if(!e.data||!e.data.type)return;
                    if(e.data.type==='captcha_success'){
                        state.captchaToken=e.data.token;
                        // Show "Thành công!" for 1.5s before transitioning to countdown
                        setTimeout(function(){
                            var cap=document.getElementById('tn-captcha');
                            var btn=document.getElementById('tn-btn');
                            if(cap){cap.style.display='none';cap.onload=null;}
                            if(btn){btn.style.display='inline-flex';btn.innerHTML='<span id="tn-btn-text">Vui lòng đợi</span><span id="tn-cd"></span>';}
                            if(state.countdownStarted&&!state.codeReady){
                                startCountdown();
                                startHeartbeat();
                            }
                        },1500);
                    }else if(e.data.type==='captcha_error'||e.data.type==='captcha_expired'){
                        if(state.countdownStarted){
                            var cap=document.getElementById('tn-captcha');
                            var btn=document.getElementById('tn-btn');
                            if(cap)cap.style.display='none';
                            if(btn)btn.style.display='inline-flex';
                            state.countdownStarted=false;
                            showToast('Captcha thất bại, thử lại');
                        }
                    }
                });
            }
            // DON'T auto-start — wait for user click on "LẤY MÃ" button
        }catch(e){console.log('LN widget parse error:',e);}
    };
    x.send('action=linkngon_widget_verify_access&referer='+encodeURIComponent(document.referrer||'')+'&current_url='+encodeURIComponent(window.location.href)+'&unlock_session='+encodeURIComponent(unlockSession)+'&unlock_time='+encodeURIComponent(unlockTime)+'&unlock_active='+encodeURIComponent(unlockActive)+'&campaign_type='+encodeURIComponent(campaignType));
}

// ================================================================
// CREATE WIDGET UI - Inline tại vị trí <script> tag
// ================================================================
function createWidget(){
    if(document.getElementById('tn-w'))return;

    // Find the script tag to insert widget AFTER it
    var scripts=document.querySelectorAll('script[src*="linkngon"][src*="widget"]');
    var anchor=scripts.length?scripts[scripts.length-1]:null;

    var s=document.createElement('style');
    s.textContent='#tn-w{display:block;text-align:center;font-family:-apple-system,BlinkMacSystemFont,sans-serif;margin:10px auto;width:100%;position:relative}'+
    '#tn-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;background:'+C.clr+';color:'+C.txtClr+';padding:6px 16px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;border:none;box-shadow:0 2px 6px rgba(0,0,0,.1);transition:transform .15s;letter-spacing:.3px}'+
    '#tn-btn:hover{transform:scale(1.03)}'+
    '#tn-cd{font-size:11px;color:#fff;background:rgba(0,0,0,.25);padding:1px 8px;border-radius:20px;margin-left:4px;display:none}'+
    '#tn-toast{position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);background:#1a7a3a;color:#fff;padding:4px 12px;border-radius:6px;font-size:11px;font-weight:600;z-index:9999999;opacity:0;transition:opacity .3s;pointer-events:none;white-space:nowrap;max-width:90vw}'+
    '#tn-toast.warn{background:#d9534f;white-space:normal;text-align:center}'+
    '#tn-toast.show{opacity:1}';
    document.head.appendChild(s);

    var w=document.createElement('div');
    w.id='tn-w';
    var iconHtml=C.icon?'<img src="'+C.icon+'" style="width:16px;height:16px">':'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="14" rx="2"/><path d="M12 8V5a3 3 0 0 0-3-3h0a3 3 0 0 0-3 3v0"/><path d="M18 8V5a3 3 0 0 0-3-3h0a3 3 0 0 0-3 3v0"/><line x1="12" y1="8" x2="12" y2="22"/></svg>';
    w.innerHTML='<div id="tn-btn" onclick="window._lnWidgetClick()">'+iconHtml+'<span id="tn-btn-text">LẤY MÃ</span><span id="tn-cd"></span></div><iframe id="tn-captcha" style="display:none;border:none;width:220px;height:45px;margin-top:4px;overflow:hidden"></iframe><div id="tn-toast"></div>';

    // Insert inline at script position (not floating)
    if(anchor&&anchor.parentNode){
        anchor.parentNode.insertBefore(w,anchor.nextSibling);
    }else{
        document.body.appendChild(w);
    }
}

// ================================================================
// COUNTDOWN
// ================================================================
function startCountdown(){
    updateCountdownUI();
    timers.countdown=setInterval(function(){
        state.remaining--;
        updateCountdownUI();
        if(state.remaining<=0){
            clearInterval(timers.countdown);
            getCode();
        }
    },1000);
}
function updateCountdownUI(){
    var cd=document.getElementById('tn-cd');
    var btn=document.getElementById('tn-btn-text');
    if(cd){cd.textContent=Math.max(0,state.remaining)+'s';cd.style.display='inline';}
    if(btn)btn.textContent='Vui lòng đợi';
}

// ================================================================
// GET CODE
// ================================================================
function getCode(){
    ajax('linkngon_get_code',{session_id:state.sessionId},function(r){
        if(r.success){
            var code=r.data.code||r.data;
            showCode(code);
        }else{
            // Retry if not ready
            var msg=(r.data&&r.data.message)||'';
            if(r.data&&r.data.data&&r.data.data.remaining){
                state.remaining=r.data.data.remaining;
                startCountdown();
            }else{
                setTimeout(getCode,3000);
            }
        }
    });
}
function showCode(code){
    var btn=document.getElementById('tn-btn');
    var cd=document.getElementById('tn-cd');
    if(cd)cd.style.display='none';
    if(btn){
        btn.innerHTML='<span style="letter-spacing:2px;font-size:12px;font-weight:700">'+code+'</span>';
        btn.style.pointerEvents='auto';
        btn.style.cursor='pointer';
    }
    state.code=code;
    state.codeReady=true;
    try{localStorage.setItem('tn_btn_clicked','1');}catch(e){}
}
function showToast(msg,duration,type){
    var t=document.getElementById('tn-toast');
    if(!t)return;
    t.textContent=msg;
    t.className='';t.id='tn-toast';
    if(type)t.classList.add(type);
    t.classList.add('show');
    setTimeout(function(){t.classList.remove('show');},duration||2000);
}

// ================================================================
// HEARTBEAT (every 10s)
// ================================================================
function startHeartbeat(){
    timers.heartbeat=setInterval(function(){
        if(state.codeReady){clearInterval(timers.heartbeat);return;}
        // Only check server when LOCAL countdown finished (don't trust server ready)
        if(state.remaining>0)return;
        ajax('linkngon_unlock_heartbeat',{session_id:state.sessionId},function(r){
            if(r.success&&r.data.ready&&!state.codeReady){
                clearInterval(timers.countdown);
                getCode();
            }
        });
    },10000);
}

// ================================================================
// BEHAVIOR TRACKING
// ================================================================
function trackBehavior(){
    document.addEventListener('mousemove',function(){bdata.mouse++;});
    document.addEventListener('click',function(){bdata.clicks++;});
    document.addEventListener('scroll',function(){
        bdata.scroll=Math.max(bdata.scroll,Math.round((window.scrollY/Math.max(1,document.body.scrollHeight-window.innerHeight))*100)||0);
    });
    document.addEventListener('visibilitychange',function(){if(document.hidden)bdata.tabs++;});
    timers.behavior=setInterval(function(){bdata.time++;},1000);
}

function reportBehavior(){
    ajax('linkngon_report_behavior',{
        session_id:state.sessionId,
        mouse_movements:bdata.mouse,scroll_depth:bdata.scroll,
        time_on_page:bdata.time,tab_switches:bdata.tabs,clicks:bdata.clicks
    },function(){});
}

// ================================================================
// ADBLOCK DETECTION (improved: less false positives)
// ================================================================
function detectAdblock(){
    var bait=document.createElement('div');
    bait.className='adsbox ad-placement';
    bait.style.cssText='height:10px;width:10px;position:fixed;left:-100px;top:-100px;opacity:0.01;pointer-events:none;';
    bait.innerHTML='&nbsp;';
    document.body.appendChild(bait);
    setTimeout(function(){
        // Only report if element was actually hidden/removed by adblock extension
        var blocked=false;
        try{
            blocked=!bait.parentNode||bait.offsetParent===null||(window.getComputedStyle(bait).display==='none')||(window.getComputedStyle(bait).visibility==='hidden');
        }catch(e){blocked=true;}
        if(blocked&&state.sessionId){
            ajax('linkngon_track_adblock',{session_id:state.sessionId},function(){});
        }
        try{bait.remove();}catch(e){}
    },500);
}

// ================================================================
// URL MATCH TRACKING (auto-detect target URL visited)
// ================================================================
function trackUrlMatch(){
    if(state.sessionId){
        ajax('linkngon_track_direct_click',{session_id:state.sessionId,url_matched:1},function(){});
    }
}
// Auto-track when widget is shown (user is on target URL)
setTimeout(trackUrlMatch,2000);

// ================================================================
// AJAX HELPER
// ================================================================
function ajax(action,data,cb){
    var x=new XMLHttpRequest();
    x.open('POST',C.api+'/wp-admin/admin-ajax.php',true);
    x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    x.onreadystatechange=function(){
        if(x.readyState===4&&x.status===200){
            try{cb(JSON.parse(x.responseText));}catch(e){console.warn('LN:',e);}
        }
    };
    var params='action='+encodeURIComponent(action);
    for(var k in data)params+='&'+encodeURIComponent(k)+'='+encodeURIComponent(data[k]);
    params+='&nonce='+encodeURIComponent('<?php echo esc_js(wp_create_nonce("linkngon_nonce")); ?>');
    x.send(params);
}

// Global functions for onclick
window._lnWidgetClick=function(){
    // Block incognito/private browsing
    if(state.isIncognito){
        showToast('Bạn đang sử dụng trình duyệt ẩn danh, vui lòng tắt đi và thử lại!',4000,'warn');
        return;
    }
    // Code ready → click to copy
    if(state.codeReady&&state.code){
        if(navigator.clipboard){
            navigator.clipboard.writeText(state.code).then(function(){showToast('Đã sao chép!');});
        }else{
            var t=document.createElement('textarea');t.value=state.code;document.body.appendChild(t);t.select();document.execCommand('copy');t.remove();
            showToast('Đã sao chép!');
        }
        return;
    }
    // First click: captcha (if needed) → then countdown
    if(state.sessionReady&&!state.countdownStarted){
        state.countdownStarted=true;
        var btnEl=document.getElementById('tn-btn');

        // Reset server timer
        ajax('linkngon_widget_start_timer',{session_id:state.sessionId},function(){});

        // If no Turnstile OR already solved → start countdown directly
        if(!C.tsKey||state.captchaToken){
            if(btnEl){btnEl.innerHTML='<span id="tn-btn-text">Vui lòng đợi</span><span id="tn-cd"></span>';}
            startCountdown();
            startHeartbeat();
            return;
        }

        // Load + show captcha iframe NOW (on click)
        if(btnEl){btnEl.innerHTML='<span id="tn-btn-text">Đang tải...</span>';btnEl.style.pointerEvents='none';}
        var captcha=document.getElementById('tn-captcha');
        if(captcha){
            captcha.src=C.api+'/widget-captcha/?session_id='+encodeURIComponent(state.sessionId)+'&origin='+encodeURIComponent(location.origin);
            captcha.onload=function(){
                captcha.onload=null; // Only fire once
                if(btnEl)btnEl.style.display='none';
                captcha.style.display='inline-block';
            };
        }
        return;
    }
    // No visit session found
    if(!state.sessionReady){
        showToast('Bạn chưa truy cập shortlink');
    }
};

// Cleanup on page unload
window.addEventListener('beforeunload',function(){
    reportBehavior();
    clearInterval(timers.countdown);
    clearInterval(timers.heartbeat);
    clearInterval(timers.behavior);
});

// ================================================================
// START
// ================================================================
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);
else init();
})();
