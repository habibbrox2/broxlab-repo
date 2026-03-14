/**
 * FIREBASE PERMISSION REQUEST COMPONENT
 * =====================================
 * Handles permission request modal/banner for notification permissions
 * Shows UI to request user's consent for push notifications
 */

/**
 * Check current notification permission status
 * @returns {string} 'granted' | 'denied' | 'default'
 */
export function getNotificationPermission() {
  if (!('Notification' in window)) {
    return 'unsupported';
  }
  return Notification.permission;
}

/**
 * Request notification permission from user
 * @returns {Promise<{success: boolean, permission: string, error?: string}>}
 */
export async function requestNotificationPermission() {
  if (!('Notification' in window)) {
    return { 
      success: false, 
      permission: 'unsupported',
      error: 'Notifications not supported in this browser' 
    };
  }
  
  const permission = Notification.permission;
  
  if (permission === 'granted') {
    return { success: true, permission: 'granted' };
  }
  
  if (permission === 'denied') {
    return { 
      success: false, 
      permission: 'denied',
      error: 'Notification permission denied by user' 
    };
  }
  
  // Request permission ('default' state)
  try {
    const result = await Notification.requestPermission();
    return { 
      success: result === 'granted', 
      permission: result 
    };
  } catch (error) {
    return { 
      success: false, 
      permission: 'error',
      error: error.message 
    };
  }
}

/**
 * Show permission request modal
 * @param {Object} options - Configuration options
 * @param {Function} options.onGranted - Callback when permission granted
 * @param {Function} options.onDenied - Callback when permission denied
 * @param {boolean} options.autoShow - Auto-show on page load
 */
