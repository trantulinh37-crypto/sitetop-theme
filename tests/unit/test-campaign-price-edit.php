<?php
// Logic-replica: gate giá custom + điều kiện recalc trong traffictop_ajax_admin_update_campaign()
// (includes/admin-dashboard.php). Nếu sửa logic thật, cập nhật replica này theo.

// Gate: POST price_per_view chỉ được nhận khi camp pending và giá > 0
function ttp_test_price_gate($camp_status, $posted_price, $camp_exists = true) {
    $price = floatval($posted_price);
    if (!$camp_exists || $camp_status !== 'pending' || $price <= 0) return null; // bị unset
    return $price;
}

assert_equals(1400.0, ttp_test_price_gate('pending', '1400'), 'Pending + giá hợp lệ → nhận');
assert_equals(2000.0, ttp_test_price_gate('pending', 2000), 'Pending + giá int → nhận');
assert_equals(null, ttp_test_price_gate('active', '1400'), 'Active → bỏ qua giá custom');
assert_equals(null, ttp_test_price_gate('paused', '1400'), 'Paused → bỏ qua giá custom');
assert_equals(null, ttp_test_price_gate('rejected', '1400'), 'Rejected → bỏ qua giá custom');
assert_equals(null, ttp_test_price_gate('pending', '0'), 'Giá 0 → bỏ qua');
assert_equals(null, ttp_test_price_gate('pending', '-500'), 'Giá âm → bỏ qua');
assert_equals(null, ttp_test_price_gate('pending', 'abc'), 'Giá không phải số → bỏ qua');
assert_equals(null, ttp_test_price_gate('pending', '1400', false), 'Camp không tồn tại → bỏ qua');

// Recalc từ settings chỉ khi traffic_type hoặc onsite_time THẬT SỰ đổi
function ttp_test_type_changed($post_tt, $post_os, $db_tt, $db_os) {
    $tt = $post_tt;
    $os = intval($post_os ?? $db_os ?? 70);
    return ($tt !== ($db_tt ?? '1step')) || ($os !== intval($db_os ?? 70));
}

assert_false(ttp_test_type_changed('1step', '70', '1step', 70), 'TT/onsite giữ nguyên → KHÔNG recalc (giá custom sống sót)');
assert_true(ttp_test_type_changed('2step', '70', '1step', 70), 'Đổi traffic_type → recalc');
assert_true(ttp_test_type_changed('1step', '80', '1step', 70), 'Đổi onsite → recalc');
assert_false(ttp_test_type_changed('1step', '70', null, null), 'DB NULL, POST default → coi như không đổi');
assert_false(ttp_test_type_changed('1step', '70', '1step', '70'), 'DB onsite kiểu string → so sánh int, không đổi');

echo "  ✓ campaign price edit\n";
