<?php
/**
 * Template Name: Admin Dashboard
 * LinkNgon V2 - Admin Dashboard UI
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) {
    wp_redirect( home_url() );
    exit;
}

$admin_nonce = wp_create_nonce( 'linkngon_admin_nonce' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - LinkNgon</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<?php wp_head(); ?>
<style>
:root {
    --p: #0D4F4F; --pl: #1A7A7A; --pd: #083838;
    --a: #E8A838; --al: #F0C060;
    --bg: #F7F5F0; --card: #FFFFFF; --dark: #1A1A2E;
    --txt: #2C2C3A; --txtl: #6B7280; --txtm: #9CA3AF;
    --brd: #E5E2DB; --brdl: #F0EDE6;
    --ok: #059669; --warn: #D97706; --err: #DC2626; --info: #2563EB;
    --font: 'Inter', sans-serif;
    --fonth: 'Plus Jakarta Sans', sans-serif;
    --mono: 'JetBrains Mono', monospace;
    --rad: 12px; --rads: 8px; --radl: 20px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font); background: var(--bg); color: var(--txt); line-height: 1.6; }

/* Sidebar */
.sidebar {
    width: 260px; position: fixed; top: 0; left: 0; bottom: 0;
    background: var(--dark); color: #fff; overflow-y: auto; z-index: 100;
    transition: transform 0.3s;
}
.sidebar-logo {
    padding: 28px 24px; border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex; align-items: center; gap: 12px;
}
.sidebar-logo h2 { font-family: var(--fonth); font-size: 22px; color: var(--a); }
.sidebar-logo span { font-size: 28px; }
.sidebar-nav { padding: 16px 0; }
.sidebar-nav a {
    display: flex; align-items: center; gap: 12px;
    padding: 13px 24px; color: rgba(255,255,255,0.55); font-size: 14px;
    text-decoration: none; transition: all 0.2s; border-left: 3px solid transparent;
}
.sidebar-nav a:hover, .sidebar-nav a.active {
    color: #fff; background: rgba(255,255,255,0.06);
    border-left-color: var(--a);
}
.sidebar-nav .nav-icon { font-size: 18px; width: 24px; text-align: center; }
.sidebar-nav .nav-badge {
    margin-left: auto; background: var(--err); color: #fff;
    font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600;
}

/* Main */
.main { margin-left: 260px; padding: 32px; min-height: 100vh; }
.page-header { margin-bottom: 32px; }
.page-header h1 { font-family: var(--fonth); font-size: 28px; color: var(--pd); }
.page-header p { color: var(--txtl); margin-top: 4px; font-size: 14px; }

/* Stats grid */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 32px; }
.stat-card {
    background: var(--card); border-radius: var(--rad); padding: 24px;
    border: 1px solid var(--brdl); position: relative; overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.stat-card::before { content:''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
.stat-card.s1::before { background: var(--p); }
.stat-card.s2::before { background: var(--a); }
.stat-card.s3::before { background: var(--ok); }
.stat-card.s4::before { background: var(--err); }
.stat-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--txtm); margin-bottom: 8px; }
.stat-value { font-family: var(--fonth); font-size: 32px; color: var(--pd); }
.stat-sub { font-size: 12px; color: var(--txtl); margin-top: 6px; }

/* Tabs */
.tabs {
    display: flex; gap: 4px; background: var(--card); padding: 6px;
    border-radius: var(--rad); border: 1px solid var(--brdl);
    margin-bottom: 24px; overflow-x: auto;
}
.tab-btn {
    padding: 10px 20px; border-radius: var(--rads); border: none;
    background: transparent; color: var(--txtl); font-family: var(--font);
    font-size: 14px; font-weight: 500; cursor: pointer; white-space: nowrap; transition: all 0.2s;
}
.tab-btn:hover { color: var(--txt); background: var(--bg); }
.tab-btn.active { background: var(--p); color: #fff; }
.tab-content { display: none; animation: fadeIn 0.3s ease; }
.tab-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

/* Card */
.card {
    background: var(--card); border-radius: var(--rad); border: 1px solid var(--brdl);
    padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); margin-bottom: 24px;
}
.card-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--brdl);
}
.card-header h3 { font-family: var(--fonth); font-size: 18px; color: var(--pd); }

