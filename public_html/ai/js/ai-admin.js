/**
 * BroxBhai AI SYSTEM - Admin Panel & Assistant (Premium)
 * Path: /public_html/ai/js/ai-admin.js
 */

// Global Singleton Guard
if (!window.BroxAdminInstance) {

    const ADMIN_CONFIG = {
        chatKey: 'brox.admin.history',
        proxyUrl: '/api/ai-system/chat',
        logUrl: '/api/admin/logs/errors'
    };

    class BroxAdmin {
        constructor() {
            this.history = JSON.parse(sessionStorage.getItem(ADMIN_CONFIG.chatKey)) || [];
            this.isThinking = false;
            this.csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

            this.loadCSS();
            this.initUI();
            this.bindEvents();
            this.startLogMonitor();
            this.renderHistory();
        }

        loadCSS() {
            const href = window.BROX_AI_CSS_URL || '/ai/css/ai-style.css';
            if (!document.querySelector(`link[href="${href}"]`)) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                document.head.appendChild(link);
            }
        }

        initUI() {
            this.nodes = {
                btn: document.getElementById('adminAiBtn'),
                shell: document.getElementById('adminAiShell'),
                body: document.getElementById('adminAiBody'),
                footer: document.getElementById('adminAiFooter'),
                input: document.getElementById('adminAiInput'),
                send: document.getElementById('adminAiSend'),
                close: document.getElementById('adminAiClose')
            };
        }

        bindEvents() {
            if (this.nodes.btn) this.nodes.btn.onclick = () => this.nodes.shell?.classList.toggle('hidden');
            if (this.nodes.close) this.nodes.close.onclick = () => this.nodes.shell?.classList.add('hidden');
            if (this.nodes.send) this.nodes.send.onclick = () => this.handleSend();
            if (this.nodes.input) this.nodes.input.onkeypress = (e) => { if (e.key === 'Enter') this.handleSend(); };

            // Settings events (delegation or direct)
            document.querySelectorAll('.toggle-provider').forEach(el => {
                el.onchange = () => this.toggleProvider(el.dataset.providerId, el.checked);
            });
            document.querySelectorAll('.set-default-btn').forEach(el => {
                el.onclick = () => this.setDefaultProvider(el.dataset.providerId);
            });
        }

        renderHistory() {
            if (!this.nodes.body) return;
            this.history.forEach(m => this.addMessage(m.role, m.content, false));
        }

        async handleSend() {
            const text = this.nodes.input.value.trim();
            if (!text || this.isThinking) return;

            this.nodes.input.value = '';
            this.addMessage('user', text);
            this.history.push({ role: 'user', content: text });

            await this.getAIResponse();
        }

        addMessage(role, content, animate = true) {
            if (!this.nodes.body) return;
            const msg = document.createElement('div');
            msg.className = `ai-msg ${role}`;

            const body = document.createElement('div');
            body.className = 'ai-msg-content';
            if (animate && role === 'assistant') {
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

        typeEffect(el, text) {
            if (!text) return;
            let i = 0;
            const interval = setInterval(() => {
                el.textContent += text[i];
                i++;
                if (i >= text.length) {
                    clearInterval(interval);
                    if (this.nodes.body) this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
                }
            }, 10);
        }

        async getAIResponse() {
            this.isThinking = true;
            const typing = this.showTyping();

            try {
                const resp = await fetch(ADMIN_CONFIG.proxyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ messages: this.history, isAdmin: true })
                });
                const data = await resp.json();

                if (typing) typing.remove();
                this.isThinking = false;

                if (data.success) {
                    const reply = data.text || data.message?.content || '';
                    this.addMessage('assistant', reply);
                    this.history.push({ role: 'assistant', content: reply });
                    sessionStorage.setItem(ADMIN_CONFIG.chatKey, JSON.stringify(this.history.slice(-40)));
                } else {
                    this.addMessage('assistant', "Error: " + (data.error || 'Connection failed'));
                }
            } catch (err) {
                if (typing) typing.remove();
                this.isThinking = false;
                this.addMessage('assistant', "Connection error.");
            }
        }

        showTyping() {
            if (!this.nodes.body) return null;
            const div = document.createElement('div');
            div.className = 'ai-typing';
            div.innerHTML = '<span></span><span></span><span></span>';
            this.nodes.body.appendChild(div);
            this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
            return div;
        }

        // --- ADMIN API ACTIONS ---
        async apiCall(url, body) {
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ...body, csrf_token: this.csrfToken })
                });

                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Malformed JSON response:", text);
                    return { success: false, error: "Invalid server response (Non-JSON)" };
                }
            } catch (e) {
                console.error("API Error:", e);
                return { success: false, error: "Network error" };
            }
        }

        async toggleProvider(id, active) {
            const res = await this.apiCall('/api/ai-system/toggle-provider', { id, active });
            if (res.success) location.reload();
            else alert('Failed: ' + (res.error || 'Unknown error'));
        }

        async setDefaultProvider(id) {
            const res = await this.apiCall('/api/ai-system/set-default', { id });
            if (res.success) location.reload();
            else alert('Failed: ' + (res.error || 'Unknown error'));
        }

        async deleteProvider(id) {
            if (!confirm('Are you sure you want to delete this provider?')) return;
            const res = await this.apiCall('/api/ai-system/delete-provider', { id });
            if (res.success) location.reload();
            else alert('Failed: ' + (res.error || 'Unknown error'));
        }

        async testConnection(id, model = null) {
            const btn = document.querySelector(`button[onclick*="testConnection(${id}"]`);
            const originalHtml = btn ? btn.innerHTML : null;
            if (btn) btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            const res = await this.apiCall('/api/ai-system/test', { id, model });

            if (btn) btn.innerHTML = originalHtml;

            if (res.success) alert('Connection successful!');
            else alert('Connection failed: ' + (res.error || 'Unknown error'));
        }

        async startLogMonitor() {
            let lastTs = Math.floor(Date.now() / 1000);
            const check = async () => {
                try {
                    const res = await fetch(`${ADMIN_CONFIG.logUrl}?since=${lastTs}`);
                    const data = await res.json();
                    if (data.errors?.length > 0) {
                        this.addMessage('assistant', `⚠️ System Alert: ${data.errors.length} new errors detected in logs.`);
                    }
                    lastTs = data.latest_timestamp || lastTs;
                } catch { }
                setTimeout(check, 60000); // Check every minute
            };
            setTimeout(check, 5000); // Initial delay
        }

        // Hooks for external tools (RTE, Selector Detector)
        async enhanceContent(content, prompt) {
            const data = await this.apiCall(ADMIN_CONFIG.proxyUrl, {
                messages: [{ role: 'system', content: prompt || 'Enhance this HTML.' }, { role: 'user', content }],
                isAdmin: true
            });
            return data.text || data.message?.content || '';
        }

        async detectSelectors(html, url) {
            const prompt = `Analyze this HTML and detect CSS selectors for scraping. URL: ${url}\nHTML: ${html.substring(0, 5000)}`;
            const data = await this.apiCall(ADMIN_CONFIG.proxyUrl, {
                messages: [{ role: 'user', content: prompt }],
                isAdmin: true
            });
            return data.text || data.message?.content || '';
        }
    }

    // Global exposure for Twig compatibility
    document.addEventListener('DOMContentLoaded', () => {
        if (window.BroxAdminInstance) return;
        window.broxAdmin = new BroxAdmin();
        window.BroxAdminInstance = window.broxAdmin;

        // Map legacy global functions to new class methods
        window.testConnection = (id, model) => window.broxAdmin.testConnection(id, model);
        window.deleteProvider = (id) => window.broxAdmin.deleteProvider(id);
        window.toggleProvider = (id, active) => window.broxAdmin.toggleProvider(id, active);
        window.setDefaultProvider = (id) => window.broxAdmin.setDefaultProvider(id);

        // Tools mapping
        window.enhanceContentWithAI = (opts) => window.broxAdmin.enhanceContent(opts.content, opts.prompt);
        window.detectSelectorsWithAI = (html, url) => window.broxAdmin.detectSelectors(html, url);
    });
}
