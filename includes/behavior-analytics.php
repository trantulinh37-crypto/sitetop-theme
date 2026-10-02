<?php
/**
 * SiteTop.net V2 - Behavior Analytics & Fraud Scoring
 * Flow 9c: 15+ factors, risk levels safe/low/medium/high
 * Auto-block: 3 lần vi phạm (>= 70 điểm, đo đủ 10 giây) từ cùng IP trong 60 phút — nới 22/09/2026
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Calculate fraud score (0-100) from behavior data
 * Exact factors and points from CLAUDE.md Flow 9c
 */
function sitetop_calculate_fraud_score( $data ) {
    /* NỚI LỎNG 22/09/2026 — CHỈ CHẤM THỨ WIDGET THẬT SỰ ĐO.
       widget.js.php chỉ gửi 5 số (mouse_movements, scroll_depth, time_on_page, tab_switches,
       clicks), và chỉ gửi MỘT lần lúc beforeunload — tức lúc rời trang hoặc TẢI LẠI. Bản cũ phạt
       cả những trường widget không bao giờ gửi: không kích thước màn hình +25, không gõ phím +10,
       không canvas +10, không webgl +5 → AI CŨNG mang sẵn 50 điểm; is_mobile không gửi → điện
       thoại bị chấm như máy tính "không di chuột" +30 → 80 điểm, vượt ngưỡng 70 khi chưa làm gì.
       User thật bị trang báo lỗi, tải lại 2 lần là bị khoá IP 12 giờ (chủ site báo 22/09, IP
       14.169.26.16: 100 rồi 75 điểm, cách nhau 19 giây).
       Nay: trường KHÔNG có trong dữ liệu gửi lên thì KHÔNG chấm — thiếu dữ liệu không phải bằng
       chứng gian lận; is_mobile không gửi thì nhận theo User-Agent (wp_is_mobile). Client nào gửi
       đủ trường thì các luật cũ vẫn chạy nguyên như trước. */
    $score = 0;
    $reasons = array();
    $co = function ( $k ) use ( $data ) { return is_array( $data ) && array_key_exists( $k, $data ); };
    $is_mobile = $co( 'is_mobile' )
        ? ! empty( $data['is_mobile'] )
        : ( function_exists( 'wp_is_mobile' ) && wp_is_mobile() );

    // ── DEVICE (max +50) ──
    if ( ! empty( $data['is_bot'] ) ) {
        $score += 50; $reasons[] = 'bot_detected';
    }
    $sw = (int) ( $data['screen_width'] ?? 0 );
    $sh = (int) ( $data['screen_height'] ?? 0 );
    if ( $co( 'screen_width' ) || $co( 'screen_height' ) ) {
        if ( $sw === 0 && $sh === 0 ) { $score += 25; $reasons[] = 'no_screen_size'; }
        elseif ( $sw < 300 && $sh < 300 && $sw > 0 ) { $score += 15; $reasons[] = 'small_screen'; }
    }

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
    if ( $co( 'keystrokes' ) && $keystrokes === 0 ) { $score += 10; $reasons[] = 'no_keystrokes'; }

    $touch = (int) ( $data['touch_events'] ?? 0 );
    if ( $is_mobile && $co( 'touch_events' ) && $touch === 0 && $mouse === 0 ) { $score += 25; $reasons[] = 'no_touch_mobile'; }

    // ── TIME (max +25) ──
    $time = (int) ( $data['time_on_page'] ?? 0 );
    if ( $time < 5 ) { $score += 25; $reasons[] = 'time_lt_5s'; }
    elseif ( $time < 10 ) { $score += 10; $reasons[] = 'time_lt_10s'; }

    $idle = (int) ( $data['idle_time'] ?? 0 );
    if ( $time > 0 && $idle / max( 1, $time ) > 0.95 ) { $score += 15; $reasons[] = 'idle_gt_95pct'; }

    $hidden = (int) ( $data['page_hidden_time'] ?? 0 );
    if ( $time > 0 && $hidden > $time / 2 ) { $score += 10; $reasons[] = 'hidden_gt_visible'; }

    // ── NETWORK (max +30) ──
    $ip_for_wl = $data['ip_address'] ?? ( function_exists('sitetop_get_real_ip') ? sitetop_get_real_ip() : '' );
    $is_wl = function_exists('sitetop_is_ip_whitelisted') && sitetop_is_ip_whitelisted( $ip_for_wl );
    if ( ! $is_wl ) {
        if ( ! empty( $data['is_datacenter'] ) ) { $score += 30; $reasons[] = 'datacenter_ip'; }
        if ( ! empty( $data['is_vpn'] ) || ! empty( $data['is_proxy'] ) ) { $score += 20; $reasons[] = 'vpn_proxy'; }
    }

    // ── FINGERPRINT (max +30) ──
    $canvas = $data['canvas_hash'] ?? '';
    if ( $co( 'canvas_hash' ) && empty( $canvas ) ) { $score += 10; $reasons[] = 'no_canvas'; }

    $webgl = $data['webgl_vendor'] ?? '';
    if ( $co( 'webgl_vendor' ) && empty( $webgl ) ) { $score += 5; $reasons[] = 'no_webgl'; }

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
 * Save behavior analytics to database — UPSERT per session_id để chống bảng phình.
 * Trước: INSERT mỗi heartbeat → 1 visitor browse N page = N rows.
 * Sau: UPSERT theo session_id → 1 visitor browse N page = 1 row (update fresh).
 *
 * Yêu cầu schema: UNIQUE INDEX `session_id_unique` trên cột session_id.
 * Migration tự động ở functions.php init hook sẽ dedupe + add unique nếu chưa có.
 */
function sitetop_save_behavior_analytics( $visit_id, $session_id, $data ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

    // Skip junk row — client gửi data trước khi có session_id → không lưu.
    if ( empty( $session_id ) ) return;

    // Calculate fraud score
    $fraud = sitetop_calculate_fraud_score( $data );

    $row = array(
        'visit_id'        => $visit_id,
        'session_id'      => $session_id,
        'user_id'         => get_current_user_id(),
        'ip_address'      => sitetop_get_real_ip(),
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
        'created_at'      => sitetop_current_time(),
    );

    // Build INSERT ... ON DUPLICATE KEY UPDATE (UPSERT theo session_id)
    $cols  = array_keys( $row );
    $vals  = array_values( $row );
    $place = array_fill( 0, count( $cols ), '%s' );
    $set   = array();
    foreach ( $cols as $c ) {
        if ( $c === 'session_id' ) continue; // không đè key
        $set[] = "`$c` = VALUES(`$c`)";
    }
    $sql = "INSERT INTO `{$p}behavior_analytics` (`" . implode( '`,`', $cols ) . "`) "
         . "VALUES (" . implode( ',', $place ) . ") "
         . "ON DUPLICATE KEY UPDATE " . implode( ', ', $set );
    $wpdb->query( $wpdb->prepare( $sql, $vals ) );

    // Update visit fraud score
    if ( $visit_id ) {
        $wpdb->update( "{$p}shortlink_visits",
            array( 'fraud_score' => $fraud['fraud_score'] ),
            array( 'id' => $visit_id )
        );
    }

    /* Tự khoá IP — NỚI LỎNG 22/09/2026. Bản cũ: 2 lần >= 70 điểm BẤT KỲ LÚC NÀO (cả 14 ngày dữ
       liệu) là khoá 12 giờ → user thật bị trang báo lỗi, tải lại 2 lần là dính.
       Nay một "lần vi phạm" phải đủ CẢ HAI:
       - >= 70 điểm;
       - đo được ít nhất 10 giây: widget chỉ báo lúc rời/TẢI LẠI trang, báo dưới 10 giây là tải
         lại hoặc thoát ngay — quá ít dữ liệu để kết luận (lượt đó chưa tới lúc lấy mã, không mất gì).
       Và phải có 3 lần như vậy từ cùng IP trong 60 phút gần nhất mới khoá. */
    if ( $fraud['fraud_score'] >= 70 && (int) ( $data['time_on_page'] ?? 0 ) >= 10 ) {
        $ip = sitetop_get_real_ip();
        $fraud_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}behavior_analytics
              WHERE ip_address = %s AND fraud_score >= 70 AND time_on_page >= 10
                AND created_at >= DATE_SUB(%s, INTERVAL 60 MINUTE)",
            $ip, sitetop_current_time()
        ));
        if ( $fraud_count >= 3 ) {
            // Bao lâu: SITETOP_IP_KHOA_GIO — đúng số giờ trang 'ip_blocked' báo cho user.
            $gio_khoa = defined( 'SITETOP_IP_KHOA_GIO' ) ? (int) SITETOP_IP_KHOA_GIO : 12;
            $wpdb->query( $wpdb->prepare(
                "INSERT INTO {$p}ip_reputation (ip_address, blocked, blocked_until, fraud_score, checked_at)
                 VALUES (%s, 1, DATE_ADD(%s, INTERVAL %d HOUR), %d, %s)
                 ON DUPLICATE KEY UPDATE blocked=1, blocked_until=DATE_ADD(%s, INTERVAL %d HOUR), fraud_score=%d",
                $ip, sitetop_current_time(), $gio_khoa, $fraud['fraud_score'], sitetop_current_time(),
                sitetop_current_time(), $gio_khoa, $fraud['fraud_score']
            ));
        }
    }

    // Check/save device fingerprint
    if ( ! empty( $data['canvas_hash'] ) ) {
        sitetop_save_device_fingerprint( $data );
    }

    return $fraud;
}

