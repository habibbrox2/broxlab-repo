# Admin Assistant Hardening + Quality Upgrade Plan

**Summary**
Admin Assistant‑কে নির্ভরযোগ্য, দ্রুত এবং স্থিতিশীল করতে UI/JS/API ফ্লোতে audit‑driven fixes, reliability guards, এবং test/monitoring যোগ করা হবে। লক্ষ্য: সব core feature (model load, chat stream, file upload, context, shortcuts, log monitor) স্থায়ীভাবে ঠিকভাবে কাজ করবে।

---

## ✅ Recently Fixed Issues (2026-03-12)

### 1. CSS Positioning Fix
- **Issue**: Admin Assistant was appearing on the LEFT side instead of RIGHT side
- **Root Cause**: CSS `inset: 0 !important` was overriding positioning
- **Fix**: Changed to explicit `right: 0 !important; left: auto !important` in `public_html/ai/css/ai-style.css`

### 2. Model Status Indicator
- **Issue**: No visual indication of AI model connectivity status
- **Fix**: Added online/offline/connecting status indicator in `public_html/ai/js/ai-admin.js`
  - Green: Online (model connected)
  - Red: Offline (connection failed)
  - Yellow: Connecting (in progress)

### 3. Delete Button Removal
- **Issue**: Delete button was present next to send button in input area (unnecessary)
- **Fix**: Removed the trash icon button from input area in `app/Views/partials/ai-assistant/admin.twig`

### 4. PHP Duplicate Require Fix
- **Issue**: Duplicate `require_once` statement in `AISystemChatController.php`
- **Fix**: Removed duplicate line 16 that was requiring PromptLoader.php twice

---

**Implementation Changes**
- **Core Reliability (JS Runtime)**
  - `ai-admin.js`‑এ boot lifecycle audit: lazy‑load, init, DOM readiness, singleton guard ভেরিফাই।
  - event binding idempotent করা (duplicate listeners প্রতিরোধ).
  - SSE stream error handling ও reconnect fallback (network/JSON parse error) add.
  - meta timing consistency + message render finalize for streaming (ensure no empty bubbles).
- **Model & Provider UX**
  - Provider/model loading caching awareness UI (show cache source when available).
  - Refresh button status + cooldown to avoid request spam.
  - Provider/model mismatch guard: invalid model হলে nearest default fallback with clear message.
- **File Upload Robustness**
  - Upload lifecycle guard: duplicate upload, upload cancel, stale progress reset.
  - Attachment preview & remove: always clears pending payload before send.
  - Non‑image attachments fallback to text note (no visual preview) + server response validation.
- **Context & Slash Commands**
  - Context extraction validation (ensure DOM context IDs are found; fallback to “Global”).
  - Slash command UI: keyboard navigation + enter select (accessibility).
  - Command registry centralized for easier extension.
- **Performance & UX**
  - Chat body virtualization limit (e.g., keep last N messages in DOM).
  - Typing indicator state safety (always cleared on exit/error).
  - Mobile responsive edge cases: sidebar open/close + focus management.
- **API & Backend Consistency**
  - `/api/admin/ai/chat` & `/api/admin/ai/upload` response schema validation client‑side.
  - Error payload normalization (standard `success/error/error_code` handling).
  - Rate limit & auth error messaging surfaced clearly to admin UI.
- **Logging & Monitoring**
  - JS telemetry hooks (optional) for failures: model fetch fail, upload fail, SSE fail.
  - Log monitor: badge debounce + retry backoff.

**Public Interfaces / API**
- No breaking API changes.
- Optional: add `cache_source` or `cache_ttl` display in admin UI if present.

**Test Plan**
1. **Model Load**: open assistant → provider list + model list loads; refresh works; offline fallback shows.
2. **Chat Stream**: send message → streaming response renders; duration meta shows; no empty bubble.
3. **Error Handling**: force 500 or network fail → user sees safe error message; typing indicator clears.
4. **File Upload**: image attach → preview + upload → send uses image; remove clears; non‑image fallback.
5. **Shortcuts**: Ctrl+Alt+A opens; Esc closes; slash menu works with keyboard.
6. **Responsive**: mobile width → full overlay works, focus in input works, sidebar toggle works.
7. **Log Monitor**: error badge appears and updates; doesn’t spam on repeats.

**Assumptions**
- Current admin assistant features (upload, model refresh, SSE) remain enabled.
- No API schema change is required—only stricter handling on client.

