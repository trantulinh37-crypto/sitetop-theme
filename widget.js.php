<?php
/**
 * Widget JS Endpoint với Full Anti-Cheat
 * Captcha chạy trong iframe từ domain shortlink
 */

// ============================================================================
// ADBLOCK PROBE SHORT-CIRCUIT — page-unlock.php gọi widget.js.php?probe=1 (HEAD)
// CHỈ để kiểm tra adblocker có chặn URL widget hay không. Trả 200 rỗng NGAY,
// TRƯỚC mọi logic chống spam/DDoS + wp-load. Probe có cache-buster (&t=Date.now())
// → luôn uncached → luôn hit PHP; không nên boot WordPress + không nên tính vào
// rate-limit cho 1 HEAD probe. Adblock chặn URL → XHR fail → banner; server 200 →
// không banner. (Trước đây probe từng trip burst-block làm nút mất.)
// ============================================================================
if (isset($_GET['probe'])) {
    http_response_code(200);
    header('Content-Type: application/javascript; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo '(function(){})();';
    exit;
}

// ============================================================================
// REFERRER SPAM BLOCK - CHẶN NGAY TỪ ĐẦU (KHÔNG TỐN CPU)
// + IP BLOCKING: Khi phát hiện spam referrer, block IP luôn
// ============================================================================

// Get real IP sớm nhất có thể (replicate taskify_get_real_ip logic vì WP chưa load)
// SECURITY: Chỉ trust CF-Connecting-IP nếu REMOTE_ADDR thuộc Cloudflare IP range
// Không blindly trust X-Forwarded-For vì attacker có thể spoof header để bypass IP blocking
$_wjs_remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
$_wjs_cf_ranges = ['173.245.48.0/20','103.21.244.0/22','103.22.200.0/22','103.31.4.0/22','141.101.64.0/18','108.162.192.0/18','190.93.240.0/20','188.114.96.0/20','197.234.240.0/22','198.41.128.0/17','162.158.0.0/15','104.16.0.0/13','104.24.0.0/14','172.64.0.0/13','131.0.72.0/22'];
$_wjs_is_cf = false;
if (!empty($_wjs_remote_addr)) {
    $remote_long = ip2long($_wjs_remote_addr);
    if ($remote_long !== false) {
        foreach ($_wjs_cf_ranges as $range) {
            list($subnet, $bits) = explode('/', $range);
            $subnet_long = ip2long($subnet);
            $mask = -1 << (32 - intval($bits));
            if (($remote_long & $mask) === ($subnet_long & $mask)) {
                $_wjs_is_cf = true;
                break;
            }
        }
    }
}
if ($_wjs_is_cf && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $spam_client_ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
} else {
    $spam_client_ip = $_wjs_remote_addr;
}
$spam_ip_hash = md5($spam_client_ip);

// Tính IPv6 prefix (4 phần đầu) để block cả mạng con
$spam_ipv6_prefix = '';
$spam_ipv6_prefix_hash = '';
if (filter_var($spam_client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    $ip_parts = explode(':', $spam_client_ip);
    if (count($ip_parts) >= 4) {
        $spam_ipv6_prefix = $ip_parts[0] . ':' . $ip_parts[1] . ':' . $ip_parts[2] . ':' . $ip_parts[3];
        $spam_ipv6_prefix_hash = md5($spam_ipv6_prefix);
    }
}

// Thư mục lưu IP bị block do spam referrer
$spam_block_dir = sys_get_temp_dir() . '/taskify_spam_block/';
if (!is_dir($spam_block_dir)) {
    @mkdir($spam_block_dir, 0755, true);
}

// CHECK 1A: Kiểm tra IPv6 prefix đã bị block chưa (ưu tiên cao nhất)
if (!empty($spam_ipv6_prefix_hash)) {
    $spam_prefix_block_file = $spam_block_dir . 'prefix_' . $spam_ipv6_prefix_hash . '.dat';
    if (file_exists($spam_prefix_block_file)) {
        $spam_block_until = intval(@file_get_contents($spam_prefix_block_file));
        if (time() < $spam_block_until) {
            // IPv6 prefix đang bị block - từ chối ngay
            http_response_code(403);
            header('Content-Type: application/javascript');
            header('X-Blocked-Reason: ipv6-prefix-spam-blocked');
            header('X-Block-Remaining: ' . ($spam_block_until - time()));
            header('Cache-Control: no-store');
            echo '(function(){})();';
            exit;
        } else {
            @unlink($spam_prefix_block_file);
        }
    }
}

// CHECK 1B: Kiểm tra IP đã bị block chưa (trước khi làm bất cứ điều gì)
$spam_block_file = $spam_block_dir . 'ip_' . $spam_ip_hash . '.dat';
if (file_exists($spam_block_file)) {
    $spam_block_until = intval(@file_get_contents($spam_block_file));
    if (time() < $spam_block_until) {
        // IP đang bị block - từ chối ngay
        http_response_code(403);
        header('Content-Type: application/javascript');
        header('X-Blocked-Reason: ip-spam-blocked');
        header('X-Block-Remaining: ' . ($spam_block_until - time()));
        header('Cache-Control: no-store');
        echo '(function(){})();';
        exit;
    } else {
        @unlink($spam_block_file); // Hết hạn, xóa file
    }
}

// Danh sách domain mặc định (hardcoded để chặn nhanh khi chưa có cache)
$blocked_referrers = array(
    'lu88.pro',
    'lu88.net',
    'lu88.com',
);

// Đọc thêm danh sách từ file cache (được cập nhật từ admin dashboard)
$cache_file = __DIR__ . '/cache/blocked-referrers.php';
if (file_exists($cache_file)) {
    $cached_referrers = @include($cache_file);
    if (is_array($cached_referrers)) {
        $blocked_referrers = array_unique(array_merge($blocked_referrers, $cached_referrers));
    }
}

$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
if (!empty($referer)) {
    foreach ($blocked_referrers as $blocked_domain) {
        if (empty($blocked_domain)) continue;
        if (stripos($referer, $blocked_domain) !== false) {
            // SPAM REFERRER DETECTED!
            // Block IP này trong 60 phút (3600 giây)
            $spam_block_duration = 3600;
            @file_put_contents($spam_block_file, time() + $spam_block_duration);

            // Nếu là IPv6, block cả prefix (4 phần đầu) trong 30 phút
            // Để chặn cả botnet gửi từ cùng mạng con
            if (!empty($spam_ipv6_prefix_hash)) {
                $spam_prefix_block_duration = 1800; // 30 phút
                $spam_prefix_block_file = $spam_block_dir . 'prefix_' . $spam_ipv6_prefix_hash . '.dat';
                @file_put_contents($spam_prefix_block_file, time() + $spam_prefix_block_duration);
                error_log("Widget SPAM BLOCKED IPv6 PREFIX: $spam_ipv6_prefix:*, referrer=$referer, blocked for {$spam_prefix_block_duration}s");
            }

            // Log để theo dõi
            error_log("Widget SPAM BLOCKED: IP=$spam_client_ip, referrer=$referer, blocked for {$spam_block_duration}s");

            // Chặn ngay lập tức
            http_response_code(403);
            header('Content-Type: application/javascript');
            header('X-Blocked-Reason: referrer-spam');
            header('X-IP-Blocked-For: ' . $spam_block_duration);
            header('Cache-Control: no-store');
            echo '(function(){})();';
            exit;
        }
    }
}

// ============================================================================
// ANTI-DDOS PROTECTION - ULTRA FAST (NO DATABASE)
// ============================================================================

// Get real IP (reuse logic from spam block above)
// SECURITY: Chỉ trust CF header nếu REMOTE_ADDR thuộc Cloudflare range (đã check ở trên)
if ($_wjs_is_cf && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $client_ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
} else {
    $client_ip = $_wjs_remote_addr;
}

// Validate IP
if (!filter_var($client_ip, FILTER_VALIDATE_IP)) {
    http_response_code(403);
    header('Content-Type: application/javascript');
    echo '(function(){console.error("Invalid request");})();';
    exit;
}

// ============================================================================
// GLOBAL RATE LIMITING - Protect against distributed attacks (botnet)
// ============================================================================

$rate_dir = sys_get_temp_dir() . '/taskify_rate/';
if (!is_dir($rate_dir)) {
    @mkdir($rate_dir, 0755, true);
}

// Global config
$global_config = array(
    'max_per_second' => 100,      // Max 100 requests/second TOÀN BỘ
    'max_per_10sec' => 500,       // Max 500 requests/10 seconds
    'emergency_threshold' => 200, // Bật emergency mode khi > 200 req/s
    'emergency_duration' => 60,   // Emergency mode kéo dài 60s
);

$global_rate_file = $rate_dir . 'global_rate.dat';
$emergency_file = $rate_dir . 'emergency_mode.dat';
$challenge_file = $rate_dir . 'challenge_mode.dat';

// ============================================================================
// CHALLENGE MODE - Yêu cầu Captcha thay vì block tất cả
// Cho phép user thật đi qua, chỉ chặn bot
// ============================================================================
$challenge_mode = false;
if (file_exists($challenge_file)) {
    $challenge_until = intval(@file_get_contents($challenge_file));
    if (time() < $challenge_until) {
        $challenge_mode = true;
        
        // Kiểm tra xem user đã có challenge token chưa
        $challenge_token = $_GET['ct'] ?? $_COOKIE['sitetop_ct'] ?? '';
        $challenge_valid = false;
        
        if (!empty($challenge_token)) {
            // Verify token (format: md5(ip + secret + hour))
            // FIX: Dùng wp_salt thay vì hardcoded secret
            $challenge_secret = defined('AUTH_SALT') ? AUTH_SALT : 'taskify_challenge_fallback';
            $expected_token = md5($client_ip . $challenge_secret . date('YmdH'));
            $expected_token_prev = md5($client_ip . $challenge_secret . date('YmdH', strtotime('-1 hour')));
            
            if ($challenge_token === $expected_token || $challenge_token === $expected_token_prev) {
                $challenge_valid = true;
            }
        }
        
        if (!$challenge_valid) {
            // Chưa có token hoặc token không hợp lệ → Trả về JS yêu cầu challenge
            http_response_code(200);
            header('Content-Type: application/javascript');
            header('X-DDoS-Protection: challenge-required');
            
            // Tạo challenge token cho IP này
            $new_token = md5($client_ip . $challenge_secret . date('YmdH'));
            
            echo "(function(){
'use strict';
console.warn('[Widget] Challenge mode active - verification required');

// Hiển thị Challenge UI
var overlay = document.createElement('div');
overlay.id = 'taskify-challenge';
overlay.innerHTML = '<div style=\"position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center;z-index:999999;font-family:-apple-system,sans-serif;\"><div style=\"background:white;padding:30px;border-radius:16px;text-align:center;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,0.3);\"><div style=\"font-size:3rem;margin-bottom:15px;\">🛡️</div><h2 style=\"margin:0 0 10px;color:#1e293b;font-size:1.3rem;\">Xác minh bạn là người thật</h2><p style=\"color:#64748b;margin:0 0 20px;font-size:0.9rem;\">Hệ thống đang được bảo vệ. Vui lòng xác minh để tiếp tục.</p><div id=\"cf-turnstile-challenge\" style=\"display:flex;justify-content:center;margin-bottom:15px;\"></div><p style=\"color:#94a3b8;font-size:0.75rem;margin:0;\">Powered by Cloudflare Turnstile</p></div></div>';
document.body.appendChild(overlay);

// Load Turnstile
var script = document.createElement('script');
script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileLoad';
script.async = true;
document.head.appendChild(script);

window.onTurnstileLoad = function() {
    turnstile.render('#cf-turnstile-challenge', {
        sitekey: '" . esc_js(get_option('sitetop_turnstile_site_key', '')) . "',
        callback: function(token) {
            // Xác minh thành công → Set cookie và reload
            document.cookie = 'sitetop_ct=" . $new_token . ";path=/;max-age=3600;SameSite=Lax';
            setTimeout(function() {
                overlay.innerHTML = '<div style=\"position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center;z-index:999999;\"><div style=\"background:white;padding:30px;border-radius:16px;text-align:center;\"><div style=\"font-size:3rem;margin-bottom:10px;\">✅</div><p style=\"color:#10b981;font-weight:600;\">Xác minh thành công!</p></div></div>';
                setTimeout(function() { location.reload(); }, 1000);
            }, 500);
        },
        'error-callback': function() {
            console.error('Turnstile error');
        }
    });
};
})();";
            exit;
        }
        // Token hợp lệ → Tiếp tục xử lý bình thường
    } else {
        @unlink($challenge_file);
    }
}