/**
 * Save device fingerprint
 */
function sitetop_save_device_fingerprint( $data ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $user_id = get_current_user_id();
    $fp = sanitize_text_field( $data['canvas_hash'] ?? '' );
    if ( ! $fp || ! $user_id ) return;

    $existing = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$p}device_fingerprints WHERE user_id = %d AND fingerprint = %s",
        $user_id, $fp
    ));

    if ( $existing ) {
        $wpdb->update( "{$p}device_fingerprints", array(
            'last_seen' => sitetop_current_time(),
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
            'first_seen'        => sitetop_current_time(),
            'last_seen'         => sitetop_current_time(),
        ));
    }

    // Check multi-account: same fingerprint, different users
    $multi = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT user_id) FROM {$p}device_fingerprints WHERE fingerprint = %s", $fp
    ));
    if ( $multi > 1 ) {
        // Flag for fraud scoring
        update_user_meta( $user_id, 'sitetop_multi_account_fp', $fp );
    }
}

/* ============================================================
   CHỐT TỰ ĐỘNG: TỶ LỆ KHAI TÊN MÁY — phát hiện lưu lượng giả lập (02/10/2026)

   VÌ SAO CHỈ SỐ NÀY MẠNH: từ 2022 Chrome trên Android KHÔNG khai tên máy nữa, mọi điện
   thoại đều gửi đúng một chuỗi "Android 10; K". Nên lưu lượng người thật bắt buộc dồn vào
   chuỗi đó. Công cụ giả lập thì phải tự bịa tên máy cho "đa dạng" — và chính sự đa dạng ấy
   tố cáo nó.

   ĐO TRÊN PRODUCTION 02/10 (14 ngày, 54 tài khoản ≥200 lượt):
     10 tài khoản khai tên máy 94,9–99,9%  ← toàn bộ nhóm đã soi tay ra là bot
     khoảng trống rộng: 58,0% rồi 44,2%
     tất cả tài khoản còn lại dưới 11%
   Không một tài khoản thật nào lọt vào vùng trên 90%.

   ĐÂY LÀ CHỐT MỨC TÀI KHOẢN, KHÔNG PHẢI MỨC LƯỢT: một lượt lẻ khai tên máy KHÔNG có nghĩa
   gì (trình duyệt cũ, Firefox, UC, Samsung Internet đều khai thật — đo được 119 lượt/3 ngày
   trên toàn hệ thống). Chỉ có tỷ lệ trên hàng nghìn lượt mới nói lên điều gì.

   Mặc định chạy ở MỨC 1 (chỉ gắn nhãn để soi), KHÔNG tự cắt tiền ai. Đúng bài học 28/09:
   lớp nhận biết mới phải đo vài ngày trước khi cho nó đụng vào tiền.
   ============================================================ */

