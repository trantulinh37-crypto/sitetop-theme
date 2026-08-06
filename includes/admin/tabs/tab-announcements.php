<?php if(!defined('ABSPATH'))exit;
if(!current_user_can('manage_options')) return;
?>
<div class="card">
    <div class="card-header">
        <h3>Quản lý Thông báo</h3>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('annCreateForm').style.display=document.getElementById('annCreateForm').style.display==='none'?'block':'none'">+ Tạo thông báo</button>
    </div>

    <!-- Create form -->
    <div id="annCreateForm" style="display:none;margin-bottom:24px;padding:20px;background:var(--bg);border-radius:var(--rads)">
        <div class="form-row" style="margin-bottom:16px">
            <div class="form-group">
                <label class="form-label">Tiêu đề</label>
                <input class="form-input" id="annTitle" placeholder="Tiêu đề thông báo" required>
            </div>
            <div class="form-group">
                <label class="form-label">Đối tượng</label>
                <select class="form-select" id="annTarget">
                    <option value="all">Tất cả (User + Customer)</option>
                    <option value="user">Chỉ User (Publisher)</option>
                    <option value="customer">Chỉ Customer (Advertiser)</option>
                </select>
            </div>
        </div>
        <div class="form-row" style="margin-bottom:16px">
            <div class="form-group">
                <label class="form-label">Loại</label>
                <select class="form-select" id="annType">
                    <option value="info">Thông tin (xanh dương)</option>
                    <option value="warning">Cảnh báo (vàng)</option>
                    <option value="success">Thành công (xanh lá)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Ghim lên đầu</label>
                <select class="form-select" id="annPinned">
                    <option value="0">Không</option>
                    <option value="1">Có</option>
                </select>
            </div>
        </div>
        <div class="form-group" style="margin-bottom:16px">
            <label class="form-label">Nội dung (hỗ trợ HTML: &lt;br&gt;, &lt;strong&gt;, &lt;em&gt;)</label>
            <textarea class="form-input" id="annMessage" rows="5" placeholder="Nội dung thông báo..." style="resize:vertical"></textarea>
        </div>
        <button class="btn btn-primary" onclick="createAnnouncement()">Tạo thông báo</button>
    </div>

    <!-- List -->
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Tiêu đề</th>
                <th>Đối tượng</th>
                <th>Loại</th>
                <th>Ghim</th>
                <th>Trạng thái</th>
                <th>Ngày tạo</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody id="annTable">
            <tr><td colspan="8" style="text-align:center;color:var(--txtm)">Đang tải...</td></tr>
        </tbody>
    </table>
</div>

<script>
function loadAnnouncements() {
    ajax('sitetop_admin_get_announcements', {}, function(r) {
        if (!r.success) return;
        var tbody = document.getElementById('annTable');
        if (!r.data.announcements.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--txtm)">Chưa có thông báo nào</td></tr>';
            return;
        }
        tbody.innerHTML = r.data.announcements.map(function(a) {
            var targetLabel = {all:'Tất cả', user:'User', customer:'Customer'}[a.target] || a.target;
            var typeLabel = {info:'Thông tin', warning:'Cảnh báo', success:'Thành công'}[a.type] || a.type;
            var typeBadge = {info:'badge-info', warning:'badge-warn', success:'badge-ok'}[a.type] || 'badge-mute';
            var statusBadge = a.status === 'active' ? 'badge-ok' : 'badge-mute';
            var statusLabel = a.status === 'active' ? 'Hiển thị' : 'Ẩn';
            return '<tr>' +
                '<td>#' + a.id + '</td>' +
                '<td><strong>' + escHtml(a.title) + '</strong><br><small style="color:var(--txtm)">' + escHtml((a.message||'').replace(/<[^>]*>/g,'').substring(0,80)) + '...</small></td>' +
                '<td>' + targetLabel + '</td>' +
                '<td><span class="badge ' + typeBadge + '">' + typeLabel + '</span></td>' +
                '<td>' + (parseInt(a.is_pinned) ? '<span style="color:var(--a)">&#9733;</span>' : '—') + '</td>' +
                '<td><span class="badge ' + statusBadge + '">' + statusLabel + '</span></td>' +
                '<td><small>' + a.created_at + '</small></td>' +
                '<td>' +
                    '<button class="btn btn-sm btn-outline" onclick="toggleAnn(' + a.id + ',\'' + (a.status==='active'?'hidden':'active') + '\')">' + (a.status==='active'?'Ẩn':'Hiện') + '</button> ' +
                    '<button class="btn btn-sm btn-danger" onclick="deleteAnn(' + a.id + ')">Xóa</button>' +
                '</td></tr>';
        }).join('');
    });
}

function createAnnouncement() {
    var title = document.getElementById('annTitle').value.trim();
    var message = document.getElementById('annMessage').value.trim();
    if (!title || !message) { toast('Vui lòng nhập đầy đủ', 'err'); return; }
    ajax('sitetop_admin_create_announcement', {
        title: title,
        message: message,
        target: document.getElementById('annTarget').value,
        type: document.getElementById('annType').value,
        is_pinned: document.getElementById('annPinned').value
    }, function(r) {
        if (r.success) {
            toast('Đã tạo thông báo!', 'ok');
            document.getElementById('annTitle').value = '';
            document.getElementById('annMessage').value = '';
            document.getElementById('annCreateForm').style.display = 'none';
            loadAnnouncements();
        } else {
            toast(r.data || 'Lỗi', 'err');
        }
    });
}

function toggleAnn(id, status) {
    ajax('sitetop_admin_update_announcement', { id: id, status: status }, function(r) {
        if (r.success) { toast('Đã cập nhật!', 'ok'); loadAnnouncements(); }
        else toast(r.data || 'Lỗi', 'err');
    });
}

function deleteAnn(id) {
    if (!confirm('Xóa thông báo #' + id + '?')) return;
    ajax('sitetop_admin_delete_announcement', { id: id }, function(r) {
        if (r.success) { toast('Đã xóa!', 'ok'); loadAnnouncements(); }
        else toast(r.data || 'Lỗi', 'err');
    });
}

loadAnnouncements();
</script>
