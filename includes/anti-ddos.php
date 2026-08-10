<?php
/**
 * SiteTop.net V2 - DDoS Protection
 * 3-tier: Global (10/sec) → Burst (30/10sec) → Sustained (300/60sec)
 * Progressive blocking: duration doubles each violation (300s → 600s → ... → max 24h)
 * Blocked referrer cache: /cache/blocked-referrers.php
 * Table: ddos_blocks
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function sitetop_ddos_check() {
    $ip = sitetop_get_real_ip();

    // Skip for logged-in administrators
    if ( function_exists('current_user_can') && current_user_can('administrator') ) return;

    // Whitelist check
    $whitelist = array_filter( explode( "\n", sitetop_get_option( 'ddos_whitelist', '' ) ) );
    if ( in_array( trim($ip), $whitelist ) ) return;

    // Check if already blocked
    if ( sitetop_ddos_is_blocked( $ip ) ) {
        http_response_code( 403 );
        die( 'Access denied.' );
    }

    // Check blocked referrers (file cache for performance)
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ( $referer && sitetop_ddos_is_blocked_referrer( $referer ) ) {
        sitetop_ddos_record_violation( $ip, 'malicious_referrer' );
        http_response_code( 403 );
        die( 'Blocked referrer.' );
    }

    // 3-tier rate check (file-based — zero DB queries)
    $global_rate     = (int) sitetop_get_option( 'ddos_global_rate', 10 );      // per second
    $burst_limit     = (int) sitetop_get_option( 'ddos_burst_limit', 30 );      // per 10 sec
    $sustained_limit = (int) sitetop_get_option( 'ddos_sustained_limit', 300 ); // per 60 sec

    $counters = sitetop_ddos_file_increment( $ip );

    $violated = false;
    if ( $counters['1s'] >= $global_rate )         { $violated = true; sitetop_ddos_record_violation( $ip, 'global_rate' ); }
    elseif ( $counters['10s'] >= $burst_limit )    { $violated = true; sitetop_ddos_record_violation( $ip, 'burst' ); }
    elseif ( $counters['60s'] >= $sustained_limit ) { $violated = true; sitetop_ddos_record_violation( $ip, 'sustained' ); }

    if ( $violated ) {
        http_response_code( 429 );
        die( 'Too many requests.' );
    }
}

/**
 * File-based DDoS rate counter — replaces transients (zero DB load).
 * Stores counters in /cache/ddos/{ip_hash}.php as serialized array.
 * Each file ~200 bytes, auto-cleaned by sitetop_ddos_cleanup_files().
 */
function sitetop_ddos_file_increment( $ip ) {
    $dir = SITETOP_DIR . '/cache/ddos/';
    if ( ! is_dir( $dir ) ) @mkdir( $dir, 0755, true );

    $hash = substr( md5( $ip ), 0, 12 );
    $file = $dir . $hash . '.php';
    $now  = microtime( true );

    $data = array( 'ts_1s' => $now, 'c_1s' => 0, 'ts_10s' => $now, 'c_10s' => 0, 'ts_60s' => $now, 'c_60s' => 0 );

    if ( file_exists( $file ) ) {
        $raw = @file_get_contents( $file );
        if ( $raw !== false ) {
            $saved = @unserialize( $raw, array( 'allowed_classes' => false ) );
            if ( is_array( $saved ) ) $data = $saved;
        }
    }

    // Reset expired windows, increment active ones
    if ( $now - $data['ts_1s'] > 1 )  { $data['ts_1s'] = $now; $data['c_1s'] = 0; }
    if ( $now - $data['ts_10s'] > 10 ) { $data['ts_10s'] = $now; $data['c_10s'] = 0; }
    if ( $now - $data['ts_60s'] > 60 ) { $data['ts_60s'] = $now; $data['c_60s'] = 0; }

    $data['c_1s']++;
    $data['c_10s']++;
    $data['c_60s']++;

    @file_put_contents( $file, serialize( $data ), LOCK_EX );

    return array( '1s' => $data['c_1s'], '10s' => $data['c_10s'], '60s' => $data['c_60s'] );
}

/**
 * Cleanup old DDoS counter files (>2 min old). Called by 5-min cron.
 */
