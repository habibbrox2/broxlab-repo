/**
 * BroxLab AI Assistant - Cache System Examples and Tests
 * Demonstrates cache usage patterns and testing scenarios
 * 
 * Note: This file is documentation with examples. Not all imports or functions
 * are used as intended for reference purposes.
 * 
 * eslint-disable
 */


// Import cache modules
// import { getModelCache, initializeModelCache } from '../../core/cache.js';
// import { cacheDebug, CachePerformanceTracker, warmCache } from '../../core/cache-debug.js';

/**
 * Example 1: Basic Cache Usage
 */
async function example1_basicUsage() {
  console.log('\n=== Example 1: Basic Cache Usage ===');

  // This is done automatically in app init
  // initializeModelCache(['openrouter']);

  // Later, when you need models
  const cache = getModelCache();
  const result = await cache.fetch('openrouter');

  console.log('Models:', result.models);
  console.log('From cache:', result.fromCache);
  console.log('Loaded at:', result.fetchedAt);
}

/**
 * Example 2: Force Refresh
 */
async function example2_forceRefresh() {
  console.log('\n=== Example 2: Force Refresh ===');

  const cache = getModelCache();

  // Skip cache and get fresh data
  const result = await cache.fetch('openrouter', {
    forceRefresh: true,
  });

  console.log('Fresh models count:', result.models.length);
}

/**
 * Example 3: Batch Operations
 */
async function example3_batchFetch() {
  console.log('\n=== Example 3: Batch Fetch ===');

  const cache = getModelCache();

  // Fetch multiple providers at once
  const results = await cache.fetchBatch(['openrouter', 'puter-js',]);

  Object.entries(results).forEach(([provider, result,]) => {
    console.log(`${provider}: ${result.models?.length || 0} models (cached: ${result.fromCache})`);
  });
}

/**
 * Example 4: Prefetching
 */
async function example4_prefetch() {
  console.log('\n=== Example 4: Prefetch Models ===');

  const cache = getModelCache();

  // Prefetch in background
  await cache.prefetch(['openrouter', 'puter-js',]);

  console.log('Prefetch complete');
}

/**
 * Example 5: Cache Statistics
 */
function example5_statistics() {
  console.log('\n=== Example 5: Cache Statistics ===');

  const cache = getModelCache();
  const stats = cache.getStats();

  console.log('Total cached providers:', stats.total);
  stats.entries.forEach(entry => {
    console.log(`
  ${entry.provider}:
    - Models: ${entry.modelCount}
    - Hits: ${entry.hits}
    - TTL: ${entry.ttlMinutes}min
    - Expired: ${entry.isExpired}
    `);
  });
}

/**
 * Example 6: Cache Clearing
 */
function example6_clearCache() {
  console.log('\n=== Example 6: Clear Cache ===');

  const cache = getModelCache();

  // Clear specific provider
  cache.clear('openrouter');
  console.log('Cleared openrouter cache');

  // Clear all
  cache.clear();
  console.log('Cleared all cache');
}

/**
 * Example 7: Performance Tracking
 */
async function example7_performanceTracking() {
  console.log('\n=== Example 7: Performance Tracking ===');

  const tracker = new CachePerformanceTracker();
  const cache = getModelCache();

  // Simulate operations
  const startTime = performance.now();
  const result1 = await cache.fetch('openrouter');
  tracker.recordTime(performance.now() - startTime);
  result1.fromCache ? tracker.recordHit() : tracker.recordMiss();

  // Second request should be cached
  const startTime2 = performance.now();
  const result2 = await cache.fetch('openrouter');
  tracker.recordTime(performance.now() - startTime2);
  result2.fromCache ? tracker.recordHit() : tracker.recordMiss();

  tracker.printReport();
}

/**
 * Example 8: Using Debug Utilities
 */
