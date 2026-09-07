/**
 * JS Runtime Configuration Singleton (ES Module)
 *
 * Exports:
 *   window.__APP_JS_CONFIG   — Merged config object
 *   window.AppJsConfig.get(path, fallback) — Safe nested accessor
 *
 * Backward compatibility:
 *   Merges into window.__APP_CONFIG (legacy readers)
 *   Aliased as window.AppConfig
 */

const DEFAULT_JS_CONFIG = Object.freeze({
  app: Object.freeze({ name: 'BroxBhai', env: 'production', }),
  ui: Object.freeze({
    theme: Object.freeze({ defaultTheme: 'light', storageKey: 'broxbhai-theme', transitionDuration: 300, }),
  }),
  network: Object.freeze({ requestTimeoutMs: 12000, }),
  notifications: Object.freeze({ permissionPopupEnabled: true, }),
  ai: Object.freeze({}),
});

function isPlainObject(value) {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function deepMerge(base, source) {
  if (!isPlainObject(source)) return base;
  for (const key of Object.keys(source)) {
    const sv = source[key];
    const bv = base[key];
    if (isPlainObject(sv) && isPlainObject(bv)) {
      deepMerge(bv, sv);
    } else {
      base[key] = sv;
    }
  }
  return base;
}

function clone(value) {
  return JSON.parse(JSON.stringify(value));
}

/* Merge overrides */
const mergedJsConfig = deepMerge(
  deepMerge(clone(DEFAULT_JS_CONFIG), isPlainObject(window.__APP_CONFIG_OVERRIDES) ? window.__APP_CONFIG_OVERRIDES : {}),
  isPlainObject(window.__APP_JS_CONFIG_OVERRIDES) ? window.__APP_JS_CONFIG_OVERRIDES : {}
);

window.__APP_JS_CONFIG = mergedJsConfig;

/* Legacy backwards compat */
window.__APP_CONFIG = deepMerge(
  isPlainObject(window.__APP_CONFIG) ? clone(window.__APP_CONFIG) : {},
  mergedJsConfig
);

const getter = {
  get(path, fallbackValue) {
    if (!path || typeof path !== 'string') return mergedJsConfig;
    const parts = path.split('.');
    let cursor = mergedJsConfig;
    for (const part of parts) {
      if (!cursor || !Object.prototype.hasOwnProperty.call(cursor, part)) {
        return fallbackValue;
      }
      cursor = cursor[part];
    }
    return cursor;
  },
};

window.AppJsConfig = getter;
window.AppConfig = getter;
