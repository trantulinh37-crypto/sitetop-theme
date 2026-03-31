<?php
/**
 * LinkNgon V2 - Theme Functions
 * Hệ thống rút gọn link kiếm tiền
 * 
 * Mapped from CLAUDE.md: prefix taskify_ → linkngon_
 * Traffic: keyword (1step/2step/nocode) + direct (bỏ social)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LINKNGON_VERSION', '2.0.0' );
define( 'LINKNGON_DIR', get_template_directory() );
define( 'LINKNGON_URL', get_template_directory_uri() );
define( 'LINKNGON_PREFIX', 'linkngon_' );

/* ============================================================
   TIMEZONE - LUÔN DÙNG VIETNAM (UTC+7)
   ============================================================ */
function linkngon_current_time() {
    return current_time( 'Y-m-d H:i:s' );
}

/* ============================================================
   THEME SETUP
   ============================================================ */
add_action( 'after_setup_theme', function() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption' ) );
    register_nav_menus( array( 'primary' => 'Menu chính' ) );
});

/* ============================================================
   CUSTOM ROLES
   ============================================================ */
add_action( 'after_setup_theme', function() {
    // Customer role (advertiser)
    if ( ! get_role( 'customer' ) ) {
        add_role( 'customer', 'Customer', array(
            'read' => true,
        ));
    }

    // Ensure administrator has full capabilities for LinkNgon
    $admin = get_role( 'administrator' );
    if ( $admin ) {
        $caps = array(
            'manage_linkngon',
            'manage_linkngon_users',
            'manage_linkngon_customers',
            'manage_linkngon_campaigns',
            'manage_linkngon_withdrawals',
            'manage_linkngon_deposits',
            'manage_linkngon_settings',
        );
        foreach ( $caps as $cap ) {
            if ( ! $admin->has_cap( $cap ) ) {
                $admin->add_cap( $cap );
            }
        }
    }

    // Admin users also get customer role (admin = advertiser too)
    $admins = get_users( array( 'role' => 'administrator' ) );
    foreach ( $admins as $admin_user ) {
        if ( ! in_array( 'customer', (array) $admin_user->roles, true ) ) {
            $admin_user->add_role( 'customer' );
        }
    }
}, 5 );

/* ============================================================
   FIX DEPRECATED WARNINGS (WP 6.4+)
   ============================================================ */
// Remove deprecated print_emoji_styles (replaced by wp_enqueue_emoji_styles in WP 6.4)
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
// Remove deprecated wp_admin_bar_header (replaced by wp_enqueue_admin_bar_header_styles in WP 6.4)
remove_action( 'wp_head', 'wp_admin_bar_header' );

/* ============================================================
   ENQUEUE
   ============================================================ */
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'linkngon-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap',
        array(), null );
    wp_enqueue_style( 'linkngon-style', LINKNGON_URL . '/assets/css/main.css', array(), LINKNGON_VERSION );
    wp_enqueue_script( 'linkngon-main', LINKNGON_URL . '/assets/js/main.js', array('jquery'), LINKNGON_VERSION, true );
    wp_localize_script( 'linkngon-main', 'linkngon_ajax', array(
        'url'   => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'linkngon_nonce' ),
        'home'  => home_url(),
    ));
});

add_action( 'admin_enqueue_scripts', function() {
    $screen = get_current_screen();
    if ( $screen && strpos( $screen->id, 'linkngon' ) !== false ) {
        wp_enqueue_style( 'linkngon-admin', LINKNGON_URL . '/assets/css/admin.css', array(), LINKNGON_VERSION );
        wp_enqueue_script( 'linkngon-admin', LINKNGON_URL . '/assets/js/admin.js', array('jquery'), LINKNGON_VERSION, true );
        wp_localize_script( 'linkngon-admin', 'linkngon_admin', array(
            'url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('linkngon_admin_nonce'),
        ));
    }
});

/* ============================================================
   INCLUDES - Order matters (dependencies)
   ============================================================ */
