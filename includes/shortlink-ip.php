<?php
/**
 * SiteTop.net V2 - IP Detection & Rate Limiting
 * Mapped: CLAUDE.md Flow 9b, Section 4
 * 
 * Priority: Cloudflare → X-Forwarded-For → REMOTE_ADDR
 * Rate limits: 6 endpoint configs (transient-based)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get real IP - Cloudflare > X-Forwarded-For > REMOTE_ADDR
 * Flow 9b from CLAUDE.md
 */
function sitetop_get_real_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // 1. Check if from Cloudflare
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && sitetop_is_cloudflare_ip( $ip ) ) {
        $cf_ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        if ( filter_var( $cf_ip, FILTER_VALIDATE_IP ) ) {
            $ip = $cf_ip;
        }
    }
    // 2. Reverse proxy trust
    elseif ( sitetop_get_option( 'trust_reverse_proxy', false ) ) {
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $forwarded = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            $first = trim( $forwarded[0] );
            if ( filter_var( $first, FILTER_VALIDATE_IP ) ) $ip = $first;
        } elseif ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
            $real = trim( $_SERVER['HTTP_X_REAL_IP'] );
            if ( filter_var( $real, FILTER_VALIDATE_IP ) ) $ip = $real;
        }
    }

    $ip = trim( $ip );

    // IPv6: prefix 4 phần
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
        $parts = explode( ':', $ip );
        $ip = implode( ':', array_slice( $parts, 0, 4 ) ) . '::';
    }

    return sanitize_text_field( $ip );
}

// Alias for backward compatibility
function sitetop_get_user_ip() {
    return sitetop_get_real_ip();
}

/**
 * Check if IP is in Cloudflare CIDR ranges (15 blocks)
 */
function sitetop_is_cloudflare_ip( $ip ) {
    $cf_ranges = array(
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    );
    foreach ( $cf_ranges as $range ) {
        if ( sitetop_ip_in_cidr( $ip, $range ) ) return true;
    }
    return false;
}

function sitetop_ip_in_cidr( $ip, $cidr ) {
    list( $subnet, $bits ) = explode( '/', $cidr );
    $ip_long = ip2long( $ip );
    $subnet_long = ip2long( $subnet );
    if ( $ip_long === false || $subnet_long === false ) return false;
    $mask = -1 << ( 32 - (int) $bits );
    return ( $ip_long & $mask ) === ( $subnet_long & $mask );
}

/**
 * Validate IP - block known bad IPs
 * DNS resolvers, private ranges, datacenter ranges
 * Returns: true if valid, false if blocked
 */
function sitetop_validate_ip( $ip ) {
    // Invalid IP format
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) return false;

    // Whitelist bypass (after format check, before all other checks)
    if ( function_exists( 'sitetop_is_ip_whitelisted' ) && sitetop_is_ip_whitelisted( $ip ) ) {
        return true;
    }

    // DNS resolvers
    $blocked = array( '1.1.1.1', '1.0.0.1', '8.8.8.8', '8.8.4.4', '9.9.9.9', '127.0.0.1', '0.0.0.0' );
    if ( in_array( $ip, $blocked ) ) return false;

    // Private ranges
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
        return false;
    }

    // Datacenter check (if enabled)
    if ( sitetop_get_option( 'block_datacenter_ip', 0 ) ) {
        $rep = sitetop_get_ip_reputation( $ip );
        if ( $rep && $rep->is_hosting ) return false;
    }

    // Proxy/VPN check (if enabled, via ip_reputation table)
    if ( sitetop_get_option( 'block_proxy_ip', 1 ) || sitetop_get_option( 'block_vpn_ip', 1 ) ) {
        $rep = $rep ?? sitetop_get_ip_reputation( $ip );
        if ( $rep ) {
            if ( sitetop_get_option( 'block_proxy_ip', 1 ) && ! empty( $rep->is_proxy ) ) return false;
            if ( sitetop_get_option( 'block_vpn_ip', 1 ) && ! empty( $rep->is_vpn ) ) return false;
        }
    }

    return true;
}

/**
 * Rate limit check - file-based (zero DB queries)
 * Returns: {allowed, remaining, retry_after}
 */
