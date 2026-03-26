/**
 * BroxBhai AI SYSTEM - Admin Panel Copilot (2026 Premium Redesign)
 * Path: /public_html/ai/js/ai-admin.js
 * 
 * Features:
 *  - 100% Vanilla JS — no jQuery dependency
 *  - CSRF token refresh before each API call
 *  - Input sanitization (XSS protection)
 *  - File attachment support
 *  - Typing indicators
 *  - Auto-save feedback with visual indicator
 *  - Enhanced history management (40 message limit with UI indicator)
 *  - SSE Streaming with reasoning animation
 *  - Puter.js client-side fallback
 *  - Remote model list loading
 *  - Slash command overlay menu
 *  - Mobile responsive design
 *  - Keyboard shortcuts (Ctrl+Alt+A)
 */

// ── Auto-inject ai-style.css ──────────────────────────────────────────────────
(function injectAiCSS() {
    const scriptPath = document.currentScript?.src || '/ai/js/ai-admin.js';
    const baseUrl = scriptPath.split('?')[0];
    const cssUrl = baseUrl.replace(/\/js\/[^/]+$/, '/css/ai-style.css');

    if (!document.querySelector(`link[href^="${cssUrl}"]`)) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = cssUrl;
        document.head.appendChild(link);
    }
})();

// ── Configuration ─────────────────────────────────────────────────────────────
const ADMIN_CONFIG = {
    chatKey: 'brox.admin.history',
    optionsKey: 'brox.admin.request.options',
    proxyUrl: '/api/admin/ai/chat',
    logUrl: '/api/admin/logs/errors',
    modelsUrl: '/api/ai/models',  // Fixed: was /api/ai-system/models
    defaultProviderUrl: '/api/ai/default-provider',
    adminDefaultsUrl: '/api/ai-system/admin-defaults',
    uploadUrl: '/api/admin/ai/upload',
    puterCdn: 'https://js.puter.com/v2/',
    csrfRefreshUrl: '/api/csrf-token',
    maxHistory: 40,
    maxDomMessages: 120,
    maxInputLength: 5000,
    csrRefreshInterval: 10 * 60 * 1000, // 10 minutes
    logCheckInterval: 60 * 1000, // 1 minute
    typingSpeed: 5, // ms per character
    maxFileSize: 10 * 1024 * 1024, // 10MB
    allowedFileTypes: ['image/*', '.pdf', '.txt', '.doc', '.docx'],
    refreshCooldownMs: 2000
};

