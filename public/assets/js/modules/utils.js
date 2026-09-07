/**
 * Admin utility functions (ES Module)
 * Replaces previous IIFE + window.Brox pattern.
 */

/**
 * Run a function when DOM is ready
 * @param {Function} fn - Function to run when DOM is ready
 */
export function runWhenReady(fn) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fn, { once: true, });
  } else {
    fn();
  }
}

/**
 * Get current user ID from meta tag
 * @returns {string|null}
 */
export function getUserId() {
  return document.querySelector('meta[name="user-id"]')?.content || null;
}

/**
 * Get CSRF token from form hidden input or meta tag
 * @returns {string}
 */
export function adminGetCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta?.content) return meta.content;
  const hidden = document.getElementById('csrf_token');
  return hidden?.value || '';
}

/**
 * Escape HTML entities for safe display
 * @param {*} value - Value to escape
 * @returns {string}
 */
export function adminEscapeHtml(value) {
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(String(value ?? '')));
  return div.innerHTML;
}

/**
 * Convert text to a URL-safe slug
 * @param {string} url - URL to sanitize
 * @returns {string}
 */
export function adminToSafeUrl(url) {
  return String(url ?? '')
    .trim()
    .toLowerCase()
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9\-/]/g, '')
    .replace(/-+/g, '-');
}

/**
 * Format a timestamp to locale time string
 * @param {*} value - Timestamp value
 * @returns {string}
 */
export function adminFormatTime(value) {
  if (!value) return '';
  try {
    return new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', });
  } catch {
    return String(value);
  }
}

/**
 * Set empty state message in a list element
 * @param {HTMLElement} listEl - List container element
 * @param {string} message - Empty state message
 */
export function adminSetListEmpty(listEl, message) {
  if (!listEl) return;
  listEl.innerHTML = `<div class="text-center text-muted py-4">
      <i class="lucide lucide-inbox block text-5xl mb-2"></i>
      <p class="mb-0 mt-2 small">${adminEscapeHtml(message)}</p>
    </div>`;
}

/**
 * Update a badge with unread count
 * @param {HTMLElement} badgeEl - Badge element to show/hide
 * @param {HTMLElement} countEl - Element containing count text
 * @param {number} unreadCount - Unread count value
 */
export function adminUpdateBadge(badgeEl, countEl, unreadCount) {
  if (!badgeEl) return;
  if (unreadCount > 0) {
    badgeEl.classList.remove('hidden');
    if (countEl) countEl.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
  } else {
    badgeEl.classList.add('hidden');
  }
}
