---
name: backend-development-workflow
description: Workflow for implementing backend features in BroxLab PHP controllers, models, and services
license: See repo LICENSE
---

# BroxLab Backend Development Workflow

Use this skill for backend tasks: adding API endpoints, models, services, middleware, or database queries.

## 1. Understand the Task

- **Scope**: Is it a new endpoint, model, service, or modification to existing code?
- **User Flow**: How will the frontend call this? REST endpoint or server-side form?
- **Database**: Does it need new tables, columns, or modify existing queries?
- **Permissions**: Who can access this? Anonymous, authenticated, admin-only?
- **Validation**: What input rules apply? Required fields, email format, etc.

Review [AGENTS.md](../../AGENTS.md) for patterns and [copilot-instructions.md](../../copilot-instructions.md) for architecture.

## 2. Create or Update the Model

**File Location:** `app/Models/FeatureNameModel.php` or extend existing model

**Pattern:**
```php
class FeatureNameModel {
    private $mysqli;
    
    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }
    
    // Query method: Always prepared statement, explicit columns, soft delete filter
    public function getActiveRecords($userId) {
        $stmt = $this->mysqli->prepare(
            "SELECT id, user_id, name, description, created_at, updated_at 
             FROM feature_table 
             WHERE user_id = ? AND deleted_at IS NULL"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Create method
    public function create($data) {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO feature_table (user_id, name, description, created_at) 
             VALUES (?, ?, ?, NOW())"
        );
        $stmt->bind_param('iss', $data['user_id'], $data['name'], $data['description']);
        return $stmt->execute();
    }
    
    // Update method
    public function update($id, $data) {
        $stmt = $this->mysqli->prepare(
            "UPDATE feature_table 
             SET name = ?, description = ?, updated_at = NOW() 
             WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->bind_param('ssi', $data['name'], $data['description'], $id);
        return $stmt->execute();
    }
    
    // Soft delete method
    public function delete($id) {
        $stmt = $this->mysqli->prepare(
            "UPDATE feature_table SET deleted_at = NOW() WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }
}
```

**Rules:**
- ✅ Prepared statements **always**
- ✅ Explicit column lists (never `SELECT *`)
- ✅ Soft deletes: `WHERE ... AND deleted_at IS NULL`
- ✅ Bind parameters with types: `'i'` (int), `'s'` (string), `'d'` (double)
- ✅ Index frequently queried columns: `status`, `created_at`, foreign keys

## 3. Create or Update the Service (Optional)

**File Location:** `app/Services/FeatureNameService.php`

**When to use:**
- Complex business logic (multiple models, calculations, external APIs)
- Reused across multiple controllers
- Transaction-like operations (atomic multi-step processes)

**Pattern:**
```php
class FeatureNameService {
    private $model;
    private $helperClass;
    
    public function __construct($model, $helperClass) {
        $this->model = $model;
        $this->helperClass = $helperClass;
    }
    
    // Business logic method
    public function processFeature($userId, $inputData) {
        // Validate
        if (!$this->validateInput($inputData)) {
            throw new \Exception("Invalid input");
        }
        
        // Transform
        $cleanData = [
            'user_id' => $userId,
            'name' => $this->helperClass->sanitize($inputData['name']),
            'description' => $this->helperClass->sanitizeHtml($inputData['description']),
        ];
        
        // Create in database
        if (!$this->model->create($cleanData)) {
            throw new \Exception("Database error");
        }
        
        return $cleanData;
    }
    
    private function validateInput($data) {
        return !empty($data['name']) && strlen($data['name']) <= 255;
    }
}
```

## 4. Create or Update the Controller

**File Location:** `app/Controllers/FeatureNameController.php`

**Pattern (with routes embedded):**
```php
use function App\Router as router;

// Get helper instances from globals
global $router, $twig, $models, $mysqli, $logger;

// GET route: Display form or list
$router->get('/features', ['middleware' => ['auth']], function() use ($twig, $models) {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        echo $twig->render('error/unauthorized.twig');
        return;
    }
    
    $features = $models['FeatureName']->getActiveRecords($userId);
    echo $twig->render('features/list.twig', ['features' => $features]);
});

// GET route: Return JSON API
$router->get('/api/features', ['middleware' => ['auth']], function() use ($models) {
    $userId = $_SESSION['user_id'] ?? null;
    $features = $models['FeatureName']->getActiveRecords($userId);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $features]);
});

// POST route: Create with form submission
$router->post('/features', ['middleware' => ['auth', 'csrf']], function() use ($twig, $models, $logger) {
    $userId = $_SESSION['user_id'] ?? null;
    $input = [
        'user_id' => $userId,
        'name' => sanitize_input($_POST['name'] ?? ''),
        'description' => sanitize_input($_POST['description'] ?? ''),
    ];
    
    if ($models['FeatureName']->create($input)) {
        $_SESSION['message'] = 'Feature created!';
        header('Location: /features');
        exit;
    } else {
        $logger->error('Feature creation failed', ['user_id' => $userId]);
        http_response_code(500);
        echo $twig->render('error/server-error.twig');
    }
});

// PUT route: Update (JSON API)
$router->put('/api/features/:id', ['middleware' => ['auth', 'csrf']], function($id) use ($models) {
    $userId = $_SESSION['user_id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $data = [
        'name' => $input['name'] ?? '',
        'description' => $input['description'] ?? '',
    ];
    
    if ($models['FeatureName']->update($id, $data)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Update failed']);
    }
});

// DELETE route: Soft delete
$router->delete('/api/features/:id', ['middleware' => ['auth', 'csrf']], function($id) use ($models) {
    if ($models['FeatureName']->delete($id)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Delete failed']);
    }
});
```