$includes = array(
    'database-setup',
    'shortlink-ip',           // IP validation, rate limiting
    'ip-fraud',               // VPN/proxy detection via ip-api.com
    'behavior-analytics',     // Fraud scoring 0-100, device fingerprinting
    'anti-ddos',              // DDoS protection, 3-tier rate check
    'shortlink-functions',    // Core shortlink logic, alias system
    'shortlink-verification', // Verify & pay, user balance
    'shortlink-distribution', // Campaign distribution algorithm
    'shortlink-ajax',         // Frontend AJAX handlers
    'campaign-management',    // Campaign approval, rejection, pause/resume
    'user-management',        // Ban/unban, notifications, inactive cleanup
    'customer-management',    // Customer ban/unban, impersonation
    'withdrawal',             // Withdrawal flow
    'deposit-management',     // Deposit with bonus tiers
    'checkin',                // Daily check-in reward
    'email-notifications',    // Email system
    'low-balance-alerts',     // Low balance alerts
    'cron-cleanup',           // Cron jobs, counter sync
    'class-google-drive-upload', // ImgBB upload + WordPress fallback
    'admin-dashboard',        // Admin AJAX handlers
);
foreach ( $includes as $file ) {
    $path = LINKNGON_DIR . '/includes/' . $file . '.php';
    if ( file_exists( $path ) ) require_once $path;
}

/* ============================================================
   ACTIVATION & AUTO-CREATE TABLES
   ============================================================ */
add_action( 'after_switch_theme', function() {
    linkngon_create_tables();
    flush_rewrite_rules();
});

// Auto-create tables if DB version mismatch or tables missing
add_action( 'init', function() {
    $db_version = get_option( 'linkngon_db_version', '' );
    if ( $db_version !== LINKNGON_VERSION ) {
        if ( function_exists( 'linkngon_create_tables' ) ) {
            linkngon_create_tables();
        }
    }
}, 1 );

/* ============================================================
   REWRITE RULES (Shortlinks)
   ============================================================ */
add_action( 'init', function() {
    // /abc123 or /custom-alias → shortlink
    add_rewrite_rule( '^([a-zA-Z0-9_-]+)/?$', 'index.php?linkngon_shortlink=$matches[1]', 'top' );
    add_rewrite_rule( '^widget\.js$', 'index.php?linkngon_widget_js=1', 'top' );
});

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'linkngon_shortlink';
    $vars[] = 'linkngon_widget_js';
    return $vars;
});

add_action( 'template_redirect', function() {
    $code = get_query_var( 'linkngon_shortlink' );
    if ( $code ) {
        // DDoS check first
        if ( function_exists('linkngon_ddos_check') ) linkngon_ddos_check();
        linkngon_handle_shortlink_visit( $code );
        exit;
    }
    if ( get_query_var( 'linkngon_widget_js' ) ) {
        linkngon_serve_widget_js();
        exit;
    }
});

/* ============================================================
   PAGE TEMPLATES
   ============================================================ */
add_filter( 'theme_page_templates', function( $templates ) {
    $templates['page-user-dashboard.php']     = 'User Dashboard (Publisher)';
    $templates['page-khach-hang.php'] = 'Customer Dashboard (Advertiser)';
    $templates['page-unlock.php']             = 'Unlock Page (Countdown)';
    $templates['page-login.php']              = 'Đăng nhập';
    $templates['page-register.php']           = 'Đăng ký';
    $templates['page-forgot-password.php']    = 'Quên mật khẩu';
    return $templates;
});

add_filter( 'template_include', function( $template ) {
    if ( is_page() ) {
        $pt = get_page_template_slug();
        if ( $pt && file_exists( LINKNGON_DIR . '/' . $pt ) ) return LINKNGON_DIR . '/' . $pt;
    }
    return $template;
});

/* ============================================================
   BLOCK WP-LOGIN & WP-ADMIN
   Redirect to custom login page, keep AJAX + admin API working
   ============================================================ */
