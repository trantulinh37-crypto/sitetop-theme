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

// Menu separator labels
add_action( 'admin_head', function() { ?>
<style>
.linkngon-menu-label{display:block;padding:10px 12px 4px!important;font-size:10px!important;font-weight:700!important;letter-spacing:.12em;color:#9ca3af!important;text-transform:uppercase;line-height:1.4!important}
#collapse-menu,#wp-admin-bar-comments,#wp-admin-bar-new-content,#wp-admin-bar-wp-logo,#wp-admin-bar-updates{display:none!important}
.wp-toggle-label{cursor:pointer;user-select:none}
.wp-toggle-label:after{content:' ▸';font-size:9px}
.wp-toggle-label.wp-open:after{content:' ▾'}
.wp-menu-hidden{display:none!important}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
    var labels = {'linkngon-users':'NHÀ XUẤT BẢN','linkngon-customers':'KHÁCH HÀNG','linkngon-visits':'HỆ THỐNG'};
    Object.keys(labels).forEach(function(slug){
        var li = document.querySelector('#adminmenu a[href*="page='+slug+'"]');
        if(li){
            var menuLi = li.closest('li');
            if(menuLi){
                var lbl = document.createElement('li');
                lbl.className = 'linkngon-menu-label';
                lbl.textContent = labels[slug];
                menuLi.parentNode.insertBefore(lbl, menuLi);
            }
        }
    });
    // Label cho nhóm WordPress mặc định (collapsible)
    var wpFirst = document.querySelector('#adminmenu a[href="upload.php"]');
    if(wpFirst){
        var wpLi = wpFirst.closest('li');
        if(wpLi){
            var wpLbl = document.createElement('li');
            wpLbl.className = 'linkngon-menu-label wp-toggle-label';
            wpLbl.textContent = 'WORDPRESS';
            wpLi.parentNode.insertBefore(wpLbl, wpLi);
            // Collect all WP menu items after the label
            var wpItems = [];
            var next = wpLbl.nextElementSibling;
            while(next){ wpItems.push(next); next = next.nextElementSibling; }
            // Start collapsed
            wpItems.forEach(function(el){ el.classList.add('wp-menu-hidden'); });
            // Check if current page is a WP menu item
            var isWpPage = wpItems.some(function(el){ return el.classList.contains('current'); });
            if(isWpPage){
                wpItems.forEach(function(el){ el.classList.remove('wp-menu-hidden'); });
                wpLbl.classList.add('wp-open');
            }
            wpLbl.addEventListener('click', function(){
                var hidden = wpItems[0] && wpItems[0].classList.contains('wp-menu-hidden');
                wpItems.forEach(function(el){ el.classList.toggle('wp-menu-hidden', !hidden); });
                wpLbl.classList.toggle('wp-open', hidden);
            });
        }
    }
});
</script>
<?php });

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

/* ============================================================
   BLOCK WP-LOGIN & WP-ADMIN
   Redirect to custom login page, keep AJAX + admin API working
   ============================================================ */
// Redirect wp-login.php to /dang-nhap
add_action( 'login_init', function() {
    // Allow logout action
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'logout' ) return;
    // Redirect password reset link to custom page
    if ( isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'rp', 'resetpass' ), true ) ) {
        $key   = sanitize_text_field( $_GET['key'] ?? '' );
        $login = sanitize_text_field( $_GET['login'] ?? '' );
        if ( $key && $login ) {
            wp_safe_redirect( home_url( '/quen-mat-khau/?key=' . urlencode( $key ) . '&login=' . urlencode( $login ) ) );
            exit;
        }
        return; // fallback to WP if params missing
    }
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

// Redirect /wp-admin/ to LinkNgon dashboard
add_action( 'admin_init', function() {
    if ( wp_doing_ajax() ) return;
    if ( strpos( $_SERVER['SCRIPT_FILENAME'] ?? '', 'admin-post.php' ) !== false ) return;
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_linkngon' ) ) return;

    global $pagenow;
    if ( $pagenow === 'index.php' && ! isset( $_GET['page'] ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=linkngon-campaigns' ) );
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
   AJAX: Admin Get Deposits
   ============================================================ */
add_action( 'wp_ajax_linkngon_admin_get_deposits', function() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Không có quyền' );

    global $wpdb;
    $prefix = $wpdb->prefix . 'linkngon_';
    $status = sanitize_text_field( $_POST['status'] ?? '' );

    $where = '';
    if ( $status ) $where = $wpdb->prepare( ' WHERE status = %s', $status );

    $deposits = $wpdb->get_results( "SELECT * FROM {$prefix}customer_deposits{$where} ORDER BY created_at DESC LIMIT 50" );
    wp_send_json_success( array( 'deposits' => $deposits ) );
});

/* ============================================================
   AJAX: Admin Process Deposit
   ============================================================ */
add_action( 'wp_ajax_linkngon_admin_process_deposit', function() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Không có quyền' );

    global $wpdb;
    $prefix     = $wpdb->prefix . 'linkngon_';
    $deposit_id = intval( $_POST['deposit_id'] ?? 0 );
    $new_status = sanitize_text_field( $_POST['new_status'] ?? '' );

    if ( ! $deposit_id || ! in_array( $new_status, array( 'approved', 'rejected' ), true ) ) {
        wp_send_json_error( 'Tham số không hợp lệ' );
    }

    $wpdb->query( 'START TRANSACTION' );

    $deposit = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$prefix}customer_deposits WHERE id = %d FOR UPDATE", $deposit_id
    ));

    if ( ! $deposit || $deposit->status !== 'pending' ) {
        $wpdb->query( 'ROLLBACK' );
        wp_send_json_error( 'Đơn không hợp lệ hoặc đã xử lý' );
    }

    if ( $new_status === 'approved' ) {
        $total = (float) $deposit->amount + (float) ( $deposit->bonus_amount ?? 0 );

        // Update customer balance
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$prefix}customer_balance WHERE user_id = %d FOR UPDATE", $deposit->customer_id
        ));
        if ( $exists ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$prefix}customer_balance SET balance = balance + %f, total_deposited = total_deposited + %f WHERE user_id = %d",
                $total, $total, $deposit->customer_id
            ));
        } else {
            $wpdb->insert( $prefix . 'customer_balance', array(
                'user_id' => $deposit->customer_id, 'balance' => $total, 'total_deposited' => $total, 'total_spent' => 0,
            ));
        }

        // Log transaction
        $wpdb->insert( $prefix . 'customer_transactions', array(
            'customer_id'  => $deposit->customer_id,
            'type'         => 'deposit',
            'amount'       => $total,
            'description'  => 'Nạp tiền #' . $deposit_id,
            'reference_id' => $deposit_id,
            'reference_type' => 'deposit',
            'status'       => 'completed',
            'created_at'   => linkngon_current_time(),
        ));
    }

    $wpdb->update( $prefix . 'customer_deposits', array(
        'status'      => $new_status,
        'approved_by' => get_current_user_id(),
        'approved_at' => linkngon_current_time(),
    ), array( 'id' => $deposit_id ) );

    $wpdb->query( 'COMMIT' );

    // Email notifications
    if ( $new_status === 'approved' ) {
        linkngon_send_deposit_approved_email( $deposit_id );
    } elseif ( $new_status === 'rejected' ) {
        linkngon_send_deposit_rejected_email( $deposit_id );
    }

    wp_send_json_success( 'Đã xử lý đơn nạp #' . $deposit_id );
});

