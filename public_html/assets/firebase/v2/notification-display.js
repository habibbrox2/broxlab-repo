/**
 * FOREGROUND NOTIFICATION DISPLAY
 * ===============================
 * Displays incoming notifications with toast notifications
 * Shows when app is in foreground (onMessage handler)
 */

/**
 * Show foreground notification toast
 * @param {Object} notification - Notification data
 * @param {string} notification.title - Notification title
 * @param {string} notification.body - Notification body/message
 * @param {string} notification.icon - Icon URL (optional)
 * @param {string} notification.image - Image URL (optional)
 * @param {Object} notification.data - Additional data with action_url, notification_id, etc.
 */
import { escapeHtml, getCsrfToken } from './firebase-utils.js';
import { fetchWithTimeout } from '../../js/shared/fetch-utils.js';

export function displayForegroundNotification(notification = {}) {
  const {
    title = 'Notification',
    body = '',
    icon = '/icon-192x192.png',
    image = null,
    data = {}
  } = notification;

  // Create toast container if not exists
  if (!document.getElementById('notification-toast-container')) {
    const container = document.createElement('div');
    container.id = 'notification-toast-container';
    container.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 10px;
      max-width: 400px;
      pointer-events: none;
    `;
    document.body.appendChild(container);
  }

  // Create toast element
  const toast = document.createElement('div');
  toast.className = 'notification-toast';
  toast.style.cssText = `
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    padding: 16px;
    min-width: 300px;
    max-width: 400px;
    pointer-events: all;
    cursor: pointer;
    transition: all 0.3s ease;
    opacity: 0;
    transform: translateY(-20px);
    animation: slideDown 0.3s ease forwards;
  `;

  // Build toast content
  let content = '';

  // Header with icon and close button
  content += `
    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 8px;">
      <div style="display: flex; align-items: flex-start; gap: 12px; flex: 1;">
        ${icon ? `<img src="${icon}" alt="" style="width: 32px; height: 32px; border-radius: 4px; flex-shrink: 0;">` : ''}
        <div style="flex: 1;">
          <h6 style="margin: 0; font-weight: 600; color: #333; font-size: 14px;">${escapeHtml(title)}</h6>
        </div>
      </div>
      <button class="notification-close-btn" style="
        background: none;
        border: none;
        color: #666;
        cursor: pointer;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        line-height: 1;
        flex-shrink: 0;
      ">×</button>
    </div>
  `;

  // Image if provided
  if (image) {
    content += `
      <img src="${image}" alt="" style="width: 100%; height: auto; border-radius: 4px; margin-bottom: 8px; display: block;">
    `;
  }

  // Body
  if (body) {
    content += `<p style="margin: 0; color: #666; font-size: 13px; line-height: 1.4;">${escapeHtml(body)}</p>`;
  }

  // Action button if action_url provided
  if (data?.action_url) {
    content += `
      <a href="${escapeHtml(data.action_url)}" style="
        display: inline-block;
        margin-top: 8px;
        padding: 6px 12px;
        background: #0d6efd;
        color: white;
        border-radius: 4px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 500;
      ">বিস্তারিত দেখুন →</a>
    `;
  }

  toast.innerHTML = content;

  // Close button handler
  toast.querySelector('.notification-close-btn').addEventListener('click', (e) => {
    e.stopPropagation();
    closeToast(toast);
  });

  // Click handler (go to action or mark as read)
  toast.addEventListener('click', (e) => {
    if (e.target.tagName === 'A') return; // Let link handler work
    if (data?.action_url && !e.target.closest('.notification-close-btn')) {
      window.location.href = data.action_url;
    }
  });

  // Add to container
  const container = document.getElementById('notification-toast-container');
  container.appendChild(toast);

  // Trigger animation
  requestAnimationFrame(() => {
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';
  });

  // Auto-close after 5 seconds
  let closeTimeout = setTimeout(() => closeToast(toast), 5000);

  // Cancel auto-close on hover
  toast.addEventListener('mouseenter', () => clearTimeout(closeTimeout));
  toast.addEventListener('mouseleave', () => {
    closeTimeout = setTimeout(() => closeToast(toast), 5000);
  });

  // Mark notification as read after 3 seconds if notification_id exists
  if (data?.notification_id) {
    setTimeout(() => {
      markNotificationAsReadSilent(data.notification_id);
    }, 3000);
  }
}

/**
 * Close/remove toast notification
 */
function closeToast(toast) {
  toast.style.opacity = '0';
  toast.style.transform = 'translateY(-20px)';

  setTimeout(() => {
    toast.remove();
  }, 300);
}


/**
 * Silently mark notification as read (don't reload)
 */
async function markNotificationAsReadSilent(notificationId) {
  try {
    const csrf = getCsrfToken();
    await fetchWithTimeout('/api/notification/mark-read', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf || ''
      },
      body: JSON.stringify({ notification_id: notificationId }),
      timeoutMs: 5000
    });
  } catch (e) {
    console.warn('Failed to mark notification as read:', e);
  }
}

/**
 * Inject animation styles
 */
function injectStyles() {
  if (document.getElementById('notification-toast-styles')) return;

  const style = document.createElement('style');
  style.id = 'notification-toast-styles';
  style.textContent = `
    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes slideUp {
      from {
        opacity: 1;
        transform: translateY(0);
      }
      to {
        opacity: 0;
        transform: translateY(-20px);
      }
    }

    .notification-toast {
      animation: slideDown 0.3s ease forwards !important;
    }

    .notification-toast.hide {
      animation: slideUp 0.3s ease forwards !important;
    }

    /* Mobile responsive */
    @media (max-width: 576px) {
      #notification-toast-container {
        top: 10px !important;
        right: 10px !important;
        left: 10px !important;
        max-width: none !important;
      }

      .notification-toast {
        min-width: unset !important;
        max-width: 100% !important;
      }
    }
  `;

  document.head.appendChild(style);
}

// Initialize styles on import
injectStyles();

// Export for global use
window.NotificationDisplay = {
  show: displayForegroundNotification,
  close: closeToast
};

export default displayForegroundNotification;
