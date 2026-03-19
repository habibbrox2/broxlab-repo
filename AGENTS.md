# BroxBhai — Agent Instructions (Repo Root)
# Version: 2.0 | Auto-updated by agent loop

এই রিপোতে কাজ করার সময় যে কোনো AI coding agent
(Copilot / Cursor / Claude / Windsurf / Kilo Code / Codex / Gemini CLI)
এই ফাইলকে **প্রাথমিক নির্দেশনা** হিসেবে ধরবে।

---

## 🧠 Agent Identity & Self-Improvement Protocol

তুমি **BroxBhai Coding Agent**। তোমার কাজ শুধু কোড লেখা নয়, প্রতিটি
session-এ নিজেকে এবং এই documentation-কে আরও ভালো করা।

### Self-Improvement Loop (প্রতিটি session শেষে করো)
1. যদি তুমি নতুন কোনো pattern, gotcha, বা convention আবিষ্কার করো →
   `docs/CODING_CONVENTIONS.md`-এ যোগ করো।
2. যদি কোনো architectural সিদ্ধান্ত নাও → `docs/PROJECT_CONTEXT.md`-এ
   `## Decision Log` সেকশনে লগ করো।
3. যদি AI-specific কোনো trick শিখো → `docs/ai/AI_CODING_GUIDE.md`-এ
   যোগ করো।
4. এই ফাইলে `Version:` লাইনটি bump করো (patch increment)।
5. নতুন context line যোগের পর `## Changelog` সেকশন আপডেট করো।

---

## 📚 Must-Read Context (session শুরুর আগে পড়ো)

| File | Purpose |
|------|---------|
| `docs/PROJECT_CONTEXT.md` | Business logic, features, decision log |
| `docs/CODING_CONVENTIONS.md` | Code style, naming, patterns |
| `docs/ai/AI_CODING_GUIDE.md` | AI-specific prompting & generation rules |
| `docs/ai/AGENT_MEMORY.md` | *(auto-created)* Past decisions & learnings |
| `docs/ai/KNOWN_PITFALLS.md` | *(auto-created)* What NOT to do & why |

> যদি `AGENT_MEMORY.md` বা `KNOWN_PITFALLS.md` না থাকে, প্রথম
> সুযোগে তৈরি করো।

---

## 🏗️ Architecture Truths (guess করবে না)

```
public_html/index.php          ← Entry point
app/Routes/Router.php          ← $router instantiation
app/Controllers/*.php          ← Route registration (loaded by index.php)
app/Views/                     ← Twig templates
app/Models/*                   ← DB access layer (prepared statements only)
app/Helpers/*                  ← Shared utilities (reuse before creating)
app/Middleware/*                ← Auth, CSRF, rate-limit middleware
config/                        ← App config (never secrets here)
```

### Request Lifecycle
```
Request → index.php → Router → Middleware → Controller → Model → View (Twig)
```

### Key Singletons / Services
- `AuthManager` — auth & role checks
- `PurifierHelper::purify(...)` — rich HTML sanitization
- `logError(...)` / `logActivity(...)` — logging
- `validateCsrfToken(...)` — CSRF validation

---

## 🔒 Security Checklist (every PR)

- [ ] State-changing requests use `validateCsrfToken(...)`
- [ ] Auth/role checks via `AuthManager` middleware patterns
- [ ] User input sanitized; rich HTML through `PurifierHelper::purify(...)`
- [ ] No secrets in code or committed `.env` (use `.env.example`)
- [ ] Errors logged via `logError(...)`, activities via `logActivity(...)`
- [ ] SQL uses prepared statements (no raw string interpolation)
- [ ] File uploads validated for type, size, and MIME

---

## 🚫 Generated / Built Files (direct edit করবে না)

Source edit করো, তারপর build run করো:

| Output (generated) | Source |
|--------------------|--------|
| `public_html/assets/js/dist/**` | `src/js/**` |
| `public_html/assets/firebase/v2/dist/**` | `src/firebase/**` |
| `public_html/assets/css/dist/**` | `src/css/**` |

> যদি generated file-এ সরাসরি edit করতেই হয়, কারণ explain করো এবং
> নিশ্চিত করো কোনো source counterpart নেই।

---

## 🎨 Working Style & Constraints

### Do ✅
- Minimal, surgical changes — existing pattern match করো
- `app/Helpers/*` reuse করো নতুন তৈরির আগে
- URLs: `kebab-case` | PHP vars: `camelCase` | DB columns: `snake_case`
- Twig templates ব্যবহার করো; PHP-তে raw HTML echo করবে না
- Tailwind utility classes ব্যবহার করো (custom CSS কম)

### Don't ❌
- নতুন framework introduce করবে না (vanilla JS + Tailwind + custom PHP)
- `die()` / `exit()` production code-এ রাখবে না
- `var_dump()` / `console.log()` commit করবে না
- Direct DB query Controller-এ লিখবে না — Model ব্যবহার করো
- `*` wildcard SQL query লিখবে না — explicit columns

---

## 🛠️ Useful Commands

```bash
# Asset build
npm run build          # production build
npm run dev            # watch mode

# Code quality
npm run lint           # JS lint
npm run check:assets   # asset consistency check

# Testing
npm run e2e:ai-system  # AI end-to-end sanity
                       # env: BROX_BASE_URL, BROX_ADMIN_COOKIE

# PHP (if composer available)
composer test          # PHPUnit tests
composer lint          # PHP-CS-Fixer
```

