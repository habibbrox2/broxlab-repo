/**
 * Notifications Scheduled
 * Extracted from notifications-workflows.js.
 */

import { escapeHtml } from './core.js';
import { getCsrfToken } from '../shared/utils.js';

const byId = (id) => document.getElementById(id);

export async function initNotificationsScheduled() {
  if (!byId('scheduledNotificationsRoot')) return;

  let scheduledClient = null;
  try {
    const mod = await import('/assets/firebase/v2/dist/scheduled-notifications.js');
    const ScheduledNotifications = mod.ScheduledNotifications || mod.default;
    if (ScheduledNotifications) {
      scheduledClient = new ScheduledNotifications();
    }
  } catch (e) {
    return;
  }

  const scheduleModalEl = byId('scheduleModal');
  const scheduleModal = scheduleModalEl ? new broxUI.Modal(scheduleModalEl) : null;

  function openScheduleModal() {
    const form = byId('scheduleForm');
    if (form) form.reset();
    handleRecipientTypeChange();
    scheduleModal?.show();
  }

  function handleRecipientTypeChange() {
    const type = byId('recipientType')?.value;
    const div = byId('recipientIdsDiv');
    if (div) div.style.display = type === 'user' ? 'block' : 'none';
  }

  async function submitScheduleForm(event) {
    event.preventDefault();
    const submitBtn = byId('submitBtn');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="lucide lucide-hourglass mr-2" style="width:1rem;height:1rem;"></i>Scheduling...';
    }

    try {
      const date = byId('scheduledDate')?.value;
      const time = byId('scheduledTime')?.value;
      const scheduledAt = `${date}T${time}:00`;

      const channels = [];
      document.querySelectorAll('input[id^="channel-"]:checked').forEach((el) => {
        channels.push(el.value);
      });

      const recipientType = byId('recipientType')?.value;
      let recipientIds = [];
      if (recipientType === 'user') {
        const ids = byId('recipientIds')?.value || '';
        recipientIds = ids.split(',').map((id) => parseInt(id.trim(), 10)).filter((id) => !Number.isNaN(id));
      }

      const result = await scheduledClient.scheduleNotification({
        title: byId('notifTitle')?.value || '',
        body: byId('notifBody')?.value || '',
        scheduled_at: scheduledAt,
        user_timezone: byId('userTimezone')?.value || 'Asia/Dhaka',
        recipient_type: recipientType,
        recipient_ids: recipientIds,
        channels,
      });

      if (result?.success) {
        window.showMessage('Notification scheduled successfully.', 'success');
        scheduleModal?.hide();
        loadScheduledNotifications('scheduled');
      } else {
        window.showMessage(`Failed to schedule notification: ${result?.error || 'Unknown error'}`, 'danger');
      }
    } catch (error) {
      console.error('Error:', error);
      window.showMessage(`Server error: ${error.message}`, 'danger');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="lucide lucide-check mr-2" style="width:1rem;height:1rem;"></i>Schedule';
      }
    }
  }

  function getStatusBadge(status) {
    const badges = {
      scheduled: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800">Scheduled</span>',
      sending: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Sending</span>',
      sent: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Sent</span>',
      failed: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed</span>',
      cancelled: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">Cancelled</span>',
      draft: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">Draft</span>',
    };
    return badges[status] || '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">Unknown</span>';
  }

  async function loadScheduledNotifications(status = 'scheduled') {
    try {
      const response = await fetch(`/api/notification/list-scheduled?status=${encodeURIComponent(status)}&limit=50`);
      const data = await response.json();
      const container = byId(`${status}-list`);
      if (!container) return;
      container.innerHTML = '';

      const list = (data && (data.scheduled || data.notifications)) || [];
      if (!data?.success || !Array.isArray(list) || list.length === 0) {
        container.innerHTML = `
                    <div class="col-12">
                        <div class="text-center py-5 text-slate-500">
                            <i class="lucide lucide-inbox mb-3 block" style="width:3rem;height:3rem;margin:0 auto;"></i>
                            <p>No notifications found.</p>
                        </div>
                    </div>
                `;
        return;
      }

      list.forEach((notif) => {
        const statusBadge = getStatusBadge(notif.status);
        container.innerHTML += `
                    <div class="col-lg-6 mb-4">
                        <div class="admin-panel-card h-100">
                            <div class="admin-panel-card-body flex flex-col">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h5 class="mb-0">${escapeHtml(notif.title)}</h5>
                                        <small class="text-slate-500">${escapeHtml(notif.created_at || '-')}</small>
                                    </div>
                                    ${statusBadge}
                                </div>

                                <p class="text-slate-500 mb-3">${escapeHtml(notif.body || notif.message || '')}</p>

                                <div class="mb-3">
                                    <div class="mb-2">
                                        <small class="text-slate-500"><i class="lucide lucide-calendar mr-1" style="width:0.875rem;height:0.875rem;"></i>Scheduled:</small>
                                        <div class="font-bold text-slate-900">${escapeHtml(notif.scheduled_at || '-')}</div>
                                    </div>
                                    <div>
                                        <small class="text-slate-500"><i class="lucide lucide-users mr-1" style="width:0.875rem;height:0.875rem;"></i>Recipient:</small>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800 capitalize">${escapeHtml(notif.recipient_type || 'all')}</span>
                                    </div>
                                </div>

                                <div class="mt-auto pt-2 border-top">
                                    <div class="btn-group btn-group-sm w-100" role="group">
                                        <button class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 transition-colors" data-action="view-scheduled" data-notification-id="${notif.id}">
                                            <i class="lucide lucide-eye mr-1" style="width:0.875rem;height:0.875rem;"></i>Details
                                        </button>
                                        ${notif.status === 'scheduled' ? `
                                            <button class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 transition-colors" data-action="cancel-scheduled" data-notification-id="${notif.id}">
                                                <i class="lucide lucide-x mr-1" style="width:0.875rem;height:0.875rem;"></i>Cancel
                                            </button>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
      });
    } catch (error) {
      console.error('Error:', error);
      const container = byId(`${status}-list`);
      if (container) {
        container.innerHTML = `
                    <div class="col-12">
                        <div class="p-4 rounded-lg bg-red-50 text-red-700 border border-red-200">
                            <i class="lucide lucide-alert-triangle mr-2" style="width:1rem;height:1rem;"></i>Error: ${escapeHtml(error.message || 'Failed to load')}
                        </div>
                    </div>
                `;
      }
    }
  }

  async function cancelScheduled(id) {
    if (!(await window.showConfirm('Cancel this scheduled notification?'))) return;
    try {
      const response = await fetch(`/api/notification/scheduled/${id}`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken(), },
      });
      const data = await response.json();
      if (data.success) {
        window.showMessage('Schedule cancelled.', 'success');
        loadScheduledNotifications('scheduled');
      } else {
        window.showMessage(`Failed: ${data.message || data.error || 'Unknown error'}`, 'danger');
      }
    } catch (error) {
      console.error('Error:', error);
      window.showMessage('Server error while cancelling.', 'danger');
    }
  }

  function viewScheduledDetail(id) {
    window.showMessage(`Detailed view is coming soon. ID: ${id}`, 'info');
  }

  document.addEventListener('click', (event) => {
    const openBtn = event.target.closest?.('[data-action="open-schedule-modal"]');
    if (openBtn) {
      openScheduleModal();
      return;
    }

    const viewBtn = event.target.closest?.('[data-action="view-scheduled"]');
    if (viewBtn) {
      const id = parseInt(viewBtn.dataset.notificationId, 10);
      if (!Number.isNaN(id)) viewScheduledDetail(id);
      return;
    }

    const cancelBtn = event.target.closest?.('[data-action="cancel-scheduled"]');
    if (cancelBtn) {
      const id = parseInt(cancelBtn.dataset.notificationId, 10);
      if (!Number.isNaN(id)) cancelScheduled(id);
    }
  });

  byId('scheduleForm')?.addEventListener('submit', submitScheduleForm);
  byId('recipientType')?.addEventListener('change', handleRecipientTypeChange);

  handleRecipientTypeChange();
  loadScheduledNotifications('scheduled'); byId('scheduled-tab')?.addEventListener('brox:shown', () => loadScheduledNotifications('scheduled'));
  byId('sent-tab')?.addEventListener('brox:shown', () => loadScheduledNotifications('sent'));
  byId('failed-tab')?.addEventListener('brox:shown', () => loadScheduledNotifications('failed'));
  byId('draft-tab')?.addEventListener('brox:shown', () => loadScheduledNotifications('draft'));
}


