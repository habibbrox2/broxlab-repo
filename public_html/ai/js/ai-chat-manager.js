/**
 * BroxBhai AI SYSTEM - Chat Management Dashboard (2026 Admin)
 * Path: /public_html/ai/js/ai-chat-manager.js
 *
 * Features:
 *   - Conversation list with search/filter
 *   - Transcript viewer with timestamp
 *   - AI-powered reply suggestions
 *   - Session management (end/export)
 *   - Mobile responsive with split view
 */

// ── Auto-inject ai-style.css ──────────────────────────────────
(() => {
  const cssUrl = (document.currentScript?.src || '/ai/dist/ai-chat-manager.js')
    .replace(/\/(?:js|dist)\/[^/]+$/, '/dist/ai-style.css');
  if (!document.querySelector(`link[href="${cssUrl}"]`)) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = cssUrl;
    document.head.appendChild(link);
  }
})();

const ChatApi = {
  conversationsUrl: '/api/admin/ai-chats',
  transcriptUrl: (id) => `/api/admin/ai-chats/${encodeURIComponent(id)}`,
  replyUrl: '/api/admin/ai-chats/reply',
  endUrl: '/api/admin/ai-chats/end',
  suggestUrl: '/api/admin/ai/chat',

  async fetchConversations() {
    const res = await fetch(this.conversationsUrl);
    if (!res.ok) throw new ChatError(`HTTP ${res.status}`, 'HTTP_ERROR');
    const data = await res.json();
    if (!data.success) throw new ChatError(data.error || 'Failed to load conversations', 'API_ERROR');
    return data.conversations || [];
  },

  async fetchTranscript(id) {
    const res = await fetch(this.transcriptUrl(id));
    if (!res.ok) throw new ChatError(`HTTP ${res.status}`, 'HTTP_ERROR');
    const data = await res.json();
    if (!data.success) throw new ChatError(data.error || 'Failed to load transcript', 'API_ERROR');
    return data.messages || [];
  },

  async sendReply(conversationId, content, csrfToken) {
    const res = await fetch(this.replyUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        conversation_id: conversationId,
        content,
        csrf_token: csrfToken,
      }),
    });
    if (!res.ok) throw new ChatError(`HTTP ${res.status}`, 'HTTP_ERROR');
    const data = await res.json();
    if (!data.success) throw new ChatError(data.error || 'Failed to send reply', 'API_ERROR');
    return data;
  },

  async endSession(conversationId, csrfToken) {
    const res = await fetch(this.endUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ conversation_id: conversationId, csrf_token: csrfToken }),
    });
    if (!res.ok) throw new ChatError(`HTTP ${res.status}`, 'HTTP_ERROR');
    const data = await res.json();
    if (!data.success) throw new ChatError(data.error || 'Failed to end session', 'API_ERROR');
    return data;
  },

  async generateSuggestion(transcript, csrfToken) {
    const messages = [
      {
        role: 'system',
        content: 'You are an AI Drafter. Analyze the chat history and provide a professional, helpful, and concise response that a support agent could use. Return ONLY the drafted text, no explanations.',
      },
      ...transcript.slice(-10),
    ];

    const res = await fetch(this.suggestUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: JSON.stringify({
        messages,
        provider: 'openrouter',
        model: 'meta-llama/llama-3-8b-instruct:free',
        csrf_token: csrfToken,
      }),
    });
    if (!res.ok) throw new ChatError(`HTTP ${res.status}`, 'HTTP_ERROR');
    const data = await res.json();
    if (!data.success) throw new ChatError(data.error || 'Suggestion failed', 'API_ERROR');
    return data.content || '';
  },
};

class ChatError extends Error {
  constructor(message, code = 'UNKNOWN') {
    super(message);
    this.name = 'ChatError';
    this.code = code;
  }
}

class AIChatManager {
  constructor() {
    this.conversations = [];
    this.filteredConversations = [];
    this.currentChatId = null;
    this.currentTranscript = [];
    this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    this._abortController = null;

    this.nodes = {};
    this.init();
  }

  init() {
    this.cacheNodes();
    this.bindEvents();
    this.loadConversations();
  }

