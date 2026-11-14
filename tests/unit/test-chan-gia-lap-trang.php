<?php
/* CHỐNG GIẢ LẬP TRONG TRANG (14/11/2026): tool "SiteTop Bypass" (userscript Stay/Safari) chạy trong
   trang đích — ghi đè document.referrer, gọi thẳng hàm bấm nút widget, gắn bảng điều khiển vào trang.
   Chạy THẬT các hàm trích từ widget.js.php bằng node, và hàm mức xử lý của máy chủ bằng PHP riêng. */
$__gl_goc = dirname( __DIR__, 2 );
$__gl_w   = (string) file_get_contents( $__gl_goc . '/widget.js.php' );
$__gl_a = strpos( $__gl_w, 'function _tnRefGhiDe(){' ); $__gl_b = strpos( $__gl_w, 'var _tnThatLuc=-1e9', (int) $__gl_a );
assert_true( $__gl_a !== false && $__gl_b !== false, 'Widget co bo do gia lap trang' );
$__gl_do = substr( $__gl_w, $__gl_a, $__gl_b - $__gl_a );
// Getter "gốc" giả: dùng một getter native có sẵn của node để toString ra [native code].
$__gl_js = <<<'JS'
var NATIVE = Object.getOwnPropertyDescriptor(Object.prototype, '__proto__').get;
function Document(){} 
var TEXT = 'trang khach binh thuong', SHADOW = '';
Object.defineProperty(Document.prototype, 'referrer', { get: NATIVE, configurable: true });
function HTMLDocument(){} HTMLDocument.prototype = Object.create(Document.prototype);
global.Document = Document;
global.document = Object.create(HTMLDocument.prototype);
document.documentElement = { get textContent(){ return TEXT; } };
document.querySelectorAll = function(){ return SHADOW ? [{ shadowRoot: { textContent: SHADOW } }] : [{}]; };
JS;
$__gl_kb = <<<'JS'
var KQ = {};
KQ.sach = _tnGiaLap();
Object.defineProperty(document, 'referrer', { get: function(){ return 'https://www.google.com/'; }, configurable: true });
KQ.ghide_doc = _tnGiaLap(); delete document.referrer;
Object.defineProperty(HTMLDocument.prototype, 'referrer', { get: function(){ return 'x'; }, configurable: true });
KQ.ghide_html = _tnGiaLap(); delete HTMLDocument.prototype.referrer;
var goc = Object.getOwnPropertyDescriptor(Document.prototype, 'referrer');
Object.defineProperty(Document.prototype, 'referrer', { get: function(){ return 'x'; }, configurable: true });
KQ.ghide_proto = _tnGiaLap(); Object.defineProperty(Document.prototype, 'referrer', goc);
TEXT = 'abc SiteTop  Bypass abc'; KQ.bang = _tnGiaLap(); TEXT = 'binh thuong';
SHADOW = 'Store • robuxvien.com'; KQ.shadow = _tnGiaLap(); SHADOW = '';
Object.defineProperty(document, 'referrer', { get: function(){ return 'g'; }, configurable: true }); TEXT = 'SiteTop Bypass';
KQ.ca_hai = _tnGiaLap();
console.log(JSON.stringify(KQ));
JS;
$__gl_f = sys_get_temp_dir() . '/st-gl-' . getmypid() . '.js';
file_put_contents( $__gl_f, $__gl_js . "\n" . $__gl_do . "\n" . $__gl_kb );
$__gl_r = (string) shell_exec( 'node ' . escapeshellarg( $__gl_f ) . ' 2>&1' ); @unlink( $__gl_f );
$K = json_decode( trim( $__gl_r ), true );
assert_true( is_array( $K ), 'Chay duoc bo do bang node. Ra: ' . substr( $__gl_r, 0, 300 ) );
if ( is_array( $K ) ) {
    assert_equals( 0, $K['sach'], 'Trang sach → gt=0' );
    assert_equals( 1, $K['ghide_doc'], 'Ghi de document.referrer → bit 1' );
    assert_equals( 1, $K['ghide_html'], 'Ghi de tren HTMLDocument.prototype → bit 1' );
    assert_equals( 1, $K['ghide_proto'], 'Thay getter Document.prototype bang ham JS → bit 1' );
    assert_equals( 2, $K['bang'], 'Bang "SiteTop Bypass" trong trang → bit 2' );
    assert_equals( 2, $K['shadow'], 'Bang cong cu nam trong shadow DOM → bit 2' );
    assert_equals( 3, $K['ca_hai'], 'Ca hai dau hieu → gt=3' );
}

