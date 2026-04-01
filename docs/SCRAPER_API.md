# Scraper System API Documentation

Complete API reference for the BroxBhai web scraping system.

---

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Dashboard Endpoints](#dashboard-endpoints)
4. [Source Management](#source-management)
5. [Collected Data Summary](#collected-data-summary)
6. [Logs Summary](#logs-summary)
7. [Categories](#categories)
8. [Settings](#settings)
9. [AI Integration](#ai-integration)
10. [Queue Management](#queue-management)
11. [Testing Endpoints](#testing-endpoints)
12. [Error Responses](#error-responses)

---

## Overview

The scraper system provides RESTful APIs for managing web scraping operations, including source configuration, AI-powered selector generation, content classification, and queue management.

**Base URL**: `/api/v1/scraper`
_(Most UI routes continue to live under `/admin/scraper`, but the official JSON API lives under `/api/v1/scraper`.)_
**Content-Type**: `application/json`
**Authentication**: Required (admin session)

---

## Authentication

All endpoints require admin authentication via session cookies. CSRF protection is enabled for state-changing operations.

**CSRF Token**: Include in request headers for POST/PUT/DELETE operations:
```
X-CSRF-Token: <token_from_meta_tag>
```

---

## Dashboard Endpoints

### GET /admin/scraper

Get scraper dashboard with statistics and recent activity.

**Response**:
```json
{
  "success": true,
  "stats": {
    "total_sources": 15,
    "active_sources": 12,
    "total_scrapes": 1234,
    "success_rate": 95.5,
    "avg_response_time": 2.3
  },
  "recent_jobs": [
    {
      "id": 1,
      "source_name": "Example News",
      "status": "completed",
      "items_found": 42,
      "items_saved": 40,
      "created_at": "2026-03-29 09:00:00"
    }
  ]
}
```

---

## Source Management

### GET /admin/scraper/sources

List all scraper sources with pagination.

**Query Parameters**:
- `page` (int, optional): Page number (default: 1)
- `per_page` (int, optional): Items per page (default: 20)
- `status` (string, optional): Filter by status (active/inactive)

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Example News",
      "url": "https://example.com",
      "type": "static",
      "is_active": true,
      "last_fetch": "2026-03-29 08:00:00",
      "category_name": "News"
    }
  ],
  "meta": {
    "total": 15,
    "page": 1,
    "per_page": 20
  }
}
```

### GET /admin/scraper/sources/{id}

Get details of a specific source.

**Response**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Example News",
    "url": "https://example.com",
    "type": "static",
    "category_id": 5,
    "selectors": {
      "title": "h1.article-title",
      "content": ".article-content",
      "date": "time.published"
    },
    "config": {
      "timeout": 30,
      "retry_attempts": 3,
      "use_proxy": false
    },
    "is_active": true,
    "created_at": "2026-03-01 00:00:00"
  }
}
```

### POST /admin/scraper/sources

Create a new scraper source.

**Request Body**:
```json
{
  "name": "Example News",
  "url": "https://example.com",
  "type": "static",
  "category_id": 5,
  "selectors": {
    "title": "h1.article-title",
    "content": ".article-content",
    "date": "time.published"
  },
  "config": {
    "timeout": 30,
    "retry_attempts": 3,
    "use_proxy": false
  },
  "is_active": true
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "id": 16,
    "message": "Source created successfully"
  }
}
```

### PUT /admin/scraper/sources/{id}

Update an existing scraper source.

**Request Body**: Same as POST /admin/scraper/sources

**Response**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "message": "Source updated successfully"
  }
}
```

### DELETE /admin/scraper/sources/{id}

Delete a scraper source.

**Response**:
```json
{
  "success": true,
  "message": "Source deleted successfully"
}
```

### POST /admin/scraper/sources/{id}/run

Manually trigger scraping for a specific source.

**Request Body**:
```json
{
  "test": false,
  "force": false
}
```

**Response**:
```json
{
  "success": true,
  "job_id": 123,
  "message": "Scraping job started",
  "estimated_time": "2-5 minutes"
}
```

### GET /api/v1/scraper/jobs/health

Lightweight telemetry for the dashboard's job health badge. The response includes job counts, a computed success rate, and the most recent completion timestamp.

**Response**:
```json
{
  "success": true,
  "data": {
    "stats": {
      "pending": 4,
      "running": 1,
      "completed": 256,
      "failed": 6
    },
    "completed_jobs": 256,
    "failed_jobs": 6,
    "success_rate": 97.7,
    "last_completed_at": "2026-03-30T09:45:00Z",
    "timestamp": "2026-03-30T09:45:12Z"
  }
}
```

## Collected Data Summary

### GET /api/v1/scraper/collected-data/summary

Live metrics for the collected data dashboard, including total/pending/completed counts, published totals, and category breakdowns.

**Response**:
```json
{
  "success": true,
  "data": {
    "total": 1124,
    "statuses": {
      "all": 1124,
      "pending": 12,
      "processing": 5,
      "completed": 1001,
      "failed": 106
    },
    "content_types": {
      "article": 664,
      "blog": 210,
      "product": 83
    },
    "published": 978,
    "last_published_at": "2026-03-30T08:45:00Z",
    "categories": [
      {"id":1, "name":"News", "articles_count":420},
      {"id":2, "name":"Blog", "articles_count":260}
    ]
  }
}
```

## Logs Summary

### GET /api/v1/scraper/logs/summary

Provides log-level totals and the timestamp of the most recent entry so the logs dashboard can show counts without scanning the entire table.

**Response**:
```json
{
  "success": true,
  "data": {
    "levels": {
      "error": 5,
      "warning": 12,
      "info": 120,
      "debug": 3
    },
    "total": 140,
    "latest_timestamp": "2026-03-30T09:20:00Z"
  }
}
```

---

## AI Integration

### POST /api/v1/scraper/ai/preset-generator

Generate a scraper preset for a new URL. The `options` object can nudge the content type or inject selectors/config overrides.

**Request Body**:
```json
{
  "url": "https://example.com",
  "options": {
    "force_content_type": "news",
    "custom_selectors": {},
    "custom_config": {}
  }
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "preset": {
      "name": "Example News Preset",
      "description": "Auto-generated preset for example.com",
      "url": "https://example.com",
      "content_type": "news_article",
      "selectors": {
        "title": ["h1.article-title"],
        "content": [".article-content"],
        "date": ["time.published"],
        "author": [".author"]
      },
      "config": {
        "timeout": 30,
        "retry_attempts": 3,
        "use_proxy": true
      },
      "confidence": 0.85,
      "auto_generated": true
    }
  }
}
```

### POST /api/v1/scraper/ai/analyzer

Analyze a page by URL or raw HTML to detect structure and recommended selectors.

**Request Body**:
```json
{
  "url": "https://example.com"
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "selectors": {...},
    "structure": {...},
    "recommendations": [...],
    "confidence": 0.92
  }
}
```

### POST /api/v1/scraper/ai/classifier

Classify HTML content and optionally evaluate selectors for structured extraction.

**Request Body**:
```json
{
  "html": "<html>...</html>",
  "url": "https://example.com/article",
  "selectors": {
    "title": "h1.article-title",
    "content": ".content"
  }
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "content_type": "news_article",
    "confidence": 0.92,
    "structured_data": {
      "title": "Example Article Title",
      "description": "Article excerpt...",
      "date": "2026-03-29",
      "author": "John Doe",
      "content": "Full article content..."
    }
  }
}
```

### POST /api/v1/scraper/ai/optimizer

Send performance data (and optional current config) to receive optimization suggestions and a recommended configuration.

**Request Body**:
```json
{
  "source_id": 1,
  "performance_data": [
    {
      "timestamp": "2026-03-29 08:00:00",
      "status": "success",
      "response_time": 2.5,
      "items_found": 42
    }
  ],
  "current_config": {
    "timeout": 30,
    "retry_attempts": 3,
    "use_proxy": false
  }
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "basic_optimization": {
      "timeout": 35,
      "retry_attempts": 3,
      "proxy_rotation": false,
      "user_agent_rotation": true
    },
    "ai_optimization": {
      "confidence": 0.88,
      "explanation": "Increased timeout due to slow response times",
      "recommended_config": {
        "timeout": 40,
        "retry_attempts": 4,
        "use_proxy_rotation": true
      }
    },
    "recommended_config": {
      "timeout": 40,
      "retry_attempts": 4,
      "use_proxy_rotation": true,
      "user_agent_rotation": true
    }
  }
}
```

---

## Queue Management

> All queue telemetry is exposed under `/api/v1/scraper/queue/...` so the dashboards can operate independently of the HTML routes.

### GET /api/v1/scraper/queue/status

Retrieve the current queue breakdown (pending/running/completed/failed), retryable window, last activity timestamp, and optional worker heartbeat.

**Response**:
```json
{
  "success": true,
  "data": {
    "stats": {
      "pending": 12,
      "running": 2,
      "completed": 743,
      "failed": 18,
      "cancelled": 4
    },
    "retryable": 5,
    "last_activity": "2026-03-30T09:15:00Z",
    "worker_heartbeat": "2026-03-30T09:16:12",
    "timestamp": "2026-03-30T09:16:13Z"
  }
}
```

### POST /api/v1/scraper/queue/run

Trigger the CLI worker (`scripts/cron/scraper-worker.php`) with optional runtime knobs. The endpoint returns the refreshed queue summary as a convenience for the UI.

**Request Body** (all parameters optional):
```json
{
  "sleep": 5,
  "max_jobs": 20
}
```

**Response**:
```json
{
  "success": true,
  "message": "Queue worker triggered",
  "data": {
    "stats": { ... },
    "retryable": 3,
    "timestamp": "..."
  }
}
```

### POST /api/v1/scraper/queue/clear

Clear all pending jobs (they are marked as cancelled). The response returns the updated summary so dashboards can re-render immediately.

**Response**:
```json
{
  "success": true,
  "message": "Cleared 7 pending jobs from the queue",
  "data": { ... }
}
```

The legacy `/admin/scraper/queue/retry` endpoint remains available for individual retries/cancels until the UI is fully transitioned to the new APIs.

---

## Testing Endpoints

### POST /admin/scraper/test-url

Test URL accessibility and response.

**Request Body**:
```json
{
  "url": "https://example.com"
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "status_code": 200,
    "accessible": true,
    "response_time": 1.2,
    "url": "https://example.com"
  }
}
```

### POST /admin/scraper/test-selectors

Test CSS/XPath selectors against a URL.

**Request Body**:
```json
{
  "url": "https://example.com",
  "selectors": {
    "title": "h1.article-title",
    "content": ".article-content",
    "date": "time.published"
  },
  "max_samples": 5
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "title": {
      "selector": "h1.article-title",
      "matched": true,
      "count": 1,
      "samples": ["Example Article Title"]
    },
    "content": {
      "selector": ".article-content",
      "matched": true,
      "count": 1,
      "samples": ["Article content..."]
    },
    "date": {
      "selector": "time.published",
      "matched": true,
      "count": 1,
      "samples": ["2026-03-29"]
    }
  }
}
```

---

## Error Responses

All endpoints return consistent error responses:

```json
{
  "success": false,
  "error": "Error message description",
  "code": "ERROR_CODE"
}
```

**Common Error Codes**:
- `INVALID_REQUEST`: Malformed request data
- `UNAUTHORIZED`: Authentication required
- `FORBIDDEN`: Insufficient permissions
- `NOT_FOUND`: Resource not found
- `VALIDATION_ERROR`: Input validation failed
- `SCRAPER_ERROR`: Scraping operation failed
- `AI_ERROR`: AI processing failed
- `RATE_LIMITED`: Too many requests

---

## Rate Limiting

API endpoints are rate-limited to prevent abuse:

- **Dashboard endpoints**: 60 requests/minute
- **AI endpoints**: 30 requests/minute
- **Queue operations**: 120 requests/minute

Rate limit headers are included in responses:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1617012400
```

