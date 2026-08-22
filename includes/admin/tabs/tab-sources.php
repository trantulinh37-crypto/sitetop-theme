<?php
/**
 * Admin: Duyệt nguồn file gốc
 * Xem / duyệt / từ chối nguồn của từng user. Trạng thái lưu ở user meta
 * (xem includes/source-approval.php).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_sitetop_users' ) ) return;

global $wpdb;

// ── Xử lý duyệt / từ chối ───────────────────────────────────────
if ( isset( $_POST['src_action'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'sitetop_src_action' ) ) {
    $r = sitetop_review_source(
        (int) ( $_POST['target_user_id'] ?? 0 ),
        sanitize_text_field( $_POST['src_action'] ),
        wp_unslash( $_POST['src_note'] ?? '' )
    );
    if ( is_wp_error( $r ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html( $r->get_error_message() ) . '</p></div>';
    } else {
        $done = $_POST['src_action'] === 'approve' ? 'đã được DUYỆT' : 'đã bị TỪ CHỐI';
        echo '<div class="notice notice-success"><p>Nguồn của user #' . (int) $_POST['target_user_id'] . ' ' . $done . '.</p></div>';
    }
}

$cap_key = $wpdb->prefix . 'capabilities';
$filter  = sanitize_text_field( $_GET['src'] ?? 'pending' );
if ( ! in_array( $filter, array( 'pending', 'approved', 'rejected', 'none' ), true ) ) $filter = 'pending';

// ── Đếm theo trạng thái ─────────────────────────────────────────
$counts = array( 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'none' => 0 );
foreach ( array( 'pending', 'approved', 'rejected' ) as $st ) {
    $counts[ $st ] = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key='sitetop_src_status' AND meta_value=%s", $st
    ) );
}
$counts['none'] = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
     INNER JOIN {$wpdb->usermeta} c ON c.user_id=u.ID AND c.meta_key=%s AND c.meta_value LIKE %s
     LEFT JOIN {$wpdb->usermeta} st ON st.user_id=u.ID AND st.meta_key='sitetop_src_status'
     WHERE st.umeta_id IS NULL", $cap_key, '%subscriber%'
) );

// ── Lấy danh sách ───────────────────────────────────────────────
$page_num = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
$per_page = 20;
$offset   = ( $page_num - 1 ) * $per_page;

if ( $filter === 'none' ) {
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
                '' AS src_status, '' AS src_value, '' AS submitted_at, '' AS note
         FROM {$wpdb->users} u
         INNER JOIN {$wpdb->usermeta} c ON c.user_id=u.ID AND c.meta_key=%s AND c.meta_value LIKE %s
         LEFT JOIN {$wpdb->usermeta} st ON st.user_id=u.ID AND st.meta_key='sitetop_src_status'
         WHERE st.umeta_id IS NULL
         ORDER BY u.ID DESC LIMIT %d OFFSET %d",
        $cap_key, '%subscriber%', $per_page, $offset
    ) );
} else {
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
                st.meta_value AS src_status,
                sv.meta_value AS src_value,
                sb.meta_value AS submitted_at,
                nt.meta_value AS note
         FROM {$wpdb->users} u
         INNER JOIN {$wpdb->usermeta} st ON st.user_id=u.ID AND st.meta_key='sitetop_src_status' AND st.meta_value=%s
         LEFT JOIN {$wpdb->usermeta} sv ON sv.user_id=u.ID AND sv.meta_key='sitetop_src_value'
         LEFT JOIN {$wpdb->usermeta} sb ON sb.user_id=u.ID AND sb.meta_key='sitetop_src_submitted_at'
         LEFT JOIN {$wpdb->usermeta} nt ON nt.user_id=u.ID AND nt.meta_key='sitetop_src_note'
         ORDER BY sb.meta_value DESC, u.ID DESC LIMIT %d OFFSET %d",
        $filter, $per_page, $offset
    ) );
}
$total_pages = (int) ceil( $counts[ $filter ] / $per_page );

$tabs = array(
    'pending'  => array( 'Chờ duyệt',    '#E08700' ),
    'approved' => array( 'Đã duyệt',     '#00A96E' ),
    'rejected' => array( 'Từ chối',      '#E0364B' ),
    'none'     => array( 'Chưa khai báo','#5A6684' ),
);
$gate_on = function_exists( 'sitetop_source_gate_enabled' ) && sitetop_source_gate_enabled();
?>
<style>
.src-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:16px 0 18px}
.src-stat{background:#fff;border:1px solid #DFE5F3;border-radius:1px;padding:13px 15px;border-left:3px solid #ccc}
.src-stat span{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#5A6684}
.src-stat b{display:block;font-size:22px;font-weight:800;color:#1F2A44;margin-top:4px}
.src-filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.src-filters a{padding:7px 14px;border:1px solid #DFE5F3;border-radius:1px;background:#fff;text-decoration:none;color:#5A6684;font-size:13px;font-weight:600}
.src-filters a.on{background:#4E80B4;border-color:#4E80B4;color:#fff}
.src-tbl{width:100%;border-collapse:collapse;background:#fff;border:1px solid #DFE5F3}
.src-tbl th{background:#F8FAFB;padding:10px 12px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#5A6684;border-bottom:1px solid #DFE5F3}
.src-tbl td{padding:12px;border-bottom:1px solid #ECF0FA;vertical-align:top;font-size:13px}
.src-tbl tr:hover{background:#F9FBFD}
.src-user b{display:block;color:#1F2A44}
.src-user small{color:#8A93AB}
.src-src{max-width:420px;white-space:pre-wrap;word-break:break-word;font-size:12.5px;line-height:1.6;color:#1F2A44}
.src-src a{color:#4E80B4}
.src-bdg{display:inline-block;padding:4px 9px;border-radius:1px;font-size:10.5px;font-weight:700}
.src-b-pending{background:#FEF3C7;color:#92400E}.src-b-approved{background:#DCFCE7;color:#046C4A}
.src-b-rejected{background:#FEE2E2;color:#991B1B}.src-b-none{background:#EEF1F8;color:#5A6684}
.src-btns{display:flex;gap:6px;flex-wrap:wrap}
.src-ok,.src-no{padding:6px 13px;border:none;border-radius:1px;font-size:12px;font-weight:700;cursor:pointer;color:#fff}
.src-ok{background:#00A96E}.src-no{background:#E0364B}
.src-note-box{margin-top:6px;font-size:11.5px;color:#991B1B}
.src-gate{padding:10px 14px;border-radius:1px;font-size:13px;margin:10px 0}
.src-gate.on{background:#ECFAF3;border:1px solid #B7EBD4;color:#046C4A}
.src-gate.off{background:#FEF3C7;border:1px solid #F5D98B;color:#92400E}
</style>
<div class="wrap">
<h1>Duyệt nguồn file</h1>

<div class="src-gate <?php echo $gate_on ? 'on' : 'off'; ?>">
    <?php if ( $gate_on ) : ?>
        <b>Đang BẬT</b> — user chưa được duyệt nguồn thì không rút gọn link được và API bị khoá.
    <?php else : ?>
        <b>Đang TẮT</b> — mọi user rút gọn link bình thường dù chưa duyệt nguồn.
        Bật lại ở <a href="<?php echo esc_url( admin_url( 'admin.php?page=sitetop-settings' ) ); ?>">Cài đặt TT</a>.
    <?php endif; ?>
</div>

<div class="src-stats">
<?php foreach ( $tabs as $k => $t ) : ?>
    <div class="src-stat" style="border-left-color:<?php echo $t[1]; ?>">
        <span><?php echo $t[0]; ?></span><b><?php echo number_format( $counts[ $k ] ); ?></b>
    </div>
<?php endforeach; ?>
</div>

<div class="src-filters">
<?php foreach ( $tabs as $k => $t ) : ?>
    <a class="<?php echo $filter === $k ? 'on' : ''; ?>"
       href="<?php echo esc_url( admin_url( 'admin.php?page=sitetop-sources&src=' . $k ) ); ?>">
       <?php echo $t[0]; ?> (<?php echo number_format( $counts[ $k ] ); ?>)
    </a>
<?php endforeach; ?>
</div>

<table class="src-tbl">
<thead><tr>
    <th style="width:180px">User</th>
    <th>Nguồn file gốc</th>
    <th style="width:130px">Ngày gửi</th>
    <th style="width:100px">Trạng thái</th>
    <th style="width:190px">Hành động</th>
</tr></thead>
<tbody>
<?php if ( empty( $rows ) ) : ?>
    <tr><td colspan="5" style="text-align:center;padding:30px;color:#8A93AB">Không có user nào ở trạng thái này.</td></tr>
<?php else : foreach ( $rows as $r ) :
    $st  = $r->src_status ?: 'none';
    $lbl = $tabs[ $st ][0] ?? 'Chưa khai báo';
?>
<tr>
    <td class="src-user">
        <b><?php echo esc_html( $r->display_name ?: $r->user_login ); ?></b>
        <small><?php echo esc_html( $r->user_email ); ?></small>
        <small>#<?php echo (int) $r->ID; ?></small>
    </td>
    <td>
        <div class="src-src"><?php
            echo $r->src_value
                ? wp_kses( make_clickable( esc_html( $r->src_value ) ), array( 'a' => array( 'href' => array(), 'rel' => array(), 'target' => array() ) ) )
                : '<i style="color:#8A93AB">— chưa khai báo —</i>';
        ?></div>
        <?php if ( $st === 'rejected' && $r->note ) : ?>
            <div class="src-note-box"><b>Lý do:</b> <?php echo esc_html( $r->note ); ?></div>
        <?php endif; ?>
    </td>
    <td style="color:#5A6684;font-size:12px">
        <?php echo $r->submitted_at ? esc_html( date( 'd/m/Y H:i', strtotime( $r->submitted_at ) ) ) : '—'; ?>
    </td>
    <td><span class="src-bdg src-b-<?php echo esc_attr( $st ); ?>"><?php echo esc_html( $lbl ); ?></span></td>
    <td>
        <?php if ( $st === 'none' ) : ?>
            <span style="color:#8A93AB;font-size:12px">Chờ user khai báo</span>
        <?php else : ?>
        <form method="post" class="src-btns" onsubmit="return srcConfirm(this)">
            <?php wp_nonce_field( 'sitetop_src_action' ); ?>
            <input type="hidden" name="target_user_id" value="<?php echo (int) $r->ID; ?>">
            <input type="hidden" name="src_note" value="">
            <?php if ( $st !== 'approved' ) : ?>
                <button class="src-ok" name="src_action" value="approve">Duyệt</button>
            <?php endif; ?>
            <?php if ( $st !== 'rejected' ) : ?>
                <button class="src-no" name="src_action" value="reject">Từ chối</button>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>

<?php if ( $total_pages > 1 ) : ?>
<div class="tablenav"><div class="tablenav-pages" style="margin:14px 0">
<?php echo paginate_links( array(
    'base'    => admin_url( 'admin.php?page=sitetop-sources&src=' . $filter . '&paged=%#%' ),
    'format'  => '',
    'current' => $page_num,
    'total'   => $total_pages,
) ); ?>
</div></div>
<?php endif; ?>
</div>

<script>
function srcConfirm(form){
    var btn = form.querySelector('button[type=submit]:focus') || document.activeElement;
    var act = btn && btn.value;
    if(act === 'reject'){
        var note = prompt('Lý do từ chối nguồn này? (user sẽ nhìn thấy)');
        if(note === null) return false;
        form.querySelector('input[name=src_note]').value = note;
        return true;
    }
    return confirm('Duyệt nguồn của user này?');
}
</script>
