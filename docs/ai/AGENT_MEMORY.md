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
