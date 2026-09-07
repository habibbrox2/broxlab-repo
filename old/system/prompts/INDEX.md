# System Prompts Index
**Version:** 1.0 | **Last Updated:** March 22, 2026

This file documents all prompt files in `system/prompts/` and their purpose, version, and usage context.

---

## Markdown Prompts

| File | Purpose | Version | Loaded By | Last Updated | Status |
|------|---------|---------|-----------|--------------|--------|
| `public.md` | Public-facing chat assistant system prompt | 1.0 | `PromptLoader::loadPrompts('public')` | 2026-03-20 | Active |
| `admin.md` | Admin/internal assistant system prompt | 1.0 | `PromptLoader::loadPrompts('admin')` | 2026-03-20 | Active |
| `enhancer.md` | Content enhancement/rewriting prompt | 1.0 | `PHPBridge.js::loadPrompt()` | 2026-03-15 | Active |
| `summarizer.md` | Content summarization prompt | 1.0 | `PHPBridge.js::loadPrompt()` | 2026-03-15 | Active |
| `translator.md` | Content translation prompt | 1.0 | `PHPBridge.js::loadPrompt()` | 2026-03-15 | Active |
| `code-helper.md` | Code generation and debugging prompt | 1.0 | `PHPBridge.js::loadPrompt()` | 2026-03-15 | Active |
| `scraper.md` | Web scraper selector and data extraction prompt | 1.0 | `PHPBridge.js::loadPrompt()` | 2026-03-15 | Active |

---

## JSON Configuration Files

| File | Purpose | Version | Loaded By | Last Updated | Status |
|------|---------|---------|-----------|--------------|--------|
| `ai-skills.json` | AI skills and capabilities configuration | 2.0 | `PromptLoader::loadAISkills()` | 2026-03-13 | Active |
| `ai-tools.json` | AI tool definitions (if exists) | — | `PromptLoader::loadAITools()` | — | N/A |
| `prompts.yaml` | Legacy YAML prompt config (if used) | — | `PromptLoader::yaml_parse()` | — | Optional |

---

## Loading Mechanism

### PHP Backend
```php
// Load prompts for a context (admin/public)
$prompts = PromptLoader::loadPrompts('admin', $mysqli);

// Load AI skills
$skills = PromptLoader::loadAISkills();

// Load AI tools
$tools = PromptLoader::loadAITools();
```

### JavaScript Frontend  
```javascript
// Load a prompt from the system/prompts/ folder
const prompt = this.loadPrompt('admin.md');
const prompt = this.loadPrompt('enhancer.md');
```

See [`app/Helpers/PromptLoader.php`](../../app/Helpers/PromptLoader.php) and [`src/ai/services/PHPBridge.js`](../../src/ai/services/PHPBridge.js) for implementation.

---

## Prompt Template Variables

### In `assistant.md` and `admin.md`
- `{{site_name}}` → Replaced with `APP_NAME` from `.env` or database
- `{{site_url}}` → Replaced with `APP_URL` from `.env` or database

Ensure these placeholders are consistent if adding new master prompts.

---

## Maintenance

### Adding a New Prompt
1. Create a `.md` file in `system/prompts/` (e.g., `translator.md`)
2. Add an entry to this INDEX.md with version, purpose, and loader reference
3. Update `PromptLoader.php` if it requires a new `load*()` method
4. Document in which controller/service loads the prompt
5. Test the prompt loading path (verify file exists and parses correctly)

### Deprecating a Prompt
1. Update `Status` column to "Deprecated" in this INDEX
2. Add end-of-life date and migration notes
3. Move file to `system/prompts/deprecated/` (create folder if needed)
4. Update any code that references the old prompt

### Version Control
- Bump version in this INDEX when prompt content significantly changes
- Keep a brief changelog comment in the prompt file itself (at the top in `<!-- comment -->`)

---

## Quick Troubleshooting

**Prompt not loading?**
- Check file path in `PromptLoader.php` — does the file exist?
- Verify PHP integration: is `PromptLoader::loadPrompts()` called?
- Check JavaScript: is `PHPBridge.loadPrompt()` passing the correct filename?

**Template variables not replaced?**
- Ensure `PromptLoader.php` is calling `strtr()` or equivalent placeholder replacement
- Check database settings for `admin_system_prompt` or `public_system_prompt` overrides

**Performance issue with prompts?**
- Prompts are loaded into memory; large prompts (>50KB) may cause slowdown
- Consider lazy-loading or caching via `UnifiedCache`

---

## References

- **PromptLoader implementation:** [`app/Helpers/PromptLoader.php`](../app/Helpers/PromptLoader.php)
- **Helper layer:** `app/Helpers/` (25 utilities including PromptLoader, FirebaseHelper, etc.)
- **Controller layer:** `app/Controllers/` (50 controllers with routes)
- **AI configuration:** `ai-skills.json`, `ai-tools.json`