function sitetop_ddos_cleanup_files() {
    $dir = SITETOP_DIR . '/cache/ddos/';
    if ( ! is_dir( $dir ) ) return;
    $cutoff = time() - 120;
    $dh = @opendir( $dir );
    if ( ! $dh ) return;
    while ( ( $entry = readdir( $dh ) ) !== false ) {
        if ( $entry[0] === '.' ) continue;
        $f = $dir . $entry;
        if ( @filemtime( $f ) < $cutoff ) @unlink( $f );
    }
    closedir( $dh );
}

function sitetop_ddos_record_violation( $ip, $type ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $now = sitetop_current_time();
    $threshold = (int) sitetop_get_option( 'ddos_violation_threshold', 5 );
    $base_duration = (int) sitetop_get_option( 'ddos_block_duration', 300 );

    // Upsert violation
    $existing = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$p}ddos_blocks WHERE ip_address = %s", $ip
    ));

    if ( $existing ) {
        $new_count = $existing->violation_count + 1;
        $types = trim( $existing->violation_types . ',' . $type, ',' );
        $wpdb->update( "{$p}ddos_blocks", array(
            'violation_count' => $new_count,
            'violation_types' => $types,
            'updated_at'      => $now,
        ), array( 'ip_address' => $ip ) );

        // Progressive blocking: duration doubles each time
        if ( $new_count >= $threshold ) {
            $duration = min( 86400, $base_duration * pow( 2, $new_count - $threshold ) ); // max 24h
            sitetop_ddos_block_ip( $ip, $duration );
        }
    } else {
        $wpdb->insert( "{$p}ddos_blocks", array(
            'ip_address'      => $ip,
            'violation_count' => 1,
            'violation_types' => $type,
            'duration'        => $base_duration,
            'created_at'      => $now,
        ));
    }
}

function sitetop_ddos_block_ip( $ip, $duration = 300 ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $until = date( 'Y-m-d H:i:s', strtotime( sitetop_current_time() ) + $duration );
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}ddos_blocks SET blocked_until = %s, duration = %d, updated_at = %s WHERE ip_address = %s",
        $until, $duration, sitetop_current_time(), $ip
    ));
}

function sitetop_ddos_permanent_block( $ip ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $wpdb->query( $wpdb->prepare(
        "INSERT INTO {$p}ddos_blocks (ip_address, permanent, violation_count, created_at)
         VALUES (%s, 1, 999, %s)
         ON DUPLICATE KEY UPDATE permanent = 1, updated_at = %s",
        $ip, sitetop_current_time(), sitetop_current_time()
    ));
}

function sitetop_ddos_is_blocked( $ip ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ddos_blocks WHERE ip_address = %s AND (permanent = 1 OR blocked_until > %s)",
        $ip, sitetop_current_time()
    )) > 0;
}

function sitetop_ddos_is_blocked_referrer( $referer ) {
    $host = parse_url( $referer, PHP_URL_HOST );
    if ( ! $host ) return false;

    // Hardcoded default
    $blocked = array( 'lu88.pro' );

    // Custom from options
    $custom = array_filter( array_map( 'trim', explode( "\n", sitetop_get_option( 'blocked_referrers', '' ) ) ) );
    $blocked = array_merge( $blocked, $custom );

    // File cache check
    $cache_file = SITETOP_DIR . '/cache/blocked-referrers.php';
    if ( file_exists( $cache_file ) ) {
        $cached = @include $cache_file;
        if ( is_array( $cached ) ) $blocked = array_merge( $blocked, $cached );
    }

    foreach ( $blocked as $bad ) {
        if ( $bad && stripos( $host, trim( $bad ) ) !== false ) return true;
    }
    return false;
}

/**
 * Regenerate blocked referrer cache file
 */
/**
 * Admin AJAX: Unblock IP
 */
add_action('wp_ajax_sitetop_ddos_unblock_ip', 'sitetop_ajax_ddos_unblock_ip');
function sitetop_ajax_ddos_unblock_ip() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    if ( ! current_user_can('administrator') ) wp_send_json_error('Forbidden');
    $ip = sanitize_text_field( $_POST['ip'] ?? '' );
    if ( empty($ip) ) wp_send_json_error('Missing IP');
    // Gỡ ở CẢ BỐN nơi. Trước đây chỉ xoá bảng ddos_blocks nên file block còn nguyên
    // (hạn 2099) → user vẫn bị 403 dù admin đã bấm gỡ.
    sitetop_ddos_unblock_ident( $ip );
    wp_send_json_success( array( 'message' => 'Đã unblock IP: ' . $ip ) );
}

