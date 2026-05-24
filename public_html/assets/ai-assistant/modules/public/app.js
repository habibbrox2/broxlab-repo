import {
  appendAssistant,
  appendMessage,
  buildStaticReplyMatcher,
  parseResponseConfig,
  typeMessage
} from '../../core/render.js';
import { createThinkingIndicator } from '../../core/thinking.js';
import { createHistoryStore } from '../../core/storage.js';
import { createLanguageState } from '../../core/i18n.js';
import { ensurePuterReady, getPuterClient, extractResponseText, getPuterModels } from '../../core/puter.js';
import { initializeModelCache, loadModelsWithCache } from '../../core/cache.js';

const UI = {
  btn: document.getElementById('publicAssistantBtn'),
  window: document.getElementById('publicAssistantChat'),
  messages: document.getElementById('publicAssistantMessages'),
  input: document.getElementById('publicAssistantInput'),
  sendBtn: document.getElementById('sendToPublicAssistant'),
  loading: document.getElementById('publicAssistantLoading'),
  closeBtn: document.getElementById('closePublicAssistant'),
  statusIndicator: document.getElementById('publicAssistantStatusIndicator'),
  status: document.getElementById('publicAssistantStatusText'),
  modelName: document.getElementById('publicAssistantModelName'),
  modelStatusIndicator: document.getElementById('publicAssistantModelStatusIndicator'),
  openRouterKeyStatus: document.getElementById('publicAssistantOpenRouterKeyStatus'),
  thinkingIndicator: document.getElementById('publicAssistantThinkingIndicator'),
  fallbackBadge: document.getElementById('publicAssistantFallbackBadge'),
  langBnBtn: document.getElementById('publicAssistantLangBn'),
  langEnBtn: document.getElementById('publicAssistantLangEn'),
  typingText: document.getElementById('publicAssistantTypingText'),
  footer: document.getElementById('publicAssistantFooter'),
  preChat: document.getElementById('publicAssistantPreChat'),
  btnNewChat: null,
};

// Enable public-only mode to skip sign-in requirements
// Puter.js is the frontend fallback provider when other AI backends are unavailable.
window.PUTER_PROXY_PUBLIC_ONLY = true;
window.PUTER_DISABLED = false; // Allow Puter.js fallback when needed

const CHAT_STORAGE_KEY = 'brox.publicAssistant.chat.v2';
const LAST_ACTIVITY_KEY = 'brox.publicAssistant.lastActivity.v2';
const LANGUAGE_KEY = 'brox.publicAssistant.language.v2';
const USER_INFO_KEY = 'brox.publicAssistant.userInfo.v2';
const MAX_STORED_MESSAGES = 40;
const INACTIVITY_LIMIT_MS = 60 * 60 * 1000; // 1 hour
const ASSISTANT_SITE_URL = window.location?.origin || `${window.location.protocol}//${window.location.host}`;

const DEFAULT_PREFS = {
  provider: 'puter',
  model: 'meta-llama/llama-3-8b-instruct:free',
  providers: [],
};

const DEBUG_MODE =
  new URLSearchParams(window.location.search).get('ai_debug') === '1' ||
  window.localStorage.getItem('ai_debug_enabled') === 'true';

const MODEL_CACHE_TTL = 24 * 60 * 60 * 1000; // 24 hours
const assistantPrefs = { ...DEFAULT_PREFS, };
const providerApiKeys = {};
const providerApiKeySources = {};
const SUPPORTED_CLIENT_PROVIDERS = new Set(['openrouter', 'fireworks']);
const ASSISTANT_SITE_URL = window.location?.origin || `${window.location.protocol}//${window.location.host}`;

function getProviderApiKey(providerName) {
  return String(providerApiKeys[providerName] || '').trim();
}

function getProviderApiKeySource(providerName) {
  return String(providerApiKeySources[providerName] || 'none');
}

function getActiveClientProviders() {
  return (assistantPrefs.providers || [])
    .filter((p) => p.provider_name && p.has_api_key && SUPPORTED_CLIENT_PROVIDERS.has(p.provider_name))
    .map((p) => p.provider_name);
}

function getPromptEnhancementContext() {
  const context = [];

  if (document.title) {
    context.push(`Page title: ${document.title.trim()}`);
  }

  const pageUrl = window.location?.href || '';
  if (pageUrl) {
    context.push(`Page URL: ${pageUrl}`);
  }

  if (currentLang) {
    context.push(`Current UI language: ${currentLang === 'bn' ? 'Bangla' : 'English'}`);
  }

  if (userInfo?.topics?.length) {
    context.push(`Selected topics: ${userInfo.topics.join(', ')}`);
  }

  return context.filter(Boolean).join('\n\n').trim();
}

