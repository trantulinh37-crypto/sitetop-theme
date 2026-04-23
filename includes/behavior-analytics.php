<?php
/**
 * Traffictop.net V2 - Behavior Analytics & Fraud Scoring
 * Flow 9c: 15+ factors, risk levels safe/low/medium/high
 * Auto-block: requires 2+ fraud incidents per IP
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Calculate fraud score (0-100) from behavior data
 * Exact factors and points from CLAUDE.md Flow 9c
 */
function traffictop_calculate_fraud_score( $data ) {
    $score = 0;
    $reasons = array();
    $is_mobile = ! empty( $data['is_mobile'] );

    // ── DEVICE (max +50) ──
    if ( ! empty( $data['is_bot'] ) ) {
        $score += 50; $reasons[] = 'bot_detected';
    }
    $sw = (int) ( $data['screen_width'] ?? 0 );
    $sh = (int) ( $data['screen_height'] ?? 0 );
    if ( $sw === 0 && $sh === 0 ) { $score += 25; $reasons[] = 'no_screen_size'; }
    elseif ( $sw < 300 && $sh < 300 && $sw > 0 ) { $score += 15; $reasons[] = 'small_screen'; }

    $vw = (int) ( $data['viewport_width'] ?? 0 );
    if ( $vw > 0 && $sw > 0 && $vw > $sw ) { $score += 10; $reasons[] = 'viewport_gt_screen'; }

    // ── BEHAVIOR (max +30) ──
    $mouse = (int) ( $data['mouse_movements'] ?? 0 );
    if ( ! $is_mobile ) {
        if ( $mouse === 0 ) { $score += 30; $reasons[] = 'no_mouse'; }
        elseif ( $mouse < 5 ) { $score += 15; $reasons[] = 'few_mouse'; }
    }

    $scroll = (int) ( $data['scroll_depth'] ?? 0 );
    if ( $scroll === 0 ) { $score += 10; $reasons[] = 'no_scroll'; }

    $clicks = (int) ( $data['clicks'] ?? 0 );
    if ( $clicks === 0 ) { $score += 20; $reasons[] = 'no_clicks'; }

    $keystrokes = (int) ( $data['keystrokes'] ?? 0 );
    if ( $keystrokes === 0 ) { $score += 10; $reasons[] = 'no_keystrokes'; }

    $touch = (int) ( $data['touch_events'] ?? 0 );
    if ( $is_mobile && $touch === 0 && $mouse === 0 ) { $score += 25; $reasons[] = 'no_touch_mobile'; }

    // ── TIME (max +25) ──
    $time = (int) ( $data['time_on_page'] ?? 0 );
    if ( $time < 5 ) { $score += 25; $reasons[] = 'time_lt_5s'; }
    elseif ( $time < 10 ) { $score += 10; $reasons[] = 'time_lt_10s'; }

    $idle = (int) ( $data['idle_time'] ?? 0 );
    if ( $time > 0 && $idle / max( 1, $time ) > 0.95 ) { $score += 15; $reasons[] = 'idle_gt_95pct'; }

    $hidden = (int) ( $data['page_hidden_time'] ?? 0 );
    if ( $time > 0 && $hidden > $time / 2 ) { $score += 10; $reasons[] = 'hidden_gt_visible'; }

    // ── NETWORK (max +30) ──
    $ip_for_wl = $data['ip_address'] ?? ( function_exists('traffictop_get_real_ip') ? traffictop_get_real_ip() : '' );
    $is_wl = function_exists('traffictop_is_ip_whitelisted') && traffictop_is_ip_whitelisted( $ip_for_wl );
    if ( ! $is_wl ) {
        if ( ! empty( $data['is_datacenter'] ) ) { $score += 30; $reasons[] = 'datacenter_ip'; }
        if ( ! empty( $data['is_vpn'] ) || ! empty( $data['is_proxy'] ) ) { $score += 20; $reasons[] = 'vpn_proxy'; }
    }

    // ── FINGERPRINT (max +30) ──
    $canvas = $data['canvas_hash'] ?? '';
    if ( empty( $canvas ) ) { $score += 10; $reasons[] = 'no_canvas'; }

    $webgl = $data['webgl_vendor'] ?? '';
    if ( empty( $webgl ) ) { $score += 5; $reasons[] = 'no_webgl'; }

    if ( ! empty( $data['devtools_open'] ) ) { $score += 15; $reasons[] = 'devtools_open'; }

    // Known fraud fingerprint (multi-user)
    if ( ! empty( $data['suspicious_fingerprint'] ) ) { $score += 20; $reasons[] = 'suspicious_fingerprint'; }
    if ( ! empty( $data['multi_account_fingerprint'] ) ) { $score += 50; $reasons[] = 'multi_account'; }

    // ── IP REPUTATION ──
    if ( ! empty( $data['avg_fraud_score'] ) && $data['avg_fraud_score'] > 60 ) {
        $score += 15; $reasons[] = 'bad_ip_reputation';
    }

    $score = min( 100, max( 0, $score ) );

    // Risk level
    $risk_level = 'safe';
    if ( $score >= 70 ) $risk_level = 'high';
    elseif ( $score >= 40 ) $risk_level = 'medium';
    elseif ( $score >= 20 ) $risk_level = 'low';

    return array(
        'fraud_score'  => $score,
        'risk_level'   => $risk_level,
        'fraud_reasons' => $reasons,
    );
}