/**
 * Admin AJAX: Whitelist current admin IP
 */
add_action('wp_ajax_sitetop_ddos_whitelist_my_ip', 'sitetop_ajax_ddos_whitelist_my_ip');
function sitetop_ajax_ddos_whitelist_my_ip() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    if ( ! current_user_can('administrator') ) wp_send_json_error('Forbidden');
    $ip = sitetop_get_real_ip();
    $whitelist = sitetop_get_option( 'ddos_whitelist', '' );
    $ips = array_filter( array_map( 'trim', explode( "\n", $whitelist ) ) );
    if ( ! in_array( $ip, $ips ) ) {
        $ips[] = $ip;
        sitetop_update_option( 'ddos_whitelist', implode( "\n", $ips ) );
    }
    // Gỡ đủ 4 nơi — thiếu file block thì whitelist cũng vô ích với các entry point
    // dùng file cache (widget.js.php, page-unlock...).
    sitetop_ddos_unblock_ident( $ip );
    wp_send_json_success( array( 'message' => 'Đã whitelist + unblock IP: ' . $ip, 'ip' => $ip ) );
}

/**
 * Admin AJAX: Reset all DDoS blocks
 */
add_action('wp_ajax_sitetop_ddos_reset_all', 'sitetop_ajax_ddos_reset_all');
function sitetop_ajax_ddos_reset_all() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    if ( ! current_user_can('administrator') ) wp_send_json_error('Forbidden');
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

    // Block permanent chỉ gỡ khi admin yêu cầu rõ ràng — trước đây KHÔNG có đường nào
    // gỡ được nó, nên user bị chặn nhầm là kẹt vĩnh viễn.
    $inc_perm = ! empty( $_POST['include_permanent'] );
    $where    = $inc_perm ? '1=1' : 'permanent = 0';

    // Lấy danh sách ident TRƯỚC khi xoá, để còn dọn file block tương ứng.
    $idents = $wpdb->get_col( "SELECT ip_address FROM {$p}ddos_blocks WHERE $where" );

    $count1 = (int) $wpdb->query( "DELETE FROM {$p}ddos_blocks WHERE $where" );
    $count2 = $inc_perm
        ? (int) $wpdb->query( "UPDATE {$p}ip_reputation SET blocked = 0, permanent_block = 0, blocked_until = NULL WHERE blocked = 1" )
        : (int) $wpdb->query( "UPDATE {$p}ip_reputation SET blocked = 0, blocked_until = NULL WHERE permanent_block = 0 AND blocked = 1" );

    foreach ( $idents as $ident ) sitetop_ddos_file_clear_block( $ident );
    sitetop_ddos_permanent_blocks_sync();

    wp_send_json_success( array( 'message' => 'Đã xóa ' . $count1 . ' DDoS block + ' . $count2
        . ' IP reputation block' . ( $inc_perm ? ' (gồm cả permanent)' : '' ) ) );
}

function sitetop_ddos_regenerate_cache() {
    $blocked = array( 'lu88.pro' );
    $custom = array_filter( array_map( 'trim', explode( "\n", sitetop_get_option( 'blocked_referrers', '' ) ) ) );
    $blocked = array_merge( $blocked, $custom );

    $cache_dir = SITETOP_DIR . '/cache/';
    if ( ! is_dir( $cache_dir ) ) @mkdir( $cache_dir, 0755, true );

    $content = "<?php\nreturn " . var_export( array_unique( $blocked ), true ) . ";\n";
    @file_put_contents( $cache_dir . 'blocked-referrers.php', $content );
}

/* ============================================================
   4-LAYER ANTI-DDOS — supplements 3-tier sliding window above
   Layer 1 Burst (permanent) → Layer 2 Hourly → Layer 3 Daily → Layer 4 Range
   IPv4 = full IP, IPv6 = /64 prefix (group botnet trong cùng /64)
   ============================================================ */

