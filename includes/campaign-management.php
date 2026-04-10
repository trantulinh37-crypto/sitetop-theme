<?php
/**
 * Traffictop.net V2 - Campaign Management
 * Campaign CRUD, approval, status management
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function traffictop_approve_campaign( $campaign_id, $admin_id = 0 ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';
    $c = traffictop_get_campaign( $campaign_id );
    if ( !$c || $c->status !== 'pending' ) return new WP_Error('invalid', 'Campaign không hợp lệ');

    // Check customer balance >= min
    $min = (int) traffictop_get_option('customer_min_balance', 20000);
    $bal = traffictop_get_customer_balance_amount($c->customer_id);
    if ( $bal !== false && $bal < $min ) return new WP_Error('insufficient', 'Customer balance không đủ');

    $wpdb->update("{$p}keyword_campaigns", array('status'=>'active','updated_at'=>traffictop_current_time()), array('id'=>$campaign_id));
    if ( $c->order_id ) {
        $wpdb->update("{$p}customer_orders", array('status'=>'active','approved_by'=>$admin_id,'approved_at'=>traffictop_current_time(),'updated_at'=>traffictop_current_time()), array('id'=>$c->order_id));
    }

    // Invalidate cache
    delete_transient('traffictop_eligible_campaigns');
    return true;
}

function traffictop_reject_campaign( $campaign_id, $reason = '' ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';
    $c = traffictop_get_campaign( $campaign_id );
    $now = traffictop_current_time();
    $reason = sanitize_text_field($reason);
    $wpdb->update("{$p}keyword_campaigns", array('status'=>'rejected','reject_reason'=>$reason,'updated_at'=>$now), array('id'=>$campaign_id));
    if ( $c && $c->order_id ) {
        $wpdb->update("{$p}customer_orders", array('status'=>'rejected','reject_reason'=>$reason,'updated_at'=>$now), array('id'=>$c->order_id));
    }
    return true;
}

function traffictop_pause_campaign( $campaign_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';
    $c = traffictop_get_campaign( $campaign_id );
    $result = traffictop_update_campaign( $campaign_id, array( 'status' => 'paused' ) );
    if ( $result ) {
        if ( $c && $c->order_id ) {
            $wpdb->update("{$p}customer_orders", array('status'=>'paused','updated_at'=>traffictop_current_time()), array('id'=>$c->order_id));
        }
        delete_transient( 'traffictop_eligible_campaigns' );
    }
    return $result;
}

function traffictop_resume_campaign( $campaign_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'traffictop_';
    // Check customer balance before resuming
    $c = traffictop_get_campaign( $campaign_id );
    if ( $c && $c->customer_id ) {
        $bal = traffictop_get_customer_balance_amount( $c->customer_id );
        $min = (int) traffictop_get_option( 'customer_min_balance', 20000 );
        if ( $bal !== false && $bal <= $min ) {
            return new WP_Error( 'insufficient', 'Customer balance không đủ để resume' );
        }
    }
    $result = traffictop_update_campaign( $campaign_id, array( 'status' => 'active' ) );
    if ( $result ) {
        if ( $c && $c->order_id ) {
            $wpdb->update("{$p}customer_orders", array('status'=>'active','updated_at'=>traffictop_current_time()), array('id'=>$c->order_id));
        }
        delete_transient( 'traffictop_eligible_campaigns' );
    }
    return $result;
}
