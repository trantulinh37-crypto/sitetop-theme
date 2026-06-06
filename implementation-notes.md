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

## Session 2026-06-06T15:54:07Z — Security hardening: chống farming reward + siết tài chính
**Spec source:** Audit nội bộ (4 luồng) + user duyệt phạm vi Critical+HIGH+MEDIUM+LOW
**Branch:** claude/page-unlock-domain-image-tjUU3

### Decisions
- C3 (bypass đếm ngược qua widget_start_timer step2): KHÔNG lùi created_at về now-onsite
  vô điều kiện. Thay bằng credit = min(onsite, now - target_visited_at). target_visited_at
  do server ghi (track_direct_click/update_step) → farmer không backdate được → buộc chờ thật.
  Luồng 2-step hợp lệ (đã ở target đủ onsite) vẫn pass.
- H1 (shortlink-verification.php:60-62): is_nocode CHỈ từ traffic_type==='nocode', bỏ suy
  diễn từ fixed_code. Khớp với widget_verify_access:590 (vốn đã strict). Chống bỏ time check.
- C1/C4: thêm bind ip_address vào WHERE của track_google_click/track_direct_click (khớp
  update_step) → chống inject cờ lên session của IP khác.
- C2 (widget_verify_access Origin forge bằng curl): KHÔNG rewrite nonce-system bây giờ (rủi
  ro phá widget cross-site). Ghi nhận RESIDUAL RISK — cần redesign nonce server-issued sau.
  Giảm nhẹ hiện tại: IP daily limit + fraud scoring + duyệt rút thủ công.

### Reviewer notes
- C3 là thay đổi nhạy cảm nhất với UX 2-step. Nếu target_visited_at NULL ở luồng hợp lệ nào
  đó → credit=0 → user phải chờ lại (an toàn: nghiêng về bắt chờ, KHÔNG cho reward free).
- Không đụng logic show/hide widget (theo CLAUDE.md), chỉ sửa anti-fraud/timing.

## Session 2026-06-06T15:58:18Z — 5 isolated low-risk security fixes
**Spec source:** Inline task — M1 DDoS CSRF nonce, M2 float deposit, LOW resend rate-limit, H4 uncapped campaign size, M5 divergent bonus tiers
**Branch:** claude/page-unlock-domain-image-tjUU3

### Decisions
- **Fix1 (DDoS nonce):** Added `check_ajax_referer('traffictop_admin_nonce','nonce')` as first line in 5 handlers (anti-ddos.php). The 5 JS callers in `tab-settings.php` did NOT send a nonce → added `fd.append('nonce', wp_create_nonce("traffictop_admin_nonce"))` to each, matching existing pattern (tab-settings.php:485/494/501). Same nonce action used by all other admin handlers.
- **Fix2 (deposit float):** `floatval($_POST['amount'])` → `absint(...)` (admin-deposit-ajax.php:111), matching `traffictop_submit_deposit` invariant (integer VND).
- **Fix5 (bonus tiers):** Replaced the ASC-last-match inline calc in admin-deposit-ajax.php with a call to shared `traffictop_calculate_deposit_bonus($amount)` (deposit-management.php:39). Both paths now identical. NOTE: this also gives admin path the helper's default tiers when `deposit_presets` empty (was 0% before) — matches canonical customer path.

### Deviations from spec
- None material. Fix5 unifies on the existing shared helper (DESC first-match = highest tier amount<=deposit), which the spec explicitly allows ("If there is a shared helper... make BOTH paths call it").

### Reviewer notes
- Fix1: `check_ajax_referer` defaults to die-on-fail (3rd arg true). Cached admin pages with a stale nonce would fail — acceptable for these rarely-used admin actions.
- Fix5: For monotonic tier configs, DESC-first-match == ASC-last-match → no bonus change for normal configs. Divergence only on non-monotonic (exploit) configs, now closed.
- Fix2: bonus calc downstream uses `floor()` — works fine with integer amount.

## Summary
**Files changed:**
- `includes/anti-ddos.php` — 5 admin AJAX handlers: added nonce check
- `includes/admin/tabs/tab-settings.php` — 5 DDoS JS fetch calls: append admin nonce
- `includes/admin-deposit-ajax.php` — float→absint amount; bonus via shared helper
- `includes/email-notifications.php` — resend_verification per-IP rate limit
- `includes/customer-campaign-ajax.php` — clamp create daily_traffic(≤5000)/days(≤90)

**Top items for reviewer:**
1. Fix5: admin deposit path now uses helper default tiers when deposit_presets empty (was 0%).
2. Fix1: stale nonce on cached admin page → handler dies; acceptable for rare admin actions.
3. Fix4: days max=90 chosen as sane cap (spec-suggested); confirm no legit campaign needs >90d.

**Open questions:**
- None.

**Test coverage:**
- `php -l` clean on all 5 files. No unit tests added (isolated guards); existing suites unaffected.

