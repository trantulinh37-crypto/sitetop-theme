<?php
/* "HẾT HẠN" NGHĨA LÀ USER KHÔNG ĐƯỢC TRẢ — chủ site chốt 25/09/2026.

   Luật: trạng thái hiện "Hết hạn" thì tuyệt đối không có tiền trả cho user. Phía khách hàng
   giữ nguyên: vẫn tính +1 view vào lịch sử hoàn thành và vẫn trừ tiền theo rate của camp.

   Hai đầu phải cùng giữ luật này:
   (1) LÚC TÍNH TIỀN — hết giờ thì thoát TRƯỚC mọi thao tác cộng/trừ tiền;
   (2) LÚC HIỂN THỊ — một lượt đã trả tiền không bao giờ được dán nhãn "Hết hạn".

   Test chạy THẬT đoạn quyết định nhãn: cắt nguyên văn từ tab-visits.php rồi eval với từng
   hàng dữ liệu giả. Đổi một điều kiện là nhãn đổi theo và test đỏ ngay. */

$__hh_goc = dirname( __DIR__, 2 );
$__hh_ver = (string) file_get_contents( $__hh_goc . '/includes/shortlink-verification.php' );
$__hh_tab = (string) file_get_contents( $__hh_goc . '/includes/admin/tabs/tab-visits.php' );
$__hh_fn  = (string) file_get_contents( $__hh_goc . '/includes/shortlink-functions.php' );

/* ---- (1) Lúc tính tiền: hết giờ là thoát TRƯỚC khi đụng tới tiền ---- */
$__hh_chot = strpos( $__hh_ver, "if ( \$elapsed > \$visit_expiry ) return new WP_Error( 'expired', 'Phiên đã hết hạn' );" );
assert_true( $__hh_chot !== false, 'Phai con chot het han trong sitetop_verify_and_pay' );

$__hh_thuong = strpos( $__hh_ver, 'sitetop_add_user_balance( $visit->user_id' );
$__hh_tru    = strpos( $__hh_ver, "\$wpdb->insert( \"{\$p}customer_transactions\"" );
assert_true( $__hh_thuong !== false && $__hh_chot < $__hh_thuong,
    'SONG CON: chot het han phai dung TRUOC cho cong thuong cho user' );
assert_true( $__hh_tru !== false && $__hh_chot < $__hh_tru,
    'Chot het han cung phai dung truoc cho ghi giao dich tien khach' );

/* Phía khách hàng GIỮ NGUYÊN: vẫn chốt sớm lúc đưa mã (trừ tiền + tính view), không trả user. */
assert_true( strpos( $__hh_fn, 'sitetop_verify_and_pay( $session_id, $code, true );' ) !== false,
    'Van phai chot som luc dua ma — khach van bi tru tien va van duoc tinh view' );
assert_true( strpos( $__hh_ver, '$should_pay_reward = ! $customer_only;' ) !== false,
    'Chot som KHONG duoc tra thuong cho user' );
assert_true( strpos( $__hh_ver, "'step'            => \$customer_only ? 'code_shown'" ) !== false,
    'Chot som giu nguyen buoc code_shown — phien con mo de user go ma' );

/* ---- (2) Lúc hiển thị: chạy thật đoạn quyết định nhãn ---- */
assert_true( preg_match(
    '#(\$step = \$row->step \?\? \'started\';.*?else\{ \$st_label=\'Đang làm\';[^\n]*\})#s',
    $__hh_tab, $__hh_m ) === 1, 'Lay duoc doan quyet dinh nhan tu tab-visits.php' );
$__hh_ma = $__hh_m[1];

$nhan = function ( array $hang, $tuoi_giay ) use ( $__hh_ma ) {
    $now_vn       = '2026-09-25 20:00:00';
    $visit_expiry = 600;
    $row = (object) array_merge( array(
        'step' => 'started', 'verified_at' => null, 'reward_paid' => 0,
        'customer_paid' => 0, 'created_at' => date( 'Y-m-d H:i:s', strtotime( $now_vn ) - $tuoi_giay ),
    ), $hang );
    $st_label = $st_color = $st_bg = '';
    eval( $__hh_ma );
    return $st_label;
};

