# PROJECT INSTRUCTIONS
## Project Guide (Clean production-ready, merged)

Version: 5.1.0
Last Updated: March 05, 2026
Language: Bangla (Primary, ASCII-safe edition)
Project Type: PHP-based Web Application (Modern /app structure) with Firebase and SweetAlert2

---

## TL;DR Quick Checklist

1. Active template path only: `app/Views` (never `app/templates`).
2. Front controller is `public_html/index.php`.
3. Public filesystem base is `public_html` (keep legacy `/public/...` compatibility).
4. Active work scope: `app`, `Config`, `public_html`, `Database`, `build`.
5. Do not edit by default: `vendor`, `node_modules`, `_backup`, generated `dist`.
6. PHP class names: PascalCase + exact class-file match.
7. PHP method/property/new global function names: camelCase.
8. Twig/JS/CSS filenames: kebab-case.
9. SQL table/column and API JSON keys: snake_case.
10. No filename spaces, no `(1)` suffix, no class/file mismatch.
11. Run `php -l` on touched PHP files.
12. Run `npm run naming:check -- --changed` before merge.

---

## TABLE OF CONTENTS

1. Project Overview
2. Folder Structure
3. Core Principles
4. File Creation Rules
5. Naming Convention (Direct Rules)
6. Database Management
7. Routing System
8. Message System (SweetAlert2)
9. Security Guideline
10. Workflow Patterns
11. Firebase Integration
12. Testing and Deployment
13. Quick Reference
14. Support and Docs
15. Critical Reminders

---

## Project Overview

This is a PHP-based MVC architecture project where:
- Frontend: HTML/CSS/JS with Twig templates
- Backend: PHP (OOP + procedural helpers)
- Database: MySQL/MariaDB
- Authentication: Firebase Authentication
- Notifications: Firebase Cloud Messaging (FCM)
- Templating: Twig

Main goals:
- Modern web application with Firebase integration
- Secure authentication and authorization
- Real-time notifications
- Modular and scalable architecture

---

## Folder Structure

```text
project-root/
|
|-- app/                           # Core logic (NOT public)
|   |-- Controllers/               # Request handlers / route controllers
|   |-- Models/                    # Database models and business logic
|   |-- Helpers/                   # Utility functions and helper modules
|   |-- Middleware/                # Middleware (auth, rate-limit, csrf)
|   |-- Routes/                    # Route registration modules
|   `-- Views/                     # Twig templates (presentation)
|
|-- Config/                        # Configuration (never public)
|-- Database/                      # DB schema and migrations
|-- storage/                       # Runtime logs/cache/temp
|-- public_html/                   # Only public assets + entry point
|-- build/                         # Build config/scripts
|-- docs/                          # Documentation
|-- vendor/                        # Composer dependencies
|-- node_modules/                  # NPM dependencies
|-- .env
|-- composer.json
|-- package.json
`-- PROJECT.INSTRUCTIONS.md
```

Key notes:
- Active template directory: `app/Views`
- Public directory: `public_html`
- Keep legacy `/public/...` URL/data compatibility when required

---

## Core Principles

### 1) Single Responsibility Principle
- Models: DB and domain logic
- Controllers: request/response orchestration
- Helpers: reusable utility/domain functions
- Views: presentation only

### 2) DRY (Do Not Repeat Yourself)
- Avoid duplicate logic
- Put shared logic in helper/service/model
- Reuse Twig partials/components

### 3) Security First
- Validate and sanitize every input
- Use prepared statements
- Escape output
- Validate CSRF token

### 4) Convention Over Configuration
- Follow naming conventions consistently
- Respect folder boundaries
- Follow established project patterns

---

## File Creation Rules

### Rule #0: Always use `/app` structure for application code

Right locations:
- `/app/Models/`
- `/app/Controllers/`
- `/app/Helpers/`
- `/app/Views/`
- `/app/Middleware/`
- `/app/Routes/`

Deprecated for new code:
- `/classes/`
- `/controllers/`
- `/helpers/`
- `/templates/`
- `/public/` (renamed base)

### Rule #1: Feature-first edits

Wrong:
- Creating many new files for a small feature without checking existing modules.

Correct:
1. Find related existing files first.
2. Extend existing method/class where practical.
3. Create new files only when clearly needed.

### Rule #2: Scan before creating files

Before creating a file, scan:
- `app/Models`
- `app/Controllers`
- `app/Helpers`
- `app/Views`
- `app/Middleware`

### Rule #3: Use correct require paths

Controller to model:
```php
require_once __DIR__ . '/../Models/UserModel.php';
```

Helper to model:
```php
require_once __DIR__ . '/../Models/EmailTemplate.php';
```

Config to model:
```php
require_once __DIR__ . '/../app/Models/UserModel.php';
```

---

## Naming Convention (Direct Rules)

### Canonical rules by artifact
- PHP class name: PascalCase (`UserModel`, `AuthManager`, `UploadService`)
- PHP class file: exact class-name match
- PHP method/property: camelCase (`findById`, `$userId`)
- PHP global function (new): camelCase
- Twig filename: kebab-case
- JS filename: kebab-case
- JS class: PascalCase
- JS function/variable: camelCase
- CSS filename: kebab-case
- SQL table/column: snake_case
- SQL migration filename: `YYYY-MM-DD_action_target.sql`
- Env vars and constants: UPPER_SNAKE_CASE
- Route URL path: kebab-case
- API JSON keys (new): snake_case

