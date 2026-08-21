<?php
/**
 * SiteTop.net V2 - Shortlink Verification & Balance
 * CLAUDE.md Flow 1: taskify_verify_and_pay() exact validation order
 * 
 * SOURCE OF TRUTH: transactions table
 * KHÔNG cộng refund_amount (double-counting prevention)
 * Session: uses session_id (32-char)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   BRIDGE CODE RESCUE (camp cầu nối từ site nguồn)
   ============================================================ */

/**
 * Camp cầu nối = job đẩy sang từ site nguồn (dethitoanthpt.com…) qua plugin ttp-lentop-bridge.
 * Nhận diện KHÔNG phụ thuộc plugin: tiền tố tiêu đề '[host#ref]' (gắn cố định lúc tạo job,
 * update job không đổi title) — dự phòng thêm map ref↔campaign của plugin (option ttplb_map).
 */
function sitetop_is_bridge_campaign( $campaign_id, $camp_title = '' ) {
    if ( '' !== (string) $camp_title && preg_match( '/^\[[^#\]]+#\d+\]/', (string) $camp_title ) ) {
        return true;
    }
    $map = get_option( 'ttplb_map', array() );
    if ( is_array( $map ) ) {
        foreach ( $map as $cid ) {
            if ( (int) $cid === (int) $campaign_id ) return true;
        }
    }
    return false;
}

/**
 * Cứu "Code chưa sẵn sàng"/mất thưởng oan cho visit camp cầu nối — cùng cơ chế ttplb_promote_code
 * của plugin nhưng nằm trong theme (auto-deploy, không phụ thuộc bản plugin trên server).
 *
 * Dùng chính MÃ khách nhập làm khoá liên kết: nếu một visit CÙNG CHIẾN DỊCH đang giữ đúng mã đó
 * (chưa verified, chưa trả thưởng, trong 30 phút — gồm cả CHÍNH phiên khách khi chỉ rớt/hết hạn
 * transient) thì chuyển mã + cờ from_google/url_matched + created_at sang phiên khách và arm lại
 * transient dưới đủ 3 tiền tố như ttplb_core_set_ready — giữ nguyên marker miễn-captcha của
 * visit cầu nối. Mã là chuỗi ngẫu nhiên duy nhất nên xác định đúng visit nguồn; ràng buộc cùng
 * campaign để chỉ hoàn tất đúng chiến dịch khách đang làm. Mọi check tiền phía sau chạy như cũ.
 * Mutate $visit tại chỗ để các check trong request hiện tại dùng giá trị mới.
 */
function sitetop_bridge_rescue_code( $visit, $session_id, $code ) {
    global $wpdb;
    $p    = $wpdb->prefix . 'sitetop_';
    $code = trim( (string) $code );
    if ( strlen( $code ) < 4 || empty( $visit->campaign_id ) ) return;
    if ( ! sitetop_is_bridge_campaign( $visit->campaign_id, $visit->camp_title ?? '' ) ) return;

    // Phiên đã giữ đúng mã + transient còn sống → không cần cứu.
    if ( ! empty( $visit->verify_code )
         && 0 === strcasecmp( (string) $visit->verify_code, $code )
         && get_transient( 'sitetop_widget_code_ready_' . $session_id ) ) {
        return;
    }

    $now = sitetop_current_time();
    $vx  = $wpdb->get_row( $wpdb->prepare(
        "SELECT verify_code, created_at FROM {$p}shortlink_visits
         WHERE campaign_id = %d AND step != 'verified' AND reward_paid = 0
           AND UPPER(verify_code) = UPPER(%s)
           AND created_at > DATE_SUB(%s, INTERVAL 30 MINUTE)
         ORDER BY id DESC LIMIT 1",
        (int) $visit->campaign_id, $code, $now
    ) );
    if ( ! $vx ) {
        // 13/07/2026 — RESCUE V2: plugin bridge ĐỜI CŨ có endpoint widget nhưng CHỈ set transient
        // (không ghi verify_code vào DB) và dùng tiền tố lentop_/trafficop_ → tra DB ở trên trượt.
        // Mã của CHÍNH phiên này nằm trong transient {pfx}verify_code_{sid} — chỉ plugin/theme
        // (server-side) set được, client không giả được → khớp mã khách nhập là đủ bằng chứng.
        // created_at GIỮ NGUYÊN (không nới time-gate).
        foreach ( array( 'lentop_', 'trafficop_', 'sitetop_' ) as $pfx ) {
            $t = get_transient( $pfx . 'verify_code_' . $session_id );
            if ( is_string( $t ) && '' !== $t && 0 === strcasecmp( $t, $code ) ) {
                $vx = (object) array( 'verify_code' => $t, 'created_at' => $visit->created_at );
                break;
            }
        }
        if ( ! $vx ) return;
    }

    $wpdb->update( "{$p}shortlink_visits", array(
        'verify_code' => $vx->verify_code,
        'from_google' => 1,
        'url_matched' => 1,
        'step'        => 'code_shown',
        'created_at'  => $vx->created_at,
    ), array( 'session_id' => $session_id ) );

    $expiry = max( 60, (int) sitetop_get_option( 'verify_code_expiry', 600 ) );
    foreach ( array( 'lentop_', 'trafficop_', 'sitetop_' ) as $pfx ) {
        set_transient( $pfx . 'widget_code_ready_' . $session_id, 1, $expiry );
        set_transient( $pfx . 'verify_code_' . $session_id, $vx->verify_code, $expiry );
    }

    $visit->verify_code = $vx->verify_code;
    $visit->from_google = 1;
    $visit->url_matched = 1;
    $visit->step        = 'code_shown';
    $visit->created_at  = $vx->created_at;
}

