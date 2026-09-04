<?php
/**
 * Kích hoạt tài khoản Khách hàng (nhà quảng cáo) THỦ CÔNG.
 *
 * Khách hàng đăng ký xong KHÔNG được dùng dashboard ngay: tài khoản ở trạng thái "chờ kích hoạt"
 * (meta `sitetop_customer_pending`='1'). Họ VẪN đăng nhập được (quyết định của chủ site:
 * "cho vào, KHÓA dashboard") nhưng màn dashboard bị khóa + hiện thông báo liên hệ Admin. Admin bấm
 * "Kích hoạt" trong danh sách Khách hàng → xóa meta → mở khóa.
 *
 * Chỉ áp dụng cho role `customer` và CHỈ tài khoản đăng ký MỚI (đặt meta lúc đăng ký) → khách hàng
 * cũ (không có meta) KHÔNG bị khóa, không cần migration.
 *
 * @package sitetop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Đánh dấu 1 user đang "chờ kích hoạt" (gọi lúc đăng ký customer). */
function sitetop_customer_set_pending( $user_id ) {
	update_user_meta( (int) $user_id, 'sitetop_customer_pending', '1' );
}

/** Gỡ trạng thái chờ → tài khoản được kích hoạt. */
function sitetop_customer_activate( $user_id ) {
	delete_user_meta( (int) $user_id, 'sitetop_customer_pending' );
}

/**
 * User có đang chờ kích hoạt không. Admin (manage_options) KHÔNG bao giờ pending.
 * Không kiểm role ở đây để dùng được cả khi role chưa nạp đủ; meta chỉ đặt cho customer nên an toàn.
 */
function sitetop_customer_is_pending( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id || user_can( $user_id, 'manage_options' ) ) {
		return false;
	}
	return get_user_meta( $user_id, 'sitetop_customer_pending', true ) === '1';
}

/**
 * HTML thông báo "liên hệ Admin để kích hoạt" + các kênh liên hệ (tele/signal/zalo/email).
 * Dùng đúng option keys như floating-contact.php để chủ site cấu hình 1 chỗ.
 *
 * @param bool $boxed true = bọc trong khung có nền (dùng ở dashboard khóa); false = gọn (login).
 */
