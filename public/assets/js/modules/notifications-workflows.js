/**
 * notifications-workflows.js — Backward-compatible re-export wrapper.
 * Individual notification modules are now in separate files.
 */

export { initNotificationsSend } from './notifications-send.js';
export { initNotificationsScheduled } from './notifications-scheduled.js';
export { initNotificationsDeviceSync } from './notifications-device-sync.js';
export { initNotificationsOfflineHandler } from './notifications-offline-handler.js';
export { initNotificationsList } from './notifications-list.js';
export { initNotificationsView } from './notifications-view.js';
export { initNotificationsDashboard } from './notifications-dashboard.js';
export { initNotificationsDrafts } from './notifications-drafts.js';
