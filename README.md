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
# Start the unified Node backend (API + AI assistant, default port 3000)
npm run node:start

# Start PHP development server (renders existing front-end / admin themes)
php -S localhost:8000 -t public_html

# For asset watching (separate terminal)
npm run dev
```

Visit `http://localhost:3000` for the Node-powered AI APIs and `http://localhost:8000` for the PHP frontend.

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
- Follow `docs/guides/coding-conventions.md`
- Run quality scans before committing
- Use `docs/index.md` to find the right documentation before adding new files or editing existing guides
- Update documentation for new features
- Test AI features thoroughly

---

## 📚 Documentation

- [Project Context](docs/project/project-context.md)
- [Coding Conventions](docs/guides/coding-conventions.md)
- [AI Coding Guide](docs/ai/AI_CODING_GUIDE.md)
- [Deployment Guide](web-host\DEPLOYMENT_GUIDE.md)
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

---

## 🚀 Quick Start

### 1. Clone and Setup
```bash
git clone git@github.com:habibbrox2/broxlab-repo.git broxlab
cd broxlab
composer install
npm install
```

### 2. Database Setup
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE broxlab;"

# Import schema
mysql -u root -p broxlab < Database/schema.sql
```

### 3. Environment Configuration
```bash
# Copy example config
cp .env.example .env

# Edit configuration
nano .env  # or your preferred editor
```

### 4. Build and Run
```bash
npm run build
php -S localhost:8000 -t public_html
```

Visit `http://localhost:8000` to get started!

---

## 📊 System Status

| Component | Status | Notes |
|-----------|--------|-------|
| **Backend** | ✅ Stable | PHP 8.0+ compatible |
| **Frontend** | ✅ Stable | Tailwind CSS + JavaScript |
| **AI System** | ✅ Active | Multi-provider support |
| **Database** | ✅ Stable | MySQL/MariaDB |
| **Build System** | ✅ Stable | esbuild + npm |

---

## 🐛 Recent Updates

### v2.1.0 (March 2026)
- ✅ Fixed Admin Assistant UI positioning
- ✅ Added AI model status indicators
- ✅ Enhanced security validation
- ✅ Improved asset bundling
- ✅ Updated Node.js compatibility

### v2.0.0 (February 2026)
- 🚀 Complete AI assistant system overhaul
- 🚀 Self-improving agent architecture
- 🚀 Multi-provider AI support
- 🚀 Enhanced security measures
- 🚀 Improved documentation structure

---

## 📈 Performance Metrics

- **Build Time**: ~30 seconds (npm run build)
- **Page Load**: <2s (optimized assets)
- **API Response**: <500ms (cached queries)
- **Memory Usage**: <100MB (typical usage)

---

## 🎯 Roadmap

- [ ] Mobile app integration
- [ ] Advanced analytics dashboard
- [ ] Plugin system for extensions
- [ ] Enhanced AI capabilities
- [ ] Performance optimizations

---

## 🤖 AI Assistant

The built-in AI assistant provides:

- **Smart Content Creation**: Generate posts, pages, and content
- **Code Assistance**: Help with development tasks
- **System Monitoring**: Track performance and issues
- **User Support**: Answer common questions

Access the AI assistant through the admin panel or via API endpoints.

---

## 📞 Support

- **Documentation**: [docs/](docs/)
- **Issues**: [GitHub Issues](https://github.com/habibbrox2/broxlab-repo/issues)
- **Website**: [broxlab.online](https://broxlab.online)
- **Email**: support@broxlab.online

---

*Last updated: March 2026*
