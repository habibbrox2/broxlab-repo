/**
 * Subscribers Admin Module
 * Lazy-loaded via loadAdminModule(subscribers).
 */

export function initNotificationsSubscribers({ byId, getCsrfToken, escapeHtml, }) {
  const root = byId('notificationSubscribersRoot');
  if (!root) return;

  const tableBody = byId('subscribersTableBody');
  const totalCountEl = byId('subsTotalCount');

  const toast = (title, message, type) => {
    if (typeof window.showNotificationToast === 'function') {
      window.showNotificationToast(title, message, type);
    } else if (typeof window.showMessage === 'function') {
      window.showMessage(message, type === 'success' ? 'success' : 'danger');
    } else {
      window.showMessage(message, 'info');
    }
  };

  function getFilters() {
    return {
      recipient: byId('recipientFilter')?.value || 'all',
      permission: byId('permissionFilter')?.value || 'granted',
      search: (byId('searchInput')?.value || '').trim(),
      per_page: byId('perPage')?.value || '20',
    };
  }

  function renderSubscribers(rows) {
    if (!tableBody) return;
    if (!Array.isArray(rows) || rows.length === 0) {
      tableBody.innerHTML =
          '<tr><td colspan="9" class="text-center text-slate-500 py-4">No subscribers found</td></tr>';
      return;
    }

    tableBody.innerHTML = rows
      .map((s) => {
        const id = escapeHtml(s.id ?? '-');
        const deviceId = escapeHtml(s.device_id ?? '');
        const deviceName = escapeHtml(s.device_name ?? '-');
        const token = String(s.token ?? '');
        const tokenShort = escapeHtml(token.length > 40 ? `${token.slice(0, 40)}...` : token);
        const permission = escapeHtml(s.permission ?? 'granted');
        const permClass =
            permission === 'granted'
              ? 'bg-emerald-100 text-emerald-800'
              : permission === 'default'
                ? 'bg-slate-200'
                : 'bg-red-100 text-red-800';
        const type = escapeHtml(s.device_type ?? '-');
        const created = escapeHtml(s.created_at ?? '-');
        const userLabel = s.user_id
          ? `<strong>${escapeHtml(s.username || s.email || `UID:${s.user_id}`)}</strong>`
          : '<span class="text-slate-500">Guest</span>';

        return `
                        <tr>
                            <td>${id}</td>
                            <td>${userLabel}</td>
                            <td><small>${deviceId || '-'}</small></td>
                            <td><small>${deviceName}</small></td>
                            <td><small>${tokenShort || '-'}</small></td>
                            <td><span class="badge ${permClass}">${permission}</span></td>
                            <td>${type}</td>
                            <td><small>${created}</small></td>
                            <td class="text-end">
                                <div class="inline-flex gap-2">
                                    <button class="modern-btn text-xs px-2.5 py-1 bg-red-600 text-white hover:bg-red-700 rounded-lg" onclick="revokeDevice('${deviceId}', this)">Revoke</button>
                                    <button class="modern-btn text-xs px-2.5 py-1 border border-red-400 text-red-600 hover:bg-red-50 rounded-lg" onclick="deleteDevicePermanent('${deviceId}', this)">Delete</button>
                                </div>
                            </td>
                        </tr>
                    `;
      })
      .join('');
  }

  async function reloadSubscribersTable() {
    const filters = getFilters();
    const q = new URLSearchParams();
    if (filters.recipient) q.set('recipient', filters.recipient);
    if (filters.search) q.set('search', filters.search);
    if (filters.permission) q.set('permission', filters.permission);
    if (filters.per_page) q.set('per_page', filters.per_page);

    try {
      const res = await fetch(`/api/admin/notification-subscribers?${q.toString()}`, {
        headers: { 'X-CSRF-Token': getCsrfToken(), },
      });
      const data = await res.json();
      if (!data || !data.success) {
        throw new Error(data?.error || 'Failed to fetch subscribers');
      }
      renderSubscribers(data.subscribers || []);
      if (totalCountEl) totalCountEl.textContent = String(data.pagination?.total ?? 0);
    } catch {
      console.error('Subscribers error');
      toast('Error', 'Subscribers data fetch failed', 'danger');
    }
  }

  window.applySubsFilter = function () {
    const filters = getFilters();
    const q = new URLSearchParams();
    if (filters.recipient) q.set('recipient', filters.recipient);
    if (filters.search) q.set('search', filters.search);
    if (filters.permission) q.set('permission', filters.permission);
    if (filters.per_page) q.set('per_page', filters.per_page);
    window.history.replaceState({}, '', `${window.location.pathname}?${q.toString()}`);
    reloadSubscribersTable();
  };

  window.revokeDevice = async function (deviceId, btn) {
    if (!deviceId) return;
    if (!(await window.showConfirm('Do you want to revoke subscription for this device?'))) return;
    if (btn) btn.disabled = true;
    try {
      const endpoints = [
        '/api/notification/revoke-device',
        '/api/admin/notification-subscribers/revoke',
      ];
      let data = null;
      for (const endpoint of endpoints) {
        try {
          const res = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken(), },
            body: JSON.stringify({ device_id: deviceId, }),
          });
          data = await res.json();
          if (data && data.success) break;
        } catch {
          data = null;
        }
      }
      if (data && data.success) {
        toast('Success', 'Subscription revoked', 'success');
        await reloadSubscribersTable();
      } else {
        toast('Error', data && data.error ? data.error : 'Revoke failed', 'danger');
        if (btn) btn.disabled = false;
      }
    } catch {
      console.error('Network error');
      toast('Error', 'Network error', 'danger');
      if (btn) btn.disabled = false;
    }
  };

  window.deleteDevicePermanent = async function (deviceId, btn) {
    if (!deviceId) return;
    if (!(await window.showConfirm('Delete this device permanently? This action cannot be undone.'))) return;
    if (btn) btn.disabled = true;
    try {
      const res = await fetch('/api/admin/notification-subscribers/revoke', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken(), },
        body: JSON.stringify({ device_id: deviceId, permanent: true, }),
      });
      const data = await res.json();
      if (data.success) {
        toast('Success', 'Device deleted permanently', 'success');
        await reloadSubscribersTable();
      } else {
        toast('Error', data.error || 'Delete failed', 'danger');
        if (btn) btn.disabled = false;
      }
    } catch {
      console.error('Network error');
      toast('Error', 'Network error', 'danger');
      if (btn) btn.disabled = false;
    }
  };

  window.revokeAllDevices = async function (permanent = false) {
    const filters = getFilters();
    const scopeLabel = filters.search
      ? `recipient=${filters.recipient}, search="${filters.search}"`
      : `recipient=${filters.recipient}`;
    const confirmText = permanent
      ? `Delete all filtered devices permanently?\n(${scopeLabel})\nThis action cannot be undone.`
      : `Revoke all filtered devices?\n(${scopeLabel})`;
    if (!(await window.showConfirm(confirmText))) return;

    try {
      const res = await fetch('/api/admin/notification-subscribers/revoke-all', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken(), },
        body: JSON.stringify({
          recipient: filters.recipient,
          search: filters.search,
          permanent,
        }),
      });
      const data = await res.json();
      if (!data || !data.success) {
        throw new Error(data?.error || 'Bulk action failed');
      }

      const affected = Number(data.affected || 0);
      toast('Success', `${affected} device ${permanent ? 'deleted' : 'revoked'}`, 'success');
      await reloadSubscribersTable();
    } catch {
      console.error('Bulk action error');
      toast('Error', 'Bulk action failed', 'danger');
    }
  };

  byId('subsFilterForm')?.addEventListener('submit', (e) => {
    e.preventDefault();
    window.applySubsFilter();
  });
  byId('revokeAllBtn')?.addEventListener('click', () => window.revokeAllDevices(false));
  byId('deleteAllBtn')?.addEventListener('click', () => window.revokeAllDevices(true));

  reloadSubscribersTable();
}

