<?php
/**
 * LinkNgon V2 - VPN/Proxy Detection
 * API: ip-api.com (45 req/min)
 * Flow 9b: taskify_check_ip_fraud(), taskify_check_ip_api()
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Check IP via ip-api.com
 * Whitelisted: iCloud Private Relay, Apple Relay, Cloudflare WARP
 * VPN keywords: vpn, private, anonymous, hide, tunnel, nord, express, surfshark...
 * Scoring: proxy=+60, VPN=+50, hosting=+40, mobile=-20
 */
function linkngon_check_ip_api( $ip ) {
    if ( ! linkngon_get_option( 'ipapi_enabled', 1 ) ) {
        return array( 'risk_score' => 0 );
    }

    // Cache check (24h)
    $rep = linkngon_get_ip_reputation( $ip );
    if ( $rep && strtotime( $rep->checked_at ) > strtotime( '-24 hours' ) ) {
        return array(
            'is_vpn' => (bool) $rep->is_vpn, 'is_proxy' => (bool) $rep->is_proxy,
            'is_hosting' => (bool) $rep->is_hosting, 'is_mobile' => (bool) $rep->is_mobile,
            'risk_score' => (int) $rep->risk_score, 'country_code' => $rep->country_code,
            'isp' => $rep->isp, 'org' => $rep->org,
        );
    }

    // Rate limit: 45 req/min
    $rate_key = 'linkngon_ipapi_rate';
    $rate = (int) get_transient( $rate_key );
    if ( $rate >= 45 ) return array( 'risk_score' => 0, 'rate_limited' => true );
    set_transient( $rate_key, $rate + 1, 60 );

    // API call
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

    // VPN keyword detection in ISP/ORG/AS
    $vpn_keywords = array( 'vpn', 'private', 'anonymous', 'hide', 'tunnel', 'nord', 'express', 'surfshark', 'cyberghost', 'pia ', 'mullvad', 'proton' );
    $combined = strtolower( ( $data['isp'] ?? '' ) . ' ' . ( $data['org'] ?? '' ) . ' ' . ( $data['as'] ?? '' ) );

    // Whitelist: Apple/Cloudflare
    $whitelisted = array( 'icloud private relay', 'apple relay', 'cloudflare warp' );
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
    $p = $wpdb->prefix . 'linkngon_';
    $wpdb->query( $wpdb->prepare(
        "INSERT INTO {$p}ip_reputation (ip_address, is_vpn, is_proxy, is_hosting, is_mobile, risk_score, country_code, isp, org, as_number, checked_at)
         VALUES (%s, %d, %d, %d, %d, %d, '', %s, %s, %s, %s)
         ON DUPLICATE KEY UPDATE is_vpn=%d, is_proxy=%d, is_hosting=%d, is_mobile=%d, risk_score=%d, isp=%s, org=%s, as_number=%s, checked_at=%s",
        $ip, $is_vpn, $is_proxy, $is_hosting, $is_mobile, $risk_score,
        $data['isp'] ?? '', $data['org'] ?? '', $data['as'] ?? '', linkngon_current_time(),
        $is_vpn, $is_proxy, $is_hosting, $is_mobile, $risk_score,
        $data['isp'] ?? '', $data['org'] ?? '', $data['as'] ?? '', linkngon_current_time()
    ));

    // Auto-block if risk_score >= 70
    if ( $risk_score >= 70 ) {
        $should_block = false;
        if ( linkngon_get_option( 'block_proxy_ip', 1 ) && $is_proxy ) $should_block = true;
        if ( linkngon_get_option( 'block_vpn_ip', 1 ) && $is_vpn ) $should_block = true;
        if ( linkngon_get_option( 'block_datacenter_ip', 0 ) && $is_hosting ) $should_block = true;
        if ( $should_block ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$p}ip_reputation SET blocked=1, blocked_until=DATE_ADD(%s, INTERVAL 24 HOUR) WHERE ip_address=%s",
                linkngon_current_time(), $ip
            ));
        }
    }

    return array(
        'is_vpn' => $is_vpn, 'is_proxy' => $is_proxy, 'is_hosting' => $is_hosting,
        'is_mobile' => $is_mobile, 'risk_score' => $risk_score,
        'isp' => $data['isp'] ?? '', 'org' => $data['org'] ?? '',
    );
}
