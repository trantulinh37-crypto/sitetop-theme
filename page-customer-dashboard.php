<?php
/**
 * Template Name: Customer Dashboard
 * LinkNgon V2 - Customer Dashboard (nhà quảng cáo mua campaign)
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
$prefix = $wpdb->prefix . 'linkngon_';
$today  = date( 'Y-m-d', strtotime( linkngon_current_time() ) );

// Stats
$cust_balance    = linkngon_get_customer_balance_amount( $user_id );
$total_deposited = (float) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(SUM(amount),0) FROM {$prefix}customer_transactions WHERE customer_id=%d AND type='deposit' AND amount>0", $user_id ) );
$total_spent = (float) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(ABS(SUM(amount)),0) FROM {$prefix}customer_transactions WHERE customer_id=%d AND type='campaign_view' AND amount<0", $user_id ) );

// Campaigns (with order info)
$my_campaigns = $wpdb->get_results( $wpdb->prepare(
    "SELECT kc.*, co.task_type,
            (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND step='verified') as total_completed,
            (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND step='verified' AND DATE(created_at)=%s) as today_views
     FROM {$prefix}keyword_campaigns kc
     LEFT JOIN {$prefix}customer_orders co ON kc.order_id = co.id
     WHERE kc.customer_id = %d
     ORDER BY kc.created_at DESC LIMIT 10", $today, $user_id ) );
$active_camps  = array_filter( $my_campaigns, function($c){ return $c->status==='active'; } );
$total_views   = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$prefix}shortlink_visits v INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id=kc.id WHERE kc.customer_id=%d AND v.step='verified'", $user_id ) );
$today_views   = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$prefix}shortlink_visits v INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id=kc.id WHERE kc.customer_id=%d AND v.step='verified' AND DATE(v.created_at)=%s", $user_id, $today ) );

$deposits = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$prefix}customer_deposits WHERE customer_id=%d AND (visible IS NULL OR visible = 1) ORDER BY created_at DESC LIMIT 10", $user_id ) );
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
     ORDER BY v.created_at DESC LIMIT 10", $user_id ) );

// 30-day chart
$chart=array();
for($i=29;$i>=0;$i--){
    $d=date('Y-m-d',strtotime("-{$i} days",strtotime(linkngon_current_time())));
    $v=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}shortlink_visits v INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id=kc.id WHERE kc.customer_id=%d AND v.step='verified' AND DATE(v.created_at)=%s",$user_id,$d));
    $chart[]=array('date'=>date('d/m',strtotime($d)),'views'=>$v);
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

.topbar{background:#fff;border-bottom:1px solid var(--brdl);padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:54px;position:sticky;top:0;z-index:50}
.topbar .logo{font-family:var(--fonth);font-weight:800;font-size:20px;color:var(--p);text-decoration:none;display:inline-flex;align-items:center}
.topbar nav{display:flex;gap:14px;align-items:center;font-size:13px}
.topbar nav a{color:var(--txtl);text-decoration:none}
.role-tag{padding:4px 12px;background:#FEF3C7;color:#92400E;border-radius:20px;font-size:11px;font-weight:600}

.hero{background:linear-gradient(135deg,#083838 0%,#0D4F4F 40%,#1A7A7A 100%);color:#fff;padding:32px 24px 24px;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;width:300px;height:300px;border-radius:50%;background:rgba(232,168,56,.06);top:-120px;right:-80px}
.hero::after{content:'';position:absolute;width:200px;height:200px;border-radius:50%;background:rgba(232,168,56,.04);bottom:-80px;left:-40px}
.hero *{position:relative;z-index:1}
.hero-inner{max-width:1100px;margin:0 auto}
.hero h1{font-family:var(--fonth);font-weight:800;font-size:22px;color:#fff;margin-bottom:2px}
.hero .sub{color:rgba(255,255,255,.45);font-size:12px}
.hero-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:0;margin-top:20px;background:rgba(255,255,255,.06);border-radius:12px;overflow:hidden}
.hero-stat{padding:16px 12px;text-align:center;border-right:1px solid rgba(255,255,255,.06)}
.hero-stat:last-child{border-right:none}
.hero-stat .hs-label{font-size:9px;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.65);margin-bottom:6px}
.hero-stat .hs-value{font-family:var(--fonth);font-weight:800;font-size:18px;color:#6EE7B7;line-height:1.2}
.hero-stat .hs-value.gold{color:#F0C060}
.hero-stat .hs-value.white{color:rgba(255,255,255,.9)}
@media(max-width:600px){.hero-stats{grid-template-columns:repeat(3,1fr)}.hero-stat{padding:12px 8px}.hero-stat .hs-value{font-size:14px}}

.container{max-width:1100px;margin:0 auto;padding:24px 0 0 0;overflow-x:hidden}
.tabs{display:flex;flex-wrap:wrap;gap:4px;background:var(--card);padding:5px;border-radius:var(--rad);border:1px solid var(--brdl);margin-bottom:24px}
.tb{padding:9px 16px;border-radius:var(--rads);border:none;background:transparent;color:var(--txtl);font-family:var(--font);font-size:13px;font-weight:500;cursor:pointer;white-space:nowrap;transition:all .2s}
.tb.on{background:var(--dark);color:#fff}
.pane{display:none;animation:fu .3s ease}.pane.on{display:block}
@keyframes fu{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

.card{background:var(--card);border-radius:var(--rad);border:1px solid var(--brdl);padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);margin-bottom:20px}
.card-h{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--brdl)}
.card-h h3{font-family:var(--fonth);font-size:17px;color:var(--pd)}
.sg{display:grid;gap:14px;margin-bottom:20px}
.sg4{grid-template-columns:repeat(4,1fr)}
.sc{background:var(--card);border-radius:var(--rad);padding:14px;border:1px solid var(--brdl);display:flex;align-items:center;gap:10px;transition:all .2s;min-width:0;overflow:hidden}
.sc:hover{box-shadow:0 4px 12px rgba(0,0,0,.06);transform:translateY(-1px)}
.sc-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sc-icon svg{width:20px;height:20px}
.sc.s1 .sc-icon{background:#DBEAFE;color:#2563EB}.sc.s2 .sc-icon{background:#FEF3C7;color:#D97706}
.sc.s3 .sc-icon{background:#D1FAE5;color:#059669}.sc.s4 .sc-icon{background:#E0F2FE;color:#0D4F4F}
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

.chart{display:flex;align-items:flex-end;gap:10px;height:130px;padding:12px 0}
.cbar{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px}
.cfill{width:100%;border-radius:5px 5px 0 0;background:linear-gradient(180deg,var(--info),#60A5FA);min-height:4px;transition:height .5s ease}
.clbl{font-size:9px;color:var(--txtm)}.cval{font-size:9px;color:var(--info);font-weight:600}

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
.ss-preview img{width:100%;height:auto;border-radius:var(--rads);display:block}
.ss-btn{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:10px;background:var(--info);color:#fff;border-radius:var(--rads);font-size:13px;font-weight:600;cursor:pointer;transition:all .2s}
.ss-btn:hover{background:#1D4ED8}

/* Deposit */
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
    .container{padding:16px!important}
    .sg4{grid-template-columns:repeat(2,1fr)}
    .ccgrid{grid-template-columns:1fr}
    .brow{gap:16px}
    .dep-grid{grid-template-columns:1fr!important}
    .tabs{gap:2px;padding:4px}
    .tb{padding:8px 12px;font-size:12px}
    #onsiteTimes{grid-template-columns:repeat(3,1fr)!important}
}
<?php if($is_minimal): ?>
#wpadminbar,html{margin-top:0!important}
#wpadminbar{display:none!important}
<?php endif; ?>
</style>
</head>
<body>
<?php if(!$is_minimal): ?>
<div class="topbar">
    <a href="<?php echo home_url(); ?>" class="logo"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>LinkNgon</a>
    <nav><span class="role-tag">ADVERTISER</span><a href="<?php echo home_url(); ?>">Trang chủ</a><a href="<?php echo wp_logout_url(home_url()); ?>">Đăng xuất</a></nav>
