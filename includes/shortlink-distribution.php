<?php
/**
 * LinkNgon V2 - Campaign Distribution Algorithm
 * Mapped from CLAUDE.md Flow 2: Weighted random selection
 *
 * customer_balance table dùng cột user_id (KHÔNG PHẢI customer_id)
 * Kiểm tra cột tồn tại trước khi JOIN
 *
 * Ported production improvements:
 * - SQL error safety in auto-pause/resume
 * - Eligible campaigns caching (60s)
 * - Visitor IP completion exclusion
 * - Recovery logic for auto-completed campaigns
 * - Diagnostic logging when no campaigns found
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get random active campaign for visitor (weighted selection)
 * Flow 2: Load eligible → filter daily limits → calculate weight → weighted random
 */
function linkngon_get_random_active_campaign( $visitor_ip = '', $exclude_campaign_id = 0 ) {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $today = date( 'Y-m-d', strtotime( linkngon_current_time() ) );

    // Check columns exist before using in query
    $has_start = $wpdb->get_results( "SHOW COLUMNS FROM {$p}keyword_campaigns LIKE 'start_date'" );
    $has_end   = $wpdb->get_results( "SHOW COLUMNS FROM {$p}keyword_campaigns LIKE 'end_date'" );

    $date_filter = '';
    if ( ! empty( $has_start ) ) $date_filter .= " AND (kc.start_date IS NULL OR kc.start_date <= '{$today}')";
    if ( ! empty( $has_end ) )   $date_filter .= " AND (kc.end_date IS NULL OR kc.end_date >= '{$today}')";

    $min_balance = (int) linkngon_get_option( 'customer_min_balance', 20000 );

    // ================================================================
    // 1. Load eligible campaigns (cached 60s)
    // Heavy balance subquery runs once per minute; daily limit check is real-time below
    // ================================================================
    $cache_key = 'linkngon_eligible_campaigns';
    $campaigns = get_transient( $cache_key );

    if ( $campaigns === false ) {
        // Column safety: customer_transactions may use customer_id or user_id
        $has_cid = $wpdb->get_results( "SHOW COLUMNS FROM {$p}customer_transactions LIKE 'customer_id'" );
        $cid_col = ! empty( $has_cid ) ? 'customer_id' : 'user_id';

        // Pre-calculate customer balances from source of truth (deposits + transactions)
        // Must match linkngon_get_customer_balance_amount() formula
        $balance_sql = "SELECT user_id AS customer_id,
            COALESCE((SELECT SUM(amount + COALESCE(bonus_amount, 0))
                      FROM {$p}customer_deposits
                      WHERE customer_id = cb_calc.user_id AND status = 'approved'), 0)
            + COALESCE((SELECT SUM(amount) FROM {$p}customer_transactions
                        WHERE {$cid_col} = cb_calc.user_id AND type = 'bonus' AND amount > 0), 0)
            - COALESCE((SELECT ABS(SUM(amount)) FROM {$p}customer_transactions
                        WHERE {$cid_col} = cb_calc.user_id AND type = 'campaign_view' AND amount < 0), 0)
            - COALESCE((SELECT ABS(SUM(amount)) FROM {$p}customer_transactions
                        WHERE {$cid_col} = cb_calc.user_id AND type = 'deduction' AND amount < 0), 0) as balance
            FROM {$p}customer_balance cb_calc";

        $sql = $wpdb->prepare(
            "SELECT kc.*, co.quantity as order_quantity, co.daily_traffic as order_daily_traffic,
                    cb_pre.balance as customer_balance
             FROM {$p}keyword_campaigns kc
             INNER JOIN {$p}customer_orders co ON co.id = kc.order_id
             INNER JOIN ({$balance_sql}) cb_pre ON cb_pre.customer_id = kc.customer_id
             WHERE kc.status = 'active'
             AND co.status = 'active'
             AND cb_pre.balance > %d + GREATEST(COALESCE(kc.price_per_view, 0), 1000)
             {$date_filter}
             ORDER BY COALESCE(co.daily_traffic, kc.daily_traffic, 10) DESC",
            $min_balance
        );
        $campaigns = $wpdb->get_results( $sql );

        if ( ! empty( $campaigns ) ) {
            set_transient( $cache_key, $campaigns, 60 );
        }
    }

    if ( empty( $campaigns ) ) {
        // Diagnostic: log why no campaigns found
        $diag_active = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$p}keyword_campaigns kc
             INNER JOIN {$p}customer_orders co ON co.id = kc.order_id
             WHERE kc.status = 'active' AND co.status = 'active'"
        );
        $diag_paused = $wpdb->get_var( "SELECT COUNT(*) FROM {$p}keyword_campaigns WHERE status = 'paused'" );
        if ( function_exists( 'linkngon_log' ) ) {
            linkngon_log( 'info', "Distribution: No eligible campaigns. Active(camp+order): {$diag_active}, Paused: {$diag_paused}, min_balance={$min_balance}" );
        }
        return null;
    }

    // ================================================================
    // 2. Per-campaign filtering (real-time, NOT cached)
    // ================================================================
    $now_str = linkngon_current_time();
    $now_hour = (int) date( 'G', strtotime( $now_str ) );
    $minute = (int) date( 'i', strtotime( $now_str ) );
    $ten_min_ago = date( 'Y-m-d H:i:s', strtotime( $now_str ) - 600 );

    // Exclude campaigns visitor already completed today
    $visitor_completed = array();
    if ( $visitor_ip ) {
        $completed_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT campaign_id FROM {$p}shortlink_visits
             WHERE ip_address = %s AND step = 'verified' AND DATE(created_at) = %s AND campaign_id IS NOT NULL",
            $visitor_ip, $today
        ));
        if ( $completed_ids ) $visitor_completed = array_map( 'intval', $completed_ids );
    }

    $eligible = array();
    $total_progress = 0;
    $base_expected = ( $now_hour + $minute / 60 ) / 24;

    // Hourly adjustments (carryover from previous hour)
    $hourly_adj = get_option( 'linkngon_hourly_adjustments', array() );
    if ( ! isset( $hourly_adj['date'] ) || $hourly_adj['date'] !== $today ) {
        $hourly_adj = array( 'date' => $today, 'camps' => array() );
    }

    foreach ( $campaigns as $c ) {
        // Skip if visitor already completed this campaign
        if ( in_array( (int) $c->id, $visitor_completed ) ) continue;

        // Skip explicitly excluded campaign (e.g. when changing keyword)
        if ( $exclude_campaign_id && (int) $c->id === (int) $exclude_campaign_id ) continue;

        $order_dt = (int) ( $c->order_daily_traffic ?? 0 );
        $camp_dt  = (int) ( $c->daily_traffic ?? 0 );
        $daily_limit = $order_dt > 0 ? $order_dt : ( $camp_dt > 0 ? $camp_dt : 10 );

        // Count today: verified + in-progress (< 10 min)
        $today_done = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}shortlink_visits
             WHERE campaign_id = %d AND DATE(created_at) = %s
             AND (step = 'verified' OR (step IN ('started','searching','on_site') AND created_at > %s))",
            $c->id, $today, $ten_min_ago
        ));

        if ( $today_done >= $daily_limit ) continue;

        $remaining = max( 1, $daily_limit - $today_done );
        $progress = $today_done / $daily_limit;
        $total_progress += $progress;

        $time_lag = $base_expected - $progress;
        $carryover = isset( $hourly_adj['camps'][ $c->id ] ) ? (float) $hourly_adj['camps'][ $c->id ] : 0;
        $carryover = max( -0.2, min( 0.2, $carryover ) );

        $c->_remaining = $remaining;
        $c->_progress = $progress;
        $c->_time_lag = $time_lag;
        $c->_carryover = $carryover;
        $c->daily_limit = $daily_limit;
        $c->today_completed = $today_done;
        $eligible[] = $c;
    }

    if ( empty( $eligible ) ) return null;

    // Calculate peer_lag and final weights
    // Formula: weight = remaining × e^(combined_lag × 10)
    // combined_lag = (time_lag × 0.5) + (peer_lag × 0.5) + carryover
    $avg_progress = $total_progress / count( $eligible );
    foreach ( $eligible as $c ) {
        $peer_lag = $avg_progress - $c->_progress;
        $combined = ( $c->_time_lag * 0.5 ) + ( $peer_lag * 0.5 ) + $c->_carryover;
        $c->weight = max( 0.01, $c->_remaining * exp( $combined * 10 ) );
    }

    // ================================================================
    // 4. Weighted random selection
    // ================================================================
    return linkngon_weighted_random_select( $eligible );
}