/* ============================================================
   VERIFY AND PAY (Flow 1 - exact order from CLAUDE.md)
   ============================================================ */

function sitetop_verify_and_pay( $session_id, $code ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $ip = sitetop_get_real_ip();
    // 13/07/2026 — IP TEST/ADMIN: miễn các guard theo IP (đổi IP, trần thưởng/ngày, trùng camp)
    // để admin test full flow; lượt vẫn tính tiền như khách thật (charge customer + reward).
    $is_test_wl = function_exists( 'sitetop_is_test_whitelisted' ) && sitetop_is_test_whitelisted( $ip );

    // ── PRE-TRANSACTION VALIDATION (exact order) ──

    // Line 248: IP block check
    if ( sitetop_is_ip_blocked( $ip ) ) {
        return new WP_Error( 'ip_blocked', 'IP bị chặn' );
    }

    // Line 256: Find visit by session_id
    $visit = $wpdb->get_row( $wpdb->prepare(
        "SELECT v.*, kc.price_per_view, kc.user_reward as camp_user_reward,
                kc.onsite_time as camp_onsite, kc.traffic_type,
                kc.customer_id, kc.daily_traffic, kc.status as camp_status,
                kc.fixed_code, kc.id as camp_id, kc.order_id as camp_order_id,
                kc.campaign_type, kc.title as camp_title,
                sl.original_url, sl.id as sl_id
         FROM {$p}shortlink_visits v
         LEFT JOIN {$p}keyword_campaigns kc ON v.campaign_id = kc.id
         LEFT JOIN {$p}user_shortlinks sl ON v.shortlink_id = sl.id
         WHERE v.session_id = %s", $session_id
    ));
    if ( ! $visit ) return new WP_Error( 'not_found', 'Session không tồn tại' );

    // Line 266: Check reward_paid
    if ( $visit->reward_paid ) {
        return new WP_Error( 'already_used', 'Đã xác minh', array( 'target_url' => $visit->original_url ) );
    }

    // Line 271: User banned check
    if ( $visit->user_id > 0 && get_user_meta( $visit->user_id, 'sitetop_banned', true ) ) {
        return new WP_Error( 'banned', 'Tài khoản bị khóa' );
    }

    // CỨU MÃ CAMP CẦU NỐI (port ttplb_promote_code của plugin vào theme — chạy được cả khi plugin
    // trên server là bản cũ). Mã camp cầu nối do widget SITE NGUỒN cấp qua proxy HMAC; khi
    // session-match trượt (ITP chặn iframe bridge) + IP-match trượt (dual-stack v4/v6), pool bind
    // mã vào visit KHÁC cùng campaign (domain-fallback), hoặc transient hết hạn/rớt cache → phiên
    // thật của khách báo "Code chưa sẵn sàng" dù làm đúng. Chuyển mã + cờ + mốc giờ về phiên khách
    // rồi arm lại transient. Phải chạy TRƯỚC age/time check vì có thể chuyển created_at.
    sitetop_bridge_rescue_code( $visit, $session_id, $code );

    $created_at = strtotime( $visit->created_at );
    $now = strtotime( sitetop_current_time() );
    $elapsed = $now - $created_at;
    $visit_expiry = function_exists('sitetop_get_visit_expiry_seconds') ? sitetop_get_visit_expiry_seconds() : 600;
    if ( $elapsed > $visit_expiry ) return new WP_Error( 'expired', 'Phiên đã hết hạn' );

    // Line 294-329: Campaign checks
    // is_nocode is determined STRICTLY by traffic_type (consistent with widget_verify_access).
    // Do NOT infer nocode from a non-empty fixed_code — a stray fixed_code on a 1step/2step
    // campaign must not disable the time check / Google check (countdown-bypass guard).
    $is_nocode = ( $visit->traffic_type === 'nocode' );
    $should_pay_reward = true;
    $should_pay_customer = true;
    $skip_reasons = array();

    if ( ! $visit->camp_id ) {
        $should_pay_reward = false;
        $should_pay_customer = false;
        $skip_reasons[] = 'no_campaign';
    } elseif ( $visit->camp_status !== 'active' ) {
        $should_pay_reward = false;
        $should_pay_customer = false;
        $skip_reasons[] = 'campaign_inactive';
    }

    // Line 340-380: TIME CHECK (skip for nocode)
    if ( ! $is_nocode ) {
        $onsite = (int) ( $visit->camp_onsite ?? $visit->onsite_time ?? 70 );
        $min_required = max( $onsite - 5, 10 );

        if ( $elapsed < $min_required ) {
            return new WP_Error( 'too_fast', 'Chưa đủ thời gian', array(
                'remaining' => $min_required - $elapsed,
            ));
        }

        // Code ready transient check
        if ( ! get_transient( 'sitetop_widget_code_ready_' . $session_id ) ) {
            return new WP_Error( 'code_not_ready', 'Code chưa sẵn sàng' );
        }
    }

    // Line 399-441: TRAFFIC-SPECIFIC CHECKS
    // Determine campaign_type (keyword_search / traffic_direct / traffic_social)
    $campaign_type = $visit->campaign_type ?? 'keyword_search';
    // Fallback: infer from task_type if available
    if ( $campaign_type === 'keyword_search' && $visit->camp_order_id ) {
        $order_task_type = $wpdb->get_var( $wpdb->prepare(
            "SELECT task_type FROM {$p}customer_orders WHERE id = %d", $visit->camp_order_id
        ));
        if ( $order_task_type ) $campaign_type = $order_task_type;
    }

    if ( $campaign_type === 'keyword_search' && ! $is_nocode ) {
        // keyword_search 1step/2step: from_google + url_matched
        if ( ! $visit->from_google || ! $visit->url_matched ) {
            $should_pay_reward = false;
            $skip_reasons[] = 'google_check_failed';
        }
    } elseif ( $campaign_type === 'keyword_search' && $is_nocode ) {
        // keyword_search nocode: chỉ check from_google (track từ page-unlock), bỏ url_matched (cần widget)
        if ( ! $visit->from_google ) {
            $should_pay_reward = false;
            $skip_reasons[] = 'google_check_failed';
        }
    } elseif ( $campaign_type === 'traffic_social' && ! $is_nocode ) {
        // traffic_social: url_matched only (no source check)
        if ( ! $visit->url_matched ) {
            $should_pay_reward = false;
            $skip_reasons[] = 'url_not_matched';
        }
    } elseif ( $campaign_type === 'traffic_direct' && ! $is_nocode ) {
        // traffic_direct: url_matched only
        if ( ! $visit->url_matched ) {
            $should_pay_reward = false;
            $skip_reasons[] = 'url_not_matched';
        }
    }

    // Line 444-478: CODE CHECK
    if ( $is_nocode ) {
        // Case-SENSITIVE comparison with fixed_code
        $fixed_code = $visit->fixed_code ?? '';
        if ( empty( $fixed_code ) ) {
            return new WP_Error( 'no_fixed_code', 'Mã cố định chưa được cấu hình.' );
        }
        if ( $code !== $fixed_code ) {
            return new WP_Error( 'wrong_code', 'Mã không đúng (case-sensitive)' );
        }
    } else {
        // Case-INSENSITIVE
        if ( strtoupper( $code ) !== strtoupper( $visit->verify_code ) ) {
            return new WP_Error( 'wrong_code', 'Mã xác minh không đúng' );
        }
        // Code expiry: 10 min transient
        $cached = get_transient( 'sitetop_verify_code_' . $session_id );
        if ( $cached === false ) {
            return new WP_Error( 'code_expired', 'Mã đã hết hạn (10 phút)' );
        }
    }

    // Line 551-602: IP CHECKS
    // Check pre-marked ip_changed flag first (from previous steps)
    $ip_changed = false;
    if ( ! $is_test_wl && (int) ( $visit->ip_changed ?? 0 ) === 1 ) {
        $ip_changed = true;
        $should_pay_reward = false;
        $skip_reasons[] = 'ip_changed_premarked';
    } elseif ( ! $is_test_wl && $ip !== ( $visit->original_ip ?? $visit->ip_address ) ) {
        $ip_changed = true;
        $should_pay_reward = false;
        $skip_reasons[] = 'ip_changed';
    }

    // Daily IP change block: if IP had ip_changed=1 on any verified visit today → block
    if ( ! $is_test_wl && ! $ip_changed ) {
        $today_check = date( 'Y-m-d', strtotime( sitetop_current_time() ) );
        $ip_changed_today = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}shortlink_visits
             WHERE ip_address = %s AND ip_changed = 1 AND step = 'verified' AND DATE(created_at) = %s",
            $ip, $today_check
        ));
        if ( $ip_changed_today > 0 ) {
            $should_pay_reward = false;
            $skip_reasons[] = 'ip_changed_daily_block';
        }
    }

    $today = date( 'Y-m-d', strtotime( sitetop_current_time() ) ); // các guard theo NGÀY bên dưới còn dùng

    /* TRẦN TÍNH TIỀN VIEW: 1 shortlink = 1 view, tối đa 2 view/IP/24 GIỜ TRƯỢT.
       Xem sitetop_ip_view_quota() để biết vì sao phải đếm theo SHORTLINK chứ không đếm
       số lượt hoàn thành. Hai nhánh tách riêng để đọc log biết user bị chặn vì lý do nào.

       Chỉ tắt trả thưởng cho USER. KHÔNG đụng $should_pay_customer: lượt này vẫn tính
       vào traffic/ngày và vẫn trừ tiền khách — đúng yêu cầu "view không cộng tiền user
       nhưng vẫn cộng vào lượt đã chạy của camp". */
    $quota = sitetop_ip_view_quota( $ip, (int) ( $visit->shortlink_id ?? 0 ) );
    if ( ! $is_test_wl && $quota['same_link'] ) {
        $should_pay_reward = false;
        $skip_reasons[] = 'shortlink_already_counted';
    } elseif ( ! $is_test_wl && $quota['used'] >= $quota['limit'] ) {
        $should_pay_reward = false;
        $skip_reasons[] = 'ip_limit_exceeded';
    }

    /* Cùng IP làm lại CÙNG MỘT CAMP trong ngày.
       19/08/2026 — chủ site chốt: lượt này vẫn cộng vào lượt hoàn thành của camp VÀ
       vẫn trừ tiền khách hàng; chỉ USER là không được cộng tiền. Trước đây chỗ này
       tắt luôn $should_pay_customer (khách không bị trừ) — đã bỏ dòng đó.
       Cùng ranh giới với trần view ở trên: mọi guard chống trùng chỉ chạm tiền của user.
       (IP test/admin được miễn — cho phép test lại cùng camp, lượt vẫn tính tiền đủ.) */
    if ( ! $is_test_wl && $visit->campaign_id ) {
        $ip_camp_today = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}shortlink_visits
             WHERE ip_address = %s AND campaign_id = %d
             AND step = 'verified' AND DATE(created_at) = %s
             AND id != %d",
            $ip, $visit->campaign_id, $today, $visit->id
        ));
        if ( $ip_camp_today > 0 ) {
            $should_pay_reward = false;
            $skip_reasons[] = 'ip_repeat_same_campaign';
        }
    }

    // Line 605: Adblock check
    if ( $visit->adblock_detected ) {
        $should_pay_reward = false;
        $skip_reasons[] = 'adblock';
    }

    // Cap1: server-side Turnstile gate. ONLY active when admin has fully configured Turnstile
    // (enabled + site_key + secret_key) — default behavior unchanged. The captcha iframe verifies
    // the token server-side (action sitetop_widget_captcha) and sets sitetop_captcha_ok_{sid}.
    // No valid transient = captcha not solved server-side (bot skipping the iframe) → no reward.
    //
    // NGOẠI LỆ visit CẦU NỐI: camp đẩy sang từ site nguồn (vd dethitoanthpt.com) dùng widget CỦA
    // NGUỒN trên trang đích; mã được cấp server-side qua plugin ttp-lentop-bridge (HMAC) — iframe
    // captcha của ta không tồn tại trong flow đó nên captcha_ok không bao giờ được set → mọi visit
    // cầu nối bị chặn thưởng oan ('captcha_unverified') dù khách hàng vẫn bị trừ tiền. Nhận diện
    // qua transient 'lentop_/trafficop_widget_code_ready_{sid}': CHỈ plugin bridge ghi 2 tiền tố
    // này khi cấp mã (ttplb_core_set_ready) — theme không bao giờ set chúng, client không thể giả.
    // TTL marker = TTL mã; verify đã bắt buộc mã còn hạn (code_expired ở trên) → marker còn sống.
    /* PHẢI đọc CÙNG công tắc mà widget dùng để quyết định có hiện captcha hay không.
       Trước đây chỗ này đọc turnstile_enabled, còn widget đọc widget_captcha_enabled
       (tách ra ngày 14/08/2026 để bật Turnstile cho trang đăng nhập mà không cắm captcha
       vào giữa luồng lấy mã). Hai cờ lệch nhau là hỏng nặng:
         turnstile_enabled=1 + widget_captcha_enabled=0
           → widget KHÔNG hiện captcha nên transient captcha_ok không bao giờ được ghi
           → MỌI user hoàn thành nhiệm vụ đều bị 'captcha_unverified' và mất thưởng,
             trong khi khách hàng vẫn bị trừ tiền. Hỏng âm thầm, không ai báo lỗi gì.
       Đọc chung một cờ thì tắt/bật kiểu nào hai bên cũng luôn khớp. */
    if ( sitetop_get_option( 'widget_captcha_enabled', 1 )
         && sitetop_get_option( 'turnstile_site_key', '' )
         && sitetop_get_option( 'turnstile_secret_key', '' ) ) {
        $captcha_ok      = (bool) get_transient( 'sitetop_captcha_ok_' . $session_id );
        $bridged_code    = get_transient( 'lentop_widget_code_ready_' . $session_id )
                        || get_transient( 'trafficop_widget_code_ready_' . $session_id );
        if ( ! $captcha_ok && ! $bridged_code ) {
            $should_pay_reward = false;
            /* KHÔNG XÁC MINH ĐƯỢC LÀ NGƯỜI THẬT -> KHÔNG THU TIỀN KHÁCH, KHÔNG CỘNG VIEW.
               Trước đây cổng này chỉ cắt thưởng user mà VẪN trừ tiền khách: đo thật bằng
               bot không giải captcha -> số dư khách 500.000 tụt còn 499.000, camp completed
               +1, trong khi user không nhận đồng nào. Khách trả tiền cho lượt mà chính hệ
               thống đã đánh dấu là không xác minh được, và nhìn báo cáo tưởng camp chạy tốt.
               Kẻ phá hoại nhắm một khách cụ thể có thể đốt sạch ngân sách của họ bằng bot
               mà không cần giải captcha lần nào.
               Cộng view chiến dịch nằm trong `if ( $visit->camp_id && $customer_paid )`
               (~dòng 596) nên chặn trừ tiền là view tự động không cộng — không cần sửa
               thêm chỗ nào khác. */
            $should_pay_customer = false;
            $skip_reasons[] = 'captcha_unverified';
        }
    }

    // Line 622: Bypass check - 3-zone system from production:
    // Zone 1 (elapsed < onsite_time - 5): BLOCKED by time check above
    // Zone 2 (onsite_time - 5 <= elapsed < onsite_time): Verify OK, NO reward
    // Zone 3 (elapsed >= onsite_time): Verify OK + reward
    $is_bypass = false;
    $verify_traffic_type = $visit->traffic_type ?? '1step';
    if ( $should_pay_reward && $verify_traffic_type !== 'nocode' ) {
        $onsite = (int) ( $visit->camp_onsite ?? $visit->onsite_time ?? 70 );
        if ( $elapsed < $onsite ) {
            $is_bypass = true;
            $should_pay_reward = false;
            $skip_reasons[] = 'bypass_detected';
        }
    }

    /* CHỐNG TUA NHANH ĐỒNG HỒ — phạt lúc trả thưởng, không chặn lúc cấp mã.
       sitetop_get_widget_code() đếm số lần phiên này đòi mã KHI CHƯA ĐỦ GIỜ. Đồng hồ
       trong trình duyệt chỉ để hiển thị; mốc thật là created_at trong DB, nên tua giờ
       không lấy được mã sớm — nhưng nó để lại dấu vết là một chuỗi lần đòi hụt.
       Người thật gần như luôn = 0 (widget đếm 70s, server chỉ đòi 65s → luôn dư 5s).
       Ngưỡng mặc định 5 để chừa biên rất rộng cho lệch đồng hồ/mạng chậm.
       Cố ý KHÔNG chặn cấp mã: chặn thì kẻ gian dò ra ngay ngưỡng rồi lách; để họ lấy
       được mã nhưng không có tiền thì họ không biết mình đã lộ — giống vùng 2 ở trên.
       Đặt 0 để tắt hẳn lớp này. Thiếu transient (cache bị xoá) → đếm = 0 → KHÔNG phạt,
       tức là hỏng theo hướng an toàn cho người dùng thật. */
    if ( $should_pay_reward ) {
        $tm_limit = (int) sitetop_get_option( 'timer_manip_limit', 5 );
        if ( $tm_limit > 0 ) {
            $tm_cnt = (int) get_transient( 'sitetop_toofast_' . $session_id );
            if ( $tm_cnt >= $tm_limit ) {
                $should_pay_reward = false;
                $skip_reasons[] = 'timer_manipulation';
            }
        }
    }

    // Line 639-675: Daily traffic limit
    if ( $visit->camp_id && $visit->daily_traffic > 0 ) {
        $daily_completed = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}shortlink_visits
             WHERE campaign_id = %d AND step = 'verified' AND DATE(created_at) = %s",
            $visit->camp_id, $today
        ));
        if ( $daily_completed >= $visit->daily_traffic ) {
            $should_pay_reward = false;
            $should_pay_customer = false;
            $skip_reasons[] = 'daily_limit_reached';
        }
    }

    // Line 681-748: Customer balance check (2-layer invariant)
    if ( $visit->customer_id && $should_pay_customer ) {
        $cust_balance = sitetop_get_customer_balance_amount( $visit->customer_id );
        if ( $cust_balance === false ) {
            $should_pay_customer = false;
            $skip_reasons[] = 'customer_balance_error';
        } else {
            $min_balance = (int) sitetop_get_option( 'customer_min_balance', 20000 );
            $cost = (float) $visit->price_per_view;
            $required = $min_balance + max( $cost, 5000 );

            if ( $cust_balance <= $min_balance ) {
                $should_pay_customer = false;
                $should_pay_reward = false;
                $skip_reasons[] = 'customer_insufficient';
                sitetop_auto_pause_customer_campaigns( $visit->customer_id );
                if ( $cust_balance <= 0 ) {
                    error_log( "Customer balance <= 0: customer_id={$visit->customer_id}, balance={$cust_balance}" );
                }
            } elseif ( $cust_balance <= $required ) {
                $should_pay_customer = false;
                $should_pay_reward = false;
                $skip_reasons[] = 'customer_insufficient';
                sitetop_auto_pause_customer_campaigns( $visit->customer_id );
            }
        }
    }

    // ── DATABASE TRANSACTION (Line 809-1050) ──
    $wpdb->query( 'START TRANSACTION' );
    try {
        // Line 814: LOCK visit FOR UPDATE → re-check
        $locked = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$p}shortlink_visits WHERE session_id = %s FOR UPDATE",
            $session_id
        ));
        if ( ! $locked || $locked->reward_paid || $locked->step === 'verified' ) {
            $wpdb->query( 'ROLLBACK' );
            // Graceful: concurrent request already paid - return success for redirect
            return array(
                'success'    => true,
                'target_url' => $visit->original_url,
                'reward'     => 0,
                'paid'       => false,
            );
        }

        /* RECHECK hạn mức view NGAY TRONG transaction. Kiểm ở ngoài là chưa đủ: hai tab
           cùng bấm xác minh một lúc thì cả hai đều đọc thấy quota còn trống rồi cùng trả
           thưởng — đúng cái "1 shortlink hoàn thành 2 lần = 2 view" cần chặn. */
        if ( ! $is_test_wl && $should_pay_reward ) {
            $q2 = sitetop_ip_view_quota( $ip, (int) ( $visit->shortlink_id ?? 0 ) );
            if ( ! $q2['allowed'] ) {
                $should_pay_reward = false;
                $skip_reasons[] = $q2['same_link'] ? 'shortlink_already_counted' : 'ip_limit_exceeded';
            }
        }

        // Line 836: RECHECK daily limit INSIDE transaction (race condition)
        if ( $visit->camp_id && $visit->daily_traffic > 0 ) {
            $recheck_daily = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$p}shortlink_visits
                 WHERE campaign_id = %d AND step = 'verified' AND DATE(created_at) = %s",
                $visit->camp_id, $today
            ));
            if ( $recheck_daily >= $visit->daily_traffic ) {
                $should_pay_reward = false;
                $should_pay_customer = false;
            }
        }

        // Line 882-911: Customer payment (2-layer invariant)
        $customer_paid = false;
        if ( $should_pay_customer && $visit->customer_id && $visit->price_per_view > 0 ) {
            $cost = absint( $visit->price_per_view );
            $min_balance = (int) sitetop_get_option( 'customer_min_balance', 20000 );
            $required = $min_balance + max( (float) $cost, 5000 );

            // Lock the customer_balance row to serialize concurrent charges.
            $cbal = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$p}customer_balance WHERE user_id = %d FOR UPDATE",
                $visit->customer_id
            ));

            // Use SOURCE-OF-TRUTH balance (deposits + customer_transactions), NOT the
            // drift-prone cache field. Sync the cache to the real value under the lock so the
            // atomic deduction guard below operates on the true balance (prevents charging
            // against an inflated cache → free traffic for the customer).
            $real_balance = sitetop_get_customer_balance_amount( $visit->customer_id );
            if ( $real_balance === false ) {
                // SQL error — do not charge against an unknown balance.
                $should_pay_customer = false;
                $should_pay_reward = false;
            } else {
                sitetop_sync_customer_balance( $visit->customer_id );
                $actual = (float) $real_balance;
                if ( $actual <= $min_balance || $actual <= $required ) {
                    $should_pay_customer = false;
                    $should_pay_reward = false;
                    sitetop_auto_pause_customer_campaigns( $visit->customer_id );
                }
            }

            if ( $should_pay_customer && $cbal ) {

                // Atomic deduct WITH balance>=cost guard (safety net vs drift/race).
                $deducted = $wpdb->query( $wpdb->prepare(
                    "UPDATE {$p}customer_balance SET balance = balance - %d, total_spent = total_spent + %d, updated_at = %s WHERE user_id = %d AND balance >= %d",
                    $cost, $cost, sitetop_current_time(), $visit->customer_id, $cost
                ));

                if ( ! $deducted ) {
                    // Insufficient at the atomic layer — do not pay either side.
                    $should_pay_customer = false;
                    $should_pay_reward = false;
                    sitetop_auto_pause_customer_campaigns( $visit->customer_id );
                } else {
                    // Log customer_transaction (type='campaign_view'); balance_after from
                    // source-of-truth value after deduction.
                    $new_cbal = $actual - $cost;
                    $wpdb->insert( "{$p}customer_transactions", array(
                        'customer_id' => $visit->customer_id,
                        'type' => 'campaign_view', 'amount' => -$cost,
                        'reference_id' => $locked->id, 'reference_type' => 'visit',
                        'description' => "View campaign #{$visit->camp_id}",
                        'balance_after' => $new_cbal,
                        'created_at' => sitetop_current_time(),
                    ));

                    $customer_paid = true;

                    // Line 919: Update order.amount_spent
                    if ( $visit->camp_order_id ) {
                        $wpdb->query( $wpdb->prepare(
                            "UPDATE {$p}customer_orders SET amount_spent = amount_spent + %d, completed = completed + 1, updated_at = %s WHERE id = %d",
                            $cost, sitetop_current_time(), $visit->camp_order_id
                        ));
                    }

                    if ( $new_cbal <= $required ) {
                        sitetop_auto_pause_customer_campaigns( $visit->customer_id );
                    }
                }
            } else {
                $should_pay_reward = false;
            }
        }

        // Line 992: User reward
        // Only pay user when customer actually paid (or non-campaign shortlink)
        $reward_amount = 0;
        $user_paid = false;
        if ( $should_pay_reward && $visit->user_id > 0 ) {
            $can_pay_user = ! $visit->camp_id || $customer_paid;

            if ( $can_pay_user ) {
                // Determine reward (Flow 8)
                $camp_obj = (object) array(
                    'user_reward' => $visit->camp_user_reward,
                    'traffic_type' => $visit->traffic_type,
                    'campaign_type' => $visit->campaign_type ?? 'keyword_search',
                );
                $reward_amount = sitetop_get_reward_amount( $camp_obj );

                // Add user balance + transaction
                sitetop_add_user_balance( $visit->user_id, $reward_amount, 'shortlink_reward',
                    'Thưởng shortlink #' . $locked->id, $locked->id, 'visit' );
                $user_paid = true;
            } else {
                $should_pay_reward = false;
                $skip_reasons[] = 'customer_not_paid';
            }
        }

        // Line 997: Update shortlink stats (only when user actually paid)
        if ( $visit->sl_id && $user_paid ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$p}user_shortlinks SET total_completed = total_completed + 1, total_earnings = total_earnings + %d WHERE id = %d",
                $reward_amount, $visit->sl_id
            ));
        }

        // Line 1019-1027: Campaign/order counters (only when customer paid)
        if ( $visit->camp_id && $customer_paid ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$p}keyword_campaigns SET completed = completed + 1 WHERE id = %d", $visit->camp_id
            ));
        }

        // Line 1040: Update visit
        $visit_update = array(
            'step'            => 'verified',
            'verified_at'     => sitetop_current_time(),
            'reward_paid'     => ( $should_pay_reward && $user_paid ) ? 1 : 0,
            'customer_paid'   => $customer_paid ? 1 : 0,
            'reward_amount'   => $user_paid ? $reward_amount : 0,
            'completion_time' => $elapsed,
            'ip_changed'      => $ip_changed ? 1 : 0,
            'is_bypass'       => $is_bypass ? 1 : 0,
            'ip_limit_exceeded' => in_array( 'ip_limit_exceeded', $skip_reasons ) ? 1 : 0,
        );
        // Chỉ ghi skip_reasons khi migration xác nhận cột tồn tại (flag chỉ set khi SHOW COLUMNS
        // thấy cột) — ghi cột không tồn tại làm cả UPDATE fail SAU khi đã trừ tiền khách +
        // cộng thưởng user → visit không được đánh dấu verified → verify lại được (multi-pay).
        if ( get_option( 'sitetop_migration_skip_reasons_v2' ) ) {
            $visit_update['skip_reasons'] = ! empty( $skip_reasons ) ? wp_json_encode( $skip_reasons ) : null;
        }
        $wpdb->update( "{$p}shortlink_visits", $visit_update, array( 'session_id' => $session_id ) );

        // Line 1050: COMMIT
        $wpdb->query( 'COMMIT' );

        // Cleanup transients
        delete_transient( 'sitetop_widget_code_ready_' . $session_id );
        delete_transient( 'sitetop_verify_code_' . $session_id );
        delete_transient( 'sitetop_google_clicked_' . $session_id );
        delete_transient( 'sitetop_toofast_' . $session_id ); // bộ đếm chống tua giờ
        // Thu hồi giấy phép bàn giao: lượt đã xong thì không được dùng nó để gắn phiên
        // cho bất kỳ lần vào trang đích nào nữa.
        delete_transient( 'sitetop_handoff_' . $session_id );

        return array(
            'success'    => true,
            'target_url' => $visit->original_url,
            'reward'     => $user_paid ? $reward_amount : 0,
            'paid'       => $user_paid,
        );

    } catch ( Exception $e ) {
        $wpdb->query( 'ROLLBACK' );
        return new WP_Error( 'error', $e->getMessage() );
    }
}

