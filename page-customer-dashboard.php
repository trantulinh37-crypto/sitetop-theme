<?php
/**
 * Template Name: Customer Dashboard
 * SiteTop.net V2 - Customer Dashboard (nhà quảng cáo mua campaign)
 * 
 * Customer nạp tiền → tạo campaign → traffic được phân phối qua shortlinks
 * Tabs: Tổng quan | Campaigns | Nạp tiền | Lịch sử GD
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }

$user_id = get_current_user_id();
$user    = wp_get_current_user();

// Chỉ role customer (hoặc admin) được vào dashboard khách hàng. Trước đây publisher thường
// mở thẳng /customer thấy nguyên form nạp tiền → tạo được đơn nạp (incident 02/07/2026,
// user alonemmo #134). Publisher → đưa về đúng dashboard của họ (/user).
if ( ! in_array( 'customer', (array) $user->roles, true ) && ! current_user_can( 'manage_options' ) ) {
    wp_redirect( function_exists( 'sitetop_get_dashboard_url' ) ? sitetop_get_dashboard_url( $user ) : home_url( '/user' ) );
    exit;
}

// Khách hàng chờ kích hoạt: KHÓA MỀM — vẫn vào dashboard xem Tổng quan/số dư, nhưng bấm sang tab
// khác hoặc nút Nạp tiền/Tạo chiến dịch sẽ hiện popup "chờ kích hoạt" (gate client-side cuối trang).
// Server vẫn chặn tạo campaign/nạp tiền (lớp bảo vệ chính). Admin không bao giờ pending.
$adv_pending = function_exists( 'sitetop_customer_is_pending' ) && sitetop_customer_is_pending( $user_id );
$is_minimal = isset($_GET['minimal']) && $_GET['minimal'] === '1';

global $wpdb;
$prefix = $wpdb->prefix . 'sitetop_';
$today  = date( 'Y-m-d', strtotime( sitetop_current_time() ) );

// Stats
$cust_balance    = sitetop_get_customer_balance_amount( $user_id );
// "Đã nạp" phải dùng CÙNG nguồn với số dư (customer_deposits, gồm bonus) — không dùng
// customer_transactions type='deposit' vì deposit cũ/admin-created có thể thiếu transaction → ra 0 sai.
$total_deposited = (float) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(SUM(amount + bonus_amount),0) FROM {$prefix}customer_deposits WHERE customer_id=%d AND status='approved' AND amount>0", $user_id ) );
$total_spent_views = (float) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(ABS(SUM(amount)),0) FROM {$prefix}customer_transactions WHERE customer_id=%d AND type='campaign_view' AND amount<0", $user_id ) );
$total_spent_admin = (float) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(ABS(SUM(amount)),0) FROM {$prefix}customer_deposits WHERE customer_id=%d AND status='approved' AND amount<0", $user_id ) );
$total_spent = $total_spent_views + $total_spent_admin;

// Pagination params
$camp_page = max(1, intval($_GET['camp_page'] ?? 1));
$dep_page = max(1, intval($_GET['dep_page'] ?? 1));
$hist_page = max(1, intval($_GET['hist_page'] ?? 1));
$camp_per = 20; $dep_per = 5; $hist_per = 20;

// Campaigns (with order info)
$camp_total = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$prefix}keyword_campaigns WHERE customer_id=%d AND status != 'deleted'", $user_id) );
$camp_pages = max(1, ceil($camp_total / $camp_per));
$camp_offset = ($camp_page - 1) * $camp_per;
$my_campaigns = $wpdb->get_results( $wpdb->prepare(
    "SELECT kc.*, co.task_type,
            (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND step='verified') as total_completed,
            (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND step='verified' AND DATE(created_at)=%s) as today_views
     FROM {$prefix}keyword_campaigns kc
     LEFT JOIN {$prefix}customer_orders co ON kc.order_id = co.id
     WHERE kc.customer_id = %d AND kc.status != 'deleted'
     ORDER BY kc.created_at DESC LIMIT %d OFFSET %d", $today, $user_id, $camp_per, $camp_offset ) );
$active_camps  = array_filter( $my_campaigns, function($c){ return $c->status==='active'; } );
$total_views   = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$prefix}shortlink_visits v INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id=kc.id WHERE kc.customer_id=%d AND v.step='verified'", $user_id ) );
$today_views   = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$prefix}shortlink_visits v INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id=kc.id WHERE kc.customer_id=%d AND v.step='verified' AND DATE(v.created_at)=%s", $user_id, $today ) );

$dep_total = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$prefix}customer_deposits WHERE customer_id=%d AND (visible IS NULL OR visible = 1)", $user_id) );
$dep_pages = max(1, ceil($dep_total / $dep_per));
$dep_offset = ($dep_page - 1) * $dep_per;
$deposits = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$prefix}customer_deposits WHERE customer_id=%d AND (visible IS NULL OR visible = 1) ORDER BY created_at DESC LIMIT %d OFFSET %d", $user_id, $dep_per, $dep_offset ) );
$cust_txns = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$prefix}customer_transactions WHERE customer_id=%d ORDER BY created_at DESC LIMIT 10", $user_id ) );

// Campaign visit history (detailed)
$visit_history = $wpdb->get_results( $wpdb->prepare(
    "SELECT v.created_at, v.verified_at, v.step, v.ip_address, v.user_agent, v.reward_paid, v.customer_paid,
            v.reward_amount, v.from_google, v.url_matched,
            kc.title as campaign_title, kc.keyword, kc.target_url, kc.traffic_type, kc.onsite_time, kc.price_per_view,
            co.task_type
     FROM {$prefix}shortlink_visits v
     INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id = kc.id
     LEFT JOIN {$prefix}customer_orders co ON kc.order_id = co.id
     WHERE kc.customer_id = %d AND v.step = 'verified' AND v.customer_paid = 1
     ORDER BY v.created_at DESC LIMIT %d OFFSET %d", $user_id, $hist_per, ($hist_page - 1) * $hist_per ) );
$hist_total = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$prefix}shortlink_visits v INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id=kc.id WHERE kc.customer_id=%d AND v.step='verified' AND v.customer_paid=1", $user_id ) );
$hist_pages = max(1, ceil($hist_total / $hist_per));

// 30-day chart
$chart=array();
for($i=29;$i>=0;$i--){
    $d=date('Y-m-d',strtotime("-{$i} days",strtotime(sitetop_current_time())));
    $v=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}shortlink_visits v INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id=kc.id WHERE kc.customer_id=%d AND v.step='verified' AND DATE(v.created_at)=%s",$user_id,$d));
    $spent=(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(ABS(SUM(amount)),0) FROM {$prefix}customer_transactions WHERE customer_id=%d AND type='campaign_view' AND amount<0 AND DATE(created_at)=%s",$user_id,$d));
    $chart[]=array('date'=>date('d/m',strtotime($d)),'views'=>$v,'spent'=>$spent);
}
$home = home_url();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer - <?php bloginfo('name'); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<?php wp_head(); ?>
<style>
:root{--p:#1E5EFF;--pl:#4C7DFF;--pd:#0A1633;--a:#00C6FF;--bg:#F2F5FC;--card:#fff;--dark:#0A1633;--txt:#1F2A44;--txtl:#5A6684;--txtm:#8A93AB;--brd:#DFE5F3;--brdl:#ECF0FA;--ok:#00A96E;--err:#E0364B;--warn:#E08700;--info:#1E5EFF;--font:'Inter',sans-serif;--fonth:'Plus Jakarta Sans',sans-serif;--mono:'JetBrains Mono',monospace;--rad:16px;--rads:10px;--sidebar-w:248px}
*{box-sizing:border-box;margin:0;padding:0}html,body{width:100%;overflow-x:hidden}body{font-family:var(--font);color:var(--txt);background:var(--bg);line-height:1.6}
.card{max-width:100%;overflow:hidden}

/* ── Sidebar: nền sáng + nav dạng pill ── */
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
.sidebar-role{font-size:11px;color:var(--p);font-weight:700;margin-top:1px}
.sidebar-sec{padding:0 22px;font-size:10px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:var(--txtm);margin-bottom:7px}
.sidebar-nav{flex:1;padding:0 12px 16px;display:flex;flex-direction:column;gap:3px}
.sidebar-nav-item{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:11px;color:var(--txtl);font-size:13.5px;font-weight:600;cursor:pointer;transition:background .18s,color .18s,box-shadow .18s;text-decoration:none}
.sidebar-nav-item:hover{background:#F2F6FE;color:var(--pd)}
.sidebar-nav-item.on,.sidebar-nav-item.on:hover{background:linear-gradient(135deg,#1E5EFF,#3E86FF);color:#fff;box-shadow:0 8px 18px -8px rgba(30,94,255,.75)}
.sidebar-nav-item.on svg,.sidebar-nav-item.on:hover svg{color:#fff}
.sidebar-nav-item svg{width:18px;height:18px;flex-shrink:0;color:var(--txtm);transition:color .18s}
.sidebar-nav-item:hover svg{color:var(--p)}
.sidebar-bottom{padding:10px 12px;border-top:1px solid var(--brdl);display:flex;flex-direction:column;gap:2px}
.sidebar-bottom a{display:flex;align-items:center;gap:11px;padding:9px 12px;border-radius:10px;color:var(--txtl);text-decoration:none;font-size:13px;font-weight:600;transition:background .18s,color .18s}
.sidebar-bottom a:hover{background:#F2F6FE;color:var(--pd)}
.sidebar-bottom a:last-child:hover{background:#FFF0F2;color:var(--err)}
.sidebar-bottom a svg{width:16px;height:16px;flex-shrink:0}

/* ── Tránh bị thanh admin WordPress che ── */
body.admin-bar .sidebar{top:32px}
body.admin-bar .main-topbar,body.admin-bar .mobile-topbar{top:32px}
@media(max-width:782px){
    body.admin-bar .sidebar{top:46px}
    body.admin-bar .main-topbar,body.admin-bar .mobile-topbar{top:46px}
}
@media(max-width:600px){
    body.admin-bar .main-topbar,body.admin-bar .mobile-topbar{top:0}
}

/* ── Main content ── */
.main-wrap{margin-left:var(--sidebar-w);min-height:100vh}
.main-topbar{background:rgba(242,245,252,.88);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);border-bottom:1px solid var(--brdl);padding:0 28px;height:60px;display:flex;align-items:center;justify-content:space-between;gap:12px;position:sticky;top:0;z-index:50}
.main-topbar-title{font-family:var(--fonth);font-weight:800;font-size:19px;color:var(--pd);letter-spacing:-.015em}
.topbar-date{display:inline-flex;align-items:center;gap:7px;font-size:12.5px;font-weight:600;color:var(--txtl);background:#fff;border:1px solid var(--brd);border-radius:999px;padding:6px 13px}
.topbar-date svg{width:14px;height:14px;color:var(--p);flex-shrink:0}
.main-content{padding:22px 28px 34px;max-width:1180px;overflow-x:hidden}

/* ── Thẻ ví: số dư + thao tác nhanh ── */
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
.wbtn-w,.wbtn-g{display:inline-flex;align-items:center;gap:8px;padding:12px 20px;border-radius:12px;font-family:var(--font);font-size:13.5px;font-weight:700;cursor:pointer;transition:transform .18s;white-space:nowrap}
.wbtn-w svg,.wbtn-g svg{width:16px;height:16px;flex-shrink:0}
.wbtn-w{background:#fff;color:var(--p);border:none;box-shadow:0 10px 22px -10px rgba(3,20,70,.9)}
.wbtn-g{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.38)}
.wbtn-w:hover,.wbtn-g:hover{transform:translateY(-2px)}

.dash-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:13px;margin-bottom:20px}

/* ── Bottom Nav (mobile) ── */
.bottom-nav{display:none;position:fixed;bottom:0;left:0;right:0;background:rgba(255,255,255,.96);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);z-index:100;padding:6px 6px env(safe-area-inset-bottom,4px);border-top:1px solid var(--brd);box-shadow:0 -4px 18px -8px rgba(15,32,74,.3)}
.bottom-nav-inner{display:flex;justify-content:space-around;align-items:center;gap:2px}
.bottom-nav-item{display:flex;flex-direction:column;align-items:center;gap:3px;padding:7px 4px;border-radius:12px;color:var(--txtm);text-decoration:none;font-size:10px;font-weight:600;cursor:pointer;transition:background .18s,color .18s;flex:1;min-width:0}
.bottom-nav-item svg{width:20px;height:20px;flex-shrink:0}
.bottom-nav-item span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
/* Mỗi tab một màu riêng — chọn theo data-t nên đổi thứ tự menu không lệch màu. */
.bottom-nav-item{--nc:#1E5EFF;--nb:#E9EFFF;position:relative}
.bottom-nav-item[data-t="overview"]{--nc:#1E5EFF;--nb:#E9EFFF}
.bottom-nav-item[data-t="create"]{--nc:#0090D0;--nb:#E2F4FF}
.bottom-nav-item[data-t="campaigns"]{--nc:#6D4AFF;--nb:#F0EAFF}
.bottom-nav-item[data-t="deposit"]{--nc:#00A96E;--nb:#E1F8F0}
.bottom-nav-item[data-t="history"]{--nc:#0090D0;--nb:#E2F4FF}
.bottom-nav-item[data-t="account"]{--nc:#E07A00;--nb:#FFF2E2}
.bottom-nav-item svg{color:var(--nc);opacity:.5;transition:opacity .18s,transform .18s}
.bottom-nav-item:hover svg{opacity:.8}
.bottom-nav-item.on{color:var(--nc);background:var(--nb)}
.bottom-nav-item.on svg{color:var(--nc);opacity:1;transform:translateY(-1px)}
.bottom-nav-item.on span{font-weight:800}

.mobile-topbar{display:none;background:#fff;border-bottom:1px solid var(--brd);padding:0 14px;height:54px;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.mobile-topbar-logo{font-family:var(--fonth);font-weight:800;font-size:17px;color:var(--pd);text-decoration:none;display:flex;align-items:center;gap:7px}
.mobile-topbar-logo svg{color:var(--p);flex-shrink:0}
.mobile-topbar-right{display:flex;align-items:center;gap:9px;font-size:12px}
.mobile-topbar-right .bal{display:inline-flex;align-items:center;padding:5px 11px;border-radius:999px;background:#EDF3FF;color:var(--p)!important;font-family:var(--fonth);font-weight:800;font-size:12.5px}
.mobile-topbar-right .avatar{width:30px;height:30px;border-radius:10px;background:linear-gradient(135deg,var(--p),var(--a));color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;font-family:var(--fonth)}

.pane{display:none;animation:fu .3s ease}.pane.on{display:block}
@keyframes fu{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

.card{background:var(--card);border-radius:var(--rad);border:1px solid var(--brd);padding:22px;box-shadow:0 1px 2px rgba(15,32,74,.04);margin-bottom:18px}
.card-h{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:0;border-bottom:0;gap:10px;flex-wrap:wrap}
.card-h h3{font-family:var(--fonth);font-size:16px;font-weight:800;color:var(--pd);letter-spacing:-.015em;display:flex;align-items:center;gap:9px}
.card-h h3::before{content:'';width:4px;height:17px;border-radius:3px;background:linear-gradient(180deg,var(--p),var(--a));flex-shrink:0}
.sg{display:grid;gap:14px;margin-bottom:20px}
.sg4{grid-template-columns:repeat(4,1fr)}
.sc{background:var(--card);border-radius:var(--rad);padding:15px;border:1px solid var(--brd);display:flex;flex-direction:column;gap:11px;transition:transform .2s,box-shadow .2s,border-color .2s;min-width:0;overflow:hidden}
.sc:hover{box-shadow:0 12px 26px -14px rgba(15,32,74,.4);transform:translateY(-2px);border-color:#CBD9F8}
.sc-icon{width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sc-icon svg{width:19px;height:19px}
.sc.s1 .sc-icon{background:#E9EFFF;color:#1E5EFF}.sc.s2 .sc-icon{background:#E2F4FF;color:#0090D0}
.sc.s3 .sc-icon{background:#E1F8F0;color:#00A96E}.sc.s4 .sc-icon{background:#FFF2E2;color:#E07A00}
.sc-text{min-width:0;overflow:hidden}
.sc .sl{font-size:11.5px;color:var(--txtl);font-weight:600;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sc .sv{font-family:var(--fonth);font-weight:800;font-size:21px;color:var(--pd);line-height:1.15;white-space:nowrap;letter-spacing:-.025em;overflow:hidden;text-overflow:ellipsis}
.sc .ss{font-size:11px;color:var(--txtm);margin-top:5px;white-space:nowrap;font-weight:600}
.sc .ss b{color:var(--ok);font-weight:800}

/* ── Bắt đầu nhanh: 4 bước ── */
.qs{background:var(--card);border:1px solid var(--brd);border-radius:var(--rad);padding:22px;margin-bottom:18px}
.qs-h{display:flex;align-items:center;gap:10px;font-family:var(--fonth);font-weight:800;font-size:16px;color:var(--pd);margin-bottom:4px;letter-spacing:-.015em}
.qs-h i{width:30px;height:30px;border-radius:10px;background:#E9EFFF;color:var(--p);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.qs-h i svg{width:16px;height:16px}
.qs-sub{font-size:12.5px;color:var(--txtl);margin:0 0 15px 40px}
.qs-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.qs-step{position:relative;border:1px solid var(--brd);border-radius:12px;padding:14px;background:#FAFCFF}
.qs-step em{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:7px;background:var(--p);color:#fff;font-style:normal;font-family:var(--fonth);font-size:11px;font-weight:800;margin-bottom:8px}
.qs-step b{display:block;font-family:var(--fonth);font-size:13.5px;font-weight:800;color:var(--pd);margin-bottom:4px}
.qs-step span{display:block;font-size:12px;color:var(--txtl);line-height:1.55}
.qs-note{display:flex;align-items:flex-start;gap:8px;margin-top:14px;font-size:11.5px;color:var(--txtm);font-weight:600;line-height:1.55}
.qs-note svg{width:14px;height:14px;flex-shrink:0;margin-top:2px;color:var(--p)}

table{width:100%;border-collapse:collapse;font-size:13px}
thead th{background:#F5F8FE;padding:10px 12px;text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.07em;color:var(--txtl);font-weight:700;border-bottom:1px solid var(--brd)}
td{padding:11px 12px;border-bottom:1px solid var(--brdl);vertical-align:middle}
tbody tr:hover{background:#F7FAFF}
.badge{display:inline-flex;padding:4px 9px;border-radius:8px;font-size:10.5px;font-weight:700}
.b-ok{background:#DCFCE7;color:#046C4A}.b-warn{background:#FEF3C7;color:#92400E}.b-err{background:#FEE2E2;color:#991B1B}.b-info{background:#E3EBFF;color:#1743B8}.b-mute{background:#EEF1F8;color:#5A6684}
.mono{font-family:var(--mono);font-size:11px}
h3.card-h{font-family:var(--fonth);font-size:16px;font-weight:800;color:var(--pd);letter-spacing:-.015em;justify-content:flex-start}
h3.card-h::before{content:'';width:4px;height:17px;border-radius:3px;background:linear-gradient(180deg,var(--p),var(--a));flex-shrink:0}

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

.acc-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start;max-width:960px}
.acc-f{margin-bottom:14px}
.acc-f label{display:block;font-size:11.5px;font-weight:700;color:var(--txtl);margin-bottom:6px}
.acc-in{position:relative}
.acc-in svg{position:absolute;left:13px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--txtm);pointer-events:none;transition:color .18s}
.acc-in input{width:100%;padding:12px 14px 12px 39px;border:1px solid var(--brd);border-radius:var(--rads);background:#FAFCFF;font-family:var(--font);font-size:13.5px;color:var(--txt)}
.acc-in input:disabled{background:#EEF2FA;color:var(--txtm);cursor:not-allowed}
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
}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--p);box-shadow:0 0 0 3px rgba(30,94,255,.14)}

.cd-chart-legend{display:flex;gap:14px;font-size:12px;font-weight:600;color:var(--txtl)}
.cd-chart-legend span{display:inline-flex;align-items:center;gap:6px}
.cd-chart-legend span::before{content:'';width:9px;height:9px;border-radius:50%;display:inline-block}
.cd-chart-legend .lg-traffic::before{background:#1E5EFF}
.cd-chart-legend .lg-spent::before{background:#E07A00}
.cd-chart-container{position:relative;height:290px}

/* Campaign cards */
.ccgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px}
.ccamp{background:var(--card);border:1px solid var(--brd);border-radius:14px;padding:18px;transition:transform .2s,box-shadow .2s,border-color .2s}
.ccamp:hover{box-shadow:0 12px 26px -16px rgba(15,32,74,.5);transform:translateY(-2px);border-color:#CBD9F8}
.ccamp-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;gap:8px}
.ccamp-name{font-family:var(--fonth);font-weight:800;font-size:14.5px;color:var(--pd);margin-bottom:4px;letter-spacing:-.01em}
.ccamp-kw{font-size:12px;color:var(--txtl);margin-bottom:10px}
.cprog{height:6px;background:#EDF1F9;border-radius:99px;overflow:hidden;margin-bottom:6px}
.cprog-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,#1E5EFF,#00C6FF)}
.ccamp-meta{display:flex;gap:14px;font-size:11px;color:var(--txtm);font-weight:600}
.ccamp-link{display:block;margin-top:10px;font-family:var(--mono);font-size:10px;color:var(--info);word-break:break-all}
.camp-pills{display:grid;grid-template-columns:repeat(5,1fr);gap:6px}
.camp-pill{font-size:12px;padding:7px 10px;border-radius:99px;text-align:center;cursor:pointer;font-weight:700;background:#F3F6FD;color:var(--txtl);border:none;line-height:1.4;transition:all .18s;font-family:var(--font)}
.camp-pill.on{color:#fff}

/* Create campaign form */
.svc-card{border:1.5px solid var(--brd);border-radius:12px;padding:11px 14px;cursor:pointer;transition:all .18s;display:flex;align-items:center;gap:10px;background:#fff}
.svc-card.selected{border-color:var(--p);background:#F4F8FF;box-shadow:0 0 0 3px rgba(30,94,255,.1)}
.svc-card:hover{border-color:#BFD3FF}
.svc-icon{width:36px;height:36px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.svc-icon svg{width:18px;height:18px}
.svc-name{font-family:var(--fonth);font-weight:800;font-size:13px;color:var(--pd);margin-bottom:1px}
.svc-price{font-size:11px;color:var(--ok);font-weight:700}
.cf-label{display:block;font-size:11.5px;font-weight:700;color:var(--txtl);margin-bottom:6px}
.cf-input{width:100%;padding:11px 14px;border:1px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px;transition:all .18s;background:#FAFCFF}
.cf-input:focus{background:#fff}
.tt-option{display:flex;align-items:center;gap:10px;padding:12px 16px;border:1.5px solid var(--brd);border-radius:12px;cursor:pointer;transition:all .18s;background:#fff}
.tt-option.selected{border-color:var(--p);background:#F4F8FF;box-shadow:0 0 0 3px rgba(30,94,255,.1)}
.tt-option:hover{border-color:#BFD3FF}
.tt-option input{width:18px;height:18px;accent-color:var(--p)}
.tt-label{flex:1;font-family:var(--fonth);font-weight:800;font-size:13px;color:var(--pd)}
.tt-price{font-family:var(--fonth);font-weight:800;font-size:13px;color:var(--p)}
.ot-option{display:flex;align-items:center;justify-content:center;gap:6px;padding:11px;border:1.5px solid var(--brd);border-radius:11px;cursor:pointer;transition:all .18s;font-size:13px;font-weight:700;background:#fff;color:var(--txtl)}
.ot-option.selected{border-color:var(--p);background:#F4F8FF;color:var(--p);box-shadow:0 0 0 3px rgba(30,94,255,.1)}
.ot-option:hover{border-color:#BFD3FF}
.ot-option input{display:none}
.ss-upload{border:1.5px dashed #C6D6F8;border-radius:14px;padding:16px;text-align:center;background:#FAFCFF}
.ss-label{font-family:var(--fonth);font-size:13px;font-weight:800;color:var(--pd);margin-bottom:10px;display:flex;align-items:center;justify-content:center;gap:6px}
.ss-preview{width:100%;min-height:120px;background:#EEF3FC;border-radius:11px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;margin-bottom:10px;overflow:hidden}
.ss-preview span{font-size:12px;color:var(--txtm)}
.ss-preview img{width:100%;height:auto;max-height:200px;object-fit:contain;border-radius:11px;display:block}
.ss-btn{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:11px;background:var(--p);color:#fff;border-radius:11px;font-size:13px;font-weight:700;cursor:pointer;transition:all .18s}
.ss-btn:hover{background:#1748CC}

/* ── Tab Tạo mới ── */
.cc-h{display:flex;align-items:center;gap:10px;margin-bottom:16px}
.cc-h em{width:24px;height:24px;border-radius:8px;background:var(--p);color:#fff;font-style:normal;font-family:var(--fonth);font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.cc-h b{font-family:var(--fonth);font-size:16px;font-weight:800;color:var(--pd);letter-spacing:-.015em}
.cc-svc{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.svc-card{align-items:flex-start;padding:14px}
.svc-t{min-width:0}
.svc-desc{font-size:11.5px;color:var(--txtm);line-height:1.45;margin:2px 0 5px}
.cc-hint{font-size:11.5px;color:var(--txtm);line-height:1.55;margin:0 0 12px;font-weight:500}
.req{color:var(--err)}

.cc-nocode{background:#FFFBF2;border:1px solid #F5E0BC;border-radius:12px;padding:16px}
.cc-nocode-h{display:flex;align-items:center;gap:8px;font-family:var(--fonth);font-size:13px;font-weight:800;color:#92400E;margin-bottom:13px}
.cc-nocode-h svg{width:15px;height:15px;flex-shrink:0}
.ot-option small{color:var(--err);font-size:10.5px;font-weight:700}
.ot-option.selected small{color:var(--p)}

.cc-est{position:relative;overflow:hidden;border-radius:18px;padding:22px;margin-bottom:12px;background:linear-gradient(118deg,#0B31BE,#1E5EFF 55%,#00A6FF);color:#fff;box-shadow:0 16px 36px -20px rgba(11,49,190,.85)}
.cc-est::before{content:'';position:absolute;right:-70px;top:-100px;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.1)}
.cc-est-h{position:relative;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:15px}
.cc-est-h b{font-family:var(--fonth);font-size:16.5px;font-weight:800;letter-spacing:-.015em}
.cc-days{display:flex;align-items:center;gap:9px}
.cc-days label{font-size:12px;color:rgba(255,255,255,.75);font-weight:600}
.cc-days input{width:82px;padding:8px 10px;border-radius:9px;border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.13);color:#fff;font-family:var(--fonth);font-size:14px;font-weight:800;text-align:center}
.cc-days input:focus{border-color:#fff;box-shadow:none}
.cc-est-nums{position:relative;z-index:2;display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.cc-est-nums>div{border-radius:12px;padding:12px 14px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18)}
.cc-est-nums .hi{background:#fff;border-color:#fff}
.cc-est-nums .k{display:block;font-size:10px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:rgba(255,255,255,.72);margin-bottom:4px}
.cc-est-nums .hi .k{color:var(--txtl)}
.cc-est-nums .v{display:block;font-family:var(--fonth);font-size:20px;font-weight:800;letter-spacing:-.025em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cc-est-nums .hi .v{color:var(--p)}
.cc-est-note{position:relative;z-index:2;display:flex;align-items:flex-start;gap:8px;margin-top:13px;font-size:11.5px;color:rgba(255,255,255,.82);font-weight:600;line-height:1.55}
.cc-est-note svg{width:14px;height:14px;flex-shrink:0;margin-top:2px}
.cc-submit{position:relative;z-index:2;display:flex;align-items:center;justify-content:center;gap:9px;width:100%;margin-top:16px;padding:14px;background:#fff;color:var(--p);border:none;border-radius:12px;font-family:var(--font);font-size:14.5px;font-weight:800;cursor:pointer;box-shadow:0 10px 22px -10px rgba(3,20,70,.9);transition:transform .18s}
.cc-submit:hover:not(:disabled){transform:translateY(-1px)}
.cc-submit:disabled{opacity:.6;cursor:not-allowed}
.cc-submit svg{width:17px;height:17px;flex-shrink:0}
.cc-msg{margin:0 0 12px;font-size:13px;text-align:center;min-height:18px;font-weight:600}
.cc-info{display:flex;align-items:flex-start;gap:9px;background:#F1F6FF;border:1px solid #D5E3FF;border-radius:12px;padding:14px 16px;font-size:12.5px;color:#1743B8;line-height:1.6;margin-bottom:18px;font-weight:500}
.cc-info svg{width:15px;height:15px;flex-shrink:0;margin-top:2px}
.cc-warn{display:flex;align-items:flex-start;gap:9px;background:#FFFBEB;border:1px solid #FDE68A;border-radius:12px;padding:14px 16px;font-size:12.5px;color:#92400E;line-height:1.6;margin-top:14px;font-weight:500}
.cc-warn svg{width:15px;height:15px;flex-shrink:0;margin-top:2px}
.cc-code{position:relative;background:#0B1330;border:1px solid #1E2C57;border-radius:12px;padding:15px 15px 15px 15px;overflow-x:auto}
.cc-code code{display:block;padding-right:62px;font-family:var(--mono);font-size:11.5px;line-height:1.8;color:#8FC7FF;word-break:break-all}
.cc-code .cp{position:absolute;top:9px;right:9px;z-index:2;padding:5px 11px;border-radius:7px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.1);color:#DCE7FF;font-family:var(--font);font-size:11px;font-weight:700;cursor:pointer;transition:all .18s}
.cc-code .cp:hover{background:var(--p);border-color:var(--p);color:#fff}
.cc-copied{font-size:12px;color:var(--ok);font-weight:700;min-height:18px;margin-top:6px}

/* ── Tab Nạp tiền ── */
.deposit-row{display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start}
.cust-paging{display:flex;gap:6px;justify-content:center;margin-top:16px;flex-wrap:wrap}
.pg-btn{display:inline-flex;align-items:center;justify-content:center;min-width:34px;padding:7px 12px;border:1px solid var(--brd);border-radius:9px;font-size:12.5px;font-weight:700;color:var(--txtl);text-decoration:none;cursor:pointer;background:#fff;transition:all .18s}
.pg-btn:hover{border-color:var(--p);color:var(--p)}
.pg-btn.on{background:var(--p);color:#fff;border-color:var(--p)}
.dep-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}

.dep-step{margin-bottom:20px}
.dep-step-h{display:flex;align-items:center;gap:9px;margin-bottom:11px}
.dep-step-h em{width:22px;height:22px;border-radius:8px;background:var(--p);color:#fff;font-style:normal;font-family:var(--fonth);font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.dep-step-h b{font-family:var(--fonth);font-size:13.5px;font-weight:800;color:var(--pd)}
.dep-amount{position:relative}
.dep-amount input{width:100%;padding:15px 46px 15px 16px;border:1.5px solid var(--brd);border-radius:12px;background:#FAFCFF;font-family:var(--fonth);font-weight:800;font-size:24px;color:var(--pd);letter-spacing:-.02em;-moz-appearance:textfield}
.dep-amount input::-webkit-outer-spin-button,.dep-amount input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.dep-amount span{position:absolute;right:16px;top:50%;transform:translateY(-50%);font-family:var(--fonth);font-weight:800;font-size:18px;color:var(--txtm);pointer-events:none}
.dep-presets{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:11px}
.dep-preset{position:relative;padding:11px 8px;border:1px solid var(--brd);border-radius:11px;background:#fff;font-family:var(--fonth);font-size:14px;font-weight:800;color:var(--p);cursor:pointer;transition:all .18s}
.dep-preset:hover{border-color:var(--p);background:#F3F7FF;transform:translateY(-1px)}
.dep-bonus-tag{position:absolute;top:-7px;right:-4px;background:linear-gradient(135deg,#E0364B,#FF6B4A);color:#fff;font-family:var(--font);font-size:9.5px;font-weight:800;padding:2px 6px;border-radius:99px;box-shadow:0 3px 8px -2px rgba(224,54,75,.7)}
.dep-hint{font-size:11.5px;color:var(--txtm);margin-top:10px;font-weight:600}
.dep-hint b{color:var(--txtl);font-weight:800}
.dep-convert{margin-top:9px;padding:9px 13px;border-radius:10px;background:#EDF3FF;border:1px solid #D5E3FF;font-size:12.5px;color:var(--p);font-weight:700}

.dep-methods{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.pay-method-option{display:flex;align-items:center;gap:11px;padding:13px;border:1.5px solid var(--brd);border-radius:12px;cursor:pointer;transition:all .18s;background:#fff;position:relative}
.pay-method-option input{position:absolute;opacity:0;width:0;height:0;pointer-events:none}
.pay-method-option .m-ic{width:36px;height:36px;border-radius:11px;background:#F1F5FF;color:var(--txtl);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .18s}
.pay-method-option .m-ic svg{width:18px;height:18px}
.pay-method-option .m-t{display:block;font-family:var(--fonth);font-weight:800;font-size:13.5px;color:var(--pd);line-height:1.25}
.pay-method-option .m-s{display:block;font-size:11px;color:var(--txtm);font-weight:600}
.pay-method-option:hover{border-color:#BFD3FF}
.pay-method-option.selected{border-color:var(--p);background:#F4F8FF;box-shadow:0 0 0 3px rgba(30,94,255,.1)}
.pay-method-option.selected .m-ic{background:var(--p);color:#fff}

.dep-bonus{display:flex;align-items:flex-start;gap:9px;background:#ECFAF3;border:1px solid #B7EBD4;border-radius:12px;padding:13px 15px;margin-bottom:16px;font-size:12.5px;color:#046C4A;line-height:1.6;font-weight:500}
.dep-bonus svg{width:16px;height:16px;flex-shrink:0;margin-top:1px}
.dep-submit{display:flex;align-items:center;justify-content:center;gap:9px;width:100%;padding:14px;background:linear-gradient(135deg,#1E5EFF,#3E86FF);color:#fff;border:none;border-radius:12px;font-family:var(--font);font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 10px 22px -12px rgba(30,94,255,.9);transition:transform .18s}
.dep-submit:hover:not(:disabled){transform:translateY(-1px)}
.dep-submit:disabled{opacity:.55;cursor:not-allowed;box-shadow:none}
.dep-submit svg{width:16px;height:16px;flex-shrink:0}
.dep-msg{margin-top:10px;font-size:12.5px;text-align:center;min-height:18px;font-weight:600}

.dep-box{position:relative;overflow:hidden;background:linear-gradient(118deg,#0B31BE,#1E5EFF 55%,#00A6FF);border-radius:16px;padding:20px;margin-bottom:12px;color:#fff;box-shadow:0 16px 36px -20px rgba(11,49,190,.85)}
.dep-box::before{content:'';position:absolute;right:-60px;bottom:-120px;width:230px;height:230px;border-radius:50%;border:1px solid rgba(255,255,255,.2)}
.dep-box-tag{position:relative;display:inline-flex;font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.78);margin-bottom:13px}
.dep-info{position:relative;display:grid;grid-template-columns:auto 1fr;gap:11px 16px;font-size:13px;align-items:center}
.dep-info dt{color:rgba(255,255,255,.7);font-weight:600;white-space:nowrap}
.dep-info dd{color:#fff;font-family:var(--fonth);font-weight:800;font-size:15px;word-break:break-all;text-align:right}
.dep-info dd.with-copy{display:flex;align-items:center;justify-content:flex-end;gap:9px}
.dep-info dd.with-copy span{font-family:var(--mono);font-size:14px;font-weight:600}
.dep-copy{padding:5px 11px;border-radius:8px;border:1px solid rgba(255,255,255,.32);background:rgba(255,255,255,.14);color:#fff;font-family:var(--font);font-size:11px;font-weight:700;cursor:pointer;flex-shrink:0;transition:all .18s}
.dep-copy:hover{background:#fff;color:var(--p);border-color:#fff}
.dep-usdt-h{font-family:var(--fonth);font-size:13px;font-weight:800;color:var(--pd);margin:16px 0 10px}
.dep-wallet{margin-bottom:10px}
.dep-wallet-t{font-size:10.5px;font-weight:800;letter-spacing:.09em;text-transform:uppercase;color:var(--txtm);margin-bottom:6px}

.dep-count{font-size:11.5px;color:var(--txtm);font-weight:600}
.dep-list{display:flex;flex-direction:column;gap:10px}
.dep-item{position:relative;overflow:hidden;border:1px solid var(--brd);border-radius:12px;background:#fff;padding:13px 14px 13px 17px}
.dep-item::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--txtm)}
.dep-pending::before{background:#E07A00}
.dep-approved::before{background:#00A96E}
.dep-rejected::before{background:#E0364B}
.dep-item-top{display:flex;align-items:center;justify-content:space-between;gap:10px}
.dep-item-amt{font-family:var(--fonth);font-weight:800;font-size:16px;color:var(--pd);letter-spacing:-.02em}
.dep-item-meta{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;font-size:12px;color:var(--txtl);margin-top:7px}
.dep-tag{background:#F1F5FF;color:var(--p);font-weight:700;font-size:10.5px;padding:2px 8px;border-radius:6px;flex-shrink:0}
.dep-plus{color:var(--ok);font-weight:700}
.dep-item-foot{font-size:11px;color:var(--txtm);margin-top:7px;font-weight:600}
.dep-note{margin-top:9px;padding:8px 10px;border-radius:8px;background:#F6F8FD;font-size:12px;color:var(--txtl);line-height:1.5}
.dep-empty{text-align:center;padding:36px 14px;color:var(--txtm)}
.dep-empty svg{width:42px;height:42px;color:#CBD5E9;margin-bottom:10px}
.dep-empty b{display:block;font-family:var(--fonth);font-size:14px;color:var(--txtl);font-weight:800;margin-bottom:3px}
.dep-empty small{font-size:12px}

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

@media(max-width:1080px){
    .qs-steps{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:768px){
    .sidebar{display:none}
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
    .sc{flex-direction:row;align-items:center;gap:11px;padding:13px}
    .sc-icon{width:36px;height:36px;border-radius:11px}
    .sc .sv{font-size:18px}
    .sc .sl{font-size:11px}
    .sg4{grid-template-columns:repeat(2,1fr)}
    .ccgrid{grid-template-columns:1fr}
    .account-grid,.acc-grid{grid-template-columns:1fr!important}
    .acc-nums{display:grid;grid-template-columns:1fr 1fr;gap:14px 12px}
    .acc-nums>div{min-width:0}
    .brow{gap:16px}
    .deposit-row{grid-template-columns:1fr!important}
    .dep-grid{grid-template-columns:1fr!important}
    .dep-methods{grid-template-columns:1fr}
    .dep-presets{grid-template-columns:repeat(2,1fr)}
    .dep-amount input{font-size:21px;padding:14px 42px 14px 14px}
    .dep-info dd{font-size:14px}
    .cd-chart-container{height:230px}
    .qs-steps{grid-template-columns:1fr}
    .camp-pills{grid-template-columns:repeat(3,1fr)}
    .card{padding:18px}
    #onsiteTimes{grid-template-columns:repeat(3,1fr)!important}
    #kwFields{grid-template-columns:1fr!important}
    #trafficTypes{grid-template-columns:1fr!important}
    .cc-svc{grid-template-columns:1fr}
    .cc-est-nums{grid-template-columns:1fr}
    .cc-est{padding:18px}
    #screenshotSection>div{grid-template-columns:1fr!important}
    #nocodeFields .cc-nocode>div{grid-template-columns:1fr!important}
}
@media(max-width:480px){
    .main-content{padding:12px 12px 150px}
}
<?php if($is_minimal): ?>
#wpadminbar,html{margin-top:0!important}
#wpadminbar{display:none!important}
<?php endif; ?>
</style>
</head>
<body<?php echo ( ! $is_minimal && is_admin_bar_showing() ) ? ' class="admin-bar"' : ''; ?>>
<?php if(!$is_minimal): ?>
<!-- Sidebar -->
<aside class="sidebar">
    <?php $sb_icon = get_option('sitetop_widget_icon',''); ?>
    <a href="<?php echo home_url(); ?>" class="sidebar-logo">
        <img src="<?php echo esc_url( $sb_icon ?: sitetop_logo_url('tft-logo.png') ); ?>" width="22" height="22" alt="" style="border-radius:50%">
        <span class="lg-chip"><span class="lgd">SITE</span><span class="lgb">TOP</span></span>
    </a>
    <div class="sidebar-user">
        <div class="sidebar-user-info">
            <div class="sidebar-avatar"><?php echo strtoupper(substr($user->display_name,0,1)); ?></div>
            <div>
                <div class="sidebar-name"><?php echo esc_html($user->display_name); ?></div>
                <div class="sidebar-role">Advertiser</div>
            </div>
        </div>
    </div>
    <div class="sidebar-sec">Menu</div>
    <nav class="sidebar-nav">
        <a class="sidebar-nav-item on" data-t="overview"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>T&#7893;ng quan</a>
        <a class="sidebar-nav-item" data-t="create"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>T&#7841;o m&#7899;i</a>
        <a class="sidebar-nav-item" data-t="campaigns"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>Chi&#7871;n d&#7883;ch</a>
        <a class="sidebar-nav-item" data-t="deposit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>N&#7841;p ti&#7873;n</a>
        <a class="sidebar-nav-item" data-t="history"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>L&#7883;ch s&#7917;</a>
        <a class="sidebar-nav-item" data-t="account"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>T&#224;i kho&#7843;n</a>
    </nav>
    <div class="sidebar-bottom">
        <a href="<?php echo home_url(); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Trang ch&#7911;</a>
        <a href="<?php echo wp_logout_url(home_url()); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>&#272;&#259;ng xu&#7845;t</a>
    </div>
</aside>

<!-- Mobile top bar -->
<div class="mobile-topbar">
    <?php $ln_icon = get_option('sitetop_widget_icon',''); ?>
    <a href="<?php echo home_url(); ?>" class="mobile-topbar-logo">
        <img src="<?php echo esc_url( $ln_icon ?: sitetop_logo_url('tft-logo.png') ); ?>" width="20" height="20" alt="" style="vertical-align:middle;border-radius:50%">
        <span><span class="lgd">SITE</span><span class="lgb">TOP</span></span>
    </a>
    <div class="mobile-topbar-right">
        <span class="bal"><?php echo sitetop_format_money($cust_balance); ?></span>
        <span class="avatar"><?php echo strtoupper(substr($user->display_name,0,1)); ?></span>
        <a href="<?php echo wp_logout_url(home_url()); ?>" style="color:var(--txtm);display:flex" title="Đăng xuất"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
    </div>
</div>

<!-- Bottom navigation (mobile) -->
<nav class="bottom-nav">
    <div class="bottom-nav-inner">
        <a class="bottom-nav-item on" data-t="overview"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg><span>T&#7893;ng quan</span></a>
        <a class="bottom-nav-item" data-t="create"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg><span>T&#7841;o m&#7899;i</span></a>
        <a class="bottom-nav-item" data-t="campaigns"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg><span>Chi&#7871;n d&#7883;ch</span></a>
        <a class="bottom-nav-item" data-t="deposit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg><span>N&#7841;p ti&#7873;n</span></a>
        <a class="bottom-nav-item" data-t="account"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>T&#224;i kho&#7843;n</span></a>
    </div>
</nav>
<?php endif; ?>

<!-- Main content area -->
<div class="main-wrap">
    <?php if(!$is_minimal): ?>
    <div class="main-topbar">
        <span class="main-topbar-title" id="mainTopbarTitle">T&#7893;ng quan</span>
        <span class="topbar-date">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            <?php echo date_i18n( 'd/m/Y', strtotime( sitetop_current_time() ) ); ?>
        </span>
    </div>
    <?php endif; ?>
    <div class="main-content">

    <?php if(!$is_minimal): ?>
    <!-- Th&#7867; v&#237;: s&#7889; d&#432; + thao t&#225;c nhanh -->
    <div class="wallet">
        <div class="wallet-l">
            <div class="wallet-lb">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
                S&#7889; d&#432; qu&#7843;ng c&#225;o
            </div>
            <div class="wallet-v"><?php echo sitetop_format_money($cust_balance); ?></div>
            <div class="wallet-meta">
                <span class="wallet-chip">&#272;&#227; n&#7841;p <b><?php echo sitetop_format_money($total_deposited); ?></b></span>
                <span class="wallet-chip">&#272;&#227; chi <b><?php echo sitetop_format_money($total_spent); ?></b></span>
                <span class="wallet-chip">&#272;ang ch&#7841;y <b><?php echo count($active_camps); ?> chi&#7871;n d&#7883;ch</b></span>
            </div>
        </div>
        <div class="wallet-r">
            <button type="button" class="wbtn-w" onclick="switchTab('deposit')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                N&#7841;p ti&#7873;n
            </button>
            <button type="button" class="wbtn-g" onclick="switchTab('create')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5"/></svg>
                T&#7841;o chi&#7871;n d&#7883;ch
            </button>
        </div>
    </div>

    <!-- Stats grid -->
    <div class="dash-stats">
        <div class="sc s1">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg></div>
            <div class="sc-text"><div class="sl">T&#7893;ng chi&#7871;n d&#7883;ch</div><div class="sv"><?php echo $camp_total; ?></div><div class="ss">&#272;ang ch&#7841;y <b><?php echo count($active_camps); ?></b></div></div>
        </div>
        <div class="sc s2">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
            <div class="sc-text"><div class="sl">T&#7893;ng views</div><div class="sv"><?php echo number_format($total_views); ?></div><div class="ss">H&#244;m nay <b>+<?php echo number_format($today_views); ?></b></div></div>
        </div>
        <div class="sc s3">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <div class="sc-text"><div class="sl">&#272;&#227; n&#7841;p</div><div class="sv"><?php echo sitetop_format_money($total_deposited); ?></div></div>
        </div>
        <div class="sc s4">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v13"/><path d="M7 12l5 5 5-5"/><path d="M4 21h16"/></svg></div>
            <div class="sc-text"><div class="sl">&#272;&#227; chi</div><div class="sv"><?php echo sitetop_format_money($total_spent); ?></div></div>
        </div>
    </div>
    <?php endif; ?>

<?php if(!$is_minimal): ?>
<!-- Overview -->
<div class="pane on" id="p-overview">

<!-- Announcements -->
<div class="ann-section" id="custAnnouncements" style="display:none"></div>

<div class="card">
<div class="card-h">
    <h3 style="margin:0">Biểu đồ 30 ngày gần nhất</h3>
    <div class="cd-chart-legend">
        <span class="lg-traffic">Traffic</span>
        <span class="lg-spent">Đã chi</span>
    </div>
</div>
<div class="cd-chart-container">
    <canvas id="cdChart"></canvas>
</div>
</div>

<div class="qs">
    <div class="qs-h">
        <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></i>
        Bắt đầu nhanh
    </div>
    <p class="qs-sub">Bốn bước để chiến dịch đầu tiên của bạn chạy được traffic.</p>
    <div class="qs-steps">
        <div class="qs-step">
            <em>1</em>
            <b>Nạp tiền</b>
            <span>Tối thiểu <?php echo sitetop_format_money(floatval(sitetop_get_option('min_deposit_amount',50000))); ?> qua chuyển khoản ngân hàng hoặc USDT.</span>
        </div>
        <div class="qs-step">
            <em>2</em>
            <b>Tạo chiến dịch</b>
            <span>Chọn loại traffic (Keyword/Direct), nhập từ khóa và URL đích.</span>
        </div>
        <div class="qs-step">
            <em>3</em>
            <b>Chờ duyệt</b>
            <span>Admin duyệt trong vòng 24h. Traffic chạy ngay sau khi duyệt.</span>
        </div>
        <div class="qs-step">
            <em>4</em>
            <b>Theo dõi</b>
            <span>Xem biểu đồ và lịch sử traffic, đối chiếu GSC &amp; Analytics.</span>
        </div>
    </div>
    <div class="qs-note">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
        Nên chạy liên tục 15-30 ngày với traffic vừa phải để đạt hiệu quả tốt nhất.
    </div>
</div>

</div><!-- /#p-overview -->
<?php endif; ?>

<!-- Create Campaign -->
<div class="pane<?php echo $is_minimal ? ' on' : ''; ?>" id="p-create">

<!-- Bước 1: loại dịch vụ -->
<div class="card">
    <div class="cc-h"><em>1</em><b>Ch&#7885;n lo&#7841;i d&#7883;ch v&#7909;</b></div>
    <div class="cc-svc" id="serviceTypes">
        <label class="svc-card selected" data-type="keyword_search">
            <input type="radio" name="task_type" value="keyword_search" checked style="display:none">
            <div class="svc-icon" style="background:linear-gradient(135deg,#1E5EFF,#00C6FF)"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21"/><path d="M8 10.5h5"/></svg></div>
            <div class="svc-t">
                <div class="svc-name">Traffic t&#7915; kh&#243;a</div>
                <div class="svc-desc">User t&#236;m t&#7915; kh&#243;a tr&#234;n Google r&#7891;i click v&#224;o web c&#7911;a b&#7841;n</div>
                <div class="svc-price">T&#7915; <?php echo sitetop_format_money(sitetop_get_option('keyword_price_1step', 1200)); ?>/l&#432;&#7907;t</div>
            </div>
        </label>
        <label class="svc-card" data-type="traffic_direct">
            <input type="radio" name="task_type" value="traffic_direct" style="display:none">
            <div class="svc-icon" style="background:linear-gradient(135deg,#1E5EFF,#00C6FF)"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div>
            <div class="svc-t">
                <div class="svc-name">Traffic Direct</div>
                <div class="svc-desc">User v&#224;o th&#7859;ng URL, kh&#244;ng qua b&#432;&#7899;c t&#236;m ki&#7871;m</div>
                <div class="svc-price">T&#7915; <?php echo sitetop_format_money(sitetop_get_option('direct_price_1step', 1200)); ?>/l&#432;&#7907;t</div>
            </div>
        </label>
    </div>
</div>

<form id="createCampForm">
    <input type="hidden" name="task_type" id="campTaskType" value="keyword_search">

    <!-- Bước 2: thông tin chiến dịch -->
    <div class="card">
        <div class="cc-h"><em>2</em><b>Th&#244;ng tin chi&#7871;n d&#7883;ch</b></div>

        <div style="display:grid;grid-template-columns:1fr 1fr 110px;gap:14px;margin-bottom:16px" id="kwFields">
            <div>
                <label class="cf-label">T&#7915; kh&#243;a c&#7847;n ch&#7841;y <span class="req">*</span></label>
                <input type="text" name="keyword" class="cf-input" placeholder="T&#7915; kh&#243;a c&#7847;n ch&#7841;y" id="campKeyword">
            </div>
            <div>
                <label class="cf-label">URL b&#224;i vi&#7871;t <span class="req">*</span></label>
                <input type="url" name="target_url" class="cf-input" placeholder="https://example.com/bai-viet" required id="campTargetUrl">
            </div>
            <div>
                <label class="cf-label">Traffic/ng&#224;y</label>
                <input type="number" name="daily_traffic" class="cf-input" id="createDailyTraffic" value="100" min="10" max="5000" oninput="checkDailyMin()">
                <div id="dailyMinWarn" style="display:none;font-size:11px;color:var(--err);margin-top:4px;font-weight:600">T&#7889;i thi&#7875;u 10 traffic/ng&#224;y</div>
            </div>
        </div>
        <!-- URL + daily traffic for Direct (shown when kwFields hidden) -->
        <div style="display:none;grid-template-columns:1fr 110px;gap:14px;margin-bottom:16px" id="directFields">
            <div>
                <label class="cf-label">URL b&#224;i vi&#7871;t <span class="req">*</span></label>
                <input type="url" name="target_url_direct" class="cf-input" placeholder="https://example.com/bai-viet" id="campTargetUrlDirect">
            </div>
            <div>
                <label class="cf-label">Traffic/ng&#224;y</label>
                <input type="number" name="daily_traffic_direct" class="cf-input" id="createDailyTrafficDirect" value="100" min="10" max="5000">
            </div>
        </div>
        <input type="hidden" name="title" value="">

        <!-- Screenshot upload -->
        <div id="screenshotSection">
            <label class="cf-label">&#7842;nh hi&#7875;n th&#7883; k&#7871;t qu&#7843; tr&#234;n Google <span class="req">*</span></label>
            <p class="cc-hint">Ch&#7909;p m&#224;n h&#236;nh v&#7883; tr&#237; website c&#7911;a b&#7841;n tr&#234;n k&#7871;t qu&#7843; t&#236;m ki&#7871;m Google &#273;&#7875; user d&#7877; t&#236;m th&#7845;y.</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div class="ss-upload" id="ssDesktopWrap">
                    <div class="ss-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg> Desktop</div>
                    <div class="ss-preview" id="ssDesktopPreview">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#B9C7E4" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <span>Ch&#432;a c&#243; &#7843;nh</span>
                    </div>
                    <label class="ss-btn" id="ssDesktopBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        T&#7843;i &#7843;nh
                        <input type="file" accept="image/*" style="display:none" onchange="imgbbUpload(this,'ssDesktopPreview','screenshot_desktop_url','ssDesktopBtn')">
                    </label>
                    <input type="hidden" name="screenshot_desktop_url" id="ssDesktopUrlHidden">
                </div>
                <div class="ss-upload" id="ssMobileWrap">
                    <div class="ss-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg> Mobile</div>
                    <div class="ss-preview" id="ssMobilePreview">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#B9C7E4" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <span>Ch&#432;a c&#243; &#7843;nh</span>
                    </div>
                    <label class="ss-btn" id="ssMobileBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        T&#7843;i &#7843;nh
                        <input type="file" accept="image/*" style="display:none" onchange="imgbbUpload(this,'ssMobilePreview','screenshot_mobile_url','ssMobileBtn')">
                    </label>
                    <input type="hidden" name="screenshot_mobile_url" id="ssMobileUrlHidden">
                </div>
            </div>
        </div>
    </div>

    <!-- Bước 3: gói traffic + onsite -->
    <div class="card">
        <div class="cc-h"><em>3</em><b>G&#243;i traffic &amp; th&#7901;i gian onsite</b></div>

        <label class="cf-label">Lo&#7841;i traffic</label>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px" id="trafficTypes">
            <label class="tt-option selected">
                <input type="radio" name="traffic_type" value="1step" checked>
                <span class="tt-label">G&#243;i 1 b&#432;&#7899;c</span>
                <span class="tt-price" id="price1step"><?php echo sitetop_format_money(sitetop_get_option('keyword_price_1step', 1200)); ?></span>
            </label>
            <label class="tt-option">
                <input type="radio" name="traffic_type" value="2step">
                <span class="tt-label">G&#243;i 2 b&#432;&#7899;c</span>
                <span class="tt-price" id="price2step"><?php echo sitetop_format_money(sitetop_get_option('keyword_price_2step', 1500)); ?></span>
            </label>
            <label class="tt-option">
                <input type="radio" name="traffic_type" value="nocode">
                <span class="tt-label">M&#227; c&#7889; &#273;&#7883;nh</span>
                <span class="tt-price" id="priceNocode"><?php echo sitetop_format_money(sitetop_get_option('keyword_price_nocode', 1200)); ?></span>
            </label>
        </div>

        <!-- Nocode: Fixed code + screenshot (hidden by default, shown when nocode selected) -->
        <div id="nocodeFields" style="display:none;margin-bottom:16px">
            <div class="cc-nocode">
                <div class="cc-nocode-h">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                    G&#243;i m&#227; c&#7889; &#273;&#7883;nh c&#7847;n th&#234;m 2 th&#244;ng tin
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div>
                        <label class="cf-label">M&#227; x&#225;c nh&#7853;n c&#7889; &#273;&#7883;nh <span class="req">*</span></label>
                        <input type="text" name="fixed_code" class="cf-input" placeholder="VD: ABC123, PROMO2024..." id="campFixedCode">
                        <div class="cc-hint" style="margin:5px 0 0">M&#227; hi&#7875;n th&#7883; tr&#234;n trang &#273;&#237;ch</div>
                    </div>
                    <div>
                        <label class="cf-label">&#7842;nh v&#7883; tr&#237; m&#227; <span class="req">*</span></label>
                        <div class="ss-upload" id="ssNocodeWrap" style="padding:10px">
                            <div class="ss-preview" id="ssNocodePreview" style="min-height:60px">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#B9C7E4" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                <span style="font-size:11px">Ch&#432;a c&#243; &#7843;nh</span>
                            </div>
                            <label class="ss-btn" id="ssNocodeBtn" style="padding:7px;font-size:12px">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                T&#7843;i &#7843;nh
                                <input type="file" accept="image/*" style="display:none" onchange="imgbbUpload(this,'ssNocodePreview','nocode_screenshot_url','ssNocodeBtn')">
                            </label>
                            <input type="hidden" name="nocode_screenshot_url" id="ssNocodeUrlHidden">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <label class="cf-label">Th&#7901;i gian onsite</label>
        <?php $oe_cust = array(70=>(int)sitetop_get_option('onsite_extra_70',0),80=>(int)sitetop_get_option('onsite_extra_80',100),90=>(int)sitetop_get_option('onsite_extra_90',200),100=>(int)sitetop_get_option('onsite_extra_100',300),120=>(int)sitetop_get_option('onsite_extra_120',400),150=>(int)sitetop_get_option('onsite_extra_150',500)); ?>
        <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:8px" id="onsiteTimes">
            <?php $first=true; foreach($oe_cust as $s=>$e): ?>
            <label class="ot-option<?php if($first){echo ' selected';$first=false;} ?>"><input type="radio" name="onsite_time" value="<?php echo $s; ?>"<?php if($s==70) echo ' checked'; ?>><span><?php echo $s; ?>s</span><?php if($e>0): ?><small>+<?php echo number_format($e); ?>&#273;</small><?php endif; ?></label>
            <?php endforeach; ?>
        </div>
        <div class="cc-hint" style="margin-top:9px">Onsite c&#224;ng l&#226;u th&#236; t&#237;n hi&#7879;u g&#7917;i v&#7873; Google c&#224;ng t&#7889;t, ph&#7909; thu t&#237;nh tr&#234;n m&#7895;i l&#432;&#7907;t.</div>
    </div>

    <span id="priceDisplay" style="display:none"></span>

    <!-- Ước tính chi phí + gửi -->
    <div class="cc-est">
        <div class="cc-est-h">
            <b>&#431;&#7899;c t&#237;nh chi ph&#237;</b>
            <div class="cc-days">
                <label for="campDays">S&#7889; ng&#224;y ch&#7841;y</label>
                <input type="number" name="days" value="30" min="1" max="365" id="campDays">
            </div>
        </div>
        <div class="cc-est-nums">
            <div><span class="k">T&#7893;ng traffic</span><span class="v" id="estTotal">3000</span></div>
            <div><span class="k">Chi ph&#237;/ng&#224;y</span><span class="v" id="estDaily">120.000&#273;</span></div>
            <div class="hi"><span class="k">T&#7893;ng chi ph&#237;</span><span class="v" id="estTotalCost">3.600.000&#273;</span></div>
        </div>
        <div class="cc-est-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
            Khuy&#7871;n ngh&#7883;: n&#234;n ch&#7841;y t&#7889;i thi&#7875;u <b>30 ng&#224;y</b> &#273;&#7875; mang l&#7841;i hi&#7879;u qu&#7843; cao nh&#7845;t cho SEO.
        </div>
        <button type="submit" id="campSubmitBtn" class="cc-submit">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            <span class="cc-submit-t">T&#7841;o chi&#7871;n d&#7883;ch</span>
        </button>
    </div>
    <div id="campMsg" class="cc-msg"></div>

    <div class="cc-info">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
        <span>Chi&#7871;n d&#7883;ch s&#7869; &#273;&#432;&#7907;c Admin duy&#7879;t tr&#432;&#7899;c khi ch&#7841;y. Ti&#7873;n tr&#7915; d&#7847;n theo t&#7915;ng l&#432;&#7907;t traffic ho&#224;n th&#224;nh. Y&#234;u c&#7847;u s&#7889; d&#432; t&#7889;i thi&#7875;u <b><?php echo sitetop_format_money(sitetop_get_option('customer_min_balance', 20000)); ?></b>.</span>
    </div>
</form>

<!-- Mã gắn vào Website -->
<div class="card">
    <div class="card-h"><h3>M&#227; g&#7855;n v&#224;o Website</h3></div>

    <div class="cc-info" style="margin-bottom:14px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
        <span>&#193;p d&#7909;ng cho <b>G&#243;i 1 b&#432;&#7899;c</b> v&#224; <b>G&#243;i 2 b&#432;&#7899;c</b>. G&#7855;n &#273;o&#7841;n m&#227; v&#224;o HTML ho&#7863;c m&#7909;c c&#224;i &#273;&#7863;t Script c&#7911;a web &#8212; v&#7883; tr&#237; n&#224;o cho ph&#233;p g&#7855;n script &#273;&#7873;u &#273;&#432;&#7907;c.</span>
    </div>

    <div class="cc-code">
        <button type="button" class="cp" onclick="copyWidgetCode()">Copy</button>
        <code>&lt;script src="<?php echo esc_html(home_url('/top.js')); ?>" async&gt;&lt;/script&gt;</code>
    </div>
    <div id="widgetCopyMsg" class="cc-copied"></div>

    <div class="cc-hint" style="margin-top:14px">
        N&#234;n th&#432;&#7901;ng xuy&#234;n &#273;&#7893;i v&#7883; tr&#237; g&#7855;n m&#227; thay v&#236; c&#7889; &#273;&#7883;nh m&#7897;t ch&#7895; &#273;&#7875; &#273;&#7841;t hi&#7879;u qu&#7843; SEO cao nh&#7845;t. Khi g&#7855;n th&#224;nh c&#244;ng, tr&#234;n website s&#7869; xu&#7845;t hi&#7879;n n&#250;t ki&#7875;m tra &#8212; user c&#243; th&#7875; t&#7921; t&#236;m t&#7915; kh&#243;a tr&#234;n Google r&#7891;i click v&#224;o k&#7871;t qu&#7843; &#273;&#7875; ki&#7875;m ch&#7913;ng.
    </div>

    <div class="cc-warn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
        <span>G&#243;i <b>M&#227; c&#7889; &#273;&#7883;nh</b> kh&#244;ng c&#7847;n g&#7855;n m&#227;. M&#227; l&#224; th&#244;ng tin c&#243; s&#7861;n tr&#234;n website c&#7911;a b&#7841;n (S&#272;T, Email, MST...).</span>
    </div>
</div>
</div>

<?php if(!$is_minimal): ?>
<!-- Campaigns -->
<div class="pane" id="p-campaigns">
<div class="card">
    <div style="padding:14px 18px 10px">
    <?php
        $camp_count_by_status = array('active'=>0,'pending'=>0,'paused'=>0,'completed'=>0,'rejected'=>0);
        foreach($my_campaigns as $c){ if(isset($camp_count_by_status[$c->status])) $camp_count_by_status[$c->status]++; }
    ?>
    <style>.camp-pills{display:grid;grid-template-columns:repeat(5,1fr);gap:4px}.camp-pill{font-size:12px;padding:5px 10px;border-radius:12px;text-align:center;cursor:pointer;font-weight:500;background:var(--bg);color:var(--txtm);border:none;line-height:1.4;transition:all .15s}.camp-pill.on{font-weight:600;color:#fff}@media(max-width:768px){.camp-pills{grid-template-columns:repeat(3,1fr)}}</style>
    <div class="camp-pills">
        <button class="camp-pill on" onclick="filterCampStatus('active')" data-cs="active" style="background:var(--ok);color:#fff">Đang chạy (<?php echo $camp_count_by_status['active']; ?>)</button>
        <button class="camp-pill" onclick="filterCampStatus('pending')" data-cs="pending">Chờ duyệt (<?php echo $camp_count_by_status['pending']; ?>)</button>
        <button class="camp-pill" onclick="filterCampStatus('paused')" data-cs="paused">Tạm dừng (<?php echo $camp_count_by_status['paused']; ?>)</button>
        <button class="camp-pill" onclick="filterCampStatus('completed')" data-cs="completed">Hoàn thành (<?php echo $camp_count_by_status['completed']; ?>)</button>
        <button class="camp-pill" onclick="filterCampStatus('rejected')" data-cs="rejected">Từ chối (<?php echo $camp_count_by_status['rejected']; ?>)</button>
    </div>
    </div>
<?php if(empty($my_campaigns)): ?>
    <div style="text-align:center;padding:40px;color:var(--txtm)">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#D1CEC7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:8px"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <p>Chưa có campaign. Liên hệ admin để tạo!</p>
    </div>
<?php else: ?>
    <div style="overflow-x:auto">
    <table>
    <thead><tr>
        <th style="min-width:180px">Từ khóa / URL</th>
        <th style="white-space:nowrap">Loại traffic</th>
        <th>Gói/Onsite</th>
        <th>Giá</th>
        <th>Traffic/ngày</th>
        <th>Đã chạy</th>
        <th>Gắn mã</th>
        <th>Trạng thái</th>
        <th>Thao tác</th>
        <th>Thời gian</th>
    </tr></thead>
    <tbody id="campaignsListContainer">
    <?php foreach($my_campaigns as $c):
        $domain = parse_url($c->target_url ?? '', PHP_URL_HOST);
        $task_icons = array('keyword_search'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>','traffic_direct'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>','traffic_social'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>');
        $task_labels = array('keyword_search'=>'Keyword','traffic_direct'=>'Direct','traffic_social'=>'Social');
        $task_colors = array('keyword_search'=>'b-info','traffic_direct'=>'b-warn','traffic_social'=>'b-mute');
        $step_labels = array('1step'=>'1 bước','2step'=>'2 bước','nocode'=>'Mã cố định');
        $status_labels = array('active'=>'Đang chạy','paused'=>'Tạm dừng','pending'=>'Chờ duyệt','rejected'=>'Từ chối');
        $status_colors = array('active'=>'b-ok','paused'=>'b-warn','pending'=>'b-info','rejected'=>'b-err');
        $tt = $c->task_type ?? 'keyword_search';
        $spent = $c->total_completed * ($c->price_per_view ?? 0);
    ?>
    <tr data-camp-status="<?php echo esc_attr($c->status); ?>"<?php if($c->status !== 'active') echo ' style="display:none"'; ?>>
        <td>
            <div style="display:flex;align-items:flex-start;gap:8px">
                <span style="color:var(--info);margin-top:2px"><?php echo $task_icons[$tt] ?? ''; ?></span>
                <div>
                    <div style="font-weight:600;font-size:13px"><?php echo esc_html($c->keyword ?: $c->title); ?></div>
                    <?php if($domain): ?><div style="font-size:11px;color:var(--txtm)"><?php echo esc_html($domain); ?></div><?php endif; ?>
                </div>
            </div>
        </td>
        <td style="white-space:nowrap"><span class="badge <?php echo $task_colors[$tt] ?? 'b-mute'; ?>"><?php echo $task_labels[$tt] ?? $tt; ?></span></td>
        <td>
            <div style="font-weight:600;font-size:12px"><?php echo $step_labels[$c->traffic_type] ?? $c->traffic_type; ?></div>
            <div style="font-size:10px;color:var(--txtm)"><?php echo (int)$c->onsite_time; ?>s</div>
            <?php if($c->traffic_type === 'nocode' && !empty($c->fixed_code)): ?>
            <div style="font-size:10px;color:var(--a);font-weight:600;margin-top:2px"><?php echo esc_html($c->fixed_code); ?></div>
            <?php endif; ?>
        </td>
        <td style="font-weight:600;color:var(--a)"><?php echo sitetop_format_money($c->price_per_view ?? 0); ?></td>
        <td>
            <div style="font-size:12px"><span style="color:var(--a);font-weight:600"><?php echo (int)$c->today_views; ?></span>/<?php echo (int)$c->daily_traffic; ?></div>
        </td>
        <td>
            <div style="font-weight:600;font-size:12px"><?php echo number_format((int)$c->total_completed); ?></div>
            <div style="font-size:10px;color:var(--txtm);margin-top:2px"><?php echo sitetop_format_money($spent); ?></div>
        </td>
        <td style="white-space:nowrap">
            <?php if($c->traffic_type === 'nocode'): ?>
                <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;color:var(--txtm)"><span style="width:8px;height:8px;border-radius:50%;background:var(--txtm);display:inline-block"></span> Không cần</span>
            <?php elseif(!empty($c->screenshot_desktop_url) || !empty($c->screenshot_mobile_url)): ?>
                <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;color:var(--ok)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--ok)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Đã gắn</span>
            <?php else: ?>
                <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;color:var(--warn)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--warn)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> Chưa gắn</span>
            <?php endif; ?>
        </td>
        <td style="white-space:nowrap"><span class="badge <?php echo $status_colors[$c->status] ?? 'b-mute'; ?>"><?php echo $status_labels[$c->status] ?? $c->status; ?></span></td>
        <td>
            <div style="display:flex;gap:6px;align-items:center">
                <?php if($c->status === 'active'): ?>
                <button onclick="viewCampaignDetail(<?php echo $c->id; ?>)" style="width:32px;height:32px;border-radius:8px;border:1px solid var(--brdl);background:var(--card);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;color:var(--info)" title="Xem chi tiết"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                <button onclick="editCampaign(<?php echo $c->id; ?>)" style="width:32px;height:32px;border-radius:8px;border:1px solid var(--brdl);background:var(--card);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;color:var(--a)" title="Chỉnh sửa"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                <button onclick="toggleCampaign(<?php echo $c->id; ?>,'paused')" style="width:32px;height:32px;border-radius:8px;border:none;background:var(--warn);color:#fff;cursor:pointer;display:inline-flex;align-items:center;justify-content:center" title="Tạm dừng"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg></button>
                <?php elseif($c->status === 'paused'): ?>
                <button onclick="editCampaign(<?php echo $c->id; ?>)" style="width:32px;height:32px;border-radius:8px;border:none;background:var(--info);color:#fff;cursor:pointer;display:inline-flex;align-items:center;justify-content:center" title="Chỉnh sửa"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                <button onclick="toggleCampaign(<?php echo $c->id; ?>,'active')" style="width:32px;height:32px;border-radius:8px;border:none;background:var(--ok);color:#fff;cursor:pointer;display:inline-flex;align-items:center;justify-content:center" title="Tiếp tục chạy"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg></button>
                <button onclick="deleteCampaign(<?php echo $c->id; ?>)" style="width:32px;height:32px;border-radius:8px;border:none;background:#fde8e8;color:var(--err);cursor:pointer;display:inline-flex;align-items:center;justify-content:center" title="Xóa"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg></button>
                <?php else: ?>
                <button onclick="viewCampaignDetail(<?php echo $c->id; ?>)" style="width:32px;height:32px;border-radius:8px;border:1px solid var(--brdl);background:var(--card);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;color:var(--info)" title="Xem chi tiết"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                <?php endif; ?>
            </div>
        </td>
        <td style="white-space:nowrap"><small><?php echo date('d/m/Y H:i', strtotime($c->created_at)); ?></small></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>
    <?php if($camp_pages > 1): ?>
    <div class="cust-paging"><?php for($i=1;$i<=$camp_pages;$i++): ?><a href="?tab=campaigns&camp_page=<?php echo $i; ?>" class="pg-btn<?php echo $i===$camp_page?' on':''; ?>"><?php echo $i; ?></a><?php endfor; ?></div>
    <?php endif; ?>
<?php endif; ?>
</div>
</div>

<!-- Deposit -->
<?php
    $show_bank = (int) sitetop_get_option('deposit_show_bank', 1);
    $show_erc20 = (int) sitetop_get_option('deposit_show_erc20', 1);
    $show_trc20 = (int) sitetop_get_option('deposit_show_trc20', 1);
    $usdt_erc = $show_erc20 ? sitetop_get_option('deposit_usdt_erc20','') : '';
    $usdt_trc = $show_trc20 ? sitetop_get_option('deposit_usdt_trc20','') : '';
?>
<div class="pane" id="p-deposit">
<?php
$dep_min   = floatval(sitetop_get_option('min_deposit_amount', 50000));
$usdt_rate = intval(sitetop_get_option('deposit_usdt_rate', 25000));
$presets   = json_decode(sitetop_get_option('deposit_presets','[]'), true);
if(empty($presets)) $presets = array(
    array('amount' => 500000, 'bonus' => 0),
    array('amount' => 1000000, 'bonus' => 0),
    array('amount' => 5000000, 'bonus' => 0),
    array('amount' => 10000000, 'bonus' => 5),
    array('amount' => 20000000, 'bonus' => 5),
    array('amount' => 50000000, 'bonus' => 10),
);
?>
<div class="deposit-row">

<!-- Tạo đơn nạp tiền -->
<div class="card">
    <div class="card-h"><h3>T&#7841;o &#273;&#417;n n&#7841;p ti&#7873;n</h3></div>
    <form id="depositForm">

        <div class="dep-step">
            <div class="dep-step-h"><em>1</em><b>S&#7889; ti&#7873;n mu&#7889;n n&#7841;p</b></div>
            <div class="dep-amount">
                <input type="number" name="amount" id="depAmount" placeholder="0" min="<?php echo $dep_min; ?>" required>
                <span>&#273;</span>
            </div>
            <div class="dep-presets" id="depPresets">
                <?php foreach ($presets as $p):
                    $label = $p['amount'] >= 1000000 ? ($p['amount']/1000000).'M' : number_format($p['amount']/1000).'K';
                ?>
                <button type="button" class="dep-preset" onclick="document.getElementById('depAmount').value=<?php echo $p['amount']; ?>;updateDepBonus();updateUsdtConvert()">
                    <?php echo $label; ?>
                    <?php if($p['bonus'] > 0): ?><span class="dep-bonus-tag">+<?php echo $p['bonus']; ?>%</span><?php endif; ?>
                </button>
                <?php endforeach; ?>
            </div>
            <div class="dep-hint">T&#7889;i thi&#7875;u <b><?php echo sitetop_format_money($dep_min); ?></b> m&#7895;i &#273;&#417;n</div>
            <?php if ($usdt_rate > 0 && ($usdt_erc || $usdt_trc)): ?>
            <div id="depUsdtConvert" class="dep-convert" style="display:none"></div>
            <?php endif; ?>
        </div>

        <div class="dep-step">
            <div class="dep-step-h"><em>2</em><b>H&#236;nh th&#7913;c n&#7841;p</b></div>
            <div class="dep-methods">
                <?php if ($show_bank): ?>
                <label class="pay-method-option selected" onclick="selectPayMethod(this)">
                    <input type="radio" name="payment_method" value="bank" checked>
                    <span class="m-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10l9-6 9 6"/><path d="M5 10v9M9 10v9M15 10v9M19 10v9"/><path d="M3 21h18"/></svg></span>
                    <span><span class="m-t">Ng&#226;n h&#224;ng</span><span class="m-s">Chuy&#7875;n kho&#7843;n n&#7897;i &#273;&#7883;a</span></span>
                </label>
                <?php endif; ?>
                <?php if ($usdt_erc || $usdt_trc): ?>
                <label class="pay-method-option<?php echo !$show_bank ? ' selected' : ''; ?>" onclick="selectPayMethod(this)">
                    <input type="radio" name="payment_method" value="usdt" <?php echo !$show_bank ? 'checked' : ''; ?>>
                    <span class="m-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 9h8"/><path d="M12 9v8"/></svg></span>
                    <span><span class="m-t">USDT</span><span class="m-s">Crypto (ERC20/TRC20)</span></span>
                </label>
                <?php endif; ?>
            </div>
        </div>

        <div id="depBonusInfo" class="dep-bonus" style="display:none">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12v9H4v-9"/><path d="M2 7h20v5H2z"/><path d="M12 21V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
            <span><b>Khuy&#7871;n m&#227;i:</b> <span id="depBonusText"></span></span>
        </div>

        <button type="submit" id="depSubmitBtn" class="dep-submit">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            <span class="dep-submit-t">T&#7841;o &#273;&#417;n n&#7841;p ti&#7873;n</span>
        </button>
        <div id="depMsg" class="dep-msg"></div>
    </form>
</div>

<!-- Thông tin chuyển khoản -->
<div class="card">
    <div class="card-h"><h3>Th&#244;ng tin chuy&#7875;n kho&#7843;n</h3></div>

    <div class="cc-info">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
        <span>T&#7841;o &#273;&#417;n tr&#432;&#7899;c, sau &#273;&#243; chuy&#7875;n kho&#7843;n theo th&#244;ng tin b&#234;n d&#432;&#7899;i.</span>
    </div>

    <?php if ($show_bank): ?>
    <div class="dep-box">
        <div class="dep-box-tag">Chuy&#7875;n kho&#7843;n ng&#226;n h&#224;ng</div>
        <dl class="dep-info">
            <dt>Ng&#226;n h&#224;ng</dt>
            <dd><?php echo esc_html(sitetop_get_option('deposit_bank','Vietcombank')); ?></dd>
            <dt>S&#7889; t&#224;i kho&#7843;n</dt>
            <dd class="with-copy">
                <span id="bankAccount"><?php echo esc_html(sitetop_get_option('deposit_account','0123456789')); ?></span>
                <button type="button" class="dep-copy" onclick="copyText('<?php echo esc_js(sitetop_get_option('deposit_account','0123456789')); ?>',this)">Copy</button>
            </dd>
            <dt>Ch&#7911; t&#224;i kho&#7843;n</dt>
            <dd><?php echo esc_html(sitetop_get_option('deposit_holder','SITETOP')); ?></dd>
        </dl>
    </div>
    <div class="cc-warn" style="margin-top:0">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
        <span>N&#7897;i dung chuy&#7875;n kho&#7843;n &#273;&#7875; m&#7863;c &#273;&#7883;nh. Chuy&#7875;n xong vui l&#242;ng g&#7917;i bill cho Admin &#273;&#7875; &#273;&#432;&#7907;c c&#7897;ng ti&#7873;n.</span>
    </div>
    <?php endif; ?>

    <?php if ($usdt_erc || $usdt_trc): ?>
    <div class="dep-usdt-h">N&#7841;p b&#7857;ng USDT</div>
    <?php if ($usdt_erc): ?>
    <div class="dep-wallet">
        <div class="dep-wallet-t">USDT &#183; ERC20</div>
        <div class="cc-code">
            <button type="button" class="cp" onclick="copyText('<?php echo esc_js($usdt_erc); ?>',this)">Copy</button>
            <code><?php echo esc_html($usdt_erc); ?></code>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($usdt_trc): ?>
    <div class="dep-wallet">
        <div class="dep-wallet-t">USDT &#183; TRC20</div>
        <div class="cc-code">
            <button type="button" class="cp" onclick="copyText('<?php echo esc_js($usdt_trc); ?>',this)">Copy</button>
            <code><?php echo esc_html($usdt_trc); ?></code>
        </div>
    </div>
    <?php endif; ?>
    <div class="cc-warn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
        <span>&#272;&#7889;i chi&#7871;u ch&#237;nh x&#225;c <b>3 k&#253; t&#7921; &#273;&#7847;u</b> v&#224; <b>3 k&#253; t&#7921; cu&#7889;i</b> c&#7911;a v&#237; tr&#432;&#7899;c khi chuy&#7875;n &#8212; g&#7917;i sai &#273;&#7883;a ch&#7881; l&#224; m&#7845;t ti&#7873;n.</span>
    </div>
    <?php endif; ?>
</div>
</div><!-- /.deposit-row -->

<!-- Lịch sử nạp tiền -->
<div class="card">
    <div class="card-h"><h3>L&#7883;ch s&#7917; n&#7841;p ti&#7873;n</h3><span class="dep-count">T&#7893;ng <?php echo count($deposits); ?> &#273;&#417;n</span></div>
    <div class="dep-list" id="depositsListContainer">
    <?php if(empty($deposits)): ?>
    <div class="dep-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="3"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        <b>Ch&#432;a c&#243; &#273;&#417;n n&#7841;p n&#224;o</b>
        <small>C&#225;c l&#7847;n n&#7841;p ti&#7873;n c&#7911;a b&#7841;n s&#7869; hi&#7879;n &#7903; &#273;&#226;y.</small>
    </div>
    <?php else: foreach($deposits as $dep):
        $bc=array('pending'=>'b-warn','approved'=>'b-ok','rejected'=>'b-err');
        $bl=array('pending'=>'Ch&#7901; duy&#7879;t','approved'=>'&#272;&#227; duy&#7879;t','rejected'=>'T&#7915; ch&#7889;i');
        $bonus = isset($dep->bonus_amount) ? (float)$dep->bonus_amount : 0;
        $total = (float)$dep->amount + $bonus;
        $pm    = $dep->payment_method ?? 'bank';
    ?>
    <div class="dep-item dep-<?php echo esc_attr($dep->status); ?>">
        <div class="dep-item-top">
            <span class="dep-item-amt"><?php echo sitetop_format_money($total); ?></span>
            <span class="badge <?php echo $bc[$dep->status]??'b-mute'; ?>"><?php echo $bl[$dep->status] ?? esc_html($dep->status); ?></span>
        </div>
        <div class="dep-item-meta">
            <span class="dep-tag"><?php echo ($pm==='usdt' && $usdt_rate > 0) ? number_format((float)$dep->amount / $usdt_rate, 1) . ' USDT' : 'Chuy&#7875;n kho&#7843;n'; ?></span>
            <span>N&#7841;p <?php echo sitetop_format_money($dep->amount); ?></span>
            <?php if($bonus > 0): ?><span class="dep-plus">Khuy&#7871;n m&#227;i +<?php echo sitetop_format_money($bonus); ?></span><?php endif; ?>
        </div>
        <div class="dep-item-foot">#<?php echo (int)$dep->id; ?> &#183; <?php echo date('H:i &#183; d/m/Y',strtotime($dep->created_at)); ?></div>
        <?php if(!empty($dep->note)): ?><div class="dep-note"><?php echo esc_html($dep->note); ?></div><?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
    </div>
    <?php if($dep_pages > 1): ?>
    <div class="cust-paging"><?php for($i=1;$i<=$dep_pages;$i++): ?><a href="?tab=deposit&dep_page=<?php echo $i; ?>" class="pg-btn<?php echo $i===$dep_page?' on':''; ?>"><?php echo $i; ?></a><?php endfor; ?></div>
    <?php endif; ?>
</div>
</div>

<!-- History -->
<div class="pane" id="p-history">

<!-- Lịch sử hoàn thành (visits) -->
<div class="card">
    <div class="card-h"><h3>Lịch sử hoàn thành</h3></div>
    <div style="overflow-x:auto">
    <table style="min-width:700px"><thead><tr><th style="white-space:nowrap">Thời gian</th><th>Từ khóa / URL</th><th style="white-space:nowrap">Loại</th><th style="white-space:nowrap">Chi phí</th><th style="white-space:nowrap">Trạng thái</th><th style="white-space:nowrap">IP</th><th style="white-space:nowrap">Thiết bị</th></tr></thead><tbody id="visitsListContainer">
    <?php if(empty($visit_history)): ?>
    <tr><td colspan="7" style="text-align:center;color:var(--txtm)">Chưa có</td></tr>
    <?php else: foreach($visit_history as $vh):
        $task_label = array('keyword_search'=>'Từ khóa','traffic_direct'=>'Direct','traffic_social'=>'Social');
        $step_map = array('1step'=>'1 bước','2step'=>'2 bước','nocode'=>'Mã cố định');
        $domain = parse_url($vh->target_url, PHP_URL_HOST);
        // Parse device from user_agent
        $ua = $vh->user_agent ?? '';
        $device = 'Unknown';
        if (stripos($ua,'Android')!==false) {
            preg_match('/Android\s*([\d.]+)/', $ua, $am);
            $device = 'Android' . (isset($am[1]) ? " ({$am[1]})" : '');
        } elseif (stripos($ua,'iPhone')!==false) {
            $device = 'iPhone';
        } elseif (stripos($ua,'Windows')!==false) {
            $device = stripos($ua,'Windows NT 10')!==false ? 'Win10/11' : 'Windows';
            if (stripos($ua,'Chrome')!==false) $device .= ' Chrome';
            elseif (stripos($ua,'Firefox')!==false) $device .= ' Firefox';
        } elseif (stripos($ua,'Mac')!==false) {
            $device = 'macOS';
            if (stripos($ua,'Chrome')!==false) $device .= ' Chrome';
            elseif (stripos($ua,'Safari')!==false) $device .= ' Safari';
        }
        $cost = $vh->price_per_view ?? 0;
    ?>
    <tr>
        <td><small><?php echo date('d/m/Y', strtotime($vh->created_at)); ?><br><?php echo date('H:i:s', strtotime($vh->created_at)); ?></small></td>
        <td style="white-space:nowrap">
            <?php if($vh->keyword): ?>
                <div style="font-weight:600;font-size:12px"><?php echo esc_html($vh->keyword); ?></div>
            <?php else: ?>
                <div style="font-weight:600;font-size:12px"><?php echo esc_html($vh->campaign_title); ?></div>
            <?php endif; ?>
            <?php if($domain): ?><div style="font-size:11px;color:var(--txtm)"><?php echo esc_html($domain); ?></div><?php endif; ?>
        </td>
        <td style="white-space:nowrap">
            <span class="badge b-info"><?php echo $task_label[$vh->task_type ?? ''] ?? 'Traffic'; ?></span>
            <div style="font-size:10px;color:var(--txtm);margin-top:2px"><?php echo $step_map[$vh->traffic_type] ?? $vh->traffic_type; ?> / <?php echo (int)$vh->onsite_time; ?>s</div>
        </td>
        <td style="white-space:nowrap;font-weight:600;color:var(--err)">-<?php echo sitetop_format_money($cost); ?></td>
        <td style="white-space:nowrap">
            <span class="badge b-ok">Hoàn thành</span>
        </td>
        <td><small style="font-family:var(--mono);font-size:10px"><?php echo esc_html($vh->ip_address); ?></small></td>
        <td><small style="font-size:11px"><?php echo esc_html($device); ?></small></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody></table>
    <?php if($hist_pages > 1): ?>
    <div class="cust-paging"><?php for($i=1;$i<=$hist_pages;$i++): ?><a href="?tab=history&hist_page=<?php echo $i; ?>" class="pg-btn<?php echo $i===$hist_page?' on':''; ?>"><?php echo $i; ?></a><?php endfor; ?></div>
    <?php endif; ?>
    </div>
</div>
</div>

<!-- Account -->
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
                <span class="acc-chip acc-chip-role">Advertiser</span>
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
        <div><span class="k">Chi&#7871;n d&#7883;ch</span><span class="v"><?php echo number_format($camp_total); ?></span></div>
        <div><span class="k">T&#7893;ng views</span><span class="v"><?php echo number_format($total_views); ?></span></div>
        <div><span class="k">&#272;&#227; n&#7841;p</span><span class="v ok"><?php echo sitetop_format_money($total_deposited); ?></span></div>
        <div><span class="k">S&#7889; d&#432;</span><span class="v ok"><?php echo sitetop_format_money($cust_balance); ?></span></div>
    </div>
</div>

<div class="acc-grid account-grid">

<!-- Thông tin liên hệ -->
<div class="card">
    <div class="card-h"><h3>Th&#244;ng tin li&#234;n h&#7879;</h3></div>
    <div class="acc-f">
        <label>T&#234;n &#273;&#259;ng nh&#7853;p</label>
        <div class="acc-in">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input type="text" value="<?php echo esc_attr($user->user_login); ?>" disabled>
        </div>
    </div>
    <form id="frmProfile" onsubmit="return saveProfile(this)">
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
            T&#234;n &#273;&#259;ng nh&#7853;p kh&#244;ng &#273;&#7893;i &#273;&#432;&#7907;c. S&#7889; &#273;i&#7879;n tho&#7841;i d&#249;ng &#273;&#7875; li&#234;n h&#7879; khi c&#243; v&#7845;n &#273;&#7873; v&#7873; chi&#7871;n d&#7883;ch.
        </div>
    </form>
</div>

<!-- Bảo mật -->
<div class="card">
    <div class="card-h"><h3>&#272;&#7893;i m&#7853;t kh&#7849;u</h3></div>
    <form id="frmPassword" onsubmit="return changePassword(this)">
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
        <div class="acc-tip">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            T&#7889;i thi&#7875;u 6 k&#253; t&#7921;. N&#234;n d&#249;ng m&#7853;t kh&#7849;u ri&#234;ng, kh&#244;ng tr&#249;ng v&#7899;i c&#225;c trang kh&#225;c.
        </div>
    </form>
</div>

</div><!-- /grid -->
</div><!-- /p-account -->

<?php endif; ?>

</div>

<!-- Campaign Detail Modal -->
<div id="campDetailModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px">
    <div style="background:var(--card);border-radius:var(--rad);width:100%;max-width:600px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 20px;border-bottom:1px solid var(--brdl)">
            <h3 style="font-family:var(--fonth);font-size:16px;color:var(--pd)">Chi tiết chiến dịch</h3>
            <button onclick="closeCampModal()" style="width:30px;height:30px;border-radius:6px;border:1px solid var(--brdl);background:var(--card);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;color:var(--txtm)">&times;</button>
        </div>
        <div id="campDetailContent" style="padding:20px">Đang tải...</div>
    </div>
</div>

<!-- Edit Campaign Modal -->
<div id="campEditModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px">
    <div style="background:var(--card);border-radius:var(--rad);width:100%;max-width:640px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 20px;border-bottom:1px solid var(--brdl)">
            <h3 style="font-family:var(--fonth);font-size:16px;color:var(--pd)">Chỉnh sửa chiến dịch</h3>
            <button onclick="closeEditModal()" style="width:30px;height:30px;border-radius:6px;border:1px solid var(--brdl);background:var(--card);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;color:var(--txtm)">&times;</button>
        </div>
        <form id="editCampForm" style="padding:20px" enctype="multipart/form-data">
            <input type="hidden" id="editCampId">

            <div style="display:grid;grid-template-columns:1fr 1fr 100px;gap:14px;margin-bottom:14px" id="editKwFields">
                <div id="editKwCell">
                    <label class="cf-label">Từ khóa <span id="editKwReq" style="color:var(--err)">*</span></label>
                    <input type="text" id="editCampKeyword" class="cf-input" placeholder="Từ khóa cần chạy">
                </div>
                <div>
                    <label class="cf-label">URL bài viết <span style="color:var(--err)">*</span></label>
                    <input type="url" id="editCampUrl" class="cf-input" placeholder="https://example.com/bai-viet" required>
                </div>
                <div>
                    <label class="cf-label">Traffic/ngày</label>
                    <input type="number" id="editCampDaily" class="cf-input" min="10" max="5000">
                </div>
            </div>
            <input type="hidden" id="editCampTitle">

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:14px">
                <div>
                    <label class="cf-label">Loại traffic</label>
                    <select id="editCampTrafficType" class="cf-input" onchange="editUpdatePrice()">
                        <option value="1step">1 bước</option>
                        <option value="2step">2 bước</option>
                        <option value="nocode">Mã cố định</option>
                    </select>
                </div>
                <div>
                    <label class="cf-label">Giá/view</label>
                    <div id="editCampPrice" style="padding:10px 14px;background:var(--bg);border-radius:var(--rads);font-size:13px;font-weight:600;color:var(--a)"></div>
                </div>
                <div>
                    <label class="cf-label">Onsite</label>
                    <select id="editCampOnsite" class="cf-input" onchange="editUpdatePrice()">
                        <option value="70">70s</option>
                        <option value="80">80s</option>
                        <option value="90">90s (+100đ)</option>
                        <option value="100">100s (+200đ)</option>
                        <option value="120">120s (+250đ)</option>
                        <option value="150">150s (+300đ)</option>
                    </select>
                </div>
            </div>
            <div id="editReapprovalNote" style="display:none;padding:10px 14px;background:#FEF3C7;border:1px solid #FDE68A;border-radius:var(--rads);font-size:12px;color:#92400E;margin-bottom:14px">
                <strong>Lưu ý:</strong> Thay đổi loại traffic, onsite, ảnh hoặc nội dung chiến dịch sẽ chuyển về trạng thái <strong>Chờ duyệt</strong>. Chỉ thay đổi Traffic/ngày là không cần duyệt lại.
            </div>

            <!-- Screenshot upload -->
            <div style="margin-bottom:18px">
                <label class="cf-label">Ảnh minh họa</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:8px">
                    <div>
                        <div class="ss-upload">
                            <div class="ss-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg> Desktop</div>
                            <div class="ss-preview" id="editSsDesktopPreview"><span>Chưa có ảnh</span></div>
                            <label class="ss-btn" id="editSsDesktopBtn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Thay ảnh<input type="file" id="editSsDesktop" accept="image/*" style="display:none" onchange="editImgbbUpload(this,'editSsDesktopPreview','editSsDesktopUrl','editSsDesktopBtn')"></label>
                            <input type="hidden" id="editSsDesktopUrl">
                        </div>
                    </div>
                    <div>
                        <div class="ss-upload">
                            <div class="ss-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg> Mobile</div>
                            <div class="ss-preview" id="editSsMobilePreview"><span>Chưa có ảnh</span></div>
                            <label class="ss-btn" id="editSsMobileBtn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Thay ảnh<input type="file" id="editSsMobile" accept="image/*" style="display:none" onchange="editImgbbUpload(this,'editSsMobilePreview','editSsMobileUrl','editSsMobileBtn')"></label>
                            <input type="hidden" id="editSsMobileUrl">
                        </div>
                    </div>
                </div>
            </div>

            <div id="editCampMsg" style="min-height:20px;margin-bottom:10px;font-size:13px;text-align:center"></div>
            <button type="submit" id="editCampSubmitBtn" style="width:100%;padding:12px;background:var(--p);color:#fff;border:none;border-radius:var(--rads);font-family:var(--font);font-size:14px;font-weight:600;cursor:pointer">Lưu thay đổi</button>
        </form>
    </div>
</div>

</div><!-- /.main-content -->
</div><!-- /.main-wrap -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function(){
    var data = <?php echo json_encode($chart); ?>;
    var labels = data.map(function(x){ return x.date; });
    var views = data.map(function(x){ return x.views; });
    var spent = data.map(function(x){ return x.spent; });

    function fmt(n) {
        if (n >= 1000000) return (n/1000000).toFixed(1) + 'M';
        if (n >= 1000) return (n/1000).toFixed(0) + 'K';
        return n.toLocaleString('vi-VN');
    }
    function fmtMoney(n) { return n.toLocaleString('vi-VN') + 'đ'; }

    var ctx = document.getElementById('cdChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Traffic',
                    data: views,
                    borderColor: '#1E5EFF',
                    backgroundColor: 'rgba(59,130,246,0.08)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    yAxisID: 'y'
                },
                {
                    label: 'Đã chi (đ)',
                    data: spent,
                    borderColor: '#E07A00',
                    backgroundColor: 'transparent',
                    tension: 0.3,
                    borderWidth: 2,
                    pointRadius: 3,
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
                    title: { display: true, text: 'Traffic', font: { size: 11 } },
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
// Tab switching — syncs sidebar + bottom nav
var _tabTitles={overview:'T\u1ed5ng quan',create:'T\u1ea1o m\u1edbi',campaigns:'Chi\u1ebfn d\u1ecbch',deposit:'N\u1ea1p ti\u1ec1n',history:'L\u1ecbch s\u1eed',account:'T\xe0i kho\u1ea3n'};
function switchTab(tab){
    document.querySelectorAll('.sidebar-nav-item').forEach(function(x){x.classList.toggle('on',x.dataset.t===tab)});
    document.querySelectorAll('.bottom-nav-item').forEach(function(x){x.classList.toggle('on',x.dataset.t===tab)});
    document.querySelectorAll('.pane').forEach(function(x){x.classList.remove('on')});
    var pane=document.getElementById('p-'+tab);if(pane)pane.classList.add('on');
    var tt=document.getElementById('mainTopbarTitle');if(tt)tt.textContent=_tabTitles[tab]||'Dashboard';
    // Lưới chỉ số thuộc về Tổng quan — tab khác ẩn đi để nội dung chính lên trên,
    // thẻ ví phía trên vẫn giữ số dư + thao tác nhanh ở mọi tab.
    var st=document.querySelector('.dash-stats');if(st)st.style.display=(tab==='overview')?'':'none';
    window.scrollTo(0,0);
}
document.querySelectorAll('.sidebar-nav-item').forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();switchTab(b.dataset.t)})});
document.querySelectorAll('.bottom-nav-item').forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();switchTab(b.dataset.t)})});
// Auto-open tab from URL param
(function(){var p=new URLSearchParams(window.location.search);var t=p.get('tab');if(t)switchTab(t)})();

function reloadKeepTab(){
    var active=document.querySelector('.tb.on');
    var tab=active?active.dataset.t:'';
    var url=window.location.pathname;
    if(tab&&tab!=='overview') url+='?tab='+tab;
    window.location.href=url;
}

// === Account Tab ===
function saveProfile(form){
    var fd=new FormData(form);
    fd.append('action','sitetop_update_profile');
    fd.append('nonce',NONCE);
    var btn=form.querySelector('button[type=submit]');
    var msg=document.getElementById('profileMsg');
    btn.disabled=true;btn.textContent='Đang lưu...';
    fetch(AJAX,{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        if(d.success){msg.innerHTML='<span style="color:var(--ok)">Đã cập nhật!</span>';setTimeout(function(){location.reload()},1500)}
        else{msg.innerHTML='<span style="color:var(--err)">'+(d.data||'Lỗi')+'</span>';btn.disabled=false;btn.textContent='Lưu thay đổi'}
    }).catch(function(){msg.innerHTML='<span style="color:var(--err)">Lỗi kết nối</span>';btn.disabled=false;btn.textContent='Lưu thay đổi'});
    return false;
}
function changePassword(form){
    var fd=new FormData(form);
    if(fd.get('new_password')!==fd.get('confirm_password')){alert('Mật khẩu xác nhận không khớp');return false}
    fd.append('action','sitetop_change_password');
    fd.append('nonce',NONCE);
    var btn=form.querySelector('button[type=submit]');
    btn.disabled=true;btn.textContent='Đang xử lý...';
    fetch(AJAX,{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        if(d.success){alert('Đổi mật khẩu thành công!');form.reset();btn.disabled=false;btn.textContent='Đổi mật khẩu'}
        else{alert(d.data||'Có lỗi xảy ra');btn.disabled=false;btn.textContent='Đổi mật khẩu'}
    }).catch(function(){alert('Lỗi kết nối');btn.disabled=false;btn.textContent='Đổi mật khẩu'});
    return false;
}

// === Create Campaign Form ===
var PRICES = {
    keyword_search: { '1step': <?php echo (int)sitetop_get_option('keyword_price_1step', 1200); ?>, '2step': <?php echo (int)sitetop_get_option('keyword_price_2step', 1500); ?>, 'nocode': <?php echo (int)sitetop_get_option('keyword_price_nocode', 1200); ?> },
    traffic_direct: { '1step': <?php echo (int)sitetop_get_option('direct_price_1step', 1200); ?>, '2step': <?php echo (int)sitetop_get_option('direct_price_2step', 1200); ?>, 'nocode': <?php echo (int)sitetop_get_option('direct_price_nocode', 1200); ?> }
};
var ONSITE_EXTRA = {70:<?php echo (int)sitetop_get_option('onsite_extra_70',0); ?>,80:<?php echo (int)sitetop_get_option('onsite_extra_80',100); ?>,90:<?php echo (int)sitetop_get_option('onsite_extra_90',200); ?>,100:<?php echo (int)sitetop_get_option('onsite_extra_100',300); ?>,120:<?php echo (int)sitetop_get_option('onsite_extra_120',400); ?>,150:<?php echo (int)sitetop_get_option('onsite_extra_150',500); ?>};
var NONCE = '<?php echo wp_create_nonce("sitetop_nonce"); ?>';
var AJAX = '<?php echo admin_url("admin-ajax.php"); ?>';

function fmtMoney(n){return n.toLocaleString('vi-VN')+'đ'}

function selectPayMethod(el){document.querySelectorAll('.pay-method-option').forEach(function(x){x.classList.remove('selected');x.style.borderColor='';x.style.background=''});el.classList.add('selected');el.style.borderColor='var(--p)';el.style.background='#F4F8FF';if(typeof updateUsdtConvert==='function')updateUsdtConvert()}
document.querySelectorAll('.pay-method-option.selected').forEach(function(el){el.style.borderColor='var(--p)';el.style.background='#F4F8FF'});

// Service type selection
document.querySelectorAll('.svc-card').forEach(function(c){
    c.addEventListener('click',function(){
        document.querySelectorAll('.svc-card').forEach(function(x){x.classList.remove('selected')});
        c.classList.add('selected');
        c.querySelector('input').checked=true;
        var t=c.dataset.type;
        document.getElementById('campTaskType').value=t;
        var kf=document.getElementById('kwFields');
        var df=document.getElementById('directFields');
        if(t==='keyword_search'){kf.style.display='grid';df.style.display='none';document.getElementById('campKeyword').required=true;document.getElementById('campTargetUrl').required=true;document.getElementById('campTargetUrlDirect').required=false}
        else{kf.style.display='none';df.style.display='grid';document.getElementById('campKeyword').required=false;document.getElementById('campTargetUrl').required=false;document.getElementById('campTargetUrlDirect').required=true}
        updatePrices();
    });
});

// Traffic type selection
document.querySelectorAll('.tt-option').forEach(function(o){
    o.addEventListener('click',function(){
        document.querySelectorAll('.tt-option').forEach(function(x){x.classList.remove('selected')});
        o.classList.add('selected');
        o.querySelector('input').checked=true;
        updatePrices();
        // Show/hide nocode fields
        var tt=o.querySelector('input').value;
        var nf=document.getElementById('nocodeFields');
        if(nf)nf.style.display=(tt==='nocode')?'block':'none';
    });
});

// previewNocodeImg now handled by imgbbUpload

// Onsite time selection
document.querySelectorAll('.ot-option').forEach(function(o){
    o.addEventListener('click',function(){
        document.querySelectorAll('.ot-option').forEach(function(x){x.classList.remove('selected')});
        o.classList.add('selected');
        o.querySelector('input').checked=true;
        updatePrices();
    });
});

function getSelectedVal(name){var el=document.querySelector('input[name="'+name+'"]:checked');return el?el.value:null}

function updatePrices(){
    var taskType=document.getElementById('campTaskType').value;
    var trafficType=getSelectedVal('traffic_type')||'1step';
    var onsite=parseInt(getSelectedVal('onsite_time')||70);
    var daily=parseInt(document.querySelector('[name="daily_traffic"]').value)||100;
    var days=parseInt(document.getElementById('campDays').value)||30;

    // Update traffic type prices display
    var p=PRICES[taskType]||PRICES.keyword_search;
    document.getElementById('price1step').textContent=fmtMoney(p['1step']);
    document.getElementById('price2step').textContent=fmtMoney(p['2step']);
    document.getElementById('priceNocode').textContent=fmtMoney(p['nocode']);

    var base=p[trafficType]||p['1step'];
    var extra=ONSITE_EXTRA[onsite]||0;
    var price=base+extra;

    document.getElementById('priceDisplay').textContent=fmtMoney(price)+'/lượt';
    document.getElementById('estTotal').textContent=(daily*days).toLocaleString();
    document.getElementById('estDaily').textContent=fmtMoney(daily*price);
    document.getElementById('estTotalCost').textContent=fmtMoney(daily*days*price);
}

document.querySelector('[name="daily_traffic"]')?.addEventListener('input',updatePrices);
document.getElementById('campDays')?.addEventListener('input',updatePrices);
updatePrices(); // Init on page load with default values

// === Deposit Form ===
var DEP_TIERS = <?php
    $dep_presets = json_decode(sitetop_get_option('deposit_presets','[]'), true);
    $tiers = array();
    if(is_array($dep_presets)){
        foreach($dep_presets as $p){
            if(!empty($p['bonus']) && $p['bonus'] > 0) $tiers[] = array('amount'=>(int)$p['amount'],'bonus'=>(int)$p['bonus']);
        }
    }
    if(empty($tiers)) $tiers = array(array('amount'=>10000000,'bonus'=>5),array('amount'=>20000000,'bonus'=>5),array('amount'=>50000000,'bonus'=>10));
    echo json_encode($tiers);
?>;

function updateDepBonus(){
    var amount=parseInt(document.getElementById('depAmount').value)||0;
    var bonus=0;
    for(var i=DEP_TIERS.length-1;i>=0;i--){
        if(amount>=DEP_TIERS[i].amount){bonus=DEP_TIERS[i].bonus;break}
    }
    var info=document.getElementById('depBonusInfo');
    if(bonus>0){
        var bonusAmt=Math.floor(amount*bonus/100);
        document.getElementById('depBonusText').textContent='Nạp '+fmtMoney(amount)+' được thêm +'+bonus+'% = +'+fmtMoney(bonusAmt)+'. Tổng nhận: '+fmtMoney(amount+bonusAmt);
        info.style.display='block';
    }else{
        info.style.display='none';
    }
}
document.getElementById('depAmount')?.addEventListener('input',updateDepBonus);

// USDT conversion
var USDT_RATE = <?php echo intval(sitetop_get_option('deposit_usdt_rate', 25000)); ?>;
function updateUsdtConvert(){
    var el=document.getElementById('depUsdtConvert');
    if(!el)return;
    var pm=document.querySelector('input[name="payment_method"]:checked');
    var isUsdt=pm&&pm.value==='usdt';
    var amt=parseInt(document.getElementById('depAmount')?.value)||0;
    if(isUsdt&&amt>0&&USDT_RATE>0){
        var usdt=(amt/USDT_RATE).toFixed(2);
        el.textContent='~ '+usdt+' USDT (tỷ giá: 1 USDT = '+USDT_RATE.toLocaleString('vi-VN')+'đ)';
        el.style.display='block';
    }else{el.style.display='none';}
}
document.getElementById('depAmount')?.addEventListener('input',updateUsdtConvert);
document.querySelectorAll('input[name="payment_method"]').forEach(function(r){r.addEventListener('change',updateUsdtConvert)});

document.getElementById('depositForm')?.addEventListener('submit',function(e){
    e.preventDefault();
    var fd=new FormData(this);
    fd.append('action','sitetop_customer_deposit');
    fd.append('nonce',NONCE);
    var btn=document.getElementById('depSubmitBtn');
    var btnTxt=btn.querySelector('.dep-submit-t')||btn; // đổi chữ mà không xoá icon
    var msg=document.getElementById('depMsg');
    btn.disabled=true;btnTxt.textContent='Đang tạo...';
    fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if(r.success){
            msg.innerHTML='<span style="color:var(--ok)">Đơn nạp tiền đã tạo! Vui lòng chuyển khoản.</span>';
            setTimeout(function(){location.reload()},2000);
        }else{
            msg.innerHTML='<span style="color:var(--err)">'+(r.data||'Lỗi')+'</span>';
            btn.disabled=false;btnTxt.textContent='Tạo đơn nạp tiền';
        }
    });
});

function copyText(txt,btn){navigator.clipboard.writeText(txt).then(function(){var o=btn.textContent;btn.textContent='Copied!';setTimeout(function(){btn.textContent=o},1500)})}

// Copy widget code
function checkDailyMin(){
    var v=parseInt(document.getElementById('createDailyTraffic').value)||0;
    document.getElementById('dailyMinWarn').style.display=v<10?'block':'none';
}

function copyWidgetCode(){
    var code='<script src="<?php echo home_url("/top.js"); ?>" async><\/script>';
    navigator.clipboard.writeText(code).then(function(){
        document.getElementById('widgetCopyMsg').textContent='Đã copy!';
        setTimeout(function(){document.getElementById('widgetCopyMsg').textContent=''},2000);
    });
}

// Screenshot upload to ImgBB with preview
function imgbbUpload(input,previewId,hiddenName,btnId){
    var f=input.files[0];if(!f)return;
    var prev=document.getElementById(previewId);
    var btn=document.getElementById(btnId);
    var hidden=document.querySelector('input[name="'+hiddenName+'"]')||document.getElementById(hiddenName);
    prev.innerHTML='<span style="font-size:12px;color:var(--txtm,#9ca3af)">Đang tải lên...</span>';
    if(btn){btn.style.opacity='0.6';btn.style.pointerEvents='none';}
    var fd=new FormData();
    fd.append('action','sitetop_upload_screenshot');
    fd.append('nonce',NONCE);
    fd.append('file',f);
    fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if(btn){btn.style.opacity='';btn.style.pointerEvents='';}
        if(r.success&&r.data.url){
            prev.innerHTML='<img src="'+r.data.url+'" alt="Preview">';
            if(hidden)hidden.value=r.data.url;
        }else{
            prev.innerHTML='<span style="font-size:12px;color:var(--err,#dc3232)">'+(r.data||'Upload lỗi')+'</span>';
        }
    }).catch(function(){
        if(btn){btn.style.opacity='';btn.style.pointerEvents='';}
        prev.innerHTML='<span style="font-size:12px;color:var(--err,#dc3232)">Lỗi kết nối</span>';
    });
}

// Show/hide screenshot section based on task type
function toggleScreenshot(){
    var t=document.getElementById('campTaskType').value;
    document.getElementById('screenshotSection').style.display=(t==='keyword_search')?'block':'none';
}
document.querySelectorAll('.svc-card').forEach(function(c){
    c.addEventListener('click',function(){setTimeout(toggleScreenshot,10)});
});

// Submit
document.getElementById('createCampForm')?.addEventListener('submit',function(e){
    e.preventDefault();
    // Sync direct fields → main fields before submit
    var taskType=document.getElementById('campTaskType').value;
    if(taskType==='traffic_direct'){
        var urlD=document.getElementById('campTargetUrlDirect');
        var dtD=document.getElementById('createDailyTrafficDirect');
        document.getElementById('campTargetUrl').value=urlD?urlD.value:'';
        document.getElementById('createDailyTraffic').value=dtD?dtD.value:'100';
    }
    var fd=new FormData(this);
    // Remove duplicate direct-only fields
    fd.delete('target_url_direct');fd.delete('daily_traffic_direct');
    fd.append('action','sitetop_customer_create_campaign');
    fd.append('nonce',NONCE);
    var adminCust=document.getElementById('adminCustomerId');
    if(adminCust&&adminCust.value)fd.append('admin_customer_id',adminCust.value);
    var btn=document.getElementById('campSubmitBtn');
    var btnTxt=btn.querySelector('.cc-submit-t')||btn; // đổi chữ mà không xoá icon
    var msg=document.getElementById('campMsg');
    // Validate nocode screenshot required (check hidden URL input)
    var _tt=fd.get('traffic_type');
    if(_tt==='nocode'){
        var nocodeUrl=document.getElementById('ssNocodeUrlHidden');
        if(!nocodeUrl||!nocodeUrl.value){
            msg.innerHTML='<span style="color:var(--err)">Vui lòng tải ảnh mô tả vị trí mã cố định</span>';
            return;
        }
    }
    btn.disabled=true;btnTxt.textContent='Đang tạo...';
    fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if(r.success){
            msg.innerHTML='<span style="color:var(--ok)">Chiến dịch đã được tạo! Chờ Admin duyệt.</span>';
            setTimeout(function(){location.reload()},2000);
        }else{
            msg.innerHTML='<span style="color:var(--err)">'+(r.data||'Lỗi')+'</span>';
            btn.disabled=false;btnTxt.textContent='Tạo chiến dịch';
        }
    });
});

// === Campaign Status Filter (pill tabs) ===
var _campPillColors={active:'var(--ok)',pending:'var(--info)',paused:'var(--warn)',completed:'var(--txtm)',rejected:'var(--err)'};
function filterCampStatus(status){
    document.querySelectorAll('#campaignsListContainer tr[data-camp-status]').forEach(function(r){r.style.display=r.dataset.campStatus===status?'':'none'});
    document.querySelectorAll('.camp-pill').forEach(function(p){if(p.dataset.cs===status){p.classList.add('on');p.style.background=_campPillColors[status]||'var(--txtm)';p.style.color='#fff'}else{p.classList.remove('on');p.style.background='var(--bg)';p.style.color='var(--txtm)'}});
}

// === Campaign Actions ===
function toggleCampaign(id, status) {
    var label = status === 'paused' ? 'Tạm dừng' : 'Tiếp tục';
    if (!confirm(label + ' chiến dịch #' + id + '?')) return;
    var fd = new FormData();
    fd.append('action', 'sitetop_customer_toggle_campaign');
    fd.append('nonce', NONCE);
    fd.append('campaign_id', id);
    fd.append('status', status);
    fetch(AJAX, {method:'POST', body:fd, credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if (r.success) { toast(r.data, 'ok'); setTimeout(reloadKeepTab, 1000); }
        else toast(r.data || 'Lỗi', 'err');
    });
}

function viewCampaignDetail(id) {
    var fd = new FormData();
    fd.append('action', 'sitetop_customer_get_campaign');
    fd.append('nonce', NONCE);
    fd.append('campaign_id', id);
    fetch(AJAX, {method:'POST', body:fd, credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if (!r.success) { toast(r.data || 'Lỗi', 'err'); return; }
        var c = r.data;
        var stepLabels = {'1step':'1 bước','2step':'2 bước','nocode':'Mã cố định'};
        var typeLabels = {'keyword_search':'Keyword','traffic_direct':'Direct','traffic_social':'Social'};
        var statusLabels = {'active':'Đang chạy','paused':'Tạm dừng','pending':'Chờ duyệt','completed':'Hoàn thành','rejected':'Từ chối'};
        var statusBg = {'active':'#ECFDF5','paused':'#FFFBEB','pending':'#EFF6FF','completed':'#F3F4F6','rejected':'#FEF2F2'};
        var statusClr = {'active':'#059669','paused':'#D97706','pending':'#2563EB','completed':'#6B7280','rejected':'#DC2626'};
        var pct = c.quantity > 0 ? Math.round(c.completed / c.quantity * 100) : 0;

        // Header: keyword + status badge
        var html = '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">';
        html += '<div><div style="font-size:16px;font-weight:700;color:var(--pd)">' + (c.keyword || c.title || '—') + '</div>';
        html += '<a href="' + c.target_url + '" target="_blank" style="font-size:11px;color:var(--info);font-family:var(--mono);word-break:break-all">' + c.target_url + '</a></div>';
        html += '<span style="padding:5px 14px;border-radius:20px;font-size:11px;font-weight:700;background:' + (statusBg[c.status]||'#F3F4F6') + ';color:' + (statusClr[c.status]||'#6B7280') + '">' + (statusLabels[c.status]||c.status) + '</span>';
        html += '</div>';

        // Progress bar
        html += '<div style="margin-bottom:18px">';
        html += '<div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span style="color:var(--txtm)">Tiến độ</span><span style="font-weight:600">' + c.completed + '/' + c.quantity + ' (' + pct + '%)</span></div>';
        html += '<div style="height:8px;background:var(--bg);border-radius:4px;overflow:hidden"><div style="height:100%;width:' + pct + '%;background:linear-gradient(90deg,#059669,#10B981);border-radius:4px;transition:width .3s"></div></div>';
        html += '</div>';

        // Stats grid
        html += '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px">';
        var stats = [
            {l:'Loại',v:typeLabels[c.task_type]||c.task_type,c:'var(--txt)'},
            {l:'Gói',v:(stepLabels[c.traffic_type]||c.traffic_type)+' / '+c.onsite_time+'s'+(c.traffic_type==='nocode'&&c.fixed_code?'<div style="font-size:11px;color:var(--a);margin-top:2px">'+c.fixed_code+'</div>':''),c:'var(--txt)'},
            {l:'Giá/view',v:fmtMoney(parseFloat(c.price_per_view)),c:'var(--a)'},
            {l:'Traffic/ngày',v:'<span style="color:var(--a)">'+c.today_views+'</span>/'+c.daily_traffic,c:'var(--txt)'}
        ];
        for(var i=0;i<stats.length;i++){
            html += '<div style="background:var(--bg);border-radius:8px;padding:10px 12px;text-align:center">';
            html += '<div style="font-size:10px;color:var(--txtm);margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px">' + stats[i].l + '</div>';
            html += '<div style="font-size:13px;font-weight:700;color:' + stats[i].c + '">' + stats[i].v + '</div></div>';
        }
        html += '</div>';

        // Meta info
        if (c.reject_reason) html += '<div style="padding:10px 14px;background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;font-size:12px;color:#DC2626;margin-bottom:12px"><strong>Từ chối:</strong> ' + c.reject_reason + '</div>';
        html += '<div style="font-size:11px;color:var(--txtm)">Ngày tạo: ' + c.created_at + '</div>';

        // Screenshots
        var hasDeskSS = c.screenshot_desktop_url && c.screenshot_desktop_url.indexOf('http') === 0;
        var hasMobSS = c.screenshot_mobile_url && c.screenshot_mobile_url.indexOf('http') === 0;
        if (hasDeskSS || hasMobSS) {
            html += '<div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--brdl)">';
            html += '<div style="font-size:11px;font-weight:600;color:var(--txtm);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px">Ảnh minh họa</div>';
            html += '<div style="display:grid;grid-template-columns:' + (hasDeskSS && hasMobSS ? '1fr 1fr' : '1fr') + ';gap:12px">';
            if (hasDeskSS) html += '<div><div style="font-size:10px;color:var(--txtm);margin-bottom:4px">Desktop</div><img src="' + c.screenshot_desktop_url + '" style="width:100%;border-radius:8px;border:1px solid var(--brdl)" alt="Desktop"></div>';
            if (hasMobSS) html += '<div><div style="font-size:10px;color:var(--txtm);margin-bottom:4px">Mobile</div><img src="' + c.screenshot_mobile_url + '" style="width:100%;border-radius:8px;border:1px solid var(--brdl)" alt="Mobile"></div>';
            html += '</div></div>';
        }
        document.getElementById('campDetailContent').innerHTML = html;
        document.getElementById('campDetailModal').style.display = 'flex';
    });
}

var _editOriginal = {};

function editCampaign(id) {
    var fd = new FormData();
    fd.append('action', 'sitetop_customer_get_campaign');
    fd.append('nonce', NONCE);
    fd.append('campaign_id', id);
    fetch(AJAX, {method:'POST', body:fd, credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if (!r.success) { toast(r.data || 'Lỗi', 'err'); return; }
        var c = r.data;
        _editOriginal = {
            keyword: c.keyword||'', target_url: c.target_url||'', title: c.title||'',
            traffic_type: c.traffic_type||'1step', onsite_time: String(c.onsite_time||70),
            task_type: c.task_type||'keyword_search',
            daily_traffic: String(c.daily_traffic||10),
            status: c.status||'pending'
        };

        document.getElementById('editCampId').value = c.id;
        document.getElementById('editCampKeyword').value = c.keyword || '';
        document.getElementById('editCampDaily').value = c.daily_traffic || 10;
        document.getElementById('editCampUrl').value = c.target_url || '';
        document.getElementById('editCampTitle').value = c.title || '';
        document.getElementById('editCampTrafficType').value = c.traffic_type || '1step';
        document.getElementById('editCampOnsite').value = String(c.onsite_time || 70);
        editUpdatePrice();

        if (c.task_type === 'keyword_search') {
            document.getElementById('editKwCell').style.display = '';
            document.getElementById('editKwFields').style.gridTemplateColumns = '1fr 1fr 100px';
        } else {
            document.getElementById('editKwCell').style.display = 'none';
            document.getElementById('editKwFields').style.gridTemplateColumns = '1fr 100px';
            document.getElementById('editCampKeyword').value = '';
        }

        // Screenshots
        var dprev = document.getElementById('editSsDesktopPreview');
        var mprev = document.getElementById('editSsMobilePreview');
        dprev.innerHTML = (c.screenshot_desktop_url && c.screenshot_desktop_url.indexOf('http') === 0)
            ? '<img src="' + c.screenshot_desktop_url + '" style="width:100%;height:auto;border-radius:var(--rads)">'
            : '<span>Chưa có ảnh</span>';
        mprev.innerHTML = (c.screenshot_mobile_url && c.screenshot_mobile_url.indexOf('http') === 0)
            ? '<img src="' + c.screenshot_mobile_url + '" style="width:100%;height:auto;border-radius:var(--rads)">'
            : '<span>Chưa có ảnh</span>';

        document.getElementById('editSsDesktop').value = '';
        document.getElementById('editSsMobile').value = '';
        document.getElementById('editSsDesktopUrl').value = '';
        document.getElementById('editSsMobileUrl').value = '';
        document.getElementById('editCampMsg').innerHTML = '';
        document.getElementById('editReapprovalNote').style.display = 'none';
        document.getElementById('editCampSubmitBtn').disabled = false;
        document.getElementById('editCampSubmitBtn').textContent = 'Lưu thay đổi';
        document.getElementById('campEditModal').style.display = 'flex';
    });
}

function editUpdatePrice() {
    var taskType = _editOriginal.task_type || 'keyword_search';
    var tt = document.getElementById('editCampTrafficType').value;
    var os = parseInt(document.getElementById('editCampOnsite').value);
    var base = (PRICES[taskType] || PRICES.keyword_search)[tt] || 1200;
    var extra = ONSITE_EXTRA[os] || 0;
    document.getElementById('editCampPrice').textContent = fmtMoney(base + extra);
    editCheckReapproval();
}

function editCheckReapproval() {
    var changed = document.getElementById('editCampTrafficType').value !== _editOriginal.traffic_type
        || document.getElementById('editCampOnsite').value !== _editOriginal.onsite_time
        || document.getElementById('editCampKeyword').value !== _editOriginal.keyword
        || document.getElementById('editCampUrl').value !== _editOriginal.target_url
        || document.getElementById('editCampTitle').value !== _editOriginal.title
        || (document.getElementById('editSsDesktopUrl').value || '') !== ''
        || (document.getElementById('editSsMobileUrl').value || '') !== '';
    document.getElementById('editReapprovalNote').style.display = changed ? 'block' : 'none';
}

// Attach change listeners for re-approval check
['editCampKeyword','editCampUrl','editCampTitle'].forEach(function(id){
    var el = document.getElementById(id);
    if (el) el.addEventListener('input', editCheckReapproval);
});

function closeEditModal() {
    document.getElementById('campEditModal').style.display = 'none';
}

function editImgbbUpload(input, previewId, hiddenId, btnId) {
    var f = input.files[0]; if (!f) return;
    var prev = document.getElementById(previewId);
    var btn = document.getElementById(btnId);
    var hidden = document.getElementById(hiddenId);
    prev.innerHTML = '<span style="font-size:12px;color:var(--txtm,#9ca3af)">Đang tải lên...</span>';
    if (btn) { btn.style.opacity = '0.6'; btn.style.pointerEvents = 'none'; }
    var fd = new FormData();
    fd.append('action', 'sitetop_upload_screenshot');
    fd.append('nonce', NONCE);
    fd.append('file', f);
    fetch(AJAX, {method:'POST', body:fd, credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if (btn) { btn.style.opacity = ''; btn.style.pointerEvents = ''; }
        if (r.success && r.data.url) {
            prev.innerHTML = '<img src="' + r.data.url + '" style="width:100%;height:auto;border-radius:var(--rads)">';
            if (hidden) hidden.value = r.data.url;
        } else {
            prev.innerHTML = '<span style="font-size:12px;color:var(--err,#dc3232)">' + (r.data || 'Upload lỗi') + '</span>';
        }
        editCheckReapproval();
    }).catch(function(){
        if (btn) { btn.style.opacity = ''; btn.style.pointerEvents = ''; }
        prev.innerHTML = '<span style="font-size:12px;color:var(--err,#dc3232)">Lỗi kết nối</span>';
    });
}

document.getElementById('editCampForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var id = document.getElementById('editCampId').value;
    var btn = document.getElementById('editCampSubmitBtn');
    var msg = document.getElementById('editCampMsg');

    var taskType = (_editOriginal.task_type) || 'keyword_search';
    var kwVal = (document.getElementById('editCampKeyword').value || '').trim();
    if (taskType === 'keyword_search' && kwVal === '') {
        msg.innerHTML = '<span style="color:var(--err)">Từ khóa không được để trống</span>';
        document.getElementById('editCampKeyword').focus();
        return;
    }

    // Confirm khi đổi daily_traffic của campaign đang active/paused
    // — bắt customer xác nhận trước khi gửi vì server sẽ reset status về pending
    var newDaily = document.getElementById('editCampDaily').value;
    var origStatus = _editOriginal.status || 'pending';
    if (newDaily !== _editOriginal.daily_traffic && (origStatus === 'active' || origStatus === 'paused')) {
        if (!confirm('Thay đổi số lượng/ngày sẽ phải duyệt lại!\nBạn có muốn tiếp tục?')) {
            return;
        }
    }

    btn.disabled = true; btn.textContent = 'Đang lưu...';

    var fd = new FormData();
    fd.append('action', 'sitetop_customer_edit_campaign');
    fd.append('nonce', NONCE);
    fd.append('campaign_id', id);
    if (taskType !== 'traffic_direct') fd.append('keyword', document.getElementById('editCampKeyword').value);
    fd.append('target_url', document.getElementById('editCampUrl').value);
    fd.append('title', document.getElementById('editCampTitle').value);
    fd.append('daily_traffic', document.getElementById('editCampDaily').value);
    fd.append('traffic_type', document.getElementById('editCampTrafficType').value);
    fd.append('onsite_time', document.getElementById('editCampOnsite').value);

    var ssDesktopUrl = document.getElementById('editSsDesktopUrl').value;
    var ssMobileUrl = document.getElementById('editSsMobileUrl').value;
    if (ssDesktopUrl) fd.append('screenshot_desktop_url', ssDesktopUrl);
    if (ssMobileUrl) fd.append('screenshot_mobile_url', ssMobileUrl);

    fetch(AJAX, {method:'POST', body:fd, credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if (r.success) {
            msg.innerHTML = '<span style="color:var(--ok)">' + r.data + '</span>';
            toast(r.data, 'ok');
            setTimeout(function() { closeEditModal(); reloadKeepTab(); }, 1000);
        } else {
            msg.innerHTML = '<span style="color:var(--err)">' + (r.data || 'Lỗi') + '</span>';
            btn.disabled = false; btn.textContent = 'Lưu thay đổi';
        }
    });
});

function deleteCampaign(id) {
    if (!confirm('Xóa chiến dịch #' + id + '? Hành động này không thể hoàn tác.')) return;
    var fd = new FormData();
    fd.append('action', 'sitetop_customer_delete_campaign');
    fd.append('nonce', NONCE);
    fd.append('campaign_id', id);
    fetch(AJAX, {method:'POST', body:fd, credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if (r.success) { toast(r.data, 'ok'); setTimeout(reloadKeepTab, 1000); }
        else toast(r.data || 'Lỗi', 'err');
    });
}

function closeCampModal() {
    document.getElementById('campDetailModal').style.display = 'none';
}

function toast(m,t){var c=document.querySelector('.toast-box');if(!c){c=document.createElement('div');c.className='toast-box';c.style.cssText='position:fixed;top:58px;right:20px;z-index:10000;display:flex;flex-direction:column;gap:6px';document.body.appendChild(c)}var d=document.createElement('div');d.style.cssText='padding:11px 18px;border-radius:8px;font-size:13px;font-weight:500;color:#fff;box-shadow:0 4px 14px rgba(0,0,0,.12);min-width:240px;animation:sr .3s ease;background:'+(t==='err'?'var(--err)':'var(--ok)');d.textContent=m;c.appendChild(d);setTimeout(function(){d.remove()},3500)}

// Load more
document.querySelectorAll('.cust-load-more-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
        var type=btn.dataset.type,offset=parseInt(btn.dataset.offset),target=btn.dataset.target;
        var origText=btn.textContent;btn.textContent='Đang tải...';btn.disabled=true;
        var fd=new FormData();fd.append('action','sitetop_customer_load_more');fd.append('nonce',NONCE);fd.append('type',type);fd.append('offset',offset);
        fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
            if(r.success&&r.data.html){
                document.getElementById(target).insertAdjacentHTML('beforeend',r.data.html);
                btn.dataset.offset=offset+10;
                if(!r.data.has_more){btn.style.display='none'}
                else{btn.textContent=origText;btn.disabled=false}
            }else{btn.style.display='none'}
        }).catch(function(){btn.textContent=origText;btn.disabled=false})
    })
});

// Load announcements
;(function(){
    var fd=new FormData();fd.append('action','sitetop_get_announcements');fd.append('nonce',NONCE);fd.append('target','customer');
    fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if(!r.success||!r.data.announcements||!r.data.announcements.length)return;
        var wrap=document.getElementById('custAnnouncements');
        var html='<div class="ann-header"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--info)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3zm-8.27 4a2 2 0 0 1-3.46 0"/></svg> Th\u00f4ng b\u00e1o</div>';
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
<?php
// Khách hàng chờ kích hoạt: gate mềm (pill + popup + chặn click chuyển tab, trừ Tổng quan).
if ( ! empty( $adv_pending ) && function_exists( 'sitetop_pending_gate_html' ) ) {
    echo sitetop_pending_gate_html( '.sidebar-nav-item[data-t],.bottom-nav-item[data-t]', 'overview' );
}
wp_footer();
?>
</body>
</html>
