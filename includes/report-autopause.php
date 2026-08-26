<?php
/**
 * Report-based auto-pause — Tự tạm dừng campaign khi bị NHIỀU NGƯỜI báo lỗi.
 *
 * Khi 1 campaign bị >= N *IP KHÁC NHAU* báo lỗi trong vòng 1 giờ → tự chuyển campaign
 * sang 'paused' + gửi Telegram cho admin. Ngưỡng mặc định 5 IP/giờ (cấu hình qua option
 * `sitetop_report_autopause_threshold`). Đếm theo IP DUY NHẤT để chống 1 người spam nút
 * "Báo lỗi" nhằm phá campaign. Camp bị dừng KHÔNG tự bật lại — admin kiểm tra rồi bật thủ công.
 *
 * Camp CẦU NỐI (job từ site nguồn, thuộc tài khoản liên kết FED_CUSTOMER): pool dừng vô nghĩa
 * (site nguồn tự đẩy 'upsert' bật lại trong ~5') → chỉ GỬI CẢNH BÁO Telegram, admin xử lý bên nguồn.
 *
 * Đếm bằng transient (map ip=>timestamp, TTL 1h) — không thêm bảng/cột. Object-cache evict chỉ làm
 * kém nhạy 1 chút (đếm hụt), không gây hại.
 *
 * @package sitetop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bật/tắt tính năng (mặc định BẬT). */
function sitetop_report_autopause_enabled() {
	return (int) sitetop_get_option( 'report_autopause_enabled', 1 ) === 1;
}

/** Ngưỡng số IP khác nhau/giờ để tạm dừng (mặc định 5). */
function sitetop_report_autopause_threshold() {
	$n = (int) sitetop_get_option( 'report_autopause_threshold', 5 );
	return $n > 0 ? $n : 5;
}

/** user_id tài khoản liên kết cầu nối (bridged campaigns thuộc tài khoản này). 0 nếu không có. */
function sitetop_report_fed_customer() {
	if ( function_exists( 'ttplb_fed_customer' ) ) {
		return (int) ttplb_fed_customer();
	}
	if ( defined( 'LENTOP_BRIDGE_FED_CUSTOMER' ) && LENTOP_BRIDGE_FED_CUSTOMER ) {
		return (int) LENTOP_BRIDGE_FED_CUSTOMER;
	}
	return (int) get_option( 'ttplb_fed_customer', 0 );
}

/** Campaign có phải job cầu nối (từ site nguồn) không → pool KHÔNG dừng (nguồn tự bật lại). */
function sitetop_report_is_bridged( $campaign ) {
	$fed     = sitetop_report_fed_customer();
	$bridged = ( $fed && (int) $campaign->customer_id === $fed );
	return (bool) apply_filters( 'sitetop_report_is_bridged', $bridged, $campaign );
}

/**
 * Camp cầu nối bị báo lỗi → BÁO SITE NGUỒN (chủ camp) tạm dừng. Dùng lại hạ tầng plugin cầu nối
 * (ttplb_*) nên KHÔNG cần sửa/redeploy plugin. ref_id trong bridge = campaign_id BÊN NGUỒN. Nguồn
 * nhận tín hiệu → set 'paused' → autosync đẩy 'pause' về pool = CẢ 2 BÊN cùng dừng, không bị upsert
 * bật lại. Best-effort (ttplb_post tự ký HMAC + tự fallback WAF; blocking=false không chặn khách).
 */
function sitetop_report_signal_source_pause( $pool_cid ) {
	if ( ! function_exists( 'ttplb_map' ) || ! function_exists( 'ttplb_post' ) || ! function_exists( 'ttplb_sources' ) ) {
		return false; // Plugin cầu nối chưa cài/không active → bỏ qua (chỉ dừng ở pool).
	}
	$found_key = '';
	foreach ( ttplb_map() as $k => $cid ) { // map: "source|ref" => pool_cid
		if ( (int) $cid === (int) $pool_cid ) {
			$found_key = (string) $k;
			break;
		}
	}
	if ( '' === $found_key || false === strpos( $found_key, '|' ) ) {
		return false;
	}
	list( $source, $ref ) = explode( '|', $found_key, 2 );
	$callback = ttplb_sources()[ $source ] ?? '';
	if ( ! $callback ) {
		return false;
	}
	$direct  = function_exists( 'ttplb_sources_direct' ) ? ( ttplb_sources_direct()[ $source ] ?? '' ) : '';
	$payload = array(
		'action' => 'report_pause',
		'ref_id' => (int) $ref,
		'source' => function_exists( 'ttplb_self_host' ) ? ttplb_self_host() : '',
		'ts'     => time(),
	);
	ttplb_post( $callback, $payload, false, (string) $direct );
	return true;
}

