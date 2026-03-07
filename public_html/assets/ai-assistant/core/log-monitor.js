/**
 * Log Monitor Service
 * Real-time monitoring and display of application logs
 */

export function createLogMonitor({ pollIntervalMs = 10000 } = {}) {
  const state = {
    isPolling: false,
    lastCheckTime: 0,
    lastErrorTimestamp: 0,
    listeners: [],
    logs: {
      errors: [],
      warnings: [],
      all: []
    }
  };

  /**
   * Register a listener for log updates
   */
  function onLogUpdate(callback) {
    if (typeof callback === 'function') {
      state.listeners.push(callback);
    }
  }

  /**
   * Notify all listeners of log updates
   */
  function notifyListeners(eventType, data) {
    state.listeners.forEach(listener => {
      try {
        listener(eventType, data);
      } catch (err) {
        console.error('Log listener error:', err);
      }
    });
  }

  /**
   * Fetch logs from API
   */
  async function fetchLogs(options = {}) {
    const { file = 'errors.log', lines = 20, filter = null } = options;
    
    try {
      const url = new URL('/api/admin/logs/read', window.location.origin);
      url.searchParams.set('file', file);
      url.searchParams.set('lines', lines);
      if (filter) {
        url.searchParams.set('filter', filter);
      }

      const response = await fetch(url.toString(), {
        method: 'GET',
        credentials: 'include',
        headers: {
          'Accept': 'application/json'
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      return await response.json();
    } catch (error) {
      console.error('Failed to fetch logs:', error);
      return { error: error.message, entries: [] };
    }
  }

  /**
   * Get recent errors only
   */
  async function getRecentErrors(limit = 10) {
    try {
      const url = new URL('/api/admin/logs/errors', window.location.origin);
      url.searchParams.set('limit', limit);
      url.searchParams.set('since', state.lastErrorTimestamp);

      const response = await fetch(url.toString(), {
        method: 'GET',
        credentials: 'include',
        headers: {
          'Accept': 'application/json'
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      
      if (data.errors && data.errors.length > 0) {
        // Update last check time
        if (data.latest_timestamp > state.lastErrorTimestamp) {
          state.lastErrorTimestamp = data.latest_timestamp;
          
          // Notify about new errors
          notifyListeners('errors', data.errors);
        }
      }

      return data;
    } catch (error) {
      console.error('Failed to get recent errors:', error);
      return { errors: [], count: 0 };
    }
  }

  /**
   * Get log statistics
   */
  async function getLogStats() {
    try {
      const response = await fetch('/api/admin/logs/stats', {
        method: 'GET',
        credentials: 'include',
        headers: {
          'Accept': 'application/json'
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error('Failed to get log stats:', error);
      return { stats: {} };
    }
  }

  /**
   * List all available logs
   */
  async function listLogs() {
    try {
      const response = await fetch('/api/admin/logs', {
        method: 'GET',
        credentials: 'include',
        headers: {
          'Accept': 'application/json'
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      return await response.json();
    } catch (error) {
      console.error('Failed to list logs:', error);
      return { logs: [] };
    }
  }

  /**
   * Start polling for new errors
   */
  function startPolling() {
    if (state.isPolling) return;
    state.isPolling = true;

    const poll = async () => {
      try {
        await getRecentErrors(5);
      } catch (error) {
        console.error('Error during polling:', error);
      }

      if (state.isPolling) {
        setTimeout(poll, pollIntervalMs);
      }
    };

    poll();
  }

  /**
   * Stop polling for new errors
   */
  function stopPolling() {
    state.isPolling = false;
  }

  /**
   * Format a log entry for display
   */
  function formatLogEntry(entry) {
    const severity = entry.severity || 'INFO';
    const severityColor = {
      'ERROR': '#dc2626',
      'CRITICAL': '#991b1b',
      'WARNING': '#f59e0b',
      'INFO': '#3b82f6',
      'DEBUG': '#8b5cf6'
    }[severity] || '#6b7280';

    return {
      ...entry,
      severity_color: severityColor,
      display_timestamp: new Date(entry.timestamp_unix * 1000).toLocaleString(),
      is_error: severity === 'ERROR' || severity === 'CRITICAL',
      is_warning: severity === 'WARNING'
    };
  }

  /**
   * Create HTML display for a formatted entry
   */
  function createEntryHTML(entry) {
    const formatted = formatLogEntry(entry);
    return `
      <div class="log-entry log-entry-${formatted.severity.toLowerCase()}" data-timestamp="${formatted.timestamp_unix}">
        <div class="log-entry-header">
          <span class="log-severity" style="color: ${formatted.severity_color}">
            [${formatted.severity}]
          </span>
          <span class="log-timestamp">${formatted.display_timestamp}</span>
        </div>
        <div class="log-entry-message">${escapeHtml(formatted.message)}</div>
        ${formatted.context ? `<div class="log-entry-context"><pre>${escapeHtml(formatted.context.substring(0, 500))}</pre></div>` : ''}
      </div>
    `;
  }

  /**
   * Simple HTML escape
   */
  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
  }

  // Public API
  return {
    fetchLogs,
    getRecentErrors,
    getLogStats,
    listLogs,
    onLogUpdate,
    startPolling,
    stopPolling,
    formatLogEntry,
    createEntryHTML,
    isPolling: () => state.isPolling
  };
}

// Auto-export for global access
if (typeof window !== 'undefined') {
  window.LogMonitor = createLogMonitor;
}
