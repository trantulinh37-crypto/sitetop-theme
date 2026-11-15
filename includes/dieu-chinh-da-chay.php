<?php
/**
 * ADMIN SỬA "ĐÃ CHẠY" CỦA CAMP — KHÁCH ĐỒNG BỘ THEO PHẦN CHÊNH (15/11/2026, chủ site).
 *
 * Yêu cầu: admin tăng/giảm cột ĐÃ CHẠY; tài khoản khách tự đồng bộ theo phần chênh lệch:
 *   Tổng Views ± Δ, Số dư quảng cáo ∓ tiền, Đã chi ± tiền. KHÔNG sửa lịch sử của khách.
 *
 * Cách ghi (mỗi lần sửa = MỘT giao dịch CSDL, khoá dòng camp + dòng số dư):
 *   Δ    = số admin nhập − số admin ĐANG THẤY lúc mở ô sửa (không lấy số trong CSDL: camp đang chạy thì
 *          lượt thật vẫn đổ vào; tính theo phần chênh là giữ nguyên các lượt thật đó, không ghi đè).
 *   tiền = Δ × giá/view hiện tại của camp (VNĐ, làm tròn đồng).
 *   1. keyword_campaigns.completed += Δ                      (cột ĐÃ CHẠY của admin)
 *   2. customer_balance: balance −= tiền, total_spent += tiền (bảng số dư lưu sẵn — chốt trừ tiền đọc bảng này)
 *   3. customer_transactions: 1 dòng type='campaign_view', amount = −tiền,
 *      reference_type='dieu_chinh_chay', reference_id = camp  (sổ đối soát: số dư + Đã chi tính từ sổ này)
 *   4. customer_orders.completed/amount_spent ± (giữ đơn khớp như luồng trừ tiền thật)
 *   5. option 'sitetop_dc_chay_<camp>' += Δ  → cộng vào Tổng Views và "Đã chạy" từng camp phía khách
 *      (hai số đó đếm từ shortlink_visits, không đọc cột completed).
 *   Dòng điều chỉnh bị ẨN khỏi danh sách giao dịch của khách → lịch sử khách không đổi.
 *
 * Chống cộng/trừ trùng: mỗi lần bấm Lưu có mã 'req' riêng; mã đã xử lý thì trả lại kết quả cũ (gửi lại 2 lần
 * vẫn chỉ tính 1). Giảm (Δ<0) là hoàn tiền: dòng campaign_view mang số DƯƠNG — các công thức "đã chi" vì thế tính
 * theo TỔNG RÒNG (GREATEST(0, −SUM(amount))) thay cho "chỉ cộng dòng âm" (14/11 production: 0 dòng dương → số cũ y hệt).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Phần lượt admin đã điều chỉnh của nhiều camp, trong MỘT câu truy vấn: [camp_id => Δ ròng]. */
function sitetop_dc_chay_cua_camp_nhieu( $ids ) {
    $ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
    if ( ! $ids ) return array();
    global $wpdb;
    $ten = array();
    foreach ( $ids as $i ) $ten[] = $wpdb->prepare( '%s', 'sitetop_dc_chay_' . $i );
    $out = array();
    foreach ( (array) $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN (" . implode( ',', $ten ) . ')' ) as $r ) {
        $out[ (int) substr( $r->option_name, strlen( 'sitetop_dc_chay_' ) ) ] = (int) $r->option_value;
    }
    return $out;
}

/** Tổng lượt admin đã điều chỉnh trên MỌI camp của một khách (kể cả camp đã xoá — Tổng Views cũng đếm cả camp đã xoá). */
function sitetop_dc_chay_cua_khach( $customer_id ) {
    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    $ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$p}keyword_campaigns WHERE customer_id = %d", (int) $customer_id ) );
    return (int) array_sum( sitetop_dc_chay_cua_camp_nhieu( $ids ) );
}

/* VIEW HÔM NAY (15/11/2026, chủ site: sửa số vàng "đang chạy hôm nay" chứ không phải số max/ngày). Phần chênh hôm nay
   lưu ở option 'sitetop_dc_hn_<camp>_<Ymd>' (autoload no) và được CỘNG vào mọi chỗ đếm view hôm nay: ô số vàng admin,
   chốt hạn mức ngày lúc trả tiền, vòng chọn camp, chia đều theo giờ, số "hôm nay" phía khách, số liệu gửi .one. */
