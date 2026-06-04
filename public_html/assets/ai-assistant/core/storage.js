/**
 * BroxLab AI Assistant - Storage Module
 * Provides localStorage-based history and settings persistence
 *
 * v2.1.0 - Added error resilience, quota handling, and migration support
 * v2.1.1 - Safe helpers moved to module scope for cross-store reuse
 */

/**
 * Safely read from storage with error handling
 * @param {Storage} storage - The storage backend (default: localStorage)
 * @param {string} key - The key to read
 * @returns {string|null} The stored value or null
 */
function safeGetItem(storage, key) {
  try {
    return storage.getItem(key);
  } catch (err) {
    console.warn('[Storage] Read failed:', key, err);
    return null;
  }
}

/**
 * Safely write to storage with quota handling
 * @param {Storage} storage - The storage backend
 * @param {string} key - The key to write
 * @param {string} value - The value to write
 * @param {Function} [onQuotaExceeded] - Optional callback to trim data before retry
 * @returns {boolean} True if write succeeded
 */
function safeSetItem(storage, key, value, onQuotaExceeded) {
  try {
    storage.setItem(key, value);
    return true;
  } catch (err) {
    if (err.name === 'QuotaExceededError' || err.code === 22) {
      console.warn('[Storage] Quota exceeded for key:', key);
      if (typeof onQuotaExceeded === 'function') {
        onQuotaExceeded();
        try {
          storage.setItem(key, value);
          return true;
        } catch (_) {
          console.error('[Storage] Still cannot write after trimming');
        }
      }
    }
    console.error('[Storage] Write failed:', key, err);
    return false;
  }
}

/**
 * Safely remove from storage
 * @param {Storage} storage - The storage backend
 * @param {string} key - The key to remove
 */
function safeRemoveItem(storage, key) {
  try {
    storage.removeItem(key);
  } catch (err) {
    console.warn('[Storage] Remove failed:', key, err);
  }
}

/**
 * Create a history store for managing chat history
 * @param {string|Object} storageKeyOrOptions - The localStorage key or options object
 * @param {Object} options - Store options
 * @returns {Object} History store API
 */
