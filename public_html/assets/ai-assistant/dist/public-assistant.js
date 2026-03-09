// public_html/assets/ai-assistant/core/styles.js
var LOADED_STYLE_IDS = /* @__PURE__ */ new Set();
function ensureAssistantStyles(styleUrl, styleId = "bb-assistant-ui-css") {
  const href = String(styleUrl || "").trim();
  if (!href) return;
  if (LOADED_STYLE_IDS.has(styleId) || document.getElementById(styleId)) {
    LOADED_STYLE_IDS.add(styleId);
    return;
  }
  const link = document.createElement("link");
  link.id = styleId;
  link.rel = "stylesheet";
  link.href = href;
  document.head.appendChild(link);
  LOADED_STYLE_IDS.add(styleId);
}

// public_html/assets/ai-assistant/core/dom.js
function scrollToBottom(container) {
  if (!container) return;
  container.scrollTop = container.scrollHeight;
}

// public_html/assets/ai-assistant/core/render.js
var TYPEWRITER_CHUNK_DELAY_MS = 16;
var TYPEWRITER_MAX_STEPS = 90;
function escapeHtml(value) {
  return String(value ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
}
function linkify(text) {
  const escaped = escapeHtml(text);
  return escaped.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>').replace(/\n/g, "<br>");
}
function formatBody(text, trustedHtml = false) {
  return trustedHtml ? String(text ?? "") : linkify(String(text ?? ""));
}
function formatMeta({ role, ts, responseMs, lang = "en", model }) {
  const parts = [];
  const locale = lang === "bn" ? "bn-BD" : "en-US";
  if (ts) {
    const dt = new Date(ts);
    if (!Number.isNaN(dt.getTime())) {
      parts.push(new Intl.DateTimeFormat(locale, { hour: "2-digit", minute: "2-digit" }).format(dt));
    }
  }
  if (model) {
    parts.push(`\u{1F916} ${model}`);
  }
  if (role === "assistant" && Number.isFinite(responseMs)) {
    const duration = responseMs < 1e3 ? `${responseMs}ms` : `${(responseMs / 1e3).toFixed(1)}s`;
    parts.push(duration);
  }
  return parts.join(" \u2022 ");
}
async function animateBody(node, text, { trustedHtml = false, append = false } = {}) {
  if (!node) return;
  const normalized = String(text ?? "");
  if (!normalized) {
    node.innerHTML = "";
    return;
  }
  if (trustedHtml || window.matchMedia?.("(prefers-reduced-motion: reduce)").matches) {
    node.innerHTML = formatBody(append ? (node.textContent || "") + normalized : normalized, trustedHtml);
    scrollToBottom(node.parentElement);
    return;
  }
  const base = append ? String(node.textContent || "") : "";
  const full = base + normalized;
  const start = base.length;
  const remaining = full.slice(start);
  const chunkSize = Math.max(1, Math.ceil(full.length / TYPEWRITER_MAX_STEPS));
  for (let i = 0; i < remaining.length; i += chunkSize) {
    node.textContent = full.slice(0, start + i + chunkSize);
    scrollToBottom(node.parentElement?.parentElement);
    await new Promise((res) => window.setTimeout(res, TYPEWRITER_CHUNK_DELAY_MS));
  }
  node.innerHTML = formatBody(full, trustedHtml);
  scrollToBottom(node.parentElement?.parentElement);
}
function appendMessage(container, role, text, { ts = (/* @__PURE__ */ new Date()).toISOString(), responseMs = null, trustedHtml = false, model } = {}) {
  if (!container) return null;
  const msg = document.createElement("div");
  msg.className = `message ${role}`;
  const body = document.createElement("div");
  body.className = "message-content";
  body.innerHTML = formatBody(text, trustedHtml);
  msg.appendChild(body);
  const meta = formatMeta({ role, ts, responseMs, model });
  if (meta) {
    const metaDiv = document.createElement("div");
    metaDiv.className = "message-time";
    metaDiv.textContent = meta;
    msg.appendChild(metaDiv);
  }
  container.appendChild(msg);
  scrollToBottom(container);
  return msg;
}
function typeMessage(node, text, { speed = 30 } = {}) {
  if (!node) return Promise.resolve();
  return new Promise((resolve) => {
    node.innerHTML = "";
    let i = 0;
    const interval = window.setInterval(() => {
      node.innerHTML += escapeHtml(text.charAt(i));
      i += 1;
      if (i >= text.length) {
        window.clearInterval(interval);
        resolve();
      }
    }, speed);
  });
}
function parseResponseConfig(text) {
  const result = { config: null, content: text };
  if (typeof text !== "string") return result;
  const trimmed = text.trimStart();
  if (!trimmed.startsWith("---")) return result;
  const parts = trimmed.split(/\r?\n/);
  let inHeader = false;
  const headerLines = [];
  let i = 0;
  for (; i < parts.length; i += 1) {
    const line = parts[i];
    if (i === 0 && line.trim() === "---") {
      inHeader = true;
      continue;
    }
    if (inHeader && line.trim() === "---") {
      i += 1;
      break;
    }
    if (inHeader) headerLines.push(line);
  }
  if (!inHeader || headerLines.length === 0) return result;
  const config = {};
  let currentKey = null;
  for (const line of headerLines) {
    const trimmedLine = line.trim();
    if (trimmedLine === "" || trimmedLine.startsWith("#")) continue;
    const match = trimmedLine.match(/^([a-zA-Z0-9_]+):\s*(.*)$/);
    if (match) {
      currentKey = match[1];
      const value = match[2] ?? "";
      if (value === "") {
        config[currentKey] = [];
      } else {
        config[currentKey] = value;
      }
      continue;
    }
    const listMatch = trimmedLine.match(/^[-*]\s+(.*)$/);
    if (listMatch && currentKey) {
      if (!Array.isArray(config[currentKey])) {
        config[currentKey] = [config[currentKey]];
      }
      config[currentKey].push(listMatch[1]);
      continue;
    }
  }
  result.config = config;
  result.content = parts.slice(i).join("\n").trim();
  return result;
}
function attachAssistantTools(message, { text, onRun } = {}) {
  if (!message) return;
  const tools = document.createElement("div");
  tools.className = "assistant-message-tools";
  const copyBtn = document.createElement("button");
  copyBtn.type = "button";
  copyBtn.className = "assistant-message-tool-btn";
  copyBtn.title = "Copy reply";
  copyBtn.textContent = "\u29C9";
  copyBtn.addEventListener("click", async () => {
    try {
      await navigator.clipboard.writeText(text);
      copyBtn.textContent = "\u2714";
      setTimeout(() => {
        copyBtn.textContent = "\u29C9";
      }, 1200);
    } catch {
      const textarea = document.createElement("textarea");
      textarea.value = text;
      textarea.style.position = "fixed";
      textarea.style.opacity = "0";
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand("copy");
      document.body.removeChild(textarea);
    }
  });
  tools.appendChild(copyBtn);
  if (typeof onRun === "function") {
    const runBtn = document.createElement("button");
    runBtn.type = "button";
    runBtn.className = "assistant-message-tool-btn";
    runBtn.title = "Run as new prompt";
    runBtn.textContent = "\u27F3";
    runBtn.addEventListener("click", () => onRun(text));
    tools.appendChild(runBtn);
  }
  const expandBtn = document.createElement("button");
  expandBtn.type = "button";
  expandBtn.className = "assistant-message-tool-btn";
  expandBtn.title = "Toggle expand";
  expandBtn.textContent = "\u2922";
  expandBtn.addEventListener("click", () => {
    message.classList.toggle("assistant-expanded");
  });
  tools.appendChild(expandBtn);
  message.appendChild(tools);
}
async function appendAssistant(container, text, opts = {}) {
  const animate = opts.animate === true;
  const { config } = opts;
  const msg = appendMessage(container, "assistant", text, {
    ts: opts.ts,
    responseMs: opts.responseMs,
    trustedHtml: opts.trustedHtml,
    model: opts.model
  });
  if (msg && config?.suggestions && Array.isArray(config.suggestions)) {
    const chipRow = document.createElement("div");
    chipRow.className = "assistant-suggestions";
    config.suggestions.forEach((suggestion) => {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "assistant-suggestion-btn";
      btn.textContent = suggestion.label || String(suggestion.action || "");
      btn.addEventListener("click", () => {
        if (typeof opts.onSuggestion === "function") {
          opts.onSuggestion(suggestion);
        }
      });
      chipRow.appendChild(btn);
    });
    msg.appendChild(chipRow);
  }
  if (msg && (opts.onRun || opts.tools)) {
    attachAssistantTools(msg, { text, onRun: opts.onRun });
  }
  if (animate && msg) {
    const body = msg.querySelector(".message-content");
    if (opts.animation === "typing_effect") {
      await typeMessage(body, text, { speed: opts.animationSpeed || 30 });
    } else {
      await animateBody(body, text, { trustedHtml: opts.trustedHtml });
    }
  }
  return msg;
}
function buildStaticReplyMatcher(staticReplies) {
  return function getStaticReply2(inputRaw, lang = "en") {
    const input = String(inputRaw || "").trim();
    if (!input) return null;
    const lowered = input.toLowerCase();
    const asksName = /(^|\b)(your name|who are you|what is your name|tell me your name)\b/i.test(input) || input.includes("\u09A4\u09CB\u09AE\u09BE\u09B0 \u09A8\u09BE\u09AE") || input.includes("\u0986\u09AA\u09A8\u09BE\u09B0 \u09A8\u09BE\u09AE") || input.includes("\u09A8\u09BE\u09AE \u0995\u09BF") || input.includes("\u09A8\u09BE\u09AE \u0995\u09C0");
    if (asksName) {
      return staticReplies[lang]?.name || staticReplies.en?.name;
    }
    const asksAbout = lowered.includes("broxlab") || lowered.includes("broxlab.online") || lowered.includes("about yourself") || lowered.includes("about brox") || input.includes("\u09A8\u09BF\u099C\u09C7\u09B0 \u09B8\u09AE\u09CD\u09AA\u09B0\u09CD\u0995\u09C7") || input.includes("\u09AC\u09CD\u09B0\u0995\u09CD\u09B8\u09B2\u09CD\u09AF\u09BE\u09AC");
    if (asksAbout) {
      return staticReplies[lang]?.about || staticReplies.en?.about;
    }
    return null;
  };
}

// public_html/assets/ai-assistant/core/storage.js
function tryStorage(storage) {
  return storage || {
    getItem: () => null,
    setItem: () => {
    },
    removeItem: () => {
    }
  };
}
function createHistoryStore({
  storage = window.sessionStorage,
  chatKey,
  activityKey,
  maxMessages = 40,
  inactivityMs = 30 * 60 * 1e3
}) {
  const store = tryStorage(storage);
  const normalize = (row) => {
    if (!row || typeof row !== "object") return null;
    const role = String(row.role || "").trim().toLowerCase();
    const text = String(row.text || "").trim();
    if (!text || role !== "user" && role !== "assistant") return null;
    const ts = row.ts ? String(row.ts).trim() : null;
    const responseMsRaw = Number(row.responseMs);
    const responseMs = Number.isFinite(responseMsRaw) ? Math.max(0, Math.round(responseMsRaw)) : null;
    return { role, text, ts, responseMs };
  };
  const trim = (history) => {
    if (!Array.isArray(history)) return [];
    return history.length <= maxMessages ? history : history.slice(history.length - maxMessages);
  };
  const load = () => {
    try {
      const tsRaw = store.getItem(activityKey);
      if (tsRaw) {
        const last = parseInt(tsRaw, 10);
        if (!Number.isNaN(last) && Date.now() - last > inactivityMs) {
          store.removeItem(chatKey);
          store.removeItem(activityKey);
          return { history: [], expired: true };
        }
      }
      const raw = store.getItem(chatKey);
      if (!raw) return { history: [], expired: false };
      const parsed = JSON.parse(raw);
      const history = trim(parsed.map(normalize).filter(Boolean));
      return { history, expired: false };
    } catch {
      return { history: [], expired: false };
    }
  };
  const updateActivity = () => {
    try {
      store.setItem(activityKey, Date.now().toString());
    } catch {
    }
  };
  const save = (history) => {
    try {
      store.setItem(chatKey, JSON.stringify(trim(history)));
      updateActivity();
    } catch {
    }
  };
  return { load, save, trim, updateActivity };
}

// public_html/assets/ai-assistant/core/i18n.js
function tryStorage2(storage) {
  return storage || {
    getItem: () => null,
    setItem: () => {
    }
  };
}
function createLanguageState({ storageKey, defaultLang = "bn", storage = window.sessionStorage }) {
  const store = tryStorage2(storage);
  let currentLang2 = defaultLang;
  try {
    const stored = store.getItem(storageKey);
    if (stored === "bn" || stored === "en") {
      currentLang2 = stored;
    }
  } catch {
    currentLang2 = defaultLang;
  }
  const setLanguage2 = (lang) => {
    if (lang !== "bn" && lang !== "en") return;
    currentLang2 = lang;
    try {
      store.setItem(storageKey, lang);
    } catch {
    }
  };
  const getLanguage2 = () => currentLang2;
  return { getLanguage: getLanguage2, setLanguage: setLanguage2 };
}

// public_html/assets/ai-assistant/core/puter.js
var DEFAULT_POPUP = { width: 600, height: 700, timeoutMs: 2 * 60 * 1e3 };
var DEFAULT_PUTER_ESM = "https://cdn.jsdelivr.net/npm/@heyputer/puter.js@2.2.11/src/index.js";
var puterInstance = null;
var puterLoading = null;
var puterRealtimeDisabled = false;
function getPuterCdnUrl() {
  if (typeof window !== "undefined" && typeof window.PUTER_CDN_URL === "string" && window.PUTER_CDN_URL.trim()) {
    return window.PUTER_CDN_URL.trim();
  }
  return DEFAULT_PUTER_ESM;
}
function clearPuterAuthTokenSilently() {
  if (typeof window === "undefined") return;
  try {
    window.localStorage?.removeItem("puter.auth.token");
  } catch {
  }
}
function disablePuterRealtimeSocket(puterClient) {
  if (!puterClient || puterRealtimeDisabled) return;
  const fs = puterClient.fs;
  if (!fs) return;
  try {
    fs.socket?.disconnect?.();
  } catch {
  }
  fs.socket = null;
  fs.initializeSocket = () => {
    fs.socket = null;
  };
  fs.setAPIOrigin = (APIOrigin) => {
    fs.APIOrigin = APIOrigin;
  };
  fs.setAuthToken = (authToken) => {
    fs.authToken = authToken;
  };
  puterRealtimeDisabled = true;
}
async function loadPuter() {
  if (typeof window !== "undefined" && window.PUTER_DISABLED) {
    throw new Error("Puter client disabled");
  }
  if (puterInstance) return puterInstance;
  if (puterLoading) return puterLoading;
  if (typeof window !== "undefined" && window.puter) {
    puterInstance = window.puter;
    disablePuterRealtimeSocket(puterInstance);
    return puterInstance;
  }
  puterLoading = (async () => {
    const src = getPuterCdnUrl();
    const module = await import(src);
    const client = module.puter || module.default?.puter || module.default || module;
    if (!client) {
      throw new Error("Puter client not found");
    }
    if (typeof window !== "undefined") {
      window.puter = client;
    }
    disablePuterRealtimeSocket(client);
    return client;
  })();
  puterInstance = await puterLoading;
  return puterInstance;
}
async function getPuterClient() {
  return loadPuter();
}
function buildError(message) {
  return new Error(message || "Authentication required");
}
function normalizeAuthError(error, t2) {
  const code = String(error?.error || error?.code || "").trim().toLowerCase();
  const message = String(error?.msg || error?.message || "").trim();
  if (code === "popup_blocked") return buildError(t2?.("error_sign_in_popup_blocked") || "Popup blocked");
  if (code === "auth_window_closed") return buildError(t2?.("error_sign_in_popup_closed") || "Popup closed early");
  if (code === "auth_timeout") return buildError(t2?.("error_sign_in_timeout") || "Sign-in timed out");
  if (message) return buildError(message);
  return buildError(t2?.("error_sign_in_required") || "Sign-in required");
}
async function ensurePuterReady({ interactive = false, allowAuth = false, t: t2, openPopup } = {}) {
  if (typeof window !== "undefined" && window.PUTER_DISABLED) {
    throw buildError(t2?.("error_puter_missing") || "Puter client disabled");
  }
  const p = await getPuter();
  if (!p?.ai?.chat) throw buildError(t2?.("error_puter_missing") || "Puter client missing");
  const auth = p.auth;
  if (!auth) return;
  let signedIn = true;
  if (typeof auth.isSignedIn === "function") {
    const state = auth.isSignedIn();
    signedIn = state && typeof state.then === "function" ? await state : Boolean(state);
  }
  if (typeof window !== "undefined" && window.PUTER_PROXY_PUBLIC_ONLY && !allowAuth) {
    try {
      clearPuterAuthTokenSilently();
      if (typeof p.resetAuthToken === "function") {
        p.resetAuthToken();
      } else if (typeof p.setAuthToken === "function") {
        p.setAuthToken(null);
      }
    } catch {
    }
    return;
  }
  if (signedIn && typeof auth.whoami === "function") {
    try {
      await auth.whoami();
    } catch (err) {
      signedIn = false;
    }
  }
  if (!signedIn && interactive) {
    if (openPopup) {
      await openPopup();
    } else if (typeof auth.signIn === "function") {
      await auth.signIn({ attempt_temp_user_creation: true });
    }
    if (typeof auth.isSignedIn === "function") {
      const next = auth.isSignedIn();
      signedIn = next && typeof next.then === "function" ? await next : Boolean(next);
    } else {
      signedIn = true;
    }
  }
  if (!signedIn) throw normalizeAuthError({ code: "auth_required" }, t2);
}
function extractResponseText(response) {
  if (!response) return "";
  if (typeof response === "string") return response;
  if (typeof response.text === "string") return response.text;
  if (typeof response.message?.content === "string") return response.message.content;
  if (Array.isArray(response.message?.content)) {
    return response.message.content.map((part) => typeof part?.text === "string" ? part.text : typeof part === "string" ? part : "").filter(Boolean).join("\n").trim();
  }
  return "";
}
if (typeof window !== "undefined") {
  window.getPuter = getPuterClient;
}

// public_html/assets/ai-assistant/modules/public/app.js
var UI = {
  btn: document.getElementById("publicAssistantBtn"),
  window: document.getElementById("publicAssistantChat"),
  messages: document.getElementById("publicAssistantMessages"),
  input: document.getElementById("publicAssistantInput"),
  sendBtn: document.getElementById("sendToPublicAssistant"),
  loading: document.getElementById("publicAssistantLoading"),
  closeBtn: document.getElementById("closePublicAssistant"),
  statusIndicator: document.getElementById("publicAssistantStatusIndicator"),
  status: document.getElementById("publicAssistantStatusText"),
  langBnBtn: document.getElementById("publicAssistantLangBn"),
  langEnBtn: document.getElementById("publicAssistantLangEn"),
  typingText: document.getElementById("publicAssistantTypingText"),
  footer: document.getElementById("publicAssistantFooter"),
  preChat: document.getElementById("publicAssistantPreChat"),
  btnNewChat: null
};
window.PUTER_PROXY_PUBLIC_ONLY = true;
window.PUTER_DISABLED = false;
var CHAT_STORAGE_KEY = "brox.publicAssistant.chat.v2";
var LAST_ACTIVITY_KEY = "brox.publicAssistant.lastActivity.v2";
var LANGUAGE_KEY = "brox.publicAssistant.language.v2";
var USER_INFO_KEY = "brox.publicAssistant.userInfo.v2";
var MAX_STORED_MESSAGES = 40;
var INACTIVITY_LIMIT_MS = 30 * 60 * 1e3;
var ASSISTANT_SITE_URL = "https://broxlab.online";
var DEFAULT_PREFS = {
  provider: "puter-js",
  model: "gemini-2.0-flash"
};
var assistantPrefs = { ...DEFAULT_PREFS };
async function loadAssistantPrefs() {
  try {
    const response = await fetch("/api/ai-settings/frontend");
    if (response.ok) {
      const data = await response.json();
      assistantPrefs.provider = data.provider;
      assistantPrefs.model = data.model;
      if (data.fireworks_api_key) {
        window.FIREWORKS_API_KEY = data.fireworks_api_key;
      }
      if (data.openrouter_api_key) {
        window.OPENROUTER_API_KEY = data.openrouter_api_key;
      }
    }
  } catch (err) {
    console.log("Failed to load assistant prefs from backend:", err);
  }
}
var I18N = {
  bn: {
    assistant_title: "\u09AC\u09CD\u09B0\u0995\u09CD\u09B8 \u09B8\u09B9\u0995\u09BE\u09B0\u09C0",
    assistant_status: "\u09AC\u09BE\u09B0\u09CD\u09A4\u09BE \u09AA\u09BE\u09A0\u09BE\u09B2\u09C7 \u09B8\u0982\u09AF\u09C1\u0995\u09CD\u09A4 \u09B9\u09AC\u09C7",
    status_thinking: "\u09AD\u09BE\u09AC\u099B\u09C7...",
    default_greeting: "\u09B9\u09CD\u09AF\u09BE\u09B2\u09CB, \u0986\u09AE\u09BF \u0986\u09AA\u09A8\u09BE\u09B0 BroxLab \u09B8\u09B9\u0995\u09BE\u09B0\u09C0\u0964 \u0995\u09C0\u09AD\u09BE\u09AC\u09C7 \u09B8\u09BE\u09B9\u09BE\u09AF\u09CD\u09AF \u0995\u09B0\u09A4\u09C7 \u09AA\u09BE\u09B0\u09BF?",
    close_label: "\u09AC\u09A8\u09CD\u09A7 \u0995\u09B0\u09C1\u09A8",
    chat_input_placeholder: "\u0986\u09AA\u09A8\u09BE\u09B0 \u09AA\u09CD\u09B0\u09B6\u09CD\u09A8 \u09B2\u09BF\u0996\u09C1\u09A8...",
    typing_text: "\u099F\u09BE\u0987\u09AA \u0995\u09B0\u099B\u09C7...",
    name_label: "\u0986\u09AA\u09A8\u09BE\u09B0 \u09A8\u09BE\u09AE",
    name_placeholder: "\u0986\u09AA\u09A8\u09BE\u09B0 \u09A8\u09BE\u09AE \u09B2\u09BF\u0996\u09C1\u09A8",
    email_label: "\u0987\u09AE\u09C7\u0987\u09B2 (\u0990\u099A\u09CD\u099B\u09BF\u0995)",
    email_placeholder: "\u0986\u09AA\u09A8\u09BE\u09B0 \u0987\u09AE\u09C7\u0987\u09B2 \u09B2\u09BF\u0996\u09C1\u09A8 (\u0990\u099A\u09CD\u099B\u09BF\u0995)",
    mobile_label: "\u09AE\u09CB\u09AC\u09BE\u0987\u09B2 \u09A8\u09AE\u09CD\u09AC\u09B0 (\u0990\u099A\u09CD\u099B\u09BF\u0995)",
    mobile_placeholder: "\u0986\u09AA\u09A8\u09BE\u09B0 \u09AE\u09CB\u09AC\u09BE\u0987\u09B2 \u09A8\u09AE\u09CD\u09AC\u09B0 \u09B2\u09BF\u0996\u09C1\u09A8 (\u0990\u099A\u09CD\u099B\u09BF\u0995)",
    topic_label: "\u0986\u09AA\u09A8\u09BE\u09B0 \u099F\u09AA\u09BF\u0995 \u09A8\u09BF\u09B0\u09CD\u09AC\u09BE\u099A\u09A8 \u0995\u09B0\u09C1\u09A8 (\u098F\u0995\u09BE\u09A7\u09BF\u0995)",
    next_btn: "\u09AA\u09B0\u09AC\u09B0\u09CD\u09A4\u09C0",
    start_chat_btn: "\u099A\u09CD\u09AF\u09BE\u099F \u09B6\u09C1\u09B0\u09C1 \u0995\u09B0\u09C1\u09A8",
    new_chat_title: "\u09A8\u09A4\u09C1\u09A8 \u099A\u09CD\u09AF\u09BE\u099F",
    topic_general: "\u09B8\u09BE\u09A7\u09BE\u09B0\u09A3 \u09A4\u09A5\u09CD\u09AF",
    topic_support: "\u09B8\u09BE\u09AA\u09CB\u09B0\u09CD\u099F",
    topic_billing: "\u09AC\u09BF\u09B2\u09BF\u0982",
    topic_feedback: "\u09AE\u09A4\u09BE\u09AE\u09A4",
    alert_name_required: "\u0985\u09A8\u09C1\u0997\u09CD\u09B0\u09B9 \u0995\u09B0\u09C7 \u0986\u09AA\u09A8\u09BE\u09B0 \u09A8\u09BE\u09AE \u09B2\u09BF\u0996\u09C1\u09A8\u0964",
    alert_topic_required: "\u0995\u09AE\u09AA\u0995\u09CD\u09B7\u09C7 \u098F\u0995\u099F\u09BF \u099F\u09AA\u09BF\u0995 \u09A8\u09BF\u09B0\u09CD\u09AC\u09BE\u099A\u09A8 \u0995\u09B0\u09C1\u09A8\u0964",
    session_expired_notice: "\u09AA\u09C2\u09B0\u09CD\u09AC\u09C7\u09B0 \u099A\u09CD\u09AF\u09BE\u099F \u09B8\u09C7\u09B6\u09A8 \u09B6\u09C7\u09B7 \u09B9\u09DF\u09C7\u099B\u09C7\u0964 \u09A8\u09A4\u09C1\u09A8 \u09B8\u09C7\u09B6\u09A8 \u09B6\u09C1\u09B0\u09C1\u0964",
    chat_reset_notice: "\u09A8\u09BF\u09B7\u09CD\u0995\u09CD\u09B0\u09BF\u09DF\u09A4\u09BE\u09B0 \u0995\u09BE\u09B0\u09A3\u09C7 \u099A\u09CD\u09AF\u09BE\u099F \u09B0\u09BF\u09B8\u09C7\u099F \u09B9\u09DF\u09C7\u099B\u09C7\u0964",
    fallback_error: "\u09A6\u09C1\u0983\u0996\u09BF\u09A4, \u098F\u0996\u09A8 \u09B8\u0982\u09AF\u09CB\u0997\u09C7 \u09B8\u09AE\u09B8\u09CD\u09AF\u09BE \u09B9\u099A\u09CD\u099B\u09C7\u0964",
    response_time_label: "\u09B0\u09C7\u09B8\u09AA\u09A8\u09CD\u09B8"
  },
  en: {
    assistant_title: "Brox Assistant",
    assistant_status: "Will connect on first message",
    status_thinking: "Thinking...",
    default_greeting: "Hello, I am your Brox assistant. How can I help you today?",
    close_label: "Close",
    chat_input_placeholder: "Ask your question...",
    typing_text: "Typing...",
    name_label: "Your Name",
    name_placeholder: "Enter your name",
    email_label: "Email (Optional)",
    email_placeholder: "Enter your email (optional)",
    mobile_label: "Mobile Number (Optional)",
    mobile_placeholder: "Enter your mobile number (optional)",
    topic_label: "Select your topics (multiple)",
    next_btn: "Next",
    start_chat_btn: "Start Chat",
    new_chat_title: "New Chat",
    topic_general: "General",
    topic_support: "Support",
    topic_billing: "Billing",
    topic_feedback: "Feedback",
    alert_name_required: "Please enter your name.",
    alert_topic_required: "Please select at least one topic.",
    session_expired_notice: "Previous chat expired. Starting fresh.",
    chat_reset_notice: "Chat reset due to inactivity.",
    fallback_error: "Sorry, having trouble connecting right now.",
    response_time_label: "Response"
  }
};
var STATIC_REPLIES = {
  bn: {
    name: "\u0986\u09AE\u09BF brox \u09AC\u09B2\u099B\u09BF, BroxLab \u09B8\u09B9\u0995\u09BE\u09B0\u09C0 \u09B9\u09BF\u09B8\u09C7\u09AC\u09C7 \u0986\u09AA\u09A8\u09BE\u0995\u09C7 \u09A4\u09A5\u09CD\u09AF \u0993 \u09B8\u09BE\u09AA\u09CB\u09B0\u09CD\u099F\u09C7 \u09B8\u09BE\u09B9\u09BE\u09AF\u09CD\u09AF \u0995\u09B0\u09BF\u0964",
    about: `\u0986\u09AE\u09BF brox \u09AC\u09B2\u099B\u09BF\u0964 BroxLab \u09B9\u09B2\u09CB ${ASSISTANT_SITE_URL} \u09B6\u09BF\u09B0\u09CB\u09A8\u09BE\u09AE\u09C7\u09B0 Bengali-first tech platform, \u09AF\u09C7\u0996\u09BE\u09A8\u09C7 \u0995\u09A8\u099F\u09C7\u09A8\u09CD\u099F, \u09B8\u09C7\u09AC\u09BE \u0993 \u09A1\u09BF\u099C\u09BF\u099F\u09BE\u09B2 \u09A4\u09A5\u09CD\u09AF \u09B8\u09BE\u099C\u09BE\u09A8\u09CB\u09AD\u09BE\u09AC\u09C7 \u09AA\u09CD\u09B0\u0995\u09BE\u09B6 \u0995\u09B0\u09BE \u09B9\u09DF\u0964`
  },
  en: {
    name: "I am Brox, speaking as the BroxLab assistant.",
    about: `I am Brox. BroxLab is the Bengali-first tech platform at ${ASSISTANT_SITE_URL}.`
  }
};
var userInfo = null;
var supportLogged = false;
var chatHistory = [];
var historyExpired = false;
var getStaticReply = buildStaticReplyMatcher(STATIC_REPLIES);
var { getLanguage, setLanguage } = createLanguageState({ storageKey: LANGUAGE_KEY, defaultLang: "bn", storage: window.localStorage });
var currentLang = getLanguage();
var historyStore = createHistoryStore({
  storage: window.localStorage,
  chatKey: CHAT_STORAGE_KEY,
  activityKey: LAST_ACTIVITY_KEY,
  maxMessages: MAX_STORED_MESSAGES,
  inactivityMs: INACTIVITY_LIMIT_MS
});
async function callFireworksAI(messages, options = {}) {
  const apiKey = window.FIREWORKS_API_KEY || "your_api_key_here";
  const response = await fetch("https://api.fireworks.ai/inference/v1/chat/completions", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": `Bearer ${apiKey}`
    },
    body: JSON.stringify({
      model: options.model || "accounts/fireworks/models/deepseek-v3p1",
      messages,
      stream: options.stream || false,
      ...options
    })
  });
  if (!response.ok) throw new Error("Fireworks API error");
  return await response.json();
}
async function callOpenRouterAI(messages, options = {}) {
  const apiKey = window.OPENROUTER_API_KEY || "your_api_key_here";
  const response = await fetch("https://openrouter.ai/api/v1/chat/completions", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": `Bearer ${apiKey}`,
      "HTTP-Referer": window.location.origin,
      // Optional
      "X-OpenRouter-Title": "BroxBhai Assistant"
      // Optional
    },
    body: JSON.stringify({
      model: options.model || "openai/gpt-5.2",
      messages,
      stream: options.stream || false,
      ...options
    })
  });
  if (!response.ok) throw new Error("OpenRouter API error");
  return await response.json();
}
function t(key) {
  return I18N[currentLang]?.[key] || I18N.en[key] || key;
}
function setStatus(text) {
  if (UI.status) UI.status.textContent = text;
  if (!UI.statusIndicator) return;
  const readyTexts = [t("assistant_status"), t("default_greeting")];
  const isReady = readyTexts.includes(text);
  UI.statusIndicator.classList.toggle("ready", isReady);
}
function setTyping(active) {
  UI.loading?.classList.toggle("d-none", !active);
  UI.loading?.classList.toggle("active", active);
}
function normalizeSuggestions(rawSuggestions) {
  if (!rawSuggestions) return [];
  if (Array.isArray(rawSuggestions)) {
    return rawSuggestions.map((item) => {
      if (typeof item === "string") return { label: item, action: item };
      if (item && typeof item === "object") return { label: item.label || item.action || String(item), action: item.action || item.label || String(item) };
      return null;
    }).filter(Boolean);
  }
  if (typeof rawSuggestions === "string") {
    return [{ label: rawSuggestions, action: rawSuggestions }];
  }
  return [];
}
function renderSuggestChips(message, suggestions = []) {
  const chips = normalizeSuggestions(suggestions);
  if (!chips.length) return;
  const existing = message.querySelector(".assistant-suggestions");
  if (existing) existing.remove();
  const chipRow = document.createElement("div");
  chipRow.className = "assistant-suggestions";
  chips.forEach((suggestion) => {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "assistant-suggestion-btn";
    btn.textContent = suggestion.label;
    btn.addEventListener("click", () => {
      UI.input.value = suggestion.action;
      UI.input.focus();
      handleUserMessage();
    });
    chipRow.appendChild(btn);
  });
  chipRow.tabIndex = 0;
  chipRow.addEventListener("keydown", (e) => {
    const buttons = Array.from(chipRow.querySelectorAll("button"));
    if (!buttons.length) return;
    const idx = buttons.indexOf(document.activeElement);
    let nextIdx = -1;
    if (e.key === "ArrowRight" || e.key === "ArrowDown") {
      nextIdx = idx < 0 ? 0 : (idx + 1) % buttons.length;
    } else if (e.key === "ArrowLeft" || e.key === "ArrowUp") {
      nextIdx = idx < 0 ? buttons.length - 1 : (idx - 1 + buttons.length) % buttons.length;
    } else if (e.key === "Home") {
      nextIdx = 0;
    } else if (e.key === "End") {
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
  const { config, content } = responseConfig ? { config: responseConfig, content: rawText } : parseResponseConfig(rawText || "");
  if (!config) return rawText;
  const body = message.querySelector(".message-content");
  if (!body) return rawText;
  const finalText = content || rawText;
  const animation = (config.animation || config.animation_type || "").toLowerCase();
  const speed = parseInt(config.animation_speed || config.animationSpeed, 10) || 30;
  if (animation === "typing_effect") {
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
    btn.classList.toggle("active", active);
    btn.classList.toggle("btn-light", active);
    btn.classList.toggle("btn-outline-light", !active);
  };
  setState(UI.langBnBtn, currentLang === "bn");
  setState(UI.langEnBtn, currentLang === "en");
}
function applyLanguage() {
  const setText = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  };
  const setPlaceholder = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.setAttribute("placeholder", val);
  };
  setText("publicAssistantTitle", t("assistant_title"));
  setText("publicAssistantStatusText", t("assistant_status"));
  setText("publicAssistantTypingText", t("typing_text"));
  setText("introNameLabel", t("name_label"));
  setText("introEmailLabel", t("email_label"));
  setText("introMobileLabel", t("mobile_label"));
  setText("introTopicLabel", t("topic_label"));
  setText("introNext1", t("next_btn"));
  setText("introNext2", t("next_btn"));
  setText("introStartChat", t("start_chat_btn"));
  setPlaceholder("introName", t("name_placeholder"));
  setPlaceholder("introEmail", t("email_placeholder"));
  setPlaceholder("introMobile", t("mobile_placeholder"));
  setPlaceholder("publicAssistantInput", t("chat_input_placeholder"));
  updateLangButtons();
}
function renderWelcome() {
  appendMessage(UI.messages, "assistant", t("default_greeting"));
}
function renderHistory() {
  UI.messages?.querySelectorAll(".message").forEach((n) => n.remove());
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
      name: String(parsed.name || "").trim(),
      email: String(parsed.email || "").trim(),
      mobile: String(parsed.mobile || "").trim(),
      topics: Array.isArray(parsed.topics) ? parsed.topics : [],
      supportSent: parsed.supportSent === true
    };
    if (!userInfo.topics.length) userInfo.topics = ["general"];
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
  }
}
function setPreChatStep(step) {
  ["step-name", "step-contact", "step-topic"].forEach((name) => {
    const node = UI.preChat?.querySelector(`.${name}`);
    if (!node) return;
    node.classList.toggle("d-none", name !== step);
  });
}
function showPreChat() {
  UI.preChat?.classList.remove("d-none");
  UI.messages?.classList.add("d-none");
  UI.footer?.classList.add("d-none");
  setPreChatStep("step-name");
}
function clearPreChat() {
  UI.preChat?.classList.add("d-none");
  UI.messages?.classList.remove("d-none");
  UI.footer?.classList.remove("d-none");
}
function getSelectedTopics() {
  return Array.from(document.querySelectorAll(".intro-topic-option:checked")).map((el) => String(el.value || "").trim()).filter(Boolean);
}
function buildSystemPrompt() {
  const visitor = userInfo?.name ? `Visitor name: ${userInfo.name}.` : "";
  const topics = userInfo?.topics?.length ? `Visitor topics: ${userInfo.topics.join(", ")}.` : "";
  return [
    "You are Brox, the bilingual public assistant for BroxLab.",
    `BroxLab website: ${ASSISTANT_SITE_URL}.`,
    `Current UI language: ${currentLang === "bn" ? "Bangla" : "English"}.`,
    visitor,
    topics,
    "Keep replies concise and friendly.",
    "If asked your name, answer that you are Brox and mention BroxLab with the URL.",
    "If asked about yourself or broxlab.online, describe briefly and include the site URL.",
    "Do not promise backend actions; provide helpful guidance and links."
  ].filter(Boolean).join("\n");
}
function resetChat() {
  chatHistory = [];
  historyStore.save(chatHistory);
  renderHistory();
  appendAssistant(UI.messages, t("chat_reset_notice"), { animate: true });
}
function initQuickAction() {
  if (!UI.footer) return;
  const inputGroup = UI.footer.querySelector(".input-group");
  if (!inputGroup || inputGroup.querySelector(".assistant-action-strip")) return;
  const strip = document.createElement("div");
  strip.className = "assistant-action-strip";
  const btn = document.createElement("button");
  btn.type = "button";
  btn.className = "btn btn-light assistant-action-btn";
  btn.id = "publicAssistantNewChat";
  btn.title = t("new_chat_title");
  btn.textContent = "\u21BA";
  btn.addEventListener("click", resetChat);
  strip.appendChild(btn);
  UI.btnNewChat = btn;
  inputGroup.insertBefore(strip, inputGroup.firstChild);
}
function bindEvents() {
  UI.langBnBtn?.addEventListener("click", () => {
    currentLang = "bn";
    setLanguage("bn");
    applyLanguage();
    renderHistory();
  });
  UI.langEnBtn?.addEventListener("click", () => {
    currentLang = "en";
    setLanguage("en");
    applyLanguage();
    renderHistory();
  });
  UI.btn?.addEventListener("click", () => {
    const opening = UI.window?.classList.contains("d-none");
    UI.window?.classList.toggle("hidden");
    UI.window?.classList.toggle("d-none");
    if (opening) {
      userInfo?.name ? clearPreChat() : showPreChat();
    }
  });
  UI.closeBtn?.addEventListener("click", () => {
    UI.window?.classList.add("hidden");
    UI.window?.classList.add("d-none");
  });
  UI.sendBtn?.addEventListener("click", handleUserMessage);
  UI.input?.addEventListener("keypress", (e) => {
    if (e.key === "Enter") handleUserMessage();
  });
  document.getElementById("introNext1")?.addEventListener("click", () => {
    const name = String(document.getElementById("introName")?.value || "").trim();
    if (!name) {
      alert(t("alert_name_required"));
      return;
    }
    setPreChatStep("step-contact");
  });
  document.getElementById("introNext2")?.addEventListener("click", () => setPreChatStep("step-topic"));
  document.getElementById("introStartChat")?.addEventListener("click", () => {
    const name = String(document.getElementById("introName")?.value || "").trim();
    const email = String(document.getElementById("introEmail")?.value || "").trim();
    const mobile = String(document.getElementById("introMobile")?.value || "").trim();
    const topics = getSelectedTopics();
    if (!name) {
      alert(t("alert_name_required"));
      setPreChatStep("step-name");
      return;
    }
    if (!topics.length) {
      alert(t("alert_topic_required"));
      return;
    }
    userInfo = { name, email, mobile, topics, supportSent: false };
    supportLogged = false;
    saveUserInfo();
    clearPreChat();
  });
}
async function handleUserMessage() {
  const text = String(UI.input?.value || "").trim();
  if (!text || !userInfo?.name) {
    showPreChat();
    return;
  }
  const { history, expired } = historyStore.load();
  chatHistory = expired ? [] : history;
  if (expired) appendAssistant(UI.messages, t("chat_reset_notice"));
  UI.input.value = "";
  const ts = (/* @__PURE__ */ new Date()).toISOString();
  chatHistory.push({ role: "user", text, ts });
  historyStore.save(chatHistory);
  appendMessage(UI.messages, "user", text, { ts });
  if (userInfo.topics.includes("support") && !supportLogged) {
    const queued = sendSupportMessage(text);
    if (queued) {
      supportLogged = true;
      userInfo.supportSent = true;
      saveUserInfo();
    }
  }
  const staticReply = getStaticReply(text, currentLang);
  if (staticReply) {
    await appendAssistant(UI.messages, staticReply, { animate: true });
    return;
  }
  setTyping(true);
  setStatus(t("status_thinking"));
  const started = performance.now();
  let usedModel;
  const hasFireworksKey = Boolean(window.FIREWORKS_API_KEY && window.FIREWORKS_API_KEY !== "your_api_key_here");
  const hasOpenRouterKey = Boolean(window.OPENROUTER_API_KEY && window.OPENROUTER_API_KEY !== "your_api_key_here");
  const isProviderConfigured = assistantPrefs.provider === "fireworks" && hasFireworksKey || assistantPrefs.provider === "openrouter" && hasOpenRouterKey;
  if (isProviderConfigured) {
    try {
      const apiMessages = [
        { role: "system", content: buildSystemPrompt() },
        ...chatHistory.map((r) => ({ role: r.role, content: r.text })),
        { role: "user", content: text }
      ];
      let model, response;
      if (assistantPrefs.provider === "openrouter") {
        model = assistantPrefs.model && assistantPrefs.model.includes("/") ? assistantPrefs.model : "openai/gpt-5.2";
        response = await callOpenRouterAI(apiMessages, { stream: false, model });
      } else {
        model = assistantPrefs.model || "accounts/fireworks/models/deepseek-v3p1";
        response = await callFireworksAI(apiMessages, { stream: false, model });
      }
      const reply = extractResponseText(response) || t("fallback_error");
      usedModel = model.replace("accounts/fireworks/models/", "").replace("openai/", "");
      const assistantMsg = await appendAssistant(UI.messages, reply, {
        animate: true,
        model: usedModel,
        tools: true,
        onRun: () => {
          UI.input.value = reply;
          handleUserMessage();
        }
      });
      const finalContent = await applyResponseConfig(assistantMsg, reply, { responseConfig: {} });
      const responseMs = Math.max(0, Math.round(performance.now() - started));
      chatHistory.push({ role: "assistant", text: finalContent, ts: (/* @__PURE__ */ new Date()).toISOString(), responseMs });
      historyStore.save(chatHistory);
      setStatus(t("assistant_status"));
      setTyping(false);
      historyStore.updateActivity();
      return;
    } catch (providerErr) {
      console.log("Settings provider failed, falling back to Puter:", providerErr.message);
    }
  }
  try {
    await ensurePuterReady({ interactive: false, allowAuth: false, t: (key) => t(key) });
    const puter = await getPuterClient();
    const model = assistantPrefs.model || "gemini-2.0-flash";
    usedModel = model;
    const apiMessages = [
      { role: "system", content: buildSystemPrompt() },
      ...chatHistory.map((r) => ({ role: r.role, content: r.text })),
      { role: "user", content: text }
    ];
    const response = await puter.ai.chat(apiMessages, { model, stream: false });
    const reply = extractResponseText(response) || t("fallback_error");
    const assistantMsg = await appendAssistant(UI.messages, reply, {
      animate: true,
      model: usedModel,
      tools: true,
      onRun: () => {
        UI.input.value = reply;
        handleUserMessage();
      }
    });
    const finalContent = await applyResponseConfig(assistantMsg, reply, { responseConfig: {} });
    const responseMs = Math.max(0, Math.round(performance.now() - started));
    chatHistory.push({ role: "assistant", text: finalContent, ts: (/* @__PURE__ */ new Date()).toISOString(), responseMs });
    historyStore.save(chatHistory);
    setStatus(t("assistant_status"));
  } catch (puterErr) {
    const msg = String(puterErr?.message || t("fallback_error"));
    await appendAssistant(UI.messages, msg, { animate: true });
    setStatus(msg);
  }
  setTyping(false);
  historyStore.updateActivity();
}
function sendSupportMessage(messageText) {
  const payload = new URLSearchParams();
  const name = String(userInfo?.name || "").trim();
  const email = String(userInfo?.email || "").trim();
  const mobile = String(userInfo?.mobile || "").trim();
  const contact = mobile || email;
  if (!name && !contact) return false;
  if (name) payload.append("name", name);
  if (email) payload.append("email", email);
  if (mobile) payload.append("mobile", mobile);
  if (contact) payload.append("contact", contact);
  payload.append("message", messageText);
  fetch("/api/public-chat/support", { method: "POST", body: payload, headers: { Accept: "application/json" } }).then((res) => res.json()).then((data) => {
    if (!data?.success && userInfo) {
      userInfo.supportSent = false;
      saveUserInfo();
    }
  }).catch(() => {
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
  const { history, expired } = historyStore.load();
  chatHistory = history;
  historyExpired = expired;
  applyLanguage();
  renderHistory();
  if (historyExpired) appendAssistant(UI.messages, t("session_expired_notice"));
  bindEvents();
  initQuickAction();
  if (userInfo?.name) clearPreChat();
  else showPreChat();
  setStatus(t("assistant_status"));
}
init();

// public_html/assets/ai-assistant/bootstrap/public-assistant.js
ensureAssistantStyles(new URL("../styles/assistant-ui.css", import.meta.url).href);
