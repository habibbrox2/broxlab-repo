# AI AutoBlog - Complete A to Z Usage Guide

Welcome to the BroxBhai AI AutoBlog System! This comprehensive guide will walk you through everything from initial setup to advanced automation.

---

## A - Getting Started (Initial Setup)

### Accessing the AutoBlog Dashboard

Navigate to the admin panel and access the AutoBlog section:

```
URL: /admin/autoblog
```

You'll need:
- Admin authentication
- Admin-only access permission

### First-Time Configuration

Before collecting any articles, configure your AI settings:

1. Go to **Settings**: `/admin/autoblog/settings`
2. Configure the following:

| Setting | Description | Example |
|---------|-------------|---------|
| AI Endpoint | API URL for AI service | `https://api.openai.com/v1/chat/completions` |
| AI Model | Model to use for content generation | `gpt-4o-mini` |
| AI Key | Your API key for authentication | `sk-xxxx...` |
| Max Articles | Maximum articles to fetch per source | `10` |
| Publish Status | Default status for published posts | `published` |

### Enabling Automation

Toggle these automation options:
- **Auto Collect**: Automatically fetch new articles on schedule
- **Auto Process**: Run AI content enhancement automatically
- **Auto Publish**: Publish processed articles without manual approval

---

## B - Adding Content Sources

### Creating Your First Source

Navigate to: `/admin/autoblog/sources/create`

**Source Types:**

1. **RSS Feed** (Most Common)
   - Perfect for news sites, blogs, podcasts
   - Example: `https://techcrunch.com/feed/`

2. **Website Scraping**
   - For sites without RSS
   - Requires CSS selectors

3. **Custom API**
   - For advanced integrations

### Source Configuration Fields

| Field | Required | Description |
|-------|----------|-------------|
| Name | Yes | Display name for the source |
| URL | Yes | The feed or website URL |
| Type | Yes | `rss`, `website`, or `api` |
| Category | No | Assign to a content category |
| Selectors | For website | CSS selectors for content extraction |
| Fetch Interval | No | How often to check (seconds) |
| Active | No | Enable/disable the source |

### Example: Adding a Tech News Source

```
Name: TechCrunch
URL: https://techcrunch.com/feed/
Type: RSS
Category: Technology
Fetch Interval: 3600 (1 hour)
Active: ✓
```

---

## C - Collecting Articles

### Manual Collection

Trigger an immediate article fetch:

```
POST /admin/autoblog/api/collect
```

**Using cURL:**
```bash
curl -X POST https://yourdomain.com/admin/autoblog/api/collect \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response Example:**
```json
{
  "success": true,
  "collected": 15,
  "message": "Articles collected successfully"
}
```

### Viewing the Queue

Access collected articles at: `/admin/autoblog/queue`

**Article Status Types:**
- `pending` - Newly collected, awaiting processing
- `processing` - Currently being enhanced by AI
- `processed` - AI enhancement complete
- `published` - Successfully posted to site
- `failed` - Error occurred during processing

### Queue Filtering

Filter by:
- Status: `?status=pending`
- Source: `?source=1`
- Page: `?page=2`

---

## D - Processing with AI

### Understanding AI Processing

The AI enhancement includes:
1. **Content Rewrite** - Originality improvement
2. **SEO Optimization** - Keyword enhancement
3. **Formatting** - Proper structure with headings
4. **Summary Generation** - Auto-generated excerpts
5. **Tag Suggestion** - Relevant tags extraction

### Manual Processing

Process individual articles:

```
GET /admin/autoblog/api/process?id=ARTICLE_ID
```

### Batch Processing

Process multiple articles:

```
POST /admin/autoblog/api/process
```

**Request Body:**
```json
{
  "ids": [1, 2, 3, 4, 5],
  "options": {
    "rewrite": true,
    "seo": true,
    "summary": true
  }
}
```

### Processing Options

| Option | Default | Description |
|--------|---------|-------------|
| `rewrite` | true | Rewrite content for originality |
| `seo` | true | Add SEO optimizations |
| `summary` | true | Generate summary |
| `tags` | true | Suggest relevant tags |
| `language` | en | Output language |

---

## E - Publishing Articles

### Manual Publishing

Publish individual articles:

```
GET /admin/autoblog/api/publish?id=ARTICLE_ID
```

### Auto-Publish Configuration

In Settings, set your preferred publish behavior:

```php
// Option 1: Publish immediately after AI processing
'auto_publish' => '1'

