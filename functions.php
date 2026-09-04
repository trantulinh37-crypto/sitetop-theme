<?php
/**
 * SiteTop.net V2 - Theme Functions
 * Hệ thống rút gọn link kiếm tiền
 * 
 * Mapped from CLAUDE.md: prefix taskify_ → sitetop_
 * Traffic: keyword (1step/2step/nocode) + direct (bỏ social)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SITETOP_VERSION', '2.6.2' );
define( 'SITETOP_DIR', get_template_directory() );
define( 'SITETOP_URL', get_template_directory_uri() );
define( 'SITETOP_PREFIX', 'sitetop_' );
// Đổi mỗi lần thay file logo. Ảnh logo bị Cloudflare cache 7 ngày (max-age=604800)
// nên ghi đè file thôi là user vẫn thấy logo cũ — ?v= đổi cache key để ăn ngay.
define( 'SITETOP_LOGO_VER', '20260808' );
// Bàn giao nhiệm vụ (bấm Copy URL đích) có hiệu lực bao lâu. User đọc trang nhiệm vụ
// rồi mở tab mới dán URL — 15 phút là thoải mái, onsite chỉ 70–150s.
define( 'SITETOP_HANDOFF_TTL', 15 * MINUTE_IN_SECONDS );
/* Vắng nhịp hiện diện quá ngần này giây = user đã rời hẳn website → đếm lại từ
   đầu. Nhịp gửi 10 giây/lần, nên 30 giây là bỏ lỡ 3 nhịp — đủ rộng để chuyển
   URL nội bộ hay mạng chập chờn không bị tính oan. */
/* 90 giây, KHÔNG phải 30. Nhịp gửi 10 giây/lần, nhưng trình duyệt bóp nhịp của
   tab đang ẩn xuống còn khoảng 1 lần/phút — để ngưỡng 30 giây thì mọi tab bị ẩn
   một lát đều bị tính là bỏ đi và xoá sạch tiến trình, dù user vẫn đang làm.
   Tín hiệu chính xác là pagehide (SITETOP_AWAY_GAP), mốc này chỉ là lưới đỡ. */
define( 'SITETOP_PRESENCE_GAP', 90 );
/* Rời trang quá ngần này giây thì coi như đã đi hẳn — đếm lại và bắt xác minh.
   Mốc này dùng tín hiệu pagehide của trình duyệt nên chính xác tới từng giây,
   khác SITETOP_PRESENCE_GAP vốn phải nới rộng vì nhịp chỉ gửi 10 giây/lần. */
define( 'SITETOP_AWAY_GAP', 10 );
/* Mỗi IP chỉ được bấm "Báo lỗi mã" một lần trong ngần này giây. Khoá theo IP,
   không theo nhiệm vụ hay từ khoá — đổi camp hay tải lại trang không gỡ được. */
define( 'SITETOP_REPORT_GAP', 5 * MINUTE_IN_SECONDS );

/* Khoá vì dùng trình duyệt ẨN DANH — 30 phút, nhẹ hơn hẳn 24 giờ của proxy/VPN.
   Ẩn danh chỉ là lách luật, không phải gian lận có tổ chức; và bộ nhận diện ẩn danh
   là suy đoán nên có thể bắt nhầm — phạt nặng người bị nhầm là không đáng.
   Proxy, fake IP, 1.1.1.1 vẫn giữ 24 giờ, KHÔNG dùng hằng số này. */
define( 'SITETOP_ANDANH_BLOCK_MINUTES', 30 );

// Disable external wp-cron.php hits (prevents DDoS abuse via cron endpoint)
// WordPress will run cron internally on page loads instead
if ( ! defined( 'DISABLE_WP_CRON' ) ) {
    define( 'DISABLE_WP_CRON', true );
}

/* ============================================================
   WIDGET.JS - Serve widget khi request match
   Cách 1: /?sitetop_widget=js (luôn hoạt động)
   Cách 2: /widget.js (cần .htaccess rewrite)
   ============================================================ */
/* Phục vụ widget SỚM ─ 22/08/2026
   ------------------------------------------------------------------
   /top.js và /widget.js chỉ cần: option của theme, $wpdb, và các hàm trong
   includes/ (đã nạp xong ở cuối functions.php). Chúng KHÔNG cần gì từ chuỗi
   hook 'init' — nơi WordPress core và mọi plugin đăng ký post type, taxonomy,
   rewrite, widget... Chạy ở 'after_setup_theme' cắt được toàn bộ phần đó khỏi
   đường phục vụ widget, tức nút trên web khách hiện sớm hơn.

   Bản xử lý ở 'init' bên dưới GIỮ NGUYÊN làm lưới an toàn: nếu vì lý do nào đó
   hàm chưa sẵn sàng ở đây, request vẫn đi tiếp và được phục vụ như cũ. */
add_action( 'after_setup_theme', function() {
    if ( ! function_exists( 'sitetop_serve_widget_js' ) ) return;
    $uri = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    $is_widget = ( $uri === 'widget.js' || $uri === 'top.js' )
        || ( isset( $_GET['sitetop_widget'] ) && $_GET['sitetop_widget'] === 'js' );
    if ( $is_widget ) {
        sitetop_serve_widget_js(); // hàm này tự exit sau khi xuất JS
    }
}, 99 );

/* ============================================================
   FULL PAGE SCRIPT — /js/full-page-script.js
   Đoạn mã Cách 3 trong tab API. Không có nội dung động (mọi cấu hình
   do TRANG NHÚNG khai báo), nên chỉ là file .js tĩnh trong theme được
   phục vụ ở đường dẫn /js/ — docroot production không sửa được từ đây,
   deploy chỉ đẩy thư mục theme.

   Phục vụ SỚM ở after_setup_theme vì file không cần gì từ chuỗi 'init'.
   ============================================================ */
function sitetop_serve_full_page_script() {
    $file = SITETOP_DIR . '/assets/js/full-page-script.js';
    if ( ! is_readable( $file ) ) {
        status_header( 404 );
        header( 'Content-Type: application/javascript; charset=UTF-8' );
        echo '/* SiteTop: thiếu full-page-script.js */';
        exit;
    }

    $js   = file_get_contents( $file );
    $etag = '"' . md5( $js ) . '"';

    header( 'Content-Type: application/javascript; charset=UTF-8' );
    header( 'Access-Control-Allow-Origin: *' );
    /* `private` BẮT BUỘC — giống widget.js: bỏ ra là Cloudflare tự áp Browser Cache
       TTL của nó và web khách ôm bản cũ hàng giờ. `no-cache` vẫn cho trình duyệt giữ
       bản sao nhưng bắt hỏi lại server mỗi lần → nhận 304 vài trăm byte nếu không đổi. */
    header( 'Cache-Control: private, no-cache, must-revalidate' );
    header( 'ETag: ' . $etag );

    /* So ETag phải bỏ tiền tố W/ (ETag yếu): LiteSpeed/Cloudflare nén xong là đổi ETag
       mạnh thành yếu, trình duyệt gửi lại đúng bản yếu đó — so thẳng chuỗi thì không bao
       giờ khớp. (Đo 01/09/2026: widget.js đang dính đúng lỗi này, trả về nguyên 93KB mỗi
       lần thay vì 304.) */
    $inm = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( $_SERVER['HTTP_IF_NONE_MATCH'] ) : '';
    if ( strpos( $inm, 'W/' ) === 0 ) $inm = substr( $inm, 2 );
    if ( $inm !== '' && $inm === $etag ) {
        status_header( 304 );
        exit;
    }

    echo $js;
    exit;
}

add_action( 'after_setup_theme', function() {
    $uri = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    if ( $uri === 'js/full-page-script.js' ) {
        sitetop_serve_full_page_script();
    }
}, 99 );


add_action( 'init', function() {
    // Query param: /?sitetop_widget=js
    if ( isset( $_GET['sitetop_widget'] ) && $_GET['sitetop_widget'] === 'js' ) {
        sitetop_serve_widget_js();
    }
    // Direct URI: /widget.js (when .htaccess passes to WP)
    $uri = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    if ( $uri === 'widget.js' || $uri === 'top.js' ) { // /top.js: alias NGẮN cho mã nhúng camp mới; /widget.js giữ nguyên cho mã cũ.
        sitetop_serve_widget_js();
    }
    // /widget-captcha/ → serve captcha iframe
    if ( $uri === 'widget-captcha' || strpos( $uri, 'widget-captcha' ) === 0 ) {
        include SITETOP_DIR . '/page-widget-captcha.php';
    }
    // /widget-bridge/ → cấp session_id (localStorage + cookie sitetop_sid) cho widget của
    // SITE NGUỒN nhúng trên trang đích — thiếu endpoint này thì widget nguồn không bao giờ khớp
    // được phiên sitetop bằng session (chỉ còn IP) → mã dễ bị hệ thống khác cấp nhầm.
    if ( $uri === 'widget-bridge' || strpos( $uri, 'widget-bridge' ) === 0 ) {
        include SITETOP_DIR . '/page-widget-bridge.php';
        exit;
    }
}, 0 );

/* ============================================================
   LOGO - One-time migration: trỏ logo widget/brand sang ảnh
   trong theme. Đổi $ver nếu thay ảnh lần nữa (giữ nguyên $ver thì
   admin vẫn đổi được logo qua Cài đặt TT → Icon URL bình thường).
   ============================================================ */
