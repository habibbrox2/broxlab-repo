// /firebase-messaging-sw.js
// Service Worker runs without loading Firebase SDK.
// We handle push events and subscription directly using the Push API.

// ==================================================================
// DEBUG & PRODUCTION CONFIG
// ==================================================================

let DEBUG = false; // Set to true for verbose logging (can be toggled via postMessage)
const FETCH_TIMEOUT_MS = 8000; // Increased timeout
const MAX_RETRIES = 3;

// Centralized logging utility (Service Worker compatible)
function log(...args) {
  if (DEBUG) console.log('[SW]', ...args);
}

function warn(...args) {
  console.warn('[SW]', ...args);
}

function error(...args) {
  console.error('[SW]', ...args);
}

function setDebug(value) {
  DEBUG = !!value;
  log('Debug logging set to', DEBUG);
}

function safeStringify(value, maxLen = 2000) {
  try {
    const str = JSON.stringify(value);
    if (str.length > maxLen) return str.slice(0, maxLen) + '...';
    return str;
  } catch (e) {
    try { return String(value); } catch (err) { return '[unserializable]'; }
  }
}

function summarizePayload(payload) {
  if (!payload || typeof payload !== 'object') return { type: typeof payload };
  const summary = {
    hasNotification: !!payload.notification,
    hasData: !!payload.data,
    hasFcmOptions: !!payload.fcmOptions,
    notificationKeys: payload.notification ? Object.keys(payload.notification) : [],
    dataKeys: payload.data ? Object.keys(payload.data) : [],
    fcmOptionsKeys: payload.fcmOptions ? Object.keys(payload.fcmOptions) : []
  };
  return summary;
}

// ==================================================================
// REQUEST DEDUPLICATION & CSRF PROTECTION
// ==================================================================

const pendingRequests = new Map(); // Track in-flight requests
const CSRF_TOKEN_HEADER = 'X-CSRF-Token';

/**
 * Get CSRF token from self (Service Worker scope)
 * Service Workers can't access document, so tokens must be provided
 * via message from main thread or stored in Cache API
 */
async function getCSRFToken() {
  try {
    // Try to retrieve from Cache API (populated by main thread)
    const cache = await caches.open('csrf-token-cache');
    const response = await cache.match('csrf-token');
    
    if (response) {
      const data = await response.json();
      return data.token || null;
    }
  } catch (e) {
    // Cache API not available or error
    log('CSRF token retrieval from cache failed:', e.message);
  }
  
  return null;
}

/**
 * Deduplicate concurrent requests to same endpoint
 * Returns existing promise if request already in-flight
 */
function fetchWithDedup(key, fetchFn) {
  // Return existing promise if request already pending
  if (pendingRequests.has(key)) {
    log(`Request dedupped for ${key}`);
    return pendingRequests.get(key);
  }
  
  // Execute fetch and store promise
  const promise = fetchFn()
    .then(response => {
      pendingRequests.delete(key);
      return response;
    })
    .catch(err => {
      pendingRequests.delete(key);
      throw err;
    });
  
  pendingRequests.set(key, promise);
  return promise;
}

/**
 * Make API request with CSRF token and deduplication
 */
async function makeAuthenticatedRequest(url, options = {}) {
  const csrfToken = await getCSRFToken();
  
  return {
    ...options,
    headers: {
      ...options.headers,
      'Content-Type': 'application/json',
      ...(csrfToken && { [CSRF_TOKEN_HEADER]: csrfToken })
    }
  };
}

// ==================================================================
// UTILITY FUNCTIONS
// ==================================================================

/**
 * Fetch Firebase config from API (matches firebase-init.js logic)
 * Uses deduplication to prevent concurrent requests
 */
async function fetchConfig() {
  try {
    const res = await fetchWithDedup('firebase-config', () => 
      fetch('/api/firebase-config', { 
        cache: 'no-store',
        headers: { 'Accept': 'application/json' }
      })
    );
    
    if (!res.ok) {
      warn('/api/firebase-config returned', res.status);
      return null;
    }
    
    const body = await res.json().catch(() => null);
    if (!body) return null;
    
    // Support both { success: true, config: {...} } and raw config
    const config = (body.config && typeof body.config === 'object') ? body.config : body;
    log('Fetched Firebase config successfully');
    return config;
  } catch (e) {
    warn('Failed to fetch firebase config', e && e.message ? e.message : e);
    return null;
  }
}

