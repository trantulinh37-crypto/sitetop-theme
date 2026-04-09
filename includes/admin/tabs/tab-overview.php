<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;

global $wpdb;
$prefix = $wpdb->prefix . 'linkngon_';
$now_vn = linkngon_current_time();
$today = date('Y-m-d', strtotime($now_vn));
$current_month = date('Y-m', strtotime($now_vn));
$nonce = wp_create_nonce('linkngon_admin_nonce');

// Quick stats (all-time + today)
$total_verified = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE step='verified'");
$today_verified = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}shortlink_visits WHERE step='verified' AND DATE(created_at)=%s", $today));
$total_customer_paid = (float) $wpdb->get_var("SELECT COALESCE(ABS(SUM(amount)),0) FROM {$prefix}customer_transactions WHERE type='campaign_view' AND amount < 0");
$total_user_earned = (float) $wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$prefix}transactions WHERE type='shortlink_reward'");
?>
<div class="wrap" id="linkngon-overview">
<style>
#linkngon-overview{max-width:1100px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}

/* Stats cards - same style as tab-customers */
.ov-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
.ov-stat{border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px}
.ov-stat.cs1{background:#eff6ff;border:2px solid #bfdbfe} .ov-stat.cs2{background:#f5f3ff;border:2px solid #c4b5fd}
.ov-stat.cs3{background:#fffbeb;border:2px solid #fde68a} .ov-stat.cs4{background:#ecfdf5;border:2px solid #a7f3d0}
.ov-val{font-size:22px;font-weight:700;line-height:1.2}
.ov-stat.cs1 .ov-val{color:#1e40af} .ov-stat.cs2 .ov-val{color:#5b21b6}
.ov-stat.cs3 .ov-val{color:#92400e} .ov-stat.cs4 .ov-val{color:#065f46}
.ov-label{font-size:12px;color:#6b7280}
.ov-ico{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ov-ico.ci1{background:#dbeafe;color:#2563eb} .ov-ico.ci2{background:#ede9fe;color:#7c3aed}
.ov-ico.ci3{background:#fef3c7;color:#d97706} .ov-ico.ci4{background:#d1fae5;color:#059669}

/* Month picker */
.ov-month-bar{display:flex;align-items:center;gap:10px;margin-bottom:16px}
.ov-month-bar input[type=month]{padding:7px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;font-family:inherit;background:#fff}
.ov-month-nav{background:#fff;border:1px solid #d1d5db;border-radius:8px;width:34px;height:34px;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;color:#374151}
.ov-month-nav:hover{background:#f3f4f6}

/* Monthly summary cards */
.ov-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
.ov-sum{border-radius:12px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.ov-sum.ms1{background:#eff6ff;border:2px solid #bfdbfe} .ov-sum.ms2{background:#fffbeb;border:2px solid #fde68a}
.ov-sum.ms3{background:#ecfdf5;border:2px solid #a7f3d0} .ov-sum.ms4{background:#fef2f2;border:2px solid #fecaca}
.ov-sum.ms5{background:#f5f3ff;border:2px solid #c4b5fd} .ov-sum.ms6{background:#fff7ed;border:2px solid #fed7aa}
.ov-sum.ms7{background:#f0fdf4;border:2px solid #bbf7d0} .ov-sum.ms8{background:#faf5ff;border:2px solid #e9d5ff}
.ov-sv{font-size:17px;font-weight:700;color:#1f2937;line-height:1.2}
.ov-sl{font-size:11px;color:#6b7280;margin-top:2px}
.ov-sico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ov-sico.si1{background:#dbeafe;color:#2563eb} .ov-sico.si2{background:#fef3c7;color:#d97706}
.ov-sico.si3{background:#d1fae5;color:#059669} .ov-sico.si4{background:#fecaca;color:#dc2626}
.ov-sico.si5{background:#ede9fe;color:#7c3aed} .ov-sico.si6{background:#fed7aa;color:#ea580c}
.ov-sico.si7{background:#bbf7d0;color:#16a34a} .ov-sico.si8{background:#e9d5ff;color:#9333ea}

/* Chart */
.ov-chart-wrap{background:#fff;border-radius:12px;border:2px solid #e5e7eb;padding:20px;margin-bottom:24px}
.ov-chart-title{font-size:14px;font-weight:600;color:#374151;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.ov-chart-legend{display:flex;gap:16px;font-size:12px;color:#6b7280}
.ov-chart-legend span{display:inline-flex;align-items:center;gap:5px}
.ov-chart-legend span::before{content:'';width:14px;height:3px;border-radius:2px;display:inline-block}
.ov-chart-legend .lg-views::before{background:#3b82f6}
.ov-chart-legend .lg-cpaid::before{background:#f59e0b}
.ov-chart-legend .lg-uearn::before{background:#10b981}
.ov-chart-container{position:relative;height:340px}

@media(max-width:782px){
    .ov-stats,.ov-summary{grid-template-columns:repeat(2,1fr)}
    .ov-val{font-size:17px} .ov-sv{font-size:14px}
    .ov-stat,.ov-sum{padding:12px 14px}
    .ov-ico{width:38px;height:38px} .ov-ico svg{width:20px;height:20px}
    .ov-sico{width:34px;height:34px} .ov-sico svg{width:18px;height:18px}
    .ov-chart-container{height:260px}
}
</style>

<h1 style="font-size:20px;font-weight:700;margin-bottom:16px">Tổng quan hệ thống</h1>

<!-- All-time stats -->
<div class="ov-stats">
    <div class="ov-stat cs1">
        <div><div class="ov-val"><?php echo number_format($total_verified); ?></div><div class="ov-label">Tổng view (all-time)</div></div>
        <div class="ov-ico ci1"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div>
    </div>
    <div class="ov-stat cs2">
        <div><div class="ov-val"><?php echo number_format($today_verified); ?></div><div class="ov-label">View hôm nay</div></div>
        <div class="ov-ico ci2"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
    </div>
    <div class="ov-stat cs3">
        <div><div class="ov-val"><?php echo linkngon_format_money($total_customer_paid); ?></div><div class="ov-label">Khách trả (all-time)</div></div>
        <div class="ov-ico ci3"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
    </div>
    <div class="ov-stat cs4">
        <div><div class="ov-val"><?php echo linkngon_format_money($total_user_earned); ?></div><div class="ov-label">User kiếm được (all-time)</div></div>
        <div class="ov-ico ci4"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
    </div>
</div>

<!-- Month picker -->
<div class="ov-month-bar">
    <button class="ov-month-nav" id="prevMonth"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></button>
    <input type="month" id="ovMonth" value="<?php echo $current_month; ?>">
    <button class="ov-month-nav" id="nextMonth"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>
</div>

<!-- Monthly summary -->
<div class="ov-summary" id="ovSummary">
    <div class="ov-sum ms1"><div><div class="ov-sv" id="smViews">-</div><div class="ov-sl">View tháng này</div></div><div class="ov-sico si1"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div></div>
    <div class="ov-sum ms2"><div><div class="ov-sv" id="smCpaid">-</div><div class="ov-sl">Khách trả</div></div><div class="ov-sico si2"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
    <div class="ov-sum ms3"><div><div class="ov-sv" id="smUearn">-</div><div class="ov-sl">User kiếm</div></div><div class="ov-sico si3"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div></div>
    <div class="ov-sum ms4"><div><div class="ov-sv" id="smRevenue">-</div><div class="ov-sl">Lợi nhuận nền tảng</div></div><div class="ov-sico si4"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div></div>
    <div class="ov-sum ms5"><div><div class="ov-sv" id="smDeposits">-</div><div class="ov-sl">Nạp tiền</div></div><div class="ov-sico si5"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div>
    <div class="ov-sum ms6"><div><div class="ov-sv" id="smWithdrawals">-</div><div class="ov-sl">Rút tiền</div></div><div class="ov-sico si6"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></div></div>
    <div class="ov-sum ms7"><div><div class="ov-sv" id="smNewUsers">-</div><div class="ov-sl">User mới</div></div><div class="ov-sico si7"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div></div>
    <div class="ov-sum ms8"><div><div class="ov-sv" id="smTotalVisits">-</div><div class="ov-sl">Tổng truy cập</div></div><div class="ov-sico si8"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div></div>
</div>

<!-- Chart -->
<div class="ov-chart-wrap">
    <div class="ov-chart-title">
        <span>Biểu đồ theo ngày</span>
        <div class="ov-chart-legend">
            <span class="lg-views">Views</span>
            <span class="lg-cpaid">Khách trả</span>
            <span class="lg-uearn">User kiếm</span>
        </div>
    </div>
    <div class="ov-chart-container">
        <canvas id="ovChart"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function(){
    var nonce = '<?php echo $nonce; ?>';
    var monthInput = document.getElementById('ovMonth');
    var chart = null;

    function fmt(n) {
        if (n >= 1000000) return (n/1000000).toFixed(1) + 'M';
        if (n >= 1000) return (n/1000).toFixed(0) + 'K';
        return n.toLocaleString('vi-VN');
    }
    function fmtFull(n) { return n.toLocaleString('vi-VN'); }
    function fmtMoney(n) { return fmtFull(n) + 'đ'; }

    function loadData(month) {
        var fd = new FormData();
        fd.append('action', 'linkngon_admin_chart_data');
        fd.append('nonce', nonce);
        fd.append('month', month);

        fetch(ajaxurl, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(r) {
            if (!r.success) return;
            var d = r.data;
            var s = d.summary;

            document.getElementById('smViews').textContent = fmtFull(s.verified);
            document.getElementById('smCpaid').textContent = fmtMoney(s.customer_paid);
            document.getElementById('smUearn').textContent = fmtMoney(s.user_earned);
            document.getElementById('smRevenue').textContent = fmtMoney(s.platform_revenue);
            document.getElementById('smDeposits').textContent = fmtMoney(s.deposits);
            document.getElementById('smWithdrawals').textContent = fmtMoney(s.withdrawals);
            document.getElementById('smNewUsers').textContent = fmtFull(s.new_users);
            document.getElementById('smTotalVisits').textContent = fmtFull(s.total_visits);

            var labels = d.daily.map(function(x) { return x.date.substring(8); });
            var views = d.daily.map(function(x) { return x.verified; });
            var cpaid = d.daily.map(function(x) { return x.customer_paid; });
            var uearn = d.daily.map(function(x) { return x.user_earned; });

            if (chart) chart.destroy();
            var ctx = document.getElementById('ovChart').getContext('2d');
            chart = new Chart(ctx, {
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
                            label: 'Khách trả (đ)',
                            data: cpaid,
                            borderColor: '#f59e0b',
                            backgroundColor: 'transparent',
                            tension: 0.3,
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            yAxisID: 'y1'
                        },
                        {
                            label: 'User kiếm (đ)',
                            data: uearn,
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
                                    if (ctx.datasetIndex === 0) return ' ' + ctx.dataset.label + ': ' + fmtFull(v);
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
        });
    }

    document.getElementById('prevMonth').onclick = function() {
        var d = new Date(monthInput.value + '-15');
        d.setMonth(d.getMonth() - 1);
        monthInput.value = d.toISOString().substring(0, 7);
        loadData(monthInput.value);
    };
    document.getElementById('nextMonth').onclick = function() {
        var d = new Date(monthInput.value + '-15');
        d.setMonth(d.getMonth() + 1);
        monthInput.value = d.toISOString().substring(0, 7);
        loadData(monthInput.value);
    };
    monthInput.onchange = function() { loadData(this.value); };

    loadData(monthInput.value);
})();
</script>
</div>
