import { scrollToBottom } from '../../core/dom.js';
import { appendAssistant, appendMessage, attachAssistantTools, buildStaticReplyMatcher, formatBody, parseResponseConfig, typeMessage } from '../../core/render.js';
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
  statusIndicator: document.getElementById('adminAssistantStatusIndicator'),
  status: document.getElementById('adminAssistantStatus'),
  publicModeBadge: document.getElementById('adminAssistantPublicModeBadge'),
  signInBtn: document.getElementById('btnPuterSignIn'),
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
      model: 'accounts/fireworks/models/deepseek-v3p1',
      messages: messages,
      stream: options.stream || false,
      ...options
    })
  });
  if (!response.ok) throw new Error('Fireworks API error');
  return await response.json();
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
  provider: 'puter-js',
  model: 'gemini-2.0-flash',
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

// OpenRouter AI API call function
async function callOpenRouterAI(messages, options = {}) {
  const apiKey = String(window.OPENROUTER_API_KEY || '').trim();
  if (!apiKey || apiKey === '') {
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
      stream: options.stream || true,
      ...options
    })
  });

  if (!response.ok) {
    let errText = '';
    try {
      const text = await response.text();
      // Try to extract a human-friendly error message from JSON responses.
      try {
        const json = JSON.parse(text);
        if (json?.error?.message) {
          errText = json.error.message;
        } else {
          errText = text;
        }
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
    // If parsing fails, include the raw response text for easier debugging.
    let rawText = '';
    try {
      rawText = await response.text();
    } catch (e) {
      // ignore
    }
    throw new Error(`OpenRouter API response parse error: ${parseErr.message}${rawText ? ` - ${rawText}` : ''}`);
  }
}

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

window.PUTER_PROXY_PUBLIC_ONLY = true;
window.PUTER_DISABLED = false; // Enable Puter.js as a fallback when backend AI is unavailable

function isPublicMode() {
  return Boolean(window.PUTER_PROXY_PUBLIC_ONLY);
}

function updatePublicModeBadge() {
  if (!UI.publicModeBadge) return;
  if (isPublicMode()) {
    UI.publicModeBadge.classList.remove('d-none');
    if (UI.signInBtn) UI.signInBtn.classList.add('d-none');
  } else {
    UI.publicModeBadge.classList.add('d-none');
    if (UI.signInBtn) UI.signInBtn.classList.remove('d-none');
  }
}

if (UI.signInBtn) {
  UI.signInBtn.addEventListener('click', async () => {
    try {
      await ensurePuterReady({ interactive: true, allowAuth: true, t: (key) => (typeof t === 'function' ? t(key) : key), openPopup: getOpenSignInPopup() });
      if (UI.signInBtn) UI.signInBtn.classList.add('d-none');
      if (UI.publicModeBadge) UI.publicModeBadge.classList.add('d-none');
      setStatus('status_ready');
    } catch (err) {
      setStatus(String(err?.message || 'Sign-in failed'), { raw: true });
    }
  });
}

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
    default_greeting: 'আমি আপনার BroxLab অ্যাডমিন সহকারী। পোস্ট, পেজ, সার্ভিস, মিডিয়া, অ্যানালিটিক্স বা অন্য অ্যাডমিন কাজ নিয়ে জিজ্ঞেস করুন। এই অ্যাসিস্ট্যান্ট public mode-এ Puter sign-in ছাড়াই কাজ করে।',
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
    default_greeting: 'I am your Brox admin assistant. Ask about posts, pages, services, media, analytics, or other admin work. This assistant runs in public mode without requiring Puter sign-in.',
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
    if (UI.statusIndicator) UI.statusIndicator.classList.remove('ready');
    return;
  }

  const text = t(textKey);
  UI.status.textContent = text;

  if (!UI.statusIndicator) return;
  const readyKeys = ['status_ready', 'status_ready_to_connect'];
  const isReady = readyKeys.includes(textKey);
  UI.statusIndicator.classList.toggle('ready', isReady);
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

  // helper to add a list if not already present
  const addModels = (list) => {
    for (const m of list) {
      if (!options.some((item) => item.value === m.value)) {
        options.push(m);
      }
    }
  };

  // try provider-specific discovery first (based on enabled providers and their API keys)
  try {
    const providerList = Array.isArray(assistantPrefs.providers) ? assistantPrefs.providers : [];

    for (const prov of providerList) {
      const providerName = prov.provider_name;
      const apiKey = prov.api_key;

      // Always include OpenRouter Free router as a selectable option.
      if (providerName === 'openrouter') {
        addModels([{ value: 'openrouter/free', label: 'OpenRouter Free (router)' }]);
      }

      // Add any configured supported models if present.
      if (Array.isArray(prov.models) && prov.models.length) {
        addModels(prov.models.map((mid) => ({ value: mid, label: mid })));
      }

      // Provider-specific remote discovery
      if (providerName === 'fireworks' && apiKey) {
        const resp = await fetch('https://api.fireworks.ai/inference/v1/models', {
          headers: { Authorization: `Bearer ${apiKey}` }
        });
        if (resp.ok) {
          const data = await resp.json();
          const fw = Array.isArray(data.models) ? data.models.map(m => ({ value: m.id || m.model || m.name, label: m.name || m.id || m.model })) : [];
          addModels(fw.filter(Boolean));
        }
      } else if (providerName === 'openrouter' && apiKey) {
        const resp = await fetch('https://openrouter.ai/api/v1/models', {
          headers: { Authorization: `Bearer ${apiKey}` }
        });
        if (resp.ok) {
          const data = await resp.json();
          const modelList = Array.isArray(data.data) ? data.data : Array.isArray(data.models) ? data.models : [];
          const orModels = modelList.map((m) => {
            const id = String(m?.id || m?.canonical_slug || m?.name || '').trim();
            if (!id) return null;
            const label = m?.name ? `${m.name} (${id})` : id;
            const option = { value: id, label };
            if (m?.description) {
              option.title = m.description;
            }
            return option;
          }).filter(Boolean);
          addModels(orModels);
        }
      }
    }
  } catch (e) {
    console.log('provider model discovery failed', e);
  }

  // always try Puter list (covers openai and other backends)
  try {
    await ensurePuterReady({ interactive: false, t: (key) => t(key) });
    const puter = await getPuterClient();
    const models = await puter.ai.listModels(assistantPrefs.provider || OPENAI_PROVIDER);
    const pmodels = Array.isArray(models)
      ? models.map((model) => {
        const id = String(model?.id || model?.model || model?.name || '').trim();
        if (!id) return null;
        return { value: id, label: id };
      }).filter(Boolean)
      : [];
    addModels(pmodels);
  } catch (e) {
    // ignore
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

async function applyResponseConfig(message, rawText) {
  const { config, content } = parseResponseConfig(rawText || '');
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
      await ensurePuterReady({ interactive: false, t: (key) => t(key) });
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

  attachAssistantTools(msg, {
    text: '',
    onRun: () => {
      const currentText = msg.querySelector('.message-content')?.textContent || '';
      UI.input.value = currentText;
      handleUserMessage();
    }
  });

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
    async finalize({ text, responseMs, responseConfig }) {
      const finalText = await applyResponseConfig(msg, text, { responseConfig });
      lastAssistantReplyText = finalText;
      meta.textContent = responseMs < 1000 ? `${responseMs}ms` : `${(responseMs / 1000).toFixed(1)}s`;
      attachSpeechAction(msg, finalText);
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

function setAssistantInteractivity(enabled) {
  if (!UI.wrapper) return;

  if (!enabled && UI.wrapper.contains(document.activeElement) && document.activeElement instanceof HTMLElement) {
    document.activeElement.blur();
  }

  UI.wrapper.toggleAttribute('inert', !enabled);

  if (!enabled) {
    UI.chat?.classList.add('hidden');
    UI.chat?.classList.add('d-none');
  }
}

function syncAssistantWithModalState() {
  const hasVisibleModal = Array.from(document.querySelectorAll('.modal.show'))
    .some((modal) => !UI.wrapper?.contains(modal));
  setAssistantInteractivity(!hasVisibleModal);
}

function bindEvents() {
  UI.langBnBtn?.addEventListener('click', () => { currentLang = 'bn'; setLanguage('bn'); applyLanguage(); renderHistory(); });
  UI.langEnBtn?.addEventListener('click', () => { currentLang = 'en'; setLanguage('en'); applyLanguage(); renderHistory(); });

  UI.toggleBtn?.addEventListener('click', () => {
    const isClosed = UI.chat?.classList.contains('hidden') || UI.chat?.classList.contains('d-none');
    if (isClosed) {
      // When opening the assistant, default to chat mode and hide advanced controls.
      syncModePreset('assistant', { preserveModel: true });
      UI.advancedPanel?.classList.add('d-none');
      UI.input?.focus?.();
    }
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
      await ensurePuterReady({ interactive: false, t: (key) => t(key) });
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
      await ensurePuterReady({ interactive: false, t: (key) => t(key) });
      await speakLastReply();
    } catch (error) {
      appendAssistant(UI.messages, String(error?.message || 'Text-to-speech failed'), { animate: true });
    }
  });

  document.addEventListener('show.bs.modal', (event) => {
    if (UI.wrapper?.contains(event.target)) return;
    setAssistantInteractivity(false);
  }, true);

  document.addEventListener('hidden.bs.modal', (event) => {
    if (UI.wrapper?.contains(event.target)) return;
    syncAssistantWithModalState();
  }, true);
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
      await ensurePuterReady({ interactive: false, t: (key) => t(key) });
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
  setStatus('status_thinking');
  const started = performance.now();
  setReasoningBanner('');

  try {
    // Check if we should use backend AI (for simple chat without advanced features)
    const useBackendAI = !shouldUseAdminTools() && !shouldUseWebSearch() && !hasContextInput && assistantPrefs.mode !== 'vision';

    let backendError = null;

    // Removed backend AI API usage; always use Puter.js for AI calls

    await ensurePuterReady({ interactive: false, t: (key) => t(key) });

    // Try settings provider (Fireworks/OpenRouter) if API key is available
    const hasFireworksKey = Boolean(window.FIREWORKS_API_KEY && window.FIREWORKS_API_KEY !== '');
    const hasOpenRouterKey = Boolean(window.OPENROUTER_API_KEY && window.OPENROUTER_API_KEY !== '');
    const isProviderConfigured = (assistantPrefs.provider === 'fireworks' && hasFireworksKey) ||
      (assistantPrefs.provider === 'openrouter' && hasOpenRouterKey);

    if (isProviderConfigured) {
      try {
        // Try settings provider
        const messages = [
          {
            role: 'system',
            content: 'You are Brox Assistant. Always use typing animation for responses. Provide concise, helpful answers. At the end of your response, add suggestions in the format [SUGGESTION: option1, option2] for follow-up actions.'
          },
          ...chatHistory.map((row) => ({ role: row.role, content: row.text })),
          { role: 'user', content: userMessageText }
        ];

        let model, response;
        if (assistantPrefs.provider === 'openrouter') {
          // When using OpenRouter in public/admin mode, prefer the free router model
          // when no explicit model is configured.
          model = (assistantPrefs.model && assistantPrefs.model.includes('/'))
            ? assistantPrefs.model
            : 'openrouter/free';
          response = await callOpenRouterAI(messages, { stream: true, model });
        } else {
          model = assistantPrefs.model || 'accounts/fireworks/models/deepseek-v3p1';
          response = await callFireworksAI(messages, { stream: true, model });
        }

        const aiText = extractResponseText(response) || t('status_error');

        // Parse suggestions
        const { text: cleanText, suggestions } = parseSuggestionsFromText(aiText);

        const responseMs = Math.max(0, Math.round(performance.now() - started));
        const usedModel = model.replace('accounts/fireworks/models/', '').replace('openai/', '');
        const msg = await appendAssistant(UI.messages, cleanText, {
          animate: true,
          responseMs,
          config: { suggestions },
          model: usedModel,
          tools: true,
          onRun: (text) => {
            UI.input.value = text;
            handleUserMessage();
          }
        });
        const finalText = await applyResponseConfig(msg, cleanText);
        attachSpeechAction(msg, finalText);
        lastAssistantReplyText = finalText;
        chatHistory.push({ role: 'assistant', text: finalText, ts: new Date().toISOString(), responseMs });
        historyStore.save(chatHistory);
        await cleanupUploadedContextAttachments();
        clearAttachmentState({ clearUrl: true });
        setStatus('status_ready');
        return;
      } catch (providerErr) {
        console.log('Settings provider failed, falling back to Puter:', providerErr.message);
        // Fall through to Puter.js fallback
      }
    } else {
      console.log('No API key configured for ' + assistantPrefs.provider + ', using Puter.js directly');
    }

    // Fallback to Puter.js
    if (shouldUseVisionTransport) {
      const result = await runVisionConversation(text);
      const responseMs = result.responseMs || Math.max(0, Math.round(performance.now() - started));
      if (!shouldUseStream()) {
        const msg = await appendAssistant(UI.messages, result.text, { animate: true, responseMs, model: 'Puter AI' });
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
    const msg = await appendAssistant(UI.messages, aiText, {
      animate: true,
      responseMs,
      model: 'Puter AI',
      tools: true,
      onRun: (text) => {
        UI.input.value = text;
        handleUserMessage();
      }
    });
    const finalText = await applyResponseConfig(msg, aiText);
    attachSpeechAction(msg, finalText);
    lastAssistantReplyText = finalText;
    await cleanupUploadedContextAttachments();
    clearAttachmentState({ clearUrl: true });
    setStatus('status_ready');
  } catch (err) {
    const msg = String(err?.message ?? err ?? t('status_error'));
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
    await ensurePuterReady({ interactive: false, t: (key) => t(key), openPopup: getOpenSignInPopup() });

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

async function loadAssistantPrefs() {
  try {
    const response = await fetch('/api/ai-settings/frontend');
    if (response.ok) {
      const data = await response.json();
      assistantPrefs.provider = data.provider;
      assistantPrefs.model = data.model;
      assistantPrefs.providers = Array.isArray(data.providers) ? data.providers : [];
      localStorage.setItem('brox.assistant.prefs', JSON.stringify(assistantPrefs));

      // Expose provider API keys globally for use in client-side calls.
      (assistantPrefs.providers || []).forEach((p) => {
        if (!p.api_key) return;
        const keyName = p.provider_name.toUpperCase();
        window[`${keyName}_API_KEY`] = p.api_key;
      });
    }
  } catch (err) {
    console.log('Failed to load assistant prefs from backend:', err);
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
  updatePublicModeBadge();
  renderHistory();
  if (historyExpired) appendAssistant(UI.messages, t('notice_session_expired'));
  bindEvents();
  initQuickActionBar();
  updateAttachmentChip();
  applyAssistantPrefillFromQuery();
  syncAssistantWithModalState();
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
