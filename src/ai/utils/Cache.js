/**
 * AI Service Cache
 * 
 * Simple in-memory cache with TTL support for AI responses
 * Can be replaced with Redis for distributed caching
 */

class Cache {
    constructor(options = {}) {
        this.store = new Map();
        this.ttl = options.ttl || 3600; // Default 1 hour
        this.maxSize = options.maxSize || 1000;
    }

    /**
     * Generate cache key from request
     */
    generateKey(provider, model, messages, options = {}) {
        const data = {
            provider,
            model,
            messages: messages.map(m => ({
                role: m.role,
                content: typeof m.content === 'string'
                    ? m.content
                    : m.content.substring?.(0, 100) || '[complex]',
            })),
            temperature: options.temperature,
            maxTokens: options.maxTokens,
        };

        // Simple hash - in production use crypto
        const str = JSON.stringify(data);
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }

        return `ai:${provider}:${model}:${Math.abs(hash).toString(36)}`;
    }

    /**
     * Get item from cache
     */
    get(key) {
        const item = this.store.get(key);

        if (!item) {
            return null;
        }

        // Check TTL
        if (Date.now() > item.expiresAt) {
            this.store.delete(key);
            return null;
        }

        return item.value;
    }

    /**
     * Set item in cache
     */
    set(key, value, customTtl = null) {
        // Evict oldest if at capacity
        if (this.store.size >= this.maxSize) {
            const firstKey = this.store.keys().next().value;
            this.store.delete(firstKey);
        }

        const ttl = customTtl || this.ttl;
        this.store.set(key, {
            value,
            expiresAt: Date.now() + (ttl * 1000),
            createdAt: Date.now(),
        });
    }

    /**
     * Delete item from cache
     */
    delete(key) {
        return this.store.delete(key);
    }

    /**
     * Clear all items
     */
    clear() {
        this.store.clear();
    }

    /**
     * Clear by prefix
     */
    clearPrefix(prefix) {
        for (const key of this.store.keys()) {
            if (key.startsWith(prefix)) {
                this.store.delete(key);
            }
        }
    }

    /**
     * Get cache stats
     */
    getStats() {
        let valid = 0;
        const now = Date.now();

        for (const item of this.store.values()) {
            if (now <= item.expiresAt) {
                valid++;
            }
        }

        return {
            total: this.store.size,
            valid,
            expired: this.store.size - valid,
            maxSize: this.maxSize,
        };
    }
}

// Export singleton instance
const cache = new Cache();

export default cache;
export { Cache };