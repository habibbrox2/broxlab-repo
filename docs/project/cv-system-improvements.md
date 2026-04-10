# CV System Improvements

## Summary
A roadmap of enhancements for the CV management system: versioning, analytics, bulk actions, and integrations that keep resumes reliable and auditable.

## Purpose
Document the security, tracking, and user-facing behavior changes so developers understand how CVs are protected, audited, and shared.

## Key Actions
- Use the version history APIs before removing data, keeping nine automatic backups per CV by default.
- Emit analytics events (`view`, `download`, `share`, `print`) any time a CV is rendered so administrators can monitor usage.
- Offer bulk operations for status updates, downloads, and deletions while preserving CSRF and prepared statement safeguards.

## Related References
- `docs/project/project-context.md` for repo-wide architecture and dependencies.
- `docs/guides/coding-standards.md` for how to log, sanitize, and respond safely.
- `docs/plans/index.md` for planning documents that intersect with CV work.

## System Overview
This document outlines the improvements made to the CV management system, including new features, performance optimizations, and security enhancements.

## New Features

### 1. Version History System
Track and restore previous versions of CVs.

**Database Table:** `cv_versions`
- Stores snapshots of CV data in JSON format
- Tracks version number, creation date, and user who created the version
- Automatic versioning before deletions

**API Endpoints:**
```
GET  /cv/{id}/versions                    - List all versions
GET  /cv/{id}/versions/{version}          - Get specific version
POST /cv/{id}/versions/{version}/restore  - Restore to version
GET  /cv/{id}/versions/compare/{v1}/{v2}  - Compare two versions
```

**Features:**
- Automatic backup before CV deletion
- Restore to any previous version
- Compare differences between versions
- Configurable version retention (default: keep last 10 versions)

### 2. Analytics Tracking
Track CV views, downloads, and other interactions.

**Database Table:** `cv_analytics`
- Records all CV interactions with timestamps
- Tracks user agent and IP address for security
- Stores additional event data as JSON

**Tracked Events:**
- `view` - CV page viewed
- `download` - PDF downloaded
- `share` - CV shared via link
- `print` - CV printed

**API Endpoints:**
```
GET /cv/{id}/analytics          - Get CV analytics
GET /cv/analytics/summary       - Get user's CV summary
```

**Analytics Data:**
- Total views and downloads per CV
- Daily breakdown of activity
- Top performing CVs
- User summary statistics

### 3. Bulk Operations
Perform actions on multiple CVs at once.

**API Endpoints:**
```
POST /cv/bulk/delete    - Delete multiple CVs
POST /cv/bulk/export    - Export multiple CVs
```

**Features:**
- Bulk delete with automatic version backup
- Bulk export to HTML/PDF
- Detailed success/failure reporting
- Ownership validation for all operations

### 4. Rate Limiting
Protect AI endpoints from abuse.

**Database Table:** `ai_rate_limits`
- Tracks API usage per user per endpoint
- Configurable limits per endpoint
- Automatic cleanup of old entries

**Rate Limits:**
| Endpoint | Limit | Window |
|----------|-------|--------|
| AI Improve | 20 requests | 1 hour |
| ATS Score | 10 requests | 1 hour |
| Keywords | 15 requests | 1 hour |
| Parse CV | 5 requests | 1 hour |
| PDF Export | 30 requests | 1 hour |

**Response Headers:**
- `X-RateLimit-Remaining` - Requests remaining
- `X-RateLimit-Reset` - Unix timestamp when limit resets

## Database Migrations

Run the migration script to add new tables and indexes:

```sql
-- Execute Database/cv_improvements.sql
```

This will:
1. Add performance indexes to existing tables
2. Create new tables for versions, analytics, and rate limiting
3. Add tracking columns to the cvs table

## Performance Improvements

### Database Indexes
Added indexes for frequently queried columns:
- `cvs.user_id, updated_at` - Faster user CV listing
- `cvs.is_active` - Faster active CV filtering
- `cv_sections.cv_id, order` - Faster section retrieval
- `cv_items.section_id, order` - Faster item retrieval
- `cv_shares.token` - Faster token lookup
- `cv_shares.cv_id` - Faster share lookup

### Query Optimization
- Batch operations for bulk deletes
- Efficient version comparison
- Optimized analytics aggregation

## Security Enhancements

### Rate Limiting
- Prevents API abuse
- Configurable per endpoint
- User-based tracking

### Input Validation
- All CV IDs cast to integers
- Ownership checks on all operations
- CSRF protection on state-changing endpoints

### Error Handling
- Detailed error logging
- User-friendly error messages
- Graceful fallbacks for AI failures

## API Response Format

### Success Response
```json
{
  "success": true,
  "data": { ... }
}
```

### Error Response
```json
{
  "error": "Error message",
  "details": { ... }
}
```

### Rate Limit Exceeded
```json
{
  "error": "Rate limit exceeded",
  "remaining": 0,
  "reset_at": 1234567890
}
```
HTTP Status: 429 Too Many Requests

## Usage Examples

### Create Version Backup
```javascript
// Versions are automatically created before deletion
// Or manually via restore endpoint
```

### Track CV View
```php
$cvAnalyticsModel->trackEvent($cvId, 'view', ['source' => 'shared_link']);
```

### Check Rate Limit
```javascript
fetch('/cv/rate-limits')
  .then(res => res.json())
  .then(data => console.log(data.rate_limits));
```

### Bulk Delete CVs
```javascript
fetch('/cv/bulk/delete', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ cv_ids: [1, 2, 3] })
});
```

## Maintenance

### Cleanup Old Data
```php
// Clean analytics older than 1 year
$cvAnalyticsModel->cleanOldData(365);

// Prune old versions (keep last 10)
$cvVersionModel->pruneVersions($cvId, 10);
```

### Monitor Rate Limits
```php
$status = $cvRateLimitModel->getUserRateLimits($userId);
```

## Future Enhancements

1. **Real-time Collaboration** - Multiple users editing same CV
2. **CV Templates Marketplace** - Share and download templates
3. **AI Suggestions** - Proactive improvement suggestions
4. **Integration APIs** - LinkedIn, Indeed, job boards
5. **Mobile App** - Native mobile CV editor
6. **Webhooks** - Notify external systems of CV events

## Changelog

### Version 1.0 (2026-03-19)
- Added version history system
- Added analytics tracking
- Added bulk operations
- Added rate limiting for AI endpoints
- Added database indexes for performance
- Improved error handling
- Added comprehensive documentation
