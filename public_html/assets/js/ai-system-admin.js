/**
 * AI System Admin - Vanilla JavaScript
 * Handles provider management, model loading, and connection testing
 * Uses SweetAlert2 for all alerts via window.showAlert()
 * Cache bust: 2026-04-01
 */

'use strict';

// Global state
let currentTestProviderId = null;
let providersData = [];
let savedFrontendModel = '';
let savedBackendModel = '';
let defaultModelSetting = '';

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  initAISystem();
});

function decodeHtmlEntities(value) {
  if (!value || typeof value !== 'string') return value;
  const txt = document.createElement('textarea');
  txt.innerHTML = value;
  return txt.value;
}

function readJsonAttr(el, attrName) {
  if (!el) return null;
  const raw = el.getAttribute(attrName);
  if (!raw) return null;
  try {
    return JSON.parse(raw);
  } catch (e) {
    const decoded = decodeHtmlEntities(raw);
    if (decoded && decoded !== raw) {
      try {
        return JSON.parse(decoded);
      } catch (e2) {
        console.warn('[AI System] Invalid JSON in', attrName, e2);
        return null;
      }
    }
    console.warn('[AI System] Invalid JSON in', attrName, e);
    return null;
  }
}

function initAISystem() {
  // Load providers from global Twig variable if available
  if (typeof window.aiSystemProviders !== 'undefined') {
    providersData = window.aiSystemProviders;
  } else {
    const dataEl = document.getElementById('aiSystemData');
    const providersFromDom = readJsonAttr(dataEl, 'data-providers');
    if (providersFromDom) {
      providersData = providersFromDom;
    }
  }

  // Load settings from global variable if available
  let settingsLoaded = false;
  if (typeof window.aiSystemSettings !== 'undefined') {
    savedFrontendModel = window.aiSystemSettings.frontend_model || '';
    savedBackendModel = window.aiSystemSettings.backend_model || '';
    defaultModelSetting = window.aiSystemSettings.default_model || '';
    settingsLoaded = true;
  } else {
    const settingsEl = document.getElementById('aiSystemData');
    const settingsFromDom = readJsonAttr(settingsEl, 'data-settings');
    if (settingsFromDom) {
      savedFrontendModel = settingsFromDom.frontend_model || '';
      savedBackendModel = settingsFromDom.backend_model || '';
      defaultModelSetting = settingsFromDom.default_model || '';
      settingsLoaded = true;
    }
  }

  if (!settingsLoaded) {
    // Fallback: Get saved models from data attributes
    const frontendModelEl = document.getElementById('frontendModelSelect');
    const backendModelEl = document.getElementById('backendModelSelect');

    if (frontendModelEl && frontendModelEl.dataset.saved) {
      savedFrontendModel = frontendModelEl.dataset.saved;
    }
    if (backendModelEl && backendModelEl.dataset.saved) {
      savedBackendModel = backendModelEl.dataset.saved;
    }

    // Get default model from settings
    const settingsEl = document.querySelector('[data-default-model]');
    if (settingsEl && settingsEl.dataset.defaultModel) {
      defaultModelSetting = settingsEl.dataset.defaultModel;
    }
  }

  // Initialize components
  initTemperatureSlider();
  initModelSelects();
  initEventListeners();
  renderAllHealthPills();
  setupProviderFilters();
  updateSidebarSummaryFromRows();
  checkOllamaStatus();
}

// Temperature slider handler
function initTemperatureSlider() {
  const tempRange = document.getElementById('temperatureRange');
  const tempValue = document.getElementById('temperatureValue');
  if (tempRange && tempValue) {
    tempRange.addEventListener('input', function () {
      tempValue.textContent = this.value;
    });
  }
}

// Model select initialization
async function initModelSelects() {
  const frontendProviderSelect = document.getElementById('frontendProviderSelect');
  const backendProviderSelect = document.getElementById('backendProviderSelect');
  const frontendModelSelect = document.getElementById('frontendModelSelect');
  const backendModelSelect = document.getElementById('backendModelSelect');
  const frontendModelWarning = document.getElementById('frontendModelWarning');
  const backendModelWarning = document.getElementById('backendModelWarning');
  const frontendModelRefresh = document.getElementById('frontendModelRefresh');
  const backendModelRefresh = document.getElementById('backendModelRefresh');

  if (frontendProviderSelect && frontendModelSelect) {
    await buildModelOptions(
      frontendProviderSelect.value,
      frontendModelSelect,
      savedFrontendModel,
      frontendModelWarning,
      frontendModelRefresh
    );
    updateAiSettingsSaveState();
    updateStatusPills(frontendProviderSelect.value);
    updateHfTips(frontendProviderSelect.value);
    setupModelMultimodalToggle(frontendProviderSelect, frontendModelSelect);

    frontendProviderSelect.addEventListener('change', function () {
      buildModelOptions(this.value, frontendModelSelect, '', frontendModelWarning, frontendModelRefresh)
        .then(updateAiSettingsSaveState);
      updateStatusPills(this.value);
      updateHfTips(this.value);
    });
  }

  if (backendProviderSelect && backendModelSelect) {
    await buildModelOptions(
      backendProviderSelect.value,
      backendModelSelect,
      savedBackendModel,
      backendModelWarning,
      backendModelRefresh
    );
    updateAiSettingsSaveState();
    updateHfTips(backendProviderSelect.value);
    setupModelMultimodalToggle(backendProviderSelect, backendModelSelect);

    backendProviderSelect.addEventListener('change', function () {
      buildModelOptions(this.value, backendModelSelect, '', backendModelWarning, backendModelRefresh)
        .then(updateAiSettingsSaveState);
      updateHfTips(this.value);
    });
  }
}