function normalizeEnhancedPromptText(text) {
  let value = String(text || '').trim();
  if (!value) return '';

  value = value.replace(/^```(?:prompt|text)?\s*/i, '').replace(/```$/i, '').trim();
  value = value.replace(/^(?:enhanced|improved)\s+prompt\s*:\s*/i, '').trim();
  value = value.replace(/^(?:here(?:'s| is) the improved prompt|here is a better prompt)\s*[:\-]?\s*/i, '').trim();
  value = value.replace(/^(?:prompt enhancement|rewritten prompt)\s*[:\-]?\s*/i, '').trim();
  return value;
}

function getPromptEnhancementProvider() {
  const activeProviders = getActiveClientProviders();
  if (assistantPrefs.provider && activeProviders.includes(assistantPrefs.provider)) {
    return assistantPrefs.provider;
  }
  return activeProviders[0] || 'puter';
}

async function requestEnhancedPrompt(rawText) {
  const promptText = String(rawText || '').trim();
  if (!promptText) return '';

  const enhancementContext = getPromptEnhancementContext();
  const provider = getPromptEnhancementProvider();
  const model =
    provider === 'puter'
      ? 'gemini-2.0-flash'
      : assistantPrefs.model || 'meta-llama/llama-3-8b-instruct:free';
  const messages = [
    {
      role: 'system',
      content: [
        'You are an expert prompt enhancer for AI assistants.',
        'Rewrite the user prompt so it is clearer, more specific, and more useful.',
        'Preserve the original intent.',
        'Add relevant context when provided, such as page information or selected topics.',
        'Improve instructions for output format, detail level, tone, or scope when helpful.',
        'If the prompt is already strong, polish it instead of over-expanding it.',
        'Return only the improved prompt text. Do not add explanations, commentary, or code fences.',
      ].join(' '),
    },
    {
      role: 'user',
      content: [
        'Original prompt:',
        promptText,
        enhancementContext ? `\nContext:\n${enhancementContext}` : '',
        '\nReturn a ready-to-send improved prompt.',
      ].filter(Boolean).join('\n'),
    },
  ];

  let response;
  if (provider === 'openrouter') {
    response = await callOpenRouterAI(messages, {
      model: model && model.includes('/') ? model : 'meta-llama/llama-3-8b-instruct:free',
      temperature: 0.2,
      maxTokens: 600,
    });
  } else if (provider === 'fireworks') {
    response = await callFireworksAI(messages, {
      model,
      temperature: 0.2,
      maxTokens: 600,
    });
  } else {
    await ensurePuterReady({ interactive: false, allowAuth: false, t: (key) => t(key), });
    const puter = await getPuterClient();
    response = await puter.ai.chat(messages, {
      model: model || 'gemini-2.0-flash',
      stream: false,
    });
  }

  const enhanced = normalizeEnhancedPromptText(extractResponseText(response) || '');
  if (!enhanced) {
    throw new Error('Prompt enhancer returned an empty result');
  }
  return enhanced;
}

function showPreChatError(message) {
  const container = document.getElementById('publicAssistantPreChatError') || (() => {
    const el = document.createElement('div');
    el.id = 'publicAssistantPreChatError';
    el.className = 'brox-ai-text-error';
    el.style.cssText = 'margin-top:0.75rem;color:#d92e2e;font-size:0.95rem;line-height:1.4;';
    const parent = document.querySelector('#publicAssistantPreChat .brox-ai-welcome');
    parent?.appendChild(el);
    return el;
  })();

  container.textContent = message;
  container.classList.remove('brox-ai-hidden');
  window.setTimeout(() => container.classList.add('brox-ai-hidden'), 6000);
}

function choosePreferredProvider() {
  const active = getActiveClientProviders();
  if (assistantPrefs.provider && active.includes(assistantPrefs.provider)) {
    return assistantPrefs.provider;
  }
  return active.length > 0 ? active[0] : assistantPrefs.provider;
}

function selectModelFromList(provider, models, currentModel) {
  if (!Array.isArray(models) || models.length === 0) {
    return currentModel;
  }

  if (currentModel && models.some((m) => String(m.id) === String(currentModel))) {
    return currentModel;
  }

  const defaultModel = models.find((m) => m.default || false);
  if (defaultModel) {
    return String(defaultModel.id);
  }

  if (provider === 'openrouter') {
    const freeModel = models.find((m) => {
      const id = String(m.id).toLowerCase();
      const name = String(m.name || '').toLowerCase();
      return id.includes(':free') || id.includes('/free') || name.includes('free');
    });
    if (freeModel) {
      return String(freeModel.id);
    }
  }

  return String(models[0].id);
}

async function getBackendModels(provider) {
  try {
    const result = await loadModelsWithCache(provider, {
      cacheTTL: MODEL_CACHE_TTL,
      timeout: 10000,
    });
    return Array.isArray(result?.models) ? result.models : [];
  } catch (err) {
    console.warn(`Failed to load models for ${provider}:`, err?.message || err);
    return [];
  }
}

async function preloadProviderModels() {
  const activeProviders = getActiveClientProviders();
  const preferredProvider = choosePreferredProvider();

  if (preferredProvider && preferredProvider !== 'puter') {
    const models = await getBackendModels(preferredProvider);
    assistantPrefs.model = selectModelFromList(preferredProvider, models, assistantPrefs.model);
    assistantPrefs.provider = preferredProvider;
  }

  updateSelectedProviderModel();

  await Promise.all(
    activeProviders
      .filter((provider) => provider !== preferredProvider)
      .map(async (provider) => {
        await getBackendModels(provider);
      })
  );

  try {
    const ready = await ensurePuterReady({ interactive: false, allowAuth: false, });
    if (ready) {
      await getPuterModels();
    }
  } catch (err) {
    console.info('Puter preload failed:', err);
  }
}

async function loadAssistantPrefs() {
  try {
    const response = await fetch('/api/ai/settings');
    if (response.ok) {
      const data = await response.json();
      assistantPrefs.provider = data.provider;
      assistantPrefs.model = data.model;
      assistantPrefs.providers = Array.isArray(data.providers) ? data.providers : [];

      // Store provider API keys in private client state only.
      (assistantPrefs.providers || []).forEach((p) => {
        if (!p.api_key || !p.provider_name) return;
        providerApiKeys[p.provider_name] = p.api_key;
        providerApiKeySources[p.provider_name] = p.api_key_source || 'db';
      });

      // Auto-select a working provider and model before the assistant opens.
      assistantPrefs.provider = choosePreferredProvider();
      if (assistantPrefs.provider === 'openrouter' && (!assistantPrefs.model || !assistantPrefs.model.includes('/'))) {
        assistantPrefs.model = 'meta-llama/llama-3-8b-instruct:free';
      }

      if (assistantPrefs.provider !== 'puter') {
        const models = await getBackendModels(assistantPrefs.provider);
        assistantPrefs.model = selectModelFromList(assistantPrefs.provider, models, assistantPrefs.model);
      }
    }
  } catch (err) {
    console.info('Failed to load assistant prefs from backend:', err);
  }
  console.info('assistantPrefs loaded:', assistantPrefs);
  updateOpenRouterKeyStatus();
  updateSelectedProviderModel();
}

const I18N = {
  bn: {
    assistant_title: 'ব্রক্স সহকারী',
    assistant_status: 'বার্তা পাঠালে সংযুক্ত হবে',
    status_thinking: 'ভাবছে...',
    thinking_understanding: 'অনুরোধ বুঝছে...',
    thinking_planning: 'উত্তর পরিকল্পনা করছে...',
    thinking_calling: 'প্রোভাইডারকে কল করছে...',
    thinking_fallback: 'ব্যর্থ হলে বিকল্প প্রোভাইডারে যাচ্ছে...',
    thinking_generating: 'চূড়ান্ত উত্তর তৈরি করছে...',
    default_greeting: 'হ্যালো, আমি আপনার BroxLab সহকারী। কীভাবে সাহায্য করতে পারি?',
    close_label: 'বন্ধ করুন',
    chat_input_placeholder: 'আপনার প্রশ্ন লিখুন...',
    typing_text: 'টাইপ করছে...',
    name_label: 'আপনার নাম',
    name_placeholder: 'আপনার নাম লিখুন',
    email_label: 'ইমেইল (ঐচ্ছিক)',
    email_placeholder: 'আপনার ইমেইল লিখুন (ঐচ্ছিক)',
    mobile_label: 'মোবাইল নম্বর (ঐচ্ছিক)',
    mobile_placeholder: 'আপনার মোবাইল নম্বর লিখুন (ঐচ্ছিক)',
    topic_label: 'আপনার টপিক নির্বাচন করুন (একাধিক)',
    next_btn: 'পরবর্তী',
    start_chat_btn: 'চ্যাট শুরু করুন',
    new_chat_title: 'নতুন চ্যাট',
    topic_general: 'সাধারণ তথ্য',
    topic_support: 'সাপোর্ট',
    topic_billing: 'বিলিং',
    topic_feedback: 'মতামত',
    alert_name_required: 'অনুগ্রহ করে আপনার নাম লিখুন।',
    alert_topic_required: 'কমপক্ষে একটি টপিক নির্বাচন করুন।',
    session_expired_notice: 'পূর্বের চ্যাট সেশন শেষ হয়েছে। অনুগ্রহ করে আপনার তথ্য পুনরায় দিন।',
    chat_reset_notice: 'নিষ্ক্রিয়তার কারণে চ্যাট রিসেট হয়েছে।',
    fallback_error: 'দুঃখিত, এখন সংযোগে সমস্যা হচ্ছে।',
    response_time_label: 'রেসপন্স',
  },
  en: {
    assistant_title: 'Brox Assistant',
    assistant_status: 'Will connect on first message',
    status_thinking: 'Thinking...',
    thinking_understanding: 'Understanding request...',
    thinking_planning: 'Planning response...',
    thinking_calling: 'Calling provider...',
    thinking_fallback: 'Falling back to fallback provider...',
    thinking_generating: 'Generating final answer...',
    default_greeting: 'Hello, I am your Brox assistant. How can I help you today?',
    close_label: 'Close',
    chat_input_placeholder: 'Ask your question...',
    typing_text: 'Typing...',
    name_label: 'Your Name',
    name_placeholder: 'Enter your name',
    email_label: 'Email (Optional)',
    email_placeholder: 'Enter your email (optional)',
    mobile_label: 'Mobile Number (Optional)',
    mobile_placeholder: 'Enter your mobile number (optional)',
    topic_label: 'Select your topics (multiple)',
    next_btn: 'Next',
    start_chat_btn: 'Start Chat',
    new_chat_title: 'New Chat',
    topic_general: 'General',
    topic_support: 'Support',
    topic_billing: 'Billing',
    topic_feedback: 'Feedback',
    alert_name_required: 'Please enter your name.',
    alert_topic_required: 'Please select at least one topic.',
    session_expired_notice: 'Previous chat session expired. Please provide your information again.',
    chat_reset_notice: 'Chat reset due to inactivity.',
    fallback_error: 'Sorry, having trouble connecting right now.',
    response_time_label: 'Response',
  },
};

const STATIC_REPLIES = {
  bn: {
    name: 'আমি brox বলছি, BroxLab সহকারী হিসেবে আপনাকে তথ্য ও সাপোর্টে সাহায্য করি।',
    about: `আমি brox বলছি। BroxLab হলো ${ASSISTANT_SITE_URL} শিরোনামের Bengali-first tech platform, যেখানে কনটেন্ট, সেবা ও ডিজিটাল তথ্য সাজানোভাবে প্রকাশ করা হয়।`,
  },
  en: {
    name: 'I am Brox, speaking as the BroxLab assistant.',
    about: `I am Brox. BroxLab is the Bengali-first tech platform at ${ASSISTANT_SITE_URL}.`,
  },
};

let userInfo = null;
let supportLogged = false;
let chatHistory = [];
let historyExpired = false;

const getStaticReply = buildStaticReplyMatcher(STATIC_REPLIES);
const { getLanguage, setLanguage, } = createLanguageState({
  storageKey: LANGUAGE_KEY,
  defaultLang: 'bn',
  storage: window.localStorage,
});
let currentLang = getLanguage();
let lastProviderUsed = null;
let lastProviderChain = null;
let publicThinking = null;
const historyStore = createHistoryStore({
  storage: window.localStorage,
  chatKey: CHAT_STORAGE_KEY,
  activityKey: LAST_ACTIVITY_KEY,
  maxMessages: MAX_STORED_MESSAGES,
  inactivityMs: INACTIVITY_LIMIT_MS,
});

// Fireworks AI API call function
async function callFireworksAI(messages, options = {}) {
  // Proxy the request to the backend so API keys are not exposed to clients
  const payload = {
    messages,
    options: { ...(options || {}), provider: 'fireworks', model: options?.model },
    stream: false,
  };

  const res = await fetch('/ai/chat', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: JSON.stringify(payload),
  });

  if (!res.ok) {
    const body = await res.text();
    throw new Error(`Backend proxy error: ${res.status} ${res.statusText} ${body}`);
  }

  return await res.json();
}

// OpenRouter AI API call function
async function callOpenRouterAI(messages, options = {}) {
  // Proxy the request to the backend so API keys are not exposed to clients
  const payload = {
    messages,
    options: {
      ...(options || {}),
      provider: 'openrouter',
      model: options?.model || 'meta-llama/llama-3-8b-instruct:free',
    },
    stream: false,
  };

  const res = await fetch('/ai/chat', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: JSON.stringify(payload),
  });

  if (!res.ok) {
    const body = await res.text();
    throw new Error(`Backend proxy error: ${res.status} ${res.statusText} ${body}`);
  }

  try {
    return await res.json();
  } catch (err) {
    const raw = await res.text();
    throw new Error(`Backend proxy parse error: ${err.message} ${raw}`);
  }
}

function t(key) {
  return I18N[currentLang]?.[key] || I18N.en[key] || key;
}

function setStatus(text) {
  if (UI.status) UI.status.textContent = text;
  if (!UI.statusIndicator) return;

  // Green when assistant is ready; red otherwise
  const readyTexts = [t('assistant_status'), t('default_greeting'),];
  const isReady = readyTexts.includes(text);
  UI.statusIndicator.classList.toggle('ready', isReady);
}

function setFallbackBadge(visible) {
  if (!UI.fallbackBadge) return;
  UI.fallbackBadge.classList.toggle('d-none', !visible);
}

function updateOpenRouterKeyStatus() {
  if (!UI.openRouterKeyStatus) return;

  const key = getProviderApiKey('openrouter');
  const source = getProviderApiKeySource('openrouter');

  if (!key) {
    UI.openRouterKeyStatus.textContent = 'OpenRouter API key not configured';
    UI.openRouterKeyStatus.className = 'assistant-status text-warning';
    return;
  }

  const label =
    source === 'db'
      ? 'OpenRouter key configured (DB)'
      : source === 'env'
        ? 'OpenRouter key configured (env)'
        : 'OpenRouter key configured';

  UI.openRouterKeyStatus.textContent = label;
  UI.openRouterKeyStatus.className = 'assistant-status text-success';
}

function setTyping(active) {
  if (publicThinking) {
    if (active) {
      publicThinking.show().setStep(0).setStatus(t('thinking_understanding') || 'Understanding request...');
    } else {
      publicThinking.hide();
    }
  }
}

function updateSelectedProviderModel() {
  if (!UI.modelName || !UI.modelStatusIndicator) {
    return;
  }

  const provider = assistantPrefs.provider || 'puter';
  const model = assistantPrefs.model || '';
  const providerLabel = provider === 'puter' ? 'Puter (fallback)' : provider;
  UI.modelName.textContent = model ? `${providerLabel} · ${model}` : providerLabel;
  UI.modelName.title = model ? `Selected provider: ${providerLabel}, selected model: ${model}` : `Selected provider: ${providerLabel}`;

  UI.modelStatusIndicator.className = 'brox-ai-status-indicator';
  if (provider === 'puter') {
    UI.modelStatusIndicator.classList.add('brox-ai-offline');
    UI.modelStatusIndicator.title = 'Fallback provider selected';
  } else {
    UI.modelStatusIndicator.classList.add('brox-ai-online');
    UI.modelStatusIndicator.title = 'Selected backend provider';
  }
}

function normalizeSuggestions(rawSuggestions) {
  if (!rawSuggestions) return [];
  if (Array.isArray(rawSuggestions)) {
    return rawSuggestions
      .map((item) => {
        if (typeof item === 'string') return { label: item, action: item, };
        if (item && typeof item === 'object')
          return {
            label: item.label || item.action || String(item),
            action: item.action || item.label || String(item),
          };
        return null;
      })
      .filter(Boolean);
  }
  if (typeof rawSuggestions === 'string') {
    return [{ label: rawSuggestions, action: rawSuggestions, },];
  }
  return [];
}

function renderSuggestChips(message, suggestions = []) {
  const chips = normalizeSuggestions(suggestions);
  if (!chips.length) return;
  const existing = message.querySelector('.assistant-suggestions');
  if (existing) existing.remove();

  const chipRow = document.createElement('div');
  chipRow.className = 'assistant-suggestions';
  chips.forEach((suggestion) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'assistant-suggestion-btn';
    btn.textContent = suggestion.label;
    btn.addEventListener('click', () => {
      UI.input.value = suggestion.action;
      UI.input.focus();
      handleUserMessage();
    });
    chipRow.appendChild(btn);
  });
  chipRow.tabIndex = 0;
  chipRow.addEventListener('keydown', (e) => {
    const buttons = Array.from(chipRow.querySelectorAll('button'));
    if (!buttons.length) return;
    const idx = buttons.indexOf(document.activeElement);
    let nextIdx = -1;

    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
      nextIdx = idx < 0 ? 0 : (idx + 1) % buttons.length;
    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
      nextIdx = idx < 0 ? buttons.length - 1 : (idx - 1 + buttons.length) % buttons.length;
    } else if (e.key === 'Home') {
      nextIdx = 0;
    } else if (e.key === 'End') {
      nextIdx = buttons.length - 1;
    }

    if (nextIdx >= 0) {
      e.preventDefault();
      buttons[nextIdx].focus();
    }
  });

  message.appendChild(chipRow);
}

