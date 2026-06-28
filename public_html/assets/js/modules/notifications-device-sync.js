/**
 * Notifications Device Sync
 * Extracted from notifications-workflows.js monolith.
 */

import { escapeHtml } from './core.js';
import { getCsrfToken } from '../shared/utils.js';

const byId = (id) => document.getElementById(id);

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
