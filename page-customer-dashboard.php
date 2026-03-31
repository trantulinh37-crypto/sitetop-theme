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

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';
$today  = date( 'Y-m-d', strtotime( linkngon_current_time() ) );

// Stats
$cust_balance    = linkngon_get_customer_balance_amount( $user_id );
$total_deposited = (float) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(SUM(amount),0) FROM {$prefix}customer_transactions WHERE customer_id=%d AND type='deposit' AND amount>0", $user_id ) );
$total_spent = (float) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(ABS(SUM(amount)),0) FROM {$prefix}customer_transactions WHERE customer_id=%d AND type='campaign_view' AND amount<0", $user_id ) );

// Campaigns
$my_campaigns = $wpdb->get_results( $wpdb->prepare(
    "SELECT kc.*,
            (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND step='verified') as views,
            (SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE campaign_id=kc.id AND step='verified' AND DATE(created_at)=%s) as today_views
     FROM {$prefix}keyword_campaigns kc
     WHERE kc.customer_id = %d
     ORDER BY kc.created_at DESC LIMIT 50", $today, $user_id ) );
$active_camps  = array_filter( $my_campaigns, function($c){ return $c->status==='active'; } );
$total_views   = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$prefix}shortlink_visits v INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id=kc.id WHERE kc.customer_id=%d AND v.step='verified'", $user_id ) );
$today_views   = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$prefix}shortlink_visits v INNER JOIN {$prefix}keyword_campaigns kc ON v.campaign_id=kc.id WHERE kc.customer_id=%d AND v.step='verified' AND DATE(v.created_at)=%s", $user_id, $today ) );

$deposits = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$prefix}customer_deposits WHERE customer_id=%d ORDER BY created_at DESC LIMIT 20", $user_id ) );
$cust_txns = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$prefix}customer_transactions WHERE customer_id=%d ORDER BY created_at DESC LIMIT 30", $user_id ) );

