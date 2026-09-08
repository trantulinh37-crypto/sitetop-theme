<?php
/* Hạn mức lấy nhiệm vụ 5 lượt / 20 giờ CUỘN + thang chặn tăng dần.
   Chạy CHÍNH hàm thật (giả lập $wpdb) chứ không chép lại logic — bản chép dễ lệch dần
   với mã thật mà test vẫn xanh. */

$__goc = dirname( __DIR__, 2 );
$__ma  = file_get_contents( $__goc . '/includes/shortlink-functions.php' );
$__ddos = file_get_contents( $__goc . '/includes/anti-ddos.php' );

/* ---- Trích thân hàm thật, BỎ chú thích (tên hàm trong chú thích không tính là đấu dây) ---- */
$__than = function ( $ma, $ten ) {
    $vt = strpos( $ma, 'function ' . $ten . '(' );
    if ( $vt === false ) return '';
    $tk = token_get_all( '<?php ' . substr( $ma, $vt ) );
    $out = ''; $d = 0; $open = false;
    foreach ( $tk as $t ) {
        $bo = is_array( $t ) && in_array( $t[0], array( T_OPEN_TAG, T_COMMENT, T_DOC_COMMENT ), true );
        if ( ! $bo ) $out .= is_array( $t ) ? $t[1] : $t;
        $mo = ( $t === '{' ) || ( is_array( $t ) && in_array( $t[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) );
        if ( $mo ) { $d++; $open = true; }
        elseif ( $t === '}' ) { $d--; if ( $open && $d === 0 ) break; }
    }
    return $out;
};

$GLOBALS['__tr'] = array();
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $k ) { return $GLOBALS['__tr'][$k] ?? false; }
}
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['__tr'][$k] = $v; $GLOBALS['__ttl'][$k] = $ttl; return true; }
}
/* ---- Giả lập tối thiểu để chạy hàm thật ---- */
if ( ! defined( 'HOUR_IN_SECONDS' ) )   define( 'HOUR_IN_SECONDS', 3600 );
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) define( 'MINUTE_IN_SECONDS', 60 );
if ( ! defined( 'DAY_IN_SECONDS' ) )    define( 'DAY_IN_SECONDS', 86400 );
if ( ! function_exists( 'sitetop_get_option' ) ) {
    function sitetop_get_option( $k, $d = null ) { return array_key_exists( $k, $GLOBALS['__opt'] ) ? $GLOBALS['__opt'][$k] : $d; }
}
if ( ! function_exists( 'sitetop_current_time' ) ) {
    function sitetop_current_time() { return $GLOBALS['__now']; }
}
class TQ_Wpdb {
    public $prefix = 'wpgd_';
    public function prepare( $q, ...$a ) { return $q; }
    public function get_var( $q ) {
        // COUNT(*) -> số lượt trong cửa sổ; còn lại -> created_at của lượt cũ nhất
        return ( strpos( $q, 'COUNT(*)' ) !== false ) ? $GLOBALS['__dem'] : $GLOBALS['__cu'];
    }
}
$GLOBALS['wpdb'] = new TQ_Wpdb();

if ( ! function_exists( 'sitetop_han_muc_nhiem_vu' ) ) {
    $__fn = $__than( $__ma, 'sitetop_han_muc_nhiem_vu' );
    if ( $__fn === '' ) { $GLOBALS['test_results']['failed']++;
        $GLOBALS['test_results']['errors'][] = 'Khong trich duoc sitetop_han_muc_nhiem_vu'; return; }
    eval( $__fn );
}

$GLOBALS['__now'] = '2026-09-08 12:00:00';
$chay = function ( $dem, $opt = array(), $tr = array() ) {
    $GLOBALS['__opt'] = $opt;
    $GLOBALS['__dem'] = $dem;
    $GLOBALS['__tr']  = $tr;    // trạng thái khoá/mốc trước khi chạy
    $GLOBALS['__ttl'] = array();
    return sitetop_han_muc_nhiem_vu( '1.2.3.4' );
};
$KHOA = md5( '1.2.3.4' );

