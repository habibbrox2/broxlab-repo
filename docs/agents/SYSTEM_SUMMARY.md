# Web Scraping & AI Content System Summary

This document provides an overview of the web scraping and AI-powered content generation system within the BroxLab platform.

## Architecture Overview

The system is designed for automated content collection, processing, and publishing, with a focus on news aggregation and SEO optimization.

### 1. Scraping Engine (`app/Modules/Scraper/`)
- **ScraperOrchestrator**: The central hub that coordinates all scraping activities.
- **BrowserScraperService**: Handles JavaScript-rendered sites using local Puppeteer or remote APIs.
- **HttpClientService**: Advanced HTTP client with proxy rotation, user-agent spoofing, and adaptive delays.
- **Scrape Logs**: All scraping activity is logged in the `autocontent_scrape_logs` table for monitoring and debugging.

### 2. AI Enhancement Layer (`app/Modules/AutoContent/`)
- **AiContentEnhancer**: Processes collected articles using various AI providers (OpenRouter, OpenAI, etc.).
- **Style Profiles**: Supports multiple writing tones (Professional, Viral, Formal, Friendly, Minimal) for tailored content generation.
- **Smart Truncation**: Intelligently slices content at sentence or HTML tag boundaries to preserve structural integrity.
- **AI-Driven Metadata**: Automatically suggests categories and tags based on article content.
- **Selector Detection**: AI-powered CSS selector detection for easy onboarding of new news sources.
- **AutoPublisher**: Automatically schedules and publishes AI-enhanced content, dynamically applying AI-suggested taxonomy.

### 3. Background Workers (`scripts/`)
- **autocontent_worker.php**: The main cron-driven script that runs the `CronWorker` to collect articles from all active sources.
- **Unified Pipeline**: Background tasks use the same orchestrator as the web UI, ensuring consistent behavior.

## Key Features
- **JS Rendering**: Support for modern, dynamic websites.
- **Anti-Blocking**: Robust proxy and header management.
- **Multi-Source**: Supports RSS, JSON APIs, and HTML Scraping.
- **AI-Powered**: Automated content rewriting and selector detection.

## Maintenance and Monitoring
- **Scraping Logs**: Check the `autocontent_scrape_logs` table for crawler failures.
- **AI Usage Logs**: Review the `ai_usage_logs` table to monitor token consumption, costs, and API performance/errors.
- **Settings**: Configure AI providers, Style Profiles, and scraping defaults in the Admin Panel or through the respective settings tables.

---

## AI Tool System (v3.0)

The AI assistant uses a centralized tool execution system with OpenAI-compatible schemas.

### Core Components
- **ToolRegistry** (`app/Helpers/ToolRegistry.php`): Registers tools with JSON Schema, executes with parallel/streaming support
- **ToolDefinitions** (`app/Helpers/ToolDefinitions.php`): 10 registered tools (system health, DB queries, log analysis, etc.)
- **AIProvider** (`app/Models/AIProvider.php`): Multi-provider abstraction with Fireworks AI autoscaling retry

### Features
- **Parallel Execution**: `pcntl_fork` (Linux) with sequential fallback (Windows)
- **Streaming Support**: SSE events for real-time tool execution feedback
- **Circuit Breaker**: Opens after 5 consecutive failures; 60s reset timer
- **Retry Logic**: Per-tool configurable exponential backoff
- **Error Categorization**: 7 categories (timeout, network_error, validation_error, auth_error, not_found, circuit_open, resource_exhausted)

### Fireworks AI Autoscaling
- Auto-retries `DEPLOYMENT_SCALING_UP` 503 errors (30 retries, 5s→60s backoff)
- Config: `max_retries=30`, `initial_delay=5s`, `max_delay=60s`, `backoff_multiplier=1.5`

### API Endpoints
| Endpoint | Purpose |
|----------|---------|
| `/api/admin/ai-tools` | List tools + circuit breaker status |
| `/api/admin/ai-tools/execute` | Execute single tool |
| `/api/admin/ai-tools/execute-parallel` | Execute multiple tools concurrently |
| `/api/admin/ai-tools/process-streaming` | Process AI streaming tool calls |
| `/api/admin/ai-tools/reset-circuit-breaker` | Reset circuit breaker |
