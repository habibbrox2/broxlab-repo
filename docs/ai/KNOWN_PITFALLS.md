# KNOWN_PITFALLS.md — BroxBhai
# Append-only. AI agents এখানে ভুল ও gotchas লগ করে।

---

## কীভাবে ব্যবহার করবে
- নতুন কাজ শুরুর আগে এই ফাইলটি স্ক্যান করো।
- Bug fix বা security patch-এর পরে নতুন entry যোগ করো।
- Entry কখনো মুছবে না — "resolved" column আপডেট করো।

---

## Pitfall Entry Format
```
### [PIT-XXX] Short title
- Severity: 🔴 Critical | 🟠 High | 🟡 Medium | 🟢 Low
- Category: Security | Logic | Performance | UX | Build
- Symptom: কী দেখা যায়
- Root Cause: কেন হয়
- Fix: কী করতে হবে
- Avoid by: future-এ কীভাবে এড়ানো যায়
- Resolved: হ্যাঁ/না
```

---

## Active Pitfalls

### [PIT-001] CSRF Token Missing on AJAX POST
- Severity: 🔴 Critical
- Category: Security
- Symptom: AJAX call 403 দেয় বা silently fail করে।
- Root Cause: Twig template থেকে `{{ csrf_token() }}` বা
  JS header-এ `X-CSRF-Token` না পাঠানো।
- Fix: সব AJAX POST-এ header যোগ করো:
  ```js
  headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content }
  ```
  এবং Twig layout-এ নিশ্চিত করো:
  ```html
  <meta name="csrf-token" content="{{ csrf_token() }}">
  ```
- Avoid by: `fetchPost()` wrapper helper ব্যবহার করো যা auto-inject করে।
- Resolved: না (ongoing vigilance দরকার)

---

### [PIT-002] Twig Cache Stale After Template Edit
- Severity: 🟡 Medium
- Category: Build
- Symptom: Template পরিবর্তন দেখা যাচ্ছে না locally।
- Root Cause: Twig cache `storage/twig_cache/` পুরনো compiled
  template serve করছে।
- Fix: `storage/twig_cache/` ফোল্ডার clear করো:
  ```bash
  rm -rf storage/twig_cache/*
  ```
- Avoid by: dev environment-এ Twig `auto_reload => true` রাখো।
- Resolved: না (dev config check করতে হবে)

---

### [PIT-003] N+1 Query in Loop
- Severity: 🟠 High
- Category: Performance
- Symptom: Page slow, DB query count অনেক বেশি (debug bar দেখো)।
- Root Cause: Loop-এর ভেতরে Model call করা হচ্ছে।
- Fix: Eager loading বা single JOIN query দিয়ে batch fetch করো।
  ```php
  // ❌ Bad
  foreach ($posts as $post) {
      $post->author = UserModel::find($post->user_id);
  }
  // ✅ Good
  $posts = PostModel::withAuthors($postIds);
  ```
- Avoid by: Loop-এ কোনো DB call দেখলেই refactor করো।
- Resolved: না (pattern-level vigilance)

---

### [PIT-004] Raw HTML Output in Controller
- Severity: 🟠 High
- Category: Security / Architecture
- Symptom: XSS vulnerability বা tangled presentation logic।
- Root Cause: Controller-এ `echo "<div>$userInput</div>"` লেখা।
- Fix: সব output Twig template-এ নিয়ে যাও।
  `{{ variable | e }}` বা `{{ variable | raw }}` (purified হলে)।
- Avoid by: Controller-এ কোনো `echo` / `print` রাখবে না।
- Resolved: না (ongoing)

---

### [PIT-005] .env Accidentally Committed
- Severity: 🔴 Critical
- Category: Security
- Symptom: Git history-তে secrets দেখা যাচ্ছে।
- Root Cause: `.gitignore`-এ `.env` নেই, বা force-add হয়েছে।
- Fix:
  1. Immediately rotate all exposed credentials.
  2. `git rm --cached .env` run করো।
  3. `.gitignore`-এ `.env` আছে কিনা verify করো।
  4. Git history rewrite (BFG Repo Cleaner) বিবেচনা করো।
- Avoid by: pre-commit hook দিয়ে `.env` check করো।
- Resolved: না (prevention hook pending)

---

### [PIT-006] Missing Index on Filtered Columns
- Severity: 🟠 High
- Category: Performance
- Symptom: Table বড় হলে query suddenly slow হয়।
- Root Cause: `WHERE`, `ORDER BY`, বা `JOIN` column-এ DB index নেই।
- Fix: Migration দিয়ে index যোগ করো:
  ```sql
  ALTER TABLE posts ADD INDEX idx_user_id (user_id);
  ALTER TABLE posts ADD INDEX idx_created_at (created_at);
  ```
