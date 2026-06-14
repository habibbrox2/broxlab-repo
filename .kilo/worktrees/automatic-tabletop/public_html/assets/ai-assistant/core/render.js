/**
 * BroxLab AI Assistant - Core Render Module
 * Provides message rendering utilities for the chat interface
 */

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
  msgEl.className = `brox-ai-message brox-ai-message-${role}`;
  msgEl.setAttribute('role', 'article');

  // Add timestamp if provided
  if (options.ts) {
    msgEl.dataset.timestamp = options.ts;
  }

  // Add response time if provided
  if (options.responseMs) {
    msgEl.dataset.responseMs = options.responseMs;
  }

  // Avatar
  const avatarEl = document.createElement('div');
  avatarEl.className = 'brox-ai-message-avatar';
  if (role === 'user') {
    avatarEl.innerHTML = '<i class="bi bi-person-circle"></i>';
  } else {
    avatarEl.innerHTML = '<i class="bi bi-stars"></i>';
  }
  msgEl.appendChild(avatarEl);

  // Message content
  const contentEl = document.createElement('div');
  contentEl.className = 'brox-ai-message-content';

  // Parse and render content
  const textEl = document.createElement('div');
  textEl.className = 'brox-ai-message-text';

  // Simple markdown-like parsing
  let renderedText = sanitizeHtml(text);

  // Handle code blocks
  renderedText = renderedText.replace(/```(\w*)\n([\s\S]*?)```/g, (match, lang, code) => {
    return `<pre class="brox-ai-code-block"><code class="language-${lang}">${escapeHtml(code.trim())}</code></pre>`;
  });

  // Handle inline code
  renderedText = renderedText.replace(/`([^`]+)`/g, '<code class="brox-ai-inline-code">$1</code>');

  // Handle bold
  renderedText = renderedText.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

  // Handle italic
  renderedText = renderedText.replace(/\*(.+?)\*/g, '<em>$1</em>');

  // Handle line breaks
  renderedText = renderedText.replace(/\n/g, '<br>');

  textEl.innerHTML = renderedText;
  contentEl.appendChild(textEl);

  // Add metadata if available
  if (options.ts) {
    const metaEl = document.createElement('div');
    metaEl.className = 'brox-ai-message-meta';
    const date = new Date(options.ts);
    const timeStr = date.toLocaleTimeString();
    metaEl.textContent = timeStr;

    if (options.responseMs && role === 'assistant') {
      metaEl.textContent += ` (${options.responseMs}ms)`;
    }
    contentEl.appendChild(metaEl);
  }

  msgEl.appendChild(contentEl);
  container.appendChild(msgEl);

  // Scroll to bottom
  container.scrollTop = container.scrollHeight;

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
  msgEl.className = 'brox-ai-message brox-ai-message-assistant';
  msgEl.setAttribute('role', 'article');

  // Avatar
  const avatarEl = document.createElement('div');
  avatarEl.className = 'brox-ai-message-avatar';
  avatarEl.innerHTML = '<i class="bi bi-stars"></i>';
  msgEl.appendChild(avatarEl);

  // Message content
  const contentEl = document.createElement('div');
  contentEl.className = 'brox-ai-message-content';

  const textEl = document.createElement('div');
  textEl.className = 'brox-ai-message-text';

  if (options.animate) {
    // Type out the message
    container.appendChild(msgEl);
    contentEl.appendChild(textEl);
    msgEl.appendChild(contentEl);

    await typeMessage(textEl, text, options);
  } else {
    // Render immediately
    let renderedText = sanitizeHtml(text);

    // Handle code blocks
    renderedText = renderedText.replace(/```(\w*)\n([\s\S]*?)```/g, (match, lang, code) => {
      return `<pre class="brox-ai-code-block"><code class="language-${lang}">${escapeHtml(code.trim())}</code></pre>`;
    });

    // Handle inline code
    renderedText = renderedText.replace(/`([^`]+)`/g, '<code class="brox-ai-inline-code">$1</code>');

    // Handle bold
    renderedText = renderedText.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

    // Handle italic
    renderedText = renderedText.replace(/\*(.+?)\*/g, '<em>$1</em>');

    // Handle line breaks
    renderedText = renderedText.replace(/\n/g, '<br>');

    textEl.innerHTML = renderedText;
    contentEl.appendChild(textEl);
    msgEl.appendChild(contentEl);
    container.appendChild(msgEl);
  }

  // Scroll to bottom
  container.scrollTop = container.scrollHeight;

  return msgEl;
}

