/**
 * BroxBhai AI SYSTEM - Chat Management Dashboard (2026 Admin)
 * Path: /public_html/ai/js/ai-chat-manager.js
 */

// ── Auto-inject ai-style.css (no <link> tag needed in HTML) ──────────────────
(function injectAiCSS() {
    const cssUrl = (document.currentScript?.src || '/ai/js/ai-chat-manager.js')
        .replace(/\/js\/[^/]+$/, '/css/ai-style.css');
    if (!document.querySelector(`link[href="${cssUrl}"]`)) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = cssUrl;
        document.head.appendChild(link);
    }
})();

class AIChatManager {
    constructor() {
        this.conversations = [];
        this.filteredConversations = [];
        this.currentChatId = null;
        this.currentTranscript = [];
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        this.initUI();
        this.bindEvents();
        this.loadConversations();
    }

    initUI() {
        this.nodes = {
            root: document.querySelector('.chat-manager-root'),
            convList: document.getElementById('convList'),
            chatHeader: document.getElementById('chatHeader'),
            chatTranscript: document.getElementById('chatTranscript'),
            chatInputArea: document.getElementById('chatInputArea'),
            activeTitle: document.getElementById('activeTitle'),
            activeAvatar: document.getElementById('activeAvatar'),
            sideUserId: document.getElementById('sideUserId'),
            sideToken: document.getElementById('sideToken'),
            sideFirstSeen: document.getElementById('sideFirstSeen'),
            suggestionContainer: document.getElementById('suggestionContainer'),
            replyField: document.getElementById('replyField'),
            btnSend: document.getElementById('btnSend'),
            btnDraft: document.getElementById('btnDraft'),
            refreshList: document.getElementById('refreshList'),
            chatSearch: document.getElementById('chatSearch'),
            btnEndSession: document.getElementById('btnEndSession'),
            btnBackToList: document.getElementById('btnBackToList')
        };
    }

    bindEvents() {
        if (this.nodes.refreshList) {
            this.nodes.refreshList.onclick = () => this.loadConversations();
        }

        if (this.nodes.btnDraft) {
            this.nodes.btnDraft.onclick = () => this.generateSuggestion();
        }

        if (this.nodes.btnSend) {
            this.nodes.btnSend.onclick = () => this.handleSend();
        }

        if (this.nodes.chatSearch) {
            this.nodes.chatSearch.oninput = (e) => this.handleSearch(e.target.value);
        }

        if (this.nodes.btnEndSession) {
            this.nodes.btnEndSession.onclick = () => this.handleEndSession();
        }

        if (this.nodes.btnBackToList) {
            this.nodes.btnBackToList.onclick = () => this.toggleMobileView(false);
        }
    }

    handleSearch(query) {
        query = query.toLowerCase();
        this.filteredConversations = this.conversations.filter(c =>
            c.id.toString().includes(query) ||
            (c.visitor_token || '').toLowerCase().includes(query) ||
            (c.last_text || '').toLowerCase().includes(query)
        );
        this.renderList();
    }

    async loadConversations() {
        try {
            this.nodes.convList.innerHTML = '<div class="p-4 text-center text-muted">Refreshing...</div>';
            const resp = await fetch('/api/admin/ai-chats');
            const data = await resp.json();

            if (data.success) {
                this.conversations = data.conversations;
                this.filteredConversations = [...this.conversations];
                this.renderList();
                if (this.nodes.chatSearch) this.nodes.chatSearch.value = '';
            } else {
                this.nodes.convList.innerHTML = `<div class="p-4 text-center text-danger">Error: ${data.error}</div>`;
            }
        } catch (err) {
            this.nodes.convList.innerHTML = '<div class="p-4 text-center text-danger">Failed to connect to API</div>';
        }
    }

