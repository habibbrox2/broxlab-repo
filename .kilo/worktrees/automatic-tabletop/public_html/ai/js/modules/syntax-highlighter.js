/**
 * Syntax Highlighter Module
 * Handles code block syntax highlighting in chat messages
 * Supports highlight.js or Prism.js with fallback to basic highlighting
 *
 * Usage:
 *   import SyntaxHighlighter from './modules/syntax-highlighter.js';
 *   const highlighter = new SyntaxHighlighter();
 *   highlighter.highlightElement(document.getElementById('chatBody'));
 */
export default class SyntaxHighlighter {
  constructor(options = {}) {
    this.initialized = false;
    this.useHighlightJs = typeof hljs !== 'undefined';
    this.usePrism = typeof Prism !== 'undefined';
    this.supportedLanguages = options.languages || [
      'javascript', 'typescript', 'python', 'php', 'html', 'css',
      'bash', 'sql', 'json', 'xml', 'yaml', 'markdown',
    ];
    this.maxHighlightLength = options.maxHighlightLength || 50000;
  }

  init() {
    if (this.initialized) return;
    this.initialized = true;

    if (this.useHighlightJs) {
      this.configureHighlightJs();
    }
  }

  configureHighlightJs() {
    try {
      if (typeof hljs.configure === 'function') {
        hljs.configure({
          ignoreUnescapedHTML: true,
          throwUnescapedHTML: false,
        });
      }
    } catch {
      // non-critical
    }
  }

  /**
   * Highlight a code block string
   * @param {string} codeBlock - Raw code text
   * @param {string} [language] - Optional language hint
   * @returns {string} HTML-highlighted code
   */
  highlight(codeBlock, language) {
    if (!codeBlock) return '';

    // Skip very large code blocks to avoid performance issues
    if (codeBlock.length > this.maxHighlightLength) {
      return this.escapeHtml(codeBlock);
    }

    if (this.useHighlightJs && language && hljs.getLanguage(language)) {
      try {
        return hljs.highlight(codeBlock, { language }).value;
      } catch {
        // fall through
      }
    }

    if (this.useHighlightJs) {
      try {
        return hljs.highlightAuto(codeBlock).value;
      } catch {
        // fall through
      }
    }

    if (this.usePrism && language && Prism.languages[language]) {
      try {
        return Prism.highlight(codeBlock, Prism.languages[language], language);
      } catch {
        // fall through
      }
    }

    return this.basicHighlight(codeBlock);
  }

  /**
   * Highlight all code blocks within a container element
   * @param {HTMLElement} element - Container to scan for <pre><code> blocks
   */
  highlightElement(element) {
    if (!element) return;

    const codeBlocks = element.querySelectorAll('pre code');
    if (!codeBlocks.length) return;

    this.init();

    codeBlocks.forEach((block) => {
      const lang = this.detectLanguage(block);
      const code = block.textContent || '';

      if (code.length > this.maxHighlightLength) {
        block.innerHTML = this.escapeHtml(code);
        block.classList.add('nohighlight');
        return;
      }

      try {
        const highlighted = this.highlight(code, lang);
        if (highlighted) {
          block.innerHTML = highlighted;
          block.classList.add('hljs');
        }
      } catch {
        block.innerHTML = this.escapeHtml(code);
      }
    });
  }

  /**
   * Detect language from class or data attribute on a code element
   * @param {HTMLElement} codeElement
   * @returns {string|null}
   */
  detectLanguage(codeElement) {
    const classMatch = (codeElement.className || '').match(/language-(\w+)/);
    if (classMatch) return classMatch[1];

    const dataLang = codeElement.dataset?.lang || codeElement.dataset?.language;
    if (dataLang) return dataLang;

    return null;
  }

  /**
   * Basic syntax highlighting fallback (no external library needed)
   * Highlights: keywords, strings, numbers, comments
   * @param {string} code
   * @returns {string}
   */
  basicHighlight(code) {
    let html = this.escapeHtml(code);

    // Comments (single-line)
    html = html.replace(/(\/\/.*$)/gm, '<span class="hljs-comment">$1</span>');

    // Strings (double and single quotes)
    html = html.replace(
      /("(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*')/g,
      '<span class="hljs-string">$1</span>'
    );

    // Template literals
    html = html.replace(
      /(`(?:[^`\\]|\\.)*`)/g,
      '<span class="hljs-string">$1</span>'
    );

    // Numbers
    html = html.replace(
      /\b(\d+\.?\d*)\b/g,
      '<span class="hljs-number">$1</span>'
    );

    // Keywords
    const keywords =
      '\b(const|let|var|function|return|if|else|for|while|do|switch|case|break|continue|' +
      'class|extends|import|export|default|from|async|await|try|catch|finally|throw|' +
      'new|delete|typeof|instanceof|this|super|in|of|true|false|null|undefined|' +
      'def|class|module|require|include|echo|print|public|private|protected|static|' +
      'abstract|interface|implements|enum|type|namespace|package|)' +
      '\b';
    html = html.replace(
      new RegExp(keywords, 'g'),
      '<span class="hljs-keyword">$1</span>'
    );

    return html;
  }

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}
