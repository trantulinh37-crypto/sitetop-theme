<?php
/**
 * Admin: Duyệt nguồn file gốc
 * Mỗi user có một DANH SÁCH nguồn; duyệt/từ chối được từng nguồn một, hoặc
 * duyệt gộp tất cả nguồn đang chờ. Xem includes/source-approval.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_sitetop_users' ) ) return;

global $wpdb;

// ── Xử lý duyệt / từ chối ───────────────────────────────────────
if ( isset( $_POST['src_action'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'sitetop_src_action' ) ) {
    $r = sitetop_review_source(
        (int) ( $_POST['target_user_id'] ?? 0 ),
        sanitize_text_field( $_POST['src_action'] ),
        wp_unslash( $_POST['src_note'] ?? '' ),
        sanitize_text_field( $_POST['src_item_id'] ?? '' )
    );
    if ( is_wp_error( $r ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html( $r->get_error_message() ) . '</p></div>';
    } else {
        $done = $_POST['src_action'] === 'approve' ? 'DUYỆT' : 'TỪ CHỐI';
        echo '<div class="notice notice-success"><p>Đã ' . $done . ' ' . (int) $r . ' nguồn của user #' . (int) $_POST['target_user_id'] . '.</p></div>';
    }
}

$cap_key = $wpdb->prefix . 'capabilities';
$filter  = sanitize_text_field( $_GET['src'] ?? 'pending' );
if ( ! in_array( $filter, array( 'pending', 'approved', 'rejected', 'none' ), true ) ) $filter = 'pending';

// ── Đếm theo trạng thái ─────────────────────────────────────────
// "Chờ duyệt" đếm theo cờ sitetop_src_pending: user vừa có nguồn đã duyệt vừa
// có nguồn mới chờ duyệt vẫn phải nằm trong hàng đợi này.
$counts = array();
$counts['pending'] = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key='sitetop_src_pending' AND meta_value='1'"
);
foreach ( array( 'approved', 'rejected' ) as $st ) {
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

// ── Lấy danh sách user ──────────────────────────────────────────
$page_num = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
$per_page = 20;
$offset   = ( $page_num - 1 ) * $per_page;

if ( $filter === 'none' ) {
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT u.ID, u.user_login, u.user_email, u.display_name
         FROM {$wpdb->users} u
         INNER JOIN {$wpdb->usermeta} c ON c.user_id=u.ID AND c.meta_key=%s AND c.meta_value LIKE %s
         LEFT JOIN {$wpdb->usermeta} st ON st.user_id=u.ID AND st.meta_key='sitetop_src_status'
         WHERE st.umeta_id IS NULL
         ORDER BY u.ID DESC LIMIT %d OFFSET %d",
        $cap_key, '%subscriber%', $per_page, $offset
    ) );
} elseif ( $filter === 'pending' ) {
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT u.ID, u.user_login, u.user_email, u.display_name
         FROM {$wpdb->users} u
         INNER JOIN {$wpdb->usermeta} pd ON pd.user_id=u.ID AND pd.meta_key='sitetop_src_pending' AND pd.meta_value='1'
         ORDER BY u.ID DESC LIMIT %d OFFSET %d", $per_page, $offset
    ) );
} else {
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT u.ID, u.user_login, u.user_email, u.display_name
         FROM {$wpdb->users} u
         INNER JOIN {$wpdb->usermeta} st ON st.user_id=u.ID AND st.meta_key='sitetop_src_status' AND st.meta_value=%s
         ORDER BY u.ID DESC LIMIT %d OFFSET %d", $filter, $per_page, $offset
    ) );
}
$total_pages = (int) ceil( $counts[ $filter ] / $per_page );

$tabs = array(
    'pending'  => array( 'Chờ duyệt',     '#E08700' ),
    'approved' => array( 'Đã duyệt',      '#00A96E' ),
    'rejected' => array( 'Từ chối',       '#E0364B' ),
    'none'     => array( 'Chưa khai báo', '#5A6684' ),
);
$badge_cls = array( 'pending' => 'src-b-pending', 'approved' => 'src-b-approved', 'rejected' => 'src-b-rejected' );
$gate_on   = function_exists( 'sitetop_source_gate_enabled' ) && sitetop_source_gate_enabled();
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
.src-user b{display:block;color:#1F2A44}
.src-user small{display:block;color:#8A93AB}
.src-rows{display:flex;flex-direction:column;gap:7px}
.src-row{display:flex;align-items:flex-start;gap:9px;padding:8px 10px;background:#F8FAFB;border:1px solid #E6EBF5;border-radius:1px;border-left:3px solid #ccc}
.src-row.st-approved{border-left-color:#00A96E}
.src-row.st-pending{border-left-color:#E08700}
.src-row.st-rejected{border-left-color:#E0364B;background:#FEF7F7}
.src-row-txt{flex:1;min-width:0;word-break:break-word;font-size:12.5px;line-height:1.55;color:#1F2A44}
.src-row-txt a{color:#4E80B4}
.src-row-note{display:block;margin-top:3px;font-size:11.5px;color:#991B1B}
.src-row-when{display:block;margin-top:3px;font-size:11px;color:#8A93AB}
.src-bdg{display:inline-block;flex:none;padding:3px 8px;border-radius:1px;font-size:10.5px;font-weight:700;white-space:nowrap}
.src-b-pending{background:#FEF3C7;color:#92400E}.src-b-approved{background:#DCFCE7;color:#046C4A}
.src-b-rejected{background:#FEE2E2;color:#991B1B}.src-b-none{background:#EEF1F8;color:#5A6684}
.src-btns{display:flex;gap:5px;flex:none}
.src-ok,.src-no{padding:5px 11px;border:none;border-radius:1px;font-size:11.5px;font-weight:700;cursor:pointer;color:#fff;white-space:nowrap}
.src-ok{background:#00A96E}.src-no{background:#E0364B}
.src-all{padding:7px 13px;border:none;border-radius:1px;font-size:12px;font-weight:700;cursor:pointer;color:#fff;background:#4E80B4;white-space:nowrap}
.src-gate{padding:10px 14px;border-radius:1px;font-size:13px;margin:10px 0}
.src-gate.on{background:#ECFAF3;border:1px solid #B7EBD4;color:#046C4A}
.src-gate.off{background:#FEF3C7;border:1px solid #F5D98B;color:#92400E}
</style>
<div class="wrap">
<h1>Duyệt nguồn file</h1>

<div class="src-gate <?php echo $gate_on ? 'on' : 'off'; ?>">
    <?php if ( $gate_on ) : ?>
        <b>Đang BẬT</b> — user phải còn ít nhất <b>1 nguồn đã duyệt</b> mới rút gọn link và dùng API được.
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
    <th style="width:190px">User</th>
    <th>Nguồn file gốc</th>
    <th style="width:150px">Duyệt gộp</th>
</tr></thead>
<tbody>
<?php if ( empty( $rows ) ) : ?>
    <tr><td colspan="3" style="text-align:center;padding:30px;color:#8A93AB">Không có user nào ở trạng thái này.</td></tr>
<?php else : foreach ( $rows as $r ) :
    $items    = sitetop_get_source_items( $r->ID );
    $n_pend   = 0;
    foreach ( $items as $it ) if ( ( $it['status'] ?? '' ) === 'pending' ) $n_pend++;
    $can      = sitetop_source_is_approved( $r->ID );
?>
<tr>
    <td class="src-user">
        <b><?php echo esc_html( $r->display_name ?: $r->user_login ); ?></b>
        <small><?php echo esc_html( $r->user_email ); ?></small>
        <small>#<?php echo (int) $r->ID; ?></small>
        <small style="margin-top:5px;color:<?php echo $can ? '#046C4A' : '#991B1B'; ?>;font-weight:700">
            <?php echo $can ? '● Đang rút gọn được' : '● Đang bị khoá'; ?>
        </small>
    </td>
    <td>
        <?php if ( ! $items ) : ?>
            <i style="color:#8A93AB">— chưa khai báo nguồn nào —</i>
        <?php else : ?>
        <div class="src-rows">
        <?php foreach ( $items as $it ) :
            $ist = $it['status'] ?? 'pending';
        ?>
            <div class="src-row st-<?php echo esc_attr( $ist ); ?>">
                <span class="src-row-txt">
                    <?php echo wp_kses( make_clickable( esc_html( $it['text'] ) ), array( 'a' => array( 'href' => array(), 'rel' => array(), 'target' => array() ) ) ); ?>
                    <?php if ( $ist === 'rejected' && ! empty( $it['note'] ) ) : ?>
                        <em class="src-row-note">Lý do: <?php echo esc_html( $it['note'] ); ?></em>
                    <?php endif; ?>
                    <?php if ( ! empty( $it['added_at'] ) ) : ?>
                        <em class="src-row-when">Khai lúc <?php echo esc_html( date( 'd/m/Y H:i', strtotime( $it['added_at'] ) ) ); ?></em>
                    <?php endif; ?>
                </span>
                <span class="src-bdg <?php echo $badge_cls[ $ist ] ?? 'src-b-none'; ?>">
                    <?php echo $tabs[ $ist ][0] ?? $ist; ?>
                </span>
                <form method="post" class="src-btns" onsubmit="return srcConfirm(this)">
                    <?php wp_nonce_field( 'sitetop_src_action' ); ?>
                    <input type="hidden" name="target_user_id" value="<?php echo (int) $r->ID; ?>">
                    <input type="hidden" name="src_item_id" value="<?php echo esc_attr( $it['id'] ); ?>">
                    <input type="hidden" name="src_note" value="">
                    <?php if ( $ist !== 'approved' ) : ?>
                        <button class="src-ok" name="src_action" value="approve">Duyệt</button>
                    <?php endif; ?>
                    <?php if ( $ist !== 'rejected' ) : ?>
                        <button class="src-no" name="src_action" value="reject">Từ chối</button>
                    <?php endif; ?>
                </form>
            </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </td>
    <td>
        <?php if ( $n_pend > 1 ) : ?>
        <form method="post" onsubmit="return srcConfirm(this)">
            <?php wp_nonce_field( 'sitetop_src_action' ); ?>
            <input type="hidden" name="target_user_id" value="<?php echo (int) $r->ID; ?>">
            <input type="hidden" name="src_item_id" value="">
            <input type="hidden" name="src_note" value="">
            <button class="src-all" name="src_action" value="approve">Duyệt cả <?php echo $n_pend; ?> nguồn</button>
        </form>
        <?php elseif ( $n_pend === 1 ) : ?>
            <span style="color:#8A93AB;font-size:12px">1 nguồn đang chờ</span>
        <?php else : ?>
            <span style="color:#8A93AB;font-size:12px">—</span>
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
    var btn = document.activeElement;
    var act = btn && btn.name === 'src_action' ? btn.value : 'approve';
    if(act === 'reject'){
        var note = prompt('Lý do từ chối nguồn này? (user sẽ nhìn thấy)');
        if(note === null) return false;
        form.querySelector('input[name=src_note]').value = note;
        return true;
    }
    return confirm('Duyệt nguồn này?');
}
</script>
