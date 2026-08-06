# Bài học: Cầu nối traffic dethitoanthpt.com ⇄ sitetop.net / lentop.one

> Đúc kết từ chuỗi sự cố ngày **09/07/2026** (đồng bộ lượt hoàn thành giữa nguồn và đối tác).
> Tài liệu này giống nhau trên các repo của hệ — NGUỒN: dethitoanthpt.com, hoclaixe.io,
> toanpro.net, hocgioitoan.com · POOL: sitetop-theme, lentop.one. Đọc trước khi động vào
> bất kỳ code cầu nối nào.

---

## 0. Vai trò từng site (ai là ai)

| Site | Vai trò | Code | Hạ tầng |
|------|---------|------|---------|
| **dethitoanthpt.com** | **NGUỒN** — tạo camp, đẩy job, nhận kết quả để **trừ tiền advertiser** | theme `toan-thpt` → `inc/traffic/lentop-bridge.php` | Có **WAF openresty** đứng trước domain |
| **sitetop.net** | **ĐỐI TÁC (pool)** — nhận job, phục vụ traffic bằng user của mình, báo lượt hoàn thành | plugin `ttp-lentop-bridge` (nguồn ở `bridge/lentop-one/`) | **KHÁC server** với nguồn |
| **lentop.one** | **ĐỐI TÁC (pool)** — như trên | plugin `ttp-lentop-bridge` | **CÙNG server** với nguồn |

Bảo mật giữa 2 bên: **HMAC-SHA256** ký trên `timestamp . "." . body`. Hai sổ tiền độc lập:
pool trừ số dư *tài khoản liên kết* của nó; nguồn trừ số dư *advertiser* của nó (qua kết quả trả về).

---

## 1. LUẬT VÀNG: chiều đi quyết định, KHÔNG phải dữ liệu

Một tích hợp cross-site có **HAI chiều**, và **bên NHẬN** quyết định chiều nào bị chặn:

| Tín hiệu | Chiều | Kết quả |
|----------|-------|---------|
| Đẩy job (nguồn→pool) | nguồn **gọi ra** | ✅ luôn thông (không ai chặn nguồn) |
| Widget hỏi nhiệm vụ / "Đang làm" | nguồn **gọi ra** pool | ✅ thông |
| **Postback "Hoàn thành" (pool→nguồn)** | pool **gọi vào** nguồn | ❌ bị WAF của NGUỒN chặn (chỉ với pool khác-server) |

> **Triệu chứng kinh điển:** "Đang làm" + thông tin camp/từ khoá hiện ĐÚNG, nhưng "Hoàn thành"
> không bao giờ cập nhật. → Đừng nghi dữ liệu. Cùng một dữ liệu, khác **chiều**: cái hiện được
> là do NGUỒN tự đi hỏi (chiều ra); cái không hiện là do POOL đẩy vào (chiều vào bị chặn).

**Vì sao lentop.one không dính:** nó **cùng server** với nguồn → postback đi nội bộ, không qua WAF edge.
sitetop.net khác server → mọi request vào nguồn phải qua openresty.

---

## 2. Đọc ĐÚNG mã lỗi HTTP trước khi chẩn đoán

- **`403 Forbidden`** = chặn IP / cấm truy cập.
- **`415 Unsupported Media Type`** = request ĐÃ tới server nhưng bị từ chối theo **kiểu nội dung/hình dạng** (WAF), **KHÔNG phải chặn IP**.

> Sai lầm đã mắc: thấy 415 → đổ cho "chặn IP" → yêu cầu hosting whitelist IP. Hosting trả lời
> "không chặn IP" — **và họ đúng**. 415 là luật WAF soi request, không phải firewall IP.
> Body lỗi `openresty/1.31.1.1` chính là chữ ký của lớp WAF — đọc nó ra là biết thủ phạm.

---

## 3. WAF chặn server-to-server theo HÌNH DẠNG request, không theo IP

Thủ phạm cuối cùng: **User-Agent**. `wp_remote_post` mặc định gửi UA `WordPress/x.x; https://site`
→ nhiều lớp openresty/anti-bot chặn (trả 415/403). Trình duyệt thật (UA Chrome + header `Accept`)
thì cho qua → đó là lý do widget AJAX của browser vào nguồn chạy tốt mà server-to-server thì tắc.

**Cách xử:** request server-to-server tới endpoint sau WAF phải **giả trình duyệt**:
`User-Agent` Chrome + `Accept` + `Accept-Language`, bỏ header `Expect`.

---

