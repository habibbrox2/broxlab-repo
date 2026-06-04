/**
 * BroxLab AI Assistant - Core Render Module
 * Provides message rendering utilities for the chat interface
 *
 * v2.1.0 - Security hardening, Lucide icon migration, deduplicated markdown
 */

/**
 * Escape HTML special characters
 * @param {string} text - Text to escape
 * @returns {string} Escaped text
 */
export function escapeHtml(text) {
  const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  return String(text).replace(/[&<>"']/g, m => map[m]);
}

/**
 * Sanitize HTML to prevent XSS attacks
 * Handles script tags, event handlers, javascript: URIs, data: URIs, and more
 * @param {string} html - HTML to sanitize
 * @returns {string} Sanitized HTML
 */
export function sanitizeHtml(html) {
  if (!html) return '';

  let sanitized = String(html);

  // Remove script tags (including content)
  sanitized = sanitized.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');

  // Remove event handler attributes (on*)
  sanitized = sanitized.replace(/\son\w+\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+)/gi, '');

  // Remove javascript:, vbscript:, and data: URIs in href/src/action attributes
  sanitized = sanitized.replace(/((?:href|src|action|formaction|dynsrc|lowsrc)\s*=\s*)(?:"javascript:[^"]*"|'javascript:[^']*'|javascript:[^\s>]+)/gi, '$1""');
  sanitized = sanitized.replace(/((?:href|src|action|formaction|dynsrc|lowsrc)\s*=\s*)(?:"vbscript:[^"]*"|'vbscript:[^']*'|vbscript:[^\s>]+)/gi, '$1""');
  sanitized = sanitized.replace(/((?:href|src|action|formaction|dynsrc|lowsrc)\s*=\s*)(?:"data:[^"]*"|'data:[^']*'|data:[^\s>]+)/gi, '$1""');

  // Remove embed/iframe dangerous elements entirely
  sanitized = sanitized.replace(/<(iframe|object|embed|applet)\b[^>]*>[\s\S]*?<\/\1>/gi, '');
  sanitized = sanitized.replace(/<(iframe|object|embed|applet)\b[^>]*\/?>/gi, '');

  return sanitized;
}

/**
 * Parse markdown-like text into HTML
 * Deduplicated single function for all markdown rendering
 * @param {string} text - Raw text with markdown
 * @returns {string} HTML string
 */
export function parseMarkdown(text) {
  if (!text) return '';

  let html = escapeHtml(text);

  // Code blocks (```lang\ncode```)
  html = html.replace(/```(\w*)\n([\s\S]*?)```/g, (match, lang, code) => {
    const langAttr = lang ? ` data-lang="${escapeHtml(lang)}"` : '';
    const langLabel = lang ? `<span class="brox-ai-code-lang">${escapeHtml(lang)}</span>` : '';
    return `<div class="brox-ai-code-block-wrap"><div class="brox-ai-code-header">${langLabel}<button class="brox-ai-copy-code-btn" title="Copy code" aria-label="Copy code"><i class="lucide lucide-copy text-sm"></i></button></div><pre class="brox-ai-code-block"${langAttr}><code>${code.trim()}</code></pre></div>`;
  });

  // Inline code
  html = html.replace(/`([^`]+)`/g, '<code class="brox-ai-inline-code">$1</code>');

  // Bold (**text**)
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

  // Italic (*text*)
  html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');

  // Strikethrough (~~text~~)
  html = html.replace(/~~(.+?)~~/g, '<del>$1</del>');

  // Links [text](url) - only http(s) URLs allowed
  html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

  // Unordered lists (lines starting with - or *, allows leading whitespace)
  html = html.replace(/^\s*[-*]\s+(.+)$/gm, 'ULITEM$1');
  html = html.replace(/((?:ULITEM.*\n?)+)/g, (match) => {
    const items = match.replace(/ULITEM/g, '').trim();
    return '<ul>' + items.split('\n').map(l => '<li>' + l.trim() + '</li>').join('') + '</ul>';
  });

  // Ordered lists (lines starting with 1. 2. etc, allows leading whitespace)
  html = html.replace(/^\s*\d+\.\s+(.+)$/gm, 'OLITEM$1');
  html = html.replace(/((?:OLITEM.*\n?)+)/g, (match) => {
    const items = match.replace(/OLITEM/g, '').trim();
    return '<ol>' + items.split('\n').map(l => '<li>' + l.trim() + '</li>').join('') + '</ol>';
  });

  // Blockquotes
  html = html.replace(/^&gt;\s?(.+)$/gm, '<blockquote>$1</blockquote>');

  // Line breaks
  html = html.replace(/\n/g, '<br>');

  // Clean up consecutive <br> tags
  html = html.replace(/(<br>){3,}/g, '<br><br>');

  return html;
}

/**
 * Append a message to the chat container
 * @param {HTMLElement} container - The messages container
 * @param {string} role - Message role ('user' or 'assistant')
 * @param {string} text - The message text
 * @param {Object} options - Rendering options
 * @returns {HTMLElement} The created message element
 */
export function appendMessage(container, role, text, options = {}) {
  if (!container) return null;

  const msgEl = document.createElement('div');
  msgEl.className = `brox-ai-message brox-ai-${role}`;
  msgEl.setAttribute('role', 'article');

  if (options.ts) msgEl.dataset.timestamp = options.ts;
  if (options.responseMs) msgEl.dataset.responseMs = options.responseMs;

  // Avatar using Lucide icons (not Bootstrap)
  const avatarEl = document.createElement('div');
  avatarEl.className = 'brox-ai-message-avatar';
  avatarEl.innerHTML = role === 'user'
    ? '<i class="lucide lucide-user" style="width:1rem;height:1rem;"></i>'
    : '<i class="lucide lucide-sparkles" style="width:1rem;height:1rem;"></i>';
  msgEl.appendChild(avatarEl);

  // Message content
  const contentEl = document.createElement('div');
  contentEl.className = 'brox-ai-message-content';

  const textEl = document.createElement('div');
  textEl.className = 'brox-ai-message-text';
  textEl.innerHTML = parseMarkdown(text);
  contentEl.appendChild(textEl);

  // Add metadata if available
  if (options.ts) {
    const metaEl = document.createElement('div');
    metaEl.className = 'brox-ai-message-meta';
    const date = new Date(options.ts);
    let metaText = date.toLocaleTimeString();
    if (options.responseMs && role === 'assistant') {
      metaText += ` \u00B7 ${options.responseMs}ms`;
    }
    metaEl.textContent = metaText;
    contentEl.appendChild(metaEl);
  }

  msgEl.appendChild(contentEl);
  container.appendChild(msgEl);
  container.scrollTop = container.scrollHeight;

  // Bind copy-code buttons
  bindCopyCodeButtons(msgEl);

  return msgEl;
}

/**
 * Append an assistant message with optional animation
 * @param {HTMLElement} container - The messages container
 * @param {string} text - The message text
 * @param {Object} options - Rendering options including animate flag
 * @returns {Promise<HTMLElement>} Resolves to the created message element
 */
export async function appendAssistant(container, text, options = {}) {
  if (!container) return null;

  const msgEl = document.createElement('div');
  msgEl.className = 'brox-ai-message brox-ai-assistant';
  msgEl.setAttribute('role', 'article');

  // Avatar using Lucide icons
  const avatarEl = document.createElement('div');
  avatarEl.className = 'brox-ai-message-avatar';
  avatarEl.innerHTML = '<i class="lucide lucide-sparkles" style="width:1rem;height:1rem;"></i>';
  msgEl.appendChild(avatarEl);

  const contentEl = document.createElement('div');
  contentEl.className = 'brox-ai-message-content';
  const textEl = document.createElement('div');
  textEl.className = 'brox-ai-message-text';

  if (options.animate) {
    container.appendChild(msgEl);
    contentEl.appendChild(textEl);
    msgEl.appendChild(contentEl);
    await typeMessage(textEl, text, options);
  } else {
    textEl.innerHTML = parseMarkdown(text);
    contentEl.appendChild(textEl);
    msgEl.appendChild(contentEl);
    container.appendChild(msgEl);
  }

  container.scrollTop = container.scrollHeight;
  bindCopyCodeButtons(msgEl);

  return msgEl;
}

/**
 * Type out a message character by character with progressive markdown rendering
 * @param {HTMLElement} element - The element to type into
 * @param {string} text - The text to type
 * @param {Object} options - Typing options
 * @returns {Promise<void>}
 */
export function typeMessage(element, text, options = {}) {
  return new Promise(resolve => {
    const speed = options.speed || 12;
    const PARSE_INTERVAL = 4; // Re-parse markdown every N chars to avoid O(n²) jank
    let index = 0;
    let lastParsedIndex = 0;
    let cachedHtml = '';

    const type = () => {
      if (index < text.length) {
        index++;

        // Re-run full markdown parse only every PARSE_INTERVAL chars
        if (index - lastParsedIndex >= PARSE_INTERVAL || index >= text.length) {
          cachedHtml = parseMarkdown(text.substring(0, index));
          element.innerHTML = cachedHtml;
          lastParsedIndex = index;
        }
        // Between parses, keep last cached HTML (no flicker)

        // Scroll parent into view
        const body = element.closest('.brox-ai-body');
        if (body) body.scrollTop = body.scrollHeight;

        // Vary speed for code blocks
        const inCode = (text.substring(0, index).match(/```/g) || []).length % 2 === 1;
        setTimeout(type, inCode ? speed * 0.3 : speed);
      } else {
        // Final render with complete formatting
        element.innerHTML = parseMarkdown(text);
        resolve();
      }
    };

    type();
  });
}

