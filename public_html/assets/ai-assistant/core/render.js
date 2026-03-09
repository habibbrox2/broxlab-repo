import { scrollToBottom } from './dom.js';

const TYPEWRITER_CHUNK_DELAY_MS = 16;
const TYPEWRITER_MAX_STEPS = 90;

export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

export function linkify(text) {
  const escaped = escapeHtml(text);
  return escaped
    .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>')
    .replace(/\n/g, '<br>');
}

export function formatBody(text, trustedHtml = false) {
  return trustedHtml ? String(text ?? '') : linkify(String(text ?? ''));
}

export function formatMeta({ role, ts, responseMs, lang = 'en', model }) {
  const parts = [];
  const locale = lang === 'bn' ? 'bn-BD' : 'en-US';
  if (ts) {
    const dt = new Date(ts);
    if (!Number.isNaN(dt.getTime())) {
      parts.push(new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit' }).format(dt));
    }
  }
  if (model) {
    parts.push(`🤖 ${model}`);
  }
  if (role === 'assistant' && Number.isFinite(responseMs)) {
    const duration = responseMs < 1000 ? `${responseMs}ms` : `${(responseMs / 1000).toFixed(1)}s`;
    parts.push(duration);
  }
  return parts.join(' • ');
}

export async function animateBody(node, text, { trustedHtml = false, append = false } = {}) {
  if (!node) return;
  const normalized = String(text ?? '');
  if (!normalized) {
    node.innerHTML = '';
    return;
  }

  if (trustedHtml || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
    node.innerHTML = formatBody(append ? (node.textContent || '') + normalized : normalized, trustedHtml);
    scrollToBottom(node.parentElement);
    return;
  }

  const base = append ? String(node.textContent || '') : '';
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

export function appendMessage(container, role, text, { ts = new Date().toISOString(), responseMs = null, trustedHtml = false, model } = {}) {
  if (!container) return null;
  const msg = document.createElement('div');
  msg.className = `message ${role}`;
  const body = document.createElement('div');
  body.className = 'message-content';
  body.innerHTML = formatBody(text, trustedHtml);
  msg.appendChild(body);

  const meta = formatMeta({ role, ts, responseMs, model });
  if (meta) {
    const metaDiv = document.createElement('div');
    metaDiv.className = 'message-time';
    metaDiv.textContent = meta;
    msg.appendChild(metaDiv);
  }

  container.appendChild(msg);
  scrollToBottom(container);
  return msg;
}

export function typeMessage(node, text, { speed = 30 } = {}) {
  if (!node) return Promise.resolve();
  return new Promise((resolve) => {
    node.innerHTML = '';
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

export function parseResponseConfig(text) {
  const result = { config: null, content: text };
  if (typeof text !== 'string') return result;

  const trimmed = text.trimStart();
  if (!trimmed.startsWith('---')) return result;

  const parts = trimmed.split(/\r?\n/);
  let inHeader = false;
  const headerLines = [];
  let i = 0;
  for (; i < parts.length; i += 1) {
    const line = parts[i];
    if (i === 0 && line.trim() === '---') {
      inHeader = true;
      continue;
    }
    if (inHeader && line.trim() === '---') {
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
    if (trimmedLine === '' || trimmedLine.startsWith('#')) continue;
    const match = trimmedLine.match(/^([a-zA-Z0-9_]+):\s*(.*)$/);
    if (match) {
      currentKey = match[1];
      const value = match[2] ?? '';
      if (value === '') {
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
  result.content = parts.slice(i).join('\n').trim();
  return result;
}

export function attachAssistantTools(message, { text, onRun } = {}) {
  if (!message) return;
  const tools = document.createElement('div');
  tools.className = 'assistant-message-tools';

  // Copy button
  const copyBtn = document.createElement('button');
  copyBtn.type = 'button';
  copyBtn.className = 'assistant-message-tool-btn';
  copyBtn.title = 'Copy reply';
  copyBtn.textContent = '⧉';
  copyBtn.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(text);
      copyBtn.textContent = '✔';
      setTimeout(() => { copyBtn.textContent = '⧉'; }, 1200);
    } catch {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
    }
  });
  tools.appendChild(copyBtn);

  // Run button
  if (typeof onRun === 'function') {
    const runBtn = document.createElement('button');
    runBtn.type = 'button';
    runBtn.className = 'assistant-message-tool-btn';
    runBtn.title = 'Run as new prompt';
    runBtn.textContent = '⟳';
    runBtn.addEventListener('click', () => onRun(text));
    tools.appendChild(runBtn);
  }

  // Expand/collapse button
  const expandBtn = document.createElement('button');
  expandBtn.type = 'button';
  expandBtn.className = 'assistant-message-tool-btn';
  expandBtn.title = 'Toggle expand';
  expandBtn.textContent = '⤢';
  expandBtn.addEventListener('click', () => {
    message.classList.toggle('assistant-expanded');
  });
  tools.appendChild(expandBtn);

  message.appendChild(tools);
}

export async function appendAssistant(container, text, opts = {}) {
  const animate = opts.animate === true;
  const { config } = opts;
  const msg = appendMessage(container, 'assistant', text, {
    ts: opts.ts,
    responseMs: opts.responseMs,
    trustedHtml: opts.trustedHtml,
    model: opts.model
  });

  // Optional suggestion chips
  if (msg && config?.suggestions && Array.isArray(config.suggestions)) {
    const chipRow = document.createElement('div');
    chipRow.className = 'assistant-suggestions';
    config.suggestions.forEach((suggestion) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'assistant-suggestion-btn';
      btn.textContent = suggestion.label || String(suggestion.action || '');
      btn.addEventListener('click', () => {
        if (typeof opts.onSuggestion === 'function') {
          opts.onSuggestion(suggestion);
        }
      });
      chipRow.appendChild(btn);
    });
    msg.appendChild(chipRow);
  }

  // Optional toolbox
  if (msg && (opts.onRun || opts.tools)) {
    attachAssistantTools(msg, { text, onRun: opts.onRun });
  }

  if (animate && msg) {
    const body = msg.querySelector('.message-content');
    if (opts.animation === 'typing_effect') {
      await typeMessage(body, text, { speed: opts.animationSpeed || 30 });
    } else {
      await animateBody(body, text, { trustedHtml: opts.trustedHtml });
    }
  }
  return msg;
}

export function buildStaticReplyMatcher(staticReplies) {
  return function getStaticReply(inputRaw, lang = 'en') {
    const input = String(inputRaw || '').trim();
    if (!input) return null;
    const lowered = input.toLowerCase();
    const asksName = /(^|\b)(your name|who are you|what is your name|tell me your name)\b/i.test(input)
      || input.includes('তোমার নাম')
      || input.includes('আপনার নাম')
      || input.includes('নাম কি')
      || input.includes('নাম কী');
    if (asksName) {
      return staticReplies[lang]?.name || staticReplies.en?.name;
    }
    const asksAbout = lowered.includes('broxlab')
      || lowered.includes('broxlab.online')
      || lowered.includes('about yourself')
      || lowered.includes('about brox')
      || input.includes('নিজের সম্পর্কে')
      || input.includes('ব্রক্সল্যাব');
    if (asksAbout) {
      return staticReplies[lang]?.about || staticReplies.en?.about;
    }
    return null;
  };
}
