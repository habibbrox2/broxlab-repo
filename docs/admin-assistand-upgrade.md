# Admin Assistant Hardening + Quality Upgrade Plan

**Summary**
Admin Assistant‑কে নির্ভরযোগ্য, দ্রুত এবং স্থিতিশীল করতে UI/JS/API ফ্লোতে audit‑driven fixes, reliability guards, এবং test/monitoring যোগ করা হবে। লক্ষ্য: সব core feature (model load, chat stream, file upload, context, shortcuts, log monitor) স্থায়ীভাবে ঠিকভাবে কাজ করবে।

---

## ✅ Recently Fixed Issues (2026-03-12)

### 1. CSS Positioning Fix
- **Issue**: Admin Assistant was appearing on the LEFT side instead of RIGHT side
- **Root Cause**: CSS `inset: 0 !important` was overriding positioning
- **Fix**: Changed to explicit `right: 0 !important; left: auto !important` in `public_html/ai/css/ai-style.css`

### 2. Model Status Indicator
- **Issue**: No visual indication of AI model connectivity status
- **Fix**: Added online/offline/connecting status indicator in `public_html/ai/js/ai-admin.js`
  - Green: Online (model connected)
  - Red: Offline (connection failed)
  - Yellow: Connecting (in progress)

### 3. Delete Button Removal
- **Issue**: Delete button was present next to send button in input area (unnecessary)
- **Fix**: Removed the trash icon button from input area in `app/Views/partials/ai-assistant/admin.twig`

### 4. PHP Duplicate Require Fix
- **Issue**: Duplicate `require_once` statement in `AISystemChatController.php`
- **Fix**: Removed duplicate line 16 that was requiring PromptLoader.php twice

---

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

---

## ✅ New AI Capabilities Added (2026-03-19)

### 1. Web Search Endpoint
- **Endpoint**: `POST /api/admin/ai/websearch`
- **Purpose**: Add real-time web data to AI model responses
- **Features**:
  - Supports `:online` model suffix or `web` plugin
  - Configurable max_results, include_domains, exclude_domains
  - Engine selection: exa, native, firecrawl, parallel
- **Auth**: Requires auth + admin_only + CSRF

### 2. PDF Input Endpoint
- **Endpoint**: `POST /api/admin/ai/pdf`
- **Purpose**: Process PDF documents with AI
- **Features**:
  - Supports URL (public PDFs) or base64 (local PDFs)
  - PDF engine selection: pdf-text (free), mistral-ocr (best for scanned), native
  - Returns annotations for cost optimization
- **Auth**: Requires auth + admin_only + CSRF

### 3. PDF Continue Endpoint (Skip Parsing Costs)
- **Endpoint**: `POST /api/admin/ai/pdf/continue`
- **Purpose**: Reuse annotations from previous requests to skip PDF parsing costs
- **Features**:
  - Send annotations from previous response to avoid re-parsing
  - Reduces API costs for large documents
- **Auth**: Requires auth + admin_only + CSRF

### 4. Text-to-Speech (TTS) Endpoint
- **Endpoint**: `POST /api/admin/ai/tts`
- **Purpose**: Convert text to speech audio
- **Features**:
  - Multiple models: tts-1, tts-1-hd
  - Multiple voices: alloy, echo, fable, onyx, nova, shimmer
  - Multiple formats: mp3, opus, aac, flac, wav
- **Auth**: Requires auth + admin_only + CSRF

### 5. Image Generation Endpoint
- **Endpoint**: `POST /api/admin/ai/image`
- **Purpose**: Generate images using AI
- **Features**:
  - Model: gpt-image-1
  - Quality: auto, high, medium, low
  - Size: 1024x1024, 1024x1536, 1536x1024
  - Multiple images: n parameter (1-10)
- **Auth**: Requires auth + admin_only + CSRF