/**
 * Weighted random select from campaign list
 */
function linkngon_weighted_random_select( $campaigns ) {
    if ( empty( $campaigns ) ) return null;
    if ( count( $campaigns ) === 1 ) return $campaigns[0];

    $total = 0;
    foreach ( $campaigns as $c ) {
        $total += max( 1, $c->weight );
    }
    if ( $total <= 0 ) return $campaigns[ array_rand( $campaigns ) ];

    $rand = random_int( 1, (int) ceil( $total ) );
    $cumulative = 0;
    foreach ( $campaigns as $c ) {
        $cumulative += max( 1, $c->weight );
        if ( $rand <= $cumulative ) return $c;
    }
    return $campaigns[0];
}

/**
 * Auto-pause campaigns when customer balance too low (every 5 min)
 * Includes SQL error safety and false-positive prevention
 */
function linkngon_auto_pause_insufficient_campaigns() {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $min_balance = (int) linkngon_get_option( 'customer_min_balance', 20000 );

    // Find customers with active campaigns
    $active_customers = $wpdb->get_results(
        "SELECT kc.customer_id, MIN(GREATEST(COALESCE(kc.price_per_view, 0), 1000)) as min_price
         FROM {$p}keyword_campaigns kc
         INNER JOIN {$p}customer_orders co ON co.id = kc.order_id
         WHERE kc.status = 'active' AND co.status = 'active'
         GROUP BY kc.customer_id"
    );

    if ( empty( $active_customers ) ) return;

    foreach ( $active_customers as $cust ) {
        $balance = linkngon_get_customer_balance_amount( $cust->customer_id );

        // SQL error safety: don't pause if balance query failed
        if ( $balance === false ) continue;

        $required = $min_balance + (float) $cust->min_price;

        if ( $balance <= $required ) {
            // Double-check: balance=0 but has approved deposits → likely SQL error
            if ( $balance == 0 ) {
                $has_deposits = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$p}customer_deposits WHERE customer_id = %d AND status = 'approved'",
                    $cust->customer_id
                ));
                if ( $has_deposits > 0 ) continue; // Skip pause - likely calculation error
            }

            linkngon_auto_pause_customer_campaigns( $cust->customer_id );
        }
    }
}

