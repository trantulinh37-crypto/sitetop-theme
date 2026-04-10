<?php
/**
 * Admin Routing: block wp-login, wp-admin redirects, hide admin bar
 * Tách từ functions.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

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
    // Allow admins and Traffictop.net managers to access wp-admin
    if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_traffictop' ) ) return;

    if ( is_user_logged_in() ) {
        wp_safe_redirect( home_url( '/nguoi-dung' ) );
    } else {
        wp_safe_redirect( home_url( '/dang-nhap' ) );
    }
    exit;
});

// Hide admin bar for non-admins
add_action( 'after_setup_theme', function() {
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_traffictop' ) ) {
        show_admin_bar( false );
    }
}, 20 );

// Redirect /wp-admin/ to Traffictop.net dashboard
add_action( 'admin_init', function() {
    if ( wp_doing_ajax() ) return;
    if ( strpos( $_SERVER['SCRIPT_FILENAME'] ?? '', 'admin-post.php' ) !== false ) return;
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_traffictop' ) ) return;

    global $pagenow;
    if ( $pagenow === 'index.php' && ! isset( $_GET['page'] ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=traffictop-campaigns' ) );
        exit;
    }
}, 999 );