### Acronym casing rule (locked)
- Use: `Api`, `Id`, `Url`, `Html`, `Pdf`
- Accepted: `ApiClient`, `getUserId`, `redirectUrl`
- Rejected: `APIClient`, `getUserID`, `redirectURL`

### Hard constraints
1. No filename spaces
2. No duplicate suffix pattern `(1)`
3. No class/file mismatch
4. No case-only rename conflict in same folder

### Validation commands
```bash
npm run naming:check
npm run naming:check -- --changed
npm run naming:check -- --all
```

---

## Database Management

### Database file locations
```text
Database/
|-- schema.sql
|-- users_table.sql
|-- notifications_table.sql
|-- notification_templates_table.sql
|-- fcm_tokens_table.sql
`-- migrations/
```

### New field/table process

Step 1: Create migration
```sql
-- File: Database/migrations/2026-03-05_add_fcm_token_field.sql
ALTER TABLE `users`
ADD COLUMN `fcm_token` VARCHAR(255) NULL AFTER `email`,
ADD INDEX `idx_fcm_token` (`fcm_token`);
```

Step 2: Update table structure files.

Step 3: Reflect change in `Database/schema.sql`.

Best practices:
- Add clear comments
- Add indexes where needed
- Select correct data types
- Keep rollback notes in migration

---

## Routing System

Active routing layout:
- Front controller: `public_html/index.php`
- Config bootstrap: `Config/*`
- Route modules: `app/Routes/*`
- Controllers: `app/Controllers/*`

Guidelines:
- Keep route contracts stable
- Avoid breaking API routes without explicit migration
- Keep middleware mapping explicit

---

## Message System (SweetAlert2)

### File locations
```text
public_html/assets/js/
|-- sweetalert2-handler.js
`-- dist/sweetalert2-handler.js

app/Views/_macros/flash.twig
app/Views/layout.twig
app/Views/admin/layout.twig
docs/SWEETALERT2_HANDLER.md
```

### API usage
```javascript
window.showMessage('Data saved', 'success');
window.showToast('Quick message', 'info');
window.showAlert('Warning', 'Please confirm', 'warning');

if (await window.showConfirm('Delete item?', 'Confirm')) {
  // proceed
}
```

### Server-side flash usage
```php
showMessage('Profile updated!', 'success');
redirect('/dashboard');
```

---

## Security Guideline

### 1) Input validation and sanitization
- Keep centralized sanitization in `Config/Functions.php`
- Never trust raw request input

### 2) SQL injection prevention
Always use prepared statements:
```php
$stmt = $mysqli->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
```

Never do direct concatenation:
```php
$query = "SELECT * FROM users WHERE email = '$email'";
```

### 3) Sensitive data handling
- Keep secrets in `.env`
- Do not commit `.env`

### 4) XSS prevention
- Twig auto-escape should stay enabled
- Prefer `textContent` over `innerHTML` in JS

### 5) Public directory hygiene
- Do not place sensitive PHP/config in `public_html`
- Keep only assets and public entry-related files there

---

## Workflow Patterns

### Pattern 1: New feature implementation
1. Understand requirement
2. Scan existing modules
3. Apply DB change if needed
4. Update model/service
5. Update controller/route
6. Update views/assets
7. Validate and regression test

### Pattern 2: Bug fix workflow
`Bug report -> related files -> root cause -> fix -> test -> verify`

### Pattern 3: Code review checklist
Security:
- Input validated?
- SQL safe?
- XSS safe?
- CSRF validated?
- Auth checks present?

Quality:
- DRY maintained?
- Naming convention followed?
- Error handling present?

Delivery:
- Touched PHP lint passed?
- Naming check passed?
- Path migration (`public_html`, `app/Views`) unaffected?

---

## Firebase Integration

### File locations
```text
public_html/
|-- firebase-messaging-sw.js
`-- assets/js/
    |-- firebase-init.js
    |-- firebase-auth.js
    `-- firebase-fcm.js
```

Rules:
1. Avoid inline Firebase bootstrapping scripts.
2. Keep service worker at root (`/firebase-messaging-sw.js`).
3. Keep config in `Config/Firebase.php`.
4. Keep frontend auth and backend session in sync.

---

## Testing and Deployment

### Local setup
1. Configure `.env`
2. `composer install`
3. `npm install`
4. Import `Database/schema.sql`
5. Build assets

### Commands
```bash
composer install
composer dump-autoload
npm install
npm run build
npm run lint
npm run naming:check -- --changed
```

### Deployment checklist
- Production `.env` values updated
- Debug disabled
- HTTPS enabled
- DB backup taken
- Migrations applied
- Build artifacts ready
- No sensitive public files

---

## Quick Reference

Common paths:
```php
require_once __DIR__ . '/../Config/Db.php';
$userModel = new UserModel($mysqli);
echo $twig->render('dashboard/index.twig', $data);
```

Validation:
```bash
php -l app/Controllers/SomeController.php
php -l app/Helpers/SomeHelper.php
npm run naming:check -- --changed
```

---

## Support and Docs

- Firebase: https://firebase.google.com/docs
- PHP: https://www.php.net/docs.php
- Twig: https://twig.symfony.com/doc/
- MySQL: https://dev.mysql.com/doc/

---

## Critical Reminders

1. Scan existing files before creating new files.
2. Always validate/sanitize input.
3. Always use prepared statements.
4. Always escape output.
5. Always enforce auth and authorization.
6. Always validate CSRF token.
7. Keep strict structure (`app/Views`, `public_html`, `Config`, `Database`).
8. Test before deployment.
9. Avoid accidental edits in excluded folders.
10. Keep security as top priority.

---

END OF PROJECT INSTRUCTIONS
