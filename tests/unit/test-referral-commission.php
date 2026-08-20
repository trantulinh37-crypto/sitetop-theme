<?php
// Engine hoa hong referral (includes/referral-management.php). Sao lai dung nhanh quyet
// dinh cua sitetop_get_active_referrer_id() + sitetop_pay_referral_commission(): file that
// can $wpdb/get_user_meta/WordPress that nen khong goi truc tiep duoc trong bootstrap nay.

// --- sitetop_get_active_referrer_id(): co duoc tra hoa hong khong? ---
$active_referrer = function ($enabled, $referrer_id, $user_id, $referrer_exists, $days, $referred_at_ts, $now_ts) {
    if (!$enabled) return 0;
    if ($referrer_id <= 0 || $referrer_id === $user_id) return 0;
    if (!$referrer_exists) return 0;
    if ($days > 0 && $referred_at_ts && ($now_ts - $referred_at_ts) > $days * 86400) return 0;
    return $referrer_id;
};

assert_equals(0,  $active_referrer(false, 5, 1, true, 0, 0, 0),                 'Tat cong tac referral -> khong tra');
assert_equals(0,  $active_referrer(true,  0, 1, true, 0, 0, 0),                 'Khong co referrer_id -> khong tra');
assert_equals(0,  $active_referrer(true,  1, 1, true, 0, 0, 0),                 'Tu gioi thieu chinh minh -> khong tra');
assert_equals(0,  $active_referrer(true,  5, 1, false, 0, 0, 0),                'Referrer da bi xoa tai khoan -> khong tra');
assert_equals(5,  $active_referrer(true,  5, 1, true, 0, 0, 0),                 'Thoi han = 0 (vinh vien) -> luon tra');
$now = strtotime('2026-08-20 00:00:00');
assert_equals(5,  $active_referrer(true, 5, 1, true, 30, strtotime('2026-08-01 00:00:00'), $now), '19 ngay, han 30 -> con trong han, van tra');
assert_equals(0,  $active_referrer(true, 5, 1, true, 30, strtotime('2026-07-01 00:00:00'), $now), '50 ngay, han 30 -> het han, khong tra');
assert_equals(5,  $active_referrer(true, 5, 1, true, 30, strtotime('2026-07-21 00:00:00'), $now), 'Dung 30 ngay tron -> con tinh (chua vuot qua)');

// --- sitetop_pay_referral_commission(): loc theo $type + tinh so tien ---
$commission_amount = function ($type, $amount, $pct) {
    if ($type !== 'shortlink_reward') return 0; // chan hoa hong-tren-hoa-hong + withdraw/refund
    if ($pct <= 0) return 0;
    return (int) round($amount * $pct / 100);
};

assert_equals(0,    $commission_amount('referral_commission', 100000, 20), 'Khong tra hoa hong tren chinh hoa hong (chan de quy)');
assert_equals(0,    $commission_amount('withdraw', 100000, 20),            'Rut tien khong sinh hoa hong');
assert_equals(0,    $commission_amount('refund', 100000, 20),              'Hoan tien khong sinh hoa hong');
assert_equals(0,    $commission_amount('shortlink_reward', 100000, 0),     'Hoa hong % = 0 -> khong tra (khong chia cho 0 / khong tra 0d)');
assert_equals(20000, $commission_amount('shortlink_reward', 100000, 20),   'Thuong 100.000d, hoa hong 20% -> 20.000d');
assert_equals(1,     $commission_amount('shortlink_reward', 3, 20),        'Lam tron: 3d x 20% = 0.6 -> lam tron len 1d, khong mat trang');

// --- sitetop_get_referral_balance_amount(): kha dung = kiem - da rut - dang cho, khong am ---
$referral_available = function ($earned, $withdrawn, $pending) {
    return max(0, $earned - $withdrawn - $pending);
};
assert_equals(20000, $referral_available(20000, 0, 0),      'Chua rut lan nao -> kha dung = tong kiem');
assert_equals(0,     $referral_available(20000, 20000, 0),  'Da rut het (completed) -> kha dung = 0');
assert_equals(0,     $referral_available(20000, 0, 20000),  'Dang cho duyet -> tru luon, khong cho rut trung');
assert_equals(0,     $referral_available(20000, 25000, 0),  'Khong am du withdrawn > earned (khong nen xay ra, van phai an toan)');

// --- sitetop_submit_referral_withdrawal(): dieu kien duoc gui lenh rut hoa hong ---
$can_submit_referral_withdraw = function ($amount, $available, $min_payout, $has_pending_referral_wd) {
    if ($amount <= 0) return false;
    if ($amount < $min_payout) return false;
    if ($amount > $available) return false;
    if ($has_pending_referral_wd) return false; // chi chan cheo VOI CHINH lenh hoa hong dang cho
    return true;
};
assert_false($can_submit_referral_withdraw(30000, 50000, 50000, false), 'Duoi nguong toi thieu -> tu choi, du con du kha dung');
assert_true ($can_submit_referral_withdraw(50000, 50000, 50000, false), 'Dung bang nguong toi thieu -> duoc');
assert_false($can_submit_referral_withdraw(60000, 50000, 50000, false), 'Vuot kha dung -> tu choi');
assert_false($can_submit_referral_withdraw(50000, 50000, 50000, true),  'Dang co lenh hoa hong cho duyet -> tu choi lenh moi');

echo "  ✓ referral-commission\n";
