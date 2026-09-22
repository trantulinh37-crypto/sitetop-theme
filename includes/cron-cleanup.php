<?php
/**
 * SiteTop.net V2 - Cron Cleanup & Counter Sync
 * SAFETY: NEVER delete financial data
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function sitetop_run_database_cleanup() {
    global $wpdb;
    $p = $wpdb->prefix . SITETOP_PREFIX;
    $now = sitetop_current_time();

    // Configurable retention (from settings, with safe defaults)
    $visit_days = (int) sitetop_get_option( 'cleanup_old_visits', 30 );
    $notif_days = (int) sitetop_get_option( 'cleanup_read_notifications', 30 );
    $behavior_days = (int) sitetop_get_option( 'cleanup_old_behavior', 14 );

    // Delete old unverified visits - SAFETY: NEVER delete reward_paid=1 or customer_paid=1
    if ( $visit_days > 0 ) {
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$p}shortlink_visits WHERE step != 'verified' AND reward_paid = 0 AND customer_paid = 0 AND created_at < DATE_SUB(%s, INTERVAL %d DAY)",
            $now, max( 2, $visit_days ) ));
    }

    // Delete old read notifications
    if ( $notif_days > 0 ) {
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$p}notifications WHERE is_read = 1 AND created_at < DATE_SUB(%s, INTERVAL %d DAY)", $now, $notif_days ));
    }

    // Delete old behavior analytics
    if ( $behavior_days > 0 ) {
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$p}behavior_analytics WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY)", $now, $behavior_days ));
    }

    // Expire old campaigns
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}keyword_campaigns SET status='expired', updated_at=%s WHERE status='active' AND end_date IS NOT NULL AND end_date < %s",
        $now, date('Y-m-d', strtotime($now)) ));

    // Unblock expired IP blocks
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}ip_reputation SET blocked=0 WHERE blocked=1 AND permanent_block=0 AND blocked_until < %s", $now ));

    // Delete unblocked IP reputation records >7 days — tích lũy không bound nếu giữ
    // Block flag được set lại khi IP visit nếu thuộc datacenter/VPN/proxy
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$p}ip_reputation WHERE blocked = 0 AND permanent_block = 0 AND checked_at < DATE_SUB(%s, INTERVAL 7 DAY)", $now ));

    // Delete orphan user_shortlinks (chưa từng click + >30 ngày) — test/abandon links
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$p}user_shortlinks WHERE total_clicks = 0 AND created_at < DATE_SUB(%s, INTERVAL 30 DAY)", $now ));

    // Delete old hourly adjustments (>7 days)
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$p}hourly_adjustments WHERE adjustment_date < DATE_SUB(%s, INTERVAL 7 DAY)", date('Y-m-d', strtotime($now)) ));

    // Cleanup expired transients (also runs separately every 5 min)
    sitetop_cleanup_expired_transients();

    // Cleanup old device fingerprints (>30 days)
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$p}device_fingerprints WHERE created_at < DATE_SUB(%s, INTERVAL 30 DAY)", $now ));

    // Cleanup old DDoS blocks (expired, not permanent)
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$p}ddos_blocks WHERE blocked_until < %s AND blocked_until IS NOT NULL", $now ));

    // Cleanup old IP reputation (no visits in 30 days, not blocked)
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$p}ip_reputation WHERE blocked = 0 AND updated_at < DATE_SUB(%s, INTERVAL 30 DAY)", $now ));

    // Sync counters to fix drift
    sitetop_sync_shortlink_counters();
    sitetop_sync_campaign_counters();
}

/**
 * Cleanup expired transients from wp_options.
 * DDoS creates 3 transients/IP/request (1s, 10s, 60s TTL) = 6 rows/IP in wp_options.
 * WordPress NEVER auto-deletes expired transients → table bloats fast.
 * Runs every 5 min via cron + inside daily cleanup.
 */