    renderList() {
        if (!this.filteredConversations.length) {
            this.nodes.convList.innerHTML = '<div class="p-4 text-center text-muted">No matching conversations.</div>';
            return;
        }

        this.nodes.convList.innerHTML = '';
        this.filteredConversations.forEach(conv => {
            const item = document.createElement('div');
            item.className = `chat-item ${this.currentChatId == conv.id ? 'active' : ''}`;
            item.onclick = () => this.selectConversation(conv.id);

            const initial = conv.visitor_token ? conv.visitor_token.substring(0, 1).toUpperCase() : 'V';
            const timeStr = new Date(conv.updated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const preview = conv.last_text ? (conv.last_text.length > 40 ? conv.last_text.substring(0, 37) + '...' : conv.last_text) : '<i>No messages</i>';

            item.innerHTML = `
                <div class="chat-item-avatar">${initial}</div>
                <div class="chat-item-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="chat-item-title">Visitor ${conv.id}</div>
                        <div class="small text-muted">${timeStr}</div>
                    </div>
                    <div class="chat-item-preview text-truncate small text-muted">${preview}</div>
                    <div class="chat-item-meta mt-1">
                        <span class="badge ${conv.status === 'open' ? 'bg-success' : 'bg-secondary'}">${conv.status}</span>
                    </div>
                </div>
            `;
            this.nodes.convList.appendChild(item);
        });
    }

    async selectConversation(id) {
        this.currentChatId = id;
        this.renderList(); // Highlights active
        this.toggleMobileView(true);

        // Load metadata
        const conv = this.conversations.find(c => c.id == id);
        if (conv) {
            this.nodes.sideUserId.textContent = conv.user_id || 'Guest';
            this.nodes.sideToken.textContent = conv.visitor_token || '---';
            this.nodes.sideFirstSeen.textContent = new Date(conv.created_at).toLocaleString();
            this.nodes.activeTitle.textContent = `Visitor #${conv.id}`;
            this.nodes.activeAvatar.textContent = (conv.visitor_token || 'V').substring(0, 1).toUpperCase();
        }

        this.nodes.chatHeader.classList.remove('d-none');
        this.nodes.chatInputArea.classList.remove('d-none');
        this.nodes.chatTranscript.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-arrow-repeat spin"></i> Loading transcript...</div>';

        try {
            const resp = await fetch(`/api/admin/ai-chats/${id}`);
            const data = await resp.json();

            if (data.success) {
                this.currentTranscript = data.messages;
                this.renderTranscript();
                this.nodes.suggestionContainer.innerHTML = `
                    <div class="text-center py-4">
                        <button class="btn btn-primary btn-sm rounded-pill" onclick="window.chatManager.generateSuggestion()">
                            <i class="bi bi-magic"></i> Generate Suggested Reply
                        </button>
                    </div>
                `;
            }
        } catch (err) {
            this.nodes.chatTranscript.innerHTML = '<div class="text-center py-5 text-danger">Error loading transcript.</div>';
        }
    }

    toggleMobileView(showMain) {
        if (showMain) {
            this.nodes.root.classList.add('mobile-show-main');
        } else {
            this.nodes.root.classList.remove('mobile-show-main');
        }
    }

    async handleEndSession() {
        if (!this.currentChatId) return;

        const confirmValue = window.confirm("Are you sure you want to end this session? This will mark it as closed.");
        if (!confirmValue) return;

        try {
            const resp = await fetch('/api/admin/ai-chats/end', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    conversation_id: this.currentChatId,
                    csrf_token: this.csrfToken
                })
            });

