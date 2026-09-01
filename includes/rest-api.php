<?php
/**
 * SiteTop.net V2 - GET API endpoints
 *
 * Endpoints (intercepted ở init hook):
 *   GET /api?api=TOKEN&url=DEST&sub_link=FALLBACK  → JSON (cho tool/script)
 *   GET /st?api=TOKEN&url=DEST&sub_link=FALLBACK   → QUICKLINK: 302 về trang
 *       shortlink /{code} (visitor đi qua flow unlock rồi tới DEST). Reuse
 *       shortlink active cùng (user, url) — mỗi visit KHÔNG đẻ thêm row.
 *
 * Auth: query param `api` match user meta `sitetop_api_token` (24-char,
 *       user tự sinh/reset trong dashboard publisher).
 * Rate limit: dùng chung action 'shorten_url' (transient-based) — chỉ gác
 *       đường TẠO MỚI; đường reuse của /st không giới hạn (tương đương mở /{code}).
 *
 * Response /api: JSON (kèm alias chuẩn Link4M: status, shortenedUrl, message)
 *   200: { success: true, status: "success", id, short_url, shortenedUrl, code, original_url }
 *   400/401/403/429/500: { success: false, status: "error", error, message }
 * Response /st: 302 Location /{code}; lỗi → trang HTML tối giản đúng status
 *   (KHÔNG redirect về url khi token sai — chống open-redirect).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Helper: detect if current request hits /api or /st path.
 */