---

## Webhooks

### POST /admin/scraper/webhook

Receive webhook notifications for scraping events.

**Request Body**:
```json
{
  "event": "scrape.completed",
  "data": {
    "job_id": 123,
    "source_id": 1,
    "items_found": 42,
    "items_saved": 40,
    "status": "completed"
  },
  "timestamp": "2026-03-29T09:00:00Z"
}
```

**Supported Events**:
- `scrape.started`: Scraping job started
- `scrape.completed`: Scraping job completed
- `scrape.failed`: Scraping job failed
- `source.created`: New source created
- `source.updated`: Source updated
- `source.deleted`: Source deleted

---

## Performance Metrics

### GET /admin/scraper/metrics

Get performance metrics for analysis.

**Query Parameters**:
- `source_id` (int, optional): Filter by source
- `days` (int, optional): Time period in days (default: 30)

**Response**:
```json
{
  "success": true,
  "data": {
    "total_requests": 1234,
    "success_rate": 95.5,
    "average_response_time": 2.3,
    "error_rate": 4.5,
    "throughput": 15.2,
    "by_source": [
      {
        "source_id": 1,
        "source_name": "Example News",
        "success_rate": 98.2,
        "avg_response_time": 1.8
      }
    ]
  }
}
```

---

## Logs

### GET /admin/scraper/logs

