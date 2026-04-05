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
$widget_btn_text = get_option('linkngon_widget_button_text', 'LẤY MÃ');
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
    tsKey:'<?php echo esc_js($ts_key); ?>',
    btnText:'<?php echo esc_js($widget_btn_text); ?>'
};
var state={sessionId:'',countdown:C.cd,onsiteTime:70,trafficType:'1step',remaining:C.cd,codeReady:false,code:null,sessionReady:false,countdownStarted:false,captchaToken:null,isIncognito:false,googleRequired:false,googleVerified:true,urlPathMatched:true,step2Done:false};
var timers={countdown:null,heartbeat:null,behavior:null};
var bdata={mouse:0,scroll:0,time:0,tabs:0,clicks:0};

// Detect incognito/private browsing (based on detectIncognito v1.6.2 by Joe Rutkowski)
function detectIncognito(cb){
    // Engine detection via toFixed error message length
    var feid=0;try{parseInt('-1').toFixed(-1)}catch(e){feid=e.message.length;}
    var isSafari=(feid===44||feid===43);
    var isChrome=(feid===51);
    var isFirefox=(feid===25);

    // Safari
    if(isSafari){
        if(navigator.storage&&navigator.storage.getDirectory){
            navigator.storage.getDirectory().then(function(){cb(false);}).catch(function(e){
                cb(typeof e.message==='string'&&e.message.indexOf('unknown transient reason')!==-1);
            });
        }else if(navigator.maxTouchPoints!==undefined){
            // Safari 13-18: IndexedDB Blob test
            var tmp='_ln'+Math.random();
            try{
                var dbReq=indexedDB.open(tmp,1);
                dbReq.onupgradeneeded=function(ev){
                    var db=ev.target.result;
                    try{db.createObjectStore('t',{autoIncrement:true}).put(new Blob());cb(false);}
                    catch(err){cb(typeof err.message==='string'&&err.message.indexOf('are not yet supported')!==-1);}
                    finally{db.close();indexedDB.deleteDatabase(tmp);}
                };
                dbReq.onerror=function(){cb(false);};
            }catch(e){cb(false);}
        }else{
            if(typeof window.openDatabase==='function'){
                try{window.openDatabase(null,null,null,null);cb(false);}catch(e){cb(true);return;}
            }
            cb(false);
        }
        return;
    }

    // Firefox
    if(isFirefox){
        if(navigator.storage&&navigator.storage.getDirectory){
            navigator.storage.getDirectory().then(function(){cb(false);}).catch(function(e){
                cb(typeof e.message==='string'&&e.message.indexOf('Security error')!==-1);
            });
        }else{
            var req=indexedDB.open('inPrivate');
            req.onerror=function(){cb(true);};
            req.onsuccess=function(){indexedDB.deleteDatabase('inPrivate');cb(false);};
        }
        return;
    }

    // Chrome/Chromium: webkitTemporaryStorage quota vs jsHeapSizeLimit
    if(isChrome&&navigator.webkitTemporaryStorage&&navigator.webkitTemporaryStorage.queryUsageAndQuota){
        var heapLimit=(window.performance&&window.performance.memory)?window.performance.memory.jsHeapSizeLimit:1073741824;
        navigator.webkitTemporaryStorage.queryUsageAndQuota(function(_,quota){
            var quotaMib=Math.round(quota/(1024*1024));
            var limitMib=Math.round(heapLimit/(1024*1024))*2;
            cb(quotaMib<limitMib);
        },function(){cb(false);});
        return;
    }

    // Fallback: old Chrome (50-75) FileSystem API
    if(window.webkitRequestFileSystem){
        window.webkitRequestFileSystem(0,1,function(){cb(false);},function(){cb(true);});
        return;
    }

    cb(false);
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

    // Check if returning from step2 (clicked internal link and came back)
    var _step2Return=false;
    var _step2SavedSession='';
    try{
        var _s2w=localStorage.getItem('tn_step2_waiting');
        var _s2c=localStorage.getItem('tn_link_clicked');
        var _s2t=parseInt(localStorage.getItem('tn_step2_time')||'0');
        _step2SavedSession=localStorage.getItem('tn_session_id')||'';
        if(_s2w==='1'&&_s2c==='1'&&_step2SavedSession&&(Date.now()-_s2t)<600000){
            _step2Return=true;
        }else{
            localStorage.removeItem('tn_step2_waiting');
            localStorage.removeItem('tn_step2_time');
            localStorage.removeItem('tn_link_clicked');
        }
    }catch(e){}

    if(_step2Return){
        initStep2Return(_step2SavedSession);
        return;
    }

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
            state.googleRequired=d.data.google_required||false;
            state.googleVerified=d.data.google_verified!==false;
            state.urlPathMatched=d.data.url_path_matched!==false;

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
    w.innerHTML='<div id="tn-btn" onclick="window._lnWidgetClick()">'+iconHtml+'<span id="tn-btn-text">'+C.btnText+'</span><span id="tn-cd"></span></div><iframe id="tn-captcha" style="display:none;border:none;width:220px;height:45px;margin-top:4px;overflow:hidden"></iframe><div id="tn-toast"></div>';

    // Insert inline at script position (not floating)
    if(anchor&&anchor.parentNode){
        anchor.parentNode.insertBefore(w,anchor.nextSibling);
    }else{
        document.body.appendChild(w);
    }
}

