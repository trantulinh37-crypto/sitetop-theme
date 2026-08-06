<?php
/**
 * Floating Contact Button (Telegram/Signal/Zalo/Email)
 * Hiển thị trên homepage, user dashboard, customer dashboard
 * Tách từ functions.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_footer', function() {
    // Chỉ hiện trên front-end (không admin)
    if ( is_admin() ) return;

    $telegram = sitetop_get_option( 'contact_telegram', '' );
    $signal   = sitetop_get_option( 'contact_signal', '' );
    $zalo     = sitetop_get_option( 'contact_zalo', '' );
    $email    = sitetop_get_option( 'contact_email', '' );

    // Cần ít nhất 1 kênh liên hệ
    if ( ! $telegram && ! $signal && ! $zalo && ! $email ) return;

    $items = [];
    if ( $telegram ) {
        $tg_user = ltrim( $telegram, '@' );
        $items[] = [
            'url'   => 'https://t.me/' . $tg_user,
            'label' => 'Tele',
            'svg'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/></svg>',
            'color' => '#229ED9',
        ];
    }
    if ( $signal ) {
        $signal_url = ( strpos( $signal, 'http' ) === 0 ) ? $signal : 'https://' . $signal;
        $items[] = [
            'url'   => $signal_url,
            'label' => 'Signal',
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16" fill="white"><path d="M0.0618938 7H1.07089C1.22013 5.95667 1.59943 4.98756 2.15575 4.14571L1.43586 3.42581C0.711022 4.46404 0.226214 5.68225 0.0618938 7Z"/><path d="M2.33812 2.34818L3.04523 3.05528C3.60952 2.48985 4.26999 2.02042 5 1.67363L5 0.581517C3.99507 0.988303 3.09161 1.59336 2.33812 2.34818Z"/><path d="M6 0.252035L6 1.28988C6.63371 1.10128 7.30503 1 8 1C8.33952 1 8.6734 1.02417 9 1.07089V0.0618938C8.67241 0.0210433 8.33866 0 8 0C7.3094 0 6.63924 0.087506 6 0.252035Z"/><path d="M10 0.252035V1.28988C10.899 1.55744 11.7223 2.00074 12.4301 2.57994L13.1403 1.86974C12.2407 1.11456 11.1723 0.55377 10 0.252035Z"/><path d="M14.1303 2.85969L13.4201 3.56989C13.9993 4.27768 14.4426 5.101 14.7101 6L15.748 6C15.4462 4.82768 14.8854 3.75936 14.1303 2.85969Z"/><path d="M15.9381 7L14.9291 7C14.9758 7.3266 15 7.66048 15 8C15 8.69497 14.8987 9.36629 14.7101 10L15.748 10C15.9125 9.36076 16 8.6906 16 8C16 7.66134 15.979 7.32759 15.9381 7Z"/><path d="M15.4185 11L14.3264 11C13.9796 11.73 13.5102 12.3905 12.9447 12.9548L13.6518 13.6619C14.4066 12.9084 15.0117 12.0049 15.4185 11Z"/><path d="M12.5742 14.5641L11.8543 13.8442C11.0124 14.4006 10.0433 14.7799 9 14.9291V15.9381C10.3177 15.7738 11.536 15.289 12.5742 14.5641Z"/><path d="M8 16V15C6.92554 15 5.90876 14.7583 5 14.3265L5 15.4183C5.92687 15.7935 6.93978 16 8 16Z"/><path d="M4 15.7822L4 14.7803L1.31716 14.948C1.16703 14.9573 1.04267 14.833 1.05205 14.6828L1.21973 12H0.217779L0.0539996 14.6205C0.00708404 15.3711 0.628888 15.9929 1.37954 15.946L4 15.7822Z"/><path d="M0.581662 11H1.6735C1.24175 10.0912 1 9.07446 1 8H0C0 9.06022 0.206493 10.0731 0.581662 11Z"/><path d="M8.00004 14C11.3137 14 14 11.3137 14 8C14 4.68631 11.3137 2.00003 8.00004 2.00003C4.68634 2.00003 2.00006 4.68631 2.00006 8C2.00006 8.81893 2.16412 9.59954 2.46118 10.3108L2.00003 14L5.68926 13.5388C6.40048 13.8359 7.1811 14 8.00004 14Z"/></svg>',
            'color' => '#3B45FD',
        ];
    }
    if ( $zalo ) {
        $items[] = [
            'url'   => 'https://zalo.me/' . $zalo,
            'label' => 'Zalo',
            'svg'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>',
            'color' => '#0068FF',
        ];
    }
    if ( $email ) {
        $items[] = [
            'url'   => 'mailto:' . $email,
            'label' => 'Email',
            'svg'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"/></svg>',
            'color' => '#F43F5E',
        ];
    }
    ?>
    <style>
    .ln-contact-fab{position:fixed;bottom:24px;right:24px;z-index:9990}
    .ln-contact-toggle{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#0057FF,#00C6FF);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(0,87,255,.4);transition:transform .2s,box-shadow .2s;position:relative;z-index:2}
    .ln-contact-toggle::before{content:'';position:absolute;inset:-8px;border-radius:50%;background:rgba(0,87,255,.12);animation:ln-fab-pulse 2s ease-in-out infinite}
    .ln-contact-toggle:hover{transform:scale(1.08);box-shadow:0 6px 24px rgba(0,87,255,.5)}
    .ln-contact-toggle svg{transition:transform .3s}
    .ln-contact-fab.open .ln-contact-toggle svg{transform:rotate(90deg)}
    .ln-contact-items{position:absolute;bottom:68px;right:0;display:flex;flex-direction:column;gap:10px;align-items:flex-end;opacity:0;visibility:hidden;transform:translateY(10px);transition:all .25s ease}
    .ln-contact-fab.open .ln-contact-items{opacity:1;visibility:visible;transform:translateY(0)}
    .ln-contact-item{display:flex;align-items:center;gap:10px;text-decoration:none}
    .ln-contact-item-label{background:#fff;color:#333;font-size:13px;font-weight:600;padding:6px 14px;border-radius:20px;box-shadow:0 2px 8px rgba(0,0,0,.12);white-space:nowrap;opacity:0;transform:translateX(8px);transition:all .2s}
    .ln-contact-fab.open .ln-contact-item-label{opacity:1;transform:translateX(0)}
    .ln-contact-item-icon{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.15);transition:transform .2s;flex-shrink:0}
    .ln-contact-item-icon:hover{transform:scale(1.1)}
    @keyframes ln-fab-pulse{0%,100%{transform:scale(1);opacity:.6}50%{transform:scale(1.15);opacity:0}}
    @media(max-width:768px){.ln-contact-fab{bottom:74px;right:16px}.ln-contact-toggle{width:50px;height:50px}.ln-contact-item-icon{width:40px;height:40px}}
    </style>
    <div class="ln-contact-fab" id="lnContactFab">
        <div class="ln-contact-items">
            <?php foreach ( $items as $item ): ?>
            <a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener" class="ln-contact-item">
                <span class="ln-contact-item-label"><?php echo esc_html( $item['label'] ); ?></span>
                <span class="ln-contact-item-icon" style="background:<?php echo esc_attr( $item['color'] ); ?>"><?php echo $item['svg']; ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <button class="ln-contact-toggle" onclick="this.parentElement.classList.toggle('open')" aria-label="Liên hệ">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5a7 7 0 0 1 7-7h4a7 7 0 0 1 7 7v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/><path d="M19 17v1a2 2 0 0 1-2 2h-2"/></svg>
        </button>
    </div>
    <script>
    document.addEventListener('click',function(e){var f=document.getElementById('lnContactFab');if(f&&!f.contains(e.target))f.classList.remove('open')});
    </script>
    <?php
} );