### Summary (security hardening — toàn bộ phạm vi đã duyệt)
**Files changed:**
- `includes/shortlink-ajax.php` — C3 (credit thời gian thực thay vì lùi created_at), C1/C4 (bind IP)
- `includes/shortlink-verification.php` — H1 (is_nocode strict), H3 (trừ tiền khách source-of-truth + atomic guard)
- `includes/shortlink-functions.php` — M3 (reset cờ from_google/url_matched khi reuse visit)
- `includes/withdrawal.php` — H2 (refund tính lại từ formula), M4 (lock trước sync)
- `includes/anti-ddos.php` + `includes/admin/tabs/tab-settings.php` — M1 (nonce CSRF cho 5 endpoint DDoS + JS gửi nonce)
- `includes/admin-deposit-ajax.php` — M2 (absint), M5 (dùng chung helper bonus)
- `includes/email-notifications.php` — LOW (rate-limit resend_verification theo IP)
- `includes/customer-campaign-ajax.php` — H4 (clamp daily_traffic<=5000, days<=90)

**Top items reviewer cần soi:**
1. C3 (shortlink-ajax.php step2) — UX 2-step phụ thuộc target_visited_at được set đúng. Cần test luồng 2-step thật.
2. H3 (verify_and_pay) — gọi get_customer_balance_amount + sync_customer_balance trong transaction (đã xác nhận không mở transaction lồng).
3. M5 — presets rỗng giờ áp default tiers 5/10/15% ở cả 2 path (trước đó admin-path cho 0%). Production có presets → không ảnh hưởng.

**Residual risk (CHƯA fix — cần quyết định product):**
- C2: widget_verify_access tin HTTP_ORIGIN → forge được bằng curl (không phải browser). Cần redesign nonce server-issued. Hiện giảm nhẹ bằng IP daily limit + fraud scoring + duyệt rút thủ công.
- Farming self-click cùng IP vẫn bị giới hạn bởi IP daily limit, không bị chặn tuyệt đối.

**Test coverage:** `php tests/unit/run.php` → 11 passed / 0 failed. Tests dùng MockWpdb nên KHÔNG kiểm SQL column thật — cần verify trên staging/production schema.

## Session 2026-06-06T15:54:07Z (tiếp) — C2 hardening: chặn scripted client
**Spec source:** User yêu cầu "xử lý nốt" residual risk C2, cẩn thận không gây lỗi

### Decisions
- C2 (widget_verify_access tin Origin → forge bằng curl) KHÔNG thể đóng tuyệt đối: cookie/nonce
  cross-site sẽ phá widget với trình duyệt chặn third-party cookie → rủi ro cao. Self-farmer
  dùng chính session của mình nên nonce cũng không chặn được.
- Giải pháp an toàn + có giá trị: helper traffictop_is_scripted_client() (shortlink-ajax.php)
  chặn non-browser UA (curl/python/headless/selenium/...) tại các endpoint set cờ thanh toán:
  widget_verify_access, track_google_click, track_direct_click, update_step.
- Bảo thủ chống false-positive: chỉ match signature tool rõ ràng; UA RỖNG → KHÔNG chặn (proxy
  privacy có thể strip UA); đã bỏ 'electron/' (app desktop nhúng browser hợp lệ).

### Deviations from spec
- Không làm nonce/cookie redesign (rủi ro phá widget). Đây là bar-raising, không phải đóng triệt để.

### Reviewer notes
- UA spoof được (attacker đặt UA=Chrome) → đây chỉ chặn script ngây thơ (đúng kịch bản C2 "curl").
  Backstop thật vẫn là IP daily limit + fraud scoring + duyệt rút thủ công.
- Trình duyệt thật (widget.js + page-unlock) gửi UA bình thường → KHÔNG bị ảnh hưởng.
- RESIDUAL còn lại: self-farmer dùng real/headful browser automation với UA giả → cần lớp
  behavioral/fraud detection xử lý, không phải endpoint này.

### Summary (C2)
**Files changed:** includes/shortlink-ajax.php — helper + chặn scripted client ở 4 endpoint.
**Test:** php tests/unit/run.php → 11 passed / 0 failed. php -l sạch.

## Session 2026-06-06T15:54:07Z (tiếp) — Security đợt 2: Sybil + cleanup + webhook
**Spec source:** Audit đợt 2 (4 luồng) + user duyệt toàn bộ
**Branch:** claude/page-unlock-domain-image-tjUU3

### Decisions
- S2/S3 (fingerprint multi-account): KHÔNG auto-deny tại verify_and_pay. Lý do: device_fingerprints
  liên kết user_id=get_current_user_id() nhưng visitor farm thường KHÔNG đăng nhập + reward trả cho
  chủ shortlink → mapping visit→account quá yếu, auto-deny vừa kém hiệu quả vừa false-positive
  (gia đình/CGNAT). Thay vào đó: surface signal "device dùng chung N account" + "N account/IP" trong
  popup fraud-check của lệnh rút (admin review trước khi chi tiền) — điểm enforce an toàn, không
  chặn nhầm tự động.
