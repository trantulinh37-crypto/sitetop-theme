<?php
/**
 * TIỀN USER BẰNG USD — chủ site yêu cầu 06/10/2026
 * ------------------------------------------------------------------
 * Thưởng user, số dư, lịch sử, thống kê và lệnh rút chuyển sang USD. KHÁCH HÀNG giữ
 * nguyên VNĐ (price_per_view, customer_balance, customer_transactions, nạp tiền) — file
 * này KHÔNG đụng tới bất kỳ cột nào bên khách.
 *
 * CÔNG TẮC: option 'sitetop_che_do_usd'. Chưa bật = hệ thống chạy VNĐ y hệt trước đây.
 * Mọi chỗ in tiền user đi qua sitetop_format_tien_user(), mọi chỗ tính thưởng đi qua
 * sitetop_get_reward_amount() / sitetop_user_reward_cho_camp() — cả hai tự đổi đơn vị
 * theo công tắc. Nhờ vậy mã mới đẩy lên trước, chuyển dữ liệu sau, không có khoảng nào
 * trả sai đơn vị.
 *
 * Rate user: USD / 1.000 view, mỗi loại camp một mức (option usd_user_{nhom}_{loai}).
 * Tỷ giá USD → VNĐ: option usd_rate (mặc định 22.000) — chỉ dùng để admin thấy số VNĐ
 * quy đổi khi duyệt lệnh rút. Tỷ giá nạp USDT của khách (deposit_usdt_rate) là chuyện
 * khác, không dùng ở đây.
 *
 * Chuyển dữ liệu: sitetop_chuyen_sang_usd() — xem chú thích ở hàm đó.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Số chữ số thập phân lưu tiền user. 8 để cộng dồn hàng nghìn dòng vẫn lệch < 1đ. */
const SITETOP_USD_LE = 8;

/* ============================================================
   CÔNG TẮC + TỶ GIÁ
   ============================================================ */
function sitetop_che_do_usd() {
    return (int) get_option( 'sitetop_che_do_usd', 0 ) === 1;
}

function sitetop_usd_rate() {
    $r = (float) sitetop_get_option( 'usd_rate', 22000 );
    return $r > 0 ? $r : 22000.0;
}

/** USD → VNĐ theo tỷ giá admin cài (làm tròn tới đồng). Dùng cho màn admin duyệt rút. */
function sitetop_usd_sang_vnd( $usd ) {
    return (int) round( (float) $usd * sitetop_usd_rate() );
}

/** Ký hiệu đơn vị tiền user đang dùng — để in nhãn ô nhập, gợi ý. */
function sitetop_don_vi_tien_user() {
    return sitetop_che_do_usd() ? '$' : 'đ';
}

/* ============================================================
   ĐỊNH DẠNG
   Theo đúng cách chủ site viết trong yêu cầu: dấu phẩy là phần lẻ, dấu chấm là nghìn
   ("$0,035", "$3,50", "$1.234,50"). KHÔNG LÀM TRÒN (chủ site chốt 06/10/2026): in đúng
   số đang có trong sổ, tối thiểu 2 số lẻ, tối đa 8 (bằng độ lẻ cột lưu), chỉ bỏ số 0 thừa.
   $9,4909 in là "$9,4909", không phải "$9,49". Số chẵn bỏ phần lẻ: "$30" chứ không "$30,00";
   có lẻ thì giữ tối thiểu 2 số lẻ: "$3,50", "$0,035".
   ============================================================ */
function sitetop_format_usd( $usd ) {
    $usd = (float) $usd;
    $am  = $usd < 0 ? '-' : '';
    $x   = abs( $usd );
    $s = number_format( $x, SITETOP_USD_LE, ',', '.' );          // đủ 8 số lẻ — không cắt phần nào đang có
    if ( preg_match( '/,0+$/', $s ) ) {
        $s = preg_replace( '/,0+$/', '', $s );                      // số chẵn: "$30" chứ không "$30,00" (chủ site chốt 06/10)
    } else {
        $s = preg_replace( '/(,\d{2}\d*?)0+$/', '$1', $s );      // có lẻ: giữ tối thiểu 2 số lẻ ($3,50), chỉ bỏ số 0 thừa
    }
    return $am . '$' . $s;
}

