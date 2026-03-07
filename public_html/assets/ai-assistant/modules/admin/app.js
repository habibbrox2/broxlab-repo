import { scrollToBottom } from '../../core/dom.js';
import { appendAssistant, appendMessage, buildStaticReplyMatcher, formatBody } from '../../core/render.js';
import { createHistoryStore } from '../../core/storage.js';
import { createLanguageState } from '../../core/i18n.js';
import { buildChatClient, buildPopupSignIn, ensurePuterReady, extractResponseText, generateImage, getPuterClient, speakText } from '../../core/puter.js';
import { createLogMonitor } from '../../core/log-monitor.js';

const UI = {
  wrapper: document.getElementById('adminAssistantWrapper'),
  chat: document.getElementById('adminAssistantChat'),
  messages: document.getElementById('assistantMessages'),
  input: document.getElementById('assistantInput'),
  sendBtn: document.getElementById('sendToAssistant'),
  toggleBtn: document.getElementById('adminAssistantBtn'),
  closeBtn: document.getElementById('closeAssistant'),
  status: document.getElementById('adminAssistantStatus'),
  title: document.getElementById('adminAssistantTitle'),
  langBnBtn: document.getElementById('adminAssistantLangBn'),
  langEnBtn: document.getElementById('adminAssistantLangEn'),
  loading: document.getElementById('adminAssistantLoading'),
  typingText: document.getElementById('adminAssistantTypingText'),
  modeSelect: document.getElementById('adminAssistantMode'),
  modelSelect: document.getElementById('adminAssistantModel'),
  settingsToggle: document.getElementById('adminAssistantSettingsToggle'),
  advancedPanel: document.getElementById('adminAssistantAdvancedPanel'),
  streamToggle: document.getElementById('adminAssistantStream'),
  webSearchToggle: document.getElementById('adminAssistantWebSearch'),
  temperatureInput: document.getElementById('adminAssistantTemperature'),
  temperatureValue: document.getElementById('adminAssistantTemperatureValue'),
  maxTokensInput: document.getElementById('adminAssistantMaxTokens'),
  reasoningSelect: document.getElementById('adminAssistantReasoning'),
  verbositySelect: document.getElementById('adminAssistantVerbosity'),
  imageModelSelect: document.getElementById('adminAssistantImageModel'),
  imageAspectSelect: document.getElementById('adminAssistantImageAspect'),
  ttsVoiceSelect: document.getElementById('adminAssistantTtsVoice'),
  fileInput: document.getElementById('adminAssistantFileInput'),
  visionInput: document.getElementById('adminAssistantVisionInput'),
  imageUrlInput: document.getElementById('adminAssistantImageUrl'),
  attachmentName: document.getElementById('adminAssistantAttachmentName'),
  attachVisionBtn: document.getElementById('btnAttachVision'),
  generateImageBtn: document.getElementById('btnGenerateImage'),
  speakLastReplyBtn: document.getElementById('btnSpeakLastReply'),
  reasoningBanner: document.getElementById('adminAssistantReasoningBanner')
};

const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const CHAT_MODEL = typeof window.BROX_ADMIN_ASSISTANT_MODEL === 'string' ? window.BROX_ADMIN_ASSISTANT_MODEL.trim() : '';
const CHAT_STORAGE_KEY = 'brox.adminAssistant.chat.v3';
const LAST_ACTIVITY_KEY = 'brox.adminAssistant.lastActivity.v3';
const LANGUAGE_KEY = 'brox.adminAssistant.language.v3';
const PREFS_STORAGE_KEY = 'brox.adminAssistant.prefs.v1';
const MAX_STORED_MESSAGES = 40;
const INACTIVITY_LIMIT_MS = 30 * 60 * 1000;
const PUTER_POPUP_SIZE = { width: 600, height: 700, timeoutMs: 2 * 60 * 1000 };
const CHAT_MODEL_PREFERENCES = [
  'openai/gpt-4.1-mini', 'openai/gpt-4o-mini', 'openai/gpt-4.1', 'openai/gpt-4o',
  'anthropic/claude-3-5-sonnet', 'anthropic/claude-3-7-sonnet',
  'google/gemini-2.0-flash', 'google/gemini-2.5-flash',
  'gpt-4.1-mini', 'gpt-4o-mini', 'gpt-4.1', 'gpt-4o', 'claude-3-5-sonnet', 'claude-3-7-sonnet',
  'gemini-2.0-flash', 'gemini-2.5-flash'
];
const ASSISTANT_SITE_URL = 'https://broxlab.online';
const OPENAI_PROVIDER = 'openai';
const DEFAULT_IMAGE_MODEL = 'gpt-image-1.5';
const DEFAULT_TTS_MODEL = 'gpt-4o-mini-tts';
const FALLBACK_OPENAI_MODELS = [
  { value: '', label: 'Auto Model' },
  { value: 'gpt-5-nano', label: 'gpt-5-nano' },
  { value: 'gpt-5.4', label: 'gpt-5.4' },
  { value: 'gpt-5.3-chat', label: 'gpt-5.3-chat' },
  { value: 'gpt-5.2', label: 'gpt-5.2' },
  { value: 'gpt-5', label: 'gpt-5' },
  { value: 'gpt-4.1', label: 'gpt-4.1' },
  { value: 'gpt-4o', label: 'gpt-4o' },
  { value: 'openai/gpt-5.2-chat', label: 'openai/gpt-5.2-chat' },
  { value: 'gpt-5.3-codex', label: 'gpt-5.3-codex' },
  { value: 'openai/gpt-oss-120b', label: 'openai/gpt-oss-120b' },
  { value: 'openai/gpt-oss-20b', label: 'openai/gpt-oss-20b' }
];
const MODE_PRESETS = {
  assistant: { model: '', useAdminTools: true, stream: false, webSearch: true },
  openai: { model: 'gpt-5-nano', useAdminTools: false, stream: true, webSearch: true },
  codex: { model: 'gpt-5.3-codex', useAdminTools: false, stream: true, webSearch: false },
  gpt_oss: { model: 'openai/gpt-oss-120b', useAdminTools: false, stream: true, webSearch: false, reasoningEffort: 'high' },
  vision: { model: 'gpt-5-nano', useAdminTools: false, stream: true, webSearch: false }
};
const DEFAULT_PREFS = {
  mode: 'assistant',
  model: '',
  stream: true,
  webSearch: true,
  temperature: 0.7,
  maxTokens: 1024,
  reasoningEffort: 'medium',
  textVerbosity: 'medium',
  imageModel: DEFAULT_IMAGE_MODEL,
  imageAspect: '1:1',
  ttsVoice: 'alloy'
};

const REDIRECT_ACTIONS = new Set([
  'create_mobile', 'edit_mobile', 'create_post', 'edit_post', 'create_page', 'edit_page', 'create_service', 'edit_service'
]);

const ACTION_PERMISSIONS = {
  create_post: 'post.create', edit_post: 'post.edit', delete_post: 'post.delete',
  create_service: 'service.create', edit_service: 'service.edit', delete_service: 'service.delete',
  create_page: 'page.create', edit_page: 'page.edit', delete_page: 'page.delete',
  create_category: 'category.create', edit_category: 'category.edit', delete_category: 'category.delete',
  create_tag: 'tag.create', edit_tag: 'tag.edit', delete_tag: 'tag.delete',
  create_mobile: 'mobile.create', edit_mobile: 'mobile.edit', delete_mobile: 'mobile.delete'
};

