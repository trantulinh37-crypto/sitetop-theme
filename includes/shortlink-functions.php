<?php
/**
 * Traffictop.net V2 - Core Shortlink Functions
 * CLAUDE.md: Flow 1, Section 8
 * 
 * Visit Step Lifecycle: started → google_clicked → target_visited → code_shown → verified
 * Transients: widget_code_ready_{sid}, widget_cd_{sid}, verify_code_{sid}, google_clicked_{sid}
 * Session: 32-char unique session_id
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   1. CREATE USER SHORTLINK (Publisher rút gọn link)
   Section 8: taskify_create_user_shortlink()
   ============================================================ */

function traffictop_create_user_shortlink( $user_id, $url, $custom_alias = '', $fallback_url = '', $created_via = 'manual' ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';

    $url = esc_url_raw( $url );
    if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        return new WP_Error( 'invalid_url', 'URL không hợp lệ' );
    }

    // Generate unique 6-char code
    $code = traffictop_generate_unique_shortcode();

    // Custom alias
    $alias = null;
    if ( $custom_alias ) {
        $alias = sanitize_title( $custom_alias );
        if ( strlen( $alias ) < 3 ) return new WP_Error( 'alias_short', 'Alias tối thiểu 3 ký tự' );
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}user_shortlinks WHERE alias = %s OR code = %s", $alias, $alias
        ));
        if ( $exists > 0 ) return new WP_Error( 'alias_taken', 'Alias đã được sử dụng' );
    }

    $data = array(
        'user_id'      => $user_id,
        'code'         => $code,
        'alias'        => $alias,
        'original_url' => $url,
        'fallback_url' => esc_url_raw( $fallback_url ),
        'status'       => 'active',
        'created_at'   => traffictop_current_time(),
    );

    // Defensive: chỉ ghi created_via nếu cột tồn tại (compat installs cũ chưa migrate)
    static $has_created_via = null;
    if ( $has_created_via === null ) {
        $has_created_via = (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'created_via'",
            $wpdb->prefix . 'traffictop_user_shortlinks'
        ));
    }
    if ( $has_created_via ) {
        $data['created_via'] = in_array( $created_via, array( 'api', 'manual' ), true ) ? $created_via : 'manual';
    }

    $wpdb->insert( "{$p}user_shortlinks", $data );

    return $wpdb->insert_id ?: new WP_Error( 'db_error', 'Không thể tạo link' );
}

/* ============================================================
   2. GENERATE UNIQUE SHORTCODE (6-char alphanumeric)
   Section 8: taskify_generate_unique_shortcode()
   ============================================================ */

function traffictop_generate_unique_shortcode( $length = 6 ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    do {
        $code = '';
        for ( $i = 0; $i < $length; $i++ ) $code .= $chars[ random_int( 0, strlen($chars) - 1 ) ];
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}user_shortlinks WHERE code = %s OR alias = %s", $code, $code
        ));
    } while ( $exists > 0 );
    return $code;
}

/* ============================================================
   3. GENERATE VISIT VERIFY CODE (8-char hex)
   Section 8: taskify_generate_visit_verify_code()
   ============================================================ */

function traffictop_generate_visit_verify_code() {
    return strtoupper( substr( bin2hex( random_bytes( 4 ) ), 0, 8 ) );
}

/* ============================================================
   4. GENERATE SESSION ID (32-char unique)
   ============================================================ */

function traffictop_generate_session_id() {
    return bin2hex( random_bytes( 16 ) ); // 32 chars
}

/* ============================================================
   5. LOOKUP SHORTLINK BY CODE OR ALIAS
   Section 8: taskify_get_shortlink_by_code_or_alias()
   ============================================================ */

function traffictop_get_shortlink_by_code_or_alias( $code_or_alias ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';
    return $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$p}user_shortlinks WHERE (code = %s OR alias = %s) AND status = 'active'",
        $code_or_alias, $code_or_alias
    ));
}

/* ============================================================
   BLOCK PAGE - Trang cảnh báo khi bị chặn
   ============================================================ */