// ============================================================================
// EMERGENCY MODE - Block ALL (chỉ dùng khi cực kỳ nghiêm trọng)
// ============================================================================
$emergency_mode = false;
if (file_exists($emergency_file)) {
    $emergency_until = intval(@file_get_contents($emergency_file));
    if (time() < $emergency_until) {
        $emergency_mode = true;
        // Return cached/minimal JS in emergency mode
        http_response_code(503);
        header('Content-Type: application/javascript');
        header('Retry-After: 30');
        header('X-DDoS-Protection: emergency');
        echo '(function(){console.warn("Server is under heavy load. Please try again later.");})();';
        exit;
    } else {
        @unlink($emergency_file);
    }
}

// Global rate check
$now = time();
$now_micro = microtime(true);
$global_data = array('requests' => array(), 'last_cleanup' => $now);

if (file_exists($global_rate_file)) {
    $content = @file_get_contents($global_rate_file);
    if ($content) {
        $global_data = @json_decode($content, true);
        if (!is_array($global_data)) {
            $global_data = array('requests' => array(), 'last_cleanup' => $now);
        }
    }
}

// Cleanup old requests (keep last 10 seconds only for performance)
if ($now - ($global_data['last_cleanup'] ?? 0) >= 5) {
    $global_data['requests'] = array_filter($global_data['requests'], function($ts) use ($now_micro) {
        return ($now_micro - $ts) < 10;
    });
    $global_data['last_cleanup'] = $now;
}

// Count global requests
$global_requests = $global_data['requests'];
$global_count_1s = count(array_filter($global_requests, function($ts) use ($now_micro) { 
    return ($now_micro - $ts) < 1; 
}));
$global_count_10s = count($global_requests);

// Check for emergency mode trigger
if ($global_count_1s >= $global_config['emergency_threshold']) {
    // ACTIVATE EMERGENCY MODE
    @file_put_contents($emergency_file, $now + $global_config['emergency_duration']);
    error_log("Widget DDoS EMERGENCY MODE ACTIVATED: {$global_count_1s} req/s from multiple IPs!");
    
    http_response_code(503);
    header('Content-Type: application/javascript');
    header('Retry-After: 60');
    header('X-DDoS-Protection: emergency-activated');
    echo '(function(){console.warn("Server protection activated. Please wait...");})();';
    exit;
}

// Check global limits
if ($global_count_1s >= $global_config['max_per_second'] || $global_count_10s >= $global_config['max_per_10sec']) {
    // Global rate exceeded - random drop to reduce load
    // Drop 80% of requests when overloaded
    if (mt_rand(1, 100) <= 80) {
        http_response_code(503);
        header('Content-Type: application/javascript');
        header('Retry-After: 5');
        header('X-DDoS-Protection: global-limit');
        echo '(function(){})();';
        exit;
    }
}

// Add to global counter
$global_data['requests'][] = $now_micro;
@file_put_contents($global_rate_file, json_encode($global_data), LOCK_EX);

// ============================================================================
// CPU LOAD PROTECTION - Skip processing if server overloaded
// ============================================================================

// Check CPU load (Linux only, skip if functions disabled)
if (function_exists('sys_getloadavg')) {
    $load = @sys_getloadavg();
    if ($load !== false && isset($load[0])) {
        // Try to get CPU cores safely (default to 2 if can't detect)
        $cpu_cores = 2;
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = @file_get_contents('/proc/cpuinfo');
            if ($cpuinfo) {
                $cpu_cores = max(1, substr_count($cpuinfo, 'processor'));
            }
        }
        
        $load_threshold = $cpu_cores * 0.9; // 90% of CPU cores
        
        if ($load[0] > $load_threshold) {
            // Server overloaded - return minimal response
            http_response_code(503);
            header('Content-Type: application/javascript');
            header('Retry-After: 10');
            header('X-DDoS-Protection: cpu-overload');
            echo '(function(){console.warn("Server busy. Retrying...");setTimeout(function(){location.reload();},5000);})();';
            exit;
        }
    }
}

// ============================================================================
// PER-IP RATE LIMITING (existing logic)
// ============================================================================

// Rate limit config per IP
$rate_config = array(
    'per_second' => 5,      // Max 5 requests/second per IP
    'per_10sec' => 20,      // Max 20 requests/10 seconds
    'per_minute' => 60,     // Max 60 requests/minute
    'block_duration' => 300 // Block 5 minutes after violations
);

$ip_hash = md5($client_ip);
$rate_file = $rate_dir . 'widget_' . $ip_hash . '.dat';
$block_file = $rate_dir . 'block_' . $ip_hash . '.dat';

// Check if blocked
if (file_exists($block_file)) {
    $block_data = @file_get_contents($block_file);
    if ($block_data) {
        $block_until = intval($block_data);
        if (time() < $block_until) {
            http_response_code(429);
            header('Content-Type: application/javascript');
            header('Retry-After: ' . ($block_until - time()));
            header('X-DDoS-Protection: blocked');
            echo '(function(){console.warn("Rate limited. Try again later.");})();';
            exit;
        } else {
            @unlink($block_file); // Expired, remove
        }
    }
}

// Rate limiting check
$violations = 0;

// Read current rate data
$rate_data = array('requests' => array(), 'violations' => 0);
if (file_exists($rate_file)) {
    $content = @file_get_contents($rate_file);
    if ($content) {
        $rate_data = @json_decode($content, true);
        if (!is_array($rate_data)) {
            $rate_data = array('requests' => array(), 'violations' => 0);
        }
    }
}

// Clean old requests (keep last 60 seconds only)
$rate_data['requests'] = array_filter($rate_data['requests'], function($ts) use ($now) {
    return ($now - $ts) < 60;
});

// Count requests in different windows
$requests = $rate_data['requests'];
$count_1s = count(array_filter($requests, function($ts) use ($now) { return ($now - $ts) < 1; }));
$count_10s = count(array_filter($requests, function($ts) use ($now) { return ($now - $ts) < 10; }));
$count_60s = count($requests);

// Check limits
$is_violation = false;
if ($count_1s >= $rate_config['per_second']) {
    $is_violation = true;
}
if ($count_10s >= $rate_config['per_10sec']) {
    $is_violation = true;
}
if ($count_60s >= $rate_config['per_minute']) {
    $is_violation = true;
}