/**
 * Main 4-layer check — gọi từ admin-ajax hook hoặc các entry point.
 * @param bool $count_in_limits  False cho cheap actions (heartbeat polling)
 *                               → chỉ check existing block, không tích counter.
 */
function sitetop_ddos_4layer_check( $count_in_limits = true ) {
    $ip = sitetop_get_real_ip();
    if ( empty( $ip ) ) return;
    if ( function_exists( 'current_user_can' ) && current_user_can( 'administrator' ) ) return;
    $wl = array_filter( array_map( 'trim', explode( "\n", sitetop_get_option( 'ddos_whitelist', '' ) ) ) );
    if ( in_array( $ip, $wl, true ) ) return;

    // Check existing block (DB + file cache cho non-WP entry)
    if ( sitetop_ddos_is_blocked( $ip ) || sitetop_ddos_file_is_blocked( $ip ) ) {
        http_response_code( 403 );
        die( 'Access denied.' );
    }

    if ( $count_in_limits ) {
        if ( sitetop_ddos_check_burst_permanent( $ip ) ) { http_response_code( 403 ); die( 'Burst limit exceeded.' ); }
        if ( sitetop_ddos_check_range_hourly( $ip ) )    { http_response_code( 403 ); die( 'Range limit exceeded.' ); }
        if ( sitetop_ddos_check_hourly_limit( $ip ) )    { http_response_code( 429 ); die( 'Hourly limit exceeded.' ); }
        if ( sitetop_ddos_check_daily_limit( $ip ) )     { http_response_code( 429 ); die( 'Daily limit exceeded.' ); }
    }
}

/* IP identifier — IPv4 full, IPv6 /64 prefix (catch botnet trong cùng /64) */
function sitetop_ddos_ip_identifier( $ip ) {
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
        $p = explode( ':', $ip );
        if ( count( $p ) >= 4 ) return $p[0].':'.$p[1].':'.$p[2].':'.$p[3].'::/64';
    }
    return $ip;
}

/* Range identifier — IPv4 /24, IPv6 /48 (catch botnet rộng hơn) */
function sitetop_ddos_range_identifier( $ip ) {
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
        $p = explode( ':', $ip );
        if ( count( $p ) >= 3 ) return $p[0].':'.$p[1].':'.$p[2].'::/48';
    }
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
        $o = explode( '.', $ip );
        if ( count( $o ) === 4 ) return $o[0].'.'.$o[1].'.'.$o[2].'.0/24';
    }
    return null;
}

/* Layer 1: Burst sliding window → PERMANENT block (khác Layer 1 cũ chỉ temp) */
function sitetop_ddos_check_burst_permanent( $ip ) {
    if ( ! (int) sitetop_get_option( 'ddos_burst_enabled', 1 ) ) return false;
    if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) return false;
    $threshold = (int) sitetop_get_option( 'ddos_burst_perm_threshold', 60 );
    $window    = (int) sitetop_get_option( 'ddos_burst_perm_window', 60 );
    if ( $threshold <= 0 || $window <= 0 ) return false;

    $ident = sitetop_ddos_ip_identifier( $ip );
    $hash  = md5( $ident );
    $dir = sys_get_temp_dir() . '/sitetop_burst_track/';
    if ( ! is_dir( $dir ) ) @mkdir( $dir, 0755, true );
    $file = $dir . $hash . '.dat';
    $now = time();

    $fp = @fopen( $file, 'c+' );
    if ( ! $fp ) return false;
    @flock( $fp, LOCK_EX );
    $raw = trim( (string) fread( $fp, 64 ) );
    $first_ts = $now; $count = 1;
    if ( $raw && strpos( $raw, ':' ) !== false ) {
        list( $f, $c ) = explode( ':', $raw, 2 );
        $f = (int) $f; $c = (int) $c;
        if ( $now - $f <= $window ) { $first_ts = $f; $count = $c + 1; }
    }
    rewind( $fp ); ftruncate( $fp, 0 );
    fwrite( $fp, $first_ts . ':' . $count );
    @flock( $fp, LOCK_UN ); fclose( $fp );

    if ( $count > $threshold ) {
        sitetop_ddos_permanent_block_ident( $ident, "burst_{$threshold}_in_{$window}s", $count );
        return true;
    }
    return false;
}