function sitetop_cleanup_expired_transients() {
    /* VIẾT LẠI 22/09/2026 — bản cũ là một lệnh DELETE tự nối bảng wp_options:
         DELETE a, b ... WHERE a.option_name LIKE '_transient_sitetop_%'
       Trong LIKE, dấu '_' là KÝ TỰ ĐẠI DIỆN; mẫu mở đầu bằng ký tự đại diện thì MySQL không
       dùng được chỉ mục option_name -> quét TOÀN BỘ bảng, và vì là DELETE nên khoá dần từng
       dòng đã quét tới khi xong. Chạy 5 phút/lần trên .net (~40.000 lượt/ngày, ~10 transient
       mỗi lượt, không object cache) -> mọi request cần đọc/ghi option phải đứng chờ: chủ site
       báo admin chuyển trang chậm, còn .one (dữ liệu nhỏ) thì không.

       Bản mới:
       1. Thoát '_' bằng esc_like -> MySQL quét ĐÚNG khoảng '_transient_timeout_sitetop_' trên
          chỉ mục, không chạm phần còn lại của bảng.
       2. Tách đọc và xoá: SELECT không khoá tìm tên khoá hết hạn, rồi DELETE theo đúng tên —
          mỗi lệnh chỉ khoá đúng những dòng sắp xoá, trong tích tắc.
       3. Chia lô 500, tối đa 20 lô mỗi lượt (10.000 transient / 5 phút): tồn đọng lớn cũng
          rút dần, không có lệnh nào kéo dài.
       Ngữ nghĩa giữ nguyên: chỉ xoá transient sitetop_ CÓ hạn và ĐÃ quá hạn. */
    global $wpdb;
    $mau     = $wpdb->esc_like( '_transient_timeout_sitetop_' ) . '%';
    $dau     = strlen( '_transient_timeout_' );
    $bay_gio = time();
    for ( $lo = 0; $lo < 20; $lo++ ) {
        $ten = $wpdb->get_col( $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options}
              WHERE option_name LIKE %s AND option_value < %d
              LIMIT 500", $mau, $bay_gio ) );
        if ( empty( $ten ) ) break;
        $xoa = array();
        foreach ( $ten as $t ) {
            $xoa[] = $t;                                   // _transient_timeout_sitetop_X
            $xoa[] = '_transient_' . substr( $t, $dau );   // _transient_sitetop_X
        }
        $cho = implode( ',', array_fill( 0, count( $xoa ), '%s' ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name IN ($cho)", $xoa ) );
        if ( count( $ten ) < 500 ) break;
    }
}

/* CẢNH BÁO 28/08/2026 — hai hàm dưới GHI ĐÈ các cột đếm. Điều kiện ở đây phải
   khớp với chỗ cộng trong sitetop_verify_and_pay(), nếu không mỗi lần cron chạy
   sẽ xoá sạch những lượt đã trả tiền mà chưa verified (user thấy mã nhưng không
   gõ). Đếm lượt = (verified HOẶC khách đã trả tiền); riêng total_earnings vẫn chỉ
   tính lượt thực sự trả thưởng — tuyệt đối không nới điều kiện của tiền. */

/** Recalculate shortlink counters (fix drift) */
function sitetop_sync_shortlink_counters() {
    global $wpdb;
    $p = $wpdb->prefix . SITETOP_PREFIX;

    $wpdb->query("UPDATE {$p}user_shortlinks sl SET
        total_clicks = (SELECT COUNT(*) FROM {$p}shortlink_visits WHERE shortlink_id = sl.id),
        total_completed = (SELECT COUNT(*) FROM {$p}shortlink_visits WHERE shortlink_id = sl.id AND (step = 'verified' OR customer_paid = 1)),
        total_earnings = COALESCE((SELECT SUM(reward_amount) FROM {$p}shortlink_visits WHERE shortlink_id = sl.id AND step = 'verified' AND reward_paid = 1), 0)");
}

/** Recalculate campaign counters */
function sitetop_sync_campaign_counters() {
    global $wpdb;
    $p = $wpdb->prefix . SITETOP_PREFIX;

    $wpdb->query("UPDATE {$p}keyword_campaigns kc SET
        completed = (SELECT COUNT(*) FROM {$p}shortlink_visits WHERE campaign_id = kc.id AND (step = 'verified' OR customer_paid = 1)),
        total_earnings = COALESCE((SELECT SUM(reward_amount) FROM {$p}shortlink_visits WHERE campaign_id = kc.id AND step = 'verified' AND reward_paid = 1), 0)");
}
