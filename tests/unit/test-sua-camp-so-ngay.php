<?php
/* Ô "Số ngày" trong khung sửa chiến dịch (14/11/2026): Số ngày = ceil(Tổng lượt ÷ Traffic/ngày);
   sửa Số ngày → Tổng lượt = Traffic/ngày × Số ngày; sửa Tổng lượt / Traffic/ngày → tính lại Số ngày,
   không tự đổi Tổng lượt. Chạy THẬT 3 hàm JS trích từ tab-campaigns.php bằng node. */
$__sn_s = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/admin/tabs/tab-campaigns.php' );
$__sn_a = strpos( $__sn_s, 'var _admEditDone = 0;' );
$__sn_b = strpos( $__sn_s, 'function admEditQtyTuSoNgay()', (int) $__sn_a );
$__sn_b = strpos( $__sn_s, "\n}\n", (int) $__sn_b ) + 3;
assert_true( $__sn_a !== false && $__sn_b > $__sn_a, 'Co bo ham So ngay' );
assert_true( strpos( $__sn_s, 'id="admEditDays"' ) !== false && strpos( $__sn_s, 'oninput="admEditSoNgayTuQty()"' ) !== false, 'Khung sua co o So ngay gan su kien' );
$__sn_js = "var E={admEditDaily:{value:''},admEditQty:{value:''},admEditDays:{value:''},admEditDaysHint:{textContent:''}};\n"
    . "global.document={getElementById:function(i){return E[i];}};\n"
    . substr( $__sn_s, $__sn_a, $__sn_b - $__sn_a ) . "\n"
    . "var K={};E.admEditDaily.value='50';E.admEditQty.value='1000';_admEditDone=200;admEditSoNgayTuQty();K.mo=E.admEditDays.value;K.goiy=E.admEditDaysHint.textContent;\n"
    . "E.admEditDays.value='30';admEditQtyTuSoNgay();K.qty30=E.admEditQty.value;\n"
    . "E.admEditQty.value='1010';admEditSoNgayTuQty();K.le=E.admEditDays.value;\n"
    . "E.admEditDaily.value='100';admEditSoNgayTuQty();K.doiDaily=[E.admEditDays.value,E.admEditQty.value];\n"
    . "console.log(JSON.stringify(K));";
$__sn_f = sys_get_temp_dir() . '/st-sn-' . getmypid() . '.js';
file_put_contents( $__sn_f, $__sn_js );
$__sn_r = (string) shell_exec( 'node ' . escapeshellarg( $__sn_f ) . ' 2>&1' ); @unlink( $__sn_f );
$K = json_decode( trim( $__sn_r ), true );
assert_true( is_array( $K ), 'Chay duoc ham So ngay. Ra: ' . substr( $__sn_r, 0, 300 ) );
if ( is_array( $K ) ) {
    assert_equals( '20', (string) $K['mo'], 'Mo khung: 1000 luot / 50 = 20 ngay' );
    assert_true( strpos( $K['goiy'], 'còn ≈ 16 ngày' ) !== false, 'Goi y con lai: (1000-200)/50 = 16 ngay — ' . $K['goiy'] );
    assert_equals( '1500', (string) $K['qty30'], 'Sua 30 ngay → 50 x 30 = 1500 luot' );
    assert_equals( '21', (string) $K['le'], '1010 luot / 50 → lam tron len 21 ngay' );
    assert_equals( array( '11', '1010' ), $K['doiDaily'], 'Doi traffic/ngay → tinh lai so ngay, KHONG doi tong luot' );
}