/* ============================================================
   AJAX: Customer Create Deposit
   ============================================================ */
add_action( 'wp_ajax_linkngon_customer_deposit', function() {
    check_ajax_referer( 'linkngon_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    $user_id = get_current_user_id();
    $user    = wp_get_current_user();
    $amount  = floatval( $_POST['amount'] ?? 0 );

    $min = floatval( linkngon_get_option( 'min_deposit_amount', 50000 ) );
    $max = 100000000;

    if ( $amount < $min ) wp_send_json_error( 'Số tiền tối thiểu ' . linkngon_format_money( $min ) );
    if ( $amount > $max ) wp_send_json_error( 'Số tiền tối đa ' . linkngon_format_money( $max ) );

    // Calculate bonus
    $bonus_percent = 0;
    $tiers = json_decode( linkngon_get_option( 'deposit_presets', '[]' ), true );
    if ( is_array( $tiers ) ) {
        usort( $tiers, function( $a, $b ) { return $a['amount'] - $b['amount']; } );
        foreach ( $tiers as $tier ) {
            if ( $amount >= $tier['amount'] ) $bonus_percent = $tier['bonus'];
        }
    }
    $bonus_amount = floor( $amount * $bonus_percent / 100 );

    global $wpdb;
    $prefix = $wpdb->prefix . 'linkngon_';

    $wpdb->insert( $prefix . 'customer_deposits', array(
        'customer_id'       => $user_id,
        'customer_username' => $user->user_login,
        'amount'            => $amount,
        'bonus_percent'     => $bonus_percent,
        'bonus_amount'      => $bonus_amount,
        'payment_method'    => in_array($_POST['payment_method'] ?? 'bank', array('bank','usdt')) ? $_POST['payment_method'] : 'bank',
        'status'            => 'pending',
        'created_at'        => linkngon_current_time(),
    ));

    if ( ! $wpdb->insert_id ) wp_send_json_error( 'Lỗi tạo đơn nạp tiền' );

    wp_send_json_success( 'Đơn nạp tiền #' . $wpdb->insert_id . ' đã tạo thành công' );
});

/* ============================================================
   AJAX: Customer Create Campaign
   ============================================================ */
add_action( 'wp_ajax_linkngon_customer_create_campaign', function() {
    check_ajax_referer( 'linkngon_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    $user_id = get_current_user_id();
    // Admin can create for another customer
    $admin_cust_id = intval( $_POST['admin_customer_id'] ?? 0 );
    if ( $admin_cust_id && current_user_can( 'manage_options' ) ) {
        $user_id = $admin_cust_id;
    }
    global $wpdb;
    $prefix = $wpdb->prefix . 'linkngon_';

    $task_type    = sanitize_text_field( $_POST['task_type'] ?? 'keyword_search' );
    $keyword      = sanitize_text_field( $_POST['keyword'] ?? '' );
    $target_url   = esc_url_raw( $_POST['target_url'] ?? '' );
    $title        = sanitize_text_field( $_POST['title'] ?? '' );
    $traffic_type = sanitize_text_field( $_POST['traffic_type'] ?? '1step' );
    $onsite_time  = intval( $_POST['onsite_time'] ?? 70 );
    $daily_traffic = max( 10, intval( $_POST['daily_traffic'] ?? 100 ) );
    $days         = max( 1, intval( $_POST['days'] ?? 15 ) );
    $quantity     = $daily_traffic * $days;

    if ( empty( $target_url ) ) wp_send_json_error( 'Vui lòng nhập URL' );
    if ( $task_type === 'keyword_search' && empty( $keyword ) ) wp_send_json_error( 'Vui lòng nhập từ khóa' );
    if ( $traffic_type === 'nocode' && empty( $_POST['fixed_code'] ) ) wp_send_json_error( 'Vui lòng nhập mã xác nhận cố định' );
    if ( $traffic_type === 'nocode' && empty( $_FILES['screenshot_nocode']['name'] ) ) wp_send_json_error( 'Vui lòng tải ảnh mô tả vị trí mã cố định' );
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
    $onsite_extra = array(70=>(int)linkngon_get_option('onsite_extra_70',0),80=>(int)linkngon_get_option('onsite_extra_80',0),90=>(int)linkngon_get_option('onsite_extra_90',100),100=>(int)linkngon_get_option('onsite_extra_100',200),120=>(int)linkngon_get_option('onsite_extra_120',250),150=>(int)linkngon_get_option('onsite_extra_150',300));
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

    // Handle screenshot uploads (ImgBB first, fallback WordPress)
    $screenshot_desktop_url = '';
    $screenshot_mobile_url  = '';
    $nocode_screenshot_url  = '';
    foreach ( array( 'screenshot_desktop' => 'screenshot_desktop_url', 'screenshot_mobile' => 'screenshot_mobile_url', 'screenshot_nocode' => 'nocode_screenshot_url' ) as $field => $var ) {
        if ( ! empty( $_FILES[ $field ]['name'] ) ) {
            $url = linkngon_upload_file( $_FILES[ $field ] );
            if ( $url ) $$var = $url;
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
        'campaign_type'          => $task_type,
        'onsite_time'            => $onsite_time,
        'quantity'               => $quantity,
        'completed'              => 0,
        'price_per_view'         => $price_per_view,
        'user_reward'            => $user_reward,
        'daily_traffic'          => $daily_traffic,
        'fixed_code'             => ( $traffic_type === 'nocode' ) ? sanitize_text_field( $_POST['fixed_code'] ?? '' ) : null,
        'screenshot_desktop_url' => $screenshot_desktop_url,
        'screenshot_mobile_url'  => $screenshot_mobile_url,
        'nocode_screenshot_url'  => $nocode_screenshot_url,
        'status'                 => 'pending',
        'created_at'             => linkngon_current_time(),
        'updated_at'             => linkngon_current_time(),
    ));

    if ( ! $wpdb->insert_id ) wp_send_json_error( 'Lỗi tạo chiến dịch' );

    delete_transient( 'linkngon_eligible_campaigns' );
    wp_send_json_success( 'Chiến dịch đã được tạo thành công' );
});

/* ============================================================
   AJAX: Customer Toggle Campaign (pause/resume)
   ============================================================ */
add_action( 'wp_ajax_linkngon_customer_toggle_campaign', function() {
    check_ajax_referer( 'linkngon_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    global $wpdb;
    $prefix      = $wpdb->prefix . 'linkngon_';
    $user_id     = get_current_user_id();
    $campaign_id = absint( $_POST['campaign_id'] ?? 0 );
    $new_status  = sanitize_text_field( $_POST['status'] ?? '' );

    if ( ! $campaign_id ) wp_send_json_error( 'Thiếu campaign ID' );
    if ( ! in_array( $new_status, array( 'active', 'paused' ) ) ) wp_send_json_error( 'Trạng thái không hợp lệ' );

    $campaign = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$prefix}keyword_campaigns WHERE id=%d AND customer_id=%d", $campaign_id, $user_id
    ) );
    if ( ! $campaign ) wp_send_json_error( 'Chiến dịch không tồn tại' );

    // Only allow toggle between active <-> paused
    if ( $new_status === 'active' && $campaign->status !== 'paused' ) wp_send_json_error( 'Chỉ có thể tiếp tục chiến dịch đang tạm dừng' );
    if ( $new_status === 'paused' && $campaign->status !== 'active' ) wp_send_json_error( 'Chỉ có thể tạm dừng chiến dịch đang chạy' );

    // If resuming, check customer balance
    if ( $new_status === 'active' && function_exists( 'linkngon_get_customer_balance_amount' ) ) {
        $balance = linkngon_get_customer_balance_amount( $user_id );
        $min_balance = floatval( linkngon_get_option( 'customer_min_balance', 20000 ) );
        $required = $min_balance + max( floatval( $campaign->price_per_view ), 1000 );
        if ( $balance !== false && $balance <= $required ) {
            wp_send_json_error( 'Số dư không đủ để tiếp tục chiến dịch. Cần tối thiểu ' . linkngon_format_money( $required ) );
        }
    }

    $wpdb->update( $prefix . 'keyword_campaigns', array( 'status' => $new_status ), array( 'id' => $campaign_id ) );
    // Sync order status
    if ( $campaign->order_id ) {
        $wpdb->update( $prefix . 'customer_orders', array( 'status' => $new_status ), array( 'id' => $campaign->order_id ) );
    }

    $label = $new_status === 'paused' ? 'Đã tạm dừng chiến dịch' : 'Đã tiếp tục chiến dịch';
    wp_send_json_success( $label );
});

