/**
 * Shared Fetch/API Utilities
 * Consolidated from: account-settings-shared.js, analytics-dashboard.js, media-upload.js
 */

function getDefaultTimeoutMs() {
    const configured = Number(
        window.__APP_JS_CONFIG?.network?.requestTimeoutMs
        ?? window.__APP_FIREBASE_CONFIG?.network?.requestTimeoutMs
        ?? window.__APP_CONFIG?.network?.requestTimeoutMs
    );
    return Number.isFinite(configured) && configured > 0 ? configured : 12000;
}

/**
 * Fetch with configurable timeout
 * @param {string} url - URL to fetch
 * @param {Object} options - Fetch options with optional timeoutMs
 * @returns {Promise<{ok: boolean, status: number, data: *}>}
 */
export async function fetchWithTimeout(url, options = {}) {
    const timeoutMs = Number(options.timeoutMs || getDefaultTimeoutMs());
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    try {
        const response = await fetch(url, {
            ...options,
            signal: controller.signal
        });
        const data = await response.json().catch(() => ({}));
        return { ok: response.ok, status: response.status, data };
    } catch (error) {
        return { ok: false, status: 0, data: {}, error };
    } finally {
        clearTimeout(timer);
    }
}

/**
 * Fetch JSON with standardized response format
 * @param {string} url - URL to fetch
 * @param {Object} options - Fetch options
 * @returns {Promise<{ok: boolean, status: number, data: *}>}
 */
export async function fetchJson(url, options = {}) {
    return fetchWithTimeout(url, { ...options, timeoutMs: options.timeoutMs || getDefaultTimeoutMs() });
}

/**
 * Safe JSON fetch with silent failure
 * @param {string} url - URL to fetch
 * @param {Object} options - Fetch options with optional timeoutMs
 * @returns {Promise<*|null>} Parsed JSON or null
 */
export async function safeFetchJson(url, options = {}) {
    const { timeoutMs = getDefaultTimeoutMs(), ...fetchOptions } = options;
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

    try {
        const response = await fetch(url, {
            credentials: 'include',
            ...fetchOptions,
            signal: controller.signal
        });
        if (!response.ok) return null;
        return await response.json().catch(() => null);
    } catch (error) {
        if (error?.name !== 'AbortError') {
            console.debug('Fetch failed:', url, error);
        }
        return null;
    } finally {
        clearTimeout(timeoutId);
    }
}

/**
 * Upload FormData via XHR with progress tracking
 * @param {string} url - Upload endpoint URL
 * @param {FormData} formData - Form data to upload
 * @param {Object} callbacks - Callback object with methods, onProgress, onSuccess, onError
 * @returns {Promise<void>}
 */
export async function uploadFormData(url, formData, callbacks = {}) {
    const { onProgress, onSuccess, onError } = callbacks;

    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);

        if (onProgress) {
            xhr.upload.onprogress = function (uploadEvent) {
                if (!uploadEvent.lengthComputable) return;
                const percent = Math.round((uploadEvent.loaded / uploadEvent.total) * 100);
                onProgress(percent);
            };
        }

        xhr.onload = function () {
            if (xhr.status === 200) {
                try {
                    const result = JSON.parse(xhr.responseText);
                    if (onSuccess) onSuccess(result);
                    resolve(result);
                } catch (error) {
                    if (onError) onError('Invalid response format');
                    reject(new Error('Invalid response format'));
                }
            } else {
                if (onError) onError('Upload failed with status ' + xhr.status);
                reject(new Error('Upload failed'));
            }
        };

        xhr.onerror = function () {
            if (onError) onError('Connection error during upload');
            reject(new Error('Upload failed'));
        };

        xhr.send(formData);
    });
}
