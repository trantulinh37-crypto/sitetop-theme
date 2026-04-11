<?php
/**
 * One-time cleanup: Delete all user data, keep only admin
 * Run via: cd /home/cogfjvaa/traffictop.net && php -r "require 'wp-load.php'; include 'wp-content/themes/traffictop-theme/cleanup-data.php';"
 * DELETE THIS FILE AFTER RUNNING
 */
if ( ! defined( 'ABSPATH' ) ) {
    echo "Must be run within WordPress context\n";
    exit;
}

global $wpdb;
$p = $wpdb->prefix . 'traffictop_';

echo "=== CLEANUP START ===\n\n";

// 1. Find admin user IDs to keep
$admin_ids = $wpdb->get_col("
    SELECT u.ID FROM {$wpdb->users} u
    INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
    WHERE um.meta_key = '{$wpdb->prefix}capabilities'
    AND um.meta_value LIKE '%administrator%'
");

if (empty($admin_ids)) {
    echo "ERROR: No admin users found! Aborting.\n";
    exit;
}

echo "Admin user IDs to keep: " . implode(', ', $admin_ids) . "\n";

// 2. Delete non-admin users
$keep_ids = implode(',', array_map('intval', $admin_ids));
$non_admin_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users} WHERE ID NOT IN ($keep_ids)");
echo "Non-admin users to delete: $non_admin_count\n";

if ($non_admin_count > 0) {
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE user_id NOT IN ($keep_ids)");
    $wpdb->query("DELETE FROM {$wpdb->users} WHERE ID NOT IN ($keep_ids)");
    echo "  -> Deleted $non_admin_count users + their meta\n";
}

// 3. Truncate all traffictop data tables
$tables_to_truncate = array(
    'shortlink_visits',
    'user_shortlinks',
    'transactions',
    'customer_transactions',
    'keyword_campaigns',
    'customer_orders',
    'withdrawals',
    'customer_deposits',
    'user_balance',
    'customer_balance',
    'notifications',
    'daily_checkins',
    'behavior_analytics',
    'device_fingerprints',
    'ip_reputation',
    'ddos_blocks',
    'low_balance_alerts',
    'tasks',
);

foreach ($tables_to_truncate as $table) {
    $full = $p . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full'");
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `$full`");
        $wpdb->query("TRUNCATE TABLE `$full`");
        echo "  TRUNCATED: $full ($count rows)\n";
    }
}

// 4. Re-initialize admin balance (if admin is also customer)
foreach ($admin_ids as $aid) {
    $wpdb->query($wpdb->prepare("
        INSERT IGNORE INTO {$p}user_balance (user_id, balance, total_earned)
        VALUES (%d, 0, 0)
    ", $aid));
    $wpdb->query($wpdb->prepare("
        INSERT IGNORE INTO {$p}customer_balance (user_id, balance, total_deposited, total_spent)
        VALUES (%d, 0, 0, 0)
    ", $aid));
}
echo "\n  -> Re-initialized admin balances\n";

echo "\n=== CLEANUP COMPLETE ===\n";
echo "IMPORTANT: Delete this file now!\n";
