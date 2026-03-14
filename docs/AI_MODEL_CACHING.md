# AI Model Caching System Documentation

## Overview

The AI model caching system is designed to improve loading performance and chat experience by caching model metadata, responses, and provider information. This system reduces API calls, speeds up model loading, and provides a smoother user experience.

## Architecture

### EnhancedCache Class
- **Location**: `app/Modules/AISystem/EnhancedCache.php`
- **Purpose**: Advanced caching with intelligent invalidation and multi-layer caching
- **Features**:
  - Model metadata caching
  - Response caching
  - Intelligent cache size management
  - TTL-based expiration
  - Cache statistics

### AgentClient Enhancements
- **Location**: `app/Modules/AISystem/AgentClient.php`
- **Purpose**: Enhanced AI client with caching capabilities
- **Features**:
  - Model availability caching
  - Response caching
  - Context-aware caching
  - Cache statistics

## Cache Types

### 1. Model Metadata Cache
- **Purpose**: Cache model information from providers
- **TTL**: 30 minutes (1800 seconds)
- **Storage**: `storage/cache/ai-models/`
- **Data**: Model capabilities, parameters, descriptions, tags

### 2. Response Cache
- **Purpose**: Cache AI responses
- **TTL**: 1 hour (3600 seconds)
- **Storage**: `storage/cache/ai-responses/`
- **Data**: Complete AI responses with metadata

### 3. Chat Cache
- **Purpose**: Cache chat responses for quick retrieval
- **TTL**: 1 hour (3600 seconds)
- **Storage**: `storage/cache/chat/`
- **Data**: Chat responses keyed by message content

## API Endpoints

### Model Management
- `GET /api/ai/models/list` - Get available models with caching
- `GET /api/ai/models/info` - Get specific model information

### Cache Management
- `POST /api/ai/cache/clear` - Clear cache (all/models/chat)
- `GET /api/ai/cache/stats` - Get cache statistics

### AI Testing
- `POST /api/ai/test` - Test AI connection with model caching

## Cache Operations

### Clearing Cache
```php
// Clear all cache
$agent->clearAllCache();

// Clear model cache for specific provider
$agent->clearProviderCache('openrouter');

// Clear chat cache only
$agent->clearChatCache();
```

### Getting Cache Stats
```php
$stats = $agent->getCacheStats();
echo json_encode($stats);
```

## Performance Benefits

### Reduced API Calls
- Model metadata fetched once every 30 minutes
- Responses cached for 1 hour
- Chat responses cached for 1 hour

### Faster Loading
- Model information available instantly from cache
- Responses served from local storage
- Reduced network latency

### Improved User Experience
- Faster response times
- Consistent performance
- Better handling of rate limits

## Cache Invalidation

### Automatic Invalidation
- TTL-based expiration
- Cache size management
- Manual clearing via API

### Manual Invalidation
- Clear cache for specific provider
- Clear all cache
- Clear specific cache types

## Configuration

### Cache Settings
- **Model TTL**: 1800 seconds (30 minutes)
- **Response TTL**: 3600 seconds (1 hour)
- **Max Cache Size**: 100 items
- **Cache Directory**: `storage/cache/`

### Provider-Specific Settings
- **Ollama**: 60-second TTL for model metadata
- **Other providers**: 1800-second TTL

## Usage Examples

### Basic Usage
```php
$agent = new AgentClient($mysqli);
$response = $agent->chat($messages);
```

### With Context
```php
$agent = new AgentClient($mysqli);
$response = $agent->chatWithContext($messages, null, null, $context);
```

### Cache Management
```php
// Get cache stats
$stats = $agent->getCacheStats();

// Clear cache
$agent->clearAllCache();
```

## Troubleshooting

### Cache Not Working
1. Check cache directory permissions
2. Verify cache directory exists
3. Check TTL settings

### Cache Too Large
1. Adjust max cache size
2. Clear cache manually
3. Check cache cleanup

### Performance Issues
1. Check cache hit rates
2. Verify TTL settings
3. Monitor cache size

## Monitoring

### Cache Statistics
- Model cache count
- Response cache count
- Cache sizes
- Hit/miss rates

### Performance Metrics
- Response times
- Cache hit rates
- API call reduction

## Best Practices

### Cache Configuration
- Set appropriate TTL values
- Monitor cache sizes
- Adjust based on usage patterns

