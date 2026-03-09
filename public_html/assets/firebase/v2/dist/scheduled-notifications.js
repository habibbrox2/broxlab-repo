import {
  fetchJson
} from "./chunks/chunk-3CAKWXPH.js";

// public_html/assets/firebase/v2/scheduled-notifications.js
var ScheduledNotifications = class {
  constructor(baseUrl = "/api/notification/scheduled") {
    this.baseUrl = baseUrl;
  }
  async list(status = "scheduled", page = 1) {
    const { ok, data } = await fetchJson(`${this.baseUrl}?status=${encodeURIComponent(status)}&page=${Number(page)}`, { credentials: "same-origin" });
    if (!ok) return { items: [], total: 0 };
    return data || { items: [], total: 0 };
  }
  async get(id) {
    const { ok, data } = await fetchJson(`/api/notification/scheduled/${encodeURIComponent(id)}`, { credentials: "same-origin" });
    if (!ok) return null;
    return data || null;
  }
  async cancel(id) {
    const { ok } = await fetchJson(`/api/notification/scheduled/${encodeURIComponent(id)}`, { method: "DELETE", credentials: "same-origin" });
    return ok;
  }
};
var scheduled_notifications_default = ScheduledNotifications;
export {
  ScheduledNotifications,
  scheduled_notifications_default as default
};