</div>

<div class="hero">
<div class="hero-inner">
    <h1><?php echo esc_html($user->display_name); ?></h1>
    <p class="sub">Quản lý chiến dịch & mua traffic cho website của bạn</p>
    <div class="hero-stats">
        <div class="hero-stat">
            <div class="hs-label">Số dư</div>
            <div class="hs-value"><?php echo linkngon_format_money($cust_balance); ?></div>
        </div>
        <div class="hero-stat">
            <div class="hs-label">Chiến dịch</div>
            <div class="hs-value gold"><?php echo count($active_camps); ?>/<?php echo count($my_campaigns); ?></div>
        </div>
        <div class="hero-stat">
            <div class="hs-label">Hôm nay</div>
            <div class="hs-value gold"><?php echo number_format($today_views); ?></div>
        </div>
        <div class="hero-stat">
            <div class="hs-label">Tổng views</div>
            <div class="hs-value gold"><?php echo number_format($total_views); ?></div>
        </div>
        <div class="hero-stat">
            <div class="hs-label">Đã chi</div>
            <div class="hs-value white"><?php echo linkngon_format_money($total_spent); ?></div>
        </div>
    </div>
</div></div>
<?php endif; ?>

<div class="container">
<?php if(!$is_minimal): ?>
<div class="tabs">
    <button class="tb on" data-t="overview"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>Tổng quan</button>
    <button class="tb" data-t="create"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Tạo mới</button>
    <button class="tb" data-t="campaigns"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>Chiến dịch</button>
    <button class="tb" data-t="deposit"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>Nạp tiền</button>
    <button class="tb" data-t="history"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Lịch sử</button>
</div>

<?php endif; ?>

<?php if(!$is_minimal): ?>
<!-- Overview -->
<div class="pane on" id="p-overview">
<div class="sg sg4">
    <div class="sc s1">
        <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg></div>
        <div class="sc-text"><div class="sl">Tổng chiến dịch</div><div class="sv"><?php echo count($my_campaigns); ?></div><div class="ss">Đang chạy: <?php echo count($active_camps); ?></div></div>
    </div>
    <div class="sc s2">
        <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div>
        <div class="sc-text"><div class="sl">Tổng views</div><div class="sv"><?php echo number_format($total_views); ?></div><div class="ss">Hôm nay: <?php echo number_format($today_views); ?></div></div>
    </div>
    <div class="sc s3">
        <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
        <div class="sc-text"><div class="sl">Đã nạp</div><div class="sv"><?php echo linkngon_format_money($total_deposited); ?></div></div>
    </div>
    <div class="sc s4">
        <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div class="sc-text"><div class="sl">Số dư</div><div class="sv"><?php echo linkngon_format_money($cust_balance); ?></div><div class="ss">Đã chi: <?php echo linkngon_format_money($total_spent); ?></div></div>
    </div>
