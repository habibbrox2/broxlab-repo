import { appendAssistant, appendMessage, animateBody, attachAssistantTools, buildStaticReplyMatcher, parseResponseConfig, typeMessage } from '../../core/render.js';
import { createHistoryStore } from '../../core/storage.js';
import { createLanguageState } from '../../core/i18n.js';
import { ensurePuterReady, getPuterClient, extractResponseText } from '../../core/puter.js';

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
  openRouterKeyStatus: document.getElementById('publicAssistantOpenRouterKeyStatus'),
  fallbackBadge: document.getElementById('publicAssistantFallbackBadge'),
  langBnBtn: document.getElementById('publicAssistantLangBn'),
  langEnBtn: document.getElementById('publicAssistantLangEn'),
  typingText: document.getElementById('publicAssistantTypingText'),
  footer: document.getElementById('publicAssistantFooter'),
  preChat: document.getElementById('publicAssistantPreChat'),
  btnNewChat: null
};

// Enable public-only mode to skip sign-in requirements
// Puter.js is only used as fallback when backend AI is unavailable.
window.PUTER_PROXY_PUBLIC_ONLY = true;
window.PUTER_DISABLED = false; // Allow Puter.js fallback when needed

const CHAT_STORAGE_KEY = 'brox.publicAssistant.chat.v2';
const LAST_ACTIVITY_KEY = 'brox.publicAssistant.lastActivity.v2';
const LANGUAGE_KEY = 'brox.publicAssistant.language.v2';
const USER_INFO_KEY = 'brox.publicAssistant.userInfo.v2';
const MAX_STORED_MESSAGES = 40;
const INACTIVITY_LIMIT_MS = 30 * 60 * 1000;
const ASSISTANT_SITE_URL = 'https://broxlab.online';
const TOPIC_KEYS = ['general', 'support', 'billing', 'feedback'];

const DEFAULT_PREFS = {
  provider: 'puter-js',
  model: 'gemini-2.0-flash'
};

let assistantPrefs = { ...DEFAULT_PREFS };

async function loadAssistantPrefs() {
  try {
    const response = await fetch('/api/ai-settings/frontend');
    if (response.ok) {
      const data = await response.json();
      assistantPrefs.provider = data.provider;
      assistantPrefs.model = data.model;
      assistantPrefs.providers = Array.isArray(data.providers) ? data.providers : [];

      // Ensure we have a default model for OpenRouter (free router) when not explicitly set.
      if (assistantPrefs.provider === 'openrouter' && (!assistantPrefs.model || !assistantPrefs.model.includes('/'))) {
        assistantPrefs.model = 'openrouter/free';
      }

      // Expose provider API keys globally for use by the runtime.
      // These are read-only values provided by the backend.
      (assistantPrefs.providers || []).forEach((p) => {
        if (!p.api_key) return;
        const keyName = p.provider_name.toUpperCase();
        window[`${keyName}_API_KEY`] = p.api_key;
      });
    }
  } catch (err) {
    console.log('Failed to load assistant prefs from backend:', err);
  }
  console.log('assistantPrefs loaded:', assistantPrefs);
  updateOpenRouterKeyStatus();
}

const I18N = {
  bn: {
    assistant_title: 'ব্রক্স সহকারী',
    assistant_status: 'বার্তা পাঠালে সংযুক্ত হবে',
    status_thinking: 'ভাবছে...',
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
    session_expired_notice: 'পূর্বের চ্যাট সেশন শেষ হয়েছে। নতুন সেশন শুরু।',
    chat_reset_notice: 'নিষ্ক্রিয়তার কারণে চ্যাট রিসেট হয়েছে।',
    fallback_error: 'দুঃখিত, এখন সংযোগে সমস্যা হচ্ছে।',
    response_time_label: 'রেসপন্স'
  },
  en: {
    assistant_title: 'Brox Assistant',
    assistant_status: 'Will connect on first message',
    status_thinking: 'Thinking...',
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
    session_expired_notice: 'Previous chat expired. Starting fresh.',
    chat_reset_notice: 'Chat reset due to inactivity.',
    fallback_error: 'Sorry, having trouble connecting right now.',
    response_time_label: 'Response'
  }
};

