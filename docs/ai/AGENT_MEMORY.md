# AGENT_MEMORY.md — BroxBhai AI Agent Decision Log
# Auto-maintained by AI agents. Do not delete entries; append only.

---

## Purpose
এই ফাইলে AI agents তাদের গুরুত্বপূর্ণ সিদ্ধান্ত, trade-offs, এবং
"কেন এভাবে করা হয়েছে" — তা লগ করে। ভবিষ্যতের agents এখান থেকে
context নেয়।

---

## Decision Log

### Template (নতুন entry এভাবে যোগ করো)
```
### [BROX-XXX] Short title
- Date: YYYY-MM-DD
- Agent: <agent name / human>
- Context: কী সমস্যা ছিল
- Decision: কী করা হয়েছে
- Alternatives Considered: কী বাদ দেওয়া হয়েছে ও কেন
- Trade-offs: কী হারানো হয়েছে / কী পাওয়া গেছে
- Follow-up needed: হ্যাঁ/না — কী করতে হবে
```

---

### [INIT-001] Agent Memory System Created
- Date: 2026-03-17
- Agent: BroxBhai Docs Agent
- Context: Agent instructions-এ কোনো persistent memory ছিল না,
  ফলে প্রতিটি session-এ পূর্বের সিদ্ধান্ত হারিয়ে যাচ্ছিল।
- Decision: `AGENT_MEMORY.md` তৈরি করা হয়েছে append-only log হিসেবে।
- Alternatives Considered: DB-based logging (অতিরিক্ত infra দরকার),
  inline comments (scattered, hard to find)।
- Trade-offs: File-based, তাই merge conflict সম্ভব। Mitigation:
  entries সবসময় append করো, কখনো edit করো না।
- Follow-up needed: না।

---

### [BROX-TOOL-001] AI Tool System v3.0 — Parallel, Streaming, Circuit Breaker
- Date: 2026-03-19
- Agent: BroxBhai Coding Agent
- Context: AI tool system (ToolRegistry v2.0) had sequential-only execution,
  basic error handling, and no support for streaming tool calls or resilience
  patterns. Needed to enhance with parallel execution, streaming support,
  and better error handling per Fireworks AI/OpenAI best practices.
- Decision: Rewrote ToolRegistry to v3.0 with:
  - Parallel execution via `pcntl_fork` (sequential fallback on Windows)
  - Streaming tool call processing (`processStreamingToolCalls()`)
  - Circuit breaker pattern (5 failures → open, 60s reset)
  - Retry logic with exponential backoff per tool
  - 7 error categories for intelligent handling
  - OpenAI-compatible `getToolsForAPI()` method
- Alternatives Considered:
  - Using pthreads for parallelism (rejected: not widely available)
  - Using curl_multi for parallelism (rejected: only works for HTTP tools)
  - No circuit breaker (rejected: would hammer failing tools indefinitely)
- Trade-offs: pcntl_fork adds temp file overhead; sequential fallback on
  Windows is slower but functional. Circuit breaker adds state management.
- Follow-up needed: Monitor circuit breaker state in production; consider
  Redis-backed state for multi-server deployments.

---

### [BROX-TOOL-002] Fireworks AI Autoscaling Retry Logic
- Date: 2026-03-19
- Agent: BroxBhai Coding Agent
- Context: Fireworks AI deployments with scale-to-zero return 503
  DEPLOYMENT_SCALING_UP errors. Without retry logic, users see failures
  during cold starts.
- Decision: Added `handleDeploymentScalingUp()` to AIProvider with
  exponential backoff retry (30 retries, 5s→60s cap, 1.5x multiplier).
  Logs scaling events via `logActivity()`.
- Alternatives Considered:
  - Client-side retry only (rejected: server-side is more reliable)
  - Fixed delay instead of exponential (rejected: less efficient)
  - No retry, just show error (rejected: poor UX for scale-to-zero)
- Trade-offs: Server-side retry blocks request for up to ~30 minutes in
  worst case. Mitigated with clear error message suggesting min-replica-count > 0.
- Follow-up needed: Consider async/background retry for very long scale-ups.

---

## Pattern Registry
*(agents নতুন reusable pattern আবিষ্কার করলে এখানে যোগ করে)*

| Pattern | Location | Notes |
|---------|----------|-------|
| CSRF validation | `app/Middleware/CsrfMiddleware.php` | সব POST/PUT/DELETE-এ |
| Auth check | `AuthManager::requireRole(...)` | Controller-এর শুরুতে |
| Paginated query | `app/Models/BaseModel::paginate(...)` | limit/offset auto |
| Activity log | `logActivity($userId, $action, $meta)` | user action tracking |
| Error log | `logError($context, $exception)` | exception wrapping |

---

## Deprecated Patterns (avoid these)
*(পুরনো pattern যা আর ব্যবহার করা উচিত না)*

| Old Pattern | Replaced By | Reason |
|-------------|-------------|--------|
| Raw `mysqli_query()` | Model + prepared statements | SQL injection risk |
| `$_SESSION` direct access | `AuthManager` methods | inconsistent state |
| Inline CSS in Twig | Tailwind utility classes | unmaintainable |

---

### [BROX-DOCS-001] Token-saver AI instruction baseline
- Date: 2026-03-20
- Agent: BroxBhai Coding Agent
- Context: Editor agents (Cursor/Windsurf/Claude/Copilot) were ingesting large, noisy instruction files (mojibake, emojis, long references) which increased token cost and reduced consistency.
- Decision: Introduced `docs/ai/AI_QUICK_CONTEXT.md` and rewrote `AGENTS.md`, `SKILL.md`, and `editor/*` rules to be short, repo-specific, and pointer-first (default context <= 2 files).
- Alternatives Considered: Shrinking every existing long doc (rejected: higher churn; humans still benefit from deep-dive docs).
- Trade-offs: Some details moved out of default view; mitigated by clear pointers in `docs/ai/AI_CONTEXT_INDEX.md`.
- Follow-up needed: No (refresh when architecture changes).

---

## Historical AGENTS.md Changelog (migrated)
These entries were removed from `AGENTS.md` to keep default editor context small.

| Version | Date | Agent | Change |
|---------|------|-------|--------|
| 2.0.2 | 2026-03-20 | BroxBhai | Fixed presets Quick Create JSON parse error by adding CSRF token + safe `fetchJson()` handling in `app/Views/admin/autocontent/presets.twig`. |
| 2.0.1 | 2026-03-20 | BroxBhai | Fixed admin Copilot not opening (removed duplicate Twig click handler; ensured admin variant loads `partials/ai-assistant/script.twig` on public layout; added Ctrl+Alt+A shortcut in `public_html/ai/js/ai-admin.js`). |
| 3.0 | 2026-03-19 | BroxBhai | Enhanced AI Tool System: added parallel execution (pcntl_fork), streaming tool calls (SSE), circuit breaker pattern, retry logic with exponential backoff, improved error categorization; new API endpoints for tool execution. |
| 2.2 | 2026-03-17 | BroxBhai | Fixed Public Assistant UI style issues; refactored assistant script (`public_html/ai/js/assistant.js`) for readability and performance; added documentation updates for UI/UX and script best-practices. |
| 2.1 | 2026-03-17 | BroxBhai | Centralised AI routes into `app/Routes/AISystemRoutes.php`; introduced `app/Helpers/JsonResponse.php` for uniform JSON responses and added CSRF middleware to all POST AI endpoints. |
| 2.0 | 2026-03-17 | BroxBhai Init | Multi-agent roles, self-improvement loop, security checklist, pitfall tracking added. |
| 1.0 | — | Original | Initial agent instructions. |
