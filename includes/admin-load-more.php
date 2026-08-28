<?php
/**
 * AJAX: User Dashboard Load More (links, transactions, withdrawals)
 * Tách từ functions.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Một mục lịch sử rút tiền (dạng thẻ).
 * Dùng chung cho page-user-dashboard.php và AJAX "Xem thêm" để 2 nơi không lệch markup.
 */
function sitetop_render_withdrawal_item( $w ) {
    $cls = array( 'pending' => 'b-warn', 'approved' => 'b-info', 'completed' => 'b-ok', 'rejected' => 'b-err', 'refunded' => 'b-err', 'cancelled' => 'b-mute' );
    $vn  = array( 'pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'completed' => 'Hoàn thành', 'rejected' => 'Từ chối', 'refunded' => 'Hoàn tiền', 'cancelled' => 'Đã huỷ' );

    $is_usdt = $w->payment_method === 'usdt';
    if ( $is_usdt ) {
        $dest   = $w->wallet_address ?? '';
        $holder = '';
    } else {
        $parts  = array_filter( array( $w->bank_name ?? '', $w->bank_account ?? '' ) );
        $dest   = implode( ' · ', $parts );
        $holder = $w->bank_holder ?? '';
    }

    $h  = '<div class="wdi wdi-' . esc_attr( $w->status ) . '">';
    $h .= '<div class="wdi-top">';
    $h .= '<span class="wdi-amount">' . sitetop_format_money( $w->amount ) . '</span>';
    $h .= '<span class="badge ' . ( $cls[ $w->status ] ?? 'b-mute' ) . '">' . ( $vn[ $w->status ] ?? esc_html( $w->status ) ) . '</span>';
    $h .= '</div>';
    $h .= '<div class="wdi-meta">';
    if ( ( $w->source ?? 'task' ) === 'referral' ) {
        $h .= '<span class="wdi-tag" style="color:var(--ok)">Hoa hồng referral</span>';
    }
    $h .= '<span class="wdi-tag">' . ( $is_usdt ? 'USDT-BEP20' : 'Ngân hàng' ) . '</span>';
    if ( $dest !== '' )   $h .= '<span>' . esc_html( $dest ) . '</span>';
    if ( $holder !== '' ) $h .= '<span>' . esc_html( $holder ) . '</span>';
    $h .= '</div>';
    $h .= '<div class="wdi-foot">' . esc_html( date( 'H:i · d/m/Y', strtotime( $w->created_at ) ) ) . '</div>';
    if ( ! empty( $w->admin_note ) ) {
        $h .= '<div class="wdi-note">' . esc_html( $w->admin_note ) . '</div>';
    }
    $h .= '</div>';

    return $h;
}

add_action( 'wp_ajax_sitetop_load_more', 'sitetop_ajax_load_more' );
function sitetop_ajax_load_more() {
    check_ajax_referer( 'sitetop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Unauthorized' );
    sitetop_block_advertiser_ajax(); // tài khoản quảng cáo không dùng khu publisher

    $user_id = get_current_user_id();
    $type    = sanitize_text_field( $_POST['type'] ?? '' );
    $offset  = absint( $_POST['offset'] ?? 0 );
    $limit   = 10;
    $today   = date( 'Y-m-d', strtotime( sitetop_current_time() ) );
    $home    = home_url();

    global $wpdb;
    $prefix = $wpdb->prefix . 'sitetop_';

    $html = '';
    $has_more = false;

    if ( $type === 'links' ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT us.*,
                    us.code as shortcode,
                    us.original_url as target_url,
                    us.total_clicks as click_count,
                    (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE shortlink_id=us.id AND (step='verified' OR customer_paid=1) AND DATE(created_at)=%s) as today_clicks
             FROM {$prefix}user_shortlinks us
             WHERE us.user_id = %d
             ORDER BY us.created_at DESC
             LIMIT %d OFFSET %d",
            $today, $user_id, $limit, $offset
        ) );
        foreach ( $rows as $lk ) {
            $short = $home . '/' . ( ! empty( $lk->alias ) ? $lk->alias : $lk->shortcode );
            $bcls = $lk->status === 'active' ? 'b-ok' : ( $lk->status === 'paused' ? 'b-warn' : 'b-mute' );
            $completed = isset( $lk->total_completed ) ? (int) $lk->total_completed : 0;
            $earnings = isset( $lk->total_earnings ) ? (float) $lk->total_earnings : 0;
            $html .= '<div class="link-card" onclick="copyText(\'' . esc_js( $short ) . '\',this)" style="background:var(--bg);border-radius:var(--rads);padding:14px;cursor:pointer;transition:all .15s;border:1.5px solid transparent" onmouseover="this.style.borderColor=\'var(--p)\'" onmouseout="this.style.borderColor=\'transparent\'">';
            $html .= '<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:6px">';
            $html .= '<div style="font-family:var(--mono);font-size:13px;color:var(--info);font-weight:600;white-space:nowrap">' . esc_html( $short ) . '</div>';
            $html .= '<span class="badge ' . $bcls . '" style="flex-shrink:0">' . esc_html( $lk->status ) . '</span></div>';
            $html .= '<div style="font-size:11px;color:var(--txtm);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:8px" title="' . esc_attr( $lk->target_url ) . '">' . esc_html( $lk->target_url ) . '</div>';
            $html .= '<div style="display:flex;gap:12px;font-size:11px;color:var(--txtl);flex-wrap:wrap">';
            $html .= '<span><strong style="color:var(--pd)">' . number_format( $lk->click_count ) . '</strong> clicks</span>';
            $html .= '<span><strong style="color:var(--ok)">' . $completed . '</strong> hoàn thành</span>';
            $html .= '<span><strong style="color:var(--a)">' . sitetop_format_money( $earnings ) . '</strong> kiếm được</span>';
            $html .= '<span>' . date( 'd/m/Y', strtotime( $lk->created_at ) ) . '</span></div>';
            $html .= '<div class="link-copied-msg" style="display:none;font-size:11px;color:var(--ok);margin-top:4px;font-weight:600">Đã copy!</div></div>';
        }
        $has_more = count( $rows ) >= $limit;

    } elseif ( $type === 'transactions' ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$prefix}transactions WHERE user_id=%d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ) );
        foreach ( $rows as $tx ) {
            $cls = $tx->amount >= 0 ? 'amt-plus' : 'amt-minus';
            $sign = $tx->amount >= 0 ? '+' : '';
            $html .= '<tr>';
            $html .= '<td><small>' . esc_html( $tx->created_at ) . '</small></td>';
            $html .= '<td>' . esc_html( $tx->description ) . '</td>';
            $html .= '<td class="' . $cls . '">' . $sign . sitetop_format_money( $tx->amount ) . '</td>';
            $html .= '<td>' . sitetop_format_money( $tx->balance_after ) . '</td>';
            $html .= '</tr>';
        }
        $has_more = count( $rows ) >= $limit;

    } elseif ( $type === 'withdrawals' ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$prefix}withdrawals WHERE user_id=%d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ) );
        foreach ( $rows as $w ) {
            $html .= sitetop_render_withdrawal_item( $w );
        }
        $has_more = count( $rows ) >= $limit;

    } else {
        wp_send_json_error( 'Invalid type' );
    }

    wp_send_json_success( array( 'html' => $html, 'has_more' => $has_more ) );
}
