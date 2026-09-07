<?php
/* Nhận diện "user đang làm BƯỚC 2" — shortlink-ajax.php, biến $step2_continue,
   trả về widget qua $result['step2_return'].

   BẪY ĐÃ MẮC 05/09/2026: khối tính cờ này nằm trong nhánh `if ( ! $visit )`, tức chỉ
   chạy khi vòng dò ứng viên TRƯỢT. Ngày xưa vòng dò so host+path nên đứng ở trang bước 2
   (cùng site, khác đường dẫn) là trượt → khối chạy. Sau khi nới vòng dò thành so DOMAIN
   thì nó KHỚP LUÔN → khối không bao giờ chạy → cờ kẹt ở false → user camp 2 bước bị treo
   mỗi khi cờ localStorage của widget trượt.

   Bài học: câu hỏi "user đã RỜI trang đích chưa" phải hỏi bằng so khớp CHẶT (host+path),
   KHÔNG được hỏi bằng cổng domain đã nới — cổng đó trang nào cùng site cũng trả true.

   Bản dưới mô phỏng logic của shortlink-ajax.php sau khi sửa. */

function _s2_host( $u ) {
    $h = parse_url( (string) $u, PHP_URL_HOST );
    return $h ? preg_replace( '/^www\./', '', strtolower( $h ) ) : '';
}
function _s2_key( $u ) {
    $h = _s2_host( $u ); if ( $h === '' ) return '';
    $p = (string) parse_url( (string) $u, PHP_URL_PATH );
    $p = preg_replace( '/(?:%20|\s|\/)+$/i', '', $p );
    if ( $p === '' ) $p = '/';
    return $h . strtolower( $p );
}

/** @param $v mảng lượt: traffic_type, url_matched, troi (giây đã trôi), onsite_time, dests */
function _s2_detect( $v, $client_url ) {
    if ( ( $v['traffic_type'] ?? '' ) !== '2step' ) return false;
    if ( empty( $v['url_matched'] ) ) return false;
    $cur = _s2_key( $client_url );
    foreach ( $v['dests'] as $d ) if ( _s2_key( $d ) === $cur ) return false; // vẫn ở trang đích
    if ( _s2_host( $client_url ) !== _s2_host( $v['dests'][0] ) ) return false; // khác site
    $req = max( (int) ( $v['onsite_time'] ?? 70 ) - 5, 10 );
    return ( (int) $v['troi'] >= $req );
}

$base = array(
    'traffic_type' => '2step',
    'url_matched'  => 1,
    'onsite_time'  => 70,      // → cần 65 giây
    'troi'         => 90,      // đã xong bước 1
    'dests'        => array( 'https://khach.com/san-pham' ),
);

// ── Trường hợp CHÍNH: đã xong bước 1, đã sang trang bước 2 ───────────────
assert_true(  _s2_detect( $base, 'https://khach.com/lien-he' ),
    'Xong buoc 1 + da sang trang khac cung site -> BAT co buoc 2' );
assert_true(  _s2_detect( $base, 'https://khach.com/tin-tuc/bai-1' ),
    'Trang buoc 2 nam sau nhieu cap -> BAT' );
assert_true(  _s2_detect( $base, 'https://www.khach.com/lien-he' ),
    'Trang buoc 2 co www -> BAT' );

// ── Vẫn đứng NGUYÊN trang đích thì chưa phải bước 2 ──────────────────────
assert_false( _s2_detect( $base, 'https://khach.com/san-pham' ),
    'Van o dung trang dich -> CHUA phai buoc 2' );
assert_false( _s2_detect( $base, 'https://khach.com/san-pham/' ),
    'Trang dich co dau / cuoi -> van la trang dich' );
assert_false( _s2_detect( $base, 'https://www.khach.com/san-pham?utm=fb' ),
    'Trang dich + www + tham so -> van la trang dich' );

// ── Chưa xong bước 1 thì KHÔNG được đẩy sang nhánh 15 giây ───────────────
$chua_du = array_merge( $base, array( 'troi' => 30 ) );
assert_false( _s2_detect( $chua_du, 'https://khach.com/lien-he' ),
    'Bam nham link noi bo GIUA CHUNG -> KHONG bat (dong ho chua chay het)' );
$sat_nguong = array_merge( $base, array( 'troi' => 64 ) );
assert_false( _s2_detect( $sat_nguong, 'https://khach.com/lien-he' ), 'Thieu 1 giay -> chua bat' );
$dung_nguong = array_merge( $base, array( 'troi' => 65 ) );
assert_true(  _s2_detect( $dung_nguong, 'https://khach.com/lien-he' ), 'Dung 65 giay -> bat' );

// ── Các cửa an toàn không được nới ───────────────────────────────────────
assert_false( _s2_detect( array_merge( $base, array( 'url_matched' => 0 ) ), 'https://khach.com/lien-he' ),
    'Chua tung dung o URL dich (url_matched=0) -> KHONG bat' );
assert_false( _s2_detect( array_merge( $base, array( 'traffic_type' => 'direct' ) ), 'https://khach.com/lien-he' ),
    'Camp 1 buoc -> KHONG bat (bat la day sai luong)' );
assert_false( _s2_detect( $base, 'https://web-khac.com/lien-he' ),
    'Nhay sang website khac -> KHONG bat' );
assert_false( _s2_detect( $base, 'https://khach.com.evil.net/x' ),
    'Domain gia dinh duoi -> KHONG bat' );

// ── Camp nhiều URL đích: đứng ở BẤT KỲ URL đích nào cũng chưa là bước 2 ──
$nhieu = array_merge( $base, array( 'dests' => array( 'https://khach.com/a', 'https://khach.com/b' ) ) );
assert_false( _s2_detect( $nhieu, 'https://khach.com/a' ), 'Dang o URL dich thu nhat -> chua phai buoc 2' );
assert_false( _s2_detect( $nhieu, 'https://khach.com/b' ), 'Dang o URL dich thu hai -> chua phai buoc 2' );
assert_true(  _s2_detect( $nhieu, 'https://khach.com/c' ), 'Sang trang ngoai danh sach -> BAT' );

echo "  ✓ step2 detect (khong phu thuoc cong domain da noi)\n";
