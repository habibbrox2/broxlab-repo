# AI_CODING_GUIDE.md — BroxBhai
# AI agents-এর জন্য generation rules, prompting tips, and quality gates.
# Version: 2.0 | Self-maintained

---

## Core Principles for AI Code Generation

### 1. Read Before Write
কোড লেখার আগে সবসময়:
- [ ] সংশ্লিষ্ট existing Model/Helper পড়ো
- [ ] Routing convention confirm করো
- [ ] `KNOWN_PITFALLS.md` স্ক্যান করো
- [ ] Similar existing feature-এর pattern follow করো

### 2. Smallest Viable Change
- সমস্যা সমাধানের জন্য minimum code লেখো
- Refactor আলাদা PR-এ রাখো
- "While I'm here" changes এড়াও

### 3. Explain Non-Obvious Decisions
- Complex logic-এ `// Why:` comment যোগ করো
- Security-related code-এ `// Security:` prefix দাও
- Workaround হলে `// TODO: BROX-XXX` reference দাও

---

## Prompting Patterns (agent-to-agent বা human-to-agent)

### Feature Implementation
```
Implement [FEATURE] for BroxBhai.

Context:
- Related models: [list]
- Related controllers: [list]
- User role required: [admin/user/public]
- CSRF needed: yes/no

Constraints:
- Follow patterns in [similar file]
- Use existing helpers: [list]
- Do NOT create new helpers unless necessary

Output:
1. Migration (if DB change)
2. Model method(s)
3. Controller method(s)
4. Twig template partial
5. JS (if needed, vanilla only)
6. Update AGENT_MEMORY.md with decision
```

### Bug Fix
```
Fix bug: [description]

Steps to reproduce: [steps]
Expected: [behavior]
Actual: [behavior]

Before fixing:
1. Check KNOWN_PITFALLS.md for related entry
2. Identify root cause (don't just fix symptom)
3. Write fix
4. Add entry to KNOWN_PITFALLS.md if new pattern
```

### Code Review
```
Review this code for BroxBhai:
[paste code]

Check for:
- Security (CSRF, XSS, SQL injection, auth bypass)
- Follows CODING_CONVENTIONS.md patterns
- N+1 queries
- Missing error handling
- Missing logError/logActivity calls
- Hardcoded values that should be config

Output: numbered list of issues with severity 🔴🟠🟡🟢
```

### Refactor
```
Refactor [file/function] in BroxBhai.

Goal: [DRY / reduce complexity / improve readability]
Constraints:
- Do NOT change behavior
- Do NOT change public API signatures
- Tests must still pass

After refactoring:
- Update AGENT_MEMORY.md with what changed and why
```

---

## Code Quality Gates

Agent-generated code must pass before considering done:

### PHP
- [ ] No raw SQL strings — prepared statements only
- [ ] No `die()` / `exit()` in production paths
- [ ] Input validated and sanitized
- [ ] Output escaped in Twig (`{{ var | e }}`)
- [ ] Exceptions caught and `logError()` called
- [ ] Auth checked at start of controller method
- [ ] CSRF validated for state-changing actions

### JavaScript
- [ ] No `console.log` left in
- [ ] Error handling on all `fetch()` calls
- [ ] No `innerHTML` with user data (use `textContent` or sanitize)
- [ ] Event listeners cleaned up (no memory leaks)
- [ ] No new npm packages without discussion

### Twig Templates
- [ ] No PHP logic — only display
- [ ] Variables escaped (`| e`) unless explicitly purified
- [ ] Extends base layout or correct partial
- [ ] Mobile-responsive (Tailwind classes)

### Database
- [ ] Migrations are reversible (has `down()`)
- [ ] Indexes on foreign keys and filtered columns
- [ ] Column names in `snake_case`
- [ ] No `SELECT *` — explicit columns only

---

## Performance Guidelines

| Scenario | Approach |
|----------|----------|
| List page | Paginate with `LIMIT`/`OFFSET`, never fetch all |
| Related data | JOIN or batch fetch, never loop queries |
| Heavy computation | Cache result, use `storage/cache/` |
| File upload | Validate type+size before processing |
| Search | Use DB indexes; avoid `LIKE '%term'` (prefix ok) |

---

## Agent Self-Improvement Protocol

প্রতিটি significant task শেষে নিম্নলিখিত চেক করো:

```
SELF-IMPROVEMENT CHECKLIST:
□ নতুন কোনো pattern তৈরি হয়েছে?
  → docs/CODING_CONVENTIONS.md আপডেট করো

□ কোনো ভুল বা gotcha পাওয়া গেছে?
  → docs/ai/KNOWN_PITFALLS.md-এ entry যোগ করো

□ কোনো architectural সিদ্ধান্ত নেওয়া হয়েছে?
  → docs/ai/AGENT_MEMORY.md-এ log করো

□ কোনো নতুন helper/model তৈরি হয়েছে?
  → docs/PROJECT_CONTEXT.md + AGENT_MEMORY.md আপডেট করো

□ এই guide-এর কোনো rule update দরকার?
  → এই ফাইলে যোগ করো, Version bump করো
```

---

## Changelog

| Version | Change |
|---------|--------|
| 2.0 | Prompting patterns, quality gates, self-improvement checklist, performance guidelines added |
| 1.0 | Initial guide |