function traffictop_show_block_page( $reason = 'blocked' ) {
    http_response_code( 403 );
    ?><!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fake IP</title></head>
<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f5f5">
<div style="text-align:center;max-width:440px;width:100%;margin:20px;background:#fff;border-radius:16px;padding:36px 28px;box-shadow:0 4px 24px rgba(0,0,0,.08)">
    <div style="width:72px;height:72px;border-radius:50%;background:#FEE2E2;display:inline-flex;align-items:center;justify-content:center;margin-bottom:20px">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    </div>
    <h1 style="font-size:20px;font-weight:800;color:#1a1a1a;margin:0 0 12px">&#9888; Fake IP</h1>
    <p style="font-size:15px;color:#555;line-height:1.7;margin:0 0 20px">Bạn đang truy cập bằng <strong>Fake IP / VPN / Proxy</strong>.<br>Vui lòng <strong>tắt chế độ Fake IP</strong> và truy cập lại!</p>
    <div style="background:#f9fafb;border-radius:12px;padding:16px 20px;text-align:left;margin-bottom:24px">
        <div style="font-size:13px;font-weight:700;color:#333;margin-bottom:10px">&#128161; Cách tắt:</div>
        <div style="font-size:13px;color:#666;line-height:1.8;padding-left:4px">1. Tắt ứng dụng VPN (1.1.1.1, NordVPN...)</div>
        <div style="font-size:13px;color:#666;line-height:1.8;padding-left:4px">2. Tắt Proxy trong cài đặt mạng</div>
        <div style="font-size:13px;color:#666;line-height:1.8;padding-left:4px">3. Truy cập lại link</div>
    </div>
    <a href="javascript:location.reload()" style="display:inline-block;padding:12px 36px;background:#0D4F4F;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;box-shadow:0 2px 8px rgba(13,79,79,.3)">Thử lại</a>
</div>
</body></html><?php
    exit;
}

/* ============================================================
   6. HANDLE SHORTLINK VISIT (Flow 1 entry point)
   /{shortcode} → page-unlock.php
   ============================================================ */