/** Số USD KHÔNG kèm ký hiệu — cho chữ mờ ô nhập rút tiền (chủ site chốt 06/10: "30,00 $").
 *  Dấu phẩy thập phân, tối thiểu 2 số lẻ, giữ đủ số lẻ đang có (tới 8), KHÔNG chấm hàng nghìn
 *  để user gõ lại y nguyên vẫn đọc được: 30 → "30,00", 4.55 → "4,55", 2.27272727 → "2,27272727". */
function sitetop_usd_so( $usd, $min_le = 2 ) {
    $s = number_format( abs( (float) $usd ), SITETOP_USD_LE, ',', '' );
    return preg_replace( '/(,\d{' . (int) $min_le . '}\d*?)0+$/', '$1', $s );
}

/** CẮT số USD còn $le số lẻ — cắt chứ KHÔNG làm tròn, làm trên chuỗi để không dính sai số float
 *  (0,29 × 100 = 28,999… floor ra 28). Dùng cho trần "Toàn bộ số dư" (luật rút tối đa 2 số lẻ,
 *  chủ site chốt 06/10/2026) và cho bản in gọn bên dưới. */
function sitetop_usd_cat_le( $usd, $le = 2 ) {
    $usd = (float) $usd;
    $s   = number_format( abs( $usd ), SITETOP_USD_LE, '.', '' );
    list( $nguyen, $thap ) = array_pad( explode( '.', $s, 2 ), 2, '' );
    $cat = (float) ( $nguyen . '.' . substr( $thap, 0, max( 0, (int) $le ) ) );
    return $usd < 0 ? -$cat : $cat;
}
/** Bản GỌN cho con số to ở đầu trang (chủ site chốt 06/10: "$100,02272727" chỉ cần hiện "$100,022"):
 *  cắt còn 3 số lẻ (100,0229 → 100,022). Số dư thật trong CSDL và các chỗ khác vẫn đủ 8 số lẻ. */
function sitetop_format_usd_gon( $usd, $le = 3 ) {
    return sitetop_format_usd( sitetop_usd_cat_le( $usd, $le ) );
}
function sitetop_format_tien_user_gon( $amount, $le = 3 ) {
    return sitetop_che_do_usd() ? sitetop_format_usd_gon( $amount, $le ) : sitetop_format_money( $amount );
}

/** In tiền USER theo đơn vị hiện hành. Tiền KHÁCH HÀNG vẫn dùng sitetop_format_money(). */
function sitetop_format_tien_user( $amount ) {
    return sitetop_che_do_usd() ? sitetop_format_usd( $amount ) : sitetop_format_money( $amount );
}

/**
 * Số tiền LỆNH RÚT cho ADMIN xem: USD kèm số VNĐ quy đổi để thanh toán, ví dụ
 * "$35,00 (≈ 770.000đ)". Chế độ VNĐ in như cũ. Chỉ dùng ở màn/thông báo cho admin —
 * user chỉ thấy USD.
 */
function sitetop_format_rut_cho_admin( $amount ) {
    if ( ! sitetop_che_do_usd() ) return sitetop_format_money( $amount );
    return sitetop_format_usd( $amount ) . ' (≈ ' . sitetop_format_money( sitetop_usd_sang_vnd( $amount ) ) . ')';
}

/** Bản HTML cho thẻ thống kê admin (chủ site 06/10: "thu nhỏ chữ lại"): USD đứng trước, phần VNĐ
 *  quy đổi xuống dòng riêng trong <small> để CSS thu nhỏ. VNĐ thuần thì in như cũ. Đã esc_html. */
function sitetop_format_rut_cho_admin_html( $amount ) {
    if ( ! sitetop_che_do_usd() ) return esc_html( sitetop_format_money( $amount ) );
    return esc_html( sitetop_format_usd( $amount ) )
        . ' <small>≈ ' . esc_html( sitetop_format_money( sitetop_usd_sang_vnd( $amount ) ) ) . '</small>';
}