export function showPermissionModal(options = {}) {
  const {
    onGranted = () => {},
    onDenied = () => {},
    autoShow = true
  } = options;
  
  // Skip if already granted or denied
  const permission = getNotificationPermission();
  if (permission === 'granted' || permission === 'denied') {
    return;
  }
  
  // Create modal HTML
  const modalHtml = `
    <div class="modal fade" id="permissionRequestModal" tabindex="-1" data-bs-backdrop="static">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title d-flex align-items-center gap-2">
              <i class="bi bi-bell-fill" style="color: #0d6efd; font-size: 1.5rem;"></i>
              পুশ নোটিফিকেশন সক্ষম করুন
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" id="dismissModalBtn" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="lead">আমাদের সর্বশেষ আপডেট এবং গুরুত্বপূর্ণ বিজ্ঞপ্তি সরাসরি পান।</p>
            
            <div class="alert alert-info d-flex gap-3 mb-3">
              <i class="bi bi-info-circle-fill flex-shrink-0" style="font-size: 1.2rem;"></i>
              <div>
                <strong>আপনি নিয়ন্ত্রণে আছেন</strong><br>
                <small class="text-muted">যেকোনো সময় নোটিফিকেশন বন্ধ করতে পারবেন</small>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <div class="card border text-center p-3 h-100">
                  <i class="bi bi-bell-fill text-primary mb-2" style="font-size: 2rem;"></i>
                  <h6 class="mb-1">গুরুত্বপূর্ণ আপডেট</h6>
                  <small class="text-muted">নতুন ফিচার এবং পরিবর্তন সম্পর্কে জানুন</small>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card border text-center p-3 h-100">
                  <i class="bi bi-megaphone-fill text-success mb-2" style="font-size: 2rem;"></i>
                  <h6 class="mb-1">ঘোষণা</h6>
                  <small class="text-muted">গুরুত্বপূর্ণ ঘোষণা মিস করবেন না</small>
                </div>
              </div>
            </div>

            <div class="alert alert-light border mb-0">
              <small>
                <i class="bi bi-shield-check me-2"></i>
                আপনার ব্রাউজার আপনার অনুমতি ছাড়া নোটিফিকেশন পাঠাতে পারবে না
              </small>
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-light" id="laterBtn" data-bs-dismiss="modal">পরে জিজ্ঞাসা করবেন</button>
            <button type="button" class="btn btn-primary" id="enableBtn">
              <i class="bi bi-check-lg me-2"></i>সক্ষম করুন
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
  
  // Inject modal into DOM if not already present
  if (!document.getElementById('permissionRequestModal')) {
    const container = document.createElement('div');
    container.innerHTML = modalHtml;
    document.body.appendChild(container.firstElementChild);
  }
  
  // Get modal element
  const modalEl = document.getElementById('permissionRequestModal');
  const modal = new bootstrap.Modal(modalEl);
  
  // Event listeners
  document.getElementById('enableBtn').addEventListener('click', async () => {
    const result = await requestNotificationPermission();
    modal.hide();
    if (result.success) {
      onGranted(result);
      showSuccessNotification();
    } else {
      onDenied(result);
    }
  });
  
  document.getElementById('laterBtn').addEventListener('click', () => {
    // Remember user clicked "later" to avoid showing again soon
    sessionStorage.setItem('permissionModalDismissed', Date.now().toString());
  });
  
  // Show modal if not recently dismissed
  if (autoShow) {
    const dismissed = sessionStorage.getItem('permissionModalDismissed');
    const now = Date.now();
    const fiveMinutes = 5 * 60 * 1000;
    
    if (!dismissed || (now - parseInt(dismissed)) > fiveMinutes) {
      modal.show();
    }
  }
  
  return modal;
}

/**
 * Show success notification after permission granted
 */
function showSuccessNotification() {
  // Create toast element
  const toastHtml = `
    <div class="toast position-fixed bottom-0 end-0 m-3" role="alert" id="permissionSuccessToast">
      <div class="toast-header bg-success text-white">
        <i class="bi bi-check-circle-fill me-2"></i>
        <strong class="me-auto">সফল</strong>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
      </div>
      <div class="toast-body">
        পুশ নোটিফিকেশন সক্ষম হয়েছে। আপনি এখন সব আপডেট পাবেন।
      </div>
    </div>
  `;
  
  const container = document.createElement('div');
  container.innerHTML = toastHtml;
  document.body.appendChild(container.firstElementChild);
  
  const toast = new bootstrap.Toast(document.getElementById('permissionSuccessToast'));
  toast.show();
  
  // Remove after hide
  document.getElementById('permissionSuccessToast').addEventListener('hidden.bs.toast', () => {
    document.getElementById('permissionSuccessToast').remove();
  });
}

/**
 * Auto-initialize permission modal on page load
 * Call this from your main layout/init script
 */
export function autoInitializePermissionModal() {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      const permission = getNotificationPermission();
      
      // Only show if permission is not decided yet
      if (permission === 'default') {
        showPermissionModal({ autoShow: true });
      }
    });
  } else {
    // DOM already loaded
    const permission = getNotificationPermission();
    if (permission === 'default') {
      showPermissionModal({ autoShow: true });
    }
  }
}

/**
 * Create a dismissible banner to request notification permission
 * Shows only when permission is 'default' unless showIfDenied is true
 */
export function showPermissionBanner(options = {}) {
  const {
    onGranted = () => {},
    onDenied = () => {},
    autoShow = true,
    container = document.body,
    dismissKey = 'permissionBannerDismissed',
    dismissDurationMs = 7 * 24 * 60 * 60 * 1000,
    showIfDenied = false
  } = options;

  const permission = getNotificationPermission();
  if (permission === 'granted') return;
  if (permission === 'denied' && !showIfDenied) return;

  // Check dismissal TTL
  if (autoShow) {
    try {
      const dismissedAt = localStorage.getItem(dismissKey);
      if (dismissedAt && (Date.now() - parseInt(dismissedAt, 10)) < dismissDurationMs) {
        return;
      }
    } catch (e) { /* ignore storage errors */ }
  }

  if (!document.getElementById('permissionRequestBannerStyles')) {
    const style = document.createElement('style');
    style.id = 'permissionRequestBannerStyles';
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

  if (document.getElementById('permissionRequestBanner')) return;

  const banner = document.createElement('div');
  banner.id = 'permissionRequestBanner';
  banner.className = 'permission-banner';
  banner.innerHTML = `
    <div class="permission-banner__text">
      <i class="bi bi-bell-fill" style="color:#38bdf8;font-size:18px;"></i>
      <div>
        <div style="font-weight:700;">নোটিফিকেশন অন করুন</div>
        <div style="opacity:.8;">গুরুত্বপূর্ণ আপডেট ও নতুন ফিচার সম্পর্কে জানুন।</div>
      </div>
    </div>
    <div class="permission-banner__actions">
      <button type="button" class="permission-banner__btn permission-banner__btn--ghost" id="permissionBannerLater">পরে</button>
      <button type="button" class="permission-banner__btn permission-banner__btn--primary" id="permissionBannerEnable">Enable</button>
      <button type="button" class="permission-banner__close" id="permissionBannerClose" aria-label="Close">×</button>
    </div>
  `;

  container.appendChild(banner);

  function dismiss() {
    try { localStorage.setItem(dismissKey, Date.now().toString()); } catch (e) {}
    banner.remove();
  }

  document.getElementById('permissionBannerLater')?.addEventListener('click', dismiss);
  document.getElementById('permissionBannerClose')?.addEventListener('click', dismiss);

  document.getElementById('permissionBannerEnable')?.addEventListener('click', async () => {
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

/**
 * Auto-initialize permission banner on page load
 */
export function autoInitializePermissionBanner(options = {}) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      if (getNotificationPermission() === 'default') {
        showPermissionBanner({ autoShow: true, ...options });
      }
    });
  } else {
    if (getNotificationPermission() === 'default') {
      showPermissionBanner({ autoShow: true, ...options });
    }
  }
}

/**
 * Create a button/banner to request permissions
 * Can be placed in UI for manual triggering
 */
export function createPermissionButton() {
  const btn = document.createElement('button');
  btn.className = 'btn btn-outline-primary';
  btn.innerHTML = '<i class="bi bi-bell me-2"></i>পুশ নোটিফিকেশন সক্ষম করুন';
  btn.addEventListener('click', () => {
    showPermissionModal({ autoShow: false });
  });
  return btn;
}

/**
 * Check permission and update UI status
 */
export function updatePermissionStatus() {
  const permission = getNotificationPermission();
  const statusEl = document.getElementById('permissionStatus');
  
  if (!statusEl) return;
  
  let html = '';
  
  switch (permission) {
    case 'granted':
      html = `
        <div class="alert alert-success d-flex align-items-center mb-0">
          <i class="bi bi-check-circle-fill me-2"></i>
          <span>পুশ নোটিফিকেশন সক্ষম</span>
        </div>
      `;
      break;
    
    case 'denied':
      html = `
        <div class="alert alert-warning d-flex align-items-center mb-0">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <span>পুশ নোটিফিকেশন অক্ষম (ব্রাউজার সেটিংস পরিবর্তন করুন)</span>
        </div>
      `;
      break;
    
    case 'default':
      html = `
        <button id="permissionEnableBtn" class="btn btn-sm btn-outline-primary w-100" type="button">
          <i class="bi bi-bell me-1"></i>সক্ষম করুন
        </button>
      `;
      break;
    
    default:
      html = `
        <div class="alert alert-secondary d-flex align-items-center mb-0">
          <i class="bi bi-info-circle me-2"></i>
          <span>এই ব্রাউজারে পুশ নোটিফিকেশন সমর্থিত নয়</span>
        </div>
      `;
  }
  
  statusEl.innerHTML = html;
  if (permission === 'default') {
    const btn = document.getElementById('permissionEnableBtn');
    if (btn) {
      btn.addEventListener('click', () => {
        showPermissionModal({ autoShow: false });
      });
    }
  }
}

/**
 * Export for global use in templates
 */
window.PermissionRequest = {
  show: showPermissionModal,
  showBanner: showPermissionBanner,
  request: requestNotificationPermission,
  getStatus: getNotificationPermission,
  updateStatus: updatePermissionStatus,
  autoInit: autoInitializePermissionModal,
  autoInitBanner: autoInitializePermissionBanner
};
