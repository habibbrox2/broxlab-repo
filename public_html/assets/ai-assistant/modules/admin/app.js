/**
 * BroxBhai AI Admin Copilot - JavaScript Module
 * Premium 2026 Redesign with enhanced features
 */

import {
  appendAssistant,
  appendMessage
} from '../../core/render.js';

// UI Element References with safe fallbacks
const UI = {
  shell: document.getElementById('adminAiShell'),
  btn: null, // No floating button for admin (embedded in interface)
  sidebar: document.getElementById('adminAiSidebar'),
  sidebarClose: document.getElementById('adminAiSidebarClose'),
  messages: document.getElementById('adminAiBody'),
  input: document.getElementById('adminAiInput'),
  sendBtn: document.getElementById('adminAiSend'),
  loading: document.getElementById('adminAiTypingIndicator'),
  history: document.getElementById('adminAiHistory'),
  historyEmpty: document.querySelector('.brox-ai-history-empty'),
  title: document.getElementById('adminAiTitle'),

  // Settings panel elements
  provider: document.getElementById('adminAiProvider'),
  model: document.getElementById('adminAiModel'),
  refreshModels: document.getElementById('adminAiRefreshModels'),
  darkModeToggle: document.getElementById('adminAiDarkModeToggle'),
  clearHistoryBtn: document.getElementById('adminAiClearHistory'),
  autoSaveToggle: document.getElementById('adminAiAutoSaveToggle'),
  webMaxResults: document.getElementById('adminAiWebMaxResults'),
  webPluginToggle: document.getElementById('adminAiWebPluginToggle'),
  responseHealingToggle: document.getElementById('adminAiResponseHealingToggle'),
  pdfEngine: document.getElementById('adminAiPdfEngine'),
  pdfPluginToggle: document.getElementById('adminAiPdfPluginToggle'),
  reasoningEffort: document.getElementById('adminAiReasoningEffort'),
  responseFormat: document.getElementById('adminAiResponseFormat'),
  advancedJson: document.getElementById('adminAiAdvancedOptionsJson'),
  fileInput: document.getElementById('adminAiFileInput'),
};

// Configuration constants
const STORAGE_KEYS = {
  HISTORY: 'brox.adminAssistant.chat.v2',
  PREFS: 'brox.adminAssistant.prefs.v2',
  LANGUAGE: 'brox.adminAssistant.language.v2',
};

const DEFAULT_PREFS = {
  provider: 'openrouter',
  model: 'openai/gpt-4-turbo',
  darkMode: true,
  autoSave: true,
  webMaxResults: 3,
  webPlugin: true,
  responseHealing: true,
  pdfEngine: 'pdf-text',
  pdfPlugin: true,
  reasoningEffort: 'medium',
  responseFormat: 'text',
};

let adminPrefs = { ...DEFAULT_PREFS, };
let chatHistory = [];

let currentLang = 'en';

// Multilingual strings
const I18N = {
  bn: {
    assistant_title: 'ব্রক্স এডমিন সহকারী',
    typing_text: 'টাইপ করছে...',
    chat_input_placeholder: 'আপনার বার্তা লিখুন...',
    no_history: 'কোন চ্যাট ইতিহাস নেই',
    new_chat: 'নতুন চ্যাট',
    clear_history_confirm: 'ইতিহাস সাফ করতে নিশ্চিত?',
    settings_saved: 'সেটিংস সংরক্ষিত হয়েছে',
    error_loading_models: 'মডেল লোড করতে ত্রুটি',
  },
  en: {
    assistant_title: 'Brox Admin Assistant',
    typing_text: 'Typing...',
    chat_input_placeholder: 'Type your message...',
    no_history: 'No chat history',
    new_chat: 'New Chat',
    clear_history_confirm: 'Are you sure you want to clear history?',
    settings_saved: 'Settings saved',
    error_loading_models: 'Error loading models',
  },
};

/**
 * Initialize the admin assistant
 */
function init() {
  loadPreferences();
  loadHistory();
  loadLanguage();
  applyLanguage();
  renderHistory();
  bindEvents();
  loadProviders();
  applyTheme();
}