/**
 * Số tiền USD để GHÉP THẲNG vào câu SQL — KHÔNG đi qua %f. wpdb::prepare() đổi %f thành %F rồi
 * vsprintf với 6 số lẻ mặc định: 0,02272727 thành 0,022727 — mất tiền user mỗi view, đúng cái
 * "làm tròn" chủ site cấm (06/10/2026). Chuỗi trả về chỉ gồm chữ số và dấu chấm nên ghép
 * thẳng là an toàn; cột DECIMAL(20,8) cộng với literal DECIMAL là phép tính chính xác.
 */
function sitetop_so_sql_usd( $amount ) {
    return number_format( abs( (float) $amount ), SITETOP_USD_LE, '.', '' );
}

/** Chuẩn hoá một khoản tiền user trước khi ghi sổ: USD giữ đủ 8 số lẻ (chỉ gạt nhiễu float ở
 *  số lẻ thứ 9 trở đi — mọi mức rate admin nhập được đều ≤ 7 số lẻ/view nên không mất gì);
 *  VNĐ giữ đúng hành vi cũ (absint). */
function sitetop_lam_tron_tien_user( $amount ) {
    return sitetop_che_do_usd() ? round( abs( (float) $amount ), SITETOP_USD_LE ) : absint( $amount );
}

/* ============================================================
   RATE THƯỞNG USER
   ============================================================ */
function sitetop_nhom_camp( $campaign_type ) {
    return ( $campaign_type === 'keyword_search' ) ? 'keyword' : 'direct';
}

/** Rate USD / 1.000 view của một loại camp (số admin nhập trong Cài đặt). */
function sitetop_usd_rate_nghin_view( $campaign_type, $traffic_type ) {
    $traffic_type = $traffic_type ?: '1step';
    return (float) sitetop_get_option( 'usd_user_' . sitetop_nhom_camp( $campaign_type ) . '_' . $traffic_type, 0 );
}

/**
 * Mức thưởng user cho MỘT view của camp sắp tạo / sắp đổi loại — giá trị này được đóng
 * băng vào keyword_campaigns.user_reward. Trước đây công thức được chép tay ở 4 chỗ
 * (admin tạo, admin sửa, khách tạo, khách sửa); nay gom về đây.
 * VNĐ: y hệt công thức cũ (rate + phụ phí onsite, mặc định 800).
 * USD: (rate USD/1.000 view + phụ phí onsite USD/1.000 view) / 1.000.
 */
function sitetop_user_reward_cho_camp( $campaign_type, $traffic_type, $onsite ) {
    $traffic_type = $traffic_type ?: '1step';
    $onsite       = (int) $onsite;
    if ( sitetop_che_do_usd() ) {
        $goc  = sitetop_usd_rate_nghin_view( $campaign_type, $traffic_type );
        $them = (float) sitetop_get_option( 'usd_user_onsite_extra_' . $onsite, 0 );
        return round( ( $goc + $them ) / 1000, SITETOP_USD_LE );
    }
    $key  = ( $campaign_type === 'keyword_search' ? 'keyword_user_' : 'direct_user_' ) . $traffic_type;
    $goc  = floatval( sitetop_get_option( $key, 800 ) );
    $them = in_array( $onsite, array( 70, 80, 90, 100, 120, 150 ), true )
        ? (int) sitetop_get_option( 'user_onsite_extra_' . $onsite, 0 ) : 0;
    return $goc + $them;
}

/* ============================================================
   TẠM KHOÁ TIỀN — dùng trong ~1 phút chuyển dữ liệu
   Lưu MỐC HẾT HẠN chứ không lưu cờ 0/1: script chuyển có chết giữa chừng thì sau 5 phút
   khoá tự mở, không treo hệ thống.
   ============================================================ */
function sitetop_tam_khoa_tien() {
    $het = (int) get_option( 'sitetop_tam_khoa_tien', 0 );
    return $het > time();
}

function sitetop_tam_khoa_tien_thong_bao() {
    return 'Hệ thống đang cập nhật số dư, vui lòng thử lại sau 1 phút.';
}

