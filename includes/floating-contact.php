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

    $telegram = traffictop_get_option( 'contact_telegram', '' );
    $signal   = traffictop_get_option( 'contact_signal', '' );
    $zalo     = traffictop_get_option( 'contact_zalo', '' );
    $email    = traffictop_get_option( 'contact_email', '' );

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
            'svg'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
            'color' => '#6C47FF',
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
    .ln-contact-toggle{width:56px;height:56px;border-radius:50%;background:#0D4F4F;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(13,79,79,.4);transition:transform .2s,box-shadow .2s;position:relative;z-index:2}
    .ln-contact-toggle::before{content:'';position:absolute;inset:-8px;border-radius:50%;background:rgba(13,79,79,.12);animation:ln-fab-pulse 2s ease-in-out infinite}
    .ln-contact-toggle:hover{transform:scale(1.08);box-shadow:0 6px 24px rgba(13,79,79,.5)}
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
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
        </button>
    </div>
    <script>
    document.addEventListener('click',function(e){var f=document.getElementById('lnContactFab');if(f&&!f.contains(e.target))f.classList.remove('open')});
    </script>
    <?php
} );