add_action( 'init', function() {
    $ver = 'sitetop-logo-20260808b';
    if ( get_option( 'sitetop_logo_version' ) !== $ver ) {
        update_option( 'sitetop_widget_icon', sitetop_logo_url( 'sitetop-logo.png' ) );
        update_option( 'sitetop_logo_version', $ver );
    }
} );

/* Mốc bật chốt bàn giao nhiệm vụ. Ghi 1 lần ở request đầu sau khi deploy; visit tạo
   TRƯỚC mốc này được miễn chốt để không cắt ngang người đang làm dở. */
add_action( 'init', function() {
    if ( ! get_option( 'sitetop_handoff_gate_since' ) ) {
        update_option( 'sitetop_handoff_gate_since', time() );
    }
} );

/* ============================================================
   CAMPAIGN — NHIỀU URL ĐÍCH
   destination_urls lưu JSON mảng: ["https://a.com/x","https://b.com/y"].
   URL đầu tiên đồng thời ghi vào target_url để mọi chỗ hiển thị/thống kê/email
   đang đọc target_url vẫn chạy như cũ, không phải sửa 78 chỗ.
   Xác thực: URL hiện tại phải TRÙNG một trong các URL đã thêm (so cả domain lẫn
   đường dẫn, bỏ qua www / '/' cuối / query). Vào trang khác cùng domain vẫn báo lỗi.
   ============================================================ */

/**
 * Referer này có đúng là GOOGLE.COM không.
 *
 * Chủ site chốt (15/08/2026): camp từ khoá chỉ nhận google.com và google.com.vn — tức
 * tìm từ khoá thẳng trên Google Chrome. Ngoài ra không nhận:
 *   - công cụ khác: Cốc Cốc, Bing, Yahoo, DuckDuckGo, Yandex, Brave, Baidu…
 *   - tên miền Google của nước khác: google.co.uk, google.de, google.co.jp…
 * Chrome gõ từ khoá ở thanh địa chỉ đi tới www.google.com/search nên vẫn tính.
 *
 * So khớp THEO ĐUÔI với dấu chấm phía trước, nên "google.com.evil.net" không qua mặt
 * được — ai cũng mua được evil.net rồi tạo tên miền con đó để giả làm Google.
 *
 * @param string $host Host của referer.
 * @return bool
 */
function sitetop_is_google_referer( $host ) {
    $host = strtolower( trim( (string) $host ) );
    if ( $host === '' ) return false;

    /* Thanh tìm kiếm của Chrome / ứng dụng Google trên Android không cho referer là
       trang web, mà là địa chỉ ứng dụng dạng android-app://<tên gói>. Gõ từ khoá ở
       thanh đó rồi bấm kết quả là đi đúng đường Google, nên phải tính. */
    $apps = array(
        'com.google.android.googlequicksearchbox', // ứng dụng Google / ô tìm kiếm màn hình chính
        'com.android.chrome',                      // Chrome
        'com.chrome.beta', 'com.chrome.dev', 'com.chrome.canary',
    );
    if ( in_array( $host, $apps, true ) ) return true;

    foreach ( array( 'google.com', 'google.com.vn' ) as $g ) {
        if ( $host === $g || substr( $host, - ( strlen( $g ) + 1 ) ) === '.' . $g ) return true;
    }
    return false;
}

/** Host của URL, bỏ www, hạ chữ thường. */
function sitetop_host_of( $url ) {
    $host = parse_url( (string) $url, PHP_URL_HOST );
    return $host ? preg_replace( '/^www\./', '', strtolower( $host ) ) : '';
}

/** Danh sách URL đích của camp. Camp cũ chưa có mảng thì rơi về target_url. */
function sitetop_campaign_destinations( $campaign ) {
    $raw = is_object( $campaign ) ? ( $campaign->destination_urls ?? '' ) : '';
    $list = array();
    if ( $raw ) {
        $decoded = json_decode( (string) $raw, true );
        if ( is_array( $decoded ) ) {
            foreach ( $decoded as $u ) {
                $u = trim( (string) $u );
                if ( $u !== '' ) $list[] = $u;
            }
        }
    }
    if ( ! $list ) {
        $main = trim( (string) ( $campaign->target_url ?? '' ) );
        if ( $main !== '' ) $list[] = $main;
    }
    return $list;
}

/** Chuẩn hoá URL để so khớp: host (bỏ www) + path (bỏ '/' cuối). Bỏ qua query/hash. */
function sitetop_url_key( $url ) {
    $url = sitetop_clean_url_text( $url );
    $host = sitetop_host_of( $url );
    if ( $host === '' ) return '';
    $path = (string) parse_url( (string) $url, PHP_URL_PATH );
    // Chỉ gọt ĐUÔI path: khoảng trắng thật, %20 (dấu cách đã mã hoá) và dấu '/'.
    // URL trong DB dính dấu cách/xuống dòng thừa làm khoá lệch đúng 1 ký tự VÔ HÌNH —
    // nhìn hai URL y hệt nhau mà so vẫn false, user bị báo "sai URL" không hiểu vì sao.
    // KHÔNG đụng %20 nằm giữa đường dẫn: đó là ký tự có nghĩa của URL.
    $path = preg_replace( '/(?:%20|\s|\/)+$/i', '', $path );
    if ( $path === '' ) $path = '/';
    return $host . strtolower( $path );
}

/**
 * Bỏ ký tự vô hình quanh URL trước khi phân tích.
 *
 * Dán URL từ Word/Zalo/Excel hay kéo theo khoảng trắng không ngắt (U+00A0), ký tự
 * rộng-0 (U+200B–U+200D, U+FEFF) hoặc xuống dòng. parse_url() gặp chúng thì trả host
 * rỗng hoặc dính vào path, khiến so khớp hỏng mà nhìn bằng mắt không thấy gì sai.
 *
 * @param string $url
 * @return string
 */
function sitetop_clean_url_text( $url ) {
    $url = (string) $url;
    $url = str_replace( array( "\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBB\xBF" ), ' ', $url );
    return trim( $url );
}

/**
 * URL hiện tại có TRÙNG một trong các URL đích đã thêm không.
 * So cả domain LẪN đường dẫn: vào đúng URL đã thêm mới cho lấy mã, vào trang
 * khác cùng domain vẫn báo lỗi.
 */
function sitetop_campaign_allows_url( $campaign, $current_url ) {
    $key = sitetop_url_key( $current_url );
    if ( $key === '' ) return false;
    foreach ( sitetop_campaign_destinations( $campaign ) as $u ) {
        if ( sitetop_url_key( $u ) === $key ) return true;
    }
    return false;
}

/** Path của URL đích thuộc domain đang xét — dùng để hiển thị/ghi log, không dùng để chặn. */
function sitetop_normalize_dest_path( $campaign, $domain ) {
    foreach ( sitetop_campaign_destinations( $campaign ) as $u ) {
        if ( sitetop_host_of( $u ) === $domain ) {
            return rtrim( parse_url( $u, PHP_URL_PATH ) ?: '/', '/' );
        }
    }
    return '/';
}

/**
 * Chuẩn hoá danh sách URL người dùng nhập → mảng sạch để lưu.
 * Bỏ dòng rỗng, bỏ URL sai định dạng, bỏ trùng (so theo cả URL đầy đủ).
 * Trả array('urls'=>[], 'error'=>'') — error khác rỗng thì đừng lưu.
 */
function sitetop_sanitize_destination_urls( $input ) {
    if ( ! is_array( $input ) ) $input = array( $input );
    $urls = array();
    foreach ( $input as $raw ) {
        $u = trim( (string) $raw );
        if ( $u === '' ) continue;                       // dòng để trống thì bỏ qua, không báo lỗi
        $u = esc_url_raw( $u );
        $scheme = $u ? strtolower( (string) parse_url( $u, PHP_URL_SCHEME ) ) : '';
        $host   = sitetop_host_of( $u );
        // esc_url_raw tự thêm http:// nên "abcxyz" thành "http://abcxyz" và lọt qua
        // FILTER_VALIDATE_URL. Bắt buộc host phải có dấu chấm (domain thật) mới nhận,
        // không thì một lỗi gõ phím trở thành domain được chấp nhận lấy mã.
        if ( ! $u || ! filter_var( $u, FILTER_VALIDATE_URL )
             || ! in_array( $scheme, array( 'http', 'https' ), true )
             || $host === '' || strpos( $host, '.' ) === false
             || ! preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/i', $host ) ) {
            return array( 'urls' => array(), 'error' => 'URL không hợp lệ: ' . esc_html( substr( (string) $raw, 0, 80 ) ) );
        }
        if ( ! in_array( $u, $urls, true ) ) $urls[] = $u;
    }
    if ( ! $urls ) return array( 'urls' => array(), 'error' => 'Vui lòng nhập ít nhất 1 URL đích' );
    if ( count( $urls ) > 20 ) return array( 'urls' => array(), 'error' => 'Tối đa 20 URL đích' );
    return array( 'urls' => $urls, 'error' => '' );
}