// Redirect wp-login.php to /dang-nhap
add_action( 'login_init', function() {
    // Allow logout action
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'logout' ) return;
    // Allow password reset from email link
    if ( isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'rp', 'resetpass' ), true ) ) return;
    // Allow POST for lost password (WP core handler)
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'lostpassword' ) return;

    $redirect = is_user_logged_in() ? home_url( '/nguoi-dung' ) : home_url( '/dang-nhap' );
    wp_safe_redirect( $redirect );
    exit;
});

// Redirect wp-admin to /dang-nhap for non-admins, /dashboard for logged-in non-admins
add_action( 'admin_init', function() {
    // Always allow AJAX requests
    if ( wp_doing_ajax() ) return;
    // Always allow admin-post.php
    if ( strpos( $_SERVER['SCRIPT_FILENAME'] ?? '', 'admin-post.php' ) !== false ) return;
    // Allow admins and LinkNgon managers to access wp-admin
    if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_linkngon' ) ) return;

    if ( is_user_logged_in() ) {
        wp_safe_redirect( home_url( '/nguoi-dung' ) );
    } else {
        wp_safe_redirect( home_url( '/dang-nhap' ) );
    }
    exit;
});

// Hide admin bar for non-admins
add_action( 'after_setup_theme', function() {
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_linkngon' ) ) {
        show_admin_bar( false );
    }
}, 20 );

/* ============================================================
   ADMIN MENU (only for admins who can still access wp-admin)
   ============================================================ */
add_action( 'admin_menu', function() {
    add_menu_page( 'LinkNgon', 'LinkNgon', 'manage_linkngon', 'linkngon-dashboard', function() {
        include LINKNGON_DIR . '/page-admin-dashboard.php';
    }, 'dashicons-admin-links', 2 ); // Position 2 = right after Dashboard
    add_submenu_page( 'linkngon-dashboard', 'Settings', 'Cài đặt', 'manage_linkngon_settings', 'linkngon-settings', function() {
        include LINKNGON_DIR . '/includes/admin/settings.php';
    });

    // Remove unnecessary admin menus
    remove_menu_page( 'index.php' );                // WP Dashboard
    remove_menu_page( 'edit.php' );                  // Posts
    remove_menu_page( 'edit-comments.php' );         // Comments
    remove_menu_page( 'themes.php' );                // Appearance

    remove_menu_page( 'users.php' );                 // Default Users (replaced by custom)

    // Custom Users page
    add_menu_page( 'Người dùng', 'Người dùng', 'manage_linkngon_users', 'linkngon-users', function() {
        echo '<script>window.location.href="' . admin_url( 'users.php?role=subscriber' ) . '";</script>';
    }, 'dashicons-admin-users', 35 );

    // Custom Customers page
    add_menu_page( 'Khách hàng', 'Khách hàng', 'manage_linkngon_customers', 'linkngon-customers', function() {
        echo '<script>window.location.href="' . admin_url( 'users.php?role=customer' ) . '";</script>';
    }, 'dashicons-store', 36 );
});

