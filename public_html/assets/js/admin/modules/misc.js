/**
 * Misc Admin Module
 * Lazy-loaded via loadAdminModule("misc").
 * Contains small utility/legacy functions that didn't justify separate modules.
 * Only loaded when their respective pages are visited.
 */

const byId = (id) => document.getElementById(id);

export function initNotificationsTopicsManagement() {
  const root = byId('topicsManagementRoot');
  if (!root) return;
  async function load() {
    const res = await fetch('/api/topics/list');
    const data = await res.json();
    const list = byId('topicsList');
    if (!list) return;
    if (data.success) {
      const html = data.topics
        .map(
          (t) =>
            `<div><strong>${t.name}</strong> (${t.slug}) - default: ${t.default_enabled}</div>`
        )
        .join('');
      list.innerHTML = html;
    }
  }
  load();
}

export function initNotificationsSendByTopic() {
  const select = byId('topicSelect');
  if (!select) return;
  async function loadTopics() {
    const res = await fetch('/api/topics/list');
    const j = await res.json();
    if (j.success) {
      select.innerHTML = j.topics
        .map((t) => `<option value="${t.slug}">${t.name}</option>`)
        .join('');
    }
  }

  byId('sendBtn')?.addEventListener('click', async () => {
    const topic = select.value;
    const title = byId('title')?.value || '';
    const message = byId('message')?.value || '';
    const res = await fetch('/api/admin/send-by-topic', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', },
      body: JSON.stringify({ topic, title, message, channels: ['push',], }),
    });
    const j = await res.json();
    if (j.success) alert(`Queued: ${j.notification_id}`);
    else alert(`Error: ${j.error || 'unknown'}`);
  });

  loadTopics();
}

export async function initNotificationsDashboardRealtime() {
  if (!byId('notificationsDashboardRealtime')) return;
  try {
    const [scheduledMod, notificationSystemMod, offlineMod,] = await Promise.all([
      import('/assets/firebase/v2/dist/scheduled-notifications.js'),
      import('/assets/firebase/v2/dist/notification-system.js'),
      import('/assets/firebase/v2/dist/offline-handler.js'),
    ]);

    const ScheduledNotifications = scheduledMod.ScheduledNotifications || scheduledMod.default;
    const MultiDeviceSync =
        notificationSystemMod.MultiDeviceSync || notificationSystemMod.default?.MultiDeviceSync;
    const OfflineNotificationHandler =
        offlineMod.OfflineNotificationHandler || offlineMod.default;

    console.info('Notifications dashboard modules loaded');
    console.info('Available modules:', {
      ScheduledNotifications,
      MultiDeviceSync,
      OfflineNotificationHandler,
    });
  } catch { /* ignore notification module diagnostics */ }
}

export function initNotificationsSubscribersLegacy() {
  const root = byId('notificationsSubscribersLegacyRoot');
  if (!root) return;
  window.applySubsFilter = window.applySubsFilter || function () { };
}

export function initNotificationsDashboardLegacy() {
  const root = byId('notificationsDashboardLegacyRoot');
  if (!root) return;
  window.filterNotifications = window.filterNotifications || function () { };
}