/* ============================================================
   CHUYỂN DỮ LIỆU VNĐ → USD (chạy MỘT lần)

   Gọi: wp eval 'print_r( sitetop_chuyen_sang_usd( false ) );'   ← chạy thử, ROLLBACK
        wp eval 'print_r( sitetop_chuyen_sang_usd( true ) );'    ← chạy thật

   Các bước:
   1. (chạy thật) bật tạm khoá tiền, chờ 35 giây cho mọi request đang trả thưởng dở xong.
   2. Nới các cột tiền user lên DECIMAL(20,8) — không mất dữ liệu, chạy được cả ở chế độ VNĐ.
   3. Chụp số dư VNĐ của TỪNG user (số dư nhiệm vụ + số dư hoa hồng) bằng chính hàm tính
      số dư của hệ thống.
   4. (chạy thật) sao lưu 6 bảng thành bảng *_bak_vnd ngay trong CSDL.
   5. Trong MỘT transaction: chia mọi cột tiền user cho tỷ giá.
   6. Đối soát: số dư mới × tỷ giá phải khớp số dư cũ ±1đ cho TỪNG user. Lệch một người
      là ROLLBACK cả lô. Chạy thử thì luôn ROLLBACK.
   7. (chạy thật, khớp hết) COMMIT, đổi rate/min/max sang USD, bật công tắc, mở khoá.
   ============================================================ */
function sitetop_cot_tien_user() {
    return array(
        'user_balance'      => array( 'balance', 'total_earned' ),
        'transactions'      => array( 'amount', 'balance_after' ),
        'withdrawals'       => array( 'amount', 'refund_amount' ),
        'shortlink_visits'  => array( 'reward_amount' ),
        'keyword_campaigns' => array( 'user_reward', 'total_earnings' ),
        'user_shortlinks'   => array( 'total_earnings' ),
    );
}

function sitetop_mo_rong_cot_tien_user() {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    $da = array();
    foreach ( sitetop_cot_tien_user() as $bang => $cots ) {
        $kieu = $wpdb->get_results( "SHOW COLUMNS FROM {$p}{$bang}", OBJECT_K );
        foreach ( $cots as $c ) {
            if ( empty( $kieu[ $c ] ) ) continue;
            if ( stripos( $kieu[ $c ]->Type, 'decimal(20,8)' ) !== false ) continue;
            $null = ( strtoupper( $kieu[ $c ]->Null ) === 'YES' ) ? 'NULL' : 'NOT NULL';
            $mac  = ( $kieu[ $c ]->Default !== null ) ? ' DEFAULT ' . (float) $kieu[ $c ]->Default : '';
            $wpdb->query( "ALTER TABLE {$p}{$bang} MODIFY COLUMN {$c} DECIMAL(20,8) {$null}{$mac}" );
            $da[] = "{$bang}.{$c}";
        }
    }
    return $da;
}