// ---- Trần mặc định 5 ----
$r = $chay( 0 );  assert_true( $r['allowed'], '0 luot -> cho lay nhiem vu' );
assert_equals( 10, $r['limit'], 'Tran mac dinh la 10' );
assert_equals( 20, $r['gio'],  'Cua so mac dinh la 20 gio' );
$r = $chay( 9 );  assert_true( $r['allowed'],  '9 luot -> van cho' );
$r = $chay( 10 );
assert_true( ! $r['allowed'], '10 luot -> CHAN (dung tran la het)' );
$r = $chay( 15 );
assert_true( ! $r['allowed'], 'Vuot tran -> CHAN' );

/* ---- Vượt trần -> KHOÁ 10 GIỜ, không phải chờ cửa sổ 20 giờ trôi ---- */
assert_equals( 36000, $chay( 10 )['cho_giay'], 'Vuot tran -> khoa dung 10 gio' );
assert_equals( 7200,  $chay( 10, array( 'nhiem_vu_chan_gio' => 2 ) )['cho_giay'], 'Doi duoc so gio khoa' );
assert_equals( 36000, $chay( 10, array( 'nhiem_vu_chan_gio' => 99 ) )['cho_giay'], 'So gio vo ly -> ve 10' );
assert_equals( 0,     $chay( 9 )['cho_giay'], 'Chua vuot -> khong khoa' );

/* Đang trong thời gian khoá thì chặn ngay, KHÔNG cần đếm lại. */
$__nowts = strtotime( sitetop_current_time() );
$r = $chay( 0, array(), array( 'st_hm_chan_' . $KHOA => $__nowts + 1800 ) );
assert_true( ! $r['allowed'], 'Dang trong gio khoa -> chan du dem = 0' );
assert_equals( 1800, $r['cho_giay'], 'Bao dung so giay khoa con lai' );
$r = $chay( 0, array(), array( 'st_hm_chan_' . $KHOA => $__nowts - 60 ) );
assert_true( $r['allowed'], 'Khoa da het han -> cho vao lai' );

/* BẪY KHOÁ VĨNH VIỄN: hết 10 giờ mà vẫn đếm lượt cũ thì bị khoá lại ngay, lặp mãi.
   Mốc hết khoá phải được ghi lại VÀ phải sống lâu hơn chính cái khoá. */
$r = $chay( 10 );
assert_true( ! $r['allowed'], 'Vuot tran -> chan' );
assert_true( isset( $GLOBALS['__tr'][ 'st_hm_moc_' . $KHOA ] ), 'Phai ghi MOC dem lai khi khoa' );
assert_equals( $GLOBALS['__tr'][ 'st_hm_chan_' . $KHOA ], $GLOBALS['__tr'][ 'st_hm_moc_' . $KHOA ],
    'Moc dem lai phai dung bang thoi diem het khoa' );
assert_true( $GLOBALS['__ttl'][ 'st_hm_moc_' . $KHOA ] > $GLOBALS['__ttl'][ 'st_hm_chan_' . $KHOA ],
    'TTL cua MOC phai dai hon TTL cua KHOA, neu khong lai dem ca luot cu -> khoa vinh vien' );

