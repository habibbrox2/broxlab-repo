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

## Resolved Pitfalls Archive
*(resolved হলে এখানে সরিয়ে আনো)*

| ID | Title | Resolved Date | Fix Summary |
|----|-------|---------------|-------------|
| — | — | — | — |
