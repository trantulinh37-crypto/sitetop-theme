<?php
/**
 * Template: Unlock Page (v2.1.1)
 * Trang mở khóa link - Visitor làm theo hướng dẫn để lấy mã từ web đích
 */

if (!defined('ABSPATH')) exit;

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

// Kiểm tra có session_id từ URL không (sau khi đổi từ khóa)
$url_session_id = sanitize_text_field($_GET['sid'] ?? '');

if (!empty($url_session_id)) {
    // Load từ DB thay vì PHP session
    global $wpdb;
    $visits_table = $wpdb->prefix . 'sitetop_shortlink_visits';
    $campaigns_table = $wpdb->prefix . 'sitetop_keyword_campaigns';
    $shortlinks_table = $wpdb->prefix . 'sitetop_user_shortlinks';
    
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $visits_table WHERE session_id = %s",
        $url_session_id
    ));
    
    if ($visit) {
        // Load shortlink
        $shortlink = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $shortlinks_table WHERE id = %d",
            $visit->shortlink_id
        ));
        
        // Load campaign
        $campaign = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $campaigns_table WHERE id = %d",
            $visit->campaign_id
        ));
        
        if ($shortlink) {
            // Cập nhật PHP session (campaign sẽ được load lại từ DB ở line 80)
            $_SESSION['sitetop_shortlink'] = $shortlink;
            if ($campaign) $_SESSION['sitetop_campaign'] = $campaign;
            $_SESSION['sitetop_session_id'] = $url_session_id;
        }
    }
}

$shortlink = $_SESSION['sitetop_shortlink'] ?? null;
$campaign = $_SESSION['sitetop_campaign'] ?? null;
$session_id = $_SESSION['sitetop_session_id'] ?? '';

if (!$shortlink || !$session_id) {
    wp_redirect(home_url());
    exit;
}

// ================================================================
// LUÔN LOAD CAMPAIGN TỪ VISIT HIỆN TẠI (không dùng session cũ)
// Đảm bảo hiển thị đúng campaign/từ khóa mới nhất
// ================================================================
global $wpdb;
$visits_table = $wpdb->prefix . 'sitetop_shortlink_visits';
$campaigns_table = $wpdb->prefix . 'sitetop_keyword_campaigns';

// Lấy visit hiện tại từ DB
$current_visit = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $visits_table WHERE session_id = %s",
    $session_id
));

if (!$current_visit) {
    wp_redirect(home_url());
    exit;
}

// Lấy campaign từ visit (KHÔNG phải từ session) - JOIN với order để check status
$orders_table = $wpdb->prefix . 'sitetop_customer_orders';
$campaign = $wpdb->get_row($wpdb->prepare(
    "SELECT kc.*, co.status as order_status 
     FROM $campaigns_table kc
     LEFT JOIN $orders_table co ON co.id = kc.order_id
     WHERE kc.id = %d",
    $current_visit->campaign_id
));

// Nếu campaign không tồn tại, không active, hoặc order không active → Tự động tìm campaign mới
$need_new_campaign = false;
if (!$campaign) {
    $need_new_campaign = true;
} elseif ($campaign->status !== 'active') {
    $need_new_campaign = true;
} elseif ($campaign->order_status && $campaign->order_status !== 'active') {
    $need_new_campaign = true;
}

if ($need_new_campaign) {
    // Tìm campaign active khác (cả campaign và order đều active)
    $new_campaign = $wpdb->get_row($wpdb->prepare(
        "SELECT kc.* FROM $campaigns_table kc
         INNER JOIN $orders_table co ON co.id = kc.order_id
         WHERE kc.status = 'active'
         AND co.status = 'active'
         AND kc.id != %d 
         ORDER BY RAND() LIMIT 1",
        $current_visit->campaign_id
    ));
    
    if ($new_campaign) {
        // Cập nhật visit với campaign mới
        $wpdb->update(
            $visits_table,
            array('campaign_id' => $new_campaign->id),
            array('session_id' => $session_id)
        );
        $campaign = $new_campaign;
    } else {
        // Không có campaign khả dụng → redirect thẳng tới original_url
        // (tránh tạo "Trang chủ" referer giả khi visitor click shortlink)
        $fallback = ! empty( $shortlink->original_url ) ? $shortlink->original_url : home_url();
        wp_redirect( $fallback );
        exit;
    }
}

// Cập nhật session với campaign mới nhất
$_SESSION['sitetop_campaign'] = $campaign;

// Logic tìm campaign mới đã được xử lý ở trên (line 78-115)
// Không cần check lại ở đây

$site_name = get_option('sitetop_site_name', get_bloginfo('name'));
$site_short = get_option('sitetop_site_short', 'LẤY MÃ');
$site_logo = get_option('sitetop_site_logo', '');

$widget_color = get_option('sitetop_widget_color', '#1E5EFF');
$widget_text_color = get_option('sitetop_widget_text_color', '#ffffff');
$widget_icon = get_option('sitetop_widget_icon', '');
$widget_btn_text = get_option('sitetop_widget_button_text', 'LẤY MÃ');

// ── Camp ĐẨY TỪ SITE NGUỒN qua cầu nối (plugin ttp-lentop-bridge) ─────────────────────────────────
// Plugin đã lưu style nút THẬT của nguồn theo campaign lúc nhận job (ttplb_widget_style[cid]). Lấy ra
// để bước "tìm nút" vẽ ĐÚNG nút của nguồn (nút tròn trong footer như trên trang đích). Camp nội
// bộ / không có style / plugin vắng → null → GIỮ NGUYÊN giao diện sitetop cũ (fallback an toàn).
$fed_widget = function_exists('ttplb_current_widget_style') ? ttplb_current_widget_style() : null;
// FALLBACK không phụ thuộc version plugin (bài học 13/07/2026 — server sitetop chạy plugin CŨ chưa có
// getter/storage widget style → nút nguồn không hiện dù theme đã port): camp cầu nối LUÔN nhận diện được
// bằng tiền tố tiêu đề "[host#ref]" plugin gắn lúc tạo job — marker bền nhất (BRIDGE-LESSONS §11, cùng
// regex shortlink-verification.php:22). Style: đọc thẳng option ttplb_widget_style nếu plugin đời mới đã
// lưu; chưa có → mảng default rỗng = hiện theo MẶC ĐỊNH của nguồn. Pad đủ 4 khoá để mảng luôn non-empty
// (mảng rỗng là falsy → if($fed_widget) bên dưới sẽ rơi nhầm về UI cũ).
if (!is_array($fed_widget) && !empty($campaign->id)
    && preg_match('/^\[[^#\]]+#\d+\]/', (string)($campaign->title ?? ''))) {
    $ttplb_all  = get_option('ttplb_widget_style', array());
    $cid        = (int) $campaign->id;
    $fed_widget = (is_array($ttplb_all) && isset($ttplb_all[$cid]) && is_array($ttplb_all[$cid])) ? $ttplb_all[$cid] : array();
    $fed_widget += array('text' => '', 'color' => '', 'tcolor' => '', 'icon' => '');
}
if (is_array($fed_widget)) {
    // Camp cầu nối → hiển thị theo style + MẶC ĐỊNH của NGUỒN (hoclaixe: nút xanh #0D4F4F, chữ "LẤY MÃ",
    // icon rỗng → SVG hộp quà mặc định). Ghi ĐÈ hẳn, KHÔNG lẫn icon/màu của sitetop khi nguồn để trống.
    $widget_btn_text   = !empty($fed_widget['text'])   ? $fed_widget['text']   : 'LẤY MÃ';
    $widget_color      = !empty($fed_widget['color'])  ? $fed_widget['color']  : '#0D4F4F';
    $widget_text_color = !empty($fed_widget['tcolor']) ? $fed_widget['tcolor'] : '#ffffff';
    $widget_icon       = !empty($fed_widget['icon'])   ? $fed_widget['icon']   : '';
} else {
    $fed_widget = null;
}

// Bước "tìm nút LẤY MÃ" — dùng chung cho cả 3 loại traffic (keyword/direct/social) VÀ mọi camp
// (nội bộ lẫn cầu nối). Nút widget thật trên trang đích giờ nằm TRONG FOOTER (cuộn theo trang) cho tất cả
// → luôn minh hoạ khung trình duyệt + dải footer có nút để khớp đúng nút thật (đồng bộ với source).
$sitetop_step_intro = '<p>Trên <strong>trang đích</strong>, nút lấy mã nằm ở <strong>cuối trang, trong phần footer</strong> — phải cuộn xuống cuối trang mới thấy (như minh hoạ). Bấm vào nút đó rồi <strong>làm theo các thông báo hiện giữa màn hình</strong> để hiện mã:</p>';
ob_start(); ?>
                        <?php $fed_host = preg_replace('/^www\./', '', (string) parse_url($campaign->target_url ?? '', PHP_URL_HOST)); ?>
                        <div class="fed-screen">
                            <div class="fed-scr-bar">
                                <i></i><i></i><i></i>
                                <span class="fed-scr-url"><?php echo esc_html($fed_host ?: 'trang-dich.com'); ?></span>
                            </div>
                            <div class="fed-scr-lines">
                                <b></b><span></span><span></span><span></span>
                            </div>
                            <span class="fed-badge-hint">Cu&#7897;n xu&#7889;ng cu&#7889;i trang<br><strong>b&#7845;m n&#250;t n&#224;y &#11015;</strong></span>
                            <?php // Camp NỘI BỘ + có icon: nút thật (widget sitetop) hiện logo phủ kín → mock vẽ y hệt (fed-logo,
                                  // không chữ). Camp CẦU NỐI giữ mock icon-nhỏ+chữ vì nút thật là widget của SITE NGUỒN, không đổi theo ta.
                                  $fed_logo_full = ( empty($fed_widget) && $widget_icon ); ?>
                            <span class="fed-foot">Footer</span>
                            <span class="fed-ring" aria-hidden="true"></span>
                            <span class="fed-badge<?php echo $fed_logo_full ? ' fed-logo' : ''; ?>" style="background:<?php echo esc_attr($widget_color); ?>;color:<?php echo esc_attr($widget_text_color); ?>">
                                <?php if ($widget_icon): ?><img src="<?php echo esc_url($widget_icon); ?>" alt=""><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="14" rx="2"/><path d="M12 8V5a3 3 0 0 0-3-3h0a3 3 0 0 0-3 3v0"/><path d="M18 8V5a3 3 0 0 0-3-3h0a3 3 0 0 0-3 3v0"/><line x1="12" y1="8" x2="12" y2="22"/></svg><?php endif; ?>
                                <?php if ( ! $fed_logo_full ): ?><span class="fed-badge-t"><?php echo esc_html($widget_btn_text); ?></span><?php endif; ?>
                            </span>
                        </div>
                        <div class="fed-acts">
                            <div class="fed-acts-h">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
                                Trong lúc đếm ngược, trang sẽ yêu cầu bạn:
                            </div>
                            <ul>
                                <li>L&#432;&#7899;t l&#234;n <strong>1/3 trang</strong></li>
                                <li>Ch&#7841;m v&#224;o m&#224;n h&#236;nh khi c&#243; th&#244;ng b&#225;o</li>
                                <li>L&#432;&#7899;t <strong>ch&#7853;m</strong> l&#234;n &#273;&#7847;u trang</li>
                                <li>Cu&#7897;n xu&#7889;ng <strong>gi&#7919;a trang</strong></li>
                                <li>Cu&#7897;n xu&#7889;ng <strong>cu&#7889;i trang</strong> &#8212; m&#227; hi&#7879;n ngay sau &#273;&#243;</li>
                            </ul>
                            <p>M&#7895;i thao t&#225;c &#273;&#7873;u &#273;&#432;&#7907;c <strong>b&#225;o tr&#432;&#7899;c 3 gi&#226;y</strong>. L&#224;m k&#7883;p th&#236; &#273;&#7891;ng h&#7891; ch&#7841;y li&#234;n t&#7909;c; &#273;&#7875; l&#7905; th&#236; &#273;&#7891;ng h&#7891; <strong>t&#7841;m d&#7915;ng</strong> cho t&#7899;i khi b&#7841;n l&#224;m xong &#8212; l&#224;m qu&#225; nhanh s&#7869; b&#7883; nh&#7855;c ch&#7853;m l&#7841;i.</p>
                        </div>
                        <p class="fed-note">Lấy được mã trên trang đích &rarr; nhập vào ô bên dưới rồi bấm <strong>TIẾP TỤC</strong>.</p>
    <?php
$sitetop_step_btn = ob_get_clean();

$target_domain = parse_url($campaign->target_url ?? '', PHP_URL_HOST) ?? '';
$target_domain_short = preg_replace('/^www\./', '', $target_domain);

// Hiển thị domain đầy đủ trong ảnh mô tả (không che bằng dấu *)
$target_domain_masked = $target_domain_short;