// ---- Mức quan sát: đếm và báo nhưng KHÔNG chặn ----
if ( ! function_exists( 'sitetop_canh_bao_qua_han_muc' ) ) {
    function sitetop_canh_bao_qua_han_muc( $ip, $u, $t, $g ) { $GLOBALS['__bao'][] = array( $ip, $u, $t, $g ); }
}
$GLOBALS['__bao'] = array();
$r = $chay( 14, array( 'nhiem_vu_che_do' => 1 ) );
assert_true( $r['allowed'],  'Muc 1 (quan sat) -> KHONG chan du da qua tran' );
assert_true( $r['qua_han'],  'Muc 1 van phai ghi nhan la da qua tran' );
assert_true( count( $GLOBALS['__bao'] ) === 1, 'Muc 1 phai ban canh bao de dem duoc' );
assert_true( ! isset( $GLOBALS['__tr'][ 'st_hm_chan_' . $KHOA ] ), 'Muc 1 TUYET DOI khong duoc dat khoa' );
$GLOBALS['__bao'] = array();
$r = $chay( 14, array( 'nhiem_vu_che_do' => 2 ) );
assert_true( ! $r['allowed'], 'Muc 2 -> chan that' );
assert_true( count( $GLOBALS['__bao'] ) === 0, 'Muc 2 khong dung kenh canh bao quan sat' );
$GLOBALS['__bao'] = array();
assert_true( $chay( 2, array( 'nhiem_vu_che_do' => 1 ) )['allowed'], 'Chua qua tran -> khong bao' );
assert_true( count( $GLOBALS['__bao'] ) === 0, 'Chua qua tran thi khong duoc ban canh bao' );

// ---- Tắt và chỉnh trần ----
assert_true( $chay( 999, array( 'nhiem_vu_ip_20h' => 0 ) )['allowed'],
    'Option = 0 -> TAT han muc, khong chan ai' );
assert_true( ! $chay( 2, array( 'nhiem_vu_ip_20h' => 2 ) )['allowed'], 'Tran 2 -> 2 luot la het' );
assert_true( $chay( 2, array( 'nhiem_vu_ip_20h' => 3 ) )['allowed'],  'Tran 3 -> 2 luot van con' );
// Cửa sổ bậy phải rơi về 20, không được nhận số vô lý
assert_equals( 20, $chay( 0, array( 'nhiem_vu_cua_so_gio' => 999 ) )['gio'], 'Cua so vo ly -> ve 20' );
assert_equals( 20, $chay( 0, array( 'nhiem_vu_cua_so_gio' => 0 ) )['gio'],   'Cua so 0 -> ve 20' );
assert_equals( 6,  $chay( 0, array( 'nhiem_vu_cua_so_gio' => 6 ) )['gio'],   'Cua so hop le -> giu nguyen' );

/* ---- Thang chặn tăng dần 10 phút -> 1 giờ -> 24 giờ ---- */
if ( ! function_exists( 'sitetop_ddos_temp_block_ident' ) ) {
    function sitetop_ddos_temp_block_ident( $i, $r = 'auto', $c = 0, $s = 3600 ) { $GLOBALS['__block'][] = array( $i, $r, $c, $s ); }
}
if ( ! function_exists( 'sitetop_chan_tang_dan' ) ) {
    $__fn2 = $__than( $__ddos, 'sitetop_chan_tang_dan' );
    if ( $__fn2 === '' ) { $GLOBALS['test_results']['failed']++;
        $GLOBALS['test_results']['errors'][] = 'Khong trich duoc sitetop_chan_tang_dan'; return; }
    eval( $__fn2 );
}
$GLOBALS['__block'] = array();
assert_equals( 600,   sitetop_chan_tang_dan( 'u9', 'api_spam' )['giay'], 'Lan 1 -> 10 phut' );
assert_equals( 3600,  sitetop_chan_tang_dan( 'u9', 'api_spam' )['giay'], 'Lan 2 -> 1 gio' );
assert_equals( 86400, sitetop_chan_tang_dan( 'u9', 'api_spam' )['giay'], 'Lan 3 -> 24 gio' );
assert_equals( 86400, sitetop_chan_tang_dan( 'u9', 'api_spam' )['giay'], 'Lan 4 -> van 24 gio, khong vuot bac' );
// Định danh khác đếm bậc riêng, không lây
assert_equals( 600, sitetop_chan_tang_dan( 'u10', 'api_spam' )['giay'], 'Dinh danh khac -> bac rieng' );
// Lý do khác cũng đếm riêng
assert_equals( 600, sitetop_chan_tang_dan( 'u9', 'ly_do_khac' )['giay'], 'Ly do khac -> bac rieng' );
assert_true( count( $GLOBALS['__block'] ) === 6, 'Moi lan leo thang deu goi ham chan tam san co' );
// Bậc phải sống LÂU HƠN cái block, nếu không hết block là mất bậc
assert_true( ( $GLOBALS['__ttl'][ 'st_bac_chan_' . md5( 'u9|api_spam' ) ] ?? 0 ) > 86400,
    'TTL nho bac phai dai hon block 24h' );

