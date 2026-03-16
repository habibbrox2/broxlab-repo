/**
 * Firebase Runtime Configuration
 * Scope: /public/assets/firebase/v2
 *
 * Primary globals:
 *   window.__APP_FIREBASE_CONFIG
 *   window.FirebaseRuntimeConfig.get(path, fallback)
 *
 * Backward compatibility:
 *   - Merges into window.__APP_CONFIG (legacy readers)
 *   - Initializes legacy debug globals (__FC_DEBUG, __FC_DEBUG_LEVEL, __FC_DEBUG_MODULES)
 */
(function initFirebaseRuntimeConfig(global) {
  const DEFAULT_FIREBASE_CONFIG = {
    logging: {
      consoleEnabled: false,
      level: 'all',
      firebaseModules: null
    },
    network: {
      requestTimeoutMs: 12000
    },
    notifications: {
      autoInitFirebase: true
    }
  };

  function isPlainObject(value) {
    return !!value && typeof value === 'object' && !Array.isArray(value);
  }

  function deepMerge(base, source) {
    if (!isPlainObject(source)) return base;
    Object.keys(source).forEach((key) => {
      const sourceValue = source[key];
      const baseValue = base[key];
      if (isPlainObject(sourceValue) && isPlainObject(baseValue)) {
        deepMerge(baseValue, sourceValue);
        return;
      }
      base[key] = sourceValue;
    });
    return base;
  }

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  const modernOverrides = isPlainObject(global.__APP_FIREBASE_CONFIG_OVERRIDES)
    ? global.__APP_FIREBASE_CONFIG_OVERRIDES
    : {};
  const legacyOverrides = isPlainObject(global.__APP_CONFIG_OVERRIDES)
    ? global.__APP_CONFIG_OVERRIDES
    : {};

  const mergedFirebaseConfig = deepMerge(
    deepMerge(clone(DEFAULT_FIREBASE_CONFIG), legacyOverrides),
    modernOverrides
  );

  global.__APP_FIREBASE_CONFIG = mergedFirebaseConfig;

  const legacyGlobal = isPlainObject(global.__APP_CONFIG) ? clone(global.__APP_CONFIG) : {};
  global.__APP_CONFIG = deepMerge(legacyGlobal, mergedFirebaseConfig);

  if (global.__FC_DEBUG === undefined) {
    global.__FC_DEBUG = mergedFirebaseConfig.logging.consoleEnabled === true;
  }
  if (global.__FC_DEBUG_LEVEL === undefined) {
    global.__FC_DEBUG_LEVEL = String(mergedFirebaseConfig.logging.level || 'warn');
  }
  if (global.__FC_DEBUG_MODULES === undefined && isPlainObject(mergedFirebaseConfig.logging.firebaseModules)) {
    global.__FC_DEBUG_MODULES = mergedFirebaseConfig.logging.firebaseModules;
  }

  global.FirebaseRuntimeConfig = {
    get(path, fallbackValue) {
      if (!path || typeof path !== 'string') return mergedFirebaseConfig;
      const parts = path.split('.');
      let cursor = mergedFirebaseConfig;
      for (const part of parts) {
        if (!cursor || !Object.prototype.hasOwnProperty.call(cursor, part)) {
          return fallbackValue;
        }
        cursor = cursor[part];
      }
      return cursor;
    }
  };
})(window);