function traffictop_handle_shortlink_visit( $code ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';

    $ip = traffictop_get_real_ip();

    // Block check (ip_reputation + ddos_blocks) — check reason for better message
    if ( traffictop_is_ip_blocked( $ip ) ) {
        $rep = traffictop_get_ip_reputation( $ip );
        if ( $rep && ! empty( $rep->is_vpn ) ) traffictop_show_block_page( 'vpn' );
        elseif ( $rep && ! empty( $rep->is_proxy ) ) traffictop_show_block_page( 'proxy' );
        else traffictop_show_block_page( 'ip_blocked' );
    }

    // VPN/Proxy realtime check via ip-api.com (BEFORE validate_ip for better message)
    if ( function_exists( 'traffictop_check_ip_api' ) && traffictop_get_option( 'detect_vpn_proxy', 1 ) ) {
        $ip_check = traffictop_check_ip_api( $ip );
        $blocked_reason = '';
        if ( ! empty( $ip_check['is_proxy'] ) && traffictop_get_option( 'block_proxy_ip', 1 ) ) {
            $blocked_reason = 'proxy';
        } elseif ( ! empty( $ip_check['is_vpn'] ) && traffictop_get_option( 'block_vpn_ip', 1 ) ) {
            $blocked_reason = 'vpn';
        } elseif ( ! empty( $ip_check['is_hosting'] ) && traffictop_get_option( 'block_datacenter_ip', 0 ) ) {
            $blocked_reason = 'datacenter';
        }
        if ( $blocked_reason ) {
            // Auto-block in ip_reputation for future fast checks
            global $wpdb;
            $p = $wpdb->prefix . 'traffictop_';
            $wpdb->query( $wpdb->prepare(
                "INSERT INTO {$p}ip_reputation (ip_address, is_vpn, is_proxy, is_hosting, risk_score, blocked, checked_at)
                 VALUES (%s, %d, %d, %d, %d, 1, %s)
                 ON DUPLICATE KEY UPDATE is_vpn=%d, is_proxy=%d, is_hosting=%d, risk_score=%d, blocked=1, checked_at=%s",
                $ip, !empty($ip_check['is_vpn']), !empty($ip_check['is_proxy']), !empty($ip_check['is_hosting']),
                $ip_check['risk_score'] ?? 70, traffictop_current_time(),
                !empty($ip_check['is_vpn']), !empty($ip_check['is_proxy']), !empty($ip_check['is_hosting']),
                $ip_check['risk_score'] ?? 70, traffictop_current_time()
            ));
            traffictop_show_block_page( $blocked_reason );
        }
    }

    // Validate IP (DNS resolvers, private ranges) — after VPN check for better message
    if ( ! traffictop_validate_ip( $ip ) ) {
        traffictop_show_block_page( 'vpn' ); // Most likely VPN/proxy causing invalid IP
    }

    // Rate limit shortlink_click — ĐÃ GỠ chặn theo yêu cầu (không hiện trang "Too many requests." khi
    // vào shortlink). Lớp chống lạm dụng thật vẫn còn: anti-ddos (global/burst/sustained) + IP block/
    // VPN/proxy checks phía trên. Nếu cần bật lại: khôi phục traffictop_rate_limit_check('shortlink_click').

    // Lookup shortlink
    $shortlink = traffictop_get_shortlink_by_code_or_alias( $code );
    if ( ! $shortlink ) return; // Not a shortlink, let WP handle

    // Create or reuse visit session
    $session_id = traffictop_create_visit_session( $shortlink, $ip );
    if ( ! $session_id ) {
        wp_redirect( $shortlink->original_url );
        exit;
    }

    // Check if visit already has an active campaign (reuse case)
    $visit = $wpdb->get_row( $wpdb->prepare(
        "SELECT v.campaign_id, kc.status as camp_status
         FROM {$p}shortlink_visits v
         LEFT JOIN {$p}keyword_campaigns kc ON v.campaign_id = kc.id
         WHERE v.session_id = %s", $session_id
    ));

    $campaign = null;
    if ( $visit && $visit->campaign_id && $visit->camp_status === 'active' ) {
        // Reuse: campaign vẫn active → giữ nguyên, không chọn lại
        $campaign = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$p}keyword_campaigns WHERE id = %d", $visit->campaign_id
        ));
    }

    if ( ! $campaign ) {
        // New visit hoặc campaign cũ inactive → chọn campaign mới
        $campaign = traffictop_get_random_active_campaign( $ip );
        if ( ! $campaign ) {
            wp_redirect( ! empty( $shortlink->fallback_url ) ? $shortlink->fallback_url : $shortlink->original_url );
            exit;
        }
        // Assign campaign to visit
        $wpdb->update( "{$p}shortlink_visits", array(
            'campaign_id' => $campaign->id,
            'order_id'    => $campaign->order_id ?? 0,
        ), array( 'session_id' => $session_id ) );
    }

    // Store in session for page-unlock
    if ( ! session_id() ) @session_start();
    $_SESSION['traffictop_shortlink']  = $shortlink;
    $_SESSION['traffictop_campaign']   = $campaign;
    $_SESSION['traffictop_session_id'] = $session_id;

    // Set cross-site cookie for widget AJAX fallback (IP may differ due to dual-stack IPv4/IPv6)
    $cookie_opts = array(
        'expires'  => time() + traffictop_get_visit_expiry_seconds(),
        'path'     => '/',
        'secure'   => true,
        'httponly'  => false,
        'samesite'  => 'None',
    );
    if ( PHP_VERSION_ID >= 70300 ) {
        setcookie( 'traffictop_sid', $session_id, $cookie_opts );
    } else {
        setcookie( 'traffictop_sid', $session_id, $cookie_opts['expires'], $cookie_opts['path'] . '; SameSite=None', '', true, false );
    }

    // Include page-unlock directly (production pattern)
    include get_template_directory() . '/page-unlock.php';
    exit;
}

/* ============================================================
   7. CREATE VISIT SESSION (Flow 1 step 2)
   CLAUDE.md: taskify_create_visit_session() [verification.php:26]
   
   Reuse check: same IP + same shortlink + within expiry window + not verified + no verify_code
   Returns: session_id (32-char)
   ============================================================ */

function traffictop_get_visit_expiry_seconds() {
    $sec = (int) traffictop_get_option( 'verify_code_expiry', 600 );
    return max( 60, $sec );
}

