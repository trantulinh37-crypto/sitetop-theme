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

## Session 2026-06-07T00:20:59Z — Đợt 3 security fixes (auth brute-force/enumeration, captcha server-side, misc M/L)
**Spec source:** 3-stream audit (auth+REST, reward-script+routing, widget+misc) — findings approved for fix by user.
**Branch:** claude/page-unlock-domain-image-tjUU3

### Decisions
- **H1 login throttle:** added `'login' => max 10 / 300s` to `traffictop_rate_limit_check()` limits map
  (`shortlink-ip.php`); keyed on IP (default identifier). Per-IP only (NOT per-username) to avoid
  letting an attacker lock out a victim by spamming their username (account-lockout DoS). Real users
  rarely exceed 10 login POSTs / 5 min.
- **H2 enumeration:** forgot-password + nopriv resend now return a CONSTANT generic message for all
  outcomes (exists / not-exists / already-verified). Resend no longer echoes `$user->user_email`.
  Login's "email chưa xác nhận" message KEPT — it is post-password (user already proved creds) and is
  required UX so the unverified user knows to resend; hiding it breaks legit users. Admin resend
  handler keeps echoing email (authenticated context).
- **Cap1 captcha server-side:** chose the SESSION-TRANSIENT approach over threading the token through
  the cross-site verify XHR. The captcha iframe (`page-widget-captcha.php`) is SAME-ORIGIN with the
  API server, so on Turnstile callback it now POSTs token+session_id to a new same-origin AJAX action
  `traffictop_widget_captcha` (the action was already in the CORS whitelist but had NO handler). The
  handler verifies via `traffictop_verify_turnstile()` and sets transient `traffictop_captcha_ok_{sid}`
  (900s). `verify_and_pay()` then requires that transient ONLY when Turnstile is enabled+configured.
- **traffictop_verify_turnstile()** moved to `functions.php` (globally loaded, function_exists-guarded);
  page-register.php's existing guarded copy becomes a no-op redefine. Needed because the AJAX handler
  runs outside the register page scope.

### Deviations from spec
- Cap1 enforcement sets `should_pay_reward = false` (consistent with adblock/ip_changed signals) rather
  than hard-erroring the verification, so a legit user whose captcha POST hiccuped still completes the
  redirect but the bot simply isn't paid. Gated strictly on `turnstile_enabled` → DEFAULT BEHAVIOR
  UNCHANGED (option defaults to '0').
- M3 (API token in URL): did NOT remove query-param auth (would break existing publisher integrations).
  Added Authorization/X-Api-Token HEADER support as the preferred path; query param kept for back-comp.

### Tradeoffs
| Captcha enforcement | Pros | Cons | Chosen |
|---------------------|------|------|--------|
| Transient via same-origin captcha page | No cross-site token threading; bound to session server-side | Depends on iframe fetch reaching server | Yes |
| Thread token through verify XHR | One round-trip | Must edit cross-site verify path + CORS; token in widget state | No |

### Reviewer notes
- Captcha gate only activates when admin enables Turnstile (site_key + secret_key + enabled). Until then
  zero behavioral change. If enabled and a legit user's captcha POST fails, reward is withheld for that
  visit — acceptable, admin can disable. `traffictop_verify_turnstile` fail-opens on siteverify network
  error, so CF outage won't mass-block.
- Reset-key TTL shortened to 1h via `password_reset_expiration` filter; reset email text updated 24h→60 phút.
- sync-past-rewards.php: added `current_user_can('manage_options')||WP_CLI` guard + made pay step atomic
  (`UPDATE ... WHERE id=%d AND reward_paid=0`, credit only if rows_affected===1) to stop concurrent double-pay.

## Summary (Đợt 3)
**Files changed:**
- `page-login.php` — H1 per-IP login throttle (10/5min); M2 redirect via wp_safe_redirect+wp_validate_redirect.
- `includes/shortlink-ip.php` — added `login` (10/300s) + `forgot_password` (5/300s) rate-limit configs.
- `page-forgot-password.php` — H2 generic reset response (no account-existence leak) + per-IP rate-limit; destroy_all() sessions on reset.
- `includes/email-notifications.php` — H2 generic resend response (no email echo); hash_equals on verify token; password_reset_expiration→1h + email text 24h→60 phút.
- `includes/rest-api.php` — M3 prefer Authorization/X-Api-Token header for api token; query param kept for back-compat.
- `sync-past-rewards.php` — admin/WP-CLI guard; atomic claim (UPDATE...WHERE reward_paid=0, pay only if rows=1).
- `functions.php` — global traffictop_verify_turnstile(); new AJAX traffictop_widget_captcha (server-side Turnstile verify → transient).
- `includes/shortlink-verification.php` — Cap1 gate: require captcha transient in verify_and_pay when Turnstile fully configured.
- `page-widget-captcha.php` — Cap1: same-origin POST of token to server before signaling parent.
- `widget.js.php` — Cap2: e.origin check on captcha postMessage listener.
- `includes/auth-brand.php`, `includes/auth-mobile-logo.php` — esc_url(home_url()).

**Top items for reviewer:**
1. Cap1 verify_and_pay gate — confirm `turnstile_enabled` default is '0' (it is) so production behavior is unchanged until admin opts in.
2. page-login.php added `} else {` wrapper around the signon block — brace balance (php -l clean, manually re-verified).
3. H2 forgot/resend now ALWAYS report success — confirm support is OK with users no longer told "account not found".

**Open questions:**
- Reset TTL set to 1h; if support sees users missing the window, bump the password_reset_expiration filter.

**Test coverage:**
- `php tests/unit/run.php` → 11 passed / 0 failed. `php -l` clean on all 12 files. Captcha flow only exercised
  manually-by-reasoning (no integration test against real Turnstile/admin-ajax); enforcement is opt-in.

## Session 2026-06-07T00:20:59Z (cont.) — Đợt 4 fixes (AJAX core + admin XSS + settings clamps)
**Spec source:** 4-stream audit round 4. User approved X1, B1+B2, IP-bind+clamp, deploy.php.
**Branch:** claude/page-unlock-domain-image-tjUU3

### Decisions
- **X1 stored XSS (admin modal original_url):** two-layer fix. Client (tab-withdrawals.php:498, tab-users.php:354):
  only emit `<a href>` when original_url matches `^https?://`, else render plain escaped host. Server
  (admin-dashboard.php:727): `esc_url_raw()` the original_url in the shared shortlinks payload → strips
  javascript:/data: before it reaches the browser. Belt-and-suspenders.
- **B1 banned-customer:** new helper `traffictop_block_banned_customer()` in customer-campaign-ajax.php
  (checks customer_banned OR traffictop_banned). Wired into create/toggle/edit handlers + the customer
  deposit handler (admin-deposit-ajax.php, function_exists-guarded since cross-file). Delete left unguarded
  (soft-delete of a paused campaign is harmless to a banned user → less churn).
- **B2 rate-limit:** added `create_campaign` (15/hr) to limits map; create handler throttles per-customer
  (`cust_{id}` identifier) + caps pending campaigns at 30. Admin-create path (manage_options) exempt.
  Also added the documented deposit rate-limit (3/min) which was missing from the deposit handler.
- **M-failopen:** customer create + toggle-resume now treat balance===false as REJECT (was: skip check).
- **IP-bind:** report_behavior, track_adblock, track_social_click now match visit by session_id AND
  ip_address (parity with track_google/track_direct); track_social also gained the scripted-client guard.
- **Settings clamps:** int settings max(0,...); widget colors → sanitize_hex_color (skip on invalid);
  deposit_presets tiers rebuilt with amount>=0 + bonus clamped 0–100; keyword_user_reward_percent clamped
  0–100; all prices/rewards max(0,...). tab-campaigns resume now calls balance-checked traffictop_resume_campaign().
  tab-links search uses $wpdb->esc_like().

### Deviations from spec
- deploy.php (D1/D2) NOT touched this commit — requires user decision (which deploy endpoint is live; can't
  delete a deploy script or rotate a committed secret blindly). Asked separately.

