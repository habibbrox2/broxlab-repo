/**
 * LearningAgent
 * Tracks selector performance and learns from successes/failures
 */

import Logger from '../utils/Logger.js';

class LearningAgent {
    constructor(databaseService = null) {
        this.db = databaseService;

        // In-memory cache of selector performance
        this.selectorCache = new Map();
    }

    /**
     * Record a successful selector usage
     */
    async recordSuccess(source, field, selector) {
        Logger.debug(`Recording selector success`, { source, field, selector });

        if (this.db) {
            await this.db.recordSelectorSuccess(source, field, selector);
        }

        // Update cache
        this.updateCache(source, field, selector, true);
    }

    /**
     * Record a failed selector usage
     */
    async recordFailure(source, field, selector) {
        Logger.debug(`Recording selector failure`, { source, field, selector });

        if (this.db) {
            await this.db.recordSelectorFailure(source, field, selector);
        }

        // Update cache
        this.updateCache(source, field, selector, false);
    }

    /**
     * Update in-memory cache
     */
    updateCache(source, field, selector, success) {
        const key = `${source}:${field}`;

        if (!this.selectorCache.has(key)) {
            this.selectorCache.set(key, []);
        }

        const selectors = this.selectorCache.get(key);
        const existing = selectors.find(s => s.selector === selector);

        if (existing) {
            if (success) {
                existing.successes++;
            } else {
                existing.failures++;
            }
            existing.lastUsed = new Date();
            existing.successRate = existing.successes / (existing.successes + existing.failures);
        } else {
            selectors.push({
                selector,
                successes: success ? 1 : 0,
                failures: success ? 0 : 1,
                successRate: success ? 1 : 0,
                lastUsed: new Date()
            });
        }

        // Sort by success rate
        selectors.sort((a, b) => b.successRate - a.successRate);
    }

    /**
     * Get best selectors for a field
     */
    async getBestSelectors(source, field, limit = 5) {
        // Try database first
        if (this.db) {
            const dbSelectors = await this.db.getBestSelectors(source, field, limit);

            if (dbSelectors.length > 0) {
                return dbSelectors.map(s => ({
                    selector: s.selector,
                    successRate: s.success_rate,
                    successCount: s.success_count
                }));
            }
        }

        // Fallback to cache
        const key = `${source}:${field}`;
        const cached = this.selectorCache.get(key) || [];

        return cached.slice(0, limit).map(s => ({
            selector: s.selector,
            successRate: s.successRate,
            successCount: s.successes
        }));
    }

    /**
     * Get recommended selector order for a field
     * Combines cache, database, and default selectors
     */
    async getOptimizedSelectors(source, field, defaultSelectors = []) {
        const bestSelectors = await this.getBestSelectors(source, field);

        // Combine and deduplicate
        const selectorMap = new Map();

        // Add best selectors first (from learning)
        for (const s of bestSelectors) {
            selectorMap.set(s.selector, {
                ...s,
                source: 'learned',
                priority: 1
            });
        }

        // Add default selectors (with lower priority)
        const defaults = Array.isArray(defaultSelectors) ? defaultSelectors :
            (defaultSelectors.primary ? [defaultSelectors.primary, ...(defaultSelectors.fallback || [])] : []);

        for (const selector of defaults) {
            if (!selectorMap.has(selector)) {
                selectorMap.set(selector, {
                    selector,
                    successRate: 0.5,
                    source: 'default',
                    priority: 2
                });
            }
        }

        // Sort by priority
        const result = Array.from(selectorMap.values())
            .sort((a, b) => {
                // First by priority
                if (a.priority !== b.priority) {
                    return a.priority - b.priority;
                }
                // Then by success rate
                return b.successRate - a.successRate;
            })
            .map(s => s.selector);

        return result;
    }

    /**
     * Get statistics
     */
    getStats() {
        const stats = {
            trackedFields: this.selectorCache.size,
            selectors: {}
        };

        for (const [key, selectors] of this.selectorCache.entries()) {
            stats.selectors[key] = selectors.map(s => ({
                selector: s.selector,
                successRate: Math.round(s.successRate * 100) + '%',
                uses: s.successes + s.failures
            }));
        }

        return stats;
    }

    /**
     * Clear cache
     */
    clearCache() {
        this.selectorCache.clear();
        Logger.info('Selector cache cleared');
    }
}

export default LearningAgent;