/**
 * Load preferences from storage
 */
function loadPreferences() {
  try {
    const stored = window.localStorage.getItem(STORAGE_KEYS.PREFS);
    if (stored) {
      adminPrefs = { ...DEFAULT_PREFS, ...JSON.parse(stored), };
    }
  } catch (err) {
    console.error('Failed to load admin preferences:', err);
  }
}

/**
 * Save preferences to storage
 */
function savePreferences() {
  try {
    window.localStorage.setItem(STORAGE_KEYS.PREFS, JSON.stringify(adminPrefs));
  } catch (err) {
    console.error('Failed to save admin preferences:', err);
  }
}

/**
 * Load chat history from storage
 */
function loadHistory() {
  try {
    const stored = window.localStorage.getItem(STORAGE_KEYS.HISTORY);
    if (stored) {
      chatHistory = JSON.parse(stored);
    }
  } catch (err) {
    console.error('Failed to load chat history:', err);
  }
}

/**
 * Save chat history to storage
 */
function saveHistory() {
  try {
    window.localStorage.setItem(STORAGE_KEYS.HISTORY, JSON.stringify(chatHistory));
  } catch (err) {
    console.error('Failed to save chat history:', err);
  }
}

/**
 * Load language preference
 */
function loadLanguage() {
  try {
    const stored = window.localStorage.getItem(STORAGE_KEYS.LANGUAGE);
    if (stored) {
      currentLang = stored;
    }
  } catch (err) {
    console.error('Failed to load language:', err);
  }
}

/**
 * Get translated text
 */
function t(key) {
  return I18N[currentLang]?.[key] || I18N.en[key] || key;
}

/**
 * Apply theme based on preference
 */
function applyTheme() {
  if (adminPrefs.darkMode && UI.shell) {
    UI.shell.classList.add('brox-ai-dark-mode');
  } else if (UI.shell) {
    UI.shell.classList.remove('brox-ai-dark-mode');
  }
}

/**
 * Apply language strings to UI
 */
function applyLanguage() {
  // Update placeholders and labels
  if (UI.input) {
    UI.input.setAttribute('placeholder', t('chat_input_placeholder'));
  }

  // Update toggle labels
  const darkModeLabel = UI.darkModeToggle?.parentElement?.querySelector('.brox-ai-setting-label');
  if (darkModeLabel) {
    darkModeLabel.textContent = 'Dark Mode';
  }
}

/**
 * Load available AI providers
 */
async function loadProviders() {
  try {
    if (!UI.provider) return;

    // Fetch providers from backend or use defaults
    const response = await fetch('/api/ai/providers', {
      credentials: 'same-origin',
    }).catch(() => null);

    const providers = response?.ok ? await response.json() : [
      { id: 'openrouter', name: 'OpenRouter', },
      { id: 'fireworks', name: 'Fireworks', },
      { id: 'puter-js', name: 'Puter.js', },
    ];

    UI.provider.innerHTML = providers
      .map(p => `<option value="${p.id}">${p.name}</option>`)
      .join('');

    UI.provider.value = adminPrefs.provider;
  } catch (err) {
    console.error('Failed to load providers:', err);
  }
}

/**
 * Load available AI models for selected provider
 */
async function loadModels() {
  try {
    if (!UI.model || !UI.provider) return;

    const provider = UI.provider.value;
    UI.model.innerHTML = '<option value="">Loading...</option>';

    const response = await fetch(`/api/ai/providers/${provider}/models`, {
      credentials: 'same-origin',
    }).catch(() => null);

    let models = [];
    if (response?.ok) {
      models = await response.json();
    } else {
      // Fallback models
      models = provider === 'openrouter'
        ? [{ id: 'openai/gpt-4-turbo', name: 'GPT-4 Turbo', },]
        : [{ id: 'gpt-4', name: 'GPT-4', },];
    }

    UI.model.innerHTML = models
      .map(m => `<option value="${m.id}">${m.name}</option>`)
      .join('');

    UI.model.value = adminPrefs.model;
  } catch (err) {
    console.error(t('error_loading_models'), err);
  }
}