/** URL ảnh logo kèm ?v= chống cache CDN. Dùng cho mọi chỗ trỏ tới assets/img logo. */
/* ============================================================
   CHỐT KỲ CHO LỆNH RÚT TIỀN (31/08/2026)
   Thêm period_start / period_end vào bảng withdrawals.

   VÌ SAO CẦN: trước đây kỳ được SUY RA lúc xem chi tiết, lấy lệnh rút liền trước
   theo status IN ('completed','approved','pending','cancelled'). Nhưng còn hai
   trạng thái nữa là 'rejected' và 'refunded' KHÔNG nằm trong danh sách đó. Nên mỗi
   lần admin từ chối một lệnh, kỳ của lệnh KẾ TIẾP tự nới rộng ra, nuốt luôn các lượt
   vốn thuộc lệnh bị từ chối — số liệu soi gian lận của lệnh sau bị thổi phồng và
   mức rủi ro chấm sai theo.

   Chốt cứng hai mốc lúc tạo lệnh thì kỳ không đổi nữa dù trạng thái về sau ra sao.
   Bù dữ liệu cũ bằng đúng phép suy ra hiện hành để các lệnh đã có vẫn xem được.
   ============================================================ */
add_action( 'init', function() {
    if ( get_option( 'sitetop_migration_wd_period_v1' ) ) return;

    global $wpdb;
    $p     = $wpdb->prefix . 'sitetop_';
    $table = $p . 'withdrawals';

    $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
    if ( ! $exists ) { update_option( 'sitetop_migration_wd_period_v1', time() ); return; }

    $wpdb->hide_errors();
    $co = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
    if ( ! in_array( 'period_start', $co, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN period_start DATETIME NULL DEFAULT NULL" );
    }
    if ( ! in_array( 'period_end', $co, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN period_end DATETIME NULL DEFAULT NULL" );
    }
    $co = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
    if ( ! in_array( 'period_start', $co, true ) || ! in_array( 'period_end', $co, true ) ) {
        $wpdb->show_errors();
        return; // ALTER hỏng (thiếu quyền) — KHÔNG đặt cờ, lần sau thử lại
    }

    /* Bù dữ liệu cũ. period_end = created_at. period_start = created_at của lệnh liền
       trước CÙNG USER (mọi trạng thái), không có thì lấy lượt truy cập đầu tiên. */
    $wpdb->query( "UPDATE {$table} SET period_end = created_at WHERE period_end IS NULL" );
    $wpdb->query(
        "UPDATE {$table} w SET w.period_start = COALESCE(
            (SELECT MAX(p.created_at) FROM (SELECT id,user_id,created_at FROM {$table}) p
              WHERE p.user_id = w.user_id AND p.id < w.id),
            (SELECT MIN(v.created_at) FROM {$p}shortlink_visits v WHERE v.user_id = w.user_id)
         ) WHERE w.period_start IS NULL"
    );
    $wpdb->show_errors();
    update_option( 'sitetop_migration_wd_period_v1', time() );
}, 21 );

/* ============================================================
   MIGRATION — dấu vết xoá link của user  (01/09/2026)
   ------------------------------------------------------------
   User được tự xoá link của mình, nhưng xoá MỀM: bản ghi ở lại
   nguyên vẹn cùng toàn bộ lượt truy cập và tiền đã kiếm, vì đó là
   chứng từ đối soát của admin. Chỉ đổi status thành 'deleted' —
   đúng giá trị admin vẫn dùng sẵn ở tab Shortlink.

   Hai cột này để admin phân biệt được AI xoá và xoá LÚC NÀO; thiếu
   chúng thì link user tự xoá lẫn với link admin xoá, không truy ra được.
   ============================================================ */
add_action( 'init', function() {
    if ( get_option( 'sitetop_migration_sl_deleted_v1' ) ) return;

    global $wpdb;
    $table = $wpdb->prefix . 'sitetop_user_shortlinks';

    $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
    if ( ! $exists ) { update_option( 'sitetop_migration_sl_deleted_v1', time() ); return; }

    $wpdb->hide_errors();
    $co = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
    if ( ! in_array( 'deleted_at', $co, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL" );
    }
    if ( ! in_array( 'deleted_by', $co, true ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN deleted_by BIGINT(20) UNSIGNED NULL DEFAULT NULL" );
    }
    $co = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
    $wpdb->show_errors();
    if ( ! in_array( 'deleted_at', $co, true ) || ! in_array( 'deleted_by', $co, true ) ) {
        return; // ALTER hỏng (thiếu quyền) — KHÔNG đặt cờ, lần sau thử lại
    }
    update_option( 'sitetop_migration_sl_deleted_v1', time() );
}, 21 );


/**
 * Khoá cho Liên kết nhanh (/st) — TÁCH RIÊNG khỏi API token thật.
 *
 * Liên kết nhanh là URL bấm được nên nội dung của nó luôn công khai: ai nhận được
 * cũng đọc được. Trước đây nó mang chính API token, nghĩa là token bị phơi ra và
 * kẻ nhặt được dùng luôn cho /api. Khoá này CHỈ chạy được ở /st, không dùng được
 * cho bất kỳ endpoint nào khác, nên lộ cũng không mở thêm được gì.
 *
 * Sinh lần đầu khi cần, lưu ở user meta sitetop_quick_key.
 */
function sitetop_get_quick_key( $user_id ) {
    $user_id = (int) $user_id;
    if ( $user_id <= 0 ) return '';
    $k = get_user_meta( $user_id, 'sitetop_quick_key', true );
    if ( ! $k ) {
        $k = wp_generate_password( 24, false );
        update_user_meta( $user_id, 'sitetop_quick_key', $k );
    }
    return $k;
}

function sitetop_logo_url( $file ) {
    /* Bám theo thời điểm sửa file thay vì hằng số phải tự tay nâng: 29/08/2026 đã đổi
       ảnh logo mà quên nâng SITETOP_LOGO_VER, kết quả là máy chủ vẫn phục vụ file cũ
       cho khách dù git đã kéo file mới về. Dùng filemtime thì đổi file là URL tự đổi.
       Không có file (đường dẫn sai) thì lùi về hằng số cũ. */
    $path = SITETOP_DIR . '/assets/img/' . $file;
    $ver  = file_exists( $path ) ? filemtime( $path ) : SITETOP_LOGO_VER;
    return SITETOP_URL . '/assets/img/' . $file . '?v=' . $ver;
}

/* ============================================================
   FAVICON - Logo SiteTop cho tab trình duyệt
   - Site Icon (Customizer) đang set → filter đổi URL mọi size (ăn cả wp-admin)
   - Chưa set → tự in <link> trong wp_head/admin_head (link tag thắng /favicon.ico vật lý)
   - page-unlock.php có <head> riêng không qua wp_head → chèn link tay trong file đó
   ============================================================ */
// Hook đúng tên là 'get_site_icon_url' (wp-includes/general-template.php) — bản cũ
// filter 'site_icon_url' nên chưa bao giờ ăn: site.net có Site Icon vuông trong
// uploads và nó đè logo tròn của theme ở favicon.
add_filter( 'get_site_icon_url', function( $url, $size ) {
    $size = (int) $size;
    // >=180 (apple-touch 180 / android 192 / ms-tile 270): bản nền trắng ĐẶC — iOS lót đen sau PNG trong suốt
    if ( $size >= 180 ) return sitetop_logo_url( 'sitetop-touch-180.png' );
    // Tab trình duyệt: bản KHÔNG NỀN. Có sẵn bản 32 hạ mẫu riêng cho sắc nét ở size favicon.
    if ( $size <= 32 )  return sitetop_logo_url( 'sitetop-icon-32.png' );
    return sitetop_logo_url( 'sitetop-icon.png' );
}, 10, 2 );

// Chỉ còn cần cho wp-admin: core hook wp_site_icon() vào wp_head + login_head (đã đi
// qua filter trên) nhưng KHÔNG hook vào admin_head, nên trang quản trị phải tự in.
function sitetop_print_favicon_links() {
    echo '<link rel="icon" type="image/png" href="' . esc_url( sitetop_logo_url( 'sitetop-icon.png' ) ) . '">' . "\n";
    echo '<link rel="apple-touch-icon" href="' . esc_url( sitetop_logo_url( 'sitetop-touch-180.png' ) ) . '">' . "\n";
}
add_action( 'admin_head', 'sitetop_print_favicon_links', 2 );

/* ============================================================
   TIMEZONE - LUÔN DÙNG VIETNAM (UTC+7)
   Set PHP + MySQL timezone đồng nhất để tất cả
   date(), time(), CURRENT_TIMESTAMP đều là Vietnam
   ============================================================ */
date_default_timezone_set( 'Asia/Ho_Chi_Minh' );

// Set MySQL timezone = Vietnam khi kết nối DB
add_action( 'init', function() {
    global $wpdb;
    $wpdb->query( "SET time_zone = '+07:00'" );
}, 1 );

function sitetop_current_time() {
    return current_time( 'Y-m-d H:i:s' );
}

/* ============================================================
   THEME SETUP
   ============================================================ */
add_action( 'after_setup_theme', function() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption' ) );
    register_nav_menus( array( 'primary' => 'Menu chính' ) );
});

/**
 * Tiêu đề SEO của trang chủ.
 *
 * Đặt riêng bằng filter, KHÔNG đổi blogname: blogname còn dùng cho tiêu đề mọi trang
 * con ("Đăng nhập - <blogname>"), tên người gửi email, và tên site trong wp-admin.
 * Nhét cả câu dài vào blogname sẽ làm hỏng hết những chỗ đó.
 *
 * Bỏ tagline và site để tiêu đề ra đúng một câu, không bị nối thêm đuôi.
 */
add_filter( 'document_title_parts', function( $parts ) {
    if ( is_front_page() || is_home() ) {
        $parts['title'] = 'SITETOP – Website Rút Gọn Link Kiếm Tiền Uy Tín';
        unset( $parts['tagline'], $parts['site'] );
    }
    return $parts;
} );

/* ============================================================
   CUSTOM ROLES
   ============================================================ */
add_action( 'after_setup_theme', function() {
    // Customer role (advertiser)
    if ( ! get_role( 'customer' ) ) {
        add_role( 'customer', 'Customer', array(
            'read' => true,
        ));
    }

    // Ensure administrator has full capabilities for SiteTop.net
    $admin = get_role( 'administrator' );
    if ( $admin ) {
        $caps = array(
            'manage_sitetop',
            'manage_sitetop_users',
            'manage_sitetop_customers',
            'manage_sitetop_campaigns',
            'manage_sitetop_withdrawals',
            'manage_sitetop_deposits',
            'manage_sitetop_settings',
        );
        foreach ( $caps as $cap ) {
            if ( ! $admin->has_cap( $cap ) ) {
                $admin->add_cap( $cap );
            }
        }
    }

    // Admin users also get customer role (admin = advertiser too)
    $admins = get_users( array( 'role' => 'administrator' ) );
    foreach ( $admins as $admin_user ) {
        if ( ! in_array( 'customer', (array) $admin_user->roles, true ) ) {
            $admin_user->add_role( 'customer' );
        }
    }
}, 5 );

/* ============================================================
   FIX DEPRECATED WARNINGS (WP 6.4+)
   ============================================================ */
// Remove deprecated print_emoji_styles (replaced by wp_enqueue_emoji_styles in WP 6.4)
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
// Remove deprecated wp_admin_bar_header (replaced by wp_enqueue_admin_bar_header_styles in WP 6.4)
remove_action( 'wp_head', 'wp_admin_bar_header' );

/* ============================================================
   ENQUEUE
   ============================================================ */
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'sitetop-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap',
        array(), null );
    wp_enqueue_style( 'sitetop-style', SITETOP_URL . '/assets/css/main.css', array(), SITETOP_VERSION );
    wp_enqueue_script( 'sitetop-main', SITETOP_URL . '/assets/js/main.js', array('jquery'), SITETOP_VERSION, true );
    wp_localize_script( 'sitetop-main', 'sitetop_ajax', array(
        'url'   => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'sitetop_nonce' ),
        'home'  => home_url(),
    ));
});

