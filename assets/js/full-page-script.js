/*!
 * SiteTop — Full Page Script
 * Đổi mọi liên kết RA NGOÀI trên trang thành liên kết rút gọn sitetop.
 *
 * Trang nhúng khai báo trước:
 *   app_url              — địa chỉ gốc sitetop, ví dụ 'https://sitetop.net/'
 *   app_api_token        — KHOÁ LIÊN KẾT NHANH (không phải API token)
 *   app_exclude_domains  — mảng domain KHÔNG đổi
 *   app_domains          — nếu khác rỗng: CHỈ đổi những domain này
 *   app_sub_link         — (tuỳ chọn) link dự phòng khi link đích hỏng
 *
 * Cách hoạt động: ghi đè href thành {app_url}st?api=KHOÁ&url=LINK-ĐÍCH.
 * Điểm /st đã có sẵn và tự dùng lại shortlink cũ cho cùng (user, url),
 * nên nhiều khách bấm cùng một link cũng chỉ sinh MỘT bản ghi.
 */
(function (w, d) {
    'use strict';

    var base = String(w.app_url || '').trim();
    var key  = String(w.app_api_token || '').trim();

    if (!base || !key) {
        if (w.console && w.console.warn) {
            w.console.warn('[SiteTop] Thiếu app_url hoặc app_api_token — script không chạy.');
        }
        return;
    }
    if (base.charAt(base.length - 1) !== '/') base += '/';

    var endpoint = base + 'st';
    var sub      = String(w.app_sub_link || '').trim();

    /* Chuẩn hoá host: bỏ chữ hoa và tiền tố www. để so sánh cho khớp. */
    function host(u) {
        try {
            var h = new URL(u, d.baseURI).hostname.toLowerCase();
            return h.replace(/^www\./, '');
        } catch (e) { return ''; }
    }

    function list(v) {
        if (!v) return [];
        if (typeof v === 'string') v = [v];
        var out = [], i, s;
        for (i = 0; i < v.length; i++) {
            s = String(v[i] || '').trim().toLowerCase().replace(/^www\./, '');
            if (s) out.push(s);
        }
        return out;
    }

    /* Khớp cả domain con: 'abc.com' khớp 'abc.com' lẫn 'tai.abc.com'. */
    function inList(h, arr) {
        for (var i = 0; i < arr.length; i++) {
            if (h === arr[i] || h.slice(-(arr[i].length + 1)) === '.' + arr[i]) return true;
        }
        return false;
    }

    var selfHost = host(base);
    var pageHost = String(location.hostname || '').toLowerCase().replace(/^www\./, '');
    var exclude  = list(w.app_exclude_domains);
    var only     = list(w.app_domains);

    /* Link nội bộ của chính trang đang nhúng — đổi là gãy điều hướng của họ. */
    function isOwn(h) {
        if (!pageHost) return false;
        return h === pageHost
            || h.slice(-(pageHost.length + 1)) === '.' + pageHost
            || pageHost.slice(-(h.length + 1)) === '.' + h;
    }

    function convert(a) {
        if (a.getAttribute('data-sitetop') !== null) return;

        var raw = a.getAttribute('href');
        if (!raw) return;

        /* Trình duyệt tự phân giải; chỉ nhận http/https, bỏ mailto/tel/#/javascript. */
        var abs = a.href;
        if (a.protocol !== 'http:' && a.protocol !== 'https:') return;

        var h = host(abs);
        if (!h || isOwn(h) || h === selfHost) return;
        if (inList(h, exclude)) return;
        if (only.length && !inList(h, only)) return;

        /* Cho phép trang tự loại trừ từng chỗ. */
        if (a.closest && a.closest('[data-no-shorten],.no-shorten')) return;

        a.setAttribute('data-sitetop', '1');
        a.href = endpoint
            + '?api=' + encodeURIComponent(key)
            + '&url=' + encodeURIComponent(abs)
            + (sub ? '&sub_link=' + encodeURIComponent(sub) : '');
    }

    function scan(root) {
        if (!root || !root.querySelectorAll) return;
        var links = root.querySelectorAll('a[href]:not([data-sitetop])'), i;
        for (i = 0; i < links.length; i++) convert(links[i]);
    }

    function start() {
        scan(d);

        /* Trang động (tải thêm bài, router SPA) vẫn phải được phủ — nhưng gom lại
           một nhịp thay vì quét mỗi lần DOM đổi, tránh ghì trang chậm đi. */
        if (!w.MutationObserver) return;
        var hen = null;
        new MutationObserver(function () {
            if (hen) return;
            hen = setTimeout(function () { hen = null; scan(d); }, 200);
        }).observe(d.documentElement, { childList: true, subtree: true });
    }

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})(window, document);
