<?php
/**
 * LinkNgon V2 - Campaign Management
 * Campaign CRUD, approval, status management
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function linkngon_approve_campaign( $campaign_id, $admin_id = 0 ) {
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';
    $c = linkngon_get_campaign( $campaign_id );
    if ( !$c || $c->status !== 'pending' ) return new WP_Error('invalid', 'Campaign không hợp lệ');

    // Check customer balance >= min
    $min = (int) linkngon_get_option('customer_min_balance', 20000);
    $bal = linkngon_get_customer_balance_amount($c->customer_id);
    if ( $bal !== false && $bal < $min ) return new WP_Error('insufficient', 'Customer balance không đủ');

    $wpdb->update("{$p}keyword_campaigns", array('status'=>'active','updated_at'=>linkngon_current_time()), array('id'=>$campaign_id));
    if ( $c->order_id ) {
        $wpdb->update("{$p}customer_orders", array('status'=>'active','approved_by'=>$admin_id,'approved_at'=>linkngon_current_time(),'updated_at'=>linkngon_current_time()), array('id'=>$c->order_id));
    }

    // Invalidate cache
    delete_transient('linkngon_eligible_campaigns');
    return true;
}

function linkngon_reject_campaign( $campaign_id, $reason = '' ) {
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';
    $wpdb->update("{$p}keyword_campaigns", array('status'=>'rejected','reject_reason'=>sanitize_text_field($reason),'updated_at'=>linkngon_current_time()), array('id'=>$campaign_id));
    return true;
}

function linkngon_pause_campaign( $campaign_id ) {
    return linkngon_update_campaign($campaign_id, array('status'=>'paused'));
}

function linkngon_resume_campaign( $campaign_id ) {
    return linkngon_update_campaign($campaign_id, array('status'=>'active'));
}