export function initNotificationsPauseResume({ byId, getCsrfToken, }) {
  const form = byId('pauseResumeForm');
  if (!form) return;
  const csrf = getCsrfToken();
  const notify = (message, type = 'success', duration = 5000) => {
    if (typeof window.showToast === 'function') {
      window.showToast(message, type, duration);
      return;
    }
    window.showMessage?.(message, type, duration);
  };

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, },
      body: JSON.stringify(body),
    }).then((r) => r.json());
  }

  byId('pauseBtn')?.addEventListener('click', async () => {
    const id = byId('notification_id')?.value;
    const reason = byId('reason')?.value || '';
    if (!id) {
      notify('Provide notification id', 'error');
      return;
    }
    if (reason && reason.length > 500) {
      notify('Reason too long (max 500 chars)', 'error');
      return;
    }
    const res = await post(`/api/notification/${id}/pause`, { reason, });
    if (res && res.success) notify(res.message || 'Paused');
    else notify(res.error || 'Failed', 'error');
  });

  byId('resumeBtn')?.addEventListener('click', async () => {
    const id = byId('notification_id')?.value;
    if (!id) {
      notify('Provide notification id', 'error');
      return;
    }
    const res = await post(`/api/notification/${id}/resume`, {});
    if (res && res.success) notify(res.message || 'Resumed');
    else notify(res.error || 'Failed', 'error');
  });
}