async function applyResponseConfig(message, rawText, opts = {}) {
  const responseConfig = opts.responseConfig || null;
  const { config, content, } = responseConfig
    ? { config: responseConfig, content: rawText, }
    : parseResponseConfig(rawText || '');
  if (!config) return rawText;

  const body = message.querySelector('.message-content');
  if (!body) return rawText;

  const finalText = content || rawText;
  const animation = (config.animation || config.animation_type || '').toLowerCase();
  const speed = parseInt(config.animation_speed || config.animationSpeed, 10) || 30;

  if (animation === 'typing_effect') {
    try {
      await typeMessage(body, finalText, { speed, });
    } catch {
      body.textContent = finalText;
    }
  } else {
    body.textContent = finalText;
  }

  if (config.suggestions) {
    renderSuggestChips(message, config.suggestions);
  }

  return finalText;
}

function updateLangButtons() {
  const setState = (btn, active) => {
    if (!btn) return;
    btn.classList.toggle('active', active);
    btn.classList.toggle('btn-light', active);
    btn.classList.toggle('btn-outline-light', !active);
  };
  setState(UI.langBnBtn, currentLang === 'bn');
  setState(UI.langEnBtn, currentLang === 'en');
}

function applyLanguage() {
  const setText = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  };
  const setPlaceholder = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.setAttribute('placeholder', val);
  };
  setText('publicAssistantTitle', t('assistant_title'));
  setText('publicAssistantStatusText', t('assistant_status'));
  setText('publicAssistantTypingText', t('typing_text'));
  setText('introNameLabel', t('name_label'));
  setText('introEmailLabel', t('email_label'));
  setText('introMobileLabel', t('mobile_label'));
  setText('introTopicLabel', t('topic_label'));
  setText('introNext1', t('next_btn'));
  setText('introNext2', t('next_btn'));
  setText('introStartChat', t('start_chat_btn'));
  setPlaceholder('introName', t('name_placeholder'));
  setPlaceholder('introEmail', t('email_placeholder'));
  setPlaceholder('introMobile', t('mobile_placeholder'));
  setPlaceholder('publicAssistantInput', t('chat_input_placeholder'));
  updateLangButtons();
}

