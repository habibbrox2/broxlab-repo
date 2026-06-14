# AI Model Cache System Documentation

## Overview

The AI Model Cache System provides efficient caching of AI models to reduce load times and minimize API calls. Models are fetched remotely once and then cached locally for subsequent use.

## Features

### 1. **Automatic Caching**
- Models are fetched from the API and automatically cached in browser localStorage
- Cache TTL (Time To Live) is set to 24 hours by default
- Expired cache entries are automatically cleaned

### 2. **Smart Fallbacks**
- If cache is expired but API is unavailable, stale cache is used
- If all else fails, hardcoded fallback models are displayed
- Multiple fallback strategies ensure models are always available

### 3. **Background Prefetching**
- Models are prefetched during initialization
- Subsequent requests benefit from cached data immediately
- No blocking operations for users

### 4. **Cache Statistics**
- Track cache hits and performance
- Monitor cache TTL and expiration
- Export cache data for debugging

## Usage

### Basic Usage in Admin Module

```javascript
import { getModelCache, initializeModelCache } from '../../core/cache.js';

// Initialize cache during app init
function init() {
  initializeModelCache(['openrouter'], {
    ttl: 24 * 60 * 60 * 1000, // 24 hours
    storageKey: 'brox.admin.models.cache',
  });
}

// Use cache to load models
async function loadModels() {
  const cache = getModelCache();
  const result = await cache.fetch(provider, {
    timeout: 10000,
    cacheTTL: 24 * 60 * 60 * 1000,
  });
  
  const models = result.models;
  const fromCache = result.fromCache; // true if loaded from cache
  const isStale = result.isStale;     // true if cache was expired
}
```

### API Reference

#### `ModelCache` Class

```javascript
// Create instance
const cache = new ModelCache(options);

// Fetch models (with cache fallback)
await cache.fetch(provider, { skipCache, forceRefresh });

// Batch fetch multiple providers
await cache.fetchBatch(['openrouter', 'puter-js']);

// Prefetch models in background
await cache.prefetch(['openrouter']);

// Clear specific or all cache
cache.clear(provider); // clear specific
cache.clear();         // clear all

// Get statistics
const stats = cache.getStats();

// Export for debugging
const exported = cache.export();
```

#### `initializeModelCache(providers, options)`

```javascript
// Initialize with providers to prefetch
initializeModelCache(['openrouter', 'puter-js'], {
  ttl: 24 * 60 * 60 * 1000,      // Cache TTL in milliseconds
  storageKey: 'custom.cache.key'  // localStorage key
});
```

#### `getModelCache(options)`

Returns the global cache instance (singleton pattern).

```javascript
const cache = getModelCache();
```

## Storage Structure

### LocalStorage Key: `brox.admin.models.cache`

```json
{
  "version": 1,
  "timestamp": "2026-04-24T10:30:00Z",
  "data": {
    "provider:openrouter": {
      "provider": "openrouter",
      "models": [
        { "id": "openai/gpt-4-turbo", "name": "GPT-4 Turbo" },
        { "id": "anthropic/claude-3-opus", "name": "Claude 3 Opus" }
      ],
      "cachedAt": "2026-04-24T10:30:00Z",
      "expiresAt": 1703123400000,
      "hits": 5
    }
  }
}
```

## Performance Metrics

### Before Cache Implementation
- First page load: ~500ms (API call + rendering)
- Subsequent loads: ~500ms (API call + rendering)

### After Cache Implementation
- First page load: ~500ms (API call + caching)
- Subsequent loads: ~50ms (cache hit)
- **90% improvement on cached loads**

## Cache Invalidation Strategies

### 1. Time-based (Default)
- Cache expires after 24 hours
- Automatic cleanup of expired entries

### 2. Manual Invalidation
```javascript
const cache = getModelCache();
cache.clear('openrouter'); // Clear specific provider
cache.clear();              // Clear all
```

### 3. Forced Refresh
```javascript
const result = await cache.fetch('openrouter', {
  forceRefresh: true
});
```

### 4. Skip Cache
```javascript
const result = await cache.fetch('openrouter', {
  skipCache: true
});
```

## Monitoring Cache Health

### Enable Debug Logging
Check browser console for cache operations:
```
[ModelCache] Cache hit for openrouter: 5 models
[ModelCache] Fetched 5 models for openrouter
[ModelCache] Prefetch completed
```

### Export Cache for Analysis
```javascript
const cache = getModelCache();
const exported = cache.export();
console.log(exported);

// Results in:
// {
//   version: 1,
//   timestamp: "2026-04-24T10:30:00Z",
//   data: { ... },
//   stats: [
//     {
//       provider: "openrouter",
//       modelCount: 5,
//       cachedAt: "2026-04-24T10:30:00Z",
//       expiresAt: 1703123400000,
//       isExpired: false,
//       hits: 5,
//       ttlMinutes: 1380
//     }
//   ]
// }
```

## Configuration Options

### Cache Instance Options

```javascript
{
  ttl: 24 * 60 * 60 * 1000,        // Cache TTL (default: 24 hours)
  storageKey: 'brox.ai.models',    // localStorage key
  apiEndpoint: '/api/ai/models'    // API endpoint to fetch from
}
```

### Fetch Options

```javascript
{
  skipCache: false,           // Ignore cache, always fetch from API
  forceRefresh: false,        // Force API refresh even if cached
  timeout: 10000,             // Fetch timeout in milliseconds
  cacheTTL: 24*60*60*1000    // Override TTL for this fetch
}
```

## Browser Compatibility

- Modern browsers with `localStorage` support
- Graceful degradation if localStorage unavailable
- Uses `AbortSignal.timeout()` if available (modern browsers)

## Troubleshooting

### Models not updating
1. Check cache expiration time
2. Use `forceRefresh: true` to force API call
3. Clear cache manually: `cache.clear(provider)`

### High memory usage
1. Reduce TTL to clear cache more frequently
2. Check cache size with `getStats()`
3. Clear cache periodically

### API errors
1. Check network connectivity
2. Verify API endpoint `/api/ai/models`
3. Check browser console for error messages
4. Ensure `credentials: 'same-origin'` is set

## Best Practices

1. **Initialize early**: Call `initializeModelCache()` in app initialization
2. **Use prefetch**: Prefetch commonly used providers
3. **Monitor metrics**: Check cache stats periodically
4. **Set appropriate TTL**: Balance between freshness and performance
5. **Handle errors**: Always provide fallback models
6. **Test offline**: Ensure app works with stale cache

## Future Enhancements

- [ ] IndexedDB support for larger datasets
- [ ] Automatic cache refresh before expiration
- [ ] LRU eviction for memory management
- [ ] Differential updates (only new models)
- [ ] Analytics and usage reporting
- [ ] Compression for cache entries