/** Chuỗi Chrome rút gọn — mọi máy Android đều gửi đúng chuỗi này. */
function sitetop_ua_chuoi_rut_gon() { return 'Android 10; K)'; }

/** 0 = tắt · 1 = chỉ gắn nhãn · 2 = không trả thưởng cho tài khoản bị gắn cờ. */
function sitetop_ua_bot_muc() { return (int) sitetop_get_option( 'ua_bot_muc', 1 ); }

/**
 * Quét toàn bộ tài khoản, tính tỷ lệ khai tên máy trong 14 ngày, lưu vào user meta.
 * Chạy trong cron giờ — một câu gộp cho tất cả, không lặp từng user.
 */
function sitetop_do_ua_khai_may() {
    global $wpdb;
    $p   = $wpdb->prefix . SITETOP_PREFIX;
    $ngay   = max( 3, (int) sitetop_get_option( 'ua_bot_ngay', 14 ) );
    $toi_thieu = max( 100, (int) sitetop_get_option( 'ua_bot_luot', 1000 ) );
    $rut = sitetop_ua_chuoi_rut_gon();

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT user_id,
                COUNT(*) AS luot,
                SUM( user_agent LIKE %s AND LOCATE(%s, user_agent) = 0 ) AS khai
           FROM {$p}shortlink_visits
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND user_id > 0
          GROUP BY user_id
         HAVING luot >= %d",
        '%Android%', $rut, $ngay, $toi_thieu
    ) );
    if ( ! is_array( $rows ) ) return 0;

    $n = 0;
    foreach ( $rows as $r ) {
        $luot = (int) $r->luot;
        if ( $luot < 1 ) continue;
        $ty_le = round( 100 * (int) $r->khai / $luot, 1 );
        update_user_meta( (int) $r->user_id, 'sitetop_ua_khai_may', array(
            'ty_le' => $ty_le,
            'luot'  => $luot,
            'ngay'  => $ngay,
            'luc'   => sitetop_current_time(),
        ) );
        $n++;
    }
    update_option( 'sitetop_ua_khai_may_luc', sitetop_current_time(), false );
    return $n;
}

