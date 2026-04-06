<?php
/**
 * LinkNgon V2 - Image Upload
 * ImgBB API → WordPress media library fallback
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function linkngon_upload_to_imgbb( $image_data ) {
    $api_key = linkngon_get_option('imgbb_api_key', '');
    if ( empty($api_key) ) return linkngon_upload_to_wp_media($image_data);

    $response = wp_remote_post('https://api.imgbb.com/1/upload', array(
        'body' => array('key' => $api_key, 'image' => base64_encode($image_data)),
        'timeout' => 30,
    ));

    if ( is_wp_error($response) ) return linkngon_upload_to_wp_media($image_data);

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ( !empty($body['data']['url']) ) return $body['data']['url'];

    return linkngon_upload_to_wp_media($image_data);
}

function linkngon_upload_to_wp_media( $image_data ) {
    $upload = wp_upload_bits('linkngon-upload-' . time() . '.jpg', null, $image_data);
    return !$upload['error'] ? $upload['url'] : false;
}

/**
 * Upload file from $_FILES entry: ImgBB first, fallback WordPress media.
 * @param array $file Single $_FILES entry (e.g. $_FILES['screenshot_desktop'])
 * @return string|false URL on success, false on failure
 */
function linkngon_upload_file( $file ) {
    if ( empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) ) return false;

    $api_key = linkngon_get_option('imgbb_api_key', '');
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
