<?php
/**
 * Widget JS Endpoint với Full Anti-Cheat
 * Captcha chạy trong iframe từ domain shortlink
 */

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
        $challenge_token = $_GET['ct'] ?? $_COOKIE['linkngon_ct'] ?? '';
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
        sitekey: '" . esc_js(get_option('linkngon_turnstile_site_key', '')) . "',
        callback: function(token) {
            // Xác minh thành công → Set cookie và reload
            document.cookie = 'linkngon_ct=" . $new_token . ";path=/;max-age=3600;SameSite=Lax';
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
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-RateLimit-Remaining: ' . ($rate_config['per_minute'] - $count_60s - 1));
header('X-DDoS-Status: passed');
header('X-Global-Rate: ' . $global_count_1s . '/s, ' . $global_count_10s . '/10s');

$site_short = get_option('linkngon_widget_button_text', 'LẤY MÃ');
$site_url = home_url();
$default_countdown = intval(get_option('linkngon_widget_default_countdown', 30));
$widget_color = get_option('linkngon_widget_color', '#0D4F4F');
$widget_text_color = get_option('linkngon_widget_text_color', '#ffffff');
$widget_icon = get_option('linkngon_widget_icon', '');
$turnstile_enabled = get_option('linkngon_turnstile_enabled', '1');
$turnstile_site_key = get_option('linkngon_turnstile_site_key', '');

// Chỉ gửi tsKey nếu turnstile được bật
$ts_key_to_send = ($turnstile_enabled === '1' && !empty($turnstile_site_key)) ? $turnstile_site_key : '';
?>
(function(){'use strict';
var C={
    api:'<?php echo esc_js($site_url); ?>',
    name:'<?php echo esc_js($site_short); ?>',
    cd:<?php echo $default_countdown; ?>,
    clr:'<?php echo esc_js($widget_color); ?>',
    txtClr:'<?php echo esc_js($widget_text_color); ?>',
    icon:'<?php echo esc_js($widget_icon); ?>',
    tsKey:'<?php echo esc_js($ts_key_to_send); ?>'
};

var state={
    sessionId:'',
    countdown:C.cd,
    captchaToken:null,
    isRunning:false,
    trafficType:'1step',
    onsiteTime:70,
    step2Done:false,
    mode:'shortlink'
};

// Hàm detect Incognito mode - Using detectIncognito library (2024/2025)
// https://github.com/Joe12387/detectIncognito
function loadDetectIncognito(callback){
    // Kiểm tra xem có bật check incognito không (admin có thể tắt)
    var checkIncognito = true;
    try {
        if(typeof window.TASKIFY_CHECK_INCOGNITO !== 'undefined'){
            checkIncognito = window.TASKIFY_CHECK_INCOGNITO;
        }
    } catch(e) {}
    
    if(!checkIncognito){
        console.log('Widget: Incognito check disabled');
        callback(false);
        return;
    }
    
    // Timeout để tránh treo nếu library không load được
    var timeout = setTimeout(function(){
        console.log('Widget: detectIncognito timeout - assuming not incognito');
        callback(false);
    }, 3000);
    
    var script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/gh/Joe12387/detectIncognito@main/dist/es5/detectIncognito.min.js';
    script.onload = function(){
        clearTimeout(timeout);
        if(typeof detectIncognito === 'function'){
            detectIncognito().then(function(result){
                console.log('Widget detectIncognito:', result.browserName, 'isPrivate:', result.isPrivate);
                // Tin tưởng kết quả của library - không double check
                callback(result.isPrivate);
            }).catch(function(e){
                clearTimeout(timeout);
                console.log('Widget detectIncognito error:', e);
                callback(false);
            });
        }else{
            callback(false);
        }
    };
    script.onerror = function(){
        clearTimeout(timeout);
        console.log('Widget: Failed to load detectIncognito');
        callback(false);
    };
    document.head.appendChild(script);
}

// Hiển thị cảnh báo incognito - giữa màn hình
function showIncognitoWarning(){
    var overlay=document.createElement('div');
    overlay.id='tn-incognito-overlay';
    overlay.style.cssText='position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);z-index:999999;display:flex;align-items:center;justify-content:center;padding:20px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;';
    
    var warning=document.createElement('div');
    warning.style.cssText='display:flex;flex-direction:column;align-items:center;gap:14px;padding:28px 32px;background:#fff;border-radius:16px;border:3px solid #ef4444;text-align:center;max-width:360px;box-shadow:0 10px 40px rgba(0,0,0,0.3);';
    
    // Detect iOS để hiện hướng dẫn phù hợp
    var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
    var instructions = '';
    
    if(isIOS){
        instructions = '<div style="font-size:12px;color:#94a3b8;background:#f8fafc;padding:12px 16px;border-radius:8px;text-align:left;margin-top:4px;">'+
            '<b>💡 Cách tắt trên iPhone/iPad:</b><br>'+
            '1. Nhấn vào biểu tượng <b>Tabs</b> (góc dưới phải)<br>'+
            '2. Nhấn <b>"Riêng tư"</b> hoặc <b>"X Tab"</b> để chuyển về tab thường<br>'+
            '3. Truy cập lại link'+
            '</div>';
    } else {
        instructions = '<div style="font-size:12px;color:#94a3b8;background:#f8fafc;padding:12px 16px;border-radius:8px;text-align:left;margin-top:4px;">'+
            '<b>💡 Cách tắt:</b><br>'+
            '1. Đóng tất cả tab ẩn danh<br>'+
            '2. Mở trình duyệt bình thường<br>'+
            '3. Truy cập lại link'+
            '</div>';
    }
    
    warning.innerHTML='<div style="width:56px;height:56px;background:linear-gradient(135deg,#ef4444,#dc2626);border-radius:50%;display:flex;align-items:center;justify-content:center;"><svg width="28" height="28" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg></div>'+
        '<div style="font-size:18px;font-weight:700;color:#991b1b;">⚠️ Trình duyệt ẩn danh</div>'+
        '<div style="font-size:14px;color:#64748b;line-height:1.6;">Bạn đang truy cập bằng <b>trình duyệt ẩn danh</b>.<br>Vui lòng <b style="color:#dc2626;">tắt chế độ ẩn danh</b> và truy cập lại!</div>'+
        instructions;
    
    overlay.appendChild(warning);
    document.body.appendChild(overlay);
}

// Chạy widget ngay - check incognito sẽ được thực hiện sau khi verify nếu mode = shortlink
initWidgetFlow();

function initWidgetFlow(){
var isStep2Return=false;
var savedSessionId='';

try{
    var waiting=localStorage.getItem('tn_step2_waiting');
    var clicked=localStorage.getItem('tn_link_clicked');
    var stepTime=parseInt(localStorage.getItem('tn_step2_time')||'0');
    var now=Date.now();
    savedSessionId=localStorage.getItem('tn_session_id')||'';
    
    console.log('Widget localStorage check:', {waiting:waiting, clicked:clicked, stepTime:stepTime, savedSessionId:savedSessionId, timeDiff:now-stepTime});
    
    // Kiểm tra trong vòng 10 phút, đã click link, VÀ có savedSessionId
    if(waiting==='1'&&clicked==='1'&&savedSessionId&&(now-stepTime)<600000){
        isStep2Return=true;
        console.log('Widget: isStep2Return = true, using savedSessionId:', savedSessionId);
    }else if(!isStep2Return){
        // Chỉ clear step2 related keys, giữ lại các key để check skip
        localStorage.removeItem('tn_step2_waiting');
        localStorage.removeItem('tn_step2_time');
        localStorage.removeItem('tn_link_clicked');
        console.log('Widget: Cleared step2 localStorage data');
    }
}catch(e){
    console.log('Widget localStorage error:', e);
}

console.log('Widget flow:', isStep2Return ? 'initWidgetStep2Return' : 'verify_access');

if(isStep2Return){
    // Đã click link nội bộ, hiện widget với nút LẤY MÃ NGAY luôn (bypass verify)
    initWidgetStep2Return();
}else{
    // Flow bình thường - verify session
    var v=new XMLHttpRequest();
    v.open('POST',C.api+'/wp-admin/admin-ajax.php',true);
    v.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    v.onreadystatechange=function(){
        if(v.readyState===4&&v.status===200){
            try{
                var d=JSON.parse(v.responseText);
                console.log('Widget verify response:', d);
                if(d.success&&d.data){
                    console.log('session_valid:', d.data.session_valid, 'referer_valid:', d.data.referer_valid, 'url_valid:', d.data.url_valid);
                    console.log('debug info:', d.data.debug);
                    
                    // Nếu campaign có mã cố định, không hiện widget
                    if(d.data.hide_code_widget){
                        console.log('Widget: Campaign has fixed code, not showing widget');
                        return;
                    }
                    
                    if(d.data.session_valid&&d.data.referer_valid&&d.data.url_valid){
                        state.sessionId=d.data.session_id||'';
                        console.log('Widget init with sessionId:', state.sessionId, 'mode:', d.data.mode);
                        if(d.data.countdown)state.countdown=parseInt(d.data.countdown);
                        if(d.data.traffic_type)state.trafficType=d.data.traffic_type;
                        if(d.data.onsite_time)state.onsiteTime=parseInt(d.data.onsite_time);
                        
                        // Traffic_direct: Clear blocked session vì session được verify từ server-side (DB visit)
                        // Không dùng localStorage để track vì user copy URL qua tab mới
                        if(d.data.campaign_type==='traffic_direct'){
                            localStorage.removeItem('tn_blocked_session');
                            console.log('Widget: Traffic direct - cleared blocked session (server-side verification)');
                        }
                        
                        // Check nếu session này đã bị block trước đó (chỉ áp dụng cho non-traffic_direct)
                        var blockedSession=localStorage.getItem('tn_blocked_session')||'';
                        if(blockedSession&&blockedSession===state.sessionId){
                            console.log('Widget: This session was blocked, not showing');
                            return;
                        }
                        
                        // Check nếu user đã skip widget trước đó (cùng session)
                        // Áp dụng cho TẤT CẢ traffic types - user phải click nút trên URL đích đầu tiên
                        var prevShown=localStorage.getItem('tn_widget_shown');
                        var prevClicked=localStorage.getItem('tn_btn_clicked');
                        var prevSession=localStorage.getItem('tn_session_id');
                        
                        console.log('Widget skip check:', {prevShown:prevShown, prevClicked:prevClicked, prevSession:prevSession, currentSession:state.sessionId, currentTraffic:state.trafficType});
                        
                        // Không check skip cho mode test
                        if(d.data.mode!=='test'&&prevShown==='1'&&prevClicked!=='1'&&prevSession===state.sessionId){
                            // User đã skip widget ở session này
                            localStorage.setItem('tn_blocked_session',state.sessionId);
                            console.log('Widget: User skipped this session, blocking');
                            return;
                        }
                        
                        // Clear blocked session nếu là session mới
                        if(blockedSession&&blockedSession!==state.sessionId){
                            localStorage.removeItem('tn_blocked_session');
                            console.log('Widget: New session, cleared old blocked session');
                        }
                        
                        // Clear và set lại localStorage cho session mới
                        localStorage.removeItem('tn_widget_shown');
                        localStorage.removeItem('tn_btn_clicked');
                        localStorage.setItem('tn_session_id',state.sessionId);
                        localStorage.setItem('tn_traffic_type',state.trafficType||'1step');
                        
                        // Lưu mode để xử lý sau
                        state.mode=d.data.mode||'shortlink';
                        
                        // Nếu mode test và referer từ Google, lưu flag để navigate nội bộ vẫn hiện
                        if(d.data.mode==='test'&&d.data.debug&&d.data.debug.referer_type==='google'){
                            localStorage.setItem('tn_google_entry','1');
                        }
                        
                        // Logic incognito:
                        // - Mode shortlink + Ẩn danh → Hiện cảnh báo (chặn gian lận)
                        // - Mode shortlink + Bình thường → Hiện widget
                        // - Mode test + Ẩn danh → Không hiện widget (silent - không cho test bằng tab ẩn danh)
                        // - Mode test + Bình thường → Hiện widget
                        
                        if(state.mode==='shortlink'){
                            // User đã click shortlink - check incognito
                            loadDetectIncognito(function(isIncognito){
                                if(isIncognito){
                                    console.log('Widget: Incognito detected for shortlink mode - showing warning');
                                    showIncognitoWarning();
                                }else{
                                    console.log('Widget: Normal browser, shortlink mode - showing widget');
                                    // Init behavior tracker + adblock + url match
                                    BT.init(state.sessionId);
                                    detectAdblock();
                                    setTimeout(trackUrlMatch,2000);
                                    initWidget();
                                }
                            });
                        }else{
                            // Mode test - check incognito, ẩn danh thì không hiện widget
                            loadDetectIncognito(function(isIncognito){
                                if(isIncognito){
                                    console.log('Widget: Incognito detected for test mode - not showing widget (silent)');
                                    // Không hiện widget, không cảnh báo
                                }else{
                                    console.log('Widget: Normal browser, test mode - showing widget');
                                    // Init behavior tracker + adblock + url match
                                    BT.init(state.sessionId);
                                    detectAdblock();
                                    setTimeout(trackUrlMatch,2000);
                                    initWidget();
                                }
                            });
                        }
                    }else{
                        console.log('Widget NOT init - missing valid flags. error_reason:', d.data.error_reason);
                        // Chỉ hiện error cho các trường hợp có campaign thật nhưng bị lỗi
                        // Không hiện error cho invalid_referer, no_valid_session, hoặc blocked (keyword_search không từ Google, nocode...)
                        var reason = d.data.error_reason || 'verify_failed';
                        var blockedReason = d.data.debug && d.data.debug.blocked_reason ? d.data.debug.blocked_reason : '';
                        
                        // Clear tn_google_entry nếu invalid_referer (user vào trực tiếp, không từ Google)
                        if(reason === 'invalid_referer'){
                            try{
                                localStorage.removeItem('tn_google_entry');
                                console.log('Widget: Cleared tn_google_entry due to invalid_referer');
                            }catch(e){}
                        }
                        
                        // Clear tn_google_entry và unlock data khi no_valid_session
                        if(reason === 'no_valid_session'){
                            try{
                                localStorage.removeItem('tn_google_entry');
                                localStorage.removeItem('tn_unlock_session');
                                localStorage.removeItem('tn_unlock_time');
                                console.log('Widget: Cleared all session data due to no_valid_session');
                            }catch(e){}
                        }
                        
                        // Silent fail cho các trường hợp:
                        // - invalid_referer: user vào trực tiếp
                        // - no_valid_session: không có session
                        // - blocked_reason có giá trị: keyword_search không từ Google, nocode không hiện widget...
                        var isSilentFail = (reason === 'invalid_referer' || reason === 'no_valid_session' || blockedReason);
                        
                        if(!isSilentFail){
                            initWidgetError(reason);
                        }else{
                            console.log('Widget: Silent fail for', reason, blockedReason ? '(blocked: ' + blockedReason + ')' : '');
                        }
                    }
                }else{
                    console.log('Widget verify failed - no success or no data');
                    initWidgetError('no_response');
                }
            }catch(e){
                console.log('Widget verify parse error:', e);
                console.log('Widget verify raw response:', v.responseText);
                initWidgetError('parse_error');
            }
        }else if(v.readyState===4){
            console.log('Widget verify HTTP error:', v.status);
            console.log('Widget verify raw response:', v.responseText);
            initWidgetError('http_error');
        }
    };
    var hadGoogleEntry=localStorage.getItem('tn_google_entry')||'';
    var unlockSession=localStorage.getItem('tn_unlock_session')||'';
    var unlockTime=localStorage.getItem('tn_unlock_time')||'';
    var campaignType=localStorage.getItem('tn_campaign_type')||'';
    var unlockActive=sessionStorage.getItem('tn_unlock_active')||'';
    
    v.send('action=linkngon_widget_verify_access&referer='+encodeURIComponent(document.referrer||'')+'&current_url='+encodeURIComponent(window.location.href)+'&had_google_entry='+encodeURIComponent(hadGoogleEntry)+'&unlock_session='+encodeURIComponent(unlockSession)+'&unlock_time='+encodeURIComponent(unlockTime)+'&unlock_active='+encodeURIComponent(unlockActive)+'&campaign_type='+encodeURIComponent(campaignType));
}

// Widget hiện lỗi (để debug)
function initWidgetError(reason){
    var s=document.createElement('style');
    s.textContent='#tn-w{display:block;text-align:center;font-family:-apple-system,BlinkMacSystemFont,sans-serif;margin:10px auto;width:100%;position:relative}#tn-btn{display:inline-flex;align-items:center;justify-content:center;gap:5px;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;padding:3px 12px;border-radius:6px;font-size:11px;font-weight:600;cursor:default;border:none;min-width:70px}';
    document.head.appendChild(s);
    
    if(document.getElementById('tn-w')) return;
    
    // Thông báo thân thiện cho từng loại lỗi
    var errorMessages = {
        'wrong_url': '⚠️ Sai URL rồi, kiểm tra lại nhé!',
        'campaign_inactive': '⚠️ Chiến dịch đã kết thúc',
        'no_response': '⚠️ Lỗi kết nối',
        'parse_error': '⚠️ Lỗi dữ liệu',
        'http_error': '⚠️ Lỗi mạng'
    };
    var displayMsg = errorMessages[reason] || ('Debug: ' + reason);
    
    var w=document.createElement('div');
    w.id='tn-w';
    w.innerHTML='<button id="tn-btn">' + displayMsg + '</button>';
    
    var scripts=document.getElementsByTagName('script');
    for(var i=0;i<scripts.length;i++){
        if(scripts[i].src&&scripts[i].src.indexOf('widget.js')!==-1){
            scripts[i].parentNode.insertBefore(w,scripts[i].nextSibling);
            break;
        }
    }
}

// Widget cho trường hợp quay lại từ step2
function initWidgetStep2Return(){
    console.log('initWidgetStep2Return called with savedSessionId:', savedSessionId);
    var spinSvg='<svg class="tn-spin" width="14" height="14" viewBox="0 0 512 512" fill="'+C.txtClr+'"><path d="M304 48c0 26.51-21.49 48-48 48s-48-21.49-48-48 21.49-48 48-48 48 21.49 48 48zm-48 368c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.49-48-48-48zm208-208c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.49-48-48-48zM96 256c0-26.51-21.49-48-48-48S0 229.49 0 256s21.49 48 48 48 48-21.49 48-48zm12.922 99.078c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48c0-26.509-21.491-48-48-48zm294.156 0c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48c0-26.509-21.49-48-48-48zM108.922 60.922c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.491-48-48-48z"/></svg>';
    
    var s=document.createElement('style');
    s.textContent='#tn-w{display:block;text-align:center;font-family:-apple-system,BlinkMacSystemFont,sans-serif;margin:10px auto;width:100%;position:relative}#tn-btn{display:inline-flex;align-items:center;justify-content:center;gap:5px;background:linear-gradient(135deg,'+C.clr+','+C.clr+'dd);color:'+C.txtClr+';padding:3px 12px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;border:none;box-shadow:0 2px 6px '+C.clr+'33;min-width:70px;text-transform:none!important}#tn-btn.countdown{font-size:14px;font-weight:700}#tn-btn.success{background:linear-gradient(135deg,#10b981,#059669);color:#fff;font-family:monospace;letter-spacing:2px;font-size:14px;font-weight:700}#tn-btn.error{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff}#tn-btn.warning{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;animation:tnPulse 1s ease-in-out infinite}@keyframes tnPulse{0%,100%{opacity:1}50%{opacity:0.7}}@keyframes tnSpin{to{transform:rotate(360deg)}}.tn-spin{animation:tnSpin 1s linear infinite}.tn-toast{position:absolute;bottom:100%;left:50%;transform:translateX(-50%);background:#1f2937;color:#fff;padding:6px 12px;border-radius:6px;font-size:12px;white-space:nowrap;margin-bottom:8px;opacity:0;transition:opacity .3s}.tn-toast.show{opacity:1}';
    document.head.appendChild(s);
    
    var defaultIcon='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>';
    var btnIcon=C.icon?'<img src="'+C.icon+'" style="width:14px;height:14px;object-fit:contain;">':defaultIcon;
    
    if(document.getElementById('tn-w')) return;
    
    var w=document.createElement('div');
    w.id='tn-w';
    w.innerHTML='<button id="tn-btn">'+btnIcon+' '+C.name+'</button>';
    
    var scripts=document.getElementsByTagName('script');
    for(var i=0;i<scripts.length;i++){
        if(scripts[i].src&&scripts[i].src.indexOf('widget.js')!==-1){
            scripts[i].parentNode.insertBefore(w,scripts[i].nextSibling);
            break;
        }
    }
    
    var btn=document.getElementById('tn-btn');
    
    // Clear localStorage
    try{
        localStorage.removeItem('tn_step2_waiting');
        localStorage.removeItem('tn_step2_time');
        localStorage.removeItem('tn_link_clicked');
        localStorage.removeItem('tn_session_id');
    }catch(e){}
    
    // Click vào nút -> gọi start_countdown -> đếm 15s -> lấy mã
    btn.onclick=function(){
        btn.onclick=null;
        btn.innerHTML=spinSvg+' Đang tải...';
        
        // Gọi start_countdown để reset timer (với countdown=15)
        var sc=new XMLHttpRequest();
        sc.open('POST',C.api+'/wp-admin/admin-ajax.php',true);
        sc.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
        sc.onreadystatechange=function(){
            if(sc.readyState===4&&sc.status===200){
                // Bắt đầu đếm 15s
                btn.innerHTML=spinSvg+' 15';
                var sec=15;
                var t=setInterval(function(){
                    sec--;
                    if(sec>0){
                        btn.innerHTML=spinSvg+' '+sec;
                    }else{
                        clearInterval(t);
                        // Gọi API lấy mã
                        var x=new XMLHttpRequest();
                        x.open('POST',C.api+'/wp-admin/admin-ajax.php',true);
                        x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
                        x.onreadystatechange=function(){
                            if(x.readyState===4&&x.status===200){
                                try{
                                    var d=JSON.parse(x.responseText);
                                    if(d.success&&d.data.code){
                                        btn.className='success';
                                        btn.textContent=d.data.code;
                                        btn.style.cursor='pointer';
                                        btn.style.pointerEvents='auto';
                                        
                                        // Thêm toast nếu chưa có
                                        var toast=document.querySelector('.tn-toast');
                                        if(!toast){
                                            toast=document.createElement('div');
                                            toast.className='tn-toast';
                                            toast.textContent='Đã sao chép!';
                                            var w=document.getElementById('tn-w');
                                            if(w)w.insertBefore(toast,w.firstChild);
                                        }
                                        
                                        btn.onclick=function(){
                                            var code=btn.textContent;
                                            if(!code||code==='Lỗi!')return;
                                            
                                            var inp=document.createElement('textarea');
                                            inp.value=code;
                                            inp.style.position='fixed';
                                            inp.style.opacity='0';
                                            document.body.appendChild(inp);
                                            inp.focus();
                                            inp.select();
                                            try{document.execCommand('copy');}catch(e){}
                                            document.body.removeChild(inp);
                                            
                                            toast.classList.add('show');
                                            setTimeout(function(){toast.classList.remove('show')},2000);
                                        };
                                    }else{
                                        console.log('initWidgetStep2Return get_code error:', d);
                                        btn.className='error';
                                        btn.textContent='Lỗi!';
                                    }
                                }catch(e){
                                    console.log('initWidgetStep2Return get_code parse error:', e, x.responseText);
                                    btn.className='error';
                                    btn.textContent='Lỗi!';
                                }
                            }
                        };
                        console.log('initWidgetStep2Return calling get_code with session:', savedSessionId);
                        x.send('action=linkngon_get_code&session_id='+encodeURIComponent(savedSessionId));
                    }
                },1000);
            }else{
                console.log('initWidgetStep2Return start_countdown failed:', sc.status, sc.responseText);
            }
        };
        console.log('initWidgetStep2Return calling start_countdown with session:', savedSessionId);
        sc.send('action=linkngon_widget_start_timer&session_id='+encodeURIComponent(savedSessionId)+'&step2=1');
    };
}

function initWidget(){
    // SVG Spinner icon
    var spinSvg='<svg class="tn-spin" width="14" height="14" viewBox="0 0 512 512" fill="'+C.txtClr+'"><path d="M304 48c0 26.51-21.49 48-48 48s-48-21.49-48-48 21.49-48 48-48 48 21.49 48 48zm-48 368c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.49-48-48-48zm208-208c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.49-48-48-48zM96 256c0-26.51-21.49-48-48-48S0 229.49 0 256s21.49 48 48 48 48-21.49 48-48zm12.922 99.078c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48c0-26.509-21.491-48-48-48zm294.156 0c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48c0-26.509-21.49-48-48-48zM108.922 60.922c-26.51 0-48 21.49-48 48s21.49 48 48 48 48-21.49 48-48-21.491-48-48-48z"/></svg>';
    
    // CSS
    var s=document.createElement('style');
    s.textContent='#tn-w{display:block;text-align:center;font-family:-apple-system,BlinkMacSystemFont,sans-serif;margin:10px auto;width:100%;position:relative}#tn-btn{display:inline-flex;align-items:center;justify-content:center;gap:5px;background:linear-gradient(135deg,'+C.clr+','+C.clr+'dd);color:'+C.txtClr+';padding:3px 12px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;border:none;box-shadow:0 2px 6px '+C.clr+'33;min-width:70px;text-transform:none!important}#tn-btn.countdown{font-size:14px;font-weight:700}#tn-btn.success{background:linear-gradient(135deg,#10b981,#059669);color:#fff;font-family:monospace;letter-spacing:2px;font-size:14px;font-weight:700}#tn-btn.error{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff}#tn-btn.warning{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;animation:tnPulse 1s ease-in-out infinite}@keyframes tnPulse{0%,100%{opacity:1}50%{opacity:0.7}}@keyframes tnSpin{to{transform:rotate(360deg)}}.tn-spin{animation:tnSpin 1s linear infinite}#tn-captcha{border:none;width:300px;height:65px}.tn-toast{position:absolute;bottom:100%;left:50%;transform:translateX(-50%);background:#1f2937;color:#fff;padding:6px 12px;border-radius:6px;font-size:12px;white-space:nowrap;margin-bottom:8px;opacity:0;transition:opacity .3s}.tn-toast.show{opacity:1}#tn-btn2{display:inline-flex;align-items:center;gap:5px;background:linear-gradient(135deg,'+C.clr+','+C.clr+'dd);color:'+C.txtClr+';border:none;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;box-shadow:0 2px 6px '+C.clr+'33}';
    document.head.appendChild(s);
    
    // Icon
    var defaultIcon='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>';
    var btnIcon=C.icon?'<img src="'+C.icon+'" style="width:14px;height:14px;object-fit:contain;">':defaultIcon;
    
    // HTML - Tránh duplicate
    if(document.getElementById('tn-w')) return;
    
    var w=document.createElement('div');
    w.id='tn-w';
    w.innerHTML='<div class="tn-toast">Đã sao chép!</div><button id="tn-btn">'+btnIcon+' '+C.name+'</button><iframe id="tn-captcha" style="display:none"></iframe>';
    
    // Insert widget
    var scripts=document.getElementsByTagName('script');
    for(var i=0;i<scripts.length;i++){
        if(scripts[i].src&&scripts[i].src.indexOf('widget.js')!==-1){
            scripts[i].parentNode.insertBefore(w,scripts[i].nextSibling);
            break;
        }
    }
    
    // Đánh dấu widget đã hiện
    try{
        localStorage.setItem('tn_widget_shown','1');
    }catch(e){}
    
    var btn=document.getElementById('tn-btn');
    var captcha=document.getElementById('tn-captcha');
    var toast=w.querySelector('.tn-toast');
    
    // Listen captcha message
    window.addEventListener('message',function(e){
        if(e.origin!==C.api.replace(/\/$/,''))return;
        if(!e.data||!e.data.type)return;
        
        if(e.data.type==='captcha_success'){
            state.captchaToken=e.data.token;
            setTimeout(function(){
                captcha.style.display='none';
                btn.style.display='inline-flex';
                startCountdown();
            },2000);
        }else if(e.data.type==='captcha_error'||e.data.type==='captcha_expired'){
            setTimeout(function(){
                captcha.style.display='none';
                btn.style.display='inline-flex';
            },2000);
        }
    });
    
    // Click handler
    btn.onclick=function(){
        if(state.isRunning)return;
        
        // Đánh dấu user đã click nút
        try{
            localStorage.setItem('tn_btn_clicked','1');
        }catch(e){}
        
        // MODE TEST: Hiện thông báo thành công ngay
        if(state.mode==='test'){
            btn.className='success';
            btn.innerHTML='✓ Đã cài mã!';
            btn.style.cursor='default';
            return;
        }
        
        btn.innerHTML=spinSvg+' Đang tải...';
        btn.style.pointerEvents='none';
        
        // Skip captcha nếu không có key
        if(!C.tsKey){
            setTimeout(function(){
                btn.style.display='inline-flex';
                startCountdown();
            },1000);
            return;
        }
        
        var loadStart=Date.now();
        var minLoadTime=2000;
        
        captcha.src=C.api+'/widget-captcha/?session_id='+encodeURIComponent(state.sessionId)+'&origin='+encodeURIComponent(location.origin);
        
        captcha.onload=function(){
            var elapsed=Date.now()-loadStart;
            var delay=Math.max(0,minLoadTime-elapsed);
            setTimeout(function(){
                btn.style.display='none';
                captcha.style.display='inline-block';
            },delay);
        };
    };
    
    function startCountdown(){
        state.isRunning=true;
        btn.onclick=null;
        btn.className='countdown';
        btn.innerHTML=spinSvg;
        
        var x=new XMLHttpRequest();
        x.open('POST',C.api+'/wp-admin/admin-ajax.php',true);
        x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
        x.onreadystatechange=function(){
            if(x.readyState===4&&x.status===200){
                try{
                    var d=JSON.parse(x.responseText);
                    if(d.success){
                        var cd=d.data.countdown||d.data.remaining||state.countdown;
                        runCountdown(cd);
                    }else{
                        // Kiểm tra nếu cần nguồn hợp lệ (2step)
                        if(d.data && d.data.require_source){
                            btn.className='error';
                            var msg = d.data.message || 'Vui lòng thực hiện đúng các bước';
                            btn.innerHTML='<span style="font-size:10px;line-height:1.2;">⚠️ ' + msg.substring(0, 50) + '</span>';
                            btn.style.padding='6px 10px';
                            btn.style.height='auto';
                            state.isRunning=false;
                            // Reset sau 5s để user có thể thử lại
                            setTimeout(function(){
                                btn.className='';
                                btn.innerHTML='Bấm để lấy mã';
                                btn.style.padding='';
                                btn.style.height='';
                                btn.onclick=function(){ startCountdown(); };
                            },5000);
                        }else if(d.data && d.data.require_google){
                            // Backward compatibility
                            btn.className='error';
                            btn.innerHTML='<span style="font-size:10px;line-height:1.2;">⚠️ Vui lòng search từ khóa trên Google</span>';
                            btn.style.padding='6px 10px';
                            btn.style.height='auto';
                            state.isRunning=false;
                            setTimeout(function(){
                                btn.className='';
                                btn.innerHTML='Bấm để lấy mã';
                                btn.style.padding='';
                                btn.style.height='';
                                btn.onclick=function(){ startCountdown(); };
                            },5000);
                        }else{
                            btn.className='error';
                            btn.textContent=d.data&&d.data.message?d.data.message:'Lỗi!';
                            state.isRunning=false;
                        }
                    }
                }catch(e){
                    runCountdown(state.countdown);
                }
            }
        };
        x.send('action=linkngon_widget_start_timer&cf_token='+encodeURIComponent(state.captchaToken||'')+'&session_id='+encodeURIComponent(state.sessionId));
    }
    
    function runCountdown(sec){
        // Dùng onsite_time cho countdown đầu tiên (cả 1step và 2step)
        if(!state.step2Done && state.onsiteTime > 0){
            sec=state.onsiteTime;
        }
        
        btn.innerHTML=spinSvg+' '+sec;
        
        var isPaused = false;
        var intervalId = null;
        var lastInteractionTime = Date.now();
        var interactionCheckInterval = 10000; // 10 giây - yêu cầu tương tác mỗi 10s
        var isWaitingInteraction = false;
        var interactionListenersAdded = false;
        var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        
        console.log('runCountdown started, sec:', sec, 'isMobile:', isMobile);
        
        // Hàm cập nhật thời gian tương tác
        function updateInteraction(e){
            lastInteractionTime = Date.now();
            console.log('Interaction detected:', e ? e.type : 'unknown');
            
            // Nếu đang chờ tương tác, resume countdown
            if(isWaitingInteraction){
                isWaitingInteraction = false;
                isPaused = false;
                btn.innerHTML = spinSvg + ' ' + sec;
                btn.className = 'countdown';
                btn.style.padding = '';
                btn.style.height = '';
                btn.style.minWidth = '';
                if(!intervalId){
                    intervalId = setInterval(tick, 1000);
                }
                console.log('Countdown resumed at', sec);
            }
        }
        
        // Hàm pause và yêu cầu tương tác
        function pauseForInteraction(){
            if(intervalId){
                clearInterval(intervalId);
                intervalId = null;
            }
            isPaused = true;
            isWaitingInteraction = true;
            
            // Hiện cảnh báo
            btn.className = 'warning';
            var msg = isMobile ? '👆 Vuốt để tiếp tục' : '🖱️ Di chuột để tiếp tục';
            btn.innerHTML = '<span style="font-size:9px;line-height:1.3;display:block;">' + msg + '<br><small style="opacity:0.8;">⏸️ ' + sec + 's</small></span>';
            btn.style.padding = '8px 12px';
            btn.style.height = 'auto';
            btn.style.minWidth = '120px';
            console.log('Countdown paused at', sec, '- waiting for interaction');
        }
        
        // Thêm event listeners cho tương tác
        function addInteractionListeners(){
            if(interactionListenersAdded) return;
            interactionListenersAdded = true;
            
            console.log('Adding interaction listeners, isMobile:', isMobile);
            
            // Desktop: mouse move, click, scroll, keydown
            document.addEventListener('mousemove', updateInteraction, {passive: true});
            document.addEventListener('click', updateInteraction, {passive: true});
            document.addEventListener('scroll', updateInteraction, {passive: true});
            document.addEventListener('keydown', updateInteraction, {passive: true});
            
            // Mobile: touch events
            document.addEventListener('touchstart', updateInteraction, {passive: true});
            document.addEventListener('touchmove', updateInteraction, {passive: true});
            document.addEventListener('touchend', updateInteraction, {passive: true});
            
            // Window scroll (cho cả mobile và desktop)
            window.addEventListener('scroll', updateInteraction, {passive: true});
        }
        
        // Xóa event listeners
        function removeInteractionListeners(){
            document.removeEventListener('mousemove', updateInteraction);
            document.removeEventListener('click', updateInteraction);
            document.removeEventListener('scroll', updateInteraction);
            document.removeEventListener('keydown', updateInteraction);
            document.removeEventListener('touchstart', updateInteraction);
            document.removeEventListener('touchmove', updateInteraction);
            document.removeEventListener('touchend', updateInteraction);
            window.removeEventListener('scroll', updateInteraction);
        }
        
        function tick(){
            // Nếu đang pause do visibility, không làm gì
            if(isPaused && !isWaitingInteraction) return;
            
            // Kiểm tra thời gian tương tác cuối
            var timeSinceLastInteraction = Date.now() - lastInteractionTime;
            if(timeSinceLastInteraction >= interactionCheckInterval && !isWaitingInteraction){
                pauseForInteraction();
                return;
            }
            
            sec--;
            if(sec>0){
                btn.innerHTML=spinSvg+' '+sec;
                btn.style.padding = '';
                btn.style.height = '';
                btn.style.minWidth = '';
            }else{
                clearInterval(intervalId);
                intervalId = null;
                document.removeEventListener('visibilitychange', handleVisibility);
                removeInteractionListeners();
                
                // Kiểm tra traffic type
                if(state.trafficType==='2step'&&!state.step2Done){
                    // Hiện hướng dẫn click link (chưa có nút)
                    showStep2Guide();
                }else{
                    getCode();
                }
            }
        }
        
        function handleVisibility(){
            if(document.hidden){
                // User rời tab - pause countdown
                if(intervalId && !isWaitingInteraction){
                    clearInterval(intervalId);
                    intervalId = null;
                    isPaused = true;
                    btn.innerHTML='⏸️ ' + sec;
                    console.log('Tab hidden - countdown paused at', sec);
                }
            }else{
                // User quay lại tab - resume countdown (nếu không đang chờ interaction)
                if(isPaused && !intervalId && !isWaitingInteraction){
                    isPaused = false;
                    lastInteractionTime = Date.now(); // Reset interaction time khi quay lại
                    btn.innerHTML=spinSvg+' '+sec;
                    intervalId = setInterval(tick, 1000);
                    console.log('Tab visible - countdown resumed at', sec);
                }
            }
        }
        
        // Lắng nghe visibility change
        document.addEventListener('visibilitychange', handleVisibility);
        
        // Thêm interaction listeners NGAY LẬP TỨC
        addInteractionListeners();
        
        // Reset lastInteractionTime để bắt đầu đếm từ khi click nút
        lastInteractionTime = Date.now();
        
        // Bắt đầu countdown
        intervalId = setInterval(tick, 1000);
        console.log('Countdown interval started');
    }
    
    // Giai đoạn 1: Hiện hướng dẫn click link (chưa có nút)
    function showStep2Guide(){
        // Tránh tạo duplicate
        if(document.getElementById('tn-guide')) return;
        
        btn.style.display='none';
        
        // Quét link nội bộ trên trang
        var internalLinks = getInternalLinks();
        var linksHtml = '';
        
        if(internalLinks.length > 0){
            // Container
            linksHtml = '<div style="margin-top:8px;">';
            
            // Mũi tên con trỏ chỉ xuống - ở trên buttons
            linksHtml += '<div id="tn-pointer" style="display:flex;justify-content:center;margin-bottom:4px;animation:tnPointerBounce 0.8s ease-in-out infinite;">';
            linksHtml += '<svg width="20" height="20" viewBox="0 0 24 24" fill="#dc2626"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg>';
            linksHtml += '</div>';
            
            // Container cho các button
            linksHtml += '<div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;">';
            
            internalLinks.forEach(function(link, index){
                // Button đầu tiên có highlight đặc biệt
                var extraStyle = index === 0 ? 'animation:tnButtonPulse 1.5s ease-in-out infinite;box-shadow:0 0 0 3px rgba(245,158,11,0.4);' : '';
                linksHtml += '<a href="'+link.url+'" class="tn-step2-link" style="display:inline-block;padding:6px 12px;background:#f59e0b;color:#fff;border-radius:6px;text-decoration:none;font-size:11px;font-weight:600;transition:all 0.2s;'+extraStyle+'" onmouseover="this.style.background=\'#d97706\';this.style.transform=\'scale(1.05)\'" onmouseout="this.style.background=\'#f59e0b\';this.style.transform=\'scale(1)\'">'+link.text+'</a>';
            });
            linksHtml += '</div>';
            
            // Toast "Click vào đây" phía dưới buttons
            linksHtml += '<div id="tn-click-toast" style="display:flex;justify-content:center;margin-top:6px;animation:tnToastBounce 1s ease-in-out infinite;">';
            linksHtml += '<span style="background:#1f2937;color:#fff;padding:5px 12px;border-radius:16px;font-size:10px;font-weight:600;box-shadow:0 2px 8px rgba(0,0,0,0.2);">👆 Click vào đây</span>';
            linksHtml += '</div>';
            
            linksHtml += '</div>';
            
            // Thêm CSS animations
            linksHtml += '<style>';
            linksHtml += '@keyframes tnToastBounce{0%,100%{transform:translateY(0);}50%{transform:translateY(3px);}}';
            linksHtml += '@keyframes tnPointerBounce{0%,100%{transform:translateY(0);}50%{transform:translateY(3px);}}';
            linksHtml += '@keyframes tnButtonPulse{0%,100%{box-shadow:0 0 0 3px rgba(245,158,11,0.4);}50%{box-shadow:0 0 0 6px rgba(245,158,11,0.2);}}';
            linksHtml += '</style>';
        }
        
        var guide=document.createElement('div');
        guide.id='tn-guide';
        guide.style.cssText='display:flex;flex-direction:column;align-items:center;gap:10px;padding:14px 16px;background:linear-gradient(135deg,#fef3c7,#fed7aa);border-radius:12px;border:2px solid #f59e0b;text-align:center;max-width:320px;margin:0 auto;';
        
        guide.innerHTML='<div style="width:44px;height:44px;background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:50%;display:flex;align-items:center;justify-content:center;"><svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M13.5 5.5C14.59 5.5 15.5 4.58 15.5 3.5S14.59 1.5 13.5 1.5 11.5 2.42 11.5 3.5s.91 2 2 2zM9.89 19.38l1-4.38L13 17v6h2v-7.5l-2.11-2 .61-3A7.35 7.35 0 0 0 19 13v-2a5.32 5.32 0 0 1-4.39-2.33l-1-1.67A2 2 0 0 0 12 6a2.15 2.15 0 0 0-.89.21L6 8.83V13h2V9.83l1.89-.94L8.2 17l-4.7 1.3.5 1.9 6.89-1.82z"/></svg></div>'+
            '<div style="font-size:14px;font-weight:700;color:#92400e;">Gần xong rồi!</div>'+
            '<div style="font-size:12px;color:#78350f;line-height:1.6;text-align:center;padding:0 5px;">'+
            'Click vào <b style="color:#dc2626;">1 link</b> bên dưới'+
            '</div>'+
            linksHtml+
            '<div style="font-size:11px;color:#a16207;margin-top:4px;">↩️ Sau đó <b>quay lại</b> để nhận mã</div>';
        
        var w=document.getElementById('tn-w');
        w.appendChild(guide);
        
        // Lưu trạng thái vào localStorage để detect khi quay lại
        try{
            localStorage.setItem('tn_step2_waiting','1');
            localStorage.setItem('tn_step2_time',Date.now().toString());
            localStorage.setItem('tn_session_id',state.sessionId); // Lưu sessionId
        }catch(e){}
        
        // Lắng nghe sự kiện click link trên trang
        listenForLinkClick();
    }
    
    // Quét các link nội bộ trên trang
    function getInternalLinks(){
        var currentHost = window.location.hostname;
        var currentPath = window.location.pathname;
        var links = [];
        var seen = {};
        var maxLinks = 5;
        
        // Ưu tiên link trong menu/nav
        var menuLinks = document.querySelectorAll('nav a, .menu a, .nav a, header a, #menu a, .navbar a');
        menuLinks.forEach(function(a){
            if(links.length >= maxLinks) return;
            var href = a.href;
            var text = (a.textContent || '').trim();
            
            // Chỉ lấy link nội bộ, không phải trang hiện tại, có text
            if(href && text && text.length > 0 && text.length < 20 &&
               href.indexOf(currentHost) !== -1 && 
               new URL(href).pathname !== currentPath &&
               !seen[href] &&
               !href.includes('#') &&
               !href.includes('javascript:') &&
               !href.includes('tel:') &&
               !href.includes('mailto:')){
                seen[href] = true;
                links.push({url: href, text: text});
            }
        });
        
        // Nếu chưa đủ, lấy thêm từ footer
        if(links.length < maxLinks){
            var footerLinks = document.querySelectorAll('footer a');
            footerLinks.forEach(function(a){
                if(links.length >= maxLinks) return;
                var href = a.href;
                var text = (a.textContent || '').trim();
                
                if(href && text && text.length > 0 && text.length < 20 &&
                   href.indexOf(currentHost) !== -1 && 
                   new URL(href).pathname !== currentPath &&
                   !seen[href] &&
                   !href.includes('#') &&
                   !href.includes('javascript:') &&
                   !href.includes('tel:') &&
                   !href.includes('mailto:')){
                    seen[href] = true;
                    links.push({url: href, text: text});
                }
            });
        }
        
        return links;
    }
    
    // Lắng nghe click vào link nội bộ
    function listenForLinkClick(){
        var currentHost=window.location.hostname;
        
        document.addEventListener('click',function handler(e){
            var target=e.target;
            while(target&&target.tagName!=='A'){
                target=target.parentElement;
            }
            
            if(target&&target.tagName==='A'){
                var href=target.getAttribute('href');
                if(href){
                    // Kiểm tra link nội bộ (cùng domain hoặc relative)
                    var isInternal=false;
                    if(href.startsWith('/')||href.startsWith('#')||href.startsWith('./')){
                        isInternal=true;
                    }else{
                        try{
                            var linkHost=new URL(href,window.location.origin).hostname;
                            if(linkHost===currentHost){
                                isInternal=true;
                            }
                        }catch(ex){}
                    }
                    
                    if(isInternal&&!href.startsWith('#')){
                        // Đánh dấu đã click link
                        try{
                            localStorage.setItem('tn_link_clicked','1');
                        }catch(ex){}
                        document.removeEventListener('click',handler);
                    }
                }
            }
        });
    }
    
    function getCode(){
        btn.innerHTML=spinSvg;
        console.log('getCode called with sessionId:', state.sessionId);
        
        var x=new XMLHttpRequest();
        x.open('POST',C.api+'/wp-admin/admin-ajax.php',true);
        x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
        x.onreadystatechange=function(){
            if(x.readyState===4&&x.status===200){
                console.log('getCode response:', x.responseText);
                try{
                    var d=JSON.parse(x.responseText);
                    if(d.success&&d.data.code){
                        btn.className='success';
                        btn.textContent=d.data.code;
                        btn.style.pointerEvents='auto';
                        btn.style.cursor='pointer';
                        btn.onclick=function(){
                            var code=btn.textContent;
                            if(!code||code==='Lỗi!')return;
                            
                            var inp=document.createElement('textarea');
                            inp.value=code;
                            inp.style.position='fixed';
                            inp.style.opacity='0';
                            document.body.appendChild(inp);
                            inp.focus();
                            inp.select();
                            try{document.execCommand('copy');}catch(e){}
                            document.body.removeChild(inp);
                            
                            toast.classList.add('show');
                            setTimeout(function(){toast.classList.remove('show')},2000);
                        };
                    }else{
                        console.log('getCode error:', d);
                        btn.className='error';
                        btn.textContent='Lỗi!';
                    }
                }catch(e){
                    console.log('getCode parse error:', e);
                    btn.className='error';
                    btn.textContent='Lỗi!';
                }
            }
        };
        x.send('action=linkngon_get_code&session_id='+encodeURIComponent(state.sessionId));
    }
} // end initWidgetFlow
}

// ============================================================================
// ADBLOCK DETECTION
// ============================================================================
function detectAdblock(){
    var bait=document.createElement('div');
    bait.className='adsbox ad-placement';
    bait.style.cssText='height:10px;width:10px;position:fixed;left:-100px;top:-100px;opacity:0.01;pointer-events:none;';
    bait.innerHTML='&nbsp;';
    document.body.appendChild(bait);
    setTimeout(function(){
        var blocked=false;
        try{
            blocked=!bait.parentNode||bait.offsetParent===null||(window.getComputedStyle(bait).display==='none')||(window.getComputedStyle(bait).visibility==='hidden');
        }catch(e){blocked=true;}
        if(blocked&&state.sessionId){
            var x=new XMLHttpRequest();
            x.open('POST',C.api+'/wp-admin/admin-ajax.php',true);
            x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
            x.send('action=linkngon_track_adblock&session_id='+encodeURIComponent(state.sessionId));
        }
        try{bait.remove();}catch(e){}
    },500);
}

// ============================================================================
// URL MATCH TRACKING (auto-detect target URL visited)
// ============================================================================
function trackUrlMatch(){
    if(state.sessionId){
        var x=new XMLHttpRequest();
        x.open('POST',C.api+'/wp-admin/admin-ajax.php',true);
        x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
        x.send('action=linkngon_track_direct_click&session_id='+encodeURIComponent(state.sessionId)+'&url_matched=1');
    }
}

// ============================================================================
// BEHAVIOR TRACKER (Inline - Lightweight version)
// ============================================================================
var BT={
    data:{
        startTime:Date.now(),
        lastActivity:Date.now(),
        mouseMovements:0,
        mouseClicks:0,
        mouseDistance:0,
        scrollEvents:0,
        scrollDepth:0,
        keystrokes:0,
        touchEvents:0,
        focusEvents:0,
        blurEvents:0,
        copyEvents:0,
        pasteEvents:0,
        lastMouseX:0,
        lastMouseY:0,
        pageVisible:true,
        visibleStart:Date.now(),
        visibleTime:0,
        hiddenTime:0
    },
    
    init:function(sessionId){
        if(!sessionId)return;
        this.sessionId=sessionId;
        this.setupListeners();
        this.collectDeviceInfo();
        
        // Send data every 10s
        var self=this;
        this.interval=setInterval(function(){
            self.send();
        },10000);
        
        // Send on unload
        window.addEventListener('beforeunload',function(){
            self.send(true);
        });
    },
    
    setupListeners:function(){
        var d=this.data;
        var self=this;
        
        document.addEventListener('mousemove',function(e){
            d.mouseMovements++;
            if(d.lastMouseX>0||d.lastMouseY>0){
                var dx=e.clientX-d.lastMouseX;
                var dy=e.clientY-d.lastMouseY;
                d.mouseDistance+=Math.sqrt(dx*dx+dy*dy);
            }
            d.lastMouseX=e.clientX;
            d.lastMouseY=e.clientY;
            d.lastActivity=Date.now();
        });
        
        document.addEventListener('click',function(){
            d.mouseClicks++;
            d.lastActivity=Date.now();
        });
        
        window.addEventListener('scroll',function(){
            d.scrollEvents++;
            var scrollTop=window.pageYOffset||document.documentElement.scrollTop;
            var scrollHeight=document.documentElement.scrollHeight-window.innerHeight;
            var pct=scrollHeight>0?Math.round((scrollTop/scrollHeight)*100):0;
            if(pct>d.scrollDepth)d.scrollDepth=pct;
            d.lastActivity=Date.now();
        });
        
        document.addEventListener('keydown',function(){
            d.keystrokes++;
            d.lastActivity=Date.now();
        });
        
        document.addEventListener('touchstart',function(){
            d.touchEvents++;
            d.lastActivity=Date.now();
        });
        
        window.addEventListener('focus',function(){d.focusEvents++;});
        window.addEventListener('blur',function(){d.blurEvents++;});
        document.addEventListener('copy',function(){d.copyEvents++;});
        document.addEventListener('paste',function(){d.pasteEvents++;});
        
        document.addEventListener('visibilitychange',function(){
            var now=Date.now();
            if(document.hidden){
                d.visibleTime+=(now-d.visibleStart);
                d.pageVisible=false;
            }else{
                d.hiddenTime+=(now-d.visibleStart);
                d.visibleStart=now;
                d.pageVisible=true;
            }
        });
    },
    
    collectDeviceInfo:function(){
        var s=window.screen||{};
        var n=navigator||{};
        this.device={
            screen_width:s.width||0,
            screen_height:s.height||0,
            viewport_width:window.innerWidth||0,
            viewport_height:window.innerHeight||0,
            color_depth:s.colorDepth||0,
            pixel_ratio:window.devicePixelRatio||1,
            timezone:Intl.DateTimeFormat?Intl.DateTimeFormat().resolvedOptions().timeZone:'',
            timezone_offset:new Date().getTimezoneOffset(),
            language:n.language||'',
            platform:n.platform||'',
            cpu_cores:n.hardwareConcurrency||0,
            touch_support:'ontouchstart' in window||n.maxTouchPoints>0?1:0,
            max_touch_points:n.maxTouchPoints||0,
            user_agent:n.userAgent||''
        };
        
        // Canvas fingerprint
        try{
            var c=document.createElement('canvas');
            var ctx=c.getContext('2d');
            c.width=200;c.height=50;
            ctx.textBaseline='alphabetic';
            ctx.fillStyle='#f60';
            ctx.fillRect(125,1,62,20);
            ctx.fillStyle='#069';
            ctx.font='14px Arial';
            ctx.fillText('Taskify',2,15);
            this.device.canvas_hash=this.hash(c.toDataURL());
        }catch(e){this.device.canvas_hash='';}
        
        // WebGL
        try{
            var c=document.createElement('canvas');
            var gl=c.getContext('webgl')||c.getContext('experimental-webgl');
            if(gl){
                var dbg=gl.getExtension('WEBGL_debug_renderer_info');
                if(dbg){
                    this.device.webgl_vendor=gl.getParameter(dbg.UNMASKED_VENDOR_WEBGL)||'';
                    this.device.webgl_renderer=gl.getParameter(dbg.UNMASKED_RENDERER_WEBGL)||'';
                }
            }
        }catch(e){}
    },
    
    hash:function(s){
        var h=0;
        if(!s||s.length===0)return h.toString();
        for(var i=0;i<s.length;i++){
            var c=s.charCodeAt(i);
            h=((h<<5)-h)+c;
            h=h&h;
        }
        return Math.abs(h).toString(16);
    },
    
    getData:function(){
        var d=this.data;
        var now=Date.now();
        var timeOnPage=Math.round((now-d.startTime)/1000);
        var idleTime=Math.round((now-d.lastActivity)/1000);
        if(idleTime>10)idleTime=Math.min(idleTime,timeOnPage);else idleTime=0;
        
        if(d.pageVisible){
            d.visibleTime+=(now-d.visibleStart);
            d.visibleStart=now;
        }
        
        var result={};
        for(var k in this.device)result[k]=this.device[k];
        result.mouse_movements=d.mouseMovements;
        result.mouse_clicks=d.mouseClicks;
        result.mouse_distance=Math.round(d.mouseDistance);
        result.scroll_events=d.scrollEvents;
        result.scroll_depth=d.scrollDepth;
        result.keystrokes=d.keystrokes;
        result.touch_events=d.touchEvents;
        result.focus_events=d.focusEvents;
        result.blur_events=d.blurEvents;
        result.copy_events=d.copyEvents;
        result.paste_events=d.pasteEvents;
        result.time_on_page=timeOnPage;
        result.active_time=Math.max(0,timeOnPage-idleTime);
        result.idle_time=idleTime;
        result.page_visible_time=Math.round(d.visibleTime/1000);
        result.page_hidden_time=Math.round(d.hiddenTime/1000);
        return result;
    },
    
    send:function(sync){
        if(!this.sessionId)return;
        var data=this.getData();
        
        var fd=new FormData();
        fd.append('action','linkngon_report_behavior');
        fd.append('session_id',this.sessionId);
        fd.append('data',JSON.stringify(data));
        
        if(sync&&navigator.sendBeacon){
            navigator.sendBeacon(C.api+'/wp-admin/admin-ajax.php',fd);
        }else{
            fetch(C.api+'/wp-admin/admin-ajax.php',{method:'POST',body:fd}).catch(function(){});
        }
    },
    
    destroy:function(){
        if(this.interval)clearInterval(this.interval);
    }
};
})();
