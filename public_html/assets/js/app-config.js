/**
 * JS Runtime Configuration
 * Scope: /public/assets/js
 *
 * Primary globals:
 *   window.__APP_JS_CONFIG
 *   window.AppJsConfig.get(path, fallback)
 *
 * Backward compatibility:
 *   - Merges into window.__APP_CONFIG (legacy readers)
 *   - Mirrors getter as window.AppConfig
 */
(function initJsRuntimeConfig(global) {
    const DEFAULT_JS_CONFIG = {
        app: {
            name: 'BroxBhai',
            env: 'production'
        },
        ui: {
            theme: {
                defaultTheme: 'light',
                storageKey: 'broxbhai-theme',
                transitionDuration: 300
            }
        },
        network: {
            requestTimeoutMs: 12000
        },
        notifications: {
            permissionPopupEnabled: true
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

    const modernOverrides = isPlainObject(global.__APP_JS_CONFIG_OVERRIDES) ? global.__APP_JS_CONFIG_OVERRIDES : {};
    const legacyOverrides = isPlainObject(global.__APP_CONFIG_OVERRIDES) ? global.__APP_CONFIG_OVERRIDES : {};

    const mergedJsConfig = deepMerge(
        deepMerge(clone(DEFAULT_JS_CONFIG), legacyOverrides),
        modernOverrides
    );

    global.__APP_JS_CONFIG = mergedJsConfig;

    const legacyGlobal = isPlainObject(global.__APP_CONFIG) ? clone(global.__APP_CONFIG) : {};
    global.__APP_CONFIG = deepMerge(legacyGlobal, mergedJsConfig);

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
        }
    };

    global.AppJsConfig = getter;
    global.AppConfig = getter;
})(window);
