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

export function formatMeta({ role, ts, responseMs, lang = 'en' }) {
  const parts = [];
  const locale = lang === 'bn' ? 'bn-BD' : 'en-US';
  if (ts) {
    const dt = new Date(ts);
    if (!Number.isNaN(dt.getTime())) {
      parts.push(new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit' }).format(dt));
    }
  }
  if (role === 'assistant' && Number.isFinite(responseMs)) {
    const duration = responseMs < 1000 ? `${responseMs}ms` : `${(responseMs / 1000).toFixed(1)}s`;
    parts.push(duration);
  }
  return parts.join(' • ');
}

export async function animateBody(node, text, { trustedHtml = false } = {}) {
  if (!node) return;
  const normalized = String(text ?? '');
  if (!normalized) {
    node.innerHTML = '';
    return;
  }

  if (trustedHtml || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
    node.innerHTML = formatBody(normalized, trustedHtml);
    scrollToBottom(node.parentElement);
    return;
  }

  const chunkSize = Math.max(1, Math.ceil(normalized.length / TYPEWRITER_MAX_STEPS));
  for (let i = 0; i < normalized.length; i += chunkSize) {
    node.textContent = normalized.slice(0, i + chunkSize);
    scrollToBottom(node.parentElement?.parentElement);
    await new Promise((res) => window.setTimeout(res, TYPEWRITER_CHUNK_DELAY_MS));
  }
  node.innerHTML = formatBody(normalized, trustedHtml);
  scrollToBottom(node.parentElement?.parentElement);
}

export function appendMessage(container, role, text, { ts = new Date().toISOString(), responseMs = null, trustedHtml = false } = {}) {
  if (!container) return null;
  const msg = document.createElement('div');
  msg.className = `message ${role}`;
  const body = document.createElement('div');
  body.className = 'message-content';
  body.innerHTML = formatBody(text, trustedHtml);
  msg.appendChild(body);

  const meta = formatMeta({ role, ts, responseMs });
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

export async function appendAssistant(container, text, opts = {}) {
  const animate = opts.animate === true;
  const msg = appendMessage(container, 'assistant', text, {
    ts: opts.ts,
    responseMs: opts.responseMs,
    trustedHtml: opts.trustedHtml
  });
  if (animate && msg) {
    const body = msg.querySelector('.message-content');
    await animateBody(body, text, { trustedHtml: opts.trustedHtml });
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