// Ca chính: lượt làm thật, đã trả tiền, step bị ghi đè, để lâu quá 600 giây.
assert_equals( 'Hoàn thành', $nhan( array( 'step' => 'target_visited', 'verified_at' => '2026-09-25 19:23:55',
    'reward_paid' => 1, 'customer_paid' => 1 ), 9000 ),
    'Luot DA TRA TIEN khong bao gio duoc hien "Het han"' );
assert_equals( 'Hoàn thành', $nhan( array( 'step' => 'expired', 'verified_at' => '2026-09-25 18:00:00',
    'reward_paid' => 1, 'customer_paid' => 1 ), 9000 ),
    'Bi nut Doi nhiem vu dong lai cung khong duoc hien "Het han"' );

// Chốt sớm: khách đã trả + đã tính view, user CHƯA được trả → vẫn phải là "Hết hạn".
assert_equals( 'Hết hạn', $nhan( array( 'step' => 'code_shown', 'customer_paid' => 1 ), 9000 ),
    'LUAT CHINH: khach da tra nhung user chua duoc tra thi van la "Het han"' );
assert_equals( 'Hết hạn', $nhan( array( 'step' => 'code_shown' ), 9000 ),
    'Co ma, khong nhap, qua gio -> Het han' );

// Các nhãn cũ không được đổi.
assert_equals( 'Hoàn thành', $nhan( array( 'step' => 'verified', 'verified_at' => '2026-09-25 19:00:00',
    'reward_paid' => 1, 'customer_paid' => 1 ), 9000 ), 'Luot verified van la Hoan thanh' );
assert_equals( 'Bị chặn', $nhan( array( 'step' => 'rejected', 'verified_at' => '2026-09-25 19:00:00' ), 9000 ),
    'Luot nguon gia van la Bi chan, khong bi doi thanh Hoan thanh' );
assert_equals( 'Đang làm', $nhan( array( 'step' => 'target_visited' ), 60 ), 'Phien moi van la Dang lam' );
assert_equals( 'Hết hạn', $nhan( array( 'step' => 'target_visited' ), 9000 ), 'Bo giua chung, qua gio -> Het han' );

/* Bất biến: KHÔNG tồn tại hàng nào vừa "Hết hạn" vừa "Đã trả" (cột Lý do in "Đã trả" theo
   reward_paid). Quét mọi tổ hợp thay vì tin vào vài ca lẻ. */
foreach ( array( 'started', 'google_clicked', 'target_visited', 'code_shown', 'expired', 'verified', 'rejected' ) as $b ) {
    foreach ( array( null, '2026-09-25 19:00:00' ) as $va ) {
        foreach ( array( 0, 1 ) as $tra ) {
            foreach ( array( 60, 9000 ) as $tuoi ) {
                $l = $nhan( array( 'step' => $b, 'verified_at' => $va, 'reward_paid' => $tra, 'customer_paid' => 1 ), $tuoi );
                assert_true( ! ( $l === 'Hết hạn' && $tra === 1 ),
                    "BAT BIEN VO: buoc=$b verified_at=" . ( $va ? 'co' : 'khong' ) . " reward_paid=$tra tuoi={$tuoi}s -> $l" );
            }
        }
    }
}

/* ---- (3) Bộ lọc và ô đếm phải khớp đúng nhãn ---- */
assert_true( strpos( $__hh_tab, "\$da_chot_sql = \"(v.verified_at IS NOT NULL OR v.reward_paid = 1) AND v.step <> 'rejected'\";" ) !== false,
    'Bo loc phai dung cung dinh nghia "da chot" voi phan hien thi' );
assert_true( strpos( $__hh_tab, "AND v.step != 'verified' AND NOT ({\$da_chot_sql}) AND v.step <> 'rejected' AND v.created_at <= %s" ) !== false,
    'Bo loc "Het han" khong duoc bat luot da chot' );
assert_true( strpos( $__hh_tab, "SUM(CASE WHEN step != 'verified' AND step <> 'rejected' AND verified_at IS NULL AND reward_paid = 0 AND created_at <= %s THEN 1 ELSE 0 END) as expired" ) !== false,
    'O dem "Het han" phai loai luot da chot' );
// Phía khách hàng giữ nguyên: ô đếm view vẫn theo công thức cũ.
assert_true( strpos( $__hh_tab, "SUM(CASE WHEN step='verified' OR customer_paid=1 THEN 1 ELSE 0 END) as completed" ) !== false,
    'O dem view cua khach PHAI giu nguyen cong thuc cu' );