function traffictop_create_visit_session( $shortlink, $ip ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';
    $now = traffictop_current_time();

    // Reuse check (window synced with verify_code_expiry setting)
    $expiry_sec = traffictop_get_visit_expiry_seconds();
    $existing = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$p}shortlink_visits
         WHERE shortlink_id = %d AND ip_address = %s
         AND step != 'verified'
         AND verified_at IS NULL
         AND verify_code IS NULL
         AND created_at > DATE_SUB(%s, INTERVAL %d SECOND)
         ORDER BY created_at DESC LIMIT 1",
        $shortlink->id, $ip, $now, $expiry_sec
    ));

    if ( $existing ) {
        $sid = $existing->session_id;

        delete_transient( 'traffictop_widget_code_ready_' . $sid );
        delete_transient( 'traffictop_widget_cd_' . $sid );
        delete_transient( 'traffictop_widget_code_' . $sid );
        delete_transient( 'traffictop_verify_code_' . $sid );
        delete_transient( 'traffictop_google_clicked_' . $sid );

        // Reset session — preserve created_at so countdown doesn't reset on reload.
        // Also clear the anti-fraud flags so a reused row cannot carry over stale
        // from_google=1 / url_matched=1 from a previous (possibly different campaign)
        // attempt and skip the checks on this fresh attempt.
        $wpdb->update( "{$p}shortlink_visits", array(
            'step'              => 'started',
            'verify_code'       => null,
            'code_shown_at'     => null,
            'from_google'       => 0,
            'url_matched'       => 0,
            'google_clicked_at' => null,
            'target_visited_at' => null,
        ), array( 'id' => $existing->id ) );

        return $sid;
    }

    // New session
    $session_id = traffictop_generate_session_id();
    // user_id = shortlink OWNER (publisher), NOT visitor
    $user_id = (int) $shortlink->user_id;

    // Check IP daily limit
    $ip_limit = (int) traffictop_get_option( 'shortlink_ip_limit_24h', 2 );
    $today = date( 'Y-m-d', strtotime( $now ) );
    $ip_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits WHERE ip_address = %s AND step = 'verified' AND DATE(created_at) = %s",
        $ip, $today
    ));
    $ip_exceeded = $ip_count >= $ip_limit;

    $insert_data = array(
        'shortlink_id'     => $shortlink->id,
        'user_id'          => $user_id,
        'session_id'       => $session_id,
        'ip_address'       => $ip,
        'original_ip'      => $ip,
        'user_agent'       => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
        // wp_get_referer() chạy qua wp_validate_redirect() → strip external hosts → DB rỗng
        'referer'          => sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) ),
        'step'             => 'started',
        'ip_limit_exceeded' => $ip_exceeded ? 1 : 0,
        'created_at'       => $now,
    );

    // UTM capture — defensive existence check (compat với DB chưa migrate)
    static $has_utm_cols = null;
    if ( $has_utm_cols === null ) {
        $has_utm_cols = (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'utm_source'",
            $wpdb->prefix . 'traffictop_shortlink_visits'
        ));
    }
    if ( $has_utm_cols ) {
        $utm_src  = substr( sanitize_text_field( wp_unslash( $_GET['utm_source']   ?? '' ) ), 0, 100 );
        $utm_med  = substr( sanitize_text_field( wp_unslash( $_GET['utm_medium']   ?? '' ) ), 0, 100 );
        $utm_camp = substr( sanitize_text_field( wp_unslash( $_GET['utm_campaign'] ?? '' ) ), 0, 150 );
        if ( $utm_src  !== '' ) $insert_data['utm_source']   = $utm_src;
        if ( $utm_med  !== '' ) $insert_data['utm_medium']   = $utm_med;
        if ( $utm_camp !== '' ) $insert_data['utm_campaign'] = $utm_camp;
    }

    $wpdb->insert( "{$p}shortlink_visits", $insert_data );

    if ( ! $wpdb->insert_id ) return null;

    // Increment shortlink click counter
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}user_shortlinks SET total_clicks = total_clicks + 1 WHERE id = %d",
        $shortlink->id
    ));

    // Store in PHP session
    if ( ! session_id() ) @session_start();
    $_SESSION['traffictop_shortlink'] = $shortlink;
    $_SESSION['traffictop_session_id'] = $session_id;

    return $session_id;
}

/* ============================================================
   8. GET WIDGET CODE (Flow 1 - "Get Code" button)
   
   TIME CHECK: elapsed >= max(onsite_time - 5, 10)
   nocode → SKIP time check
   If verify_code exists in DB → return cached
   Else → generate 8-char hex code
   Set transient verify_code_{session_id} with expiry (default 600s)
   Update visit: step='code_shown', code_shown_at=now
   ============================================================ */

