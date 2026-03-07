import { appendAssistant, appendMessage, buildStaticReplyMatcher } from '../../core/render.js';
import { createHistoryStore } from '../../core/storage.js';
import { createLanguageState } from '../../core/i18n.js';
import { buildChatClient, buildPopupSignIn, ensurePuterReady, extractResponseText } from '../../core/puter.js';

const UI = {
  btn: document.getElementById('publicAssistantBtn'),
  window: document.getElementById('publicAssistantChat'),
  messages: document.getElementById('publicAssistantMessages'),
  input: document.getElementById('publicAssistantInput'),
  sendBtn: document.getElementById('sendToPublicAssistant'),
  loading: document.getElementById('publicAssistantLoading'),
  closeBtn: document.getElementById('closePublicAssistant'),
  status: document.getElementById('publicAssistantStatusText'),
  langBnBtn: document.getElementById('publicAssistantLangBn'),
  langEnBtn: document.getElementById('publicAssistantLangEn'),
  typingText: document.getElementById('publicAssistantTypingText'),
  footer: document.getElementById('publicAssistantFooter'),
  preChat: document.getElementById('publicAssistantPreChat'),
  btnNewChat: null
};

const CHAT_MODEL = typeof window.BROX_PUBLIC_ASSISTANT_MODEL === 'string' ? window.BROX_PUBLIC_ASSISTANT_MODEL.trim() : '';
const CHAT_STORAGE_KEY = 'brox.publicAssistant.chat.v2';
const LAST_ACTIVITY_KEY = 'brox.publicAssistant.lastActivity.v2';
const LANGUAGE_KEY = 'brox.publicAssistant.language.v2';
const USER_INFO_KEY = 'brox.publicAssistant.userInfo.v2';
const MAX_STORED_MESSAGES = 40;
const INACTIVITY_LIMIT_MS = 30 * 60 * 1000;
const PUTER_POPUP_SIZE = { width: 600, height: 700, timeoutMs: 2 * 60 * 1000 };
const ASSISTANT_SITE_URL = 'https://broxlab.online';
const CHAT_MODEL_PREFERENCES = [
  'openai/gpt-4.1-mini', 'openai/gpt-4o-mini', 'openai/gpt-4.1', 'openai/gpt-4o',
  'anthropic/claude-3-5-sonnet', 'anthropic/claude-3-7-sonnet',
  'google/gemini-2.0-flash', 'google/gemini-2.5-flash',
  'gpt-4.1-mini', 'gpt-4o-mini', 'gpt-4.1', 'gpt-4o',
  'claude-3-5-sonnet', 'claude-3-7-sonnet',
  'gemini-2.0-flash', 'gemini-2.5-flash'
];
const TOPIC_KEYS = ['general', 'support', 'billing', 'feedback'];

const I18N = {
  bn: {
    assistant_title: 'ব্রক্স সহকারী',
    assistant_status: 'বার্তা পাঠালে সংযুক্ত হবে',
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
const historyStore = createHistoryStore({
  storage: window.localStorage,
  chatKey: CHAT_STORAGE_KEY,
  activityKey: LAST_ACTIVITY_KEY,
  maxMessages: MAX_STORED_MESSAGES,
  inactivityMs: INACTIVITY_LIMIT_MS
});
let openSignInPopup = null;

function getOpenSignInPopup() {
  if (!openSignInPopup) {
    openSignInPopup = buildPopupSignIn({ popupSize: PUTER_POPUP_SIZE, t: (key) => t(key) });
  }
  return openSignInPopup;
}
let chatClient = null;

function getChatClient() {
  if (!chatClient) {
    chatClient = buildChatClient({ chatModel: CHAT_MODEL, modelPreferences: CHAT_MODEL_PREFERENCES });
  }
  return chatClient;
}

function t(key) {
  return I18N[currentLang]?.[key] || I18N.en[key] || key;
}

function setStatus(text) {
  if (UI.status) UI.status.textContent = text;
}

function setTyping(active) {
  UI.loading?.classList.toggle('d-none', !active);
  UI.loading?.classList.toggle('active', active);
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
  setStatus(t('assistant_status'));
  const started = performance.now();
  try {
    await ensurePuterReady({ interactive: true, t: (key) => t(key), openPopup: getOpenSignInPopup() });
    const response = await getChatClient().chatWithFallback(buildMessages(text));
    const aiText = extractResponseText(response) || t('fallback_error');
    const responseMs = Math.max(0, Math.round(performance.now() - started));
    chatHistory.push({ role: 'assistant', text: aiText, ts: new Date().toISOString(), responseMs });
    historyStore.save(chatHistory);
    await appendAssistant(UI.messages, aiText, { animate: true, responseMs });
    setStatus(t('assistant_status'));
  } catch (err) {
    const msg = String(err?.message || t('fallback_error'));
    await appendAssistant(UI.messages, msg, { animate: true });
    setStatus(msg);
  } finally {
    setTyping(false);
    historyStore.updateActivity();
  }
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

function init() {
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
