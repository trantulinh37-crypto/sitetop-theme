<?php
/**
 * LinkNgon V2 - Campaign Distribution Algorithm
 * Mapped from CLAUDE.md Flow 2: Weighted random selection
 * 
 * customer_balance table dùng cột user_id (KHÔNG PHẢI customer_id)
 * Kiểm tra cột tồn tại trước khi JOIN
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get random active campaign for visitor (weighted selection)
 * Flow 2: Load eligible → calculate weight → weighted random
 */
function linkngon_get_random_active_campaign( $visitor_ip = '' ) {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $today = date( 'Y-m-d', strtotime( linkngon_current_time() ) );
    $now_hour = (int) date( 'G', strtotime( linkngon_current_time() ) );

    // Check columns exist before using
    $has_start = $wpdb->get_results( "SHOW COLUMNS FROM {$p}keyword_campaigns LIKE 'start_date'" );
    $has_end   = $wpdb->get_results( "SHOW COLUMNS FROM {$p}keyword_campaigns LIKE 'end_date'" );

    $date_filter = '';
    if ( !empty($has_start) ) $date_filter .= " AND (kc.start_date IS NULL OR kc.start_date <= '{$today}')";
    if ( !empty($has_end) )   $date_filter .= " AND (kc.end_date IS NULL OR kc.end_date >= '{$today}')";

    // V2: bỏ social traffic filter
    $traffic_filter = ""; // traffic_type is 1step/2step/nocode - no filter needed

    // 1. Load eligible campaigns
    // customer_balance uses user_id (NOT customer_id)
    $sql = "SELECT kc.*, co.status as order_status
            FROM {$p}keyword_campaigns kc
            LEFT JOIN {$p}customer_orders co ON kc.order_id = co.id
            INNER JOIN (
                SELECT user_id, balance FROM {$p}customer_balance WHERE balance > 20000
            ) cb ON kc.customer_id = cb.user_id
            WHERE kc.status = 'active'
            AND (co.id IS NULL OR co.status = 'active')
            AND cb.balance > 20000 + kc.price_per_view
            {$date_filter}
            {$traffic_filter}";

    $campaigns = $wpdb->get_results( $sql );
    if ( empty($campaigns) ) return null;

    // 2. Filter and calculate weights
    $weighted = array();
    foreach ( $campaigns as $c ) {
        // Count today's verified visits
        $completed_today = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}shortlink_visits WHERE campaign_id=%d AND step='verified' AND DATE(created_at)=%s",
            $c->id, $today
        ));

        // Skip if daily limit reached
        if ( $c->daily_traffic > 0 && $completed_today >= $c->daily_traffic ) continue;

        // Skip if visitor already completed this campaign today
        if ( $visitor_ip && linkngon_ip_already_completed_campaign( $c->id, $visitor_ip ) ) continue;

        // Count in-progress (< 10 min, prevent over-assignment)
        $in_progress = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}shortlink_visits WHERE campaign_id=%d AND step != 'verified' AND created_at > DATE_SUB(%s, INTERVAL 10 MINUTE)",
            $c->id, linkngon_current_time()
        ));

        $remaining = $c->daily_traffic > 0 ? max(1, $c->daily_traffic - $completed_today - $in_progress) : 100;

        // 3. Calculate weight: remaining × e^(combined_lag × 10)
        $time_lag = 0;
        $peer_lag = 0;
        $carryover = 0;

        if ( $c->daily_traffic > 0 ) {
            // time_lag: expected% vs actual% (linear through day)
            $hours_passed = max(1, $now_hour);
            $expected_pct = $hours_passed / 24;
            $actual_pct = $completed_today / max(1, $c->daily_traffic);
            $time_lag = $expected_pct - $actual_pct; // positive = behind schedule
        }

        // Get carryover from hourly adjustments
        $adj = $wpdb->get_row( $wpdb->prepare(
            "SELECT carryover FROM {$p}hourly_adjustments WHERE campaign_id=%d AND adjustment_date=%s AND hour_slot=%d",
            $c->id, $today, $now_hour
        ));
        if ( $adj ) $carryover = (float) $adj->carryover;
        $carryover = max( -0.2, min( 0.2, $carryover ) ); // ±20% cap

        $combined_lag = $time_lag + $peer_lag + $carryover;
        $weight = $remaining * exp( $combined_lag * 10 );
        $weight = max( 0.01, $weight );

        $c->_weight = $weight;
        $weighted[] = $c;
    }

    if ( empty($weighted) ) return null;

    // 4. Weighted random selection
    $total_weight = array_sum( array_column( $weighted, '_weight' ) );
    $rand = mt_rand() / mt_getrandmax() * $total_weight;
    $cumulative = 0;

    foreach ( $weighted as $c ) {
        $cumulative += $c->_weight;
        if ( $rand <= $cumulative ) return $c;
    }

    return end( $weighted );
}