function sitetop_chuyen_sang_usd( $chay_that = false, $tuy = array() ) {
    global $wpdb;
    $p  = $wpdb->prefix . 'sitetop_';
    $R  = sitetop_usd_rate();
    /* Tuỳ chọn cho site LỚN (.net 06/10: shortlink_visits 746k dòng / 888 MB, user_shortlinks 370k dòng):
       - khoa_giay: thời gian khoá tiền tối đa (phải phủ hết lúc chia).
       - cho_giay : chờ request đang chạy xong trước khi chụp số dư.
       - bang_lo  : bảng TO chia theo LÔ id ngoài transaction lớn. Chỉ được đưa vào đây bảng có cột
                    CHỈ ĐỂ XEM (reward_amount của visits, total_earnings của links) — số dư user tính từ
                    transactions + withdrawals, không đụng tới. Chạy thử KHÔNG đụng bảng lô (quá nặng, và
                    không ảnh hưởng đối soát).
       - co_lo    : số dòng mỗi lô. */
    $tuy = array_merge( array( 'khoa_giay' => 300, 'cho_giay' => 35, 'bang_lo' => array(), 'co_lo' => 20000 ), (array) $tuy );
    $bang_lo = array_values( array_intersect( (array) $tuy['bang_lo'], array_keys( sitetop_cot_tien_user() ) ) );
    $bc = array( 'chay_that' => (bool) $chay_that, 'ty_gia' => $R, 'bang_lo' => $bang_lo );

    if ( sitetop_che_do_usd() ) return array( 'loi' => 'Đã ở chế độ USD rồi, không chuyển lại.' );

    // 1. Nới cột — TRƯỚC khi khoá tiền. ALTER bảng to là việc nặng nhất (MariaDB copy cả bảng) và
    //    không cần khoá: cột rộng hơn vẫn chứa số VNĐ cũ nguyên vẹn.
    $bc['noi_cot'] = sitetop_mo_rong_cot_tien_user();

    // 2. Sao lưu trong CSDL — cũng TRƯỚC khi khoá. Đọc bảng gốc kiểu READ COMMITTED để INSERT…SELECT
    //    không đặt khoá dòng lên bảng đang chạy (REPEATABLE READ mặc định sẽ khoá cả khoảng trống →
    //    chặn luôn lượt mới chèn vào). Dòng phát sinh sau lúc chụp vẫn được chia ở bước 5/6.
    if ( $chay_that ) {
        $wpdb->query( 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED' );
        foreach ( array_keys( sitetop_cot_tien_user() ) as $bang ) {
            $bak = "{$p}{$bang}_bak_vnd";
            $wpdb->query( "DROP TABLE IF EXISTS {$bak}" );
            $wpdb->query( "CREATE TABLE {$bak} LIKE {$p}{$bang}" );
            $wpdb->query( "INSERT INTO {$bak} SELECT * FROM {$p}{$bang}" );
        }
        $wpdb->query( 'SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ' );
        update_option( 'sitetop_usd_sao_luu', array(
            'luc' => time(), 'ty_gia' => $R,
            'opt' => array_map( function ( $k ) { return get_option( 'sitetop_' . $k ); }, array_combine( sitetop_usd_khoa_option_cu(), sitetop_usd_khoa_option_cu() ) ),
        ), false );
    }

    // 3. Khoá tiền rồi chờ request đang chạy xong. Từ đây tới COMMIT không có đồng thưởng VNĐ nào
    //    được ghi thêm — điều kiện để chia bảng lô ngoài transaction vẫn an toàn.
    if ( $chay_that ) {
        update_option( 'sitetop_tam_khoa_tien', time() + (int) $tuy['khoa_giay'], false );
        if ( ! defined( 'SITETOP_USD_KHONG_CHO' ) ) sleep( (int) $tuy['cho_giay'] );   // test định nghĩa hằng này để khỏi chờ
    }

    // 4. Chụp số dư cũ của từng user
    $uids = $wpdb->get_col(
        "SELECT user_id FROM {$p}transactions UNION SELECT user_id FROM {$p}withdrawals UNION SELECT user_id FROM {$p}user_balance" );
    $cu = array();
    foreach ( $uids as $u ) {
        $u = (int) $u;
        if ( ! $u ) continue;
        $cu[ $u ] = array(
            'nv' => (float) sitetop_get_user_balance_amount( $u ),
            'hh' => function_exists( 'sitetop_get_referral_balance_amount' ) ? (float) sitetop_get_referral_balance_amount( $u ) : 0.0,
        );
    }
    $bc['so_user'] = count( $cu );
    $bc['tong_so_du_vnd'] = array_sum( array_column( $cu, 'nv' ) );

    // 5. Bảng lô: chia từng khoảng id, mỗi lô tự COMMIT (khoá dòng ngắn, không giữ undo khổng lồ).
    //    Ghi lại khoảng đã chia để nếu bước 6 lệch thì nhân ngược đúng khoảng đó.
    $cot_tat_ca = sitetop_cot_tien_user();
    $lo_da_chia = array();
    $bc['bang_lo_dong'] = array();
    foreach ( $bang_lo as $bang ) {
        $max_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM {$p}{$bang}" );
        $bc['bang_lo_dong'][ $bang ] = $max_id;
        if ( ! $chay_that || $max_id <= 0 ) continue;
        $set = implode( ', ', array_map( function ( $c ) use ( $R ) {
            return "{$c} = ROUND({$c} / {$R}, " . SITETOP_USD_LE . ")";
        }, $cot_tat_ca[ $bang ] ) );
        for ( $tu = 1; $tu <= $max_id; $tu += (int) $tuy['co_lo'] ) {
            $den = min( $max_id, $tu + (int) $tuy['co_lo'] - 1 );
            $wpdb->query( "UPDATE {$p}{$bang} SET {$set} WHERE id BETWEEN {$tu} AND {$den}" );
        }
        $lo_da_chia[ $bang ] = $max_id;
    }

    // 6. Các bảng còn lại (trong đó có transactions + withdrawals = nguồn số dư) chia trong MỘT transaction.
    $wpdb->query( 'START TRANSACTION' );
    foreach ( $cot_tat_ca as $bang => $cots ) {
        if ( in_array( $bang, $bang_lo, true ) ) continue;
        $set = implode( ', ', array_map( function ( $c ) use ( $R ) {
            return "{$c} = ROUND({$c} / {$R}, " . SITETOP_USD_LE . ")";
        }, $cots ) );
        $wpdb->query( "UPDATE {$p}{$bang} SET {$set}" );
    }

    // 7. Đối soát — đọc bằng CHÍNH hàm tính số dư (cùng transaction nên thấy số đã chia).
    $lech = array();
    foreach ( $cu as $u => $c ) {
        $moi_nv = (float) sitetop_get_user_balance_amount( $u );
        $moi_hh = function_exists( 'sitetop_get_referral_balance_amount' ) ? (float) sitetop_get_referral_balance_amount( $u ) : 0.0;
        $d_nv = abs( $moi_nv * $R - $c['nv'] );
        $d_hh = abs( $moi_hh * $R - $c['hh'] );
        if ( $d_nv > 1 || $d_hh > 1 ) {
            $lech[] = array( 'user' => $u, 'cu_vnd' => $c['nv'], 'moi_usd' => $moi_nv, 'lech_vnd' => round( max( $d_nv, $d_hh ), 4 ) );
        }
    }
    $bc['tong_so_du_usd'] = round( $bc['tong_so_du_vnd'] / $R, 2 );
    $bc['user_lech'] = $lech;

    if ( $lech || ! $chay_that ) {
        $wpdb->query( 'ROLLBACK' );
        // Bảng lô đã chia ngoài transaction → nhân ngược đúng khoảng id đã chia (về đồng chẵn như cũ).
        foreach ( $lo_da_chia as $bang => $max_id ) {
            $set = implode( ', ', array_map( function ( $c ) use ( $R ) { return "{$c} = ROUND({$c} * {$R}, 2)"; }, $cot_tat_ca[ $bang ] ) );
            for ( $tu = 1; $tu <= $max_id; $tu += (int) $tuy['co_lo'] ) {
                $den = min( $max_id, $tu + (int) $tuy['co_lo'] - 1 );
                $wpdb->query( "UPDATE {$p}{$bang} SET {$set} WHERE id BETWEEN {$tu} AND {$den}" );
            }
        }
        $bc['ket_qua'] = $lech ? 'ROLLBACK — có user lệch, KHÔNG chuyển' . ( $lo_da_chia ? ' (bảng lô đã nhân ngược)' : '' ) : 'ROLLBACK — chạy thử, mọi user khớp';
        if ( $chay_that ) delete_option( 'sitetop_tam_khoa_tien' );
        return $bc;
    }
    $wpdb->query( 'COMMIT' );

    /* BẬT CÔNG TẮC NGAY SAU COMMIT — trước mọi việc phụ bên dưới. Nếu PHP chết giữa chừng
       sau COMMIT mà công tắc còn tắt thì dữ liệu đã chia nhưng hệ vẫn chạy VNĐ: mỗi view cộng
       500 "đồng" vào số dư đang tính bằng USD (= $500). Ghi mốc + tỷ giá cùng lúc để
       sitetop_lui_ve_vnd() biết nhân ngược bằng đúng số nào. */
    update_option( 'sitetop_che_do_usd', 1 );
    update_option( 'sitetop_usd_chuyen_luc', array( 'luc' => time(), 'ty_gia' => $R ), false );

    // 8. Rate, ngưỡng rút, rate riêng → USD. Ô nào admin ĐÃ nhập sẵn trong Cài đặt thì giữ
    //    nguyên số admin nhập, chỉ điền những ô còn trống bằng giá trị quy đổi.
    $dat = function ( $khoa, $gia_tri ) {
        if ( (string) get_option( 'sitetop_' . $khoa, '' ) === '' ) update_option( 'sitetop_' . $khoa, $gia_tri );
    };
    foreach ( array( 'keyword', 'direct' ) as $n ) {
        foreach ( array( '1step', '2step', 'nocode' ) as $t ) {
            $vnd = (float) sitetop_get_option( $n . '_user_' . $t, 800 );
            $dat( 'usd_user_' . $n . '_' . $t, round( $vnd / $R * 1000, 4 ) );
        }
    }
    foreach ( array( 70, 80, 90, 100, 120, 150 ) as $giay ) {
        $vnd = (float) sitetop_get_option( 'user_onsite_extra_' . $giay, 0 );
        $dat( 'usd_user_onsite_extra_' . $giay, round( $vnd / $R * 1000, 4 ) );
    }
    $dat( 'min_withdrawal_usd', round( (float) sitetop_get_option( 'min_withdrawal', 50000 ) / $R, 2 ) );
    $dat( 'max_withdrawal_usd', round( (float) sitetop_get_option( 'max_withdrawal', 0 ) / $R, 2 ) );
    $dat( 'referral_min_payout_usd', round( (float) sitetop_get_option( 'referral_min_payout', 50000 ) / $R, 2 ) );
    foreach ( $wpdb->get_results( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'sitetop_rate_rieng'" ) as $m ) {
        $bang = maybe_unserialize( $m->meta_value );
        if ( ! is_array( $bang ) ) continue;
        $usd = array();
        foreach ( $bang as $k => $v ) {
            if ( (float) $v > 0 ) $usd[ $k ] = round( (float) $v / $R * 1000, 4 );
        }
        update_user_meta( (int) $m->user_id, 'sitetop_rate_rieng_usd', $usd );
    }

    // Đồng bộ lại cột cache user_balance từ sổ đã chia
    if ( function_exists( 'sitetop_sync_user_balance' ) ) {
        foreach ( array_keys( $cu ) as $u ) sitetop_sync_user_balance( $u );
    }

    delete_option( 'sitetop_tam_khoa_tien' );
    $bc['ket_qua'] = 'ĐÃ CHUYỂN SANG USD';
    return $bc;
}

/** Các option VNĐ cũ chụp lại để lùi được. */
function sitetop_usd_khoa_option_cu() {
    return array(
        'keyword_user_1step', 'keyword_user_2step', 'keyword_user_nocode',
        'direct_user_1step', 'direct_user_2step', 'direct_user_nocode',
        'user_onsite_extra_70', 'user_onsite_extra_80', 'user_onsite_extra_90',
        'user_onsite_extra_100', 'user_onsite_extra_120', 'user_onsite_extra_150',
        'min_withdrawal', 'max_withdrawal', 'referral_min_payout',
    );
}

/* ============================================================
   BỘ ĐỊNH DẠNG CHO JAVASCRIPT — cùng quy tắc với sitetop_format_usd() ở trên.
   Các màn admin/user có hàm JS tự gắn 'đ' (wdMoney, fm, soDuTien…). Thay vì sửa từng
   hàm theo một kiểu, chúng gọi về stTienUser() — đổi đơn vị theo đúng công tắc máy chủ.
   In ở mọi trang admin, và ở front-end khi đã đăng nhập (dashboard user).
   ============================================================ */
function sitetop_in_js_tien_user() {
    static $da_in = false;
    if ( $da_in ) return;
    $da_in = true;
    printf(
        '<script>var ST_USD=%d,ST_TYGIA=%s;'
        . 'function stUsd(n){n=Number(n||0);var a=Math.abs(n),o=Number.isInteger(a)?{maximumFractionDigits:0}:{minimumFractionDigits:2,maximumFractionDigits:8};return(n<0?"-":"")+"$"+a.toLocaleString("vi-VN",o);}'
        . 'function stVnd(n){return Math.round(Number(n||0)).toLocaleString("vi-VN")+"đ";}'
        . 'function stTienUser(n){return ST_USD?stUsd(n):stVnd(n);}'
        . 'function stRutAdmin(n){return ST_USD?stUsd(n)+" (≈ "+stVnd(Number(n||0)*ST_TYGIA)+")":stVnd(n);}'
        . 'function stRutAdminHtml(n){return ST_USD?stUsd(n)+" <small>≈ "+stVnd(Number(n||0)*ST_TYGIA)+"</small>":stVnd(n);}'
        . '</script>',
        sitetop_che_do_usd() ? 1 : 0,
        wp_json_encode( (float) sitetop_usd_rate() )
    );
}
add_action( 'admin_head', 'sitetop_in_js_tien_user' );
add_action( 'wp_head', function () { if ( is_user_logged_in() ) sitetop_in_js_tien_user(); } );

/* ============================================================
   LÙI VỀ VNĐ — nhân ngược theo đúng tỷ giá đã chuyển, KHÔNG mất giao dịch phát sinh sau đó.
   Gọi: wp eval 'print_r( sitetop_lui_ve_vnd() );'
   Bảng *_bak_vnd vẫn còn nguyên để đối chiếu; không dùng nó để ghi đè (ghi đè là mất mọi
   dòng mới). Rate riêng: khoá VNĐ cũ 'sitetop_rate_rieng' chưa bao giờ bị xoá nên tự khớp.
   ============================================================ */
function sitetop_lui_ve_vnd() {
    global $wpdb;
    $p = $wpdb->prefix . 'sitetop_';
    if ( ! sitetop_che_do_usd() ) return array( 'loi' => 'Đang ở chế độ VNĐ rồi.' );
    $moc = get_option( 'sitetop_usd_chuyen_luc', array() );
    $R   = (float) ( is_array( $moc ) ? ( $moc['ty_gia'] ?? 0 ) : 0 );
    if ( $R <= 0 ) return array( 'loi' => 'Không có tỷ giá đã chuyển (sitetop_usd_chuyen_luc) — không lùi mù được.' );

    update_option( 'sitetop_tam_khoa_tien', time() + 300, false );
    if ( ! defined( 'SITETOP_USD_KHONG_CHO' ) ) sleep( 35 );

    $uids = $wpdb->get_col(
        "SELECT user_id FROM {$p}transactions UNION SELECT user_id FROM {$p}withdrawals UNION SELECT user_id FROM {$p}user_balance" );

    $wpdb->query( 'START TRANSACTION' );
    foreach ( sitetop_cot_tien_user() as $bang => $cots ) {
        $set = implode( ', ', array_map( function ( $c ) use ( $R ) {
            return "{$c} = ROUND({$c} * {$R}, 2)";
        }, $cots ) );
        $wpdb->query( "UPDATE {$p}{$bang} SET {$set}" );
    }
    $wpdb->query( 'COMMIT' );

    // Tắt công tắc NGAY — cùng lý do với lúc bật (xem sitetop_chuyen_sang_usd).
    update_option( 'sitetop_che_do_usd', 0 );
    update_option( 'sitetop_usd_lui_luc', array( 'luc' => time(), 'ty_gia' => $R ), false );

    $sl = get_option( 'sitetop_usd_sao_luu', array() );
    foreach ( (array) ( is_array( $sl ) ? ( $sl['opt'] ?? array() ) : array() ) as $k => $v ) {
        if ( $v !== false && $v !== null && $v !== '' ) update_option( 'sitetop_' . $k, $v );
    }
    if ( function_exists( 'sitetop_sync_user_balance' ) ) {
        foreach ( $uids as $u ) if ( (int) $u ) sitetop_sync_user_balance( (int) $u );
    }
    delete_option( 'sitetop_tam_khoa_tien' );
    return array( 'ket_qua' => 'ĐÃ LÙI VỀ VNĐ', 'ty_gia' => $R, 'so_user' => count( $uids ) );
}
