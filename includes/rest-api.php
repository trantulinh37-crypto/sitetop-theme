<?php
/**
 * Traffictop.net V2 - GET API endpoints
 *
 * Endpoints (intercepted ở init hook):
 *   GET /api?api=TOKEN&url=DEST&sub_link=FALLBACK
 *   GET /st?api=TOKEN&url=DEST&sub_link=FALLBACK
 *
 * Auth: query param `api` match user meta `traffictop_api_token` (24-char,
 *       user tự sinh/reset trong dashboard publisher).
 * Rate limit: dùng chung action 'shorten_url' (transient-based).
 *
 * Response: JSON
 *   200: { success: true, id, short_url, code, original_url }
 *   400/401/403/429/500: { success: false, error: "..." }
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', function() {
    if ( empty( $_SERVER['REQUEST_URI'] ) ) return;
    $uri = trim( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
    if ( $uri === 'api' || $uri === 'st' ) {
        traffictop_handle_api_shorten();
        exit;
    }
}, 0 );

function traffictop_handle_api_shorten() {
    header( 'Content-Type: application/json; charset=utf-8' );

    // ── Auth: token qua query param `api`
    $token = isset( $_GET['api'] ) ? sanitize_text_field( wp_unslash( $_GET['api'] ) ) : '';
    if ( empty( $token ) || strlen( $token ) < 16 ) {
        status_header( 401 );
        echo wp_json_encode( array( 'success' => false, 'error' => 'Missing or invalid api token' ) );
        return;
    }

    // Lookup user by meta `traffictop_api_token` (user tự sinh trong dashboard)
    $users = get_users( array(
        'meta_key'   => 'traffictop_api_token',
        'meta_value' => $token,
        'number'     => 1,
        'fields'     => 'ID',
    ) );
    if ( empty( $users ) ) {
        status_header( 401 );
        echo wp_json_encode( array( 'success' => false, 'error' => 'Invalid api token' ) );
        return;
    }
    $uid = (int) $users[0];

    if ( get_user_meta( $uid, 'traffictop_banned', true ) ) {
        status_header( 403 );
        echo wp_json_encode( array( 'success' => false, 'error' => 'Tài khoản đã bị khóa' ) );
        return;
    }
    if ( get_user_meta( $uid, 'traffictop_deleted', true ) ) {
        status_header( 403 );
        echo wp_json_encode( array( 'success' => false, 'error' => 'Tài khoản đã bị xóa' ) );
        return;
    }

    wp_set_current_user( $uid );

    // Rate limit (10 req/min/user — shared với AJAX shorten_url)
    if ( function_exists( 'traffictop_rate_limit_check' ) ) {
        $rate = traffictop_rate_limit_check( 'shorten_url' );
        if ( empty( $rate['allowed'] ) ) {
            status_header( 429 );
            header( 'Retry-After: ' . ( $rate['retry_after'] ?? 60 ) );
            echo wp_json_encode( array(
                'success'     => false,
                'error'       => 'Quá nhiều yêu cầu, thử lại sau',
                'retry_after' => $rate['retry_after'] ?? 60,
            ) );
            return;
        }
    }

    // ── Validate URL params
    $url      = isset( $_GET['url'] ) ? esc_url_raw( wp_unslash( $_GET['url'] ) ) : '';
    $sub_link = isset( $_GET['sub_link'] ) ? esc_url_raw( wp_unslash( $_GET['sub_link'] ) ) : '';

    // Auto-prefix https:// nếu thiếu scheme (UX — user thường paste plain domain)
    if ( $url !== '' && ! preg_match( '#^https?://#i', $url ) ) {
        $url = 'https://' . $url;
    }
    if ( $sub_link !== '' && ! preg_match( '#^https?://#i', $sub_link ) ) {
        $sub_link = 'https://' . $sub_link;
    }

    if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        status_header( 400 );
        echo wp_json_encode( array( 'success' => false, 'error' => 'Missing or invalid url parameter' ) );
        return;
    }

    // ── Tạo shortlink với created_via='api' → badge "API" hiện trong admin
    $result = traffictop_create_user_shortlink( $uid, $url, '', $sub_link, 'api' );
    if ( is_wp_error( $result ) ) {
        status_header( 400 );
        echo wp_json_encode( array( 'success' => false, 'error' => $result->get_error_message() ) );
        return;
    }

    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';
    $sl = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}user_shortlinks WHERE id=%d", $result ) );
    if ( ! $sl ) {
        status_header( 500 );
        echo wp_json_encode( array( 'success' => false, 'error' => 'DB error' ) );
        return;
    }

    echo wp_json_encode( array(
        'success'      => true,
        'id'           => (int) $sl->id,
        'short_url'    => home_url( '/' . $sl->code ),
        'code'         => $sl->code,
        'original_url' => $sl->original_url,
    ) );
}
