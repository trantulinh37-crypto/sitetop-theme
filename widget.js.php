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
// không banner. (Đồng bộ với lentop, nơi probe từng trip burst-block làm nút mất.)
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
        $challenge_token = $_GET['ct'] ?? $_COOKIE['traffictop_ct'] ?? '';
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
        sitekey: '" . esc_js(get_option('traffictop_turnstile_site_key', '')) . "',
        callback: function(token) {
            // Xác minh thành công → Set cookie và reload
            document.cookie = 'traffictop_ct=" . $new_token . ";path=/;max-age=3600;SameSite=Lax';
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
$default_countdown = intval(get_option('traffictop_widget_default_countdown', 30));
$widget_color = get_option('traffictop_widget_color', '#0D4F4F');
$widget_text_color = get_option('traffictop_widget_text_color', '#ffffff');
$widget_icon = get_option('traffictop_widget_icon', '');
$widget_btn_text = get_option('traffictop_widget_button_text', 'LẤY MÃ');
$ts_enabled = get_option('traffictop_turnstile_enabled', '0');
$ts_site_key = get_option('traffictop_turnstile_site_key', '');
$ts_key = ($ts_enabled === '1' && !empty($ts_site_key)) ? $ts_site_key : '';
?>
(function(){'use strict';
// Detect API origin from script src (handles domain alias: traffictop.net vs linkngon.top)
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
var state={sessionId:'',countdown:C.cd,onsiteTime:70,trafficType:'1step',remaining:C.cd,codeReady:false,code:null,sessionReady:false,countdownStarted:false,captchaToken:null,isIncognito:false,googleRequired:false,googleVerified:true,urlPathMatched:true,step2Done:false,wantStart:false};
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

    sendVerifyAccess(unlockSession,unlockTime,unlockActive,campaignType);
}

// Verify phiên với server — tách khỏi init() để click retry gọi lại được
// (đồng bộ cấu trúc lentop). Chỉ DI CHUYỂN nguyên khối từ init, không đổi logic.
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
                        if(e.data.token){ajax('traffictop_widget_captcha',{session_id:state.sessionId,token:e.data.token},function(){});}
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
            // không cần bấm lần 2. _lnWidgetClick tự re-check gate (Google/URL/ẩn danh).
            if(state.wantStart){state.wantStart=false;window._lnWidgetClick();}
        }catch(e){console.log('LN widget parse error:',e);}
    };
    x.send('action=traffictop_widget_verify_access&referer='+encodeURIComponent(document.referrer||'')+'&current_url='+encodeURIComponent(window.location.href)+'&unlock_session='+encodeURIComponent(unlockSession)+'&unlock_time='+encodeURIComponent(unlockTime)+'&unlock_active='+encodeURIComponent(unlockActive)+'&campaign_type='+encodeURIComponent(campaignType));
}

