<?php
/* Ảnh bước 2: ô "Bắt user tự tìm đúng mục" — 13/09/2026.

   Trước đây bỏ trống "Link khi bấm ảnh" thì widget vẫn biến ảnh thành thẻ <a> trỏ tới
   LINK NỘI BỘ ĐẦU TIÊN dò được — thường KHÔNG phải mục trong ảnh. Câu hướng dẫn bảo
   "bấm vào link giống ảnh" mà bấm đại vào ảnh vẫn qua, nên chống bấm bừa vô nghĩa.

   Nay thêm cờ step2_bat_tu_tim. MẶC ĐỊNH TẮT là điều kiện bắt buộc: chiến dịch cũ đang
   chạy không được đổi hành vi, không thì tỉ lệ hoàn thành tụt hàng loạt mà không ai
   biết vì sao.

   BẪY: comment trong widget.js.php có chứa nguyên văn `internalLinks[0]`, `s2TuTim`,
   `<a>`. Mọi khẳng định trên widget chạy ở bản ĐÃ LỘT COMMENT. */

$__goc = dirname( __DIR__, 2 );
$__db  = file_get_contents( $__goc . '/includes/database-setup.php' );
$__fnc = file_get_contents( $__goc . '/includes/shortlink-functions.php' );
$__tab = file_get_contents( $__goc . '/includes/admin/tabs/tab-campaigns.php' );
$__adm = file_get_contents( $__goc . '/includes/admin-dashboard.php' );
$__ajx = file_get_contents( $__goc . '/includes/shortlink-ajax.php' );
$__wjs_tho = file_get_contents( $__goc . '/widget.js.php' );

// --- 1. Cột phải có, và MẶC ĐỊNH 0 ---
assert_true( strpos( $__db, 'step2_bat_tu_tim tinyint(1) NOT NULL DEFAULT 0' ) !== false,
    'Cot step2_bat_tu_tim PHAI mac dinh 0 — chien dich cu khong duoc doi hanh vi' );

// --- 2. Cho phép cập nhật, đúng kiểu số ---
assert_true( strpos( $__fnc, "'step2_bat_tu_tim'=>'%d'" ) !== false,
    'Phai co step2_bat_tu_tim trong bang do dinh dang, kieu %d' );

// --- 3. Form tạo: đọc từ POST, và không tick = 0 ---
assert_true( strpos( $__tab, "\$_POST['step2_bat_tu_tim']" ) !== false,
    'Form tao PHAI doc step2_bat_tu_tim tu POST' );
assert_true( preg_match( '#\$step2_bat_tu_tim\s*=.*\?\s*1\s*:\s*0;#', $__tab ) === 1,
    'Khong tick PHAI ra 0 (mac dinh tat)' );
assert_true( strpos( $__tab, "'step2_bat_tu_tim' => \$step2_bat_tu_tim," ) !== false,
    'Form tao PHAI ghi step2_bat_tu_tim vao DB' );

// --- 4. Ô tick có mặt ở CẢ hai form (tạo + sửa) ---
assert_true( substr_count( $__tab, 'name="step2_bat_tu_tim"' ) === 2,
    'O tick PHAI co o ca form tao lan modal sua, dem duoc: ' . substr_count( $__tab, 'name="step2_bat_tu_tim"' ) );
assert_true( strpos( $__tab, "getElementById('admEditStep2TuTim').checked = " ) !== false,
    'Modal sua PHAI nap lai trang thai o tick' );
assert_true( strpos( $__tab, "fd.append('step2_bat_tu_tim'," ) !== false,
    'Modal sua PHAI gui step2_bat_tu_tim khi luu' );

// --- 5. Admin trả cột về cho modal (thiếu là lan luu sau ghi de 0) ---
assert_true( strpos( $__adm, "'step2_bat_tu_tim'=>(int)(\$c->step2_bat_tu_tim ?? 0)" ) !== false,
    'admin-dashboard PHAI tra step2_bat_tu_tim ve modal' );

// --- 6. Truy vấn widget lấy cột, và gửi sang widget ---
assert_true( substr_count( $__ajx, 'c.step2_bat_tu_tim' ) >= 2,
    'It nhat 2 cau SELECT cua luong widget PHAI lay c.step2_bat_tu_tim' );
assert_true( strpos( $__ajx, "'bat_tu_tim' => ! empty( \$visit->step2_bat_tu_tim )" ) !== false,
    'Phai gui bat_tu_tim sang widget' );

// --- 7. Widget (đã lột comment) ---
$__w = preg_replace( '#/\*.*?\*/#s', '', $__wjs_tho );
$__w = preg_replace( '#//[^\n]*#', '', $__w );

assert_true( strpos( $__wjs_tho, 'internalLinks[0]' ) !== false,
    'Ban THO phai con internalLinks[0] trong comment (neu khong, phep tu kiem het y nghia)' );

// Rơi về link nội bộ đầu tiên PHẢI bị chặn bởi !s2TuTim
assert_true( strpos( $__w, 'if(!s2Href&&!s2TuTim&&internalLinks.length>0)s2Href=internalLinks[0].url;' ) !== false,
    'SONG CON: roi ve internalLinks[0] PHAI bi chan boi !s2TuTim' );

// Nhánh hiện ảnh phải nhận cả trường hợp không có href
assert_true( strpos( $__w, 'if(s2&&s2.image_url&&(s2Href||s2TuTim)){' ) !== false,
    'Nhanh hien anh PHAI nhan ca truong hop khong co href (s2TuTim)' );

// Không có href → ảnh TUYỆT ĐỐI không được bọc trong thẻ <a>
$__p_if   = strpos( $__w, 'if(s2Href){' );
assert_true( $__p_if !== false, 'Phai co nhanh re theo s2Href' );
$__p_else = strpos( $__w, '}else{', $__p_if );
assert_true( $__p_else !== false, 'Phai co nhanh else (anh khong bam duoc)' );
$__p_het  = strpos( $__w, "\n        }", $__p_else + 1 );
assert_true( $__p_het !== false, 'Phai tim duoc cuoi nhanh else' );
$__khong_bam = substr( $__w, $__p_else, $__p_het - $__p_else );

assert_true( strpos( $__khong_bam, 'href' ) === false,
    'SONG CON: khong co link dich thi anh TUYET DOI khong duoc la the <a href> — bam bua la qua duoc' );
assert_true( strpos( $__khong_bam, 'tn-s2img-xem' ) !== false,
    'Nhanh khong bam duoc phai dung id rieng tn-s2img-xem' );
assert_true( strpos( $__khong_bam, 'tnBtnPulse' ) === false,
    'Anh khong bam duoc KHONG duoc co nhip dap — nhip dap la tin hieu "bam vao day"' );
