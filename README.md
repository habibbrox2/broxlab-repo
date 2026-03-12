# BroxBhai

**BroxBhai** is a full-stack web application built with PHP (likely CodeIgniter / custom framework) for managing content, services, devices, notifications, and AI-driven features. It includes a rich admin panel, API endpoints, Telegram integration, automated content tools, and a full-featured backend.

---

## 🚀 Key Features

- ✅ **Content Management**: Posts, pages, categories, tags, media handling
- ✅ **User & Role Management**: Roles, permissions, user sessions, security tools
- ✅ **Notifications & Messaging**: Email, SMS, push notifications, Telegram integration
- ✅ **AI & Automation**: AI conversations, knowledge base, content scraping/crawling, autotext generation
- ✅ **Device & IoT Features**: Device sync, control commands, telemetry
- ✅ **Analytics & Logging**: Event logging, audit trails, activity history
- ✅ **Modular Structure**: Modular app structure (Controllers, Models, Views, Middleware, etc.)

---

## 🧩 Repository Structure

```
app/            # Application source: Controllers, Models, Views, Middleware
broxbhai/       # Build & front-end tooling (esbuild, Tailwind config, etc.)
Config/         # Configuration helpers & 3rd party config files
Database/       # SQL schema / table definitions
public_html/    # Public webroot (front controller, assets)
scripts/        # Utility scripts
storage/        # Runtime storage (uploads, cache, logs)
system/         # Framework/system core (if applicable)
vendor/         # Composer dependencies
```

---

## 🛠️ Prerequisites

- PHP 8.0+ (or higher) with required extensions (PDO, mbstring, json, curl, openssl)
- Composer
- MySQL / MariaDB
- Node.js + npm (for frontend tooling)

---

## 🧰 Installation / Setup

1. **Clone repository**

   ```bash
   git clone <repo-url> broxbhai
   cd broxbhai
   ```

2. **Install PHP dependencies**

   ```bash
   composer install
   ```

3. **Install frontend dependencies**

   ```bash
   npm install
   ```

4. **Set up database**

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

## 🛡 Security Notes

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