Get scraping logs with filtering.

**Query Parameters**:
- `source_id` (int, optional): Filter by source
- `level` (string, optional): Filter by log level (info/warning/error)
- `limit` (int, optional): Maximum results (default: 100)

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "source_id": 1,
      "job_id": 123,
      "level": "info",
      "message": "Scrape completed successfully",
      "context": {
        "items_found": 42,
        "items_saved": 40
      },
      "created_at": "2026-03-29 09:00:00"
    }
  ]
}
```

---

## Categories

### GET /api/v1/scraper/categories

Returns the configured scraper categories (news, blog, product, etc.) so dashboards can render category chips and filters dynamically.

**Response**:
```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "News", "slug": "news", "description": "News articles" },
    { "id": 2, "name": "Blog", "slug": "blog", "description": "Blog posts" }
  ]
}
```

## Settings

### GET /api/v1/scraper/settings

Provides the latest settings list, total count, and most recently updated key so the settings dashboard can show quick metrics without a full page refresh.

**Query Parameters**:
- `limit` (int, optional): How many rows to return (default: 50, max: 200)

**Response**:
```json
{
  "success": true,
  "data": {
    "total": 38,
    "latest_updated_at": "2026-03-30T09:40:00Z",
    "settings": [
      {
        "setting_key": "max_concurrent_requests",
        "setting_value": "10",
        "description": "Maximum number of concurrent scraper threads",
        "updated_at": "2026-03-30T09:40:00Z"
      }
    ]
  }
}
```

## Best Practices

1. **Always validate selectors** before saving sources
2. **Use AI detection** for new websites to save time
3. **Monitor queue depth** to prevent backlog
4. **Review performance metrics** regularly for optimization
5. **Implement retry logic** for failed jobs
6. **Use rate limiting** to avoid being blocked
7. **Test URLs** before adding as sources
8. **Keep selectors simple** for better performance

---

## Related Documentation

- [Selector Testing API](SELECTOR_TESTING_API.md)
- [Scraper Deployment Guide](SCRAPER_DEPLOYMENT.md)
- [Coding Standards](CODING_STANDARDS.md)
- [AI System Documentation](ai/AI_CODING_GUIDE.md)
