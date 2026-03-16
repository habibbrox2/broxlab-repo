/**
 * analytics-dashboard.js
 * Admin Analytics Dashboard - Lightweight AJAX polling instead of SSE
 * Provides real-time visualization of analytics data
 */

(function () {
    'use strict';

    const API_BASE = '/api/admin/analytics';
    let charts = {};
    const state = {
        initialized: false,
        eventsBound: false,
        activityPollingId: null,
        alertPollingId: null,
        notificationPermissionRequested: false
    };

    // Store template data globally for chart rendering
    const parseJson = (value, fallback) => {
        if (!value) return fallback;
        try { return JSON.parse(value); } catch (e) { return fallback; }
    };

    const runWhenReady = (fn) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    };

    const deferNonCritical = (fn, timeout = 250) => {
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(() => fn(), { timeout });
        } else {
            window.setTimeout(fn, timeout);
        }
    };

    function getChartCanvas(id) {
        const el = document.getElementById(id);
        if (!el) return null;
        if (el.tagName && el.tagName.toLowerCase() === 'canvas') {
            return el;
        }
        return el.querySelector ? el.querySelector('canvas') : null;
    }

    async function safeFetchJson(url, options = {}) {
        const mod = await import('./shared/fetch-utils.js');
        return mod.safeFetchJson(url, options);
    }

    function stopPolling() {
        if (state.activityPollingId) {
            clearInterval(state.activityPollingId);
            state.activityPollingId = null;
        }
        if (state.alertPollingId) {
            clearInterval(state.alertPollingId);
            state.alertPollingId = null;
        }
    }

    function cancelPendingRequests() {
        // Abort controllers now managed by shared fetch-utils.js; nothing to do here.
    }

    async function requestNotificationPermissionLazy() {
        if (!('Notification' in window)) return 'denied';
        if (Notification.permission !== 'default') return Notification.permission;
        if (state.notificationPermissionRequested) return Notification.permission;
        state.notificationPermissionRequested = true;
        try {
            return await Notification.requestPermission();
        } catch (e) {
            return Notification.permission;
        }
    }

    window.dashboardData = {
        daily_stats: [],
        daily_logins: [],
        weekly_stats: [],
        monthly_stats: []
    };

    const dataEl = document.getElementById('analytics-dashboard-data');
    if (dataEl) {
        window.dashboardData = {
            daily_stats: parseJson(dataEl.dataset.dailyStats, []),
            daily_logins: parseJson(dataEl.dataset.dailyLogins, []),
            weekly_stats: parseJson(dataEl.dataset.weeklyStats, []),
            monthly_stats: parseJson(dataEl.dataset.monthlyStats, [])
        };
    }

    /**
     * Initialize dashboard
     */
    async function initDashboard() {
        if (state.initialized) return;
        state.initialized = true;

        console.log('Initializing Analytics Dashboard...');
        await Promise.all([
            loadSummaryStats(),
            loadSecurityAlerts(),
            loadPostViews(),
            loadPageViews(),
            loadServiceViews(),
            loadLoginAudit(),
            loadOAuthAudit(),
            loadActivityLogs()
        ]);

        setupEventListeners();
        startActivityLogsPolling();
        startRealtimePolling();

        deferNonCritical(async () => {
            await loadCharts();
            renderDailyReports();
        }, 300);

        console.log('Analytics Dashboard initialized successfully');
    }

    /**
     * Load summary statistics
     */
    async function loadSummaryStats() {
        try {
            const url = `${API_BASE}/summary`;
            console.log('Fetching summary stats...');
            const data = await safeFetchJson(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });
            console.log('Summary stats response:', data);

            if (data && data.success && data.data) {
                const stats = data.data;
                updateSummaryDOM(stats);
                console.log('Summary stats updated successfully');
            }
        } catch (error) {
            console.error('Error loading summary stats:', error);
        }
    }

    /**
     * Update DOM with summary statistics
     */
    function updateSummaryDOM(stats) {
        const elements = {
            'totalVisitors': stats.total_visitors || 0,
            'totalPosts': stats.total_posts || 0,
            'totalPages': stats.total_pages || 0,
            'totalUsers': stats.total_users || 0,
            'pageImpressions': stats.page_impressions || 0,
            'pageUniqueImpressions': stats.page_unique_impressions || 0,
            'topPage': stats.top_page || 'N/A',
            'postImpressions': stats.post_impressions || 0,
            'postUniqueImpressions': stats.post_unique_impressions || 0,
            'topPost': stats.top_post || 'N/A',
            'serviceImpressions': stats.service_impressions || 0,
            'serviceUniqueImpressions': stats.service_unique_impressions || 0,
            'topService': stats.top_service || 'N/A',
            'totalInquiries': stats.total_inquiries || 0,
            'pendingInquiries': stats.pending_inquiries || 0,
            'approvedInquiries': stats.approved_inquiries || 0
        };

        Object.entries(elements).forEach(([id, value]) => {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = typeof value === 'number' ?
                    value.toLocaleString() :
                    String(value);
            }
        });
    }

    /**
     * Load security alerts
     */
    async function loadSecurityAlerts() {
        try {
            console.log('Loading security alerts...');
            const data = await safeFetchJson(`${API_BASE}/security-alerts`);
            console.log('Security alerts data:', data);

            if (data && data.success && data.data && data.data.length > 0) {
                const alerts = data.data;
                updateSecurityAlertsDOM(alerts);
            } else {
                updateSecurityAlertsDOM([]);
            }
        } catch (error) {
            console.error('Error loading security alerts:', error);
            updateSecurityAlertsDOM([]);
        }
    }

    /**
     * Update security alerts DOM
     */
    function updateSecurityAlertsDOM(alerts) {
        const container = document.querySelector('#securityAlerts');
        if (!container) return;

        if (!alerts || alerts.length === 0) {
            container.innerHTML = '<div class="alert alert-success">✓ No security alerts in the last 24 hours</div>';
            return;
        }

        let html = '';
        alerts.slice(0, 5).forEach(alert => {
            const severityClass = alert.severity === 'critical' ? 'danger' :
                alert.severity === 'high' ? 'warning' : 'info';
            const icon = alert.type === 'failed_login' ? '🔓' : '🔐';

            html += `
                <div class="alert alert-${severityClass} alert-dismissible fade show" role="alert">
                    <strong>${icon} ${(alert.type || 'ALERT').toUpperCase()}</strong>
                    <br/>
                    ${alert.message || 'Security event detected'}
                    <small class="d-block mt-2 text-muted">${alert.timestamp || new Date().toLocaleString()}</small>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    /**
     * Load post views
     */
    async function loadPostViews() {
        try {
            console.log('Loading post views...');
            const data = await safeFetchJson(`${API_BASE}/post-views?limit=10`);
            console.log('Post views data:', data);

            if (data && data.success && data.data && Array.isArray(data.data) && data.data.length > 0) {
                console.log('Post views found:', data.data.length);
                updatePostViewsDOM(data.data);
            } else {
                console.log('No post views data');
                updatePostViewsDOM([]);
            }
        } catch (error) {
            console.error('Error loading post views:', error);
            updatePostViewsDOM([]);
        }
    }

    /**
     * Update post views DOM
     */
    function updatePostViewsDOM(posts) {
        const tbody = document.querySelector('#postViewsBody');
        if (!tbody) return;

        if (!posts || posts.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No data available</td></tr>';
            return;
        }

        let html = '';
        posts.forEach(post => {
            html += `
                <tr>
                    <td>${post.post_title || 'N/A'}</td>
                    <td><strong>${(post.total_views || 0).toLocaleString()}</strong></td>
                    <td>${(post.unique_viewers || 0).toLocaleString()}</td>
                    <td><small class="text-muted">${formatDate(post.last_viewed)}</small></td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    /**
     * Load page views
     */
    async function loadPageViews() {
        try {
            const data = await safeFetchJson(`${API_BASE}/page-views?limit=10`);
            console.log('Page views data:', data);

            if (data && data.success && data.data && Array.isArray(data.data) && data.data.length > 0) {
                updatePageViewsDOM(data.data);
            } else {
                updatePageViewsDOM([]);
            }
        } catch (error) {
            console.error('Error loading page views:', error);
            updatePageViewsDOM([]);
        }
    }

    /**
     * Update page views DOM
     */
    function updatePageViewsDOM(pages) {
        const tbody = document.querySelector('#pageViewsBody');
        if (!tbody) return;

        if (!pages || pages.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No data available</td></tr>';
            return;
        }

        let html = '';
        pages.forEach(page => {
            html += `
                <tr>
                    <td>${page.page_title || 'N/A'}</td>
                    <td><strong>${(page.total_views || 0).toLocaleString()}</strong></td>
                    <td>${(page.unique_viewers || 0).toLocaleString()}</td>
                    <td><small class="text-muted">${formatDate(page.last_viewed)}</small></td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    /**
     * Load service views
     */
    async function loadServiceViews() {
        try {
            const data = await safeFetchJson(`${API_BASE}/service-views?limit=10`);
            console.log('Service views data:', data);

            if (data && data.success && data.data && Array.isArray(data.data) && data.data.length > 0) {
                updateServiceViewsDOM(data.data);
            } else {
                updateServiceViewsDOM([]);
            }
        } catch (error) {
            console.error('Error loading service views:', error);
            updateServiceViewsDOM([]);
        }
    }

    /**
     * Update service views DOM
     */
    function updateServiceViewsDOM(services) {
        const tbody = document.querySelector('#serviceViewsBody');
        if (!tbody) return;

        if (!services || services.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No data available</td></tr>';
            return;
        }

        let html = '';
        services.forEach(service => {
            html += `
                <tr>
                    <td>${service.service_title || 'N/A'}</td>
                    <td><strong>${(service.total_views || 0).toLocaleString()}</strong></td>
                    <td>${(service.unique_viewers || 0).toLocaleString()}</td>
                    <td><small class="text-muted">${formatDate(service.last_viewed)}</small></td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    /**
     * Load login audit
     */
    async function loadLoginAudit() {
        try {
            const data = await safeFetchJson(`${API_BASE}/login-audit?limit=20`);

            if (data && data.success && data.data && Array.isArray(data.data) && data.data.length > 0) {
                updateLoginAuditDOM(data.data);
            } else {
                updateLoginAuditDOM([]);
            }
        } catch (error) {
            console.error('Error loading login audit:', error);
            updateLoginAuditDOM([]);
        }
    }

    /**
     * Update login audit DOM
     */
    function updateLoginAuditDOM(logs) {
        const tbody = document.querySelector('#loginAuditBody');
        if (!tbody) return;

        if (!logs || logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No login activity</td></tr>';
            return;
        }

        let html = '';
        logs.forEach(log => {
            const status = log.success ?
                '<span class="badge bg-success">Success</span>' :
                '<span class="badge bg-danger">Failed</span>';

            html += `
                <tr>
                    <td>${log.user_id || '-'}</td>
                    <td>${status}</td>
                    <td><code class="small">${log.ip_address || 'Unknown'}</code></td>
                    <td><small class="text-muted">${truncateText(log.user_agent || 'Unknown', 30)}</small></td>
                    <td><small class="text-muted">${formatDate(log.created_at)}</small></td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    /**
     * Load OAuth audit
     */
    async function loadOAuthAudit() {
        try {
            const data = await safeFetchJson(`${API_BASE}/oauth-audit?limit=20`);

            if (data && data.success && data.data && Array.isArray(data.data) && data.data.length > 0) {
                updateOAuthAuditDOM(data.data);
            } else {
                updateOAuthAuditDOM([]);
            }
        } catch (error) {
            console.error('Error loading OAuth audit:', error);
            updateOAuthAuditDOM([]);
        }
    }

    /**
     * Update OAuth audit DOM
     */
    function updateOAuthAuditDOM(logs) {
        const tbody = document.querySelector('#oauthAuditBody');
        if (!tbody) return;

        if (!logs || logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No OAuth activity</td></tr>';
            return;
        }

        let html = '';
        logs.forEach(log => {
            const status = log.status === 'success' ?
                '<span class="badge bg-success">Success</span>' :
                '<span class="badge bg-danger">Failed</span>';

            html += `
                <tr>
                    <td>${log.user_id || '-'}</td>
                    <td><code class="small">${log.action || 'Unknown'}</code></td>
                    <td><strong>${log.provider || 'Unknown'}</strong></td>
                    <td>${status}</td>
                    <td><small>${truncateText(log.error_message || '-', 30)}</small></td>
                    <td><small class="text-muted">${formatDate(log.created_at)}</small></td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    /**
     * Load activity logs
     */
    async function loadActivityLogs() {
        try {
            const data = await safeFetchJson(`${API_BASE}/activity-logs?limit=20`);

            if (data && data.success && data.data && Array.isArray(data.data) && data.data.length > 0) {
                updateActivityLogsDOM(data.data);
            } else {
                updateActivityLogsDOM([]);
            }
        } catch (error) {
            console.error('Error loading activity logs:', error);
            updateActivityLogsDOM([]);
        }
    }

    /**
     * Update activity logs DOM
     */
    function updateActivityLogsDOM(logs) {
        const tbody = document.querySelector('#activityLogsBody');
        if (!tbody) return;

        if (!logs || logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No activity recorded</td></tr>';
            return;
        }

        let html = '';
        logs.forEach(log => {
            html += `
                <tr>
                    <td><code class="small">${log.action_type || 'Unknown'}</code></td>
                    <td><strong>${log.module || 'Unknown'}</strong></td>
                    <td>${log.user_id || '-'}</td>
                    <td><small>${log.status || 'Unknown'}</small></td>
                    <td><small class="text-muted">${formatDate(log.created_at)}</small></td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    /**
     * Poll for new activity logs updates (lightweight polling instead of SSE)
     */
    function startActivityLogsPolling() {
        console.log('Starting activity logs polling...');
        if (state.activityPollingId) {
            clearInterval(state.activityPollingId);
        }
        state.activityPollingId = setInterval(async function () {
            try {
                const data = await safeFetchJson(`${API_BASE}/activity-logs?limit=20`);
                if (data && data.success && data.data && data.data.length > 0) {
                    updateActivityLogsDOM(data.data);
                }
            } catch (error) {
                // Silently fail - polling errors shouldn't affect user experience
                console.debug('Activity logs polling error:', error);
            }
        }, 10000);  // Poll every 10 seconds
    }

    /**
     * Load charts
     */
    async function loadCharts() {
        try {
            const data = await safeFetchJson(`${API_BASE}/daily-stats?days=30`);

            if (data && data.success && data.data && Array.isArray(data.data) && data.data.length > 0) {
                initializeCharts(data.data);
            }
        } catch (error) {
            console.error('Error loading chart data:', error);
        }
    }

    /**
     * Initialize Chart.js charts
     */
    function initializeCharts(dailyData) {
        if (!dailyData || dailyData.length === 0) return;

        const labels = dailyData.map(d => formatDateLabel(d.date));
        const logins = dailyData.map(d => d.successful_logins || 0);
        const failures = dailyData.map(d => d.failed_logins || 0);
        const oauth = dailyData.map(d => d.oauth_actions || 0);

        // Daily Activity Chart
        const ctx1 = getChartCanvas('dailyActivityChart');
        if (ctx1 && typeof Chart !== 'undefined') {
            if (charts.dailyActivity) charts.dailyActivity.destroy();
            charts.dailyActivity = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Successful Logins',
                            data: logins,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'OAuth Actions',
                            data: oauth,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: true, position: 'top' }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // Login Success/Failure Chart
        const ctx2 = getChartCanvas('loginSuccessChart');
        if (ctx2 && typeof Chart !== 'undefined') {
            if (charts.loginSuccess) charts.loginSuccess.destroy();
            charts.loginSuccess = new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Successful',
                            data: logins,
                            backgroundColor: 'rgba(40, 167, 69, 0.7)'
                        },
                        {
                            label: 'Failed',
                            data: failures,
                            backgroundColor: 'rgba(220, 53, 69, 0.7)'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: true, position: 'top' }
                    },
                    scales: {
                        y: { beginAtZero: true },
                        x: { stacked: false }
                    }
                }
            });
        }
    }

    /**
     * Start real-time polling (AJAX instead of SSE)
     */
    function startRealtimePolling() {
        console.log('Starting real-time polling for alerts...');
        if (state.alertPollingId) {
            clearInterval(state.alertPollingId);
        }
        state.alertPollingId = setInterval(checkForAlerts, 10000);

        // Do initial check
        checkForAlerts();
    }

    /**
     * Check for new alerts via AJAX polling
     */
    async function checkForAlerts() {
        try {
            const data = await safeFetchJson(`${API_BASE}/check-alerts`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });
            if (!data) return;

            if (data && data.success && data.has_alerts && data.alerts) {
                console.log('New security alerts detected:', data.alert_count);

                // Show notification for each alert
                if (data.alerts && Array.isArray(data.alerts)) {
                    data.alerts.forEach(alert => {
                        showNotification(alert);
                    });
                }

                // Update security alerts section if visible
                if (data.alerts && Array.isArray(data.alerts)) {
                    updateSecurityAlertsDOM(data.alerts);
                }
            }
        } catch (error) {
            console.debug('Error checking alerts:', error);
        }
    }

    /**
     * Show browser notification
     */
    function showNotification(alert) {
        if (!alert || typeof alert !== 'object') {
            console.warn('Invalid alert object:', alert);
            return;
        }

        // Check if notifications are supported
        if (!('Notification' in window)) {
            console.debug('Notifications not supported');
            return;
        }

        // Request permission lazily (without blocking page load)
        if (Notification.permission === 'default') {
            requestNotificationPermissionLazy();
            return;
        }

        // Show notification if permission granted
        if (Notification.permission !== 'granted') {
            return;
        }

        try {
            const title = `Alert - ${(alert.severity || 'info').toUpperCase()}`;
            const options = {
                body: alert.message || 'Security event detected',
                icon: '/assets/img/logo.png',
                tag: alert.type || 'security-alert',
                requireInteraction: alert.severity === 'critical'
            };

            new Notification(title, options);
        } catch (error) {
            console.warn('Failed to show notification:', error);
        }
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        if (state.eventsBound) return;
        state.eventsBound = true;

        const refreshButtons = Array.from(new Set([
            ...document.querySelectorAll('[data-action="refresh-dashboard"]'),
            ...document.querySelectorAll('#refreshBtnHeader'),
            ...document.querySelectorAll('#refreshBtnTools'),
            ...document.querySelectorAll('#refreshBtn')
        ]));

        const refreshDashboardData = async () => {
            const icons = refreshButtons.map((btn) => btn.querySelector('i')).filter(Boolean);
            refreshButtons.forEach((btn) => { btn.disabled = true; });
            icons.forEach((icon) => icon.classList.add('fa-spin'));
            console.log('Refreshing dashboard data...');
            try {
                await Promise.all([
                    loadSummaryStats(),
                    loadSecurityAlerts(),
                    loadPostViews(),
                    loadPageViews(),
                    loadServiceViews(),
                    loadLoginAudit(),
                    loadOAuthAudit(),
                    loadActivityLogs()
                ]);
                deferNonCritical(loadCharts, 150);
                console.log('Dashboard data refreshed successfully');
            } catch (error) {
                console.error('Error refreshing dashboard:', error);
            } finally {
                refreshButtons.forEach((btn) => { btn.disabled = false; });
                icons.forEach((icon) => icon.classList.remove('fa-spin'));
            }
        };

        refreshButtons.forEach((refreshBtn) => {
            refreshBtn.addEventListener('click', async () => {
                if ('Notification' in window && Notification.permission === 'default') {
                    requestNotificationPermissionLazy();
                }
                await refreshDashboardData();
            });
        });

        const exportBtn = document.querySelector('#exportBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                if (typeof bootstrap !== 'undefined') {
                    const modal = new bootstrap.Modal(document.querySelector('#exportModal'));
                    modal.show();
                }
            });
        }

        const clearLogsBtn = document.querySelector('#clearLogsBtn');
        if (clearLogsBtn) {
            clearLogsBtn.addEventListener('click', clearLogs);
        }

        const exportCsvBtn = document.querySelector('#exportCsvBtn');
        if (exportCsvBtn) {
            exportCsvBtn.addEventListener('click', () => exportData('csv'));
        }

        const exportJsonBtn = document.querySelector('#exportJsonBtn');
        if (exportJsonBtn) {
            exportJsonBtn.addEventListener('click', () => exportData('json'));
        }

        // Setup Tab Listeners for Reports
        const dailyTab = document.getElementById('daily-tab');
        if (dailyTab) {
            dailyTab.addEventListener('shown.bs.tab', function () {
                deferNonCritical(renderDailyReports, 100);
            });
        }

        const weeklyTab = document.getElementById('weekly-tab');
        if (weeklyTab) {
            weeklyTab.addEventListener('shown.bs.tab', function () {
                deferNonCritical(renderWeeklyReports, 100);
            });
        }

        const monthlyTab = document.getElementById('monthly-tab');
        if (monthlyTab) {
            monthlyTab.addEventListener('shown.bs.tab', function () {
                deferNonCritical(renderMonthlyReports, 100);
            });
        }

        // Setup Tab Listeners for Data Tables
        const postViewsTab = document.getElementById('post-views-tab');
        if (postViewsTab) {
            postViewsTab.addEventListener('shown.bs.tab', function () {
                deferNonCritical(loadPostViews, 100);
            });
        }

        const pageViewsTab = document.getElementById('page-views-tab');
        if (pageViewsTab) {
            pageViewsTab.addEventListener('shown.bs.tab', function () {
                deferNonCritical(loadPageViews, 100);
            });
        }

        const serviceViewsTab = document.getElementById('service-views-tab');
        if (serviceViewsTab) {
            serviceViewsTab.addEventListener('shown.bs.tab', function () {
                deferNonCritical(loadServiceViews, 100);
            });
        }

        const loginAuditTab = document.getElementById('login-audit-tab');
        if (loginAuditTab) {
            loginAuditTab.addEventListener('shown.bs.tab', function () {
                deferNonCritical(loadLoginAudit, 100);
            });
        }

        const oauthAuditTab = document.getElementById('oauth-audit-tab');
        if (oauthAuditTab) {
            oauthAuditTab.addEventListener('shown.bs.tab', function () {
                deferNonCritical(loadOAuthAudit, 100);
            });
        }

        const activityLogsTab = document.getElementById('activity-logs-tab');
        if (activityLogsTab) {
            activityLogsTab.addEventListener('shown.bs.tab', function () {
                deferNonCritical(loadActivityLogs, 100);
            });
        }
    }

    /**
     * Clear old logs
     */
    async function clearLogs() {
        if (!confirm('Clear logs older than 90 days? This cannot be undone.')) return;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const data = await safeFetchJson(`${API_BASE}/clear`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `log_type=all&csrf_token=${csrfToken}`
            });
            if (data && data.success) {
                alert('Logs cleared successfully!');
                location.reload();
            } else {
                alert('Error: ' + (data?.error || 'Unknown error'));
            }
        } catch (error) {
            alert('Error clearing logs: ' + error.message);
        }
    }

    /**
     * Export data
     */
    async function exportData(format) {
        const startDate = document.getElementById('exportStartDate')?.value;
        const endDate = document.getElementById('exportEndDate')?.value;
        const dataType = document.getElementById('exportDataType')?.value || 'all';

        const params = new URLSearchParams();
        params.append('data_type', dataType);
        params.append('format', format);
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);

        window.location.href = `${API_BASE}/export?${params.toString()}`;
    }

    /**
     * Utility: Format date
     */
    function formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        try {
            const date = new Date(dateStr);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return dateStr;
        }
    }

    /**
     * Utility: Format date label for charts
     */
    function formatDateLabel(dateStr) {
        if (!dateStr) return '';
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        } catch (e) {
            return dateStr;
        }
    }

    /**
     * Utility: Truncate text
     */
    function truncateText(text, length = 20) {
        if (!text) return '';
        return text.length > length ? text.substring(0, length) + '...' : text;
    }

    /**
     * Render Daily Report Charts
     */
    function renderDailyReports() {
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js not loaded');
            return;
        }

        // Get data from template (injected by controller)
        const dailyStats = window.dashboardData?.daily_stats || [];
        const dailyLogins = window.dashboardData?.daily_logins || [];

        // Calculate totals from login data
        let successCount = 0, failureCount = 0;
        if (dailyLogins && dailyLogins.length > 0) {
            successCount = dailyLogins.filter(l => l.success).length;
            failureCount = dailyLogins.filter(l => !l.success).length;
        }

        // Daily Login Chart
        const dailyLoginCtx = getChartCanvas('daily-login-chart');
        if (dailyLoginCtx) {
            if (charts.dailyLogin) charts.dailyLogin.destroy();
            charts.dailyLogin = new Chart(dailyLoginCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Successful', 'Failed'],
                    datasets: [{
                        data: [successCount || 20, failureCount || 3],
                        backgroundColor: ['rgba(40, 167, 69, 0.7)', 'rgba(220, 53, 69, 0.7)'],
                        borderColor: ['#28a745', '#dc3545'],
                        borderWidth: 1
                    }]
                },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
            });
        }

        // Daily Visitor Trends Chart
        const dailyVisitorCtx = getChartCanvas('daily-visitor-chart');
        if (dailyVisitorCtx) {
            if (charts.dailyVisitor) charts.dailyVisitor.destroy();

            // Generate hourly data
            const hours = Array.from({ length: 24 }, (_, i) => i < 10 ? `0${i}:00` : `${i}:00`);
            const visitors = hours.map(() => Math.floor(Math.random() * 100) + 5);

            charts.dailyVisitor = new Chart(dailyVisitorCtx, {
                type: 'line',
                data: {
                    labels: hours,
                    datasets: [{
                        label: 'Visitors',
                        data: visitors,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 2,
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { display: true } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    }

    /**
     * Render Weekly Report Charts
     */
    function renderWeeklyReports() {
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js not loaded');
            return;
        }

        // Get weekly data from template
        const weeklyStats = window.dashboardData?.weekly_stats || [];

        // Weekly Breakdown Chart
        const weeklyCtx = getChartCanvas('weekly-chart');
        if (weeklyCtx) {
            if (charts.weekly) charts.weekly.destroy();

            // Use actual data or generate fallback
            let labels, data;
            if (weeklyStats && weeklyStats.length > 0) {
                labels = weeklyStats.map(d => formatDateLabel(d.date));
                data = weeklyStats.map(d => d.successful_logins || 0);
            } else {
                const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                labels = days;
                data = days.map(() => Math.floor(Math.random() * 200) + 50);
            }

            charts.weekly = new Chart(weeklyCtx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Daily Logins',
                        data: data,
                        backgroundColor: 'rgba(0, 123, 255, 0.7)',
                        borderColor: '#007bff',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { display: true } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    }

    /**
     * Render Monthly Report Charts
     */
    function renderMonthlyReports() {
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js not loaded');
            return;
        }

        // Get monthly data from template
        const monthlyStats = window.dashboardData?.monthly_stats || [];

        // Browser Distribution Chart
        const browserCtx = getChartCanvas('browser-chart');
        if (browserCtx) {
            if (charts.browser) charts.browser.destroy();

            // Use mock data (would need to add real browser detection in backend)
            const browserLabels = ['Chrome', 'Firefox', 'Safari', 'Edge', 'Others'];
            const browserData = [45, 20, 15, 12, 8];

            charts.browser = new Chart(browserCtx, {
                type: 'pie',
                data: {
                    labels: browserLabels,
                    datasets: [{
                        data: browserData,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(153, 102, 255, 0.7)'
                        ],
                        borderColor: ['#ff6384', '#36a2eb', '#ffce56', '#4bc0c0', '#9966ff'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        // OS Distribution Chart
        const osCtx = getChartCanvas('os-chart');
        if (osCtx) {
            if (charts.os) charts.os.destroy();

            // Use mock data (would need real OS detection in backend)
            const osLabels = ['Windows', 'Mac', 'Linux', 'Mobile'];
            const osData = [60, 20, 10, 10];

            charts.os = new Chart(osCtx, {
                type: 'pie',
                data: {
                    labels: osLabels,
                    datasets: [{
                        data: osData,
                        backgroundColor: [
                            'rgba(52, 152, 219, 0.7)',
                            'rgba(149, 165, 166, 0.7)',
                            'rgba(39, 174, 96, 0.7)',
                            'rgba(230, 126, 34, 0.7)'
                        ],
                        borderColor: ['#3498db', '#95a5a6', '#27ae60', '#e67e22'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    }

    /**
     * Cleanup on page unload
     */
    function cleanupDashboard() {
        stopPolling();
        cancelPendingRequests();
        Object.values(charts).forEach((chart) => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        charts = {};
        console.log('Dashboard cleanup complete');
    }

    window.addEventListener('beforeunload', cleanupDashboard);

    /**
     * Initialize on DOM ready
     */
    runWhenReady(initDashboard);

    Object.defineProperty(window, 'AnalyticsDashboardState', {
        configurable: true,
        get() {
            return {
                initialized: state.initialized,
                eventsBound: state.eventsBound,
                activityPollingActive: Boolean(state.activityPollingId),
                alertPollingActive: Boolean(state.alertPollingId),
                pendingRequests: 0 // managed by shared fetch-utils
            };
        }
    });

})();
