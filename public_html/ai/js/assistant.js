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
        userKey: 'brox.ai.visitor',
        langKey: 'brox.ai.lang',
        tokenKey: 'brox.ai.visitor_token',
        proxyUrl: '/api/ai/chat',
        modelsUrl: '/api/ai/models',
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
            err_email_invalid: 'দয়া করে সঠিক ইমেল ঠিকানা দিন।',
            err_mobile_invalid: 'দয়া করে সঠিক মোবাইল নম্বর দিন (১১টি সংখ্যা)।',
            err_conn: 'দুঃখিত, বর্তমানে সংযোগে সমস্যা হচ্ছে। পরে চেষ্টা করুন।',
            fallback: '⚠️ AI-তে সমস্যা হয়েছে। সমাধান করার চেষ্টা করছি...',
            reset: 'পূর্ববর্তী চ্যাট হিস্ট্রি রিসেট করা হয়েছে।',
            history_empty: 'কোন ইতিহাস নেই',
            chat_session: 'চ্যাট সেশন',
            no_history: 'এই চ্যাটটি বর্তমানে সক্রিয় আছে।'
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
            err_email_invalid: 'Please enter a valid email address.',
            err_mobile_invalid: 'Please enter a valid mobile number (11 digits).',
            err_conn: 'Connection error. Please try again later.',
            fallback: '⚠️ Primary AI unavailable. Falling back to Puter AI.',
            reset: 'Previous chat history has been reset.',
            history_empty: 'No history',
            chat_session: 'Chat Session',
            no_history: 'This chat is currently active.'
        }
    };

    // ─── Validation Helpers ─────────────────────────────────────────────
    function validateEmail(email) {
        if (!email) return true; // Optional field
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function validateMobile(mobile) {
        if (!mobile) return true; // Optional field
        // Bangladesh mobile: 11 digits starting with 01, or with +880
        const cleaned = mobile.replace(/[+\s-]/g, '');
        // Accept: 01XXXXXXXXX (11 digits), +8801XXXXXXXXX (13 digits)
        return /^(\+8801|8801|01)\d{9}$/.test(cleaned);
    }

    function sanitizeInput(text) {
        if (!text) return '';
        // Basic XSS prevention
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

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
            // Return fallback models for public assistant
            return [
                { id: 'anthropic/claude-3-haiku:free', name: 'Claude 3 Haiku', default: true },
                { id: 'google/gemini-pro-1.5:free', name: 'Gemini Pro 1.5' },
                { id: 'openai/gpt-4o-mini:free', name: 'GPT-4o Mini' }
            ];
        }
    }

    // ─── Main Class ───────────────────────────────────────────────────────────────
    class BroxAssistant {
        constructor() {
            this.lang = localStorage.getItem(CONFIG.langKey) || 'bn';
            this.history = this.loadHistory();
            // User data now session-only (not persisted to localStorage for privacy)
            this.user = null; // Will be set per session, not stored
            this.visitorToken = this.getVisitorToken();
            this.isThinking = false;
            this.currentModel = null;    // will be set after model list loads
            this.recognition = null;     // Speech recognition instance

            this.initUI();
            if (this.nodes.btn) {
                this.bindEvents();
                this.initSpeechRecognition();
                this.initFileAttachment();
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
                shell: document.getElementById('PublicAssistantChat'),
                sidebar: document.getElementById('publicAssistantSidebar'),
                history: document.getElementById('publicAssistantHistory'),
                toggleSidebar: document.getElementById('toggleAiSidebar'),
                title: document.getElementById('publicAssistantTitle'),
                status: document.getElementById('publicAssistantStatusText'),
                agenticStatus: document.getElementById('publicAssistantAgenticStatus'),
                statusDetail: document.querySelector('.brox-ai-status-detail'),
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
            });
        }

        saveLang() {
            localStorage.setItem(CONFIG.langKey, this.lang);
            this.updateLangUI();
            this.updatePrechatLabels();
        }

        // Update pre-chat labels when language changes
        updatePrechatLabels() {
            const nameLabel = document.getElementById('introNameLabel');
            const emailLabel = document.getElementById('introEmailLabel');
            const mobileLabel = document.getElementById('introMobileLabel');
            const topicLabel = document.getElementById('introTopicLabel');
            const startBtn = document.getElementById('introStartChat');

            if (nameLabel) {
                const nameInput = document.getElementById('introName');
                nameLabel.textContent = this.lang === 'bn' ? 'আপনার নাম' : 'Your Name';
                if (nameInput) nameInput.placeholder = this.lang === 'bn' ? 'আপনার নাম লিখুন' : 'Enter your name';
            }
            if (emailLabel) {
                const emailInput = document.getElementById('introEmail');
                emailLabel.textContent = this.lang === 'bn' ? 'ইমেল (ঐচ্ছিক)' : 'Email (Optional)';
                if (emailInput) emailInput.placeholder = this.lang === 'bn' ? 'আপনার ইমেল লিখুন' : 'Enter your email';
            }
            if (mobileLabel) {
                const mobileInput = document.getElementById('introMobile');
                mobileLabel.textContent = this.lang === 'bn' ? 'মোবাইল নম্বর (ঐচ্ছিক)' : 'Mobile Number (Optional)';
                if (mobileInput) mobileInput.placeholder = this.lang === 'bn' ? 'মোবাইল নম্বর লিখুন' : 'Enter mobile number';
            }
            if (topicLabel) topicLabel.textContent = this.t('topic_label');
            if (startBtn) startBtn.textContent = this.t('start_btn');
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
            entry.className = 'brox-ai-history-item';
            const firstMsg = this.history[0]?.content || 'চ্যাট সেশন';
            entry.textContent = firstMsg.substring(0, 30) + '...';
            entry.onclick = () => {
                alert('এই চ্যাটটি বর্তমানে সক্রিয় আছে।');
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

        // ── Speech Recognition ─────────────────────────────────────────────────
        initSpeechRecognition() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                console.log('[Voice] Speech API not supported');
                return;
            }

            this.recognition = new SpeechRecognition();
            this.recognition.continuous = false;
            this.recognition.interimResults = true;
            this.recognition.lang = this.lang === 'bn' ? 'bn-BD' : 'en-US';

            this.recognition.onresult = (event) => {
                let interimTranscript = '';
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    if (event.results[i].isFinal) {
                        this.nodes.input.value = event.results[i][0].transcript;
                    } else {
                        interimTranscript += event.results[i][0].transcript;
                    }
                }
            };

            this.recognition.onerror = (event) => {
                console.error('[Voice] Error:', event.error);
            };

            this.recognition.onend = () => {
                const micBtn = document.querySelector('.brox-ai-tool-btn[data-voice]');
                if (micBtn) micBtn.classList.remove('brox-ai-recording');
            };

            // Enable voice button
            const micBtn = document.querySelector('.brox-ai-tool-btn[title="Voice Input"]');
            if (micBtn) {
                micBtn.disabled = false;
                micBtn.onclick = () => this.toggleVoiceInput();
            }
        }

        toggleVoiceInput() {
            if (!this.recognition) {
                alert(this.lang === 'bn' ? 'ভয়েস ইনপুট সমর্থিত নয়' : 'Voice input not supported');
                return;
            }

            const voiceMicBtn = document.querySelector('.brox-ai-tool-btn[title="Voice Input"]');

            if (voiceMicBtn && voiceMicBtn.classList.contains('recording')) {
                this.recognition.stop();
            } else {
                this.recognition.lang = this.lang === 'bn' ? 'bn-BD' : 'en-US';
                this.recognition.start();
                if (voiceMicBtn) voiceMicBtn.classList.add('brox-ai-recording');
            }
        }

        // ── File Attachment ───────────────────────────────────────────────────
        initFileAttachment() {
            const attachBtn = document.querySelector('.brox-ai-tool-btn[title="Attach Files"]');
            if (!attachBtn) return;

            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = 'image/*,.pdf,.doc,.docx,.txt';
            fileInput.style.display = 'none';
            document.body.appendChild(fileInput);

            attachBtn.disabled = false;
            attachBtn.onclick = () => fileInput.click();

            fileInput.onchange = (e) => {
                const file = e.target.files[0];
                if (!file) return;
                if (file.size > 5 * 1024 * 1024) {
                    alert(this.lang === 'bn' ? 'ফাইলের আকার ৫MB এর বেশি হতে পারবে না।' : 'File size must be less than 5MB.');
                    return;
                }
                this.addFileMessage(file);
                fileInput.value = '';
            };
        }

        addFileMessage(file) {
            if (!this.nodes.body) return;
            const msg = document.createElement('div');
            msg.className = 'brox-ai-msg user';
            const content = document.createElement('div');
            content.className = 'brox-ai-msg-content';
            content.innerHTML = '<div class="brox-ai-file-attachment"><i class="bi bi-file-earmark"></i><span>' + file.name + '</span><small>' + this.formatFileSize(file.size) + '</small></div>';
            msg.appendChild(content);
            const meta = document.createElement('div');
            meta.className = 'brox-ai-msg-meta';
            meta.textContent = new Date().toLocaleTimeString(this.lang === 'bn' ? 'bn-BD' : 'en-US', { hour: '2-digit', minute: '2-digit' });
            msg.appendChild(meta);
            this.nodes.body.appendChild(msg);
            this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
            this.history.push({ role: 'user', content: '[File: ' + file.name + ']', timestamp: new Date().toISOString(), isFile: true });
            this.saveHistory();
        }

        formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        // ── Events ────────────────────────────────────────────────────────────────
        bindEvents() {
            // Toggle chat open/close with icon change
            this.nodes.btn.onclick = () => {
                if (this.nodes.shell?.classList.contains('d-none')) {
                    this.nodes.shell.classList.remove('d-none');
                    setTimeout(() => this.nodes.shell.classList.remove('brox-ai-hidden'), 10);
                    // Show close icon
                    this.nodes.btn.classList.add('brox-ai-active');
                } else {
                    this.nodes.shell?.classList.add('brox-ai-hidden');
                    setTimeout(() => this.nodes.shell?.classList.add('d-none'), 300);
                    // Show open icon
                    this.nodes.btn.classList.remove('brox-ai-active');
                }
            };
            this.nodes.shell?.classList.add('brox-ai-hidden');
            setTimeout(() => this.nodes.shell?.classList.add('d-none'), 300);

            // Close button handler
            if (this.nodes.close) {
                this.nodes.close.onclick = () => {
                    this.nodes.shell?.classList.add('brox-ai-hidden');
                    setTimeout(() => this.nodes.shell?.classList.add('d-none'), 300);
                    // Show open icon on FAB button
                    this.nodes.btn?.classList.remove('brox-ai-active');
                };
            }

            if (this.nodes.toggleSidebar) {
                this.nodes.toggleSidebar.onclick = () => {
                    this.nodes.sidebar?.classList.toggle('brox-ai-collapsed');
                };
                if (this.nodes.title) {
                    this.nodes.title.style.cursor = 'pointer';
                    this.nodes.title.onclick = () => this.nodes.sidebar?.classList.toggle('brox-ai-collapsed');
                }
            }

            if (this.nodes.langBn) this.nodes.langBn.onclick = () => { this.lang = 'bn'; this.saveLang(); };
            if (this.nodes.langEn) this.nodes.langEn.onclick = () => { this.lang = 'en'; this.saveLang(); };

            if (this.nodes.send) this.nodes.send.onclick = () => this.handleSend();
            if (this.nodes.input) this.nodes.input.onkeypress = e => { if (e.key === 'Enter') this.handleSend(); };

            if (this.nodes.quickActions) {
                this.nodes.quickActions.querySelectorAll('.brox-ai-action-chip').forEach(btn => {
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
                    // Update labels when moving to contact step
                    this.updatePrechatLabels();
                };
            }
            if (this.nodes.prechatBtns.next2) {
                this.nodes.prechatBtns.next2.onclick = () => {
                    // Validate email if provided
                    const email = this.nodes.prechatInputs.email?.value.trim();
                    const mobile = this.nodes.prechatInputs.mobile?.value.trim();

                    if (email && !validateEmail(email)) {
                        alert(this.t('err_email_invalid'));
                        return;
                    }
                    if (mobile && !validateMobile(mobile)) {
                        alert(this.t('err_mobile_invalid'));
                        return;
                    }

                    this.nodes.prechatSteps.contact?.classList.add('d-none');
                    this.nodes.prechatSteps.topic?.classList.remove('d-none');
                    this.updatePrechatLabels();
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

            // Final validation
            if (email && !validateEmail(email)) {
                alert(this.t('err_email_invalid'));
                return;
            }
            if (phone && !validateMobile(phone)) {
                alert(this.t('err_mobile_invalid'));
                return;
            }

            const selected = Array.from(document.querySelectorAll('.brox-ai-topic-grid input:checked')).map(i => i.value);

            // Store only session data (not in localStorage for privacy)
            this.user = { name, email, phone, topics: selected };
            // DO NOT persist user data to localStorage - privacy concern

            this.renderChatMode();
        }

        renderChatMode() {
            this.nodes.prechat.classList.add('d-none');
            this.nodes.body.classList.remove('d-none');
            this.nodes.footer.classList.remove('d-none');
            this.nodes.quickActions?.classList.remove('d-none');
            this.nodes.body.innerHTML = '';

            const greeting = (this.lang === 'bn' ? `হ্যালো ${this.user.name}! ` : `Hello ${this.user.name}! `) + this.t('welcome');
            this.addMessage('assistant', greeting);

            // Initialize chat in history
            this.history = [];
            this.history.push({ role: 'user', content: name, timestamp: new Date().toISOString() });
            this.saveHistory();

            this.renderHistorySidebar();
        }

        async handleSend() {
            const text = this.nodes.input.value.trim();
            if (!text || this.isThinking) return;

            // Sanitize input
            const sanitized = sanitizeInput(text);
            this.nodes.input.value = '';
            this.addMessage('user', sanitized);
            this.history.push({ role: 'user', content: sanitized, timestamp: new Date().toISOString() });
            this.saveHistory();
            this.renderHistorySidebar();
            await this.getAIResponse();
        }

        // ── Message Rendering ─────────────────────────────────────────────────────
        addMessage(role, content, animate = true) {
            if (!this.nodes.body) return;
            const existing = this.nodes.body.querySelector('.brox-ai-typing');
            existing?.remove();

            const msg = document.createElement('div');
            msg.className = `brox-ai-msg ${role}`;

            const body = document.createElement('div');
            body.className = 'brox-ai-msg-content';
            msg.appendChild(body);

            if (animate && role === 'assistant') this.typeEffect(body, content);
            else this.renderMarkdown(body, content);

            const meta = document.createElement('div');
            meta.className = 'brox-ai-msg-meta';
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
            msg.className = `brox-ai-msg ${role}`;
            const body = document.createElement('div');
            body.className = 'brox-ai-msg-content';
            msg.appendChild(body);
            const meta = document.createElement('div');
            meta.className = 'brox-ai-msg-meta';
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
                const pill = this.nodes.agenticStatus.querySelector('.brox-ai-status-pill');
                if (pill) pill.textContent = pillText;
                this.nodes.statusDetail.textContent = detailText || '';
            } else {
                this.nodes.agenticStatus.classList.add('d-none');
            }
        }

        showTyping() {
            if (!this.nodes.body) return null;
            const div = document.createElement('div');
            div.className = 'brox-ai-typing';
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
                    this.history.push({ role: 'assistant', content: fullReply, timestamp: new Date().toISOString() });
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
                    this.history.push({ role: 'assistant', content: reply, timestamp: new Date().toISOString() });
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
