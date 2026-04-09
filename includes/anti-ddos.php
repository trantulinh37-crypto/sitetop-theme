<?php
/**
 * LinkNgon V2 - DDoS Protection
 * 3-tier: Global (10/sec) → Burst (30/10sec) → Sustained (300/60sec)
 * Progressive blocking: duration doubles each violation (300s → 600s → ... → max 24h)
 * Blocked referrer cache: /cache/blocked-referrers.php
 * Table: ddos_blocks
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function linkngon_ddos_check() {
    $ip = linkngon_get_real_ip();

    // Skip for logged-in administrators
    if ( function_exists('current_user_can') && current_user_can('administrator') ) return;

    // Whitelist check
    $whitelist = array_filter( explode( "\n", linkngon_get_option( 'ddos_whitelist', '' ) ) );
    if ( in_array( trim($ip), $whitelist ) ) return;

    // Check if already blocked
    if ( linkngon_ddos_is_blocked( $ip ) ) {
        http_response_code( 403 );
        die( 'Access denied.' );
    }

    // Check blocked referrers (file cache for performance)
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ( $referer && linkngon_ddos_is_blocked_referrer( $referer ) ) {
        linkngon_ddos_record_violation( $ip, 'malicious_referrer' );
        http_response_code( 403 );
        die( 'Blocked referrer.' );
    }

    // 3-tier rate check (file-based — zero DB queries)
    $global_rate     = (int) linkngon_get_option( 'ddos_global_rate', 10 );      // per second
    $burst_limit     = (int) linkngon_get_option( 'ddos_burst_limit', 30 );      // per 10 sec
    $sustained_limit = (int) linkngon_get_option( 'ddos_sustained_limit', 300 ); // per 60 sec

    $counters = linkngon_ddos_file_increment( $ip );

    $violated = false;
    if ( $counters['1s'] >= $global_rate )         { $violated = true; linkngon_ddos_record_violation( $ip, 'global_rate' ); }
    elseif ( $counters['10s'] >= $burst_limit )    { $violated = true; linkngon_ddos_record_violation( $ip, 'burst' ); }
    elseif ( $counters['60s'] >= $sustained_limit ) { $violated = true; linkngon_ddos_record_violation( $ip, 'sustained' ); }

    if ( $violated ) {
        http_response_code( 429 );
        die( 'Too many requests.' );
    }
}

/**
 * File-based DDoS rate counter — replaces transients (zero DB load).
 * Stores counters in /cache/ddos/{ip_hash}.php as serialized array.
 * Each file ~200 bytes, auto-cleaned by linkngon_ddos_cleanup_files().
 */
function linkngon_ddos_file_increment( $ip ) {
    $dir = LINKNGON_DIR . '/cache/ddos/';
    if ( ! is_dir( $dir ) ) @mkdir( $dir, 0755, true );

    $hash = substr( md5( $ip ), 0, 12 );
    $file = $dir . $hash . '.php';
    $now  = microtime( true );

    $data = array( 'ts_1s' => $now, 'c_1s' => 0, 'ts_10s' => $now, 'c_10s' => 0, 'ts_60s' => $now, 'c_60s' => 0 );

    if ( file_exists( $file ) ) {
        $raw = @file_get_contents( $file );
        if ( $raw !== false ) {
            $saved = @unserialize( $raw );
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
function linkngon_ddos_cleanup_files() {
    $dir = LINKNGON_DIR . '/cache/ddos/';
    if ( ! is_dir( $dir ) ) return;
    $cutoff = time() - 120;
    foreach ( glob( $dir . '*.php' ) as $f ) {
        if ( filemtime( $f ) < $cutoff ) @unlink( $f );
    }
}

function linkngon_ddos_record_violation( $ip, $type ) {
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';
    $now = linkngon_current_time();
    $threshold = (int) linkngon_get_option( 'ddos_violation_threshold', 5 );
    $base_duration = (int) linkngon_get_option( 'ddos_block_duration', 300 );

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
            linkngon_ddos_block_ip( $ip, $duration );
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

function linkngon_ddos_block_ip( $ip, $duration = 300 ) {
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';
    $until = date( 'Y-m-d H:i:s', strtotime( linkngon_current_time() ) + $duration );
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}ddos_blocks SET blocked_until = %s, duration = %d, updated_at = %s WHERE ip_address = %s",
        $until, $duration, linkngon_current_time(), $ip
    ));
}

function linkngon_ddos_permanent_block( $ip ) {
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';
    $wpdb->query( $wpdb->prepare(
        "INSERT INTO {$p}ddos_blocks (ip_address, permanent, violation_count, created_at)
         VALUES (%s, 1, 999, %s)
         ON DUPLICATE KEY UPDATE permanent = 1, updated_at = %s",
        $ip, linkngon_current_time(), linkngon_current_time()
    ));
}

function linkngon_ddos_is_blocked( $ip ) {
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ddos_blocks WHERE ip_address = %s AND (permanent = 1 OR blocked_until > %s)",
        $ip, linkngon_current_time()
    )) > 0;
}