### 6. Enhanced Image/Vision Support
- **Endpoint**: `POST /api/admin/ai/chat` (existing, enhanced)
- **Features**:
  - Multi-format support: URL, Base64, data URLs
  - Detail levels: low, high, original, auto
  - File attachment preview in chat
- **Auth**: Requires auth + admin_only + CSRF

### 7. Helper Function Added
- **Function**: `getOpenRouterApiKey()`
- **Location**: `app/Routes/AISystemRoutes.php`
- **Purpose**: Get OpenRouter API key from database settings or environment variable

---

## 📋 API Usage Examples

### Web Search
```json
POST /api/admin/ai/websearch
{
  "query": "latest news about AI",
  "model": "openai/gpt-4o",
  "max_results": 5,
  "include_domains": ["techcrunch.com", "wired.com"],
  "engine": "exa"
}
```

### PDF Processing
```json
POST /api/admin/ai/pdf
{
  "prompt": "Summarize this document",
  "url": "https://example.com/document.pdf",
  "model": "openai/gpt-4o-mini",
  "engine": "pdf-text"
}
```

### Image Generation
```json
POST /api/admin/ai/image
{
  "prompt": "A beautiful sunset over mountains",
  "model": "gpt-image-1",
  "quality": "high",
  "size": "1024x1024",
  "n": 1
}
```

### Text-to-Speech
```json
POST /api/admin/ai/tts
{
  "text": "Hello, this is a test message",
  "model": "tts-1",
  "voice": "alloy",
  "format": "mp3"
}
```

---

## ✅ New Admin Tools Added (2026-03-19)

### 11. List Storage Files Tool
- **Command**: `/list_storage_files`
- **Purpose**: List files and directories in storage folder
- **Parameters**: path, filter, limit

### 12. Get App Settings Tool
- **Command**: `/get_app_settings`
- **Purpose**: Retrieve application settings from database
- **Parameters**: category (general, ai, appearance, etc.)

### 13. Search Knowledge Base Tool
- **Command**: `/search_knowledge_base`
- **Purpose**: Search the AI knowledge base for relevant information
- **Parameters**: query, category, limit

### 14. Reindex Knowledge Base Tool
- **Command**: `/reindex_knowledge_base`
- **Purpose**: Re-index all knowledge base items with embeddings
- **Parameters**: provider (openai, cohere, voyage, ollama)

---

## ✅ Real-time Collaboration Features Added (2026-03-19)

### 1. Presence Tracking
- **Endpoint**: `GET /api/admin/ai/presence`
- **Purpose**: See who's currently using the AI assistant
- **Features**:
  - Shows active users with their last activity
  - Auto-cleans sessions older than 5 minutes
  - Returns user ID, username, action, last active time

### 2. Heartbeat
- **Endpoint**: `POST /api/admin/ai/heartbeat`
- **Purpose**: Keep session alive, update current action
- **Features**:
  - Updates user's last active timestamp
  - Tracks current action (chatting, idle, etc.)

### 3. Session Sharing
- **Endpoint**: `POST /api/admin/ai/share`
- **Purpose**: Generate shareable session link for collaboration
- **Features**:
  - Share AI session with another admin
  - Configurable expiration (default 24 hours)
  - Returns shareable URL with token

---

## ✅ RAG Enhancement - Multi-Provider Embeddings (2026-03-19)

### New Methods Added to RAGEngine
- `generateEmbeddingMultiProvider()` - Try multiple providers in order
- `generateEmbeddingForProvider()` - Generate embedding for specific provider
- `callEmbeddingAPI()` - Call embedding API for OpenAI, Cohere, Voyage
- `generateEmbeddingOllama()` - Generate embedding using local Ollama
- `reindexAllWithProvider()` - Re-index all knowledge with specified provider

### Supported Providers
1. **OpenAI** - text-embedding-3-small
2. **Cohere** - embed-english-v3.0
3. **Voyage** - voyage-2
4. **Ollama** - nomic-embed-text (local)
5. **Python** - sentence-transformers (fallback)

