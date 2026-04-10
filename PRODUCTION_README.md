# BroxLab AI Backend - Production Deployment Guide

## Overview

This is a production-ready Node.js + TypeScript backend for the BroxLab AI Assistant system. The backend provides AI chat functionality, tool execution, OCR processing, and comprehensive monitoring.

## Architecture

- **Runtime**: Node.js 20+
- **Framework**: Fastify
- **Language**: TypeScript
- **Database**: MySQL 8.0+
- **Cache**: Redis 6.0+
- **Monitoring**: Prometheus + Grafana
- **Testing**: Vitest + Playwright

## Features

### Core Functionality
- 🤖 AI Chat with streaming support
- 🛠️ Tool execution system with 20+ tools
- 📷 OCR processing for images
- 🔐 Admin panel integration
- 📊 Comprehensive monitoring and metrics

### Production Features
- ✅ Health checks and graceful shutdown
- 📈 Prometheus metrics collection
- 🔍 Structured logging with Pino
- 🛡️ Security middleware (CORS, rate limiting, CSRF)
- 📊 Database connection pooling
- 🚀 Performance optimizations

## Quick Start

### Prerequisites

- Node.js 20.x
- MySQL 8.0+
- Redis 6.0+
- npm or yarn

### Installation

```bash
# Clone the repository
git clone <repository-url>
cd broxlab-ai-backend

# Install dependencies
npm install

# Copy environment file
cp .env.example .env

# Edit environment variables
nano .env
```

### Environment Variables

```env
# Application
NODE_ENV=production
PORT=3001

# Database
DB_HOST=localhost
DB_PORT=3306
DB_USER=broxlab
DB_PASSWORD=your_password
DB_DATABASE=broxlab

# Redis
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=

# AI Providers
OPENROUTER_API_KEY=your_openrouter_key
ANTHROPIC_API_KEY=your_anthropic_key

# Security
JWT_SECRET=your_jwt_secret
CSRF_SECRET=your_csrf_secret

# Monitoring
PROMETHEUS_PORT=9090
```

### Development

```bash
# Start development server
npm run dev

# Run tests
npm test

# Run linting
npm run lint

# Build for production
npm run build
```

### Production Deployment

```bash
# Run deployment script
node scripts/deploy.js

# Or manually:
npm ci --production=false
npm run lint
npm test
npm run build

# Start production server
npm start
```

## API Endpoints

### Health & Monitoring
- `GET /health` - Health check with service status
- `GET /metrics` - Prometheus metrics endpoint

### AI Chat
- `POST /api/ai/chat` - Non-streaming chat
- `POST /api/ai/chat/stream` - Streaming chat with SSE

### Tools (Admin Only)
- `GET /api/admin/ai-tools` - List available tools
- `POST /api/admin/ai-tools/execute` - Execute a tool
- `GET /api/admin/ai-tools/cache/stats` - Cache statistics
- `POST /api/admin/ai-tools/cache/clear` - Clear tool cache

### OCR
- `POST /api/ai/ocr` - Process image for OCR
- `GET /api/ai/ocr/health` - OCR service health check

## Monitoring & Metrics

### Prometheus Metrics

The application exposes comprehensive metrics at `/metrics`:

#### HTTP Metrics
- `http_requests_total{method, route, status_code}` - Total HTTP requests
- `http_request_duration_seconds{method, route}` - Request duration

#### AI Metrics
- `ai_requests_total{provider, model, success}` - AI API requests
- `ai_tokens_used_total{provider, model}` - Token usage

#### Tool Metrics
- `tool_executions_total{tool_name, success}` - Tool executions
- `tool_execution_duration_seconds{tool_name}` - Tool execution time

#### Cache Metrics
- `cache_hits_total{cache_type}` - Cache hits
- `cache_misses_total{cache_type}` - Cache misses

#### Database Metrics
- `db_connections_active` - Active DB connections
- `db_query_duration_seconds{query_type}` - Query execution time

#### Health Metrics
- `health_checks_total{service, status}` - Health check results

### Grafana Dashboard

Import the provided dashboard JSON (`monitoring/grafana-dashboard.json`) for a comprehensive monitoring dashboard.

## Testing

### Unit Tests
```bash
npm run test:unit
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
- JWT-based authentication
- Role-based access control (admin/user)
- CSRF protection on sensitive endpoints

### Input Validation
- Zod schemas for all API inputs
- SQL injection prevention with prepared statements
- XSS protection with input sanitization

### Rate Limiting
- Configurable rate limits per endpoint
- Automatic retry with exponential backoff

## Performance

### Optimizations
- Database connection pooling
- Redis caching for tool results
- Circuit breaker pattern for tool execution
- Request/response compression

### Scaling
- Horizontal scaling with multiple instances
- Redis pub/sub for inter-instance communication
- Load balancer configuration provided

## Troubleshooting

### Common Issues

#### Database Connection Failed
```bash
# Check MySQL service
sudo systemctl status mysql

# Test connection
mysql -h localhost -u broxlab -p
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

## Contributing

1. Fork the repository
2. Create a feature branch
3. Run tests: `npm test`
4. Run linting: `npm run lint`
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and questions:
- 📧 Email: support@broxlab.com
- 📖 Documentation: https://docs.broxlab.com
- 🐛 Issues: https://github.com/broxlab/ai-backend/issues