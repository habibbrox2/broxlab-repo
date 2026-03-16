// v2/notification-system.js
// Minimal notification system adapter for v2 modules
import initFirebase from './init.js';
import { DebugUtils } from './debug.js';
import OfflineNotificationHandler from './offline-handler.js';
import { fetchJson } from '../../js/shared/fetch-utils.js';

const offlineHandler = new OfflineNotificationHandler();

export async function loadUserNotifications() {
  try {
    await initFirebase();
    const { ok, status, data } = await fetchJson('/api/user-notifications', { credentials: 'same-origin' });
    if (!ok) {
      // On server error, queue for retry
      if (status >= 500 || status === 408 || status === 429) {
        offlineHandler.enqueue({ action: 'load_notifications', ts: Date.now() });
      }
      return [];
    }
    return (data && data.notifications) ? data.notifications : [];
  } catch (e) {
    // Network error - queue for retry
    offlineHandler.enqueue({ action: 'load_notifications_error', ts: Date.now() });
    return [];
  }
}

export async function markNotificationAsRead(id) {
  try {
    const { ok, status } = await fetchJson('/api/notification/mark-read', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ notification_id: id }) });
    if (!ok) {
      // Queue for later retry
      if (status >= 500 || status === 408 || status === 429) {
        offlineHandler.enqueue({ action: 'mark_read', notification_id: id, ts: Date.now() });
      }
      return false;
    }
    return true;
  } catch (e) {
    // Queue for retry on network error
    offlineHandler.enqueue({ action: 'mark_read_error', notification_id: id, ts: Date.now() });
    return false;
  }
}

// Backwards-compatible wrappers
export async function broxLoadNotifications() { return loadUserNotifications(); }
export async function broxMarkNotificationRead(id) { return markNotificationAsRead(id); }

export class MultiDeviceSync {
  constructor() {
    this.channel = null;
  }

  async init() {
    try {
      await initFirebase();
      // Multi-device sync is server-driven in current architecture.
      return true;
    } catch (e) {
      DebugUtils.moduleError('sync', 'Failed to initialize multi-device sync');
      return false;
    }
  }
}

export const ForegroundNotifications = {
  config: {
    toastDelay: 5000,
    maxNotifications: 5,
    position: 'top-right',
    playSound: true,
    vibrate: true,
    soundFile: '/assets/sounds/notification.mp3'
  },
  state: {
    queue: [],
    currentNotifications: 0,
    containerElement: null,
    audioElement: null
  },
  init(options = {}) {
    this.config = { ...this.config, ...options };
    if (!this.state.containerElement) {
      this.state.containerElement = this._createContainer();
      document.body.appendChild(this.state.containerElement);
    }
    if (this.config.playSound) {
      this.state.audioElement = new Audio(this.config.soundFile);
    }
    return this;
  },
  show(payload) {
    const { notification = {}, data = {} } = payload || {};
    const title = notification.title || 'Notification';
    const body = notification.body || '';
    const icon = notification.icon || '/assets/logo/icon-192x192.png';

    if (this.state.currentNotifications >= this.config.maxNotifications) {
      this.state.queue.push(payload);
      return;
    }

    if (this.config.playSound && this.state.audioElement) this._playSound();
    if (this.config.vibrate && navigator.vibrate) navigator.vibrate([200, 100, 200]);

    const toastId = `toast-${Date.now()}`;
    const toastElement = this._createToastElement(toastId, title, body, icon, data.click_action || notification.clickAction);
    this.state.containerElement.appendChild(toastElement);
    this.state.currentNotifications += 1;

    if (window.bootstrap?.Toast) {
      const bsToast = new window.bootstrap.Toast(toastElement, {
        autohide: true,
        delay: this.config.toastDelay
      });
      toastElement.addEventListener('hidden.bs.toast', () => {
        this.state.currentNotifications -= 1;
        toastElement.remove();
        if (this.state.queue.length > 0) {
          const next = this.state.queue.shift();
          setTimeout(() => this.show(next), 100);
        }
      });
      bsToast.show();
      return;
    }

    setTimeout(() => {
      toastElement.style.opacity = '0';
      setTimeout(() => {
        this.state.currentNotifications -= 1;
        toastElement.remove();
        if (this.state.queue.length > 0) {
          const next = this.state.queue.shift();
          setTimeout(() => this.show(next), 100);
        }
      }, 300);
    }, this.config.toastDelay);
  },
  _createContainer() {
    const container = document.createElement('div');
    container.id = 'fcm-toast-container';
    container.className = 'fcm-toast-container';
    container.style.cssText = `
      position: fixed;
      ${this.config.position === 'top-right' ? 'top: 20px; right: 20px;' : ''}
      ${this.config.position === 'top-left' ? 'top: 20px; left: 20px;' : ''}
      ${this.config.position === 'bottom-right' ? 'bottom: 20px; right: 20px;' : ''}
      ${this.config.position === 'bottom-left' ? 'bottom: 20px; left: 20px;' : ''}
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 10px;
    `;
    return container;
  },
  _createToastElement(id, title, body, icon, clickAction) {
    const div = document.createElement('div');
    div.id = id;
    div.className = 'toast';
    div.role = 'alert';
    div.setAttribute('aria-live', 'assertive');
    div.setAttribute('aria-atomic', 'true');
    div.style.minWidth = '300px';
    div.style.maxWidth = '500px';
    div.innerHTML = `
      <div class="toast-header bg-primary text-white border-0">
        <img src="${this._escapeHtml(icon)}" alt="icon" class="rounded me-2" style="width: 20px; height: 20px; object-fit: cover;">
        <strong class="me-auto">${this._escapeHtml(title)}</strong>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body">
        ${this._escapeHtml(body)}
      </div>
    `;
    if (clickAction) {
      div.style.cursor = 'pointer';
      div.addEventListener('click', () => window.open(clickAction, '_blank'));
    }
    return div;
  },
  _playSound() {
    try {
      this.state.audioElement.currentTime = 0;
      const playPromise = this.state.audioElement.play();
      if (playPromise !== undefined) playPromise.catch(() => { });
    } catch (err) { }
  },
  _escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
};

if (typeof window !== 'undefined') {
  window.ForegroundNotifications = ForegroundNotifications;
}

export default {
  loadUserNotifications,
  markNotificationAsRead,
  broxLoadNotifications,
  broxMarkNotificationRead,
  MultiDeviceSync,
  ForegroundNotifications
};