function traffictop_get_widget_code( $session_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';

    $visit = $wpdb->get_row( $wpdb->prepare(
        "SELECT v.*, kc.onsite_time as camp_onsite, kc.traffic_type, kc.campaign_type,
                kc.fixed_code, kc.countdown_seconds
         FROM {$p}shortlink_visits v
         LEFT JOIN {$p}keyword_campaigns kc ON v.campaign_id = kc.id
         WHERE v.session_id = %s", $session_id
    ));

    if ( ! $visit || $visit->step === 'verified' ) {
        return new WP_Error( 'invalid', 'Visit không hợp lệ' );
    }

    // IP ownership check
    $ip = function_exists('traffictop_get_real_ip') ? traffictop_get_real_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
    if ( $visit->ip_address !== $ip ) {
        return new WP_Error( 'invalid', 'Visit không hợp lệ' );
    }

    $traffic_type = $visit->traffic_type ?? '1step';
    $is_nocode = ( $traffic_type === 'nocode' );

    // If code already exists in DB → return cached.
    // ALSO refresh transients (set lại với expiry mới) — fix bug "Code chưa sẵn sàng":
    // - Lần đầu generate code: DB + transient set OK
    // - Heartbeat/reload widget call get_code lần 2: DB có code → return early
    // - NẾU không set lại transient: subsequent verify_and_pay tìm transient
    //   không có → trả "Code chưa sẵn sàng"
    // Set lại an toàn — transient API là set, không phải add (idempotent).
    if ( $visit->verify_code ) {
        $expiry = (int) traffictop_get_option( 'verify_code_expiry', 600 );
        set_transient( 'traffictop_widget_code_ready_' . $session_id, 1, $expiry );
        set_transient( 'traffictop_verify_code_' . $session_id, $visit->verify_code, $expiry );
        return $visit->verify_code;
    }

    // TIME CHECK + FLAG CHECK (skip for nocode)
    if ( ! $is_nocode ) {
        $created_at = strtotime( $visit->created_at );
        $now = strtotime( traffictop_current_time() );
        $elapsed = $now - $created_at;
        $onsite = (int) ( $visit->camp_onsite ?? $visit->onsite_time ?? 70 );
        $required = max( $onsite - 5, 10 );

        if ( $elapsed < $required ) {
            return new WP_Error( 'too_fast', 'Chưa đủ thời gian', array(
                'remaining' => $required - $elapsed,
            ));
        }

        // Enforce url_matched + from_google before code generation
        if ( ! $visit->url_matched ) {
            return new WP_Error( 'url_not_matched', 'Bạn chưa truy cập đúng URL đích. Vui lòng truy cập đúng link được hướng dẫn.' );
        }
        // Google referrer chỉ bắt buộc cho campaign_type='keyword_search'.
        // KHÔNG dùng !empty($visit->keyword) — traffic_direct cũng có thể có
        // field keyword được lưu (form không phụ thuộc task_type) → bị block sai.
        $campaign_type = $visit->campaign_type ?? 'keyword_search';
        if ( $campaign_type === 'keyword_search' && ! $visit->from_google ) {
            return new WP_Error( 'no_google', 'Bạn chưa truy cập từ Google. Vui lòng tìm từ khóa trên Google và click vào kết quả.' );
        }
    }

    // Generate code
    if ( $is_nocode && $visit->fixed_code ) {
        $code = $visit->fixed_code; // Case-sensitive
    } else {
        $code = traffictop_generate_visit_verify_code(); // 8-char hex
    }

    // Save code + update step
    $wpdb->update( "{$p}shortlink_visits", array(
        'verify_code'   => $code,
        'step'          => 'code_shown',
        'code_shown_at' => traffictop_current_time(),
    ), array( 'session_id' => $session_id ) );

    // Set transients by session_id
    $expiry = (int) traffictop_get_option( 'verify_code_expiry', 600 ); // 10 min default
    set_transient( 'traffictop_widget_code_ready_' . $session_id, 1, $expiry );
    set_transient( 'traffictop_verify_code_' . $session_id, $code, $expiry ); // 10 min

    return $code;
}

// Alias
function traffictop_get_verify_code( $session_id ) {
    return traffictop_get_widget_code( $session_id );
}

/* ============================================================
   8b. UPDATE VISIT STEP (guard against regression from 'verified')
   Production: taskify_update_visit_step()
   ============================================================ */

