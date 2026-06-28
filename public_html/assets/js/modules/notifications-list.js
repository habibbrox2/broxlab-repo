/**
 * Notifications List
 * Extracted from notifications-workflows.js.
 */

import { getCsrfToken } from '../shared/utils.js';

import { initNotificationModuleHelpers } from './notifications-shared-helpers.js';

export async function initNotificationsList() {
  const hasAction = document.querySelector('[data-action="resend-notification"]');
  if (!hasAction) return;
  const { notificationSystem, analytics, } = await initNotificationModuleHelpers();
  const showSuccess = notificationSystem?.showSuccess || window.showSuccess || window.showMessage;
  const showError = notificationSystem?.showError || window.showError || window.showMessage;
  const trackResend = analytics?.trackAdminNotificationResend;

  document.addEventListener('click', (event) => {
    const button = event.target.closest?.('[data-action="resend-notification"]');
    if (!button) return;
    const notificationId = parseInt(button.dataset.notificationId, 10);
    if (Number.isNaN(notificationId)) return;
    fetch('/api/notification/resend', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken(), },
      body: JSON.stringify({ notification_id: notificationId, channels: ['push',], }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) {
          if (trackResend) trackResend(notificationId, data.recipient_count || 0);
          showSuccess?.(data.message || 'Notification resent');
          location.reload();
        } else {
          showError?.(`? Error: ${data.error || 'Unknown error'}`);
        }
      })
      .catch((err) => showError?.(err.message || 'Error resending'));
  });
}
