# Living Implementation Notes

## Session 2026-06-02T02:04:37Z — Bỏ che domain trong ảnh mô tả page-unlock
**Spec source:** Yêu cầu user — domain đang hiện dạng `https://shivamo*****artcity.in`, muốn hiện đầy đủ
**Branch:** claude/page-unlock-domain-image-tjUU3

### Decisions
- Giữ nguyên biến `$target_domain_masked` (`page-unlock.php:146`) để không phải sửa 4 chỗ
  output (lines ~535, 544, 712, 721). Chỉ gán nó = `$target_domain_short` (domain đầy đủ
  đã bỏ `www.`), bỏ toàn bộ block tính start/***/end.

### Deviations from spec
- Không có.

### Reviewer notes
- Không đụng vào logic show/hide widget (theo CLAUDE.md). Đây thuần là thay đổi hiển thị
  domain trong screenshot mô tả.
- 4 chỗ render (`.mask-url` span + dòng "Tìm kết quả từ ...") giờ hiện domain đầy đủ.

## Summary
**Files changed:**
- `page-unlock.php` — bỏ logic che domain, hiện domain đầy đủ trong ảnh mô tả

**Top items for reviewer to scrutinize:**
1. Xác nhận muốn hiện full domain ở CẢ 4 chỗ (screenshot URL mask + dòng hướng dẫn).

**Open questions:**
- Không có.

**Test coverage:**
- Thay đổi hiển thị thuần, không có unit test liên quan.