/* ---- Canh ĐẤU DÂY: thứ tự cắm mới là chỗ chết người ---- */
$than_tao = $__than( $__ma, 'sitetop_create_visit_session' );
assert_true( strpos( $than_tao, 'sitetop_han_muc_nhiem_vu' ) !== false,
    'create_visit_session PHAI xet han muc' );
$vt_dung   = strpos( $than_tao, 'if($existing)' ) !== false ? strpos( $than_tao, 'if($existing)' ) : strpos( $than_tao, 'if ( $existing )' );
$vt_hanmuc = strpos( $than_tao, 'sitetop_han_muc_nhiem_vu' );
assert_true( $vt_dung !== false && $vt_hanmuc > $vt_dung,
    'Han muc phai xet SAU nhanh tai su dung (F5/quay lai khong duoc dot luot)' );

$than_xl = $__than( $__ma, 'sitetop_handle_shortlink_visit' );
$vt_err  = strpos( $than_xl, 'is_wp_error($session_id)' ) !== false
         ? strpos( $than_xl, 'is_wp_error($session_id)' ) : strpos( $than_xl, 'is_wp_error( $session_id )' );
$vt_redir = strpos( $than_xl, 'original_url' );
assert_true( $vt_err !== false, 'Cho goi PHAI bat WP_Error het han muc' );
assert_true( $vt_err !== false && $vt_redir !== false && $vt_err < $vt_redir,
    'Bat WP_Error phai TRUOC nhanh redirect original_url (khong duoc phat link dich cho nguoi bi chan)' );

/* ---- API: chặn tăng dần + hạn mức 20h ---- */
$__api = file_get_contents( $__goc . '/includes/rest-api.php' );
$than_api = $__than( $__api, 'sitetop_handle_api_shorten' );
/* Tìm LỆNH GỌI THẬT, không tìm tên trần: tên vẫn nằm trong function_exists('...') nên
   canh theo tên sẽ xanh cả khi lệnh gọi đã bị gỡ (đã dính đúng bẫy này khi thử phá). */
assert_true( strpos( $than_api, 'sitetop_dang_bi_chan( $dinh_danh )' ) !== false,
    'API PHAI GOI sitetop_dang_bi_chan (ddos_check chi soi theo IP)' );
assert_true( strpos( $than_api, 'sitetop_chan_tang_dan( $dinh_danh' ) !== false,
    'API PHAI GOI sitetop_chan_tang_dan khi spam' );
assert_true( strpos( $than_api, "'api_spam'" ) !== false, 'API PHAI dung ro chong spam rieng' );
// Rổ api_spam phải tồn tại trong bảng hạn mức, nếu không nó rơi về 'default' 60/60 lặng lẽ
$__ip = file_get_contents( $__goc . '/includes/shortlink-ip.php' );
assert_true( strpos( $__ip, "'api_spam'" ) !== false,
    "Ro 'api_spam' PHAI co trong bang han muc, khong duoc roi ve default" );

/* ---- MỌI cửa tạo phiên mới đều phải xét hạn mức ----
   Bỏ sót một cửa là hạn mức vô nghĩa: đo production 08/09 cho thấy đổi nhiệm vụ tự insert
   dòng lượt mới nên lách sạch, một IP đạt 10 lượt trên đúng 2 shortlink. */