export function initNotificationsDeviceSync() {
  const root = byId('deviceSyncRoot');
  if (!root) return;
  let autoSyncInterval = null;

  function getCurrentDeviceId() {
    const key = '__fcm_device_id';
    try {
      const existing = localStorage.getItem(key);
      if (existing) return existing;
      const generated = `${Date.now()}-${Math.random().toString(36).slice(2, 11)}`;
      localStorage.setItem(key, generated);
      return generated;
    } catch (e) {
      return `admin-${Date.now()}`;
    }
  }

  function shortId(value) {
    const text = String(value || '');
    return text ? `${text.substring(0, 8)}...` : 'N/A';
  }

  function getActionBadge(action) {
    const badges = {
      read: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800"><i class="lucide lucide-check-circle mr-1" style="width:0.875rem;height:0.875rem;"></i>Read</span>',
      dismissed: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800"><i class="lucide lucide-x-circle mr-1" style="width:0.875rem;height:0.875rem;"></i>Dismissed</span>',
      deleted: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"><i class="lucide lucide-trash-2 mr-1" style="width:0.875rem;height:0.875rem;"></i>Deleted</span>',
      sync: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800"><i class="lucide lucide-repeat mr-1" style="width:0.875rem;height:0.875rem;"></i>Sync</span>',
    };
    return badges[action] || `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">${escapeHtml(action || 'unknown')}</span>`;
  }

  function getSyncStatusBadge(log) {
    const status = String(log?.status || '').toLowerCase();
    if (status === 'sent' || status === 'success' || status === 'synced' || log?.synced_at) {
      return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Synced</span>';
    }
    if (status === 'failed' || status === 'error') {
      return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed</span>';
    }
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Pending</span>';
  }

  async function parseJsonResponse(response) {
    const raw = await response.text();
    const cleaned = raw.replace(/^\uFEFF/, '').trim();
    try {
      return JSON.parse(cleaned || '{}');
    } catch (e) {
      throw new Error(`Invalid JSON response (${response.status}): ${cleaned.slice(0, 160)}`, { cause: e, });
    }
  }

  function updateSyncStats(data) {
    try {
      const activeDevices = typeof data.count === 'number'
        ? data.count
        : (Array.isArray(data.devices) ? data.devices.length : 0);
      byId('activeDevicesCount').textContent = activeDevices;
      byId('pendingSyncCount').textContent = data.pending_count || 0;
      byId('syncedItemsCount').textContent = data.synced_count || 0;

      const total = (data.pending_count || 0) + (data.synced_count || 0);
      const syncRate = total > 0 ? Math.round(((data.synced_count || 0) / total) * 100) : 0;
      byId('syncRatePercent').textContent = `${syncRate}%`;
    } catch (error) {
      console.error('Error updating stats:', error);
    }
  }

  async function loadDevicesList() {
    try {
      const response = await fetch('/api/notification/device-list');
      const data = await parseJsonResponse(response);

      if (!data.success) {
        throw new Error(data.error || data.message || 'Failed to load device list');
      }

      const tbody = byId('devicesTableBody');
      if (!tbody) return;
      tbody.innerHTML = '';

      if (!Array.isArray(data.devices) || data.devices.length === 0) {
        tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-slate-500 py-4">
                            <i class="lucide lucide-inbox" style="width:1.5rem;height:1.5rem;"></i> No devices found
                        </td>
                    </tr>
                `;
        updateSyncStats(data);
        return;
      }

      data.devices.forEach((device) => {
        const deviceId = String(device.device_id || '');
        const deviceName = device.device_name || device.username || 'Unknown Device';
        const platform = device.device_type || device.platform || 'web';
        const lastSeenRaw = device.last_active || device.last_sync || device.created_at || null;
        const lastSeen = lastSeenRaw ? new Date(lastSeenRaw).toLocaleString('bn-BD') : 'N/A';

        tbody.innerHTML += `
                    <tr>
                        <td>
                            <i class="lucide lucide-phone mr-2" style="width:1rem;height:1rem;"></i>
                            ${escapeHtml(deviceName)}
                        </td>
                        <td><code>${escapeHtml(shortId(deviceId))}</code></td>
                        <td><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-50 text-slate-700">${escapeHtml(platform)}</span></td>
                        <td><small class="text-slate-500">${escapeHtml(lastSeen)}</small></td>
                        <td>
                            <button class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-medium border border-indigo-600 text-indigo-600 hover:bg-indigo-50 transition-colors" onclick="syncDevice('${escapeHtml(deviceId)}')">
                                <i class="lucide lucide-repeat" style="width:1rem;height:1rem;"></i>
                            </button>
                            <button class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-medium border border-red-600 text-red-600 hover:bg-red-50 transition-colors" onclick="removeDevice('${escapeHtml(deviceId)}')">
                                <i class="lucide lucide-trash-2" style="width:1rem;height:1rem;"></i>
                            </button>
                        </td>
                    </tr>
                `;
      });

      updateSyncStats(data);
    } catch (error) {
      console.error('Error:', error);
      const tbody = byId('devicesTableBody');
      if (tbody) {
        tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-red-600 py-4">
                            <i class="lucide lucide-alert-triangle mr-2" style="width:1rem;height:1rem;"></i>${escapeHtml(error.message || 'Failed')}
                        </td>
                    </tr>
                `;
      }
    }
  }

  async function loadSyncLog(filter = 'all') {
    try {
      const response = await fetch(`/api/notification/sync-log?action=${filter !== 'all' ? encodeURIComponent(filter) : ''}`);
      const data = await parseJsonResponse(response);

      const tbody = byId('syncLogBody');
      if (!tbody) return;
      tbody.innerHTML = '';

      if (!data.success || !Array.isArray(data.logs) || data.logs.length === 0) {
        tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-slate-500 py-4">
                            <i class="lucide lucide-inbox" style="width:1.5rem;height:1.5rem;"></i> No sync logs
                        </td>
                    </tr>
                `;
        return;
      }

      data.logs.forEach((log) => {
        const actionBadge = getActionBadge(log.action);
        const statusBadge = getSyncStatusBadge(log);
        const timestampRaw = log.synced_at || log.created_at || null;
        const timestamp = timestampRaw ? new Date(timestampRaw).toLocaleString('bn-BD') : 'N/A';

        tbody.innerHTML += `
                    <tr>
                        <td><small>${escapeHtml(timestamp)}</small></td>
                        <td>${actionBadge}</td>
                        <td><code>${escapeHtml(log.notification_id ?? '-')}</code></td>
                        <td><code>${escapeHtml(shortId(log.device_id || ''))}</code></td>
                        <td>${statusBadge}</td>
                    </tr>
                `;
      });
    } catch (error) {
      console.error('Error:', error);
    }
  }

  async function manualSync() {
    try {
      const response = await fetch('/api/notification/sync-status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken(), },
        body: JSON.stringify({
          device_id: getCurrentDeviceId(),
          device_type: 'web',
          action: 'sync',
        }),
      });
      const data = await parseJsonResponse(response);
      if (data.success) {
        window.showMessage('Sync completed successfully', 'success');
        loadSyncLog();
        loadDevicesList();
      } else {
        window.showMessage(`Sync failed: ${data.error || data.message || 'Unknown error'}`, 'danger');
      }
    } catch (error) {
      console.error('Error:', error);
      window.showMessage('Server error while syncing', 'danger');
    }
  }

  async function syncDevice(deviceId) {
    try {
      const response = await fetch('/api/notification/sync-status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken(), },
        body: JSON.stringify({
          device_id: String(deviceId || ''),
          device_type: 'web',
          action: 'sync',
        }),
      });
      const data = await parseJsonResponse(response);
      if (data.success) {
        window.showMessage('Device synced successfully', 'success');
        loadDevicesList();
        loadSyncLog();
      } else {
        window.showMessage(`Device sync failed: ${data.error || data.message || 'Unknown error'}`, 'danger');
      }
    } catch (error) {
      console.error('Error:', error);
      window.showMessage('Server error while syncing device', 'danger');
    }
  }

  async function removeDevice(deviceId) {
    if (!(await window.showConfirm('Are you sure you want to remove this device?'))) return;
    try {
      const response = await fetch(`/api/notification/devices/${encodeURIComponent(deviceId)}`, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken(), },
      });
      const data = await parseJsonResponse(response);
      if (data.success) {
        window.showMessage('Device removed successfully', 'success');
        loadDevicesList();
        loadSyncLog();
      } else {
        window.showMessage(`Failed to remove device: ${data.error || data.message || 'Unknown error'}`, 'danger');
      }
    } catch (error) {
      console.error('Error:', error);
      window.showMessage('Server error while removing device', 'danger');
    }
  }

  function clearSyncLog() {
    window.showMessage('Clear log API is not configured in backend.', 'info');
    loadSyncLog();
  }

  function filterSyncLog(filter) {
    loadSyncLog(filter);
  }

  function refreshDeviceList() {
    loadDevicesList();
  }

  window.refreshDeviceList = refreshDeviceList;
  window.manualSync = manualSync;
  window.clearSyncLog = clearSyncLog;
  window.filterSyncLog = filterSyncLog;
  window.syncDevice = syncDevice;
  window.removeDevice = removeDevice;

  byId('autoSyncToggle')?.addEventListener('change', function () {
    if (this.checked) {
      manualSync();
      autoSyncInterval = setInterval(manualSync, 30000);
    } else {
      clearInterval(autoSyncInterval);
    }
  });

  loadDevicesList();
  loadSyncLog();
  if (byId('autoSyncToggle')?.checked) {
    autoSyncInterval = setInterval(manualSync, 30000);
  }
}
