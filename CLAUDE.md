# Project Rules for AI Assistants

> Đây là file hướng dẫn bắt buộc cho AI khi làm việc với dự án này.

## DO NOT TOUCH - KHÔNG ĐƯỢC SỬA

### Widget Show/Hide Logic
**KHÔNG ĐƯỢC thay đổi** bất kỳ logic ẩn/hiện widget nào trong `widget.js.php` và các file liên quan.
Các logic này đã hoạt động ổn định, bao gồm:
- Logic hiển thị/ẩn widget trên trang
- Logic toggle widget visibility
- CSS/JS điều khiển show/hide widget
- Bất kỳ condition nào quyết định widget có hiển thị hay không

> Nếu có bug liên quan đến ẩn/hiện widget → **HỎI USER TRƯỚC**, không tự ý sửa.

## CRITICAL SAFETY RULES

### 1. Destructive Commands - PHẢI HỎI TRƯỚC
**KHÔNG BAO GIỜ** tự ý thực thi các lệnh sau mà không hỏi user:
- `rm -rf`, `rm -r`, `rmdir` - Xóa file/folder
- `DROP TABLE`, `DROP DATABASE` - Xóa bảng/database
- `TRUNCATE TABLE` - Xóa toàn bộ data trong bảng
- `DELETE FROM ... WHERE` - Xóa records
- `git push --force` - Force push
- `git reset --hard` - Hard reset

### 2. Quy trình khi cần xóa/sửa data
1. **Liệt kê** những gì sẽ bị ảnh hưởng
2. **Hỏi user** xác nhận
3. **Chờ user đồng ý** rồi mới thực thi
4. **Backup** nếu cần thiết

### 3. Khi đọc code
- Luôn đọc file `docs/SHORTLINK-SYSTEM.md` trước khi sửa logic liên quan đến shortlink/campaign
- Hiểu rõ flow trước khi thay đổi
- Không sửa code mà chưa hiểu tác động

## DEPLOYMENT

### Pipeline: Claude Code → GitHub → Production Server
```
Claude Code (sửa code)
    │
    ▼ git push -u origin claude/<branch-name>
GitHub (branch claude/*)
    │
    ▼ GitHub Actions (.github/workflows/auto-merge-claude.yml)
GitHub (branch main) ← auto-merge từ claude/* branch, rồi xóa branch claude/*
    │
    ▼ GitHub Webhook → deploy-webhook.php trên server
Production Server ← git pull --ff-only origin main + opcache_reset()
```

### Chi tiết kỹ thuật
1. **Push** lên branch `claude/*` → GitHub Actions tự merge vào `main` rồi xóa branch `claude/*`
2. **Webhook** (`deploy-webhook.php`): GitHub gửi push event → server chạy `git pull --ff-only origin main`
3. **Server** phải ở branch `main` (KHÔNG phải detached HEAD) — nếu bị detached → fix: `git checkout -B main origin/main`
4. **OPcache**: `deploy-webhook.php` gọi `opcache_reset()` sau khi pull để website dùng code mới

### Workflow file
- **File:** `.github/workflows/auto-merge-claude.yml`
- **Trigger:** push to `claude/**` or `claude/*`
- **Steps:** checkout main → merge claude branch → push main → delete claude branch

### Webhook file
- **File:** `deploy-webhook.php` (trong theme root)
- **URL:** Cấu hình trong GitHub Settings → Webhooks
- **Secret:** `linkngon-deploy-2026`
- **Repo path trên server:** `/home/cogfjvaa/traffictop.net/wp-content/themes/traffictop-theme`
- **Lệnh deploy:** `git pull --ff-only origin main`

