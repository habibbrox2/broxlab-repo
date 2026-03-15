// public_html/assets/js/shared/fetch-utils.js
function getDefaultTimeoutMs() {
  const configured = Number(
    window.__APP_JS_CONFIG?.network?.requestTimeoutMs ?? window.__APP_FIREBASE_CONFIG?.network?.requestTimeoutMs ?? window.__APP_CONFIG?.network?.requestTimeoutMs
  );
  return Number.isFinite(configured) && configured > 0 ? configured : 12e3;
}
async function fetchWithTimeout(url, options = {}) {
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
async function fetchJson(url, options = {}) {
  return fetchWithTimeout(url, { ...options, timeoutMs: options.timeoutMs || getDefaultTimeoutMs() });
}

export {
  fetchWithTimeout,
  fetchJson
};
