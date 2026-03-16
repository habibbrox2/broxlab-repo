# BroxLab 🌐

**BroxLab** (formerly BroxBhai) is a comprehensive full-stack web application built with PHP for managing content, services, devices, notifications, and AI-driven features. It features a rich admin panel, API endpoints, Telegram integration, automated content tools, and an intelligent AI assistant system with self-improvement capabilities.

[![PHP Version](https://img.shields.io/badge/PHP-8.0+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Build Status](https://img.shields.io/badge/Build-Passing-brightgreen.svg)]()
[![GitHub Repo](https://img.shields.io/badge/GitHub-Repo-blue.svg)](https://github.com/habibbrox2/broxlab-repo)
[![Website](https://img.shields.io/badge/Website-broxlabs.online-green.svg)](https://broxlab.online)

---

## ✨ Key Features

- 📝 **Content Management**: Posts, pages, categories, tags, media handling
- 👥 **User & Role Management**: Roles, permissions, user sessions, security tools
- 📢 **Notifications & Messaging**: Email, SMS, push notifications, Telegram integration
- 🤖 **AI & Automation**: AI conversations, knowledge base, content scraping/crawling, autotext generation, self-improving agent system
- 📱 **Device & IoT Features**: Device sync, control commands, telemetry
- 📊 **Analytics & Logging**: Event logging, audit trails, activity history
- 🧩 **Modular Architecture**: Clean separation of Controllers, Models, Views, Middleware
- 🔒 **Security**: CSRF protection, input sanitization, secure DB queries

---

## 🛠️ Tech Stack

- **Backend**: PHP 8.0+ (Custom Framework)
- **Database**: MySQL / MariaDB
- **Frontend**: JavaScript, Tailwind CSS
- **Build Tools**: esbuild, npm
- **AI Integration**: OpenRouter, OpenAI, Anthropic, and more
- **Other**: Firebase (notifications), Telegram Bot API

---

## 📁 Project Structure

```
├── app/                    # Application core
│   ├── Controllers/        # Route handlers
│   ├── Models/            # Data models
│   ├── Views/             # Twig templates
│   ├── Middleware/        # Request middleware
│   └── Modules/           # Specialized modules (AI, etc.)
├── build/                 # Build configuration
│   ├── esbuild-*.mjs      # Asset bundlers
│   └── tailwind.config.js # CSS framework config
├── Config/                # Configuration files
├── docs/                  # Documentation
│   ├── ai/               # AI-specific docs
│   └── *.md              # Project docs
├── public_html/           # Web root
│   ├── assets/           # Compiled assets
│   ├── ai/               # AI assistant JS
│   └── index.php         # Entry point
├── scripts/               # Utility scripts
├── storage/               # Runtime data
│   ├── cache/            # Cache files
│   ├── logs/             # Application logs
│   └── tmp/              # Temporary files
├── system/                # Framework core
├── vendor/                # Composer dependencies
├── broxlab.agent.md      # Custom AI agent config
└── README.md             # This file
```

---

## 📋 Prerequisites

- **PHP 8.0+** with extensions: PDO, mbstring, json, curl, openssl, gd
- **Composer** (PHP dependency manager)
- **MySQL / MariaDB** (Database)
- **Node.js + npm** (Frontend build tools)
- **Git** (Version control)

---

## 🚀 Installation

1. **Clone the repository**
   ```bash
   git clone git@github.com:habibbrox2/broxlab-repo.git broxlab
   cd broxlab
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**
   ```bash
   npm install
   ```

4. **Set up the database**
   - Create a new MySQL database
   - Import the schema (check `Database/` folder for SQL files)
   ```bash
   mysql -u <username> -p <database_name> < Database/schema.sql
   ```

5. **Configure environment**
   - Edit `Config/Db.php` for database credentials
   - Update `Config/Constants.php` for app settings
   - Add API keys in `.env` (Firebase, Telegram, AI providers)

6. **Set permissions**
   ```bash
   chmod -R 755 storage/
   chmod -R 755 public_html/uploads/
   ```

7. **Build assets**
   ```bash
   npm run build
   ```

---

## ▶️ Usage

### Local Development
```bash
# Start PHP development server
php -S localhost:8000 -t public_html

# For asset watching (separate terminal)
npm run dev
```

Visit `http://localhost:8000` in your browser.

### Production Deployment
1. Upload files to your web server
2. Point document root to `public_html/`
3. Ensure `storage/` is writable
4. Run `composer install --no-dev`
5. Execute `npm run build`

---

## 🤖 AI Features

BroxBhai includes an advanced AI assistant system:

- **Multi-Provider Support**: OpenRouter, OpenAI, Anthropic, Fireworks, etc.
- **Self-Improving Agent**: User feedback collection and analysis for continuous improvement
- **Knowledge Base**: Integrated context-aware responses
- **Streaming Responses**: Real-time AI chat with typing effects
- **Admin Copilot**: AI assistance for content creation and management

### AI Feedback Analysis
Run the feedback analyzer:
```bash
php scripts/analyze_ai_feedback.php
```

---

## 📜 API Reference

### AI Chat API
- `POST /api/chat` - Send message to AI
- `POST /api/ai/feedback` - Submit feedback on AI responses
- `GET /api/ai/models/list` - List available models

### Other Endpoints
- `POST /api/public-chat/support` - Public support contact
- `POST /api/log-activity` - Log user activities

Full API documentation available in `docs/` folder.

---

## 🛠️ Available Scripts

- `php scripts/quality_scan.php` - Code quality check
- `php scripts/security_scan.php` - Security audit
- `php scripts/analyze_ai_feedback.php` - AI feedback analysis
- `npm run build` - Build frontend assets
- `npm run dev` - Watch mode for development
- `npm run lint` - JavaScript linting

---

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines
- Follow `docs/CODING_CONVENTIONS.md`
- Run quality scans before committing
- Update documentation for new features
- Test AI features thoroughly

---

## 📚 Documentation

- [Project Context](docs/PROJECT_CONTEXT.md)
- [Coding Conventions](docs/CODING_CONVENTIONS.md)
- [AI Coding Guide](docs/ai/AI_CODING_GUIDE.md)
- [Deployment Guide](docs/DEPLOYMENT_GUIDE.md)
- [AI Context Index](docs/ai/AI_CONTEXT_INDEX.md)

---

## 🔒 Security

- Keep dependencies updated
- Never commit `.env` files
- Use HTTPS in production
- Regular security scans with `php scripts/security_scan.php`

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 📞 Contact & Support

For questions or support:
- Visit our website: [https://broxlab.online](https://broxlab.online)
- Create an issue on GitHub
- Use the built-in AI assistant
- Check the Telegram integration

---

*Built with ❤️ for efficient web development and AI-powered experiences.*

   - Create a new MySQL database
   - Import schema from `Database/*.sql` (e.g., `Database/users.sql`, `Database/posts.sql`, etc.)

   ```bash
   mysql -u <user> -p <database> < Database/db.sql
   ```

5. **Configure environment**

   - Copy the sample configuration file (if exists) or edit `Config/Constants.php`, `Config/Db.php`, `Config/Firebase.php`, etc.
   - Ensure correct database credentials, base URL, and any API keys (Firebase, Telegram, etc.)

6. **Set up writable directories**

   Ensure the web server user can write to:

   - `storage/`
   - `app/Uploads/` (if used)

7. **Build assets**

   ```bash
   npm run build
   ```

---

## ▶ Running Locally

Start the built-in PHP server (for development):

```bash
php -S localhost:8000 -t public_html
```

Then visit: `http://localhost:8000`

---

## 🧪 Testing

(If tests exist, describe how to run them. Otherwise, remove this section.)

```bash
# Example PHPUnit command (if available)
./vendor/bin/phpunit
```

---

## 🧩 Deployment

Deployment steps depend on your hosting provider. Common steps include:

1. Upload source to your server
2. Install PHP dependencies via Composer
3. Run frontend build (npm run build)
4. Point your webroot to `public_html/`
5. Ensure writable permissions on `storage/` and any upload directories

---

## 🐛 Recent Bug Fixes & Improvements

### Admin Assistant UI
- Fixed CSS positioning issue (was appearing on left instead of right side)
- Added AI model online/offline/connecting status indicator
- Removed unnecessary delete button from input area

### Backend
- Fixed duplicate require_once statement in AISystemChatController.php
- Code reviewed for security vulnerabilities (SQL injection, XSS protection verified)

---

## � Security Notes

- Always keep dependencies up to date
- Protect `.env` / config files from public access
- Use HTTPS in production

---

## Contributing

Contributions are welcome! Please:

1. Fork the repo
2. Create a feature branch
3. Submit a pull request with a clear description

---

## License

Specify your project's license here (e.g., MIT, Apache 2.0). Update this section accordingly.

---

## 📄 Contact

For questions or support, open an issue in this repository.
#   t e s t   d e p l o y m e n t   0 3 / 1 6 / 2 0 2 6   1 5 : 0 9 : 2 2 
 
 #   f i n a l   t e s t 
 
 #   c o m p o s e r   f i x 
 
 #   s h a r e d   f i l e s   f i x 
 
 #   u p l o a d s   p a t h   f i x 
 
 #   c o m p o s e r   a n d   n p m   f i x   w i t h   v e r s i o n 
 
 #   e s b u i l d   f i x   -   i n s t a l l   d e v   d e p e n d e n c i e s 
 
 #   v e r s i o n . j s o n   a u t o   v e r s i o n i n g   f i x 
 
 #   f i x :   u p d a t e   t o   N o d e . j s   2 0   f o r   F i r e b a s e   c o m p a t i b i l i t y 
 
 