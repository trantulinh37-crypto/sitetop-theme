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
.bottom-nav-item.on{color:var(--p);background:#EDF3FF}
.bottom-nav-item.on svg{color:var(--p)}

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
@media(max-width:768px){
    .sidebar{display:none}
    .sidebar-overlay{display:none!important}
    .main-wrap{margin-left:0}
    .main-topbar{display:none}
    .mobile-topbar{display:flex}
    .bottom-nav{display:block}
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

/* ── Quy định chung: 2 cột, số thứ tự dạng chip ── */
.rules{background:var(--card);border:1px solid var(--brd);border-radius:var(--rad);padding:22px;margin-bottom:18px}
.rules-h{display:flex;align-items:center;gap:10px;font-family:var(--fonth);font-weight:800;font-size:16px;color:var(--pd);margin-bottom:4px;letter-spacing:-.015em}
.rules-h i{width:30px;height:30px;border-radius:10px;background:#E9EFFF;color:var(--p);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.rules-h i svg{width:16px;height:16px}
.rules-sub{font-size:12.5px;color:var(--txtl);margin:0 0 14px 40px}
.rules-list{display:grid;grid-template-columns:1fr 1fr;gap:9px 26px;list-style:none}
.rules-list li{display:flex;gap:10px;align-items:flex-start;font-size:12.8px;color:var(--txtl);line-height:1.55}
.rules-list li em{flex-shrink:0;width:20px;height:20px;border-radius:7px;background:#F1F5FF;color:var(--p);font-style:normal;font-family:var(--fonth);font-size:10.5px;font-weight:800;display:flex;align-items:center;justify-content:center;margin-top:2px}
.rules-list li b{color:var(--pd);font-weight:700}

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

/* Shorten form */
.sf{display:flex;gap:8px;margin-bottom:16px}
.sf input{flex:1;padding:13px 16px;border:1px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:14px}
.sf input:focus{outline:none;border-color:var(--p);box-shadow:0 0 0 3px rgba(30,94,255,.14)}
.sf button{padding:13px 28px;background:var(--p);color:#fff;border:none;border-radius:var(--rads);font-family:var(--font);font-size:14px;font-weight:700;cursor:pointer;white-space:nowrap}
.sf button:hover{background:var(--pl)}
.sf-result{display:none;background:#F1F6FF;border:1.5px solid #BFD3FF;border-radius:var(--rads);padding:14px 16px;margin-bottom:4px}
.sf-result-row{display:flex;align-items:center;gap:8px}
.sf-result-row input{flex:1;font-family:var(--mono);font-size:14px;color:var(--p);font-weight:600;border:none;background:transparent;outline:none}
.sf-result-row button{padding:8px 16px;background:var(--ok);color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-family:var(--font);font-size:12px}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--p);box-shadow:0 0 0 3px rgba(30,94,255,.14)}

/* Withdraw */
.wfg{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.wfg .full{grid-column:1/-1}
.wfl{display:block;font-size:11.5px;font-weight:700;color:var(--txtl);margin-bottom:5px}
.wfi,.wfs{width:100%;padding:11px 12px;border:1px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px;background:#FAFCFF}
.wbtn{display:block;width:100%;padding:13px;background:linear-gradient(135deg,#1E5EFF,#3E86FF);color:#fff;border:none;border-radius:var(--rads);font-family:var(--font);font-size:14px;font-weight:700;cursor:pointer;margin-top:8px;box-shadow:0 10px 22px -12px rgba(30,94,255,.9)}
.wbtn:disabled{opacity:.45;cursor:not-allowed;box-shadow:none}
.wmsg{margin-top:10px;font-size:12px;text-align:center;min-height:18px}

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
    .sf{flex-direction:column}
    .wd-grid,.acc-grid{grid-template-columns:1fr!important}
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
        <?php if($sb_icon): ?><img src="<?php echo esc_url($sb_icon); ?>" width="22" height="22" alt="" style="border-radius:4px"><?php else: ?><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><?php endif; ?>
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
        <?php if($ln_icon): ?><img src="<?php echo esc_url($ln_icon); ?>" width="20" height="20" alt="" style="vertical-align:middle"><?php else: ?><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><?php endif; ?>
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
        Quy định chung
    </div>
    <p class="rules-sub">Để đảm bảo quyền lợi cho tất cả người dùng, vui lòng tuân thủ các quy định sau:</p>
    <ul class="rules-list">
        <li><em>1</em><span>Mỗi người chỉ được sở hữu <b>01 tài khoản</b>. Nghiêm cấm tạo nhiều tài khoản hoặc dùng chung.</span></li>
        <li><em>2</em><span>Chỉ chia sẻ link qua các kênh hợp pháp. <b>Không spam</b>, lừa đảo, ép click hoặc tự click.</span></li>
        <li><em>3</em><span>Mỗi lượt truy cập hợp lệ được tính 01 lần (tối đa <b><?php echo (int)sitetop_get_option('shortlink_ip_limit_24h',5); ?> IP/ngày</b>).</span></li>
        <li><em>4</em><span>Nghiêm cấm sử dụng <b>VPN, Proxy, tool auto</b> hoặc bất kỳ hình thức gian lận nào.</span></li>
        <li><em>5</em><span>Doanh thu có thể được kiểm duyệt trước khi thanh toán.</span></li>
        <li><em>6</em><span>Rút tiền khi đạt mức tối thiểu <b><?php echo sitetop_format_money(floatval(sitetop_get_option('min_withdrawal', 50000))); ?></b>.</span></li>
        <li><em>7</em><span>Vi phạm sẽ bị thu hồi doanh thu hoặc <b>khóa tài khoản vĩnh viễn</b> mà không cần báo trước.</span></li>
    </ul>
</div>

</div>

<!-- ═══ LINKS ═══ -->
<div class="pane" id="p-links">

<!-- Create form -->
<div class="card">
    <div class="card-h"><h3>Tạo link rút gọn mới</h3></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div style="grid-column:1/-1">
            <label style="display:block;font-size:12px;font-weight:600;color:var(--txtl);margin-bottom:4px">URL gốc (bắt buộc)</label>
            <input type="url" id="dashLongUrl" placeholder="https://example.com/your-long-url-here" style="width:100%;padding:10px 12px;border:1.5px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px;background:#FAFCFF">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--txtl);margin-bottom:4px">Link dự phòng</label>
            <input type="url" id="dashFallbackUrl" placeholder="https://backup-link.com" style="width:100%;padding:10px 12px;border:1.5px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px;background:#FAFCFF">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--txtl);margin-bottom:4px">Bí danh</label>
            <input type="text" id="dashAlias" placeholder="my-link" style="width:100%;padding:10px 12px;border:1.5px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px;background:#FAFCFF">
        </div>
    </div>
    <button onclick="dashShorten()" style="padding:10px 24px;background:var(--info);color:#fff;border:none;border-radius:var(--rads);font-family:var(--font);font-size:14px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Tạo link
    </button>
    <div class="sf-result" id="dashResult">
        <div class="sf-result-row" style="margin-top:12px;display:flex;gap:8px">
            <input type="text" id="dashShortUrl" readonly style="flex:1;padding:10px 12px;border:2px solid var(--ok);border-radius:var(--rads);font-family:var(--mono);font-size:13px;color:var(--p);font-weight:600">
            <button onclick="copyText(document.getElementById('dashShortUrl').value,this)" style="padding:10px 16px;background:var(--ok);color:#fff;border:none;border-radius:var(--rads);font-weight:600;cursor:pointer;font-size:13px">Copy</button>
        </div>
    </div>
</div>

<!-- Links list -->
<div class="card"><div class="card-h"><h3>Links của tôi (<?php echo $total_links; ?>)</h3></div>

<!-- Tìm kiếm shortlink cũ: gõ = lọc realtime trang hiện tại; Enter/"Tìm" = tìm backend toàn bộ links (?q=) -->
<form method="get" style="margin:0 0 10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <input type="hidden" name="tab" value="links">
    <div style="position:relative;flex:1;min-width:220px">
        <input type="text" name="q" id="lkSearch" value="<?php echo esc_attr($lq); ?>" placeholder="Tìm mã shortlink (W1wcNk), full link hoặc URL gốc..." autocomplete="off" oninput="lkFilter()" style="width:100%;padding:10px 34px 10px 12px;border:1.5px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px;background:#FAFCFF;box-sizing:border-box">
        <button type="button" id="lkClear" onclick="lkClearSearch()" title="Xóa tìm kiếm" style="display:none;position:absolute;right:6px;top:50%;transform:translateY(-50%);width:24px;height:24px;border:none;background:transparent;color:var(--txtm);font-size:17px;cursor:pointer;line-height:1;padding:0">&times;</button>
    </div>
    <button type="submit" style="padding:10px 18px;background:var(--info);color:#fff;border:none;border-radius:var(--rads);font-family:var(--font);font-size:13px;font-weight:600;cursor:pointer">Tìm</button>
</form>
<?php if ($lq !== ''): ?>
<div id="lkServerInfo" style="font-size:12px;color:var(--txtm);margin:0 0 10px">Tìm thấy <strong style="color:var(--p)"><?php echo number_format($links_found); ?></strong> kết quả cho &quot;<strong><?php echo esc_html($lq); ?></strong>&quot; — <a href="?tab=links" style="color:var(--info);font-weight:600">Xóa tìm kiếm</a></div>
<?php endif; ?>
<div id="lkLiveInfo" style="display:none;font-size:12px;color:var(--txtm);margin:0 0 10px"></div>

<?php if(empty($my_links)): ?>
<p style="text-align:center;color:var(--txtm);padding:24px 0"><?php echo $lq !== '' ? 'Không tìm thấy link nào khớp &quot;' . esc_html($lq) . '&quot;.' : 'Chưa có link nào.'; ?></p>
<?php else: ?>
<div style="overflow-x:auto" id="linksListContainer">
<table style="width:100%;border-collapse:collapse;font-size:12px">
<thead><tr style="background:var(--bg)">
    <th style="padding:10px 12px;text-align:left;font-size:11px;color:var(--txtm);font-weight:600">Shortlink</th>
    <th style="padding:10px 12px;text-align:left;font-size:11px;color:var(--txtm);font-weight:600">URL gốc</th>
    <th style="padding:10px 8px;text-align:center;font-size:11px;color:var(--txtm);font-weight:600">Hoàn thành</th>
    <th style="padding:10px 8px;text-align:center;font-size:11px;color:var(--txtm);font-weight:600">Kiếm được</th>
    <th style="padding:10px 8px;text-align:center;font-size:11px;color:var(--txtm);font-weight:600">Trạng thái</th>
    <th style="padding:10px 8px;text-align:center;font-size:11px;color:var(--txtm);font-weight:600">Ngày tạo</th>
    <th style="padding:10px 8px;text-align:center;font-size:11px;color:var(--txtm);font-weight:600">Thao tác</th>
</tr></thead>
<tbody>
<?php foreach($my_links as $lk):
    $short = $home.'/'.(!empty($lk->alias) ? $lk->alias : $lk->shortcode);
    $bcls = $lk->status==='active'?'b-ok':($lk->status==='paused'?'b-warn':'b-mute');
    $completed = isset($lk->total_completed) ? (int)$lk->total_completed : 0;
    $earnings = isset($lk->total_earnings) ? (float)$lk->total_earnings : 0;
?>
<tr style="border-bottom:1px solid var(--brdl)">
    <td style="padding:10px 12px;position:relative"><span onclick="copyLink(this,'<?php echo esc_js($short); ?>')" style="font-family:var(--mono);font-size:12px;color:var(--info);font-weight:600;cursor:pointer"><?php echo esc_html($short); ?></span></td>
    <td style="padding:10px 12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--txtm);font-size:11px" title="<?php echo esc_attr($lk->target_url); ?>"><?php echo esc_html($lk->target_url); ?></td>
    <td style="padding:10px 8px;text-align:center;font-weight:600"><?php echo $completed; ?></td>
    <td style="padding:10px 8px;text-align:center;font-weight:600;color:<?php echo $earnings > 0 ? 'var(--ok)' : 'var(--txtm)'; ?>"><?php echo sitetop_format_money($earnings); ?></td>
    <td style="padding:10px 8px;text-align:center"><span class="badge <?php echo $bcls; ?>"><?php echo $lk->status === 'active' ? 'Hoạt động' : ($lk->status === 'paused' ? 'Tạm dừng' : 'Tắt'); ?></span></td>
    <td style="padding:10px 8px;text-align:center;font-size:11px;color:var(--txtm)"><?php echo date('d/m/Y', strtotime($lk->created_at)); ?></td>
    <td style="padding:10px 8px;text-align:center;white-space:nowrap">
        <button onclick="openEditLink(<?php echo $lk->id; ?>,'<?php echo esc_js($lk->target_url); ?>','<?php echo esc_js($lk->fallback_url ?? ''); ?>','<?php echo esc_js($lk->alias ?? ''); ?>')" style="padding:4px 10px;background:var(--card);border:1px solid var(--brd);border-radius:5px;font-size:11px;cursor:pointer;color:var(--info);font-weight:600">Sửa</button>
        <button onclick="viewLinkVisits(<?php echo $lk->id; ?>,'<?php echo esc_js($short); ?>')" style="padding:4px 10px;background:var(--card);border:1px solid var(--brd);border-radius:5px;font-size:11px;cursor:pointer;color:var(--p);font-weight:600">Chi tiết</button>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
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
    $pb = 'display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;padding:0 10px;border:1px solid var(--brd);border-radius:6px;font-size:13px;font-weight:600;color:var(--txt);background:var(--card);text-decoration:none;cursor:pointer';
?>
<div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin-top:14px">
    <a href="<?php echo $pag_base . max(1, $lpg-1); ?>" style="<?php echo $pb; ?><?php if($lpg<=1) echo ';opacity:.4;pointer-events:none'; ?>">«</a>
    <?php foreach($pag_show as $p): ?>
        <?php if ($p === '...'): ?>
            <span style="display:inline-flex;align-items:center;padding:0 6px;color:var(--txtm)">…</span>
        <?php else: ?>
            <a href="<?php echo $pag_base . $p; ?>" style="<?php echo $pb; ?><?php if($p===$lpg) echo ';background:var(--p);color:#fff;border-color:var(--p)'; ?>"><?php echo $p; ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
    <a href="<?php echo $pag_base . min($lpg_total_pages, $lpg+1); ?>" style="<?php echo $pb; ?><?php if($lpg>=$lpg_total_pages) echo ';opacity:.4;pointer-events:none'; ?>">»</a>
</div>
<div style="text-align:center;font-size:11px;color:var(--txtm);margin-top:4px">Trang <?php echo $lpg; ?>/<?php echo $lpg_total_pages; ?> — Tổng <?php echo number_format($total_links); ?> links</div>
<?php endif; ?>
<?php endif; ?>
</div>

<script>
// Tìm kiếm links: lọc realtime các dòng ĐANG hiển thị (10 dòng/trang); Enter/"Tìm" submit ?q= để
// backend tìm trong toàn bộ links của user (phân trang theo kết quả).
var LK_SERVER_Q = '<?php echo esc_js($lq); ?>';
var LK_TOTAL_LINKS = <?php echo (int) $total_links; ?>;
function lkFilter(){
    var inp=document.getElementById('lkSearch'); if(!inp) return;
    var q=inp.value.trim().toLowerCase();
    var clr=document.getElementById('lkClear'); if(clr) clr.style.display=q?'block':'none';
    var live=document.getElementById('lkLiveInfo');
    var cont=document.getElementById('linksListContainer');
    if(!cont){ if(live) live.style.display='none'; return; }
    var rows=cont.querySelectorAll('tbody tr'), shown=0;
    for(var i=0;i<rows.length;i++){
        var c=rows[i].cells; if(!c||c.length<2) continue;
        var shortTxt=(c[0].textContent||'').toLowerCase();
        var origTxt=((c[1].getAttribute('title')||'')+' '+(c[1].textContent||'')).toLowerCase();
        var hit=!q||shortTxt.indexOf(q)>=0||origTxt.indexOf(q)>=0;
        rows[i].style.display=hit?'':'none';
        if(hit) shown++;
    }
    if(live){
        if(q&&q!==LK_SERVER_Q.toLowerCase()){
            live.style.display='';
            live.innerHTML='Lọc nhanh: <strong style="color:var(--p)">'+shown+'</strong> kết quả trên trang này — nhấn <strong>Enter</strong> hoặc "Tìm" để tìm trong toàn bộ '+LK_TOTAL_LINKS.toLocaleString('vi-VN')+' links';
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
<div class="wd-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
<div class="card"><div class="card-h"><h3>Yêu cầu rút tiền</h3></div>
<div style="background:linear-gradient(135deg,#EDF3FF,#F5F9FF);border:1px solid #DCE7FF;border-radius:var(--rads);padding:14px;margin-bottom:14px;font-size:13px">
    <strong>Số dư khả dụng:</strong> <span style="color:var(--ok);font-family:var(--fonth);font-size:20px"><?php echo sitetop_format_money($balance); ?></span>
    <br><small style="color:var(--txtm)">Rút tối thiểu: <?php echo sitetop_format_money($min_wd); ?></small>
</div>
<form id="wdForm">
<?php
    $saved_bank = get_user_meta($user_id, 'sitetop_bank_name', true);
    $saved_account = get_user_meta($user_id, 'sitetop_bank_account', true);
    $saved_holder = get_user_meta($user_id, 'sitetop_bank_holder', true);
?>
<div class="wfg">
    <div class="full"><label class="wfl">Số tiền rút (VNĐ)</label><input class="wfi" type="number" name="amount" min="<?php echo $min_wd; ?>" max="<?php echo $balance; ?>" required></div>
    <div><label class="wfl">Phương thức</label><select class="wfs" name="method" id="wdMethod" onchange="toggleWdFields()"><option value="bank">Ngân hàng</option><option value="usdt">USDT-BEP20</option></select></div>
    <div id="wdBankName" class="wd-bank-field"><label class="wfl">Ngân hàng</label><input class="wfi" name="bank_name" required placeholder="Vietcombank" value="<?php echo esc_attr($saved_bank); ?>"></div>
    <div id="wdBankAccount" class="wd-bank-field"><label class="wfl">Số tài khoản</label><input class="wfi" name="bank_account" required value="<?php echo esc_attr($saved_account); ?>"></div>
    <div id="wdBankHolder" class="wd-bank-field"><label class="wfl">Chủ tài khoản</label><input class="wfi" name="bank_holder" required placeholder="NGUYEN VAN A" value="<?php echo esc_attr($saved_holder); ?>"></div>
    <div id="wdWallet" class="wd-usdt-field" style="display:none"><label class="wfl">Địa chỉ ví (BEP20)</label><input class="wfi" name="wallet_address" placeholder="0x..."></div>
</div>
<button type="submit" class="wbtn" <?php echo $balance<$min_wd?'disabled':''; ?>>Gửi yêu cầu rút tiền</button>
<div class="wmsg" id="wdMsg"></div>
</form></div>

<div class="card"><div class="card-h"><h3>Lịch sử rút tiền</h3></div>
<style>.wd-hist-tbl td,.wd-hist-tbl th{white-space:nowrap;font-size:12px;padding:6px 8px}.wd-hist-tbl td:last-child,.wd-hist-tbl th:last-child{white-space:normal;min-width:80px}</style>
<div style="overflow-x:auto"><table class="wd-hist-tbl"><thead><tr><th>Ngày</th><th>Số tiền</th><th>PT</th><th>Ngân hàng</th><th>Số TK/Ví</th><th>Chủ TK</th><th>Trạng thái</th><th>Ghi chú</th></tr></thead><tbody id="wdListContainer">
<?php if(empty($withdrawals)): ?>
<tr><td colspan="8" style="text-align:center;color:var(--txtm)">Chưa có</td></tr>
<?php else:
    $wd_status_vn = array('pending'=>'Chờ duyệt','approved'=>'Đã duyệt','completed'=>'Hoàn thành','rejected'=>'Từ chối','refunded'=>'Hoàn tiền','cancelled'=>'Đã huỷ');
    $bc=array('pending'=>'b-warn','approved'=>'b-info','completed'=>'b-ok','rejected'=>'b-err','refunded'=>'b-err','cancelled'=>'b-mute');
    foreach($withdrawals as $w):
    $is_usdt = $w->payment_method === 'usdt';
?>
<tr>
    <td><small><?php echo date('d/m/Y',strtotime($w->created_at)); ?></small></td>
    <td style="font-weight:600"><?php echo sitetop_format_money($w->amount); ?></td>
    <td><small><?php echo esc_html(strtoupper($w->payment_method)); ?></small></td>
    <td><small><?php echo $is_usdt ? '—' : esc_html($w->bank_name ?? ''); ?></small></td>
    <td><small><?php echo $is_usdt ? esc_html($w->wallet_address ?? '') : esc_html($w->bank_account ?? ''); ?></small></td>
    <td><small><?php echo $is_usdt ? '—' : esc_html($w->bank_holder ?? ''); ?></small></td>
    <td><span class="badge <?php echo $bc[$w->status]??'b-mute'; ?>"><?php echo $wd_status_vn[$w->status] ?? $w->status; ?></span></td>
    <td><small><?php echo esc_html($w->admin_note ?? ''); ?></small></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div>
<?php if(count($withdrawals) >= 10): ?>
<button type="button" class="load-more-btn" data-type="withdrawals" data-offset="10" data-target="wdListContainer" style="padding:10px 24px;background:var(--bg);border:1.5px solid var(--brd);border-radius:var(--rads);font-size:13px;font-weight:600;cursor:pointer;display:block;width:100%;margin-top:12px;color:var(--txtl);font-family:var(--font)">Xem thêm</button>
<?php endif; ?>
</div>
</div>
<div class="card" style="margin-top:20px">
<div style="display:flex;align-items:center;gap:8px;margin-bottom:14px"><span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;background:#E9EFFF;border-radius:9px"><svg width="16" height="16" fill="none" stroke="#1E5EFF" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg></span><h3 style="margin:0;font-size:15px;font-weight:700">Giải thích trạng thái</h3></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
<div style="border:1.5px solid var(--brdl);border-radius:var(--rads);padding:14px">
    <div style="margin-bottom:8px"><span style="display:inline-flex;align-items:center;gap:5px;background:#fef3c7;color:#92400e;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Chờ duyệt</span></div>
    <div style="font-size:13px;color:var(--txtl);line-height:1.5">Yêu cầu rút tiền đang được kiểm tra bởi nhóm của chúng tôi.</div>
</div>
<div style="border:1.5px solid var(--brdl);border-radius:var(--rads);padding:14px">
    <div style="margin-bottom:8px"><span style="display:inline-flex;align-items:center;gap:5px;background:#dcfce7;color:#166534;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg> Đã duyệt</span></div>
    <div style="font-size:13px;color:var(--txtl);line-height:1.5">Khoản thanh toán đã được phê duyệt và đang chờ để được gửi.</div>
</div>
<div style="border:1.5px solid var(--brdl);border-radius:var(--rads);padding:14px">
    <div style="margin-bottom:8px"><span style="display:inline-flex;align-items:center;gap:5px;background:#dcfce7;color:#166534;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg> Hoàn thành</span></div>
    <div style="font-size:13px;color:var(--txtl);line-height:1.5">Thanh toán đã được gửi thành công đến tài khoản của bạn.</div>
</div>
<div style="border:1.5px solid var(--brdl);border-radius:var(--rads);padding:14px">
    <div style="margin-bottom:8px"><span style="display:inline-flex;align-items:center;gap:5px;background:#fee2e2;color:#991b1b;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg> Từ chối</span></div>
    <div style="font-size:13px;color:var(--txtl);line-height:1.5">Yêu cầu bị từ chối do không hợp lệ. Tiền đã được hoàn lại vào số dư.</div>
</div>
<div style="border:1.5px solid var(--brdl);border-radius:var(--rads);padding:14px">
    <div style="margin-bottom:8px"><span style="display:inline-flex;align-items:center;gap:5px;background:#f3f4f6;color:#6b7280;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg> Đã huỷ</span></div>
    <div style="font-size:13px;color:var(--txtl);line-height:1.5">Thanh toán đã bị huỷ bỏ, khoản thanh toán không được hoàn.</div>
</div>
<div style="border:1.5px solid var(--brdl);border-radius:var(--rads);padding:14px">
    <div style="margin-bottom:8px"><span style="display:inline-flex;align-items:center;gap:5px;background:#f3e8ff;color:#7c3aed;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg> Hoàn tiền</span></div>
    <div style="font-size:13px;color:var(--txtl);line-height:1.5">Khoản thanh toán đã được trả lại vào tài khoản của bạn.</div>
</div>
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
$api_base = home_url('/api');
$quick_link = home_url('/st?api=' . $api_token . '&url=YOUR_URL&sub_link=https://link-du-phong');
?>

<div class="card">
    <div class="card-h"><h3>API</h3></div>
    <p style="color:var(--txtl);font-size:14px;margin-bottom:20px">Tích hợp hệ thống rút gọn link vào website của bạn</p>

    <!-- API Token -->
    <div style="background:var(--bg);border-radius:var(--rad);padding:20px;margin-bottom:20px">
        <div style="font-weight:700;font-size:15px;color:var(--pd);margin-bottom:12px;display:flex;align-items:center;gap:8px">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
            API Token của bạn
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="text" id="apiToken" value="<?php echo esc_attr($api_token); ?>" readonly style="flex:1;min-width:200px;padding:10px 14px;background:#fff;border:1.5px solid var(--brd);border-radius:var(--rads);font-family:var(--mono);font-size:13px;color:var(--pd)">
            <button type="button" onclick="copyText(document.getElementById('apiToken').value,this)" style="padding:8px 16px;background:var(--card);border:1.5px solid var(--brd);border-radius:var(--rads);font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font)">Copy</button>
            <button type="button" onclick="resetApiToken()" style="padding:8px 16px;background:var(--a);color:#fff;border:none;border-radius:var(--rads);font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font)">Tạo mới</button>
        </div>
        <p style="margin-top:8px;font-size:12px;color:var(--warn)">Giữ bí mật token này. Không chia sẻ với người khác!</p>
    </div>

    <!-- Quick Link -->
    <div style="background:var(--bg);border-radius:var(--rad);padding:20px;margin-bottom:20px">
        <div style="font-weight:700;font-size:15px;color:var(--pd);margin-bottom:12px;display:flex;align-items:center;gap:8px">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            Liên kết nhanh
        </div>
        <div style="position:relative">
            <div id="quickLinkCode" style="background:#fff;border:1.5px solid var(--brd);border-radius:var(--rads);padding:12px 40px 12px 14px;font-family:var(--mono);font-size:11px;color:var(--info);word-break:break-word;overflow-wrap:break-word;line-height:1.6"><?php echo esc_html($quick_link); ?></div>
            <button type="button" onclick="copyText('<?php echo esc_js($quick_link); ?>',this)" style="position:absolute;top:8px;right:8px;padding:4px 8px;background:var(--bg);border:1px solid var(--brd);border-radius:4px;cursor:pointer;font-size:11px">Copy</button>
        </div>
        <p style="margin-top:10px;font-size:12px;color:var(--txtl);line-height:1.7">Chỉ cần sao chép liên kết bên trên rồi dán vào trình duyệt, thay đổi phần cuối thành liên kết đích và nhấn ENTER. Sẽ chuyển hướng tự động đến liên kết rút gọn.</p>
    </div>

    <!-- API Developer -->
    <div style="background:var(--bg);border-radius:var(--rad);padding:20px;margin-bottom:20px">
        <div style="font-weight:700;font-size:15px;color:var(--pd);margin-bottom:12px;display:flex;align-items:center;gap:8px">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            API dành cho nhà phát triển
        </div>
        <div style="position:relative">
            <div style="background:#fff;border:1.5px solid var(--brd);border-radius:var(--rads);padding:12px 40px 12px 14px;font-family:var(--mono);font-size:11px;color:var(--info);word-break:break-word;overflow-wrap:break-word;line-height:1.6"><?php echo esc_html($api_base . '?api=' . $api_token . '&url=yourdestinationlink.com&sub_link=https://link-du-phong'); ?></div>
            <button type="button" onclick="copyText('<?php echo esc_js($api_base . '?api=' . $api_token . '&url=yourdestinationlink.com&sub_link=https://link-du-phong'); ?>',this)" style="position:absolute;top:8px;right:8px;padding:4px 8px;background:var(--bg);border:1px solid var(--brd);border-radius:4px;cursor:pointer;font-size:11px">Copy</button>
        </div>
        <p style="margin-top:10px;font-size:13px;color:var(--txt)">Bạn sẽ nhận được phản hồi JSON như sau:</p>
        <div style="background:#1A1A2E;border-radius:var(--rads);padding:14px;margin-top:8px;font-family:var(--mono);font-size:12px;color:#34D399;line-height:1.6;overflow-x:auto;word-break:break-word">{"status":"success","shortenedUrl":"<?php echo home_url('/xxxxxxx'); ?>"}</div>
    </div>

    <!-- Full Page Script -->
    <div style="background:var(--bg);border-radius:var(--rad);padding:20px">
        <div style="font-weight:700;font-size:15px;color:var(--pd);margin-bottom:12px;display:flex;align-items:center;gap:8px">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            Full Page Script
        </div>
        <p style="font-size:13px;color:var(--txtl);margin-bottom:12px">Sao chép và dán mã bên dưới vào trang web hoặc blog của bạn và các liên kết sẽ được cập nhật tự động!</p>
        <div style="background:#1A1A2E;border-radius:var(--rads);padding:14px;font-family:var(--mono);font-size:11px;color:#E2E8F0;line-height:1.8;white-space:pre-wrap;word-break:break-word;overflow-x:auto" id="fullPageScript">&lt;script type="text/javascript"&gt;
    var app_url = '<?php echo home_url('/'); ?>';
    var app_api_token = '<?php echo esc_js($api_token); ?>';
    var app_advert = 2;
    var app_exclude_domains = [''];
    var app_domains = [''];
&lt;/script&gt;
&lt;script src='<?php echo home_url('/js/full-page-script.js'); ?>'&gt;&lt;/script&gt;</div>
        <button type="button" onclick="copyFullPageScript()" style="margin-top:10px;padding:8px 16px;background:var(--card);border:1.5px solid var(--brd);border-radius:var(--rads);font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font)">Copy Script</button>
    </div>
</div>
</div>

<!-- ═══ ACCOUNT ═══ -->
<div class="pane" id="p-account">
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px" class="acc-grid">

<div class="card">
    <div class="card-h"><h3>Thông tin tài khoản</h3></div>
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;padding-bottom:18px;border-bottom:1px solid var(--brdl)">
        <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--p),var(--pl));color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;font-family:var(--fonth);flex-shrink:0"><?php echo strtoupper(substr($user->display_name,0,1)); ?></div>
        <div>
            <div style="font-weight:700;font-size:16px;color:var(--pd)"><?php echo esc_html($user->display_name); ?></div>
            <div style="font-size:12px;color:var(--txtm)">@<?php echo esc_html($user->user_login); ?> &middot; Tham gia <?php echo date('d/m/Y',strtotime($user->user_registered)); ?></div>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px">
        <div style="padding:10px 14px;background:var(--bg);border-radius:var(--rads)">
            <div style="font-size:11px;color:var(--txtm);margin-bottom:2px">Email</div>
            <div style="font-weight:600;color:var(--pd)"><?php echo esc_html($user->user_email); ?></div>
        </div>
        <div style="padding:10px 14px;background:var(--bg);border-radius:var(--rads)">
            <div style="font-size:11px;color:var(--txtm);margin-bottom:2px">Điện thoại</div>
            <div style="font-weight:600;color:var(--pd)"><?php echo esc_html(get_user_meta($user_id, 'phone', true) ?: '—'); ?></div>
        </div>
        <div style="padding:10px 14px;background:var(--bg);border-radius:var(--rads)">
            <div style="font-size:11px;color:var(--txtm);margin-bottom:2px">Tổng links</div>
            <div style="font-weight:700;font-size:16px;color:var(--info)"><?php echo $total_links; ?></div>
        </div>
        <div style="padding:10px 14px;background:var(--bg);border-radius:var(--rads)">
            <div style="font-size:11px;color:var(--txtm);margin-bottom:2px">Hoàn thành</div>
            <div style="font-weight:700;font-size:16px;color:var(--info)"><?php echo number_format($total_completed); ?></div>
        </div>
        <div style="padding:10px 14px;background:var(--bg);border-radius:var(--rads)">
            <div style="font-size:11px;color:var(--txtm);margin-bottom:2px">Tổng thu nhập</div>
            <div style="font-weight:700;font-size:16px;color:var(--ok)"><?php echo sitetop_format_money($total_earned); ?></div>
        </div>
        <div style="padding:10px 14px;background:var(--bg);border-radius:var(--rads)">
            <div style="font-size:11px;color:var(--txtm);margin-bottom:2px">Số dư</div>
            <div style="font-weight:700;font-size:16px;color:var(--ok)"><?php echo sitetop_format_money($balance); ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-h"><h3>Cập nhật thông tin</h3></div>
    <form id="updateProfileForm">
        <div style="margin-bottom:14px">
            <label style="display:block;font-size:12px;font-weight:600;color:var(--txtl);margin-bottom:4px">Email</label>
            <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required style="width:100%;padding:10px 14px;border:1px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px">
        </div>
        <div style="margin-bottom:14px">
            <label style="display:block;font-size:12px;font-weight:600;color:var(--txtl);margin-bottom:4px">Số điện thoại</label>
            <input type="tel" name="phone" value="<?php echo esc_attr(get_user_meta($user_id, 'phone', true)); ?>" placeholder="0912 345 678" style="width:100%;padding:10px 14px;border:1px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px">
        </div>
        <button type="submit" style="width:100%;padding:10px;background:var(--p);color:#fff;border:none;border-radius:var(--rads);font-family:var(--font);font-size:13px;font-weight:600;cursor:pointer">Lưu thay đổi</button>
        <div id="profileMsg" style="margin-top:8px;font-size:12px"></div>
    </form>
</div>

<div class="card">
        <div class="card-h"><h3>Đổi mật khẩu</h3></div>
        <form id="changePwForm">
            <div style="margin-bottom:14px">
                <label style="display:block;font-size:12px;font-weight:600;color:var(--txtl);margin-bottom:4px">Mật khẩu hiện tại</label>
                <input type="password" name="current_password" required style="width:100%;padding:10px 14px;border:1px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px">
            </div>
            <div style="margin-bottom:14px">
                <label style="display:block;font-size:12px;font-weight:600;color:var(--txtl);margin-bottom:4px">Mật khẩu mới</label>
                <input type="password" name="new_password" required minlength="6" style="width:100%;padding:10px 14px;border:1px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px">
            </div>
            <div style="margin-bottom:14px">
                <label style="display:block;font-size:12px;font-weight:600;color:var(--txtl);margin-bottom:4px">Xác nhận mật khẩu mới</label>
                <input type="password" name="confirm_password" required minlength="6" style="width:100%;padding:10px 14px;border:1px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px">
            </div>
            <button type="submit" style="width:100%;padding:10px;background:var(--p);color:#fff;border:none;border-radius:var(--rads);font-family:var(--font);font-size:13px;font-weight:600;cursor:pointer">Đổi mật khẩu</button>
            <div id="pwMsg" style="margin-top:8px;font-size:12px"></div>
        </form>
    </div>

</div></div>

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

function toggleWdFields(){var isUsdt=document.getElementById('wdMethod').value==='usdt';document.querySelectorAll('.wd-bank-field').forEach(function(el){el.style.display=isUsdt?'none':'';el.querySelector('input').required=!isUsdt});document.querySelectorAll('.wd-usdt-field').forEach(function(el){el.style.display=isUsdt?'':'none';el.querySelector('input').required=isUsdt})}

function ajax(action,data,cb){data.action=action;data.nonce='<?php echo $nonce;?>';var fd=new FormData();for(var k in data)fd.append(k,data[k]);fetch('<?php echo admin_url("admin-ajax.php");?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(cb).catch(function(e){toast('Lỗi: '+e.message,'err')})}

function dashShorten(){var btn=document.querySelector('[onclick="dashShorten()"]');if(btn.disabled)return;var u=document.getElementById('dashLongUrl').value.trim();if(!u){alert('Nhập URL gốc');return}if(!/^https?:\/\//i.test(u))u='https://'+u;var fb=document.getElementById('dashFallbackUrl').value.trim();var alias=document.getElementById('dashAlias').value.trim();btn.disabled=true;btn.style.opacity='.6';ajax('sitetop_shorten_url',{url:u,fallback_url:fb,alias:alias},function(r){btn.disabled=false;btn.style.opacity='1';if(r.success){document.getElementById('dashShortUrl').value=r.data.short_url;document.getElementById('dashResult').style.display='block';toast('Link đã rút gọn!','ok')}else{toast(r.data||'Lỗi','err')}})}

function copyText(txt,el){navigator.clipboard.writeText(txt).then(function(){
    var msg=el.querySelector?el.querySelector('.link-copied-msg'):null;
    if(msg){msg.style.display='block';setTimeout(function(){msg.style.display='none'},1500)}
    toast('Đã copy!','ok');
})}
function copyLink(el,txt){navigator.clipboard.writeText(txt).then(function(){
    var old=el.parentNode.querySelector('.copy-tip');if(old)old.remove();
    var tip=document.createElement('span');tip.className='copy-tip';tip.textContent='Đã copy!';
    tip.style.cssText='position:absolute;left:12px;top:0;font-size:10px;color:var(--ok);font-weight:600;';
    el.parentNode.appendChild(tip);
    setTimeout(function(){tip.remove()},1500);
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
    var btn=this.querySelector('button'),msg=document.getElementById('profileMsg');btn.disabled=true;btn.textContent='Đang lưu...';
    fetch('<?php echo admin_url("admin-ajax.php");?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if(r.success){msg.innerHTML='<span style="color:var(--ok)">Đã cập nhật!</span>';toast('Cập nhật thành công!','ok');setTimeout(function(){location.reload()},1500)}
        else{msg.innerHTML='<span style="color:var(--err)">'+(r.data||'Lỗi')+'</span>'}
        btn.disabled=false;btn.textContent='Lưu thay đổi';
    })
});

document.getElementById('changePwForm')?.addEventListener('submit',function(e){
    e.preventDefault();var fd=new FormData(this);fd.append('action','sitetop_change_password');fd.append('nonce','<?php echo $nonce;?>');
    var btn=this.querySelector('button'),msg=document.getElementById('pwMsg');btn.disabled=true;btn.textContent='Đang xử lý...';
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
