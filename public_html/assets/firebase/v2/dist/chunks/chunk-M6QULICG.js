import {
  fetchWithTimeout
} from "./chunk-3CAKWXPH.js";

// public_html/assets/firebase/v2/offline-handler.js
var OFFLINE_QUEUE_KEY = "v2_offline_notifications";
var OfflineNotificationHandler = class {
  constructor() {
    this.queue = this._loadQueue();
  }
  _loadQueue() {
    try {
      const raw = localStorage.getItem(OFFLINE_QUEUE_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (e) {
      return [];
    }
  }
  _saveQueue() {
    try {
      localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(this.queue));
    } catch (e) {
    }
  }
  enqueue(item) {
    this.queue.push({ ts: Date.now(), item });
    this._saveQueue();
  }
  async sync() {
    if (!navigator.onLine) return false;
    while (this.queue.length) {
      const entry = this.queue[0];
      try {
        const { ok } = await fetchWithTimeout("/api/notification/send", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json" }, body: JSON.stringify(entry.item) });
        if (!ok) throw new Error("Sync request failed");
        this.queue.shift();
        this._saveQueue();
      } catch (e) {
        return false;
      }
    }
    return true;
  }
};
var offline_handler_default = OfflineNotificationHandler;

export {
  OfflineNotificationHandler,
  offline_handler_default
};