function renderWelcome() {
  appendMessage(UI.messages, 'assistant', t('default_greeting'));
}

function renderHistory() {
  UI.messages?.querySelectorAll('.message').forEach((n) => n.remove());
  if (!chatHistory.length) {
    renderWelcome();
    return;
  }
  chatHistory.forEach((row) =>
    appendMessage(UI.messages, row.role, row.text, { ts: row.ts, responseMs: row.responseMs, })
  );
}

function loadUserInfo() {
  try {
    const raw = window.localStorage.getItem(USER_INFO_KEY);
    if (!raw) return;
    const parsed = JSON.parse(raw);
    userInfo = {
      name: String(parsed.name || '').trim(),
      email: String(parsed.email || '').trim(),
      mobile: String(parsed.mobile || '').trim(),
      topics: Array.isArray(parsed.topics) ? parsed.topics : [],
      supportSent: parsed.supportSent === true,
    };
    if (!userInfo.topics.length) userInfo.topics = ['general',];
    supportLogged = userInfo.supportSent;
  } catch {
    userInfo = null;
  }
}

function saveUserInfo() {
  if (!userInfo) return;
  try {
    window.localStorage.setItem(USER_INFO_KEY, JSON.stringify(userInfo));
  } catch {
    // ignore
  }
}

function setPreChatStep(step) {
  ['step-name', 'step-contact', 'step-topic',].forEach((name) => {
    const node = UI.preChat?.querySelector(`.${name}`);
    if (!node) return;
    node.classList.toggle('d-none', name !== step);
  });
}

