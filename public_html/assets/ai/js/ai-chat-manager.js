/**
 * AI Chat Manager
 * Manages chat sessions, conversations, and feedback
 *
 * Features:
 * - Session initialization and restoration
 * - Message history loading
 * - Conversation CRUD (create, list, select, delete)
 * - Feedback collection (thumbs up/down)
 * - Message export
 *
 * API Base: /api/ai/
 */

(function () {
  'use strict';

  /** @type {number|null} */
  let currentConversationId = null;

  /** @type {string|null} */
  let sessionKey = null;

  /** @type {string|null} */
  let guestToken = null;

  /** @type {boolean} */
  let isLoading = false;

  /** @type {number} */
  let page = 1;

  /** @type {boolean} */
  let hasMore = true;

  /** @type {string} */
  const API_BASE = '';

  /** @type {Object} */
  const listeners = {};

  /**
   * Initialize the chat manager
   * @param {Object} [options]
   * @param {string} [options.guestToken]
   * @param {number} [options.conversationId]
   */
  function init(options) {
    options = options || {};
    guestToken = options.guestToken || getGuestToken();
    currentConversationId = options.conversationId || null;

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', setup);
    } else {
      setup();
    }
  }

  function setup() {
    // Auto-restore session
    restoreSession();
  }

  // ── Event System ──

  function on(event, callback) {
    if (!listeners[event]) listeners[event] = [];
    listeners[event].push(callback);
  }

  function off(event, callback) {
    if (!listeners[event]) return;
    listeners[event] = listeners[event].filter((cb) => cb !== callback);
  }

  function emit(event, data) {
    (listeners[event] || []).forEach((cb) => {
      try {
        cb(data);
      } catch (e) {
        console.error('[AI Chat] Event handler error:', e);
      }
    });
  }

  // ── Session Management ──

  /**
   * Get or create a guest token
   * @returns {string}
   */
  function getGuestToken() {
    let token = sessionStorage.getItem('ai_guest_token');
    if (!token) {
      token = 'guest_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
      sessionStorage.setItem('ai_guest_token', token);
    }
    return token;
  }

  /**
   * Restore an existing session or create a new one
   * @returns {Promise<Object>}
   */
  async function restoreSession() {
    try {
      const data = await apiFetch('/session', {
        method: 'POST',
        body: JSON.stringify({
          guestToken: guestToken,
          conversation_id: currentConversationId,
        }),
      });

      if (data.success) {
        sessionKey = data.session_key || null;
        if (data.conversation_id) {
          currentConversationId = data.conversation_id;
        }
        emit('session:ready', data);
        return data;
      }
      throw new Error(data.error || 'Session restore failed');
    } catch (err) {
      console.error('[AI Chat] Session restore failed:', err);
      emit('session:error', err);
      return null;
    }
  }

  /**
   * Get current session info
   * @returns {{ conversationId: number|null, sessionKey: string|null, guestToken: string }}
   */
  function getSession() {
    return {
      conversationId: currentConversationId,
      sessionKey,
      guestToken,
    };
  }

  // ── Conversation Management ──

  /**
   * List conversations with pagination
   * @param {Object} [options]
   * @param {number} [options.page=1]
   * @param {number} [options.limit=50]
   * @returns {Promise<Array>}
   */
  async function listConversations(options) {
    options = options || {};
    const params = new URLSearchParams({
      page: options.page || page,
      limit: options.limit || 50,
    });

    try {
      const data = await apiFetch(`/api/admin/ai/conversations?${params.toString()}`);
      if (data.success && Array.isArray(data.conversations)) {
        hasMore = data.conversations.length >= (options.limit || 50);
        page = (options.page || page) + 1;
        emit('conversations:loaded', data.conversations);
        return data.conversations;
      }
      return [];
    } catch (err) {
      console.error('[AI Chat] Failed to list conversations:', err);
      return [];
    }
  }

  /**
   * Load messages for a conversation via the export endpoint
   * @param {number} conversationId
   * @returns {Promise<Array>}
   */
  async function loadMessages(conversationId) {
    if (!conversationId) return [];
    isLoading = true;
    emit('messages:loading', { conversationId });

    try {
      const exportApi = `/api/admin/ai/conversations/export?conversation_id=${conversationId}`;
      const res = await fetch(exportApi, { credentials: 'same-origin' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (data.success && Array.isArray(data.messages)) {
        currentConversationId = conversationId;
        emit('messages:loaded', { conversationId, messages: data.messages });
        return data.messages;
      }
      return [];
    } catch (err) {
      console.error('[AI Chat] Failed to load messages:', err);
      emit('messages:error', err);
      return [];
    } finally {
      isLoading = false;
    }
  }

  /**
   * Delete a conversation and all its messages
   * @param {number} conversationId
   * @returns {Promise<boolean>}
   */
  async function deleteConversation(conversationId) {
    if (!conversationId) return false;

    try {
      const data = await apiFetch(`/api/admin/ai/conversations/${conversationId}`, {
        method: 'DELETE',
      });

      if (data.success) {
        if (currentConversationId === conversationId) {
          currentConversationId = null;
        }
        emit('conversation:deleted', { conversationId });
        return true;
      }

      console.error('[AI Chat] Server returned failure for delete:', data);
      return false;
    } catch (err) {
      console.error('[AI Chat] Failed to delete conversation:', err);
      return false;
    }
  }

  /**
   * Export a conversation
   * @param {number} conversationId
   * @returns {Promise<Object|null>}
   */
  async function exportConversation(conversationId) {
    try {
      const res = await fetch(`/api/admin/ai/conversations/export?conversation_id=${conversationId}`, {
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return await res.json();
    } catch (err) {
      console.error('[AI Chat] Failed to export conversation:', err);
      return null;
    }
  }

  // ── Feedback ──

  /**
   * Submit feedback for a message
   * @param {number} conversationId
   * @param {number} messageId
   * @param {number} rating - 1-5 rating
   * @returns {Promise<boolean>}
   */
  async function submitFeedback(conversationId, messageId, rating) {
    try {
      const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

      const data = await apiFetch('/feedback', {
        method: 'POST',
        body: JSON.stringify({
          conversation_id: conversationId,
          message_id: messageId,
          rating: rating,
          csrf_token: csrfToken,
        }),
      });

      if (data.success) {
        emit('feedback:sent', { conversationId, messageId, rating });
        return true;
      }
      return false;
    } catch (err) {
      console.error('[AI Chat] Failed to submit feedback:', err);
      return false;
    }
  }

  /**
   * Collect feedback using the feedback widget
   * @param {number} conversationId
   * @param {number} messageId
   * @param {'up'|'down'} type
   * @returns {Promise<boolean>}
   */
  async function collectFeedback(conversationId, messageId, type) {
    const rating = type === 'up' ? 5 : 1;
    const success = await submitFeedback(conversationId, messageId, rating);
    if (success) {
      emit('feedback:collected', { conversationId, messageId, type });
    }
    return success;
  }

  // ── Image Context Management ──

  /**
   * Clear image context for the current session
   * @returns {Promise<boolean>}
   */
  async function clearImageContext() {
    try {
      const data = await apiFetch('/clear-image-context', {
        method: 'POST',
        body: JSON.stringify({ guestToken }),
      });
      return data.success === true;
    } catch (err) {
      console.error('[AI Chat] Failed to clear image context:', err);
      return false;
    }
  }

  // ── Utility ──

  /**
   * Format a timestamp for display
   * @param {string} timestamp
   * @returns {string}
   */
  function formatTime(timestamp) {
    if (!timestamp) return '';
    const date = new Date(timestamp);
    if (isNaN(date.getTime())) return '';
    const now = new Date();
    const diff = now - date;
    const mins = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (mins < 1) return 'Just now';
    if (mins < 60) return `${mins}m ago`;
    if (hours < 24) return `${hours}h ago`;
    if (days < 7) return `${days}d ago`;
    return date.toLocaleDateString();
  }

  /**
   * Truncate text to a maximum length
   * @param {string} text
   * @param {number} max
   * @returns {string}
   */
  function truncate(text, max) {
    if (!text || text.length <= max) return text || '';
    return text.slice(0, max) + '...';
  }

  // ── API Helper ──

  async function apiFetch(path, options) {
    try {
      const url = path.startsWith('/') ? path : `${API_BASE}${path}`;
      const res = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        ...options,
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return await res.json();
    } catch (err) {
      console.error('[AI Chat] API Error:', path, err);
      throw err;
    }
  }

  // ── Public API ──

  const AIChatManager = {
    init,
    on,
    off,
    getSession,
    restoreSession,
    listConversations,
    loadMessages,
    deleteConversation,
    exportConversation,
    submitFeedback,
    collectFeedback,
    clearImageContext,
    formatTime,
    truncate,
  };

  if (typeof window !== 'undefined') {
    window.AIChatManager = AIChatManager;
  }
})();
