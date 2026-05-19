/**
 * BroxBhai AI Admin Copilot - JavaScript Module
 * Premium 2026 Redesign with enhanced features
 */

import {
  appendAssistant,
  appendMessage,
  sanitizeHtml
} from '../../core/render.js';
import { createThinkingIndicator } from '../../core/thinking.js';
import { getModelCache, initializeModelCache } from '../../core/cache.js';

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
  thinkingIndicator: document.getElementById('adminAiThinkingIndicator'),
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
  model: 'meta-llama/llama-3-8b-instruct:free',
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
let adminThinking = null;

let currentLang = 'en';

// Multilingual strings
const I18N = {
  bn: {
    assistant_title: 'ব্রক্স এডমিন সহকারী',
    typing_text: 'টাইপ করছে...',
    chat_input_placeholder: 'আপনার বার্তা লিখুন...',
    thinking_understanding: 'অনুরোধ বুঝছে...',
    thinking_planning: 'উত্তর পরিকল্পনা করছে...',
    thinking_generating: 'চূড়ান্ত উত্তর তৈরি করছে...',
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
    thinking_understanding: 'Understanding request...',
    thinking_planning: 'Planning response...',
    thinking_generating: 'Generating final answer...',
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
  adminThinking = createThinkingIndicator(UI.thinkingIndicator, {
    aiName: 'Brox Admin',
    initialStatus: t('thinking_understanding') || 'Thinking...',
  });
  renderHistory();
  bindEvents();
  loadProviders();
  applyTheme();

  // Initialize model cache
  initializeModelCache(['openrouter',], {
    ttl: 24 * 60 * 60 * 1000, // 24 hours
    storageKey: 'brox.admin.models.cache',
  });
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

    // Fetch providers from backend
    const response = await fetch('/api/ai/providers', {
      credentials: 'same-origin',
    }).catch(() => null);

    const providers = response?.ok ? await response.json() : [
      { id: 'openrouter', name: 'OpenRouter', },
    ];

    UI.provider.innerHTML = providers
      .map(p => `<option value="${p.id || p.name}">${p.name}</option>`)
      .join('');

    if (UI.provider.value === '') {
      UI.provider.value = adminPrefs.provider || (providers[0]?.id || providers[0]?.name);
    }
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

    // Get model cache instance
    const cache = getModelCache();

    // Fetch models from cache or API
    const result = await cache.fetch(provider, {
      timeout: 10000,
      cacheTTL: 24 * 60 * 60 * 1000, // 24 hours
    });

    let models = result.models || [];

    // Ensure models is an array of objects
    if (!Array.isArray(models)) {
      models = [];
    }

    // Render models if we have any
    if (models.length > 0) {
      UI.model.innerHTML = models
        .map(m => `<option value="${m.id || m.model || m.name}">${m.name || m.model || m.id}</option>`)
        .join('');

      // Set selected model
      if (adminPrefs.model && UI.model.value !== adminPrefs.model) {
        UI.model.value = adminPrefs.model;
      }
      if (UI.model.value === '') {
        UI.model.value = adminPrefs.model;
      }

      // Log cache status
      const logMsg = result.fromCache
        ? `[Cache] ${models.length} models loaded${result.isStale ? ' (stale)' : ''}`
        : `[Fresh] ${models.length} models fetched from API`;
      if (window.BroxBridgeIntegration?.config?.debugMode) {
        console.info(`[ModelCache] ${logMsg} for ${provider}`);
      }
    } else {
      // Fallback models - prefer free models
      const fallbackModels = provider === 'openrouter'
        ? [
            { id: 'meta-llama/llama-3-8b-instruct:free', name: 'Llama 3.8B (Free)', },
            { id: 'google/gemini-2.0-flash-exp:free', name: 'Gemini 2.0 Flash (Free)', },
            { id: 'google/gemma-2-9b-it:free', name: 'Gemma 2.9B (Free)', },
          ]
        : [{ id: 'gpt-3.5-turbo', name: 'GPT-3.5 Turbo', },];

      UI.model.innerHTML = fallbackModels
        .map(m => `<option value="${m.id}">${m.name}</option>`)
        .join('');

      UI.model.value = adminPrefs.model || fallbackModels[0].id;
    }
  } catch (err) {
    console.error(t('error_loading_models'), err);

    // Always have fallback - use free models
    UI.model.innerHTML = '<option value="meta-llama/llama-3-8b-instruct:free">Llama 3.8B (Free)</option><option value="google/gemma-2-9b-it:free">Gemma 2.9B (Free)</option>';
    UI.model.value = 'meta-llama/llama-3-8b-instruct:free';
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

function applyThinkingEvent(event) {
  if (!adminThinking || !event || typeof event !== 'object') return;
  if (typeof event.step === 'number') {
    adminThinking.setStep(event.step);
  }
  if (event.status) {
    adminThinking.setStatus(event.status);
  }
  if (event.toolLabel) {
    adminThinking.setToolLabel(event.toolLabel);
  }
  if (event.toolName) {
    adminThinking.setToolLabel(`${event.toolName}${event.status ? `: ${event.status}` : ''}`);
  }
}

function renderAssistantText(text) {
  return sanitizeHtml(text || '').replace(/\n/g, '<br>');
}

function updateAssistantText(element, text) {
  if (!element) return;
  element.textContent = text;
  if (UI.messages) {
    UI.messages.scrollTop = UI.messages.scrollHeight;
  }
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
  if (adminThinking) {
    adminThinking.show().setStep(0).setStatus(t('thinking_understanding') || 'Understanding request...');
  }

  if (adminThinking) {
    adminThinking.setStep(1).setStatus(t('thinking_planning') || 'Planning response...');
  }
  // System prompt is now handled by the backend PromptLoader
  // Build messages array with chat history
  const messages = chatHistory.map(msg => ({
    role: msg.role,
    content: msg.text,
  }));

  const assistantMessage = appendAssistant(UI.messages, '', { animate: false, });
  const assistantTextEl = assistantMessage?.querySelector('.brox-ai-message-text');
  let finalReply = '';

  try {
    const response = await callAI(messages, (event) => {
      if (!event) return;
      if (event.event === 'content' && typeof event.content === 'string') {
        finalReply += event.content;
        updateAssistantText(assistantTextEl, finalReply);
        return;
      }

      applyThinkingEvent(event);
    });

    const reply = finalReply || response.content || 'No response';
    if (assistantTextEl) {
      assistantTextEl.innerHTML = renderAssistantText(reply);
    }

    // Add response to history
    const responseTs = new Date().toISOString();
    const responseMs = new Date().getTime() - new Date(ts).getTime();
    chatHistory.push({ role: 'assistant', text: reply, ts: responseTs, responseMs, });
    saveHistory();

    if (adminThinking) {
      adminThinking.setStep(3).setStatus(t('thinking_generating') || 'Generating final answer...');
    }
  } catch (err) {
    console.error('Error calling AI:', err);
    if (assistantTextEl) {
      assistantTextEl.textContent = `Error: ${err?.message || err}`;
    }
  } finally {
    if (UI.loading) {
      UI.loading.classList.add('d-none');
    }
    if (adminThinking) {
      adminThinking.hide();
    }
  }
}

/**
 * Call selected AI provider via backend API
 */
async function callAI(messages, onEvent = () => { }) {
  const model = adminPrefs.model;
  const requestBody = {
    messages,
    options: {
      model,
      temperature: 0.7,
      maxTokens: 2000,
    },
    stream: true,
  };

  if (UI.advancedJson?.value) {
    try {
      Object.assign(requestBody, JSON.parse(UI.advancedJson.value));
    } catch (err) {
      throw new Error(`Advanced request JSON invalid: ${err.message}`);
    }
  }

  let response;
  try {
    response = await fetch('/api/admin/ai/chat', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify(requestBody),
    });
  } catch (networkError) {
    console.error('Network error sending AI request:', networkError, requestBody);
    throw new Error(`Network error while sending chat request: ${networkError.message}`);
  }

  if (!response.ok) {
    const rawText = await response.text();
    let errorDetails = rawText;
    try {
      const parsed = JSON.parse(rawText);
      errorDetails = parsed.error || JSON.stringify(parsed, null, 2);
    } catch (err) {
      errorDetails = rawText.trim() || `${response.status} ${response.statusText}`;
    }

    console.error('AI backend request failed', {
      status: response.status,
      statusText: response.statusText,
      requestBody,
      responseText: rawText,
      errorDetails,
    });

    throw new Error(`Backend API error: ${errorDetails}`);
  }

  if (!response.body) {
    throw new Error('Stream response body is empty');
  }

  const reader = response.body.getReader();
  const decoder = new TextDecoder('utf-8');
  let buffer = '';
  let fullText = '';
  let done = false;
  let finalMeta = null;

  while (!done) {
    const { value, done: streamDone, } = await reader.read();
    if (streamDone) break;
    buffer += decoder.decode(value, { stream: true, });
    const lines = buffer.split(/\r?\n/);
    buffer = lines.pop();

    for (const rawLine of lines) {
      const line = rawLine.trim();
      if (!line || !line.startsWith('data:')) continue;
      const payload = line.slice(5).trim();

      if (payload === '[DONE]') {
        done = true;
        break;
      }

      let eventData;
      try {
        eventData = JSON.parse(payload);
      } catch (err) {
        console.warn('Could not parse SSE payload:', payload, err);
        continue;
      }

      if (eventData.error) {
        throw new Error(eventData.error);
      }

      if (eventData.content) {
        fullText += eventData.content;
        onEvent({ event: 'content', content: eventData.content, fullText, });
        continue;
      }

      if (eventData.event || eventData.step !== undefined || eventData.status || eventData.toolName || eventData.toolLabel) {
        onEvent(eventData);
        continue;
      }

      if (eventData.done) {
        done = true;
        finalMeta = eventData.meta || null;
      }
    }
  }

  if (buffer.trim()) {
    const trimmed = buffer.trim();
    if (trimmed.startsWith('data:')) {
      const payload = trimmed.slice(5).trim();
      if (payload !== '[DONE]') {
        try {
          const eventData = JSON.parse(payload);
          if (eventData.content) {
            fullText += eventData.content;
            onEvent({ event: 'content', content: eventData.content, fullText, });
          } else if (eventData.event || eventData.step !== undefined || eventData.status || eventData.toolName || eventData.toolLabel) {
            onEvent(eventData);
          } else if (eventData.done) {
            finalMeta = eventData.meta || null;
          }
        } catch {
          // ignore incomplete buffer
        }
      }
    }
  }

  return {
    content: fullText,
    meta: finalMeta,
  };
}

/**
 * Call OpenRouter API (legacy - kept for compatibility)
 */
async function _callOpenRouter(messages, _options = {}) {
  // Redirect to backend API
  return await callAI(messages);
}

/**
 * Call Fireworks API (legacy - kept for compatibility)
 */
async function _callFireworks(messages, _options = {}) {
  // Redirect to backend API
  return await callAI(messages);
}

if (typeof window !== 'undefined') {
  window._callOpenRouter = _callOpenRouter;
  window._callFireworks = _callFireworks;
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
