<?php
/* ẢNH BƯỚC 2 — quy tắc phẳng, 13/09/2026.

   Có nhập "Link khi bấm ảnh"  -> ảnh bấm được, chuyển sang đúng link đó.
   KHÔNG nhập link             -> VẪN hiện ảnh chỉ dẫn, nhưng bấm vào không đi đâu cả.

   Bản cũ rơi về internalLinks[0] khi thiếu link đích. Link đầu tiên dò được thường
   CHÍNH LÀ trang đang đứng, nên bấm đại vào ảnh chỉ tải lại trang — trông y như bấm F5,
   mà vẫn được tính là đã làm bước 2. Chống bấm bừa thành vô nghĩa.

   BẪY: comment trong widget.js.php chứa nguyên văn `internalLinks[0]`, `<a>`, `F5`.
   Mọi khẳng định chạy trên bản ĐÃ LỘT COMMENT. */

$__goc = dirname( __DIR__, 2 );
$__wjs_tho = file_get_contents( $__goc . '/widget.js.php' );
$__w = preg_replace( '#/\*.*?\*/#s', '', $__wjs_tho );
$__w = preg_replace( '#//[^\n]*#', '', $__w );

// Tự kiểm: bản lột phải thật sự sạch, không thì mọi khẳng định dưới đây vô nghĩa.
assert_true( strpos( $__wjs_tho, 'internalLinks[0]' ) !== false,
    'Ban THO phai con internalLinks[0] trong comment (neu khong, phep tu kiem het y nghia)' );
assert_true( strpos( $__w, 'internalLinks[0]' ) === false,
    'SONG CON: KHONG duoc con bat ky cho nao roi ve internalLinks[0] — bam bua vao anh la qua duoc' );

// --- Link đích là nguồn DUY NHẤT làm ảnh bấm được ---
assert_true( strpos( $__w, 'if(s2&&s2.image_url&&s2.target_url){' ) !== false,
    'Chi khi CO s2.target_url moi duoc tinh chuyen s2Href' );
assert_true( strpos( $__w, 'new URL(s2.target_url,location.origin).hostname===location.hostname' ) !== false,
    'Link dich khac ten mien PHAI bi bo qua — the <a> tro ra ngoai la user khong bao gio nhan duoc ma' );

// --- Ảnh LUÔN hiện khi có cấu hình, kể cả không có link ---
assert_true( strpos( $__w, 'if(s2&&s2.image_url){' ) !== false,
    'Co anh la PHAI hien anh chi dan, khong phu thuoc co link hay khong' );

// --- Hai nhánh dựng HTML ---
$__p_if = strpos( $__w, 'if(s2Href){' );
assert_true( $__p_if !== false, 'Phai co nhanh re theo s2Href' );
$__p_else = strpos( $__w, '}else{', $__p_if );
assert_true( $__p_else !== false, 'Phai co nhanh else (anh khong bam duoc)' );
$__p_het = strpos( $__w, "\n        }", $__p_else + 1 );
assert_true( $__p_het !== false, 'Phai tim duoc cuoi nhanh else' );

$__co_bam    = substr( $__w, $__p_if, $__p_else - $__p_if );
$__khong_bam = substr( $__w, $__p_else, $__p_het - $__p_else );

// Nhánh CÓ link: phải là thẻ <a> trỏ đúng s2Href
assert_true( strpos( $__co_bam, "<a href=" ) !== false && strpos( $__co_bam, "+s2Href." ) !== false,
    'Nhanh co link PHAI boc anh bang the <a href> tro dung s2Href' );

// Nhánh KHÔNG link: tuyệt đối không được có href, không nhịp đập
assert_true( strpos( $__khong_bam, 'href' ) === false,
    'SONG CON: khong co link dich thi anh TUYET DOI khong duoc la the <a href>' );
assert_true( strpos( $__khong_bam, 'tnBtnPulse' ) === false,
    'Anh khong bam duoc KHONG duoc co nhip dap — nhip dap la tin hieu "bam vao day"' );
assert_true( strpos( $__khong_bam, 'tn-s2img-xem' ) !== false,
    'Nhanh khong bam duoc phai dung id rieng tn-s2img-xem' );
assert_true( strpos( $__khong_bam, 's2.image_url' ) !== false || strpos( $__khong_bam, '_s2img' ) !== false,
    'Nhanh khong bam duoc VAN phai hien anh chi dan' );

// --- Cờ step2_bat_tu_tim đã gỡ sạch khỏi mọi đường code ---
foreach ( array(
    'widget.js.php'                          => 's2TuTim',
    'includes/shortlink-ajax.php'            => 'bat_tu_tim',
    'includes/shortlink-functions.php'       => 'step2_bat_tu_tim',
    'includes/admin-dashboard.php'           => 'step2_bat_tu_tim',
    'includes/admin/tabs/tab-campaigns.php'  => 'step2_bat_tu_tim',
) as $__f => $__tu ) {
    $__src = file_get_contents( $__goc . '/' . $__f );
    $__src = preg_replace( '#/\*.*?\*/#s', '', $__src );
    $__src = preg_replace( '#//[^\n]*#', '', $__src );
    assert_true( strpos( $__src, $__tu ) === false,
        'Da bo o tick: ' . $__f . ' khong duoc con ' . $__tu );
}

// --- Chú thích cho chủ nguồn phải nói đúng quy tắc mới ---
$__tab = file_get_contents( $__goc . '/includes/admin/tabs/tab-campaigns.php' );
assert_true( substr_count( $__tab, 'bấm vào không đi đâu' ) === 2,
    'Ca 2 form PHAI giai thich ro: bo trong link thi bam vao anh khong di dau' );
assert_true( strpos( $__tab, 'placeholder="Để trống = dùng link nội bộ đầu tiên"' ) === false,
    'Placeholder cu (dung link noi bo dau tien) PHAI go — no mo ta hanh vi khong con nua' );

/* --- Ảnh hỏng ở nhánh KHÔNG bấm được ---
   Bản vá 13/09/2026. Đoạn onerror có sẵn chỉ bắt '#tn-s2img' (nhánh CÓ link). Nhánh
   không bấm được dùng id khác nên rơi ngoài: ảnh bị adblock chặn là user không còn manh
   mối nào — không link để bấm, cũng không thấy phải tìm mục nào. Nhiệm vụ tắc hẳn. */
assert_true( strpos( $__w, "guide.querySelector('#tn-s2img-xem')" ) !== false,
    'PHAI co onerror rieng cho nhanh khong bam duoc — anh hong la user mat het manh moi' );
$__p_x = strpos( $__w, "guide.querySelector('#tn-s2img-xem')" );
$__khoi_x = substr( $__w, $__p_x, 700 );
assert_true( strpos( $__khoi_x, 'onerror' ) !== false,
    'Khoi #tn-s2img-xem PHAI gan onerror cho anh' );
assert_true( strpos( $__khoi_x, '👆 Click vào đây' ) === false,
    'Nhanh khong bam duoc KHONG duoc hien "Click vao day" — khong co link nao de bam' );