if ($is_violation) {
    $rate_data['violations']++;
    
    // Block after 3 violations
    if ($rate_data['violations'] >= 3) {
        // Progressive blocking: 5min, 10min, 20min, ...
        $block_multiplier = min($rate_data['violations'] - 2, 6); // Max 6x
        $block_time = $rate_config['block_duration'] * $block_multiplier;
        @file_put_contents($block_file, $now + $block_time);
        
        // Log attack
        error_log("Widget DDoS BLOCKED: IP=$client_ip, violations={$rate_data['violations']}, block={$block_time}s");
    }
    
    // Save violations count
    @file_put_contents($rate_file, json_encode($rate_data));
    
    http_response_code(429);
    header('Content-Type: application/javascript');
    header('Retry-After: 10');
    header('X-RateLimit-Remaining: 0');
    echo '(function(){console.warn("Too many requests. Please slow down.");})();';
    exit;
}

// Add current request
$rate_data['requests'][] = $now;
// Reset violations if behaving well
if ($rate_data['violations'] > 0 && $count_60s < ($rate_config['per_minute'] / 2)) {
    $rate_data['violations'] = max(0, $rate_data['violations'] - 1);
}
@file_put_contents($rate_file, json_encode($rate_data));

// ============================================================================
// LOAD WORDPRESS (only after rate limit check passed)
// ============================================================================

if (!defined('ABSPATH')) {
    require_once(dirname(__FILE__) . '/../../../wp-load.php');
}

header('Content-Type: application/javascript; charset=utf-8');
// nocache_headers() (như 3 site nguồn): kèm chỉ thị `private` → Cloudflare không
// cache file .js. Header tay thiếu `private` từng để CF giữ bản widget cũ.
nocache_headers();
header('X-Content-Type-Options: nosniff');
header('X-RateLimit-Remaining: ' . ($rate_config['per_minute'] - $count_60s - 1));
header('X-DDoS-Status: passed');
header('X-Global-Rate: ' . $global_count_1s . '/s, ' . $global_count_10s . '/10s');


