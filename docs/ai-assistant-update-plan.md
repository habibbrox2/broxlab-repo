# AI Assistant Update Plan

## Goal

Resolve current public/admin AI assistant integration issues, improve prompt consistency, and strengthen UX/security for BroxLab AI assistants.

---

## Phase 1: Critical Fixes (Immediate)

1. Fix public assistant frontend settings API
   - Update `public_html/assets/ai-assistant/modules/public/app.js`
   - Replace `/api/ai-settings/frontend` with `/api/ai/settings`
   - Parse response keys correctly:
     - `frontend_provider` -> `provider`
     - `frontend_model` -> `model`
     - `backend_provider`, `backend_model` if needed for future features
   - Verify that the public assistant loads configured provider settings instead of falling back to defaults.

2. Consolidate public prompt files
   - Choose one canonical public prompt file: either `system/prompts/public.md` or `system/prompts/assistant.md`.
   - Remove or archive the unused duplicate.
   - Update `system/prompts/ai-skills.json` to reference the chosen file.
   - Confirm prompt loading with `PromptLoader::loadPrompts('public')` and `PromptLoader::getSystemPrompt()`.

3. Harden prompt injection and prompt context handling
   - Update `app/Helpers/PromptLoader.php` or the prompt assembly path to sanitize `contextData` values.
   - Ensure values appended to system prompt are escaped or stripped of control characters.
   - Add a small regression test if possible.

---

## Phase 2: User Experience & Security

1. Improve public assistant onboarding and privacy
   - Add a privacy/data notice near the public chat entry.
   - Document retention behavior for localStorage chat history.
   - Avoid storing sensitive data without consent.

2. Improve public UI and accessibility
   - Replace `alert()` validation with inline validation messages.
   - Provide friendly error messages for provider failures.
   - Optionally hide technical fallback messages from normal users.

3. Align public and admin assistant feature parity
   - Review whether both assistants should support the same knowledge sources.
   - Consider a shared `PromptLoader` configuration for common safety rules.

---

## Phase 3: Admin Enhancements

1. Add explicit admin tool help
   - Extend `system/prompts/admin.md` with a dynamic tool list or embedded examples.
   - Use `ToolRegistry::listTools()` output where possible.
   - Add `/help` or `/list_tools` guidance in the admin prompt.

2. Add provider health and metrics
   - Track provider fallback usage and latency.
   - Expose a dashboard metric for `openrouter`, `fireworks`, and Puter fallback.
   - Use admin route `/api/admin/system-health` as the starting point.

3. Add better admin prompt versioning
   - Add a prompt metadata section for `admin.md` and `public.md`.
   - Consider a `prompt_version` field in `system/prompts/ai-skills.json` or a separate manifest.

---

## Phase 4: Testing and Validation

1. Add backend API contract tests
   - Verify `/api/ai/settings` returns expected keys.
   - Ensure public frontend mapping is correct.
   - Test `/api/admin/ai/chat` for auth and error paths.

2. Add frontend integration tests
   - Validate public assistant config load and fallback logic.
   - Validate admin assistant streaming chat behavior.

3. Add regression coverage for prompt selection
   - Test `PromptLoader::getSystemPrompt('public')`
   - Test `PromptLoader::getSystemPrompt('admin')`
   - Ensure no stale prompt file is accidentally used.

---

## Suggested Timeline

- Week 1:
  - Critical API fix
  - Prompt file consolidation
  - Public frontend contract validation

- Week 2:
  - User experience and privacy improvements
  - Admin prompt/tool enhancements
  - Basic backend tests

- Week 3:
  - Metrics and admin health improvements
  - Prompt versioning and documentation
  - Full regression audit

---

## Validation Checklist

- [ ] Public AI assistant loads provider settings correctly
- [ ] `public_html/assets/ai-assistant/modules/public/app.js` no longer requests missing endpoint
- [ ] Prompt file usage is consolidated and consistent
- [ ] `ai-skills.json` references the canonical prompt file
- [ ] Context data is sanitized before prompt injection
- [ ] Admin AI assistant tool guidance is clearer
- [ ] Public assistant privacy note is visible
- [ ] API contract tests pass
- [ ] Documentation updated in `docs/`
