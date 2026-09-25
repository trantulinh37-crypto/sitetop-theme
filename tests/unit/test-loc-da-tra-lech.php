<?php
/* BỘ LỌC "ĐÃ TRẢ" + "ĐÃ TRẢ · LỆCH TRẠNG THÁI" — 25/09/2026.

   Chủ site thấy một dòng "Trạng thái: Hết hạn · Lý do: Đã trả" và hỏi xem có nhiều không.
   Hai cột đó đọc HAI cột CSDL khác nhau: Lý do in "Đã trả" theo reward_paid=1, Trạng thái
   đọc step. Lượt đã chốt trả tiền xong mà bị ghi đè step (widget ping lại sau khi chốt,
   hoặc user bấm Đổi nhiệm vụ) sẽ hiện lệch như vậy.

   Bộ lọc reward_paid=1 vốn ĐÃ CÓ nhưng dán nhãn tiếng Anh "Earned" nên chủ site không nhận
   ra. Nay: đổi nhãn cho khớp đúng chữ ở cột Lý do, và thêm một bộ lọc soi riêng nhóm lệch. */

$__lt_ma = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/admin/tabs/tab-visits.php' );

/* ---- 1. Nhãn phải khớp chữ in ra ở cột Lý do ---- */
assert_true( strpos( $__lt_ma, ">Đã trả</option>" ) !== false,
    'O chon phai ghi "Da tra" — dung chu voi cot Ly do, khong phai "Earned"' );
assert_true( strpos( $__lt_ma, '>Earned</option>' ) === false,
    'Khong con nhan tieng Anh "Earned" — chu site khong nhan ra no chinh la "Da tra"' );
assert_true( strpos( $__lt_ma, '<span style="color:#46b450;font-weight:600">Đã trả</span>' ) !== false,
    'Cot Ly do van in "Da tra" theo reward_paid — bo loc phai soi dung dieu kien nay' );

/* ---- 2. Điều kiện lọc ---- */
assert_true( strpos( $__lt_ma, "if(\$reason_filter === 'earned'){ \$where .= \" AND v.reward_paid = 1\"; }" ) !== false,
    'Bo loc "Da tra" giu nguyen dieu kien reward_paid = 1' );
assert_true( strpos( $__lt_ma, "elseif(\$reason_filter === 'da_tra_lech'){ \$where .= \" AND v.reward_paid = 1 AND v.step != 'verified'\"; }" ) !== false,
    'Bo loc lech phai la: da tra thuong NHUNG step khong phai verified' );
assert_true( strpos( $__lt_ma, "<option value=\"da_tra_lech\"" ) !== false, 'Thieu o chon cho bo loc lech' );
assert_true( strpos( $__lt_ma, "selected(\$reason_filter,'da_tra_lech')" ) !== false,
    'O chon phai tu giu lai lua chon sau khi loc' );

/* ---- 3. Hai bộ lọc không được trùng nhau ---- */
/* 'earned' đứng TRƯỚC trong chuỗi if/elseif nên nếu ai đó đặt cùng giá trị thì nhánh lệch
   sẽ không bao giờ chạy. Canh luôn thứ tự lẫn sự khác nhau của hai điều kiện. */
$__lt_a = strpos( $__lt_ma, "\$reason_filter === 'earned'" );
$__lt_b = strpos( $__lt_ma, "\$reason_filter === 'da_tra_lech'" );
assert_true( $__lt_a !== false && $__lt_b !== false && $__lt_a < $__lt_b,
    'Nhanh lech phai nam sau nhanh earned trong chuoi if/elseif' );
assert_true( substr_count( $__lt_ma, "=== 'da_tra_lech'" ) === 1, 'Chi duoc mot nhanh xu ly da_tra_lech' );

/* ---- 4. Không đụng các bộ lọc khác ---- */
foreach ( array( 'nguon_gia', 'ref_lech', 'cong_cu', 'timer_manip', 'adblock_mode2', 'self_click' ) as $__lt_cu ) {
    assert_true( strpos( $__lt_ma, "=== '" . $__lt_cu . "'" ) !== false, 'Bo loc cu phai con: ' . $__lt_cu );
}