// Option 2: Save as draft for review
'publish_status' => 'draft'

// Option 3: Schedule for later
'publish_status' => 'scheduled'
```

### Bulk Publishing

Publish multiple articles at once:

```
POST /admin/autoblog/api/publish
```

**Request Body:**
```json
{
  "ids": [1, 2, 3, 4, 5],
  "status": "published",
  "category_id": 5,
  "author_id": 1
}
```

---

## F - Monitoring & Analytics

### Dashboard Statistics

View at: `/admin/autoblog`

**Key Metrics:**
- Total Articles Collected
- Articles Pending Processing
- Published Today/This Week
- Success Rate
- API Usage

### Chart Data

Get chart data via AJAX:

```
GET /admin/autoblog/stats/chart?days=30
```

**Response:**
```json
{
  "labels": ["2026-03-01", "2026-03-02", "..."],
  "datasets": [{
    "label": "Articles Collected",
    "data": [5, 8, 12, 3, ...],
    "borderColor": "#6366f1"
  }]
}
```

### Activity Logs

All AutoBlog activities are logged:
- Source created/updated/deleted
- Articles collected
- Processing results
- Publish events

View logs in the main activity section.

---

## G - Advanced Configuration

### Custom AI Models

Use any OpenAI-compatible endpoint:

```
AI Endpoint: https://api.anthropic.com/v1/messages
AI Model: claude-3-opus-20240229
```

### Webhook Notifications

Configure webhooks for real-time notifications:

```php
// In settings or custom implementation
$webhookUrl = 'https://yourdomain.com/webhook/autoblog';
$payload = [
    'event' => 'article_published',
    'article_id' => 123,
    'title' => 'Article Title',
    'url' => 'https://yoursite.com/article-slug'
];
```

### Rate Limiting

Protect your API usage:

```php
// Configure in settings
'rate_limit' => [
    'requests_per_minute' => 10,
    'articles_per_hour' => 50
]
```

---

## H - Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| No articles collected | Source URL invalid | Verify URL in browser first |
| AI processing fails | Invalid API key | Check settings, regenerate key |
| Content not published | Category not set | Assign default category in settings |
| Rate limit errors | Too many requests | Increase interval or reduce concurrency |

### Debug Mode

Enable detailed logging:

```php
// In config or settings
'debug_mode' => true;
'log_level' => 'verbose'; // minimal, normal, verbose
```

### Log Files

Check these locations for errors:
- `storage/logs/debug.log`
- `storage/logs/error.log`

---

## I - API Reference

### Complete Endpoint List

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/autoblog` | Dashboard |
| GET | `/admin/autoblog/sources` | List sources |
| POST | `/admin/autoblog/sources/create` | Create source |
| POST | `/admin/autoblog/sources/edit` | Update source |
| GET | `/admin/autoblog/sources/delete` | Delete source |
| GET | `/admin/autoblog/sources/toggle` | Toggle active |
| GET | `/admin/autoblog/queue` | Article queue |
| GET | `/admin/autoblog/queue/view` | View article |
| POST | `/admin/autoblog/api/collect` | Collect articles |
| POST | `/admin/autoblog/api/process` | Process with AI |
| POST | `/admin/autoblog/api/publish` | Publish articles |
| GET/POST | `/admin/autoblog/settings` | Configuration |

### Authentication

All admin endpoints require:
1. Valid admin session
2. CSRF token for POST requests

---

## J - Best Practices