function showPreChat() {
  UI.preChat?.classList.remove('d-none');
  UI.messages?.classList.add('d-none');
  UI.footer?.classList.add('d-none');
  setPreChatStep('step-name');
}

function clearPreChat() {
  UI.preChat?.classList.add('d-none');
  UI.messages?.classList.remove('d-none');
  UI.footer?.classList.remove('d-none');
}

function getSelectedTopics() {
  return Array.from(document.querySelectorAll('.intro-topic-option:checked'))
    .map((el) => String(el.value || '').trim())
    .filter(Boolean);
}

function buildSystemPrompt() {
  const visitor = userInfo?.name ? `Visitor name: ${userInfo.name}.` : '';
  const topics = userInfo?.topics?.length ? `Visitor topics: ${userInfo.topics.join(', ')}.` : '';
  return [
    'You are Brox, the bilingual public assistant for BroxLab.',
    `BroxLab website: ${ASSISTANT_SITE_URL}.`,
    `Current UI language: ${currentLang === 'bn' ? 'Bangla' : 'English'}.`,
    visitor,
    topics,
    'Keep replies concise and friendly.',
    'If asked your name, answer that you are Brox and mention BroxLab with the URL.',
    'If asked about yourself or the site, describe briefly and include the site URL.',
    'Do not promise backend actions; provide helpful guidance and links.',
  ]
    .filter(Boolean)
    .join('\n');
}

