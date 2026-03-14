// v2/debug.js
// Centralized debug utilities and per-module logging control
// Supports global debug level + per-module enable/disable toggles

/**
 * DEBUG MODES:
 * 
 * 1. CONSOLE: এক্টিভ করতে: window.__FC_DEBUG = true
 * 2. DEBUG LEVEL সেট করতে: window.__FC_DEBUG_LEVEL = 'error'|'warn'|'info'|'log'|'all'
 * 3. SPECIFIC MODULES চালু করতে: window.__FC_DEBUG_MODULES = ['auth', 'messaging']
 * 
 * 4. DEBUG ALL (সকল ফিচারের সকল লগ): DebugUtils.enable('all')
 *    - এটি চালু করলে সকল মডিউলের লগ কনসলে আসবে
 *    - বন্ধ করতে: DebugUtils.disable('all')
 * 
 * USAGE EXAMPLES:
 *   window.__FC_DEBUG = true;                           // Debug mode চালু
 *   window.__FC_DEBUG_LEVEL = 'all';                    // সব লেভেলের লগ দেখান
 *   DebugUtils.enable('all');                           // সব ফিচার লগ চালু
 *   DebugUtils.getStatus();                             // বর্তমান স্ট্যাটাস দেখুন
 *   DebugUtils.moduleLog('auth', 'Login attempt');      // মডিউলে লগ করুন
 */
function getLoggingConfig() {
  if (typeof window === 'undefined') return {};
  const cfg = window.__APP_FIREBASE_CONFIG || window.__APP_CONFIG;
  if (!cfg || typeof cfg !== 'object') return {};
  return (cfg.logging && typeof cfg.logging === 'object') ? cfg.logging : {};
}

if (typeof window !== 'undefined' && window.__FC_DEBUG === undefined) {
  const loggingCfg = getLoggingConfig();
  window.__FC_DEBUG = loggingCfg.consoleEnabled === true;
}

// Debug level (reads live from window for runtime changes)
const DEFAULT_LEVEL = 'warn';
const getLevel = () => {
  if (typeof window === 'undefined') return DEFAULT_LEVEL;
  if (window.__FC_DEBUG_LEVEL) return window.__FC_DEBUG_LEVEL;
  const loggingCfg = getLoggingConfig();
  return loggingCfg.level || DEFAULT_LEVEL;
};

// Per-module debug toggles (all disabled by default for production)
// Can be controlled via: DebugUtils.enable('messaging'), DebugUtils.disable('auth'), etc.
// Special 'all' module: When enabled via enable('all'), all feature logs will show in console

const MODULE_DEBUG_MAP = {
  'init': false,
  'config': false,
  'auth': false,
  'messaging': false,
  'analytics': false,
  'notifications': false,
  'offline': false,
  'sync': false,
  'tokens': false,
  'scheduled': false,
  'remoteConfig': false,
  'all': false
};

// Initialize module debug flags from window globals
function initializeModuleFlags() {
  if (typeof window !== 'undefined' && window.__FC_DEBUG_MODULES) {
    const modules = window.__FC_DEBUG_MODULES;
    if (Array.isArray(modules)) {
      modules.forEach(m => { if (m in MODULE_DEBUG_MAP) MODULE_DEBUG_MAP[m] = true; });
    } else if (typeof modules === 'object') {
      Object.assign(MODULE_DEBUG_MAP, modules);
    }
  }
}

// Initialize on load
initializeModuleFlags();

function normalizeLevel(level) {
  const raw = String(level || '').toLowerCase();
  return ['error', 'warn', 'info', 'log', 'all'].includes(raw) ? raw : DEFAULT_LEVEL;
}

function shouldLog(level) {
  const levels = { 'error': 0, 'warn': 1, 'info': 2, 'log': 3, 'all': 4 };
  const currentLevel = normalizeLevel(getLevel());
  const requestedLevel = normalizeLevel(level);
  if (currentLevel === 'all') return true;
  return levels[requestedLevel] <= levels[currentLevel];
}

function formatTimestamp() {
  const now = new Date();
  return `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}:${now.getSeconds().toString().padStart(2, '0')}.${now.getMilliseconds().toString().padStart(3, '0')}`;
}

// Main DebugUtils object with per-module support
export const DebugUtils = {
  // Global debug check - only ON when explicitly true
  isDebug: () => (typeof window !== 'undefined' ? (window.__FC_DEBUG === true) : false),

  // Per-module debug check
  isEnabled: (module) => {
    if (!DebugUtils.isDebug()) return false;
    // If no per-module overrides defined, enable all features when debug is ON
    const hasOverrides = (typeof window !== 'undefined' && window.__FC_DEBUG_MODULES !== undefined);
    if (!hasOverrides) return true;
    if (MODULE_DEBUG_MAP['all']) return true;
    return MODULE_DEBUG_MAP[module] === true;
  },

  // Enable/disable per-module debugging (requires window.__FC_DEBUG = true)
  enable: (module) => {
    if (module === 'all') {
      Object.keys(MODULE_DEBUG_MAP).forEach(k => MODULE_DEBUG_MAP[k] = true);
    } else if (module in MODULE_DEBUG_MAP) {
      MODULE_DEBUG_MAP[module] = true;
    }
    if (DebugUtils.isDebug() && typeof window !== 'undefined') {
      console.log(`[Firebase:v2:DEBUG] Module enabled: ${module}`);
    }
  },

  disable: (module) => {
    if (module === 'all') {
      Object.keys(MODULE_DEBUG_MAP).forEach(k => { if (k !== 'all') MODULE_DEBUG_MAP[k] = false; });
    } else if (module in MODULE_DEBUG_MAP) {
      MODULE_DEBUG_MAP[module] = false;
    }
    if (DebugUtils.isDebug() && typeof window !== 'undefined') {
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
    const allModulesEnabled = MODULE_DEBUG_MAP['all'];
    const enabledModules = Object.keys(MODULE_DEBUG_MAP)
      .filter(m => m !== 'all' && MODULE_DEBUG_MAP[m]);

    return {
      debugEnabled: isDebugEnabled,
      debugLevel: debugLevel,
      debugAll: allModulesEnabled,
      enabledModules: enabledModules,
      allStates: { ...MODULE_DEBUG_MAP }
    };
  },

  // Standard logging functions
  log: (...args) => { if (DebugUtils.isDebug() && shouldLog('log')) console.log(`[Firebase:v2:${formatTimestamp()}]`, ...args); },
  info: (...args) => { if (DebugUtils.isDebug() && shouldLog('info')) console.info(`[Firebase:v2:${formatTimestamp()}]`, ...args); },
  warn: (...args) => { if (DebugUtils.isDebug() && shouldLog('warn')) console.warn(`[Firebase:v2:${formatTimestamp()}]`, ...args); },
  error: (...args) => { if (DebugUtils.isDebug() && shouldLog('error')) console.error(`[Firebase:v2:${formatTimestamp()}]`, ...args); },

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

export default DebugUtils;
