<?php
/**
 * LinkNgon V2 - Theme Functions
 * Hệ thống rút gọn link kiếm tiền
 * 
 * Mapped from CLAUDE.md: prefix taskify_ → linkngon_
 * Traffic: keyword (1step/2step/nocode) + direct (bỏ social)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LINKNGON_VERSION', '2.1.0' );
define( 'LINKNGON_DIR', get_template_directory() );
define( 'LINKNGON_URL', get_template_directory_uri() );
define( 'LINKNGON_PREFIX', 'linkngon_' );

/* ============================================================
   WIDGET.JS - Serve widget khi request match
   Cách 1: /?linkngon_widget=js (luôn hoạt động)
   Cách 2: /widget.js (cần .htaccess rewrite)
   ============================================================ */
add_action( 'init', function() {
    // Query param: /?linkngon_widget=js
    if ( isset( $_GET['linkngon_widget'] ) && $_GET['linkngon_widget'] === 'js' ) {
        linkngon_serve_widget_js();
    }
    // Direct URI: /widget.js (when .htaccess passes to WP)
    $uri = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    if ( $uri === 'widget.js' ) {
        linkngon_serve_widget_js();
    }
    // /widget-captcha/ → serve captcha iframe
    if ( $uri === 'widget-captcha' || strpos( $uri, 'widget-captcha' ) === 0 ) {
        include LINKNGON_DIR . '/page-widget-captcha.php';
    }
}, 0 );

/* ============================================================
   TIMEZONE - LUÔN DÙNG VIETNAM (UTC+7)
   Set PHP + MySQL timezone đồng nhất để tất cả
   date(), time(), CURRENT_TIMESTAMP đều là Vietnam
   ============================================================ */
date_default_timezone_set( 'Asia/Ho_Chi_Minh' );

// Set MySQL timezone = Vietnam khi kết nối DB
add_action( 'init', function() {
    global $wpdb;
    $wpdb->query( "SET time_zone = '+07:00'" );
}, 1 );

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

// Admin menu UI + tab caching (tách ra includes/admin-menu-ui.php)

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
    'email-notifications',    // Email system
    'low-balance-alerts',     // Low balance alerts
    'cron-cleanup',           // Cron jobs, counter sync
    'class-google-drive-upload', // ImgBB upload + WordPress fallback
    'admin-dashboard',        // Admin AJAX handlers
    'settings-management',    // Admin save settings (pricing, fraud, SMTP, etc.)
    'payment-settings',       // Bank QR, USDT config
    'admin-menu-ui',          // Admin sidebar labels, collapsible WP group, tab caching
    'admin-routing',          // Block wp-login, wp-admin redirects, hide admin bar
    'admin-deposit-ajax',     // AJAX: admin get/process deposits, customer create deposit
    'customer-campaign-ajax', // AJAX: customer campaign CRUD, shortlink edit, profile
    'admin-load-more',        // AJAX: user dashboard load more (links, transactions, withdrawals)
    'customer-load-more',     // AJAX: customer dashboard load more (campaigns, visits, deposits)
    'floating-contact',       // Floating contact button (Telegram/Zalo/Email)
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

// Auto-install custom db-error.php to wp-content/
add_action( 'admin_init', function() {
    $src = LINKNGON_DIR . '/db-error.php';
    $dst = WP_CONTENT_DIR . '/db-error.php';
    if ( file_exists( $src ) && ( ! file_exists( $dst ) || md5_file( $src ) !== md5_file( $dst ) ) ) {
        @copy( $src, $dst );
    }
}, 99 );

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
// Shortlink: parse_request catches 6-char codes before WordPress processes as page
add_action( 'parse_request', function( $wp ) {
    $request = trim( $wp->request, '/' );
    if ( empty($request) || strpos($request, '/') !== false ) return;
    // Only intercept 6-char codes that exist as shortlinks
    if ( ! preg_match('/^[a-zA-Z0-9]{6}$/', $request) ) return;
    if ( ! function_exists('linkngon_get_shortlink_by_code_or_alias') ) return;
    $sl = linkngon_get_shortlink_by_code_or_alias( $request );
    if ( ! $sl ) return; // Not a shortlink — let WP handle as normal page
    $wp->query_vars['linkngon_shortlink'] = $request;
}, 1 );