/* ============================================================
   AJAX: Customer Get Campaign Detail
   ============================================================ */
add_action( 'wp_ajax_linkngon_customer_get_campaign', function() {
    check_ajax_referer( 'linkngon_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    global $wpdb;
    $prefix      = $wpdb->prefix . 'linkngon_';
    $user_id     = get_current_user_id();
    $campaign_id = absint( $_POST['campaign_id'] ?? 0 );

    $c = $wpdb->get_row( $wpdb->prepare(
        "SELECT kc.*, co.task_type FROM {$prefix}keyword_campaigns kc
         LEFT JOIN {$prefix}customer_orders co ON kc.order_id = co.id
         WHERE kc.id=%d AND kc.customer_id=%d", $campaign_id, $user_id
    ) );
    if ( ! $c ) wp_send_json_error( 'Không tìm thấy chiến dịch' );

    $today = date( 'Y-m-d', strtotime( linkngon_current_time() ) );
    $today_views = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=%d AND step='verified' AND DATE(created_at)=%s", $campaign_id, $today
    ) );
    $total_completed = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=%d AND step='verified'", $campaign_id
    ) );

    wp_send_json_success( array(
        'id'              => $c->id,
        'title'           => $c->title,
        'keyword'         => $c->keyword,
        'target_url'      => $c->target_url,
        'task_type'       => $c->task_type ?? 'keyword_search',
        'traffic_type'    => $c->traffic_type,
        'onsite_time'     => $c->onsite_time,
        'price_per_view'  => $c->price_per_view,
        'daily_traffic'   => $c->daily_traffic,
        'quantity'        => $c->quantity,
        'completed'       => $total_completed,
        'today_views'     => $today_views,
        'status'          => $c->status,
        'screenshot_desktop_url' => $c->screenshot_desktop_url,
        'screenshot_mobile_url'  => $c->screenshot_mobile_url,
        'created_at'      => $c->created_at,
        'reject_reason'   => $c->reject_reason,
        'fixed_code'      => $c->fixed_code,
    ) );
});

/* ============================================================
   AJAX: Customer Edit Campaign
   ============================================================ */
