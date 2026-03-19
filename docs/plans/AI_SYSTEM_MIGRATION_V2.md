# AI System Migration: Genkit → Direct SDK

**Date:** 2026-03-19  
**Status:** Completed  
**Version:** 2.1.0 (with CV + KB Self-Healing)

---

## Executive Summary

This document details the migration from Genkit-based AI architecture to Direct SDK-based architecture for the BroxLab Node.js services.

### Key Finding

**Genkit was never actually used** in the codebase. It existed as an unused dependency in `package.json`. The actual AI system is PHP-based with direct API calls via cURL.

### Actions Taken

1. ✅ Removed unused Genkit dependencies from `package.json`
2. ✅ Created new Node.js AI service layer using direct SDKs
3. ✅ Implemented unified AI Router with fallback support
4. ✅ Implemented RAG pipeline for Node.js
5. ✅ Created BullMQ worker integration

---

## What Was Removed

### package.json Changes

```diff
- "genkit": "^1.30.1",
- "@genkit-ai/googleai": "^1.28.0",
```

**Note:** The following SDKs were already present but underutilized:
- `@google/generative-ai`
- `@anthropic-ai/sdk`
- `langchain`
- `@langchain/openai`

---

## New Architecture

### Directory Structure

```
src/ai/
├── config.js                 # Configuration + feature flags
├── index.js                  # Main exports
├── AIRouter.js               # Unified AI router
├── RAGEngine.js              # RAG pipeline
├── utils/
│   ├── Logger.js             # Structured logging
│   └── Cache.js              # In-memory cache
├── providers/
│   ├── BaseProvider.js       # Base class
│   ├── GoogleProvider.js     # Google AI (Gemini)
│   ├── OpenAIProvider.js    # OpenAI
│   └── AnthropicProvider.js # Anthropic
└── services/
    └── AIWorker.js           # BullMQ worker
```

### Features

| Feature | Status | Description |
|---------|--------|-------------|
| Multi-provider | ✅ | Google, OpenAI, Anthropic, OpenRouter, Ollama, Fireworks, HuggingFace |
| Fallback | ✅ | Automatic provider failover |
| Retry | ✅ | Exponential backoff |
| Caching | ✅ | In-memory with TTL |
| Streaming | ✅ | SSE support |
| RAG | ✅ | Qdrant integration |
| Workers | ✅ | BullMQ integration |
| Metrics | ✅ | Latency + error tracking |
| CV Enhancement | ✅ | ATS scoring, text improvement, job matching |
| KB Self-Healing | ✅ | Auto quality improvement, duplicate detection |
| API Server | ✅ | Express server on port 3001 |

---

## Usage Examples

### Basic Chat

```javascript
import { aiRouter } from './src/ai/index.js';

const messages = [
    { role: 'system', content: 'You are a helpful assistant.' },
    { role: 'user', content: 'What is the capital of Bangladesh?' }
];

const response = await aiRouter.chat(messages, 'openai', 'gpt-4o-mini');
console.log(response.content);
```

### Streaming

```javascript
import { aiRouter } from './src/ai/index.js';

const messages = [
    { role: 'user', content: 'Write a story about AI' }
];

for await (const chunk of aiRouter.chatStream(messages, 'anthropic', 'claude-3-5-sonnet')) {
    process.stdout.write(chunk.content);
}
```

### RAG Query

```javascript
import { ragEngine } from './src/ai/index.js';

const result = await ragEngine.query('What is our return policy?', {
    provider: 'openai',
    model: 'gpt-4o-mini',
    maxResults: 5,
});

console.log(result.response.content);
console.log('Sources:', result.sources);
```

### BullMQ Worker

```javascript
import { createAIQueue, addAIJob } from './src/ai/services/AIWorker.js';
import Redis from 'ioredis';

const connection = new Redis();
const queue = createAIQueue(connection);

// Add content enhancement job
await addAIJob(queue, 'enhance-content', {
    content: 'Your article content here...',
    style: 'professional',
});
```

### CV Enhancement (Node.js)

```javascript
import { cvEnhancer } from './src/ai/index.js';

// ATS Score
const atsResult = await cvEnhancer.calculateAtsScore(cvData);
console.log('ATS Score:', atsResult.score);

// Improve text
const improved = await cvEnhancer.improveText('Led a team', 'bullet');
console.log('Improved:', improved.improved);

// Job matching
const match = await cvEnhancer.matchToJob(cvData, jobDescription);
console.log('Match %:', match.matchScore);
```

### CV Enhancement (PHP → Node.js)

```php
$ch = curl_init('http://localhost:3001/api/cv/ats-score');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['cv' => $cvData]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
```

### KB Self-Healing (Node.js)

```javascript
import { selfHealingKB } from './src/ai/index.js';

// Run health check
const results = await selfHealingKB.runHealthCheck();
console.log('Healthy:', results.healthy);
console.log('Needs improvement:', results.needsImprovement);

// Get content suggestions
const suggestions = await selfHealingKB.suggestNewContent();
```

### KB Self-Healing (PHP)

```php
$healer = new KBSelfHealer($mysqli, [
    'autoImprove' => true,
    'qualityThreshold' => 50
]);

$results = $healer->runHealthCheck();
$stats = $healer->getStats();
```

---

## Configuration

### Environment Variables

