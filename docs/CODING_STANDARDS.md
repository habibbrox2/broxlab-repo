# BroxBhai Coding Standards
**Version:** 1.0 | **Date:** March 22, 2026  
**Purpose:** Single source of truth for security, database, and code quality standards across BroxBhai.

---

## Security (Non-Negotiable)

### CSRF Protection
- **Rule:** All state-changing requests (POST, PUT, PATCH, DELETE) must validate CSRF tokens.
- **Implementation:**
  - Use `validateCsrfToken()` helper in controllers, OR
  - Follow existing CSRF middleware patterns in `app/Middleware/*`
  - Include CSRF token meta tag in Twig layouts: `<meta name="csrf-token" content="{{ csrf_token() }}">`
  - Pass token in AJAX headers: `'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content`
- **Reference:** [AGENTS.md - Hard rules](../AGENTS.md#hard-rules-security--correctness)

### Input Sanitization
- **Rule:** All user input displaying as HTML must pass through `PurifierHelper::purify()`.
- **When:** Rich text editors, user-generated content, imported data
- **Example:**
  ```php
  $cleanHtml = PurifierHelper::purify($userInput);
  ```
- **Do NOT:** Use `raw` filter in Twig without sanitization first.

### Secrets Management
- **Rule:** Never commit credentials to the repository.
- **Where credentials live:** `.env` file only (add to `.gitignore` — already done)
- **Do NOT:** Paste API keys, database passwords, JWT secrets, or Firebase credentials in code or comments
- **Rotation:** If exposed, rotate immediately and document in git history (do not revert).
- **Checking code:** Use `git diff --cached` before commit to catch secrets.

---

## Database (SQL Patterns)

### Prepared Statements (Required)
- **Rule:** All SQL queries must use prepared statements. No string interpolation.
- **Pattern (mysqli OOP):**
  ```php
  $stmt = $mysqli->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  ```
- **Why:** Prevents SQL injection attacks.

### Explicit Column Selection
- **Rule:** Never use `SELECT *`. Always specify columns needed.
- **Bad:** `SELECT * FROM users`
- **Good:** `SELECT id, name, email, created_at FROM users`
- **Why:** Prevents accidental exposure of sensitive columns, improves query performance, clarifies intent.

### Model-Based Access
- **Rule:** Database operations must go through Model classes in `app/Models/*`.
- **Do NOT:** Write raw SQL in controllers (except in Model classes).
- **Pattern:**
  ```php
  $userModel = new UserModel($mysqli);
  $users = $userModel->getAllUsers();
  ```
- **Transactions:** Wrap multi-step DB operations in transactions for consistency.

### Pagination
- **Rule:** Always paginate large result sets (100+ rows).
- **Pattern:** Use `BaseModel::paginate($page, $perPage)` or implement `LIMIT` + `OFFSET`.
- **Why:** Prevents memory overload and improves perceived performance.

---

## Architecture

### Naming Conventions
- **URLs:** kebab-case (e.g., `/api/v1/user-feedback`)
- **PHP variables:** camelCase (e.g., `$userFeedback`, `$postTitle`)
- **Database columns:** snake_case (e.g., `user_feedback`, `post_title`)
- **CSS classes:** kebab-case (e.g., `.button-primary`, `.card-header`)
- **JavaScript:** camelCase (e.g., `fetchUserData()`, `isActiveUser`)

### Generated Assets
- **Do NOT edit:** `public_html/assets/**/dist/**` (compiled output folder)
- **DO edit:** Source files in `public_html/assets/` or `src/` (per build config)
- **Build:** Run `npm run build` after changes
- **Why:** Changes to dist/ are overwritten on next build, wasting effort.

### Frontend Output
- **Rule:** All HTML output must come from Twig templates, not echoed from controllers.
- **Bad:** Controller with `echo "<div>$userInput</div>"`
- **Good:** Controller passes data to Twig; Twig renders: `{{ userInput | e }}`
- **Exceptions:** JSON endpoints are OK to `echo json_encode(...)` after proper headers.

### Code Organization
- **Helpers:** Store reusable functions in `app/Helpers/*` — check existing before creating new
- **Models:** Database access in `app/Models/*` (prepared statements only)
- **Controllers:** Request handling + routing to models/views
- **Views:** Twig templates in `app/Views/` (subdirs: `public/`, `admin/`, `auth/`, etc.)
- **Middleware:** Request/response interceptors in `app/Middleware/*`

---

## Error Handling & Logging

### Exception Handling
- **Rule:** Catch exceptions at the controller level.
- **Pattern:**
  ```php
  try {
      $result = $model->save($data);
  } catch (Exception $e) {
      logError('save_user_error', 'Failed to save user', $e);
      return JsonResponse::error('Unable to save user');
  }
  ```
- **Do NOT:** Let exceptions bubble up to HTTP; always return consistent error response.

### Logging
- **Activity Log:** User actions tracked via `logActivity($userId, $action, $meta)`
- **Error Log:** Caught exceptions logged via `logError($context, $message, $exception)`
- **No sensitive data:** Never log passwords, API keys, or full credentials
- **Example:**
  ```php
  logActivity($userId, 'post_created', ['post_id' => $postId, 'title' => $title]);
  ```

### JSON Responses
- **Success:** `{ "success": true, "data": {...} }`
- **Error:** `{ "success": false, "error": "<user-friendly message>" }`
- **Lists:** `{ "data": [...], "meta": { "total": n, "page": p, "perPage": pp } }`
- **Status codes:** Use appropriate HTTP codes (400, 401, 403, 404, 422, 500)

---

## Verification Checklist

Before committing code:
1. **PHP Syntax:** `php -l path/to/file.php`
2. **Code Quality:** `php scripts/quality_scan.php`
3. **Security:** `php scripts/security_scan.php`
4. **JavaScript** (if changed): `npm run lint`
5. **Assets** (if changed): `npm run check:assets`

---

## Reference

- See [AGENTS.md](../AGENTS.md) for repo structure and quick facts
- See [docs/ai/AI_QUICK_CONTEXT.md](../docs/ai/AI_QUICK_CONTEXT.md) for AI-specific guidance
- See [docs/GENERATED_ASSETS_AND_BUILD.md](../docs/GENERATED_ASSETS_AND_BUILD.md) for asset build details
- See [docs/ai/KNOWN_PITFALLS.md](../docs/ai/KNOWN_PITFALLS.md) for common gotchas
