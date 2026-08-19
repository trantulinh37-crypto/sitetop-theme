<?php
/* Sửa camp "Mã cố định" từ tài khoản khách hàng (customer-campaign-ajax.php).
   Lỗi gốc: handler sửa camp không hề đọc fixed_code — form tạo có ô mã, form sửa thì không,
   nên set mã xong bấm lưu là mã không hiện. Nặng hơn: đổi camp sang nocode vẫn ghi được
   traffic_type='nocode' kèm fixed_code rỗng → camp chạy nhưng user không bao giờ lấy được mã. */

// Chép đúng nhánh quyết định của handler sau khi sửa.
$edit = function ($camp, $post) {
    $data = array();
    if ( isset( $post['traffic_type'] ) && in_array( $post['traffic_type'], array('1step','2step','nocode') ) ) {
        $data['traffic_type'] = $post['traffic_type'];
    }
    if ( isset( $post['fixed_code'] ) ) {
        $data['fixed_code'] = trim( $post['fixed_code'] );
    }
    $final_tt = $data['traffic_type'] ?? $camp['traffic_type'] ?? '1step';
    if ( $final_tt === 'nocode' ) {
        $final_fc = trim( (string) ( $data['fixed_code'] ?? $camp['fixed_code'] ?? '' ) );
        if ( $final_fc === '' ) return 'CHAN';
    }
    return $data;
};

$camp_nocode = array('traffic_type'=>'nocode','fixed_code'=>'ABC123');
$camp_1step  = array('traffic_type'=>'1step','fixed_code'=>null);

// 1. Sửa mã của camp nocode -> phải ghi mã mới (lỗi cũ: bỏ qua hoàn toàn)
$r = $edit($camp_nocode, array('traffic_type'=>'nocode','fixed_code'=>'XYZ789'));
assert_equals('XYZ789', $r['fixed_code'] ?? null, 'Sua ma camp nocode -> ghi ma moi');

// 2. Sửa field khác, không gửi fixed_code -> giữ nguyên mã cũ, không ghi đè rỗng
$r = $edit($camp_nocode, array('traffic_type'=>'nocode'));
assert_false(array_key_exists('fixed_code', $r), 'Khong gui fixed_code -> khong dung toi cot do');

// 3. Đổi camp 1 bước sang nocode mà không có mã -> PHẢI chặn (lỗ hổng cũ)
assert_equals('CHAN', $edit($camp_1step, array('traffic_type'=>'nocode')),
    'Doi sang nocode khong co ma -> chan');
assert_equals('CHAN', $edit($camp_1step, array('traffic_type'=>'nocode','fixed_code'=>'')),
    'Doi sang nocode voi ma rong -> chan');
assert_equals('CHAN', $edit($camp_1step, array('traffic_type'=>'nocode','fixed_code'=>'   ')),
    'Ma toan khoang trang -> chan');

// 4. Đổi sang nocode kèm mã -> cho qua
$r = $edit($camp_1step, array('traffic_type'=>'nocode','fixed_code'=>'PROMO2024'));
assert_equals('PROMO2024', $r['fixed_code'] ?? null, 'Doi sang nocode co ma -> cho qua');

// 5. Xoá trắng mã của camp nocode đang chạy -> chặn, không để camp thành vô dụng
assert_equals('CHAN', $edit($camp_nocode, array('traffic_type'=>'nocode','fixed_code'=>'')),
    'Xoa trang ma cua camp nocode -> chan');

// 6. Camp không phải nocode thì không bị ràng buộc mã
$r = $edit($camp_1step, array('traffic_type'=>'2step'));
assert_equals('2step', $r['traffic_type'] ?? null, 'Camp 2 buoc -> khong doi hoi ma co dinh');
$r = $edit($camp_nocode, array('traffic_type'=>'1step'));
assert_equals('1step', $r['traffic_type'] ?? null, 'Doi nocode -> 1 buoc: khong doi hoi ma nua');

echo "  ✓ nocode edit\n";