function traffictop_update_visit_step( $session_id, $step ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';

    $time_field = '';
    switch ( $step ) {
        case 'google_clicked':  $time_field = 'google_clicked_at'; break;
        case 'target_visited':  $time_field = 'target_visited_at'; break;
        case 'code_shown':      $time_field = 'code_shown_at'; break;
        case 'verified':        $time_field = 'verified_at'; break;
    }

    $update = array( 'step' => $step );
    if ( $time_field ) $update[ $time_field ] = traffictop_current_time();

    // Guard: don't regress from 'verified'
    $set_parts = array();
    $values = array();
    foreach ( $update as $k => $v ) {
        $set_parts[] = "`{$k}` = %s";
        $values[] = $v;
    }
    $values[] = $session_id;

    return $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}shortlink_visits SET " . implode( ', ', $set_parts ) . " WHERE session_id = %s AND step != 'verified'",
        ...$values
    ));
}

/* ============================================================
   9. CAMPAIGN CRUD
   ============================================================ */

function traffictop_create_keyword_campaign( $data ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';

    // V2 traffic types (bỏ social): 1step, 2step, nocode
    $valid_types = array( '1step', '2step', 'nocode' );
    $traffic_type = in_array( $data['traffic_type'] ?? '', $valid_types ) ? $data['traffic_type'] : '1step';

    // V2 task types: keyword_search, traffic_direct (bỏ traffic_social)
    $valid_task = array( 'keyword_search', 'traffic_direct' );
    $task_type = in_array( $data['task_type'] ?? '', $valid_task ) ? $data['task_type'] : 'keyword_search';

    $keyword = sanitize_text_field( $data['keyword'] ?? '' );
    if ( $task_type === 'keyword_search' && trim( $keyword ) === '' ) {
        return new WP_Error( 'empty_keyword', 'Từ khóa không được để trống cho chiến dịch keyword' );
    }

    // Price per view from settings
    $price_key = ( $task_type === 'keyword_search' ? 'keyword' : 'direct' ) . '_price_' . $traffic_type;
    $default_prices = array( '1step' => 1200, '2step' => 1500, 'nocode' => 1200 );
    $price_per_view = floatval( $data['price_per_view'] ?? traffictop_get_option( $price_key, $default_prices[ $traffic_type ] ?? 1200 ) );

    // User reward = price × reward_percent / 100
    $reward_pct = (int) traffictop_get_option( 'keyword_user_reward_percent', 80 );
    $user_reward = isset( $data['user_reward'] ) ? floatval( $data['user_reward'] ) : floor( $price_per_view * $reward_pct / 100 );

    $wpdb->insert( "{$p}keyword_campaigns", array(
        'customer_id'        => absint( $data['customer_id'] ?? 0 ),
        'title'              => sanitize_text_field( $data['title'] ?? $data['name'] ?? '' ),
        'keyword'            => $keyword,
        'target_url'         => esc_url_raw( $data['target_url'] ?? '' ),
        'target_title'       => sanitize_text_field( $data['target_title'] ?? '' ),
        'target_description' => sanitize_textarea_field( $data['target_description'] ?? '' ),
        'traffic_type'       => $traffic_type,
        'campaign_type'      => $task_type,
        'quantity'           => absint( $data['quantity'] ?? 0 ),
        'price_per_view'     => $price_per_view,
        'user_reward'        => $user_reward,
        'countdown_seconds'  => absint( $data['countdown_seconds'] ?? 30 ),
        'onsite_time'        => absint( $data['onsite_time'] ?? 70 ),
        'fixed_code'         => $traffic_type === 'nocode' ? sanitize_text_field( $data['fixed_code'] ?? '' ) : null,
        'daily_traffic'      => absint( $data['daily_traffic'] ?? 10 ),
        'status'             => 'pending', // Admin must approve
        'start_date'         => $data['start_date'] ?? null,
        'end_date'           => $data['end_date'] ?? null,
        'created_at'         => traffictop_current_time(),
    ));

    if ( ! $wpdb->insert_id ) return new WP_Error( 'db_error', 'Không thể tạo campaign' );
    $campaign_id = $wpdb->insert_id;

    // Create customer order (NO money deducted upfront)
    if ( ! empty( $data['customer_id'] ) ) {
        $wpdb->insert( "{$p}customer_orders", array(
            'customer_id'      => absint( $data['customer_id'] ),
            'customer_username' => sanitize_text_field( $data['customer_username'] ?? '' ),
            'task_type'        => $task_type,
            'title'            => sanitize_text_field( $data['title'] ?? $data['name'] ?? '' ),
            'task_url'         => esc_url_raw( $data['target_url'] ?? '' ),
            'quantity'         => absint( $data['quantity'] ?? 0 ),
            'price_per_task'   => $price_per_view,
            'daily_traffic'    => absint( $data['daily_traffic'] ?? 10 ),
            'status'           => 'active',
            'created_at'       => traffictop_current_time(),
        ));
        $order_id = $wpdb->insert_id;
        $wpdb->update( "{$p}keyword_campaigns", array( 'order_id' => $order_id ), array( 'id' => $campaign_id ) );
    }

    // Email admin
    traffictop_send_new_campaign_email( $campaign_id );

    return $campaign_id;
}