/* ============================================================
   USER BALANCE - SOURCE OF TRUTH: transactions table
   Flow 8b from CLAUDE.md
   ============================================================ */

/**
 * Get available balance from transactions
 * type IN ('shortlink_reward', 'earn') for total_earned
 */
function sitetop_get_user_balance_amount( $user_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

    $total_earned = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE user_id=%d AND type IN ('shortlink_reward','earn')", $user_id ));
    $total_withdrawn = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}withdrawals WHERE user_id=%d AND status IN ('completed','cancelled')", $user_id ));
    $pending_wd = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}withdrawals WHERE user_id=%d AND status IN ('pending','approved')", $user_id ));
    $other_deductions = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE user_id=%d AND type='withdraw' AND (reference_type IS NULL OR reference_type != 'withdrawal')", $user_id ));

    return max( 0, $total_earned - $total_withdrawn - $pending_wd - abs($other_deductions) );
}

/**
 * Add user balance + insert transaction
 * 1. UPDATE balance, total_earned
 * 2. If 0 rows → INSERT IGNORE
 * 3. If INSERT skipped (race) → RETRY UPDATE
 */
function sitetop_add_user_balance( $user_id, $amount, $type = 'shortlink_reward', $description = '', $ref_id = null, $ref_type = null ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

    $amount = absint( $amount ); // VND integer

    // referral_commission có SỔ RIÊNG (xem includes/referral-management.php): rút riêng,
    // ngưỡng rút riêng (referral_min_payout), không gộp vào balance/total_earned chung ở
    // đây — gộp vào sẽ khiến ngưỡng rút riêng vô nghĩa (rút được qua nút thường, không qua
    // cổng 50k). Bảng transactions (INSERT bên dưới) vẫn ghi đủ — đó là sổ cái thật của nó;
    // chỉ 2 cột cache dùng cho luồng rút TIỀN NHIỆM VỤ là bị bỏ qua ở nhánh này.
    if ( $type !== 'referral_commission' ) {
        // 1. Try UPDATE
        $updated = $wpdb->query( $wpdb->prepare(
            "UPDATE {$p}user_balance SET balance = balance + %d, total_earned = total_earned + %d, updated_at = %s WHERE user_id = %d",
            $amount, $amount, sitetop_current_time(), $user_id
        ));

        // 2. If 0 rows → INSERT IGNORE
        if ( $updated === 0 ) {
            $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO {$p}user_balance (user_id, balance, total_earned, updated_at) VALUES (%d, %d, %d, %s)",
                $user_id, $amount, $amount, sitetop_current_time()
            ));

            // 3. Race condition → RETRY UPDATE
            if ( $wpdb->rows_affected === 0 ) {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$p}user_balance SET balance = balance + %d, total_earned = total_earned + %d, updated_at = %s WHERE user_id = %d",
                    $amount, $amount, sitetop_current_time(), $user_id
                ));
            }
        }
    }

    // Insert transaction record
    $balance_after = sitetop_get_user_balance_amount( $user_id );
    $wpdb->insert( "{$p}transactions", array(
        'user_id'        => $user_id,
        'type'           => $type,
        'amount'         => $amount,
        'description'    => sanitize_text_field( $description ),
        'reference_id'   => $ref_id,
        'reference_type' => $ref_type,
        'balance_after'  => $balance_after,
        'created_at'     => sitetop_current_time(),
    ));

    // Hook cho các module ăn theo sự kiện cộng số dư (VD: hoa hồng referral trong
    // includes/referral-management.php) mà không phải sửa logic quyết định thưởng ở trên.
    // Vô hại nếu không ai đăng ký hook — chỉ thêm một điểm mở rộng.
    do_action( 'sitetop_user_balance_added', $user_id, $amount, $type, $ref_id, $ref_type );

    return true;
}