function resetChat() {
  chatHistory = [];
  historyStore.save(chatHistory);
  renderHistory();
  appendAssistant(UI.messages, t('chat_reset_notice'), { animate: true, });
}

function initQuickAction() {
  if (!UI.footer) return;
  const inputGroup = UI.footer.querySelector('.input-group');
  if (!inputGroup || inputGroup.querySelector('.assistant-action-strip')) return;
  const strip = document.createElement('div');
  strip.className = 'assistant-action-strip';
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'btn btn-light assistant-action-btn';
  btn.id = 'publicAssistantNewChat';
  btn.title = t('new_chat_title');
  btn.textContent = '↺';
  btn.addEventListener('click', resetChat);
  strip.appendChild(btn);
  UI.btnNewChat = btn;
  inputGroup.insertBefore(strip, inputGroup.firstChild);
}

function bindEvents() {
  UI.langBnBtn?.addEventListener('click', () => {
    currentLang = 'bn';
    setLanguage('bn');
    applyLanguage();
    renderHistory();
  });
  UI.langEnBtn?.addEventListener('click', () => {
    currentLang = 'en';
    setLanguage('en');
    applyLanguage();
    renderHistory();
  });
  UI.btn?.addEventListener('click', () => {
    const opening = UI.window?.classList.contains('d-none');
    UI.window?.classList.toggle('hidden');
    UI.window?.classList.toggle('d-none');
    if (opening) {
      userInfo?.name ? clearPreChat() : showPreChat();
    }
  });
  UI.closeBtn?.addEventListener('click', () => {
    UI.window?.classList.add('hidden');
    UI.window?.classList.add('d-none');
  });
  UI.sendBtn?.addEventListener('click', handleUserMessage);
  UI.input?.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') handleUserMessage();
  });
  document.getElementById('introNext1')?.addEventListener('click', () => {
    const name = String(document.getElementById('introName')?.value || '').trim();
    if (!name) {
      showPreChatError(t('alert_name_required'));
      document.getElementById('introName')?.focus();
      return;
    }
    setPreChatStep('step-contact');
  });
  document
    .getElementById('introNext2')
    ?.addEventListener('click', () => setPreChatStep('step-topic'));
  document.getElementById('introStartChat')?.addEventListener('click', () => {
    const name = String(document.getElementById('introName')?.value || '').trim();
    const email = String(document.getElementById('introEmail')?.value || '').trim();
    const mobile = String(document.getElementById('introMobile')?.value || '').trim();
    const topics = getSelectedTopics();
    if (!name) {
      showPreChatError(t('alert_name_required'));
      document.getElementById('introName')?.focus();
      setPreChatStep('step-name');
      return;
    }
    if (!topics.length) {
      showPreChatError(t('alert_topic_required'));
      document.getElementById('introTopicOptions')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    userInfo = { name, email, mobile, topics, supportSent: false, };
    supportLogged = false;
    saveUserInfo();
    clearPreChat();
  });
}

