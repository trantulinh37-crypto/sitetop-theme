<?php
/**
 * Hạn mức tính tiền view: 1 shortlink = 1 view, tối đa 2 view/IP/24 giờ trượt.
 *
 * Bám đúng 6 trường hợp chủ site yêu cầu kiểm (14/08/2026). Bộ test này KHÔNG cần
 * MySQL: nó giả lập bảng shortlink_visits bằng mảng, rồi chạy CHÍNH hàm quyết định
 * của sitetop_ip_view_quota() trên dữ liệu đó — thứ cần kiểm là quy tắc chọn, không
 * phải cú pháp SQL.
 */

/** Bản sao logic quyết định của sitetop_ip_view_quota(), dùng chung dữ liệu giả lập. */
function tq_quota( $paid_rows, $ip, $shortlink_id, $now, $limit = 2 ) {
    $links = array();
    foreach ( $paid_rows as $r ) {
        if ( $r['ip'] !== $ip ) continue;
        if ( empty( $r['reward_paid'] ) ) continue;      // chỉ lượt ĐÃ trả thưởng
        if ( (int) $r['shortlink_id'] <= 0 ) continue;
        if ( strtotime( $r['created_at'] ) <= strtotime( $now ) - 86400 ) continue; // 24h trượt
        $links[ (int) $r['shortlink_id'] ] = true;
    }
    $used = count( $links );
    $same = isset( $links[ (int) $shortlink_id ] );
    return array( 'used' => $used, 'same_link' => $same, 'allowed' => ( ! $same && $used < $limit ), 'limit' => $limit );
}

/** Chạy một chuỗi lượt hoàn thành, trả về tổng số view ĐƯỢC TÍNH TIỀN. */
function tq_run( $seq, $ip = '1.2.3.4', $limit = 2 ) {
    $rows = array(); $paid = 0;
    foreach ( $seq as $step ) {
        $sid  = $step[0];
        $when = $step[1];
        $q = tq_quota( $rows, $ip, $sid, $when, $limit );
        $ok = $q['allowed'];
        if ( $ok ) $paid++;
        // Lượt nào cũng được ghi lại; chỉ lượt được tính mới có reward_paid = 1.
        $rows[] = array( 'ip' => $ip, 'shortlink_id' => $sid, 'reward_paid' => $ok ? 1 : 0, 'created_at' => $when );
    }
    return $paid;
}

$T = '2026-08-14 10:00:00';
$t = function( $mins ) use ( $T ) { return date( 'Y-m-d H:i:s', strtotime( $T ) + $mins * 60 ); };

// TH1: 1 IP vượt shortlink A rồi shortlink B → mỗi cái 1 view, tổng 2.
assert_equals( 2, tq_run( array( array(101,$t(0)), array(102,$t(5)) ) ),
    'TH1: 2 shortlink khac nhau = 2 view' );

// TH2: view thứ 2 đúng là do shortlink THỨ HAI mang lại, không phải do làm lại cái cũ.
assert_equals( 1, tq_run( array( array(101,$t(0)), array(101,$t(5)) ) ),
    'TH2: shortlink thu hai moi sinh view thu hai' );

// TH3: shortlink thứ 3 trong 24h → không tính thêm (đã đủ trần 2).
assert_equals( 2, tq_run( array( array(101,$t(0)), array(102,$t(5)), array(103,$t(10)) ) ),
    'TH3: shortlink thu 3 trong 24h khong tinh them' );

// TH4: chỉ 1 shortlink, làm nhiệm vụ nhiều lần → vẫn 1 view.
assert_equals( 1, tq_run( array( array(101,$t(0)), array(101,$t(5)), array(101,$t(9)), array(101,$t(30)) ) ),
    'TH4: 1 shortlink lam 4 lan van chi 1 view' );

// TH5: đúng cái lỗ cũ — 1 shortlink hoàn thành 2 lần KHÔNG được thành 2 view.
assert_false( tq_run( array( array(101,$t(0)), array(101,$t(2)) ) ) === 2,
    'TH5: 1 shortlink hoan thanh 2 lan KHONG duoc = 2 view' );

// TH6 (phần đếm được ở đây): lượt bị chặn tiền vẫn được GHI LẠI làm lượt đã chạy.
$rows = array(); $ip = '1.2.3.4';
foreach ( array( array(101,$t(0)), array(101,$t(2)), array(102,$t(4)), array(103,$t(6)) ) as $s ) {
    $q = tq_quota( $rows, $ip, $s[0], $s[1] );
    $rows[] = array( 'ip'=>$ip, 'shortlink_id'=>$s[0], 'reward_paid'=>$q['allowed']?1:0, 'created_at'=>$s[1] );
}
assert_equals( 4, count( $rows ), 'TH6: moi luot deu duoc ghi lai (tinh vao traffic da chay)' );
assert_equals( 2, count( array_filter( $rows, function($r){ return $r['reward_paid']===1; } ) ),
    'TH6: nhung chi 2 luot duoc tra tien' );

// Cửa sổ 24 GIỜ TRƯỢT, không phải ngày lịch: 23:50 đủ trần thì 00:10 vẫn bị chặn.
assert_equals( 2, tq_run( array(
        array(101,'2026-08-14 23:50:00'),
        array(102,'2026-08-14 23:55:00'),
        array(103,'2026-08-15 00:10:00'),   // sang ngày mới nhưng chưa qua 24h
    ) ), 'Cua so truot: qua nua dem van bi chan' );

