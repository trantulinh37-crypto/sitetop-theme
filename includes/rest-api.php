<?php
/**
 * Traffictop.net V2 - REST API
 *
 * Endpoint: POST /wp-json/traffictop/v1/shortlinks
 * Auth: Header `X-Api-Key: <key>` — verify qua user meta `traffictop_api_key`
 * Body: JSON { url, alias?, fallback_url? }
 * Response: { success: true, short_url, code, alias }
 *
 * Rate limit dùng chung với AJAX `shorten_url` (transient-based).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function() {
    register_rest_route( 'traffictop/v1', '/shortlinks', array(
        'methods'             => 'POST',
        'callback'            => 'traffictop_rest_create_shortlink',
        'permission_callback' => 'traffictop_rest_api_auth',
    ) );
} );

/**
 * Auth: tìm user qua header X-Api-Key → user meta traffictop_api_key.
 * Set current user nếu match, để downstream xài get_current_user_id().
 */
function traffictop_rest_api_auth( WP_REST_Request $request ) {
    $key = $request->get_header( 'x_api_key' );
    if ( empty( $key ) ) {
        return new WP_Error( 'unauthorized', 'Missing X-Api-Key header', array( 'status' => 401 ) );
    }
    $key = sanitize_text_field( $key );
    if ( strlen( $key ) < 16 ) {
        return new WP_Error( 'unauthorized', 'Invalid API key format', array( 'status' => 401 ) );
    }

    $users = get_users( array(
        'meta_key'   => 'traffictop_api_key',
        'meta_value' => $key,
        'number'     => 1,
        'fields'     => 'ID',
    ) );
    if ( empty( $users ) ) {
        return new WP_Error( 'unauthorized', 'Invalid API key', array( 'status' => 401 ) );
    }
    $uid = (int) $users[0];

    if ( get_user_meta( $uid, 'traffictop_banned', true ) ) {
        return new WP_Error( 'forbidden', 'Tài khoản đã bị khóa', array( 'status' => 403 ) );
    }
    if ( get_user_meta( $uid, 'traffictop_deleted', true ) ) {
        return new WP_Error( 'forbidden', 'Tài khoản đã bị xóa', array( 'status' => 403 ) );
    }

    wp_set_current_user( $uid );
    return true;
}

/**
 * POST /shortlinks — tạo shortlink mới với created_via='api'.
 */
function traffictop_rest_create_shortlink( WP_REST_Request $request ) {
    $uid = get_current_user_id();
    if ( ! $uid ) {
        return new WP_Error( 'unauthorized', 'No user context', array( 'status' => 401 ) );
    }

    // Rate limit (transient-based, 10/min/user — dùng chung action 'shorten_url')
    $rate = function_exists( 'traffictop_rate_limit_check' )
        ? traffictop_rate_limit_check( 'shorten_url' )
        : array( 'allowed' => true );
    if ( empty( $rate['allowed'] ) ) {
        return new WP_Error(
            'rate_limited',
            'Quá nhiều yêu cầu, thử lại sau',
            array( 'status' => 429, 'retry_after' => $rate['retry_after'] ?? 60 )
        );
    }

    $params = $request->get_json_params();
    if ( ! is_array( $params ) ) $params = array();

    $url      = isset( $params['url'] ) ? esc_url_raw( (string) $params['url'] ) : '';
    $alias    = isset( $params['alias'] ) ? sanitize_text_field( (string) $params['alias'] ) : '';
    $fallback = isset( $params['fallback_url'] ) ? esc_url_raw( (string) $params['fallback_url'] ) : '';

    if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        return new WP_Error( 'invalid_url', 'URL không hợp lệ', array( 'status' => 400 ) );
    }

    $result = traffictop_create_user_shortlink( $uid, $url, $alias, $fallback, 'api' );
    if ( is_wp_error( $result ) ) {
        $result->add_data( array( 'status' => 400 ) );
        return $result;
    }

    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';
    $sl = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}user_shortlinks WHERE id=%d", $result ) );
    if ( ! $sl ) {
        return new WP_Error( 'db_error', 'Không tìm thấy shortlink vừa tạo', array( 'status' => 500 ) );
    }

    return rest_ensure_response( array(
        'success'      => true,
        'id'           => (int) $sl->id,
        'short_url'    => home_url( '/' . ( $sl->alias ?: $sl->code ) ),
        'code'         => $sl->code,
        'alias'        => $sl->alias,
        'original_url' => $sl->original_url,
        'created_at'   => $sl->created_at,
    ) );
}

/**
 * Helper: generate API key (32 chars, alphanumeric).
 */
function traffictop_generate_api_key() {
    return wp_generate_password( 32, false, false );
}