async function handleUserMessage() {
  const rawText = String(UI.input?.value || '').trim();
  if (!rawText || !userInfo?.name) {
    showPreChat();
    return;
  }

  const staticReply = getStaticReply(rawText, currentLang);
  if (staticReply) {
    const { history, expired, } = historyStore.load();
    chatHistory = expired ? [] : history;
    if (expired) {
      // Reset user info and show pre-chat when session expires due to inactivity
      userInfo = null;
      window.localStorage.removeItem(USER_INFO_KEY);
      supportLogged = false;
      showPreChat();
      appendAssistant(UI.messages, t('session_expired_notice'), { animate: true, });
      return; // Don't proceed with the message
    }

    UI.input.value = '';
    const ts = new Date().toISOString();
    chatHistory.push({ role: 'user', text: rawText, ts, });
    historyStore.save(chatHistory);
    appendMessage(UI.messages, 'user', rawText, { ts, });
    await appendAssistant(UI.messages, staticReply, { animate: true, });
    return;
  }

  if (rawText.startsWith('/')) {
    return;
  }

  const { history, expired, } = historyStore.load();
  chatHistory = expired ? [] : history;
  if (expired) {
    // Reset user info and show pre-chat when session expires due to inactivity
    userInfo = null;
    window.localStorage.removeItem(USER_INFO_KEY);
    supportLogged = false;
    showPreChat();
    appendAssistant(UI.messages, t('session_expired_notice'), { animate: true, });
    return; // Don't proceed with the message
  }

  let promptText = rawText;
  try {
    if (UI.loading) {
      UI.loading.classList.remove('d-none');
    }
    if (publicThinking) {
      publicThinking.show().setStep(0).setStatus('Enhancing prompt...');
    }
    promptText = await requestEnhancedPrompt(rawText);
  } catch (err) {
    console.warn('Prompt enhancement skipped:', err);
    promptText = rawText;
  }

  UI.input.value = '';
  const ts = new Date().toISOString();
  chatHistory.push({ role: 'user', text: promptText, ts, });
  historyStore.save(chatHistory);
  appendMessage(UI.messages, 'user', promptText, { ts, });

  if (userInfo.topics.includes('support') && !supportLogged) {
    const queued = sendSupportMessage(rawText);
    if (queued) {
      supportLogged = true;
      userInfo.supportSent = true;
      saveUserInfo();
    }
  }

  setTyping(true);
  if (publicThinking) {
    publicThinking.show().setStep(0).setStatus(t('thinking_understanding') || 'Understanding request...');
  }
  setStatus(t('status_thinking'));
  setFallbackBadge(false);
  if (publicThinking) {
    publicThinking.setStep(1).setStatus(t('thinking_planning') || 'Planning response...');
  }
  const started = performance.now();

  let usedModel;

  const providerChain = (() => {
    const list = Array.isArray(assistantPrefs.providers) ? [...assistantPrefs.providers,] : [];
    const primary = assistantPrefs.provider;
    const ordered = [];

    if (primary) {
      const idx = list.findIndex((p) => p.provider_name === primary);
      if (idx !== -1) {
        ordered.push(list.splice(idx, 1)[0]);
      }
    }

    // Append remaining providers in config order.
    ordered.push(...list);
    return ordered.filter((p) => p.has_api_key);
  })();

  const providerOrder =
    providerChain.length > 0
      ? `${providerChain.map((p) => p.provider_name).join(' → ')} → Puter`
      : 'Puter';

  if (providerOrder !== lastProviderChain) {
    if (DEBUG_MODE) {
      await appendAssistant(UI.messages, `Provider fallback order: ${providerOrder}`, {
        animate: true,
      });
    } else {
      console.info('Provider fallback order:', providerOrder);
    }
    lastProviderChain = providerOrder;
  }

  const apiMessages = [
    { role: 'system', content: buildSystemPrompt(), },
    ...chatHistory.map((r) => ({ role: r.role, content: r.text, })),
    { role: 'user', content: promptText, },
  ];

  let providerError = null;

  for (const prov of providerChain) {
    let triedFallbackModel = false;

    while (true) {
      try {
        let model = assistantPrefs.model;
        let response;

        if (publicThinking) {
          publicThinking.setStep(2).setStatus(`Calling ${prov.provider_name}...`);
        }
        if (prov.provider_name === 'openrouter') {
          model = model && model.includes('/') ? model : 'meta-llama/llama-3-8b-instruct:free';
          response = await callOpenRouterAI(apiMessages, { stream: false, model, });
        } else if (prov.provider_name === 'fireworks') {
          model = model || '';
          response = await callFireworksAI(apiMessages, { stream: false, model, });
        } else {
          // Provider not supported by client; skip.
          break;
        }

        const reply = extractResponseText(response) || t('fallback_error');
        usedModel = model.replace('accounts/fireworks/models/', '').replace('openai/', '');

        const assistantMsg = await appendAssistant(UI.messages, reply, {
          animate: true,
          model: usedModel,
          provider: prov.provider_name,
          tools: true,
          onRun: () => {
            UI.input.value = reply;
            handleUserMessage();
          },
        });
        const finalContent = await applyResponseConfig(assistantMsg, reply, { responseConfig: {}, });
        const responseMs = Math.max(0, Math.round(performance.now() - started));
        chatHistory.push({
          role: 'assistant',
          text: finalContent,
          ts: new Date().toISOString(),
          responseMs,
        });
        historyStore.save(chatHistory);
        setStatus(t('assistant_status'));
        setTyping(false);
        historyStore.updateActivity();

        // Show provider switch message when changing provider during session
        if (lastProviderUsed && lastProviderUsed !== prov.provider_name) {
          await appendAssistant(
            UI.messages,
            `Switched provider: ${lastProviderUsed} → ${prov.provider_name}`,
            { animate: true, }
          );
        }
        lastProviderUsed = prov.provider_name;
        return;
      } catch (providerErr) {
        providerError = providerErr;
        console.info('Provider failed, trying next:', prov.provider_name, providerErr.message);

        // If auth failure, show a helpful message (only for openrouter)
        if (prov.provider_name === 'openrouter' && /401|Unauthorized/i.test(providerErr.message)) {
          await appendAssistant(
            UI.messages,
            'OpenRouter authentication failed (401). Please verify your OpenRouter API key in AI Settings and refresh the page.',
            { animate: true, }
          );
        }

        // If we got an invalid-model error, try the free router once and retry.
        if (
          prov.provider_name === 'openrouter' &&
          !triedFallbackModel &&
          /not a valid model id/i.test(providerErr.message)
        ) {
          triedFallbackModel = true;
          assistantPrefs.model = 'meta-llama/llama-3-8b-instruct:free';
          console.info('Retrying OpenRouter using meta-llama/llama-3-8b-instruct:free due to invalid model error');
          continue; // retry this provider with fallback model
        }

        // Otherwise, move on to the next provider.
        break;
      }
    }
  } // end for providerChain

  // If we get here, all providers failed; fall back to Puter.js.
  if (providerChain.length > 0) {
    const lastUsed = lastProviderUsed ? ` (last used: ${lastProviderUsed})` : '';
    if (publicThinking) {
      publicThinking.setStep(2).setStatus(t('thinking_fallback') || 'Falling back to fallback provider...');
    }
    await appendAssistant(
      UI.messages,
      DEBUG_MODE
        ? `All configured providers failed, falling back to Puter.js${lastUsed}.`
        : 'One of our AI connectors is temporarily unavailable. Switching to the local fallback to keep the conversation going.',
      { animate: true }
    );
  }

  if (providerChain.length > 0) {
    // Show fallback badge when we attempted providers but fell back to Puter.
    setFallbackBadge(true);
    console.info(
      'All configured providers failed, falling back to Puter.js',
      providerError?.message
    );
  }

  // Use Puter.js directly (no sign-in required)
  try {
    await ensurePuterReady({ interactive: false, allowAuth: false, t: (key) => t(key), });
    const puter = await getPuterClient();
    const model = assistantPrefs.model || 'gemini-2.0-flash';
    usedModel = model;

    const apiMessages = [
      { role: 'system', content: buildSystemPrompt(), },
      ...chatHistory.map((r) => ({ role: r.role, content: r.text, })),
      { role: 'user', content: promptText, },
    ];

    const response = await puter.ai.chat(apiMessages, { model: model, stream: false, });
    const reply = extractResponseText(response) || t('fallback_error');

    if (publicThinking) {
      publicThinking.setStep(3).setStatus(t('thinking_generating') || 'Generating final answer...');
    }
    const assistantMsg = await appendAssistant(UI.messages, reply, {
      animate: true,
      model: usedModel,
      provider: 'Puter',
      tools: true,
      onRun: () => {
        UI.input.value = reply;
        handleUserMessage();
      },
    });
    const finalContent = await applyResponseConfig(assistantMsg, reply, { responseConfig: {}, });
    const responseMs = Math.max(0, Math.round(performance.now() - started));
    chatHistory.push({
      role: 'assistant',
      text: finalContent,
      ts: new Date().toISOString(),
      responseMs,
    });
    historyStore.save(chatHistory);
    setStatus(t('assistant_status'));
  } catch (puterErr) {
    const msg = String(puterErr?.message || t('fallback_error'));
    await appendAssistant(UI.messages, msg, { animate: true, });
    setStatus(msg);
  }
  if (publicThinking) {
    publicThinking.hide();
  }
  setTyping(false);
  historyStore.updateActivity();
}

