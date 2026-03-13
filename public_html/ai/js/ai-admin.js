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
        return {
            success: Boolean(data.success),
            error: data.error || data.message || null,
            error_code: data.error_code || data.code || null,
            payload: data.data ?? data,
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
            if (!this.input) return;

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
            this.preview.classList.remove('brox-ai-hidden');
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
            this.preview?.classList.add('d-none');
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
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken || '');

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
                console.log('[Admin Puter] SDK loaded');
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
            this.isThinking = false;
            this.csrfToken = csrfToken;
            this.currentModel = null;
            this.currentProvider = 'openrouter';
            this.preferredModel = '';
            this.puterDisabled = false;
            this.fileHandler = null;
            this.modelBarOpen = false;
            this._providersBootstrapped = false;
            this._providersBootstrapPromise = null;
            this._bgModelsRefresh = null;
            this._bgModelsRefreshToken = 0;

            this.initUI();
            this.bindEvents();
            this.startLogMonitor();
            this.renderHistory();
            this.updateContext();

            console.log('[Admin Copilot] Initialized with', this.history.length, 'messages');
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
                const stored = sessionStorage.getItem(ADMIN_CONFIG.chatKey);
                const parsed = stored ? JSON.parse(stored) : [];
                // Ensure we respect the max history limit
                return parsed.slice(-ADMIN_CONFIG.maxHistory);
            } catch (e) {
                console.warn('[Admin Copilot] Failed to load history:', e);
                return [];
            }
        }

        saveHistory() {
            try {
                // Keep only the last N messages
                const trimmed = this.history.slice(-ADMIN_CONFIG.maxHistory);
                sessionStorage.setItem(ADMIN_CONFIG.chatKey, JSON.stringify(trimmed));
                this.updateSaveIndicator();
            } catch (e) {
                console.warn('[Admin Copilot] Failed to save history:', e);
            }
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
                close: document.getElementById('adminAiClose'),
                slashMenu: document.getElementById('adminAiSlashMenu'),
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
                historyToggle: document.getElementById('adminAiHistoryToggle'),
                historySidebarClose: document.getElementById('adminAiSidebarClose')
            };

            // Initialize file handler
            this.fileHandler = new FileAttachmentHandler();
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
            if (this.preferredModel) {
                this.currentModel = this.preferredModel;
                this.updateModelLabel();
            }

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
            console.log('[Admin Models] Loaded', models.length, 'for', provider);

            // Track selection changes (idempotent)
            this._modelChangeHandler = () => {
                const newModel = this.nodes.modelSel.value;
                if (newModel && newModel !== this.currentModel) {
                    this.currentModel = newModel;
                    this.updateModelLabel();
                    console.log('[Admin Models] Selected:', this.currentModel);
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
            console.log('[Admin Models] Using fallback models');

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
            if (!this.nodes.modelBadge && !this.nodes.modelLabel) return;
            const modelId = this.currentModel || '';
            const rawLabel = this.nodes.modelSel?.options[this.nodes.modelSel.selectedIndex]?.text || modelId || 'AI';
            const label = this.getShortModelLabel(modelId, rawLabel);
            if (this.nodes.modelBadge) {
                this.nodes.modelBadge.textContent = label;
            }
            if (this.nodes.modelLabel) {
                this.nodes.modelLabel.textContent = label;
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
                this.nodes.attach.onclick = () => {
                    this.fileHandler?.input?.click();
                };
            }

            // Clear chat
            if (this.nodes.clear) {
                this.nodes.clear.onclick = () => this.clearChat();
            }

            // Collect page form data
            if (this.nodes.collectDataBtn) {
                this.nodes.collectDataBtn.onclick = () => this.handleCollectData();
            }

            // Auto-fill form from assistant output
            if (this.nodes.autoFillBtn) {
                this.nodes.autoFillBtn.onclick = () => this.handleAutoFill();
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
                if (!this.nodes.shell || !this.nodes.btn) return;
                if (this.nodes.shell.classList.contains('brox-ai-hidden')) return;
                const path = e.composedPath ? e.composedPath() : [];
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
                this.nodes.shell.classList.remove('d-none');
                this.nodes.shell.classList.remove('brox-ai-hidden');
                this.ensureProvidersBootstrapped();
                setTimeout(() => {
                    this.nodes.input?.focus();
                }, 10);
                // Toggle button icon: show close icon
                this.nodes.btn?.classList.add('brox-ai-active');
            } else {
                this.nodes.shell.classList.add('brox-ai-hidden');
                setTimeout(() => this.nodes.shell.classList.add('d-none'), 300);
                // Toggle button icon: show open icon
                this.nodes.btn?.classList.remove('brox-ai-active');
            }
        }

        minimizeSidebar() {
            if (!this.nodes.shell) return;
            this.nodes.shell.classList.add('brox-ai-hidden');
            // Toggle button icon: show open icon
            this.nodes.btn?.classList.remove('brox-ai-active');
        }

        closeSidebar() {
            if (!this.nodes.shell) return;
            this.nodes.shell.classList.add('brox-ai-hidden');
            setTimeout(() => this.nodes.shell.classList.add('d-none'), 300);
            // Toggle button icon: show open icon
            this.nodes.btn?.classList.remove('brox-ai-active');
        }

        // ── Chat Management ─────────────────────────────────────────────────────
        clearChat() {
            if (!confirm('Clear all chat history?')) return;

            this.history = [];
            sessionStorage.removeItem(ADMIN_CONFIG.chatKey);

            if (this.nodes.body) {
                this.nodes.body.innerHTML = '';
                this.nodes.welcome?.classList.remove('d-none');
            }

            // Clear any stored image context on the server for this user session
            fetch('/api/ai/clear-image-context', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({})
            }).catch(() => {
                // non-critical
            });

            console.log('[Admin Copilot] Chat cleared');
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
                const empty = document.createElement('div');
                empty.className = 'text-center p-4 text-muted small';
                empty.textContent = 'No history yet';
                this.nodes.historyList.appendChild(empty);
                return;
            }

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
                const cmdMatch = sanitized.match(/^\/(collect-data|autofill)\b/i);
                if (cmdMatch) {
                    const cmd = cmdMatch[1].toLowerCase();
                    this.updateStatus('loading', 'Running command...');
                    if (cmd === 'collect-data') {
                        await this.handleCollectData();
                    } else if (cmd === 'autofill') {
                        await this.handleAutoFill();
                    }
                    this.updateStatus('ready', 'Ready');
                    return;
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
                const note = `Attachment: ${attachment.file.name} (not supported for AI analysis)`;
                messageContent = sanitized ? `${sanitized}\n\n${note}` : note;
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

        addMessage(role, content, animate = true, msgIndex = null) {
            if (!this.nodes.body) return;

            // Remove welcome message if exists
            this.nodes.welcome?.classList.add('d-none');

            const msg = document.createElement('div');
            msg.className = `brox-ai-msg brox-ai-${role}`;
            msg.setAttribute('data-role', role);

            // Avatar
            const avatar = document.createElement('div');
            avatar.className = 'brox-ai-msg-avatar';
            avatar.innerHTML = role === 'user'
                ? '<i class="bi bi-person-fill"></i>'
                : '<i class="bi bi-stars"></i>';
            msg.appendChild(avatar);

            // Content
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

                        const meta = document.createElement('div');
                        meta.className = 'brox-ai-msg-image-meta';
                        const parts = [];
                        if (part.image_url.name) parts.push(part.image_url.name);
                        if (part.image_url.size) parts.push(this.formatFileSize(part.image_url.size));
                        if (part.image_url.mime) parts.push(part.image_url.mime);
                        if (parts.length) {
                            meta.textContent = parts.join(' • ');
                            imgWrap.appendChild(meta);
                        }

                        contentDiv.appendChild(imgWrap);
                    }
                    if (idx < content.length - 1) {
                        contentDiv.appendChild(document.createElement('br'));
                    }
                });
            } else if (typeof content === 'string' && content.includes('```artifact')) {
                this.renderWithArtifacts(contentDiv, content, animate && role === 'assistant');
            } else if (animate && role === 'assistant' && typeof content === 'string') {
                this.typeEffect(contentDiv, content);
            } else if (typeof content === 'string') {
                contentDiv.innerHTML = this.formatMessage(content);
            }

            msg.appendChild(contentDiv);

            // Meta
            const meta = document.createElement('div');
            meta.className = 'brox-ai-msg-meta';
            meta.textContent = new Date().toLocaleTimeString();
            msg.appendChild(meta);

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

        formatMessage(text) {
            // Basic markdown-like formatting (after escaping HTML)
            const safe = this.escapeHtml(text);
            return safe
                .replace(/```(\w+)?\n([\s\S]*?)```/g, '<pre><code>$2</code></pre>')
                .replace(/`([^`]+)`/g, '<code>$1</code>')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/\n/g, '<br>');
        }

        renderWithArtifacts(container, content, animate) {
            const parts = content.split(/```artifact([\s\S]*?)```/);
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
            if (!text || typeof text !== 'string') return null;

            // Prefer explicit code fences
            const fenceMatch = text.match(/```(?:json)?\s*([\s\S]*?)```/i);
            if (fenceMatch && fenceMatch[1]) {
                try {
                    return JSON.parse(fenceMatch[1].trim());
                } catch {
                    // fallthrough
                }
            }

            // Fallback: try to parse first JSON object found
            const braceMatch = text.match(/\{[\s\S]*\}/);
            if (braceMatch) {
                try {
                    return JSON.parse(braceMatch[0]);
                } catch {
                    // ignore
                }
            }

            return null;
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

        async handleCollectData() {
            const url = this.extractUrlFromText(this.nodes.input?.value || '');
            const attachment = this.fileHandler?.getAttachment();
            const hasAttachment = !!attachment?.file;

            // If user provided a URL or an attachment, ask the AI to scan it for form values
            if (hasAttachment || url) {
                const intro = 'Please analyze the following input and return a JSON object mapping form field names to values. Only return valid JSON.';
                const promptParts = [intro];
                if (url) {
                    promptParts.push(`Source URL: ${url}`);
                }
                const messageContent = [{ type: 'text', text: promptParts.join('\n\n') }];

                if (hasAttachment && attachment.uploaded?.url) {
                    messageContent.push({
                        type: 'image_url',
                        image_url: {
                            url: attachment.uploaded.url,
                            name: attachment.uploaded.name || attachment.file.name,
                            mime: attachment.uploaded.mime || attachment.file.type,
                            size: attachment.uploaded.size || attachment.file.size
                        }
                    });
                }

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
            const raw = this.getLastAssistantContent();
            const payload = this.extractJsonFromText(typeof raw === 'string' ? raw : (Array.isArray(raw) ? JSON.stringify(raw) : ''));
            if (!payload || typeof payload !== 'object') {
                this.addMessage('assistant', 'Could not find structured JSON in the last assistant response to auto-fill the form.');
                return;
            }

            const form = this.findPrimaryForm();
            if (!form) {
                this.addMessage('assistant', 'No visible form found on this page to auto-fill.');
                return;
            }

            const keys = Object.keys(payload);
            let filled = 0;
            let missing = [];

            keys.forEach((key) => {
                const selector = `input[name="${key}"], textarea[name="${key}"], select[name="${key}"], input[id="${key}"], textarea[id="${key}"], select[id="${key}"]`;
                const elt = form.querySelector(selector);
                if (!elt) {
                    missing.push(key);
                    return;
                }

                const val = payload[key];
                if (elt.tagName === 'INPUT') {
                    const type = (elt.getAttribute('type') || '').toLowerCase();
                    if (type === 'checkbox') {
                        elt.checked = Boolean(val);
                        filled++;
                        return;
                    }
                    if (type === 'radio') {
                        const group = form.querySelectorAll(`input[type="radio"][name="${key}"]`);
                        let matched = false;
                        group.forEach((radio) => {
                            if (String(radio.value) === String(val)) {
                                radio.checked = true;
                                matched = true;
                            }
                        });
                        if (matched) filled++;
                        else missing.push(key);
                        return;
                    }
                }

                elt.value = String(val);
                filled++;
            });

            let summary = `Auto-fill completed: ${filled} field(s) updated.`;
            if (missing.length) {
                summary += ` Missing fields: ${missing.join(', ')}.`;
            }
            this.addMessage('assistant', summary);
            this.history.push({ role: 'assistant', content: summary });
            this.saveHistory();
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

        // ── AI Response (SSE Streaming) ─────────────────────────────────────────
        async getAIResponse() {
            if (!this.nodes.body) return;

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
                stream: true
            };
            if (this.currentProvider) payload.provider = this.currentProvider;
            if (this.currentModel) payload.model = this.currentModel;

            const msgIndex = this.history.length;
            const msgBubble = this.createEmptyMessage('assistant', msgIndex);
            let fullReply = '';
            let lastError = null;

            const maxRetries = 2;
            const baseDelay = 1000;

            const attemptStream = async () => {
                const resp = await fetch(ADMIN_CONFIG.proxyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
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
                    fullReply = typeof norm.payload === 'string' ? norm.payload : JSON.stringify(norm.payload);
                    msgBubble.innerHTML = '';
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

                        if (obj.content) {
                            fullReply += obj.content;
                            msgBubble.innerHTML = '';
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
                    msgBubble.innerHTML = `<em>${this.escapeHtml(msg)}</em>`;
                }
                if (lastError) {
                    this.updateStatus('error', 'AI error');
                    this.addMessage('assistant', `❌ ${msg}`);
                }
            } else {
                this.history.push({ role: 'assistant', content: fullReply });
                this.saveHistory();
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

            const meta = document.createElement('div');
            meta.className = 'brox-ai-msg-meta';
            meta.textContent = this.formatMetaTime();
            msg.appendChild(meta);

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
            console.log('[Fallback] Using Puter AI (Admin)');
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

        console.log('[Admin Copilot] Ready');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapAdminCopilot);
    } else {
        bootstrapAdminCopilot();
    }
}
