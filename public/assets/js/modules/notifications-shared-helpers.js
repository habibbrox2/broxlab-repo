// Extract from notifications-workflows.js
export async function initNotificationModuleHelpers() {
  try {
    const notificationSystem = await import('/assets/firebase/v2/dist/notification-system.js');
    const analytics = await import('/assets/firebase/v2/dist/analytics.js');
    return { notificationSystem, analytics, };
  } catch {
    return { notificationSystem: null, analytics: null, };
  }
}

/**
 * Parse JSON object from string, returning null on failure.
 */
export function parseJsonObject(raw) {
  if (!raw || !String(raw).trim()) return {};
  try {
    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : null;
  } catch {
    return null;
  }
}