add_action( 'wp_ajax_linkngon_customer_edit_campaign', function() {
    check_ajax_referer( 'linkngon_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    global $wpdb;
    $prefix      = $wpdb->prefix . 'linkngon_';
    $user_id     = get_current_user_id();
    $campaign_id = absint( $_POST['campaign_id'] ?? 0 );

    if ( ! $campaign_id ) wp_send_json_error( 'Thiếu campaign ID' );

    $campaign = $wpdb->get_row( $wpdb->prepare(
        "SELECT kc.*, co.task_type FROM {$prefix}keyword_campaigns kc
         LEFT JOIN {$prefix}customer_orders co ON co.id = kc.order_id
         WHERE kc.id=%d AND kc.customer_id=%d", $campaign_id, $user_id
    ) );
    if ( ! $campaign ) wp_send_json_error( 'Chiến dịch không tồn tại' );
    if ( ! in_array( $campaign->status, array( 'pending', 'active', 'paused' ) ) ) {
        wp_send_json_error( 'Không thể chỉnh sửa chiến dịch ở trạng thái này' );
    }

    $data = array( 'updated_at' => linkngon_current_time() );
    $needs_reapproval = false;

    // Fields that require re-approval
    if ( isset( $_POST['keyword'] ) ) {
        $new_keyword = sanitize_text_field( $_POST['keyword'] );
        if ( $new_keyword !== ( $campaign->keyword ?? '' ) ) { $needs_reapproval = true; }
        $data['keyword'] = $new_keyword;
    }
    if ( isset( $_POST['target_url'] ) ) {
        $url = esc_url_raw( $_POST['target_url'] );
        if ( empty( $url ) ) wp_send_json_error( 'URL không hợp lệ' );
        if ( $url !== ( $campaign->target_url ?? '' ) ) { $needs_reapproval = true; }
        $data['target_url'] = $url;
    }
    if ( isset( $_POST['title'] ) ) {
        $new_title = sanitize_text_field( $_POST['title'] );
        if ( $new_title !== ( $campaign->title ?? '' ) ) { $needs_reapproval = true; }
        $data['title'] = $new_title;
    }
    if ( isset( $_POST['traffic_type'] ) ) {
        $new_tt = sanitize_text_field( $_POST['traffic_type'] );
        if ( in_array( $new_tt, array( '1step', '2step', 'nocode' ) ) ) {
            if ( $new_tt !== ( $campaign->traffic_type ?? '1step' ) ) { $needs_reapproval = true; }
            $data['traffic_type'] = $new_tt;
        }
    }
    if ( isset( $_POST['onsite_time'] ) ) {
        $new_os = intval( $_POST['onsite_time'] );
        $allowed_os = array( 70, 80, 90, 100, 120, 150 );
        if ( in_array( $new_os, $allowed_os ) ) {
            if ( $new_os !== intval( $campaign->onsite_time ?? 70 ) ) { $needs_reapproval = true; }
            $data['onsite_time'] = $new_os;
        }
    }

    // Daily traffic does NOT require re-approval
    if ( isset( $_POST['daily_traffic'] ) ) {
        $data['daily_traffic'] = max( 1, min( 100, intval( $_POST['daily_traffic'] ) ) );
    }

    // Screenshot uploads require re-approval (ImgBB first, fallback WordPress)
    foreach ( array( 'screenshot_desktop' => 'screenshot_desktop_url', 'screenshot_mobile' => 'screenshot_mobile_url' ) as $field => $col ) {
        if ( ! empty( $_FILES[ $field ]['name'] ) ) {
            $url = linkngon_upload_file( $_FILES[ $field ] );
            if ( $url ) {
                $data[ $col ] = $url;
                $needs_reapproval = true;
            }
        }
    }

    // Recalculate price if traffic_type or onsite_time changed
    $task_type    = $campaign->task_type ?? 'keyword_search';
    $traffic_type = $data['traffic_type'] ?? $campaign->traffic_type ?? '1step';
    $onsite_time  = $data['onsite_time'] ?? intval( $campaign->onsite_time ?? 70 );

    $price_key = ( $task_type === 'keyword_search' ) ? 'keyword_price_' : 'direct_price_';
    $price_per_view = floatval( linkngon_get_option( $price_key . $traffic_type, 1200 ) );
    $onsite_extra = array(70=>(int)linkngon_get_option('onsite_extra_70',0),80=>(int)linkngon_get_option('onsite_extra_80',0),90=>(int)linkngon_get_option('onsite_extra_90',100),100=>(int)linkngon_get_option('onsite_extra_100',200),120=>(int)linkngon_get_option('onsite_extra_120',250),150=>(int)linkngon_get_option('onsite_extra_150',300));
    $price_per_view += $onsite_extra[ $onsite_time ] ?? 0;

    $reward_pct  = floatval( linkngon_get_option( 'keyword_user_reward_percent', 80 ) );
    $user_reward = floor( $price_per_view * $reward_pct / 100 );

    $data['price_per_view'] = $price_per_view;
    $data['user_reward']    = $user_reward;

    // Set to pending if significant changes
    if ( $needs_reapproval && $campaign->status !== 'pending' ) {
        $data['status'] = 'pending';
    }

    $wpdb->update( $prefix . 'keyword_campaigns', $data, array( 'id' => $campaign_id ) );

    // Sync order
    if ( $campaign->order_id ) {
        $order_data = array( 'updated_at' => linkngon_current_time(), 'price_per_task' => $price_per_view );
        if ( isset( $data['title'] ) )      $order_data['title']    = $data['title'];
        if ( isset( $data['target_url'] ) )  $order_data['task_url'] = $data['target_url'];
        if ( isset( $data['status'] ) )      $order_data['status']   = $data['status'];
        $wpdb->update( $prefix . 'customer_orders', $order_data, array( 'id' => $campaign->order_id ) );
    }

    delete_transient( 'linkngon_eligible_campaigns' );
    $msg = $needs_reapproval && $campaign->status !== 'pending'
        ? 'Đã cập nhật. Chiến dịch chuyển về Chờ duyệt.'
        : 'Đã cập nhật chiến dịch';
    wp_send_json_success( $msg );
});

/* ============================================================
   AJAX: Customer Delete Campaign (only paused)
   ============================================================ */
add_action( 'wp_ajax_linkngon_customer_delete_campaign', function() {
    check_ajax_referer( 'linkngon_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    global $wpdb;
    $prefix      = $wpdb->prefix . 'linkngon_';
    $user_id     = get_current_user_id();
    $campaign_id = absint( $_POST['campaign_id'] ?? 0 );

    if ( ! $campaign_id ) wp_send_json_error( 'Thiếu campaign ID' );

    $campaign = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$prefix}keyword_campaigns WHERE id=%d AND customer_id=%d", $campaign_id, $user_id
    ) );
    if ( ! $campaign ) wp_send_json_error( 'Chiến dịch không tồn tại' );
    if ( $campaign->status !== 'paused' ) wp_send_json_error( 'Chỉ có thể xóa chiến dịch đang tạm dừng' );

    // Soft delete - preserve for financial audit trail
    $now = linkngon_current_time();
    $wpdb->update( $prefix . 'keyword_campaigns', array( 'status' => 'deleted', 'updated_at' => $now ), array( 'id' => $campaign_id ) );
    if ( $campaign->order_id ) {
        $wpdb->update( $prefix . 'customer_orders', array( 'status' => 'deleted', 'updated_at' => $now ), array( 'id' => $campaign->order_id ) );
    }
    delete_transient( 'linkngon_eligible_campaigns' );
    wp_send_json_success( 'Đã xóa chiến dịch' );
});

/* ============================================================
   AJAX: Edit Shortlink
   ============================================================ */
add_action( 'wp_ajax_linkngon_edit_shortlink', function() {
    check_ajax_referer( 'linkngon_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    global $wpdb;
    $prefix  = $wpdb->prefix . 'linkngon_';
    $link_id = intval( $_POST['link_id'] ?? 0 );
    $user_id = get_current_user_id();

    $link = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prefix}user_shortlinks WHERE id=%d AND user_id=%d", $link_id, $user_id ) );
    if ( ! $link ) wp_send_json_error( 'Link không tồn tại' );

    $data = array();
    if ( isset( $_POST['url'] ) ) $data['original_url'] = esc_url_raw( $_POST['url'] );
    if ( isset( $_POST['fallback_url'] ) ) $data['fallback_url'] = esc_url_raw( $_POST['fallback_url'] );
    if ( isset( $_POST['alias'] ) ) {
        $alias = sanitize_title( $_POST['alias'] );
        if ( $alias && $alias !== $link->alias ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$prefix}user_shortlinks WHERE alias=%s AND id!=%d", $alias, $link_id ) );
            if ( $exists ) wp_send_json_error( 'Bí danh đã tồn tại' );
            $data['alias'] = $alias;
        } elseif ( empty( $_POST['alias'] ) ) {
            $data['alias'] = null;
        }
    }

    if ( ! empty( $data ) ) {
        $wpdb->update( $prefix . 'user_shortlinks', $data, array( 'id' => $link_id ) );
    }
    wp_send_json_success( 'Đã cập nhật' );
});