// ================================================================
// CREATE WIDGET UI - Inline tại vị trí <script> tag
// ================================================================
function createWidget(){
    if(document.getElementById('tn-w'))return;

    // Find the script tag to insert widget AFTER it. Prefer document.currentScript (_cs) — the EXACT
    // <script> being executed — so the widget appears right where the embed code is pasted. The old
    // src*="traffictop" selector missed alias domains (e.g. linkngon.top/widget.js), leaving anchor=null
    // → widget fell back to document.body and appeared stuck at the page bottom/footer regardless of
    // where the script was placed. _cs is captured synchronously at IIFE start, so it's reliable.
    var scripts=document.querySelectorAll('script[src*="widget.js"],script[src*="top.js"]'); // top.js = alias mã nhúng mới
    var anchor=_cs||(scripts.length?scripts[scripts.length-1]:null);

    // Optional placement — does NOT change show/hide logic, only WHERE the widget mounts:
    //   1. data-target="#selector" on the <script> tag → mount inside the matched element
    //   2. an empty <div id="traffictop-widget"></div> anywhere → mount inside it
    //   3. data-position="bottom-right|bottom-left|top-right|top-left" → fixed floating corner
    //   4. (default, unchanged) inline right after the <script> tag
    var cfgEl=_cs||anchor, mountEl=null, floatPos='';
    try{
        if(cfgEl){
            var tsel=cfgEl.getAttribute('data-target');
            if(tsel){
                var sel=(tsel.charAt(0)==='#'||tsel.charAt(0)==='.')?tsel:'#'+tsel;
                mountEl=document.querySelector(sel);
            }
            floatPos=(cfgEl.getAttribute('data-position')||'').toLowerCase().replace('fixed-','');
        }
    }catch(e){}
    if(!mountEl)mountEl=document.getElementById('traffictop-widget');

    var s=document.createElement('style');
    // Nút CỐ ĐỊNH tròn giữa bên phải màn hình (đồng bộ giao diện với source dethito/hoclaixe): đếm ngược
    // hiện SỐ trong vòng tròn (class tn-counting), mã hiện dạng pill (tn-pill), mọi hướng dẫn ra toast bên
    // trái. CHỈ đổi giao diện — KHÔNG đụng logic ẩn/hiện, đếm ngược, sinh mã, verify.
    s.textContent='#tn-w{position:fixed;top:calc(50% - 100px);right:0;transform:translateY(-50%);z-index:2147483000;text-align:center;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;display:block;width:auto;margin:0}'+
    '@media(min-width:769px){#tn-w{right:14px}}'+ // desktop: cách mép phải 14px (mobile giữ flush right:0)
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
    '#tn-toast{position:absolute;top:50%;right:calc(100% + 10px);left:auto;bottom:auto;transform:translateY(-50%);background:#1a7a3a;color:#fff;padding:8px 13px;border-radius:9px;font-size:12px;font-weight:600;line-height:1.35;z-index:9999999;opacity:0;transition:opacity .25s;pointer-events:none;white-space:normal;width:190px;text-align:center;box-shadow:0 5px 16px rgba(0,0,0,.24)}'+
    '#tn-toast.warn{background:#d9534f}'+
    '#tn-toast.show{opacity:1}'+
    // Placement tuỳ chọn cũ (data-position) → GỘP về giữa-phải (đã dời lên 100px như #tn-w) để mọi trang đích hiện nút giống nhau.
    '#tn-w.tn-float,#tn-w.tn-float-br,#tn-w.tn-float-bl,#tn-w.tn-float-tr,#tn-w.tn-float-tl{top:calc(50% - 100px);right:0;bottom:auto;left:auto;transform:translateY(-50%)}';
    document.head.appendChild(s);

    var w=document.createElement('div');
    w.id='tn-w';
    var iconHtml=C.icon?'<img src="'+C.icon+'" style="width:16px;height:16px">':'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="14" rx="2"/><path d="M12 8V5a3 3 0 0 0-3-3h0a3 3 0 0 0-3 3v0"/><path d="M18 8V5a3 3 0 0 0-3-3h0a3 3 0 0 0-3 3v0"/><line x1="12" y1="8" x2="12" y2="22"/></svg>';
    // Icon tùy chỉnh → class tn-logo (logo phủ kín nút) + chữ RỖNG (logo tự mang brand; :empty tự ẩn).
    // Chữ phải rỗng từ đầu chứ KHÔNG ẩn bằng CSS theo class — các trạng thái "Vui lòng đợi"/"Đang tải..."
    // thay innerHTML bằng text thuần, ẩn theo class sẽ làm nút trống trơn.
    w.innerHTML='<div id="tn-btn"'+(C.icon?' class="tn-logo"':'')+' onclick="window._lnWidgetClick()">'+iconHtml+'<span id="tn-btn-text">'+(C.icon?'':C.btnText)+'</span><span id="tn-cd"></span></div><iframe id="tn-captcha" style="display:none;border:none;width:220px;height:45px;margin-top:4px;overflow:hidden"></iframe><div id="tn-toast"></div>';

    // Nút CỐ ĐỊNH (position:fixed) → gắn THẲNG vào <body>, KHÔNG vào placeholder/anchor của trang đích.
    // Lý do: ancestor của placeholder/<script> nếu có transform/filter (slider/section động) hoặc
    // display:none theo media query (vd chỉ hiện mobile) sẽ NUỐT mất nút fixed → nút biến mất trên
    // desktop dù mobile vẫn thấy. Gắn body (giống source hoclaixe) → cài script ở đâu nút cũng hiện
    // đúng giữa-phải. (mountEl/floatPos cũ không còn ý nghĩa khi nút đã fixed giữa-phải.)
    if(document.body){
        document.body.appendChild(w);
    }else{
        document.addEventListener('DOMContentLoaded',function(){document.body.appendChild(w);});
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
// Máy đọc-cuộn (đồng bộ 4 site nguồn): đếm ngược chỉ chạy khi cuộn XUỐNG đúng nhịp;
// dừng >6s → tạm dừng + nhắc; tới đáy → vuốt nhẹ (bất kỳ hướng) trong 6s để tiếp tục.
var _canScroll=false,_frontier=0,_progAt=0,_anyScrollAt=0,_tooFastUntil=0,_warnUntil=0,_nagUntil=0,_fastDist=99999,_rWin=[],_readListenerAdded=false;
function _vh(){ return window.innerHeight||document.documentElement.clientHeight||600; }
function _scrollY(){ return window.pageYOffset||document.documentElement.scrollTop||0; }
function _scrollPct(){ var h=Math.max(document.documentElement.scrollHeight||0,(document.body||{}).scrollHeight||0)-_vh(); return h<=0?1:Math.min(1,_scrollY()/h); }
function _readNag(msg){ var n=Date.now(); if(n>=_nagUntil){ showToast(msg,2200,'warn'); _nagUntil=n+2500; } }
function _onReadScroll(){
    if(!state.countdownStarted||state.codeReady)return;
    var now=Date.now(),y=_scrollY(),p=_scrollPct(); _anyScrollAt=now;
    _rWin.push({t:now,y:y}); while(_rWin.length&&now-_rWin[0].t>2000)_rWin.shift();
    var dist=0; for(var i=1;i<_rWin.length;i++)dist+=Math.abs(_rWin[i].y-_rWin[i-1].y);
    if(dist>_fastDist){ _tooFastUntil=now+1600; }
    if(y>_frontier+2){ _frontier=y; _progAt=now; }                          // cuộn XUỐNG đúng hướng → tiến bộ.
    else if(p<0.97&&y<_frontier-30&&now>=_warnUntil){ showToast('Cuộn xuống để đọc tiếp ↓',2000); _warnUntil=now+2000; }
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
    if(_canScroll)return; // trang cuộn được → dùng cổng đọc-cuộn (6s), không dùng mouse-idle (30s).
    if(Date.now()-_lastMouseMove>_mouseIdleLimit){
        _pauseCountdown('mouse_idle');
    }
}
function _pauseCountdown(reason){
    if(_cdPaused)return;
    _cdPaused=true;
    if(timers.countdown){clearInterval(timers.countdown);timers.countdown=null;}
    showToast(reason==='mouse_idle'?'Di chuyển chuột hoặc chạm màn hình để tiếp tục':'Quay lại trang để tiếp tục',3000,'warn');
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
        var _now=Date.now();
        if(_canScroll){                                                    // trang cuộn được → cổng đọc-cuộn (KHÔNG clear interval, chỉ hoãn giây).
            if(_now<_tooFastUntil){ _readNag('Bạn lướt quá nhanh — đọc chậm lại!'); return; }
            var _p=_scrollPct();
            if(_p>=0.97){ if(_now-_lastMouseMove>10000){ _readNag('Di chuyển chuột hoặc chạm màn hình để tiếp tục'); return; } } // tới đáy → vuốt nhẹ bất kỳ hướng.
            else{ if(!(_progAt&&_now-_progAt<=10000)){ _readNag('Kéo xuống dưới để đọc & nhận mã ↓'); return; } }                          // dừng cuộn >6s → hoãn giây.
        }else{
            if(_now-_lastMouseMove>_mouseIdleLimit){_pauseCountdown('mouse_idle');return;} // trang ngắn không cuộn được → giữ cơ chế mouse-idle cũ.
        }
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
    // init máy đọc-cuộn (giống 4 site nguồn): trang > 2 màn hình mới bắt cuộn; ngắn hơn → đếm thường.
    var _h=Math.max(document.documentElement.scrollHeight||0,(document.body||{}).scrollHeight||0);
    _canScroll=_h>_vh()*2;
    _frontier=_scrollY(); _progAt=Date.now(); _anyScrollAt=Date.now(); _tooFastUntil=0; _warnUntil=0; _nagUntil=0; _rWin=[];
    var _txt=(((document.body&&document.body.innerText)||'').slice(0,200000)),_words=(_txt.match(/\S+/g)||[]).length;
    var _readSecs=Math.max(8,_words/200*60),_pace=Math.max(1,_h-_vh())/_readSecs;
    _fastDist=Math.min(Math.max(_pace*4,_vh()*0.9),_vh()*2.5)*2;             // ngưỡng lướt-nhanh theo tốc độ đọc 200 từ/phút.
    if(!_readListenerAdded){ _readListenerAdded=true; document.addEventListener('scroll',_onReadScroll,{passive:true}); document.addEventListener('touchmove',_onReadScroll,{passive:true}); }
    showToast(_canScroll?'Kéo xuống dưới để đọc — dừng quá 10 giây sẽ tạm dừng đếm ↓':'Vui lòng ở lại trang, chờ đủ thời gian để nhận mã');
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
}

// ================================================================
// GET CODE
// ================================================================
function getCode(){
    ajax('traffictop_get_code',{session_id:state.sessionId},function(r){
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
        btn.innerHTML='<span style="letter-spacing:2px;font-size:12px;font-weight:700">'+code+'</span>';
        btn.style.pointerEvents='auto';
        btn.style.cursor='pointer';
        btn.onclick=function(){
            if(navigator.clipboard){
                navigator.clipboard.writeText(code).then(function(){showToast('Đã sao chép!');});
            }else{
                var t=document.createElement('textarea');t.value=code;document.body.appendChild(t);t.select();document.execCommand('copy');t.remove();
                showToast('Đã sao chép!');
            }
        };
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
        // Camp 2step: CHƯA hoàn tất bước 2 (click 1 link nội bộ rồi quay lại) → KHÔNG lấy mã qua
        // heartbeat. Nếu không, hết bước 1 (countdown) heartbeat thấy server 'ready' → hiện mã ngay,
        // bỏ qua bước 2. Bước 2 hoàn tất ở trang quay lại (initStep2Return: getCode qua nút bấm, và
        // trafficType về mặc định '1step') nên guard này không chặn nhầm.
        if(state.trafficType==='2step'&&!state.step2Done)return;
        ajax('traffictop_unlock_heartbeat',{session_id:state.sessionId},function(r){
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
    ajax('traffictop_report_behavior',{
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
            ajax('traffictop_track_adblock',{session_id:state.sessionId},function(){});
        }
        try{bait.remove();}catch(e){}
    },500);
}

// ================================================================
// URL MATCH TRACKING (auto-detect target URL visited)
// ================================================================
function trackUrlMatch(){
    if(state.sessionId){
        ajax('traffictop_track_direct_click',{session_id:state.sessionId,url_matched:1},function(){});
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
    params+='&nonce='+encodeURIComponent('<?php echo esc_js(wp_create_nonce("traffictop_nonce")); ?>');
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
        btn.innerHTML='<span id="tn-btn-text"></span><span id="tn-cd" style="display:block">15</span>'; btn.classList.add('tn-counting');

        // Gọi start_timer để reset server timer
        ajax('traffictop_widget_start_timer',{session_id:savedSession,step2:'1'},function(){});

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
                ajax('traffictop_get_code',{session_id:savedSession},function(r){
                    if(r.success){
                        var code=r.data.code||r.data;
                        showCode(code);
                    }else{
                        showToast('Lỗi, thử lại',4000,'warn');
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
        var debugInfo='Cần tìm Google. Referer hiện tại: '+(state.refererHost||'(empty)');
        showToast(debugInfo,6000,'warn');
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
        ajax('traffictop_widget_start_timer',{session_id:state.sessionId},function(){});

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
    // Chưa khớp phiên → báo NGAY kết quả (cơ chế source dethito/hoclaixe), đồng thời
    // verify lại ÂM THẦM 1 lần (widget có thể load TRƯỚC khi page-unlock tạo visit
    // ở tab khác). Verify xong TỰ chạy đếm ngược (wantStart) — không cần bấm lần 2.
    if(!state.sessionReady){
        showToast('Chưa hợp lệ!',4000,'warn');
        state.wantStart=true;
        if(!window._lnRetried){
            window._lnRetried=true;
            sendVerifyAccess('','','','');
        }
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
