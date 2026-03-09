/**
 * BroxBhai AI SYSTEM - Public Assistant (Premium)
 * Path: /public_html/ai/js/assistant.js
 */

const CONFIG = {
    chatKey: 'brox.ai.history',
    userKey: 'brox.ai.user',
    langKey: 'brox.ai.lang',
    tokenKey: 'brox.ai.visitor_token',
    proxyUrl: '/api/ai-system/chat'
};

const I18N = {
    bn: {
        title: 'ব্রক্স অটোমেশন',
        status: 'অনলাইন',
        thinking: 'উত্তর খুঁজছি...',
        welcome: 'হ্যালো! আমি ব্রক্স ল্যাব সহকারী। আপনার নাম এবং কোন বিষয়ে জানতে চান তা সিলেক্ট করে চ্যাট শুরু করুন।',
        placeholder: 'এখানে লিখুন...',
        name_label: 'আপনার নাম',
        topic_label: 'বিষয় নির্বাচন করুন (একাধিক হতে পারে)',
        start_btn: 'চ্যাট শুরু করুন',
        err_name: 'দয়া করে আপনার নাম লিখুন।',
        err_conn: 'দুঃখিত, বর্তমানে সংযোগে সমস্যা হচ্ছে। পরে চেষ্টা করুন।',
        reset: 'পূর্বের চ্যাট হিস্ট্রি রিসেট করা হয়েছে।'
    },
    en: {
        title: 'Brox Automation',
        status: 'Online',
        thinking: 'AI is thinking...',
        welcome: 'Hello! I am Brox Lab assistant. Please enter your name and select topics to start chatting.',
        placeholder: 'Type message...',
        name_label: 'Your Name',
        topic_label: 'Select Topics (Multi-select)',
        start_btn: 'Start Chatting',
        err_name: 'Please enter your name.',
        err_conn: 'Connection error. Please try again later.',
        reset: 'Previous chat history has been reset.'
    }
};

