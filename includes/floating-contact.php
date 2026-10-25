<?php
/**
 * Thanh liên hệ cuối trang (Telegram / Signal / Facebook / Zalo / Email)
 * Hiển thị trên homepage, user dashboard, customer dashboard, đăng nhập, đăng ký...
 * — mọi trang front-end gọi wp_footer().
 *
 * 25/10/2026: thay nút nổi góc phải (bấm mới bung Tele/Signal) bằng một dải ngang
 * nằm cuối trang theo mẫu chủ site gửi: nền navy, viền cam phía trên, icon tròn nền kem
 * viền cam, câu "Liên hệ Tele: @… để mua traffic user". Dải nằm TRONG dòng chảy trang
 * (không position:fixed) nên không đè lên nội dung hay thanh điều hướng đáy của dashboard.
 * Option giữ nguyên tên cũ (contact_*) — cấu hình ở tab Cài đặt → Integrations.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* In thanh liên hệ. Trang chủ gọi THẲNG hàm này ở chỗ dòng bản quyền (index.php) để dải
   nằm đúng đáy bố cục một màn hình; các trang khác do wp_footer in. Cờ tĩnh chống in hai lần. */
function sitetop_thanh_lien_he() {
    static $da_in = false;
    if ( $da_in || is_admin() ) return;
    $da_in = true;

    $telegram = sitetop_get_option( 'contact_telegram', '' );
    $signal   = sitetop_get_option( 'contact_signal', '' );
    $facebook = sitetop_get_option( 'contact_facebook', '' );
    $zalo     = sitetop_get_option( 'contact_zalo', '' );
    $email    = sitetop_get_option( 'contact_email', '' );


    $tg_user = ltrim( trim( $telegram ), '@' );
    $items   = [];
    if ( $tg_user ) {
        $items[] = [
            'url'   => 'https://t.me/' . $tg_user,
            'label' => 'Telegram',
            'svg'   => '<svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor"/><path d="M17.4 7.3 5.9 11.7c-.8.3-.8.8-.1 1l2.9.9 1.1 3.5c.1.4.3.5.6.5.2 0 .4-.1.6-.3l1.5-1.4 3 2.2c.6.3 1 .2 1.1-.5l2-9.3c.2-.8-.3-1.2-.9-1zm-1.6 2.3-5.4 4.9-.2 2.3-.8-2.7 6.1-3.9c.3-.2.5-.1.3.1z" fill="#fff"/></svg>',
        ];
    }
    if ( $signal ) {
        $items[] = [
            'url'   => ( strpos( $signal, 'http' ) === 0 ) ? $signal : 'https://' . $signal,
            'label' => 'Signal',
            'svg'   => '<svg width="28" height="28" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M0.0618938 7H1.07089C1.22013 5.95667 1.59943 4.98756 2.15575 4.14571L1.43586 3.42581C0.711022 4.46404 0.226214 5.68225 0.0618938 7Z"/><path d="M2.33812 2.34818L3.04523 3.05528C3.60952 2.48985 4.26999 2.02042 5 1.67363L5 0.581517C3.99507 0.988303 3.09161 1.59336 2.33812 2.34818Z"/><path d="M6 0.252035L6 1.28988C6.63371 1.10128 7.30503 1 8 1C8.33952 1 8.6734 1.02417 9 1.07089V0.0618938C8.67241 0.0210433 8.33866 0 8 0C7.3094 0 6.63924 0.087506 6 0.252035Z"/><path d="M10 0.252035V1.28988C10.899 1.55744 11.7223 2.00074 12.4301 2.57994L13.1403 1.86974C12.2407 1.11456 11.1723 0.55377 10 0.252035Z"/><path d="M14.1303 2.85969L13.4201 3.56989C13.9993 4.27768 14.4426 5.101 14.7101 6L15.748 6C15.4462 4.82768 14.8854 3.75936 14.1303 2.85969Z"/><path d="M15.9381 7L14.9291 7C14.9758 7.3266 15 7.66048 15 8C15 8.69497 14.8987 9.36629 14.7101 10L15.748 10C15.9125 9.36076 16 8.6906 16 8C16 7.66134 15.979 7.32759 15.9381 7Z"/><path d="M15.4185 11L14.3264 11C13.9796 11.73 13.5102 12.3905 12.9447 12.9548L13.6518 13.6619C14.4066 12.9084 15.0117 12.0049 15.4185 11Z"/><path d="M12.5742 14.5641L11.8543 13.8442C11.0124 14.4006 10.0433 14.7799 9 14.9291V15.9381C10.3177 15.7738 11.536 15.289 12.5742 14.5641Z"/><path d="M8 16V15C6.92554 15 5.90876 14.7583 5 14.3265L5 15.4183C5.92687 15.7935 6.93978 16 8 16Z"/><path d="M4 15.7822L4 14.7803L1.31716 14.948C1.16703 14.9573 1.04267 14.833 1.05205 14.6828L1.21973 12H0.217779L0.0539996 14.6205C0.00708404 15.3711 0.628888 15.9929 1.37954 15.946L4 15.7822Z"/><path d="M0.581662 11H1.6735C1.24175 10.0912 1 9.07446 1 8H0C0 9.06022 0.206493 10.0731 0.581662 11Z"/><path d="M8.00004 14C11.3137 14 14 11.3137 14 8C14 4.68631 11.3137 2.00003 8.00004 2.00003C4.68634 2.00003 2.00006 4.68631 2.00006 8C2.00006 8.81893 2.16412 9.59954 2.46118 10.3108L2.00003 14L5.68926 13.5388C6.40048 13.8359 7.1811 14 8.00004 14Z"/></svg>',
        ];
    }
    if ( $facebook ) {
        $items[] = [
            'url'   => ( strpos( $facebook, 'http' ) === 0 ) ? $facebook : 'https://facebook.com/' . ltrim( $facebook, '@/' ),
            'label' => 'Facebook',
            'svg'   => '<svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14.2 22v-8.2h2.8l.4-3.3h-3.2V8.4c0-.9.3-1.6 1.6-1.6h1.7V3.9c-.3 0-1.3-.1-2.5-.1-2.5 0-4.1 1.5-4.1 4.3v2.4H8v3.3h2.9V22h3.3z"/></svg>',
        ];
    }
    if ( $zalo ) {
        $items[] = [
            'url'   => 'https://zalo.me/' . $zalo,
            'label' => 'Zalo',
            'svg'   => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>',
        ];
    }
    if ( $email ) {
        $items[] = [
            'url'   => 'mailto:' . $email,
            'label' => 'Email',
            'svg'   => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
        ];
    }
    ?>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800&display=swap" rel="stylesheet">
    <style>
    /* MỘT DÒNG: bản quyền · icon · câu liên hệ. Cỡ chữ liên hệ = cỡ bản quyền + 5px
       (chủ site chốt 25/10: 13px → 18px; màn thấp 11px → 16px như .ln-copyright cũ). */
    .st-lienhe{background:#0A1433;border-top:3px solid #F5B841;padding:10px 0;position:relative;z-index:1}
    .st-lienhe-in{max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:center;gap:10px 18px;flex-wrap:wrap}
    .st-lienhe-copy{margin:0;font-size:13px;color:#6B7E99;white-space:nowrap}
    .st-lienhe-icons{display:flex;gap:10px;flex-shrink:0}
    .st-lienhe-icon{width:34px;height:34px;border-radius:50%;background:#FFF4DC;border:2px solid #F5B841;color:#0A1433;display:flex;align-items:center;justify-content:center;text-decoration:none;transition:transform .2s,box-shadow .2s}
    .st-lienhe-icon svg{width:18px;height:18px}
    .st-lienhe-icon:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(245,184,65,.35)}
    .st-lienhe-icon:focus-visible{outline:3px solid #F5B841;outline-offset:3px}
    .st-lienhe-text{margin:0;color:#DCE6FA;font-family:'Montserrat','Plus Jakarta Sans',system-ui,sans-serif;font-size:18px;font-weight:700;line-height:1.35}
    .st-lienhe-text a{color:#F5B841;text-decoration:none}
    .st-lienhe-text a:hover{text-decoration:underline}
    body.home .st-lienhe{margin-top:auto}
    /* Dashboard user/khách có sidebar position:fixed bên trái (> 768px) — dải phải chừa
       đúng bề rộng đó, không thì sidebar đè lên icon. */
    @media(min-width:769px){body:has(.sidebar) .st-lienhe{margin-left:var(--sidebar-w,248px)}}
    @media(max-width:768px){.st-lienhe-in{padding:0 16px}}
    @media(max-width:600px),(max-height:520px){
        .st-lienhe{padding:7px 0}
        .st-lienhe-copy{font-size:11px}
        .st-lienhe-icon{width:30px;height:30px}
        .st-lienhe-icon svg{width:16px;height:16px}
        .st-lienhe-text{font-size:16px;text-align:center;text-wrap:balance}
    }
    </style>
    <div class="st-lienhe" id="stLienHe">
        <div class="st-lienhe-in">
            <p class="st-lienhe-copy">Copyright &copy;<?php echo esc_html( get_bloginfo( 'name' ) ); ?> <?php echo esc_html( date_i18n( 'Y' ) ); ?></p>
            <?php if ( $items ): ?>
            <div class="st-lienhe-icons">
                <?php foreach ( $items as $item ): ?>
                <a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener" class="st-lienhe-icon" aria-label="<?php echo esc_attr( $item['label'] ); ?>" title="<?php echo esc_attr( $item['label'] ); ?>"><?php echo $item['svg']; ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ( $tg_user ): ?>
            <p class="st-lienhe-text">Liên hệ Tele: <a href="<?php echo esc_url( 'https://t.me/' . $tg_user ); ?>" target="_blank" rel="noopener">@<?php echo esc_html( $tg_user ); ?></a> để mua traffic user</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
add_action( 'wp_footer', 'sitetop_thanh_lien_he' );