// Event listeners
function initEventListeners() {
  // Toggle provider switches
  document.querySelectorAll('.toggle-provider').forEach((inputEl) => {
    inputEl.addEventListener('change', () => {
      toggleProviderActive(inputEl);
    });
  });

  // Toggle multimodal support per provider
  document.querySelectorAll('.toggle-multimodal').forEach((inputEl) => {
    inputEl.addEventListener('change', () => {
      toggleProviderMultimodal(inputEl);
    });
  });

  // Set default provider buttons
  const providersTable = document.getElementById('providersTable');
  if (providersTable) {
    providersTable.addEventListener('click', (event) => {
      const button = event.target.closest('.set-default-btn');
      if (!button) return;
      event.preventDefault();
      const id = button.getAttribute('data-provider-id');
      if (id) {
        setDefaultProvider(id, button);
      }
    });
  }

  // Test connection buttons
  document.querySelectorAll('[data-action="test-connection"]').forEach((btn) => {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      const id = this.getAttribute('data-provider-id');
      if (id) {
        testConnection(id);
      }
    });
  });

  // Edit provider buttons
  document.querySelectorAll('[data-action="edit-provider"]').forEach((btn) => {
    btn.addEventListener('click', function () {
      const id = this.getAttribute('data-provider-id');
      if (id) openProviderEdit(id);
    });
  });

  // Delete provider buttons
  document.querySelectorAll('[data-action="delete-provider"]').forEach((btn) => {
    btn.addEventListener('click', function () {
      const id = this.getAttribute('data-provider-id');
      if (id) deleteProvider(id);
    });
  });

  // Run test button in modal
  const runTestBtn = document.querySelector('[data-action="run-test"]');
  if (runTestBtn) {
    runTestBtn.addEventListener('click', runTestConnection);
  }

  // Model refresh buttons
  const frontendModelRefresh = document.getElementById('frontendModelRefresh');
  const frontendProviderSelect = document.getElementById('frontendProviderSelect');
  const frontendModelSelect = document.getElementById('frontendModelSelect');
  const frontendModelWarning = document.getElementById('frontendModelWarning');

  if (frontendModelRefresh && frontendProviderSelect) {
    frontendModelRefresh.addEventListener('click', () => {
      buildModelOptions(
        frontendProviderSelect.value,
        frontendModelSelect,
        '',
        frontendModelWarning,
        frontendModelRefresh,
        true
      ).then(updateAiSettingsSaveState);
    });
  }

  const backendModelRefresh = document.getElementById('backendModelRefresh');
  const backendProviderSelect = document.getElementById('backendProviderSelect');
  const backendModelSelect = document.getElementById('backendModelSelect');
  const backendModelWarning = document.getElementById('backendModelWarning');

  if (backendModelRefresh && backendProviderSelect) {
    backendModelRefresh.addEventListener('click', () => {
      buildModelOptions(
        backendProviderSelect.value,
        backendModelSelect,
        '',
        backendModelWarning,
        backendModelRefresh,
        true
      ).then(updateAiSettingsSaveState);
    });
  }

  // Reset model defaults
  const resetModelDefaults = document.getElementById('resetModelDefaults');
  if (resetModelDefaults) {
    resetModelDefaults.addEventListener('click', () => {
      const feSelect = document.getElementById('frontendModelSelect');
      const beSelect = document.getElementById('backendModelSelect');
      if (defaultModelSetting) {
        selectModelById(feSelect, defaultModelSetting);
        selectModelById(beSelect, defaultModelSetting);
      } else {
        if (feSelect) feSelect.selectedIndex = 0;
        if (beSelect) beSelect.selectedIndex = 0;
      }
    });
  }

  // Save provider config button
  const saveProviderBtn = document.getElementById('saveProviderConfig');
  if (saveProviderBtn) {
    saveProviderBtn.addEventListener('click', saveProviderConfig);
  }

  // API key password / visibility toggle
  document.querySelectorAll('.toggle-password').forEach((btn) => {
    btn.addEventListener('click', function () {
      const input = this.previousElementSibling;
      if (!input) return;

      if (input.type === 'password') {
        // Reveal — seed value from data attribute if the field has been cleared
        if (!input.value && input.dataset && input.dataset.apiKeyValue !== undefined) {
          input.value = input.dataset.apiKeyValue;
        }
        input.type = 'text';
        this.querySelector('i').classList.remove('bi-eye');
        this.querySelector('i').classList.add('bi-eye-slash');
      } else {
        // Hide again
        input.type = 'password';
        this.querySelector('i').classList.remove('bi-eye-slash');
        this.querySelector('i').classList.add('bi-eye');
      }
    });
  });
}

// HuggingFace tips toggle
function updateHfTips(_providerName) {
  const hfTipsFrontend = document.getElementById('hfTipsFrontend');
  const hfTipsBackend = document.getElementById('hfTipsBackend');
  const frontendProviderSelect = document.getElementById('frontendProviderSelect');
  const backendProviderSelect = document.getElementById('backendProviderSelect');

  if (hfTipsFrontend && frontendProviderSelect) {
    hfTipsFrontend.classList.toggle('d-none', frontendProviderSelect.value !== 'huggingface');
  }
  if (hfTipsBackend && backendProviderSelect) {
    hfTipsBackend.classList.toggle('d-none', backendProviderSelect.value !== 'huggingface');
  }
}