function sitetop_is_api_request() {
    if ( empty( $_SERVER['REQUEST_URI'] ) ) return false;
    $uri = (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
    $uri = strtolower( trim( $uri, '/' ) );
    return ( $uri === 'api' || $uri === 'st' );
}

// Layer 1: intercept ngay khi load wp (sớm nhất, trước cả init)
add_action( 'plugins_loaded', function() {
    if ( sitetop_is_api_request() ) {
        sitetop_handle_api_shorten();
        exit;
    }
}, 0 );

// Layer 2: init priority 0 (giống pattern widget.js đã có sẵn)
add_action( 'init', function() {
    if ( sitetop_is_api_request() ) {
        sitetop_handle_api_shorten();
        exit;
    }
}, 0 );

// Layer 3: parse_request (defense — chạy trước WP routing tìm page)
add_action( 'parse_request', function() {
    if ( sitetop_is_api_request() ) {
        sitetop_handle_api_shorten();
        exit;
    }
}, 0 );

// Layer 4: template_redirect (fallback cuối — bắt cả case 404 cũng dispatch được)
add_action( 'template_redirect', function() {
    if ( sitetop_is_api_request() ) {
        sitetop_handle_api_shorten();
        exit;
    }
}, 0 );

function sitetop_handle_api_shorten() {
    // /st = QUICKLINK (visitor mở bằng trình duyệt) → 302 về trang shortlink /{code}
    // /api = API cho tool/script → JSON. Cùng auth + validate, khác định dạng trả về.
    $req_path = strtolower( trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ) );
    $is_quicklink = ( $req_path === 'st' );

    // Link dán qua forum/chat hay bị HTML-encode '&' thành '&amp;' → $_GET nhận key 'amp;url'/'amp;api'
    foreach ( array( 'api', 'url', 'sub_link' ) as $qk ) {
        if ( ! isset( $_GET[ $qk ] ) && isset( $_GET[ 'amp;' . $qk ] ) ) {
            $_GET[ $qk ] = $_GET[ 'amp;' . $qk ];
        }
    }

    // Trả lỗi đúng kênh: /st → trang HTML tối giản (KHÔNG redirect về url khi lỗi — tránh thành
    // open-redirect cho phishing); /api → JSON như cũ.
    $api_fail = function( $status, $msg, $extra = array() ) use ( $is_quicklink ) {
        status_header( $status );
        if ( $is_quicklink ) {
            header( 'Content-Type: text/html; charset=utf-8' );
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Quicklink</title></head><body style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;text-align:center;padding:60px 20px;color:#334155"><p style="font-size:15px">' . esc_html( $msg ) . '</p></body></html>';
        } else {
            // `status`/`message`: alias chuẩn Link4M — khớp tài liệu ở dashboard, tool bên ngoài đọc được
            echo wp_json_encode( array_merge(
                array( 'success' => false, 'status' => 'error', 'error' => $msg, 'message' => $msg ),
                $extra
            ) );
        }
    };

    if ( ! $is_quicklink ) {
        header( 'Content-Type: application/json; charset=utf-8' );
    }

    // ── Auth: prefer token qua HEADER (Authorization: Bearer / X-Api-Token) — không lộ trong
    //    access log / Referer / lịch sử trình duyệt. Fallback query param `api` cho back-compat.
    $token = '';
    /* Đo trên production 01/09/2026: `Authorization: Bearer` TỚI ĐƯỢC PHP, còn
       `X-Api-Token` BỊ GỠ dọc đường (Cloudflare/LiteSpeed) nên không bao giờ nhận
       được. Giữ lại làm dự phòng, nhưng tài liệu ở dashboard chỉ nêu Bearer. */
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? ( $_SERVER['HTTP_X_API_TOKEN'] ?? '' );
    if ( ! empty( $hdr ) ) {
        $token = sanitize_text_field( trim( preg_replace( '/^Bearer\s+/i', '', wp_unslash( $hdr ) ) ) );
    }
    if ( empty( $token ) && isset( $_GET['api'] ) ) {
        $token = sanitize_text_field( wp_unslash( $_GET['api'] ) );
    }
    if ( empty( $token ) || strlen( $token ) < 16 ) {
        $api_fail( 401, 'Missing or invalid api token' );
        return;
    }

    // Lookup user by meta `sitetop_api_token` (user tự sinh trong dashboard)
    $users = get_users( array(
        'meta_key'   => 'sitetop_api_token',
        'meta_value' => $token,
        'number'     => 1,
        'fields'     => 'ID',
    ) );

    /* KHOÁ LIÊN KẾT NHANH (31/08/2026) — chỉ chấp nhận ở /st, tuyệt đối không ở /api.
       Liên kết nhanh là URL bấm được nên nội dung luôn công khai; trước đây nó mang
       chính API token, tức token bị phơi ra và kẻ nhặt được dùng luôn cho /api.
       Tách khoá riêng thì lộ cũng chỉ tạo được link ghi công cho chính chủ, không mở
       thêm quyền gì. VẪN nhận api token ở /st để link publisher đang dùng không vỡ. */
    if ( empty( $users ) && $is_quicklink ) {
        $users = get_users( array(
            'meta_key'   => 'sitetop_quick_key',
            'meta_value' => $token,
            'number'     => 1,
            'fields'     => 'ID',
        ) );
    }

    if ( empty( $users ) ) {
        $api_fail( 401, 'Invalid api token' );
        return;
    }
    $uid = (int) $users[0];

    if ( get_user_meta( $uid, 'sitetop_banned', true ) ) {
        $api_fail( 403, 'Tài khoản đã bị khóa' );
        return;
    }
    if ( get_user_meta( $uid, 'sitetop_deleted', true ) ) {
        $api_fail( 403, 'Tài khoản đã bị xóa' );
        return;
    }
    // Chưa được duyệt Nguồn file gốc → API không hoạt động (chặn cả đường /st reuse
    // link cũ, vốn không đi qua sitetop_create_user_shortlink()).
    if ( function_exists( 'sitetop_source_is_approved' ) && ! sitetop_source_is_approved( $uid ) ) {
        $api_fail( 403, sitetop_source_block_message( $uid ) );
        return;
    }

    wp_set_current_user( $uid );

    // ── Validate URL params (TRƯỚC rate-limit để đường reuse của /st không tốn quota)
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
        $api_fail( 400, 'Missing or invalid url parameter' );
        return;
    }

    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';

    // ── /st: REUSE shortlink active cùng (user, url). Quicklink được share công khai — mỗi
    //    visit mà tạo row mới sẽ spam bảng user_shortlinks; đường reuse bỏ qua rate limit
    //    (tương đương visitor mở thẳng /{code}).
    $shortlink_id = 0;
    if ( $is_quicklink ) {
        $shortlink_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$p}user_shortlinks WHERE user_id=%d AND original_url=%s AND status='active' ORDER BY id DESC LIMIT 1",
            $uid, $url
        ) );
    }

    if ( ! $shortlink_id ) {
        // Rate limit (10 req/min — shared với AJAX shorten_url) — chỉ gác đường TẠO MỚI
        if ( function_exists( 'sitetop_rate_limit_check' ) ) {
            $rate = sitetop_rate_limit_check( 'shorten_url' );
            if ( empty( $rate['allowed'] ) ) {
                header( 'Retry-After: ' . ( $rate['retry_after'] ?? 60 ) );
                $api_fail( 429, 'Quá nhiều yêu cầu, thử lại sau', array( 'retry_after' => $rate['retry_after'] ?? 60 ) );
                return;
            }
        }

        // ── Tạo shortlink với created_via='api' → badge "API" hiện trong admin
        $result = sitetop_create_user_shortlink( $uid, $url, '', $sub_link, 'api' );
        if ( is_wp_error( $result ) ) {
            $api_fail( 400, $result->get_error_message() );
            return;
        }
        $shortlink_id = (int) $result;
    }

    $sl = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}user_shortlinks WHERE id=%d", $shortlink_id ) );
    if ( ! $sl ) {
        $api_fail( 500, 'DB error' );
        return;
    }

    $short_url = home_url( '/' . $sl->code );

    if ( $is_quicklink ) {
        /* URL của chính request này chứa CẢ api token LẪN link đích. Mặc định trình duyệt
           gửi nguyên URL đó trong header Referer sang trang kế tiếp, và trang đích cuối
           cùng cũng có thể đọc được — tức là token của publisher rò sang bên thứ ba.
           no-referrer chặn hẳn việc chuyển tiếp đó. Không sửa được việc URL hiện trên
           thanh địa chỉ (bản chất của liên kết bấm được), nhưng chặn được đường rò xa nhất.
           no-store để proxy/CDN không giữ lại bản sao URL kèm token. */
        header( 'Referrer-Policy: no-referrer' );
        header( 'Cache-Control: no-store, private' );
        // 302 chuẩn về trang shortlink; DỪNG THỰC THI ngay sau redirect. Visitor đi qua
        // flow unlock của /{code} rồi mới tới URL đích — không 302 thẳng về đích (mất flow
        // kiếm tiền + thành open-redirect).
        wp_safe_redirect( $short_url, 302 );
        exit;
    }

    // `status`/`shortenedUrl`: alias chuẩn Link4M — dashboard đang tài liệu hóa đúng 2 field này;
    // giữ song song field cũ (success/short_url/...) để integrator hiện có không vỡ.
    echo wp_json_encode( array(
        'success'      => true,
        'status'       => 'success',
        'id'           => (int) $sl->id,
        'short_url'    => $short_url,
        'shortenedUrl' => $short_url,
        'code'         => $sl->code,
        /* KHÔNG trả original_url nữa (31/08/2026). Người gọi vừa tự gửi URL đó lên nên
           echo lại là thừa, mà nếu publisher gọi API từ TRÌNH DUYỆT (token nằm trong mã
           nguồn trang, như Cách 3) thì phản hồi này đi thẳng vào máy member — member mở
           DevTools là đọc được link đích, khỏi làm nhiệm vụ. Bỏ đi thì không mất gì:
           caller đã biết URL, còn link rút gọn vẫn trả đủ ở short_url/shortenedUrl. */
    ) );
}
