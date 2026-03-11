**Title: Assistant Defaults Sync + Model Bar Accordion + HF Manual Config**

**Summary**
- Apply **admin AI System defaults** (provider + model) to **both Public and Admin assistants**.
- Update assistant UI: **model bar as accordion** (expand/collapse inside bar) placed **above response messages**; show **short model label**.
- Add **click‑outside auto close** for admin assistant.
- Prevent **CSS conflicts** with other bundles (tightened scoping).
- Enhance **AI System settings UI** and add **HuggingFace manual endpoint/models input** in provider edit.

**Key Changes**
1. **Default provider/model propagation**
   - Public assistant (`/api/ai-system/frontend` already returns provider/model) → ensure assistant uses `frontend_provider + frontend_model` when available.
   - Admin assistant: add endpoint or reuse existing settings to pull `default_provider` + `default_model` (fallbacks), and preselect models.
   - Ensure server respects admin overrides but initializes from settings on load.

2. **Model Bar Accordion UI**
   - Move `<div class="brox-ai-model-bar">` **above message list** in admin and public assistant templates.
   - Implement accordion behavior in `ai-admin.js` and `assistant.js`:
     - Collapsed by default → shows short label only.
     - Expand inside bar to show provider/model selects.
     - Click outside collapses.

3. **Short model name display**
   - Use `supported_models_select` labels where available.
   - Fallback: last segment of model ID (split by `/` and `:`).
   - Show in model badge and collapsed bar label.

4. **Click‑outside auto close (admin assistant)**
   - Add global click handler to collapse admin sidebar if click is outside shell and trigger button.

5. **CSS isolation & override fix**
   - Tighten selectors to `#adminAiShell .brox-ai-*` and `#publicAssistantChat .brox-ai-*`.
   - Avoid generic class collisions; ensure no global resets.

6. **AI System UI improvements (suggested)**
   - Add quick status pills: API key present, active provider, last tested.
   - Add inline warning if provider has no models.
   - Add “Reset to defaults” for model selections.

7. **HuggingFace manual config**
   - In provider edit modal for `huggingface`:
     - Endpoint input field.
     - Models textarea (JSON map id→label).
   - Save into `supported_models` + `api_endpoint`.
   - UI shows validation error for invalid JSON.

**APIs / Interfaces**
- Admin assistant uses settings response with `frontend_model`/`backend_model` and `default_model` fallback.
- Provider edit supports manual `supported_models` JSON for HF.

**Test Plan**
1. Change frontend/backend defaults → public + admin assistants show correct provider/model on load.
2. Model bar collapses/expands in place; shows short label; placed above messages.
3. Click outside admin assistant closes sidebar.
4. HF provider: custom endpoint/models save and appear in model dropdowns.
5. CSS unaffected by other bundles (no layout regressions).

**Assumptions**
- `supported_models_select` is populated for active providers.
- Admin assistant can read settings via existing endpoints without new auth changes.
