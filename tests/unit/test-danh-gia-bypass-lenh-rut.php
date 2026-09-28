<?php
/* CỘT "ĐÁNH GIÁ" Ở BẢNG LỆNH RÚT — 28/09/2026.
   Chủ site cần nhìn ngay: lệnh rút này của user đã dùng bypass nguồn giả / tua giờ bao nhiêu.

   Hai thứ dễ hỏng nhất, test canh đúng chúng:
   1. Thước đo phải DÙNG CHUNG giữa bảng và popup soi gian lận — mỗi nơi một bộ dấu là hai
      con số đá nhau, admin không biết tin cái nào.
   2. Trang không được tính tại chỗ: đo trên production, đếm cho 20 lệnh mất ~3 giây vì phải
      quét lượt của từng user trong kỳ và đọc hai cột TEXT. */

$__bp_goc = dirname( __DIR__, 2 );
$__bp_adm = (string) file_get_contents( $__bp_goc . '/includes/admin-dashboard.php' );
$__bp_tab = (string) file_get_contents( $__bp_goc . '/includes/admin/tabs/tab-withdrawals.php' );
$__bp_cron = (string) file_get_contents( $__bp_goc . '/includes/cron-cleanup.php' );

/* ---- 1. Bộ dấu: đủ cả hai nguồn, và dùng LOCATE ---- */
assert_true( preg_match( '#function sitetop_wd_dieu_kien_bypass\(\) \{(.*?)\n\}#s', $__bp_adm, $__bp_m ) === 1,
    'Phai co ham tra ve bo dieu kien dung chung' );
$__bp_dk = $__bp_m[1];
foreach ( array( 'nguon_gia', 'ref_lech', 'cong_cu' ) as $__bp_d ) {
    assert_true( substr_count( $__bp_dk, "'" . $__bp_d . "'" ) === 2,
        "Dau '$__bp_d' phai soi CA skip_reasons LAN dau_vet (2 lan)" );
}
assert_true( strpos( $__bp_dk, "'tua_gio'" ) !== false && strpos( $__bp_dk, "'timer_manipulation'" ) !== false
          && strpos( $__bp_dk, "'tuagio'" ) !== false,
    'Nhom tua gio phai gom ca dau moi lan dau cu' );
/* KHÔNG được tính 'tuchoi_gio' (máy chủ từ chối vì chưa đủ giờ): đo 28/09 thấy 37/68 user
   đang hoạt động đều có dấu đó — chuyện thường của người thật, gộp vào là dán nhãn oan cho
   quá nửa số người. */
/* Soi ĐIỀU KIỆN SQL chứ không soi cả thân hàm: tên dấu này còn nằm trong chú thích giải
   thích vì sao không dùng nó. */
assert_true( strpos( $__bp_dk, "LOCATE('tuchoi_gio'" ) === false,
    'Dau tuchoi_gio qua nhieu (54% user that co) — khong duoc tinh vao cot Danh gia' );
/* SỐNG CÒN: gạch dưới trong 'nguon_gia' là ký tự đại diện của LIKE — phải dùng LOCATE. */
assert_true( strpos( $__bp_dk, 'LIKE' ) === false && substr_count( $__bp_dk, 'LOCATE(' ) === 9,
    'Phai dung LOCATE, khong duoc dung LIKE (gach duoi la ky tu dai dien)' );
// 91% bằng chứng chỉ nằm ở dau_vet (lượt bị chặn ngay tại cổng) — bỏ cột này là mù.
assert_true( substr_count( $__bp_dk, 'v.dau_vet' ) === 4, 'Phai doc dau_vet du 4 dau' );

/* ---- 2. Bảng và popup dùng CHUNG một thước ---- */
/* Đếm CHỖ GỌI, không tính dòng khai báo hàm — đếm cả khai báo thì thêm một chỗ gọi nữa
   cũng không làm test đỏ. */
