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
?>
(function(){'use strict';
var C={
    api:'<?php echo esc_js($site_url); ?>',
    cd:<?php echo $default_countdown; ?>,
    clr:'<?php echo esc_js($widget_color); ?>',
    txtClr:'<?php echo esc_js($widget_text_color); ?>',
    icon:'<?php echo esc_js($widget_icon); ?>'
};
var state={sessionId:'',countdown:C.cd,onsiteTime:70,trafficType:'1step',remaining:C.cd,codeReady:false,code:null,sessionReady:false,countdownStarted:false};
var timers={countdown:null,heartbeat:null,behavior:null};
var bdata={mouse:0,scroll:0,time:0,tabs:0,clicks:0};

// ================================================================
// INIT: Verify access via server (match IP + URL)
// ================================================================
function init(){
    // Widget LUÔN HIỆN khi embed
    createWidget();
    trackBehavior();
    detectAdblock();

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

            state.remaining=parseInt(d.data.onsite_time)||70; // Full onsite_time, countdown starts on click
            state.sessionReady=true;
            state.codeIsReady=false; // Always require countdown from click moment
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
    '#tn-toast{position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);background:#1a7a3a;color:#fff;padding:4px 12px;border-radius:6px;font-size:11px;font-weight:600;z-index:9999999;opacity:0;transition:opacity .3s;pointer-events:none;white-space:nowrap}'+
    '#tn-toast.show{opacity:1}';
    document.head.appendChild(s);

    var w=document.createElement('div');
    w.id='tn-w';
    var iconHtml=C.icon?'<img src="'+C.icon+'" style="width:16px;height:16px">':'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="14" rx="2"/><path d="M12 8V5a3 3 0 0 0-3-3h0a3 3 0 0 0-3 3v0"/><path d="M18 8V5a3 3 0 0 0-3-3h0a3 3 0 0 0-3 3v0"/><line x1="12" y1="8" x2="12" y2="22"/></svg>';
    w.innerHTML='<div id="tn-btn" onclick="window._lnWidgetClick()">'+iconHtml+'<span id="tn-btn-text">LẤY MÃ</span><span id="tn-cd"></span></div><div id="tn-toast"></div>';

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
            state.code=code;
            state.codeReady=true;
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
    // Replace button content with code (click to copy)
    if(btn){
        btn.innerHTML='<span style="letter-spacing:2px;font-size:12px;font-weight:700">'+code+'</span>';
    }
    try{localStorage.setItem('tn_btn_clicked','1');}catch(e){}
}
function showToast(msg){
    var t=document.getElementById('tn-toast');
    if(!t)return;
    t.textContent=msg;
    t.classList.add('show');
    setTimeout(function(){t.classList.remove('show');},2000);
}

// ================================================================
// HEARTBEAT (every 10s)
// ================================================================
function startHeartbeat(){
    timers.heartbeat=setInterval(function(){
        if(state.codeReady){clearInterval(timers.heartbeat);return;}
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
// ADBLOCK DETECTION
// ================================================================
function detectAdblock(){
    var bait=document.createElement('div');
    bait.className='adsbox ad-placement ad-banner';
    bait.style.cssText='position:absolute;left:-9999px;width:1px;height:1px;';
    document.body.appendChild(bait);
    setTimeout(function(){
        if(bait.offsetHeight===0||bait.clientHeight===0){
            ajax('linkngon_track_adblock',{session_id:state.sessionId},function(){});
        }
        try{bait.remove();}catch(e){}
    },100);
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
    // First click: reset server timer + start countdown
    if(state.sessionReady&&!state.countdownStarted){
        state.countdownStarted=true;
        // Tell server to reset created_at = NOW (onsite starts from click)
        ajax('linkngon_widget_start_timer',{session_id:state.sessionId},function(r){
            // Timer reset on server, now countdown locally
            startCountdown();
            startHeartbeat();
        });
        // Start countdown immediately (don't wait for AJAX response)
        startCountdown();
        startHeartbeat();
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
