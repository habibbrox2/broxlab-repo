/**
 * Fetch utility functions (ES Module)
 * Replaces previous IIFE + window.Brox pattern.
 */

import { debounce } from '../shared/utils.js';

/**
 * Fetch with timeout support
 * @param {string} url - URL to fetch
 * @param {object} [options={}] - Fetch options with optional `timeout` (ms)
 * @returns {Promise<Response>}
 */
export async function fetchWithTimeout(url, options = {}) {
  const controller = new AbortController();
  const timeout = options.timeout || 15000;
  const timer = setTimeout(() => controller.abort(), timeout);
  const { timeout: _t, ...fetchOptions } = options;
  fetchOptions.signal = controller.signal;
  try {
    const response = await fetch(url, fetchOptions);
    return response;
  } finally {
    clearTimeout(timer);
  }
}

/**
 * Fetch JSON helper
 * @param {string} url - URL to fetch
 * @param {object} [options={}] - Fetch options
 * @returns {Promise<object>}
 */
export function fetchJson(url, options = {}) {
  return fetchWithTimeout(url, {
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', ...options.headers, },
    ...options,
  }).then((r) => r.json());
}

/**
 * Safe fetch JSON with error handling
 * @param {string} url - URL to fetch
 * @param {object} [options={}] - Fetch options
 * @returns {Promise<{success: boolean, data?: any, error?: string}>}
 */
export async function safeFetchJson(url, options = {}) {
  try {
    const data = await fetchJson(url, options);
    return { success: true, data, };
  } catch (err) {
    return { success: false, error: err.message || 'Network error', };
  }
}

/**
 * Upload form data with progress callback
 * @param {string} url - URL to upload to
 * @param {FormData} formData - Form data to upload
 * @param {object} [callbacks={}] - Callbacks { onProgress, onSuccess, onError }
 */
export function uploadFormData(url, formData, callbacks = {}) {
  const xhr = new XMLHttpRequest();
  xhr.open('POST', url, true);
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  if (csrfMeta) {
    xhr.setRequestHeader('X-CSRF-Token', csrfMeta.content);
  }
  if (callbacks.onProgress) {
    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable) {
        callbacks.onProgress(Math.round((e.loaded / e.total) * 100));
      }
    };
  }
  xhr.onload = () => {
    if (xhr.status >= 200 && xhr.status < 300) {
      try {
        const data = JSON.parse(xhr.responseText);
        callbacks.onSuccess?.(data);
      } catch {
        callbacks.onSuccess?.(xhr.responseText);
      }
    } else {
      callbacks.onError?.(new Error(`Upload failed: ${xhr.status}`));
    }
  };
  xhr.onerror = () => callbacks.onError?.(new Error('Network error during upload'));
  xhr.send(formData);
  return xhr;
}

/**
 * Create a debounced fetcher function
 * @param {number} [wait=300] - Debounce wait time in ms
 * @returns {Function} Debounced fetch function
 */
export function createDebouncedFetcher(wait = 300) {
  return debounce((url, options) => safeFetchJson(url, options), wait);
}