/**
 * Auto-resume campaigns when customer balance recovered (every 15 min)
 * Includes one-time recovery for incorrectly auto-completed campaigns
 */
function linkngon_auto_resume_paused_campaigns() {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $min_balance = (int) linkngon_get_option( 'customer_min_balance', 20000 );

    // One-time recovery v2: restore incorrectly auto-completed campaigns
    if ( ! get_transient( 'linkngon_autocomplete_recovery_v2' ) ) {
        $completed_customers = $wpdb->get_col(
            "SELECT DISTINCT kc.customer_id FROM {$p}keyword_campaigns kc
             INNER JOIN {$p}customer_orders co ON co.id = kc.order_id
             WHERE kc.status = 'completed' AND co.status = 'completed'
             AND co.quantity > 0 AND co.completed >= co.quantity"
        );

        $recovered = 0;
        foreach ( $completed_customers as $cid ) {
            $bal = linkngon_get_customer_balance_amount( $cid );
            if ( $bal === false ) continue; // SQL error safety
            // Use min_balance + min price_per_view to match pause/resume logic
            $min_price = (float) $wpdb->get_var( $wpdb->prepare(
                "SELECT MIN(GREATEST(COALESCE(price_per_view, 0), 1000)) FROM {$p}keyword_campaigns WHERE customer_id = %d AND status = 'completed'", $cid
            ));
            $required = $min_balance + ( $min_price ?: 1000 );
            if ( $bal > $required ) {
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$p}customer_orders SET status = 'active'
                     WHERE customer_id = %d AND status = 'completed'
                     AND quantity > 0 AND completed >= quantity", $cid
                ));
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$p}keyword_campaigns kc
                     INNER JOIN {$p}customer_orders co ON co.id = kc.order_id
                     SET kc.status = 'active'
                     WHERE kc.customer_id = %d AND kc.status = 'completed' AND co.status = 'active'", $cid
                ));
                $recovered++;
            }
        }
        if ( $recovered > 0 ) delete_transient( 'linkngon_eligible_campaigns' );
        set_transient( 'linkngon_autocomplete_recovery_v2', 1, 30 * DAY_IN_SECONDS );
    }

    // Auto-resume paused campaigns when balance recovered
    $paused_customers = $wpdb->get_results(
        "SELECT kc.customer_id, MIN(GREATEST(COALESCE(kc.price_per_view, 0), 1000)) as min_price
         FROM {$p}keyword_campaigns kc
         INNER JOIN {$p}customer_orders co ON co.id = kc.order_id
         WHERE kc.status = 'paused' AND co.status = 'paused'
         GROUP BY kc.customer_id"
    );

    foreach ( $paused_customers as $cust ) {
        $balance = linkngon_get_customer_balance_amount( $cust->customer_id );
        if ( $balance === false ) continue; // SQL error safety

        $required = $min_balance + (float) $cust->min_price;

        if ( $balance > $required ) {
            $now = linkngon_current_time();
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$p}keyword_campaigns SET status='active', updated_at=%s WHERE customer_id=%d AND status='paused'",
                $now, $cust->customer_id
            ));
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$p}customer_orders SET status='active', updated_at=%s WHERE customer_id=%d AND status='paused'",
                $now, $cust->customer_id
            ));
            delete_transient( 'linkngon_eligible_campaigns' );
        }
    }
}

/**
 * Update customer balance with transaction logging
 * Ported from production taskify_update_customer_balance_new()
 */