function linkngon_ddos_is_blocked_referrer( $referer ) {
    $host = parse_url( $referer, PHP_URL_HOST );
    if ( ! $host ) return false;

    // Hardcoded default
    $blocked = array( 'lu88.pro' );

    // Custom from options
    $custom = array_filter( array_map( 'trim', explode( "\n", linkngon_get_option( 'blocked_referrers', '' ) ) ) );
    $blocked = array_merge( $blocked, $custom );

    // File cache check
    $cache_file = LINKNGON_DIR . '/cache/blocked-referrers.php';
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
add_action('wp_ajax_linkngon_ddos_unblock_ip', 'linkngon_ajax_ddos_unblock_ip');
function linkngon_ajax_ddos_unblock_ip() {
    if ( ! current_user_can('administrator') ) wp_send_json_error('Forbidden');
    $ip = sanitize_text_field( $_POST['ip'] ?? '' );
    if ( empty($ip) ) wp_send_json_error('Missing IP');
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';
    $wpdb->delete( "{$p}ddos_blocks", array( 'ip_address' => $ip ) );
    wp_send_json_success( array( 'message' => 'Đã unblock IP: ' . $ip ) );
}

/**
 * Admin AJAX: Whitelist current admin IP
 */
add_action('wp_ajax_linkngon_ddos_whitelist_my_ip', 'linkngon_ajax_ddos_whitelist_my_ip');
function linkngon_ajax_ddos_whitelist_my_ip() {
    if ( ! current_user_can('administrator') ) wp_send_json_error('Forbidden');
    $ip = linkngon_get_real_ip();
    $whitelist = linkngon_get_option( 'ddos_whitelist', '' );
    $ips = array_filter( array_map( 'trim', explode( "\n", $whitelist ) ) );
    if ( ! in_array( $ip, $ips ) ) {
        $ips[] = $ip;
        linkngon_update_option( 'ddos_whitelist', implode( "\n", $ips ) );
    }
    // Also unblock from both ddos_blocks AND ip_reputation
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';
    $wpdb->delete( "{$p}ddos_blocks", array( 'ip_address' => $ip ) );
    $wpdb->update( "{$p}ip_reputation", array( 'blocked' => 0, 'permanent_block' => 0, 'blocked_until' => null ), array( 'ip_address' => $ip ) );
    wp_send_json_success( array( 'message' => 'Đã whitelist + unblock IP: ' . $ip, 'ip' => $ip ) );
}

/**
 * Admin AJAX: Reset all DDoS blocks
 */
add_action('wp_ajax_linkngon_ddos_reset_all', 'linkngon_ajax_ddos_reset_all');
function linkngon_ajax_ddos_reset_all() {
    if ( ! current_user_can('administrator') ) wp_send_json_error('Forbidden');
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';
    $count1 = (int) $wpdb->query( "DELETE FROM {$p}ddos_blocks WHERE permanent = 0" );
    $count2 = (int) $wpdb->query( "UPDATE {$p}ip_reputation SET blocked = 0, blocked_until = NULL WHERE permanent_block = 0 AND blocked = 1" );
    wp_send_json_success( array( 'message' => 'Đã xóa ' . $count1 . ' DDoS block + ' . $count2 . ' IP reputation block' ) );
}

function linkngon_ddos_regenerate_cache() {
    $blocked = array( 'lu88.pro' );
    $custom = array_filter( array_map( 'trim', explode( "\n", linkngon_get_option( 'blocked_referrers', '' ) ) ) );
    $blocked = array_merge( $blocked, $custom );

    $cache_dir = LINKNGON_DIR . '/cache/';
    if ( ! is_dir( $cache_dir ) ) @mkdir( $cache_dir, 0755, true );

    $content = "<?php\nreturn " . var_export( array_unique( $blocked ), true ) . ";\n";
    @file_put_contents( $cache_dir . 'blocked-referrers.php', $content );
}