const LINK_ACTIONS = {
  upload_file: { url: '/admin/media/upload', label: '/admin/media/upload' },
  view_uploads: { url: '/admin/media', label: '/admin/media' },
  manage_service_applications: { url: '/admin/applications', label: '/admin/applications' },
  manage_payments: { url: '/admin/payments', label: '/admin/payments' },
  manage_chats: { url: '/admin/contact', label: '/admin/contact' }
};

const I18N = {
  bn: {
    title: 'ব্রক্স সহকারী',
    input_placeholder: 'আপনার নির্দেশ লিখুন...',
    typing_text: 'টাইপ করছে...',
    default_greeting: 'আমি আপনার BroxLab অ্যাডমিন সহকারী। পোস্ট, পেজ, সার্ভিস, মিডিয়া, অ্যানালিটিক্স বা অন্য অ্যাডমিন কাজ নিয়ে জিজ্ঞেস করুন। প্রথম মেসেজে Puter সাইন-ইন চাইতে পারে।',
    status_initializing: 'চালু হচ্ছে...',
    status_ready: 'সহকারী প্রস্তুত',
    status_ready_to_connect: 'বার্তা পাঠালে সংযুক্ত হবে',
    status_connecting: 'Puter-এ সংযুক্ত হচ্ছে...',
    status_error: 'ত্রুটি হয়েছে',
    status_response: 'রেসপন্স',
    notice_session_expired: 'নিষ্ক্রিয়তার কারণে পূর্বের চ্যাট শেষ হয়েছে। নতুন সেশন শুরু।',
    notice_chat_reset: 'নিষ্ক্রিয়তার কারণে চ্যাট রিসেট হয়েছে।',
    notice_new_chat: 'নতুন চ্যাট শুরু হয়েছে।',
    notice_unknown_tool: 'সহকারী একটি অজানা টুল ব্যবহার করতে চেয়েছিল।',
    error_puter_missing: 'Puter ক্লায়েন্ট লোড হয়নি।',
    error_sign_in_required: 'চালিয়ে যেতে Puter সাইন-ইন দরকার।',
    error_sign_in_popup_blocked: 'Puter সাইন-ইন পপআপ ব্লক হয়েছে। পপআপ allow করুন।',
    error_sign_in_popup_closed: 'পপআপ বন্ধ হওয়ায় সাইন-ইন সম্পন্ন হয়নি।',
    error_sign_in_timeout: 'Puter সাইন-ইনে সময়সীমা অতিক্রম। আবার চেষ্টা করুন।'
  },
  en: {
    title: 'Brox Assistant',
    input_placeholder: 'Type your instruction...',
    typing_text: 'Typing...',
    default_greeting: 'I am your Brox admin assistant. Ask about posts, pages, services, media, analytics, or other admin work. Puter may ask you to sign in on your first message.',
    status_initializing: 'Initializing...',
    status_ready: 'Assistant Ready',
    status_ready_to_connect: 'Will connect on first message',
    status_connecting: 'Connecting to Puter...',
    status_error: 'Error occurred',
    status_response: 'Response',
    status_navigating: 'Opening page...',
    notice_session_expired: 'Previous chat expired; starting fresh.',
    notice_chat_reset: 'Chat reset due to inactivity.',
    notice_new_chat: 'Started a fresh chat.',
    notice_unknown_tool: 'Assistant tried an unknown tool.',
    error_puter_missing: 'Puter client is not loaded.',
    error_sign_in_required: 'You need to sign in with Puter.',
    error_sign_in_popup_blocked: 'Puter sign-in popup was blocked.',
    error_sign_in_popup_closed: 'Puter sign-in popup closed early.',
    error_sign_in_timeout: 'Puter sign-in timed out.'
  }
};

const STATIC_REPLIES = {
  bn: {
    name: 'আমি brox বলছি, BroxLab অ্যাডমিন সহকারী হিসেবে আপনাকে তথ্য ও সাপোর্টে সাহায্য করি।',
    about: `আমি brox বলছি। BroxLab হলো ${ASSISTANT_SITE_URL} শিরোনামের Bengali-first tech platform, যেখানে কনটেন্ট, সেবা ও ডিজিটাল তথ্য সাজানোভাবে প্রকাশ করা হয়।`
  },
  en: {
    name: 'I am Brox, speaking as the BroxLab assistant.',
    about: `I am Brox. BroxLab is the Bengali-first tech platform at ${ASSISTANT_SITE_URL}.`
  }
};

const getStaticReply = buildStaticReplyMatcher(STATIC_REPLIES);
const { getLanguage, setLanguage } = createLanguageState({ storageKey: LANGUAGE_KEY, storage: window.sessionStorage });
let currentLang = getLanguage();
let assistantPrefs = { ...DEFAULT_PREFS };
let contextAttachments = [];
let lastAssistantReplyText = '';
let lastAssistantAudio = null;
const historyStore = createHistoryStore({
  storage: window.sessionStorage,
  chatKey: CHAT_STORAGE_KEY,
  activityKey: LAST_ACTIVITY_KEY,
  maxMessages: MAX_STORED_MESSAGES,
  inactivityMs: INACTIVITY_LIMIT_MS
});
let openSignInPopup = null;

function getOpenSignInPopup() {
  if (!openSignInPopup) {
    openSignInPopup = buildPopupSignIn({ popupSize: PUTER_POPUP_SIZE, t: (key) => I18N[currentLang]?.[key] });
  }
  return openSignInPopup;
}
let chatClient = null;

function getChatClient(modelOverride = '') {
  const requestedModel = String(modelOverride || '').trim();
  const cacheKey = requestedModel || CHAT_MODEL || '__auto__';
  if (!chatClient || chatClient.cacheKey !== cacheKey) {
    chatClient = {
      cacheKey,
      instance: buildChatClient({
        chatModel: requestedModel || CHAT_MODEL,
        modelPreferences: CHAT_MODEL_PREFERENCES
      })
    };
  }
  return chatClient.instance;
}
let chatHistory = [];
let historyExpired = false;

// Initialize log monitor for real-time error tracking
const logMonitor = createLogMonitor({ pollIntervalMs: 15000 });

function t(key) {
  return I18N[currentLang]?.[key] || I18N.en[key] || key;
}

function setStatus(textKey, { raw = false } = {}) {
  if (!UI.status) return;
  if (raw) {
    UI.status.textContent = textKey;
    return;
  }
  UI.status.textContent = t(textKey);
}

function loadAssistantPrefs() {
  try {
    const raw = window.sessionStorage.getItem(PREFS_STORAGE_KEY);
    if (!raw) {
      assistantPrefs = { ...DEFAULT_PREFS };
      return;
    }
    const parsed = JSON.parse(raw);
    assistantPrefs = {
      ...DEFAULT_PREFS,
      ...parsed,
      temperature: Number.isFinite(Number(parsed?.temperature)) ? Number(parsed.temperature) : DEFAULT_PREFS.temperature,
      maxTokens: Number.isFinite(Number(parsed?.maxTokens)) ? Number(parsed.maxTokens) : DEFAULT_PREFS.maxTokens
    };
  } catch {
    assistantPrefs = { ...DEFAULT_PREFS };
  }
}