class BroxAssistant {
    constructor() {
        this.lang = localStorage.getItem(CONFIG.langKey) || 'bn';
        this.history = this.loadHistory();
        this.user = JSON.parse(localStorage.getItem(CONFIG.userKey)) || null;
        this.visitorToken = this.getVisitorToken();
        this.isThinking = false;

        this.initUI();
        if (this.nodes.btn) {
            this.bindEvents();
            this.renderInitialState();
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
        try {
            const h = localStorage.getItem(CONFIG.chatKey);
            return h ? JSON.parse(h) : [];
        } catch { return []; }
    }

    saveHistory() {
        localStorage.setItem(CONFIG.chatKey, JSON.stringify(this.history.slice(-40)));
    }

    initUI() {
        this.nodes = {
            btn: document.getElementById('aiBtn'),
            shell: document.getElementById('aiShell'),
            title: document.getElementById('aiTitle'),
            status: document.getElementById('aiStatus'),
            body: document.getElementById('aiBody'),
            footer: document.getElementById('aiFooter'),
            input: document.getElementById('aiInput'),
            send: document.getElementById('aiSend'),
            prechat: document.getElementById('aiPrechat'),
            langBn: document.getElementById('aiLangBn'),
            langEn: document.getElementById('aiLangEn'),
            close: document.getElementById('aiClose')
        };
        if (this.nodes.btn) this.updateLangUI();
    }

    updateLangUI() {
        if (!this.nodes.title || !this.nodes.input) return;
        this.nodes.title.textContent = this.t('title');
        if (this.nodes.status) this.nodes.status.textContent = this.isThinking ? this.t('thinking') : this.t('status');
        this.nodes.input.placeholder = this.t('placeholder');

        const nameLabel = document.querySelector('label[for="aiUserName"]');
        const topicLabel = document.querySelector('label[for="aiUserTopics"]');
        const startBtn = document.getElementById('aiStartBtn');
        if (nameLabel) nameLabel.textContent = this.t('name_label');
        if (topicLabel) topicLabel.textContent = this.t('topic_label');
        if (startBtn) startBtn.textContent = this.t('start_btn');

        if (this.nodes.langBn) this.nodes.langBn.classList.toggle('active', this.lang === 'bn');
        if (this.nodes.langEn) this.nodes.langEn.classList.toggle('active', this.lang === 'en');
    }

    renderInitialState() {
        if (!this.nodes.prechat || !this.nodes.body || !this.nodes.footer) return;

        if (!this.user) {
            this.nodes.prechat.classList.remove('d-none');
            this.nodes.body.classList.add('d-none');
            this.nodes.footer.classList.add('d-none');
        } else {
            this.nodes.prechat.classList.add('d-none');
            this.nodes.body.classList.remove('d-none');
            this.nodes.footer.classList.remove('d-none');

            if (this.history.length === 0) {
                this.addMessage('assistant', this.t('welcome'));
            } else {
                this.nodes.body.innerHTML = '';
                this.history.forEach(m => this.addMessage(m.role, m.content, false));
            }
        }
    }

    bindEvents() {
        if (!this.nodes.btn) return;
        this.nodes.btn.onclick = () => this.nodes.shell?.classList.toggle('hidden');
        if (this.nodes.close) this.nodes.close.onclick = () => this.nodes.shell?.classList.add('hidden');
        if (this.nodes.langBn) this.nodes.langBn.onclick = () => { this.lang = 'bn'; this.saveLang(); };
        if (this.nodes.langEn) this.nodes.langEn.onclick = () => { this.lang = 'en'; this.saveLang(); };
        if (this.nodes.send) this.nodes.send.onclick = () => this.handleSend();
        if (this.nodes.input) this.nodes.input.onkeypress = (e) => { if (e.key === 'Enter') this.handleSend(); };

        const startBtn = document.getElementById('aiStartBtn');
        if (startBtn) startBtn.onclick = () => this.startChat();
    }

    saveLang() {
        localStorage.setItem(CONFIG.langKey, this.lang);
        this.updateLangUI();
        this.renderInitialState();
    }

    startChat() {
        const nameInput = document.getElementById('aiUserName');
        const name = nameInput?.value.trim();
        if (!name) { alert(this.t('err_name')); return; }

        const selectedTopics = Array.from(document.querySelectorAll('.ai-topic-grid input:checked')).map(i => i.value);
        this.user = { name, topics: selectedTopics };
        localStorage.setItem(CONFIG.userKey, JSON.stringify(this.user));

        this.renderInitialState();
        this.addMessage('assistant', `Hello ${name}! I see you are interested in ${selectedTopics.join(', ') || 'our services'}. How can I help you today?`);
    }

    async handleSend() {
        const text = this.nodes.input.value.trim();
        if (!text || this.isThinking) return;

        this.nodes.input.value = '';
        this.addMessage('user', text);
        this.history.push({ role: 'user', content: text });
        this.saveHistory();

        await this.getAIResponse();
    }

    addMessage(role, content, animate = true) {
        if (!this.nodes.body) return;
        const msg = document.createElement('div');
        msg.className = `ai-msg ${role}`;

        const body = document.createElement('div');
        body.className = 'ai-msg-content';
        msg.appendChild(body);

        if (animate && role === 'assistant') {
            this.typeEffect(body, content);
        } else {
            body.textContent = content;
        }

        const meta = document.createElement('div');
        meta.className = 'ai-msg-meta';
        meta.textContent = new Date().toLocaleTimeString(this.lang === 'bn' ? 'bn-BD' : 'en-US', { hour: '2-digit', minute: '2-digit' });
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
        }, 15);
    }

    async getAIResponse() {
        this.isThinking = true;
        this.updateLangUI();
        const typing = this.showTyping();

        try {
            const resp = await fetch(CONFIG.proxyUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    messages: this.history,
                    visitorToken: this.visitorToken,
                    context: this.user
                })
            });
            const data = await resp.json();

            if (typing) typing.remove();
            this.isThinking = false;
            this.updateLangUI();

            if (data.success) {
                const reply = data.text || data.message?.content || '';
                this.addMessage('assistant', reply);
                this.history.push({ role: 'assistant', content: reply });
                this.saveHistory();
            } else {
                this.addMessage('assistant', this.t('err_conn'));
            }
        } catch (err) {
            if (typing) typing.remove();
            this.isThinking = false;
            this.updateLangUI();
            this.addMessage('assistant', this.t('err_conn'));
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
}

if (!window.broxAssistant) {
    document.addEventListener('DOMContentLoaded', () => {
        window.broxAssistant = new BroxAssistant();
    });
}
