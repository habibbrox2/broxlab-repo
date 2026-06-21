/**
 * AI Knowledge Manager
 * Manages the knowledge base for AI retrieval-augmented generation (RAG)
 *
 * Features:
 * - CRUD for knowledge base entries
 * - Search and filtering
 * - Categories management
 * - Quality analytics and improvement suggestions
 * - RAG reindexing support
 *
 * API Base: /api/admin/ai-knowledge
 */

(function () {
  'use strict';

  /** @type {number} */
  let currentPage = 1;

  /** @type {number} */
  const PAGE_SIZE = 50;

  /** @type {boolean} */
  let isLoading = false;

  /** @type {string} */
  const API_BASE = '/api/admin/ai-knowledge';

  /** @type {Object} */
  const listeners = {};

  /**
   * Initialize the knowledge manager
   * @param {Object} [options]
   */
  function init(options) {
    options = options || {};

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', setup);
    } else {
      setup();
    }
  }

  function setup() {
    initEventListeners();
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
        console.error('[AI Knowledge] Event handler error:', e);
      }
    });
  }

  // ── Event Listeners ──

  function initEventListeners() {
    // Search input
    const searchInput = document.getElementById('knowledgeSearchInput');
    if (searchInput) {
      let debounceTimer;
      searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
          currentPage = 1;
          search(searchInput.value);
        }, 300);
      });
    }

    // Category filter
    const categoryFilter = document.getElementById('knowledgeCategoryFilter');
    if (categoryFilter) {
      categoryFilter.addEventListener('change', () => {
        currentPage = 1;
        const searchInput = document.getElementById('knowledgeSearchInput');
        search(searchInput?.value || '', categoryFilter.value);
      });
    }

    // New knowledge button
    const newBtn = document.getElementById('newKnowledgeBtn');
    if (newBtn) {
      newBtn.addEventListener('click', () => openEditor());
    }

    // Save button
    const saveBtn = document.getElementById('saveKnowledgeBtn');
    if (saveBtn) {
      saveBtn.addEventListener('click', saveItem);
    }

    // Refresh button
    const refreshBtn = document.getElementById('refreshKnowledgeBtn');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        currentPage = 1;
        list();
      });
    }

    // Reindex button
    const reindexBtn = document.getElementById('reindexKnowledgeBtn');
    if (reindexBtn) {
      reindexBtn.addEventListener('click', reindexAll);
    }

    // Load suggestions
    const suggestionsBtn = document.getElementById('loadSuggestionsBtn');
    if (suggestionsBtn) {
      suggestionsBtn.addEventListener('click', loadSuggestions);
    }

    // Load analytics
    const analyticsBtn = document.getElementById('loadAnalyticsBtn');
    if (analyticsBtn) {
      analyticsBtn.addEventListener('click', loadAnalytics);
    }
  }

  // ── CRUD Operations ──

  /**
   * List knowledge items
   * @param {Object} [options]
   * @returns {Promise<Array>}
   */
  async function list(options) {
    if (isLoading) return [];
    isLoading = true;
    emit('list:loading');

    options = options || {};

    try {
      const params = new URLSearchParams({
        page: options.page || currentPage,
        limit: options.limit || PAGE_SIZE,
      });

      if (options.includeInactive) {
        params.set('include_inactive', '1');
      }

      const data = await apiFetch(`?${params.toString()}`);
      if (data.success && Array.isArray(data.items)) {
        emit('list:loaded', data.items);
        return data.items;
      }
      return [];
    } catch (err) {
      console.error('[AI Knowledge] Failed to list items:', err);
      emit('list:error', err);
      return [];
    } finally {
      isLoading = false;
    }
  }

  /**
   * Get a single knowledge item by ID
   * @param {number} id
   * @returns {Promise<Object|null>}
   */
  async function getById(id) {
    try {
      const data = await apiFetch(`/${id}`);
      if (data.success && data.item) {
        return data.item;
      }
      return null;
    } catch (err) {
      console.error('[AI Knowledge] Failed to get item:', err);
      return null;
    }
  }

  /**
   * Create or update a knowledge item
   * @param {Object} item - { id?, title, content, category?, source_type?, priority?, is_active? }
   * @returns {Promise<Object|null>}
   */
  async function saveItem(item) {
    if (arguments.length === 0) {
      // Called from form button - gather form data
      item = {
        id: parseInt(document.getElementById('knowledgeEditId')?.value || '0', 10),
        title: document.getElementById('knowledgeTitle')?.value || '',
        content: document.getElementById('knowledgeContent')?.value || '',
        category: document.getElementById('knowledgeCategory')?.value || null,
        source_type: document.getElementById('knowledgeSourceType')?.value || 'text',
        priority: parseInt(document.getElementById('knowledgePriority')?.value || '0', 10),
        is_active: document.getElementById('knowledgeIsActive')?.checked !== false,
      };
    }

    if (!item.title || !item.content) {
      showToast('Title and content are required', 'warning');
      return null;
    }

    try {
      const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
      const data = await apiFetch('', {
        method: 'POST',
        body: JSON.stringify({ ...item, csrf_token: csrfToken }),
      });

      if (data.success) {
        showToast(item.id ? 'Knowledge item updated' : 'Knowledge item created', 'success');
        emit('item:saved', { item, id: data.id });
        closeEditor();
        currentPage = 1;
        list();
        return data;
      }
      throw new Error(data.error || 'Save failed');
    } catch (err) {
      showToast(err.message, 'error');
      return null;
    }
  }

  /**
   * Delete a knowledge item
   * @param {number} id
   * @returns {Promise<boolean>}
   */
  async function deleteItem(id) {
    const confirmed = typeof window.showConfirm === 'function'
      ? await window.showConfirm('Delete this knowledge item?', 'Confirm Delete')
      : confirm('Delete this knowledge item?');

    if (!confirmed) return false;

    try {
      const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
      const data = await apiFetch('/delete', {
        method: 'POST',
        body: JSON.stringify({ id, csrf_token: csrfToken }),
      });

      if (data.success) {
        showToast('Knowledge item deleted', 'success');
        emit('item:deleted', { id });
        list();
        return true;
      }
      throw new Error(data.error || 'Delete failed');
    } catch (err) {
      showToast(err.message, 'error');
      return false;
    }
  }

  /**
   * Toggle active state of a knowledge item
   * @param {number} id
   * @returns {Promise<boolean>}
   */
  async function toggleActive(id) {
    try {
      const item = await getById(id);
      if (!item) return false;
      return await saveItem({ ...item, id, is_active: !item.is_active });
    } catch (err) {
      showToast('Failed to toggle status', 'error');
      return false;
    }
  }

  // ── Search ──

  /**
   * Search the knowledge base
   * @param {string} query
   * @param {string} [category]
   * @returns {Promise<Array>}
   */
  async function search(query, category) {
    try {
      const params = new URLSearchParams({ q: query, limit: 20 });
      if (category) params.set('category', category);

      const data = await apiFetch(`/search?${params.toString()}`);
      if (data.success && Array.isArray(data.results)) {
        emit('search:results', { query, results: data.results });
        return data.results;
      }
      return [];
    } catch (err) {
      console.error('[AI Knowledge] Search failed:', err);
      return [];
    }
  }

  // ── Suggestions & Analytics ──

  /**
   * Load improvement suggestions
   * @returns {Promise<Array>}
   */
  async function loadSuggestions() {
    try {
      const data = await apiFetch('/suggestions');
      if (data.success && Array.isArray(data.suggestions)) {
        emit('suggestions:loaded', data.suggestions);
        return data.suggestions;
      }
      return [];
    } catch (err) {
      console.error('[AI Knowledge] Failed to load suggestions:', err);
      return [];
    }
  }

  /**
   * Load knowledge base analytics
   * @returns {Promise<Object>}
   */
  async function loadAnalytics() {
    try {
      const data = await apiFetch('/analytics');
      if (data.success && data.analytics) {
        emit('analytics:loaded', data.analytics);
        return data.analytics;
      }
      return {};
    } catch (err) {
      console.error('[AI Knowledge] Failed to load analytics:', err);
      return {};
    }
  }

  /**
   * Get knowledge sorted by quality
   * @param {number} [limit=20]
   * @returns {Promise<Array>}
   */
  async function getByQuality(limit) {
    try {
      const params = new URLSearchParams({ limit: String(limit || 20) });
      const data = await apiFetch(`/by-quality?${params.toString()}`);
      if (data.success && Array.isArray(data.items)) {
        return data.items;
      }
      return [];
    } catch (err) {
      console.error('[AI Knowledge] Failed to get by quality:', err);
      return [];
    }
  }

  // ── RAG Reindexing ──

  /**
   * Reindex all knowledge items with embeddings
   * @returns {Promise<Object|null>}
   */
  async function reindexAll() {
    const confirmed = typeof window.showConfirm === 'function'
      ? await window.showConfirm('Reindex all knowledge items? This may take a while.', 'Confirm Reindex')
      : confirm('Reindex all knowledge items? This may take a while.');

    if (!confirmed) return null;

    try {
      showToast('Reindexing started...', 'info');

      const data = await apiFetch('/reindex', {
        method: 'POST',
      });

      if (data.success) {
        showToast('Reindexing complete', 'success');
        emit('reindex:complete', data.result);
        return data.result;
      }
      throw new Error(data.error || 'Reindex failed');
    } catch (err) {
      showToast(err.message, 'error');
      return null;
    }
  }

  // ── Editor Modal ──

  /**
   * Open the knowledge item editor
   * @param {Object} [item] - Existing item to edit
   */
  function openEditor(item) {
    const modal = document.getElementById('knowledgeEditorModal');
    if (!modal) return;

    document.getElementById('knowledgeEditId').value = item?.id || '';
    document.getElementById('knowledgeTitle').value = item?.title || '';
    document.getElementById('knowledgeContent').value = item?.content || '';
    document.getElementById('knowledgeCategory').value = item?.category || '';
    document.getElementById('knowledgeSourceType').value = item?.source_type || 'text';
    document.getElementById('knowledgePriority').value = item?.priority || '0';
    document.getElementById('knowledgeIsActive').checked = item ? item.is_active !== false : true;

    document.getElementById('knowledgeModalTitle').textContent = item ? 'Edit Knowledge Item' : 'New Knowledge Item';

    showModal(modal);
  }

  function closeEditor() {
    const modal = document.getElementById('knowledgeEditorModal');
    if (modal) hideModal(modal);
  }

  // ── UI Helpers ──

  function showModal(modalEl) {
    if (!modalEl) return;
    if (typeof window.broxUI?.Modal?.getOrCreateInstance === 'function') {
      window.broxUI.Modal.getOrCreateInstance(modalEl).show();
      return;
    }
    modalEl.classList.add('show');
    modalEl.style.display = 'block';
    modalEl.setAttribute('aria-hidden', 'false');
  }

  function hideModal(modalEl) {
    if (!modalEl) return;
    if (typeof window.broxUI?.Modal?.getInstance === 'function') {
      const instance = window.broxUI.Modal.getInstance(modalEl);
      if (instance) instance.hide();
      return;
    }
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
  }

  function showToast(message, type) {
    if (typeof window.showAlert === 'function') {
      window.showAlert(message, type.charAt(0).toUpperCase() + type.slice(1), type);
      return;
    }
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-xl text-sm font-medium shadow-lg ${
      type === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
      type === 'error' ? 'bg-red-50 text-red-700 border border-red-200' :
      'bg-sky-50 text-sky-700 border border-sky-200'
    }`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  }

  // ── API Helper ──

  async function apiFetch(path, options) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 30000);

    try {
      const fetchOptions = {
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        signal: controller.signal,
        ...options,
      };
      const url = path.startsWith('/') ? path : `${API_BASE}${path}`;
      const response = await fetch(url, fetchOptions);
      clearTimeout(timeoutId);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return await response.json();
    } catch (err) {
      clearTimeout(timeoutId);
      if (err.name === 'AbortError') {
        throw new Error('Request timeout');
      }
      console.error('[AI Knowledge] API Error:', path, err);
      throw err;
    }
  }

  // ── Public API ──

  const AIKnowledgeManager = {
    init,
    on,
    off,
    list,
    getById,
    saveItem,
    deleteItem,
    toggleActive,
    search,
    loadSuggestions,
    loadAnalytics,
    getByQuality,
    reindexAll,
    openEditor,
    closeEditor,
  };

  if (typeof window !== 'undefined') {
    window.AIKnowledgeManager = AIKnowledgeManager;
  }
})();