### Source Management

1. **Start Small** - Begin with 1-2 reliable RSS feeds
2. **Monitor Quality** - Check published content regularly
3. **Diversify** - Add sources from different niches
4. **Regular Cleanup** - Remove inactive sources

### AI Optimization

1. **Choose Right Model** - Balance quality vs cost
2. **Custom Prompts** - Customize for your niche
3. **Review Outputs** - Audit AI-generated content
4. **Human Review** - Keep manual approval for important posts

### Automation Safety

1. **Start with Manual** - Test thoroughly before auto-publish
2. **Set Limits** - Configure reasonable article limits
3. **Monitor Closely** - Watch initial automated runs
4. **Backup Regularly** - Keep database backups

---

## K - Use Case Examples

### Example 1: Tech News Aggregator

```php
// Sources
$sources = [
    ['name' => 'TechCrunch', 'url' => 'https://techcrunch.com/feed/', 'category' => 'Technology'],
    ['name' => 'The Verge', 'url' => 'https://www.theverge.com/rss/index.xml', 'category' => 'Technology'],
    ['name' => 'Wired', 'url' => 'https://www.wired.com/feed/rss', 'category' => 'Technology']
];

// Settings
$settings = [
    'auto_collect' => true,
    'auto_process' => true,
    'auto_publish' => false, // Review first
    'max_articles_per_source' => 5
];
```

### Example 2: Daily Blog Automation

```php
// Schedule: Run daily at 6 AM
// Sources: 10 curated blogs in niche
// Processing: Full AI enhancement
// Publishing: Auto-publish with draft review

$automation = [
    'schedule' => '0 6 * * *', // Cron expression
    'sources' => $curatedBlogs,
    'ai_enhancement' => 'full',
    'publish_mode' => 'auto'
];
```

---

## L - Database Schema

### autoblog_sources Table

```sql
CREATE TABLE autoblog_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    type ENUM('rss', 'website', 'api') DEFAULT 'rss',
    category_id INT,
    selectors TEXT,
    fetch_interval INT DEFAULT 3600,
    is_active TINYINT(1) DEFAULT 1,
    last_fetched_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### autoblog_articles Table

```sql
CREATE TABLE autoblog_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_id INT NOT NULL,
    original_url VARCHAR(2048),
    original_title TEXT,
    original_content LONGTEXT,
    processed_title TEXT,
    processed_content LONGTEXT,
    summary TEXT,
    featured_image VARCHAR(512),
    tags VARCHAR(512),
    status ENUM('pending', 'processing', 'processed', 'published', 'failed') DEFAULT 'pending',
    published_at DATETIME,
    post_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## M - Security Considerations

### API Key Protection

1. Never expose API keys in URLs
2. Use environment variables
3. Rotate keys regularly
4. Set appropriate scopes

### Input Validation

All user inputs are sanitized:
- SQL injection prevention via prepared statements
- XSS protection via output encoding
- CSRF protection for all POST forms

### Rate Protection

Implement rate limiting to prevent:
- API abuse
- Server overload
- Cost spikes

---

## N - Performance Optimization

### Caching Strategies

1. **Source Caching** - Cache RSS feeds locally
2. **AI Response Caching** - Cache similar transformations
3. **Output Caching** - Cache published content

### Database Optimization

```sql
-- Add indexes for performance
CREATE INDEX idx_source_id ON autoblog_articles(source_id);
CREATE INDEX idx_status ON autoblog_articles(status);
CREATE INDEX idx_created_at ON autoblog_articles(created_at);
```

### Queue Processing

Process articles in batches:
```php
$batchSize = 10; // Process 10 at a time
$delayBetweenBatches = 5; // seconds
```

---

## O - Integration Examples

### WordPress Integration

