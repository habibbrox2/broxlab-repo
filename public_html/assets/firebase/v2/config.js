// v2/config.js
// Centralized Firebase configuration & notification system settings
// Single source of truth for all v2 modules: messaging, auth, notifications, offline, sync, token management, analytics
// Non-blocking config fetch on page load, immediate initialization

import { fetchWithTimeout } from '../../js/shared/fetch-utils.js';

const MAX_CACHE_AGE_MS = 7 * 24 * 60 * 60 * 1000; // 7 days

let _cachedConfig = null; // module-local cache
let _fetchInProgress = null;
let _notificationConfig = null; // parsed notification/system config
let _configFetchInitiated = true; // track if fetch started

// ============ DEFAULT NOTIFICATION CONFIG ============
// Centralized settings for notifications, FCM, offline, multi-device sync, token management, rate-limiting
const DEFAULT_NOTIFICATION_CONFIG = {
  // Notification UI Behavior
  notifications: {
    enabled: true,
    enablePopup: true,
    enableToast: true,
    enableSound: true,
    defaultIcon: 'assets/logo/logo-sm.png',
    defaultBadge: 'assets/logo/favicon.ico',
    maxNotifications: 10, // max concurrent notifications on screen
    popupDelay: 300, // ms before showing popup
    popupCooldown: 500, // ms between popup displays
    autoCloseDelay: 5000, // ms before auto-closing toast
    soundUrl: 'assets/sounds/notification.mp3'
  },

  // Scheduled Notifications
  scheduledNotifications: {
    enabled: true,
    checkInterval: 60000, // ms between server checks
    retentionDays: 30, // keep history for N days
    timezoneAware: true,
    maxScheduledPerUser: 100,
    enableClientSideScheduler: false // use server-side scheduling
  },

  // Multi-Device Sync
  multiDeviceSync: {
    enabled: true,
    deduplication: true,
    deduplicateWindow: 5000, // ms window for dedup
    syncInterval: 30000, // ms between syncs
    syncOnForeground: true, // sync when tab comes to foreground
    maxDevicesPerUser: 5,
    enableOfflineSync: true
  },

  // Offline Handling & Queue
  offlineHandling: {
    enabled: true,
    cacheNotifications: true,
    maxCachedNotifications: 100,
    retryInterval: 5000, // start retry after this delay
    maxRetries: 5,
    retryDelayMultiplier: 1.5, // exponential backoff: 5s, 7.5s, 11.25s...
    enableBackgroundSync: true,
    syncReadStatus: true, // sync read/unread status offline
    persistToIndexedDB: true, // use IndexedDB for larger cache
    enableAutoResume: true // auto-resume when online
  },

  // FCM Token Management
  tokenManagement: {
    enabled: true,
    autoCleanup: true,
    cleanupInterval: 86400000, // ms (1 day) - clean up expired tokens
    heartbeatSyncIntervalMs: 86400000, // ms (1 day) - refresh server timestamp even when token is unchanged
    heartbeatCheckIntervalMs: 900000, // ms (15 min) - check whether heartbeat sync is due
    tokenExpiryDays: 90,
    validateTokenOnStartup: true,
    maxTokensPerDevice: 5,
    maxTokenRetries: 4,
    maxSyncRetries: 3,
    tokenValidationRegex: '^[A-Za-z0-9_:-]+$', // FCM token format
    minTokenLength: 10,
    syncDebounceMs: 3000, // debounce token sync requests
    enableTokenEncryption: false // for future enhancement
  },

  // Rate Limiting & Throttling
  rateLimiting: {
    enabled: true,
    minLoadInterval: 1000, // ms between config loads
    maxRetries: 3, // max retries before giving up
    retryDelayMultiplier: 2.0, // exponential backoff multiplier
    requestTimeout: 10000, // ms timeout for network requests
    enableAdaptiveThrottling: true // adjust rates based on network
  },

  // Analytics Integration
  analytics: {
    enabled: true,
    trackNotificationEvents: true,
    trackTokenEvents: true,
    trackOfflineEvents: true,
    trackSyncEvents: true,
    batchEventsSizeThreshold: 20,
    batchEventTimeThreshold: 60000 // ms (1 min)
  },

  // Event Handlers (can be overridden)
  handlers: {
    onNotificationReceived: null, // function(payload)
    onNotificationClick: null, // function(notificationId)
    onPermissionGranted: null, // function()
    onPermissionDenied: null, // function()
    onTokenGenerated: null, // function(token)
    onTokenRefreshed: null, // function(newToken)
    onSyncComplete: null, // function(syncResult)
    onOfflineItemsRestored: null // function(itemCount)
  },

  // Feature Flags
  features: {
    enableNotificationCenter: true,
    enableNotificationHistory: true,
    enableNotificationPreferences: true,
    enableGroupNotifications: true,
    enableNotificationActions: true, // action buttons on notifications
    enableRichNotifications: true // images, badges, etc
  },

  // API Endpoints
  api: {
    configEndpoint: '/api/firebase-config',
    tokenSyncEndpoint: '/api/notifications/sync-token',
    // Mapped to canonical server routes (singular "notification" prefix)
    offlineSyncEndpoint: '/api/notification/send',
    multiDeviceSyncEndpoint: '/api/notification/sync-status',
    notificationsEndpoint: '/api/notification',
    // No backend audit endpoint available by default; rely on Firebase analytics
    auditEndpoint: null
  }
};

function safeParseJSON(raw) {
  try { return JSON.parse(raw); } catch (e) { return null; }
}

