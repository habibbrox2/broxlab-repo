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
