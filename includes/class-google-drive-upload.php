<?php
/**
 * SiteTop.net V2 - Image Upload
 *
 * Thư viện media của WordPress (ảnh nằm trên máy chủ sitetop.net) là nơi lưu CHÍNH.
 * ImgBB chỉ còn là phương án dự phòng khi máy chủ không ghi được file.
 *
 * Vì sao đảo lại thứ tự: ImgBB là dịch vụ miễn phí của bên thứ ba và đã nhận upload,
 * trả JSON thành công kèm URL, nhưng file ảnh lại hỏng — thư viện trên chính
 * imgbb.com hiện "image not found". Hậu quả là một URL chết nằm im trong database,
 * mãi đến lúc user làm nhiệm vụ mới lộ ra. Ảnh để trên máy chủ của mình thì không
 * có ai ở giữa để hỏng.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Lưu ảnh từ dữ liệu nhị phân. Máy chủ site trước, ImgBB dự phòng.
 *
 * Giữ nguyên tên hàm cũ vì đang có nơi gọi; thứ tự ưu tiên bên trong đã đảo.
 *
 * @param string $image_data Dữ liệu nhị phân của ảnh.
 * @return string|false URL ảnh, hoặc false nếu cả hai đường đều hỏng.
 */
function sitetop_upload_to_imgbb( $image_data ) {
    $local = sitetop_upload_to_wp_media( $image_data );
    if ( $local ) return $local;

    // Chỉ tới đây khi máy chủ không ghi được file (hết dung lượng, sai quyền thư mục).
    $api_key = sitetop_get_option('imgbb_api_key', '');
    if ( empty($api_key) ) return false;

    $response = wp_remote_post('https://api.imgbb.com/1/upload', array(
        'body' => array('key' => $api_key, 'image' => base64_encode($image_data)),
        'timeout' => 30,
    ));

    if ( is_wp_error($response) ) return false;

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ( ! empty( $body['data']['url'] ) && sitetop_imgbb_url_usable( $body['data']['url'] ) ) {
        return $body['data']['url'];
    }

    return false;
}

/**
 * ImgBB có tạo được ảnh dùng được thật không?
 *
 * ImgBB nhận upload, trả về JSON thành công kèm URL, nhưng file ảnh lại hỏng — thư
 * viện ảnh trên chính imgbb.com hiện "image not found" cho các ảnh vừa tải lên. Tin
 * vào JSON là lưu vào database một URL chết, và mãi sau mới phát hiện khi user làm
 * nhiệm vụ. Kiểm ngay tại đây; hỏng thì rơi về thư viện media của WordPress —
 * ảnh nằm trên máy chủ của mình, không phụ thuộc bên thứ ba nữa.
 *
 * @param string $url
 * @return bool
 */
function sitetop_imgbb_url_usable( $url ) {
    $resp = wp_remote_get( $url, array(
        'timeout'     => 8,
        'redirection' => 3,
        // Chỉ cần vài byte đầu để đọc mã HTTP + kiểu nội dung, không tải cả ảnh.
        'headers'     => array( 'Range' => 'bytes=0-63' ),
        'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
    ) );
    if ( is_wp_error( $resp ) ) return true;   // lỗi mạng phía mình → không kết luận, cứ dùng URL của ImgBB

    $code = (int) wp_remote_retrieve_response_code( $resp );
    return $code >= 200 && $code < 400;
}

/**
 * Ảnh còn sống không?
 *
 * PHẢI kiểm ở server, không kiểm được ở trình duyệt: khi ảnh trên ImgBB đã bị xoá,
 * i.ibb.co trả về HTTP 404 nhưng KÈM một file PNG 180x180 ("imgbb.com image not
 * found"). Trình duyệt tải file đó thành công nên sự kiện onerror KHÔNG bao giờ
 * chạy — chỉ mã HTTP mới phân biệt được ảnh thật với ảnh báo lỗi.
 *
 * CHỈ coi là chết khi máy chủ ảnh nói thẳng "không có tài nguyên này" — 404 hoặc 410.
 * Mọi mã khác (403 chặn bot, 405 không nhận HEAD, 429 quá tải, 5xx, hay lỗi mạng phía
 * mình) đều nói về REQUEST CỦA SERVER chứ không nói ảnh có tồn tại hay không: bản đầu
 * coi luôn những mã đó là chết nên đã giấu mất cả ảnh đang hiển thị tốt trên trình duyệt.
 * Nghi ngờ thì cho hiện — hiện nhầm ảnh chết còn đỡ hơn giấu mất ảnh đúng.
 *
 * Kết quả cache bằng transient nên mỗi URL chỉ gọi mạng 1 lần/6 giờ.
 *
 * @param string $url
 * @return bool
 */