/* Layer 2: Hourly per-IP → permanent */
function sitetop_ddos_check_hourly_limit( $ip ) {
    if ( ! (int) sitetop_get_option( 'ddos_hourly_enabled', 1 ) ) return false;
    if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) return false;
    $limit = (int) sitetop_get_option( 'ddos_hourly_limit', 500 );
    if ( $limit <= 0 ) return false;
    return sitetop_ddos_window_counter( sitetop_ddos_ip_identifier( $ip ), 'hourly', date('YmdH'), $limit );
}

/* Layer 3: Daily per-IP → permanent */
function sitetop_ddos_check_daily_limit( $ip ) {
    if ( ! (int) sitetop_get_option( 'ddos_daily_enabled', 1 ) ) return false;
    if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) return false;
    $limit = (int) sitetop_get_option( 'ddos_daily_limit', 2000 );
    if ( $limit <= 0 ) return false;
    return sitetop_ddos_window_counter( sitetop_ddos_ip_identifier( $ip ), 'daily', date('Ymd'), $limit );
}

/* Layer 4: Range hourly /24 IPv4 + /48 IPv6 → permanent */
function sitetop_ddos_check_range_hourly( $ip ) {
    if ( ! (int) sitetop_get_option( 'ddos_range_hourly_enabled', 1 ) ) return false;
    if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) return false;
    $limit = (int) sitetop_get_option( 'ddos_range_hourly_limit', 1000 );
    if ( $limit <= 0 ) return false;
    $range = sitetop_ddos_range_identifier( $ip );
    if ( ! $range ) return false;
    return sitetop_ddos_window_counter( $range, 'range', date('YmdH'), $limit );
}

/* Shared bucket counter — atomic flock */
function sitetop_ddos_window_counter( $ident, $type, $bucket, $limit ) {
    $hash = md5( $ident );
    $dir = sys_get_temp_dir() . '/sitetop_' . $type . '_count/';
    if ( ! is_dir( $dir ) ) @mkdir( $dir, 0755, true );
    $file = $dir . $hash . '_' . $bucket . '.dat';
    $fp = @fopen( $file, 'c+' );
    if ( ! $fp ) return false;
    @flock( $fp, LOCK_EX );
    $count = (int) trim( (string) fread( $fp, 16 ) ) + 1;
    rewind( $fp ); ftruncate( $fp, 0 );
    fwrite( $fp, (string) $count );
    @flock( $fp, LOCK_UN ); fclose( $fp );
    if ( $count > $limit ) {
        // Chặn TẠM đúng bằng cửa sổ đếm, KHÔNG chặn vĩnh viễn.
        //
        // Trước đây cả 3 lớp hourly/daily/range đều gọi thẳng permanent_block_ident:
        // vượt ngưỡng theo GIỜ một lần là cấm VĨNH VIỄN. Riêng lớp range còn cấm cả
        // dải /24 (256 địa chỉ) hoặc /48 IPv6 — mạng di động Việt Nam dùng CGNAT nên
        // hàng nghìn người thật chung một dải, một người vượt ngưỡng là cả dải chết.
        // Phạt vĩnh viễn cho một lần vượt ngưỡng tạm thời là quá tay, và trang báo lỗi
        // vẫn ghi "tự hết sau 24 giờ" — tức là thông báo cho user một điều KHÔNG đúng.
        //
        // Lớp burst (30 request/10 giây) giữ nguyên vĩnh viễn: đó mới là dấu hiệu tấn
        // công thật, không phải người dùng bình thường.
        $secs = ( $type === 'daily' ) ? DAY_IN_SECONDS : HOUR_IN_SECONDS;
        sitetop_ddos_temp_block_ident( $ident, $type . '_limit', $count, $secs );
        return true;
    }
    return false;
}

/* Chặn TẠM — hết hạn là tự mở, không cần ai gỡ tay.
   Khác permanent_block_ident ở 3 điểm: permanent=0, blocked_until là mốc thật, và file
   block ghi đúng mốc đó nên sitetop_ddos_file_is_blocked() tự unlink khi hết hạn. */
