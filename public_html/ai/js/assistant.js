if (!window.BroxAssistantLoaded) {
    window.BroxAssistantLoaded = true;

    // ── Auto-inject ai-style.css (no <link> tag needed in HTML) ──────────────────
    (function injectAiCSS() {
        // We auto-load the CSS dynamically via JS
        const scriptPath = document.currentScript?.src || '/ai/js/assistant.js';
        // Strip query strings (?v=...) and replace /js/assistant.js with /css/ai-style.css
        const baseUrl = scriptPath.split('?')[0];
        const cssUrl = baseUrl.replace(/\/js\/assistant\.js$/, '/css/ai-style.css');

        if (!document.querySelector(`link[href^="${cssUrl}"]`)) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = cssUrl;
            document.head.appendChild(link);
        }
    })();

    const CONFIG = {
        chatKey: 'brox.ai.history',
        userKey: 'brox.ai.user',
        langKey: 'brox.ai.lang',
        tokenKey: 'brox.ai.visitor_token',
        proxyUrl: '/api/ai-system/chat',
        modelsUrl: '/api/ai-system/models',       // GET ?provider=openrouter
        puterCdn: 'https://js.puter.com/v2/'     // Puter.js CDN (fallback only)
    };

    const I18N = {
        bn: {
            title: 'ব্রক্স সহকারী',
            status: 'অনলাইন ও প্রস্তুত',
            thinking: 'উত্তর খুঁজছি...',
            welcome: 'হ্যালো! আমি ব্রক্স ল্যাব সহকারী। কীভাবে আপনাকে সাহায্য করতে পারি?',
            placeholder: 'এখানে লিখুন...',
            name_label: 'আপনার নাম',
            topic_label: 'বিষয় নির্বাচন করুন (একাধিক হতে পারে)',
            start_btn: 'চ্যাট শুরু করুন',
            err_name: 'দয়া করে আপনার নাম লিখুন।',
            err_conn: 'দুঃখিত, বর্তমানে সংযোগে সমস্যা হচ্ছে। পরে চেষ্টা করুন।',
            fallback: '⚠️ প্রাথমিক AI-তে সমস্যা হয়েছে। Puter AI ব্যবহার করা হচ্ছে।',
            reset: 'পূর্বের চ্যাট হিস্ট্রি রিসেট করা হয়েছে।'
        },
        en: {
            title: 'Brox Assistant',
            status: 'Online & Ready',
            thinking: 'AI is thinking...',
            welcome: 'Hello! I am Brox Lab assistant. How can I help you today?',
            placeholder: 'Type message...',
            name_label: 'Your Name',
            topic_label: 'Select Topics (Multi-select)',
            start_btn: 'Start Chatting',
            err_name: 'Please enter your name.',
            err_conn: 'Connection error. Please try again later.',
            fallback: '⚠️ Primary AI unavailable. Falling back to Puter AI.',
            reset: 'Previous chat history has been reset.'
        }
    };

    // ─── Puter.js Loader (lazy, CDN) ──────────────────────────────────────────────
    function loadPuter() {
        return new Promise((resolve, reject) => {
            if (window.puter) return resolve(window.puter);
            const s = document.createElement('script');
            s.src = CONFIG.puterCdn;
            s.async = true;
            s.onload = () => {
                console.log('[Puter] SDK loaded from CDN');
                resolve(window.puter);
            };
            s.onerror = () => reject(new Error('Puter.js could not be loaded from CDN'));
            document.head.appendChild(s);
        });
    }

    // ─── Remote Model Loader ──────────────────────────────────────────────────────
    async function fetchModels(provider) {
        try {
            const res = await fetch(`${CONFIG.modelsUrl}?provider=${encodeURIComponent(provider)}`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            return Array.isArray(data.models) ? data.models : [];
        } catch (e) {
            console.warn('[Models] Failed to fetch model list:', e.message);
            return [];
        }
    }

    // ─── Main Class ───────────────────────────────────────────────────────────────
    class BroxAssistant {
        constructor() {
            this.lang = localStorage.getItem(CONFIG.langKey) || 'bn';
            this.history = this.loadHistory();
            this.user = JSON.parse(localStorage.getItem(CONFIG.userKey)) || null;
            this.visitorToken = this.getVisitorToken();
            this.isThinking = false;
            this.currentModel = null;    // will be set after model list loads

            this.initUI();
            if (this.nodes.btn) {
                this.bindEvents();
                this.renderInitialState();
                this.loadProviderModels();   // load default provider models on boot
            }
        }

        t(key) { return I18N[this.lang][key] || key; }

        getVisitorToken() {
            let token = localStorage.getItem(CONFIG.tokenKey);
            if (!token) {
                token = 'vt_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
                localStorage.setItem(CONFIG.tokenKey, token);
            }
            return token;
        }

        loadHistory() {
            try { return JSON.parse(localStorage.getItem(CONFIG.chatKey)) || []; }
            catch { return []; }
        }

        saveHistory() {
            localStorage.setItem(CONFIG.chatKey, JSON.stringify(this.history.slice(-40)));
        }

        // ── UI Nodes ──────────────────────────────────────────────────────────────
        initUI() {
            this.nodes = {
                btn: document.getElementById('publicAssistantBtn'),
                shell: document.getElementById('publicAssistantChat'),
                sidebar: document.getElementById('publicAssistantSidebar'),
                history: document.getElementById('publicAssistantHistory'),
                toggleSidebar: document.getElementById('toggleAiSidebar'),
                title: document.getElementById('publicAssistantTitle'),
                status: document.getElementById('publicAssistantStatusText'),
                agenticStatus: document.getElementById('publicAssistantAgenticStatus'),
                statusDetail: document.querySelector('.ai-status-detail'),
                body: document.getElementById('publicAssistantMessages'),
                footer: document.getElementById('publicAssistantFooter'),
                input: document.getElementById('publicAssistantInput'),
                send: document.getElementById('sendToPublicAssistant'),
                prechat: document.getElementById('publicAssistantPreChat'),
                langBn: document.getElementById('publicAssistantLangBn'),
                langEn: document.getElementById('publicAssistantLangEn'),
                close: document.getElementById('closePublicAssistant'),
                modelSel: document.getElementById('publicAssistantModel'),
                quickActions: document.getElementById('publicAssistantQuickActions'),

                prechatSteps: {
                    name: document.querySelector('.step-name'),
                    contact: document.querySelector('.step-contact'),
                    topic: document.querySelector('.step-topic')
                },
                prechatBtns: {
                    next1: document.getElementById('introNext1'),
                    next2: document.getElementById('introNext2'),
                    start: document.getElementById('introStartChat')
                },
                prechatInputs: {
                    name: document.getElementById('introName'),
                    email: document.getElementById('introEmail'),
                    mobile: document.getElementById('introMobile')
                }
            };
            if (this.nodes.btn) {
                this.updateLangUI();
                this.renderHistorySidebar();
            }
        }

        // ── Language ──────────────────────────────────────────────────────────────
        updateLangUI() {
            if (!this.nodes.title || !this.nodes.input) return;
            this.nodes.title.textContent = this.t('title');
            if (this.nodes.status)
                this.nodes.status.textContent = this.isThinking ? this.t('thinking') : this.t('status');
            this.nodes.input.placeholder = this.t('placeholder');

            const nameLabel = document.getElementById('introNameLabel');
            const topicLabel = document.getElementById('introTopicLabel');
            const startBtn = document.getElementById('introStartChat');
            if (nameLabel) nameLabel.textContent = this.t('name_label');
            if (topicLabel) topicLabel.textContent = this.t('topic_label');
            if (startBtn) startBtn.textContent = this.t('start_btn');

            ['langBn', 'langEn'].forEach(k => {
                const node = this.nodes[k];
                if (!node) return;
                const isActive = (k === 'langBn') ? this.lang === 'bn' : this.lang === 'en';
                node.classList.toggle('active', isActive);
                // node.classList.toggle('btn-light', isActive); // Clean up old classes
                // node.classList.toggle('btn-outline-light', !isActive);
            });
        }

        saveLang() {
            localStorage.setItem(CONFIG.langKey, this.lang);
            this.updateLangUI();
            if (this.user) this.renderInitialState();
        }

        // ── Sidebar & History ─────────────────────────────────────────────────────
        renderHistorySidebar() {
            if (!this.nodes.history) return;
            this.nodes.history.innerHTML = '';
            if (this.history.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'text-center p-4 text-muted small';
                empty.textContent = 'কোন ইতিহাস নেই';
                this.nodes.history.appendChild(empty);
                return;
            }

            const entry = document.createElement('div');
            entry.className = 'ai-history-item';
            const firstMsg = this.history[0]?.content || 'চ্যাট সেশন';
            entry.textContent = firstMsg.substring(0, 30) + '...';
            entry.onclick = () => {
                alert('এই চ্যাটটি বর্তমানে সক্রিয় আছে।');
            };
            this.nodes.history.appendChild(entry);
        }

        // ── Initial Render ────────────────────────────────────────────────────────
        renderInitialState() {
            if (!this.nodes.prechat || !this.nodes.body || !this.nodes.footer) return;

            if (!this.user) {
                this.nodes.prechat.classList.remove('d-none');
                this.nodes.body.classList.add('d-none');
                this.nodes.footer.classList.add('d-none');
                this.nodes.quickActions?.classList.add('d-none');
                if (this.nodes.prechatSteps.name) this.nodes.prechatSteps.name.classList.remove('d-none');
                if (this.nodes.prechatSteps.contact) this.nodes.prechatSteps.contact.classList.add('d-none');
                if (this.nodes.prechatSteps.topic) this.nodes.prechatSteps.topic.classList.add('d-none');
            } else {
                this.nodes.prechat.classList.add('d-none');
                this.nodes.body.classList.remove('d-none');
                this.nodes.footer.classList.remove('d-none');
                this.nodes.quickActions?.classList.remove('d-none');
                this.nodes.body.innerHTML = '';

                if (this.history.length === 0) {
                    const greeting = (this.lang === 'bn' ? `হ্যালো ${this.user.name}! ` : `Hello ${this.user.name}! `) + this.t('welcome');
                    this.addMessage('assistant', greeting);
                } else {
                    this.history.forEach(m => this.addMessage(m.role, m.content, false));
                }
            }
            this.renderHistorySidebar();
        }

        // ── Remote Model Loading ──────────────────────────────────────────────────
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
                opt.textContent = m.name + (m.id.endsWith(':free') ? ' (Free)' : '');
                if (m.default) opt.selected = true;
                this.nodes.modelSel.appendChild(opt);
            });

            const defaultOpt = models.find(m => m.default);
            this.currentModel = defaultOpt ? defaultOpt.id : models[0].id;
            this.nodes.modelSel.classList.remove('d-none');

            this.nodes.modelSel.addEventListener('change', () => {
                this.currentModel = this.nodes.modelSel.value;
            });
        }

        // ── Events ────────────────────────────────────────────────────────────────
        bindEvents() {
            this.nodes.btn.onclick = () => {
                if (this.nodes.shell?.classList.contains('d-none')) {
                    this.nodes.shell.classList.remove('d-none');
                    setTimeout(() => this.nodes.shell.classList.remove('hidden'), 10);
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

            if (this.nodes.toggleSidebar) {
                this.nodes.toggleSidebar.onclick = () => {
                    this.nodes.sidebar?.classList.toggle('collapsed');
                };
                if (this.nodes.title) {
                    this.nodes.title.style.cursor = 'pointer';
                    this.nodes.title.onclick = () => this.nodes.sidebar?.classList.toggle('collapsed');
                }
            }

            if (this.nodes.langBn) this.nodes.langBn.onclick = () => { this.lang = 'bn'; this.saveLang(); };
            if (this.nodes.langEn) this.nodes.langEn.onclick = () => { this.lang = 'en'; this.saveLang(); };

            if (this.nodes.send) this.nodes.send.onclick = () => this.handleSend();
            if (this.nodes.input) this.nodes.input.onkeypress = e => { if (e.key === 'Enter') this.handleSend(); };

            if (this.nodes.quickActions) {
                this.nodes.quickActions.querySelectorAll('.ai-action-chip').forEach(btn => {
                    btn.onclick = () => {
                        this.nodes.input.value = btn.dataset.prompt;
                        this.handleSend();
                    };
                });
            }

            if (this.nodes.prechatBtns.next1) {
                this.nodes.prechatBtns.next1.onclick = () => {
                    if (!this.nodes.prechatInputs.name?.value.trim()) { alert(this.t('err_name')); return; }
                    this.nodes.prechatSteps.name?.classList.add('d-none');
                    this.nodes.prechatSteps.contact?.classList.remove('d-none');
                };
            }
            if (this.nodes.prechatBtns.next2) {
                this.nodes.prechatBtns.next2.onclick = () => {
                    this.nodes.prechatSteps.contact?.classList.add('d-none');
                    this.nodes.prechatSteps.topic?.classList.remove('d-none');
                };
            }
            if (this.nodes.prechatBtns.start) {
                this.nodes.prechatBtns.start.onclick = () => this.startChat();
            }
        }

        startChat() {
            const name = this.nodes.prechatInputs.name?.value.trim();
            if (!name) { alert(this.t('err_name')); return; }
            const email = this.nodes.prechatInputs.email?.value.trim() || '';
            const phone = this.nodes.prechatInputs.mobile?.value.trim() || '';
            const selected = Array.from(document.querySelectorAll('.ai-topic-grid input:checked')).map(i => i.value);
            this.user = { name, email, phone, topics: selected };
            localStorage.setItem(CONFIG.userKey, JSON.stringify(this.user));
            this.renderInitialState();
        }

        async handleSend() {
            const text = this.nodes.input.value.trim();
            if (!text || this.isThinking) return;
            this.nodes.input.value = '';
            this.addMessage('user', text);
            this.history.push({ role: 'user', content: text });
            this.saveHistory();
            this.renderHistorySidebar();
            await this.getAIResponse();
        }

        // ── Message Rendering ─────────────────────────────────────────────────────
        addMessage(role, content, animate = true) {
            if (!this.nodes.body) return;
            const existing = this.nodes.body.querySelector('.ai-typing');
            existing?.remove();

            const msg = document.createElement('div');
            msg.className = `ai-msg ${role}`;

            const body = document.createElement('div');
            body.className = 'ai-msg-content';
            msg.appendChild(body);

            if (animate && role === 'assistant') this.typeEffect(body, content);
            else this.renderMarkdown(body, content);

            const meta = document.createElement('div');
            meta.className = 'ai-msg-meta';
            meta.textContent = new Date().toLocaleTimeString(this.lang === 'bn' ? 'bn-BD' : 'en-US', { hour: '2-digit', minute: '2-digit' });
            msg.appendChild(meta);

            this.nodes.body.appendChild(msg);
            this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
        }

        renderMarkdown(el, text) {
            if (!text) return;
            let html = text
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`(.*?)`/g, '<code>$1</code>')
                .replace(/\n/g, '<br>');
            html = html.replace(/```(.*?)\n([\s\S]*?)```/g, (m, lang, code) => {
                return `<pre><code class="language-${lang.trim()}">${code.trim()}</code></pre>`;
            });
            el.innerHTML = html;
        }

        typeEffect(el, text) {
            if (!text) return;
            let i = 0;
            const iv = setInterval(() => {
                const char = text[i++];
                if (char === undefined) {
                    clearInterval(iv);
                    this.renderMarkdown(el, text);
                    this.nodes.body?.scrollTop && (this.nodes.body.scrollTop = this.nodes.body.scrollHeight);
                    return;
                }
                el.textContent += char;
                if (i % 5 === 0) this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
            }, 10);
        }

        createEmptyMessage(role) {
            if (!this.nodes.body) return document.createElement('div');
            const msg = document.createElement('div');
            msg.className = `ai-msg ${role}`;
            const body = document.createElement('div');
            body.className = 'ai-msg-content';
            msg.appendChild(body);
            const meta = document.createElement('div');
            meta.className = 'ai-msg-meta';
            meta.textContent = new Date().toLocaleTimeString(this.lang === 'bn' ? 'bn-BD' : 'en-US', { hour: '2-digit', minute: '2-digit' });
            msg.appendChild(meta);
            this.nodes.body.appendChild(msg);
            this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
            return body;
        }

        updateAgenticStatus(pillText, detailText) {
            if (!this.nodes.agenticStatus || !this.nodes.statusDetail) return;
            if (pillText) {
                this.nodes.agenticStatus.classList.remove('d-none');
                const pill = this.nodes.agenticStatus.querySelector('.ai-status-pill');
                if (pill) pill.textContent = pillText;
                this.nodes.statusDetail.textContent = detailText || '';
            } else {
                this.nodes.agenticStatus.classList.add('d-none');
            }
        }

        showTyping() {
            if (!this.nodes.body) return null;
            const div = document.createElement('div');
            div.className = 'ai-typing';
            div.innerHTML = `<span></span><span></span><span></span>`;
            this.nodes.body.appendChild(div);
            this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
            return div;
        }

        async getAIResponse() {
            this.isThinking = true;
            this.updateLangUI();
            this.updateAgenticStatus('Thinking', 'নলেজ বেস চেক করছি...');
            try {
                const payload = {
                    messages: this.history,
                    visitorToken: this.visitorToken,
                    context: this.user,
                    stream: true
                };
                if (this.currentModel) payload.model = this.currentModel;
                const resp = await fetch(CONFIG.proxyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                this.updateAgenticStatus('Agentic', 'উত্তর জেনারেট করছি...');
                if (!resp.ok) {
                    this.updateAgenticStatus(null);
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
                                this.renderMarkdown(msgBubble, fullReply);
                                this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
                            }
                        } catch (e) { }
                    }
                }
                this.isThinking = false;
                this.updateLangUI();
                this.updateAgenticStatus(null);
                if (fullReply) {
                    this.history.push({ role: 'assistant', content: fullReply });
                    this.saveHistory();
                }
            } catch (err) {
                this.isThinking = false;
                this.updateLangUI();
                this.updateAgenticStatus(null);
                await this.puterFallback();
            }
        }

        async puterFallback() {
            this.addMessage('assistant', this.t('fallback'));
            try {
                const puter = await loadPuter();
                const lastMsg = this.history.filter(m => m.role === 'user').pop();
                if (!lastMsg) return;
                const msgBubble = this.createEmptyMessage('assistant');
                let reply = '';
                const stream = await puter.ai.chat(lastMsg.content, { stream: true });
                for await (const chunk of stream) {
                    reply += chunk?.text || '';
                    this.renderMarkdown(msgBubble, reply);
                    this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
                }
                if (reply) {
                    this.history.push({ role: 'assistant', content: reply });
                    this.saveHistory();
                }
            } catch (fallbackErr) {
                this.addMessage('assistant', this.t('err_conn'));
            }
        }
    }

    // ── Bootstrap ─────────────────────────────────────────────────────────────────
    if (!window.broxAssistant) {
        document.addEventListener('DOMContentLoaded', () => {
            window.broxAssistant = new BroxAssistant();
        });
    }

} // End of BroxAssistantLoaded guard
