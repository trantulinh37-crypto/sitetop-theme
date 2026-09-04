<?php
/**
 * SiteTop.net V2 - Core Shortlink Functions
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

function sitetop_create_user_shortlink( $user_id, $url, $custom_alias = '', $fallback_url = '', $created_via = 'manual' ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

    // ── Cổng "Nguồn file gốc": chưa được Admin duyệt thì không tạo được link.
    //    Đặt ở đây vì đây là điểm chốt DUY NHẤT của cả dashboard (AJAX) lẫn API.
    if ( function_exists( 'sitetop_source_is_approved' ) && ! sitetop_source_is_approved( $user_id ) ) {
        return new WP_Error( 'source_unapproved', sitetop_source_block_message( $user_id ) );
    }

    $url = esc_url_raw( $url );
    if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        return new WP_Error( 'invalid_url', 'URL không hợp lệ' );
    }

    // Generate unique 6-char code
    $code = sitetop_generate_unique_shortcode();

    // Custom alias
    $alias = null;
    if ( $custom_alias ) {
        $alias = sanitize_title( $custom_alias );
        /* Chặn bí danh trùng slug hệ thống hoặc trùng một trang đang có — không
           chặn thì link rút gọn che mất trang thật (vd đặt bí danh 'dang-nhap'). */
        if ( function_exists( 'sitetop_alias_available' ) ) {
            $loi = sitetop_alias_available( $alias );
            if ( $loi !== '' ) return new WP_Error( 'alias_invalid', $loi );
        } elseif ( strlen( $alias ) < 3 ) {
            return new WP_Error( 'alias_short', 'Bí danh tối thiểu 3 ký tự' );
        }
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}user_shortlinks WHERE alias = %s OR code = %s", $alias, $alias
        ));
        if ( $exists > 0 ) return new WP_Error( 'alias_taken', 'Bí danh đã được sử dụng' );
    }

    $data = array(
        'user_id'      => $user_id,
        'code'         => $code,
        'alias'        => $alias,
        'original_url' => $url,
        'fallback_url' => esc_url_raw( $fallback_url ),
        'status'       => 'active',
        'created_at'   => sitetop_current_time(),
    );

    // Defensive: chỉ ghi created_via nếu cột tồn tại (compat installs cũ chưa migrate)
    static $has_created_via = null;
    if ( $has_created_via === null ) {
        $has_created_via = (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'created_via'",
            $wpdb->prefix . 'sitetop_user_shortlinks'
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

function sitetop_generate_unique_shortcode( $length = 6 ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
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

function sitetop_generate_visit_verify_code() {
    return strtoupper( substr( bin2hex( random_bytes( 4 ) ), 0, 8 ) );
}

/* ============================================================
   4. GENERATE SESSION ID (32-char unique)
   ============================================================ */

function sitetop_generate_session_id() {
    return bin2hex( random_bytes( 16 ) ); // 32 chars
}

/* ============================================================
   5. LOOKUP SHORTLINK BY CODE OR ALIAS
   Section 8: taskify_get_shortlink_by_code_or_alias()
   ============================================================ */

function sitetop_get_shortlink_by_code_or_alias( $code_or_alias ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    return $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$p}user_shortlinks WHERE (code = %s OR alias = %s) AND status = 'active'",
        $code_or_alias, $code_or_alias
    ));
}

/* ============================================================
   BLOCK PAGE - Trang cảnh báo khi bị chặn
   ============================================================ */
