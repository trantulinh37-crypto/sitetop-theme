<?php
/**
 * One-time sync: Fix visits with user_id=0 that have customer_paid=1
 * These visits were created with get_current_user_id() instead of shortlink->user_id
 *
 * Run: cd /home/cogfjvaa/traffictop.net && php -r "require 'wp-load.php'; include 'wp-content/themes/traffictop-theme/sync-past-rewards.php';"
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Affirmative guard: only runnable from WP-CLI or by an admin. Prevents this money-granting
// maintenance script from ever executing for a non-admin even if it gets included post-bootstrap.
if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! current_user_can( 'manage_options' ) ) {
    echo "Forbidden\n";
    return;
}

global $wpdb;
$p = $wpdb->prefix . 'traffictop_';

echo "=== Sync Past Rewards ===\n\n";

// Step 1: Find verified visits with customer_paid=1 but reward_paid=0 (any user_id)
$orphan_visits = $wpdb->get_results(
    "SELECT v.id, v.session_id, v.shortlink_id, v.campaign_id, v.reward_amount,
            v.customer_paid, v.reward_paid, v.user_id, v.created_at,
            v.adblock_detected, v.ip_changed, v.is_bypass, v.ip_limit_exceeded,
            sl.user_id as publisher_id,
            kc.user_reward as camp_reward, kc.traffic_type, kc.campaign_type, kc.price_per_view
     FROM {$p}shortlink_visits v
     LEFT JOIN {$p}user_shortlinks sl ON v.shortlink_id = sl.id
     LEFT JOIN {$p}keyword_campaigns kc ON v.campaign_id = kc.id
     WHERE v.step = 'verified'
     AND v.customer_paid = 1
     AND v.reward_paid = 0
     ORDER BY v.id ASC"
);

$total = count( $orphan_visits );
echo "Found {$total} visits with customer_paid=1 but reward_paid=0 and user_id=0\n\n";

if ( $total === 0 ) {
    echo "Nothing to sync.\n";
    return;
}

$synced = 0;
$skipped = 0;
$total_reward = 0;

foreach ( $orphan_visits as $v ) {
    // Publisher = visit's user_id (if > 0) or shortlink owner
    $publisher_id = (int) $v->user_id > 0 ? (int) $v->user_id : (int) $v->publisher_id;

    if ( $publisher_id <= 0 ) {
        echo "  SKIP visit #{$v->id}: no publisher found\n";
        $skipped++;
        continue;
    }

    // Log skip reasons (but still pay - these visits already had customer_paid=1)
    $reasons = array();
    if ( $v->adblock_detected ) $reasons[] = 'adblock';
    if ( $v->ip_changed ) $reasons[] = 'ip_changed';
    if ( $v->is_bypass ) $reasons[] = 'bypass';
    if ( $v->ip_limit_exceeded ) $reasons[] = 'ip_limit';
    $reason_str = ! empty( $reasons ) ? ' [' . implode( ',', $reasons ) . ']' : '';

    // Calculate reward
    $reward = 0;
    if ( $v->camp_reward && $v->camp_reward > 0 ) {
        $reward = (float) $v->camp_reward;
    } else {
        // Fallback to settings
        $campaign_type = $v->campaign_type ?? 'keyword_search';
        $traffic_type = $v->traffic_type ?? '1step';
        if ( $campaign_type === 'keyword_search' ) {
            $key = 'keyword_user_' . $traffic_type;
        } elseif ( $campaign_type === 'traffic_direct' ) {
            $key = 'direct_user_' . $traffic_type;
        } else {
            $key = 'keyword_user_' . $traffic_type;
        }
        $reward = (float) traffictop_get_option( $key, 800 );
    }

    if ( $reward <= 0 ) {
        echo "  SKIP visit #{$v->id}: reward = 0\n";
        $skipped++;
        continue;
    }

    // Atomic claim: only this run that flips reward_paid 0→1 may pay. Guards against a second
    // concurrent run reading the same visit as unpaid and double-crediting the balance.
    $claimed = $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}shortlink_visits SET user_id = %d, reward_paid = 1, reward_amount = %f
         WHERE id = %d AND reward_paid = 0",
        $publisher_id, $reward, $v->id
    ) );
    if ( $claimed !== 1 ) {
        echo "  SKIP visit #{$v->id}: already claimed by another run\n";
        $skipped++;
        continue;
    }

    // Add balance to publisher
    traffictop_add_user_balance( $publisher_id, $reward, 'shortlink_reward',
        'Đồng bộ thưởng visit #' . $v->id, $v->id, 'visit' );

    // Update shortlink stats
    if ( $v->shortlink_id ) {
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$p}user_shortlinks SET total_completed = total_completed + 1, total_earnings = total_earnings + %f WHERE id = %d",
            $reward, $v->shortlink_id
        ));
    }

    $synced++;
    $total_reward += $reward;
    echo "  OK visit #{$v->id}: publisher #{$publisher_id} +{$reward}đ (campaign #{$v->campaign_id}){$reason_str}\n";
}

// Sync balance from transactions
if ( $synced > 0 ) {
    // Get unique publishers
    $publishers = $wpdb->get_col(
        "SELECT DISTINCT sl.user_id FROM {$p}shortlink_visits v
         JOIN {$p}user_shortlinks sl ON v.shortlink_id = sl.id
         WHERE v.step = 'verified' AND v.reward_paid = 1 AND sl.user_id > 0"
    );
    foreach ( $publishers as $pub_id ) {
        traffictop_sync_user_balance( $pub_id );
    }
}

echo "\n=== DONE ===\n";
echo "Synced: {$synced} | Skipped: {$skipped} | Total reward: " . number_format( $total_reward ) . "đ\n";
