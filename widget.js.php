<?php
/**
 * LinkNgon V2 - Widget JavaScript
 * Widget LUÔN HIỆN - không có logic ẩn/hiện (V2)
 * Supports: 1step, 2step, nocode, direct
 * Countdown display (default 30s), onsite_time (default 70s)
 * Heartbeat: ≤15s = active shortlink
 * Adblock detection, Google click verify
 */
header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
$ajax_url = admin_url('admin-ajax.php');
$nonce = wp_create_nonce('linkngon_nonce');
?>
(function(){
'use strict';
var W = {
    ajaxUrl: '<?php echo esc_js($ajax_url); ?>',
    nonce: '<?php echo esc_js($nonce); ?>',
    visitId: null,
    countdown: 30,
    onsiteTime: 70,
    campaignType: '1step',
    remaining: 30,
    timer: null,
    heartbeatTimer: null,
    behaviorData: { mouse: 0, scroll: 0, time: 0, tabs: 0 },

    init: function() {
        var p = new URLSearchParams(window.location.search);
        this.visitId = p.get('v');
        if (!this.visitId) return;

        // Widget LUÔN HIỆN
        this.render();
        this.startHeartbeat();
        this.trackBehavior();
        this.detectAdblock();
    },

    render: function() {
        var c = document.getElementById('ln-widget-container');
        if (!c) return;
        c.innerHTML = '<div id="ln-widget-inner"></div>';
        // Initial heartbeat to get countdown info
        var self = this;
        this.ajax('linkngon_heartbeat', {visit_id: this.visitId}, function(r) {
            if (r.success) {
                self.countdown = r.data.countdown || 30;
                self.onsiteTime = r.data.onsite_time || 70;
                self.remaining = r.data.remaining || self.countdown;
                self.campaignType = r.data.campaign_type || '1step';
                if (r.data.ready) { self.getCode(); }
                else { self.startCountdown(); }
            }
        });
    },

    startCountdown: function() {
        var self = this;
        self.timer = setInterval(function() {
            self.remaining--;
            // Dispatch event for UI update
            document.dispatchEvent(new CustomEvent('ln-countdown', {detail: {remaining: Math.max(0, self.remaining)}}));
            if (self.remaining <= 0) { clearInterval(self.timer); self.getCode(); }
        }, 1000);
    },

    startHeartbeat: function() {
        var self = this;
        self.heartbeatTimer = setInterval(function() {
            self.ajax('linkngon_heartbeat', {visit_id: self.visitId}, function(r) {
                if (r.success && r.data.ready) { clearInterval(self.timer); self.getCode(); }
            });
        }, 15000); // ≤15s
    },

    getCode: function() {
        var self = this;
        self.ajax('linkngon_get_code', {visit_id: self.visitId}, function(r) {
            if (r.success) {
                document.dispatchEvent(new CustomEvent('ln-code-ready', {detail: {code: r.data.code}}));
            } else { setTimeout(function(){ self.getCode(); }, 3000); }
        });
    },

    verify: function(code) {
        var self = this;
        self.reportBehavior();
        self.ajax('linkngon_verify', {visit_id: self.visitId, code: code}, function(r) {
            if (r.success) {
                clearInterval(self.heartbeatTimer);
                document.dispatchEvent(new CustomEvent('ln-verified', {detail: r.data}));
            } else {
                document.dispatchEvent(new CustomEvent('ln-verify-error', {detail: r.data || {message:'Mã không đúng'}}));
            }
        });
    },

    trackBehavior: function() {
        var self = this;
        document.addEventListener('mousemove', function() { self.behaviorData.mouse++; });
        document.addEventListener('scroll', function() {
            self.behaviorData.scroll = Math.max(self.behaviorData.scroll,
                Math.round((window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100) || 0);
        });
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) self.behaviorData.tabs++;
        });
        setInterval(function() { self.behaviorData.time++; }, 1000);
    },

    detectAdblock: function() {
        var self = this;
        var bait = document.createElement('div');
        bait.className = 'adsbox ad-placement ad-banner';
        bait.style.cssText = 'position:absolute;left:-9999px;width:1px;height:1px;';
        document.body.appendChild(bait);
        setTimeout(function() {
            var blocked = bait.offsetHeight === 0 || bait.clientHeight === 0;
            if (blocked) {
                self.ajax('linkngon_report_behavior', {visit_id: self.visitId, adblock: 1}, function(){});
            }
            bait.remove();
        }, 100);
    },

    reportBehavior: function() {
        this.ajax('linkngon_report_behavior', {
            visit_id: this.visitId,
            mouse_movements: this.behaviorData.mouse,
            scroll_depth: this.behaviorData.scroll,
            time_on_page: this.behaviorData.time,
            tab_switches: this.behaviorData.tabs,
        }, function(){});
    },

    ajax: function(action, data, cb) {
        data.action = action; data.nonce = this.nonce;
        var fd = new FormData();
        for (var k in data) fd.append(k, data[k]);
        fetch(this.ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){return r.json()}).then(cb).catch(function(e){console.error('LN:', e)});
    }
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function(){W.init();});
else W.init();
window.LN_WIDGET = W;
})();
