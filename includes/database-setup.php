<?php
/**
 * LinkNgon V2 - Database Setup
 * ALL tables mapped from CLAUDE.md v3 (prefix linkngon_)
 * 
 * Tables: 18 total (17 core + 1 DDoS)
 * Mapped: taskify_ → linkngon_
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function linkngon_create_tables() {
    global $wpdb;
    $c = $wpdb->get_charset_collate();
    $p = $wpdb->prefix . 'linkngon_';
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    /* ─── 1. shortlink_visits ─── */
    dbDelta("CREATE TABLE {$p}shortlink_visits (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        shortlink_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        campaign_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        order_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        session_id varchar(32) NOT NULL DEFAULT '',
        verify_code varchar(20) DEFAULT NULL,
        ip_address varchar(100) NOT NULL DEFAULT '',
        original_ip varchar(100) DEFAULT '',
        ip_changed tinyint(1) NOT NULL DEFAULT 0,
        is_bypass tinyint(1) NOT NULL DEFAULT 0,
        user_agent text,
        referer text,
        step varchar(20) NOT NULL DEFAULT 'started',
        google_clicked_at datetime DEFAULT NULL,
        target_visited_at datetime DEFAULT NULL,
        code_shown_at datetime DEFAULT NULL,
        verified_at datetime DEFAULT NULL,
        from_google tinyint(1) NOT NULL DEFAULT 0,
        url_matched tinyint(1) NOT NULL DEFAULT 0,
        social_clicked tinyint(1) NOT NULL DEFAULT 0,
        adblock_detected tinyint(1) NOT NULL DEFAULT 0,
        ip_limit_exceeded tinyint(1) NOT NULL DEFAULT 0,
        unlock_active tinyint(1) NOT NULL DEFAULT 0,
        reward_paid tinyint(1) NOT NULL DEFAULT 0,
        reward_amount decimal(10,2) NOT NULL DEFAULT 0,
        customer_paid tinyint(1) NOT NULL DEFAULT 0,
        fraud_score int(11) NOT NULL DEFAULT 0,
        onsite_time int(11) DEFAULT NULL,
        completion_time int(11) DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY session_id (session_id),
        KEY shortlink_id (shortlink_id),
        KEY campaign_id (campaign_id),
        KEY user_id (user_id),
        KEY step (step),
        KEY ip_address (ip_address),
        KEY created_at (created_at),
        KEY reward_paid (reward_paid)
    ) $c;");

    /* ─── 2. keyword_campaigns ─── */
    dbDelta("CREATE TABLE {$p}keyword_campaigns (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        order_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        task_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        title varchar(255) NOT NULL DEFAULT '',
        keyword varchar(500) DEFAULT '',
        target_url text NOT NULL,
        target_title varchar(255) DEFAULT '',
        target_description text,
        screenshot_desktop_url text,
        screenshot_mobile_url text,
        nocode_screenshot_url text,
        quantity int(11) NOT NULL DEFAULT 0,
        completed int(11) NOT NULL DEFAULT 0,
        price_per_view decimal(10,2) NOT NULL DEFAULT 0,
        user_reward decimal(10,2) DEFAULT NULL,
        countdown_seconds int(11) NOT NULL DEFAULT 30,
        traffic_type varchar(20) NOT NULL DEFAULT '1step',
        campaign_type varchar(30) NOT NULL DEFAULT 'keyword_search',
        onsite_time int(11) NOT NULL DEFAULT 70,
        fixed_code varchar(20) DEFAULT NULL,
        daily_traffic int(11) NOT NULL DEFAULT 10,
        mobile_only tinyint(1) NOT NULL DEFAULT 0,
        widget_code_status varchar(20) NOT NULL DEFAULT 'not_attached',
        total_earnings decimal(12,2) NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'pending',
        reject_reason text,
        start_date date DEFAULT NULL,
        end_date date DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY status (status),
        KEY customer_id (customer_id),
        KEY order_id (order_id),
        KEY traffic_type (traffic_type)
    ) $c;");

    /* ─── 3. customer_orders ─── */
    /* LƯU Ý: KHÔNG có start_date, end_date (incident 08/03/2026) */
    dbDelta("CREATE TABLE {$p}customer_orders (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        customer_username varchar(100) DEFAULT '',
        task_type varchar(30) NOT NULL DEFAULT 'keyword_search',
        title varchar(255) DEFAULT '',
        task_url text,
        instructions text,
        quantity int(11) NOT NULL DEFAULT 0,
        completed int(11) NOT NULL DEFAULT 0,
        price_per_task decimal(10,2) NOT NULL DEFAULT 0,
        service_fee decimal(10,2) NOT NULL DEFAULT 0,
        total_amount decimal(12,2) NOT NULL DEFAULT 0,
        amount_spent decimal(12,2) NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'active',
        task_id bigint(20) UNSIGNED DEFAULT NULL,
        reject_reason text,
        approved_by bigint(20) UNSIGNED DEFAULT NULL,
        approved_at datetime DEFAULT NULL,
        daily_traffic int(11) NOT NULL DEFAULT 10,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY customer_id (customer_id),
        KEY status (status),
        KEY task_id (task_id)
    ) $c;");

    /* ─── 4. user_shortlinks ─── */
    dbDelta("CREATE TABLE {$p}user_shortlinks (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        code varchar(50) NOT NULL,
        alias varchar(100) DEFAULT NULL,
        original_url text NOT NULL,
        fallback_url text,
        total_clicks bigint(20) NOT NULL DEFAULT 0,
        total_completed bigint(20) NOT NULL DEFAULT 0,
        total_earnings decimal(12,2) NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'active',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY code (code),
        UNIQUE KEY alias (alias),
        KEY user_id (user_id),
        KEY status (status)
    ) $c;");

    /* ─── 5. transactions (SOURCE OF TRUTH - user) ─── */
    dbDelta("CREATE TABLE {$p}transactions (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        type varchar(50) NOT NULL DEFAULT '',
        amount decimal(12,2) NOT NULL DEFAULT 0,
        description text,
        reference_id bigint(20) UNSIGNED DEFAULT NULL,
        reference_type varchar(50) DEFAULT NULL,
        status varchar(20) DEFAULT 'completed',
        balance_after decimal(12,2) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY type (type),
        KEY reference_id (reference_id),
        KEY created_at (created_at)
    ) $c;");

    /* ─── 6. customer_transactions (SOURCE OF TRUTH - customer) ─── */
    dbDelta("CREATE TABLE {$p}customer_transactions (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        type varchar(50) NOT NULL DEFAULT '',
        amount decimal(12,2) NOT NULL DEFAULT 0,
        balance_after decimal(12,2) NOT NULL DEFAULT 0,
        description text,
        reference_id bigint(20) UNSIGNED DEFAULT NULL,
        reference_type varchar(50) DEFAULT NULL,
        status varchar(20) DEFAULT 'completed',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY customer_id (customer_id),
        KEY type (type),
        KEY created_at (created_at)
    ) $c;");

    /* ─── 7. withdrawals ─── */
    dbDelta("CREATE TABLE {$p}withdrawals (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        amount decimal(12,2) NOT NULL DEFAULT 0,
        payment_method varchar(50) NOT NULL DEFAULT 'bank',
        bank_name varchar(255) DEFAULT '',
        bank_account varchar(255) DEFAULT '',
        bank_holder varchar(255) DEFAULT '',
        wallet_address varchar(255) DEFAULT '',
        status varchar(20) NOT NULL DEFAULT 'pending',
        admin_note text,
        refund_amount decimal(12,2) NOT NULL DEFAULT 0,
        processed_at datetime DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY status (status)
    ) $c;");

    /* ─── 8. customer_deposits ─── */
    dbDelta("CREATE TABLE {$p}customer_deposits (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        customer_username varchar(100) DEFAULT '',
        amount decimal(12,2) NOT NULL DEFAULT 0,
        bonus_percent decimal(5,2) NOT NULL DEFAULT 0,
        bonus_amount decimal(12,2) NOT NULL DEFAULT 0,
        payment_method varchar(50) NOT NULL DEFAULT 'bank',
        note text,
        status varchar(20) NOT NULL DEFAULT 'pending',
        approved_by bigint(20) UNSIGNED DEFAULT NULL,
        approved_at datetime DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY customer_id (customer_id),
        KEY status (status)
    ) $c;");

    /* ─── 9. user_balance (cache - CÓ THỂ DRIFT) ─── */
    dbDelta("CREATE TABLE {$p}user_balance (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        balance decimal(12,2) NOT NULL DEFAULT 0,
        total_earned decimal(12,2) NOT NULL DEFAULT 0,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) $c;");

    /* ─── 10. customer_balance (cột user_id, KHÔNG PHẢI customer_id) ─── */
    dbDelta("CREATE TABLE {$p}customer_balance (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        balance decimal(12,2) NOT NULL DEFAULT 0,
        total_deposited decimal(12,2) NOT NULL DEFAULT 0,
        total_spent decimal(12,2) NOT NULL DEFAULT 0,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) $c;");

    /* ─── 11. tasks ─── */
    dbDelta("CREATE TABLE {$p}tasks (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        task_type varchar(50) NOT NULL DEFAULT 'keyword',
        keyword varchar(500) DEFAULT '',
        target_url text,
        reward_amount decimal(10,2) NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'pending',
        admin_note text,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        KEY campaign_id (campaign_id),
        KEY user_id (user_id),
        KEY status (status)
    ) $c;");

    /* ─── 12. notifications ─── */
    dbDelta("CREATE TABLE {$p}notifications (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        type varchar(50) NOT NULL DEFAULT 'info',
        title varchar(255) NOT NULL DEFAULT '',
        message text,
        data longtext,
        is_read tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY is_read (is_read),
        KEY created_at (created_at)
    ) $c;");

    /* ─── 13. daily_checkins — REMOVED (không sử dụng) ─── */

    /* ─── 14. behavior_analytics ─── */
    dbDelta("CREATE TABLE {$p}behavior_analytics (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        visit_id bigint(20) UNSIGNED DEFAULT NULL,
        session_id varchar(32) DEFAULT '',
        user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        ip_address varchar(100) DEFAULT '',
        fraud_score int(11) NOT NULL DEFAULT 0,
        fraud_reasons text,
        risk_level varchar(20) NOT NULL DEFAULT 'safe',
        mouse_movements int(11) DEFAULT 0,
        scroll_depth int(11) DEFAULT 0,
        clicks int(11) DEFAULT 0,
        keystrokes int(11) DEFAULT 0,
        touch_events int(11) DEFAULT 0,
        time_on_page int(11) DEFAULT 0,
        idle_time int(11) DEFAULT 0,
        tab_switches int(11) DEFAULT 0,
        page_hidden_time int(11) DEFAULT 0,
        is_mobile tinyint(1) NOT NULL DEFAULT 0,
        is_bot tinyint(1) NOT NULL DEFAULT 0,
        screen_width int(11) DEFAULT 0,
        screen_height int(11) DEFAULT 0,
        viewport_width int(11) DEFAULT 0,
        viewport_height int(11) DEFAULT 0,
        canvas_hash varchar(64) DEFAULT '',
        webgl_vendor varchar(255) DEFAULT '',
        devtools_open tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY visit_id (visit_id),
        KEY session_id (session_id),
        KEY user_id (user_id),
        KEY fraud_score (fraud_score)
    ) $c;");

    /* ─── 15. device_fingerprints ─── */
    dbDelta("CREATE TABLE {$p}device_fingerprints (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        fingerprint varchar(255) NOT NULL DEFAULT '',
        canvas_hash varchar(64) DEFAULT '',
        user_agent text,
        screen_resolution varchar(50) DEFAULT '',
        timezone_offset int(11) DEFAULT 0,
        languages varchar(255) DEFAULT '',
        first_seen datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_seen datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        visit_count int(11) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY fingerprint (fingerprint),
        KEY canvas_hash (canvas_hash)
    ) $c;");

    /* ─── 16. ip_reputation ─── */
    dbDelta("CREATE TABLE {$p}ip_reputation (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        ip_address varchar(100) NOT NULL DEFAULT '',
        is_vpn tinyint(1) NOT NULL DEFAULT 0,
        is_proxy tinyint(1) NOT NULL DEFAULT 0,
        is_tor tinyint(1) NOT NULL DEFAULT 0,
        is_hosting tinyint(1) NOT NULL DEFAULT 0,
        is_mobile tinyint(1) NOT NULL DEFAULT 0,
        fraud_score int(11) NOT NULL DEFAULT 0,
        risk_score int(11) NOT NULL DEFAULT 0,
        country_code varchar(10) DEFAULT '',
        isp varchar(255) DEFAULT '',
        org varchar(255) DEFAULT '',
        as_number varchar(100) DEFAULT '',
        blocked tinyint(1) NOT NULL DEFAULT 0,
        blocked_until datetime DEFAULT NULL,
        permanent_block tinyint(1) NOT NULL DEFAULT 0,
        checked_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY ip_address (ip_address),
        KEY blocked (blocked),
        KEY fraud_score (fraud_score)
    ) $c;");

    /* ─── 17. ddos_blocks ─── */
    dbDelta("CREATE TABLE {$p}ddos_blocks (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        ip_address varchar(100) NOT NULL DEFAULT '',
        violation_count int(11) NOT NULL DEFAULT 1,
        violation_types text,
        blocked_until datetime DEFAULT NULL,
        duration int(11) NOT NULL DEFAULT 300,
        permanent tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY ip_address (ip_address),
        KEY blocked_until (blocked_until)
    ) $c;");

    /* ─── 18. low_balance_alerts ─── */
    dbDelta("CREATE TABLE {$p}low_balance_alerts (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        alert_date date NOT NULL,
        balance_at_alert decimal(12,2) NOT NULL DEFAULT 0,
        email_sent tinyint(1) NOT NULL DEFAULT 0,
        popup_shown int(11) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY customer_date (customer_id, alert_date),
        KEY customer_id (customer_id)
    ) $c;");

    /* ─── 19. hourly_adjustments (distribution rebalancing) ─── */
    dbDelta("CREATE TABLE {$p}hourly_adjustments (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        hour_slot int(11) NOT NULL DEFAULT 0,
        adjustment_date date NOT NULL,
        carryover decimal(10,6) NOT NULL DEFAULT 0,
        deviation decimal(10,6) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY camp_hour_date (campaign_id, hour_slot, adjustment_date),
        KEY campaign_id (campaign_id),
        KEY adjustment_date (adjustment_date)
    ) $c;");

    /* ─── 20. announcements ─── */
    dbDelta("CREATE TABLE {$p}announcements (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        target varchar(20) NOT NULL DEFAULT 'all',
        type varchar(20) NOT NULL DEFAULT 'info',
        title varchar(255) NOT NULL DEFAULT '',
        message text,
        is_pinned tinyint(1) NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'active',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY target (target),
        KEY status (status),
        KEY created_at (created_at)
    ) $c;");

    update_option( 'linkngon_db_version', LINKNGON_VERSION );
}
