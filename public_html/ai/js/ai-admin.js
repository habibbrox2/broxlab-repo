/**
 * BroxBhai AI SYSTEM - Admin Panel Copilot (2026 Standard)
 * Path: /public_html/ai/js/ai-admin.js
 *
 * Features:
 *  - 100% Vanilla JS — no jQuery dependency
 *  - Persistent right-aligned sidebar (ai-copilot-sidebar)
 *  - SSE Streaming with reasoning animation
 *  - Puter.js client-side fallback (loaded lazily from CDN on provider failure)
 *  - Remote model list loading per provider (OpenRouter :free variants supported)
 *  - Model pre-selection based on provider default
 *  - Slash command overlay menu
 *  - Dynamic context header (window.location / document.title)
 */

// ── Auto-inject ai-style.css (no <link> tag needed in HTML) ──────────────────
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

if (!window.BroxAdminInstance) {

    const ADMIN_CONFIG = {
        chatKey: 'brox.admin.history',
        proxyUrl: '/api/ai-system/chat',
        logUrl: '/api/admin/logs/errors',
        modelsUrl: '/api/ai-system/models',      // GET ?provider=name
        puterCdn: 'https://js.puter.com/v2/'    // Puter.js CDN (fallback only)
    };

    // ── Puter.js Loader (lazy, CDN) ───────────────────────────────────────────
    function loadPuter() {
        return new Promise((resolve, reject) => {
            if (window.puter) return resolve(window.puter);
            const s = document.createElement('script');
            s.src = ADMIN_CONFIG.puterCdn;
            s.async = true;
            s.onload = () => { console.log('[Admin Puter] SDK loaded'); resolve(window.puter); };
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

    // ── Main Class ────────────────────────────────────────────────────────────
    class BroxAdmin {
        constructor() {
            this.history = JSON.parse(sessionStorage.getItem(ADMIN_CONFIG.chatKey)) || [];
            this.isThinking = false;
            this.csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            this.currentModel = null;

            this.initUI();
            this.bindEvents();
            this.startLogMonitor();
            this.renderHistory();
            this.updateContext();
            this.loadProviderModels();  // load default (openrouter) model list on boot
        }

        initUI() {
            this.nodes = {
                btn: document.getElementById('adminAiBtn'),
                shell: document.getElementById('adminAiShell'),
                title: document.getElementById('adminAiTitle'),
                contextBadge: document.getElementById('adminAiContextBadge'),
                body: document.getElementById('adminAiBody'),
                input: document.getElementById('adminAiInput'),
                send: document.getElementById('adminAiSend'),
                close: document.getElementById('adminAiClose'),
                slashMenu: document.getElementById('adminAiSlashMenu'),
                modelSel: document.getElementById('adminAiModel')   // model dropdown
            };

            if (this.nodes.btn) {
                this.updateContextUI();
            }
        }

        // ── Context Header ────────────────────────────────────────────────────
        updateContextUI() {
            if (!this.nodes.contextBadge) return;
            const ctx = this.getCurrentContext();
            this.nodes.contextBadge.innerHTML =
                `Context: <span class="badge bg-secondary">${ctx.module}</span>`;
        }

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

        updateContext() {
            // alias kept for backward-compat calls
            this.updateContextUI();
        }

        // ── Remote Model Loading ──────────────────────────────────────────────
        async loadProviderModels(provider = 'openrouter') {
            if (!this.nodes.modelSel) return;

            const models = await fetchModels(provider);
            if (!models.length) {
                this.nodes.modelSel.classList.add('d-none');
                return;
            }

            this.nodes.modelSel.innerHTML = '';
            models.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                // Highlight OpenRouter :free models
                opt.textContent = m.name + (m.id.endsWith(':free') ? ' (Free)' : '');
                if (m.default) opt.selected = true;
                this.nodes.modelSel.appendChild(opt);
            });

            const def = models.find(m => m.default);
            this.currentModel = def ? def.id : models[0].id;
            this.nodes.modelSel.classList.remove('d-none');
            console.log('[Admin Models] Loaded', models.length, 'for', provider, '— default:', this.currentModel);

            // Track selection changes
            this.nodes.modelSel.addEventListener('change', () => {
                this.currentModel = this.nodes.modelSel.value;
                console.log('[Admin Models] Selected:', this.currentModel);
            });
        }

        // ── Events ────────────────────────────────────────────────────────────
        bindEvents() {
            if (!this.nodes.btn) return;

            this.nodes.btn.onclick = () => {
                this.updateContextUI();
                if (this.nodes.shell?.classList.contains('d-none')) {
                    this.nodes.shell.classList.remove('d-none');
                    setTimeout(() => {
                        this.nodes.shell.classList.remove('hidden');
                        this.nodes.input?.focus();
                    }, 10);
                } else {
                    this.nodes.shell?.classList.add('hidden');
                    setTimeout(() => this.nodes.shell?.classList.add('d-none'), 300);
                }
            };

            if (this.nodes.close) {
                this.nodes.close.onclick = () => {
                    this.nodes.shell?.classList.add('hidden');
                    setTimeout(() => this.nodes.shell?.classList.add('d-none'), 300);
                };
            }

            if (this.nodes.send) {
                this.nodes.send.onclick = () => this.handleSend();
            }

            if (this.nodes.input) {
                this.nodes.input.addEventListener('keydown', e => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.handleSend();
                    }
                });

                this.nodes.input.addEventListener('input', e => {
                    // Auto-resize textarea
                    e.target.style.height = 'auto';
                    e.target.style.height = e.target.scrollHeight + 'px';

                    // Slash command overlay
                    const val = e.target.value;
                    if (this.nodes.slashMenu) {
                        const show = val.trim().startsWith('/');
                        this.nodes.slashMenu.classList.toggle('d-none', !show);
                    }
                });
            }

            if (this.nodes.slashMenu) {
                this.nodes.slashMenu.addEventListener('click', e => {
                    const item = e.target.closest('.list-group-item');
                    if (item && this.nodes.input) {
                        this.nodes.input.value = item.dataset.cmd + ' ';
                        this.nodes.input.style.height = 'auto';
                        this.nodes.slashMenu.classList.add('d-none');
                        this.nodes.input.focus();
                    }
                });
            }

            document.addEventListener('click', e => {
                if (!this.nodes.input?.contains(e.target) && !this.nodes.slashMenu?.contains(e.target)) {
                    this.nodes.slashMenu?.classList.add('d-none');
                }
            });
        }

        // ── History ───────────────────────────────────────────────────────────
        renderHistory() {
            if (!this.nodes.body) return;
            this.nodes.body.innerHTML = '';
            this.history.forEach(m => this.addMessage(m.role, m.content, false));
            this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
        }

        // ── Log Monitor ───────────────────────────────────────────────────────
        startLogMonitor() {
            let lastTs = Math.floor(Date.now() / 1000);
            const check = async () => {
                try {
                    const res = await fetch(`${ADMIN_CONFIG.logUrl}?since=${lastTs}`);
                    const data = await res.json();
                    if (data.errors?.length > 0 && this.nodes.body) {
                        this.addMessage('assistant', `⚠️ System Alert: ${data.errors.length} new errors detected in logs.`);
                    }
                    lastTs = data.latest_timestamp || lastTs;
                } catch { /* silent */ }
                setTimeout(check, 60000);
            };
            setTimeout(check, 5000);
        }

        // ── Send Flow ─────────────────────────────────────────────────────────
        async handleSend() {
            const text = this.nodes.input?.value.trim();
            if (!text || this.isThinking) return;

            this.nodes.input.value = '';
            this.nodes.slashMenu?.classList.add('d-none');
            this.nodes.input.style.height = 'auto';

            this.addMessage('user', text);
            this.history.push({ role: 'user', content: text });

            await this.getAIResponse();
        }

        // ── Message Rendering ─────────────────────────────────────────────────
        addMessage(role, content, animate = true) {
            if (!this.nodes.body) return;

            const msg = document.createElement('div');
            msg.className = `ai-msg ${role}`;

            const body = document.createElement('div');
            body.className = 'ai-msg-content';

            if (content.includes('```artifact')) {
                this.renderWithArtifacts(body, content, animate && role === 'assistant');
            } else if (animate && role === 'assistant') {
                this.typeEffect(body, content);
            } else {
                body.textContent = content;
            }

            msg.appendChild(body);

            const meta = document.createElement('div');
            meta.className = 'ai-msg-meta';
            meta.textContent = new Date().toLocaleTimeString();
            msg.appendChild(meta);

            this.nodes.body.appendChild(msg);
            this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
        }

        renderWithArtifacts(container, content, animate) {
            const parts = content.split(/```artifact([\s\S]*?)```/);
            parts.forEach((part, i) => {
                if (i % 2 === 1) {
                    try { container.appendChild(this.createArtifactElement(JSON.parse(part.trim()))); }
                    catch { const pre = document.createElement('pre'); pre.textContent = part; container.appendChild(pre); }
                } else if (part.trim()) {
                    const span = document.createElement('span');
                    if (animate) this.typeEffect(span, part); else span.textContent = part;
                    container.appendChild(span);
                }
            });
        }

        createArtifactElement(data) {
            const wrap = document.createElement('div');
            wrap.className = 'ai-artifact';

            const hdr = document.createElement('div');
            hdr.className = 'ai-artifact-header';
            hdr.innerHTML = `<span>${data.title || 'Data Artifact'}</span><span class="badge bg-primary">${data.type || 'Table'}</span>`;
            wrap.appendChild(hdr);

            const body = document.createElement('div');
            body.className = 'ai-artifact-body';

            if (data.type === 'table') {
                const table = document.createElement('table');
                table.className = 'ai-artifact-table';
                if (data.headers) {
                    const thead = document.createElement('thead');
                    const tr = document.createElement('tr');
                    data.headers.forEach(h => { const th = document.createElement('th'); th.textContent = h; tr.appendChild(th); });
                    thead.appendChild(tr);
                    table.appendChild(thead);
                }
                if (data.rows) {
                    const tbody = document.createElement('tbody');
                    data.rows.forEach(row => {
                        const tr = document.createElement('tr');
                        row.forEach(cell => { const td = document.createElement('td'); td.textContent = cell; tr.appendChild(td); });
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
            let i = 0;
            const iv = setInterval(() => {
                el.textContent += text[i++];
                if (i >= text.length) {
                    clearInterval(iv);
                    this.nodes.body && (this.nodes.body.scrollTop = this.nodes.body.scrollHeight);
                }
            }, 5);
        }

        createEmptyMessage(role) {
            const msg = document.createElement('div');
            msg.className = `ai-msg ${role}`;
            const body = document.createElement('div');
            body.className = 'ai-msg-content';
            msg.appendChild(body);
            this.nodes.body.appendChild(msg);
            this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
            return body;
        }

        // ── Primary AI Request (SSE) ──────────────────────────────────────────
        async getAIResponse() {
            if (!this.nodes.body) return;
            this.isThinking = true;
            if (this.nodes.input) this.nodes.input.disabled = true;

            // Reasoning animation
            const reasoning = document.createElement('div');
            reasoning.className = 'ai-reasoning';
            reasoning.innerHTML = '<div class="ai-reasoning-step"><i class="bi bi-gear-wide-connected spin"></i> Analyzing context...</div>';
            this.nodes.body.appendChild(reasoning);
            this.nodes.body.scrollTop = this.nodes.body.scrollHeight;

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
                        'X-CSRF-TOKEN': this.csrfToken
                    },
                    body: JSON.stringify(payload)
                });

                reasoning.innerHTML = '<div class="ai-reasoning-step"><i class="bi bi-check-circle-fill text-success"></i> Connected</div>';
                setTimeout(() => reasoning.remove(), 2000);
                this.isThinking = false;

                // ── Fallback on auth / server errors ─────────────────────────
                if (!resp.ok) {
                    console.warn('[Admin AI] Provider responded', resp.status, '— trying Puter fallback');
                    if (this.nodes.input) this.nodes.input.disabled = false;
                    return await this.puterFallback();
                }

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
                                console.log('[SSE Admin]', obj.content);
                                msgBubble.innerHTML = '';
                                this.renderWithArtifacts(msgBubble, fullReply, false);
                                this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
                            } else if (obj.error) {
                                console.error('[SSE Admin Error]', obj.error);
                                const span = document.createElement('span');
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
                    sessionStorage.setItem(ADMIN_CONFIG.chatKey, JSON.stringify(this.history.slice(-40)));
                } else if (!msgBubble.textContent.trim()) {
                    msgBubble.textContent = 'Received an empty response from the AI.';
                }

                this.isThinking = false;
                if (this.nodes.input) { this.nodes.input.disabled = false; this.nodes.input.focus(); }

            } catch (err) {
                console.error('[Admin AI] Fetch error:', err);
                reasoning.parentNode && reasoning.remove();
                this.isThinking = false;
                if (this.nodes.input) this.nodes.input.disabled = false;

                // Network error → Puter fallback
                console.warn('[Admin AI] Network error — trying Puter fallback');
                await this.puterFallback();
            }
        }

        // ── Puter.js Client-side Fallback ─────────────────────────────────────
        async puterFallback() {
            console.log('[Fallback] Using Puter AI (Admin)');
            this.addMessage('assistant', '⚠️ Primary AI unavailable. Falling back to Puter AI.');

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
                    this.nodes.body && (this.nodes.body.scrollTop = this.nodes.body.scrollHeight);
                }

                if (reply) {
                    this.history.push({ role: 'assistant', content: reply });
                    sessionStorage.setItem(ADMIN_CONFIG.chatKey, JSON.stringify(this.history.slice(-40)));
                }

            } catch (fallbackErr) {
                console.error('[Admin Fallback] Puter error:', fallbackErr);
                this.addMessage('assistant', 'Connection error. Puter AI is also unavailable.');
            }
        }

        // ── Admin API Helpers ─────────────────────────────────────────────────
        async apiCall(url, body) {
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ...body, csrf_token: this.csrfToken })
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
        window.broxAdmin = new BroxAdmin();
        window.BroxAdminInstance = window.broxAdmin;

        // Expose helpers for Twig inline onclick calls
        window.testConnection = (id, model) => window.broxAdmin.testConnection(id, model);
        window.deleteProvider = (id) => window.broxAdmin.apiCall('/api/ai-system/provider/delete', { id });
    });
}
