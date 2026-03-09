// public_html/assets/firebase/v2/permission-request.js
function getNotificationPermission() {
  if (!("Notification" in window)) {
    return "unsupported";
  }
  return Notification.permission;
}
async function requestNotificationPermission() {
  if (!("Notification" in window)) {
    return {
      success: false,
      permission: "unsupported",
      error: "Notifications not supported in this browser"
    };
  }
  const permission = Notification.permission;
  if (permission === "granted") {
    return { success: true, permission: "granted" };
  }
  if (permission === "denied") {
    return {
      success: false,
      permission: "denied",
      error: "Notification permission denied by user"
    };
  }
  try {
    const result = await Notification.requestPermission();
    return {
      success: result === "granted",
      permission: result
    };
  } catch (error) {
    return {
      success: false,
      permission: "error",
      error: error.message
    };
  }
}
function showPermissionModal(options = {}) {
  const {
    onGranted = () => {
    },
    onDenied = () => {
    },
    autoShow = true
  } = options;
  const permission = getNotificationPermission();
  if (permission === "granted" || permission === "denied") {
    return;
  }
  const modalHtml = `
    <div class="modal fade" id="permissionRequestModal" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title d-flex align-items-center gap-2">
              <i class="bi bi-bell-fill" style="color: #0d6efd; font-size: 1.5rem;"></i>
              \u09AA\u09C1\u09B6 \u09A8\u09CB\u099F\u09BF\u09AB\u09BF\u0995\u09C7\u09B6\u09A8 \u09B8\u0995\u09CD\u09B7\u09AE \u0995\u09B0\u09C1\u09A8
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" id="dismissModalBtn" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="lead">\u0986\u09AE\u09BE\u09A6\u09C7\u09B0 \u09B8\u09B0\u09CD\u09AC\u09B6\u09C7\u09B7 \u0986\u09AA\u09A1\u09C7\u099F \u098F\u09AC\u0982 \u0997\u09C1\u09B0\u09C1\u09A4\u09CD\u09AC\u09AA\u09C2\u09B0\u09CD\u09A3 \u09AC\u09BF\u099C\u09CD\u099E\u09AA\u09CD\u09A4\u09BF \u09B8\u09B0\u09BE\u09B8\u09B0\u09BF \u09AA\u09BE\u09A8\u0964</p>
            
            <div class="alert alert-info d-flex gap-3 mb-3">
              <i class="bi bi-info-circle-fill flex-shrink-0" style="font-size: 1.2rem;"></i>
              <div>
                <strong>\u0986\u09AA\u09A8\u09BF \u09A8\u09BF\u09AF\u09BC\u09A8\u09CD\u09A4\u09CD\u09B0\u09A3\u09C7 \u0986\u099B\u09C7\u09A8</strong><br>
                <small class="text-muted">\u09AF\u09C7\u0995\u09CB\u09A8\u09CB \u09B8\u09AE\u09AF\u09BC \u09A8\u09CB\u099F\u09BF\u09AB\u09BF\u0995\u09C7\u09B6\u09A8 \u09AC\u09A8\u09CD\u09A7 \u0995\u09B0\u09A4\u09C7 \u09AA\u09BE\u09B0\u09AC\u09C7\u09A8</small>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <div class="card border text-center p-3 h-100">
                  <i class="bi bi-bell-fill text-primary mb-2" style="font-size: 2rem;"></i>
                  <h6 class="mb-1">\u0997\u09C1\u09B0\u09C1\u09A4\u09CD\u09AC\u09AA\u09C2\u09B0\u09CD\u09A3 \u0986\u09AA\u09A1\u09C7\u099F</h6>
                  <small class="text-muted">\u09A8\u09A4\u09C1\u09A8 \u09AB\u09BF\u099A\u09BE\u09B0 \u098F\u09AC\u0982 \u09AA\u09B0\u09BF\u09AC\u09B0\u09CD\u09A4\u09A8 \u09B8\u09AE\u09CD\u09AA\u09B0\u09CD\u0995\u09C7 \u099C\u09BE\u09A8\u09C1\u09A8</small>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card border text-center p-3 h-100">
                  <i class="bi bi-megaphone-fill text-success mb-2" style="font-size: 2rem;"></i>
                  <h6 class="mb-1">\u0998\u09CB\u09B7\u09A3\u09BE</h6>
                  <small class="text-muted">\u0997\u09C1\u09B0\u09C1\u09A4\u09CD\u09AC\u09AA\u09C2\u09B0\u09CD\u09A3 \u0998\u09CB\u09B7\u09A3\u09BE \u09AE\u09BF\u09B8 \u0995\u09B0\u09AC\u09C7\u09A8 \u09A8\u09BE</small>
                </div>
              </div>
            </div>

            <div class="alert alert-light border mb-0">
              <small>
                <i class="bi bi-shield-check me-2"></i>
                \u0986\u09AA\u09A8\u09BE\u09B0 \u09AC\u09CD\u09B0\u09BE\u0989\u099C\u09BE\u09B0 \u0986\u09AA\u09A8\u09BE\u09B0 \u0985\u09A8\u09C1\u09AE\u09A4\u09BF \u099B\u09BE\u09A1\u09BC\u09BE \u09A8\u09CB\u099F\u09BF\u09AB\u09BF\u0995\u09C7\u09B6\u09A8 \u09AA\u09BE\u09A0\u09BE\u09A4\u09C7 \u09AA\u09BE\u09B0\u09AC\u09C7 \u09A8\u09BE
              </small>
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-light" id="laterBtn" data-bs-dismiss="modal">\u09AA\u09B0\u09C7 \u099C\u09BF\u099C\u09CD\u099E\u09BE\u09B8\u09BE \u0995\u09B0\u09AC\u09C7\u09A8</button>
            <button type="button" class="btn btn-primary" id="enableBtn">
              <i class="bi bi-check-lg me-2"></i>\u09B8\u0995\u09CD\u09B7\u09AE \u0995\u09B0\u09C1\u09A8
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
  if (!document.getElementById("permissionRequestModal")) {
    const container = document.createElement("div");
    container.innerHTML = modalHtml;
    document.body.appendChild(container.firstElementChild);
  }
  const modalEl = document.getElementById("permissionRequestModal");
  const modal = new bootstrap.Modal(modalEl);
  document.getElementById("enableBtn").addEventListener("click", async () => {
    const result = await requestNotificationPermission();
    modal.hide();
    if (result.success) {
      onGranted(result);
      showSuccessNotification();
    } else {
      onDenied(result);
    }
  });
  document.getElementById("laterBtn").addEventListener("click", () => {
    sessionStorage.setItem("permissionModalDismissed", Date.now().toString());
  });
  if (autoShow) {
    const dismissed = sessionStorage.getItem("permissionModalDismissed");
    const now = Date.now();
    const fiveMinutes = 5 * 60 * 1e3;
    if (!dismissed || now - parseInt(dismissed) > fiveMinutes) {
      modal.show();
    }
  }
  return modal;
}
function showSuccessNotification() {
  const toastHtml = `
    <div class="toast position-fixed bottom-0 end-0 m-3" role="alert" id="permissionSuccessToast">
      <div class="toast-header bg-success text-white">
        <i class="bi bi-check-circle-fill me-2"></i>
        <strong class="me-auto">\u09B8\u09AB\u09B2</strong>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
      </div>
      <div class="toast-body">
        \u09AA\u09C1\u09B6 \u09A8\u09CB\u099F\u09BF\u09AB\u09BF\u0995\u09C7\u09B6\u09A8 \u09B8\u0995\u09CD\u09B7\u09AE \u09B9\u09AF\u09BC\u09C7\u099B\u09C7\u0964 \u0986\u09AA\u09A8\u09BF \u098F\u0996\u09A8 \u09B8\u09AC \u0986\u09AA\u09A1\u09C7\u099F \u09AA\u09BE\u09AC\u09C7\u09A8\u0964
      </div>
    </div>
  `;
  const container = document.createElement("div");
  container.innerHTML = toastHtml;
  document.body.appendChild(container.firstElementChild);
  const toast = new bootstrap.Toast(document.getElementById("permissionSuccessToast"));
  toast.show();
  document.getElementById("permissionSuccessToast").addEventListener("hidden.bs.toast", () => {
    document.getElementById("permissionSuccessToast").remove();
  });
}
function autoInitializePermissionModal() {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      const permission = getNotificationPermission();
      if (permission === "default") {
        showPermissionModal({ autoShow: true });
      }
    });
  } else {
    const permission = getNotificationPermission();
    if (permission === "default") {
      showPermissionModal({ autoShow: true });
    }
  }
}
function showPermissionBanner(options = {}) {
  const {
    onGranted = () => {
    },
    onDenied = () => {
    },
    autoShow = true,
    container = document.body,
    dismissKey = "permissionBannerDismissed",
    dismissDurationMs = 7 * 24 * 60 * 60 * 1e3,
    showIfDenied = false
  } = options;
  const permission = getNotificationPermission();
  if (permission === "granted") return;
  if (permission === "denied" && !showIfDenied) return;
  if (autoShow) {
    try {
      const dismissedAt = localStorage.getItem(dismissKey);
      if (dismissedAt && Date.now() - parseInt(dismissedAt, 10) < dismissDurationMs) {
        return;
      }
    } catch (e) {
    }
  }
  if (!document.getElementById("permissionRequestBannerStyles")) {
    const style = document.createElement("style");
    style.id = "permissionRequestBannerStyles";
    style.textContent = `
      .permission-banner {
        position: fixed;
        left: 16px;
        right: 16px;
        bottom: 16px;
        z-index: 9999;
        background: #0f172a;
        color: #f8fafc;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,.25);
        padding: 14px 16px;
        display: flex;
        gap: 12px;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
      }
      .permission-banner__text {
        display: flex;
        gap: 10px;
        align-items: center;
        font-size: 14px;
        flex: 1 1 240px;
      }
      .permission-banner__actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex: 0 0 auto;
      }
      .permission-banner__btn {
        border: 0;
        border-radius: 8px;
        padding: 8px 12px;
        font-weight: 600;
        cursor: pointer;
      }
      .permission-banner__btn--primary {
        background: #22c55e;
        color: #0b1220;
      }
      .permission-banner__btn--ghost {
        background: transparent;
        color: #e2e8f0;
        border: 1px solid #334155;
      }
      .permission-banner__close {
        background: transparent;
        color: #94a3b8;
        border: 0;
        font-size: 18px;
        cursor: pointer;
        padding: 4px 6px;
      }
      @media (min-width: 768px) {
        .permission-banner {
          left: 24px;
          right: 24px;
          bottom: 24px;
        }
      }
    `;
    document.head.appendChild(style);
  }
  if (document.getElementById("permissionRequestBanner")) return;
  const banner = document.createElement("div");
  banner.id = "permissionRequestBanner";
  banner.className = "permission-banner";
  banner.innerHTML = `
    <div class="permission-banner__text">
      <i class="bi bi-bell-fill" style="color:#38bdf8;font-size:18px;"></i>
      <div>
        <div style="font-weight:700;">\u09A8\u09CB\u099F\u09BF\u09AB\u09BF\u0995\u09C7\u09B6\u09A8 \u0985\u09A8 \u0995\u09B0\u09C1\u09A8</div>
        <div style="opacity:.8;">\u0997\u09C1\u09B0\u09C1\u09A4\u09CD\u09AC\u09AA\u09C2\u09B0\u09CD\u09A3 \u0986\u09AA\u09A1\u09C7\u099F \u0993 \u09A8\u09A4\u09C1\u09A8 \u09AB\u09BF\u099A\u09BE\u09B0 \u09B8\u09AE\u09CD\u09AA\u09B0\u09CD\u0995\u09C7 \u099C\u09BE\u09A8\u09C1\u09A8\u0964</div>
      </div>
    </div>
    <div class="permission-banner__actions">
      <button type="button" class="permission-banner__btn permission-banner__btn--ghost" id="permissionBannerLater">\u09AA\u09B0\u09C7</button>
      <button type="button" class="permission-banner__btn permission-banner__btn--primary" id="permissionBannerEnable">Enable</button>
      <button type="button" class="permission-banner__close" id="permissionBannerClose" aria-label="Close">\xD7</button>
    </div>
  `;
  container.appendChild(banner);
  function dismiss() {
    try {
      localStorage.setItem(dismissKey, Date.now().toString());
    } catch (e) {
    }
    banner.remove();
  }
  document.getElementById("permissionBannerLater")?.addEventListener("click", dismiss);
  document.getElementById("permissionBannerClose")?.addEventListener("click", dismiss);
  document.getElementById("permissionBannerEnable")?.addEventListener("click", async () => {
    const result = await requestNotificationPermission();
    if (result.success) {
      onGranted(result);
      banner.remove();
    } else {
      onDenied(result);
      dismiss();
    }
  });
}
function autoInitializePermissionBanner(options = {}) {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      if (getNotificationPermission() === "default") {
        showPermissionBanner({ autoShow: true, ...options });
      }
    });
  } else {
    if (getNotificationPermission() === "default") {
      showPermissionBanner({ autoShow: true, ...options });
    }
  }
}
function createPermissionButton() {
  const btn = document.createElement("button");
  btn.className = "btn btn-outline-primary";
  btn.innerHTML = '<i class="bi bi-bell me-2"></i>\u09AA\u09C1\u09B6 \u09A8\u09CB\u099F\u09BF\u09AB\u09BF\u0995\u09C7\u09B6\u09A8 \u09B8\u0995\u09CD\u09B7\u09AE \u0995\u09B0\u09C1\u09A8';
  btn.addEventListener("click", () => {
    showPermissionModal({ autoShow: false });
  });
  return btn;
}
function updatePermissionStatus() {
  const permission = getNotificationPermission();
  const statusEl = document.getElementById("permissionStatus");
  if (!statusEl) return;
  let html = "";
  switch (permission) {
    case "granted":
      html = `
        <div class="alert alert-success d-flex align-items-center mb-0">
          <i class="bi bi-check-circle-fill me-2"></i>
          <span>\u09AA\u09C1\u09B6 \u09A8\u09CB\u099F\u09BF\u09AB\u09BF\u0995\u09C7\u09B6\u09A8 \u09B8\u0995\u09CD\u09B7\u09AE</span>
        </div>
      `;
      break;
    case "denied":
      html = `
        <div class="alert alert-warning d-flex align-items-center mb-0">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <span>\u09AA\u09C1\u09B6 \u09A8\u09CB\u099F\u09BF\u09AB\u09BF\u0995\u09C7\u09B6\u09A8 \u0985\u0995\u09CD\u09B7\u09AE (\u09AC\u09CD\u09B0\u09BE\u0989\u099C\u09BE\u09B0 \u09B8\u09C7\u099F\u09BF\u0982\u09B8 \u09AA\u09B0\u09BF\u09AC\u09B0\u09CD\u09A4\u09A8 \u0995\u09B0\u09C1\u09A8)</span>
        </div>
      `;
      break;
    case "default":
      html = `
        <button id="permissionEnableBtn" class="btn btn-sm btn-outline-primary w-100" type="button">
          <i class="bi bi-bell me-1"></i>\u09B8\u0995\u09CD\u09B7\u09AE \u0995\u09B0\u09C1\u09A8
        </button>
      `;
      break;
    default:
      html = `
        <div class="alert alert-secondary d-flex align-items-center mb-0">
          <i class="bi bi-info-circle me-2"></i>
          <span>\u098F\u0987 \u09AC\u09CD\u09B0\u09BE\u0989\u099C\u09BE\u09B0\u09C7 \u09AA\u09C1\u09B6 \u09A8\u09CB\u099F\u09BF\u09AB\u09BF\u0995\u09C7\u09B6\u09A8 \u09B8\u09AE\u09B0\u09CD\u09A5\u09BF\u09A4 \u09A8\u09AF\u09BC</span>
        </div>
      `;
  }
  statusEl.innerHTML = html;
  if (permission === "default") {
    const btn = document.getElementById("permissionEnableBtn");
    if (btn) {
      btn.addEventListener("click", () => {
        showPermissionModal({ autoShow: false });
      });
    }
  }
}
window.PermissionRequest = {
  show: showPermissionModal,
  showBanner: showPermissionBanner,
  request: requestNotificationPermission,
  getStatus: getNotificationPermission,
  updateStatus: updatePermissionStatus,
  autoInit: autoInitializePermissionModal,
  autoInitBanner: autoInitializePermissionBanner
};
export {
  autoInitializePermissionBanner,
  autoInitializePermissionModal,
  createPermissionButton,
  getNotificationPermission,
  requestNotificationPermission,
  showPermissionBanner,
  showPermissionModal,
  updatePermissionStatus
};
