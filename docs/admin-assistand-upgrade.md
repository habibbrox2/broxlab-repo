# Admin Assistant Hardening + Quality Upgrade Plan

**Summary**
Admin Assistant‑কে নির্ভরযোগ্য, দ্রুত এবং স্থিতিশীল করতে UI/JS/API ফ্লোতে audit‑driven fixes, reliability guards, এবং test/monitoring যোগ করা হবে। লক্ষ্য: সব core feature (model load, chat stream, file upload, context, shortcuts, log monitor) স্থায়ীভাবে ঠিকভাবে কাজ করবে।

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