/* ============================================================
   AJAX: Get Link Visits
   ============================================================ */
add_action( 'wp_ajax_linkngon_get_link_visits', function() {
    check_ajax_referer( 'linkngon_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    global $wpdb;
    $prefix  = $wpdb->prefix . 'linkngon_';
    $link_id = intval( $_POST['link_id'] ?? 0 );
    $user_id = get_current_user_id();

    $link = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prefix}user_shortlinks WHERE id=%d AND user_id=%d", $link_id, $user_id ) );
    if ( ! $link ) wp_send_json_error( 'Link không tồn tại' );

    $visits = $wpdb->get_results( $wpdb->prepare(
        "SELECT v.created_at, v.ip_address, v.user_agent, v.step, v.reward_paid, v.reward_amount
         FROM {$prefix}shortlink_visits v WHERE v.shortlink_id=%d AND v.reward_paid=1 ORDER BY v.created_at DESC LIMIT 20", $link_id
    ) );

    if ( empty( $visits ) ) {
        wp_send_json_success( array( 'html' => '' ) );
    }

    $html = '<div style="font-size:12px;color:#6B7280;margin-bottom:8px"><strong>' . count( $visits ) . '</strong> lượt gần nhất</div>';
    $html .= '<table style="width:100%;border-collapse:collapse;font-size:12px"><thead><tr style="background:#F7F5F0"><th style="padding:8px;text-align:left">Thời gian</th><th>IP</th><th>Thiết bị</th><th>Bước</th><th>Tiền</th></tr></thead><tbody>';
    foreach ( $visits as $v ) {
        $ua = $v->user_agent ?? '';
        $device = 'Unknown';
        if ( stripos($ua,'Android') !== false ) $device = 'Android';
        elseif ( stripos($ua,'iPhone') !== false ) $device = 'iPhone';
        elseif ( stripos($ua,'Windows') !== false ) $device = 'Windows';
        elseif ( stripos($ua,'Mac') !== false ) $device = 'macOS';

        $step_color = array('verified'=>'#059669','started'=>'#6B7280','code_shown'=>'#D97706');
        $color = $step_color[$v->step] ?? '#6B7280';
        $reward = $v->reward_paid ? '<span style="color:#059669">+' . linkngon_format_money($v->reward_amount) . '</span>' : '<span style="color:#9CA3AF">—</span>';

        $html .= '<tr style="border-bottom:1px solid #F0EDE6">';
        $html .= '<td style="padding:8px">' . date('d/m H:i', strtotime($v->created_at)) . '</td>';
        $html .= '<td style="padding:8px"><code style="font-size:10px">' . esc_html( substr($v->ip_address, 0, 20) ) . '</code></td>';
        $html .= '<td style="padding:8px">' . esc_html($device) . '</td>';
        $html .= '<td style="padding:8px;color:' . $color . ';font-weight:600">' . esc_html($v->step) . '</td>';
        $html .= '<td style="padding:8px">' . $reward . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    wp_send_json_success( array( 'html' => $html ) );
});

/* ============================================================
   AJAX: Reset API Token
   ============================================================ */
add_action( 'wp_ajax_linkngon_reset_api_token', function() {
    check_ajax_referer( 'linkngon_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );

    $token = wp_generate_password( 24, false );
    update_user_meta( get_current_user_id(), 'linkngon_api_token', $token );
    wp_send_json_success( array( 'token' => $token ) );
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

/* ============================================================
   AJAX: User Dashboard Load More
   ============================================================ */
add_action( 'wp_ajax_linkngon_load_more', 'linkngon_ajax_load_more' );
function linkngon_ajax_load_more() {
    check_ajax_referer( 'linkngon_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Unauthorized' );

    $user_id = get_current_user_id();
    $type    = sanitize_text_field( $_POST['type'] ?? '' );
    $offset  = absint( $_POST['offset'] ?? 0 );
    $limit   = 10;
    $today   = date( 'Y-m-d', strtotime( linkngon_current_time() ) );
    $home    = home_url();

    global $wpdb;
    $prefix = $wpdb->prefix . 'linkngon_';

    $html = '';
    $has_more = false;

    if ( $type === 'links' ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT us.*,
                    us.code as shortcode,
                    us.original_url as target_url,
                    us.total_clicks as click_count,
                    (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE shortlink_id=us.id AND step='verified' AND DATE(created_at)=%s) as today_clicks
             FROM {$prefix}user_shortlinks us
             WHERE us.user_id = %d
             ORDER BY us.created_at DESC
             LIMIT %d OFFSET %d",
            $today, $user_id, $limit, $offset
        ) );
        foreach ( $rows as $lk ) {
            $short = $home . '/' . ( ! empty( $lk->alias ) ? $lk->alias : $lk->shortcode );
            $bcls = $lk->status === 'active' ? 'b-ok' : ( $lk->status === 'paused' ? 'b-warn' : 'b-mute' );
            $completed = isset( $lk->total_completed ) ? (int) $lk->total_completed : 0;
            $earnings = isset( $lk->total_earnings ) ? (float) $lk->total_earnings : 0;
            $html .= '<div class="link-card" onclick="copyText(\'' . esc_js( $short ) . '\',this)" style="background:var(--bg);border-radius:var(--rads);padding:14px;cursor:pointer;transition:all .15s;border:1.5px solid transparent" onmouseover="this.style.borderColor=\'var(--p)\'" onmouseout="this.style.borderColor=\'transparent\'">';
            $html .= '<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:6px">';
            $html .= '<div style="font-family:var(--mono);font-size:13px;color:var(--info);font-weight:600;white-space:nowrap">' . esc_html( $short ) . '</div>';
            $html .= '<span class="badge ' . $bcls . '" style="flex-shrink:0">' . esc_html( $lk->status ) . '</span></div>';
            $html .= '<div style="font-size:11px;color:var(--txtm);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:8px" title="' . esc_attr( $lk->target_url ) . '">' . esc_html( $lk->target_url ) . '</div>';
            $html .= '<div style="display:flex;gap:12px;font-size:11px;color:var(--txtl);flex-wrap:wrap">';
            $html .= '<span><strong style="color:var(--pd)">' . number_format( $lk->click_count ) . '</strong> clicks</span>';
            $html .= '<span><strong style="color:var(--ok)">' . $completed . '</strong> hoàn thành</span>';
            $html .= '<span><strong style="color:var(--a)">' . linkngon_format_money( $earnings ) . '</strong> kiếm được</span>';
            $html .= '<span>' . date( 'd/m/Y', strtotime( $lk->created_at ) ) . '</span></div>';
            $html .= '<div class="link-copied-msg" style="display:none;font-size:11px;color:var(--ok);margin-top:4px;font-weight:600">Đã copy!</div></div>';
        }
        $has_more = count( $rows ) >= $limit;

    } elseif ( $type === 'transactions' ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$prefix}transactions WHERE user_id=%d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ) );
        foreach ( $rows as $tx ) {
            $cls = $tx->amount >= 0 ? 'amt-plus' : 'amt-minus';
            $sign = $tx->amount >= 0 ? '+' : '';
            $html .= '<tr>';
            $html .= '<td><small>' . esc_html( $tx->created_at ) . '</small></td>';
            $html .= '<td>' . esc_html( $tx->description ) . '</td>';
            $html .= '<td class="' . $cls . '">' . $sign . linkngon_format_money( $tx->amount ) . '</td>';
            $html .= '<td>' . linkngon_format_money( $tx->balance_after ) . '</td>';
            $html .= '</tr>';
        }
        $has_more = count( $rows ) >= $limit;

    } elseif ( $type === 'withdrawals' ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$prefix}withdrawals WHERE user_id=%d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ) );
        $bc = array( 'pending' => 'b-warn', 'approved' => 'b-info', 'completed' => 'b-ok', 'rejected' => 'b-err', 'refunded' => 'b-err', 'cancelled' => 'b-mute' );
        foreach ( $rows as $w ) {
            $html .= '<tr>';
            $html .= '<td><small>' . date( 'd/m/Y', strtotime( $w->created_at ) ) . '</small></td>';
            $html .= '<td style="font-weight:600">' . linkngon_format_money( $w->amount ) . '</td>';
            $html .= '<td><small>' . esc_html( $w->bank_name ) . '</small></td>';
            $html .= '<td><span class="badge ' . ( $bc[ $w->status ] ?? 'b-mute' ) . '">' . esc_html( $w->status ) . '</span></td>';
            $html .= '</tr>';
        }
        $has_more = count( $rows ) >= $limit;

    } else {
        wp_send_json_error( 'Invalid type' );
    }

    wp_send_json_success( array( 'html' => $html, 'has_more' => $has_more ) );
}

