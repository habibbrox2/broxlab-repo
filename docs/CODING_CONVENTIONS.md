# Coding Conventions - BroxBhai

This document outlines the coding standards and patterns used in the BroxBhai project. Following these conventions ensures consistency and helps AI coding agents write code that matches the existing codebase.

---

## 📁 File Naming

### PHP Files
| Type | Convention | Example |
|------|------------|---------|
| Controllers | `PascalCaseController.php` | `HomeController.php` |
| Models | `PascalCase.php` | `UserModel.php`, `AuthManager.php` |
| Helpers | `PascalCaseHelper.php` | `EmailHelper.php` |
| Middleware | `PascalCaseMiddleware.php` | `AuthMiddleware.php` |

### View Templates (Twig)
| Location | Convention | Example |
|----------|------------|---------|
| Public pages | `kebab-case.twig` | `home.twig`, `about-us.twig` |
| Admin pages | `kebab-case.twig` | `user-list.twig`, `post-edit.twig` |
| Macros | `snake_case.twig` | `flash.twig`, `dashboard_macros.twig` |

### Directories
- Use **kebab-case** for directory names: `app/Views/public/`, `public_html/assets/js/`

---

## 🐘 PHP Coding Standards

### Naming Conventions

```php
// Classes - PascalCase
class UserModel { }

// Functions - camelCase
function sanitizeInput($data) { }

// Variables - camelCase
$userId = 1;
$totalCount = 0;
$isActive = true;

// Constants - UPPER_SNAKE_CASE
define('MAX_UPLOAD_SIZE', 10485760);

// Private methods/properties - camelCase with underscore prefix
private function _processData($input) { }
private $_cache = [];
```

### Code Structure

#### Controllers
```php
<?php
// controllers/ExampleController.php

// Initialize models at top (required dependencies)
$exampleModel = new ExampleModel($mysqli);
$settingsModel = new AppSettings($mysqli);

// Route definitions
$router->get('/example', function () use ($twig, $exampleModel) {
    $data = $exampleModel->getData();
    echo $twig->render('public/example.twig', [
        'title' => 'Example',
        'data' => $data
    ]);
});

$router->post('/example', function () use ($mysqli, $twig) {
    // POST handler code
});
```

#### Models
```php
<?php
// models/ExampleModel.php

class ExampleModel {
    private $db;
    
    public function __construct($mysqli) {
        $this->db = $mysqli;
    }
    
    public function getData() {
        $stmt = $this->db->prepare("SELECT * FROM examples WHERE active = 1");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
```

### SQL Queries
Always use **prepared statements**:
```php
// ✅ Correct - Prepared statement
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ? AND status = ?");
$stmt->bind_param("is", $userId, $status);
$stmt->execute();

// ❌ Wrong - String concatenation
$result = $mysqli->query("SELECT * FROM users WHERE id = " . $userId);
```

### Input Handling
```php
// Sanitize text input
$name = sanitize_input($_POST['name'] ?? '');

// Sanitize HTML content (from WYSIWYG editors)
$htmlContent = PurifierHelper::purify($_POST['content'] ?? '');

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Valid email required";
}

// Cast integers
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
```

### Error Handling
```php
// Try-catch for operations that may fail
try {
    $uploadService = new UploadService($mysqli, $userId);
    $result = $uploadService->upload($file, 'avatar');
} catch (Throwable $e) {
    logError('Upload failed: ' . $e->getMessage(), 'UPLOAD_ERROR', ['user_id' => $userId]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    return;
}

// Log activities
logActivity("User Created", "user", $userId, ['name' => $name], 'success');
```

### Authentication Checks
```php
// Protect routes
if (!AuthManager::isUserAuthenticated()) {
    header('Location: /login');
    exit;
}

// Get current user
$userId = AuthManager::getCurrentUserId();
$currentUser = AuthManager::getCurrentUser();
```

### CSRF Protection
```php
// Validate CSRF token on POST requests
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    return;
}
```

### JSON API Responses
```php
header('Content-Type: application/json');

// Success response (201 Created)
http_response_code(201);
echo json_encode([
    'success' => true,
    'data' => $result,
    'message' => 'Created successfully'
]);

// Error response (400 Bad Request)
http_response_code(400);
echo json_encode([
    'success' => false,
    'error' => 'Validation failed',
    'details' => $errors
]);

// Not found (404)
http_response_code(404);
echo json_encode([
    'success' => false,
    'error' => 'Resource not found'
]);
```