function sitetop_pending_notice_html( $boxed = true ) {
	$telegram = sitetop_get_option( 'contact_telegram', '' );
	$signal   = sitetop_get_option( 'contact_signal', '' );
	$zalo     = sitetop_get_option( 'contact_zalo', '' );
	$email    = sitetop_get_option( 'contact_email', '' );

	/* Biểu tượng vẽ thẳng bằng SVG nét, không dùng emoji: emoji mỗi hệ điều hành
	   render một kiểu, và không ăn theo màu chữ của nút. */
	$ic_gui   = '<path d="M22 2 11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/>';
	$ic_bong  = '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>';
	$ic_thu   = '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>';

	$links = array();
	if ( $telegram ) {
		$links[] = array( 'https://t.me/' . ltrim( $telegram, '@' ), 'Telegram', '#229ED9', $ic_gui );
	}
	if ( $signal ) {
		$links[] = array( ( strpos( $signal, 'http' ) === 0 ) ? $signal : 'https://' . $signal, 'Signal', '#3B45FD', $ic_bong );
	}
	if ( $zalo ) {
		$links[] = array( 'https://zalo.me/' . rawurlencode( $zalo ), 'Zalo', '#0068FF', $ic_bong );
	}
	if ( $email ) {
		$links[] = array( 'mailto:' . $email, 'Email', '#E0364B', $ic_thu );
	}

	/* Góc VUÔNG (1px) — dashboard khách hàng đặt --rad:1px cho toàn bộ giao diện.
	   Nút bo tròn 999px là ngôn ngữ của bản traffictop cũ, để lại là lạc lõng. */
	$btns = '';
	foreach ( $links as $l ) {
		$btns .= '<a href="' . esc_url( $l[0] ) . '" target="_blank" rel="noopener" '
			. 'style="display:inline-flex;align-items:center;gap:8px;padding:11px 20px;border-radius:1px;'
			. 'background:' . esc_attr( $l[2] ) . ';color:#fff;font-weight:700;font-size:13.5px;'
			. 'letter-spacing:.01em;text-decoration:none;line-height:1">'
			. '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
			. 'stroke-linecap="round" stroke-linejoin="round">' . $l[3] . '</svg>'
			. esc_html( $l[1] ) . '</a>';
	}

	$msg = 'Vui lòng liên hệ Admin để được kích hoạt tài khoản';

	if ( ! $boxed ) {
		$out = '<div style="margin-top:14px;padding:14px 16px;border-radius:8px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;text-align:center">';
		$out .= '<div style="font-weight:700;font-size:15px;margin-bottom:6px">⏳ Tài khoản đang chờ kích hoạt</div>';
		$out .= '<div style="font-size:14px;line-height:1.5">' . esc_html( $msg ) . '</div>';
		if ( $btns ) {
			$out .= '<div style="margin-top:10px">' . $btns . '</div>';
		}
		$out .= '</div>';
		return $out;
	}

	/* Dải 3 bước: cho khách thấy họ ĐANG Ở ĐÂU trong quy trình, thay vì chỉ một cái
	   emoji đồng hồ cát không nói lên điều gì. Đây là thông tin thật — đăng ký đã
	   xong, còn đúng một bước duyệt tay là chạy được chiến dịch. */
	$cham = function( $mau, $nen, $ruot, $to = false ) {
		$d = $to ? 26 : 20;
		return '<div style="flex:0 0 auto;width:' . $d . 'px;height:' . $d . 'px;border-radius:50%;'
			. 'background:' . $nen . ';border:2px solid ' . $mau . ';display:flex;align-items:center;'
			. 'justify-content:center;box-sizing:border-box">' . $ruot . '</div>';
	};
	$tick = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
	$dhcat = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14M5 2h14M17 22v-4.2a2 2 0 0 0-.6-1.4L12 12l-4.4 4.4a2 2 0 0 0-.6 1.4V22M7 2v4.2a2 2 0 0 0 .6 1.4L12 12l4.4-4.4a2 2 0 0 0 .6-1.4V2"/></svg>';

	$vach = function( $mau ) { return '<div style="flex:1;height:2px;background:' . $mau . ';margin:0 6px"></div>'; };

	$buoc  = '<div style="display:flex;align-items:center;margin:0 0 9px">';
	$buoc .= $cham( '#00A96E', '#00A96E', $tick );
	$buoc .= $vach( '#00A96E' );
	$buoc .= $cham( '#E08700', '#E08700', $dhcat, true );
	$buoc .= $vach( '#DFE5F3' );
	$buoc .= $cham( '#DFE5F3', '#fff', '' );
	$buoc .= '</div>';
	$buoc .= '<div style="display:flex;font-size:11.5px;font-weight:700;letter-spacing:.02em">';
	$buoc .= '<span style="flex:1;text-align:left;color:#00A96E">Đăng ký xong</span>';
	$buoc .= '<span style="flex:1;text-align:center;color:#E08700">Chờ kích hoạt</span>';
	$buoc .= '<span style="flex:1;text-align:right;color:#8A93AB">Chạy chiến dịch</span>';
	$buoc .= '</div>';

	/* Góc vuông 1px, navy #0A1633, xanh thép #4E80B4 — lấy thẳng bảng màu của
	   dashboard khách hàng. Bản cũ bo tròn 12px với emoji là ngôn ngữ traffictop
	   ngày trước, đứng cạnh giao diện hiện tại nhìn như của site khác. */
	$out  = '<div style="max-width:520px;margin:40px auto;background:#fff;border-radius:1px;';
	$out .= 'box-shadow:0 18px 50px rgba(10,22,51,.22);font-family:inherit;overflow:hidden">';

	$out .= '<div style="background:#0A1633;padding:16px 22px;display:flex;align-items:center;gap:11px">';
	$out .= '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#E08700" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 auto"><path d="M5 22h14M5 2h14M17 22v-4.2a2 2 0 0 0-.6-1.4L12 12l-4.4 4.4a2 2 0 0 0-.6 1.4V22M7 2v4.2a2 2 0 0 0 .6 1.4L12 12l4.4-4.4a2 2 0 0 0 .6-1.4V2"/></svg>';
	$out .= '<h2 style="margin:0;font-size:16px;font-weight:800;color:#fff;letter-spacing:-.01em">Tài khoản đang chờ kích hoạt</h2>';
	$out .= '</div>';

	$out .= '<div style="padding:22px 22px 24px">';
	$out .= $buoc;
	$out .= '<p style="margin:20px 0 0;font-size:14.5px;line-height:1.6;color:#1F2A44">' . esc_html( $msg ) . '</p>';
	$out .= '<p style="margin:7px 0 0;font-size:12.5px;line-height:1.55;color:#8A93AB">'
		. 'Kích hoạt xong, bạn đăng nhập lại là dùng được ngay.</p>';

	if ( $btns ) {
		$out .= '<div style="margin-top:18px;padding-top:16px;border-top:1px solid #ECF0FA">';
		$out .= '<div style="font-size:11px;font-weight:800;letter-spacing:.11em;text-transform:uppercase;'
			. 'color:#8A93AB;margin-bottom:10px">Liên hệ Admin</div>';
		$out .= '<div style="display:flex;flex-wrap:wrap;gap:8px">' . $btns . '</div></div>';
	} else {
		$out .= '<p style="margin-top:14px;font-size:13px;color:#8A93AB">Liên hệ quản trị viên để được hỗ trợ.</p>';
	}
	$out .= '</div></div>';
	return $out;
}

/**
 * MÀN CHỜ TOÀN TRANG cho khách hàng chưa kích hoạt.
 *
 * Trước đây là khoá MỀM: khách vẫn vào dashboard xem Tổng quan, bấm tab khác mới
 * hiện popup. Chủ site đổi ý (04/09/2026): chưa kích hoạt thì KHÔNG vào xem được
 * gì, chỉ nằm ở màn chờ này.
 *
 * Gọi TRƯỚC mọi truy vấn của dashboard rồi exit — vừa khoá chặt, vừa khỏi chạy
 * hàng chục câu hỏi cơ sở dữ liệu cho một người còn chưa được vào.
 *
 * Trang tự dựng đầu/cuối, KHÔNG gọi wp_head(): để style của theme không đè lên
 * và để màn chờ nhẹ nhất có thể. Luôn có lối ra (Đăng xuất / Về trang chủ) —
 * khoá cứng mà không có lối ra là nhốt người ta lại.
 */
