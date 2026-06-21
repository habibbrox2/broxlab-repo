/**
 * Server Status Admin Module
 * Lazy-loaded via loadAdminModule("serverStatus").
 * Handles the server health status indicator in the admin panel header.
 * Loaded on every admin page.
 */

export function initServerStatusIndicator() {
  const indicator = document.getElementById('serverStatusIndicator');
  const icon = document.getElementById('serverStatusIcon');
  const text = document.getElementById('serverStatusText');

  if (!indicator || !icon || !text) return;

  let checkInterval = null;
  let isChecking = false;

  const updateStatus = (status, message) => {
    indicator.classList.remove('online', 'offline', 'warning', 'checking');

    switch (status) {
      case 'online':
        indicator.classList.add('online');
        icon.className = 'lucide lucide-server';
        text.textContent = 'Online';
        indicator.title = 'All systems operational';
        break;
      case 'offline':
        indicator.classList.add('offline');
        icon.className = 'lucide lucide-triangle-alert';
        text.textContent = 'Offline';
        indicator.title = message || 'Server offline';
        break;
      case 'warning':
        indicator.classList.add('warning');
        icon.className = 'lucide lucide-triangle-alert';
        text.textContent = 'Warning';
        indicator.title = message || 'Some services may be degraded';
        break;
      case 'checking':
      default:
        indicator.classList.add('checking');
        icon.className = 'lucide lucide-refresh-cw';
        text.textContent = 'Checking...';
        indicator.title = 'Checking server status...';
        break;
    }
  };

  const updateServiceStatus = (serviceName, status, error = null) => {
    const item = document.querySelector(
      `.server-status-item[data-service="${serviceName}"], .admin-status-row[data-service="${serviceName}"]`
    );
    if (!item) return;

    const badge = item.querySelector('.status-badge');
    if (!badge) return;

    badge.setAttribute('data-status', status);
    badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);

    if (error) {
      badge.title = error;
    }
  };

  const updateLastCheck = () => {
    const lastCheckEl = document.getElementById('serverStatusLastCheck');
    if (lastCheckEl) {
      lastCheckEl.textContent = `Last checked: ${new Date().toLocaleString()}`;
    }
  };

  const checkServerStatus = async () => {
    if (isChecking) return;
    isChecking = true;

    try {
      updateStatus('checking');

      const response = await fetch('/api/admin/system-health', {
        method: 'GET',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();

      if (data.success) {
        // Update individual service statuses (Node.js removed)
        const services = ['database', 'cache', 'api',];
        services.forEach((service) => {
          if (data[service]) {
            const status = data[service].check ? 'online' : 'offline';
            const error = data[service].error || null;
            updateServiceStatus(service, status, error);
          }
        });

        // Check if all required services are operational.
        const { database, cache, api, } = data;

        if (database.check && cache.check && api.check) {
          updateStatus('online', 'All systems operational');
        } else if (database.check || cache.check || api.check) {
          updateStatus('warning', 'Some services may be degraded');
        } else {
          updateStatus('offline', 'All services offline');
        }

        updateLastCheck();
      } else {
        updateStatus('offline', 'Server health check failed');
      }
    } catch (error) {
      console.warn('Server status check failed:', error);
      updateStatus('offline', 'Unable to check server status');
    } finally {
      isChecking = false;
    }
  };

  // Initial check
  checkServerStatus();

  // Set up periodic checks (every 30 seconds)
  checkInterval = setInterval(checkServerStatus, 30000);

  // Add click handler to manually refresh
  indicator.addEventListener('click', (e) => {
    e.preventDefault();
    checkServerStatus();
  });

  // Cleanup on page unload
  window.addEventListener('beforeunload', () => {
    if (checkInterval) {
      clearInterval(checkInterval);
    }
  });
}
