# AI Coding Agent Guide

This guide helps AI coding agents (Copilot, Kilo Code, etc.) work efficiently with the BroxBhai codebase while minimizing unnecessary API calls and rework.

---

## Token-Saver Default Context

Default read order (keep editor token cost low):
1) `AGENTS.md`
2) `docs/ai/AI_QUICK_CONTEXT.md`

Open this guide only when you need deeper implementation detail.

Cleanup:
- After implementing a temporary plan in `docs/plans/` (including audit plans), delete that plan file to avoid stale context. Preserve long-term decisions in `docs/project/project-context.md` or `docs/ai/AGENT_MEMORY.md`.

---

## 🚀 Quick Start

**Tech Stack:**
- Backend: PHP 8.2+ (Custom framework, not Laravel/CodeIgniter)
- Templates: Twig 3.x
- Database: MySQL/MariaDB
- Frontend: Vanilla JS + Tailwind CSS
- Build: Node.js (esbuild, npm)
- External APIs: Firebase, Telegram, AI Providers (Anthropic, Google Gemini, OpenRouter)

**Key Paths:**
```
app/Controllers/   # Route handlers (file-based routing)
app/Models/        # Database operations
app/Helpers/       # Utility functions
app/Views/         # Twig templates (public/, admin/, auth/)
public_html/       # Webroot, static assets
Config/            # Configuration files
scripts/           # CLI scripts, workers
```

---

## 📁 Project Architecture

### Routing Pattern
This project uses **closure-based routing** in individual controller files. Routes are defined at the bottom of each controller file:

```php
// app/Controllers/HomeController.php
$router->get('/', function () use ($twig, $homeModel) {
    // Handler code here
    echo $twig->render('public/home.twig', ['title' => 'Home']);
});

$router->post('/contact', function () use ($mysqli, $twig) {
    // POST handler
});
```

**DO NOT** create new route files. Add routes to existing controller files or create new controller files in `app/Controllers/`.

### Database Access
All models receive `$mysqli` (mysqli connection) via constructor:

```php
// Creating a model instance
$userModel = new UserModel($mysqli);

// Using the model
$users = $userModel->getAllUsers();
```

### Rendering Views
Use the `$twig` global to render templates:

```php
// Public page
echo $twig->render('public/home.twig', [
    'title' => 'Home',
    'contents' => $data
]);

// Admin page
echo $twig->render('admin/users/list.twig', [
    'title' => 'User List',
    'users' => $users
]);

// With form errors
echo $twig->render('public/contact.twig', [
    'title' => 'Contact',
    'errors' => ['Name is required'],
    'old' => $_POST
]);
```

---

## 🔧 Available Helpers

**Before writing new utility code, check these helpers:**

| Helper | Purpose |
|--------|---------|
| [`app/Helpers/EmailHelper.php`](..\..\app\Helpers\EmailHelper.php) | `sendEmail($to, $subject, $body, $name)` |
| [`app/Helpers/FirebaseHelper.php`](..\..\app\Helpers\FirebaseHelper.php) | Push notifications, FCM |
| [`app/Helpers/NotificationHelper.php`](..\..\app\Helpers\NotificationHelper.php) | Multi-channel notifications |
| [`app/Helpers/ErrorLogging.php`](..\..\app\Helpers\ErrorLogging.php) | `logError()`, `logActivity()` |
| [`app/Helpers/PurifierHelper.php`](..\..\app\Helpers\PurifierHelper.php) | HTML sanitization |
| [`app/Helpers/BreadcrumbHelper.php`](..\..\app\Helpers\BreadcrumbHelper.php) | Breadcrumb generation |
| [`app/Helpers/EditorHelper.php`](..\..\app\Helpers\EditorHelper.php) | Content editor utilities |
| [`app/Helpers/AuthAndSecurityHelper.php`](..\..\app\Helpers\AuthAndSecurityHelper.php) | Auth & security functions |

---

## 📋 Common Patterns

### Authentication Check
```php
// For protected routes
if (!AuthManager::isUserAuthenticated()) {
    header('Location: /login');
    exit;
}

$userId = AuthManager::getCurrentUserId();
```

### CSRF Protection
```php
// Validate CSRF token
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    return;
}
```

### AI Assistant Streaming (SSE) Meta
- `POST /api/ai/chat` requires CSRF (`csrf_token` in JSON body or `X-CSRF-Token` header).
- SSE responses may start with a meta event: `{"meta":{"conversation_id":...,"message_id":...}}` before any `{"content":...}` chunks.
- Frontend feedback should use these IDs (not client-side indexes) when calling `POST /api/ai/feedback`.