/**
 * Sync user_balance cache from transactions (fix drift)
 */
function sitetop_sync_user_balance( $user_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

    $balance = sitetop_get_user_balance_amount( $user_id );
    $earned = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}transactions WHERE user_id=%d AND type IN ('shortlink_reward','earn')", $user_id ));

    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}user_balance WHERE user_id=%d", $user_id ) );
    $data = array( 'balance' => $balance, 'total_earned' => $earned, 'updated_at' => sitetop_current_time() );

    if ( $existing ) $wpdb->update( "{$p}user_balance", $data, array( 'user_id' => $user_id ) );
    else { $data['user_id'] = $user_id; $wpdb->insert( "{$p}user_balance", $data ); }
}

/* ============================================================
   CUSTOMER BALANCE - SOURCE OF TRUTH: deposits + customer_transactions
   CLAUDE.md: customer_balance uses user_id (NOT customer_id)
   Returns FALSE on SQL error (not 0) — callers must check!
   ============================================================ */

function sitetop_get_customer_balance_amount( $user_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

    // Column safety: verify user_id exists
    $has_uid = $wpdb->get_results( "SHOW COLUMNS FROM {$p}customer_balance LIKE 'user_id'" );
    if ( empty( $has_uid ) ) return false; // Safety

    $deposited = $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount + bonus_amount),0) FROM {$p}customer_deposits WHERE customer_id=%d AND status='approved'", $user_id ));
    if ( $deposited === null ) return false;

    $bonus = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}customer_transactions WHERE customer_id=%d AND type='bonus' AND amount > 0", $user_id ));
    $spent = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(ABS(SUM(amount)),0) FROM {$p}customer_transactions WHERE customer_id=%d AND type='campaign_view' AND amount < 0", $user_id ));
    $deductions = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(ABS(SUM(amount)),0) FROM {$p}customer_transactions WHERE customer_id=%d AND type='deduction' AND amount < 0", $user_id ));

    return max( 0, (float) $deposited + $bonus - $spent - $deductions );
}