/**
 * Fetch with timeout, retry logic, and deduplication
 */
async function fetchWithRetry(url, options = {}, retries = MAX_RETRIES) {
  // Create dedup key for config requests
  const dedupKey = `retry-${url}`;
  
  return fetchWithDedup(dedupKey, async () => {
    for (let i = 0; i < retries; i++) {
      try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), FETCH_TIMEOUT_MS);

        const response = await fetch(url, {
          ...options,
          signal: controller.signal
        });

        clearTimeout(timeoutId);
        return response;
      } catch (err) {
        if (i === retries - 1) throw err;
        
        // Wait before retry (exponential backoff)
        await new Promise(resolve => setTimeout(resolve, Math.pow(2, i) * 1000));
        log(`Retry attempt ${i + 1} for ${url}`);
      }
    }
  });
}

/**
 * Get notification icon
 */
function getNotificationIcon() {
  return '/assets/images/favicon.ico';
}

/**
 * Get notification badge
 */
function getNotificationBadge() {
  return '/assets/images/favicon.ico';
}

/**
 * Convert VAPID base64 key to Uint8Array for PushManager
 */
function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

// ==================================================================
// EVENT HANDLERS
// ==================================================================

self.addEventListener('push', function(event) {
  log('Push event received', {
    timeStamp: event.timeStamp,
    hasData: !!event.data
  });
  
  event.waitUntil((async () => {
    try {
      let payload = {};
      let rawText = null;
      
      try { 
        if (event.data) {
          try {
            payload = await event.data.json();
          } catch (parseErr) {
            try {
              rawText = await event.data.text();
              payload = rawText ? JSON.parse(rawText) : {};
            } catch (textErr) {
              warn('Failed to parse push data as text:', textErr.message);
              payload = {};
            }
          }
        }
      } catch (e) { 
        warn('Failed to parse push data:', e.message);
        payload = {}; 
      }
      
      log('Push payload summary:', summarizePayload(payload));
      log('Push payload (truncated):', safeStringify(payload, 1200));
      if (rawText) log('Raw push payload text:', rawText.slice(0, 1000));

      // Extract notification data
      const title = payload.notification?.title 
        || payload.fcmOptions?.title 
        || payload.data?.title 
        || 'নোটিফিকেশন';

      if (!title || title === 'নোটিফিকেশন') {
        log('No valid title found in push payload, skipping notification', {
          title,
          summary: summarizePayload(payload)
        });
        return;
      }

      const options = {
        body: payload.notification?.body 
          || payload.data?.body 
          || 'আপনার নতুন বার্তা এসেছে',
        icon: payload.notification?.icon || getNotificationIcon(),
        badge: payload.notification?.badge || getNotificationBadge(),
        tag: payload.data?.tag || 'notification-' + Date.now(),
        requireInteraction: payload.data?.requireInteraction === 'true' || false,
        data: {
          url: payload.fcmOptions?.link 
            || payload.data?.url 
            || payload.data?.click_action 
            || '/',
          notification_id: payload.data?.notification_id || '',
          type: payload.data?.type || 'general',
          ...payload.data
        },
        actions: payload.notification?.actions || []
      };

      // Add vibration pattern if supported
      if ('vibrate' in navigator) {
        options.vibrate = [200, 100, 200];
      }

      // Detect silent / data-only pushes: do not show UI notification
      const isSilent = (payload.data && (payload.data.silent === true || payload.data.silent === '1' || payload.data.type === 'silent')) || (!payload.notification && payload.data && Object.keys(payload.data).length > 0 && (payload.data.silent !== undefined));

      if (isSilent) {
        log('Silent push received; delivering to clients without showing notification', {
          data: payload.data || {},
          url: options.data?.url || '/',
          notification_id: options.data?.notification_id || ''
        });
        // Broadcast to all clients so pages can handle background updates
        const allClients = await clients.matchAll({ includeUncontrolled: true, type: 'window' });
        log('Posting silent payload to clients:', allClients.length);
        for (const client of allClients) {
          try {
            client.postMessage({ type: 'push', payload: payload.data || {} });
          } catch (e) {
            warn('Failed to postMessage to client', e.message);
          }
        }
        return; // do not call showNotification for silent messages
      }

      log('Showing notification:', {
        title,
        body: options.body,
        tag: options.tag,
        url: options.data?.url,
        notification_id: options.data?.notification_id,
        requireInteraction: options.requireInteraction,
        actionsCount: (options.actions || []).length
      });
      return self.registration.showNotification(title, options);
      
    } catch (e) {
      error('Push handler error:', e.message);
    }
  })());
});

