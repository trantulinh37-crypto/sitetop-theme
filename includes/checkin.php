<?php
/**
 * LinkNgon V2 - Daily Check-in Reward
 * Streak-based increasing reward
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function linkngon_get_checkin_reward( $streak_day ) {
    $rewards = array( 1=>100, 2=>200, 3=>300, 4=>400, 5=>500, 6=>600, 7=>1000 );
    $day = min($streak_day, 7);
    return $rewards[$day] ?? 100;
}

function linkngon_daily_checkin( $user_id ) {
    global $wpdb;
    $p = $wpdb->prefix . LINKNGON_PREFIX;
    $today = date('Y-m-d', strtotime(linkngon_current_time()));

    // Already checked in today?
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$p}daily_checkins WHERE user_id=%d AND checkin_date=%s", $user_id, $today ));
    if ( $exists ) return new WP_Error('already', 'Đã điểm danh hôm nay');

    // Calculate streak
    $yesterday = date('Y-m-d', strtotime('-1 day', strtotime($today)));
    $prev = $wpdb->get_row( $wpdb->prepare(
        "SELECT streak_day FROM {$p}daily_checkins WHERE user_id=%d AND checkin_date=%s", $user_id, $yesterday ));
    // Cycle: after day 7, reset to day 1
    $streak = $prev ? ( $prev->streak_day >= 7 ? 1 : $prev->streak_day + 1 ) : 1;

    $reward = linkngon_get_checkin_reward($streak);

    $wpdb->insert("{$p}daily_checkins", array(
        'user_id'=>$user_id, 'checkin_date'=>$today, 'streak_day'=>$streak,
        'reward_amount'=>$reward, 'created_at'=>linkngon_current_time(),
    ));

    // Add reward via balance function (uses 'earn' type for consistency with balance calc)
    linkngon_add_user_balance( $user_id, $reward, 'earn', "Điểm danh ngày {$streak} (streak)" );

    return array('streak'=>$streak, 'reward'=>$reward);
}
