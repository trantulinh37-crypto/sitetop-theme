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
.traffictop-menu-label{display:block;padding:10px 12px 4px!important;font-size:10px!important;font-weight:700!important;letter-spacing:.12em;color:#9ca3af!important;text-transform:uppercase;line-height:1.4!important}
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
    var labels = {'traffictop-users':'NHÀ XUẤT BẢN','traffictop-customers':'KHÁCH HÀNG','traffictop-visits':'HỆ THỐNG'};
    Object.keys(labels).forEach(function(slug){
        var li = document.querySelector('#adminmenu a[href*="page='+slug+'"]');
        if(li){
            var menuLi = li.closest('li');
            if(menuLi){
                var lbl = document.createElement('li');
                lbl.className = 'traffictop-menu-label';
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
            wpLbl.className = 'traffictop-menu-label wp-toggle-label';
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

// Advanced tab cache: localStorage + idle prefetch + version-based invalidation
// Backed by includes/admin-tab-cache.php server-side version tracking.
add_action( 'admin_footer', function() {
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'traffictop' ) === false ) return;
    $initial_versions = function_exists( 'traffictop_get_tab_versions' ) ? traffictop_get_tab_versions() : array();
?>
<script>
window.lnInitialTabVersions = <?php echo wp_json_encode( $initial_versions ); ?>;
</script>
<script>
(function(){
    var THEME_V    = '<?php echo esc_js( TRAFFICTOP_VERSION ); ?>';
    var AJAX_URL   = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
    var NONCE      = '<?php echo wp_create_nonce( 'traffictop_admin_nonce' ); ?>';
    // page slug \u2192 tab key (server-side map \u1edf admin-tab-cache.php)
    var TABS = {
        'traffictop-overview':    'overview',
        'traffictop-users':       'users',
        'traffictop-links':       'links',
        'traffictop-withdrawals': 'withdrawals',
        'traffictop-customers':   'customers',
        'traffictop-deposits':    'deposits',
        'traffictop-visits':      'visits',
        'traffictop-settings':    'settings'
    };
    // Max age (ms) cho stale-while-revalidate. null = invalidate ch\u1ec9 qua version.
    // Overview c\u00f3 stats hardcoded v\u00e0o PHP \u2192 c\u1ea7n refresh \u0111\u1ecbnh k\u1ef3.
    var MAX_AGE = {
        'traffictop-overview': 5 * 60 * 1000  // 5 ph\u00fat
    };
    var CACHE_PREFIX  = 'lnTabCache_v' + THEME_V + '_';
    var POLL_MS       = 120000;
    var VIS_THROTTLE  = 30000;
    var PREFETCH_GAP  = 400;
    var BACKOFF_MS    = 30000;
    var serverVersions = window.lnInitialTabVersions || {};
    var lastVersionsFetch = 0;
    var rateLimitedUntil = 0;

    var params = new URLSearchParams(location.search);
    var currentPage = params.get('page') || '';
    var isDefaultView = !params.get('paged') && !params.get('s') && !params.get('status') && !params.get('view');

    function safeParse(s){ try { return JSON.parse(s); } catch(e){ return null; } }
    function getCached(slug){ var raw = localStorage.getItem(CACHE_PREFIX + slug); return raw ? safeParse(raw) : null; }
    function setCached(slug, html, v){
        try { localStorage.setItem(CACHE_PREFIX + slug, JSON.stringify({h: html, v: v, t: Date.now()})); }
        catch(e) {
            // Quota exceeded \u2192 clear all our cache keys and retry once
            Object.keys(localStorage).forEach(function(k){ if (k.indexOf(CACHE_PREFIX) === 0) localStorage.removeItem(k); });
            try { localStorage.setItem(CACHE_PREFIX + slug, JSON.stringify({h: html, v: v, t: Date.now()})); } catch(_){}
        }
    }
    function clearCached(slug){ localStorage.removeItem(CACHE_PREFIX + slug); }
    function isRateLimited(){ return Date.now() < rateLimitedUntil; }
    function handleResponse(r){
        if (r.status === 429 || r.status === 503) { rateLimitedUntil = Date.now() + BACKOFF_MS; return null; }
        if (!r.ok) return null;
        return r;
    }
    function fetchVersions(){
        if (isRateLimited()) return Promise.resolve(serverVersions);
        if (Date.now() - lastVersionsFetch < 5000) return Promise.resolve(serverVersions);
        lastVersionsFetch = Date.now();
        return fetch(AJAX_URL + '?action=traffictop_admin_tab_versions&nonce=' + NONCE, {credentials:'same-origin'})
            .then(handleResponse).then(function(r){ return r ? r.json() : null; })
            .then(function(j){
                if (j && j.success) { serverVersions = j.data || {}; invalidateStale(); }
                return serverVersions;
            }).catch(function(){ return serverVersions; });
    }
    function invalidateStale(){
        Object.keys(TABS).forEach(function(slug){
            var c = getCached(slug);
            if (c && (c.v || 0) !== (serverVersions[TABS[slug]] || 0)) clearCached(slug);
        });
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
                setCached(slug, wrap.outerHTML, serverVersions[TABS[slug]] || 0);
            }).catch(function(){});
    }
    function prefetchMissing(){
        var missing = Object.keys(TABS).filter(function(slug){ return slug !== currentPage && !getCached(slug); });
        if (!missing.length) return;
        var idle = window.requestIdleCallback || function(cb){ return setTimeout(cb, 500); };
        function next(){
            if (!missing.length || isRateLimited()) return;
            var slug = missing.shift();
            fetchTabHTML(slug).then(function(){ setTimeout(function(){ idle(next); }, PREFETCH_GAP); });
        }
        idle(next);
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
    function isStale(slug, c){ if (!c) return true; var m = MAX_AGE[slug]; return m && (Date.now() - (c.t || 0) > m); }

    Object.keys(TABS).forEach(function(slug){
        var link = document.querySelector('#adminmenu a[href="admin.php?page=' + slug + '"]');
        if (!link) return;
        link.addEventListener('click', function(e){
            if (slug === currentPage && isDefaultView) { e.preventDefault(); return; }
            var c = getCached(slug);
            if (!c) return;
            var stale = isStale(slug, c);
            e.preventDefault();
            showCachedTab(slug, c.h);
            if (stale) {
                fetchTabHTML(slug);
            } else {
                fetchVersions().then(function(){
                    if ((serverVersions[TABS[slug]] || 0) !== (c.v || 0)) fetchTabHTML(slug);
                });
            }
        });
    });

    function cacheCurrentPage(){
        if (!TABS[currentPage] || !isDefaultView) return;
        var wrap = document.querySelector('#wpbody-content > .wrap');
        if (!wrap) return;
        var clone = wrap.cloneNode(true);
        clone.querySelectorAll('.notice, .updated, .error').forEach(function(n){ n.remove(); });
        setCached(currentPage, clone.outerHTML, serverVersions[TABS[currentPage]] || 0);
    }

    window.addEventListener('popstate', function(e){
        if (e.state && e.state.lnPage) { var c = getCached(e.state.lnPage); if (c) { showCachedTab(e.state.lnPage, c.h); return; } }
        location.reload();
    });
    if (TABS[currentPage] && isDefaultView) history.replaceState({lnPage: currentPage}, '');

    invalidateStale();
    cacheCurrentPage();
    setTimeout(prefetchMissing, 1000);
    setInterval(fetchVersions, POLL_MS);
    document.addEventListener('visibilitychange', function(){
        if (document.hidden) return;
        if (Date.now() - lastVersionsFetch < VIS_THROTTLE) return;
        fetchVersions();
    });
})();
</script>
<?php });
