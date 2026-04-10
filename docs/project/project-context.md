# Project Context - BroxBhai

## Overview
Global context for BroxLab: structure, dependencies, and touchpoints that every contributor should review before diving into new features.

## Purpose
Provide a single reference that explains the repository layout, external services, and frequently inspected directories so onboarding and investigation stay efficient.

## Key Actions
- Read this document before editing `app/`, AI services, or automation workflows.
- Keep the directory tree at the top of the file in sync with reality when new folders are added.
- Reference the dependency lists and integrations sections when adding or updating Composer/npm packages.

## Related References
- `docs/guides/coding-standards.md` for security and SQL rules.
- `docs/index.md` to locate other guides, plans, and editors docs.
- `docs/project/cv-system-improvements.md` for upcoming capabilities.

Quick reference for understanding the BroxBhai project structure and dependencies.

---

## 🏗️ Project Overview

**BroxBhai** is a Bengali-first mobile tech platform with content management, AI features, Firebase integration, and automation tools.

- **Type**: Full-stack web application
- **Framework**: Custom PHP (not Laravel/CodeIgniter)
- **PHP Version**: 8.2+
- **Database**: MySQL/MariaDB

---

## 📦 Dependencies

### PHP (composer.json)
```json
"twig/twig": "^3.20"              // Template engine
"ezyang/htmlpurifier": "^4.17"   // HTML sanitization
"vlucas/phpdotenv": "^5.6"       // Environment variables
"phpmailer/phpmailer": "^6.9"    // Email sending
"kreait/firebase-php": "^7.0"    // Firebase integration
"guzzlehttp/guzzle": "^7.8"      // HTTP client
"symfony/dom-crawler": "^6.4"    // Web scraping
"symfony/css-selector": "^6.4"   // CSS selectors for scraping
"mpdf/mpdf": "^8.2"              // PDF generation
"endroid/qr-code": "^6.0"        // QR code generation
"erusev/parsedown": "^1.7"       // Markdown parsing
```

### Node.js (package.json)
```json
"firebase": "^12.9.0"            // Firebase SDK
"@anthropic-ai/sdk": "^0.78"     // Anthropic Claude
"@google/generative-ai": "^0.24" // Google Gemini
"@genkit-ai/googleai": "^1.16"  // Genkit
"@openrouter/sdk": "^0.9"        // OpenRouter
"genkit": "^1.16"                // AI framework
"esbuild": "^0.27"               // Bundler
"tailwindcss": "^3.4"            // CSS framework
```

---

## 📁 Directory Structure

Note: When adding a new feature that introduces a new major folder/module (or changes where code lives), update this section to match the repo.

```
broxbhai/
├── app/
│   ├── Controllers/     # Route handlers (closure-based)
│   ├── Models/          # Database operations
│   ├── Helpers/         # Utility functions
│   ├── Middleware/      # Request middleware
│   ├── Modules/         # Feature modules (Scraper, AI, etc.)
│   ├── Views/           # Twig templates
│   │   ├── public/      # Public-facing pages
│   │   ├── admin/       # Admin panel
│   │   ├── auth/        # Authentication pages
│   │   └── _macros/     # Reusable Twig macros
│   ├── FeatureFlags/    # Feature flag configurations
│   ├── Routes/          # Additional route files
│   └── Telegram/        # Telegram bot integration
├── Config/              # Configuration files
├── public_html/         # Webroot
│   ├── assets/          # CSS, JS, images
│   └── index.php        # Entry point
├── scripts/             # CLI scripts, workers
├── storage/             # Uploads, cache, logs
├── system/              # Framework core
├── docs/                # Documentation
└── Database/            # SQL schemas
```

---

## 🔗 Database Schema

Key tables (see `Database/` folder for full schema):
- `users` - User accounts
- `posts` - Content articles
- `categories` - Content categories
- `tags` - Content tags
- `media` - Uploaded files
- `notifications` - User notifications
- `autocontent_sources` - Web scraping sources
- `ai_conversations` - AI chat history

---

## 🎨 Frontend Assets

- **CSS**: Tailwind CSS (source in `public_html/assets/css/`)
- **JS**: Vanilla JS modules in `public_html/assets/js/`
- **Build**: esbuild via npm scripts

**Build Commands:**
```bash
npm run dev          # Development with watch
npm run build        # Production build
```

---

## 🔐 Authentication

- Session-based authentication via `AuthManager` class
- CSRF protection required for all state-changing requests
- Role-based access control (RBAC) available

**Key Classes:**
- [`app/Models/AuthManager.php`](..\app\Models\AuthManager.php) - Auth logic
- [`app/Models/SessionManager.php`](..\app\Models\SessionManager.php) - Session handling
- [`app/Models/SecurityManager.php`](..\app\Models\SecurityManager.php) - Security features

