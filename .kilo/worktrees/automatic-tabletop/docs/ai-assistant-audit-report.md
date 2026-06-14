# AI Assistant Audit Report

## Scope

This audit covers the public AI assistant and the admin AI assistant systems in the BroxLab workspace.

Key areas reviewed:
- System prompt files: `system/prompts/public.md`, `system/prompts/admin.md`, `system/prompts/assistant.md`
- AI skills configuration: `system/prompts/ai-skills.json`
- Backend AI chat routes: `app/Controllers/AISystemController.php`
- Prompt loading logic: `app/Helpers/PromptLoader.php`
- Public assistant frontend: `public_html/assets/ai-assistant/modules/public/app.js`
- Admin assistant frontend: `public_html/assets/ai-assistant/modules/admin/app.js`
- AI provider management: `app/Models/AIProvider.php`

---

## High-Level Findings

1. **Public assistant configuration loading is broken.**
   - `public_html/assets/ai-assistant/modules/public/app.js` calls `/api/ai-settings/frontend`, but the backend provides `/api/ai/settings`.
   - The frontend also expects `provider` and `model`, while `/api/ai/settings` returns `frontend_provider` and `frontend_model`.
   - Result: public assistant will likely fall back to default local settings and not use configured provider preferences correctly.

2. **Prompt file naming and configuration mismatch.**
   - There are both `system/prompts/public.md` and `system/prompts/assistant.md` present.
   - `ai-skills.json` refers to `assistant.md` for the general assistant, while runtime prompt loading uses the context name `public`.
   - This creates a stale/duplicate prompt risk and makes prompt drift likely.

3. **Potential prompt injection / context data risk.**
   - `PromptLoader::getSystemPrompt()` appends `contextData` values directly into the system prompt without escaping.
   - If any context content is user-controlled, that may allow prompt injection or unexpected instruction injection.

4. **Admin assistant is generally well-scoped, but missing command guidance.**
   - `system/prompts/admin.md` describes capabilities and slash commands, but it does not dynamically expose available `ToolRegistry` commands.
   - The admin frontend uses `/api/admin/ai/chat` and streaming responses, which is good, but can be improved with explicit tool documentation.

5. **Public UX and privacy gaps.**
   - The public assistant stores user profile and chat history in localStorage without visible consent or privacy notice.
   - `alert()` is used for validation, which is not ideal on modern UI.
   - Technical provider-order messages are shown to public users and can be confusing.

6. **Documentation & integration audit.**
   - There is existing internal docs under `public_html/assets/ai-assistant/docs/INTEGRATION_GUIDE.md`, but no centralized `docs/` report or implementation plan until now.
   - The project would benefit from a dedicated public audit and improvement plan in `docs/`.

7. **Routing and route naming consistency.**
   - The backend route naming is inconsistent between frontend expectations and actual API endpoints.
   - This is a clear bug in the integration contract.

8. **Provider fallback path is robust but should be simplified.**
   - The public assistant attempts configured providers first and then falls back to Puter.js.
   - However, provider selection and ordering logic is complex and can be simplified with a clearer preference chain.

---

## Detailed Findings

### Public Assistant

- `public/app.js` uses `fetch('/api/ai-settings/frontend')`.
- The backend route exists only as `/api/ai/settings`.
- This mismatch is a functional defect and should be fixed immediately.
- The assistant also expects a JSON schema with keys `provider` and `model`; the backend returns different keys.
- The current implementation will likely only operate on default client-side preferences.

### Prompt System

- `PromptLoader::loadPrompts()` looks for `system/prompts/{context}.md`.
- `ai-skills.json` refers to `assistant.md` rather than `public.md` for the public skill.
- The presence of duplicate prompt files means the prompt content may diverge over time.
- Consolidation or a prompt manifest is recommended.

### Backend AI Routes

- Public chat uses `/api/ai/chat` and `/ai/chat`.
- Admin chat uses `/api/admin/ai/chat`.
- There is a separate route `/api/ai/settings` for frontend configuration.
- No `/api/ai-settings/frontend` route exists in the codebase.
- `aiChatHandleRequest()` appends `contextData` to the system prompt, so any untrusted values should be sanitized.

### Admin Assistant

- Admin frontend supports provider/model selection and streaming chat.
- The admin prompt file is present and strong, but it can be enhanced by exposing actual tool names and using richer admin metadata.
- Admin chat route catches exceptions and returns safe responses; good.
- There is also admin image upload and OCR support, which is a useful extension point.

### AI Provider & Settings

- `AIProvider::getSettings()` and `/api/ai/settings` appear to supply frontend defaults.
- Public frontend does not currently consume those settings correctly.
- Provider test and active provider routes are present, which is positive.

---

## Recommended Fixes

### Immediate Fixes

- Fix public frontend config endpoint:
  - Change `public/app.js` from `/api/ai-settings/frontend` to `/api/ai/settings`.
  - Map received keys correctly: `frontend_provider` -> `provider`, `frontend_model` -> `model`.
- Align prompt file usage:
  - Decide whether `public.md` or `assistant.md` is the canonical public prompt and remove the duplicate.
  - Update `system/prompts/ai-skills.json` to use the canonical file.
- Harden prompt injection vectors:
  - Escape or sanitize `contextData` values before appending them to the system prompt.

### Short-Term Improvements

- Add a lightweight privacy notice in the public assistant flow.
- Replace `alert()` validation with inline UI feedback.
- Hide low-level provider fallback messages from public users unless in debug mode.
- Add a dedicated `docs/ai-assistant-architecture.md` or `docs/ai-assistant-usage.md` for maintainers.

### Medium-Term Enhancements

- Add explicit admin tool help generation from `ToolRegistry`.
- Add provider health and fallback telemetry to the admin dashboard.
- Add browser and backend tests for:
  - `/api/ai/settings`
  - Public assistant frontend config mapping
  - prompt loading and fallback behavior
- Add conversation rating/use analytics for both public and admin assistants.

### Long-Term Strategy

- Introduce prompt versioning metadata in `system/prompts/`.
- Add an AI knowledge base sync feature for admin-assisted context enrichment.
- Add a configurable safety layer for sensitive admin-only commands.

---

## Summary

The BroxLab AI assistant system has a strong structure and several good features, including:
- separate public and admin prompts
- provider fallback architecture
- admin tool execution support
- prompt loader with fallback and DB storage

However, the current implementation has a major integration bug in public assistant settings loading and a prompt file naming mismatch that should be fixed immediately.

The system will benefit from prompt consolidation, stronger frontend/back-end contract validation, and clearer public UX/privacy handling.
