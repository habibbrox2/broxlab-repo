/**
 * AI System Admin - Vanilla JavaScript
 * Handles provider management, model loading, and connection testing
 * Uses SweetAlert2 for all alerts via window.showAlert()
 */

(function () {
    'use strict';

    // Global state
    let currentTestProviderId = null;
    let providersData = [];
    let savedFrontendModel = '';
    let savedBackendModel = '';
    let defaultModelSetting = '';

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function () {
        initAISystem();
    });

    function initAISystem() {
        // Load providers from global Twig variable if available
        if (typeof window.aiSystemProviders !== 'undefined') {
            providersData = window.aiSystemProviders;
        }

        // Load settings from global variable if available
        if (typeof window.aiSystemSettings !== 'undefined') {
            savedFrontendModel = window.aiSystemSettings.frontend_model || '';
            savedBackendModel = window.aiSystemSettings.backend_model || '';
            defaultModelSetting = window.aiSystemSettings.default_model || '';
        } else {
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
        document.querySelectorAll('.toggle-provider').forEach(function (inputEl) {
            inputEl.addEventListener('change', function () {
                toggleProviderActive(this);
            });
        });

        // Test connection buttons
        document.querySelectorAll('[data-action="test-connection"]').forEach(function (btn) {
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
        document.querySelectorAll('[data-action="edit-provider"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const id = this.getAttribute('data-provider-id');
                if (id) openProviderEdit(id);
            });
        });

        // Delete provider buttons
        document.querySelectorAll('[data-action="delete-provider"]').forEach(function (btn) {
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
            frontendModelRefresh.addEventListener('click', function () {
                buildModelOptions(
                    frontendProviderSelect.value,
                    frontendModelSelect,
                    '',
                    frontendModelWarning,
                    frontendModelRefresh
                ).then(updateAiSettingsSaveState);
            });
        }

        const backendModelRefresh = document.getElementById('backendModelRefresh');
        const backendProviderSelect = document.getElementById('backendProviderSelect');
        const backendModelSelect = document.getElementById('backendModelSelect');
        const backendModelWarning = document.getElementById('backendModelWarning');

        if (backendModelRefresh && backendProviderSelect) {
            backendModelRefresh.addEventListener('click', function () {
                buildModelOptions(
                    backendProviderSelect.value,
                    backendModelSelect,
                    '',
                    backendModelWarning,
                    backendModelRefresh
                ).then(updateAiSettingsSaveState);
            });
        }

        // Reset model defaults
        const resetModelDefaults = document.getElementById('resetModelDefaults');
        if (resetModelDefaults) {
            resetModelDefaults.addEventListener('click', function () {
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

        // API key password toggle
        document.querySelectorAll('.toggle-password').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const input = this.previousElementSibling;
                if (input.type === 'password') {
                    input.type = 'text';
                    this.querySelector('i').classList.remove('bi-eye');
                    this.querySelector('i').classList.add('bi-eye-slash');
                } else {
                    input.type = 'password';
                    this.querySelector('i').classList.remove('bi-eye-slash');
                    this.querySelector('i').classList.add('bi-eye');
                }
            });
        });
    }

    // HuggingFace tips toggle
    function updateHfTips(providerName) {
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
    async function buildModelOptions(providerName, selectEl, savedModel, warningEl, refreshBtn) {
        return new Promise(function (resolve) {
            if (!selectEl) {
                resolve();
                return;
            }

            const reqId = Date.now() + '_' + Math.random().toString(36).slice(2, 8);
            selectEl.dataset.reqId = reqId;
            setRefreshState(refreshBtn, selectEl, true);
            buildLoadingOption(selectEl, 'Loading models...');
            if (warningEl) warningEl.classList.add('d-none');

            fetchProviderModels(providerName)
                .then(function (models) {
                    if (selectEl.dataset.reqId !== reqId) {
                        resolve();
                        return;
                    }
                    renderModelOptions(selectEl, models, savedModel, providerName, warningEl, refreshBtn, false, '');
                    resolve();
                })
                .catch(function (e) {
                    if (selectEl.dataset.reqId !== reqId) {
                        resolve();
                        return;
                    }
                    const fetchError = e && e.message ? e.message : 'Fetch failed';
                    console.warn('[AI Models] Using fallback list', providerName, e);

                    const provider = providersData.find(function (p) {
                        return p.provider_name === providerName;
                    });
                    const fallbackMap = getProviderModelMap(provider, true);
                    const models = Object.entries(fallbackMap).map(function (item) {
                        return { id: item[0], name: item[1] };
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
            models = models.filter(function (m) { return isHuggingFaceChatModelId(m.id); });
            hiddenNonChat = before > models.length;
        }

        if (!models.length) {
            buildLoadingOption(selectEl, 'No models available');
            if (warningEl) {
                let msg = '';
                if (providerName === 'huggingface') {
                    msg = 'No chat-capable Hugging Face models available for /v1/responses.';
                } else if (usedFallback) {
                    msg = fetchError
                        ? 'Remote models unavailable (' + fetchError + '). No config models found.'
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

        models.forEach(function (m) {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.name;
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
                    ? 'Remote fetch failed (' + fetchError + '). Using config model list.'
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
            selectEl.disabled = !!isLoading;
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
    async function fetchProviderModels(providerName) {
        const res = await fetch('/api/ai/models?provider=' + encodeURIComponent(providerName) + '&scope=admin', {
            credentials: 'same-origin'
        });

        if (!res.ok) {
            console.warn('[AI Models] Fetch failed', providerName, res.status);
            throw new Error('HTTP ' + res.status);
        }

        const raw = await res.text();
        let data = null;

        try {
            data = JSON.parse(raw);
        } catch (e) {
            console.warn('[AI Models] Non-JSON response', providerName, raw);
            throw new Error('Non-JSON response');
        }

        if (!data || data.success === false) {
            const apiError = data && data.error ? String(data.error) : 'API returned success=false';
            console.warn('[AI Models] API error', providerName, apiError);
            throw new Error(apiError);
        }

        if (!Array.isArray(data.models)) {
            console.warn('[AI Models] Invalid models payload', providerName, data);
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

        Object.keys(models).forEach(function (id) {
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
            're-rank'
        ];
        return !blocked.some(function (pattern) {
            return lower.includes(pattern);
        });
    }

    // Select model by ID
    function selectModelById(selectEl, modelId) {
        if (!selectEl) return false;
        const options = Array.from(selectEl.options || []);
        const match = options.find(function (opt) { return opt.value === modelId; });
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

    // Test connection function
    function testConnection(id) {
        currentTestProviderId = id;

        const provider = providersData.find(function (p) {
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
            providersData.forEach(function (p) {
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
        var loadModelsForProvider = function (providerRow) {
            select.innerHTML = '<option value="">Loading models...</option>';
            if (warningDiv) warningDiv.classList.add('d-none');

            fetchProviderModels(providerRow.provider_name)
                .then(function (models) {
                    select.innerHTML = '';
                    models.forEach(function (m) {
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
                .catch(function (e) {
                    var fallback = getProviderModelMap(providerRow, true);
                    select.innerHTML = '';
                    Object.keys(fallback).forEach(function (key) {
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
                        var errMsg = e && e.message ? e.message : 'fetch failed';
                        warningDiv.textContent = 'Remote models unavailable (' + errMsg + '). Using fallback list.';
                        warningDiv.classList.remove('d-none');
                    }
                });
        };

        loadModelsForProvider(provider);

        // Provider select change handler
        if (providerSelect) {
            providerSelect.onchange = function () {
                var newId = providerSelect.value;
                currentTestProviderId = newId;
                var selectedProvider = providersData.find(function (p) {
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
            var modal = new bootstrap.Modal(modalEl);
            modal.show();
        } else {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
        }
    }

    // Run test connection
    async function runTestConnection() {
        if (!currentTestProviderId) return;

        var modelSelect = document.getElementById('testModelSelect');
        var model = modelSelect ? modelSelect.value : '';
        var resultDiv = document.getElementById('testConnectionResult');
        var btn = document.querySelector('#testConnectionModal .btn-primary');
        var warningDiv = document.getElementById('testConnectionWarning');
        var csrfToken = document.querySelector('input[name="csrf_token"]');

        if (!model) {
            if (window.showAlert) {
                await window.showAlert('Please select a model before testing.', 'Warning', 'warning');
            } else {
                alert('Please select a model before testing.');
            }
            return;
        }

        var originalBtnHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Testing...';
        resultDiv.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary"></div><p class="mt-2">Sending test request...</p></div>';

        try {
            var csrfTokenValue = csrfToken ? csrfToken.value : '';
            var response = await fetch('/api/ai-system/test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: currentTestProviderId,
                    model: model,
                    csrf_token: csrfTokenValue
                })
            });

            var data = await response.json();

            if (data.success) {
                if (window.showAlert) {
                    await window.showAlert(
                        '<strong>Model:</strong> ' + data.model + '<br><br><strong>Response:</strong> ' + (data.response || 'OK'),
                        'Connection Successful!',
                        'success',
                        { allowHtml: true }
                    );
                } else {
                    alert('Connection successful!\n\nModel: ' + data.model + '\nResponse: ' + (data.response || 'OK'));
                }
            } else {
                if (window.showAlert) {
                    await window.showAlert(
                        data.error || 'Unknown error occurred',
                        'Connection Failed',
                        'error'
                    );
                } else {
                    alert('Connection failed: ' + (data.error || 'Unknown error'));
                }
            }

            var provider = providersData.find(function (p) {
                return String(p.id) === String(currentTestProviderId);
            });
            if (provider) {
                setLastTested(provider.provider_name);
                updateStatusPills(provider.provider_name);
            }
        } catch (e) {
            if (window.showAlert) {
                await window.showAlert('Network error: ' + e.message, 'Error', 'error');
            } else {
                alert('Network error: ' + e.message);
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalBtnHtml;
        }
    }

    // Toggle provider active state
    async function toggleProviderActive(inputEl) {
        var id = inputEl && inputEl.dataset ? inputEl.dataset.providerId : null;
        if (!id) return;

        var rowEl = inputEl.closest('tr');
        var active = !!inputEl.checked;
        inputEl.disabled = true;

        try {
            var res = await fetch('/api/ai-system/toggle-provider', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(id, 10), active })
            });

            var data = await res.json();
            if (!data.success) {
                throw new Error(data.error || 'Update failed');
            }

            updateProviderRowState(rowEl, active);

            var provider = providersData.find(function (p) {
                return String(p.id) === String(id);
            });
            if (provider) {
                provider.is_active = active;
            }
        } catch (e) {
            inputEl.checked = !active;
            var errMsg = e && e.message ? e.message : 'Failed to update provider';
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

        var badge = rowEl.querySelector('td[data-label="Status"] .badge');
        if (badge) {
            badge.className = 'badge bg-' + (isActive ? 'success' : 'secondary');
            badge.textContent = isActive ? 'Active' : 'Inactive';
        }

        var defaultBtn = rowEl.querySelector('.set-default-btn');
        if (defaultBtn) {
            defaultBtn.disabled = !isActive;
        }
    }

    // Status pills functions
    function getLastTested(providerName) {
        var raw = localStorage.getItem('brox.ai.last_tested.' + providerName) || '';
        if (!raw) return 'Not tested';
        var date = new Date(raw);
        if (Number.isNaN(date.getTime())) return 'Not tested';
        return date.toLocaleString();
    }

    function setLastTested(providerName) {
        localStorage.setItem('brox.ai.last_tested.' + providerName, new Date().toISOString());
    }

    function updateStatusPills(providerName) {
        var apiKeyPill = document.getElementById('aiStatusApiKey');
        var activePill = document.getElementById('aiStatusActive');
        var testedPill = document.getElementById('aiStatusTested');

        var provider = providersData.find(function (p) {
            return p.provider_name === providerName;
        });

        if (!provider) return;

        if (apiKeyPill) {
            apiKeyPill.className = 'badge ' + (provider.has_api_key ? 'bg-success' : 'bg-warning text-dark');
            apiKeyPill.textContent = provider.has_api_key ? 'API Key: Present' : 'API Key: Missing';
        }

        if (activePill) {
            activePill.className = 'badge ' + (provider.is_active ? 'bg-success' : 'bg-secondary');
            activePill.textContent = provider.is_active ? 'Provider: Active' : 'Provider: Inactive';
        }

        if (testedPill) {
            testedPill.className = 'badge bg-info text-dark';
            testedPill.textContent = 'Last tested: ' + getLastTested(providerName);
        }
    }

    // Open provider edit modal
    function openProviderEdit(id) {
        var provider = providersData.find(function (p) {
            return String(p.id) === String(id);
        });

        if (!provider || provider.provider_name !== 'huggingface') return;

        var modalEl = document.getElementById('editProviderModal');
        var idInput = document.getElementById('editProviderId');
        var endpointInput = document.getElementById('editProviderEndpoint');
        var modelsInput = document.getElementById('editProviderModels');
        var errorEl = document.getElementById('editProviderError');

        if (idInput) idInput.value = provider.id;
        if (endpointInput) endpointInput.value = provider.api_endpoint || (provider.config && provider.config.endpoint) || '';
        if (modelsInput) modelsInput.value = JSON.stringify(provider.supported_models || {}, null, 2);
        if (errorEl) errorEl.classList.add('d-none');

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var modal = new bootstrap.Modal(modalEl);
            modal.show();
        } else {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
        }
    }

    // Save provider config
    async function saveProviderConfig() {
        var idInput = document.getElementById('editProviderId');
        var endpointInput = document.getElementById('editProviderEndpoint');
        var modelsInput = document.getElementById('editProviderModels');
        var errorEl = document.getElementById('editProviderError');
        var csrfInput = document.querySelector('input[name="csrf_token"]');

        var providerId = 0;
        if (idInput && idInput.value) {
            providerId = parseInt(idInput.value, 10);
        }
        if (!providerId) return;

        var models = {};
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

        var payload = {
            action: 'update_config',
            provider_id: providerId,
            api_endpoint: endpointInput ? endpointInput.value : '',
            supported_models: models,
            csrf_token: csrfInput ? csrfInput.value : ''
        };

        var saveProviderBtn = document.getElementById('saveProviderConfig');
        saveProviderBtn.disabled = true;
        saveProviderBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

        try {
            var res = await fetch('/admin/ai-system/update-provider', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            var data = await res.json();
            if (!data.success) {
                throw new Error(data.error || 'Update failed');
            }

            var provider = providersData.find(function (p) {
                return String(p.id) === String(providerId);
            });
            if (provider) {
                provider.supported_models = models;
                provider.supported_models_select = models;
                provider.api_endpoint = payload.api_endpoint;
            }

            // Refresh model selects if needed
            var frontendProviderSelect = document.getElementById('frontendProviderSelect');
            var backendProviderSelect = document.getElementById('backendProviderSelect');
            var frontendModelSelect = document.getElementById('frontendModelSelect');
            var backendModelSelect = document.getElementById('backendModelSelect');
            var frontendModelWarning = document.getElementById('frontendModelWarning');
            var backendModelWarning = document.getElementById('backendModelWarning');
            var frontendModelRefresh = document.getElementById('frontendModelRefresh');
            var backendModelRefresh = document.getElementById('backendModelRefresh');

            if (frontendProviderSelect && frontendProviderSelect.value === 'huggingface') {
                buildModelOptions('huggingface', frontendModelSelect, '', frontendModelWarning, frontendModelRefresh)
                    .then(updateAiSettingsSaveState);
            }
            if (backendProviderSelect && backendProviderSelect.value === 'huggingface') {
                buildModelOptions('huggingface', backendModelSelect, '', backendModelWarning, backendModelRefresh)
                    .then(updateAiSettingsSaveState);
            }

            // Hide modal
            var modalEl = document.getElementById('editProviderModal');
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var modalInst = bootstrap.Modal.getInstance(modalEl);
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
        var confirmed = false;

        if (window.showConfirm) {
            confirmed = await window.showConfirm('Are you sure you want to delete this provider?', 'Delete Provider');
        } else {
            confirmed = confirm('Are you sure you want to delete this provider?');
        }

        if (!confirmed) return;

        var csrfInput = document.querySelector('input[name="csrf_token"]');

        try {
            var res = await fetch('/admin/ai-system/delete-provider', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: parseInt(id, 10),
                    csrf_token: csrfInput ? csrfInput.value : ''
                })
            });

            var data = await res.json();
            if (!data.success) {
                throw new Error(data.error || 'Delete failed');
            }

            if (window.showAlert) {
                await window.showAlert('Provider deleted successfully.', 'Success', 'success');
            }

            // Reload page
            setTimeout(function () {
                window.location.reload();
            }, 1000);
        } catch (e) {
            var errMsg = e && e.message ? e.message : 'Failed to delete provider';
            if (window.showAlert) {
                await window.showAlert(errMsg, 'Error', 'error');
            } else {
                alert(errMsg);
            }
        }
    }

    // Check Ollama status
    function checkOllamaStatus() {
        var ollamaStatusEl = document.getElementById('ollamaLiveStatus');
        if (!ollamaStatusEl) return;

        fetchProviderModels('ollama')
            .then(function (models) {
                var online = Array.isArray(models) && models.length > 0;
                ollamaStatusEl.className = 'badge ' + (online ? 'bg-success' : 'bg-secondary') + ' ms-2';
                ollamaStatusEl.textContent = online ? 'Online' : 'Offline';
            })
            .catch(function () {
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

})();
