// public_html/assets/firebase/v2/debug.js
function getLoggingConfig() {
  if (typeof window === "undefined") return {};
  const cfg = window.__APP_FIREBASE_CONFIG || window.__APP_CONFIG;
  if (!cfg || typeof cfg !== "object") return {};
  return cfg.logging && typeof cfg.logging === "object" ? cfg.logging : {};
}
if (typeof window !== "undefined" && window.__FC_DEBUG === void 0) {
  const loggingCfg = getLoggingConfig();
  window.__FC_DEBUG = loggingCfg.consoleEnabled === true;
}
var DEFAULT_LEVEL = "warn";
var getLevel = () => {
  if (typeof window === "undefined") return DEFAULT_LEVEL;
  if (window.__FC_DEBUG_LEVEL) return window.__FC_DEBUG_LEVEL;
  const loggingCfg = getLoggingConfig();
  return loggingCfg.level || DEFAULT_LEVEL;
};
var MODULE_DEBUG_MAP = {
  "init": false,
  "config": false,
  "auth": false,
  "messaging": false,
  "analytics": false,
  "notifications": false,
  "offline": false,
  "sync": false,
  "tokens": false,
  "scheduled": false,
  "remoteConfig": false,
  "all": false
};
function initializeModuleFlags() {
  if (typeof window !== "undefined" && window.__FC_DEBUG_MODULES) {
    const modules = window.__FC_DEBUG_MODULES;
    if (Array.isArray(modules)) {
      modules.forEach((m) => {
        if (m in MODULE_DEBUG_MAP) MODULE_DEBUG_MAP[m] = true;
      });
    } else if (typeof modules === "object") {
      Object.assign(MODULE_DEBUG_MAP, modules);
    }
  }
}
initializeModuleFlags();
function normalizeLevel(level) {
  const raw = String(level || "").toLowerCase();
  return ["error", "warn", "info", "log", "all"].includes(raw) ? raw : DEFAULT_LEVEL;
}
function shouldLog(level) {
  const levels = { "error": 0, "warn": 1, "info": 2, "log": 3, "all": 4 };
  const currentLevel = normalizeLevel(getLevel());
  const requestedLevel = normalizeLevel(level);
  if (currentLevel === "all") return true;
  return levels[requestedLevel] <= levels[currentLevel];
}
function formatTimestamp() {
  const now = /* @__PURE__ */ new Date();
  return `${now.getHours().toString().padStart(2, "0")}:${now.getMinutes().toString().padStart(2, "0")}:${now.getSeconds().toString().padStart(2, "0")}.${now.getMilliseconds().toString().padStart(3, "0")}`;
}
var DebugUtils = {
  // Global debug check - only ON when explicitly true
  isDebug: () => typeof window !== "undefined" ? window.__FC_DEBUG === true : false,
  // Per-module debug check
  isEnabled: (module) => {
    if (!DebugUtils.isDebug()) return false;
    const hasOverrides = typeof window !== "undefined" && window.__FC_DEBUG_MODULES !== void 0;
    if (!hasOverrides) return true;
    if (MODULE_DEBUG_MAP["all"]) return true;
    return MODULE_DEBUG_MAP[module] === true;
  },
  // Enable/disable per-module debugging (requires window.__FC_DEBUG = true)
  enable: (module) => {
    if (module === "all") {
      Object.keys(MODULE_DEBUG_MAP).forEach((k) => MODULE_DEBUG_MAP[k] = true);
    } else if (module in MODULE_DEBUG_MAP) {
      MODULE_DEBUG_MAP[module] = true;
    }
    if (DebugUtils.isDebug() && typeof window !== "undefined") {
      console.log(`[Firebase:v2:DEBUG] Module enabled: ${module}`);
    }
  },
  disable: (module) => {
    if (module === "all") {
      Object.keys(MODULE_DEBUG_MAP).forEach((k) => {
        if (k !== "all") MODULE_DEBUG_MAP[k] = false;
      });
    } else if (module in MODULE_DEBUG_MAP) {
      MODULE_DEBUG_MAP[module] = false;
    }
    if (DebugUtils.isDebug() && typeof window !== "undefined") {
      console.log(`[Firebase:v2:DEBUG] Module disabled: ${module}`);
    }
  },
  // Get all module debug states
  getModuleStates: () => ({ ...MODULE_DEBUG_MAP }),
  // Set module states
  setModuleStates: (states) => {
    Object.assign(MODULE_DEBUG_MAP, states);
  },
  // Get current debug status
  getStatus: () => {
    const isDebugEnabled = DebugUtils.isDebug();
    const debugLevel = getLevel();
    const allModulesEnabled = MODULE_DEBUG_MAP["all"];
    const enabledModules = Object.keys(MODULE_DEBUG_MAP).filter((m) => m !== "all" && MODULE_DEBUG_MAP[m]);
    return {
      debugEnabled: isDebugEnabled,
      debugLevel,
      debugAll: allModulesEnabled,
      enabledModules,
      allStates: { ...MODULE_DEBUG_MAP }
    };
  },
  // Standard logging functions
  log: (...args) => {
    if (DebugUtils.isDebug() && shouldLog("log")) console.log(`[Firebase:v2:${formatTimestamp()}]`, ...args);
  },
  info: (...args) => {
    if (DebugUtils.isDebug() && shouldLog("info")) console.info(`[Firebase:v2:${formatTimestamp()}]`, ...args);
  },
  warn: (...args) => {
    if (DebugUtils.isDebug() && shouldLog("warn")) console.warn(`[Firebase:v2:${formatTimestamp()}]`, ...args);
  },
  error: (...args) => {
    if (DebugUtils.isDebug() && shouldLog("error")) console.error(`[Firebase:v2:${formatTimestamp()}]`, ...args);
  },
  // Module-specific logging (only logs if module debug enabled)
  moduleLog: (module, ...args) => {
    if (DebugUtils.isEnabled(module)) {
      console.log(`[Firebase:v2:${module}:${formatTimestamp()}]`, ...args);
    }
  },
  moduleInfo: (module, ...args) => {
    if (DebugUtils.isEnabled(module)) {
      console.info(`[Firebase:v2:${module}:${formatTimestamp()}]`, ...args);
    }
  },
  moduleWarn: (module, ...args) => {
    if (DebugUtils.isEnabled(module)) {
      console.warn(`[Firebase:v2:${module}:${formatTimestamp()}]`, ...args);
    }
  },
  moduleError: (module, ...args) => {
    if (DebugUtils.isEnabled(module)) {
      console.error(`[Firebase:v2:${module}:${formatTimestamp()}]`, ...args);
    }
  }
};
var debug_default = DebugUtils;

export {
  DebugUtils,
  debug_default
};
