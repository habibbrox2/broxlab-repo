// v2/offline-handler.js
// Minimal offline notification queue and sync helper for v2 modules.
import { DebugUtils } from './debug.js';
import { fetchWithTimeout } from '../../js/shared/fetch-utils.js';

const OFFLINE_QUEUE_KEY = 'v2_offline_notifications';

export class OfflineNotificationHandler {
  constructor() {
    this.queue = this._loadQueue();
  }

  _loadQueue() {
    try {
      const raw = localStorage.getItem(OFFLINE_QUEUE_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (e) { return []; }
  }

  _saveQueue() {
    try { localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(this.queue)); } catch (e) { }
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
        // Post to canonical server admin send endpoint
        const { ok } = await fetchWithTimeout('/api/notification/send', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(entry.item) });
        if (!ok) throw new Error('Sync request failed');
        this.queue.shift();
        this._saveQueue();
      } catch (e) {
        // Offline sync will retry next time
        return false;
      }
    }
    return true;
  }
}

export default OfflineNotificationHandler;