## 4. GIẢI PHÁP GỐC RỄ: PULL thay cho PUSH (khi bên nhận có WAF)

Thay vì bắt pool **đẩy vào** nguồn (chiều bị chặn), cho **nguồn tự đi KÉO** từ pool
(chiều đi-ra — chiều mà job-push và widget-verify vẫn dùng mỗi ngày):

```
Cũ (hỏng):   pool  --postback-->  nguồn        (bị WAF nguồn chặn)
Mới (đúng):  nguồn --pull/hỏi-->  pool          (chiều ra, không bao giờ bị chặn)
```

- Plugin (pool) mở endpoint `lentop/v1/pull`: trả các lượt `verified + customer_paid` của job cầu nối
  (cửa sổ 48h, con trỏ `after_id` + lưới 5 phút bắt lượt hoàn thành muộn), kèm dấu xác nhận `ttplb=1`.
- Theme (nguồn): cron 1 phút + kích theo traffic (shutdown) gọi `/pull` mỗi pool → ghi bằng
  `ttp_lentop_record_view` (idempotent).

> **Nguyên tắc chuyển giao:** khi một chiều thông và chiều ngược bị chặn bởi hạ tầng bên nhận,
> **đảo luồng để chỉ dùng chiều thông** — đừng cố đục tường. Đây là kiến trúc bền nhất.

Postback đẩy-vào vẫn giữ làm lớp dự phòng (chạy được cho pool cùng-server như lentop).

---

## 5. Idempotency là BẮT BUỘC — chống trừ tiền 2 lần

Có retry + giao chồng lấn (pull kéo lại 200 lượt/phút, và pool cùng-server còn push) → **cùng một
lượt có thể tới nhiều lần / nhiều kênh**. Chống trùng phải tuyệt đối:

1. **`session_id` UNIQUE + tất định**: `ttp_lentop_view_sid(source, event_id)` với `event_id` =
   `"<self_host pool>:<visit_id>"`. Cột `session_id` phải có **UNIQUE KEY**.
2. **Source phải CANONICAL & giống nhau mọi kênh**: luôn lấy `source` từ **tiền tố event_id**
   (`ttp_lentop_src_host('', $event_id)`), **KHÔNG** lấy từ header (push) hay `$peer['host']` (pull)
   — nếu 2 kênh ra chuỗi khác nhau → 2 `session_id` → 2 dòng → **trừ 2 lần**.
3. **Trừ tiền chỉ SAU khi "giành" được dòng**: chuyển `step='verified'` bằng
   `UPDATE ... WHERE step!='verified'` (hoặc `INSERT IGNORE`). Lượt đã verified → trả `duplicate`,
   **không trừ lại**. `ttp_adv_add_tx` KHÔNG tự dedupe theo visit (ref_id = campaign), nên toàn bộ
   chống-trùng dựa vào gate này + `session_id` nhất quán.

---

## 6. KHÔNG tin `HTTP 200` trần

Một 200 từ **cache/WAF** (handler chưa hề chạy) trông y như thành công → đánh dấu "đã gửi" →
**mất lượt vĩnh viễn** (không retry, advertiser không bị trừ, dòng kẹt "Đang làm").

**Cách xử:** handler trả **dấu xác nhận** (`ttplb=1`) trong body; bên gửi chỉ tính thành công khi
2xx **VÀ** thấy dấu này. Thiếu dấu → coi là thất bại, thử kênh khác / retry.

---

## 7. Bẫy double-encode khi gửi qua GET

GET được dùng để né luật-soi-body của WAF (GET không có body). Nhưng:

```php
// SAI — double encode: add_query_arg tự urlencode LẦN NỮA → bên nhận giải JSON hỏng
add_query_arg( array( 'payload' => rawurlencode( $body ) ), $url );

// ĐÚNG — đưa $body THÔ, để add_query_arg encode đúng 1 lần
add_query_arg( array( 'payload' => $body, '_n' => $cache_buster ), $url );
```

Bên nhận (`ttp_lentop_req_body`) nên có fallback thử `urldecode/rawurldecode` cho chắc.
Kèm `_n` cache-buster + `nocache_headers()` để WAF không cache GET.

---

## 8. Column-safety (bài học cũ, TÁI KHẲNG ĐỊNH — thủ phạm giai đoạn đầu)

Query quét postback hardcode `v.onsite_time`, `v.completion_time` — nhưng bảng visits của
production (tạo từ lâu) **có thể thiếu** 2 cột này → SQL lỗi → `get_results` trả **rỗng** →
**KHÔNG postback nào được gửi** dù visit verified → nhìn như "không có gì để gửi" chứ không ra lỗi.

