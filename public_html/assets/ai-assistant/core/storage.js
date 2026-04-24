/**
 * BroxLab AI Assistant - Storage Module
 * Provides localStorage-based history and settings persistence
 */

/**
 * Create a history store for managing chat history
 * @param {string} storageKey - The localStorage key to use
 * @param {Object} options - Store options
 * @returns {Object} History store API
 */
export function createHistoryStore(storageKey, options = {}) {
    const maxMessages = options.maxMessages || 100;
    const autoSave = options.autoSave !== false;

    let history = [];

    // Load history from storage
    function load() {
        try {
            const stored = window.localStorage.getItem(storageKey);
            if (stored) {
                history = JSON.parse(stored);
                // Limit to maxMessages
                if (history.length > maxMessages) {
                    history = history.slice(-maxMessages);
                    save();
                }
            }
        } catch (err) {
            console.error('Failed to load history:', err);
            history = [];
        }
        return history;
    }

    // Save history to storage
    function save() {
        if (!autoSave) return;
        try {
            window.localStorage.setItem(storageKey, JSON.stringify(history));
        } catch (err) {
            console.error('Failed to save history:', err);
        }
    }

    // Add a message to history
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

        // Limit to maxMessages
        if (history.length > maxMessages) {
            history = history.slice(-maxMessages);
        }

        save();
        return msg;
    }

    // Get all messages
    function getAll() {
        return [...history];
    }

    // Get message by index
    function get(index) {
        return history[index] || null;
    }

    // Clear history
    function clear() {
        history = [];
        try {
            window.localStorage.removeItem(storageKey);
        } catch (err) {
            console.error('Failed to clear history:', err);
        }
    }

    // Get recent messages
    function getRecent(count = 10) {
        return history.slice(-count);
    }

    // Export to JSON
    function export_() {
        return JSON.stringify(history, null, 2);
    }

    // Import from JSON
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
            console.error('Failed to import history:', err);
            return false;
        }
    }

    // Initialize by loading from storage
    load();

    return {
        load,
        save,
        add,
        getAll,
        get,
        clear,
        getRecent,
        export: export_,
        import: import_,
        get length() {
            return history.length;
        },
    };
}

/**
 * Create a settings store for managing preferences
 * @param {string} storageKey - The localStorage key to use
 * @param {Object} defaults - Default settings
 * @returns {Object} Settings store API
 */
export function createSettingsStore(storageKey, defaults = {}) {
    let settings = { ...defaults };

    // Load settings from storage
    function load() {
        try {
            const stored = window.localStorage.getItem(storageKey);
            if (stored) {
                const loaded = JSON.parse(stored);
                settings = { ...defaults, ...loaded };
            }
        } catch (err) {
            console.error('Failed to load settings:', err);
            settings = { ...defaults };
        }
        return settings;
    }

    // Save settings to storage
    function save() {
        try {
            window.localStorage.setItem(storageKey, JSON.stringify(settings));
        } catch (err) {
            console.error('Failed to save settings:', err);
        }
    }

    // Get a setting
    function get(key) {
        return settings[key];
    }

    // Set a setting
    function set(key, value) {
        settings[key] = value;
        save();
        return value;
    }

    // Update multiple settings
    function update(updates) {
        Object.assign(settings, updates);
        save();
        return settings;
    }

    // Get all settings
    function getAll() {
        return { ...settings };
    }

    // Reset to defaults
    function reset() {
        settings = { ...defaults };
        try {
            window.localStorage.removeItem(storageKey);
        } catch (err) {
            console.error('Failed to reset settings:', err);
        }
    }

    // Initialize by loading from storage
    load();

    return {
        load,
        save,
        get,
        set,
        update,
        getAll,
        reset,
    };
}

/**
 * Create a session store for temporary data
 * @param {Object} initialState - Initial state
 * @returns {Object} Session store API
 */
export function createSessionStore(initialState = {}) {
    let state = { ...initialState };
    const listeners = new Set();

    // Get state
    function getState() {
        return { ...state };
    }

    // Set state
    function setState(updates) {
        state = { ...state, ...updates };
        notifyListeners();
        return state;
    }

    // Subscribe to changes
    function subscribe(callback) {
        listeners.add(callback);
        return () => listeners.delete(callback);
    }

    // Notify all listeners
    function notifyListeners() {
        listeners.forEach(callback => {
            try {
                callback(state);
            } catch (err) {
                console.error('Error in state listener:', err);
            }
        });
    }

    // Get a value
    function get(key) {
        return state[key];
    }

    // Set a value
    function set(key, value) {
        state[key] = value;
        notifyListeners();
        return value;
    }

    // Reset to initial state
    function reset() {
        state = { ...initialState };
        notifyListeners();
    }

    return {
        getState,
        setState,
        subscribe,
        get,
        set,
        reset,
    };
}
