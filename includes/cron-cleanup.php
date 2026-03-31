<?php
/**
 * LinkNgon V2 - Cron Cleanup & Counter Sync
 * SAFETY: NEVER delete financial data
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function linkngon_run_database_cleanup() {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $now = linkngon_current_time();

    // Delete old unverified visits (>48h) - ONLY if reward_paid=0 AND customer_paid=0
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$p}shortlink_visits WHERE step != 'verified' AND reward_paid = 0 AND customer_paid = 0 AND created_at < DATE_SUB(%s, INTERVAL 48 HOUR)", $now ));

    // Delete old notifications (>30 days)
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$p}notifications WHERE created_at < DATE_SUB(%s, INTERVAL 30 DAY)", $now ));

    // Delete old behavior analytics (>7 days)
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$p}behavior_analytics WHERE created_at < DATE_SUB(%s, INTERVAL 7 DAY)", $now ));

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
}

/** Recalculate shortlink counters (fix drift) */
function linkngon_sync_shortlink_counters() {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;

    $wpdb->query("UPDATE {$p}user_shortlinks sl SET
        total_clicks = (SELECT COUNT(*) FROM {$p}shortlink_visits WHERE shortlink_id = sl.id AND step = 'verified'),
        total_earnings = COALESCE((SELECT SUM(reward_amount) FROM {$p}shortlink_visits WHERE shortlink_id = sl.id AND step = 'verified' AND reward_paid = 1), 0)");
}

/** Recalculate campaign counters */
function linkngon_sync_campaign_counters() {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;

    $wpdb->query("UPDATE {$p}keyword_campaigns kc SET
        total_completed = (SELECT COUNT(*) FROM {$p}shortlink_visits WHERE campaign_id = kc.id AND step = 'verified'),
        total_earnings = COALESCE((SELECT SUM(reward_amount) FROM {$p}shortlink_visits WHERE campaign_id = kc.id AND step = 'verified' AND reward_paid = 1), 0)");
}