self.addEventListener('pushsubscriptionchange', function(event) {
  log('Push subscription changed', {
    hasOld: !!event.oldSubscription,
    oldEndpoint: event.oldSubscription?.endpoint ? event.oldSubscription.endpoint.slice(0, 120) + '...' : null
  });

  event.waitUntil((async () => {
    try {
      // Try to fetch VAPID key from cached config or API
      let vapidKey = null;
      try {
        const config = await fetchConfig();
        vapidKey = config?.vapidKey || null;
      } catch (e) {
        warn('Could not fetch VAPID key for subscription change:', e && e.message ? e.message : e);
      }

      const applicationServerKey = vapidKey ? urlBase64ToUint8Array(vapidKey) : null;
      log('VAPID key availability:', { hasVapidKey: !!vapidKey });

      // Get new subscription
      const newSubscription = await self.registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey
      });
      log('New subscription created', {
        endpoint: newSubscription?.endpoint ? newSubscription.endpoint.slice(0, 120) + '...' : null,
        keys: newSubscription?.toJSON ? Object.keys(newSubscription.toJSON()) : []
      });

      // Send new subscription to server with deduplication and CSRF
      await fetchWithDedup('update-fcm-subscription', async () => {
        // Try to read device meta (device_id/device_name) from cache set by main thread
        let deviceMeta = null;
        try {
          const cache = await caches.open('csrf-token-cache');
          const resp = await cache.match('device-meta');
          if (resp) deviceMeta = await resp.json();
        } catch (e) {
          warn('Failed to read device meta from cache:', e.message);
        }

        const bodyPayload = {
          old_endpoint: event.oldSubscription?.endpoint,
          new_subscription: newSubscription
        };

        if (deviceMeta && deviceMeta.device_id) bodyPayload.device_id = deviceMeta.device_id;
        if (deviceMeta && deviceMeta.device_name) bodyPayload.device_name = deviceMeta.device_name;

        const requestOptions = await makeAuthenticatedRequest('/api/update-fcm-subscription', {
          method: 'POST',
          body: JSON.stringify(bodyPayload),
          keepalive: true
        });

        return fetch('/api/update-fcm-subscription', requestOptions);
      });

      log('Subscription updated successfully');
    } catch (err) {
      error('Failed to update subscription:', err && err.message ? err.message : err);
    }
  })());
});

self.addEventListener('notificationclick', function(event) {
  log('Notification clicked', {
    action: event.action || null,
    title: event.notification?.title,
    tag: event.notification?.tag,
    data: event.notification?.data ? safeStringify(event.notification.data, 800) : null
  });
  event.notification.close();
  
  const urlToOpen = event.notification.data?.url || '/';
  const notificationId = event.notification.data?.notification_id;

  event.waitUntil((async () => {
    try {
      // Track notification click with deduplication and CSRF
      if (notificationId) {
        try {
          await fetchWithDedup(`track-click-${notificationId}`, async () => {
            const requestOptions = await makeAuthenticatedRequest('/api/notification/track-click', {
              method: 'POST',
              body: JSON.stringify({ 
                notification_id: notificationId,
                clicked_at: new Date().toISOString()
              }),
              keepalive: true
            });
            
            return fetch('/api/notification/track-click', requestOptions);
          });
          log('Notification click tracked', { notification_id: notificationId });
        } catch (err) {
          warn('Failed to track notification click:', err.message);
        }
      }

      // Handle action buttons
      if (event.action) {
        log('Action button clicked:', { action: event.action, url: urlToOpen });
        // Handle specific actions here
        return clients.openWindow(urlToOpen + '?action=' + event.action);
      }

      // Try to focus existing window
      const clientList = await clients.matchAll({ 
        type: 'window', 
        includeUncontrolled: true 
      });
      log('Available client windows:', clientList.length);

      for (const client of clientList) {
        if (client.url.includes(urlToOpen) && 'focus' in client) {
          log('Focusing existing client', { url: client.url });
          return client.focus();
        }
      }

      // Open new window if no existing window found
      if (clients.openWindow) {
        log('Opening new window', { url: urlToOpen });
        return clients.openWindow(urlToOpen);
      }
    } catch (err) {
      error('Notification click handler error:', err.message);
    }
  })());
});

