/**
 * BroxLab AI Assistant - Caching Module
 * Provides model caching with expiration and background refresh
 */

const CACHE_VERSION = 1;
const DEFAULT_CACHE_TTL = 24 * 60 * 60 * 1000; // 24 hours

/**
 * AI Model Cache Manager
 * Handles fetching, storing, and retrieving AI models from cache
 */
export class ModelCache {
  constructor(options = {}) {
    this.ttl = options.ttl || DEFAULT_CACHE_TTL;
    this.storageKey = options.storageKey || 'brox.ai.models.cache';
    this.apiEndpoint = options.apiEndpoint || '/api/ai/models';
    this.debug = Boolean(options.debug);
    this.cache = new Map();
    this.isRefreshing = new Map();
    this.loadFromStorage();
  }

  logDebug(...args) {
    if (!this.debug) return;
    console.info(...args);
  }

  /**
     * Load cache from localStorage
     */
  loadFromStorage() {
    try {
      const stored = window.localStorage.getItem(this.storageKey);
      if (stored) {
        const data = JSON.parse(stored);
        if (data.version === CACHE_VERSION) {
          this.cache = new Map(Object.entries(data.data || {}));
          this.validateAndClean();
        }
      }
    } catch (err) {
      console.error('Failed to load cache from storage:', err);
      this.cache.clear();
    }
  }

  /**
     * Save cache to localStorage
     */
  saveToStorage() {
    try {
      const data = {
        version: CACHE_VERSION,
        timestamp: new Date().toISOString(),
        data: Object.fromEntries(this.cache),
      };
      window.localStorage.setItem(this.storageKey, JSON.stringify(data));
    } catch (err) {
      console.error('Failed to save cache to storage:', err);
    }
  }

  /**
     * Validate and clean expired cache entries
     */
  validateAndClean() {
    const now = Date.now();
    const expiredKeys = [];

    for (const [key, entry,] of this.cache.entries()) {
      if (entry.expiresAt && entry.expiresAt < now) {
        expiredKeys.push(key);
      }
    }

    if (expiredKeys.length > 0) {
      expiredKeys.forEach(key => this.cache.delete(key));
      this.saveToStorage();
    }
  }

  /**
     * Get cache key for provider
     */
  getCacheKey(provider) {
    return `provider:${provider}`;
  }

  /**
     * Get cached models for a provider
     */
  get(provider) {
    const key = this.getCacheKey(provider);
    const entry = this.cache.get(key);

    if (!entry) return null;

    // Check if expired
    if (entry.expiresAt && entry.expiresAt < Date.now()) {
      this.cache.delete(key);
      this.saveToStorage();
      return null;
    }

    return entry.models || [];
  }

  /**
     * Set cached models for a provider
     */
  set(provider, models, ttl = this.ttl) {
    const key = this.getCacheKey(provider);
    const entry = {
      provider,
      models: Array.isArray(models) ? models : [],
      cachedAt: new Date().toISOString(),
      expiresAt: Date.now() + ttl,
      hits: 0,
    };

    this.cache.set(key, entry);
    this.saveToStorage();
    return entry;
  }

  /**
     * Record cache hit for analytics
     */
  recordHit(provider) {
    const key = this.getCacheKey(provider);
    const entry = this.cache.get(key);
    if (entry) {
      entry.hits = (entry.hits || 0) + 1;
      this.saveToStorage();
    }
  }

  /**
     * Fetch models from API with cache fallback
     */
  async fetch(provider, options = {}) {
    const skipCache = options.skipCache || false;
    const forceRefresh = options.forceRefresh || false;

    // Try cache first if not forcing refresh
    if (!forceRefresh && !skipCache) {
      const cached = this.get(provider);
      if (cached && cached.length > 0) {
        this.recordHit(provider);
        this.logDebug(`[ModelCache] Cache hit for ${provider}: ${cached.length} models`);
        return {
          models: cached,
          fromCache: true,
          provider,
        };
      }
    }

    // If already refreshing, wait for it
    if (this.isRefreshing.has(provider)) {
      return await this.isRefreshing.get(provider);
    }

    // Fetch from API
    const promise = this.fetchFromAPI(provider, options);
    this.isRefreshing.set(provider, promise);

    try {
      const result = await promise;
      return result;
    } finally {
      this.isRefreshing.delete(provider);
    }
  }

  /**
     * Fetch models from API
     */
  async fetchFromAPI(provider, options = {}) {
    const timeout = options.timeout || 10000;
    const signal = AbortSignal.timeout ? AbortSignal.timeout(timeout) : null;

    try {
      const url = `${this.apiEndpoint}?provider=${encodeURIComponent(provider)}`;

      const response = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        signal,
      });

