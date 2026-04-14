# BroxLab - Production Deployment Guide

**Version:** 2.1.5 | **Last Updated:** 2026-04-14

## Overview

BroxLab is a comprehensive Bengali-first mobile technology platform built with PHP 8.2+ and Node.js 20+, featuring web scraping, AI-powered content enhancement, OCR processing, admin management, and modern web technologies. This guide covers production deployment for the full-stack application.

## Architecture

### Backend (PHP)

- **Runtime**: PHP 8.2+
- **Framework**: Custom MVC with Twig templating
- **Database**: MySQL 8.0+ with prepared statements
- **Cache**: Redis 6.0+ for sessions and caching
- **Queue**: Database-based job queue

### AI Services (Node.js)

- **Runtime**: Node.js 20+
- **Framework**: Fastify + Express
- **Language**: TypeScript
- **AI Providers**: OpenRouter, Anthropic, Ollama
- **OCR**: Tesseract + custom processing

### Frontend

- **CSS**: Tailwind CSS (compiled)
- **JavaScript**: ES6 modules with bundling
- **Admin Panel**: Custom dashboard with real-time features

### Infrastructure

- **Web Server**: Apache/Nginx
- **Database**: MySQL 8.0+
- **Cache/Queue**: Redis 6.0+
- **Monitoring**: Custom health checks
- **Testing**: PHPUnit + Playwright E2E

## Features

### Core Functionality

- 🌐 **Web Scraping**: AI-powered content collection with 10+ sources
- 🤖 **AI Content Enhancement**: Automated title optimization, SEO, taxonomy
- 📱 **Mobile Database**: Comprehensive device specifications
- 👥 **User Management**: CV system, job listings, notifications
- 📊 **Admin Dashboard**: Real-time monitoring, scraper management
- 📷 **OCR Processing**: Image text extraction with Node.js service
- 🔐 **Security**: CSRF protection, input sanitization, RBAC

### Production Features

- ✅ **Health Monitoring**: Database, cache, API service checks
- 📈 **Performance**: Connection pooling, caching, optimized queries
- 🔍 **Logging**: Structured logs with error tracking
- 🛡️ **Security**: Prepared statements, XSS prevention, rate limiting
- 🚀 **Scalability**: Multi-server setup, load balancing options
- 📊 **Analytics**: Custom metrics and reporting

## Quick Start

### Prerequisites

- PHP 8.2+ with extensions (pdo_mysql, redis, gd, etc.)
- Node.js 20.x
- MySQL 8.0+
- Redis 6.0+
- Composer
- npm or yarn
- Apache/Nginx with mod_rewrite

### Installation

```bash
# Clone the repository
git clone <repository-url>
cd broxlab

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies
npm ci --production=false

# Setup database
mysql -u root -p < scripts/create_tables.sql

# Run migrations
php scripts/run_migration.php
php scripts/migrate-ai-enhancement-columns.php
php scripts/add_ai_mcp_settings_columns.php

# Build assets
npm run build

# Copy environment file
cp .env.example .env

# Edit environment variables
nano .env
```

### Environment Variables

```env
# Application
APP_ENV=production
APP_URL=https://yourdomain.com
APP_KEY=your_app_key

# Database
DB_HOST=localhost
DB_USER=broxlab
DB_PASSWORD=your_password
DB_DATABASE=broxlab

# Redis
REDIS_HOST=localhost
REDIS_PASSWORD=
SESSION_DRIVER=redis
CACHE_DRIVER=redis

# AI Providers
OPENROUTER_API_KEY=your_openrouter_key
ANTHROPIC_API_KEY=your_anthropic_key
OLLAMA_BASE_URL=http://localhost:11434

# Node.js Services
NODE_AI_PORT=3001
NODE_OCR_PORT=3002

# Security
CSRF_SECRET=your_csrf_secret

# Email (optional)
SMTP_HOST=your_smtp_host
SMTP_USER=your_smtp_user
SMTP_PASS=your_smtp_pass
```

### Development

```bash
# Start PHP development (requires local server)
# Configure Apache/Nginx to serve public_html/

# Start Node.js services
npm run nodes:start

# Start AI assistant
npm run ai-assistant:dev

# Run tests
composer test
npm test

# Run linting
npm run lint

# Build assets
npm run build
```

### Production Deployment

```bash
# Deploy script (if available)
php scripts/deploy.php

# Or manually:
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node dependencies
npm ci --production=false

# Run quality checks
php scripts/quality_scan.php
npm run lint

# Build assets
npm run build

# Setup web server (Apache/Nginx)
# Copy public_html/ to web root
# Configure .htaccess or nginx.conf

# Start Node.js services
npm run nodes:start

# Setup cron jobs (see docs/integrations/cpanel-cronjobs.md)
```

## API Endpoints

### PHP Backend APIs

- `GET /api/health` - System health check
- `POST /api/scraper/start` - Start scraping job
- `GET /api/scraper/status/{id}` - Check scraping status
- `POST /api/ai/enhance` - Enhance content with AI
- `GET /api/mobiles` - Mobile device listings
- `POST /api/admin/notification` - Send notification

### Node.js AI Services

- `POST /api/ai/chat` - AI chat (streaming/non-streaming)
- `GET /api/ai/tools` - List available AI tools
- `POST /api/ai/ocr` - OCR image processing
- `GET /api/health/ai` - AI service health check

### Admin APIs