function sitetop_pending_screen() {
	$notice = sitetop_pending_notice_html( true );
	$ten    = get_bloginfo( 'name' );
	nocache_headers();
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( 'Chờ kích hoạt — ' . $ten ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap">
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;background:#F5F7F9;color:#1F2A44;
     font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
     display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px 16px}
h2{font-family:'Plus Jakarta Sans','Inter',sans-serif}
.pd-top{display:flex;align-items:center;gap:9px;margin-bottom:20px}
.pd-top b{font-family:'Plus Jakarta Sans','Inter',sans-serif;font-size:17px;font-weight:800;color:#0A1633;letter-spacing:-.01em}
.pd-out{margin-top:18px;display:flex;gap:16px;font-size:12.5px}
.pd-out a{color:#5A6684;text-decoration:none;border-bottom:1px solid #DFE5F3;padding-bottom:1px}
.pd-out a:hover{color:#4E80B4;border-color:#4E80B4}
</style>
</head>
<body>
<div class="pd-top">
	<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4E80B4" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 14 4-4 3 3 5-6"/></svg>
	<b><?php echo esc_html( $ten ); ?></b>
</div>
<?php echo $notice; // đã escape bên trong hàm ?>
<div class="pd-out">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Về trang chủ</a>
	<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Đăng xuất</a>
</div>
</body>
</html>
	<?php
}

/**
 * Gate MỀM cho dashboard khách hàng chờ kích hoạt: KHÔNG khoá cả trang — cho xem Tổng quan/số dư,
 * nhưng bấm sang tab khác hoặc nút Nạp tiền/Tạo chiến dịch → hiện popup "chờ kích hoạt".
 * In ra: 1 pill nổi + 1 modal chứa notice + script chặn click (capture-phase) trên các control
 * chuyển tab (trừ tab $overview). Server vẫn chặn tạo campaign/nạp tiền (lớp bảo vệ chính).
 *
 * @param string $sel      CSS selector các control chuyển tab (vd '.tb' hoặc '.sidebar-nav-item,.bottom-nav-item').
 * @param string $overview Giá trị data-t/data-tabbtn của tab được PHÉP xem (Tổng quan). '' = chặn tất cả.
 */
function sitetop_pending_gate_html( $sel, $overview = 'overview' ) {
	$notice = sitetop_pending_notice_html( true );
	$sel_js = wp_json_encode( (string) $sel );
	$ov_js  = wp_json_encode( (string) $overview );
	ob_start();
	?>
	<div id="ttpaPill" onclick="ttpaShow()" title="Chi tiết kích hoạt" style="position:fixed;top:10px;left:50%;transform:translateX(-50%);z-index:99998;background:#f59e0b;color:#fff;padding:8px 16px;border-radius:999px;font-size:13px;font-weight:700;box-shadow:0 4px 14px rgba(245,158,11,.45);cursor:pointer;max-width:92vw;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">⏳ Tài khoản chờ kích hoạt — bấm để liên hệ Admin</div>
	<div id="ttpaModal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,.55);align-items:center;justify-content:center;padding:18px" onclick="if(event.target===this)ttpaHide()">
		<div style="max-width:560px;width:100%;position:relative">
			<button type="button" onclick="ttpaHide()" aria-label="Đóng" style="position:absolute;top:-12px;right:-12px;width:34px;height:34px;border-radius:50%;border:none;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.25);cursor:pointer;font-size:20px;line-height:1;color:#334155;z-index:1">&times;</button>
			<?php echo $notice; // đã escape trong hàm. ?>
		</div>
	</div>
	<script>
	(function(){
		var SEL=<?php echo $sel_js; ?>, OV=<?php echo $ov_js; ?>;
		var m=document.getElementById('ttpaModal');
		window.ttpaShow=function(e){ if(e&&e.preventDefault)e.preventDefault(); m.style.display='flex'; };
		window.ttpaHide=function(){ m.style.display='none'; };
		document.addEventListener('click', function(e){
			var el=e.target.closest(SEL); if(!el) return;
			var t=el.getAttribute('data-t')||el.getAttribute('data-tabbtn')||el.getAttribute('data-tab')||'';
			if(OV && t===OV) return;                 // cho phép tab Tổng quan
			e.preventDefault(); e.stopPropagation(); window.ttpaShow();
		}, true);
		// Nếu URL/khôi phục tab nhảy sang tab khác → kéo về Tổng quan.
		setTimeout(function(){
			if(!OV) return;
			var back=document.querySelector('[data-t="'+OV+'"],[data-tabbtn="'+OV+'"]');
			if(back && back.click) back.click();
		},0);
	})();
	</script>
	<?php
	return ob_get_clean();
}
