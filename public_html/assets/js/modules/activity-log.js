/**
 * Activity Log Admin Module
 * Lazy-loaded via loadAdminModule('activityLog').
 */

export function initActivityLog({ byId, escapeHtml, getCsrfToken, }) {
  const tbody = byId('log-table-body');
  if (!tbody) return;

  let currentPage = 1;
  let perPage = 50;
  const currentSort = { by: 'created_at', order: 'DESC', };
  let totalRecords = 0;
  let totalPages = 1;
  let activityEnabled = tbody.dataset.activityEnabled === 'true';

  function parseBrowserInfo(userAgent) {
    if (!userAgent) return 'Unknown';
    let browser = 'Unknown';
    let os = 'Unknown';
    let version = '';
    if (userAgent.includes('Edge')) {
      browser = 'Edge';
      const match = userAgent.match(/Edge\/(\d+)/);
      if (match) version = match[1];
    } else if (userAgent.includes('Chrome')) {
      browser = 'Chrome';
      const match = userAgent.match(/Chrome\/(\d+)/);
      if (match) version = match[1];
    } else if (userAgent.includes('Safari')) {
      browser = 'Safari';
      const match = userAgent.match(/Version\/(\d+)/);
      if (match) version = match[1];
    } else if (userAgent.includes('Firefox')) {
      browser = 'Firefox';
      const match = userAgent.match(/Firefox\/(\d+)/);
      if (match) version = match[1];
    } else if (userAgent.includes('Opera')) {
      browser = 'Opera';
      const match = userAgent.match(/Version\/(\d+)/);
      if (match) version = match[1];
    } else if (userAgent.includes('MSIE') || userAgent.includes('Trident')) {
      browser = 'Internet Explorer';
      const match = userAgent.match(/MSIE (\d+)/) || userAgent.match(/rv:(\d+)/);
      if (match) version = match[1];
    }

    if (userAgent.includes('Windows')) {
      if (userAgent.includes('Windows NT 10.0')) os = 'Windows 10/11';
      else if (userAgent.includes('Windows NT 6.3')) os = 'Windows 8.1';
      else if (userAgent.includes('Windows NT 6.2')) os = 'Windows 8';
      else if (userAgent.includes('Windows NT 6.1')) os = 'Windows 7';
      else os = 'Windows';
    } else if (userAgent.includes('Mac OS X')) os = 'macOS';
    else if (userAgent.includes('Linux')) os = 'Linux';
    else if (userAgent.includes('iPhone') || userAgent.includes('iPad')) os = 'iOS';
    else if (userAgent.includes('Android')) os = 'Android';

    let info = browser;
    if (version) info += ` ${version}`;
    info += ` (${os})`;
    return info;
  }

  function renderLog(log) {
    const time = new Date(log.created_at).toLocaleString();
    const statusClass = log.status === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800';
    const username = escapeHtml(log.username) || `#${escapeHtml(String(log.user_id || '0'))}`;
    let browserInfo = 'Unknown';
    if (log.details && log.details._browser) browserInfo = log.details._browser;
    else if (log.user_agent) browserInfo = parseBrowserInfo(log.user_agent);

    const row = document.createElement('tr');
    row.dataset.id = log.id;
    row.innerHTML = `
                <td class="log-time">${time}</td>
                <td class="log-user"><i class="lucide lucide-user-circle mr-2" style="width:1rem;height:1rem;"></i>${username}</td>
                <td class="log-action">${escapeHtml(String(log.action))}</td>
                <td><span class="resource-type">${escapeHtml(log.resource_type || 'N/A')} <strong>#${escapeHtml(String(log.resource_id || 'N/A'))}</strong></span></td>
                <td><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">${log.status}</span></td>
                <td class="ip-badge" title="${escapeHtml(log.user_agent || 'N/A')}">
                    <div style="font-size: 0.8rem; color: #333; font-weight: 500;">${escapeHtml(log.ip_address || 'N/A')}</div>
                    <div style="font-size: 0.75rem; color: #999;">${browserInfo}</div>
                </td>
            `;
    row.addEventListener('click', (e) => {
      if (e.target.tagName !== 'A') showLogDetailsModal(log);
    });
    return row;
  }

  function showLogDetailsModal(log) {
    const time = new Date(log.created_at).toLocaleString();
    const statusClass = log.status === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800';
    const username = log.username || `#${log.user_id || '0'}`;
    byId('modalLogId').textContent = log.id;
    byId('modalLogTime').textContent = time;
    byId('modalLogUser').textContent = username;
    byId('modalLogRole').textContent = log.role;
    byId('modalLogAction').textContent = log.action;
    byId('modalLogResource').textContent =
        `${log.resource_type || 'N/A'} #${log.resource_id || 'N/A'}`;
    byId('modalLogStatus').innerHTML = `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">${escapeHtml(log.status)}</span>`;
    byId('modalLogIp').textContent = log.ip_address || 'N/A';
    byId('modalLogAgent').textContent = log.user_agent || 'N/A';

    let browserInfo = 'Not available';
    if (log.details && log.details._browser) browserInfo = log.details._browser;
    else if (log.user_agent) browserInfo = parseBrowserInfo(log.user_agent);
    byId('modalLogBrowser').innerHTML =
        `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800">${escapeHtml(browserInfo)}</span>`;

    const detailsJson = log.details
      ? JSON.stringify(log.details, null, 2)
      : 'No additional details';
    byId('modalLogDetails').textContent = detailsJson;
    window.currentLogJson = JSON.stringify(log, null, 2);
    const modal = new broxUI.Modal(byId('logDetailsModal'));
    modal.show();
  }

  async function fetchLogs(page = 1) {
    const q = byId('searchBox')?.value?.trim() || '';
    const status = byId('filterStatus')?.value || '';
    const user = byId('filterUser')?.value?.trim() || '';
    const resource = byId('filterResource')?.value?.trim() || '';

    const params = new URLSearchParams({
      page,
      perPage,
      sort_by: currentSort.by,
      sort_order: currentSort.order,
    });
    if (q) params.set('q', q);
    if (status) params.set('status', status);
    if (user) params.set('user_id', user);
    if (resource) params.set('resource_type', resource);

    try {
      const res = await fetch(`/api/log-activity?${params.toString()}`);
      const data = await res.json();

      tbody.innerHTML = '';
      if (data.logs && data.logs.length) {
        data.logs.forEach((log) => tbody.appendChild(renderLog(log)));
      } else {
        tbody.innerHTML =
            '<tr><td colspan="6" class="empty-state"><div><i class="lucide lucide-inbox mx-auto mb-2"></i><p>No logs found</p></div></td></tr>';
      }

      currentPage = data.page;
      totalRecords = data.total;
      totalPages = data.totalPages;

      const start = (currentPage - 1) * perPage + 1;
      const end = Math.min(currentPage * perPage, totalRecords);
      byId('startRecord').textContent = totalRecords > 0 ? start : 0;
      byId('endRecord').textContent = end;
      byId('totalRecord').textContent = totalRecords;
      byId('pageIndicator').textContent = `Page ${currentPage} of ${totalPages}`;

      byId('prevPage').disabled = currentPage <= 1;
      byId('nextPage').disabled = currentPage >= totalPages;
    } catch {
      console.error('Error fetching logs');
      tbody.innerHTML =
          '<tr><td colspan="6" class="empty-state"><i class="lucide lucide-alert-circle mx-auto mb-2"></i> Error loading logs</td></tr>';
    }
  }

  document.querySelectorAll('thead th[data-sort]').forEach((th) => {
    th.addEventListener('click', () => {
      const sortBy = th.dataset.sort;
      const sortSpan = byId(`sort-${sortBy}`);
      document.querySelectorAll('.sort-indicator').forEach((s) => {
        s.textContent = '';
        s.classList.remove('active');
      });

      if (currentSort.by === sortBy) {
        currentSort.order = currentSort.order === 'DESC' ? 'ASC' : 'DESC';
      } else {
        currentSort.by = sortBy;
        currentSort.order = 'DESC';
      }

      if (sortSpan) {
        sortSpan.classList.add('active');
        sortSpan.textContent = currentSort.order === 'DESC' ? 'v' : '^';
      }

      currentPage = 1;
      fetchLogs();
    });
  });

  byId('prevPage')?.addEventListener('click', () => fetchLogs(currentPage - 1));
  byId('nextPage')?.addEventListener('click', () => fetchLogs(currentPage + 1));
  byId('btnRefresh')?.addEventListener('click', () => {
    currentPage = 1;
    fetchLogs(1);
  });

  ['searchBox', 'filterStatus', 'filterUser', 'filterResource',].forEach((id) => {
    byId(id)?.addEventListener('change', () => {
      currentPage = 1;
      fetchLogs(1);
    });
  });

  byId('perPageSelect')?.addEventListener('change', (e) => {
    perPage = parseInt(e.target.value, 10);
    currentPage = 1;
    fetchLogs(1);
  });

  byId('exportCsv')?.addEventListener('click', (e) => {
    e.preventDefault();
    exportLogs('csv');
  });
  byId('exportJson')?.addEventListener('click', (e) => {
    e.preventDefault();
    exportLogs('json');
  });

  function exportLogs(format) {
    const q = byId('searchBox')?.value?.trim() || '';
    const status = byId('filterStatus')?.value || '';
    const user = byId('filterUser')?.value?.trim() || '';
    const resource = byId('filterResource')?.value?.trim() || '';

    const params = new URLSearchParams({ format, });
    if (q) params.set('q', q);
    if (status) params.set('status', status);
    if (user) params.set('user_id', user);
    if (resource) params.set('resource_type', resource);

    window.location.href = `/api/log-activity/export?${params.toString()}`;
  }

  byId('modalCopyJson')?.addEventListener('click', () => {
    if (!window.currentLogJson) return;
    navigator.clipboard
      .writeText(window.currentLogJson)
      .then(() => {
        const btn = byId('modalCopyJson');
        if (!btn) return;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="lucide lucide-check mr-2" style="width:1rem;height:1rem;"></i>Copied!';
        btn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
        btn.classList.add('bg-green-600', 'hover:bg-green-700', 'text-white');
        setTimeout(() => {
          btn.innerHTML = originalText;
          btn.classList.remove('bg-green-600', 'hover:bg-green-700', 'text-white');
          btn.classList.add('bg-indigo-600', 'hover:bg-indigo-700', 'text-white');
        }, 2000);
      })
      .catch(() => {
        window.showMessage('Failed to copy to clipboard', 'danger');
      });
  });

  byId('toggleActivity')?.addEventListener('click', async () => {
    const target = !activityEnabled;
    try {
      const res = await fetch('/api/log-activity/toggle', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken(), },
        body: JSON.stringify({ enabled: target, }),
      });
      const data = await res.json();
      if (data.success) {
        activityEnabled = Boolean(data.enabled);
        byId('toggleActivityLabel').textContent = activityEnabled
          ? 'Activity: ON'
          : 'Activity: OFF';
        window.showMessage(
          data.message ||
            (activityEnabled ? 'Activity logging enabled' : 'Activity logging disabled'),
          'success'
        );
      } else {
        window.showMessage(data.message || 'Failed to update activity logging', 'danger');
      }
    } catch {
      window.showMessage('Error updating activity logging', 'danger');
    }
  });

  fetchLogs(1);
}
