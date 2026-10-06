<?php
/* Dashboard user chỉ được có MỘT khối <head> (06/10/2026). Bản .one từ 28/09 (7975648) dính hai khối:
   sau <style> thứ nhất lại có nguyên đoạn $nonce… <!DOCTYPE html> … <head> … wp_head() … <style> — trình
   duyệt nhận 2 head, wp_head() chạy 2 lần, script/CSS nạp đôi, chữ PHP in thẳng vào CSS. Canh cả hai site. */
$__mh = (string) file_get_contents( dirname( __DIR__, 2 ) . '/page-user-dashboard.php' );
assert_equals( 1, substr_count( $__mh, '<!DOCTYPE html>' ), 'Dashboard: dung MOT <!DOCTYPE html>' );
assert_equals( 1, substr_count( $__mh, '<?php wp_head(); ?>' ), 'Dashboard: wp_head() dung MOT lan' );
assert_equals( 1, substr_count( $__mh, "\n<head>\n" ), 'Dashboard: dung MOT <head>' );
assert_equals( 1, substr_count( $__mh, "\n<style>\n:root{" ), 'Dashboard: dung MOT khoi <style> chinh' );
assert_equals( 1, substr_count( $__mh, "\$nonce  = wp_create_nonce( 'sitetop_nonce' );\n\$home   = home_url();\n?>" ), 'Dashboard: doan $nonce/$home mo HTML dung MOT lan' );
echo "  ✓ dashboard mot head\n";