add_action( 'admin_enqueue_scripts', function() {
    $screen = get_current_screen();
    if ( $screen && strpos( $screen->id, 'sitetop' ) !== false ) {
        wp_enqueue_style( 'sitetop-admin', SITETOP_URL . '/assets/css/admin.css', array(), SITETOP_VERSION );
        wp_enqueue_script( 'sitetop-admin', SITETOP_URL . '/assets/js/admin.js', array('jquery'), SITETOP_VERSION, true );
        wp_localize_script( 'sitetop-admin', 'sitetop_admin', array(
            'url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('sitetop_admin_nonce'),
        ));
    }
});

// Admin menu UI + tab caching (tách ra includes/admin-menu-ui.php)

/* ============================================================
   INCLUDES - Order matters (dependencies)
   ============================================================ */
$includes = array(
    'database-setup',
    'shortlink-ip',           // IP validation, rate limiting
    'ip-fraud',               // VPN/proxy detection via ip-api.com
    'behavior-analytics',     // Fraud scoring 0-100, device fingerprinting
    'anti-ddos',              // DDoS protection, 3-tier rate check
    'shortlink-functions',    // Core shortlink logic, alias system
    'shortlink-verification', // Verify & pay, user balance
    'shortlink-distribution', // Campaign distribution algorithm
    'shortlink-ajax',         // Frontend AJAX handlers
    'campaign-management',    // Campaign approval, rejection, pause/resume
    'user-management',        // Ban/unban, notifications, inactive cleanup
    'customer-management',    // Customer ban/unban, impersonation
    'customer-activation',    // Kích hoạt tài khoản Khách hàng thủ công (chờ Admin duyệt)
    'withdrawal',             // Withdrawal flow
    'deposit-management',     // Deposit with bonus tiers
    'email-notifications',    // Email system
    'telegram-notifications', // Admin Telegram bot notifications (thay email khi bật)
    'report-autopause',       // Tự tạm dừng camp khi >=5 IP báo lỗi/giờ + Telegram admin
    'low-balance-alerts',     // Low balance alerts
    'cron-cleanup',           // Cron jobs, counter sync
    'class-google-drive-upload', // ImgBB upload + WordPress fallback
    'admin-dashboard',        // Admin AJAX handlers
    'settings-management',    // Admin save settings (pricing, fraud, SMTP, etc.)
    'payment-settings',       // Bank QR, USDT config
    'admin-menu-ui',          // Admin sidebar labels, collapsible WP group, tab caching
    'admin-routing',          // Block wp-login, wp-admin redirects, hide admin bar
    'admin-deposit-ajax',     // AJAX: admin get/process deposits, customer create deposit
    'customer-campaign-ajax', // AJAX: customer campaign CRUD, shortlink edit, profile
    'admin-load-more',        // AJAX: user dashboard load more (links, transactions, withdrawals)
    'customer-load-more',     // AJAX: customer dashboard load more (campaigns, visits, deposits)
    'floating-contact',       // Floating contact button (Telegram/Zalo/Email)
    'rest-api',               // REST API endpoints (POST /wp-json/sitetop/v1/shortlinks)
    'referral-management',    // Hoa hồng referral: tính % + trả khi người được giới thiệu kiếm tiền
    'source-approval',        // Duyệt "Nguồn file gốc": user khai nguồn → Admin duyệt mới cho rút gọn link
    // 'admin-tab-cache' đã gỡ 02/07/2026: bỏ cache backend (chỉ giữ cache tab Visits, không cần
    // version tracking) — shutdown hook của nó ghi option sau mỗi admin action, tốn DB vô ích.
);

// 4-layer anti-DDoS check trên admin-ajax.php — skip cho cheap actions (heartbeat polling)
add_action( 'plugins_loaded', function() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( strpos( $uri, '/wp-admin/admin-ajax.php' ) === false ) return;
    if ( ! function_exists( 'sitetop_ddos_4layer_check' ) ) return;

    $action = $_REQUEST['action'] ?? '';
    // Cheap actions = polling/heartbeat (fire mỗi 2-5s) → KHÔNG count counter,
    // chỉ check existing block. Tránh false positive khi user mở page-unlock lâu.
    $cheap = array(
        'sitetop_unlock_heartbeat',
        'sitetop_check_code_ready',
        'sitetop_widget_verify_access',
        'sitetop_heartbeat',
        'heartbeat', // WP core
    );
    sitetop_ddos_4layer_check( ! in_array( $action, $cheap, true ) );
}, 1 );
foreach ( $includes as $file ) {
    $path = SITETOP_DIR . '/includes/' . $file . '.php';
    if ( file_exists( $path ) ) require_once $path;
}

/* ============================================================
   ONE-TIME MIGRATION: linkngon_ → sitetop_ (DB tables, options, user meta)
   Runs once on first load after the rename. Priority -999 ensures it
   executes before any other init hook accesses the renamed tables.
   ============================================================ */
add_action( 'init', function() {
    if ( get_option( 'sitetop_migrated_from_linkngon' ) ) return;

    global $wpdb;

    // Check if old tables exist — if not, this is a fresh install
    $old_table = $wpdb->prefix . 'linkngon_shortlink_visits';
    $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $old_table ) );
    if ( ! $exists ) {
        update_option( 'sitetop_migrated_from_linkngon', time() );
        return;
    }

    // 1. Rename all wp_linkngon_* tables → wp_sitetop_*
    $tables = $wpdb->get_col(
        $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->prefix . 'linkngon_%' )
    );
    foreach ( $tables as $old_name ) {
        $new_name = str_replace(
            $wpdb->prefix . 'linkngon_',
            $wpdb->prefix . 'sitetop_',
            $old_name
        );
        if ( $old_name !== $new_name ) {
            $wpdb->query( "RENAME TABLE `{$old_name}` TO `{$new_name}`" );
        }
    }

    // 2. Rename linkngon_* options → sitetop_*
    $wpdb->query(
        "UPDATE {$wpdb->options}
         SET option_name = CONCAT('sitetop_', SUBSTRING(option_name, 10))
         WHERE option_name LIKE 'linkngon\\_%%'
         AND option_name NOT LIKE '\\_transient%%'"
    );

    // 3. Rename transients
    $wpdb->query(
        "UPDATE {$wpdb->options}
         SET option_name = REPLACE(option_name, '_transient_linkngon_', '_transient_sitetop_')
         WHERE option_name LIKE '\\_transient\\_linkngon\\_%%'"
    );
    $wpdb->query(
        "UPDATE {$wpdb->options}
         SET option_name = REPLACE(option_name, '_transient_timeout_linkngon_', '_transient_timeout_sitetop_')
         WHERE option_name LIKE '\\_transient\\_timeout\\_linkngon\\_%%'"
    );

    // 4. Rename user meta keys
    $wpdb->query(
        "UPDATE {$wpdb->usermeta}
         SET meta_key = CONCAT('sitetop_', SUBSTRING(meta_key, 10))
         WHERE meta_key LIKE 'linkngon\\_%%'"
    );

    update_option( 'sitetop_migrated_from_linkngon', time() );
    flush_rewrite_rules();
}, -999 );