assert_true( substr_count( $__bp_adm, '= sitetop_wd_dieu_kien_bypass();' ) === 2,
    'Ca cho dem cho bang LAN cho soi gian lan deu phai goi cung mot ham dieu kien' );
assert_true( strpos( $__bp_adm, "\$dk_bp = sitetop_wd_dieu_kien_bypass();" ) !== false,
    'Ham soi gian lan phai dung lai bo dieu kien chung' );

/* ---- 3. Xếp mức: chạy thật ---- */
assert_true( preg_match( '#(function sitetop_wd_muc_bypass\(.*?\n\})#s', $__bp_adm, $__bp_m2 ) === 1,
    'Lay duoc ham xep muc' );
eval( $__bp_m2[1] );
$muc = function ( $ngia, $tua, $view ) { $r = sitetop_wd_muc_bypass( $ngia, $tua, $view ); return $r['muc']; };

assert_equals( 'sach', $muc( 0, 0, 500 ), 'Khong co dau nao -> sach' );
// Ca thật đo được trên production 28/09:
assert_equals( 'nang', $muc( 29, 0, 211 ),  'LongMods2009 #322: 29/211 = 13,7% -> NANG' );
assert_equals( 'nang', $muc( 32, 0, 3275 ), 'monpro #333: 32 luot (>=20) -> NANG du ty le thap' );
assert_equals( 'nang', $muc( 13, 6, 382 ),  'LongMods2009 #336: 19 luot / 382 = 5,0% -> NANG' );
assert_equals( 'vua',  $muc( 10, 0, 437 ),  'moneytask #303: 10/437 = 2,3% -> VUA' );
assert_equals( 'vua',  $muc( 5, 0, 5000 ),  'Du 5 luot -> VUA du ty le rat thap' );
assert_equals( 'nhe',  $muc( 1, 0, 1000 ),  '1 luot tren 1000 view -> NHE' );
assert_equals( 'nang', $muc( 1, 0, 0 ),     'Co dau ma 0 view tra tien -> coi nhu 100% -> NANG' );
// Tua giờ phải được cộng vào cùng thước với nguồn giả.
assert_equals( 'vua', $muc( 0, 6, 1000 ), 'Rieng tua gio 6 luot -> VUA' );

/* ---- 4. Trang KHÔNG được tính tại chỗ ---- */
assert_true( preg_match( '#<td class="col-bp"><span class="wd-bp" data-wid="<\?php echo intval\(\$row->id\); \?>">…</span></td>#', $__bp_tab ) === 1,
    'O danh gia phai de TRONG luc dung trang, chi mang data-wid' );
assert_true( strpos( $__bp_tab, 'sitetop_wd_danh_gia_bypass' ) === false,
    'SONG CON: tab KHONG duoc goi ham dem khi dung trang — do thay 20 lenh mat ~3 giay' );
assert_true( strpos( $__bp_tab, "if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', wdNapDanhGia);" ) !== false,
    'Chi nap sau khi trang da hien' );
assert_true( strpos( $__bp_tab, "fd.append('action', 'sitetop_admin_wd_bypass');" ) !== false, 'Phai goi dung cong' );
assert_true( strpos( $__bp_adm, "add_action( 'wp_ajax_sitetop_admin_wd_bypass'" ) !== false, 'Phai dang ky cong' );
assert_true( strpos( $__bp_adm, "if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );" ) !== false,
    'Cong phai chot quyen admin' );
// Hỏng/chậm thì bảng vẫn dùng được, ô chỉ hiện dấu "–".
assert_true( substr_count( $__bp_tab, 'wdDanhGiaHong' ) === 3, 'Phai co duong lui khi goi hong' );

