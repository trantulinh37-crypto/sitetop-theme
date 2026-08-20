<?php
$now = strtotime(sitetop_current_time()); $t = time();
assert_true(abs($now-$t)<5, 'Timezone ok');
$c = strtotime('2026-01-01 10:00:00'); $n = strtotime('2026-01-01 10:01:05');
$e = $n - $c; $req = max(70-5, 10);
assert_true($e >= $req, 'Onsite passed at 65s');
assert_false(30 >= $req, 'Onsite blocked at 30s');

// Cổng captcha (shortlink-verification.php ~:230): chặn thưởng CHỈ khi
// không có captcha_ok VÀ mã không cấp qua cầu nối (marker lentop_/trafficop_
// widget_code_ready do plugin ttp-lentop-bridge set — theme không bao giờ set).
$captcha_gate_blocks = function ($captcha_ok, $bridged_code) {
    return !$captcha_ok && !$bridged_code;
};
assert_false($captcha_gate_blocks(true,  false), 'Captcha solved (widget ta) -> paid');
assert_false($captcha_gate_blocks(false, true),  'Bridged code (camp dethito) -> paid, khong doi captcha');
assert_false($captcha_gate_blocks(true,  true),  'Ca hai -> paid');
assert_true($captcha_gate_blocks(false, false),  'Khong captcha + khong bridge -> captcha_unverified');

// Nhận diện camp cầu nối theo tiền tố tiêu đề '[host#ref]' (sitetop_is_bridge_campaign).
$bridge_title = function ($t) { return (bool) preg_match('/^\[[^#\]]+#\d+\]/', (string) $t); };
assert_true($bridge_title('[dethitoanthpt.com#123] Cửa cuốn khe thoáng'), 'Title job cau noi -> bridge');
assert_false($bridge_title('Cửa cuốn khe thoáng [khuyến mãi #1]'),        'Prefix giua chung -> khong bridge');
assert_false($bridge_title('Camp thuong cua customer'),                    'Title thuong -> khong bridge');
// Cổng captcha nút TIẾP TỤC (shortlink-ajax.php) — sao lại đúng nhánh thoát sớm của
// sitetop_verify_turnstile(). Bài học từ sự cố widget_captcha_enabled: gate bật nhầm khi
// chưa cấu hình sẽ chặn NỘP MÃ của mọi user mà không ai hay -> phải mặc định thông.
$ts_pass = function ($enabled, $site, $secret, $token) {
    if (!$enabled || $secret === '' || $site === '') return true; // chua cau hinh -> bo qua
    if ($token === '') return false;                              // bat roi ma khong co token
    return 'goi_cloudflare';                                      // moi thuc su verify
};
assert_true($ts_pass(0, '', '', ''),                  'Cong tac tat + chua co key -> thong (mac dinh)');
assert_true($ts_pass(0, '0xSITE', '0xSEC', ''),       'Co key nhung cong tac tat -> thong');
assert_true($ts_pass(1, '', '0xSEC', ''),             'Bat nhung thieu site key -> thong, khong chan oan');
assert_true($ts_pass(1, '0xSITE', '', ''),            'Bat nhung thieu secret key -> thong, khong chan oan');
assert_false($ts_pass(1, '0xSITE', '0xSEC', ''),      'Cau hinh du + khong token -> chan');
assert_equals('goi_cloudflare', $ts_pass(1, '0xSITE', '0xSEC', 'tok'), 'Cau hinh du + co token -> verify that');

// Tai khoan quang cao (role customer) khong duoc di cua publisher (/user, tao link, rut tien).
// Chieu nguoc cua guard o page-customer-dashboard.php (su co 02/07/2026). Sao lai dung nhanh
// cua sitetop_is_advertiser_account(): admin phai duoc mien tru TRUOC khi xet role, vi khoi
// CUSTOM ROLES gan them role 'customer' cho MOI administrator -> xet role truoc se khoa ca admin.
$is_advertiser = function ($roles, $is_admin) {
    if ($is_admin) return false;                            // admin di duoc ca hai khu
    return in_array('customer', (array) $roles, true);
};
assert_true ($is_advertiser(array('customer'), false),                'Khach hang -> chan khoi khu publisher');
assert_false($is_advertiser(array('subscriber'), false),              'Publisher -> vao duoc khu publisher');
assert_false($is_advertiser(array('administrator','customer'), true), 'Admin co san role customer -> KHONG bi chan');
assert_false($is_advertiser(array(), false),                          'Khong role -> khong phai tai khoan quang cao');
assert_true ($is_advertiser(array('subscriber','customer'), false),   'Kiem ca hai role -> van tinh la quang cao');

echo "  ✓ security\n";