/* ============================================================
   ONE-TIME MIGRATION: behavior_analytics dedupe + UNIQUE INDEX
   Behavior analytics đã có pattern INSERT mỗi page → bảng phình.
   Migration này:
   1. Dedupe: giữ row mới nhất per session_id (MAX(id))
   2. Add UNIQUE INDEX session_id_unique để chặn dup level DB
   Dùng REBUILD approach (CREATE _new + INSERT SELECT + RENAME atomic).
   Pattern cũ DELETE self-JOIN sẽ timeout trên bảng lớn.
   ============================================================ */
add_action( 'init', function() {
    if ( get_option( 'sitetop_migration_behavior_unique_v1' ) ) return;

    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $table = $p . 'behavior_analytics';

    // Skip if table không tồn tại (fresh install — schema đã có UNIQUE từ dbDelta)
    $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
    if ( ! $exists ) {
        update_option( 'sitetop_migration_behavior_unique_v1', time() );
        return;
    }

    // Skip if UNIQUE INDEX đã tồn tại (đã migrate trước hoặc fresh install)
    $has_unique = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s
         AND INDEX_NAME = 'session_id_unique' AND NON_UNIQUE = 0",
        $table
    ));
    if ( $has_unique ) {
        update_option( 'sitetop_migration_behavior_unique_v1', time() );
        return;
    }

    // REBUILD approach — atomic, không timeout
    $wpdb->hide_errors();
    $charset = $wpdb->get_charset_collate();

    // 1. CREATE _new LIKE original
    $wpdb->query( "DROP TABLE IF EXISTS {$table}_new" );
    $created = $wpdb->query( "CREATE TABLE {$table}_new LIKE {$table}" );
    if ( $created === false ) {
        error_log( "sitetop migration: CREATE _new failed — " . $wpdb->last_error );
        return; // retry next request
    }

    // 2. ALTER _new: drop old session_id KEY, add UNIQUE
    $wpdb->query( "ALTER TABLE {$table}_new DROP INDEX session_id" );
    $altered = $wpdb->query( "ALTER TABLE {$table}_new ADD UNIQUE INDEX session_id_unique (session_id)" );
    if ( $altered === false ) {
        error_log( "sitetop migration: ALTER _new failed — " . $wpdb->last_error );
        $wpdb->query( "DROP TABLE IF EXISTS {$table}_new" );
        return;
    }

    // 3. Copy chỉ row mới nhất per session_id (subquery, không self-join)
    //    session_id != '' để skip junk rows từ legacy code chưa skip empty
    $copied = $wpdb->query(
        "INSERT INTO {$table}_new
         SELECT t.* FROM {$table} t
         INNER JOIN (
             SELECT MAX(id) AS max_id FROM {$table}
             WHERE session_id != '' GROUP BY session_id
         ) latest ON t.id = latest.max_id"
    );
    if ( $copied === false ) {
        error_log( "sitetop migration: INSERT SELECT failed — " . $wpdb->last_error );
        $wpdb->query( "DROP TABLE IF EXISTS {$table}_new" );
        return;
    }

    // 4. Swap atomic
    $swapped = $wpdb->query(
        "RENAME TABLE {$table} TO {$table}_old, {$table}_new TO {$table}"
    );
    if ( $swapped === false ) {
        error_log( "sitetop migration: RENAME failed — " . $wpdb->last_error );
        $wpdb->query( "DROP TABLE IF EXISTS {$table}_new" );
        return;
    }

    // 5. Drop _old (data đã được dedupe vào table chính)
    $wpdb->query( "DROP TABLE IF EXISTS {$table}_old" );

    // 6. OPTIMIZE để reclaim disk
    $wpdb->query( "OPTIMIZE TABLE {$table}" );

    update_option( 'sitetop_migration_behavior_unique_v1', time() );
    error_log( "sitetop migration: behavior_analytics dedupe + UNIQUE INDEX OK — copied {$copied} rows" );
}, -998 );

/* ============================================================
   ACTIVATION & AUTO-CREATE TABLES
   ============================================================ */
add_action( 'after_switch_theme', function() {
    sitetop_create_tables();
    flush_rewrite_rules();
});

// Auto-install custom db-error.php to wp-content/
add_action( 'admin_init', function() {
    $src = SITETOP_DIR . '/db-error.php';
    $dst = WP_CONTENT_DIR . '/db-error.php';
    if ( file_exists( $src ) && ( ! file_exists( $dst ) || md5_file( $src ) !== md5_file( $dst ) ) ) {
        @copy( $src, $dst );
    }
}, 99 );

// Auto-create tables if DB version mismatch or tables missing
add_action( 'init', function() {
    $db_version = get_option( 'sitetop_db_version', '' );
    if ( $db_version !== SITETOP_VERSION ) {
        if ( function_exists( 'sitetop_create_tables' ) ) {
            sitetop_create_tables();
        }
    }
}, 1 );

/* ============================================================
   REWRITE RULES (Shortlinks)
   ============================================================ */
/**
 * Slug hệ thống — bí danh KHÔNG được trùng, nếu không link rút gọn sẽ che mất
 * trang thật (đăng nhập, dashboard, endpoint API...).
 */
function sitetop_reserved_slugs() {
    return array(
        'dang-nhap', 'dang-ky', 'quen-mat-khau', 'user', 'customer', 'dieu-khoan',
        'admin', 'api', 'st', 'js', 'widget.js', 'top.js', 'widget-captcha',
        'widget-bridge', 'wp-admin', 'wp-login.php', 'wp-content', 'wp-includes',
        'wp-json', 'feed', 'robots.txt', 'sitemap.xml', 'favicon.ico',
    );
}

/**
 * Bí danh có dùng được không: đúng dạng, không phải slug hệ thống, và không
 * trùng slug của một trang/bài WordPress đang có (trang thật luôn thắng).
 */
function sitetop_alias_available( $alias ) {
    $alias = sanitize_title( $alias );
    if ( $alias === '' )                                     return 'Bí danh không hợp lệ';
    if ( strlen( $alias ) < 3 )                              return 'Bí danh tối thiểu 3 ký tự';
    if ( strlen( $alias ) > 100 )                            return 'Bí danh tối đa 100 ký tự';
    if ( in_array( $alias, sitetop_reserved_slugs(), true ) ) return 'Bí danh này hệ thống đang dùng, chọn tên khác';
    if ( get_page_by_path( $alias, OBJECT, array( 'page', 'post' ) ) ) {
        return 'Bí danh trùng một trang đang có, chọn tên khác';
    }
    return '';   // rỗng = dùng được
}

/* Shortlink: parse_request bắt request TRƯỚC khi WordPress coi nó là trang.
   ------------------------------------------------------------------
   TRƯỚC 01/09/2026 chỗ này chỉ nhận đúng 6 ký tự chữ-số, nên BÍ DANH do user
   đặt (sanitize_title → có dấu gạch nối, dài ngắn tuỳ ý) không bao giờ khớp:
   dashboard vẫn khoe link /khuyen-mai nhưng bấm vào trả 404. Bí danh lưu đúng
   trong DB, chỉ là không có đường nào dẫn tới.

   Giờ nhận mọi slug một đoạn, và để CHÍNH DATABASE quyết định: không tra ra
   shortlink thì trả request lại cho WordPress y như cũ. Trang thật vẫn được
   ưu tiên nhờ hai lớp chặn ở trên. */
add_action( 'parse_request', function( $wp ) {
    $request = trim( $wp->request, '/' );
    if ( empty($request) || strpos($request, '/') !== false ) return;
    if ( ! preg_match('/^[A-Za-z0-9._-]{1,100}$/', $request) ) return;
    if ( in_array( strtolower($request), sitetop_reserved_slugs(), true ) ) return;
    if ( ! function_exists('sitetop_get_shortlink_by_code_or_alias') ) return;
    $sl = sitetop_get_shortlink_by_code_or_alias( $request );
    if ( ! $sl ) return; // Không phải shortlink — trả về cho WP xử lý như trang thường
    $wp->query_vars['sitetop_shortlink'] = $request;
}, 1 );

add_action( 'init', function() {
    // Only match 6-char alphanumeric codes (shortlink format)
    // NOT all slugs — that blocks WP pages like dang-nhap, nguoi-dung, etc.
    add_rewrite_rule( '^([a-zA-Z0-9]{6})/?$', 'index.php?sitetop_shortlink=$matches[1]', 'top' );
    add_rewrite_rule( '^widget\.js$', 'index.php?sitetop_widget_js=1', 'top' );
});

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'sitetop_shortlink';
    $vars[] = 'sitetop_widget_js';
    return $vars;
});

add_action( 'template_redirect', function() {
    $code = get_query_var( 'sitetop_shortlink' );
    if ( $code ) {
        // Verify shortlink exists in DB before handling (don't block WP pages)
        if ( function_exists( 'sitetop_get_shortlink_by_code_or_alias' ) ) {
            $sl = sitetop_get_shortlink_by_code_or_alias( $code );
            if ( ! $sl ) return; // Not a shortlink — let WP handle normally
        }
        /* Mã 6 ký tự khớp rewrite rule nên WP dựng truy vấn bình thường; BÍ DANH thì
           không khớp rule nào, WP đã dựng sẵn truy vấn 404 trước khi parse_request của
           mình gán query var. Không gỡ cờ đó ra thì trang nhiệm vụ phục vụ đúng nội
           dung nhưng kèm header 404 — Cloudflare và trình thu thập hiểu là trang chết. */
        global $wp_query;
        if ( $wp_query ) $wp_query->is_404 = false;
        status_header( 200 );

        if ( function_exists('sitetop_ddos_check') ) sitetop_ddos_check();
        sitetop_handle_shortlink_visit( $code );
        exit;
    }
    if ( get_query_var( 'sitetop_widget_js' ) ) {
        sitetop_serve_widget_js();
        exit;
    }
});

