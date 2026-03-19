# CV Builder Controller Structure

## File Location
`app/Controllers/CvController.php`

## Dependencies
The controller will use:
- `$router` - Global router instance
- `$twig` - Twig template engine
- `$mysqli` - Database connection
- Existing models: `UserModel`, `AuthManager`
- New models to be created: `CvModel`, `CvSectionModel`, `CvItemModel`, `CvShareModel`

## Route Registration
All routes are prefixed with `/cv` and use the `auth` middleware unless specified otherwise.

### CV CRUD
```php
// List all CVs for the authenticated user
$router->get('/cv', ['middleware' => ['auth']], function () use ($twig, $mysqli) {
    // Render user CV dashboard
});

// Create a new CV
$router->post('/cv', ['middleware' => ['auth', 'csrf']], function () use ($mysqli) {
    // Handle CV creation
});

// Get a specific CV (edit page)
$router->get('/cv/{id}', ['middleware' => ['auth']], function ($id) use ($twig, $mysqli) {
    // Render CV editor with preview
});

// Update a specific CV
$router->put('/cv/{id}', ['middleware' => ['auth', 'csrf']], function ($id) use ($mysqli) {
    // Handle CV update
});

// Delete a specific CV
$router->delete('/cv/{id}', ['middleware' => ['auth', 'csrf']], function ($id) use ($mysqli) {
    // Handle CV deletion
});
```

### Section Management
```php
// Add a new section
$router->post('/cv/{cv_id}/sections', ['middleware' => ['auth', 'csrf']], function ($cv_id) use ($mysqli) {
    // Add section
});

// Update section (title, visibility, order)
$router->put('/cv/{cv_id}/sections/{section_id}', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id) use ($mysqli) {
    // Update section
});

// Delete a section
$router->delete('/cv/{cv_id}/sections/{section_id}', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id) use ($mysqli) {
    // Delete section
});

// Reorder sections
$router->patch('/cv/{cv_id}/sections/reorder', ['middleware' => ['auth', 'csrf']], function ($cv_id) use ($mysqli) {
    // Reorder sections (drag & drop)
});
```

### Item Management (within sections)
```php
// Add item to a section
$router->post('/cv/{cv_id}/sections/{section_id}/items', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id) use ($mysqli) {
    // Add item
});

// Update item
$router->put('/cv/{cv_id}/sections/{section_id}/items/{item_id}', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id, $item_id) use ($mysqli) {
    // Update item
});

// Delete item
$router->delete('/cv/{cv_id}/sections/{section_id}/items/{item_id}', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id, $item_id) use ($mysqli) {
    // Delete item
});

// Reorder items
$router->patch('/cv/{cv_id}/sections/{section_id}/items/reorder', ['middleware' => ['auth', 'csrf']], function ($cv_id, $section_id) use ($mysqli) {
    // Reorder items (drag & drop)
});
```

### Preview and Export
```php
// Real-time preview (debounced)
$router->get('/cv/{id}/preview', ['middleware' => ['auth']], function ($id) use ($twig, $mysqli) {
    // Render preview HTML (or partial)
});

// Generate PDF
$router->get('/cv/{id}/export', ['middleware' => ['auth']], function ($id) use ($twig, $mysqli) {
    // Generate and download PDF
});

// PDF Preview (inline)
$router->get('/cv/{id}/export/preview', ['middleware' => ['auth']], function ($id) use ($twig, $mysqli) {
    // Generate and preview PDF inline
});
```

### Shareable CV
```php
// Generate share token
$router->post('/cv/{id}/share', ['middleware' => ['auth', 'csrf']], function ($id) use ($mysqli) {
    // Generate share token
});

// Public view (no auth)
$router->get('/cv/view/{token}', function ($token) use ($twig, $mysqli) {
    // Render public read-only CV view
});

// Revoke share
$router->delete('/cv/{id}/share', ['middleware' => ['auth', 'csrf']], function ($id) use ($mysqli) {
    // Revoke share token
});
```

### AI Assistance (Proxy)
```php
// Improve text
$router->post('/cv/{id}/ai/improve', ['middleware' => ['auth', 'csrf']], function ($id) use ($mysqli) {
    // Proxy to Node.js AI service
});

// ATS Score
$router->post('/cv/{id}/ai/ats-score', ['middleware' => ['auth', 'csrf']], function ($id) use ($mysqli) {
    // Proxy to Node.js AI service
});

// Keyword Extraction
$router->post('/cv/{id}/ai/keyword-extract', ['middleware' => ['auth', 'csrf']], function ($id) use ($mysqli) {
    // Proxy to Node.js AI service
});
```

### Import
```php
// Import CV from PDF/DOCX
$router->post('/cv/import', ['middleware' => ['auth', 'csrf']], function () use ($mysqli) {
    // Handle file upload and proxy to Node.js for parsing
});
```

## Key Functions

### Authentication
- Use `AuthManager` to check if user is logged in.
- Use `validateCsrfToken()` for state-changing routes.

### Authorization
- For each CV operation, verify that the CV belongs to the current user:
  ```php
  $cvModel = new CvModel($mysqli);
  $cv = $cvModel->getById($id);
  if (!$cv || $cv['user_id'] !== $_SESSION['user_id']) {
      http_response_code(403);
      echo json_encode(['error' => 'Forbidden']);
      exit;
  }
  ```

### Auto-save
- For the preview and edit endpoints, implement auto-save logic (e.g., save every 3-5 seconds if there are changes).

### File Uploads
- For import, validate file type (PDF/DOCX) and size (max 5MB).
- Use `PurifierHelper::purify()` to sanitize any user input.

## Notes
- Use JSON for API responses.
- Use Twig templates for page rendering.
- Use `logActivity()` and `logError()` for logging.
- Use `showMessage()` for flash messages.
- Use `redirectWithMessage()` for redirects with flash messages.