function sitetop_ddos_temp_block_ident( $ident, $reason = 'auto', $trigger_count = 0, $seconds = 3600 ) {
    global $wpdb;
    $tbl = $wpdb->prefix . 'sitetop_ddos_blocks';
    $now = sitetop_current_time();
    $until_ts = strtotime( $now ) + max( 60, (int) $seconds );
    $until    = date( 'Y-m-d H:i:s', $until_ts );

    $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, permanent FROM $tbl WHERE ip_address = %s", $ident ) );
    if ( $existing ) {
        // Đang bị chặn vĩnh viễn (do burst hoặc admin đặt tay) → KHÔNG hạ xuống tạm.
        if ( ! empty( $existing->permanent ) ) return;
        $wpdb->update( $tbl, array(
            'blocked_until' => $until, 'duration' => (int) $seconds, 'permanent' => 0,
            'violation_count' => $trigger_count ?: 1, 'violation_types' => $reason,
            'updated_at' => $now,
        ), array( 'id' => $existing->id ) );
    } else {
        $wpdb->insert( $tbl, array(
            'ip_address' => $ident, 'permanent' => 0, 'blocked_until' => $until,
            'violation_count' => $trigger_count ?: 1, 'violation_types' => $reason,
            'duration' => (int) $seconds, 'created_at' => $now,
        ) );
    }
    sitetop_ddos_file_set_block( $ident, $until_ts );
    error_log( "SITETOP DDOS TEMP BLOCK: $ident reason=$reason count=$trigger_count secs=$seconds" );
}

/* Permanent block — supports /64, /48, /24 prefixes */
function sitetop_ddos_permanent_block_ident( $ident, $reason = 'auto', $trigger_count = 0 ) {
    global $wpdb;
    $tbl = $wpdb->prefix . 'sitetop_ddos_blocks';
    $now = sitetop_current_time();
    $far = '2099-12-31 23:59:59';
    $far_ts = 4070908799;
    $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $tbl WHERE ip_address = %s", $ident ) );
    if ( $existing ) {
        $wpdb->update( $tbl, array(
            'permanent' => 1, 'blocked_until' => $far, 'duration' => 0,
            'violation_count' => $trigger_count ?: 1, 'violation_types' => $reason,
            'updated_at' => $now,
        ), array( 'id' => $existing->id ) );
    } else {
        $wpdb->insert( $tbl, array(
            'ip_address' => $ident, 'permanent' => 1, 'blocked_until' => $far,
            'violation_count' => $trigger_count ?: 1, 'violation_types' => $reason,
            'duration' => 0, 'created_at' => $now,
        ) );
    }
    sitetop_ddos_file_set_block( $ident, $far_ts );
    sitetop_ddos_permanent_blocks_sync();
    error_log( "SITETOP DDOS PERMANENT BLOCK: $ident reason=$reason count=$trigger_count" );
}

/* File-based block cho non-WP entry points (widget.js.php, page-unlock, ...) */
function sitetop_ddos_file_set_block( $ident, $until_ts ) {
    $dir = sys_get_temp_dir() . '/sitetop_spam_block/';
    if ( ! is_dir( $dir ) ) @mkdir( $dir, 0755, true );
    if ( strpos( $ident, '::/64' ) !== false ) {
        $pfx = str_replace( '::/64', '', $ident );
        @file_put_contents( $dir . 'prefix_' . md5( $pfx ) . '.dat', $until_ts );
    } else {
        @file_put_contents( $dir . 'ip_' . md5( $ident ) . '.dat', $until_ts );
    }
}

/* Gỡ file block. BẮT BUỘC gọi kèm mỗi lần xoá bản ghi trong ddos_blocks: block ghi ra
   HAI nơi (DB + file), mà sitetop_ddos_4layer_check kiểm tra bằng phép HOẶC. Xoá mỗi DB
   thì file còn lại vẫn chặn — và file permanent hết hạn năm 2099 nên không bao giờ tự
   hết. Đây chính là lỗi "đã gỡ chặn mà vẫn bị chặn".
   Xoá mọi biến thể tên file mà file_set_block có thể đã ghi. */