// ================================================================
// COUNTDOWN (with visibility + mouse activity checks)
// ================================================================
var _cdPaused=false;
var _lastMouseMove=0;
var _mouseIdleLimit=20000; // 20 giây không di chuyển chuột → dừng countdown
var _mouseCheckTimer=null;
var _visListenerAdded=false;

function _onVisChange(){
    if(document.hidden){
        _pauseCountdown('tab_hidden');
    }else{
        _lastMouseMove=Date.now();
        _resumeCountdown();
    }
}
function _onMouseMove(){
    _lastMouseMove=Date.now();
    if(_cdPaused)_resumeCountdown();
}
function _checkMouseIdle(){
    if(!state.countdownStarted||_cdPaused||state.remaining<=0)return;
    if(Date.now()-_lastMouseMove>_mouseIdleLimit){
        _pauseCountdown('mouse_idle');
    }
}
function _pauseCountdown(reason){
    if(_cdPaused)return;
    _cdPaused=true;
    if(timers.countdown){clearInterval(timers.countdown);timers.countdown=null;}
    var btn=document.getElementById('tn-btn-text');
    if(btn)btn.textContent=reason==='mouse_idle'?'Di chuyển chuột để tiếp tục':'Quay lại để tiếp tục';
}
function _resumeCountdown(){
    if(!_cdPaused||state.remaining<=0)return;
    _cdPaused=false;
    _startCountdownInterval();
    updateCountdownUI();
}
function _startCountdownInterval(){
    if(timers.countdown)clearInterval(timers.countdown);
    timers.countdown=setInterval(function(){
        if(document.hidden){_pauseCountdown('tab_hidden');return;}
        if(Date.now()-_lastMouseMove>_mouseIdleLimit){_pauseCountdown('mouse_idle');return;}
        state.remaining--;
        updateCountdownUI();
        if(state.remaining<=0){
            clearInterval(timers.countdown);timers.countdown=null;
            if(_mouseCheckTimer){clearInterval(_mouseCheckTimer);_mouseCheckTimer=null;}
            if(state.trafficType==='2step'&&!state.step2Done){
                showStep2Guide();
            }else{
                getCode();
            }
        }
    },1000);
}
function startCountdown(){
    _lastMouseMove=Date.now();
    _cdPaused=false;
    updateCountdownUI();
    _startCountdownInterval();
    // Mouse idle check mỗi 2 giây
    if(_mouseCheckTimer)clearInterval(_mouseCheckTimer);
    _mouseCheckTimer=setInterval(_checkMouseIdle,2000);
    // Visibility + mouse listeners (chỉ thêm 1 lần)
    if(!_visListenerAdded){
        _visListenerAdded=true;
        document.addEventListener('visibilitychange',_onVisChange);
        document.addEventListener('mousemove',_onMouseMove);
        document.addEventListener('touchstart',_onMouseMove);
        document.addEventListener('touchmove',_onMouseMove);
        document.addEventListener('click',_onMouseMove);
        document.addEventListener('keydown',_onMouseMove);
        document.addEventListener('scroll',_onMouseMove);
    }
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

// ================================================================
// STEP 2 GUIDE - Hiện hướng dẫn click link nội bộ
// ================================================================
function showStep2Guide(){
    if(document.getElementById('tn-guide'))return;
    var btn=document.getElementById('tn-btn');
    if(btn)btn.style.display='none';

    var internalLinks=getInternalLinks();
    var linksHtml='';
    if(internalLinks.length>0){
        linksHtml='<div style="margin-top:8px;">';
        linksHtml+='<div style="display:flex;justify-content:center;margin-bottom:4px;animation:tnPointerBounce 0.8s ease-in-out infinite;"><svg width="20" height="20" viewBox="0 0 24 24" fill="#dc2626"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg></div>';
        linksHtml+='<div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;">';
        internalLinks.forEach(function(link,i){
            var extra=i===0?'animation:tnBtnPulse 1.5s ease-in-out infinite;box-shadow:0 0 0 3px rgba(245,158,11,0.4);':'';
            linksHtml+='<a href="'+link.url+'" class="tn-step2-link" style="display:inline-block;padding:6px 12px;background:#f59e0b;color:#fff;border-radius:6px;text-decoration:none;font-size:11px;font-weight:600;transition:all 0.2s;'+extra+'" onmouseover="this.style.background=\'#d97706\';this.style.transform=\'scale(1.05)\'" onmouseout="this.style.background=\'#f59e0b\';this.style.transform=\'scale(1)\'">'+link.text+'</a>';
        });
        linksHtml+='</div>';
        linksHtml+='<div style="display:flex;justify-content:center;margin-top:6px;animation:tnToastBounce 1s ease-in-out infinite;"><span style="background:#1f2937;color:#fff;padding:5px 12px;border-radius:16px;font-size:10px;font-weight:600;box-shadow:0 2px 8px rgba(0,0,0,0.2);">👆 Click vào đây</span></div>';
        linksHtml+='</div>';
        linksHtml+='<style>@keyframes tnToastBounce{0%,100%{transform:translateY(0)}50%{transform:translateY(3px)}}@keyframes tnPointerBounce{0%,100%{transform:translateY(0)}50%{transform:translateY(3px)}}@keyframes tnBtnPulse{0%,100%{box-shadow:0 0 0 3px rgba(245,158,11,0.4)}50%{box-shadow:0 0 0 6px rgba(245,158,11,0.2)}}</style>';
    }

    var guide=document.createElement('div');
    guide.id='tn-guide';
    guide.style.cssText='display:flex;flex-direction:column;align-items:center;gap:10px;padding:14px 16px;background:linear-gradient(135deg,#fef3c7,#fed7aa);border-radius:12px;border:2px solid #f59e0b;text-align:center;max-width:320px;margin:0 auto;';
    guide.innerHTML='<div style="width:44px;height:44px;background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:50%;display:flex;align-items:center;justify-content:center;"><svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M13.5 5.5C14.59 5.5 15.5 4.58 15.5 3.5S14.59 1.5 13.5 1.5 11.5 2.42 11.5 3.5s.91 2 2 2zM9.89 19.38l1-4.38L13 17v6h2v-7.5l-2.11-2 .61-3A7.35 7.35 0 0 0 19 13v-2a5.32 5.32 0 0 1-4.39-2.33l-1-1.67A2 2 0 0 0 12 6a2.15 2.15 0 0 0-.89.21L6 8.83V13h2V9.83l1.89-.94L8.2 17l-4.7 1.3.5 1.9 6.89-1.82z"/></svg></div>'+
        '<div style="font-size:14px;font-weight:700;color:#92400e;">Gần xong rồi!</div>'+
        '<div style="font-size:12px;color:#78350f;line-height:1.6;text-align:center;padding:0 5px;">Click vào <b style="color:#dc2626;">1 link</b> bên dưới</div>'+
        linksHtml+
        '<div style="font-size:11px;color:#a16207;margin-top:4px;">↩️ Sau đó <b>quay lại</b> để nhận mã</div>';

    var w=document.getElementById('tn-w');
    if(w)w.appendChild(guide);

    try{
        localStorage.setItem('tn_step2_waiting','1');
        localStorage.setItem('tn_step2_time',Date.now().toString());
        localStorage.setItem('tn_session_id',state.sessionId);
    }catch(e){}

    listenForLinkClick();
}

function getInternalLinks(){
    var currentHost=window.location.hostname;
    var currentPath=window.location.pathname;
    var links=[],seen={},maxLinks=5;

    // Ưu tiên link trong menu/nav
    var menuLinks=document.querySelectorAll('nav a, .menu a, .nav a, header a, #menu a, .navbar a');
    menuLinks.forEach(function(a){
        if(links.length>=maxLinks)return;
        var href=a.href,text=(a.textContent||'').trim();
        if(href&&text&&text.length>0&&text.length<20&&href.indexOf(currentHost)!==-1){
            try{if(new URL(href).pathname===currentPath)return;}catch(e){return;}
            if(!seen[href]&&!href.includes('#')&&!href.includes('javascript:')&&!href.includes('tel:')&&!href.includes('mailto:')){
                seen[href]=true;
                links.push({url:href,text:text});
            }
        }
    });

    // Nếu chưa đủ, lấy thêm từ footer
    if(links.length<maxLinks){
        var footerLinks=document.querySelectorAll('footer a');
        footerLinks.forEach(function(a){
            if(links.length>=maxLinks)return;
            var href=a.href,text=(a.textContent||'').trim();
            if(href&&text&&text.length>0&&text.length<20&&href.indexOf(currentHost)!==-1){
                try{if(new URL(href).pathname===currentPath)return;}catch(e){return;}
                if(!seen[href]&&!href.includes('#')&&!href.includes('javascript:')&&!href.includes('tel:')&&!href.includes('mailto:')){
                    seen[href]=true;
                    links.push({url:href,text:text});
                }
            }
        });
    }
    return links;
}

function listenForLinkClick(){
    var currentHost=window.location.hostname;
    document.addEventListener('click',function handler(e){
        var target=e.target;
        while(target&&target.tagName!=='A')target=target.parentElement;
        if(target&&target.tagName==='A'){
            var href=target.getAttribute('href');
            if(href){
                var isInternal=false;
                if(href.startsWith('/')||href.startsWith('./')){isInternal=true;}
                else{try{if(new URL(href,window.location.origin).hostname===currentHost)isInternal=true;}catch(e){}}
                if(isInternal&&!href.startsWith('#')){
                    try{localStorage.setItem('tn_link_clicked','1');}catch(e){}
                    document.removeEventListener('click',handler);
                }
            }
        }
    });
}

// ================================================================
// STEP 2 RETURN - Quay lại từ step2, hiện widget lấy mã
// ================================================================
function initStep2Return(savedSession){
    try{
        localStorage.removeItem('tn_step2_waiting');
        localStorage.removeItem('tn_step2_time');
        localStorage.removeItem('tn_link_clicked');
    }catch(e){}

    var btn=document.getElementById('tn-btn');
    if(!btn)return;

    btn.onclick=function(){
        btn.onclick=null;
        btn.innerHTML='<span id="tn-btn-text">Vui lòng đợi</span><span id="tn-cd" style="display:inline">15s</span>';

        // Gọi start_timer để reset server timer
        ajax('linkngon_widget_start_timer',{session_id:savedSession,step2:'1'},function(){});

        // Countdown 15 giây rồi lấy mã
        var sec=15;
        var cdEl=document.getElementById('tn-cd');
        var t=setInterval(function(){
            sec--;
            if(sec>0){
                if(cdEl)cdEl.textContent=sec+'s';
            }else{
                clearInterval(t);
                if(cdEl)cdEl.style.display='none';
                // Lấy mã
                ajax('linkngon_get_code',{session_id:savedSession},function(r){
                    if(r.success){
                        var code=r.data.code||r.data;
                        showCode(code);
                    }else{
                        var btnText=document.getElementById('tn-btn-text');
                        if(btnText)btnText.textContent='Lỗi, thử lại';
                    }
                });
            }
        },1000);
    };
}

// Global functions for onclick
window._lnWidgetClick=function(){
    // Block incognito/private browsing
    if(state.isIncognito){
        showToast('Bạn đang sử dụng trình duyệt ẩn danh, vui lòng tắt đi và thử lại!',4000,'warn');
        return;
    }
    // Block if keyword campaign but didn't come from Google
    if(state.googleRequired&&!state.googleVerified){
        showToast('Bạn cần tìm kiếm từ khóa trên Google và click vào kết quả đúng!',4000,'warn');
        return;
    }
    // Block if URL path doesn't match target
    if(!state.urlPathMatched){
        showToast('Bạn đang ở sai trang, hãy truy cập đúng URL được yêu cầu!',4000,'warn');
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
