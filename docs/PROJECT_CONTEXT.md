# Project Context - BroxBhai

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
- [`app/Models/AuthManager.php`](app/Models/AuthManager.php) - Auth logic
- [`app/Models/SessionManager.php`](app/Models/SessionManager.php) - Session handling
- [`app/Models/SecurityManager.php`](app/Models/SecurityManager.php) - Security features

---

## 🤖 AI Features

AI features are handled through:
- [`app/Models/AIProvider.php`](app/Models/AIProvider.php) - Multi-provider AI abstraction
- [`app/Controllers/AISystemController.php`](app/Controllers/AISystemController.php) - AI endpoints
- [`app/Helpers/PromptLoader.php`](app/Helpers/PromptLoader.php) - Prompt management

**Supported Providers:**
- Anthropic Claude (via @anthropic-ai/sdk)
- Google Gemini (via @google/generative-ai)
- OpenRouter (multi-provider)

---

## 📱 Firebase Integration

- Push notifications (FCM)
- User authentication
- Real-time features

**Key Files:**
- [`app/Helpers/FirebaseHelper.php`](app/Helpers/FirebaseHelper.php)
- [`app/Models/FirebaseModel.php`](app/Models/FirebaseModel.php)

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

- [`app/Helpers/ErrorLogging.php`](app/Helpers/ErrorLogging.php) - Centralized logging
- `logActivity()` for user actions
- `logError()` for errors

---

## 📧 Email

- PHPMailer for sending emails
- Templates stored in database (see `EmailTemplate` model)
- [`app/Helpers/EmailHelper.php`](app/Helpers/EmailHelper.php)

---

## 🔧 Configuration

- Environment variables: `.env` file
- Database config: `Config/Db.php`
- App settings: `AppSettings` model (stored in database)
- Constants: `Config/Constants.php`

---

## 📄 License

MIT License