/* ============================================================
   VIRTUAL PAGES - serve template files for slugs that don't have
   a WP page in the database. This ensures /dang-nhap/, /dang-ky/,
   /quen-mat-khau/, /user/, /customer/ always work even
   without manually creating WP pages.
   ============================================================ */
add_action( 'template_redirect', function() {
    if ( is_404() ) {
        // Map slug → template file
        $slug_map = array(
            'dang-nhap'      => 'page-login.php',
            'dang-ky'        => 'page-register.php',
            'quen-mat-khau'  => 'page-forgot-password.php',
            'user'           => 'page-user-dashboard.php',
            'customer'       => 'page-customer-dashboard.php',
            'dieu-khoan'     => 'page-dieu-khoan.php',
        );

        $request = trim( $_SERVER['REQUEST_URI'] ?? '', '/' );
        $request = strtok( $request, '?' );
        $request = rtrim( $request, '/' );

        // /admin/ → redirect to WP admin
        if ( $request === 'admin' ) {
            wp_safe_redirect( admin_url() );
            exit;
        }

        if ( isset( $slug_map[ $request ] ) ) {
            $tpl = SITETOP_DIR . '/' . $slug_map[ $request ];
            if ( file_exists( $tpl ) ) {
                status_header( 200 );
                include $tpl;
                exit;
            }
        }
    }
}, 1 );

/* ============================================================
   PAGE TEMPLATES
   ============================================================ */
add_filter( 'theme_page_templates', function( $templates ) {
    $templates['page-user-dashboard.php']     = 'User Dashboard (Publisher)';
    $templates['page-khach-hang.php'] = 'Customer Dashboard (Advertiser)';
    $templates['page-unlock.php']             = 'Unlock Page (Countdown)';
    $templates['page-login.php']              = 'Đăng nhập';
    $templates['page-register.php']           = 'Đăng ký';
    $templates['page-forgot-password.php']    = 'Quên mật khẩu';
    return $templates;
});

add_filter( 'template_include', function( $template ) {
    if ( is_page() ) {
        $pt = get_page_template_slug();
        if ( $pt && file_exists( SITETOP_DIR . '/' . $pt ) ) return SITETOP_DIR . '/' . $pt;
    }
    return $template;
});

// Admin routing: wp-login redirect, wp-admin block (tách ra includes/admin-routing.php)

/* ============================================================
   ADMIN MENU (only for admins who can still access wp-admin)
   ============================================================ */
add_action( 'admin_menu', function() {
    // ── TỔNG QUAN ──
    add_menu_page( 'Tổng quan', 'Tổng quan', 'manage_options', 'sitetop-overview', function() {
        include SITETOP_DIR . '/includes/admin/tabs/tab-overview.php';
    }, 'dashicons-chart-area', 2 );

    // ── NHÀ XUẤT BẢN ──
    add_menu_page( 'Người dùng', 'Người dùng', 'manage_sitetop_users', 'sitetop-users', function() {
        include SITETOP_DIR . '/includes/admin/tabs/tab-users.php';
    }, 'dashicons-admin-users', 3 );

    // Duyệt nguồn file gốc — badge đỏ hiện số user đang chờ duyệt
    $src_pending = function_exists( 'sitetop_count_pending_sources' ) ? sitetop_count_pending_sources() : 0;
    add_menu_page( 'Duyệt nguồn file', 'Duyệt nguồn file' . ( $src_pending ? ' <span class="update-plugins count-' . $src_pending . '"><span class="plugin-count">' . $src_pending . '</span></span>' : '' ),
        'manage_sitetop_users', 'sitetop-sources', function() {
        include SITETOP_DIR . '/includes/admin/tabs/tab-sources.php';
    }, 'dashicons-yes-alt', 4 );

    add_menu_page( 'Shortlinks', 'Shortlinks', 'manage_sitetop', 'sitetop-links', function() {
        include SITETOP_DIR . '/includes/admin/tabs/tab-links.php';
    }, 'dashicons-admin-links', 4 );

    add_menu_page( 'Rút tiền', 'Rút tiền', 'manage_sitetop', 'sitetop-withdrawals', function() {
        include SITETOP_DIR . '/includes/admin/tabs/tab-withdrawals.php';
    }, 'dashicons-bank', 5 );

    // ── KHÁCH HÀNG ──
    add_menu_page( 'Khách hàng', 'Khách hàng', 'manage_sitetop_customers', 'sitetop-customers', function() {
        include SITETOP_DIR . '/includes/admin/tabs/tab-customers.php';
    }, 'dashicons-store', 11 );

    add_menu_page( 'Nạp tiền', 'Nạp tiền', 'manage_sitetop', 'sitetop-deposits', function() {
        include SITETOP_DIR . '/includes/admin/tabs/tab-deposits.php';
    }, 'dashicons-money-alt', 12 );

    add_menu_page( 'Chiến dịch', 'Chiến dịch', 'manage_sitetop', 'sitetop-campaigns', function() {
        include SITETOP_DIR . '/includes/admin/tabs/tab-campaigns.php';
    }, 'dashicons-megaphone', 13 );

    // ── HỆ THỐNG ──
    add_menu_page( 'Visits', 'Visits', 'manage_sitetop', 'sitetop-visits', function() {
        include SITETOP_DIR . '/includes/admin/tabs/tab-visits.php';
    }, 'dashicons-visibility', 21 );

    add_menu_page( 'Cài đặt TT', 'Cài đặt TT', 'manage_sitetop_settings', 'sitetop-settings', function() {
        include SITETOP_DIR . '/includes/admin/tabs/tab-settings.php';
    }, 'dashicons-admin-generic', 22 );

    // Remove unnecessary WP menus
    remove_menu_page( 'index.php' );
    remove_menu_page( 'edit.php' );
    remove_menu_page( 'edit-comments.php' );
    remove_menu_page( 'themes.php' );
    remove_menu_page( 'users.php' );

});

// Redirect /wp-admin/ to Tổng quan page
add_action( 'admin_init', function() {
    global $pagenow;
    if ( $pagenow === 'index.php' && empty( $_GET['page'] ) && current_user_can( 'manage_options' ) ) {
        wp_redirect( admin_url( 'admin.php?page=sitetop-overview' ) );
        exit;
    }
});

// ── WORDPRESS ── Gom các mục WP mặc định vào cuối sidebar (chạy sau tất cả menu registered)
add_action( 'admin_menu', function() {
    global $menu;
    $wp_items = array( 'upload.php', 'edit.php?post_type=page', 'plugins.php', 'tools.php', 'options-general.php' );
    $wp_pos = 200; // Start position for WP items group (high to avoid collisions)
    foreach ( $wp_items as $slug ) {
        foreach ( $menu as $pos => $item ) {
            if ( isset( $item[2] ) && $item[2] === $slug ) {
                unset( $menu[$pos] );
                $menu[$wp_pos] = $item;
                $wp_pos++;
                break;
            }
        }
    }
}, 999 );

// Redirect /wp-admin/ to SiteTop.net dashboard (trong includes/admin-routing.php)

/* ============================================================
   HELPERS
   ============================================================ */

/** Get dashboard URL by user role */
function sitetop_get_dashboard_url( $user = null ) {
    if ( ! $user ) {
        $user = wp_get_current_user();
    }
    if ( ! $user || ! $user->ID ) {
        return home_url( '/user' );
    }
    if ( in_array( 'administrator', (array) $user->roles, true ) ) {
        return admin_url();
    }
    if ( in_array( 'customer', (array) $user->roles, true ) ) {
        return home_url( '/customer' );
    }
    return home_url( '/user' );
}

/**
 * Tài khoản quảng cáo (role `customer`) có đang đi nhầm cửa publisher không?
 *
 * Hai khu là hai sổ tiền tách biệt: publisher kiếm thưởng trong `transactions` /
 * `user_balance`; khách hàng nạp và tiêu trong `customer_transactions` /
 * `customer_balance`. Cho một tài khoản đi cả hai cửa là mở đường tạo link, gom
 * thưởng rồi gửi yêu cầu rút tiền từ sổ không phải của mình.
 *
 * Đây là CHIỀU NGƯỢC của guard đã có ở page-customer-dashboard.php (sự cố
 * 02/07/2026, user alonemmo #134: publisher mở thẳng /customer thấy nguyên form
 * nạp tiền). Lần đó chỉ vá một chiều, chiều này vẫn hở tới 20/08/2026.
 *
 * Admin luôn đi được cả hai cửa — khối CUSTOM ROLES ở trên gán thêm role
 * `customer` cho mọi administrator, nên phải miễn trừ trước khi xét role.
 */
function sitetop_is_advertiser_account( $user = null ) {
    if ( ! $user ) {
        $user = wp_get_current_user();
    }
    if ( ! $user || ! $user->ID ) {
        return false;
    }
    if ( user_can( $user, 'manage_options' ) ) {
        return false; // admin = quảng cáo luôn, được vào cả hai khu
    }
    return in_array( 'customer', (array) $user->roles, true );
}