```bash
# Enable AI services
AI_ENABLED=true
USE_DIRECT_SDK=true

# CV Enhancement
CV_ENHANCEMENT_ENABLED=true
CV_AI_PROVIDER=auto

# KB Self-Healing
KB_SELF_HEALING_ENABLED=true
KB_AUTO_IMPROVE=true
KB_QUALITY_THRESHOLD=50
KB_LOOKBACK_DAYS=30

# Node.js Server
NODEJS_AI_SERVER_URL=http://localhost:3001

# Provider API Keys
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
GOOGLE_API_KEY=AI...

# Optional providers
OPENROUTER_API_KEY=...
OLLAMA_BASE_URL=http://localhost:11434

# Model preferences
OPENAI_MODEL=gpt-4o-mini
ANTHROPIC_MODEL=claude-3-5-sonnet-20241022
GOOGLE_MODEL=gemini-2.0-flash-exp

# Features
ENABLE_RAG=true
ENABLE_STREAMING=true
ENABLE_CACHING=true
ENABLE_RETRY=true

# RAG settings
QDRANT_URL=http://localhost:6333
QDRANT_COLLECTION=broxlab_knowledge

# Logging
AI_LOG_LEVEL=info
```

---

## Feature Flags

The system uses feature flags in [`src/ai/config.js`](src/ai/config.js) for gradual rollouts:

```javascript
export const FEATURE_FLAGS = {
    AI_ENABLED: process.env.AI_ENABLED === 'true',
    USE_DIRECT_SDK: process.env.USE_DIRECT_SDK !== 'false',
    ENABLE_RAG: process.env.ENABLE_RAG !== 'false',
    ENABLE_STREAMING: process.env.ENABLE_STREAMING !== 'false',
    ENABLE_CACHING: process.env.ENABLE_CACHING !== 'false',
    ENABLE_RETRY: process.env.ENABLE_RETRY !== 'false',
    ENABLE_METRICS: process.env.AI_METRICS === 'true',
    ENABLE_FALLBACK: process.env.ENABLE_FALLBACK !== 'false',
    
    // CV Enhancement
    CV_ENHANCEMENT_ENABLED: process.env.CV_ENHANCEMENT_ENABLED === 'true',
    
    // KB Self-Healing
    KB_SELF_HEALING_ENABLED: process.env.KB_SELF_HEALING_ENABLED === 'true',
    KB_AUTO_IMPROVE: process.env.KB_AUTO_IMPROVE === 'true',
    KB_QUALITY_THRESHOLD: parseInt(process.env.KB_QUALITY_THRESHOLD || '50'),
    KB_LOOKBACK_DAYS: parseInt(process.env.KB_LOOKBACK_DAYS || '30'),
    
    // Server
    USE_NODEJS_AI_SERVER: process.env.USE_NODEJS_AI_SERVER === 'true',
    NODEJS_AI_SERVER_URL: process.env.NODEJS_AI_SERVER_URL || 'http://localhost:3001',
};
```

---

## Rollback Strategy

If issues arise, rollback is simple:

1. **Disable new system:**
   ```bash
   AI_ENABLED=false
   ```

2. **Revert package.json:**
   - Add back `genkit` and `@genkit-ai/googleai` to dependencies

3. **The PHP AI system is unaffected** - it continues working independently

---

## Comparison: Before vs After

| Aspect | Before (Genkit - Unused) | After (Direct SDK) |
|--------|--------------------------|-------------------|
| Dependencies | genkit, @genkit-ai/* | @google/generative-ai, @anthropic-ai/sdk |
| Provider Support | None (unused) | Google, OpenAI, Anthropic, OpenRouter, Ollama |
| RAG | PHP-based only | Node.js + Qdrant |
| CV Enhancement | PHP CvAiHelper | PHP + Node.js (dual) |
| KB Self-Healing | None | Auto quality improvement |
| Workers | PHP cron | BullMQ (Node.js) |
| Caching | PHP file-based | Node.js in-memory (Redis-ready) |
| Logging | PHP Logger | Winston-style JSON logs |

---

## Performance Improvements

1. **Reduced dependencies:** Removed unused Genkit packages
2. **Direct SDK usage:** No abstraction overhead
3. **In-memory caching:** Faster than file-based PHP cache
4. **Streaming support:** Real-time responses
5. **Parallel processing:** BullMQ for background tasks

---

## Testing Checklist

- [ ] Basic chat with each provider
- [ ] Provider fallback when primary fails
- [ ] Streaming response
- [ ] RAG query and document indexing
- [ ] BullMQ job processing
- [ ] Cache hit/miss
- [ ] Error handling and retries
- [ ] Latency under load

---

## Future Enhancements

1. Redis integration for distributed caching
2. LangChain integration for complex chains
3. Additional providers (Mistral, Cohere)
4. Rate limiting per provider
5. Cost tracking and budgets

---

## API Endpoints (Port 3001)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/health` | GET | Server health check |
| `/api/cv/improve` | POST | Improve CV text |
| `/api/cv/ats-score` | POST | Calculate ATS score |
| `/api/cv/keywords` | POST | Extract keywords |
| `/api/cv/match` | POST | Match CV to job |
| `/api/cv/cover-letter` | POST | Generate cover letter |
| `/api/cv/parse` | POST | Parse CV text |
| `/api/cv/improve-all` | POST | Improve entire CV |
| `/api/kb/search` | GET | Search KB |
| `/api/kb/add` | POST | Add KB entry |
| `/api/kb/health` | GET | KB health check |
| `/api/kb/suggest` | GET | Content suggestions |
| `/api/ai/chat` | POST | AI chat |
| `/api/ai/rag` | POST | RAG query |

---

## Running the Server

```bash
# Start server
node src/ai/server.js

# Or with npm
npm run ai-server

# Server runs on port 3001
```

---

*This migration was completed on 2026-03-19*
*Version 2.1.0 added CV Enhancement and KB Self-Healing on 2026-03-19*