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
            'label' => 'Telegram',
            'svg'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/></svg>',
            'color' => '#0088cc',
        ];
    }
    if ( $signal ) {
        $signal_url = ( strpos( $signal, 'http' ) === 0 ) ? $signal : 'https://' . $signal;
        $items[] = [
            'url'   => $signal_url,
            'label' => 'Signal',
            'svg'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12c0 1.82.53 3.53 1.45 4.97L2.05 22l5.03-1.4A9.95 9.95 0 0012 22c5.52 0 10-4.48 10-10S17.52 2 12 2z"/><circle cx="5.5" cy="4.5" r=".7"/><circle cx="3.2" cy="7" r=".55"/><circle cx="7.8" cy="2.5" r=".55"/><circle cx="19" cy="4" r=".55"/><circle cx="21" cy="6.5" r=".5"/></svg>',
            'color' => '#3a76f0',
        ];
    }
    if ( $zalo ) {
        $items[] = [
            'url'   => 'https://zalo.me/' . $zalo,
            'label' => 'Zalo',
            'svg'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.04 2 11c0 2.77 1.36 5.24 3.5 6.86V22l3.77-2.07c.88.24 1.8.37 2.73.37 5.52 0 10-4.04 10-9S17.52 2 12 2zm1.13 12.12H8.53l-.2-.6 3.27-4.42H9.27l.2-.6h4.4l.2.6-3.27 4.42h2.53l-.2.6zm3.73-2.93c0 1.6-1.1 2.93-2.53 2.93-.42 0-.8-.12-1.13-.33l.73-.53c.13.07.27.13.4.13.73 0 1.33-.87 1.33-2.07v-.13c0-1.2-.6-2.07-1.33-2.07-.13 0-.27.07-.4.13l-.73-.53c.33-.2.73-.33 1.13-.33 1.4 0 2.53 1.33 2.53 2.93v-.13z"/></svg>',
            'color' => '#0068ff',
        ];
    }
    if ( $email ) {
        $items[] = [
            'url'   => 'mailto:' . $email,
            'label' => 'Email',
            'svg'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
            'color' => '#ea4335',
        ];
    }
    ?>
    <style>
    .ln-contact-fab{position:fixed;bottom:24px;right:24px;z-index:9990}
    .ln-contact-toggle{width:56px;height:56px;border-radius:50%;background:#2563eb;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(37,99,235,.4);transition:transform .2s,box-shadow .2s;position:relative;z-index:2}
    .ln-contact-toggle::before{content:'';position:absolute;inset:-8px;border-radius:50%;background:rgba(37,99,235,.15);animation:ln-fab-pulse 2s ease-in-out infinite}
    .ln-contact-toggle:hover{transform:scale(1.08);box-shadow:0 6px 24px rgba(37,99,235,.5)}
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
            <svg width="26" height="26" viewBox="0 0 24 24" fill="white"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/><circle cx="12" cy="12" r="1"/><circle cx="8" cy="12" r="1"/><circle cx="16" cy="12" r="1"/></svg>
        </button>
    </div>
    <script>
    document.addEventListener('click',function(e){var f=document.getElementById('lnContactFab');if(f&&!f.contains(e.target))f.classList.remove('open')});
    </script>
    <?php
} );