</div>

<!-- Announcements -->
<div class="ann-section" id="custAnnouncements" style="display:none"></div>

<div class="card"><div class="card-h"><h3>Views 30 ngày</h3></div>
<?php $mx=max(array_column($chart,'views'))?:1; ?>
<div class="chart"><?php foreach($chart as $d):$h=max(4,($d['views']/$mx)*110); ?>
<div class="cbar"><div class="cval"><?php echo $d['views']; ?></div><div class="cfill" style="height:<?php echo $h; ?>px"></div><div class="clbl"><?php echo $d['date']; ?></div></div>
<?php endforeach; ?></div></div>
</div>
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
                <div class="svc-icon" style="background:linear-gradient(135deg,#0EA5E9,#06B6D4)"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
                <div class="svc-name">Traffic từ khóa</div>
                <div class="svc-price">Từ <?php echo linkngon_format_money(linkngon_get_option('keyword_price_1step', 1200)); ?>/lượt</div>
            </label>
            <label class="svc-card" data-type="traffic_direct">
                <input type="radio" name="task_type" value="traffic_direct" style="display:none">
                <div class="svc-icon" style="background:linear-gradient(135deg,#8B5CF6,#A78BFA)"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></div>
                <div class="svc-name">Traffic Direct</div>
                <div class="svc-price">Từ <?php echo linkngon_format_money(linkngon_get_option('direct_price_1step', 1200)); ?>/lượt</div>
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
                <input type="url" name="target_url" class="cf-input" placeholder="https://example.com/bai-viet" required>
            </div>
            <div>
                <label class="cf-label">Traffic/ngày</label>
                <input type="number" name="daily_traffic" class="cf-input" value="10" min="1" max="100">
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
                        <label class="ss-btn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Tải ảnh
                            <input type="file" name="screenshot_desktop" accept="image/*" style="display:none" onchange="previewSS(this,'ssDesktopPreview')">
                        </label>
                    </div>
                </div>
                <div>
                    <div class="ss-upload" id="ssMobileWrap">
                        <div class="ss-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg> Mobile</div>
                        <div class="ss-preview" id="ssMobilePreview">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#D1CEC7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <span>Chưa có ảnh</span>
                        </div>
                        <label class="ss-btn">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            Tải ảnh
                            <input type="file" name="screenshot_mobile" accept="image/*" style="display:none" onchange="previewSS(this,'ssMobilePreview')">
                        </label>
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
                    <span class="tt-price" id="price1step"><?php echo linkngon_format_money(linkngon_get_option('keyword_price_1step', 1200)); ?></span>
                </label>
                <label class="tt-option">
                    <input type="radio" name="traffic_type" value="2step">
                    <span class="tt-label">Gói 2 bước</span>
                    <span class="tt-price" id="price2step"><?php echo linkngon_format_money(linkngon_get_option('keyword_price_2step', 1500)); ?></span>
                </label>
                <label class="tt-option">
                    <input type="radio" name="traffic_type" value="nocode">
                    <span class="tt-label">Mã cố định</span>
                    <span class="tt-price" id="priceNocode"><?php echo linkngon_format_money(linkngon_get_option('keyword_price_nocode', 1200)); ?></span>
                </label>
            </div>
        </div>

        <!-- Nocode: Fixed code + screenshot (hidden by default, shown when nocode selected) -->
        <div id="nocodeFields" style="display:none;margin-bottom:18px;background:#FFF9F0;border:1.5px solid #F0DCC0;border-radius:var(--rad);padding:18px">
            <div style="margin-bottom:14px">
                <label class="cf-label" style="color:#92400E">🔑 Mã xác nhận cố định <span style="color:var(--err)">*</span></label>
                <input type="text" name="fixed_code" class="cf-input" placeholder="VD: ABC123, PROMO2024..." id="campFixedCode">
                <div style="font-size:11px;color:var(--txtm);margin-top:4px">Mã này sẽ hiển thị trên trang đích, user tìm và nhập mã để xác nhận</div>
            </div>
            <div>
                <label class="cf-label" style="color:#92400E">🖼 Ảnh mô tả vị trí mã <span style="color:var(--err)">*</span></label>
                <div style="font-size:11px;color:var(--txtm);margin-bottom:8px">Chụp màn hình vị trí đặt mã xác nhận trên website</div>
                <div class="ss-upload" id="ssNocodeWrap">
                    <div class="ss-preview" id="ssNocodePreview">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#D1CEC7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <span style="color:#9CA3AF;font-size:12px">Chưa có ảnh</span>
                    </div>
                    <label style="display:block;padding:8px;background:#7C3AED;color:#fff;border-radius:6px;text-align:center;cursor:pointer;font-size:13px;font-weight:600;margin-top:8px">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Tải ảnh lên
                        <input type="file" name="screenshot_nocode" accept="image/*" style="display:none" onchange="previewNocodeImg(this)">
                    </label>
                </div>
            </div>
        </div>

        <!-- Onsite time -->
        <div style="margin-bottom:18px">
            <label class="cf-label">Thời gian onsite</label>
            <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:8px" id="onsiteTimes">
                <label class="ot-option selected"><input type="radio" name="onsite_time" value="70" checked><span>70s</span></label>
                <label class="ot-option"><input type="radio" name="onsite_time" value="80"><span>80s</span></label>
                <label class="ot-option"><input type="radio" name="onsite_time" value="90"><span>90s</span><small style="color:var(--err)">(+100đ)</small></label>
                <label class="ot-option"><input type="radio" name="onsite_time" value="100"><span>100s</span><small style="color:var(--err)">(+200đ)</small></label>
                <label class="ot-option"><input type="radio" name="onsite_time" value="120"><span>120s</span><small style="color:var(--err)">(+250đ)</small></label>
                <label class="ot-option"><input type="radio" name="onsite_time" value="150"><span>150s</span><small style="color:var(--err)">(+300đ)</small></label>
            </div>
        </div>

        <!-- Price display -->
        <div style="background:linear-gradient(135deg,var(--a),#F0C060);border-radius:var(--rads);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
            <span style="font-weight:600;color:#083838;font-size:14px">Giá mỗi lượt traffic:</span>
            <span style="font-family:var(--fonth);font-weight:800;font-size:24px;color:#083838" id="priceDisplay"><?php echo linkngon_format_money(linkngon_get_option('keyword_price_1step', 1200)); ?>/lượt</span>
        </div>

        <!-- Cost estimation -->
        <div style="border:1.5px solid var(--brdl);border-radius:var(--rad);padding:20px;margin-bottom:20px">
            <div style="font-weight:700;font-size:14px;color:var(--p);margin-bottom:14px">Ước tính chi phí</div>
            <div style="margin-bottom:12px">
                <label class="cf-label">Số ngày chạy</label>
                <input type="number" name="days" class="cf-input" value="15" min="1" max="365" id="campDays" style="max-width:120px">
            </div>
            <div style="background:#FFF9E6;border-radius:var(--rads);padding:10px 14px;font-size:12px;color:#92400E;margin-bottom:14px">
                Khuyến nghị: Nên chạy tối thiểu <strong>15 ngày</strong> để mang lại hiệu quả cao nhất cho SEO
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center">
                <div style="background:var(--bg);border-radius:var(--rads);padding:12px">
                    <div style="font-size:11px;color:var(--txtm)">Tổng traffic</div>
                    <div style="font-family:var(--fonth);font-size:20px;color:var(--info)" id="estTotal">150</div>
                    <div style="font-size:10px;color:var(--txtm)">lượt</div>
                </div>
                <div style="background:var(--bg);border-radius:var(--rads);padding:12px">
                    <div style="font-size:11px;color:var(--txtm)">Chi phí/ngày</div>
                    <div style="font-family:var(--fonth);font-size:20px;color:var(--a)" id="estDaily">12.000đ</div>
                </div>
                <div style="background:var(--p);border-radius:var(--rads);padding:12px;color:#fff">
                    <div style="font-size:11px;opacity:.7">Tổng chi phí</div>
                    <div style="font-family:var(--fonth);font-size:20px" id="estTotalCost">180.000đ</div>
                </div>
            </div>
        </div>

        <!-- Info -->
        <div style="background:#EFF6FF;border:1px solid #DBEAFE;border-radius:var(--rads);padding:14px;font-size:12px;color:#1E40AF;margin-bottom:20px;line-height:1.6">
            Chiến dịch sẽ được Admin duyệt trước khi chạy. Tiền sẽ được trừ dần theo từng lượt traffic hoàn thành. Yêu cầu số dư tối thiểu <?php echo linkngon_format_money(linkngon_get_option('customer_min_balance', 20000)); ?>.
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

    <div style="background:#FFF5F5;border:1.5px solid #FED7D7;border-radius:var(--rads);padding:14px 18px;margin-bottom:16px;font-family:var(--mono);font-size:12px;color:#C53030;word-break:break-all">
        &lt;script src="<?php echo home_url('/widget.js?v=' . rand(1000, 9999)); ?>"&gt;&lt;/script&gt;
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
        <h3>Chiến dịch (<?php echo count($my_campaigns); ?>)</h3>
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
        <th>Từ khóa / URL</th>
        <th>Loại traffic</th>
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
        $step_labels = array('1step'=>'1 bước','2step'=>'2 bước','nocode'=>'Không mã');
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
        <td><span class="badge <?php echo $task_colors[$tt] ?? 'b-mute'; ?>"><?php echo $task_labels[$tt] ?? $tt; ?></span></td>
        <td>
            <div style="font-weight:600;font-size:12px"><?php echo $step_labels[$c->traffic_type] ?? $c->traffic_type; ?></div>
            <div style="font-size:10px;color:var(--txtm)"><?php echo (int)$c->onsite_time; ?>s</div>
        </td>
        <td style="font-weight:600;color:var(--a)"><?php echo linkngon_format_money($c->price_per_view ?? 0); ?></td>
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
            <div style="font-size:10px;color:var(--txtm);margin-top:2px">= <?php echo linkngon_format_money($spent); ?></div>
        </td>
        <td>
            <?php if($c->traffic_type === 'nocode'): ?>
                <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;color:var(--txtm)"><span style="width:8px;height:8px;border-radius:50%;background:var(--txtm);display:inline-block"></span> Không cần</span>
            <?php elseif(!empty($c->screenshot_desktop_url) || !empty($c->screenshot_mobile_url)): ?>
                <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;color:var(--ok)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--ok)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Đã gắn</span>
            <?php else: ?>
                <span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;color:var(--warn)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--warn)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> Chưa gắn</span>
            <?php endif; ?>
        </td>
        <td><span class="badge <?php echo $status_colors[$c->status] ?? 'b-mute'; ?>"><?php echo $status_labels[$c->status] ?? $c->status; ?></span></td>
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
        <td><?php
            $created = strtotime($c->created_at);
            $now = strtotime(linkngon_current_time());
            $diff_days = floor(($now - $created) / 86400);
            if($diff_days < 1) echo '<small>Hôm nay</small>';
            elseif($diff_days == 1) echo '<small>Hôm qua</small>';
            elseif($diff_days <= 30) echo '<small>'.$diff_days.' ngày</small>';
            else echo '<small>'.date('d/m/Y', $created).'</small>';
        ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>
    <?php if(count($my_campaigns) >= 10): ?>
    <button type="button" class="cust-load-more-btn" data-type="campaigns" data-offset="10" data-target="campaignsListContainer" style="padding:10px 24px;background:var(--bg);border:1.5px solid var(--brd);border-radius:var(--rads);font-size:13px;font-weight:600;cursor:pointer;display:block;width:100%;margin-top:12px;color:var(--txtl);font-family:var(--font)">Xem thêm</button>
    <?php endif; ?>