```php
// Publish to WordPress
$wordpress = [
    'api_url' => 'https://yourblog.com/wp-json/wp/v2/posts',
    'username' => 'admin',
    'application_password' => 'xxxx xxxx xxxx xxxx'
];

// Create post
$postData = [
    'title' => $article['processed_title'],
    'content' => $article['processed_content'],
    'status' => 'publish',
    'categories' => [$article['category_id']],
    'tags' => explode(',', $article['tags'])
];
```

### Custom CMS Integration

```php
// Generic CMS publish
$publisher = new CustomPublisher();
$postId = $publisher->createPost([
    'title' => $article['processed_title'],
    'body' => $article['processed_content'],
    'excerpt' => $article['summary'],
    'featured_image' => $article['featured_image'],
    'slug' => sanitize_title($article['processed_title'])
]);
```

---

## P - Scheduling with Cron

### Setting Up Automated Collection

Add to your crontab:

```bash
# Every hour - collect new articles
0 * * * * curl -s -X POST https://yourdomain.com/admin/autoblog/api/collect

# Every 6 hours - process pending articles
0 */6 * * * curl -s -X POST https://yourdomain.com/admin/autoblog/api/process

# Daily at 2 AM - publish processed articles
0 2 * * * curl -s -X POST https://yourdomain.com/admin/autoblog/api/publish
```

### Alternative: WP-Cron (for WordPress)

```php
// In wp-config.php
define('DISABLE_WP_CRON', false);
define('ALTERNATE_WP_CRON', true);

// Schedule events
if (!wp_next_scheduled('autoblog_hourly_event')) {
    wp_schedule_event(time(), 'hourly', 'autoblog_hourly_event');
}
```

---

## Q - Quality Control

### Content Review Checklist

Before enabling full automation, ensure:

- [ ] AI-generated content reads naturally
- [ ] SEO optimizations are effective
- [ ] Images are properly handled
- [ ] Links are preserved and working
- [ ] Category assignments are correct
- [ ] Author attribution is set

### Moderation Tools

1. **Preview Mode** - Review before publishing
2. **Approval Queue** - Manual approval workflow
3. **Content Filters** - Block inappropriate content
4. **Duplicate Detection** - Prevent duplicate posts

---

## R - Reporting & Notifications

### Email Reports

Configure email notifications in settings:

```php
$notificationSettings = [
    'email_enabled' => true,
    'email_to' => 'admin@yoursite.com',
    'notify_on' => ['error', 'publish', 'daily_summary']
];
```

### Dashboard Widgets

The dashboard includes:
- Recent activity feed
- Quick action buttons
- Performance charts
- Status indicators

---

## S - Scaling Considerations

### Handling Growth

As your AutoBlog grows:

1. **Multiple Sources** - Add sources incrementally
2. **Queue Management** - Monitor processing queue
3. **Resource Allocation** - Ensure adequate server resources
4. **API Budget** - Plan for increased AI usage

### Multi-Site Support

For multiple AutoBlog installations:

```php
// Per-site configuration
$sites = [
    'tech.yoursite.com' => ['sources' => [...], 'settings' => [...]],
    'lifestyle.yoursite.com' => ['sources' => [...], 'settings' => [...]]
];
```

---

## T - Troubleshooting Flowchart

```
Problem Occurs
     |
     v
Check Dashboard Stats
     |
     v
Look at Article Status
     |
     +---> Failed? ---> Check Error Logs
     |
     +---> Pending? ---> Try Manual Processing
     |
     +---> Processing? ---> Wait or Cancel & Retry
     |
     v
Verify Settings
     |
     +---> AI Key Valid?
     +---> Source URL Working?
     +---> Category Assigned?
     |
     v
Check Logs (debug.log)
     |
     v
Fix & Test
     |
     v
Re-run Process
```

---

## U - Maintenance Tasks

### Regular Maintenance

1. **Weekly**
   - Review published content quality
   - Check for source errors
   - Monitor API usage

2. **Monthly**
   - Clean up old pending articles
   - Update source list
   - Review and optimize settings

3. **Quarterly**
   - Full system audit
   - Update AI prompts
   - Backup and test restoration