- Avoid by: নতুন Model তৈরিতে filtered columns-এ index plan করো।
- Resolved: না (per-table review needed)

---

### [PIT-007] Tool Circuit Breaker Opens Unexpectedly
- Severity: 🟡 Medium
- Category: Logic
- Symptom: Tool returns "circuit_open" error despite tool working fine.
- Root Cause: Transient network errors counted as failures; 5 consecutive
  errors open circuit. Temporary DNS or connection issues trigger this.
- Fix: `ToolRegistry::resetCircuitBreaker('tool_name')` to manually reset.
  Check logs for actual failure cause before resetting.
- Avoid by: Ensure tool handlers have proper retry logic internally for
  transient errors. Don't count network blips as tool failures.
- Resolved: না (monitor circuit breaker status via `/api/admin/ai-tools`)

---

### [PIT-008] Parallel Tool Execution Fails on Windows
- Severity: 🟡 Medium
- Category: Compatibility
- Symptom: `executeParallel()` falls back to sequential; no error but slower.
- Root Cause: `pcntl_fork()` not available on Windows; system falls back
  to sequential execution automatically.
- Fix: No fix needed — fallback is automatic. For production, use Linux
  server to get true parallel execution.
- Avoid by: Document that parallel execution requires Linux. Test on
  Linux before deploying.
- Resolved: না (expected behavior on Windows)

---

### [PIT-009] Fireworks 503 During Scale-Up Blocks Request
- Severity: 🟠 High
- Category: Performance / UX
- Symptom: Request hangs for minutes during Fireworks deployment scale-up.
- Root Cause: `handleDeploymentScalingUp()` retries up to 30 times with
  exponential backoff. Large deployments may take 5-10 minutes to scale.
- Fix: Set `--min-replica-count 1` on Fireworks deployments for instant
  responses. Or accept the retry behavior for cost-optimized deployments.
- Avoid by: For production/latency-sensitive use, always set min-replica-count > 0.
  Use scale-to-zero only for dev/testing.
- Resolved: না (document in deployment guide)

---

### [PIT-013] Duplicate Route Registration Overrides Earlier Routes
- Severity: 🟠 High
- Category: Logic / UX
- Symptom: Same endpoint (e.g. `POST /api/ai/feedback`) different behavior depending on load order; frontend suddenly breaks (JSON parse errors / wrong response shape).
- Root Cause: `public_html/index.php` loads `app/Routes/*.php` early, then later `require_once` loads every `app/Controllers/*.php`. If both register the same method+path, the later registration wins (route collision).
- Fix: Centralize shared `/api/*` routes in `app/Routes/AISystemRoutes.php` and remove/guard overlapping controller registrations (use a constant guard like `BROX_AI_API_ROUTES_HANDLED`).
- Avoid by: Before adding a new `/api/*` route, search for existing registrations across `app/Routes/` + `app/Controllers/`.
- Resolved: হ্যাঁ (2026-03-20)

---

### [PIT-014] Chat Feedback Breaks Without Server Message IDs
- Severity: 🟠 High
- Category: UX / Logic
- Symptom: Thumbs up/down appears to work but backend rejects (`Invalid feedback data`) or stores unusable rows; using client-side indexes (`0,1,2...`) doesn’t match DB `ai_messages.id`.
- Root Cause: Feedback endpoint expects real `conversation_id` + DB `message_id`, but UI used local indexes and/or didn’t receive IDs from chat stream.
- Fix: Send SSE meta first: `{"meta":{"conversation_id":...,"message_id":...}}` and set dataset IDs in the UI. Then post to `POST /api/ai/feedback` with those values.
- Avoid by: Never assume “message index” equals DB id; always use server-provided IDs.
- Resolved: হ্যাঁ (2026-03-20)

---

## Resolved Pitfalls Archive
*(resolved হলে এখানে সরিয়ে আনো)*

### [PIT-010] Admin Copilot Button Click Toggles Twice (Doesn’t Open)
- Severity: ðŸŸ  High
- Category: UX / Logic
- Symptom: Admin panel-এ Copilot FAB click করলে কিছুই হয় না (open হয় না / সাথে সাথে close হয়ে যায়)।
- Root Cause: একই button-এ ২টা handler ছিল — Twig inline script `toggleSidebar()` কল করত, আবার `ai-admin.js`-ও `btn.onclick` দিয়ে `toggleSidebar()` call করত → single click এ double-toggle।
- Fix: Twig inline loader/shortcut script সরিয়ে দিয়ে keyboard shortcut + toggle logic `public_html/ai/js/ai-admin.js`-এ রাখো।
- Avoid by: UI toggle/shortcut logic এক জায়গায় রাখো (JS file), Twig inline click-handler দিয়ে already-bound button-এ toggle করো না।
- Resolved: à¦¹à§à¦¯à¦¾à¦ (2026-03-20)