/* ============================================================
   AJAX: Customer Dashboard Load More
   ============================================================ */
add_action( 'wp_ajax_linkngon_customer_load_more', 'linkngon_ajax_customer_load_more' );
function linkngon_ajax_customer_load_more() {
    check_ajax_referer( 'linkngon_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Unauthorized' );

    $user_id = get_current_user_id();
    $type    = sanitize_text_field( $_POST['type'] ?? '' );
    $offset  = absint( $_POST['offset'] ?? 0 );
    $limit   = 10;
    $today   = date( 'Y-m-d', strtotime( linkngon_current_time() ) );

    global $wpdb;
    $prefix = $wpdb->prefix . 'linkngon_';

    $html = '';
    $has_more = false;

    if ( $type === 'campaigns' ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT kc.*, co.task_type,
                    (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND step='verified') as total_completed,
                    (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND step='verified' AND DATE(created_at)=%s) as today_views
             FROM {$prefix}keyword_campaigns kc
             LEFT JOIN {$prefix}customer_orders co ON kc.order_id = co.id
             WHERE kc.customer_id = %d
             ORDER BY kc.created_at DESC LIMIT %d OFFSET %d",
            $today, $user_id, $limit, $offset
        ) );
        $task_icons = array( 'keyword_search' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>', 'traffic_direct' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>', 'traffic_social' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>' );
        $task_labels = array( 'keyword_search' => 'Keyword', 'traffic_direct' => 'Direct', 'traffic_social' => 'Social' );
        $task_colors = array( 'keyword_search' => 'b-info', 'traffic_direct' => 'b-warn', 'traffic_social' => 'b-mute' );
        $step_labels = array( '1step' => '1 bước', '2step' => '2 bước', 'nocode' => 'Mã cố định' );
        $status_labels = array( 'active' => 'Đang chạy', 'paused' => 'Tạm dừng', 'pending' => 'Chờ duyệt', 'completed' => 'Hoàn thành', 'rejected' => 'Từ chối' );
        $status_colors = array( 'active' => 'b-ok', 'paused' => 'b-warn', 'pending' => 'b-info', 'completed' => 'b-mute', 'rejected' => 'b-err' );

        foreach ( $rows as $c ) {
            $domain = parse_url( $c->target_url ?? '', PHP_URL_HOST );
            $tt = $c->task_type ?? 'keyword_search';
            $pct = $c->quantity > 0 ? round( ( $c->total_completed / $c->quantity ) * 100 ) : 0;
            $spent = $c->total_completed * ( $c->price_per_view ?? 0 );
            $html .= '<tr>';
            $html .= '<td><div style="display:flex;align-items:flex-start;gap:8px"><span style="color:var(--info);margin-top:2px">' . ( $task_icons[ $tt ] ?? '' ) . '</span><div><div style="font-weight:600;font-size:13px">' . esc_html( $c->keyword ?: $c->title ) . '</div>';
            if ( $domain ) $html .= '<div style="font-size:11px;color:var(--txtm)">' . esc_html( $domain ) . '</div>';
            $html .= '</div></div></td>';
            $html .= '<td><span class="badge ' . ( $task_colors[ $tt ] ?? 'b-mute' ) . '">' . ( $task_labels[ $tt ] ?? $tt ) . '</span></td>';
            $html .= '<td><div style="font-weight:600;font-size:12px">' . ( $step_labels[ $c->traffic_type ] ?? $c->traffic_type ) . '</div><div style="font-size:10px;color:var(--txtm)">' . (int) $c->onsite_time . 's</div>';
            if ( $c->traffic_type === 'nocode' && ! empty( $c->fixed_code ) ) $html .= '<div style="font-size:10px;color:var(--a);font-weight:600;margin-top:2px">' . esc_html( $c->fixed_code ) . '</div>';
            $html .= '</td>';
            $html .= '<td style="font-weight:600;color:var(--a)">' . linkngon_format_money( $c->price_per_view ?? 0 ) . '</td>';
            $html .= '<td><div style="font-size:12px">' . (int) $c->daily_traffic . '/ngày</div></td>';
            $html .= '<td><div style="font-weight:600;font-size:12px">' . $c->total_completed . '/' . $c->quantity . '</div>';
            if ( $c->quantity > 0 ) {
                $html .= '<div style="height:4px;background:var(--brdl);border-radius:2px;margin-top:4px;width:60px"><div style="height:100%;border-radius:2px;background:var(--p);width:' . min( 100, $pct ) . '%"></div></div>';
            }
            $html .= '<div style="font-size:10px;color:var(--txtm);margin-top:2px">= ' . linkngon_format_money( $spent ) . '</div></td>';
            $html .= '<td><span class="badge ' . ( $status_colors[ $c->status ] ?? 'b-mute' ) . '">' . ( $status_labels[ $c->status ] ?? $c->status ) . '</span></td>';
            $html .= '<td><small>' . date( 'd/m/Y', strtotime( $c->created_at ) ) . '</small></td>';
            $html .= '</tr>';
        }
        $has_more = count( $rows ) >= $limit;

    } elseif ( $type === 'visits' ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT v.created_at, v.verified_at, v.step, v.ip_address, v.user_agent, v.reward_paid, v.customer_paid,
                    v.reward_amount, v.from_google, v.url_matched,
                    kc.title as campaign_title, kc.keyword, kc.target_url, kc.traffic_type, kc.onsite_time, kc.price_per_view,
                    co.task_type
             FROM {$prefix}shortlink_visits v
             INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id = kc.id
             LEFT JOIN {$prefix}customer_orders co ON kc.order_id = co.id
             WHERE kc.customer_id = %d AND v.step = 'verified'
             ORDER BY v.created_at DESC LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ) );
        $task_label = array( 'keyword_search' => 'Từ khóa', 'traffic_direct' => 'Direct', 'traffic_social' => 'Social' );
        $step_map = array( '1step' => '1 bước', '2step' => '2 bước', 'nocode' => 'Mã cố định' );

        foreach ( $rows as $vh ) {
            $domain = parse_url( $vh->target_url, PHP_URL_HOST );
            $ua = $vh->user_agent ?? '';
            $device = 'Unknown';
            if ( stripos( $ua, 'Android' ) !== false ) {
                preg_match( '/Android\s*([\d.]+)/', $ua, $am );
                $device = 'Android' . ( isset( $am[1] ) ? " ({$am[1]})" : '' );
            } elseif ( stripos( $ua, 'iPhone' ) !== false ) {
                $device = 'iPhone';
            } elseif ( stripos( $ua, 'Windows' ) !== false ) {
                $device = stripos( $ua, 'Windows NT 10' ) !== false ? 'Win10/11' : 'Windows';
                if ( stripos( $ua, 'Chrome' ) !== false ) $device .= ' Chrome';
                elseif ( stripos( $ua, 'Firefox' ) !== false ) $device .= ' Firefox';
            } elseif ( stripos( $ua, 'Mac' ) !== false ) {
                $device = 'macOS';
                if ( stripos( $ua, 'Chrome' ) !== false ) $device .= ' Chrome';
                elseif ( stripos( $ua, 'Safari' ) !== false ) $device .= ' Safari';
            }
            $cost = $vh->price_per_view ?? 0;

            $html .= '<tr>';
            $html .= '<td><small>' . date( 'd/m/Y', strtotime( $vh->created_at ) ) . '<br>' . date( 'H:i:s', strtotime( $vh->created_at ) ) . '</small></td>';
            $html .= '<td style="white-space:nowrap">';
            if ( $vh->keyword ) {
                $html .= '<div style="font-weight:600;font-size:12px">' . esc_html( $vh->keyword ) . '</div>';
            } else {
                $html .= '<div style="font-weight:600;font-size:12px">' . esc_html( $vh->campaign_title ) . '</div>';
            }
            if ( $domain ) $html .= '<div style="font-size:11px;color:var(--txtm)">' . esc_html( $domain ) . '</div>';
            $html .= '</td>';
            $html .= '<td style="white-space:nowrap"><span class="badge b-info">' . ( $task_label[ $vh->task_type ?? '' ] ?? 'Traffic' ) . '</span>';
            $html .= '<div style="font-size:10px;color:var(--txtm);margin-top:2px">' . ( $step_map[ $vh->traffic_type ] ?? $vh->traffic_type ) . ' / ' . (int) $vh->onsite_time . 's</div></td>';
            $html .= '<td style="white-space:nowrap;font-weight:600;color:var(--err)">-' . linkngon_format_money( $cost ) . '</td>';
            $html .= '<td style="white-space:nowrap">';
            if ( $vh->customer_paid ) {
                $html .= '<span class="badge b-ok">Hoàn thành</span>';
            } else {
                $html .= '<span class="badge b-warn">Không tính phí</span>';
            }
            $html .= '</td>';
            $html .= '<td><small style="font-family:var(--mono);font-size:10px">' . esc_html( $vh->ip_address ) . '</small></td>';
            $html .= '<td><small style="font-size:11px">' . esc_html( $device ) . '</small></td>';
            $html .= '</tr>';
        }
        $has_more = count( $rows ) >= $limit;

    } elseif ( $type === 'transactions' ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$prefix}customer_transactions WHERE customer_id=%d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ) );
        $tl = array( 'deposit' => 'Nạp tiền', 'campaign_view' => 'Chi phí view', 'refund' => 'Hoàn tiền', 'bonus' => 'Thưởng', 'deduction' => 'Trừ tiền' );
        $tb = array( 'deposit' => 'b-ok', 'campaign_view' => 'b-err', 'refund' => 'b-info', 'bonus' => 'b-ok', 'deduction' => 'b-warn' );

        foreach ( $rows as $tx ) {
            $color = $tx->amount >= 0 ? 'var(--ok)' : 'var(--err)';
            $sign = $tx->amount >= 0 ? '+' : '';
            $html .= '<tr>';
            $html .= '<td><small>' . date( 'd/m/Y H:i', strtotime( $tx->created_at ) ) . '</small></td>';
            $html .= '<td><span class="badge ' . ( $tb[ $tx->type ] ?? 'b-mute' ) . '">' . ( $tl[ $tx->type ] ?? $tx->type ) . '</span></td>';
            $html .= '<td style="font-size:12px">' . esc_html( $tx->description ) . '</td>';
            $html .= '<td style="font-weight:600;color:' . $color . '">' . $sign . linkngon_format_money( $tx->amount ) . '</td>';
            $html .= '<td style="font-size:12px">' . linkngon_format_money( $tx->balance_after ) . '</td>';
            $html .= '</tr>';
        }
        $has_more = count( $rows ) >= $limit;

    } elseif ( $type === 'deposits' ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$prefix}customer_deposits WHERE customer_id=%d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ) );
        $bc = array( 'pending' => 'b-warn', 'approved' => 'b-ok', 'rejected' => 'b-err' );
        $bl = array( 'pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối' );

        foreach ( $rows as $dep ) {
            $bonus = isset( $dep->bonus_amount ) ? (float) $dep->bonus_amount : 0;
            $total = (float) $dep->amount + $bonus;
            $html .= '<tr>';
            $html .= '<td style="font-size:12px;color:var(--txtm)">#' . $dep->id . '</td>';
            $html .= '<td style="font-weight:600;color:var(--ok)">+' . linkngon_format_money( $dep->amount ) . '</td>';
            $html .= '<td style="font-size:12px">' . ( $bonus > 0 ? '+' . linkngon_format_money( $bonus ) : '—' ) . '</td>';
            $html .= '<td style="font-weight:600">' . linkngon_format_money( $total ) . '</td>';
            $html .= '<td><span class="badge ' . ( $bc[ $dep->status ] ?? 'b-mute' ) . '">' . ( $bl[ $dep->status ] ?? $dep->status ) . '</span></td>';
            $html .= '<td><small>' . date( 'd/m/Y', strtotime( $dep->created_at ) ) . '</small></td>';
            $html .= '</tr>';
        }
        $has_more = count( $rows ) >= $limit;

    } else {
        wp_send_json_error( 'Invalid type' );
    }

    wp_send_json_success( array( 'html' => $html, 'has_more' => $has_more ) );
}

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