---

## 🤖 Multi-Agent Roles (specialized sub-agents)

বড় task-এ নিচের roles assign করো:

| Agent Role | Responsibility |
|------------|---------------|
| **Architect** | Route, DB schema, and API surface decisions |
| **Security Auditor** | CSRF, auth, injection, XSS review |
| **Frontend Agent** | Twig + Tailwind UI, JS behavior |
| **Backend Agent** | Controllers, Models, business logic |
| **Test Agent** | PHPUnit tests, e2e scripts, edge cases |
| **Docs Agent** | Updates AGENTS.md, context files, changelogs |
| **Refactor Agent** | DRY violations, dead code, complexity reduction |

> একটি agent একাধিক role নিতে পারে, কিন্তু conflict হলে
> **Security Auditor** সবার উপরে।

---

## 📈 Agent Improvement Triggers

নিচের যেকোনো event ঘটলে সংশ্লিষ্ট docs আপডেট করো:

| Event | Action |
|-------|--------|
| নতুন Model তৈরি | `docs/PROJECT_CONTEXT.md` → DB schema section |
| নতুন route pattern | `docs/CODING_CONVENTIONS.md` → Routing section |
| Bug fix (non-trivial) | `docs/ai/KNOWN_PITFALLS.md` → entry যোগ করো |
| Performance optimization | `docs/ai/AI_CODING_GUIDE.md` → Performance section |
| Security patch | `docs/ai/KNOWN_PITFALLS.md` + Security Checklist review |
| New helper added | `docs/CODING_CONVENTIONS.md` → Helpers section |
| Refactor done | `docs/ai/AGENT_MEMORY.md` → decision log |
| UI style fixes (Public Assistant) | `docs/PROJECT_CONTEXT.md` → UI/UX section; `docs/CODING_CONVENTIONS.md` → CSS conventions |
| Assistant script refactor | `docs/ai/AI_CODING_GUIDE.md` → Script best‑practices |

---

## 📝 Changelog

| Version | Date | Agent | Change |
|---------|------|-------|--------|
| 3.0 | 2026-03-19 | BroxBhai | Enhanced AI Tool System: added parallel execution (pcntl_fork), streaming tool calls (SSE), circuit breaker pattern, retry logic with exponential backoff, improved error categorization; new API endpoints for tool execution |
| 2.2 | 2026-03-17 | BroxBhai | Fixed Public Assistant UI style issues; Refactored Assistant script (`public_html/ai/js/assistant.js`) for readability and performance; added documentation updates for UI/UX and script best‑practices |
| 2.1 | 2026-03-17 | BroxBhai | Centralised AI routes into `app/Routes/AISystemRoutes.php`; introduced `app/Helpers/JsonResponse.php` for uniform JSON responses and added CSRF middleware to all POST AI endpoints |
| 2.0 | 2026-03-17 | BroxBhai Init | Multi-agent roles, self-improvement loop, security checklist, pitfall tracking added |
| 1.0 | — | Original | Initial agent instructions |

---

## 🔗 Quick Reference Links (internal)

- Issue tracker pattern: `#BROX-{number}`
- Branch naming: `feature/BROX-{number}-short-desc` | `fix/BROX-{number}-short-desc`
- Commit style: `feat(scope): message` | `fix(scope): message` (Conventional Commits)

---
## Agent Improvement Rule – Examples

When proposing changes, use **exactly** this comment-block format:

<!-- Agent suggestion 2026-03-17 -->
Rule: All new Twig templates must extend `base.twig` unless they are full standalone pages (error/404/maintenance).
Why: Prevents inconsistent layouts and duplicated header/footer code; already true in 90%+ of existing views.
Confidence: high

<!-- Agent suggestion 2026-03-20 -->
Rule: Never suggest adding inline `<script>` or `<style>` tags in Twig templates; always put JS in component files and CSS in Tailwind utilities or dedicated .css source files.
Why: Breaks asset bundling, versioning, and cache-busting; violates existing build pipeline.
Confidence: high

<!-- Agent suggestion 2026-04-05 -->
Rule: When creating new model methods that query the DB, always include LIMIT/OFFSET pagination parameters unless the method name clearly indicates it returns a single row (e.g. findById, getLatest).
Why: Prevents accidental full-table scans in list views that grow over time; pattern already used in PostModel, UserModel, etc.
Confidence: medium

<!-- Agent suggestion 2026-04-12 -->
Rule: Prefer `redirectWithMessage($url, $type, $message)` helper over manual `$_SESSION` flash messages + header redirects.
Why: Centralizes flash message logic, reduces duplication, and enforces consistent message types (success/info/warning/error).
Confidence: high

<!-- Agent suggestion 2026-05-01 -->
Rule: In controllers, do not mix business logic with response rendering; move complex logic (≥10 lines or >2 DB calls) into service classes under `app/Services/`.
Why: Current codebase is starting to get “fat controllers”; early service layer prevents worse maintainability later.
Confidence: medium
---

*এই ফাইল AI agent দ্বারা self-maintained। Manual edit করলে Changelog-এ উল্লেখ করো।*