// ---- Nút widget chỉ chạy bằng cú bấm thật ----
preg_match( '/window\.(_st[a-z]*WidgetClick)=function\(\)\{\n(.*?)\n    state\._choBamThat=false;/s', $__gl_w, $__gl_m );
assert_true( ! empty( $__gl_m[1] ), 'Ham bam nut widget co chot cu bam that' );
if ( ! empty( $__gl_m[1] ) ) {
    $__gl_ten = $__gl_m[1];
    $__gl_js2 = 'var T=100000,_tnBamLuc=-1e9,CHAY=0; function _mocGio(){return T;} var state={}; global.window=global;'
        . "\nwindow.$__gl_ten=function(){\n" . $__gl_m[2] . "\n    state._choBamThat=false; CHAY++; };\n"
        . 'var KQ={}; window.' . $__gl_ten . '(); KQ.script=CHAY;'
        . '_tnBamLuc=T-300; window.' . $__gl_ten . '(); KQ.bam_that=CHAY;'
        . 'T+=5000; window.' . $__gl_ten . '(); KQ.bam_cu=CHAY;'
        . 'state._choBamThat=true; window.' . $__gl_ten . '(); KQ.cho=CHAY; window.' . $__gl_ten . '(); KQ.cho_het=CHAY;'
        . 'console.log(JSON.stringify(KQ));';
    $__gl_f = sys_get_temp_dir() . '/st-gl2-' . getmypid() . '.js';
    file_put_contents( $__gl_f, $__gl_js2 );
    $__gl_r = (string) shell_exec( 'node ' . escapeshellarg( $__gl_f ) . ' 2>&1' ); @unlink( $__gl_f );
    $B = json_decode( trim( $__gl_r ), true );
    assert_true( is_array( $B ), 'Chay duoc chot bam nut. Ra: ' . substr( $__gl_r, 0, 300 ) );
    if ( is_array( $B ) ) {
        assert_equals( 0, $B['script'], 'Script goi thang ham bam nut → khong chay' );
        assert_equals( 1, $B['bam_that'], 'Vua co cu bam that → chay' );
        assert_equals( 1, $B['bam_cu'], 'Cu bam that da qua 1,5 giay → khong chay' );
        assert_true( $B['cho'] === 2 && $B['cho_het'] === 2, 'Bam som luc phien chua san (wantStart) → chay dung MOT lan' );
    }
}
assert_true( strpos( $__gl_w, "if(state.wantStart){state.wantStart=false;state._choBamThat=true;" ) !== false, 'Duong wantStart giu cu bam that' );
assert_true( strpos( $__gl_w, "action=sitetop_widget_verify_access&gt='+_tnGiaLap()" ) !== false && substr_count( $__gl_w, "ajax('sitetop_get_code',{session_id:" ) === substr_count( $__gl_w, 'gt:_tnGiaLap(),kf:' ), 'Ca cong xac minh lan moi lenh xin ma deu gui gt' );

// ---- Máy chủ ----
$__gl_s = (string) file_get_contents( $__gl_goc . '/includes/shortlink-ajax.php' );
$__gl_a = strpos( $__gl_s, "if ( ! function_exists( 'sitetop_gialap_muc' ) ) {" ); $__gl_b = strpos( $__gl_s, "if ( ! function_exists( 'sitetop_canh_bao_gialap' ) ) {", (int) $__gl_a );
$__gl_ham = substr( $__gl_s, $__gl_a, $__gl_b - $__gl_a );
foreach ( array( array( '', null, 0 ), array( '0', null, 0 ), array( '1', null, 2 ), array( '2', null, 2 ), array( '3', 1, 1 ), array( '2', 0, 0 ) ) as $c ) {
    $f = sys_get_temp_dir() . '/st-gl3-' . getmypid() . '-' . mt_rand() . '.php';
    file_put_contents( $f, '<?php $_POST=array(' . ( $c[0] === '' ? '' : "'gt'=>'" . $c[0] . "'" ) . '); function sitetop_get_option($k,$d){ return ' . var_export( $c[1], true ) . ' === null ? $d : ' . var_export( $c[1], true ) . '; } ' . $__gl_ham . ' echo sitetop_gialap_muc();' );
    $r = trim( (string) shell_exec( 'php ' . escapeshellarg( $f ) . ' 2>&1' ) ); @unlink( $f );
    assert_equals( (string) $c[2], $r, 'gialap_muc gt=' . $c[0] . ' option=' . var_export( $c[1], true ) );
}
assert_true( strpos( $__gl_s, "get_transient( 'sitetop_gialap_' . \$sid )" ) !== false, 'Xin ma: phien da danh dau gia lap thi khong cap ma' );
assert_true( strpos( $__gl_s, "if ( \$_muc_gl >= 2 ) { wp_send_json_error('Forbidden'); return; }" ) !== false, 'Xac minh: muc 2 tu choi' );
assert_true( strpos( $__gl_s, "'sitetop_gialap_'," ) !== false, 'Dau gia lap duoc don khi doi phien' );
$__gl_v = (string) file_get_contents( $__gl_goc . '/includes/shortlink-verification.php' );
assert_true( strpos( $__gl_v, "\$skip_reasons[] = 'gia_lap_trang';" ) !== false, 'Tra thuong: phien gia lap bi cat thuong' );