---

## 🤖 AI Features

AI features are handled through:
- [`app/Models/AIProvider.php`](..\app\Models\AIProvider.php) - Multi-provider AI abstraction with Fireworks autoscaling retry
- [`app/Controllers/AISystemController.php`](..\app\Controllers\AISystemController.php) - AI endpoints + tool execution
- [`app/Helpers/ToolRegistry.php`](..\app\Helpers\ToolRegistry.php) - Tool execution system (v3.0: parallel, streaming, circuit breaker)
- [`app/Helpers/ToolDefinitions.php`](..\app\Helpers\ToolDefinitions.php) - 10 registered tools (system health, DB queries, etc.)
- [`app/Helpers/PromptLoader.php`](..\app\Helpers\PromptLoader.php) - Prompt management

**Supported Providers:**
- Anthropic Claude
- Google Gemini
- OpenRouter (multi-provider)
- Fireworks AI (with autoscaling retry)
- Ollama (local)
- Hugging Face
- Kilo.ai

**AI Tool System (v3.0):**
- Parallel execution via `pcntl_fork` (sequential fallback on Windows)
- Streaming tool call support with SSE events
- Circuit breaker pattern (5 failures → open, 60s reset)
- Retry logic with exponential backoff per tool
- 7 error categories for intelligent handling
- Fireworks AI autoscaling retry (503 DEPLOYMENT_SCALING_UP)

---

## 📱 Firebase Integration

- Push notifications (FCM)
- User authentication
- Real-time features

**Key Files:**
- [`app/Helpers/FirebaseHelper.php`](..\app\Helpers\FirebaseHelper.php)
- [`app/Models/FirebaseModel.php`](..\app\Models\FirebaseModel.php)

---

## 🌐 Web Scraping

Automated content collection system:
- [`app/Models/AutoContentModel.php`](app/Models/AutoContentModel.php)
- [`app/Modules/Scraper/`](app/Modules/Scraper/) - Scraping modules

---

## 📝 Twig Templates

Template location: `app/Views/`

**Extensions:**
- `twig/intl-extra` for internationalization

**Macros:**
- `app/Views/_macros/flash.twig` - Flash messages
- `app/Views/_macros/dashboard_macros.twig` - Dashboard widgets

---

## 🐛 Error Handling

- [`app/Helpers/ErrorLogging.php`](..\app\Helpers\ErrorLogging.php) - Centralized logging
- `logActivity()` for user actions
- `logError()` for errors

---

## 📧 Email

- PHPMailer for sending emails
- Templates stored in database (see `EmailTemplate` model)
- [`app/Helpers/EmailHelper.php`](..\app\Helpers\EmailHelper.php)

---

## 🔧 Configuration

- Environment variables: `.env` file
- Database config: `Config/Db.php`
- App settings: `AppSettings` model (stored in database)
- Constants: `Config/Constants.php`

---

## Decision Log

### [2026-03-20] Centralize assistant chat APIs + feedback IDs
- Centralized assistant/coplay API routes in `app/Routes/AISystemRoutes.php` to avoid route override collisions during `app/Controllers/*.php` loading.
- Enforced CSRF required for public assistant chat (`POST /api/ai/chat`).
- Added SSE meta (`conversation_id`, `message_id`) so UI feedback can reference real DB message IDs instead of client-side indexes.

### [2026-03-21] AutoContent production hardening (CSRF + schema + fatal fix)
- Fixed production 500 caused by `AIProvider` being included twice under symlinked release paths by normalizing `require_once` to `realpath(...)` in all AIProvider include sites.
- Corrected AutoContent AI processing to be schema-tolerant (supports legacy `title/content/excerpt` fields; avoids calling non-existent metadata methods; avoids updating missing optional columns).
- Enforced CSRF on AutoContent admin API endpoints and aligned collect endpoints to POST-only usage.
- Added a schema health warning block to the AutoContent dashboard to surface missing tables/columns/auto-increment early.

### [2026-03-22] bdnews24 scraping system audit complete
- Conducted comprehensive audit of multi-agent scraping system (TickerScraper, ArticleScraper, ValidationAgent, DiffDetector, SelfHealingAgent, LearningAgent, NotificationAgent).
- **Critical findings** (addressed in CODING_STANDARDS.md): Input validation gaps, inconsistent error handling, SSRF risks, missing log rotation, lack of graceful shutdown.
- **High-priority improvements** (backlog): Persistent URL tracking, robust date parsing consolidation, improved content extraction, performance monitoring, configuration validation.
- **Documentation**: All findings consolidated into `docs/guides/coding-standards.md` security rules, error handling patterns, and logging standards. Audit report deleted per governance rule #7 (delete completed plans).

---

## 📄 License

MIT License