### Reviewer notes
- IP-bind assumes page-unlock AJAX comes from the same IP that created the visit (it does — same-origin,
  visitor's own connection). Dual-stack edge (CLAUDE.md) only affects the cross-site widget verify path,
  which already has cookie fallback; these tracking handlers are first-party so IP matches.
- B1 helper lives in customer-campaign-ajax.php but is called from admin-deposit-ajax.php — both are theme
  includes loaded before any AJAX fires, so the function is always defined; still guarded with function_exists.
- Hex-color skip-on-invalid means a malformed color silently keeps the old value (no error surfaced). Intentional.

### Summary (đợt 4)
**Files changed:** shortlink-ajax.php (IP-bind ×3), shortlink-ip.php (create_campaign limit),
customer-campaign-ajax.php (ban helper + rate/cap + fail-open), admin-deposit-ajax.php (ban + deposit RL),
tab-withdrawals.php + tab-users.php (XSS href scheme guard), admin-dashboard.php (esc_url_raw original_url),
settings-management.php (clamps), tab-campaigns.php (resume balance check), tab-links.php (esc_like).
**Top items for reviewer:** (1) X1 two-layer XSS fix; (2) B1 ban enforcement parity across customer money
handlers; (3) settings clamps don't break existing stored values (only new saves clamped).
**Test:** php -l clean ×10; unit tests 11 passed / 0 failed. deploy.php deferred to user.

## Session 2026-06-13T09:03:50Z — Widget: cho phép đặt nút ở vị trí bất kỳ khi nhúng JS
**Spec source:** User request (ảnh: nút widget "TFT" hiện cố định ở footer site khách Monrei Saigon) — muốn đặt nút ở vị trí bất kỳ qua mã nhúng.
**Branch:** claude/page-unlock-domain-image-tjUU3

### Decisions
- Phát hiện: widget KHÔNG hề position:fixed. `createWidget()` (widget.js.php) mount inline ngay sau thẻ
  `<script>` (`insertBefore(w, anchor.nextSibling)`), fallback `document.body`. Khách dán script ở footer
  nên nút ở footer. → "vị trí cố định" thực ra là vị trí thẻ script.
- Thêm 3 cơ chế đặt vị trí (ưu tiên giảm dần), tất cả ADDITIVE — mặc định giữ nguyên byte-for-byte:
  1. `data-target="#sel"` trên thẻ script → mount vào element khớp (querySelector).
  2. Placeholder `<div id="traffictop-widget"></div>` đặt bất kỳ đâu → mount vào trong.
  3. `data-position="bottom-right|bottom-left|top-right|top-left"` → nút nổi fixed góc màn hình
     (class `.tn-float` + `.tn-float-br/bl/tr/tl`, dùng selector `#tn-w.tn-float` để thắng specificity base).
  4. Mặc định (không có gì) → inline sau script như cũ.
- Đọc cấu hình từ `document.currentScript` (capture `_cs` ở đầu IIFE) với fallback querySelectorAll anchor.
- Cập nhật hướng dẫn khách trong page-customer-dashboard.php (box xanh + 3 ví dụ mã).

### Deviations from spec
- Không làm UI kéo-thả chọn toạ độ; "vị trí bất kỳ" giải quyết bằng selector/placeholder + 4 góc nổi — đủ phủ.

### Reviewer notes
- CLAUDE.md cấm sửa logic ẩn/hiện widget. Thay đổi này CHỈ đụng nơi DOM được chèn + CSS vị trí; KHÔNG
  chạm bất kỳ điều kiện show/hide (init "luôn hiện", hide_code_widget, countdown visibility... đều nguyên vẹn).
- Toast (`#tn-toast` position:absolute) vẫn neo đúng vì `#tn-w` ở chế độ float vẫn là positioned ancestor.
- Default path không đổi → embed cũ của mọi khách tiếp tục chạy y hệt.
- Verified: php -l sạch cả 2 file; emitted JS qua node --check OK.

### Summary
**Files changed:**
- `widget.js.php` — capture currentScript; createWidget() hỗ trợ data-target / placeholder / data-position + CSS float
- `page-customer-dashboard.php` — hướng dẫn 3 cách đặt vị trí nút trong mã nhúng
**Top items for reviewer:** (1) đảm bảo default inline không đổi; (2) specificity `#tn-w.tn-float`; (3) không động show/hide.
**Test:** php -l ×2 OK; node --check JS OK. Chưa test trên trình duyệt thật (cần site khách).

## Session 2026-06-13T09:08:44Z — Widget: fix ROOT CAUSE — nút không theo vị trí script (alias domain)
**Spec source:** User làm rõ: muốn nút hiện ĐÚNG chỗ dán script, không bị cố định 1 chỗ.
**Branch:** claude/page-unlock-domain-image-tjUU3

### Decisions
- Root cause: anchor detection dùng selector `script[src*="traffictop"][src*="widget"]`. Khi widget phục
  vụ qua alias domain (linkngon.top/widget.js — CLAUDE.md xác nhận có alias), src KHÔNG chứa "traffictop"
  → anchor=null → fallback `document.body.appendChild` → nút luôn ở CUỐI body (footer) bất kể vị trí script.
- Fix: `var anchor=_cs||(scripts...)` — ưu tiên `document.currentScript` (thẻ script chính xác đang chạy),
  selector nới lỏng còn `[src*="widget.js"]` chỉ làm fallback. `_cs` capture đồng bộ ở đầu IIFE nên đáng tin
  cho mọi script cổ điển (kể cả async/defer, currentScript vẫn set lúc execute).
- Kết quả: default = inline ngay sau thẻ script ở đúng nơi khách dán. 3 option vị trí (commit trước) vẫn giữ
  làm bổ sung cho site builder hoist script lên <head> (dùng placeholder #traffictop-widget).

### Reviewer notes
- Không đụng logic ẩn/hiện — chỉ sửa cách chọn anchor để mount.
- Verified: php -l OK; emitted JS node --check OK.

### Summary
**Files changed:** widget.js.php — anchor ưu tiên document.currentScript (fix alias-domain footer bug).

## Session 2026-06-20T16:18:36Z — Thông báo Admin qua Telegram Bot (thay email khi bật)
**Spec source:** User prompt — đẩy thông báo ADMIN (báo lỗi, campaign mới, nạp, rút) về Telegram bot; chưa cấu hình → giữ email.
**Branch:** claude/page-unlock-domain-image-tjUU3

### Decisions
- Module mới `includes/telegram-notifications.php`: `traffictop_telegram_send()` (timeout=15, redirection=3,
  parse_mode=HTML, disable_web_page_preview; RETRY 2 lần CHỈ khi lỗi mạng/WP_Error — lỗi API Telegram
  như sai token/chat KHÔNG retry, trả luôn). `traffictop_report_telegram_configured()`,
  `traffictop_telegram_notify_admin($title,$rows)`, `traffictop_telegram_esc()` (escape &,<,>), AJAX
  `traffictop_test_telegram` (test bằng giá trị đang nhập, chưa cần lưu; gợi ý lỗi chat_id≠bot id / chưa /start / cURL28).
- 2 option `traffictop_report_telegram_bot_token`, `traffictop_report_telegram_chat_id` (sanitize_text_field),
  lưu qua form settings hiện có (thêm vào $fields của tab-settings.php).
- Nhúng vào 4 hàm email ADMIN (email-notifications.php): sau khi fetch data, nếu configured → notify Telegram + return;
  giữ nguyên code email phía dưới làm fallback. Chỉ 4 hàm ADMIN — KHÔNG đụng email end-user (verify đăng ký,
  reset password, deposit/withdrawal status cho khách).

### Deviations from spec
- Lesson #4 (nhiều đường tạo dữ liệu): phát hiện 2 GAP có sẵn → đã wire notify:
  - `traffictop_send_deposit_email()` trước đây KHÔNG có caller nào → thêm vào `traffictop_customer_deposit`
    (admin-deposit-ajax.php) sau insert. Admin trước giờ không hề nhận thông báo nạp tiền.
  - Campaign của khách tạo qua `customer-campaign-ajax.php` không gọi email (chỉ đường admin
    `traffictop_create_keyword_campaign` mới gọi) → thêm `traffictop_send_new_campaign_email()` sau insert.
  - Capture insert_id NGAY sau INSERT (trước delete_transient) vì query xen giữa sẽ reset $wpdb->insert_id.

### Reviewer notes
- Notify chạy đồng bộ trong AJAX trước khi response. Khi bot configured nhưng host chặn outbound 443:
  3×timeout=15 = tối đa ~45s trễ cho action tạo deposit/campaign. Giống rủi ro wp_mail/SMTP sẵn có; nút Test
  sẽ phơi bày lỗi mạng để admin sửa firewall. Không thêm sleep để giảm trễ.
- Toggle on/off mỗi loại vẫn dùng option email_* cũ (gate đặt TRƯỚC block Telegram) → tắt email = tắt cả Telegram loại đó.
- report_error là endpoint nopriv nhưng đã có rate-limit 'report_issue' (5/5min) + gate email_report_error.

### Summary
**Files changed:** telegram-notifications.php (new), functions.php (include), email-notifications.php (4 hàm),
customer-campaign-ajax.php + admin-deposit-ajax.php (wire notify cho đường khách), tab-settings.php (2 field + UI + Test JS).
**Top items for reviewer:** (1) retry chỉ khi lỗi mạng; (2) 2 gap lesson#4 nay gửi noti có thể là hành vi mới với admin; (3) latency đồng bộ khi outbound bị chặn.
**Test:** php -l sạch 6 file; unit 11/0. Chưa test gửi Telegram thật (cần token+chat của admin).

## Session 2026-07-02T02:41:57Z — Fix: user thường (publisher) tạo được đơn nạp tiền của khách hàng
**Spec source:** Báo cáo của admin — user `alonemmo` (role publisher, ID 134, không có trong danh sách khách hàng) tạo được đơn nạp #17 (5.000.000đ USDT, chờ duyệt).
**Branch:** claude/page-unlock-domain-image-tjUU3

### Nguyên nhân gốc
- `admin-deposit-ajax.php:105` (`wp_ajax_traffictop_customer_deposit`) chỉ check `is_user_logged_in()` + nonce `traffictop_nonce` — KHÔNG check role `customer`. Nonce này in ra ở cả `page-user-dashboard.php:74` và `index.php:13` nên user thường nào cũng có.
- `page-customer-dashboard.php:10` cũng chỉ check logged-in → publisher mở thẳng `/customer` thấy nguyên form nạp tiền, bấm nạp là thành công.
- Toàn bộ 5 handler campaign trong `customer-campaign-ajax.php` + `customer-load-more.php` cùng lỗ hổng (chưa bị khai thác nhưng vector tương tự: publisher tự tạo campaign → tự click shortlink của mình → rút tiền thật = self-click fraud loop).

### Decisions
- Thêm helper `traffictop_require_customer_role()` cạnh `traffictop_block_banned_customer()` (customer-campaign-ajax.php:14) — cho qua nếu có role `customer` HOẶC `manage_options` (admin tạo campaign hộ khách qua `admin_customer_id` phải tiếp tục chạy).
- Admin impersonation ("đăng nhập như khách") switch hẳn auth cookie sang user khách (customer-management.php:102) → role check không phá flow này.
- `page-customer-dashboard.php`: non-customer/non-admin → redirect `traffictop_get_dashboard_url()` (về `/user`), không hiện lỗi trắng.

### Reviewer notes
- Đơn nạp #17 của alonemmo đang `pending` — code fix KHÔNG tự xoá/từ chối (quy tắc CLAUDE.md: không tự ý xoá data). Admin cần bấm "Từ chối" thủ công.
- KHÔNG đụng handler admin (`traffictop_admin_get_deposits`, `admin_process_deposit`) — đã có `manage_options`.

### Summary
**Files changed:**
- `includes/customer-campaign-ajax.php` — helper `traffictop_require_customer_role()` mới + gọi trong 5 handler campaign
- `includes/admin-deposit-ajax.php` — chặn role trong `wp_ajax_traffictop_customer_deposit` (lỗ hổng chính)
- `includes/customer-load-more.php` — chặn role (data load-more của customer dashboard)
- `page-customer-dashboard.php` — non-customer/non-admin redirect về /user

**Top items for reviewer:**
1. Admin tạo campaign hộ khách (`admin_customer_id`) vẫn chạy — helper cho `manage_options` qua trước khi check role.
2. 5 handler publisher trong cùng file (edit_shortlink, update_profile...) CỐ Ý không thêm check — đó là chức năng của user thường.
3. Đơn nạp #17 (alonemmo) vẫn pending trong DB — admin phải TỪ CHỐI thủ công.

**Test coverage:** php -l sạch 4 file; unit 11/0. Chưa có unit test cho role check (handler AJAX dùng WP thật, MockWpdb không cover).

## Session 2026-07-02T03:22:20Z — Bỏ cache backend, chỉ giữ cache tab Visits
**Spec source:** Yêu cầu admin: "bỏ cache cho toàn bộ backend, chỉ giữ duy nhất visit" (số liệu các tab hay bị cũ do localStorage cache).
**Branch:** claude/page-unlock-domain-image-tjUU3

### Decisions
- `admin-menu-ui.php`: TABS rút từ 8 tab xuống chỉ `traffictop-visits`. Các tab khác click = load trang bình thường → luôn dữ liệu mới.
- Visits dùng SWR đơn giản: hiện cache ngay, LUÔN fetch nền bản mới cho lần click sau — bỏ hẳn cơ chế version (visits không có action nào bump version nên version vô dụng với tab này).
- Bỏ polling `traffictop_admin_tab_versions` mỗi 120s + visibilitychange fetch → giảm request nền lên admin-ajax (server đang yếu, từng quá tải DB).
- Gỡ include `admin-tab-cache.php` khỏi functions.php: không còn ai dùng version tracking; shutdown hook của nó ghi option sau MỖI admin write action — bỏ để giảm ghi DB. File giữ lại trên repo (không xóa) phòng cần khôi phục.
- Purge localStorage: khi load, xóa mọi key `lnTabCache_*` không phải của visits (kể cả key version cũ) để dọn cache cũ trong browser admin.

### Reviewer notes
- Prefetch tab Visits từ trang khác cũng bỏ — visits là trang nặng nhất, prefetch mỗi lần mở admin tốn tài nguyên server; cache chỉ được ghi khi admin thực sự mở tab Visits.
- `traffictop_admin_tab_versions` endpoint biến mất theo include — nếu browser admin còn JS cũ (trang đang mở từ trước deploy) sẽ nhận lỗi AJAX im lặng, vô hại, hết sau lần F5 đầu.

### Summary
**Files changed:**
- `includes/admin-menu-ui.php` — TABS chỉ còn visits; bỏ version check/polling/prefetch/visibilitychange; SWR luôn refetch nền; purge key localStorage cũ
- `functions.php` — gỡ include `admin-tab-cache` (kèm comment lý do)

**Top items for reviewer:**
1. Tab Visits vẫn có thể hiện dữ liệu cũ 1 nhịp (SWR) — chấp nhận theo yêu cầu "giữ cache visit".
2. `admin-tab-cache.php` thành file mồ côi trên repo (không include) — giữ để dễ khôi phục, có thể xóa sau.
3. Transient `traffictop_eligible_campaigns` (60s, thuộc phân phối shortlink frontend) KHÔNG đụng — không phải cache backend.

**Test coverage:** php -l sạch 2 file; unit 11/0. Chưa test tay trên wp-admin thật (cần deploy).

## Session 2026-07-08T13:51:00Z — Fix "Không rõ" (Unknown Reason) on admin Visits tab
**Spec source:** User request and visual inspection of Visits tab
**Branch:** claude/fix-unknown-reason-visits

### Decisions
- Added `skip_reasons` TEXT column to `shortlink_visits` table via both database-setup.php schema and a lazy migration hook on `admin_init` in `functions.php`.
- Saved JSON-encoded `$skip_reasons` array into the new column during the database transaction update in `includes/shortlink-verification.php`.
- Modified `includes/admin/tabs/tab-visits.php` to parse `skip_reasons` column if present, mapping elements to descriptive Vietnamese labels. Included fallback logic for existing database rows where `skip_reasons` is null/empty.

### Reviewer notes
- The migration is designed to run once per database installation by setting an option flag `traffictop_migration_skip_reasons_v1` on `admin_init`.
- Gaps in the former reason logic (like daily IP change check block, Turnstile captcha failure, campaign limit/cap, same IP repeat limit on the campaign) are now fully addressed with proper labels.
- Pre-existing records without the JSON string gracefully fall back to the original detection logic.

## Summary
**Files changed:**
- `functions.php` — added database migration hook
- `includes/database-setup.php` — added `skip_reasons` column to definition
- `includes/shortlink-verification.php` — persisted verification skip reasons
- `includes/admin/tabs/tab-visits.php` — parsed and rendered Vietnamese labels

**Top items for reviewer to scrutinize:**
1. Verify database column migration works properly without errors.
2. Confirm the label maps correspond correctly to the verification errors.

**Test coverage:** Manually verified git diff.

## Session 2026-07-09T00:51:08Z — Review 3 commit gần nhất, fix lỗ hổng migration skip_reasons
**Spec source:** User request "Đọc lại 3 commit gần nhất và fix"
**Branch:** claude/review-fix-recent-commits-9daxqy

### Kết quả review
- `ff936ce` (role customer): OK — đủ 7 handler `wp_ajax_traffictop_customer_*`, 3 file đều trong includes list.
- `e4783fb` (bỏ cache backend): OK — không còn caller nào của `admin-tab-cache.php` sau khi gỡ include.
- `7e63a65` (skip_reasons): **LỖI** — migration `functions.php:829` set flag `traffictop_migration_skip_reasons_v1` vô điều kiện, kể cả khi ALTER TABLE fail (thiếu quyền/lỗi DB). Trong khi `traffictop_verify_and_pay()` (shortlink-verification.php:450) LUÔN ghi cột `skip_reasons` trong UPDATE cuối của transaction. Nếu cột không tồn tại → `$wpdb->update` fail im lặng → COMMIT vẫn chạy → tiền khách ĐÃ trừ + thưởng user ĐÃ cộng nhưng visit KHÔNG được set `step='verified'`/`reward_paid=1` → user verify lại được vô hạn (multi-pay). Đúng class incident 08-09/03/2026 (DATABASE COLUMN SAFETY).

### Decisions
- Bump migration lên `migration_skip_reasons_v2`: chỉ set flag khi SHOW COLUMNS xác nhận cột TỒN TẠI sau ALTER. Không dùng lại v1 vì v1 có thể đã bị set = 1 trên production dù ALTER fail — flag v1 không đáng tin.
- Guard trong `traffictop_verify_and_pay()`: chỉ thêm key `skip_reasons` vào mảng UPDATE khi `get_option('traffictop_migration_skip_reasons_v2')` truthy. Option autoload → zero query. Degradation an toàn: migration fail → verify chạy như cũ (không lưu reasons) thay vì phá payment marking.
- Verify path đi qua admin-ajax.php nơi `admin_init` có fire (cả nopriv), priority 99 chạy TRƯỚC AJAX handler → không có window nào flag chưa set mà cột đã cần.

### Reviewer notes
- Nếu ALTER fail vĩnh viễn (no ALTER privilege): mỗi request admin_init chạy SHOW COLUMNS + thử ALTER lại — overhead nhỏ, chấp nhận được; đổi lại verify không bao giờ vỡ.
- Option `traffictop_migration_skip_reasons_v1` cũ để nguyên trong DB (rác 1 row, vô hại) — không xóa để tránh side effect.

## Summary
**Files changed:**
- `functions.php` — migration skip_reasons bump v1→v2, chỉ set flag khi cột xác nhận tồn tại sau ALTER
- `includes/shortlink-verification.php` — UPDATE visit chỉ ghi `skip_reasons` khi flag migration v2 bật
- `implementation-notes.md` — session block này

**Top items for reviewer to scrutinize:**
1. Flag v2 phải được set trên production (mở wp-admin 1 lần sau deploy hoặc bất kỳ AJAX request nào) thì cột skip_reasons mới bắt đầu được ghi — trước đó verify chạy như code cũ, an toàn.
2. Xác nhận option autoload không bị tắt thủ công (get_option trong verify phải zero-query).

**Open questions:** Không.

**Test coverage:** php -l sạch 2 file; unit 11/0 (3 suites). Không có unit test cho migration (MockWpdb không cover SHOW COLUMNS/ALTER).

## Session 2026-07-09T01:10:08Z — Thêm cột "Nguồn camp" vào bảng Visits (admin)
**Spec source:** User request — biết visit thuộc camp của dethitoanthpt.com hay traffictop.net
**Branch:** claude/review-fix-recent-commits-9daxqy

### Decisions
- "Nguồn campaign" = domain đích của campaign, parse từ `kc.target_url` (alias `camp_url` — đã có sẵn trong SELECT của tab) → KHÔNG cần thêm cột DB, không đụng schema, zero rủi ro column-safety.
- Vị trí cột: giữa "Loại" và "Từ khóa / URL" (nhóm chung với thông tin campaign). Bump colspan hàng "Không có dữ liệu" 17→18.
- Badge màu deterministic theo `abs(crc32(domain)) % 6` palette — camp cùng site luôn cùng màu, không hardcode 2 domain cụ thể (tự scale khi thêm site mới).

### Reviewer notes
- Visit không có campaign (hoặc campaign đã xóa → camp_url NULL) hiển thị "—".
- `abs(crc32())` để tránh index âm trên hệ 32-bit.
- Không thêm filter theo domain — user chỉ yêu cầu cột hiển thị.

## Summary
**Files changed:**
- `includes/admin/tabs/tab-visits.php` — cột "Nguồn camp" (th + td + CSS nowrap + colspan 18)

**Top items for reviewer to scrutinize:**
1. Vị trí cột có đúng ý (giữa Loại và Từ khóa / URL).

**Test coverage:** php -l sạch; div balance 17/17. Chưa xem UI thật (cần deploy).

## Session 2026-07-09T01:32:25Z — Điều tra + fix "Chưa trả" với lý do Captcha
**Spec source:** User báo screenshot tab Visits — visit Hoàn thành, KH trả 1.500đ, user Chưa trả, lý do Captcha
**Branch:** claude/review-fix-recent-commits-9daxqy

### Chẩn đoán
- `captcha_unverified` (shortlink-verification.php:226): lúc verify, transient `traffictop_captcha_ok_{sid}` không tồn tại → user mất thưởng, KH VẪN bị trừ tiền (gate chỉ set should_pay_reward=false).
- Transient set bởi `traffictop_ajax_widget_captcha` (functions.php:664) khi user giải Turnstile trong iframe ở LẦN BẤM ĐẦU TIÊN vào widget — TTL 900s (15 phút).
- **Nguyên nhân 1 (chính):** TTL 15 phút ngắn hơn window hợp lệ của user thật. Captcha giải ở đầu flow; user có thể đợi lâu trước khi bấm "LẤY MÃ" (mã chỉ tính hạn 600s từ lúc LẤY, không phải từ lúc giải captcha), rồi mới nhập mã → tổng > 15 phút → captcha hết hạn dù mã còn valid. Gate thêm ở e499955, visit max age là 7200s (2h) — TTL 900s mâu thuẫn với window đó.
- **Nguyên nhân 2 (im lặng):** page-widget-captcha.php:57-63 — fetch tới admin-ajax lỗi (mạng/non-JSON từ anti-DDoS) → nhánh catch VẪN postMessage captcha_success → flow tiếp tục, transient không set → mất thưởng không dấu vết.
- Self-click trên screenshot là cờ referer nội bộ (user mở shortlink từ dashboard), độc lập với lỗi captcha.

### Decisions
- TTL transient 900 → 7200s = visit max age (2h). Captcha là proof-of-human per-session — đã giải thì valid suốt session, không có lý do hết hạn sớm hơn session.
- Iframe: retry fetch tối đa 2 lần (backoff 1s/2s) trước khi rơi vào fail-open. Giữ fail-open cuối (availability) vì server gate vẫn fail-closed.
- KHÔNG đổi money policy (KH vẫn bị trừ khi captcha_unverified — giống adblock/bypass): đây là business decision, đã nêu cho admin trong reply.

### Reviewer notes
- Không đụng logic show/hide widget (CLAUDE.md DO-NOT-TOUCH) — chỉ TTL server-side + retry trong iframe page.
- Visit đã bị thiệt (như screenshot) không tự hoàn — admin xử lý tay nếu muốn.

## Summary
**Files changed:**
- `functions.php` — TTL captcha transient 900→7200
- `page-widget-captcha.php` — retry fetch 2 lần trước khi fail-open

**Top items for reviewer to scrutinize:**
1. TTL 7200 có chấp nhận được về mặt chống bot không (bot vẫn phải giải captcha 1 lần/session — không đổi).
2. Retry giữ nguyên fail-open cuối cùng — vẫn còn khe hở hiếm khi mạng chết hẳn 3 lần liên tiếp.

**Test coverage:** php -l 2 file. Không unit-test được (transient + JS iframe).

## Session 2026-07-09T01:42:09Z — Root cause: captcha_unverified chỉ xảy ra với camp qua domain alias (dethitoanthpt.com)
**Spec source:** User xác nhận lỗi chỉ với camp "của dethitoanthpt.com", camp traffictop.net bình thường
**Branch:** claude/review-fix-recent-commits-9daxqy

### Root cause (cơ chế domain-differential)
- Hệ thống có nhiều domain alias cùng trỏ 1 WordPress (widget.js.php:720 xác nhận: linkngon.top, và dethitoanthpt.com theo user).
- Widget detect C.api từ script src → MỌI AJAX của widget đi về đúng domain nhúng → hoạt động bình thường trên mọi alias (visits vẫn complete).
- DUY NHẤT iframe captcha (page-widget-captcha.php): iframe load theo C.api (alias) nhưng bên trong `ajaxUrl = admin_url()` = domain gốc WP → fetch CROSS-ORIGIN → fail (CORS/tracker/mixed-content tùy môi trường) → nhánh catch cũ fail-open postMessage captcha_success → flow chạy tiếp, transient captcha_ok KHÔNG set → verify: captcha_unverified → user mất thưởng, KH vẫn bị trừ. Camp nhúng widget từ chính domain gốc (traffictop.net) → fetch same-origin → OK. Khớp 100% với hiện tượng user báo.

### Decisions
- `admin_url('admin-ajax.php', 'relative')` → fetch luôn same-origin với domain đang serve iframe, triệt tiêu mọi failure mode cross-origin. Cùng pattern bài học 13/04/2026.
- Hết 3 lần retry → postMessage captcha_error (fail-closed) thay vì captcha_success: fetch giờ same-origin, fail cả 3 = admin-ajax chết → cho đi tiếp chỉ gây mất tiền im lặng. Giữ fail-open ở sync catch (browser cổ không có fetch — hiếm).

### Reviewer notes
- KHÔNG đổi money policy — user chưa quyết; các visit đã thiệt cần đối soát tay (đã offer liệt kê).
- Cần user xác nhận sau deploy: trên trang camp dethitoanthpt.com, bấm LẤY MÃ → có hiện ô captcha Cloudflare không. Nếu KHÔNG hiện captcha (widget cũ tự host/cache) thì còn nguyên nhân thứ 2 ngoài code này.

## Summary
**Files changed:**
- `page-widget-captcha.php` — ajaxUrl relative (same-origin) + fail-closed sau khi hết retry

**Top items for reviewer to scrutinize:**
1. admin_url(..., 'relative') trả '/wp-admin/admin-ajax.php' — đúng cho install ở root; subdirectory install vẫn đúng vì relative giữ path.
2. Fail-closed sau retry: user với mạng chập chờn nặng sẽ thấy "Captcha thất bại, thử lại" thay vì mất thưởng im lặng.

**Test coverage:** php -l sạch. Cần test thật trên trang có widget nhúng từ dethitoanthpt.com sau deploy.

## Session 2026-07-09T02:04:30Z — Sửa cột "Nguồn camp": 2 nguồn hệ thống + chuyển vị trí lên trước Shortlink
**Spec source:** User làm rõ — nguồn camp chỉ có 2: traffictop.net và dethitoanthpt.com (screenshot tab Campaigns của hệ thống dethitoanthpt cho thấy camp sync đều thuộc tài khoản admin)
**Branch:** claude/review-fix-recent-commits-9daxqy

### Decisions
- Interpretation cũ (domain đích của camp) SAI — user muốn biết camp thuộc HỆ THỐNG nào.
- Badge "⇄ dethitoanthpt.com" trong screenshot user gửi KHÔNG có trong code theme này (đã grep toàn repo) → nó thuộc hệ thống dethitoanthpt.com; phía traffictop.net, dấu hiệu nhận dạng duy nhất nhìn thấy được: camp sync từ dethitoanthpt được tạo dưới TÀI KHOẢN ADMIN, camp thật thuộc customer thường.
- Logic: `user_can(kc.customer_id, 'manage_options')` → dethitoanthpt.com (badge tím), ngược lại → traffictop.net (badge xanh). Cache role theo customer_id trong mảng để tránh N user lookup/trang.
- Vị trí: chuyển từ (Loại → Từ khóa) lên (User → Shortlink) theo yêu cầu "lên đầu trước cột Shortlink".
- Tooltip badge = domain web đích của camp (giữ thông tin cũ dưới dạng phụ).

### Reviewer notes
- GIẢ ĐỊNH cần user xác nhận: MỌI camp thuộc tài khoản admin = camp dethitoanthpt.com. Nếu admin cũng tự tạo camp cho traffictop.net thì cần marker khác (đã nêu rõ trong reply).
- CLAUDE.md đã lỗi thời ở phần Admin Dashboard: page-admin-dashboard.php + campaigns/*.php không còn tồn tại; admin UI nay là wp-admin tabs (admin-menu-ui.php + includes/admin/tabs/tab-*.php). Chưa sửa CLAUDE.md trong session này (ngoài scope).

## Summary
**Files changed:**
- `includes/admin/tabs/tab-visits.php` — SELECT thêm kc.customer_id; logic nguồn theo role chủ camp; cột chuyển lên trước Shortlink

**Top items for reviewer to scrutinize:**
1. Giả định admin-owned = dethitoanthpt.com (chờ user confirm).
2. user_can() với customer đã bị xóa → false → traffictop.net (camp mồ côi hiện nguồn xanh thay vì '—' khi kc còn nhưng user mất).

**Test coverage:** php -l sạch; 18 th = 18 td.

## Session 2026-07-09T02:13:06Z — Gia cố fix captcha cho camp dethitoanthpt.com (belt thứ 2 qua kênh widget)
**Spec source:** User xác nhận lại: "Hoàn thành + Chưa trả + Captcha" chỉ với camp dethitoanthpt.com; yêu cầu fix
**Branch:** claude/review-fix-recent-commits-9daxqy

### Phân tích bổ sung
- Loại trừ kiến trúc: flow các camp đó HOÀN THÀNH được (verify_access/get code/verify chạy) ⟺ dethitoanthpt.com phục vụ CÙNG WordPress/DB này (nếu là WP riêng thì widget không tìm thấy visit, flow chết từ đầu). → Fix same-origin (e9d5f56) đánh đúng cơ chế: chỉ duy nhất fetch trong iframe captcha đi cross-origin (admin_url) và fail; mọi call khác của widget đi C.api nên sống.
- Không probe được domain từ container (egress bị chặn, exit 56) — kết luận bằng loại trừ.

### Decisions
- Belt thứ 2: widget nhận captcha_success → tự POST token qua ajax() của widget (XHR → C.api/wp-admin/admin-ajax.php, kênh đã chứng minh hoạt động trên mọi domain nhúng). Token Turnstile single-use: iframe verify xong thì call này trả duplicate (vô hại, transient đã set); iframe fetch fail thì call này verify + set transient. Fire-and-forget.
- Iframe hết retry → postMessage captcha_success KÈM token (đảo lại quyết định fail-closed ở e9d5f56): giờ đã có belt 2 nên ưu tiên availability, widget sẽ tự verify qua kênh riêng.
- Thêm Cache-Control no-store + X-LiteSpeed-Cache-Control cho page-widget-captcha: chống page-cache giữ bản HTML cũ (ajaxUrl tuyệt đối) tái nhiễm bug sau deploy.

### Reviewer notes
- Duplicate-call tốn 1 request siteverify về Cloudflare mỗi lượt captcha — chấp nhận (nhẹ, rate 'widget_verify' 30/phút vẫn dư).
- CÁC VISIT CŨ không tự sửa: rows lịch sử vẫn hiện Chưa trả/Captcha vĩnh viễn — chỉ visit MỚI (sau deploy) phản ánh fix. Đã dặn user test bằng visit mới.
- Nếu visit MỚI vẫn dính Captcha ở camp dethito → nghi phạm còn lại: web đích cache widget.js cũ (CDN phía site đích) — cần purge phía đó.

## Summary
**Files changed:**
- `widget.js.php` — captcha_success: re-send token qua kênh ajax() của widget (belt 2)
- `page-widget-captcha.php` — hết retry vẫn gửi token cho parent; no-store headers

**Top items for reviewer to scrutinize:**
1. Duplicate siteverify trả success=false → handler trả captcha_failed cho call belt-2 khi iframe đã verify — callback widget bỏ qua response nên không ảnh hưởng UI.
2. Đổi fail-closed → fail-open-with-token có chủ đích (belt 2 gánh phần verify).

**Test coverage:** php -l sạch 2 file. Cần test thật: visit mới trên camp dethitoanthpt.com phải ra "Đã trả".

## Session 2026-07-09T02:38:22Z — Root cause THẬT của "Hoàn thành + Chưa trả + Captcha" trên camp dethitoanthpt.com: visit cầu nối không bao giờ có bước captcha
**Spec source:** User báo lại (screenshot tab Visits 09/07 09:15–09:23): mọi visit có Nguồn camp = dethitoanthpt.com đều Hoàn thành + KH trả + user Chưa trả, lý do Captcha; camp traffictop.net bình thường
**Branch:** claude/repo-access-check-2yodjl

### Chẩn đoán (thay thế chẩn đoán 2 session trước — chúng SAI)
- dethitoanthpt.com KHÔNG phải domain alias của WordPress này. Nó là WP RIÊNG (repo anhtienhm/dethitoanthpt.com, theme toan-thpt) có hệ traffic riêng (`inc/traffic/`), đẩy camp sang đây qua bridge HMAC (`inc/traffic/lentop-bridge.php` → plugin `ttp-lentop-bridge` cài trên server traffictop.net, nguồn plugin ở repo dethito `bridge/lentop-one/`).
- Trang đích của camp dethito nhúng WIDGET CỦA DETHITO (advertiser là khách của dethito). Widget đó tìm visit của TA qua peer-bridge (`ttp_widget_verify` → `ttp_lentop_widget_verify_any` → REST `lentop/v1/widget` trên ta, HMAC) → phiên `lt:{sid}` → LẤY MÃ proxy server-side → `ttplb_widget_code()` (plugin) cấp mã bằng `traffictop_generate_visit_verify_code()` + `ttplb_core_set_ready()` set transients `{lentop_,trafficop_,traffictop_}widget_code_ready_{sid}` / `*verify_code_{sid}`.
- Trong flow đó KHÔNG TỒN TẠI widget của ta trên trang đích → iframe captcha Turnstile (`page-widget-captcha.php`) KHÔNG BAO GIỜ chạy → `traffictop_captcha_ok_{sid}` không bao giờ được set → cổng captcha (shortlink-verification.php:223) chặn thưởng: `captcha_unverified`. Khớp 100% hiện tượng: chỉ camp dethito dính, camp traffictop (widget ta, có iframe captcha) bình thường.
- 3 fix trước (same-origin iframe e9d5f56, TTL 7200, belt-2 2e4f9c3) đều sửa widget/iframe CỦA TA — thứ không hề xuất hiện trong flow này → không có tác dụng. Bằng chứng quyết định: visit 09:15–09:23 (SAU deploy belt-2) vẫn dính.

### Decisions
- Fix tại CỔNG CAPTCHA của theme (shortlink-verification.php:219-230): miễn captcha cho visit có mã cấp QUA CẦU NỐI. Marker nhận diện: transient `lentop_widget_code_ready_{sid}` HOẶC `trafficop_widget_code_ready_{sid}` — 2 tiền tố này CHỈ được ghi bởi plugin bridge (`ttplb_core_set_ready`, kênh HMAC server-side); toàn theme không có chữ `lentop`/`trafficop_` nào (đã grep) → client không thể giả marker.
- TTL marker = TTL mã (600s, set cùng thời điểm). Verify vốn đã bắt buộc `traffictop_verify_code_{sid}` còn sống (code_expired nếu không) → tại thời điểm qua cổng captcha, marker chắc chắn chưa hết hạn — không có false-negative do lệch TTL.
- Belt tương lai ở PLUGIN (repo dethito, `bridge/lentop-one/ttp-lentop-bridge.php`): `ttplb_core_set_ready()` set thêm `{prefix}captcha_ok_{sid}` TTL 7200 + bump 1.1.27. KHÔNG bắt buộc cho fix (theme fix tự đủ với plugin production hiện tại — nó đã set marker lentop_/trafficop_ sẵn) nhưng tài liệu hoá contract "mã qua cầu = miễn captcha" tại chính điểm cấp mã. Plugin cài TAY trên traffictop.net — chỉ có hiệu lực khi user cập nhật plugin, không auto-deploy.
- KHÔNG đổi money policy (KH vẫn bị trừ như cũ); fix chỉ gỡ oan phần thưởng user cho visit cầu nối.

### Tradeoffs
| Phương án | Ưu | Nhược | Chọn |
|---|---|---|---|
| Miễn captcha theo marker transient của bridge (theme) | Auto-deploy, per-visit, không giả được từ client, không đụng schema | Visit cầu nối không có proof-of-human Turnstile (vốn dĩ chưa từng có) | **Có** |
| Miễn theo chủ camp = admin (như cột Nguồn camp) | Không phụ thuộc transient | Heuristic rộng (mọi camp admin tạo đều thoát captcha), sai nếu admin tạo camp nội bộ | Không |
| Bắt widget dethito chạy captcha của ta cho phiên lt: | Giữ Turnstile end-to-end | Sửa lớn cross-system 2 repo + UX đổi với khách dethito; cần bàn riêng | Không (đề xuất sau) |

### Reviewer notes
- Bảo mật: visit cầu nối chưa bao giờ có captcha; trước gate (e499955) chúng vẫn được trả thưởng bình thường → fix này KHÔI PHỤC hành vi cũ cho riêng nhóm cầu nối, không nới lỏng hơn quá khứ. Bot muốn lợi dụng vẫn phải đi qua time-gate của plugin bridge (anchor onsite) + toàn bộ check khác của verify (IP limit, ip_changed, bypass, daily limit).
- Nếu sau này muốn Turnstile cho cả camp cầu nối: làm ở widget dethito (mở iframe `https://traffictop.net/widget-captcha/?sid=` cho phiên `lt:`) — đã ghi ở Tradeoffs.
- Test: suite này là logic-replica (bootstrap không mock transient) → thêm decision-table test cho cổng captcha trong test-security-checks.php.

## Summary
**Files changed:**
- `includes/shortlink-verification.php` — cổng captcha nhận thêm marker mã-cầu-nối (`lentop_/trafficop_widget_code_ready_{sid}`) → visit camp dethitoanthpt.com không còn bị 'captcha_unverified' oan
- `tests/unit/test-security-checks.php` — decision-table 4 case cho cổng captcha (captcha_ok × bridged)
- (repo dethitoanthpt.com) `bridge/lentop-one/ttp-lentop-bridge.php` — `ttplb_core_set_ready()` set thêm `{prefix}captcha_ok_{sid}` TTL 7200, bump 1.1.27 — belt tương lai, cần cập nhật plugin TAY trên traffictop.net mới hiệu lực

**Top items for reviewer to scrutinize:**
1. Theme fix tự đủ với plugin production HIỆN TẠI chỉ khi plugin đó đã set marker lentop_/trafficop_ (`ttplb_core_set_ready` đa tiền tố). Suy luận: camp cầu nối hoàn thành được trên traffictop ⟺ plugin đã set `traffictop_widget_code_ready_` (verify bắt buộc) ⟺ cùng hàm đó set lentop_/trafficop_ → marker chắc chắn có. Nếu sản xuất chạy bản plugin dị bản chỉ set traffictop_* thì marker trượt → cần cập nhật plugin (đã có sẵn trong repo).
2. Visit cầu nối được miễn Turnstile (chưa từng có trong flow đó) — bot đi đường bridge vẫn vướng time-gate anchor của plugin + IP limit/bypass/daily-limit của verify.
3. Test suite là logic-replica — không bắt được regression nếu ai đó đổi tên transient marker trong theme/plugin.

**Open questions:**
- Có muốn Turnstile end-to-end cho camp cầu nối không (sửa widget dethito mở iframe captcha của traffictop cho phiên `lt:`)? Đề xuất để sau, cần user quyết.

**Test coverage:**
- php -l sạch 3 file; unit 15/0 (3 suites, +4 test mới). Không test được transient thật/flow bridge thật (cần 2 WP + plugin).

## Session 2026-07-09T03:11:53Z — "Code chưa sẵn sàng" trên camp cầu nối dethitoanthpt.com: cứu mã ngay trong theme
**Spec source:** User screenshot — page-unlock traffictop.net/eCvp0h/ báo "Code chưa sẵn sàng" dù widget (dethito, nút THPT trên vuacuacuon.com) đã cấp mã F8B3793D; camp thuộc dethitoanthpt.com
**Branch:** claude/repo-access-check-2yodjl

### Chẩn đoán
- "Code chưa sẵn sàng" = shortlink-verification.php:91 — transient `traffictop_widget_code_ready_{sid}` không tồn tại cho PHIÊN NGƯỜI NHẬP (V_A), tức mã đã cấp nhưng gắn vào chỗ khác/mất transient.
- Với camp cầu nối, mã do plugin ttp-lentop-bridge cấp (`ttplb_widget_code`). Khi session-match trượt (ITP chặn iframe /widget-bridge/ đọc localStorage bên thứ 3) và IP-match trượt (dual-stack: visit tạo qua IPv6, widget→dethito→bridge mang IPv4), pool rơi vào DOMAIN-fallback 15' → mã bind vào visit KHÁC (V_X) cùng campaign. Ngoài ra transient 600s có thể hết hạn/rớt object-cache dù DB còn mã.
- Plugin đã có patch cứu (`ttplb_promote_code`, hook priority 1 trên `wp_ajax_traffictop_verify_shortlink_code`, field `session_id`/`code` — ĐÃ ĐỐI CHIẾU khớp theme). Nhưng sự cố vẫn xảy ra ⇒ bản plugin cài trên production có thể cũ (thiếu promote/hook fork), hoặc rơi case promote không cứu (mất map `ttplb_map` với camp mirror trùng, quá cửa sổ 30', mã nằm ở POOL KHÁC).

### Decisions
- Port promote vào THEME (`traffictop_bridge_rescue_code`, gọi đầu verify_and_pay trước age/time/code_ready check): theme auto-deploy, không phụ thuộc phiên bản plugin. Điều kiện + hành vi GIỮ NGUYÊN plugin: cùng campaign, chưa verified/chưa trả, khớp mã (case-insensitive), cửa sổ 30'; chuyển verify_code + from_google=1 + url_matched=1 + step + created_at sang V_A.
- Nhận diện camp cầu nối KHÔNG phụ thuộc plugin: title prefix `[host#ref]` (gắn cố định lúc tạo job, update không đổi title) OR campaign_id ∈ option `ttplb_map`. → cứu được cả camp mirror mồ côi map.
- Re-arm transient đủ CẢ 3 tiền tố (lentop_/trafficop_/traffictop_) như `ttplb_core_set_ready` — giữ luôn marker miễn-captcha (nếu chỉ arm traffictop_* thì visit được cứu sẽ dính lại 'captcha_unverified').
- Đặt rescue TRƯỚC dòng tính $elapsed (line 53) vì rescue chuyển created_at → age check (visit_expiry 600s) phải dùng mốc mới, giống thứ tự plugin (priority 1 chạy trước cả handler).
- KHÔNG sửa vòng precise của dethito verify_any (đề phòng nhầm pool): phân tích cho thấy chỉ peer khớp peer_host mới nhận session nên "ưu tiên session giữa các pool" là no-op; fix thật cần peerBridge thu session TẤT CẢ pool (đổi widget JS dethito) — speculative, không có bằng chứng sự cố này là cross-pool → để lại, ghi nhận residual.

### Reviewer notes
- Rescue giữ tradeoff của plugin: mã bind nhầm phiên người khác (V_X của P2) vẫn chuyển cho người nhập (P1) — guard tiền (FOR UPDATE, reward_paid, IP/daily limit recheck) chặn double-pay như trước.
- Phát hiện trong lúc đọc: age check verify dùng visit_expiry = verify_code_expiry (600s), KHÔNG phải 7200s như CLAUDE.md Flow 1 ghi (Line 279 max 7200) — CLAUDE.md lỗi thời, chưa sửa (ngoài scope).
- Residual đã biết: nếu mã do POOL KHÁC cấp (lentop.one) thì rescue trên traffictop không thấy mã trong DB → vẫn "Code chưa sẵn sàng". Chữ ký nhận diện: mã khách nhập tồn tại trong visits của pool kia. Nếu tái diễn → nâng cấp peerBridge widget dethito (thu session mọi pool).

## Summary
**Files changed:**
- `includes/shortlink-verification.php` — helper `traffictop_is_bridge_campaign` + `traffictop_bridge_rescue_code`; gọi rescue đầu verify_and_pay (trước age/time/code_ready); SELECT thêm kc.title
- `tests/unit/test-security-checks.php` — 3 test regex nhận diện title job cầu nối

**Top items for reviewer to scrutinize:**
1. Vị trí gọi rescue TRƯỚC age check: rescue chuyển created_at của V_X (mới reset lúc LẤY MÃ) sang V_A → phiên già hơn visit_expiry vẫn cứu được (đúng hành vi promote của plugin — chạy hook priority 1 trước handler).
2. Rescue force from_google=1/url_matched=1 — semantics cầu nối sẵn có (ttplb_widget_verify cũng set vậy), chỉ trong phạm vi camp cầu nối.
3. Query sibling không loại chính V_A → tự cứu case transient rớt/hết hạn (V_A giữ mã trong DB).

**Open questions:**
- Sự cố cụ thể trên screenshot có thể do mã cấp bởi POOL KHÁC (lentop.one) — rescue này không thấy mã trong DB traffictop thì vẫn báo lỗi. Cần user test lại; nếu tái diễn thì nâng peerBridge (widget dethito) thu session mọi pool.

**Test coverage:** php -l sạch; unit 18/0 (3 suites). Rescue SQL/transient không unit-test được (MockWpdb).

## Session 2026-07-09T03:40:38Z — traffictop.net THIẾU /widget-bridge/: bổ sung endpoint cấp session cho widget đối tác
**Spec source:** User retest vẫn "Code chưa sẵn sàng" sau khi update plugin v1.1.27 cả 2 pool; yêu cầu: visit của pool nào thì mã phải do pool đó sinh
**Branch:** claude/repo-access-check-2yodjl

### Chẩn đoán (bằng chứng code, đã loại trừ deploy — 4 commit fix đều trong main)
- Widget dethito trên trang đích đọc session của TỪNG pool qua iframe `{pool}/widget-bridge/`. lentop.one CÓ endpoint này (page-widget-bridge.php + route); **traffictop.net KHÔNG CÓ** (không file, không route — grep toàn theme).
- Hệ quả: widget dethito không bao giờ có session traffictop → khớp traffictop chỉ còn đường IP (trượt khi dual-stack v4/v6) → rơi fallback domain/bị lentop (pool duy nhất có session) hoặc visit nội bộ dethito "vơ" mất → mã sinh từ SAI hệ thống → nhập trên traffictop "Code chưa sẵn sàng" (mã không tồn tại trong DB traffictop nên mọi lớp cứu local vô hiệu). Visit không hoàn thành → không postback → bảng giờ dethito cũng không có dòng mới (giải thích luôn "giờ vẫn chưa khớp").

### Decisions
- Thêm `page-widget-bridge.php` + route (mirror lentop, kèm `exit` sau include như lentop). postMessage `{type:'ln_session', sid}` — đúng shape peerBridge dethito đang nghe.
- sid lấy 2 nguồn: localStorage `tn_unlock_session` (JS, như lentop) + **fallback cookie `traffictop_sid`** (echo server-side — cookie SameSite=None đã có sẵn từ bài học 13/04). Cookie sống được cả khi browser PHÂN VÙNG localStorage bên thứ ba (Chrome storage partitioning / Safari ITP) — chính là lý do session-match hay trượt.
- **Cache-Control: no-store** (KHÁC lentop đang cache public 1h): vì echo cookie làm HTML per-visitor — cache CDN sẽ phát tán sid của người này cho người khác. An toàn > tải origin.
- Validate cookie 32-hex trước khi echo (chống XSS/injection qua cookie).

### Reviewer notes
- lentop.one không có cookie sid (fork cũ) → bridge của nó giữ nguyên; nếu muốn đồng bộ sau này phải thêm cả setcookie ở page-unlock của lentop + đổi cache header.
- Phần chọn-đúng-pool theo bậc độ mạnh (session > IP > domain, xuyên hệ thống) nằm ở widget dethito (repo dethitoanthpt.com, cùng đợt fix này).

## Summary
**Files changed:**
- `page-widget-bridge.php` — MỚI: cấp sid qua postMessage (localStorage tn_unlock_session + fallback cookie traffictop_sid, no-store)
- `functions.php` — route /widget-bridge/ (include + exit, mirror lentop)
- (repo dethitoanthpt.com) `inc/traffic/widget.php` + `lentop-bridge.php` — peerBridge thu session MỌI pool; verify theo bậc session > IP > domain xuyên hệ thống (helper `ttp_lentop_widget_verify_collect` dùng field `match` của plugin)

**Top items for reviewer to scrutinize:**
1. no-store trên bridge page là BẮT BUỘC (echo cookie per-visitor) — nếu ai đổi sang cache public sẽ rò sid giữa các khách qua CDN.
2. Widget dethito đổi thứ tự: session nội bộ giờ đứng TRÊN IP nội bộ (trước đây IP trước) — đúng hơn về nguyên tắc nhưng là thay đổi hành vi cho cả flow nội bộ dethito.
3. Vòng precise giờ hỏi TẤT CẢ pool khi chưa có session-match (trước dừng ở pool found đầu tiên) → +1 call server-side trong ca xấu; đổi lại đúng pool.

**Open questions:**
- lentop.one chưa có cookie sid — session channel của nó vẫn chết khi browser phân vùng localStorage; nếu cần thì thêm setcookie ở page-unlock lentop + widget-bridge no-store (đổi cache header).

**Test coverage:** php -l sạch; unit 18/0. Flow iframe/postMessage/cookie cross-origin không unit-test được — cần test thật sau deploy.

## Session 2026-07-10T03:05:28Z — Cho admin chỉnh "Giá/view (KH trả)": camp Chờ duyệt (edit modal) + form Tạo chiến dịch cho khách hàng
**Spec source:** User request + 2 screenshots của wp-admin `admin.php?page=traffictop-campaigns` (edit modal camp #99 và form "Tạo chiến dịch cho khách hàng")
**Branch:** claude/campaign-price-view-edit-96wqtt

### Bối cảnh đã xác minh trước khi code
- UI campaigns admin nằm TRỌN trong `includes/admin/tabs/tab-campaigns.php` (menu page `traffictop-campaigns`, functions.php:537). `page-admin-dashboard.php` KHÔNG còn tồn tại — CLAUDE.md lỗi thời ở mục đó.
- Billing thật lấy `kc.price_per_view` từ bảng keyword_campaigns tại thời điểm verify (shortlink-verification.php:110,368) → giá trên campaign row là source of truth tài chính; `orders.price_per_task`/`total_amount` chỉ ghi lúc tạo, không dùng để trừ tiền.
- `traffictop_approve_campaign()` (campaign-management.php:8) KHÔNG recalc giá khi duyệt → giá custom sống sót qua approve.
- Handler `traffictop_ajax_admin_update_campaign()` (admin-dashboard.php:147): nếu POST có `traffic_type` (JS modal LUÔN gửi) → recalc price/reward từ settings và ghi đè, TRỪ KHI POST đã có `price_per_view`/`user_reward`. `traffictop_update_campaign()` whitelist đã cho phép `price_per_view` (%f).

### Decisions
- **Giá custom chỉ nhận cho camp `pending`** (đúng yêu cầu user): gate ở SERVER trong `traffictop_ajax_admin_update_campaign()` — POST `price_per_view` bị unset nếu camp không phải pending hoặc giá ≤ 0. JS cũng chỉ gửi giá khi status=pending (client + server cùng chặn).
- **Sửa auto-recalc: chỉ recalc price/reward khi traffic_type HOẶC onsite_time THẬT SỰ đổi** (so với DB), thay vì mọi lần lưu như cũ. Lý do: hành vi cũ sẽ RESET giá custom về giá settings ở bất kỳ lần sửa nào sau đó (kể cả chỉ sửa URL/daily) → feature giá custom thành vô dụng sau 1 lần edit. Đổi TT/onsite thì giá vẫn recalc như cũ (giá đi theo loại).
- Edit modal: `#admEditPrice` div display → `<input type=number>`; mở modal hiển thị GIÁ ĐANG LƯU trong DB (trước đây hiển thị giá tính từ settings — có thể sai với giá thực). readOnly khi status ≠ pending. Đổi TT/onsite → JS điền lại giá gợi ý từ settings (admin có thể gõ đè tiếp).
- `#admEditReward` (User nhận/view) hiển thị reward ĐANG LƯU khi mở modal (trước đây tính từ settings) — nhất quán với giá; reward KHÔNG editable, KHÔNG suy từ giá custom (giữ nguyên scheme settings).
- Form tạo: bỏ `readonly` khỏi `#adm_price`, `oninput` cập nhật ước tính chi phí; `admUpdatePrice()` vẫn ghi đè giá gợi ý khi đổi loại dịch vụ/loại traffic/onsite (semantics "gợi ý, cho phép gõ đè").
- Server create (tab-campaigns.php POST `create`): thêm validate `price_per_view > 0` (field giờ editable → gõ nhầm/để trống sẽ thành camp 0đ silent). Báo lỗi thay vì âm thầm nhận 0/âm.
- Sync `customer_orders.price_per_task` khi giá custom được áp (mirror pattern customer-side customer-campaign-ajax.php:382). KHÔNG đụng `total_amount` (admin update path xưa nay không sync khi đổi quantity — drift có sẵn, ngoài scope).
- Cảnh báo (không chặn) trên cả 2 form khi giá KH trả < user reward (nền tảng lỗ/view) — admin có thể cố ý (camp nội bộ) nên chỉ warning đỏ nhỏ.

### Deviations from spec
- User chỉ yêu cầu "chỉnh giá cho camp Chờ duyệt" — nhưng nếu giữ auto-recalc-mọi-lần-lưu thì giá custom bị xoá ngầm ở lần sửa kế tiếp (sau khi approve). Đã đổi thành recalc-khi-TT/onsite-đổi (ghi ở Decisions) — hệ quả phụ: sửa camp mà không đổi TT/onsite sẽ KHÔNG còn tự "làm tươi" giá theo settings mới (trước đây có, dù không ai chủ đích dùng).

### Reviewer notes
- Customer tự sửa camp của họ (customer-campaign-ajax.php:356-371) LUÔN recalc giá từ settings → nếu KH sửa camp pending sau khi admin đặt giá custom, giá custom bị ghi đè. Ngoài scope (đổi UX phía KH cần bàn riêng); admin nên duyệt sớm sau khi chốt giá.
- Reward giữ nguyên khi đổi giá — nếu admin hạ giá dưới reward thì nền tảng bù lỗ; chỉ warning, không chặn (có thể là chủ đích cho camp nội bộ admin).

## Summary
**Files changed:**
- `includes/admin-dashboard.php` — `traffictop_ajax_admin_update_campaign()`: gate giá custom (chỉ pending, >0), recalc settings chỉ khi TT/onsite đổi, sync `orders.price_per_task` khi giá đổi
- `includes/admin/tabs/tab-campaigns.php` — form tạo: input giá editable + validate server >0 + warning lỗ; edit modal: giá thành input (editable khi pending, hiển thị giá đang lưu), hint + warning, JS gửi price_per_view khi pending
- `tests/unit/test-campaign-price-edit.php` — MỚI: decision-table 14 case cho gate giá + điều kiện type_changed (logic-replica)

**Top items for reviewer to scrutinize:**
1. Đổi hành vi recalc: lưu camp mà KHÔNG đổi TT/onsite giờ GIỮ NGUYÊN price/reward trong DB (trước đây luôn làm tươi theo settings hiện hành). Cần thiết để giá custom không bị xoá ngầm, nhưng là thay đổi hành vi cho MỌI camp (kể cả active).
2. Sync `orders.price_per_task` giờ chạy cả khi recalc đổi giá camp active (trước đây order giữ giá cũ) — nhất quán hơn nhưng là hành vi mới.
3. Modal hiển thị giá/reward ĐANG LƯU thay vì giá tính từ settings — nếu ai đó dựa vào modal để "xem giá theo settings mới" thì hành vi đổi.

**Open questions:**
- Customer sửa camp pending vẫn recalc giá từ settings (ghi đè giá custom của admin) — có muốn chặn/khóa giá phía customer sau khi admin chỉnh không? Cần user quyết, chưa đụng.

**Test coverage:**
- php -l sạch 2 file PHP; div balance 102/102; unit 32/0 (4 suites, +14 test mới). Không test được flow AJAX/DOM thật (MockWpdb, không có WP instance) — cần bấm thử trên production sau deploy.

## Session 2026-07-10T03:33:19Z — Trang Cài đặt không nhập được giá lẻ (VD 1680): gỡ step HTML trên field tiền
**Spec source:** User screenshot — `admin.php?page=traffictop-settings`, browser báo "Hai giá trị hợp lệ gần nhất là 1600 và 1700" khi nhập 1680
**Branch:** claude/campaign-price-view-edit-96wqtt

### Decisions
- Nguyên nhân: HTML5 `step` validation (`step="100"` trên 12 field giá/reward; `step="50"` onsite extra; `step="1000"` min tài chính + referral payout; `step="100000"` mức nạp nhanh) — browser chặn submit mọi giá trị không nằm trên lưới step. Server không làm tròn gì (`sanitize_text_field`), nên chỉ cần gỡ ở client.
- Đổi 30 field TIỀN về `step="1"` (nhận mọi số nguyên). GIỮ `step="60"` cho 2 field thời gian tính giây (`verify_code_expiry`, `ddos_block_duration`) — step theo phút là chủ đích, giá trị lưu chỉ đến từ chính form này nên luôn là bội của 60.

### Reviewer notes
- Spinner mũi tên giờ tăng/giảm 1đ thay vì 100đ — đánh đổi chấp nhận được để gõ tay giá tùy ý (spinner gần như không dùng cho field tiền).

## Summary
**Files changed:**
- `includes/admin/tabs/tab-settings.php` — 30 field tiền: step 100/50/1000/100000 → 1

**Test coverage:** php -l sạch. Validation là hành vi browser — cần thử nhập 1680 và Lưu trên production.

## Session 2026-07-13T11:36:56Z — Port page-unlock federated: hiện đúng nút widget của site NGUỒN cho camp cầu nối
**Spec source:** lentop.one commits `7690517` (page-unlock bước 3: nút widget của nguồn) + `efdb254` (default icon/chữ theo nguồn) — port trạng thái CUỐI (gộp 2 commit)
**Branch:** claude/repo-access-check-b6ravp

### Bối cảnh đã xác minh trước khi code
- Camp cầu nối (đẩy từ dethitoanthpt.com/hoclaixe.io): khách làm nhiệm vụ lấy mã bằng nút widget của SITE NGUỒN trên trang đích (nút TRÒN cố định giữa-phải), nhưng page-unlock của traffictop đang vẽ preview nút chữ nhật theo config traffictop → khách tìm sai nút.
- Plugin `ttp-lentop-bridge` (chạy trên cả 2 pool, nguồn ở dethito repo `bridge/lentop-one/`) đã lưu style nút nguồn theo campaign (`ttplb_widget_style[cid]`, lưu lúc nhận job) + getter `ttplb_current_widget_style()` (resolve campaign từ global $campaign / $_SESSION — `ttplb_sess()` biết cả 3 tiền tố lentop_/trafficop_/traffictop_) → theme chỉ cần đọc.
- 3 khối "tìm nút" trong page-unlock.php của traffictop (keyword Step 4 ~dòng 715 · direct Step 2 ~dòng 750 · social Step 3 ~dòng 833) byte-giống lentop TRƯỚC port → port thẳng được.
- Các commit lentop còn lại KHÔNG cần port: `ba3a389` (cột "Nguồn camp") gốc từ traffictop; `f25a16c` (BRIDGE-LESSONS docs) đã có; `62d045e` (${var} deprecated + bảng comments) — traffictop không có ${var} nào (đã grep).

### Decisions
- Đặt tên biến `$traffictop_step_intro` / `$traffictop_step_btn` (lentop dùng `$lentop_step_*`) — theo prefix của theme này.
- Port TRẠNG THÁI CUỐI của 2 commit (efdb254 sửa hành vi của 7690517): camp federated → ghi ĐÈ $widget_* bằng giá trị nguồn + DEFAULT NGUỒN ('LẤY MÃ' / #0D4F4F / #ffffff / icon rỗng→SVG hộp quà) thay vì fallback về config traffictop — tránh lẫn icon/màu traffictop khi nguồn để trống.
- Guard `function_exists('ttplb_current_widget_style')`: plugin vắng/cũ trên server traffictop → null → giữ nguyên UI cũ (fallback an toàn, không đổi hành vi camp nội bộ).
- Fed-note cuối minh hoạ: lentop ghi "bấm **Mở khoá**" (khớp nút MỞ KHOÁ của lentop) — traffictop nút unlock là **TIẾP TỤC** (`page-unlock.php` #btn-unlock) → đổi chữ trong note thành "TIẾP TỤC" cho khớp UI thật. Đây là điểm DUY NHẤT khác nguyên văn lentop (ngoài prefix biến + chữ "traffictop" trong comment).

### Reviewer notes
- Getter `ttplb_current_widget_style()` nằm ở PLUGIN ttp-lentop-bridge (repo dethito `bridge/lentop-one/`, ~line 1526) — plugin trên server traffictop phải là bản có getter thì nút nguồn mới hiện; plugin cũ → `function_exists` fail → tự về UI cũ (không vỡ gì). Plugin tự nhận diện core traffictop (`ttplb_known_cores`: lentop_/trafficop_/traffictop_).
- Getter còn gọi `ttplb_maybe_signal_started()` (báo "Đang làm" sớm về nguồn, 1 lần/phiên, non-blocking) — side-effect có sẵn của plugin, giống hành vi trên lentop, không phải do port này thêm.
- KHÔNG đụng `widget.js.php` / logic show-hide / verify / tiền — chỉ HTML+CSS phần hướng dẫn trong page-unlock.php (đúng phạm vi 2 commit gốc).
- Khối keyword của traffictop là **Step 4** (lentop là Step 3) — intro dùng chung không nhắc số bước nên vô hại.

### Verification
- `php -l` sạch; div balance toàn file 127/127 (trước sửa cũng cân bằng).
- Diff block mới vs lentop (scratchpad): chỉ khác đúng 5 điểm chủ đích (prefix biến, "lentop"→"traffictop"/"nguồn" trong comment, "bước 3"→"bước tìm nút", "Mở khoá"→"TIẾP TỤC").
- Render-test harness (stub get_option/esc_*/ttplb_current_widget_style, chạy fragment TRÍCH TỪ FILE THẬT): ① plugin trả null → giữ preview cũ + config traffictop nguyên vẹn; ② fed style rỗng → default nguồn (#0D4F4F, "LẤY MÃ", SVG hộp quà) — KHÔNG lẫn config traffictop; ③ fed style riêng → màu/chữ/icon nguồn. Cả 3 case div cân bằng.

## Summary
**Files changed:**
- `page-unlock.php` — camp cầu nối: bước "tìm nút" vẽ đúng nút widget TRÒN cố định giữa-phải của site NGUỒN (đọc `ttplb_current_widget_style()`, default theo nguồn); 3 khối duplicate (keyword Step 4 / direct Step 2 / social Step 3) gom về `$traffictop_step_intro` + `$traffictop_step_btn`; thêm CSS `.fed-*`

**Top items for reviewer to scrutinize:**
1. Ghi ĐÈ `$widget_*` toàn cục khi camp là federated — các chỗ khác của page-unlock dùng `$widget_color/$widget_icon` (CSS `.widget-btn-preview` line ~335) sẽ ăn theo màu nguồn cho camp cầu nối. Giống hành vi lentop (chủ đích), nhưng là thay đổi hành vi hiển thị.
2. Phụ thuộc version plugin ttp-lentop-bridge trên server traffictop (cần bản có `ttplb_current_widget_style`) — nếu chưa cập nhật plugin, tính năng im lặng không kích hoạt (an toàn nhưng "không thấy gì").

**Open questions:**
- Không có — port 1-1 từ lentop đã chạy production.

**Test coverage:**
- php -l + div balance + diff-vs-lentop + render-test 3 case trên fragment thật (stub WP). Không test được trên WP thật trong build này — cần mở 1 camp cầu nối trên traffictop.net sau deploy để nhìn bước 3/4.

## Session 2026-07-13T12:57:10Z — Fix: nút nguồn KHÔNG hiện trên traffictop (plugin cũ) → fallback nhận diện bằng tiền tố tiêu đề
**Spec source:** User screenshots — traffictop.net/BdySPA/ (bước 4 vẫn preview cũ "SEO TFT") vs ảnh mong muốn (fed-screen nút HLX, chính là render của lentop cho camp cầu nối hoclaixe)
**Branch:** claude/repo-access-check-b6ravp

### Chẩn đoán
- Plugin ttp-lentop-bridge bản MỚI đăng ký `pre_option_{lentop_,trafficop_,traffictop_}widget_*` filter (ttp-lentop-bridge.php:~1611) → nếu plugin mới chạy trên traffictop, NGAY CẢ UI CŨ cũng đã hiện màu/chữ nguồn. Ảnh 1 hiện icon+chữ "TFT" của traffictop → filter không chạy → **plugin trên server traffictop là bản CŨ** (chưa có storage/getter/filter widget style) hoặc style camp chưa được lưu.
- `function_exists('ttplb_current_widget_style')` = false → code port hôm nay rơi về preview cũ (đúng thiết kế fallback an toàn, nhưng user muốn camp cầu nối phải ra fed-screen).

### Decisions
- Thêm FALLBACK theme-side không phụ thuộc version plugin: nhận diện camp cầu nối bằng tiền tố tiêu đề `[host#ref]` — marker bền nhất (BRIDGE-LESSONS §11), cùng regex theme đang dùng (`shortlink-verification.php:22`, `tests/unit/test-security-checks.php:21`). Match → đọc thẳng `get_option('ttplb_widget_style')[cid]` (nếu plugin đời mới đã lưu) ; chưa có → default NGUỒN (LẤY MÃ/#0D4F4F/hộp quà).
- Pad `$fed_widget += ['text'=>'','color'=>'','tcolor'=>'','icon'=>'']` để mảng LUÔN non-empty → `if ($fed_widget)` truthy (mảng rỗng PHP là falsy — không pad sẽ rơi nhầm về UI cũ).
- KHÔNG đổi UI camp nội bộ (giữ preview cũ) — ảnh 2 là minh hoạ dành riêng camp cầu nối, khớp hành vi lentop mà user đã duyệt.

### Reviewer notes
- Khi plugin cũ + style chưa lưu: fed-screen hiện với DEFAULT nguồn (nút xanh #0D4F4F "LẤY MÃ" hộp quà), CHƯA phải style thật (vd nút đỏ HLX). Muốn style thật: cập nhật plugin ttp-lentop-bridge trên server traffictop.net → nguồn đẩy/refresh job sẽ lưu `ttplb_widget_style[cid]` → tự hiện đúng.
- `empty($campaign->id)` / `$campaign->title ?? ''` an toàn khi $campaign null (isset-semantics, không warning).

### Verification (fix fallback)
- php -l sạch; div 127/127.
- Render-test 5 case trên fragment trích từ file thật: ① plugin cũ + prefix + chưa lưu style → FED default nguồn (#0D4F4F/LẤY MÃ) ② plugin cũ + prefix + option đã lưu → FED style thật (HLX/#b23b2e) ③ plugin cũ + camp nội bộ → UI cũ, config traffictop nguyên vẹn ④ campaign null → UI cũ, không warning ⑤ plugin mới (getter) → FED style thật (regression OK).

## Summary (phiên fix)
**Files changed:**
- `page-unlock.php` — thêm fallback nhận diện camp cầu nối bằng tiền tố tiêu đề `[host#ref]` + đọc thẳng option `ttplb_widget_style` khi plugin cũ/vắng getter

**Top items for reviewer:**
1. Camp cầu nối trên plugin cũ giờ hiện fed-screen với DEFAULT nguồn (xanh #0D4F4F "LẤY MÃ") — style thật (vd nút đỏ HLX) chỉ hiện sau khi cập nhật plugin ttp-lentop-bridge trên server + nguồn refresh job.
2. Regex prefix khớp mọi title bắt đầu `[host#số]` — camp nội bộ đặt tên kiểu này (hi hữu) sẽ bị nhận nhầm là cầu nối.

**Test coverage:** render-test 5 case như trên; chưa nhìn được trên WP production — cần mở lại trafictop.net/BdySPA/ sau deploy.
