/**
 * BroxBhai AI SYSTEM - Admin Panel & Copilot (2026 Standard)
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
            this.initUI();
            this.bindEvents();
            this.startLogMonitor();
            this.renderHistory();
            this.updateContext();
        }

        initUI() {
            this.nodes = {
                btn: document.getElementById('adminAiBtn'),
                shell: document.getElementById('adminAiShell'),
                body: document.getElementById('adminAiBody'),
                footer: document.getElementById('adminAiFooter'),
                input: document.getElementById('adminAiInput'),
                send: document.getElementById('adminAiSend'),
                close: document.getElementById('adminAiClose'),
                title: document.getElementById('adminAiTitle'),
                status: document.getElementById('adminAiStatus'),
                slashMenu: document.getElementById('adminAiSlashMenu')
            };
        }

        bindEvents() {
            if (this.nodes.btn) this.nodes.btn.onclick = () => this.toggleSidebar();
            if (this.nodes.close) this.nodes.close.onclick = () => this.toggleSidebar(false);
            if (this.nodes.send) this.nodes.send.onclick = () => this.handleSend();

            if (this.nodes.input) {
                this.nodes.input.onkeypress = (e) => { if (e.key === 'Enter') this.handleSend(); };
                this.nodes.input.oninput = (e) => this.handleInput(e);
            }

            // Slash menu clicks
            document.querySelectorAll('.ai-slash-item').forEach(el => {
                el.onclick = () => {
                    this.nodes.input.value = el.dataset.cmd + ' ';
                    this.nodes.slashMenu.classList.add('hidden');
                    this.nodes.input.focus();
                };
            });

            // Global click to close slash menu
            document.addEventListener('click', (e) => {
                if (!this.nodes.input?.contains(e.target) && !this.nodes.slashMenu?.contains(e.target)) {
                    this.nodes.slashMenu?.classList.add('hidden');
                }
            });
        }

        toggleSidebar(show = null) {
            if (show === null) this.nodes.shell?.classList.toggle('hidden');
            else if (show) this.nodes.shell?.classList.remove('hidden');
            else this.nodes.shell?.classList.add('hidden');
        }

        handleInput(e) {
            const val = e.target.value;
            if (val === '/') {
                this.nodes.slashMenu?.classList.remove('hidden');
            } else if (!val.startsWith('/')) {
                this.nodes.slashMenu?.classList.add('hidden');
            }
        }

        updateContext() {
            if (!this.nodes.status) return;
            const pageTitle = document.title.split('|')[0].trim();
            this.nodes.status.textContent = `Context: ${pageTitle}`;
        }

        renderHistory() {
            if (!this.nodes.body) return;
            this.nodes.body.innerHTML = '';
            this.history.forEach(m => this.addMessage(m.role, m.content, false));
        }

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
                } catch { }
                setTimeout(check, 60000);
            };
            setTimeout(check, 5000);
        }

        async handleSend() {
            const text = this.nodes.input.value.trim();
            if (!text || this.isThinking) return;

            this.nodes.input.value = '';
            this.nodes.slashMenu?.classList.add('hidden');

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

            // Check for artifacts in content
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
            parts.forEach((part, index) => {
                if (index % 2 === 1) {
                    // This is an artifact
                    try {
                        const data = JSON.parse(part.trim());
                        container.appendChild(this.createArtifactElement(data));
                    } catch (e) {
                        const pre = document.createElement('pre');
                        pre.textContent = part;
                        container.appendChild(pre);
                    }
                } else if (part.trim()) {
                    const span = document.createElement('span');
                    if (animate) this.typeEffect(span, part);
                    else span.textContent = part;
                    container.appendChild(span);
                }
            });
        }

        createArtifactElement(data) {
            const wrapper = document.createElement('div');
            wrapper.className = 'ai-artifact';

            const header = document.createElement('div');
            header.className = 'ai-artifact-header';
            header.innerHTML = `<span>${data.title || 'Data Artifact'}</span><span class="badge bg-primary">${data.type || 'Table'}</span>`;
            wrapper.appendChild(header);

            const body = document.createElement('div');
            body.className = 'ai-artifact-body';

            if (data.type === 'table') {
                const table = document.createElement('table');
                table.className = 'ai-artifact-table';

                if (data.headers) {
                    const thead = document.createElement('thead');
                    const hr = document.createElement('tr');
                    data.headers.forEach(h => hr.innerHTML += `<th>${h}</th>`);
                    thead.appendChild(hr);
                    table.appendChild(thead);
                }

                if (data.rows) {
                    const tbody = document.createElement('tbody');
                    data.rows.forEach(row => {
                        const tr = document.createElement('tr');
                        row.forEach(cell => tr.innerHTML += `<td>${cell}</td>`);
                        tbody.appendChild(tr);
                    });
                    table.appendChild(tbody);
                }
                body.appendChild(table);
            } else {
                body.textContent = JSON.stringify(data.content || data, null, 2);
            }

            wrapper.appendChild(body);
            return wrapper;
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
            }, 5);
        }

        async getAIResponse() {
            this.isThinking = true;
            const typing = this.showTyping();

            // Add reasoning step
            const reasoning = document.createElement('div');
            reasoning.className = 'ai-reasoning';
            reasoning.innerHTML = '<div class="ai-reasoning-step"><i class="fas fa-spinner fa-spin"></i> Analyzing context...</div>';
            this.nodes.body.appendChild(reasoning);

            try {
                const context = {
                    url: window.location.href,
                    title: document.title,
                    timestamp: new Date().toISOString()
                };

                const resp = await fetch(ADMIN_CONFIG.proxyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        messages: this.history,
                        isAdmin: true,
                        context: context
                    })
                });
                const data = await resp.json();

                if (typing) typing.remove();
                reasoning.innerHTML = '<div class="ai-reasoning-step"><i class="fas fa-check text-success"></i> Done</div>';
                setTimeout(() => reasoning.remove(), 2000);

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
                reasoning.remove();
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
                return await res.json();
            } catch (e) {
                return { success: false, error: "Network error" };
            }
        }

        async testConnection(id, model = null) {
            const res = await this.apiCall('/api/ai-system/test', { id, model });
            if (res.success) alert('Connection successful!');
            else alert('Connection failed: ' + (res.error || 'Unknown error'));
        }
    }

    // Global exposure
    document.addEventListener('DOMContentLoaded', () => {
        if (window.BroxAdminInstance) return;
        window.broxAdmin = new BroxAdmin();
        window.BroxAdminInstance = window.broxAdmin;

        // Expose helpers for Twig
        window.testConnection = (id, model) => window.broxAdmin.testConnection(id, model);
    });
}
