/**
 * Shared Utility Functions
 * Consolidated from: account-settings-shared.js, linked-emails.js, media-upload.js,
 * modules/dom-utils.js, analytics-dashboard.js
 */

const HTML_ESCAPE_MAP = Object.freeze({
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
});
const HTML_ESCAPE_RE = /[&<>"']/g;

/**
 * Safely escape HTML characters to prevent XSS.
 * Also exposed as window.RTE_utils?.escapeHtml for RTE compatibility.
 * @param {*} text - Text to escape
 * @returns {string} Escaped text safe for HTML insertion
 */
export function escapeHtml(text) {
  return String(text ?? '').replace(HTML_ESCAPE_RE, (char) => HTML_ESCAPE_MAP[char]);
}

/**
 * Retrieve CSRF token from DOM
 * @param {string} [selector] - Optional CSS selector for more specific lookup
 * @returns {string} CSRF token value or empty string
 */
export function getCsrfToken(selector) {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta?.content) return meta.content;

  if (selector) {
    const el = document.querySelector(selector);
    if (el) return el.value || el.content || '';
  }

  const hidden = document.getElementById('csrf_token');
  if (hidden?.value) return hidden.value;
  return '';
}

/**
 * Safely parse JSON with fallback.
 * @param {string} value - JSON string to parse
 * @param {*} fallback - Value to return if parsing fails
 * @returns {*} Parsed object or fallback
 */
export function parseJson(value, fallback = null) {
  if (!value) return fallback;
  try {
    return JSON.parse(value);
  } catch {
    return fallback;
  }
}

/**
 * Convert text to safe ID format (kebab-case, alphanumeric + hyphens/underscores only).
 * @param {string} value - Text to convert
 * @returns {string} Safe ID string
 */
export function toSafeId(value) {
  return String(value ?? '')
    .trim()
    .replace(/\s+/g, '-')
    .replace(/[^a-zA-Z0-9_-]/g, '');
}

/**
 * Format date to localized string with time.
 * @param {string|Date} dateStr - Date string or Date object
 * @returns {string} Formatted date (e.g., "Mar 3, 2026, 02:30 PM") or fallback
 */
export function formatDate(dateStr) {
  if (!dateStr) return 'N/A';
  try {
    return new Date(dateStr).toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return String(dateStr);
  }
}

/**
 * Format date to label format (date only, no time).
 * @param {string|Date} dateStr - Date string or Date object
 * @returns {string} Formatted date label (e.g., "Mar 3")
 */
export function formatDateLabel(dateStr) {
  if (!dateStr) return '';
  try {
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', });
  } catch {
    return String(dateStr);
  }
}

/**
 * Safely set text content of DOM element.
 * @param {HTMLElement|null} el - Element to update
 * @param {*} text - Text to set
 */
export function setText(el, text) {
  if (!el) return;
  el.textContent = String(text ?? '');
}

/**
 * Safely parse a Response object as JSON with BOM stripping and error fallback.
 * @param {Response} response - Fetch Response object
 * @returns {Promise<Object>} Parsed JSON or fallback object
 */
export async function parseJsonResponse(response) {
  try {
    const raw = await response.text();
    const cleaned = raw.replace(/^\uFEFF/, '').trim();
    return JSON.parse(cleaned || '{}');
  } catch {
    return { success: false, error: 'Invalid server response', };
  }
}

/**
 * Debounce a function — waits `delay` ms after last call before invoking.
 * NOTE: An identical window-level version exists in shared/dom-helpers.js
 * for inline <script> consumers in layout.twig. Keep both in sync.
 * @param {Function} fn - Function to debounce
 * @param {number} delay - Milliseconds to wait
 * @returns {Function}
 */
export function debounce(fn, delay = 300) {
  let timer = null;
  return function (...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
}

/**
 * Throttle a function — ensures it's called at most once every `limit` ms.
 * NOTE: An identical window-level version exists in shared/dom-helpers.js
 * for inline <script> consumers in layout.twig. Keep both in sync.
 * @param {Function} fn - Function to throttle
 * @param {number} limit - Minimum interval between calls
 * @returns {Function}
 */
export function throttle(fn, limit = 300) {
  let inThrottle = false;
  return function (...args) {
    if (inThrottle) return;
    inThrottle = true;
    fn.apply(this, args);
    setTimeout(() => { inThrottle = false; }, limit);
  };
}

/**
 * Create a DOM element from an HTML string safely.
 * @param {string} html - HTML string
 * @returns {DocumentFragment}
 */
export function createElement(html) {
  const tmpl = document.createElement('template');
  tmpl.innerHTML = String(html ?? '');
  return tmpl.content;
}

/**
 * Create a unique ID string.
 * @param {string} prefix - Optional prefix
 * @returns {string}
 */
export function uniqueId(prefix = 'uid') {
  return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}

// ── Asset Version Helpers ──

/**
 * Extract version parameter from the current module URL (set by PHP asset() helper).
 * Used for cache-busting dynamic import() calls.
 * @type {string}
 */
export const runtimeAssetVersion = (() => {
  try {
    return new URL(import.meta.url).searchParams.get('v') || '';
  } catch {
    return '';
  }
})();

/**
 * Append a cache-busting version parameter to a URL.
 * If no version is available, returns the URL unchanged.
 * @param {string} url - Base URL
 * @returns {string} URL with version parameter appended (if version available)
 */
export function withAssetVersion(url) {
  if (!runtimeAssetVersion) return url;
  const separator = url.includes('?') ? '&' : '?';
  return `${url}${separator}v=${encodeURIComponent(runtimeAssetVersion)}`;
}
