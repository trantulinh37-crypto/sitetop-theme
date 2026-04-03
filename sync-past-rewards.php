<?php
/**
 * One-time sync: Fix visits with user_id=0 that have customer_paid=1
 * These visits were created with get_current_user_id() instead of shortlink->user_id
 *
 * Run: cd /home/wlcjwhje/linkngon.top && php -r "require 'wp-load.php'; include 'wp-content/themes/linkngon-theme/sync-past-rewards.php';"
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$p = $wpdb->prefix . 'linkngon_';

echo "=== Sync Past Rewards ===\n\n";

// Step 1: Find visits with customer_paid=1 but reward_paid=0 and user_id=0
$orphan_visits = $wpdb->get_results(
    "SELECT v.id, v.session_id, v.shortlink_id, v.campaign_id, v.reward_amount,
            v.customer_paid, v.reward_paid, v.user_id, v.created_at,
            sl.user_id as publisher_id,
            kc.user_reward as camp_reward, kc.traffic_type, kc.campaign_type, kc.price_per_view
     FROM {$p}shortlink_visits v
     LEFT JOIN {$p}user_shortlinks sl ON v.shortlink_id = sl.id
     LEFT JOIN {$p}keyword_campaigns kc ON v.campaign_id = kc.id
     WHERE v.step = 'verified'
     AND v.customer_paid = 1
     AND v.reward_paid = 0
     AND v.user_id = 0
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
    $publisher_id = (int) $v->publisher_id;

    // Skip if no publisher found
    if ( $publisher_id <= 0 ) {
        echo "  SKIP visit #{$v->id}: no publisher (shortlink #{$v->shortlink_id} not found)\n";
        $skipped++;
        continue;
    }

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
        $reward = (float) linkngon_get_option( $key, 800 );
    }

    if ( $reward <= 0 ) {
        echo "  SKIP visit #{$v->id}: reward = 0\n";
        $skipped++;
        continue;
    }

    // Update visit: set user_id + reward_paid + reward_amount
    $wpdb->update( "{$p}shortlink_visits", array(
        'user_id'       => $publisher_id,
        'reward_paid'   => 1,
        'reward_amount' => $reward,
    ), array( 'id' => $v->id ) );

    // Add balance to publisher
    linkngon_add_user_balance( $publisher_id, $reward, 'shortlink_reward',
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
    echo "  OK visit #{$v->id}: publisher #{$publisher_id} +{$reward}đ (campaign #{$v->campaign_id})\n";
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
        linkngon_sync_user_balance( $pub_id );
    }
}

echo "\n=== DONE ===\n";
echo "Synced: {$synced} | Skipped: {$skipped} | Total reward: " . number_format( $total_reward ) . "đ\n";