const STATIC_REPLIES = {
  bn: {
    name: 'আমি brox বলছি, BroxLab সহকারী হিসেবে আপনাকে তথ্য ও সাপোর্টে সাহায্য করি।',
    about: `আমি brox বলছি। BroxLab হলো ${ASSISTANT_SITE_URL} শিরোনামের Bengali-first tech platform, যেখানে কনটেন্ট, সেবা ও ডিজিটাল তথ্য সাজানোভাবে প্রকাশ করা হয়।`
  },
  en: {
    name: 'I am Brox, speaking as the BroxLab assistant.',
    about: `I am Brox. BroxLab is the Bengali-first tech platform at ${ASSISTANT_SITE_URL}.`
  }
};

let userInfo = null;
let supportLogged = false;
let chatHistory = [];
let historyExpired = false;

const getStaticReply = buildStaticReplyMatcher(STATIC_REPLIES);
const { getLanguage, setLanguage } = createLanguageState({ storageKey: LANGUAGE_KEY, defaultLang: 'bn', storage: window.localStorage });
let currentLang = getLanguage();
let lastProviderUsed = null;
let lastProviderChain = null;
const historyStore = createHistoryStore({
  storage: window.localStorage,
  chatKey: CHAT_STORAGE_KEY,
  activityKey: LAST_ACTIVITY_KEY,
  maxMessages: MAX_STORED_MESSAGES,
  inactivityMs: INACTIVITY_LIMIT_MS
});

// Fireworks AI API call function
async function callFireworksAI(messages, options = {}) {
  const apiKey = window.FIREWORKS_API_KEY || ''; // Set your API key here or via window.FIREWORKS_API_KEY
  const response = await fetch('https://api.fireworks.ai/inference/v1/chat/completions', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${apiKey}`
    },
    body: JSON.stringify({
      model: options.model || 'accounts/fireworks/models/deepseek-v3p1',
      messages: messages,
      stream: options.stream || false,
      ...options
    })
  });
  if (!response.ok) throw new Error('Fireworks API error');
  return await response.json();
}

// OpenRouter AI API call function
async function callOpenRouterAI(messages, options = {}) {
  const apiKey = String(window.OPENROUTER_API_KEY || '').trim();
  if (!apiKey) {
    throw new Error('Missing OpenRouter API key. Configure it in AI settings and refresh the page.');
  }

  const response = await fetch('https://openrouter.ai/api/v1/chat/completions', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${apiKey}`,
      'HTTP-Referer': window.location.origin, // Optional
      'X-OpenRouter-Title': 'BroxBhai Assistant' // Optional
    },
    body: JSON.stringify({
      model: options.model || 'openrouter/free',
      messages: messages,
      stream: options.stream || false,
      ...options
    })
  });

  if (!response.ok) {
    let errText = '';
    try {
      const text = await response.text();
      try {
        const json = JSON.parse(text);
        errText = json?.error?.message || text;
      } catch {
        errText = text;
      }
    } catch (e) {
      // ignore
    }
    throw new Error(`OpenRouter API error (${response.status}): ${errText || response.statusText}`);
  }

  try {
    return await response.json();
  } catch (parseErr) {
    let raw = '';
    try {
      raw = await response.text();
    } catch {
      // ignore
    }
    throw new Error(`OpenRouter response parse error: ${parseErr.message}${raw ? ` - ${raw}` : ''}`);
  }
}

// Parse suggestions from response text
function parseSuggestionsFromText(text) {
  const match = text.match(/\[SUGGESTION:\s*(.*?)\]/);
  if (match) {
    const suggestions = match[1].split(',').map(s => s.trim());
    const cleanText = text.replace(/\[SUGGESTION:\s*.*?\]/, '').trim();
    return { text: cleanText, suggestions };
  }
  return { text, suggestions: [] };
}