---

### [PIT-011] Public Layout Shows Admin Copilot UI but Doesn’t Load Admin Script
- Severity: ðŸŸ¡ Medium
- Category: UX / Logic
- Symptom: Public route-এ (e.g. `/`) admin user login থাকলেও Copilot button/UI দেখা যায়, কিন্তু click করলে open হয় না।
- Root Cause: `app/Views/layout.twig`-এ admin branch-এ `partials/ai-assistant/admin.twig` include ছিল, কিন্তু `assistant_variant='admin'` set করে `partials/ai-assistant/script.twig` include করা ছিল না → `ai-admin.js` load হয়নি।
- Fix: `app/Views/layout.twig`-এ admin branch-এ `assistant_variant = 'admin'` set করে `partials/ai-assistant/script.twig` include করো।
- Avoid by: Assistant UI include করলে corresponding script loader (`partials/ai-assistant/script.twig`) একই branch-এ রাখো (public/admin দুটোতেই)।
- Resolved: à¦¹à§à¦¯à¦¾à¦ (2026-03-20)

---

### [PIT-012] AJAX JSON Parse Error (Unexpected token '<') on CSRF-Protected Endpoint
- Severity: ðŸŸ  High
- Category: UX / Logic / Security
- Symptom: Console error: `Unexpected token '<', "<!DOCTYPE "... is not valid JSON` (AJAX expects JSON কিন্তু server HTML ফিরিয়ে দেয়)।
- Root Cause: CSRF middleware থাকা endpoint-এ JS fetch থেকে `csrf_token` পাঠানো হয়নি → middleware error/redirect HTML page return করে, তারপর `response.json()` parse fail করে।
- Fix: POST `FormData`/urlencoded body-এ `csrf_token` append করো (meta `csrf-token` থেকে); এবং JSON parse করার আগে `content-type` check করো।
- Avoid by: Shared `fetchJson()` helper ব্যবহার করো যা `Accept: application/json` সেট করে + non-JSON response হলে friendly error দেয়।
- Resolved: à¦¹à§à¦¯à¦¾à¦ (2026-03-20)

---

### [PIT-013] Public Assistant Thinking UI Not Shown Inside Assistant Bubble (and Duplicate Stream Messages)
- Severity: ðŸŸ¡ Medium
- Category: UX / Logic
- Symptom: Thinking state-এ `.brox-ai-msg.brox-ai-assistant` empty থাকে / thinking আলাদা element-এ দেখায়; stream শেষে একই reply দুইবার দেখা যায়।
- Root Cause: `getAIResponse()` thinking indicator হিসেবে আলাদা `.brox-ai-typing` div ব্যবহার করত, পরে `createEmptyMessage()` দিয়ে stream bubble বানাত, আবার শেষে `addMessage()` দিয়ে একই reply duplicate করত।
- Fix: `getAIResponse()` শুরুতেই একবার assistant bubble তৈরি করে সেই bubble-এর ভিতর thinking skeleton render করো; প্রথম content আসলেই clear করে একই bubble-এ stream render করো; শেষে আর `addMessage()` দিয়ে duplicate message বানিও না।
- Avoid by: Streaming UI-তে “one message per assistant response” invariant বজায় রাখো; placeholder/typing indicator সবসময় target bubble-এর ভেতরে রাখো।
- Resolved: à¦¹à§à¦¯à¦¾à¦ (2026-03-20)

---

### [PIT-014] Mojibake (à¦…à¦¿) Bengali Text Shows in UI
- Severity: ðŸŸ¡ Medium
- Category: UX / Content
- Symptom: UI text-এ `à¦¹à§à¦¯à¦¾à¦` টাইপের ভাঙা বাংলা দেখা যায়।
- Root Cause: Source file-এ বাংলা string ভুল encoding/incorrect copy-paste হয়ে “mojibake” আকারে committed ছিল (UTF-8 bytes ভুলভাবে decode হয়ে literal `à¦...` characters হয়ে যায়)।
- Fix: Affected strings-গুলোকে সঠিক বাংলা UTF-8 text দিয়ে replace করো (JS/Twig), এবং editor-এ UTF-8 encoding নিশ্চিত করো।
- Avoid by: UI-facing বাংলা string যোগ করার পর browser + IDE-এ verify করো; repo files UTF-8 (without lossy conversions) হিসেবে রাখো।
- Resolved: à¦¹à§à¦¯à¦¾à¦ (2026-03-20)

---

| ID | Title | Resolved Date | Fix Summary |
|----|-------|---------------|-------------|
| — | — | — | — |