export function createHistoryStore(storageKeyOrOptions, options = {}) {
  const config = typeof storageKeyOrOptions === 'string'
    ? { storageKey: storageKeyOrOptions, ...options }
    : {
      storageKey: storageKeyOrOptions?.chatKey || storageKeyOrOptions?.storageKey || 'brox.assistant.history',
      storage: storageKeyOrOptions?.storage || window.localStorage,
      maxMessages: storageKeyOrOptions?.maxMessages || options.maxMessages || 100,
      autoSave: storageKeyOrOptions?.autoSave ?? options.autoSave ?? true,
      activityKey: storageKeyOrOptions?.activityKey || options.activityKey,
      inactivityMs: storageKeyOrOptions?.inactivityMs || options.inactivityMs || 0,
      ...options,
    };

  const storage = config.storage || window.localStorage;
  const storageKey = config.storageKey;
  const maxMessages = Number(config.maxMessages) || 100;
  const autoSave = config.autoSave !== false;

  let history = [];

  /** Quota exceeded callback: trim oldest 20% of history */
  function onHistoryQuotaExceeded() {
    const trimCount = Math.max(1, Math.floor(history.length * 0.2));
    history = history.slice(trimCount);
    console.warn('[Storage] Trimmed', trimCount, 'oldest history entries');
  }

  function load() {
    let expired = false;

    try {
      const stored = safeGetItem(storage, storageKey);
      if (stored) {
        const parsed = JSON.parse(stored);
        history = Array.isArray(parsed) ? parsed : [];
      } else {
        history = [];
      }

      if (history.length > maxMessages) {
        history = history.slice(-maxMessages);
        save();
      }

      const lastActivity = config.activityKey
        ? Number(safeGetItem(storage, config.activityKey) || 0)
        : 0;
      if (config.inactivityMs && lastActivity > 0 && Date.now() - lastActivity > config.inactivityMs) {
        expired = true;
        history = [];
        save();
      }
    } catch (err) {
      console.error('[HistoryStore] Load failed:', err);
      history = [];
      expired = false;
    }

    return { history: [...history], expired };
  }

  function save() {
    if (!autoSave) return;
    safeSetItem(storage, storageKey, JSON.stringify(history), onHistoryQuotaExceeded);
  }

  function add(message) {
    if (!message || !message.role || message.text === undefined) {
      throw new Error('Invalid message: must have role and text properties');
    }

    const msg = {
      role: message.role,
      text: message.text,
      ts: message.ts || new Date().toISOString(),
      ...message.metadata,
    };

    history.push(msg);

    if (history.length > maxMessages) {
      history = history.slice(-maxMessages);
    }

    save();
    return msg;
  }

  function getAll() {
    return [...history];
  }

  function get(index) {
    return history[index] || null;
  }

  function clear() {
    history = [];
    safeRemoveItem(storage, storageKey);
    if (config.activityKey) {
      safeRemoveItem(storage, config.activityKey);
    }
  }

  function getRecent(count = 10) {
    return history.slice(-count);
  }

  function export_() {
    return JSON.stringify(history, null, 2);
  }

  function updateActivity() {
    if (!config.activityKey) return;
    safeSetItem(storage, config.activityKey, String(Date.now()));
  }

  function import_(jsonStr) {
    try {
      const imported = JSON.parse(jsonStr);
      if (!Array.isArray(imported)) {
        throw new Error('Import data must be an array');
      }
      history = imported.slice(-maxMessages);
      save();
      return true;
    } catch (err) {
      console.error('[HistoryStore] Import failed:', err);
      return false;
    }
  }

  // Initialize
  load();

  return {
    load,
    save,
    add,
    getAll,
    get,
    clear,
    getRecent,
    updateActivity,
    export: export_,
    import: import_,
    get length() { return history.length; },
  };
}

/**
 * Create a settings store for managing preferences
 * @param {string} storageKey - The localStorage key to use
 * @param {Object} defaults - Default settings
 * @returns {Object} Settings store API
 */
export function createSettingsStore(storageKey, defaults = {}) {
  const storage = window.localStorage;
  let settings = { ...defaults };

  function load() {
    const stored = safeGetItem(storage, storageKey);
    if (stored) {
      try {
        settings = { ...defaults, ...JSON.parse(stored) };
      } catch (err) {
        console.warn('[SettingsStore] Parse failed, using defaults:', err);
        settings = { ...defaults };
      }
    }
    return settings;
  }

  function save() {
    safeSetItem(storage, storageKey, JSON.stringify(settings));
  }

  function get(key) { return settings[key]; }

  function set(key, value) {
    settings[key] = value;
    save();
    return value;
  }

  function update(updates) {
    Object.assign(settings, updates);
    save();
    return settings;
  }

  function getAll() { return { ...settings }; }

  function reset() {
    settings = { ...defaults };
    safeRemoveItem(storage, storageKey);
  }

  load();

  return { load, save, get, set, update, getAll, reset };
}

/**
 * Create a session store for temporary in-memory state
 * @param {Object} initialState - Initial state
 * @returns {Object} Session store API
 */
export function createSessionStore(initialState = {}) {
  let state = { ...initialState };
  const listeners = new Set();

  function getState() { return { ...state }; }

  function setState(updates) {
    state = { ...state, ...updates };
    notifyListeners();
    return state;
  }

  function subscribe(callback) {
    listeners.add(callback);
    return () => listeners.delete(callback);
  }

  function notifyListeners() {
    listeners.forEach(callback => {
      try { callback(state); } catch (err) { console.error('[SessionStore] Listener error:', err); }
    });
  }

  function get(key) { return state[key]; }

  function set(key, value) {
    state[key] = value;
    notifyListeners();
    return value;
  }

  function reset() {
    state = { ...initialState };
    notifyListeners();
  }

  return { getState, setState, subscribe, get, set, reset };
}
