<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="auth-brand">
    <?php $ln_icon = get_option('traffictop_widget_icon',''); ?>
    <a href="<?php echo home_url(); ?>" class="auth-brand-logo">
        <?php if($ln_icon): ?><img src="<?php echo esc_url($ln_icon); ?>" width="28" height="28" alt="" style="margin-right:6px"><?php else: ?><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#E8A838" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><?php endif; ?>
        Traffictop.net
    </a>
    <h1 id="brandTitle">Chia sẻ link.<br><span>Nhận thưởng.</span></h1>
    <p id="brandDesc">Nền tảng tăng traffic từ người dùng thật. Rút gọn link để kiếm tiền hoặc mua traffic chất lượng cho website của bạn.</p>

    <div class="auth-features" id="brandFeatures">
        <div class="auth-feat">
            <div class="auth-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#E8A838" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            <div class="auth-feat-text">
                <h4>Rút tiền nhanh chóng</h4>
                <p>Thanh toán qua ngân hàng hoặc USDT, xử lý trong 24h</p>
            </div>
        </div>
        <div class="auth-feat">
            <div class="auth-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#E8A838" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
            <div class="auth-feat-text">
                <h4>Traffic người dùng thật</h4>
                <p>100% lượt truy cập từ người thật, chống gian lận tự động</p>
            </div>
        </div>
        <div class="auth-feat">
            <div class="auth-feat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#E8A838" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></div>
            <div class="auth-feat-text">
                <h4>Thống kê chi tiết</h4>
                <p>Dashboard trực quan, theo dõi thu nhập và chiến dịch real-time</p>
            </div>
        </div>
    </div>
</div>
