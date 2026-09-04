<?php
/**
 * SiteTop.net V2 - VPN/Proxy Detection
 * API: ip-api.com (45 req/min)
 * Flow 9b: taskify_check_ip_fraud(), taskify_check_ip_api()
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Check if IP is in VPN/Proxy whitelist (bypasses all VPN/Proxy/Datacenter/Fraud checks)
 * Separate from ddos_whitelist — different purpose
 */
function sitetop_is_ip_whitelisted( $ip ) {
    if ( empty( $ip ) ) return false;
    static $list = null;
    if ( $list === null ) {
        $raw = sitetop_get_option( 'vpn_ip_whitelist', '' );
        $list = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
    }
    return in_array( trim( $ip ), $list, true );
}

/**
 * Check IP via ip-api.com
 * Whitelisted: iCloud Private Relay, Apple Relay, Cloudflare WARP
 * VPN keywords: tên thương hiệu đầy đủ (nordvpn, expressvpn, surfshark...) — KHÔNG
 *   dùng từ chung chung như 'private'/'express'/'nord' vì khớp chuỗi con sẽ chặn oan
 * Scoring: proxy=+60, VPN=+50, hosting=+40, mobile=-20
 */
function sitetop_check_ip_api( $ip ) {
    if ( ! sitetop_get_option( 'ipapi_enabled', 1 ) ) {
        return array( 'risk_score' => 0 );
    }

    if ( sitetop_is_ip_whitelisted( $ip ) ) {
        return array(
            'is_vpn' => false, 'is_proxy' => false, 'is_hosting' => false,
            'is_mobile' => false, 'risk_score' => 0, 'whitelisted' => true,
            'isp' => '', 'org' => '',
        );
    }

    // Cache check (24h)
    $rep = sitetop_get_ip_reputation( $ip );
    if ( $rep && strtotime( $rep->checked_at ) > strtotime( '-24 hours' ) ) {
        return array(
            'is_vpn' => (bool) $rep->is_vpn, 'is_proxy' => (bool) $rep->is_proxy,
            'is_hosting' => (bool) $rep->is_hosting, 'is_mobile' => (bool) $rep->is_mobile,
            'risk_score' => (int) $rep->risk_score, 'country_code' => $rep->country_code,
            'isp' => $rep->isp, 'org' => $rep->org,
        );
    }

    // Rate limit: 45 req/min
    $rate_key = 'sitetop_ipapi_rate';
    $rate = (int) get_transient( $rate_key );
    if ( $rate >= 45 ) return array( 'risk_score' => 0, 'rate_limited' => true );
    set_transient( $rate_key, $rate + 1, 60 );

    // API call
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) return array( 'risk_score' => 0 );
    // IMPORTANT: ip-api.com free tier only supports HTTP, NOT HTTPS
    $response = wp_remote_get( "http://ip-api.com/json/{$ip}?fields=status,proxy,hosting,mobile,isp,org,as", array( 'timeout' => 5 ) );
    if ( is_wp_error( $response ) ) return array( 'risk_score' => 0, 'error' => true );

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! $data || ( $data['status'] ?? '' ) !== 'success' ) return array( 'risk_score' => 0 );

    $risk_score = 0;
    $is_vpn = false;
    $is_proxy = (bool) ( $data['proxy'] ?? false );
    $is_hosting = (bool) ( $data['hosting'] ?? false );
    $is_mobile = (bool) ( $data['mobile'] ?? false );

    // Proxy → +60
    if ( $is_proxy ) $risk_score += 60;

    // Hosting/datacenter → +40
    if ( $is_hosting ) $risk_score += 40;

    // Dò VPN theo tên ISP/ORG/AS. PHẢI là tên thương hiệu đầy đủ — khớp chuỗi con nên
    // từ khoá chung chung là máy sinh báo nhầm: 'private' dính mọi nhà mạng tên
    // "... Private Limited" (rất phổ biến ở VN/Đông Nam Á) và dính cả iCloud Private
    // Relay của iPhone; 'express' dính mọi công ty có chữ Express; 'nord' dính Nordnet,
    // Nord-Est...; 'hide'/'pia ' dính vô số tên khác. Người dùng thật bị chặn oan.
    $vpn_keywords = array(
        'vpn', 'anonymous', 'tunnelbear', 'nordvpn', 'expressvpn', 'surfshark',
        'cyberghost', 'mullvad', 'protonvpn', 'private internet access', 'privatevpn',
        'hide.me', 'hidemyass', 'ipvanish', 'purevpn', 'windscribe', 'torguard',
    );
    $combined = strtolower( ( $data['isp'] ?? '' ) . ' ' . ( $data['org'] ?? '' ) . ' ' . ( $data['as'] ?? '' ) );

    // Whitelist hạ tầng của hãng lớn — KHÔNG phải VPN ẩn danh, user bình thường vẫn dùng.
    // Mảng này trước đây để RỖNG nên phần dò bên dưới không có gì cản.
    $whitelisted = array( 'apple', 'icloud', 'cloudflare', 'akamai', 'google', 'amazon technologies' );
    $is_whitelisted = false;
    foreach ( $whitelisted as $w ) {
        if ( stripos( $combined, $w ) !== false ) { $is_whitelisted = true; break; }
    }

    if ( ! $is_whitelisted ) {
        foreach ( $vpn_keywords as $kw ) {
            if ( stripos( $combined, $kw ) !== false ) { $is_vpn = true; $risk_score += 50; break; }
        }
    }

    // Mobile → -20 (reduce false positives)
    if ( $is_mobile ) $risk_score -= 20;
    $risk_score = max( 0, min( 100, $risk_score ) );

    // Save to ip_reputation
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $wpdb->query( $wpdb->prepare(
        "INSERT INTO {$p}ip_reputation (ip_address, is_vpn, is_proxy, is_hosting, is_mobile, risk_score, country_code, isp, org, as_number, checked_at)
         VALUES (%s, %d, %d, %d, %d, %d, '', %s, %s, %s, %s)
         ON DUPLICATE KEY UPDATE is_vpn=%d, is_proxy=%d, is_hosting=%d, is_mobile=%d, risk_score=%d, isp=%s, org=%s, as_number=%s, checked_at=%s",
        $ip, $is_vpn, $is_proxy, $is_hosting, $is_mobile, $risk_score,
        $data['isp'] ?? '', $data['org'] ?? '', $data['as'] ?? '', sitetop_current_time(),
        $is_vpn, $is_proxy, $is_hosting, $is_mobile, $risk_score,
        $data['isp'] ?? '', $data['org'] ?? '', $data['as'] ?? '', sitetop_current_time()
    ));

    // Auto-block if risk_score >= 70
    if ( $risk_score >= 70 ) {
        $should_block = false;
        if ( sitetop_get_option( 'block_proxy_ip', 1 ) && $is_proxy ) $should_block = true;
        if ( sitetop_get_option( 'block_vpn_ip', 1 ) && $is_vpn ) $should_block = true;
        if ( sitetop_get_option( 'block_datacenter_ip', 0 ) && $is_hosting ) $should_block = true;
        if ( $should_block ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$p}ip_reputation SET blocked=1, blocked_until=DATE_ADD(%s, INTERVAL %d MINUTE) WHERE ip_address=%s",
                sitetop_current_time(), SITETOP_IP_BLOCK_MINUTES, $ip
            ));
        }
    }

    return array(
        'is_vpn' => $is_vpn, 'is_proxy' => $is_proxy, 'is_hosting' => $is_hosting,
        'is_mobile' => $is_mobile, 'risk_score' => $risk_score,
        'isp' => $data['isp'] ?? '', 'org' => $data['org'] ?? '',
    );
}
