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
$balance       = linkngon_get_user_balance_amount( $user_id );
$total_earned  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$prefix}transactions WHERE user_id=%d AND type='shortlink_reward'", $user_id ) );
$today_earned  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$prefix}transactions WHERE user_id=%d AND type='shortlink_reward' AND DATE(created_at)=%s", $user_id, $today ) );
$total_links   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$prefix}keyword_campaigns WHERE customer_id=%d", $user_id ) );
$total_clicks  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$prefix}shortlink_visits v INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id=kc.id WHERE kc.customer_id=%d AND v.step='verified'", $user_id ) );
$today_clicks  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$prefix}shortlink_visits v INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id=kc.id WHERE kc.customer_id=%d AND v.step='verified' AND DATE(v.created_at)=%s", $user_id, $today ) );
$pending_wd    = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$prefix}withdrawals WHERE user_id=%d AND status IN ('pending','approved')", $user_id ) );

// My links
$my_links = $wpdb->get_results( $wpdb->prepare(
    "SELECT kc.*, 
            (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND step='verified') as click_count,
            (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND step='verified' AND DATE(created_at)=%s) as today_clicks
     FROM {$prefix}keyword_campaigns kc
     WHERE kc.customer_id = %d
     ORDER BY kc.created_at DESC
     LIMIT 50",
    $today, $user_id
) );

// 7-day chart
$chart = array();
for ( $i = 6; $i >= 0; $i-- ) {
    $d = date( 'Y-m-d', strtotime( "-{$i} days", strtotime( linkngon_current_time() ) ) );
    $clicks = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$prefix}shortlink_visits v INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id=kc.id WHERE kc.customer_id=%d AND v.step='verified' AND DATE(v.created_at)=%s", $user_id, $d
    ) );
    $earned = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$prefix}transactions WHERE user_id=%d AND type='shortlink_reward' AND DATE(created_at)=%s", $user_id, $d
    ) );
    $chart[] = array( 'date' => date('d/m', strtotime($d)), 'clicks' => $clicks, 'earned' => $earned );
}

// Withdrawals
$withdrawals = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$prefix}withdrawals WHERE user_id=%d ORDER BY created_at DESC LIMIT 20", $user_id
) );

// Transactions
$transactions = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$prefix}transactions WHERE user_id=%d ORDER BY created_at DESC LIMIT 30", $user_id
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
*{box-sizing:border-box;margin:0;padding:0}body{font-family:var(--font);color:var(--txt);background:var(--bg);line-height:1.6}