**Cách xử:** `SHOW COLUMNS` trước, thiếu thì thay bằng `NULL AS onsite_time` (lấy từ campaign / để NULL).

---

## 9. Cổng captcha (Turnstile) chặn thưởng lượt cầu nối

Lượt của camp cầu nối nhận mã qua **widget của SITE NGUỒN** (server-side, HMAC) → **iframe captcha
của pool không bao giờ chạy** → transient `sitetop_captcha_ok_{sid}` không được set →
`verify_and_pay` chặn thưởng (`captcha_unverified`) dù khách hàng vẫn bị trừ tiền.

**Cách xử:** miễn cổng captcha cho lượt có mã cấp qua cầu nối — nhận diện bằng transient
`lentop_widget_code_ready_{sid}` HOẶC `trafficop_widget_code_ready_{sid}` (chỉ plugin bridge ghi 2
tiền tố này; client không giả được).

---

## 10. Khớp GIỜ 2 bảng Visits

Cột "Bắt đầu/Kết thúc" của nguồn phải lấy **giờ thật của pool** (`started_at`/`ended_at` trong
payload = `created_at`/`verified_at` bên pool), KHÔNG lấy giờ nhận tại nguồn. Guard `ttp_lentop_dt`:
sai định dạng hoặc lệch > 48h → fallback giờ nguồn. "Đang làm" và "Hoàn thành" phải **cùng
`session_id`** để hoàn thành cập nhật đúng dòng (không tạo dòng mồ côi).

---

## 11. Nhận diện camp cầu nối trong admin (cột "Nguồn camp")

Marker bền nhất, hoạt động trên MỌI pool: **tiền tố tiêu đề `[host#ref]`** mà plugin gắn cố định
lúc tạo job (`ttplb_create_campaign`). Đừng dùng `user_can(manage_options)` — trên lentop camp cầu
nối tạo dưới **tài khoản liên kết (customer)**, không phải admin → role check sai.

```php
if ( preg_match( '/^\[([^#\]]+)#\d+\]/', $camp_title, $m ) ) { $src = $m[1]; /* vd dethitoanthpt.com */ }
else { $src = 'lentop.one'; /* hoặc sitetop.net — camp nội bộ */ }
```

---

## CHECKLIST khi cầu nối "không đồng bộ"

1. **Deploy đã vào `main` chưa?** (`git merge-base --is-ancestor <commit> origin/main`). Chưa deploy → mọi phân tích khác là nhiễu.
2. **Chiều nào hỏng?** "Đang làm" chạy = chiều ra OK. "Hoàn thành" hỏng = chiều vào (pool→nguồn) bị chặn.
3. **Đọc panel 🩺** trên cả 2 phía: bên gửi thấy `HTTP mấy`; đọc **body lỗi** (chữ ký WAF).
   - `415/403 openresty` → WAF hình-dạng-request → giả trình duyệt (UA) / chuyển GET / **chuyển PULL**.
   - cURL error / timeout → egress bị chặn phía pool.
   - `200 nhưng không ghi` → thiếu dấu `ttplb` (cache) hoặc payload hỏng (double-encode).
4. **Tồn đọng nhưng "pending=1"?** → các lượt đã cháy 5 lần retry (bị loại khỏi đếm). Xem "Đã bỏ cuộc".
5. **Sau khi thông:** bấm "Gửi lại visit thất bại/chưa xác nhận" → tồn đọng 48h tự cuốn về.
6. **Kiểm tra tiền:** số dòng "Hoàn thành" 1 camp = `completed` = số giao dịch `campaign_view` của advertiser đó.

## Các khoá/marker quan trọng (tra nhanh)

| Thứ | Giá trị | Ý nghĩa |
|-----|---------|---------|
| `session_id` lượt cầu nối | `ttp_lentop_view_sid( prefix(event_id), event_id )` | UNIQUE, tất định, giống nhau mọi kênh |
| `event_id` | `"<self_host pool>:<visit_id>"` | Khoá toàn cục 1 lượt |
| Dấu xác nhận | `ttplb = 1` trong response | Handler đã chạy thật (chống 200-cache) |
| Miễn captcha | transient `{lentop_,trafficop_}widget_code_ready_{sid}` | Mã cấp qua cầu nối |
| Marker camp cầu nối | tiền tố tiêu đề `[host#ref]` | Nhận diện nguồn camp |
| Endpoint kéo | `POST/GET {pool}/wp-json/lentop/v1/pull` | Nguồn tự hỏi lượt hoàn thành |

---

