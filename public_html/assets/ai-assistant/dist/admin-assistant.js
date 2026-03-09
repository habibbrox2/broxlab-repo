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
function getCenteredPopupFeatures({ width, height }) {
  const dualScreenLeft = window.screenLeft ?? window.screenX ?? 0;
  const dualScreenTop = window.screenTop ?? window.screenY ?? 0;
  const viewportWidth = window.outerWidth || document.documentElement.clientWidth || screen.width;
  const viewportHeight = window.outerHeight || document.documentElement.clientHeight || screen.height;
  const left = Math.max(0, Math.round(dualScreenLeft + (viewportWidth - width) / 2));
  const top = Math.max(0, Math.round(dualScreenTop + (viewportHeight - height) / 2));
  return [
    "toolbar=no",
    "location=no",
    "directories=no",
    "status=no",
    "menubar=no",
    "scrollbars=yes",
    "resizable=yes",
    `width=${width}`,
    `height=${height}`,
    `top=${top}`,
    `left=${left}`
  ].join(", ");
}
function buildError(message) {
  return new Error(message || "Authentication required");
}
function isTrustedPuterOrigin(origin, expectedOrigin) {
  if (typeof origin !== "string" || !origin) return false;
  try {
    const current = new URL(origin);
    const expected = new URL(expectedOrigin);
    if (current.origin === expected.origin) return true;
    if (current.protocol !== "https:") return false;
    return current.hostname === "puter.com" || current.hostname.endsWith(".puter.com");
  } catch {
    return false;
  }
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
async function getPuterGuiOrigin() {
  const p = await getPuter();
  const origin = typeof p?.defaultGUIOrigin === "string" && p.defaultGUIOrigin ? p.defaultGUIOrigin : "https://puter.com";
  return origin.replace(/\/+$/, "");
}
async function buildPuterSignInUrl(msgId) {
  const origin = await getPuterGuiOrigin();
  const params = new URLSearchParams({
    embedded_in_popup: "true",
    msg_id: String(msgId),
    attempt_temp_user_creation: "true"
  });
  if (window.crossOriginIsolated) {
    params.set("cross_origin_isolated", "true");
  }
  return `${origin}/action/sign-in?${params.toString()}`;
}
function buildPopupSignIn({ popupSize = DEFAULT_POPUP, t: t2 } = {}) {
  let authMessageId = 1;
  let pending = null;
  return async function openPopup() {
    if (pending) return pending;
    const msgId = authMessageId++;
    const origin = await getPuterGuiOrigin();
    const popup = window.open(await buildPuterSignInUrl(msgId), "Puter", getCenteredPopupFeatures(popupSize));
    if (!popup) {
      return Promise.reject(normalizeAuthError({ code: "popup_blocked" }, t2));
    }
    popup.focus?.();
    pending = new Promise((resolve, reject) => {
      const expectedOrigin = origin;
      let settled = false;
      let closedIntervalId = 0;
      let timeoutId = 0;
      const cleanup = () => {
        if (closedIntervalId) window.clearInterval(closedIntervalId);
        if (timeoutId) window.clearTimeout(timeoutId);
        window.removeEventListener("message", handleMessage);
        pending = null;
      };
      const finalize = (cb, value) => {
        if (settled) return;
        settled = true;
        cleanup();
        try {
          popup.close();
        } catch {
        }
        cb(value);
      };
      const handleMessage = async (event) => {
        if (event.source !== popup) return;
        if (!isTrustedPuterOrigin(event.origin, expectedOrigin)) return;
        const payload = event.data;
        if (!payload || typeof payload !== "object" || Number(payload.msg_id) !== msgId) return;
        if (payload.success) {
          const p = await getPuter();
          if (payload.token) {
            p.setAuthToken?.(payload.token);
          }
          finalize(resolve, payload);
          return;
        }
        finalize(reject, normalizeAuthError(payload, t2));
      };
      window.addEventListener("message", handleMessage);
      closedIntervalId = window.setInterval(() => {
        if (!popup.closed) return;
        finalize(reject, normalizeAuthError({ code: "auth_window_closed" }, t2));
      }, 250);
      timeoutId = window.setTimeout(() => {
        finalize(reject, normalizeAuthError({ code: "auth_timeout" }, t2));
      }, popupSize.timeoutMs ?? DEFAULT_POPUP.timeoutMs);
    });
    return pending;
  };
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
function shouldRetryWithoutModel(error) {
  const message = String(error?.message || error?.error || "").toLowerCase();
  return message.includes("no fallback model available") || message.includes("model_not_found") || message.includes("model not found") || message.includes("unknown model") || message.includes("unsupported model");
}
function getModelId(model) {
  if (!model) return "";
  const candidates = [model.id, model.model, model.model_id, model.modelId, model.name];
  return candidates.find((v) => typeof v === "string" && v.trim())?.trim() || "";
}
function isFailedChatResponse(response) {
  return !!response && typeof response === "object" && response.success === false && typeof response.error === "string" && response.error.trim() !== "";
}
function buildChatClient({
  chatModel,
  modelPreferences = [],
  listModels = async () => (await getPuter()).ai.listModels(),
  chat = async (messages, options) => (await getPuter()).ai.chat(messages, options)
} = {}) {
  let resolvedModel = chatModel || "";
  let discoveryPromise = null;
  const discoverModel = async (forceRefresh = false) => {
    const p = await getPuter();
    if (!p?.ai?.listModels) return chatModel;
    if (!forceRefresh && resolvedModel) return resolvedModel;
    if (!forceRefresh && discoveryPromise) return discoveryPromise;
    discoveryPromise = (async () => {
      try {
        const models = await listModels();
        const ids = Array.isArray(models) ? models.map(getModelId).filter(Boolean) : [];
        if (!ids.length) return chatModel;
        if (chatModel) {
          const exact = ids.find((id) => id.toLowerCase() === chatModel.toLowerCase());
          if (exact) {
            resolvedModel = exact;
            return resolvedModel;
          }
        }
        for (const pref of modelPreferences) {
          const match = ids.find((id) => id.toLowerCase() === pref.toLowerCase());
          if (match) {
            resolvedModel = match;
            return resolvedModel;
          }
        }
        resolvedModel = ids[0];
        return resolvedModel;
      } finally {
        discoveryPromise = null;
      }
    })();
    return discoveryPromise;
  };
  const chatWithFallback = async (messages, {
    includeTools = true,
    tools = [],
    temperature,
    maxTokens,
    reasoningEffort,
    textVerbosity,
    stream = false
  } = {}) => {
    const preferredModel = await discoverModel(false);
    const buildOptions = (model) => {
      const opts = {};
      if (model) opts.model = model;
      if (includeTools && tools?.length) opts.tools = tools;
      if (typeof temperature === "number") opts.temperature = temperature;
      if (typeof maxTokens === "number") opts.max_tokens = maxTokens;
      if (typeof reasoningEffort === "string" && reasoningEffort.trim()) {
        opts.reasoning_effort = reasoningEffort.trim();
      }
      if (typeof textVerbosity === "string" && textVerbosity.trim()) {
        opts.text_verbosity = textVerbosity.trim();
        opts.verbosity = textVerbosity.trim();
      }
      if (stream) opts.stream = true;
      return opts;
    };
    const attempt = async (model) => {
      const response = await chat(messages, buildOptions(model));
      if (isFailedChatResponse(response)) throw response;
      return response;
    };
    try {
      return await attempt(preferredModel);
    } catch (err) {
      if (!shouldRetryWithoutModel(err)) throw err;
      const rediscovered = await discoverModel(true);
      const queue = [];
      if (rediscovered && rediscovered !== preferredModel) queue.push(rediscovered);
      if (chatModel && chatModel !== preferredModel && chatModel !== rediscovered) queue.push(chatModel);
      queue.push("");
      for (const model of queue) {
        try {
          const resp = await attempt(model);
          if (model) resolvedModel = model;
          return resp;
        } catch (retryErr) {
          if (!shouldRetryWithoutModel(retryErr)) throw retryErr;
        }
      }
      throw err;
    }
  };
  return { chatWithFallback, discoverModel, getResolvedModel: () => resolvedModel };
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
async function generateImage(prompt, {
  model = "gpt-image-1.5",
  aspectRatio = "1:1",
  negativePrompt = "",
  testMode = false
} = {}) {
  const p = await getPuter();
  const image = await p.ai.txt2img({
    prompt: String(prompt || "").trim(),
    model,
    aspect_ratio: aspectRatio,
    negative_prompt: negativePrompt,
    test_mode: testMode
  });
  return image;
}
async function speakText(text, {
  provider = "openai",
  model = "gpt-4o-mini-tts",
  voice = "alloy",
  language = "en-US",
  instructions = "",
  testMode = false
} = {}) {
  const p = await getPuter();
  const audio = await p.ai.txt2speech(String(text || ""), {
    provider,
    model,
    voice,
    language,
    instructions,
    test_mode: testMode
  });
  return audio;
}
if (typeof window !== "undefined") {
  window.getPuter = getPuterClient;
}

// public_html/assets/ai-assistant/core/log-monitor.js
function createLogMonitor({ pollIntervalMs = 1e4 } = {}) {
  const state = {
    isPolling: false,
    lastCheckTime: 0,
    lastErrorTimestamp: 0,
    listeners: [],
    logs: {
      errors: [],
      warnings: [],
      all: []
    }
  };
  function onLogUpdate(callback) {
    if (typeof callback === "function") {
      state.listeners.push(callback);
    }
  }
  function notifyListeners(eventType, data) {
    state.listeners.forEach((listener) => {
      try {
        listener(eventType, data);
      } catch (err) {
        console.error("Log listener error:", err);
      }
    });
  }
  async function fetchLogs(options = {}) {
    const { file = "errors.log", lines = 20, filter = null } = options;
    try {
      const url = new URL("/api/admin/logs/read", window.location.origin);
      url.searchParams.set("file", file);
      url.searchParams.set("lines", lines);
      if (filter) {
        url.searchParams.set("filter", filter);
      }
      const response = await fetch(url.toString(), {
        method: "GET",
        credentials: "include",
        headers: {
          "Accept": "application/json"
        }
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return await response.json();
    } catch (error) {
      console.error("Failed to fetch logs:", error);
      return { error: error.message, entries: [] };
    }
  }
  async function getRecentErrors(limit = 10) {
    try {
      const url = new URL("/api/admin/logs/errors", window.location.origin);
      url.searchParams.set("limit", limit);
      url.searchParams.set("since", state.lastErrorTimestamp);
      const response = await fetch(url.toString(), {
        method: "GET",
        credentials: "include",
        headers: {
          "Accept": "application/json"
        }
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const data = await response.json();
      if (data.errors && data.errors.length > 0) {
        if (data.latest_timestamp > state.lastErrorTimestamp) {
          state.lastErrorTimestamp = data.latest_timestamp;
          notifyListeners("errors", data.errors);
        }
      }
      return data;
    } catch (error) {
      console.error("Failed to get recent errors:", error);
      return { errors: [], count: 0 };
    }
  }
  async function getLogStats() {
    try {
      const response = await fetch("/api/admin/logs/stats", {
        method: "GET",
        credentials: "include",
        headers: {
          "Accept": "application/json"
        }
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return await response.json();
    } catch (error) {
      console.error("Failed to get log stats:", error);
      return { stats: {} };
    }
  }
  async function listLogs() {
    try {
      const response = await fetch("/api/admin/logs", {
        method: "GET",
        credentials: "include",
        headers: {
          "Accept": "application/json"
        }
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return await response.json();
    } catch (error) {
      console.error("Failed to list logs:", error);
      return { logs: [] };
    }
  }
  function startPolling() {
    if (state.isPolling) return;
    state.isPolling = true;
    const poll = async () => {
      try {
        await getRecentErrors(5);
      } catch (error) {
        console.error("Error during polling:", error);
      }
      if (state.isPolling) {
        setTimeout(poll, pollIntervalMs);
      }
    };
    poll();
  }
  function stopPolling() {
    state.isPolling = false;
  }
  function formatLogEntry(entry) {
    const severity = entry.severity || "INFO";
    const severityColor = {
      "ERROR": "#dc2626",
      "CRITICAL": "#991b1b",
      "WARNING": "#f59e0b",
      "INFO": "#3b82f6",
      "DEBUG": "#8b5cf6"
    }[severity] || "#6b7280";
    return {
      ...entry,
      severity_color: severityColor,
      display_timestamp: new Date(entry.timestamp_unix * 1e3).toLocaleString(),
      is_error: severity === "ERROR" || severity === "CRITICAL",
      is_warning: severity === "WARNING"
    };
  }
  function createEntryHTML(entry) {
    const formatted = formatLogEntry(entry);
    return `
      <div class="log-entry log-entry-${formatted.severity.toLowerCase()}" data-timestamp="${formatted.timestamp_unix}">
        <div class="log-entry-header">
          <span class="log-severity" style="color: ${formatted.severity_color}">
            [${formatted.severity}]
          </span>
          <span class="log-timestamp">${formatted.display_timestamp}</span>
        </div>
        <div class="log-entry-message">${escapeHtml2(formatted.message)}</div>
        ${formatted.context ? `<div class="log-entry-context"><pre>${escapeHtml2(formatted.context.substring(0, 500))}</pre></div>` : ""}
      </div>
    `;
  }
  function escapeHtml2(text) {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;"
    };
    return text.replace(/[&<>"']/g, (m) => map[m]);
  }
  return {
    fetchLogs,
    getRecentErrors,
    getLogStats,
    listLogs,
    onLogUpdate,
    startPolling,
    stopPolling,
    formatLogEntry,
    createEntryHTML,
    isPolling: () => state.isPolling
  };
}
if (typeof window !== "undefined") {
  window.LogMonitor = createLogMonitor;
}

// public_html/assets/ai-assistant/modules/admin/app.js
var UI = {
  wrapper: document.getElementById("adminAssistantWrapper"),
  chat: document.getElementById("adminAssistantChat"),
  messages: document.getElementById("assistantMessages"),
  input: document.getElementById("assistantInput"),
  sendBtn: document.getElementById("sendToAssistant"),
  toggleBtn: document.getElementById("adminAssistantBtn"),
  closeBtn: document.getElementById("closeAssistant"),
  statusIndicator: document.getElementById("adminAssistantStatusIndicator"),
  status: document.getElementById("adminAssistantStatus"),
  publicModeBadge: document.getElementById("adminAssistantPublicModeBadge"),
  signInBtn: document.getElementById("btnPuterSignIn"),
  title: document.getElementById("adminAssistantTitle"),
  langBnBtn: document.getElementById("adminAssistantLangBn"),
  langEnBtn: document.getElementById("adminAssistantLangEn"),
  loading: document.getElementById("adminAssistantLoading"),
  typingText: document.getElementById("adminAssistantTypingText"),
  modeSelect: document.getElementById("adminAssistantMode"),
  modelSelect: document.getElementById("adminAssistantModel"),
  settingsToggle: document.getElementById("adminAssistantSettingsToggle"),
  advancedPanel: document.getElementById("adminAssistantAdvancedPanel"),
  streamToggle: document.getElementById("adminAssistantStream"),
  webSearchToggle: document.getElementById("adminAssistantWebSearch"),
  temperatureInput: document.getElementById("adminAssistantTemperature"),
  temperatureValue: document.getElementById("adminAssistantTemperatureValue"),
  maxTokensInput: document.getElementById("adminAssistantMaxTokens"),
  reasoningSelect: document.getElementById("adminAssistantReasoning"),
  verbositySelect: document.getElementById("adminAssistantVerbosity"),
  imageModelSelect: document.getElementById("adminAssistantImageModel"),
  imageAspectSelect: document.getElementById("adminAssistantImageAspect"),
  ttsVoiceSelect: document.getElementById("adminAssistantTtsVoice"),
  fileInput: document.getElementById("adminAssistantFileInput"),
  visionInput: document.getElementById("adminAssistantVisionInput"),
  imageUrlInput: document.getElementById("adminAssistantImageUrl"),
  attachmentName: document.getElementById("adminAssistantAttachmentName"),
  attachVisionBtn: document.getElementById("btnAttachVision"),
  generateImageBtn: document.getElementById("btnGenerateImage"),
  speakLastReplyBtn: document.getElementById("btnSpeakLastReply"),
  reasoningBanner: document.getElementById("adminAssistantReasoningBanner")
};
var CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
var CHAT_MODEL = typeof window.BROX_ADMIN_ASSISTANT_MODEL === "string" ? window.BROX_ADMIN_ASSISTANT_MODEL.trim() : "";
var CHAT_STORAGE_KEY = "brox.adminAssistant.chat.v3";
var LAST_ACTIVITY_KEY = "brox.adminAssistant.lastActivity.v3";
var LANGUAGE_KEY = "brox.adminAssistant.language.v3";
var PREFS_STORAGE_KEY = "brox.adminAssistant.prefs.v1";
var MAX_STORED_MESSAGES = 40;
var INACTIVITY_LIMIT_MS = 30 * 60 * 1e3;
var PUTER_POPUP_SIZE = { width: 600, height: 700, timeoutMs: 2 * 60 * 1e3 };
var CHAT_MODEL_PREFERENCES = [
  "openai/gpt-4.1-mini",
  "openai/gpt-4o-mini",
  "openai/gpt-4.1",
  "openai/gpt-4o",
  "anthropic/claude-3-5-sonnet",
  "anthropic/claude-3-7-sonnet",
  "google/gemini-2.0-flash",
  "google/gemini-2.5-flash",
  "gpt-4.1-mini",
  "gpt-4o-mini",
  "gpt-4.1",
  "gpt-4o",
  "claude-3-5-sonnet",
  "claude-3-7-sonnet",
  "gemini-2.0-flash",
  "gemini-2.5-flash"
];
async function callFireworksAI(messages, options = {}) {
  const apiKey = window.FIREWORKS_API_KEY || "your_api_key_here";
  const response = await fetch("https://api.fireworks.ai/inference/v1/chat/completions", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": `Bearer ${apiKey}`
    },
    body: JSON.stringify({
      model: "accounts/fireworks/models/deepseek-v3p1",
      messages,
      stream: options.stream || false,
      ...options
    })
  });
  if (!response.ok) throw new Error("Fireworks API error");
  return await response.json();
}
function parseSuggestionsFromText(text) {
  const match = text.match(/\[SUGGESTION:\s*(.*?)\]/);
  if (match) {
    const suggestions = match[1].split(",").map((s) => s.trim());
    const cleanText = text.replace(/\[SUGGESTION:\s*.*?\]/, "").trim();
    return { text: cleanText, suggestions };
  }
  return { text, suggestions: [] };
}
var ASSISTANT_SITE_URL = "https://broxlab.online";
var OPENAI_PROVIDER = "openai";
var DEFAULT_IMAGE_MODEL = "gpt-image-1.5";
var DEFAULT_TTS_MODEL = "gpt-4o-mini-tts";
var FALLBACK_OPENAI_MODELS = [
  { value: "", label: "Auto Model" },
  { value: "gpt-5-nano", label: "gpt-5-nano" },
  { value: "gpt-5.4", label: "gpt-5.4" },
  { value: "gpt-5.3-chat", label: "gpt-5.3-chat" },
  { value: "gpt-5.2", label: "gpt-5.2" },
  { value: "gpt-5", label: "gpt-5" },
  { value: "gpt-4.1", label: "gpt-4.1" },
  { value: "gpt-4o", label: "gpt-4o" },
  { value: "openai/gpt-5.2-chat", label: "openai/gpt-5.2-chat" },
  { value: "gpt-5.3-codex", label: "gpt-5.3-codex" },
  { value: "openai/gpt-oss-120b", label: "openai/gpt-oss-120b" },
  { value: "openai/gpt-oss-20b", label: "openai/gpt-oss-20b" }
];
var MODE_PRESETS = {
  assistant: { model: "", useAdminTools: true, stream: false, webSearch: true },
  openai: { model: "gpt-5-nano", useAdminTools: false, stream: true, webSearch: true },
  codex: { model: "gpt-5.3-codex", useAdminTools: false, stream: true, webSearch: false },
  gpt_oss: { model: "openai/gpt-oss-120b", useAdminTools: false, stream: true, webSearch: false, reasoningEffort: "high" },
  vision: { model: "gpt-5-nano", useAdminTools: false, stream: true, webSearch: false }
};
var DEFAULT_PREFS = {
  mode: "assistant",
  provider: "puter-js",
  model: "gemini-2.0-flash",
  stream: true,
  webSearch: true,
  temperature: 0.7,
  maxTokens: 1024,
  reasoningEffort: "medium",
  textVerbosity: "medium",
  imageModel: DEFAULT_IMAGE_MODEL,
  imageAspect: "1:1",
  ttsVoice: "alloy"
};
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
      model: options.model || "openrouter/free",
      messages,
      stream: options.stream || false,
      ...options
    })
  });
  if (!response.ok) throw new Error("OpenRouter API error");
  return await response.json();
}
var REDIRECT_ACTIONS = /* @__PURE__ */ new Set([
  "create_mobile",
  "edit_mobile",
  "create_post",
  "edit_post",
  "create_page",
  "edit_page",
  "create_service",
  "edit_service"
]);
var ACTION_PERMISSIONS = {
  create_post: "post.create",
  edit_post: "post.edit",
  delete_post: "post.delete",
  create_service: "service.create",
  edit_service: "service.edit",
  delete_service: "service.delete",
  create_page: "page.create",
  edit_page: "page.edit",
  delete_page: "page.delete",
  create_category: "category.create",
  edit_category: "category.edit",
  delete_category: "category.delete",
  create_tag: "tag.create",
  edit_tag: "tag.edit",
  delete_tag: "tag.delete",
  create_mobile: "mobile.create",
  edit_mobile: "mobile.edit",
  delete_mobile: "mobile.delete"
};
window.PUTER_PROXY_PUBLIC_ONLY = true;
window.PUTER_DISABLED = false;
function isPublicMode() {
  return Boolean(window.PUTER_PROXY_PUBLIC_ONLY);
}
function updatePublicModeBadge() {
  if (!UI.publicModeBadge) return;
  if (isPublicMode()) {
    UI.publicModeBadge.classList.remove("d-none");
    if (UI.signInBtn) UI.signInBtn.classList.add("d-none");
  } else {
    UI.publicModeBadge.classList.add("d-none");
    if (UI.signInBtn) UI.signInBtn.classList.remove("d-none");
  }
}
if (UI.signInBtn) {
  UI.signInBtn.addEventListener("click", async () => {
    try {
      await ensurePuterReady({ interactive: true, allowAuth: true, t: (key) => typeof t === "function" ? t(key) : key, openPopup: getOpenSignInPopup() });
      if (UI.signInBtn) UI.signInBtn.classList.add("d-none");
      if (UI.publicModeBadge) UI.publicModeBadge.classList.add("d-none");
      setStatus("status_ready");
    } catch (err) {
      setStatus(String(err?.message || "Sign-in failed"), { raw: true });
    }
  });
}
var LINK_ACTIONS = {
  upload_file: { url: "/admin/media/upload", label: "/admin/media/upload" },
  view_uploads: { url: "/admin/media", label: "/admin/media" },
  manage_service_applications: { url: "/admin/applications", label: "/admin/applications" },
  manage_payments: { url: "/admin/payments", label: "/admin/payments" },
  manage_chats: { url: "/admin/contact", label: "/admin/contact" }
};
var I18N = {
  bn: {
    title: "\u09AC\u09CD\u09B0\u0995\u09CD\u09B8 \u09B8\u09B9\u0995\u09BE\u09B0\u09C0",
    input_placeholder: "\u0986\u09AA\u09A8\u09BE\u09B0 \u09A8\u09BF\u09B0\u09CD\u09A6\u09C7\u09B6 \u09B2\u09BF\u0996\u09C1\u09A8...",
    typing_text: "\u099F\u09BE\u0987\u09AA \u0995\u09B0\u099B\u09C7...",
    default_greeting: "\u0986\u09AE\u09BF \u0986\u09AA\u09A8\u09BE\u09B0 BroxLab \u0985\u09CD\u09AF\u09BE\u09A1\u09AE\u09BF\u09A8 \u09B8\u09B9\u0995\u09BE\u09B0\u09C0\u0964 \u09AA\u09CB\u09B8\u09CD\u099F, \u09AA\u09C7\u099C, \u09B8\u09BE\u09B0\u09CD\u09AD\u09BF\u09B8, \u09AE\u09BF\u09A1\u09BF\u09AF\u09BC\u09BE, \u0985\u09CD\u09AF\u09BE\u09A8\u09BE\u09B2\u09BF\u099F\u09BF\u0995\u09CD\u09B8 \u09AC\u09BE \u0985\u09A8\u09CD\u09AF \u0985\u09CD\u09AF\u09BE\u09A1\u09AE\u09BF\u09A8 \u0995\u09BE\u099C \u09A8\u09BF\u09AF\u09BC\u09C7 \u099C\u09BF\u099C\u09CD\u099E\u09C7\u09B8 \u0995\u09B0\u09C1\u09A8\u0964 \u098F\u0987 \u0985\u09CD\u09AF\u09BE\u09B8\u09BF\u09B8\u09CD\u099F\u09CD\u09AF\u09BE\u09A8\u09CD\u099F public mode-\u098F Puter sign-in \u099B\u09BE\u09DC\u09BE\u0987 \u0995\u09BE\u099C \u0995\u09B0\u09C7\u0964",
    status_initializing: "\u099A\u09BE\u09B2\u09C1 \u09B9\u099A\u09CD\u099B\u09C7...",
    status_ready: "\u09B8\u09B9\u0995\u09BE\u09B0\u09C0 \u09AA\u09CD\u09B0\u09B8\u09CD\u09A4\u09C1\u09A4",
    status_ready_to_connect: "\u09AC\u09BE\u09B0\u09CD\u09A4\u09BE \u09AA\u09BE\u09A0\u09BE\u09B2\u09C7 \u09B8\u0982\u09AF\u09C1\u0995\u09CD\u09A4 \u09B9\u09AC\u09C7",
    status_connecting: "Puter-\u098F \u09B8\u0982\u09AF\u09C1\u0995\u09CD\u09A4 \u09B9\u099A\u09CD\u099B\u09C7...",
    status_error: "\u09A4\u09CD\u09B0\u09C1\u099F\u09BF \u09B9\u09AF\u09BC\u09C7\u099B\u09C7",
    status_response: "\u09B0\u09C7\u09B8\u09AA\u09A8\u09CD\u09B8",
    notice_session_expired: "\u09A8\u09BF\u09B7\u09CD\u0995\u09CD\u09B0\u09BF\u09AF\u09BC\u09A4\u09BE\u09B0 \u0995\u09BE\u09B0\u09A3\u09C7 \u09AA\u09C2\u09B0\u09CD\u09AC\u09C7\u09B0 \u099A\u09CD\u09AF\u09BE\u099F \u09B6\u09C7\u09B7 \u09B9\u09AF\u09BC\u09C7\u099B\u09C7\u0964 \u09A8\u09A4\u09C1\u09A8 \u09B8\u09C7\u09B6\u09A8 \u09B6\u09C1\u09B0\u09C1\u0964",
    notice_chat_reset: "\u09A8\u09BF\u09B7\u09CD\u0995\u09CD\u09B0\u09BF\u09AF\u09BC\u09A4\u09BE\u09B0 \u0995\u09BE\u09B0\u09A3\u09C7 \u099A\u09CD\u09AF\u09BE\u099F \u09B0\u09BF\u09B8\u09C7\u099F \u09B9\u09AF\u09BC\u09C7\u099B\u09C7\u0964",
    notice_new_chat: "\u09A8\u09A4\u09C1\u09A8 \u099A\u09CD\u09AF\u09BE\u099F \u09B6\u09C1\u09B0\u09C1 \u09B9\u09AF\u09BC\u09C7\u099B\u09C7\u0964",
    notice_unknown_tool: "\u09B8\u09B9\u0995\u09BE\u09B0\u09C0 \u098F\u0995\u099F\u09BF \u0985\u099C\u09BE\u09A8\u09BE \u099F\u09C1\u09B2 \u09AC\u09CD\u09AF\u09AC\u09B9\u09BE\u09B0 \u0995\u09B0\u09A4\u09C7 \u099A\u09C7\u09AF\u09BC\u09C7\u099B\u09BF\u09B2\u0964",
    error_puter_missing: "Puter \u0995\u09CD\u09B2\u09BE\u09AF\u09BC\u09C7\u09A8\u09CD\u099F \u09B2\u09CB\u09A1 \u09B9\u09AF\u09BC\u09A8\u09BF\u0964",
    error_sign_in_required: "\u099A\u09BE\u09B2\u09BF\u09AF\u09BC\u09C7 \u09AF\u09C7\u09A4\u09C7 Puter \u09B8\u09BE\u0987\u09A8-\u0987\u09A8 \u09A6\u09B0\u0995\u09BE\u09B0\u0964",
    error_sign_in_popup_blocked: "Puter \u09B8\u09BE\u0987\u09A8-\u0987\u09A8 \u09AA\u09AA\u0986\u09AA \u09AC\u09CD\u09B2\u0995 \u09B9\u09AF\u09BC\u09C7\u099B\u09C7\u0964 \u09AA\u09AA\u0986\u09AA allow \u0995\u09B0\u09C1\u09A8\u0964",
    error_sign_in_popup_closed: "\u09AA\u09AA\u0986\u09AA \u09AC\u09A8\u09CD\u09A7 \u09B9\u0993\u09AF\u09BC\u09BE\u09AF\u09BC \u09B8\u09BE\u0987\u09A8-\u0987\u09A8 \u09B8\u09AE\u09CD\u09AA\u09A8\u09CD\u09A8 \u09B9\u09AF\u09BC\u09A8\u09BF\u0964",
    error_sign_in_timeout: "Puter \u09B8\u09BE\u0987\u09A8-\u0987\u09A8\u09C7 \u09B8\u09AE\u09AF\u09BC\u09B8\u09C0\u09AE\u09BE \u0985\u09A4\u09BF\u0995\u09CD\u09B0\u09AE\u0964 \u0986\u09AC\u09BE\u09B0 \u099A\u09C7\u09B7\u09CD\u099F\u09BE \u0995\u09B0\u09C1\u09A8\u0964"
  },
  en: {
    title: "Brox Assistant",
    input_placeholder: "Type your instruction...",
    typing_text: "Typing...",
    default_greeting: "I am your Brox admin assistant. Ask about posts, pages, services, media, analytics, or other admin work. This assistant runs in public mode without requiring Puter sign-in.",
    status_initializing: "Initializing...",
    status_ready: "Assistant Ready",
    status_ready_to_connect: "Will connect on first message",
    status_connecting: "Connecting to Puter...",
    status_error: "Error occurred",
    status_response: "Response",
    status_navigating: "Opening page...",
    notice_session_expired: "Previous chat expired; starting fresh.",
    notice_chat_reset: "Chat reset due to inactivity.",
    notice_new_chat: "Started a fresh chat.",
    notice_unknown_tool: "Assistant tried an unknown tool.",
    error_puter_missing: "Puter client is not loaded.",
    error_sign_in_required: "You need to sign in with Puter.",
    error_sign_in_popup_blocked: "Puter sign-in popup was blocked.",
    error_sign_in_popup_closed: "Puter sign-in popup closed early.",
    error_sign_in_timeout: "Puter sign-in timed out."
  }
};
var STATIC_REPLIES = {
  bn: {
    name: "\u0986\u09AE\u09BF brox \u09AC\u09B2\u099B\u09BF, BroxLab \u0985\u09CD\u09AF\u09BE\u09A1\u09AE\u09BF\u09A8 \u09B8\u09B9\u0995\u09BE\u09B0\u09C0 \u09B9\u09BF\u09B8\u09C7\u09AC\u09C7 \u0986\u09AA\u09A8\u09BE\u0995\u09C7 \u09A4\u09A5\u09CD\u09AF \u0993 \u09B8\u09BE\u09AA\u09CB\u09B0\u09CD\u099F\u09C7 \u09B8\u09BE\u09B9\u09BE\u09AF\u09CD\u09AF \u0995\u09B0\u09BF\u0964",
    about: `\u0986\u09AE\u09BF brox \u09AC\u09B2\u099B\u09BF\u0964 BroxLab \u09B9\u09B2\u09CB ${ASSISTANT_SITE_URL} \u09B6\u09BF\u09B0\u09CB\u09A8\u09BE\u09AE\u09C7\u09B0 Bengali-first tech platform, \u09AF\u09C7\u0996\u09BE\u09A8\u09C7 \u0995\u09A8\u099F\u09C7\u09A8\u09CD\u099F, \u09B8\u09C7\u09AC\u09BE \u0993 \u09A1\u09BF\u099C\u09BF\u099F\u09BE\u09B2 \u09A4\u09A5\u09CD\u09AF \u09B8\u09BE\u099C\u09BE\u09A8\u09CB\u09AD\u09BE\u09AC\u09C7 \u09AA\u09CD\u09B0\u0995\u09BE\u09B6 \u0995\u09B0\u09BE \u09B9\u09AF\u09BC\u0964`
  },
  en: {
    name: "I am Brox, speaking as the BroxLab assistant.",
    about: `I am Brox. BroxLab is the Bengali-first tech platform at ${ASSISTANT_SITE_URL}.`
  }
};
var getStaticReply = buildStaticReplyMatcher(STATIC_REPLIES);
var { getLanguage, setLanguage } = createLanguageState({ storageKey: LANGUAGE_KEY, storage: window.sessionStorage });
var currentLang = getLanguage();
var assistantPrefs = { ...DEFAULT_PREFS };
var contextAttachments = [];
var lastAssistantReplyText = "";
var lastAssistantAudio = null;
var historyStore = createHistoryStore({
  storage: window.sessionStorage,
  chatKey: CHAT_STORAGE_KEY,
  activityKey: LAST_ACTIVITY_KEY,
  maxMessages: MAX_STORED_MESSAGES,
  inactivityMs: INACTIVITY_LIMIT_MS
});
var openSignInPopup = null;
function getOpenSignInPopup() {
  if (!openSignInPopup) {
    openSignInPopup = buildPopupSignIn({ popupSize: PUTER_POPUP_SIZE, t: (key) => I18N[currentLang]?.[key] });
  }
  return openSignInPopup;
}
var chatClient = null;
function getChatClient(modelOverride = "") {
  const requestedModel = String(modelOverride || "").trim();
  const cacheKey = requestedModel || CHAT_MODEL || "__auto__";
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
var chatHistory = [];
var historyExpired = false;
var logMonitor = createLogMonitor({ pollIntervalMs: 15e3 });
function t(key) {
  return I18N[currentLang]?.[key] || I18N.en[key] || key;
}
function setStatus(textKey, { raw = false } = {}) {
  if (!UI.status) return;
  if (raw) {
    UI.status.textContent = textKey;
    if (UI.statusIndicator) UI.statusIndicator.classList.remove("ready");
    return;
  }
  const text = t(textKey);
  UI.status.textContent = text;
  if (!UI.statusIndicator) return;
  const readyKeys = ["status_ready", "status_ready_to_connect"];
  const isReady = readyKeys.includes(textKey);
  UI.statusIndicator.classList.toggle("ready", isReady);
}
function saveAssistantPrefs() {
  try {
    window.sessionStorage.setItem(PREFS_STORAGE_KEY, JSON.stringify(assistantPrefs));
  } catch {
  }
}
function getModePreset(mode = assistantPrefs.mode) {
  return MODE_PRESETS[mode] || MODE_PRESETS.assistant;
}
function getSelectedChatModel() {
  return String(assistantPrefs.model || getModePreset().model || CHAT_MODEL || "").trim();
}
function isOpenAIModel(model) {
  const normalized = String(model || "").trim().toLowerCase();
  if (!normalized) return true;
  return normalized.startsWith("openai/") || normalized.startsWith("gpt-") || normalized.startsWith("codex") || normalized.startsWith("gpt-oss");
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
  return String(name || "file").replace(/[^a-zA-Z0-9._-]+/g, "_").replace(/^_+|_+$/g, "") || "file";
}
function getImageUrlAttachment() {
  const imageUrl = String(UI.imageUrlInput?.value || "").trim();
  return imageUrl ? { name: imageUrl, kind: "url", url: imageUrl } : null;
}
function getAllContextAttachments() {
  const imageUrlAttachment = getImageUrlAttachment();
  return imageUrlAttachment ? [...contextAttachments, imageUrlAttachment] : [...contextAttachments];
}
function addContextAttachments(files = []) {
  const nextFiles = Array.from(files || []).filter((file) => file instanceof File).map((file) => ({
    id: `attachment_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
    kind: "file",
    file,
    name: file.name,
    type: file.type,
    size: file.size,
    puterPath: ""
  }));
  if (!nextFiles.length) return;
  contextAttachments = [...contextAttachments, ...nextFiles];
}
async function uploadContextAttachments() {
  const pending = contextAttachments.filter((attachment) => attachment.kind === "file" && attachment.file && !attachment.puterPath);
  if (!pending.length) return;
  const puter = await getPuterClient();
  for (const attachment of pending) {
    const safeName = sanitizeAttachmentName(attachment.name);
    const remoteName = `brox_assistant_${Date.now()}_${safeName}`;
    const uploaded = await puter.fs.write(remoteName, attachment.file);
    const puterPath = String(uploaded?.path || uploaded?.puter_path || "").trim();
    if (!puterPath) {
      throw new Error(`Failed to upload ${attachment.name} as assistant context.`);
    }
    attachment.puterPath = puterPath;
  }
}
async function cleanupUploadedContextAttachments() {
  const uploadedPaths = contextAttachments.map((attachment) => String(attachment?.puterPath || "").trim()).filter(Boolean);
  if (!uploadedPaths.length) return;
  try {
    const puter = await getPuterClient();
    for (const puterPath of uploadedPaths) {
      try {
        await puter.fs.delete(puterPath);
      } catch {
      }
    }
  } catch {
  }
}
function buildAttachmentSummary() {
  const attachments = getAllContextAttachments();
  if (!attachments.length) return "";
  return attachments.map((attachment) => {
    if (attachment.kind === "url") return `[Image URL: ${attachment.url}]`;
    return `[File: ${attachment.name}]`;
  }).join("\n");
}
function hasUploadedContextFiles() {
  return contextAttachments.some((attachment) => attachment.kind === "file");
}
function hasContextInputs() {
  return getAllContextAttachments().length > 0;
}
function updateAttachmentChip() {
  if (!UI.attachmentName) return;
  const label = getAllContextAttachments().map((attachment) => attachment.kind === "url" ? attachment.url : attachment.name).join(" | ");
  UI.attachmentName.textContent = label;
  UI.attachmentName.classList.toggle("d-none", !label);
}
function clearAttachmentState({ clearUrl = true } = {}) {
  contextAttachments = [];
  if (UI.fileInput) UI.fileInput.value = "";
  if (UI.visionInput) UI.visionInput.value = "";
  if (clearUrl && UI.imageUrlInput) UI.imageUrlInput.value = "";
  updateAttachmentChip();
}
function applyAssistantPrefsToUi() {
  if (UI.modeSelect) UI.modeSelect.value = assistantPrefs.mode;
  if (UI.modelSelect) UI.modelSelect.value = assistantPrefs.model;
  if (UI.streamToggle) UI.streamToggle.checked = assistantPrefs.stream;
  if (UI.webSearchToggle) UI.webSearchToggle.checked = assistantPrefs.webSearch;
  if (UI.temperatureInput) UI.temperatureInput.value = String(assistantPrefs.temperature);
  if (UI.maxTokensInput) UI.maxTokensInput.value = String(assistantPrefs.maxTokens);
  if (UI.reasoningSelect) UI.reasoningSelect.value = assistantPrefs.reasoningEffort || "";
  if (UI.verbositySelect) UI.verbositySelect.value = assistantPrefs.textVerbosity || "medium";
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
function setReasoningBanner(text = "") {
  if (!UI.reasoningBanner) return;
  const normalized = String(text || "").trim();
  UI.reasoningBanner.textContent = normalized;
  UI.reasoningBanner.classList.toggle("d-none", !normalized);
}
async function populateModelOptions() {
  if (!UI.modelSelect) return;
  const currentValue = assistantPrefs.model;
  const options = [...FALLBACK_OPENAI_MODELS];
  const addModels = (list) => {
    for (const m of list) {
      if (!options.some((item) => item.value === m.value)) {
        options.push(m);
      }
    }
  };
  try {
    if (assistantPrefs.provider === "fireworks") {
      const apiKey = window.FIREWORKS_API_KEY;
      if (apiKey) {
        const resp = await fetch("https://api.fireworks.ai/inference/v1/models", {
          headers: { Authorization: `Bearer ${apiKey}` }
        });
        if (resp.ok) {
          const data = await resp.json();
          const fw = Array.isArray(data.models) ? data.models.map((m) => ({ value: m.id || m.model || m.name, label: m.name || m.id || m.model })) : [];
          addModels(fw.filter(Boolean));
        }
      }
    } else if (assistantPrefs.provider === "openrouter") {
      const apiKey = window.OPENROUTER_API_KEY;
      if (apiKey) {
        const resp = await fetch("https://openrouter.ai/api/v1/models", {
          headers: { Authorization: `Bearer ${apiKey}` }
        });
        if (resp.ok) {
          const data = await resp.json();
          const orModels = Array.isArray(data.models) ? data.models.map((m) => ({ value: m.id || m.name, label: m.name || m.id })) : [];
          addModels(orModels.filter(Boolean));
        }
      }
    }
  } catch (e) {
    console.log("provider model discovery failed", e);
  }
  try {
    await ensurePuterReady({ interactive: false, t: (key) => t(key) });
    const puter = await getPuterClient();
    const models = await puter.ai.listModels(assistantPrefs.provider || OPENAI_PROVIDER);
    const pmodels = Array.isArray(models) ? models.map((model) => {
      const id = String(model?.id || model?.model || model?.name || "").trim();
      if (!id) return null;
      return { value: id, label: id };
    }).filter(Boolean) : [];
    addModels(pmodels);
  } catch (e) {
  }
  UI.modelSelect.innerHTML = "";
  for (const option of options) {
    const node = document.createElement("option");
    node.value = option.value;
    node.textContent = option.label;
    UI.modelSelect.appendChild(node);
  }
  UI.modelSelect.value = currentValue;
}
function applyLanguage() {
  UI.title && (UI.title.textContent = t("title"));
  UI.input && UI.input.setAttribute("placeholder", t("input_placeholder"));
  UI.typingText && (UI.typingText.textContent = t("typing_text"));
  setStatus("status_ready_to_connect");
  updateLangButtons();
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
function setTyping(active) {
  if (!UI.loading) return;
  UI.loading.classList.toggle("d-none", !active);
  UI.loading.classList.toggle("active", active);
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
async function applyResponseConfig(message, rawText) {
  const { config, content } = parseResponseConfig(rawText || "");
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
function attachSpeechAction(messageNode, text) {
  if (!messageNode || !text) return;
  const actions = document.createElement("div");
  actions.className = "assistant-message-actions";
  const speakBtn = document.createElement("button");
  speakBtn.type = "button";
  speakBtn.className = "assistant-message-action-btn";
  speakBtn.innerHTML = '<i class="bi bi-volume-up"></i>';
  speakBtn.title = "Speak this reply";
  speakBtn.addEventListener("click", async () => {
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
      appendAssistant(UI.messages, String(error?.message || "Text-to-speech failed"), { animate: true });
    }
  });
  actions.appendChild(speakBtn);
  messageNode.appendChild(actions);
}
function createStreamingAssistantMessage() {
  if (!UI.messages) return null;
  const msg = document.createElement("div");
  msg.className = "message assistant";
  const reasoning = document.createElement("div");
  reasoning.className = "assistant-stream-reasoning d-none";
  msg.appendChild(reasoning);
  const body = document.createElement("div");
  body.className = "message-content";
  msg.appendChild(body);
  const meta = document.createElement("div");
  meta.className = "message-time";
  msg.appendChild(meta);
  UI.messages.appendChild(msg);
  scrollToBottom(UI.messages);
  attachAssistantTools(msg, {
    text: "",
    onRun: () => {
      const currentText = msg.querySelector(".message-content")?.textContent || "";
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
      const normalized = String(text || "").trim();
      reasoning.textContent = normalized;
      reasoning.classList.toggle("d-none", !normalized);
      setReasoningBanner(normalized);
      scrollToBottom(UI.messages);
    },
    async finalize({ text, responseMs, responseConfig }) {
      const finalText = await applyResponseConfig(msg, text, { responseConfig });
      lastAssistantReplyText = finalText;
      meta.textContent = responseMs < 1e3 ? `${responseMs}ms` : `${(responseMs / 1e3).toFixed(1)}s`;
      attachSpeechAction(msg, finalText);
      setReasoningBanner("");
    }
  };
}
function renderWelcome() {
  const msg = appendMessage(UI.messages, "assistant", t("default_greeting"));
  attachSpeechAction(msg, t("default_greeting"));
}
function renderHistory() {
  if (!UI.messages) return;
  UI.messages.querySelectorAll(".message").forEach((node) => node.remove());
  if (!chatHistory.length) {
    renderWelcome();
    return;
  }
  chatHistory.forEach((row) => {
    const msg = appendMessage(UI.messages, row.role, row.text, { ts: row.ts, responseMs: row.responseMs });
    if (row.role === "assistant") {
      attachSpeechAction(msg, row.text);
    }
  });
}
function buildSystemPrompt() {
  const mode = assistantPrefs.mode;
  const selectedModel = getSelectedChatModel() || "auto";
  return [
    "You are BroxAdmin Assistant, a bilingual admin-panel helper for the BroxBhai dashboard.",
    "Your name is Brox.",
    `BroxLab website: ${ASSISTANT_SITE_URL}.`,
    `Current UI language: ${currentLang === "bn" ? "Bangla" : "English"}.`,
    `Current admin page: ${window.location.pathname}.`,
    `Current AI mode: ${mode}.`,
    `Selected chat model: ${selectedModel}.`,
    "Keep replies concise and practical.",
    "Prefer the current UI language unless the user clearly asks for another language.",
    "If asked your name, answer as Brox and mention BroxLab clearly.",
    "If asked about yourself or broxlab.online, describe briefly and include the site URL.",
    mode === "assistant" ? "Use the admin_action tool when the user wants navigation, admin mutations, analytics data, media links, log viewing, or a fresh chat." : "Stay in direct AI mode unless the user explicitly asks to switch back to admin tool mode.",
    mode === "assistant" ? "When the user asks to add/create/edit/update content, send them to the relevant admin form with prefilled fields." : "When coding help is requested in Codex mode, prefer code-first answers with concrete snippets or diffs.",
    "Ask a follow-up question instead of calling the tool when details are missing.",
    "Use at most one admin_action call per turn unless another is required.",
    "",
    "LOG MONITORING CAPABILITIES:",
    "You can view and report application logs using: view_error_logs, view_all_logs, view_log_stats, get_recent_errors actions.",
    'When admin asks "show errors", "check logs", "view error log", etc., use the appropriate log viewing action.',
    "The system actively monitors for new errors and will alert the admin when they occur. You are also aware of these alerts.",
    "Always respond to error-related questions by fetching and displaying relevant log information."
  ].join("\n");
}
async function buildMessages(userText, { defaultUserText = "" } = {}) {
  if (hasUploadedContextFiles()) {
    await uploadContextAttachments();
  }
  const normalizedText = String(userText || "").trim() || defaultUserText;
  const fileParts = contextAttachments.map((attachment) => {
    const puterPath = String(attachment?.puterPath || "").trim();
    if (!puterPath) return null;
    return {
      type: "file",
      puter_path: puterPath
    };
  }).filter(Boolean);
  const userContent = fileParts.length ? [
    ...fileParts,
    ...normalizedText ? [{ type: "text", text: normalizedText }] : []
  ] : normalizedText;
  return [
    { role: "system", content: buildSystemPrompt() },
    ...chatHistory.map((row) => ({ role: row.role, content: row.text })),
    { role: "user", content: userContent }
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
  const tools = shouldUseWebSearch() ? [{ type: "web_search" }] : [];
  const stream = await getChatClient(getSelectedChatModel()).chatWithFallback(
    messages,
    buildRuntimeOptions({ stream: true, includeTools: tools.length > 0, tools })
  );
  let text = "";
  let reasoning = "";
  for await (const part of stream) {
    if (typeof part?.reasoning === "string") {
      reasoning += part.reasoning;
      streamMessage?.setReasoning(reasoning);
    }
    if (typeof part?.text === "string") {
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
  const imageUrl = String(UI.imageUrlInput?.value || "").trim();
  const hasUploadedFiles = hasUploadedContextFiles();
  const contentPrompt = String(prompt || "").trim() || "Analyze this image in detail for an admin workflow.";
  if (!hasUploadedFiles && !imageUrl) {
    throw new Error("Attach an image or file, or provide an image URL first.");
  }
  if (hasUploadedFiles) {
    const messages = await buildMessages(contentPrompt, {
      defaultUserText: "Analyze these uploaded files in detail for this admin workflow."
    });
    if (shouldUseStream()) {
      return runStreamedConversation(messages);
    }
    const response2 = await getChatClient(model).chatWithFallback(
      messages,
      buildRuntimeOptions({ includeTools: false })
    );
    return { text: extractResponseText(response2) || t("status_error"), responseMs: 0 };
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
    let text = "";
    let reasoning = "";
    for await (const part of stream) {
      if (typeof part?.reasoning === "string") {
        reasoning += part.reasoning;
        streamMessage?.setReasoning(reasoning);
      }
      if (typeof part?.text === "string") {
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
  return { text: extractResponseText(response) || t("status_error"), responseMs: 0 };
}
async function handleImageGeneration(prompt) {
  const normalizedPrompt = String(prompt || "").trim();
  if (!normalizedPrompt) {
    throw new Error("Write an image prompt first.");
  }
  appendMessage(UI.messages, "user", `Generate image: ${normalizedPrompt}`);
  const image = await generateImage(normalizedPrompt, {
    model: assistantPrefs.imageModel || DEFAULT_IMAGE_MODEL,
    aspectRatio: assistantPrefs.imageAspect || "1:1"
  });
  const html = [
    '<div class="assistant-image-card">',
    `<img src="${image.src}" alt="Generated image">`,
    `<a href="${image.src}" target="_blank" rel="noopener noreferrer">Open image</a>`,
    "</div>"
  ].join("");
  const msg = await appendAssistant(UI.messages, html, { animate: true, trustedHtml: true });
  attachSpeechAction(msg, `Generated image for prompt: ${normalizedPrompt}`);
  lastAssistantReplyText = `Generated image for prompt: ${normalizedPrompt}`;
}
async function speakLastReply() {
  if (!lastAssistantReplyText) {
    throw new Error("No assistant reply available to speak yet.");
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
  const id = params?.id ? String(params.id).trim() : "";
  let base = "";
  switch (action) {
    case "create_mobile":
      base = "/admin/mobiles/insert";
      break;
    case "edit_mobile":
      base = id ? `/admin/mobiles/update/${encodeURIComponent(id)}` : "";
      break;
    case "create_post":
      base = "/admin/posts/create";
      break;
    case "edit_post":
      base = "/admin/posts/edit";
      break;
    case "create_page":
      base = "/admin/pages/create";
      break;
    case "edit_page":
      base = "/admin/pages/edit";
      break;
    case "create_service":
      base = "/admin/services/create";
      break;
    case "edit_service":
      base = id ? `/admin/services/${encodeURIComponent(id)}/edit` : "";
      break;
    default:
      base = "";
  }
  if (!base) return "";
  const query = new URLSearchParams();
  query.set("assistant_prefill", "1");
  if ((action === "edit_post" || action === "edit_page") && id) {
    query.set("id", id);
  }
  Object.entries(params || {}).forEach(([key, value]) => {
    if (key === "id" || value === void 0 || value === null || value === "") return;
    if (Array.isArray(value)) {
      value.filter(Boolean).forEach((v) => query.append(`${key}[]`, String(v)));
      return;
    }
    query.append(key, typeof value === "boolean" ? value ? "1" : "0" : String(value));
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
  if (name !== "admin_action") return false;
  const rawArgs = toolCall?.function?.arguments || toolCall?.arguments || "{}";
  let args = {};
  if (typeof rawArgs === "string") {
    try {
      args = JSON.parse(rawArgs);
    } catch {
      args = {};
    }
  } else if (rawArgs && typeof rawArgs === "object") {
    args = rawArgs;
  }
  const action = String(args.action || "").trim();
  const params = args.params || {};
  if (!action) return false;
  if (!hasPermission(ACTION_PERMISSIONS[action])) {
    appendAssistant(UI.messages, t("notice_unknown_tool"));
    return true;
  }
  if (action === "start_new_chat") {
    resetChat({ noticeKey: "notice_new_chat" });
    return true;
  }
  if (REDIRECT_ACTIONS.has(action)) {
    const url = buildRedirectUrl(action, params);
    if (url) {
      setStatus("status_response");
      window.location.href = url;
      appendAssistant(UI.messages, t("status_navigating"), { animate: true });
    }
    return true;
  }
  if (LINK_ACTIONS[action]) {
    const link = LINK_ACTIONS[action];
    appendAssistant(UI.messages, `${t("status_response")}: ${link.label}`);
    window.location.href = link.url;
    return true;
  }
  if (action === "view_error_logs") {
    handleViewErrorLogs(params);
    return true;
  }
  if (action === "view_all_logs") {
    handleViewAllLogs(params);
    return true;
  }
  if (action === "view_log_stats") {
    handleViewLogStats();
    return true;
  }
  if (action === "get_recent_errors") {
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
  const inputGroup = UI.chat?.querySelector(".input-group");
  if (!inputGroup || inputGroup.querySelector(".assistant-action-strip")) return;
  const actionStrip = document.createElement("div");
  actionStrip.className = "assistant-action-strip";
  ["btnNewChat", "btnUploadFiles", "btnViewUploads"].forEach((id) => {
    const btn = document.getElementById(id);
    if (btn) {
      btn.classList.add("assistant-action-btn");
      actionStrip.appendChild(btn);
    }
  });
  inputGroup.insertBefore(actionStrip, inputGroup.firstChild);
  const manageChats = document.getElementById("btnManageChats");
  if (manageChats) manageChats.remove();
}
function setAssistantInteractivity(enabled) {
  if (!UI.wrapper) return;
  if (!enabled && UI.wrapper.contains(document.activeElement) && document.activeElement instanceof HTMLElement) {
    document.activeElement.blur();
  }
  UI.wrapper.toggleAttribute("inert", !enabled);
  if (!enabled) {
    UI.chat?.classList.add("hidden");
    UI.chat?.classList.add("d-none");
  }
}
function syncAssistantWithModalState() {
  const hasVisibleModal = Array.from(document.querySelectorAll(".modal.show")).some((modal) => !UI.wrapper?.contains(modal));
  setAssistantInteractivity(!hasVisibleModal);
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
  UI.toggleBtn?.addEventListener("click", () => {
    const isClosed = UI.chat?.classList.contains("hidden") || UI.chat?.classList.contains("d-none");
    if (isClosed) {
      syncModePreset("assistant", { preserveModel: true });
      UI.advancedPanel?.classList.add("d-none");
      UI.input?.focus?.();
    }
    UI.chat?.classList.toggle("hidden");
    UI.chat?.classList.toggle("d-none");
  });
  UI.closeBtn?.addEventListener("click", () => {
    UI.chat?.classList.add("hidden");
    UI.chat?.classList.add("d-none");
  });
  UI.sendBtn?.addEventListener("click", handleUserMessage);
  UI.input?.addEventListener("keypress", (e) => {
    if (e.key === "Enter") handleUserMessage();
  });
  UI.settingsToggle?.addEventListener("click", () => {
    UI.advancedPanel?.classList.toggle("d-none");
  });
  UI.modeSelect?.addEventListener("change", (event) => {
    syncModePreset(String(event.target.value || "assistant"));
    setStatus("status_ready_to_connect");
  });
  UI.modelSelect?.addEventListener("change", (event) => {
    assistantPrefs.model = String(event.target.value || "").trim();
    saveAssistantPrefs();
  });
  UI.streamToggle?.addEventListener("change", (event) => {
    assistantPrefs.stream = Boolean(event.target.checked);
    saveAssistantPrefs();
  });
  UI.webSearchToggle?.addEventListener("change", (event) => {
    assistantPrefs.webSearch = Boolean(event.target.checked);
    saveAssistantPrefs();
  });
  UI.temperatureInput?.addEventListener("input", (event) => {
    assistantPrefs.temperature = Number(event.target.value || DEFAULT_PREFS.temperature);
    updateTemperatureLabel();
    saveAssistantPrefs();
  });
  UI.maxTokensInput?.addEventListener("change", (event) => {
    assistantPrefs.maxTokens = Number(event.target.value || DEFAULT_PREFS.maxTokens);
    saveAssistantPrefs();
  });
  UI.reasoningSelect?.addEventListener("change", (event) => {
    assistantPrefs.reasoningEffort = String(event.target.value || "");
    saveAssistantPrefs();
  });
  UI.verbositySelect?.addEventListener("change", (event) => {
    assistantPrefs.textVerbosity = String(event.target.value || "medium");
    saveAssistantPrefs();
  });
  UI.imageModelSelect?.addEventListener("change", (event) => {
    assistantPrefs.imageModel = String(event.target.value || DEFAULT_IMAGE_MODEL);
    saveAssistantPrefs();
  });
  UI.imageAspectSelect?.addEventListener("change", (event) => {
    assistantPrefs.imageAspect = String(event.target.value || "1:1");
    saveAssistantPrefs();
  });
  UI.ttsVoiceSelect?.addEventListener("change", (event) => {
    assistantPrefs.ttsVoice = String(event.target.value || "alloy");
    saveAssistantPrefs();
  });
  document.getElementById("btnNewChat")?.addEventListener("click", () => resetChat({ noticeKey: "notice_new_chat" }));
  document.getElementById("btnUploadFiles")?.addEventListener("click", () => UI.fileInput?.click());
  document.getElementById("btnViewUploads")?.addEventListener("click", () => {
    window.location.href = "/admin/media";
  });
  UI.attachVisionBtn?.addEventListener("click", () => UI.visionInput?.click());
  UI.fileInput?.addEventListener("change", (event) => {
    addContextAttachments(event.target?.files || []);
    event.target.value = "";
    updateAttachmentChip();
  });
  UI.visionInput?.addEventListener("change", (event) => {
    addContextAttachments(event.target?.files || []);
    event.target.value = "";
    updateAttachmentChip();
  });
  UI.imageUrlInput?.addEventListener("input", updateAttachmentChip);
  UI.generateImageBtn?.addEventListener("click", async () => {
    try {
      await ensurePuterReady({ interactive: false, t: (key) => t(key) });
      await handleImageGeneration(String(UI.input?.value || "").trim());
      if (UI.input) UI.input.value = "";
      setStatus("status_ready");
    } catch (error) {
      appendAssistant(UI.messages, String(error?.message || t("status_error")), { animate: true });
      setStatus("status_error");
    }
  });
  UI.speakLastReplyBtn?.addEventListener("click", async () => {
    try {
      await ensurePuterReady({ interactive: false, t: (key) => t(key) });
      await speakLastReply();
    } catch (error) {
      appendAssistant(UI.messages, String(error?.message || "Text-to-speech failed"), { animate: true });
    }
  });
  document.addEventListener("show.bs.modal", (event) => {
    if (UI.wrapper?.contains(event.target)) return;
    setAssistantInteractivity(false);
  }, true);
  document.addEventListener("hidden.bs.modal", (event) => {
    if (UI.wrapper?.contains(event.target)) return;
    syncAssistantWithModalState();
  }, true);
}
async function handleUserMessage() {
  const text = String(UI.input?.value || "").trim();
  const imageUrl = String(UI.imageUrlInput?.value || "").trim();
  const hasFileContext = hasUploadedContextFiles();
  const hasContextInput = hasContextInputs();
  const shouldUseVisionTransport = Boolean(imageUrl) || assistantPrefs.mode === "vision" && hasFileContext;
  if (!text && !hasContextInput) return;
  const { history, expired } = historyStore.load();
  if (expired) {
    chatHistory = [];
    appendAssistant(UI.messages, t("notice_chat_reset"));
  } else {
    chatHistory = history;
  }
  if (text.startsWith("/image ")) {
    UI.input.value = "";
    try {
      await ensurePuterReady({ interactive: false, t: (key) => t(key) });
      await handleImageGeneration(text.slice(7));
      setStatus("status_ready");
    } catch (error) {
      await appendAssistant(UI.messages, String(error?.message || t("status_error")), { animate: true });
      setStatus("status_error");
    }
    return;
  }
  UI.input.value = "";
  const ts = (/* @__PURE__ */ new Date()).toISOString();
  const attachmentSummary = buildAttachmentSummary();
  const userMessageText = hasContextInput ? `${text || "Use these uploaded files as context."}${attachmentSummary ? `
${attachmentSummary}` : ""}` : text;
  chatHistory.push({ role: "user", text: userMessageText, ts });
  historyStore.save(chatHistory);
  appendMessage(UI.messages, "user", userMessageText, { ts });
  const staticReply = !hasContextInput ? getStaticReply(text, currentLang) : null;
  if (staticReply && assistantPrefs.mode === "assistant") {
    const staticNode = await appendAssistant(UI.messages, staticReply, { animate: true });
    attachSpeechAction(staticNode, staticReply);
    lastAssistantReplyText = staticReply;
    historyStore.save(chatHistory);
    return;
  }
  setTyping(true);
  setStatus("status_thinking");
  const started = performance.now();
  setReasoningBanner("");
  try {
    const useBackendAI = !shouldUseAdminTools() && !shouldUseWebSearch() && !hasContextInput && assistantPrefs.mode !== "vision";
    let backendError = null;
    await ensurePuterReady({ interactive: false, t: (key) => t(key) });
    const hasFireworksKey = Boolean(window.FIREWORKS_API_KEY && window.FIREWORKS_API_KEY !== "your_api_key_here");
    const hasOpenRouterKey = Boolean(window.OPENROUTER_API_KEY && window.OPENROUTER_API_KEY !== "your_api_key_here");
    const isProviderConfigured = assistantPrefs.provider === "fireworks" && hasFireworksKey || assistantPrefs.provider === "openrouter" && hasOpenRouterKey;
    if (isProviderConfigured) {
      try {
        const messages2 = [
          {
            role: "system",
            content: "You are Brox Assistant. Always use typing animation for responses. Provide concise, helpful answers. At the end of your response, add suggestions in the format [SUGGESTION: option1, option2] for follow-up actions."
          },
          ...chatHistory.map((row) => ({ role: row.role, content: row.text })),
          { role: "user", content: userMessageText }
        ];
        let model, response2;
        if (assistantPrefs.provider === "openrouter") {
          model = assistantPrefs.model && assistantPrefs.model.includes("/") ? assistantPrefs.model : "openai/gpt-5.2";
          response2 = await callOpenRouterAI(messages2, { stream: false, model });
        } else {
          model = assistantPrefs.model || "accounts/fireworks/models/deepseek-v3p1";
          response2 = await callFireworksAI(messages2, { stream: false, model });
        }
        const aiText2 = extractResponseText(response2) || t("status_error");
        const { text: cleanText, suggestions } = parseSuggestionsFromText(aiText2);
        const responseMs2 = Math.max(0, Math.round(performance.now() - started));
        const usedModel = model.replace("accounts/fireworks/models/", "").replace("openai/", "");
        const msg2 = await appendAssistant(UI.messages, cleanText, {
          animate: true,
          responseMs: responseMs2,
          config: { suggestions },
          model: usedModel,
          tools: true,
          onRun: (text2) => {
            UI.input.value = text2;
            handleUserMessage();
          }
        });
        const finalText2 = await applyResponseConfig(msg2, cleanText);
        attachSpeechAction(msg2, finalText2);
        lastAssistantReplyText = finalText2;
        chatHistory.push({ role: "assistant", text: finalText2, ts: (/* @__PURE__ */ new Date()).toISOString(), responseMs: responseMs2 });
        historyStore.save(chatHistory);
        await cleanupUploadedContextAttachments();
        clearAttachmentState({ clearUrl: true });
        setStatus("status_ready");
        return;
      } catch (providerErr) {
        console.log("Settings provider failed, falling back to Puter:", providerErr.message);
      }
    } else {
      console.log("No API key configured for " + assistantPrefs.provider + ", using Puter.js directly");
    }
    if (shouldUseVisionTransport) {
      const result = await runVisionConversation(text);
      const responseMs2 = result.responseMs || Math.max(0, Math.round(performance.now() - started));
      if (!shouldUseStream()) {
        const msg2 = await appendAssistant(UI.messages, result.text, { animate: true, responseMs: responseMs2, model: "Puter AI" });
        attachSpeechAction(msg2, result.text);
      }
      chatHistory.push({ role: "assistant", text: result.text, ts: (/* @__PURE__ */ new Date()).toISOString(), responseMs: responseMs2 });
      historyStore.save(chatHistory);
      lastAssistantReplyText = result.text;
      await cleanupUploadedContextAttachments();
      clearAttachmentState({ clearUrl: true });
      setStatus("status_ready");
      return;
    }
    const messages = await buildMessages(text, {
      defaultUserText: hasContextInput ? "Use the uploaded files as context for this request." : ""
    });
    if (shouldUseStream()) {
      const result = await runStreamedConversation(messages);
      chatHistory.push({ role: "assistant", text: result.text, ts: (/* @__PURE__ */ new Date()).toISOString(), responseMs: result.responseMs });
      historyStore.save(chatHistory);
      lastAssistantReplyText = result.text;
      await cleanupUploadedContextAttachments();
      clearAttachmentState({ clearUrl: true });
      setStatus("status_ready");
      return;
    }
    const tools = [];
    if (shouldUseWebSearch()) {
      tools.push({ type: "web_search" });
    }
    if (shouldUseAdminTools()) {
      tools.push({ type: "function", function: buildAdminToolSchema() });
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
        setStatus("status_ready");
        return;
      }
    }
    const aiText = extractResponseText(response) || t("status_error");
    const responseMs = Math.max(0, Math.round(performance.now() - started));
    chatHistory.push({ role: "assistant", text: aiText, ts: (/* @__PURE__ */ new Date()).toISOString(), responseMs });
    historyStore.save(chatHistory);
    const msg = await appendAssistant(UI.messages, aiText, {
      animate: true,
      responseMs,
      model: "Puter AI",
      tools: true,
      onRun: (text2) => {
        UI.input.value = text2;
        handleUserMessage();
      }
    });
    const finalText = await applyResponseConfig(msg, aiText);
    attachSpeechAction(msg, finalText);
    lastAssistantReplyText = finalText;
    await cleanupUploadedContextAttachments();
    clearAttachmentState({ clearUrl: true });
    setStatus("status_ready");
  } catch (err) {
    const msg = String(err?.message || t("status_error"));
    const errorNode = await appendAssistant(UI.messages, msg, { animate: true });
    attachSpeechAction(errorNode, msg);
    lastAssistantReplyText = msg;
    setStatus(msg, { raw: true });
  } finally {
    setTyping(false);
    setReasoningBanner("");
    historyStore.updateActivity();
  }
}
function buildAdminToolSchema() {
  return {
    name: "admin_action",
    description: "Run a Brox admin panel action such as opening a prefilled create/edit form, deleting content, reading analytics, opening admin pages, or starting a new chat.",
    parameters: {
      type: "object",
      properties: {
        action: {
          type: "string",
          enum: [
            "create_post",
            "edit_post",
            "delete_post",
            "create_service",
            "edit_service",
            "delete_service",
            "create_page",
            "edit_page",
            "delete_page",
            "create_category",
            "edit_category",
            "delete_category",
            "create_tag",
            "edit_tag",
            "delete_tag",
            "create_mobile",
            "edit_mobile",
            "delete_mobile",
            "manage_service_applications",
            "manage_payments",
            "create_user",
            "delete_user",
            "create_role",
            "delete_role",
            "send_notification",
            "get_analytics_summary",
            "get_visitor_stats",
            "get_top_content",
            "upload_file",
            "view_uploads",
            "manage_chats",
            "start_new_chat",
            "view_error_logs",
            "view_all_logs",
            "view_log_stats",
            "get_recent_errors"
          ]
        },
        params: {
          type: "object",
          additionalProperties: true,
          description: "Parameters for the action; include fields to prefill forms."
        }
      },
      required: ["action"]
    }
  };
}
function applyAssistantPrefillFromQuery() {
  const url = new URL(window.location.href);
  if (url.searchParams.get("assistant_prefill") !== "1") return;
  url.searchParams.delete("assistant_prefill");
  url.searchParams.forEach((value, key) => {
    const field = document.querySelector(`[name="${CSS.escape(key)}"]`);
    if (!field) return;
    if (field.type === "checkbox") {
      field.checked = value === "1" || value === "true" || value === "on";
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
        role: "system",
        content: prompt
      },
      {
        role: "user",
        content: "Please enhance this content according to the instructions provided."
      }
    ];
    const response = await getChatClient().chatWithFallback(enhancementMessages, {
      includeTools: false
    });
    const enhancedText = extractResponseText(response) || "";
    if (!enhancedText || enhancedText.trim().length === 0) {
      throw new Error("AI returned empty response");
    }
    let cleanedText = enhancedText.trim();
    if (cleanedText.startsWith("```html")) {
      cleanedText = cleanedText.slice(7);
    } else if (cleanedText.startsWith("```")) {
      cleanedText = cleanedText.slice(3);
    }
    if (cleanedText.endsWith("```")) {
      cleanedText = cleanedText.slice(0, -3);
    }
    cleanedText = cleanedText.trim();
    return {
      enhanced: cleanedText,
      success: true
    };
  } catch (error) {
    console.error("Content enhancement error:", error);
    return {
      error: error.message || "Enhancement failed",
      success: false
    };
  }
}
async function handleViewErrorLogs(params = {}) {
  try {
    const lines = params?.lines || 30;
    const response = await fetch(`/api/admin/logs/read?file=errors.log&lines=${lines}`, {
      credentials: "include"
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();
    if (data.error) {
      appendAssistant(UI.messages, `\u274C Error reading logs: ${data.error}`);
      return;
    }
    const entries = data.entries || [];
    if (entries.length === 0) {
      appendAssistant(UI.messages, "\u2705 No errors found in the log file. Everything looks good!");
      return;
    }
    let logText = `\u{1F4CB} **Error Log** (${data.file})
Size: ${data.file_size_display} | Last updated: ${data.last_modified}

`;
    logText += `Found **${entries.length}** recent error(s):

`;
    entries.slice(0, 5).forEach((entry, idx) => {
      logText += `**#${idx + 1}** [${entry.severity}] ${entry.timestamp}
`;
      logText += `${entry.message}
`;
      if (entry.context) {
        logText += `\`\`\`
${entry.context.substring(0, 200)}...
\`\`\`
`;
      }
      logText += "\n";
    });
    if (entries.length > 5) {
      logText += `... and ${entries.length - 5} more error(s).
`;
    }
    appendAssistant(UI.messages, logText);
  } catch (error) {
    appendAssistant(UI.messages, `\u274C Failed to fetch error logs: ${error.message}`);
  }
}
async function handleGetRecentErrors(params = {}) {
  try {
    const limit = params?.limit || 10;
    const response = await fetch(`/api/admin/logs/errors?limit=${limit}`, {
      credentials: "include"
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();
    if (!data.errors || data.errors.length === 0) {
      appendAssistant(UI.messages, "\u2705 No recent errors. The application is running smoothly.");
      return;
    }
    let logText = `\u26A0\uFE0F **Recent Errors** (Found ${data.count})

`;
    data.errors.slice(0, 5).forEach((err, idx) => {
      logText += `**${idx + 1}. [${err.severity}] ${err.timestamp}**
`;
      logText += `${err.message}

`;
    });
    if (data.count > 5) {
      logText += `... and ${data.count - 5} more.
`;
    }
    appendAssistant(UI.messages, logText);
  } catch (error) {
    console.error("Error fetching recent errors:", error);
    appendAssistant(UI.messages, `\u274C Could not fetch recent errors: ${error.message}`);
  }
}
async function handleViewAllLogs(params = {}) {
  try {
    const response = await fetch("/api/admin/logs", {
      credentials: "include"
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();
    const logs = data.logs || [];
    if (logs.length === 0) {
      appendAssistant(UI.messages, "No log files found in the storage directory.");
      return;
    }
    let logText = `\u{1F4C2} **Available Log Files** (${logs.length})

`;
    logs.forEach((log, idx) => {
      logText += `**${idx + 1}. ${log.name}**
`;
      logText += `   Size: ${log.size_display} | Lines: ${log.lines} | Modified: ${log.modified}

`;
    });
    appendAssistant(UI.messages, logText);
  } catch (error) {
    appendAssistant(UI.messages, `\u274C Failed to list logs: ${error.message}`);
  }
}
async function handleViewLogStats() {
  try {
    const response = await fetch("/api/admin/logs/stats", {
      credentials: "include"
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();
    const stats = data.stats || {};
    let logText = `\u{1F4CA} **Log Statistics**

`;
    logText += `Total Size: **${stats.total_size_display || "0 B"}**
`;
    logText += `Total Lines: **${stats.total_lines || 0}**
`;
    logText += `Error Count: **${stats.error_count || 0}**
`;
    logText += `Warnings: **${stats.warning_count || 0}**

`;
    if (stats.files) {
      logText += `**Individual Files:**
`;
      Object.entries(stats.files).forEach(([filename, fileStats]) => {
        logText += `- ${filename}: ${fileStats.size_display} (${fileStats.lines} lines)
`;
      });
    }
    appendAssistant(UI.messages, logText);
  } catch (error) {
    appendAssistant(UI.messages, `\u274C Failed to get log statistics: ${error.message}`);
  }
}
async function loadAssistantPrefs() {
  try {
    const response = await fetch("/api/ai-settings/frontend");
    if (response.ok) {
      const data = await response.json();
      assistantPrefs.provider = data.provider;
      assistantPrefs.model = data.model;
      localStorage.setItem("brox.assistant.prefs", JSON.stringify(assistantPrefs));
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
  if (historyExpired) appendAssistant(UI.messages, t("notice_session_expired"));
  bindEvents();
  initQuickActionBar();
  updateAttachmentChip();
  applyAssistantPrefillFromQuery();
  syncAssistantWithModalState();
  setStatus("status_ready_to_connect");
  populateModelOptions();
  window.enhanceContentWithAI = enhanceContentWithAI;
  logMonitor.onLogUpdate((eventType, data) => {
    if (eventType === "errors" && data && data.length > 0) {
      const errorCount = data.length;
      let alertMsg = `\u{1F6A8} **${errorCount} new error(s) detected!**

`;
      data.slice(0, 3).forEach((err, idx) => {
        alertMsg += `**Error ${idx + 1}:** [${err.severity}] ${err.message}
`;
      });
      if (errorCount > 3) {
        alertMsg += `
... and ${errorCount - 3} more errors.
`;
      }
      alertMsg += `
Type "show errors" to view all error logs.`;
      appendAssistant(UI.messages, alertMsg, { animate: true });
    }
  });
  logMonitor.startPolling();
}
init();

// public_html/assets/ai-assistant/bootstrap/admin-assistant.js
ensureAssistantStyles(new URL("../styles/assistant-ui.css", import.meta.url).href);