- `GET /admin/api/system/health` - Full system health
- `POST /admin/api/scraper/create` - Create scraper source
- `GET /admin/api/scraper/logs` - Scraper activity logs
- `POST /admin/api/ai/enhance/batch` - Batch content enhancement

## Monitoring & Health Checks

### System Health

- **Database**: Connection and basic query check
- **Redis**: Cache connectivity test
- **Node.js Services**: AI and OCR service availability
- **File System**: Write permissions check
- **External APIs**: AI provider connectivity

### Health Check Endpoints

- `GET /api/health` - Overall system health
- `GET /api/health/database` - Database connectivity
- `GET /api/health/redis` - Redis connectivity
- `GET /api/health/services` - Node.js services status

### Monitoring Dashboard

- Admin panel includes real-time service status
- Server online/offline indicators
- Scraper job progress tracking
- Error logs and notifications

### Logs

- PHP: `storage/logs/laravel.log` (error logs)
- Node.js: Structured JSON logs to console
- Admin: Real-time log viewer in dashboard

## Testing

### PHP Tests

```bash
# Run PHPUnit tests
composer test

# With coverage
composer test:coverage
```

### Node.js Tests

```bash
# Unit tests
npm run test

# E2E tests
npm run e2e

# Playwright tests
npx playwright test
```

### Quality Checks

```bash
# PHP syntax check
php scripts/quality_scan.php

# JS linting
npm run lint

# Asset validation
npm run check:assets
```

### Integration Tests

```bash
npm run test:integration
```

### E2E Tests

```bash
npm run test:e2e
```

### All Tests

```bash
npm test
```

## Security

### Authentication & Authorization

- Session-based authentication with Redis
- Role-based access control (admin/user/guest)
- CSRF protection on all forms and APIs
- Password hashing with secure algorithms

### Input Validation & Sanitization

- Server-side validation on all inputs
- SQL injection prevention with prepared statements
- XSS protection with output escaping
- File upload validation and scanning

### Security Features

- Secure headers (CSP, HSTS, etc.)
- Rate limiting on sensitive endpoints
- Input sanitization helpers
- Secure password requirements
- Account lockout protection

## Performance & Scaling

### Optimizations

- Database connection pooling and query optimization
- Redis caching for sessions, content, and API responses
- Lazy loading and pagination for large datasets
- Minified CSS/JS assets with proper caching headers
- Image optimization and CDN support

### Scaling Options

- **Multi-Server Setup**: Load balancing with session sharing
- **Database Replication**: Read/write splitting for high traffic
- **Queue Processing**: Background job processing for scrapers
- **CDN Integration**: Static asset delivery optimization
- **Horizontal Scaling**: Multiple PHP/Node.js instances

## Troubleshooting

### Common Issues

#### Database Connection Failed

```bash
# Check MySQL service
sudo systemctl status mysql

# Test connection
mysql -h localhost -u broxlab -p broxlab

# Check PHP database config
php scripts/test_db_connection.php
```

#### Redis Connection Failed

```bash
# Check Redis service
sudo systemctl status redis-server

# Test connection
redis-cli ping

# Check PHP Redis extension
php -m | grep redis
```

#### Node.js Services Not Starting

```bash
# Check PM2 status
pm2 status

# View service logs
pm2 logs broxlab-node

# Restart services
npm run nodes:restart
```

#### Web Scraping Issues

```bash
# Check scraper service health
curl http://localhost:3001/health

# View scraper logs
tail -f storage/logs/scraper.log

# Test scraper manually
php scripts/test-scraper-system.php
```

#### Permission Issues

```bash
# Fix storage permissions
chmod -R 755 storage/
chmod -R 777 storage/logs/
chmod -R 777 storage/cache/

# Check web server user
ps aux | grep apache
```

### Logs

```bash
# PHP application logs
tail -f storage/logs/laravel.log

# Node.js service logs
pm2 logs

# Web server logs
tail -f /var/log/apache2/error.log
tail -f /var/log/nginx/error.log

# Scraper logs
tail -f storage/logs/scraper.log
```

#### Redis Connection Failed

```bash
# Check Redis service
sudo systemctl status redis

# Test connection
redis-cli ping
```

#### Port Already in Use

```bash
# Find process using port
lsof -i :3001

# Kill process
kill -9 <PID>
```

#### High Memory Usage

- Check for memory leaks in tool executions
- Monitor with `/metrics` endpoint
- Consider increasing server memory

### Logs

Logs are structured JSON output to stdout/stderr:

```bash
# View recent logs
tail -f /var/log/broxlab/backend.log

# Search for errors
grep "error" /var/log/broxlab/backend.log
```

## Deployment Checklist

- [ ] Environment variables configured
- [ ] Database tables created and migrated
- [ ] Node.js services started with PM2
- [ ] Web server configured (Apache/Nginx)
- [ ] SSL certificate installed
- [ ] Cron jobs scheduled
- [ ] File permissions set correctly
- [ ] Health checks passing
- [ ] Admin panel accessible
- [ ] Backup system configured

## Contributing

1. Fork the repository
2. Create a feature branch from `main`
3. Follow coding standards in `docs/guides/coding-standards.md`
4. Run tests: `composer test && npm test`
5. Run quality checks: `php scripts/quality_scan.php && npm run lint`
6. Submit a pull request with detailed description

## License

This project is proprietary software. See LICENSE file for details.

## Support

For support and questions:

- 📧 Email: support@broxlab.com
- 📖 Documentation: https://docs.broxlab.com
- 🐛 Issues: https://github.com/broxlab/broxlab/issues
- 📱 Live Chat: Available in admin panel
