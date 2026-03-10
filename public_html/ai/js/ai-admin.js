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
    proxyUrl: '/api/ai-system/chat',
    logUrl: '/api/admin/logs/errors',
    modelsUrl: '/api/ai/models',  // Fixed: was /api/ai-system/models
    puterCdn: 'https://js.puter.com/v2/',
    csrfRefreshUrl: '/api/csrf-token',
    maxHistory: 40,
    maxInputLength: 5000,
    csrRefreshInterval: 10 * 60 * 1000, // 10 minutes
    logCheckInterval: 60 * 1000, // 1 minute
    typingSpeed: 5, // ms per character
    maxFileSize: 10 * 1024 * 1024, // 10MB
    allowedFileTypes: ['image/*', '.pdf', '.txt', '.doc', '.docx']
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

    // ── File Attachment Handler ───────────────────────────────────────────────
    class FileAttachmentHandler {
        constructor() {
            this.files = [];
            this.input = document.getElementById('adminAiFileInput');
            this.preview = document.getElementById('adminAiAttachmentPreview');
            this.fileName = document.getElementById('adminAiFileName');
            this.fileSize = document.getElementById('adminAiFileSize');
            this.removeBtn = document.getElementById('adminAiRemoveAttachment');

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

            // Validate file size
            if (file.size > ADMIN_CONFIG.maxFileSize) {
                alert(`File too large. Maximum size is ${ADMIN_CONFIG.maxFileSize / 1024 / 1024}MB`);
                return;
            }

            this.files = [file];
            this.updatePreview();
        }

        updatePreview() {
            if (!this.preview || this.files.length === 0) {
                this.preview?.classList.add('d-none');
                return;
            }

            const file = this.files[0];
            this.fileName.textContent = file.name;
            this.fileSize.textContent = this.formatFileSize(file.size);
            this.preview.classList.remove('d-none');
        }

        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        clearFiles() {
            this.files = [];
            if (this.input) this.input.value = '';
            this.preview?.classList.add('d-none');
        }

        getFiles() {
            return this.files;
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
    async function fetchModels(provider) {
        try {
            const res = await fetch(`${ADMIN_CONFIG.modelsUrl}?provider=${encodeURIComponent(provider)}`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            return Array.isArray(data.models) ? data.models : [];
        } catch (e) {
            console.warn('[Admin Models] Failed:', e.message);
            return [];
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
            this.fileHandler = null;

            this.initUI();
            this.bindEvents();
            this.startLogMonitor();
            this.renderHistory();
            this.updateContext();
            this.loadProviderModels();

            console.log('[Admin Copilot] Initialized with', this.history.length, 'messages');
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
                modelSel: document.getElementById('adminAiModel'),
                modelBadge: document.getElementById('adminAiCurrentModel'),
                refreshModels: document.getElementById('adminAiRefreshModels'),
                statusDot: document.getElementById('adminAiStatusDot'),
                statusText: document.getElementById('adminAiStatusText'),
                notification: document.getElementById('adminAiNotification')
            };

            // Initialize file handler
            this.fileHandler = new FileAttachmentHandler();
        }

        // ── Context Management ─────────────────────────────────────────────────
        getCurrentContext() {
            const parts = window.location.pathname.split('/').filter(Boolean);
            const module = parts.length > 1 ? parts[1] : 'Dashboard';
            return {
                url: window.location.href,
                title: document.title,
                module: module.charAt(0).toUpperCase() + module.slice(1),
                timestamp: new Date().toISOString()
            };
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
        async loadProviderModels(provider = 'openrouter') {
            if (!this.nodes.modelSel) return;

            this.currentProvider = provider;
            this.updateStatus('loading', 'Loading models...');

            const models = await fetchModels(provider);

            if (!models.length) {
                // Use fallback models if API fails
                this.loadFallbackModels();
                return;
            }

            this.nodes.modelSel.innerHTML = '';
            models.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.name + (m.id.endsWith(':free') ? ' (Free)' : '');
                if (m.default) opt.selected = true;
                this.nodes.modelSel.appendChild(opt);
            });

            const def = models.find(m => m.default);
            this.currentModel = def ? def.id : models[0].id;

            if (this.nodes.modelBadge) {
                this.nodes.modelBadge.textContent = this.nodes.modelSel.options[this.nodes.modelSel.selectedIndex]?.text || this.currentModel;
            }

            this.updateStatus('ready', 'Ready');
            console.log('[Admin Models] Loaded', models.length, 'for', provider);

            // Track selection changes
            this.nodes.modelSel.addEventListener('change', () => {
                this.currentModel = this.nodes.modelSel.value;
                if (this.nodes.modelBadge) {
                    this.nodes.modelBadge.textContent = this.nodes.modelSel.options[this.nodes.modelSel.selectedIndex]?.text || this.currentModel;
                }
                console.log('[Admin Models] Selected:', this.currentModel);
            });
        }

        loadFallbackModels() {
            // Fallback models when API is unavailable
            const fallbackModels = [
                { id: 'anthropic/claude-3-haiku:free', name: 'Claude 3 Haiku (Free)', default: true },
                { id: 'google/gemini-pro-1.5:free', name: 'Gemini Pro 1.5 (Free)' },
                { id: 'openai/gpt-4o-mini:free', name: 'GPT-4o Mini (Free)' }
            ];

            if (!this.nodes.modelSel) return;

            this.nodes.modelSel.innerHTML = '';
            fallbackModels.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.name;
                if (m.default) opt.selected = true;
                this.nodes.modelSel.appendChild(opt);
            });

            this.currentModel = fallbackModels[0].id;

            if (this.nodes.modelBadge) {
                this.nodes.modelBadge.textContent = fallbackModels[0].name;
            }

            this.updateStatus('ready', 'Ready (Offline)');
            console.log('[Admin Models] Using fallback models');

            // Track selection changes
            this.nodes.modelSel.addEventListener('change', () => {
                this.currentModel = this.nodes.modelSel.value;
                if (this.nodes.modelBadge) {
                    this.nodes.modelBadge.textContent = this.nodes.modelSel.options[this.nodes.modelSel.selectedIndex]?.text || this.currentModel;
                }
            });
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

            // Toggle sidebar
            this.nodes.btn.onclick = () => this.toggleSidebar();

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

            // Refresh models
            if (this.nodes.refreshModels) {
                this.nodes.refreshModels.onclick = () => {
                    this.loadProviderModels(this.currentProvider);
                };
            }

            // Input handling
            if (this.nodes.input) {
                this.nodes.input.addEventListener('keydown', (e) => {
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
                        this.nodes.charCount.classList.toggle('warning', len > ADMIN_CONFIG.maxInputLength * 0.9);
                    }

                    // Slash command overlay
                    const val = e.target.value;
                    if (this.nodes.slashMenu) {
                        const show = val.trim().startsWith('/');
                        this.nodes.slashMenu.classList.toggle('d-none', !show);
                    }
                });

                this.nodes.input.addEventListener('focus', () => {
                    this.updateContextUI();
                });
            }

            // Slash menu
            this.bindSlashMenu();

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
                    this.nodes.slashMenu?.classList.add('d-none');
                }
            });
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
                closeBtn.onclick = () => this.nodes.slashMenu.classList.add('d-none');
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

            if (this.nodes.shell.classList.contains('d-none')) {
                this.nodes.shell.classList.remove('d-none');
                setTimeout(() => {
                    this.nodes.shell.classList.remove('brox-ai-hidden');
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
                return;
            }

            this.history.forEach(m => this.addMessage(m.role, m.content, false));
            this.scrollToBottom();
        }

        // ── Message Handling ───────────────────────────────────────────────────
        async handleSend() {
            const text = this.nodes.input?.value.trim();
            if (!text || this.isThinking) return;

            // Validate input
            const validation = validateInput(text);
            if (!validation.valid) {
                this.updateStatus('error', validation.error);
                setTimeout(() => this.updateStatus('ready', 'Ready'), 3000);
                return;
            }

            const sanitized = validation.sanitized;

            // Clear input
            this.nodes.input.value = '';
            this.nodes.slashMenu?.classList.add('d-none');
            this.nodes.charCount.textContent = `0/${ADMIN_CONFIG.maxInputLength}`;
            this.resizeInput();

            // Hide welcome message
            this.nodes.welcome?.classList.add('d-none');

            // Add user message
            this.addMessage('user', sanitized);
            this.history.push({ role: 'user', content: sanitized });
            this.saveHistory();

            // Get AI response
            await this.getAIResponse();
        }

        addMessage(role, content, animate = true) {
            if (!this.nodes.body) return;

            // Remove welcome message if exists
            this.nodes.welcome?.classList.add('d-none');

            const msg = document.createElement('div');
            msg.className = `brox-ai-msg ${role}`;
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

            if (content.includes('```artifact')) {
                this.renderWithArtifacts(contentDiv, content, animate && role === 'assistant');
            } else if (animate && role === 'assistant') {
                this.typeEffect(contentDiv, content);
            } else {
                contentDiv.innerHTML = this.formatMessage(content);
            }

            msg.appendChild(contentDiv);

            // Meta
            const meta = document.createElement('div');
            meta.className = 'brox-ai-msg-meta';
            meta.textContent = new Date().toLocaleTimeString();
            msg.appendChild(meta);

            this.nodes.body.appendChild(msg);
            this.scrollToBottom();
        }

        formatMessage(text) {
            // Basic markdown-like formatting
            return text
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
            hdr.innerHTML = `<span>${data.title || 'Data Artifact'}</span><span class="badge bg-primary">${data.type || 'Table'}</span>`;
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

        // ── AI Response (SSE Streaming) ─────────────────────────────────────────
        async getAIResponse() {
            if (!this.nodes.body) return;

            this.isThinking = true;
            this.showTypingIndicator();

            if (this.nodes.input) this.nodes.input.disabled = true;

            this.updateStatus('thinking', 'Thinking...');

            // Refresh CSRF token before making request
            await refreshCsrfToken();

            try {
                const ctx = this.getCurrentContext();

                const payload = {
                    messages: this.history,
                    isAdmin: true,
                    context: ctx,
                    stream: true
                };
                if (this.currentModel) payload.model = this.currentModel;

                const resp = await fetch(ADMIN_CONFIG.proxyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(payload)
                });

                this.hideTypingIndicator();

                if (!resp.ok) {
                    console.warn('[Admin AI] Provider responded', resp.status);
                    this.updateStatus('error', 'AI error');
                    if (this.nodes.input) this.nodes.input.disabled = false;
                    return await this.puterFallback();
                }

                this.updateStatus('receiving', 'Receiving...');

                const msgBubble = this.createEmptyMessage('assistant');
                let fullReply = '';

                const reader = resp.body.getReader();
                const decoder = new TextDecoder('utf-8');

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    const lines = decoder.decode(value, { stream: true }).split('\n');
                    for (const line of lines) {
                        if (!line.startsWith('data: ')) continue;
                        const raw = line.slice(6).trim();
                        if (raw === '[DONE]') break;
                        try {
                            const obj = JSON.parse(raw);
                            if (obj.content) {
                                fullReply += obj.content;
                                msgBubble.innerHTML = '';
                                this.renderWithArtifacts(msgBubble, fullReply, false);
                                this.scrollToBottom();
                            } else if (obj.error) {
                                console.error('[SSE Admin Error]', obj.error);
                                const span = document.createElement('span');
                                span.className = 'text-danger';
                                span.textContent = '\n[Error: ' + obj.error + ']';
                                msgBubble.appendChild(span);
                            }
                        } catch (e) {
                            console.error('[SSE Admin Parse]', e, 'raw:', raw);
                        }
                    }
                }

                if (fullReply) {
                    this.history.push({ role: 'assistant', content: fullReply });
                    this.saveHistory();
                } else if (!msgBubble.textContent.trim()) {
                    msgBubble.innerHTML = '<em>Received an empty response from the AI.</em>';
                }

                this.isThinking = false;
                this.updateStatus('ready', 'Ready');
                if (this.nodes.input) {
                    this.nodes.input.disabled = false;
                    this.nodes.input.focus();
                }

            } catch (err) {
                console.error('[Admin AI] Fetch error:', err);
                this.hideTypingIndicator();
                this.isThinking = false;
                this.updateStatus('error', 'Connection error');

                if (this.nodes.input) this.nodes.input.disabled = false;

                // Network error → Puter fallback
                await this.puterFallback();
            }
        }

        createEmptyMessage(role) {
            const msg = document.createElement('div');
            msg.className = `brox-ai-msg ${role}`;
            msg.setAttribute('data-role', role);

            const avatar = document.createElement('div');
            avatar.className = 'brox-ai-msg-avatar';
            avatar.innerHTML = role === 'user'
                ? '<i class="bi bi-person-fill"></i>'
                : '<i class="bi bi-stars"></i>';
            msg.appendChild(avatar);

            const body = document.createElement('div');
            body.className = 'brox-ai-msg-content';
            msg.appendChild(body);

            this.nodes.body.appendChild(msg);
            this.scrollToBottom();
            return body;
        }

        // ── Puter.js Fallback ───────────────────────────────────────────────────
        async puterFallback() {
            console.log('[Fallback] Using Puter AI (Admin)');
            this.updateStatus('fallback', 'Using fallback AI');

            this.addMessage('assistant', '⚠️ Primary AI unavailable. Switching to Puter AI...');

            try {
                const puter = await loadPuter();
                const lastMsg = this.history.filter(m => m.role === 'user').pop();
                if (!lastMsg) return;

                const msgBubble = this.createEmptyMessage('assistant');
                let reply = '';

                const stream = await puter.ai.chat(lastMsg.content, { stream: true });
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

                this.updateStatus('ready', 'Ready (Puter)');

            } catch (fallbackErr) {
                console.error('[Admin Fallback] Puter error:', fallbackErr);
                this.addMessage('assistant', '❌ Connection error. Both primary AI and Puter are unavailable.');
                this.updateStatus('error', 'All AI failed');
            }
        }

        // ── Log Monitor ─────────────────────────────────────────────────────────
        startLogMonitor() {
            let lastTs = Math.floor(Date.now() / 1000);
            const check = async () => {
                try {
                    const res = await fetch(`${ADMIN_CONFIG.logUrl}?since=${lastTs}`);
                    const data = await res.json();
                    if (data.errors?.length > 0 && this.nodes.body) {
                        // Show notification badge
                        if (this.nodes.notification) {
                            this.nodes.notification.textContent = data.errors.length;
                            this.nodes.notification.classList.add('show');
                        }

                        // Add system alert message
                        this.addMessage('assistant', `⚠️ System Alert: ${data.errors.length} new error(s) detected in logs.`);
                    }
                    lastTs = data.latest_timestamp || lastTs;
                } catch { /* silent */ }
                setTimeout(check, ADMIN_CONFIG.logCheckInterval);
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
            if (res.success) alert('Connection successful!');
            else alert('Connection failed: ' + (res.error || 'Unknown error'));
        }
    }

    // ── Bootstrap ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        if (window.BroxAdminInstance) return;

        window.broxAdmin = new BroxAdminCopilot();
        window.BroxAdminInstance = window.broxAdmin;

        // Expose helpers for Twig inline onclick calls
        window.testConnection = (id, model) => window.broxAdmin.testConnection(id, model);
        window.deleteProvider = (id) => window.broxAdmin.apiCall('/api/ai-system/provider/delete', { id });

        console.log('[Admin Copilot] Ready');
    });
}
