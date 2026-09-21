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

/**
 * Xoá MỀM: chuyển chiến dịch (và đơn hàng đi kèm) sang trạng thái 'deleted', giữ nguyên dữ
 * liệu để đối soát. Dùng chung cho nút Xoá từng camp VÀ thao tác xoá hàng loạt — một đường
 * duy nhất, hai nút không thể lệch nhau. Không xoá transient danh sách camp: bên gọi xoá
 * một lần sau cả loạt, khỏi làm lại hàng chục lần.
 */
function sitetop_xoa_mem_campaign( $campaign_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, order_id, status FROM {$p}keyword_campaigns WHERE id = %d", (int) $campaign_id ) );
    if ( ! $row ) return new WP_Error( 'not_found', 'Không tìm thấy chiến dịch.' );
    $now = sitetop_current_time();
    $wpdb->update( $p . 'keyword_campaigns', array( 'status' => 'deleted', 'updated_at' => $now ), array( 'id' => $row->id ) );
    if ( $row->order_id ) {
        $wpdb->update( $p . 'customer_orders', array( 'status' => 'deleted', 'updated_at' => $now ), array( 'id' => $row->order_id ) );
    }
    return true;
}

/**
 * Xoá VĨNH VIỄN — không hoàn tác được. Chỉ nhận camp ĐÃ xoá mềm (trạng thái 'deleted'),
 * tránh một phát bấm nhầm mất luôn camp đang chạy.
 * Chỉ động vào keyword_campaigns + customer_orders. CỐ Ý KHÔNG đụng:
 *   - customer_transactions: số dư khách được tính LIVE bằng cách cộng bảng này, xoá đi là
 *     số dư tự nhảy, tiền đã trừ bỗng dưng được hoàn.
 *   - shortlink_visits / shortlink_reports / hourly_adjustments: bằng chứng ai đã làm nhiệm
 *     vụ nào, cần khi khách khiếu nại "trả tiền mà không có traffic".
 */
function sitetop_xoa_vinh_vien_campaign( $campaign_id ) {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $row = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, order_id, status FROM {$p}keyword_campaigns WHERE id = %d", (int) $campaign_id ) );
    if ( ! $row ) return new WP_Error( 'not_found', 'Không tìm thấy chiến dịch.' );
    if ( $row->status !== 'deleted' ) {
        return new WP_Error( 'not_deleted', 'Chỉ xoá vĩnh viễn được chiến dịch đang ở trạng thái Đã xóa. Hãy xóa mềm trước.' );
    }
    $wpdb->delete( $p . 'keyword_campaigns', array( 'id' => (int) $row->id ) );
    if ( (int) $row->order_id ) $wpdb->delete( $p . 'customer_orders', array( 'id' => (int) $row->order_id ) );
    return true;
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