function sitetop_rate_limit_check( $endpoint, $identifier = null ) {
    if ( ! $identifier ) $identifier = sitetop_get_real_ip();

    // Exact limits from CLAUDE.md Section 4
    $limits = array(
        'verify_code'      => array( 'max' => 10, 'window' => 60 ),
        'get_code'         => array( 'max' => 20, 'window' => 60 ),
        'shortlink_click'  => array( 'max' => 30, 'window' => 60 ),
        'widget_verify'    => array( 'max' => 30, 'window' => 60 ),
        'report_issue'     => array( 'max' => 5,  'window' => 300 ),
        'login'            => array( 'max' => 10, 'window' => 300 ),
        'forgot_password'  => array( 'max' => 5,  'window' => 300 ),
        'deposit'          => array( 'max' => 3,  'window' => 60 ),
        'shorten_url'      => array( 'max' => 20,  'window' => 3600 ),
        /* API gọi từ MÁY CHỦ của publisher: cả website chỉ có MỘT ip, nên đo theo ip
           là cả site dùng chung 20 link/giờ — web sinh link động hết quota trong vài
           giây. Rổ riêng này đo theo TỪNG USER (xem rest-api.php) và rộng hơn. */
        'shorten_url_api'  => array( 'max' => 300, 'window' => 3600 ),
        'create_campaign'  => array( 'max' => 15, 'window' => 3600 ),
        'default'          => array( 'max' => 60, 'window' => 60 ),
    );

    $limit = $limits[ $endpoint ] ?? $limits['default'];

    // File-based counter (no DB)
    $dir = SITETOP_DIR . '/cache/ratelimit/';
    if ( ! is_dir( $dir ) ) @mkdir( $dir, 0755, true );
    $hash = substr( md5( $endpoint . '_' . $identifier ), 0, 16 );
    $file = $dir . $hash . '.php';
    $now = time();
    $count = 0;

    if ( file_exists( $file ) ) {
        $raw = @file_get_contents( $file );
        if ( $raw !== false ) {
            $saved = @unserialize( $raw, array( 'allowed_classes' => false ) );
            if ( is_array( $saved ) && ($now - $saved['ts']) < $limit['window'] ) {
                $count = $saved['c'];
            }
        }
    }
    $count++;
    @file_put_contents( $file, serialize( array( 'ts' => $count === 1 ? $now : ($saved['ts'] ?? $now), 'c' => $count ) ), LOCK_EX );

    $allowed = $count <= $limit['max'];
    $remaining = max( 0, $limit['max'] - $count );

    return array(
        'allowed'    => $allowed,
        'remaining'  => $remaining,
        'retry_after' => $allowed ? 0 : $limit['window'],
        'reset_at'   => time() + $limit['window'],
    );
}

/**
 * Dọn file cache hết hạn — CÓ GIỚI HẠN, an toàn để gọi ngay trong request.
 * ------------------------------------------------------------------------
 * Vì sao cần: sitetop_ratelimit_cleanup_files() chỉ được gọi từ sitetop_5min_cron,
 * mà WP-Cron trên hệ thống này không chạy (22/08/2026: mọi sự kiện cron đứng im từ
 * 06/08 — 16 ngày). Hậu quả: thư mục cache/ratelimit phình tới hàng trăm nghìn file,
 * vừa tốn dung lượng vừa có nguy cơ cạn inode trên hosting cPanel.
 *
 * Hàm này KHÔNG thay thế cron mà là lưới an toàn: tự chạy rải rác trong request thật.
 * Có 3 lớp chặn để không bao giờ làm chậm người dùng:
 *   1. Chỉ 1/200 request mới xét tới (sitetop_maybe_gc_cache).
 *   2. Tem thời gian: tối đa 1 lượt dọn mỗi 5 phút cho cả site.
 *   3. Trần số file quét/xoá mỗi lượt — thư mục khổng lồ sẽ được dọn dần qua nhiều lượt.
 */
function sitetop_gc_cache_files( $force = false, $max_scan = 12000, $max_delete = 2000 ) {
    $stamp = SITETOP_DIR . '/cache/.gc-last';
    $now   = time();

    if ( ! $force ) {
        $last = (int) @file_get_contents( $stamp );
        if ( $last && ( $now - $last ) < 300 ) return 0;
    }
    // Ghi tem TRƯỚC khi quét: hai request vào cùng lúc thì chỉ một cái thật sự dọn.
    @file_put_contents( $stamp, $now, LOCK_EX );

    // widget.js.php giữ trạng thái chặn của NÓ ở thư mục tạm hệ thống, không nằm
    // trong cache/ của theme — và không có bất kỳ cơ chế dọn nào. Mỗi IP một file.
    // Cắt ở 1 giờ: lệnh chặn dài nhất là 5 phút nên file quá 1 giờ chắc chắn đã hết hiệu lực.
    $tmp  = rtrim( sys_get_temp_dir(), '/\\' ) . '/';
    $dirs = array(
        SITETOP_DIR . '/cache/ratelimit/' => HOUR_IN_SECONDS,
        SITETOP_DIR . '/cache/ddos/'      => HOUR_IN_SECONDS,
        $tmp . 'taskify_rate/'            => HOUR_IN_SECONDS,
        $tmp . 'taskify_spam_block/'      => HOUR_IN_SECONDS,
    );

    $deleted = 0;
    $scanned = 0;
    foreach ( $dirs as $dir => $max_age ) {
        if ( ! is_dir( $dir ) ) continue;
        $cutoff = $now - $max_age;
        $dh = @opendir( $dir );
        if ( ! $dh ) continue;
        while ( ( $entry = readdir( $dh ) ) !== false ) {
            if ( $entry === '' || $entry[0] === '.' ) continue;
            if ( ++$scanned > $max_scan || $deleted >= $max_delete ) break;
            $file = $dir . $entry;
            if ( @filemtime( $file ) < $cutoff ) {
                @unlink( $file );
                $deleted++;
            }
        }
        closedir( $dh );
    }
    return $deleted;
}

