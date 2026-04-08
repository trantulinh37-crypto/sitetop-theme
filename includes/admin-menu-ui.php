<?php
/**
 * Admin Menu UI: sidebar labels, collapsible WordPress group, tab caching
 * Tách từ functions.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Menu separator labels + collapsible WordPress group
add_action( 'admin_head', function() { ?>
<style>
.linkngon-menu-label{display:block;padding:10px 12px 4px!important;font-size:10px!important;font-weight:700!important;letter-spacing:.12em;color:#9ca3af!important;text-transform:uppercase;line-height:1.4!important}
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
    var labels = {'linkngon-users':'NHÀ XUẤT BẢN','linkngon-customers':'KHÁCH HÀNG','linkngon-visits':'HỆ THỐNG'};
    Object.keys(labels).forEach(function(slug){
        var li = document.querySelector('#adminmenu a[href*="page='+slug+'"]');
        if(li){
            var menuLi = li.closest('li');
            if(menuLi){
                var lbl = document.createElement('li');
                lbl.className = 'linkngon-menu-label';
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
            wpLbl.className = 'linkngon-menu-label wp-toggle-label';
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

// Tab caching: cache shortlinks, users, visits, customers tabs client-side
add_action( 'admin_footer', function() {
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'linkngon' ) === false ) return;
?>
<script>
(function(){
    var CACHEABLE = ['linkngon-links','linkngon-users','linkngon-visits','linkngon-customers'];
    var AJAX_URL = '<?php echo admin_url("admin-ajax.php"); ?>';
    var NONCE = '<?php echo wp_create_nonce("linkngon_admin_nonce"); ?>';
    var cache = {};
    var params = new URLSearchParams(window.location.search);
    var currentPage = params.get('page') || '';

    // Only cache default view (no pagination, search, or status filters)
    var isDefaultView = !params.get('paged') && !params.get('s') && !params.get('status');
    if (CACHEABLE.indexOf(currentPage) !== -1 && isDefaultView) {
        var wrap = document.querySelector('#wpbody-content > .wrap');
        if (wrap) {
            var clone = wrap.cloneNode(true);
            clone.querySelectorAll('.notice,.updated').forEach(function(n){ n.remove(); });
            cache[currentPage] = clone.outerHTML;
        }
    }

    // Map page slugs to AJAX tab names
    var TAB_MAP = {
        'linkngon-links': 'links',
        'linkngon-users': 'users',
        'linkngon-visits': 'visits',
        'linkngon-customers': 'customers'
    };

    // Find and intercept menu links for cacheable tabs
    CACHEABLE.forEach(function(slug) {
        var link = document.querySelector('#adminmenu a[href="admin.php?page=' + slug + '"]');
        if (!link) return;
        link.addEventListener('click', function(e) {
            if (slug === currentPage && isDefaultView) { e.preventDefault(); return; }
            if (cache[slug]) {
                e.preventDefault();
                showCachedTab(slug);
            }
        });
    });

    function showCachedTab(slug) {
        var wrap = document.querySelector('#wpbody-content > .wrap');
        if (!wrap) return;

        // Replace content
        var temp = document.createElement('div');
        temp.innerHTML = cache[slug];
        var newWrap = temp.firstElementChild;
        wrap.parentNode.replaceChild(newWrap, wrap);

        // Re-execute inline scripts
        newWrap.querySelectorAll('script').forEach(function(old) {
            var s = document.createElement('script');
            s.textContent = old.textContent;
            old.parentNode.replaceChild(s, old);
        });

        // Update URL
        history.pushState({ lnPage: slug }, '', 'admin.php?page=' + slug);
        currentPage = slug;
        isDefaultView = true;

        // Update active menu
        updateMenu(slug);

        // Update page title
        var h1 = newWrap.querySelector('h1');
        if (h1) document.title = h1.textContent + ' \u2039 ' + (document.title.split('\u2039').slice(1).join('\u2039') || 'WordPress');
    }

    function updateMenu(slug) {
        // Remove current from all menu items
        document.querySelectorAll('#adminmenu li.current').forEach(function(li) {
            li.classList.remove('current');
        });
        document.querySelectorAll('#adminmenu .wp-has-current-submenu').forEach(function(el) {
            el.classList.remove('wp-has-current-submenu', 'wp-menu-open');
            el.classList.add('wp-not-current-submenu');
        });
        document.querySelectorAll('#adminmenu a.current').forEach(function(a) {
            a.classList.remove('current');
            a.removeAttribute('aria-current');
        });

        // Set target as current
        var targetLink = document.querySelector('#adminmenu a[href="admin.php?page=' + slug + '"]');
        if (targetLink) {
            targetLink.classList.add('current');
            targetLink.setAttribute('aria-current', 'page');
            var li = targetLink.closest('li.menu-top');
            if (li) {
                li.classList.add('current', 'wp-has-current-submenu', 'wp-menu-open');
                li.classList.remove('wp-not-current-submenu');
            }
        }
    }

    // Handle browser back/forward
    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.lnPage && cache[e.state.lnPage]) {
            showCachedTab(e.state.lnPage);
        } else {
            location.reload();
        }
    });

    // Store initial state for back/forward
    if (CACHEABLE.indexOf(currentPage) !== -1 && isDefaultView) {
        history.replaceState({ lnPage: currentPage }, '');
    }
})();
</script>
<?php });
