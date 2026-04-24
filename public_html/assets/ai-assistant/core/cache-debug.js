/**
 * BroxLab AI Assistant - Cache Utilities and Debugging
 * Provides helper functions for cache management and debugging
 */

import { getModelCache } from './cache.js';

/**
 * Cache debugging utilities
 */
export const cacheDebug = {
    /**
     * Get current cache state
     */
    getState() {
        const cache = getModelCache();
        return cache.export();
    },

    /**
     * Log cache statistics to console
     */
    logStats() {
        const cache = getModelCache();
        const stats = cache.getStats();

        console.group('🔹 Cache Statistics');
        console.log(`Total cached providers: ${stats.total}`);

        stats.entries.forEach(entry => {
            const status = entry.isExpired ? '❌ EXPIRED' : '✅ VALID';
            const ttlText = entry.ttlMinutes > 0
                ? `${entry.ttlMinutes}min`
                : 'expired';

            console.log(`
  ${entry.provider} [${status}]
    • Models: ${entry.modelCount}
    • Cached: ${new Date(entry.cachedAt).toLocaleString()}
    • TTL: ${ttlText}
    • Cache Hits: ${entry.hits}
      `);
        });
        console.groupEnd();
    },

    /**
     * Clear all cache
     */
    clearAll() {
        const cache = getModelCache();
        cache.clear();
        console.log('✓ All cache cleared');
    },

    /**
     * Clear specific provider cache
     */
    clear(provider) {
        const cache = getModelCache();
        cache.clear(provider);
        console.log(`✓ Cache cleared for ${provider}`);
    },

    /**
     * Force refresh cache for provider
     */
    async refresh(provider) {
        const cache = getModelCache();
        try {
            const result = await cache.fetch(provider, { forceRefresh: true });
            console.log(`✓ Refreshed cache for ${provider}: ${result.models.length} models`);
            return result;
        } catch (err) {
            console.error(`✗ Failed to refresh ${provider}:`, err.message);
            return null;
        }
    },

    /**
     * Compare cache vs API
     */
    async compare(provider) {
        const cache = getModelCache();

        // Get cached models
        const cached = cache.get(provider);

        // Fetch fresh from API
        try {
            const result = await cache.fetchFromAPI(provider, { skipCache: true });
            const fresh = result.models || [];

            console.group(`📊 Cache Comparison for ${provider}`);
            console.log(`Cached: ${cached?.length || 0} models`);
            console.log(`Fresh: ${fresh.length} models`);

            if (cached && fresh) {
                const cachedIds = new Set(cached.map(m => m.id));
                const freshIds = new Set(fresh.map(m => m.id));

                const added = [...freshIds].filter(id => !cachedIds.has(id));
                const removed = [...cachedIds].filter(id => !freshIds.has(id));

                if (added.length > 0) {
                    console.log(`Added (${added.length}):`, added.slice(0, 3));
                }
                if (removed.length > 0) {
                    console.log(`Removed (${removed.length}):`, removed.slice(0, 3));
                }
                if (added.length === 0 && removed.length === 0) {
                    console.log('✓ Cache is up to date');
                }
            }
            console.groupEnd();

            return { cached, fresh };
        } catch (err) {
            console.error('Failed to compare:', err.message);
            return null;
        }
    },

    /**
     * Export cache as JSON
     */
    export() {
        const cache = getModelCache();
        const data = cache.export();
        const json = JSON.stringify(data, null, 2);

        console.log('Cache exported (copy below):');
        console.log(json);

        return json;
    },

    /**
     * Import cache from JSON
     */
    import(jsonStr) {
        try {
            const cache = getModelCache();
            const data = JSON.parse(jsonStr);

            if (data.data) {
                Object.entries(data.data).forEach(([key, entry]) => {
                    cache.cache.set(key, entry);
                });
                cache.saveToStorage();
                console.log('✓ Cache imported successfully');
            }
        } catch (err) {
            console.error('✗ Failed to import cache:', err.message);
        }
    },

    /**
     * Get cache size in KB
     */
    getSize() {
        try {
            const cache = getModelCache();
            const json = JSON.stringify(cache.export());
            const bytes = new Blob([json]).size;
            const kb = (bytes / 1024).toFixed(2);
            console.log(`Cache size: ${kb} KB`);
            return kb;
        } catch (err) {
            console.error('Failed to get cache size:', err.message);
            return 0;
        }
    },

    /**
     * Monitor cache performance
     */
    monitorPerformance() {
        const cache = getModelCache();
        const stats = cache.getStats();

        console.group('📈 Performance Analysis');

        let totalHits = 0;
        stats.entries.forEach(entry => {
            totalHits += entry.hits || 0;
        });

        console.log(`Total cache hits: ${totalHits}`);
        console.log(`Estimated API calls saved: ${totalHits}`);
        console.log(`Estimated time saved: ~${(totalHits * 0.45).toFixed(1)}s`);

        console.groupEnd();
    },

    /**
     * Setup real-time monitoring
     */
    startMonitoring(interval = 30000) {
        console.log('📡 Starting cache monitoring (every 30s)');

        const monitor = setInterval(() => {
            const cache = getModelCache();
            const stats = cache.getStats();

            stats.entries.forEach(entry => {
                const warn = entry.isExpired ? '⚠️' : '✓';
                console.log(`${warn} ${entry.provider}: ${entry.hits} hits, TTL: ${entry.ttlMinutes}min`);
            });
        }, interval);

        return () => {
            clearInterval(monitor);
            console.log('Monitoring stopped');
        };
    },
};

