<?php
/**
 * Admin Menu UI: sidebar labels, collapsible WordPress group, tab caching
 * Tách từ functions.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Menu separator labels + collapsible WordPress group
add_action( 'admin_head', function() { ?>
<meta name="format-detection" content="telephone=no">
<style>
.sitetop-menu-label{display:block;padding:10px 12px 4px!important;font-size:10px!important;font-weight:700!important;letter-spacing:.12em;color:#9ca3af!important;text-transform:uppercase;line-height:1.4!important}
#collapse-menu,#wp-admin-bar-comments,#wp-admin-bar-new-content,#wp-admin-bar-wp-logo,#wp-admin-bar-updates{display:none!important}
.wp-toggle-label{cursor:pointer;user-select:none}
.wp-toggle-label:after{content:' ▸';font-size:9px}
.wp-toggle-label.wp-open:after{content:' ▾'}
.wp-menu-hidden{display:none!important}
.search-box{display:flex;gap:6px;align-items:center;margin:0!important}
.search-box input[type="search"]{flex:1;min-width:0}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
    var labels = {'sitetop-users':'NHÀ XUẤT BẢN','sitetop-customers':'KHÁCH HÀNG','sitetop-visits':'HỆ THỐNG'};
    Object.keys(labels).forEach(function(slug){
        var li = document.querySelector('#adminmenu a[href*="page='+slug+'"]');
        if(li){
            var menuLi = li.closest('li');
            if(menuLi){
                var lbl = document.createElement('li');
                lbl.className = 'sitetop-menu-label';
                lbl.textContent = labels[slug];
                menuLi.parentNode.insertBefore(lbl, menuLi);
            }
        }
    });
    // Label cho nhóm WordPress mặc định (collapsible)
    var wpFirst = document.querySelector('#adminmenu a[href="upload.php"]');
    if(wpFirst){
        var wpLi = wpFirst.closest('li');
        if(wpLi){
            var wpLbl = document.createElement('li');
            wpLbl.className = 'sitetop-menu-label wp-toggle-label';
            wpLbl.textContent = 'WORDPRESS';
            wpLi.parentNode.insertBefore(wpLbl, wpLi);
            // Collect all WP menu items after the label
            var wpItems = [];
            var next = wpLbl.nextElementSibling;
            while(next){ wpItems.push(next); next = next.nextElementSibling; }
            // Start collapsed
            wpItems.forEach(function(el){ el.classList.add('wp-menu-hidden'); });
            // Check if current page is a WP menu item
            var isWpPage = wpItems.some(function(el){ return el.classList.contains('current'); });
            if(isWpPage){
                wpItems.forEach(function(el){ el.classList.remove('wp-menu-hidden'); });
                wpLbl.classList.add('wp-open');
            }
            wpLbl.addEventListener('click', function(){
                var hidden = wpItems[0] && wpItems[0].classList.contains('wp-menu-hidden');
                wpItems.forEach(function(el){ el.classList.toggle('wp-menu-hidden', !hidden); });
                wpLbl.classList.toggle('wp-open', hidden);
            });
        }
    }
});
</script>
<?php });

// Tab cache \u2014 CH\u1ec8 tab Visits (02/07/2026: b\u1ecf cache to\u00e0n b\u1ed9 backend theo y\u00eau c\u1ea7u admin,
// s\u1ed1 li\u1ec7u c\u00e1c tab kh\u00e1c ph\u1ea3i lu\u00f4n t\u01b0\u01a1i; Visits l\u00e0 trang n\u1eb7ng nh\u1ea5t n\u00ean gi\u1eef cache SWR:
// hi\u1ec7n b\u1ea3n cache ngay, lu\u00f4n fetch n\u1ec1n b\u1ea3n m\u1edbi cho l\u1ea7n xem sau. Kh\u00f4ng c\u00f2n version
// tracking/polling \u2014 admin-tab-cache.php \u0111\u00e3 g\u1ee1 kh\u1ecfi functions.php).
add_action( 'admin_footer', function() {
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'sitetop' ) === false ) return;
?>
<script>
(function(){
    var THEME_V    = '<?php echo esc_js( SITETOP_VERSION ); ?>';
    // Ch\u1ec9 cache tab Visits \u2014 c\u00e1c tab kh\u00e1c click = load trang th\u01b0\u1eddng, d\u1eef li\u1ec7u lu\u00f4n m\u1edbi.
    var TABS = {
        'sitetop-visits': 'visits'
    };
    var CACHE_PREFIX  = 'lnTabCache_v' + THEME_V + '_';
    var BACKOFF_MS    = 30000;
    var rateLimitedUntil = 0;

    var params = new URLSearchParams(location.search);
    var currentPage = params.get('page') || '';
    var isDefaultView = !params.get('paged') && !params.get('s') && !params.get('status') && !params.get('view');

    // D\u1ecdn cache c\u0169: x\u00f3a m\u1ecdi key lnTabCache_* kh\u00f4ng thu\u1ed9c TABS (c\u00e1c tab backend \u0111\u00e3 b\u1ecf cache
    // + key c\u1ee7a theme version c\u0169) \u0111\u1ec3 browser admin kh\u00f4ng gi\u1eef HTML stale.
    Object.keys(localStorage).forEach(function(k){
        if (k.indexOf('lnTabCache_') !== 0) return;
        var keep = Object.keys(TABS).some(function(slug){ return k === CACHE_PREFIX + slug; });
        if (!keep) localStorage.removeItem(k);
    });

    function safeParse(s){ try { return JSON.parse(s); } catch(e){ return null; } }
    function getCached(slug){ var raw = localStorage.getItem(CACHE_PREFIX + slug); return raw ? safeParse(raw) : null; }
    function setCached(slug, html){
        try { localStorage.setItem(CACHE_PREFIX + slug, JSON.stringify({h: html, t: Date.now()})); }
        catch(e) {
            // Quota exceeded \u2192 clear all our cache keys and retry once
            Object.keys(localStorage).forEach(function(k){ if (k.indexOf(CACHE_PREFIX) === 0) localStorage.removeItem(k); });
            try { localStorage.setItem(CACHE_PREFIX + slug, JSON.stringify({h: html, t: Date.now()})); } catch(_){}
        }
    }
    function isRateLimited(){ return Date.now() < rateLimitedUntil; }
    function handleResponse(r){
        if (r.status === 429 || r.status === 503) { rateLimitedUntil = Date.now() + BACKOFF_MS; return null; }
        if (!r.ok) return null;
        return r;
    }
    function fetchTabHTML(slug){
        if (isRateLimited()) return Promise.resolve();
        return fetch('admin.php?page=' + slug, {credentials:'same-origin'})
            .then(handleResponse).then(function(r){ return r ? r.text() : null; })
            .then(function(html){
                if (!html) return;
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var wrap = doc.querySelector('#wpbody-content > .wrap');
                if (!wrap) return;
                // Safety net: pull orphan <style>/<script src> siblings v\u00e0o trong .wrap
                // (vd: tab-settings c\u00f3 <style> tr\u01b0\u1edbc .wrap \u2192 m\u1ea5t khi swap)
                var body = doc.querySelector('#wpbody-content') || doc.body;
                if (body) {
                    var orphans = [];
                    body.childNodes.forEach(function(n){
                        if (n.nodeType === 1 && (n.tagName === 'STYLE' || (n.tagName === 'SCRIPT' && n.src)) && n !== wrap) {
                            orphans.push(n);
                        }
                    });
                    orphans.reverse().forEach(function(n){ wrap.insertBefore(n, wrap.firstChild); });
                }
                wrap.querySelectorAll('.notice, .updated, .error').forEach(function(n){ n.remove(); });
                setCached(slug, wrap.outerHTML);
            }).catch(function(){});
    }
    function showCachedTab(slug, html){
        var wrap = document.querySelector('#wpbody-content > .wrap');
        if (!wrap) return;
        var temp = document.createElement('div');
        temp.innerHTML = html;
        var newWrap = temp.firstElementChild;
        if (!newWrap) return;
        wrap.parentNode.replaceChild(newWrap, wrap);
        // Re-execute inline scripts (DOM swap kh\u00f4ng t\u1ef1 ch\u1ea1y)
        newWrap.querySelectorAll('script').forEach(function(old){
            var s = document.createElement('script');
            for (var i = 0; i < old.attributes.length; i++) s.setAttribute(old.attributes[i].name, old.attributes[i].value);
            s.textContent = old.textContent;
            old.parentNode.replaceChild(s, old);
        });
        history.pushState({lnPage: slug}, '', 'admin.php?page=' + slug);
        currentPage = slug;
        isDefaultView = true;
        updateMenu(slug);
        var h1 = newWrap.querySelector('h1');
        if (h1) { var tail = document.title.split('\u2039').slice(1).join('\u2039') || 'WordPress'; document.title = h1.textContent + ' \u2039 ' + tail; }
    }
    function updateMenu(slug){
        document.querySelectorAll('#adminmenu li.current').forEach(function(li){ li.classList.remove('current'); });
        document.querySelectorAll('#adminmenu .wp-has-current-submenu').forEach(function(el){
            el.classList.remove('wp-has-current-submenu', 'wp-menu-open');
            el.classList.add('wp-not-current-submenu');
        });
        document.querySelectorAll('#adminmenu a.current').forEach(function(a){ a.classList.remove('current'); a.removeAttribute('aria-current'); });
        var link = document.querySelector('#adminmenu a[href="admin.php?page=' + slug + '"]');
        if (link) {
            link.classList.add('current'); link.setAttribute('aria-current', 'page');
            var li = link.closest('li.menu-top');
            if (li) { li.classList.add('current', 'wp-has-current-submenu', 'wp-menu-open'); li.classList.remove('wp-not-current-submenu'); }
        }
    }
    Object.keys(TABS).forEach(function(slug){
        var link = document.querySelector('#adminmenu a[href="admin.php?page=' + slug + '"]');
        if (!link) return;
        link.addEventListener('click', function(e){
            if (slug === currentPage && isDefaultView) { e.preventDefault(); return; }
            var c = getCached(slug);
            if (!c) return;
            e.preventDefault();
            showCachedTab(slug, c.h);
            // SWR: luôn fetch nền bản mới để lần xem sau có dữ liệu tươi.
            fetchTabHTML(slug);
        });
    });

    function cacheCurrentPage(){
        if (!TABS[currentPage] || !isDefaultView) return;
        var wrap = document.querySelector('#wpbody-content > .wrap');
        if (!wrap) return;
        var clone = wrap.cloneNode(true);
        clone.querySelectorAll('.notice, .updated, .error').forEach(function(n){ n.remove(); });
        setCached(currentPage, clone.outerHTML);
    }

    window.addEventListener('popstate', function(e){
        if (e.state && e.state.lnPage) { var c = getCached(e.state.lnPage); if (c) { showCachedTab(e.state.lnPage, c.h); return; } }
        location.reload();
    });
    if (TABS[currentPage] && isDefaultView) history.replaceState({lnPage: currentPage}, '');

    cacheCurrentPage();
})();
</script>
<?php });