// Lấy countdown từ SETTING (thời gian đếm ngược widget, thường 15-30s)
$countdown_seconds = intval(get_option('sitetop_widget_default_countdown', 30));
if ($countdown_seconds < 10) $countdown_seconds = 30;
if ($countdown_seconds > 60) $countdown_seconds = 30;

// Lấy traffic_type (1step, 2step, nocode)
$traffic_type = $campaign->traffic_type ?? '1step';
$is_2step = ($traffic_type === '2step');
$is_nocode = ($traffic_type === 'nocode');

// Lấy fixed_code và screenshot (từ campaign hoặc order)
$fixed_code = $campaign->fixed_code ?? '';
$nocode_screenshot_url = $campaign->nocode_screenshot_url ?? '';
$screenshot_desktop = $campaign->screenshot_desktop_url ?? '';
$screenshot_mobile = $campaign->screenshot_mobile_url ?? '';

// Lấy thêm từ order nếu thiếu data
$order_data = null;

// Cách 1: Lấy từ order_id trong campaign
if (!empty($campaign->order_id)) {
    $order_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sitetop_customer_orders WHERE id = %d",
        $campaign->order_id
    ));
}

// Cách 2: Tìm order theo target_url nếu chưa có
if (!$order_data && !empty($campaign->target_url)) {
    $order_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sitetop_customer_orders WHERE task_url = %s ORDER BY id DESC LIMIT 1",
        $campaign->target_url
    ));
}

// Cách 3: Tìm order theo keyword nếu chưa có
if (!$order_data && !empty($campaign->keyword)) {
    $order_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sitetop_customer_orders WHERE keyword = %s ORDER BY id DESC LIMIT 1",
        $campaign->keyword
    ));
}

// Lấy data từ order nếu tìm thấy
if ($order_data) {
    // Screenshot kết quả Google (bước 3)
    if (empty($screenshot_desktop) && !empty($order_data->screenshot_desktop_url)) {
        $screenshot_desktop = $order_data->screenshot_desktop_url;
    }
    if (empty($screenshot_mobile) && !empty($order_data->screenshot_mobile_url)) {
        $screenshot_mobile = $order_data->screenshot_mobile_url;
    }
    
    // Screenshot vị trí mã (bước 4 - nocode)
    // Có thể nằm ở direct_screenshot_url hoặc keyword_screenshot_url tùy loại campaign
    if (empty($nocode_screenshot_url)) {
        if (!empty($order_data->direct_screenshot_url)) {
            $nocode_screenshot_url = $order_data->direct_screenshot_url;
        } elseif (!empty($order_data->keyword_screenshot_url)) {
            $nocode_screenshot_url = $order_data->keyword_screenshot_url;
        }
    }
    
    // Fixed code
    if (empty($fixed_code) && !empty($order_data->fixed_code)) {
        $fixed_code = $order_data->fixed_code;
    }
    // Cũng check keyword_fixed_code
    if (empty($fixed_code) && !empty($order_data->keyword_fixed_code)) {
        $fixed_code = $order_data->keyword_fixed_code;
    }
    // Cũng check direct_fixed_code (traffic_direct)
    if (empty($fixed_code) && !empty($order_data->direct_fixed_code)) {
        $fixed_code = $order_data->direct_fixed_code;
    }
    
    // Social data (traffic_social)
    if (!empty($order_data->social_post_url)) {
        $social_post_url = $order_data->social_post_url;
    }
    if (!empty($order_data->social_screenshot_url)) {
        $social_screenshot_url = $order_data->social_screenshot_url;
    }
    // Ảnh vị trí mã cho social nocode
    if (!empty($order_data->social_nocode_screenshot_url)) {
        $social_nocode_screenshot_url = $order_data->social_nocode_screenshot_url;
    }
    // Nếu chưa có nocode_screenshot_url, thử lấy từ social_nocode_screenshot_url
    if (empty($nocode_screenshot_url) && !empty($order_data->social_nocode_screenshot_url)) {
        $nocode_screenshot_url = $order_data->social_nocode_screenshot_url;
    }
}

// Khởi tạo biến social nếu chưa có
$social_post_url = $social_post_url ?? '';
$social_screenshot_url = $social_screenshot_url ?? '';

// Lấy campaign_type (keyword_search, traffic_direct, traffic_social)
$campaign_type = $campaign->campaign_type ?? 'keyword_search';