// 7-day chart
$chart=array();
for($i=6;$i>=0;$i--){
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
*{box-sizing:border-box;margin:0;padding:0}body{font-family:var(--font);color:var(--txt);background:var(--bg);line-height:1.6}

.topbar{background:#fff;border-bottom:1px solid var(--brdl);padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:54px;position:sticky;top:0;z-index:50}
.topbar .logo{font-family:var(--fonth);font-weight:800;font-size:20px;color:var(--p);text-decoration:none;display:inline-flex;align-items:center}
.topbar nav{display:flex;gap:14px;align-items:center;font-size:13px}
.topbar nav a{color:var(--txtl);text-decoration:none}
.role-tag{padding:4px 12px;background:#FEF3C7;color:#92400E;border-radius:20px;font-size:11px;font-weight:600}

.hero{background:linear-gradient(135deg,var(--dark),#2C2C3A,#3D3D50);color:#fff;padding:36px 24px;position:relative;overflow:hidden}
.hero::after{content:'';position:absolute;right:-80px;bottom:-80px;width:220px;height:220px;border-radius:50%;background:rgba(232,168,56,.05)}
.hero-inner{max-width:1100px;margin:0 auto}
.hero h1{font-family:var(--fonth);font-size:24px;color:#fff}
.hero .sub{color:rgba(255,255,255,.5);font-size:13px;margin-top:2px}
.brow{display:flex;gap:32px;margin-top:20px;flex-wrap:wrap}
.bi{text-align:center}.bi .bl{font-size:10px;text-transform:uppercase;letter-spacing:.08em;opacity:.5}.bi .bv{font-family:var(--fonth);font-size:22px;color:#34D399}
.bi .bv.gold{color:var(--a)}

.container{max-width:1100px;margin:0 auto;padding:24px}
.tabs{display:flex;gap:4px;background:var(--card);padding:5px;border-radius:var(--rad);border:1px solid var(--brdl);margin-bottom:24px;overflow-x:auto}
.tb{padding:9px 16px;border-radius:var(--rads);border:none;background:transparent;color:var(--txtl);font-family:var(--font);font-size:13px;font-weight:500;cursor:pointer;white-space:nowrap;transition:all .2s}
.tb.on{background:var(--dark);color:#fff}
.pane{display:none;animation:fu .3s ease}.pane.on{display:block}
@keyframes fu{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

.card{background:var(--card);border-radius:var(--rad);border:1px solid var(--brdl);padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);margin-bottom:20px}
.card-h{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--brdl)}
.card-h h3{font-family:var(--fonth);font-size:17px;color:var(--pd)}
.sg{display:grid;gap:14px;margin-bottom:20px}
.sg4{grid-template-columns:repeat(4,1fr)}.sg5{grid-template-columns:repeat(5,1fr)}
.sc{background:var(--card);border-radius:var(--rads);padding:18px;border:1px solid var(--brdl);position:relative;overflow:hidden}
.sc::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%}
.sc.s1::before{background:var(--info)}.sc.s2::before{background:var(--a)}.sc.s3::before{background:var(--ok)}.sc.s4::before{background:var(--p)}
.sc .sl{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--txtm);margin-bottom:4px}
.sc .sv{font-family:var(--fonth);font-size:22px;color:var(--pd)}.sc .ss{font-size:10px;color:var(--txtl);margin-top:3px}

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

/* Deposit */
.dep-box{background:linear-gradient(135deg,#DBEAFE,#EFF6FF);border-radius:var(--rad);padding:22px;margin-bottom:20px}
.dep-box h4{font-family:var(--fonth);font-size:17px;color:var(--pd);margin-bottom:10px}
.dep-info{display:grid;grid-template-columns:120px 1fr;gap:6px 14px;font-size:13px}
.dep-info dt{color:var(--txtm)}.dep-info dd{color:var(--txt);font-weight:600;font-family:var(--mono)}

@media(max-width:768px){.sg4,.sg5{grid-template-columns:repeat(2,1fr)}.ccgrid{grid-template-columns:1fr}.brow{gap:16px}}
@media(max-width:480px){.sg4,.sg5{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="topbar">
    <a href="<?php echo home_url(); ?>" class="logo"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>LinkNgon</a>
    <nav><span class="role-tag">ADVERTISER</span><a href="<?php echo home_url(); ?>">Trang chủ</a><a href="<?php echo wp_logout_url(home_url()); ?>">Đăng xuất</a></nav>
</div>

<div class="hero">
<div class="hero-inner">
    <h1><?php echo esc_html($user->display_name); ?></h1>
    <p class="sub">Quản lý campaigns & mua traffic cho website của bạn</p>
    <div class="brow">
        <div class="bi"><div class="bl">Số dư</div><div class="bv"><?php echo linkngon_format_money($cust_balance); ?></div></div>
        <div class="bi"><div class="bl">Campaigns</div><div class="bv gold"><?php echo count($active_camps); ?>/<?php echo count($my_campaigns); ?></div></div>
        <div class="bi"><div class="bl">Views hôm nay</div><div class="bv gold"><?php echo number_format($today_views); ?></div></div>
        <div class="bi"><div class="bl">Tổng views</div><div class="bv gold"><?php echo number_format($total_views); ?></div></div>
        <div class="bi"><div class="bl">Đã chi</div><div class="bv gold"><?php echo linkngon_format_money($total_spent); ?></div></div>
    </div>
</div></div>

<div class="container">
<div class="tabs">
    <button class="tb on" data-t="overview"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>Tổng quan</button>
    <button class="tb" data-t="campaigns"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>Campaigns</button>
    <button class="tb" data-t="deposit"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>Nạp tiền</button>
    <button class="tb" data-t="history"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Lịch sử GD</button>
</div>

<!-- Overview -->
<div class="pane on" id="p-overview">
<div class="sg sg4">
    <div class="sc s1"><div class="sl">Tổng campaigns</div><div class="sv"><?php echo count($my_campaigns); ?></div><div class="ss">Đang chạy: <?php echo count($active_camps); ?></div></div>
    <div class="sc s2"><div class="sl">Tổng views</div><div class="sv"><?php echo number_format($total_views); ?></div><div class="ss">Hôm nay: <?php echo number_format($today_views); ?></div></div>
    <div class="sc s3"><div class="sl">Đã nạp</div><div class="sv"><?php echo linkngon_format_money($total_deposited); ?></div></div>
    <div class="sc s4"><div class="sl">Số dư</div><div class="sv"><?php echo linkngon_format_money($cust_balance); ?></div><div class="ss">Đã chi: <?php echo linkngon_format_money($total_spent); ?></div></div>
</div>
<div class="card"><div class="card-h"><h3>Views 7 ngày</h3></div>
<?php $mx=max(array_column($chart,'views'))?:1; ?>
<div class="chart"><?php foreach($chart as $d):$h=max(4,($d['views']/$mx)*110); ?>
<div class="cbar"><div class="cval"><?php echo $d['views']; ?></div><div class="cfill" style="height:<?php echo $h; ?>px"></div><div class="clbl"><?php echo $d['date']; ?></div></div>
<?php endforeach; ?></div></div>
</div>

<!-- Campaigns -->
<div class="pane" id="p-campaigns">
<?php if(empty($my_campaigns)): ?>
<div class="card" style="text-align:center;padding:48px;color:var(--txtm)"><div style="margin-bottom:12px"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div><p>Chưa có campaign. Liên hệ admin để tạo!</p></div>
<?php else: ?>
<div class="ccgrid">
<?php foreach($my_campaigns as $c):
    $pct=$c->total_limit>0?round(($c->total_completed/$c->total_limit)*100):0;
    $link=$home.'/s/'.$c->shortcode;
    $bc=$c->status==='active'?'b-ok':($c->status==='paused'?'b-warn':'b-mute');
    $tc=$c->traffic_type==='keyword'?'b-info':'b-warn';
?>
<div class="ccamp">
    <div class="ccamp-top">
        <span class="badge <?php echo $tc; ?>"><?php echo $c->traffic_type==='keyword'?'Keyword':'Direct'; ?></span>
        <span class="badge <?php echo $bc; ?>"><?php echo $c->status; ?></span>
    </div>
    <div class="ccamp-name"><?php echo esc_html($c->name); ?></div>
    <?php if($c->keyword): ?><div class="ccamp-kw"><?php echo esc_html($c->keyword); ?></div><?php endif; ?>
    <?php if($c->total_limit>0): ?><div class="cprog"><div class="cprog-fill" style="width:<?php echo min(100,$pct); ?>%"></div></div><?php endif; ?>
    <div class="ccamp-meta">
        <span><?php echo $c->total_completed; ?><?php echo $c->total_limit>0?"/{$c->total_limit} ({$pct}%)":" views"; ?></span>
        <span><?php echo linkngon_format_money($c->reward_amount); ?>/view</span>
        <span>Hôm nay: <?php echo $c->today_views; ?></span>
    </div>
    <a class="ccamp-link" href="<?php echo esc_url($link); ?>" target="_blank"><?php echo esc_html($link); ?></a>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<!-- Deposit -->
<div class="pane" id="p-deposit">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
<div>
    <div class="dep-box">
        <h4>Thông tin nạp tiền</h4>
        <p style="font-size:13px;color:var(--txtl);margin-bottom:14px">Chuyển khoản theo thông tin bên dưới, ghi đúng nội dung CK.</p>
        <dl class="dep-info">
            <dt>Ngân hàng</dt><dd><?php echo esc_html(linkngon_get_option('deposit_bank','Vietcombank')); ?></dd>
            <dt>Số TK</dt><dd><?php echo esc_html(linkngon_get_option('deposit_account','0123456789')); ?></dd>
            <dt>Chủ TK</dt><dd><?php echo esc_html(linkngon_get_option('deposit_holder','LINKNGON')); ?></dd>
            <dt>Nội dung CK</dt><dd>NAP <?php echo $user_id; ?> <?php echo strtoupper($user->user_login); ?></dd>
        </dl>
    </div>
    <div class="card"><div class="card-h"><h3>Số dư hiện tại</h3></div>
    <div style="text-align:center;padding:16px">
        <div style="font-family:var(--fonth);font-size:36px;color:var(--ok)"><?php echo linkngon_format_money($cust_balance); ?></div>
        <div style="font-size:12px;color:var(--txtm);margin-top:6px">Đã nạp: <?php echo linkngon_format_money($total_deposited); ?> | Đã chi: <?php echo linkngon_format_money($total_spent); ?></div>
    </div></div>
</div>
<div class="card"><div class="card-h"><h3>Lịch sử nạp tiền</h3></div>
<table><thead><tr><th>Ngày</th><th>Số tiền</th><th>PT</th><th>TT</th></tr></thead><tbody>
<?php if(empty($deposits)): ?>
<tr><td colspan="4" style="text-align:center;color:var(--txtm)">Chưa có</td></tr>
<?php else: foreach($deposits as $dep):
    $bc=array('pending'=>'b-warn','completed'=>'b-ok','rejected'=>'b-err');
?>
<tr>
    <td><small><?php echo date('d/m/Y',strtotime($dep->created_at)); ?></small></td>
    <td style="font-weight:600;color:var(--ok)">+<?php echo linkngon_format_money($dep->amount); ?></td>
    <td><?php echo esc_html($dep->method); ?></td>
    <td><span class="badge <?php echo $bc[$dep->status]??'b-mute'; ?>"><?php echo $dep->status; ?></span></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div>
</div></div>

<!-- History -->
<div class="pane" id="p-history">
<div class="card"><div class="card-h"><h3>Lịch sử giao dịch</h3></div>
<table><thead><tr><th>Thời gian</th><th>Loại</th><th>Mô tả</th><th>Số tiền</th><th>Số dư</th></tr></thead><tbody>
<?php if(empty($cust_txns)): ?>
<tr><td colspan="5" style="text-align:center;color:var(--txtm)">Chưa có</td></tr>
<?php else: foreach($cust_txns as $tx):
    $tl=array('deposit'=>'Nạp tiền','campaign_view'=>'Chi phí view','refund'=>'Hoàn tiền');
    $tb=array('deposit'=>'b-ok','campaign_view'=>'b-err','refund'=>'b-info');
?>
<tr>
    <td><small><?php echo $tx->created_at; ?></small></td>
    <td><span class="badge <?php echo $tb[$tx->type]??'b-mute'; ?>"><?php echo $tl[$tx->type]??$tx->type; ?></span></td>
    <td><?php echo esc_html($tx->description); ?></td>
    <td style="font-weight:600;color:<?php echo $tx->amount>=0?'var(--ok)':'var(--err)'; ?>"><?php echo ($tx->amount>=0?'+':'').linkngon_format_money($tx->amount); ?></td>
    <td><?php echo linkngon_format_money($tx->balance_after); ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div>
</div>

</div>

<script>
document.querySelectorAll('.tb').forEach(function(b){b.addEventListener('click',function(){document.querySelectorAll('.tb').forEach(function(x){x.classList.remove('on')});document.querySelectorAll('.pane').forEach(function(x){x.classList.remove('on')});b.classList.add('on');document.getElementById('p-'+b.dataset.t).classList.add('on')})});
</script>
<?php wp_footer(); ?>
</body>
</html>
