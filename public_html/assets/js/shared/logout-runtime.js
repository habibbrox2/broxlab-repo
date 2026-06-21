/**
 * Unified logout runtime (ES Module)
 * Replaces previous IIFE + window.Brox pattern.
 */

/**
 * Perform unified logout — clears session, redirects
 * @param {object} [options={}]
 * @param {string} [options.redirectUrl] - URL to redirect after logout
 * @param {boolean} [options.forceReload] - Force page reload after logout
 * @param {boolean} [options.suppressRedirect] - Skip redirect entirely
 * @param {string} [options.method] - HTTP method for logout request
 * @param {object} [options.headers] - Extra headers for logout request
 * @param {string} [options.csrfToken] - CSRF token override
 */
export async function performUnifiedLogout(options = {}) {

  if (options.redirectUrl && !options.suppressRedirect) {
    window.location.href = options.redirectUrl;
    return;
  }

  const method = (options.method || 'POST').toUpperCase();
  const headers = {
    'X-Requested-With': 'XMLHttpRequest',
    'Accept': 'application/json',
    ...(options.headers || {}),
  };

  const csrfToken = options.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
  if (csrfToken && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
    headers['X-CSRF-Token'] = csrfToken;
  }

  try {
    const res = await fetch('/logout', { method, headers, credentials: 'same-origin', });
    if (!res.ok) {
      console.warn('[logout-runtime] Logout request failed:', res.status);
    }
  } catch (err) {
    console.warn('[logout-runtime] Logout request error:', err);
  }

  if (options.forceReload) {
    window.location.reload();
  }
}

/**
 * Initialize unified logout behavior on the page
 * Attaches click handlers to elements with [data-logout] attribute
 * @param {object} [options={}]
 */
export function initUnifiedLogout(options = {}) {
  const triggers = document.querySelectorAll('[data-logout]');
  if (!triggers.length) return;

  triggers.forEach((el) => {
    el.addEventListener('click', async (e) => {
      e.preventDefault();
      const redirectUrl = el.getAttribute('data-logout-url') || '/';
      const runtimeOptions = {
        redirectUrl,
        forceReload: el.hasAttribute('data-logout-force'),
        suppressRedirect: el.hasAttribute('data-logout-skip-redirect'),
        method: el.getAttribute('data-logout-method') || 'POST',
        csrfToken: el.getAttribute('data-logout-csrf') || undefined,
      };
      await performUnifiedLogout({ ...options, ...runtimeOptions, });
    });
  });
}

// Auto-initialize on DOMContentLoaded
initUnifiedLogout();