<?php endif; ?>
</div>
</div>

<!-- Deposit -->
<div class="pane" id="p-deposit">

<!-- Tạo đơn nạp tiền -->
<div class="card">
    <div class="card-h"><h3>Tạo đơn nạp tiền</h3></div>
    <form id="depositForm">
        <div style="margin-bottom:14px">
            <label class="cf-label">Số tiền muốn nạp <span style="color:var(--err)">*</span></label>
            <div style="position:relative">
                <input type="number" name="amount" class="cf-input" id="depAmount" placeholder="Nhập số tiền..." min="<?php echo linkngon_get_option('min_deposit_amount', 50000); ?>" required style="padding-right:30px">
                <span style="position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:13px;color:var(--txtm);font-weight:600">đ</span>
            </div>
            <div style="font-size:12px;color:var(--txtm);margin-top:4px">Số tiền tối thiểu: <?php echo linkngon_format_money(linkngon_get_option('min_deposit_amount', 50000)); ?></div>
        </div>

        <div style="margin-bottom:18px">
            <label class="cf-label">Chọn mức nạp</label>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px" id="depPresets">
                <?php
                $presets = json_decode(linkngon_get_option('deposit_presets','[]'), true);
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
                <button type="button" class="dep-preset" onclick="document.getElementById('depAmount').value=<?php echo $p['amount']; ?>;updateDepBonus()" style="position:relative">
                    <?php echo $label; ?>
                    <?php if($p['bonus'] > 0): ?>
                    <span style="position:absolute;top:-6px;right:-4px;background:var(--err);color:#fff;font-size:9px;font-weight:700;padding:1px 5px;border-radius:10px">+<?php echo $p['bonus']; ?>%</span>
                    <?php endif; ?>
                </button>
                <?php endforeach; ?>
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
            <span style="font-weight:700;font-size:15px"><?php echo esc_html(linkngon_get_option('deposit_bank','Vietcombank')); ?></span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px dashed var(--brdl)">
            <span style="color:var(--txtm);font-size:13px">Số tài khoản:</span>
            <div style="display:flex;align-items:center;gap:8px">
                <span style="font-weight:700;font-size:15px;font-family:var(--mono)" id="bankAccount"><?php echo esc_html(linkngon_get_option('deposit_account','0123456789')); ?></span>
                <button type="button" onclick="copyText('<?php echo esc_js(linkngon_get_option('deposit_account','0123456789')); ?>',this)" style="padding:4px 10px;background:var(--p);color:#fff;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer">Copy</button>
            </div>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px dashed var(--brdl)">
            <span style="color:var(--txtm);font-size:13px">Chủ tài khoản:</span>
            <span style="font-weight:700;font-size:15px"><?php echo esc_html(linkngon_get_option('deposit_holder','LINKNGON')); ?></span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px">
            <span style="color:var(--txtm);font-size:13px">Nội dung CK:</span>
            <div style="display:flex;align-items:center;gap:8px">
                <span style="font-weight:700;font-size:15px;font-family:var(--mono)" id="bankContent">NAP <?php echo $user_id; ?> <?php echo strtoupper($user->user_login); ?></span>
                <button type="button" onclick="copyText('NAP <?php echo $user_id; ?> <?php echo esc_js(strtoupper($user->user_login)); ?>',this)" style="padding:4px 10px;background:var(--p);color:#fff;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer">Copy</button>
            </div>
        </div>
    </div>
    <div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:var(--rads);padding:14px;margin-top:16px;font-size:13px;color:#92400E;line-height:1.6">
        <strong>Lưu ý:</strong> Ghi đúng nội dung chuyển khoản. Liên hệ Admin nếu sau 30 phút chưa nhận được tiền.
    </div>