### Cache Usage
- Use context-aware caching
- Clear cache when models change
- Monitor performance

### Maintenance
- Regular cache cleanup
- Monitor cache health
- Adjust settings as needed

## Future Enhancements

### Planned Features
- Distributed caching
- Cache warming
- Advanced invalidation strategies
- Performance analytics

### Roadmap
- Enhanced cache statistics
- Better cache management
- Improved performance monitoring
- Advanced caching strategies

#### Get Cache Statistics
```
GET /api/ai/cache/stats
```
- Shows cache usage and performance metrics

### 4. Enhanced AgentClient Methods

#### `getAvailableModels($provider)`
- Returns cached models for provider
- Automatically refreshes cache when expired
- Fallback to remote fetch if needed

#### `chatWithContext($messages, $provider, $model, $context)`
- Enhanced chat with context awareness
- Separate cache keys for different contexts
- Better performance for repeated conversations

#### `getCacheStats()`
- Returns cache usage statistics
- Shows file counts and sizes
- Helps monitor system performance

## Performance Benefits

1. **Faster Model Loading**: Models are cached for 30 minutes, reducing API calls
2. **Improved Chat Speed**: Responses are cached for 1 hour, eliminating repeated processing
3. **Reduced API Costs**: Fewer API calls to external providers
4. **Better User Experience**: Faster response times and smoother interactions
5. **Automatic Management**: Cache expires automatically, ensuring fresh data

## Cache Structure

### Model Cache Files
```
storage/cache/ai-models/models_{hash}.json
```
Contains:
- `fetched_at`: Timestamp when models were fetched
- `models`: Array of model IDs and labels
- `ttl`: Time-to-live in seconds

### Chat Cache Files
```
storage/cache/chat/{hash}.json
```
Contains:
- Cached response data
- Usage statistics
- Metadata for debugging

## Usage Examples

### Get Available Models
```php
$agent = new AgentClient($mysqli);
$models = $agent->getAvailableModels('openrouter');
echo json_encode($models);
```

### Chat with Context
```php
$messages = [
    ['role' => 'system', 'content' => 'You are a helpful assistant'],
    ['role' => 'user', 'content' => 'What is AI?']
];

$context = [
    ['role' => 'system', 'content' => 'You are an expert in technology']
];

$response = $agent->chatWithContext($messages, 'openrouter', 'gpt-4o', $context);
```

### Clear Cache
```php
$agent = new AgentClient($mysqli);
$agent->clearProviderCache('openrouter'); // Clear model cache for specific provider
$agent->clearAllCache(); // Clear all cache
```

## Cache Monitoring

### Check Cache Status
```php
$stats = $agent->getCacheStats();
echo "Chat cache files: " . $stats['chat_cache']['files'];
echo "Model cache files: " . $stats['model_cache']['files'];
```

### Cache Size Limits
- **Maximum Files**: No hard limit, but old files are automatically cleaned
- **Storage Usage**: Monitored through stats API
- **Cleanup**: Automatic when cache expires

## Troubleshooting

### Cache Not Updating
- Check if cache directory is writable
- Verify API keys are configured correctly
- Check network connectivity for remote fetches

### Models Not Available
- Clear model cache: `POST /api/ai/cache/clear?type=models`
- Check API provider status
- Verify provider configuration

### Performance Issues
- Monitor cache statistics
- Check cache hit rates
- Adjust TTL values if needed

## Configuration

### Cache TTL Settings
- **Model Cache**: 1800 seconds (30 minutes)
- **Chat Cache**: 3600 seconds (1 hour)
- **Adjustable**: Can be modified in Cache class

### Cache Directory
- **Default**: `storage/cache/`
- **Writable**: Must have write permissions
- **Structure**: Automatically created if missing

## Best Practices

1. **Use Context**: Always provide context for better caching
2. **Monitor Stats**: Regularly check cache performance
3. **Clear When Needed**: Clear cache after provider updates
4. **Test Performance**: Monitor response times
5. **Backup**: Consider backing up cache directory periodically

## Future Enhancements

- **Distributed Caching**: Support for Redis or Memcached
- **Cache Warming**: Pre-fetch models during low traffic
- **Analytics**: Detailed cache hit/miss analytics
- **Compression**: Compress cache files for better storage efficiency