function sitetop_dc_hn_ngay() { return date( 'Y-m-d', strtotime( sitetop_current_time() ) ); }
function sitetop_dc_hn_khoa( $camp_id, $ngay = null ) { return 'sitetop_dc_hn_' . (int) $camp_id . '_' . str_replace( '-', '', $ngay ?: sitetop_dc_hn_ngay() ); }
function sitetop_dc_hn_cua_camp_nhieu( $ids, $ngay = null ) {
    $ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
    if ( ! $ids ) return array();
    global $wpdb; $ten = array(); $map = array();
    foreach ( $ids as $i ) { $k = sitetop_dc_hn_khoa( $i, $ngay ); $map[ $k ] = $i; $ten[] = $wpdb->prepare( '%s', $k ); }
    $out = array();
    foreach ( (array) $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN (" . implode( ',', $ten ) . ')' ) as $r ) {
        if ( isset( $map[ $r->option_name ] ) ) $out[ $map[ $r->option_name ] ] = (int) $r->option_value;
    }
    return $out;
}
function sitetop_dc_hn_cua_camp( $camp_id, $ngay = null ) {
    $a = sitetop_dc_hn_cua_camp_nhieu( array( $camp_id ), $ngay );
    return (int) ( $a[ (int) $camp_id ] ?? 0 );
}
function sitetop_dc_hn_cua_khach( $customer_id, $ngay = null ) {
    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    $ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$p}keyword_campaigns WHERE customer_id = %d", (int) $customer_id ) );
    return (int) array_sum( sitetop_dc_hn_cua_camp_nhieu( $ids, $ngay ) );
}
/* BIỂU ĐỒ 30 NGÀY phía khách (15/11/2026, chủ site: "phải báo đúng số view/ngày sau khi tăng giảm"). Mọi lần sửa (Đã chạy
   hoặc view hôm nay) ghi phần chênh vào option 'sitetop_dc_bd_<camp>_<Ymd>' theo NGÀY SỬA — cùng ngày với dòng tiền điều chỉnh
   (đường Đã chi), nên hai đường của biểu đồ khớp nhau. Trả [Y-m-d => Δ lượt] cho mọi camp của khách. */
function sitetop_dc_bd_cua_khach( $customer_id ) {
    global $wpdb; $p = $wpdb->prefix . 'sitetop_';
    $ids = array_flip( array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$p}keyword_campaigns WHERE customer_id = %d", (int) $customer_id ) ) ) );
    if ( ! $ids ) return array();
    $out = array();
    foreach ( (array) $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'sitetop\\_dc\\_bd\\_%'" ) as $r ) {
        if ( ! preg_match( '/^sitetop_dc_bd_(\d+)_(\d{4})(\d{2})(\d{2})$/', $r->option_name, $m ) || ! isset( $ids[ (int) $m[1] ] ) ) continue;
        $d = $m[2] . '-' . $m[3] . '-' . $m[4];
        $out[ $d ] = ( $out[ $d ] ?? 0 ) + (int) $r->option_value;
    }
    return $out;
}

/** Cộng phần view-hôm-nay đã điều chỉnh vào today_views của các dòng camp. */
function sitetop_dc_hn_cong_vao_camp( $rows, $ngay = null ) {
    $dc = sitetop_dc_hn_cua_camp_nhieu( wp_list_pluck( (array) $rows, 'id' ), $ngay );
    foreach ( (array) $rows as $c ) if ( isset( $dc[ (int) $c->id ] ) ) $c->today_views = max( 0, (int) ( $c->today_views ?? 0 ) + $dc[ (int) $c->id ] );
}

/** Cộng phần lượt admin đã điều chỉnh vào total_completed của các dòng camp (danh sách camp phía khách). */
function sitetop_dc_chay_cong_vao_camp( $rows ) {
    $dc = sitetop_dc_chay_cua_camp_nhieu( wp_list_pluck( (array) $rows, 'id' ) );
    if ( ! $dc ) return;
    foreach ( (array) $rows as $c ) {
        if ( isset( $dc[ (int) $c->id ] ) ) $c->total_completed = max( 0, (int) ( $c->total_completed ?? 0 ) + $dc[ (int) $c->id ] );
    }
}

/** Camp gương (.one nhận từ .net): ĐÃ CHẠY lấy theo số liệu .net — không cho sửa ở đây. */
function sitetop_dc_chay_la_camp_guong( $camp ) {
    return function_exists( 'sitetop_cn_la_pool' ) && sitetop_cn_la_pool()
        && function_exists( 'sitetop_cn_la_camp_nguon' ) && sitetop_cn_la_camp_nguon( $camp );
}