function validateFirebaseConfig(cfg) {
  if (!cfg || typeof cfg !== 'object') return null;
  // Basic required fields
  const { apiKey, projectId, appId } = cfg;
  if (!apiKey || !projectId) return null;
  return cfg;
}

// Merge notification config with defaults
function mergeNotificationConfig(fetched = {}) {
  const defaults = DEFAULT_NOTIFICATION_CONFIG;
  // Shallow merge with fetched config (fetched overrides defaults)
  const merged = { ...defaults, ...fetched };
  // Deep merge nested objects
  Object.keys(defaults).forEach(key => {
    if (typeof defaults[key] === 'object' && !Array.isArray(defaults[key])) {
      merged[key] = { ...defaults[key], ...(fetched[key] || {}) };
    }
  });
  return merged;
}

// Synchronous version for immediate access
function mergeNotificationConfigSync(fetched = {}) {
  const defaults = DEFAULT_NOTIFICATION_CONFIG;
  const merged = { ...defaults, ...fetched };
  Object.keys(defaults).forEach(key => {
    if (typeof defaults[key] === 'object' && !Array.isArray(defaults[key])) {
      merged[key] = { ...defaults[key], ...(fetched[key] || {}) };
    }
  });
  return merged;
}

export async function loadFirebaseConfig(timeout = 5000) {
  // Return cached if present
  if (_cachedConfig) return _cachedConfig;

  if (_fetchInProgress) return _fetchInProgress;

  _fetchInProgress = (async () => {
    // 1) Embedded config set by server in window.__EMBEDDED_FIREBASE_CONFIG
    try {
      if (typeof window !== 'undefined' && window.__EMBEDDED_FIREBASE_CONFIG) {
        const validated = validateFirebaseConfig(window.__EMBEDDED_FIREBASE_CONFIG);
        if (validated) { _cachedConfig = validated; return validated; }
      }
    } catch (e) { }

    // 2) Network fetch from /api/firebase-config with timeout
    try {
      const { ok, status, data } = await fetchWithTimeout('/api/firebase-config', {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Accept': 'application/json' },
        timeoutMs: timeout
      });
      if (ok) {
        const body = data || null;
        const cfg = body && body.config ? body.config : body;
        const validated = validateFirebaseConfig(cfg);
        if (validated) {
          try { localStorage.setItem('firebase_config_cache', JSON.stringify({ ts: Date.now(), config: validated })); } catch (e) { }
          _cachedConfig = validated;
          return validated;
        }
      }
    } catch (e) {
      // swallow network errors; fall back to cache
    }

    // 3) localStorage fallback
    try {
      const raw = localStorage.getItem('firebase_config_cache');
      const parsed = safeParseJSON(raw);
      if (parsed && parsed.config) {
        const age = Date.now() - (parsed.ts || 0);
        if (age < MAX_CACHE_AGE_MS) {
          const validated = validateFirebaseConfig(parsed.config);
          if (validated) { _cachedConfig = validated; return validated; }
        }
      }
    } catch (e) { }

    // 4) inline <script id="firebase-config"> fallback
    try {
      if (typeof document !== 'undefined') {
        const el = document.getElementById('firebase-config');
        if (el && el.textContent) {
          const parsed = safeParseJSON(el.textContent);
          const validated = validateFirebaseConfig(parsed);
          if (validated) { _cachedConfig = validated; return validated; }
        }
      }
    } catch (e) { }

    return null;
  })();

  try { const r = await _fetchInProgress; return r; } finally { _fetchInProgress = null; }
}

export function clearCachedFirebaseConfig() { _cachedConfig = null; _notificationConfig = null; try { localStorage.removeItem('firebase_config_cache'); } catch (e) { } }

// Get notification config (returns merged default + fetched config)
// Synchronous: returns immediately (uses default if not yet loaded)
export function getNotificationConfig() {
  if (_notificationConfig) return _notificationConfig;
  // Return defaults while async fetch is in progress
  return mergeNotificationConfigSync({});
}

// Get full config (Firebase + Notifications merged)
export async function getFullConfig(timeout = 5000) {
  const firebaseConfig = await loadFirebaseConfig(timeout);
  const notifConfig = await _ensureNotificationConfig();
  return { firebaseConfig, notificationConfig: notifConfig };
}

// Internal: ensure notification config is loaded
async function _ensureNotificationConfig() {
  if (_notificationConfig) return _notificationConfig;
  await loadFirebaseConfig().catch(() => null); // ensure base config loaded

  // Try to extract from firebase config or use defaults
  try {
    const fbConfig = _cachedConfig || {};
    const embedded = (typeof window !== 'undefined' && window.__EMBEDDED_NOTIFICATION_CONFIG) || {};
    _notificationConfig = mergeNotificationConfigSync({ ...fbConfig.notificationConfig, ...embedded });
  } catch (e) {
    _notificationConfig = mergeNotificationConfigSync({});
  }

  return _notificationConfig;
}

// Prefetch everything non-blockingly on page load
export async function prefetchAllConfigs() {
  try {
    await Promise.all([
      loadFirebaseConfig().catch(() => null),
      _ensureNotificationConfig().catch(() => null)
    ]);
  } catch (e) { }
}

export async function prefetchFirebaseConfig() { try { await loadFirebaseConfig().catch(() => null); } catch (e) { } }

export default { loadFirebaseConfig, getNotificationConfig, getFullConfig, clearCachedFirebaseConfig, prefetchFirebaseConfig, prefetchAllConfigs, DEFAULT_NOTIFICATION_CONFIG };
