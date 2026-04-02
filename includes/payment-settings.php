<?php
/**
 * LinkNgon V2 - Payment Settings (Bank QR, USDT)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Bank QR upload
add_action( 'wp_ajax_linkngon_upload_bank_qr', 'linkngon_upload_bank_qr' );
function linkngon_upload_bank_qr() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    if ( ! isset( $_FILES['qr_image'] ) ) wp_send_json_error( 'No file' );

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $id = media_handle_upload( 'qr_image', 0 );
    if ( is_wp_error( $id ) ) wp_send_json_error( $id->get_error_message() );

    $url = wp_get_attachment_url( $id );
    update_option( 'linkngon_bank_qr', $url );
    wp_send_json_success( array( 'url' => $url ) );
}

// Bank QR remove
add_action( 'wp_ajax_linkngon_remove_bank_qr', 'linkngon_remove_bank_qr' );
function linkngon_remove_bank_qr() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    delete_option( 'linkngon_bank_qr' );
    wp_send_json_success();
}

// USDT network toggle
add_action( 'wp_ajax_linkngon_toggle_usdt_network', 'linkngon_toggle_usdt_network' );
function linkngon_toggle_usdt_network() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    $network = sanitize_text_field( $_POST['network'] ?? '' );
    $enabled = sanitize_text_field( $_POST['enabled'] ?? '0' );
    if ( ! in_array( $network, array( 'trc20', 'bep20' ) ) ) wp_send_json_error( 'Invalid' );
    update_option( 'linkngon_usdt_' . $network . '_enabled', $enabled );
    wp_send_json_success();
}

// USDT QR upload
add_action( 'wp_ajax_linkngon_upload_usdt_network_qr', 'linkngon_upload_usdt_network_qr' );
function linkngon_upload_usdt_network_qr() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    $network = sanitize_text_field( $_POST['network'] ?? '' );
    if ( ! in_array( $network, array( 'trc20', 'bep20' ) ) ) wp_send_json_error( 'Invalid' );
    if ( ! isset( $_FILES['qr_image'] ) ) wp_send_json_error( 'No file' );

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $id = media_handle_upload( 'qr_image', 0 );
    if ( is_wp_error( $id ) ) wp_send_json_error( $id->get_error_message() );

    $url = wp_get_attachment_url( $id );
    update_option( 'linkngon_usdt_' . $network . '_qr', $url );
    wp_send_json_success( array( 'url' => $url ) );
}

// USDT address save
add_action( 'wp_ajax_linkngon_save_usdt_network', 'linkngon_save_usdt_network' );
function linkngon_save_usdt_network() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    $network = sanitize_text_field( $_POST['network'] ?? '' );
    $address = sanitize_text_field( $_POST['address'] ?? '' );
    if ( ! in_array( $network, array( 'trc20', 'bep20' ) ) ) wp_send_json_error( 'Invalid' );
    update_option( 'linkngon_usdt_' . $network . '_address', $address );
    wp_send_json_success();
}

// USDT rate save
add_action( 'wp_ajax_linkngon_save_usdt_rate', 'linkngon_save_usdt_rate' );
function linkngon_save_usdt_rate() {
    check_ajax_referer( 'linkngon_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
    $rate = floatval( $_POST['rate'] ?? 25000 );
    if ( $rate <= 0 ) wp_send_json_error( 'Invalid rate' );
    update_option( 'linkngon_usdt_rate', $rate );
    wp_send_json_success();
}
