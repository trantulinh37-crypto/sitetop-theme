<?php
/* THANH LIÊN HỆ CUỐI TRANG (25/10/2026): thay nút nổi góc phải bằng dải ngang theo mẫu chủ site
   ("Liên hệ Tele: @… để mua traffic user"). Canh: nút nổi cũ không quay lại, dải không position:fixed,
   dashboard có sidebar cố định phải chừa lề trái, ô Facebook lưu được. */
$__lh = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/floating-contact.php' );
assert_true( strpos( $__lh, 'lnContactFab' ) === false && strpos( $__lh, 'ln-contact-fab' ) === false, 'Nut lien he noi cu (bam moi bung) da bo' );
assert_true( strpos( $__lh, 'Liên hệ Tele: <a' ) !== false && strpos( $__lh, 'để mua traffic user</p>' ) !== false, 'Cau "Lien he Tele: @... de mua traffic user" lay tu contact_telegram' );
assert_true( ! preg_match( '/\.st-lienhe\{[^}]*position:fixed/', $__lh ), 'Dai lien he nam trong dong chay trang, khong position:fixed' );
assert_true( strpos( $__lh, 'body:has(.sidebar) .st-lienhe{margin-left:var(--sidebar-w' ) !== false, 'Dashboard co sidebar co dinh → dai chua le trai, sidebar khong de icon' );
$__lh_cd = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/admin/tabs/tab-settings.php' );
assert_true( strpos( $__lh_cd, "'contact_facebook'," ) !== false && strpos( $__lh_cd, 'name="contact_facebook"' ) !== false, 'Tab Cai dat luu duoc o Lien he Facebook' );