/* Table */
table { width: 100%; border-collapse: collapse; font-size: 14px; }
thead th {
    background: var(--bg); padding: 12px 16px; text-align: left;
    font-weight: 600; font-size: 12px; text-transform: uppercase;
    letter-spacing: 0.05em; color: var(--txtl); border-bottom: 2px solid var(--brd);
}
tbody td { padding: 12px 16px; border-bottom: 1px solid var(--brdl); vertical-align: middle; }
tbody tr:hover { background: rgba(13,79,79,0.02); }

/* Forms */
.form-group { margin-bottom: 20px; }
.form-label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; }
.form-input, .form-select {
    width: 100%; padding: 12px 16px; border: 1px solid var(--brd);
    border-radius: var(--rads); font-family: var(--font); font-size: 14px;
    transition: border-color 0.2s;
}
.form-input:focus, .form-select:focus {
    outline: none; border-color: var(--p); box-shadow: 0 0 0 3px rgba(13,79,79,0.1);
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* Buttons */
.btn {
    display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;
    border-radius: var(--rads); font-family: var(--font); font-size: 14px;
    font-weight: 600; border: none; cursor: pointer; transition: all 0.25s; text-decoration: none;
}
.btn-primary { background: var(--p); color: #fff; }
.btn-primary:hover { background: var(--pl); transform: translateY(-1px); }
.btn-accent { background: var(--a); color: var(--pd); }
.btn-outline { background: transparent; color: var(--p); border: 2px solid var(--p); }
.btn-sm { padding: 6px 14px; font-size: 13px; }
.btn-danger { background: var(--err); color: #fff; }

/* Badges */
.badge { display: inline-flex; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.badge-ok { background: #D1FAE5; color: #065F46; }
.badge-warn { background: #FEF3C7; color: #92400E; }
.badge-err { background: #FEE2E2; color: #991B1B; }
.badge-info { background: #DBEAFE; color: #1E40AF; }
.badge-mute { background: #F3F4F6; color: #4B5563; }

/* Toast */
.toast-container { position: fixed; top: 16px; right: 24px; z-index: 10000; display: flex; flex-direction: column; gap: 8px; }
.toast {
    padding: 14px 20px; border-radius: var(--rads); font-size: 14px; font-weight: 500;
    color: #fff; box-shadow: 0 10px 25px rgba(0,0,0,0.12); animation: slideIn 0.3s ease;
    display: flex; align-items: center; gap: 10px; min-width: 300px;
}
.toast-ok { background: var(--ok); }
.toast-err { background: var(--err); }
@keyframes slideIn { from { opacity: 0; transform: translateX(100px); } to { opacity: 1; transform: translateX(0); } }

/* Mobile */
.sidebar-toggle { display: none; position: fixed; top: 12px; left: 12px; z-index: 200; background: var(--p); color: #fff; border: none; border-radius: var(--rads); padding: 8px 12px; cursor: pointer; font-size: 20px; }
@media(max-width:1024px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .main { margin-left: 0; }
    .sidebar-toggle { display: block; }
}
@media(max-width:768px) {
    .stats-grid { grid-template-columns: repeat(2,1fr); }
    .form-row { grid-template-columns: 1fr; }
}
@media(max-width:480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .main { padding: 16px; }
}
</style>
</head>
<body>

<button class="sidebar-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')">☰</button>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--a)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
        <h2>LinkNgon</h2>
    </div>
    <nav class="sidebar-nav">
        <a href="#" class="active" data-tab="overview">
            <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></span> Tổng quan
        </a>
        <a href="#" data-tab="campaigns">
            <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg></span> Campaigns
        </a>
        <a href="#" data-tab="orders">
            <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></span> Đơn hàng
        </a>
        <a href="#" data-tab="deposits">
            <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span> Nạp tiền
            <span class="nav-badge" id="pendingDepBadge" style="display:none">0</span>
        </a>
        <a href="#" data-tab="users">
            <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span> Người dùng
        </a>
        <a href="#" data-tab="withdrawals">
            <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span> Rút tiền
            <span class="nav-badge" id="pendingBadge" style="display:none">0</span>
        </a>
        <a href="#" data-tab="visits">
            <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span> Visits
        </a>
        <a href="#" data-tab="customers">
            <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span> Khách hàng
        </a>
        <a href="#" data-tab="links">
            <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></span> Shortlinks
        </a>
        <a href="#" data-tab="settings">
            <span class="nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span> Cài đặt
        </a>
    </nav>
</aside>

<!-- MAIN CONTENT -->
<main class="main">
    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Quản lý shortlink, campaigns & users</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid" id="statsGrid">
        <div class="stat-card s1">
            <div class="stat-label">Campaigns đang chạy</div>
            <div class="stat-value" id="statCampaigns">--</div>
        </div>
        <div class="stat-card s2">
            <div class="stat-label">Visits hôm nay</div>
            <div class="stat-value" id="statVisits">--</div>
        </div>
        <div class="stat-card s3">
            <div class="stat-label">Thu nhập hôm nay</div>
            <div class="stat-value" id="statEarned">--</div>
        </div>
        <div class="stat-card s4">
            <div class="stat-label">Chờ duyệt rút tiền</div>
            <div class="stat-value" id="statPending">--</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs" style="flex-wrap:wrap">
        <button class="tab-btn active" data-tab="overview">Tổng quan</button>
        <button class="tab-btn" data-tab="campaigns">Campaigns</button>
        <button class="tab-btn" data-tab="orders">Đơn hàng</button>
        <button class="tab-btn" data-tab="deposits">Nạp tiền</button>
        <button class="tab-btn" data-tab="users">Người dùng</button>
        <button class="tab-btn" data-tab="withdrawals">Rút tiền</button>
        <button class="tab-btn" data-tab="visits">Visits</button>
        <button class="tab-btn" data-tab="customers">Khách hàng</button>
        <button class="tab-btn" data-tab="links">Shortlinks</button>
        <button class="tab-btn" data-tab="settings">Cài đặt</button>
    </div>

    <!-- Tab: Overview -->
    <div class="tab-content active" id="tab-overview">
        <div class="card">
            <div class="card-header">
                <h3>Thống kê tổng quan</h3>
                <button class="btn btn-sm btn-outline" onclick="loadStats()">Refresh</button>
            </div>
            <div class="stats-grid">
                <div class="stat-card s1">
                    <div class="stat-label">Tổng campaigns</div>
                    <div class="stat-value" id="statTotalCampaigns">--</div>
                </div>
                <div class="stat-card s2">
                    <div class="stat-label">Tổng visits (verified)</div>
                    <div class="stat-value" id="statTotalVisits">--</div>
                </div>
                <div class="stat-card s3">
                    <div class="stat-label">Tổng đã trả users</div>
                    <div class="stat-value" id="statTotalEarned">--</div>
                </div>
                <div class="stat-card s1">
                    <div class="stat-label">Tổng users hoạt động</div>
                    <div class="stat-value" id="statTotalUsers">--</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab: Campaigns -->
    <div class="tab-content" id="tab-campaigns">
        <div class="card">
            <div class="card-header">
                <h3>Tạo Campaign Mới</h3>
            </div>
            <form id="createCampaignForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tên campaign</label>
                        <input class="form-input" name="name" required placeholder="VD: SEO từ khóa ABC">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Keyword</label>
                        <input class="form-input" name="keyword" placeholder="Từ khóa tìm kiếm">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">URL đích</label>
                    <input class="form-input" name="target_url" type="url" required placeholder="https://example.com/page">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Loại traffic</label>
                        <select class="form-select" name="traffic_type">
                            <option value="keyword">Keyword (Google Search)</option>
                            <option value="direct">Direct (Truy cập trực tiếp)</option>
                            <option value="all">Tất cả</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Thưởng/visit (VNĐ)</label>
                        <input class="form-input" name="reward_amount" type="number" value="500" min="0">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Giới hạn/ngày</label>
                        <input class="form-input" name="daily_limit" type="number" value="0" min="0" placeholder="0 = không giới hạn">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tổng giới hạn</label>
                        <input class="form-input" name="total_limit" type="number" value="0" min="0" placeholder="0 = không giới hạn">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">IP limit/ngày</label>
                        <input class="form-input" name="ip_limit_per_day" type="number" value="1" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Countdown (giây)</label>
                        <input class="form-input" name="countdown_seconds" type="number" value="30" min="5">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Tạo Campaign</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Danh sách Campaigns</h3>
                <div style="display:flex;gap:8px;">
                    <select class="form-select" id="campaignFilter" style="width:auto;" onchange="loadCampaigns()">
                        <option value="">Tất cả</option>
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                        <option value="expired">Expired</option>
                    </select>
                    <button class="btn btn-sm btn-outline" onclick="loadCampaigns()">Refresh</button>
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên</th>
                            <th>Shortlink</th>
                            <th>Traffic</th>
                            <th>Thưởng</th>
                            <th>Hoàn thành</th>
                            <th>Status</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="campaignsTable">
                        <tr><td colspan="8" style="text-align:center;color:var(--txtm)">Đang tải...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab: Withdrawals -->
    <div class="tab-content" id="tab-withdrawals">
        <div class="card">
            <div class="card-header">
                <h3>Yêu cầu rút tiền</h3>
                <select class="form-select" id="withdrawalFilter" style="width:auto;" onchange="loadWithdrawals()">
                    <option value="pending">Chờ duyệt</option>
                    <option value="">Tất cả</option>
                    <option value="approved">Đã duyệt</option>
                    <option value="completed">Hoàn thành</option>
                    <option value="rejected">Từ chối</option>
                </select>
            </div>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th>User</th><th>Số tiền</th><th>Ngân hàng</th>
                            <th>Status</th><th>Ngày tạo</th><th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="withdrawalsTable">
                        <tr><td colspan="7" style="text-align:center;color:var(--txtm)">Đang tải...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab: Deposits -->
    <div class="tab-content" id="tab-deposits">
        <div class="card">
            <div class="card-header">
                <h3>Yêu cầu nạp tiền</h3>
                <select class="form-select" id="depositFilter" style="width:auto;" onchange="loadDeposits()">
                    <option value="pending">Chờ duyệt</option>
                    <option value="">Tất cả</option>
                    <option value="approved">Đã duyệt</option>
                    <option value="rejected">Từ chối</option>
                </select>
            </div>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>#</th><th>Khách hàng</th><th>Số tiền</th><th>Bonus</th><th>Tổng</th>
                            <th>PT</th><th>Trạng thái</th><th>Ngày</th><th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="depositsTable">
                        <tr><td colspan="9" style="text-align:center;color:var(--txtm)">Đang tải...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Placeholder tabs (lazy loaded) -->
    <div class="tab-content" id="tab-orders"><div class="card"><p style="color:var(--txtm)">Loading orders...</p></div></div>
    <div class="tab-content" id="tab-users"><div class="card"><p style="color:var(--txtm)">Loading users...</p></div></div>
    <div class="tab-content" id="tab-visits"><div class="card"><p style="color:var(--txtm)">Loading visits...</p></div></div>
    <div class="tab-content" id="tab-customers"><div class="card"><p style="color:var(--txtm)">Loading customers...</p></div></div>
    <div class="tab-content" id="tab-links"><div class="card"><p style="color:var(--txtm)">Loading links...</p></div></div>
    <div class="tab-content" id="tab-settings"><div class="card"><p style="color:var(--txtm)">Loading settings...</p></div></div>
</main>

<div class="toast-container" id="toastContainer"></div>

<script>
var AJAX = '<?php echo admin_url("admin-ajax.php"); ?>';
var NONCE = '<?php echo $admin_nonce; ?>';
var HOME = '<?php echo home_url(); ?>';

/* === Tab system === */
document.querySelectorAll('[data-tab]').forEach(function(el) {
    el.addEventListener('click', function(e) {
        e.preventDefault();
        var tab = this.dataset.tab;

        // Update sidebar nav
        document.querySelectorAll('.sidebar-nav a').forEach(function(a) { a.classList.remove('active'); });
        document.querySelector('.sidebar-nav a[data-tab="' + tab + '"]')?.classList.add('active');

        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
        document.querySelector('.tab-btn[data-tab="' + tab + '"]')?.classList.add('active');

        // Show tab
        document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
        var tabEl = document.getElementById('tab-' + tab);
        if (tabEl) tabEl.classList.add('active');

        // Lazy load
        if (tab === 'campaigns') loadCampaigns();
        if (tab === 'withdrawals') loadWithdrawals();
        if (tab === 'deposits') loadDeposits();

        // Close mobile sidebar
        document.querySelector('.sidebar').classList.remove('open');
    });
});

/* === AJAX helper === */
function ajax(action, data, cb) {
    data.action = action;
    data.nonce = NONCE;
    var fd = new FormData();
    for (var k in data) fd.append(k, data[k]);
    fetch(AJAX, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(cb)
        .catch(function(e) { toast('Lỗi: ' + e.message, 'err'); });
}

/* === Stats === */
function loadStats() {
    ajax('linkngon_admin_stats', {}, function(r) {
        if (!r.success) return;
        var d = r.data;
        document.getElementById('statCampaigns').textContent = d.active_campaigns;
        document.getElementById('statVisits').textContent = d.today_visits.toLocaleString();
        document.getElementById('statEarned').textContent = formatMoney(d.today_earned);
        document.getElementById('statPending').textContent = d.pending_withdrawals;
        document.getElementById('statTotalCampaigns').textContent = d.total_campaigns;
        document.getElementById('statTotalVisits').textContent = d.total_visits.toLocaleString();
        document.getElementById('statTotalEarned').textContent = formatMoney(d.total_earned);
        document.getElementById('statTotalUsers').textContent = d.total_users;

        // Badge
        var badge = document.getElementById('pendingBadge');
        if (d.pending_withdrawals > 0) {
            badge.textContent = d.pending_withdrawals;
            badge.style.display = 'inline';
        } else { badge.style.display = 'none'; }
    });
}

/* === Campaigns === */
function loadCampaigns() {
    var status = document.getElementById('campaignFilter')?.value || '';
    ajax('linkngon_admin_get_campaigns', { status: status }, function(r) {
        if (!r.success) return;
        var tbody = document.getElementById('campaignsTable');
        if (!r.data.campaigns.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--txtm)">Không có campaign nào</td></tr>';
            return;
        }
        tbody.innerHTML = r.data.campaigns.map(function(c) {
            var link = HOME + '/s/' + c.shortcode;
            var badgeCls = c.status === 'active' ? 'badge-ok' : (c.status === 'paused' ? 'badge-warn' : 'badge-mute');
            var traffic = c.traffic_type === 'keyword' ? 'Keyword' : (c.traffic_type === 'direct' ? 'Direct' : 'All');
            return '<tr>' +
                '<td>#' + c.id + '</td>' +
                '<td><strong>' + escHtml(c.name) + '</strong><br><small style="color:var(--txtm)">' + escHtml(c.keyword) + '</small></td>' +
                '<td><a href="' + link + '" target="_blank" style="font-family:var(--mono);font-size:12px;">' + link + '</a></td>' +
                '<td>' + traffic + '</td>' +
                '<td>' + formatMoney(c.reward_amount) + '</td>' +
                '<td>' + c.total_completed + (c.total_limit > 0 ? '/' + c.total_limit : '') + '</td>' +
                '<td><span class="badge ' + badgeCls + '">' + c.status + '</span></td>' +
                '<td><button class="btn btn-sm btn-outline" onclick="toggleCampaign(' + c.id + ',\'' + c.status + '\')">' + (c.status === 'active' ? 'Pause' : 'Play') + '</button></td>' +
                '</tr>';
        }).join('');
    });
}

function toggleCampaign(id, current) {
    var next = current === 'active' ? 'paused' : 'active';
    ajax('linkngon_admin_update_campaign', { campaign_id: id, status: next }, function(r) {
        if (r.success) { toast('Đã cập nhật campaign', 'ok'); loadCampaigns(); }
        else toast(r.data || 'Lỗi', 'err');
    });
}

// Create campaign form
document.getElementById('createCampaignForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(this);
    var data = {};
    fd.forEach(function(v, k) { data[k] = v; });
    ajax('linkngon_admin_create_campaign', data, function(r) {
        if (r.success) {
            toast('Campaign đã tạo thành công!', 'ok');
            document.getElementById('createCampaignForm').reset();
            loadCampaigns();
        } else toast(r.data || 'Lỗi', 'err');
    });
});

/* === Withdrawals === */
function loadWithdrawals() {
    var status = document.getElementById('withdrawalFilter')?.value || '';
    ajax('linkngon_admin_get_withdrawals', { status: status }, function(r) {
        if (!r.success) return;
        var tbody = document.getElementById('withdrawalsTable');
        if (!r.data.withdrawals.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--txtm)">Không có yêu cầu nào</td></tr>';
            return;
        }
        tbody.innerHTML = r.data.withdrawals.map(function(w) {
            var badgeCls = { pending:'badge-warn', approved:'badge-info', completed:'badge-ok', rejected:'badge-err', refunded:'badge-mute', cancelled:'badge-mute' }[w.status] || 'badge-mute';
            var actions = '';
            if (w.status === 'pending') {
                actions = '<button class="btn btn-sm btn-primary" onclick="processW(' + w.id + ',\'approved\')">Duyệt</button> ' +
                          '<button class="btn btn-sm btn-danger" onclick="processW(' + w.id + ',\'rejected\')">Từ chối</button>';
            } else if (w.status === 'approved') {
                actions = '<button class="btn btn-sm btn-primary" onclick="processW(' + w.id + ',\'completed\')">Hoàn thành</button>';
            }
            return '<tr>' +
                '<td>#' + w.id + '</td>' +
                '<td>' + escHtml(w.display_name || 'User #' + w.user_id) + '<br><small>' + escHtml(w.user_email || '') + '</small></td>' +
                '<td style="font-weight:600;color:var(--err)">' + formatMoney(w.amount) + '</td>' +
                '<td>' + escHtml(w.bank_name) + '<br><small>' + escHtml(w.bank_account) + ' - ' + escHtml(w.bank_holder) + '</small></td>' +
                '<td><span class="badge ' + badgeCls + '">' + w.status + '</span></td>' +
                '<td><small>' + w.created_at + '</small></td>' +
                '<td>' + actions + '</td></tr>';
        }).join('');
    });
}

function processW(id, status) {
    var note = status === 'rejected' ? prompt('Lý do từ chối:') : '';
    if (status === 'rejected' && note === null) return;
    ajax('linkngon_admin_process_withdrawal', { withdrawal_id: id, new_status: status, admin_note: note || '' }, function(r) {
        if (r.success) { toast('Đã xử lý!', 'ok'); loadWithdrawals(); loadStats(); }
        else toast(r.data || 'Lỗi', 'err');
    });
}

/* === Helpers === */
function formatMoney(n) { return new Intl.NumberFormat('vi-VN').format(n || 0) + 'đ'; }
function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}
function toast(msg, type) {
    var c = document.getElementById('toastContainer');
    var t = document.createElement('div');
    t.className = 'toast toast-' + (type || 'ok');
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(function() { t.remove(); }, 4000);
}

/* === Deposits === */
function loadDeposits() {
    var status = document.getElementById('depositFilter')?.value || '';
    ajax('linkngon_admin_get_deposits', { status: status }, function(r) {
        if (!r.success) return;
        var tbody = document.getElementById('depositsTable');
        if (!r.data.deposits.length) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--txtm)">Không có đơn nạp nào</td></tr>';
            return;
        }
        tbody.innerHTML = r.data.deposits.map(function(d) {
            var badgeCls = { pending:'badge-warn', approved:'badge-ok', rejected:'badge-err' }[d.status] || 'badge-mute';
            var statusLabel = { pending:'Chờ duyệt', approved:'Đã duyệt', rejected:'Từ chối' }[d.status] || d.status;
            var bonus = parseFloat(d.bonus_amount) || 0;
            var total = parseFloat(d.amount) + bonus;
            var actions = '';
            if (d.status === 'pending') {
                actions = '<button class="btn btn-sm btn-primary" onclick="processDeposit(' + d.id + ',\'approved\')">Duyệt</button> ' +
                          '<button class="btn btn-sm btn-danger" onclick="processDeposit(' + d.id + ',\'rejected\')">Từ chối</button>';
            }
            return '<tr>' +
                '<td>#' + d.id + '</td>' +
                '<td><strong>' + escHtml(d.customer_username) + '</strong></td>' +
                '<td style="font-weight:600;color:var(--ok)">+' + formatMoney(d.amount) + '</td>' +
                '<td>' + (bonus > 0 ? '+' + formatMoney(bonus) + ' (' + d.bonus_percent + '%)' : '—') + '</td>' +
                '<td style="font-weight:700">' + formatMoney(total) + '</td>' +
                '<td>' + escHtml(d.payment_method || 'bank') + '</td>' +
                '<td><span class="badge ' + badgeCls + '">' + statusLabel + '</span></td>' +
                '<td><small>' + d.created_at + '</small></td>' +
                '<td>' + actions + '</td></tr>';
        }).join('');
    });
}

function processDeposit(id, status) {
    if (status === 'rejected' && !confirm('Từ chối đơn nạp #' + id + '?')) return;
    ajax('linkngon_admin_process_deposit', { deposit_id: id, new_status: status }, function(r) {
        if (r.success) { toast('Đã xử lý đơn nạp!', 'ok'); loadDeposits(); loadStats(); }
        else toast(r.data || 'Lỗi', 'err');
    });
}

// Init
loadStats();
</script>

<?php wp_footer(); ?>
</body>
</html>