- S1: rate-limit đăng ký theo IP (transient) + Turnstile verify server-side CHỈ khi enabled+configured,
  fail-open khi network lỗi (tránh chặn user thật do lỗi tạm).
- W1: deploy-webhook.php check chữ ký VÔ ĐIỀU KIỆN (giống deploy.php). User đã cho phép sửa file này
  (CLAUDE.md mặc định cấm). Giả định: GitHub webhook có secret → vẫn gửi chữ ký hợp lệ → không vỡ deploy.

## Session 2026-06-06T16:23:22Z — Additive security fixes A-F (rate-limit, identity, account-age, cleanup guards, unserialize, upload allow-list)
**Spec source:** Inline task (Fix A-F), additive low-risk security hardening
**Branch:** claude/page-unlock-domain-image-tjUU3

### Decisions
- Fix A/B (page-register.php): rate-limit + identity checks inserted into existing `elseif` chain
  using same `$error` surfacing pattern. Rate-limit transient incremented only on otherwise-valid
  attempts (after all field validation passes) to avoid burning quota on typos.
- Fix B: `traffictop_normalize_email()` helper defined in page-register.php (page-local, only consumer).
  Gmail/googlemail dot+plus stripping; non-gmail just lowercased.
- Fix C (withdrawal.php): account-age gate uses UTC user_registered vs time() (both UTC), NOT
  traffictop_current_time() (Vietnam) — per spec to avoid TZ skew on the UTC DB field.
- Fix D (user-management.php): added NOT EXISTS guards (reward_paid visits, user_balance>0,
  traffictop_deleted meta) to candidate SELECT; defense-in-depth, existing guards kept.
- Fix E: unserialize allowed_classes=false (object-injection hardening), is_array check kept.
- Fix F: extension+MIME allow-list via finfo in traffictop_upload_file() before wp_handle_upload,
  mirroring AJAX validation. Returns false (function's existing failure return type).

### Reviewer notes
- Fix B normalized-email/phone uniqueness queries usermeta directly with $wpdb->prepare.
- Fix A uses traffictop_get_real_ip() (shortlink-ip.php) — loaded by theme before templates.

### Summary (Fixes A-F)
**Files changed:**
- page-register.php — traffictop_normalize_email() helper; per-IP reg rate-limit (5/hr); disposable-domain block; normalized-email + phone uniqueness; store phone_normalized/traffictop_email_normalized meta.
- includes/withdrawal.php — min account-age gate (3d, option traffictop_min_account_age_days), UTC compare.
- includes/user-management.php — cleanup SELECT extra guards (traffictop_deleted meta, reward_paid visits, user_balance>0).
- includes/shortlink-ip.php / includes/anti-ddos.php — unserialize allowed_classes=false.
- includes/class-google-drive-upload.php — image ext+MIME allow-list in traffictop_upload_file().

**Test coverage:** php -l clean on all 6 files. No unit tests added (additive guards; existing flows unaffected). NOT committed per instruction.

### Summary (đợt 2 — phần 1)
**Files changed:**
- deploy-webhook.php — W1: chữ ký HMAC bắt buộc (reject nếu thiếu/sai)
- includes/admin-dashboard.php — S2/S3: 2 tín hiệu Sybil (device dùng chung account, account/IP) vào fraud-check lệnh rút
- page-register.php — S1 rate-limit/IP + S4 (chuẩn hóa email gmail-alias, chặn email rác, unique phone)
- includes/withdrawal.php — S4: min account age 3 ngày trước khi rút
- includes/user-management.php — C2: cleanup inactive thêm guard reward_paid=1/total_earned>0/traffictop_deleted
- includes/shortlink-ip.php, includes/anti-ddos.php — LOW: unserialize allowed_classes=false
- includes/class-google-drive-upload.php — LOW: allow-list extension+MIME cho upload fallback

**Reviewer cần soi:** S2/S3 là advisory cho admin (không auto-deny) — đúng chủ đích, tránh false-positive. Turnstile đăng ký làm ở commit sau.
**Test:** php tests/unit/run.php → 11 passed / 0 failed; php -l sạch toàn bộ.

### Summary (đợt 2 — phần 2: Turnstile đăng ký)
- page-register.php: helper traffictop_verify_turnstile() — no-op khi chưa cấu hình, fail-open
  khi lỗi mạng (tránh chặn user thật khi CF outage); nhánh verify trước wp_create_user; widget
  Turnstile render trong form CHỈ khi turnstile_enabled + site_key có.
- An toàn: mặc định chưa bật → đăng ký không đổi. Khi bật cần cả site_key + secret_key.