self.addEventListener('notificationclose', function(event) {
  log('Notification closed', {
    title: event.notification?.title,
    tag: event.notification?.tag,
    notification_id: event.notification?.data?.notification_id || null
  });
  
  const notificationId = event.notification.data?.notification_id;
  
  if (notificationId) {
    // Track notification dismissal with deduplication and CSRF
    event.waitUntil((async () => {
      try {
        await fetchWithDedup(`track-dismiss-${notificationId}`, async () => {
          const requestOptions = await makeAuthenticatedRequest('/api/notification/track-dismiss', {
            method: 'POST',
            body: JSON.stringify({ 
              notification_id: notificationId,
              dismissed_at: new Date().toISOString()
            }),
            keepalive: true
          });
          
          return fetch('/api/notification/track-dismiss', requestOptions);
        });
      } catch (err) {
        warn('Failed to track notification dismissal:', err.message);
      }
    })());
  }
});

// No Firebase SDK initialization in Service Worker — push events are handled
// directly by this worker. We intentionally avoid loading the Firebase
// compat SDK here to keep the SW small and focused on push handling.
    


// ==================================================================
// SERVICE WORKER LIFECYCLE
// ==================================================================

self.addEventListener('install', function(event) {
  log('Service Worker installing...');
  
  // Skip waiting to activate immediately
  self.skipWaiting();
  
  event.waitUntil(
    caches.open('firebase-config-cache').then(cache => {
      log('Cache opened');
      return cache;
    }).then(() => {
      return self.clients.matchAll({ includeUncontrolled: true }).then(clients => {
        for (const client of clients) {
          try { client.postMessage({ type: 'sw-install', message: 'Service Worker installed' }); } catch (e) {}
        }
      });
    })
  );
});

self.addEventListener('activate', function(event) {
  log('Service Worker activating...');
  
  event.waitUntil(
    clients.claim().then(() => {
      log('Service Worker activated and claimed clients');
      return self.clients.matchAll({ includeUncontrolled: true }).then(clients => {
        for (const client of clients) {
          try { client.postMessage({ type: 'sw-activate', message: 'Service Worker activated' }); } catch (e) {}
        }
      });
    })
  );
});

// ==================================================================
// MESSAGE HANDLER (for communication with main thread)
// ==================================================================

self.addEventListener('message', function(event) {
  log('Message received from client:', event.data);
  
  if (event.data && event.data.type === 'SET_SW_DEBUG') {
    setDebug(!!event.data.enabled);
  }

  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  
  if (event.data && event.data.type === 'GET_VERSION') {
    event.ports[0].postMessage({ version: '1.0.0' });
  }
  
  // Handle CSRF token storage (from main thread)
  if (event.data && event.data.type === 'STORE_CSRF_TOKEN') {
    (async () => {
      try {
        const cache = await caches.open('csrf-token-cache');
        await cache.put('csrf-token', new Response(JSON.stringify({
          token: event.data.token,
          timestamp: Date.now()
        })));
        log('CSRF token stored in cache');
      } catch (e) {
        warn('Failed to store CSRF token:', e.message);
      }
    })();
  }
  
  // Handle device metadata storage (device_id/device_name) from main thread
  if (event.data && event.data.type === 'STORE_DEVICE_META') {
    (async () => {
      try {
        const cache = await caches.open('csrf-token-cache');
        await cache.put('device-meta', new Response(JSON.stringify({
          device_id: event.data.device_id,
          device_name: event.data.device_name,
          timestamp: Date.now()
        })));
        log('Device meta stored in cache');
      } catch (e) {
        warn('Failed to store device meta:', e.message);
      }
    })();
  }
});
