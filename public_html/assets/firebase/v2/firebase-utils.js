// v2/firebase-utils.js
// Shared helpers used across firebase v2 modules
// Consolidates common logic such as provider normalization, error messages, and CSRF token access

import { getCsrfToken as sharedCsrf, escapeHtml as sharedEscapeHtml } from '../../js/shared/utils.js';

/**
 * Normalize various provider identifiers to a canonical lowercase string.
 * @param {*} provider - provider id or name
 * @returns {string|null} normalized provider or null if unrecognized
 */
export function normalizeProvider(provider) {
    if (!provider) return 'firebase';
    const str = String(provider).toLowerCase();
    if (str === 'google.com' || str === 'google') return 'google';
    if (str === 'facebook.com' || str === 'facebook') return 'facebook';
    if (str === 'github.com' || str === 'github') return 'github';
    if (str === 'anonymous' || str === 'guest') return 'anonymous';
    return str;
}

/**
 * Produce a human‑readable message from an error object.
 * @param {*} error
 * @returns {string}
 */
export function getErrorMessage(error) {
    if (!error) return 'Unknown error occurred';
    if (error.message) return error.message;
    if (error.error) return error.error;
    return String(error);
}

/**
 * Detect whether a Firebase auth error represents a closed popup.
 * @param {*} error
 * @returns {boolean}
 */
export function isPopupClosedError(error) {
    const code = String(error?.code || '').toLowerCase().replace(/^auth\//, '');
    return code === 'popup_closed_by_user' || code === 'cancelled-popup-request' || code === 'popup_closed';
}

/**
 * Obtain CSRF token from DOM (delegates to shared utils).
 * @returns {string|null}
 */
export function getCsrfToken() {
    return sharedCsrf();
}

/**
 * Expose escapeHtml for convenience in firebase modules that also need it.
 */
export const escapeHtml = sharedEscapeHtml;
