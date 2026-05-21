/**
 * UI Enhancements Module
 * Handles dark mode, theme management, chat UI utilities, animations, and responsive helpers
 *
 * Usage:
 *   import UIEnhancements from './modules/ui-enhancements.js';
 *   const ui = new UIEnhancements();
 *   ui.applyInitialTheme();
 */
export default class UIEnhancements {
  constructor(options = {}) {
    this.darkMode = localStorage.getItem('brox.admin.darkMode') === 'true';
    this.themeKey = options.themeKey || 'brox.admin.darkMode';
    this.onThemeChange = options.onThemeChange || null;
  }

  /**
   * Apply the initial theme based on stored preference
   */
  applyInitialTheme() {
    if (this.darkMode) {
      document.documentElement.classList.add('dark', 'brox-dark-mode');
    } else {
      document.documentElement.classList.remove('dark', 'brox-dark-mode');
    }
    return this.darkMode;
  }

  /**
   * Toggle between dark and light mode
   * @returns {boolean} New dark mode state
   */
  toggleDarkMode() {
    this.darkMode = !this.darkMode;
    this.persistTheme();
    this.applyInitialTheme();

    if (typeof this.onThemeChange === 'function') {
      this.onThemeChange(this.darkMode);
    }

    return this.darkMode;
  }

  /**
   * Set a specific theme
   * @param {'dark'|'light'} theme
   */
  setTheme(theme) {
    this.darkMode = theme === 'dark';
    this.persistTheme();
    this.applyInitialTheme();

    if (typeof this.onThemeChange === 'function') {
      this.onThemeChange(this.darkMode);
    }
  }

  /**
   * Persist theme preference to localStorage
   */
  persistTheme() {
    try {
      localStorage.setItem(this.themeKey, this.darkMode ? 'true' : 'false');
    } catch {
      // ignore storage failures
    }
  }

  /**
   * Detect system color scheme preference
   * @returns {'dark'|'light'|null}
   */
  detectSystemTheme() {
    if (typeof window === 'undefined' || !window.matchMedia) return null;

    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
    if (prefersDark?.matches) return 'dark';

    const prefersLight = window.matchMedia('(prefers-color-scheme: light)');
    if (prefersLight?.matches) return 'light';

    return null;
  }

  /**
   * Add a timestamp element to a message
   * @param {HTMLElement} msgElement - The message container
   * @param {Date} [date] - Optional date object (defaults to now)
   */
  addMessageTimestamp(msgElement, date = new Date()) {
    if (!msgElement) return;

    const timeEl = document.createElement('span');
    timeEl.className = 'msg-time small text-muted ms-2';
    timeEl.textContent = date.toLocaleTimeString(undefined, {
      hour: '2-digit',
      minute: '2-digit',
    });

    const msgContent = msgElement.querySelector('.brox-ai-msg-content, .msg-content');
    if (msgContent) {
      msgContent.appendChild(timeEl);
    } else {
      msgElement.appendChild(timeEl);
    }
  }

  /**
   * Scroll an element to the bottom smoothly
   * @param {HTMLElement} el
   */
  scrollToBottom(el) {
    if (!el) return;
    requestAnimationFrame(() => {
      el.scrollTop = el.scrollHeight;
    });
  }

  /**
   * Add a CSS class temporarily (e.g., for highlight animation)
   * @param {HTMLElement} el
   * @param {string} className
   * @param {number} duration
   */
  flashElement(el, className = 'brox-ai-highlight', duration = 2000) {
    if (!el) return;
    el.classList.add(className);
    setTimeout(() => el.classList.remove(className), duration);
  }

  /**
   * Check if the viewport is mobile-sized
   * @param {number} [breakpoint=768]
   * @returns {boolean}
   */
  isMobile(breakpoint = 768) {
    return window.innerWidth < breakpoint;
  }

  /**
   * Create a typing indicator element
   * @returns {HTMLElement}
   */
  createTypingIndicator() {
    const div = document.createElement('div');
    div.className = 'brox-ai-typing brox-ai-thinking-dots';
    div.innerHTML = '<span></span><span></span><span></span>';
    div.setAttribute('aria-label', 'Typing indicator');
    return div;
  }

  /**
   * Safely set HTML content with XSS protection
   * @param {HTMLElement} el
   * @param {string} html
   */
  safeSetHTML(el, html) {
    if (!el) return;
    const sanitized = html
      .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
      .replace(/on\w+="[^"]*"/gi, '')
      .replace(/on\w+='[^']*'/gi, '')
      .replace(/javascript:/gi, '');
    el.innerHTML = sanitized;
  }
}
