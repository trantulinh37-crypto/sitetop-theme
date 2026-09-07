<?php
/* ĐỔI NHIỆM VỤ KHÔNG ĐƯỢC KẾ THỪA GÌ CỦA NHIỆM VỤ CŨ.
   Lỗi gốc: sitetop_ajax_change_keyword chỉ đổi campaign_id ngay trên hàng cũ và không trả
   session mới -> client rơi vào window.location.reload() -> nạp lại đúng phiên cũ, nên
   nhiệm vụ B thừa hưởng đồng hồ (sitetop_timer_), cờ Cloudflare (sitetop_captcha_ok_) và
   dấu bàn giao (sitetop_handoff_) của A.
   Bộ test này canh để không ai vô tình làm hỏng lại. */

$__goc = dirname(__DIR__, 2);
$__ajax = file_get_contents( $__goc . '/includes/shortlink-ajax.php' );

/* Trích nguyên văn hàm bằng tokenizer (đếm cả T_CURLY_OPEN của "{$p}" trong chuỗi). */
$__trich = function ( $src, $ten ) {
    $tk = token_get_all( $src ); $n = count( $tk );
    for ( $i = 0; $i < $n; $i++ ) {
        if ( ! is_array( $tk[$i] ) || $tk[$i][0] !== T_FUNCTION ) continue;
        $j = $i + 1;
        while ( $j < $n && is_array( $tk[$j] ) && in_array( $tk[$j][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) $j++;
        if ( $j >= $n || ! is_array( $tk[$j] ) || $tk[$j][1] !== $ten ) continue;
        $out=''; $d=0; $mo=false;
        for ( $k = $i; $k < $n; $k++ ) {
            $t=$tk[$k]; $out .= is_array($t)?$t[1]:$t;
            $om = ($t==='{') || (is_array($t) && in_array($t[0], array(T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES), true));
            if($om){$d++;$mo=true;} elseif($t==='}'){$d--; if($mo&&$d===0)break;}
        }
        return $out;
    }
    return null;
};

$fn = $__trich( $__ajax, 'sitetop_ajax_change_keyword' );
assert_true( $fn !== null, 'Trich duoc ham change_keyword' );

if ( $fn !== null ) {
    // 1) Phải cấp session MỚI và trả về cho client
    assert_true( strpos( $fn, 'sitetop_generate_session_id()' ) !== false, 'Doi nhiem vu -> sinh session_id MOI' );
    assert_true( strpos( $fn, "'new_session_id'" ) !== false,              'Tra new_session_id cho client (khong thi client reload phien cu)' );
    assert_true( strpos( $fn, '$wpdb->insert' ) !== false,                 'Tao HANG visit moi cho nhiem vu B' );

    // 2) KHÔNG được đổi campaign ngay trên hàng cũ nữa (đó chính là cách sinh ra lỗi)
    assert_false( preg_match( "/'campaign_id'\s*=>\s*\\\$campaign->id,\s*\n\s*'order_id'/", $fn ) === 1,
        'Khong con UPDATE campaign_id de len chinh hang cu' );

    // 3) Đồng hồ B tính từ thời điểm đổi
    assert_true( strpos( $fn, "'created_at'" ) !== false, 'Hang moi co created_at rieng -> dong ho B tinh lai tu dau' );

    /* 4) Chốt quan trọng nhất: mọi transient gắn theo session trong TOÀN BỘ mã nguồn đều
          phải được xoá. Ai thêm transient mới mà quên xoá ở đây là test do ngay. */
    $files = array_merge(
        glob( $__goc . '/includes/*.php' ) ?: array(),
        array( $__goc . '/page-unlock.php', $__goc . '/widget.js.php' )
    );
    $khoa = array();
    foreach ( $files as $f ) {
        if ( ! is_file( $f ) ) continue;
        if ( preg_match_all( "/'(sitetop_[a-z0-9_]+_)'\s*\.\s*\\\$(?:sid|session_id)\b/", (string) file_get_contents( $f ), $m ) ) {
            foreach ( $m[1] as $k ) $khoa[ $k ] = true;
        }
    }
    $khoa = array_keys( $khoa );
    assert_true( count( $khoa ) >= 10, 'Quet duoc danh sach transient theo session (' . count( $khoa ) . ' khoa)' );
    $thieu = array();
    foreach ( $khoa as $k ) { if ( strpos( $fn, $k ) === false ) $thieu[] = $k; }
    assert_true( empty( $thieu ), 'Doi nhiem vu xoa DU moi transient cua phien cu; con sot: ' . implode( ', ', $thieu ) );
}

echo "  ✓ doi-nhiem-vu (khong ke thua timer/captcha/ban giao)\n";