add_action( 'wp_ajax_sitetop_admin_sua_da_chay', function () {
    check_ajax_referer( 'sitetop_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Không có quyền' );
    global $wpdb; $p = $wpdb->prefix . 'sitetop_';

    $id  = absint( $_POST['campaign_id'] ?? 0 );
    $moi = isset( $_POST['moi'] ) ? (int) $_POST['moi'] : -1;
    $cu  = isset( $_POST['cu'] ) ? (int) $_POST['cu'] : -1;
    $hom_nay = ! empty( $_POST['hom_nay'] );   // sửa số VIEW HÔM NAY (số vàng): cũng đồng bộ Đã chạy + tiền khách
    $req = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $_POST['req'] ?? '' ) );
    if ( ! $id || $moi < 0 || $cu < 0 || strlen( $req ) < 8 ) wp_send_json_error( 'Thiếu thông tin' );

    // Gửi lại cùng một lần bấm (mạng chập chờn, bấm 2 lần) → trả kết quả cũ, KHÔNG tính lại.
    $khoa_req = 'sitetop_dc_req_' . md5( $req );
    $da_xu_ly = get_transient( $khoa_req );
    if ( is_array( $da_xu_ly ) ) wp_send_json_success( $da_xu_ly + array( 'gui_lai' => 1 ) );

    $delta = $moi - $cu;
    if ( $delta === 0 ) wp_send_json_success( array( 'doi' => 0 ) );

    $wpdb->query( 'START TRANSACTION' );
    $c = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$p}keyword_campaigns WHERE id = %d FOR UPDATE", $id ) );
    if ( ! $c ) { $wpdb->query( 'ROLLBACK' ); wp_send_json_error( 'Không tìm thấy chiến dịch' ); }
    if ( sitetop_dc_chay_la_camp_guong( $c ) ) {
        $wpdb->query( 'ROLLBACK' );
        wp_send_json_error( 'Camp nhận từ .net — sửa "Đã chạy" ở sitetop.net.' );
    }
    $kh = (int) $c->customer_id;
    if ( $kh <= 0 ) { $wpdb->query( 'ROLLBACK' ); wp_send_json_error( 'Camp chưa gắn khách hàng' ); }

    $truoc = (int) $c->completed;
    $sau   = $truoc + $delta;
    $hn_ngay = sitetop_dc_hn_ngay(); $hn_sau = null;
    if ( $hom_nay ) {
        $kn = array( $hn_ngay . ' 00:00:00', date( 'Y-m-d', strtotime( $hn_ngay . ' +1 day' ) ) . ' 00:00:00' );   // khoảng hôm nay (.one không có sitetop_khoang_ngay)
        $hn_that = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}shortlink_visits WHERE campaign_id = %d AND created_at >= %s AND created_at < %s AND (step='verified' OR customer_paid=1)",
            $id, $kn[0], $kn[1] ) );
        $hn_sau = $hn_that + sitetop_dc_hn_cua_camp( $id, $hn_ngay ) + $delta;
        if ( $hn_sau < 0 ) { $wpdb->query( 'ROLLBACK' ); wp_send_json_error( 'View hôm nay không thể âm' ); }
    }
    if ( $sau < 0 ) { $wpdb->query( 'ROLLBACK' ); wp_send_json_error( 'Đã chạy không thể âm (hiện có ' . number_format( $truoc ) . ')' ); }

    $gia  = (float) $c->price_per_view;
    $tien = (int) round( $delta * $gia );   // dương = trừ khách, âm = hoàn khách

    /* Giảm = hoàn tiền: không hoàn quá số tiền CAMP NÀY đã thực trừ của khách (tiền các lượt thật + các lần điều chỉnh
       trước). Vượt quá là hai sổ lệch nhau: bảng số dư được cộng, còn công thức tính từ sổ (GREATEST(0, …)) thì không. */
    if ( $tien < 0 ) {
        $da_tru = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT -COALESCE(SUM(t.amount),0) FROM {$p}customer_transactions t
              WHERE t.customer_id = %d AND t.type = 'campaign_view' AND (
                    ( t.reference_type = 'dieu_chinh_chay' AND t.reference_id = %d )
                 OR ( t.reference_type = 'visit' AND t.reference_id IN ( SELECT v.id FROM {$p}shortlink_visits v WHERE v.campaign_id = %d ) ) )",
            $kh, $id, $id ) );
        if ( -$tien > $da_tru + 0.5 ) {
            $wpdb->query( 'ROLLBACK' );
            wp_send_json_error( 'Camp này mới thực trừ khách ' . number_format( max( 0, $da_tru ), 0, ',', '.' ) . 'đ — không hoàn được '
                . number_format( -$tien, 0, ',', '.' ) . 'đ. Giảm ít hơn (tối đa ' . number_format( floor( max( 0, $da_tru ) / max( 1, $gia ) ) ) . ' lượt).' );
        }
    }

    $cb = $wpdb->get_row( $wpdb->prepare( "SELECT id, balance FROM {$p}customer_balance WHERE user_id = %d FOR UPDATE", $kh ) );
    if ( ! $cb ) {
        if ( function_exists( 'sitetop_sync_customer_balance' ) ) sitetop_sync_customer_balance( $kh );
        $cb = $wpdb->get_row( $wpdb->prepare( "SELECT id, balance FROM {$p}customer_balance WHERE user_id = %d FOR UPDATE", $kh ) );
    }
    if ( ! $cb ) { $wpdb->query( 'ROLLBACK' ); wp_send_json_error( 'Không đọc được số dư khách' ); }
    $so_du = (float) $cb->balance;
    if ( $tien > 0 && $so_du < $tien ) {
        $wpdb->query( 'ROLLBACK' );
        wp_send_json_error( 'Số dư khách không đủ: còn ' . number_format( $so_du, 0, ',', '.' ) . 'đ, cần ' . number_format( $tien, 0, ',', '.' ) . 'đ.' );
    }
    $now = sitetop_current_time();
    $ok  = true;

    $ok = $ok && false !== $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}keyword_campaigns SET completed = completed + %d WHERE id = %d", $delta, $id ) );
    $ok = $ok && false !== $wpdb->query( $wpdb->prepare(
        "UPDATE {$p}customer_balance SET balance = balance - %d, total_spent = total_spent + %d, updated_at = %s WHERE user_id = %d",
        $tien, $tien, $now, $kh ) );
    $ok = $ok && false !== $wpdb->insert( "{$p}customer_transactions", array(
        'customer_id'    => $kh,
        'type'           => 'campaign_view',
        'amount'         => -$tien,
        'reference_id'   => $id,
        'reference_type' => 'dieu_chinh_chay',
        'description'    => sprintf( 'Admin điều chỉnh ' . ( $hom_nay ? 'view hôm nay' : 'Đã chạy' ) . ' camp #%d: %s → %s (%+d lượt × %s)', $id,
            number_format( $truoc ), number_format( $sau ), $delta, number_format( $gia, 0, ',', '.' ) ),
        'balance_after'  => $so_du - $tien,
        'created_at'     => $now,
    ) );
    if ( $ok && ! empty( $c->order_id ) ) {
        $ok = false !== $wpdb->query( $wpdb->prepare(
            "UPDATE {$p}customer_orders SET completed = GREATEST(0, CAST(completed AS SIGNED) + %d), amount_spent = amount_spent + %d, updated_at = %s WHERE id = %d",
            $delta, $tien, $now, (int) $c->order_id ) );
    }
    if ( $ok ) {
        $khoa = 'sitetop_dc_chay_' . $id;
        // Đọc thẳng CSDL (không qua cache) — đang trong giao dịch, số này phải là số thật.
        $cu_dc = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE", $khoa ) );
        if ( $cu_dc === null ) $ok = add_option( $khoa, $delta, '', 'no' );
        else $ok = update_option( $khoa, (int) $cu_dc + $delta, false );   // Δ ≠ 0 nên giá trị luôn đổi → true
    }
    if ( $ok && $hom_nay ) {
        $khoa_hn = sitetop_dc_hn_khoa( $id, $hn_ngay );
        $cu_hn = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE", $khoa_hn ) );
        if ( $cu_hn === null ) $ok = add_option( $khoa_hn, $delta, '', 'no' );
        else $ok = update_option( $khoa_hn, (int) $cu_hn + $delta, false );
    }
    if ( $ok ) {   // biểu đồ 30 ngày: phần chênh theo ngày sửa (mọi lần sửa)
        $khoa_bd = 'sitetop_dc_bd_' . $id . '_' . str_replace( '-', '', $hn_ngay );
        $cu_bd = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE", $khoa_bd ) );
        if ( $cu_bd === null ) $ok = add_option( $khoa_bd, $delta, '', 'no' );
        else $ok = update_option( $khoa_bd, (int) $cu_bd + $delta, false );
    }
    if ( ! $ok ) {
        $wpdb->query( 'ROLLBACK' );
        wp_cache_delete( 'sitetop_dc_chay_' . $id, 'options' );
        wp_send_json_error( 'Lưu không thành công, chưa có gì thay đổi. Thử lại.' );
    }
    $wpdb->query( 'COMMIT' );

    delete_transient( 'sitetop_eligible_campaigns' );   // số dư đổi → danh sách camp đủ tiền tính lại
    delete_transient( 'sitetop_sl_kh_bd_' . $kh . '_' . $hn_ngay );   // bộ đệm 5 phút biểu đồ 30 ngày của khách → vẽ lại ngay
    if ( $tien > 0 && ( $so_du - $tien ) <= 0 && function_exists( 'sitetop_auto_pause_customer_campaigns' ) ) {
        sitetop_auto_pause_customer_campaigns( $kh );   // giống luồng trừ tiền thật: hết tiền thì tạm dừng
    }

    $kq = array(
        'doi' => 1, 'completed' => $sau, 'delta' => $delta, 'tien' => $tien, 'hom_nay' => $hn_sau,
        'so_du' => $so_du - $tien,
        'da_chi_camp' => sitetop_format_money( $sau * $gia ),
    );
    set_transient( $khoa_req, $kq, 30 * MINUTE_IN_SECONDS );
    wp_send_json_success( $kq );
} );
