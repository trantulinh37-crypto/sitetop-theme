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

    // Notify customer
    if ( function_exists('linkngon_create_notification') ) {
        linkngon_create_notification($c->customer_id, 'success', 'Chiến dịch đã được duyệt', 'Chiến dịch <strong>'.esc_html($c->title).'</strong> đã được duyệt và đang hoạt động.');
    }
    return true;
}

function linkngon_reject_campaign( $campaign_id, $reason = '' ) {
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';
    $c = linkngon_get_campaign( $campaign_id );
    $now = linkngon_current_time();
    $reason = sanitize_text_field($reason);
    $wpdb->update("{$p}keyword_campaigns", array('status'=>'rejected','reject_reason'=>$reason,'updated_at'=>$now), array('id'=>$campaign_id));
    if ( $c && $c->order_id ) {
        $wpdb->update("{$p}customer_orders", array('status'=>'rejected','reject_reason'=>$reason,'updated_at'=>$now), array('id'=>$c->order_id));
    }

    // Notify customer
    if ( $c && function_exists('linkngon_create_notification') ) {
        $msg = 'Chiến dịch <strong>'.esc_html($c->title).'</strong> đã bị từ chối.';
        if ( $reason ) $msg .= '<br>Lý do: '.esc_html($reason);
        linkngon_create_notification($c->customer_id, 'error', 'Chiến dịch bị từ chối', $msg);
    }
    return true;
}

function linkngon_pause_campaign( $campaign_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';
    $c = linkngon_get_campaign( $campaign_id );
    $result = linkngon_update_campaign( $campaign_id, array( 'status' => 'paused' ) );
    if ( $result ) {
        if ( $c && $c->order_id ) {
            $wpdb->update("{$p}customer_orders", array('status'=>'paused','updated_at'=>linkngon_current_time()), array('id'=>$c->order_id));
        }
        delete_transient( 'linkngon_eligible_campaigns' );
    }
    return $result;
}

function linkngon_resume_campaign( $campaign_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'linkngon_';
    // Check customer balance before resuming
    $c = linkngon_get_campaign( $campaign_id );
    if ( $c && $c->customer_id ) {
        $bal = linkngon_get_customer_balance_amount( $c->customer_id );
        $min = (int) linkngon_get_option( 'customer_min_balance', 20000 );
        if ( $bal !== false && $bal <= $min ) {
            return new WP_Error( 'insufficient', 'Customer balance không đủ để resume' );
        }
    }
    $result = linkngon_update_campaign( $campaign_id, array( 'status' => 'active' ) );
    if ( $result ) {
        if ( $c && $c->order_id ) {
            $wpdb->update("{$p}customer_orders", array('status'=>'active','updated_at'=>linkngon_current_time()), array('id'=>$c->order_id));
        }
        delete_transient( 'linkngon_eligible_campaigns' );
    }
    return $result;
}