/**
 * Expose cache debug utilities to window for console access
 */
if (typeof window !== 'undefined') {
    window.__cacheDebug = cacheDebug;
    console.log('💡 Cache debug available at window.__cacheDebug');
}

/**
 * Cache performance tracker
 */
export class CachePerformanceTracker {
    constructor() {
        this.metrics = {
            cacheHits: 0,
            cacheMisses: 0,
            apiCalls: 0,
            totalTime: 0,
        };
    }

    recordHit() {
        this.metrics.cacheHits++;
    }

    recordMiss() {
        this.metrics.cacheMisses++;
        this.metrics.apiCalls++;
    }

    recordTime(ms) {
        this.metrics.totalTime += ms;
    }

    getMetrics() {
        const total = this.metrics.cacheHits + this.metrics.cacheMisses;
        const hitRate = total > 0 ? ((this.metrics.cacheHits / total) * 100).toFixed(2) : 0;

        return {
            ...this.metrics,
            total,
            hitRate: `${hitRate}%`,
            averageTime: total > 0 ? (this.metrics.totalTime / total).toFixed(2) : 0,
        };
    }

    reset() {
        this.metrics = {
            cacheHits: 0,
            cacheMisses: 0,
            apiCalls: 0,
            totalTime: 0,
        };
    }

    printReport() {
        const metrics = this.getMetrics();
        console.group('📊 Cache Performance Report');
        console.table(metrics);
        console.groupEnd();
    }
}

/**
 * Cache batch operations helper
 */
export async function batchFetchWithProgress(providers, options = {}) {
    const cache = getModelCache();
    const results = {};
    let completed = 0;

    const progressCallback = options.onProgress || (() => { });

    for (const provider of providers) {
        try {
            results[provider] = await cache.fetch(provider, options);
            completed++;
            progressCallback({
                provider,
                completed,
                total: providers.length,
                percentage: Math.round((completed / providers.length) * 100),
            });
        } catch (err) {
            console.warn(`Failed to fetch ${provider}:`, err.message);
            results[provider] = { error: err.message, models: [] };
        }
    }

    return results;
}

/**
 * Cache warming strategy
 */
export async function warmCache(providers, options = {}) {
    console.log('🔥 Warming cache for:', providers);

    return batchFetchWithProgress(providers, {
        ...options,
        skipCache: true,
        onProgress: (progress) => {
            console.debug(`Warming... ${progress.completed}/${progress.total} (${progress.percentage}%)`);
        },
    });
}

/**
 * Get cache recommendations
 */
export function getCacheRecommendations() {
    const cache = getModelCache();
    const stats = cache.getStats();
    const recommendations = [];

    stats.entries.forEach(entry => {
        if (entry.isExpired) {
            recommendations.push({
                type: 'warning',
                provider: entry.provider,
                message: 'Cache expired, will be refreshed on next access',
            });
        }

        if (entry.ttlMinutes < 60) {
            recommendations.push({
                type: 'info',
                provider: entry.provider,
                message: `Cache expiring soon (${entry.ttlMinutes}min)`,
            });
        }

        if (entry.hits < 1) {
            recommendations.push({
                type: 'debug',
                provider: entry.provider,
                message: 'Low cache hits, consider prefetching',
            });
        }
    });

    if (stats.total === 0) {
        recommendations.push({
            type: 'info',
            message: 'No cached models, prefetch to improve performance',
        });
    }

    return recommendations;
}