// Build model options for a provider
function buildModelOptions(providerName, selectEl, savedModel, warningEl, refreshBtn, refresh) {
  return new Promise((resolve) => {
    if (!selectEl) {
      resolve();
      return;
    }

    const reqId = `${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
    selectEl.dataset.reqId = reqId;
    setRefreshState(refreshBtn, selectEl, true);
    buildLoadingOption(selectEl, 'Loading models...');
    if (warningEl) warningEl.classList.add('d-none');

    fetchProviderModels(providerName, refresh)
      .then((models) => {
        if (selectEl.dataset.reqId !== reqId) {
          resolve();
          return;
        }
        renderModelOptions(selectEl, models, savedModel, providerName, warningEl, refreshBtn, false, '');
        resolve();
      })
      .catch((e) => {
        if (selectEl.dataset.reqId !== reqId) {
          resolve();
          return;
        }
        const fetchError = e && e.message ? e.message : 'Fetch failed';
        console.warn('[AI Models] Using fallback list', providerName, e);

        const provider = providersData.find((p) => {
          return p.provider_name === providerName;
        });
        const fallbackMap = getProviderModelMap(provider, true);
        const models = Object.entries(fallbackMap).map((item) => {
          return { id: item[0], name: item[1], };
        });

        renderModelOptions(selectEl, models, savedModel, providerName, warningEl, refreshBtn, true, fetchError);
        resolve();
      });
  });
}

// Render model options to select element
function renderModelOptions(selectEl, models, savedModel, providerName, warningEl, refreshBtn, usedFallback, fetchError) {
  let hiddenNonChat = false;

  // Filter for HuggingFace
  if (providerName === 'huggingface') {
    const before = models.length;
    models = models.filter((m) => { return isHuggingFaceChatModelId(m.id); });
    hiddenNonChat = before > models.length;
  }

  if (!models.length) {
    buildLoadingOption(selectEl, 'No models available');
    if (warningEl) {
      let msg;
      if (providerName === 'huggingface') {
        msg = 'No chat-capable Hugging Face models available for /v1/responses.';
      } else if (usedFallback) {
        msg = fetchError
          ? `Remote models unavailable (${fetchError}). No config models found.`
          : 'Remote models unavailable. No config models found.';
      } else {
        msg = 'No models available for this provider.';
      }
      warningEl.textContent = msg;
      warningEl.classList.remove('text-danger', 'text-warning');
      warningEl.classList.add('text-danger');
      warningEl.classList.remove('d-none');
    }
    selectEl.dataset.hfHasModels = '0';
    setRefreshState(refreshBtn, selectEl, false);
    return;
  }

  selectEl.innerHTML = '';
  let hasSaved = false;
  let hasDefault = false;

  models.forEach((m) => {
    const opt = document.createElement('option');
    opt.value = m.id;
    opt.textContent = m.name + (m.supports_multimodal ? ' (Multimodal)' : '');
    if (m.supports_multimodal) {
      opt.dataset.multimodal = '1';
    }
    if (savedModel && savedModel === m.id) {
      opt.selected = true;
      hasSaved = true;
    } else if (m.default && !hasSaved && !hasDefault) {
      opt.selected = true;
      hasDefault = true;
    }
    selectEl.appendChild(opt);
  });

  if (!hasSaved && !hasDefault) {
    selectEl.selectedIndex = 0;
  }

  if (warningEl) {
    if (providerName === 'huggingface' && hiddenNonChat) {
      warningEl.textContent = 'Some Hugging Face models were hidden because /v1/responses requires chat models.';
      warningEl.classList.remove('text-danger');
      warningEl.classList.add('text-warning');
      warningEl.classList.remove('d-none');
    } else if (usedFallback) {
      warningEl.textContent = fetchError
        ? `Remote fetch failed (${fetchError}). Using config model list.`
        : 'Remote fetch failed. Using config model list.';
      warningEl.classList.remove('text-danger');
      warningEl.classList.add('text-warning');
      warningEl.classList.remove('d-none');
    } else {
      warningEl.classList.add('d-none');
    }
  }

  selectEl.dataset.hfHasModels = '1';
  setRefreshState(refreshBtn, selectEl, false);

  // Update the multimodal indicator checkbox for the selected model.
  updateModelMultimodalCheckbox(selectEl);
}

// Build loading option
function buildLoadingOption(selectEl, text) {
  selectEl.innerHTML = '';
  const opt = document.createElement('option');
  opt.value = '';
  opt.textContent = text || 'Loading models...';
  opt.disabled = true;
  opt.selected = true;
  selectEl.appendChild(opt);
}

// Set refresh button state
function setRefreshState(buttonEl, selectEl, isLoading) {
  if (selectEl) {
    selectEl.disabled = Boolean(isLoading);
  }
  if (!buttonEl) return;
  if (isLoading) {
    if (!buttonEl.dataset.originalHtml) {
      buttonEl.dataset.originalHtml = buttonEl.innerHTML;
    }
    buttonEl.disabled = true;
    buttonEl.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Refreshing...';
  } else {
    buttonEl.disabled = false;
    if (buttonEl.dataset.originalHtml) {
      buttonEl.innerHTML = buttonEl.dataset.originalHtml;
    } else {
      buttonEl.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Refresh';
    }
  }
}

// Fetch provider models from API
async function fetchProviderModels(providerName, refresh) {
  const params = new URLSearchParams();
  params.set('provider', providerName);
  params.set('scope', 'admin');
  if (refresh) {
    params.set('refresh', '1');
  }

  const phpUrl = `/api/ai/models?${params.toString()}`;
  const res = await fetch(phpUrl, {
    credentials: 'same-origin',
  });

  if (!res.ok) {
    console.warn('[AI Models] PHP API fetch failed', providerName, res.status);
    throw new Error(`HTTP ${res.status}`);
  }

  const raw = await res.text();
  let data;
  try {
    data = JSON.parse(raw);
  } catch (e) {
    console.warn('[AI Models] Non-JSON response from PHP', providerName, raw);
    throw new Error('Non-JSON response', { cause: e, });
  }

  if (!data || data.success === false) {
    const apiError = data && data.error ? String(data.error) : 'API returned success=false';
    console.warn('[AI Models] API error from PHP', providerName, apiError);
    throw new Error(apiError);
  }

  if (!Array.isArray(data.models)) {
    console.warn('[AI Models] Invalid models payload from PHP', providerName, data);
    throw new Error('Invalid models payload');
  }
  return data.models;
}

// Get provider model map
function getProviderModelMap(provider, allowDbFallback) {
  let models = {};

  if (provider && provider.config && provider.config.models) {
    models = provider.config.models;
  }

  if (allowDbFallback && (!models || Object.keys(models).length === 0)) {
    if (provider && provider.supported_models) {
      models = provider.supported_models;
    }
  }

  const labels = models;
  const map = {};

  Object.keys(models).forEach((id) => {
    map[id] = labels[id] || models[id] || id;
  });

  return map;
}

// Check if HuggingFace chat model
function isHuggingFaceChatModelId(modelId) {
  const lower = String(modelId || '').toLowerCase();
  const blocked = [
    'sentence-transformers/',
    'embedding',
    'feature-extraction',
    'text-embedding',
    'text2vec',
    'rerank',
    're-rank',
  ];
  return !blocked.some((pattern) => {
    return lower.includes(pattern);
  });
}

// Select model by ID
function selectModelById(selectEl, modelId) {
  if (!selectEl) return false;
  const options = Array.from(selectEl.options || []);
  const match = options.find((opt) => { return opt.value === modelId; });
  if (match) {
    match.selected = true;
    return true;
  }
  selectEl.selectedIndex = 0;
  return false;
}

// Update AI settings save button state
function updateAiSettingsSaveState() {
  const frontendProviderSelect = document.getElementById('frontendProviderSelect');
  const backendProviderSelect = document.getElementById('backendProviderSelect');
  const frontendModelSelect = document.getElementById('frontendModelSelect');
  const backendModelSelect = document.getElementById('backendModelSelect');
  const aiSettingsSaveBtn = document.getElementById('aiSettingsSaveBtn');

  if (!aiSettingsSaveBtn) return;

  const frontendBlocked = frontendProviderSelect && frontendProviderSelect.value === 'huggingface' && frontendModelSelect && frontendModelSelect.dataset && frontendModelSelect.dataset.hfHasModels === '0';
  const backendBlocked = backendProviderSelect && backendProviderSelect.value === 'huggingface' && backendModelSelect && backendModelSelect.dataset && backendModelSelect.dataset.hfHasModels === '0';
  const shouldDisable = frontendBlocked || backendBlocked;

  aiSettingsSaveBtn.disabled = shouldDisable;
  aiSettingsSaveBtn.title = shouldDisable ? 'No chat-capable Hugging Face models available.' : '';
}

function getModelMultimodalCheckbox(selectEl) {
  if (!selectEl) return null;
  if (selectEl.id === 'frontendModelSelect') return document.getElementById('frontendModelMultimodal');
  if (selectEl.id === 'backendModelSelect') return document.getElementById('backendModelMultimodal');
  return null;
}

function getProviderNameForModelSelect(selectEl) {
  if (!selectEl) return null;
  if (selectEl.id === 'frontendModelSelect') {
    const providerSelect = document.getElementById('frontendProviderSelect');
    return providerSelect ? providerSelect.value : null;
  }
  if (selectEl.id === 'backendModelSelect') {
    const providerSelect = document.getElementById('backendProviderSelect');
    return providerSelect ? providerSelect.value : null;
  }
  return null;
}

function updateModelMultimodalCheckbox(selectEl) {
  const checkbox = getModelMultimodalCheckbox(selectEl);
  if (!checkbox) return;
  const option = selectEl.options[selectEl.selectedIndex];
  if (!option) {
    checkbox.checked = false;
    checkbox.disabled = true;
    return;
  }
  const isMulti = option.dataset.multimodal === '1';
  checkbox.checked = isMulti;
  checkbox.disabled = false;
}

async function setModelMultimodal(providerName, modelId, enabled) {
  const provider = providersData.find((p) => {
    return p.provider_name === providerName;
  });
  if (!provider) return;

  try {
    const res = await fetch('/admin/ai-system/update-provider', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', },
      body: JSON.stringify({
        action: 'set_model_multimodal',
        provider_id: parseInt(provider.id, 10),
        model_id: modelId,
        enabled: enabled,
        csrf_token: document.querySelector('input[name="csrf_token"]').value,
      }),
    });
    const data = await res.json();
    if (!data.success) {
      throw new Error(data.error || 'Update failed');
    }

    // Update local cached provider metadata to keep UI in sync
    if (!provider.extra_settings || typeof provider.extra_settings !== 'object') {
      provider.extra_settings = {};
    }
    if (!provider.extra_settings.model_multimodal || typeof provider.extra_settings.model_multimodal !== 'object') {
      provider.extra_settings.model_multimodal = {};
    }
    provider.extra_settings.model_multimodal[modelId] = enabled;
  } catch (e) {
    console.warn('Failed to save model multimodal setting', e);
  }
}

function setupModelMultimodalToggle(providerSelect, modelSelect) {
  const checkbox = getModelMultimodalCheckbox(modelSelect);
  if (!checkbox) return;

  const update = () => {
    updateModelMultimodalCheckbox(modelSelect);
  };

  modelSelect.addEventListener('change', update);
  if (providerSelect) {
    providerSelect.addEventListener('change', update);
  }

  checkbox.addEventListener('change', () => {
    const providerName = getProviderNameForModelSelect(modelSelect);
    const modelId = modelSelect.value;
    if (providerName && modelId) {
      setModelMultimodal(providerName, modelId, checkbox.checked);
      const selectedOpt = modelSelect.selectedOptions[0];
      if (selectedOpt) {
        selectedOpt.dataset.multimodal = checkbox.checked ? '1' : '0';
      }
    }
  });

  update();
}

// Test connection function
function testConnection(id) {
  currentTestProviderId = id;

  const provider = providersData.find((p) => {
    return String(p.id) === String(id);
  });

  if (!provider) return;

  const modalEl = document.getElementById('testConnectionModal');
  const providerSelect = document.getElementById('testProviderSelect');
  const select = document.getElementById('testModelSelect');
  const resultDiv = document.getElementById('testConnectionResult');
  const warningDiv = document.getElementById('testConnectionWarning');

  // Populate providers
  if (providerSelect) {
    providerSelect.innerHTML = '';
    providersData.forEach((p) => {
      const opt = document.createElement('option');
      opt.value = p.id;
      opt.textContent = p.display_name || p.provider_name;
      if (String(p.id) === String(id)) {
        opt.selected = true;
      }
      providerSelect.appendChild(opt);
    });
  }

  // Load models for selected provider
  const loadModelsForProvider = function (providerRow) {
    select.innerHTML = '<option value="">Loading models...</option>';
    if (warningDiv) warningDiv.classList.add('d-none');

    fetchProviderModels(providerRow.provider_name)
      .then((models) => {
        select.innerHTML = '';
        models.forEach((m) => {
          const opt = document.createElement('option');
          opt.value = m.id;
          opt.textContent = m.name;
          if (m.default) {
            opt.selected = true;
          }
          select.appendChild(opt);
        });
        if (select.options.length === 0) {
          const opt = document.createElement('option');
          opt.value = '';
          opt.textContent = 'No models available';
          select.appendChild(opt);
        }
      })
      .catch((e) => {
        const fallback = getProviderModelMap(providerRow, true);
        select.innerHTML = '';
        Object.keys(fallback).forEach((key) => {
          const opt = document.createElement('option');
          opt.value = key;
          opt.textContent = fallback[key];
          select.appendChild(opt);
        });
        if (select.options.length === 0) {
          const opt = document.createElement('option');
          opt.value = '';
          opt.textContent = 'No models available';
          select.appendChild(opt);
        }
        if (warningDiv) {
          const errMsg = e && e.message ? e.message : 'fetch failed';
          warningDiv.textContent = `Remote models unavailable (${errMsg}). Using fallback list.`;
          warningDiv.classList.remove('d-none');
        }
      });
  };

  loadModelsForProvider(provider);

  // Provider select change handler
  if (providerSelect) {
    providerSelect.onchange = function () {
      const newId = providerSelect.value;
      currentTestProviderId = newId;
      const selectedProvider = providersData.find((p) => {
        return String(p.id) === String(newId);
      });
      if (selectedProvider) {
        loadModelsForProvider(selectedProvider);
      }
    };
  }

  resultDiv.innerHTML = '<div class="alert alert-info">Select a model and click "Run Test"</div>';

  // Show modal using Bootstrap
  if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  } else {
    modalEl.classList.add('show');
    modalEl.style.display = 'block';
  }
}

// Run test connection
async function runTestConnection() {
  if (!currentTestProviderId) return;

  const modelSelect = document.getElementById('testModelSelect');
  const model = modelSelect ? modelSelect.value : '';
  const resultDiv = document.getElementById('testConnectionResult');
  const btn = document.querySelector('#testConnectionModal .btn-primary');
  const csrfToken = document.querySelector('input[name="csrf_token"]');
  const provider = providersData.find((p) => {
    return String(p.id) === String(currentTestProviderId);
  });

  if (!model) {
    if (window.showAlert) {
      await window.showAlert('Please select a model before testing.', 'Warning', 'warning');
    } else {
      alert('Please select a model before testing.');
    }
    return;
  }

  if (!provider) {
    console.warn('[AI System] Provider not found for testing', currentTestProviderId);
    return;
  }

  const originalBtnHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Testing...';
  resultDiv.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary"></div><p class="mt-2">Sending test request...</p></div>';

  try {
    const csrfTokenValue = csrfToken ? csrfToken.value : '';
    const response = await fetch('/api/ai-system/test', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', },
      body: JSON.stringify({
        id: currentTestProviderId,
        model: model,
        csrf_token: csrfTokenValue,
      }),
    });

    const data = await response.json();

    if (data.success) {
      if (window.showAlert) {
        await window.showAlert(
          `<strong>Model:</strong> ${data.model}<br><br><strong>Response:</strong> ${data.response || 'OK'}`,
          'Connection Successful!',
          'success',
          { allowHtml: true, }
        );
      } else {
        alert(`Connection successful!\n\nModel: ${data.model}\nResponse: ${data.response || 'OK'}`);
      }
      handleTestResult(provider.provider_name, 'success', `Response: ${data.response || 'OK'}`);
    } else {
      if (window.showAlert) {
        await window.showAlert(
          data.error || 'Unknown error occurred',
          'Connection Failed',
          'error'
        );
      } else {
        alert(`Connection failed: ${data.error || 'Unknown error'}`);
      }
      handleTestResult(provider.provider_name, 'error', data.error || 'Unknown error occurred');
    }

    // ensure health badge and summary are up to date (helper already handles this)
  } catch (e) {
    if (window.showAlert) {
      await window.showAlert(`Network error: ${e.message}`, 'Error', 'error');
    } else {
      alert(`Network error: ${e.message}`);
    }
    handleTestResult(provider.provider_name, 'error', e.message || 'Network error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = originalBtnHtml;
  }
}

// Toggle provider active state
async function toggleProviderActive(inputEl) {
  const id = inputEl && inputEl.dataset ? inputEl.dataset.providerId : null;
  if (!id) return;

  const rowEl = inputEl.closest('tr');
  const active = Boolean(inputEl.checked);
  inputEl.disabled = true;

  try {
    const res = await fetch('/api/ai-system/toggle-provider', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', },
      body: JSON.stringify({ id: parseInt(id, 10), active, }),
    });

    const data = await res.json();
    if (!data.success) {
      throw new Error(data.error || 'Update failed');
    }

    updateProviderRowState(rowEl, active);

    const provider = providersData.find((p) => {
      return String(p.id) === String(id);
    });
    if (provider) {
      provider.is_active = active;
    }
  } catch (e) {
    inputEl.checked = !active;
    const errMsg = e && e.message ? e.message : 'Failed to update provider';
    if (window.showAlert) {
      await window.showAlert(errMsg, 'Error', 'error');
    } else {
      alert(errMsg);
    }
  } finally {
    inputEl.disabled = false;
  }
}

// Toggle provider multimodal support
async function toggleProviderMultimodal(inputEl) {
  const id = inputEl && inputEl.dataset ? inputEl.dataset.providerId : null;
  if (!id) return;

  const rowEl = inputEl.closest('tr');
  const enabled = Boolean(inputEl.checked);
  inputEl.disabled = true;

  try {
    const res = await fetch('/admin/ai-system/update-provider', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', },
      body: JSON.stringify({
        action: 'set_multimodal',
        provider_id: parseInt(id, 10),
        enabled: enabled,
        csrf_token: document.querySelector('input[name="csrf_token"]').value,
      }),
    });

    const data = await res.json();
    if (!data.success) {
      throw new Error(data.error || 'Update failed');
    }

    const provider = providersData.find((p) => {
      return String(p.id) === String(id);
    });
    if (provider) {
      provider.supports_multimodal = enabled;
    }

    // Update badge text
    const badge = rowEl?.querySelector('td[data-label="Multimodal"] .badge');
    if (badge) {
      badge.className = `badge ${enabled ? 'bg-success' : 'bg-secondary'}`;
      badge.textContent = enabled ? 'Yes' : 'No';
    }

    if (rowEl) {
      rowEl.dataset.supportsMultimodal = enabled ? '1' : '0';
    }
    updateSidebarSummaryFromRows();
  } catch (e) {
    inputEl.checked = !enabled;
    const errMsg = e && e.message ? e.message : 'Failed to update provider';
    if (window.showAlert) {
      await window.showAlert(errMsg, 'Error', 'error');
    } else {
      alert(errMsg);
    }
  } finally {
    inputEl.disabled = false;
  }
}

// Update provider row state
function updateProviderRowState(rowEl, isActive) {
  if (!rowEl) return;

  const badge = rowEl.querySelector('td[data-label="Status"] .badge');
  if (badge) {
    badge.className = `badge bg-${isActive ? 'success' : 'secondary'}`;
    badge.textContent = isActive ? 'Active' : 'Inactive';
  }

  rowEl.dataset.isActive = isActive ? '1' : '0';

  const defaultBtn = rowEl.querySelector('.set-default-btn');
  if (defaultBtn) {
    defaultBtn.disabled = !isActive;
  }

  updateSidebarSummaryFromRows();
}

async function setDefaultProvider(providerId, buttonEl) {
  if (!buttonEl) return;
  buttonEl.disabled = true;

  try {
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
    const res = await fetch('/admin/ai-system/update-provider', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        action: 'set_default',
        provider_id: parseInt(providerId, 10),
        csrf_token: csrfToken,
      }),
    });

    const data = await res.json();
    if (!data.success) {
      throw new Error(data.error || 'Failed to set default provider');
    }

    refreshDefaultProviderRows(providerId);
    if (window.showAlert) {
      await window.showAlert('Default provider updated successfully.', 'Success', 'success');
    }
  } catch (error) {
    console.warn('Failed to set default provider', error);
    if (window.showAlert) {
      await window.showAlert(error.message || 'Failed to set default provider', 'Error', 'error');
    } else {
      alert(error.message || 'Failed to set default provider');
    }
  } finally {
    buttonEl.disabled = false;
  }
}

function refreshDefaultProviderRows(defaultProviderId) {
  providersData = providersData.map((provider) => ({
    ...provider,
    is_default: String(provider.id) === String(defaultProviderId),
  }));

  const rows = document.querySelectorAll('#providersTable tbody tr[data-provider-id]');
  rows.forEach((row) => {
    const rowId = row.getAttribute('data-provider-id');
    const defaultCell = row.querySelector('td[data-label="Default"]');
    const isActive = row.dataset.isActive === '1';

    if (!defaultCell || !rowId) return;

    if (String(rowId) === String(defaultProviderId)) {
      defaultCell.innerHTML = '<span class="badge bg-primary"><i class="bi bi-check-circle me-1"></i>Default</span>';
    } else {
      const disabledAttr = isActive ? '' : 'disabled';
      defaultCell.innerHTML = `<button class="btn btn-outline-primary btn-sm set-default-btn" data-provider-id="${rowId}" ${disabledAttr}>Set as Default</button>`;
    }
  });

  updateSidebarSummaryFromRows();
}

// Status pills functions
function getLastTested(providerName) {
  const raw = localStorage.getItem(`brox.ai.last_tested.${providerName}`) || '';
  if (!raw) return 'Not tested';
  const date = new Date(raw);
  if (Number.isNaN(date.getTime())) return 'Not tested';
  return date.toLocaleString();
}

function setLastTested(providerName) {
  localStorage.setItem(`brox.ai.last_tested.${providerName}`, new Date().toISOString());
}

function updateStatusPills(providerName) {
  const apiKeyPill = document.getElementById('aiStatusApiKey');
  const activePill = document.getElementById('aiStatusActive');
  const testedPill = document.getElementById('aiStatusTested');

  const provider = providersData.find((p) => {
    return p.provider_name === providerName;
  });

  if (!provider) return;

  if (apiKeyPill) {
    apiKeyPill.className = `badge ${provider.has_api_key ? 'bg-success' : 'bg-warning text-dark'}`;
    apiKeyPill.textContent = provider.has_api_key ? 'API Key: Present' : 'API Key: Missing';
  }

  if (activePill) {
    activePill.className = `badge ${provider.is_active ? 'bg-success' : 'bg-secondary'}`;
    activePill.textContent = provider.is_active ? 'Provider: Active' : 'Provider: Inactive';
  }

  if (testedPill) {
    testedPill.className = 'badge bg-info text-dark';
    testedPill.textContent = `Last tested: ${getLastTested(providerName)}`;
  }
}

function getHealthStorageKey(providerName) {
  return `brox.ai.health.${providerName}`;
}

function getProviderHealth(providerName) {
  if (!providerName) return null;
  const raw = localStorage.getItem(getHealthStorageKey(providerName));
  if (!raw) return null;
  try {
    return JSON.parse(raw);
  } catch (e) {
    console.warn('[AI System] Invalid health payload for', providerName);
    return null;
  }
}

function setProviderHealth(providerName, data) {
  if (!providerName || !data) return;
  localStorage.setItem(getHealthStorageKey(providerName), JSON.stringify(data));
}

function formatRelativeTime(timestamp) {
  if (!timestamp) return 'Not tested';
  const date = new Date(timestamp);
  if (Number.isNaN(date.getTime())) return 'Invalid date';
  const diff = Date.now() - date.getTime();
  if (diff < 60000) return 'Just now';
  const minutes = Math.floor(diff / 60000);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  return `${days}d ago`;
}

function renderHealthPill(providerName) {
  if (!providerName) return;
  const row = document.querySelector(`tr[data-provider-name="${providerName}"]`);
  if (!row) return;
  const pill = row.querySelector(`.provider-health-pill[data-provider-name="${providerName}"]`);
  const timestamp = row.querySelector(`.provider-health-timestamp[data-provider-name="${providerName}"]`);
  if (!pill || !timestamp) return;

  const health = getProviderHealth(providerName);
  const status = health && health.status ? health.status : 'unknown';
  const message = health && health.message ? health.message : 'Not tested yet';
  let label = 'Not tested yet';
  if (status === 'success') {
    label = 'Last success';
  } else if (status === 'error') {
    label = 'Last failure';
  }

  pill.textContent = label;
  pill.dataset.status = status;
  pill.classList.remove('success', 'error', 'unknown');
  pill.classList.add(status === 'success' ? 'success' : status === 'error' ? 'error' : 'unknown');
  pill.setAttribute('title', message);
  timestamp.textContent = health && health.testedAt ? formatRelativeTime(health.testedAt) : 'Not tested';
}

function renderAllHealthPills() {
  providersData.forEach((p) => {
    renderHealthPill(p.provider_name);
  });
  updateSidebarSummaryFromRows();
}

function applyHealthToProvider(providerName) {
  renderHealthPill(providerName);
  updateSidebarSummaryFromRows();
}

function handleTestResult(providerName, status, message) {
  if (!providerName) return;
  const payload = {
    status: status,
    message: message || (status === 'success' ? 'Connection successful' : 'Unknown error'),
    testedAt: new Date().toISOString(),
  };
  setProviderHealth(providerName, payload);
  applyHealthToProvider(providerName);
  setLastTested(providerName);
  updateStatusPills(providerName);
}

function setupProviderFilters() {
  const searchInput = document.getElementById('providerSearchInput');
  const statusSelect = document.getElementById('providerStatusFilter');
  const apiKeySelect = document.getElementById('providerApiKeyFilter');
  const multimodalSelect = document.getElementById('providerMultimodalFilter');
  const healthSelect = document.getElementById('providerHealthFilter');
  const handler = filterProviderRows;
  [searchInput, statusSelect, apiKeySelect, multimodalSelect, healthSelect,].forEach((el) => {
    if (!el) return;
    const eventName = el.tagName === 'INPUT' ? 'input' : 'change';
    el.addEventListener(eventName, handler);
  });
  filterProviderRows();
}

function filterProviderRows() {
  const table = document.getElementById('providersTable');
  if (!table) return;
  const searchInput = document.getElementById('providerSearchInput');
  const statusSelect = document.getElementById('providerStatusFilter');
  const apiKeySelect = document.getElementById('providerApiKeyFilter');
  const multimodalSelect = document.getElementById('providerMultimodalFilter');
  const healthSelect = document.getElementById('providerHealthFilter');
  const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';
  const status = statusSelect ? statusSelect.value : 'all';
  const apiKey = apiKeySelect ? apiKeySelect.value : 'all';
  const multimodal = multimodalSelect ? multimodalSelect.value : 'all';
  const health = healthSelect ? healthSelect.value : 'all';

  table.querySelectorAll('tbody tr').forEach((row) => {
    const displayName = row.dataset.displayName ? row.dataset.displayName.toLowerCase() : '';
    const providerName = row.dataset.providerName ? row.dataset.providerName : '';
    const hasKey = row.dataset.hasKey === '1';
    const isActive = row.dataset.isActive === '1';
    const supportsMultimodal = row.dataset.supportsMultimodal === '1';
    const healthData = getProviderHealth(providerName);
    const healthStatus = healthData && healthData.status ? healthData.status : 'unknown';

    const matchesSearch = !searchTerm || displayName.includes(searchTerm) || providerName.includes(searchTerm);
    const matchesStatus = status === 'all' || (status === 'active' && isActive) || (status === 'inactive' && !isActive);
    const matchesKey = apiKey === 'all' || (apiKey === 'keyed' && hasKey) || (apiKey === 'missing' && !hasKey);
    const matchesMultimodal = multimodal === 'all' || (multimodal === 'yes' && supportsMultimodal) || (multimodal === 'no' && !supportsMultimodal);
    const matchesHealth = health === 'all' || healthStatus === health;

    const visible = matchesSearch && matchesStatus && matchesKey && matchesMultimodal && matchesHealth;
    row.classList.toggle('d-none', !visible);
  });
  updateSidebarSummaryFromRows();
}

function updateSidebarSummaryFromRows() {
  const rows = document.querySelectorAll('#providersTable tbody tr');
  let activeCount = 0;
  let needsKeyCount = 0;
  let testedCount = 0;
  let latestTested = null;

  rows.forEach((row) => {
    if (row.classList.contains('d-none')) return;
    if (row.dataset.isActive === '1') {
      activeCount++;
    }
    if (row.dataset.hasKey === '0') {
      needsKeyCount++;
    }
    const providerName = row.dataset.providerName;
    const health = getProviderHealth(providerName);
    if (health && health.status && health.status !== 'unknown') {
      testedCount++;
      if (health.testedAt) {
        const date = new Date(health.testedAt);
        if (!Number.isNaN(date.getTime())) {
          if (!latestTested || date > latestTested) {
            latestTested = date;
          }
        }
      }
    }
  });

  const activeEl = document.getElementById('providerSummaryActiveCount');
  const needsKeyEl = document.getElementById('providerSummaryNeedsKeyCount');
  const testedEl = document.getElementById('providerSummaryTestedCount');
  const lastTestedEl = document.getElementById('providerSummaryLastTestedTime');

  if (activeEl) activeEl.textContent = activeCount;
  if (needsKeyEl) needsKeyEl.textContent = needsKeyCount;
  if (testedEl) testedEl.textContent = testedCount;
  if (lastTestedEl) {
    lastTestedEl.textContent = latestTested ? formatRelativeTime(latestTested.toISOString()) : 'Not run';
  }
}

// Open provider edit modal
function openProviderEdit(id) {
  const provider = providersData.find((p) => {
    return String(p.id) === String(id);
  });

  if (!provider || provider.provider_name !== 'huggingface') return;

  const modalEl = document.getElementById('editProviderModal');
  const idInput = document.getElementById('editProviderId');
  const endpointInput = document.getElementById('editProviderEndpoint');
  const modelsInput = document.getElementById('editProviderModels');
  const errorEl = document.getElementById('editProviderError');

  if (idInput) idInput.value = provider.id;
  if (endpointInput) endpointInput.value = provider.api_endpoint || (provider.config && provider.config.endpoint) || '';
  if (modelsInput) modelsInput.value = JSON.stringify(provider.supported_models || {}, null, 2);
  if (errorEl) errorEl.classList.add('d-none');

  if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  } else {
    modalEl.classList.add('show');
    modalEl.style.display = 'block';
  }
}

// Save provider config
async function saveProviderConfig() {
  const idInput = document.getElementById('editProviderId');
  const endpointInput = document.getElementById('editProviderEndpoint');
  const modelsInput = document.getElementById('editProviderModels');
  const errorEl = document.getElementById('editProviderError');
  const csrfInput = document.querySelector('input[name="csrf_token"]');

  let providerId = 0;
  if (idInput && idInput.value) {
    providerId = parseInt(idInput.value, 10);
  }
  if (!providerId) return;

  let models = {};
  try {
    if (modelsInput && modelsInput.value) {
      models = JSON.parse(modelsInput.value);
    }
    if (!models || typeof models !== 'object' || Array.isArray(models)) {
      throw new Error('Invalid JSON');
    }
    if (errorEl) errorEl.classList.add('d-none');
  } catch (e) {
    if (errorEl) {
      errorEl.textContent = 'Invalid JSON. Provide a map of model_id to label.';
      errorEl.classList.remove('d-none');
    }
    return;
  }

  const payload = {
    action: 'update_config',
    provider_id: providerId,
    api_endpoint: endpointInput ? endpointInput.value : '',
    supported_models: models,
    csrf_token: csrfInput ? csrfInput.value : '',
  };

  const saveProviderBtn = document.getElementById('saveProviderConfig');
  saveProviderBtn.disabled = true;
  saveProviderBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

  try {
    const res = await fetch('/admin/ai-system/update-provider', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', },
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (!data.success) {
      throw new Error(data.error || 'Update failed');
    }

    const provider = providersData.find((p) => {
      return String(p.id) === String(providerId);
    });
    if (provider) {
      provider.supported_models = models;
      provider.supported_models_select = models;
      provider.api_endpoint = payload.api_endpoint;
    }

    // Refresh model selects if needed
    const frontendProviderSelect = document.getElementById('frontendProviderSelect');
    const backendProviderSelect = document.getElementById('backendProviderSelect');
    const frontendModelSelect = document.getElementById('frontendModelSelect');
    const backendModelSelect = document.getElementById('backendModelSelect');
    const frontendModelWarning = document.getElementById('frontendModelWarning');
    const backendModelWarning = document.getElementById('backendModelWarning');
    const frontendModelRefresh = document.getElementById('frontendModelRefresh');
    const backendModelRefresh = document.getElementById('backendModelRefresh');

    if (frontendProviderSelect && frontendProviderSelect.value === 'huggingface') {
      buildModelOptions('huggingface', frontendModelSelect, '', frontendModelWarning, frontendModelRefresh)
        .then(updateAiSettingsSaveState);
    }
    if (backendProviderSelect && backendProviderSelect.value === 'huggingface') {
      buildModelOptions('huggingface', backendModelSelect, '', backendModelWarning, backendModelRefresh)
        .then(updateAiSettingsSaveState);
    }

    // Hide modal
    const modalEl = document.getElementById('editProviderModal');
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
      const modalInst = bootstrap.Modal.getInstance(modalEl);
      if (modalInst) modalInst.hide();
    } else {
      modalEl.classList.remove('show');
      modalEl.style.display = 'none';
    }
  } catch (e) {
    if (errorEl) {
      errorEl.textContent = e.message;
      errorEl.classList.remove('d-none');
    }
  } finally {
    saveProviderBtn.disabled = false;
    saveProviderBtn.innerHTML = '<i class="bi bi-save me-1"></i>Save Changes';
  }
}

// Delete provider
async function deleteProvider(id) {
  const confirmed = window.showConfirm
    ? await window.showConfirm('Are you sure you want to delete this provider?', 'Delete Provider')
    : confirm('Are you sure you want to delete this provider?');
  if (!confirmed) return;

  if (!confirmed) return;

  const csrfInput = document.querySelector('input[name="csrf_token"]');

  try {
    const res = await fetch('/api/ai-system/delete-provider', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', },
      body: JSON.stringify({
        id: parseInt(id, 10),
        csrf_token: csrfInput ? csrfInput.value : '',
      }),
    });

    const data = await res.json();
    if (!data.success) {
      throw new Error(data.error || 'Delete failed');
    }

    if (window.showAlert) {
      await window.showAlert('Provider deleted successfully.', 'Success', 'success');
    }

    // Reload page
    setTimeout(() => {
      window.location.reload();
    }, 1000);
  } catch (e) {
    const errMsg = e && e.message ? e.message : 'Failed to delete provider';
    if (window.showAlert) {
      await window.showAlert(errMsg, 'Error', 'error');
    } else {
      alert(errMsg);
    }
  }
}

// Check Ollama cloud status
function checkOllamaStatus() {
  const ollamaStatusEl = document.getElementById('ollamaLiveStatus');
  if (!ollamaStatusEl) return;

  fetchProviderModels('ollama')
    .then((models) => {
      const online = Array.isArray(models) && models.length > 0;
      ollamaStatusEl.className = `badge ${online ? 'bg-success' : 'bg-secondary'} ms-2`;
      ollamaStatusEl.textContent = online ? 'Online' : 'Offline';
    })
    .catch(() => {
      ollamaStatusEl.className = 'badge bg-secondary ms-2';
      ollamaStatusEl.textContent = 'Offline';
    });
}

// Expose functions globally for inline handlers
window.aiSystemTestConnection = testConnection;
window.aiSystemRunTest = runTestConnection;
window.aiSystemToggleProvider = toggleProviderActive;
window.aiSystemOpenEdit = openProviderEdit;
window.aiSystemDelete = deleteProvider;

export {};