/**
 * Save behavior analytics to database
 */
function traffictop_save_behavior_analytics( $visit_id, $session_id, $data ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';

    // Calculate fraud score
    $fraud = traffictop_calculate_fraud_score( $data );

    $wpdb->insert( "{$p}behavior_analytics", array(
        'visit_id'        => $visit_id,
        'session_id'      => $session_id,
        'user_id'         => get_current_user_id(),
        'ip_address'      => traffictop_get_real_ip(),
        'fraud_score'     => $fraud['fraud_score'],
        'fraud_reasons'   => wp_json_encode( $fraud['fraud_reasons'] ),
        'risk_level'      => $fraud['risk_level'],
        'mouse_movements' => absint( $data['mouse_movements'] ?? 0 ),
        'scroll_depth'    => absint( $data['scroll_depth'] ?? 0 ),
        'clicks'          => absint( $data['clicks'] ?? 0 ),
        'keystrokes'      => absint( $data['keystrokes'] ?? 0 ),
        'touch_events'    => absint( $data['touch_events'] ?? 0 ),
        'time_on_page'    => absint( $data['time_on_page'] ?? 0 ),
        'idle_time'       => absint( $data['idle_time'] ?? 0 ),
        'tab_switches'    => absint( $data['tab_switches'] ?? 0 ),
        'page_hidden_time' => absint( $data['page_hidden_time'] ?? 0 ),
        'is_mobile'       => absint( $data['is_mobile'] ?? 0 ),
        'is_bot'          => absint( $data['is_bot'] ?? 0 ),
        'screen_width'    => absint( $data['screen_width'] ?? 0 ),
        'screen_height'   => absint( $data['screen_height'] ?? 0 ),
        'viewport_width'  => absint( $data['viewport_width'] ?? 0 ),
        'viewport_height' => absint( $data['viewport_height'] ?? 0 ),
        'canvas_hash'     => sanitize_text_field( $data['canvas_hash'] ?? '' ),
        'webgl_vendor'    => sanitize_text_field( $data['webgl_vendor'] ?? '' ),
        'devtools_open'   => absint( $data['devtools_open'] ?? 0 ),
        'created_at'      => traffictop_current_time(),
    ));

    // Update visit fraud score
    if ( $visit_id ) {
        $wpdb->update( "{$p}shortlink_visits",
            array( 'fraud_score' => $fraud['fraud_score'] ),
            array( 'id' => $visit_id )
        );
    }

    // Auto-block: requires 2+ fraud incidents per IP (score >= 70)
    if ( $fraud['fraud_score'] >= 70 ) {
        $ip = traffictop_get_real_ip();
        $fraud_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}behavior_analytics WHERE ip_address = %s AND fraud_score >= 70",
            $ip
        ));
        if ( $fraud_count >= 2 ) {
            $wpdb->query( $wpdb->prepare(
                "INSERT INTO {$p}ip_reputation (ip_address, blocked, blocked_until, fraud_score, checked_at)
                 VALUES (%s, 1, DATE_ADD(%s, INTERVAL 24 HOUR), %d, %s)
                 ON DUPLICATE KEY UPDATE blocked=1, blocked_until=DATE_ADD(%s, INTERVAL 24 HOUR), fraud_score=%d",
                $ip, traffictop_current_time(), $fraud['fraud_score'], traffictop_current_time(),
                traffictop_current_time(), $fraud['fraud_score']
            ));
        }
    }

    // Check/save device fingerprint
    if ( ! empty( $data['canvas_hash'] ) ) {
        traffictop_save_device_fingerprint( $data );
    }

    return $fraud;
}

/**
 * Save device fingerprint
 */
function traffictop_save_device_fingerprint( $data ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';
    $user_id = get_current_user_id();
    $fp = sanitize_text_field( $data['canvas_hash'] ?? '' );
    if ( ! $fp || ! $user_id ) return;

    $existing = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$p}device_fingerprints WHERE user_id = %d AND fingerprint = %s",
        $user_id, $fp
    ));

    if ( $existing ) {
        $wpdb->update( "{$p}device_fingerprints", array(
            'last_seen' => traffictop_current_time(),
            'visit_count' => $existing->visit_count + 1,
        ), array( 'id' => $existing->id ) );
    } else {
        $wpdb->insert( "{$p}device_fingerprints", array(
            'user_id'           => $user_id,
            'fingerprint'       => $fp,
            'canvas_hash'       => $fp,
            'user_agent'        => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
            'screen_resolution' => ( $data['screen_width'] ?? 0 ) . 'x' . ( $data['screen_height'] ?? 0 ),
            'timezone_offset'   => (int) ( $data['timezone_offset'] ?? 0 ),
            'languages'         => sanitize_text_field( $data['languages'] ?? '' ),
            'first_seen'        => traffictop_current_time(),
            'last_seen'         => traffictop_current_time(),
        ));
    }

    // Check multi-account: same fingerprint, different users
    $multi = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT user_id) FROM {$p}device_fingerprints WHERE fingerprint = %s", $fp
    ));
    if ( $multi > 1 ) {
        // Flag for fraud scoring
        update_user_meta( $user_id, 'traffictop_multi_account_fp', $fp );
    }
}