// Lấy thông tin site hiện tại
$current_domain = $_SERVER['HTTP_HOST'] ?? parse_url(home_url(), PHP_URL_HOST);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mở khóa link - <?php echo esc_html($site_name); ?></title>
    <?php // <head> riêng không qua wp_head → chèn favicon tay (đồng bộ sitetop_print_favicon_links) ?>
    <link rel="icon" type="image/png" href="<?php echo esc_url( sitetop_logo_url( 'sitetop-icon.png' ) ); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url( sitetop_logo_url( 'sitetop-touch-180.png' ) ); ?>">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--p:#1E5EFF;--pd:#0A1633;--a:#00C6FF;--bg:#F2F5FC;--txt:#1F2A44;--txtl:#5A6684;--txtm:#8A93AB;--brd:#DFE5F3;--brdl:#ECF0FA;--ok:#00A96E;--err:#E0364B;--warn:#E08700}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',-apple-system,sans-serif;background:var(--bg);min-height:100vh;color:var(--txt);line-height:1.6;font-size:14px}
        .container{max-width:520px;margin:0 auto;padding:0 14px}
        @media(min-width:769px){.container{max-width:680px;padding:0 24px}}
        .header{display:none}
        .logo{font-weight:800;font-size:20px;color:var(--pd)}
        .logo img{height:36px;border-radius:10px}.logo i{font-size:24px}

        /* Cảnh báo đầu trang */
        .warning-box{background:#fff;border:1px solid var(--brd);border-left:3px solid var(--err);border-radius:14px;padding:13px 16px;margin-bottom:14px;font-size:12px;line-height:1.95;box-shadow:0 1px 2px rgba(15,32,74,.04)}
        .warning-box .icon{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;background:var(--err);color:#fff;border-radius:50%;font-size:9px;margin-right:5px;vertical-align:middle}
        .warning-box .red{color:var(--err);font-weight:700}.warning-box .blue{color:var(--p);font-weight:700}

        /* Card chính */
        .main-card{background:#fff;border-radius:16px;border:1px solid var(--brd);padding:20px 18px;margin-bottom:14px;box-shadow:0 1px 2px rgba(15,32,74,.04)}
        .main-title{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;font-size:18px;font-weight:800;color:var(--pd);letter-spacing:-.02em;margin-bottom:18px}
        .main-title-text{display:inline-flex;align-items:center;gap:9px}
        .main-title-text::before{content:'';width:4px;height:19px;border-radius:3px;background:linear-gradient(180deg,var(--p),var(--a));flex-shrink:0}
        .main-title i{color:var(--p);margin-right:6px}
        .visit-timer{display:inline-flex;align-items:center;gap:6px;padding:6px 13px;border-radius:99px;font-size:12px;font-weight:700;background:#EDF3FF;color:var(--p);border:1px solid #D5E3FF;white-space:nowrap;transition:all .25s}
        .visit-timer strong{font-variant-numeric:tabular-nums}
        .visit-timer.warn{background:#FFF6E6;color:#92400E;border-color:#FBDCA0}
        .visit-timer.crit{background:#FFE9EC;color:#991B1B;border-color:#F6BEC6;animation:vtPulse 1.5s infinite}
        .visit-timer.float{position:fixed;top:10px;left:10px;right:10px;z-index:9999;padding:10px 16px;border-radius:99px;justify-content:center;box-shadow:0 10px 26px -8px rgba(224,54,75,.55);max-width:520px;margin:auto}
        @keyframes vtPulse{0%,100%{opacity:1}50%{opacity:.75}}

        /* Ô nhập mã */
        .code-section{background:#F7FAFF;border:1px solid var(--brd);border-radius:14px;padding:16px;margin-top:12px;margin-bottom:18px}
        .code-input{width:100%;padding:14px 16px;border:1.5px solid var(--brd);border-radius:12px;font-size:15px;text-align:left;letter-spacing:0;font-weight:700;font-family:inherit;background:#fff;color:var(--pd);margin-bottom:12px;transition:all .2s}
        .code-input:focus{outline:none;border-color:var(--p);box-shadow:0 0 0 3px rgba(30,94,255,.14)}
        .code-input::placeholder{color:var(--txtm);font-size:13px;font-weight:500;letter-spacing:0}

        .btn-row{display:grid;grid-template-columns:1fr 1fr;gap:9px}
        .btn{padding:13px 16px;border:none;border-radius:12px;font-size:13px;font-weight:700;cursor:pointer;transition:transform .18s,box-shadow .18s,opacity .18s;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:7px}
        .btn-primary{background:linear-gradient(135deg,#1E5EFF,#3E86FF);color:#fff;box-shadow:0 10px 22px -12px rgba(30,94,255,.95)}
        .btn-primary:hover:not(:disabled){transform:translateY(-1px)}
        .btn-secondary{background:#EDF3FF;color:var(--p);border:1px solid #CFDEFF}
        .btn-secondary:hover{background:#DCE8FF}
        .btn:disabled{opacity:.45;cursor:not-allowed;transform:none;box-shadow:none}
        .note-text{text-align:center;color:var(--txtm);font-size:12px;margin-top:10px;font-weight:500}

        /* Các bước */
        .steps{display:flex;flex-direction:column;gap:10px}
        .step{display:flex;align-items:flex-start;gap:12px;padding:14px;background:#F7FAFF;border-radius:12px;border:1px solid var(--brd);transition:border-color .18s,background .18s}
        .step:hover{background:#F1F6FF;border-color:#C9DAFF}
        .step-num{width:26px;height:26px;background:var(--p);color:#fff;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex-shrink:0}
        .step-content{flex:1;padding-top:2px;min-width:0}
        .step-content p{font-size:13px;color:var(--txtl)}
        .step-content a{color:var(--p);font-weight:700;text-decoration:none;border-bottom:1px solid #9FBEFF}
        .step-content a:hover{border-bottom-color:var(--p)}

        .target-link-btn{display:inline-flex;align-items:center;gap:7px;padding:11px 18px;background:linear-gradient(135deg,#1E5EFF,#3E86FF);color:#fff!important;border-radius:11px;font-weight:700;font-size:13px;text-decoration:none!important;border:none!important;margin-top:9px;box-shadow:0 10px 22px -13px rgba(30,94,255,.95);transition:transform .18s}
        .target-link-btn:hover{transform:translateY(-1px)}
        .target-link-btn i{font-size:14px}

        .url-copy-box{display:flex;gap:7px;margin-top:9px;align-items:stretch}
        .url-display{flex:1;padding:11px 13px;border:1px solid var(--brd);border-radius:10px;font-size:12px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:#fff;color:var(--txt);outline:none;min-width:0}
        .url-display:focus{border-color:var(--p);box-shadow:0 0 0 3px rgba(30,94,255,.12)}
        .btn-copy-url{display:inline-flex;align-items:center;gap:5px;padding:11px 15px;background:var(--p);color:#fff;border:none;border-radius:10px;font-weight:700;font-size:12px;cursor:pointer;transition:background .18s;white-space:nowrap}
        .btn-copy-url:hover{background:#1748CC}
        .btn-copy-url.copied{background:var(--ok)}

        .nocode-hint{display:flex;align-items:flex-start;gap:9px;background:#EDF3FF;border:1px solid #D5E3FF;border-radius:12px;padding:11px 13px;margin-top:10px;margin-left:-38px}
        .nocode-hint i{color:var(--p);font-size:16px;margin-top:1px}
        .nocode-hint span{font-size:12px;color:#1743B8;line-height:1.55}
        .nocode-screenshot img{max-width:100%;border-radius:10px;border:1px solid var(--brd)}
        @media(max-width:480px){.url-copy-box{flex-direction:column}.url-display{font-size:11px}}

        .keyword-highlight{display:inline-block;background:#EDF3FF;color:var(--p);font-weight:700;padding:4px 11px;border:1px solid #D5E3FF;border-radius:8px}
        /* Lớp che URL trên ảnh chụp — giữ nguyên toạ độ, chỉ đổi bo góc */
        .screenshot-img{margin-top:10px;border-radius:10px;overflow:hidden;border:1px solid var(--brd);position:relative}
        .screenshot-img img{width:100%;display:none}
        .screenshot-img img.active{display:block}
        .screenshot-img .url-mask{position:absolute;top:8px;left:52px;right:0;height:30px;background:#fff;z-index:2;pointer-events:none;display:flex;align-items:center;padding:1px 10px}
        @media(max-width:768px){.screenshot-img .url-mask{top:14px;height:48px;left:64px;padding:4px 10px}}
        .screenshot-img .url-mask .mask-text{display:flex;font-family:Arial,sans-serif;line-height:1.3}
        .screenshot-img .url-mask .mask-url{font-size:11px;color:#4d5156}
        .screenshot-img .mobile-badge{position:absolute;top:6px;right:8px;background:var(--err);color:#fff;font-size:11px;font-weight:700;padding:3px 10px;border-radius:6px;z-index:3;pointer-events:none}

        .widget-section{text-align:center;padding:15px;background:#EDF3FF;border-radius:12px;margin-top:10px;margin-left:-38px;border:1px solid #D5E3FF}
        .widget-label{font-size:13px;color:var(--txtl);margin-bottom:10px;font-weight:600}
        .widget-btn-preview{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:<?php echo esc_attr($widget_color); ?>;color:<?php echo esc_attr($widget_text_color); ?>;border-radius:10px;font-weight:700;font-size:14px;box-shadow:0 6px 16px -6px rgba(15,32,74,.4)}
        .widget-btn-preview img{width:20px;height:20px}
        .widget-btn-preview.widget-btn-small{padding:6px 14px;font-size:12px;border-radius:8px}
        .widget-btn-preview.widget-btn-small img{width:16px;height:16px}.widget-btn-preview.widget-btn-small i{font-size:12px}
        /* Minh hoạ nút LẤY MÃ của camp cầu nối (nút tròn trong footer, bê từ trang nhiệm vụ nguồn) */
        .fed-screen{position:relative;margin:12px 0 6px;height:184px;border:1px solid var(--brd);border-radius:14px;background:#fff;overflow:hidden;box-shadow:0 8px 22px -14px rgba(15,32,74,.5)}
        .fed-foot{position:absolute;left:0;right:0;bottom:0;height:70px;background:#0F172A;color:rgba(255,255,255,.42);font-size:9.5px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;display:flex;align-items:flex-start;justify-content:flex-start;padding:7px 0 0 13px}
        .fed-scr-bar{height:28px;background:#F5F8FE;border-bottom:1px solid var(--brdl);display:flex;align-items:center;gap:5px;padding:0 11px}
        .fed-scr-bar i{width:8px;height:8px;border-radius:50%;background:#CBD8EE}
        .fed-scr-url{margin-left:8px;flex:1;height:16px;border-radius:99px;background:#fff;border:1px solid var(--brd);display:flex;align-items:center;padding:0 9px;font-size:9.5px;color:var(--txtm);font-weight:600;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
        .fed-scr-lines{padding:14px 15px}
        .fed-scr-lines b{display:block;height:12px;width:44%;border-radius:5px;background:#D7E2F7;margin:0 0 12px}
        .fed-scr-lines span{display:block;height:8px;border-radius:5px;background:#EDF1F9;margin:0 0 9px}
        .fed-scr-lines span:nth-child(2){width:92%}.fed-scr-lines span:nth-child(3){width:74%}.fed-scr-lines span:nth-child(4){width:58%}
        /* vòng nhấn quanh nút cho user biết nhìn vào đâu */
        .fed-ring{position:absolute;left:50%;bottom:6px;width:52px;height:52px;transform:translateX(-50%);border-radius:50%;border:2px solid rgba(255,255,255,.55);animation:fedPulse 1.8s ease-out infinite;pointer-events:none;z-index:1}
        @keyframes fedPulse{0%{transform:translateX(-50%) scale(1);opacity:.75}70%{transform:translateX(-50%) scale(1.45);opacity:0}100%{opacity:0}}
        @media(prefers-reduced-motion:reduce){.fed-ring{animation:none;opacity:.5}}
        .fed-badge{position:absolute;left:50%;bottom:9px;top:auto;right:auto;transform:translateX(-50%);z-index:2;display:inline-flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;width:60px;height:60px;border-radius:50%;font-size:10px;font-weight:800;letter-spacing:.4px;line-height:1;box-shadow:0 4px 14px rgba(0,0,0,.3);overflow:hidden;text-align:center}
        .fed-badge svg,.fed-badge img{width:22px;height:22px;display:block}
        .fed-badge.fed-logo img{width:100%;height:100%;object-fit:cover;border-radius:50%} /* logo phủ kín nút (camp nội bộ, đồng bộ widget.js) */
        .fed-badge-t{margin-top:1px}
        .fed-badge-hint{position:absolute;left:50%;bottom:82px;top:auto;right:auto;transform:translateX(-50%);font-size:12px;font-weight:700;color:#0f7a3c;text-align:center;line-height:1.35;white-space:nowrap}
        .fed-badge-hint strong{font-size:15px}
        .fed-note{font-size:12.5px;color:var(--txtm);margin-top:8px;line-height:1.55}
        .fed-acts{margin-top:10px;background:#EDF3FF;border:1px solid #D5E3FF;border-radius:12px;padding:12px 14px}
        .fed-acts-h{display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:800;color:var(--p);margin-bottom:8px}
        .fed-acts-h svg{width:15px;height:15px;flex-shrink:0}
        .fed-acts ul{margin:0;padding-left:17px}
        .fed-acts li{font-size:12.5px;color:#1743B8;line-height:1.6;margin-bottom:3px}
        .fed-acts p{font-size:11.5px;color:var(--txtl);margin:8px 0 0;line-height:1.55}

        .divider{display:flex;align-items:center;gap:12px;margin:16px 0;color:var(--txtm);font-size:12px;font-weight:600}
        .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--brd)}

        .report-section{text-align:center}
        .report-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;background:#fff;border:1px solid #F6BEC6;border-radius:99px;color:var(--err);font-weight:700;font-size:12px;cursor:pointer;transition:all .18s}
        .report-btn:hover{background:var(--err);border-color:var(--err);color:#fff}
        .report-note{font-size:11px;color:var(--txtm);margin-top:7px}

        .info-section{background:#fff;border-radius:16px;padding:18px;border:1px solid var(--brd);box-shadow:0 1px 2px rgba(15,32,74,.04)}
        .info-section h3{font-size:15px;font-weight:800;color:var(--pd);letter-spacing:-.01em;margin-bottom:10px;display:flex;align-items:center;gap:7px}
        .info-section h3 i{color:var(--p)}
        .info-section p{font-size:13px;color:var(--txtl);margin-bottom:8px}
        .info-section .highlight{background:#EDF3FF;color:var(--p);padding:2px 7px;border-radius:6px;font-weight:700}
        .info-section a{color:var(--p);font-weight:700;text-decoration:none}
        .info-section a:hover{text-decoration:underline}

        .toast{position:fixed;top:16px;left:50%;transform:translateX(-50%) translateY(-80px);padding:11px 20px;border-radius:12px;font-weight:700;font-size:13px;display:flex;align-items:center;gap:8px;z-index:1000;transition:all .3s ease;box-shadow:0 12px 28px -10px rgba(15,32,74,.55)}
        .toast.show{transform:translateX(-50%) translateY(0)}
        .toast-success{background:var(--ok);color:#fff}
        .toast-error{background:var(--err);color:#fff}
        .toast-warning{background:#fff!important;color:var(--p);border:1px solid #CFDEFF}

        .loading{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(242,245,252,.97);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;z-index:2000;opacity:0;visibility:hidden;transition:all .3s}
        .loading.show{opacity:1;visibility:visible}
        .spinner{width:36px;height:36px;border:3px solid var(--brd);border-top-color:var(--p);border-radius:50%;animation:spin .7s linear infinite}
        .loading p{color:var(--txtl);font-weight:700;font-size:13px}
        @keyframes spin{to{transform:rotate(360deg)}}

        .footer{text-align:center;padding:18px;font-size:12px;color:var(--txtm)}
        .footer a{color:var(--p);text-decoration:none;font-weight:700}

        .modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(10,22,51,.45);display:flex;align-items:center;justify-content:center;z-index:3000;opacity:0;visibility:hidden;transition:all .2s;padding:16px}
        .modal-overlay.show{opacity:1;visibility:visible}
        .modal{background:#fff;border-radius:16px;width:100%;max-width:380px;max-height:90vh;overflow-y:auto;transform:scale(.95);transition:all .2s;box-shadow:0 24px 60px -20px rgba(10,22,51,.6)}
        .modal-overlay.show .modal{transform:scale(1)}
        .modal-header{padding:15px 17px;border-bottom:1px solid var(--brdl);display:flex;align-items:center;justify-content:space-between}
        .modal-header h3{font-size:14px;font-weight:800;color:var(--pd);display:flex;align-items:center;gap:7px}
        .modal-header h3 i{color:var(--p)}
        .modal-close{background:none;border:none;font-size:18px;color:var(--txtm);cursor:pointer;padding:2px}
        .modal-close:hover{color:var(--err)}
        .modal-body{padding:15px 17px}
        .error-options{display:flex;flex-direction:column;gap:7px}
        .error-option{display:flex;align-items:center;gap:9px;padding:11px 13px;background:#F7FAFF;border:1px solid var(--brd);border-radius:11px;cursor:pointer;transition:all .18s;font-size:13px;color:var(--txtl)}
        .error-option:hover{background:#F1F6FF;border-color:#C9DAFF}
        .error-option.selected{background:#EDF3FF;border-color:var(--p);color:var(--p);font-weight:600;box-shadow:0 0 0 3px rgba(30,94,255,.1)}
        .error-option i{font-size:14px;color:var(--txtm);width:18px;text-align:center}
        .error-option.selected i{color:var(--p)}

        .tip-box{background:#EDF3FF;border:1px solid #D5E3FF;border-radius:12px;padding:14px;margin-bottom:14px}
        .tip-box .tip-title{display:flex;align-items:center;gap:7px;font-weight:800;color:var(--p);margin-bottom:10px;font-size:13px}
        .tip-box .tip-title i{font-size:16px;color:var(--p)}
        .tip-box .tip-steps{color:#1743B8;font-size:12px;line-height:1.65}
        .tip-box .tip-steps ol{margin:0;padding-left:18px}
        .tip-box .tip-steps li{margin-bottom:6px}
        .tip-box .tip-steps strong{color:var(--pd)}
        .tip-box .tip-steps code{background:#fff;padding:2px 6px;border-radius:5px;font-size:11px;color:var(--p);border:1px solid #D5E3FF}
        .tip-actions{display:flex;gap:8px;margin-bottom:16px}
        .tip-actions .btn{flex:1;padding:9px 10px;font-size:12px;border-radius:10px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px}
        .btn-back{background:#EEF2FA;color:var(--txtl)}
        .btn-back:hover{background:#E3E9F5}
        .btn-success{background:linear-gradient(135deg,#1E5EFF,#3E86FF);color:#fff;box-shadow:0 10px 22px -13px rgba(30,94,255,.95)}
        .btn-success:hover{transform:translateY(-1px)}
        .tip-report-section{border-top:1px dashed var(--brd);padding-top:12px}
        .tip-report-note{font-size:12px;color:var(--txtm);margin-bottom:8px;text-align:center}
        .tip-report-section textarea{width:100%;padding:9px 11px;border:1px solid var(--brd);border-radius:10px;font-size:12px;resize:none;height:50px;margin-bottom:8px;font-family:inherit}
        .tip-report-section textarea:focus{outline:none;border-color:var(--p);box-shadow:0 0 0 3px rgba(30,94,255,.12)}
        .btn-report{width:100%;padding:11px;border-radius:10px;margin-top:8px}
        .other-input{margin-top:8px;display:none}
        .other-input.show{display:block}
        .other-input textarea{width:100%;padding:11px;border:1px solid var(--brd);border-radius:10px;font-size:13px;font-family:inherit;resize:none;height:60px}
        .other-input textarea:focus{outline:none;border-color:var(--p);box-shadow:0 0 0 3px rgba(30,94,255,.12)}
        .modal-footer{padding:14px 17px;border-top:1px solid var(--brdl);display:flex;gap:8px}
        .modal-footer .btn{flex:1}
        @media(max-width:500px){.btn-row{gap:7px}.btn-row .btn{padding:12px 6px;font-size:12px}.main-title{font-size:16px}.keyword-highlight{font-size:13px}.container{padding:0 10px}}
        #report-turnstile iframe{border-radius:10px!important}
    </style>
    
    <!-- Turnstile Script -->
    <?php $turnstile_site_key = get_option('sitetop_turnstile_site_key', ''); ?>
    <?php if ($turnstile_site_key): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
    <?php endif; ?>
</head>
<body>
    <div id="adblock-mode2-banner" style="display:none;position:sticky;top:0;left:0;right:0;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;padding:14px 16px;text-align:center;z-index:99999;font-weight:600;font-size:14px;box-shadow:0 2px 12px rgba(220,38,38,0.4);line-height:1.5">
        ⚠️ <strong>Trình chặn quảng cáo đang chặn widget lấy mã</strong>. Vui lòng <strong>tắt Adblock / Brave Shield / AdGuard</strong> trên trang đích để lấy được mã, sau đó tải lại trang.
        <button onclick="this.parentNode.style.display='none'" style="margin-left:10px;background:rgba(255,255,255,0.25);border:none;color:#fff;padding:5px 14px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600">Đã hiểu</button>
    </div>
    <div class="container">
        <!-- Main Card -->
        <div class="main-card">
            <!-- Warning Box -->
            <div class="warning-box" style="margin-top:0">
                <span class="icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="12" y1="2" x2="12" y2="14"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></span>
                <span class="red">KHÔNG</span> sử dụng Fake IP, VPN, 1.1.1.1 để tránh bị chặn<br>
                <span class="icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg></span>
                Sử dụng trình duyệt <span class="blue">Chrome</span> để tránh gặp lỗi<br>
                <span class="icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span>
                <span class="red">KHÔNG</span> click vào quảng cáo <span class="blue">"Được tài trợ"</span><br>
                <span class="icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span>
                <span class="red">KHÔNG</span> sử dụng trình duyệt ẩn danh
            </div>
            <?php
            $tutorial_video = sitetop_get_option('unlock_tutorial_video', '');
            if (!empty($tutorial_video)):
            ?>
            <div class="tutorial-video" style="margin-bottom:16px">
                <?php
                $is_youtube = preg_match('/youtube\.com\/embed\//i', $tutorial_video) || preg_match('/youtube\.com\/watch/i', $tutorial_video) || preg_match('/youtu\.be\//i', $tutorial_video) || preg_match('/youtube\.com\/shorts\//i', $tutorial_video);
                if ($is_youtube):
                    $embed_url = $tutorial_video;
                    $is_shorts = false;
                    if (preg_match('/youtube\.com\/watch\?v=([^&]+)/i', $tutorial_video, $m)) {
                        $embed_url = 'https://www.youtube.com/embed/' . $m[1];
                    } elseif (preg_match('/youtu\.be\/([^?]+)/i', $tutorial_video, $m)) {
                        $embed_url = 'https://www.youtube.com/embed/' . $m[1];
                    } elseif (preg_match('/youtube\.com\/shorts\/([^?]+)/i', $tutorial_video, $m)) {
                        $embed_url = 'https://www.youtube.com/embed/' . $m[1];
                        $is_shorts = true;
                    }
                ?>
                    <div style="position:relative;width:100%;padding-bottom:56.25%;border-radius:10px;overflow:hidden;background:#000">
                        <iframe src="<?php echo esc_url($embed_url); ?>" style="position:absolute;top:0;left:0;width:100%;height:100%;border:none" allowfullscreen allow="autoplay; encrypted-media"></iframe>
                    </div>
                <?php else: ?>
                    <video controls playsinline preload="metadata" style="width:100%;border-radius:10px;background:#000">
                        <source src="<?php echo esc_url($tutorial_video); ?>" type="video/mp4">
                    </video>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Title + Countdown -->
            <?php
                $vt_expiry_sec = function_exists('sitetop_get_visit_expiry_seconds') ? sitetop_get_visit_expiry_seconds() : 600;
                $vt_elapsed = max(0, strtotime(sitetop_current_time()) - strtotime($current_visit->created_at));
                $vt_remaining = max(0, $vt_expiry_sec - $vt_elapsed);
                $vt_init_display = sprintf('%d:%02d', floor($vt_remaining / 60), $vt_remaining % 60);
            ?>
            <h1 class="main-title">
                <span class="main-title-text">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.78 7.78 5.5 5.5 0 017.78-7.78zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                    Hướng dẫn lấy mã
                </span>
                <?php if ($vt_remaining > 0): ?>
                <span class="visit-timer" id="visitTimer" data-remaining="<?php echo (int) $vt_remaining; ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span>Còn lại:</span>
                    <strong id="vcTime"><?php echo esc_html($vt_init_display); ?></strong>
                </span>
                <?php endif; ?>
            </h1>
            <p class="note-text" style="margin-bottom:14px"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2" style="vertical-align:-2px;margin-right:2px"><path d="M9 18h6M10 22h4M12 2v1M12 7a4 4 0 00-4 4c0 1.5.8 2.8 2 3.4V17h4v-2.6c1.2-.6 2-1.9 2-3.4a4 4 0 00-4-4z"/></svg> Làm đúng thứ tự các bước để không bị sai mã!</p>

            <!-- Steps -->
            <div class="steps">
                <?php if ($is_nocode): ?>
                <!-- NOCODE: Mã cố định - chỉ cần truy cập trang và đọc ở đúng vị trí -->
                
                <?php if ($campaign_type === 'keyword_search'): ?>
                <!-- Step 1: Google (bắt buộc, không chèn link) -->
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-content">
                        <p>Mở tab mới, truy cập <strong>Google.com</strong> <span style="color:#d63638;font-weight:600">(bắt buộc)</span></p>
                        <p style="font-size:11px;color:#6b7280;margin-top:4px">Hệ thống tự phát hiện — không cần bấm xác nhận</p>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-content">
                        <p>Tìm kiếm từ khóa: <span class="keyword-highlight"><?php echo esc_html($campaign->keyword); ?></span></p>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-content">
                        <p>Tìm và click vào kết quả như hình dưới:</p>

                        <?php if (!empty($screenshot_desktop) || !empty($screenshot_mobile)): ?>
                        <div class="screenshot-img" style="margin-left: -38px;"><?php if(!empty($campaign->mobile_only)): ?><div class="mobile-badge">Chỉ hiện trên điện thoại</div><?php endif; ?>
                            <?php if (!empty($screenshot_desktop)): ?>
                                <img src="<?php echo esc_url($screenshot_desktop); ?>" id="screenshot-desktop-nocode">
                            <?php endif; ?>
                            <?php if (!empty($screenshot_mobile)): ?>
                                <img src="<?php echo esc_url($screenshot_mobile); ?>" id="screenshot-mobile-nocode">
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <p style="color: #94a3b8; font-style: italic; margin-top: 8px;">Tìm kết quả từ <strong><?php echo esc_html($target_domain_masked); ?></strong> và click vào</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Step 4: Mã cố định -->
                <div class="step">
                    <div class="step-num">4</div>
                    <div class="step-content">
                        <p>Tìm <strong>MÃ XÁC NHẬN</strong> bị che trên trang web ở vị trí như hình dưới:</p>
                        
                        <?php if (!empty($nocode_screenshot_url)): ?>
                        <div class="nocode-screenshot" style="margin: 12px 0; margin-left: -46px;">
                            <img src="<?php echo esc_url($nocode_screenshot_url); ?>" alt="Vị trí mã xác nhận" style="max-width: 100%; border-radius: 8px; border: 2px solid #e2e8f0;">
                        </div>
                        <?php else: ?>
                        <p style="color: #64748b; font-style: italic;">Tìm mã xác nhận được hiển thị trên trang web</p>
                        <?php endif; ?>
                        
                        <div class="nocode-hint">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2" style="vertical-align:-2px"><path d="M9 18h6M10 22h4M12 2v1M12 7a4 4 0 00-4 4c0 1.5.8 2.8 2 3.4V17h4v-2.6c1.2-.6 2-1.9 2-3.4a4 4 0 00-4-4z"/></svg>
                            <span>Sau khi tìm được mã, nhập vào ô phía trên và nhấn <strong>"TIẾP TỤC"</strong></span>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($campaign_type === 'traffic_direct'): ?>
                <!-- Traffic Direct + Nocode -->
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-content">
                        <p>Truy cập trang web:</p>
                        <div class="url-copy-box">
                            <input type="text" class="url-display" value="<?php echo esc_attr($campaign->target_url); ?>" readonly id="target-url-input">
                            <button type="button" class="btn-copy-url" onclick="copyTargetUrl()">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg> Copy
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-content">
                        <p>Tìm <strong>MÃ XÁC NHẬN</strong> bị che trên trang web ở vị trí như hình dưới:</p>
                        
                        <?php if (!empty($nocode_screenshot_url)): ?>
                        <div class="nocode-screenshot" style="margin: 12px 0; margin-left: -46px;">
                            <img src="<?php echo esc_url($nocode_screenshot_url); ?>" alt="Vị trí mã xác nhận" style="max-width: 100%; border-radius: 8px; border: 2px solid #e2e8f0;">
                        </div>
                        <?php else: ?>
                        <p style="color: #64748b; font-style: italic;">Tìm mã xác nhận được hiển thị trên trang web</p>
                        <?php endif; ?>
                        
                        <div class="nocode-hint">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2" style="vertical-align:-2px"><path d="M9 18h6M10 22h4M12 2v1M12 7a4 4 0 00-4 4c0 1.5.8 2.8 2 3.4V17h4v-2.6c1.2-.6 2-1.9 2-3.4a4 4 0 00-4-4z"/></svg>
                            <span>Sau khi tìm được mã, nhập vào ô phía trên và nhấn <strong>"TIẾP TỤC"</strong></span>
                        </div>
                    </div>
                </div>
                <?php elseif ($campaign_type === 'traffic_social'): ?>
                <!-- Traffic Social + Nocode -->
                <?php 
                $social_platform = $campaign->social_platform ?? 'facebook';
                $social_icons_nocode = [
                    'facebook' => ['svg' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>', 'color' => '#1877f2', 'name' => 'Facebook'],
                    'tiktok' => ['svg' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M9 12a4 4 0 104 4V4a5 5 0 005 5"/></svg>', 'color' => '#000000', 'name' => 'TikTok'],
                    'youtube' => ['svg' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M22.54 6.42a2.78 2.78 0 00-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 00-1.94 2A29 29 0 001 11.75a29 29 0 00.46 5.33A2.78 2.78 0 003.4 19.1c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 001.94-2 29 29 0 00.46-5.25 29 29 0 00-.46-5.43z"/><polygon fill="#333" points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"/></svg>', 'color' => '#ff0000', 'name' => 'YouTube'],
                    'instagram' => ['svg' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>', 'color' => '#e4405f', 'name' => 'Instagram'],
                    'twitter' => ['svg' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/></svg>', 'color' => '#1da1f2', 'name' => 'Twitter/X'],
                    'zalo' => ['svg' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>', 'color' => '#0068ff', 'name' => 'Zalo'],
                ];
                $social_info_nocode = $social_icons_nocode[$social_platform] ?? $social_icons_nocode['facebook'];
                $social_link_nocode = !empty($social_post_url) ? $social_post_url : $campaign->target_url;
                ?>
                
                <!-- Step 1: Mở bài viết MXH -->
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-content">
                        <p>Truy cập bài viết trên <?php echo esc_html($social_info_nocode['name']); ?>:</p>
                        <a href="<?php echo esc_url($social_link_nocode); ?>" target="_blank" class="target-link-btn" style="background: <?php echo esc_attr($social_info_nocode['color']); ?>;" onclick="trackSocial()">
                            <?php echo $social_info_nocode['svg']; ?>
                            Mở bài viết
                        </a>
                    </div>
                </div>
                
                <!-- Step 2: Click vào link trong bài viết -->
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-content">
                        <p>Click vào link trong bài viết để truy cập trang đích:</p>
                        <?php if (!empty($social_screenshot_url)): ?>
                        <div class="screenshot-section" style="margin-top: 12px; margin-left: -46px;">
                            <img src="<?php echo esc_url($social_screenshot_url); ?>" alt="Ảnh hướng dẫn bài viết" style="max-width: 100%; border-radius: 8px 8px 0 0; border: 2px solid #e5e7eb; border-bottom: none; display: block;">
                            <div class="link-preview-box" style="background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border: 2px solid #e5e7eb; border-top: 1px dashed #94a3b8; border-radius: 0 0 8px 8px; padding: 10px 14px 10px 8px; display: flex; align-items: center; gap: 10px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
                                <div style="flex: 1; overflow: hidden;">
                                    <div style="font-size: 11px; color: #64748b; margin-bottom: 2px;">Link cần click:</div>
                                    <div style="font-size: 13px; color: #1e40af; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php 
                                        $target_url_display_social = $campaign->target_url;
                                        // Hiện 3/4 URL, che 1/4
                                        $max_length_social = min(80, ceil(strlen($target_url_display_social) * 3 / 4));
                                        if (strlen($target_url_display_social) > $max_length_social) {
                                            echo esc_html(substr($target_url_display_social, 0, $max_length_social) . '...');
                                        } else {
                                            echo esc_html($target_url_display_social);
                                        }
                                        ?>
                                    </div>
                                </div>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Step 3: Tìm mã xác nhận -->
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-content">
                        <p>Tìm <strong>MÃ XÁC NHẬN</strong> bị che trên trang web ở vị trí như hình dưới:</p>
                        
                        <?php if (!empty($nocode_screenshot_url)): ?>
                        <div class="nocode-screenshot" style="margin: 12px 0; margin-left: -46px;">
                            <img src="<?php echo esc_url($nocode_screenshot_url); ?>" alt="Vị trí mã xác nhận" style="max-width: 100%; border-radius: 8px; border: 2px solid #e2e8f0;">
                        </div>
                        <?php else: ?>
                        <p style="color: #64748b; font-style: italic;">Tìm mã xác nhận được hiển thị trên trang web</p>
                        <?php endif; ?>
                        
                        <div class="nocode-hint">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2" style="vertical-align:-2px"><path d="M9 18h6M10 22h4M12 2v1M12 7a4 4 0 00-4 4c0 1.5.8 2.8 2 3.4V17h4v-2.6c1.2-.6 2-1.9 2-3.4a4 4 0 00-4-4z"/></svg>
                            <span>Sau khi tìm được mã, nhập vào ô phía trên và nhấn <strong>"TIẾP TỤC"</strong></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php elseif ($campaign_type === 'keyword_search'): ?>
                <!-- KEYWORD SEARCH: Tìm kiếm từ khóa trên Google -->

                <!-- Step 1 -->
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-content">
                        <p>Truy cập Google.com</p>
                    </div>
                </div>
                
                <!-- Step 2 -->
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-content">
                        <p>Tìm kiếm từ khóa: <span class="keyword-highlight"><?php echo esc_html($campaign->keyword); ?></span></p>
                    </div>
                </div>
                
                <!-- Step 3 -->
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-content">
                        <p>Tìm và click vào kết quả như hình dưới:</p>
                        
                        <?php if (!empty($screenshot_desktop) || !empty($screenshot_mobile)): ?>
                        <div class="screenshot-img" style="margin-left: -38px;"><?php if(!empty($campaign->mobile_only)): ?><div class="mobile-badge">Chỉ hiện trên điện thoại</div><?php endif; ?>
                            <?php if (!empty($screenshot_desktop)): ?>
                                <img src="<?php echo esc_url($screenshot_desktop); ?>" id="screenshot-desktop">
                            <?php endif; ?>
                            <?php if (!empty($screenshot_mobile)): ?>
                                <img src="<?php echo esc_url($screenshot_mobile); ?>" id="screenshot-mobile">
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <p style="color: #94a3b8; font-style: italic; margin-top: 8px;">Tìm kết quả từ <strong><?php echo esc_html($target_domain_masked); ?></strong> và click vào</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Step 4 -->
                <div class="step">
                    <div class="step-num">4</div>
                    <div class="step-content">
                        <?php echo $sitetop_step_intro; ?>

                        <?php echo $sitetop_step_btn; ?>
                    </div>
                </div>
                
                <?php elseif ($campaign_type === 'traffic_direct'): ?>
                <!-- TRAFFIC DIRECT: Truy cập trực tiếp URL -->
                
                <!-- Step 1 -->
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-content">
                        <p>Copy URL sau và dán vào trình duyệt:</p>
                        <div class="url-copy-box">
                            <input type="text" class="url-display" value="<?php echo esc_attr($campaign->target_url); ?>" readonly id="target-url-input">
                            <button type="button" class="btn-copy-url" onclick="copyTargetUrl()">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg> Copy
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Step 2 -->
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-content">
                        <?php echo $sitetop_step_intro; ?>

                        <?php echo $sitetop_step_btn; ?>
                    </div>
                </div>
                
                <?php elseif ($campaign_type === 'traffic_social'): ?>
                <!-- TRAFFIC SOCIAL: Truy cập từ mạng xã hội -->
                <?php 
                $social_platform = $campaign->social_platform ?? 'facebook';
                $social_icons = [
                    'facebook' => ['svg' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>', 'color' => '#1877f2', 'name' => 'Facebook'],
                    'tiktok' => ['svg' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M9 12a4 4 0 104 4V4a5 5 0 005 5"/></svg>', 'color' => '#000000', 'name' => 'TikTok'],
                    'youtube' => ['svg' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M22.54 6.42a2.78 2.78 0 00-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 00-1.94 2A29 29 0 001 11.75a29 29 0 00.46 5.33A2.78 2.78 0 003.4 19.1c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 001.94-2 29 29 0 00.46-5.25 29 29 0 00-.46-5.43z"/><polygon fill="#333" points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"/></svg>', 'color' => '#ff0000', 'name' => 'YouTube'],
                    'instagram' => ['svg' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>', 'color' => '#e4405f', 'name' => 'Instagram'],
                    'twitter' => ['svg' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/></svg>', 'color' => '#1da1f2', 'name' => 'Twitter/X'],
                    'zalo' => ['svg' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>', 'color' => '#0068ff', 'name' => 'Zalo'],
                ];
                $social_info = $social_icons[$social_platform] ?? $social_icons['facebook'];
                
                // Link bài viết MXH (ưu tiên social_post_url, fallback về target_url nếu không có)
                $social_link = !empty($social_post_url) ? $social_post_url : $campaign->target_url;
                ?>
                
                <!-- Step 1: Mở bài viết MXH -->
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-content">
                        <p>Truy cập bài viết trên <?php echo esc_html($social_info['name']); ?>:</p>
                        <a href="<?php echo esc_url($social_link); ?>" target="_blank" class="target-link-btn" style="background: <?php echo esc_attr($social_info['color']); ?>;" onclick="trackSocial()">
                            <?php echo $social_info['svg']; ?>
                            Mở bài viết
                        </a>
                    </div>
                </div>
                
                <!-- Step 2: Hướng dẫn click link trong bài viết + ảnh chụp -->
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-content">
                        <p>Click vào link trong bài viết để truy cập trang đích:</p>
                        <?php if (!empty($social_screenshot_url)): ?>
                        <div class="screenshot-section" style="margin-top: 12px; margin-left: -46px;">
                            <img src="<?php echo esc_url($social_screenshot_url); ?>" alt="Ảnh hướng dẫn bài viết" style="max-width: 100%; border-radius: 8px 8px 0 0; border: 2px solid #e5e7eb; border-bottom: none; display: block;">
                            <div class="link-preview-box" style="background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border: 2px solid #e5e7eb; border-top: 1px dashed #94a3b8; border-radius: 0 0 8px 8px; padding: 10px 14px 10px 8px; display: flex; align-items: center; gap: 10px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
                                <div style="flex: 1; overflow: hidden;">
                                    <div style="font-size: 11px; color: #64748b; margin-bottom: 2px;">Link cần click:</div>
                                    <div style="font-size: 13px; color: #1e40af; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php 
                                        $target_url_display = $campaign->target_url;
                                        // Hiện 3/4 URL, che 1/4
                                        $max_length = min(80, ceil(strlen($target_url_display) * 3 / 4));
                                        if (strlen($target_url_display) > $max_length) {
                                            echo esc_html(substr($target_url_display, 0, $max_length) . '...');
                                        } else {
                                            echo esc_html($target_url_display);
                                        }
                                        ?>
                                    </div>
                                </div>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Step 3: Lấy mã trên trang đích -->
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-content">
                        <?php echo $sitetop_step_intro; ?>

                        <?php echo $sitetop_step_btn; ?>
                    </div>
                </div>
                
                <?php endif; ?>
            </div>

            <!-- Code Section (below steps) -->
            <div class="code-section">
                <input type="text" id="code-input" class="code-input" placeholder="Nhập mã tìm được vào đây để tiếp tục" maxlength="30" autocomplete="off">
                <div class="btn-row">
                    <button type="button" id="btn-unlock" class="btn btn-primary" onclick="unlockLink()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M5 12h14"/><polyline points="12 5 19 12 12 19"/></svg> TIẾP TỤC
                    </button>
                    <?php if ($campaign_type === 'keyword_search'): ?>
                    <button type="button" class="btn btn-secondary" id="btn-change-keyword" onclick="changeKeyword()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg> ĐỔI TỪ KHOÁ
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn btn-secondary" id="btn-change-campaign" onclick="changeCampaign()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg> ĐỔI CHIẾN DỊCH
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Report & Info Section (cùng card) -->
            <div class="divider">hoặc</div>
            
            <div class="report-section">
                <button class="report-btn" onclick="openReportModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                    Báo lỗi mã
                </button>
                <p class="report-note">Nếu không tìm thấy nút hoặc mã bị lỗi</p>
            </div>

            <!-- Info Section -->
            <div class="info-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px dashed #e2e8f0;">
                <h3><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg> <?php echo esc_html($current_domain); ?> là gì?</h3>
                <div class="info-content">Nền tảng traffic User kết nối người cung cấp traffic và doanh nghiệp cần đẩy từ khóa lên top Google. Bạn kiếm tiền bằng cách hoàn thành các tác vụ traffic đơn giản.

Mỗi lượt hoàn thành hợp lệ bạn nhận <span class="highlight">500đ-1.000đ</span>, rút tiền khi đạt <span class="highlight">50.000đ</span> qua ngân hàng hoặc USDT.

<br>Đăng ký miễn phí và bắt đầu kiếm tiền <a href="<?php echo esc_url(home_url('/dang-ky')); ?>"><strong>TẠI ĐÂY</strong></a>!</div>
            </div>
        </div>
        
    </div>
    
    <!-- Toast -->
    <div id="toast" class="toast"></div>
    
    <!-- Loading -->
    <div id="loading" class="loading">
        <div class="spinner"></div>
        <p>Đang xác thực...</p>
    </div>
    
    <!-- Report Modal -->
    <div id="report-modal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg> Báo lỗi mã</h3>
                <button class="modal-close" onclick="closeReportModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Bước 1: Chọn loại lỗi -->
                <div id="error-step-1">
                    <div class="error-options">
                        <div class="error-option" onclick="selectErrorWithTip(this, 'widget_not_show')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 01-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            <span>Không tìm thấy NÚT LẤY MÃ</span>
                        </div>
                        <div class="error-option" onclick="selectErrorWithTip(this, 'not_visited')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span>Hiện "Bạn chưa truy cập shortlink"</span>
                        </div>
                        <div class="error-option" onclick="selectErrorWithTip(this, 'generic_error')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <span>Nhập mã hiện "Có lỗi xảy ra!"</span>
                        </div>
                        <div class="error-option" onclick="selectErrorWithTip(this, 'code_wrong')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <span>Nhập mã hiện "Mã không đúng!"</span>
                        </div>
                        <div class="error-option" onclick="selectErrorWithTip(this, 'code_expired')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span>Mã đã hết hạn (quá 10 phút)</span>
                        </div>
                        <div class="error-option" onclick="selectErrorWithTip(this, 'no_code_appear')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                            <span>Bấm lấy mã nhưng không hiện mã</span>
                        </div>
                        <div class="error-option" onclick="selectErrorWithTip(this, 'not_found_google')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <span>Không tìm thấy kết quả trên Google</span>
                        </div>
                        <div class="error-option" onclick="selectErrorWithTip(this, 'page_error')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.84 12.25l1.72-1.71h0a5.004 5.004 0 00-7.07-7.07l-1.72 1.71"/><path d="M5.17 11.75l-1.71 1.71a5 5 0 007.07 7.07l1.71-1.71"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            <span>Trang web bị lỗi/không load được</span>
                        </div>
                        <div class="error-option" onclick="selectErrorWithTip(this, 'other')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            <span>Lỗi khác...</span>
                        </div>
                    </div>
                </div>
                
                <!-- Bước 2: Hiển thị hướng dẫn khắc phục -->
                <div id="error-step-2" style="display: none;">
                    <div class="tip-box" id="tip-content">
                        <!-- Nội dung tip sẽ được JS điền vào -->
                    </div>
                    
                    <div class="tip-actions">
                        <button class="btn btn-back" onclick="backToStep1()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg> Chọn lỗi khác
                        </button>
                        <button class="btn btn-success" onclick="markResolved()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><polyline points="20 6 9 17 4 12"/></svg> Đã khắc phục được
                        </button>
                    </div>
                    
                    <div class="tip-report-section">
                        <p class="tip-report-note">Nếu vẫn không được, hãy gửi báo lỗi để Admin kiểm tra:</p>
                        <textarea id="report-detail" placeholder="Mô tả thêm chi tiết lỗi (không bắt buộc)..."></textarea>
                        
                        <!-- Turnstile Captcha -->
                        <?php 
                        $turnstile_site_key = get_option('sitetop_turnstile_site_key', '');
                        if ($turnstile_site_key): 
                        ?>
                        <div class="report-captcha" style="margin-top: 12px; display: flex; justify-content: center;">
                            <div id="report-turnstile" style="transform: scale(0.85); transform-origin: center;"></div>
                        </div>
                        <?php endif; ?>
                        
                        <button class="btn btn-primary btn-report" id="btn-submit-report" onclick="submitReport()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Gửi báo lỗi
                        </button>
                    </div>
                </div>
                
                <!-- Lỗi khác - textarea -->
                <div class="other-input" id="other-input">
                    <textarea id="other-message" placeholder="Mô tả lỗi bạn gặp phải..."></textarea>
                    
                    <?php if ($turnstile_site_key): ?>
                    <div class="report-captcha" style="margin-top: 12px; display: flex; justify-content: center;">
                        <div id="report-turnstile-other" style="transform: scale(0.85); transform-origin: center;"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer" id="modal-footer-default">
                <button class="btn" style="background: #e2e8f0; color: #64748b;" onclick="closeReportModal()">Hủy</button>
                <button class="btn btn-primary" id="btn-submit-other" onclick="submitReportOther()" style="display: none;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Gửi báo lỗi
                </button>
            </div>
        </div>
    </div>
    
    <script>
        var sessionId = '<?php echo esc_js($session_id); ?>';
        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var originalUrl = '<?php echo esc_js($shortlink->original_url); ?>';
        var isNocodeKeyword = <?php echo ($is_nocode && $campaign_type === 'keyword_search') ? 'true' : 'false'; ?>;
        // Google detection via widget_verify_access (referer check on target site)
        var selectedError = '';
        var adblockDetected = false; // Biến lưu trạng thái adblock
        
        // ========================================
        // ADBLOCK DETECTION
        // ========================================
        function detectAdblock() {
            return new Promise(function(resolve) {
                var testAd = document.createElement('div');
                testAd.innerHTML = '&nbsp;';
                testAd.className = 'adsbox ad-banner ad-placeholder pub_300x250 pub_300x250m pub_728x90 text-ad textAd text_ad text_ads text-ads text-ad-links';
                testAd.style.cssText = 'position:absolute;left:-9999px;width:10px;height:10px;';
                document.body.appendChild(testAd);

                setTimeout(function() {
                    var isBlocked = false;

                    if (!document.body.contains(testAd)) {
                        isBlocked = true;
                    } else {
                        try {
                            var style = window.getComputedStyle(testAd);
                            if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                                isBlocked = true;
                            }
                        } catch(e) { isBlocked = true; }
                    }

                    if (testAd.parentNode) testAd.parentNode.removeChild(testAd);
                    resolve(isBlocked);
                }, 300);
            });
        }
        
        // Chạy detect ngay khi load
        detectAdblock().then(function(blocked) {
            adblockDetected = blocked;
            console.log('Adblock detected:', blocked);
            
            // Gửi trạng thái adblock lên server
            var fd = new FormData();
            fd.append('action', 'sitetop_track_adblock');
            fd.append('session_id', sessionId);
            fd.append('adblock', blocked ? '1' : '0');
            fetch(ajaxUrl, { method: 'POST', body: fd });
        });
        
        function showToast(text, type) {
            var t = document.getElementById('toast');
            t.className = 'toast toast-' + type + ' show';
            t.innerHTML = (type === 'error' ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>' : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>') + ' ' + text;
            setTimeout(function() { t.className = 'toast'; }, 4000);
        }
        
        function trackDirect() {
            var fd = new FormData();
            fd.append('action', 'sitetop_track_direct_click');
            fd.append('session_id', sessionId);
            fetch(ajaxUrl, { method: 'POST', body: fd });
        }
        
        function copyTargetUrl() {
            var input = document.getElementById('target-url-input');
            var btn = document.querySelector('.btn-copy-url');
            
            // Select và copy
            input.select();
            input.setSelectionRange(0, 99999);
            document.execCommand('copy');
            
            // Feedback
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><polyline points="20 6 9 17 4 12"/></svg> Đã copy!';
            btn.classList.add('copied');
            
            showToast('Đã copy URL! Hãy dán vào trình duyệt mới.', 'success');
            
            // Track
            var fd = new FormData();
            fd.append('action', 'sitetop_track_direct_click');
            fd.append('session_id', sessionId);
            fetch(ajaxUrl, { method: 'POST', body: fd });

            taskHandoff();

            // Reset sau 3 giây
            setTimeout(function() {
                btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg> Copy';
                btn.classList.remove('copied');
            }, 3000);
        }
        
        /* Báo server "user đã lấy URL đích, đang sang trang đích". Không có tín hiệu này
           thì widget bên trang đích KHÔNG gắn phiên → không chạy đếm ngược, dù IP/cookie
           vẫn còn visit đang chờ. Gọi khi bấm Copy và cả khi user tự bôi đen copy tay.
           Chỉ gửi 1 lần/trang, fire-and-forget — lỗi mạng không được chặn thao tác copy. */
        var _handoffSent = false;
        function taskHandoff() {
            if (_handoffSent || !sessionId) return;
            _handoffSent = true;
            var hf = new FormData();
            hf.append('action', 'sitetop_task_handoff');
            hf.append('session_id', sessionId);
            try { fetch(ajaxUrl, { method: 'POST', body: hf, credentials: 'same-origin' }); } catch (e) {}
        }
        // Copy tay (Ctrl/Cmd+C sau khi bôi đen ô URL) cũng tính là đã lấy URL.
        document.addEventListener('copy', function (e) {
            var t = e.target;
            if (t && t.classList && t.classList.contains('url-display')) taskHandoff();
        }, true);

        function trackSocial() {
            var fd = new FormData();
            fd.append('action', 'sitetop_track_social_click');
            fd.append('session_id', sessionId);
            fetch(ajaxUrl, { method: 'POST', body: fd });
        }
        
        function autoSelectScreenshot() {
            // Normal screenshots
            var d = document.getElementById('screenshot-desktop');
            var m = document.getElementById('screenshot-mobile');
            if (d) d.classList.remove('active');
            if (m) m.classList.remove('active');
            if (window.innerWidth <= 768 && m) m.classList.add('active');
            else if (d) d.classList.add('active');
            else if (m) m.classList.add('active');
            
            // Nocode screenshots
            var dNocode = document.getElementById('screenshot-desktop-nocode');
            var mNocode = document.getElementById('screenshot-mobile-nocode');
            if (dNocode) dNocode.classList.remove('active');
            if (mNocode) mNocode.classList.remove('active');
            if (window.innerWidth <= 768 && mNocode) mNocode.classList.add('active');
            else if (dNocode) dNocode.classList.add('active');
            else if (mNocode) mNocode.classList.add('active');
        }
        
        autoSelectScreenshot();
        window.addEventListener('resize', autoSelectScreenshot);
        
        function unlockLink() {
            var code = document.getElementById('code-input').value.trim();
            if (!code) { showToast('Vui lòng nhập mã!', 'error'); return; }
            if (code.length < 4) { showToast('Mã phải có ít nhất 4 ký tự!', 'error'); return; }
            
            document.getElementById('loading').classList.add('show');
            document.getElementById('btn-unlock').disabled = true;
            
            var fd = new FormData();
            fd.append('action', 'sitetop_verify_shortlink_code');
            fd.append('session_id', sessionId);
            fd.append('code', code);
            
            fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function(r) { return r.text(); })
            .then(function(text) {
                try { return JSON.parse(text); }
                catch(e) { throw new Error('Invalid'); }
            })
            .then(function(data) {
                document.getElementById('loading').classList.remove('show');
                document.getElementById('btn-unlock').disabled = false;
                if (data.success) {
                    if (window._clearCodeCache) window._clearCodeCache();
                    if (window._clearPending) window._clearPending();
                    showToast('Thành công! Đang chuyển hướng...', 'success');
                    var url = (data.data && (data.data.target_url || data.data.redirect_url)) || originalUrl;
                    setTimeout(function() { window.location.href = url; }, 1200);
                } else {
                    if (window._clearPending) window._clearPending();
                    showToast(data.data?.message || 'Mã không đúng!', 'error');
                }
            })
            .catch(function() {
                document.getElementById('loading').classList.remove('show');
                document.getElementById('btn-unlock').disabled = false;
                if (!navigator.onLine && window._savePending) {
                    window._savePending(code);
                    showToast('Mất kết nối, sẽ tự thử lại khi có mạng.', 'error');
                } else {
                    showToast('Có lỗi xảy ra!', 'error');
                }
            });
        }
        
        // Change Keyword/Campaign - lần đầu chờ 10s, sau đó mỗi 30 giây mới được đổi tiếp
        var firstChangeCooldown = 10000; // 10 giây cooldown lần đầu
        var changeCooldown = 30000; // 30 giây cooldown các lần sau
        var currentCampaignId = <?php echo intval($campaign->id); ?>;
        var sessionId = '<?php echo esc_js($session_id); ?>';
        
        // Dùng shortlink slug làm key (không đổi khi đổi campaign)
        var shortlinkSlug = '<?php echo esc_js( is_object($shortlink) ? ($shortlink->alias ?? $shortlink->code ?? '') : '' ); ?>';
        var baseKey = shortlinkSlug || 'default';
        
        // Lưu thời điểm vào page và số lần đã đổi (dùng baseKey để persist qua các lần đổi campaign)
        var storageKey = 'tn_last_change_' + baseKey;
        var changeCountKey = 'tn_change_count_' + baseKey;
        var pageEntryKey = 'tn_page_entry_' + baseKey;
        
        var lastChangeTime = parseInt(sessionStorage.getItem(storageKey) || '0');
        var changeCount = parseInt(sessionStorage.getItem(changeCountKey) || '0');
        
        // Ghi nhận thời điểm vào page (chỉ lần đầu)
        var pageEntryTime = parseInt(sessionStorage.getItem(pageEntryKey) || '0');
        if (pageEntryTime === 0) {
            pageEntryTime = Date.now();
            sessionStorage.setItem(pageEntryKey, pageEntryTime.toString());
        }
        
        function canChange() {
            var now = Date.now();
            
            // Lần đầu (chưa đổi lần nào): phải chờ 10s kể từ khi vào page
            if (changeCount === 0) {
                var elapsedSinceEntry = now - pageEntryTime;
                if (elapsedSinceEntry < firstChangeCooldown) {
                    var remaining = Math.ceil((firstChangeCooldown - elapsedSinceEntry) / 1000);
                    return { allowed: false, remaining: remaining, message: 'Chờ ' + remaining + ' giây nữa để đổi từ khóa!' };
                }
                return { allowed: true };
            }
            
            // Các lần sau: phải chờ 30s kể từ lần đổi trước
            var elapsedSinceLastChange = now - lastChangeTime;
            if (elapsedSinceLastChange < changeCooldown) {
                var remaining = Math.ceil((changeCooldown - elapsedSinceLastChange) / 1000);
                return { allowed: false, remaining: remaining, message: 'Chờ ' + remaining + ' giây nữa để đổi tiếp!' };
            }
            
            return { allowed: true };
        }
        
        function doChangeCampaign() {
            // Gọi AJAX để đổi campaign
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var res = JSON.parse(xhr.responseText);
                            if (res.success) {
                                // Ghi nhận thời điểm đổi và tăng số lần đổi
                                var now = Date.now();
                                sessionStorage.setItem(storageKey, now.toString());
                                lastChangeTime = now;
                                changeCount++;
                                sessionStorage.setItem(changeCountKey, changeCount.toString());
                                // Redirect với session_id mới để load đúng campaign
                                var newSessionId = res.data.new_session_id;
                                if (newSessionId) {
                                    window.location.href = window.location.pathname + '?sid=' + encodeURIComponent(newSessionId);
                                } else {
                                    window.location.reload();
                                }
                            } else {
                                showToast(res.data.message || 'Không thể đổi!', 'error');
                            }
                        } catch(e) {
                            showToast('Có lỗi xảy ra!', 'error');
                        }
                    } else {
                        showToast('Có lỗi xảy ra!', 'error');
                    }
                }
            };
            xhr.send('action=sitetop_change_keyword&session_id=' + encodeURIComponent(sessionId) + '&exclude_id=' + currentCampaignId);
        }
        
        function changeKeyword() {
            var check = canChange();
            if (!check.allowed) {
                showToast(check.message, 'warning');
                return;
            }
            if (confirm('Bạn có chắc muốn đổi sang từ khóa khác?')) {
                doChangeCampaign();
            }
        }
        
        function changeCampaign() {
            var check = canChange();
            if (!check.allowed) {
                showToast(check.message, 'warning');
                return;
            }
            if (confirm('Bạn có chắc muốn đổi sang chiến dịch khác?')) {
                doChangeCampaign();
            }
        }
        
        // Report Modal Functions
        var reportTurnstileWidgetId = null;
        var reportCaptchaToken = '';
        var turnstileSiteKey = '<?php echo esc_js(get_option("sitetop_turnstile_site_key", "")); ?>';
        
        function openReportModal() {
            document.getElementById('report-modal').classList.add('show');
            selectedError = '';
            reportCaptchaToken = '';
            document.querySelectorAll('.error-option').forEach(function(el) {
                el.classList.remove('selected');
            });
            document.getElementById('other-input').classList.remove('show');
            document.getElementById('other-message').value = '';
            
            // Render Turnstile captcha
            if (turnstileSiteKey && typeof turnstile !== 'undefined') {
                var container = document.getElementById('report-turnstile');
                if (container) {
                    container.innerHTML = '';
                    reportTurnstileWidgetId = turnstile.render(container, {
                        sitekey: turnstileSiteKey,
                        callback: function(token) {
                            reportCaptchaToken = token;
                        },
                        'expired-callback': function() {
                            reportCaptchaToken = '';
                        }
                    });
                }
            }
        }
        
        function closeReportModal() {
            document.getElementById('report-modal').classList.remove('show');
            // Reset về step 1
            document.getElementById('error-step-1').style.display = 'block';
            document.getElementById('error-step-2').style.display = 'none';
            document.getElementById('other-input').classList.remove('show');
            document.getElementById('modal-footer-default').style.display = 'flex';
            document.getElementById('btn-submit-other').style.display = 'none';
            selectedError = '';
            selectedErrorType = '';
            // Reset captcha
            if (reportTurnstileWidgetId !== null && typeof turnstile !== 'undefined') {
                try { turnstile.reset(reportTurnstileWidgetId); } catch(e) {}
            }
            reportCaptchaToken = '';
            // Clear selections
            document.querySelectorAll('.error-option').forEach(function(opt) {
                opt.classList.remove('selected');
            });
        }
        
        // Định nghĩa các tip khắc phục
        var errorTips = {
            'widget_not_show': {
                title: 'Không tìm thấy NÚT LẤY MÃ',
                steps: [
                    'Kiểm tra lại yêu cầu ở bước 4 xem yêu cầu là nút lấy mã hay ảnh mã bị che?',
                    'Đợi <strong>3-5 giây</strong> sau khi trang load xong để nút lấy mã hiện lên.',
                    'Thử truy cập lại link rút gọn và làm lại từ đầu với trình duyệt <strong>Google Chrome</strong>.',
                    'Nếu vẫn không được hãy <strong>Gửi báo lỗi</strong> với admin nhé!'
                ]
            },
            'not_visited': {
                title: 'Hiện "Bạn chưa truy cập shortlink"',
                steps: [
                    'Bạn cần truy cập lại <strong>link rút gọn</strong> (link bắt đầu bằng sitetop.net/...).',
                    'Làm theo <strong>đúng thứ tự các bước</strong> hướng dẫn trên trang.',
                    'Đảm bảo bạn đang dùng <strong>cùng trình duyệt</strong> (không mở tab ẩn danh).',
                    'Nếu vẫn bị, thử <strong>xóa cookie</strong> trình duyệt rồi truy cập lại link rút gọn từ đầu.'
                ]
            },
            'generic_error': {
                title: 'Nhập mã hiện "Có lỗi xảy ra!"',
                steps: [
                    'Kiểm tra <strong>kết nối mạng</strong> của bạn (WiFi/4G).',
                    'Lỗi này thường do mạng bị gián đoạn hoặc server đang bận, thử <strong>bấm lại sau 5-10 giây</strong>.',
                    'Nếu dùng VPN/Proxy, hãy <strong>tắt đi</strong> rồi thử lại.',
                    'Thử truy cập lại link rút gọn bằng trình duyệt <strong>Google Chrome</strong> và làm lại từ đầu.',
                    'Nếu vẫn bị, hãy <strong>Gửi báo lỗi</strong> để Admin kiểm tra.'
                ]
            },
            'code_wrong': {
                title: 'Nhập mã hiện "Mã không đúng!"',
                steps: [
                    'Kiểm tra lại mã bạn <strong>đã copy đúng chưa</strong>? Mã gồm 6-8 ký tự.',
                    'Đảm bảo <strong>không có khoảng trắng</strong> ở đầu hoặc cuối mã.',
                    'Nên <strong>copy mã</strong> thay vì gõ tay để tránh sai.',
                    'Mã chỉ có hiệu lực trong <strong>10 phút</strong>. Nếu quá lâu, hãy lấy mã mới.',
                    'Thử <strong>lấy mã mới</strong> bằng cách truy cập lại link rút gọn.'
                ]
            },
            'code_expired': {
                title: 'Mã đã hết hạn (quá 10 phút)',
                steps: [
                    'Mã xác minh chỉ có hiệu lực trong <strong>10 phút</strong> kể từ lúc hiện mã.',
                    'Truy cập lại <strong>link rút gọn</strong> để lấy mã mới.',
                    'Lần này hãy <strong>nhập mã ngay</strong> sau khi lấy được, đừng chờ quá lâu.',
                    'Nếu vẫn bị hết hạn, hãy <strong>Gửi báo lỗi</strong> để Admin kiểm tra.'
                ]
            },
            'no_code_appear': {
                title: 'Bấm lấy mã nhưng không hiện mã',
                steps: [
                    'Kiểm tra lại các bước ở phần hướng dẫn đã làm <strong>đúng thứ tự</strong> chưa?',
                    'Kiểm tra <strong>kết nối mạng</strong> của bạn.',
                    'Đảm bảo bạn đã ở trên trang web đích <strong>đủ thời gian</strong> yêu cầu.',
                    'Thử truy cập lại link rút gọn bằng trình duyệt <strong>Google Chrome</strong> và làm lại.',
                    'Nếu vẫn không hiện mã, hãy <strong>Gửi báo lỗi</strong> để chúng tôi kiểm tra.'
                ]
            },
            'not_found_google': {
                title: 'Không tìm thấy kết quả trên Google',
                steps: [
                    'Hãy đảm bảo bạn truy cập <strong>Google.com</strong> hoặc <strong>Google.com.vn</strong>.',
                    'Gõ <strong>chính xác từ khóa</strong> được yêu cầu (nên copy từ trang hướng dẫn).',
                    'Thử <strong>lướt từ trang 1 xuống trang 2, 3</strong> của kết quả tìm kiếm.',
                    'Dùng trình duyệt <strong>Chrome</strong> thay vì Safari hoặc trình duyệt khác.',
                    'Nếu vẫn không thấy, hãy <strong>Gửi báo lỗi</strong> để Admin kiểm tra nhé!'
                ]
            },
            'page_error': {
                title: 'Trang web bị lỗi/không load được',
                steps: [
                    'Đợi <strong>10-15 giây</strong> để trang load hoàn tất.',
                    'Thử <strong>refresh trang</strong> (kéo xuống trên điện thoại, hoặc F5 trên máy tính).',
                    'Kiểm tra <strong>kết nối mạng</strong> của bạn.',
                    'Thử dùng <strong>trình duyệt khác</strong> (Chrome, Firefox, Edge).',
                    'Nếu trang vẫn lỗi, hãy <strong>Gửi báo lỗi</strong> để Admin kiểm tra nhé!'
                ]
            }
        };
        
        var selectedErrorType = '';
        
        function selectErrorWithTip(el, errorType) {
            document.querySelectorAll('.error-option').forEach(function(opt) {
                opt.classList.remove('selected');
            });
            el.classList.add('selected');
            selectedErrorType = errorType;
            
            if (errorType === 'other') {
                // Hiện textarea cho lỗi khác
                document.getElementById('other-input').classList.add('show');
                document.getElementById('btn-submit-other').style.display = 'block';
                return;
            }
            
            // Ẩn step 1, hiện step 2 với tip
            document.getElementById('error-step-1').style.display = 'none';
            document.getElementById('error-step-2').style.display = 'block';
            document.getElementById('modal-footer-default').style.display = 'none';
            
            var tip = errorTips[errorType];
            var stepsHtml = '<ol>';
            tip.steps.forEach(function(step) {
                stepsHtml += '<li>' + step + '</li>';
            });
            stepsHtml += '</ol>';
            
            document.getElementById('tip-content').innerHTML = 
                '<div class="tip-title"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2" style="vertical-align:-2px"><path d="M9 18h6M10 22h4M12 2v1M12 7a4 4 0 00-4 4c0 1.5.8 2.8 2 3.4V17h4v-2.6c1.2-.6 2-1.9 2-3.4a4 4 0 00-4-4z"/></svg> Hướng dẫn khắc phục: ' + tip.title + '</div>' +
                '<div class="tip-steps">' + stepsHtml + '</div>';
            
            selectedError = tip.title;
        }
        
        function backToStep1() {
            document.getElementById('error-step-1').style.display = 'block';
            document.getElementById('error-step-2').style.display = 'none';
            document.getElementById('modal-footer-default').style.display = 'flex';
            selectedError = '';
            selectedErrorType = '';
            document.querySelectorAll('.error-option').forEach(function(opt) {
                opt.classList.remove('selected');
            });
        }
        
        function markResolved() {
            closeReportModal();
            showToast('Tuyệt vời! Chúc bạn lấy mã vượt link thành công nhé! 🎉', 'success');
        }
        
        // Giữ lại function cũ cho compatibility
        function selectError(el, error) {
            selectErrorWithTip(el, error);
        }
        
        function submitReport() {
            var message = selectedError;
            var detail = document.getElementById('report-detail')?.value?.trim() || '';
            if (detail) {
                message += ' - Chi tiết: ' + detail;
            }
            
            if (!message) {
                showToast('Vui lòng chọn loại lỗi!', 'error');
                return;
            }
            
            // Check captcha if enabled
            if (turnstileSiteKey && !reportCaptchaToken) {
                showToast('Vui lòng xác minh captcha!', 'error');
                return;
            }
            
            var btn = document.getElementById('btn-submit-report');
            btn.disabled = true;
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-2px;animation:spin 1s linear infinite"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg> Đang gửi...';
            
            var fd = new FormData();
            fd.append('action', 'sitetop_report_shortlink_error');
            fd.append('session_id', sessionId);
            fd.append('message', message);
            if (reportCaptchaToken) {
                fd.append('captcha_token', reportCaptchaToken);
            }
            
            fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Gửi báo lỗi';
                
                if (data.success) {
                    closeReportModal();
                    showToast('Đã gửi báo lỗi! Admin sẽ kiểm tra sớm nhất. Cảm ơn bạn! 🙏', 'success');
                } else {
                    showToast(data.data?.message || 'Không thể gửi báo lỗi!', 'error');
                    // Reset captcha on error
                    if (reportTurnstileWidgetId !== null && typeof turnstile !== 'undefined') {
                        try { turnstile.reset(reportTurnstileWidgetId); } catch(e) {}
                    }
                    reportCaptchaToken = '';
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Gửi báo lỗi';
                showToast('Không thể gửi báo lỗi!', 'error');
            });
        }
        
        function submitReportOther() {
            var message = document.getElementById('other-message').value.trim();
            
            if (!message) {
                showToast('Vui lòng mô tả lỗi bạn gặp phải!', 'error');
                return;
            }
            
            // Check captcha if enabled
            if (turnstileSiteKey && !reportCaptchaToken) {
                showToast('Vui lòng xác minh captcha!', 'error');
                return;
            }
            
            var btn = document.getElementById('btn-submit-other');
            btn.disabled = true;
            btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-2px;animation:spin 1s linear infinite"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg> Đang gửi...';
            
            var fd = new FormData();
            fd.append('action', 'sitetop_report_shortlink_error');
            fd.append('session_id', sessionId);
            fd.append('message', 'Lỗi khác: ' + message);
            if (reportCaptchaToken) {
                fd.append('captcha_token', reportCaptchaToken);
            }
            
            fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Gửi báo lỗi';
                
                if (data.success) {
                    closeReportModal();
                    showToast('Đã gửi báo lỗi! Admin sẽ kiểm tra sớm nhất. Cảm ơn bạn! 🙏', 'success');
                } else {
                    showToast(data.data?.message || 'Không thể gửi báo lỗi!', 'error');
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Gửi báo lỗi';
                showToast('Không thể gửi báo lỗi!', 'error');
            });
        }
        
        document.getElementById('code-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') unlockLink();
        });
        
        // Close modal on overlay click
        document.getElementById('report-modal').addEventListener('click', function(e) {
            if (e.target === this) closeReportModal();
        });
        
        // ========================================
        // INCOGNITO DETECTION - Using detectIncognito library (2024/2025)
        // https://github.com/Joe12387/detectIncognito
        // Supports: Chrome, Safari, Firefox, Edge, Brave, Opera on Desktop/iOS/Android
        // ========================================
        
        function showIncognitoOverlay(){
            var overlay = document.createElement('div');
            overlay.id = 'incognito-overlay';
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.95);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;';
            
            overlay.innerHTML = '<div style="background:#fff;border-radius:16px;padding:32px;max-width:400px;text-align:center;">'+
                '<div style="width:64px;height:64px;background:linear-gradient(135deg,#ef4444,#dc2626);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;"><svg width="32" height="32" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg></div>'+
                '<h2 style="font-size:20px;color:#991b1b;margin-bottom:12px;"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" style="vertical-align:-3px;margin-right:2px"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Trình duyệt ẩn danh</h2>'+
                '<p style="font-size:14px;color:#64748b;line-height:1.6;margin-bottom:20px;">Bạn đang truy cập bằng <b>trình duyệt ẩn danh</b>.<br>Vui lòng <b style="color:#dc2626;">tắt chế độ ẩn danh</b> và truy cập lại!</p>'+
                '<div style="font-size:12px;color:#94a3b8;background:#f8fafc;padding:12px;border-radius:8px;text-align:left;"><b><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#eab308" stroke-width="2" style="vertical-align:-2px;margin-right:1px"><path d="M9 18h6M10 22h4M12 2v1M12 7a4 4 0 00-4 4c0 1.5.8 2.8 2 3.4V17h4v-2.6c1.2-.6 2-1.9 2-3.4a4 4 0 00-4-4z"/></svg> Cách tắt:</b><br>1. Đóng tất cả tab ẩn danh<br>2. Mở trình duyệt bình thường<br>3. Truy cập lại link</div>'+
                '</div>';
            
            document.body.appendChild(overlay);
            document.body.style.overflow = 'hidden';
        }
        
        // Load và chạy detectIncognito library
        (function(){
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/gh/Joe12387/detectIncognito@main/dist/es5/detectIncognito.min.js';
            script.onload = function(){
                if(typeof detectIncognito === 'function'){
                    detectIncognito().then(function(result){
                        console.log('detectIncognito:', result.browserName, 'isPrivate:', result.isPrivate);
                        if(result.isPrivate){
                            console.log('>>> INCOGNITO DETECTED <<<');
                            showIncognitoOverlay();
                        }else{
                            console.log('>>> NORMAL MODE <<<');
                        }
                    }).catch(function(e){
                        console.log('detectIncognito error:', e);
                    });
                }
            };
            script.onerror = function(){
                console.log('Failed to load detectIncognito, skipping check');
            };
            document.head.appendChild(script);
        })();
    </script>
    
    <!-- Script detect user exit (đóng tab/tắt trình duyệt) -->
    <script>
    (function() {
        var sessionId = '<?php echo esc_js($session_id); ?>';
        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var isCompleted = false;
        
        // Đánh dấu đã hoàn thành khi verify thành công
        window.markVisitCompleted = function() {
            isCompleted = true;
        };
        
        // Hàm gửi request đánh dấu hết hạn
        function markVisitExpired() {
            if (isCompleted) return; // Đã hoàn thành thì không đánh dấu hết hạn
            
            var data = new FormData();
            data.append('action', 'sitetop_mark_visit_expired');
            data.append('session_id', sessionId);
            
            // Dùng sendBeacon để đảm bảo request được gửi khi đóng tab
            if (navigator.sendBeacon) {
                navigator.sendBeacon(ajaxUrl, data);
            } else {
                // Fallback cho trình duyệt cũ
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxUrl, false); // sync request
                xhr.send(data);
            }
        }
        
        // Detect khi user rời trang (đóng tab, tắt trình duyệt, chuyển trang)
        window.addEventListener('beforeunload', function(e) {
            markVisitExpired();
        });
        
        // Detect khi tab bị ẩn (user chuyển sang tab khác)
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                // User chuyển tab hoặc minimize - không đánh dấu hết hạn ngay
                // Chỉ đánh dấu khi thực sự đóng tab (beforeunload)
            }
        });
        
        // Detect khi user đóng tab trên mobile (pagehide event)
        window.addEventListener('pagehide', function(e) {
            markVisitExpired();
        });
    })();
    </script>
    
    <!-- Script polling check code ready status -->
    <script>
    (function() {
        var sessionId = '<?php echo esc_js($session_id); ?>';
        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var codeReady = false;
        var checkInterval = null;
        
        // ========================================
        // LƯU SESSION VÀO LOCALSTORAGE
        // Widget sẽ đọc session này để xác định mode
        // ========================================
        try {
            localStorage.setItem('tn_unlock_session', sessionId);
            localStorage.setItem('tn_unlock_time', Date.now().toString());
            localStorage.setItem('tn_campaign_type', '<?php echo esc_js($campaign_type); ?>');
            // Flag này dùng sessionStorage - tự clear khi đóng tab
            // Widget check flag này để biết user có đang trong flow shortlink không
            sessionStorage.setItem('tn_unlock_active', '1');
            console.log('Unlock session saved:', sessionId, '- campaign_type:', '<?php echo esc_js($campaign_type); ?>', '- unlock_active flag set');
        } catch(e) {
            console.warn('Cannot save unlock session to localStorage');
        }
        
        // Function check code ready status
        // Poll KHÔNG dừng ở lúc mã sẵn sàng nữa: còn phải chờ user bấm copy trên trang đích để
        // server trả mã về đây rồi tự điền. Dừng khi đã điền xong (hoặc user tự gõ tay).
        var codeFilled = false;
        function checkCodeReady() {
            if (codeFilled) return;
            
            var fd = new FormData();
            fd.append('action', 'sitetop_check_code_ready');
            fd.append('session_id', sessionId);
            
            fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.data.code_ready) return;

                if (!codeReady) {
                    codeReady = true;
                    // Mở khoá ô nhập + focus
                    var input0 = document.getElementById('code-input');
                    var btn0 = document.getElementById('btn-unlock');
                    if (input0) {
                        input0.disabled = false;
                        input0.focus();
                        input0.placeholder = 'Nhập mã tìm được vào đây để tiếp tục';
                    }
                    if (btn0) btn0.disabled = false;
                }

                // Server chỉ trả mã sau khi widget báo user đã bấm COPY trên trang đích.
                if (data.data.code) {
                    codeFilled = true;
                    if (checkInterval) { clearInterval(checkInterval); checkInterval = null; }
                    var input = document.getElementById('code-input');
                    if (input && !input.value.trim()) {
                        input.value = data.data.code;
                        input.focus();
                        try { localStorage.setItem('code_input_' + sessionId, JSON.stringify({ code: data.data.code, ts: Date.now() })); } catch(e){}
                        if (typeof showToast === 'function') showToast('Đã tự điền mã — bấm TIẾP TỤC');
                    }
                }
            })
            .catch(function(e) {
                console.log('Check code ready error:', e);
            });
        }
        
        // Start polling every 2 seconds
        checkInterval = setInterval(checkCodeReady, 2000);
        
        // Also check immediately
        checkCodeReady();
        
        // Stop polling after 10 minutes
        setTimeout(function() {
            if (checkInterval) {
                clearInterval(checkInterval);
                checkInterval = null;
            }
        }, 600000);
        
        // ========================================
        // HEARTBEAT: Giữ unlock_active = 1 khi user còn ở page
        // Gửi heartbeat mỗi 5s, nếu không nhận trong 10s → hết hạn
        // ========================================
        var heartbeatInterval = setInterval(function() {
            var fd = new FormData();
            fd.append('action', 'sitetop_unlock_heartbeat');
            fd.append('session_id', sessionId);
            navigator.sendBeacon('<?php echo admin_url('admin-ajax.php'); ?>', fd);
        }, 5000); // Mỗi 5 giây
        
        // Gửi heartbeat ngay lập tức khi load page
        (function() {
            var fd = new FormData();
            fd.append('action', 'sitetop_unlock_heartbeat');
            fd.append('session_id', sessionId);
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: fd
            });
        })();
    })();
    </script>
    <script>
    (function(){
        // Countdown timer
        var el = document.getElementById('visitTimer');
        if (el) {
            var rem0 = parseInt(el.getAttribute('data-remaining'), 10) || 0;
            if (rem0 > 0) {
                var EXPIRY = Date.now() + rem0 * 1000;
                var timeEl = document.getElementById('vcTime');
                var origTitle = document.title;
                var warned3 = false, warned1 = false;
                function fmt(s){ return Math.floor(s/60)+':'+(s%60<10?'0'+s%60:s%60); }
                function tick(){
                    var rem = Math.max(0, Math.floor((EXPIRY - Date.now()) / 1000));
                    timeEl.textContent = fmt(rem);
                    el.className = 'visit-timer' + (rem <= 0 ? ' crit' : rem <= 60 ? ' crit float' : rem <= 180 ? ' warn' : '');
                    if (document.hidden) document.title = '⏱ ' + fmt(rem) + ' - ' + origTitle;
                    else document.title = origTitle;
                    if (rem <= 60 && !warned1){ warned1=true; if(typeof showToast==='function') showToast('⚠️ Còn chưa đến 1 phút! Hoàn thành ngay.','error'); }
                    else if(rem <= 180 && !warned3){ warned3=true; if(typeof showToast==='function') showToast('Còn chưa đến 3 phút — hãy hoàn thành sớm.','error'); }
                }
                tick();
                setInterval(tick, 1000);
                document.addEventListener('visibilitychange', function(){ if(!document.hidden) tick(); });
            }
        }

        // Auto-fill code input from localStorage + DB fallback
        var input = document.getElementById('code-input');
        var sid = '<?php echo esc_js($session_id); ?>';
        if (input && sid) {
            var cacheKey = 'code_input_' + sid;
            var cached = null;
            try { cached = JSON.parse(localStorage.getItem(cacheKey) || 'null'); } catch(e){}
            if (cached && cached.code && (Date.now() - cached.ts) < 7200000) {
                if (!input.value) input.value = cached.code;
            }
            <?php
                $vt_autofill = '';
                if (!empty($current_visit->verify_code) && empty($current_visit->verified_at) && $vt_remaining > 0) {
                    $vt_autofill = $current_visit->verify_code;
                }
            ?>
            var dbCode = '<?php echo esc_js($vt_autofill); ?>';
            if (!input.value && dbCode) input.value = dbCode;

            var saveTimer;
            input.addEventListener('input', function(){
                clearTimeout(saveTimer);
                saveTimer = setTimeout(function(){
                    var val = input.value.trim();
                    if (val) localStorage.setItem(cacheKey, JSON.stringify({code:val,ts:Date.now()}));
                }, 300);
            });
            window._clearCodeCache = function(){ localStorage.removeItem(cacheKey); };

            // Mobile tab switch: re-fetch verify_code via heartbeat
            var lastFetchTs = 0;
            var hbUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
            function fetchAndAutoFill(){
                if (Date.now() - lastFetchTs < 2000) return;
                lastFetchTs = Date.now();
                if (input.value && input.value.trim().length > 0) return;
                var fd = new FormData();
                fd.append('action', 'sitetop_unlock_heartbeat');
                fd.append('session_id', sid);
                fetch(hbUrl, {method:'POST', body:fd, credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data && data.success && data.data && data.data.verify_code && !input.value) {
                        input.value = data.data.verify_code;
                        input.style.transition = 'background .3s';
                        input.style.background = '#ECFDF5';
                        setTimeout(function(){ input.style.background = ''; }, 800);
                        if (typeof showToast === 'function') showToast('Đã tự điền mã, hãy bấm MỞ KHOÁ', 'success');
                    }
                })
                .catch(function(){});
            }
            document.addEventListener('visibilitychange', function(){ if(!document.hidden) fetchAndAutoFill(); });
            window.addEventListener('focus', fetchAndAutoFill);
        }

        // Offline retry
        if (sid) {
            var pendingKey = 'pending_submit_' + sid;
            window._savePending = function(code){
                localStorage.setItem(pendingKey, JSON.stringify({code:code,ts:Date.now()}));
            };
            window._clearPending = function(){ localStorage.removeItem(pendingKey); };
            function retryPending(){
                var raw = localStorage.getItem(pendingKey);
                if (!raw) return;
                try { var p = JSON.parse(raw); } catch(e){ return; }
                if ((Date.now() - p.ts) > 600000) { localStorage.removeItem(pendingKey); return; }
                if (input && !input.value) input.value = p.code;
                if (typeof unlockLink === 'function') setTimeout(unlockLink, 500);
            }
            window.addEventListener('online', retryPending);
            if (navigator.onLine) setTimeout(retryPending, 1000);
        }
    })();
    </script>
    <script>
    (function(){
        var widgetUrl = '<?php echo esc_url( get_template_directory_uri() . "/widget.js.php" ); ?>?probe=1&t=' + Date.now();
        var xhr = new XMLHttpRequest();
        try { xhr.open('HEAD', widgetUrl, true); } catch(e) { return showBanner(); }
        xhr.timeout = 5000;
        xhr.onload = function(){ if (xhr.status >= 400) showBanner(); };
        xhr.onerror = function(){ showBanner(); };
        xhr.ontimeout = function(){ showBanner(); };
        try { xhr.send(); } catch(e) { showBanner(); }
        function showBanner(){
            var b = document.getElementById('adblock-mode2-banner');
            if (b) b.style.display = 'block';
            try {
                var fd = new FormData();
                fd.append('action', 'sitetop_track_adblock_mode2');
                fd.append('session_id', '<?php echo esc_js($session_id); ?>');
                fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: fd, credentials: 'same-origin' });
            } catch(e) {}
        }
    })();
    </script>
</body>
</html>
