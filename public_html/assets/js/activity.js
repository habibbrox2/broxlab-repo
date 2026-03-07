/**
 * Enhanced Activity Tracking System (Vanilla JS)
 * Version: 2.1
 */

(function () {
    'use strict';

    if (window.__activityTrackerBooted) return;
    window.__activityTrackerBooted = true;

    const CONFIG = {
        INIT_DELAY: 30000,
        LOG_ENDPOINT: '/api/log-activity',
        DEBOUNCE_DELAY: 300,
        RATE_LIMIT_WINDOW: 1000,
        MAX_LOGS_PER_WINDOW: 10,
        RETRY_ATTEMPTS: 3,
        RETRY_DELAY: 1000
    };

    const State = {
        initStarted: false,
        initialized: false,
        formDataCache: new Map(),
        formSubmitHandlers: new Map(),
        logQueue: [],
        logTimestamps: [],
        sessionInfo: null,
        clientIp: 'UNKNOWN',
        eventListeners: [],
        queueProcessorId: null,
        fullTrackingTimerId: null,
        ajaxTrackingInstalled: false
    }; 

    function runWhenReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    function isRateLimited() {
        const now = Date.now();
        State.logTimestamps = State.logTimestamps.filter(
            (timestamp) => now - timestamp < CONFIG.RATE_LIMIT_WINDOW
        );

        if (State.logTimestamps.length >= CONFIG.MAX_LOGS_PER_WINDOW) {
            return true;
        }

        State.logTimestamps.push(now);
        return false;
    }

    function parseBrowserInfo(userAgent) {
        const browsers = [
            { name: 'Edge', pattern: /Edge\/(\d+)/, versionPattern: /Edge\/(\d+)/ },
            { name: 'Edg', pattern: /Edg\/(\d+)/, versionPattern: /Edg\/(\d+)/ },
            { name: 'Chrome', pattern: /Chrome\/(\d+)/, versionPattern: /Chrome\/(\d+)/ },
            { name: 'Safari', pattern: /Safari/, versionPattern: /Version\/(\d+)/ },
            { name: 'Firefox', pattern: /Firefox\/(\d+)/, versionPattern: /Firefox\/(\d+)/ },
            { name: 'Opera', pattern: /Opera/, versionPattern: /Version\/(\d+)/ }
        ];

        let browser = 'Unknown';
        let version = '';

        for (const candidate of browsers) {
            if (candidate.pattern.test(userAgent)) {
                browser = candidate.name;
                const match = userAgent.match(candidate.versionPattern);
                if (match) version = match[1];
                break;
            }
        }

        let os = 'Unknown';
        if (/Windows NT 10\.0/.test(userAgent)) os = 'Windows 10/11';
        else if (/Windows NT 6\.3/.test(userAgent)) os = 'Windows 8.1';
        else if (/Windows NT 6\.2/.test(userAgent)) os = 'Windows 8';
        else if (/Windows NT 6\.1/.test(userAgent)) os = 'Windows 7';
        else if (/Windows/.test(userAgent)) os = 'Windows';
        else if (/Mac OS X/.test(userAgent)) os = 'macOS';
        else if (/Linux/.test(userAgent)) os = 'Linux';
        else if (/iPhone|iPad/.test(userAgent)) os = 'iOS';
        else if (/Android/.test(userAgent)) os = 'Android';

        return `${browser}${version ? ' ' + version : ''} (${os})`;
    }

    function getDeviceInfo() {
        return {
            screen_width: window.screen.width,
            screen_height: window.screen.height,
            screen_colorDepth: window.screen.colorDepth,
            viewport_width: window.innerWidth,
            viewport_height: window.innerHeight,
            pixel_ratio: window.devicePixelRatio || 1,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            language: navigator.language,
            languages: navigator.languages || [navigator.language],
            platform: navigator.platform,
            online: navigator.onLine,
            cookie_enabled: navigator.cookieEnabled,
            do_not_track: navigator.doNotTrack || 'unspecified',
            hardware_concurrency: navigator.hardwareConcurrency || 'unknown',
            max_touch_points: navigator.maxTouchPoints || 0
        };
    }

    function getPerformanceInfo() {
        if (!window.performance || !window.performance.timing) return null;

        const timing = window.performance.timing;
        const navigation = window.performance.navigation || {};

        return {
            page_load_time: timing.loadEventEnd - timing.navigationStart,
            dom_ready_time: timing.domContentLoadedEventEnd - timing.navigationStart,
            dom_interactive_time: timing.domInteractive - timing.navigationStart,
            response_time: timing.responseEnd - timing.requestStart,
            dns_time: timing.domainLookupEnd - timing.domainLookupStart,
            tcp_time: timing.connectEnd - timing.connectStart,
            ttfb: timing.responseStart - timing.navigationStart,
            navigation_type: navigation.type,
            redirect_count: navigation.redirectCount || 0
        };
    }

    function generateSessionId() {
        return 'session_' + Date.now() + '_' + Math.random().toString(36).slice(2, 11);
    }

    function cleanJSONString(obj) {
        if (!obj || typeof obj !== 'object') return '{}';
        try {
            return Object.keys(obj).length ? JSON.stringify(obj) : '{}';
        } catch (_error) {
            return '{}';
        }
    }

    function buildAction(currentUrl, response, oldData, newData, elementSummary = '') {
        const oldStr = cleanJSONString(oldData);
        const newStr = cleanJSONString(newData);
        return `${currentUrl} -> ${response} -> ${oldStr} -> ${newStr}${elementSummary ? ' -> ' + elementSummary : ''}`;
    }

    function initSessionInfo() {
        State.clientIp = window.__clientIp || 'UNKNOWN';
        State.sessionInfo = {
            user_agent: navigator.userAgent,
            browser_info: parseBrowserInfo(navigator.userAgent),
            device_info: getDeviceInfo(),
            timestamp: new Date().toISOString(),
            page_url: window.location.href,
            page_title: document.title,
            referrer: document.referrer,
            session_id: generateSessionId(),
            started_at: new Date().toISOString()
        };
        window.activitySessionInfo = State.sessionInfo;
    }

    function sendWithXhr(formData, headers, onError) {
        try {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', CONFIG.LOG_ENDPOINT, true);
            Object.entries(headers).forEach(([key, value]) => {
                xhr.setRequestHeader(key, value);
            });
            xhr.onerror = onError;
            xhr.send(formData);
        } catch (_error) {
            onError();
        }
    }

    function sendLog(actionStr, resourceType, resourceId, details, status = 'success', attempt = 1) {
        if (isRateLimited()) {
            State.logQueue.push({ actionStr, resourceType, resourceId, details, status, attempt });
            return;
        }

        const enhancedDetails = {
            ...details,
            _browser: parseBrowserInfo(navigator.userAgent),
            _device: getDeviceInfo(),
            _user_agent: navigator.userAgent,
            _page_url: window.location.href,
            _page_title: document.title,
            _referrer: document.referrer,
            _timestamp: new Date().toISOString(),
            _session_id: State.sessionInfo?.session_id || 'unknown'
        };

        const perfInfo = getPerformanceInfo();
        if (perfInfo) enhancedDetails._performance = perfInfo;

        const payload = {
            action: actionStr,
            resource_type: resourceType,
            resource_id: resourceId,
            details: JSON.stringify(enhancedDetails),
            status: status
        };

        const formData = new FormData();
        Object.keys(payload).forEach((key) => {
            if (payload[key] !== null && payload[key] !== undefined) {
                formData.append(key, payload[key]);
            }
        });

        const headers = {
            'X-Client-IP': State.clientIp,
            'X-Request-Time': new Date().toISOString(),
            'X-Session-ID': State.sessionInfo?.session_id || 'unknown'
        };

        const retry = () => {
            if (attempt >= CONFIG.RETRY_ATTEMPTS) return;
            setTimeout(() => {
                sendLog(actionStr, resourceType, resourceId, details, status, attempt + 1);
            }, CONFIG.RETRY_DELAY * attempt);
        };

        if (typeof fetch === 'function') {
            fetch(CONFIG.LOG_ENDPOINT, {
                method: 'POST',
                body: formData,
                headers
            }).catch(() => retry());
            return;
        }

        sendWithXhr(formData, headers, retry);
    }

    function processLogQueue() {
        if (!State.logQueue.length) return;
        const queued = State.logQueue.shift();
        sendLog(queued.actionStr, queued.resourceType, queued.resourceId, queued.details, queued.status, queued.attempt || 1);
    }

    function fieldValue(field) {
        const tagName = (field.tagName || '').toLowerCase();
        const type = (field.type || '').toLowerCase();

        if (type === 'checkbox') {
            return field.checked ? (field.value || 'on') : '';
        }

        if (type === 'radio') {
            return field.checked ? field.value : null;
        }

        if (tagName === 'select' && field.multiple) {
            return Array.from(field.selectedOptions).map((option) => option.value).join(',');
        }

        return field.value;
    }

    function captureFormData(form) {
        const data = {};
        const fields = form.querySelectorAll('input, select, textarea');

        fields.forEach((field) => {
            const name = field.name;
            const type = (field.type || '').toLowerCase();
            if (!name || /password|hidden/i.test(type)) return;

            const value = fieldValue(field);
            if (value === null) return;
            data[name] = value;
        });

        const key = form.id || form.name || `form_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
        State.formDataCache.set(key, data);
        return { key, data };
    }

    function collectSubmitData(form) {
        const data = {};
        const formData = new FormData(form);
        formData.forEach((value, key) => {
            if (/password|token|csrf/i.test(key)) return;
            data[key] = value;
        });
        return data;
    }

    function setupFormSubmitTracking() {
        const forms = document.querySelectorAll('form');
        forms.forEach((form) => {
            if (State.formSubmitHandlers.has(form)) {
                form.removeEventListener('submit', State.formSubmitHandlers.get(form));
            }

            const initial = captureFormData(form);
            const formId = initial.key;

            const handler = function () {
                const oldData = State.formDataCache.get(formId) || {};
                const newData = collectSubmitData(form);

                const actionStr = buildAction(
                    window.location.href,
                    'form_submitted',
                    oldData,
                    newData
                );

                const formDetails = {
                    url: window.location.href,
                    form_id: formId,
                    form_name: form.getAttribute('name') || 'unnamed',
                    form_action: form.getAttribute('action') || window.location.href,
                    form_method: form.getAttribute('method') || 'GET',
                    old_data: oldData,
                    new_data: newData,
                    fields_count: Object.keys(newData).length,
                    fields_changed: Object.keys(newData).filter((key) => oldData[key] !== newData[key]).length,
                    response: 'form submitted'
                };

                const resourceType = form.getAttribute('data-resource-type') || 'form';
                const resourceId = form.getAttribute('data-resource-id') || null;
                sendLog(actionStr, resourceType, resourceId, formDetails, 'success');
                State.formDataCache.set(formId, { ...oldData, ...newData });
            };

            form.addEventListener('submit', handler);
            State.formSubmitHandlers.set(form, handler);
        });
    }

    function setupInputTracking() {
        const changeHandler = debounce(function () {
            const input = this;
            const name = input.name;
            const type = (input.type || '').toLowerCase();
            if (!name || /password|hidden/i.test(type)) return;

            const form = input.closest('form');
            if (!form) return;

            const formId = form.id || form.name || 'unknown';
            const oldData = State.formDataCache.get(formId) || {};
            const value = fieldValue(input);
            const oldValue = oldData[name];
            if (value === oldValue) return;

            const actionStr = buildAction(
                window.location.href,
                'input_changed',
                { [name]: oldValue },
                { [name]: value }
            );

            const inputDetails = {
                url: window.location.href,
                field_name: name,
                field_type: input.type || input.tagName,
                field_id: input.id || 'unknown',
                old_value: oldValue,
                new_value: value,
                value_length: String(value || '').length,
                form_id: formId,
                is_required: !!input.required,
                is_disabled: !!input.disabled,
                response: 'input changed'
            };

            const resourceType = form.getAttribute('data-resource-type') || 'form_field';
            const resourceId = form.getAttribute('data-resource-id') || null;
            sendLog(actionStr, resourceType, resourceId, inputDetails, 'success');

            oldData[name] = value;
            State.formDataCache.set(formId, oldData);
        }, CONFIG.DEBOUNCE_DELAY);

        const fields = document.querySelectorAll('form input, form select, form textarea');
        fields.forEach((field) => {
            field.addEventListener('change', changeHandler);
            State.eventListeners.push({ element: field, event: 'change', handler: changeHandler });
        });
    }

    function setupKeyboardTracking() {
        const keyHandler = function (event) {
            if (!(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== 's') return;
            event.preventDefault();

            const keyboardDetails = {
                event: 'keyboard_shortcut',
                shortcut: `${event.ctrlKey ? 'Ctrl' : 'Cmd'}+S`,
                action: 'Save',
                page_url: window.location.href,
                active_element: document.activeElement?.tagName || 'unknown'
            };

            sendLog('Keyboard Shortcut: Save', 'keyboard_event', null, keyboardDetails, 'success');
        };

        document.addEventListener('keydown', keyHandler);
        State.eventListeners.push({ element: document, event: 'keydown', handler: keyHandler });
    }

    function setupErrorTracking() {
        const errorHandler = function (event) {
            const errorDetails = {
                event: 'javascript_error',
                error_message: event.message || 'Unknown error',
                error_source: event.filename || 'unknown',
                error_line: event.lineno || 0,
                error_column: event.colno || 0,
                error_stack: event.error?.stack || 'No stack trace',
                page_url: window.location.href
            };

            sendLog('JavaScript Error', 'error', null, errorDetails, 'failure');
        };

        const rejectionHandler = function (event) {
            const errorDetails = {
                event: 'unhandled_promise_rejection',
                error_message: event.reason?.message || String(event.reason) || 'Unknown',
                error_stack: event.reason?.stack || 'No stack trace',
                page_url: window.location.href
            };

            sendLog('Unhandled Promise Rejection', 'error', null, errorDetails, 'failure');
        };

        window.addEventListener('error', errorHandler);
        window.addEventListener('unhandledrejection', rejectionHandler);
        State.eventListeners.push({ element: window, event: 'error', handler: errorHandler });
        State.eventListeners.push({ element: window, event: 'unhandledrejection', handler: rejectionHandler });
    }


    function setupXhrTracking() {
        if (typeof XMLHttpRequest === 'undefined') return;
        if (!State.originalXhrOpen) State.originalXhrOpen = XMLHttpRequest.prototype.open;
        if (!State.originalXhrSend) State.originalXhrSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function (method, url, ...rest) {
            this.__activityMethod = String(method || 'GET').toUpperCase();
            this.__activityUrl = String(url || '');
            return State.originalXhrOpen.call(this, method, url, ...rest);
        };

        XMLHttpRequest.prototype.send = function (...args) {
            const method = this.__activityMethod || 'GET';
            const url = this.__activityUrl || '';
            const isLogEndpoint = url.includes(CONFIG.LOG_ENDPOINT);
            const startedAt = Date.now();

            if (!isLogEndpoint) {
                sendLog(`AJAX: ${method} ${url}`, 'ajax_request', null, {
                    event: 'ajax_request',
                    method,
                    url,
                    data_type: 'xhr',
                    page_url: window.location.href,
                    request_time: new Date().toISOString()
                }, 'success');
            }

            const onLoad = () => {
                if (isLogEndpoint) return;
                sendLog(`AJAX Success: ${method} ${url}`, 'ajax_response', null, {
                    event: 'ajax_success',
                    method,
                    url,
                    status: this.status,
                    status_text: this.statusText,
                    response_size: String(this.responseText || '').length,
                    duration_ms: Date.now() - startedAt,
                    page_url: window.location.href,
                    response_time: new Date().toISOString()
                }, this.status >= 200 && this.status < 400 ? 'success' : 'failure');
            };

            const onError = () => {
                if (isLogEndpoint) return;
                sendLog(`AJAX Error: ${method} ${url}`, 'ajax_error', null, {
                    event: 'ajax_error',
                    method,
                    url,
                    status: this.status,
                    status_text: this.statusText,
                    response_text: String(this.responseText || '').slice(0, 500),
                    duration_ms: Date.now() - startedAt,
                    page_url: window.location.href,
                    error_time: new Date().toISOString()
                }, 'failure');
            };

            this.addEventListener('load', onLoad, { once: true });
            this.addEventListener('error', onError, { once: true });
            return State.originalXhrSend.apply(this, args);
        };
    }

    function setupAjaxTracking() {
        if (State.ajaxTrackingInstalled) return;
        State.ajaxTrackingInstalled = true;
        setupXhrTracking();
    }

    function initializeTracking() {
        if (State.initialized) return;
        setupInputTracking();
        setupKeyboardTracking();
        setupErrorTracking();
        setupAjaxTracking();
        State.initialized = true;
    }

    function restorePatchedNetworkApis() {
        // only XHR restoration remains
        if (State.originalXhrOpen) {
            XMLHttpRequest.prototype.open = State.originalXhrOpen;
            State.originalXhrOpen = null;
        }

        if (State.originalXhrSend) {
            XMLHttpRequest.prototype.send = State.originalXhrSend;
            State.originalXhrSend = null;
        }
    }

    function cleanup() {
        if (State.fullTrackingTimerId) {
            clearTimeout(State.fullTrackingTimerId);
            State.fullTrackingTimerId = null;
        }
        if (State.queueProcessorId) {
            clearInterval(State.queueProcessorId);
            State.queueProcessorId = null;
        }

        State.eventListeners.forEach((listener) => {
            listener.element.removeEventListener(listener.event, listener.handler);
        });
        State.eventListeners = [];

        State.formSubmitHandlers.forEach((handler, form) => {
            form.removeEventListener('submit', handler);
        });
        State.formSubmitHandlers.clear();

        restorePatchedNetworkApis();
        State.ajaxTrackingInstalled = false;
    }

    function init() {
        if (State.initStarted) return;
        State.initStarted = true;

        initSessionInfo();
        setupFormSubmitTracking();

        if (!State.queueProcessorId) {
            State.queueProcessorId = setInterval(processLogQueue, 1000);
        }

        State.fullTrackingTimerId = setTimeout(() => {
            runWhenReady(initializeTracking);
        }, CONFIG.INIT_DELAY);

        window.addEventListener('beforeunload', cleanup, { once: true });
    }

    runWhenReady(init);

    window.ActivityTracker = {
        getState: () => ({
            ...State,
            formDataCache: Object.fromEntries(State.formDataCache)
        }),
        getConfig: () => ({ ...CONFIG }),
        forceInit: initializeTracking,
        cleanup,
        version: '2.1'
    };
})();
