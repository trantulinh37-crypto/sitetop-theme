<?php
/**
 * Kích hoạt tài khoản Khách hàng (nhà quảng cáo) THỦ CÔNG.
 *
 * Khách hàng đăng ký xong KHÔNG được dùng dashboard ngay: tài khoản ở trạng thái "chờ kích hoạt"
 * (meta `traffictop_customer_pending`='1'). Họ VẪN đăng nhập được (quyết định của chủ site:
 * "cho vào, KHÓA dashboard") nhưng màn dashboard bị khóa + hiện thông báo liên hệ Admin. Admin bấm
 * "Kích hoạt" trong danh sách Khách hàng → xóa meta → mở khóa.
 *
 * Chỉ áp dụng cho role `customer` và CHỈ tài khoản đăng ký MỚI (đặt meta lúc đăng ký) → khách hàng
 * cũ (không có meta) KHÔNG bị khóa, không cần migration.
 *
 * @package traffictop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Đánh dấu 1 user đang "chờ kích hoạt" (gọi lúc đăng ký customer). */
function traffictop_customer_set_pending( $user_id ) {
	update_user_meta( (int) $user_id, 'traffictop_customer_pending', '1' );
}

/** Gỡ trạng thái chờ → tài khoản được kích hoạt. */
function traffictop_customer_activate( $user_id ) {
	delete_user_meta( (int) $user_id, 'traffictop_customer_pending' );
}

/**
 * User có đang chờ kích hoạt không. Admin (manage_options) KHÔNG bao giờ pending.
 * Không kiểm role ở đây để dùng được cả khi role chưa nạp đủ; meta chỉ đặt cho customer nên an toàn.
 */
function traffictop_customer_is_pending( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id || user_can( $user_id, 'manage_options' ) ) {
		return false;
	}
	return get_user_meta( $user_id, 'traffictop_customer_pending', true ) === '1';
}

/**
 * HTML thông báo "liên hệ Admin để kích hoạt" + các kênh liên hệ (tele/signal/zalo/email).
 * Dùng đúng option keys như floating-contact.php để chủ site cấu hình 1 chỗ.
 *
 * @param bool $boxed true = bọc trong khung có nền (dùng ở dashboard khóa); false = gọn (login).
 */
function traffictop_pending_notice_html( $boxed = true ) {
	$telegram = traffictop_get_option( 'contact_telegram', '' );
	$signal   = traffictop_get_option( 'contact_signal', '' );
	$zalo     = traffictop_get_option( 'contact_zalo', '' );
	$email    = traffictop_get_option( 'contact_email', '' );

	$links = array();
	if ( $telegram ) {
		$links[] = array( 'https://t.me/' . ltrim( $telegram, '@' ), 'Telegram', '#229ED9' );
	}
	if ( $signal ) {
		$links[] = array( ( strpos( $signal, 'http' ) === 0 ) ? $signal : 'https://' . $signal, 'Signal', '#3B45FD' );
	}
	if ( $zalo ) {
		$links[] = array( 'https://zalo.me/' . rawurlencode( $zalo ), 'Zalo', '#0068FF' );
	}
	if ( $email ) {
		$links[] = array( 'mailto:' . $email, 'Email', '#F43F5E' );
	}

	$btns = '';
	foreach ( $links as $l ) {
		$btns .= '<a href="' . esc_url( $l[0] ) . '" target="_blank" rel="noopener" '
			. 'style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:999px;'
			. 'background:' . esc_attr( $l[2] ) . ';color:#fff;font-weight:600;font-size:14px;text-decoration:none;margin:4px">'
			. esc_html( $l[1] ) . '</a>';
	}

	$msg = 'Vui lòng liên hệ Admin để được kích hoạt tài khoản ngay sau 2 phút!';

	if ( ! $boxed ) {
		$out = '<div style="margin-top:14px;padding:14px 16px;border-radius:12px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;text-align:center">';
		$out .= '<div style="font-weight:700;font-size:15px;margin-bottom:6px">⏳ Tài khoản đang chờ kích hoạt</div>';
		$out .= '<div style="font-size:14px;line-height:1.5">' . esc_html( $msg ) . '</div>';
		if ( $btns ) {
			$out .= '<div style="margin-top:10px">' . $btns . '</div>';
		}
		$out .= '</div>';
		return $out;
	}

	$out  = '<div style="max-width:560px;margin:40px auto;padding:32px 26px;border-radius:18px;background:#fff;';
	$out .= 'box-shadow:0 12px 40px rgba(0,0,0,.10);text-align:center;font-family:inherit">';
	$out .= '<div style="width:72px;height:72px;margin:0 auto 18px;border-radius:50%;background:#fffbeb;';
	$out .= 'display:flex;align-items:center;justify-content:center;font-size:36px">⏳</div>';
	$out .= '<h2 style="margin:0 0 10px;font-size:20px;color:#1e293b">Tài khoản đang chờ kích hoạt</h2>';
	$out .= '<p style="margin:0 0 6px;font-size:15px;line-height:1.6;color:#475569">' . esc_html( $msg ) . '</p>';
	if ( $btns ) {
		$out .= '<div style="margin-top:18px;display:flex;flex-wrap:wrap;justify-content:center">' . $btns . '</div>';
	} else {
		$out .= '<p style="margin-top:14px;font-size:13px;color:#94a3b8">Liên hệ quản trị viên để được hỗ trợ.</p>';
	}
	$out .= '</div>';
	return $out;
}
