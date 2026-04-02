<?php
/**
 * LinkNgon V2 - Widget JavaScript
 * Embed trên website đích (target_url) để hiện countdown + get code
 *
 * Flow:
 * 1. page-unlock.php lưu session_id vào localStorage (tn_unlock_session)
 * 2. User click vào target website
 * 3. Widget.js đọc session_id từ localStorage
 * 4. Hiện countdown → get code → user copy code về page-unlock verify
 *
 * CLAUDE.md: KHÔNG ĐƯỢC thay đổi logic ẩn/hiện widget
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
    sessionId: null,
    countdown: 30,
    onsiteTime: 70,
    trafficType: '1step',
    remaining: 30,
    timer: null,
    heartbeatTimer: null,
    codeReady: false,
    code: null,
    behaviorData: { mouse: 0, scroll: 0, time: 0, tabs: 0 },

    init: function() {
        // Read session from localStorage (saved by page-unlock.php)
        try {
            this.sessionId = localStorage.getItem('tn_unlock_session');
            // Check if unlock is active (sessionStorage = current tab only)
            var unlockActive = sessionStorage.getItem('tn_unlock_active');
            if (!this.sessionId || !unlockActive) return;
        } catch(e) { return; }

        this.createContainer();
        this.render();
        this.startHeartbeat();
        this.trackBehavior();
        this.detectAdblock();
    },

    createContainer: function() {
        // Create widget container if not exists
        if (document.getElementById('ln-widget')) return;
        var el = document.createElement('div');
        el.id = 'ln-widget';
        el.innerHTML = '<div id="ln-widget-inner" style="position:fixed;bottom:20px;right:20px;z-index:999999;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;"></div>';
        document.body.appendChild(el);
    },

    render: function() {
        var inner = document.getElementById('ln-widget-inner');
        if (!inner) return;
        inner.innerHTML = '<div style="background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.15);padding:16px 20px;min-width:220px;text-align:center"><div style="font-size:12px;color:#666;margin-bottom:8px">Đang xác minh...</div><div id="ln-w-status" style="font-size:24px;font-weight:700;color:#0D4F4F">--</div><div id="ln-w-msg" style="font-size:11px;color:#999;margin-top:4px"></div><div id="ln-w-code" style="display:none;margin-top:10px"></div></div>';

        // Initial heartbeat
        var self = this;
        this.ajax('linkngon_unlock_heartbeat', { session_id: this.sessionId }, function(r) {
            if (r.success) {
                self.onsiteTime = r.data.onsite_time || 70;
                self.trafficType = r.data.traffic_type || '1step';
                var isNocode = self.trafficType === 'nocode';
                if (isNocode || r.data.ready) {
                    self.showReady();
                } else {
                    self.remaining = r.data.remaining || 30;
                    self.startCountdown();
                }
            } else {
                self.showError('Phiên không hợp lệ');
            }
        });
    },

    startCountdown: function() {
        var self = this;
        self.updateCountdownUI();
        self.timer = setInterval(function() {
            self.remaining--;
            self.updateCountdownUI();
            if (self.remaining <= 0) {
                clearInterval(self.timer);
                self.showReady();
            }
        }, 1000);
    },

    updateCountdownUI: function() {
        var el = document.getElementById('ln-w-status');
        var msg = document.getElementById('ln-w-msg');
        if (el) el.textContent = Math.max(0, this.remaining) + 's';
        if (msg) msg.textContent = 'Vui lòng ở lại trang này';
    },

    showReady: function() {
        var self = this;
        // Get code from server
        self.ajax('linkngon_get_code', { session_id: self.sessionId }, function(r) {
            if (r.success) {
                self.code = r.data.code || r.data;
                self.codeReady = true;
                self.showCode(self.code);
            } else {
                var msg = (r.data && r.data.message) || 'Chưa sẵn sàng';
                // Retry after delay if too fast
                if (r.data && r.data.data && r.data.data.remaining) {
                    self.remaining = r.data.data.remaining;
                    self.startCountdown();
                } else {
                    setTimeout(function() { self.showReady(); }, 3000);
                }
            }
        });
    },

    showCode: function(code) {
        var el = document.getElementById('ln-w-status');
        var msg = document.getElementById('ln-w-msg');
        var codeEl = document.getElementById('ln-w-code');
        if (el) el.innerHTML = '<span style="letter-spacing:3px;color:#E8A838">' + code + '</span>';
        if (msg) msg.textContent = 'Sao chép mã này về trang xác minh';
        if (codeEl) {
            codeEl.style.display = 'block';
            codeEl.innerHTML = '<button onclick="navigator.clipboard.writeText(\'' + code + '\').then(function(){document.getElementById(\'ln-w-copy\').textContent=\'Đã sao chép!\'})" style="width:100%;padding:8px 16px;background:#0D4F4F;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer" id="ln-w-copy">Sao chép mã</button>';
        }

        // Dispatch event
        try { document.dispatchEvent(new CustomEvent('ln-code-ready', {detail: {code: code}})); } catch(e){}
    },

    showError: function(text) {
        var el = document.getElementById('ln-w-status');
        if (el) el.innerHTML = '<span style="color:#dc3232;font-size:13px">' + text + '</span>';
    },

    startHeartbeat: function() {
        var self = this;
        self.heartbeatTimer = setInterval(function() {
            if (self.codeReady) { clearInterval(self.heartbeatTimer); return; }
            self.ajax('linkngon_unlock_heartbeat', { session_id: self.sessionId }, function(r) {
                if (r.success && r.data.ready && !self.codeReady) {
                    clearInterval(self.timer);
                    self.showReady();
                }
            });
        }, 10000);
    },

    trackBehavior: function() {
        var self = this;
        document.addEventListener('mousemove', function() { self.behaviorData.mouse++; });
        document.addEventListener('scroll', function() {
            self.behaviorData.scroll = Math.max(self.behaviorData.scroll,
                Math.round((window.scrollY / Math.max(1, document.body.scrollHeight - window.innerHeight)) * 100) || 0);
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
                self.ajax('linkngon_track_adblock', { session_id: self.sessionId }, function(){});
            }
            try { bait.remove(); } catch(e){}
        }, 100);
    },

    reportBehavior: function() {
        this.ajax('linkngon_report_behavior', {
            session_id: this.sessionId,
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
        fetch(this.ajaxUrl, {method:'POST', body:fd, credentials:'include'})
            .then(function(r){return r.json()}).then(cb).catch(function(e){console.warn('LN widget:', e)});
    },

    // Clean up when done
    destroy: function() {
        clearInterval(this.timer);
        clearInterval(this.heartbeatTimer);
        var el = document.getElementById('ln-widget');
        if (el) el.remove();
        try {
            localStorage.removeItem('tn_unlock_session');
            localStorage.removeItem('tn_unlock_time');
            localStorage.removeItem('tn_campaign_type');
            sessionStorage.removeItem('tn_unlock_active');
        } catch(e){}
    }
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function(){W.init();});
else W.init();
window.LN_WIDGET = W;
})();
