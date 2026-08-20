<?php
/**
 * Template Name: User Dashboard
 * SiteTop.net V2 - Publisher Dashboard (người rút gọn link kiếm tiền)
 * 
 * Tabs: Tổng quan | Links của tôi | Tạo link mới | Rút tiền | Referral | Tài khoản
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }

$user_id = get_current_user_id();
$user    = wp_get_current_user();

// Chỉ publisher (hoặc admin) được vào dashboard user. Tài khoản quảng cáo mở thẳng /user
// sẽ thấy nguyên form Tạo link mới và form Rút tiền của sổ publisher — chiều ngược của
// guard ở page-customer-dashboard.php. Khách hàng → đưa về đúng dashboard của họ (/customer).
if ( function_exists( 'sitetop_is_advertiser_account' ) && sitetop_is_advertiser_account( $user ) ) {
    wp_redirect( function_exists( 'sitetop_get_dashboard_url' ) ? sitetop_get_dashboard_url( $user ) : home_url( '/customer' ) );
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix . 'sitetop_';
$today  = date( 'Y-m-d', strtotime( sitetop_current_time() ) );

// Stats
$balance       = function_exists('sitetop_get_user_balance_amount') ? sitetop_get_user_balance_amount( $user_id ) : 0;
$total_earned  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$prefix}transactions WHERE user_id=%d AND type='shortlink_reward'", $user_id ) );
$today_earned  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$prefix}transactions WHERE user_id=%d AND type='shortlink_reward' AND DATE(created_at)=%s", $user_id, $today ) );
$total_links   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$prefix}user_shortlinks WHERE user_id=%d", $user_id ) );
$total_completed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(total_completed),0) FROM {$prefix}user_shortlinks WHERE user_id=%d", $user_id ) );
$today_completed = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$prefix}shortlink_visits v
     INNER JOIN {$prefix}user_shortlinks us ON v.shortlink_id = us.id
     WHERE us.user_id=%d AND v.step='verified' AND v.reward_paid=1 AND DATE(v.created_at)=%s", $user_id, $today ) );
$pending_wd    = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE user_id=%d AND status IN ('pending','approved')", $user_id ) );
$total_withdrawn = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE user_id=%d AND status IN ('completed')", $user_id ) );

// My links (user_shortlinks) — paginated + search ?q= (mã code, alias, full shortlink, URL gốc)
$lq = isset($_GET['q']) ? trim( sanitize_text_field( wp_unslash( $_GET['q'] ) ) ) : '';
$lq_where = '';
$lq_args  = array();
if ( $lq !== '' ) {
    // Dán FULL shortlink (https://sitetop.net/W1wcNk) → tách path làm mã để match code/alias.
    // KHÔNG so hostname với home_url (bài học 13/04: home_url có thể khác domain truy cập) —
    // nguyên chuỗi vẫn được match với original_url nên dán URL gốc cũng tìm được.
    $lq_code = $lq;
    if ( preg_match( '#^https?://#i', $lq ) ) {
        $lq_path = trim( (string) parse_url( $lq, PHP_URL_PATH ), '/' );
        if ( $lq_path !== '' ) $lq_code = $lq_path;
    }
    $like_code = '%' . $wpdb->esc_like( $lq_code ) . '%';
    $like_full = '%' . $wpdb->esc_like( $lq ) . '%';
    $lq_where  = " AND (us.code LIKE %s OR us.alias LIKE %s OR us.original_url LIKE %s)";
    $lq_args   = array( $like_code, $like_code, $like_full );
}

$lpg_per_page = 10;
$lpg = max(1, (int) ($_GET['lpg'] ?? 1));
// Số link khớp tìm kiếm (chỉ trong link của CHÍNH user) — dùng cho phân trang + dòng "Tìm thấy N kết quả".
// $total_links (tổng không lọc) giữ nguyên cho stat Tổng quan + tiêu đề card.
$links_found = ( $lq === '' ) ? $total_links : (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$prefix}user_shortlinks us WHERE us.user_id = %d{$lq_where}",
    array_merge( array( $user_id ), $lq_args )
) );
$lpg_total_pages = max(1, (int) ceil($links_found / $lpg_per_page));
if ($lpg > $lpg_total_pages) $lpg = $lpg_total_pages;
$lpg_offset = ($lpg - 1) * $lpg_per_page;
$my_links = $wpdb->get_results( $wpdb->prepare(
    "SELECT us.*,
            us.code as shortcode,
            us.original_url as target_url,
            us.total_clicks as click_count,
            (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE shortlink_id=us.id AND step='verified' AND DATE(created_at)=%s) as today_clicks
     FROM {$prefix}user_shortlinks us
     WHERE us.user_id = %d{$lq_where}
     ORDER BY us.created_at DESC
     LIMIT %d OFFSET %d",
    array_merge( array( $today, $user_id ), $lq_args, array( $lpg_per_page, $lpg_offset ) )
) );

// 30-day chart
$chart = array();
for ( $i = 29; $i >= 0; $i-- ) {
    $d = date( 'Y-m-d', strtotime( "-{$i} days", strtotime( sitetop_current_time() ) ) );
    $clicks = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}shortlink_visits v INNER JOIN {$prefix}user_shortlinks us ON v.shortlink_id=us.id WHERE us.user_id=%d AND v.step='verified' AND DATE(v.created_at)=%s", $user_id, $d
    ) );
    $earned = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$prefix}transactions WHERE user_id=%d AND type='shortlink_reward' AND DATE(created_at)=%s", $user_id, $d
    ) );
    $chart[] = array( 'date' => date('d/m', strtotime($d)), 'clicks' => $clicks, 'earned' => $earned );
}

// Withdrawals
$withdrawals = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$prefix}withdrawals WHERE user_id=%d ORDER BY created_at DESC LIMIT 10", $user_id
) );

// Transactions
$transactions = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$prefix}transactions WHERE user_id=%d ORDER BY created_at DESC LIMIT 10", $user_id
) );

$min_wd = floatval( sitetop_get_option( 'min_withdrawal', 50000 ) );
$nonce  = wp_create_nonce( 'sitetop_nonce' );
$home   = home_url();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - <?php bloginfo('name'); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<?php wp_head(); ?>
<style>
:root{--p:#1E5EFF;--pl:#4C7DFF;--pd:#0A1633;--a:#00C6FF;--al:#5CDBFF;--bg:#F2F5FC;--card:#fff;--dark:#0A1633;--txt:#1F2A44;--txtl:#5A6684;--txtm:#8A93AB;--brd:#DFE5F3;--brdl:#ECF0FA;--ok:#00A96E;--err:#E0364B;--warn:#E08700;--info:#1E5EFF;--font:'Inter',sans-serif;--fonth:'Plus Jakarta Sans',sans-serif;--mono:'JetBrains Mono',monospace;--rad:16px;--rads:10px;--sidebar-w:248px}
*{box-sizing:border-box;margin:0;padding:0}html,body{width:100%;overflow-x:hidden}body{font-family:var(--font);color:var(--txt);background:var(--bg);line-height:1.6}
.card{max-width:100%;overflow:hidden}

/* ── Sidebar: nền sáng + nav dạng pill (trước đây là sidebar tối) ── */
.sidebar{position:fixed;top:0;left:0;bottom:0;width:var(--sidebar-w);background:#fff;border-right:1px solid var(--brd);z-index:100;display:flex;flex-direction:column;overflow-y:auto}
.sidebar-logo{padding:20px 20px 16px;display:flex;align-items:center;gap:9px;text-decoration:none;font-family:var(--fonth);font-weight:800;font-size:17px;color:var(--pd)}
.sidebar-logo svg{flex-shrink:0;color:var(--p)}
.lg-chip{display:inline-flex}
.lgd{color:#0F172A}
.lgb{background:linear-gradient(120deg,#1E5EFF,#00C6FF);-webkit-background-clip:text;background-clip:text;color:transparent}
.sidebar-user{margin:0 14px 16px;padding:11px 12px;border-radius:14px;background:linear-gradient(135deg,#F4F7FF,#EAF1FF);border:1px solid #E1EAFF}
.sidebar-user-info{display:flex;align-items:center;gap:11px}
.sidebar-avatar{width:40px;height:40px;border-radius:13px;background:linear-gradient(135deg,var(--p),var(--a));color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;font-family:var(--fonth);font-weight:800;flex-shrink:0;box-shadow:0 5px 13px -3px rgba(30,94,255,.5)}
.sidebar-name{font-weight:700;font-size:13.5px;color:var(--pd);line-height:1.3}
.sidebar-role{font-size:11px;color:var(--p);font-weight:700;margin-top:1px;letter-spacing:.02em}
.sidebar-sec{padding:0 22px 0;font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:var(--txtm);margin-bottom:7px}

.sidebar-nav{flex:1;padding:0 12px 16px;display:flex;flex-direction:column;gap:3px}
.sidebar-nav-item{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:11px;color:var(--txtl);font-size:13.5px;font-weight:600;cursor:pointer;transition:background .18s,color .18s,box-shadow .18s;text-decoration:none}
.sidebar-nav-item:hover{background:#F2F6FE;color:var(--pd)}
.sidebar-nav-item.on{background:linear-gradient(135deg,#1E5EFF,#3E86FF);color:#fff;box-shadow:0 8px 18px -8px rgba(30,94,255,.75)}
.sidebar-nav-item.on svg,.sidebar-nav-item.on:hover svg{color:#fff}
.sidebar-nav-item.on:hover{background:linear-gradient(135deg,#1E5EFF,#3E86FF);color:#fff}
.sidebar-nav-item svg{width:18px;height:18px;flex-shrink:0;color:var(--txtm);transition:color .18s}
.sidebar-nav-item:hover svg{color:var(--p)}

.sidebar-bottom{padding:10px 12px;border-top:1px solid var(--brdl);display:flex;flex-direction:column;gap:2px}
.sidebar-bottom a{display:flex;align-items:center;gap:11px;padding:9px 12px;border-radius:10px;color:var(--txtl);text-decoration:none;font-size:13px;font-weight:600;transition:background .18s,color .18s}
.sidebar-bottom a:hover{background:#F2F6FE;color:var(--pd)}
.sidebar-bottom a:last-child:hover{background:#FFF0F2;color:var(--err)}
.sidebar-bottom a svg{width:16px;height:16px;flex-shrink:0}

/* ── Tránh bị thanh admin WordPress che ── */
body.admin-bar .sidebar{top:32px}
body.admin-bar .main-topbar{top:32px}
body.admin-bar .mobile-topbar{top:32px}
@media(max-width:782px){
    body.admin-bar .sidebar{top:46px}
    body.admin-bar .main-topbar,body.admin-bar .mobile-topbar{top:46px}
}
/* <=600px: WordPress chuyển admin bar sang position:absolute (cuộn theo trang)
   nên thanh sticky của dashboard không cần chừa chỗ nữa. */
@media(max-width:600px){
    body.admin-bar .main-topbar,body.admin-bar .mobile-topbar{top:0}
}

/* ── Sidebar overlay (mobile) ── */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99;opacity:0;transition:opacity .3s}
.sidebar-overlay.show{display:block;opacity:1}

/* ── Main content ── */
.main-wrap{margin-left:var(--sidebar-w);min-height:100vh}
.main-topbar{background:rgba(242,245,252,.88);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);border-bottom:1px solid var(--brdl);padding:0 28px;height:60px;display:flex;align-items:center;justify-content:space-between;gap:12px;position:sticky;top:0;z-index:50}
.main-topbar-title{font-family:var(--fonth);font-weight:800;font-size:19px;color:var(--pd);letter-spacing:-.015em}
.topbar-date{display:inline-flex;align-items:center;gap:7px;font-size:12.5px;font-weight:600;color:var(--txtl);background:#fff;border:1px solid var(--brd);border-radius:999px;padding:6px 13px}
.topbar-date svg{width:14px;height:14px;color:var(--p);flex-shrink:0}

.main-content{padding:22px 28px 34px;max-width:1180px;overflow-x:hidden}

/* ── Thẻ ví: điểm nhấn mới thay cho hàng 6 ô phẳng ── */
.wallet{position:relative;overflow:hidden;border-radius:20px;padding:24px 28px;margin-bottom:16px;background:linear-gradient(118deg,#0B31BE 0%,#1E5EFF 46%,#00A6FF 100%);color:#fff;display:flex;align-items:center;justify-content:space-between;gap:22px;flex-wrap:wrap;box-shadow:0 16px 36px -18px rgba(11,49,190,.85)}
.wallet::before{content:'';position:absolute;right:-70px;top:-110px;width:290px;height:290px;border-radius:50%;background:rgba(255,255,255,.1)}
.wallet::after{content:'';position:absolute;right:70px;bottom:-140px;width:250px;height:250px;border-radius:50%;border:1.5px solid rgba(255,255,255,.22)}
.wallet-l{position:relative;z-index:2;min-width:0}
.wallet-lb{display:flex;align-items:center;gap:7px;font-size:11px;font-weight:700;letter-spacing:.15em;text-transform:uppercase;color:rgba(255,255,255,.78)}
.wallet-lb svg{width:14px;height:14px;flex-shrink:0}
.wallet-v{font-family:var(--fonth);font-weight:800;font-size:clamp(30px,4.2vw,42px);line-height:1.1;margin:5px 0 10px;letter-spacing:-.025em}
.wallet-meta{display:flex;gap:8px;flex-wrap:wrap}
.wallet-chip{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;padding:5px 12px;border-radius:999px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.22);color:rgba(255,255,255,.9)}
.wallet-chip b{font-weight:800;color:#fff}
.wallet-r{position:relative;z-index:2;display:flex;gap:10px;flex-wrap:wrap}
.wbtn-w,.wbtn-g{display:inline-flex;align-items:center;gap:8px;padding:12px 20px;border-radius:12px;font-family:var(--font);font-size:13.5px;font-weight:700;cursor:pointer;transition:transform .18s,box-shadow .18s;white-space:nowrap}
.wbtn-w svg,.wbtn-g svg{width:16px;height:16px;flex-shrink:0}
.wbtn-w{background:#fff;color:var(--p);border:none;box-shadow:0 10px 22px -10px rgba(3,20,70,.9)}
.wbtn-g{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.38)}
.wbtn-w:hover,.wbtn-g:hover{transform:translateY(-2px)}

/* ── Lưới chỉ số ── */
.dash-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:13px;margin-bottom:20px}

.pane{display:none;animation:fu .3s ease}.pane.on{display:block}
@keyframes fu{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

/* ── Bottom Nav (mobile) ── */
.bottom-nav{display:none;position:fixed;bottom:0;left:0;right:0;background:rgba(255,255,255,.96);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);z-index:100;padding:6px 6px env(safe-area-inset-bottom,4px);border-top:1px solid var(--brd);box-shadow:0 -4px 18px -8px rgba(15,32,74,.3)}
.bottom-nav-inner{display:flex;justify-content:space-around;align-items:center;gap:2px}
.bottom-nav-item{display:flex;flex-direction:column;align-items:center;gap:3px;padding:7px 4px;border-radius:12px;color:var(--txtm);text-decoration:none;font-size:10px;font-weight:600;cursor:pointer;transition:background .18s,color .18s;flex:1;min-width:0}
.bottom-nav-item svg{width:20px;height:20px;flex-shrink:0}
.bottom-nav-item span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
/* Mỗi tab một màu riêng — dùng lại bộ màu của thẻ chỉ số, chọn theo data-t
   nên đổi thứ tự menu cũng không lệch màu. */
.bottom-nav-item{--nc:#1E5EFF;--nb:#E9EFFF;position:relative}
.bottom-nav-item[data-t="overview"]{--nc:#1E5EFF;--nb:#E9EFFF}
.bottom-nav-item[data-t="links"]{--nc:#0090D0;--nb:#E2F4FF}
.bottom-nav-item[data-t="withdraw"]{--nc:#00A96E;--nb:#E1F8F0}
.bottom-nav-item[data-t="referral"]{--nc:#E0364B;--nb:#FFE9EC}
.bottom-nav-item[data-t="api"]{--nc:#6D4AFF;--nb:#F0EAFF}
.bottom-nav-item[data-t="account"]{--nc:#E07A00;--nb:#FFF2E2}
.bottom-nav-item svg{color:var(--nc);opacity:.5;transition:opacity .18s,transform .18s}
.bottom-nav-item:hover svg{opacity:.8}
.bottom-nav-item.on{color:var(--nc);background:var(--nb)}
.bottom-nav-item.on svg{color:var(--nc);opacity:1;transform:translateY(-1px)}
.bottom-nav-item.on span{font-weight:800}

/* ── Mobile topbar ── */
.mobile-topbar{display:none;background:#fff;border-bottom:1px solid var(--brd);padding:0 14px;height:54px;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.mobile-topbar-logo{font-family:var(--fonth);font-weight:800;font-size:17px;color:var(--pd);text-decoration:none;display:flex;align-items:center;gap:7px}
.mobile-topbar-logo svg{color:var(--p);flex-shrink:0}
.mobile-topbar-right{display:flex;align-items:center;gap:9px;font-size:12px}
.mobile-topbar-right .bal{display:inline-flex;align-items:center;padding:5px 11px;border-radius:999px;background:#EDF3FF;color:var(--p)!important;font-family:var(--fonth);font-weight:800;font-size:12.5px}
.mobile-topbar-right .avatar{width:30px;height:30px;border-radius:10px;background:linear-gradient(135deg,var(--p),var(--a));color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;font-family:var(--fonth)}

/* ── Mobile ── */
@media(max-width:1080px){
    .dash-stats{grid-template-columns:repeat(3,1fr)}
}
@media(max-width:960px){
    .wd-grid{grid-template-columns:1fr}
    .wd-legend{grid-template-columns:1fr 1fr}
}
@media(max-width:768px){
    .sidebar{display:none}
    .sidebar-overlay{display:none!important}
    .main-wrap{margin-left:0}
    .main-topbar{display:none}
    .mobile-topbar{display:flex}
    .bottom-nav{display:block}
    /* Nút liên hệ nổi (floating-contact.php) mặc định nằm sát góc trên toàn site.
       Trang này có .bottom-nav cố định ~60px nên nâng nút lên tránh đè lên nhau. */
    .ln-contact-fab{bottom:74px!important}
    .main-content{padding:14px 14px 150px}
    .main-content::after{content:'';display:block;height:50px}
    .dash-stats{grid-template-columns:repeat(2,1fr);gap:11px}
    .wallet{padding:20px;border-radius:18px;flex-direction:column;align-items:stretch;gap:16px}
    .wallet-r{display:grid;grid-template-columns:1fr 1fr}
    .wbtn-w,.wbtn-g{justify-content:center;padding:12px 10px}
}
@media(max-width:480px){
    .main-content{padding:12px 12px 150px}
    .wallet-v{margin:4px 0 8px}
}

.card{background:var(--card);border-radius:var(--rad);border:1px solid var(--brd);padding:22px;box-shadow:0 1px 2px rgba(15,32,74,.04);margin-bottom:18px}
.card-h{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:0;border-bottom:0;gap:10px;flex-wrap:wrap}
.card-h h3{font-family:var(--fonth);font-size:16px;font-weight:800;color:var(--pd);letter-spacing:-.015em;display:flex;align-items:center;gap:9px}
.card-h h3::before{content:'';width:4px;height:17px;border-radius:3px;background:linear-gradient(180deg,var(--p),var(--a));flex-shrink:0}
.sg{display:grid;gap:14px;margin-bottom:20px}
.sg4{grid-template-columns:repeat(2,1fr)}.sg6{grid-template-columns:repeat(auto-fit,minmax(130px,1fr))}
.sc{background:var(--card);border-radius:var(--rad);padding:15px;border:1px solid var(--brd);display:flex;flex-direction:column;gap:11px;transition:transform .2s,box-shadow .2s,border-color .2s;min-width:0;overflow:hidden}
.sc:hover{box-shadow:0 12px 26px -14px rgba(15,32,74,.4);transform:translateY(-2px);border-color:#CBD9F8}
.sc-icon{width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sc-icon svg{width:19px;height:19px}
.sc.s1 .sc-icon{background:#E9EFFF;color:#1E5EFF}.sc.s2 .sc-icon{background:#FFF2E2;color:#E07A00}
.sc.s3 .sc-icon{background:#E1F8F0;color:#00A96E}.sc.s4 .sc-icon{background:#FFE9EC;color:#E0364B}
.sc.s5 .sc-icon{background:#E2F4FF;color:#0090D0}.sc.s6 .sc-icon{background:#F0EAFF;color:#6D4AFF}
.sc-text{min-width:0;overflow:hidden}
.sc .sl{font-size:11.5px;color:var(--txtl);font-weight:600;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sc .sv{font-family:var(--fonth);font-weight:800;font-size:21px;color:var(--pd);line-height:1.15;white-space:nowrap;letter-spacing:-.025em;overflow:hidden;text-overflow:ellipsis}
.sc .ss{font-size:11px;color:var(--txtm);margin-top:5px;white-space:nowrap;font-weight:600}
.sc .ss b{color:var(--ok);font-weight:800}

/* ── Quy định chung: 2 cột, số thứ tự dạng chip. Toàn khối chuyển tông đỏ để
   nhấn mạnh mức độ nghiêm túc — dùng lại var(--err) và cặp màu badge lỗi
   (#FEE2E2/#991B1B) đã có sẵn trong file, không bịa màu mới. ── */
.rules{background:var(--card);border:2px solid var(--err);border-radius:var(--rad);padding:22px;margin-bottom:18px}
.rules-h{display:flex;align-items:center;gap:10px;font-family:var(--fonth);font-weight:800;font-size:16px;color:var(--pd);margin-bottom:4px;letter-spacing:-.015em}
.rules-h i{width:30px;height:30px;border-radius:10px;background:#FEE2E2;color:var(--err);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.rules-h i svg{width:16px;height:16px}
.rules-sub{font-size:12.5px;color:var(--txtl);margin:0 0 14px 40px}
.rules-list{display:grid;grid-template-columns:1fr;gap:10px;list-style:none}
.rules-list li{display:flex;gap:10px;align-items:flex-start;font-size:12.8px;color:var(--txtl);line-height:1.55}
.rules-list li em{flex-shrink:0;width:20px;height:20px;border-radius:7px;background:#FEE2E2;color:#991B1B;font-style:normal;font-family:var(--fonth);font-size:10.5px;font-weight:800;display:flex;align-items:center;justify-content:center;margin-top:2px}
.rules-list li b{color:var(--err);font-weight:700}
/* Dòng lưu ý cuối bảng: KHÔNG đánh số vì đây là giải thích cho quy định số 5, không
   phải một quy định mới. Nền + viền đứt để tách hẳn khỏi danh sách đánh số phía trên. */
.rules-note{display:flex;gap:10px;align-items:flex-start;margin-top:14px;padding:12px 14px;background:#FEF2F2;border:1px dashed var(--err);border-radius:10px;font-size:12.8px;line-height:1.6;color:#7F1D1D}
.rules-note i{flex-shrink:0;font-style:normal;font-family:var(--fonth);font-size:9.5px;font-weight:800;letter-spacing:.06em;color:#fff;background:var(--err);padding:3px 8px;border-radius:6px;margin-top:1px}
.rules-note b{color:var(--err);font-weight:800}

table{width:100%;border-collapse:collapse;font-size:13px}
thead th{background:#F5F8FE;padding:10px 12px;text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.07em;color:var(--txtl);font-weight:700;border-bottom:1px solid var(--brd)}
td{padding:11px 12px;border-bottom:1px solid var(--brdl);vertical-align:middle}
tbody tr:hover{background:#F7FAFF}
.badge{display:inline-flex;padding:4px 9px;border-radius:8px;font-size:10.5px;font-weight:700}
.b-ok{background:#DCFCE7;color:#046C4A}.b-warn{background:#FEF3C7;color:#92400E}.b-err{background:#FEE2E2;color:#991B1B}.b-info{background:#E3EBFF;color:#1743B8}.b-mute{background:#EEF1F8;color:#5A6684}
.mono{font-family:var(--mono);font-size:12px}
.link-url{color:var(--info);word-break:break-all;font-family:var(--mono);font-size:11px}
.copy-btn{padding:5px 11px;background:#F5F8FE;border:1px solid var(--brd);border-radius:8px;font-size:11px;cursor:pointer;font-family:var(--font);font-weight:600;transition:all .2s}
.copy-btn:hover{background:var(--p);color:#fff;border-color:var(--p)}
.amt-plus{color:var(--ok);font-weight:700}.amt-minus{color:var(--err);font-weight:700}

.ud-chart-legend{display:flex;gap:14px;font-size:12px;font-weight:600;color:var(--txtl)}
.ud-chart-legend span{display:inline-flex;align-items:center;gap:6px}
.ud-chart-legend span::before{content:'';width:9px;height:9px;border-radius:50%;display:inline-block}
.ud-chart-legend .lg-views::before{background:#1E5EFF}
.ud-chart-legend .lg-earned::before{background:#00A96E}
.ud-chart-container{position:relative;height:290px}

input:focus,select:focus,textarea:focus{outline:none;border-color:var(--p);box-shadow:0 0 0 3px rgba(30,94,255,.14)}

/* ── Tab Links: ô tạo link ── */
.lk-create{background:linear-gradient(135deg,#F3F7FF,#FFF 60%);border:1px solid #DCE7FF;border-radius:var(--rad);padding:20px 22px;margin-bottom:18px}
.lk-create-h{display:flex;align-items:center;gap:11px;margin-bottom:15px}
.lk-create-h i{width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,var(--p),var(--a));color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 6px 14px -5px rgba(30,94,255,.6)}
.lk-create-h i svg{width:19px;height:19px}
.lk-create-h b{display:block;font-family:var(--fonth);font-size:16px;font-weight:800;color:var(--pd);letter-spacing:-.015em;line-height:1.25}
.lk-create-h span{display:block;font-size:12px;color:var(--txtl);font-weight:500}
.lk-create-row{display:flex;gap:10px}
.lk-create-input{position:relative;flex:1;min-width:0}
.lk-create-input svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);width:17px;height:17px;color:var(--txtm);pointer-events:none}
.lk-create-input input{width:100%;padding:14px 14px 14px 40px;border:1.5px solid var(--brd);border-radius:12px;background:#fff;font-family:var(--font);font-size:14px;color:var(--txt)}
.lk-create-btn{display:inline-flex;align-items:center;gap:8px;padding:14px 24px;background:linear-gradient(135deg,#1E5EFF,#3E86FF);color:#fff;border:none;border-radius:12px;font-family:var(--font);font-size:14px;font-weight:700;cursor:pointer;white-space:nowrap;box-shadow:0 10px 22px -12px rgba(30,94,255,.9);transition:transform .18s}
.lk-create-btn svg{width:16px;height:16px;flex-shrink:0}
.lk-create-btn:hover{transform:translateY(-1px)}
.lk-adv-t{display:inline-flex;align-items:center;gap:6px;margin-top:11px;padding:0;background:none;border:none;color:var(--p);font-family:var(--font);font-size:12.5px;font-weight:700;cursor:pointer}
.lk-adv-t svg{width:14px;height:14px;transition:transform .2s}
.lk-adv-t.on svg{transform:rotate(180deg)}
.lk-adv{display:none;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px}
.lk-adv.on{display:grid}
.lk-adv label{display:block;font-size:11.5px;font-weight:700;color:var(--txtl);margin-bottom:5px}
.lk-adv input{width:100%;padding:11px 12px;border:1px solid var(--brd);border-radius:var(--rads);background:#fff;font-family:var(--font);font-size:13px}
.sf-result{display:none;background:#ECFAF3;border:1px solid #B7EBD4;border-radius:12px;padding:13px 15px;margin-top:14px}
.lk-result-h{display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:800;color:#046C4A;margin-bottom:9px}
.lk-result-h svg{width:15px;height:15px;flex-shrink:0}
.sf-result-row{display:flex;align-items:center;gap:8px}
.sf-result-row input{flex:1;min-width:0;padding:11px 12px;border:1px solid #B7EBD4;border-radius:9px;background:#fff;font-family:var(--mono);font-size:13px;color:var(--p);font-weight:600}
.sf-result-row button{padding:11px 18px;background:var(--ok);color:#fff;border:none;border-radius:9px;font-weight:700;cursor:pointer;font-family:var(--font);font-size:13px;white-space:nowrap}

/* ── Tab Links: tìm kiếm ── */
.lk-search{display:flex;gap:8px;margin:0 0 12px;flex-wrap:wrap;align-items:center}
.lk-search-box{position:relative;flex:1;min-width:220px}
.lk-search-box>svg{position:absolute;left:13px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--txtm);pointer-events:none}
.lk-search-box input{width:100%;padding:11px 34px 11px 38px;border:1px solid var(--brd);border-radius:var(--rads);background:#FAFCFF;font-family:var(--font);font-size:13px}
#lkClear{display:none;position:absolute;right:7px;top:50%;transform:translateY(-50%);width:22px;height:22px;align-items:center;justify-content:center;border:none;border-radius:50%;background:#EEF2FA;color:var(--txtl);font-size:15px;cursor:pointer;line-height:1;padding:0}
#lkClear:hover{background:var(--err);color:#fff}
.lk-search-btn{padding:11px 20px;background:var(--p);color:#fff;border:none;border-radius:var(--rads);font-family:var(--font);font-size:13px;font-weight:700;cursor:pointer}
.lk-info{font-size:12px;color:var(--txtm);margin:0 0 12px;font-weight:500}
.lk-info strong{color:var(--p)}
.lk-info a{color:var(--p);font-weight:700}

/* ── Tab Links: danh sách thẻ thay cho bảng 7 cột ── */
.lk-list{display:flex;flex-direction:column;gap:10px}
.lk-item{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:16px;border:1px solid var(--brd);border-radius:12px;background:#fff;padding:13px 14px 13px 17px;position:relative;overflow:hidden;transition:border-color .18s,box-shadow .18s}
.lk-item::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--txtm)}
.lk-item.lk-on::before{background:#00A96E}
.lk-item.lk-pause::before{background:#E07A00}
.lk-item.lk-off::before{background:#9CA3AF}
.lk-item:hover{border-color:#BFD3FF;box-shadow:0 10px 22px -14px rgba(15,32,74,.4)}
.lk-main{min-width:0}
.lk-head{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.lk-code{display:inline-flex;align-items:center;gap:7px;max-width:100%;padding:4px 9px;border:1px dashed #BFD3FF;border-radius:8px;background:#F5F9FF;color:var(--p);font-family:var(--mono);font-size:12.5px;font-weight:600;cursor:pointer;transition:all .18s}
.lk-code span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.lk-code svg{width:13px;height:13px;flex-shrink:0;opacity:.6}
.lk-code:hover{background:var(--p);color:#fff;border-color:var(--p)}
.lk-code:hover svg{opacity:1}
.copy-tip{font-size:10.5px;font-weight:800;color:#046C4A;background:#DCFCE7;padding:3px 8px;border-radius:7px}
.lk-url{font-size:11.5px;color:var(--txtm);margin-top:7px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.lk-stats{display:flex;gap:22px;flex-shrink:0}
.lk-stats>div{display:flex;flex-direction:column;gap:2px;min-width:62px}
.lk-stats .k{font-size:10.5px;color:var(--txtm);font-weight:600;white-space:nowrap}
.lk-stats .v{font-family:var(--fonth);font-size:15px;font-weight:800;color:var(--pd);letter-spacing:-.02em;white-space:nowrap}
.lk-stats .v.ok{color:var(--ok)}
.lk-stats .v.sm{font-size:12.5px;font-weight:700;color:var(--txtl)}
.lk-acts{display:flex;gap:7px;flex-shrink:0}
.lk-btn{padding:7px 13px;border:1px solid var(--brd);border-radius:9px;background:#fff;color:var(--txtl);font-family:var(--font);font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;transition:all .18s}
.lk-btn:hover{border-color:var(--p);color:var(--p);background:#F3F7FF}
.lk-btn-p{background:var(--p);border-color:var(--p);color:#fff}
.lk-btn-p:hover{background:#1748CC;border-color:#1748CC;color:#fff}
.lk-empty{text-align:center;padding:38px 14px;color:var(--txtm)}
.lk-empty svg{width:44px;height:44px;color:#CBD5E9;margin-bottom:10px}
.lk-empty b{display:block;font-family:var(--fonth);font-size:14.5px;color:var(--txtl);font-weight:800;margin-bottom:3px}
.lk-empty small{font-size:12px}
.lk-pag{display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin-top:16px}
.lk-pag .pg{display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;padding:0 10px;border:1px solid var(--brd);border-radius:9px;font-size:13px;font-weight:700;color:var(--txtl);background:#fff;text-decoration:none;transition:all .18s}
.lk-pag .pg:hover{border-color:var(--p);color:var(--p)}
.lk-pag .pg.on{background:var(--p);color:#fff;border-color:var(--p)}
.lk-pag .pg.off{opacity:.4;pointer-events:none}
.lk-pag .pg-dots{display:inline-flex;align-items:center;padding:0 6px;color:var(--txtm)}
.lk-pag-note{text-align:center;font-size:11px;color:var(--txtm);margin-top:8px;font-weight:600}

/* ── Tab API ── */
.api-token{position:relative;overflow:hidden;border-radius:var(--rad);padding:20px 22px;margin-bottom:18px;background:linear-gradient(125deg,#0A1633 0%,#132A63 55%,#0F3BD6 100%);color:#fff;box-shadow:0 16px 36px -20px rgba(10,22,51,.9)}
.api-token::before{content:'';position:absolute;right:-70px;top:-90px;width:250px;height:250px;border-radius:50%;border:1px solid rgba(255,255,255,.14)}
.api-token-h{position:relative;z-index:2;display:flex;align-items:center;gap:12px;margin-bottom:15px}
.api-token-h i{width:38px;height:38px;border-radius:12px;background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.api-token-h i svg{width:19px;height:19px}
.api-token-h b{display:block;font-family:var(--fonth);font-size:15.5px;font-weight:800;letter-spacing:-.01em;line-height:1.3}
.api-token-h>div span{display:block;font-size:11.5px;color:rgba(255,255,255,.6);font-weight:500}
.api-token-row{position:relative;z-index:2;display:flex;gap:8px;flex-wrap:wrap}
.api-token-row input{flex:1;min-width:200px;padding:12px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.22);background:rgba(255,255,255,.1);color:#fff;font-family:var(--mono);font-size:13px;font-weight:600;letter-spacing:.02em}
.api-token-row input:focus{border-color:#fff;box-shadow:none}
.api-btn{padding:12px 18px;border-radius:10px;border:1px solid rgba(255,255,255,.28);background:rgba(255,255,255,.1);color:#fff;font-family:var(--font);font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;transition:background .18s}
.api-btn:hover{background:rgba(255,255,255,.22)}
.api-btn-new{background:#fff;color:var(--p);border-color:#fff}
.api-btn-new:hover{background:#E7EFFF}
.api-warn{position:relative;z-index:2;display:flex;align-items:flex-start;gap:8px;margin-top:13px;font-size:12px;line-height:1.55;color:#FFD79A;font-weight:600}
.api-warn svg{width:15px;height:15px;flex-shrink:0;margin-top:1px}

.api-m{padding:20px 22px}
.api-m-h{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:9px}
.api-m-h em{width:24px;height:24px;border-radius:8px;background:var(--p);color:#fff;font-style:normal;font-family:var(--fonth);font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.api-m-h b{font-family:var(--fonth);font-size:16px;font-weight:800;color:var(--pd);letter-spacing:-.015em}
.api-tag{padding:3px 9px;border-radius:7px;font-size:10.5px;font-weight:800}
.api-tag-g{background:#DCFCE7;color:#046C4A}
.api-tag-b{background:#E3EBFF;color:#1743B8}
.api-tag-p{background:#F0EAFF;color:#5B33D6}
.api-m-p{font-size:13px;color:var(--txtl);line-height:1.65;margin-bottom:13px}
.api-m-p code{font-family:var(--mono);font-size:11.5px;background:#EEF2FA;color:var(--pd);padding:2px 6px;border-radius:5px;font-weight:600}

.api-code{position:relative;background:#0B1330;border:1px solid #1E2C57;border-radius:12px;padding:15px 15px 15px 15px;overflow-x:auto}
.api-code code{display:block;font-family:var(--mono);font-size:11.5px;line-height:1.8;color:#8FC7FF;word-break:break-all}
.api-code .cp{position:absolute;top:9px;right:9px;z-index:2;padding:5px 11px;border-radius:7px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.1);color:#DCE7FF;font-family:var(--font);font-size:11px;font-weight:700;cursor:pointer;transition:all .18s}
.api-code .cp:hover{background:var(--p);border-color:var(--p);color:#fff}
.api-code:has(.cp) code{padding-right:62px}
.api-mth{display:inline-block;background:#1E5EFF;color:#fff;font-size:10px;font-weight:800;padding:1px 7px;border-radius:5px;margin-right:7px;vertical-align:1px;letter-spacing:.05em}
.api-code-res code{color:#5DE3A8}
.api-code-block code{white-space:pre-wrap;color:#D6E2FF}
.api-res-l{font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--txtm);margin:14px 0 7px}

/* ── Tab Tài khoản ── */
.acc-profile{display:flex;align-items:center;justify-content:space-between;gap:22px;flex-wrap:wrap;background:var(--card);border:1px solid var(--brd);border-radius:var(--rad);padding:20px 22px;margin-bottom:18px;box-shadow:0 1px 2px rgba(15,32,74,.04)}
.acc-id{display:flex;align-items:center;gap:15px;min-width:0}
.acc-ava{width:60px;height:60px;border-radius:18px;background:linear-gradient(135deg,var(--p),var(--a));color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--fonth);font-size:25px;font-weight:800;flex-shrink:0;box-shadow:0 10px 22px -8px rgba(30,94,255,.7)}
.acc-id-t{min-width:0}
.acc-id-t b{font-family:var(--fonth);font-size:19px;font-weight:800;color:var(--pd);letter-spacing:-.02em;line-height:1.2;margin-right:8px}
.acc-user{font-family:var(--mono);font-size:12.5px;color:var(--txtm);font-weight:600}
.acc-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:9px}
.acc-chip{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:99px;background:#F1F5FF;color:var(--txtl);font-size:11px;font-weight:700}
.acc-chip svg{width:12px;height:12px;flex-shrink:0}
.acc-chip-role{background:#E3EBFF;color:#1743B8}
.acc-chip-ok{background:#DCFCE7;color:#046C4A}
.acc-chip-warn{background:#FEF3C7;color:#92400E}
.acc-nums{display:flex;gap:26px;flex-wrap:wrap}
.acc-nums>div{display:flex;flex-direction:column;gap:2px;min-width:72px}
.acc-nums .k{font-size:10.5px;color:var(--txtm);font-weight:600;white-space:nowrap}
.acc-nums .v{font-family:var(--fonth);font-size:19px;font-weight:800;color:var(--pd);letter-spacing:-.025em;white-space:nowrap}
.acc-nums .v.ok{color:var(--ok)}

.acc-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start}
.acc-f{margin-bottom:14px}
.acc-f label{display:block;font-size:11.5px;font-weight:700;color:var(--txtl);margin-bottom:6px}
.acc-in{position:relative}
.acc-in svg{position:absolute;left:13px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--txtm);pointer-events:none;transition:color .18s}
.acc-in input{width:100%;padding:12px 14px 12px 39px;border:1px solid var(--brd);border-radius:var(--rads);background:#FAFCFF;font-family:var(--font);font-size:13.5px;color:var(--txt)}
.acc-in:focus-within svg{color:var(--p)}
.acc-btn{display:block;width:100%;padding:13px;margin-top:4px;background:linear-gradient(135deg,#1E5EFF,#3E86FF);color:#fff;border:none;border-radius:11px;font-family:var(--font);font-size:13.5px;font-weight:700;cursor:pointer;box-shadow:0 10px 22px -13px rgba(30,94,255,.9);transition:transform .18s}
.acc-btn:hover:not(:disabled){transform:translateY(-1px)}
.acc-btn:disabled{opacity:.5;cursor:not-allowed;box-shadow:none}
.acc-btn-d{background:linear-gradient(135deg,#0A1633,#22346E);box-shadow:0 10px 22px -13px rgba(10,22,51,.9)}
.acc-msg{margin-top:9px;font-size:12px;text-align:center;min-height:16px}
.acc-tip{display:flex;align-items:flex-start;gap:8px;margin-top:14px;padding-top:13px;border-top:1px solid var(--brdl);font-size:11.5px;color:var(--txtm);line-height:1.6;font-weight:500}
.acc-tip svg{width:14px;height:14px;flex-shrink:0;margin-top:2px;color:var(--p)}

@media(max-width:960px){
    .acc-profile{align-items:flex-start;flex-direction:column;gap:16px}
    .acc-nums{width:100%;justify-content:space-between;gap:12px}

    .lk-item{grid-template-columns:1fr;gap:12px;align-items:stretch}
    .lk-stats{gap:0;justify-content:space-between}
    .lk-stats>div{flex:1;min-width:0}
    .lk-acts{justify-content:flex-end}
}

/* ── Rút tiền ── */
.wfg{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.wfg .full{grid-column:1/-1}
.wfl{display:block;font-size:11.5px;font-weight:700;color:var(--txtl);margin-bottom:5px}
.wfi,.wfs{width:100%;padding:11px 12px;border:1px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px;background:#FAFCFF}
.wbtn{display:block;width:100%;padding:14px;background:linear-gradient(135deg,#1E5EFF,#3E86FF);color:#fff;border:none;border-radius:12px;font-family:var(--font);font-size:14px;font-weight:700;cursor:pointer;margin-top:18px;box-shadow:0 10px 22px -12px rgba(30,94,255,.9)}
.wbtn:disabled{opacity:.45;cursor:not-allowed;box-shadow:none}
.wmsg{margin-top:10px;font-size:12px;text-align:center;min-height:18px}

/* 3 ô tổng quan */
.wd-top{display:grid;grid-template-columns:repeat(3,1fr);gap:13px;margin-bottom:18px}
.wd-tile{background:var(--card);border:1px solid var(--brd);border-radius:var(--rad);padding:16px;display:flex;flex-direction:column;gap:10px}
.wd-tile .t-ic{width:36px;height:36px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.wd-tile .t-ic svg{width:18px;height:18px}
.wd-tile.t-avail .t-ic{background:#E1F8F0;color:#00A96E}
.wd-tile.t-pend .t-ic{background:#FFF2E2;color:#E07A00}
.wd-tile.t-done .t-ic{background:#E9EFFF;color:#1E5EFF}
.wd-tile .t-l{font-size:11.5px;color:var(--txtl);font-weight:600;margin-bottom:2px}
.wd-tile .t-v{font-family:var(--fonth);font-weight:800;font-size:21px;color:var(--pd);letter-spacing:-.025em;line-height:1.15}
.wd-tile .t-note{font-size:11px;color:var(--txtm);font-weight:600;margin-top:auto}
.wd-progress{margin-top:auto}
.wd-progress .bar{height:6px;border-radius:99px;background:#EDF1F9;overflow:hidden}
.wd-progress .bar i{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,#1E5EFF,#00C6FF)}
.wd-progress .txt{font-size:11px;color:var(--txtm);margin-top:6px;font-weight:600}

.wd-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:18px;align-items:start}

/* Form theo bước */
.wd-step{margin-bottom:20px}
.wd-step-h{display:flex;align-items:center;gap:9px;margin-bottom:11px}
.wd-step-h em{width:22px;height:22px;border-radius:8px;background:var(--p);color:#fff;font-style:normal;font-family:var(--fonth);font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.wd-step-h b{font-family:var(--fonth);font-size:13.5px;font-weight:800;color:var(--pd)}
.wd-amount{position:relative}
.wd-amount input{width:100%;padding:15px 46px 15px 16px;border:1.5px solid var(--brd);border-radius:12px;background:#FAFCFF;font-family:var(--fonth);font-weight:800;font-size:24px;color:var(--pd);letter-spacing:-.02em;-moz-appearance:textfield}
.wd-amount input::-webkit-outer-spin-button,.wd-amount input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.wd-amount span{position:absolute;right:16px;top:50%;transform:translateY(-50%);font-family:var(--fonth);font-weight:800;font-size:18px;color:var(--txtm);pointer-events:none}
.wd-quick{display:flex;gap:7px;flex-wrap:wrap;margin-top:10px}
.wd-quick button{padding:7px 13px;border-radius:99px;border:1px solid var(--brd);background:#fff;color:var(--txtl);font-family:var(--font);font-size:12px;font-weight:700;cursor:pointer;transition:all .18s}
.wd-quick button:hover:not(:disabled){border-color:var(--p);color:var(--p);background:#F3F7FF}
.wd-quick button:disabled{opacity:.4;cursor:not-allowed}
.wd-hint{font-size:11.5px;color:var(--txtm);margin-top:9px;font-weight:600}
.wd-hint b{color:var(--txtl);font-weight:800}

.wd-methods{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.wd-method{display:flex;align-items:center;gap:11px;padding:13px;border:1.5px solid var(--brd);border-radius:12px;cursor:pointer;transition:all .18s;background:#fff;position:relative}
.wd-method input{position:absolute;opacity:0;width:0;height:0;pointer-events:none}
.wd-method .m-ic{width:36px;height:36px;border-radius:11px;background:#F1F5FF;color:var(--txtl);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .18s}
.wd-method .m-ic svg{width:18px;height:18px}
.wd-method .m-t{display:block;font-family:var(--fonth);font-weight:800;font-size:13.5px;color:var(--pd);line-height:1.25}
.wd-method .m-s{display:block;font-size:11px;color:var(--txtm);font-weight:600}
.wd-method:hover{border-color:#BFD3FF}
.wd-method.on{border-color:var(--p);background:#F4F8FF;box-shadow:0 0 0 3px rgba(30,94,255,.1)}
.wd-method.on .m-ic{background:var(--p);color:#fff}

.wd-safe{display:flex;align-items:flex-start;gap:8px;margin-top:12px;font-size:11.5px;color:var(--txtm);line-height:1.5;font-weight:600}
.wd-safe svg{width:14px;height:14px;flex-shrink:0;margin-top:2px;color:var(--p)}

/* Lịch sử dạng thẻ thay cho bảng 8 cột */
.wd-list{display:flex;flex-direction:column;gap:10px}
.wdi{position:relative;overflow:hidden;border:1px solid var(--brd);border-radius:12px;background:#fff;padding:13px 14px 13px 17px}
.wdi::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--txtm)}
.wdi-pending::before{background:#E07A00}
.wdi-approved::before{background:#1E5EFF}
.wdi-completed::before{background:#00A96E}
.wdi-rejected::before,.wdi-refunded::before{background:#E0364B}
.wdi-cancelled::before{background:#9CA3AF}
.wdi-top{display:flex;align-items:center;justify-content:space-between;gap:10px}
.wdi-amount{font-family:var(--fonth);font-weight:800;font-size:16px;color:var(--pd);letter-spacing:-.02em}
.wdi-meta{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;font-size:12px;color:var(--txtl);margin-top:7px;word-break:break-all}
.wdi-tag{background:#F1F5FF;color:var(--p);font-weight:700;font-size:10.5px;padding:2px 8px;border-radius:6px;flex-shrink:0}
.wdi-foot{font-size:11px;color:var(--txtm);margin-top:7px;font-weight:600}
.wdi-note{margin-top:9px;padding:8px 10px;border-radius:8px;background:#F6F8FD;font-size:12px;color:var(--txtl);line-height:1.5}
.wd-empty{text-align:center;padding:36px 14px;color:var(--txtm)}
.wd-empty svg{width:42px;height:42px;color:#CBD5E9;margin-bottom:10px}
.wd-empty b{display:block;font-family:var(--fonth);font-size:14px;color:var(--txtl);font-weight:800;margin-bottom:3px}
.wd-empty small{font-size:12px}

.wd-legend{display:grid;grid-template-columns:repeat(3,1fr);gap:11px 20px}
.wd-legend>div{display:flex;gap:9px;align-items:flex-start;font-size:12.5px;color:var(--txtl);line-height:1.5}
.wd-legend .badge{flex-shrink:0;margin-top:1px;min-width:76px;justify-content:center}

/* Referral */
.ref-box{position:relative;overflow:hidden;background:linear-gradient(118deg,#0B31BE,#1E5EFF 50%,#00A6FF);border:none;border-radius:20px;padding:28px 24px;text-align:center;margin-bottom:18px;color:#fff;box-shadow:0 16px 36px -18px rgba(11,49,190,.85)}
.ref-box::before{content:'';position:absolute;right:-60px;bottom:-130px;width:250px;height:250px;border-radius:50%;border:1.5px solid rgba(255,255,255,.22)}
.ref-box h3{position:relative;font-family:var(--fonth);font-size:21px;font-weight:800;color:#fff;margin-bottom:2px}
.ref-pct{position:relative;font-family:var(--fonth);font-weight:800;font-size:54px;line-height:1;color:#fff;margin-bottom:4px;letter-spacing:-.03em}
.ref-box p{position:relative;color:rgba(255,255,255,.85)!important}
.ref-link{position:relative;margin-top:18px;display:flex;gap:8px;max-width:520px;margin-left:auto;margin-right:auto}
.ref-link input{flex:1;padding:11px 14px;border:1px solid rgba(255,255,255,.4);border-radius:var(--rads);font-family:var(--mono);font-size:13px;color:#fff;background:rgba(255,255,255,.14)}
.ref-link input:focus{box-shadow:none;border-color:#fff}
.ref-link button{padding:11px 20px;background:#fff;color:var(--p);border:none;border-radius:var(--rads);font-weight:800;cursor:pointer;font-family:var(--font)}

.toast-box{position:fixed;top:70px;right:20px;z-index:10000;display:flex;flex-direction:column;gap:8px}
.toast{padding:12px 18px;border-radius:12px;font-size:13px;font-weight:600;color:#fff;box-shadow:0 12px 28px -12px rgba(15,32,74,.7);animation:sr .3s ease;min-width:240px}
.t-ok{background:var(--ok)}.t-err{background:var(--err)}
@keyframes sr{from{opacity:0;transform:translateX(60px)}to{opacity:1;transform:translateX(0)}}

/* Announcements */
.ann-section{margin-bottom:18px}
.ann-header{display:flex;align-items:center;gap:8px;margin-bottom:12px;font-family:var(--fonth);font-size:16px;font-weight:800;color:var(--pd)}
.ann-header svg{flex-shrink:0}
.ann-item{background:var(--card);border-radius:var(--rad);border:1px solid var(--brd);padding:18px 20px;margin-bottom:12px;border-left:4px solid var(--info);box-shadow:0 1px 2px rgba(15,32,74,.04)}
.ann-item.ann-warning{border-left-color:var(--warn)}
.ann-item.ann-success{border-left-color:var(--ok)}
.ann-item .ann-title{display:flex;align-items:center;gap:8px;font-weight:800;font-size:14px;color:var(--pd);margin-bottom:6px}
.ann-item .ann-title .ann-icon{width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
.ann-item.ann-info .ann-icon{background:#E3EBFF;color:var(--info)}
.ann-item.ann-warning .ann-icon{background:#FEF3C7;color:var(--warn)}
.ann-item.ann-success .ann-icon{background:#DCFCE7;color:var(--ok)}
.ann-badge-new{display:inline-flex;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:800;background:var(--info);color:#fff;text-transform:uppercase;letter-spacing:.05em}
.ann-item .ann-body{font-size:13px;color:var(--txt);line-height:1.7;margin-bottom:8px}
.ann-item .ann-time{font-size:11px;color:var(--txtm);display:flex;align-items:center;gap:4px}

@media(max-width:768px){
    .sg6{grid-template-columns:repeat(2,1fr)}
    .wfg{grid-template-columns:1fr}
    .brow{gap:16px}
    .lk-create{padding:16px}
    .lk-create-row{flex-direction:column}
    .lk-create-btn{justify-content:center}
    .lk-adv{grid-template-columns:1fr}
    .lk-acts .lk-btn{flex:1;text-align:center}
    .api-token{padding:16px}
    .api-m{padding:16px}
    .api-btn{flex:1;text-align:center}
    .wd-grid,.acc-grid{grid-template-columns:1fr!important}
    .acc-nums{display:grid;grid-template-columns:1fr 1fr;gap:14px 12px}
    .acc-nums>div{min-width:0}
    .wd-top{grid-template-columns:1fr}
    .wd-methods{grid-template-columns:1fr}
    .wd-legend{grid-template-columns:1fr}
    .wd-amount input{font-size:21px;padding:14px 42px 14px 14px}
    .ud-chart-container{height:230px}
    .rules-list{grid-template-columns:1fr}
    .sc{flex-direction:row;align-items:center;gap:11px;padding:13px}
    .sc-icon{width:36px;height:36px;border-radius:11px}
    .sc .sv{font-size:18px}
    .sc .sl{font-size:11px}
    .dash-stats .sc:last-child{grid-column:1/-1}
    .card{padding:18px}
    .toast-box{top:64px;right:12px;left:12px}
    .toast{min-width:0}
}
</style>
</head>
<body<?php echo is_admin_bar_showing() ? ' class="admin-bar"' : ''; ?>>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <?php $sb_icon = get_option('sitetop_widget_icon',''); ?>
    <a href="<?php echo home_url(); ?>" class="sidebar-logo">
        <img src="<?php echo esc_url( $sb_icon ?: sitetop_logo_url('sitetop-logo.png') ); ?>" width="22" height="22" alt="" style="border-radius:50%">
        <span class="lg-chip"><span class="lgd">SITE</span><span class="lgb">TOP</span></span>
    </a>
    <div class="sidebar-user">
        <div class="sidebar-user-info">
            <div class="sidebar-avatar"><?php echo strtoupper(substr($user->display_name,0,1)); ?></div>
            <div>
                <div class="sidebar-name"><?php echo esc_html($user->display_name); ?></div>
                <div class="sidebar-role">Publisher</div>
            </div>
        </div>
    </div>
    <div class="sidebar-sec">Menu</div>
    <nav class="sidebar-nav">
        <a class="sidebar-nav-item on" data-t="overview">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>
            T&#7893;ng quan
        </a>
        <a class="sidebar-nav-item" data-t="links">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            Links c&#7911;a t&#244;i
        </a>
        <a class="sidebar-nav-item" data-t="withdraw">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            R&#250;t ti&#7873;n
        </a>
        <?php if ( sitetop_get_option('referral_enabled', 0) ) : ?>
        <a class="sidebar-nav-item" data-t="referral">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Referral
        </a>
        <?php endif; ?>
        <a class="sidebar-nav-item" data-t="api">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            API
        </a>
        <a class="sidebar-nav-item" data-t="account">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            T&#224;i kho&#7843;n
        </a>
    </nav>
    <div class="sidebar-bottom">
        <a href="<?php echo home_url(); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Trang ch&#7911;
        </a>
        <a href="<?php echo wp_logout_url(home_url()); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            &#272;&#259;ng xu&#7845;t
        </a>
    </div>
</aside>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Mobile top bar -->
<div class="mobile-topbar">
    <?php $ln_icon = get_option('sitetop_widget_icon',''); ?>
    <a href="<?php echo home_url(); ?>" class="mobile-topbar-logo">
        <img src="<?php echo esc_url( $ln_icon ?: sitetop_logo_url('sitetop-logo.png') ); ?>" width="20" height="20" alt="" style="vertical-align:middle;border-radius:50%">
        <span><span class="lgd">SITE</span><span class="lgb">TOP</span></span>
    </a>
    <div class="mobile-topbar-right">
        <span class="bal"><?php echo sitetop_format_money($balance); ?></span>
        <span class="avatar"><?php echo strtoupper(substr($user->display_name,0,1)); ?></span>
        <a href="<?php echo wp_logout_url(home_url()); ?>" style="color:var(--txtm);display:flex" title="Đăng xuất"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
    </div>
</div>

<!-- Bottom navigation (mobile) -->
<nav class="bottom-nav">
    <div class="bottom-nav-inner">
        <a class="bottom-nav-item on" data-t="overview">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>
            <span>T&#7893;ng quan</span>
        </a>
        <a class="bottom-nav-item" data-t="links">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            <span>Links</span>
        </a>
        <a class="bottom-nav-item" data-t="withdraw">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <span>R&#250;t ti&#7873;n</span>
        </a>
        <a class="bottom-nav-item" data-t="api">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            <span>API</span>
        </a>
        <a class="bottom-nav-item" data-t="account">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>T&#224;i kho&#7843;n</span>
        </a>
    </div>
</nav>

<!-- Main content area -->
<div class="main-wrap">
    <div class="main-topbar">
        <span class="main-topbar-title" id="mainTopbarTitle">T&#7893;ng quan</span>
        <span class="topbar-date">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            <?php echo date_i18n( 'd/m/Y', strtotime( sitetop_current_time() ) ); ?>
        </span>
    </div>
    <div class="main-content">

    <!-- Th&#7867; v&#237;: s&#7889; d&#432; + thao t&#225;c nhanh -->
    <div class="wallet">
        <div class="wallet-l">
            <div class="wallet-lb">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
                S&#7889; d&#432; kh&#7843; d&#7909;ng
            </div>
            <div class="wallet-v"><?php echo sitetop_format_money($balance); ?></div>
            <div class="wallet-meta">
                <span class="wallet-chip">H&#244;m nay <b>+<?php echo sitetop_format_money($today_earned); ?></b></span>
                <span class="wallet-chip">T&#7893;ng thu nh&#7853;p <b><?php echo sitetop_format_money($total_earned); ?></b></span>
                <span class="wallet-chip">R&#250;t t&#7889;i thi&#7875;u <b><?php echo sitetop_format_money($min_wd); ?></b></span>
            </div>
        </div>
        <div class="wallet-r">
            <button type="button" class="wbtn-w" onclick="switchTab('links')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                T&#7841;o link m&#7899;i
            </button>
            <button type="button" class="wbtn-g" onclick="switchTab('withdraw')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v13"/><path d="M7 12l5 5 5-5"/><path d="M4 21h16"/></svg>
                R&#250;t ti&#7873;n
            </button>
        </div>
    </div>

    <!-- Stats grid -->
    <div class="dash-stats">
        <div class="sc s1">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></div>
            <div class="sc-text"><div class="sl">T&#7893;ng links</div><div class="sv"><?php echo number_format($total_links); ?></div></div>
        </div>
        <div class="sc s5">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
            <div class="sc-text"><div class="sl">T&#7893;ng views</div><div class="sv"><?php echo number_format($total_completed); ?></div><div class="ss">H&#244;m nay <b>+<?php echo number_format($today_completed); ?></b></div></div>
        </div>
        <div class="sc s3">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <div class="sc-text"><div class="sl">T&#7893;ng thu nh&#7853;p</div><div class="sv"><?php echo sitetop_format_money($total_earned); ?></div><div class="ss">H&#244;m nay <b>+<?php echo sitetop_format_money($today_earned); ?></b></div></div>
        </div>
        <div class="sc s6">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 16v3a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h3"/><path d="M8 12l4 4 8-8"/></svg></div>
            <div class="sc-text"><div class="sl">&#272;&#227; r&#250;t</div><div class="sv"><?php echo sitetop_format_money($total_withdrawn); ?></div></div>
        </div>
        <div class="sc s2">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
            <div class="sc-text"><div class="sl">&#272;ang ch&#7901; r&#250;t</div><div class="sv"><?php echo sitetop_format_money($pending_wd); ?></div></div>
        </div>
    </div>

<!-- ═══ OVERVIEW ═══ -->
<div class="pane on" id="p-overview">

<!-- Announcements -->
<div class="ann-section" id="userAnnouncements" style="display:none"></div>

<div class="card">
<div class="card-h">
    <h3 style="margin:0">Biểu đồ 30 ngày gần nhất</h3>
    <div class="ud-chart-legend">
        <span class="lg-views">Views</span>
        <span class="lg-earned">Kiếm được</span>
    </div>
</div>
<div class="ud-chart-container">
    <canvas id="udChart"></canvas>
</div>
</div>

<div class="rules">
    <div class="rules-h">
        <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg></i>
        ĐỌC QUA QUY ĐỊNH CHUNG TRƯỚC KHI LÀM
    </div>
    <p class="rules-sub">Để đảm bảo quyền lợi cho tất cả người dùng, vui lòng tuân thủ các quy định sau từ của hệ thống.</p>
    <ul class="rules-list">
        <li><em>1</em><span>Mỗi người chỉ được sở hữu <b>01 tài khoản</b>. Nghiêm cấm tạo nhiều tài khoản hoặc dùng chung.</span></li>
        <li><em>2</em><span>Nghiêm cấm sử dụng <b>VPN, Proxy, tool auto</b> hoặc bất kỳ hình thức gian lận nào.</span></li>
        <li><em>3</em><span>Nghiêm cấm rút gọn trùng lặp: mỗi link gốc chỉ được tạo <b>01 shortlink</b>, không rút gọn 2 lần trở lên trên cùng một link gốc.</span></li>
        <li><em>4</em><span>Chỉ chia sẻ link qua các kênh hợp pháp. <b>Không spam</b>, lừa đảo, ép click hoặc tự click.</span></li>
        <?php
        /* Kẹp đúng như sitetop_ip_view_quota(): trần thật là 2, option trên production có
           thể còn giá trị cũ (5) từ đời trước — hiển thị thẳng option ra là hứa với user
           một con số hệ thống không bao giờ trả. */
        $ip_limit_show = (int) sitetop_get_option( 'shortlink_ip_limit_24h', 2 );
        if ( $ip_limit_show < 1 || $ip_limit_show > 2 ) { $ip_limit_show = 2; }
        ?>
        <li><em>5</em><span>Mỗi lượt truy cập hợp lệ được tính 01 lần (tối đa <b><?php echo $ip_limit_show; ?> view/IP trong 24 giờ</b>).</span></li>
        <li><em>6</em><span>Doanh thu có thể được kiểm duyệt trước khi thanh toán.</span></li>
        <li><em>7</em><span>Rút tiền khi đạt mức tối thiểu <b><?php echo sitetop_format_money(floatval(sitetop_get_option('min_withdrawal', 50000))); ?></b>.</span></li>
        <li><em>8</em><span>Vi phạm sẽ bị thu hồi doanh thu hoặc <b>khóa tài khoản vĩnh viễn</b> mà không cần báo trước.</span></li>
    </ul>
    <div class="rules-note">
        <i>LƯU Ý</i>
        <span><b>2 View / 1 IP</b> → 1 IP phải làm nhiệm vụ ở <b>2 shortlink khác nhau</b> mới được cộng tiền. Không phải 1 IP vượt 1 shortlink 2 lần là được tính 2 view.</span>
    </div>
</div>

</div>

<!-- ═══ LINKS ═══ -->
<div class="pane" id="p-links">

<!-- Ô tạo link: 1 dòng chính, tuỳ chọn phụ gập lại -->
<div class="lk-create">
    <div class="lk-create-h">
        <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></i>
        <div><b>R&#250;t g&#7885;n link m&#7899;i</b><span>D&#225;n link g&#7889;c, nh&#7853;n ngay link ki&#7871;m ti&#7873;n</span></div>
    </div>
    <div class="lk-create-row">
        <div class="lk-create-input">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3.6 9h16.8M3.6 15h16.8"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18z"/></svg>
            <input type="url" id="dashLongUrl" placeholder="https://example.com/your-long-url-here" autocomplete="off">
        </div>
        <button onclick="dashShorten()" class="lk-create-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            R&#250;t g&#7885;n
        </button>
    </div>
    <button type="button" class="lk-adv-t" id="lkAdvToggle" onclick="lkToggleAdv()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        Tu&#7923; ch&#7885;n n&#226;ng cao
    </button>
    <div class="lk-adv" id="lkAdv">
        <div>
            <label>Link d&#7921; ph&#242;ng</label>
            <input type="url" id="dashFallbackUrl" placeholder="https://backup-link.com">
        </div>
        <div>
            <label>B&#237; danh</label>
            <input type="text" id="dashAlias" placeholder="my-link">
        </div>
    </div>
    <div class="sf-result" id="dashResult">
        <div class="lk-result-h"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg> Link c&#7911;a b&#7841;n &#273;&#227; s&#7861;n s&#224;ng</div>
        <div class="sf-result-row">
            <input type="text" id="dashShortUrl" readonly>
            <button onclick="copyText(document.getElementById('dashShortUrl').value,this)">Copy</button>
        </div>
    </div>
</div>

<!-- Danh sách links -->
<div class="card">
<div class="card-h"><h3>Links c&#7911;a t&#244;i (<?php echo number_format($total_links); ?>)</h3></div>

<!-- Tìm kiếm shortlink cũ: gõ = lọc realtime trang hiện tại; Enter/"Tìm" = tìm backend toàn bộ links (?q=) -->
<form method="get" class="lk-search">
    <input type="hidden" name="tab" value="links">
    <div class="lk-search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="text" name="q" id="lkSearch" value="<?php echo esc_attr($lq); ?>" placeholder="T&#236;m m&#227; shortlink, full link ho&#7863;c URL g&#7889;c..." autocomplete="off" oninput="lkFilter()">
        <button type="button" id="lkClear" onclick="lkClearSearch()" title="X&#243;a t&#236;m ki&#7871;m">&times;</button>
    </div>
    <button type="submit" class="lk-search-btn">T&#236;m</button>
</form>
<?php if ($lq !== ''): ?>
<div id="lkServerInfo" class="lk-info">T&#236;m th&#7845;y <strong><?php echo number_format($links_found); ?></strong> k&#7871;t qu&#7843; cho &quot;<strong><?php echo esc_html($lq); ?></strong>&quot; — <a href="?tab=links">X&#243;a t&#236;m ki&#7871;m</a></div>
<?php endif; ?>
<div id="lkLiveInfo" class="lk-info" style="display:none"></div>

<?php if(empty($my_links)): ?>
<div class="lk-empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
    <b><?php echo $lq !== '' ? 'Kh&#244;ng t&#236;m th&#7845;y link n&#224;o' : 'Ch&#432;a c&#243; link n&#224;o'; ?></b>
    <small><?php echo $lq !== '' ? 'Kh&#244;ng c&#243; link n&#224;o kh&#7899;p &quot;' . esc_html($lq) . '&quot;.' : 'D&#225;n link g&#7889;c v&#224;o &#244; ph&#237;a tr&#234;n &#273;&#7875; t&#7841;o link &#273;&#7847;u ti&#234;n.'; ?></small>
</div>
<?php else: ?>
<div class="lk-list" id="linksListContainer">
<?php foreach($my_links as $lk):
    $short = $home.'/'.(!empty($lk->alias) ? $lk->alias : $lk->shortcode);
    $bcls = $lk->status==='active'?'b-ok':($lk->status==='paused'?'b-warn':'b-mute');
    $scls = $lk->status==='active'?'lk-on':($lk->status==='paused'?'lk-pause':'lk-off');
    $completed = isset($lk->total_completed) ? (int)$lk->total_completed : 0;
    $earnings = isset($lk->total_earnings) ? (float)$lk->total_earnings : 0;
?>
<div class="lk-item <?php echo $scls; ?>">
    <div class="lk-main">
        <div class="lk-head">
            <button type="button" class="lk-code" onclick="copyLink(this,'<?php echo esc_js($short); ?>')" title="B&#7845;m &#273;&#7875; copy">
                <span><?php echo esc_html($short); ?></span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            </button>
            <span class="badge <?php echo $bcls; ?>"><?php echo $lk->status === 'active' ? 'Ho&#7841;t &#273;&#7897;ng' : ($lk->status === 'paused' ? 'T&#7841;m d&#7915;ng' : 'T&#7855;t'); ?></span>
        </div>
        <div class="lk-url" title="<?php echo esc_attr($lk->target_url); ?>"><?php echo esc_html($lk->target_url); ?></div>
    </div>
    <div class="lk-stats">
        <div><span class="k">Ho&#224;n th&#224;nh</span><span class="v"><?php echo number_format($completed); ?></span></div>
        <div><span class="k">Ki&#7871;m &#273;&#432;&#7907;c</span><span class="v<?php echo $earnings > 0 ? ' ok' : ''; ?>"><?php echo sitetop_format_money($earnings); ?></span></div>
        <div><span class="k">Ng&#224;y t&#7841;o</span><span class="v sm"><?php echo date('d/m/Y', strtotime($lk->created_at)); ?></span></div>
    </div>
    <div class="lk-acts">
        <button type="button" class="lk-btn" onclick="openEditLink(<?php echo $lk->id; ?>,'<?php echo esc_js($lk->target_url); ?>','<?php echo esc_js($lk->fallback_url ?? ''); ?>','<?php echo esc_js($lk->alias ?? ''); ?>')">S&#7917;a</button>
        <button type="button" class="lk-btn lk-btn-p" onclick="viewLinkVisits(<?php echo $lk->id; ?>,'<?php echo esc_js($short); ?>')">Chi ti&#7871;t</button>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php if($lpg_total_pages > 1):
    $pag_range = 2;
    $pag_show = array(1);
    if ($lpg - $pag_range > 2) $pag_show[] = '...';
    for ($i = max(2, $lpg - $pag_range); $i <= min($lpg_total_pages - 1, $lpg + $pag_range); $i++) $pag_show[] = $i;
    if ($lpg + $pag_range < $lpg_total_pages - 1) $pag_show[] = '...';
    if ($lpg_total_pages > 1) $pag_show[] = $lpg_total_pages;
    $pag_show = array_values(array_unique($pag_show));
    $pag_base = '?tab=links' . ($lq !== '' ? '&q=' . urlencode($lq) : '') . '&lpg=';
?>
<div class="lk-pag">
    <a href="<?php echo $pag_base . max(1, $lpg-1); ?>" class="pg<?php if($lpg<=1) echo ' off'; ?>">&laquo;</a>
    <?php foreach($pag_show as $p): ?>
        <?php if ($p === '...'): ?>
            <span class="pg-dots">&hellip;</span>
        <?php else: ?>
            <a href="<?php echo $pag_base . $p; ?>" class="pg<?php if($p===$lpg) echo ' on'; ?>"><?php echo $p; ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
    <a href="<?php echo $pag_base . min($lpg_total_pages, $lpg+1); ?>" class="pg<?php if($lpg>=$lpg_total_pages) echo ' off'; ?>">&raquo;</a>
</div>
<div class="lk-pag-note">Trang <?php echo $lpg; ?>/<?php echo $lpg_total_pages; ?> &#8212; T&#7893;ng <?php echo number_format($total_links); ?> links</div>
<?php endif; ?>
<?php endif; ?>
</div>

<script>
// Tìm kiếm links: lọc realtime các thẻ ĐANG hiển thị (10 thẻ/trang); Enter/"Tìm" submit ?q= để
// backend tìm trong toàn bộ links của user (phân trang theo kết quả).
var LK_SERVER_Q = '<?php echo esc_js($lq); ?>';
var LK_TOTAL_LINKS = <?php echo (int) $total_links; ?>;
function lkToggleAdv(){
    var box=document.getElementById('lkAdv'), btn=document.getElementById('lkAdvToggle');
    if(!box||!btn) return;
    var open=box.classList.toggle('on');
    btn.classList.toggle('on',open);
}
function lkFilter(){
    var inp=document.getElementById('lkSearch'); if(!inp) return;
    var q=inp.value.trim().toLowerCase();
    var clr=document.getElementById('lkClear'); if(clr) clr.style.display=q?'flex':'none';
    var live=document.getElementById('lkLiveInfo');
    var cont=document.getElementById('linksListContainer');
    if(!cont){ if(live) live.style.display='none'; return; }
    var rows=cont.querySelectorAll('.lk-item'), shown=0;
    for(var i=0;i<rows.length;i++){
        var codeEl=rows[i].querySelector('.lk-code'), urlEl=rows[i].querySelector('.lk-url');
        var shortTxt=(codeEl?codeEl.textContent:'').toLowerCase();
        var origTxt=(((urlEl&&urlEl.getAttribute('title'))||'')+' '+((urlEl&&urlEl.textContent)||'')).toLowerCase();
        var hit=!q||shortTxt.indexOf(q)>=0||origTxt.indexOf(q)>=0;
        rows[i].style.display=hit?'':'none';
        if(hit) shown++;
    }
    if(live){
        if(q&&q!==LK_SERVER_Q.toLowerCase()){
            live.style.display='';
            live.innerHTML='Lọc nhanh: <strong>'+shown+'</strong> kết quả trên trang này — nhấn <strong>Enter</strong> hoặc "Tìm" để tìm trong toàn bộ '+LK_TOTAL_LINKS.toLocaleString('vi-VN')+' links';
        }else{
            live.style.display='none';
        }
    }
}
function lkClearSearch(){
    if(LK_SERVER_Q){ window.location='?tab=links'; return; } // đang lọc backend → về danh sách đầy đủ
    var inp=document.getElementById('lkSearch');
    if(inp){ inp.value=''; lkFilter(); inp.focus(); }
}
lkFilter();
</script>
</div>

<!-- ═══ WITHDRAW ═══ -->
<div class="pane" id="p-withdraw">
<?php
    $wd_ready   = $balance >= $min_wd;
    $wd_pct     = $min_wd > 0 ? min( 100, ( $balance / $min_wd ) * 100 ) : 100;
    $saved_bank    = get_user_meta($user_id, 'sitetop_bank_name', true);
    $saved_account = get_user_meta($user_id, 'sitetop_bank_account', true);
    $saved_holder  = get_user_meta($user_id, 'sitetop_bank_holder', true);
    // Mốc rút nhanh suy ra từ mức tối thiểu, bỏ mốc trùng
    $wd_quick = array();
    foreach ( array( 1, 2, 5, 10 ) as $m ) {
        $q = $min_wd * $m;
        if ( $q > 0 && ! in_array( $q, $wd_quick, true ) ) $wd_quick[] = $q;
    }
?>

<!-- Tổng quan ví rút tiền -->
<div class="wd-top">
    <div class="wd-tile t-avail">
        <div class="t-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg></div>
        <div><div class="t-l">C&#243; th&#7875; r&#250;t</div><div class="t-v"><?php echo sitetop_format_money($balance); ?></div></div>
        <div class="wd-progress">
            <div class="bar"><i style="width:<?php echo round($wd_pct); ?>%"></i></div>
            <div class="txt"><?php echo $wd_ready
                ? 'Đủ điều kiện rút tiền'
                : 'Cần thêm ' . sitetop_format_money( $min_wd - $balance ) . ' để đạt mức tối thiểu'; ?></div>
        </div>
    </div>
    <div class="wd-tile t-pend">
        <div class="t-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
        <div><div class="t-l">&#272;ang ch&#7901; duy&#7879;t</div><div class="t-v"><?php echo sitetop_format_money($pending_wd); ?></div></div>
        <div class="t-note">Y&#234;u c&#7847;u &#273;&#227; g&#7917;i, ch&#432;a thanh to&#225;n</div>
    </div>
    <div class="wd-tile t-done">
        <div class="t-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></div>
        <div><div class="t-l">&#272;&#227; r&#250;t th&#224;nh c&#244;ng</div><div class="t-v"><?php echo sitetop_format_money($total_withdrawn); ?></div></div>
        <div class="t-note">T&#7893;ng c&#7897;ng t&#7915; tr&#432;&#7899;c &#273;&#7871;n nay</div>
    </div>
</div>

<div class="wd-grid">

<!-- Form yêu cầu rút tiền -->
<div class="card">
<div class="card-h"><h3>T&#7841;o y&#234;u c&#7847;u r&#250;t ti&#7873;n</h3></div>
<form id="wdForm">

<div class="wd-step">
    <div class="wd-step-h"><em>1</em><b>S&#7889; ti&#7873;n mu&#7889;n r&#250;t</b></div>
    <div class="wd-amount">
        <input type="number" id="wdAmount" name="amount" min="<?php echo $min_wd; ?>" max="<?php echo $balance; ?>" placeholder="0" required>
        <span>&#273;</span>
    </div>
    <div class="wd-quick">
        <?php foreach ( $wd_quick as $q ) : ?>
        <button type="button" onclick="wdSetAmount(<?php echo (int) $q; ?>)" <?php echo $q > $balance ? 'disabled' : ''; ?>><?php echo sitetop_format_money($q); ?></button>
        <?php endforeach; ?>
        <button type="button" onclick="wdSetAmount(<?php echo (int) $balance; ?>)" <?php echo ! $wd_ready ? 'disabled' : ''; ?>>To&#224;n b&#7897; s&#7889; d&#432;</button>
    </div>
    <div class="wd-hint">T&#7889;i thi&#7875;u <b><?php echo sitetop_format_money($min_wd); ?></b> &#183; T&#7889;i &#273;a <b><?php echo sitetop_format_money($balance); ?></b></div>
</div>

<div class="wd-step">
    <div class="wd-step-h"><em>2</em><b>Ph&#432;&#417;ng th&#7913;c nh&#7853;n ti&#7873;n</b></div>
    <div class="wd-methods">
        <label class="wd-method on" onclick="wdPickMethod(this)">
            <input type="radio" name="method" value="bank" checked>
            <span class="m-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10l9-6 9 6"/><path d="M5 10v9M9 10v9M15 10v9M19 10v9"/><path d="M3 21h18"/></svg></span>
            <span><span class="m-t">Ng&#226;n h&#224;ng</span><span class="m-s">Chuy&#7875;n kho&#7843;n n&#7897;i &#273;&#7883;a</span></span>
        </label>
        <label class="wd-method" onclick="wdPickMethod(this)">
            <input type="radio" name="method" value="usdt">
            <span class="m-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 9h8"/><path d="M12 9v8"/></svg></span>
            <span><span class="m-t">USDT</span><span class="m-s">M&#7841;ng BEP20</span></span>
        </label>
    </div>
</div>

<div class="wd-step">
    <div class="wd-step-h"><em>3</em><b>Th&#244;ng tin nh&#7853;n ti&#7873;n</b></div>
    <div class="wfg">
        <div id="wdBankName" class="wd-bank-field full"><label class="wfl">Ng&#226;n h&#224;ng</label><input class="wfi" name="bank_name" required placeholder="Nh&#7853;p t&#234;n ng&#226;n h&#224;ng" value="<?php echo esc_attr($saved_bank); ?>"></div>
        <div id="wdBankAccount" class="wd-bank-field"><label class="wfl">S&#7889; t&#224;i kho&#7843;n</label><input class="wfi" name="bank_account" required placeholder="Ch&#7881; nh&#7853;p s&#7889;" value="<?php echo esc_attr($saved_account); ?>"></div>
        <div id="wdBankHolder" class="wd-bank-field"><label class="wfl">Ch&#7911; t&#224;i kho&#7843;n</label><input class="wfi" name="bank_holder" required placeholder="H&#7884; V&#192; T&#202;N ( In hoa )" value="<?php echo esc_attr($saved_holder); ?>"></div>
        <div id="wdWallet" class="wd-usdt-field full" style="display:none"><label class="wfl">&#272;&#7883;a ch&#7881; v&#237; (BEP20)</label><input class="wfi" name="wallet_address" placeholder="0x..."></div>
    </div>
</div>

<button type="submit" class="wbtn" <?php echo ! $wd_ready ? 'disabled' : ''; ?>>
    <?php echo $wd_ready ? 'G&#7917;i y&#234;u c&#7847;u r&#250;t ti&#7873;n' : 'Ch&#432;a &#273;&#7911; m&#7913;c r&#250;t t&#7889;i thi&#7875;u'; ?>
</button>
<div class="wmsg" id="wdMsg"></div>
<div class="wd-safe">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    Ki&#7875;m tra k&#7929; th&#244;ng tin tr&#432;&#7899;c khi g&#7917;i &#8212; y&#234;u c&#7847;u &#273;&#227; g&#7917;i kh&#244;ng t&#7921; s&#7917;a &#273;&#432;&#7907;c.
</div>
</form>
</div>

<!-- Lịch sử -->
<div class="card">
<div class="card-h"><h3>L&#7883;ch s&#7917; r&#250;t ti&#7873;n</h3></div>
<div class="wd-list" id="wdListContainer">
<?php if ( empty($withdrawals) ) : ?>
<div class="wd-empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
    <b>Ch&#432;a c&#243; y&#234;u c&#7847;u n&#224;o</b>
    <small>C&#225;c l&#7847;n r&#250;t ti&#7873;n c&#7911;a b&#7841;n s&#7869; hi&#7875;n &#7903; &#273;&#226;y.</small>
</div>
<?php else : ?>
<?php foreach ( $withdrawals as $w ) { echo sitetop_render_withdrawal_item( $w ); } ?>
<?php endif; ?>
</div>
<?php if(count($withdrawals) >= 10): ?>
<button type="button" class="load-more-btn" data-type="withdrawals" data-offset="10" data-target="wdListContainer" style="padding:10px 24px;background:#F5F8FE;border:1px solid var(--brd);border-radius:var(--rads);font-size:13px;font-weight:600;cursor:pointer;display:block;width:100%;margin-top:12px;color:var(--txtl);font-family:var(--font)">Xem th&#234;m</button>
<?php endif; ?>
</div>

</div>

<!-- Giải thích trạng thái -->
<div class="card">
<div class="card-h"><h3>C&#225;c tr&#7841;ng th&#225;i y&#234;u c&#7847;u</h3></div>
<div class="wd-legend">
    <div><span class="badge b-warn">Ch&#7901; duy&#7879;t</span><span>Y&#234;u c&#7847;u &#273;ang &#273;&#432;&#7907;c ki&#7875;m tra.</span></div>
    <div><span class="badge b-info">&#272;&#227; duy&#7879;t</span><span>&#272;&#227; ph&#234; duy&#7879;t, &#273;ang ch&#7901; chuy&#7875;n ti&#7873;n.</span></div>
    <div><span class="badge b-ok">Ho&#224;n th&#224;nh</span><span>&#272;&#227; chuy&#7875;n th&#224;nh c&#244;ng &#273;&#7871;n b&#7841;n.</span></div>
    <div><span class="badge b-err">T&#7915; ch&#7889;i</span><span>Kh&#244;ng h&#7907;p l&#7879; &#8212; ti&#7873;n &#273;&#227; ho&#224;n v&#7873; s&#7889; d&#432;.</span></div>
    <div><span class="badge b-mute">&#272;&#227; hu&#7927;</span><span>Y&#234;u c&#7847;u b&#7883; hu&#7927;, kh&#244;ng ho&#224;n ti&#7873;n.</span></div>
    <div><span class="badge b-err">Ho&#224;n ti&#7873;n</span><span>Kho&#7843;n ti&#7873;n &#273;&#227; tr&#7843; l&#7841;i t&#224;i kho&#7843;n.</span></div>
</div>
</div>
</div>

<?php if ( sitetop_get_option('referral_enabled', 0) ) : ?>
<!-- ═══ REFERRAL ═══ -->
<div class="pane" id="p-referral">
<div class="ref-box">
    <?php $ref_pct = sitetop_get_option('referral_commission_percent', 20); ?>
    <div class="ref-pct"><?php echo $ref_pct; ?>%</div>
    <h3>Giới thiệu bạn bè — Kiếm thêm trọn đời!</h3>
    <p style="color:var(--txtl);font-size:14px;margin:8px 0 0">Chia sẻ link giới thiệu bên dưới. Mỗi khi bạn bè đăng ký và kiếm tiền, bạn nhận <?php echo $ref_pct; ?>% thu nhập của họ — vĩnh viễn.</p>
    <div class="ref-link">
        <input type="text" id="refUrl" value="<?php echo home_url('?ref=' . $user->user_login); ?>" readonly>
        <button onclick="copyText(document.getElementById('refUrl').value,this)">Copy</button>
    </div>
</div>
<div class="card"><div class="card-h"><h3>Thống kê Referral</h3></div>
<p style="color:var(--txtm);font-size:13px">Tính năng thống kê referral chi tiết sẽ được cập nhật.</p>
</div></div>
<?php endif; ?>

<!-- ═══ API ═══ -->
<div class="pane" id="p-api">
<?php
$api_token = get_user_meta($user_id, 'sitetop_api_token', true);
if(!$api_token){
    $api_token = wp_generate_password(24, false);
    update_user_meta($user_id, 'sitetop_api_token', $api_token);
}
$api_base   = home_url('/api');
$quick_base = home_url('/st');
$quick_tail = '&url=YOUR_URL&sub_link=https://link-du-phong';
$dev_tail   = '&url=yourdestinationlink.com&sub_link=https://link-du-phong';
$quick_link = $quick_base . '?api=' . $api_token . $quick_tail;
$dev_link   = $api_base . '?api=' . $api_token . $dev_tail;
$tok_esc = esc_html($api_token);
?>

<!-- Token: khối chính, mặc định làm mờ để an toàn khi chia sẻ màn hình -->
<div class="api-token">
    <div class="api-token-h">
        <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L19 4l2 2-2 2 2 2-3 3-2-2-1.5 1.5"/></svg></i>
        <div>
            <b>API Token c&#7911;a b&#7841;n</b>
            <span>D&#249;ng chung cho c&#7843; 3 c&#225;ch t&#237;ch h&#7907;p b&#234;n d&#432;&#7899;i</span>
        </div>
    </div>
    <div class="api-token-row">
        <input type="text" id="apiToken" value="<?php echo esc_attr($api_token); ?>" readonly>
        <button type="button" class="api-btn" onclick="copyText(document.getElementById('apiToken').value,this)">Copy</button>
        <button type="button" class="api-btn api-btn-new" onclick="resetApiToken()">T&#7841;o m&#7899;i</button>
    </div>
    <div class="api-warn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
        Gi&#7919; b&#237; m&#7853;t token n&#224;y. Ai c&#243; token c&#243; th&#7875; t&#7841;o link d&#432;&#7899;i t&#234;n b&#7841;n &#8212; l&#7897; th&#236; b&#7845;m &quot;T&#7841;o m&#7899;i&quot; ngay.
    </div>
</div>

<!-- Cách 1 -->
<div class="card api-m">
    <div class="api-m-h">
        <em>1</em>
        <b>Li&#234;n k&#7871;t nhanh</b>
        <span class="api-tag api-tag-g">Kh&#244;ng c&#7847;n bi&#7871;t code</span>
    </div>
    <p class="api-m-p">Sao ch&#233;p li&#234;n k&#7871;t b&#234;n d&#432;&#7899;i, d&#225;n v&#224;o tr&#236;nh duy&#7879;t, thay <code>YOUR_URL</code> b&#7857;ng li&#234;n k&#7871;t &#273;&#237;ch r&#7891;i nh&#7845;n ENTER. Tr&#236;nh duy&#7879;t s&#7869; t&#7921; chuy&#7875;n t&#7899;i link r&#250;t g&#7885;n.</p>
    <div class="api-code">
        <button type="button" class="cp" onclick="copyText('<?php echo esc_js($quick_link); ?>',this)">Copy</button>
        <code><?php echo esc_html($quick_base) . '?api=' . $tok_esc . esc_html($quick_tail); ?></code>
    </div>
</div>

<!-- Cách 2 -->
<div class="card api-m">
    <div class="api-m-h">
        <em>2</em>
        <b>G&#7885;i API t&#7915; code</b>
        <span class="api-tag api-tag-b">Tr&#7843; v&#7873; JSON</span>
    </div>
    <p class="api-m-p">G&#7917;i request GET t&#7899;i endpoint b&#234;n d&#432;&#7899;i t&#7915; server ho&#7863;c tool c&#7911;a b&#7841;n.</p>
    <div class="api-code">
        <button type="button" class="cp" onclick="copyText('<?php echo esc_js($dev_link); ?>',this)">Copy</button>
        <code><span class="api-mth">GET</span> <?php echo esc_html($api_base) . '?api=' . $tok_esc . esc_html($dev_tail); ?></code>
    </div>
    <div class="api-res-l">Ph&#7843;n h&#7891;i</div>
    <div class="api-code api-code-res">
        <code>{"status":"success","shortenedUrl":"<?php echo esc_html(home_url('/xxxxxxx')); ?>"}</code>
    </div>
</div>

<!-- Cách 3 -->
<div class="card api-m">
    <div class="api-m-h">
        <em>3</em>
        <b>Full Page Script</b>
        <span class="api-tag api-tag-p">T&#7921; &#273;&#7897;ng to&#224;n site</span>
    </div>
    <p class="api-m-p">D&#225;n &#273;o&#7841;n m&#227; n&#224;y v&#224;o website ho&#7863;c blog c&#7911;a b&#7841;n &#8212; m&#7885;i li&#234;n k&#7871;t tr&#234;n trang s&#7869; t&#7921; &#273;&#7897;ng &#273;&#432;&#7907;c r&#250;t g&#7885;n.</p>
    <div class="api-code api-code-block">
        <button type="button" class="cp" onclick="copyFullPageScript()">Copy</button>
        <code id="fullPageScript">&lt;script type="text/javascript"&gt;
    var app_url = '<?php echo esc_html(home_url('/')); ?>';
    var app_api_token = '<?php echo $tok_esc; ?>';
    var app_advert = 2;
    var app_exclude_domains = [''];
    var app_domains = [''];
&lt;/script&gt;
&lt;script src='<?php echo esc_html(home_url('/js/full-page-script.js')); ?>'&gt;&lt;/script&gt;</code>
    </div>
</div>
</div>

<!-- ═══ ACCOUNT ═══ -->
<div class="pane" id="p-account">
<?php
$acc_phone    = get_user_meta($user_id, 'phone', true);
$acc_verified = function_exists('sitetop_is_email_verified') ? sitetop_is_email_verified($user_id) : true;
?>

<!-- Hồ sơ -->
<div class="acc-profile">
    <div class="acc-id">
        <div class="acc-ava"><?php echo strtoupper(substr($user->display_name,0,1)); ?></div>
        <div class="acc-id-t">
            <b><?php echo esc_html($user->display_name); ?></b>
            <span class="acc-user">@<?php echo esc_html($user->user_login); ?></span>
            <div class="acc-chips">
                <span class="acc-chip acc-chip-role">Publisher</span>
                <?php if ( $acc_verified ) : ?>
                <span class="acc-chip acc-chip-ok">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                    Email &#273;&#227; x&#225;c minh
                </span>
                <?php else : ?>
                <span class="acc-chip acc-chip-warn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
                    Email ch&#432;a x&#225;c minh
                </span>
                <?php endif; ?>
                <span class="acc-chip">Tham gia <?php echo date('d/m/Y',strtotime($user->user_registered)); ?></span>
            </div>
        </div>
    </div>
    <div class="acc-nums">
        <div><span class="k">T&#7893;ng links</span><span class="v"><?php echo number_format($total_links); ?></span></div>
        <div><span class="k">Ho&#224;n th&#224;nh</span><span class="v"><?php echo number_format($total_completed); ?></span></div>
        <div><span class="k">T&#7893;ng thu nh&#7853;p</span><span class="v ok"><?php echo sitetop_format_money($total_earned); ?></span></div>
        <div><span class="k">S&#7889; d&#432;</span><span class="v ok"><?php echo sitetop_format_money($balance); ?></span></div>
    </div>
</div>

<div class="acc-grid">

<!-- Thông tin liên hệ -->
<div class="card">
    <div class="card-h"><h3>Th&#244;ng tin li&#234;n h&#7879;</h3></div>
    <form id="updateProfileForm">
        <div class="acc-f">
            <label>Email</label>
            <div class="acc-in">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="M3 7l9 6 9-6"/></svg>
                <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required>
            </div>
        </div>
        <div class="acc-f">
            <label>S&#7889; &#273;i&#7879;n tho&#7841;i</label>
            <div class="acc-in">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>
                <input type="tel" name="phone" value="<?php echo esc_attr($acc_phone); ?>" placeholder="0912 345 678">
            </div>
        </div>
        <button type="submit" class="acc-btn">L&#432;u thay &#273;&#7893;i</button>
        <div id="profileMsg" class="acc-msg"></div>
        <div class="acc-tip">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
            &#272;&#7893;i email s&#7869; c&#7847;n x&#225;c minh l&#7841;i. S&#7889; &#273;i&#7879;n tho&#7841;i d&#249;ng &#273;&#7875; li&#234;n h&#7879; khi c&#243; v&#7845;n &#273;&#7873; v&#7873; thanh to&#225;n.
        </div>
    </form>
</div>

<!-- Bảo mật -->
<div class="card">
    <div class="card-h"><h3>&#272;&#7893;i m&#7853;t kh&#7849;u</h3></div>
    <form id="changePwForm">
        <div class="acc-f">
            <label>M&#7853;t kh&#7849;u hi&#7879;n t&#7841;i</label>
            <div class="acc-in">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                <input type="password" name="current_password" required>
            </div>
        </div>
        <div class="acc-f">
            <label>M&#7853;t kh&#7849;u m&#7899;i</label>
            <div class="acc-in">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14v3"/></svg>
                <input type="password" name="new_password" required minlength="6">
            </div>
        </div>
        <div class="acc-f">
            <label>X&#225;c nh&#7853;n m&#7853;t kh&#7849;u m&#7899;i</label>
            <div class="acc-in">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M9.5 15.5l1.6 1.6 3.4-3.4"/></svg>
                <input type="password" name="confirm_password" required minlength="6">
            </div>
        </div>
        <button type="submit" class="acc-btn acc-btn-d">&#272;&#7893;i m&#7853;t kh&#7849;u</button>
        <div id="pwMsg" class="acc-msg"></div>
        <div class="acc-tip">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            T&#7889;i thi&#7875;u 6 k&#253; t&#7921;. N&#234;n d&#249;ng m&#7853;t kh&#7849;u ri&#234;ng, kh&#244;ng tr&#249;ng v&#7899;i c&#225;c trang kh&#225;c.
        </div>
    </form>
</div>

</div>
</div>

</div><!-- /.main-content -->
</div><!-- /.main-wrap -->

<!-- Edit Link Modal -->
<div id="editLinkModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;padding:20px">
<div style="background:#fff;border-radius:var(--rad);padding:24px;max-width:440px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.15)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <h3 style="font-family:var(--fonth);font-size:17px;color:var(--pd)">Chỉnh sửa link</h3>
        <button onclick="closeModal('editLinkModal')" style="background:none;border:none;cursor:pointer;font-size:20px;color:var(--txtm)">&times;</button>
    </div>
    <input type="hidden" id="editLinkId">
    <div style="margin-bottom:12px"><label style="display:block;font-size:12px;font-weight:600;color:var(--txtl);margin-bottom:4px">URL gốc</label><input type="url" id="editUrl" style="width:100%;padding:10px 12px;border:1.5px solid var(--brd);border-radius:var(--rads);font-size:13px"></div>
    <div style="margin-bottom:12px"><label style="display:block;font-size:12px;font-weight:600;color:var(--txtl);margin-bottom:4px">Link dự phòng</label><input type="url" id="editFallback" style="width:100%;padding:10px 12px;border:1.5px solid var(--brd);border-radius:var(--rads);font-size:13px"></div>
    <div style="margin-bottom:16px"><label style="display:block;font-size:12px;font-weight:600;color:var(--txtl);margin-bottom:4px">Bí danh</label><input type="text" id="editAlias" style="width:100%;padding:10px 12px;border:1.5px solid var(--brd);border-radius:var(--rads);font-size:13px"></div>
    <button onclick="saveEditLink()" style="padding:10px 24px;background:var(--p);color:#fff;border:none;border-radius:var(--rads);font-size:14px;font-weight:600;cursor:pointer">Lưu</button>
    <div id="editLinkMsg" style="margin-top:8px;font-size:12px"></div>
</div>
</div>

<!-- View Visits Modal -->
<div id="viewVisitsModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);align-items:center;justify-content:center;padding:20px">
<div style="background:#fff;border-radius:var(--rad);padding:24px;max-width:600px;width:100%;max-height:80vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.15)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <h3 style="font-family:var(--fonth);font-size:17px;color:var(--pd)">Chi tiết lượt truy cập</h3>
        <button onclick="closeModal('viewVisitsModal')" style="background:none;border:none;cursor:pointer;font-size:20px;color:var(--txtm)">&times;</button>
    </div>
    <div id="visitLinkInfo" style="font-size:13px;color:var(--info);margin-bottom:12px"></div>
    <div id="visitsContent" style="font-size:13px">Đang tải...</div>
</div>
</div>

<div class="toast-box" id="toastBox"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function(){
    var data = <?php echo json_encode($chart); ?>;
    var labels = data.map(function(x){ return x.date; });
    var views = data.map(function(x){ return x.clicks; });
    var earned = data.map(function(x){ return x.earned; });

    function fmt(n) {
        if (n >= 1000000) return (n/1000000).toFixed(1) + 'M';
        if (n >= 1000) return (n/1000).toFixed(0) + 'K';
        return n.toLocaleString('vi-VN');
    }
    function fmtMoney(n) { return n.toLocaleString('vi-VN') + 'đ'; }

    var ctx = document.getElementById('udChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Views',
                    data: views,
                    borderColor: '#1E5EFF',
                    backgroundColor: 'rgba(30,94,255,0.10)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2.5,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    yAxisID: 'y'
                },
                {
                    label: 'Kiếm được (đ)',
                    data: earned,
                    borderColor: '#00A96E',
                    backgroundColor: 'transparent',
                    tension: 0.3,
                    borderWidth: 2.5,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0A1633',
                    titleFont: { size: 13 },
                    bodyFont: { size: 12 },
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        title: function(items) { return 'Ngày ' + items[0].label; },
                        label: function(ctx) {
                            var v = ctx.raw;
                            if (ctx.datasetIndex === 0) return ' ' + ctx.dataset.label + ': ' + v.toLocaleString('vi-VN');
                            return ' ' + ctx.dataset.label + ': ' + fmtMoney(v);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 }, maxRotation: 0 }
                },
                y: {
                    position: 'left',
                    title: { display: true, text: 'Views', font: { size: 11 } },
                    grid: { color: '#EDF1F9' },
                    ticks: { font: { size: 11 }, callback: function(v) { return fmt(v); } },
                    beginAtZero: true
                },
                y1: {
                    position: 'right',
                    title: { display: true, text: 'VNĐ', font: { size: 11 } },
                    grid: { display: false },
                    ticks: { font: { size: 11 }, callback: function(v) { return fmt(v); } },
                    beginAtZero: true
                }
            }
        }
    });
})();
</script>

<script>
// Tab switching — syncs sidebar nav + bottom nav
var _tabTitles={overview:'T\u1ed5ng quan',links:'Links c\u1ee7a t\xf4i',withdraw:'R\xfat ti\u1ec1n',referral:'Referral',api:'API',account:'T\xe0i kho\u1ea3n'};
function switchTab(tab){
    document.querySelectorAll('.sidebar-nav-item').forEach(function(x){x.classList.toggle('on',x.dataset.t===tab)});
    document.querySelectorAll('.bottom-nav-item').forEach(function(x){x.classList.toggle('on',x.dataset.t===tab)});
    document.querySelectorAll('.pane').forEach(function(x){x.classList.remove('on')});
    var pane=document.getElementById('p-'+tab);if(pane)pane.classList.add('on');
    var tt=document.getElementById('mainTopbarTitle');if(tt)tt.textContent=_tabTitles[tab]||'Dashboard';
    // Lưới chỉ số thuộc về Tổng quan — các tab khác ẩn đi để nội dung chính lên trên,
    // thẻ ví phía trên vẫn giữ số dư + thao tác nhanh ở mọi tab.
    var st=document.querySelector('.dash-stats');if(st)st.style.display=(tab==='overview')?'':'none';
    window.scrollTo(0,0);
}
document.querySelectorAll('.sidebar-nav-item').forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();switchTab(b.dataset.t)})});
document.querySelectorAll('.bottom-nav-item').forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();switchTab(b.dataset.t)})});
(function(){var p=new URLSearchParams(window.location.search);var t=p.get('tab');if(t){var btn=document.querySelector('.sidebar-nav-item[data-t="'+t+'"]');if(btn)switchTab(t);}})();

function toggleWdFields(){var sel=document.querySelector('#wdForm input[name="method"]:checked');var isUsdt=!!sel&&sel.value==='usdt';document.querySelectorAll('.wd-bank-field').forEach(function(el){el.style.display=isUsdt?'none':'';el.querySelector('input').required=!isUsdt});document.querySelectorAll('.wd-usdt-field').forEach(function(el){el.style.display=isUsdt?'':'none';el.querySelector('input').required=isUsdt})}
function wdPickMethod(el){var r=el.querySelector('input[type=radio]');if(r)r.checked=true;document.querySelectorAll('.wd-method').forEach(function(x){x.classList.toggle('on',x===el)});toggleWdFields()}
function wdSetAmount(v){var i=document.getElementById('wdAmount');if(!i)return;i.value=v;i.focus()}

function ajax(action,data,cb){data.action=action;data.nonce='<?php echo $nonce;?>';var fd=new FormData();for(var k in data)fd.append(k,data[k]);fetch('<?php echo admin_url("admin-ajax.php");?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(cb).catch(function(e){toast('Lỗi: '+e.message,'err')})}

function dashShorten(){var btn=document.querySelector('[onclick="dashShorten()"]');if(btn.disabled)return;var u=document.getElementById('dashLongUrl').value.trim();if(!u){alert('Nhập URL gốc');return}if(!/^https?:\/\//i.test(u))u='https://'+u;var fb=document.getElementById('dashFallbackUrl').value.trim();var alias=document.getElementById('dashAlias').value.trim();btn.disabled=true;btn.style.opacity='.6';ajax('sitetop_shorten_url',{url:u,fallback_url:fb,alias:alias},function(r){btn.disabled=false;btn.style.opacity='1';if(r.success){document.getElementById('dashShortUrl').value=r.data.short_url;document.getElementById('dashResult').style.display='block';toast('Link đã rút gọn!','ok')}else{toast(r.data||'Lỗi','err')}})}

function copyText(txt,el){navigator.clipboard.writeText(txt).then(function(){
    var msg=el.querySelector?el.querySelector('.link-copied-msg'):null;
    if(msg){msg.style.display='block';setTimeout(function(){msg.style.display='none'},1500)}
    toast('Đã copy!','ok');
})}
function copyLink(el,txt){navigator.clipboard.writeText(txt).then(function(){
    var host=el.closest('.lk-head')||el.parentNode;
    var old=host.querySelector('.copy-tip');if(old)old.remove();
    var tip=document.createElement('span');tip.className='copy-tip';tip.textContent='Đã copy!';
    host.appendChild(tip);
    setTimeout(function(){tip.remove()},1600);
})}

document.getElementById('wdForm')?.addEventListener('submit',function(e){e.preventDefault();var fd=new FormData(this);fd.append('action','sitetop_user_withdraw');fd.append('nonce','<?php echo $nonce;?>');var btn=this.querySelector('button[type=submit]'),msg=document.getElementById('wdMsg');btn.disabled=true;btn.textContent='Đang xử lý...';fetch('<?php echo admin_url("admin-ajax.php");?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){if(r.success){msg.innerHTML='<span style="color:var(--ok)">Đã gửi thành công!</span>';toast('Yêu cầu rút tiền đã gửi!','ok');setTimeout(function(){location.reload()},2000)}else{msg.innerHTML='<span style="color:var(--err)">'+(r.data||'Lỗi')+'</span>';btn.disabled=false;btn.textContent='Gửi yêu cầu rút tiền'}})});

function openEditLink(id,url,fallback,alias){
    document.getElementById('editLinkId').value=id;
    document.getElementById('editUrl').value=url;
    document.getElementById('editFallback').value=fallback;
    document.getElementById('editAlias').value=alias;
    document.getElementById('editLinkMsg').innerHTML='';
    document.getElementById('editLinkModal').style.display='flex';
}
function saveEditLink(){
    var id=document.getElementById('editLinkId').value;
    ajax('sitetop_edit_shortlink',{link_id:id,url:document.getElementById('editUrl').value,fallback_url:document.getElementById('editFallback').value,alias:document.getElementById('editAlias').value},function(r){
        if(r.success){document.getElementById('editLinkMsg').innerHTML='<span style="color:var(--ok)">Đã lưu!</span>';toast('Đã cập nhật!','ok');setTimeout(function(){location.reload()},1000)}
        else{document.getElementById('editLinkMsg').innerHTML='<span style="color:var(--err)">'+(r.data||'Lỗi')+'</span>'}
    });
}
function viewLinkVisits(id,short){
    document.getElementById('visitLinkInfo').innerHTML=short;
    document.getElementById('visitsContent').innerHTML='Đang tải...';
    document.getElementById('viewVisitsModal').style.display='flex';
    ajax('sitetop_get_link_visits',{link_id:id},function(r){
        if(r.success&&r.data.html){document.getElementById('visitsContent').innerHTML=r.data.html}
        else{document.getElementById('visitsContent').innerHTML='<span style="color:var(--txtm)">Chưa có lượt truy cập</span>'}
    });
}
function closeModal(id){document.getElementById(id).style.display='none'}

function resetApiToken(){
    if(!confirm('Tạo token mới? Token cũ sẽ không còn hoạt động.'))return;
    ajax('sitetop_reset_api_token',{},function(r){
        if(r.success){document.getElementById('apiToken').value=r.data.token;toast('Đã tạo token mới!','ok');setTimeout(function(){location.reload()},1500)}
        else toast(r.data||'Lỗi','err');
    });
}
function copyFullPageScript(){
    var el=document.getElementById('fullPageScript');
    var text=el.textContent.replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&amp;/g,'&');
    navigator.clipboard.writeText(text).then(function(){toast('Đã copy script!','ok')});
}

function toast(m,t){var c=document.getElementById('toastBox'),d=document.createElement('div');d.className='toast t-'+(t||'ok');d.textContent=m;c.appendChild(d);setTimeout(function(){d.remove()},3500)}

document.getElementById('updateProfileForm')?.addEventListener('submit',function(e){
    e.preventDefault();var fd=new FormData(this);fd.append('action','sitetop_update_profile');fd.append('nonce','<?php echo $nonce;?>');
    var btn=this.querySelector('button[type=submit]'),msg=document.getElementById('profileMsg');btn.disabled=true;btn.textContent='Đang lưu...';
    fetch('<?php echo admin_url("admin-ajax.php");?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if(r.success){msg.innerHTML='<span style="color:var(--ok)">Đã cập nhật!</span>';toast('Cập nhật thành công!','ok');setTimeout(function(){location.reload()},1500)}
        else{msg.innerHTML='<span style="color:var(--err)">'+(r.data||'Lỗi')+'</span>'}
        btn.disabled=false;btn.textContent='Lưu thay đổi';
    })
});

document.getElementById('changePwForm')?.addEventListener('submit',function(e){
    e.preventDefault();var fd=new FormData(this);fd.append('action','sitetop_change_password');fd.append('nonce','<?php echo $nonce;?>');
    var btn=this.querySelector('button[type=submit]'),msg=document.getElementById('pwMsg');btn.disabled=true;btn.textContent='Đang xử lý...';
    fetch('<?php echo admin_url("admin-ajax.php");?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if(r.success){msg.innerHTML='<span style="color:var(--ok)">Đổi mật khẩu thành công!</span>';toast('Đổi mật khẩu thành công!','ok');this.reset()}
        else{msg.innerHTML='<span style="color:var(--err)">'+(r.data||'Lỗi')+'</span>'}
        btn.disabled=false;btn.textContent='Đổi mật khẩu';
    }.bind(this))
});

// Load more
document.querySelectorAll('.load-more-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
        var type=btn.dataset.type,offset=parseInt(btn.dataset.offset),target=btn.dataset.target;
        var origText=btn.textContent;btn.textContent='Đang tải...';btn.disabled=true;
        var fd=new FormData();fd.append('action','sitetop_load_more');fd.append('nonce','<?php echo $nonce;?>');fd.append('type',type);fd.append('offset',offset);
        fetch('<?php echo admin_url("admin-ajax.php");?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
            if(r.success&&r.data.html){
                var container=document.getElementById(target);
                if(type==='links'){container.insertAdjacentHTML('beforeend',r.data.html)}
                else{container.insertAdjacentHTML('beforeend',r.data.html)}
                btn.dataset.offset=offset+10;
                if(!r.data.has_more){btn.style.display='none'}
                else{btn.textContent=origText;btn.disabled=false}
            }else{btn.style.display='none'}
        }).catch(function(){btn.textContent=origText;btn.disabled=false})
    })
})

// Load announcements
;(function(){
    ajax('sitetop_get_announcements',{target:'user'},function(r){
        if(!r.success||!r.data.announcements||!r.data.announcements.length)return;
        var wrap=document.getElementById('userAnnouncements');
        var html='<div class="ann-header"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--info)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3zm-8.27 4a2 2 0 0 1-3.46 0"/></svg> Thông báo</div>';
        r.data.announcements.forEach(function(a){
            var cls='ann-info';
            if(a.type==='warning')cls='ann-warning';
            if(a.type==='success')cls='ann-success';
            var iconSvg='<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
            if(a.type==='warning')iconSvg='<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
            if(a.type==='success')iconSvg='<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
            var isNew=isAnnouncementNew(a.created_at);
            var date=formatAnnDate(a.created_at);
            html+='<div class="ann-item '+cls+'">';
            html+='<div class="ann-title"><span class="ann-icon">'+iconSvg+'</span> '+escHtmlAnn(a.title);
            if(isNew)html+=' <span class="ann-badge-new">M\u1edbi</span>';
            html+='</div>';
            html+='<div class="ann-body">'+a.message+'</div>';
            html+='<div class="ann-time"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> '+date+'</div>';
            html+='</div>';
        });
        wrap.innerHTML=html;
        wrap.style.display='block';
    });
    function isAnnouncementNew(dateStr){
        var d=new Date(dateStr.replace(' ','T'));
        var now=new Date();
        return(now-d)<7*24*60*60*1000;
    }
    function formatAnnDate(dateStr){
        var d=new Date(dateStr.replace(' ','T'));
        var dd=String(d.getDate()).padStart(2,'0');
        var mm=String(d.getMonth()+1).padStart(2,'0');
        var yy=d.getFullYear();
        var hh=String(d.getHours()).padStart(2,'0');
        var mi=String(d.getMinutes()).padStart(2,'0');
        return dd+'/'+mm+'/'+yy+' '+hh+':'+mi;
    }
    function escHtmlAnn(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML}
})();
</script>
<?php wp_footer(); ?>
</body>
</html>