  cacheNodes() {
    this.nodes = {
      root: document.querySelector('.chat-manager-root'),
      convList: document.getElementById('convList'),
      chatHeader: document.getElementById('chatHeader'),
      chatTranscript: document.getElementById('chatTranscript'),
      chatInputArea: document.getElementById('chatInputArea'),
      activeTitle: document.getElementById('activeTitle'),
      activeAvatar: document.getElementById('activeAvatar'),
      activeStatus: document.getElementById('activeStatus'),
      statusText: document.getElementById('statusText'),
      sideUserId: document.getElementById('sideUserId'),
      sideStatus: document.getElementById('sideStatus'),
      sideMsgCount: document.getElementById('sideMsgCount'),
      sideLastActivity: document.getElementById('sideLastActivity'),
      suggestionContainer: document.getElementById('suggestionContainer'),
      replyField: document.getElementById('replyField'),
      btnSend: document.getElementById('btnSend'),
      btnDraft: document.getElementById('btnDraft'),
      refreshList: document.getElementById('refreshList'),
      chatSearch: document.getElementById('chatSearch'),
      btnExportConversation: document.getElementById('btnExportConversation'),
      btnEndSession: document.getElementById('btnEndSession'),
      btnBackToList: document.getElementById('btnBackToList'),
    };
  }

