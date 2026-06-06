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

## Session 2026-06-02T02:04:37Z (tiếp) — Gỡ hẳn hộp che `.url-mask`
**Spec source:** User feedback — "vẫn chưa bỏ hẳn" kèm screenshot cho thấy hộp trắng vẫn che một phần ảnh

### Decisions
- Lần trước chỉ bỏ dấu `*` nhưng hộp overlay trắng `.url-mask` (CSS `page-unlock.php:327`,
  `background:#fff` đè lên screenshot) vẫn còn → tạo khoảng trắng che tiêu đề/URL gốc trong ảnh.
- Gỡ hẳn `<div class="url-mask">...</div>` ở cả 2 block screenshot (nocode `:523` và thường `:700`),
  giữ lại `.mobile-badge`. Screenshot giờ hiện tự nhiên, domain đầy đủ trong ảnh thật.
- Giữ nguyên CSS `.url-mask` (không còn được dùng) và biến `$target_domain_masked`
  (vẫn dùng cho 2 dòng fallback text "Tìm kết quả từ ...") → tránh sửa lan rộng.

### Reviewer notes
- Sau khi gỡ overlay, dòng URL hiển thị là URL THẬT trong ảnh screenshot của campaign
  (không còn che). Đây là ý user muốn.

## Session 2026-06-02T02:04:37Z (tiếp) — Fix cột "Đã nạp" hiện 0 sai ở customer dashboard
**Spec source:** User report — "Đã nạp" = 0đ nhưng "Đã chi" = 1.475.200đ, "Số dư" = 24.800đ (mâu thuẫn)

### Decisions
- Root cause: `page-customer-dashboard.php:22` tính `$total_deposited` từ
  `customer_transactions WHERE type='deposit'`, nhưng SỐ DƯ (`traffictop_get_customer_balance_amount`,
  `shortlink-verification.php:549`) lại tính deposit từ `customer_deposits WHERE status='approved'`
  (gồm `amount + bonus_amount`). Hai nguồn khác nhau → nếu deposit approved nhưng không có
  transaction type='deposit' tương ứng (deposit cũ / admin tạo trực tiếp) → cột "Đã nạp" = 0.
- Fix: đổi `$total_deposited` sang CÙNG nguồn với balance: `customer_deposits` approved,
  `SUM(amount + bonus_amount)`, lọc `amount > 0` (giữ admin-adjustment âm trong bucket
  `$total_spent_admin` đã có sẵn ở dòng 26-27). Khớp đúng biểu thức hàm balance dùng.

### Deviations from spec
- Không có.

### Reviewer notes
- "Đã nạp" giờ GỒM bonus tiền nạp (đúng theo định nghĩa `total_deposited` mà
  `traffictop_sync_customer_balance:570` đang lưu). Nhờ vậy hiển thị nhất quán:
  Số dư ≈ Đã nạp − Đã chi.
- KHÔNG đổi `customer_id` → đúng cột của bảng `customer_deposits` (bảng này dùng `customer_id`,
  khác `customer_balance` dùng `user_id`).