/**
 * Render chat history in the messages panel
 */
function renderHistory() {
  if (!UI.messages) return;

  // Clear existing messages
  UI.messages.innerHTML = '';

  if (!chatHistory.length) {
    if (UI.historyEmpty) {
      UI.historyEmpty.classList.remove('d-none');
    }
    return;
  }

  if (UI.historyEmpty) {
    UI.historyEmpty.classList.add('d-none');
  }

  // Render each message
  chatHistory.forEach(msg => {
    appendMessage(UI.messages, msg.role, msg.text, {
      ts: msg.ts,
      responseMs: msg.responseMs,
    });
  });
}

/**
 * Clear chat history with confirmation
 */
function clearHistory() {
  if (!window.confirm(t('clear_history_confirm'))) return;

  chatHistory = [];
  saveHistory();
  renderHistory();
}

/**
 * Handle user message submission
 */
async function handleUserMessage() {
  const text = String(UI.input?.value || '').trim();
  if (!text) return;

  UI.input.value = '';

  // Add message to history
  const ts = new Date().toISOString();
  chatHistory.push({ role: 'user', text, ts, });
  saveHistory();

  // Display message
  appendMessage(UI.messages, 'user', text, { ts, });

  // Show loading state
  if (UI.loading) {
    UI.loading.classList.remove('d-none');
  }

  try {
    // Build system prompt
    const systemPrompt = [
      'You are Brox, the professional admin assistant for BroxLab.',
      'Provide clear, actionable guidance for administrative tasks.',
      'Keep responses concise but comprehensive.',
      'If uncertain, ask clarifying questions.',
    ].join('\n');

    // Call AI provider
    const messages = [
      { role: 'system', content: systemPrompt, },
      ...chatHistory.map(msg => ({
        role: msg.role,
        content: msg.text,
      })),
    ];

    const response = await callAI(messages);
    const reply = response.choices?.[0]?.message?.content || 'No response';

    // Add response to history
    const responseTs = new Date().toISOString();
    const responseMs = new Date().getTime() - new Date(ts).getTime();
    chatHistory.push({ role: 'assistant', text: reply, ts: responseTs, responseMs, });
    saveHistory();

    // Display response with animation
    await appendAssistant(UI.messages, reply, { animate: true, });

  } catch (err) {
    console.error('Error calling AI:', err);
    await appendAssistant(UI.messages, `Error: ${err.message}`, { animate: true, });
  } finally {
    if (UI.loading) {
      UI.loading.classList.add('d-none');
    }
  }
}

/**
 * Call selected AI provider
 */
async function callAI(messages) {
  const provider = adminPrefs.provider;
  const model = adminPrefs.model;

  if (provider === 'openrouter') {
    return await callOpenRouter(messages, { model, });
  } else if (provider === 'fireworks') {
    return await callFireworks(messages, { model, });
  }

  throw new Error(`Unsupported provider: ${provider}`);
}

/**
 * Call OpenRouter API
 */
async function callOpenRouter(messages, options = {}) {
  const apiKey = window.OPENROUTER_API_KEY || '';
  if (!apiKey) {
    throw new Error('OpenRouter API key not configured');
  }

  const response = await fetch('https://openrouter.ai/api/v1/chat/completions', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${apiKey}`,
      'HTTP-Referer': window.location.origin,
      'X-OpenRouter-Title': 'BroxBhai Admin Assistant',
    },
    body: JSON.stringify({
      model: options.model || 'openai/gpt-4-turbo',
      messages,
      temperature: 0.7,
      max_tokens: 2000,
    }),
  });

  if (!response.ok) {
    throw new Error(`OpenRouter API error: ${response.statusText}`);
  }

  return await response.json();
}

/**
 * Call Fireworks API
 */
async function callFireworks(messages, options = {}) {
  const apiKey = window.FIREWORKS_API_KEY || '';
  if (!apiKey) {
    throw new Error('Fireworks API key not configured');
  }

  const response = await fetch('https://api.fireworks.ai/inference/v1/chat/completions', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${apiKey}`,
    },
    body: JSON.stringify({
      model: options.model || 'accounts/fireworks/models/llama-v2-7b-chat',
      messages,
    }),
  });

  if (!response.ok) {
    throw new Error(`Fireworks API error: ${response.statusText}`);
  }

  return await response.json();
}