function traffictop_get_campaign( $id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}keyword_campaigns WHERE id = %d", $id ) );
}

function traffictop_update_campaign( $id, $data ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';

    // Validate keyword not empty for keyword_search campaigns
    if ( isset( $data['keyword'] ) && trim( $data['keyword'] ) === '' ) {
        $camp = $wpdb->get_row( $wpdb->prepare(
            "SELECT kc.campaign_type, co.task_type FROM {$p}keyword_campaigns kc
             LEFT JOIN {$p}customer_orders co ON co.id = kc.order_id WHERE kc.id=%d", $id ) );
        $task_type = $camp->campaign_type ?? $camp->task_type ?? 'keyword_search';
        if ( $task_type === 'keyword_search' ) {
            return new WP_Error( 'empty_keyword', 'Từ khóa không được để trống cho chiến dịch keyword' );
        }
    }

    $allowed = array(
        'title'=>'%s','keyword'=>'%s','target_url'=>'%s','traffic_type'=>'%s',
        'price_per_view'=>'%f','user_reward'=>'%f','quantity'=>'%d','daily_traffic'=>'%d',
        'onsite_time'=>'%d','countdown_seconds'=>'%d','fixed_code'=>'%s',
        'screenshot_desktop_url'=>'%s','screenshot_mobile_url'=>'%s','nocode_screenshot_url'=>'%s',
        'status'=>'%s','reject_reason'=>'%s','start_date'=>'%s','end_date'=>'%s',
    );

    $update = array(); $format = array();
    foreach ( $allowed as $f => $fmt ) {
        if ( isset( $data[$f] ) ) {
            if ( $f === 'traffic_type' && ! in_array( $data[$f], array('1step','2step','nocode') ) ) continue;
            $update[$f] = $data[$f];
            $format[] = $fmt;
        }
    }
    if ( empty( $update ) ) return false;

    $update['updated_at'] = traffictop_current_time();
    $format[] = '%s';

    return $wpdb->update( "{$p}keyword_campaigns", $update, array('id'=>$id), $format, array('%d') );
}

function traffictop_get_campaigns( $args = array() ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';

    $defaults = array( 'status'=>'','customer_id'=>0,'search'=>'','orderby'=>'created_at','order'=>'DESC','limit'=>20,'offset'=>0 );
    $args = wp_parse_args( $args, $defaults );

    $where = array('1=1'); $params = array();
    if ( $args['status'] ) { $where[] = 'status = %s'; $params[] = $args['status']; }
    if ( $args['customer_id'] ) { $where[] = 'customer_id = %d'; $params[] = $args['customer_id']; }
    if ( $args['search'] ) {
        $where[] = '(title LIKE %s OR keyword LIKE %s)';
        $s = '%' . $wpdb->esc_like($args['search']) . '%';
        $params[] = $s; $params[] = $s;
    }

    $ob_allowed = array('id','title','created_at','status','completed');
    $ob = in_array($args['orderby'], $ob_allowed) ? $args['orderby'] : 'created_at';
    $o = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

    $sql = "SELECT * FROM {$p}keyword_campaigns WHERE " . implode(' AND ', $where) . " ORDER BY {$ob} {$o} LIMIT %d OFFSET %d";
    $params[] = $args['limit']; $params[] = $args['offset'];

    return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
}