/**
 * Chặn tài khoản quảng cáo ở các endpoint AJAX CHỈ dành cho publisher
 * (tạo link, rút tiền, thống kê, xem thêm). Gọi sau check_ajax_referer và
 * kiểm đăng nhập. Tự kết thúc request khi chặn.
 *
 * KHÔNG dùng cho các endpoint hai khu xài chung (sitetop_change_password,
 * sitetop_update_profile) — chặn ở đó sẽ khoá luôn khách hàng đổi mật khẩu.
 */
function sitetop_block_advertiser_ajax() {
    if ( sitetop_is_advertiser_account() ) {
        wp_send_json_error( 'Tài khoản quảng cáo không dùng được khu vực này. Vui lòng vào trang Khách hàng.' );
    }
}

/** Format VND */
function sitetop_format_money( $amount ) {
    return number_format( (float) $amount, 0, ',', '.' ) . 'đ';
}

/** Generate unique shortcode — defined in includes/shortlink-functions.php */

/** Get user IP — defined in includes/shortlink-ip.php (Cloudflare priority) */

/** Get/set option */
function sitetop_get_option( $key, $default = '' ) {
    return get_option( 'sitetop_' . $key, $default );
}
function sitetop_update_option( $key, $value ) {
    return update_option( 'sitetop_' . $key, $value );
}

/**
 * Verify a Cloudflare Turnstile token server-side. Globally available (used by registration
 * AND the widget captcha gate). No-op (true) when Turnstile isn't fully configured so flows are
 * unaffected unless an admin enables it. Fails OPEN on network/transport error (availability) —
 * only a definitive "not success" from Cloudflare blocks.
 */
if ( ! function_exists( 'sitetop_verify_turnstile' ) ) {
    /**
     * @param string $token        Token Turnstile do client gửi lên.
     * @param string $ip           IP người dùng.
     * @param string $enabled_flag Công tắc nào quyết định cổng này có bật hay không.
     *        Trang đăng nhập/đăng ký dùng 'turnstile_enabled'; cổng captcha của widget
     *        dùng 'widget_captcha_enabled'.
     *
     * PHẢI có tham số này. Trước đây hàm luôn xét 'turnstile_enabled', nên khi công tắc
     * đó TẮT mà captcha widget vẫn BẬT thì hàm trả true cho MỌI token — kể cả token rỗng
     * hoặc bịa. Kẻ gian chỉ cần gọi thẳng sitetop_widget_captcha với token bừa là được
     * ghi cờ captcha_ok, qua mặt sạch lớp captcha. Mỗi cổng phải tự xét công tắc của nó.
     */
    function sitetop_verify_turnstile( $token, $ip = '', $enabled_flag = 'turnstile_enabled' ) {
        $enabled = sitetop_get_option( $enabled_flag, $enabled_flag === 'widget_captcha_enabled' ? 1 : 0 );
        $secret  = sitetop_get_option( 'turnstile_secret_key', '' );
        $site    = sitetop_get_option( 'turnstile_site_key', '' );
        if ( ! $enabled || empty( $secret ) || empty( $site ) ) return true; // not configured → skip
        if ( empty( $token ) ) return false; // enabled but no token submitted
        $resp = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
            'timeout' => 8,
            'body'    => array( 'secret' => $secret, 'response' => $token, 'remoteip' => $ip ),
        ) );
        if ( is_wp_error( $resp ) ) return true; // network error → fail open
        if ( (int) wp_remote_retrieve_response_code( $resp ) !== 200 ) return true; // transport issue → fail open
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
        return ! empty( $body['success'] );
    }
}

/**
 * AJAX: widget captcha verification. Called same-origin from page-widget-captcha.php after the
 * visitor solves Turnstile. Verifies the token server-side and, on success, records a short-lived
 * transient bound to the session so verify_and_pay() can require it. (CORS-whitelisted in admin_init.)
 */
add_action( 'wp_ajax_nopriv_sitetop_widget_captcha', 'sitetop_ajax_widget_captcha' );
add_action( 'wp_ajax_sitetop_widget_captcha', 'sitetop_ajax_widget_captcha' );
function sitetop_ajax_widget_captcha() {
    if ( function_exists( 'sitetop_rate_limit_check' ) ) {
        $rate = sitetop_rate_limit_check( 'widget_verify' );
        if ( empty( $rate['allowed'] ) ) wp_send_json_error( 'rate_limited' );
    }
    $session_id = sanitize_text_field( $_POST['session_id'] ?? '' );
    $token      = sanitize_text_field( $_POST['token'] ?? '' );
    if ( empty( $session_id ) || ! preg_match( '/^[A-Za-z0-9]{16,64}$/', $session_id ) ) {
        wp_send_json_error( 'bad_session' );
    }
    $ip = function_exists( 'sitetop_get_real_ip' ) ? sitetop_get_real_ip() : ( $_SERVER['REMOTE_ADDR'] ?? '' );
    // Xét ĐÚNG công tắc của cổng này, không mượn công tắc của trang đăng nhập.
    if ( ! sitetop_verify_turnstile( $token, $ip, 'widget_captcha_enabled' ) ) {
        wp_send_json_error( 'captcha_failed' );
    }
    // Mark this session as captcha-cleared. TTL = visit max age (2h, xem verify_and_pay age check):
    // captcha giải ở LẦN BẤM ĐẦU vào widget, nhưng user có thể đợi lâu trước khi bấm "LẤY MÃ"
    // (hạn mã 600s tính từ lúc lấy) — TTL 900s cũ hết hạn trước mã → user làm đúng vẫn mất thưởng
    // (lý do captcha_unverified) trong khi khách hàng vẫn bị trừ tiền.
    set_transient( 'sitetop_captcha_ok_' . $session_id, 1, 7200 );
    wp_send_json_success( 'ok' );
}

// AJAX: Deposits (tách ra includes/admin-deposit-ajax.php)

// AJAX: Customer campaign CRUD + shortlink + profile (tách ra includes/customer-campaign-ajax.php)

/** Traffic types (V2: bỏ social) */
function sitetop_get_traffic_types() {
    return array(
        'keyword_search' => array(
            '1step' => 'Keyword 1-Step',
            '2step' => 'Keyword 2-Step',
            'nocode' => 'Keyword No-Code',
        ),
        'traffic_direct' => array(
            '1step' => 'Direct 1-Step',
            '2step' => 'Direct 2-Step',
            'nocode' => 'Direct No-Code',
        ),
    );
}

/** Get reward amount by campaign_type + traffic_type (Flow 8 from CLAUDE.md) */
/**
 * Hạn mức view/IP/ngày THỰC TẾ mà hệ thống áp dụng.
 *
 * Nguồn sự thật là sitetop_ip_view_quota() trong includes/shortlink-ip.php: nó đọc
 * option rồi KẸP CỨNG về 1–2, vì option trên production có thể còn giá trị cũ (5) từ
 * đời trước. In thẳng option ra cho user là hứa một con số hệ thống không bao giờ trả.
 *
 * Hàm này chỉ dùng để HIỂN THỊ, lặp lại đúng phép kẹp đó. Có hai chỗ hiển thị (danh
 * sách quy định và khối rate trong Tổng quan) nên tách ra đây để không bị lệch nhau.
 * Muốn cho phép quá 2 thì phải nới trần trong sitetop_ip_view_quota() TRƯỚC.
 */
function sitetop_effective_ip_limit() {
    $limit = (int) sitetop_get_option( 'shortlink_ip_limit_24h', 2 );
    if ( $limit < 1 || $limit > 2 ) { $limit = 2; }
    return $limit;
}

function sitetop_get_reward_amount( $campaign ) {
    // Priority 1: Campaign-specific user_reward
    if ( ! empty( $campaign->user_reward ) && $campaign->user_reward > 0 ) {
        return (float) $campaign->user_reward;
    }
    // Priority 2: Settings by campaign_type (keyword_search/traffic_direct) + traffic_type (1step/2step/nocode)
    $campaign_type = $campaign->campaign_type ?? 'keyword_search';
    $traffic_type = $campaign->traffic_type ?? '1step';

    if ( $campaign_type === 'keyword_search' ) {
        $key = 'keyword_user_' . $traffic_type; // keyword_user_1step, keyword_user_2step, keyword_user_nocode
    } elseif ( $campaign_type === 'traffic_direct' ) {
        $key = 'direct_user_' . $traffic_type;
    } elseif ( $campaign_type === 'traffic_social' ) {
        $key = 'social_user_' . $traffic_type;
    } else {
        $key = 'keyword_user_' . $traffic_type; // fallback
    }

    $val = sitetop_get_option( $key, 0 );
    if ( $val > 0 ) return (float) $val;

    // Priority 3: Fallback defaults
    $defaults = array( '1step' => 800, '2step' => 1000, 'nocode' => 800 );
    return (float) ( $defaults[ $traffic_type ] ?? 800 );
}