/**
 * Bind event listeners
 */
function bindEvents() {
  // Send message on button click
  UI.sendBtn?.addEventListener('click', handleUserMessage);

  // Send message on Enter key
  UI.input?.addEventListener('keypress', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleUserMessage();
    }
  });

  // Sidebar close button
  UI.sidebarClose?.addEventListener('click', () => {
    if (UI.sidebar) {
      UI.sidebar.classList.add('brox-ai-collapsed');
      UI.sidebar.classList.remove('brox-ai-expanded');
    }
  });

  // Provider change
  UI.provider?.addEventListener('change', async () => {
    adminPrefs.provider = UI.provider.value;
    savePreferences();
    await loadModels();
  });

  // Model change
  UI.model?.addEventListener('change', () => {
    adminPrefs.model = UI.model.value;
    savePreferences();
  });

  // Refresh models button
  UI.refreshModels?.addEventListener('click', loadModels);

  // Dark mode toggle
  UI.darkModeToggle?.addEventListener('click', () => {
    adminPrefs.darkMode = !adminPrefs.darkMode;
    savePreferences();
    applyTheme();
  });

  // Clear history button
  UI.clearHistoryBtn?.addEventListener('click', clearHistory);

  // Auto-save toggle
  UI.autoSaveToggle?.addEventListener('click', () => {
    adminPrefs.autoSave = !adminPrefs.autoSave;
    savePreferences();
  });

  // Web plugin settings
  UI.webPluginToggle?.addEventListener('click', () => {
    adminPrefs.webPlugin = !adminPrefs.webPlugin;
    savePreferences();
  });

  UI.webMaxResults?.addEventListener('change', () => {
    adminPrefs.webMaxResults = parseInt(UI.webMaxResults.value) || 3;
    savePreferences();
  });

  // Response healing toggle
  UI.responseHealingToggle?.addEventListener('click', () => {
    adminPrefs.responseHealing = !adminPrefs.responseHealing;
    savePreferences();
  });

  // PDF plugin settings
  UI.pdfPluginToggle?.addEventListener('click', () => {
    adminPrefs.pdfPlugin = !adminPrefs.pdfPlugin;
    savePreferences();
  });

  UI.pdfEngine?.addEventListener('change', () => {
    adminPrefs.pdfEngine = UI.pdfEngine.value;
    savePreferences();
  });

  // Reasoning effort
  UI.reasoningEffort?.addEventListener('change', () => {
    adminPrefs.reasoningEffort = UI.reasoningEffort.value;
    savePreferences();
  });

  // Response format
  UI.responseFormat?.addEventListener('change', () => {
    adminPrefs.responseFormat = UI.responseFormat.value;
    savePreferences();
  });

  // Advanced JSON
  UI.advancedJson?.addEventListener('change', () => {
    try {
      if (UI.advancedJson.value) {
        JSON.parse(UI.advancedJson.value);
      }
    } catch (err) {
      console.error('Invalid JSON in advanced options:', err);
    }
  });

  // Update UI from preferences on load
  if (UI.provider) UI.provider.value = adminPrefs.provider;
  if (UI.model) UI.model.value = adminPrefs.model;
  if (UI.webMaxResults) UI.webMaxResults.value = adminPrefs.webMaxResults;
  if (UI.reasoningEffort) UI.reasoningEffort.value = adminPrefs.reasoningEffort;
  if (UI.responseFormat) UI.responseFormat.value = adminPrefs.responseFormat;
  if (UI.pdfEngine) UI.pdfEngine.value = adminPrefs.pdfEngine;
}

// Initialize on DOMContentLoaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}

// Export for testing if needed
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    init,
    handleUserMessage,
    clearHistory,
    loadProviders,
    loadModels,
  };
}
