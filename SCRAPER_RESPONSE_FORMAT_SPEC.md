# BroxLab Web Scraping System - Response Format Specification

**Document Version**: 1.0.0  
**Date**: April 21, 2026  
**Status**: FINAL

---

## 📋 STANDARDIZED RESPONSE FORMATS

### 1. SUCCESS RESPONSE (JSON)

#### Standard Format
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {
    // Optional: Additional data specific to endpoint
  },
  "timestamp": "2026-04-21T10:30:45Z"
}
```

#### List Response with Pagination
```json
{
  "success": true,
  "data": [
    {/* item 1 */},
    {/* item 2 */}
  ],
  "pagination": {
    "total": 100,
    "page": 1,
    "limit": 20,
    "pages": 5
  },
  "timestamp": "2026-04-21T10:30:45Z"
}
```

---

### 2. ERROR RESPONSE (JSON)

#### Standard Error Format
```json
{
  "success": false,
  "error": "Error message describing the issue",
  "error_code": "ERROR_CODE",
  "details": {
    // Optional: Additional error details
  },
  "timestamp": "2026-04-21T10:30:45Z"
}
```

#### Validation Error Format
```json
{
  "success": false,
  "error": "Validation failed",
  "error_code": "VALIDATION_ERROR",
  "details": {
    "errors": {
      "field_name": ["Error message 1", "Error message 2"]
    }
  },
  "timestamp": "2026-04-21T10:30:45Z"
}
```

---

### 3. HTTP STATUS CODES

| Status | Usage | Response |
|--------|-------|----------|
| 200 | Success | `{"success": true, ...}` |
| 201 | Created | `{"success": true, "message": "Created"}` |
| 400 | Bad Request | `{"success": false, "error": "Validation error"}` |
| 401 | Unauthorized | `{"success": false, "error": "Authentication required"}` |
| 403 | Forbidden | `{"success": false, "error": "Access denied"}` |
| 404 | Not Found | `{"success": false, "error": "Resource not found"}` |
| 409 | Conflict | `{"success": false, "error": "Conflict detected"}` |
| 422 | Unprocessable | `{"success": false, "error": "Validation failed"}` |
| 429 | Too Many Requests | `{"success": false, "error": "Rate limited"}` |
| 500 | Server Error | `{"success": false, "error": "Internal server error"}` |

---

### 4. PAGINATION STANDARDS

All list endpoints should return:
```json
{
  "success": true,
  "data": [...],
  "pagination": {
    "total": 0,          // Total number of items
    "page": 1,           // Current page (1-indexed)
    "limit": 20,         // Items per page
    "pages": 0,          // Total number of pages
    "has_more": false    // Whether more pages exist
  }
}
```

---

### 5. DATA OBJECT STANDARDS

#### Article/Content Object
```json
{
  "id": 1,
  "source_id": 1,
  "title": "Article Title",
  "content": "Full article content",
  "excerpt": "Short excerpt",
  "author": "Author name",
  "image_url": "https://example.com/image.jpg",
  "url": "https://example.com/article",
  "status": "completed",
  "content_type": "article",
  "created_at": "2026-04-21T10:00:00Z",
  "updated_at": "2026-04-21T10:30:00Z",
  "metadata": {
    "category": "Technology",
    "tags": ["web", "scraping"],
    "source_name": "Example News",
    "confidence": 0.95
  }
}
```

#### Source Object
```json
{
  "id": 1,
  "name": "Example Source",
  "url": "https://example.com",
  "type": "static",
  "category_id": 1,
  "is_active": true,
  "content_type": "articles",
  "selectors": {...},
  "configuration": {...},
  "statistics": {
    "total_items": 150,
    "items_today": 5,
    "last_run": "2026-04-21T09:00:00Z",
    "success_rate": 0.95
  }
}
```

#### Queue Item Object
```json
{
  "id": 1,
  "source_id": 1,
  "job_type": "collect",
  "status": "pending",
  "priority": 5,
  "created_at": "2026-04-21T10:00:00Z",
  "started_at": null,
  "completed_at": null,
  "items_found": 0,
  "items_saved": 0,
  "error_message": null
}
```

#### Error Object
```json
{
  "type": "network",
  "severity": "medium",
  "message": "Connection timeout",
  "timestamp": "2026-04-21T10:30:45Z",
  "context": {
    "url": "https://example.com",
    "retry_count": 3
  }
}
```

---

### 6. RESPONSE HEADERS

All JSON responses should include:
```
Content-Type: application/json; charset=utf-8
X-API-Version: 1.0
X-Request-ID: <unique-request-id>
Cache-Control: no-cache
```

---

### 7. ERROR CODES

| Code | HTTP Status | Meaning |
|------|-------------|---------|
| `INVALID_REQUEST` | 400 | Invalid request parameters |
| `UNAUTHORIZED` | 401 | Authentication required |
| `FORBIDDEN` | 403 | Access denied |
| `NOT_FOUND` | 404 | Resource not found |
| `CONFLICT` | 409 | Resource conflict |
| `VALIDATION_ERROR` | 422 | Validation failed |
| `RATE_LIMITED` | 429 | Too many requests |
| `SERVER_ERROR` | 500 | Internal server error |
| `SERVICE_UNAVAILABLE` | 503 | Service unavailable |
| `TIMEOUT` | 504 | Request timeout |

---

## 🔄 ENDPOINT RESPONSE EXAMPLES

### GET /api/v1/scraper/sources
**Status**: 200 OK
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "BDNews24",
      "url": "https://bdnews24.com",
      "type": "static",
      "is_active": true,
      "statistics": {"total_items": 150}
    }
  ],
  "pagination": {
    "total": 15,
    "page": 1,
    "limit": 20,
    "pages": 1
  }
}
```