function sitetop_sync_customer_balance( $user_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

    $balance = sitetop_get_customer_balance_amount( $user_id );
    if ( $balance === false ) return; // SQL error safety

    $deposited = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount + bonus_amount),0) FROM {$p}customer_deposits WHERE customer_id=%d AND status='approved'", $user_id ));
    $spent_views = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(ABS(SUM(amount)),0) FROM {$p}customer_transactions WHERE customer_id=%d AND type='campaign_view' AND amount < 0", $user_id ));
    $spent_admin = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(ABS(SUM(amount)),0) FROM {$p}customer_deposits WHERE customer_id=%d AND status='approved' AND amount < 0", $user_id ));
    $spent = $spent_views + $spent_admin;

    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}customer_balance WHERE user_id=%d", $user_id ) );
    $data = array( 'balance'=>$balance, 'total_deposited'=>$deposited, 'total_spent'=>$spent, 'updated_at'=>sitetop_current_time() );

    if ( $existing ) $wpdb->update( "{$p}customer_balance", $data, array( 'user_id' => $user_id ) );
    else { $data['user_id'] = $user_id; $wpdb->insert( "{$p}customer_balance", $data ); }
}

/** Auto-pause all campaigns of a customer */
function sitetop_auto_pause_customer_campaigns( $customer_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $now = sitetop_current_time();
    $wpdb->query( $wpdb->prepare( "UPDATE {$p}keyword_campaigns SET status='paused', updated_at=%s WHERE customer_id=%d AND status='active'", $now, $customer_id ) );
    $wpdb->query( $wpdb->prepare( "UPDATE {$p}customer_orders SET status='paused', updated_at=%s WHERE customer_id=%d AND status='active'", $now, $customer_id ) );

    // Invalidate eligible campaigns cache
    delete_transient( 'sitetop_eligible_campaigns' );
}