/**
 * Bind copy-code buttons within a message element
 * @param {HTMLElement} msgEl - The message element
 */
export function bindCopyCodeButtons(msgEl) {
  if (!msgEl) return;
  msgEl.querySelectorAll('.brox-ai-copy-code-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const codeBlock = btn.closest('.brox-ai-code-block-wrap')?.querySelector('code');
      if (!codeBlock) return;

      const text = codeBlock.textContent;
      navigator.clipboard.writeText(text).then(() => {
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="lucide lucide-check text-sm" style="color: var(--brox-ai-success, #2dd4bf);"></i>';
        setTimeout(() => { btn.innerHTML = originalHTML; }, 1500);
      }).catch(() => {
        // Fallback copy method
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (_) { /* noop */ }
        document.body.removeChild(ta);
      });
    });
  });
}

/**
 * Build a static reply matcher
 * @param {string|RegExp|Array} pattern - Pattern to match against
 * @returns {Function} Matcher function
 */
export function buildStaticReplyMatcher(pattern) {
  if (typeof pattern === 'string') {
    return (text) => text.toLowerCase().includes(pattern.toLowerCase());
  } else if (pattern instanceof RegExp) {
    return (text) => pattern.test(text);
  } else if (Array.isArray(pattern)) {
    return (text) => pattern.some(p =>
      typeof p === 'string'
        ? text.toLowerCase().includes(p.toLowerCase())
        : p instanceof RegExp ? p.test(text) : false
    );
  }
  return () => false;
}

/**
 * Parse response configuration from a response object
 * @param {Object} config - The response configuration
 * @returns {Object} Parsed configuration with defaults
 */
export function parseResponseConfig(config = {}) {
  return {
    animate: config.animate !== false,
    speed: config.speed || 12,
    markdown: config.markdown !== false,
    syntax: config.syntax !== false,
    metadata: config.metadata !== false,
  };
}