            const data = await resp.json();
            if (data.success) {
                // Update local status
                const conv = this.conversations.find(c => c.id == this.currentChatId);
                if (conv) conv.status = 'closed';
                this.renderList();
                this.selectConversation(this.currentChatId); // Refresh transcript area
            } else {
                alert("Failed to end session: " + (data.error || 'Unknown error'));
            }
        } catch (err) {
            alert("Connection error.");
        }
    }

    renderTranscript() {
        this.nodes.chatTranscript.innerHTML = '';
        if (!this.currentTranscript.length) {
            this.nodes.chatTranscript.innerHTML = '<div class="text-center text-muted">No messages yet.</div>';
            return;
        }

        this.currentTranscript.forEach(msg => {
            const wrap = document.createElement('div');
            wrap.className = `ai-msg ${msg.role}`;

            const content = document.createElement('div');
            content.className = 'ai-msg-content';
            content.textContent = msg.content;

            const meta = document.createElement('div');
            meta.className = 'ai-msg-meta';
            meta.textContent = new Date(msg.created_at).toLocaleTimeString();

            wrap.appendChild(content);
            wrap.appendChild(meta);
            this.nodes.chatTranscript.appendChild(wrap);
        });

        this.nodes.chatTranscript.scrollTop = this.nodes.chatTranscript.scrollHeight;
    }

    async generateSuggestion() {
        if (!this.currentChatId) return;

        this.nodes.suggestionContainer.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                <div class="small text-muted mt-2">AI is drafting a reply...</div>
            </div>
        `;

        try {
            const draftingPrompt = {
                role: 'system',
                content: "You are an AI Drafter. Analyze the chat history and provide a professional, helpful, and concise response that a support agent could use. Return ONLY the drafted text, no explanations."
            };

            const messages = [
                draftingPrompt,
                ...this.currentTranscript.map(m => ({ role: m.role, content: m.content })).slice(-10)
            ];

            const resp = await fetch('/api/ai-system/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    messages: messages,
                    isAdmin: true,
                    provider: 'openrouter',
                    model: 'openrouter/auto'
                })
            });

            const data = await resp.json();
            if (data.success) {
                const suggestion = data.text || data.message?.content || '';
                this.renderSuggestion(suggestion);
            } else {
                this.nodes.suggestionContainer.innerHTML = '<div class="text-danger small">Drafting failed.</div>';
            }
        } catch (err) {
            this.nodes.suggestionContainer.innerHTML = '<div class="text-danger small">Network error.</div>';
        }
    }

    renderSuggestion(text) {
        this.nodes.suggestionContainer.innerHTML = `
            <div class="suggestion-card">
                <div class="suggestion-header">
                    <i class="bi bi-stars"></i> Suggested Reply
                </div>
                <div class="suggestion-content">${text}</div>
                <button class="btn btn-primary btn-apply-suggestion btn-sm rounded-pill" onclick="window.chatManager.applySuggestion(\`${text.replace(/`/g, '\\`').replace(/\n/g, '\\n')}\`)">
                    Insert Suggestion
                </button>
            </div>
            <div class="text-center mt-3">
                <button class="btn btn-link btn-sm text-muted" onclick="window.chatManager.generateSuggestion()">
                    <i class="bi bi-arrow-clockwise"></i> Try again
                </button>
            </div>
        `;
    }

    applySuggestion(text) {
        this.nodes.replyField.value = text;
        this.nodes.replyField.focus();
    }

    async handleSend() {
        const text = this.nodes.replyField.value.trim();
        if (!text || !this.currentChatId) return;

        this.nodes.btnSend.disabled = true;

        try {
            const resp = await fetch('/api/admin/ai-chats/reply', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    conversation_id: this.currentChatId,
                    content: text,
                    csrf_token: this.csrfToken
                })
            });

            const data = await resp.json();
            if (data.success) {
                this.currentTranscript.push({
                    role: 'assistant',
                    content: text,
                    created_at: new Date().toISOString()
                });
                this.renderTranscript();
                this.nodes.replyField.value = '';
            } else {
                alert("Failed to send reply: " + (data.error || 'Unknown error'));
            }
        } catch (err) {
            alert("Connection error while sending reply.");
        } finally {
            this.nodes.btnSend.disabled = false;
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.chatManager = new AIChatManager();
});
