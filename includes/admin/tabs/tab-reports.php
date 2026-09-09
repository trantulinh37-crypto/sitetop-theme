<?php
/**
 * Báo lỗi của user — trang xem.
 *
 * Bảng shortlink_reports trước 10/09/2026 KHÔNG hề được tạo, nên cổng ghi lặng lẽ bỏ qua
 * và mọi báo lỗi rơi vào hư không; nơi duy nhất còn lại là Telegram. Nay bảng đã có, trang
 * này để tra lại — đặc biệt hữu ích lúc xét duyệt rút tiền: bot không bấm nút báo lỗi, nên
 * một nguồn traffic có người thật báo lỗi là dấu hiệu tốt, còn nguồn hàng nghìn lượt mà
 * tuyệt đối im lặng thì đáng soi.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_sitetop' ) ) wp_die( 'Không đủ quyền' );

global $wpdb;
$p     = $wpdb->prefix . 'sitetop_';
$tbl   = $p . 'shortlink_reports';
$co_bang = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tbl ) );

$tim   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$ngay  = isset( $_GET['ngay'] ) ? absint( $_GET['ngay'] ) : 7;
if ( $ngay < 1 || $ngay > 365 ) $ngay = 7;
$trang = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
$moi   = 50;

$rows = array(); $tong = 0; $theo_user = array();
if ( $co_bang ) {
    /* Nối sang visits để biết báo lỗi thuộc CHỦ SHORTLINK nào và chiến dịch nào —
       chỉ có session_id thì không tra tay nổi. LEFT JOIN để báo lỗi của phiên đã bị dọn
       vẫn hiện, chỉ thiếu phần thông tin kèm. */
    $dk   = array( "r.created_at > DATE_SUB(%s, INTERVAL %d DAY)" );
    $args = array( sitetop_current_time(), $ngay );
    if ( $tim !== '' ) {
        $dk[]   = "( u.user_login LIKE %s OR r.session_id LIKE %s OR r.ip_address LIKE %s OR kc.title LIKE %s )";
        $like   = '%' . $wpdb->esc_like( $tim ) . '%';
        array_push( $args, $like, $like, $like, $like );
    }
    $where = implode( ' AND ', $dk );

    $sql_base = "FROM {$tbl} r
        LEFT JOIN {$p}shortlink_visits v ON v.session_id = r.session_id
        LEFT JOIN {$wpdb->users} u ON u.ID = v.user_id
        LEFT JOIN {$p}keyword_campaigns kc ON kc.id = v.campaign_id
        WHERE $where";

    $tong = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) $sql_base", $args ) );
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT r.*, u.user_login, kc.title AS camp, v.user_agent, v.step $sql_base
         ORDER BY r.id DESC LIMIT %d OFFSET %d",
        array_merge( $args, array( $moi, ( $trang - 1 ) * $moi ) ) ) );
    $theo_user = $wpdb->get_results( $wpdb->prepare(
        "SELECT u.user_login AS ten, COUNT(*) AS n, COUNT(DISTINCT r.ip_address) AS so_ip $sql_base
         GROUP BY u.user_login ORDER BY n DESC LIMIT 15", $args ) );
}
$so_trang = max( 1, (int) ceil( $tong / $moi ) );
?>
<div class="wrap">
<h1>Báo lỗi của user</h1>

<?php if ( ! $co_bang ) : ?>
    <div class="notice notice-warning"><p><b>Bảng chưa được tạo.</b> Tải lại trang quản trị một lần
    để hệ thống tự tạo, hoặc kiểm quyền tạo bảng của tài khoản CSDL.</p></div>
<?php else : ?>

<form method="get" style="margin:12px 0">
    <input type="hidden" name="page" value="sitetop-reports">
    <input type="search" name="s" value="<?php echo esc_attr( $tim ); ?>" placeholder="user / session / IP / chiến dịch" style="width:280px">
    <select name="ngay">
        <?php foreach ( array( 1 => 'Hôm nay', 7 => '7 ngày', 30 => '30 ngày', 90 => '90 ngày', 365 => '1 năm' ) as $k => $v ) : ?>
        <option value="<?php echo (int) $k; ?>" <?php selected( $ngay, $k ); ?>><?php echo esc_html( $v ); ?></option>
        <?php endforeach; ?>
    </select>
    <button class="button">Lọc</button>
    <span style="margin-left:10px;color:#666"><b><?php echo (int) $tong; ?></b> báo lỗi</span>
</form>

<?php if ( $theo_user ) : ?>
<h2 style="font-size:14px;margin:18px 0 6px">Theo chủ shortlink</h2>
<table class="widefat striped" style="max-width:560px">
    <thead><tr><th>Tài khoản</th><th style="width:110px">Số báo lỗi</th><th style="width:110px">Số IP khác nhau</th></tr></thead>
    <tbody>
    <?php foreach ( $theo_user as $u ) : ?>
        <tr>
            <td><?php echo esc_html( $u->ten ?: '—' ); ?></td>
            <td><?php echo (int) $u->n; ?></td>
            <td><?php echo (int) $u->so_ip; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h2 style="font-size:14px;margin:18px 0 6px">Chi tiết</h2>
<table class="widefat striped">
    <thead><tr>
        <th style="width:140px">Thời gian</th><th style="width:110px">Tài khoản</th>
        <th style="width:90px">Loại lỗi</th><th>Nội dung</th>
        <th style="width:150px">Chiến dịch</th><th style="width:120px">IP</th>
        <th style="width:90px">Thiết bị</th>
    </tr></thead>
    <tbody>
    <?php if ( ! $rows ) : ?>
        <tr><td colspan="7">Không có báo lỗi nào trong khoảng đã chọn.</td></tr>
    <?php else : foreach ( $rows as $r ) : ?>
        <tr>
            <td><?php echo esc_html( $r->created_at ); ?></td>
            <td><?php echo esc_html( $r->user_login ?: '—' ); ?></td>
            <td><?php echo esc_html( $r->error_type ); ?></td>
            <td style="max-width:380px"><?php echo esc_html( mb_substr( (string) $r->error_message, 0, 300 ) ); ?></td>
            <td><?php echo esc_html( $r->camp ?: '—' ); ?></td>
            <td style="font-size:11px"><?php echo esc_html( $r->ip_address ); ?></td>
            <td style="font-size:11px" title="<?php echo esc_attr( (string) $r->user_agent ); ?>">
                <?php echo esc_html( function_exists( 'sitetop_mo_ta_thiet_bi' ) ? sitetop_mo_ta_thiet_bi( $r->user_agent ?? '' ) : '—' ); ?>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>

<?php if ( $so_trang > 1 ) : ?>
<div class="tablenav"><div class="tablenav-pages" style="margin:12px 0">
    <?php echo paginate_links( array(
        'base'    => add_query_arg( 'paged', '%#%' ),
        'format'  => '', 'current' => $trang, 'total' => $so_trang,
    ) ); ?>
</div></div>
<?php endif; ?>

<?php endif; ?>
</div>
