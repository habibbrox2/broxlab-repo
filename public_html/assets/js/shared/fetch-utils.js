/**
 * Shared Fetch/API Utilities
 * Consolidated from: account-settings-shared.js, analytics-dashboard.js, media-upload.js
 */

import { debounce } from './utils.js';

function getDefaultTimeoutMs() {
  const configured = Number(
    window.__APP_JS_CONFIG?.network?.requestTimeoutMs
    ?? window.__APP_FIREBASE_CONFIG?.network?.requestTimeoutMs
    ?? window.__APP_CONFIG?.network?.requestTimeoutMs
  );
  return Number.isFinite(configured) && configured > 0 ? configured : 12000;
}

/**
 * Create an AbortController with timeout.
 * @param {number} ms - Timeout in milliseconds
 * @returns {[AbortController, () => void]} [controller, clearTimer]
 */
function createAbortWithTimeout(ms) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), ms);
  return [controller, () => clearTimeout(timer)];
}

/**
 * Fetch with configurable timeout.
 * @param {string} url - URL to fetch
 * @param {Object} [options] - Fetch options with optional timeoutMs
 * @returns {Promise<{ok: boolean, status: number, data: *, error?: Error}>}
 */
export async function fetchWithTimeout(url, options = {}) {
  const timeoutMs = Number(options.timeoutMs || getDefaultTimeoutMs());
  const [controller, clearTimer] = createAbortWithTimeout(timeoutMs);

  try {
    const response = await fetch(url, { ...options, signal: controller.signal });
    const data = await response.json().catch(() => ({}));
    return { ok: response.ok, status: response.status, data };
  } catch (error) {
    return { ok: false, status: 0, data: {}, error };
  } finally {
    clearTimer();
  }
}

/**
 * Fetch JSON with standardized response format.
 * @param {string} url - URL to fetch
 * @param {Object} [options] - Fetch options
 * @returns {Promise<{ok: boolean, status: number, data: *}>}
 */
export function fetchJson(url, options = {}) {
  return fetchWithTimeout(url, {
    ...options,
    timeoutMs: options.timeoutMs || getDefaultTimeoutMs(),
  });
}

/**
 * Safe JSON fetch — returns null on failure instead of throwing.
 * @param {string} url - URL to fetch
 * @param {Object} [options] - Fetch options
 * @returns {Promise<*|null>} Parsed JSON or null
 */
export async function safeFetchJson(url, options = {}) {
  const { timeoutMs = getDefaultTimeoutMs(), ...fetchOptions } = options;
  const [controller, clearTimer] = createAbortWithTimeout(timeoutMs);

  try {
    const response = await fetch(url, {
      credentials: 'include',
      ...fetchOptions,
      signal: controller.signal,
    });
    if (!response.ok) return null;
    return await response.json().catch(() => null);
  } catch (error) {
    if (error?.name !== 'AbortError') {
      console.info('Fetch failed:', url, error);
    }
    return null;
  } finally {
    clearTimer();
  }
}

/**
 * Upload FormData via XHR with progress tracking.
 * @param {string} url - Upload endpoint URL
 * @param {FormData} formData - Form data to upload
 * @param {Object} [callbacks] - { onProgress, onSuccess, onError }
 * @returns {Promise<*>}
 */
export function uploadFormData(url, formData, callbacks = {}) {
  const { onProgress, onSuccess, onError } = callbacks;

  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);

    if (onProgress) {
      xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
          onProgress(Math.round((e.loaded / e.total) * 100));
        }
      };
    }

    xhr.onload = () => {
      if (xhr.status === 200) {
        try {
          const result = JSON.parse(xhr.responseText);
          onSuccess?.(result);
          resolve(result);
        } catch {
          onError?.('Invalid response format');
          reject(new Error('Invalid response format'));
        }
      } else {
        const msg = `Upload failed with status ${xhr.status}`;
        onError?.(msg);
        reject(new Error('Upload failed'));
      }
    };

    xhr.onerror = () => {
      onError?.('Connection error during upload');
      reject(new Error('Upload failed'));
    };

    xhr.ontimeout = () => {
      onError?.('Upload timeout');
      reject(new Error('Upload timeout'));
    };

    xhr.send(formData);
  });
}

/**
 * Debounced wrapper for safeFetchJson — avoids duplicate concurrent requests.
 * @param {number} wait - Debounce wait in ms (default 300)
 * @returns {Function} Debounced safeFetchJson
 */
export function createDebouncedFetcher(wait = 300) {
  return debounce((url, options) => safeFetchJson(url, options), wait);
}