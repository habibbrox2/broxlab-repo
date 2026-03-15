import {
  offline_handler_default
} from "./chunks/chunk-M6QULICG.js";
import {
  init_default
} from "./chunks/chunk-UEMGXEGC.js";
import {
  fetchJson
} from "./chunks/chunk-3CAKWXPH.js";
import {
  DebugUtils
} from "./chunks/chunk-A5EIDU75.js";

// public_html/assets/firebase/v2/notification-system.js
var offlineHandler = new offline_handler_default();
async function loadUserNotifications() {
  try {
    await init_default();
    const { ok, status, data } = await fetchJson("/api/user-notifications", { credentials: "same-origin" });
    if (!ok) {
      if (status >= 500 || status === 408 || status === 429) {
        offlineHandler.enqueue({ action: "load_notifications", ts: Date.now() });
      }
      return [];
    }
    return data && data.notifications ? data.notifications : [];
  } catch (e) {
    offlineHandler.enqueue({ action: "load_notifications_error", ts: Date.now() });
    return [];
  }
}
async function markNotificationAsRead(id) {
  try {
    const { ok, status } = await fetchJson("/api/notification/mark-read", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ notification_id: id }) });
    if (!ok) {
      if (status >= 500 || status === 408 || status === 429) {
        offlineHandler.enqueue({ action: "mark_read", notification_id: id, ts: Date.now() });
      }
      return false;
    }
    return true;
  } catch (e) {
    offlineHandler.enqueue({ action: "mark_read_error", notification_id: id, ts: Date.now() });
    return false;
  }
}
async function broxLoadNotifications() {
  return loadUserNotifications();
}
async function broxMarkNotificationRead(id) {
  return markNotificationAsRead(id);
}
var MultiDeviceSync = class {
  constructor() {
    this.channel = null;
  }
  async init() {
    try {
      await init_default();
      return true;
    } catch (e) {
      DebugUtils.moduleError("sync", "Failed to initialize multi-device sync");
      return false;
    }
  }
};
var ForegroundNotifications = {
  config: {
    toastDelay: 5e3,
    maxNotifications: 5,
    position: "top-right",
    playSound: true,
    vibrate: true,
    soundFile: "/assets/sounds/notification.mp3"
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
    const title = notification.title || "Notification";
    const body = notification.body || "";
    const icon = notification.icon || "/assets/logo/icon-192x192.png";
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
      toastElement.addEventListener("hidden.bs.toast", () => {
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
      toastElement.style.opacity = "0";
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
    const container = document.createElement("div");
    container.id = "fcm-toast-container";
    container.className = "fcm-toast-container";
    container.style.cssText = `
      position: fixed;
      ${this.config.position === "top-right" ? "top: 20px; right: 20px;" : ""}
      ${this.config.position === "top-left" ? "top: 20px; left: 20px;" : ""}
      ${this.config.position === "bottom-right" ? "bottom: 20px; right: 20px;" : ""}
      ${this.config.position === "bottom-left" ? "bottom: 20px; left: 20px;" : ""}
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 10px;
    `;
    return container;
  },
  _createToastElement(id, title, body, icon, clickAction) {
    const div = document.createElement("div");
    div.id = id;
    div.className = "toast";
    div.role = "alert";
    div.setAttribute("aria-live", "assertive");
    div.setAttribute("aria-atomic", "true");
    div.style.minWidth = "300px";
    div.style.maxWidth = "500px";
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
      div.style.cursor = "pointer";
      div.addEventListener("click", () => window.open(clickAction, "_blank"));
    }
    return div;
  },
  _playSound() {
    try {
      this.state.audioElement.currentTime = 0;
      const playPromise = this.state.audioElement.play();
      if (playPromise !== void 0) playPromise.catch(() => {
      });
    } catch (err) {
    }
  },
  _escapeHtml(text) {
    if (!text) return "";
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }
};
if (typeof window !== "undefined") {
  window.ForegroundNotifications = ForegroundNotifications;
}
var notification_system_default = {
  loadUserNotifications,
  markNotificationAsRead,
  broxLoadNotifications,
  broxMarkNotificationRead,
  MultiDeviceSync,
  ForegroundNotifications
};
export {
  ForegroundNotifications,
  MultiDeviceSync,
  broxLoadNotifications,
  broxMarkNotificationRead,
  notification_system_default as default,
  loadUserNotifications,
  markNotificationAsRead
};