### Lưu ý quan trọng
- **KHÔNG BAO GIỜ** yêu cầu user chạy `git pull` trên cPanel Terminal
- **KHÔNG BAO GIỜ** cung cấp lệnh SSH hay Terminal cho user
- Nếu cần server cập nhật code mới nhất mà chưa có commit nào mới → tạo 1 commit nhỏ (trigger deploy) rồi push
- User có thể dùng Claude Code từ **điện thoại** → không có Terminal
- **KHÔNG sửa deploy-webhook.php** trừ khi deploy bị hỏng — file này tự deploy chính nó nên phải cẩn thận
- Webhook trả 2 delivery mỗi lần push (1 cho claude/*, 1 cho main) — delivery cho claude/* sẽ bị skip, chỉ delivery cho main mới deploy

## PROJECT CONTEXT

### Tech Stack
- WordPress Theme (PHP)
- MySQL Database
- JavaScript (widget.js.php)

### Important Files
| File | Description |
|------|-------------|
| `docs/SHORTLINK-SYSTEM.md` | Technical documentation - ĐỌC TRƯỚC KHI SỬA |
| `includes/shortlink-functions.php` | Core shortlink logic, AJAX hooks, shortlink CRUD, code generation |
| `includes/shortlink-verification.php` | Verify logic, `traffictop_verify_and_pay()`, balance calculation |
| `includes/shortlink-distribution.php` | Campaign distribution, customer balance, auto-pause/resume |
| `includes/shortlink-ajax.php` | AJAX action registrations (40+ actions), tab lazy-loading |
| `includes/shortlink-ip.php` | IP detection, rate limiting, daily IP limit |
| `includes/admin-dashboard.php` | Admin AJAX handlers (unit tests, notes, cache, migrations) |
| `includes/withdrawal.php` | Withdrawal submission, validation, status transitions |
| `includes/deposit-management.php` | Deposit creation, bonus tiers, approval flow |
| `includes/customer-management.php` | Customer ban/unban, impersonation, delete |
| `includes/user-management.php` | User ban/freeze, inactive cleanup, notification system |
| `includes/campaign-management.php` | Campaign CRUD, approval, status management |
| `includes/ip-fraud.php` | VPN/proxy detection via external API |
| `includes/behavior-analytics.php` | Fraud scoring 0-100, device fingerprinting |
| `includes/anti-ddos.php` | DDoS protection, IP blocking, blocked referrers |
| `includes/low-balance-alerts.php` | Hourly cron alert khi customer balance thấp |
| `includes/email-notifications.php` | HTML email cho deposit, withdrawal |
| `includes/checkin.php` | Daily check-in reward (streak-based) |
| `includes/cron-cleanup.php` | Database cleanup, counter sync, retention policies |
| `includes/class-google-drive-upload.php` | ImgBB upload + WordPress fallback |
| `functions.php` | Helper functions, `traffictop_current_time()` |
| `widget.js.php` | Widget JavaScript - **KHÔNG ĐƯỢC SỬA show/hide logic** |
| `page-unlock.php` | Shortlink landing page, session load/restore |
| `page-admin-dashboard.php` | Admin dashboard UI (~16K lines) |
| `includes/admin/tabs/tab-*.php` | Admin tab files (lazy-loaded via AJAX) |

### Critical Functions (với line numbers)
- `traffictop_current_time($format='mysql')` — Vietnam timezone (Asia/Ho_Chi_Minh), returns 'Y-m-d H:i:s' or timestamp
- `traffictop_create_visit_session()` — shortlink-verification.php:26 — Tạo/reuse visit session
- `traffictop_verify_and_pay()` — shortlink-verification.php:243 — Core verify + payment logic
- `traffictop_get_random_active_campaign()` — shortlink-distribution.php:440 — Distribution algorithm
- `traffictop_get_user_balance_amount()` — shortlink-verification.php:1153 — User balance từ transactions
- `traffictop_add_user_balance()` — shortlink-verification.php:1087 — Add reward to user
- `traffictop_get_customer_balance_amount()` — shortlink-distribution.php:83 — Customer balance realtime
- `traffictop_auto_pause_insufficient_campaigns()` — shortlink-distribution.php:366 — Auto-pause
- `traffictop_auto_resume_paused_campaigns()` — shortlink-distribution.php:244 — Auto-resume
- `traffictop_update_hourly_adjustments()` — shortlink-distribution.php:751 — Hourly rebalance
- `traffictop_submit_withdrawal()` — withdrawal.php:9 — Submit withdrawal request
- `traffictop_get_real_ip()` — shortlink-ip.php:28 — Real IP (Cloudflare > X-Forwarded-For > REMOTE_ADDR)
- `traffictop_rate_limit_check()` — shortlink-ip.php:544 — Transient-based rate limiting
- `traffictop_cleanup_inactive_users()` — user-management.php:20 — Auto-delete inactive users

## TIMEZONE RULE
```php
// ĐÚNG - Dùng timezone Vietnam
$now = traffictop_current_time();
$today = date('Y-m-d', strtotime(traffictop_current_time()));

// SAI - Không dùng
date('Y-m-d H:i:s'); // Server timezone
NOW(); // MySQL timezone
```

## DATABASE TABLES (với cột quan trọng)

### Core Tables
**`wp_traffictop_shortlink_visits`** — Visit logs (mỗi lượt truy cập shortlink)
- `id`, `shortlink_id`, `campaign_id`, `order_id`, `user_id`, `session_id` (32-char unique)
- `verify_code` (8-char), `ip_address`, `original_ip`, `ip_changed`, `is_bypass`, `user_agent`, `referer`
- `step` ENUM: `started` → `google_clicked` → `target_visited` → `code_shown` → `verified`
- `google_clicked_at`, `target_visited_at`, `code_shown_at`, `verified_at`
- `from_google`, `url_matched`, `social_clicked`, `adblock_detected`, `ip_limit_exceeded`, `unlock_active`
- `reward_paid` (0/1), `reward_amount`, `customer_paid` (0/1), `created_at`

**`wp_traffictop_keyword_campaigns`** — Campaigns
- `id`, `customer_id`, `order_id`, `task_id`, `title`, `keyword`, `target_url`, `target_title`, `target_description`
- `screenshot_desktop_url`, `screenshot_mobile_url`
- `quantity`, `completed`, `price_per_view`, `user_reward`
- `countdown_seconds`, `traffic_type` ENUM(`1step`,`2step`,`nocode`), `onsite_time` (default 70s)
- `fixed_code` (cho nocode), `daily_traffic` (default 10/day)
- `status` ENUM(`pending`,`active`,`paused`,`completed`,`rejected`), `reject_reason`
- `created_at`, `updated_at`

**`wp_traffictop_customer_orders`** — Orders (linked to campaigns)
- `id`, `customer_id`, `customer_username`, `task_type` (`keyword_search`/`traffic_direct`/`traffic_social`)
- `title`, `task_url`, `instructions`, `quantity`, `completed`
- `price_per_task`, `service_fee`, `total_amount`, `amount_spent`
- `status`, `task_id`, `reject_reason`, `approved_by`, `approved_at`, `created_at`, `updated_at`
- **LƯU Ý:** KHÔNG có cột `start_date`, `end_date` (đã gây incident 08/03/2026)

**`wp_traffictop_user_shortlinks`** — User-created shortlinks
- `id`, `user_id`, `code` (6-char UNIQUE), `alias` (UNIQUE, optional custom slug)
- `original_url`, `fallback_url`, `total_clicks`, `total_completed`, `total_earnings`
- `status` ENUM(`active`,`disabled`), `created_at`

### Financial Tables (SOURCE OF TRUTH)
**`wp_traffictop_transactions`** — User transactions (**SOURCE OF TRUTH cho tài chính user**)
- `id`, `user_id`, `type` (`shortlink_reward`/`earn`/`withdraw`/`refund`/`bonus`/`deduction`)
- `amount`, `description`, `reference_id`, `reference_type`, `status`, `balance_after`, `created_at`
- **KHÔNG BAO GIỜ xóa** type IN (`shortlink_reward`, `refund`, `withdraw`)

**`wp_traffictop_customer_transactions`** — Customer transactions (**SOURCE OF TRUTH cho customer**)
- `id`, `customer_id`, `type` (`deposit`/`campaign_view`/`bonus`/`deduction`/`refund`)
- `amount`, `balance_after`, `description`, `reference_id`, `reference_type`, `status`, `created_at`
- **KHÔNG BAO GIỜ xóa** (source data cho customer balance)

**`wp_traffictop_withdrawals`** — Withdrawal requests
- `id`, `user_id`, `amount`, `payment_method` (`bank`/`usdt`), `bank_account`/`wallet_address`
- `status` (`pending`/`approved`/`rejected`/`completed`/`cancelled`/`refunded`)
- `admin_note`, `processed_at`, `created_at`

**`wp_traffictop_customer_deposits`** — Customer deposits
- `id`, `customer_id`, `customer_username`, `amount`, `bonus_percent`, `bonus_amount`
- `payment_method` (`bank`/`usdt`), `note`, `status` (`pending`/`approved`/`rejected`)
- `approved_by`, `approved_at`, `created_at`

### Balance Tables (có thể drift — luôn tính lại từ transactions)
**`wp_traffictop_user_balance`** — User balance cache
- `user_id`, `balance`, `total_earned`
- **CẢNH BÁO:** `balance` có thể bị lệch, LUÔN tính lại từ transactions khi check withdrawal

**`wp_traffictop_customer_balance`** — Customer balance cache
- `user_id` (**KHÔNG phải** `customer_id` — đã gây incident 09/03/2026), `balance`, `total_deposited`, `total_spent`

### Other Tables
- `wp_traffictop_tasks` — Tasks
- `wp_traffictop_notifications` — user_id, type, title, message, data (JSON), is_read, created_at
- `wp_traffictop_daily_checkins` — user_id, checkin_date, streak_day
- `wp_traffictop_behavior_analytics` — Fraud detection logs (fraud_score, fraud_reasons, risk_level)
- `wp_traffictop_device_fingerprints` — Browser fingerprint tracking
- `wp_traffictop_ip_reputation` — IP reputation scores
- `wp_traffictop_ddos_blocks` — DDoS blocked IPs (ip_address, violation_count, blocked_until, duration)
- `wp_traffictop_low_balance_alerts` — Alert tracking (1 lần/ngày/customer)

## WITHDRAWAL RULES - CHỐNG RÚT VƯỢT SỐ DƯ

### Nguyên tắc
1. User chỉ được rút khi số dư >= mức tối thiểu (`traffictop_min_withdrawal`)
2. Không được rút vượt quá số dư (balance sau rút >= 0)

### Source of Truth cho số dư
**LUÔN tính từ source data, KHÔNG dùng `wp_traffictop_user_balance.balance` field** vì running total có thể bị lệch.

```php
// ĐÚNG - Tính từ source data (cùng cách dashboard)
$available_balance = shortlink_earnings
                   - completed_and_cancelled_withdrawals - pending_withdrawals - other_deductions;

// SAI - Dùng balance field (có thể bị lệch → rút vượt số dư)
$current_balance = $balance_row->balance;
```

### Công thức tính số dư khả dụng
**QUAN TRỌNG: Dùng TRANSACTIONS TABLE (không dùng visits table)**
- Visits table chỉ dùng cho thống kê/hiển thị, KHÔNG dùng tính balance
- Vì visits có thể bị xóa bởi cron cleanup → earnings giảm → balance sai
```sql
-- Thu nhập shortlink (từ TRANSACTIONS - source of truth tài chính)
SELECT COALESCE(SUM(amount), 0) FROM transactions
WHERE user_id = %d AND type = 'shortlink_reward'

-- Đã rút + hủy không hoàn tiền (completed + cancelled)
SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE user_id = %d AND status IN ('completed', 'cancelled')

-- Đang rút (pending + approved)
SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE user_id = %d AND status IN ('pending', 'approved')

-- Deductions khác (xu convert, etc.) - type='withdraw' nhưng KHÔNG phải withdrawal thật
SELECT COALESCE(SUM(amount), 0) FROM transactions
WHERE user_id = %d AND type = 'withdraw' AND (reference_type IS NULL OR reference_type != 'withdrawal')

-- available_balance = total_earned - total_withdrawn - pending_withdrawal - other_deductions
-- QUAN TRỌNG: KHÔNG cộng refund_amount vì:
-- Khi withdrawal bị rejected/refunded → nó bị XÓA khỏi bucket trừ tiền (không còn trong
-- completed/cancelled hoặc pending/approved). Việc xóa khỏi bucket ĐÃ TRẢ LẠI tiền.
-- Nếu cộng thêm refund_amount → DOUBLE-COUNTING → balance bị thổi phồng → user rút vượt số dư.
```

### Withdrawal Status Matrix
| Status | Tiền bị trừ? | Refund transaction? | Ghi chú |
|--------|-------------|-------------------|---------|
| pending | Có (pending bucket) | - | Đang chờ duyệt |
| approved | Có (pending bucket) | - | Đã duyệt, chờ chuyển |
| completed | Có (withdrawn bucket) | - | Đã chuyển tiền xong |
| cancelled | Có (withdrawn bucket) | Không | Hủy, KHÔNG hoàn tiền |
| rejected | Không (trả lại) | Có (type=refund) | Từ chối, tự động hoàn tiền |
| refunded | Không (trả lại) | Có (type=refund) | Admin hoàn tiền thủ công |

### Source of Truth cho customer balance
**Dùng CUSTOMER_TRANSACTIONS TABLE (không dùng visits table)**
```sql
-- Customer total_spent (từ customer_transactions)
SELECT COALESCE(ABS(SUM(amount)), 0) FROM customer_transactions
WHERE user_id = %d AND type = 'campaign_view' AND amount < 0

-- Customer balance = total_deposited - total_spent
```

### Checklist khi sửa code liên quan withdrawal
- [ ] Tính `available_balance` từ TRANSACTIONS TABLE (shortlink_reward - withdrawals - other_deductions)
- [ ] KHÔNG cộng refund_amount vào công thức (đã trả lại qua việc xóa khỏi bucket → cộng thêm = double-counting)
- [ ] KHÔNG dùng visits table để tính balance (visits có thể bị xóa)
- [ ] KHÔNG dùng `$balance_row->balance` để check số dư
- [ ] Sync `balance` field = `available_balance` trước khi trừ (fix drift)
- [ ] Dashboard và server PHẢI dùng cùng công thức tính
- [ ] FOR UPDATE lock trên `wp_traffictop_user_balance` để chống concurrent withdrawal
- [ ] Atomic SQL `WHERE balance >= amount` làm safety net cuối

### Checklist khi sửa code cleanup/xóa dữ liệu
- [ ] KHÔNG xóa transactions có type IN ('shortlink_reward', 'refund', 'withdraw')
- [ ] KHÔNG xóa customer_transactions (source data cho customer balance)
- [ ] KHÔNG xóa visits có reward_paid=1 hoặc customer_paid=1
- [ ] Visits table chỉ dùng cho thống kê, KHÔNG dùng tính balance

## COMMON MISTAKES TO AVOID
1. Dùng `DATE(verified_at)` thay vì `DATE(created_at)` khi đếm
2. Dùng `NOW()` trong MySQL thay vì PHP timestamp
3. Quên sync status giữa customer_orders ↔ keyword_campaigns ↔ tasks
4. Đếm chỉ `reward_paid=1` thay vì `step='verified'` cho IP limit
5. Dùng `$balance_row->balance` thay vì tính từ transactions khi check withdrawal
6. Dùng `SUM(reward_amount) FROM visits` thay vì `SUM(amount) FROM transactions WHERE type='shortlink_reward'` để tính thu nhập shortlink
7. Xóa visits/transactions tài chính trong cron cleanup
8. Không đếm withdrawal `cancelled` trong bucket trừ tiền → user bị hủy vẫn nhận lại tiền
9. Quên tạo refund transaction khi chuyển sang status 'refunded'
10. Cộng `refund_amount` vào công thức tính balance → double-counting → user rút vượt số dư (rejected/refunded đã bị xóa khỏi bucket trừ tiền, xóa khỏi bucket = đã trả lại tiền)
11. **Hardcode tên cột trong SQL mà không kiểm tra cột tồn tại** → Query fail silent, trả về 0 rows → hệ thống chết hoàn toàn (xem mục DATABASE COLUMN SAFETY bên dưới)
12. **Dùng sai tên cột khi tham chiếu bảng** — ví dụ: `wp_traffictop_customer_balance` có cột `user_id` nhưng lại dùng `customer_id` → SQL lỗi → subquery rỗng → INNER JOIN fail → hệ thống chết. **LUÔN kiểm tra tên cột thực tế** của bảng trước khi viết query
13. **CORS headers trong `plugins_loaded` bị WordPress override** — `admin-ajax.php` gọi `send_origin_headers()` SAU `plugins_loaded` → ghi đè CORS headers → widget AJAX bị block. **PHẢI dùng `admin_init` (priority 0)** để set CORS headers SAU `send_origin_headers()` (xem mục WIDGET CORS bên dưới)

## WIDGET CORS & CROSS-SITE SESSION — BÀI HỌC 13/04/2026

### Vấn đề
Widget AJAX từ web đích (trafficuser.net) gọi traffictop.net/wp-admin/admin-ajax.php bị CORS block:
> No 'Access-Control-Allow-Origin' header is present on the requested resource

### Nguyên nhân gốc
WordPress `admin-ajax.php` loading order:
1. `wp-load.php` → `plugins_loaded` fires → CORS headers set ở đây
2. `send_origin_headers()` → **GHI ĐÈ** CORS headers → chỉ cho phép domains trong WP whitelist
3. `admin_init` fires
4. AJAX handler runs

CORS headers set ở `plugins_loaded` bị `send_origin_headers()` xóa sạch → browser thấy không có `Access-Control-Allow-Origin` → block request → widget hiện "Bạn chưa truy cập shortlink".

### Fix
```php
// SAI — bị WordPress override
add_action( 'plugins_loaded', function() { header('Access-Control-Allow-Origin: *'); });

// ĐÚNG — chạy SAU send_origin_headers(), dùng replace=true
add_action( 'admin_init', function() {
    if ( ! defined('DOING_AJAX') || ! DOING_AJAX ) return;
    // ... check action in widget_actions list ...
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ( ! empty( $origin ) ) {
        header( 'Access-Control-Allow-Origin: ' . $origin, true ); // replace=true
        header( 'Access-Control-Allow-Credentials: true' );
    } else {
        header( 'Access-Control-Allow-Origin: *', true );
    }
}, 0 ); // priority 0 = chạy sớm nhất trong admin_init
```

### Vấn đề phụ: Dual-stack IPv4/IPv6
Widget tìm visit bằng IP matching. Nếu user kết nối traffictop.net qua IPv4 (tạo visit) nhưng AJAX từ widget kết nối qua IPv6 (hoặc ngược lại) → IP không match → không tìm được visit.

**Fix:** Cookie cross-site fallback:
1. `page-unlock` set cookie `traffictop_sid` (`SameSite=None; Secure; 2h`)
2. Widget AJAX dùng `withCredentials=true` để gửi cookie
3. `verify_access`: nếu IP match fail → fallback match bằng cookie session_id

### Vấn đề phụ: Widget API domain
`widget.js.php` dùng `home_url()` cho API URL. Nếu WordPress home_url khác domain user truy cập (ví dụ: home_url = linkngon.top nhưng user dùng traffictop.net) → cookie domain mismatch.

**Fix:** Widget detect API origin từ `document.currentScript.src` thay vì hardcode `home_url()`:
```javascript
var _csrc = document.currentScript ? document.currentScript.src : '';
var _apiOrigin = '';
if (_csrc) { var _m = _csrc.match(/^(https?:\/\/[^\/]+)/); if (_m) _apiOrigin = _m[1]; }
var C = { api: _apiOrigin || '<?php echo esc_js($site_url); ?>', ... };
```

### Checklist khi sửa CORS / Widget AJAX
- [ ] CORS headers PHẢI set trong `admin_init`, KHÔNG PHẢI `plugins_loaded`
- [ ] `Access-Control-Allow-Origin: *` + `Access-Control-Allow-Credentials: true` = **browser reject** → chỉ gửi Credentials khi có Origin cụ thể
- [ ] Widget XHR phải có `withCredentials=true` để gửi cookie cross-site
- [ ] Cookie cross-site cần `SameSite=None; Secure` (chỉ hoạt động trên HTTPS)
- [ ] Widget API URL nên detect từ script src, không hardcode `home_url()`

## DATABASE COLUMN SAFETY - KIỂM TRA CỘT TRƯỚC KHI DÙNG

### Bài học thực tế (08/03/2026)
Query trong `traffictop_get_random_active_campaign()` dùng `co.start_date` và `co.end_date` nhưng bảng `customer_orders` **KHÔNG có** các cột này → SQL lỗi → trả về 0 campaigns → **TẤT CẢ shortlinks redirect về link gốc** thay vì hiện page-unlock.

### Bài học thực tế (09/03/2026)
Query trong `traffictop_get_random_active_campaign()` dùng subquery từ bảng `wp_traffictop_customer_balance` và tham chiếu cột `customer_id` — nhưng bảng này **chỉ có cột `user_id`**, không có `customer_id`. Hậu quả: SQL lỗi → subquery trả về rỗng → INNER JOIN không match bất kỳ campaign nào → hàm return null → **TẤT CẢ shortlinks redirect về link gốc** thay vì hiện page-unlock. Fix: đổi `SELECT customer_id` → `SELECT user_id AS customer_id` và tất cả reference từ `cb_calc.customer_id` → `cb_calc.user_id`.

### Nguyên tắc
- **KHÔNG hardcode tên cột** trong SQL nếu không chắc chắn cột tồn tại trên production
- Khi tham chiếu cột có thể không tồn tại (đặc biệt `start_date`, `end_date`, hoặc các cột mới thêm), **PHẢI kiểm tra trước**:
```php
// ĐÚNG - Kiểm tra cột tồn tại trước khi dùng
$has_col = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'column_name'");
if (!empty($has_col)) {
    // Thêm vào query
}

// SAI - Hardcode cột không tồn tại → SQL error → query trả về rỗng
$wpdb->get_results("SELECT ... WHERE co.start_date IS NULL ...");
```

### Checklist khi viết SQL query
- [ ] Xác nhận tất cả các cột trong SELECT, WHERE, ORDER BY tồn tại trên bảng tương ứng
- [ ] Đặc biệt cẩn thận khi JOIN nhiều bảng — kiểm tra cột thuộc bảng nào (`kc.` vs `co.`)
- [ ] Nếu cột có thể không tồn tại → dùng `SHOW COLUMNS` check trước hoặc bỏ qua filter đó
- [ ] Test query trên production schema (không chỉ local)

## SECURITY - CHỐNG BYPASS COUNTDOWN

### 1. Time Check - LUÔN dùng cùng timezone
```php
// ĐÚNG - So sánh với cùng timezone
$created_at = strtotime($visit->created_at);
$now = strtotime(traffictop_current_time());  // ← Vietnam timezone
$elapsed = $now - $created_at;

// SAI - Gây bypass do timezone mismatch (chênh 7 giờ!)
$now = time();  // ← Server timezone (có thể là UTC)
```

### 2. Traffic Type Check - Default là '1step'
```php
// ĐÚNG - Default nếu NULL (campaign bị xóa)
$traffic_type = $visit->traffic_type ?? '1step';
if ($traffic_type !== 'nocode') {
    // Check time
}

// SAI - NULL = skip time check = BYPASS!
if (in_array($visit->traffic_type, ['1step', '2step'])) {
    // Check time
}
```

### 3. Visit Reuse - Chống race condition
```sql
-- ĐÚNG - Không reuse nếu đã lấy mã
SELECT * FROM visits WHERE
    step != 'verified'
    AND verified_at IS NULL
    AND verify_code IS NULL  -- ← QUAN TRỌNG: Tránh race condition

-- SAI - Race condition khi đang verify
SELECT * FROM visits WHERE
    step != 'verified'
    AND verified_at IS NULL
    -- Thiếu check verify_code → created_at bị update khi đang verify
```

### 4. Các Endpoint Get Code - Phải có time check
| Endpoint | File | Line | Phải có |
|----------|------|------|---------|
| `traffictop_widget_get_code` | shortlink-functions.php | ~4672 | Time check (luôn) |
| `traffictop_get_verify_code` | shortlink-functions.php | ~2912 | Time check + traffic_type default |
| `traffictop_get_widget_code` | shortlink-functions.php | ~2983 | Time check + traffic_type default |

### 5. Completion Time âm
**Nguyên nhân:** `created_at` bị update SAU khi `verified_at` đã set
**Fix:** Query reuse visit phải check cả `verify_code IS NULL`

### 6. Checklist khi sửa code liên quan verify
- [ ] Dùng `strtotime(traffictop_current_time())` thay vì `time()`
- [ ] Default `traffic_type = '1step'` nếu NULL
- [ ] Không reuse visit đã có `verify_code`
- [ ] Rate limiting cho tất cả endpoint

## ADMIN DASHBOARD - CẤU TRÚC & LƯU Ý

### Kiến trúc Tab System
- **File chính:** `page-admin-dashboard.php` (~16K lines) chứa layout, CSS, JavaScript
- **Tab files:** `includes/admin/tabs/tab-*.php` - mỗi tab là 1 file riêng
- **Campaigns tab** là tab DUY NHẤT được **server-render** (include trực tiếp trong PHP)
- **Tất cả tab khác** được **lazy-load via AJAX** (`traffictop_admin_load_tab` action)
- AJAX handler: `includes/shortlink-ajax.php` → `traffictop_ajax_admin_load_tab()`
- JS: `loadLazyTab()` dùng `outerHTML` để thay thế placeholder div

### Campaigns Tab - Cấu trúc include
```
tab-campaigns.php
├── campaigns/stats.php      (28 divs - balanced)
├── campaigns/create-form.php (177 divs - balanced)
└── campaigns/list.php        (26 divs - balanced)
```

### Bài học: HTML Tag Balance (09/03/2026)
**campaigns/list.php từng có 1 extra `</div>`** ở cuối file (27 closes vs 26 opens).
Hậu quả: extra `</div>` đóng sớm wrapper `#tab-campaigns` → `</div>` tiếp theo đóng luôn `.admin-content` → tất cả lazy tabs nằm NGOÀI `.admin-content` → khi campaigns tab ẩn, `.admin-content` rỗng (có padding) tạo **khoảng trống lớn** phía trên nội dung các tab khác.

**Quy tắc:**
- Khi thêm/sửa HTML trong tab files, **LUÔN đếm `<div>` vs `</div>`** đảm bảo balanced
- Đặc biệt cẩn thận với files được `include` — extra tag sẽ phá vỡ parent structure
- Dùng lệnh kiểm tra: `grep -c '<div' file.php && grep -c '</div>' file.php`

### Style block trong admin-content
Có 1 `<style>` block (~600 lines, lines 9973-10570) nằm trực tiếp trong `.admin-content` div, SAU tất cả tab divs. Block này PHẢI có cả `<style>` mở VÀ `</style>` đóng đúng.

## MAIN FLOWS & LOGIC

### Flow 1: Shortlink Visit (User clicks shortlink → Verification → Payment)

```
Visitor clicks /{shortcode}
    │
    ▼
page-unlock.php loads
    │  ├─ Session restore: URL param ?sid={session_id} → load visit from DB
    │  ├─ Always loads FRESH from DB (not session cache)
    │  └─ If campaign inactive → auto-select random active campaign → update visit
    │
    ▼
traffictop_create_visit_session() [shortlink-verification.php:26]
    │  ├─ REUSE CHECK (SQL):
    │  │   WHERE shortlink_id=%d AND ip_address=%s AND step!='verified'
    │  │   AND verified_at IS NULL AND verify_code IS NULL AND created_at > (now-10min)
    │  ├─ If reuse: reset created_at, step='started', verify_code=NULL, code_shown_at=NULL
    │  ├─ Clear transients: widget_code_ready_, widget_cd_, widget_code_, verify_code_, google_clicked_
    │  ├─ New session columns: shortlink_id, campaign_id, order_id, user_id, session_id,
    │  │   ip_address, original_ip, user_agent, referer, step='started', ip_limit_exceeded, created_at
    │  └─ Auto-migration: creates original_ip, ip_changed, is_bypass, order_id columns if missing
    │
    ▼
traffictop_get_random_active_campaign() [shortlink-distribution.php:440]
    │  ├─ ELIGIBLE CAMPAIGNS (cached 60s):
    │  │   SQL: kc INNER JOIN co ON co.id=kc.order_id
    │  │   INNER JOIN (realtime balance subquery) cb_pre
    │  │   WHERE kc.status='active' AND co.status='active'
    │  │   AND cb_pre.balance > 20,000 + GREATEST(price_per_view, 1000)
    │  │   AND date range (if columns exist, checked via SHOW COLUMNS)
    │  │
    │  ├─ PER-CAMPAIGN FILTERING (real-time, not cached):
    │  │   ├─ Count today's verified + in-progress (<10min) visits
    │  │   ├─ Skip if visitor IP already completed this campaign today
    │  │   └─ Skip if today_completed >= daily_limit
    │  │
    │  ├─ WEIGHT FORMULA:
    │  │   weight = remaining × e^(combined_lag × 10)
    │  │   combined_lag = (time_lag × 0.5) + (peer_lag × 0.5) + carryover_capped
    │  │   ├─ time_lag = expected_rate - actual_progress  (expected = (hour+min/60)/24)
    │  │   ├─ peer_lag = avg_progress - campaign_progress
    │  │   └─ carryover = previous hour deviation ÷ 2  (capped ±20%)
    │  │
    │  └─ WEIGHTED RANDOM: min weight=1, fallback to random if total_weight<=0
    │
    ▼
Visitor follows campaign instructions (search keyword / visit URL)
    │  ├─ Widget shows countdown (default 30s display, 70s actual onsite_time)
    │  └─ Session stored in $_SESSION['traffictop_campaign'] + $_SESSION['traffictop_shortlink']
    │
    ▼
Widget "Get Code" button → traffictop_ajax_get_widget_code [shortlink-functions.php]
    │  ├─ TIME CHECK: elapsed >= max(onsite_time - 5, 10) → else 'too_fast'
    │  ├─ nocode traffic → SKIP time check
    │  ├─ If verify_code exists in DB → return cached (prevent extending expiry)
    │  ├─ Else → generate 8-char hex code (strtoupper(substr(bin2hex(random_bytes),0,8)))
    │  ├─ Set transient verify_code_{session_id} with traffictop_verify_code_expiry (default 600s)
    │  └─ Update visit: step='code_shown', code_shown_at=now
    │
    ▼
Visitor enters code → traffictop_verify_and_pay() [shortlink-verification.php:243]
    │
    ├─ PRE-TRANSACTION VALIDATION (exact order with line numbers):
    │  ├─ Line 248: IP block check (traffictop_is_ip_blocked)
    │  ├─ Line 256: Find visit by session_id → error if not found
    │  ├─ Line 266: Check reward_paid=1 → error "already used"
    │  ├─ Line 271: Check user meta traffictop_banned → error if banned
    │  ├─ Line 279: Visit age check: max 7200s (2 hours)
    │  ├─ Line 294: Get campaign by campaign_id, check fixed_code, determine is_nocode
    │  ├─ Line 323: Campaign existence check
    │  ├─ Line 329: Campaign status='active' check → sets should_pay_reward/customer=false
    │  ├─ Line 340-380: TIME CHECK (skip for nocode):
    │  │   ├─ elapsed = strtotime(now) - strtotime(created_at)
    │  │   ├─ min_required = max(onsite_time-5, 10)
    │  │   ├─ BLOCK if elapsed < min_required
    │  │   └─ BLOCK if no code_ready transient
    │  ├─ Line 399-441: TRAFFIC-SPECIFIC CHECKS:
    │  │   ├─ keyword_search: from_google + url_matched (skip for nocode)
    │  │   ├─ traffic_social: url_matched only
    │  │   └─ traffic_direct: url_matched only
    │  ├─ Line 444-478: CODE CHECK:
    │  │   ├─ nocode: case-SENSITIVE comparison with fixed_code
    │  │   └─ normal: case-INSENSITIVE (strtoupper), check transient expiry (600s default)
    │  │
    │  ├─ Line 551-602: IP CHECKS:
    │  │   ├─ IP changed: compare original_ip vs current → should_pay_reward=false
    │  │   ├─ IP daily limit: COUNT verified WHERE ip=%s AND step='verified' AND created_at>=today
    │  │   └─ If count >= traffictop_shortlink_ip_limit_24h (default 5) → should_pay_reward=false
    │  ├─ Line 605: Adblock check → should_pay_reward=false
    │  ├─ Line 622: Bypass check: completion_time < onsite_time → should_pay_reward=false
    │  ├─ Line 639-675: Daily traffic limit:
    │  │   COUNT verified WHERE campaign_id=%d AND DATE(created_at)=today
    │  │   If >= daily_limit → should_pay_reward=false AND should_pay_customer=false
    │  └─ Line 681-748: Customer balance:
    │      realtime balance via traffictop_get_customer_balance_amount()
    │      If balance <= min_balance OR < customer_cost → auto-pause ALL campaigns
    │
    ├─ DATABASE TRANSACTION (Line 809-1050):
    │  ├─ START TRANSACTION
    │  ├─ Line 814: LOCK visit FOR UPDATE → re-check reward_paid and step
    │  ├─ Line 836: RECHECK daily limit INSIDE transaction (race condition safety)
    │  ├─ Line 882: LOCK customer_balance FOR UPDATE
    │  ├─ Line 898: Deduct customer: traffictop_update_customer_balance_new(-$cost, 'campaign_view')
    │  ├─ Line 911: Set customer_paid=1 on visit
    │  ├─ Line 919: Update order.amount_spent += cost
    │  ├─ Line 926: If balance <= min after → auto-pause ALL campaigns/orders/tasks
    │  ├─ Line 992: Add user reward: traffictop_add_user_balance($reward, 'shortlink_reward')
    │  ├─ Line 997: Update shortlink stats (total_completed++, total_earnings+=reward)
    │  ├─ Line 1019: campaign.completed++, Line 1027: order.completed++
    │  ├─ Line 1040: Update visit: step='verified', verified_at, reward_paid, reward_amount, flags
    │  └─ Line 1050: COMMIT (Line 1056: ROLLBACK on exception)
    │
    ▼
Redirect to original_url (shortlink's destination)
```

**Key Tables**: `wp_traffictop_shortlink_visits`, `wp_traffictop_keyword_campaigns`, `wp_traffictop_customer_orders`, `wp_traffictop_transactions`, `wp_traffictop_customer_transactions`, `wp_traffictop_user_balance`, `wp_traffictop_customer_balance`

**Transients Used**: `widget_code_ready_{sid}`, `widget_cd_{sid}`, `widget_code_{sid}`, `verify_code_{sid}`, `google_clicked_{sid}`

**Visit Step Lifecycle**: `started` → `google_clicked` → `target_visited` → `code_shown` → `verified`

---

### Flow 2: Campaign Distribution Algorithm

```
traffictop_get_random_active_campaign() [shortlink-distribution.php:440]
    │
    ├─ 1. ELIGIBLE CAMPAIGNS (cached 60s, transient 'traffictop_eligible_campaigns'):
    │     SQL: SELECT kc.*, co.quantity, co.daily_traffic, cb_pre.balance
    │     FROM campaigns kc
    │     INNER JOIN orders co ON co.id = kc.order_id
    │     INNER JOIN (realtime balance subquery from deposits + transactions) cb_pre
    │     WHERE kc.status='active' AND co.status='active'
    │     AND balance > 20000 + GREATEST(COALESCE(price_per_view, 0), 1000)
    │     [+ date filter if start_date/end_date columns exist]
    │
    │     Customer Balance Subquery:
    │       SUM(deposits.amount + bonus WHERE approved)
    │       + SUM(transactions WHERE type='bonus' AND amount>0)
    │       - ABS(SUM(transactions WHERE type='campaign_view' AND amount<0))
    │       - ABS(SUM(transactions WHERE type='deduction' AND amount<0))
    │
    │     Column safety: SHOW COLUMNS check for start_date, customer_id vs user_id
    │     Cache invalidated on: auto-pause, auto-resume, recovery v2
    │
    ├─ 2. PER-CAMPAIGN FILTERING (real-time, NOT cached):
    │     ├─ Count today: step='verified' OR (step IN started/on_site AND created_at > 10min ago)
    │     ├─ Skip if visitor IP already verified this campaign today
    │     └─ remaining = daily_limit - today_completed; skip if remaining <= 0
    │
    ├─ 3. WEIGHT FORMULA (exact coefficients):
    │     time_lag = (hour + minute/60) / 24 - (today_completed / daily_limit)
    │     peer_lag = average_progress_all_campaigns - campaign_progress
    │     carryover = get from traffictop_hourly_adjustments option, capped max(-0.2, min(0.2, value))
    │     combined_lag = (time_lag × 0.5) + (peer_lag × 0.5) + carryover_capped
    │     lag_multiplier = e^(combined_lag × 10)
    │     weight = max(1, remaining × lag_multiplier)
    │
    │     Example: remaining=5, time_lag=0.05, peer_lag=0.03, carryover=0.01
    │     → combined=0.05 → multiplier=e^0.5≈1.649 → weight=8.24
    │
    └─ 4. WEIGHTED RANDOM (traffictop_weighted_random_select):
          ├─ Empty list → null
          ├─ Single campaign → return directly
          ├─ total_weight <= 0 → random pick any
          └─ Normal: cumulative weight selection

traffictop_update_hourly_adjustments() [line 751] — Runs hourly via WP Cron:
    ├─ hourly_expected = (hour + 1) / 24
    ├─ actual_progress = today_completed / daily_limit
    ├─ deviation = hourly_expected - actual_progress
    ├─ carryover_next_hour = deviation / 2 (damped to avoid swings)
    ├─ Stored in option: traffictop_hourly_adjustments = {date, hour, camps: {id: carryover}}
    └─ Resets all carryover on new day
```

---

### Flow 3: Traffic Types & Verification Differences

| Traffic Type | Time Check | Code Type | Google Check | User Reward Setting |
|-------------|-----------|-----------|-------------|-------------------|
| `1step` (keyword_search) | Yes, onsite_time-5 | Random 8-char | Yes (from_google + url_matched) | `traffictop_keyword_user_1step` |
| `2step` (keyword_search) | Yes, onsite_time-5 | Random 8-char | Yes (from_google + url_matched) | `traffictop_keyword_user_2step` |
| `nocode` | **SKIP** | Fixed code (case-sensitive) | No | `traffictop_keyword_user_nocode` |
| `traffic_direct` | Yes | Random 8-char | No | `traffictop_direct_user_*` |
| `traffic_social` | Yes | Random 8-char | No | `traffictop_social_user_*` |

---

### Flow 4: Customer/Advertiser Flow

```
1. DEPOSIT (deposit-management.php)
   Customer submits deposit → status='pending' → Admin approves → balance += amount + bonus
   ├─ Rate limit: 3 req/min/user (transient traffictop_deposit_rate_X)
   ├─ Min: 50,000đ (traffictop_min_deposit_amount), Max: 100,000,000đ
   ├─ Bonus tiers: traffictop_deposit_tiers [{amount, bonus%}]
   │   ├─ Sorted by amount ASC
   │   ├─ Monotonic constraint: higher tier bonus >= lower tier (auto-corrected)
   │   └─ Bonus = floor(amount × bonus_percent / 100)
   ├─ Table: wp_traffictop_customer_deposits (status='pending')
   ├─ APPROVAL (in transaction):
   │   ├─ Lock deposit FOR UPDATE → check status='pending'
   │   ├─ Lock customer_balance FOR UPDATE → atomic balance += amount + bonus
   │   ├─ Update deposit: status='approved', approved_by, approved_at
   │   └─ Log customer_transaction type='deposit'
   └─ REJECTION: status='rejected', no balance change

2. CREATE CAMPAIGN (shortlink-ajax.php → traffictop_create_keyword_campaign)
   Customer fills form → status='pending' → Admin approves → status='active'
   ├─ NO money deducted upfront (only when traffic completes)
   ├─ Requires balance > 20,000đ (traffictop_customer_min_balance)
   ├─ Creates: wp_traffictop_keyword_campaigns + wp_traffictop_customer_orders
   ├─ price_per_view = customer pays (from settings by campaign_type + traffic_type)
   └─ user_reward = price × traffictop_keyword_user_reward_percent / 100 (default 80%)

3. CAMPAIGN ACTIVE → Distributed to visitors
   └─ Each verified visit: customer balance -= price_per_view

4. AUTO-PAUSE (every 5 min, traffictop_auto_pause_insufficient_campaigns) [line 366]
   ├─ Get active customers with MIN(GREATEST(price_per_view, 1000))
   ├─ Required = 20,000 + min_price
   ├─ Pause if: balance <= required (note: <= not <)
   ├─ Safety: if balance=0 but has approved deposits → SKIP pause (likely SQL error)
   ├─ Safety: if balance===false (SQL error) → SKIP pause
   └─ Pauses: campaigns (status='paused'), orders (status='paused'), tasks (via JOIN)

5. AUTO-RESUME (every 15 min, traffictop_auto_resume_paused_campaigns) [line 244]
   ├─ Resume if: balance > required (note: > not >=)
   ├─ Safety: if balance===false → skip
   ├─ Resumes: campaigns, orders, tasks → status='active'
   └─ ONE-TIME RECOVERY v2: restores incorrectly auto-completed campaigns
       (transient traffictop_autocomplete_recovery_v2, 30-day cooldown)
```

**Customer Balance Formula** (realtime, NOT from balance field):
```sql
-- traffictop_get_customer_balance_amount() [shortlink-distribution.php:83]
-- Column safety: SHOW COLUMNS check for customer_id vs user_id

balance = COALESCE(SUM(deposits.amount + deposits.bonus_amount) WHERE approved, 0)
        + COALESCE(SUM(transactions.amount) WHERE type='bonus' AND amount > 0, 0)
        - COALESCE(ABS(SUM(transactions.amount)) WHERE type='campaign_view' AND amount < 0, 0)
        - COALESCE(ABS(SUM(transactions.amount)) WHERE type='deduction' AND amount < 0, 0)

-- Returns FALSE on SQL error (not 0) — callers must check for false!
```

---

### Flow 5: Withdrawal Flow

```
1. USER SUBMITS (withdrawal.php:9 → traffictop_submit_withdrawal)
   ├─ Nonce check + auth check
   ├─ User banned check (meta traffictop_banned)
   ├─ Amount > 0 check
   ├─ Amount >= traffictop_min_withdrawal (default 50,000đ)
   ├─ IN TRANSACTION with locks:
   │   ├─ Lock user_balance FOR UPDATE
   │   ├─ Calculate available_balance from TRANSACTIONS (not balance field):
   │   │   total_earned (type IN 'shortlink_reward','earn')
   │   │   - total_withdrawn (status IN 'completed','cancelled')
   │   │   - pending_withdrawal (status IN 'pending','approved')
   │   │   - other_deductions (type='withdraw' AND reference_type != 'withdrawal')
   │   ├─ Recheck: available_balance >= min_withdrawal
   │   ├─ Recheck: amount <= available_balance
   │   ├─ Sync balance field = available_balance (fix drift)
   │   ├─ Atomic: UPDATE balance SET balance = balance - amount WHERE balance >= amount
   │   ├─ Insert wp_traffictop_withdrawals (status='pending')
   │   └─ Insert wp_traffictop_transactions (type='withdraw')
   └─ Email notification to admin + user

2. ADMIN REVIEWS (admin-dashboard.php)
   ├─ APPROVE → status='approved' → admin transfers money manually → mark 'completed'
   └─ REJECT → status='rejected' → auto-refund: balance += amount, log type='refund'

Status transitions:
   pending → approved → completed (normal flow)
   pending → rejected (refunded automatically, creates type='refund' transaction)
   approved → cancelled (NO refund — tiền mất)
   completed → refunded (admin manual refund, creates type='refund' transaction)
```

---

### Flow 6: Cron/Automated Jobs

| Schedule | Function | File | Purpose |
|----------|----------|------|---------|
| Every 5 min | `traffictop_auto_pause_insufficient_campaigns` | shortlink-distribution.php:366 | Pause campaigns if customer balance too low |
| Every 15 min | `traffictop_auto_resume_paused_campaigns` | shortlink-distribution.php:244 | Resume campaigns if balance recovered |
| Hourly | `traffictop_update_hourly_adjustments` | shortlink-distribution.php:751 | Rebalance campaign distribution weights |
| Hourly | `traffictop_cache_eligible_campaigns` | shortlink-distribution.php:470 | Pre-calculate eligible campaigns cache (60s transient) |
| Hourly | `traffictop_check_low_balance_customers` | low-balance-alerts.php | Email customer if balance < threshold |
| Daily | `traffictop_run_database_cleanup` | cron-cleanup.php:9 | Delete old non-financial data |

**Cleanup Retention Periods** (all configurable):
| Data Type | Setting | Default | Safety |
|-----------|---------|---------|--------|
| Expired task logs | `traffictop_cleanup_expired_logs` | 30 ngày | — |
| Old shortlink sessions | `traffictop_cleanup_old_shortlinks` | 7 ngày | — |
| Read notifications | `traffictop_cleanup_read_notifications` | 30 ngày | — |
| Daily submissions | `traffictop_cleanup_daily_submissions` | 30 ngày | — |
| Old visits | `traffictop_cleanup_old_visits` | 30 ngày | **NEVER** nếu reward_paid=1 hoặc customer_paid=1 |
| Behavior analytics | `traffictop_cleanup_old_behavior` | 14 ngày | — |
| Deleted campaigns | `traffictop_cleanup_deleted_campaigns` | 30 ngày | Chỉ nếu completed=0 |
| Admin notifications | `traffictop_cleanup_admin_notifications` | 30 ngày | — |
| Old transactions | `traffictop_cleanup_old_transactions` | 0 (disabled) | **NEVER** type IN (shortlink_reward, refund, withdraw) |

**Cleanup Safety**: NEVER deletes visits with `reward_paid=1` or `customer_paid=1`, transactions with type IN (`shortlink_reward`, `refund`, `withdraw`), customer_transactions, hoặc verified visits (step='verified').

**Counter Sync** (cron-cleanup.php:1266): `traffictop_sync_shortlink_counters()` + `traffictop_sync_campaign_counters()` — Recalculate counters từ visits để fix drift.

---

### Flow 7: Admin Dashboard Tab System

```
page-admin-dashboard.php (~16K lines)
    │
    ├─ Campaigns tab: SERVER-RENDERED (include trực tiếp)
    │   └─ tab-campaigns.php
    │       ├─ campaigns/stats.php
    │       ├─ campaigns/create-form.php
    │       └─ campaigns/list.php
    │
    └─ ALL OTHER TABS: LAZY-LOAD via AJAX
        ├─ AJAX action: traffictop_admin_load_tab
        ├─ Handler: shortlink-ajax.php → traffictop_ajax_admin_load_tab()
        ├─ JS: loadLazyTab() replaces placeholder div with outerHTML
        └─ Tab files: includes/admin/tabs/tab-*.php
```

---

### Flow 8: Reward Amount Determination

```php
// Priority order for user reward amount:
1. Campaign-specific: campaign->user_reward (if set and > 0)
2. Settings by campaign_type + traffic_type:
   - keyword_search + 1step → traffictop_keyword_user_1step (default 800đ)
   - keyword_search + 2step → traffictop_keyword_user_2step (default 1000đ)
   - keyword_search + nocode → traffictop_keyword_user_nocode (default 800đ)
   - traffic_direct + 1step → traffictop_direct_user_1step (default 500đ)
   - traffic_direct + 2step → traffictop_direct_user_2step (default 700đ)
   - traffic_direct + nocode → traffictop_direct_user_nocode (default 800đ)
   - traffic_social + 1step → traffictop_social_user_1step (default 700đ)
   - traffic_social + 2step → traffictop_social_user_2step (default 900đ)
   - traffic_social + nocode → traffictop_social_user_nocode (default 1000đ)
3. Fallback: campaign->user_reward or global default

// Customer cost per view (how much customer pays):
   - keyword_search: traffictop_keyword_price_1step (1200đ) / 2step (1500đ) / nocode (1200đ)
   - traffic_direct: traffictop_direct_price_1step (1200đ)
   - traffic_social: traffictop_social_price_1step (1200đ)

// Relationship: user_reward = price × traffictop_keyword_user_reward_percent / 100 (default 80%)
// Example: 1step → customer pays 1200đ, user gets 800đ (66.7%), platform keeps 400đ
```

### Flow 8b: User Balance Calculation

```php
// traffictop_get_user_balance_amount() [shortlink-verification.php:1153]
// Source of truth — KHÔNG dùng balance field

$total_earned = SUM(amount) FROM transactions WHERE type IN ('shortlink_reward', 'earn');
$total_withdrawn = SUM(amount) FROM withdrawals WHERE status IN ('completed', 'cancelled');
$pending_withdrawal = SUM(amount) FROM withdrawals WHERE status IN ('pending', 'approved');
$other_deductions = SUM(amount) FROM transactions WHERE type='withdraw'
                    AND (reference_type IS NULL OR reference_type != 'withdrawal');

$available_balance = $total_earned - $total_withdrawn - $pending_withdrawal - $other_deductions;

// traffictop_add_user_balance() [shortlink-verification.php:1087]
// 1. UPDATE balance += amount, total_earned += amount WHERE user_id=%d
// 2. If 0 rows affected (new user): INSERT IGNORE
// 3. If INSERT also skipped (race): RETRY UPDATE
// 4. Insert transaction record (type, amount, description, balance_after if column exists)
```

---

### Flow 9: Security Mechanisms Summary

| Mechanism | Purpose | Implementation |
|-----------|---------|---------------|
| FOR UPDATE lock | Prevent double-payment | Lock visit + customer_balance rows in transaction |
| Timezone (traffictop_current_time) | Prevent bypass via time mismatch | All comparisons use Vietnam UTC+7 (Asia/Ho_Chi_Minh) |
| IP daily limit | Prevent farming | COUNT verified WHERE ip=%s AND step='verified' >= setting (default 5) |
| IP change detection | Detect VPN/proxy switching | Compare original_ip vs current IP during verify |
| Bypass detection | Catch time manipulation | completion_time < onsite_time → is_bypass=1, no reward |
| Code expiry | Limit verification window | 10 min transient TTL (traffictop_verify_code_expiry=600) |
| Visit reuse guard | Prevent race condition | verify_code IS NULL required for reuse |
| Rate limiting | Prevent brute force | 6 endpoint configs: 10-60 req/min (transient-based) |
| DDoS protection | Prevent resource exhaustion | 3-tier: 10/sec, 30/10s, 300/60s + progressive blocking |
| Adblock detection | Ensure ad visibility | Client-side check → server-side flag → no reward |
| Google click verify | Ensure search traffic quality | from_google=1 + url_matched=1 (keyword_search only) |
| VPN/Proxy detection | Block non-organic traffic | ip-api.com (45 req/min) + known VPN keywords |
| Fraud scoring | Multi-factor abuse detection | 0-100 score from 15+ factors, risk levels: safe/low/medium/high |
| IP validation | Block known bad IPs | DNS resolvers blocked, private ranges, 200+ datacenter CIDRs |
| Daily limit recheck | Race condition in transaction | Recounts daily limit INSIDE transaction after lock |

### Flow 9b: IP Detection & Validation

```
traffictop_get_real_ip() [shortlink-ip.php:28]
    ├─ 1. Check if REMOTE_ADDR is in Cloudflare CIDR (15 blocks)
    │     → Use HTTP_CF_CONNECTING_IP (validated with filter_var)
    ├─ 2. If traffictop_trust_reverse_proxy enabled
    │     → Use HTTP_X_FORWARDED_FOR (first IP) or HTTP_X_REAL_IP
    └─ 3. Default: $_SERVER['REMOTE_ADDR'] (TCP, cannot spoof)

traffictop_validate_ip() [shortlink-ip.php:100]
    ├─ Blocked IPs: 1.1.1.1, 8.8.8.8, 127.0.0.1, etc. (DNS resolvers)
    ├─ Private ranges: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, etc.
    ├─ Datacenter ranges: AWS, Google Cloud, DigitalOcean, Vultr, Linode, OVH
    └─ Risk score >= 70 → BLOCK

traffictop_check_ip_api() [shortlink-ip.php:275]
    ├─ API: http://ip-api.com/json/{ip}?fields=status,proxy,hosting,mobile,isp,org,as
    ├─ Whitelisted: iCloud Private Relay, Apple Relay, Cloudflare WARP
    ├─ VPN keywords: vpn, private, anonymous, hide, tunnel, nord, express, surfshark, etc.
    ├─ Scoring: proxy=+60, VPN=+50, hosting=+40
    └─ Mobile networks: risk_score -= 20 (reduce false positives)
```

### Flow 9c: Fraud Score Breakdown

```
traffictop_calculate_fraud_score() [behavior-analytics.php:430]

DEVICE (max +50):
  Bot detected:              +50
  Screen size = 0:           +25 (headless browser)
  Screen < 300px:            +15
  Viewport > Screen:         +10

BEHAVIOR (max +30):
  No mouse movement (desktop): +30
  < 5 mouse movements:        +15
  No scroll (long page):      +10
  Mobile no touch/mouse:      +20

TIME (max +25):
  Time < 5 seconds:           +25
  Time 5-10 seconds:          +10
  Idle ratio > 95%:           +15
  Page hidden > visible:      +10

NETWORK (max +30):
  Datacenter IP:              +30
  VPN/Proxy:                  +20

FINGERPRINT (max +30):
  No canvas hash:             +10
  WebGL disabled:             +5
  DevTools open:              +15
  Suspicious (multi-user):    +20

IP REPUTATION:
  avg_fraud_score > 60:       +15

Risk Levels: <20=safe, 20-39=low, 40-69=medium, >=70=high
Auto-block: requires 2+ fraud incidents per IP
```

## CONFIGURABLE SETTINGS (wp_options)

### Giá & Reward (đơn vị VNĐ)
| Setting Key | Default | Mô tả |
|------------|---------|-------|
| `traffictop_keyword_price_1step` | 1200 | Customer trả / view (keyword 1step) |
| `traffictop_keyword_price_2step` | 1500 | Customer trả / view (keyword 2step) |
| `traffictop_keyword_price_nocode` | 1200 | Customer trả / view (keyword nocode) |
| `traffictop_direct_price_1step` | 1200 | Customer trả / view (direct 1step) |
| `traffictop_social_price_1step` | 1200 | Customer trả / view (social 1step) |
| `traffictop_keyword_user_1step` | 800 | User nhận / view (keyword 1step) |
| `traffictop_keyword_user_2step` | 1000 | User nhận / view (keyword 2step) |
| `traffictop_keyword_user_nocode` | 800 | User nhận / view (keyword nocode) |
| `traffictop_direct_user_1step` | 500 | User nhận / view (direct 1step) |
| `traffictop_social_user_1step` | 700 | User nhận / view (social 1step) |
| `traffictop_keyword_user_reward_percent` | 80 | % của price_per_view → user_reward |

### Campaign & Balance
| Setting Key | Default | Mô tả |
|------------|---------|-------|
| `traffictop_customer_min_balance` | 20000 | Min balance để campaign hoạt động |
| `traffictop_widget_default_countdown` | 30 | Countdown hiển thị trên widget (giây) |
| `traffictop_verify_code_expiry` | 600 | Code hết hạn sau 10 phút (giây) |
| `traffictop_min_withdrawal` | 50000 | Số tiền rút tối thiểu |
| `traffictop_min_deposit_amount` | 50000 | Số tiền nạp tối thiểu |
| `traffictop_deposit_tiers` | JSON | Bonus tiers [{amount, bonus%}] |

### Security & IP
| Setting Key | Default | Mô tả |
|------------|---------|-------|
| `traffictop_shortlink_ip_limit_24h` | 5 | Max verified visits / IP / ngày |
| `traffictop_max_tasks_per_ip_per_day` | 10 | IP daily limit |
| `traffictop_detect_ip_change` | 1 | Detect IP thay đổi giữa session |
| `traffictop_detect_vpn_proxy` | 1 | Bật VPN/proxy detection |
| `traffictop_block_proxy_ip` | 1 | Block proxy IPs |
| `traffictop_block_vpn_ip` | 1 | Block VPN IPs |
| `traffictop_block_datacenter_ip` | 0 | Block datacenter IPs |
| `traffictop_block_fraud_reward` | 1 | Không trả reward cho fraud |
| `traffictop_trust_reverse_proxy` | false | Trust X-Forwarded-For header |

### DDoS Protection
| Setting Key | Default | Mô tả |
|------------|---------|-------|
| `traffictop_ddos_global_rate` | 10 | Max req/giây/IP |
| `traffictop_ddos_burst_limit` | 30 | Max req/10 giây/IP |
| `traffictop_ddos_sustained_limit` | 300 | Max req/60 giây/IP |
| `traffictop_ddos_violation_threshold` | 5 | Violations trước khi block |
| `traffictop_ddos_block_duration` | 300 | Block duration đầu tiên (giây) |
| `traffictop_ddos_whitelist` | '' | Whitelist IPs (newline-separated) |
| `traffictop_blocked_referrers` | '' | Blocked referrers (newline-separated) |

### Low Balance Alerts
| Setting Key | Default | Mô tả |
|------------|---------|-------|
| `traffictop_low_balance_alert_enabled` | 1 | Bật alert |
| `traffictop_low_balance_threshold` | 20 | Alert khi balance < X% of min |
| `traffictop_low_balance_min_amount` | 10000 | Min amount để check |
| `traffictop_low_balance_email_enabled` | 1 | Gửi email alert |
| `traffictop_low_balance_popup_enabled` | 1 | Hiện popup trên dashboard |
| `traffictop_low_balance_popup_frequency` | 2 | Max popups / session |
| `traffictop_low_balance_popup_interval` | 6 | Giờ giữa các popup |

### Cleanup Retention (ngày)
| Setting Key | Default | Mô tả |
|------------|---------|-------|
| `traffictop_cleanup_expired_logs` | 30 | Task logs hết hạn |
| `traffictop_cleanup_old_shortlinks` | 7 | Shortlink sessions cũ |
| `traffictop_cleanup_read_notifications` | 30 | Notifications đã đọc |
| `traffictop_cleanup_daily_submissions` | 30 | Daily submissions |
| `traffictop_cleanup_old_visits` | 30 | Visits cũ (non-financial) |
| `traffictop_cleanup_old_behavior` | 14 | Behavior analytics |
| `traffictop_cleanup_deleted_campaigns` | 30 | Campaigns đã xóa |
| `traffictop_cleanup_admin_notifications` | 30 | Admin notifications |
| `traffictop_inactive_user_days` | 10 | Ngày inactive trước khi xóa user |

### Other
| Setting Key | Default | Mô tả |
|------------|---------|-------|
| `traffictop_turnstile_enabled` | 1 | Cloudflare Turnstile captcha |
| `traffictop_smtp_enabled` | 0 | SMTP cho email |
| `traffictop_imgbb_api_key` | '' | ImgBB API key cho upload ảnh |
| `traffictop_ipapi_enabled` | 1 | IP-API cho fraud detection |

## OTHER IMPORTANT SYSTEMS

### 1. User Ban/Freeze System
**File:** `includes/user-management.php` (lines 463-525)
- `traffictop_ajax_ban_user()` — Ban user trong **database transaction**:
  1. Set user meta: `traffictop_banned = true`
  2. Find ALL pending/approved withdrawals via `FOR UPDATE` lock
  3. Reject each withdrawal: status → `rejected`, admin_note = 'Tự động hủy do tài khoản bị cấm'
  4. Tạo refund transaction (type=`refund`) cho mỗi withdrawal bị reject
  5. COMMIT transaction
- `traffictop_ajax_unban_user()` — Delete meta `traffictop_banned`
- **LƯU Ý:** Ban user → withdrawal bị reject + refund → ảnh hưởng balance. Phải hiểu flow withdrawal trước khi sửa.

### 2. Inactive User Auto-Cleanup
**File:** `includes/user-management.php` (lines 20-114) → `traffictop_cleanup_inactive_users()`
- Xóa user đăng ký > X ngày (setting: `traffictop_inactive_user_days`, default 10) nếu **TẤT CẢ** điều kiện:
  - KHÔNG có completed tasks (status != 'approved' trong `traffictop_user_tasks`)
  - KHÔNG có withdrawals
  - Balance = 0, total_earned = 0
  - KHÔNG phải administrator
  - KHÔNG phải customer role
  - KHÔNG bị soft-deleted (no `traffictop_deleted` meta)
  - KHÔNG có task logs hoặc transactions
- **Xóa khi cleanup:** `wp_delete_user()` + records từ: `user_tasks`, `user_balance`, `withdrawals`, `transactions`, `task_logs`, `notifications`, `daily_submissions`, `shortlink_clicks`

### 3. Customer Management
**File:** `includes/customer-management.php`
- `traffictop_login_as_customer()` (line 102) — Admin impersonation:
  1. Verify admin + customer exists + has 'customer' role + not soft-deleted
  2. Store admin ID in user meta: `switched_from_admin`
  3. Clear auth cookie → set customer auth cookie
  4. Redirect to `/customer-dashboard/`
- `traffictop_ban_customer()` (line 179) — Set meta `customer_banned = true` (ngăn tạo campaign, deposit)
- `traffictop_unban_customer()` (line 194) — Delete meta `customer_banned`
- `traffictop_delete_customer()` (line 209) — Soft delete + ban
- `traffictop_permanent_delete_customer()` (line 271) — **Phải soft-delete trước** + cannot delete admin → `wp_delete_user()`
- `traffictop_auto_delete_old_customers()` (line 349) — Auto-cleanup after 30 days (**CURRENTLY DISABLED**)

### 4. Rate Limiting Specifics
**File:** `includes/shortlink-ip.php` (lines 544-555) → `traffictop_rate_limit_check()`

| Endpoint | Limit | Window |
|----------|-------|--------|
| `verify_code` | 10 req | 1 phút |
| `get_code` | 20 req | 1 phút |
| `shortlink_click` | 30 req | 1 phút |
| `widget_verify` | 30 req | 1 phút |
| `report_issue` | 5 req | 5 phút |
| Default | 60 req | 1 phút |
| Deposit | 3 req/user | 1 phút |

**Implementation:** Transient key `traffictop_ratelimit_{action}_{md5(identifier)}`, returns `{allowed, remaining, retry_after/reset_at}`

### 5. IP Detection Priority
**File:** `includes/shortlink-ip.php` (lines 28-92) → `traffictop_get_real_ip()`
```
1. Check request from Cloudflare IP range (15 CIDR blocks) → dùng HTTP_CF_CONNECTING_IP
2. Nếu traffictop_trust_reverse_proxy → dùng HTTP_X_FORWARDED_FOR (first IP) hoặc HTTP_X_REAL_IP
3. Default → $_SERVER['REMOTE_ADDR'] (TCP connection, cannot be spoofed)
```

### 6. VPN/Proxy Detection & Fraud Scoring
**Files:** `includes/ip-fraud.php`, `includes/behavior-analytics.php`
- `traffictop_check_ip_fraud()` — Detect VPN/proxy via external API (ip-api rate: 45 req/min)
- `traffictop_calculate_fraud_score()` — Score 0-100:

| Factor | Points | Condition |
|--------|--------|-----------|
| Bot detection | +50 | `is_bot = 1` |
| No screen size | +25 | Headless browser |
| Small screen | +15 | < 300x300 |
| Viewport > Screen | +10 | Anomaly |
| No mouse movements | +30 | Non-mobile |
| Few mouse movements | +15 | < 5 (non-mobile) |
| No scroll | +20 | Long page |
| No clicks | +20 | — |
| No keystrokes | +10 | — |
| No touch (mobile) | +25 | Mobile device |
| Known fraud fingerprint | +40 | Canvas hash match |
| Multi-account fingerprint | +50 | Same fingerprint, different users |
| VPN/Proxy detected | +60 | External API |
| Datacenter IP | +40 | — |

- **Settings:** `traffictop_detect_vpn_proxy`, `traffictop_block_proxy_ip`, `traffictop_block_vpn_ip`, `traffictop_block_datacenter_ip`
- **Tables:** `wp_traffictop_behavior_analytics`, `wp_traffictop_device_fingerprints`, `wp_traffictop_ip_reputation`

### 7. DDoS Protection
**File:** `includes/anti-ddos.php`
- **3-tier rate check:** Global (10/sec) → Burst (30/10sec) → Sustained (300/60sec) — all configurable
- **Progressive blocking:** Ban duration doubles mỗi lần vi phạm (300s → 600s → ... → max 24h)
- `traffictop_ddos_check()` — Main entry point
- `traffictop_ddos_block_ip()` — Block IP tạm thời
- `traffictop_ddos_permanent_block()` — Block vĩnh viễn
- **Blocked referrer cache:** File `/cache/blocked-referrers.php` (PHP array, auto-generated)
  - Default blocked: `lu88.pro` (hardcoded)
  - Custom: từ option `traffictop_blocked_referrers`
- Tracks violations per IP trong `wp_traffictop_ddos_blocks` table

### 8. Shortlink Creation & Alias System
**File:** `includes/shortlink-functions.php` (lines 184-232)
- `traffictop_create_user_shortlink()` — Insert: user_id, code, alias, original_url, fallback_url, created_at
- `traffictop_generate_unique_shortcode()` (lines 237-254) — 6-char alphanumeric, loop until unique
- `traffictop_generate_visit_verify_code()` (lines 215-238) — 8-char hex code, stored with 600s transient expiry
- `traffictop_get_shortlink_by_code_or_alias()` — Lookup bằng code HOẶC alias
- **Table:** `wp_traffictop_user_shortlinks` (UNIQUE constraints trên cả `code` và `alias`)
- **Alias:** URL-safe (sanitized), optional custom slug cho shortlink

### 9. Notification System
**File:** `includes/user-management.php` (lines 194-333)
- `traffictop_create_notification($user_id, $type, $title, $message, $data=[])` — Line 194
  - Sanitizes: title via `sanitize_text_field()`, message via `wp_kses()` (allows `<br>`, `<strong>`, `<em>`)
  - `$data` = JSON object cho extra info
- `traffictop_get_user_notifications($user_id, $limit=10, $unread_only=false)` — Line 216
- `traffictop_get_unread_notification_count($user_id)` — Line 235
- `traffictop_mark_notification_read($notification_id)` — Line 248
- `traffictop_mark_all_notifications_read($user_id)` — Line 262
- **Table:** `wp_traffictop_notifications` (user_id, type, title, message, data JSON, is_read, created_at)

### 10. Email & Low Balance Alerts
**Files:** `includes/email-notifications.php`, `includes/low-balance-alerts.php`
- **Deposit email:** HTML email khi customer submit deposit
- **Low balance alert:** Hourly cron (`traffictop_check_low_balance_customers`):
  - Check ALL customers: balance < threshold (% of min, default 20%)
  - Gửi email (`traffictop_low_balance_email_enabled`) + popup trên dashboard (`traffictop_low_balance_popup_enabled`)
  - Max `traffictop_low_balance_popup_frequency` (2) popups, mỗi `traffictop_low_balance_popup_interval` (6h)
  - Chỉ alert 1 lần/ngày/customer (tracked in `wp_traffictop_low_balance_alerts`)

### 11. Daily Check-in Reward
**File:** `includes/checkin.php` (lines 19-94)
- `traffictop_get_checkin_reward()` — Reward theo streak day:

| Streak Day | Reward |
|-----------|--------|
| Day 1 | 100đ |
| Day 2 | 200đ |
| Day 3 | 300đ |
| Day 4 | 400đ |
| Day 5 | 500đ |
| Day 6 | 600đ |
| Day 7 | **1,000đ** (bonus) |
| Day 8+ | Cycles lại từ Day 1 |

- **Streak logic** (`traffictop_get_user_streak()`):
  - Checked in hôm nay → `can_checkin = false`, giữ streak
  - Checked in hôm qua → `can_checkin = true`, tiếp tục streak
  - Missed > 1 ngày → Reset `streak_day = 0`
- **Table:** `wp_traffictop_daily_checkins` (user_id, checkin_date, streak_day)

### 12. Counter Sync (Chống Drift)
**File:** `includes/cron-cleanup.php` (lines 1266-1423)
- `traffictop_sync_shortlink_counters()` — Recalculate total_clicks, total_completed, total_earnings từ visits
- `traffictop_sync_campaign_counters()` — Recalculate campaign.completed từ visits
- **Mục đích:** Fix counter drift sau cleanup operations

### 13. Deposit Management
**File:** `includes/deposit-management.php`
- **Creation** (`customer_create_deposit`, line 13):
  - Rate limit: 3 req/min/user (transient `traffictop_deposit_rate_X`)
  - Validate: min 50,000đ, max 100,000,000đ
  - Bonus tier: Sort by amount ASC, monotonic constraint (higher tier bonus >= lower)
  - Bonus = `floor(amount × bonus_percent / 100)`
  - Insert `wp_traffictop_customer_deposits` status='pending'
- **Approval** (`admin_approve_deposit`, line 220):
  - **In transaction:** Lock deposit FOR UPDATE → check status='pending'
  - Lock customer_balance FOR UPDATE → atomic `balance += amount + bonus`
  - Update deposit: status='approved', approved_by, approved_at
  - Log customer_transaction type='deposit'
  - COMMIT

### 14. Image Upload
**File:** `includes/class-google-drive-upload.php`
- `traffictop_upload_to_imgbb()` → ImgBB API (cần `traffictop_imgbb_api_key`)
- Fallback → WordPress media library
- **Dùng cho:** Campaign screenshots, admin uploads
- **Test endpoint:** AJAX `traffictop_ajax_test_imgbb` (admin-dashboard.php:262)

### 15. Admin Dashboard Internals
**File:** `includes/admin-dashboard.php`
- `traffictop_ajax_run_unit_tests()` (line 129) — Chạy `/tests/unit/run.php` via PHP CLI, parses "Results: X passed, Y failed"
- `traffictop_ajax_update_submission_note()` (line 218) — Admin notes on `traffictop_user_tasks.admin_note` (dynamic column, created via ALTER TABLE if missing)
- `traffictop_ajax_update_database()` (line 59) — Run pending migrations via `Taskify_Migrator`
- `traffictop_ajax_clear_db_cache()` (line 104) — Delete transients matching `%_transient_traffictop_%`

### 16. Page-Unlock Session Management
**File:** `page-unlock.php` (lines 9-132)
- **Session restore:** URL param `?sid={session_id}` → load visit from DB → restore `$_SESSION`
- **Always fresh:** Load visit from DB (NOT session cache) để đảm bảo data mới nhất
- **Campaign fallback:** Nếu campaign inactive/paused → random active campaign → update visit's campaign_id
- **No campaign:** Nếu KHÔNG có active campaign nào → redirect home `?error=no_campaign`
- **Session storage:** `$_SESSION['traffictop_shortlink']`, `$_SESSION['traffictop_campaign']`, `$_SESSION['traffictop_session_id']`

## UNIT TESTS

### Cấu trúc
```
tests/unit/
├── bootstrap.php          - Mock WordPress environment (MockWpdb, assertions)
├── run.php                - Test runner (chạy tất cả test-*.php files)
├── test-balance-calculation.php
├── test-balance-drift.php
├── test-campaign-eligibility.php
├── test-campaign-lifecycle.php
├── test-cleanup-safety.php
├── test-hourly-adjustment.php
├── test-ip-validation.php
├── test-rate-limiting.php
├── test-security-checks.php
├── test-verify-and-pay.php
├── test-visit-session.php
└── test-withdrawal-flow.php
```

### Chạy tests
- **CLI:** `php tests/unit/run.php`
- **Admin UI:** Settings > Database Tools > "Chạy Tests"
- **Kết quả hiện tại:** 256 tests passed, 12 suites, 0 failures

### Lưu ý hosting
- `passthru()` bị **disable** trên production hosting
- `run.php` dùng fallback chain: `exec()` → `shell_exec()` → `include`
- AJAX handler (`includes/admin-dashboard.php`) cũng dùng fallback chain tương tự
- LUÔN dùng `PHP_BINARY` thay vì `'php'` để đảm bảo đúng PHP path

### Tests kiểm tra được gì
- Logic tính balance (công thức, edge cases, double-counting prevention)
- Withdrawal rules (minimum, exceed balance, status transitions, refund logic)
- Campaign distribution (weighted random, daily limits, customer balance check)
- Security (timezone consistency, traffic type defaults, rate limiting)
- IP validation, visit session, cleanup safety

### Tests KHÔNG kiểm tra được gì
- SQL queries có đúng syntax/columns không (dùng MockWpdb)
- Data thực tế trên production
- Columns có tồn tại trên database thật không
- Network/AJAX behavior

## TRIẾT LÝ ANTI-FRAUD

> Áp dụng cho `traffictop_ajax_admin_fraud_check()` (popup Kiểm tra gian lận của lệnh rút) và mọi logic scoring rủi ro publisher trong tương lai.

### User TỰ NHIÊN = MIX của signals với % vừa phải

Real users đa dạng: NAT (gia đình), CGNAT (carrier mobile), café công cộng, công ty.
- Một số đổi WiFi/4G giữa flow → **Change IP có vài cái** (1-7%)
- Một số dùng adblock plugin → **Adblock > 0** (real desktop users)
- Một số IP heavy NAT → **Max IP / IP >3 có** (NAT bình thường, đặc biệt CGNAT mobile)
- **Completion rate 30-80%** (organic dropout tự nhiên)

### User GIAN LẬN = QUÁ CLEAN hoặc QUÁ EXTREME

- **Bot quá sạch**: signals = 0 với volume cao (1000+ views mà 0 adblock, 0 change_ip, 0 ip_over_3)
- **Bot quá hoàn hảo**: completion >90% (real users dropout tự nhiên không thể >90%)
- **Bot extreme 1 signal**: change_ip 800/1000 (VPN switching) hoặc reuse 50+ visit/IP (farm)
- **Self-click**: > 50% referer từ dashboard nội bộ (`/user`, `/customer`, `/wp-admin`)
- **Bot farm reuse**: > 30 visit/IP đạt daily limit (không CGNAT real nào tạo được pattern này)

### Bảng tổng hợp ngưỡng scoring (file `includes/admin-dashboard.php` → `traffictop_ajax_admin_fraud_check()`)

| Rule | Score | Min volume |
|---|---|---|
| Completion ≤10% / <20% / <30% / >90% | +4 / +2 / +1 / +1 | paid_views ≥ 20 |
| Change IP % >30% / >15% / >7% | +4 / +2 / +1 | paid_views ≥ 50 |
| Max IP % >30% / >15% | +2 / +1 | paid_views ≥ 100 |
| **Reuse ratio (max_ip/ip_over_3) >30 / >15 / >8** | **+5** / +2 / +1 | max_ip ≥ 30 |
| IP concentration (ip_over_3/unique_paid_ips) >50% / >25% | +3 / +1 | unique_paid_ips ≥ 20 |
| **Self-click % >50% / >20% / >5%** | **+5** / +2 / +1 | paid_views ≥ 20 |
| Too clean (≥2/3 signals = 0) | +2 | paid_views > 100 |
| **Adblock có (trust bonus)** | **−1** | paid_views ≥ 50 |

**Map**: `≥5 = HIGH`, `≥3 = MEDIUM`, `≥1 = LOW`, `0 = SAFE`.

**Bypass bỏ qua** — real users hiếm khi bypass, signal nhiễu hơn useful.

### Nguyên tắc khi sửa scoring
- **Thresholds proportional (%)**, không absolute count → scale với volume khác nhau
- **Min volume guards** tránh false positive với sample nhỏ
- **Adblock = bonus, không penalty** — vắng adblock OK với mobile traffic chủ yếu
- **Period scope** tính từ `prev_wd.created_at` → `wd.created_at` cho TẤT CẢ queries (visits, transactions, IP, sources, shortlinks) — tránh mọi lệnh rút của cùng user ra số liệu giống hệt
- **Filter `reward_paid=1`** cho IP/Shortlink/Source tables — loại visits verified nhưng không trả tiền (bypass, IP changed, adblock detected)