function linkngon_update_customer_balance_new( $customer_id, $amount, $type, $description, $reference_id = null, $reference_type = null ) {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;

    $reference_id = $reference_id ?? 0;
    $reference_type = $reference_type ?? '';
    $now = linkngon_current_time();

    // Update balance
    if ( $amount > 0 ) {
        $result = $wpdb->query( $wpdb->prepare(
            "UPDATE {$p}customer_balance SET balance = balance + %d, updated_at = %s WHERE user_id = %d",
            $amount, $now, $customer_id
        ));
    } else {
        // Only add to total_spent for campaign_view (actual traffic)
        if ( $type === 'campaign_view' ) {
            $result = $wpdb->query( $wpdb->prepare(
                "UPDATE {$p}customer_balance SET balance = balance + %d, total_spent = total_spent + %d, updated_at = %s WHERE user_id = %d",
                $amount, abs( $amount ), $now, $customer_id
            ));
        } else {
            $result = $wpdb->query( $wpdb->prepare(
                "UPDATE {$p}customer_balance SET balance = balance + %d, updated_at = %s WHERE user_id = %d",
                $amount, $now, $customer_id
            ));
        }
    }

    if ( $result === false ) {
        throw new Exception( "Failed to update customer balance for customer_id: {$customer_id}" );
    }

    // If row doesn't exist, create it
    if ( $result === 0 ) {
        $wpdb->insert( "{$p}customer_balance", array(
            'user_id' => $customer_id,
            'balance' => $amount,
            'total_deposited' => 0,
            'total_spent' => ( $amount < 0 && $type === 'campaign_view' ) ? abs( $amount ) : 0,
            'updated_at' => $now,
        ));
    }

    // Log transaction
    $insert_data = array(
        'type'           => $type,
        'amount'         => $amount,
        'description'    => $description,
        'reference_id'   => $reference_id,
        'reference_type' => $reference_type,
        'created_at'     => $now,
    );

    // Column safety: check if customer_id column exists in transactions table
    $has_cid = $wpdb->get_results( "SHOW COLUMNS FROM {$p}customer_transactions LIKE 'customer_id'" );
    if ( ! empty( $has_cid ) ) $insert_data['customer_id'] = $customer_id;
    $has_uid = $wpdb->get_results( "SHOW COLUMNS FROM {$p}customer_transactions LIKE 'user_id'" );
    if ( ! empty( $has_uid ) ) $insert_data['user_id'] = $customer_id;

    $wpdb->insert( "{$p}customer_transactions", $insert_data );

    return true;
}

/**
 * Hourly rebalancing (Flow 2 step 4)
 */
function linkngon_update_hourly_adjustments() {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $today = date( 'Y-m-d', strtotime( linkngon_current_time() ) );
    $hour = (int) date( 'G', strtotime( linkngon_current_time() ) );

    $hourly_expected = ( $hour + 1 ) / 24;

    $campaigns = $wpdb->get_results(
        "SELECT kc.id, kc.daily_traffic, co.daily_traffic as order_daily_traffic
         FROM {$p}keyword_campaigns kc
         INNER JOIN {$p}customer_orders co ON co.id = kc.order_id
         WHERE kc.status = 'active' AND co.status = 'active'"
    );
    if ( empty( $campaigns ) ) return;

    $adjustments = array( 'date' => $today, 'hour' => $hour, 'camps' => array() );

    foreach ( $campaigns as $c ) {
        $order_dt = (int) ( $c->order_daily_traffic ?? 0 );
        $camp_dt  = (int) ( $c->daily_traffic ?? 0 );
        $daily_limit = $order_dt > 0 ? $order_dt : ( $camp_dt > 0 ? $camp_dt : 10 );

        $done = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}shortlink_visits
             WHERE campaign_id = %d AND step = 'verified' AND DATE(created_at) = %s",
            $c->id, $today
        ));

        $progress = $daily_limit > 0 ? ( $done / $daily_limit ) : 0;
        $deviation = $hourly_expected - $progress;
        $adjustments['camps'][ $c->id ] = $deviation / 2; // Smoothing
    }

    update_option( 'linkngon_hourly_adjustments', $adjustments );
}

/**
 * Cache eligible campaigns (hourly pre-warm)
 */
function linkngon_cache_eligible_campaigns() {
    delete_transient( 'linkngon_eligible_campaigns' );
    linkngon_get_random_active_campaign();
}

// Register cron for hourly distribution check
add_action( 'init', function() {
    if ( ! wp_next_scheduled( 'linkngon_hourly_distribution_check' ) ) {
        wp_schedule_event( time(), 'hourly', 'linkngon_hourly_distribution_check' );
    }
});
add_action( 'linkngon_hourly_distribution_check', 'linkngon_update_hourly_adjustments' );