function sitetop_show_block_page( $reason = 'blocked' ) {
    http_response_code( 403 );

    // Mỗi lý do một nội dung riêng. Bản cũ luôn hiện "Fake IP" cho MỌI lý do nên user
    // bị chặn vì quá tải/IP blacklist vẫn bị mắng là xài VPN — đọc xong không biết sửa gì.
    $reasons = array(
        'vpn' => array(
            'tag'   => 'VPN',
            'title' => 'Bạn đang dùng VPN',
            'lead'  => 'Hệ thống thấy kết nối của bạn đi qua <b>VPN</b>. Nhiệm vụ cần IP thật để tính lượt truy cập hợp lệ.',
            'steps' => array( 'Tắt ứng dụng VPN (1.1.1.1, NordVPN, Proton…)', 'Tắt VPN trong Cài đặt → VPN của điện thoại', 'Tải lại trang này' ),
            'btn'   => 'Tôi đã tắt VPN, thử lại',
            'help'  => 'Chắc chắn không dùng VPN?',
        ),
        'proxy' => array(
            'tag'   => 'Proxy',
            'title' => 'Bạn đang dùng Proxy',
            'lead'  => 'Kết nối của bạn đi qua <b>proxy trung gian</b> nên không xác định được IP thật.',
            'steps' => array( 'Tắt Proxy trong cài đặt mạng của thiết bị', 'Tắt tiện ích đổi IP trên trình duyệt', 'Tải lại trang này' ),
            'btn'   => 'Tôi đã tắt Proxy, thử lại',
            'help'  => 'Chắc chắn không dùng Proxy?',
        ),
        'datacenter' => array(
            'tag'   => 'Máy chủ',
            'title' => 'IP máy chủ, không phải mạng người dùng',
            'lead'  => 'IP này thuộc <b>trung tâm dữ liệu</b> (hosting/cloud). Nhiệm vụ chỉ nhận traffic từ mạng nhà hoặc mạng di động thật.',
            'steps' => array( 'Dùng Wi-Fi nhà hoặc 4G/5G của điện thoại', 'Không truy cập qua máy chủ ảo / trình duyệt đám mây', 'Tải lại trang này' ),
            'btn'   => 'Đã đổi mạng, thử lại',
            'help'  => 'Đây là mạng nhà bạn?',
        ),
        'an_danh' => array(
            'tag'   => 'Ẩn danh',
            'title' => 'Không làm nhiệm vụ bằng cửa sổ ẩn danh',
            'lead'  => 'Nhiệm vụ không nhận lượt từ <b>cửa sổ ẩn danh</b>. Khoá sẽ <b>tự hết sau '
                . ( defined( 'SITETOP_ANDANH_BLOCK_MINUTES' ) ? (int) SITETOP_ANDANH_BLOCK_MINUTES : 30 ) . ' phút</b>.',
            'steps' => array(
                'Đợi hết thời gian khoá, rồi mở lại bằng cửa sổ thường',
                'Không dùng chế độ ẩn danh / riêng tư khi làm nhiệm vụ',
                'Nếu chắc mình không dùng ẩn danh, liên hệ hỗ trợ',
            ),
            'btn'   => 'Thử lại',
            'help'  => 'Bạn nghĩ đây là nhầm lẫn?',
        ),
        'ip_blocked' => array(
            'tag'   => 'Tạm khoá',
            'title' => 'IP của bạn đang bị tạm khoá',
            'lead'  => 'IP này bị khoá tạm thời do có dấu hiệu bất thường. Khoá sẽ <b>tự hết sau 24 giờ</b>.',
            'steps' => array( 'Đợi hết thời gian khoá rồi vào lại', 'Hoặc đổi sang mạng khác (4G ↔ Wi-Fi)', 'Nếu chắc mình không vi phạm, liên hệ hỗ trợ' ),
            'btn'   => 'Thử lại',
            'help'  => 'Bạn nghĩ đây là nhầm lẫn?',
        ),
    );
    $r = $reasons[ $reason ] ?? array(
        'tag'   => 'Bị chặn',
        'title' => 'Không thể mở link lúc này',
        'lead'  => 'Truy cập của bạn tạm thời bị từ chối.',
        'steps' => array( 'Thử lại sau ít phút', 'Hoặc đổi sang mạng khác', 'Vẫn lỗi thì liên hệ hỗ trợ' ),
        'btn'   => 'Thử lại',
        'help'  => 'Cần hỗ trợ?',
    );

    $ip       = function_exists( 'sitetop_get_real_ip' ) ? sitetop_get_real_ip() : ( $_SERVER['REMOTE_ADDR'] ?? '' );
    $telegram = function_exists( 'sitetop_get_option' ) ? sitetop_get_option( 'contact_telegram', '' ) : '';
    $zalo     = function_exists( 'sitetop_get_option' ) ? sitetop_get_option( 'contact_zalo', '' ) : '';
    $icon     = defined( 'SITETOP_URL' ) && function_exists( 'sitetop_logo_url' ) ? sitetop_logo_url( 'sitetop-icon.png' ) : '';
    ?><!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( $r['title'] ); ?></title>
<meta name="robots" content="noindex">
<?php if ( $icon ) : ?><link rel="icon" type="image/png" href="<?php echo esc_url( $icon ); ?>"><?php endif; ?>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;
     font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1F2A44;
     background:#F2F5FC;background-image:radial-gradient(1200px 400px at 50% -120px,#E2ECFF 0%,#F2F5FC 70%)}
.bk{width:100%;max-width:420px;background:#fff;border-radius:18px;overflow:hidden;
    box-shadow:0 24px 60px -28px rgba(10,22,51,.4),0 2px 8px rgba(10,22,51,.06)}
.bk-top{height:5px;background:linear-gradient(90deg,#DC2626,#F97316)}
.bk-in{padding:26px 22px 22px;text-align:center}
.bk-ic{width:64px;height:64px;margin:0 auto 14px;border-radius:50%;background:#FEE2E2;
       display:flex;align-items:center;justify-content:center}
.bk-tag{display:inline-block;padding:4px 11px;border-radius:99px;background:#FEF2F2;color:#B91C1C;
        font-size:11px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;margin-bottom:10px}
h1{font-size:19px;font-weight:800;line-height:1.35;margin-bottom:9px;text-wrap:balance}
.bk-lead{font-size:14px;line-height:1.65;color:#5A6684}
.bk-lead b{color:#1F2A44}
.bk-steps{margin-top:18px;text-align:left;background:#F7F9FD;border:1px solid #E3E8F2;border-radius:14px;padding:14px 15px}
.bk-steps-h{font-size:12px;font-weight:800;color:#1F2A44;margin-bottom:10px;letter-spacing:.2px}
.bk-step{display:flex;gap:10px;align-items:flex-start;font-size:13px;line-height:1.55;color:#5A6684;padding:5px 0}
.bk-n{flex:none;width:19px;height:19px;margin-top:1px;border-radius:50%;background:#1E5EFF;color:#fff;
      font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center}
.bk-ip{margin-top:16px;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;
       font-size:12px;color:#8A93AB}
.bk-ip code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px;color:#1F2A44;
            background:#EEF2FA;border:1px solid #E3E8F2;border-radius:7px;padding:3px 9px;user-select:all}
.bk-btn{display:block;width:100%;margin-top:18px;padding:13px 20px;border:none;border-radius:12px;cursor:pointer;
        background:#1E5EFF;color:#fff;font-size:14.5px;font-weight:700;font-family:inherit;
        box-shadow:0 8px 20px -8px rgba(30,94,255,.7);transition:transform .12s,box-shadow .12s}
.bk-btn:hover{transform:translateY(-1px);box-shadow:0 12px 24px -10px rgba(30,94,255,.8)}
.bk-btn:active{transform:translateY(0)}
.bk-btn:focus-visible{outline:3px solid rgba(30,94,255,.35);outline-offset:2px}
.bk-help{margin-top:14px;font-size:12.5px;color:#8A93AB}
.bk-help a{color:#1E5EFF;text-decoration:none;font-weight:600}
.bk-help a:hover{text-decoration:underline}
@media(max-width:400px){.bk-in{padding:22px 16px 18px}h1{font-size:17.5px}.bk-ic{width:56px;height:56px}}
@media(prefers-reduced-motion:reduce){.bk-btn{transition:none}.bk-btn:hover{transform:none}}
</style></head>
<body>
<main class="bk">
    <div class="bk-top"></div>
    <div class="bk-in">
        <div class="bk-ic">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2 4 6v6c0 5 3.4 9.1 8 10 4.6-.9 8-5 8-10V6l-8-4Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="16.5" x2="12.01" y2="16.5"/></svg>
        </div>
        <span class="bk-tag"><?php echo esc_html( $r['tag'] ); ?></span>
        <h1><?php echo esc_html( $r['title'] ); ?></h1>
        <p class="bk-lead"><?php echo wp_kses( $r['lead'], array( 'b' => array() ) ); ?></p>

        <div class="bk-steps">
            <div class="bk-steps-h">Cách xử lý</div>
            <?php foreach ( $r['steps'] as $i => $s ) : ?>
            <div class="bk-step"><span class="bk-n"><?php echo (int) ( $i + 1 ); ?></span><span><?php echo esc_html( $s ); ?></span></div>
            <?php endforeach; ?>
        </div>

        <?php if ( $ip ) : ?>
        <div class="bk-ip">IP của bạn <code><?php echo esc_html( $ip ); ?></code></div>
        <?php endif; ?>

        <button type="button" class="bk-btn" onclick="location.reload()"><?php echo esc_html( $r['btn'] ?? 'Thử lại' ); ?></button>

        <?php if ( $telegram || $zalo ) : ?>
        <p class="bk-help"><?php echo esc_html( $r['help'] ?? 'Cần hỗ trợ?' ); ?>
            <?php if ( $telegram ) : ?><a href="https://t.me/<?php echo esc_attr( ltrim( $telegram, '@' ) ); ?>" target="_blank" rel="noopener">Báo qua Telegram</a><?php endif; ?>
            <?php if ( $telegram && $zalo ) : ?> · <?php endif; ?>
            <?php if ( $zalo ) : ?><a href="https://zalo.me/<?php echo esc_attr( ltrim( $zalo, '+' ) ); ?>" target="_blank" rel="noopener">Zalo</a><?php endif; ?>
        </p>
        <?php endif; ?>
    </div>
</main>
<?php if ( 'an_danh' === $reason ) : ?>
<script>
/* Người lỡ mở nhầm cửa sổ ẩn danh, đọc trang này rồi mở lại bằng cửa sổ thường vẫn
   bị chặn tiếp, vì khoá gồm IP + máy + ngôn ngữ mà tắt ẩn danh không đổi thứ nào.
   Nên chính trang chặn tự dò lại: không còn ẩn danh thì gỡ khoá và vào luôn. */
(function(){
    var api = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    function go(){
        var fd = new FormData();
        fd.append('action', 'sitetop_go_an_danh');
        fetch(api, { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(){ window.location.reload(); })
            .catch(function(){});
    }
    function duPhong(){
        try {
            if (!navigator.storage || !navigator.storage.estimate) return;
            navigator.storage.estimate().then(function(u){
                var han = u && u.quota ? u.quota : 0;
                if (han > 240 * 1024 * 1024) go();   // hạn mức rộng → cửa sổ thường
            }).catch(function(){});
        } catch(e) {}
    }
    var sc = document.createElement('script');
    sc.src = <?php echo wp_json_encode( get_template_directory_uri() . '/assets/js/detect-incognito.js?v=1.9.0' ); ?>;
    sc.onload = function(){
        if (typeof detectIncognito !== 'function') { duPhong(); return; }
        detectIncognito().then(function(kq){ if (!kq.isPrivate) go(); }).catch(duPhong);
    };
    sc.onerror = duPhong;
    document.head.appendChild(sc);
})();
</script>
<?php endif; ?>
</body></html><?php
    exit;
}

/* ============================================================
   6. HANDLE SHORTLINK VISIT (Flow 1 entry point)
   /{shortcode} → page-unlock.php
   ============================================================ */

function sitetop_handle_shortlink_visit( $code ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

    $ip = sitetop_get_real_ip();

    /* Ẩn danh: khoá riêng 30 phút. Kiểm TRƯỚC các chốt khác để câu báo nói đúng
       lý do — rơi vào nhánh 'ip_blocked' thì user bị bảo là "dấu hiệu bất thường"
       và đi đổi mạng, trong khi việc cần làm chỉ là tắt cửa sổ ẩn danh. */
    if ( function_exists( 'sitetop_andanh_dang_khoa' ) && sitetop_andanh_dang_khoa( $ip ) ) {
        sitetop_show_block_page( 'an_danh' );
    }

    // Block check (ip_reputation + ddos_blocks) — check reason for better message
    if ( sitetop_is_ip_blocked( $ip ) ) {
        $rep = sitetop_get_ip_reputation( $ip );
        if ( $rep && ! empty( $rep->is_vpn ) ) sitetop_show_block_page( 'vpn' );
        elseif ( $rep && ! empty( $rep->is_proxy ) ) sitetop_show_block_page( 'proxy' );
        else sitetop_show_block_page( 'ip_blocked' );
    }

    // VPN/Proxy realtime check via ip-api.com (BEFORE validate_ip for better message)
    if ( function_exists( 'sitetop_check_ip_api' ) && sitetop_get_option( 'detect_vpn_proxy', 1 ) ) {
        $ip_check = sitetop_check_ip_api( $ip );
        $blocked_reason = '';
        if ( ! empty( $ip_check['is_proxy'] ) && sitetop_get_option( 'block_proxy_ip', 1 ) ) {
            $blocked_reason = 'proxy';
        } elseif ( ! empty( $ip_check['is_vpn'] ) && sitetop_get_option( 'block_vpn_ip', 1 ) ) {
            $blocked_reason = 'vpn';
        } elseif ( ! empty( $ip_check['is_hosting'] ) && sitetop_get_option( 'block_datacenter_ip', 0 ) ) {
            $blocked_reason = 'datacenter';
        }
        if ( $blocked_reason ) {
            // Auto-block in ip_reputation for future fast checks
            global $wpdb;
            $p = $wpdb->prefix . 'sitetop_';
            $wpdb->query( $wpdb->prepare(
                "INSERT INTO {$p}ip_reputation (ip_address, is_vpn, is_proxy, is_hosting, risk_score, blocked, checked_at)
                 VALUES (%s, %d, %d, %d, %d, 1, %s)
                 ON DUPLICATE KEY UPDATE is_vpn=%d, is_proxy=%d, is_hosting=%d, risk_score=%d, blocked=1, checked_at=%s",
                $ip, !empty($ip_check['is_vpn']), !empty($ip_check['is_proxy']), !empty($ip_check['is_hosting']),
                $ip_check['risk_score'] ?? 70, sitetop_current_time(),
                !empty($ip_check['is_vpn']), !empty($ip_check['is_proxy']), !empty($ip_check['is_hosting']),
                $ip_check['risk_score'] ?? 70, sitetop_current_time()
            ));
            sitetop_show_block_page( $blocked_reason );
        }
    }

    // Validate IP (DNS resolvers, private ranges) — after VPN check for better message
    if ( ! sitetop_validate_ip( $ip ) ) {
        sitetop_show_block_page( 'vpn' ); // Most likely VPN/proxy causing invalid IP
    }

    // Rate limit shortlink_click — ĐÃ GỠ chặn theo yêu cầu (không hiện trang "Too many requests." khi
    // vào shortlink). Lớp chống lạm dụng thật vẫn còn: anti-ddos (global/burst/sustained) + IP block/
    // VPN/proxy checks phía trên. Nếu cần bật lại: khôi phục sitetop_rate_limit_check('shortlink_click').

    // Lookup shortlink
    $shortlink = sitetop_get_shortlink_by_code_or_alias( $code );
    if ( ! $shortlink ) return; // Not a shortlink, let WP handle

    // Create or reuse visit session
    $session_id = sitetop_create_visit_session( $shortlink, $ip );
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
        $campaign = sitetop_get_random_active_campaign( $ip );
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
    $_SESSION['sitetop_shortlink']  = $shortlink;
    $_SESSION['sitetop_campaign']   = $campaign;
    $_SESSION['sitetop_session_id'] = $session_id;

    // Set cross-site cookie for widget AJAX fallback (IP may differ due to dual-stack IPv4/IPv6)
    $cookie_opts = array(
        'expires'  => time() + sitetop_get_visit_expiry_seconds(),
        'path'     => '/',
        'secure'   => true,
        'httponly'  => false,
        'samesite'  => 'None',
    );
    if ( PHP_VERSION_ID >= 70300 ) {
        setcookie( 'sitetop_sid', $session_id, $cookie_opts );
    } else {
        setcookie( 'sitetop_sid', $session_id, $cookie_opts['expires'], $cookie_opts['path'] . '; SameSite=None', '', true, false );
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

/**
 * Hạn mức TÍNH TIỀN VIEW của một IP trong 24 giờ gần nhất.
 *
 * QUY TẮC (chốt 14/08/2026):
 *   - Mỗi SHORTLINK KHÁC NHAU chỉ được tính tối đa 1 view. Làm đi làm lại CÙNG một
 *     shortlink bao nhiêu lần cũng chỉ tính 1.
 *   - Mỗi IP tối đa 2 view trong 24 giờ TRƯỢT (không phải ngày lịch).
 *
 * VÌ SAO PHẢI ĐẾM THEO SHORTLINK: bản cũ đếm SỐ LƯỢT hoàn thành
 * (COUNT(*) ... step='verified'), nên một shortlink làm 2 lần = 2 view = trả tiền 2 lần.
 * Chốt duy nhất chặn trùng là 'ip_repeat_same_campaign' — nhưng nó xét CAMPAIGN, mà mỗi
 * lượt lại được gán campaign NGẪU NHIÊN, nên hai lần làm cùng một shortlink thường rơi
 * vào hai campaign khác nhau và lọt qua sạch.
 *
 * Chỉ tính các lượt ĐÃ TRẢ THƯỞNG (reward_paid = 1): lượt verified nhưng bị chặn trả
 * thưởng (adblock, đổi IP...) không được chiếm mất suất của user.
 *
 * @param string $ip           IP đã chuẩn hoá (sitetop_get_real_ip).
 * @param int    $shortlink_id Shortlink của lượt đang xét.
 * @return array{used:int,same_link:bool,allowed:bool,limit:int}
 *         used      = số shortlink KHÁC NHAU đã được trả thưởng trong 24h
 *         same_link = shortlink này đã được tính view trong 24h rồi
 *         allowed   = lượt này có được tính tiền view không
 */
function sitetop_ip_view_quota( $ip, $shortlink_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

    // Trần CỨNG 2: option trên production có thể còn giá trị cũ (5) từ đời trước.
    $limit = (int) sitetop_get_option( 'shortlink_ip_limit_24h', 2 );
    if ( $limit < 1 || $limit > 2 ) { $limit = 2; }

    $paid_links = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT shortlink_id FROM {$p}shortlink_visits
         WHERE ip_address = %s AND reward_paid = 1 AND shortlink_id > 0
         AND created_at > DATE_SUB(%s, INTERVAL 24 HOUR)",
        $ip, sitetop_current_time()
    ));
    $paid_links = array_map( 'intval', (array) $paid_links );

    $sid  = (int) $shortlink_id;
    $same = ( $sid > 0 && in_array( $sid, $paid_links, true ) );
    $used = count( $paid_links );

    return array(
        'used'      => $used,
        'same_link' => $same,
        'allowed'   => ( ! $same && $used < $limit ),
        'limit'     => $limit,
    );
}

function sitetop_get_visit_expiry_seconds() {
    $sec = (int) sitetop_get_option( 'verify_code_expiry', 600 );
    return max( 60, $sec );
}

function sitetop_create_visit_session( $shortlink, $ip ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $now = sitetop_current_time();

    // Reuse check (window synced with verify_code_expiry setting)
    $expiry_sec = sitetop_get_visit_expiry_seconds();
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

        delete_transient( 'sitetop_widget_code_ready_' . $sid );
        delete_transient( 'sitetop_widget_cd_' . $sid );
        delete_transient( 'sitetop_widget_code_' . $sid );
        delete_transient( 'sitetop_verify_code_' . $sid );
        delete_transient( 'sitetop_google_clicked_' . $sid );

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
    $session_id = sitetop_generate_session_id();
    // user_id = shortlink OWNER (publisher), NOT visitor
    $user_id = (int) $shortlink->user_id;

    // Đánh dấu trước: lượt này có nằm ngoài hạn mức tính tiền view không.
    // Dùng CHUNG sitetop_ip_view_quota() với lúc xác minh — trước đây chỗ này đếm số lượt
    // theo ngày lịch còn lúc trả thưởng đếm kiểu khác, nên cờ ip_limit_exceeded trong
    // thống kê admin không khớp với tiền thực trả.
    $ip_quota    = sitetop_ip_view_quota( $ip, (int) $shortlink->id );
    $ip_exceeded = ! $ip_quota['allowed'];

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
            $wpdb->prefix . 'sitetop_shortlink_visits'
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
    $_SESSION['sitetop_shortlink'] = $shortlink;
    $_SESSION['sitetop_session_id'] = $session_id;

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

function sitetop_get_widget_code( $session_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

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

    // IP ownership check.
    // CROSS-SITE DUAL-STACK FIX: IP có thể đổi giữa page-unlock (tạo visit) và widget
    // call cross-origin (Apple Private Relay, CGNAT mobile, IPv4↔IPv6). widget_verify_access
    // đã tolerate case này (fallback cookie/domain) và — SAU khi validate Origin + đúng domain
    // target + path — set url_matched=1. url_matched=1 chỉ do 1 browser THẬT trên đúng target
    // site tạo (curl không vượt được Origin check) → tin cờ đó để MIỄN đòi IP khớp tuyệt đối.
    // IP khác + url_matched=0 (chưa qua verify_access hợp lệ) → VẪN chặn (chống forge cross-IP).
    $ip = function_exists('sitetop_get_real_ip') ? sitetop_get_real_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
    if ( $visit->ip_address !== $ip && empty( $visit->url_matched ) ) {
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
        $expiry = (int) sitetop_get_option( 'verify_code_expiry', 600 );
        set_transient( 'sitetop_widget_code_ready_' . $session_id, 1, $expiry );
        set_transient( 'sitetop_verify_code_' . $session_id, $visit->verify_code, $expiry );
        return $visit->verify_code;
    }

    // TIME CHECK + FLAG CHECK (skip for nocode)
    if ( ! $is_nocode ) {
        $created_at = strtotime( $visit->created_at );
        $now = strtotime( sitetop_current_time() );
        $elapsed = $now - $created_at;
        $onsite = (int) ( $visit->camp_onsite ?? $visit->onsite_time ?? 70 );
        $required = max( $onsite - 5, 10 );

        if ( $elapsed < $required ) {
            /* ĐẾM SỐ LẦN ĐÒI MÃ SỚM — dấu vết của công cụ tua nhanh đồng hồ.
               Người dùng bình thường gần như KHÔNG bao giờ rơi vào đây: widget đếm đủ
               onsite (70s) rồi mới đòi, trong khi server chỉ cần onsite-5 (65s) — luôn
               chậm hơn ngưỡng 5 giây. Ngược lại, khi đồng hồ bị tua: widget đốt hết số
               giây trong tích tắc -> đòi mã -> server từ chối kèm `remaining` -> widget
               đặt lại rồi lại đốt tiếp -> đòi lại... mỗi vòng để lại một lần đếm ở đây.
               Bộ đếm nằm ở SERVER nên công cụ phía trình duyệt không sửa được.
               Chỉ ĐẾM, không chặn: vẫn trả 'too_fast' như cũ để kẻ tua không nhận ra
               mình bị lộ (cùng triết lý với vùng 2 của chốt bypass). Việc phạt diễn ra
               lúc trả thưởng, xem sitetop_verify_and_pay(). */
            $tm_key = 'sitetop_toofast_' . $session_id;
            $tm_cnt = (int) get_transient( $tm_key );
            set_transient( $tm_key, $tm_cnt + 1, 2 * HOUR_IN_SECONDS );

            return new WP_Error( 'too_fast', 'Chưa đủ thời gian', array(
                'remaining' => max( 0, $onsite - $elapsed ),   // trọn thời lượng camp, giữ biên 5 giây
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
            return new WP_Error( 'no_google', 'Chỉ chấp nhận tìm từ khoá trên Google bằng Google Chrome. Hãy mở Chrome, gõ từ khoá rồi bấm vào kết quả.' );
        }
    }

    // Generate code
    if ( $is_nocode && $visit->fixed_code ) {
        $code = $visit->fixed_code; // Case-sensitive
    } else {
        $code = sitetop_generate_visit_verify_code(); // 8-char hex
    }

    // Save code + update step
    $wpdb->update( "{$p}shortlink_visits", array(
        'verify_code'   => $code,
        'step'          => 'code_shown',
        'code_shown_at' => sitetop_current_time(),
    ), array( 'session_id' => $session_id ) );

    // Set transients by session_id
    $expiry = (int) sitetop_get_option( 'verify_code_expiry', 600 ); // 10 min default
    set_transient( 'sitetop_widget_code_ready_' . $session_id, 1, $expiry );
    set_transient( 'sitetop_verify_code_' . $session_id, $code, $expiry ); // 10 min

    /* CHỐT SỚM 26/08/2026 — user đã chờ đủ thời gian onsite nên tính view và trừ tiền
       khách hàng ngay tại đây, không đợi user gõ mã. Gọi lại chính hàm tính tiền nên
       ĐẦY ĐỦ 22 chốt vẫn chạy (captcha, số dư khách, giới hạn IP, chống bot...) — chỉ
       khác là không trả thưởng user và không đóng phiên.
       Bọc try/catch: lỗi ở bước tính tiền tuyệt đối không được chặn việc đưa mã cho user.
       KHÔNG áp dụng cho nocode: loại đó server không kiểm thời gian (cả hàm này lẫn
       sitetop_verify_and_pay đều bỏ qua), nên chốt sớm sẽ trừ tiền ngay lúc mở trang —
       sai với tiền đề "đã chờ đủ giờ". Nocode giữ nguyên cách tính cũ. */
    if ( ! $is_nocode && function_exists( 'sitetop_verify_and_pay' ) ) {
        try {
            sitetop_verify_and_pay( $session_id, $code, true );
        } catch ( \Throwable $e ) {
            error_log( 'SiteTop auto-settle lỗi (session ' . $session_id . '): ' . $e->getMessage() );
        }
    }

    return $code;
}

// Alias
function sitetop_get_verify_code( $session_id ) {
    return sitetop_get_widget_code( $session_id );
}

/* ============================================================
   8b. UPDATE VISIT STEP (guard against regression from 'verified')
   Production: taskify_update_visit_step()
   ============================================================ */

function sitetop_update_visit_step( $session_id, $step ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

    $time_field = '';
    switch ( $step ) {
        case 'google_clicked':  $time_field = 'google_clicked_at'; break;
        case 'target_visited':  $time_field = 'target_visited_at'; break;
        case 'code_shown':      $time_field = 'code_shown_at'; break;
        case 'verified':        $time_field = 'verified_at'; break;
    }

    $update = array( 'step' => $step );
    if ( $time_field ) $update[ $time_field ] = sitetop_current_time();

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

function sitetop_create_keyword_campaign( $data ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

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
    $price_per_view = floatval( $data['price_per_view'] ?? sitetop_get_option( $price_key, $default_prices[ $traffic_type ] ?? 1200 ) );

    // User reward = price × reward_percent / 100
    $reward_pct = (int) sitetop_get_option( 'keyword_user_reward_percent', 80 );
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
        'created_at'         => sitetop_current_time(),
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
            'created_at'       => sitetop_current_time(),
        ));
        $order_id = $wpdb->insert_id;
        $wpdb->update( "{$p}keyword_campaigns", array( 'order_id' => $order_id ), array( 'id' => $campaign_id ) );
    }

    // Email admin
    sitetop_send_new_campaign_email( $campaign_id );

    return $campaign_id;
}

function sitetop_get_campaign( $id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}keyword_campaigns WHERE id = %d", $id ) );
}

function sitetop_update_campaign( $id, $data ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

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
        'title'=>'%s','keyword'=>'%s','target_url'=>'%s','destination_urls'=>'%s','traffic_type'=>'%s',
        'price_per_view'=>'%f','user_reward'=>'%f','quantity'=>'%d','daily_traffic'=>'%d',
        'onsite_time'=>'%d','countdown_seconds'=>'%d','fixed_code'=>'%s',
        'screenshot_desktop_url'=>'%s','screenshot_mobile_url'=>'%s','nocode_screenshot_url'=>'%s',
        'step2_image_url'=>'%s','step2_target_url'=>'%s',
        'serp_page'=>'%d',
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

    $update['updated_at'] = sitetop_current_time();
    $format[] = '%s';

    return $wpdb->update( "{$p}keyword_campaigns", $update, array('id'=>$id), $format, array('%d') );
}

function sitetop_get_campaigns( $args = array() ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

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