</div>

<!-- Số dư + Lịch sử nạp -->
<div class="dep-grid">
    <div class="card"><div class="card-h"><h3>Số dư hiện tại</h3></div>
    <div style="text-align:center;padding:16px">
        <div style="font-family:var(--fonth);font-size:28px;color:var(--ok);word-break:break-word"><?php echo linkngon_format_money($cust_balance); ?></div>
        <div style="font-size:12px;color:var(--txtm);margin-top:6px">Đã nạp: <?php echo linkngon_format_money($total_deposited); ?> | Đã chi: <?php echo linkngon_format_money($total_spent); ?></div>
    </div></div>

    <div class="card"><div class="card-h"><h3>Lịch sử nạp tiền</h3><span style="font-size:11px;color:var(--txtm)">Tổng: <?php echo count($deposits); ?> đơn</span></div>
    <div style="overflow-x:auto">
    <table><thead><tr><th>#</th><th>Số tiền</th><th>Tổng</th><th>Ghi chú</th><th>Trạng thái</th><th>Ngày</th></tr></thead><tbody id="depositsListContainer">
    <?php if(empty($deposits)): ?>
    <tr><td colspan="6" style="text-align:center;color:var(--txtm)">Chưa có</td></tr>
    <?php else: foreach($deposits as $dep):
        $bc=array('pending'=>'b-warn','approved'=>'b-ok','rejected'=>'b-err');
        $bl=array('pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối');
        $bonus = isset($dep->bonus_amount) ? (float)$dep->bonus_amount : 0;
        $total = (float)$dep->amount + $bonus;
    ?>
    <tr>
        <td style="font-size:12px;color:var(--txtm)">#<?php echo $dep->id; ?></td>
        <td style="font-weight:600;color:<?php echo (float)$dep->amount >= 0 ? 'var(--ok)' : 'var(--err)'; ?>"><?php echo ((float)$dep->amount >= 0 ? '+' : '') . linkngon_format_money($dep->amount); ?></td>
        <td style="font-weight:600"><?php echo linkngon_format_money($total); ?></td>
        <td style="font-size:12px;color:var(--txtl)"><?php echo esc_html($dep->note ?? ''); ?></td>
        <td><span class="badge <?php echo $bc[$dep->status]??'b-mute'; ?>"><?php echo $bl[$dep->status] ?? $dep->status; ?></span></td>
        <td><small><?php echo date('d/m/Y',strtotime($dep->created_at)); ?></small></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody></table>
    <?php if(count($deposits) >= 10): ?>
    <button type="button" class="cust-load-more-btn" data-type="deposits" data-offset="10" data-target="depositsListContainer" style="padding:10px 24px;background:var(--bg);border:1.5px solid var(--brd);border-radius:var(--rads);font-size:13px;font-weight:600;cursor:pointer;display:block;width:100%;margin-top:12px;color:var(--txtl);font-family:var(--font)">Xem thêm</button>
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
    <table><thead><tr><th>Thời gian</th><th>Từ khóa / URL</th><th>Loại</th><th>Onsite</th><th>Chi phí</th><th>Trạng thái</th><th>IP</th><th>Thiết bị</th></tr></thead><tbody id="visitsListContainer">
    <?php if(empty($visit_history)): ?>
    <tr><td colspan="8" style="text-align:center;color:var(--txtm)">Chưa có</td></tr>
    <?php else: foreach($visit_history as $vh):
        $task_label = array('keyword_search'=>'Từ khóa','traffic_direct'=>'Direct','traffic_social'=>'Social');
        $step_map = array('1step'=>'1 bước','2step'=>'2 bước','nocode'=>'Nocode');
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
        <td>
            <?php if($vh->keyword): ?>
                <div style="font-weight:600;font-size:12px"><?php echo esc_html($vh->keyword); ?></div>
            <?php else: ?>
                <div style="font-weight:600;font-size:12px"><?php echo esc_html($vh->campaign_title); ?></div>
            <?php endif; ?>
            <?php if($domain): ?><div style="font-size:11px;color:var(--txtm)"><?php echo esc_html($domain); ?></div><?php endif; ?>
        </td>
        <td>
            <span class="badge b-info"><?php echo $task_label[$vh->task_type ?? ''] ?? 'Traffic'; ?></span>
            <div style="font-size:10px;color:var(--txtm);margin-top:2px"><?php echo $step_map[$vh->traffic_type] ?? $vh->traffic_type; ?> / <?php echo (int)$vh->onsite_time; ?>s</div>
        </td>
        <td style="font-size:12px"><?php echo (int)$vh->onsite_time; ?>s</td>
        <td style="font-weight:600;color:var(--err)">-<?php echo linkngon_format_money($cost); ?></td>
        <td>
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
    <?php if(count($visit_history) >= 10): ?>
    <button type="button" class="cust-load-more-btn" data-type="visits" data-offset="10" data-target="visitsListContainer" style="padding:10px 24px;background:var(--bg);border:1.5px solid var(--brd);border-radius:var(--rads);font-size:13px;font-weight:600;cursor:pointer;display:block;width:100%;margin-top:12px;color:var(--txtl);font-family:var(--font)">Xem thêm</button>
    <?php endif; ?>
    </div>
