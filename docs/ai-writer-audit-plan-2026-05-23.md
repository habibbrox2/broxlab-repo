# AI Writer Full Audit + Fix Plan (BroxLab)

Date: 2026-05-23

This document records the end-to-end audit map and the implementation plan for stabilizing the AI Writer features across admin pages and embedded forms.

## Feature Map

### Admin pages

- UI: `/admin/ai/article-writer`
  - Twig: `app/Views/admin/ai/article-writer.twig`
  - JS: `public_html/assets/js/admin-article-writer.js`
  - API:
    - `POST /api/admin/ai/article-writer/generate` (JSON)
    - `POST /api/admin/ai/article-writer/publish` (JSON)
  - Backend:
    - Routes: `app/Controllers/AISystemController.php`
    - Service: `app/Services/AI/ArticleWriterService.php`

- UI: `/admin/ai/article-writer-stream`
  - Twig: `app/Views/admin/ai/article-writer-stream.twig`
  - JS: `public_html/assets/js/admin-article-writer-stream.js`
  - API:
    - `POST /api/admin/ai/article-writer-stream/generate` (SSE, chunked after generation)
    - `POST /api/admin/ai/article-writer-stream/save` (JSON)
  - Backend:
    - Routes: `app/Controllers/AISystemController.php`
    - Service: `app/Services/AI/ArticleWriterService.php`

### Embedded AI writers (within other admin forms)

- Admin content form embedded AI Writer modal:
  - Twig: `app/Views/admin/content/form.twig`
  - API: `POST /api/admin/ai/chat` (JSON or SSE)

- Admin service form AI Writer (service description generator):
  - Twig: `app/Views/admin/services/forms.twig`
  - API: `POST /api/admin/ai/chat` (JSON or SSE)

## Response Contracts (Current)

### `/api/admin/ai/article-writer/generate`

- Content-Type: `application/json`
- Returns:
  - `{ success: true, article: { title, seo_title, seo_description, content (HTML), slug, tags[], reading_time_minutes, key_points[] }, meta: { provider, model, attempts, word_target } }`

### `/api/admin/ai/chat`

- Non-stream:
  - Content-Type: `application/json`
  - Returns: `{ success: true, content: string, ... }`
- Stream:
  - Content-Type: `text/event-stream`
  - Events:
    - `data: {"meta": {...}}` (optional first event)
    - `data: {"content":"...chunk..."}` repeated
    - `data: [DONE]`

### `/api/admin/ai/article-writer-stream/generate`

- Content-Type: `text/event-stream`
- Emits events:
  - `type=start`, `type=title`, `type=content` chunks, `type=meta`, then `[DONE]`

## Known Issues Found

1. Live streaming page JS uses a non-existent endpoint:
   - `admin-article-writer-stream.js` calls `/api/admin/ai/article-writer-stream/publish`
   - Server only has `/api/admin/ai/article-writer-stream/save`

2. Live streaming page JS save payload mismatches server expectation:
   - Server expects `{ article: {title, content, ...}, publish, author_id }`
   - JS sends top-level `title/content/slug` and `publish`

3. Streaming meta event shape mismatch:
   - Server emits `{ type: "meta", meta: {...} }`
   - JS expects `data.slug` or `data.content` in some places

4. Streaming endpoint pulls SEO meta from the wrong place:
   - `ArticleWriterService::generateArticle()` returns SEO/meta fields inside `article`
   - Route used `$result['meta']` for seo_title/seo_description/slug/tags/key_points, but `$result['meta']` is provider/model/attempts/word_target

5. URL param `content` prefill is unsafe/fragile:
   - Needs decode (URL-encoding) + minimal sanitization before inserting into editor/textarea

## Implementation Checklist

1. Fix `public_html/assets/js/admin-article-writer-stream.js`
   - Use `/save` always; pass `publish` flag
   - Align payload to `{ article: {...}, publish, author_id }`
   - Fix meta event parsing (`data.meta.*`)

2. Fix `app/Controllers/AISystemController.php`
   - Streaming generate: populate meta from `$article` (not `$result['meta']`)
   - Streaming save route: accept both payload shapes (backward compatible)
   - Add optional alias route `/api/admin/ai/article-writer-stream/publish` -> same save handler

3. Harden URL prefill in:
   - `app/Views/admin/content/form.twig`
   - `app/Views/admin/services/forms.twig`
   - Decode safely + sanitize HTML before inserting

4. Verify with:
   - `php -l app/Controllers/AISystemController.php`
   - `php -l app/Services/AI/ArticleWriterService.php`
   - `node --check public_html/assets/js/admin-article-writer-stream.js`
   - `npm run validate`

## Acceptance Scenarios

1. `/admin/ai/article-writer`
   - Generate renders HTML preview
   - Publish and save draft succeed
   - URL prefill works without injecting unsafe HTML

2. `/admin/ai/article-writer-stream`
   - Generate streams title + content reliably
   - Meta (slug/tags/seo) is available to UI
   - Save draft uses `/save`
   - Publish uses `/save` with `publish:true`

3. Embedded AI Writers (content form + services form)
   - Stream/non-stream both work against `/api/admin/ai/chat`
   - Stream parsers ignore optional `{meta:...}` event and only append `content` chunks
   - URL prefill (topic/slug/content) decodes + sanitizes and only fills empty fields

