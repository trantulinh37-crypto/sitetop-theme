<?php
/**
 * SiteTop.net V2 - Image Upload
 * ImgBB API → WordPress media library fallback
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function sitetop_upload_to_imgbb( $image_data ) {
    $api_key = sitetop_get_option('imgbb_api_key', '');
    if ( empty($api_key) ) return sitetop_upload_to_wp_media($image_data);

    $response = wp_remote_post('https://api.imgbb.com/1/upload', array(
        'body' => array('key' => $api_key, 'image' => base64_encode($image_data)),
        'timeout' => 30,
    ));

    if ( is_wp_error($response) ) return sitetop_upload_to_wp_media($image_data);

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ( !empty($body['data']['url']) ) return $body['data']['url'];

    return sitetop_upload_to_wp_media($image_data);
}

/**
 * Ảnh còn sống không?
 *
 * PHẢI kiểm ở server, không kiểm được ở trình duyệt: khi ảnh trên ImgBB đã bị xoá,
 * i.ibb.co trả về HTTP 404 nhưng KÈM một file PNG 180x180 ("imgbb.com image not
 * found"). Trình duyệt tải file đó thành công nên sự kiện onerror KHÔNG bao giờ
 * chạy — chỉ mã HTTP mới phân biệt được ảnh thật với ảnh báo lỗi.
 *
 * Kết quả cache bằng transient nên mỗi URL chỉ gọi mạng 1 lần/6 giờ.
 *
 * @param string $url
 * @return bool
 */
function sitetop_image_url_alive( $url ) {
    $url = trim( (string) $url );
    if ( $url === '' || ! preg_match( '#^https?://#i', $url ) ) return false;

    $key    = 'st_img_ok_' . md5( $url );
    $cached = get_transient( $key );
    if ( $cached !== false ) return $cached === '1';

    $resp = wp_remote_head( $url, array( 'timeout' => 5, 'redirection' => 3 ) );
    $code = is_wp_error( $resp ) ? 0 : (int) wp_remote_retrieve_response_code( $resp );

    // Lỗi mạng phía mình (code 0) KHÔNG chứng minh được ảnh chết → cho qua, cache ngắn
    // để thử lại sớm. Chỉ mã lỗi rõ ràng từ máy chủ ảnh mới coi là chết.
    $ok = ( 0 === $code ) || ( $code >= 200 && $code < 400 );
    set_transient( $key, $ok ? '1' : '0', $ok ? 6 * HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS );
    return $ok;
}

function sitetop_upload_to_wp_media( $image_data ) {
    $upload = wp_upload_bits('sitetop-upload-' . time() . '.jpg', null, $image_data);
    return !$upload['error'] ? $upload['url'] : false;
}

/**
 * Upload file from $_FILES entry: ImgBB first, fallback WordPress media.
 * @param array $file Single $_FILES entry (e.g. $_FILES['screenshot_desktop'])
 * @return string|false URL on success, false on failure
 */
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

    $api_key = sitetop_get_option('imgbb_api_key', '');
    if ( !empty($api_key) ) {
        $image_data = file_get_contents($file['tmp_name']);
        if ( $image_data ) {
            $response = wp_remote_post('https://api.imgbb.com/1/upload', array(
                'body' => array('key' => $api_key, 'image' => base64_encode($image_data)),
                'timeout' => 30,
            ));
            if ( !is_wp_error($response) ) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if ( !empty($body['data']['url']) ) return $body['data']['url'];
            }
        }
    }

    // Fallback: WordPress media
    if ( !function_exists('wp_handle_upload') ) require_once ABSPATH . 'wp-admin/includes/file.php';
    $uploaded = wp_handle_upload($file, array('test_form' => false));
    return ($uploaded && !isset($uploaded['error'])) ? $uploaded['url'] : false;
}
