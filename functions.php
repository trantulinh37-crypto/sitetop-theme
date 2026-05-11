<?php
/**
 * Traffictop.net V2 - Theme Functions
 * Hệ thống rút gọn link kiếm tiền
 * 
 * Mapped from CLAUDE.md: prefix taskify_ → traffictop_
 * Traffic: keyword (1step/2step/nocode) + direct (bỏ social)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TRAFFICTOP_VERSION', '2.4.2' );
define( 'TRAFFICTOP_DIR', get_template_directory() );
define( 'TRAFFICTOP_URL', get_template_directory_uri() );
define( 'TRAFFICTOP_PREFIX', 'traffictop_' );

// Disable external wp-cron.php hits (prevents DDoS abuse via cron endpoint)
// WordPress will run cron internally on page loads instead
if ( ! defined( 'DISABLE_WP_CRON' ) ) {
    define( 'DISABLE_WP_CRON', true );
}

/* ============================================================
   WIDGET.JS - Serve widget khi request match
   Cách 1: /?traffictop_widget=js (luôn hoạt động)
   Cách 2: /widget.js (cần .htaccess rewrite)
   ============================================================ */
add_action( 'init', function() {
    // Query param: /?traffictop_widget=js
    if ( isset( $_GET['traffictop_widget'] ) && $_GET['traffictop_widget'] === 'js' ) {
        traffictop_serve_widget_js();
    }
    // Direct URI: /widget.js (when .htaccess passes to WP)
    $uri = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    if ( $uri === 'widget.js' ) {
        traffictop_serve_widget_js();
    }
    // /widget-captcha/ → serve captcha iframe
    if ( $uri === 'widget-captcha' || strpos( $uri, 'widget-captcha' ) === 0 ) {
        include TRAFFICTOP_DIR . '/page-widget-captcha.php';
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

function traffictop_current_time() {
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

    // Ensure administrator has full capabilities for Traffictop.net
    $admin = get_role( 'administrator' );
    if ( $admin ) {
        $caps = array(
            'manage_traffictop',
            'manage_traffictop_users',
            'manage_traffictop_customers',
            'manage_traffictop_campaigns',
            'manage_traffictop_withdrawals',
            'manage_traffictop_deposits',
            'manage_traffictop_settings',
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
    wp_enqueue_style( 'traffictop-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap',
        array(), null );
    wp_enqueue_style( 'traffictop-style', TRAFFICTOP_URL . '/assets/css/main.css', array(), TRAFFICTOP_VERSION );
    wp_enqueue_script( 'traffictop-main', TRAFFICTOP_URL . '/assets/js/main.js', array('jquery'), TRAFFICTOP_VERSION, true );
    wp_localize_script( 'traffictop-main', 'traffictop_ajax', array(
        'url'   => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'traffictop_nonce' ),
        'home'  => home_url(),
    ));
});

add_action( 'admin_enqueue_scripts', function() {
    $screen = get_current_screen();
    if ( $screen && strpos( $screen->id, 'traffictop' ) !== false ) {
        wp_enqueue_style( 'traffictop-admin', TRAFFICTOP_URL . '/assets/css/admin.css', array(), TRAFFICTOP_VERSION );
        wp_enqueue_script( 'traffictop-admin', TRAFFICTOP_URL . '/assets/js/admin.js', array('jquery'), TRAFFICTOP_VERSION, true );
        wp_localize_script( 'traffictop-admin', 'traffictop_admin', array(
            'url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('traffictop_admin_nonce'),
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
    'rest-api',               // REST API endpoints (POST /wp-json/traffictop/v1/shortlinks)
);
foreach ( $includes as $file ) {
    $path = TRAFFICTOP_DIR . '/includes/' . $file . '.php';
    if ( file_exists( $path ) ) require_once $path;
}

/* ============================================================
   ONE-TIME MIGRATION: linkngon_ → traffictop_ (DB tables, options, user meta)
   Runs once on first load after the rename. Priority -999 ensures it
   executes before any other init hook accesses the renamed tables.
   ============================================================ */
add_action( 'init', function() {
    if ( get_option( 'traffictop_migrated_from_linkngon' ) ) return;

    global $wpdb;

    // Check if old tables exist — if not, this is a fresh install
    $old_table = $wpdb->prefix . 'linkngon_shortlink_visits';
    $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $old_table ) );
    if ( ! $exists ) {
        update_option( 'traffictop_migrated_from_linkngon', time() );
        return;
    }

    // 1. Rename all wp_linkngon_* tables → wp_traffictop_*
    $tables = $wpdb->get_col(
        $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->prefix . 'linkngon_%' )
    );
    foreach ( $tables as $old_name ) {
        $new_name = str_replace(
            $wpdb->prefix . 'linkngon_',
            $wpdb->prefix . 'traffictop_',
            $old_name
        );
        if ( $old_name !== $new_name ) {
            $wpdb->query( "RENAME TABLE `{$old_name}` TO `{$new_name}`" );
        }
    }

    // 2. Rename linkngon_* options → traffictop_*
    $wpdb->query(
        "UPDATE {$wpdb->options}
         SET option_name = CONCAT('traffictop_', SUBSTRING(option_name, 10))
         WHERE option_name LIKE 'linkngon\\_%%'
         AND option_name NOT LIKE '\\_transient%%'"
    );

    // 3. Rename transients
    $wpdb->query(
        "UPDATE {$wpdb->options}
         SET option_name = REPLACE(option_name, '_transient_linkngon_', '_transient_traffictop_')
         WHERE option_name LIKE '\\_transient\\_linkngon\\_%%'"
    );
    $wpdb->query(
        "UPDATE {$wpdb->options}
         SET option_name = REPLACE(option_name, '_transient_timeout_linkngon_', '_transient_timeout_traffictop_')
         WHERE option_name LIKE '\\_transient\\_timeout\\_linkngon\\_%%'"
    );

    // 4. Rename user meta keys
    $wpdb->query(
        "UPDATE {$wpdb->usermeta}
         SET meta_key = CONCAT('traffictop_', SUBSTRING(meta_key, 10))
         WHERE meta_key LIKE 'linkngon\\_%%'"
    );

    update_option( 'traffictop_migrated_from_linkngon', time() );
    flush_rewrite_rules();
}, -999 );

/* ============================================================
   ACTIVATION & AUTO-CREATE TABLES
   ============================================================ */
add_action( 'after_switch_theme', function() {
    traffictop_create_tables();
    flush_rewrite_rules();
});

// Auto-install custom db-error.php to wp-content/
add_action( 'admin_init', function() {
    $src = TRAFFICTOP_DIR . '/db-error.php';
    $dst = WP_CONTENT_DIR . '/db-error.php';
    if ( file_exists( $src ) && ( ! file_exists( $dst ) || md5_file( $src ) !== md5_file( $dst ) ) ) {
        @copy( $src, $dst );
    }
}, 99 );

// Auto-create tables if DB version mismatch or tables missing
add_action( 'init', function() {
    $db_version = get_option( 'traffictop_db_version', '' );
    if ( $db_version !== TRAFFICTOP_VERSION ) {
        if ( function_exists( 'traffictop_create_tables' ) ) {
            traffictop_create_tables();
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
    if ( ! function_exists('traffictop_get_shortlink_by_code_or_alias') ) return;
    $sl = traffictop_get_shortlink_by_code_or_alias( $request );
    if ( ! $sl ) return; // Not a shortlink — let WP handle as normal page
    $wp->query_vars['traffictop_shortlink'] = $request;
}, 1 );

add_action( 'init', function() {
    // Only match 6-char alphanumeric codes (shortlink format)
    // NOT all slugs — that blocks WP pages like dang-nhap, nguoi-dung, etc.
    add_rewrite_rule( '^([a-zA-Z0-9]{6})/?$', 'index.php?traffictop_shortlink=$matches[1]', 'top' );
    add_rewrite_rule( '^widget\.js$', 'index.php?traffictop_widget_js=1', 'top' );
});

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'traffictop_shortlink';
    $vars[] = 'traffictop_widget_js';
    return $vars;
});

add_action( 'template_redirect', function() {
    $code = get_query_var( 'traffictop_shortlink' );
    if ( $code ) {
        // Verify shortlink exists in DB before handling (don't block WP pages)
        if ( function_exists( 'traffictop_get_shortlink_by_code_or_alias' ) ) {
            $sl = traffictop_get_shortlink_by_code_or_alias( $code );
            if ( ! $sl ) return; // Not a shortlink — let WP handle normally
        }
        if ( function_exists('traffictop_ddos_check') ) traffictop_ddos_check();
        traffictop_handle_shortlink_visit( $code );
        exit;
    }
    if ( get_query_var( 'traffictop_widget_js' ) ) {
        traffictop_serve_widget_js();
        exit;
    }
});

/* ============================================================
   VIRTUAL PAGES - serve template files for slugs that don't have
   a WP page in the database. This ensures /dang-nhap/, /dang-ky/,
   /quen-mat-khau/, /user/, /customer/ always work even
   without manually creating WP pages.
   ============================================================ */
add_action( 'template_redirect', function() {
    if ( is_404() ) {
        // Map slug → template file
        $slug_map = array(
            'dang-nhap'      => 'page-login.php',
            'dang-ky'        => 'page-register.php',
            'quen-mat-khau'  => 'page-forgot-password.php',
            'user'           => 'page-user-dashboard.php',
            'customer'       => 'page-customer-dashboard.php',
            'dieu-khoan'     => 'page-dieu-khoan.php',
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
            $tpl = TRAFFICTOP_DIR . '/' . $slug_map[ $request ];
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
        if ( $pt && file_exists( TRAFFICTOP_DIR . '/' . $pt ) ) return TRAFFICTOP_DIR . '/' . $pt;
    }
    return $template;
});

// Admin routing: wp-login redirect, wp-admin block (tách ra includes/admin-routing.php)

/* ============================================================
   ADMIN MENU (only for admins who can still access wp-admin)
   ============================================================ */
add_action( 'admin_menu', function() {
    // ── TỔNG QUAN ──
    add_menu_page( 'Tổng quan', 'Tổng quan', 'manage_options', 'traffictop-overview', function() {
        include TRAFFICTOP_DIR . '/includes/admin/tabs/tab-overview.php';
    }, 'dashicons-chart-area', 2 );

    // ── NHÀ XUẤT BẢN ──
    add_menu_page( 'Người dùng', 'Người dùng', 'manage_traffictop_users', 'traffictop-users', function() {
        include TRAFFICTOP_DIR . '/includes/admin/tabs/tab-users.php';
    }, 'dashicons-admin-users', 3 );

    add_menu_page( 'Shortlinks', 'Shortlinks', 'manage_traffictop', 'traffictop-links', function() {
        include TRAFFICTOP_DIR . '/includes/admin/tabs/tab-links.php';
    }, 'dashicons-admin-links', 4 );

    add_menu_page( 'Rút tiền', 'Rút tiền', 'manage_traffictop', 'traffictop-withdrawals', function() {
        include TRAFFICTOP_DIR . '/includes/admin/tabs/tab-withdrawals.php';
    }, 'dashicons-bank', 5 );

    // ── KHÁCH HÀNG ──
    add_menu_page( 'Khách hàng', 'Khách hàng', 'manage_traffictop_customers', 'traffictop-customers', function() {
        include TRAFFICTOP_DIR . '/includes/admin/tabs/tab-customers.php';
    }, 'dashicons-store', 11 );

    add_menu_page( 'Nạp tiền', 'Nạp tiền', 'manage_traffictop', 'traffictop-deposits', function() {
        include TRAFFICTOP_DIR . '/includes/admin/tabs/tab-deposits.php';
    }, 'dashicons-money-alt', 12 );

    add_menu_page( 'Chiến dịch', 'Chiến dịch', 'manage_traffictop', 'traffictop-campaigns', function() {
        include TRAFFICTOP_DIR . '/includes/admin/tabs/tab-campaigns.php';
    }, 'dashicons-megaphone', 13 );

    // ── HỆ THỐNG ──
    add_menu_page( 'Visits', 'Visits', 'manage_traffictop', 'traffictop-visits', function() {
        include TRAFFICTOP_DIR . '/includes/admin/tabs/tab-visits.php';
    }, 'dashicons-visibility', 21 );

    add_menu_page( 'Cài đặt TT', 'Cài đặt TT', 'manage_traffictop_settings', 'traffictop-settings', function() {
        include TRAFFICTOP_DIR . '/includes/admin/tabs/tab-settings.php';
    }, 'dashicons-admin-generic', 22 );

    // Remove unnecessary WP menus
    remove_menu_page( 'index.php' );
    remove_menu_page( 'edit.php' );
    remove_menu_page( 'edit-comments.php' );
    remove_menu_page( 'themes.php' );
    remove_menu_page( 'users.php' );

});

// Redirect /wp-admin/ to Tổng quan page
add_action( 'admin_init', function() {
    global $pagenow;
    if ( $pagenow === 'index.php' && empty( $_GET['page'] ) && current_user_can( 'manage_options' ) ) {
        wp_redirect( admin_url( 'admin.php?page=traffictop-overview' ) );
        exit;
    }
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

// Redirect /wp-admin/ to Traffictop.net dashboard (trong includes/admin-routing.php)

/* ============================================================
   HELPERS
   ============================================================ */

/** Get dashboard URL by user role */
function traffictop_get_dashboard_url( $user = null ) {
    if ( ! $user ) {
        $user = wp_get_current_user();
    }
    if ( ! $user || ! $user->ID ) {
        return home_url( '/user' );
    }
    if ( in_array( 'administrator', (array) $user->roles, true ) ) {
        return admin_url();
    }
    if ( in_array( 'customer', (array) $user->roles, true ) ) {
        return home_url( '/customer' );
    }
    return home_url( '/user' );
}

/** Format VND */
function traffictop_format_money( $amount ) {
    return number_format( (float) $amount, 0, ',', '.' ) . 'đ';
}

/** Generate unique shortcode — defined in includes/shortlink-functions.php */

/** Get user IP — defined in includes/shortlink-ip.php (Cloudflare priority) */

/** Get/set option */
function traffictop_get_option( $key, $default = '' ) {
    return get_option( 'traffictop_' . $key, $default );
}
function traffictop_update_option( $key, $value ) {
    return update_option( 'traffictop_' . $key, $value );
}

// AJAX: Deposits (tách ra includes/admin-deposit-ajax.php)

// AJAX: Customer campaign CRUD + shortlink + profile (tách ra includes/customer-campaign-ajax.php)

/** Traffic types (V2: bỏ social) */
function traffictop_get_traffic_types() {
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
function traffictop_get_reward_amount( $campaign ) {
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

    $val = traffictop_get_option( $key, 0 );
    if ( $val > 0 ) return (float) $val;

    // Priority 3: Fallback defaults
    $defaults = array( '1step' => 800, '2step' => 1000, 'nocode' => 800 );
    return (float) ( $defaults[ $traffic_type ] ?? 800 );
}

/** Widget JS serve - Widget LUÔN HIỆN (V2: bỏ logic ẩn/hiện) */
function traffictop_serve_widget_js() {
    header( 'Content-Type: application/javascript; charset=UTF-8' );
    header( 'Cache-Control: no-cache, no-store, must-revalidate' );
    header( 'Access-Control-Allow-Origin: *' );
    include TRAFFICTOP_DIR . '/widget.js.php';
    exit;
}

/** CORS headers for widget AJAX (cross-origin from target websites)
 *  Must use admin_init (not plugins_loaded) because admin-ajax.php calls
 *  send_origin_headers() AFTER plugins_loaded, which overrides our headers */
add_action( 'admin_init', function() {
    if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) return;
    $action = $_REQUEST['action'] ?? '';
    if ( empty( $action ) ) return;
    $widget_actions = array(
        'traffictop_widget_verify_access', 'traffictop_widget_start_timer', 'traffictop_widget_captcha',
        'traffictop_unlock_heartbeat', 'traffictop_get_code', 'traffictop_track_adblock',
        'traffictop_report_behavior', 'traffictop_check_code_ready',
        'traffictop_track_google_click', 'traffictop_track_direct_click',
        'traffictop_track_social_click', 'traffictop_verify_shortlink_code',
    );
    if ( in_array( $action, $widget_actions ) ) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ( ! empty( $origin ) ) {
            header( 'Access-Control-Allow-Origin: ' . $origin, true );
            header( 'Access-Control-Allow-Credentials: true' );
        } else {
            header( 'Access-Control-Allow-Origin: *', true );
        }
        header( 'Access-Control-Allow-Methods: POST, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Content-Type' );
        if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) { exit; }
    }
}, 0 );

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
        'traffictop_5min_cron'    => 'every_5_min',
        'traffictop_15min_cron'   => 'every_15_min',
        'traffictop_hourly_cron'  => 'hourly',
        'traffictop_daily_cron'   => 'daily',
    );
    foreach ( $crons as $hook => $schedule ) {
        if ( ! wp_next_scheduled( $hook ) ) wp_schedule_event( time(), $schedule, $hook );
    }
});

// 5 min: auto-pause insufficient campaigns + cleanup cache files + expired transients
add_action( 'traffictop_5min_cron', function() {
    if ( function_exists('traffictop_auto_pause_insufficient_campaigns') )
        traffictop_auto_pause_insufficient_campaigns();
    if ( function_exists('traffictop_ddos_cleanup_files') )
        traffictop_ddos_cleanup_files();
    if ( function_exists('traffictop_ratelimit_cleanup_files') )
        traffictop_ratelimit_cleanup_files();
    if ( function_exists('traffictop_cleanup_expired_transients') )
        traffictop_cleanup_expired_transients();
});

// 15 min: auto-resume paused campaigns
add_action( 'traffictop_15min_cron', function() {
    if ( function_exists('traffictop_auto_resume_paused_campaigns') )
        traffictop_auto_resume_paused_campaigns();
});

// Hourly: distribution rebalance, cache, low balance alerts
add_action( 'traffictop_hourly_cron', function() {
    if ( function_exists('traffictop_update_hourly_adjustments') )
        traffictop_update_hourly_adjustments();
    if ( function_exists('traffictop_cache_eligible_campaigns') )
        traffictop_cache_eligible_campaigns();
    if ( function_exists('traffictop_check_low_balance_alerts') )
        traffictop_check_low_balance_alerts();
});


// AJAX: Load more - user + customer (tách ra includes/admin-load-more.php + customer-load-more.php)

// Daily: cleanup, counter sync
add_action( 'traffictop_daily_cron', function() {
    if ( function_exists('traffictop_run_database_cleanup') )
        traffictop_run_database_cleanup();
    if ( function_exists('traffictop_sync_shortlink_counters') )
        traffictop_sync_shortlink_counters();
    if ( function_exists('traffictop_sync_campaign_counters') )
        traffictop_sync_campaign_counters();
    if ( function_exists('traffictop_cleanup_inactive_users') )
        traffictop_cleanup_inactive_users();
    if ( function_exists('traffictop_auto_delete_old_customers') )
        traffictop_auto_delete_old_customers();
});

// One-time counter sync after deploy (runs once per code version)
add_action( 'admin_init', function() {
    $ver = 'counter_sync_v3';
    if ( get_option( "traffictop_{$ver}" ) ) return;
    if ( function_exists('traffictop_sync_shortlink_counters') ) traffictop_sync_shortlink_counters();
    if ( function_exists('traffictop_sync_campaign_counters') ) traffictop_sync_campaign_counters();
    update_option( "traffictop_{$ver}", 1 );
}, 99 );

// One-time fix: update unlock info text in DB (runs on ANY page load)
add_action( 'wp_loaded', function() {
    if ( get_option( 'traffictop_fix_unlock_info_v2' ) ) return;
    $content = get_option( 'traffictop_unlock_info_content', '' );
    if ( $content ) {
        $new = str_replace(
            array( '500đ-550đ', '100.000đ' ),
            array( '500đ-1.000đ', '50.000đ' ),
            $content
        );
        if ( $new !== $content ) update_option( 'traffictop_unlock_info_content', $new );
    }
    update_option( 'traffictop_fix_unlock_info_v2', 1 );
});


// Floating contact button (tách ra includes/floating-contact.php)


/* ONE-TIME FIX: Đã xóa — script bù thưởng from_google đã chạy xong hoặc gây DB overload.
   Nếu cần chạy lại, dùng AJAX diagnostic endpoint thay vì admin_init. */