      if (!response.ok) {
        throw new Error(`API error: ${response.statusText}`);
      }

      let models = await response.json();

      // Normalize models format
      if (!Array.isArray(models)) {
        models = [];
      }

      // Cache the results
      const ttl = options.cacheTTL || this.ttl;
      this.set(provider, models, ttl);

      this.logDebug(`[ModelCache] Fetched ${models.length} models for ${provider}`);

      return {
        models,
        fromCache: false,
        provider,
        fetchedAt: new Date().toISOString(),
      };
    } catch (err) {
      console.warn(`[ModelCache] Failed to fetch models for ${provider}:`, err.message);

      // Fall back to cache even if expired
      const cachedModels = this.cache.get(this.getCacheKey(provider));
      if (cachedModels && cachedModels.models && cachedModels.models.length > 0) {
        this.logDebug(`[ModelCache] Using stale cache for ${provider}`);
        return {
          models: cachedModels.models,
          fromCache: true,
          isStale: true,
          provider,
        };
      }

      throw err;
    }
  }

  /**
     * Batch fetch models for multiple providers
     */
  async fetchBatch(providers = [], options = {}) {
    const results = {};
    const promises = providers.map(provider =>
      this.fetch(provider, options)
        .then(result => {
          results[provider] = result;
        })
        .catch(err => {
          console.error(`Failed to fetch models for ${provider}:`, err);
          results[provider] = { models: [], error: err.message, };
        })
    );

    await Promise.allSettled(promises);
    return results;
  }

  /**
     * Pre-fetch models in background
     */
  prefetch(providers = [], options = {}) {
    const prefetchOptions = {
      ...options,
      skipCache: false, // Use cache for prefetch
    };

    return Promise.all(
      providers.map(provider =>
        this.fetch(provider, prefetchOptions).catch(err => {
          console.warn(`Prefetch failed for ${provider}:`, err.message);
          return null;
        })
      )
    );
  }

  /**
     * Clear cache for specific provider
     */
  clear(provider = null) {
    if (provider) {
      const key = this.getCacheKey(provider);
      this.cache.delete(key);
    } else {
      this.cache.clear();
    }
    this.saveToStorage();
  }

  /**
     * Get cache statistics
     */
  getStats() {
    const stats = {
      total: this.cache.size,
      entries: [],
    };

    for (const [, entry,] of this.cache.entries()) {
      const isExpired = entry.expiresAt && entry.expiresAt < Date.now();
      stats.entries.push({
        provider: entry.provider,
        modelCount: entry.models?.length || 0,
        cachedAt: entry.cachedAt,
        expiresAt: entry.expiresAt,
        isExpired,
        hits: entry.hits || 0,
        ttlMinutes: Math.round((entry.expiresAt - Date.now()) / 60000),
      });
    }

    return stats;
  }

  /**
     * Export cache for debugging
     */
  export() {
    return {
      version: CACHE_VERSION,
      timestamp: new Date().toISOString(),
      data: Object.fromEntries(this.cache),
      stats: this.getStats(),
    };
  }
}

/**
 * Create a global model cache instance
 */
let globalCache = null;

export function getModelCache(options = {}) {
  if (!globalCache) {
    globalCache = new ModelCache(options);
  }
  return globalCache;
}

/**
 * Create a new cache instance
 */
export function createModelCache(options = {}) {
  return new ModelCache(options);
}

/**
 * Initialize model cache with default providers
 */
export function initializeModelCache(providers = [], options = {}) {
  const cache = getModelCache(options);

  // Prefetch models for given providers in background
  if (providers.length > 0) {
    cache.prefetch(providers, { skipCache: false, })
      .then(() => {
        console.warn('[ModelCache] Prefetch completed');
      })
      .catch(err => {
        console.warn('[ModelCache] Prefetch failed:', err.message);
      });
  }

  return cache;
}

/**
 * Invalidate cache after certain time
 */
export function scheduleInvalidation(provider, delay = DEFAULT_CACHE_TTL) {
  setTimeout(() => {
    const cache = getModelCache();
    cache.clear(provider);
    cache.logDebug(`[ModelCache] Scheduled invalidation for ${provider}`);
  }, delay);
}

/**
 * Batch load models with cache
 */
export async function loadModelsWithCache(provider, options = {}) {
  const cache = getModelCache(options.cacheOptions || {});
  return await cache.fetch(provider, options);
}