/** Gửi Telegram cho admin (dùng token/chat của mục Báo lỗi). Best-effort. */
function sitetop_report_autopause_tele( $campaign, $distinct, $bridged ) {
	// Bật lại 25/08/2026: bot Telegram đã cấu hình xong sau khi đổi domain sang sitetop.net.
	// (Tắt tạm từ 06/08 trong lần rebrand — xem commit 910543d.)
	// sitetop_telegram_send() tự thoát khi chưa có token/chat_id nên gọi vô hại kể cả khi
	// admin chưa cấu hình bot.
	if ( ! function_exists( 'sitetop_telegram_notify_admin' ) ) {
		return;
	}
	$title = $bridged
		? '🛑 ĐÃ TẠM DỪNG camp cầu nối (pool + báo nguồn dừng theo)'
		: '🛑 ĐÃ TẠM DỪNG campaign do bị báo lỗi';
	$rows = array(
		'Website'  => wp_parse_url( home_url(), PHP_URL_HOST ),
		'Camp'     => '#' . (int) $campaign->id . ' — ' . ( $campaign->title ? $campaign->title : $campaign->keyword ),
		'Từ khoá'  => (string) $campaign->keyword,
		'URL đích' => (string) $campaign->target_url,
		'Báo lỗi'  => (int) $distinct . ' IP khác nhau / 1 giờ (ngưỡng ' . sitetop_report_autopause_threshold() . ')',
		'Xử lý'    => $bridged
			? 'Camp CẦU NỐI — đã dừng ở POOL + báo site NGUỒN dừng theo. Bật lại: bên NGUỒN.'
			: 'ĐÃ TẠM DỪNG — vào admin kiểm tra link/nội dung rồi bật lại',
		'Thời gian' => sitetop_current_time(),
	);
	sitetop_telegram_notify_admin( $title, $rows );
}

/**
 * Lõi: gọi mỗi lần có 1 báo lỗi cho campaign. Đếm IP duy nhất trong 1 giờ; đủ ngưỡng →
 * tạm dừng (nếu native) + Telegram. Chỉ hành động 1 lần/giờ/camp (cooldown transient).
 */
function sitetop_report_autopause_check( $campaign_id, $ip ) {
	$campaign_id = (int) $campaign_id;
	$ip          = (string) $ip;
	if ( ! $campaign_id || '' === $ip || ! sitetop_report_autopause_enabled() ) {
		return;
	}

	$window = HOUR_IN_SECONDS;
	$now    = time();
	$key    = 'sitetop_reperr_' . $campaign_id;
	$map    = get_transient( $key );
	if ( ! is_array( $map ) ) {
		$map = array();
	}
	foreach ( $map as $k => $ts ) {
		if ( (int) $ts < $now - $window ) {
			unset( $map[ $k ] );
		}
	}
	$map[ $ip ] = $now;
	set_transient( $key, $map, $window );

	$distinct = count( $map );
	if ( $distinct < sitetop_report_autopause_threshold() ) {
		return;
	}

	// Chống spam Telegram + pause lặp: chỉ hành động 1 lần / giờ / camp.
	$cooldown = 'sitetop_repalert_' . $campaign_id;
	if ( get_transient( $cooldown ) ) {
		return;
	}

	global $wpdb;
	$p        = $wpdb->prefix . 'sitetop_';
	$campaign = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}keyword_campaigns WHERE id = %d", $campaign_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( ! $campaign || 'active' !== $campaign->status ) {
		return; // Chỉ xử lý camp đang chạy.
	}

	set_transient( $cooldown, 1, $window );

	$bridged = sitetop_report_is_bridged( $campaign );
	if ( $bridged ) {
		// Camp cầu nối: báo site NGUỒN (chủ camp) tạm dừng → nguồn dừng + autosync 'pause' về pool.
		sitetop_report_signal_source_pause( $campaign_id );
	}
	// Tạm dừng NGAY ở pool (native: dừng hẳn; bridged: nguồn autosync xác nhận, không bị upsert bật lại).
	if ( function_exists( 'sitetop_update_campaign' ) ) {
		sitetop_update_campaign( $campaign_id, array( 'status' => 'paused' ) );
	} else {
		$wpdb->update( "{$p}keyword_campaigns", array( 'status' => 'paused' ), array( 'id' => $campaign_id ) );
	}
	delete_transient( 'sitetop_eligible_campaigns' );
	sitetop_report_autopause_tele( $campaign, $distinct, $bridged );
}

/** Gọi từ AJAX báo lỗi: tìm campaign theo session rồi kiểm tra ngưỡng. */
function sitetop_report_autopause_on_report( $session_id, $ip ) {
	$session_id = (string) $session_id;
	if ( '' === $session_id || ! $ip || ! sitetop_report_autopause_enabled() ) {
		return;
	}
	global $wpdb;
	$p   = $wpdb->prefix . 'sitetop_';
	$cid = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT campaign_id FROM {$p}shortlink_visits WHERE session_id = %s ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$session_id
	) );
	if ( $cid > 0 ) {
		sitetop_report_autopause_check( $cid, $ip );
	}
}