.topbar{background:#fff;border-bottom:1px solid var(--brdl);padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:54px;position:sticky;top:0;z-index:50}
.topbar .logo{font-family:var(--fonth);font-weight:800;font-size:20px;color:var(--p);text-decoration:none;display:inline-flex;align-items:center}
.topbar nav{display:flex;gap:14px;align-items:center;font-size:13px}
.topbar nav a{color:var(--txtl);text-decoration:none}
.avatar{width:30px;height:30px;border-radius:50%;background:var(--p);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:700}

.hero{background:linear-gradient(135deg,#083838,#0D4F4F,#1A7A7A);color:#fff;padding:36px 24px;position:relative;overflow:hidden}
.hero::after{content:'';position:absolute;right:-60px;top:-60px;width:200px;height:200px;border-radius:50%;background:rgba(232,168,56,.07)}
.hero-inner{max-width:1100px;margin:0 auto}
.hero h1{font-family:var(--fonth);font-size:24px;color:#fff}
.hero .sub{color:rgba(255,255,255,.55);font-size:13px;margin-top:2px}
.brow{display:flex;gap:32px;margin-top:20px;flex-wrap:wrap}
.bi{text-align:center}.bi .bl{font-size:10px;text-transform:uppercase;letter-spacing:.08em;opacity:.5}.bi .bv{font-family:var(--fonth);font-size:22px;color:var(--a)}

.container{max-width:1100px;margin:0 auto;padding:24px}
.tabs{display:flex;gap:4px;background:var(--card);padding:5px;border-radius:var(--rad);border:1px solid var(--brdl);margin-bottom:24px;overflow-x:auto}
.tb{padding:9px 16px;border-radius:var(--rads);border:none;background:transparent;color:var(--txtl);font-family:var(--font);font-size:13px;font-weight:500;cursor:pointer;white-space:nowrap;transition:all .2s}
.tb.on{background:var(--p);color:#fff}
.pane{display:none;animation:fu .3s ease}.pane.on{display:block}
@keyframes fu{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

.card{background:var(--card);border-radius:var(--rad);border:1px solid var(--brdl);padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);margin-bottom:20px}
.card-h{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--brdl)}
.card-h h3{font-family:var(--fonth);font-size:17px;color:var(--pd)}
.sg{display:grid;gap:14px;margin-bottom:20px}
.sg4{grid-template-columns:repeat(4,1fr)}.sg6{grid-template-columns:repeat(6,1fr)}
.sc{background:var(--card);border-radius:var(--rads);padding:18px;border:1px solid var(--brdl);position:relative;overflow:hidden}
.sc::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%}
.sc.s1::before{background:var(--p)}.sc.s2::before{background:var(--a)}.sc.s3::before{background:var(--ok)}.sc.s4::before{background:var(--err)}.sc.s5::before{background:var(--info)}.sc.s6::before{background:var(--warn)}
.sc .sl{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--txtm);margin-bottom:4px}
.sc .sv{font-family:var(--fonth);font-size:22px;color:var(--pd)}
.sc .ss{font-size:10px;color:var(--txtl);margin-top:3px}

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

.chart{display:flex;align-items:flex-end;gap:10px;height:130px;padding:12px 0}
.cbar{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px}
.cfill{width:100%;border-radius:5px 5px 0 0;background:linear-gradient(180deg,var(--p),var(--pl));min-height:4px;transition:height .5s ease}
.clbl{font-size:9px;color:var(--txtm)}.cval{font-size:9px;color:var(--p);font-weight:600}

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

@media(max-width:768px){
    .wfg{grid-template-columns:1fr}
    .brow{gap:16px}
    .sf{flex-direction:column}
    .wd-grid{grid-template-columns:1fr!important}
    .tabs{gap:2px;padding:4px}
    .tb{padding:8px 10px;font-size:12px}
}
@media(max-width:480px){.sg4,.sg6{grid-template-columns:1fr}}
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
    <div class="brow">
        <div class="bi"><div class="bl">Số dư khả dụng</div><div class="bv"><?php echo linkngon_format_money($balance); ?></div></div>
        <div class="bi"><div class="bl">Hôm nay</div><div class="bv"><?php echo linkngon_format_money($today_earned); ?></div></div>
        <div class="bi"><div class="bl">Clicks hôm nay</div><div class="bv"><?php echo number_format($today_clicks); ?></div></div>
        <div class="bi"><div class="bl">Tổng links</div><div class="bv"><?php echo $total_links; ?></div></div>
        <div class="bi"><div class="bl">Tổng clicks</div><div class="bv"><?php echo number_format($total_clicks); ?></div></div>
        <div class="bi"><div class="bl">Tổng thu nhập</div><div class="bv"><?php echo linkngon_format_money($total_earned); ?></div></div>
    </div>
</div></div>

<div class="container">
<div class="tabs">
    <button class="tb on" data-t="overview"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>Tổng quan</button>
    <button class="tb" data-t="links"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>Links của tôi</button>
    <button class="tb" data-t="create"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Tạo link mới</button>
    <button class="tb" data-t="withdraw"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Rút tiền</button>
    <button class="tb" data-t="referral"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Referral</button>
    <button class="tb" data-t="account"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Tài khoản</button>
</div>

<!-- ═══ OVERVIEW ═══ -->
<div class="pane on" id="p-overview">
<div class="sg sg6">
    <div class="sc s1"><div class="sl">Tổng links</div><div class="sv"><?php echo $total_links; ?></div></div>
    <div class="sc s5"><div class="sl">Tổng clicks</div><div class="sv"><?php echo number_format($total_clicks); ?></div><div class="ss">Hôm nay: <?php echo $today_clicks; ?></div></div>
    <div class="sc s2"><div class="sl">Tổng thu nhập</div><div class="sv"><?php echo linkngon_format_money($total_earned); ?></div><div class="ss">Hôm nay: <?php echo linkngon_format_money($today_earned); ?></div></div>
    <div class="sc s3"><div class="sl">Số dư</div><div class="sv"><?php echo linkngon_format_money($balance); ?></div></div>
    <div class="sc s4"><div class="sl">Đang chờ rút</div><div class="sv"><?php echo linkngon_format_money($pending_wd); ?></div></div>
    <div class="sc s6"><div class="sl">Referral</div><div class="sv">20%</div><div class="ss">trọn đời</div></div>
</div>

<div class="card"><div class="card-h"><h3>Clicks & Thu nhập 7 ngày</h3></div>
<?php $max_c = max(array_column($chart,'clicks')) ?: 1; ?>
<div class="chart">
<?php foreach($chart as $day): $h = max(4, ($day['clicks']/$max_c)*110); ?>
<div class="cbar"><div class="cval"><?php echo $day['clicks']; ?></div><div class="cfill" style="height:<?php echo $h; ?>px"></div><div class="clbl"><?php echo $day['date']; ?></div></div>
<?php endforeach; ?>
</div></div>

<div class="card"><div class="card-h"><h3>Giao dịch gần đây</h3></div>
<table><thead><tr><th>Thời gian</th><th>Mô tả</th><th>Số tiền</th><th>Số dư</th></tr></thead><tbody>
<?php $r5 = array_slice($transactions,0,5); if(empty($r5)): ?>
<tr><td colspan="4" style="text-align:center;color:var(--txtm)">Chưa có giao dịch</td></tr>
<?php else: foreach($r5 as $tx): ?>
<tr>
    <td><small><?php echo $tx->created_at; ?></small></td>
    <td><?php echo esc_html($tx->description); ?></td>
    <td class="<?php echo $tx->amount>=0?'amt-plus':'amt-minus'; ?>"><?php echo ($tx->amount>=0?'+':'').linkngon_format_money($tx->amount); ?></td>
    <td><?php echo linkngon_format_money($tx->balance_after); ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div>
</div>

<!-- ═══ LINKS ═══ -->
<div class="pane" id="p-links">
<div class="card"><div class="card-h"><h3>Links đã rút gọn (<?php echo count($my_links); ?>)</h3></div>
<div style="overflow-x:auto">
<table><thead><tr><th>Shortlink</th><th>Link gốc</th><th>Clicks</th><th>Hôm nay</th><th>Status</th><th>Ngày tạo</th><th></th></tr></thead><tbody>
<?php if(empty($my_links)): ?>
<tr><td colspan="7" style="text-align:center;color:var(--txtm)">Chưa có link nào. Tạo link mới để bắt đầu kiếm tiền!</td></tr>
<?php else: foreach($my_links as $lk):
    $short = $home.'/s/'.$lk->shortcode;
    $bcls = $lk->status==='active'?'b-ok':($lk->status==='paused'?'b-warn':'b-mute');
?>
<tr>
    <td><span class="link-url"><?php echo esc_html($short); ?></span></td>
    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><small title="<?php echo esc_attr($lk->target_url); ?>"><?php echo esc_html($lk->target_url); ?></small></td>
    <td style="font-weight:600"><?php echo number_format($lk->click_count); ?></td>
    <td><?php echo $lk->today_clicks; ?></td>
    <td><span class="badge <?php echo $bcls; ?>"><?php echo $lk->status; ?></span></td>
    <td><small><?php echo date('d/m/Y', strtotime($lk->created_at)); ?></small></td>
    <td><button class="copy-btn" onclick="copyText('<?php echo esc_js($short); ?>',this)">Copy</button></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div></div>

<!-- ═══ CREATE ═══ -->
<div class="pane" id="p-create">
<div class="card">
    <div class="card-h"><h3>Rút gọn link mới</h3></div>
    <div class="sf" id="dashShortenForm">
        <input type="url" id="dashLongUrl" placeholder="Dán link cần rút gọn tại đây...">
        <button onclick="dashShorten()">Rút gọn</button>
    </div>
    <div class="sf-result" id="dashResult">
        <div class="sf-result-row">
            <input type="text" id="dashShortUrl" readonly>
            <button onclick="copyText(document.getElementById('dashShortUrl').value,this)">Copy</button>
        </div>
        <p style="font-size:12px;color:var(--ok);margin-top:8px">Link đã được rút gọn! Chia sẻ để kiếm tiền.</p>
    </div>
    <div style="background:var(--bg);border-radius:var(--rads);padding:16px;font-size:13px;color:var(--txtl)">
        <strong>Mẹo kiếm tiền hiệu quả:</strong>
        <ul style="margin-top:8px;padding-left:20px;line-height:2">
            <li>Rút gọn link tải phần mềm, tài liệu, media phổ biến</li>
            <li>Chia sẻ lên blog, YouTube description, Facebook groups</li>
            <li>Traffic từ Google/CocCoc có tỷ lệ CPM cao hơn</li>
            <li>Giới thiệu bạn bè để nhận thêm 20% thu nhập referral</li>
        </ul>
    </div>
</div></div>

<!-- ═══ WITHDRAW ═══ -->
<div class="pane" id="p-withdraw">
<div class="wd-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
<div class="card"><div class="card-h"><h3>Yêu cầu rút tiền</h3></div>
<div style="background:linear-gradient(135deg,#E0F2F1,#F0F9F9);border-radius:var(--rads);padding:14px;margin-bottom:14px;font-size:13px">
    <strong>Số dư khả dụng:</strong> <span style="color:var(--ok);font-family:var(--fonth);font-size:20px"><?php echo linkngon_format_money($balance); ?></span>
    <br><small style="color:var(--txtm)">Rút tối thiểu: <?php echo linkngon_format_money($min_wd); ?></small>
</div>
<form id="wdForm">
<div class="wfg">
    <div class="full"><label class="wfl">Số tiền rút (VNĐ)</label><input class="wfi" type="number" name="amount" min="<?php echo $min_wd; ?>" max="<?php echo $balance; ?>" required></div>
    <div><label class="wfl">Phương thức</label><select class="wfs" name="method"><option value="bank">Ngân hàng</option><option value="momo">MoMo</option></select></div>
    <div><label class="wfl">Tên NH</label><input class="wfi" name="bank_name" required placeholder="Vietcombank"></div>
    <div><label class="wfl">Số TK</label><input class="wfi" name="bank_account" required></div>
    <div><label class="wfl">Chủ TK</label><input class="wfi" name="bank_holder" required placeholder="NGUYEN VAN A"></div>
</div>
<button type="submit" class="wbtn" <?php echo $balance<$min_wd?'disabled':''; ?>>Gửi yêu cầu rút tiền</button>
<div class="wmsg" id="wdMsg"></div>
</form></div>

<div class="card"><div class="card-h"><h3>Lịch sử rút tiền</h3></div>
<table><thead><tr><th>Ngày</th><th>Số tiền</th><th>Ngân hàng</th><th>TT</th></tr></thead><tbody>
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
</tbody></table></div>
</div></div>

<!-- ═══ REFERRAL ═══ -->
<div class="pane" id="p-referral">
<div class="ref-box">
    <div class="ref-pct">20%</div>
    <h3>Giới thiệu bạn bè — Kiếm thêm trọn đời!</h3>
    <p style="color:var(--txtl);font-size:14px;margin:8px 0 0">Chia sẻ link giới thiệu bên dưới. Mỗi khi bạn bè đăng ký và kiếm tiền, bạn nhận 20% thu nhập của họ — vĩnh viễn.</p>
    <div class="ref-link">
        <input type="text" id="refUrl" value="<?php echo home_url('?ref=' . $user->user_login); ?>" readonly>
        <button onclick="copyText(document.getElementById('refUrl').value,this)">Copy</button>
    </div>
</div>
<div class="card"><div class="card-h"><h3>Thống kê Referral</h3></div>
<p style="color:var(--txtm);font-size:13px">Tính năng thống kê referral chi tiết sẽ được cập nhật.</p>
</div></div>

<!-- ═══ ACCOUNT ═══ -->
<div class="pane" id="p-account">
<div class="card" style="max-width:500px">
    <div class="card-h"><h3>Thông tin tài khoản</h3></div>
    <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--p),var(--pl));color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;font-family:var(--fonth);margin-bottom:14px"><?php echo strtoupper(substr($user->display_name,0,1)); ?></div>
    <div style="display:grid;grid-template-columns:110px 1fr;gap:6px 14px;font-size:13px">
        <span style="color:var(--txtm)">Tên</span><span style="font-weight:600"><?php echo esc_html($user->display_name); ?></span>
        <span style="color:var(--txtm)">Email</span><span style="font-weight:600"><?php echo esc_html($user->user_email); ?></span>
        <span style="color:var(--txtm)">Username</span><span style="font-weight:600"><?php echo esc_html($user->user_login); ?></span>
        <span style="color:var(--txtm)">Ngày ĐK</span><span style="font-weight:600"><?php echo date('d/m/Y',strtotime($user->user_registered)); ?></span>
        <span style="color:var(--txtm)">Tổng links</span><span style="font-weight:600"><?php echo $total_links; ?></span>
        <span style="color:var(--txtm)">Tổng clicks</span><span style="font-weight:600"><?php echo number_format($total_clicks); ?></span>
        <span style="color:var(--txtm)">Tổng thu nhập</span><span style="font-weight:600"><?php echo linkngon_format_money($total_earned); ?></span>
    </div>