$site_url = home_url();
$default_countdown = intval(get_option('sitetop_widget_default_countdown', 30));
$widget_color = get_option('sitetop_widget_color', '#1E5EFF');
$widget_text_color = get_option('sitetop_widget_text_color', '#ffffff');
$widget_icon = get_option('sitetop_widget_icon', '');
$widget_btn_text = get_option('sitetop_widget_button_text', 'LẤY MÃ');
$ts_enabled = get_option('sitetop_turnstile_enabled', '0');
$ts_site_key = get_option('sitetop_turnstile_site_key', '');
$ts_key = ($ts_enabled === '1' && !empty($ts_site_key)) ? $ts_site_key : '';
?>
(function(){'use strict';
// Detect API origin from script src (handles alias domains serving the same WordPress)
var _cs=document.currentScript;
var _csrc=_cs?_cs.src:'';
var _apiOrigin='';
if(_csrc){var _m=_csrc.match(/^(https?:\/\/[^\/]+)/);if(_m)_apiOrigin=_m[1];}
var C={
    api:_apiOrigin||'<?php echo esc_js($site_url); ?>',
    cd:<?php echo $default_countdown; ?>,
    clr:'<?php echo esc_js($widget_color); ?>',
    txtClr:'<?php echo esc_js($widget_text_color); ?>',
    icon:'<?php echo esc_js($widget_icon); ?>',
    tsKey:'<?php echo esc_js($ts_key); ?>',
    btnText:'<?php echo esc_js($widget_btn_text); ?>'
};
var state={sessionId:'',countdown:C.cd,onsiteTime:70,trafficType:'1step',remaining:C.cd,codeReady:false,code:null,sessionReady:false,countdownStarted:false,captchaToken:null,isIncognito:false,googleRequired:false,googleVerified:true,urlPathMatched:true,step2Done:false,step2Image:null,wantStart:false};
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
            var tmp='_st'+Math.random();
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

    sendVerifyAccess(unlockSession,unlockTime,unlockActive,campaignType);
}

// Verify phiên với server — tách khỏi init() để click retry gọi lại được
// (giữ nguyên cấu trúc cũ). Chỉ DI CHUYỂN nguyên khối từ init, không đổi logic.
function sendVerifyAccess(unlockSession, unlockTime, unlockActive, campaignType){
    var x=new XMLHttpRequest();
    x.open('POST',C.api+'/wp-admin/admin-ajax.php',true);
    x.withCredentials=true;
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
            state.step2Image=d.data.step2_image||null;
            state.targetUrl=d.data.target_url||'';
            state.targetPath=d.data.target_path||'';
            state.currentPath=d.data.current_path||'';
            state.refererReceived=d.data.referer_received||'(empty)';
            state.refererHost=d.data.referer_host||'(empty)';

            // Register captcha message listener early (but don't load iframe yet)
            if(C.tsKey){
                window.addEventListener('message',function(e){
                    // Cap2: only trust messages from our own captcha iframe origin (defense-in-depth;
                    // server-side transient is the real gate). Blocks the embedding site forging captcha_success.
                    if(e.origin!==C.api)return;
                    if(!e.data||!e.data.type)return;
                    if(e.data.type==='captcha_success'){
                        state.captchaToken=e.data.token;
                        // Belt thứ 2: gửi lại token qua kênh AJAX của widget (kênh này chạy tốt
                        // trên mọi domain nhúng — cùng kênh verify_access). Nếu fetch bên trong
                        // iframe fail (cross-origin/mạng) thì kênh này verify token + set transient
                        // captcha_ok; nếu iframe đã verify rồi thì server trả duplicate, vô hại vì
                        // transient đã set. Fire-and-forget, không đụng UI.
                        if(e.data.token){ajax('sitetop_widget_captcha',{session_id:state.sessionId,token:e.data.token},function(){});}
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
            // KHÔNG tự bắt đầu khi user chưa bấm — NHƯNG nếu user ĐÃ bấm trong lúc
            // verify chạy (wantStart, cơ chế source hoclaixe/dethito) → tự chạy luôn,
            // không cần bấm lần 2. _stWidgetClick tự re-check gate (Google/URL/ẩn danh).
            if(state.wantStart){state.wantStart=false;window._stWidgetClick();}
        }catch(e){console.log('LN widget parse error:',e);}
    };
    x.send('action=sitetop_widget_verify_access&referer='+encodeURIComponent(document.referrer||'')+'&current_url='+encodeURIComponent(window.location.href)+'&unlock_session='+encodeURIComponent(unlockSession)+'&unlock_time='+encodeURIComponent(unlockTime)+'&unlock_active='+encodeURIComponent(unlockActive)+'&campaign_type='+encodeURIComponent(campaignType)+'&nav_type='+encodeURIComponent(_navType()));
}

// Kiểu điều hướng của lần tải trang này: navigate | reload | back_forward.
// Cần vì document.referrer KHÔNG đổi khi F5 — user đến A.com từ Google cho nhiệm vụ 1,
// xong mở shortlink 2 rồi F5 lại A.com thì referrer vẫn là Google. Chỉ xét referrer là
// bị qua mặt; kiểu điều hướng mới phân biệt được "đến mới" với "tải lại".
function _navType(){
    try{
        var e=performance.getEntriesByType('navigation');
        if(e&&e.length&&e[0].type)return e[0].type;
        if(performance.navigation){   // API cũ, Safari đời thấp
            var t=performance.navigation.type;
            return t===1?'reload':(t===2?'back_forward':'navigate');
        }
    }catch(e){}
    return '';
}

// ================================================================
// CREATE WIDGET UI - Inline tại vị trí <script> tag
// ================================================================
function createWidget(){
    if(document.getElementById('tn-w'))return;

    // Find the script tag to insert widget AFTER it. Prefer document.currentScript (_cs) — the EXACT
    // <script> being executed — so the widget appears right where the embed code is pasted. The old
    // src*="sitetop" selector missed alias domains (any <alias>/widget.js), leaving anchor=null
    // → widget fell back to document.body and appeared stuck at the page bottom/footer regardless of
    // where the script was placed. _cs is captured synchronously at IIFE start, so it's reliable.
    var scripts=document.querySelectorAll('script[src*="widget.js"],script[src*="top.js"]'); // top.js = alias mã nhúng mới
    var anchor=_cs||(scripts.length?scripts[scripts.length-1]:null);

    // Optional placement — does NOT change show/hide logic, only WHERE the widget mounts:
    //   1. data-target="#selector" on the <script> tag → mount inside the matched element
    //   2. an empty <div id="sitetop-widget"></div> anywhere → mount inside it
    //   3. data-position="bottom-right|bottom-left|top-right|top-left" → fixed floating corner
    //   4. (default, unchanged) inline right after the <script> tag
    var cfgEl=_cs||anchor, mountEl=null, floatPos='', inlineHere=false;
    try{
        if(cfgEl){
            var tsel=cfgEl.getAttribute('data-target');
            if(tsel){
                var sel=(tsel.charAt(0)==='#'||tsel.charAt(0)==='.')?tsel:'#'+tsel;
                mountEl=document.querySelector(sel);
            }
            floatPos=(cfgEl.getAttribute('data-position')||'').toLowerCase().replace('fixed-','');
            // data-inline: "để yên tại chỗ dán thẻ script", không kéo xuống footer.
            // Chấp nhận cả data-inline (không giá trị), ="1", ="true".
            if(cfgEl.hasAttribute('data-inline')){
                var _di=(cfgEl.getAttribute('data-inline')||'').toLowerCase();
                inlineHere=(_di===''||_di==='1'||_di==='true'||_di==='yes');
            }
        }
    }catch(e){}
    if(!mountEl)mountEl=document.getElementById('sitetop-widget');

    // data-offset="-60" trên thẻ <script>: dịch nút LÊN 60px (số dương thì dịch xuống).
    // Cần vì nhiều theme chèn mã nhúng qua ô "script cuối trang", vốn luôn nằm ngay trước
    // </body> — tức là SAU footer. Nút rơi xuống dải trắng dưới cùng, trông rời rạc.
    // Đây là cách chỉnh nhanh; muốn nút nằm ĐÚNG trong footer thì dùng data-target.
    var _off=0;
    try{ if(cfgEl) _off=parseInt(cfgEl.getAttribute('data-offset')||'0',10)||0; }catch(e){}
    if(_off<-400)_off=-400; if(_off>400)_off=400;
    var _mt=22+_off;

    var s=document.createElement('style');
    // Nút nằm TRONG luồng trang, ở khối footer — KHÔNG position:fixed, không dính màn hình.
    // User phải cuộn xuống cuối trang mới thấy nút (đúng bước 1 của kịch bản nhiệm vụ).
    // position:relative để #tn-toast (absolute) neo theo nút. Đếm ngược hiện SỐ trong vòng
    // tròn (tn-counting), mã hiện dạng pill (tn-pill). KHÔNG đụng logic đếm ngược/sinh mã/verify.
    s.textContent='#tn-w{position:relative;display:block;width:100%;margin:'+_mt+'px auto 22px;padding:0;text-align:center;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;z-index:2147483000}'+
    '#tn-btn{display:inline-flex!important;flex-direction:column;align-items:center;justify-content:center;gap:2px;background:'+C.clr+';color:'+C.txtClr+';width:34px!important;height:34px!important;min-width:34px!important;max-width:34px!important;min-height:34px!important;border-radius:50%!important;box-sizing:border-box!important;padding:0!important;margin:0!important;aspect-ratio:1/1!important;flex:none!important;overflow:hidden;font-size:9.5px;font-weight:800;cursor:pointer;border:none!important;box-shadow:0 3px 10px rgba(0,0,0,.2);transition:transform .15s;letter-spacing:.4px;line-height:1.05;text-align:center}'+
    '#tn-btn:hover{transform:scale(1.03)}'+
    '#tn-btn svg,#tn-btn img{width:16px!important;height:16px!important;display:block}'+
    // Icon tùy chỉnh (tn-logo): logo phủ KÍN mặt nút tròn (thay cho icon 22px + chữ). Chỉ trạng thái
    // ban đầu có img — đếm ngược/pill/đợi giữ nguyên (ẩn theo .tn-counting hoặc innerHTML đã thay).
    '#tn-btn.tn-logo img{width:100%!important;height:100%!important;object-fit:cover;border-radius:50%}'+
    '#tn-btn-text:empty{display:none}'+
    '#tn-cd{font-size:18px;font-weight:600;color:#fff;line-height:1;text-align:center;display:none}'+
    '#tn-btn.tn-counting>*{display:none!important}'+
    '#tn-btn.tn-counting>#tn-cd{display:block!important}'+
    '#tn-btn.tn-pill{width:auto!important;height:auto!important;min-width:0!important;max-width:none!important;min-height:0!important;border-radius:20px!important;padding:9px 15px!important;aspect-ratio:auto!important;flex-direction:row;gap:7px;overflow:visible;font-size:12px}'+
    '#tn-toast{position:absolute;bottom:calc(100% + 10px);left:50%;right:auto;top:auto;transform:translateX(-50%);background:#1a7a3a;color:#fff;padding:8px 13px;border-radius:9px;font-size:12px;font-weight:600;line-height:1.35;z-index:9999999;opacity:0;transition:opacity .25s;pointer-events:none;white-space:normal;width:190px;text-align:center;box-shadow:0 5px 16px rgba(0,0,0,.24)}'+
    '#tn-toast.warn{background:#d9534f}'+
    '#tn-toast.show{opacity:1}'+
    // Placement tuỳ chọn cũ (data-position) → vô hiệu, mọi trang đích đặt nút trong footer như nhau.
    '#tn-w.tn-float,#tn-w.tn-float-br,#tn-w.tn-float-bl,#tn-w.tn-float-tr,#tn-w.tn-float-tl{position:relative;left:auto;right:auto;top:auto;bottom:auto;transform:none}'+
    // Popup chốt hành vi: LUÔN giữa màn hình, overlay mờ. pointer-events:none để user vẫn
    // cuộn/chạm được trang bên dưới — chính thao tác đó mới là điều kiện qua chốt.
    '#tn-ov,#tn-ov *{box-sizing:border-box}'+   // không để CSS reset của trang đích đổi kích thước thẻ
    '#tn-ov{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(10,22,51,.26);z-index:2147483100;display:none;align-items:center;justify-content:center;padding:20px;pointer-events:none}'+
    '#tn-ov.show{display:flex}'+
    '#tn-pop{background:#fff;border-radius:18px;padding:20px 18px;max-width:290px;width:100%;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.42);font-family:inherit;animation:tnPopIn .22s ease}'+
    '@keyframes tnPopIn{from{opacity:0;transform:scale(.94)}to{opacity:1;transform:scale(1)}}'+
    '#tn-pop-ic{display:none}'+                                        // mẫu mới: bỏ icon
    '#tn-pop-msg{font-size:14.5px;font-weight:700;color:#111827;line-height:1.45;margin:0 0 13px}'+
    '#tn-pop-msg:empty{display:none}'+                                 // không có việc gì → chỉ còn vòng đếm
    '#tn-pop-sub{display:none}'+                                       // mẫu mới: bỏ dòng phụ
    // Đồng hồ = vòng tròn viền mảnh chỉ chứa SỐ (bỏ chữ "Thời gian còn lại")
    '#tn-pop-timer{width:56px;height:56px;margin:0 auto;padding:0;border-radius:50%;border:1.5px solid #E3E8F2;background:#fff;display:flex;align-items:center;justify-content:center}'+
    '#tn-pop-timer b{font-size:18px;font-weight:700;color:#111827;font-variant-numeric:tabular-nums}'+
    '#tn-pop.warn #tn-pop-msg{color:#B45309}'+
    '#tn-pop.warn #tn-pop-timer{border-color:#FBBF24}'+
    // Chế độ MINI: sau popup đầu tiên, thu thành chip mờ nổi GIỮA màn hình (không phải trên
    // đầu trang), KHÔNG overlay, không chặn thao tác, không cần tắt — ở đó suốt phiên.
    // Lúc không có việc gì thì chỉ còn đồng hồ đếm ngược.
    '#tn-ov.mini{background:transparent;align-items:center;justify-content:center;padding:0}'+
    '#tn-ov.mini #tn-pop{max-width:248px;padding:16px 16px;opacity:1;box-shadow:0 14px 36px -14px rgba(0,0,0,.4);animation:none}'+
    '#tn-ov.mini #tn-pop-msg{font-size:13.5px;margin:0 0 11px}'+
    '#tn-ov.mini #tn-pop-timer{width:50px;height:50px}'+
    '#tn-ov.mini #tn-pop-timer b{font-size:17px}'+
    // Mobile: khung nhỏ lại rõ rệt (cả popup lần đầu lẫn chip mini)
    '@media(max-width:600px){'+
    '#tn-pop{max-width:250px;padding:16px 14px;border-radius:16px}'+
    '#tn-pop-msg{font-size:13.5px;margin:0 0 11px}'+
    '#tn-pop-timer{width:48px;height:48px}#tn-pop-timer b{font-size:16px}'+
    '#tn-ov.mini #tn-pop{max-width:62vw;padding:12px 12px;border-radius:14px}'+
    '#tn-ov.mini #tn-pop-msg{font-size:12.5px;margin:0 0 9px}'+
    '#tn-ov.mini #tn-pop-timer{width:42px;height:42px}#tn-ov.mini #tn-pop-timer b{font-size:15px}}';
    document.head.appendChild(s);

    var w=document.createElement('div');
    w.id='tn-w';
    var iconHtml=C.icon?'<img src="'+C.icon+'" style="width:16px;height:16px">':'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="14" rx="2"/><path d="M12 8V5a3 3 0 0 0-3-3h0a3 3 0 0 0-3 3v0"/><path d="M18 8V5a3 3 0 0 0-3-3h0a3 3 0 0 0-3 3v0"/><line x1="12" y1="8" x2="12" y2="22"/></svg>';
    // Icon tùy chỉnh → class tn-logo (logo phủ kín nút) + chữ RỖNG (logo tự mang brand; :empty tự ẩn).
    // Chữ phải rỗng từ đầu chứ KHÔNG ẩn bằng CSS theo class — các trạng thái "Vui lòng đợi"/"Đang tải..."
    // thay innerHTML bằng text thuần, ẩn theo class sẽ làm nút trống trơn.
    w.innerHTML='<div id="tn-btn"'+(C.icon?' class="tn-logo"':'')+' onclick="window._stWidgetClick()">'+iconHtml+'<span id="tn-btn-text">'+(C.icon?'':C.btnText)+'</span><span id="tn-cd"></span></div><iframe id="tn-captcha" style="display:none;border:none;width:220px;height:45px;margin-top:4px;overflow:hidden"></iframe><div id="tn-toast"></div>';

    // Nút nằm trong luồng trang → gắn vào FOOTER của trang đích. Dò theo thứ tự phổ biến nhất,
    // không thấy footer nào thì rơi về cuối <body> (vẫn là cuối trang, đúng tinh thần).
    // Không còn dùng position:fixed nên ancestor có transform/filter cũng không nuốt mất nút.
    function _findFooter(){
        // Danh sách phủ các theme WordPress phổ biến. Bộ cũ chỉ có 7 selector nên trượt
        // nhiều theme (Flatsome dùng .footer-wrapper, nhiều theme dùng .footer-area...),
        // trượt là nút rơi ra ngoài khối footer, nằm chỏng chơ trên nền trắng cuối trang.
        var sel=['footer','.footer','#footer','.site-footer','.footer-wrapper','.footer-area',
                 '.footer-container','.main-footer','#colophon','.page-footer','.site-info',
                 '[role=contentinfo]'];
        function _ok(el){
            if(!el)return false;
            var cs=window.getComputedStyle(el);
            if(cs.display==='none'||cs.visibility==='hidden')return false;
            // offsetParent null với position:fixed → kiểm thêm bằng kích thước thật
            return el.offsetParent!==null||el.getBoundingClientRect().height>0;
        }
        for(var i=0;i<sel.length;i++){
            var els=document.querySelectorAll(sel[i]);
            for(var j=els.length-1;j>=0;j--){          // lấy footer CUỐI cùng nếu trang có nhiều
                if(_ok(els[j]))return els[j];
            }
        }
        // Vét cạn: bất kỳ thẻ nào có class chứa "footer" và cao trên 60px. Ngưỡng chiều cao
        // để không dính mấy cái như <div class="footer-toggle"> chỉ vài chục pixel.
        var all=document.querySelectorAll('[class*="footer"],[id*="footer"]');
        for(var k=all.length-1;k>=0;k--){
            if(_ok(all[k])&&all[k].getBoundingClientRect().height>60)return all[k];
        }
        return null;
    }
    function _mount(){
        // LỖI CŨ: hàm này bỏ qua hoàn toàn mountEl / floatPos / anchor đã tính ở trên,
        // luôn nhét nút vào footer tự dò. Nên 4 kiểu đặt vị trí ghi trong chú thích
        // (data-target, #sitetop-widget, data-position, ngay sau thẻ script) đều VÔ TÁC
        // DỤNG — khách dán mã ở đâu nút cũng rơi xuống cuối trang.

        // 1. Chỉ định rõ ràng: data-target="#..." hoặc <div id="sitetop-widget">
        if(mountEl){ mountEl.appendChild(w); return; }

        // 2. data-position: nổi cố định ở góc màn hình
        if(floatPos){
            var m={'bottom-right':'bottom:18px;right:18px','bottom-left':'bottom:18px;left:18px',
                   'top-right':'top:18px;right:18px','top-left':'top:18px;left:18px'}[floatPos];
            if(m){ w.style.cssText+=';position:fixed;width:auto;margin:0;'+m+';';
                   document.body.appendChild(w); return; }
        }

        // 3. data-inline: ép nút mọc ĐÚNG chỗ dán thẻ <script>, không kéo xuống footer.
        //    Đây là cách để khách "cài đâu nằm đấy" mà không phải thêm div hay data-target.
        if(inlineHere&&anchor&&anchor.parentNode&&!/^(head|html)$/i.test(anchor.parentNode.tagName)){
            anchor.parentNode.insertBefore(w,anchor.nextSibling); return;
        }

        // 4. MẶC ĐỊNH: mã nhúng trần <script src=".../top.js"></script> → vào trong FOOTER
        //    của trang đích. Đây là hành vi mong muốn: khách dán mã ở đâu cũng không phải
        //    bận tâm, nút luôn nằm cuối trang và user phải cuộn xuống mới thấy.
        var f=_findFooter();
        if(f){ f.appendChild(w); return; }

        // 5. Không tìm được footer → vẫn đặt ngay sau thẻ <script>.
        //    Bỏ qua nếu thẻ nằm trong <head> (không render được) hoặc đã bị gỡ khỏi DOM.
        if(anchor&&anchor.parentNode&&!/^(head|html)$/i.test(anchor.parentNode.tagName)){
            anchor.parentNode.insertBefore(w,anchor.nextSibling); return;
        }

        // 6. Bí lắm mới rơi về cuối body
        document.body.appendChild(w);
    }
    var ov=document.createElement('div');
    ov.id='tn-ov';
    ov.innerHTML='<div id="tn-pop"><div id="tn-pop-ic"></div><p id="tn-pop-msg"></p><p id="tn-pop-sub"></p><span id="tn-pop-timer">Thời gian còn lại <b>--</b>s</span></div>';
    if(document.body)document.body.appendChild(ov);

    if(document.body){
        _mount();
    }else{
        document.addEventListener('DOMContentLoaded',_mount);
    }
}

// ================================================================
// COUNTDOWN (with visibility + mouse activity checks)
// ================================================================
var _cdPaused=false;
var _lastMouseMove=0;
var _mouseIdleLimit=30000; // 30 giây không di chuyển chuột → dừng countdown (nới từ 20s: đồng bộ "gọn lại" — user đọc trang đích lâu không bị ngắt sớm)
var _mouseCheckTimer=null;
var _visListenerAdded=false;
// Đo tốc độ cuộn — thứ DUY NHẤT còn lại của máy đọc-cuộn cũ. Không còn dùng để chặn đếm,
// chỉ để kịch bản hành vi biết user đang lướt quá nhanh mà nhắc "chậm lại".
var _tooFastUntil=0,_fastDist=99999,_rWin=[],_readListenerAdded=false;
function _vh(){ return window.innerHeight||document.documentElement.clientHeight||600; }
function _scrollY(){ return window.pageYOffset||document.documentElement.scrollTop||0; }
// Vị trí cuộn lần trước, để biết lần này user cuộn LÊN hay XUỐNG. Chốt đầu tiên chỉ
// đòi "có cuộn lên", không đòi tới đúng vị trí 1/3 trang nữa.
var _prevScrollY=0;
function _scrollPct(){ var h=Math.max(document.documentElement.scrollHeight||0,(document.body||{}).scrollHeight||0)-_vh(); return h<=0?1:Math.min(1,_scrollY()/h); }
function _onReadScroll(){
    if(!state.countdownStarted||state.codeReady)return;
    var now=Date.now(),y=_scrollY();
    _rWin.push({t:now,y:y}); while(_rWin.length&&now-_rWin[0].t>2000)_rWin.shift();
    var dist=0; for(var i=1;i<_rWin.length;i++)dist+=Math.abs(_rWin[i].y-_rWin[i-1].y);
    if(dist>_fastDist){ _tooFastUntil=now+1600; }
}

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
    if(reason==='behavior')return;   // popup chốt đã tự hiện, không chồng thêm
    // Mọi hướng dẫn đi qua MỘT kênh duy nhất là popup giữa màn hình (bỏ toast cũ).
    if(reason==='tab_hidden')return; // tab bị ẩn thì popup cũng không ai thấy
    _bhShowIdle();
}
function _resumeCountdown(){
    if(_bh.gate)return;              // đang chờ user qua chốt → chỉ _bhPass mới được resume
    if(!_cdPaused||state.remaining<=0)return;
    if(_bh.idle){_bh.idle=false;_bhHide();}
    _cdPaused=false;
    _startCountdownInterval();
    updateCountdownUI();
}
function _startCountdownInterval(){
    if(timers.countdown)clearInterval(timers.countdown);
    timers.countdown=setInterval(function(){
        if(document.hidden){_pauseCountdown('tab_hidden');return;}
        var _now=Date.now();
        // Cổng đọc-cuộn cũ đã bỏ: nó bắt cuộn xuống liên tục nên mâu thuẫn với chốt hành vi
        // (chốt bảo lên đầu trang, cổng cũ lại nhắc kéo xuống). Việc ép tương tác thật giờ do
        // 5 chốt đảm nhiệm. Ở đây chỉ giữ chống-bỏ-máy: không đụng gì quá lâu → tạm dừng.
        if(_now-_lastMouseMove>_mouseIdleLimit){_pauseCountdown('mouse_idle');return;}
        state.remaining--;
        updateCountdownUI();
        _bhTick();
        // Còn 1 giây thì dọn thẻ nổi trước, để bước 2 hiện ra trên màn hình sạch.
        if(state.remaining<=1)_bhForceHide();
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
// ================================================================
// KỊCH BẢN HÀNH VI (các chốt tương tác rải dọc countdown)
// Mục tiêu: phiên không thể chạy trôi tự động — mỗi chốt bắt user thao tác thật
// (chạm màn hình / lên đầu trang / xuống cuối trang) mới cho đếm tiếp.
// Countdown vẫn là NGUỒN SỰ THẬT cấp mã; chốt chỉ pause/resume nó, không tự cấp mã.
// Mọi delay random trong khoảng min–max để nhịp không đều đặn như máy.
// ================================================================
var _bh={on:false,i:-1,left:0,gate:null,stages:[],warnUntil:0,idle:false,firstDone:false,pre:null,satisfied:false};
function _bhRnd(a,b){ return a+Math.floor(Math.random()*(b-a+1)); }
function _bhMinTotal(){ var t=0; for(var i=0;i<_bh.stages.length;i++)t+=_bh.stages[i].dur; return t; }
function _bhInit(){
    // Nhịp CHUẨN của kịch bản (tổng trung bình 60s). Delay thật sẽ được giãn/co theo
    // onsite của chiến dịch bên dưới, nên bảng này chỉ đóng vai trò TỶ LỆ giữa các chặng.
    var base=[
        // lead = số giây báo trước khi chốt đóng. Không khai thì mặc định 3.
        {min:5, max:8,  gate:'third',  msg:'Lướt trang lên để tiếp tục',    sub:'Chỉ cần cuộn trang lên một chút.'},
        {min:8, max:10, gate:'tap',    msg:'Chạm vào màn hình để tiếp tục', sub:'Giữ nhịp tự nhiên, không thao tác quá nhanh.'},
        {min:6, max:8,  gate:'third',  msg:'Lướt trang lên để tiếp tục',    sub:'Cuộn trang lên thêm một chút nữa.'},
        {min:8, max:10, gate:'top',    msg:'Lướt chậm lên đầu trang',       sub:'Cuộn từ từ lên tận đầu trang.', lead:5},
        {min:5, max:8,  gate:'half',   msg:'Lướt trang xuống để tiếp tục',  sub:'Chỉ cần cuộn trang xuống một chút.'},
        {min:8, max:10, gate:'tap',    msg:'Chạm vào màn hình để tiếp tục', sub:'Chạm bất kỳ đâu trên trang để đếm tiếp.'},
        {min:6, max:8,  gate:'half',   msg:'Lướt trang xuống để tiếp tục',  sub:'Cuộn trang xuống thêm một chút nữa.'},
        {min:10,max:15, gate:'bottom', msg:'Cuộn xuống cuối trang',         sub:'Mã sẽ hiện ngay khi bạn xuống tới cuối trang.'}
    ];
    // Phân bổ theo onsite của chiến dịch (70–150s). Bốc ngẫu nhiên TRƯỚC rồi chuẩn hoá cho
    // tổng khớp ĐÚNG ngân sách — nếu chỉ nhân hệ số theo trung bình thì nhánh max vẫn vượt
    // onsite, chặng cuối chưa xong mã đã hiện. Chừa 4s đuôi để user kịp xuống tới đáy.
    var raw=base.map(function(st){ return _bhRnd(st.min,st.max); });
    var sum=0; for(var _i=0;_i<raw.length;_i++) sum+=raw[_i];
    var budget=Math.max(0,state.remaining-4);
    var k=sum>0 ? budget/sum : 1;
    var acc=0;
    _bh.stages=base.map(function(st,idx){
        // Sàn thời lượng phải LỚN HƠN độ trễ báo trước, không thì chặng vừa bắt đầu
        // đã bung luôn thông báo "Sắp tới", user không kịp phản ứng.
        var lead=st.lead||3;
        var d=Math.max(lead+1,Math.round(raw[idx]*k));
        acc+=d;
        return { gate:st.gate, msg:st.msg, sub:st.sub, dur:d, lead:lead };
    });
    // Bù sai số làm tròn vào chặng cuối → tổng đúng bằng ngân sách.
    if(_bh.stages.length){
        var last=_bh.stages[_bh.stages.length-1];
        last.dur=Math.max(4,last.dur+(budget-acc));
    }
    // Lưới an toàn: ngân sách vẫn không đủ cho mọi chặng (gói lạ, quá ngắn) → cắt bớt chặng cuối.
    while(_bh.stages.length>1 && _bhMinTotal()>state.remaining) _bh.stages.pop();
    _bh.on=_bh.stages.length>0; _bh.i=-1; _bh.gate=null; _bh.warnUntil=0; _bh.firstDone=false; _bh.pre=null; _bh.satisfied=false;
    if(!_bhListenerAdded){
        _bhListenerAdded=true;
        document.addEventListener('touchstart',_bhOnAct,{passive:true});
        document.addEventListener('mousedown',_bhOnAct,{passive:true});
        document.addEventListener('scroll',_bhOnScroll,{passive:true});
    }
    if(_bh.on)_bhNext();
}
var _bhListenerAdded=false;
function _bhNext(){
    _bh.pre=null; _bh.satisfied=false;
    _bh.i++;
    if(_bh.i>=_bh.stages.length){ _bh.on=false; _bhHide(); return; }   // _bhHide sẽ rút về chip đồng hồ
    _bh.left=_bh.stages[_bh.i].dur;
}
// Gọi mỗi khi countdown TIÊU THỤ 1 giây thật → chốt cũng pause/resume theo countdown.
function _bhTick(){
    if(!_bh.on||_bh.gate)return;
    var st=_bh.stages[_bh.i];
    if(_bh.left>0){
        _bh.left--;
        // Độ trễ báo trước lấy theo từng chặng (st.lead), không còn cố định 3 giây.
        if(st&&_bh.left<=(st.lead||3)&&!_bh.pre) _bhPreArm();
        return;
    }
    if(!st)return;
    // Đã làm đúng thao tác ngay trong 3 giây báo trước → đi tiếp, KHÔNG dừng đồng hồ.
    if(_bh.satisfied){ _bhHide(); _bhNext(); return; }
    _bh.gate=st.gate;
    _bhShow(st.msg,st.sub,false);
    _pauseCountdown('behavior');
    // Đã đứng sẵn ở vị trí yêu cầu → không có sự kiện cuộn nào phát ra, sẽ kẹt.
    // Cho qua sau 1.5s để user kịp đọc thông báo.
    if(st.gate==='third'||st.gate==='top'||st.gate==='half'||st.gate==='bottom'){
        // 'third' giờ đòi CÓ CUỘN LÊN chứ không đòi vị trí. Nếu user đã ở sát đầu trang
        // thì không còn chỗ để cuộn lên → sẽ kẹt vĩnh viễn. Coi như đã đạt, cho qua.
        var _ok=function(){ return st.gate==='third' ? _scrollY()<=5
                                 : st.gate==='top'   ? _scrollY()<=60
                                 : st.gate==='half'  ? _scrollPct()>=0.99
                                 : _scrollPct()>=0.92; };
        if(_ok()) setTimeout(function(){ if(_bh.gate===st.gate&&_ok()) _bhPass(); },1500);
    }
}
// Báo trước 3 giây: hiện thông báo nhưng KHÔNG dừng đồng hồ. Làm đúng trong 3 giây này
// thì qua chặng êm; lơ là để hết 3 giây thì chốt mới mở và đồng hồ mới đứng.
function _bhPreArm(){
    var st=_bh.stages[_bh.i]; if(!st)return;
    _bh.pre = st.gate;
    var m = st.gate==='third'  ? 'Sắp tới: lướt trang lên'
          : st.gate==='top'    ? 'Sắp tới: lướt chậm lên đầu trang'
          : st.gate==='half'   ? 'Sắp tới: lướt trang xuống'
          : st.gate==='bottom' ? 'Sắp tới: cuộn xuống cuối trang'
          : 'Sắp tới: chạm vào màn hình';
    _bhShow(m,'',false);
}
function _bhEarly(){
    if(_bh.satisfied)return;
    _bh.satisfied=true;
    _bhShow('Đã ghi nhận','',false);
}
function _bhPass(){
    if(!_bh.gate)return;
    _bh.gate=null; _bh.firstDone=true; _bhHide(); _bhNext();
    _lastMouseMove=Date.now();
    _resumeCountdown();
}
function _bhOnAct(e){
    if(_bh.pre==='tap'&&!_bh.gate){ _bhEarly(); return; }   // làm sớm trong 3 giây báo trước
    if(!_bh.gate)return;
    // Chốt 'top' và 'bottom' qua bằng CUỘN (xem _bhOnScroll), không cần chạm màn hình.
    if(_bh.gate==='tap'){ _bhPass(); return; }
}
function _bhOnScroll(){
    // Tính MỘT lần cho cả hàm — gọi nhiều lần sẽ làm hỏng mốc so sánh.
    var _y=_scrollY(), _up=_y<_prevScrollY-2, _down=_y>_prevScrollY+2;   // ngưỡng 2px chống rung/nảy
    _prevScrollY=_y;

    if(!_bh.gate){                                          // làm sớm trong 3 giây báo trước
        if(_bh.pre==='third'  && _up)                _bhEarly();
        if(_bh.pre==='top'    && _scrollY()<=60)     _bhEarly();
        if(_bh.pre==='half'   && _down)              _bhEarly();
        if(_bh.pre==='bottom' && _scrollPct()>=0.92) _bhEarly();
        return;
    }
    if(Date.now()<_tooFastUntil){ _bhNagPop('Bạn lướt quá nhanh — chậm lại'); return; }
    if(_bh.gate==='third'  && _up)   _bhPass();                // chỉ cần CÓ cuộn lên
    if(_bh.gate==='half'   && _down) _bhPass();                // chỉ cần CÓ cuộn xuống
    if(_bh.gate==='top'    && _scrollY()<=60)     _bhPass();   // lên tới đầu trang là đếm tiếp
    if(_bh.gate==='bottom' && _scrollPct()>=0.92) _bhPass();
}
function _bhNagPop(msg){
    var n=Date.now(); if(n<_bh.warnUntil)return; _bh.warnUntil=n+2200;
    var p=document.getElementById('tn-pop'),m=document.getElementById('tn-pop-sub');
    if(!p||!m)return;
    var old=m.textContent; p.classList.add('warn'); m.textContent=msg;
    setTimeout(function(){ if(_bh.gate){ p.classList.remove('warn'); m.textContent=old; } },2000);
}
function _bhIcon(gate){
    if(gate==='timer')  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
    if(gate==='top'||gate==='third') return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5"/><path d="M5 12l7-7 7 7"/></svg>';
    if(gate==='bottom'||gate==='half') return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M19 12l-7 7-7-7"/></svg>';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11V6a2 2 0 1 1 4 0v5"/><path d="M13 11V4a2 2 0 1 1 4 0v7"/><path d="M17 11V7a2 2 0 1 1 4 0v9a5 5 0 0 1-5 5h-3a6 6 0 0 1-5.2-3L5 15a2 2 0 0 1 3.3-2.3L9 13"/></svg>';
}
function _bhShowIdle(){
    if(state.codeReady||state.remaining<=1)return;
    _bh.idle=true;
    if(_bh.firstDone){ _bhMini('Đã tạm dừng — chạm để tiếp tục',true); return; }
    var ov=document.getElementById('tn-ov'),p=document.getElementById('tn-pop'),ic=document.getElementById('tn-pop-ic');
    if(!ov||!p)return;
    if(ic)ic.innerHTML=_bhIcon('tap');
    var m=document.getElementById('tn-pop-msg'); if(m)m.textContent='Đã tạm dừng đếm';
    var sb=document.getElementById('tn-pop-sub'); if(sb)sb.textContent='Chạm vào màn hình để tiếp tục.';
    p.classList.add('warn');
    ov.classList.remove('mini');
    _bhTimerUI();
    ov.classList.add('show');
}
function _bhShow(msg,sub,warn){
    // Đếm ngược đã xong hoặc mã đã hiện → không dựng thẻ nữa. _bhEarly() có thể bắn
    // muộn (user thoả chốt hành vi sau khi hết giờ) và sẽ nổi đè lên mã / khối bước 2.
    if(state.codeReady||state.remaining<=1)return;
    var ov=document.getElementById('tn-ov'),p=document.getElementById('tn-pop');
    if(!ov||!p)return;
    var ic=document.getElementById('tn-pop-ic');
    if(ic)ic.innerHTML=_bhIcon(_bh.gate);
    var m=document.getElementById('tn-pop-msg'); if(m)m.textContent=msg||'';
    var sb=document.getElementById('tn-pop-sub'); if(sb)sb.textContent=sub||'';
    p.classList.toggle('warn',!!warn);
    // Chỉ LẦN ĐẦU mới bung popup to + overlay để giải thích; từ lần 2 dùng chip mini
    // (không overlay, không chặn thao tác, không cần tắt).
    ov.classList.toggle('mini',_bh.firstDone);
    _bhTimerUI();
    ov.classList.add('show');
    _bh.firstDone=true;   // từ thông báo thứ 2 trở đi dùng thẻ mini
}
// Chip mini: giữ NGUYÊN trên màn hình suốt phiên. Không truyền msg → chỉ còn đồng hồ.
function _bhMini(msg,warn){
    var ov=document.getElementById('tn-ov'),p=document.getElementById('tn-pop');
    // remaining<=1: chặn mọi đường hiện lại chip ở giây cuối, dù _bhHide có gọi tới.
    if(!ov||!p||state.codeReady||state.remaining<=1)return;
    var ic=document.getElementById('tn-pop-ic'); if(ic)ic.innerHTML=_bhIcon('timer');
    var m=document.getElementById('tn-pop-msg'); if(m)m.textContent=msg||'';
    var sb=document.getElementById('tn-pop-sub'); if(sb)sb.textContent='';
    p.classList.toggle('warn',!!warn);
    ov.classList.add('mini','show');
    _bhTimerUI();
}
function _bhHide(){
    var ov=document.getElementById('tn-ov');
    if(!ov)return;
    // Sau popup đầu tiên: KHÔNG tắt hẳn nữa, chỉ rút về chip đồng hồ luôn hiện.
    if(_bh.firstDone&&!state.codeReady){ _bhMini(''); return; }
    ov.classList.remove('show','mini');
}
// Tắt HẲN thẻ nổi, không thu về chip mini. Dùng ở giây cuối để nhường màn hình
// cho khối bước 2 / nút lấy mã — chip cũ nằm đè lên trông rất rối.
function _bhForceHide(){
    var ov=document.getElementById('tn-ov');
    if(ov)ov.classList.remove('show','mini');
    _bh.on=false; _bh.gate=null; _bh.idle=false;
}
function _bhTimerUI(){
    var t=document.getElementById('tn-pop-timer');
    if(t)t.innerHTML='<b>'+Math.max(0,state.remaining)+'</b>';
}

function startCountdown(){
    _lastMouseMove=Date.now();
    _cdPaused=false;
    updateCountdownUI();
    // Ngưỡng "lướt quá nhanh" tính theo tốc độ đọc 200 từ/phút của chính trang đích.
    var _h=Math.max(document.documentElement.scrollHeight||0,(document.body||{}).scrollHeight||0);
    _tooFastUntil=0; _rWin=[];
    var _txt=(((document.body&&document.body.innerText)||'').slice(0,200000)),_words=(_txt.match(/\S+/g)||[]).length;
    var _readSecs=Math.max(8,_words/200*60),_pace=Math.max(1,_h-_vh())/_readSecs;
    _fastDist=Math.min(Math.max(_pace*4,_vh()*0.9),_vh()*2.5)*2;             // ngưỡng lướt-nhanh theo tốc độ đọc 200 từ/phút.
    if(!_readListenerAdded){ _readListenerAdded=true; document.addEventListener('scroll',_onReadScroll,{passive:true}); document.addEventListener('touchmove',_onReadScroll,{passive:true}); }
    // Bỏ toast mở màn: kịch bản chốt hành vi đã có popup giữa màn hình dẫn từng bước,
    // toast này vừa thừa vừa dễ mâu thuẫn (bảo "kéo xuống" trong khi chốt bảo lên đầu trang).
    _bhInit();
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
    var btnEl=document.getElementById('tn-btn');
    var cd=document.getElementById('tn-cd');
    if(btnEl)btnEl.classList.add('tn-counting');       // vòng tròn chỉ hiện SỐ (ẩn icon + chữ qua CSS).
    if(cd){cd.textContent=Math.max(0,state.remaining);cd.style.display='block';}
    _bhTimerUI();
}

// ================================================================
// GET CODE
// ================================================================
function getCode(){
    ajax('sitetop_get_code',{session_id:state.sessionId},function(r){
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
                // Lỗi CÓ thông báo (chưa qua Google / sai URL đích / hết hạn…) → HIỆN cho user biết lý do
                // thay vì để nút kẹt "0" im lặng. Vẫn poll lại phòng khi flag verify cross-site tới trễ.
                if(msg){ showToast(msg,5000,'warn'); }
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
        btn.classList.remove('tn-counting');btn.classList.add('tn-pill'); // giãn vòng tròn thành pill cho mã.
        // Kèm icon copy để user biết bấm được, chứ mã trần trông như nhãn tĩnh.
        btn.innerHTML='<span id="tn-code-t" style="letter-spacing:2px;font-size:12px;font-weight:700">'+code+'</span>'+
            '<svg id="tn-code-cp" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;opacity:.85"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
        btn.style.pointerEvents='auto';
        btn.style.cursor='pointer';
        btn.title='Bấm để sao chép mã';
        btn.onclick=function(){ _tnCopyCode(code); };
    }
    _bh.on=false;_bh.gate=null;_bh.idle=false;_bh.firstDone=false;_bhHide();
    state.code=code;
    state.codeReady=true;
    _bhForceHide();   // mã đã hiện → dọn nốt thẻ nổi nếu nó còn dựng từ trước
    try{localStorage.setItem('tn_btn_clicked','1');}catch(e){}
}
// Copy mã + báo server đã copy → trang unlock (tab kia) tự điền mã vào ô nhập.
function _tnCopyCode(code){
    var done=function(){
        var t=document.getElementById('tn-code-t');
        if(t){ var old=t.textContent; t.textContent='Đã copy!'; setTimeout(function(){ t.textContent=old; },1500); }
        showToast('Đã sao chép — quay lại tab nhiệm vụ, mã sẽ tự điền');
        // Báo về server. Không chặn thao tác copy nếu request lỗi.
        try{ ajax('sitetop_code_copied',{session_id:state.sessionId},function(){}); }catch(e){}
    };
    if(navigator.clipboard&&navigator.clipboard.writeText){
        navigator.clipboard.writeText(code).then(done,function(){ _tnCopyFallback(code); done(); });
    }else{ _tnCopyFallback(code); done(); }
}
function _tnCopyFallback(code){
    try{
        var t=document.createElement('textarea');
        t.value=code; t.style.position='fixed'; t.style.opacity='0';
        document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove();
    }catch(e){}
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
        // Camp 2step: CHƯA hoàn tất bước 2 (click 1 link nội bộ rồi quay lại) → KHÔNG lấy mã qua
        // heartbeat. Nếu không, hết bước 1 (countdown) heartbeat thấy server 'ready' → hiện mã ngay,
        // bỏ qua bước 2. Bước 2 hoàn tất ở trang quay lại (initStep2Return: getCode qua nút bấm, và
        // trafficType về mặc định '1step') nên guard này không chặn nhầm.
        if(state.trafficType==='2step'&&!state.step2Done)return;
        ajax('sitetop_unlock_heartbeat',{session_id:state.sessionId},function(r){
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
    ajax('sitetop_report_behavior',{
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
    bait.style.cssText='height:10px;width:10px;position:absolute;left:-100px;top:-100px;opacity:0.01;pointer-events:none;';
    bait.innerHTML='&nbsp;';
    document.body.appendChild(bait);
    setTimeout(function(){
        var blocked=false;
        try{
            if(!document.body.contains(bait)){blocked=true;}
            else{
                var s=window.getComputedStyle(bait);
                if(s.display==='none'||s.visibility==='hidden'||s.opacity==='0'){blocked=true;}
            }
        }catch(e){blocked=true;}
        if(blocked&&state.sessionId){
            ajax('sitetop_track_adblock',{session_id:state.sessionId},function(){});
        }
        try{bait.remove();}catch(e){}
    },500);
}

// ================================================================
// URL MATCH TRACKING (auto-detect target URL visited)
// ================================================================
function trackUrlMatch(){
    if(state.sessionId){
        ajax('sitetop_track_direct_click',{session_id:state.sessionId,url_matched:1},function(){});
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
    x.withCredentials=true;
    x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    x.onreadystatechange=function(){
        if(x.readyState===4&&x.status===200){
            try{cb(JSON.parse(x.responseText));}catch(e){console.warn('LN:',e);}
        }
    };
    var params='action='+encodeURIComponent(action);
    for(var k in data)params+='&'+encodeURIComponent(k)+'='+encodeURIComponent(data[k]);
    params+='&nonce='+encodeURIComponent('<?php echo esc_js(wp_create_nonce("sitetop_nonce")); ?>');
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
    var titleText='Click vào <b style="color:#dc2626;">1 link</b> bên dưới';
    // Chế độ ảnh: câu hướng dẫn đã nói đủ cả bước sau, nên bỏ dòng nhắc này.
    // Chế độ danh sách link giữ lại, vì câu trên không nói gì về bước tiếp theo.
    var hintHtml='<div style="font-size:11px;color:#a16207;margin-top:4px;">↩️ Sau đó <b>quay lại</b> để nhận mã</div>';

    // Ảnh bước 2 do admin cấu hình → thay danh sách link bằng 1 ảnh bấm được.
    // href BẮT BUỘC cùng domain: listenForLinkClick chỉ ghi cờ cho link nội bộ,
    // trỏ ra ngoài là user bấm xong không bao giờ nhận được mã.
    var s2=state.step2Image,s2Href='';
    if(s2&&s2.image_url){
        if(s2.target_url){
            try{if(new URL(s2.target_url,location.origin).hostname===location.hostname)s2Href=s2.target_url;}catch(e){}
        }
        if(!s2Href&&internalLinks.length>0)s2Href=internalLinks[0].url;
    }

    if(s2&&s2.image_url&&s2Href){
        titleText='<span style="display:inline-block;background:#fff;border:2px solid #f59e0b;border-radius:10px;padding:9px 13px;font-size:13px;font-weight:700;color:#92400e;line-height:1.55;box-shadow:0 2px 7px rgba(245,158,11,.28);">Bấm chọn vào <b style="color:#dc2626;">link giống ảnh</b><br>và lướt xuống cuối trang <b style="color:#dc2626;">Lấy Mã</b></span>';
        linksHtml='<div style="margin-top:8px;"><a href="'+s2Href.replace(/"/g,'%22')+'" id="tn-s2img" style="display:block;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.18);animation:tnBtnPulse 1.5s ease-in-out infinite;"><img src="'+s2.image_url.replace(/"/g,'%22')+'" alt="Click để tiếp tục" style="display:block;width:100%;max-width:280px;height:auto;"></a></div>';
        linksHtml+='<style>@keyframes tnBtnPulse{0%,100%{box-shadow:0 0 0 3px rgba(245,158,11,0.4)}50%{box-shadow:0 0 0 6px rgba(245,158,11,0.2)}}</style>';

        // Mẫu thu nhỏ của ĐÚNG cái nút mà user phải tìm ở cuối trang. Dùng lại C.icon và
        // C.clr nên mẫu luôn khớp nút thật, kể cả khi admin đổi logo hoặc màu widget.
        var _si=C.icon
            ? '<img src="'+C.icon+'" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block">'
            : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="'+C.txtClr+'" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="14" rx="2"/><path d="M12 8V5a3 3 0 0 0-3-3h0a3 3 0 0 0-3 3v0"/><path d="M18 8V5a3 3 0 0 0-3-3h0a3 3 0 0 0-3 3v0"/></svg>';
        hintHtml='<div style="display:flex;justify-content:center;margin-top:2px;">'
            +'<span style="display:inline-flex;align-items:center;gap:7px;padding:5px 11px 5px 5px;background:#fff;border:1.5px solid #f59e0b;border-radius:999px;font-size:11px;font-weight:700;color:#92400e;box-shadow:0 1px 4px rgba(245,158,11,.22)">'
            +'<span style="width:24px;height:24px;border-radius:50%;overflow:hidden;background:'+C.clr+';display:inline-flex;align-items:center;justify-content:center;flex:none">'+_si+'</span>'
            +'Tìm nút này ở cuối trang</span></div>';
    }else if(internalLinks.length>0){
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
        '<div style="font-size:12px;color:#78350f;line-height:1.6;text-align:center;padding:0 5px;">'+titleText+'</div>'+
        linksHtml+
        hintHtml;

    var w=document.getElementById('tn-w');
    if(w)w.appendChild(guide);

    // Ảnh hỏng/chặn → đổi sang nút chữ cùng href, không để user kẹt không bấm được gì.
    var s2a=guide.querySelector('#tn-s2img');
    if(s2a){
        var s2im=s2a.querySelector('img');
        if(s2im)s2im.onerror=function(){
            s2a.style.cssText='display:inline-block;padding:9px 18px;background:#1f2937;color:#fff;border-radius:16px;text-decoration:none;font-size:12px;font-weight:600;';
            s2a.textContent='👆 Click vào đây';
        };
    }

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
        btn.innerHTML='<span id="tn-btn-text"></span><span id="tn-cd" style="display:block">15</span>'; btn.classList.add('tn-counting');

        // Gọi start_timer để reset server timer
        ajax('sitetop_widget_start_timer',{session_id:savedSession,step2:'1'},function(){});

        // Countdown 15 giây rồi lấy mã
        var sec=15;
        var cdEl=document.getElementById('tn-cd');
        var t=setInterval(function(){
            sec--;
            if(sec>0){
                if(cdEl)cdEl.textContent=sec;
            }else{
                clearInterval(t);
                if(cdEl)cdEl.style.display='none';
                // Lấy mã
                ajax('sitetop_get_code',{session_id:savedSession},function(r){
                    if(r.success){
                        var code=r.data.code||r.data;
                        showCode(code);
                        return;
                    }
                    // Hiện ĐÚNG lý do server trả về thay vì câu chung chung 'Lỗi, thử lại'.
                    // Server phân biệt rõ 4 trường hợp (chưa đủ thời gian / sai URL đích /
                    // chưa qua Google / phiên không hợp lệ) nhưng chỗ này nuốt hết, nên
                    // user lẫn admin đều không biết hỏng ở đâu.
                    var d=r.data, msg='';
                    if(typeof d==='string') msg=d;
                    else if(d&&typeof d.message==='string') msg=d.message;
                    if(d&&d.data&&typeof d.data.remaining!=='undefined'){
                        msg+=' (còn '+Math.max(0,parseInt(d.data.remaining,10))+'s)';
                    }
                    showToast(msg||'Lỗi, thử lại',6000,'warn');
                    // Ghi ra Console: toast tự tắt sau 6s và có thể nằm ngoài tầm nhìn nếu
                    // user đang cuộn chỗ khác. Console giữ lại, chẩn đoán mới bắt được.
                    try{ if(window.console&&console.warn) console.warn('[SiteTop] Không lấy được mã:', r); }catch(e){}
                });
            }
        },1000);
    };
}

// Global functions for onclick
window._stWidgetClick=function(){
    // Block incognito/private browsing
    if(state.isIncognito){
        showToast('Bạn đang sử dụng trình duyệt ẩn danh, vui lòng tắt đi và thử lại!',4000,'warn');
        return;
    }
    // Block if keyword campaign but didn't come from Google
    if(state.googleRequired&&!state.googleVerified){
        // KHÔNG hiện referer ở đây nữa: sau khi chặn F5, referer vẫn có thể là google.com
        // (trình duyệt giữ nguyên qua lần tải lại) nên câu cũ tự mâu thuẫn — bảo "cần tìm
        // Google" trong khi đang khoe referer là Google. Nói thẳng việc user phải làm.
        showToast('Hãy thoát trang và thực hiện lại nhiệm vụ',6000,'warn');
        return;
    }
    // Block if URL path doesn't match target — hiện URL đúng để user copy/navigate
    if(!state.urlPathMatched){
        var expectPath=state.targetPath||state.targetUrl||'URL đích';
        var msg='Sai trang! Cần truy cập: '+expectPath;
        showToast(msg,6000,'warn');
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
        ajax('sitetop_widget_start_timer',{session_id:state.sessionId},function(){});

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
    // Chưa khớp phiên nào = KHÔNG đi qua link nhiệm vụ (vào thẳng trang đích, hoặc phiên
    // đã hết/đã dùng). Không được chạy đếm ngược — server cũng chặn (start_timer đòi visit
    // + khớp IP, get_code đòi session_id) nên đây chỉ là báo cho user biết phải làm gì.
    // Thử khớp phiên thêm 1 lần trước khi kết luận: widget có thể load TRƯỚC khi trang
    // nhiệm vụ kịp tạo visit ở tab kia. Khớp được thì wantStart tự chạy, khỏi bấm lại.
    if(!state.sessionReady){
        if(!window._stRetried){
            window._stRetried=true;
            state.wantStart=true;
            showToast('Đang kiểm tra phiên nhiệm vụ...',2500);
            sendVerifyAccess('','','','');
            setTimeout(function(){ if(!state.sessionReady)_stNoTask(); },2600);
            return;
        }
        _stNoTask();
    }
};

// Không khớp được phiên nhiệm vụ nào — vào thẳng trang đích, hoặc vào SAI URL đích.
// Chỉ user về xem lại ảnh hướng dẫn trên trang nhiệm vụ, thay cho "Chưa hợp lệ!" chung
// chung. KHÔNG xoá wantStart: nếu lát nữa verify khớp được phiên thì vẫn tự chạy đếm
// ngược, user không phải bấm lại.
function _stNoTask(){
    showToast('Truy cập sai URL, ra xem lại ảnh',6000,'warn');
}

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