function t(key) {
  return I18N[currentLang]?.[key] || I18N.en[key] || key;
}

function setStatus(text) {
  if (UI.status) UI.status.textContent = text;
  if (!UI.statusIndicator) return;

  // Green when assistant is ready; red otherwise
  const readyTexts = [t('assistant_status'), t('default_greeting')];
  const isReady = readyTexts.includes(text);
  UI.statusIndicator.classList.toggle('ready', isReady);
}

function setFallbackBadge(visible) {
  if (!UI.fallbackBadge) return;
  UI.fallbackBadge.classList.toggle('d-none', !visible);
}

function updateOpenRouterKeyStatus() {
  if (!UI.openRouterKeyStatus) return;

  const key = String(window.OPENROUTER_API_KEY || '').trim();
  const source = String(window.OPENROUTER_API_KEY_SOURCE || 'none');

  if (!key) {
    UI.openRouterKeyStatus.textContent = 'OpenRouter key missing';
    UI.openRouterKeyStatus.className = 'assistant-status text-warning';
    return;
  }

  const label = source === 'db'
    ? 'OpenRouter key configured (DB)' : source === 'env'
      ? 'OpenRouter key configured (env)' : 'OpenRouter key configured';

  UI.openRouterKeyStatus.textContent = label;
  UI.openRouterKeyStatus.className = 'assistant-status text-success';
}

function setTyping(active) {
  UI.loading?.classList.toggle('d-none', !active);
  UI.loading?.classList.toggle('active', active);
}

