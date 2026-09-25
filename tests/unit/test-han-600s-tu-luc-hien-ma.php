<?php
/* "QUÁ 600 GIÂY KỂ TỪ LÚC HIỆN MÃ → CẮT THƯỞNG USER" — chủ site chốt 25/09/2026.
   Khách hàng giữ nguyên: vẫn +1 view và vẫn bị trừ tiền (đã chốt sớm ngay lúc hiện mã).

   Chốt cũ đếm từ created_at, mà created_at CÓ THỂ BỊ ĐẨY TỚI (start_timer bước 2 ghi
   created_at = now - credit; bridge_rescue chép created_at của lượt khác trong 30 phút) —
   mỗi lần như vậy là đồng hồ chạy lại từ đầu. Mốc code_shown_at thì không ai dời được.

   Test CHẠY THẬT đoạn quyết định: cắt nguyên văn từ shortlink-verification.php rồi eval. */

$__h6_ma = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/shortlink-verification.php' );

/* ---- 1. Hai chốt, đúng thứ tự, và đứng trước mọi thao tác tiền ---- */
$__h6_c1 = strpos( $__h6_ma, "if ( \$elapsed > \$visit_expiry ) return new WP_Error( 'expired', 'Phiên đã hết hạn' );" );
$__h6_c2 = strpos( $__h6_ma, "if ( \$tuoi_ma > \$visit_expiry ) return new WP_Error( 'expired', 'Phiên đã hết hạn' );" );
assert_true( $__h6_c1 !== false && $__h6_c2 !== false && $__h6_c1 < $__h6_c2, 'Phai co ca hai chot han' );
assert_true( $__h6_c2 < strpos( $__h6_ma, 'sitetop_add_user_balance( $visit->user_id' ),
    'SONG CON: chot han theo code_shown_at phai dung TRUOC cho cong thuong user' );
assert_true( $__h6_c2 < strpos( $__h6_ma, "\$wpdb->insert( \"{\$p}customer_transactions\"" ),
    'Chot nay cung dung truoc cho ghi giao dich tien khach' );
assert_true( strpos( $__h6_ma, "\$tuoi_ma = \$now - strtotime( \$visit->code_shown_at );" ) !== false,
    'Phai do tuoi ma tu code_shown_at, khong phai tu created_at' );

/* ---- 2. Chạy thật đoạn quyết định ---- */
assert_true( preg_match(
    '#(\$created_at = strtotime\( \$visit->created_at \);.*?if \( \$tuoi_ma > \$visit_expiry \) return [^\n]*\n\s*\})#s',
    $__h6_ma, $__h6_m ) === 1, 'Lay duoc doan quyet dinh han tu ma nguon' );

if ( ! class_exists( 'WP_Error' ) ) { class WP_Error { public $ma; function __construct( $m = '', $t = '' ) { $this->ma = $m; } } }
if ( ! function_exists( 'sitetop_get_visit_expiry_seconds' ) ) { function sitetop_get_visit_expiry_seconds() { return 600; } }

$__h6_body = $__h6_m[1];
$tu_choi = function ( $tuoi_phien_giay, $tuoi_ma_giay ) use ( $__h6_body ) {
    $moc = strtotime( sitetop_current_time() );
    $visit = (object) array(
        'created_at'    => date( 'Y-m-d H:i:s', $moc - $tuoi_phien_giay ),
        'code_shown_at' => $tuoi_ma_giay === null ? null : date( 'Y-m-d H:i:s', $moc - $tuoi_ma_giay ),
    );
    /* eval() TRẢ VỀ giá trị của lệnh return nằm trong đoạn mã — phải hứng lấy, bỏ đi là
       mọi ca "từ chối" đều lọt thành "cho qua" và test xanh giả. */
    $ket = eval( $__h6_body );
    return $ket instanceof WP_Error ? 'tu_choi' : 'cho_qua';
};

// Ca thật đã đo: làm 108 giây, gõ mã 13 giây sau khi hiện mã → phải được trả.
assert_equals( 'cho_qua', $tu_choi( 108, 13 ), 'Luot lam that trong han PHAI duoc tra thuong' );
assert_equals( 'cho_qua', $tu_choi( 590, 476 ), 'Khoang cach lau nhat do duoc (476s) van phai duoc tra' );
assert_equals( 'cho_qua', $tu_choi( 599, 599 ), 'Dung 599 giay van con trong han' );

// Quá 600 giây kể từ lúc hiện mã → cắt thưởng, dù đồng hồ phiên vừa bị đặt lại.
assert_equals( 'tu_choi', $tu_choi( 601, 601 ), 'Qua 600 giay -> cat thuong' );
assert_equals( 'tu_choi', $tu_choi( 10, 900 ),
    'DUONG LOT CU: created_at vua bi day toi (phien moi 10 giay) nhung ma da hien 900 giay -> van phai cat' );
assert_equals( 'tu_choi', $tu_choi( 10, 3600 ), 'Ma hien tu 1 tieng truoc -> cat thuong' );

// Phiên quá hạn theo mốc cũ vẫn bị chặn như trước.
assert_equals( 'tu_choi', $tu_choi( 900, 60 ), 'Chot cu theo created_at van con hieu luc' );
// Chưa hiện mã thì không xét mốc này (tránh chặn oan luồng bước 2 vừa xoá code_shown_at).
assert_equals( 'cho_qua', $tu_choi( 100, null ), 'Chua hien ma thi khong xet moc nay' );

/* ---- 3. Phía khách hàng KHÔNG được đụng ---- */
assert_true( strpos( $__h6_ma, '$should_pay_reward = ! $customer_only;' ) !== false,
    'Chot som van la: tru tien khach, khong tra user' );
assert_true( strpos( (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/shortlink-functions.php' ),
    'sitetop_verify_and_pay( $session_id, $code, true );' ) !== false,
    'Van chot som ngay luc hien ma — khach van bi tru tien va van duoc tinh view' );
