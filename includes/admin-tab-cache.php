<?php
/**
 * Admin Tab Cache — server-side version tracking
 *
 * Track version timestamp per admin tab. Admin write actions auto-bump version
 * → frontend invalidates cache khi version mismatch.
 *
 * Flow:
 * 1. plugins_loaded: catch incoming AJAX action, register shutdown_function
 *    để bump version sau khi handler chạy xong (không cần sửa từng handler).
 * 2. AJAX endpoint `sitetop_admin_tab_versions`: trả về versions hiện tại.
 * 3. On theme version change (deploy): reset all versions.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Map AJAX write action → affected tabs.
 * Khi action trong list này fire, các tab tương ứng sẽ được bump version
 * → client cache invalidate, fetch lại HTML mới nhất.
 */
function sitetop_admin_action_tab_map() {
    return apply_filters( 'sitetop_admin_action_tabs', array(
        // Deposits flow → ảnh hưởng deposits + customers (balance) + overview (stats)
        'sitetop_admin_process_deposit'         => array( 'deposits', 'customers', 'overview' ),
        'sitetop_admin_approve_deposit'         => array( 'deposits', 'customers', 'overview' ),

        // Campaign CRUD → campaigns + customers (campaign count) + overview
        'sitetop_admin_create_campaign'         => array( 'campaigns', 'customers', 'overview' ),
        'sitetop_admin_update_campaign'         => array( 'campaigns', 'customers', 'overview' ),
        'sitetop_admin_update_widget_code_status' => array( 'campaigns' ),

        // Withdrawals → withdrawals + users (balance) + overview
        'sitetop_admin_process_withdrawal'      => array( 'withdrawals', 'users', 'overview' ),

        // User management → users + withdrawals (banned user's pending)
        'sitetop_admin_ban_user'                => array( 'users', 'withdrawals' ),
        'sitetop_admin_edit_user'               => array( 'users' ),
        'sitetop_admin_activate_user'           => array( 'users' ),
        'sitetop_admin_delete_user'             => array( 'users' ),
        'sitetop_admin_resend_verification'     => array( 'users' ),

        // Announcements (settings sub-section)
        'sitetop_admin_create_announcement'     => array( 'settings' ),
        'sitetop_admin_update_announcement'     => array( 'settings' ),
        'sitetop_admin_delete_announcement'     => array( 'settings' ),

        // API key management (users tab badge)
        'sitetop_admin_user_api_key'            => array( 'users' ),
    ) );
}

function sitetop_get_tab_versions() {
    $v = get_option( 'sitetop_tab_cache_versions', array() );
    return is_array( $v ) ? $v : array();
}

function sitetop_bump_tab_versions( $tabs ) {
    if ( empty( $tabs ) ) return;
    $versions = sitetop_get_tab_versions();
    $ts = time();
    foreach ( (array) $tabs as $tab ) $versions[ $tab ] = $ts;
    update_option( 'sitetop_tab_cache_versions', $versions, false );
}

// Auto-bump qua register_shutdown_function — không cần sửa từng handler
add_action( 'plugins_loaded', function() {
    if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) return;
    $action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';
    if ( ! $action ) return;
    $map = sitetop_admin_action_tab_map();
    if ( ! isset( $map[ $action ] ) ) return;
    $tabs = $map[ $action ];
    register_shutdown_function( function() use ( $tabs ) {
        if ( function_exists( 'sitetop_bump_tab_versions' ) ) {
            sitetop_bump_tab_versions( $tabs );
        }
    } );
}, 5 );

// AJAX endpoint — trả về versions hiện tại (lightweight ~100 bytes)
add_action( 'wp_ajax_sitetop_admin_tab_versions', function() {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    wp_send_json_success( sitetop_get_tab_versions() );
} );

// Reset cache khi theme version đổi (post-deploy → force invalidate stale cache)
add_action( 'init', function() {
    if ( ! defined( 'SITETOP_VERSION' ) ) return;
    $stored = get_option( 'sitetop_tab_cache_theme_version', '' );
    if ( $stored !== SITETOP_VERSION ) {
        update_option( 'sitetop_tab_cache_versions', array(), false );
        update_option( 'sitetop_tab_cache_theme_version', SITETOP_VERSION, false );
    }
}, 5 );