### POST /api/v1/scraper/sources
**Status**: 201 Created
```json
{
  "success": true,
  "message": "Source created successfully",
  "data": {
    "id": 16,
    "name": "New Source",
    "url": "https://newsource.com"
  }
}
```

### GET /api/v1/scraper/sources/{id}
**Status**: 200 OK
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "BDNews24",
    "configuration": {...}
  }
}
```

### GET /api/v1/scraper/queue
**Status**: 200 OK
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "source_id": 1,
      "status": "pending",
      "items_found": 0
    }
  ],
  "pagination": {
    "total": 42,
    "page": 1,
    "limit": 20,
    "pages": 3
  }
}
```

### DELETE /api/v1/scraper/articles/{id}
**Status**: 200 OK
```json
{
  "success": true,
  "message": "Article deleted successfully"
}
```

### Error Response
**Status**: 400 Bad Request
```json
{
  "success": false,
  "error": "Validation failed",
  "error_code": "VALIDATION_ERROR",
  "details": {
    "errors": {
      "url": ["Invalid URL format"],
      "name": ["Name is required"]
    }
  }
}
```

---

## ✅ RESPONSE FORMAT CHECKLIST

For every route, ensure:

- [ ] HTTP status code is correct
- [ ] JSON response includes `success` boolean
- [ ] Error responses include `error` message
- [ ] List responses include `pagination` object
- [ ] All timestamps are ISO 8601 format
- [ ] All dates are in UTC timezone
- [ ] Consistent field naming (snake_case)
- [ ] Null values are explicit (not omitted)
- [ ] Large objects are paginated
- [ ] Error messages are user-friendly
- [ ] Error codes are standardized
- [ ] Response headers are set correctly

---

## 📝 IMPLEMENTATION GUIDE

### Using jsonResponse() Helper

```php
// Success
return jsonResponse([
  'message' => 'Success',
  'data' => $data
], 200);

// Error
return jsonResponse([
  'error' => 'Error message',
  'error_code' => 'ERROR_CODE'
], 400);
```

### Using paginatedResponse() Helper

```php
return paginatedResponse(
  $items,           // array of items
  $total,           // total count
  $page,            // current page
  $limit,           // items per page
  true              // success
);
```

---

## 🔔 MIGRATION GUIDE

### Old Format → New Format

**Before**:
```php
return ['success' => true, 'data' => []];
```

**After**:
```php
return jsonResponse([
  'message' => 'Success',
  'data' => []
], 200);
```

---

**Document Status**: ✅ COMPLETE  
**Next Action**: Implement all missing routes and fix response formats
