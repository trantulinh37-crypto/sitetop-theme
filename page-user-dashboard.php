<?php
/**
 * Template Name: User Dashboard
 * LinkNgon V2 - Publisher Dashboard (người rút gọn link kiếm tiền)
 * 
 * Tabs: Tổng quan | Links của tôi | Tạo link mới | Rút tiền | Referral | Tài khoản
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }

$user_id = get_current_user_id();
$user    = wp_get_current_user();

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';
$today  = date( 'Y-m-d', strtotime( linkngon_current_time() ) );

// Stats
$balance       = function_exists('linkngon_get_user_balance_amount') ? linkngon_get_user_balance_amount( $user_id ) : 0;
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

// My links (user_shortlinks)
$my_links = $wpdb->get_results( $wpdb->prepare(
    "SELECT us.*,
            us.code as shortcode,
            us.original_url as target_url,
            us.total_clicks as click_count,
            (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE shortlink_id=us.id AND step='verified' AND DATE(created_at)=%s) as today_clicks
     FROM {$prefix}user_shortlinks us
     WHERE us.user_id = %d
     ORDER BY us.created_at DESC
     LIMIT 10",
    $today, $user_id
) );

// 30-day chart
$chart = array();
for ( $i = 29; $i >= 0; $i-- ) {
    $d = date( 'Y-m-d', strtotime( "-{$i} days", strtotime( linkngon_current_time() ) ) );
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

$min_wd = floatval( linkngon_get_option( 'min_withdrawal', 50000 ) );
$nonce  = wp_create_nonce( 'linkngon_nonce' );
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
:root{--p:#0D4F4F;--pl:#1A7A7A;--pd:#083838;--a:#E8A838;--al:#F0C060;--bg:#F7F5F0;--card:#fff;--dark:#1A1A2E;--txt:#2C2C3A;--txtl:#6B7280;--txtm:#9CA3AF;--brd:#E5E2DB;--brdl:#F0EDE6;--ok:#059669;--err:#DC2626;--warn:#D97706;--info:#2563EB;--font:'Inter',sans-serif;--fonth:'Plus Jakarta Sans',sans-serif;--mono:'JetBrains Mono',monospace;--rad:12px;--rads:8px}
*{box-sizing:border-box;margin:0;padding:0}html,body{width:100%;overflow-x:hidden}body{font-family:var(--font);color:var(--txt);background:var(--bg);line-height:1.6}
.card{max-width:100%;overflow:hidden}

.topbar{background:#fff;border-bottom:1px solid var(--brdl);padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:54px;position:sticky;top:0;z-index:50}
.topbar .logo{font-family:var(--fonth);font-weight:800;font-size:20px;color:var(--p);text-decoration:none;display:inline-flex;align-items:center}
.topbar nav{display:flex;gap:14px;align-items:center;font-size:13px}
.topbar nav a{color:var(--txtl);text-decoration:none}
.avatar{width:30px;height:30px;border-radius:50%;background:var(--p);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700}

.hero{background:linear-gradient(135deg,#083838 0%,#0D4F4F 40%,#1A7A7A 100%);color:#fff;padding:32px 24px 24px;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;width:300px;height:300px;border-radius:50%;background:rgba(232,168,56,.06);top:-120px;right:-80px}
.hero::after{content:'';position:absolute;width:200px;height:200px;border-radius:50%;background:rgba(232,168,56,.04);bottom:-80px;left:-40px}
.hero *{position:relative;z-index:1}
.hero-inner{max-width:1100px;margin:0 auto}
.hero h1{font-family:var(--fonth);font-weight:800;font-size:22px;color:#fff;margin-bottom:2px}
.hero .sub{color:rgba(255,255,255,.45);font-size:12px}
.hero-stats{display:grid;grid-template-columns:repeat(6,1fr);gap:0;margin-top:20px;background:rgba(255,255,255,.06);border-radius:12px;overflow:hidden}
.hero-stat{padding:16px 12px;text-align:center;border-right:1px solid rgba(255,255,255,.06)}
.hero-stat:last-child{border-right:none}
.hero-stat .hs-label{font-size:9px;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.65);margin-bottom:6px}
.hero-stat .hs-value{font-family:var(--fonth);font-weight:800;font-size:18px;color:#F0C060;line-height:1.2}
.hero-stat .hs-value.green{color:#6EE7B7}
@media(max-width:600px){.hero-stats{grid-template-columns:repeat(3,1fr)}.hero-stat{padding:12px 8px}.hero-stat .hs-value{font-size:14px}}

.container{max-width:1100px;margin:0 auto;padding:24px 0 0 0;overflow-x:hidden}
.tabs{display:flex;flex-wrap:wrap;gap:4px;background:var(--card);padding:5px;border-radius:var(--rad);border:1px solid var(--brdl);margin-bottom:24px}
.tb{padding:9px 16px;border-radius:var(--rads);border:none;background:transparent;color:var(--txtl);font-family:var(--font);font-size:13px;font-weight:500;cursor:pointer;white-space:nowrap;transition:all .2s}
.tb.on{background:var(--p);color:#fff}
.pane{display:none;animation:fu .3s ease}.pane.on{display:block}
@keyframes fu{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

.card{background:var(--card);border-radius:var(--rad);border:1px solid var(--brdl);padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);margin-bottom:20px}
.card-h{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--brdl)}
.card-h h3{font-family:var(--fonth);font-size:17px;color:var(--pd)}
.sg{display:grid;gap:14px;margin-bottom:20px}
.sg4{grid-template-columns:repeat(2,1fr)}.sg6{grid-template-columns:repeat(auto-fit,minmax(130px,1fr))}
.sc{background:var(--card);border-radius:var(--rad);padding:12px;border:1px solid var(--brdl);display:flex;align-items:center;gap:8px;transition:all .2s;min-width:0;overflow:hidden}
.sc:hover{box-shadow:0 4px 12px rgba(0,0,0,.06);transform:translateY(-1px)}
.sc-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sc-icon svg{width:20px;height:20px}
.sc.s1 .sc-icon{background:#E0F2FE;color:#0D4F4F}.sc.s2 .sc-icon{background:#FEF3C7;color:#D97706}
.sc.s3 .sc-icon{background:#D1FAE5;color:#059669}.sc.s4 .sc-icon{background:#FEE2E2;color:#DC2626}
.sc.s5 .sc-icon{background:#DBEAFE;color:#2563EB}.sc.s6 .sc-icon{background:#FEF3C7;color:#92400E}
.sc-text{min-width:0;overflow:hidden}
.sc .sl{font-size:10px;color:var(--txtm);margin-bottom:2px;white-space:nowrap}
.sc .sv{font-family:var(--fonth);font-weight:800;font-size:15px;color:var(--pd);line-height:1.2;white-space:nowrap}
.sc .ss{font-size:10px;color:var(--txtl);margin-top:2px;white-space:nowrap}

table{width:100%;border-collapse:collapse;font-size:13px}
thead th{background:var(--bg);padding:9px 12px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--txtl);border-bottom:2px solid var(--brd)}
td{padding:9px 12px;border-bottom:1px solid var(--brdl);vertical-align:middle}
tr:hover{background:rgba(13,79,79,.01)}
.badge{display:inline-flex;padding:3px 8px;border-radius:20px;font-size:10px;font-weight:600}
.b-ok{background:#D1FAE5;color:#065F46}.b-warn{background:#FEF3C7;color:#92400E}.b-err{background:#FEE2E2;color:#991B1B}.b-info{background:#DBEAFE;color:#1E40AF}.b-mute{background:#F3F4F6;color:#4B5563}
.mono{font-family:var(--mono);font-size:12px}
.link-url{color:var(--info);word-break:break-all;font-family:var(--mono);font-size:11px}
.copy-btn{padding:4px 10px;background:var(--bg);border:1px solid var(--brd);border-radius:6px;font-size:11px;cursor:pointer;font-family:var(--font);transition:all .2s}
.copy-btn:hover{background:var(--p);color:#fff;border-color:var(--p)}
.amt-plus{color:var(--ok);font-weight:600}.amt-minus{color:var(--err);font-weight:600}

.ud-chart-legend{display:flex;gap:16px;font-size:12px;color:var(--txtm)}
.ud-chart-legend span{display:inline-flex;align-items:center;gap:5px}
.ud-chart-legend span::before{content:'';width:14px;height:3px;border-radius:2px;display:inline-block}
.ud-chart-legend .lg-views::before{background:#3b82f6}
.ud-chart-legend .lg-earned::before{background:#10b981}
.ud-chart-container{position:relative;height:280px}

/* Shorten form */
.sf{display:flex;gap:8px;margin-bottom:16px}
.sf input{flex:1;padding:13px 16px;border:1px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:14px}
.sf input:focus{outline:none;border-color:var(--p);box-shadow:0 0 0 3px rgba(13,79,79,.1)}
.sf button{padding:13px 28px;background:var(--p);color:#fff;border:none;border-radius:var(--rads);font-family:var(--font);font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap}
.sf button:hover{background:var(--pl)}
.sf-result{display:none;background:#F0F9F9;border:2px solid var(--p);border-radius:var(--rads);padding:14px 16px;margin-bottom:16px}
.sf-result-row{display:flex;align-items:center;gap:8px}
.sf-result-row input{flex:1;font-family:var(--mono);font-size:14px;color:var(--p);font-weight:600;border:none;background:transparent;outline:none}
.sf-result-row button{padding:8px 16px;background:var(--ok);color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-family:var(--font);font-size:12px}

/* Withdraw */
.wfg{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.wfg .full{grid-column:1/-1}
.wfl{display:block;font-size:11px;font-weight:600;margin-bottom:4px}
.wfi,.wfs{width:100%;padding:10px 12px;border:1px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px}
.wfi:focus,.wfs:focus{outline:none;border-color:var(--p);box-shadow:0 0 0 3px rgba(13,79,79,.1)}
.wbtn{display:block;width:100%;padding:13px;background:var(--p);color:#fff;border:none;border-radius:var(--rads);font-family:var(--font);font-size:14px;font-weight:600;cursor:pointer;margin-top:6px}
.wbtn:disabled{opacity:.5;cursor:not-allowed}
.wmsg{margin-top:10px;font-size:12px;text-align:center;min-height:18px}

/* Referral */
.ref-box{background:linear-gradient(135deg,#FFF9E6,#FFF5D6);border:2px solid var(--a);border-radius:var(--rad);padding:24px;text-align:center;margin-bottom:20px}
.ref-box h3{font-family:var(--fonth);font-size:22px;color:var(--pd);margin-bottom:6px}
.ref-pct{font-family:var(--fonth);font-size:48px;color:var(--a);margin-bottom:4px}
.ref-link{margin-top:16px;display:flex;gap:8px;max-width:500px;margin-left:auto;margin-right:auto}
.ref-link input{flex:1;padding:10px 14px;border:2px solid var(--a);border-radius:var(--rads);font-family:var(--mono);font-size:13px;color:var(--pd);background:#fff}
.ref-link button{padding:10px 18px;background:var(--a);color:var(--pd);border:none;border-radius:var(--rads);font-weight:700;cursor:pointer;font-family:var(--font)}

.toast-box{position:fixed;top:58px;right:20px;z-index:10000;display:flex;flex-direction:column;gap:6px}
.toast{padding:11px 18px;border-radius:var(--rads);font-size:13px;font-weight:500;color:#fff;box-shadow:0 4px 14px rgba(0,0,0,.12);animation:sr .3s ease;min-width:240px}
.t-ok{background:var(--ok)}.t-err{background:var(--err)}
@keyframes sr{from{opacity:0;transform:translateX(60px)}to{opacity:1;transform:translateX(0)}}

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
    .sg6{grid-template-columns:repeat(2,1fr)}
    .wfg{grid-template-columns:1fr}
    .brow{gap:16px}
    .sf{flex-direction:column}
    .wd-grid,.acc-grid{grid-template-columns:1fr!important}
    .tabs{gap:2px;padding:4px}
    .tb{padding:8px 10px;font-size:12px}
    .ud-chart-container{height:220px}
}
</style>
</head>
<body>
<div class="topbar">
    <a href="<?php echo home_url(); ?>" class="logo"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>LinkNgon</a>
    <nav>
        <a href="<?php echo home_url(); ?>">Trang chủ</a>
        <span class="avatar"><?php echo strtoupper(substr($user->display_name,0,1)); ?></span>
        <span style="font-weight:500"><?php echo esc_html($user->display_name); ?></span>
        <a href="<?php echo wp_logout_url(home_url()); ?>">Đăng xuất</a>
    </nav>
</div>

<div class="hero">
<div class="hero-inner">
    <h1>Xin chào, <?php echo esc_html($user->display_name); ?></h1>
    <p class="sub">Quản lý links rút gọn & thu nhập của bạn</p>
    <div class="sg sg6" style="margin-top:16px;margin-bottom:0">
        <div class="sc s1">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></div>
            <div class="sc-text"><div class="sl">Tổng links</div><div class="sv"><?php echo $total_links; ?></div></div>
        </div>
        <div class="sc s5">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
            <div class="sc-text"><div class="sl">Hoàn thành</div><div class="sv"><?php echo number_format($total_completed); ?></div><div class="ss">Hôm nay: <?php echo $today_completed; ?></div></div>
        </div>
        <div class="sc s2">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <div class="sc-text"><div class="sl">Tổng thu nhập</div><div class="sv"><?php echo linkngon_format_money($total_earned); ?></div><div class="ss">Hôm nay: <?php echo linkngon_format_money($today_earned); ?></div></div>
        </div>
        <div class="sc s3">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 10h20"/></svg></div>
            <div class="sc-text"><div class="sl">Số dư</div><div class="sv"><?php echo linkngon_format_money($balance); ?></div></div>
        </div>
        <div class="sc" style="border-color:#bbf7d0">
            <div class="sc-icon" style="background:#dcfce7;color:#16a34a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div class="sc-text"><div class="sl">Đã rút</div><div class="sv"><?php echo linkngon_format_money($total_withdrawn); ?></div></div>
        </div>
        <div class="sc s4">
            <div class="sc-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
            <div class="sc-text"><div class="sl">Đang chờ rút</div><div class="sv"><?php echo linkngon_format_money($pending_wd); ?></div></div>
        </div>
    </div>
</div></div>

<div class="container">
<div class="tabs">
    <button class="tb on" data-t="overview"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>Tổng quan</button>
    <button class="tb" data-t="links"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>Links của tôi</button>
    <button class="tb" data-t="withdraw"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Rút tiền</button>
    <?php if ( linkngon_get_option('referral_enabled', 0) ) : ?><button class="tb" data-t="referral"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Referral</button><?php endif; ?>
    <button class="tb" data-t="api"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>API</button>
    <button class="tb" data-t="account"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Tài khoản</button>
</div>

<!-- ═══ OVERVIEW ═══ -->
<div class="pane on" id="p-overview">

<!-- Announcements -->
<div class="ann-section" id="userAnnouncements" style="display:none"></div>

<div style="background:#fff;border-left:4px solid #3b82f6;border-radius:8px;padding:16px 18px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.08)">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px"><span style="background:#3b82f6;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700">i</span><strong style="font-size:15px;color:#1e293b">Quy định tham gia!</strong></div>
    <div style="font-size:13px;color:#334155;line-height:1.7">
        Khi sử dụng hệ thống rút gọn link kiếm tiền, người dùng bắt buộc tuân thủ các quy định sau:<br><br>
        1. Mỗi tài khoản chỉ được sử dụng bởi 01 người, nghiêm cấm tạo nhiều tài khoản hoặc dùng chung.<br>
        2. Người dùng chỉ được chia sẻ link rút gọn qua các kênh hợp pháp, không spam, không lừa đảo, nội dung vi phạm pháp luật, không ép click, không tự click.<br>
        3. Chỉ lượt truy cập hợp lệ mới được ghi nhận doanh thu; mỗi lượt chỉ được tính 01 lần (hiện tại cho phép <?php echo (int)linkngon_get_option('shortlink_ip_limit_24h',5); ?>IP/ngày).<br>
        4. Cấm sử dụng VPN, Proxy, giả lập, tool, auto hoặc bất kỳ hình thức gian lận nào.<br>
        5. Doanh thu hợp lệ có thể cần chờ kiểm duyệt trước khi thanh toán.<br>
        6. Người dùng chỉ được rút tiền khi đạt mức tối thiểu theo quy định của hệ thống.<br>
        7. Hành vi vi phạm có thể bị thu hồi doanh thu, khóa rút tiền hoặc khóa vĩnh viễn tài khoản mà không cần báo trước.<br><br>
        <span style="color:#b45309"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-2px;margin-right:4px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Tiếp tục sử dụng hệ thống đồng nghĩa với việc bạn đã đồng ý với toàn bộ quy định trên.</span>
    </div>
</div>

<div class="card">
<div class="card-h" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
    <h3 style="margin:0">Biểu đồ theo ngày</h3>
    <div class="ud-chart-legend">
        <span class="lg-views">Views</span>
        <span class="lg-earned">Kiếm được</span>
    </div>
</div>
<div class="ud-chart-container">
    <canvas id="udChart"></canvas>
</div>
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
            <input type="url" id="dashLongUrl" placeholder="https://example.com/your-long-url-here" style="width:100%;padding:10px 12px;border:1.5px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px;background:#FAFAF8">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--txtl);margin-bottom:4px">Link dự phòng</label>
            <input type="url" id="dashFallbackUrl" placeholder="https://backup-link.com" style="width:100%;padding:10px 12px;border:1.5px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px;background:#FAFAF8">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;color:var(--txtl);margin-bottom:4px">Bí danh</label>
            <input type="text" id="dashAlias" placeholder="my-link" style="width:100%;padding:10px 12px;border:1.5px solid var(--brd);border-radius:var(--rads);font-family:var(--font);font-size:13px;background:#FAFAF8">
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
<?php if(empty($my_links)): ?>
<p style="text-align:center;color:var(--txtm);padding:24px 0">Chưa có link nào.</p>
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
    <td style="padding:10px 8px;text-align:center;font-weight:600;color:<?php echo $earnings > 0 ? 'var(--ok)' : 'var(--txtm)'; ?>"><?php echo linkngon_format_money($earnings); ?></td>
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
<?php if(count($my_links) >= 10): ?>
<button type="button" class="load-more-btn" data-type="links" data-offset="10" data-target="linksListContainer" style="padding:10px 24px;background:var(--bg);border:1.5px solid var(--brd);border-radius:var(--rads);font-size:13px;font-weight:600;cursor:pointer;display:block;width:100%;margin-top:12px;color:var(--txtl);font-family:var(--font)">Xem thêm</button>
<?php endif; ?>
<?php endif; ?>
</div>
</div>

<!-- ═══ WITHDRAW ═══ -->
<div class="pane" id="p-withdraw">
<div class="wd-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
<div class="card"><div class="card-h"><h3>Yêu cầu rút tiền</h3></div>
<div style="background:linear-gradient(135deg,#E0F2F1,#F0F9F9);border-radius:var(--rads);padding:14px;margin-bottom:14px;font-size:13px">
    <strong>Số dư khả dụng:</strong> <span style="color:var(--ok);font-family:var(--fonth);font-size:20px"><?php echo linkngon_format_money($balance); ?></span>
    <br><small style="color:var(--txtm)">Rút tối thiểu: <?php echo linkngon_format_money($min_wd); ?></small>
</div>
<form id="wdForm">
<?php
    $saved_bank = get_user_meta($user_id, 'linkngon_bank_name', true);
    $saved_account = get_user_meta($user_id, 'linkngon_bank_account', true);
    $saved_holder = get_user_meta($user_id, 'linkngon_bank_holder', true);
?>
<div class="wfg">
    <div class="full"><label class="wfl">Số tiền rút (VNĐ)</label><input class="wfi" type="number" name="amount" min="<?php echo $min_wd; ?>" max="<?php echo $balance; ?>" required></div>
    <div><label class="wfl">Phương thức</label><select class="wfs" name="method"><option value="bank">Ngân hàng</option><option value="usdt">USDT-BEP20</option></select></div>
    <div><label class="wfl">Ngân hàng/Ví</label><input class="wfi" name="bank_name" required placeholder="Vietcombank" value="<?php echo esc_attr($saved_bank); ?>"></div>
    <div><label class="wfl">Số TK/Ví</label><input class="wfi" name="bank_account" required value="<?php echo esc_attr($saved_account); ?>"></div>
    <div><label class="wfl">Chủ TK</label><input class="wfi" name="bank_holder" required placeholder="NGUYEN VAN A" value="<?php echo esc_attr($saved_holder); ?>"></div>
</div>
<button type="submit" class="wbtn" <?php echo $balance<$min_wd?'disabled':''; ?>>Gửi yêu cầu rút tiền</button>
<div class="wmsg" id="wdMsg"></div>
</form></div>

<div class="card"><div class="card-h"><h3>Lịch sử rút tiền</h3></div>
<table><thead><tr><th>Ngày</th><th>Số tiền</th><th>Ngân hàng/Ví</th><th>TT</th></tr></thead><tbody id="wdListContainer">
<?php if(empty($withdrawals)): ?>
<tr><td colspan="4" style="text-align:center;color:var(--txtm)">Chưa có</td></tr>
<?php else: foreach($withdrawals as $w):
    $bc=array('pending'=>'b-warn','approved'=>'b-info','completed'=>'b-ok','rejected'=>'b-err','refunded'=>'b-err','cancelled'=>'b-mute');
?>
<tr>
    <td><small><?php echo date('d/m/Y',strtotime($w->created_at)); ?></small></td>
    <td style="font-weight:600"><?php echo linkngon_format_money($w->amount); ?></td>
    <td><small><?php echo esc_html($w->bank_name); ?></small></td>
    <td><span class="badge <?php echo $bc[$w->status]??'b-mute'; ?>"><?php echo $w->status; ?></span></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table>
<?php if(count($withdrawals) >= 10): ?>
<button type="button" class="load-more-btn" data-type="withdrawals" data-offset="10" data-target="wdListContainer" style="padding:10px 24px;background:var(--bg);border:1.5px solid var(--brd);border-radius:var(--rads);font-size:13px;font-weight:600;cursor:pointer;display:block;width:100%;margin-top:12px;color:var(--txtl);font-family:var(--font)">Xem thêm</button>
<?php endif; ?>
</div>
</div></div>

<?php if ( linkngon_get_option('referral_enabled', 0) ) : ?>
<!-- ═══ REFERRAL ═══ -->
<div class="pane" id="p-referral">
<div class="ref-box">
    <?php $ref_pct = linkngon_get_option('referral_commission_percent', 20); ?>
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
$api_token = get_user_meta($user_id, 'linkngon_api_token', true);
if(!$api_token){
    $api_token = wp_generate_password(24, false);
    update_user_meta($user_id, 'linkngon_api_token', $api_token);
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
            <div style="font-weight:700;font-size:16px;color:var(--ok)"><?php echo linkngon_format_money($total_earned); ?></div>
        </div>
        <div style="padding:10px 14px;background:var(--bg);border-radius:var(--rads)">
            <div style="font-size:11px;color:var(--txtm);margin-bottom:2px">Số dư</div>
            <div style="font-weight:700;font-size:16px;color:var(--ok)"><?php echo linkngon_format_money($balance); ?></div>
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

</div>

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
                    label: 'Kiếm được (đ)',
                    data: earned,
                    borderColor: '#10b981',
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
                    title: { display: true, text: 'Views', font: { size: 11 } },
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
document.querySelectorAll('.tb').forEach(function(b){b.addEventListener('click',function(){document.querySelectorAll('.tb').forEach(function(x){x.classList.remove('on')});document.querySelectorAll('.pane').forEach(function(x){x.classList.remove('on')});b.classList.add('on');document.getElementById('p-'+b.dataset.t).classList.add('on')})});

function ajax(action,data,cb){data.action=action;data.nonce='<?php echo $nonce;?>';var fd=new FormData();for(var k in data)fd.append(k,data[k]);fetch('<?php echo admin_url("admin-ajax.php");?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(cb).catch(function(e){toast('Lỗi: '+e.message,'err')})}

function dashShorten(){var btn=document.querySelector('[onclick="dashShorten()"]');if(btn.disabled)return;var u=document.getElementById('dashLongUrl').value.trim();if(!u){alert('Nhập URL gốc');return}if(!/^https?:\/\//i.test(u))u='https://'+u;var fb=document.getElementById('dashFallbackUrl').value.trim();var alias=document.getElementById('dashAlias').value.trim();btn.disabled=true;btn.style.opacity='.6';ajax('linkngon_shorten_url',{url:u,fallback_url:fb,alias:alias},function(r){btn.disabled=false;btn.style.opacity='1';if(r.success){document.getElementById('dashShortUrl').value=r.data.short_url;document.getElementById('dashResult').style.display='block';toast('Link đã rút gọn!','ok')}else{toast(r.data||'Lỗi','err')}})}

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

document.getElementById('wdForm')?.addEventListener('submit',function(e){e.preventDefault();var fd=new FormData(this);fd.append('action','linkngon_user_withdraw');fd.append('nonce','<?php echo $nonce;?>');var btn=this.querySelector('button[type=submit]'),msg=document.getElementById('wdMsg');btn.disabled=true;btn.textContent='Đang xử lý...';fetch('<?php echo admin_url("admin-ajax.php");?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){if(r.success){msg.innerHTML='<span style="color:var(--ok)">Đã gửi thành công!</span>';toast('Yêu cầu rút tiền đã gửi!','ok');setTimeout(function(){location.reload()},2000)}else{msg.innerHTML='<span style="color:var(--err)">'+(r.data||'Lỗi')+'</span>';btn.disabled=false;btn.textContent='Gửi yêu cầu rút tiền'}})});

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
    ajax('linkngon_edit_shortlink',{link_id:id,url:document.getElementById('editUrl').value,fallback_url:document.getElementById('editFallback').value,alias:document.getElementById('editAlias').value},function(r){
        if(r.success){document.getElementById('editLinkMsg').innerHTML='<span style="color:var(--ok)">Đã lưu!</span>';toast('Đã cập nhật!','ok');setTimeout(function(){location.reload()},1000)}
        else{document.getElementById('editLinkMsg').innerHTML='<span style="color:var(--err)">'+(r.data||'Lỗi')+'</span>'}
    });
}
function viewLinkVisits(id,short){
    document.getElementById('visitLinkInfo').innerHTML=short;
    document.getElementById('visitsContent').innerHTML='Đang tải...';
    document.getElementById('viewVisitsModal').style.display='flex';
    ajax('linkngon_get_link_visits',{link_id:id},function(r){
        if(r.success&&r.data.html){document.getElementById('visitsContent').innerHTML=r.data.html}
        else{document.getElementById('visitsContent').innerHTML='<span style="color:var(--txtm)">Chưa có lượt truy cập</span>'}
    });
}
function closeModal(id){document.getElementById(id).style.display='none'}

function resetApiToken(){
    if(!confirm('Tạo token mới? Token cũ sẽ không còn hoạt động.'))return;
    ajax('linkngon_reset_api_token',{},function(r){
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
    e.preventDefault();var fd=new FormData(this);fd.append('action','linkngon_update_profile');fd.append('nonce','<?php echo $nonce;?>');
    var btn=this.querySelector('button'),msg=document.getElementById('profileMsg');btn.disabled=true;btn.textContent='Đang lưu...';
    fetch('<?php echo admin_url("admin-ajax.php");?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
        if(r.success){msg.innerHTML='<span style="color:var(--ok)">Đã cập nhật!</span>';toast('Cập nhật thành công!','ok');setTimeout(function(){location.reload()},1500)}
        else{msg.innerHTML='<span style="color:var(--err)">'+(r.data||'Lỗi')+'</span>'}
        btn.disabled=false;btn.textContent='Lưu thay đổi';
    })
});

document.getElementById('changePwForm')?.addEventListener('submit',function(e){
    e.preventDefault();var fd=new FormData(this);fd.append('action','linkngon_change_password');fd.append('nonce','<?php echo $nonce;?>');
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
        var fd=new FormData();fd.append('action','linkngon_load_more');fd.append('nonce','<?php echo $nonce;?>');fd.append('type',type);fd.append('offset',offset);
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
    ajax('linkngon_get_announcements',{target:'user'},function(r){
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
