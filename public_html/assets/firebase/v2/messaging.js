// v2/messaging.js - FCM helpers with retry logic, SW registration, and token management
// Uses centralized config from config.js
// Exports: registerServiceWorker, obtainAndSendFCMToken, listenForMessages

import initFirebase from './init.js';
import { getMessagingInstance, getNotificationConfig } from './init.js';
import { DebugUtils } from './debug.js';
import { getToken, onMessage, isSupported as isFirebaseMessagingSupported } from 'firebase/messaging';
import { fetchWithTimeout } from '../../js/shared/fetch-utils.js';
import { getCsrfToken } from './firebase-utils.js';

// Token management defaults (overridable via config)
let DEFAULT_RETRIES = 4;
let TOKEN_SYNC_DEBOUNCE = 3000;
let _tokenSyncTimeout = null;
const TOKEN_STORAGE_KEY = '__fcm_token';
const TOKEN_LAST_SYNC_KEY = '__fcm_token_last_synced_at';
const TOKEN_PENDING_SYNC_KEY = '__fcm_token_pending_sync';
const DEFAULT_SYNC_RETRIES = 3;
const DEFAULT_HEARTBEAT_SYNC_INTERVAL_MS = 24 * 60 * 60 * 1000; // 1 day
const DEFAULT_HEARTBEAT_CHECK_INTERVAL_MS = 15 * 60 * 1000; // 15 minutes
let TOKEN_HEARTBEAT_SYNC_INTERVAL = DEFAULT_HEARTBEAT_SYNC_INTERVAL_MS;
let TOKEN_HEARTBEAT_CHECK_INTERVAL = DEFAULT_HEARTBEAT_CHECK_INTERVAL_MS;
let _heartbeatIntervalId = null;
let _pendingFlushInFlight = false;
let _lifecycleListenersBound = false;
const LIFECYCLE_CHECK_COOLDOWN_MS = 3000;
let _lastLifecycleCheckAt = 0;
let _messagingSupportPromise = null;

function emitMessagingSupportResolved(supported) {
  if (typeof window === 'undefined') return;
  try {
    window.dispatchEvent(new CustomEvent('fcm-support-resolved', {
      detail: { supported: !!supported, source: 'messaging' }
    }));
  } catch (e) {
    // Ignore event dispatch errors.
  }
}

function setMessagingSupportState(supported, { emit = true } = {}) {
  if (typeof window === 'undefined') return;
  const normalized = !!supported;
  const previous = window.__fcmMessagingSupported;
  window.__fcmMessagingSupported = normalized;
  if (emit && previous !== normalized) {
    emitMessagingSupportResolved(normalized);
  }
}

export async function isMessagingSupported(forceRefresh = false) {
  if (!forceRefresh && _messagingSupportPromise) return _messagingSupportPromise;
  _messagingSupportPromise = (async () => {
    if (typeof window === 'undefined' || typeof navigator === 'undefined') {
      return false;
    }
    if (typeof Notification === 'undefined' || !('serviceWorker' in navigator)) {
      setMessagingSupportState(false);
      return false;
    }
    try {
      const supported = (await isFirebaseMessagingSupported()) === true;
      setMessagingSupportState(supported);
      return supported;
    } catch (err) {
      DebugUtils.moduleWarn('messaging', 'Messaging support check failed:', err?.message || err);
      setMessagingSupportState(false);
      return false;
    }
  })();
  return _messagingSupportPromise;
}

function getEffectiveUserId(opts = {}) {
  const directUserId = opts?.userId;
  if (directUserId !== undefined && directUserId !== null) {
    const normalized = String(directUserId).trim();
    if (normalized) return normalized;
  }
  try {
    const metaUserId = document.querySelector('meta[name="user-id"]')?.content;
    const normalized = String(metaUserId || '').trim();
    return normalized || '';
  } catch (e) {
    return '';
  }
}

