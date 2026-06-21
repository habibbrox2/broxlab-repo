/**
 * assistant-runtime.js
 * Wires up the AI assistant chat UI - input, send, close, status, suggestions.
 * Detects admin vs public role from data-ai-role on the root element.
 * Supports SSE streaming and non-streaming fallback.
 */
/* global hljs */

const API_ENDPOINTS = {
  admin: '/api/admin/ai/chat',
  public: '/api/ai/chat',
};

function getMeta(name) {
  const el = document.querySelector(`meta[name="${name}"]`);
  return el ? el.content : '';
}

function escapeHtml(str) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(String(str || '')));
  return d.innerHTML;
}

/**
 * Lightweight markdown-to-HTML renderer.
 * Handles: headings, bold, italic, inline code, code blocks,
 * tables, links, lists, blockquotes, horizontal rules, citations [N].
 * Output is XSS-safe (only known HTML tags are emitted).
 */
function renderMarkdown(text) {
  if (!text) return '';
  const lines = text.split('\n');
  const htmlLines = [];
  let i = 0;

  function escapeInline(t) {
    return t
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function renderInline(str) {
    // HTML-escape first to prevent XSS from AI output containing <> chars
    str = escapeInline(str);
    // Bold
    str = str.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    // Italic
    str = str.replace(/\*(.+?)\*/g, '<em>$1</em>');
    // Inline code (must happen before link processing)
    str = str.replace(/`([^`]+)`/g, '<code>$1</code>');
    // Citations [1], [2-3]
    str = str.replace(/\[(\d+)(?:-\d+)?\]/g, '<sup class="ai-msg-citation">[$1]</sup>');
    // Links [text](url)
    str = str.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
    // Strikethrough
    str = str.replace(/~~(.+?)~~/g, '<del>$1</del>');
    return str;
  }

  while (i < lines.length) {
    const line = lines[i];

    // ── Code block ──
    if (/^```(\w*)/.test(line)) {
      const lang = line.match(/^```(\w*)/)[1];
      const codeLines = [];
      i++;
      while (i < lines.length && !/^```/.test(lines[i])) {
        codeLines.push(escapeInline(lines[i]));
        i++;
      }
      i++; // skip closing ```
      const code = codeLines.join('\n');
      const langLabel = lang ? `<span class="ai-code-lang">${escapeHtml(lang)}</span>` : '';
      const langClass = lang ? ` class="language-${escapeHtml(lang)}"` : '';
      htmlLines.push(
        '<div class="ai-code-block" data-hljs>' +
        `<div class="ai-code-header">${langLabel}` +
        '<button class="ai-code-copy" data-code-copy><i class="lucide lucide-clipboard"></i> Copy</button></div>' +
        `<pre><code${langClass}>${code}</code></pre></div>`
      );
      continue;
    }

    // ── Table ──
    if (/^\|.+/ .test(line) && i + 1 < lines.length && /^\|[\s:-]+\|/.test(lines[i + 1])) {
      const headerCells = line.split('|').filter(Boolean).map((c) => { return c.trim(); });
      i += 2; // skip separator row
      const rows = [];
      while (i < lines.length && /^\|/.test(lines[i])) {
        const cells = lines[i].split('|').filter(Boolean).map((c) => { return renderInline(c.trim()); });
        rows.push(`<tr><td>${ cells.join('</td><td>') }</td></tr>`);
        i++;
      }
      htmlLines.push(
        '<div class="ai-msg-table-wrap"><table class="ai-msg-table">' +
        `<thead><tr><th>${ headerCells.join('</th><th>') }</th></tr></thead>` +
        `<tbody>${ rows.join('') }</tbody></table></div>`
      );
      continue;
    }

    // ── Horizontal rule ──
    if (/^\s*[-*_]{3,}\s*$/.test(line)) {
      htmlLines.push('<hr>');
      i++;
      continue;
    }

    // ── Heading ──
    const headingMatch = line.match(/^(#{1,4})\s+(.+)/);
    if (headingMatch) {
      const level = headingMatch[1].length;
      htmlLines.push(`<h${ level }>${ renderInline(headingMatch[2]) }</h${ level }>`);
      i++;
      continue;
    }

    // ── Blockquote ──
    if (/^>\s/.test(line)) {
      const quoteLines = [];
      while (i < lines.length && /^>\s/.test(lines[i])) {
        quoteLines.push(renderInline(lines[i].replace(/^>\s*/, '')));
        i++;
      }
      htmlLines.push(`<blockquote>${ quoteLines.join('<br>') }</blockquote>`);
      continue;
    }

    // ── Unordered list ──
    if (/^\s*[-*+]\s/.test(line)) {
      const listItems = [];
      while (i < lines.length && /^\s*[-*+]\s/.test(lines[i])) {
        listItems.push(`<li>${ renderInline(lines[i].replace(/^\s*[-*+]\s*/, '')) }</li>`);
        i++;
      }
      htmlLines.push(`<ul>${ listItems.join('') }</ul>`);
      continue;
    }

    // ── Ordered list ──
    if (/^\s*\d+\.\s/.test(line)) {
      const listItems = [];
      while (i < lines.length && /^\s*\d+\.\s/.test(lines[i])) {
        listItems.push(`<li>${ renderInline(lines[i].replace(/^\s*\d+\.\s*/, '')) }</li>`);
        i++;
      }
      htmlLines.push(`<ol>${ listItems.join('') }</ol>`);
      continue;
    }

    // ── Empty line ──
    if (!line.trim()) {
      i++;
      continue;
    }

    // ── Paragraph ──
    htmlLines.push(`<p>${ renderInline(line) }</p>`);
    i++;
  }

  return htmlLines.join('\n');
}

/**
 * Creates HTML for the message action toolbar (copy, regenerate, etc.)
 */
function makeActionToolbar() {
  return '<div class="ai-msg-actions">' +
    '<button class="ai-msg-action-btn" data-action="copy" title="Copy response" aria-label="Copy response"><i class="lucide lucide-copy"></i></button>' +
    '<button class="ai-msg-action-btn" data-action="regenerate" title="Regenerate" aria-label="Regenerate"><i class="lucide lucide-refresh-cw"></i></button>' +
    '<button class="ai-msg-action-btn" data-action="summarize" title="Summarize" aria-label="Summarize"><i class="lucide lucide-text"></i></button>' +
    '<button class="ai-msg-action-btn" data-action="star" title="Star this response" aria-label="Star this response"><i class="lucide lucide-star"></i></button>' +
    '</div>';
}

function autoResize(el) {
  el.style.height = 'auto';
  const max = parseInt(el.style.maxHeight, 10) || 144;
  el.style.height = `${Math.min(el.scrollHeight, max)}px`;
}

function SSEParser() {
  this.buffer = '';
}

SSEParser.prototype.feed = function (chunk) {
  this.buffer += chunk;
  const events = [];
  const parts = this.buffer.split('\n');
  this.buffer = parts.pop() || '';
  for (let i = 0; i < parts.length; i++) {
    const line = parts[i];
    if (line.indexOf('data: ') !== 0) continue;
    const data = line.slice(6);
    if (data === '[DONE]') {
      events.push({ done: true, });
      continue;
    }
    try {
      events.push(JSON.parse(data));
    } catch (e) { /* ignore malformed SSE lines */ }
  }
  return events;
};

const state = {
  submitting: false,
  conversationId: null,
  messages: [],
  visitorToken: null,
  settings: {
    provider: 'default',
    model: 'default',
    webSearch: true,
    autoSave: true,
  },
};

/**
 * Get or create a persistent visitor token for the public assistant.
 * Stored in localStorage so the user is identified across page loads.
 */
function getVisitorToken() {
  try {
    let token = localStorage.getItem('broxAssistantVisitorToken');
    if (!token) {
      token = `visitor_${ Date.now() }_${ Math.random().toString(36).slice(2, 10)}`;
      localStorage.setItem('broxAssistantVisitorToken', token);
    }
    return token;
  } catch (e) {
    return `visitor_${ Date.now() }_${ Math.random().toString(36).slice(2, 10)}`;
  }
}

function loadSettingsFromStorage() {
  try {
    const stored = localStorage.getItem('broxAssistantSettings');
    if (stored) {
      const parsed = JSON.parse(stored);
      Object.assign(state.settings, parsed);
      return state.settings;
    }
  } catch (e) {
    console.warn('[Assistant] Failed to load settings from localStorage:', e);
  }
  return state.settings;
}

function saveSettingsToStorage() {
  try {
    localStorage.setItem('broxAssistantSettings', JSON.stringify(state.settings));
  } catch (e) {
    console.warn('[Assistant] Failed to save settings to localStorage:', e);
  }
}

function syncSettingsFromModal(els) {
  if (!els.settingsModal) return;

  const providerSelect = els.settingsModal.querySelector('select[aria-label="Select AI provider"]');
  const modelSelect = els.settingsModal.querySelector('select[aria-label="Select AI model"]');
  const webSearchCheckbox = els.settingsModal.querySelector('input[aria-label="Enable web search"]');
  const autoSaveCheckbox = els.settingsModal.querySelector('input[aria-label="Auto-save conversations"]');

  if (providerSelect) {
    providerSelect.addEventListener('change', (e) => {
      state.settings.provider = e.target.value;
      saveSettingsToStorage();
      trackEngagement('settings_changed', { setting: 'provider', value: e.target.value, });
    });
    providerSelect.value = state.settings.provider;
  }

  if (modelSelect) {
    modelSelect.addEventListener('change', (e) => {
      state.settings.model = e.target.value;
      saveSettingsToStorage();
      trackEngagement('settings_changed', { setting: 'model', value: e.target.value, });
    });
    modelSelect.value = state.settings.model;
  }

  if (webSearchCheckbox) {
    webSearchCheckbox.addEventListener('change', (e) => {
      state.settings.webSearch = e.target.checked;
      saveSettingsToStorage();
      trackEngagement('settings_changed', { setting: 'webSearch', value: e.target.checked, });
    });
    webSearchCheckbox.checked = state.settings.webSearch;
  }

  if (autoSaveCheckbox) {
    autoSaveCheckbox.addEventListener('change', (e) => {
      state.settings.autoSave = e.target.checked;
      saveSettingsToStorage();
      trackEngagement('settings_changed', { setting: 'autoSave', value: e.target.checked, });
    });
    autoSaveCheckbox.checked = state.settings.autoSave;
  }
}

/**
 * Engagement Metrics — Track user interactions for analytics
 */
function trackEngagement(event, data = {}) {
  try {
    // Send to Google Analytics if available
    // eslint-disable-next-line no-undef
    if (typeof gtag !== 'undefined') gtag('event', event, data);

    // Optional: Send to custom analytics API
    // fetch('/api/analytics/track', {
    //   method: 'POST',
    //   headers: { 'Content-Type': 'application/json' },
    //   body: JSON.stringify({ event, timestamp: new Date().toISOString(), ...data }),
    // }).catch(() => {});

    // Console log for debugging (can be filtered by event)
    console.debug('[Analytics]', event, data);
  } catch (e) {
    console.warn('[Analytics] Tracking failed:', e);
  }
}

/**
 * Toast Notifications — Show temporary feedback messages
 */
function showToast(root, message, type = 'info', duration = 3000) {
  const toast = document.createElement('div');
  const bgColor = type === 'error' ? 'bg-red-500/90' : type === 'success' ? 'bg-emerald-500/90' : 'bg-blue-500/90';
  const icon = type === 'error' ? 'lucide-alert-circle' : type === 'success' ? 'lucide-check-circle' : 'lucide-info';
  toast.className = `fixed bottom-4 right-4 z-50 flex items-center gap-3 rounded-lg ${bgColor} px-4 py-3 text-sm text-white shadow-lg animate-bounce`;
  toast.innerHTML = `<i class="lucide ${icon} text-sm"></i><span>${escapeHtml(message)}</span>`;
  root.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = 'fadeOut 300ms ease-out forwards';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

function resolveElements(root) {
  const role = root.dataset.aiRole || 'public';
  const isAdmin = role === 'admin';
  const inputArea = root.querySelector('[data-input-area]');
  const textarea = root.querySelector('textarea[aria-label="Message input"]');
  const sendBtn = inputArea
    ? inputArea.querySelector('button[aria-label="Send"]')
    : root.querySelector('button[aria-label="Send"]');
  const messagesContainer = root.querySelector('[role="log"]');
  const preChat = root.querySelector('[data-prechat]');
  const statusBar = root.querySelector('[data-status-bar]');
  const statusDetail = root.querySelector('[data-status-detail]');
  const shell = root.querySelector('[role="dialog"]');
  const triggerBtn = shell
    ? document.querySelector(`[aria-controls="${shell.id}"]`)
    : null;
  const settingsToggleBtn = root.querySelector('[data-settings-toggle]');
  const settingsModal = root.querySelector('[data-settings-modal]');
  const settingsCloseButtons = root.querySelectorAll('[data-settings-close]');
  const allBtns = root.querySelectorAll('header button, [class*="header"] button');
  const closeBtns = [];
  const minimizeBtns = [];
  let newChatBtn = null;
  const deleteConvBtn = root.querySelector('[data-delete-conv]');
  const voiceBtn = root.querySelector('[data-voice-input]');
  const historyList = root.querySelector('[data-history-list]');
  const historyToggle = root.querySelector('[data-history-toggle]');
  const attachBtn = root.querySelector('[data-attach-btn]');
  const fileInput = root.querySelector('[data-file-input]');
  const adminProviderSelect = root.querySelector('[data-admin-provider-select]');
  const adminModelSelect = root.querySelector('[data-admin-model-select]');
  const adminRefreshModels = root.querySelector('[data-admin-refresh-models]');
  for (let i = 0; i < allBtns.length; i++) {
    const label = (allBtns[i].getAttribute('aria-label') || '').toLowerCase();
    if (label.indexOf('close') === 0 && label !== 'close settings') closeBtns.push(allBtns[i]);
    if (label === 'minimize') minimizeBtns.push(allBtns[i]);
    if (label === 'new chat') newChatBtn = allBtns[i];
  }
  const suggestionChips = root.querySelectorAll('[data-prompt]');
  const actionChips = root.querySelectorAll('[data-action-chip]');
  const shortcuts = root.querySelector('[aria-label="Quick shortcuts"]');
  let charCountSpan = null;
  if (shortcuts) {
    const spans = shortcuts.querySelectorAll('span');
    for (let i = 0; i < spans.length; i++) {
      const txt = (spans[i].textContent || '').trim();
      if (/^\d+ chars?$/i.test(txt)) {
        charCountSpan = spans[i];
        break;
      }
    }
  }
  return {
    root,
    role,
    isAdmin,
    inputArea,
    textarea,
    sendBtn,
    messagesContainer,
    preChat,
    statusBar,
    statusDetail,
    shell,
    triggerBtn,
    closeBtns,
    minimizeBtns,
    suggestionChips,
    actionChips,
    charCountSpan,
    settingsToggleBtn,
    settingsModal,
    settingsCloseButtons,
    newChatBtn,
    deleteConvBtn,
    historyList,
    historyToggle,
    voiceBtn,
    attachBtn,
    fileInput,
    adminProviderSelect,
    adminModelSelect,
    adminRefreshModels,
  };
}

/**
 * Fetch recent conversation history for the admin sidebar.
 */
function fetchConversationHistory() {
  return fetch('/api/admin/ai/conversations?limit=50')
    .then((r) => {
      if (!r.ok) throw new Error(`Failed to fetch history (HTTP ${ r.status })`);
      return r.json();
    })
    .then((data) => {
      if (!data.success) throw new Error(data.error || 'API error');
      return data.conversations || [];
    });
}

/**
 * Render conversation history items into the admin sidebar.
 */
function renderAdminHistorySidebar(els, conversations) {
  if (!els.historyList) return;

  const emptyEl = els.historyList.querySelector('[data-history-empty]');
  const errorEl = els.historyList.querySelector('[data-history-error]');

  // Remove existing list items (keep the empty + error state elements)
  const existingItems = els.historyList.querySelectorAll('[data-history-item]');
  for (let i = 0; i < existingItems.length; i++) {
    existingItems[i].remove();
  }
  // Clear old active state
  clearActiveHistoryItem(els);

  if (!conversations || !conversations.length) {
    if (emptyEl) emptyEl.classList.remove('hidden');
    if (errorEl) errorEl.classList.add('hidden');
    return;
  }

  if (emptyEl) emptyEl.classList.add('hidden');
  if (errorEl) errorEl.classList.add('hidden');

  for (let j = 0; j < conversations.length; j++) {
    const conv = conversations[j];
    const item = document.createElement('button');
    item.setAttribute('type', 'button');
    item.setAttribute('data-history-item', '');
    item.setAttribute('data-conversation-id', conv.id);
    item.className = 'w-full rounded-2xl border border-white/10 bg-white/[0.04] px-3 py-3 text-left text-sm text-slate-200 transition hover:bg-white/10 hover:text-white mb-1';

    // Title or fallback
    const title = conv.title || `Conversation #${ conv.id}`;
    const lastText = conv.last_text || '';
    const msgCount = conv.message_count || 0;
    const timeAgo = conv.updated_at ? formatTimeAgo(conv.updated_at) : '';

    item.innerHTML =
      '<div class="flex items-start justify-between gap-2">' +
        `<span class="block truncate font-medium text-sm">${ escapeHtml(title) }</span>${
          timeAgo ? `<span class="shrink-0 text-[10px] text-slate-500">${ timeAgo }</span>` : ''
        }</div>${
          lastText ? `<span class="block mt-1 truncate text-xs text-slate-400">${ escapeHtml(lastText.substring(0, 80)) }</span>` : ''
        }<span class="mt-1 inline-flex items-center gap-1 text-[10px] text-slate-500">` +
        `<i class="lucide lucide-message-square text-[10px]"></i> ${ msgCount } msgs` +
      '</span>';

    els.historyList.appendChild(item);
  }
}

/**
 * Highlight the active history item and remove highlights from others.
 */
function clearActiveHistoryItem(els) {
  if (!els.historyList) return;
  const active = els.historyList.querySelector('[data-history-item].active');
  if (active) active.classList.remove('active');
}

function setActiveHistoryItem(els, conversationId) {
  if (!els.historyList) return;
  clearActiveHistoryItem(els);
  const item = els.historyList.querySelector(`[data-history-item][data-conversation-id="${ conversationId }"]`);
  if (item) item.classList.add('active');
}

/**
 * Format an ISO date string as a relative time (e.g. "2h ago", "3d ago").
 */
function formatTimeAgo(isoStr) {
  try {
    const now = new Date();
    const date = new Date(isoStr);
    const diffMs = now - date;
    if (diffMs < 0) return '';
    const mins = Math.floor(diffMs / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins }m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours }h ago`;
    const days = Math.floor(hours / 24);
    if (days < 30) return `${days }d ago`;
    const months = Math.floor(days / 30);
    if (months < 12) return `${months }mo ago`;
    return `${Math.floor(months / 12) }y ago`;
  } catch (e) {
    return '';
  }
}

/**
 * Load a conversation's messages via the export API and display them.
 */
function loadAdminConversation(els, conversationId) {
  if (!conversationId) return;
  showToast(els.shell || els.root, 'Loading conversation...', 'info', 3000);

  fetch(`/api/admin/ai/conversations/export?conversation_id=${ conversationId}`)
    .then((r) => {
      if (!r.ok) throw new Error(`Failed to load (HTTP ${ r.status })`);
      return r.json();
    })
    .then((data) => {
      if (!data.success) throw new Error(data.error || 'API error');

      // Populate state and DOM
      state.conversationId = conversationId;
      state.messages = [];

      const msgs = data.messages || [];
      // Filter out system messages for display, keep user + assistant
      const displayMsgs = [];
      for (let i = 0; i < msgs.length; i++) {
        if (msgs[i].role === 'user' || msgs[i].role === 'assistant') {
          displayMsgs.push({
            role: msgs[i].role,
            content: msgs[i].content,
            sentAt: msgs[i].created_at,
          });
        }
      }

      if (displayMsgs.length) {
        state.messages = displayMsgs;
        restoreMessagesToDOM(els, {
          messages: displayMsgs,
          conversationId: conversationId,
          updatedAt: data.conversation ? data.conversation.updated_at : null,
        });
      } else {
        // Empty conversation — start fresh with that ID
        hidePreChat(els);
        els.messagesContainer.innerHTML = '';
        showToast(els.shell || els.root, 'Conversation has no messages', 'info', 3000);
      }
    })
    .catch((err) => {
      showToast(els.shell || els.root, `Failed to load conversation: ${ err.message || 'Unknown error'}`, 'error', 4000);
    });
}

/**
 * Fetch active AI providers and current settings for the admin sidebar.
 */
function fetchAdminProviders() {
  return fetch('/api/ai-system/frontend')
    .then((r) => {
      if (!r.ok) throw new Error(`Failed to fetch providers (HTTP ${ r.status })`);
      return r.json();
    })
    .then((data) => {
      return {
        providers: data.providers || [],
        currentProvider: data.provider || '',
        currentModel: data.model || '',
        backendProvider: data.backend_provider || data.provider || '',
        backendModel: data.backend_model || data.model || '',
      };
    });
}

/**
 * Fetch models for a specific provider.
 */
function fetchAdminModels(providerName, refresh) {
  let url = `/api/ai/models?provider=${ encodeURIComponent(providerName)}`;
  if (refresh) url += '&refresh=1';
  return fetch(url)
    .then((r) => {
      if (!r.ok) throw new Error(`Failed to fetch models (HTTP ${ r.status })`);
      return r.json();
    })
    .then((data) => {
      return data.models || [];
    });
}

/**
 * Populate a <select> element with options from an array.
 */
function populateAdminSelect(select, items, valueKey, labelKey, currentValue) {
  if (!select) return;

  // Clear existing options
  select.innerHTML = '';

  if (!items || !items.length) {
    // Add a placeholder option
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = 'No options available';
    opt.disabled = true;
    select.appendChild(opt);
    return;
  }

  for (let i = 0; i < items.length; i++) {
    const item = items[i];
    const val = item[valueKey];
    const label = item[labelKey];
    if (val === undefined || val === null) continue;
    const opt = document.createElement('option');
    opt.value = val;
    opt.textContent = label || val;
    if (String(val) === String(currentValue)) {
      opt.selected = true;
    }
    select.appendChild(opt);
  }
}

/**
 * Populate the model select from models array (with id/name keys).
 */
function populateAdminModelSelect(select, models, currentModel) {
  if (!select) return;
  select.innerHTML = '';

  if (!models || !models.length) {
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = 'No models available';
    opt.disabled = true;
    select.appendChild(opt);
    return;
  }

  for (let i = 0; i < models.length; i++) {
    const m = models[i];
    const opt = document.createElement('option');
    opt.value = m.id;
    opt.textContent = m.name || m.id;
    if (m.default || (currentModel && String(m.id) === String(currentModel))) {
      opt.selected = true;
    }
    select.appendChild(opt);
  }
}

/**
 * Initialize admin sidebar settings — populate provider/model selects from API.
 */
function initAdminSidebarSettings(els) {
  if (!els.isAdmin || !els.adminProviderSelect || !els.adminModelSelect) return;

  fetchAdminProviders().then((data) => {
    const providers = data.providers;
    // Admin uses backend provider/model from settings
    const currentProvider = data.backendProvider || data.currentProvider;
    const currentModel = data.backendModel || data.currentModel;

    // Populate provider select
    populateAdminSelect(els.adminProviderSelect, providers, 'provider_name', 'display_name', currentProvider);

    // Store the current provider in state
    state.settings.provider = currentProvider;
    state.settings.model = currentModel;

    // Fetch and populate models for the current provider
    return fetchAdminModels(currentProvider).then((models) => {
      populateAdminModelSelect(els.adminModelSelect, models, currentModel);
    });
  }).catch((err) => {
    console.warn('[Assistant] Failed to load admin provider settings:', err);
    // Show fallback options in selects
    const fallbackOpt = document.createElement('option');
    fallbackOpt.value = '';
    fallbackOpt.textContent = 'Settings unavailable';
    fallbackOpt.disabled = true;
    if (els.adminProviderSelect) {
      els.adminProviderSelect.innerHTML = '';
      els.adminProviderSelect.appendChild(fallbackOpt.cloneNode(true));
    }
    if (els.adminModelSelect) {
      els.adminModelSelect.innerHTML = '';
      els.adminModelSelect.appendChild(fallbackOpt.cloneNode(true));
    }
  });

  // When provider changes, refresh the model list
  els.adminProviderSelect.addEventListener('change', () => {
    const selectedProvider = els.adminProviderSelect.value;
    if (!selectedProvider) return;

    state.settings.provider = selectedProvider;

    // Show loading state
    els.adminModelSelect.innerHTML = '';
    const loadingOpt = document.createElement('option');
    loadingOpt.value = '';
    loadingOpt.textContent = 'Loading models...';
    loadingOpt.disabled = true;
    els.adminModelSelect.appendChild(loadingOpt);

    fetchAdminModels(selectedProvider).then((models) => {
      populateAdminModelSelect(els.adminModelSelect, models, state.settings.model);
      trackEngagement('admin_provider_changed', { provider: selectedProvider, modelCount: models.length, });
    }).catch((err) => {
      console.warn(`[Assistant] Failed to load models for ${ selectedProvider }:`, err);
      els.adminModelSelect.innerHTML = '';
      const errOpt = document.createElement('option');
      errOpt.value = '';
      errOpt.textContent = 'Failed to load models';
      errOpt.disabled = true;
      els.adminModelSelect.appendChild(errOpt);
    });
  });

  // Track model selection changes
  els.adminModelSelect.addEventListener('change', () => {
    state.settings.model = els.adminModelSelect.value;
    trackEngagement('admin_model_changed', { model: els.adminModelSelect.value, });
  });

  // Refresh models button
  if (els.adminRefreshModels) {
    els.adminRefreshModels.addEventListener('click', () => {
      const selectedProvider = els.adminProviderSelect.value;
      if (!selectedProvider) return;

      // Show loading state on button
      const originalHtml = els.adminRefreshModels.innerHTML;
      els.adminRefreshModels.innerHTML = '<svg class="ai-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:1em;height:1em"><circle cx="12" cy="12" r="10"/></svg> Refreshing...';
      els.adminRefreshModels.disabled = true;

      fetchAdminModels(selectedProvider, true).then((models) => {
        populateAdminModelSelect(els.adminModelSelect, models, els.adminModelSelect.value);
        trackEngagement('admin_models_refreshed', { provider: selectedProvider, modelCount: models.length, });
        showToast(els.shell || els.root, `Models refreshed (${ models.length } available)`, 'success', 2000);
      }).catch((err) => {
        console.warn('[Assistant] Failed to refresh models:', err);
        showToast(els.shell || els.root, 'Failed to refresh models', 'error', 3000);
      }).finally(() => {
        els.adminRefreshModels.innerHTML = originalHtml;
        els.adminRefreshModels.disabled = false;
      });
    });
  }
}

/**
 * Call the public delete conversation API.
 * Sends visitorToken as a custom header; falls back to query param.
 */
function callDeleteConversation(conversationId) {
  return fetch(`/api/ai/conversations/${ conversationId}`, {
    method: 'DELETE',
    headers: {
      'X-Visitor-Token': state.visitorToken || '',
    },
  }).then((res) => {
    return res.json().then((data) => {
      if (!res.ok || !data.success) {
        throw new Error(data.error || `Delete failed (HTTP ${ res.status })`);
      }
      return data;
    });
  });
}

function sendMessage(els, userText) {
  const endpoint = els.isAdmin ? API_ENDPOINTS.admin : API_ENDPOINTS.public;
  const messagesPayload = [];
  for (let i = 0; i < state.messages.length; i++) {
    messagesPayload.push({
      role: state.messages[i].role,
      content: state.messages[i].content,
    });
  }
  messagesPayload.push({ role: 'user', content: userText, });
  const body = { messages: messagesPayload, stream: true, };
  if (state.conversationId) body.conversation_id = state.conversationId;
  if (!els.isAdmin) {
    body.csrf_token = getMeta('csrf-token');
    // Send visitor token so the backend can identify/link conversations
    if (state.visitorToken) {
      body.visitorToken = state.visitorToken;
    }
  } else {
    // Include provider/model overrides from the admin sidebar settings
    if (els.adminProviderSelect && els.adminProviderSelect.value) {
      body.provider = els.adminProviderSelect.value;
    }
    if (els.adminModelSelect && els.adminModelSelect.value) {
      body.model = els.adminModelSelect.value;
    }
  }
  return fetch(endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', },
    body: JSON.stringify(body),
  }).then((response) => {
    if (!response.ok) {
      return response.json().then((errBody) => {
        throw new Error(errBody.error || `Request failed (HTTP ${response.status})`);
      }).catch(() => {
        throw new Error(`Request failed (HTTP ${response.status})`);
      });
    }
    const ct = response.headers.get('Content-Type') || '';
    if (ct.indexOf('text/event-stream') !== -1) {
      return consumeSSEStream(response, els);
    }
    return response.json().then((data) => {
      if (!data.success) throw new Error(data.error || 'AI error');
      return { content: data.content || '', conversationId: data.conversation_id || null, };
    });
  });
}

function consumeSSEStream(response, els) {
  const reader = response.body.getReader();
  const decoder = new TextDecoder();
  const parser = new SSEParser();
  let fullContent = '';
  let resultMeta = {};
  function pump() {
    return reader.read().then((result) => {
      if (result.done) {
        const rem = parser.feed('\n');
        for (let i = 0; i < rem.length; i++) {
          if (rem[i].content) fullContent += rem[i].content;
        }
        return { content: fullContent, conversationId: resultMeta.conversation_id || null, };
      }
      const text = decoder.decode(result.value, { stream: true, });
      const events = parser.feed(text);
      for (let i = 0; i < events.length; i++) {
        const evt = events[i];
        if (evt.done) break;
        if (evt.meta) { resultMeta = evt.meta; continue; }
        if (evt.content) {
          fullContent += evt.content;
          updateAssistantMessage(els, fullContent);
        }
      }
      return pump();
    }).catch((err) => {
      console.error('[Assistant] SSE stream error:', err);
      return { content: fullContent, conversationId: resultMeta.conversation_id || null, error: err.message, };
    });
  }
  return pump();
}

function addUserMessage(els, text) {
  const group = document.createElement('div');
  group.className = 'ai-msg-group';
  const time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false, });
  group.innerHTML = `<div class="ai-msg-row user"><div class="ai-msg-bubble user" data-msg-container><div data-msg-output>${escapeHtml(text)}</div></div></div><div class="ai-msg-time">${time}</div>` +
    '<div class="ai-msg-row assistant"><div class="ai-msg-bubble assistant" data-msg-container><div data-msg-output><div class="ai-msg-content"></div></div></div></div><div class="ai-msg-time"></div>';
  els.messagesContainer.appendChild(group);
  els._lastUserGroup = group;
  scrollToBottom(els);
}

function scrollToBottom(els) {
  requestAnimationFrame(() => {
    els.messagesContainer.scrollTop = els.messagesContainer.scrollHeight;
  });
}

function showThinking(els) {
  if (els.statusBar) els.statusBar.classList.remove('hidden');
  if (els.statusDetail) els.statusDetail.textContent = 'Thinking...';
  const div = document.createElement('div');
  div.className = 'ai-thinking';
  div.setAttribute('data-thinking-msg', '');
  div.innerHTML = '<div class="ai-thinking-dots"><span class="ai-thinking-dot"></span><span class="ai-thinking-dot"></span><span class="ai-thinking-dot"></span></div><span class="ai-thinking-text">Thinking</span>';
  els.messagesContainer.appendChild(div);
  scrollToBottom(els);
}

function hideThinking(els) {
  if (els.statusBar) {
    if (els.isAdmin) {
      // Admin: reset status text without hiding the always-visible status strip
      if (els.statusDetail) els.statusDetail.textContent = 'Ready';
    } else {
      // Public: hide the status bar after thinking completes
      els.statusBar.classList.add('hidden');
    }
  }
  const el = els.messagesContainer.querySelector('[data-thinking-msg]');
  if (el) el.remove();
}

function getAssistantOutputEl(els) {
  let el = els.messagesContainer.querySelector('[data-msg-container]:last-child [data-msg-output]');
  if (!el) {
    const w = els.messagesContainer.querySelector('[data-thinking-msg]');
    if (w) {
      w.className = 'ai-msg-group';
      w.innerHTML = '<div class="ai-msg-row assistant"><div class="ai-msg-bubble assistant" data-msg-container><div data-msg-output></div></div></div>';
      el = w.querySelector('[data-msg-output]');
    }
  }
  return el;
}

/**
 * Typewriter streaming animation state.
 * Buffers full text from SSE and renders incrementally via requestAnimationFrame.
 */
const _twState = {
  els: null,
  fullContent: '',
  displayedLength: 0,
  rafId: null,
  active: false,
};

function startTypewriter(els, text) {
  if (!_twState.active) {
    _twState.active = true;
    _twState.els = els;
    _twState.displayedLength = 0;
    _twState.fullContent = text;
    _twState.rafId = requestAnimationFrame(typewriterTick);
    // Add streaming cursor indicator
    const outputEl = getAssistantOutputEl(els);
    if (outputEl && !outputEl.querySelector('.ai-typewriter-cursor')) {
      const cursor = document.createElement('span');
      cursor.className = 'ai-typewriter-cursor';
      cursor.setAttribute('aria-hidden', 'true');
      outputEl.appendChild(cursor);
    }
  } else {
    // Just update the buffer - RAF loop picks it up
    _twState.fullContent = text;
    // Restart RAF if it stalled (typewriter caught up but more SSE content arrived)
    if (!_twState.rafId) {
      _twState.rafId = requestAnimationFrame(typewriterTick);
    }
  }
}

function typewriterTick() {
  const s = _twState;
  if (!s.active || !s.els) return;

  // Advance 3-6 chars per frame for natural feel
  // If buffer is far ahead, advance faster (up to 15 chars)
  const backlog = s.fullContent.length - s.displayedLength;
  const advance = backlog > 100 ? 15 : backlog > 30 ? 8 : 4;
  const charsToShow = Math.min(advance, backlog);

  if (charsToShow <= 0) {
    // Caught up - keep cursor blinking but stop advancing
    s.rafId = null;
    return;
  }

  s.displayedLength += charsToShow;
  const partialText = s.fullContent.substring(0, s.displayedLength);

  const outputEl = getAssistantOutputEl(s.els);
  if (outputEl) {
    // Remove old cursor, render new content, re-append cursor
    const oldCursor = outputEl.querySelector('.ai-typewriter-cursor');
    if (oldCursor) oldCursor.remove();
    outputEl.innerHTML = `<div class="ai-msg-content">${ renderMarkdown(partialText) }</div>`;
    const cursor = document.createElement('span');
    cursor.className = 'ai-typewriter-cursor';
    cursor.setAttribute('aria-hidden', 'true');
    outputEl.appendChild(cursor);
  }

  scrollToBottom(s.els);

  // Continue if more to show
  if (s.displayedLength < s.fullContent.length) {
    s.rafId = requestAnimationFrame(typewriterTick);
  } else {
    s.rafId = null;
  }
}

function flushTypewriter(els) {
  if (!_twState.active) return;
  _twState.active = false;
  if (_twState.rafId) {
    cancelAnimationFrame(_twState.rafId);
    _twState.rafId = null;
  }
  _twState.els = null;
  _twState.fullContent = '';
  _twState.displayedLength = 0;
  // Remove cursor indicator
  const cursor = els.messagesContainer.querySelector('.ai-typewriter-cursor');
  if (cursor) cursor.remove();
}

function updateAssistantMessage(els, text) {
  // Start or continue the typewriter animation
  startTypewriter(els, text);
}

function finalizeAssistantMessage(els, text) {
  hideThinking(els);
  flushTypewriter(els);

  // Find the assistant output container within the last user's group
  let outputEl = null;
  if (els._lastUserGroup) {
    outputEl = els._lastUserGroup.querySelector('[data-msg-container]:last-child [data-msg-output]');
  }
  if (!outputEl) {
    outputEl = els.messagesContainer.querySelector('[data-msg-container]:last-child [data-msg-output]');
  }

  if (outputEl) {
    const time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false, });
    outputEl.innerHTML = `<div class="ai-msg-content">${ renderMarkdown(text) }</div>${ makeActionToolbar()}`;
    // Update the time element in the same group
    const group = outputEl.closest('.ai-msg-group');
    if (group) {
      const timeEls = group.querySelectorAll('.ai-msg-time');
      if (timeEls.length >= 2) {
        timeEls[1].textContent = time;
      }
    }
    scrollToBottom(els);
    highlightCodeBlocks(els.messagesContainer);
    els._lastUserGroup = null;
    return;
  }

  // Fallback: create a new group if no output container found
  const group = document.createElement('div');
  group.className = 'ai-msg-group';
  const time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false, });
  group.innerHTML = `<div class="ai-msg-row assistant"><div class="ai-msg-bubble assistant" data-msg-container><div data-msg-output><div class="ai-msg-content">${renderMarkdown(text)}</div>${makeActionToolbar()}</div></div></div><div class="ai-msg-time">${time}</div>`;
  els.messagesContainer.appendChild(group);
  scrollToBottom(els);
  highlightCodeBlocks(els.messagesContainer);
  els._lastUserGroup = null;
}

function showError(els, msg) {
  const div = document.createElement('div');
  div.className = 'ai-msg-group';
  div.innerHTML = `<div class="ai-msg-error"><strong>Error</strong>${escapeHtml(msg)}</div>`;
  els.messagesContainer.appendChild(div);
  scrollToBottom(els);
  showToast(els.shell || els.root, msg, 'error', 4000);
}

function setInputEnabled(els, enabled) {
  els.textarea.disabled = !enabled;
  els.sendBtn.disabled = !enabled;
  els.sendBtn.classList.toggle('ai-send-btn-loading', !enabled);
  if (!enabled) {
    els.sendBtn.innerHTML = '<svg class="ai-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/></svg>';
  } else {
    els.sendBtn.innerHTML = '<i class="lucide lucide-send"></i>';
    els.textarea.focus();
  }
}

function updateCharCount(/* els */) {
  // Char count display removed in redesign - kept as no-op
}

function hidePreChat(els) {
  if (els.preChat) els.preChat.classList.add('hidden');
}

/**
 * Conversation History Persistence
 * Saves and restores the public assistant conversation to/from localStorage.
 */
function saveConversation() {
  if (!state.messages.length) return;
  try {
    const now = new Date().toISOString();
    // Attach sentAt timestamp to each message for accurate time display on restore
    const messagesWithTime = state.messages.map((msg) => {
      if (!msg.sentAt) {
        msg.sentAt = now;
      }
      return msg;
    });
    localStorage.setItem('broxAssistantConversation', JSON.stringify({
      messages: messagesWithTime,
      conversationId: state.conversationId,
      updatedAt: now,
    }));
  } catch (e) {
    console.warn('[Assistant] Failed to save conversation:', e);
  }
}

function loadConversation() {
  try {
    const stored = localStorage.getItem('broxAssistantConversation');
    if (!stored) return null;
    const parsed = JSON.parse(stored);
    if (!parsed.messages || !parsed.messages.length) return null;
    return parsed;
  } catch (e) {
    console.warn('[Assistant] Failed to load conversation:', e);
    return null;
  }
}

function clearConversation() {
  try {
    localStorage.removeItem('broxAssistantConversation');
  } catch (e) {
    console.warn('[Assistant] Failed to clear conversation:', e);
  }
}

function restoreMessagesToDOM(els, data) {
  if (!data || !data.messages || !data.messages.length) return;

  state.messages = data.messages;
  state.conversationId = data.conversationId || null;

  // Hide pre-chat
  hidePreChat(els);

  // Clear any existing messages
  els.messagesContainer.innerHTML = '';

  // Use the stored updatedAt for timestamps, falling back to current time
  const baseDate = data.updatedAt ? new Date(data.updatedAt) : new Date();

  // Re-render each message pair
  let i = 0;
  while (i < data.messages.length) {
    const userMsg = data.messages[i];
    const assistantMsg = (i + 1 < data.messages.length) ? data.messages[i + 1] : null;

    if (userMsg && userMsg.role === 'user') {
      // Use sentAt if available on each message, otherwise baseDate
      const userTime = userMsg.sentAt ? new Date(userMsg.sentAt) : baseDate;
      const userTimeStr = isNaN(userTime.getTime())
        ? baseDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false, })
        : userTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false, });

      const assistantTimeStr = (function () {
        if (assistantMsg && assistantMsg.sentAt) {
          const d = new Date(assistantMsg.sentAt);
          if (!isNaN(d.getTime())) {
            return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false, });
          }
        }
        return userTimeStr;
      })();

      const group = document.createElement('div');
      group.className = 'ai-msg-group';

      let assistantHtml = '';
      if (assistantMsg && assistantMsg.role === 'assistant') {
        assistantHtml =
          '<div class="ai-msg-row assistant"><div class="ai-msg-bubble assistant" data-msg-container><div data-msg-output>' +
          `<div class="ai-msg-content">${ renderMarkdown(assistantMsg.content) }</div>${
            makeActionToolbar()
          }</div></div></div><div class="ai-msg-time">${ assistantTimeStr }</div>`;
      }

      group.innerHTML =
        `<div class="ai-msg-row user"><div class="ai-msg-bubble user" data-msg-container><div data-msg-output>${
          escapeHtml(userMsg.content)
        }</div></div></div><div class="ai-msg-time">${ userTimeStr }</div>${
          assistantHtml}`;

      els.messagesContainer.appendChild(group);
    } if (assistantMsg && assistantMsg.role === 'assistant') {
      i += 2;
    } else {
      i += 1;
    }
  }

  // Highlight any code blocks in restored messages
  highlightCodeBlocks(els.messagesContainer);
  scrollToBottom(els);
}

function startNewConversation(els) {
  if (!els) return;
  // Clear state
  state.messages = [];
  state.conversationId = null;
  state.submitting = false;

  // Clear storage
  clearConversation();

  // Clear messages container
  els.messagesContainer.innerHTML = '';

  // Show pre-chat if it exists
  if (els.preChat) els.preChat.classList.remove('hidden');

  // Restore visitor token (keep the same identity for consistency)
  if (!els.isAdmin) {
    state.visitorToken = getVisitorToken();
  }

  // Focus textarea
  if (els.textarea) {
    els.textarea.value = '';
    els.textarea.disabled = false;
    if (els.sendBtn) {
      els.sendBtn.disabled = false;
      els.sendBtn.classList.remove('ai-send-btn-loading');
      els.sendBtn.innerHTML = '<i class="lucide lucide-send"></i>';
    }
    els.textarea.focus();
  }
}

function handleSubmit(els) {
  const text = els.textarea.value.trim();
  if (!text || state.submitting) return;
  state.submitting = true;
  setInputEnabled(els, false);
  hidePreChat(els);
  els.textarea.value = '';
  autoResize(els.textarea);
  updateCharCount(els);
  addUserMessage(els, text);
  showThinking(els);

  trackEngagement('message_sent', {
    role: els.role,
    messageLength: text.length,
    conversationId: state.conversationId,
  });

  sendMessage(els, text).then((result) => {
    state.messages.push({ role: 'user', content: text, });
    if (result.content) state.messages.push({ role: 'assistant', content: result.content, });
    if (result.conversationId) state.conversationId = result.conversationId;
    finalizeAssistantMessage(els, result.content || 'No response generated.');
    state.submitting = false;
    setInputEnabled(els, true);
    // Auto-save conversation to localStorage
    if (state.settings.autoSave) {
      saveConversation();
    }
    showToast(els.shell || els.root, 'Response received', 'success', 2000);
    trackEngagement('message_received', {
      role: els.role,
      responseLength: result.content ? result.content.length : 0,
    });
  }).catch((err) => {
    flushTypewriter(els);
    hideThinking(els);
    showError(els, err.message || 'Failed to get a response.');
    state.submitting = false;
    setInputEnabled(els, true);
    trackEngagement('message_error', {
      role: els.role,
      error: err.message,
    });
  });
}

function toggleSettingsModal(els, show) {
  if (!els.settingsModal) return;
  if (show) {
    els.settingsModal.classList.add('open');
  } else {
    els.settingsModal.classList.remove('open');
  }
}

function initSettingsModal(els) {
  if (!els.settingsToggleBtn || !els.settingsModal || !els.settingsCloseButtons) return;

  els.settingsToggleBtn.addEventListener('click', () => {
    toggleSettingsModal(els, true);
    trackEngagement('settings_opened', { role: els.role, });
  });

  for (let i = 0; i < els.settingsCloseButtons.length; i++) {
    els.settingsCloseButtons[i].addEventListener('click', () => {
      toggleSettingsModal(els, false);
      trackEngagement('settings_closed', { role: els.role, });
    });
  }

  els.settingsModal.addEventListener('click', (e) => {
    if (e.target === els.settingsModal) {
      toggleSettingsModal(els, false);
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && els.settingsModal.classList.contains('open')) {
      toggleSettingsModal(els, false);
      trackEngagement('settings_closed_esc', { role: els.role, });
    }
  });

  syncSettingsFromModal(els);
}

function initAssistant(root) {
  const els = resolveElements(root);
  if (!els.textarea || !els.sendBtn || !els.messagesContainer) {
    console.warn('[Assistant Runtime] Missing core elements - skipping.');
    return;
  }

  // Load settings from storage
  loadSettingsFromStorage();
  trackEngagement('assistant_initialized', { role: els.role, });

  // Initialize visitor token for public assistant (identifies user across sessions)
  if (!els.isAdmin) {
    state.visitorToken = getVisitorToken();
  }

  // Restore saved conversation (only for public assistant)
  if (!els.isAdmin) {
    const saved = loadConversation();
    if (saved) {
      restoreMessagesToDOM(els, saved);
    }
  }

  els.sendBtn.addEventListener('click', () => { handleSubmit(els); });

  // ── Delegated handlers for action toolbar & code copy ──
  els.messagesContainer.addEventListener('click', (e) => {
    const target = e.target.closest('[data-action]');
    if (target) {
      const action = target.getAttribute('data-action');
      const group = target.closest('.ai-msg-group');
      const contentEl = group ? group.querySelector('[data-msg-output] .ai-msg-content') : null;
      const fullText = contentEl ? contentEl.textContent || '' : '';

      if (action === 'copy') {
        navigator.clipboard.writeText(fullText).then(() => {
          target.classList.add('copied');
          target.innerHTML = '<i class="lucide lucide-check"></i>';
          setTimeout(() => {
            target.classList.remove('copied');
            target.innerHTML = '<i class="lucide lucide-copy"></i>';
          }, 2000);
        }).catch(() => {
          // Fallback: select text
          if (contentEl) {
            const range = document.createRange();
            range.selectNodeContents(contentEl);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
          }
        });
      } else if (action === 'regenerate') {
        if (els.textarea) {
          // User and assistant are now in the same .ai-msg-group.
          // Find the user text within the same group.
          const userMsgEl = group.querySelector('.ai-msg-row.user [data-msg-output]');
          const userText = userMsgEl ? userMsgEl.textContent || '' : '';
          // Remove the last user+assistant pair from state.messages
          // before sending so the AI doesn't see duplicates
          if (state.messages.length >= 2) {
            state.messages.pop(); // remove last assistant message
            state.messages.pop(); // remove last user message
          }
          // Remove the entire group (contains both user and assistant)
          group.remove();
          if (userText) {
            els.textarea.value = userText;
            autoResize(els.textarea);
            handleSubmit(els);
          }
        }
      } else if (action === 'summarize') {
        els.textarea.value = `Summarize the above: ${ fullText.substring(0, 200)}`;
        autoResize(els.textarea);
        handleSubmit(els);
      } else if (action === 'star') {
        target.classList.toggle('favorited');
      }
      return;
    }

    // Code block copy button
    const codeCopy = target || e.target.closest('[data-code-copy]');
    if (codeCopy) {
      const codeEl = codeCopy.closest('.ai-code-block').querySelector('code');
      const codeText = codeEl ? codeEl.textContent : '';
      navigator.clipboard.writeText(codeText).then(() => {
        codeCopy.innerHTML = '<i class="lucide lucide-check"></i> Copied!';
        setTimeout(() => {
          codeCopy.innerHTML = '<i class="lucide lucide-clipboard"></i> Copy';
        }, 2000);
      }).catch(() => {
        /* ignore clipboard errors */
      });
    }
  });


  els.textarea.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSubmit(els);
    }
  });

  els.textarea.addEventListener('input', () => {
    autoResize(els.textarea);
    updateCharCount(els);
  });

  for (let i = 0; i < els.closeBtns.length; i++) {
    els.closeBtns[i].addEventListener('click', closeAssistantFactory(els));
  }
  for (let i = 0; i < els.minimizeBtns.length; i++) {
    els.minimizeBtns[i].addEventListener('click', closeAssistantFactory(els));
  }

  for (let i = 0; i < els.suggestionChips.length; i++) {
    els.suggestionChips[i].addEventListener('click', function () {
      const p = this.dataset.prompt;
      if (p) {
        els.textarea.value = p;
        autoResize(els.textarea);
        updateCharCount(els);
        trackEngagement('suggestion_clicked', {
          role: els.role,
          suggestion: p.substring(0, 50),
        });
        handleSubmit(els);
      }
    });
  }

  for (let i = 0; i < els.actionChips.length; i++) {
    els.actionChips[i].addEventListener('click', function () {
      const t = (this.textContent || '').trim();
      if (t) {
        els.textarea.value = t;
        autoResize(els.textarea);
        updateCharCount(els);
        trackEngagement('action_clicked', {
          role: els.role,
          action: t.substring(0, 50),
        });
        handleSubmit(els);
      }
    });
  }

  if (els.triggerBtn) {
    els.triggerBtn.addEventListener('click', () => {
      setTimeout(() => { els.textarea.focus(); }, 150);
      trackEngagement('widget_opened', { role: els.role, });
    });
  }

  // New Chat button
  if (els.newChatBtn) {
    els.newChatBtn.addEventListener('click', () => {
      startNewConversation(els);
      trackEngagement('new_conversation', { role: els.role, });
    });
  }

  // Delete conversation button (public only — deletes from server and local)
  if (els.deleteConvBtn) {
    els.deleteConvBtn.addEventListener('click', async () => {
      if (!state.conversationId) return;
      if (!(await window.showConfirm('Delete this conversation? This cannot be undone.'))) return;
      const btn = els.deleteConvBtn;
      btn.disabled = true;
      btn.innerHTML = '<svg class="ai-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/></svg>';
      callDeleteConversation(state.conversationId).then(() => {
        showToast(els.shell || els.root, 'Conversation deleted', 'success', 2000);
        startNewConversation(els);
        trackEngagement('conversation_deleted', { role: els.role, });
      }).catch((err) => {
        showToast(els.shell || els.root, `Delete failed: ${ err.message || 'Unknown error'}`, 'error', 4000);
      }).finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="lucide lucide-trash-2"></i>';
      });
    });
  }

  // Wire up file attach button to hidden file input
  if (els.attachBtn && els.fileInput) {
    els.attachBtn.addEventListener('click', () => {
      els.fileInput.click();
    });

    // Handle file selection — upload to existing endpoint (admin) or advise (public)
    els.fileInput.addEventListener('change', function () {
      const files = this.files;
      if (!files || !files.length) return;
      const file = files[0];

      if (els.isAdmin) {
        // Admin: upload via existing POST /api/admin/ai/upload endpoint
        const formData = new FormData();
        formData.append('file', file);
        formData.append('csrf_token', getMeta('csrf-token'));

        showToast(els.shell || els.root, `Uploading ${ file.name }...`, 'info', 5000);

        fetch('/api/admin/ai/upload', {
          method: 'POST',
          body: formData,
        }).then((r) => { return r.json(); }).then((data) => {
          if (!data.success) throw new Error(data.error || 'Upload failed');
          const url = data.url || '';
          if (url) {
            const isImage = file.type.indexOf('image/') === 0;
            const insertText = isImage ? `![attached](${ url })` : `[${ file.name }](${ url })`;
            els.textarea.value = (els.textarea.value ? `${els.textarea.value }\n` : '') + insertText;
            autoResize(els.textarea);
          }
          showToast(els.shell || els.root, 'File uploaded successfully', 'success', 2000);
          trackEngagement('file_uploaded', { role: els.role, type: file.type, size: file.size, });
        }).catch((err) => {
          showToast(els.shell || els.root, `Upload failed: ${ err.message || 'Unknown error'}`, 'error', 4000);
        });
      } else {
        // Public: show informational toast (file upload is admin-only for now)
        showToast(els.shell || els.root, 'File upload is available in the admin assistant', 'info', 3000);
      }

      // Reset input so the same file can be selected again
      this.value = '';
    });
  }

  // ── Admin Sidebar Settings (provider/model selects) ──
  if (els.isAdmin) {
    initAdminSidebarSettings(els);
  }

  // ── Admin History Sidebar ──
  if (els.isAdmin && els.historyList) {
    // Fetch and populate conversation history
    fetchConversationHistory().then((conversations) => {
      renderAdminHistorySidebar(els, conversations);
    }).catch((err) => {
      console.warn('[Assistant] Failed to fetch history:', err);
      // Show error state
      const emptyEl = els.historyList.querySelector('[data-history-empty]');
      const errorEl = els.historyList.querySelector('[data-history-error]');
      if (emptyEl) emptyEl.classList.add('hidden');
      if (errorEl) errorEl.classList.remove('hidden');
    });

    // Toggle sidebar visibility
    if (els.historyToggle) {
      els.historyToggle.addEventListener('click', () => {
        const sidebar = els.historyList.closest('aside');
        if (sidebar) {
          sidebar.classList.toggle('hidden');
        }
      });
    }

    // Ctrl+H to toggle history sidebar
    document.addEventListener('keydown', (e) => {
      if (e.key === 'h' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        const sidebar = els.historyList.closest('aside');
        if (sidebar) {
          sidebar.classList.toggle('hidden');
        }
        trackEngagement('keyboard_shortcut', { shortcut: 'Ctrl+H', action: 'toggle_history', });
        return;
      }
      // Escape to close history sidebar if open
      if (e.key === 'Escape') {
        const sidebar2 = els.historyList.closest('aside');
        if (sidebar2 && !sidebar2.classList.contains('hidden')) {
          sidebar2.classList.add('hidden');
          trackEngagement('keyboard_shortcut', { shortcut: 'Escape', action: 'close_history', });
        }
      }
    });

    // Delegate click on history items to load a conversation
    els.historyList.addEventListener('click', (e) => {
      const item = e.target.closest('[data-history-item]');
      if (item) {
        const convId = item.getAttribute('data-conversation-id');
        if (convId) {
          const parsedId = parseInt(convId, 10);
          loadAdminConversation(els, parsedId);
          setActiveHistoryItem(els, parsedId);
          const sidebar = els.historyList.closest('aside');
          if (sidebar) sidebar.classList.add('hidden');
          trackEngagement('history_item_clicked', { role: els.role, conversationId: parsedId, });
        }
      }
    });
  }

  initVoiceInput(els);
  initSettingsModal(els);
  updateCharCount(els);
}

/**
 * Voice Input — Web Speech API integration for the public assistant.
 * Uses the browser's SpeechRecognition API if available.
 */
function initVoiceInput(els) {
  if (!els.voiceBtn) return;

  // Check for SpeechRecognition support
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SpeechRecognition) {
    els.voiceBtn.style.display = 'none';
    return;
  }

  let recognition = null;
  let isListening = false;
  let manuallyStopped = false;

  function startListening() {
    if (recognition) {
      try { recognition.abort(); } catch (e) { /* ignore */ }
    }
    manuallyStopped = false;
    recognition = new SpeechRecognition();
    recognition.lang = 'en-US';
    recognition.continuous = false;
    recognition.interimResults = true;
    recognition.maxAlternatives = 1;

    recognition.onresult = function (event) {
      let finalTranscript = '';
      let interimTranscript = '';
      for (let i = event.resultIndex; i < event.results.length; i++) {
        if (event.results[i].isFinal) {
          finalTranscript += event.results[i][0].transcript;
        } else {
          interimTranscript += event.results[i][0].transcript;
        }
      }
      // Show interim + final in textarea for live feedback
      els.textarea.value = finalTranscript + interimTranscript;
      autoResize(els.textarea);
      updateCharCount(els);
    };

    recognition.onend = function () {
      isListening = false;
      els.voiceBtn.classList.remove('ai-voice-listening');
      // Auto-submit only when recognition ends naturally (user stopped speaking),
      // not when the user manually clicked the mic to stop
      if (!manuallyStopped && els.textarea.value.trim()) {
        handleSubmit(els);
      }
      manuallyStopped = false;
    };

    recognition.onerror = function (event) {
      isListening = false;
      els.voiceBtn.classList.remove('ai-voice-listening');
      if (event.error !== 'aborted' && event.error !== 'no-speech') {
        showToast(els.shell || els.root, `Voice input: ${ event.error}`, 'error', 3000);
      }
    };

    els.voiceBtn.classList.add('ai-voice-listening');
    isListening = true;
    try {
      recognition.start();
    } catch (e) {
      els.voiceBtn.classList.remove('ai-voice-listening');
      isListening = false;
    }
  }

  function stopListening() {
    manuallyStopped = true;
    if (recognition) {
      try { recognition.stop(); } catch (e) { /* ignore */ }
    }
    els.voiceBtn.classList.remove('ai-voice-listening');
    isListening = false;
  }

  els.voiceBtn.addEventListener('click', () => {
    if (isListening) {
      stopListening();
    } else {
      startListening();
    }
  });
}

function closeAssistantFactory(els) {
  return function () {
    if (els.shell) {
      els.shell.classList.add('ai-hidden');
      // Remove .hidden if previously applied (backward compat for admin)
      els.shell.classList.remove('hidden');
    }
    if (els.triggerBtn) {
      els.triggerBtn.setAttribute('aria-expanded', 'false');
      const oi = els.triggerBtn.querySelector('[data-icon="open"]');
      const ci = els.triggerBtn.querySelector('[data-icon="close"]');
      if (oi) oi.classList.remove('hidden');
      if (ci) ci.classList.add('hidden');
    }
  };
}

/**
 * On-demand highlight.js loader.
 * Detects code blocks with [data-hljs] and loads highlight.js only when needed.
 */
let _hljsLoading = false;
let _hljsQueue = [];

function loadHighlightJs(callback) {
  if (typeof hljs !== 'undefined') {
    if (callback) callback();
    return;
  }
  if (_hljsLoading) {
    if (callback) _hljsQueue.push(callback);
    return;
  }
  _hljsLoading = true;
  if (callback) _hljsQueue.push(callback);

  // Load CSS
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css';
  document.head.appendChild(link);

  // Load JS
  const script = document.createElement('script');
  script.src = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js';
  script.onload = function () {
    _hljsLoading = false;
    const q = _hljsQueue;
    _hljsQueue = [];
    for (let i = 0; i < q.length; i++) q[i]();
  };
  script.onerror = function () {
    _hljsLoading = false;
    console.warn('[Assistant] Failed to load highlight.js');
  };
  document.head.appendChild(script);
}

function highlightCodeBlocks(container) {
  const blocks = container.querySelectorAll('[data-hljs] code[class*="language-"]');
  if (!blocks.length) return;
  loadHighlightJs(() => {
    blocks.forEach((el) => {
      hljs.highlightElement(el);
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const roots = document.querySelectorAll('[data-ai-role]');
  for (let i = 0; i < roots.length; i++) {
    initAssistant(roots[i]);
  }
});
