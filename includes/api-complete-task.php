<?php
/**
 * SiteTop.net — POST /api/complete-task
 *
 * Cổng RIÊNG cho hệ thống ngoài báo "user đã làm xong nhiệm vụ" và nhận mã hoàn thành.
 * THÊM VÀO, không thay luồng đang chạy: widget → đếm ngược → hiện mã → user dán mã vào
 * trang nhiệm vụ → sitetop_verify trả thưởng. Luồng đó giữ nguyên 100%.
 *
 * Payload (JSON hoặc form-encoded):
 *   { "campaign_id": 12, "device_type": "desktop"|"mobile", "session_id": "..." }
 *
 * Không phân biệt URL desktop/mobile — chỉ cần đúng camp, đúng phiên, đủ thời gian onsite.
 * Toàn bộ phần "đủ điều kiện chưa" giao lại cho sitetop_get_widget_code() — cùng một hàm
 * mà widget đang dùng, nên API và widget không bao giờ lệch luật.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Trả JSON kèm CORS cho đúng domain của camp rồi thoát. */
function sitetop_api_json( $status, $data, $allow_origin = '' ) {
    if ( ! headers_sent() ) {
        status_header( $status );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Cache-Control: no-store' );
        if ( $allow_origin ) {
            header( 'Access-Control-Allow-Origin: ' . $allow_origin );
            header( 'Vary: Origin' );
        }
    }
    echo wp_json_encode( $data );
    exit;
}

function sitetop_api_complete_task() {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

    $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( $_SERVER['HTTP_ORIGIN'] ) : '';

    // Preflight: chưa biết camp nên chưa chốt được domain — trả khung cho phép chung,
    // request thật bên dưới mới là chỗ kiểm origin có đúng domain camp hay không.
    if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'OPTIONS' ) {
        if ( ! headers_sent() ) {
            status_header( 204 );
            header( 'Access-Control-Allow-Origin: ' . ( $origin ?: '*' ) );
            header( 'Access-Control-Allow-Methods: POST, OPTIONS' );
            header( 'Access-Control-Allow-Headers: Content-Type' );
            header( 'Access-Control-Max-Age: 600' );
            header( 'Vary: Origin' );
        }
        exit;
    }

    if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
        sitetop_api_json( 405, array( 'success' => false, 'error' => 'method_not_allowed' ), $origin );
    }

    // Nhận cả JSON lẫn form-encoded để bên tích hợp gửi kiểu nào cũng được.
    $body = array();
    $raw  = file_get_contents( 'php://input' );
    if ( $raw ) {
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) ) $body = $decoded;
    }
    if ( ! $body ) $body = $_POST;

    $campaign_id = (int) ( $body['campaign_id'] ?? 0 );
    $session_id  = sanitize_text_field( (string) ( $body['session_id'] ?? '' ) );
    $device_type = sanitize_text_field( (string) ( $body['device_type'] ?? '' ) );
    if ( ! in_array( $device_type, array( 'desktop', 'mobile' ), true ) ) {
        $device_type = sitetop_current_device_type();
    }

    if ( ! $campaign_id || ! $session_id ) {
        sitetop_api_json( 400, array( 'success' => false, 'error' => 'missing_params' ), $origin );
    }

    if ( function_exists( 'sitetop_rate_limit_check' ) ) {
        $rate = sitetop_rate_limit_check( 'get_code' );
        if ( empty( $rate['allowed'] ) ) {
            sitetop_api_json( 429, array( 'success' => false, 'error' => 'rate_limited' ), $origin );
        }
    }

    // Phiên phải TỒN TẠI và thuộc ĐÚNG camp được khai. Thiếu ràng buộc này thì ai cầm
    // session_id của camp A có thể xin mã cho camp B.
    $visit = $wpdb->get_row( $wpdb->prepare(
        "SELECT v.id, v.campaign_id, v.step, v.reward_paid,
                c.target_url, c.target_url_desktop, c.target_url_mobile
         FROM {$p}shortlink_visits v
         INNER JOIN {$p}keyword_campaigns c ON v.campaign_id = c.id
         WHERE v.session_id = %s LIMIT 1", $session_id
    ) );

    if ( ! $visit ) {
        sitetop_api_json( 404, array( 'success' => false, 'error' => 'session_not_found' ), $origin );
    }
    if ( (int) $visit->campaign_id !== $campaign_id ) {
        sitetop_api_json( 403, array( 'success' => false, 'error' => 'campaign_mismatch' ), $origin );
    }

    // Chỉ mở CORS cho đúng domain của camp — không phản chiếu bừa Origin của bất kỳ ai.
    $camp_domain  = sitetop_campaign_domain( $visit );
    $origin_host  = $origin ? parse_url( $origin, PHP_URL_HOST ) : '';
    $origin_host  = $origin_host ? preg_replace( '/^www\./', '', strtolower( $origin_host ) ) : '';
    $allow_origin = ( $origin_host && $origin_host === $camp_domain ) ? $origin : '';

    // Đủ điều kiện hay chưa (onsite, cờ hợp lệ, đã trả thưởng chưa...) do đúng hàm mà
    // widget đang dùng quyết định — API không tự đặt luật riêng.
    $code = sitetop_get_widget_code( $session_id );
    if ( is_wp_error( $code ) ) {
        sitetop_api_json( 409, array(
            'success' => false,
            'error'   => $code->get_error_code(),
            'message' => $code->get_error_message(),
            'data'    => $code->get_error_data(),
        ), $allow_origin );
    }

    sitetop_api_json( 200, array(
        'success'     => true,
        'code'        => $code,
        'campaign_id' => $campaign_id,
        'session_id'  => $session_id,
        'device_type' => $device_type,
    ), $allow_origin );
}