/* ============================================================
   FLOATING CONTACT BUTTON
   Hiển thị trên homepage, user dashboard, customer dashboard
   ============================================================ */
add_action( 'wp_footer', function() {
    // Chỉ hiện trên front-end (không admin)
    if ( is_admin() ) return;

    $telegram = linkngon_get_option( 'contact_telegram', '' );
    $signal   = linkngon_get_option( 'contact_signal', '' );
    $zalo     = linkngon_get_option( 'contact_zalo', '' );
    $email    = linkngon_get_option( 'contact_email', '' );

    // Cần ít nhất 1 kênh liên hệ
    if ( ! $telegram && ! $signal && ! $zalo && ! $email ) return;

    $items = [];
    if ( $telegram ) {
        $tg_user = ltrim( $telegram, '@' );
        $items[] = [
            'url'   => 'https://t.me/' . $tg_user,
            'label' => 'Telegram',
            'svg'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/></svg>',
            'color' => '#0088cc',
        ];
    }
    if ( $signal ) {
        $signal_url = ( strpos( $signal, 'http' ) === 0 ) ? $signal : 'https://' . $signal;
        $items[] = [
            'url'   => $signal_url,
            'label' => 'Signal',
            'svg'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12c0 1.82.53 3.53 1.45 4.97L2.05 22l5.03-1.4A9.95 9.95 0 0012 22c5.52 0 10-4.48 10-10S17.52 2 12 2z"/><circle cx="5.5" cy="4.5" r=".7"/><circle cx="3.2" cy="7" r=".55"/><circle cx="7.8" cy="2.5" r=".55"/><circle cx="19" cy="4" r=".55"/><circle cx="21" cy="6.5" r=".5"/></svg>',
            'color' => '#3a76f0',
        ];
    }
    if ( $zalo ) {
        $items[] = [
            'url'   => 'https://zalo.me/' . $zalo,
            'label' => 'Zalo',
            'svg'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.04 2 11c0 2.77 1.36 5.24 3.5 6.86V22l3.77-2.07c.88.24 1.8.37 2.73.37 5.52 0 10-4.04 10-9S17.52 2 12 2zm1.13 12.12H8.53l-.2-.6 3.27-4.42H9.27l.2-.6h4.4l.2.6-3.27 4.42h2.53l-.2.6zm3.73-2.93c0 1.6-1.1 2.93-2.53 2.93-.42 0-.8-.12-1.13-.33l.73-.53c.13.07.27.13.4.13.73 0 1.33-.87 1.33-2.07v-.13c0-1.2-.6-2.07-1.33-2.07-.13 0-.27.07-.4.13l-.73-.53c.33-.2.73-.33 1.13-.33 1.4 0 2.53 1.33 2.53 2.93v-.13z"/></svg>',
            'color' => '#0068ff',
        ];
    }
    if ( $email ) {
        $items[] = [
            'url'   => 'mailto:' . $email,
            'label' => 'Email',
            'svg'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
            'color' => '#ea4335',
        ];
    }
    ?>
    <style>
    .ln-contact-fab{position:fixed;bottom:24px;right:24px;z-index:9990}
    .ln-contact-toggle{width:56px;height:56px;border-radius:50%;background:#2563eb;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(37,99,235,.4);transition:transform .2s,box-shadow .2s;position:relative;z-index:2}
    .ln-contact-toggle::before{content:'';position:absolute;inset:-8px;border-radius:50%;background:rgba(37,99,235,.15);animation:ln-fab-pulse 2s ease-in-out infinite}
    .ln-contact-toggle:hover{transform:scale(1.08);box-shadow:0 6px 24px rgba(37,99,235,.5)}
    .ln-contact-toggle svg{transition:transform .3s}
    .ln-contact-fab.open .ln-contact-toggle svg{transform:rotate(90deg)}
    .ln-contact-items{position:absolute;bottom:68px;right:0;display:flex;flex-direction:column;gap:10px;align-items:flex-end;opacity:0;visibility:hidden;transform:translateY(10px);transition:all .25s ease}
    .ln-contact-fab.open .ln-contact-items{opacity:1;visibility:visible;transform:translateY(0)}
    .ln-contact-item{display:flex;align-items:center;gap:10px;text-decoration:none}
    .ln-contact-item-label{background:#fff;color:#333;font-size:13px;font-weight:600;padding:6px 14px;border-radius:20px;box-shadow:0 2px 8px rgba(0,0,0,.12);white-space:nowrap;opacity:0;transform:translateX(8px);transition:all .2s}
    .ln-contact-fab.open .ln-contact-item-label{opacity:1;transform:translateX(0)}
    .ln-contact-item-icon{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.15);transition:transform .2s;flex-shrink:0}
    .ln-contact-item-icon:hover{transform:scale(1.1)}
    @keyframes ln-fab-pulse{0%,100%{transform:scale(1);opacity:.6}50%{transform:scale(1.15);opacity:0}}
    @media(max-width:600px){.ln-contact-fab{bottom:16px;right:16px}.ln-contact-toggle{width:50px;height:50px}.ln-contact-item-icon{width:40px;height:40px}}
    </style>
    <div class="ln-contact-fab" id="lnContactFab">
        <div class="ln-contact-items">
            <?php foreach ( $items as $item ): ?>
            <a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener" class="ln-contact-item">
                <span class="ln-contact-item-label"><?php echo esc_html( $item['label'] ); ?></span>
                <span class="ln-contact-item-icon" style="background:<?php echo esc_attr( $item['color'] ); ?>"><?php echo $item['svg']; ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <button class="ln-contact-toggle" onclick="this.parentElement.classList.toggle('open')" aria-label="Liên hệ">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="white"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/><circle cx="12" cy="12" r="1"/><circle cx="8" cy="12" r="1"/><circle cx="16" cy="12" r="1"/></svg>
        </button>
    </div>
    <script>
    document.addEventListener('click',function(e){var f=document.getElementById('lnContactFab');if(f&&!f.contains(e.target))f.classList.remove('open')});
    </script>
    <?php
} );

