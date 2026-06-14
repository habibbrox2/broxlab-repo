/**
 * Real-time Monitoring Admin Module
 * Lazy-loaded via loadAdminModule("realtimeMonitoring").
 * Handles live activity feed, active users, system health, alerts.
 */
export function initRealtimeMonitoring() {
  const byId = (id) => document.getElementById(id);

  // Bail out early if not on the realtime-monitoring page
  if (!byId('refreshMainBtn') || !byId('activityFeed')) {
    return;
  }

  let autoRefreshTimer = null;

  async function refreshAllData() {
    const btn = byId('refreshMainBtn');
    if (btn) btn.classList.add('opacity-60', 'cursor-not-allowed', 'pointer-events-none');
    try {
      await Promise.all([
        loadActivityFeed(),
        loadActiveUsers(),
        loadSystemHealth(),
        loadAlerts(),
        loadActivitySummary(),
      ]);
    } catch (e) { console.error('Refresh error:', e); }
    finally { if (btn) btn.classList.remove('opacity-60', 'cursor-not-allowed', 'pointer-events-none'); }
  }

  async function loadActivityFeed() {
    try {
      const r = await fetch('/api/admin/analytics/realtime-feed?limit=20');
      const d = await r.json();
      if (d.success && d.data) {
        const html = d.data.map((a) => {
          let iconClass = 'bg-sky-100 text-sky-600';
          if (a.activity_type === 'login')
            iconClass = a.status === 'success' ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600';
          return '<div class="flex items-start gap-3 px-4 py-3.5 border-b border-slate-100 text-sm last:border-b-0">'
            + `<div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${ iconClass }">`
            + `<i class="lucide lucide-${ a.activity_type === 'login' ? 'log-in' : 'plug' }" style="width:1em;height:1em;"></i></div>`
            + `<div class="min-w-0 flex-1"><div class="font-semibold text-slate-900">${
              a.activity_type === 'login' ? 'User Login' : 'API Call'
            } <span class="text-slate-400 font-normal">(${ a.status })</span></div>`
            + `<div class="mt-0.5 text-xs text-slate-500">${ a.ip_address } � ${ new Date(a.timestamp).toLocaleTimeString() }</div></div></div>`;
        }).join('');
        byId('activityFeed').innerHTML = html || '<div class="text-center text-slate-400 py-8">No recent activity</div>';
      }
    } catch (e) { console.error('Activity feed error:', e); }
  }

  async function loadActiveUsers() {
    try {
      const r = await fetch('/api/admin/analytics/active-users?minutes=15');
      const d = await r.json();
      if (byId('activeUsersCount')) byId('activeUsersCount').textContent = d.count || '0';
      if (byId('activeUsersChange')) byId('activeUsersChange').textContent = d.count ? `${d.count } active now` : 'No active users';
      if (d.success && d.data && d.data.length > 0) {
        byId('activeUsersList').innerHTML = d.data.map((u) =>
          '<span class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm">'
          + `<span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-100 text-blue-600 text-[0.6rem] font-bold">${
            u.username.charAt(0).toUpperCase() }</span><strong>${ u.username }</strong></span>`
        ).join('');
      } else {
        byId('activeUsersList').innerHTML = '<div class="text-center text-slate-400 py-4 w-full">No active users</div>';
      }
    } catch (e) { console.error('Active users error:', e); }
  }

  async function loadSystemHealth() {
    try {
      const r = await fetch('/api/admin/analytics/system-health');
      const d = await r.json();
      if (d.success && d.data) {
        const h = d.data;
        byId('dbStatus').textContent = h.database?.status === 'healthy' ? 'Healthy' : 'Issue';
        byId('dbInfo').textContent = `${h.database?.connected_threads || 0 } threads`;
        const du = h.disk_usage?.usage_percent || 0;
        byId('diskUsage').textContent = `${du.toFixed(1) }%`;
        const bar = byId('diskBar');
        if (bar) { bar.style.width = `${du }%`; bar.style.background = du > 80 ? '#dc2626' : (du > 60 ? '#d97706' : '#16a34a'); }
        byId('phpVersion').textContent = h.php_info?.version || '-';
        byId('phpMemory').textContent = h.php_info?.memory_limit || '-';
      }
    } catch (e) { console.error('System health error:', e); }
  }

  async function loadAlerts() {
    try {
      const r = await fetch('/api/admin/analytics/alerts?minutes=60&limit=10');
      const d = await r.json();
      if (byId('activeAlertsCount')) byId('activeAlertsCount').textContent = d.count || '0';
      if (byId('alertsStatus')) byId('alertsStatus').textContent = d.count > 0 ? `${d.count } alerts` : 'All clear';
      if (d.success && d.data && d.data.length > 0) {
        byId('alertsList').innerHTML = d.data.map((a) => {
          const sev = a.severity || 'warning';
          const clr = sev === 'critical' ? 'border-l-red-500 bg-red-50' : sev === 'warning' ? 'border-l-amber-500 bg-amber-50' : 'border-l-sky-500 bg-sky-50';
          return `<div class="${ clr } border-l-4 rounded-lg p-4 mb-3 last:mb-0">`
            + `<div class="font-bold text-sm text-slate-900 mb-0.5">${ a.message }</div>`
            + `<div class="text-xs text-slate-500">${ a.count ? `${a.count } occurrences` : '' } � Last: ${ new Date(a.last_occurrence).toLocaleTimeString() }</div></div>`;
        }).join('');
      } else {
        byId('alertsList').innerHTML = '<div class="text-center text-slate-400 py-8">No active alerts</div>';
      }
    } catch (e) { console.error('Alerts error:', e); }
  }

  async function loadActivitySummary() {
    try {
      const r = await fetch('/api/admin/analytics/activity-summary?hours=24');
      const d = await r.json();
      if (d.success && d.data) {
        const s = d.data;
        byId('successfulLoginsCount').textContent = s.successful_logins || '0';
        byId('failedLoginsCount').textContent = s.failed_logins || '0';
        const total = (s.successful_logins || 0) + (s.failed_logins || 0);
        const pct = total > 0 ? ((s.successful_logins / total) * 100).toFixed(1) : 0;
        byId('successfulLoginsPercent').textContent = `${pct }% success rate`;
        byId('failedLoginsPercent').textContent = `${(100 - pct).toFixed(1) }% failed`;
      }
    } catch (e) { console.error('Activity summary error:', e); }
  }

  function toggleAutoRefresh() {
    if (byId('autoRefreshToggle') && byId('autoRefreshToggle').checked) startAutoRefresh();
    else stopAutoRefresh();
  }
  function startAutoRefresh() {
    const interval = parseInt(byId('refreshInterval').value) * 1000;
    if (autoRefreshTimer) clearInterval(autoRefreshTimer);
    autoRefreshTimer = setInterval(refreshAllData, interval);
    updateRefreshCountdown();
  }
  function stopAutoRefresh() {
    if (autoRefreshTimer) { clearInterval(autoRefreshTimer); autoRefreshTimer = null; }
    if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
    if (byId('nextRefreshTime')) byId('nextRefreshTime').textContent = '';
  }

  let countdownTimer = null;
  function updateRefreshCountdown() {
    const interval = parseInt(byId('refreshInterval').value) || 10;
    let countdown = interval;
    if (countdownTimer) clearInterval(countdownTimer);
    countdownTimer = setInterval(() => {
      countdown--;
      const el = byId('nextRefreshTime');
      if (countdown > 0 && el) el.textContent = `Next refresh in ${countdown }s`;
      else if (el) { el.textContent = ''; clearInterval(countdownTimer); }
    }, 1000);
  }

  window.refreshAllData = refreshAllData;
  window.toggleAutoRefresh = toggleAutoRefresh;

  refreshAllData();
  startAutoRefresh();
}