function saveAssistantPrefs() {
  try {
    window.sessionStorage.setItem(PREFS_STORAGE_KEY, JSON.stringify(assistantPrefs));
  } catch {
    // ignore storage failures
  }
}

function getModePreset(mode = assistantPrefs.mode) {
  return MODE_PRESETS[mode] || MODE_PRESETS.assistant;
}

function getSelectedChatModel() {
  return String(assistantPrefs.model || getModePreset().model || CHAT_MODEL || '').trim();
}

function isOpenAIModel(model) {
  const normalized = String(model || '').trim().toLowerCase();
  if (!normalized) return true;
  return normalized.startsWith('openai/')
    || normalized.startsWith('gpt-')
    || normalized.startsWith('codex')
    || normalized.startsWith('gpt-oss');
}

function shouldUseAdminTools() {
  return getModePreset().useAdminTools === true;
}

function shouldUseStream() {
  if (!assistantPrefs.stream) return false;
  if (shouldUseAdminTools()) return false;
  return true;
}

function shouldUseWebSearch() {
  return assistantPrefs.webSearch && isOpenAIModel(getSelectedChatModel());
}

function updateTemperatureLabel() {
  if (!UI.temperatureValue || !UI.temperatureInput) return;
  UI.temperatureValue.textContent = Number(UI.temperatureInput.value || assistantPrefs.temperature || 0.7).toFixed(1);
}

function sanitizeAttachmentName(name) {
  return String(name || 'file')
    .replace(/[^a-zA-Z0-9._-]+/g, '_')
    .replace(/^_+|_+$/g, '')
    || 'file';
}

function getImageUrlAttachment() {
  const imageUrl = String(UI.imageUrlInput?.value || '').trim();
  return imageUrl ? { name: imageUrl, kind: 'url', url: imageUrl } : null;
}

function getAllContextAttachments() {
  const imageUrlAttachment = getImageUrlAttachment();
  return imageUrlAttachment ? [...contextAttachments, imageUrlAttachment] : [...contextAttachments];
}