add_action( 'init', function() {
    // Only match 6-char alphanumeric codes (shortlink format)
    // NOT all slugs — that blocks WP pages like dang-nhap, nguoi-dung, etc.
    add_rewrite_rule( '^([a-zA-Z0-9]{6})/?$', 'index.php?linkngon_shortlink=$matches[1]', 'top' );
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
        // Verify shortlink exists in DB before handling (don't block WP pages)
        if ( function_exists( 'linkngon_get_shortlink_by_code_or_alias' ) ) {
            $sl = linkngon_get_shortlink_by_code_or_alias( $code );
            if ( ! $sl ) return; // Not a shortlink — let WP handle normally
        }
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
   VIRTUAL PAGES - serve template files for slugs that don't have
   a WP page in the database. This ensures /dang-nhap/, /dang-ky/,
   /quen-mat-khau/, /nguoi-dung/, /khach-hang/ always work even
   without manually creating WP pages.
   ============================================================ */
add_action( 'template_redirect', function() {
    if ( is_404() ) {
        // Map slug → template file
        $slug_map = array(
            'dang-nhap'      => 'page-login.php',
            'dang-ky'        => 'page-register.php',
            'quen-mat-khau'  => 'page-forgot-password.php',
            'nguoi-dung'     => 'page-user-dashboard.php',
            'khach-hang'     => 'page-customer-dashboard.php',
        );

        $request = trim( $_SERVER['REQUEST_URI'] ?? '', '/' );
        $request = strtok( $request, '?' );
        $request = rtrim( $request, '/' );

        // /admin/ → redirect to WP admin
        if ( $request === 'admin' ) {
            wp_safe_redirect( admin_url() );
            exit;
        }

        if ( isset( $slug_map[ $request ] ) ) {
            $tpl = LINKNGON_DIR . '/' . $slug_map[ $request ];
            if ( file_exists( $tpl ) ) {
                status_header( 200 );
                include $tpl;
                exit;
            }
        }
    }
}, 1 );

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

// Admin routing: wp-login redirect, wp-admin block (tách ra includes/admin-routing.php)

/* ============================================================
   ADMIN MENU (only for admins who can still access wp-admin)
   ============================================================ */
add_action( 'admin_menu', function() {
    // ── NHÀ XUẤT BẢN ──
    add_menu_page( 'Người dùng', 'Người dùng', 'manage_linkngon_users', 'linkngon-users', function() {
        include LINKNGON_DIR . '/includes/admin/tabs/tab-users.php';
    }, 'dashicons-admin-users', 3 );

    add_menu_page( 'Shortlinks', 'Shortlinks', 'manage_linkngon', 'linkngon-links', function() {
        include LINKNGON_DIR . '/includes/admin/tabs/tab-links.php';
    }, 'dashicons-admin-links', 4 );

    add_menu_page( 'Rút tiền', 'Rút tiền', 'manage_linkngon', 'linkngon-withdrawals', function() {
        include LINKNGON_DIR . '/includes/admin/tabs/tab-withdrawals.php';
    }, 'dashicons-bank', 5 );

    // ── KHÁCH HÀNG ──
    add_menu_page( 'Khách hàng', 'Khách hàng', 'manage_linkngon_customers', 'linkngon-customers', function() {
        include LINKNGON_DIR . '/includes/admin/tabs/tab-customers.php';
    }, 'dashicons-store', 11 );

    add_menu_page( 'Nạp tiền', 'Nạp tiền', 'manage_linkngon', 'linkngon-deposits', function() {
        include LINKNGON_DIR . '/includes/admin/tabs/tab-deposits.php';
    }, 'dashicons-money-alt', 12 );

    add_menu_page( 'Chiến dịch', 'Chiến dịch', 'manage_linkngon', 'linkngon-campaigns', function() {
        include LINKNGON_DIR . '/includes/admin/tabs/tab-campaigns.php';
    }, 'dashicons-megaphone', 13 );

    // ── HỆ THỐNG ──
    add_menu_page( 'Visits', 'Visits', 'manage_linkngon', 'linkngon-visits', function() {
        include LINKNGON_DIR . '/includes/admin/tabs/tab-visits.php';
    }, 'dashicons-visibility', 21 );

    add_menu_page( 'Cài đặt LN', 'Cài đặt LN', 'manage_linkngon_settings', 'linkngon-settings', function() {
        include LINKNGON_DIR . '/includes/admin/tabs/tab-settings.php';
    }, 'dashicons-admin-generic', 22 );

    // Remove unnecessary WP menus
    remove_menu_page( 'index.php' );
    remove_menu_page( 'edit.php' );
    remove_menu_page( 'edit-comments.php' );
    remove_menu_page( 'themes.php' );
    remove_menu_page( 'users.php' );

});

// ── WORDPRESS ── Gom các mục WP mặc định vào cuối sidebar (chạy sau tất cả menu registered)
add_action( 'admin_menu', function() {
    global $menu;
    $wp_items = array( 'upload.php', 'edit.php?post_type=page', 'plugins.php', 'tools.php', 'options-general.php' );
    $wp_pos = 200; // Start position for WP items group (high to avoid collisions)
    foreach ( $wp_items as $slug ) {
        foreach ( $menu as $pos => $item ) {
            if ( isset( $item[2] ) && $item[2] === $slug ) {
                unset( $menu[$pos] );
                $menu[$wp_pos] = $item;
                $wp_pos++;
                break;
            }
        }
    }
}, 999 );

// Redirect /wp-admin/ to LinkNgon dashboard (trong includes/admin-routing.php)

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

// AJAX: Deposits (tách ra includes/admin-deposit-ajax.php)

// AJAX: Customer campaign CRUD + shortlink + profile (tách ra includes/customer-campaign-ajax.php)

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

/** Get reward amount by campaign_type + traffic_type (Flow 8 from CLAUDE.md) */
function linkngon_get_reward_amount( $campaign ) {
    // Priority 1: Campaign-specific user_reward
    if ( ! empty( $campaign->user_reward ) && $campaign->user_reward > 0 ) {
        return (float) $campaign->user_reward;
    }
    // Priority 2: Settings by campaign_type (keyword_search/traffic_direct) + traffic_type (1step/2step/nocode)
    $campaign_type = $campaign->campaign_type ?? 'keyword_search';
    $traffic_type = $campaign->traffic_type ?? '1step';

    if ( $campaign_type === 'keyword_search' ) {
        $key = 'keyword_user_' . $traffic_type; // keyword_user_1step, keyword_user_2step, keyword_user_nocode
    } elseif ( $campaign_type === 'traffic_direct' ) {
        $key = 'direct_user_' . $traffic_type;
    } elseif ( $campaign_type === 'traffic_social' ) {
        $key = 'social_user_' . $traffic_type;
    } else {
        $key = 'keyword_user_' . $traffic_type; // fallback
    }

    $val = linkngon_get_option( $key, 0 );
    if ( $val > 0 ) return (float) $val;

    // Priority 3: Fallback defaults
    $defaults = array( '1step' => 800, '2step' => 1000, 'nocode' => 800 );
    return (float) ( $defaults[ $traffic_type ] ?? 800 );
}

/** Widget JS serve - Widget LUÔN HIỆN (V2: bỏ logic ẩn/hiện) */
function linkngon_serve_widget_js() {
    header( 'Content-Type: application/javascript; charset=UTF-8' );
    header( 'Cache-Control: no-cache, no-store, must-revalidate' );
    header( 'Access-Control-Allow-Origin: *' );
    include LINKNGON_DIR . '/widget.js.php';
    exit;
}

/** CORS headers for widget AJAX (cross-origin from target websites) */
add_action( 'plugins_loaded', function() {
    $action = $_REQUEST['action'] ?? '';
    if ( empty( $action ) ) return;
    $widget_actions = array(
        'linkngon_widget_verify_access', 'linkngon_widget_start_timer', 'linkngon_widget_captcha',
        'linkngon_unlock_heartbeat', 'linkngon_get_code', 'linkngon_track_adblock',
        'linkngon_report_behavior', 'linkngon_check_code_ready',
        'linkngon_track_google_click', 'linkngon_track_direct_click',
        'linkngon_track_social_click', 'linkngon_verify_shortlink_code',
    );
    if ( in_array( $action, $widget_actions ) ) {
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Access-Control-Allow-Methods: POST, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Content-Type' );
        // Handle preflight
        if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) { exit; }
    }
});

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

// 5 min: auto-pause insufficient campaigns + cleanup expired transients
add_action( 'linkngon_5min_cron', function() {
    if ( function_exists('linkngon_auto_pause_insufficient_campaigns') )
        linkngon_auto_pause_insufficient_campaigns();
    if ( function_exists('linkngon_cleanup_expired_transients') )
        linkngon_cleanup_expired_transients();
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


// AJAX: Load more - user + customer (tách ra includes/admin-load-more.php + customer-load-more.php)

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

// One-time counter sync after deploy (runs once per code version)
add_action( 'admin_init', function() {
    $ver = 'counter_sync_v3';
    if ( get_option( "linkngon_{$ver}" ) ) return;
    if ( function_exists('linkngon_sync_shortlink_counters') ) linkngon_sync_shortlink_counters();
    if ( function_exists('linkngon_sync_campaign_counters') ) linkngon_sync_campaign_counters();
    update_option( "linkngon_{$ver}", 1 );
}, 99 );


// Floating contact button (tách ra includes/floating-contact.php)


/* ONE-TIME FIX: Đã xóa — script bù thưởng from_google đã chạy xong hoặc gây DB overload.
   Nếu cần chạy lại, dùng AJAX diagnostic endpoint thay vì admin_init. */