function normalizeSuggestions(rawSuggestions) {
  if (!rawSuggestions) return [];
  if (Array.isArray(rawSuggestions)) {
    return rawSuggestions.map((item) => {
      if (typeof item === 'string') return { label: item, action: item };
      if (item && typeof item === 'object') return { label: item.label || item.action || String(item), action: item.action || item.label || String(item) };
      return null;
    }).filter(Boolean);
  }
  if (typeof rawSuggestions === 'string') {
    return [{ label: rawSuggestions, action: rawSuggestions }];
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
  const { config, content } = responseConfig ? { config: responseConfig, content: rawText } : parseResponseConfig(rawText || '');
  if (!config) return rawText;

  const body = message.querySelector('.message-content');
  if (!body) return rawText;

  const finalText = content || rawText;
  const animation = (config.animation || config.animation_type || '').toLowerCase();
  const speed = parseInt(config.animation_speed || config.animationSpeed, 10) || 30;

  if (animation === 'typing_effect') {
    try {
      await typeMessage(body, finalText, { speed });
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
  const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  const setPlaceholder = (id, val) => { const el = document.getElementById(id); if (el) el.setAttribute('placeholder', val); };
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
  chatHistory.forEach((row) => appendMessage(UI.messages, row.role, row.text, { ts: row.ts, responseMs: row.responseMs }));
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
      supportSent: parsed.supportSent === true
    };
    if (!userInfo.topics.length) userInfo.topics = ['general'];
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
  ['step-name', 'step-contact', 'step-topic'].forEach((name) => {
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
  return Array.from(document.querySelectorAll('.intro-topic-option:checked')).map((el) => String(el.value || '').trim()).filter(Boolean);
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
    'If asked about yourself or broxlab.online, describe briefly and include the site URL.',
    'Do not promise backend actions; provide helpful guidance and links.'
  ].filter(Boolean).join('\n');
}

function buildMessages(userText) {
  return [
    { role: 'system', content: buildSystemPrompt() },
    ...chatHistory.map((r) => ({ role: r.role, content: r.text })),
    { role: 'user', content: userText }
  ];
}

function resetChat() {
  chatHistory = [];
  historyStore.save(chatHistory);
  renderHistory();
  appendAssistant(UI.messages, t('chat_reset_notice'), { animate: true });
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
  UI.langBnBtn?.addEventListener('click', () => { currentLang = 'bn'; setLanguage('bn'); applyLanguage(); renderHistory(); });
  UI.langEnBtn?.addEventListener('click', () => { currentLang = 'en'; setLanguage('en'); applyLanguage(); renderHistory(); });
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
  UI.input?.addEventListener('keypress', (e) => { if (e.key === 'Enter') handleUserMessage(); });
  document.getElementById('introNext1')?.addEventListener('click', () => {
    const name = String(document.getElementById('introName')?.value || '').trim();
    if (!name) { alert(t('alert_name_required')); return; }
    setPreChatStep('step-contact');
  });
  document.getElementById('introNext2')?.addEventListener('click', () => setPreChatStep('step-topic'));
  document.getElementById('introStartChat')?.addEventListener('click', () => {
    const name = String(document.getElementById('introName')?.value || '').trim();
    const email = String(document.getElementById('introEmail')?.value || '').trim();
    const mobile = String(document.getElementById('introMobile')?.value || '').trim();
    const topics = getSelectedTopics();
    if (!name) { alert(t('alert_name_required')); setPreChatStep('step-name'); return; }
    if (!topics.length) { alert(t('alert_topic_required')); return; }
    userInfo = { name, email, mobile, topics, supportSent: false };
    supportLogged = false;
    saveUserInfo();
    clearPreChat();
  });
}

async function handleUserMessage() {
  const text = String(UI.input?.value || '').trim();
  if (!text || !userInfo?.name) {
    showPreChat();
    return;
  }

  const { history, expired } = historyStore.load();
  chatHistory = expired ? [] : history;
  if (expired) appendAssistant(UI.messages, t('chat_reset_notice'));

  UI.input.value = '';
  const ts = new Date().toISOString();
  chatHistory.push({ role: 'user', text, ts });
  historyStore.save(chatHistory);
  appendMessage(UI.messages, 'user', text, { ts });

  if (userInfo.topics.includes('support') && !supportLogged) {
    const queued = sendSupportMessage(text);
    if (queued) { supportLogged = true; userInfo.supportSent = true; saveUserInfo(); }
  }

  const staticReply = getStaticReply(text, currentLang);
  if (staticReply) {
    await appendAssistant(UI.messages, staticReply, { animate: true });
    return;
  }

  setTyping(true);
  setStatus(t('status_thinking'));
  setFallbackBadge(false);
  const started = performance.now();

  let usedModel;

  const providerChain = (() => {
    const list = Array.isArray(assistantPrefs.providers) ? [...assistantPrefs.providers] : [];
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

  const providerOrder = (providerChain.length > 0
    ? providerChain.map((p) => p.provider_name).join(' → ') + ' → Puter'
    : 'Puter');

  if (providerOrder !== lastProviderChain) {
    await appendAssistant(UI.messages, `Provider fallback order: ${providerOrder}`, { animate: true });
    lastProviderChain = providerOrder;
  }

  const apiMessages = [
    { role: 'system', content: buildSystemPrompt() },
    ...chatHistory.map((r) => ({ role: r.role, content: r.text })),
    { role: 'user', content: text }
  ];

  let providerError = null;

  for (const prov of providerChain) {
    let triedFallbackModel = false;

    while (true) {
      try {
        let model = assistantPrefs.model;
        let response;

        if (prov.provider_name === 'openrouter') {
          model = model && model.includes('/') ? model : 'openrouter/free';
          response = await callOpenRouterAI(apiMessages, { stream: false, model });
        } else if (prov.provider_name === 'fireworks') {
          model = model || '';
          response = await callFireworksAI(apiMessages, { stream: false, model });
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
          }
        });
        const finalContent = await applyResponseConfig(assistantMsg, reply, { responseConfig: {} });
        const responseMs = Math.max(0, Math.round(performance.now() - started));
        chatHistory.push({ role: 'assistant', text: finalContent, ts: new Date().toISOString(), responseMs });
        historyStore.save(chatHistory);
        setStatus(t('assistant_status'));
        setTyping(false);
        historyStore.updateActivity();

        // Show provider switch message when changing provider during session
        if (lastProviderUsed && lastProviderUsed !== prov.provider_name) {
          await appendAssistant(UI.messages, `Switched provider: ${lastProviderUsed} → ${prov.provider_name}`, { animate: true });
        }
        lastProviderUsed = prov.provider_name;
        return;
      } catch (providerErr) {
        providerError = providerErr;
        console.log('Provider failed, trying next:', prov.provider_name, providerErr.message);

        // If auth failure, show a helpful message (only for openrouter)
        if (prov.provider_name === 'openrouter' && /401|Unauthorized/i.test(providerErr.message)) {
          await appendAssistant(UI.messages, 'OpenRouter authentication failed (401). Please verify your OpenRouter API key in AI Settings and refresh the page.', { animate: true });
        }

        // If we got an invalid-model error, try the free router once and retry.
        if (
          prov.provider_name === 'openrouter' &&
          !triedFallbackModel &&
          /not a valid model id/i.test(providerErr.message)
        ) {
          triedFallbackModel = true;
          assistantPrefs.model = 'openrouter/free';
          console.log('Retrying OpenRouter using openrouter/free due to invalid model error');
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
    await appendAssistant(UI.messages, `All configured providers failed, falling back to Puter.js${lastUsed}.`, { animate: true });
  }

  if (providerChain.length > 0) {
    // Show fallback badge when we attempted providers but fell back to Puter.
    setFallbackBadge(true);
    console.log('All configured providers failed, falling back to Puter.js', providerError?.message);
  }

  // Use Puter.js directly (no sign-in required)
  try {
    await ensurePuterReady({ interactive: false, allowAuth: false, t: (key) => t(key) });
    const puter = await getPuterClient();
    const model = assistantPrefs.model || 'gemini-2.0-flash';
    usedModel = model;

    const apiMessages = [
      { role: 'system', content: buildSystemPrompt() },
      ...chatHistory.map((r) => ({ role: r.role, content: r.text })),
      { role: 'user', content: text }
    ];

    const response = await puter.ai.chat(apiMessages, { model: model, stream: false });
    const reply = extractResponseText(response) || t('fallback_error');

    const assistantMsg = await appendAssistant(UI.messages, reply, {
      animate: true,
      model: usedModel,
      provider: 'Puter',
      tools: true,
      onRun: () => {
        UI.input.value = reply;
        handleUserMessage();
      }
    });
    const finalContent = await applyResponseConfig(assistantMsg, reply, { responseConfig: {} });
    const responseMs = Math.max(0, Math.round(performance.now() - started));
    chatHistory.push({ role: 'assistant', text: finalContent, ts: new Date().toISOString(), responseMs });
    historyStore.save(chatHistory);
    setStatus(t('assistant_status'));
  } catch (puterErr) {
    const msg = String(puterErr?.message || t('fallback_error'));
    await appendAssistant(UI.messages, msg, { animate: true });
    setStatus(msg);
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
  fetch('/api/public-chat/support', { method: 'POST', body: payload, headers: { Accept: 'application/json' } })
    .then((res) => res.json())
    .then((data) => { if (!data?.success && userInfo) { userInfo.supportSent = false; saveUserInfo(); } })
    .catch(() => { if (userInfo) { userInfo.supportSent = false; saveUserInfo(); } });
  return true;
}

async function init() {
  await loadAssistantPrefs();
  loadUserInfo();
  const { history, expired } = historyStore.load();
  chatHistory = history;
  historyExpired = expired;
  applyLanguage();
  renderHistory();
  if (historyExpired) appendAssistant(UI.messages, t('session_expired_notice'));
  bindEvents();
  initQuickAction();
  if (userInfo?.name) clearPreChat(); else showPreChat();
  setStatus(t('assistant_status'));
}

init();
