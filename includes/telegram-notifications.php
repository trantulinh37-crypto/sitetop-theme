<?php
/**
 * Traffictop.net - Admin Telegram Notifications
 *
 * Đẩy các thông báo dành cho ADMIN (báo lỗi, chiến dịch mới, nạp tiền, rút tiền) về một
 * Telegram Bot của admin. Khi bot ĐÃ cấu hình (token + chat id) → gửi Telegram; CHƯA cấu hình
 * → giữ nguyên luồng email cũ (fallback).
 *
 * QUAN TRỌNG: Bot là của ADMIN. KHÔNG dùng để gửi cho END USER (xác nhận đăng ký, quên mật khẩu,
 * báo duyệt cho khách...). Các thông báo đó vẫn đi qua email như cũ.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Bot đã được cấu hình đầy đủ (có cả token + chat id) hay chưa.
 */
function traffictop_report_telegram_configured() {
	$token = trim( (string) traffictop_get_option( 'report_telegram_bot_token', '' ) );
	$chat  = trim( (string) traffictop_get_option( 'report_telegram_chat_id', '' ) );
	return ( $token !== '' && $chat !== '' );
}

/**
 * Escape giá trị động cho Telegram parse_mode=HTML (chỉ cần &, <, >).
 */
function traffictop_telegram_esc( $s ) {
	return str_replace( array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), (string) $s );
}

/**
 * Core: gửi 1 message tới Telegram Bot API (sendMessage).
 *
 * - timeout=15, redirection=3, parse_mode=HTML, disable_web_page_preview.
 * - RETRY 2 lần KHI LỖI MẠNG (wp_remote_post trả WP_Error, vd cURL 28). Lỗi từ phía Telegram
 *   (sai token/chat id) KHÔNG retry — trả lỗi ngay để admin sửa.
 *
 * @return array{success:bool,error:string}
 */
function traffictop_telegram_send( $token, $chat_id, $text ) {
	$token   = trim( (string) $token );
	$chat_id = trim( (string) $chat_id );
	if ( $token === '' || $chat_id === '' ) {
		return array( 'success' => false, 'error' => 'Chưa cấu hình Bot Token hoặc Chat ID' );
	}

	$url  = 'https://api.telegram.org/bot' . $token . '/sendMessage';
	$args = array(
		'timeout'     => 15,
		'redirection' => 3,
		'body'        => array(
			'chat_id'                  => $chat_id,
			'text'                     => $text,
			'parse_mode'               => 'HTML',
			'disable_web_page_preview' => 'true',
		),
	);

	$last_error   = '';
	$max_attempts = 3; // 1 lần đầu + 2 lần retry mạng
	for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
		$resp = wp_remote_post( $url, $args );

		if ( is_wp_error( $resp ) ) {
			// Lỗi MẠNG của host (vd cURL error 28 timeout) → thử lại.
			$last_error = $resp->get_error_message();
			continue;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( $code === 200 && is_array( $body ) && ! empty( $body['ok'] ) ) {
			return array( 'success' => true, 'error' => '' );
		}

		// Telegram phản hồi nhưng từ chối (sai token/chat id, chưa /start...) → KHÔNG retry.
		$desc = ( is_array( $body ) && ! empty( $body['description'] ) ) ? $body['description'] : ( 'HTTP ' . $code );
		return array( 'success' => false, 'error' => $desc );
	}

	return array( 'success' => false, 'error' => $last_error !== '' ? $last_error : 'Lỗi mạng không xác định' );
}

/**
 * Helper cấp cao: build message "tiêu đề + danh sách dòng" rồi gửi cho admin qua Telegram.
 *
 * @param string $title Tiêu đề (đã gồm emoji nếu muốn).
 * @param array  $rows  array( 'Nhãn' => 'Giá trị' ). Giá trị rỗng/null sẽ được bỏ qua. Mọi giá trị được HTML-escape.
 * @return bool Gửi thành công hay không.
 */
function traffictop_telegram_notify_admin( $title, $rows ) {
	$token = traffictop_get_option( 'report_telegram_bot_token', '' );
	$chat  = traffictop_get_option( 'report_telegram_chat_id', '' );

	$lines   = array();
	$lines[] = '<b>' . traffictop_telegram_esc( $title ) . '</b>';
	if ( is_array( $rows ) ) {
		foreach ( $rows as $label => $value ) {
			if ( $value === '' || $value === null ) continue;
			$lines[] = '<b>' . traffictop_telegram_esc( $label ) . ':</b> ' . traffictop_telegram_esc( $value );
		}
	}
	$text = implode( "\n", $lines );

	$res = traffictop_telegram_send( $token, $chat, $text );
	if ( ! $res['success'] ) {
		// Không chặn luồng — chỉ log để admin debug (lesson: cURL 28 = lỗi mạng host).
		error_log( 'Traffictop Telegram notify failed: ' . $res['error'] );
	}
	return $res['success'];
}

/* ============================================================
   AJAX: Test gửi Telegram (dùng giá trị đang nhập, chưa cần lưu)
   ============================================================ */
add_action( 'wp_ajax_traffictop_test_telegram', 'traffictop_ajax_test_telegram' );
function traffictop_ajax_test_telegram() {
	check_ajax_referer( 'traffictop_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

	$token = sanitize_text_field( $_POST['token'] ?? '' );
	$chat  = sanitize_text_field( $_POST['chat_id'] ?? '' );
	if ( $token === '' || $chat === '' ) wp_send_json_error( 'Vui lòng nhập đủ Bot Token và Chat ID' );

	$site = get_bloginfo( 'name' );
	$text = '<b>✅ Test thành công</b>' . "\n"
		. 'Thông báo Admin của <b>' . traffictop_telegram_esc( $site ) . '</b> sẽ được gửi về đây.' . "\n"
		. '<b>Thời gian:</b> ' . traffictop_telegram_esc( traffictop_current_time() );

	$res = traffictop_telegram_send( $token, $chat, $text );
	if ( $res['success'] ) {
		wp_send_json_success( 'Đã gửi tin nhắn test — kiểm tra Telegram của bạn!' );
	}

	// Gợi ý các lỗi cấu hình thường gặp (theo bài học thực chiến).
	$hint = $res['error'];
	$low  = strtolower( $hint );
	if ( strpos( $low, "can't send messages to the bot" ) !== false || strpos( $low, 'chat not found' ) !== false || strpos( $low, 'bot can' ) !== false ) {
		$hint .= ' — Lưu ý: Chat ID KHÔNG phải ID của bot (số trước dấu ":" trong token). Lấy Chat ID từ @userinfobot, và nhớ bấm /start bot trước.';
	} elseif ( strpos( $low, 'unauthorized' ) !== false ) {
		$hint .= ' — Bot Token sai. Kiểm tra lại token từ @BotFather.';
	} elseif ( strpos( $low, 'timed out' ) !== false || strpos( $low, 'cURL error 28' ) !== false || strpos( $low, 'could not resolve' ) !== false ) {
		$hint .= ' — Lỗi MẠNG của host (đã thử lại 3 lần). Nhờ hosting mở outbound 443 tới api.telegram.org.';
	}
	wp_send_json_error( $hint );
}
