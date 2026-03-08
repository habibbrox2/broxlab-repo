## পরিকল্পনা: AI Auto Content System উন্নত করা

Enhance the existing AI Auto Content system by addressing security vulnerabilities, improving performance, adding new features, and increasing observability. This will make the system more robust, scalable, and user-friendly while maintaining its core functionality of automated content collection, AI processing, and publishing.

**Steps**

### Phase 1: Security Fixes (Priority 1 - Critical)
1. Fix SQL injection vulnerability in AutoContentController.php by replacing real_escape_string with prepared statements for all database updates.
2. Implement API key encryption in the database using a secure encryption library (e.g., PHP's openssl_encrypt).
3. Enforce HTTPS for all external API calls and add certificate validation.
4. Add per-IP rate limiting to /api/autocontent/articles endpoint using middleware.
5. Validate CSS selectors to prevent potential DOM injection attacks by whitelisting allowed characters and patterns.
6. Implement URL validation before scraping to prevent malicious URLs.

### Phase 2: Performance Optimizations (Priority 2)
1. Add caching layer (Redis) for frequently accessed data like articles, selectors, and presets.
2. Convert synchronous AI API calls to asynchronous processing using a queue system (e.g., integrate with Redis Queue for simplicity).
3. Implement database connection pooling to reduce overhead.
4. Move image storage to a cloud CDN (e.g., AWS S3 or Cloudflare) for faster delivery.
5. Optimize batch processing in CronWorker.php to handle more articles per run without timeouts.

### Phase 3: Feature Enhancements (Priority 3)
1. Integrate JavaScript rendering for scraping using Puppeteer or Playwright to handle dynamic content.
2. Add fact-checking integration by calling external APIs (e.g., Google Fact Check Tools or custom models).
3. Implement multiple AI providers with fallback using OpenRouter API (since you use OpenRouter key) for redundancy to models like GPT-4, Claude, etc.
4. Add plagiarism detection by checking content against web sources.
5. Implement auto-tagging using ML models for automatic category assignment.

### Phase 4: Observability and Monitoring (Priority 4)
1. Implement structured logging with ELK stack or similar for better error tracking.
2. Add metrics collection using Prometheus for monitoring collection rates, latencies, and errors.
3. Set up error alerts via Slack or PagerDuty for critical failures.
4. Enhance admin dashboard with analytics for article performance and system health.
5. Expand audit logs to track all admin actions with timestamps and user IDs.

**Relevant files**
- `app/Controllers/AutoContentController.php` — Fix SQL injection, add rate limiting
- `app/Models/AutoContentModel.php` — Implement prepared statements, add encryption for keys
- `app/Modules/AiContentEnhancer.php` — Add async processing, integrate OpenRouter for multiple providers
- `app/Modules/CronWorker.php` — Optimize batch processing
- `Database/autocontent_settings.sql` — Update schema for encrypted keys
- `app/Views/admin/autocontent/dashboard.twig` — Add analytics widgets
- `Config/AutoContent.php` — Add caching and HTTPS configs

**Verification**
1. Run security scans (e.g., OWASP ZAP) on the admin interface to confirm no SQL injection or XSS vulnerabilities.
2. Perform load testing with tools like JMeter to ensure performance improvements handle increased traffic.
3. Test new features manually: scrape dynamic sites, verify fact-checking, check fallback AI providers via OpenRouter.
4. Monitor logs and metrics in production to ensure observability tools capture issues.
5. Conduct code reviews and unit tests for all changes, especially security-related ones.

**Decisions**
- Start with Phase 1 (Security Fixes) as priority, since you confirmed to begin with security.
- Use OpenRouter for multiple AI providers, as you mentioned using it instead of default Puter.
- For queue system, recommend Redis Queue as it's simple and effective.
- Scope includes all suggested improvements, with OpenRouter integration for AI redundancy.
- Assumes budget allows for OpenRouter (which is paid per token), and other tools like Redis if available; alternatives if not.
- Improvements implemented incrementally, testing each phase.