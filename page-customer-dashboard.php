<?php
/**
 * Template Name: Customer Dashboard
 * Traffictop.net V2 - Customer Dashboard (nhà quảng cáo mua campaign)
 * 
 * Customer nạp tiền → tạo campaign → traffic được phân phối qua shortlinks
 * Tabs: Tổng quan | Campaigns | Nạp tiền | Lịch sử GD
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }

$user_id = get_current_user_id();
$user    = wp_get_current_user();
$is_minimal = isset($_GET['minimal']) && $_GET['minimal'] === '1';

global $wpdb;
$prefix = $wpdb->prefix . 'traffictop_';
$today  = date( 'Y-m-d', strtotime( traffictop_current_time() ) );

// Stats
$cust_balance    = traffictop_get_customer_balance_amount( $user_id );
$total_deposited = (float) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(SUM(amount),0) FROM {$prefix}customer_transactions WHERE customer_id=%d AND type='deposit' AND amount>0", $user_id ) );
$total_spent = (float) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(ABS(SUM(amount)),0) FROM {$prefix}customer_transactions WHERE customer_id=%d AND type='campaign_view' AND amount<0", $user_id ) );

// Pagination params
$camp_page = max(1, intval($_GET['camp_page'] ?? 1));
$dep_page = max(1, intval($_GET['dep_page'] ?? 1));
$hist_page = max(1, intval($_GET['hist_page'] ?? 1));
$camp_per = 20; $dep_per = 5; $hist_per = 20;

// Campaigns (with order info)
$camp_total = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$prefix}keyword_campaigns WHERE customer_id=%d", $user_id) );
$camp_pages = max(1, ceil($camp_total / $camp_per));
$camp_offset = ($camp_page - 1) * $camp_per;
$my_campaigns = $wpdb->get_results( $wpdb->prepare(
    "SELECT kc.*, co.task_type,
            (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND step='verified') as total_completed,
            (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND step='verified' AND DATE(created_at)=%s) as today_views
     FROM {$prefix}keyword_campaigns kc
     LEFT JOIN {$prefix}customer_orders co ON kc.order_id = co.id
     WHERE kc.customer_id = %d
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
     WHERE kc.customer_id = %d AND v.step = 'verified'
     ORDER BY v.created_at DESC LIMIT %d OFFSET %d", $user_id, $hist_per, ($hist_page - 1) * $hist_per ) );
$hist_total = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$prefix}shortlink_visits v INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id=kc.id WHERE kc.customer_id=%d AND v.step='verified'", $user_id ) );
$hist_pages = max(1, ceil($hist_total / $hist_per));

// 30-day chart
$chart=array();
for($i=29;$i>=0;$i--){
    $d=date('Y-m-d',strtotime("-{$i} days",strtotime(traffictop_current_time())));
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
:root{--p:#0D4F4F;--pl:#1A7A7A;--pd:#083838;--a:#E8A838;--bg:#F7F5F0;--card:#fff;--dark:#1A1A2E;--txt:#2C2C3A;--txtl:#6B7280;--txtm:#9CA3AF;--brd:#E5E2DB;--brdl:#F0EDE6;--ok:#059669;--err:#DC2626;--warn:#D97706;--info:#2563EB;--font:'Inter',sans-serif;--fonth:'Plus Jakarta Sans',sans-serif;--mono:'JetBrains Mono',monospace;--rad:12px;--rads:8px}
*{box-sizing:border-box;margin:0;padding:0}html,body{width:100%;overflow-x:hidden}body{font-family:var(--font);color:var(--txt);background:var(--bg);line-height:1.6}
.card{max-width:100%;overflow:hidden}

/* ── Sidebar ── */
.sidebar{position:fixed;top:0;left:0;bottom:0;width:var(--sidebar-w,260px);background:var(--dark);color:#fff;z-index:100;display:flex;flex-direction:column;overflow-y:auto}
.sidebar-logo{padding:24px;display:flex;align-items:center;gap:8px;text-decoration:none;color:#fff;font-family:var(--fonth);font-weight:800;font-size:20px;border-bottom:1px solid rgba(255,255,255,.08)}
.sidebar-logo svg{flex-shrink:0;color:var(--a)}
.sidebar-user{padding:20px 24px;border-bottom:1px solid rgba(255,255,255,.08)}
.sidebar-user-info{display:flex;align-items:center;gap:12px;margin-bottom:12px}
.sidebar-avatar{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--a),#D97706);color:#fff;display:flex;align-items:center;justify-content:center;font-size:17px;font-family:var(--fonth);font-weight:700;flex-shrink:0}
.sidebar-name{font-weight:600;font-size:14px;color:#fff;line-height:1.3}
.sidebar-role{font-size:11px;color:rgba(255,255,255,.45);margin-top:2px}
.sidebar-balance{background:rgba(255,255,255,.06);border-radius:var(--rads);padding:10px 14px}
.sidebar-balance-label{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.5);margin-bottom:2px}
.sidebar-balance-value{font-family:var(--fonth);font-weight:800;font-size:20px;color:#6EE7B7}
.sidebar-nav{flex:1;padding:16px 0}
.sidebar-nav-item{display:flex;align-items:center;gap:12px;padding:11px 24px;color:rgba(255,255,255,.6);font-size:13px;font-weight:500;cursor:pointer;transition:all .2s;border-left:3px solid transparent;text-decoration:none}
.sidebar-nav-item:hover{color:#fff;background:rgba(255,255,255,.06)}
.sidebar-nav-item.on{color:#fff;background:rgba(232,168,56,.1);border-left-color:var(--a)}
.sidebar-nav-item.on svg{color:var(--a)}
.sidebar-nav-item svg{width:18px;height:18px;flex-shrink:0;color:rgba(255,255,255,.4);transition:color .2s}
.sidebar-nav-item:hover svg{color:rgba(255,255,255,.8)}
.sidebar-bottom{padding:12px 24px;border-top:1px solid rgba(255,255,255,.08);display:flex;flex-direction:column;gap:4px}
.sidebar-bottom a{display:flex;align-items:center;gap:10px;padding:9px 0;color:rgba(255,255,255,.5);text-decoration:none;font-size:13px;transition:color .2s}
.sidebar-bottom a:hover{color:#fff}
.sidebar-bottom a svg{width:16px;height:16px;flex-shrink:0}

/* ── Main content ── */
.main-wrap{margin-left:var(--sidebar-w,260px);min-height:100vh}
.main-topbar{background:var(--card);border-bottom:1px solid var(--brdl);padding:0 24px;height:54px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:50}
.main-topbar-title{font-family:var(--fonth);font-weight:700;font-size:17px;color:var(--pd)}
.main-content{padding:24px;max-width:1100px;overflow-x:hidden}
.dash-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}

/* ── Bottom Nav (mobile) ── */
.bottom-nav{display:none;position:fixed;bottom:0;left:0;right:0;background:var(--card);z-index:100;padding:4px 0 env(safe-area-inset-bottom,0);border-top:1px solid var(--brd);box-shadow:0 -2px 10px rgba(0,0,0,.06)}
.bottom-nav-inner{display:flex;justify-content:space-around;align-items:center}
.bottom-nav-item{display:flex;flex-direction:column;align-items:center;gap:2px;padding:6px 4px;color:var(--txtm);text-decoration:none;font-size:9px;font-weight:500;cursor:pointer;transition:color .2s;flex:1;min-width:0}
.bottom-nav-item svg{width:20px;height:20px;flex-shrink:0}
.bottom-nav-item span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
.bottom-nav-item.on{color:var(--p)}
.bottom-nav-item.on svg{color:var(--p)}
.mobile-topbar{display:none;background:var(--card);border-bottom:1px solid var(--brdl);padding:0 16px;height:50px;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.mobile-topbar-logo{font-family:var(--fonth);font-weight:800;font-size:17px;color:var(--p);text-decoration:none;display:flex;align-items:center;gap:6px}
.mobile-topbar-logo svg{color:var(--p);flex-shrink:0}
.mobile-topbar-right{display:flex;align-items:center;gap:10px;font-size:12px}
.mobile-topbar-right .avatar{width:28px;height:28px;border-radius:50%;background:var(--a);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700}

.pane{display:none;animation:fu .3s ease}.pane.on{display:block}
@keyframes fu{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

@media(max-width:768px){
    .sidebar{display:none}
    .main-wrap{margin-left:0}
    .main-topbar{display:none}
    .mobile-topbar{display:flex}
    .bottom-nav{display:block}
    .main-content{padding:16px 16px 100px}
    .dash-stats{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:480px){
    .dash-stats{grid-template-columns:repeat(2,1fr);gap:10px}
    .main-content{padding:12px 12px 100px}
}

.card{background:var(--card);border-radius:var(--rad);border:1px solid var(--brdl);padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);margin-bottom:20px}
.card-h{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--brdl)}
.card-h h3{font-family:var(--fonth);font-size:17px;color:var(--pd)}
.sg{display:grid;gap:14px;margin-bottom:20px}
.sg4{grid-template-columns:repeat(4,1fr)}
.sc{background:var(--card);border-radius:var(--rad);padding:14px;border:1px solid var(--brdl);display:flex;align-items:center;gap:10px;transition:all .2s;min-width:0;overflow:hidden}
.sc:hover{box-shadow:0 4px 12px rgba(0,0,0,.06);transform:translateY(-1px)}
.sc-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sc-icon svg{width:20px;height:20px}
.sc.s1 .sc-icon{background:#EDE9FE;color:#7C3AED}.sc.s2 .sc-icon{background:#FFF7ED;color:#EA580C}
.sc.s3 .sc-icon{background:#ECFDF5;color:#059669}.sc.s4 .sc-icon{background:#F0F9FF;color:#0284C7}
.sc-text{min-width:0;overflow:hidden}
.sc .sl{font-size:10px;color:var(--txtm);margin-bottom:2px;white-space:nowrap}
.sc .sv{font-family:var(--fonth);font-weight:800;font-size:16px;color:var(--pd);line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sc .ss{font-size:10px;color:var(--txtl);margin-top:2px;white-space:nowrap}

table{width:100%;border-collapse:collapse;font-size:13px}
thead th{background:var(--bg);padding:9px 12px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--txtl);border-bottom:2px solid var(--brd)}
td{padding:9px 12px;border-bottom:1px solid var(--brdl);vertical-align:middle}
.badge{display:inline-flex;padding:3px 8px;border-radius:20px;font-size:10px;font-weight:600}
.b-ok{background:#D1FAE5;color:#065F46}.b-warn{background:#FEF3C7;color:#92400E}.b-err{background:#FEE2E2;color:#991B1B}.b-info{background:#DBEAFE;color:#1E40AF}.b-mute{background:#F3F4F6;color:#4B5563}
.mono{font-family:var(--mono);font-size:11px}

.cd-chart-legend{display:flex;gap:16px;font-size:12px;color:var(--txtm)}
.cd-chart-legend span{display:inline-flex;align-items:center;gap:5px}
.cd-chart-legend span::before{content:'';width:14px;height:3px;border-radius:2px;display:inline-block}
.cd-chart-legend .lg-traffic::before{background:#3b82f6}
.cd-chart-legend .lg-spent::before{background:#f59e0b}
.cd-chart-container{position:relative;height:280px}

/* Campaign cards */
.ccgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px}
.ccamp{background:var(--card);border:1px solid var(--brdl);border-radius:var(--rad);padding:18px;transition:all .3s}
.ccamp:hover{box-shadow:0 4px 14px rgba(0,0,0,.04)}
.ccamp-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.ccamp-name{font-weight:600;font-size:14px;color:var(--pd);margin-bottom:4px}
.ccamp-kw{font-size:12px;color:var(--txtl);margin-bottom:10px}
.cprog{height:5px;background:var(--brdl);border-radius:3px;overflow:hidden;margin-bottom:6px}
.cprog-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--p),var(--pl))}
.ccamp-meta{display:flex;gap:14px;font-size:11px;color:var(--txtm)}
.ccamp-link{display:block;margin-top:10px;font-family:var(--mono);font-size:10px;color:var(--info);word-break:break-all}

/* Create campaign form */
.svc-card{border:2px solid var(--brdl);border-radius:var(--rads);padding:10px 14px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:10px}
.svc-card.selected{border-color:var(--info);background:#F0F7FF}
.svc-card:hover{border-color:var(--info)}
.svc-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.svc-icon svg{width:18px;height:18px}
.svc-name{font-weight:700;font-size:13px;color:var(--pd);margin-bottom:1px}
.svc-price{font-size:11px;color:var(--ok);font-weight:600}
.cf-label{display:block;font-size:12px;font-weight:600;color:var(--txtl);margin-bottom:5px}
.cf-input{width:100%;padding:10px 14px;border:1.5px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px;transition:all .2s;background:#FAFAF8}
.cf-input:focus{outline:none;border-color:var(--p);box-shadow:0 0 0 3px rgba(13,79,79,.08);background:#fff}
.tt-option{display:flex;align-items:center;gap:10px;padding:12px 16px;border:1.5px solid var(--brdl);border-radius:var(--rads);cursor:pointer;transition:all .2s}
.tt-option.selected{border-color:var(--info);background:#F0F7FF}
.tt-option:hover{border-color:var(--info)}
.tt-option input{width:18px;height:18px;accent-color:var(--info)}
.tt-label{flex:1;font-weight:600;font-size:13px;color:var(--pd)}
.tt-price{font-weight:700;font-size:13px;color:var(--a)}
.ot-option{display:flex;align-items:center;justify-content:center;gap:6px;padding:10px;border:1.5px solid var(--brdl);border-radius:var(--rads);cursor:pointer;transition:all .2s;font-size:13px;font-weight:600}
.ot-option.selected{border-color:var(--info);background:#F0F7FF}
.ot-option:hover{border-color:var(--info)}
.ot-option input{display:none}
.ss-upload{border:2px dashed var(--brdl);border-radius:var(--rad);padding:16px;text-align:center}
.ss-label{font-size:13px;font-weight:600;color:var(--pd);margin-bottom:10px;display:flex;align-items:center;justify-content:center;gap:6px}
.ss-preview{width:100%;min-height:120px;background:var(--bg);border-radius:var(--rads);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;margin-bottom:10px;overflow:hidden}
.ss-preview span{font-size:12px;color:var(--txtm)}
.ss-preview img{width:100%;height:auto;max-height:200px;object-fit:contain;border-radius:var(--rads);display:block}
.ss-btn{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:10px;background:var(--info);color:#fff;border-radius:var(--rads);font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.ss-btn:hover{background:#1D4ED8}

/* Deposit */
.deposit-row{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.cust-paging{display:flex;gap:4px;justify-content:center;margin-top:14px;flex-wrap:wrap}
.pg-btn{padding:6px 12px;border:1px solid var(--brd);border-radius:6px;font-size:12px;font-weight:600;color:var(--txtl);text-decoration:none;cursor:pointer;background:var(--card);transition:all .2s}
.pg-btn:hover{border-color:var(--p);color:var(--p)}
.pg-btn.on{background:var(--p);color:#fff;border-color:var(--p)}
.dep-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.dep-preset{padding:10px;border:1.5px solid var(--brdl);border-radius:var(--rads);background:#fff;font-size:14px;font-weight:700;color:var(--p);cursor:pointer;transition:all .2s;font-family:var(--font)}
.dep-preset:hover{border-color:var(--p);background:#F0F9F9}
.dep-box{background:linear-gradient(135deg,#DBEAFE,#EFF6FF);border-radius:var(--rad);padding:22px;margin-bottom:20px}
.dep-box h4{font-family:var(--fonth);font-size:17px;color:var(--pd);margin-bottom:10px}
.dep-info{display:grid;grid-template-columns:120px 1fr;gap:6px 14px;font-size:13px}
.dep-info dt{color:var(--txtm)}.dep-info dd{color:var(--txt);font-weight:600;font-family:var(--mono)}

/* Announcements */
.ann-section{margin-bottom:20px}
.ann-header{display:flex;align-items:center;gap:8px;margin-bottom:14px;font-family:var(--fonth);font-size:16px;font-weight:700;color:var(--pd)}
.ann-header svg{flex-shrink:0}
.ann-item{background:var(--card);border-radius:var(--rad);border:1px solid var(--brdl);padding:18px 20px;margin-bottom:12px;border-left:4px solid var(--info);box-shadow:0 1px 3px rgba(0,0,0,.04)}
.ann-item.ann-warning{border-left-color:var(--warn)}
.ann-item.ann-success{border-left-color:var(--ok)}
.ann-item .ann-title{display:flex;align-items:center;gap:8px;font-weight:700;font-size:14px;color:var(--pd);margin-bottom:6px}
.ann-item .ann-title .ann-icon{width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
.ann-item.ann-info .ann-icon{background:#DBEAFE;color:var(--info)}
.ann-item.ann-warning .ann-icon{background:#FEF3C7;color:var(--warn)}
.ann-item.ann-success .ann-icon{background:#D1FAE5;color:var(--ok)}
.ann-badge-new{display:inline-flex;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;background:var(--info);color:#fff;text-transform:uppercase;letter-spacing:.05em}
.ann-item .ann-body{font-size:13px;color:var(--txt);line-height:1.7;margin-bottom:8px}
.ann-item .ann-time{font-size:11px;color:var(--txtm);display:flex;align-items:center;gap:4px}

@media(max-width:768px){
    .sg4{grid-template-columns:repeat(2,1fr)}
    .ccgrid{grid-template-columns:1fr}
    .account-grid{grid-template-columns:1fr!important}
    .brow{gap:16px}
    .deposit-row{grid-template-columns:1fr!important}
    .dep-grid{grid-template-columns:1fr!important}
    .cd-chart-container{height:220px}
    #onsiteTimes{grid-template-columns:repeat(3,1fr)!important}
    #kwFields{grid-template-columns:1fr!important}
    #trafficTypes{grid-template-columns:1fr!important}
}
<?php if($is_minimal): ?>
#wpadminbar,html{margin-top:0!important}
#wpadminbar{display:none!important}
<?php endif; ?>
</style>
</head>
<body>
<?php if(!$is_minimal): ?>
<!-- Sidebar -->
<aside class="sidebar">
    <?php $sb_icon = get_option('traffictop_widget_icon',''); ?>
    <a href="<?php echo home_url(); ?>" class="sidebar-logo">
        <?php if($sb_icon): ?><img src="<?php echo esc_url($sb_icon); ?>" width="22" height="22" alt="" style="border-radius:4px"><?php else: ?><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><?php endif; ?>
        Traffictop.net
    </a>
    <div class="sidebar-user">
        <div class="sidebar-user-info">
            <div class="sidebar-avatar"><?php echo strtoupper(substr($user->display_name,0,1)); ?></div>
            <div>
                <div class="sidebar-name"><?php echo esc_html($user->display_name); ?></div>
                <div class="sidebar-role">Advertiser</div>
            </div>
        </div>
        <div class="sidebar-balance">
            <div class="sidebar-balance-label">S&#7889; d&#432;</div>
            <div class="sidebar-balance-value"><?php echo traffictop_format_money($cust_balance); ?></div>
        </div>
    </div>
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
    <?php $ln_icon = get_option('traffictop_widget_icon',''); ?>
    <a href="<?php echo home_url(); ?>" class="mobile-topbar-logo">
        <?php if($ln_icon): ?><img src="<?php echo esc_url($ln_icon); ?>" width="20" height="20" alt="" style="vertical-align:middle"><?php else: ?><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><?php endif; ?>
        Traffictop.net
    </a>
    <div class="mobile-topbar-right">
        <span style="color:var(--ok);font-family:var(--fonth);font-weight:700"><?php echo traffictop_format_money($cust_balance); ?></span>
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
    </div>
    <?php endif; ?>
    <div class="main-content">

    <?php if(!$is_minimal): ?>
    <!-- Stats grid -->
    <div class="dash-stats">
        <div class="sc s1">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg></div>
            <div class="sc-text"><div class="sl">T&#7893;ng chi&#7871;n d&#7883;ch</div><div class="sv"><?php echo $camp_total; ?></div><div class="ss">&#272;ang ch&#7841;y: <?php echo count($active_camps); ?></div></div>
        </div>
        <div class="sc s2">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
            <div class="sc-text"><div class="sl">T&#7893;ng views</div><div class="sv"><?php echo number_format($total_views); ?></div><div class="ss">H&#244;m nay: <?php echo number_format($today_views); ?></div></div>
        </div>
        <div class="sc s3">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <div class="sc-text"><div class="sl">&#272;&#227; n&#7841;p</div><div class="sv"><?php echo traffictop_format_money($total_deposited); ?></div></div>
        </div>
        <div class="sc s4">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg></div>
            <div class="sc-text"><div class="sl">S&#7889; d&#432;</div><div class="sv"><?php echo traffictop_format_money($cust_balance); ?></div><div class="ss">&#272;&#227; chi: <?php echo traffictop_format_money($total_spent); ?></div></div>
        </div>
    </div>
    <?php endif; ?>

<?php if(!$is_minimal): ?>
<!-- Overview -->
<div class="pane on" id="p-overview">

<!-- Announcements -->
<div class="ann-section" id="custAnnouncements" style="display:none"></div>

<div style="background:#fff;border-left:4px solid var(--p);border-radius:8px;padding:16px 18px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.08)">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px"><span style="background:var(--p);color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700">i</span><strong style="font-size:15px;color:#1e293b">Bắt đầu nhanh</strong></div>
    <div style="font-size:13px;color:#334155;line-height:1.7">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:8px">
            <div style="background:#f0fdf4;border-radius:8px;padding:12px">
                <div style="font-weight:700;color:#16a34a;font-size:12px;margin-bottom:6px">1. Nạp tiền</div>
                <div style="font-size:12px;color:#334155">Nạp tối thiểu <?php echo traffictop_format_money(floatval(traffictop_get_option('min_deposit_amount',50000))); ?> qua chuyển khoản ngân hàng hoặc USDT.</div>
            </div>
            <div style="background:#eff6ff;border-radius:8px;padding:12px">
                <div style="font-weight:700;color:#2563eb;font-size:12px;margin-bottom:6px">2. Tạo chiến dịch</div>
                <div style="font-size:12px;color:#334155">Chọn loại traffic (Keyword/Direct), nhập từ khóa và URL đích.</div>
            </div>
            <div style="background:#fefce8;border-radius:8px;padding:12px">
                <div style="font-weight:700;color:#b45309;font-size:12px;margin-bottom:6px">3. Chờ duyệt</div>
                <div style="font-size:12px;color:#334155">Admin duyệt chiến dịch trong vòng 24h. Traffic bắt đầu chạy ngay sau khi duyệt.</div>
            </div>
            <div style="background:#fdf2f8;border-radius:8px;padding:12px">
                <div style="font-weight:700;color:#be185d;font-size:12px;margin-bottom:6px">4. Theo dõi</div>
                <div style="font-size:12px;color:#334155">Xem biểu đồ và lịch sử traffic. Kiểm tra GSC & Analytics để đánh giá hiệu quả.</div>
            </div>
        </div>
        <div style="font-size:12px;color:var(--txtm);margin-top:4px">Lưu ý: Nên chạy liên tục 15-30 ngày với traffic vừa phải để đạt hiệu quả tốt nhất.</div>
    </div>
</div>

<div class="card">
<div class="card-h" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
    <h3 style="margin:0">Biểu đồ theo ngày</h3>
    <div class="cd-chart-legend">
        <span class="lg-traffic">Traffic</span>
        <span class="lg-spent">Đã chi</span>
    </div>
</div>
<div class="cd-chart-container">
    <canvas id="cdChart"></canvas>
</div>
</div>

</div><!-- /#p-overview -->
<?php endif; ?>

<!-- Create Campaign -->
<div class="pane<?php echo $is_minimal ? ' on' : ''; ?>" id="p-create">
<div class="card">
    <div class="card-h"><h3>Tạo chiến dịch mới</h3></div>

    <!-- Step 1: Service type -->
    <div style="margin-bottom:24px">
        <label style="display:block;font-size:13px;font-weight:600;color:var(--txt);margin-bottom:10px">Chọn loại dịch vụ <span style="color:var(--err)">*</span></label>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px" id="serviceTypes">
            <label class="svc-card selected" data-type="keyword_search">
                <input type="radio" name="task_type" value="keyword_search" checked style="display:none">
                <div class="svc-icon" style="background:linear-gradient(135deg,#EA4335,#FBBC05)"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09A6.69 6.69 0 0 1 5.5 12c0-.72.12-1.42.35-2.09V7.07H2.18A11.1 11.1 0 0 0 1 12c0 1.78.42 3.47 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg></div>
                <div class="svc-name">Traffic từ khóa</div>
                <div class="svc-price">Từ <?php echo traffictop_format_money(traffictop_get_option('keyword_price_1step', 1200)); ?>/lượt</div>
            </label>
            <label class="svc-card" data-type="traffic_direct">
                <input type="radio" name="task_type" value="traffic_direct" style="display:none">
                <div class="svc-icon" style="background:linear-gradient(135deg,#0D4F4F,#1A7A7A)"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div>
                <div class="svc-name">Traffic Direct</div>
                <div class="svc-price">Từ <?php echo traffictop_format_money(traffictop_get_option('direct_price_1step', 1200)); ?>/lượt</div>
            </label>
        </div>
    </div>

    <hr style="border:none;border-top:1px solid var(--brdl);margin:24px 0">

    <!-- Step 2: Campaign details -->
    <form id="createCampForm">
        <input type="hidden" name="task_type" id="campTaskType" value="keyword_search">

        <div style="display:grid;grid-template-columns:1fr 1fr 100px;gap:14px;margin-bottom:14px" id="kwFields">
            <div>
                <label class="cf-label">Từ khóa cần chạy <span style="color:var(--err)">*</span></label>
                <input type="text" name="keyword" class="cf-input" placeholder="Từ khóa cần chạy" id="campKeyword">
            </div>
            <div>
                <label class="cf-label">URL bài viết <span style="color:var(--err)">*</span></label>
                <input type="url" name="target_url" class="cf-input" placeholder="https://example.com/bai-viet" required id="campTargetUrl">
            </div>
            <div>
                <label class="cf-label">Traffic/ngày</label>
                <input type="number" name="daily_traffic" class="cf-input" id="createDailyTraffic" value="100" min="10" max="1000" oninput="checkDailyMin()">
                <div id="dailyMinWarn" style="display:none;font-size:11px;color:var(--err);margin-top:4px">Tối thiểu 10 traffic/ngày</div>
            </div>
        </div>
        <!-- URL + daily traffic for Direct (shown when kwFields hidden) -->
        <div style="display:none;grid-template-columns:1fr 100px;gap:14px;margin-bottom:14px" id="directFields">
            <div>
                <label class="cf-label">URL bài viết <span style="color:var(--err)">*</span></label>
                <input type="url" name="target_url_direct" class="cf-input" placeholder="https://example.com/bai-viet" id="campTargetUrlDirect">
            </div>
            <div>
                <label class="cf-label">Traffic/ngày</label>
                <input type="number" name="daily_traffic_direct" class="cf-input" id="createDailyTrafficDirect" value="100" min="10" max="1000">
            </div>
        </div>
        <input type="hidden" name="title" value="">

        <!-- Screenshot upload -->
        <div style="margin-bottom:18px" id="screenshotSection">
            <label class="cf-label">Ảnh hiển thị kết quả trên Google <span style="color:var(--err)">*</span></label>
            <p style="font-size:11px;color:var(--txtm);margin-bottom:12px">Chụp màn hình vị trí website của bạn trên kết quả tìm kiếm Google để user dễ tìm thấy</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div>
                    <div class="ss-upload" id="ssDesktopWrap">
                        <div class="ss-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg> Desktop</div>
                        <div class="ss-preview" id="ssDesktopPreview">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#D1CEC7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <span>Chưa có ảnh</span>
                        </div>
                        <label class="ss-btn" id="ssDesktopBtn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Tải ảnh
                            <input type="file" accept="image/*" style="display:none" onchange="imgbbUpload(this,'ssDesktopPreview','screenshot_desktop_url','ssDesktopBtn')">
                        </label>
                        <input type="hidden" name="screenshot_desktop_url" id="ssDesktopUrlHidden">
                    </div>
                </div>
                <div>
                    <div class="ss-upload" id="ssMobileWrap">
                        <div class="ss-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg> Mobile</div>
                        <div class="ss-preview" id="ssMobilePreview">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#D1CEC7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <span>Chưa có ảnh</span>
                        </div>
                        <label class="ss-btn" id="ssMobileBtn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Tải ảnh
                            <input type="file" accept="image/*" style="display:none" onchange="imgbbUpload(this,'ssMobilePreview','screenshot_mobile_url','ssMobileBtn')">
                        </label>
                        <input type="hidden" name="screenshot_mobile_url" id="ssMobileUrlHidden">
                    </div>
                </div>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid var(--brdl);margin:20px 0">

        <!-- Traffic type -->
        <div style="margin-bottom:18px">
            <label class="cf-label">Loại traffic</label>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px" id="trafficTypes">
                <label class="tt-option selected">
                    <input type="radio" name="traffic_type" value="1step" checked>
                    <span class="tt-label">Gói 1 bước</span>
                    <span class="tt-price" id="price1step"><?php echo traffictop_format_money(traffictop_get_option('keyword_price_1step', 1200)); ?></span>
                </label>
                <label class="tt-option">
                    <input type="radio" name="traffic_type" value="2step">
                    <span class="tt-label">Gói 2 bước</span>
                    <span class="tt-price" id="price2step"><?php echo traffictop_format_money(traffictop_get_option('keyword_price_2step', 1500)); ?></span>
                </label>
                <label class="tt-option">
                    <input type="radio" name="traffic_type" value="nocode">
                    <span class="tt-label">Mã cố định</span>
                    <span class="tt-price" id="priceNocode"><?php echo traffictop_format_money(traffictop_get_option('keyword_price_nocode', 1200)); ?></span>
                </label>
            </div>
        </div>

        <!-- Nocode: Fixed code + screenshot (hidden by default, shown when nocode selected) -->
        <div id="nocodeFields" style="display:none;margin-bottom:18px;background:#FFF9F0;border:1.5px solid #F0DCC0;border-radius:var(--rad);padding:18px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div>
                <label class="cf-label" style="color:#92400E"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg> Mã xác nhận cố định <span style="color:var(--err)">*</span></label>
                <input type="text" name="fixed_code" class="cf-input" placeholder="VD: ABC123, PROMO2024..." id="campFixedCode">
                <div style="font-size:11px;color:var(--txtm);margin-top:4px">Mã hiển thị trên trang đích</div>
            </div>
            <div>
                <label class="cf-label" style="color:#92400E"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg> Ảnh vị trí mã <span style="color:var(--err)">*</span></label>
                <div class="ss-upload" id="ssNocodeWrap" style="padding:10px">
                    <div class="ss-preview" id="ssNocodePreview" style="min-height:60px">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#D1CEC7" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <span style="color:#9CA3AF;font-size:11px">Chưa có ảnh</span>
                    </div>
                    <label id="ssNocodeBtn" style="display:block;padding:6px;background:#7C3AED;color:#fff;border-radius:6px;text-align:center;cursor:pointer;font-size:12px;font-weight:600;margin-top:6px">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Tải ảnh
                        <input type="file" accept="image/*" style="display:none" onchange="imgbbUpload(this,'ssNocodePreview','nocode_screenshot_url','ssNocodeBtn')">
                    </label>
                    <input type="hidden" name="nocode_screenshot_url" id="ssNocodeUrlHidden">
                </div>
            </div>
            </div>
        </div>

        <!-- Onsite time -->
        <div style="margin-bottom:18px">
            <label class="cf-label">Thời gian onsite</label>
            <?php $oe_cust = array(70=>(int)traffictop_get_option('onsite_extra_70',0),80=>(int)traffictop_get_option('onsite_extra_80',100),90=>(int)traffictop_get_option('onsite_extra_90',200),100=>(int)traffictop_get_option('onsite_extra_100',300),120=>(int)traffictop_get_option('onsite_extra_120',400),150=>(int)traffictop_get_option('onsite_extra_150',500)); ?>
            <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:8px" id="onsiteTimes">
                <?php $first=true; foreach($oe_cust as $s=>$e): ?>
                <label class="ot-option<?php if($first){echo ' selected';$first=false;} ?>"><input type="radio" name="onsite_time" value="<?php echo $s; ?>"<?php if($s==70) echo ' checked'; ?>><span><?php echo $s; ?>s</span><?php if($e>0): ?><small style="color:var(--err)">(+<?php echo number_format($e); ?>đ)</small><?php endif; ?></label>
                <?php endforeach; ?>
            </div>
        </div>

        <span id="priceDisplay" style="display:none"></span>

        <!-- Cost estimation -->
        <div style="border:1.5px solid var(--brdl);border-radius:var(--rad);padding:14px 16px;margin-bottom:20px">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;flex-wrap:wrap">
                <span style="font-weight:700;font-size:13px;color:var(--p)">Ước tính chi phí</span>
                <div style="display:flex;align-items:center;gap:6px"><label style="font-size:12px;color:var(--txtm)">Số ngày:</label><input type="number" name="days" class="cf-input" value="30" min="1" max="365" id="campDays" style="width:70px;padding:6px 10px;font-size:13px"></div>
                <span style="font-size:11px;color:#92400E;background:#FFF9E6;padding:4px 10px;border-radius:4px">Khuyến nghị: Nên chạy tối thiểu <strong>30 ngày</strong> để mang lại hiệu quả cao nhất cho SEO</span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;text-align:center">
                <div style="background:var(--bg);border-radius:var(--rads);padding:10px">
                    <div style="font-size:10px;color:var(--txtm)">Tổng traffic</div>
                    <div style="font-family:var(--fonth);font-size:18px;color:var(--info)" id="estTotal">3000</div>
                </div>
                <div style="background:var(--bg);border-radius:var(--rads);padding:10px">
                    <div style="font-size:10px;color:var(--txtm)">Chi phí/ngày</div>
                    <div style="font-family:var(--fonth);font-size:18px;color:var(--a)" id="estDaily">120.000đ</div>
                </div>
                <div style="background:var(--p);border-radius:var(--rads);padding:10px;color:#fff">
                    <div style="font-size:10px;opacity:.7">Tổng chi phí</div>
                    <div style="font-family:var(--fonth);font-size:18px" id="estTotalCost">3.600.000đ</div>
                </div>
            </div>
        </div>

        <!-- Info -->
        <div style="background:#EFF6FF;border:1px solid #DBEAFE;border-radius:var(--rads);padding:14px;font-size:12px;color:#1E40AF;margin-bottom:20px;line-height:1.6">
            Chiến dịch sẽ được Admin duyệt trước khi chạy. Tiền sẽ được trừ dần theo từng lượt traffic hoàn thành. Yêu cầu số dư tối thiểu <?php echo traffictop_format_money(traffictop_get_option('customer_min_balance', 20000)); ?>.
        </div>

        <button type="submit" id="campSubmitBtn" style="display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:linear-gradient(135deg,#2563EB,#1D4ED8);color:#fff;border:none;border-radius:var(--rads);font-size:14px;font-weight:700;font-family:var(--font);cursor:pointer;transition:all .2s;box-shadow:0 2px 8px rgba(37,99,235,.3)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Tạo chiến dịch
        </button>
        <div id="campMsg" style="margin-top:10px;font-size:13px"></div>
    </form>
</div>

<!-- Mã gắn vào Website -->
<div class="card">
    <div class="card-h"><h3>Mã gắn vào Website/URL cần chạy traffic</h3></div>

    <div style="background:#EFF6FF;border:1px solid #DBEAFE;border-radius:var(--rads);padding:16px;margin-bottom:16px;font-size:13px;color:#1E40AF;line-height:1.7">
        <strong>Đối với loại <span style="color:var(--err)">Traffic 1 bước</span> và <span style="color:var(--info)">Traffic 2 bước</span>:</strong>
        Gắn mã sau đây vào phần HTML, hoặc mục cài đặt Script của web (Vị trí nào cho phép gắn script là đều được).
    </div>

    <?php $widget_v = rand(1000, 9999); ?>
    <div style="background:#FFF5F5;border:1.5px solid #FED7D7;border-radius:var(--rads);padding:14px 18px;margin-bottom:16px;font-family:var(--mono);font-size:12px;color:#C53030;word-break:break-all">
        &lt;script src="<?php echo home_url('/widget.js?v=' . $widget_v); ?>"&gt;&lt;/script&gt;
    </div>

    <div style="display:flex;gap:8px;margin-bottom:16px">
        <button type="button" onclick="copyWidgetCode()" style="padding:8px 16px;background:var(--p);color:#fff;border:none;border-radius:var(--rads);font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font)">Copy mã</button>
        <span id="widgetCopyMsg" style="font-size:12px;color:var(--ok);line-height:32px"></span>
    </div>

    <div style="font-size:12px;color:var(--txtl);line-height:1.7;margin-bottom:16px">
        <strong>Mẹo:</strong> Thay đổi <code>?v=xxxx</code> để buộc refresh khi cần cập nhật giao diện nút.<br>
        Nên thường xuyên thay đổi gắn mã ở các vị trí khác nhau thay vì cố định một chỗ để đạt hiệu quả SEO cao nhất.
    </div>

    <div style="font-size:13px;color:var(--txt);line-height:1.7;margin-bottom:16px">
        Khi gắn mã thành công, trên Website của bạn sẽ xuất hiện nút giống như thế này. User có thể chủ động vào Google tìm kiếm từ khoá bất kỳ về website rồi click vào kết quả để kiểm tra.
    </div>

    <hr style="border:none;border-top:1px solid var(--brdl);margin:16px 0">

    <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:var(--rads);padding:14px;font-size:13px;color:#92400E;line-height:1.7">
        <strong>Đối với loại traffic MÃ CỐ ĐỊNH</strong> thì không cần gắn mã vào cuối trang. Mã sẽ là các thông tin có sẵn trên website của bạn (SĐT, Email, MST,...).
    </div>
</div>
</div>

<?php if(!$is_minimal): ?>
<!-- Campaigns -->
<div class="pane" id="p-campaigns">
<div class="card">
    <div class="card-h">
        <h3>Chiến dịch (<?php echo $camp_total; ?>)</h3>
        <span style="font-size:12px;color:var(--txtm)">Đang chạy: <?php echo count($active_camps); ?></span>
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
        $status_labels = array('active'=>'Đang chạy','paused'=>'Tạm dừng','pending'=>'Chờ duyệt','completed'=>'Hoàn thành','rejected'=>'Từ chối');
        $status_colors = array('active'=>'b-ok','paused'=>'b-warn','pending'=>'b-info','completed'=>'b-mute','rejected'=>'b-err');
        $tt = $c->task_type ?? 'keyword_search';
        $pct = $c->quantity > 0 ? round(($c->total_completed / $c->quantity) * 100) : 0;
        $spent = $c->total_completed * ($c->price_per_view ?? 0);
    ?>
    <tr>
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
        <td style="font-weight:600;color:var(--a)"><?php echo traffictop_format_money($c->price_per_view ?? 0); ?></td>
        <td>
            <div style="font-size:12px"><span style="color:var(--a);font-weight:600"><?php echo (int)$c->today_views; ?></span>/<?php echo (int)$c->daily_traffic; ?></div>
        </td>
        <td>
            <div style="font-weight:600;font-size:12px"><?php echo $c->total_completed; ?>/<?php echo $c->quantity; ?></div>
            <?php if($c->quantity > 0): ?>
            <div style="height:4px;background:var(--brdl);border-radius:2px;margin-top:4px;width:60px">
                <div style="height:100%;border-radius:2px;background:var(--p);width:<?php echo min(100,$pct); ?>%"></div>
            </div>
            <?php endif; ?>
            <div style="font-size:10px;color:var(--txtm);margin-top:2px">= <?php echo traffictop_format_money($spent); ?></div>
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
<div class="pane" id="p-deposit">
<div class="deposit-row">
<!-- Tạo đơn nạp tiền -->
<div class="card">
    <div class="card-h"><h3>Tạo đơn nạp tiền</h3></div>
    <form id="depositForm">
        <div style="margin-bottom:14px">
            <label class="cf-label">Số tiền muốn nạp <span style="color:var(--err)">*</span></label>
            <div style="position:relative">
                <input type="number" name="amount" class="cf-input" id="depAmount" placeholder="Nhập số tiền..." min="<?php echo traffictop_get_option('min_deposit_amount', 50000); ?>" required style="padding-right:30px">
                <span style="position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:13px;color:var(--txtm);font-weight:600">đ</span>
            </div>
            <div style="font-size:12px;color:var(--txtm);margin-top:4px">Số tiền tối thiểu: <?php echo traffictop_format_money(traffictop_get_option('min_deposit_amount', 50000)); ?></div>
            <?php $usdt_rate = intval(traffictop_get_option('deposit_usdt_rate', 25000)); if ($usdt_rate > 0 && (traffictop_get_option('deposit_usdt_erc20','') || traffictop_get_option('deposit_usdt_trc20',''))): ?>
            <div id="depUsdtConvert" style="display:none;font-size:13px;color:var(--info);font-weight:600;margin-top:6px;padding:8px 12px;background:#EFF6FF;border-radius:6px"></div>
            <?php endif; ?>
        </div>

        <div style="margin-bottom:18px">
            <label class="cf-label">Chọn mức nạp</label>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px" id="depPresets">
                <?php
                $presets = json_decode(traffictop_get_option('deposit_presets','[]'), true);
                if(empty($presets)) $presets = array(
                    array('amount' => 500000, 'bonus' => 0),
                    array('amount' => 1000000, 'bonus' => 0),
                    array('amount' => 5000000, 'bonus' => 0),
                    array('amount' => 10000000, 'bonus' => 5),
                    array('amount' => 20000000, 'bonus' => 5),
                    array('amount' => 50000000, 'bonus' => 10),
                );
                foreach ($presets as $p):
                    $label = $p['amount'] >= 1000000 ? ($p['amount']/1000000).'M' : number_format($p['amount']/1000).'K';
                ?>
                <button type="button" class="dep-preset" onclick="document.getElementById('depAmount').value=<?php echo $p['amount']; ?>;updateDepBonus();updateUsdtConvert()" style="position:relative">
                    <?php echo $label; ?>
                    <?php if($p['bonus'] > 0): ?>
                    <span style="position:absolute;top:-6px;right:-4px;background:var(--err);color:#fff;font-size:9px;font-weight:700;padding:1px 5px;border-radius:10px">+<?php echo $p['bonus']; ?>%</span>
                    <?php endif; ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="margin-bottom:14px">
            <label class="cf-label">Hình thức nạp</label>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <label style="flex:1;display:flex;align-items:center;gap:10px;padding:12px 16px;border:1.5px solid var(--brdl);border-radius:var(--rads);cursor:pointer;transition:all .2s;min-width:140px" class="pay-method-option selected" onclick="selectPayMethod(this)">
                    <input type="radio" name="payment_method" value="bank" checked style="width:18px;height:18px;accent-color:var(--p)">
                    <div>
                        <div style="font-weight:600;font-size:13px;color:var(--pd)">Ngân hàng</div>
                        <div style="font-size:11px;color:var(--txtm)">Chuyển khoản ngân hàng</div>
                    </div>
                </label>
                <?php if (traffictop_get_option('deposit_usdt_erc20','') || traffictop_get_option('deposit_usdt_trc20','')): ?>
                <label style="flex:1;display:flex;align-items:center;gap:10px;padding:12px 16px;border:1.5px solid var(--brdl);border-radius:var(--rads);cursor:pointer;transition:all .2s;min-width:140px" class="pay-method-option" onclick="selectPayMethod(this)">
                    <input type="radio" name="payment_method" value="usdt" style="width:18px;height:18px;accent-color:var(--p)">
                    <div>
                        <div style="font-weight:600;font-size:13px;color:var(--pd)">USDT</div>
                        <div style="font-size:11px;color:var(--txtm)">Crypto (ERC20/TRC20)</div>
                    </div>
                </label>
                <?php endif; ?>
            </div>
        </div>

        <div id="depBonusInfo" style="display:none;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:var(--rads);padding:12px;margin-bottom:14px;font-size:13px;color:#166534">
            <strong>Khuyến mãi:</strong> <span id="depBonusText"></span>
        </div>

        <button type="submit" id="depSubmitBtn" style="display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:linear-gradient(135deg,#2563EB,#1D4ED8);color:#fff;border:none;border-radius:var(--rads);font-size:14px;font-weight:700;font-family:var(--font);cursor:pointer;transition:all .2s;box-shadow:0 2px 8px rgba(37,99,235,.3)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Tạo đơn nạp tiền
        </button>
        <div id="depMsg" style="margin-top:8px;font-size:13px"></div>
    </form>
</div>

<!-- Thông tin chuyển khoản -->
<div class="card">
    <div class="card-h"><h3>Thông tin chuyển khoản</h3></div>
    <div style="background:#EFF6FF;border:1px solid #DBEAFE;border-radius:var(--rads);padding:14px;margin-bottom:16px;font-size:13px;color:#1E40AF;line-height:1.6">
        <strong>Hướng dẫn:</strong> Sau khi tạo đơn, chuyển khoản theo thông tin bên dưới với nội dung chính xác.
    </div>
    <div style="border:1.5px solid var(--brdl);border-radius:var(--rad);overflow:hidden">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px dashed var(--brdl)">
            <span style="color:var(--txtm);font-size:13px">Ngân hàng:</span>
            <span style="font-weight:700;font-size:15px"><?php echo esc_html(traffictop_get_option('deposit_bank','Vietcombank')); ?></span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px dashed var(--brdl)">
            <span style="color:var(--txtm);font-size:13px">Số tài khoản:</span>
            <div style="display:flex;align-items:center;gap:8px">
                <span style="font-weight:700;font-size:15px;font-family:var(--mono)" id="bankAccount"><?php echo esc_html(traffictop_get_option('deposit_account','0123456789')); ?></span>
                <button type="button" onclick="copyText('<?php echo esc_js(traffictop_get_option('deposit_account','0123456789')); ?>',this)" style="padding:4px 10px;background:var(--p);color:#fff;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer">Copy</button>
            </div>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px dashed var(--brdl)">
            <span style="color:var(--txtm);font-size:13px">Chủ tài khoản:</span>
            <span style="font-weight:700;font-size:15px"><?php echo esc_html(traffictop_get_option('deposit_holder','TRAFFICTOP')); ?></span>
        </div>
    </div>
    <div style="margin-top:12px;padding:12px 16px;background:#fff8e1;border:1px solid #ffe082;border-radius:var(--rad);font-size:13px;color:#795548;line-height:1.6">
        <strong style="color:#e65100">Lưu ý:</strong> Nội dung chuyển khoản để mặc định (Ví dụ: NGUYEN VAN A chuyển khoản). Liên hệ Admin gửi bill chuyển khoản để được cộng tiền.
    </div>
    <?php $usdt_erc = traffictop_get_option('deposit_usdt_erc20',''); $usdt_trc = traffictop_get_option('deposit_usdt_trc20',''); ?>
    <?php if ($usdt_erc || $usdt_trc): ?>
    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--brdl)">
        <div style="font-weight:700;font-size:14px;margin-bottom:12px;color:var(--pd)">Nạp bằng USDT</div>
        <div style="border:1.5px solid var(--brdl);border-radius:var(--rad);overflow:hidden">
            <?php if ($usdt_erc): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px dashed var(--brdl)">
                <span style="color:var(--txtm);font-size:13px">USDT (ERC20):</span>
                <div style="display:flex;align-items:center;gap:8px">
                    <span style="font-weight:600;font-size:11px;font-family:var(--mono);word-break:break-all"><?php echo esc_html($usdt_erc); ?></span>
                    <button type="button" onclick="copyText('<?php echo esc_js($usdt_erc); ?>',this)" style="padding:4px 10px;background:var(--p);color:#fff;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;flex-shrink:0">Copy</button>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($usdt_trc): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px">
                <span style="color:var(--txtm);font-size:13px">USDT (TRC20):</span>
                <div style="display:flex;align-items:center;gap:8px">
                    <span style="font-weight:600;font-size:11px;font-family:var(--mono);word-break:break-all"><?php echo esc_html($usdt_trc); ?></span>
                    <button type="button" onclick="copyText('<?php echo esc_js($usdt_trc); ?>',this)" style="padding:4px 10px;background:var(--p);color:#fff;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;flex-shrink:0">Copy</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:var(--rads);padding:14px;margin-top:16px;font-size:13px;color:#92400E;line-height:1.6">
        <strong>Lưu ý:</strong> Ghi đúng nội dung chuyển khoản. Liên hệ Admin nếu sau 30 phút chưa nhận được tiền.
    </div>
</div>
</div><!-- /.deposit-row -->

<!-- Số dư + Lịch sử nạp -->
<div class="dep-grid">
    <div class="card"><div class="card-h"><h3>Số dư hiện tại</h3></div>
    <div style="text-align:center;padding:16px">
        <div style="font-family:var(--fonth);font-size:28px;color:var(--ok);word-break:break-word"><?php echo traffictop_format_money($cust_balance); ?></div>
        <div style="font-size:12px;color:var(--txtm);margin-top:6px">Đã nạp: <?php echo traffictop_format_money($total_deposited); ?> | Đã chi: <?php echo traffictop_format_money($total_spent); ?></div>
    </div></div>

    <div class="card"><div class="card-h"><h3>Lịch sử nạp tiền</h3><span style="font-size:11px;color:var(--txtm)">Tổng: <?php echo count($deposits); ?> đơn</span></div>
    <div style="overflow-x:auto">
    <table style="min-width:650px"><thead><tr><th>#</th><th>Số tiền</th><th>KM</th><th>Tổng</th><th style="white-space:nowrap">Hình thức</th><th style="white-space:nowrap">Ghi chú</th><th style="white-space:nowrap">Trạng thái</th><th>Ngày</th></tr></thead><tbody id="depositsListContainer">
    <?php if(empty($deposits)): ?>
    <tr><td colspan="8" style="text-align:center;color:var(--txtm)">Chưa có</td></tr>
    <?php else: foreach($deposits as $dep):
        $bc=array('pending'=>'b-warn','approved'=>'b-ok','rejected'=>'b-err');
        $bl=array('pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối');
        $bonus = isset($dep->bonus_amount) ? (float)$dep->bonus_amount : 0;
        $total = (float)$dep->amount + $bonus;
    ?>
    <tr>
        <td style="font-size:12px;color:var(--txtm)">#<?php echo $dep->id; ?></td>
        <td style="font-weight:600;color:<?php echo (float)$dep->amount >= 0 ? 'var(--ok)' : 'var(--err)'; ?>"><?php echo ((float)$dep->amount >= 0 ? '+' : '') . traffictop_format_money($dep->amount); ?></td>
        <td style="white-space:nowrap"><?php if($bonus > 0): ?><span style="font-size:11px;font-weight:600;color:var(--err)">+<?php echo traffictop_format_money($bonus); ?></span><?php else: ?><span style="font-size:11px;color:var(--txtm)">—</span><?php endif; ?></td>
        <td style="font-weight:600"><?php echo traffictop_format_money($total); ?></td>
        <td style="white-space:nowrap"><?php $pm = $dep->payment_method ?? 'bank'; $usdt_r = intval(traffictop_get_option('deposit_usdt_rate',25000)); ?>
            <?php if($pm==='usdt' && $usdt_r > 0): ?>
            <span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:4px;background:#DBEAFE;color:#2563EB"><?php echo number_format((float)$dep->amount / $usdt_r, 1); ?> USDT</span>
            <?php else: ?>
            <span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:4px;background:#F3F4F6;color:#6B7280">CK</span>
            <?php endif; ?>
        </td>
        <td style="font-size:12px;color:var(--txtl)"><?php echo esc_html($dep->note ?? ''); ?></td>
        <td><span class="badge <?php echo $bc[$dep->status]??'b-mute'; ?>"><?php echo $bl[$dep->status] ?? $dep->status; ?></span></td>
        <td><small><?php echo date('d/m/Y',strtotime($dep->created_at)); ?></small></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody></table>
    <?php if($dep_pages > 1): ?>
    <div class="cust-paging"><?php for($i=1;$i<=$dep_pages;$i++): ?><a href="?tab=deposit&dep_page=<?php echo $i; ?>" class="pg-btn<?php echo $i===$dep_page?' on':''; ?>"><?php echo $i; ?></a><?php endfor; ?></div>
    <?php endif; ?>
    </div></div>
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
        <td style="white-space:nowrap;font-weight:600;color:var(--err)">-<?php echo traffictop_format_money($cost); ?></td>
        <td style="white-space:nowrap">
            <?php if($vh->customer_paid): ?>
                <span class="badge b-ok">Hoàn thành</span>
            <?php else: ?>
                <span class="badge b-warn">Không tính phí</span>
            <?php endif; ?>
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
<div class="account-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:900px">
    <div class="card">
        <h3 class="card-h" style="margin-bottom:16px">Cập nhật thông tin</h3>
        <div style="margin-bottom:14px">
            <label style="display:block;font-size:12px;font-weight:600;color:var(--txtl);margin-bottom:4px">Tên đăng nhập</label>
            <input type="text" value="<?php echo esc_attr($user->user_login); ?>" disabled style="width:100%;padding:10px 12px;border:1px solid var(--brdl);border-radius:var(--rads);background:var(--bg);color:var(--txtl);font-size:14px">
        </div>
        <form id="frmProfile" onsubmit="return saveProfile(this)">
            <div style="margin-bottom:14px">
                <label style="display:block;font-size:12px;font-weight:600;color:var(--txt);margin-bottom:4px">Email</label>
                <input type="email" name="email" value="<?php echo esc_attr($user->user_email); ?>" required style="width:100%;padding:10px 12px;border:1px solid var(--brdl);border-radius:var(--rads);background:var(--card);font-size:14px">
            </div>
            <div style="margin-bottom:16px">
                <label style="display:block;font-size:12px;font-weight:600;color:var(--txt);margin-bottom:4px">Số điện thoại</label>
                <input type="tel" name="phone" value="<?php echo esc_attr(get_user_meta($user_id, 'phone', true)); ?>" style="width:100%;padding:10px 12px;border:1px solid var(--brdl);border-radius:var(--rads);background:var(--card);font-size:14px">
            </div>
            <button type="submit" style="width:100%;padding:10px;background:var(--p);color:#fff;border:none;border-radius:var(--rads);font-family:var(--font);font-size:13px;font-weight:600;cursor:pointer">Lưu thay đổi</button>
            <div id="profileMsg" style="margin-top:8px;font-size:12px"></div>
        </form>
    </div>

    <div class="card">
        <h3 class="card-h" style="margin-bottom:16px">Đổi mật khẩu</h3>
        <form id="frmPassword" onsubmit="return changePassword(this)">
            <div style="margin-bottom:14px">
                <label style="display:block;font-size:12px;font-weight:600;color:var(--txt);margin-bottom:4px">Mật khẩu hiện tại</label>
                <input type="password" name="current_password" required style="width:100%;padding:10px 12px;border:1px solid var(--brdl);border-radius:var(--rads);background:var(--card);font-size:14px">
            </div>
            <div style="margin-bottom:14px">
                <label style="display:block;font-size:12px;font-weight:600;color:var(--txt);margin-bottom:4px">Mật khẩu mới</label>
                <input type="password" name="new_password" required minlength="6" style="width:100%;padding:10px 12px;border:1px solid var(--brdl);border-radius:var(--rads);background:var(--card);font-size:14px">
            </div>
            <div style="margin-bottom:16px">
                <label style="display:block;font-size:12px;font-weight:600;color:var(--txt);margin-bottom:4px">Xác nhận mật khẩu mới</label>
                <input type="password" name="confirm_password" required minlength="6" style="width:100%;padding:10px 12px;border:1px solid var(--brdl);border-radius:var(--rads);background:var(--card);font-size:14px">
            </div>
            <button type="submit" style="width:100%;padding:10px;background:var(--p);color:#fff;border:none;border-radius:var(--rads);font-family:var(--font);font-size:13px;font-weight:600;cursor:pointer">Đổi mật khẩu</button>
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
                <div>
                    <label class="cf-label">Từ khóa</label>
                    <input type="text" id="editCampKeyword" class="cf-input" placeholder="Từ khóa cần chạy">
                </div>
                <div>
                    <label class="cf-label">URL bài viết <span style="color:var(--err)">*</span></label>
                    <input type="url" id="editCampUrl" class="cf-input" placeholder="https://example.com/bai-viet" required>
                </div>
                <div>
                    <label class="cf-label">Traffic/ngày</label>
                    <input type="number" id="editCampDaily" class="cf-input" min="10" max="1000">
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
                    borderColor: '#3b82f6',
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
                    borderColor: '#f59e0b',
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
                    backgroundColor: '#1f2937',
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
                    grid: { color: '#f3f4f6' },
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
    fd.append('action','traffictop_update_profile');
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
    fd.append('action','traffictop_change_password');
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
    keyword_search: { '1step': <?php echo (int)traffictop_get_option('keyword_price_1step', 1200); ?>, '2step': <?php echo (int)traffictop_get_option('keyword_price_2step', 1500); ?>, 'nocode': <?php echo (int)traffictop_get_option('keyword_price_nocode', 1200); ?> },
    traffic_direct: { '1step': <?php echo (int)traffictop_get_option('direct_price_1step', 1200); ?>, '2step': <?php echo (int)traffictop_get_option('direct_price_2step', 1200); ?>, 'nocode': <?php echo (int)traffictop_get_option('direct_price_nocode', 1200); ?> }
};
var ONSITE_EXTRA = {70:<?php echo (int)traffictop_get_option('onsite_extra_70',0); ?>,80:<?php echo (int)traffictop_get_option('onsite_extra_80',100); ?>,90:<?php echo (int)traffictop_get_option('onsite_extra_90',200); ?>,100:<?php echo (int)traffictop_get_option('onsite_extra_100',300); ?>,120:<?php echo (int)traffictop_get_option('onsite_extra_120',400); ?>,150:<?php echo (int)traffictop_get_option('onsite_extra_150',500); ?>};
var NONCE = '<?php echo wp_create_nonce("traffictop_nonce"); ?>';
var AJAX = '<?php echo admin_url("admin-ajax.php"); ?>';

function fmtMoney(n){return n.toLocaleString('vi-VN')+'đ'}

function selectPayMethod(el){document.querySelectorAll('.pay-method-option').forEach(function(x){x.classList.remove('selected');x.style.borderColor='';x.style.background=''});el.classList.add('selected');el.style.borderColor='var(--p)';el.style.background='#F0F9F9';if(typeof updateUsdtConvert==='function')updateUsdtConvert()}
document.querySelectorAll('.pay-method-option.selected').forEach(function(el){el.style.borderColor='var(--p)';el.style.background='#F0F9F9'});

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
    $dep_presets = json_decode(traffictop_get_option('deposit_presets','[]'), true);
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
var USDT_RATE = <?php echo intval(traffictop_get_option('deposit_usdt_rate', 25000)); ?>;
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
    fd.append('action','traffictop_customer_deposit');
    fd.append('nonce',NONCE);
    var btn=document.getElementById('depSubmitBtn');
    var msg=document.getElementById('depMsg');
    btn.disabled=true;btn.innerHTML='Đang tạo...';
    fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if(r.success){
            msg.innerHTML='<span style="color:var(--ok)">Đơn nạp tiền đã tạo! Vui lòng chuyển khoản.</span>';
            setTimeout(function(){location.reload()},2000);
        }else{
            msg.innerHTML='<span style="color:var(--err)">'+(r.data||'Lỗi')+'</span>';
            btn.disabled=false;btn.innerHTML='Tạo đơn nạp tiền';
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
    var code='<script src="<?php echo home_url("/widget.js?v=" . $widget_v); ?>"><\/script>';
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
    fd.append('action','traffictop_upload_screenshot');
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
    fd.append('action','traffictop_customer_create_campaign');
    fd.append('nonce',NONCE);
    var adminCust=document.getElementById('adminCustomerId');
    if(adminCust&&adminCust.value)fd.append('admin_customer_id',adminCust.value);
    var btn=document.getElementById('campSubmitBtn');
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
    btn.disabled=true;btn.innerHTML='Đang tạo...';
    fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if(r.success){
            msg.innerHTML='<span style="color:var(--ok)">Chiến dịch đã được tạo! Chờ Admin duyệt.</span>';
            setTimeout(function(){location.reload()},2000);
        }else{
            msg.innerHTML='<span style="color:var(--err)">'+(r.data||'Lỗi')+'</span>';
            btn.disabled=false;btn.innerHTML='Tạo chiến dịch';
        }
    });
});

// === Campaign Actions ===
function toggleCampaign(id, status) {
    var label = status === 'paused' ? 'Tạm dừng' : 'Tiếp tục';
    if (!confirm(label + ' chiến dịch #' + id + '?')) return;
    var fd = new FormData();
    fd.append('action', 'traffictop_customer_toggle_campaign');
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
    fd.append('action', 'traffictop_customer_get_campaign');
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
    fd.append('action', 'traffictop_customer_get_campaign');
    fd.append('nonce', NONCE);
    fd.append('campaign_id', id);
    fetch(AJAX, {method:'POST', body:fd, credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if (!r.success) { toast(r.data || 'Lỗi', 'err'); return; }
        var c = r.data;
        _editOriginal = {
            keyword: c.keyword||'', target_url: c.target_url||'', title: c.title||'',
            traffic_type: c.traffic_type||'1step', onsite_time: String(c.onsite_time||70),
            task_type: c.task_type||'keyword_search'
        };

        document.getElementById('editCampId').value = c.id;
        document.getElementById('editCampKeyword').value = c.keyword || '';
        document.getElementById('editCampDaily').value = c.daily_traffic || 10;
        document.getElementById('editCampUrl').value = c.target_url || '';
        document.getElementById('editCampTitle').value = c.title || '';
        document.getElementById('editCampTrafficType').value = c.traffic_type || '1step';
        document.getElementById('editCampOnsite').value = String(c.onsite_time || 70);
        editUpdatePrice();

        // Show/hide keyword field
        document.getElementById('editKwFields').style.display = (c.task_type === 'keyword_search') ? 'grid' : 'none';

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
    fd.append('action', 'traffictop_upload_screenshot');
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
    btn.disabled = true; btn.textContent = 'Đang lưu...';

    var fd = new FormData();
    fd.append('action', 'traffictop_customer_edit_campaign');
    fd.append('nonce', NONCE);
    fd.append('campaign_id', id);
    fd.append('keyword', document.getElementById('editCampKeyword').value);
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
    fd.append('action', 'traffictop_customer_delete_campaign');
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
        var fd=new FormData();fd.append('action','traffictop_customer_load_more');fd.append('nonce',NONCE);fd.append('type',type);fd.append('offset',offset);
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
    var fd=new FormData();fd.append('action','traffictop_get_announcements');fd.append('nonce',NONCE);fd.append('target','customer');
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
<?php wp_footer(); ?>
</body>
</html>
