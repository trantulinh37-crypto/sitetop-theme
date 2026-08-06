<?php
/**
 * AJAX: Customer Dashboard Load More (campaigns, visits, transactions, deposits)
 * Tách từ functions.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_sitetop_customer_load_more', 'sitetop_ajax_customer_load_more' );
function sitetop_ajax_customer_load_more() {
    check_ajax_referer( 'sitetop_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Unauthorized' );
    sitetop_require_customer_role();

    $user_id = get_current_user_id();
    $type    = sanitize_text_field( $_POST['type'] ?? '' );
    $offset  = absint( $_POST['offset'] ?? 0 );
    $limit   = 10;
    $today   = date( 'Y-m-d', strtotime( sitetop_current_time() ) );

    global $wpdb;
    $prefix = $wpdb->prefix . 'sitetop_';

    $html = '';
    $has_more = false;

    if ( $type === 'campaigns' ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT kc.*, co.task_type,
                    (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND step='verified') as total_completed,
                    (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND step='verified' AND DATE(created_at)=%s) as today_views
             FROM {$prefix}keyword_campaigns kc
             LEFT JOIN {$prefix}customer_orders co ON kc.order_id = co.id
             WHERE kc.customer_id = %d
             ORDER BY kc.created_at DESC LIMIT %d OFFSET %d",
            $today, $user_id, $limit, $offset
        ) );
        $task_icons = array( 'keyword_search' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>', 'traffic_direct' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>', 'traffic_social' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>' );
        $task_labels = array( 'keyword_search' => 'Keyword', 'traffic_direct' => 'Direct', 'traffic_social' => 'Social' );
        $task_colors = array( 'keyword_search' => 'b-info', 'traffic_direct' => 'b-warn', 'traffic_social' => 'b-mute' );
        $step_labels = array( '1step' => '1 bước', '2step' => '2 bước', 'nocode' => 'Mã cố định' );
        $status_labels = array( 'active' => 'Đang chạy', 'paused' => 'Tạm dừng', 'pending' => 'Chờ duyệt', 'rejected' => 'Từ chối' );
        $status_colors = array( 'active' => 'b-ok', 'paused' => 'b-warn', 'pending' => 'b-info', 'rejected' => 'b-err' );

        foreach ( $rows as $c ) {
            $domain = parse_url( $c->target_url ?? '', PHP_URL_HOST );
            $tt = $c->task_type ?? 'keyword_search';
            $spent = $c->total_completed * ( $c->price_per_view ?? 0 );
            $html .= '<tr>';
            $html .= '<td><div style="display:flex;align-items:flex-start;gap:8px"><span style="color:var(--info);margin-top:2px">' . ( $task_icons[ $tt ] ?? '' ) . '</span><div><div style="font-weight:600;font-size:13px">' . esc_html( $c->keyword ?: $c->title ) . '</div>';
            if ( $domain ) $html .= '<div style="font-size:11px;color:var(--txtm)">' . esc_html( $domain ) . '</div>';
            $html .= '</div></div></td>';
            $html .= '<td><span class="badge ' . ( $task_colors[ $tt ] ?? 'b-mute' ) . '">' . ( $task_labels[ $tt ] ?? $tt ) . '</span></td>';
            $html .= '<td><div style="font-weight:600;font-size:12px">' . ( $step_labels[ $c->traffic_type ] ?? $c->traffic_type ) . '</div><div style="font-size:10px;color:var(--txtm)">' . (int) $c->onsite_time . 's</div>';
            if ( $c->traffic_type === 'nocode' && ! empty( $c->fixed_code ) ) $html .= '<div style="font-size:10px;color:var(--a);font-weight:600;margin-top:2px">' . esc_html( $c->fixed_code ) . '</div>';
            $html .= '</td>';
            $html .= '<td style="font-weight:600;color:var(--a)">' . sitetop_format_money( $c->price_per_view ?? 0 ) . '</td>';
            $html .= '<td><div style="font-size:12px">' . (int) $c->daily_traffic . '/ngày</div></td>';
            $html .= '<td><div style="font-weight:600;font-size:12px">' . number_format( (int) $c->total_completed ) . '</div>';
            $html .= '<div style="font-size:10px;color:var(--txtm);margin-top:2px">' . sitetop_format_money( $spent ) . '</div></td>';
            $html .= '<td><span class="badge ' . ( $status_colors[ $c->status ] ?? 'b-mute' ) . '">' . ( $status_labels[ $c->status ] ?? $c->status ) . '</span></td>';
            $html .= '<td><small>' . date( 'd/m/Y', strtotime( $c->created_at ) ) . '</small></td>';
            $html .= '</tr>';
        }
        $has_more = count( $rows ) >= $limit;

    } elseif ( $type === 'visits' ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT v.created_at, v.verified_at, v.step, v.ip_address, v.user_agent, v.reward_paid, v.customer_paid,
                    v.reward_amount, v.from_google, v.url_matched,
                    kc.title as campaign_title, kc.keyword, kc.target_url, kc.traffic_type, kc.onsite_time, kc.price_per_view,
                    co.task_type
             FROM {$prefix}shortlink_visits v
             INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id = kc.id
             LEFT JOIN {$prefix}customer_orders co ON kc.order_id = co.id
             WHERE kc.customer_id = %d AND v.step = 'verified'
             ORDER BY v.created_at DESC LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ) );
        $task_label = array( 'keyword_search' => 'Từ khóa', 'traffic_direct' => 'Direct', 'traffic_social' => 'Social' );
        $step_map = array( '1step' => '1 bước', '2step' => '2 bước', 'nocode' => 'Mã cố định' );

        foreach ( $rows as $vh ) {
            $domain = parse_url( $vh->target_url, PHP_URL_HOST );
            $ua = $vh->user_agent ?? '';
            $device = 'Unknown';
            if ( stripos( $ua, 'Android' ) !== false ) {
                preg_match( '/Android\s*([\d.]+)/', $ua, $am );
                $device = 'Android' . ( isset( $am[1] ) ? " ({$am[1]})" : '' );
            } elseif ( stripos( $ua, 'iPhone' ) !== false ) {
                $device = 'iPhone';
            } elseif ( stripos( $ua, 'Windows' ) !== false ) {
                $device = stripos( $ua, 'Windows NT 10' ) !== false ? 'Win10/11' : 'Windows';
                if ( stripos( $ua, 'Chrome' ) !== false ) $device .= ' Chrome';
                elseif ( stripos( $ua, 'Firefox' ) !== false ) $device .= ' Firefox';
            } elseif ( stripos( $ua, 'Mac' ) !== false ) {
                $device = 'macOS';
                if ( stripos( $ua, 'Chrome' ) !== false ) $device .= ' Chrome';
                elseif ( stripos( $ua, 'Safari' ) !== false ) $device .= ' Safari';
            }
            $cost = $vh->price_per_view ?? 0;

            $html .= '<tr>';
            $html .= '<td><small>' . date( 'd/m/Y', strtotime( $vh->created_at ) ) . '<br>' . date( 'H:i:s', strtotime( $vh->created_at ) ) . '</small></td>';
            $html .= '<td style="white-space:nowrap">';
            if ( $vh->keyword ) {
                $html .= '<div style="font-weight:600;font-size:12px">' . esc_html( $vh->keyword ) . '</div>';
            } else {
                $html .= '<div style="font-weight:600;font-size:12px">' . esc_html( $vh->campaign_title ) . '</div>';
            }
            if ( $domain ) $html .= '<div style="font-size:11px;color:var(--txtm)">' . esc_html( $domain ) . '</div>';
            $html .= '</td>';
            $html .= '<td style="white-space:nowrap"><span class="badge b-info">' . ( $task_label[ $vh->task_type ?? '' ] ?? 'Traffic' ) . '</span>';
            $html .= '<div style="font-size:10px;color:var(--txtm);margin-top:2px">' . ( $step_map[ $vh->traffic_type ] ?? $vh->traffic_type ) . ' / ' . (int) $vh->onsite_time . 's</div></td>';
            $html .= '<td style="white-space:nowrap;font-weight:600;color:var(--err)">-' . sitetop_format_money( $cost ) . '</td>';
            $html .= '<td style="white-space:nowrap">';
            if ( $vh->customer_paid ) {
                $html .= '<span class="badge b-ok">Hoàn thành</span>';
            } else {
                $html .= '<span class="badge b-warn">Không tính phí</span>';
            }
            $html .= '</td>';
            $html .= '<td><small style="font-family:var(--mono);font-size:10px">' . esc_html( $vh->ip_address ) . '</small></td>';
            $html .= '<td><small style="font-size:11px">' . esc_html( $device ) . '</small></td>';
            $html .= '</tr>';
        }
        $has_more = count( $rows ) >= $limit;

    } elseif ( $type === 'transactions' ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$prefix}customer_transactions WHERE customer_id=%d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ) );
        $tl = array( 'deposit' => 'Nạp tiền', 'campaign_view' => 'Chi phí view', 'refund' => 'Hoàn tiền', 'bonus' => 'Thưởng', 'deduction' => 'Trừ tiền' );
        $tb = array( 'deposit' => 'b-ok', 'campaign_view' => 'b-err', 'refund' => 'b-info', 'bonus' => 'b-ok', 'deduction' => 'b-warn' );

        foreach ( $rows as $tx ) {
            $color = $tx->amount >= 0 ? 'var(--ok)' : 'var(--err)';
            $sign = $tx->amount >= 0 ? '+' : '';
            $html .= '<tr>';
            $html .= '<td><small>' . date( 'd/m/Y H:i', strtotime( $tx->created_at ) ) . '</small></td>';
            $html .= '<td><span class="badge ' . ( $tb[ $tx->type ] ?? 'b-mute' ) . '">' . ( $tl[ $tx->type ] ?? $tx->type ) . '</span></td>';
            $html .= '<td style="font-size:12px">' . esc_html( $tx->description ) . '</td>';
            $html .= '<td style="font-weight:600;color:' . $color . '">' . $sign . sitetop_format_money( $tx->amount ) . '</td>';
            $html .= '<td style="font-size:12px">' . sitetop_format_money( $tx->balance_after ) . '</td>';
            $html .= '</tr>';
        }
        $has_more = count( $rows ) >= $limit;

    } elseif ( $type === 'deposits' ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$prefix}customer_deposits WHERE customer_id=%d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ) );
        $bc = array( 'pending' => 'b-warn', 'approved' => 'b-ok', 'rejected' => 'b-err' );
        $bl = array( 'pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối' );

        foreach ( $rows as $dep ) {
            $bonus = isset( $dep->bonus_amount ) ? (float) $dep->bonus_amount : 0;
            $total = (float) $dep->amount + $bonus;
            $html .= '<tr>';
            $html .= '<td style="font-size:12px;color:var(--txtm)">#' . $dep->id . '</td>';
            $html .= '<td style="font-weight:600;color:var(--ok)">+' . sitetop_format_money( $dep->amount ) . '</td>';
            $html .= '<td style="font-size:12px">' . ( $bonus > 0 ? '+' . sitetop_format_money( $bonus ) : '—' ) . '</td>';
            $html .= '<td style="font-weight:600">' . sitetop_format_money( $total ) . '</td>';
            $html .= '<td><span class="badge ' . ( $bc[ $dep->status ] ?? 'b-mute' ) . '">' . ( $bl[ $dep->status ] ?? $dep->status ) . '</span></td>';
            $html .= '<td><small>' . date( 'd/m/Y', strtotime( $dep->created_at ) ) . '</small></td>';
            $html .= '</tr>';
        }
        $has_more = count( $rows ) >= $limit;

    } else {
        wp_send_json_error( 'Invalid type' );
    }

    wp_send_json_success( array( 'html' => $html, 'has_more' => $has_more ) );
}
