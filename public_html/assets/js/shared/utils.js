/**
 * Shared Utility Functions
 * Consolidated from: account-settings-shared.js, linked-emails.js, media-upload.js, 
 * admin/modules/dom-utils.js, analytics-dashboard.js
 */

/**
 * Safely escape HTML characters to prevent XSS
 * @param {*} text - Text to escape
 * @returns {string} Escaped text safe for HTML insertion
 */
export function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    };
    return String(text ?? '').replace(/[&<>"']/g, (char) => map[char]);
}

/**
 * Retrieve CSRF token from DOM
 * @param {string} selector - Optional CSS selector for specific element
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
 * Safely parse JSON with fallback
 * @param {string} value - JSON string to parse
 * @param {*} fallback - Value to return if parsing fails
 * @returns {*} Parsed object or fallback
 */
export function parseJson(value, fallback) {
    if (!value) return fallback;
    try {
        return JSON.parse(value);
    } catch (e) {
        return fallback;
    }
}

/**
 * Convert text to safe ID format (kebab-case, alphanumeric only)
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
 * Format date to localized string with time
 * @param {string|Date} dateStr - Date string or Date object
 * @returns {string} Formatted date string (e.g., "Mar 3, 2026, 02:30 PM")
 */
export function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    try {
        const date = new Date(dateStr);
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return dateStr;
    }
}

/**
 * Format date to label format (date only, no time)
 * @param {string|Date} dateStr - Date string or Date object
 * @returns {string} Formatted date label (e.g., "Mar 3")
 */
export function formatDateLabel(dateStr) {
    if (!dateStr) return '';
    try {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    } catch (e) {
        return dateStr;
    }
}

/**
 * Safely set text content of DOM element
 * @param {HTMLElement} el - Element to update
 * @param {*} text - Text to set
 */
export function setText(el, text) {
    if (!el) return;
    el.textContent = String(text ?? '');
}