</div>

</div>
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
                    <input type="number" id="editCampDaily" class="cf-input" min="1" max="100">
                </div>
            </div>

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
                            <label class="ss-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Thay ảnh<input type="file" id="editSsDesktop" accept="image/*" style="display:none" onchange="previewEditSS(this,'editSsDesktopPreview')"></label>
                        </div>
                    </div>
                    <div>
                        <div class="ss-upload">
                            <div class="ss-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg> Mobile</div>
                            <div class="ss-preview" id="editSsMobilePreview"><span>Chưa có ảnh</span></div>
                            <label class="ss-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Thay ảnh<input type="file" id="editSsMobile" accept="image/*" style="display:none" onchange="previewEditSS(this,'editSsMobilePreview')"></label>
                        </div>
                    </div>
                </div>
            </div>

            <div id="editCampMsg" style="min-height:20px;margin-bottom:10px;font-size:13px;text-align:center"></div>
            <button type="submit" id="editCampSubmitBtn" style="width:100%;padding:12px;background:var(--p);color:#fff;border:none;border-radius:var(--rads);font-family:var(--font);font-size:14px;font-weight:600;cursor:pointer">Lưu thay đổi</button>
        </form>
    </div>
</div>

<script>
// Tab switching
document.querySelectorAll('.tb').forEach(function(b){b.addEventListener('click',function(){document.querySelectorAll('.tb').forEach(function(x){x.classList.remove('on')});document.querySelectorAll('.pane').forEach(function(x){x.classList.remove('on')});b.classList.add('on');document.getElementById('p-'+b.dataset.t).classList.add('on')})});
// Auto-open tab from URL param
(function(){var p=new URLSearchParams(window.location.search);var t=p.get('tab');if(t){var btn=document.querySelector('.tb[data-t="'+t+'"]');if(btn)btn.click()}})();

function reloadKeepTab(){
    var active=document.querySelector('.tb.on');
    var tab=active?active.dataset.t:'';
    var url=window.location.pathname;
    if(tab&&tab!=='overview') url+='?tab='+tab;
    window.location.href=url;
}

// === Create Campaign Form ===
var PRICES = {
    keyword_search: { '1step': <?php echo (int)linkngon_get_option('keyword_price_1step', 1200); ?>, '2step': <?php echo (int)linkngon_get_option('keyword_price_2step', 1500); ?>, 'nocode': <?php echo (int)linkngon_get_option('keyword_price_nocode', 1200); ?> },
    traffic_direct: { '1step': <?php echo (int)linkngon_get_option('direct_price_1step', 1200); ?>, '2step': <?php echo (int)linkngon_get_option('direct_price_2step', 1200); ?>, 'nocode': <?php echo (int)linkngon_get_option('direct_price_nocode', 1200); ?> }
};
var ONSITE_EXTRA = {70:0, 80:0, 90:100, 100:200, 120:250, 150:300};
var NONCE = '<?php echo wp_create_nonce("linkngon_nonce"); ?>';
var AJAX = '<?php echo admin_url("admin-ajax.php"); ?>';

function fmtMoney(n){return n.toLocaleString('vi-VN')+'đ'}

// Service type selection
document.querySelectorAll('.svc-card').forEach(function(c){
    c.addEventListener('click',function(){
        document.querySelectorAll('.svc-card').forEach(function(x){x.classList.remove('selected')});
        c.classList.add('selected');
        c.querySelector('input').checked=true;
        var t=c.dataset.type;
        document.getElementById('campTaskType').value=t;
        var kf=document.getElementById('kwFields');
        if(t==='keyword_search'){kf.style.display='grid';document.getElementById('campKeyword').required=true}
        else{kf.style.display='none';document.getElementById('campKeyword').required=false}
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

function previewNocodeImg(input){
    if(!input.files||!input.files[0])return;
    var reader=new FileReader();
    reader.onload=function(e){
        document.getElementById('ssNocodePreview').innerHTML='<img src="'+e.target.result+'" style="max-height:120px;max-width:100%;object-fit:contain;border-radius:6px">';
    };
    reader.readAsDataURL(input.files[0]);
}

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
    var daily=parseInt(document.querySelector('[name="daily_traffic"]').value)||10;
    var days=parseInt(document.getElementById('campDays').value)||15;

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

// === Deposit Form ===
var DEP_TIERS = <?php
    $dep_presets = json_decode(linkngon_get_option('deposit_presets','[]'), true);
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

document.getElementById('depositForm')?.addEventListener('submit',function(e){
    e.preventDefault();
    var fd=new FormData(this);
    fd.append('action','linkngon_customer_deposit');
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
function copyWidgetCode(){
    var code='<script src="<?php echo home_url("/widget.js?v=" . time()); ?>"><\/script>';
    navigator.clipboard.writeText(code).then(function(){
        document.getElementById('widgetCopyMsg').textContent='Đã copy!';
        setTimeout(function(){document.getElementById('widgetCopyMsg').textContent=''},2000);
    });
}

// Screenshot preview
function previewSS(input,previewId){
    var preview=document.getElementById(previewId);
    if(input.files&&input.files[0]){
        var reader=new FileReader();
        reader.onload=function(e){
            preview.innerHTML='<img src="'+e.target.result+'" alt="Preview">';
        };
        reader.readAsDataURL(input.files[0]);
    }
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
    var fd=new FormData(this);
    fd.append('action','linkngon_customer_create_campaign');
    fd.append('nonce',NONCE);
    var adminCust=document.getElementById('adminCustomerId');
    if(adminCust&&adminCust.value)fd.append('admin_customer_id',adminCust.value);
    var btn=document.getElementById('campSubmitBtn');
    var msg=document.getElementById('campMsg');
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
    fd.append('action', 'linkngon_customer_toggle_campaign');
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
    fd.append('action', 'linkngon_customer_get_campaign');
    fd.append('nonce', NONCE);
    fd.append('campaign_id', id);
    fetch(AJAX, {method:'POST', body:fd, credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if (!r.success) { toast(r.data || 'Lỗi', 'err'); return; }
        var c = r.data;
        var stepLabels = {'1step':'1 bước','2step':'2 bước','nocode':'Không mã'};
        var typeLabels = {'keyword_search':'Keyword','traffic_direct':'Direct','traffic_social':'Social'};
        var statusLabels = {'active':'Đang chạy','paused':'Tạm dừng','pending':'Chờ duyệt','completed':'Hoàn thành','rejected':'Từ chối'};
        var statusColors = {'active':'var(--ok)','paused':'var(--warn)','pending':'var(--info)','completed':'var(--txtm)','rejected':'var(--err)'};
        var pct = c.quantity > 0 ? Math.round(c.completed / c.quantity * 100) : 0;
        var html = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;font-size:13px">';
        html += '<div><span style="color:var(--txtm);font-size:11px">Từ khóa</span><div style="font-weight:600">' + (c.keyword || c.title || '—') + '</div></div>';
        html += '<div><span style="color:var(--txtm);font-size:11px">URL đích</span><div style="font-family:var(--mono);font-size:11px;word-break:break-all"><a href="' + c.target_url + '" target="_blank" style="color:var(--info)">' + c.target_url + '</a></div></div>';
        html += '<div><span style="color:var(--txtm);font-size:11px">Loại traffic</span><div style="font-weight:600">' + (typeLabels[c.task_type]||c.task_type) + '</div></div>';
        html += '<div><span style="color:var(--txtm);font-size:11px">Gói</span><div style="font-weight:600">' + (stepLabels[c.traffic_type]||c.traffic_type) + ' / ' + c.onsite_time + 's</div></div>';
        html += '<div><span style="color:var(--txtm);font-size:11px">Giá/view</span><div style="font-weight:600;color:var(--a)">' + fmtMoney(parseFloat(c.price_per_view)) + '</div></div>';
        html += '<div><span style="color:var(--txtm);font-size:11px">Traffic/ngày</span><div style="font-weight:600"><span style="color:var(--a)">' + c.today_views + '</span>/' + c.daily_traffic + '</div></div>';
        html += '<div><span style="color:var(--txtm);font-size:11px">Tiến độ</span><div style="font-weight:600">' + c.completed + '/' + c.quantity + ' (' + pct + '%)</div></div>';
        html += '<div><span style="color:var(--txtm);font-size:11px">Trạng thái</span><div style="font-weight:600;color:' + (statusColors[c.status]||'var(--txt)') + '">' + (statusLabels[c.status]||c.status) + '</div></div>';
        if (c.reject_reason) html += '<div style="grid-column:1/-1"><span style="color:var(--txtm);font-size:11px">Lý do từ chối</span><div style="color:var(--err)">' + c.reject_reason + '</div></div>';
        html += '<div><span style="color:var(--txtm);font-size:11px">Ngày tạo</span><div>' + c.created_at + '</div></div>';
        html += '</div>';
        var hasDeskSS = c.screenshot_desktop_url && c.screenshot_desktop_url.indexOf('http') === 0;
        var hasMobSS = c.screenshot_mobile_url && c.screenshot_mobile_url.indexOf('http') === 0;
        if (hasDeskSS || hasMobSS) {
            html += '<div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--brdl)">';
            html += '<div style="font-weight:600;font-size:12px;margin-bottom:8px">Ảnh minh họa</div>';
            html += '<div style="display:flex;gap:12px;flex-wrap:wrap">';
            if (hasDeskSS) html += '<img src="' + c.screenshot_desktop_url + '" style="max-width:280px;border-radius:8px;border:1px solid var(--brdl)" alt="Desktop">';
            if (hasMobSS) html += '<img src="' + c.screenshot_mobile_url + '" style="max-width:160px;border-radius:8px;border:1px solid var(--brdl)" alt="Mobile">';
            html += '</div></div>';
        }
        document.getElementById('campDetailContent').innerHTML = html;
        document.getElementById('campDetailModal').style.display = 'flex';
    });
}

var _editOriginal = {};

function editCampaign(id) {
    var fd = new FormData();
    fd.append('action', 'linkngon_customer_get_campaign');
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
        || document.getElementById('editSsDesktop').files.length > 0
        || document.getElementById('editSsMobile').files.length > 0;
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

function previewEditSS(input, previewId) {
    var file = input.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById(previewId).innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:auto;border-radius:var(--rads)">';
    };
    reader.readAsDataURL(file);
    editCheckReapproval();
}

document.getElementById('editCampForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var id = document.getElementById('editCampId').value;
    var btn = document.getElementById('editCampSubmitBtn');
    var msg = document.getElementById('editCampMsg');
    btn.disabled = true; btn.textContent = 'Đang lưu...';

    var fd = new FormData();
    fd.append('action', 'linkngon_customer_edit_campaign');
    fd.append('nonce', NONCE);
    fd.append('campaign_id', id);
    fd.append('keyword', document.getElementById('editCampKeyword').value);
    fd.append('target_url', document.getElementById('editCampUrl').value);
    fd.append('title', document.getElementById('editCampTitle').value);
    fd.append('daily_traffic', document.getElementById('editCampDaily').value);
    fd.append('traffic_type', document.getElementById('editCampTrafficType').value);
    fd.append('onsite_time', document.getElementById('editCampOnsite').value);

    var ssDesktop = document.getElementById('editSsDesktop').files[0];
    var ssMobile = document.getElementById('editSsMobile').files[0];
    if (ssDesktop) fd.append('screenshot_desktop', ssDesktop);
    if (ssMobile) fd.append('screenshot_mobile', ssMobile);

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
    fd.append('action', 'linkngon_customer_delete_campaign');
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
        var fd=new FormData();fd.append('action','linkngon_customer_load_more');fd.append('nonce',NONCE);fd.append('type',type);fd.append('offset',offset);
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
    var fd=new FormData();fd.append('action','linkngon_get_announcements');fd.append('nonce',NONCE);fd.append('target','customer');
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