**Route Patterns:**
- `$router->get('/path', ['middleware' => [...]], function() { ... })`
- `$router->post('/path', ['middleware' => ['auth', 'csrf']], function() { ... })`
- `$router->put('/api/path/:id', ['middleware' => ['auth', 'csrf']], function($id) { ... })`
- `$router->delete('/api/path/:id', ['middleware' => ['auth', 'csrf']], function($id) { ... })`

**Middleware (always use CSRF on mutating actions):**
- `auth` - Require logged-in user
- `guest_only` - Require NOT logged in (login/register pages)
- `admin` - Require admin role
- `csrf` - Validate CSRF token (POST/PUT/DELETE only)

**Global Imports in Controller:**
```php
// $models['TableName'] → Instantiated model
// $twig → Twig template renderer
// $mysqli → Database connection
// $logger → Logger instance
// $router → Router for defining routes
```

## 5. Create or Update the View (Twig)

**File Location:** `app/Views/features/` (organized by area)

**Pattern:**
```twig
{% extends 'layout/main.twig' %}

{% block content %}
<h1>Features</h1>

{% if features %}
    <ul>
    {% for feature in features %}
        <li>
            {{ feature.name }}
            <p>{{ feature.description }}</p>
            <small>Created: {{ feature.created_at|date('Y-m-d') }}</small>
        </li>
    {% endfor %}
    </ul>
{% else %}
    <p>No features found.</p>
{% endif %}

<!-- Asset versioning for CSS/JS -->
<link rel="stylesheet" href="{{ withAssetVersion('/assets/css/dist/features.css') }}">
<script src="{{ withAssetVersion('/assets/js/dist/features.js') }}"></script>
{% endblock %}
```

**Rules:**
- Use `{{ withAssetVersion('/path/to/file') }}` for CSS/JS links
- Escape user output: `{{ feature.name }}` (auto-escaped by default)
- Use filters for formatting: `{{ date|date('Y-m-d') }}`

## 6. Add Database Schema (if needed)

**File Location:** `Database/feature_table.sql`

**Pattern:**
```sql
CREATE TABLE IF NOT EXISTS feature_table (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    KEY user_id_idx (user_id),
    KEY status_idx (status),
    KEY created_at_idx (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Rules:**
- Add soft delete column: `deleted_at TIMESTAMP NULL`
- Add timestamps: `created_at` and `updated_at`
- Index frequently queried columns (status, created_at, foreign keys)
- Use `FOREIGN KEY` constraints
- Use UTF-8: `utf8mb4` charset for emoji support

## 7. Validate Your Code

```bash
# PHP syntax check
php -l app/Controllers/FeatureNameController.php

# Type-check TypeScript (if using Node services)
npm run type-check

# Full validation gate (required before commit)
npm run validate
```

## 8. Test Locally

```bash
# Start app
php -S localhost:8000 -t public_html

# Start Node service (if needed)
npm run ai-assistant

# Test endpoint
curl -X GET http://localhost:8000/api/features

# Run test suite
npm run test:run
```

## Decision Checklist

- [ ] Model created/updated with prepared statements and explicit columns
- [ ] All queries filter soft deletes: `WHERE ... AND deleted_at IS NULL`
- [ ] Service created (if business logic is complex)
- [ ] Controller routes defined with correct middleware
- [ ] Twig view uses `{{ withAssetVersion() }}` for assets
- [ ] Database schema SQL file added (with indexes and soft deletes)
- [ ] CSRF middleware applied to POST/PUT/DELETE routes
- [ ] Input validation and sanitization implemented
- [ ] Error handling in place (logging and user-friendly errors)
- [ ] Ran `npm run validate` (linting, type-check, tests pass)
- [ ] Tested locally with curl, browser, or Postman
