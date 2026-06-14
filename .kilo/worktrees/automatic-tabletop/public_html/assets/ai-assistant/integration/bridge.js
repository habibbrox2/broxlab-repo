/**
 * BroxLab - Legacy & Modern Integration Bridge
 * Bridges between legacy ai-admin.js and new cache system
 * Version: 1.0 | Date: April 24, 2026
 */

(function () {
  'use strict';

  const config = {
    enableNewCacheSystem: true,
    enableLegacyFallback: true,
    debugMode: false,
  };

  const debugLog = (...args) => {
    if (!config.debugMode) return;
    console.info(...args);
  };

  const statusLog = (...args) => {
    console.info(...args);
  };

  debugLog('[Integration Bridge] Initializing legacy & modern system bridge...');

  window.BroxBridgeIntegration = {
    config,
    legacyCache: {},
    modernCache: null,

    async init() {
      debugLog('[Integration Bridge] Starting initialization...');

      try {
        if (config.enableNewCacheSystem) {
          await this.initModernCache();
        }

        if (config.enableLegacyFallback) {
          this.setupLegacyFallback();
        }

        debugLog('[Integration Bridge] ✓ Initialization complete');
        return true;
      } catch (err) {
        console.error('[Integration Bridge] Initialization failed:', err);
        return false;
      }
    },

    async initModernCache() {
      try {
        if (typeof window.__cacheDebug !== 'undefined') {
          this.modernCache = window.__cacheDebug;
          debugLog('[Integration Bridge] ✓ Modern cache system connected');
          return;
        }

        debugLog('[Integration Bridge] Waiting for cache-debug module...');
        await this.waitForModuleLoad('__cacheDebug', 5000);

        if (typeof window.__cacheDebug !== 'undefined') {
          this.modernCache = window.__cacheDebug;
          debugLog('[Integration Bridge] ✓ Modern cache system connected');
        } else {
          console.warn('[Integration Bridge] Modern cache not available (timeout)');
        }
      } catch (err) {
        console.warn('[Integration Bridge] Could not init modern cache:', err.message);
      }
    },

    async waitForModuleLoad(moduleName, timeout = 5000) {
      const startTime = Date.now();
      while (Date.now() - startTime < timeout) {
        if (typeof window[moduleName] !== 'undefined') {
          return true;
        }
        await new Promise(resolve => setTimeout(resolve, 100));
      }
      return false;
    },

    setupLegacyFallback() {
      if (typeof window.BroxAdminCopilot !== 'undefined') {
        debugLog('[Integration Bridge] ✓ Legacy system available');
      }
    },

    async getModels(provider, options = {}) {
      const cacheKey = `models_${provider}`;

      if (this.modernCache) {
        try {
          const result = await this.getModelsFromModernCache(provider, options);
          if (result && result.models && result.models.length > 0) {
            debugLog(`[Integration Bridge] Got ${result.models.length} models from modern cache for ${provider}`);
            return result;
          }
        } catch (err) {
          debugLog(`[Integration Bridge] Modern cache lookup failed: ${err.message}`);
        }
      }

      if (this.legacyCache[cacheKey]) {
        debugLog(`[Integration Bridge] Using legacy cache for ${provider}`);
        return {
          models: this.legacyCache[cacheKey],
          fromCache: true,
          isLegacy: true,
        };
      }

      return await this.fetchFromAPI(provider, options);
    },

    getModelsFromModernCache(provider, _options = {}) {
      if (!this.modernCache || !this.modernCache.getState) {
        return null;
      }

      try {
        const state = this.modernCache.getState();
        if (state && state.data) {
          const key = `provider:${provider}`;
          const entry = state.data[key];

          if (entry && entry.models) {
            return {
              models: entry.models,
              fromCache: true,
              isModern: true,
              hits: entry.hits || 0,
            };
          }
        }
      } catch (err) {
        debugLog('[Integration Bridge] Error reading modern cache:', err.message);
      }

      return null;
    },

    async fetchFromAPI(provider, options = {}) {
      try {
        const response = await fetch(`/api/ai/models?provider=${provider}`, {
          credentials: 'same-origin',
          timeout: options.timeout || 10000,
        });

        if (!response.ok) {
          throw new Error(`API error: ${response.statusText}`);
        }

        let models = await response.json();
        if (!Array.isArray(models)) {
          models = [];
        }

        const cacheKey = `models_${provider}`;
        this.legacyCache[cacheKey] = models;

        return {
          models,
          fromCache: false,
          fromAPI: true,
        };
      } catch (err) {
        console.warn(`[Integration Bridge] API fetch failed for ${provider}:`, err.message);
        return { models: [], error: err.message, };
      }
    },

    getStats() {
      const stats = {
        modernCache: null,
        legacyCache: Object.keys(this.legacyCache).length,
      };

      if (this.modernCache && this.modernCache.getStats) {
        try {
          stats.modernCache = this.modernCache.getStats();
        } catch (err) {
          debugLog('[Integration Bridge] Could not get modern cache stats:', err.message);
        }
      }

      return stats;
    },

    clearAll() {
      this.legacyCache = {};

      if (this.modernCache && this.modernCache.clearAll) {
        try {
          this.modernCache.clearAll();
        } catch (err) {
          debugLog('[Integration Bridge] Could not clear modern cache:', err.message);
        }
      }

      debugLog('[Integration Bridge] ✓ All caches cleared');
    },

    getReport() {
      const report = {
        timestamp: new Date().toISOString(),
        modernCacheAvailable: Boolean(this.modernCache),
        legacySystemAvailable: typeof window.BroxAdminCopilot !== 'undefined',
        nodeServerOnline: false,
        legacyCacheEntries: Object.keys(this.legacyCache).length,
      };

      fetch('/api/health', { credentials: 'same-origin', })
        .then(r => r.ok && (report.nodeServerOnline = true))
        .catch(() => {
          report.nodeServerOnline = false;
        });

      return report;
    },

    debug(enabled = true) {
      config.debugMode = enabled;
      statusLog(`[Integration Bridge] Debug mode: ${enabled ? 'ON' : 'OFF'}`);
    },
  };

  window.BroxBridge = {
    getModels: (provider, opts) => window.BroxBridgeIntegration.getModels(provider, opts),
    getStats: () => window.BroxBridgeIntegration.getStats(),
    clear: () => window.BroxBridgeIntegration.clearAll(),
    report: () => window.BroxBridgeIntegration.getReport(),
    debug: (on) => window.BroxBridgeIntegration.debug(on),
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      setTimeout(() => {
        window.BroxBridgeIntegration.init();
      }, 500);
    });
  } else {
    setTimeout(() => {
      window.BroxBridgeIntegration.init();
    }, 500);
  }

  if (typeof window.fetchProviderModels !== 'undefined') {
    const originalFetch = window.fetchProviderModels;

    window.fetchProviderModels = async function (provider, scope = 'admin') {
      debugLog(`[Integration Bridge] Intercepting fetchProviderModels for ${provider}`);

      try {
        const result = await window.BroxBridgeIntegration.getModels(provider, { scope, });

        if (result && result.models && result.models.length > 0) {
          debugLog(`[Integration Bridge] ✓ Got models from bridge: ${result.models.length}`);
          return result.models;
        }
      } catch (err) {
        debugLog('[Integration Bridge] Bridge fetch failed, falling back:', err.message);
      }

      return await originalFetch.call(this, provider, scope);
    };

    debugLog('[Integration Bridge] ✓ Patched legacy fetchProviderModels');
  }

  debugLog('[Integration Bridge] ✓ Bridge loaded and ready');
})();