### Database Cleanup

```sql
-- Delete old failed articles (older than 30 days)
DELETE FROM autoblog_articles 
WHERE status = 'failed' 
AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Optimize tables
OPTIMIZE TABLE autoblog_articles;
OPTIMIZE TABLE autoblog_sources;
```

---

## V - Version History & Updates

### Current Version Features

- RSS Feed Collection
- Website Scraping
- AI Content Enhancement (GPT models)
- Auto-Publishing
- Dashboard Analytics
- Full CRUD for Sources
- Article Queue Management
- REST API Endpoints

### Coming Soon

- Multiple AI provider support
- Visual content scraping
- Social media auto-sharing
- Advanced scheduling
- Content templates

---

## W - Support & Resources

### Getting Help

1. Check the [Troubleshooting](#h---troubleshooting) section
2. Review error logs
3. Check system requirements
4. Contact support with log details

### Related Documentation

- [PROJECT.md](../PROJECT.md) - Main project documentation
- [ScraperService.php](../app/Modules/Scraper/ScraperService.php) - Scraping module
- [AI Integration](./ai-integration.md) - AI setup guide

---

## X - Quick Reference Card

### Essential Commands

```bash
# Collect articles
curl -X POST /admin/autoblog/api/collect

# Process articles  
curl -X POST /admin/autoblog/api/process -d '{"ids":[1,2,3]}'

# Publish articles
curl -X POST /admin/autoblog/api/publish -d '{"ids":[1,2,3]}'

# Get settings
curl -X GET /admin/autoblog/settings

# Update settings
curl -X POST /admin/autoblog/settings -d 'ai_key=xxx'
```

### Key URLs

| Function | URL |
|----------|-----|
| Dashboard | `/admin/autoblog` |
| Sources | `/admin/autoblog/sources` |
| Queue | `/admin/autoblog/queue` |
| Settings | `/admin/autoblog/settings` |
| API Collect | `/admin/autoblog/api/collect` |
| API Process | `/admin/autoblog/api/process` |
| API Publish | `/admin/autoblog/api/publish` |

---

## Y - Success Tips

### Top 10 Tips for Success

1. **Start Slow** - Begin with manual processes
2. **Quality First** - Don't sacrifice quality for quantity
3. **Regular Monitoring** - Check results frequently
4. **Diversify Sources** - Don't rely on one source
5. **Customize AI** - Tune prompts for your niche
6. **Keep Backups** - Regular database backups
7. **Test Thoroughly** - Test before automating
8. **Stay Updated** - Keep system updated
9. **Engage with Content** - Add human touch where needed
10. **Be Patient** - Build quality over time

### Common Mistakes to Avoid

1. ❌ Adding too many sources at once
2. ❌ Enabling auto-publish without testing
3. ❌ Ignoring AI-generated content quality
4. ❌ Not monitoring API costs
5. ❌ Skipping regular maintenance

---

## Z - Conclusion

Congratulations! You've completed the A-Z guide to BroxBhai AI AutoBlog.

### Next Steps

1. ✅ Configure your AI settings
2. ✅ Add your first source
3. ✅ Test article collection
4. ✅ Process with AI
5. ✅ Publish manually first
6. ✅ Enable automation gradually

### Getting Started Today

```php
// Quick start checklist
$quickStart = [
    '1. Get AI API Key' => 'Visit OpenAI or your preferred provider',
    '2. Configure Settings' => 'Go to /admin/autoblog/settings',
    '3. Add First Source' => 'Use an RSS feed you trust',
    '4. Collect Articles' => 'Click "Collect Now" button',
    '5. Process & Review' => 'Process with AI, review output',
    '6. Publish' => 'Publish your first article!'
];
```

### Need Help?

- Review this guide again
- Check error logs
- Contact support

**Happy AutoBlogging! 🚀**

---

*Document Version: 1.0*  
*Last Updated: 2026-03-06*  
*For BroxBhai Platform v1.0+*