/**
 * Type out a message character by character
 * @param {HTMLElement} element - The element to type into
 * @param {string} text - The text to type
 * @param {Object} options - Typing options
 * @returns {Promise<void>}
 */
export function typeMessage(element, text, options = {}) {
  return new Promise(resolve => {
    const speed = options.speed || 10; // milliseconds per character
    let index = 0;
    let isCodeBlock = false;
    let currentLine = '';

    const type = () => {
      if (index < text.length) {
        const char = text[index];
        currentLine += char;

        // Check for code block markers
        if (text.substring(index, index + 3) === '```') {
          isCodeBlock = !isCodeBlock;
        }

        // Render progressively with basic formatting
        let displayText = currentLine;

        // Handle code blocks
        displayText = displayText.replace(/```(\w*)\n([\s\S]*?)```/g, (match, lang, code) => {
          return `<pre class="brox-ai-code-block"><code class="language-${lang}">${escapeHtml(code.trim())}</code></pre>`;
        });

        // Handle inline code
        displayText = displayText.replace(/`([^`]+)`/g, '<code class="brox-ai-inline-code">$1</code>');

        // Handle bold
        displayText = displayText.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

        // Handle italic
        displayText = displayText.replace(/\*(.+?)\*/g, '<em>$1</em>');

        // Handle line breaks
        displayText = displayText.replace(/\n/g, '<br>');

        element.innerHTML = displayText;

        // Scroll parent into view
        if (element.closest('.brox-ai-body')) {
          const body = element.closest('.brox-ai-body');
          body.scrollTop = body.scrollHeight;
        }

        index++;
        setTimeout(type, isCodeBlock ? speed * 0.5 : speed);
      } else {
        // Final render with complete formatting
        let finalText = currentLine;

        // Handle code blocks
        finalText = finalText.replace(/```(\w*)\n([\s\S]*?)```/g, (match, lang, code) => {
          return `<pre class="brox-ai-code-block"><code class="language-${lang}">${escapeHtml(code.trim())}</code></pre>`;
        });

        // Handle inline code
        finalText = finalText.replace(/`([^`]+)`/g, '<code class="brox-ai-inline-code">$1</code>');

        // Handle bold
        finalText = finalText.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

        // Handle italic
        finalText = finalText.replace(/\*(.+?)\*/g, '<em>$1</em>');

        // Handle line breaks
        finalText = finalText.replace(/\n/g, '<br>');

        element.innerHTML = finalText;
        resolve();
      }
    };

    type();
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
        : p instanceof RegExp
          ? p.test(text)
          : false
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
    speed: config.speed || 10,
    markdown: config.markdown !== false,
    syntax: config.syntax !== false,
    metadata: config.metadata !== false,
  };
}

/**
 * Escape HTML special characters
 * @param {string} text - Text to escape
 * @returns {string} Escaped text
 */
export function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  };
  return text.replace(/[&<>"']/g, m => map[m]);
}

/**
 * Sanitize HTML to prevent XSS
 * @param {string} html - HTML to sanitize
 * @returns {string} Sanitized HTML
 */
export function sanitizeHtml(html) {
  // Remove script tags and other dangerous content
  const sanitized = html
    .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
    .replace(/on\w+\s*=\s*"[^"]*"/gi, '')
    .replace(/on\w+\s*=\s*'[^']*'/gi, '');

  return sanitized;
}
