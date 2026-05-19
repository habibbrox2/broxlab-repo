if (!window.BroxAssistantLoaded) {
    window.BroxAssistantLoaded = true;

    // ── Auto-inject ai-style.css (no <link> tag needed in HTML) ──────────────────
    (function injectAiCSS() {
        // We auto-load the CSS dynamically via JS
        const scriptPath = document.currentScript?.src || '/ai/dist/assistant.js';
        // Strip query strings (?v=...) and replace /js/assistant.js or /dist/assistant.js with /dist/ai-style.css
        const baseUrl = scriptPath.split('?')[0];
        const cssUrl = baseUrl.replace(/\/(?:js|dist)\/assistant\.js$/, '/dist/ai-style.css');

        if (!document.querySelector(`link[href^="${cssUrl}"]`)) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = cssUrl;
            document.head.appendChild(link);
        }
    })();

    const CONFIG = {
        chatKey: 'brox.ai.history',
        sessionStateKey: 'brox.ai.session',
        userKey: 'brox.ai.visitor',
        langKey: 'brox.ai.lang',
        tokenKey: 'brox.ai.visitor_token',
        proxyUrl: '/api/ai/chat',
        sessionBootstrapUrl: '/api/ai/session',
        modelsUrl: '/api/ai/models',
        frontendSettingsUrl: '/api/ai-system/frontend',
        cryptoKeyId: 'brox.ai.crypto_key', // Stored encryption key identifier
    };

    const CACHE_TTL = 60 * 60; // 1 hour cache for AI provider/model metadata

    function getCachedJson(key) {
        try {
            const raw = localStorage.getItem(key);
            if (!raw) return null;
            const payload = JSON.parse(raw);
            if (!payload || typeof payload !== 'object') return null;
            if (typeof payload.expires !== 'number' || payload.expires < Date.now()) {
                localStorage.removeItem(key);
                return null;
            }
            return payload.data;
        } catch (e) {
            return null;
        }
    }

    function setCachedJson(key, data, ttl = CACHE_TTL) {
        try {
            const payload = {
                data,
                expires: Date.now() + (ttl * 1000)
            };
            localStorage.setItem(key, JSON.stringify(payload));
        } catch (e) {
            console.warn('[BroxCache] Failed to save cache', e);
        }
    }

    function selectFreeOrSmallModel(models) {
        if (!Array.isArray(models) || models.length === 0) return '';
        const scored = models.map(m => {
            const text = `${m.id} ${m.name}`.toLowerCase();
            let score = 0;
            if (text.includes(':free') || text.includes(' free')) score -= 200;
            if (text.includes(' auto')) score -= 150;
            if (text.includes(' mini') || text.includes(' small') || text.includes(' flash') || text.includes(' fast') || text.includes(' turbo')) score -= 100;
            return { score, id: m.id };
        });
        scored.sort((a, b) => a.score - b.score || a.id.localeCompare(b.id));
        return scored[0]?.id || '';
    }

    // ── BroxCrypto: Encryption utility using Web Crypto API (AES-GCM) ───────────────
    const BroxCrypto = (function () {
        // Generate or retrieve a device-specific encryption key
        async function getKey() {
            let keyData = localStorage.getItem(CONFIG.cryptoKeyId);

            if (keyData) {
                // Import existing key from stored bytes
                const keyBytes = Uint8Array.from(atob(keyData), c => c.charCodeAt(0));
                return await crypto.subtle.importKey(
                    'raw',
                    keyBytes,
                    { name: 'AES-GCM', length: 256, },
                    true,
                    ['encrypt', 'decrypt',]
                );
            }

            // Generate new key if none exists
            const key = await crypto.subtle.generateKey(
                { name: 'AES-GCM', length: 256, },
                true,
                ['encrypt', 'decrypt',]
            );

            // Export and store the key bytes
            const exported = await crypto.subtle.exportKey('raw', key);
            const bytes = new Uint8Array(exported);
            keyData = btoa(String.fromCharCode(...bytes));
            localStorage.setItem(CONFIG.cryptoKeyId, keyData);

            return key;
        }

        return {
            // Encrypt plaintext string, returns base64-encoded ciphertext
            async encrypt(plaintext) {
                try {
                    if (!plaintext) return null;

                    const key = await getKey();
                    const iv = crypto.getRandomValues(new Uint8Array(12)); // 96-bit IV for GCM
                    const encoder = new TextEncoder();
                    const data = encoder.encode(plaintext);

                    const ciphertext = await crypto.subtle.encrypt(
                        { name: 'AES-GCM', iv: iv, },
                        key,
                        data
                    );

                    // Combine IV + ciphertext and base64 encode
                    const combined = new Uint8Array(iv.length + ciphertext.byteLength);
                    combined.set(iv, 0);
                    combined.set(new Uint8Array(ciphertext), iv.length);

                    return btoa(String.fromCharCode(...combined));
                } catch (e) {
                    console.warn('[BroxCrypto] Encryption failed:', e);
                    return null;
                }
            },

            // Decrypt base64-encoded ciphertext, returns plaintext string
            async decrypt(encryptedBase64) {
                try {
                    if (!encryptedBase64) return null;

                    const key = await getKey();
                    const combined = Uint8Array.from(atob(encryptedBase64), c => c.charCodeAt(0));
                    const iv = combined.slice(0, 12);
                    const ciphertext = combined.slice(12);

                    const decrypted = await crypto.subtle.decrypt(
                        { name: 'AES-GCM', iv: iv, },
                        key,
                        ciphertext
                    );

                    const decoder = new TextDecoder();
                    return decoder.decode(decrypted);
                } catch (e) {
                    console.warn('[BroxCrypto] Decryption failed:', e);
                    return null;
                }
            },
        };
    })();

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
            no_history: 'এই চ্যাটটি বর্তমানে সক্রিয় আছে।',
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
            fallback: '⚠️ AI service unavailable. Please try again later.',
            reset: 'Previous chat history has been reset.',
            history_empty: 'No history',
            chat_session: 'Chat Session',
            no_history: 'This chat is currently active.',
        },
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

    // ─── Remote Model Loader ──────────────────────────────────────────────────────
    async function fetchModels(provider) {
        const cacheKey = `brox.ai.models.${provider}`;
        const cached = getCachedJson(cacheKey);
        if (cached && Array.isArray(cached.models) && cached.models.length > 0) {
            return { models: cached.models, source: cached.source || 'cache' };
        }

        try {
            const res = await fetch(`${CONFIG.modelsUrl}?provider=${encodeURIComponent(provider)}`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            if (data?.models && Array.isArray(data.models) && data.models.length > 0) {
                setCachedJson(cacheKey, { models: data.models, source: 'remote' });
                return { models: data.models, source: 'remote' };
            }
            throw new Error('No models returned');
        } catch (e) {
            console.warn('[Models] Failed to fetch model list:', e.message);
            const fallback = {
                models: [
                    { id: 'gemini-2.0-flash', name: 'Gemini 2.0 Flash', default: true, },
                    { id: 'gemini-pro-1.5', name: 'Gemini Pro 1.5', },
                    { id: 'gpt-4o-mini', name: 'GPT-4o Mini', },
                    { id: 'claude-3-haiku', name: 'Claude 3 Haiku', },
                ],
                source: 'fallback',
            };
            setCachedJson(cacheKey, fallback, 300);
            return fallback;
        }
    }

    async function fetchProviderList() {
        const cacheKey = 'brox.ai.providers';
        const cached = getCachedJson(cacheKey);
        if (cached && cached.providers && typeof cached.providers === 'object') {
            return cached;
        }

        try {
            const res = await fetch(CONFIG.modelsUrl);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            if (data && typeof data === 'object' && data.providers) {
                setCachedJson(cacheKey, { providers: data.providers, source: 'remote' });
                return { providers: data.providers, source: 'remote' };
            }
            throw new Error('No provider list returned');
        } catch (e) {
            console.warn('[Providers] Failed to fetch provider list:', e.message);
            return { providers: {}, source: 'fallback' };
        }
    }

    async function fetchFrontendSettings() {
        const cacheKey = 'brox.ai.frontend.settings';
        const cached = getCachedJson(cacheKey);
        if (cached && typeof cached === 'object') {
            return cached;
        }
        try {
            const res = await fetch(CONFIG.frontendSettingsUrl);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            if (data && typeof data === 'object') {
                setCachedJson(cacheKey, data);
                return data;
            }
            return null;
        } catch (e) {
            console.warn('[Frontend Settings] Failed to load:', e.message);
            return null;
        }
    }

    // ─── Main Class ───────────────────────────────────────────────────────────────
    class BroxAssistant {
        constructor() {
            this.lang = localStorage.getItem(CONFIG.langKey) || 'bn';
            this.history = this.loadHistory();
            this.sessionState = this.loadSessionState();
            // User profile persists, chat history is session-only
            this.user = this.loadUserProfile();
            this.visitorToken = this.getVisitorToken();
            this.conversationId = this.sessionState.conversationId || null;
            this.sessionKey = this.sessionState.sessionKey || this.generateSessionKey();
            this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            this.isThinking = false;
            this.currentProvider = 'openrouter';
            this.currentModel = null; // will be set after model list loads
            this.frontendProvider = 'openrouter';
            this.frontendModel = '';
            this.recognition = null; // Speech recognition instance
            this.idleTimer = null;
            this.isChatActive = false;
            this.modelBarOpen = false;
            this.historyStateKey = 'brox.ai.assistant.open';
            this.historyStateActive = Boolean(history.state && history.state[this.historyStateKey]);
            this.overlay = null;

            this.initUI();
            this.initOverlay();
            if (this.nodes.btn) {
                this.bindEvents();
                this.initSpeechRecognition();
                this.renderInitialState();
                this.bootstrapFrontendSettings();
                this.saveSessionState();
                this.bootstrapConversationSession();
            }

            window.addEventListener('popstate', (event) => this.handlePopState(event));
        }

        t(key) { return I18N[this.lang][key] || key; }

        getVisitorToken() {
            let token = localStorage.getItem(CONFIG.tokenKey);
            if (!token) {
                token = `vt_${Math.random().toString(36).substr(2, 9)}${Date.now().toString(36)}`;
                localStorage.setItem(CONFIG.tokenKey, token);
            }
            return token;
        }

        loadHistory() {
            try {
                const raw = sessionStorage.getItem(CONFIG.chatKey);
                if (!raw) return [];
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch {
                return [];
            }
        }

        saveHistory() {
            try {
                sessionStorage.setItem(CONFIG.chatKey, JSON.stringify(this.history));
            } catch (e) {
                console.warn('[BroxAssistant] Failed to save history:', e);
            }
        }

        generateSessionKey() {
            return `public_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 10)}`;
        }

        loadSessionState() {
            try {
                const raw = sessionStorage.getItem(CONFIG.sessionStateKey);
                if (!raw) return {};
                const parsed = JSON.parse(raw);
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch {
                return {};
            }
        }

        saveSessionState() {
            try {
                sessionStorage.setItem(CONFIG.sessionStateKey, JSON.stringify({
                    sessionKey: this.sessionKey || this.generateSessionKey(),
                    conversationId: this.conversationId || null,
                    updatedAt: Date.now(),
                }));
            } catch (e) { }
        }

        resetConversationSession() {
            this.conversationId = null;
            this.sessionKey = this.generateSessionKey();
            this.saveSessionState();
            try {
                sessionStorage.removeItem(CONFIG.chatKey);
            } catch (e) { }
        }

        async bootstrapConversationSession() {
            if (!this.sessionKey) {
                this.sessionKey = this.generateSessionKey();
                this.saveSessionState();
            }

            try {
                const resp = await fetch(CONFIG.sessionBootstrapUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', },
                    body: JSON.stringify({
                        visitorToken: this.visitorToken,
                        session_key: this.sessionKey,
                        conversation_id: this.conversationId || null,
                    }),
                });
                if (!resp.ok) return;

                const data = await resp.json();
                if (!data?.success) return;

                if (data.session_key) {
                    this.sessionKey = String(data.session_key);
                }
                if (data.conversation_id) {
                    this.conversationId = Number(data.conversation_id);
                }

                if (Array.isArray(data.messages) && this.user) {
                    const serverHistory = data.messages
                        .map(msg => ({
                            role: msg.role,
                            content: msg.content,
                            timestamp: msg.created_at || msg.timestamp || undefined,
                        }))
                        .filter(msg => ['system', 'user', 'assistant',].includes(msg.role));

                    if (serverHistory.length && (this.history.length === 0 || serverHistory.length > this.history.length)) {
                        this.history = serverHistory;
                        this.saveHistory();
                        this.renderInitialState();
                    }
                }

                this.saveSessionState();
            } catch (e) {
                // non-critical bootstrap failure
            }
        }

        loadUserProfile() {
            // Try encrypted storage first (v2026), fallback to plain
            const encrypted = localStorage.getItem(`${CONFIG.userKey}_enc`);
            if (encrypted) {
                // Async decryption - return null for now, actual load happens async
                BroxCrypto.decrypt(encrypted).then(decrypted => {
                    if (decrypted) {
                        try {
                            const data = JSON.parse(decrypted);
                            if (data && typeof data === 'object' && data.name) {
                                this.user = data;
                                // Show chat interface and add greeting
                                this.showChatInterface();
                            }
                        } catch (e) { }
                    }
                });
            }

            // Also try legacy unencrypted storage
            try {
                const raw = localStorage.getItem(CONFIG.userKey);
                if (!raw) return null;
                const data = JSON.parse(raw);
                if (!data || typeof data !== 'object') return null;
                if (!data.name) return null;
                return data;
            } catch {
                return null;
            }
        }

        showChatInterface() {
            if (!this.nodes.prechat || !this.nodes.body || !this.nodes.footer) return;
            this.nodes.prechat.classList.add('brox-ai-hidden');
            this.nodes.body.classList.remove('brox-ai-hidden');
            this.nodes.footer.classList.remove('brox-ai-hidden');
            this.nodes.modelBar?.classList.remove('brox-ai-hidden');
            this.nodes.quickActions?.classList.remove('brox-ai-hidden');
            this.isChatActive = true;
            this.nodes.body.innerHTML = '';

            // Add greeting if no history
            if (this.history.length === 0 && this.user) {
                const greeting = (this.lang === 'bn' ? `হ্যালো ${this.user.name}! ` : `Hello ${this.user.name}! `) + this.t('welcome');
                this.addMessage('assistant', greeting);
            }
            this.renderHistorySidebar();
        }

        async saveUserProfile(profile) {
            if (!profile || !profile.name) return;

            // Save encrypted (v2026)
            const encrypted = await BroxCrypto.encrypt(JSON.stringify(profile));
            if (encrypted) {
                localStorage.setItem(`${CONFIG.userKey}_enc`, encrypted);
            }

            // Also save legacy unencrypted for backward compatibility
            localStorage.setItem(CONFIG.userKey, JSON.stringify(profile));
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
                modelName: document.getElementById('publicAssistantModelName'),
                modelStatusIndicator: document.getElementById('publicAssistantModelStatusIndicator'),
                modelBar: document.getElementById('publicAssistantModelBar'),
                modelToggle: document.getElementById('publicAssistantModelToggle'),
                providerSel: document.getElementById('publicAssistantProvider'),
                modelLabel: document.getElementById('publicAssistantModelLabel'),
                agenticStatus: document.getElementById('publicAssistantAgenticStatus'),
                statusDetail: document.querySelector('.ai-status-detail'),
                body: document.getElementById('publicAssistantMessages'),
                footer: document.getElementById('publicAssistantFooter'),
                input: document.getElementById('publicAssistantInput'),
                send: document.getElementById('sendToPublicAssistant'),
                suggestions: document.getElementById('publicAssistantSuggestions'),
                prechat: document.getElementById('publicAssistantPreChat'),
                langBn: document.getElementById('publicAssistantLangBn'),
                langEn: document.getElementById('publicAssistantLangEn'),
                close: document.getElementById('closePublicAssistant'),
                modelSel: document.getElementById('publicAssistantModel'),
                quickActions: document.getElementById('publicAssistantQuickActions'),

                prechatSteps: {
                    name: document.querySelector('.brox-ai-step-name'),
                    contact: document.querySelector('.brox-ai-step-contact'),
                    topic: document.querySelector('.brox-ai-step-topic'),
                },
                prechatBtns: {
                    next1: document.getElementById('introNext1'),
                    next2: document.getElementById('introNext2'),
                    start: document.getElementById('introStartChat'),
                },
                prechatInputs: {
                    name: document.getElementById('introName'),
                    email: document.getElementById('introEmail'),
                    mobile: document.getElementById('introMobile'),
                },
            };
            if (this.nodes.btn) {
                this.updateLangUI();
                this.renderHistorySidebar();
                this.renderQuickActions();
                this.updateModelStatus('connecting');
            }
        }

        async bootstrapFrontendSettings() {
            const data = await fetchFrontendSettings();
            this.frontendProvider = data?.provider || 'openrouter';
            this.frontendModel = data?.frontend_model || data?.model || 'gemini-2.0-flash';

            // Set initial model name immediately to show it's loading
            this.currentModel = this.frontendModel;
            this.updateModelLabel();

            await this.loadProviderSelector(this.frontendProvider);
            await this.loadProviderModels(this.frontendProvider, this.frontendModel);
        }

            async loadProviderSelector(defaultProvider = 'openrouter') {
                if (!this.nodes.providerSel) return;
                const result = await fetchProviderList();
                const providers = result.providers || {};
                this.nodes.providerSel.innerHTML = '';
                const availableProviders = Object.keys(providers);
                if (!availableProviders.length) {
                    this.nodes.providerSel.classList.add('brox-ai-hidden');
                    return;
                }

                availableProviders.forEach((providerName) => {
                    const option = document.createElement('option');
                    option.value = providerName;
                    option.textContent = providerName.charAt(0).toUpperCase() + providerName.slice(1);
                    this.nodes.providerSel.appendChild(option);
                });

                this.currentProvider = availableProviders.includes(defaultProvider)
                    ? defaultProvider
                    : availableProviders[0];
                this.nodes.providerSel.value = this.currentProvider;
                this.nodes.providerSel.classList.remove('brox-ai-hidden');
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

                ['langBn', 'langEn',].forEach(k => {
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
                this.renderQuickActions();
                this.updateSuggestions();
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

            applyUserProfileToForm() {
                if (!this.user) return;
                if (this.nodes.prechatInputs.name) this.nodes.prechatInputs.name.value = this.user.name || '';
                if (this.nodes.prechatInputs.email) this.nodes.prechatInputs.email.value = this.user.email || '';
                if (this.nodes.prechatInputs.mobile) this.nodes.prechatInputs.mobile.value = this.user.phone || '';

                if (Array.isArray(this.user.topics) && this.user.topics.length) {
                    document.querySelectorAll('.brox-ai-topic-grid input').forEach((input) => {
                        input.checked = this.user.topics.includes(input.value);
                    });
                }
            }

            showTopicStep() {
                this.nodes.prechat.classList.remove('brox-ai-hidden');
                this.nodes.body.classList.add('brox-ai-hidden');
                this.nodes.footer.classList.add('brox-ai-hidden');
                this.nodes.modelBar?.classList.add('brox-ai-hidden');
                this.nodes.quickActions?.classList.add('brox-ai-hidden');
                if (this.nodes.prechatSteps.name) this.nodes.prechatSteps.name.classList.add('brox-ai-hidden');
                if (this.nodes.prechatSteps.contact) this.nodes.prechatSteps.contact.classList.add('brox-ai-hidden');
                if (this.nodes.prechatSteps.topic) this.nodes.prechatSteps.topic.classList.remove('brox-ai-hidden');
            }

            resetIdleTimer() {
                if (this.idleTimer) clearTimeout(this.idleTimer);
                if (!this.isChatActive) return;
                this.idleTimer = setTimeout(() => this.resetSessionToTopics(), 15 * 60 * 1000);
            }

            markActivity() {
                if (!this.isChatActive) return;
                this.resetIdleTimer();
            }

            resetSessionToTopics() {
                this.isChatActive = false;
                this.history = [];
                this.resetConversationSession();
                this.nodes.body.innerHTML = '';
                this.isChatActive = true;
                this.resetIdleTimer();
                this.nodes.body.classList.add('brox-ai-hidden');
                this.nodes.footer.classList.add('brox-ai-hidden');
                this.nodes.quickActions?.classList.add('brox-ai-hidden');
                this.showTopicStep();
                this.renderHistorySidebar();
                this.updateSuggestions();

                // Clear server-side image context for this session, since it is being reset.
                fetch('/api/ai/clear-image-context', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', },
                    body: JSON.stringify({ visitorToken: this.visitorToken, session_key: this.sessionKey, }),
                }).catch(() => {
                    // non-critical
                });
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
                entry.textContent = `${firstMsg.substring(0, 30)}...`;
                entry.onclick = () => {
                    window.showAlert(this.t('no_history'), this.t('chat_session'), 'info');
                };
                this.nodes.history.appendChild(entry);
            }

            // ── Initial Render ────────────────────────────────────────────────────────
            // ── GDPR Consent Management (v2026) ────────────────────────────────────────────
            initGdprConsent() {
                const modal = document.getElementById('publicAssistantGdpr');
                if (!modal) return;

                // Check if consent was already given
                const consentGiven = localStorage.getItem('brox.ai.gdpr_consent');
                if (consentGiven) {
                    modal.classList.add('brox-ai-hidden');
                    this.showPreChat();
                    return;
                }

                // Show GDPR modal
                modal.classList.remove('brox-ai-hidden');

                // Bind events
                const acceptBtn = document.getElementById('gdprAccept');
                const declineBtn = document.getElementById('gdprDecline');

                acceptBtn?.addEventListener('click', () => {
                    const dataConsent = document.getElementById('gdprConsentData')?.checked;
                    const analyticsConsent = document.getElementById('gdprConsentAnalytics')?.checked;

                    // Store consent locally
                    localStorage.setItem('brox.ai.gdpr_consent', JSON.stringify({
                        timestamp: Date.now(),
                        data: dataConsent,
                        analytics: analyticsConsent,
                    }));

                    // Send consent to server for audit trail
                    this.sendGdprConsentToServer(dataConsent, analyticsConsent);

                    modal.classList.add('brox-ai-hidden');
                    this.showPreChat();
                });

                declineBtn?.addEventListener('click', () => {
                    modal.classList.add('brox-ai-hidden');
                    // Close chat without proceeding
                    this.nodes.shell?.classList.add('brox-ai-hidden');
                    this.nodes.btn?.classList.remove('d-none');
                });
            }

            showPreChat() {
                if (!this.nodes.prechat) return;
                this.nodes.prechat.classList.remove('brox-ai-hidden');
                this.nodes.body?.classList.add('brox-ai-hidden');
                this.nodes.footer?.classList.add('brox-ai-hidden');
            }

            renderInitialState() {
                if (!this.nodes.prechat || !this.nodes.body || !this.nodes.footer) return;
                this.isChatActive = false;

                // Only clear if not currently streaming a response
                if (!this.isThinking) {
                    this.nodes.body.innerHTML = '';
                }

                if (this.user) {
                    // User exists - show chat interface
                    this.nodes.prechat.classList.add('brox-ai-hidden');
                    this.nodes.body.classList.remove('brox-ai-hidden');
                    this.nodes.footer.classList.remove('brox-ai-hidden');
                    this.nodes.modelBar?.classList.remove('brox-ai-hidden');
                    this.nodes.quickActions?.classList.remove('brox-ai-hidden');
                    this.isChatActive = true;
                    this.applyUserProfileToForm();

                    // Add greeting if no history
                    if (this.history.length === 0) {
                        const greeting = (this.lang === 'bn' ? `হ্যালো ${this.user.name}! ` : `Hello ${this.user.name}! `) + this.t('welcome');
                        this.addMessage('assistant', greeting);
                    } else {
                        // Only add messages that haven't been rendered yet (check data attribute)
                        const renderedMessages = new Set();
                        this.nodes.body.querySelectorAll('[data-rendered-to-dom]').forEach(el => {
                            const content = el.querySelector('.brox-ai-msg-content')?.innerText || '';
                            renderedMessages.add(content.substring(0, 50));
                        });

                        this.history.forEach(m => {
                            const msgPreview = m.content.substring(0, 50);
                            if (!renderedMessages.has(msgPreview)) {
                                this.addMessage(m.role, m.content, false);
                            }
                        });
                    }
                    this.renderHistorySidebar();
                    return;
                }

                // No user - show prechat form
                this.nodes.prechat.classList.remove('brox-ai-hidden');
                this.nodes.body.classList.add('brox-ai-hidden');
                this.nodes.footer.classList.add('brox-ai-hidden');
                this.nodes.modelBar?.classList.add('brox-ai-hidden');
                this.nodes.quickActions?.classList.add('brox-ai-hidden');
                if (this.nodes.prechatSteps.name) this.nodes.prechatSteps.name.classList.remove('brox-ai-hidden');
                if (this.nodes.prechatSteps.contact) this.nodes.prechatSteps.contact.classList.add('brox-ai-hidden');
                if (this.nodes.prechatSteps.topic) this.nodes.prechatSteps.topic.classList.add('brox-ai-hidden');
                this.renderHistorySidebar();
            }

        // ── Remote Model Loading ──────────────────────────────────────────────────
        async loadProviderModels(provider = 'openrouter', preferredModel = '') {
                if (!this.nodes.modelSel) return;

                this.currentProvider = provider || this.currentProvider || 'openrouter';
                if (this.nodes.providerSel) {
                    this.nodes.providerSel.value = this.currentProvider;
                }

                this.updateModelStatus('connecting');
                const result = await fetchModels(this.currentProvider);
                const models = result?.models || [];
                this.providerModels = models;

                if (this._modelChangeHandler) {
                    this.nodes.modelSel.removeEventListener('change', this._modelChangeHandler);
                    this._modelChangeHandler = null;
                }
                if (!models.length) {
                    this.nodes.modelSel.classList.add('brox-ai-hidden');
                    // Try to use frontend model as fallback
                    if (this.frontendModel) {
                        this.currentModel = this.frontendModel;
                        this.updateModelLabel();
                        this.updateModelStatus('online');
                    } else {
                        this.updateModelLabel();
                        this.updateModelStatus('offline');
                    }
                    return;
                }

                this.nodes.modelSel.innerHTML = '';
                let hasPreferred = false;
                models.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    const shortLabel = this.mapModelLabel(m.id, m.name);
                    opt.textContent = shortLabel + (m.id.endsWith(':free') ? ' (Free)' : '');
                    if (preferredModel && preferredModel === m.id) {
                        opt.selected = true;
                        hasPreferred = true;
                    } else if (m.default && !hasPreferred) {
                        opt.selected = true;
                    }
                    this.nodes.modelSel.appendChild(opt);
                });

                const defaultOpt = hasPreferred
                    ? models.find(m => m.id === preferredModel)
                    : models.find(m => m.default);
                const autoDefault = selectFreeOrSmallModel(models);
                this.currentModel = defaultOpt ? defaultOpt.id : (autoDefault || models[0].id);
                if (this.currentModel && this.nodes.modelSel) {
                    this.nodes.modelSel.value = this.currentModel;
                }
                this.ensureModelOption(this.currentModel);
                this.nodes.modelSel.classList.remove('d-none');
                this.nodes.modelSel.classList.remove('brox-ai-hidden');
                this.updateModelLabel();

                this.updateModelStatus(result?.source === 'fallback' ? 'offline' : 'online');

                this._modelChangeHandler = () => {
                    this.currentModel = this.nodes.modelSel.value;
                    this.updateModelLabel();
                };
                this.nodes.modelSel.addEventListener('change', this._modelChangeHandler);

                if (this.nodes.providerSel) {
                    this.nodes.providerSel.onchange = () => {
                        const selected = this.nodes.providerSel.value;
                        if (selected && selected !== this.currentProvider) {
                            this.currentProvider = selected;
                            this.loadProviderModels(this.currentProvider, '');
                        }
                    };
                }
            }

            // ── Speech Recognition ─────────────────────────────────────────────────
            initSpeechRecognition() {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SpeechRecognition) {
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
                    const micBtn = document.querySelector('.brox-ai-tool-btn[title="Voice Input"]');
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
                    window.showAlert(this.lang === 'bn' ? 'ভয়েস ইনপুট সমর্থিত নয়' : 'Voice input not supported', 'Voice Input', 'warning');
                    return;
                }

                const voiceMicBtn = document.querySelector('.brox-ai-tool-btn[title="Voice Input"]');

                if (voiceMicBtn && voiceMicBtn.classList.contains('brox-ai-recording')) {
                    this.recognition.stop();
                } else {
                    this.recognition.lang = this.lang === 'bn' ? 'bn-BD' : 'en-US';
                    this.recognition.start();
                    if (voiceMicBtn) voiceMicBtn.classList.add('brox-ai-recording');
                }
            }

            // ── Events ────────────────────────────────────────────────────────────────
            bindEvents() {
                // Toggle chat open/close with icon change
                this.nodes.btn.onclick = () => {
                    if (this.nodes.shell?.classList.contains('brox-ai-hidden')) {
                        this.openAssistant();
                    } else {
                        this.closeAssistant();
                    }
                };
                this.nodes.shell?.classList.add('brox-ai-hidden');

                // Close button handler
                if (this.nodes.close) {
                    this.nodes.close.onclick = () => {
                        this.closeAssistant();
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
                if (this.nodes.input) {
                    this.nodes.input.onkeypress = e => { if (e.key === 'Enter') this.handleSend(); };
                    this.nodes.input.oninput = () => {
                        this.updateSuggestions();
                        this.markActivity();
                    };
                    this.nodes.input.addEventListener('keydown', (e) => {
                        if (e.key === 'Tab' && !e.shiftKey && this.nodes.suggestions && !this.nodes.suggestions.classList.contains('brox-ai-hidden')) {
                            const firstSuggestion = this.nodes.suggestions.querySelector('.brox-ai-suggestion-chip');
                            if (firstSuggestion && firstSuggestion.dataset.prompt) {
                                e.preventDefault();
                                this.nodes.input.value = firstSuggestion.dataset.prompt;
                                this.updateSuggestions();
                            }
                        }
                    });
                }

                if (this.nodes.quickActions) {
                    this.nodes.quickActions.onclick = (e) => {
                        const btn = e.target.closest('.brox-ai-action-chip');
                        if (!btn) return;
                        this.nodes.input.value = btn.dataset.prompt || '';
                        this.handleSend();
                    };
                }

                if (this.nodes.suggestions) {
                    this.nodes.suggestions.onclick = (e) => {
                        const btn = e.target.closest('.brox-ai-suggestion-chip');
                        if (!btn) return;
                        this.nodes.input.value = btn.dataset.prompt || '';
                        this.nodes.input.focus();
                        this.updateSuggestions();
                        this.markActivity();
                    };
                }

                if (this.nodes.prechatBtns.next1) {
                    this.nodes.prechatBtns.next1.onclick = () => {
                        if (!this.nodes.prechatInputs.name?.value.trim()) { window.showAlert(this.t('err_name'), 'Validation Error', 'warning'); return; }
                        this.nodes.prechatSteps.name?.classList.add('brox-ai-hidden');
                        this.nodes.prechatSteps.contact?.classList.remove('brox-ai-hidden');
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
                            window.showAlert(this.t('err_email_invalid'), 'Validation Error', 'warning');
                            return;
                        }
                        if (mobile && !validateMobile(mobile)) {
                            window.showAlert(this.t('err_mobile_invalid'), 'Validation Error', 'warning');
                            return;
                        }

                        this.nodes.prechatSteps.contact?.classList.add('brox-ai-hidden');
                        this.nodes.prechatSteps.topic?.classList.remove('brox-ai-hidden');
                        this.updatePrechatLabels();
                    };
                }
                if (this.nodes.prechatBtns.start) {
                    this.nodes.prechatBtns.start.onclick = () => this.startChat();
                }

                if (this.nodes.modelToggle) {
                    this.nodes.modelToggle.onclick = () => this.toggleModelBar();
                }

                document.addEventListener('pointerdown', (e) => {
                    if (!this.nodes.shell || !this.nodes.btn) return;
                    if (this.nodes.shell.classList.contains('brox-ai-hidden')) return;
                    const path = e.composedPath ? e.composedPath() : [];
                    const clickedInside = path.includes(this.nodes.shell) || path.includes(this.nodes.btn)
                        || this.nodes.shell.contains(e.target) || this.nodes.btn.contains(e.target);
                    if (clickedInside) return;
                    this.nodes.shell.classList.add('brox-ai-hidden');
                    this.nodes.btn.classList.remove('brox-ai-active');
                });

                document.addEventListener('pointerdown', (e) => {
                    if (!this.nodes.modelBar || this.nodes.modelBar.classList.contains('brox-ai-collapsed')) return;
                    const path = e.composedPath ? e.composedPath() : [];
                    const clickedInside = path.includes(this.nodes.modelBar) || this.nodes.modelBar.contains(e.target);
                    if (clickedInside) return;
                    this.closeModelBar();
                });
            }

            initOverlay() {
                const existing = document.getElementById('publicAssistantOverlay');
                if (existing) {
                    this.overlay = existing;
                    return;
                }
                const overlay = document.createElement('div');
                overlay.id = 'publicAssistantOverlay';
                overlay.className = 'brox-ai-overlay';
                overlay.addEventListener('click', () => this.closeAssistant());
                document.body.appendChild(overlay);
                this.overlay = overlay;
            }

            openAssistant(options = {}) {
                if (!this.nodes.shell) return;
                if (!this.nodes.shell.classList.contains('brox-ai-hidden')) return;

                this.nodes.shell.classList.remove('brox-ai-hidden');
                this.nodes.btn?.classList.add('brox-ai-active');
                this.overlay?.classList.add('brox-ai-overlay-active');

                if (!options.skipHistory) {
                    this.pushHistoryState();
                }

                // Check GDPR consent on first open
                const consentGiven = localStorage.getItem('brox.ai.gdpr_consent');
                if (!consentGiven) {
                    this.initGdprConsent();
                } else if (this.user) {
                    // User exists, show chat
                    this.nodes.prechat?.classList.add('brox-ai-hidden');
                    this.nodes.body?.classList.remove('brox-ai-hidden');
                    this.nodes.footer?.classList.remove('brox-ai-hidden');
                    this.isChatActive = true;
                } else {
                    // No user, show pre-chat
                    this.showPreChat();
                }
                this.markActivity();
            }

            closeAssistant(options = {}) {
                if (!this.nodes.shell) return;
                this.nodes.shell.classList.add('brox-ai-hidden');
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
                        this.openAssistant({ skipHistory: true, });
                    }
                    return;
                }

                if (this.nodes.shell && !this.nodes.shell.classList.contains('brox-ai-hidden')) {
                    this.closeAssistant({ fromPop: true, });
                }
            }

            // ── Status Management ─────────────────────────────────────────────────────
            updateStatus(status, text) {
                const statusDot = document.getElementById('publicAssistantStatusIndicator');
                if (statusDot) {
                    statusDot.classList.remove('brox-ai-online', 'brox-ai-offline', 'brox-ai-connecting', 'brox-ai-thinking');
                    if (status === 'ready' || status === 'online') {
                        statusDot.classList.add('brox-ai-online');
                    } else if (status === 'offline' || status === 'error') {
                        statusDot.classList.add('brox-ai-offline');
                    } else if (status === 'thinking') {
                        statusDot.classList.add('brox-ai-thinking');
                    } else {
                        statusDot.classList.add('brox-ai-connecting');
                    }
                }
                if (this.nodes.status && text) {
                    this.nodes.status.textContent = text;
                }
            }

            startChat() {
                const name = this.nodes.prechatInputs.name?.value.trim();
                if (!name) { window.showAlert(this.t('err_name'), 'Validation Error', 'warning'); return; }
                const email = this.nodes.prechatInputs.email?.value.trim() || '';
                const phone = this.nodes.prechatInputs.mobile?.value.trim() || '';

                // Clear any cached image context when starting a fresh chat
                fetch('/api/ai/clear-image-context', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', },
                    body: JSON.stringify({ visitorToken: this.visitorToken, session_key: this.sessionKey, }),
                }).catch(() => {
                    // non-critical
                });

                // Final validation
                if (email && !validateEmail(email)) {
                    window.showAlert(this.t('err_email_invalid'), 'Validation Error', 'warning');
                    return;
                }
                if (phone && !validateMobile(phone)) {
                    window.showAlert(this.t('err_mobile_invalid'), 'Validation Error', 'warning');
                    return;
                }

                const selected = Array.from(document.querySelectorAll('.brox-ai-topic-grid input:checked')).map(i => i.value);

                this.user = { name, email, phone, topics: selected, };
                this.saveUserProfile(this.user);
                this.resetConversationSession();

                this.renderChatMode();
            }

            renderChatMode() {
                this.nodes.prechat.classList.add('brox-ai-hidden');
                this.nodes.body.classList.remove('brox-ai-hidden');
                this.nodes.footer.classList.remove('brox-ai-hidden');
                this.nodes.modelBar?.classList.remove('brox-ai-hidden');
                this.nodes.quickActions?.classList.remove('brox-ai-hidden');
                this.nodes.body.innerHTML = '';
                this.isChatActive = true;
                this.resetIdleTimer();

                const greeting = (this.lang === 'bn' ? `হ্যালো ${this.user.name}! ` : `Hello ${this.user.name}! `) + this.t('welcome');
                this.addMessage('assistant', greeting);

                // Initialize chat in history
                this.history = [];
                this.history.push({ role: 'user', content: this.user.name, timestamp: new Date().toISOString(), });
                this.saveHistory();
                this.saveSessionState();

                this.renderHistorySidebar();
                this.renderQuickActions();
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

        async handleSend() {
                const text = this.nodes.input.value.trim();
                if (!text || this.isThinking) return;

                // Sanitize input
                const sanitized = sanitizeInput(text);
                this.nodes.input.value = '';
                this.addMessage('user', sanitized);
                this.history.push({ role: 'user', content: sanitized, timestamp: new Date().toISOString(), });
                this.saveHistory();
                this.renderHistorySidebar();
                this.renderQuickActions();
                this.markActivity();
                await this.getAIResponse();
            }

            // ── Message Rendering ─────────────────────────────────────────────────────
            addMessage(role, content, animate = true, messageId = null) {
                if (!this.nodes.body) return;
                const existing = this.nodes.body.querySelector('.brox-ai-typing');
                existing?.remove();

                // Prevent duplicate assistant messages - check if last message has same content
                if (role === 'assistant') {
                    const lastMsg = this.nodes.body.querySelector('.brox-ai-assistant:last-child');
                    if (lastMsg) {
                        const lastContent = lastMsg.querySelector('.brox-ai-msg-content')?.innerText || '';
                        const newContent = content.split('\n')[0]; // First line of new content
                        if (lastContent.includes(newContent)) {
                            console.warn('[BroxAssistant] Skipping duplicate assistant message');
                            return;
                        }
                    }
                }

                // Check for incomplete response (e.g., ends with numbered list like "3.")
                const isIncomplete = this.isResponseIncomplete(content);
                if (isIncomplete) {
                    content += '\n\n⚠️ *Response was truncated. Please try again or ask a more specific question.*';
                }

                const msg = document.createElement('div');
                msg.className = `brox-ai-msg brox-ai-${role}`;

                const body = document.createElement('div');
                body.className = 'brox-ai-msg-content';
                msg.appendChild(body);

                if (animate && role === 'assistant') this.typeEffect(body, content);
                else this.renderMarkdown(body, content);

                const meta = document.createElement('div');
                meta.className = 'brox-ai-msg-meta';
                meta.textContent = new Date().toLocaleTimeString(this.lang === 'bn' ? 'bn-BD' : 'en-US', { hour: '2-digit', minute: '2-digit', });
                msg.appendChild(meta);

                // Add feedback for assistant messages
                if (role === 'assistant') {
                    // Create actions container for feedback + copy + timestamp
                    const actions = document.createElement('div');
                    actions.className = 'brox-ai-msg-actions';

                    const feedback = document.createElement('div');
                    feedback.className = 'brox-ai-feedback';
                    feedback.innerHTML = `
                    <button class="brox-ai-feedback-btn" data-rating="1" title="Poor"><i class="bi bi-hand-thumbs-down"></i></button>
                    <button class="brox-ai-feedback-btn" data-rating="5" title="Excellent"><i class="bi bi-hand-thumbs-up"></i></button>
                `;

                    // Add copy button
                    const copyBtn = document.createElement('button');
                    copyBtn.className = 'brox-ai-copy-btn';
                    copyBtn.title = this.lang === 'bn' ? 'কপি করুন' : 'Copy';
                    copyBtn.innerHTML = '<i class="bi bi-clipboard"></i>';
                    copyBtn.addEventListener('click', async () => {
                        try {
                            await navigator.clipboard.writeText(content);
                            copyBtn.innerHTML = '<i class="bi bi-check2"></i>';
                            setTimeout(() => {
                                copyBtn.innerHTML = '<i class="bi bi-clipboard"></i>';
                            }, 2000);
                        } catch (e) {
                            console.error('Copy failed', e);
                        }
                    });

                    // Move meta to actions container (beside feedback + copy)
                    meta.style.marginTop = '0';

                    // Append all to actions container
                    actions.appendChild(feedback);
                    actions.appendChild(copyBtn);
                    actions.appendChild(meta);
                    msg.appendChild(actions);

                    // Store messageId for feedback
                    msg.dataset.messageId = messageId || 0;

                    // Add event listeners
                    feedback.querySelectorAll('.brox-ai-feedback-btn').forEach(btn => {
                        btn.addEventListener('click', async () => {
                            const rating = btn.dataset.rating;
                            const msgId = msg.dataset.messageId;
                            const convId = this.conversationId || 'guest';
                            const csrfToken = this.csrfToken || '';
                            try {
                                const resp = await fetch('/api/ai/feedback', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', },
                                    body: JSON.stringify({
                                        conversation_id: convId,
                                        message_id: msgId,
                                        rating: rating,
                                        csrf_token: csrfToken,
                                    }),
                                });
                                const result = await resp.json();
                                if (result.success) {
                                    feedback.innerHTML = '<small>ধন্যবাদ!</small>';
                                }
                            } catch (e) {
                                console.error('Feedback submission failed', e);
                            }
                        });
                    });
                }

                this.nodes.body.appendChild(msg);
                this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
            }

            // ── Check for Incomplete Response ───────────────────────────────────────────────
            isResponseIncomplete(text) {
                if (!text || typeof text !== 'string') return false;
                const trimmed = text.trim();

                // Check if ends with incomplete numbered list (e.g., "3." or "3)" or "3 -")
                if (/\d+[.)\-\s]*$/i.test(trimmed)) return true;

                // Check if ends with incomplete bullet point
                if (/[-*•]\s*$/.test(trimmed)) return true;

                // Short responses are often valid, so do not treat them as truncated.
                if (trimmed.length < 20) {
                    return false;
                }

                // Check if ends with incomplete sentence (no period, question mark, or exclamation)
                const lastChar = trimmed.slice(-1);
                if (!/[.?!]$/.test(lastChar) && trimmed.length > 75) {
                    // Only flag as incomplete if it looks like it was cut mid-sentence
                    if (/\s(?:to|and|the|a|is|are|was|were|have|has|had|will|would|could|should|may|might|must|for|with|that|which|when|where|who|whom|whose|because|since|although|before|after|until|while)$/i.test(trimmed)) {
                        return true;
                    }
                }

                return false;
            }

            // ── Markdown Rendering with marked.js + highlight.js (v2026) ──────────────────
            renderMarkdown(el, text) {
                if (!text) return;

                const escapedText = text
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');

                // Check if marked/highlight are available, otherwise use fallback
                if (typeof marked !== 'undefined' && typeof hljs !== 'undefined') {
                    try {
                        // Configure marked with highlight.js
                        marked.setOptions({
                            highlight: function (code, lang) {
                                if (lang && hljs.getLanguage(lang)) {
                                    return hljs.highlight(code, { language: lang, }).value;
                                }
                                return hljs.highlightAuto(code).value;
                            },
                            breaks: true,
                            gfm: true,
                        });
                        el.innerHTML = marked.parse(escapedText);
                        return;
                    } catch (e) {
                        console.warn('[Markdown] marked.js failed, using fallback:', e.message);
                    }
                }

                // Fallback: Basic markdown rendering
                let html = text
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\*(.*?)\*/g, '<em>$1</em>')
                    .replace(/`(.*?)`/g, '<code>$1</code>')
                    .replace(/\n/g, '<br>');

                // Code blocks with language
                html = html.replace(/```(\w*)\n([\s\S]*?)```/g, (m, lang, code) => {
                    const language = lang.trim() || 'plaintext';
                    return `<pre><code class="language-${language}">${code.trim()}</code></pre>`;
                });

                // Links
                html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');

                // Lists
                html = html.replace(/^\s*[-*]\s+/gm, '<li>');
                html = html.replace(/(<li>.*)/g, '<ul>$1</ul>');

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
                msg.className = `brox-ai-msg brox-ai-${role}`;
                const body = document.createElement('div');
                body.className = 'brox-ai-msg-content';
                msg.appendChild(body);
                const meta = document.createElement('div');
                meta.className = 'brox-ai-msg-meta';
                meta.textContent = new Date().toLocaleTimeString(this.lang === 'bn' ? 'bn-BD' : 'en-US', { hour: '2-digit', minute: '2-digit', });

                // Keep UX consistent with addMessage(): add feedback + copy actions for assistant bubbles
                if (role === 'assistant') {
                    const actions = document.createElement('div');
                    actions.className = 'brox-ai-msg-actions';

                    const feedback = document.createElement('div');
                    feedback.className = 'brox-ai-feedback';
                    feedback.innerHTML = `
                    <button class="brox-ai-feedback-btn" data-rating="1" title="Poor"><i class="bi bi-hand-thumbs-down"></i></button>
                    <button class="brox-ai-feedback-btn" data-rating="5" title="Excellent"><i class="bi bi-hand-thumbs-up"></i></button>
                `;

                    const copyBtn = document.createElement('button');
                    copyBtn.className = 'brox-ai-copy-btn';
                    copyBtn.title = this.lang === 'bn' ? 'কপি করুন' : 'Copy';
                    copyBtn.innerHTML = '<i class="bi bi-clipboard"></i>';
                    copyBtn.addEventListener('click', async () => {
                        try {
                            const textToCopy = body.innerText || body.textContent || '';
                            await navigator.clipboard.writeText(textToCopy);
                            copyBtn.innerHTML = '<i class="bi bi-check2"></i>';
                            setTimeout(() => {
                                copyBtn.innerHTML = '<i class="bi bi-clipboard"></i>';
                            }, 2000);
                        } catch (e) {
                            console.error('Copy failed', e);
                        }
                    });

                    meta.style.marginTop = '0';
                    actions.appendChild(feedback);
                    actions.appendChild(copyBtn);
                    actions.appendChild(meta);
                    msg.appendChild(actions);
                    // messageId is injected from SSE meta (see sendMessage stream parser)
                    feedback.querySelectorAll('.brox-ai-feedback-btn').forEach(btn => {
                        btn.addEventListener('click', async () => {
                            const rating = btn.dataset.rating;
                            const msgId = msg.dataset.messageId || '';
                            const convId = this.conversationId || '';
                            const csrfToken = this.csrfToken || '';
                            if (!convId || !msgId) return;
                            try {
                                const resp = await fetch('/api/ai/feedback', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', },
                                    body: JSON.stringify({
                                        conversation_id: convId,
                                        message_id: msgId,
                                        rating: rating,
                                        csrf_token: csrfToken,
                                    }),
                                });
                                const result = await resp.json();
                                if (result.success) {
                                    feedback.innerHTML = '<small>ধন্যবাদ!</small>';
                                }
                            } catch (e) {
                                console.error('Feedback submission failed', e);
                            }
                        });
                    });
                } else {
                    msg.appendChild(meta);
                }
                // Mark wrapper to prevent duplicate rendering
                msg.dataset.renderedToDom = 'true';
                this.nodes.body.appendChild(msg);
                this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
                return body;
            }

            updateAgenticStatus(pillText, detailText) {
                if (!this.nodes.agenticStatus || !this.nodes.statusDetail) return;
                if (pillText) {
                    this.nodes.agenticStatus.classList.remove('brox-ai-hidden');
                    const pill = this.nodes.agenticStatus.querySelector('.brox-ai-status-pill');
                    if (pill) pill.textContent = pillText;
                    this.nodes.statusDetail.textContent = detailText || '';
                } else {
                    this.nodes.agenticStatus.classList.add('brox-ai-hidden');
                }
            }

            extractToolNamesFromMeta(autoToolCalls) {
                if (!Array.isArray(autoToolCalls)) return [];
                const names = [];
                autoToolCalls.forEach((round) => {
                    const calls = Array.isArray(round?.calls) ? round.calls : [];
                    calls.forEach((call) => {
                        const name = String(call?.name || '').trim();
                        if (name && !names.includes(name)) names.push(name);
                    });
                });
                return names;
            }

            describeToolUsage(toolNames) {
                const map = this.lang === 'bn'
                    ? {
                        web_search: { label: 'Searching', detail: 'ওয়েব থেকে তথ্য খোঁজা হচ্ছে...', },
                        read_file: { label: 'Reading File', detail: 'লোকাল ফাইল পড়া হচ্ছে...', },
                        analyze_image: { label: 'Analyzing Image', detail: 'ছবি বিশ্লেষণ করা হচ্ছে...', },
                        fetch_url_content: { label: 'Browsing URL', detail: 'ওয়েবপেজ কনটেন্ট আনা হচ্ছে...', },
                        search_knowledge_base: { label: 'Searching KB', detail: 'নলেজ বেসে খোঁজা হচ্ছে...', },
                    }
                    : {
                        web_search: { label: 'Searching Web', detail: 'Looking up web results...', },
                        read_file: { label: 'Reading File', detail: 'Reading local project files...', },
                        analyze_image: { label: 'Analyzing Image', detail: 'Inspecting the image and OCR text...', },
                        fetch_url_content: { label: 'Browsing URL', detail: 'Fetching page content...', },
                        search_knowledge_base: { label: 'Searching KB', detail: 'Searching the knowledge base...', },
                    };
                const primary = toolNames.find((name) => map[name]) || toolNames[0] || '';
                const meta = map[primary] || (this.lang === 'bn'
                    ? { label: 'Calling Tools', detail: 'সহায়ক টুল চালানো হচ্ছে...', }
                    : { label: 'Calling Tools', detail: 'Running assistant tools...', });
                return {
                    label: meta.label,
                    detail: meta.detail,
                    summary: toolNames.map((name) => name.replace(/_/g, ' ')).join(', '),
                };
            }

            applyLiveToolStatus(autoToolCalls, thinkingWrap = null) {
                const toolNames = this.extractToolNamesFromMeta(autoToolCalls);
                if (!toolNames.length) return;
                const meta = this.describeToolUsage(toolNames);
                try { this.updateAgenticStatus(meta.label, meta.detail); } catch { }
                if (thinkingWrap) {
                    const label = thinkingWrap.querySelector('[data-brox-ai-stage-label]');
                    const sub = thinkingWrap.querySelector('[data-brox-ai-stage-sub]');
                    const icon = thinkingWrap.querySelector('[data-brox-ai-stage-icon]');
                    if (label) label.textContent = meta.label;
                    if (sub) sub.textContent = `${meta.detail} ${meta.summary ? `(${meta.summary})` : ''}`.trim();
                    if (icon) icon.className = 'bi bi-gear';
                    thinkingWrap.dataset.stage = 'calling';
                }
                const statusText = this.lang === 'bn'
                    ? `Using tools: ${meta.summary}`
                    : `Using tools: ${meta.summary}`;
                this.updateStatus('thinking', statusText);
            }

            showTyping() {
                if (!this.nodes.body) return null;
                const div = document.createElement('div');
                div.className = 'brox-ai-typing brox-ai-thinking-dots';
                div.innerHTML = '<span></span><span></span><span></span>';
                this.nodes.body.appendChild(div);
                this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
                return div;
            }

        // ── Auto Model Selection for Faster Responses ──────────────────────────────
        async autoSelectModel() {
                const lastMsg = this.history.filter(m => m.role === 'user').pop();
                if (!lastMsg?.content) return;

                if (!Array.isArray(this.providerModels) || this.providerModels.length === 0) {
                    return;
                }

                const currentId = this.currentModel || this.nodes.modelSel?.value || '';
                const available = this.providerModels.map(m => m.id);
                if (currentId && available.includes(currentId)) {
                    return;
                }

                const preferred = selectFreeOrSmallModel(this.providerModels);
                if (preferred && preferred !== this.currentModel) {
                    this.currentModel = preferred;
                    this.updateModelLabel();
                    if (this.nodes.modelSel) {
                        this.nodes.modelSel.value = preferred;
                    }
                }
            }

        async getAIResponse() {
                this.isThinking = true;
                const t0 = performance.now();
                this.updateLangUI();
                this.updateModelStatus('connecting');
                this.updateAgenticStatus('Thinking', 'নলেজ বেস চেক করছি...');
                this.markActivity();
                // Create assistant message bubble immediately and show "thinking" inside it
                const msgBubble = this.createEmptyMessage('assistant');
                const msgWrapper = msgBubble?.parentElement;

                let stageTimer = null;
                const stageMeta = this.lang === 'bn'
                    ? {
                        thinking: { label: 'Thinking', sub: 'উত্তর সাজাচ্ছি...', icon: 'bi-stars', },
                        searching: { label: 'Searching', sub: 'প্রাসঙ্গিক তথ্য খুঁজছি...', icon: 'bi-search', },
                        calling: { label: 'Calling', sub: 'টুল/সিস্টেম কল চলছে...', icon: 'bi-gear', },
                        writing: { label: 'Writing', sub: 'সম্পূর্ণ উত্তর লিখছি...', icon: 'bi-pencil-square', },
                    }
                    : {
                        thinking: { label: 'Thinking', sub: 'Working on your request...', icon: 'bi-stars', },
                        searching: { label: 'Searching', sub: 'Looking up relevant context...', icon: 'bi-search', },
                        calling: { label: 'Calling', sub: 'Running tools/actions...', icon: 'bi-gear', },
                        writing: { label: 'Writing', sub: 'Composing the final answer...', icon: 'bi-pencil-square', },
                    };

                const detectInitialStage = () => {
                    try {
                        for (let i = this.history.length - 1; i >= 0; i--) {
                            const m = this.history[i];
                            if (m?.role !== 'user') continue;
                            const c = m.content;
                            if (typeof c === 'string' && /https?:\/\//i.test(c)) return 'searching';
                            break;
                        }
                    } catch { }
                    return 'thinking';
                };

                const setStage = (wrap, stageKey) => {
                    if (!wrap) return;
                    const meta = stageMeta[stageKey] || stageMeta.thinking;
                    wrap.dataset.stage = stageKey;
                    const icon = wrap.querySelector('[data-brox-ai-stage-icon]');
                    const label = wrap.querySelector('[data-brox-ai-stage-label]');
                    const sub = wrap.querySelector('[data-brox-ai-stage-sub]');
                    if (icon) icon.className = `bi ${meta.icon}`;
                    if (label) label.textContent = meta.label;
                    if (sub) sub.textContent = meta.sub;
                    try { this.updateAgenticStatus(meta.label, meta.sub); } catch { }
                };

                const showThinkingInBubble = () => {
                    if (!msgBubble || !msgWrapper) return;
                    msgWrapper.classList.add('brox-ai-thinking-msg');
                    msgBubble.innerHTML = `
                    <div class="brox-ai-thinking-wrap" aria-live="polite" aria-busy="true">
                        <div class="brox-ai-progress-pill" role="status">
                            <span class="brox-ai-progress-icon" aria-hidden="true"><i data-brox-ai-stage-icon class="bi bi-stars"></i></span>
                            <span class="brox-ai-thinking-text" data-brox-ai-stage-label>${this.t('thinking')}</span>
                            <span class="brox-ai-thinking-dots" aria-hidden="true"><span></span><span></span><span></span></span>
                        </div>
                        <div class="brox-ai-progress-sub" data-brox-ai-stage-sub></div>
                        <div class="brox-ai-progress-bar" aria-hidden="true"></div>
                        <div class="brox-ai-thinking-skeleton" aria-hidden="true">
                            <span class="brox-ai-skel-line skel-1"></span>
                            <span class="brox-ai-skel-line skel-2"></span>
                            <span class="brox-ai-skel-line skel-3"></span>
                        </div>
                    </div>
                `;

                    const wrap = msgBubble.querySelector('.brox-ai-thinking-wrap');
                    const initial = detectInitialStage();
                    setStage(wrap, initial);

                    const sequence = initial === 'searching'
                        ? ['searching', 'calling', 'writing',]
                        : ['thinking', 'searching', 'calling', 'writing',];
                    let idx = 0;

                    if (stageTimer) {
                        try { clearInterval(stageTimer); } catch { }
                        stageTimer = null;
                    }
                    stageTimer = setInterval(() => {
                        if (thinkingCleared) return;
                        idx = (idx + 1) % sequence.length;
                        setStage(wrap, sequence[idx]);
                    }, 2200);
                };

                let thinkingCleared = false;
                const clearThinking = () => {
                    if (!msgBubble || !msgWrapper) return;
                    if (thinkingCleared) return;
                    thinkingCleared = true;
                    if (stageTimer) {
                        try { clearInterval(stageTimer); } catch { }
                        stageTimer = null;
                    }
                    msgWrapper.classList.remove('brox-ai-thinking-msg');
                    msgBubble.innerHTML = '';
                };

                showThinkingInBubble();
                try {
                    // Auto-select best model based on query before sending
                    await this.autoSelectModel();
                    const payload = {
                        messages: this.history,
                        visitorToken: this.visitorToken,
                        conversation_id: this.conversationId || null,
                        session_key: this.sessionKey,
                        context: this.user,
                        stream: true,
                        csrf_token: this.csrfToken || '',
                    };
                    if (this.currentProvider) payload.provider = this.currentProvider;
                    if (this.currentModel) payload.model = this.currentModel;
                    const fetchChat = async () => fetch(CONFIG.proxyUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', },
                        body: JSON.stringify(payload),
                    });
                    const resp = await fetchChat();
                    this.updateAgenticStatus('Agentic', 'উত্তর জেনারেট করছি...');
                    if (!resp.ok) {
                        this.updateAgenticStatus(null);
                        clearThinking();
                        let errData = null;
                        try {
                            const ct = (resp.headers.get('content-type') || '').toLowerCase();
                            if (ct.includes('application/json')) {
                                errData = await resp.json();
                            } else if (ct.includes('text/html')) {
                                errData = { error: resp.status === 502 ? 'Bad Gateway' : resp.statusText || `Server error (${resp.status})`, };
                            } else {
                                errData = { error: (await resp.text()) || null, };
                            }
                        } catch (e) { }
                        const msg = errData?.error || (resp.status === 502 ? 'Bad Gateway' : this.t('err_conn'));
                        this.updateModelStatus('offline');
                        if (msgBubble) {
                            this.renderMarkdown(msgBubble, msg);
                            // Mark as rendered to prevent duplicate via addMessage
                            msgWrapper.dataset.renderedToDom = 'true';
                        }
                        return;
                    }
                    let fullReply = '';
                    const reader = resp.body.getReader();
                    const decoder = new TextDecoder('utf-8');
                    let isComplete = true;
                    let buffer = '';
                    while (true) {
                        const { done, value, } = await reader.read();
                        if (done) break;
                        buffer += decoder.decode(value, { stream: true, });
                        const lines = buffer.split(/\r?\n/);
                        buffer = lines.pop() || '';
                        for (const line of lines) {
                            if (!line.startsWith('data: ')) continue;
                            const raw = line.slice(6).trim();
                            if (raw === '[DONE]') {
                                isComplete = true;
                                break;
                            }
                            try {
                                const obj = JSON.parse(raw);
                                if (obj && obj.meta) {
                                    const meta = obj.meta || {};
                                    this.applyBackendSelectionMeta(meta);
                                    if (meta.conversation_id) {
                                        this.conversationId = Number(meta.conversation_id);
                                    }
                                    if (meta.session_key) {
                                        this.sessionKey = String(meta.session_key);
                                    }
                                    if (meta.message_id && msgWrapper) {
                                        msgWrapper.dataset.messageId = String(meta.message_id);
                                    }
                                    if (Array.isArray(meta.auto_tool_calls)) {
                                        const wrap = msgBubble?.querySelector('.brox-ai-thinking-wrap');
                                        this.applyLiveToolStatus(meta.auto_tool_calls, wrap);
                                    }
                                    this.saveSessionState();
                                    continue;
                                }
                                if (obj.content) {
                                    clearThinking();
                                    fullReply += obj.content;
                                    this.renderMarkdown(msgBubble, fullReply);
                                    this.nodes.body.scrollTop = this.nodes.body.scrollHeight;
                                }
                                // Check for finish_reason indicating incomplete response
                                if (obj.finish_reason && obj.finish_reason !== 'stop') {
                                    isComplete = false;
                                }
                            } catch (e) { }
                        }
                    }
                    clearThinking();
                    this.isThinking = false;
                    this.updateLangUI();
                    this.updateAgenticStatus(null);

                    // Check if response is incomplete and offer continue option
                    if (fullReply) {
                        const isIncomplete = !isComplete || this.isResponseIncomplete(fullReply);
                        if (isIncomplete) this.addContinueButton();

                        // Mark message as having been rendered to DOM to prevent duplicates
                        msgWrapper.dataset.renderedToDom = 'true';

                        this.history.push({ role: 'assistant', content: fullReply, timestamp: new Date().toISOString(), });
                        this.saveHistory();
                        this.saveSessionState();
                        this.renderQuickActions();
                        this.markActivity();
                    }
                    this.updateModelStatus('online');
                    this.updateResponseMeta(msgBubble, t0);
                } catch (err) {
                    this.isThinking = false;
                    this.updateLangUI();
                    this.updateAgenticStatus(null);
                    clearThinking();
                    this.updateModelStatus('offline');
                    if (msgBubble) {
                        this.renderMarkdown(msgBubble, this.t('err_conn'));
                        // Mark as rendered to prevent duplicate
                        msgWrapper.dataset.renderedToDom = 'true';
                    }
                }
            }

            addContinueButton() {
                if (!this.nodes.body) return;
                const buttonContainer = document.createElement('div');
                buttonContainer.className = 'brox-ai-continue-container';
                buttonContainer.innerHTML = `
                <button class="brox-ai-continue-btn" id="brox-ai-continue-btn">
                    <span class="brox-ai-continue-icon">➡️</span>
                    <span class="brox-ai-continue-text">${this.lang === 'bn' ? 'উত্তর চালিয়ে যান' : 'Continue Response'}</span>
                </button>
            `;
                this.nodes.body.appendChild(buttonContainer);

                const continueBtn = document.getElementById('brox-ai-continue-btn');
                if (continueBtn) {
                    continueBtn.onclick = () => this.continueResponse();
                }
            }

        async continueResponse() {
                const lastAssistantMsg = this.history.filter(m => m.role === 'assistant').pop();
                if (!lastAssistantMsg) return;

                // Extract last 50 tokens from the response
                const lastTokens = this.extractLastTokens(lastAssistantMsg.content, 50);
                const continuePrompt = `continue from: "${lastTokens}"`;

                // Add user message indicating continuation
                this.history.push({ role: 'user', content: continuePrompt, timestamp: new Date().toISOString(), });
                this.saveHistory();
                this.renderHistorySidebar();
                this.renderQuickActions();
                this.markActivity();

                // Get AI response for continuation
                await this.getAIResponse();
            }

            extractLastTokens(text, count) {
                if (!text) return '';
                const words = text.split(/\s+/);
                const lastWords = words.slice(-count);
                return lastWords.join(' ');
            }

        // ── GDPR Consent Server Sync ───────────────────────────────────────────────
        async sendGdprConsentToServer(dataConsent, analyticsConsent) {
                try {
                    const csrfToken = this.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
                    await fetch('/api/gdpr/consent', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            visitor_token: this.visitorToken,
                            consent: {
                                data: dataConsent,
                                analytics: analyticsConsent,
                                timestamp: Date.now(),
                            },
                        }),
                    });
                } catch (e) {
                    // Non-critical: local consent is already stored
                    console.warn('[GDPR] Failed to sync consent to server:', e);
                }
            }

            updateModelStatus(status, title = null) {
                if (!this.nodes.modelStatusIndicator) return;

                this.nodes.modelStatusIndicator.classList.remove('brox-ai-online', 'brox-ai-offline', 'brox-ai-connecting');

                if (status === 'online') {
                    this.nodes.modelStatusIndicator.classList.add('brox-ai-online');
                    this.nodes.modelStatusIndicator.title = title || 'AI Online';
                    return;
                }
                if (status === 'offline') {
                    this.nodes.modelStatusIndicator.classList.add('brox-ai-offline');
                    this.nodes.modelStatusIndicator.title = title || 'AI Offline';
                    return;
                }

                this.nodes.modelStatusIndicator.classList.add('brox-ai-connecting');
                this.nodes.modelStatusIndicator.title = title || 'Connecting...';
            }

            updateModelLabel() {
                if (!this.nodes.modelName) return;
                // Show a loading indicator while model is being fetched
                if (!this.currentModel && !this.nodes.modelSel?.selectedOptions?.length) {
                    this.nodes.modelName.textContent = 'Loading...';
                    return;
                }
                const modelId = this.currentModel || '';
                const rawLabel = this.nodes.modelSel?.selectedOptions?.[0]?.textContent || modelId || 'AI';
                const label = this.mapModelLabel(modelId, rawLabel);
                const providerLabel = this.currentProvider ? `${this.currentProvider} · ` : '';
                this.nodes.modelName.textContent = providerLabel + (label || 'AI');
                if (this.nodes.modelLabel) {
                    this.nodes.modelLabel.textContent = label || 'AI';
                }
            }

            applyBackendSelectionMeta(meta = {}) {
                if (!meta || typeof meta !== 'object') return;

                const nextModel = String(meta.selected_model || meta.model || '').trim();
                if (!nextModel) return;

                if (nextModel !== this.currentModel) {
                    this.currentModel = nextModel;
                    this.ensureModelOption(nextModel);
                    if (this.nodes.modelSel) {
                        this.nodes.modelSel.value = nextModel;
                    }
                    this.updateModelLabel();
                    return;
                }

                if (this.nodes.modelSel && this.nodes.modelSel.value !== nextModel) {
                    this.nodes.modelSel.value = nextModel;
                }
                this.updateModelLabel();
            }

            ensureModelOption(modelId) {
                if (!this.nodes.modelSel || !modelId) return;
                const exists = Array.from(this.nodes.modelSel.options || []).some((opt) => opt.value === modelId);
                if (exists) return;

                const opt = document.createElement('option');
                opt.value = modelId;
                opt.textContent = this.mapModelLabel(modelId, modelId);
                opt.selected = true;
                this.nodes.modelSel.appendChild(opt);
            }

            mapModelLabel(modelId, fallbackLabel) {
                const id = (modelId || '').split('/').pop() || '';
                const shortId = id.split(':')[0] || id;
                if (shortId) return shortId;
                const cleanedFallback = (fallbackLabel || '').replace(/\s*\(Free\)\s*/i, '').trim();
                return cleanedFallback || 'AI';
            }

            formatMetaTime() {
                const locale = this.lang === 'bn' ? 'bn-BD' : 'en-US';
                return new Date().toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit', });
            }

            formatDuration(ms) {
                return `${(ms / 1000).toFixed(1)}s`;
            }

            updateResponseMeta(bodyEl, startedAt) {
                if (!bodyEl) return;
                const meta = bodyEl.parentElement?.querySelector('.brox-ai-msg-meta');
                if (!meta) return;
                const timeLabel = this.formatMetaTime();
                const duration = this.formatDuration(performance.now() - startedAt);
                meta.innerHTML = `<span class="brox-ai-meta-time">${timeLabel}</span><span class="brox-ai-meta-sep"> • </span><span class="brox-ai-meta-duration">${duration}</span>`;
            }

            normalizeText(text) {
                return String(text || '')
                    .replace(/<[^>]+>/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim()
                    .toLowerCase();
            }

            getTopicLabels() {
                if (this.lang === 'bn') {
                    return {
                        general: 'সাধারণ তথ্য',
                        support: 'সাপোর্ট',
                        billing: 'বিলিং',
                        feedback: 'মতামত',
                    };
                }
                return {
                    general: 'General',
                    support: 'Support',
                    billing: 'Billing',
                    feedback: 'Feedback',
                };
            }

            getTopicKeywords() {
                return {
                    general: ['general', 'info', 'information', 'guide', 'how to', 'what', 'why', 'কী', 'কি', 'তথ্য', 'জানতে',],
                    support: ['support', 'help', 'issue', 'problem', 'error', 'সাপোর্ট', 'সহায়তা', 'সমস্যা', 'ত্রুটি',],
                    billing: ['billing', 'bill', 'payment', 'price', 'pricing', 'invoice', 'বিল', 'পেমেন্ট', 'দাম', 'মূল্য', 'ইনভয়েস',],
                    feedback: ['feedback', 'review', 'suggestion', 'complaint', 'মতামত', 'প্রস্তাব', 'রিভিউ', 'অভিযোগ',],
                };
            }

            getRelatedTopics(text) {
                const normalized = this.normalizeText(text);
                if (!normalized) return new Set();
                const keywords = this.getTopicKeywords();
                const related = new Set();
                Object.keys(keywords).forEach((topic) => {
                    if (keywords[topic].some((kw) => normalized.includes(kw))) {
                        related.add(topic);
                    }
                });
                return related;
            }

            renderQuickActions() {
                if (!this.nodes.quickActions) return;
                const lastAssistant = [...this.history,].reverse().find(m => m.role === 'assistant');
                const lastUser = [...this.history,].reverse().find(m => m.role === 'user');
                const actions = [];

                if (this.lang === 'bn') {
                    if (lastAssistant) {
                        actions.push(
                            { label: 'শেষ উত্তরের সারাংশ', prompt: 'শেষ উত্তরের সারাংশ দিন।', },
                            { label: 'আরও বিস্তারিত', prompt: 'আরও বিস্তারিত ব্যাখ্যা করুন।', },
                            { label: 'বাংলায় অনুবাদ', prompt: 'শেষ উত্তরের বাংলা অনুবাদ দিন।', }
                        );
                    } else if (lastUser) {
                        actions.push(
                            { label: 'প্রশ্ন সংক্ষেপ', prompt: 'আমার প্রশ্ন সংক্ষেপ করুন।', },
                            { label: 'দ্রুত উত্তর', prompt: 'এক লাইনে উত্তর দিন।', },
                            { label: 'ধাপে ধাপে', prompt: 'ধাপে ধাপে উত্তর দিন।', }
                        );
                    } else {
                        actions.push(
                            { label: 'কী করতে পারি?', prompt: 'আপনি কী কী করতে পারেন?', },
                            { label: 'সাহায্য দরকার', prompt: 'আমি সাহায্য চাই।', }
                        );
                    }
                } else {
                    if (lastAssistant) {
                        actions.push(
                            { label: 'Summarize last reply', prompt: 'Summarize the last reply.', },
                            { label: 'Go deeper', prompt: 'Explain in more detail.', },
                            { label: 'Translate to Bengali', prompt: 'Translate the last reply to Bengali.', }
                        );
                    } else if (lastUser) {
                        actions.push(
                            { label: 'Shorten my question', prompt: 'Shorten my question.', },
                            { label: 'Quick answer', prompt: 'Give a one-line answer.', },
                            { label: 'Step-by-step', prompt: 'Answer step by step.', }
                        );
                    } else {
                        actions.push(
                            { label: 'What can you do?', prompt: 'What can you help with?', },
                            { label: 'Need help', prompt: 'I need help.', }
                        );
                    }
                }

                const selectedTopics = Array.isArray(this.user?.topics) ? this.user.topics : [];
                const topicLabels = this.getTopicLabels();
                const relatedTopics = lastAssistant ? this.getRelatedTopics(lastAssistant.content) : new Set();
                let topicsHtml = '';
                if (selectedTopics.length) {
                    const title = this.lang === 'bn' ? 'সাইট টপিক' : 'Site Topics';
                    const chips = selectedTopics.map((topic) => {
                        const label = topicLabels[topic] || topic;
                        const relatedClass = relatedTopics.has(topic) ? ' brox-ai-quick-related' : '';
                        return `<span class="brox-ai-quick-topic${relatedClass}" data-topic="${topic}">${label}</span>`;
                    }).join('');
                    topicsHtml = `<div class="brox-ai-quick-topics"><div class="brox-ai-quick-title">${title}</div>${chips}</div>`;
                }

                const actionsHtml = actions.map(a =>
                    `<button class="brox-ai-action-chip" data-prompt="${a.prompt.replace(/"/g, '&quot;')}">${a.label}</button>`
                ).join('');

                this.nodes.quickActions.innerHTML = topicsHtml + actionsHtml;
            }

            updateSuggestions() {
                if (!this.nodes.suggestions || !this.nodes.input) return;
                const text = this.nodes.input.value.trim();
                if (!text || text.length < 3) {
                    this.nodes.suggestions.classList.add('brox-ai-hidden');
                    this.nodes.suggestions.innerHTML = '';
                    return;
                }

                const suggestions = this.lang === 'bn'
                    ? [
                        { label: 'পরবর্তী বাক্য', prompt: `এই বাক্যের পরবর্তী স্বাভাবিক বাক্যটি পূরণ করুন: ‘${text}’`, },
                        { label: 'পরবর্তী বাক্যাংশ', prompt: `এই বাক্যের পরবর্তী সংক্ষিপ্ত বাক্যাংশটি সাজান: ‘${text}’`, },
                        { label: 'পরবর্তী শব্দসমষ্টি', prompt: `এই বাক্যের পরবর্তী কয়েকটি শব্দ অনুমান করুন: ‘${text}’`, },
                        { label: 'বাক্য সম্পূর্ণ করুন', prompt: `এই বাক্যটি সম্পূর্ণ করুন: ‘${text}’`, },
                        { label: 'শব্দের পরামর্শ', prompt: `এই বাক্যের পরবর্তী সম্ভাব্য শব্দ বা সংক্ষিপ্ত বাক্যাংশটি লিখুন: ‘${text}’`, },
                        { label: 'শব্দ/বাক্য পূরণ', prompt: `এই ইনপুটের পরবর্তী প্রাকৃতিক শব্দ বা বাক্যাংশ লিখুন: ‘${text}’`, },
                        { label: 'সংক্ষেপে', prompt: `${text} (সংক্ষেপে বলুন)`, },
                        { label: 'উদাহরণসহ', prompt: `${text} (উদাহরণসহ)`, },
                        { label: 'ধাপে ধাপে', prompt: `${text} (ধাপে ধাপে)`, },
                        { label: 'ইংরেজিতে', prompt: `${text} (ইংরেজিতে লিখুন)`, },
                    ]
                    : [
                        { label: 'Next Sentence', prompt: `Complete the next natural sentence after: ‘${text}’`, },
                        { label: 'Next Phrase', prompt: `Predict the next short phrase for: ‘${text}’`, },
                        { label: 'Next Words', prompt: `Suggest the next few words after: ‘${text}’`, },
                        { label: 'Complete Sentence', prompt: `Complete this sentence: ‘${text}’`, },
                        { label: 'Word Suggestion', prompt: `Write the next possible word or short phrase for: ‘${text}’`, },
                        { label: 'Word/Phrase Completion', prompt: `Provide the next natural word or phrase following this input: ‘${text}’`, },
                        { label: 'Short', prompt: `${text} (short version)`, },
                        { label: 'With examples', prompt: `${text} (with examples)`, },
                        { label: 'Step‑by‑step', prompt: `${text} (step by step)`, },
                        { label: 'Translate to Bengali', prompt: `${text} (translate to Bengali)`, },
                    ];

                this.nodes.suggestions.innerHTML = suggestions.map(s =>
                    `<button class="brox-ai-suggestion-chip" data-prompt="${s.prompt.replace(/"/g, '&quot;')}">${s.label}</button>`
                ).join('');
                this.nodes.suggestions.classList.remove('brox-ai-hidden');
            }
        }

        // ── Bootstrap ─────────────────────────────────────────────────────────────────
        if(!window.broxAssistant) {
            document.addEventListener('DOMContentLoaded', () => {
                window.broxAssistant = new BroxAssistant();
            });
        }

    } // End of BroxAssistantLoaded guard
