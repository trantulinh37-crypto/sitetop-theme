<?php
/* THANH LIÊN HỆ CUỐI TRANG (25/10/2026): thay nút nổi góc phải bằng dải ngang theo mẫu chủ site
   ("Liên hệ Tele: @… để mua traffic user"). Canh: nút nổi cũ không quay lại, dải không position:fixed,
   dashboard có sidebar cố định phải chừa lề trái, ô Facebook lưu được. */
$__lh = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/floating-contact.php' );
assert_true( strpos( $__lh, 'lnContactFab' ) === false && strpos( $__lh, 'ln-contact-fab' ) === false, 'Nut lien he noi cu (bam moi bung) da bo' );
assert_true( strpos( $__lh, 'Liên hệ Tele: <a' ) !== false && strpos( $__lh, 'để mua traffic user</p>' ) !== false, 'Cau "Lien he Tele: @... de mua traffic user" lay tu contact_telegram' );
assert_true( ! preg_match( '/\.st-lienhe\{[^}]*position:fixed/', $__lh ), 'Dai lien he nam trong dong chay trang, khong position:fixed' );
assert_true( strpos( $__lh, "array( 'page-user-dashboard.php', 'page-customer-dashboard.php' ), true ) ) return;" ) !== false, 'Dashboard user va khach hang KHONG hien thanh lien he (chi trang cong khai)' );
$__lh_cd = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/admin/tabs/tab-settings.php' );
assert_true( strpos( $__lh_cd, "'contact_facebook'," ) !== false && strpos( $__lh_cd, 'name="contact_facebook"' ) !== false, 'Tab Cai dat luu duoc o Lien he Facebook' );
// 25/10 chiều: chung MỘT dòng với bản quyền, chữ liên hệ = cỡ bản quyền + 5px; trang chủ in tại chỗ .ln-copyright cũ.
assert_true( strpos( $__lh, '<p class="st-lienhe-copy">Copyright &copy;' ) !== false, 'Dong ban quyen nam trong thanh lien he' );
assert_true( strpos( $__lh, '.st-lienhe-copy{margin:0;font-size:13px' ) !== false && strpos( $__lh, "font-size:18px;font-weight:700" ) !== false, 'Chu lien he 18px = ban quyen 13px + 5px' );
assert_true( strpos( $__lh, 'static $da_in = false;' ) !== false, 'Co chong in hai lan (trang chu goi tai cho + wp_footer)' );
$__lh_i = (string) file_get_contents( dirname( __DIR__, 2 ) . '/index.php' );
assert_true( strpos( $__lh_i, "sitetop_thanh_lien_he();" ) !== false, 'Trang chu in thanh lien he dung cho dong ban quyen' );
// 25/10 tối: màn rộng bản quyền nằm GIỮA thanh, cụm icon + câu liên hệ dạt sang phải.
assert_true( strpos( $__lh, 'grid-template-columns:1fr auto 1fr' ) !== false && strpos( $__lh, '.st-lienhe-copy{grid-column:2}' ) !== false && strpos( $__lh, '<div class="st-lienhe-lh">' ) !== false, 'Man rong: ban quyen o cot giua, cum lien he o cot phai' );