/** Widget JS serve - Widget LUÔN HIỆN (V2: bỏ logic ẩn/hiện) */
function sitetop_serve_widget_js() {
    header( 'Content-Type: application/javascript; charset=UTF-8' );
    nocache_headers(); // Cloudflare không được cache .js (header kèm `private`).
    header( 'Access-Control-Allow-Origin: *' );

    /* File này ~92KB và trước đây gửi kèm `no-store`, nghĩa là trình duyệt phải TẢI LẠI
       TOÀN BỘ ở MỌI lần tải trang — mỗi trang web khách, mỗi lần chuyển trang ở bước 2.
       Đổi sang `no-cache, must-revalidate` + ETag: trình duyệt VẪN hỏi server mỗi lần
       (nên bản vá tới ngay, không bao giờ bị đóng băng như vụ WP Rocket), nhưng nếu nội
       dung không đổi thì server trả 304 vài trăm byte thay vì 92KB.

       ETag băm theo NỘI DUNG ĐÃ SINH, không phải mtime của file: nội dung còn phụ thuộc
       cấu hình (màu, logo, site key captcha, countdown) — đổi cài đặt là ETag đổi theo. */
    ob_start();
    include SITETOP_DIR . '/widget.js.php';
    $js = ob_get_clean();

    $etag = '"' . md5( $js ) . '"';
    /* `private` BẮT BUỘC phải có. Bỏ nó ra là Cloudflare coi phản hồi này cache được và
       áp Browser Cache TTL của nó — đo thực tế thấy bị ghi đè thành `max-age=14400`, tức
       trình duyệt ôm bản cũ 4 TIẾNG mà không thèm hỏi lại. Đúng cái bẫy đóng băng đã mất
       cả ngày với WP Rocket. `private` chặn mọi cache dùng chung; `no-cache` vẫn cho
       trình duyệt giữ bản sao nhưng bắt hỏi server mỗi lần → nhận 304 nếu không đổi. */
    header( 'Cache-Control: private, no-cache, must-revalidate, max-age=0' );
    header( 'ETag: ' . $etag );
    header_remove( 'Expires' );

    /* So ETag phải BỎ QUA tiền tố W/ và dấu nháy. Cloudflare nén phản hồi rồi đổi ETag
       mạnh của mình thành ETag yếu (W/"..."), nên trình duyệt gửi lại bản có W/ — so
       thẳng chuỗi là không bao giờ khớp và lần nào cũng tải lại đủ 92KB.
       Trình duyệt cũng có thể gửi nhiều ETag cách nhau bởi dấu phẩy. */
    $inm = trim( (string) ( $_SERVER['HTTP_IF_NONE_MATCH'] ?? '' ) );
    if ( $inm !== '' ) {
        $want = trim( $etag, '"' );
        foreach ( explode( ',', $inm ) as $cand ) {
            $cand = trim( $cand );
            $cand = preg_replace( '/^W\//i', '', $cand );
            if ( trim( $cand, '"' ) === $want ) {
                status_header( 304 );
                exit;
            }
        }
    }
    echo $js;
    exit;
}

/** CORS headers for widget AJAX (cross-origin from target websites)
 *  Must use admin_init (not plugins_loaded) because admin-ajax.php calls
 *  send_origin_headers() AFTER plugins_loaded, which overrides our headers */
add_action( 'admin_init', function() {
    if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) return;
    $action = $_REQUEST['action'] ?? '';
    if ( empty( $action ) ) return;
    $widget_actions = array(
        'sitetop_widget_verify_access', 'sitetop_widget_start_timer', 'sitetop_widget_captcha',
        'sitetop_unlock_heartbeat', 'sitetop_get_code', 'sitetop_track_adblock',
        'sitetop_report_behavior', 'sitetop_check_code_ready',
        'sitetop_track_google_click', 'sitetop_track_direct_click',
        'sitetop_track_social_click', 'sitetop_verify_shortlink_code',
        /* Nhịp hiện diện — thiếu ở đây là trình duyệt CHẶN bằng CORS: máy chủ không bao
           giờ nhận được nhịp nên chốt "rời hẳn website" không kích hoạt, mà console của
           web khách thì cứ 10 giây lại nhả một lỗi đỏ. */
        'sitetop_widget_ping', 'sitetop_widget_left',
    );
    if ( in_array( $action, $widget_actions ) ) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ( ! empty( $origin ) ) {
            header( 'Access-Control-Allow-Origin: ' . $origin, true );
            header( 'Access-Control-Allow-Credentials: true' );
        } else {
            header( 'Access-Control-Allow-Origin: *', true );
        }
        header( 'Access-Control-Allow-Methods: POST, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Content-Type' );
        if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) { exit; }
    }
}, 0 );

/* ============================================================
   CRON SCHEDULES
   ============================================================ */
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['every_5_min'] = array( 'interval' => 300, 'display' => 'Every 5 minutes' );
    $schedules['every_15_min'] = array( 'interval' => 900, 'display' => 'Every 15 minutes' );
    return $schedules;
});

add_action( 'init', function() {
    $crons = array(
        'sitetop_5min_cron'    => 'every_5_min',
        'sitetop_15min_cron'   => 'every_15_min',
        'sitetop_hourly_cron'  => 'hourly',
        'sitetop_daily_cron'   => 'daily',
    );
    foreach ( $crons as $hook => $schedule ) {
        if ( ! wp_next_scheduled( $hook ) ) wp_schedule_event( time(), $schedule, $hook );
    }
});

// 5 min: auto-pause insufficient campaigns + cleanup cache files + expired transients
add_action( 'sitetop_5min_cron', function() {
    if ( function_exists('sitetop_auto_pause_insufficient_campaigns') )
        sitetop_auto_pause_insufficient_campaigns();
    if ( function_exists('sitetop_ddos_cleanup_files') )
        sitetop_ddos_cleanup_files();
    if ( function_exists('sitetop_ratelimit_cleanup_files') )
        sitetop_ratelimit_cleanup_files();
    // Dọn thêm bằng bộ gom rác có trần — cron chạy nền nên cho trần rộng.
    if ( function_exists('sitetop_gc_cache_files') )
        sitetop_gc_cache_files( true, 200000, 50000 );
    if ( function_exists('sitetop_cleanup_expired_transients') )
        sitetop_cleanup_expired_transients();
});

// 15 min: auto-resume paused campaigns
add_action( 'sitetop_15min_cron', function() {
    if ( function_exists('sitetop_auto_resume_paused_campaigns') )
        sitetop_auto_resume_paused_campaigns();
});

// Hourly: distribution rebalance, cache, low balance alerts
add_action( 'sitetop_hourly_cron', function() {
    if ( function_exists('sitetop_update_hourly_adjustments') )
        sitetop_update_hourly_adjustments();
    if ( function_exists('sitetop_cache_eligible_campaigns') )
        sitetop_cache_eligible_campaigns();
    if ( function_exists('sitetop_check_low_balance_alerts') )
        sitetop_check_low_balance_alerts();
});


// AJAX: Load more - user + customer (tách ra includes/admin-load-more.php + customer-load-more.php)

// Daily: cleanup, counter sync
add_action( 'sitetop_daily_cron', function() {
    if ( function_exists('sitetop_run_database_cleanup') )
        sitetop_run_database_cleanup();
    if ( function_exists('sitetop_sync_shortlink_counters') )
        sitetop_sync_shortlink_counters();
    if ( function_exists('sitetop_sync_campaign_counters') )
        sitetop_sync_campaign_counters();
    if ( function_exists('sitetop_cleanup_inactive_users') )
        sitetop_cleanup_inactive_users();
    if ( function_exists('sitetop_auto_delete_old_customers') )
        sitetop_auto_delete_old_customers();
});

// One-time counter sync after deploy (runs once per code version)
add_action( 'admin_init', function() {
    $ver = 'counter_sync_v3';
    if ( get_option( "sitetop_{$ver}" ) ) return;
    if ( function_exists('sitetop_sync_shortlink_counters') ) sitetop_sync_shortlink_counters();
    if ( function_exists('sitetop_sync_campaign_counters') ) sitetop_sync_campaign_counters();
    update_option( "sitetop_{$ver}", 1 );
}, 99 );

// One-time migration: ensure skip_reasons column exists on shortlink_visits.
// v2: chỉ set flag khi cột THỰC SỰ tồn tại sau ALTER — v1 set flag kể cả khi ALTER fail,
// khiến verify_and_pay ghi cột không tồn tại → UPDATE fail sau khi đã trả tiền (multi-pay).
// verify_and_pay chỉ ghi skip_reasons khi flag này bật (xem shortlink-verification.php).
add_action( 'admin_init', function() {
    $ver = 'migration_skip_reasons_v2';
    if ( get_option( "sitetop_{$ver}" ) ) return;
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $has_col = $wpdb->get_results( "SHOW COLUMNS FROM {$p}shortlink_visits LIKE 'skip_reasons'" );
    if ( empty( $has_col ) ) {
        $wpdb->query( "ALTER TABLE {$p}shortlink_visits ADD COLUMN skip_reasons text NULL" );
        $has_col = $wpdb->get_results( "SHOW COLUMNS FROM {$p}shortlink_visits LIKE 'skip_reasons'" );
    }
    if ( ! empty( $has_col ) ) {
        update_option( "sitetop_{$ver}", 1 );
    }
}, 99 );

// One-time fix: update unlock info text in DB (runs on ANY page load)
add_action( 'wp_loaded', function() {
    if ( get_option( 'sitetop_fix_unlock_info_v2' ) ) return;
    $content = get_option( 'sitetop_unlock_info_content', '' );
    if ( $content ) {
        $new = str_replace(
            array( '500đ-550đ', '100.000đ' ),
            array( '500đ-1.000đ', '50.000đ' ),
            $content
        );
        if ( $new !== $content ) update_option( 'sitetop_unlock_info_content', $new );
    }
    update_option( 'sitetop_fix_unlock_info_v2', 1 );
});


// Floating contact button (tách ra includes/floating-contact.php)


/* ONE-TIME FIX: Đã xóa — script bù thưởng from_google đã chạy xong hoặc gây DB overload.
   Nếu cần chạy lại, dùng AJAX diagnostic endpoint thay vì admin_init. */