function sendSupportMessage(messageText) {
  const payload = new URLSearchParams();
  const name = String(userInfo?.name || '').trim();
  const email = String(userInfo?.email || '').trim();
  const mobile = String(userInfo?.mobile || '').trim();
  const contact = mobile || email;
  if (!name && !contact) return false;
  if (name) payload.append('name', name);
  if (email) payload.append('email', email);
  if (mobile) payload.append('mobile', mobile);
  if (contact) payload.append('contact', contact);
  payload.append('message', messageText);
  fetch('/api/public-chat/support', {
    method: 'POST',
    body: payload,
    headers: { Accept: 'application/json', },
  })
    .then((res) => res.json())
    .then((data) => {
      if (!data?.success && userInfo) {
        userInfo.supportSent = false;
        saveUserInfo();
      }
    })
    .catch(() => {
      if (userInfo) {
        userInfo.supportSent = false;
        saveUserInfo();
      }
    });
  return true;
}

async function init() {
  await loadAssistantPrefs();
  loadUserInfo();
  const { history, expired, } = historyStore.load();
  chatHistory = history;
  historyExpired = expired;
  applyLanguage();
  publicThinking = createThinkingIndicator(UI.thinkingIndicator, {
    aiName: 'Brox Assistant',
    initialStatus: t('status_thinking'),
  });
  renderHistory();
  if (historyExpired) appendAssistant(UI.messages, t('session_expired_notice'));
  bindEvents();
  initQuickAction();
  if (userInfo?.name) clearPreChat();
  else showPreChat();
  setStatus(t('assistant_status'));

  // Initialize model cache and prefetch supported provider models.
  initializeModelCache(['openrouter', 'fireworks'], {
    ttl: MODEL_CACHE_TTL,
    storageKey: 'brox.public.models.cache',
  });
  preloadProviderModels();
}

init();
// Global function for site language sync
window.syncChatLanguage = function (newLang) {
  if (newLang === 'bn' || newLang === 'en') {
    currentLang = newLang;
    setLanguage(newLang);
    applyLanguage();
    renderHistory();
    updateLangButtons();
  }
};