function sitetop_ddos_file_clear_block( $ident ) {
    $dir = sys_get_temp_dir() . '/sitetop_spam_block/';
    if ( ! is_dir( $dir ) ) return;
    $targets = array( $dir . 'ip_' . md5( $ident ) . '.dat' );
    if ( strpos( $ident, '::/64' ) !== false ) {
        $targets[] = $dir . 'prefix_' . md5( str_replace( '::/64', '', $ident ) ) . '.dat';
    }
    // IP thuần dạng IPv6: file_is_blocked còn dò thêm file prefix theo 4 nhóm đầu
    if ( filter_var( $ident, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
        $p = explode( ':', $ident );
        if ( count( $p ) >= 4 ) {
            $targets[] = $dir . 'prefix_' . md5( $p[0].':'.$p[1].':'.$p[2].':'.$p[3] ) . '.dat';
        }
    }
    foreach ( array_unique( $targets ) as $f ) @unlink( $f );
}

/* Gỡ chặn hoàn chỉnh một IP/dải: DB + ip_reputation + file + cache đồng bộ.
   Dùng chung cho mọi đường gỡ chặn để không bao giờ sót nơi nào. */
function sitetop_ddos_unblock_ident( $ident ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $wpdb->delete( "{$p}ddos_blocks", array( 'ip_address' => $ident ) );
    $wpdb->update( "{$p}ip_reputation",
        array( 'blocked' => 0, 'permanent_block' => 0, 'blocked_until' => null ),
        array( 'ip_address' => $ident ) );
    sitetop_ddos_file_clear_block( $ident );
    sitetop_ddos_permanent_blocks_sync();
}

function sitetop_ddos_file_is_blocked( $ip ) {
    $dir = sys_get_temp_dir() . '/sitetop_spam_block/';
    $files = array( $dir . 'ip_' . md5( $ip ) . '.dat' );
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
        $p = explode( ':', $ip );
        if ( count( $p ) >= 4 ) $files[] = $dir . 'prefix_' . md5( $p[0].':'.$p[1].':'.$p[2].':'.$p[3] ) . '.dat';
    }
    $now = time();
    foreach ( $files as $f ) {
        if ( ! file_exists( $f ) ) continue;
        $until = (int) @file_get_contents( $f );
        if ( $until > $now ) return true;
        @unlink( $f );
    }
    return false;
}

/* Sync permanent blocks → cache file cho standalone entry points */
function sitetop_ddos_permanent_blocks_sync() {
    global $wpdb;
    $tbl = $wpdb->prefix . 'sitetop_ddos_blocks';
    $blocks = $wpdb->get_col( "SELECT ip_address FROM $tbl WHERE permanent = 1" );
    $cache = SITETOP_DIR . '/cache/ddos-permanent.php';
    $dir = dirname( $cache );
    if ( ! is_dir( $dir ) ) @mkdir( $dir, 0755, true );
    $content = "<?php return " . var_export( array_values( $blocks ), true ) . ";\n";
    @file_put_contents( $cache, $content );
}

