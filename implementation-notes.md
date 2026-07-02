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
