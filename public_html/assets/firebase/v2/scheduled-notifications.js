// v2/scheduled-notifications.js
// Client-side helper to interact with scheduled notifications API and provide a small class used by admin UI.

import { fetchJson } from '../../js/shared/fetch-utils.js';

export class ScheduledNotifications {
  constructor(baseUrl = '/api/notification/scheduled') {
    this.baseUrl = baseUrl;
  }

  async list(status = 'scheduled', page = 1) {
    const { ok, data } = await fetchJson(`${this.baseUrl}?status=${encodeURIComponent(status)}&page=${Number(page)}`, { credentials: 'same-origin' });
    if (!ok) return { items: [], total: 0 };
    return data || { items: [], total: 0 };
  }

  async get(id) {
    const { ok, data } = await fetchJson(`/api/notification/scheduled/${encodeURIComponent(id)}`, { credentials: 'same-origin' });
    if (!ok) return null;
    return data || null;
  }

  async cancel(id) {
    const { ok } = await fetchJson(`/api/notification/scheduled/${encodeURIComponent(id)}`, { method: 'DELETE', credentials: 'same-origin' });
    return ok;
  }
}

export default ScheduledNotifications;