async function example8_debugUtilities() {
  console.log('\n=== Example 8: Debug Utilities ===');

  // Log statistics
  cacheDebug.logStats();

  // Get cache size
  const sizeKB = cacheDebug.getSize();
  console.log(`Cache size: ${sizeKB} KB`);

  // Monitor performance
  cacheDebug.monitorPerformance();

  // Get current state
  const state = cacheDebug.getState();
  console.log('Cache state:', state);
}

/**
 * Example 9: Error Handling
 */
async function example9_errorHandling() {
  console.log('\n=== Example 9: Error Handling ===');

  const cache = getModelCache();

  try {
    // This will use fallback if API fails
    const result = await cache.fetch('invalid-provider');
    console.log('Result:', result);
  } catch (err) {
    console.error('Error:', err.message);
  }
}

/**
 * Example 10: Real-time Monitoring
 */
function example10_monitoring() {
  console.log('\n=== Example 10: Real-time Monitoring ===');

  // Start monitoring every 10 seconds
  const stopMonitoring = cacheDebug.startMonitoring(10000);

  // Stop after 1 minute
  setTimeout(() => {
    stopMonitoring();
  }, 60000);
}

/**
 * Console Commands Reference
 *
 * Copy and paste these commands in browser console for quick testing:
 *
 * // Get cache state
 * window.__cacheDebug.getState()
 *
 * // Log statistics
 * window.__cacheDebug.logStats()
 *
 * // Get cache size
 * window.__cacheDebug.getSize()
 *
 * // Force refresh
 * window.__cacheDebug.refresh('openrouter')
 *
 * // Clear all cache
 * window.__cacheDebug.clearAll()
 *
 * // Compare cache vs API
 * window.__cacheDebug.compare('openrouter')
 *
 * // Export cache as JSON
 * window.__cacheDebug.export()
 *
 * // Monitor performance
 * window.__cacheDebug.monitorPerformance()
 *
 * // Start monitoring (every 30s)
 * window.__cacheDebug.startMonitoring()
 */

/**
 * Test Suite
 */
async function runAllTests() {
  console.log('🚀 Running Cache System Tests\n');

  try {
    await example1_basicUsage();
    await example2_forceRefresh();
    await example3_batchFetch();
    await example4_prefetch();
    example5_statistics();
    example6_clearCache();
    await example7_performanceTracking();
    await example8_debugUtilities();
    await example9_errorHandling();

    console.log('\n✅ All tests completed');
  } catch (err) {
    console.error('❌ Test failed:', err);
  }
}

/**
 * Performance Benchmark
 */
async function benchmarkCache() {
  console.log('📊 Cache Performance Benchmark\n');

  const cache = getModelCache();
  const iterations = 10;
  const provider = 'openrouter';

  // Benchmark: First fetch (cache miss)
  console.time('First fetch (cache miss)');
  const result1 = await cache.fetch(provider, { forceRefresh: true, });
  console.timeEnd('First fetch (cache miss)');

  // Benchmark: Subsequent fetches (cache hits)
  const times = [];
  for (let i = 0; i < iterations; i++) {
    console.time(`Fetch ${i + 1} (cache hit)`);
    const result = await cache.fetch(provider);
    console.timeEnd(`Fetch ${i + 1} (cache hit)`);
  }

  console.log('\n✅ Cache significantly faster for repeated access!');
}

// Export for testing
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    example1_basicUsage,
    example2_forceRefresh,
    example3_batchFetch,
    example4_prefetch,
    example5_statistics,
    example6_clearCache,
    example7_performanceTracking,
    example8_debugUtilities,
    example9_errorHandling,
    example10_monitoring,
    runAllTests,
    benchmarkCache,
  };
}

/**
 * Quick Start Commands
 *
 * Run in browser console:
 *
 * // Check if cache is working
 * const cache = getModelCache();
 * console.log('Cache ready:', cache !== null);
 *
 * // Load and time a request
 * console.time('Model Load');
 * const result = await cache.fetch('openrouter');
 * console.timeEnd('Model Load');
 * console.log('Result:', result);
 */