function sitetop_image_url_alive( $url ) {
    $url = trim( (string) $url );
    if ( $url === '' || ! preg_match( '#^https?://#i', $url ) ) return false;

    // Đổi tiền tố khoá so với bản đầu để bỏ hết kết quả "chết" đã cache sai.
    $key    = 'st_img_live_' . md5( $url );
    $cached = get_transient( $key );
    if ( $cached !== false ) return $cached === '1';

    // UA trình duyệt: nhiều CDN ảnh chặn thẳng UA mặc định của WordPress.
    $resp = wp_remote_head( $url, array(
        'timeout'     => 5,
        'redirection' => 3,
        'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
    ) );
    $code = is_wp_error( $resp ) ? 0 : (int) wp_remote_retrieve_response_code( $resp );

    $dead = in_array( $code, array( 404, 410 ), true );
    set_transient( $key, $dead ? '0' : '1', $dead ? 15 * MINUTE_IN_SECONDS : 6 * HOUR_IN_SECONDS );
    return ! $dead;
}

function sitetop_upload_to_wp_media( $image_data ) {
    // Đuôi file phải khớp dữ liệu thật. Trước đây luôn ghi .jpg, nên ảnh PNG/WebP bị
    // phục vụ với Content-Type sai — giờ ảnh lưu ở đây là chính nên phải làm đúng.
    $ext  = 'jpg';
    if ( function_exists( 'finfo_open' ) && ( $finfo = finfo_open( FILEINFO_MIME_TYPE ) ) ) {
        $map  = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp' );
        $mime = finfo_buffer( $finfo, $image_data );
        finfo_close( $finfo );
        if ( isset( $map[ $mime ] ) ) $ext = $map[ $mime ];
    }
    $upload = wp_upload_bits( 'sitetop-upload-' . time() . '-' . wp_generate_password( 6, false ) . '.' . $ext, null, $image_data );
    return empty( $upload['error'] ) && ! empty( $upload['url'] ) ? $upload['url'] : false;
}
/**
 * AJAX: Upload screenshot to ImgBB immediately (called on file select).
 * Returns ImgBB URL for instant preview + hidden input storage.
 */
add_action( 'wp_ajax_sitetop_upload_screenshot', 'sitetop_ajax_upload_screenshot' );
function sitetop_ajax_upload_screenshot() {
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Chưa đăng nhập' );
    // Accept both admin and customer nonce
    $valid = wp_verify_nonce( $_POST['nonce'] ?? '', 'sitetop_nonce' )
          || wp_verify_nonce( $_POST['nonce'] ?? '', 'sitetop_admin_nonce' );
    if ( ! $valid ) wp_send_json_error( 'Nonce không hợp lệ' );

    if ( empty( $_FILES['file']['name'] ) ) wp_send_json_error( 'Không có file' );

    // Validate image type
    $allowed = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
    $finfo   = finfo_open( FILEINFO_MIME_TYPE );
    $mime    = finfo_file( $finfo, $_FILES['file']['tmp_name'] );
    finfo_close( $finfo );
    if ( ! in_array( $mime, $allowed ) ) wp_send_json_error( 'File không phải ảnh hợp lệ' );

    // Max 5MB
    if ( $_FILES['file']['size'] > 5 * 1024 * 1024 ) wp_send_json_error( 'File quá lớn (tối đa 5MB)' );

    $url = sitetop_upload_file( $_FILES['file'] );
    if ( $url ) {
        wp_send_json_success( array( 'url' => $url ) );
    } else {
        wp_send_json_error( 'Upload thất bại' );
    }
}

function sitetop_upload_file( $file ) {
    if ( empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) ) return false;

    // Allow-list: only image files (extension + real MIME via finfo on content)
    $allowed_ext  = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );
    $allowed_mime = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
    $ext = strtolower( pathinfo( $file['name'] ?? '', PATHINFO_EXTENSION ) );
    if ( ! in_array( $ext, $allowed_ext, true ) ) return false;
    $finfo = finfo_open( FILEINFO_MIME_TYPE );
    $mime  = $finfo ? finfo_file( $finfo, $file['tmp_name'] ) : '';
    if ( $finfo ) finfo_close( $finfo );
    if ( ! in_array( $mime, $allowed_mime, true ) ) return false;

    // CHÍNH: lưu thẳng vào thư viện media, ảnh nằm trên sitetop.net.
    // Đọc dữ liệu TRƯỚC vì wp_handle_upload() sẽ di chuyển file tạm đi.
    $image_data = file_get_contents( $file['tmp_name'] );

    if ( !function_exists('wp_handle_upload') ) require_once ABSPATH . 'wp-admin/includes/file.php';
    $uploaded = wp_handle_upload( $file, array( 'test_form' => false ) );
    if ( $uploaded && empty( $uploaded['error'] ) && ! empty( $uploaded['url'] ) ) return $uploaded['url'];

    // DỰ PHÒNG: máy chủ không ghi được file (hết dung lượng, sai quyền thư mục) thì
    // mới nhờ ImgBB, và vẫn phải kiểm ảnh có dùng được thật không trước khi trả URL.
    $api_key = sitetop_get_option('imgbb_api_key', '');
    if ( !empty($api_key) && $image_data ) {
        $response = wp_remote_post('https://api.imgbb.com/1/upload', array(
            'body' => array('key' => $api_key, 'image' => base64_encode($image_data)),
            'timeout' => 30,
        ));
        if ( !is_wp_error($response) ) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ( ! empty( $body['data']['url'] ) && sitetop_imgbb_url_usable( $body['data']['url'] ) ) {
                return $body['data']['url'];
            }
        }
    }

    return false;
}