</div></div>

</div>

<div class="toast-box" id="toastBox"></div>

<script>
document.querySelectorAll('.tb').forEach(function(b){b.addEventListener('click',function(){document.querySelectorAll('.tb').forEach(function(x){x.classList.remove('on')});document.querySelectorAll('.pane').forEach(function(x){x.classList.remove('on')});b.classList.add('on');document.getElementById('p-'+b.dataset.t).classList.add('on')})});

function ajax(action,data,cb){data.action=action;data.nonce='<?php echo $nonce;?>';var fd=new FormData();for(var k in data)fd.append(k,data[k]);fetch('<?php echo admin_url("admin-ajax.php");?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(cb).catch(function(e){toast('Lỗi: '+e.message,'err')})}

function dashShorten(){var u=document.getElementById('dashLongUrl').value.trim();if(!u){alert('Nhập link');return}if(!/^https?:\/\//i.test(u))u='https://'+u;ajax('linkngon_shorten_url',{url:u},function(r){if(r.success){document.getElementById('dashShortUrl').value=r.data.short_url;document.getElementById('dashResult').style.display='block';toast('Link đã rút gọn!','ok')}else{toast(r.data||'Lỗi','err')}})}

function copyText(txt,btn){navigator.clipboard.writeText(txt).then(function(){var o=btn.textContent;btn.textContent='Copied!';setTimeout(function(){btn.textContent=o},1500);toast('Đã copy!','ok')})}

document.getElementById('wdForm')?.addEventListener('submit',function(e){e.preventDefault();var fd=new FormData(this);fd.append('action','linkngon_user_withdraw');fd.append('nonce','<?php echo $nonce;?>');var btn=this.querySelector('button[type=submit]'),msg=document.getElementById('wdMsg');btn.disabled=true;btn.textContent='Đang xử lý...';fetch('<?php echo admin_url("admin-ajax.php");?>',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){if(r.success){msg.innerHTML='<span style="color:var(--ok)">Đã gửi thành công!</span>';toast('Yêu cầu rút tiền đã gửi!','ok');setTimeout(function(){location.reload()},2000)}else{msg.innerHTML='<span style="color:var(--err)">'+(r.data||'Lỗi')+'</span>';btn.disabled=false;btn.textContent='Gửi yêu cầu rút tiền'}})});

function toast(m,t){var c=document.getElementById('toastBox'),d=document.createElement('div');d.className='toast t-'+(t||'ok');d.textContent=m;c.appendChild(d);setTimeout(function(){d.remove()},3500)}
</script>
<?php wp_footer(); ?>
</body>
</html>