### JSON API Response
```php
header('Content-Type: application/json');

// Success
http_response_code(201);
echo json_encode([
    'success' => true,
    'data' => $result
]);

// Error
http_response_code(400);
echo json_encode([
    'success' => false,
    'error' => 'Error message'
]);
```

### Form Validation
```php
$errors = [];

if (empty($name))
    $errors[] = "Name is required";
if (!filter_var($email, FILTER_VALIDATE_EMAIL))
    $errors[] = "Valid email required";

if (!empty($errors)) {
    echo $twig->render('page.twig', [
        'errors' => $errors,
        'old' => $_POST
    ]);
    return;
}
```

### Activity Logging
```php
logActivity("User Created", "user", $userId, ['name' => $name], 'success');
logActivity("Login Failed", "auth", 0, ['email' => $email], 'failure');
```

---

## 🎯 Coding Conventions

### File Naming
- Controllers: `PascalCaseController.php` (e.g., `HomeController.php`)
- Models: `PascalCase.php` (e.g., `UserModel.php`)
- Helpers: `PascalCaseHelper.php` (e.g., `EmailHelper.php`)
- Views: `kebab-case.twig` (e.g., `user-profile.twig`)

### Variable Naming
- PHP variables: `$camelCase` (e.g., `$userId`, `$totalCount`)
- Twig variables: `snake_case` or `camelCase`
- Database columns: `snake_case`

### SQL Queries
Use prepared statements via the model layer:
```php
// In models - use $this->db->prepare() or direct mysqli
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
```

### Input Sanitization
```php
// For user input
$name = sanitize_input($_POST['name'] ?? '');

// For HTML content (from WYSIWYG)
$htmlContent = PurifierHelper::purify($rawHtml);
```

---

## ⚠️ Common Pitfalls to Avoid

1. **Don't create new route files** - Add routes to existing controller files
2. **Don't bypass models** - Always use models for database operations
3. **Don't forget CSRF** - Validate tokens on all POST/PUT/DELETE requests
4. **Don't use raw SQL** - Use prepared statements in models
5. **Don't skip error handling** - Use try-catch and log errors
6. **Don't hardcode values** - Use Config files or database settings
7. **Don't forget authentication** - Check AuthManager for protected routes

---

## 📚 Key Models Reference

| Model | Purpose |
|-------|---------|
| [`app/Models/UserModel.php`](..\..\app\Models\UserModel.php) | User CRUD, authentication |
| [`app/Models/ContentModel.php`](..\..\app\Models\ContentModel.php) | Posts, pages, categories |
| [`app/Models/AuthManager.php`](..\..\app\Models\AuthManager.php) | Session, login, permissions |
| [`app/Models/AppSettings.php`](..\..\app\Models\AppSettings.php) | Application settings |
| [`app/Models/NotificationModel.php`](..\..\app\Models\NotificationModel.php) | Notifications |
| [`app/Models/AutoContentModel.php`](app/Models/AutoContentModel.php) | Web scraping, auto-content |
| [`app/Models/AIProvider.php`](..\..\app\Models\AIProvider.php) | AI API integrations |

---

## 🔨 Before Writing Code

1. **Check existing controllers** - Similar functionality may already exist
2. **Check models** - Don't duplicate database logic
3. **Check helpers** - Utility functions may already exist
4. **Check views** - Reuse existing Twig templates and macros
5. **Check Constants.php** - Application-wide constants

---

## 📖 View Template Patterns

### Using Macros
```twig
{% import '_macros/flash.twig' as flash %}
{{ flash.show(flash) }}
```

### Using Dashboard Macros
```twig
{% import '_macros/dashboard_macros.twig' as dashboard %}
{{ dashboard.card(title, value, icon) }}
```

### Admin Layout
Admin views typically extend or use admin layout. Check existing admin views for patterns.

---

## 🛠️ Build Commands

```bash
# Development
npm run dev

# Production build
npm run build

# Run PHP server
php -S localhost:8000 -t public_html
```

---

## 🤖 AI Tool System (v3.0)

The AI assistant uses a centralized tool execution system with OpenAI-compatible schemas.

### Architecture
```
ToolRegistry (app/Helpers/ToolRegistry.php)
├── Tool Registration (OpenAI JSON Schema)
├── Parallel Execution (pcntl_fork fallback to sequential)
├── Streaming Support (SSE events)
├── Circuit Breaker Pattern
├── Retry Logic (exponential backoff)
└── Argument Validation

ToolDefinitions (app/Helpers/ToolDefinitions.php)
├── get_system_health — System diagnostics
├── query_database — Read-only SQL queries
├── get_table_stats — Table statistics
├── analyze_error_logs — Log analysis
├── summarize_text — Extractive summarization
├── get_cache_stats — Cache monitoring
├── get_user_stats — User analytics
├── get_content_stats — Content analytics
├── list_tools — Tool discovery
└── clear_cache — Cache management
```