/** Tài khoản này có đang bị gắn cờ không. Trả về mảng số liệu, hoặc false. */
function sitetop_ua_bot_co_co( $user_id ) {
    if ( sitetop_ua_bot_muc() < 1 ) return false;
    $d = get_user_meta( (int) $user_id, 'sitetop_ua_khai_may', true );
    if ( ! is_array( $d ) || ! isset( $d['ty_le'] ) ) return false;
    $nguong = (float) sitetop_get_option( 'ua_bot_nguong', 90 );
    $luot   = max( 100, (int) sitetop_get_option( 'ua_bot_luot', 1000 ) );
    if ( (float) $d['ty_le'] < $nguong || (int) $d['luot'] < $luot ) return false;
    return $d;
}

add_action( 'sitetop_hourly_cron', function () {
    /* Chạy 6 giờ một lần, không phải giờ nào cũng chạy: câu quét 14 ngày khá nặng và con số
       này gần như không đổi trong vài giờ. */
    $truoc = strtotime( (string) get_option( 'sitetop_ua_khai_may_luc', '' ) );
    if ( $truoc && ( time() - $truoc ) < 6 * HOUR_IN_SECONDS ) return;
    if ( sitetop_ua_bot_muc() < 1 ) return;
    sitetop_do_ua_khai_may();
}, 20 );