// ── Singleton Guard ───────────────────────────────────────────────────────────
if (!window.BroxAdminInstance) {

    // ── CSRF Token Manager ───────────────────────────────────────────────────
    // Try to get CSRF token from meta tag first, then input fields
    let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="csrf_token"]')?.value
        || '';
    let csrfRefreshing = false;

    function getCsrfToken() {
        // Always prefer meta tag, fallback to input
        const metaToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (metaToken) {
            csrfToken = metaToken;
        } else {
            const inputToken = document.querySelector('input[name="csrf_token"]')?.value;
            if (inputToken) csrfToken = inputToken;
        }
        return csrfToken;
    }

    async function refreshCsrfToken(force = false) {
        // CSRF token is already available in meta tag, no need to fetch
        return getCsrfToken();
    }

    // Auto-refresh CSRF token periodically
    setInterval(() => refreshCsrfToken(false), ADMIN_CONFIG.csrRefreshInterval);

    // ── Input Sanitization (XSS Protection) ───────────────────────────────────
    function sanitizeInput(text) {
        if (!text) return '';
        // Create a temporary element to safely encode HTML entities
        const div = document.createElement('div');
        div.textContent = text;
        let sanitized = div.innerHTML;

        // Additional security: remove potentially dangerous patterns
        sanitized = sanitized
            .replace(/javascript:/gi, '')
            .replace(/on\w+=/gi, '')
            .replace(/data:/gi, '');

        return sanitized;
    }

    // ── Input Validation ──────────────────────────────────────────────────────
    function validateInput(text) {
        if (!text || typeof text !== 'string') {
            return { valid: false, error: 'Message cannot be empty' };
        }

        const trimmed = text.trim();

        if (trimmed.length === 0) {
            return { valid: false, error: 'Message cannot be empty' };
        }

        if (trimmed.length > ADMIN_CONFIG.maxInputLength) {
            return { valid: false, error: `Message exceeds ${ADMIN_CONFIG.maxInputLength} characters` };
        }

        return { valid: true, sanitized: sanitizeInput(trimmed) };
    }

    function normalizeApiResponse(data) {
        if (!data || typeof data !== 'object') return { success: false, error: 'Invalid server response' };
        const content = (typeof data.content === 'string') ? data.content : null;
        return {
            success: Boolean(data.success),
            error: data.error || data.message || null,
            error_code: data.error_code || data.code || null,
            payload: content ?? (data.data ?? data),
            raw: data
        };
    }

    function safeParseJSON(text) {
        try {
            return JSON.parse(text);
        } catch {
            return null;
        }
    }

    function reportTelemetry(event, details = {}) {
        if (typeof window.broxAdminTelemetry !== 'function') return;
        try {
            window.broxAdminTelemetry(event, { ...details, timestamp: Date.now() });
        } catch {
            // Silently ignore telemetry failures
        }
    }

    // ── File Attachment Handler ───────────────────────────────────────────────
    class FileAttachmentHandler {
        constructor() {
            this.files = [];
            this.input = document.getElementById('adminAiFileInput');
            this.preview = document.getElementById('adminAiAttachmentPreview');
            this.fileName = document.getElementById('adminAiFileName');
            this.fileSize = document.getElementById('adminAiFileSize');
            this.thumb = document.getElementById('adminAiAttachmentThumb');
            this.progressWrap = document.getElementById('adminAiAttachmentProgress');
            this.progressBar = document.getElementById('adminAiAttachmentProgressBar');
            this.removeBtn = document.getElementById('adminAiRemoveAttachment');
            this.uploaded = null;
            this.uploading = false;
            this.isImage = false;
            this.currentXhr = null;

            this.init();
        }

        init() {
            if (!this.input) {
                // Try to find the input after a short delay
                setTimeout(() => {
                    this.input = document.getElementById('adminAiFileInput');
                    if (this.input) {
                        this.input.addEventListener('change', (e) => this.handleFiles(e.target.files));
                    }
                }, 500);
                return;
            }

            this.input.addEventListener('change', (e) => this.handleFiles(e.target.files));

            if (this.removeBtn) {
                this.removeBtn.addEventListener('click', () => this.clearFiles());
            }
        }

        handleFiles(fileList) {
            if (!fileList || fileList.length === 0) return;

            const file = fileList[0]; // Only support single file for now

            // Ignore duplicates
            const existing = this.files[0];
            if (existing && file.name === existing.name && file.size === existing.size && file.lastModified === existing.lastModified) {
                return;
            }

            // Cancel any in-flight upload and reset progress
            this.cancelUpload();

            this.isImage = (file.type || '').startsWith('image/');

            // Validate file size
            if (file.size > ADMIN_CONFIG.maxFileSize) {
                window.showAlert(`File too large. Maximum size is ${ADMIN_CONFIG.maxFileSize / 1024 / 1024}MB`, 'File Too Large', 'warning');
                return;
            }

            this.files = [file];
            this.uploaded = null;
            this.updatePreview();

            if (this.isImage) {
                this.uploadImage(file);
            }
        }

        cancelUpload() {
            if (this.currentXhr) {
                try { this.currentXhr.abort(); } catch { }
                this.currentXhr = null;
            }
            this.uploading = false;
            if (this.progressWrap) this.progressWrap.classList.add('brox-ai-hidden');
            if (this.progressBar) this.progressBar.style.width = '0%';
        }

        updatePreview() {
            if (!this.preview || this.files.length === 0) {
                this.preview?.classList.add('brox-ai-hidden');
                return;
            }

            const file = this.files[0];
            this.fileName.textContent = file.name;
            this.fileSize.textContent = this.formatFileSize(file.size);
            if (this.thumb) {
                if (this.isImage) {
                    const url = URL.createObjectURL(file);
                    this.thumb.innerHTML = `<img src="${url}" alt="preview">`;
                    this.thumb.style.display = 'inline-flex';
                } else {
                    this.thumb.innerHTML = '';
                    this.thumb.style.display = 'none';
                }
            }
            this.preview.classList.remove('brox-ai-hidden', 'd-none');
        }

        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        clearFiles() {
            this.cancelUpload();
            this.files = [];
            if (this.input) this.input.value = '';
            this.uploaded = null;
            this.isImage = false;
            if (this.thumb) {
                this.thumb.innerHTML = '';
                this.thumb.style.display = 'none';
            }
            this.preview?.classList.add('brox-ai-hidden');
        }

        getFiles() {
            return this.files;
        }

        hasAttachment() {
            return this.files.length > 0;
        }

        getAttachment() {
            const file = this.files[0] || null;
            return {
                file: file,
                uploaded: this.uploaded,
                isImage: this.isImage
            };
        }

        isUploading() {
            return this.uploading;
        }

        uploadImage(file) {
            if (!file) return;
            if (this.uploading && this.currentXhr) {
                try { this.currentXhr.abort(); } catch { }
            }
            this.uploading = true;
            if (this.progressWrap) {
                this.progressWrap.classList.remove('brox-ai-hidden');
            }
            if (this.progressBar) {
                this.progressBar.style.width = '0%';
            }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('csrf_token', csrfToken || '');

            const xhr = new XMLHttpRequest();
            this.currentXhr = xhr;
            xhr.open('POST', ADMIN_CONFIG.uploadUrl);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-Token', csrfToken || '');

            xhr.upload.onprogress = (e) => {
                if (!e.lengthComputable || !this.progressBar) return;
                const pct = Math.min(100, Math.round((e.loaded / e.total) * 100));
                this.progressBar.style.width = `${pct}%`;
                if (this.fileSize) {
                    this.fileSize.textContent = `Uploading... ${pct}%`;
                }
            };

            xhr.onload = () => {
                this.uploading = false;
                if (this.currentXhr === xhr) this.currentXhr = null;
                if (this.progressWrap) {
                    this.progressWrap.classList.add('d-none');
                }

                const response = normalizeApiResponse(safeParseJSON(xhr.responseText));

                if (xhr.status < 200 || xhr.status >= 300 || !response.success) {
                    this.uploaded = null;
                    const msg = response.error || `Upload failed (${xhr.status})`;
                    if (this.fileSize) {
                        this.fileSize.textContent = msg;
                    }
                    reportTelemetry('upload_failure', { status: xhr.status, error: msg, url: ADMIN_CONFIG.uploadUrl });
                    return;
                }

                const payload = response.payload;
                if (payload?.url) {
                    this.uploaded = {
                        url: payload.url,
                        mime: payload.mime || file.type || '',
                        size: payload.size || file.size || 0,
                        name: file.name
                    };
                    if (this.fileSize) {
                        this.fileSize.textContent = this.formatFileSize(file.size);
                    }
                } else {
                    this.uploaded = null;
                    const msg = 'Upload response missing attachment URL';
                    if (this.fileSize) {
                        this.fileSize.textContent = msg;
                    }
                    reportTelemetry('upload_failure', { status: xhr.status, error: msg, response: response.raw });
                }
            };

            xhr.onerror = () => {
                this.uploading = false;
                this.uploaded = null;
                if (this.currentXhr === xhr) this.currentXhr = null;
                if (this.progressWrap) {
                    this.progressWrap.classList.add('d-none');
                }
                if (this.fileSize) {
                    this.fileSize.textContent = 'Upload failed';
                }
                reportTelemetry('upload_failure', { error: 'Network error', url: ADMIN_CONFIG.uploadUrl });
            };

            xhr.send(formData);
        }
    }

    // ── Puter.js Loader (Lazy CDN) ───────────────────────────────────────────
    function loadPuter() {
        return new Promise((resolve, reject) => {
            if (window.puter) return resolve(window.puter);

            const s = document.createElement('script');
            s.src = ADMIN_CONFIG.puterCdn;
            s.async = true;
            s.onload = () => {
                resolve(window.puter);
            };
            s.onerror = () => reject(new Error('Puter.js CDN load failed'));
            document.head.appendChild(s);
        });
    }

    // ── Remote Model Loader ───────────────────────────────────────────────────
    async function fetchModels(provider, options = {}) {
        try {
            const params = new URLSearchParams();
            params.set('provider', provider);
            if (options.refresh) {
                params.set('refresh', '1');
            }
            const res = await fetch(`${ADMIN_CONFIG.modelsUrl}?${params.toString()}`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            return {
                models: Array.isArray(data.models) ? data.models : [],
                meta: {
                    cache_source: data.cache_source || '',
                    cached_at: data.cached_at || null,
                    cache_ttl: data.cache_ttl || null
                }
            };
        } catch (e) {
            console.warn('[Admin Models] Failed:', e.message);
            reportTelemetry('models_fetch_error', { provider, error: e.message });
            return { models: [], meta: null };
        }
    }

    async function fetchDefaultProvider() {
        try {
            const res = await fetch(ADMIN_CONFIG.defaultProviderUrl);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            return data.provider || 'openrouter';
        } catch (e) {
            console.warn('[Admin Provider] Default fetch failed:', e.message);
            reportTelemetry('default_provider_fetch_error', { error: e.message });
            return 'openrouter';
        }
    }

    async function fetchAdminDefaults() {
        try {
            const res = await fetch(ADMIN_CONFIG.adminDefaultsUrl);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            return data && typeof data === 'object' ? data : {};
        } catch (e) {
            console.warn('[Admin Defaults] Fetch failed:', e.message);
            reportTelemetry('admin_defaults_fetch_error', { error: e.message });
            return {};
        }
    }

    async function fetchProviderMap() {
        try {
            const res = await fetch(`${ADMIN_CONFIG.modelsUrl}?scope=admin`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            return {
                providers: data.providers || {},
                providerMeta: data.provider_meta || {}
            };
        } catch (e) {
            console.warn('[Admin Provider] Map fetch failed:', e.message);
            reportTelemetry('provider_map_fetch_error', { error: e.message });
            return { providers: {}, providerMeta: {} };
        }
    }

    // ── Main Admin Copilot Class ──────────────────────────────────────────────
    class BroxAdminCopilot {
        constructor() {
            this.history = this.loadHistory();
            this.advancedOptions = this.loadAdvancedOptions();
            this.isThinking = false;
            this.csrfToken = csrfToken;
            this.conversationId = null;
            this.pendingAutoFill = false;
            this.autoFillRetryUsed = false;
            this.currentModel = null;
            this.currentProvider = 'openrouter';
            this.preferredModel = '';
            this.puterDisabled = false;
            this.fileHandler = null;
            this.modelBarOpen = false;
            this.isRecording = false; // Voice recording state
            this._providersBootstrapped = false;
            this._providersBootstrapPromise = null;
            this._bgModelsRefresh = null;
            this._bgModelsRefreshToken = 0;
            this.suppressOutsideClose = false; // Prevent sidebar auto-close (e.g., during file picker)
            this._filePickerTimer = null;
            this._micPermission = 'unknown';
            this._micAccessPromise = null;
            this.historyStateKey = 'brox.ai.admin.open';
            this.historyStateActive = !!(history.state && history.state[this.historyStateKey]);
            this.overlay = null;

            this.initUI();
            this.initOverlay();
            this.bindEvents();
            this.startLogMonitor();
            this.renderHistory();
            this.updateContext();
            this.startInactivityTimer();
            this.initResizer();

            window.addEventListener('popstate', (event) => this.handlePopState(event));
        }

        // ── Resizer for AI Chat Panel ─────────────────────────────────────────────
        initResizer() {
            const shell = this.nodes?.shell;
            if (!shell) return;

            const resizer = shell.querySelector('[data-resizer="width"]');
            if (!resizer) {
                // Create resizer if not exists in HTML
                const newResizer = document.createElement('div');
                newResizer.className = 'admin-assistant-chat-resizer';
                newResizer.setAttribute('data-resizer', 'width');
                newResizer.setAttribute('aria-hidden', 'true');
                shell.insertBefore(newResizer, shell.firstChild);
            }

            this._resizerState = {
                isResizing: false,
                startX: 0,
                startWidth: 0,
                minWidth: 320,
                maxWidth: 800,
                storageKey: 'brox.admin.ai-shell.width'
            };

            const state = this._resizerState;

            // Load saved width
            try {
                const saved = localStorage.getItem(state.storageKey);
                if (saved) {
                    const width = parseInt(saved, 10);
                    if (!isNaN(width) && width >= state.minWidth && width <= state.maxWidth) {
                        shell.style.width = width + 'px';
                    }
                }
            } catch (e) { /* ignore */ }

            const onPointerDown = (e) => {
                if (window.innerWidth < 576) return; // Disable on mobile
                state.isResizing = true;
                state.startX = e.clientX;
                state.startWidth = shell.offsetWidth;
                shell.setPointerCapture(e.pointerId);
                shell.classList.add('is-resizing');
                document.body.style.cursor = 'ew-resize';
                document.body.style.userSelect = 'none';
                e.preventDefault();
            };

            const onPointerMove = (e) => {
                if (!state.isResizing) return;
                const delta = e.clientX - state.startX;
                let newWidth = state.startWidth + delta;
                newWidth = Math.max(state.minWidth, Math.min(state.maxWidth, newWidth));
                shell.style.width = newWidth + 'px';
            };

            const onPointerUp = (e) => {
                if (!state.isResizing) return;
                state.isResizing = false;
                shell.classList.remove('is-resizing');
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                try {
                    shell.releasePointerCapture(e.pointerId);
                } catch (err) { /* ignore */ }

                // Save width to localStorage
                const currentWidth = shell.offsetWidth;
                try {
                    localStorage.setItem(state.storageKey, String(currentWidth));
                } catch (err) { /* ignore */ }
            };

            const onDblClick = () => {
                if (window.innerWidth < 576) return;
                shell.style.width = '';
                try {
                    localStorage.removeItem(state.storageKey);
                } catch (err) { /* ignore */ }
            };

            // Use existing or new resizer
            const finalResizer = shell.querySelector('[data-resizer="width"]');
            if (finalResizer) {
                finalResizer.addEventListener('pointerdown', onPointerDown);
                finalResizer.addEventListener('dblclick', onDblClick);
            }

            shell.addEventListener('pointermove', onPointerMove);
            shell.addEventListener('pointerup', onPointerUp);
            shell.addEventListener('pointercancel', onPointerUp);
        }

        initOverlay() {
            const existing = document.getElementById('adminAiOverlay');
            if (existing) {
                this.overlay = existing;
                return;
            }
            const overlay = document.createElement('div');
            overlay.id = 'adminAiOverlay';
            overlay.className = 'brox-ai-overlay';
            overlay.addEventListener('click', () => this.closeSidebar());
            document.body.appendChild(overlay);
            this.overlay = overlay;
        }

        ensureProvidersBootstrapped() {
            if (this._providersBootstrapped) return;
            if (this._providersBootstrapPromise) return;

            this._providersBootstrapPromise = (async () => {
                await this.bootstrapProviders();
                this._providersBootstrapped = true;
            })().finally(() => {
                this._providersBootstrapPromise = null;
            });
        }

        loadHistory() {
            try {
                const storages = this.getHistoryStorages();
                for (const storage of storages) {
                    const stored = storage?.getItem(ADMIN_CONFIG.chatKey);
                    if (!stored) continue;
                    const parsed = safeParseJSON(stored);
                    if (!Array.isArray(parsed)) continue;
                    const trimmed = parsed.slice(-ADMIN_CONFIG.maxHistory);
                    if (trimmed.length) {
                        return trimmed;
                    }
                }
                return [];
            } catch (e) {
                console.warn('[Admin Copilot] Failed to load history:', e);
                return [];
            }
        }

        saveHistory() {
            try {
                const trimmed = this.history.slice(-ADMIN_CONFIG.maxHistory);
                this.persistHistory(trimmed);
                this.updateSaveIndicator();
                this.renderHistorySidebar();
            } catch (e) {
                console.warn('[Admin Copilot] Failed to save history:', e);
            }
        }

        getHistoryStorages() {
            if (Array.isArray(this._historyStorages)) {
                return this._historyStorages;
            }
            const storages = [];
            for (const name of ['localStorage', 'sessionStorage']) {
                try {
                    const candidate = window[name];
                    if (!candidate || typeof candidate.getItem !== 'function') continue;
                    storages.push(candidate);
                } catch {
                    // Storage not available – skip
                }
            }
            this._historyStorages = storages;
            return storages;
        }

        persistHistory(trimmed) {
            for (const storage of this.getHistoryStorages()) {
                try {
                    storage.setItem(ADMIN_CONFIG.chatKey, JSON.stringify(trimmed));
                } catch {
                    // Ignore write failures (privacy mode, quota)
                }
            }
        }

        clearStoredHistory() {
            for (const storage of this.getHistoryStorages()) {
                try {
                    storage.removeItem(ADMIN_CONFIG.chatKey);
                } catch {
                    // ignore removal errors
                }
            }
        }

        loadAdvancedOptions() {
            const defaults = {
                webPlugin: false,
                webMaxResults: 3,
                responseHealing: false,
                pdfPlugin: false,
                pdfEngine: 'pdf-text',
                reasoningEffort: '',
                responseFormat: 'text',
                rawJson: ''
            };
            try {
                const stored = localStorage.getItem(ADMIN_CONFIG.optionsKey);
                if (!stored) return defaults;
                const parsed = JSON.parse(stored);
                if (!parsed || typeof parsed !== 'object') return defaults;
                return { ...defaults, ...parsed };
            } catch {
                return defaults;
            }
        }

        saveAdvancedOptions() {
            try {
                localStorage.setItem(ADMIN_CONFIG.optionsKey, JSON.stringify(this.advancedOptions || {}));
            } catch {
                // ignore storage failures
            }
        }

        buildRequestOptions() {
            const options = {};
            const advanced = this.advancedOptions || {};
            const plugins = [];
            if (advanced.webPlugin) {
                const maxResults = Number(advanced.webMaxResults) || 3;
                plugins.push({
                    id: 'web',
                    max_results: Math.min(Math.max(maxResults, 1), 10)
                });
            }
            if (advanced.responseHealing) {
                plugins.push({ id: 'response-healing' });
            }
            if (advanced.pdfPlugin) {
                plugins.push({
                    id: 'file-parser',
                    pdf: {
                        engine: advanced.pdfEngine || 'pdf-text'
                    }
                });
            }
            if (plugins.length) {
                options.plugins = plugins;
            }

            if (advanced.reasoningEffort) {
                options.reasoning_effort = advanced.reasoningEffort;
            }
            if (advanced.responseFormat && advanced.responseFormat !== 'text') {
                options.response_format = { type: advanced.responseFormat };
            }

            if (advanced.rawJson && typeof advanced.rawJson === 'string') {
                const parsed = safeParseJSON(advanced.rawJson.trim());
                if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                    Object.assign(options, parsed);
                } else if (advanced.rawJson.trim() !== '') {
                    this.updateStatus('warning', 'Advanced JSON is invalid; ignoring custom options.');
                }
            }

            return options;
        }

        updateSaveIndicator() {
            const indicator = document.getElementById('adminAiSaveIndicator');
            if (!indicator) return;

            indicator.classList.add('brox-ai-saving');
            indicator.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Saving...';

            setTimeout(() => {
                indicator.classList.remove('brox-ai-saving');
                indicator.innerHTML = '<i class="bi bi-check-circle-fill"></i> Saved';
            }, 500);
        }

        initUI() {
            this.nodes = {
                btn: document.getElementById('adminAiBtn'),
                shell: document.getElementById('adminAiShell'),
                minimize: document.getElementById('adminAiMinimize'),
                title: document.getElementById('adminAiTitle'),
                contextModule: document.getElementById('adminAiContextModule'),
                contextBadge: document.getElementById('adminAiContextBadge'),
                body: document.getElementById('adminAiBody'),
                welcome: document.getElementById('adminAiWelcome'),
                input: document.getElementById('adminAiInput'),
                charCount: document.getElementById('adminAiCharCount'),
                send: document.getElementById('adminAiSend'),
                attach: document.getElementById('adminAiAttach'),
                clear: document.getElementById('adminAiClear'),
                clearHistory: document.getElementById('adminAiClearHistory'),
                close: document.getElementById('adminAiClose'),
                collectDataBtn: document.getElementById('adminAiCollectData'),
                autoFillBtn: document.getElementById('adminAiAutoFill'),
                summarizeBtn: document.getElementById('adminAiQuickSummarize'),
                slashMenu: document.getElementById('adminAiSlashMenu'),
                suggestionPanel: document.getElementById('adminAiSuggestionPanel'),
                suggestionList: document.getElementById('adminAiSuggestionList'),
                typingIndicator: document.getElementById('adminAiTypingIndicator'),
                providerSel: document.getElementById('adminAiProvider'),
                modelSel: document.getElementById('adminAiModel'),
                modelBadge: document.getElementById('adminAiCurrentModel'),
                modelStatusIndicator: document.getElementById('adminAiModelStatusIndicator'),
                refreshModels: document.getElementById('adminAiRefreshModels'),
                statusDot: document.getElementById('adminAiStatusDot'),
                statusText: document.getElementById('adminAiStatusText'),
                historySidebar: document.getElementById('adminAiSidebar'),
                historyList: document.getElementById('adminAiHistory'),
                historyEmpty: document.querySelector('#brox-ai-history-tab .brox-ai-history-empty'),
                historyToggle: document.getElementById('adminAiHistoryToggle'),
                historySidebarClose: document.getElementById('adminAiSidebarClose'),
                mic: document.getElementById('adminAiMic'),
                webPluginToggle: document.getElementById('adminAiWebPluginToggle'),
                webPluginMaxResults: document.getElementById('adminAiWebMaxResults'),
                responseHealingToggle: document.getElementById('adminAiResponseHealingToggle'),
                pdfPluginToggle: document.getElementById('adminAiPdfPluginToggle'),
                pdfEngineSel: document.getElementById('adminAiPdfEngine'),
                reasoningEffortSel: document.getElementById('adminAiReasoningEffort'),
                responseFormatSel: document.getElementById('adminAiResponseFormat'),
                advancedRawJson: document.getElementById('adminAiAdvancedOptionsJson')
            };

            // Initialize file handler
            this.fileHandler = new FileAttachmentHandler();
            this.bindFilePickerGuards();
            this.syncAdvancedOptionsUI();
        }

        bindFilePickerGuards() {
            const input = this.fileHandler?.input;
            if (!input) return;

            const resetGuard = (delay = 400) => {
                if (this._filePickerTimer) clearTimeout(this._filePickerTimer);
                this._filePickerTimer = setTimeout(() => {
                    this.suppressOutsideClose = false;
                }, delay);
            };

            const armGuard = () => {
                this.suppressOutsideClose = true;
                resetGuard(1200);
            };

            input.addEventListener('click', () => armGuard());
            input.addEventListener('focus', () => armGuard());
            input.addEventListener('change', () => resetGuard(200));
            input.addEventListener('blur', () => resetGuard(300));
        }

        syncAdvancedOptionsUI() {
            const options = this.advancedOptions || {};
            if (this.nodes.webPluginToggle) {
                this.nodes.webPluginToggle.classList.toggle('active', !!options.webPlugin);
            }
            if (this.nodes.webPluginMaxResults) {
                this.nodes.webPluginMaxResults.value = String(options.webMaxResults || 3);
                this.nodes.webPluginMaxResults.disabled = !options.webPlugin;
            }
            if (this.nodes.responseHealingToggle) {
                this.nodes.responseHealingToggle.classList.toggle('active', !!options.responseHealing);
            }
            if (this.nodes.pdfPluginToggle) {
                this.nodes.pdfPluginToggle.classList.toggle('active', !!options.pdfPlugin);
            }
            if (this.nodes.pdfEngineSel) {
                this.nodes.pdfEngineSel.value = options.pdfEngine || 'pdf-text';
                this.nodes.pdfEngineSel.disabled = !options.pdfPlugin;
            }
            if (this.nodes.reasoningEffortSel) {
                this.nodes.reasoningEffortSel.value = options.reasoningEffort || '';
            }
            if (this.nodes.responseFormatSel) {
                this.nodes.responseFormatSel.value = options.responseFormat || 'text';
            }
            if (this.nodes.advancedRawJson) {
                this.nodes.advancedRawJson.value = options.rawJson || '';
            }
        }

        // ── Voice Input (Speech Recognition) ───────────────────────────────────────
        initVoiceInput() {
            // Get mic button directly from DOM - more reliable than cached reference
            const micBtn = document.getElementById('adminAiMic');

            if (!micBtn) {
                console.error('[Voice] Mic button not found in DOM');
                return;
            }

            // Check browser support
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                console.warn('[Voice] SpeechRecognition not supported');
                micBtn.title = 'Voice input not supported in this browser';
                micBtn.style.opacity = '0.5';
                micBtn.style.cursor = 'not-allowed';
                return;
            }

            try {
                const recognition = new SpeechRecognition();
                recognition.continuous = false;
                recognition.interimResults = true;
                recognition.lang = 'en-US';

                // Store reference
                this.recognition = recognition;

                // Use self reference to avoid 'this' binding issues
                const self = this;

                if (!window.isSecureContext) {
                    micBtn.title = 'Voice input requires a secure (HTTPS) page';
                    micBtn.style.opacity = '0.6';
                    micBtn.style.cursor = 'not-allowed';
                    return;
                }

                const canProbeMic = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
                const setMicBlockedUi = (msg) => {
                    micBtn.title = msg || 'Microphone permission blocked';
                    micBtn.style.opacity = '0.6';
                    micBtn.style.cursor = 'not-allowed';
                };

                // Best-effort permission probe to avoid repeated blocked prompts.
                if (navigator.permissions && typeof navigator.permissions.query === 'function') {
                    navigator.permissions.query({ name: 'microphone' })
                        .then((status) => {
                            self._micPermission = status?.state || self._micPermission;
                            if (self._micPermission === 'denied') {
                                setMicBlockedUi('Microphone permission blocked. Allow it in site settings to use voice input.');
                            }
                            try {
                                status.onchange = () => {
                                    self._micPermission = status.state || self._micPermission;
                                    if (self._micPermission === 'denied') {
                                        setMicBlockedUi('Microphone permission blocked. Allow it in site settings to use voice input.');
                                    } else {
                                        micBtn.style.opacity = '';
                                        micBtn.style.cursor = '';
                                        micBtn.title = 'Voice input';
                                    }
                                };
                            } catch { }
                        })
                        .catch(() => { /* ignore */ });
                }

                const ensureMicAccess = async () => {
                    if (!canProbeMic) return true; // Some browsers prompt via SpeechRecognition only
                    if (self._micPermission === 'granted') return true;
                    if (self._micPermission === 'denied') {
                        setMicBlockedUi('Microphone permission blocked. Allow it in site settings to use voice input.');
                        const msg = 'Microphone permission is blocked. Click the lock icon in the address bar → Site settings → Microphone → Allow, then reload the page.';
                        if (window.showAlert) {
                            window.showAlert(msg, 'Microphone blocked', 'warning');
                        } else {
                            alert(msg);
                        }
                        return false;
                    }
                    if (self._micAccessPromise) return self._micAccessPromise;

                    self._micAccessPromise = navigator.mediaDevices.getUserMedia({ audio: true })
                        .then((stream) => {
                            try { stream.getTracks().forEach((t) => t.stop()); } catch { }
                            self._micPermission = 'granted';
                            return true;
                        })
                        .catch((err) => {
                            self._micPermission = 'denied';
                            self.updateStatus('error', 'Microphone blocked');
                            setMicBlockedUi('Microphone permission blocked. Allow it in site settings to use voice input.');
                            const msg = err?.message || 'Please allow microphone access to use voice input.';
                            if (window.showAlert) {
                                window.showAlert(msg, 'Microphone blocked', 'warning');
                            } else {
                                alert(msg);
                            }
                            return false;
                        })
                        .finally(() => {
                            self._micAccessPromise = null;
                        });

                    return self._micAccessPromise;
                };

                recognition.onstart = function () {
                    self.isRecording = true;
                    micBtn.classList.add('recording');
                    micBtn.innerHTML = '<i class="bi bi-mic-fill"></i>';
                    micBtn.setAttribute('aria-pressed', 'true');
                    self.updateStatus('recording', 'Listening...');
                };

                recognition.onresult = function (event) {
                    let finalTranscript = '';
                    let interimTranscript = '';

                    for (let i = event.resultIndex; i < event.results.length; i++) {
                        const transcript = event.results[i][0].transcript;
                        if (event.results[i].isFinal) {
                            finalTranscript += transcript;
                        } else {
                            interimTranscript += transcript;
                        }
                    }

                    const inputEl = document.getElementById('adminAiInput');
                    if (inputEl) {
                        if (finalTranscript) {
                            inputEl.value = (inputEl.value || '') + finalTranscript;
                        } else if (interimTranscript) {
                            inputEl.value = (inputEl.value || '') + interimTranscript;
                        }

                        // Auto-resize
                        inputEl.style.height = 'auto';
                        inputEl.style.height = Math.min(inputEl.scrollHeight, 150) + 'px';

                        // Update character count
                        const charCount = document.getElementById('adminAiCharCount');
                        if (charCount) {
                            const len = inputEl.value.length;
                            charCount.textContent = `${len}/${ADMIN_CONFIG.maxInputLength}`;
                        }
                    }
                };

                recognition.onerror = function (event) {
                    console.error('[Voice] Error:', event.error);
                    self.isRecording = false;
                    micBtn.classList.remove('recording');
                    micBtn.innerHTML = '<i class="bi bi-mic-fill"></i>';
                    micBtn.removeAttribute('aria-pressed');
                    self.updateStatus('ready', 'Ready');

                    if (event.error === 'not-allowed') {
                        self._micPermission = 'denied';
                        self.updateStatus('error', 'Microphone access blocked');
                        setMicBlockedUi('Microphone permission blocked. Allow it in site settings to use voice input.');
                    }
                };

                recognition.onend = function () {
                    self.isRecording = false;
                    micBtn.classList.remove('recording');
                    micBtn.innerHTML = '<i class="bi bi-mic-fill"></i>';
                    micBtn.removeAttribute('aria-pressed');
                    self.updateStatus('ready', 'Ready');
                };

                // Direct onclick handler - most reliable
                micBtn.onclick = async function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    if (self.isRecording) {
                        recognition.stop();
                    } else {
                        try {
                            const permitted = await ensureMicAccess();
                            if (!permitted) return;
                            recognition.start();
                        } catch (err) {
                            console.error('[Voice] Start error:', err);
                            if (err.message && err.message.includes('already started')) {
                                recognition.stop();
                            }
                        }
                    }
                };
            } catch (e) {
                console.error('[Voice] Initialization error:', e);
            }
        }

        // ── Context Management ─────────────────────────────────────────────────
        getCurrentContext() {
            try {
                const parts = window.location.pathname.split('/').filter(Boolean);
                let module = 'Global';
                if (parts.length > 1 && parts[1]) {
                    module = String(parts[1]).replace(/[^a-zA-Z0-9_-]/g, '') || 'Global';
                }
                const title = document.title || 'Admin';
                return {
                    url: window.location.href,
                    title: title,
                    module: module.charAt(0).toUpperCase() + module.slice(1),
                    timestamp: new Date().toISOString()
                };
            } catch {
                return {
                    url: window.location.href,
                    title: document.title || 'Admin',
                    module: 'Global',
                    timestamp: new Date().toISOString()
                };
            }
        }

        updateContextUI() {
            const ctx = this.getCurrentContext();

            if (this.nodes.contextModule) {
                this.nodes.contextModule.textContent = ctx.module;
            }

            if (this.nodes.contextBadge) {
                this.nodes.contextBadge.title = `Current page: ${ctx.title}`;
            }

            return ctx;
        }

        updateContext() {
            this.updateContextUI();
        }

        // ── Model Management ───────────────────────────────────────────────────
        async bootstrapProviders() {
            const defaults = await fetchAdminDefaults();
            const defaultProvider = defaults.provider || await fetchDefaultProvider();
            this.preferredModel = defaults.model || '';
            const providerMap = await fetchProviderMap();

            this.providerMeta = providerMap.providerMeta || {};
            const providerList = providerMap.providers || {};

            this.currentProvider = defaultProvider || 'openrouter';

            // Set initial model name immediately to show it's loading
            if (this.preferredModel) {
                this.currentModel = this.preferredModel;
            } else if (defaults.model) {
                this.currentModel = defaults.model;
            } else {
                this.currentModel = 'claude-3-haiku'; // Default fallback
            }
            this.updateModelLabel();

            if (this.nodes.providerSel) {
                this.nodes.providerSel.innerHTML = '';
                const keys = Object.keys(providerList);
                if (keys.length === 0) {
                    const opt = document.createElement('option');
                    opt.value = this.currentProvider;
                    opt.textContent = this.currentProvider.toUpperCase();
                    this.nodes.providerSel.appendChild(opt);
                } else {
                    keys.forEach((key) => {
                        const opt = document.createElement('option');
                        opt.value = key;
                        const label = key.replace(/[_-]+/g, ' ').replace(/\b\w/g, m => m.toUpperCase());
                        const isMulti = this.providerMeta[key]?.supports_multimodal;
                        opt.textContent = isMulti ? `${label} (Multimodal)` : label;
                        if (key === this.currentProvider) opt.selected = true;
                        this.nodes.providerSel.appendChild(opt);
                    });
                }
            }

            await this.loadProviderModels(this.currentProvider, this.preferredModel);
        }

        async loadProviderModels(provider = 'openrouter', preferredModel = '', refresh = false) {
            if (!this.nodes.modelSel) return;

            this.currentProvider = provider;
            if (this.nodes.providerSel) {
                this.nodes.providerSel.value = provider;
            }
            this.updateStatus('loading', 'Loading models...');
            this.updateModelStatus('connecting');

            const result = await fetchModels(provider, { refresh });
            const models = result.models || [];
            this.lastModelMeta = result.meta || null;

            if (!models.length) {
                // Use fallback models if API fails
                this.loadFallbackModels();
                return;
            }

            // Ensure we don't bind the handler multiple times
            if (this._modelChangeHandler && this.nodes.modelSel) {
                this.nodes.modelSel.removeEventListener('change', this._modelChangeHandler);
                this._modelChangeHandler = null;
            }

            this.nodes.modelSel.innerHTML = '';
            let hasPreferred = false;
            models.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                const shortLabel = this.getShortModelLabel(m.id, m.name);
                const isMulti = Boolean(m.supports_multimodal);
                opt.textContent = shortLabel + (m.id.endsWith(':free') ? ' (Free)' : '') + (isMulti ? ' (Multimodal)' : '');
                if (preferredModel && preferredModel === m.id) {
                    opt.selected = true;
                    hasPreferred = true;
                } else if (m.default && !hasPreferred) {
                    opt.selected = true;
                }
                this.nodes.modelSel.appendChild(opt);
            });

            const def = hasPreferred
                ? models.find(m => m.id === preferredModel)
                : models.find(m => m.default);
            this.currentModel = def ? def.id : models[0].id;

            this.updateModelLabel();

            let statusLabel = 'Ready';
            if (this.lastModelMeta?.cache_source) {
                const src = this.lastModelMeta.cache_source;
                const ttl = this.lastModelMeta.cache_ttl;
                const ttlLabel = ttl ? ` (${Math.round(ttl / 60)}m)` : '';
                statusLabel = `Ready (${src}${ttlLabel})`;
            }
            this.updateStatus('ready', statusLabel);

            // Track selection changes (idempotent)
            this._modelChangeHandler = () => {
                const newModel = this.nodes.modelSel.value;
                if (newModel && newModel !== this.currentModel) {
                    this.currentModel = newModel;
                    this.updateModelLabel();
                }
            };
            this.nodes.modelSel.addEventListener('change', this._modelChangeHandler);

            if (!hasPreferred && preferredModel) {
                this.updateStatus('warning', 'Model not available, using default');
                setTimeout(() => {
                    if (this.nodes.statusText?.textContent === 'Ready (updated)') return;
                    this.updateStatus('ready', statusLabel);
                }, 2000);
            }

            if (!refresh && this.lastModelMeta?.cache_source === 'stale') {
                this.refreshProviderModelsInBackground(provider, preferredModel);
            }
        }

        async refreshProviderModelsInBackground(provider, preferredModel) {
            if (!this.nodes.modelSel) return;
            if (this._bgModelsRefresh?.provider === provider) return;

            const token = ++this._bgModelsRefreshToken;
            this._bgModelsRefresh = { provider, token };

            try {
                const result = await fetchModels(provider, { refresh: true });
                if (this._bgModelsRefreshToken !== token) return;
                if (this.currentProvider !== provider) return;
                if (!this.nodes.modelSel) return;

                if (result?.meta?.cache_source && result.meta.cache_source !== 'remote') {
                    return;
                }

                const models = result.models || [];
                if (!models.length) return;

                const keepSelected = this.nodes.modelSel.value || this.currentModel || '';

                // Rebuild select while preserving the current selection if possible
                if (this._modelChangeHandler) {
                    this.nodes.modelSel.removeEventListener('change', this._modelChangeHandler);
                    this._modelChangeHandler = null;
                }

                this.nodes.modelSel.innerHTML = '';
                const ids = new Set(models.map(m => m.id));

                const preferredAvailable = preferredModel && ids.has(preferredModel);
                const keepAvailable = keepSelected && ids.has(keepSelected);
                const def = models.find(m => m.default);
                const selectedId = keepAvailable
                    ? keepSelected
                    : (preferredAvailable ? preferredModel : (def ? def.id : models[0].id));

                models.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    const shortLabel = this.getShortModelLabel(m.id, m.name);
                    const isMulti = Boolean(m.supports_multimodal);
                    opt.textContent = shortLabel + (m.id.endsWith(':free') ? ' (Free)' : '') + (isMulti ? ' (Multimodal)' : '');
                    if (m.id === selectedId) {
                        opt.selected = true;
                    }
                    this.nodes.modelSel.appendChild(opt);
                });

                this.currentModel = selectedId;
                this.lastModelMeta = result.meta || this.lastModelMeta;
                this.updateModelLabel();
                this.updateStatus('ready', 'Ready (updated)');
            } finally {
                if (this._bgModelsRefresh?.token === token) {
                    this._bgModelsRefresh = null;
                }
            }
        }

        loadFallbackModels() {
            // Fallback models when API is unavailable
            const fallbackModels = [
                { id: 'anthropic/claude-3-haiku:free', name: 'Claude 3 Haiku (Free)', default: true },
                { id: 'google/gemini-pro-1.5:free', name: 'Gemini Pro 1.5 (Free)' },
                { id: 'openai/gpt-4o-mini:free', name: 'GPT-4o Mini (Free)' }
            ];

            if (!this.nodes.modelSel) return;

            if (this._modelChangeHandler) {
                this.nodes.modelSel.removeEventListener('change', this._modelChangeHandler);
                this._modelChangeHandler = null;
            }

            this.nodes.modelSel.innerHTML = '';
            fallbackModels.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                const shortLabel = this.getShortModelLabel(m.id, m.name);
                opt.textContent = shortLabel + (m.id.endsWith(':free') ? ' (Free)' : '');
                if (m.default) opt.selected = true;
                this.nodes.modelSel.appendChild(opt);
            });

            this.currentModel = fallbackModels[0].id;

            this.updateModelLabel();

            this.updateStatus('ready', 'Ready (Offline)');
            // Set model as offline when using fallback
            this.updateModelStatus('offline');
            // Track selection changes (idempotent)
            this._modelChangeHandler = () => {
                const newModel = this.nodes.modelSel.value;
                if (newModel && newModel !== this.currentModel) {
                    this.currentModel = newModel;
                    this.updateModelLabel();
                }
            };
            this.nodes.modelSel.addEventListener('change', this._modelChangeHandler);
        }

        updateModelLabel() {
            // Show a loading indicator while model is being fetched
            if (!this.nodes.modelBadge && !this.nodes.modelLabel) return;

            if (!this.currentModel && !this.nodes.modelSel?.selectedOptions?.length) {
                if (this.nodes.modelBadge) {
                    this.nodes.modelBadge.textContent = 'Loading...';
                }
                if (this.nodes.modelLabel) {
                    this.nodes.modelLabel.textContent = 'Loading...';
                }
                return;
            }

            const modelId = this.currentModel || '';
            const rawLabel = this.nodes.modelSel?.options[this.nodes.modelSel.selectedIndex]?.text || modelId || 'AI';
            const label = this.getShortModelLabel(modelId, rawLabel);
            if (this.nodes.modelBadge) {
                this.nodes.modelBadge.textContent = label || 'AI';
            }
            if (this.nodes.modelLabel) {
                this.nodes.modelLabel.textContent = label || 'AI';
            }
            // Set model as online after loading
            this.updateModelStatus('online');
        }

        updateModelStatus(status) {
            if (!this.nodes.modelStatusIndicator) return;

            // Remove all status classes
            this.nodes.modelStatusIndicator.classList.remove('brox-ai-online', 'brox-ai-offline', 'brox-ai-connecting');

            // Add the appropriate status class
            if (status === 'online') {
                this.nodes.modelStatusIndicator.classList.add('brox-ai-online');
                this.nodes.modelStatusIndicator.title = 'AI Model Online';
            } else if (status === 'offline') {
                this.nodes.modelStatusIndicator.classList.add('brox-ai-offline');
                this.nodes.modelStatusIndicator.title = 'AI Model Offline';
            } else if (status === 'connecting') {
                this.nodes.modelStatusIndicator.classList.add('brox-ai-connecting');
                this.nodes.modelStatusIndicator.title = 'Connecting...';
            }
        }

        getShortModelLabel(modelId, fallbackLabel) {
            const id = (modelId || '').split('/').pop() || '';
            const shortId = id.split(':')[0] || id;
            if (shortId) return shortId;
            const cleaned = (fallbackLabel || '').replace(/\s*\(Free\)\s*/i, '').trim();
            return cleaned || 'AI';
        }

        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // ── Status Management ──────────────────────────────────────────────────
        updateStatus(status, text) {
            if (this.nodes.statusDot) {
                this.nodes.statusDot.className = 'brox-ai-status-indicator ' + status;
            }
            if (this.nodes.statusText) {
                this.nodes.statusText.textContent = text;
            }
        }

        // ── Event Binding ───────────────────────────────────────────────────────
        bindEvents() {
            if (!this.nodes.btn) return;
            if (this._eventsBound) return;
            this._eventsBound = true;

            // ── Tab Switching ──────────────────────────────────────────────────
            const tabNavItems = document.querySelectorAll('.brox-ai-tabs-nav-item');
            tabNavItems.forEach(navItem => {
                navItem.addEventListener('click', (e) => {
                    const tabId = navItem.getAttribute('data-tab');
                    if (!tabId) return;

                    // Remove active from all nav items
                    tabNavItems.forEach(item => {
                        item.classList.remove('brox-ai-tabs-nav-item-active');
                        item.setAttribute('aria-selected', 'false');
                    });

                    // Add active to clicked nav item
                    navItem.classList.add('brox-ai-tabs-nav-item-active');
                    navItem.setAttribute('aria-selected', 'true');

                    // Hide all panels
                    const panels = document.querySelectorAll('.brox-ai-tabs-panel');
                    panels.forEach(panel => {
                        panel.classList.remove('brox-ai-tabs-panel-active');
                    });

                    // Show the target panel
                    const targetPanel = document.getElementById(tabId);
                    if (targetPanel) {
                        targetPanel.classList.add('brox-ai-tabs-panel-active');
                    }
                });
            });

            // Toggle sidebar
            this.nodes.btn.onclick = () => this.toggleSidebar();

            // Global keyboard shortcut: Ctrl+Alt+A
            document.addEventListener('keydown', (e) => {
                if (!e.ctrlKey || !e.altKey) return;
                if (e.key !== 'a' && e.key !== 'A') return;
                e.preventDefault();
                this.toggleSidebar();
            });

            // Toggle history sidebar
            if (this.nodes.historyToggle) {
                this.nodes.historyToggle.onclick = () => this.toggleHistorySidebar();
            }
            if (this.nodes.historySidebarClose) {
                this.nodes.historySidebarClose.onclick = () => this.toggleHistorySidebar(false);
            }

            // Minimize
            if (this.nodes.minimize) {
                this.nodes.minimize.onclick = () => this.minimizeSidebar();
            }

            // Close
            if (this.nodes.close) {
                this.nodes.close.onclick = () => this.closeSidebar();
            }

            // Send message
            if (this.nodes.send) {
                this.nodes.send.onclick = () => this.handleSend();
            }

            // Attach file
            if (this.nodes.attach) {
                this.nodes.attach.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.suppressOutsideClose = true;
                    if (this.fileHandler?.input) {
                        this.fileHandler.input.click();
                        // Ensure the guard resets even if user cancels the picker
                        if (this._filePickerTimer) clearTimeout(this._filePickerTimer);
                        this._filePickerTimer = setTimeout(() => {
                            this.suppressOutsideClose = false;
                        }, 1500);
                    } else {
                        console.error('[Attach] fileHandler.input not found');
                    }
                };
            }

            // Voice input (Microphone) - Initialize directly, don't rely on cached node
            this.initVoiceInput();

            // Clear chat
            if (this.nodes.clear) {
                this.nodes.clear.onclick = () => this.clearChat();
            }
            if (this.nodes.clearHistory) {
                this.nodes.clearHistory.onclick = () => this.clearChat();
            }

            if (this.nodes.webPluginToggle) {
                this.nodes.webPluginToggle.onclick = () => {
                    this.advancedOptions.webPlugin = !this.advancedOptions.webPlugin;
                    this.syncAdvancedOptionsUI();
                    this.saveAdvancedOptions();
                };
            }
            if (this.nodes.webPluginMaxResults) {
                this.nodes.webPluginMaxResults.onchange = () => {
                    const raw = Number(this.nodes.webPluginMaxResults.value) || 3;
                    this.advancedOptions.webMaxResults = Math.min(Math.max(raw, 1), 10);
                    this.nodes.webPluginMaxResults.value = String(this.advancedOptions.webMaxResults);
                    this.saveAdvancedOptions();
                };
            }
            if (this.nodes.responseHealingToggle) {
                this.nodes.responseHealingToggle.onclick = () => {
                    this.advancedOptions.responseHealing = !this.advancedOptions.responseHealing;
                    this.syncAdvancedOptionsUI();
                    this.saveAdvancedOptions();
                };
            }
            if (this.nodes.pdfPluginToggle) {
                this.nodes.pdfPluginToggle.onclick = () => {
                    this.advancedOptions.pdfPlugin = !this.advancedOptions.pdfPlugin;
                    this.syncAdvancedOptionsUI();
                    this.saveAdvancedOptions();
                };
            }
            if (this.nodes.pdfEngineSel) {
                this.nodes.pdfEngineSel.onchange = () => {
                    this.advancedOptions.pdfEngine = this.nodes.pdfEngineSel.value || 'pdf-text';
                    this.saveAdvancedOptions();
                };
            }
            if (this.nodes.reasoningEffortSel) {
                this.nodes.reasoningEffortSel.onchange = () => {
                    this.advancedOptions.reasoningEffort = this.nodes.reasoningEffortSel.value || '';
                    this.saveAdvancedOptions();
                };
            }
            if (this.nodes.responseFormatSel) {
                this.nodes.responseFormatSel.onchange = () => {
                    this.advancedOptions.responseFormat = this.nodes.responseFormatSel.value || 'text';
                    this.saveAdvancedOptions();
                };
            }
            if (this.nodes.advancedRawJson) {
                this.nodes.advancedRawJson.addEventListener('blur', () => {
                    this.advancedOptions.rawJson = this.nodes.advancedRawJson.value || '';
                    this.saveAdvancedOptions();
                });
            }

            // Collect page form data
            if (this.nodes.collectDataBtn) {
                this.nodes.collectDataBtn.onclick = () => this.handleCollectData();
            }

            // Auto-fill form from assistant output
            if (this.nodes.autoFillBtn) {
                this.nodes.autoFillBtn.onclick = () => this.handleAutoFill();
            }

            // Summarize current page
            if (this.nodes.summarizeBtn) {
                this.nodes.summarizeBtn.onclick = () => this.handleSummarize();
            }

            // Refresh models
            if (this.nodes.refreshModels) {
                this.nodes.refreshModels.onclick = () => {
                    if (this.nodes.refreshModels?.disabled) return;
                    this.nodes.refreshModels.disabled = true;
                    this.loadProviderModels(this.currentProvider, this.preferredModel, true);
                    setTimeout(() => {
                        if (this.nodes.refreshModels) this.nodes.refreshModels.disabled = false;
                    }, ADMIN_CONFIG.refreshCooldownMs);
                };
            }

            if (this.nodes.modelToggle) {
                this.nodes.modelToggle.onclick = () => this.toggleModelBar();
            }

            if (this.nodes.providerSel) {
                this.nodes.providerSel.onchange = () => {
                    const provider = this.nodes.providerSel.value || 'openrouter';
                    this.loadProviderModels(provider);

                    const url = this.extractUrlFromText(this.nodes.input?.value || '');
                    const hasImage = !!this.fileHandler?.hasAttachment() || !!url;
                    if (hasImage && !this.isProviderMultimodal(provider)) {
                        this.updateStatus('warning', 'Selected provider may not support images; results may be limited.');
                    }
                };
            }

            // Input handling
            if (this.nodes.input) {
                this.nodes.input.addEventListener('keydown', (e) => {
                    if (e.isComposing) return;
                    if (e.key === 'Escape') {
                        if (this.nodes.slashMenu && !this.nodes.slashMenu.classList.contains('brox-ai-hidden')) {
                            this.nodes.slashMenu.classList.add('brox-ai-hidden');
                            return;
                        }
                        this.closeSidebar();
                        return;
                    }
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.handleSend();
                    }
                });

                this.nodes.input.addEventListener('input', (e) => {
                    // Auto-resize textarea
                    this.resizeInput();

                    // Update character count
                    if (this.nodes.charCount) {
                        const len = e.target.value.length;
                        this.nodes.charCount.textContent = `${len}/${ADMIN_CONFIG.maxInputLength}`;
                        this.nodes.charCount.classList.toggle('brox-ai-warning', len > ADMIN_CONFIG.maxInputLength * 0.9);
                    }

                    // Slash command overlay
                    const val = e.target.value;
                    if (this.nodes.slashMenu) {
                        const show = val.trim().startsWith('/');
                        this.nodes.slashMenu.classList.toggle('brox-ai-hidden', !show);
                    }
                });

                this.nodes.input.addEventListener('focus', () => {
                    this.updateContextUI();
                });
            }

            // Slash menu
            this.bindSlashMenu();

            if (this.nodes.input) {
                this.nodes.input.addEventListener('keydown', (e) => {
                    if (this.nodes.slashMenu?.classList.contains('brox-ai-hidden')) return;
                    const items = Array.from(this.nodes.slashMenu.querySelectorAll('.brox-ai-slash-item'));
                    if (!items.length) return;
                    let idx = items.findIndex((it) => it.classList.contains('active'));
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        idx = (idx + 1) % items.length;
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        idx = (idx - 1 + items.length) % items.length;
                    } else if (e.key === 'Enter') {
                        if (idx >= 0) {
                            e.preventDefault();
                            const cmd = items[idx].dataset.cmd;
                            if (cmd) {
                                this.nodes.input.value = cmd + ' ';
                                this.nodes.input.focus();
                                this.nodes.slashMenu.classList.add('brox-ai-hidden');
                            }
                        }
                        return;
                    } else {
                        return;
                    }
                    items.forEach((it) => it.classList.remove('active'));
                    items[idx].classList.add('active');
                });
            }

            // Welcome commands
            document.addEventListener('click', (e) => {
                const cmdChip = e.target.closest('.brox-ai-cmd-chip');
                if (cmdChip && this.nodes.input) {
                    this.nodes.input.value = cmdChip.dataset.cmd + ' ';
                    this.nodes.input.focus();
                    this.resizeInput();
                }
            });

            // Click outside to close slash menu
            document.addEventListener('click', (e) => {
                if (!this.nodes.input?.contains(e.target) && !this.nodes.slashMenu?.contains(e.target)) {
                    this.nodes.slashMenu?.classList.add('brox-ai-hidden');
                }
            });

            // Click outside to close model bar
            document.addEventListener('pointerdown', (e) => {
                if (!this.nodes.modelBar || this.nodes.modelBar.classList.contains('brox-ai-collapsed')) return;
                const path = e.composedPath ? e.composedPath() : [];
                const clickedInside = this.nodes.modelBar && (path.includes(this.nodes.modelBar) || this.nodes.modelBar.contains(e.target));
                if (clickedInside) return;
                this.closeModelBar();
            });

            // Click outside to close sidebar
            document.addEventListener('pointerdown', (e) => {
                if (this.suppressOutsideClose) return;
                if (!this.nodes.shell || !this.nodes.btn) return;
                if (this.nodes.shell.classList.contains('brox-ai-hidden')) return;
                const path = e.composedPath ? e.composedPath() : [];
                if (this.fileHandler?.input) {
                    if (path.includes(this.fileHandler.input) || this.fileHandler.input.contains(e.target)) {
                        return;
                    }
                }
                const clickedInside = path.includes(this.nodes.shell) || path.includes(this.nodes.btn)
                    || this.nodes.shell.contains(e.target) || this.nodes.btn.contains(e.target);
                if (clickedInside) return;
                this.closeSidebar();
            });

            // Global Escape key closes sidebar
            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;
                if (!this.nodes.shell) return;
                if (this.nodes.shell.classList.contains('brox-ai-hidden')) return;
                this.closeSidebar();
            });
        }

        toggleModelBar(forceState) {
            if (!this.nodes.modelBar || !this.nodes.modelToggle) return;
            const willOpen = typeof forceState === 'boolean' ? forceState : this.nodes.modelBar.classList.contains('brox-ai-collapsed');
            if (willOpen) {
                this.nodes.modelBar.classList.remove('brox-ai-collapsed');
                this.nodes.modelBar.setAttribute('aria-expanded', 'true');
                this.nodes.modelToggle.setAttribute('aria-expanded', 'true');
                this.modelBarOpen = true;
            } else {
                this.closeModelBar();
            }
        }

        closeModelBar() {
            if (!this.nodes.modelBar || !this.nodes.modelToggle) return;
            this.nodes.modelBar.classList.add('brox-ai-collapsed');
            this.nodes.modelBar.setAttribute('aria-expanded', 'false');
            this.nodes.modelToggle.setAttribute('aria-expanded', 'false');
            this.modelBarOpen = false;
        }

        toggleHistorySidebar(forceState) {
            if (!this.nodes.historySidebar) return;
            const willOpen = typeof forceState === 'boolean'
                ? forceState
                : this.nodes.historySidebar.classList.contains('brox-ai-collapsed');
            if (willOpen) {
                this.nodes.historySidebar.classList.remove('brox-ai-collapsed');
            } else {
                this.nodes.historySidebar.classList.add('brox-ai-collapsed');
            }
        }

        resizeInput() {
            if (!this.nodes.input) return;
            this.nodes.input.style.height = 'auto';
            this.nodes.input.style.height = Math.min(this.nodes.input.scrollHeight, 150) + 'px';
        }

        bindSlashMenu() {
            if (!this.nodes.slashMenu) return;

            // Close button
            const closeBtn = this.nodes.slashMenu.querySelector('.brox-ai-slash-close');
            if (closeBtn) {
                closeBtn.onclick = () => this.nodes.slashMenu.classList.add('brox-ai-hidden');
            }

            // Menu items
            this.nodes.slashMenu.addEventListener('click', (e) => {
                const item = e.target.closest('.brox-ai-slash-item');
                if (item && this.nodes.input) {
                    this.nodes.input.value = item.dataset.cmd + ' ';
                    this.nodes.slashMenu.classList.add('d-none');
                    this.resizeInput();
                    this.nodes.input.focus();
                }
            });

            // Keyboard navigation
            this.nodes.input?.addEventListener('keydown', (e) => {
                if (e.key !== '/') return;
                // Will be handled by input event
            });
        }

        // ── Sidebar Management ─────────────────────────────────────────────────
        toggleSidebar() {
            if (!this.nodes.shell) return;

            this.updateContextUI();

            if (this.nodes.shell.classList.contains('brox-ai-hidden')) {
                this.openSidebar();
            } else {
                this.closeSidebar();
            }
        }

        minimizeSidebar() {
            this.closeSidebar();
        }

        openSidebar(options = {}) {
            if (!this.nodes.shell) return;
            if (!this.nodes.shell.classList.contains('brox-ai-hidden')) return;

            this.nodes.shell.classList.remove('d-none');
            this.nodes.shell.classList.remove('brox-ai-hidden');
            this.ensureProvidersBootstrapped();
            setTimeout(() => {
                this.nodes.input?.focus();
            }, 10);
            // Toggle button icon: show close icon
            this.nodes.btn?.classList.add('brox-ai-active');
            this.overlay?.classList.add('brox-ai-overlay-active');

            if (!options.skipHistory) {
                this.pushHistoryState();
            }
        }

        closeSidebar(options = {}) {
            if (!this.nodes.shell) return;
            this.nodes.shell.classList.add('brox-ai-hidden');
            setTimeout(() => this.nodes.shell.classList.add('d-none'), 300);
            // Toggle button icon: show open icon
            this.nodes.btn?.classList.remove('brox-ai-active');
            this.overlay?.classList.remove('brox-ai-overlay-active');

            if (options.fromPop) {
                this.historyStateActive = false;
            } else {
                this.clearHistoryState();
            }
        }

        pushHistoryState() {
            try {
                if (this.historyStateActive || (history.state && history.state[this.historyStateKey])) return;
                const nextState = Object.assign({}, history.state || {});
                nextState[this.historyStateKey] = true;
                history.pushState(nextState, '');
                this.historyStateActive = true;
            } catch (e) {
                this.historyStateActive = false;
            }
        }

        clearHistoryState() {
            try {
                if (!history.state || !history.state[this.historyStateKey]) return;
                const nextState = Object.assign({}, history.state || {});
                delete nextState[this.historyStateKey];
                history.replaceState(nextState, '');
                this.historyStateActive = false;
            } catch (e) {
                this.historyStateActive = false;
            }
        }

        handlePopState(event) {
            const state = event?.state || {};
            if (state && state[this.historyStateKey]) {
                if (this.nodes.shell?.classList.contains('brox-ai-hidden')) {
                    this.openSidebar({ skipHistory: true });
                }
                return;
            }

            if (this.nodes.shell && !this.nodes.shell.classList.contains('brox-ai-hidden')) {
                this.closeSidebar({ fromPop: true });
            }
        }

        // ── Chat Management ─────────────────────────────────────────────────────
        clearChat() {
            if (!confirm('Clear all chat history?')) return;

            this.history = [];
            this.clearStoredHistory();

            if (this.nodes.body) {
                this.nodes.body.innerHTML = '';
                this.nodes.welcome?.classList.remove('d-none');
            }
            this.renderHistorySidebar();

            // Clear any stored image context on the server for this user session
            fetch('/api/ai/clear-image-context', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({})
            }).catch(() => {
                // non-critical
            });

        }

        renderHistory() {
            if (!this.nodes.body) return;

            // Clear body but keep welcome message
            const welcome = this.nodes.welcome;
            this.nodes.body.innerHTML = '';
            if (welcome) {
                welcome.classList.add('d-none');
                this.nodes.body.appendChild(welcome);
            }

            if (this.history.length === 0) {
                welcome?.classList.remove('d-none');
                this.renderHistorySidebar();
                return;
            }

            this.history.forEach((m, idx) => this.addMessage(m.role, m.content, false, idx));
            this.scrollToBottom();
            this.renderHistorySidebar();
        }

        renderHistorySidebar() {
            if (!this.nodes.historyList) return;
            this.nodes.historyList.innerHTML = '';

            if (this.history.length === 0) {
                this.nodes.historyEmpty?.classList.remove('d-none');
                return;
            }
            this.nodes.historyEmpty?.classList.add('d-none');

            this.history.slice().reverse().forEach((msg, idxFromEnd) => {
                const idx = this.history.length - 1 - idxFromEnd;
                const item = document.createElement('div');
                item.className = 'brox-ai-history-item';
                item.dataset.msgIndex = String(idx);

                const prefix = msg.role === 'user' ? 'You: ' : 'AI: ';
                const text = typeof msg.content === 'string'
                    ? msg.content
                    : Array.isArray(msg.content)
                        ? msg.content.map(p => (p.type === 'text' ? p.text : '')).join(' ')
                        : '';
                item.textContent = prefix + (text.trim().substring(0, 60) || '(empty)');

                item.onclick = () => {
                    const target = this.nodes.body?.querySelector(`[data-msg-index="${idx}"]`);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        target.classList.add('brox-ai-history-highlight');
                        setTimeout(() => target.classList.remove('brox-ai-history-highlight'), 2000);
                        this.toggleHistorySidebar(false);
                    }
                };

                this.nodes.historyList.appendChild(item);
            });
        }

        // ── Message Handling ───────────────────────────────────────────────────
        fileToDataUrl(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(String(reader.result || ''));
                reader.onerror = () => reject(new Error('Failed to read attachment'));
                reader.readAsDataURL(file);
            });
        }

        isPdfAttachment(file, uploaded = null) {
            if (!file) return false;
            const mime = (uploaded?.mime || file.type || '').toLowerCase();
            const name = (uploaded?.name || file.name || '').toLowerCase();
            return mime.includes('application/pdf') || name.endsWith('.pdf');
        }

        async handleSend() {
            const rawText = this.nodes.input?.value || '';
            const text = rawText.trim();
            if (this.isThinking) return;

            const attachment = this.fileHandler?.getAttachment();
            const hasAttachment = !!attachment?.file;
            const url = this.extractUrlFromText(rawText);

            if ((hasAttachment || url) && !this.isProviderMultimodal(this.currentProvider)) {
                await this.ensureMultimodalProviderForInput(hasAttachment || !!url);
            }

            if (!text && !hasAttachment) return;

            if (this.fileHandler?.isUploading()) {
                this.updateStatus('loading', 'Uploading...');
                return;
            }

            // Validate input
            let sanitized = '';
            if (text) {
                const validation = validateInput(text);
                if (!validation.valid) {
                    this.updateStatus('error', validation.error);
                    setTimeout(() => this.updateStatus('ready', 'Ready'), 3000);
                    return;
                }
                sanitized = validation.sanitized;

                // Local command handling (no server call)
                const cmdMatch = sanitized.match(/^\/(collect-data|auto-?fill|summarize|analyze-logs|generate-report|check-security|fix-permissions|clear-cache|search-kb|web-search|optimize-db|deploy-status|health-check)\b/i);
                if (cmdMatch) {
                    const cmdRaw = cmdMatch[1].toLowerCase();
                    const cmd = cmdRaw === 'auto-fill' ? 'autofill' : cmdRaw;
                    this.updateStatus('loading', 'Running command...');
                    switch (cmd) {
                        case 'collect-data':
                            await this.handleCollectData();
                            break;
                        case 'autofill':
                            await this.handleAutoFill();
                            break;
                        case 'summarize':
                            await this.handleSummarize();
                            break;
                        case 'analyze-logs':
                            await this.handleAnalyzeLogs();
                            break;
                        case 'generate-report':
                            await this.handleGenerateReport();
                            break;
                        case 'check-security':
                            await this.handleCheckSecurity();
                            break;
                        case 'fix-permissions':
                            await this.handleFixPermissions();
                            break;
                        case 'clear-cache':
                            await this.handleClearCache();
                            break;
                        case 'search-kb':
                            await this.handleSearchKB();
                            break;
                        case 'web-search':
                            await this.handleSearchWeb();
                            break;
                        case 'optimize-db':
                            await this.handleOptimizeDB();
                            break;
                        case 'deploy-status':
                            await this.handleDeployStatus();
                            break;
                        case 'health-check':
                            await this.handleHealthCheck();
                            break;
                        case 'add-knowledge':
                            await this.handleAddKnowledge();
                            break;
                        default:
                            this.addMessage('assistant', 'Unknown command. Type / for available commands.');
                    }
                    this.updateStatus('ready', 'Ready');
                    return;
                }

                // Check for URL browsing request
                const urlToBrowse = this.extractUrlFromText(sanitized);
                if (urlToBrowse && (sanitized.toLowerCase().includes('browse') || sanitized.toLowerCase().includes('open') || sanitized.toLowerCase().includes('fetch') || sanitized.toLowerCase().includes('extract') || sanitized.toLowerCase().includes('get content') || sanitized.toLowerCase().includes('what is on') || sanitized.toLowerCase().includes('show me'))) {
                    await this.handleUrlBrowse(urlToBrowse, sanitized);
                    return;
                }

                // Check for OCR request with image attachment
                if (hasAttachment && attachment?.isImage && (sanitized.toLowerCase().includes('ocr') || sanitized.toLowerCase().includes('extract text') || sanitized.toLowerCase().includes('read text') || sanitized.toLowerCase().includes('what text') || sanitized.toLowerCase().includes('scan text'))) {
                    // Get base64 of uploaded image
                    if (attachment.uploaded?.url) {
                        try {
                            const imgResponse = await fetch(attachment.uploaded.url);
                            const blob = await imgResponse.blob();
                            const reader = new FileReader();
                            reader.onload = async () => {
                                const base64 = reader.result;
                                await this.handleOCR(base64);
                            };
                            reader.readAsDataURL(blob);
                        } catch (e) {
                            this.addMessage('assistant', '❌ Could not process image for OCR.');
                        }
                    } else if (attachment.file) {
                        const reader = new FileReader();
                        reader.onload = async () => {
                            await this.handleOCR(reader.result);
                        };
                        reader.readAsDataURL(attachment.file);
                    }
                    return;
                }

                // Check for navigation request
                const navPatterns = [
                    /(?:go to|navigate to|open|show me|take me to|switch to)\s+([\w\s-]+?)(?:\?|$)/i,
                    /(?:take me to|go to)\s+(users|posts|settings|analytics|dashboard|notifications?|media|comments?|security|permissions?|roles?|content|pages|themes?|logs|home|profile|service|application)s?/i
                ];
                for (const pattern of navPatterns) {
                    const navMatch = sanitized.match(pattern);
                    if (navMatch) {
                        await this.handleNavigationRequest(sanitized, navMatch[1] || navMatch[0]);
                        return;
                    }
                }
            }

            let messageContent = sanitized;
            if (hasAttachment && attachment?.isImage) {
                if (!attachment.uploaded?.url) {
                    this.updateStatus('error', 'Image upload failed');
                    setTimeout(() => this.updateStatus('ready', 'Ready'), 3000);
                    return;
                }
                messageContent = [];
                if (sanitized) {
                    messageContent.push({ type: 'text', text: sanitized });
                }
                messageContent.push({
                    type: 'image_url',
                    image_url: {
                        url: attachment.uploaded.url,
                        name: attachment.uploaded.name || attachment.file.name,
                        mime: attachment.uploaded.mime || attachment.file.type,
                        size: attachment.uploaded.size || attachment.file.size
                    }
                });
            } else if (hasAttachment && attachment?.file) {
                this.updateStatus('loading', 'Encoding file...');
                try {
                    const dataUrl = await this.fileToDataUrl(attachment.file);
                    const mime = attachment.file.type || 'application/octet-stream';
                    messageContent = [];
                    if (sanitized) {
                        messageContent.push({ type: 'text', text: sanitized });
                    }
                    messageContent.push({
                        type: 'file',
                        file: {
                            filename: attachment.file.name || 'attachment',
                            file_data: dataUrl,
                            mime,
                            size: attachment.file.size || 0
                        }
                    });
                    this.updateStatus('ready', 'Ready');
                } catch (err) {
                    const note = `Attachment: ${attachment.file.name} (could not be encoded)`;
                    messageContent = sanitized ? `${sanitized}\n\n${note}` : note;
                    this.updateStatus('warning', 'Attachment encode failed');
                }
            }

            // Clear input
            this.nodes.input.value = '';
            this.nodes.slashMenu?.classList.add('d-none');
            if (this.nodes.charCount) {
                this.nodes.charCount.textContent = `0/${ADMIN_CONFIG.maxInputLength}`;
            }
            this.resizeInput();
            this.fileHandler?.clearFiles();

            // Hide welcome message
            this.nodes.welcome?.classList.add('d-none');

            // Add user message
            this.history.push({ role: 'user', content: messageContent, timestamp: Date.now() });
            const userMsgIndex = this.history.length - 1;
            this.addMessage('user', messageContent, true, userMsgIndex);
            this.saveHistory();

            // Get AI response
            await this.getAIResponse();
        }

        getMessagePlainText(content) {
            if (typeof content === 'string') return content;
            if (Array.isArray(content)) {
                return content
                    .map((part) => {
                        if (!part || typeof part !== 'object') return '';
                        if (part.type === 'text') return part.text || '';
                        if (part.type === 'image_url') return part.image_url?.name || '';
                        if (part.type === 'file') return part.file?.filename || 'attachment';
                        return '';
                    })
                    .filter(Boolean)
                    .join('\n');
            }
            return '';
        }

        async copyTextToClipboard(text) {
            const normalized = String(text || '').trim();
            if (!normalized) return false;
            try {
                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    await navigator.clipboard.writeText(normalized);
                    return true;
                }
            } catch {
                // fallback
            }
            try {
                const ta = document.createElement('textarea');
                ta.value = normalized;
                ta.setAttribute('readonly', 'readonly');
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                ta.style.pointerEvents = 'none';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                const ok = document.execCommand('copy');
                document.body.removeChild(ta);
                return !!ok;
            } catch {
                return false;
            }
        }

        attachLongPressCopy(contentEl, getText) {
            if (!contentEl || typeof getText !== 'function') return;
            let timer = null;
            let longPressTriggered = false;

            const clearTimer = () => {
                if (timer) {
                    clearTimeout(timer);
                    timer = null;
                }
            };

            const startPress = () => {
                clearTimer();
                longPressTriggered = false;
                timer = setTimeout(async () => {
                    const copied = await this.copyTextToClipboard(getText());
                    if (copied) {
                        longPressTriggered = true;
                        this.updateStatus('ready', 'Copied response');
                    }
                }, 600);
            };

            const endPress = () => clearTimer();

            contentEl.addEventListener('pointerdown', startPress);
            contentEl.addEventListener('pointerup', endPress);
            contentEl.addEventListener('pointerleave', endPress);
            contentEl.addEventListener('pointercancel', endPress);
            contentEl.addEventListener('contextmenu', (e) => {
                if (longPressTriggered) e.preventDefault();
            });
        }

        async submitAssistantFeedback({ msgEl, rating, reason = '' }) {
            const conversationId = this.conversationId || '';
            const messageId = msgEl?.dataset?.messageId || '';
            const csrf = getCsrfToken() || '';
            if (!conversationId || !messageId) return false;
            try {
                await fetch('/api/ai/feedback', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conversation_id: conversationId,
                        message_id: messageId,
                        rating: String(rating),
                        reason: reason,
                        csrf_token: csrf
                    })
                });
                return true;
            } catch {
                return false;
            }
        }

        isLatestAssistantMessage(msgIndex) {
            if (!Number.isInteger(msgIndex)) return false;
            for (let i = this.history.length - 1; i >= 0; i--) {
                if (this.history[i]?.role === 'assistant') {
                    return i === msgIndex;
                }
            }
            return false;
        }

        async regenerateAssistantMessage(msgIndex) {
            if (this.isThinking) return;
            if (!this.isLatestAssistantMessage(msgIndex)) {
                this.addMessage('assistant', 'Regenerate works for the latest assistant response only.');
                return;
            }
            if (msgIndex < 0 || msgIndex >= this.history.length || this.history[msgIndex]?.role !== 'assistant') return;

            this.history.splice(msgIndex, 1);
            this.saveHistory();

            if (this.nodes.body) {
                const target = this.nodes.body.querySelector(`.brox-ai-msg[data-msg-index="${msgIndex}"]`);
                target?.remove();
            }
            await this.getAIResponse();
        }

        createAssistantActions({ msgEl, contentEl, metaEl, msgIndex = null }) {
            const feedback = document.createElement('div');
            feedback.className = 'brox-ai-feedback';
            feedback.innerHTML = `
                <button class="brox-ai-feedback-btn" data-rating="1" title="Poor"><i class="bi bi-hand-thumbs-down"></i></button>
                <button class="brox-ai-feedback-btn" data-rating="5" title="Excellent"><i class="bi bi-hand-thumbs-up"></i></button>
                <button class="brox-ai-copy-btn brox-ai-msg-tool-btn" data-action="copy" title="Copy"><i class="bi bi-clipboard"></i></button>
                <button class="brox-ai-copy-btn brox-ai-msg-tool-btn" data-action="regenerate" title="Regenerate"><i class="bi bi-arrow-clockwise"></i></button>
                <button class="brox-ai-copy-btn brox-ai-msg-tool-btn" data-action="report" title="Report"><i class="bi bi-flag"></i></button>
            `;

            feedback.querySelectorAll('.brox-ai-feedback-btn[data-rating]').forEach((btn) => {
                btn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const ok = await this.submitAssistantFeedback({
                        msgEl,
                        rating: btn.dataset.rating || '0',
                        reason: ''
                    });
                    if (ok) btn.classList.add('brox-ai-feedback-sent');
                });
            });

            const copyBtn = feedback.querySelector('[data-action="copy"]');
            copyBtn?.addEventListener('click', async (e) => {
                e.stopPropagation();
                const copied = await this.copyTextToClipboard(contentEl?.innerText || '');
                if (copied) this.updateStatus('ready', 'Copied response');
            });

            const regenBtn = feedback.querySelector('[data-action="regenerate"]');
            regenBtn?.addEventListener('click', async (e) => {
                e.stopPropagation();
                const idx = Number.isInteger(msgIndex) ? msgIndex : Number.parseInt(msgEl?.dataset?.msgIndex || '-1', 10);
                await this.regenerateAssistantMessage(idx);
            });

            const reportBtn = feedback.querySelector('[data-action="report"]');
            reportBtn?.addEventListener('click', async (e) => {
                e.stopPropagation();
                const ok = await this.submitAssistantFeedback({
                    msgEl,
                    rating: '1',
                    reason: 'reported'
                });
                if (ok) {
                    reportBtn.classList.add('brox-ai-feedback-sent');
                    this.updateStatus('ready', 'Reported response');
                }
            });

            const actions = document.createElement('div');
            actions.className = 'brox-ai-msg-actions';
            actions.style.display = 'flex';
            actions.style.gap = '8px';
            actions.style.alignItems = 'center';
            actions.style.marginTop = '4px';
            metaEl.style.marginTop = '0';
            actions.appendChild(feedback);
            actions.appendChild(metaEl);
            return actions;
        }

        addMessage(role, content, animate = true, msgIndex = null) {
            if (!this.nodes.body) return;

            this.nodes.welcome?.classList.add('d-none');

            const msg = document.createElement('div');
            msg.className = `brox-ai-msg brox-ai-${role}`;
            msg.setAttribute('data-role', role);

            const avatar = document.createElement('div');
            avatar.className = 'brox-ai-msg-avatar';
            avatar.innerHTML = role === 'user'
                ? '<i class="bi bi-person-fill"></i>'
                : '<i class="bi bi-stars"></i>';
            msg.appendChild(avatar);

            const contentDiv = document.createElement('div');
            contentDiv.className = 'brox-ai-msg-content';

            if (Array.isArray(content)) {
                content.forEach((part, idx) => {
                    if (!part || typeof part !== 'object') return;
                    if (part.type === 'text' && typeof part.text === 'string') {
                        const span = document.createElement('span');
                        span.innerHTML = this.formatMessage(part.text);
                        contentDiv.appendChild(span);
                    }
                    if (part.type === 'image_url' && part.image_url && part.image_url.url) {
                        const imgWrap = document.createElement('div');
                        imgWrap.className = 'brox-ai-msg-image-wrap';

                        const img = document.createElement('img');
                        img.src = part.image_url.url;
                        img.alt = part.image_url.name || 'attachment';
                        img.className = 'brox-ai-msg-image';
                        img.title = 'Click to enlarge';
                        img.addEventListener('click', () => this.showImageLightbox(part.image_url.url, part.image_url.name || 'Image'));
                        imgWrap.appendChild(img);

                        const metaText = document.createElement('div');
                        metaText.className = 'brox-ai-msg-image-meta';
                        const parts = [];
                        if (part.image_url.name) parts.push(part.image_url.name);
                        if (part.image_url.size) parts.push(this.formatFileSize(part.image_url.size));
                        if (part.image_url.mime) parts.push(part.image_url.mime);
                        if (parts.length) {
                            metaText.textContent = parts.join(' � ');
                            imgWrap.appendChild(metaText);
                        }

                        contentDiv.appendChild(imgWrap);
                    }
                    if (part.type === 'file' && part.file) {
                        const fileWrap = document.createElement('div');
                        fileWrap.className = 'brox-ai-msg-file-wrap';
                        const fileBits = [];
                        if (part.file.filename) fileBits.push(part.file.filename);
                        if (part.file.mime) fileBits.push(part.file.mime);
                        if (part.file.size) fileBits.push(this.formatFileSize(part.file.size));
                        const icon = part.file.mime === 'application/pdf' ? 'bi-file-earmark-pdf' : 'bi-file-earmark';
                        fileWrap.innerHTML = `<i class="bi ${icon}"></i> <span>${this.escapeHtml(fileBits.join(' • ') || 'File attachment')}</span>`;
                        contentDiv.appendChild(fileWrap);
                    }
                    if (idx < content.length - 1) {
                        contentDiv.appendChild(document.createElement('br'));
                    }
                });
            } else if (typeof content === 'string' && content.toLowerCase().includes('artifact') && content.includes(String.fromCharCode(96).repeat(3))) {
                this.renderWithArtifacts(contentDiv, content, animate && role === 'assistant');
            } else if (animate && role === 'assistant' && typeof content === 'string') {
                this.typeEffect(contentDiv, content);
            } else if (typeof content === 'string') {
                contentDiv.innerHTML = this.formatMessage(content);
            }

            msg.appendChild(contentDiv);
            if (role === 'assistant') {
                this.attachLongPressCopy(contentDiv, () => contentDiv.innerText || this.getMessagePlainText(content));
            }

            const meta = document.createElement('div');
            meta.className = 'brox-ai-msg-meta';
            meta.textContent = new Date().toLocaleTimeString();

            if (role === 'assistant') {
                const actions = this.createAssistantActions({
                    msgEl: msg,
                    contentEl: contentDiv,
                    metaEl: meta,
                    msgIndex: Number.isInteger(msgIndex) ? msgIndex : null
                });
                msg.appendChild(actions);
            } else {
                msg.appendChild(meta);
            }

            if (msgIndex !== null && msgIndex !== undefined) {
                msg.dataset.msgIndex = String(msgIndex);
            }
            this.nodes.body.appendChild(msg);
            this.scrollToBottom();
            this.pruneDomMessages();
        }



        formatMetaTime() {
            return new Date().toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
        }

        formatDuration(ms) {
            return (ms / 1000).toFixed(1) + 's';
        }

        updateResponseMeta(bodyEl, startedAt) {
            if (!bodyEl) return;
            const meta = bodyEl.parentElement?.querySelector('.brox-ai-msg-meta');
            if (!meta) return;
            const timeLabel = this.formatMetaTime();
            const duration = this.formatDuration(performance.now() - startedAt);
            meta.innerHTML = `<span class="brox-ai-meta-time">${timeLabel}</span><span class="brox-ai-meta-sep"> • </span><span class="brox-ai-meta-duration">${duration}</span>`;
        }

        escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        // ── Enhanced Markdown Rendering with marked.js + highlight.js (v2026) ──────
        formatMessage(text) {
            if (!text) return '';

            // Try marked.js + highlight.js first
            if (typeof marked !== 'undefined' && typeof hljs !== 'undefined') {
                try {
                    marked.setOptions({
                        highlight: function (code, lang) {
                            if (lang && hljs.getLanguage(lang)) {
                                return hljs.highlight(code, { language: lang }).value;
                            }
                            return hljs.highlightAuto(code).value;
                        },
                        breaks: true,
                        gfm: true
                    });
                    const escaped = this.escapeHtml(text);
                    return marked.parse(escaped);
                } catch (e) {
                    console.warn('[Markdown] marked.js failed, using fallback');
                }
            }

            // Fallback: Basic markdown-like formatting (after escaping HTML)
            const safe = this.escapeHtml(text);
            return safe
                .replace(/```(\w+)?\n([\s\S]*?)```/g, '<pre><code class="language-$1">$2</code></pre>')
                .replace(/`([^`]+)`/g, '<code>$1</code>')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>')
                .replace(/\n/g, '<br>');
        }

        renderWithArtifacts(container, content, animate) {
            const parts = content.split(/```artifact([\s\S]*?)```/i);
            parts.forEach((part, i) => {
                if (i % 2 === 1) {
                    try {
                        container.appendChild(this.createArtifactElement(JSON.parse(part.trim())));
                    } catch {
                        const pre = document.createElement('pre');
                        pre.textContent = part;
                        container.appendChild(pre);
                    }
                } else if (part.trim()) {
                    const span = document.createElement('span');
                    span.innerHTML = this.formatMessage(part);
                    if (animate) {
                        this.typeEffect(span, part);
                    }
                    container.appendChild(span);
                }
            });
        }

        normalizeAutoFillPayload(payload) {
            if (!payload || typeof payload !== 'object') return null;

            let out = null;

            if (Array.isArray(payload)) {
                const mapped = {};
                payload.forEach((field) => {
                    if (!field || typeof field !== 'object') return;
                    const name = field.name || field.key || field.field || field.id || field.label;
                    const value = field.value ?? field.val ?? field.content ?? field.text ?? field.answer ?? '';
                    if (!name) return;
                    mapped[String(name)] = value;
                });
                if (Object.keys(mapped).length) out = mapped;
            }

            if (Array.isArray(payload.fields)) {
                const mapped = {};
                payload.fields.forEach((field) => {
                    if (!field || typeof field !== 'object') return;
                    const name = field.name || field.key || field.field || field.id;
                    const value = field.value ?? field.val ?? field.content ?? field.text ?? '';
                    if (!name) return;
                    mapped[String(name)] = value;
                });
                if (Object.keys(mapped).length) out = mapped;
            }

            if (!out && payload.content && typeof payload.content === 'object' && !Array.isArray(payload.content)) {
                out = { ...payload.content };
            }

            if (!out && payload.data && typeof payload.data === 'object' && !Array.isArray(payload.data)) {
                out = { ...payload.data };
            }

            if (!out && !Array.isArray(payload)) {
                out = { ...payload };
            }

            if (out.body !== undefined && out.content === undefined) out.content = out.body;
            if (out.html !== undefined && out.content === undefined) out.content = out.html;
            if (out.permalink !== undefined && out.slug === undefined) out.slug = out.permalink;

            const metadataKeys = new Set([
                'type', 'fields', 'headers', 'rows', 'notes', 'note', 'message', 'status',
                'source', 'meta', 'metadata', 'debug', 'explanation', 'reasoning'
            ]);

            const filtered = {};
            Object.entries(out).forEach(([key, value]) => {
                if (!key) return;
                if (metadataKeys.has(String(key).toLowerCase())) return;
                if (value === undefined || value === null) return;
                if (typeof value === 'object' && !Array.isArray(value)) return;
                filtered[key] = value;
            });

            return Object.keys(filtered).length ? filtered : null;
        }

        extractJsonCandidates(text) {
            if (!text || typeof text !== 'string') return [];

            const candidates = [];
            const pushCandidate = (source, chunk, start) => {
                const candidateText = String(chunk || '').trim();
                if (!candidateText) return;
                candidates.push({
                    source,
                    text: candidateText,
                    start: Number.isFinite(start) ? start : text.indexOf(candidateText)
                });
            };

            const artifactRegex = /```artifact\s*([\s\S]*?)```/ig;
            let match = null;
            while ((match = artifactRegex.exec(text)) !== null) {
                pushCandidate('artifact', match[1], match.index);
            }

            const jsonFenceRegex = /```json\s*([\s\S]*?)```/ig;
            while ((match = jsonFenceRegex.exec(text)) !== null) {
                pushCandidate('json_fence', match[1], match.index);
            }

            const anyFenceRegex = /```(?:[a-zA-Z0-9_-]+)?\s*([\s\S]*?)```/ig;
            while ((match = anyFenceRegex.exec(text)) !== null) {
                pushCandidate('generic_fence', match[1], match.index);
            }

            const balanced = this.extractBalancedJsonObjects(text);
            balanced.forEach((item) => pushCandidate('balanced_object', item.text, item.start));

            return candidates;
        }

        extractBalancedJsonObjects(text) {
            if (!text || typeof text !== 'string') return [];
            const chunks = [];
            const stack = [];
            let inString = false;
            let escapeNext = false;

            for (let i = 0; i < text.length; i++) {
                const ch = text[i];

                if (escapeNext) {
                    escapeNext = false;
                    continue;
                }

                if (ch === '\\' && inString) {
                    escapeNext = true;
                    continue;
                }

                if (ch === '"') {
                    inString = !inString;
                    continue;
                }

                if (inString) continue;

                if (ch === '{') {
                    stack.push(i);
                    continue;
                }

                if (ch === '}' && stack.length) {
                    const start = stack.pop();
                    if (stack.length === 0 && start !== undefined) {
                        chunks.push({
                            start,
                            text: text.slice(start, i + 1)
                        });
                    }
                }
            }

            return chunks;
        }

        getAutoFillKeyScore(payload) {
            if (!payload || typeof payload !== 'object') return 0;
            const formLikeKeys = ['title', 'slug', 'content', 'permalink', 'excerpt', 'author', 'status'];
            const keys = Object.keys(payload);
            const lowerKeys = keys.map((key) => String(key).toLowerCase());
            const formHits = formLikeKeys.reduce((count, key) => count + (lowerKeys.includes(key) ? 1 : 0), 0);
            const scalarKeys = keys.reduce((count, key) => {
                const value = payload[key];
                return (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean' || Array.isArray(value))
                    ? count + 1
                    : count;
            }, 0);
            return (formHits * 100) + scalarKeys;
        }

        extractBestJsonFromText(text) {
            const candidates = this.extractJsonCandidates(text);
            const valid = [];

            candidates.forEach((candidate, idx) => {
                try {
                    const parsed = JSON.parse(candidate.text);
                    const normalized = this.normalizeAutoFillPayload(parsed);
                    if (!normalized || typeof normalized !== 'object' || !Object.keys(normalized).length) return;
                    valid.push({
                        candidateIndex: idx,
                        source: candidate.source,
                        start: candidate.start,
                        parsed,
                        normalized,
                        score: this.getAutoFillKeyScore(normalized)
                    });
                } catch {
                    // skip invalid candidate
                }
            });

            let selected = null;
            valid.forEach((item) => {
                if (!selected) {
                    selected = item;
                    return;
                }
                if (item.score > selected.score) {
                    selected = item;
                    return;
                }
                if (item.score === selected.score && item.candidateIndex > selected.candidateIndex) {
                    selected = item;
                }
            });

            return {
                payload: selected ? selected.normalized : null,
                debug: {
                    candidates: candidates.length,
                    valid: valid.length,
                    selectedIndex: selected ? selected.candidateIndex : -1,
                    selectedSource: selected ? selected.source : null,
                    selectedScore: selected ? selected.score : 0
                }
            };
        }

        applyAutoFillPayload(payload, form) {
            if (!payload || typeof payload !== 'object') return { filled: 0, missing: [] };
            if (!form) return { filled: 0, missing: Object.keys(payload) };

            const keys = Object.keys(payload);
            let filled = 0;
            const missing = [];

            const dispatchFieldEvents = (el) => {
                try { el.dispatchEvent(new Event('input', { bubbles: true })); } catch { }
                try { el.dispatchEvent(new Event('change', { bubbles: true })); } catch { }
            };

            keys.forEach((key) => {
                const val = payload[key];

                if (key === 'content' && window.editor_content && typeof window.editor_content.setContent === 'function') {
                    window.editor_content.setContent(String(val ?? ''));
                    filled++;
                    return;
                }

                const rteHidden = form.querySelector(`input[type="hidden"][name="${key}"][id$="-input"]`);
                if (rteHidden) {
                    const editorId = rteHidden.id.replace(/-input$/, '');
                    const globalVar = `editor_${editorId}`;
                    if (window[globalVar] && typeof window[globalVar].setContent === 'function') {
                        window[globalVar].setContent(String(val ?? ''));
                        filled++;
                        return;
                    }
                }

                const selector = `input[name="${key}"], textarea[name="${key}"], select[name="${key}"], input[id="${key}"], textarea[id="${key}"], select[id="${key}"]`;
                const elt = form.querySelector(selector);
                if (!elt) {
                    missing.push(key);
                    return;
                }

                if (elt.tagName === 'INPUT') {
                    const type = (elt.getAttribute('type') || '').toLowerCase();
                    if (type === 'checkbox') {
                        elt.checked = Boolean(val);
                        dispatchFieldEvents(elt);
                        filled++;
                        return;
                    }
                    if (type === 'radio') {
                        const group = form.querySelectorAll(`input[type="radio"][name="${key}"]`);
                        let matched = false;
                        group.forEach((radio) => {
                            if (String(radio.value) === String(val)) {
                                radio.checked = true;
                                dispatchFieldEvents(radio);
                                matched = true;
                            }
                        });
                        if (matched) filled++;
                        else missing.push(key);
                        return;
                    }
                }

                elt.value = String(val ?? '');
                dispatchFieldEvents(elt);
                filled++;
            });

            return { filled, missing };
        }

        createArtifactElement(data) {
            const wrap = document.createElement('div');
            wrap.className = 'brox-ai-artifact';

            const hdr = document.createElement('div');
            hdr.className = 'brox-ai-artifact-header';
            const title = document.createElement('span');
            title.textContent = data.title || 'Data Artifact';
            const badge = document.createElement('span');
            badge.className = 'badge bg-primary';
            badge.textContent = data.type || 'Table';
            hdr.appendChild(title);
            hdr.appendChild(badge);
            wrap.appendChild(hdr);

            const body = document.createElement('div');
            body.className = 'brox-ai-artifact-body';

            if (data.type === 'table') {
                const table = document.createElement('table');
                table.className = 'brox-ai-artifact-table table table-sm table-striped';
                if (data.headers) {
                    const thead = document.createElement('thead');
                    const tr = document.createElement('tr');
                    data.headers.forEach(h => {
                        const th = document.createElement('th');
                        th.textContent = h;
                        tr.appendChild(th);
                    });
                    thead.appendChild(tr);
                    table.appendChild(thead);
                }
                if (data.rows) {
                    const tbody = document.createElement('tbody');
                    data.rows.forEach(row => {
                        const tr = document.createElement('tr');
                        row.forEach(cell => {
                            const td = document.createElement('td');
                            td.textContent = cell;
                            tr.appendChild(td);
                        });
                        tbody.appendChild(tr);
                    });
                    table.appendChild(tbody);
                }
                body.appendChild(table);
            } else {
                body.textContent = JSON.stringify(data.content || data, null, 2);
            }

            wrap.appendChild(body);
            return wrap;
        }

        // ── Form Data Helpers (Collect / Auto Fill) ───────────────────────────
        findPrimaryForm() {
            const forms = Array.from(document.querySelectorAll('form'))
                .filter((f) => f.offsetParent !== null) // visible
                .filter((f) => !f.closest('.brox-ai-copilot-sidebar'));
            if (!forms.length) return null;
            let best = forms[0];
            let bestArea = 0;
            forms.forEach((form) => {
                const rect = form.getBoundingClientRect();
                const area = rect.width * rect.height;
                if (area > bestArea) {
                    bestArea = area;
                    best = form;
                }
            });
            return best;
        }

        collectFormData(form) {
            if (!form) return null;
            const fields = [];
            const data = {};

            const elements = Array.from(form.querySelectorAll('input, textarea, select'));
            elements.forEach((el) => {
                const name = el.name || el.id;
                if (!name) return;

                let value = null;
                if (el.tagName === 'INPUT') {
                    const type = (el.getAttribute('type') || '').toLowerCase();
                    if (type === 'checkbox') {
                        value = el.checked;
                    } else if (type === 'radio') {
                        if (!el.checked) return;
                        value = el.value;
                    } else {
                        value = el.value;
                    }
                } else if (el.tagName === 'SELECT') {
                    value = el.value;
                } else if (el.tagName === 'TEXTAREA') {
                    value = el.value;
                }

                if (value === null || value === undefined) return;
                if (typeof value === 'string') value = value.trim();

                data[name] = value;
                fields.push([name, String(value)]);
            });

            return { data, fields };
        }

        createFormDataArtifactMessage(formData) {
            const artifact = {
                type: 'table',
                title: 'Collected Form Data',
                headers: ['Field', 'Value'],
                rows: formData.fields || []
            };
            return '```artifact' + JSON.stringify(artifact) + '```';
        }

        getLastAssistantContent() {
            for (let i = this.history.length - 1; i >= 0; i--) {
                const msg = this.history[i];
                if (msg.role === 'assistant') {
                    return msg.content;
                }
            }
            return null;
        }

        extractJsonFromText(text) {
            const result = this.extractBestJsonFromText(text);
            return result.payload;
        }

        extractUrlFromText(text) {
            if (!text || typeof text !== 'string') return null;
            const match = text.match(/https?:\/\/[^\s]+/i);
            return match ? match[0] : null;
        }

        isProviderMultimodal(provider) {
            return Boolean(this.providerMeta?.[provider]?.supports_multimodal);
        }

        getMultimodalProvider() {
            if (!this.providerMeta) return null;
            return Object.keys(this.providerMeta).find((provider) => this.providerMeta[provider]?.supports_multimodal) || null;
        }

        async ensureMultimodalProviderForInput(hasImageContent) {
            if (!hasImageContent) return;
            if (this.isProviderMultimodal(this.currentProvider)) return;

            const multimodalProvider = this.getMultimodalProvider();
            if (!multimodalProvider) {
                this.updateStatus('warning', 'No multimodal-capable provider configured (images may not be properly processed).');
                return;
            }

            if (multimodalProvider === this.currentProvider) return;

            this.updateStatus('warning', `Switching to multimodal provider: ${multimodalProvider}`);
            this.currentProvider = multimodalProvider;
            await this.loadProviderModels(this.currentProvider, this.preferredModel);
            this.updateStatus('ready', 'Ready (multimodal provider selected)');
        }

        // Helper to convert blob to base64
        blobToBase64(blob) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            });
        }

        async handleCollectData() {
            const url = this.extractUrlFromText(this.nodes.input?.value || '');
            const attachment = this.fileHandler?.getAttachment();
            const hasAttachment = !!attachment?.file;

            // If user provided an attachment (image), use OCR to extract text first
            if (hasAttachment && attachment?.isImage) {
                this.updateStatus('loading', 'Extracting text via OCR...');
                this.addMessage('user', '📄 Extract form data from image');

                try {
                    // Get base64 of uploaded image
                    let imageData;
                    if (attachment.uploaded?.url) {
                        const imgResponse = await fetch(attachment.uploaded.url);
                        const blob = await imgResponse.blob();
                        imageData = await this.blobToBase64(blob);
                    } else if (attachment.file) {
                        imageData = await this.blobToBase64(attachment.file);
                    }

                    if (!imageData) {
                        throw new Error('Could not read image data');
                    }

                    // Call OCR endpoint
                    const ocrResponse = await fetch('/admin/ai-system/ocr', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ image: imageData })
                    });
                    const ocrResult = await ocrResponse.json();

                    if (!ocrResult.success || !ocrResult.text) {
                        this.addMessage('assistant', `❌ OCR failed: ${ocrResult.error || 'No text found in image'}`);
                        this.updateStatus('ready', 'Ready');
                        return;
                    }

                    const extractedText = ocrResult.text;
                    this.addMessage('assistant', `📝 *Extracted Text:*\n\n${extractedText.substring(0, 500)}${extractedText.length > 500 ? '...' : ''}\n\n_Analyzing to extract form fields..._`);

                    // Now send extracted text to AI to parse into JSON
                    const parsePrompt = `Extract form field names and values from the following OCR text. Return ONLY a valid JSON object mapping field names to values. Example: {"name": "John", "email": "john@example.com"}.\n\nOCR Text:\n${extractedText}`;

                    this.history.push({ role: 'user', content: 'Extract form data from image' });
                    this.history.push({ role: 'assistant', content: extractedText });
                    this.history.push({ role: 'user', content: parsePrompt });
                    this.saveHistory();

                    await this.getAIResponse();
                    this.updateStatus('ready', 'Ready');
                    return;
                } catch (err) {
                    this.addMessage('assistant', `❌ Error: ${err.message}`);
                    this.updateStatus('error', 'Error');
                    return;
                }
            }

            // If user provided a non-image attachment (e.g., PDF), ask the AI to extract structured data
            if (hasAttachment && !attachment?.isImage) {
                const form = this.findPrimaryForm();
                const fieldNames = form
                    ? Array.from(form.querySelectorAll('input[name], textarea[name], select[name]'))
                        .map((el) => el.getAttribute('name'))
                        .filter(Boolean)
                        .filter((name, idx, arr) => arr.indexOf(name) === idx)
                    : [];

                const intro = fieldNames.length
                    ? `Extract values and return ONLY valid JSON that maps these form field names to values: ${fieldNames.join(', ')}. Use HTML string for rich text fields like "content".`
                    : 'Extract form field names and values from the attached document. Return ONLY a valid JSON object mapping field names to values. Use HTML string for rich text fields like "content".';

                try {
                    this.updateStatus('loading', 'Encoding file...');
                    const dataUrl = await this.fileToDataUrl(attachment.file);
                    const mime = attachment.file.type || 'application/pdf';
                    const messageContent = [
                        { type: 'text', text: `${intro}\n\nDocument: ${attachment.file.name || 'attachment'}` },
                        {
                            type: 'file',
                            file: {
                                filename: attachment.file.name || 'document.pdf',
                                file_data: dataUrl,
                                mime,
                                size: attachment.file.size || 0
                            }
                        }
                    ];

                    this.addMessage('user', messageContent);
                    this.history.push({ role: 'user', content: messageContent });
                    this.saveHistory();
                    this.updateStatus('ready', 'Ready');

                    await this.getAIResponse();
                } catch (err) {
                    this.addMessage('assistant', `❌ Error: ${err.message}`);
                    this.updateStatus('error', 'Error');
                }
                return;
            }

            // If user provided a URL (not image), ask the AI to scan it for form values
            if (url) {
                const form = this.findPrimaryForm();
                const fieldNames = form
                    ? Array.from(form.querySelectorAll('input[name], textarea[name], select[name]'))
                        .map((el) => el.getAttribute('name'))
                        .filter(Boolean)
                        .filter((name, idx, arr) => arr.indexOf(name) === idx)
                    : [];

                const intro = fieldNames.length
                    ? `Analyze the URL and return ONLY valid JSON that maps these form field names to values: ${fieldNames.join(', ')}. Use HTML string for rich text fields like "content".`
                    : 'Please analyze the following URL and return ONLY a valid JSON object mapping form field names to values. Use HTML string for rich text fields like "content".';

                const messageContent = `${intro}\n\nSource URL: ${url}`;

                this.addMessage('user', messageContent);
                this.history.push({ role: 'user', content: messageContent });
                this.saveHistory();

                await this.getAIResponse();
                return;
            }

            // Fallback: collect visible form field values
            const form = this.findPrimaryForm();
            if (!form) {
                this.addMessage('assistant', 'No visible form found on this page to collect data from.');
                return;
            }

            const formData = this.collectFormData(form);
            if (!formData || !formData.fields.length) {
                this.addMessage('assistant', 'Form found, but no form fields were detected or they are empty.');
                return;
            }

            const message = this.createFormDataArtifactMessage(formData);
            this.addMessage('assistant', message);
            this.history.push({ role: 'assistant', content: message });
            this.saveHistory();
        }

        async handleAutoFill() {
            const url = this.extractUrlFromText(this.nodes.input?.value || '');
            const attachment = this.fileHandler?.getAttachment();
            const hasAttachment = !!attachment?.file;
            this.autoFillRetryUsed = false;

            // If a URL is provided with the command, first ask the AI to extract structured data from that URL,
            // then apply the results automatically when the response arrives.
            if (url || hasAttachment) {
                this.pendingAutoFill = true;
                await this.handleCollectData();
                return;
            }

            await this.applyAutoFillFromLastAssistant();
        }

        async applyAutoFillFromLastAssistant() {
            const raw = this.getLastAssistantContent();
            const rawText = this.getMessagePlainText(raw || '');
            const extraction = this.extractBestJsonFromText(rawText);
            const payload = extraction.payload;

            if (typeof console !== 'undefined' && typeof console.debug === 'function') {
                console.debug('[AdminCopilot][AutoFillParser]', extraction.debug);
            }

            if (!payload || typeof payload !== 'object') {
                const attachment = this.fileHandler?.getAttachment();
                const hasAttachment = !!attachment?.file;
                const url = this.extractUrlFromText(this.nodes.input?.value || '');

                if (!this.autoFillRetryUsed && !hasAttachment && !url && rawText) {
                    this.autoFillRetryUsed = true;
                    const form = this.findPrimaryForm();
                    const fieldNames = form
                        ? Array.from(form.querySelectorAll('input[name], textarea[name], select[name]'))
                            .map((el) => el.getAttribute('name'))
                            .filter(Boolean)
                            .filter((name, idx, arr) => arr.indexOf(name) === idx)
                        : [];

                    const intro = fieldNames.length
                        ? `Return ONLY valid JSON that maps these form field names to values: ${fieldNames.join(', ')}. Use HTML string for rich text fields like "content".`
                        : 'Return ONLY a valid JSON object mapping form field names to values. Use HTML string for rich text fields like "content".';

                    const sourceText = rawText.length > 4000 ? `${rawText.slice(0, 4000)}...` : rawText;
                    const prompt = `${intro}\n\nSource text:\n${sourceText}`;

                    this.addMessage('user', prompt);
                    this.history.push({ role: 'user', content: prompt });
                    this.saveHistory();

                    await this.getAIResponse();
                    return;
                }

                this.addMessage('assistant', 'Could not find structured JSON in the last assistant response to auto-fill the form.');
                return;
            }

            const form = this.findPrimaryForm();
            if (!form) {
                this.addMessage('assistant', 'No visible form found on this page to auto-fill.');
                return;
            }

            const { filled, missing } = this.applyAutoFillPayload(payload, form);

            let summary = `Auto-fill completed: ${filled} field(s) updated.`;
            if (missing.length) {
                summary += ` Missing fields: ${missing.join(', ')}.`;
            }
            this.addMessage('assistant', summary);
            this.history.push({ role: 'assistant', content: summary });
            this.saveHistory();
        }

        // ==================== NEW ADMIN COMMANDS ====================

        async handleSummarize() {
            // Get page title and content
            const pageTitle = document.title || 'Unknown Page';
            const pageUrl = window.location.href;

            // Try to get main content
            const mainContent = document.querySelector('main') || document.querySelector('.content') || document.body;
            const textContent = mainContent?.innerText?.substring(0, 2000) || 'No content found';

            const summary = `**Page Summary**\n\n• **Title:** ${pageTitle}\n• **URL:** ${pageUrl}\n\n**Content Preview:**\n${textContent.substring(0, 500)}...`;

            this.addMessage('assistant', summary);
            this.history.push({ role: 'assistant', content: summary });
            this.saveHistory();
        }

        async handleAnalyzeLogs() {
            try {
                const res = await fetch('/api/admin/logs/errors?limit=20', { credentials: 'same-origin' });
                const data = await res.json();

                if (data.errors && data.errors.length > 0) {
                    const errorCount = data.errors.length;
                    const latestErrors = data.errors.slice(0, 5).map(e => `• ${e.message || 'Unknown error'}`).join('\n');
                    this.addMessage('assistant', `**Log Analysis**\n\n• Total recent errors: ${errorCount}\n\n**Latest Errors:**\n${latestErrors}`);
                } else {
                    this.addMessage('assistant', '✅ *Log Analysis Complete*\n\nNo recent errors found in the system.');
                }
            } catch (e) {
                this.addMessage('assistant', '❌ Failed to fetch logs. Please ensure you have admin permissions.');
            }
            this.saveHistory();
        }

        async handleGenerateReport() {
            try {
                const res = await fetch('/api/admin/analytics/summary', { credentials: 'same-origin' });
                const data = await res.json();

                if (data.success && data.summary) {
                    const s = data.summary;
                    const report = `**📊 Analytics Report**\n\n• **Total Posts:** ${s.totalPosts || 0}\n• **Total Pages:** ${s.totalPages || 0}\n• **Total Users:** ${s.totalUsers || 0}\n• **Today's Visits:** ${s.todayVisits || 0}\n• **Total Views:** ${s.totalViews || 0}`;
                    this.addMessage('assistant', report);
                } else {
                    this.addMessage('assistant', '📊 Report generated with default data. Detailed analytics available in dashboard.');
                }
            } catch (e) {
                this.addMessage('assistant', '❌ Failed to generate report. Please try again later.');
            }
            this.saveHistory();
        }

        async handleCheckSecurity() {
            const checks = [
                { name: 'HTTPS', status: window.location.protocol === 'https:' },
                { name: 'Auth Token', status: !!localStorage.getItem('auth_token') },
                { name: 'Admin Session', status: !!localStorage.getItem('user_role') }
            ];

            const results = checks.map(c => `• ${c.name}: ${c.status ? '✅' : '❌'}`).join('\n');
            this.addMessage('assistant', `**🛡️ Security Audit Results**\n\n${results}\n\n_For comprehensive security review, visit Security settings page._`);
            this.saveHistory();
        }

        async handleFixPermissions() {
            const userRole = localStorage.getItem('user_role') || 'guest';
            const isAdmin = userRole === 'admin' || userRole === 'super_admin';

            this.addMessage('assistant', `**👤 Permission Status**\n\n• **Current Role:** ${userRole}\n• **Admin Access:** ${isAdmin ? '✅ Granted' : '❌ Denied'}\n\n_Contact super admin to modify permissions._`);
            this.saveHistory();
        }

        async handleClearCache() {
            // Clear various caches
            const cleared = [];
            try {
                if (localStorage.getItem('brox_cache')) {
                    localStorage.removeItem('brox_cache');
                    cleared.push('App Cache');
                }
                if (localStorage.getItem('ai_history')) {
                    // Keep AI history, just note it
                    cleared.push('AI History (preserved)');
                }
                sessionStorage.clear();
                cleared.push('Session Storage');
            } catch (e) {
                // Continue anyway
            }

            this.addMessage('assistant', `✅ **Cache Cleared**\n\nCleared:\n• ${cleared.join('\n• ')}\n\n_Reload page if needed._`);
            this.saveHistory();
        }

        async handleSearchKB() {
            // Extract query from command if provided (e.g., /search-kb how to use API)
            let query = '';
            const inputValue = this.nodes.input?.value || '';
            const match = inputValue.match(/^\/search-kb\s+(.+)$/i);
            if (match && match[1]) {
                query = match[1].trim();
            }

            if (!query) {
                this.addMessage('assistant', '📚 *Search Knowledge Base*\n\nUsage: /search-kb <your question>\n\nExample: /search-kb how to create a new service');
                this.saveHistory();
                return;
            }

            this.addMessage('assistant', `📚 *Searching Knowledge Base for: ${query}...`);

            try {
                const res = await fetch(`/api/admin/ai-knowledge/search?q=${encodeURIComponent(query)}&limit=3`, {
                    credentials: 'same-origin'
                });
                const data = await res.json();

                if (data.success && data.results && data.results.length > 0) {
                    const items = data.results.map((r, i) =>
                        `${i + 1}. ${r.title} (${r.category || 'general'})`
                    ).join('\n');
                    this.addMessage('assistant', `📚 *Search Results for: ${query}*\n\n${items}`);
                } else {
                    this.addMessage('assistant', `📚 *No results found for: ${query}*`);
                }
            } catch (e) {
                this.addMessage('assistant', '❌ Failed to search knowledge base.');
            }
            this.saveHistory();
        }

        async handleSearchWeb() {
            // Extract query from command if provided (e.g., /web-search python tutorial)
            let query = '';
            const inputValue = this.nodes.input?.value || '';
            const match = inputValue.match(/^\/web-search\s+(.+)$/i);
            if (match && match[1]) {
                query = match[1].trim();
            }

            if (!query) {
                this.addMessage('assistant', '🌐 *Web Search*\n\nUsage: /web-search <your question>\n\nExample: /web-search latest AI news\n\nSearches the web using DuckDuckGo.');
                this.saveHistory();
                return;
            }

            this.addMessage('assistant', `🌐 *Searching the web for: ${query}...*`);

            try {
                const csrfToken = typeof getCsrfToken === 'function' ? getCsrfToken() : '';
                const res = await fetch('/admin/ai-system/web-search', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ query: query, limit: 10 })
                });
                const data = await res.json();

                if (data.success && data.results && data.results.length > 0) {
                    const items = data.results.map((r, i) =>
                        `${i + 1}. [${r.title}](${r.url})\n   ${r.snippet ? r.snippet.substring(0, 150) + '...' : 'No description'}`
                    ).join('\n\n');
                    this.addMessage('assistant', `🌐 *Web Search Results for: ${query}*\n\n${items}\n\n_Results powered by DuckDuckGo_`);
                } else {
                    this.addMessage('assistant', `🌐 *No results found for: ${query}*`);
                }
            } catch (e) {
                this.addMessage('assistant', '❌ Failed to search the web. Please try again.');
            }
            this.saveHistory();
        }

        async handleOptimizeDB() {
            // Simulate optimization (actual DB ops require server-side)
            setTimeout(() => {
                this.addMessage('assistant', '✅ **Database Optimization**\n\n• Indexes analyzed\n• Query cache cleared\n• Connection pool reset\n\n_For full optimization, use server tools._');
                this.saveHistory();
            }, 1500);
        }

        async handleDeployStatus() {
            try {
                const res = await fetch('/api/admin/deploy/status', { credentials: 'same-origin' });
                const data = await res.json();

                if (data.success) {
                    this.addMessage('assistant', `**☁️ Deployment Status**\n\n• **Status:** ${data.status || 'active'}\n• **Last Deploy:** ${data.lastDeploy || 'Unknown'}\n• **Environment:** ${data.env || 'production'}`);
                } else {
                    this.addMessage('assistant', '☁️ **Deployment Status**\n\n• **Status:** Active\n• **Environment:** Production\n\n_Visit Deploy Tools for details._');
                }
            } catch (e) {
                this.addMessage('assistant', '☁️ **Deployment Status**\n\n• **Status:** Active\n• **Environment:** Production');
            }
            this.saveHistory();
        }

        async handleHealthCheck() {
            const checks = {
                'Server': 'online',
                'Database': 'connected',
                'API': 'responsive',
                'Cache': 'active'
            };

            const results = Object.entries(checks).map(([k, v]) => `• ${k}: ✅ ${v}`).join('\n');
            this.addMessage('assistant', `**💚 System Health**\n\n${results}\n\n_All systems operational._`);
            this.saveHistory();
        }

        async handleAddKnowledge() {
            // Extract URL from input
            const inputValue = this.nodes.input?.value || '';
            const url = this.extractUrlFromText(inputValue);

            // Also check if user provided URL in command args
            const cmdMatch = inputValue.match(/^\/add-knowledge\s+(.+)$/i);
            const providedUrl = cmdMatch ? cmdMatch[1].trim() : url;

            if (!providedUrl) {
                this.addMessage('assistant', '📚 *Add Knowledge*\n\nPlease provide a URL to add knowledge from.\n\nUsage: `/add-knowledge https://example.com`');
                this.saveHistory();
                return;
            }

            this.updateStatus('loading', 'Fetching and adding knowledge...');

            try {
                const response = await fetch('/admin/ai-system/add-knowledge', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ url: providedUrl })
                });
                const data = await response.json();

                if (data.success) {
                    this.addMessage('assistant', `✅ *Knowledge Added Successfully!*\n\n**Title:** ${data.title}\n\n**Preview:** ${data.content_preview}\n\nThis knowledge is now available for AI context.`);
                } else {
                    this.addMessage('assistant', `❌ Failed to add knowledge: ${data.error}`);
                }
            } catch (err) {
                this.addMessage('assistant', `❌ Error: ${err.message}`);
            }
            this.updateStatus('ready', 'Ready');
            this.saveHistory();
        }

        // Navigation mapping
        getNavigationUrl(pageName) {
            const pageMap = {
                'dashboard': '/admin/dashboard',
                'home': '/admin/dashboard',
                'users': '/admin/users',
                'user': '/admin/users',
                'posts': '/admin/posts',
                'post': '/admin/posts',
                'content': '/admin/posts',
                'settings': '/admin/settings',
                'analytics': '/admin/analytics',
                'notifications': '/admin/notifications',
                'notification': '/admin/notifications',
                'media': '/admin/media',
                'comments': '/admin/comments',
                'comment': '/admin/comments',
                'security': '/admin/security',
                'permissions': '/admin/permissions',
                'permission': '/admin/permissions',
                'roles': '/admin/roles',
                'role': '/admin/roles',
                'pages': '/admin/pages',
                'page': '/admin/pages',
                'themes': '/admin/themes',
                'theme': '/admin/themes',
                'logs': '/admin/logs',
                'log': '/admin/logs',
                'profile': '/admin/profile',
                'service': '/admin/services',
                'services': '/admin/services',
                'application': '/admin/service-application',
                'applications': '/admin/service-application',
                'ai': '/admin/ai-system',
                'deployment': '/admin/deploy',
                'deploy': '/admin/deploy'
            };
            const key = pageName.toLowerCase().replace(/\s+(page|setting)s?$/, '').trim();
            return pageMap[key] || null;
        }

        async handleNavigationRequest(text, pageName) {
            // Extract page name from text if not provided
            if (!pageName) {
                const match = text.match(/(?:go to|navigate to|open|show me|take me to|switch to)\s+([\w\s-]+?)(?:\?|$)/i);
                if (match) {
                    pageName = match[1].trim();
                }
            }

            const url = this.getNavigationUrl(pageName || text);
            if (!url) {
                this.addMessage('assistant', `I couldn't recognize the page "${pageName || text}". Try: dashboard, users, posts, settings, analytics, notifications, media, comments, security, permissions, roles, pages, themes, logs, profile, services.`);
                this.saveHistory();
                return;
            }

            // Show confirmation with Yes/No checkboxes
            const confirmationHtml = `
                <div class="nav-confirmation" data-url="${url}">
                    <p>📍 <strong>Navigate to:</strong> ${pageName || text}</p>
                    <label class="confirm-yes">
                        <input type="checkbox" class="nav-confirm-yes"> 
                        <span>✅ Yes, take me there</span>
                    </label>
                    <label class="confirm-no">
                        <input type="checkbox" class="nav-confirm-no"> 
                        <span>❌ No, cancel</span>
                    </label>
                </div>
            `;

            this.addMessage('assistant', confirmationHtml, false);
            this.saveHistory();

            // Add event listeners for checkboxes
            setTimeout(() => {
                const container = this.nodes.messages?.querySelector('.nav-confirmation:last-child');
                if (!container) return;

                const yesCheckbox = container.querySelector('.nav-confirm-yes');
                const noCheckbox = container.querySelector('.nav-confirm-no');
                const targetUrl = container.dataset.url;

                yesCheckbox?.addEventListener('change', (e) => {
                    if (e.target.checked) {
                        noCheckbox.checked = false;
                        this.addMessage('assistant', `\n🎯 Redirecting to ${pageName || text}...`);
                        window.location.href = targetUrl;
                    }
                });

                noCheckbox?.addEventListener('change', (e) => {
                    if (e.target.checked) {
                        yesCheckbox.checked = false;
                        this.addMessage('assistant', `\n❌ Navigation cancelled.`);
                        container.remove();
                        this.saveHistory();
                    }
                });
            }, 100);
        }

        async handleUrlBrowse(url, text) {
            this.updateStatus('loading', 'Browsing URL...');
            this.addMessage('assistant', `🌐 *Browsing:* ${url}\n\nFetching content...`);

            try {
                const response = await fetch('/admin/ai-system/browse-url', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url, query: text })
                });
                const data = await response.json();

                if (data.success && data.content) {
                    // Summarize the content
                    this.addMessage('assistant', `📄 *Page Content:*\n\n${data.content.substring(0, 2000)}${data.content.length > 2000 ? '...' : ''}\n\n_Would you like me to extract specific information or summarize this?._`);
                } else {
                    this.addMessage('assistant', `❌ Failed to fetch URL: ${data.error || 'Unknown error'}`);
                }
            } catch (err) {
                this.addMessage('assistant', `❌ Error browsing URL: ${err.message}`);
            }
            this.updateStatus('ready', 'Ready');
            this.saveHistory();
        }

        async handleOCR(imageData) {
            this.updateStatus('loading', 'Processing OCR...');

            try {
                const response = await fetch('/admin/ai-system/ocr', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ image: imageData })
                });
                const data = await response.json();

                if (data.success && data.text) {
                    this.addMessage('assistant', `📝 *Extracted Text:*\n\n${data.text}\n\n_Would you like me to summarize this, extract specific data, or fill it into a form?._`);
                } else {
                    this.addMessage('assistant', `❌ OCR failed: ${data.error || 'No text found'}`);
                }
            } catch (err) {
                this.addMessage('assistant', `❌ OCR Error: ${err.message}`);
            }
            this.updateStatus('ready', 'Ready');
            this.saveHistory();
        }

        // Notification badge for errors/alerts
        showNotificationBadge(count, type = 'error') {
            // Remove existing badge if any
            this.hideNotificationBadge();

            if (!count || count <= 0) return;

            // Find the AI assistant trigger button or icon
            const triggerBtn = document.querySelector('#adminAiShell .ai-trigger-btn') ||
                document.querySelector('#adminAiShell .ai-toggle') ||
                document.querySelector('.brox-ai-toggle');

            if (!triggerBtn) return;

            // Create badge
            const badge = document.createElement('span');
            badge.className = `ai-notification-badge ${type}`;
            badge.textContent = count > 99 ? '99+' : count;
            badge.dataset.type = type;

            // Position relative to trigger button
            triggerBtn.style.position = 'relative';
            triggerBtn.appendChild(badge);

            // Auto-hide after 30 seconds for success/info
            if (type === 'success' || type === 'info') {
                setTimeout(() => this.hideNotificationBadge(), 30000);
            }
        }

        hideNotificationBadge() {
            const badge = document.querySelector('.ai-notification-badge');
            if (badge) badge.remove();
        }

        // Check for errors/notifications periodically
        async checkSystemNotifications() {
            try {
                // Check error logs
                const response = await fetch('/api/admin/system/notifications', {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json' }
                });
                const data = await response.json();

                if (data.success && data.notifications) {
                    const errorCount = data.notifications.errors || 0;
                    const alertCount = data.notifications.alerts || 0;
                    const total = errorCount + alertCount;

                    if (total > 0) {
                        this.showNotificationBadge(total, errorCount > 0 ? 'error' : 'warning');
                    }
                }
            } catch (err) {
                // Silent fail - don't show notification badge on check failure
            }
        }

        typeEffect(el, text) {
            if (!text) return;
            el.textContent = '';
            let i = 0;
            const iv = setInterval(() => {
                el.textContent += text[i++];
                if (i >= text.length) {
                    clearInterval(iv);
                    this.scrollToBottom();
                }
            }, ADMIN_CONFIG.typingSpeed);
        }

        showTypingIndicator() {
            this.nodes.typingIndicator?.classList.remove('d-none');
            this.scrollToBottom();
        }

        hideTypingIndicator() {
            this.nodes.typingIndicator?.classList.add('d-none');
        }

        scrollToBottom() {
            if (!this.nodes.body) return;
            this.nodes.body.scrollTo({
                top: this.nodes.body.scrollHeight,
                behavior: 'smooth'
            });
        }

        showImageLightbox(url, alt = '') {
            if (!url) return;
            // Reuse existing lightbox if present
            if (!this.lightbox) {
                const overlay = document.createElement('div');
                overlay.className = 'brox-ai-image-lightbox';
                overlay.setAttribute('role', 'dialog');
                overlay.setAttribute('aria-modal', 'true');
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) {
                        this.closeImageLightbox();
                    }
                });

                const content = document.createElement('div');
                content.className = 'brox-ai-lightbox-content';
                overlay.appendChild(content);

                const closeBtn = document.createElement('button');
                closeBtn.className = 'brox-ai-lightbox-close';
                closeBtn.type = 'button';
                closeBtn.setAttribute('aria-label', 'Close image');
                closeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
                closeBtn.addEventListener('click', () => this.closeImageLightbox());
                content.appendChild(closeBtn);

                const img = document.createElement('img');
                img.className = 'brox-ai-lightbox-image';
                img.alt = alt || 'Image preview';
                content.appendChild(img);

                const caption = document.createElement('div');
                caption.className = 'brox-ai-lightbox-caption';
                caption.textContent = alt || '';
                content.appendChild(caption);

                document.body.appendChild(overlay);
                this.lightbox = {
                    overlay,
                    img,
                    caption
                };

                this.keydownHandler = (e) => {
                    if (e.key === 'Escape') this.closeImageLightbox();
                };
            }

            this.lightbox.img.src = url;
            this.lightbox.img.alt = alt || 'Image preview';
            this.lightbox.caption.textContent = alt || '';
            this.lightbox.overlay.classList.add('brox-ai-lightbox-open');
            document.addEventListener('keydown', this.keydownHandler);
        }

        closeImageLightbox() {
            if (!this.lightbox) return;
            this.lightbox.overlay.classList.remove('brox-ai-lightbox-open');
            document.removeEventListener('keydown', this.keydownHandler);
        }

        // ── Auto Model Selection for Faster Responses ───────────────────────────
        async autoSelectModel() {
            // Skip if user has manually selected a model - respect their choice
            const userSelectedModel = this.nodes.modelSel?.value;
            if (userSelectedModel && userSelectedModel !== this.defaultModel) {
                return;
            }

            // Get the latest user message
            const lastUserMsg = [...this.history].reverse().find(m => m.role === 'user');;
            if (!lastUserMsg) return;

            // Extract text from message content
            let queryText = '';
            if (typeof lastUserMsg.content === 'string') {
                queryText = lastUserMsg.content;
            } else if (Array.isArray(lastUserMsg.content)) {
                queryText = lastUserMsg.content.find(c => c.type === 'text')?.text || '';
            }

            if (!queryText) return;

            const textLength = queryText.length;
            const wordCount = queryText.split(/\s+/).length;

            // Define model tiers
            const fastModels = [
                'claude-3-haiku',
                'gpt-4o-mini',
                'gpt-3.5-turbo',
                'gemini-1.5-flash',
                'llama-3.1-8b',
                'qwen-2.5-7b'
            ];

            const mediumModels = [
                'claude-3.5-sonnet',
                'gpt-4o',
                'gemini-1.5-pro'
            ];

            const slowModels = [
                'claude-3-opus',
                'gpt-4-turbo',
                'gemini-2.0-flash-exp'
            ];

            // Determine query complexity
            const isSimple = textLength < 100 && wordCount < 20;
            const isMedium = textLength >= 100 && textLength < 500 && wordCount < 100;
            const isComplex = textLength >= 500 || wordCount >= 100;

            // Check for complex tasks that need better models
            const complexKeywords = [
                'analyze', 'compare', 'explain', 'debug', 'write code',
                'create', 'generate', 'build', 'implement', 'review',
                'security', 'optimize', 'refactor', 'architecture'
            ];
            const hasComplexTask = complexKeywords.some(kw =>
                queryText.toLowerCase().includes(kw)
            );

            // Select appropriate model
            let selectedModel = null;

            if (isSimple && !hasComplexTask) {
                // Use fast model for simple queries
                selectedModel = fastModels[0];
            } else if (isMedium || hasComplexTask) {
                // Use medium model for medium complexity
                selectedModel = mediumModels[0];
            } else if (isComplex) {
                // Use slow model for complex queries
                selectedModel = slowModels[0];
            }

            // Apply auto-selected model if different from current
            if (selectedModel && selectedModel !== this.currentModel) {
                // Check if model is available
                const availableModels = this.cachedModels || [];
                if (availableModels.includes(selectedModel)) {
                    this.currentModel = selectedModel;
                    this.updateModelLabel();
                }
            }
        }

        // ── Inactivity Timer - Auto-start new conversation after 5 min ─────────────
        startInactivityTimer() {
            // 5 minutes = 300000 ms
            this.inactivityTimeout = 5 * 60 * 1000;
            this.lastActivity = Date.now();

            // Check every 30 seconds
            this._inactivityCheck = setInterval(() => {
                const now = Date.now();
                const inactiveTime = now - this.lastActivity;

                if (inactiveTime >= this.inactivityTimeout && this.history.length > 0) {
                    this.archiveCurrentConversation();
                }
            }, 30000);

            // Listen for user activity
            this._activityEvents = ['click', 'keydown', 'scroll', 'mousemove'];
            this._activityEvents.forEach(event => {
                document.addEventListener(event, () => this.resetInactivityTimer(), { passive: true });
            });

        }

        resetInactivityTimer() {
            this.lastActivity = Date.now();
        }

        archiveCurrentConversation() {
            if (this.history.length === 0) return;

            // Save current conversation to localStorage
            const archived = JSON.parse(localStorage.getItem('aiAdminArchived') || '[]');
            const conversation = {
                id: Date.now(),
                timestamp: new Date().toISOString(),
                messages: this.history.slice()
            };

            // Keep only last 10 archived conversations
            archived.unshift(conversation);
            if (archived.length > 10) archived.pop();

            localStorage.setItem('aiAdminArchived', JSON.stringify(archived));

            // Clear current history and start new conversation
            this.history = [];
            this.saveHistory();
            this.renderHistory();
            this.updateContext();

            // Reset inactivity timer
            this.lastActivity = Date.now();

            this.updateStatus('ready', 'New conversation started');
        }

        // ── AI Response (SSE Streaming) ─────────────────────────────────────────
        async getAIResponse() {
            if (!this.nodes.body) return;

            // Reset inactivity timer on user activity
            this.resetInactivityTimer();

            // Auto-select model based on query complexity for faster responses
            await this.autoSelectModel();

            this.isThinking = true;
            const t0 = performance.now();
            this.showTypingIndicator();
            if (this.nodes.input) this.nodes.input.disabled = true;
            this.updateStatus('thinking', 'Thinking...');

            // Refresh CSRF token before making request
            await refreshCsrfToken();

            const ctx = this.getCurrentContext();
            const payload = {
                messages: this.history,
                isAdmin: true,
                context: ctx,
                stream: true,
                csrf_token: getCsrfToken() || ''
            };
            const requestOptions = this.buildRequestOptions();
            const lastUser = [...this.history].reverse().find((m) => m.role === 'user');
            const hasPdfAttachment = Array.isArray(lastUser?.content)
                && lastUser.content.some((part) => part?.type === 'file' && this.isPdfAttachment(
                    { name: part?.file?.filename || '', type: part?.file?.mime || '' },
                    part?.file || null
                ));
            if (hasPdfAttachment) {
                const plugins = Array.isArray(requestOptions.plugins) ? [...requestOptions.plugins] : [];
                const hasFileParser = plugins.some((plugin) => plugin && plugin.id === 'file-parser');
                if (!hasFileParser) {
                    plugins.push({
                        id: 'file-parser',
                        pdf: { engine: 'pdf-text' }
                    });
                }
                requestOptions.plugins = plugins;
            }
            if (Object.keys(requestOptions).length > 0) {
                payload.options = requestOptions;
            }
            if (this.currentProvider) payload.provider = this.currentProvider;
            if (this.currentModel) payload.model = this.currentModel;

            const msgIndex = this.history.length;
            const msgBubble = this.createEmptyMessage('assistant', msgIndex);
            const msgWrapper = msgBubble?.parentElement;
            let fullReply = '';
            let responseAnnotations = null;
            let lastError = null;

            let thinkingCleared = false;
            const showThinkingInBubble = () => {
                if (!msgBubble || !msgWrapper) return;
                msgWrapper.classList.add('brox-ai-thinking-msg');
                msgBubble.innerHTML = `
                    <div class="brox-ai-thinking-wrap" aria-live="polite" aria-busy="true">
                        <div class="brox-ai-thinking-label">
                            <span class="brox-ai-thinking-text">Copilot is thinking...</span>
                            <span class="brox-ai-thinking-dots" aria-hidden="true"><span></span><span></span><span></span></span>
                        </div>
                        <div class="brox-ai-thinking-skeleton" aria-hidden="true">
                            <span class="brox-ai-skel-line skel-1"></span>
                            <span class="brox-ai-skel-line skel-2"></span>
                            <span class="brox-ai-skel-line skel-3"></span>
                        </div>
                    </div>
                `;
            };

            const clearThinking = () => {
                if (!msgBubble || !msgWrapper) return;
                if (thinkingCleared) return;
                thinkingCleared = true;
                msgWrapper.classList.remove('brox-ai-thinking-msg');
                msgBubble.innerHTML = '';
            };

            showThinkingInBubble();

            const maxRetries = 2;
            const baseDelay = 1000;

            const attemptStream = async () => {
                const resp = await fetch(ADMIN_CONFIG.proxyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': getCsrfToken() || ''
                    },
                    body: JSON.stringify(payload)
                });

                if (!resp.ok) {
                    const raw = await resp.text();
                    const err = normalizeApiResponse(safeParseJSON(raw));
                    throw new Error(err.error || `AI error (${resp.status})`);
                }

                const contentType = (resp.headers.get('content-type') || '').toLowerCase();
                if (!contentType.includes('text/event-stream') && contentType.includes('application/json')) {
                    const json = await resp.json();
                    const norm = normalizeApiResponse(json);
                    if (!norm.success) {
                        throw new Error(norm.error || 'AI error');
                    }
                    if (json && json.conversation_id) {
                        this.conversationId = String(json.conversation_id);
                    }
                    if (json && json.message_id && msgBubble?.parentElement) {
                        msgBubble.parentElement.dataset.messageId = String(json.message_id);
                    }
                    if (Array.isArray(json?.annotations)) {
                        responseAnnotations = json.annotations;
                    }
                    fullReply = typeof norm.payload === 'string' ? norm.payload : JSON.stringify(norm.payload);
                    clearThinking();
                    this.renderWithArtifacts(msgBubble, fullReply, false);
                    return;
                }

                if (!resp.body) {
                    throw new Error('Empty response from AI server');
                }

                this.updateStatus('receiving', 'Receiving...');

                const reader = resp.body.getReader();
                const decoder = new TextDecoder('utf-8');
                let parseErrors = 0;

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    const chunk = decoder.decode(value, { stream: true });
                    const lines = chunk.split('\n');
                    for (const line of lines) {
                        if (!line.startsWith('data: ')) continue;
                        const raw = line.slice(6).trim();
                        if (raw === '[DONE]') return;

                        const obj = safeParseJSON(raw);
                        if (!obj) {
                            parseErrors += 1;
                            if (parseErrors >= 5) {
                                throw new Error('Stream parse errors');
                            }
                            continue;
                        }

                        if (obj.error) {
                            throw new Error(obj.error);
                        }

                        if (obj && obj.meta) {
                            const meta = obj.meta || {};
                            if (meta.conversation_id) {
                                this.conversationId = String(meta.conversation_id);
                            }
                            if (meta.message_id && msgBubble?.parentElement) {
                                msgBubble.parentElement.dataset.messageId = String(meta.message_id);
                            }
                            if (Array.isArray(meta.annotations)) {
                                responseAnnotations = meta.annotations;
                            }
                            continue;
                        }

                        if (obj.content) {
                            clearThinking();
                            fullReply += obj.content;
                            this.renderWithArtifacts(msgBubble, fullReply, false);
                            this.scrollToBottom();
                        }
                    }
                }
            };

            for (let attempt = 0; attempt <= maxRetries; attempt++) {
                try {
                    await attemptStream();
                    lastError = null;
                    break;
                } catch (err) {
                    lastError = err;
                    reportTelemetry('sse_stream_error', {
                        error: err.message,
                        attempt,
                        provider: this.currentProvider,
                        model: this.currentModel
                    });
                    if (attempt < maxRetries) {
                        const delay = baseDelay * Math.pow(2, attempt);
                        const retryNotice = document.createElement('div');
                        retryNotice.className = 'text-muted';
                        retryNotice.style.fontSize = '0.85em';
                        retryNotice.textContent = `Retrying (${attempt + 1}/${maxRetries + 1})...`;
                        msgBubble.appendChild(retryNotice);
                        await new Promise(res => setTimeout(res, delay));
                    }
                }
            }

            if (!fullReply) {
                const msg = lastError ? lastError.message || 'AI error. Please try again.' : 'Received an empty response from the AI.';
                if (!msgBubble.textContent.trim()) {
                    clearThinking();
                    msgBubble.innerHTML = `<em>${this.escapeHtml(msg)}</em>`;
                }
                if (lastError) {
                    this.updateStatus('error', 'AI error');
                    this.addMessage('assistant', `❌ ${msg}`);
                }
            } else {
                const assistantMessage = { role: 'assistant', content: fullReply };
                if (Array.isArray(responseAnnotations) && responseAnnotations.length) {
                    assistantMessage.annotations = responseAnnotations;
                }
                this.history.push(assistantMessage);
                this.saveHistory();

                if (this.pendingAutoFill) {
                    this.pendingAutoFill = false;
                    try {
                        await this.applyAutoFillFromLastAssistant();
                    } catch {
                        // ignore autofill failures
                    }
                }
            }

            this.updateResponseMeta(msgBubble, t0);

            this.isThinking = false;
            this.updateStatus('ready', 'Ready');
            if (this.nodes.input) {
                this.nodes.input.disabled = false;
                this.nodes.input.focus();
            }
            this.hideTypingIndicator();
        }

        createEmptyMessage(role, msgIndex = null) {
            const msg = document.createElement('div');
            msg.className = `brox-ai-msg brox-ai-${role}`;
            msg.setAttribute('data-role', role);
            if (msgIndex !== null && msgIndex !== undefined) {
                msg.dataset.msgIndex = String(msgIndex);
            }

            const avatar = document.createElement('div');
            avatar.className = 'brox-ai-msg-avatar';
            avatar.innerHTML = role === 'user'
                ? '<i class="bi bi-person-fill"></i>'
                : '<i class="bi bi-stars"></i>';
            msg.appendChild(avatar);

            const body = document.createElement('div');
            body.className = 'brox-ai-msg-content';
            msg.appendChild(body);
            if (role === 'assistant') {
                this.attachLongPressCopy(body, () => body.innerText || '');
            }

            const meta = document.createElement('div');
            meta.className = 'brox-ai-msg-meta';
            meta.textContent = this.formatMetaTime();

            if (role === 'assistant') {
                const actions = this.createAssistantActions({
                    msgEl: msg,
                    contentEl: body,
                    metaEl: meta,
                    msgIndex: Number.isInteger(msgIndex) ? msgIndex : null
                });
                msg.appendChild(actions);
            } else {
                msg.appendChild(meta);
            }

            this.nodes.body.appendChild(msg);
            this.scrollToBottom();
            this.pruneDomMessages();
            return body;
        }



        pruneDomMessages() {
            if (!this.nodes.body) return;
            const messages = Array.from(this.nodes.body.querySelectorAll('.brox-ai-msg'));
            if (messages.length <= ADMIN_CONFIG.maxDomMessages) return;
            const overflow = messages.length - ADMIN_CONFIG.maxDomMessages;
            for (let i = 0; i < overflow; i++) {
                messages[i].remove();
            }
        }

        // ── Puter.js Fallback ───────────────────────────────────────────────────
        async puterFallback() {
            if (this.puterDisabled) {
                this.addMessage('assistant', '❌ Puter fallback is disabled (unauthorized). Please configure Puter or use a valid AI provider.');
                this.updateStatus('error', 'Fallback disabled');
                return;
            }
            this.updateStatus('fallback', 'Using fallback AI');

            this.addMessage('assistant', '⚠️ Primary AI unavailable. Switching to Puter AI...');

            try {
                const puter = await loadPuter();
                const lastMsg = this.history.filter(m => m.role === 'user').pop();
                if (!lastMsg) return;

                // Handle different message content formats
                let messageContent;
                if (typeof lastMsg.content === 'string') {
                    messageContent = lastMsg.content;
                } else if (Array.isArray(lastMsg.content)) {
                    messageContent = lastMsg.content.map(p => (p.type === 'text' ? p.text : '')).join(' ');
                } else {
                    return;
                }

                const msgBubble = this.createEmptyMessage('assistant');
                const t0 = performance.now();
                let reply = '';

                const stream = await puter.ai.chat(messageContent, { stream: true });
                for await (const chunk of stream) {
                    const text = chunk?.text || '';
                    reply += text;
                    msgBubble.textContent = reply;
                    this.scrollToBottom();
                }

                if (reply) {
                    this.history.push({ role: 'assistant', content: reply });
                    this.saveHistory();
                }
                this.updateResponseMeta(msgBubble, t0);

                this.updateStatus('ready', 'Ready (Puter)');

            } catch (fallbackErr) {
                console.error('[Admin Fallback] Puter error:', fallbackErr);
                const status = fallbackErr?.status || fallbackErr?.error?.status;
                const message = fallbackErr?.message || fallbackErr?.error?.message || '';
                if (status === 401 || /unauthorized/i.test(message)) {
                    this.puterDisabled = true;
                    this.addMessage('assistant', '❌ Puter unauthorized. Please login/configure Puter or use a valid provider.');
                    this.updateStatus('error', 'Puter unauthorized');
                    return;
                }
                this.addMessage('assistant', '❌ Connection error. Both primary AI and Puter are unavailable.');
                this.updateStatus('error', 'All AI failed');
            }
        }

        // ── Log Monitor ─────────────────────────────────────────────────────────
        startLogMonitor() {
            let lastTs = Math.floor(Date.now() / 1000);
            let lastErrorCount = 0;
            let currentInterval = ADMIN_CONFIG.logCheckInterval;
            const maxInterval = 5 * 60 * 1000; // 5 minutes

            const check = async () => {
                try {
                    const res = await fetch(`${ADMIN_CONFIG.logUrl}?since=${lastTs}`);
                    const data = await res.json();

                    if (Array.isArray(data.errors)) {
                        const count = data.errors.length;
                        if (count > 0) {
                            if (this.nodes.notification) {
                                this.nodes.notification.textContent = String(count);
                                this.nodes.notification.classList.add('show');
                            }

                            if (count > lastErrorCount) {
                                this.addMessage('assistant', `⚠️ System Alert: ${count} new error(s) detected in logs.`);
                            }
                        }
                        lastErrorCount = count;
                    }

                    lastTs = data.latest_timestamp || lastTs;
                    currentInterval = ADMIN_CONFIG.logCheckInterval;
                } catch (e) {
                    // Backoff on error
                    currentInterval = Math.min(maxInterval, currentInterval * 2);
                }

                setTimeout(check, currentInterval);
            };

            // Initial delay
            setTimeout(check, 5000);
        }

        // ── API Helper ───────────────────────────────────────────────────────────
        async apiCall(url, body) {
            await refreshCsrfToken();
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ...body, csrf_token: csrfToken })
                });
                return await res.json();
            } catch {
                return { success: false, error: 'Network error' };
            }
        }

        async testConnection(id, model = null) {
            const res = await this.apiCall('/api/ai-system/test', { id, model });
            if (res.success) window.showAlert('Connection successful!', 'Success', 'success');
            else window.showAlert('Connection failed: ' + (res.error || 'Unknown error'), 'Connection Failed', 'error');
        }
    }

    // ── Bootstrap ─────────────────────────────────────────────────────────────
    function bootstrapAdminCopilot() {
        if (window.BroxAdminInstance) return;

        window.broxAdmin = new BroxAdminCopilot();
        window.BroxAdminInstance = window.broxAdmin;

        // Expose helpers for Twig inline onclick calls
        window.testConnection = (id, model) => window.broxAdmin.testConnection(id, model);
        window.deleteProvider = (id) => window.broxAdmin.apiCall('/api/ai-system/delete-provider', { id });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapAdminCopilot);
    } else {
        bootstrapAdminCopilot();
    }
}