/* Cleanup cron — daily clean expired buckets */
function sitetop_ddos_4layer_cleanup() {
    $today = date( 'Ymd' );
    $hour  = date( 'YmdH' );
    foreach ( array( 'daily', 'hourly', 'range' ) as $type ) {
        $dir = sys_get_temp_dir() . '/sitetop_' . $type . '_count/';
        if ( ! is_dir( $dir ) ) continue;
        $dh = @opendir( $dir );
        if ( ! $dh ) continue;
        while ( ( $e = readdir( $dh ) ) !== false ) {
            if ( $e[0] === '.' ) continue;
            $skip = ( $type === 'daily' ) ? $today : $hour;
            $re   = ( $type === 'daily' ) ? '/_(\d{8})\.dat$/' : '/_(\d{10})\.dat$/';
            if ( preg_match( $re, $e, $m ) && $m[1] !== $skip ) @unlink( $dir . $e );
        }
        closedir( $dh );
    }
    // Dọn file block mồ côi: file còn nhưng bản ghi DB đã bị xoá bằng đường khác
    // (sửa tay, xoá DB...). Không dọn thì file hạn 2099 chặn user vĩnh viễn.
    $sb = sys_get_temp_dir() . '/sitetop_spam_block/';
    if ( is_dir( $sb ) ) {
        global $wpdb;
        $tbl  = $wpdb->prefix . 'sitetop_ddos_blocks';
        $live = array();
        foreach ( (array) $wpdb->get_col( "SELECT ip_address FROM $tbl" ) as $ident ) {
            $live[ 'ip_' . md5( $ident ) . '.dat' ] = 1;
            if ( strpos( $ident, '::/64' ) !== false ) {
                $live[ 'prefix_' . md5( str_replace( '::/64', '', $ident ) ) . '.dat' ] = 1;
            }
        }
        $dh = @opendir( $sb );
        if ( $dh ) {
            while ( ( $e = readdir( $dh ) ) !== false ) {
                if ( $e[0] === '.' ) continue;
                if ( isset( $live[ $e ] ) ) continue;   // còn bản ghi DB → block thật, giữ
                @unlink( $sb . $e );                    // mồ côi → xoá, dù còn hạn hay không
            }
            closedir( $dh );
        }
    }

    $bd = sys_get_temp_dir() . '/sitetop_burst_track/';
    if ( is_dir( $bd ) ) {
        $cutoff = time() - 86400;
        $dh = @opendir( $bd );
        if ( $dh ) {
            while ( ( $e = readdir( $dh ) ) !== false ) {
                if ( $e[0] === '.' ) continue;
                if ( @filemtime( $bd . $e ) < $cutoff ) @unlink( $bd . $e );
            }
            closedir( $dh );
        }
    }
}
add_action( 'sitetop_ddos_4layer_cleanup_cron', 'sitetop_ddos_4layer_cleanup' );
if ( ! wp_next_scheduled( 'sitetop_ddos_4layer_cleanup_cron' ) ) {
    wp_schedule_event( time(), 'daily', 'sitetop_ddos_4layer_cleanup_cron' );
}

/* Sync limit thresholds → cache file cho standalone entry points đọc nhanh */
function sitetop_ddos_sync_limit_cache() {
    $dir = SITETOP_DIR . '/cache/';
    if ( ! is_dir( $dir ) ) @mkdir( $dir, 0755, true );
    $pairs = array(
        'ddos-burst-perm-threshold.txt' => (int) sitetop_get_option( 'ddos_burst_perm_threshold', 60 ),
        'ddos-burst-perm-window.txt'    => (int) sitetop_get_option( 'ddos_burst_perm_window', 60 ),
    );
    foreach ( $pairs as $name => $v ) {
        $f = $dir . $name;
        if ( ! file_exists( $f ) || (int) @file_get_contents( $f ) !== $v ) {
            @file_put_contents( $f, (string) $v );
        }
    }
}
add_action( 'init', 'sitetop_ddos_sync_limit_cache' );
foreach ( array( 'burst_perm_threshold', 'burst_perm_window' ) as $k ) {
    add_action( 'update_option_sitetop_ddos_' . $k, 'sitetop_ddos_sync_limit_cache' );
    add_action( 'add_option_sitetop_ddos_' . $k,    'sitetop_ddos_sync_limit_cache' );
}

/* Admin AJAX: list permanent blocks */
add_action( 'wp_ajax_sitetop_ddos_permanent_list', function() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'administrator' ) ) wp_send_json_error( 'Forbidden' );
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT id, ip_address, violation_count, violation_types, blocked_until, updated_at, created_at
         FROM " . $wpdb->prefix . "sitetop_ddos_blocks
         WHERE permanent = 1 ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 500"
    );
    wp_send_json_success( array( 'blocks' => $rows ) );
} );

/* Admin AJAX: manually add permanent block */
add_action( 'wp_ajax_sitetop_ddos_permanent_add', function() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'administrator' ) ) wp_send_json_error( 'Forbidden' );
    $ip = trim( sanitize_text_field( $_POST['ip'] ?? '' ) );
    if ( ! $ip ) wp_send_json_error( 'Missing IP/prefix' );
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) && ! preg_match( '#^[0-9a-fA-F:.]+/(24|48|64)$#', $ip ) ) {
        wp_send_json_error( 'Format không hợp lệ (vd: 1.2.3.4 hoặc 2402:800::/48)' );
    }
    sitetop_ddos_permanent_block_ident( $ip, 'manual_admin', 1 );
    wp_send_json_success( array( 'message' => 'Đã permanent block: ' . $ip ) );
} );