/**
 * Auto-pause campaigns when customer balance too low (every 5 min)
 */
function linkngon_auto_pause_insufficient_campaigns() {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;

    // Find customers with active campaigns but low balance
    $low_customers = $wpdb->get_results(
        "SELECT DISTINCT kc.customer_id, cb.balance, kc.price_per_view
         FROM {$p}keyword_campaigns kc
         INNER JOIN {$p}customer_balance cb ON kc.customer_id = cb.user_id
         WHERE kc.status = 'active'
         AND cb.balance <= 20000 + kc.price_per_view"
    );

    foreach ( $low_customers as $c ) {
        linkngon_auto_pause_customer_campaigns( $c->customer_id );
    }
}

/**
 * Auto-resume campaigns when customer balance recovered (every 15 min)
 */
function linkngon_auto_resume_paused_campaigns() {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;

    $resumable = $wpdb->get_results(
        "SELECT DISTINCT kc.customer_id, cb.balance
         FROM {$p}keyword_campaigns kc
         INNER JOIN {$p}customer_balance cb ON kc.customer_id = cb.user_id
         WHERE kc.status = 'paused'
         AND cb.balance > 20000 + kc.price_per_view"
    );

    foreach ( $resumable as $c ) {
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$p}keyword_campaigns SET status='active', updated_at=%s WHERE customer_id=%d AND status='paused'",
            linkngon_current_time(), $c->customer_id
        ));
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$p}customer_orders SET status='active', updated_at=%s WHERE customer_id=%d AND status='paused'",
            linkngon_current_time(), $c->customer_id
        ));
    }
}

/**
 * Hourly rebalancing (Flow 2 step 4)
 */
function linkngon_update_hourly_adjustments() {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $today = date('Y-m-d', strtotime(linkngon_current_time()));
    $hour = (int) date('G', strtotime(linkngon_current_time()));

    $campaigns = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, daily_traffic, total_completed FROM {$p}keyword_campaigns WHERE status='active' AND daily_traffic > 0"
    ));

    if ( empty($campaigns) ) return;

    $avg_pct = 0;
    $count = 0;
    foreach ( $campaigns as $c ) {
        $done_today = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}shortlink_visits WHERE campaign_id=%d AND step='verified' AND DATE(created_at)=%s",
            $c->id, $today
        ));
        $pct = $done_today / max(1, $c->daily_traffic);
        $c->_pct = $pct;
        $avg_pct += $pct;
        $count++;
    }
    $avg_pct = $count > 0 ? $avg_pct / $count : 0;

    foreach ( $campaigns as $c ) {
        $deviation = $c->_pct - $avg_pct;
        $carryover = -$deviation * 0.5; // Smoothing
        $carryover = max(-0.2, min(0.2, $carryover)); // ±20% cap

        $wpdb->replace( "{$p}hourly_adjustments", array(
            'campaign_id' => $c->id, 'hour_slot' => $hour, 'adjustment_date' => $today,
            'carryover' => $carryover, 'deviation' => $deviation, 'created_at' => linkngon_current_time(),
        ));
    }
}

/**
 * Cache eligible campaigns (hourly)
 */
function linkngon_cache_eligible_campaigns() {
    // Pre-warm the campaign selection query by calling it
    linkngon_get_random_active_campaign();
}
