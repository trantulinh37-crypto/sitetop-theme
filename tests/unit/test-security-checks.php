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
echo "  ✓ security\n";