  bindEvents() {
    const { nodes } = this;

    nodes.refreshList?.addEventListener('click', () => this.loadConversations());
    nodes.btnDraft?.addEventListener('click', () => this.generateSuggestion());
    nodes.btnSend?.addEventListener('click', () => this.handleSend());
    nodes.chatSearch?.addEventListener('input', (e) => this.handleSearch(e.target.value));
    nodes.btnExportConversation?.addEventListener('click', () => this.handleExportConversation());
    nodes.btnEndSession?.addEventListener('click', () => this.handleEndSession());
    nodes.btnBackToList?.addEventListener('click', () => this.toggleMobileView(false));

    // Allow Enter to send in reply field
    nodes.replyField?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        this.handleSend();
      }
    });
  }

  handleSearch(query) {
    const q = query.toLowerCase();
    this.filteredConversations = this.conversations.filter(
      (c) =>
        c.id.toString().includes(q) ||
        (c.visitor_token || '').toLowerCase().includes(q) ||
        (c.last_text || '').toLowerCase().includes(q)
    );
    this.renderList();
  }

  async loadConversations() {
    this.setConvListLoading('Refreshing...');

    try {
      this.conversations = await ChatApi.fetchConversations();
      this.filteredConversations = [...this.conversations];
      this.renderList();
      if (this.nodes.chatSearch) this.nodes.chatSearch.value = '';
    } catch (err) {
      console.error('[ChatManager] Failed to load conversations:', err);
      this.setConvListError(`Error: ${err.message}`);
    }
  }

  setConvListLoading(text) {
    if (!this.nodes.convList) return;
    this.nodes.convList.innerHTML = `<div class="p-4 text-center text-muted">${text}</div>`;
  }

  setConvListError(text) {
    if (!this.nodes.convList) return;
    this.nodes.convList.innerHTML = `<div class="p-4 text-center text-danger">${text}</div>`;
  }

  renderList() {
    if (!this.nodes.convList) return;

    if (!this.filteredConversations.length) {
      this.nodes.convList.innerHTML =
        '<div class="p-4 text-center text-muted">No matching conversations.</div>';
      return;
    }

    this.nodes.convList.innerHTML = '';
    this.filteredConversations.forEach((conv) => {
      const item = this.createConversationItem(conv);
      this.nodes.convList.appendChild(item);
    });
  }

  createConversationItem(conv) {
    const item = document.createElement('div');
    item.className = `chat-item ${this.currentChatId === conv.id ? 'active' : ''}`;
    item.onclick = () => this.selectConversation(conv.id);

    const initial = conv.visitor_token
      ? conv.visitor_token.substring(0, 1).toUpperCase()
      : 'V';
    const timeStr = new Date(conv.updated_at).toLocaleTimeString([], {
      hour: '2-digit',
      minute: '2-digit',
    });
    const previewText = conv.last_text
      ? conv.last_text.length > 40
        ? conv.last_text.substring(0, 37) + '...'
        : conv.last_text
      : 'No messages';

    item.innerHTML = `
      <div class="chat-item-avatar">${initial}</div>
      <div class="chat-item-info">
        <div class="d-flex justify-content-between align-items-center">
          <div class="chat-item-title">Visitor ${conv.id}</div>
          <div class="small text-muted">${timeStr}</div>
        </div>
        <div class="chat-item-preview text-truncate small text-muted">${previewText}</div>
        <div class="chat-item-meta mt-1">
          <span class="badge ${conv.status === 'open' ? 'bg-success' : 'bg-secondary'}">${conv.status || 'open'}</span>
        </div>
      </div>
    `;

    return item;
  }

  async selectConversation(id) {
    this.currentChatId = id;
    this.renderList(); // highlights active
    this.toggleMobileView(true);

    const conv = this.conversations.find((c) => c.id === id);
    if (conv) this.updateConversationMeta(conv);

    this.nodes.chatHeader?.classList.remove('d-none');
    this.nodes.chatInputArea?.classList.remove('d-none');
    this.setTranscriptLoading();

    try {
      this.currentTranscript = await ChatApi.fetchTranscript(id);

      if (this.nodes.sideMsgCount) {
        this.nodes.sideMsgCount.textContent = this.currentTranscript.length;
      }

      this.renderTranscript();
      this.showSuggestionButton();
    } catch (err) {
      console.error('[ChatManager] Failed to load transcript:', err);
      this.setTranscriptError(`Error: ${err.message}`);
    }
  }

  updateConversationMeta(conv) {
    const isActive = conv.status === 'open';

    // Update sidebar info
    if (this.nodes.sideUserId) {
      this.nodes.sideUserId.textContent = conv.user_id || 'Guest';
    }
    this.updateStatusDisplay(isActive);
    if (this.nodes.sideMsgCount) {
      this.nodes.sideMsgCount.textContent = conv.message_count || '0';
    }
    if (this.nodes.sideLastActivity) {
      this.nodes.sideLastActivity.textContent = conv.updated_at
        ? new Date(conv.updated_at).toLocaleString()
        : '---';
    }

    // Update header
    if (this.nodes.activeTitle) {
      this.nodes.activeTitle.textContent = `Visitor #${conv.id}`;
    }
    if (this.nodes.activeAvatar) {
      this.nodes.activeAvatar.textContent = (conv.visitor_token || 'V').substring(0, 1).toUpperCase();
    }

    // Show/hide End Session button
    if (this.nodes.btnEndSession) {
      this.nodes.btnEndSession.style.display = isActive ? 'block' : 'none';
    }
  }

  updateStatusDisplay(isActive) {
    if (this.nodes.activeStatus) {
      this.nodes.activeStatus.className = isActive ? 'small text-success' : 'small text-secondary';
      this.nodes.activeStatus.innerHTML = `
        <i class="lucide lucide-circle" style="font-size: 6px;"></i>
        <span id="statusText">${isActive ? 'Active' : 'Inactive'}</span>
      `;
    }

    if (this.nodes.sideStatus) {
      this.nodes.sideStatus.className = `badge ${isActive ? 'bg-success' : 'bg-secondary'}`;
      this.nodes.sideStatus.textContent = isActive ? 'Active' : 'Inactive';
    }
  }

  toggleMobileView(showMain) {
    if (!this.nodes.root) return;
    this.nodes.root.classList.toggle('mobile-show-main', showMain);
  }

  setTranscriptLoading() {
    if (!this.nodes.chatTranscript) return;
    this.nodes.chatTranscript.innerHTML =
      '<div class="text-center py-5 text-muted"><i class="lucide lucide-refresh-cw spin"></i> Loading transcript...</div>';
  }

  setTranscriptError(text) {
    if (!this.nodes.chatTranscript) return;
    this.nodes.chatTranscript.innerHTML =
      `<div class="text-center py-5 text-danger">${text}</div>`;
  }

  renderTranscript() {
    if (!this.nodes.chatTranscript) return;

    if (!this.currentTranscript.length) {
      this.nodes.chatTranscript.innerHTML =
        '<div class="text-center text-muted">No messages yet.</div>';
      return;
    }

    this.nodes.chatTranscript.innerHTML = '';
    this.currentTranscript.forEach((msg) => {
      const wrap = document.createElement('div');
      wrap.className = `ai-msg ${msg.role}`;

      const content = document.createElement('div');
      content.className = 'ai-msg-content';
      content.textContent = msg.content;

      const meta = document.createElement('div');
      meta.className = 'ai-msg-meta';
      meta.textContent = this.formatTimestamp(msg.created_at);

      wrap.appendChild(content);
      wrap.appendChild(meta);
      this.nodes.chatTranscript.appendChild(wrap);
    });

    this.nodes.chatTranscript.scrollTop = this.nodes.chatTranscript.scrollHeight;
  }

  showSuggestionButton() {
    if (!this.nodes.suggestionContainer) return;

    this.nodes.suggestionContainer.innerHTML = `
      <div class="text-center py-4">
        <button class="btn btn-primary btn-sm rounded-pill js-generate-suggestion">
          <i class="lucide lucide-wand"></i> Generate Suggested Reply
        </button>
      </div>
    `;

    this.nodes.suggestionContainer
      .querySelector('.js-generate-suggestion')
      ?.addEventListener('click', () => this.generateSuggestion());
  }

  showSuggestionLoading() {
    if (!this.nodes.suggestionContainer) return;
    this.nodes.suggestionContainer.innerHTML = `
      <div class="text-center py-4">
        <div class="inline-spinner inline-spinner-sm text-primary" role="status"></div>
        <div class="small text-muted mt-2">AI is drafting a reply...</div>
      </div>
    `;
  }

  async generateSuggestion() {
    if (!this.currentChatId) return;

    this.showSuggestionLoading();

    try {
      const suggestion = await ChatApi.generateSuggestion(
        this.currentTranscript.map((m) => ({ role: m.role, content: m.content })),
        this.csrfToken
      );
      this.renderSuggestion(suggestion);
    } catch (err) {
      console.error('[ChatManager] Suggestion failed:', err);
      if (this.nodes.suggestionContainer) {
        this.nodes.suggestionContainer.innerHTML =
          '<div class="text-danger small">Drafting failed. Please try again.</div>';
      }
    }
  }

  renderSuggestion(text) {
    if (!this.nodes.suggestionContainer) return;

    this.nodes.suggestionContainer.innerHTML = `
      <div class="suggestion-card">
        <div class="suggestion-header">
          <i class="lucide lucide-sparkles"></i> Suggested Reply
        </div>
        <div class="suggestion-content">${this.escapeHtml(text)}</div>
        <button class="btn btn-primary btn-apply-suggestion btn-sm rounded-pill js-apply-suggestion">
          Insert Suggestion
        </button>
      </div>
      <div class="text-center mt-3">
        <button class="btn btn-link btn-sm text-muted js-retry-suggestion">
          <i class="lucide lucide-refresh-cw"></i> Try again
        </button>
      </div>
    `;

    this.nodes.suggestionContainer
      .querySelector('.js-apply-suggestion')
      ?.addEventListener('click', () => this.applySuggestion(text));

    this.nodes.suggestionContainer
      .querySelector('.js-retry-suggestion')
      ?.addEventListener('click', () => this.generateSuggestion());
  }

  applySuggestion(text) {
    if (this.nodes.replyField) {
      this.nodes.replyField.value = text;
      this.nodes.replyField.focus();
    }
  }

  async handleSend() {
    const text = this.nodes.replyField?.value.trim();
    if (!text || !this.currentChatId) return;

    if (this.nodes.btnSend) this.nodes.btnSend.disabled = true;

    try {
      await ChatApi.sendReply(this.currentChatId, text, this.csrfToken);

      this.currentTranscript.push({
        role: 'assistant',
        content: text,
        created_at: new Date().toISOString(),
      });
      this.renderTranscript();
      if (this.nodes.replyField) this.nodes.replyField.value = '';
      this.showSuggestionButton();
    } catch (err) {
      console.error('[ChatManager] Send failed:', err);
      await this.showAlert(`Failed to send reply: ${err.message}`, 'Error', 'danger');
    } finally {
      if (this.nodes.btnSend) this.nodes.btnSend.disabled = false;
    }
  }

  async handleExportConversation() {
    if (!this.currentChatId) {
      await this.showAlert('Please select a conversation to export.', 'No conversation selected', 'warning');
      return;
    }
    const url = `/api/admin/ai/conversations/export?conversation_id=${encodeURIComponent(this.currentChatId)}`;
    window.open(url, '_blank');
  }

  async handleEndSession() {
    if (!this.currentChatId) return;

    const confirmed = await this.showConfirm(
      'Are you sure you want to end this session? This will mark it as closed.',
      'End Session',
      'warning'
    );
    if (!confirmed) return;

    try {
      await ChatApi.endSession(this.currentChatId, this.csrfToken);

      const conv = this.conversations.find((c) => c.id === this.currentChatId);
      if (conv) conv.status = 'closed';

      this.renderList();
      this.updateStatusDisplay(false);

      if (this.nodes.btnEndSession) {
        this.nodes.btnEndSession.style.display = 'none';
      }
    } catch (err) {
      console.error('[ChatManager] End session failed:', err);
      await this.showAlert(`Failed to end session: ${err.message}`, 'Error', 'danger');
    }
  }

  formatTimestamp(dateStr) {
    if (!dateStr) return '';
    try {
      return new Date(dateStr).toLocaleTimeString();
    } catch {
      return dateStr;
    }
  }

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  async showAlert(message, title, type) {
    if (typeof window.showAlert === 'function') {
      await window.showAlert(message, title, type);
    } else {
      alert(message);
    }
  }

  async showConfirm(message, title, type) {
    if (typeof window.showConfirm === 'function') {
      return await window.showConfirm(message, title, type);
    }
    return confirm(message);
  }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  window.chatManager = new AIChatManager();
});