---

## 🎨 Twig Template Conventions

### Variable Naming in Templates
```twig
{# Use snake_case for variables passed from controllers #}
{{ user_name }}
{{ post_title }}
{{ created_at }}
{{ is_active }}
```

### Control Structures
```twig
{# If statement #}
{% if user.is_admin %}
    <p>Admin</p>
{% elseif user.is_editor %}
    <p>Editor</p>
{% else %}
    <p>User</p>
{% endif %}

{# Loop #}
{% for post in posts %}
    <h2>{{ post.title }}</h2>
{% endfor %}

{# Macro import #}
{% import '_macros/flash.twig' as flash %}
{{ flash.show(flash) }}
```

### HTML Attributes
```twig
{# Classes #}
<div class="container-fluid">

{# IDs #}
<div id="main-content">

{# Data attributes #}
<button data-id="{{ user.id }}" data-action="delete">

{# URL generation #}
<a href="/posts/{{ post.slug }}">Read more</a>
```

---

## 🎯 Best Practices

### DO
- Use models for all database operations
- Validate all user input
- Use prepared statements for SQL
- Log important actions with `logActivity()`
- Use helper functions instead of duplicating code
- Check if helper/model exists before creating new ones
- Follow existing file organization patterns

### DON'T
- Write raw SQL in controllers
- Skip CSRF validation on forms
- Hardcode configuration values
- Duplicate existing functionality
- Skip error handling
- Create new routing files (add to existing controllers)
- Mix PHP logic in views (keep views clean)

---

## 🔍 Code Review Checklist

Before submitting code, verify:

- [ ] All inputs are validated
- [ ] SQL uses prepared statements
- [ ] CSRF protection on state-changing routes
- [ ] Authentication check on protected routes
- [ ] Errors are logged with `logError()`
- [ ] Activities are logged with `logActivity()`
- [ ] No hardcoded config values
- [ ] Uses existing helpers/models when available
- [ ] Follows naming conventions
- [ ] Twig templates don't contain complex PHP logic

---

## 📚 Reference Examples

See these files for real-world examples:

- **Controller Pattern**: [`app/Controllers/HomeController.php`](app/Controllers/HomeController.php)
- **Model Pattern**: [`app/Models/UserModel.php`](app/Models/UserModel.php)
- **Helper Pattern**: [`app/Helpers/EmailHelper.php`](app/Helpers/EmailHelper.php)
- **AI Tool Pattern**: [`app/Helpers/ToolRegistry.php`](app/Helpers/ToolRegistry.php) (v3.0: parallel, streaming, circuit breaker)
- **AI Tool Definitions**: [`app/Helpers/ToolDefinitions.php`](app/Helpers/ToolDefinitions.php) (10 registered tools)
- **AI Provider Pattern**: [`app/Models/AIProvider.php`](app/Models/AIProvider.php) (multi-provider with autoscaling retry)
- **Form Handling**: [`app/Controllers/HomeController.php`](app/Controllers/HomeController.php) (contact form)
- **JSON API**: [`app/Controllers/HomeController.php`](app/Controllers/HomeController.php) (upload endpoint)

---

## 🤖 AI Tool System Patterns

### Registering a New Tool
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

### Executing Tools
```php
// Single tool
$result = ToolRegistry::execute('get_system_health', [], $mysqli, ['stream' => true]);

// Parallel execution
$results = ToolRegistry::executeParallel([
    ['tool' => 'get_system_health', 'args' => [], 'call_id' => 'call_1'],
    ['tool' => 'get_user_stats', 'args' => [], 'call_id' => 'call_2'],
], $mysqli, ['stream' => true, 'timeout' => 60]);

// Build messages for AI provider
$messages = ToolRegistry::buildToolResultMessages($results);
```

### Error Handling
```php
$result = ToolRegistry::execute('my_tool', $args, $mysqli);

if (!$result['success']) {
    switch ($result['error_code']) {
        case 'circuit_open':
            // Tool temporarily unavailable
            break;
        case 'timeout':
            // Tool exceeded timeout
            break;
        case 'invalid_arguments':
            // Bad arguments
            break;
        default:
            // Other error
    }
}
```

---

## 🔧 Tools & Configuration

### ESLint
The project uses ESLint for JavaScript. Run:
```bash
npm run lint
```

### Naming Conventions Checker
```bash
npm run naming:check
```

### Asset Budget Checker
```bash
npm run check:dist:budget
```