/**
 * TẠM NGỪNG 23/08/2026 — KHÔNG gọi bộ gom rác trong request người dùng.
 * Lý do: sau khi bật, /top.js trên production nhảy từ ~0,3s lên 2–6 giây và có
 * request timeout hẳn. Thư mục cache trên production lớn hơn local rất nhiều và
 * I/O của hosting chậm hơn nhiều, nên ngay cả bản có trần vẫn treo quá lâu.
 * Giữ lại hàm để chạy từ cron/nút Xoá cache trong admin — nơi chậm không sao.
 */
function sitetop_maybe_gc_cache() {
    return; // cố ý không làm gì
}

/**
 * Cleanup old rate limit files (>1 hour old). Called by 5-min cron.
 */
function sitetop_ratelimit_cleanup_files() {
    $dir = SITETOP_DIR . '/cache/ratelimit/';
    if ( ! is_dir( $dir ) ) return;
    $cutoff = time() - 3600;
    $dh = @opendir( $dir );
    if ( ! $dh ) return;
    while ( ( $entry = readdir( $dh ) ) !== false ) {
        if ( $entry[0] === '.' ) continue;
        $f = $dir . $entry;
        if ( @filemtime( $f ) < $cutoff ) @unlink( $f );
    }
    closedir( $dh );
}

/**
 * 13/07/2026 — IP TEST/ADMIN: bỏ qua các giới hạn theo IP để admin test camp (xoay-camp,
 * trần thưởng/ngày, chặn trùng camp, chặn đổi IP). Đúng khi: admin đang đăng nhập HOẶC IP nằm
 * trong option 'shortlink_test_whitelist_ips' (cách nhau bởi xuống dòng/dấu phẩy; hậu tố * để
 * khớp tiền tố — vd '2001:ee0:*' cho IPv6 /64 đổi đuôi).
 * LƯU Ý: lượt của IP whitelist vẫn TÍNH TIỀN như khách thật (charge customer + trả reward) —
 * chỉ đưa IP của admin/test vào đây.
 */
function sitetop_is_test_whitelisted( $ip = '' ) {
    if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) {
        return true;
    }
    if ( '' === $ip ) {
        $ip = sitetop_get_real_ip();
    }
    $raw = trim( (string) sitetop_get_option( 'shortlink_test_whitelist_ips', '' ) );
    if ( '' === $raw || '' === (string) $ip ) {
        return false;
    }
    foreach ( preg_split( '/[\s,]+/', $raw ) as $w ) {
        $w = trim( $w );
        if ( '' === $w ) { continue; }
        if ( '*' === substr( $w, -1 ) ) {
            $pfx = substr( $w, 0, -1 );
            if ( '' !== $pfx && 0 === stripos( $ip, $pfx ) ) { return true; }
        } elseif ( 0 === strcasecmp( $w, $ip ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Check IP daily limit for campaign
 */
function sitetop_check_ip_daily_limit( $campaign_id, $ip, $limit = null ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $today = date( 'Y-m-d', strtotime( sitetop_current_time() ) );
    if ( $limit === null ) $limit = (int) sitetop_get_option( 'shortlink_ip_limit_24h', 2 );

    $count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits
         WHERE campaign_id = %d AND ip_address = %s AND (step = 'verified' OR customer_paid = 1) AND DATE(created_at) = %s",
        $campaign_id, $ip, $today
    ));
    return $count < $limit;
}

function sitetop_ip_already_completed_campaign( $campaign_id, $ip ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $today = date( 'Y-m-d', strtotime( sitetop_current_time() ) );
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}shortlink_visits
         WHERE campaign_id = %d AND ip_address = %s AND (step = 'verified' OR customer_paid = 1) AND DATE(created_at) = %s",
        $campaign_id, $ip, $today
    )) > 0;
}

function sitetop_get_ip_reputation( $ip ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}ip_reputation WHERE ip_address = %s", $ip ) );
}

function sitetop_is_ip_blocked( $ip ) {
    // Skip for logged-in administrators
    if ( function_exists('current_user_can') && current_user_can('administrator') ) return false;

    // Skip for whitelisted IPs (DDoS whitelist)
    $whitelist = array_filter( array_map('trim', explode( "\n", sitetop_get_option( 'ddos_whitelist', '' ) ) ) );
    if ( in_array( $ip, $whitelist ) ) return false;

    // Skip for VPN/Proxy whitelisted IPs (overrides reputation blocks)
    if ( function_exists( 'sitetop_is_ip_whitelisted' ) && sitetop_is_ip_whitelisted( $ip ) ) return false;

    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

    // Check ip_reputation
    $blocked = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ip_reputation WHERE ip_address = %s AND (permanent_block = 1 OR (blocked = 1 AND blocked_until > %s))",
        $ip, sitetop_current_time()
    ));
    if ( $blocked > 0 ) return true;

    // Check ddos_blocks
    $ddos = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$p}ddos_blocks WHERE ip_address = %s AND (permanent = 1 OR blocked_until > %s)",
        $ip, sitetop_current_time()
    ));
    return $ddos > 0;
}
