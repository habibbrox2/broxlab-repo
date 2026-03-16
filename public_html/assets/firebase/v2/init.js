// v2/init.js
// Initialize Firebase app singleton using modular SDK (bundled via esbuild)
// Prefetches config non-blockingly on page load
// Exports: initFirebase, getters for app/modules/config, notification config access

import { loadFirebaseConfig, getNotificationConfig, getFullConfig, prefetchAllConfigs } from './config.js';
import { DebugUtils } from './debug.js';

import { initializeApp, getApps, getApp } from 'firebase/app';
import { getAuth } from 'firebase/auth';
import { getMessaging, isSupported as isFirebaseMessagingSupported } from 'firebase/messaging';
import { getAnalytics } from 'firebase/analytics';
import { getRemoteConfig } from 'firebase/remote-config';

let _app = null;
let _modules = null;
let _initialized = false;
let _messagingSupportPromise = null;
let _autoInitPromise = null;

function setMessagingSupportState(supported) {
  if (typeof window === 'undefined') return;
  window.__fcmMessagingSupported = !!supported;
}

async function resolveMessagingSupport() {
  if (_messagingSupportPromise) return _messagingSupportPromise;
  _messagingSupportPromise = (async () => {
    if (typeof window === 'undefined' || typeof navigator === 'undefined') {
      return false;
    }
    if (typeof Notification === 'undefined' || !('serviceWorker' in navigator)) {
      return false;
    }
    try {
      return (await isFirebaseMessagingSupported()) === true;
    } catch (err) {
      return false;
    }
  })();
  return _messagingSupportPromise;
}

// Prefetch config immediately (non-blocking)
// This starts the config fetch as soon as this module loads
if (typeof window !== 'undefined') {
  // Schedule non-blocking prefetch (doesn't wait for fetch)
  Promise.resolve().then(() => prefetchAllConfigs()).catch((err) => {
    // Silent fail on prefetch - will retry during init. Only log if debug enabled
    DebugUtils.moduleWarn('init', 'Config prefetch failed, will retry during initialization');
  });
}

export async function initFirebase(options = {}) {
  if (_initialized) {
    return { app: _app, modules: _modules };
  }

  const cfg = await loadFirebaseConfig(options.configTimeout || 5000).catch(e => {
    DebugUtils.moduleError('init', 'Failed to load Firebase configuration');
    throw e;
  });
  if (!cfg) throw new Error('Firebase configuration not available');

  try {
    // Initialize app if not present
    const apps = getApps ? getApps() : [];
    _app = (apps && apps.length) ? (getApp ? getApp() : apps[0]) : initializeApp(cfg);
    const messagingSupported = await resolveMessagingSupport();
    setMessagingSupportState(messagingSupported);
    if (!messagingSupported) {
      DebugUtils.moduleWarn('init', 'Firebase messaging is not supported in this browser/context');
    }

    _modules = {
      auth: (() => { try { return getAuth(_app); } catch (e) { return null; } })(),
      messaging: messagingSupported
        ? (() => { try { return getMessaging(_app); } catch (e) { return null; } })()
        : null,
      analytics: (() => { try { return getAnalytics(_app); } catch (e) { return null; } })(),
      remoteConfig: (() => { try { return getRemoteConfig(_app); } catch (e) { return null; } })()
    };

    _initialized = true;
    try {
      globalThis?.dispatchEvent?.(new Event('firebase-initialized'));
      if (typeof document !== 'undefined') {
        document.dispatchEvent(new Event('firebase-initialized'));
      }
    } catch (e) { }
    return { app: _app, modules: _modules };
  } catch (err) {
    DebugUtils.moduleError('init', 'Firebase initialization failed');
    throw err;
  }
}

export function getFirebaseApp() { return _app; }
export function getAuthInstance() { return _modules?.auth || null; }
export function getMessagingInstance() { return _modules?.messaging || null; }
export function getAnalyticsInstance() { return _modules?.analytics || null; }
export function getRemoteConfigInstance() { return _modules?.remoteConfig || null; }
export async function getFirebaseConfig(timeout = 5000) { return await loadFirebaseConfig(timeout); }
export function FirebaseModules() { return _modules; }
export function getFirebaseInitPromise() { return _autoInitPromise; }

// ===== Config Getter Exports (from centralized config.js) =====
export { getNotificationConfig, getFullConfig, prefetchAllConfigs } from './config.js';

function autoInitializeFirebaseOnModuleLoad() {
  if (typeof window === 'undefined') return;
  if (window.__APP_FIREBASE_CONFIG?.notifications?.autoInitFirebase === false) return;
  if (window.__APP_CONFIG?.notifications?.autoInitFirebase === false) return;
  if (_autoInitPromise) return;

  _autoInitPromise = initFirebase().catch((err) => {
    DebugUtils.moduleWarn('init', 'Auto Firebase initialization failed:', err?.message || err);
    return null;
  });

  try {
    window.__firebaseInitPromise = _autoInitPromise;
  } catch (e) { }
}

autoInitializeFirebaseOnModuleLoad();

export default initFirebase;
