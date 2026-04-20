/**
 * Utils Module
 * General utility functions for admin panel
 */

export const runWhenReady = (fn) => {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fn, { once: true, });
  } else {
    fn();
  }
};

export const getUserId = () => document.querySelector('meta[name="user-id"]')?.content || null;

export function adminGetCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

export function adminEscapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char] || char));
}

export function adminToSafeUrl(url) {
  const value = String(url || '').trim();
  if (!value) return '#';
  if (value.startsWith('/')) return value;
  if (/^https?:\/\//i.test(value)) return value;
  return '#';
}

export function adminFormatTime(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleString();
}

export function adminSetListEmpty(listEl, message) {
  if (!listEl) return;
  listEl.innerHTML = `
        <div class="text-center py-4 text-muted">
            <i class="bi bi-inbox fs-4"></i>
            <p class="mb-0 mt-2 small">${adminEscapeHtml(message)}</p>
        </div>
    `;
}

export function adminUpdateBadge(badgeEl, countEl, unreadCount) {
  const safeCount = Number.isFinite(unreadCount) ? Math.max(0, unreadCount) : 0;
  if (countEl) {
    countEl.textContent = String(safeCount);
  }
  if (badgeEl) {
    badgeEl.classList.toggle('d-none', safeCount <= 0);
  }
}