<?php
/**
 * Traffictop.net V2 - Cron Cleanup & Counter Sync
 * SAFETY: NEVER delete financial data
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function traffictop_run_database_cleanup() {
    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;
    $now = traffictop_current_time();

    // Configurable retention (from settings, with safe defaults)
    $visit_days = (int) traffictop_get_option( 'cleanup_old_visits', 30 );
    $notif_days = (int) traffictop_get_option( 'cleanup_read_notifications', 30 );
    $behavior_days = (int) traffictop_get_option( 'cleanup_old_behavior', 14 );

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

    // Delete old hourly adjustments (>7 days)
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$p}hourly_adjustments WHERE adjustment_date < DATE_SUB(%s, INTERVAL 7 DAY)", date('Y-m-d', strtotime($now)) ));

    // Cleanup expired transients (also runs separately every 5 min)
    traffictop_cleanup_expired_transients();

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
    traffictop_sync_shortlink_counters();
    traffictop_sync_campaign_counters();
}

/**
 * Cleanup expired transients from wp_options.
 * DDoS creates 3 transients/IP/request (1s, 10s, 60s TTL) = 6 rows/IP in wp_options.
 * WordPress NEVER auto-deletes expired transients → table bloats fast.
 * Runs every 5 min via cron + inside daily cleanup.
 */
function traffictop_cleanup_expired_transients() {
    global $wpdb;
    $wpdb->query(
        "DELETE a, b FROM {$wpdb->options} a
         LEFT JOIN {$wpdb->options} b ON b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
         WHERE a.option_name LIKE '_transient_traffictop_%'
         AND a.option_name NOT LIKE '_transient_timeout_%'
         AND b.option_value IS NOT NULL
         AND b.option_value < UNIX_TIMESTAMP()"
    );
}

/** Recalculate shortlink counters (fix drift) */
function traffictop_sync_shortlink_counters() {
    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;

    $wpdb->query("UPDATE {$p}user_shortlinks sl SET
        total_clicks = (SELECT COUNT(*) FROM {$p}shortlink_visits WHERE shortlink_id = sl.id),
        total_completed = (SELECT COUNT(*) FROM {$p}shortlink_visits WHERE shortlink_id = sl.id AND step = 'verified' AND reward_paid = 1),
        total_earnings = COALESCE((SELECT SUM(reward_amount) FROM {$p}shortlink_visits WHERE shortlink_id = sl.id AND step = 'verified' AND reward_paid = 1), 0)");
}

/** Recalculate campaign counters */
function traffictop_sync_campaign_counters() {
    global $wpdb;
    $p = $wpdb->prefix . TRAFFICTOP_PREFIX;

    $wpdb->query("UPDATE {$p}keyword_campaigns kc SET
        completed = (SELECT COUNT(*) FROM {$p}shortlink_visits WHERE campaign_id = kc.id AND step = 'verified'),
        total_earnings = COALESCE((SELECT SUM(reward_amount) FROM {$p}shortlink_visits WHERE campaign_id = kc.id AND step = 'verified' AND reward_paid = 1), 0)");
}