$__ajax = file_get_contents( dirname(__DIR__, 2) . '/includes/shortlink-ajax.php' );
$than_doi = $__than( $__ajax, 'sitetop_ajax_change_keyword' );
assert_true( $than_doi !== '', 'Phai tim thay sitetop_ajax_change_keyword' );
assert_true( strpos( $than_doi, 'sitetop_han_muc_nhiem_vu( $ip )' ) !== false,
    'change_keyword PHAI GOI han muc (no tu insert dong luot moi, khong qua create_visit_session)' );
// Phải xét TRƯỚC khi chọn chiến dịch, kẻo đốt oan camp của khách
$vt_hm   = strpos( $than_doi, 'sitetop_han_muc_nhiem_vu( $ip )' );
$vt_camp = strpos( $than_doi, 'sitetop_get_random_active_campaign' );
assert_true( $vt_camp !== false && $vt_hm < $vt_camp,
    'Xet han muc TRUOC khi chon chien dich (khong dot oan camp cua khach)' );
// Và phải nằm trước lệnh insert
$vt_ins = strpos( $than_doi, '$wpdb->insert' );
assert_true( $vt_ins !== false && $vt_hm < $vt_ins, 'Xet han muc TRUOC khi insert dong luot moi' );

/* ---- /st (liên kết nhanh cho NGƯỜI XEM) phải nằm NGOÀI rổ chống spam theo tài khoản ----
   Sự cố 08/09/2026 18:46: publisher dán /st công khai, mỗi visitor bấm vào đều tính vào rổ
   của CHỦ TOKEN -> tài khoản bị khoá 24 giờ trong khi họ không gửi request nào. */
/* Neo phải bám ĐÚNG khối: chuỗi 'if ( ! $is_quicklink ) {' còn xuất hiện ở chỗ đặt header
   JSON gần đầu hàm, nên strpos trần bắt nhầm chỗ đó và phép canh thành vô dụng — thử phá
   lần đầu đã lọt đúng vì lý do này. Neo theo dòng $dinh_danh rồi đòi ngay sau nó là nhánh
   loại trừ quicklink. */
$vt_dd = strpos( $than_api, '$dinh_danh = \'u\' . $uid;' );
assert_true( $vt_dd !== false, 'Phai tim thay dong dat dinh danh tai khoan' );
$sau_dd = $vt_dd === false ? '' : substr( $than_api, $vt_dd, 200 );
assert_true( strpos( $sau_dd, 'if ( ! $is_quicklink ) {' ) !== false,
    'Ngay sau dinh danh PHAI la nhanh loai tru quicklink (khong duoc chan /st)' );
$vt_spam = strpos( $than_api, "sitetop_rate_limit_check( 'api_spam'" );
$vt_chan = strpos( $than_api, 'sitetop_dang_bi_chan( $dinh_danh )' );
assert_true( $vt_spam !== false && $vt_spam > $vt_dd, 'Ro api_spam nam sau nhanh loai tru' );
assert_true( $vt_chan !== false && $vt_chan > $vt_dd, 'Kiem block nam sau nhanh loai tru' );

/* ---- Gỡ khoá phải xoá luôn TIỀN ÁN bậc thang ----
   Không xoá thì admin bấm gỡ xong, user vi phạm nhẹ một cái là nhảy thẳng mức 24 giờ —
   nhìn như gỡ hụt. Gặp đúng ca này 08/09/2026 khi gỡ oan cho một tài khoản. */
$than_go = $__than( $__ddos, 'sitetop_ddos_unblock_ident' );
assert_true( $than_go !== '', 'Phai tim thay sitetop_ddos_unblock_ident' );
assert_true( strpos( $than_go, "delete_transient( 'st_bac_chan_'" ) !== false,
    'Go khoa PHAI xoa tien an bac thang' );
assert_true( strpos( $than_go, 'sitetop_chan_cac_ly_do()' ) !== false,
    'Phai duyet theo danh sach ly do chung, khong cam cung tung ly do' );