## 12. Đa pool: chọn theo ĐỘ TƯƠI, không theo thứ tự cấu hình (13/07/2026)

**Sự cố:** camp dethito đẩy sang sitetop — khách làm đúng flow, widget hiện mã, nhưng nhập ở
page-unlock sitetop báo **"Code chưa sẵn sàng"**. Mã trên widget do **LENTOP** mint chứ không
phải sitetop (dò được vì rescue v1/v2 của sitetop không thấy mã ở đâu cả).

**Nguyên nhân:** khách (người test) đã làm CÙNG camp đó ở lentop trước đó (<2h, cùng mạng) → visit
dở còn nằm bên lentop. Widget nguồn hỏi các pool theo **thứ tự cấu hình** và lấy kết quả `found`
**đầu tiên** (cả `verify_collect` vòng precise lẫn `verify_any` vòng lỏng) → lentop (pool #1) khớp
IP/domain vào visit CŨ → "vơ" mất khách của sitetop → start/code đều proxy sang LENTOP → mã nằm
trong DB lentop → sitetop không biết mã. Trọng tài độ tươi trước đó chỉ so **nội bộ vs pool**,
chưa so **pool vs pool**.

**Fix (phía NGUỒN — dethito + hoclaixe):**
- `*_lentop_pick_freshest()`: giữa các ứng viên pool cùng khớp, chọn `age` nhỏ nhất (giây, đồng hồ
  pool — plugin v1.1.38+ trả kèm trong verify). Plugin cũ không trả age → xếp cuối. Match `session`
  vẫn thắng tuyệt đối, dừng hỏi ngay.
- `*_lentop_widget_verify_collect` + `*_lentop_widget_verify_any` (cả 2 vòng): gom kết quả của
  **mọi** pool rồi `pick_freshest` — không return ở pool trả lời trước.
- Widget L4: ứng viên IP đối tác đã CŨ (>120s) → hỏi thêm vòng lỏng và lấy bên tươi hơn (bịt ca
  pool ĐÚNG trượt IP-match vì dual-stack IPv4/IPv6, chỉ khớp được qua domain).

**Nguyên tắc:** visit TƯƠI nhất = phiên khách vừa mở page-unlock (pool reuse có reset `created_at`)
= pool mà khách sẽ quay về **nhập mã**. Chọn giữa nhiều ứng viên khớp-yếu (IP/domain) phải so
`age`; tuyệt đối không dựa thứ tự cấu hình hay thứ tự trả lời.

---

## 13. Máy đọc-cuộn: hết giờ ở BẤT KỲ pha nào cũng phải vào chế độ "kéo xuống lấy mã" + toast (14/07/2026)

> SỬA LẠI kết luận 13/07 (hiểu nhầm yêu cầu): máy đọc theo nhịp cuộn GIỮ cho MỌI camp — kể cả
> camp đẩy sang pool (đã hoàn tác nhánh `isPeer()`→`startCountdown`). Yêu cầu thật: giữ nguyên
> rào cuộn-đọc, chỉ sửa việc THIẾU toast hướng dẫn khi đếm về 0.

**Bug thật:** `readTick` chỉ xử lý `remaining=0` ở pha `downfinal`; hết giờ ở pha `down`/`reset`
(khách chưa từng chạm đáy trang) → giây về 0 mà KHÔNG chuyển sang chờ-kéo-xuống → nút kẹt "0"
IM LẶNG, không toast, khách không biết phải làm gì.

**Fix:** `readTimeUp(now)` gọi ở MỌI nhánh trừ giây (down/reset/up): `remaining<=0` →
`S.readWait=true` + nút hiện "↓" + toast "Đã đủ thời gian! Kéo xuống dưới cùng để lấy mã ↓"
(tự nhắc lặp ~2.8s); nhánh `readWait` sẵn có nhả mã khi khách chạm đáy trang (97%).

## 14. Trần traffic/ngày TOÀN HỆ (audit 13/07/2026)

Tổng lượt MỌI site (nguồn onsite + mọi pool) của 1 camp ≤ `daily_traffic`:
- **Payload đẩy job:** `daily_traffic` gửi pool = daily gốc − onsite nguồn hôm nay (KHÔNG chia đôi).
- **`record_view`:** đếm `daily_done` TOÀN HỆ sau insert; trả `daily_exhausted` cho pull/postback.
- **Charge gate off-by-one:** trừ tiền khi `done > limit`, KHÔNG phải `>=` — vì `daily_done` đã
  GỒM lượt hiện tại; dùng `>=` thì suất cuối (camp daily=1) không bao giờ được tính tiền.
- **Pause tức thì:** `*_lentop_daily_pause_kick($cid)` (throttle 60s/camp) đẩy 'pause' ngay từ
  pull-loop + postback khi `daily_exhausted`; reconcile 5 phút chỉ là lưới an toàn.
- **Giành suất NGUYÊN TỬ** trước khi trừ tiền: `UPDATE ... SET completed=completed+1 WHERE
  completed<quantity`; giành fail → rollback, không trừ.

## 15. Chính sách IP toàn hệ + IP test/admin (13/07/2026)

- **Phân phối:** 1 IP nhận được MỌI camp của hệ, miễn KHÔNG lặp camp đã làm TRONG NGÀY
  (step verified/code_shown; so prefix IPv4 /24 · IPv6 /64).
- **Thưởng:** tối đa **2 lượt trả tiền/IP/ngày/site** (clamp `$ip_limit` về [1,2]); lượt vượt →
  user KHÔNG được thưởng nhưng **khách hàng VẪN bị trừ tiền**.
- **Lặp CÙNG camp** trong ngày (lọt qua phân phối): không thưởng VÀ không trừ khách
  (`ip_repeat_same_campaign`).
- **IP test/admin (pool):** `*_is_test_whitelisted()` = đăng nhập `manage_options` HOẶC IP nằm
  trong option `shortlink_test_whitelist_ips` (hậu tố `*` = so prefix) → bỏ qua rotation /
  ip_changed / ip_limit / ip_repeat nhưng **VẪN trừ khách + trả thưởng** — để admin test
  end-to-end cầu nối như lượt thật.

## 16. Session bridge chết vì storage partitioning — bậc IP/domain phải tự đứng vững

Chrome (storage partitioning) + Safari ITP: iframe `/widget-bridge/` của CẢ nguồn lẫn pool đọc
localStorage **RỖNG** trong ngữ cảnh third-party → bậc SESSION thường xuyên trượt trên mobile.
Giảm thiểu: cookie `{pool}_sid` (`SameSite=None; Secure`) do page-unlock set + trang bridge echo
cookie (`Cache-Control: private, no-store`) — Safari vẫn chặn cookie third-party, chấp nhận.
**Hệ quả thiết kế:** các bậc IP/domain PHẢI kèm trọng tài ĐỘ TƯƠI (#12) và không được giả định
"bậc session chắc chắn chạy" để nới lỏng bậc dưới.

---

## 17. Auto-pause/resume KHÔNG qua hook → pool không dừng theo nguồn (14/07/2026)

**Sự cố:** camp tạm dừng ở NGUỒN (dethito) nhưng pool (lentop/sitetop) VẪN CHẠY.

**Nguyên nhân:** đổi status camp có 2 nhóm đường:
- **Thủ công** (admin/khách bấm dừng/chạy/duyệt): qua `*_adv_set_campaign_status()` → fire
  `*_campaign_status_changed` → `*_lentop_push()` đẩy pause/upsert sang pool NGAY. ✅
- **Tự động** (`*_adv_pause_campaigns` khi hết số dư · `*_adv_resume_campaigns` · `*_adv_remove` ·
  hoclaixe `*_adv_set_banned`): `$wpdb->update` status TRỰC TIẾP (phải giữ cờ `auto_paused` nên
  KHÔNG dùng set_campaign_status) → **KHÔNG fire hook** → chỉ trông vào reconcile 5' (`*_traffic_5min`,
  WP-Cron chập chờn). Riêng "hết hạn mức ngày" đã có kick tức thì (`*_lentop_daily_pause_kick`, audit
  13/07) nhưng **auto-pause do SỐ DƯ bị bỏ sót** → camp hết tiền dừng ở nguồn mà pool chạy tiếp.

**Fix:** `*_lentop_status_kick($id)` — listener trên action `*_campaign_autosync`, fire ngay sau mỗi
`$wpdb->update` status ở các đường auto. Kick đọc status HIỆN TẠI lúc **shutdown** (sau
`fastcgi_finish_request` → KHÔNG thêm trễ cho request user, vì auto-pause chạy ngay trong lúc verify
lấy mã) → map pause/upsert/complete/delete → `*_lentop_push`. Throttle 30s/camp; reconcile 5' vẫn là
lưới cuối.

**Nguyên tắc:** MỌI đường đổi status camp (kể cả `$wpdb->update` trực tiếp) PHẢI phát 1 tín hiệu để
cầu nối đẩy sang pool — nếu không, chiều nguồn→pool mất đồng bộ và pool over-deliver/không dừng.
