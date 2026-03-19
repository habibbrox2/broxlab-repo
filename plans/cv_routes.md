# CV Builder PHP Routes

All routes are prefixed with `/cv` and require authentication (auth middleware).

## Route Groups

### CV Management (CRUD)
- `GET /cv` - List all CVs for the current user
- `POST /cv` - Create a new CV
- `GET /cv/:id` - Get a specific CV by ID
- `PUT /cv/:id` - Update a specific CV
- `DELETE /cv/:id` - Delete a specific CV

### CV Sections
- `GET /cv/:cv_id/sections` - List sections for a CV
- `POST /cv/:cv_id/sections` - Add a new section to a CV
- `PUT /cv/:cv_id/sections/:section_id` - Update a section
- `DELETE /cv/:cv_id/sections/:section_id` - Delete a section
- `PATCH /cv/:cv_id/sections/reorder` - Reorder sections (drag & drop)

### CV Items (within a section)
- `GET /cv/:cv_id/sections/:section_id/items` - List items in a section
- `POST /cv/:cv_id/sections/:section_id/items` - Add a new item to a section
- `PUT /cv/:cv_id/sections/:section_id/items/:item_id` - Update an item
- `DELETE /cv/:cv_id/sections/:section_id/items/:item_id` - Delete an item
- `PATCH /cv/:cv_id/sections/:section_id/items/reorder` - Reorder items within a section

### Real-time Preview
- `GET /cv/:id/preview` - Get the preview HTML for a CV (uses a template)
- `POST /cv/:id/preview` - Generate preview with updated data (debounced calls from frontend)

### PDF Export
- `GET /cv/:id/export` - Generate and download PDF for a CV
- `GET /cv/:id/export/preview` - Get PDF as inline preview (for browser display)

### Shareable CV
- `POST /cv/:id/share` - Generate a shareable token for a CV
- `GET /cv/view/:token` - Public read-only view of a CV (no auth required)
- `DELETE /cv/:id/share` - Remove/share token (invalidate)

### AI Assistance (Proxy to Node.js microservice)
- `POST /cv/:id/ai/improve` - Improve text in a specific item (proxy to Node.js)
- `POST /cv/:id/ai/ats-score` - Get ATS score for the entire CV (proxy to Node.js)
- `POST /cv/:id/ai/keyword-extract` - Extract keywords from job description (proxy to Node.js)

### CV Import
- `POST /cv/import` - Upload and parse PDF/DOCX to create a new CV (proxy to Node.js for parsing)

## Middleware
- All routes under `/cv` (except public share view) require `auth` middleware.
- State-changing routes (POST, PUT, PATCH, DELETE) require `csrf` middleware.
- The public share view route (`/cv/view/:token`) is guest accessible.

## Example Route Definitions (in a controller)

In `app/Controllers/CvController.php`:

```php
$router->get('/cv', ['middleware' => ['auth']], function() use ($twig, $mysqli) {
    // List CVs
});

$router->post('/cv', ['middleware' => ['auth', 'csrf']], function() use ($twig, $mysqli) {
    // Create CV
});

// ... and so on for other routes
```

## Notes
- The `:id`, `:cv_id`, `:section_id`, `:item_id`, and `:token` are route parameters.
- We use JSON for request bodies in POST/PUT/PATCH requests (except file uploads).
- Responses are JSON for API routes, except for preview and export which return HTML/PDF.
- The real-time preview endpoint should be debounced on the frontend to avoid excessive calls.