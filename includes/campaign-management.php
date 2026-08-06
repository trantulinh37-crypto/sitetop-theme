<?php
/**
 * SiteTop.net V2 - Campaign Management
 * Campaign CRUD, approval, status management
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function sitetop_approve_campaign( $campaign_id, $admin_id = 0 ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $c = sitetop_get_campaign( $campaign_id );
    if ( !$c || $c->status !== 'pending' ) return new WP_Error('invalid', 'Campaign không hợp lệ');

    // Check keyword_search must have keyword
    $task_type = $c->campaign_type ?? '';
    if ( empty( $task_type ) && $c->order_id ) {
        $task_type = $wpdb->get_var( $wpdb->prepare( "SELECT task_type FROM {$p}customer_orders WHERE id=%d", $c->order_id ) ) ?: 'keyword_search';
    }
    if ( empty( $task_type ) ) $task_type = 'keyword_search';
    if ( $task_type === 'keyword_search' && trim( $c->keyword ?? '' ) === '' ) {
        return new WP_Error( 'empty_keyword', 'Chiến dịch thiếu từ khóa, không thể duyệt' );
    }

    $min = (int) sitetop_get_option('customer_min_balance', 20000);
    $bal = sitetop_get_customer_balance_amount($c->customer_id);
    $required = $min + max( (float) ($c->price_per_view ?? 0), 5000 );
    if ( $bal !== false && $bal <= $required ) return new WP_Error('insufficient', 'Số dư không đủ');

    $wpdb->update("{$p}keyword_campaigns", array('status'=>'active','updated_at'=>sitetop_current_time()), array('id'=>$campaign_id));
    if ( $c->order_id ) {
        $wpdb->update("{$p}customer_orders", array('status'=>'active','approved_by'=>$admin_id,'approved_at'=>sitetop_current_time(),'updated_at'=>sitetop_current_time()), array('id'=>$c->order_id));
    }

    // Invalidate cache
    delete_transient('sitetop_eligible_campaigns');
    return true;
}

function sitetop_reject_campaign( $campaign_id, $reason = '' ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $c = sitetop_get_campaign( $campaign_id );
    $now = sitetop_current_time();
    $reason = sanitize_text_field($reason);
    $wpdb->update("{$p}keyword_campaigns", array('status'=>'rejected','reject_reason'=>$reason,'updated_at'=>$now), array('id'=>$campaign_id));
    if ( $c && $c->order_id ) {
        $wpdb->update("{$p}customer_orders", array('status'=>'rejected','reject_reason'=>$reason,'updated_at'=>$now), array('id'=>$c->order_id));
    }
    return true;
}

function sitetop_pause_campaign( $campaign_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $c = sitetop_get_campaign( $campaign_id );
    $result = sitetop_update_campaign( $campaign_id, array( 'status' => 'paused' ) );
    if ( $result ) {
        if ( $c && $c->order_id ) {
            $wpdb->update("{$p}customer_orders", array('status'=>'paused','updated_at'=>sitetop_current_time()), array('id'=>$c->order_id));
        }
        delete_transient( 'sitetop_eligible_campaigns' );
    }
    return $result;
}

function sitetop_resume_campaign( $campaign_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    // Check customer balance before resuming
    $c = sitetop_get_campaign( $campaign_id );
    if ( $c && $c->customer_id ) {
        $bal = sitetop_get_customer_balance_amount( $c->customer_id );
        $min = (int) sitetop_get_option( 'customer_min_balance', 20000 );
        $required = $min + max( (float) ($c->price_per_view ?? 0), 5000 );
        if ( $bal !== false && $bal <= $required ) {
            return new WP_Error( 'insufficient', 'Số dư không đủ để resume' );
        }
    }
    $result = sitetop_update_campaign( $campaign_id, array( 'status' => 'active' ) );
    if ( $result ) {
        if ( $c && $c->order_id ) {
            $wpdb->update("{$p}customer_orders", array('status'=>'active','updated_at'=>sitetop_current_time()), array('id'=>$c->order_id));
        }
        delete_transient( 'sitetop_eligible_campaigns' );
    }
    return $result;
}
