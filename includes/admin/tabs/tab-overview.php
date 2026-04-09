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
.ov-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
.ov-stat{background:#fff;border-radius:10px;padding:18px 20px;border:1px solid #e5e7eb;position:relative;overflow:hidden}
.ov-stat .val{font-size:22px;font-weight:700;line-height:1.2}
.ov-stat .lbl{font-size:12px;color:#6b7280;margin-top:2px}
.ov-stat .ico{position:absolute;right:16px;top:50%;transform:translateY(-50%);font-size:28px;opacity:.15}
.ov-stat.s1{border-left:4px solid #3b82f6}.ov-stat.s1 .val{color:#3b82f6}
.ov-stat.s2{border-left:4px solid #8b5cf6}.ov-stat.s2 .val{color:#8b5cf6}
.ov-stat.s3{border-left:4px solid #f59e0b}.ov-stat.s3 .val{color:#f59e0b}
.ov-stat.s4{border-left:4px solid #10b981}.ov-stat.s4 .val{color:#10b981}

.ov-month-bar{display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.ov-month-bar input[type=month]{padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;font-family:inherit}
.ov-month-bar .month-nav{background:#fff;border:1px solid #d1d5db;border-radius:8px;width:34px;height:34px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center}
.ov-month-bar .month-nav:hover{background:#f3f4f6}

.ov-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px}
.ov-sum{background:#fff;border-radius:8px;padding:14px 16px;border:1px solid #e5e7eb;text-align:center}
.ov-sum .sv{font-size:18px;font-weight:700;color:#1f2937}
.ov-sum .sl{font-size:11px;color:#9ca3af;margin-top:2px}

.ov-chart-wrap{background:#fff;border-radius:10px;border:1px solid #e5e7eb;padding:20px;margin-bottom:24px}
.ov-chart-title{font-size:14px;font-weight:600;color:#374151;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between}
.ov-chart-legend{display:flex;gap:16px;font-size:12px;color:#6b7280}
.ov-chart-legend span{display:inline-flex;align-items:center;gap:4px}
.ov-chart-legend span::before{content:'';width:12px;height:3px;border-radius:2px;display:inline-block}
.ov-chart-legend .lg-views::before{background:#3b82f6}
.ov-chart-legend .lg-cpaid::before{background:#f59e0b}
.ov-chart-legend .lg-uearn::before{background:#10b981}
#ovChart{width:100%;height:280px}

.ov-loading{text-align:center;padding:40px;color:#9ca3af;font-size:14px}

@media(max-width:782px){
    .ov-stats,.ov-summary{grid-template-columns:repeat(2,1fr)}
    .ov-stat .val{font-size:18px}
}
</style>

<h1 style="font-size:20px;font-weight:700;margin-bottom:18px">Tổng quan hệ thống</h1>

<!-- All-time stats -->
<div class="ov-stats">
    <div class="ov-stat s1">
        <div class="val"><?php echo number_format($total_verified); ?></div>
        <div class="lbl">Tổng view (all-time)</div>
        <div class="ico">&#128065;</div>
    </div>
    <div class="ov-stat s2">
        <div class="val"><?php echo number_format($today_verified); ?></div>
        <div class="lbl">View hôm nay</div>
        <div class="ico">&#128200;</div>
    </div>
    <div class="ov-stat s3">
        <div class="val"><?php echo number_format($total_customer_paid); ?>đ</div>
        <div class="lbl">Khách trả (all-time)</div>
        <div class="ico">&#128176;</div>
    </div>
    <div class="ov-stat s4">
        <div class="val"><?php echo number_format($total_user_earned); ?>đ</div>
        <div class="lbl">User kiếm được (all-time)</div>
        <div class="ico">&#128178;</div>
    </div>
</div>

<!-- Month picker -->
<div class="ov-month-bar">
    <button class="month-nav" id="prevMonth">&#9664;</button>
    <input type="month" id="ovMonth" value="<?php echo $current_month; ?>">
    <button class="month-nav" id="nextMonth">&#9654;</button>
</div>

<!-- Monthly summary (loaded via AJAX) -->
<div class="ov-summary" id="ovSummary">
    <div class="ov-sum"><div class="sv" id="smViews">-</div><div class="sl">View tháng này</div></div>
    <div class="ov-sum"><div class="sv" id="smCpaid">-</div><div class="sl">Khách trả</div></div>
    <div class="ov-sum"><div class="sv" id="smUearn">-</div><div class="sl">User kiếm</div></div>
    <div class="ov-sum"><div class="sv" id="smRevenue">-</div><div class="sl">Lợi nhuận nền tảng</div></div>
    <div class="ov-sum"><div class="sv" id="smDeposits">-</div><div class="sl">Nạp tiền</div></div>
    <div class="ov-sum"><div class="sv" id="smWithdrawals">-</div><div class="sl">Rút tiền</div></div>
    <div class="ov-sum"><div class="sv" id="smNewUsers">-</div><div class="sl">User mới</div></div>
    <div class="ov-sum"><div class="sv" id="smTotalVisits">-</div><div class="sl">Tổng truy cập</div></div>
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
    <canvas id="ovChart"></canvas>
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

            // Update summary cards
            document.getElementById('smViews').textContent = fmtFull(s.verified);
            document.getElementById('smCpaid').textContent = fmtFull(s.customer_paid) + 'đ';
            document.getElementById('smUearn').textContent = fmtFull(s.user_earned) + 'đ';
            document.getElementById('smRevenue').textContent = fmtFull(s.platform_revenue) + 'đ';
            document.getElementById('smDeposits').textContent = fmtFull(s.deposits) + 'đ';
            document.getElementById('smWithdrawals').textContent = fmtFull(s.withdrawals) + 'đ';
            document.getElementById('smNewUsers').textContent = fmtFull(s.new_users);
            document.getElementById('smTotalVisits').textContent = fmtFull(s.total_visits);

            // Chart
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
                            pointRadius: 2,
                            pointHoverRadius: 5,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Khách trả (đ)',
                            data: cpaid,
                            borderColor: '#f59e0b',
                            backgroundColor: 'transparent',
                            tension: 0.3,
                            borderWidth: 2,
                            pointRadius: 2,
                            pointHoverRadius: 5,
                            yAxisID: 'y1'
                        },
                        {
                            label: 'User kiếm (đ)',
                            data: uearn,
                            borderColor: '#10b981',
                            backgroundColor: 'transparent',
                            tension: 0.3,
                            borderWidth: 2,
                            pointRadius: 2,
                            pointHoverRadius: 5,
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
                            callbacks: {
                                label: function(ctx) {
                                    var v = ctx.raw;
                                    if (ctx.datasetIndex === 0) return ctx.dataset.label + ': ' + fmtFull(v);
                                    return ctx.dataset.label + ': ' + fmtFull(v) + 'đ';
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

    // Month navigation
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

    // Initial load
    loadData(monthInput.value);
})();
</script>
</div>