// Redirect /wp-admin/ to LinkNgon dashboard
add_action( 'admin_init', function() {
    if ( wp_doing_ajax() ) return;
    if ( strpos( $_SERVER['SCRIPT_FILENAME'] ?? '', 'admin-post.php' ) !== false ) return;
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_linkngon' ) ) return;

    global $pagenow;
    if ( $pagenow === 'index.php' && ! isset( $_GET['page'] ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=linkngon-dashboard' ) );
        exit;
    }
}, 999 );

/* ============================================================
   HELPERS
   ============================================================ */

/** Get dashboard URL by user role */
function linkngon_get_dashboard_url( $user = null ) {
    if ( ! $user ) {
        $user = wp_get_current_user();
    }
    if ( ! $user || ! $user->ID ) {
        return home_url( '/nguoi-dung' );
    }
    if ( in_array( 'administrator', (array) $user->roles, true ) ) {
        return admin_url();
    }
    if ( in_array( 'customer', (array) $user->roles, true ) ) {
        return home_url( '/khach-hang' );
    }
    return home_url( '/nguoi-dung' );
}

/** Format VND */
function linkngon_format_money( $amount ) {
    return number_format( (float) $amount, 0, ',', '.' ) . 'đ';
}

/** Generate unique shortcode — defined in includes/shortlink-functions.php */

/** Get user IP — defined in includes/shortlink-ip.php (Cloudflare priority) */

/** Get/set option */
function linkngon_get_option( $key, $default = '' ) {
    return get_option( 'linkngon_' . $key, $default );
}
function linkngon_update_option( $key, $value ) {
    return update_option( 'linkngon_' . $key, $value );
}

/* ============================================================
   AJAX: Customer Create Campaign
   ============================================================ */
add_action( 'wp_ajax_linkngon_customer_create_campaign', function() {
    check_ajax_referer( 'linkngon_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    $user_id = get_current_user_id();
    global $wpdb;
    $prefix = $wpdb->prefix . 'linkngon_';

    $task_type    = sanitize_text_field( $_POST['task_type'] ?? 'keyword_search' );
    $keyword      = sanitize_text_field( $_POST['keyword'] ?? '' );
    $target_url   = esc_url_raw( $_POST['target_url'] ?? '' );
    $title        = sanitize_text_field( $_POST['title'] ?? '' );
    $traffic_type = sanitize_text_field( $_POST['traffic_type'] ?? '1step' );
    $onsite_time  = intval( $_POST['onsite_time'] ?? 70 );
    $daily_traffic = max( 1, intval( $_POST['daily_traffic'] ?? 10 ) );
    $days         = max( 1, intval( $_POST['days'] ?? 15 ) );
    $quantity     = $daily_traffic * $days;

    if ( empty( $target_url ) ) wp_send_json_error( 'Vui lòng nhập URL' );
    if ( $task_type === 'keyword_search' && empty( $keyword ) ) wp_send_json_error( 'Vui lòng nhập từ khóa' );
    if ( empty( $title ) ) $title = $keyword ?: parse_url( $target_url, PHP_URL_HOST );

    // Check customer balance
    $min_balance = floatval( linkngon_get_option( 'customer_min_balance', 20000 ) );
    if ( function_exists( 'linkngon_get_customer_balance_amount' ) ) {
        $balance = linkngon_get_customer_balance_amount( $user_id );
        if ( $balance !== false && $balance < $min_balance ) {
            wp_send_json_error( 'Số dư không đủ. Yêu cầu tối thiểu ' . linkngon_format_money( $min_balance ) );
        }
    }

    // Get price
    $price_key = '';
    if ( $task_type === 'keyword_search' ) $price_key = 'keyword_price_' . $traffic_type;
    else $price_key = 'direct_price_' . $traffic_type;
    $price_per_view = floatval( linkngon_get_option( $price_key, 1200 ) );

    // Onsite extra cost
    $onsite_extra = array( 70 => 0, 80 => 0, 90 => 100, 100 => 200, 120 => 250, 150 => 300 );
    $price_per_view += $onsite_extra[ $onsite_time ] ?? 0;

    // User reward
    $reward_pct  = floatval( linkngon_get_option( 'keyword_user_reward_percent', 80 ) );
    $user_reward = floor( $price_per_view * $reward_pct / 100 );

    // Create order
    $wpdb->insert( $prefix . 'customer_orders', array(
        'customer_id'       => $user_id,
        'customer_username' => wp_get_current_user()->user_login,
        'task_type'         => $task_type,
        'title'             => $title,
        'task_url'          => $target_url,
        'quantity'          => $quantity,
        'completed'         => 0,
        'price_per_task'    => $price_per_view,
        'total_amount'      => $price_per_view * $quantity,
        'amount_spent'      => 0,
        'status'            => 'pending',
        'created_at'        => linkngon_current_time(),
        'updated_at'        => linkngon_current_time(),
    ));
    $order_id = $wpdb->insert_id;
    if ( ! $order_id ) wp_send_json_error( 'Lỗi tạo đơn hàng' );

    // Handle screenshot uploads
    $screenshot_desktop_url = '';
    $screenshot_mobile_url  = '';
    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $upload_overrides = array( 'test_form' => false );
    foreach ( array( 'screenshot_desktop' => 'screenshot_desktop_url', 'screenshot_mobile' => 'screenshot_mobile_url' ) as $field => $var ) {
        if ( ! empty( $_FILES[ $field ]['name'] ) ) {
            $uploaded = wp_handle_upload( $_FILES[ $field ], $upload_overrides );
            if ( $uploaded && ! isset( $uploaded['error'] ) ) {
                $$var = $uploaded['url'];
            }
        }
    }

    // Create campaign
    $wpdb->insert( $prefix . 'keyword_campaigns', array(
        'customer_id'            => $user_id,
        'order_id'               => $order_id,
        'title'                  => $title,
        'keyword'                => $keyword,
        'target_url'             => $target_url,
        'traffic_type'           => $traffic_type,
        'onsite_time'            => $onsite_time,
        'quantity'               => $quantity,
        'completed'              => 0,
        'price_per_view'         => $price_per_view,
        'user_reward'            => $user_reward,
        'daily_traffic'          => $daily_traffic,
        'screenshot_desktop_url' => $screenshot_desktop_url,
        'screenshot_mobile_url'  => $screenshot_mobile_url,
        'status'                 => 'pending',
        'created_at'             => linkngon_current_time(),
        'updated_at'             => linkngon_current_time(),
    ));

    if ( ! $wpdb->insert_id ) wp_send_json_error( 'Lỗi tạo chiến dịch' );

    wp_send_json_success( 'Chiến dịch đã được tạo thành công' );
});

/* ============================================================
   AJAX: Update Profile (email + phone)
   ============================================================ */
add_action( 'wp_ajax_linkngon_update_profile', function() {
    check_ajax_referer( 'linkngon_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    $user_id = get_current_user_id();
    $email   = sanitize_email( $_POST['email'] ?? '' );
    $phone   = sanitize_text_field( $_POST['phone'] ?? '' );

    if ( empty( $email ) || ! is_email( $email ) ) {
        wp_send_json_error( 'Email không hợp lệ' );
    }

    // Check email uniqueness
    $existing = email_exists( $email );
    if ( $existing && $existing !== $user_id ) {
        wp_send_json_error( 'Email đã được sử dụng bởi tài khoản khác' );
    }

    wp_update_user( array( 'ID' => $user_id, 'user_email' => $email ) );
    update_user_meta( $user_id, 'phone', $phone );

    wp_send_json_success( 'Cập nhật thành công' );
});

/* ============================================================
   AJAX: Change Password
   ============================================================ */
add_action( 'wp_ajax_linkngon_change_password', function() {
    check_ajax_referer( 'linkngon_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    $user = wp_get_current_user();
    $current  = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
        wp_send_json_error( 'Mật khẩu hiện tại không đúng' );
    }
    if ( strlen( $new_pass ) < 6 ) {
        wp_send_json_error( 'Mật khẩu mới tối thiểu 6 ký tự' );
    }
    if ( $new_pass !== $confirm ) {
        wp_send_json_error( 'Mật khẩu xác nhận không khớp' );
    }

    wp_set_password( $new_pass, $user->ID );
    // Re-login after password change
    wp_set_auth_cookie( $user->ID );

    wp_send_json_success( 'Đổi mật khẩu thành công' );
});

/** Traffic types (V2: bỏ social) */
function linkngon_get_traffic_types() {
    return array(
        'keyword_search' => array(
            '1step' => 'Keyword 1-Step',
            '2step' => 'Keyword 2-Step',
            'nocode' => 'Keyword No-Code',
        ),
        'traffic_direct' => array(
            '1step' => 'Direct 1-Step',
            '2step' => 'Direct 2-Step',
            'nocode' => 'Direct No-Code',
        ),
    );
}

/** Get reward amount by traffic_type + campaign_type (Flow 8 from CLAUDE.md) */
function linkngon_get_reward_amount( $campaign ) {
    // Priority 1: Campaign-specific user_reward
    if ( !empty($campaign->user_reward) && $campaign->user_reward > 0 ) {
        return (float) $campaign->user_reward;
    }
    // Priority 2: Settings by traffic_type + campaign_type
    $traffic = $campaign->traffic_type ?? 'keyword_search';
    $type = $campaign->campaign_type ?? '1step';
    $key = '';
    if ( strpos($traffic, 'keyword') !== false ) {
        $key = 'keyword_user_' . $type; // e.g. keyword_user_1step
    } elseif ( strpos($traffic, 'direct') !== false ) {
        $key = 'direct_user_' . $type;
    }
    if ( $key ) {
        $val = linkngon_get_option( $key, 0 );
        if ( $val > 0 ) return (float) $val;
    }
    // Priority 3: Fallback defaults
    $defaults = array( '1step' => 800, '2step' => 1000, 'nocode' => 800 );
    return (float) ( $defaults[ $type ] ?? 800 );
}

/** Widget JS serve - Widget LUÔN HIỆN (V2: bỏ logic ẩn/hiện) */
function linkngon_serve_widget_js() {
    header( 'Content-Type: application/javascript; charset=UTF-8' );
    header( 'Cache-Control: no-cache, no-store, must-revalidate' );
    include LINKNGON_DIR . '/widget.js.php';
    exit;
}

/* ============================================================
   CRON SCHEDULES
   ============================================================ */
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['every_5_min'] = array( 'interval' => 300, 'display' => 'Every 5 minutes' );
    $schedules['every_15_min'] = array( 'interval' => 900, 'display' => 'Every 15 minutes' );
    return $schedules;
});

add_action( 'init', function() {
    $crons = array(
        'linkngon_5min_cron'    => 'every_5_min',
        'linkngon_15min_cron'   => 'every_15_min',
        'linkngon_hourly_cron'  => 'hourly',
        'linkngon_daily_cron'   => 'daily',
    );
    foreach ( $crons as $hook => $schedule ) {
        if ( ! wp_next_scheduled( $hook ) ) wp_schedule_event( time(), $schedule, $hook );
    }
});

// 5 min: auto-pause insufficient campaigns
add_action( 'linkngon_5min_cron', function() {
    if ( function_exists('linkngon_auto_pause_insufficient_campaigns') )
        linkngon_auto_pause_insufficient_campaigns();
});

// 15 min: auto-resume paused campaigns
add_action( 'linkngon_15min_cron', function() {
    if ( function_exists('linkngon_auto_resume_paused_campaigns') )
        linkngon_auto_resume_paused_campaigns();
});

// Hourly: distribution rebalance, cache, low balance alerts
add_action( 'linkngon_hourly_cron', function() {
    if ( function_exists('linkngon_update_hourly_adjustments') )
        linkngon_update_hourly_adjustments();
    if ( function_exists('linkngon_cache_eligible_campaigns') )
        linkngon_cache_eligible_campaigns();
    if ( function_exists('linkngon_check_low_balance_alerts') )
        linkngon_check_low_balance_alerts();
});

// Daily: cleanup, counter sync
add_action( 'linkngon_daily_cron', function() {
    if ( function_exists('linkngon_run_database_cleanup') )
        linkngon_run_database_cleanup();
    if ( function_exists('linkngon_sync_shortlink_counters') )
        linkngon_sync_shortlink_counters();
    if ( function_exists('linkngon_sync_campaign_counters') )
        linkngon_sync_campaign_counters();
    if ( function_exists('linkngon_cleanup_inactive_users') )
        linkngon_cleanup_inactive_users();
    if ( function_exists('linkngon_auto_delete_old_customers') )
        linkngon_auto_delete_old_customers();
});
