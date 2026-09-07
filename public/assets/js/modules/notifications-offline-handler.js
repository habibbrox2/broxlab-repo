/**
 * Notifications Offline Handler
 * Extracted from notifications-workflows.js.
 */

import { escapeHtml } from './core.js';

const byId = (id) => document.getElementById(id);

export async function initNotificationsOfflineHandler() {
  if (!byId('offlineHandlerRoot')) return;
  try {
    const mod = await import('/assets/firebase/v2/dist/offline-handler.js');
    const OfflineNotificationHandler = mod.OfflineNotificationHandler || mod.default;
    if (!OfflineNotificationHandler) return;

    const offlineHandler = new OfflineNotificationHandler();
    let offlineModal = null;

    function initializeOfflineModal() {
      if (offlineModal) return;
      const offlineModalElement = byId('offlineModal');
      if (offlineModalElement && typeof broxUI !== 'undefined' && broxUI.Modal) {
        offlineModal = new broxUI.Modal(offlineModalElement);
      }
    }

    async function refreshBuffer() {
      try {
        const buffered = await offlineHandler?.getBufferedNotifications?.() || [];
        const tbody = byId('bufferedTable');
        tbody.innerHTML = '';

        if (buffered.length === 0) {
          tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center text-slate-500 py-4">
                                <i class="lucide lucide-inbox" style="width:1.5rem;height:1.5rem;"></i> No buffered notifications found
                            </td>
                        </tr>
                    `;
          return;
        }

        buffered.forEach(notif => {
          const savedTime = new Date(notif.savedAt).toLocaleString('bn-BD');
          const html = `
                        <tr>
                            <td><code>${notif.id.substring(0, 8)}...</code></td>
                            <td>${escapeHtml(notif.title)}</td>
                            <td><small>${savedTime}</small></td>
                            <td><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800">Buffered</span></td>
                            <td>
                                <button class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-medium border border-red-600 text-red-600 hover:bg-red-50 transition-colors" data-action="remove-buffer" data-notification-id="${notif.id}">
                                    <i class="lucide lucide-trash-2" style="width:1rem;height:1rem;"></i>
                                </button>
                            </td>
                        </tr>
                    `;
          tbody.innerHTML += html;
        });

        byId('bufferCount').textContent = buffered.length;
      } catch (error) {
        console.error('Error:', error);
      }
    }

    async function loadRetryQueue(filter = 'all') {
      try {
        const retries = await offlineHandler?.getRetryQueue?.() || [];
        const tbody = byId('retryQueueTable');
        tbody.innerHTML = '';

        const filtered = filter === 'all' ? retries : retries.filter(r => {
          if (filter === 'pending') return r.status === 'pending';
          if (filter === 'failed') return r.status === 'failed';
          return true;
        });

        if (filtered.length === 0) {
          tbody.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center text-slate-500 py-4">
                                <i class="lucide lucide-inbox" style="width:1.5rem;height:1.5rem;"></i> No retry queue items
                            </td>
                        </tr>
                    `;
          return;
        }

        filtered.forEach(retry => {
          const nextRetry = new Date(retry.nextRetryTime).toLocaleString('bn-BD');
          const statusBadge = retry.status === 'pending'
            ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Pending</span>'
            : '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed</span>';
          const html = `
                        <tr>
                            <td><code>${retry.id.substring(0, 8)}...</code></td>
                            <td>${retry.notificationId.substring(0, 8)}...</td>
                            <td>${retry.retryCount}</td>
                            <td><small>${nextRetry}</small></td>
                            <td>${statusBadge}</td>
                            <td>
                                <button class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-medium border border-indigo-600 text-indigo-600 hover:bg-indigo-50 transition-colors" data-action="force-retry" data-retry-id="${retry.id}">
                                    <i class="lucide lucide-repeat" style="width:1rem;height:1rem;"></i>
                                </button>
                            </td>
                        </tr>
                    `;
          tbody.innerHTML += html;
        });

        byId('retryCount').textContent = filtered.length;
      } catch (error) {
        console.error('Error:', error);
      }
    }

    async function removeFromBuffer(id) {
      try {
        await offlineHandler?.removeFromBuffer?.(id);
        refreshBuffer();
      } catch (error) {
        console.error('Error removing from buffer:', error);
      }
    }

    async function forceRetry(id) {
      try {
        await offlineHandler?.forceRetry?.(id);
        loadRetryQueue();
      } catch (error) {
        console.error('Error forcing retry:', error);
      }
    }

    async function processQueue() {
      try {
        await offlineHandler?.processQueue?.();
        window.showMessage('Queue processed successfully', 'success');
        refreshBuffer();
        loadRetryQueue();
      } catch (error) {
        window.showMessage(`Error: ${error.message}`, 'danger');
      }
    }

    async function clearExpiredCache() {
      try {
        await offlineHandler?.clearExpiredCache?.();
        window.showMessage('Expired cache cleared successfully', 'success');
        refreshBuffer();
      } catch (error) {
        window.showMessage(`Error: ${error.message}`, 'danger');
      }
    }

    async function clearAllBuffer() {
      if (!(await window.showConfirm('Do you want to clear all buffered notifications? This action cannot be undone.'))) return;
      try {
        await offlineHandler?.clearCache?.();
        window.showMessage('All buffered notifications were cleared', 'success');
        refreshBuffer();
      } catch (error) {
        window.showMessage(`Error: ${error.message}`, 'danger');
      }
    }

    function simulateOfflineMode() {
      if (!offlineModal) {
        initializeOfflineModal();
      }
      if (offlineModal) {
        offlineModal.show();
      } else {
        window.showMessage('Offline simulation modal is not available', 'info');
      }
    }

    function applyOfflineMode() {
      const mode = document.querySelector('input[name="offlineMode"]:checked')?.value;
      window.showMessage(`Offline simulation mode set to: ${mode}. Verify behavior in DevTools network panel.`, 'info');
      offlineModal?.hide();
    }

    function filterRetryQueue(filter) {
      loadRetryQueue(filter);
    }

    async function filterDeliveryHistory(filter) {
      try {
        const history = await offlineHandler?.getDeliveryHistory?.() || [];
        const tbody = byId('deliveryHistoryTable');
        tbody.innerHTML = '';

        const filtered = filter === 'all' ? history : history.filter(h => {
          if (filter === 'success') return h.status === 'success';
          if (filter === 'failed') return h.status === 'failed';
          return true;
        });

        if (filtered.length === 0) {
          tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center text-slate-500 py-4">
                                <i class="lucide lucide-inbox" style="width:1.5rem;height:1.5rem;"></i> No delivery history records
                            </td>
                        </tr>
                    `;
          return;
        }

        filtered.forEach(h => {
          const time = new Date(h.timestamp).toLocaleString('bn-BD');
          const statusBadge = h.status === 'success'
            ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Success</span>'
            : '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed</span>';
          const html = `
                        <tr>
                            <td><small>${time}</small></td>
                            <td><code>${h.notificationId.substring(0, 8)}...</code></td>
                            <td>${h.retryCount}</td>
                            <td>${statusBadge}</td>
                            <td>
                                <small class="text-slate-500">${escapeHtml(h.error || 'N/A')}</small>
                            </td>
                        </tr>
                    `;
          tbody.innerHTML += html;
        });
      } catch (error) {
        console.error('Error:', error);
      }
    }

    document.addEventListener('click', (event) => {
      const button = event.target.closest?.('[data-action]');
      if (!button) return;
      const action = button.dataset.action;
      if (action === 'refresh-buffer') return refreshBuffer();
      if (action === 'clear-expired-cache') return clearExpiredCache();
      if (action === 'process-queue') return processQueue();
      if (action === 'simulate-offline') return simulateOfflineMode();
      if (action === 'clear-all-buffer') return clearAllBuffer();
      if (action === 'remove-buffer') return removeFromBuffer(button.dataset.notificationId);
      if (action === 'force-retry') return forceRetry(button.dataset.retryId);
      if (action === 'filter-retry-queue') return filterRetryQueue(button.dataset.filter || 'all');
      if (action === 'filter-history') return filterDeliveryHistory(button.dataset.filter || 'all');
      if (action === 'apply-offline-mode') return applyOfflineMode();
    });

    function initializePageContent() {
      if (typeof broxUI === 'undefined') {
        setTimeout(initializePageContent, 100);
        return;
      }
      initializeOfflineModal();
      refreshBuffer();
      loadRetryQueue();
      filterDeliveryHistory('all');
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initializePageContent);
    } else {
      initializePageContent();
    }
  } catch (e) { /* ignore diagnostics from optional notification module loading */ }
}