function addContextAttachments(files = []) {
  const nextFiles = Array.from(files || [])
    .filter((file) => file instanceof File)
    .map((file) => ({
      id: `attachment_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
      kind: 'file',
      file,
      name: file.name,
      type: file.type,
      size: file.size,
      puterPath: ''
    }));

  if (!nextFiles.length) return;
  contextAttachments = [...contextAttachments, ...nextFiles];
}

async function uploadContextAttachments() {
  const pending = contextAttachments.filter((attachment) => attachment.kind === 'file' && attachment.file && !attachment.puterPath);
  if (!pending.length) return;

  const puter = await getPuterClient();
  for (const attachment of pending) {
    const safeName = sanitizeAttachmentName(attachment.name);
    const remoteName = `brox_assistant_${Date.now()}_${safeName}`;
    const uploaded = await puter.fs.write(remoteName, attachment.file);
    const puterPath = String(uploaded?.path || uploaded?.puter_path || '').trim();
    if (!puterPath) {
      throw new Error(`Failed to upload ${attachment.name} as assistant context.`);
    }
    attachment.puterPath = puterPath;
  }
}

async function cleanupUploadedContextAttachments() {
  const uploadedPaths = contextAttachments
    .map((attachment) => String(attachment?.puterPath || '').trim())
    .filter(Boolean);

  if (!uploadedPaths.length) return;

  try {
    const puter = await getPuterClient();
    for (const puterPath of uploadedPaths) {
      try {
        await puter.fs.delete(puterPath);
      } catch {
        // ignore cleanup failures
      }
    }
  } catch {
    // ignore cleanup failures
  }
}

function buildAttachmentSummary() {
  const attachments = getAllContextAttachments();
  if (!attachments.length) return '';

  return attachments
    .map((attachment) => {
      if (attachment.kind === 'url') return `[Image URL: ${attachment.url}]`;
      return `[File: ${attachment.name}]`;
    })
    .join('\n');
}

function hasUploadedContextFiles() {
  return contextAttachments.some((attachment) => attachment.kind === 'file');
}

function hasContextInputs() {
  return getAllContextAttachments().length > 0;
}

function updateAttachmentChip() {
  if (!UI.attachmentName) return;
  const label = getAllContextAttachments()
    .map((attachment) => attachment.kind === 'url' ? attachment.url : attachment.name)
    .join(' | ');
  UI.attachmentName.textContent = label;
  UI.attachmentName.classList.toggle('d-none', !label);
}

function clearAttachmentState({ clearUrl = true } = {}) {
  contextAttachments = [];
  if (UI.fileInput) UI.fileInput.value = '';
  if (UI.visionInput) UI.visionInput.value = '';
  if (clearUrl && UI.imageUrlInput) UI.imageUrlInput.value = '';
  updateAttachmentChip();
}

function applyAssistantPrefsToUi() {
  if (UI.modeSelect) UI.modeSelect.value = assistantPrefs.mode;
  if (UI.modelSelect) UI.modelSelect.value = assistantPrefs.model;
  if (UI.streamToggle) UI.streamToggle.checked = assistantPrefs.stream;
  if (UI.webSearchToggle) UI.webSearchToggle.checked = assistantPrefs.webSearch;
  if (UI.temperatureInput) UI.temperatureInput.value = String(assistantPrefs.temperature);
  if (UI.maxTokensInput) UI.maxTokensInput.value = String(assistantPrefs.maxTokens);
  if (UI.reasoningSelect) UI.reasoningSelect.value = assistantPrefs.reasoningEffort || '';
  if (UI.verbositySelect) UI.verbositySelect.value = assistantPrefs.textVerbosity || 'medium';
  if (UI.imageModelSelect) UI.imageModelSelect.value = assistantPrefs.imageModel;
  if (UI.imageAspectSelect) UI.imageAspectSelect.value = assistantPrefs.imageAspect;
  if (UI.ttsVoiceSelect) UI.ttsVoiceSelect.value = assistantPrefs.ttsVoice;
  updateTemperatureLabel();
}

function syncModePreset(mode = assistantPrefs.mode, { preserveModel = false } = {}) {
  const preset = getModePreset(mode);
  assistantPrefs.mode = mode;
  assistantPrefs.stream = preset.stream;
  assistantPrefs.webSearch = preset.webSearch;
  if (!preserveModel) {
    assistantPrefs.model = preset.model;
  }
  if (preset.reasoningEffort) {
    assistantPrefs.reasoningEffort = preset.reasoningEffort;
  }
  saveAssistantPrefs();
  applyAssistantPrefsToUi();
}

function setReasoningBanner(text = '') {
  if (!UI.reasoningBanner) return;
  const normalized = String(text || '').trim();
  UI.reasoningBanner.textContent = normalized;
  UI.reasoningBanner.classList.toggle('d-none', !normalized);
}

async function populateModelOptions() {
  if (!UI.modelSelect) return;

  const currentValue = assistantPrefs.model;
  const options = [...FALLBACK_OPENAI_MODELS];

  try {
    await ensurePuterReady({ interactive: false, t: (key) => t(key) });
    const puter = await getPuterClient();
    const models = await puter.ai.listModels(OPENAI_PROVIDER);
    const openAiModels = Array.isArray(models)
      ? models
          .map((model) => {
            const id = String(model?.id || model?.model || model?.name || '').trim();
            if (!id) return null;
            return { value: id, label: id };
          })
          .filter(Boolean)
      : [];

    for (const model of openAiModels) {
      if (!options.some((item) => item.value === model.value)) {
        options.push(model);
      }
    }
  } catch {
    // fallback list is enough when model discovery is unavailable
  }

  UI.modelSelect.innerHTML = '';
  for (const option of options) {
    const node = document.createElement('option');
    node.value = option.value;
    node.textContent = option.label;
    UI.modelSelect.appendChild(node);
  }

  UI.modelSelect.value = currentValue;
}

function applyLanguage() {
  UI.title && (UI.title.textContent = t('title'));
  UI.input && UI.input.setAttribute('placeholder', t('input_placeholder'));
  UI.typingText && (UI.typingText.textContent = t('typing_text'));
  setStatus('status_ready_to_connect');
  updateLangButtons();
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

function setTyping(active) {
  if (!UI.loading) return;
  UI.loading.classList.toggle('d-none', !active);
  UI.loading.classList.toggle('active', active);
}

function attachSpeechAction(messageNode, text) {
  if (!messageNode || !text) return;
  const actions = document.createElement('div');
  actions.className = 'assistant-message-actions';
  const speakBtn = document.createElement('button');
  speakBtn.type = 'button';
  speakBtn.className = 'assistant-message-action-btn';
  speakBtn.innerHTML = '<i class="bi bi-volume-up"></i>';
  speakBtn.title = 'Speak this reply';
  speakBtn.addEventListener('click', async () => {
    try {
      await ensurePuterReady({ interactive: true, t: (key) => t(key), openPopup: getOpenSignInPopup() });
      if (lastAssistantAudio) {
        lastAssistantAudio.pause?.();
      }
      lastAssistantAudio = await speakText(text, {
        provider: OPENAI_PROVIDER,
        model: DEFAULT_TTS_MODEL,
        voice: assistantPrefs.ttsVoice
      });
      lastAssistantAudio.play?.();
    } catch (error) {
      appendAssistant(UI.messages, String(error?.message || 'Text-to-speech failed'), { animate: true });
    }
  });
  actions.appendChild(speakBtn);
  messageNode.appendChild(actions);
}

function createStreamingAssistantMessage() {
  if (!UI.messages) return null;
  const msg = document.createElement('div');
  msg.className = 'message assistant';

  const reasoning = document.createElement('div');
  reasoning.className = 'assistant-stream-reasoning d-none';
  msg.appendChild(reasoning);

  const body = document.createElement('div');
  body.className = 'message-content';
  msg.appendChild(body);

  const meta = document.createElement('div');
  meta.className = 'message-time';
  msg.appendChild(meta);

  UI.messages.appendChild(msg);
  scrollToBottom(UI.messages);

  return {
    node: msg,
    setText(text) {
      body.innerHTML = formatBody(text);
      scrollToBottom(UI.messages);
    },
    setReasoning(text) {
      const normalized = String(text || '').trim();
      reasoning.textContent = normalized;
      reasoning.classList.toggle('d-none', !normalized);
      setReasoningBanner(normalized);
      scrollToBottom(UI.messages);
    },
    finalize({ text, responseMs }) {
      lastAssistantReplyText = text;
      meta.textContent = responseMs < 1000 ? `${responseMs}ms` : `${(responseMs / 1000).toFixed(1)}s`;
      attachSpeechAction(msg, text);
      setReasoningBanner('');
    }
  };
}

function renderWelcome() {
  const msg = appendMessage(UI.messages, 'assistant', t('default_greeting'));
  attachSpeechAction(msg, t('default_greeting'));
}

function renderHistory() {
  if (!UI.messages) return;
  UI.messages.querySelectorAll('.message').forEach((node) => node.remove());
  if (!chatHistory.length) {
    renderWelcome();
    return;
  }
  chatHistory.forEach((row) => {
    const msg = appendMessage(UI.messages, row.role, row.text, { ts: row.ts, responseMs: row.responseMs });
    if (row.role === 'assistant') {
      attachSpeechAction(msg, row.text);
    }
  });
}

function buildSystemPrompt() {
  const mode = assistantPrefs.mode;
  const selectedModel = getSelectedChatModel() || 'auto';
  return [
    'You are BroxAdmin Assistant, a bilingual admin-panel helper for the BroxBhai dashboard.',
    'Your name is Brox.',
    `BroxLab website: ${ASSISTANT_SITE_URL}.`,
    `Current UI language: ${currentLang === 'bn' ? 'Bangla' : 'English'}.`,
    `Current admin page: ${window.location.pathname}.`,
    `Current AI mode: ${mode}.`,
    `Selected chat model: ${selectedModel}.`,
    'Keep replies concise and practical.',
    'Prefer the current UI language unless the user clearly asks for another language.',
    'If asked your name, answer as Brox and mention BroxLab clearly.',
    'If asked about yourself or broxlab.online, describe briefly and include the site URL.',
    mode === 'assistant'
      ? 'Use the admin_action tool when the user wants navigation, admin mutations, analytics data, media links, log viewing, or a fresh chat.'
      : 'Stay in direct AI mode unless the user explicitly asks to switch back to admin tool mode.',
    mode === 'assistant'
      ? 'When the user asks to add/create/edit/update content, send them to the relevant admin form with prefilled fields.'
      : 'When coding help is requested in Codex mode, prefer code-first answers with concrete snippets or diffs.',
    'Ask a follow-up question instead of calling the tool when details are missing.',
    'Use at most one admin_action call per turn unless another is required.',
    '',
    'LOG MONITORING CAPABILITIES:',
    'You can view and report application logs using: view_error_logs, view_all_logs, view_log_stats, get_recent_errors actions.',
    'When admin asks "show errors", "check logs", "view error log", etc., use the appropriate log viewing action.',
    'The system actively monitors for new errors and will alert the admin when they occur. You are also aware of these alerts.',
    'Always respond to error-related questions by fetching and displaying relevant log information.'
  ].join('\n');
}

function buildEnhancementPrompt(content) {
  return [
    'You are an expert Bengali-English content enhancement specialist.',
    'Your task is to improve the provided article/post content.',
    '',
    'CRITICAL: Return ONLY valid, clean HTML. Do NOT use markdown, code fences, or explanations.',
    '',
    'Enhancement focus areas:',
    '1. Fix grammar and spelling in both Bengali and English',
    '2. Improve Bengali language quality, naturalness, and flow',
    '3. Restructure content with proper heading hierarchy (h1, h2, h3, etc.)',
    '4. Optimize for SEO by improving clarity and logical structure',
    '5. Apply professional formatting with proper paragraphs, lists, and spacing',
    '6. Preserve all embedded images, links, media, and custom formatting',
    '7. Keep original meaning and intent intact',
    '',
    'CONTENT TO ENHANCE:',
    content,
    '',
    'RESPONSE FORMAT: Return ONLY the enhanced HTML content. No explanations, no markdown, no code fences. Just clean HTML.'
  ].join('\n');
}

async function buildMessages(userText, { defaultUserText = '' } = {}) {
  if (hasUploadedContextFiles()) {
    await uploadContextAttachments();
  }

  const normalizedText = String(userText || '').trim() || defaultUserText;
  const fileParts = contextAttachments
    .map((attachment) => {
      const puterPath = String(attachment?.puterPath || '').trim();
      if (!puterPath) return null;
      return {
        type: 'file',
        puter_path: puterPath
      };
    })
    .filter(Boolean);

  const userContent = fileParts.length
    ? [
        ...fileParts,
        ...(normalizedText ? [{ type: 'text', text: normalizedText }] : [])
      ]
    : normalizedText;

  return [
    { role: 'system', content: buildSystemPrompt() },
    ...chatHistory.map((row) => ({ role: row.role, content: row.text })),
    { role: 'user', content: userContent }
  ];
}

function buildRuntimeOptions({ includeTools = false, tools = [], stream = false } = {}) {
  return {
    includeTools,
    tools,
    stream,
    temperature: assistantPrefs.temperature,
    maxTokens: assistantPrefs.maxTokens,
    reasoningEffort: assistantPrefs.reasoningEffort,
    textVerbosity: assistantPrefs.textVerbosity
  };
}

async function runStreamedConversation(messages) {
  const started = performance.now();
  const streamMessage = createStreamingAssistantMessage();
  const tools = shouldUseWebSearch() ? [{ type: 'web_search' }] : [];
  const stream = await getChatClient(getSelectedChatModel()).chatWithFallback(
    messages,
    buildRuntimeOptions({ stream: true, includeTools: tools.length > 0, tools })
  );

  let text = '';
  let reasoning = '';
  for await (const part of stream) {
    if (typeof part?.reasoning === 'string') {
      reasoning += part.reasoning;
      streamMessage?.setReasoning(reasoning);
    }
    if (typeof part?.text === 'string') {
      text += part.text;
      streamMessage?.setText(text);
    }
  }

  const responseMs = Math.max(0, Math.round(performance.now() - started));
  streamMessage?.finalize({ text, responseMs });
  return { text, responseMs };
}

async function runVisionConversation(prompt) {
  const puter = await getPuterClient();
  const model = getSelectedChatModel() || MODE_PRESETS.vision.model;
  const imageUrl = String(UI.imageUrlInput?.value || '').trim();
  const hasUploadedFiles = hasUploadedContextFiles();
  const contentPrompt = String(prompt || '').trim() || 'Analyze this image in detail for an admin workflow.';

  if (!hasUploadedFiles && !imageUrl) {
    throw new Error('Attach an image or file, or provide an image URL first.');
  }

  if (hasUploadedFiles) {
    const messages = await buildMessages(contentPrompt, {
      defaultUserText: 'Analyze these uploaded files in detail for this admin workflow.'
    });

    if (shouldUseStream()) {
      return runStreamedConversation(messages);
    }

    const response = await getChatClient(model).chatWithFallback(
      messages,
      buildRuntimeOptions({ includeTools: false })
    );

    return { text: extractResponseText(response) || t('status_error'), responseMs: 0 };
  }

  if (shouldUseStream()) {
    const started = performance.now();
    const streamMessage = createStreamingAssistantMessage();
    const stream = await puter.ai.chat(contentPrompt, imageUrl, {
      model,
      stream: true,
      temperature: assistantPrefs.temperature,
      max_tokens: assistantPrefs.maxTokens,
      reasoning_effort: assistantPrefs.reasoningEffort,
      verbosity: assistantPrefs.textVerbosity
    });

    let text = '';
    let reasoning = '';
    for await (const part of stream) {
      if (typeof part?.reasoning === 'string') {
        reasoning += part.reasoning;
        streamMessage?.setReasoning(reasoning);
      }
      if (typeof part?.text === 'string') {
        text += part.text;
        streamMessage?.setText(text);
      }
    }

    const responseMs = Math.max(0, Math.round(performance.now() - started));
    streamMessage?.finalize({ text, responseMs });
    return { text, responseMs };
  }

  const response = await puter.ai.chat(contentPrompt, imageUrl, {
    model,
    temperature: assistantPrefs.temperature,
    max_tokens: assistantPrefs.maxTokens,
    reasoning_effort: assistantPrefs.reasoningEffort,
    verbosity: assistantPrefs.textVerbosity
  });

  return { text: extractResponseText(response) || t('status_error'), responseMs: 0 };
}

async function handleImageGeneration(prompt) {
  const normalizedPrompt = String(prompt || '').trim();
  if (!normalizedPrompt) {
    throw new Error('Write an image prompt first.');
  }

  appendMessage(UI.messages, 'user', `Generate image: ${normalizedPrompt}`);

  const image = await generateImage(normalizedPrompt, {
    model: assistantPrefs.imageModel || DEFAULT_IMAGE_MODEL,
    aspectRatio: assistantPrefs.imageAspect || '1:1'
  });

  const html = [
    '<div class="assistant-image-card">',
    `<img src="${image.src}" alt="Generated image">`,
    `<a href="${image.src}" target="_blank" rel="noopener noreferrer">Open image</a>`,
    '</div>'
  ].join('');

  const msg = await appendAssistant(UI.messages, html, { animate: true, trustedHtml: true });
  attachSpeechAction(msg, `Generated image for prompt: ${normalizedPrompt}`);
  lastAssistantReplyText = `Generated image for prompt: ${normalizedPrompt}`;
}

async function speakLastReply() {
  if (!lastAssistantReplyText) {
    throw new Error('No assistant reply available to speak yet.');
  }
  if (lastAssistantAudio) {
    lastAssistantAudio.pause?.();
  }
  lastAssistantAudio = await speakText(lastAssistantReplyText, {
    provider: OPENAI_PROVIDER,
    model: DEFAULT_TTS_MODEL,
    voice: assistantPrefs.ttsVoice
  });
  lastAssistantAudio.play?.();
}

function buildRedirectUrl(action, params = {}) {
  const id = params?.id ? String(params.id).trim() : '';
  let base = '';
  switch (action) {
    case 'create_mobile': base = '/admin/mobiles/insert'; break;
    case 'edit_mobile': base = id ? `/admin/mobiles/update/${encodeURIComponent(id)}` : ''; break;
    case 'create_post': base = '/admin/posts/create'; break;
    case 'edit_post': base = '/admin/posts/edit'; break;
    case 'create_page': base = '/admin/pages/create'; break;
    case 'edit_page': base = '/admin/pages/edit'; break;
    case 'create_service': base = '/admin/services/create'; break;
    case 'edit_service': base = id ? `/admin/services/${encodeURIComponent(id)}/edit` : ''; break;
    default: base = '';
  }
  if (!base) return '';
  const query = new URLSearchParams();
  query.set('assistant_prefill', '1');
  if ((action === 'edit_post' || action === 'edit_page') && id) {
    query.set('id', id);
  }
  Object.entries(params || {}).forEach(([key, value]) => {
    if (key === 'id' || value === undefined || value === null || value === '') return;
    if (Array.isArray(value)) {
      value.filter(Boolean).forEach((v) => query.append(`${key}[]`, String(v)));
      return;
    }
    query.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
  });
  const suffix = query.toString();
  return suffix ? `${base}?${suffix}` : base;
}

function hasPermission(permission) {
  if (!permission) return true;
  if (window.IS_SUPER_ADMIN) return true;
  const list = Array.isArray(window.ADMIN_PERMISSIONS) ? window.ADMIN_PERMISSIONS : [];
  return list.includes(permission);
}

function handleAdminAction(toolCall) {
  const name = toolCall?.function?.name || toolCall?.name;
  if (name !== 'admin_action') return false;
  const rawArgs = toolCall?.function?.arguments || toolCall?.arguments || '{}';
  let args = {};
  if (typeof rawArgs === 'string') {
    try { args = JSON.parse(rawArgs); } catch { args = {}; }
  } else if (rawArgs && typeof rawArgs === 'object') {
    args = rawArgs;
  }
  const action = String(args.action || '').trim();
  const params = args.params || {};
  if (!action) return false;

  if (!hasPermission(ACTION_PERMISSIONS[action])) {
    appendAssistant(UI.messages, t('notice_unknown_tool'));
    return true;
  }

  if (action === 'start_new_chat') {
    resetChat({ noticeKey: 'notice_new_chat' });
    return true;
  }

  if (REDIRECT_ACTIONS.has(action)) {
    const url = buildRedirectUrl(action, params);
    if (url) {
      setStatus('status_response');
      window.location.href = url;
      appendAssistant(UI.messages, t('status_navigating'), { animate: true });
    }
    return true;
  }

  if (LINK_ACTIONS[action]) {
    const link = LINK_ACTIONS[action];
    appendAssistant(UI.messages, `${t('status_response')}: ${link.label}`);
    window.location.href = link.url;
    return true;
  }

  // Handle log viewing actions
  if (action === 'view_error_logs') {
    handleViewErrorLogs(params);
    return true;
  }

  if (action === 'view_all_logs') {
    handleViewAllLogs(params);
    return true;
  }

  if (action === 'view_log_stats') {
    handleViewLogStats();
    return true;
  }

  if (action === 'get_recent_errors') {
    handleGetRecentErrors(params);
    return true;
  }

  return false;
}

function resetChat({ noticeKey } = {}) {
  cleanupUploadedContextAttachments();
  clearAttachmentState({ clearUrl: true });
  chatHistory = [];
  historyStore.save(chatHistory);
  renderHistory();
  if (noticeKey) appendAssistant(UI.messages, t(noticeKey), { animate: true });
}

function initQuickActionBar() {
  const inputGroup = UI.chat?.querySelector('.input-group');
  if (!inputGroup || inputGroup.querySelector('.assistant-action-strip')) return;
  const actionStrip = document.createElement('div');
  actionStrip.className = 'assistant-action-strip';
  ['btnNewChat', 'btnUploadFiles', 'btnViewUploads'].forEach((id) => {
    const btn = document.getElementById(id);
    if (btn) {
      btn.classList.add('assistant-action-btn');
      actionStrip.appendChild(btn);
    }
  });
  inputGroup.insertBefore(actionStrip, inputGroup.firstChild);
  const manageChats = document.getElementById('btnManageChats');
  if (manageChats) manageChats.remove();
}

function bindEvents() {
  UI.langBnBtn?.addEventListener('click', () => { currentLang = 'bn'; setLanguage('bn'); applyLanguage(); renderHistory(); });
  UI.langEnBtn?.addEventListener('click', () => { currentLang = 'en'; setLanguage('en'); applyLanguage(); renderHistory(); });

  UI.toggleBtn?.addEventListener('click', () => {
    UI.chat?.classList.toggle('hidden');
    UI.chat?.classList.toggle('d-none');
  });

  UI.closeBtn?.addEventListener('click', () => {
    UI.chat?.classList.add('hidden');
    UI.chat?.classList.add('d-none');
  });

  UI.sendBtn?.addEventListener('click', handleUserMessage);
  UI.input?.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') handleUserMessage();
  });

  UI.settingsToggle?.addEventListener('click', () => {
    UI.advancedPanel?.classList.toggle('d-none');
  });

  UI.modeSelect?.addEventListener('change', (event) => {
    syncModePreset(String(event.target.value || 'assistant'));
    setStatus('status_ready_to_connect');
  });

  UI.modelSelect?.addEventListener('change', (event) => {
    assistantPrefs.model = String(event.target.value || '').trim();
    saveAssistantPrefs();
  });

  UI.streamToggle?.addEventListener('change', (event) => {
    assistantPrefs.stream = Boolean(event.target.checked);
    saveAssistantPrefs();
  });

  UI.webSearchToggle?.addEventListener('change', (event) => {
    assistantPrefs.webSearch = Boolean(event.target.checked);
    saveAssistantPrefs();
  });

  UI.temperatureInput?.addEventListener('input', (event) => {
    assistantPrefs.temperature = Number(event.target.value || DEFAULT_PREFS.temperature);
    updateTemperatureLabel();
    saveAssistantPrefs();
  });

  UI.maxTokensInput?.addEventListener('change', (event) => {
    assistantPrefs.maxTokens = Number(event.target.value || DEFAULT_PREFS.maxTokens);
    saveAssistantPrefs();
  });

  UI.reasoningSelect?.addEventListener('change', (event) => {
    assistantPrefs.reasoningEffort = String(event.target.value || '');
    saveAssistantPrefs();
  });

  UI.verbositySelect?.addEventListener('change', (event) => {
    assistantPrefs.textVerbosity = String(event.target.value || 'medium');
    saveAssistantPrefs();
  });

  UI.imageModelSelect?.addEventListener('change', (event) => {
    assistantPrefs.imageModel = String(event.target.value || DEFAULT_IMAGE_MODEL);
    saveAssistantPrefs();
  });

  UI.imageAspectSelect?.addEventListener('change', (event) => {
    assistantPrefs.imageAspect = String(event.target.value || '1:1');
    saveAssistantPrefs();
  });

  UI.ttsVoiceSelect?.addEventListener('change', (event) => {
    assistantPrefs.ttsVoice = String(event.target.value || 'alloy');
    saveAssistantPrefs();
  });

  document.getElementById('btnNewChat')?.addEventListener('click', () => resetChat({ noticeKey: 'notice_new_chat' }));
  document.getElementById('btnUploadFiles')?.addEventListener('click', () => UI.fileInput?.click());
  document.getElementById('btnViewUploads')?.addEventListener('click', () => {
    window.location.href = '/admin/media';
  });
  UI.attachVisionBtn?.addEventListener('click', () => UI.visionInput?.click());
  UI.fileInput?.addEventListener('change', (event) => {
    addContextAttachments(event.target?.files || []);
    event.target.value = '';
    updateAttachmentChip();
  });
  UI.visionInput?.addEventListener('change', (event) => {
    addContextAttachments(event.target?.files || []);
    event.target.value = '';
    updateAttachmentChip();
  });
  UI.imageUrlInput?.addEventListener('input', updateAttachmentChip);
  UI.generateImageBtn?.addEventListener('click', async () => {
    try {
      await ensurePuterReady({ interactive: true, t: (key) => t(key), openPopup: getOpenSignInPopup() });
      await handleImageGeneration(String(UI.input?.value || '').trim());
      if (UI.input) UI.input.value = '';
      setStatus('status_ready');
    } catch (error) {
      appendAssistant(UI.messages, String(error?.message || t('status_error')), { animate: true });
      setStatus('status_error');
    }
  });
  UI.speakLastReplyBtn?.addEventListener('click', async () => {
    try {
      await ensurePuterReady({ interactive: true, t: (key) => t(key), openPopup: getOpenSignInPopup() });
      await speakLastReply();
    } catch (error) {
      appendAssistant(UI.messages, String(error?.message || 'Text-to-speech failed'), { animate: true });
    }
  });
}

async function handleUserMessage() {
  const text = String(UI.input?.value || '').trim();
  const imageUrl = String(UI.imageUrlInput?.value || '').trim();
  const hasFileContext = hasUploadedContextFiles();
  const hasContextInput = hasContextInputs();
  const shouldUseVisionTransport = Boolean(imageUrl) || (assistantPrefs.mode === 'vision' && hasFileContext);
  if (!text && !hasContextInput) return;

  const { history, expired } = historyStore.load();
  if (expired) {
    chatHistory = [];
    appendAssistant(UI.messages, t('notice_chat_reset'));
  } else {
    chatHistory = history;
  }

  if (text.startsWith('/image ')) {
    UI.input.value = '';
    try {
      await ensurePuterReady({ interactive: true, t: (key) => t(key), openPopup: getOpenSignInPopup() });
      await handleImageGeneration(text.slice(7));
      setStatus('status_ready');
    } catch (error) {
      await appendAssistant(UI.messages, String(error?.message || t('status_error')), { animate: true });
      setStatus('status_error');
    }
    return;
  }

  UI.input.value = '';
  const ts = new Date().toISOString();
  const attachmentSummary = buildAttachmentSummary();
  const userMessageText = hasContextInput
    ? `${text || 'Use these uploaded files as context.'}${attachmentSummary ? `\n${attachmentSummary}` : ''}`
    : text;

  chatHistory.push({ role: 'user', text: userMessageText, ts });
  historyStore.save(chatHistory);
  appendMessage(UI.messages, 'user', userMessageText, { ts });

  const staticReply = !hasContextInput ? getStaticReply(text, currentLang) : null;
  if (staticReply && assistantPrefs.mode === 'assistant') {
    const staticNode = await appendAssistant(UI.messages, staticReply, { animate: true });
    attachSpeechAction(staticNode, staticReply);
    lastAssistantReplyText = staticReply;
    historyStore.save(chatHistory);
    return;
  }

  setTyping(true);
  setStatus('status_connecting');
  const started = performance.now();
  setReasoningBanner('');

  try {
    await ensurePuterReady({ interactive: true, t: (key) => t(key), openPopup: getOpenSignInPopup() });

    if (shouldUseVisionTransport) {
      const result = await runVisionConversation(text);
      const responseMs = result.responseMs || Math.max(0, Math.round(performance.now() - started));
      if (!shouldUseStream()) {
        const msg = await appendAssistant(UI.messages, result.text, { animate: true, responseMs });
        attachSpeechAction(msg, result.text);
      }
      chatHistory.push({ role: 'assistant', text: result.text, ts: new Date().toISOString(), responseMs });
      historyStore.save(chatHistory);
      lastAssistantReplyText = result.text;
      await cleanupUploadedContextAttachments();
      clearAttachmentState({ clearUrl: true });
      setStatus('status_ready');
      return;
    }

    const messages = await buildMessages(text, {
      defaultUserText: hasContextInput ? 'Use the uploaded files as context for this request.' : ''
    });

    if (shouldUseStream()) {
      const result = await runStreamedConversation(messages);
      chatHistory.push({ role: 'assistant', text: result.text, ts: new Date().toISOString(), responseMs: result.responseMs });
      historyStore.save(chatHistory);
      lastAssistantReplyText = result.text;
      await cleanupUploadedContextAttachments();
      clearAttachmentState({ clearUrl: true });
      setStatus('status_ready');
      return;
    }

    const tools = [];
    if (shouldUseWebSearch()) {
      tools.push({ type: 'web_search' });
    }
    if (shouldUseAdminTools()) {
      tools.push({ type: 'function', function: buildAdminToolSchema() });
    }

    const response = await getChatClient(getSelectedChatModel()).chatWithFallback(
      messages,
      buildRuntimeOptions({ includeTools: tools.length > 0, tools })
    );

    if (shouldUseAdminTools()) {
      let handled = false;
      const toolCalls = response?.message?.tool_calls || [];
      for (const call of toolCalls) {
        handled = handleAdminAction(call) || handled;
      }
      if (handled) {
        setStatus('status_ready');
        return;
      }
    }

    const aiText = extractResponseText(response) || t('status_error');
    const responseMs = Math.max(0, Math.round(performance.now() - started));
    chatHistory.push({ role: 'assistant', text: aiText, ts: new Date().toISOString(), responseMs });
    historyStore.save(chatHistory);
    const msg = await appendAssistant(UI.messages, aiText, { animate: true, responseMs });
    attachSpeechAction(msg, aiText);
    lastAssistantReplyText = aiText;
    await cleanupUploadedContextAttachments();
    clearAttachmentState({ clearUrl: true });
    setStatus('status_ready');
  } catch (err) {
    const msg = String(err?.message || t('status_error'));
    const errorNode = await appendAssistant(UI.messages, msg, { animate: true });
    attachSpeechAction(errorNode, msg);
    lastAssistantReplyText = msg;
    setStatus(msg, { raw: true });
  } finally {
    setTyping(false);
    setReasoningBanner('');
    historyStore.updateActivity();
  }
}

function buildAdminToolSchema() {
  return {
    name: 'admin_action',
    description: 'Run a Brox admin panel action such as opening a prefilled create/edit form, deleting content, reading analytics, opening admin pages, or starting a new chat.',
    parameters: {
      type: 'object',
      properties: {
        action: {
          type: 'string',
          enum: [
            'create_post', 'edit_post', 'delete_post',
            'create_service', 'edit_service', 'delete_service',
            'create_page', 'edit_page', 'delete_page',
            'create_category', 'edit_category', 'delete_category',
            'create_tag', 'edit_tag', 'delete_tag',
            'create_mobile', 'edit_mobile', 'delete_mobile',
            'manage_service_applications', 'manage_payments', 'create_user', 'delete_user', 'create_role', 'delete_role',
            'send_notification', 'get_analytics_summary', 'get_visitor_stats', 'get_top_content',
            'upload_file', 'view_uploads', 'manage_chats', 'start_new_chat',
            'view_error_logs', 'view_all_logs', 'view_log_stats', 'get_recent_errors'
          ]
        },
        params: {
          type: 'object',
          additionalProperties: true,
          description: 'Parameters for the action; include fields to prefill forms.'
        }
      },
      required: ['action']
    }
  };
}

function applyAssistantPrefillFromQuery() {
  const url = new URL(window.location.href);
  if (url.searchParams.get('assistant_prefill') !== '1') return;
  url.searchParams.delete('assistant_prefill');
  url.searchParams.forEach((value, key) => {
    const field = document.querySelector(`[name="${CSS.escape(key)}"]`);
    if (!field) return;
    if (field.type === 'checkbox') {
      field.checked = value === '1' || value === 'true' || value === 'on';
      return;
    }
    field.value = value;
  });
}

async function enhanceContentWithAI({ content, prompt }) {
  try {
    await ensurePuterReady({ interactive: true, t: (key) => t(key), openPopup: getOpenSignInPopup() });
    
    const enhancementMessages = [
      {
        role: 'system',
        content: prompt
      },
      {
        role: 'user',
        content: 'Please enhance this content according to the instructions provided.'
      }
    ];

    const response = await getChatClient().chatWithFallback(enhancementMessages, {
      includeTools: false
    });

    const enhancedText = extractResponseText(response) || '';
    
    if (!enhancedText || enhancedText.trim().length === 0) {
      throw new Error('AI returned empty response');
    }

    // Clean up the response - remove any markdown code fences if present
    let cleanedText = enhancedText.trim();
    if (cleanedText.startsWith('```html')) {
      cleanedText = cleanedText.slice(7); // Remove ```html
    } else if (cleanedText.startsWith('```')) {
      cleanedText = cleanedText.slice(3); // Remove ```
    }
    if (cleanedText.endsWith('```')) {
      cleanedText = cleanedText.slice(0, -3); // Remove trailing ```
    }
    cleanedText = cleanedText.trim();

    return {
      enhanced: cleanedText,
      success: true
    };
  } catch (error) {
    console.error('Content enhancement error:', error);
    return {
      error: error.message || 'Enhancement failed',
      success: false
    };
  }
}

async function handleViewErrorLogs(params = {}) {
  try {
    const lines = params?.lines || 30;
    const response = await fetch(`/api/admin/logs/read?file=errors.log&lines=${lines}`, {
      credentials: 'include'
    });

    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();

    if (data.error) {
      appendAssistant(UI.messages, `❌ Error reading logs: ${data.error}`);
      return;
    }

    const entries = data.entries || [];
    if (entries.length === 0) {
      appendAssistant(UI.messages, '✅ No errors found in the log file. Everything looks good!');
      return;
    }

    let logText = `📋 **Error Log** (${data.file})\nSize: ${data.file_size_display} | Last updated: ${data.last_modified}\n\n`;
    logText += `Found **${entries.length}** recent error(s):\n\n`;

    entries.slice(0, 5).forEach((entry, idx) => {
      logText += `**#${idx + 1}** [${entry.severity}] ${entry.timestamp}\n`;
      logText += `${entry.message}\n`;
      if (entry.context) {
        logText += `\`\`\`\n${entry.context.substring(0, 200)}...\n\`\`\`\n`;
      }
      logText += '\n';
    });

    if (entries.length > 5) {
      logText += `... and ${entries.length - 5} more error(s).\n`;
    }

    appendAssistant(UI.messages, logText);
  } catch (error) {
    appendAssistant(UI.messages, `❌ Failed to fetch error logs: ${error.message}`);
  }
}

async function handleGetRecentErrors(params = {}) {
  try {
    const limit = params?.limit || 10;
    const response = await fetch(`/api/admin/logs/errors?limit=${limit}`, {
      credentials: 'include'
    });

    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();

    if (!data.errors || data.errors.length === 0) {
      appendAssistant(UI.messages, '✅ No recent errors. The application is running smoothly.');
      return;
    }

    let logText = `⚠️ **Recent Errors** (Found ${data.count})\n\n`;
    data.errors.slice(0, 5).forEach((err, idx) => {
      logText += `**${idx + 1}. [${err.severity}] ${err.timestamp}**\n`;
      logText += `${err.message}\n\n`;
    });

    if (data.count > 5) {
      logText += `... and ${data.count - 5} more.\n`;
    }

    appendAssistant(UI.messages, logText);
  } catch (error) {
    console.error('Error fetching recent errors:', error);
    appendAssistant(UI.messages, `❌ Could not fetch recent errors: ${error.message}`);
  }
}

async function handleViewAllLogs(params = {}) {
  try {
    const response = await fetch('/api/admin/logs', {
      credentials: 'include'
    });

    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();

    const logs = data.logs || [];
    if (logs.length === 0) {
      appendAssistant(UI.messages, 'No log files found in the storage directory.');
      return;
    }

    let logText = `📂 **Available Log Files** (${logs.length})\n\n`;
    logs.forEach((log, idx) => {
      logText += `**${idx + 1}. ${log.name}**\n`;
      logText += `   Size: ${log.size_display} | Lines: ${log.lines} | Modified: ${log.modified}\n\n`;
    });

    appendAssistant(UI.messages, logText);
  } catch (error) {
    appendAssistant(UI.messages, `❌ Failed to list logs: ${error.message}`);
  }
}

async function handleViewLogStats() {
  try {
    const response = await fetch('/api/admin/logs/stats', {
      credentials: 'include'
    });

    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();
    const stats = data.stats || {};

    let logText = `📊 **Log Statistics**\n\n`;
    logText += `Total Size: **${stats.total_size_display || '0 B'}**\n`;
    logText += `Total Lines: **${stats.total_lines || 0}**\n`;
    logText += `Error Count: **${stats.error_count || 0}**\n`;
    logText += `Warnings: **${stats.warning_count || 0}**\n\n`;

    if (stats.files) {
      logText += `**Individual Files:**\n`;
      Object.entries(stats.files).forEach(([filename, fileStats]) => {
        logText += `- ${filename}: ${fileStats.size_display} (${fileStats.lines} lines)\n`;
      });
    }

    appendAssistant(UI.messages, logText);
  } catch (error) {
    appendAssistant(UI.messages, `❌ Failed to get log statistics: ${error.message}`);
  }
}

function init() {
  const { history, expired } = historyStore.load();
  chatHistory = history;
  historyExpired = expired;
  currentLang = getLanguage();
  loadAssistantPrefs();
  applyLanguage();
  applyAssistantPrefsToUi();
  renderHistory();
  if (historyExpired) appendAssistant(UI.messages, t('notice_session_expired'));
  bindEvents();
  initQuickActionBar();
  updateAttachmentChip();
  applyAssistantPrefillFromQuery();
  setStatus('status_ready_to_connect');
  populateModelOptions();
  
  // Expose enhancement function globally for RTE sidebar
  window.enhanceContentWithAI = enhanceContentWithAI;
  
  // Initialize log monitoring
  logMonitor.onLogUpdate((eventType, data) => {
    if (eventType === 'errors' && data && data.length > 0) {
      // Alert admin if new errors detected
      const errorCount = data.length;
      let alertMsg = `🚨 **${errorCount} new error(s) detected!**\n\n`;
      
      data.slice(0, 3).forEach((err, idx) => {
        alertMsg += `**Error ${idx + 1}:** [${err.severity}] ${err.message}\n`;
      });
      
      if (errorCount > 3) {
        alertMsg += `\n... and ${errorCount - 3} more errors.\n`;
      }
      
      alertMsg += `\nType "show errors" to view all error logs.`;
      
      appendAssistant(UI.messages, alertMsg, { animate: true });
    }
  });
  
  // Start monitoring logs for new errors
  logMonitor.startPolling();
}

init();