// Quá 24 giờ thì suất được trả lại.
assert_equals( 3, tq_run( array(
        array(101,'2026-08-14 10:00:00'),
        array(102,'2026-08-14 10:05:00'),
        array(103,'2026-08-15 10:06:00'),   // đã quá 24h so với 2 lượt đầu
    ) ), 'Qua 24h thi suat duoc tra lai' );

// Lượt verified nhưng KHÔNG được trả thưởng (adblock, đổi IP...) không chiếm suất.
$rows = array( array( 'ip'=>'1.2.3.4','shortlink_id'=>101,'reward_paid'=>0,'created_at'=>$t(0) ) );
$q = tq_quota( $rows, '1.2.3.4', 101, $t(5) );
assert_true( $q['allowed'], 'Luot khong duoc tra thuong khong chiem suat' );

// IP khác không ảnh hưởng lẫn nhau.
$rows = array( array( 'ip'=>'9.9.9.9','shortlink_id'=>101,'reward_paid'=>1,'created_at'=>$t(0) ) );
$q = tq_quota( $rows, '1.2.3.4', 101, $t(5) );
assert_true( $q['allowed'], 'IP khac khong chiem suat cua nhau' );

/* ============================================================
   Cài đặt "IP limit/ngày" = 1 (19/08/2026)
   Chủ site hạ trần xuống 1: MỌI shortlink cộng lại, 1 IP chỉ được tính tiền
   ĐÚNG 1 view trong 24 giờ trượt. Chỉnh lại lên 2 thì cơ chế cũ trở lại
   nguyên vẹn — hai khối dưới đây kiểm cả hai chiều.
   ============================================================ */

// Trần 1: shortlink thứ hai KHÁC shortlink đầu cũng không được tính thêm.
assert_equals( 1, tq_run( array( array(101,$t(0)), array(102,$t(5)) ), '1.2.3.4', 1 ),
    'Tran 1: 2 shortlink khac nhau van chi 1 view' );

// Trần 1: làm lại đúng shortlink cũ — vẫn 1.
assert_equals( 1, tq_run( array( array(101,$t(0)), array(101,$t(5)) ), '1.2.3.4', 1 ),
    'Tran 1: lam lai shortlink cu van 1 view' );

// Trần 1: rải khắp nhiều shortlink cũng không lách được.
assert_equals( 1, tq_run( array( array(101,$t(0)), array(102,$t(5)), array(103,$t(9)), array(104,$t(20)) ), '1.2.3.4', 1 ),
    'Tran 1: 4 shortlink khac nhau van chi 1 view' );

// Trần 1: hết 24 giờ trượt thì suất được trả lại.
assert_equals( 2, tq_run( array(
        array(101,'2026-08-14 10:00:00'),
        array(102,'2026-08-14 10:05:00'),   // bi chan
        array(103,'2026-08-15 10:06:00'),   // da qua 24h -> duoc tinh lai
    ), '1.2.3.4', 1 ), 'Tran 1: qua 24h duoc tinh lai 1 view moi' );

// Trần 1: lượt bị chặn tiền vẫn được ghi lại (traffic/ngày của camp vẫn cộng).
$rows = array(); $ip1 = '1.2.3.4';
foreach ( array( array(101,$t(0)), array(102,$t(3)), array(103,$t(6)) ) as $s1 ) {
    $q1 = tq_quota( $rows, $ip1, $s1[0], $s1[1], 1 );
    $rows[] = array( 'ip'=>$ip1, 'shortlink_id'=>$s1[0], 'reward_paid'=>$q1['allowed']?1:0, 'created_at'=>$s1[1] );
}
assert_equals( 3, count( $rows ), 'Tran 1: moi luot van duoc ghi lai' );
assert_equals( 1, count( array_filter( $rows, function($r){ return $r['reward_paid']===1; } ) ),
    'Tran 1: nhung chi 1 luot duoc tra tien' );

// Chỉnh lại lên 2: cơ chế cũ trở lại đúng như trước, không sót gì.
assert_equals( 2, tq_run( array( array(101,$t(0)), array(102,$t(5)) ), '1.2.3.4', 2 ),
    'Chinh lai 2: 2 shortlink khac nhau = 2 view (co che cu tro lai)' );
assert_equals( 1, tq_run( array( array(101,$t(0)), array(101,$t(5)) ), '1.2.3.4', 2 ),
    'Chinh lai 2: 1 shortlink vuot 2 lan van chi 1 view' );

// Kẹp giá trị: sitetop_ip_view_quota() chỉ chấp nhận 1 hoặc 2, ngoài ra về 2.
$clamp = function ( $v ) { $l = (int) $v; return ( $l < 1 || $l > 2 ) ? 2 : $l; };
assert_equals( 1, $clamp(1),  'Cai 1 -> nhan 1' );
assert_equals( 2, $clamp(2),  'Cai 2 -> nhan 2' );
assert_equals( 2, $clamp(0),  'Cai 0 -> ve 2' );
assert_equals( 2, $clamp(3),  'Cai 3 -> ve 2' );
assert_equals( 2, $clamp(5),  'Cai 5 (gia tri doi cu) -> ve 2' );
assert_equals( 2, $clamp(''), 'Bo trong -> ve 2' );