function shouldRunLifecycleTokenCheck() {
  const now = Date.now();
  if ((now - _lastLifecycleCheckAt) < LIFECYCLE_CHECK_COOLDOWN_MS) return false;
  _lastLifecycleCheckAt = now;
  return true;
}

function getLastSyncedAtMs() {
  try {
    const raw = localStorage.getItem(TOKEN_LAST_SYNC_KEY);
    if (!raw) return 0;
    const parsed = parseInt(raw, 10);
    return Number.isFinite(parsed) ? parsed : 0;
  } catch (e) {
    return 0;
  }
}

function getStoredToken() {
  try {
    return localStorage.getItem(TOKEN_STORAGE_KEY) || '';
  } catch (e) {
    return '';
  }
}

function markTokenSynced(token) {
  try {
    localStorage.setItem(TOKEN_STORAGE_KEY, token);
    localStorage.setItem(TOKEN_LAST_SYNC_KEY, String(Date.now()));
  } catch (e) { }
}

function getPendingTokenSyncPayload() {
  try {
    const raw = localStorage.getItem(TOKEN_PENDING_SYNC_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    return (parsed && typeof parsed === 'object') ? parsed : null;
  } catch (e) {
    return null;
  }
}

function setPendingTokenSyncPayload(payload) {
  try {
    if (!payload || !payload.token) return;
    localStorage.setItem(TOKEN_PENDING_SYNC_KEY, JSON.stringify(payload));
  } catch (e) { }
}

function clearPendingTokenSyncPayload() {
  try {
    localStorage.removeItem(TOKEN_PENDING_SYNC_KEY);
  } catch (e) { }
}

function isRetryableStatus(status) {
  return status === 408 || status === 429 || status >= 500;
}

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function postTokenSyncWithRetry(sendUrl, payload, maxRetries = DEFAULT_SYNC_RETRIES) {
  const retries = Number.isFinite(maxRetries) && maxRetries >= 0 ? Math.floor(maxRetries) : DEFAULT_SYNC_RETRIES;
  const csrfToken = getCsrfToken();
  let lastStatus = 0;
  let lastError = null;

  for (let attempt = 0; attempt <= retries; attempt++) {
    try {
      const { ok, status, error } = await fetchWithTimeout(sendUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          ...(csrfToken ? { 'X-CSRF-Token': csrfToken } : {})
        },
        body: JSON.stringify(payload)
      });
      lastStatus = status;

      if (ok) {
        return { ok: true, status };
      }

      if (!isRetryableStatus(status) || attempt === retries) {
        return { ok: false, status };
      }
    } catch (err) {
      lastError = err;
      if (attempt === retries) break;
    }

    const backoffMs = Math.min(10000, Math.pow(2, attempt) * 500) + Math.floor(Math.random() * 250);
    await wait(backoffMs);
  }

  return { ok: false, status: lastStatus, error: lastError };
}

async function syncTokenToBackend(sendUrl, payload, options = {}) {
  const maxRetries = Number.isFinite(options.maxRetries) ? options.maxRetries : DEFAULT_SYNC_RETRIES;
  const normalized = payload && typeof payload === 'object' ? { ...payload } : {};
  const token = String(normalized.token || '').trim();
  const deviceId = String(normalized.device_id || '').trim();
  if (!token || !deviceId) return false;

  if (normalized.previous_token && normalized.previous_token === token) {
    delete normalized.previous_token;
  }

  const result = await postTokenSyncWithRetry(sendUrl, normalized, maxRetries);
  if (result.ok) {
    markTokenSynced(token);
    clearPendingTokenSyncPayload();
    return true;
  }

  const existingPending = getPendingTokenSyncPayload();
  const retryCount = ((existingPending?.retry_count || 0) + 1);
  setPendingTokenSyncPayload({
    ...normalized,
    retry_count: retryCount,
    last_retry_at_ms: Date.now()
  });
  return false;
}

