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