export function initNotificationsRateLimit({ byId, getCsrfToken, }) {
  const form = byId('rateLimitForm');
  if (!form) return;
  const csrf = getCsrfToken();
  const notify = (message, type = 'success', duration = 5000) => {
    if (typeof window.showToast === 'function') {
      window.showToast(message, type, duration);
      return;
    }
    window.showMessage?.(message, type, duration);
  };

  async function getLimits() {
    const res = await fetch('/api/notification/admin-rate-limit', {
      headers: { 'X-CSRF-Token': csrf, },
    }).then((r) => r.json());
    if (res.success) {
      const limits = res.limits || {};
      byId('currentLimits').innerText = `Current limits: ${JSON.stringify(limits)}`;
      byId('hourly').value = limits.hourly || '';
      byId('daily').value = limits.daily || '';
    } else {
      byId('currentLimits').innerText = 'Error loading limits';
    }
  }

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, },
      body: JSON.stringify(body),
    }).then((r) => r.json());
  }

  byId('saveBtn')?.addEventListener('click', async () => {
    const hourlyRaw = byId('hourly')?.value;
    const dailyRaw = byId('daily')?.value;
    let hourly = null;
    let daily = null;
    if (hourlyRaw !== '') {
      if (isNaN(Number(hourlyRaw)) || Number(hourlyRaw) < 0) {
        notify('Hourly must be a non-negative number', 'error');
        return;
      }
      hourly = parseInt(hourlyRaw, 10);
    }
    if (dailyRaw !== '') {
      if (isNaN(Number(dailyRaw)) || Number(dailyRaw) < 0) {
        notify('Daily must be a non-negative number', 'error');
        return;
      }
      daily = parseInt(dailyRaw, 10);
    }
    if (hourly !== null && daily !== null && daily < hourly) {
      notify('Daily must be greater than or equal to hourly', 'error');
      return;
    }

    const body = {};
    if (hourly !== null) body.hourly = hourly;
    if (daily !== null) body.daily = daily;

    const res = await post('/api/notification/admin-rate-limit', body);
    if (res && res.success) {
      notify(res.message || 'Limits updated');
      getLimits();
    } else {
      notify(res.error || 'Failed to save', 'error');
    }
  });

  getLimits();
}

export function initNotificationsKillSwitch({ byId, }) {
  const toggleBtn = byId('toggleBtn');
  const saveBtn = byId('saveBtn');
  const maintenanceMsg = byId('maintenanceMsg');
  if (!toggleBtn || !saveBtn || !maintenanceMsg) return;

  async function load() {
    const res = await fetch('/api/admin/notifications/kill-switch');
    const data = await res.json();
    if (data.success) {
      toggleBtn.textContent = data.enabled ? 'Enabled' : 'Disabled';
      toggleBtn.dataset.enabled = data.enabled ? '1' : '0';
      maintenanceMsg.value = data.message || '';
    }
  }

  toggleBtn.addEventListener('click', () => {
    const current = toggleBtn.dataset.enabled === '1';
    toggleBtn.dataset.enabled = current ? '0' : '1';
    toggleBtn.textContent = current ? 'Disabled' : 'Enabled';
  });

  saveBtn.addEventListener('click', async () => {
    const enabled = toggleBtn.dataset.enabled === '1' ? 1 : 0;
    const message = maintenanceMsg.value || '';
    const res = await fetch('/api/admin/notifications/kill-switch', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', },
      body: JSON.stringify({ enabled, message, }),
    });
    const j = await res.json();
    if (j.success) window.showMessage('Saved', 'success');
    else window.showMessage('Error', 'danger');
  });

  load();
}