/* ---- 5. Số cột và colspan phải khớp ---- */
/* Đếm bằng '<th' trần là dính luôn '<thead>' — phải đòi ký tự sau tên thẻ. */
$__bp_dau = strpos( $__bp_tab, '<thead>' );
$__bp_th  = preg_match_all( '#<th[ >]#', substr( $__bp_tab, $__bp_dau, strpos( $__bp_tab, '</thead>' ) - $__bp_dau ) );
assert_equals( 13, $__bp_th, 'Bang phai co 13 cot sau khi them cot Danh gia' );
assert_true( strpos( $__bp_tab, 'colspan="13"' ) !== false && strpos( $__bp_tab, 'colspan="12"' ) === false,
    'Dong "Khong co du lieu" phai doi colspan theo' );

/* ---- 6. Ô nhớ: cột + migration ---- */
foreach ( array( 'bp_ngia', 'bp_tua', 'bp_view', 'bp_luc' ) as $__bp_c ) {
    assert_true( strpos( $__bp_cron, "ADD COLUMN {$__bp_c} " ) !== false, 'Thieu cot o nho: ' . $__bp_c );
}
assert_true( strpos( $__bp_cron, "if ( ! in_array( \$ten, \$cot, true ) ) return;   // ALTER hỏng" ) !== false,
    'ALTER hong thi KHONG duoc dat co — lan sau con thu lai' );
assert_true( strpos( $__bp_adm, "if ( ! in_array( 'bp_luc', \$cot, true ) ) return array();" ) !== false,
    'Chua co cot thi tra ve rong, khong duoc vo' );
/* Kỳ của lệnh đã chốt cứng nên số không đổi -> ghi lại được. Nhưng phải ghi ĐÚNG lệnh. */
assert_true( strpos( $__bp_adm, "array( 'id' => \$wid )" ) !== false, 'Ghi o nho phai theo dung id lenh' );

/* ---- 7. Chấm điểm rủi ro: chạy thật cái thang điểm ---- */
assert_true( preg_match( '#(    if \(\$bp_ngia > 0 \|\| \$bp_tua > 0\) \{.*?\n    \})#s', $__bp_adm, $__bp_m3 ) === 1,
    'Lay duoc doan cham diem bypass' );
$diem = function ( $ngia, $tua, $paid ) use ( $__bp_m3 ) {
    $bp_ngia = $ngia; $bp_tua = $tua; $paid_views = $paid;
    $risk_score = 0; $risk_reasons = array();
    eval( $__bp_m3[1] );
    return array( $risk_score, $risk_reasons );
};
list( $d1, $r1 ) = $diem( 0, 0, 500 );
assert_equals( 0, $d1, 'Khong dinh bypass thi khong cong diem' );
assert_equals( 0, count( $r1 ), 'Va khong them dong ly do nao' );
list( $d2, ) = $diem( 29, 0, 211 );
assert_equals( 5, $d2, 'Muc NANG cong 5 diem (du de thanh RUI RO CAO mot minh no)' );
list( $d3, ) = $diem( 10, 0, 437 );
assert_equals( 3, $d3, 'Muc VUA cong 3 diem' );
list( $d4, $r4 ) = $diem( 1, 0, 1000 );
assert_equals( 1, $d4, 'Muc NHE cong 1 diem' );
assert_true( strpos( $r4[0], 'nguồn giả 1' ) !== false && strpos( $r4[0], '1000 view' ) !== false,
    'Dong ly do phai noi ro so luot va mau so' );
list( , $r5 ) = $diem( 3, 4, 200 );
assert_true( strpos( $r5[0], 'nguồn giả 3' ) !== false && strpos( $r5[0], 'tua giờ 4' ) !== false,
    'Dinh ca hai loai thi ly do phai ke ca hai' );
/* Mốc 5 điểm = RỦI RO CAO; mức NẶNG phải tự mình chạm tới đó. */
assert_true( strpos( $__bp_adm, "if     (\$risk_score >= 5) \$risk_level = 'high';" ) !== false,
    'Nguong RUI RO CAO van la 5 diem' );
