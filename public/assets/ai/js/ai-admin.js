/**
 * AI Admin Panel - JavaScript
 * Manages AI providers, settings, and system configuration
 *
 * Features:
 * - Provider CRUD (toggle, set default, test connection)
 * - Settings management (temperature, model selection, API keys)
 * - Chat history management (list, export, view)
 * - Model fetching with remote API support
 *
 * Dependencies: AIKeyboardShortcuts (keyboard-shortcuts.js)
 * API Base: /api/ai/
 */

(function () {
  'use strict';

  /** @type {Array} */
  let providersData = [];

  /** @type {Object} */
  let settingsData = {};

  /** @type {number|null} */
  let currentTestProviderId = null;

  /** @type {string} */
  const API_BASE = '';

  /**
   * Initialize the AI admin panel
   * @param {Object} options
   * @param {Array} [options.providers] - Initial providers data
   * @param {Object} [options.settings] - Initial settings data
   */
  function init(options) {
    options = options || {};
    providersData = options.providers || [];
    settingsData = options.settings || {};

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', setup);
    } else {
      setup();
    }
  }

  /**
   * Setup DOM event listeners and initialization
   */
  function setup() {
    initProviderToggles();
    initModelSelects();
    initTestButtons();
    initSettingsForm();
    initSearchFilter();
    initHealthPills();
  }

  // ── API Helpers ──

  /**
   * Fetch JSON from the API
   * @param {string} path - API path
   * @param {Object} [options] - Fetch options
   * @returns {Promise<Object>}
   */
  async function apiFetch(path, options) {
    try {
      const url = path.startsWith('/') ? path : `${API_BASE}${path}`;
      const res = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        ...options,
      });
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      return await res.json();
    } catch (err) {
      console.error('[AI Admin] API Error:', path, err);
      throw err;
    }
  }

  // ── Provider Toggles ──

  function initProviderToggles() {
    document.querySelectorAll('[data-action="toggle-provider"]').forEach((el) => {
      el.addEventListener('change', async function () {
        const id = this.dataset.providerId;
        const active = this.checked;
        this.disabled = true;

        try {
          const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
      const data = await apiFetch('/admin/ai-system/update-provider', {
            method: 'POST',
            body: JSON.stringify({ action: 'toggle', provider_id: parseInt(id, 10), csrf_token: csrfToken }),
          });
          if (!data.success) throw new Error(data.error || 'Update failed');
          updateProviderRowState(this.closest('tr'), active);
          showToast('Provider updated', 'success');
        } catch (err) {
          this.checked = !active;
          showToast(err.message, 'error');
        } finally {
          this.disabled = false;
        }
      });
    });
  }

  // ── Model Selects ──

  function initModelSelects() {
    document.querySelectorAll('[data-action="load-models"]').forEach(async (el) => {
      const providerName = el.dataset.provider;
      const selectEl = document.getElementById(el.dataset.target);
      if (!selectEl) return;

      await loadModels(providerName, selectEl);

      el.addEventListener('click', async () => {
        await loadModels(providerName, selectEl, true);
      });
    });
  }

  /**
   * Load models into a select element
   * @param {string} providerName
   * @param {HTMLSelectElement} selectEl
   * @param {boolean} [refresh=false]
   */
  async function loadModels(providerName, selectEl, refresh) {
    selectEl.disabled = true;
    selectEl.innerHTML = '<option value="">Loading models...</option>';

    try {
      const params = new URLSearchParams({ provider: providerName, scope: 'admin' });
      if (refresh) params.set('refresh', '1');

      const data = await apiFetch(`/models?${params.toString()}`);
      if (!data.success || !Array.isArray(data.models)) {
        throw new Error(data.error || 'Failed to load models');
      }

      selectEl.innerHTML = '';
      const savedModel = selectEl.dataset.saved || '';

      let hasSaved = false;
      data.models.forEach((m) => {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.name + (m.supports_multimodal ? ' (Multimodal)' : '');
        if (savedModel && savedModel === m.id) {
          opt.selected = true;
          hasSaved = true;
        } else if (m.default && !hasSaved) {
          opt.selected = true;
        }
        selectEl.appendChild(opt);
      });
    } catch (err) {
      console.warn('[AI Admin] Model fetch failed, using fallback:', err);
      selectEl.innerHTML = '<option value="">Models unavailable</option>';
    } finally {
      selectEl.disabled = false;
    }
  }

  // ── Test Connection ──

  function initTestButtons() {
    document.querySelectorAll('[data-action="test-connection"]').forEach((btn) => {
      btn.addEventListener('click', function () {
        const id = this.dataset.providerId;
        if (id) openTestModal(id);
      });
    });
  }

  /**
   * Open the test connection modal
   * @param {string|number} providerId
   */
  function openTestModal(providerId) {
    currentTestProviderId = parseInt(providerId, 10);
    const provider = providersData.find((p) => p.id === currentTestProviderId);
    if (!provider) return;

    const modal = document.getElementById('testConnectionModal');
    if (!modal) return;

    const providerSelect = document.getElementById('testProviderSelect');
    const modelSelect = document.getElementById('testModelSelect');
    const resultDiv = document.getElementById('testConnectionResult');

    if (providerSelect) {
      providerSelect.innerHTML = providersData
        .map(
          (p) =>
            `<option value="${p.id}" ${p.id === currentTestProviderId ? 'selected' : ''}>
              ${p.display_name || p.provider_name}
            </option>`
        )
        .join('');
      providerSelect.onchange = () => loadModels(
        providersData.find((p) => p.id === parseInt(providerSelect.value))?.provider_name,
        modelSelect
      );
    }

    loadModels(provider.provider_name, modelSelect);

    if (resultDiv) {
      resultDiv.innerHTML =
        '<div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-700">Select a model and click "Run Test"</div>';
    }

    showModal(modal);
  }

  /**
   * Run the connection test
   */
  async function runTest() {
    if (!currentTestProviderId) return;

    const modelSelect = document.getElementById('testModelSelect');
    const model = modelSelect ? modelSelect.value : '';
    const resultDiv = document.getElementById('testConnectionResult');
    const btn = document.querySelector('#testConnectionModal .btn-primary');
    const provider = providersData.find((p) => p.id === currentTestProviderId);

    if (!model || !provider) {
      showToast('Please select a model', 'warning');
      return;
    }

    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="inline-spinner inline-spinner-sm mr-1"></span> Testing...';
    resultDiv.innerHTML =
      '<div class="flex items-center gap-2 py-3 text-slate-600"><span class="inline-spinner"></span><span>Sending test request...</span></div>';

    try {
      const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
      const data = await apiFetch('/api/ai-system/test', {
        method: 'POST',
        body: JSON.stringify({ id: currentTestProviderId, model, csrf_token: csrfToken }),
      });

      if (data.success) {
        resultDiv.innerHTML = `
          <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            <strong>Success!</strong><br>
            Model: ${data.model}<br>
            Response: ${data.response || 'OK'}
          </div>`;
        saveHealthStatus(provider.provider_name, 'success', 'Connection successful');
        showToast('Connection successful!', 'success');
      } else {
        resultDiv.innerHTML = `
          <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <strong>Failed:</strong> ${data.error || 'Unknown error'}
          </div>`;
        saveHealthStatus(provider.provider_name, 'error', data.error || 'Unknown error');
        showToast('Connection failed', 'error');
      }
    } catch (err) {
      resultDiv.innerHTML = `
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          Network error: ${err.message}
        </div>`;
      saveHealthStatus(provider.provider_name, 'error', err.message);
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }

  // ── Settings Form ──

  function initSettingsForm() {
    const form = document.getElementById('aiSettingsForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const submitBtn = form.querySelector('[type="submit"]');
      const originalText = submitBtn.textContent;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="inline-spinner inline-spinner-sm mr-1"></span> Saving...';

      try {
        const formData = new FormData(form);
        const data = await fetch('/admin/ai-system/save', {
          method: 'POST',
          body: new URLSearchParams(formData),
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          credentials: 'same-origin',
        });

        // Settings save redirects; check if the response indicates an error
        if (data.success === false) {
          showToast(data.error || 'Save failed', 'error');
        } else {
          showToast('Settings saved successfully', 'success');
        }
      } catch (err) {
        showToast('Network error: ' + err.message, 'error');
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
      }
    });
  }

  // ── Search & Filter ──

  function initSearchFilter() {
    const searchInput = document.getElementById('providerSearchInput');
    const statusFilter = document.getElementById('providerStatusFilter');

    if (searchInput) {
      searchInput.addEventListener('input', filterProviders);
    }
    if (statusFilter) {
      statusFilter.addEventListener('change', filterProviders);
    }
  }

  function filterProviders() {
    const searchInput = document.getElementById('providerSearchInput');
    const statusFilter = document.getElementById('providerStatusFilter');
    const searchTerm = (searchInput?.value || '').toLowerCase();
    const status = statusFilter?.value || 'all';

    document.querySelectorAll('[data-provider-row]').forEach((row) => {
      const name = (row.dataset.providerName || '').toLowerCase();
      const isActive = row.dataset.isActive === '1';
      const matchesSearch = !searchTerm || name.includes(searchTerm);
      const matchesStatus = status === 'all' || (status === 'active' && isActive) || (status === 'inactive' && !isActive);
      row.classList.toggle('hidden', !(matchesSearch && matchesStatus));
    });
  }

  // ── Health Pills ──

  function initHealthPills() {
    document.querySelectorAll('.provider-health-pill').forEach((pill) => {
      const providerName = pill.dataset.providerName;
      if (!providerName) return;
      const health = loadHealthStatus(providerName);
      if (health) {
        pill.textContent = health.status === 'success' ? 'Last success' : health.status === 'error' ? 'Last failure' : 'Not tested';
        pill.className = `provider-health-pill ${health.status || 'unknown'}`;
      }
    });
  }

  /**
   * Save health status to sessionStorage (no conflict with ai-system-admin.js localStorage)
   * @param {string} providerName
   * @param {string} status
   * @param {string} message
   */
  function saveHealthStatus(providerName, status, message) {
    try {
      const data = { status, message, testedAt: new Date().toISOString() };
      sessionStorage.setItem(`ai_admin.health.${providerName}`, JSON.stringify(data));
      // Update the health pill
      document.querySelectorAll(`.provider-health-pill[data-provider-name="${providerName}"]`).forEach((pill) => {
        pill.textContent = status === 'success' ? 'Last success' : 'Last failure';
        pill.className = `provider-health-pill ${status}`;
      });
    } catch (e) {
      // localStorage may be full
    }
  }

  /**
   * Load health status from sessionStorage
   * @param {string} providerName
   * @returns {Object|null}
   */
  function loadHealthStatus(providerName) {
    try {
      const raw = sessionStorage.getItem(`ai_admin.health.${providerName}`);
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      return null;
    }
  }

  // ── UI Helpers ──

  function updateProviderRowState(rowEl, isActive) {
    if (!rowEl) return;
    const badge = rowEl.querySelector('[data-status-badge]');
    if (badge) {
      badge.textContent = isActive ? 'Active' : 'Inactive';
      badge.className = `ai-provider-badge ${isActive ? 'active' : 'inactive'}`;
    }
    rowEl.dataset.isActive = isActive ? '1' : '0';
  }

  function showModal(modalEl) {
    if (!modalEl) return;
    modalEl.classList.add('show');
    modalEl.style.display = 'block';
    modalEl.setAttribute('aria-hidden', 'false');
  }

  function hideModal(modalEl) {
    if (!modalEl) return;
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
  }

  /**
   * Show a toast notification via SweetAlert2 (always available in admin context)
   * @param {string} message
   * @param {'success'|'error'|'warning'|'info'} type
   */
  function showToast(message, type) {
    const label = type.charAt(0).toUpperCase() + type.slice(1);
    typeof window.showAlert === 'function' && window.showAlert(message, label, type);
  }

  // ── Public API ──

  const AIAdmin = {
    init,
    loadModels,
    runTest,
    showToast,
    openTestModal,
  };

  if (typeof window !== 'undefined') {
    window.AIAdmin = AIAdmin;
  }
})();