### API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/admin/ai-tools` | GET | List all tools + circuit breaker status |
| `/api/admin/ai-tools/execute` | POST | Execute single tool |
| `/api/admin/ai-tools/execute-parallel` | POST | Execute multiple tools in parallel |
| `/api/admin/ai-tools/process-streaming` | POST | Process streaming tool calls from AI |
| `/api/admin/ai-tools/reset-circuit-breaker` | POST | Reset circuit breaker |

### Tool Registration Example
```php
ToolRegistry::register('my_tool', function(array $args, ?mysqli $mysqli) {
    // Tool implementation
    return ['result' => 'data'];
}, [
    'name' => 'My Tool',
    'description' => 'Does something useful',
    'namespace' => 'system',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'input' => ['type' => 'string', 'description' => 'Input data']
        ],
        'required' => ['input']
    ],
    'timeout' => 30,
    'max_retries' => 2,
    'retry_delay' => 1,
    'cacheable' => true,
    'cache_ttl' => 300
]);
```

### Parallel Execution
```php
$results = ToolRegistry::executeParallel([
    ['tool' => 'get_system_health', 'args' => [], 'call_id' => 'call_1'],
    ['tool' => 'get_user_stats', 'args' => [], 'call_id' => 'call_2'],
    ['tool' => 'get_content_stats', 'args' => [], 'call_id' => 'call_3'],
], $mysqli, ['stream' => true, 'timeout' => 60]);

// Build messages to send back to AI provider
$messages = ToolRegistry::buildToolResultMessages($results);
```

### Circuit Breaker
Tools automatically open after 5 consecutive failures. Reset via:
```php
ToolRegistry::resetCircuitBreaker('tool_name');
ToolRegistry::resetAllCircuitBreakers();
```

### Error Categories
- `timeout` — Execution exceeded timeout (retryable)
- `network_error` — Connection/network issue (retryable)
- `validation_error` — Invalid arguments (not retryable)
- `auth_error` — Permission denied (not retryable)
- `not_found` — Resource not found (not retryable)
- `circuit_open` — Circuit breaker is open
- `resource_exhausted` — Memory/resource limit (retryable)
- `deployment_scale_timeout` — Deployment didn't scale up in time

---

## 🚀 Fireworks AI Deployment Autoscaling

The `AIProvider` model includes built-in retry logic for Fireworks AI deployments with autoscaling.

### Scale-from-Zero Behavior

When a Fireworks deployment is scaled to zero (idle), the API returns:
```json
{
  "error": {
    "message": "Deployment is currently scaled to zero and is scaling up...",
    "code": "DEPLOYMENT_SCALING_UP",
    "type": "error"
  }
}
```

### Automatic Retry with Exponential Backoff

The system automatically retries with exponential backoff:

| Parameter | Default | Description |
|-----------|---------|-------------|
| `max_retries` | 30 | Maximum retry attempts |
| `initial_delay` | 5s | Initial wait between retries |
| `max_delay` | 60s | Maximum wait cap |
| `backoff_multiplier` | 1.5 | Delay multiplier per retry |

### Configuration Constants (in AIProvider.php)
```php
private const AUTOSCALING_CONFIG = [
    'max_retries' => 30,
    'initial_delay_seconds' => 5,
    'max_delay_seconds' => 60,
    'backoff_multiplier' => 1.5,
    'retry_on_status_codes' => [503],
    'retry_error_codes' => ['DEPLOYMENT_SCALING_UP'],
];
```

### Deployment Recommendations

| Pattern | Config | Best For |
|---------|--------|----------|
| **Cost optimization** | `--min-replica-count 0 --scale-to-zero-window 1h` | Dev, testing, intermittent traffic |
| **Performance-focused** | `--min-replica-count 2 --scale-up-window 15s` | Low latency, high traffic |
| **Predictable traffic** | `--min-replica-count 3 --scale-down-window 30m` | Steady workloads |

### Load Target Options
- `default=0.8` — General load target (0-1)
- `tokens_generated_per_second=150` — Tokens/sec per replica
- `concurrent_requests=5` — Concurrent requests per replica

<Tip>
For instant responses without cold starts, set `--min-replica-count 1` or higher. Deployments with min replicas = 0 are auto-deleted after 7 days of no traffic.
</Tip>

---

## 📞 Need Help?

- Check [`README.md`](..\..\README.md) for setup instructions
- Check existing code in `app/Controllers/` for patterns
- Check `app/Views/` for template examples
