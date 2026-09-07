/**
 * Notifications Dashboard
 * Extracted from notifications-workflows.js monolith.
 */

import { getCsrfToken } from '../shared/utils.js';

const byId = (id) => document.getElementById(id);

export function initNotificationsDashboard() {
  const root = byId('notificationsDashboardRoot');
  if (!root) return;
  const filterEndpoints = [
    root.dataset.filterEndpoint,
    '/api/notification/list',
    '/api/admin/notifications',
  ].filter(Boolean);

  async function tryFilter(status) {
    for (const endpoint of filterEndpoints) {
      const url = status ? `${endpoint}?status=${encodeURIComponent(status)}` : endpoint;
      try {
        const res = await fetch(url);
        if (!res.ok) continue;
        const data = await res.json();
        if (data && data.success) return true;
      } catch {
        continue;
      }
    }
    return false;
  }

  window.filterNotifications = async function (status = null) {
    const ok = await tryFilter(status);
    if (ok) location.reload();
  };

  window.loadNotificationDetail = function (notifId) {
    const detailContent = byId('detailContent');
    if (!detailContent) return;
    fetch(`/api/notification/${notifId}`)
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          const notif = data.notification;
          detailContent.innerHTML = `
                            <div class="mb-3">
                                <label class="block text-sm font-semibold text-slate-700 mb-1">Title</label>
                                <p>${notif.title}</p>
                            </div>
                            <div class="mb-3">
                                <label class="block text-sm font-semibold text-slate-700 mb-1">Message</label>
                                <p>${notif.message}</p>
                            </div>
                            <div class="row">
                                <div class="w-full md:w-1/2 mb-3">
                                    <label class="block text-sm font-semibold text-slate-700 mb-1">Recipient Type</label>
                                    <p><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800">${notif.recipient_type}</span></p>
                                </div>
                                <div class="w-full md:w-1/2 mb-3">
                                    <label class="block text-sm font-semibold text-slate-700 mb-1">Status</label>
                                    <p><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">${notif.status}</span></p>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="block text-sm font-semibold text-slate-700 mb-1">Delivery Channels</label>
                                <p>
                                    ${notif.channels.includes('push') ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 mr-2"><i class="lucide lucide-smartphone"></i> Push</span>' : ''}
                                    ${notif.channels.includes('email') ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 mr-2"><i class="lucide lucide-mail"></i> Email</span>' : ''}
                                    ${notif.channels.includes('in_app') ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800"><i class="lucide lucide-message-square"></i> In-App</span>' : ''}
                                </p>
                            </div>
                            <div class="row">
                                <div class="w-full md:w-1/2 mb-3">
                                    <label class="block text-sm font-semibold text-slate-700 mb-1">Created At</label>
                                    <p>${new Date(notif.created_at).toLocaleString('bn-BD')}</p>
                                </div>
                                <div class="w-full md:w-1/2 mb-3">
                                    <label class="block text-sm font-semibold text-slate-700 mb-1">Scheduled At</label>
                                    <p>${notif.scheduled_at ? new Date(notif.scheduled_at).toLocaleString('bn-BD') : 'Not scheduled'}</p>
                                </div>
                            </div>
                        `;

          if (data.delivery_logs && data.delivery_logs.length > 0) {
            const rows = data.delivery_logs
              .map(
                (l) => `
                                <tr>
                                    <td>${l.id}</td>
                                    <td>${l.user_id || 'guest'}</td>
                                    <td>${l.device_id || '-'}</td>
                                    <td>${l.channel || '-'}</td>
                                    <td>${l.ip_address || '-'}</td>
                                    <td><small>${l.token || '-'}</small></td>
                                    <td>${l.status}</td>
                                    <td><small>${l.message_id || '-'}</small></td>
                                    <td><small>${l.provider_response ? l.provider_response.substring(0, 200) + (l.provider_response.length > 200 ? '...' : '') : '-'}</small></td>
                                    <td class="small text-slate-500">${l.created_at}</td>
                                </tr>
                            `
              )
              .join('');

            detailContent.innerHTML += `
                                <hr>
                                <h6>Delivery Logs</h6>
                                <div class="table-responsive">
                                    <table class="w-full text-sm border-collapse">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>User</th>
                                                <th>Device</th>
                                                <th>Channel</th>
                                                <th>IP</th>
                                                <th>Token</th>
                                                <th>Status</th>
                                                <th>Message ID</th>
                                                <th>Provider Response</th>
                                                <th>When</th>
                                            </tr>
                                        </thead>
                                        <tbody>${rows}</tbody>
                                    </table>
                                </div>
                            `;
          }
        }
      })
      .catch(() => {
        if (detailContent) {
          detailContent.innerHTML =
            '<div class="p-4 rounded-lg bg-red-50 text-red-700 border border-red-200">Failed to load notification details</div>';
        }
      });
  };

  window.deleteNotification = async function (notifId) {
    if (!(await window.showConfirm('Do you want to delete this notification?'))) return;
    fetch(`/api/notification/${notifId}`, {
      method: 'DELETE',
      headers: { 'X-CSRF-Token': getCsrfToken(), },
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          location.reload();
        } else {
          window.showMessage('Failed to delete notification', 'danger');
        }
      });
  };
}