async function flushPendingTokenSync(sendUrl, options = {}) {
  if (_pendingFlushInFlight) return false;
  const pending = getPendingTokenSyncPayload();
  if (!pending || !pending.token) return true;

  _pendingFlushInFlight = true;
  try {
    return await syncTokenToBackend(sendUrl, pending, options);
  } finally {
    _pendingFlushInFlight = false;
  }
}

// Stable device ID generation and storage
function getOrCreateDeviceId() {
  const KEY = '__fcm_device_id';
  let deviceId = null;
  try { deviceId = localStorage.getItem(KEY); } catch (e) { }
  if (!deviceId) {
    deviceId = `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    try { localStorage.setItem(KEY, deviceId); } catch (e) { }
  }
  return deviceId;
}

function clampDeviceName(value, fallback = 'web') {
  const base = (typeof value === 'string' ? value : '').trim() || fallback;
  return base.length > 100 ? base.slice(0, 100) : base;
}

function getDefaultDeviceName() {
  try {
    const ua = navigator?.userAgent;
    if (ua && typeof ua === 'string') {
      return clampDeviceName(ua, 'web');
    }
  } catch (e) { }
  return clampDeviceName('web', 'web');
}


async function postMessageToServiceWorker(message) {
  if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return false;
  try {
    const readyReg = await navigator.serviceWorker.ready.catch(() => null);
    const reg = readyReg || await navigator.serviceWorker.getRegistration().catch(() => null);
    const sw = navigator.serviceWorker.controller || reg?.active || reg?.waiting || reg?.installing;
    if (!sw) return false;
    sw.postMessage(message);
    return true;
  } catch (e) {
    return false;
  }
}

async function syncServiceWorkerContext({ deviceId, deviceName, csrfToken, debug } = {}) {
  const resolvedDeviceId = deviceId || getOrCreateDeviceId();
  const resolvedDeviceName = clampDeviceName(deviceName, 'web');
  if (csrfToken) {
    await postMessageToServiceWorker({ type: 'STORE_CSRF_TOKEN', token: csrfToken });
  }
  await postMessageToServiceWorker({ type: 'STORE_DEVICE_META', device_id: resolvedDeviceId, device_name: resolvedDeviceName });
  if (typeof debug === 'boolean') {
    await postMessageToServiceWorker({ type: 'SET_SW_DEBUG', enabled: debug });
  }
}

export async function registerServiceWorker(swPath = '/firebase-messaging-sw.js') {
  const supported = await isMessagingSupported();
  if (!supported) {
    DebugUtils.moduleWarn('messaging', 'Skipping Service Worker registration: messaging unsupported');
    return null;
  }

  if ('serviceWorker' in navigator) {
    try {
      // Verify service worker file exists before registration
      const { ok: swOk } = await fetchWithTimeout(swPath);
      const swCheckResponse = { ok: swOk };
      if (!swCheckResponse.ok) {
        throw new Error('Service Worker file not found');
      }

      const reg = await navigator.serviceWorker.register(swPath, { scope: '/' });
      DebugUtils.moduleLog('messaging', 'Service Worker registered');
      return reg;
    } catch (err) {
      DebugUtils.moduleError('messaging', 'Service Worker registration failed');
      throw err;
    }
  }
  throw new Error('Service workers not supported');
}

async function _getTokenWithRetries(messaging, vapidKey, retries = DEFAULT_RETRIES) {
  let attempt = 0; let lastErr = null;
  while (attempt <= retries) {
    try {
      const token = await getToken(messaging, { vapidKey });
      if (token) {
        DebugUtils.moduleLog('messaging', 'FCM token obtained');
        return token;
      }
    } catch (err) {
      lastErr = err;
      const wait = Math.pow(2, attempt) * 250;
      DebugUtils.moduleWarn('messaging', `Token retry attempt ${attempt + 1}`);
      await new Promise(r => setTimeout(r, wait));
    }
    attempt++;
  }
  DebugUtils.moduleError('messaging', 'Failed to obtain FCM token');
  throw lastErr || new Error('FCM token unavailable');
}

export async function obtainAndSendFCMToken(opts = {}) {
  if (opts == null) opts = {};
  if (typeof opts !== 'object') {
    const legacyUserId = opts; const legacyRequestPermission = arguments[1] === true;
    opts = { userId: legacyUserId, requestPermission: legacyRequestPermission };
  }

  const supported = await isMessagingSupported();
  if (!supported) {
    DebugUtils.moduleWarn('messaging', 'Skipping FCM token sync: messaging unsupported');
    return null;
  }

  // Get centralized config
  const notifConfig = getNotificationConfig();
  const tokenConfig = notifConfig.tokenManagement || {};
  const apiConfig = notifConfig.api || {};

  const defaultDeviceName = opts.deviceName || getDefaultDeviceName();
  const {
    swPath = '/firebase-messaging-sw.js',
    vapidKey = null,
    sendUrl = opts.sendUrl || apiConfig.tokenSyncEndpoint || '/api/notifications/sync-token',
    deviceName = defaultDeviceName,
    deviceType = 'web',
    requestPermission: _requestPermission = false
  } = opts;
  const normalizedDeviceName = clampDeviceName(deviceName, defaultDeviceName || 'web');

  if (Number.isFinite(tokenConfig.syncDebounceMs)) {
    TOKEN_SYNC_DEBOUNCE = tokenConfig.syncDebounceMs;
  }
  if (Number.isFinite(tokenConfig.heartbeatSyncIntervalMs) && tokenConfig.heartbeatSyncIntervalMs > 0) {
    TOKEN_HEARTBEAT_SYNC_INTERVAL = tokenConfig.heartbeatSyncIntervalMs;
  }
  if (Number.isFinite(tokenConfig.heartbeatCheckIntervalMs) && tokenConfig.heartbeatCheckIntervalMs > 0) {
    TOKEN_HEARTBEAT_CHECK_INTERVAL = tokenConfig.heartbeatCheckIntervalMs;
  }

  DebugUtils.moduleLog('messaging', 'Starting FCM token acquisition and sync');
  await initFirebase();
  const messaging = getMessagingInstance();
  if (!messaging) {
    DebugUtils.moduleWarn('messaging', 'Firebase messaging instance not available');
    return null;
  }

  // Handle notification permission
  let hasPermission = Notification.permission === 'granted';
  if (!hasPermission && _requestPermission) {
    DebugUtils.moduleLog('messaging', 'Notification permission not granted, requesting...');
    const perm = await Notification.requestPermission();
    hasPermission = perm === 'granted';
    if (!hasPermission) {
      DebugUtils.moduleWarn('messaging', 'Notification permission denied by user');
    }
  }

  if (!hasPermission) {
    DebugUtils.moduleWarn('messaging', 'Skipping FCM token - notification permission not granted');
    throw new Error('Notification permission not granted');
  }

  DebugUtils.moduleLog('messaging', 'Notification permission confirmed');

  let _reg = null;
  try {
    _reg = await registerServiceWorker(swPath);
  } catch (e) {
    DebugUtils.moduleWarn('messaging', 'Service Worker registration failed (continuing with token acquisition):', e.message);
    // Don't throw - we can still try to get a token even without SW
  }

  const resolvedDeviceId = opts.deviceId || getOrCreateDeviceId();
  await syncServiceWorkerContext({
    deviceId: resolvedDeviceId,
    deviceName: normalizedDeviceName,
    csrfToken: getCsrfToken(),
    debug: (typeof window !== 'undefined' && window.__FC_DEBUG === true)
  });

  const cfg = (globalThis?.firebaseConfig || {});

  // Use config retries if available
  const maxRetries = Number.isFinite(tokenConfig.maxTokenRetries) ? tokenConfig.maxTokenRetries : DEFAULT_RETRIES;
  DebugUtils.moduleLog('messaging', `Attempting to get FCM token (max ${maxRetries} retries)`);
  const token = await _getTokenWithRetries(messaging, vapidKey || (cfg?.vapidKey || null), maxRetries);
  const storedToken = getStoredToken();
  const syncRetries = Number.isFinite(tokenConfig.maxSyncRetries) ? tokenConfig.maxSyncRetries : DEFAULT_SYNC_RETRIES;
  const payload = {
    token,
    device_name: normalizedDeviceName,
    device_type: deviceType,
    device_id: opts.deviceId || resolvedDeviceId,
    userId: getEffectiveUserId(opts) || undefined,
    sync_reason: storedToken && storedToken !== token ? 'token_changed' : 'initial',
    token_observed_at_ms: Date.now()
  };
  if (storedToken && storedToken !== token) {
    payload.previous_token = storedToken;
  }

  try {
    await flushPendingTokenSync(sendUrl, { maxRetries: syncRetries });
    const synced = await syncTokenToBackend(sendUrl, payload, { maxRetries: syncRetries });
    if (synced) {
      DebugUtils.moduleLog('messaging', 'FCM token synced successfully');
    } else {
      DebugUtils.moduleWarn('messaging', 'FCM token sync queued for retry');
    }
  } catch (err) {
    DebugUtils.moduleError('messaging', 'sendTokenToServer error:', err?.message || err);
  }
  return token;
}

// Unsubscribe / mute helpers
export async function unsubscribeFCMToken(token) {
  try {
    const { ok } = await fetchWithTimeout('/api/unsubscribe-fcm', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ fcm_token: token }) });
    if (res.ok) {
      try {
        localStorage.removeItem(TOKEN_STORAGE_KEY);
        localStorage.removeItem(TOKEN_LAST_SYNC_KEY);
      } catch (e) { }
      DebugUtils.moduleLog('messaging', 'Token unsubscribed on server');
      return true;
    }
    DebugUtils.moduleWarn('messaging', 'unsubscribeFCMToken failed:', res.status);
    return false;
  } catch (e) {
    DebugUtils.moduleError('messaging', 'unsubscribeFCMToken error:', e);
    return false;
  }
}

// Topic subscription wrappers (server-side topic endpoints exist)
export async function subscribeToTopic(topic, token = null) {
  try {
    const body = { topic };
    if (token) body.token = token;
    const { ok } = await fetchWithTimeout('/api/topics/subscribe', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    return res.ok;
  } catch (e) { DebugUtils.moduleWarn('messaging', 'subscribeToTopic failed', e); return false; }
}

export async function unsubscribeFromTopic(topic, token = null) {
  try {
    const body = { topic };
    if (token) body.token = token;
    const { ok } = await fetchWithTimeout('/api/topics/unsubscribe', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    return res.ok;
  } catch (e) { DebugUtils.moduleWarn('messaging', 'unsubscribeFromTopic failed', e); return false; }
}

export async function listenForMessages(cb) {
  const supported = await isMessagingSupported();
  if (!supported) {
    DebugUtils.moduleWarn('messaging', 'Skipping foreground listener: messaging unsupported');
    return () => { };
  }

  DebugUtils.moduleLog('messaging', 'Setting up message listener');
  await initFirebase();
  const messaging = getMessagingInstance();
  if (!messaging) {
    DebugUtils.moduleWarn('messaging', 'Messaging not available');
    return () => { };
  }
  DebugUtils.moduleLog('messaging', 'Message listener established');
  return onMessage(messaging, (payload) => {
    DebugUtils.moduleLog('messaging', 'Message received:', payload.notification?.title);
    cb(payload);
  });
}

// Foreground Notification Display Handler
// Shows toast/modal notification when app is in foreground
export function showForegroundNotification(payload) {
  const { notification = {}, data = {} } = payload;
  const title = notification.title || 'নোটিফিকেশন';
  const body = notification.body || '';
  const icon = notification.icon || '/assets/logo/icon-192x192.png';
  const badge = notification.badge || '/assets/logo/badge.png';

  DebugUtils.moduleLog('messaging', 'Displaying foreground notification:', title);

  // Create toast container if not exists
  let toastContainer = document.getElementById('fcm-toast-container');
  if (!toastContainer) {
    toastContainer = document.createElement('div');
    toastContainer.id = 'fcm-toast-container';
    toastContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
    document.body.appendChild(toastContainer);
  }

  // Create toast element
  const toastId = `toast-${Date.now()}`;
  const toastHtml = `
    <div id="${toastId}" class="toast show" role="alert" aria-live="assertive" aria-atomic="true" style="min-width: 320px;">
      <div class="toast-header bg-primary text-white border-0">
        <img src="${icon}" alt="icon" class="rounded me-2" style="width: 24px; height: 24px;">
        <strong class="me-auto">${title}</strong>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
      </div>
      <div class="toast-body">
        ${body}
      </div>
    </div>
  `;

  toastContainer.insertAdjacentHTML('beforeend', toastHtml);

  // Initialize Bootstrap toast
  if (window.bootstrap?.Toast) {
    const toastElement = document.getElementById(toastId);
    const bsToast = new window.bootstrap.Toast(toastElement, {
      autohide: true,
      delay: 5000
    });

    // Auto-remove from DOM after hiding
    toastElement.addEventListener('hidden.bs.toast', () => {
      toastElement.remove();
    });

    // Handle click to open action URL
    if (data.click_action || notification.clickAction) {
      toastElement.style.cursor = 'pointer';
      toastElement.addEventListener('click', () => {
        const url = data.click_action || notification.clickAction;
        if (url) {
          window.open(url, '_blank');
        }
      });
    }
  } else {
    // Fallback: auto-remove after 5 seconds if Bootstrap not available
    setTimeout(() => {
      const elem = document.getElementById(toastId);
      if (elem) elem.remove();
    }, 5000);
  }

  // Log notification display for analytics
  if (window.__fcmNotificationLog) {
    window.__fcmNotificationLog.push({
      title,
      body,
      displayedAt: new Date().toISOString()
    });
  }
}

// Auto-initialize foreground message listener
export function autoInitializeForegroundListener(opts = {}) {
  const { onMessageCallback = null } = opts;

  DebugUtils.moduleLog('messaging', 'Setting up auto-initialize foreground listener');

  void (async () => {
    const supported = await isMessagingSupported();
    if (!supported) {
      DebugUtils.moduleWarn('messaging', 'Skipping foreground auto-init: messaging unsupported');
      return;
    }

    // Listen for Firebase initialization
    document.addEventListener('firebase-initialized', async () => {
      try {
        if (Notification.permission === 'granted') {
          DebugUtils.moduleLog('messaging', 'Setting up foreground message listener...');
          const unsubscribe = await listenForMessages((payload) => {
            // Show foreground notification
            showForegroundNotification(payload);

            // Call custom callback if provided
            if (onMessageCallback) {
              onMessageCallback(payload);
            }
          });

          DebugUtils.moduleLog('messaging', 'Foreground message listener active');

          // Store unsubscribe function for cleanup
          window.__fcmListenerCleanup = unsubscribe;
        }
      } catch (err) {
        DebugUtils.moduleError('messaging', 'Failed to setup foreground listener:', err);
      }
    }, { once: true });
  })();
}

// Auto-initialize FCM token when notification permission is granted
export function autoInitializeFCMToken(opts = {}) {
  const {
    onSuccess = null,
    onError = null,
    autoRetry = true
  } = opts;

  if (typeof window === 'undefined') return; // Not in browser

  DebugUtils.moduleLog('messaging', 'Setting up auto-FCM initialization listener');

  void (async () => {
    const supported = await isMessagingSupported();
    if (!supported) {
      window.__fcmTokenObtained = false;
      DebugUtils.moduleWarn('messaging', 'Skipping auto-FCM init: messaging unsupported');
      return;
    }

    const notifConfig = getNotificationConfig?.() || {};
    const tokenConfig = notifConfig.tokenManagement || {};
    if (Number.isFinite(tokenConfig.heartbeatSyncIntervalMs) && tokenConfig.heartbeatSyncIntervalMs > 0) {
      TOKEN_HEARTBEAT_SYNC_INTERVAL = tokenConfig.heartbeatSyncIntervalMs;
    }
    if (Number.isFinite(tokenConfig.heartbeatCheckIntervalMs) && tokenConfig.heartbeatCheckIntervalMs > 0) {
      TOKEN_HEARTBEAT_CHECK_INTERVAL = tokenConfig.heartbeatCheckIntervalMs;
    }

    // Listen for Firebase initialization
    document.addEventListener('firebase-initialized', async () => {
      try {
        if (Notification.permission === 'granted') {
          DebugUtils.moduleLog('messaging', 'Notification permission granted, obtaining FCM token...');
          const token = await obtainAndSendFCMToken(opts);
          window.__fcmTokenObtained = true;
          if (onSuccess) onSuccess(token);
        } else {
          DebugUtils.moduleLog('messaging', 'Notification permission not granted, skipping FCM token');
        }
      } catch (err) {
        DebugUtils.moduleError('messaging', 'Auto-FCM initialization failed:', err);
        window.__fcmTokenObtained = false;
        if (onError) onError(err);
      }
    }, { once: true }); // Only listen once

    // Also listen for permission changes (if user grants permission after loading page)
    if ('Notification' in window) {
      // Check permission status periodically (in case user enabled from browser settings)
      const interval = setInterval(async () => {
        if (Notification.permission === 'granted' && !window.__fcmTokenObtained) {
          DebugUtils.moduleLog('messaging', 'Permission change detected, obtaining FCM token...');
          window.__fcmTokenObtained = true;
          clearInterval(interval);
          try {
            const token = await obtainAndSendFCMToken(opts);
            if (onSuccess) onSuccess(token);
          } catch (err) {
            window.__fcmTokenObtained = false;
            if (onError) onError(err);
            if (autoRetry) {
              // Retry after delay
              setTimeout(() => { autoInitializeFCMToken(opts); }, 5000);
            }
          }
        }
      }, 1000); // Check every 1 second

      // Clear interval after 30 seconds to avoid memory leak
      setTimeout(() => clearInterval(interval), 30000);
    }

    // Token refresh detection: check on foreground/visibility change
    if (typeof document !== 'undefined' && !_lifecycleListenersBound) {
      _lifecycleListenersBound = true;
      document.addEventListener('visibilitychange', async () => {
        if (document.visibilityState === 'visible') {
          if (!shouldRunLifecycleTokenCheck()) return;
          DebugUtils.moduleLog('messaging', 'App became visible, checking token freshness');
          checkAndRefreshTokenIfNeeded(opts);
        }
      });
      window.addEventListener('focus', () => {
        if (!shouldRunLifecycleTokenCheck()) return;
        DebugUtils.moduleLog('messaging', 'Window focused, checking token freshness');
        checkAndRefreshTokenIfNeeded(opts);
      });
      window.addEventListener('online', () => {
        DebugUtils.moduleLog('messaging', 'Network restored, retrying token sync');
        checkAndRefreshTokenIfNeeded(opts);
      });
    }

    if (!_heartbeatIntervalId) {
      const intervalMs = Math.max(60000, TOKEN_HEARTBEAT_CHECK_INTERVAL || DEFAULT_HEARTBEAT_CHECK_INTERVAL_MS);
      _heartbeatIntervalId = setInterval(() => {
        checkAndRefreshTokenIfNeeded(opts);
      }, intervalMs);
      DebugUtils.moduleLog('messaging', `Token heartbeat checker active (${intervalMs}ms)`);
    }
  })();
}

// Check if stored token is still valid, re-sync if changed or expired
async function checkAndRefreshTokenIfNeeded(opts = {}) {
  if (typeof Notification === 'undefined' || Notification.permission !== 'granted') return;
  if (!(await isMessagingSupported())) return;
  let storedToken = null;
  try { storedToken = localStorage.getItem(TOKEN_STORAGE_KEY); } catch (e) { }
  const lastSyncedAtMs = getLastSyncedAtMs();
  const nowMs = Date.now();

  try {
    await initFirebase();
    const messaging = getMessagingInstance();
    if (!messaging) return;
    const cfg = globalThis?.firebaseConfig || {};
    const currentToken = await getToken(messaging, { vapidKey: cfg?.vapidKey || null });

    const tokenChanged = !!(currentToken && currentToken !== storedToken);
    const syncIntervalMs = Math.max(60000, TOKEN_HEARTBEAT_SYNC_INTERVAL || DEFAULT_HEARTBEAT_SYNC_INTERVAL_MS);
    const heartbeatDue = !!currentToken && (!lastSyncedAtMs || (nowMs - lastSyncedAtMs) >= syncIntervalMs);
    const needsSync = tokenChanged || heartbeatDue;
    const notifConfig = getNotificationConfig?.() || {};
    const apiConfig = notifConfig?.api || {};
    const tokenConfig = notifConfig?.tokenManagement || {};
    const sendUrl = apiConfig.tokenSyncEndpoint || opts.sendUrl || '/api/notifications/sync-token';
    const syncRetries = Number.isFinite(tokenConfig.maxSyncRetries) ? tokenConfig.maxSyncRetries : DEFAULT_SYNC_RETRIES;

    await flushPendingTokenSync(sendUrl, { maxRetries: syncRetries });

    if (currentToken && needsSync) {
      const syncReason = tokenChanged ? 'token_changed' : 'heartbeat';
      DebugUtils.moduleLog('messaging', `Token sync needed (${syncReason}), sending update...`);
      // Debounce token sync
      if (_tokenSyncTimeout) clearTimeout(_tokenSyncTimeout);
      _tokenSyncTimeout = setTimeout(async () => {
        try {
          const payload = {
            token: currentToken,
            device_name: clampDeviceName(opts.deviceName, 'web'),
            device_type: opts.deviceType || 'web',
            device_id: getOrCreateDeviceId(),
            sync_reason: syncReason,
            token_observed_at_ms: nowMs
          };
          const effectiveUserId = getEffectiveUserId(opts);
          if (effectiveUserId) payload.userId = effectiveUserId;
          if (tokenChanged && storedToken && storedToken !== currentToken) {
            payload.previous_token = storedToken;
          }

          const synced = await syncTokenToBackend(sendUrl, payload, { maxRetries: syncRetries });
          if (synced) {
            DebugUtils.moduleLog('messaging', `Token sync complete (${syncReason})`);
          } else {
            DebugUtils.moduleWarn('messaging', `Token refresh sync queued (${syncReason})`);
          }
        } catch (e) {
          DebugUtils.moduleWarn('messaging', 'Token refresh sync error:', e?.message);
        }
      }, TOKEN_SYNC_DEBOUNCE);
    }
  } catch (e) {
    DebugUtils.moduleWarn('messaging', 'checkAndRefreshTokenIfNeeded error:', e);
  }
}

export default { isMessagingSupported, obtainAndSendFCMToken, registerServiceWorker, listenForMessages, autoInitializeFCMToken, unsubscribeFCMToken, subscribeToTopic, unsubscribeFromTopic, showForegroundNotification, autoInitializeForegroundListener };
