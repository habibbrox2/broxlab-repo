/**
 * StateManager - Single Source of Truth for Chat State
 * Path: /public_html/ai/js/modules/state/StateManager.js
 *
 * Implements Redux-lite pattern with:
 *  - Single immutable state object
 *  - Action-based mutations
 *  - Event-driven subscribers
 *  - localStorage persistence
 *  - Time-travel debugging support
 */

export class StateManager {
    constructor(config = {}) {
        this.config = {
            persistKey: 'brox.ai.state',
            maxHistorySize: 50,
            ...config,
        };

        // Initialize state
        this.state = this.loadPersistedState() || this.getInitialState();
        this.subscribers = new Map();
        this.history = [];
        this.historyIndex = -1;
        this.isDispatching = false;

        console.log('[StateManager] Initialized', { state: this.state });
    }

    /**
     * Get initial state structure
     */
    getInitialState() {
        return {
            // Chat
            messages: [],
            conversations: [],
            currentConversationId: null,
            isLoading: false,
            error: null,

            // UI
            sidebarOpen: true,
            darkMode: this.loadDarkModePreference(),
            activeTab: 'chat', // chat, settings, history

            // Models & Providers
            currentModel: null,
            availableModels: [],
            selectedProvider: null,

            // Settings
            customPrompt: null,
            responseTone: 'professional',
            contextDepth: 'full',
            notificationLevel: 'banner', // silent, sound, banner, all
            autoComplete: true,

            // Image Context
            imageContext: [],
            maxImages: 5,

            // Metadata
            lastUpdated: new Date().toISOString(),
            version: '2.0.0',
        };
    }

    /**
     * Load persisted state from localStorage
     */
    loadPersistedState() {
        try {
            const stored = localStorage.getItem(this.config.persistKey);
            if (!stored) return null;

            const state = JSON.parse(stored);
            console.log('[StateManager] Loaded persisted state', { keys: Object.keys(state) });
            return state;
        } catch (e) {
            console.error('[StateManager] Failed to load persisted state:', e);
            return null;
        }
    }

    /**
     * Load dark mode preference
     */
    loadDarkModePreference() {
        try {
            const stored = localStorage.getItem('brox.ai.darkMode');
            if (stored !== null) return stored === 'true';

            // Check system preference
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return true;
            }
            return false;
        } catch (e) {
            return false;
        }
    }

    /**
     * Get current state (immutable reference)
     */
    getState() {
        return this.state;
    }

    /**
     * Get specific state slice
     */
    getStateSlice(path) {
        return this.getNestedValue(this.state, path);
    }

    /**
     * Set state (merge with existing state)
     */
    setState(updates) {
        if (this.isDispatching) {
            console.warn('[StateManager] State update during dispatch, queuing');
            return;
        }

        this.isDispatching = true;

        try {
            // Create new state (shallow merge)
            const oldState = this.state;
            this.state = { ...this.state, ...updates };

            // Persist to localStorage
            this.persistState();

            // Add to history for time-travel
            this.addToHistory(oldState);

            // Notify subscribers
            this.notifySubscribers(oldState, this.state);

            console.log('[StateManager] State updated', { updates });
        } catch (e) {
            console.error('[StateManager] Error in setState:', e);
            this.state = oldState; // Rollback
        } finally {
            this.isDispatching = false;
        }
    }

    /**
     * Update nested state (e.g., 'messages.0.content')
     */
    setNestedState(path, value) {
        const oldState = this.state;
        const newState = JSON.parse(JSON.stringify(oldState)); // Deep copy

        this.setNestedValue(newState, path, value);
        this.state = newState;

        this.persistState();
        this.addToHistory(oldState);
        this.notifySubscribers(oldState, this.state);

        console.log('[StateManager] Nested state updated', { path, value });
    }

    /**
     * Add message to conversation
     */
    addMessage(message) {
        const messages = [...this.state.messages, message];
        this.setState({ messages, lastUpdated: new Date().toISOString() });
        this.emit('message:added', message);
    }

    /**
     * Update message by index
     */
    updateMessage(index, updates) {
        const messages = [...this.state.messages];
        messages[index] = { ...messages[index], ...updates };
        this.setState({ messages });
        this.emit('message:updated', { index, message: messages[index] });
    }

    /**
     * Clear all messages
     */
    clearMessages() {
        this.setState({ messages: [] });
        this.emit('messages:cleared');
    }

    /**
     * Add conversation to list
     */
    addConversation(conversation) {
        const conversations = [...this.state.conversations, conversation];
        this.setState({ conversations });
        this.emit('conversation:added', conversation);
    }

    /**
     * Update conversation metadata
     */
    updateConversation(id, updates) {
        const conversations = this.state.conversations.map((c) =>
            c.id === id ? { ...c, ...updates } : c
        );
        this.setState({ conversations });
        this.emit('conversation:updated', { id, updates });
    }

    /**
     * Delete conversation
     */
    deleteConversation(id) {
        const conversations = this.state.conversations.filter((c) => c.id !== id);
        this.setState({ conversations });
        this.emit('conversation:deleted', { id });
    }

    /**
     * Set loading state
     */
    setLoading(isLoading, error = null) {
        this.setState({ isLoading, error });
        this.emit(isLoading ? 'loading:start' : 'loading:end', { error });
    }

    /**
     * Set error state
     */
    setError(error) {
        this.setState({ error, isLoading: false });
        this.emit('error', { error });
    }

    /**
     * Subscribe to state changes
     */
    subscribe(eventName, callback) {
        if (!this.subscribers.has(eventName)) {
            this.subscribers.set(eventName, []);
        }
        this.subscribers.get(eventName).push(callback);

        // Return unsubscribe function
        return () => {
            const callbacks = this.subscribers.get(eventName);
            const index = callbacks.indexOf(callback);
            if (index > -1) {
                callbacks.splice(index, 1);
            }
        };
    }

    /**
     * Emit event to all subscribers
     */
    emit(eventName, data = null) {
        if (!this.subscribers.has(eventName)) return;

        const callbacks = this.subscribers.get(eventName);
        callbacks.forEach((callback) => {
            try {
                callback(data);
            } catch (e) {
                console.error(`[StateManager] Error in subscriber for ${eventName}:`, e);
            }
        });
    }

    /**
     * Notify subscribers of state changes
     */
    notifySubscribers(oldState, newState) {
        // Emit event for each changed key
        Object.keys(newState).forEach((key) => {
            if (JSON.stringify(oldState[key]) !== JSON.stringify(newState[key])) {
                this.emit(`state:${key}:changed`, { oldValue: oldState[key], newValue: newState[key] });
            }
        });

        // Emit general change event
        this.emit('state:changed', { oldState, newState });
    }

    /**
     * Persist state to localStorage
     */
    persistState() {
        try {
            localStorage.setItem(this.config.persistKey, JSON.stringify(this.state));
        } catch (e) {
            console.error('[StateManager] Failed to persist state:', e);
            // Silently fail if quota exceeded
        }
    }

    /**
     * Add to history for time-travel
     */
    addToHistory(state) {
        // Remove future history if traveling back in time
        this.history = this.history.slice(0, this.historyIndex + 1);

        // Add new state to history
        this.history.push(JSON.parse(JSON.stringify(state)));

        // Limit history size
        if (this.history.length > this.config.maxHistorySize) {
            this.history.shift();
        } else {
            this.historyIndex++;
        }
    }

    /**
     * Undo to previous state
     */
    undo() {
        if (this.historyIndex > 0) {
            this.historyIndex--;
            this.state = JSON.parse(JSON.stringify(this.history[this.historyIndex]));
            this.persistState();
            this.emit('history:undo', { state: this.state });
            console.log('[StateManager] Undo performed', { historyIndex: this.historyIndex });
        }
    }

    /**
     * Redo to next state
     */
    redo() {
        if (this.historyIndex < this.history.length - 1) {
            this.historyIndex++;
            this.state = JSON.parse(JSON.stringify(this.history[this.historyIndex]));
            this.persistState();
            this.emit('history:redo', { state: this.state });
            console.log('[StateManager] Redo performed', { historyIndex: this.historyIndex });
        }
    }

    /**
     * Reset to initial state
     */
    reset() {
        this.state = this.getInitialState();
        this.history = [];
        this.historyIndex = -1;
        this.persistState();
        this.emit('state:reset', { state: this.state });
        console.log('[StateManager] State reset');
    }

    /**
     * Export state for debugging
     */
    exportState() {
        return JSON.parse(JSON.stringify(this.state));
    }

    /**
     * Import state (for testing or recovery)
     */
    importState(importedState) {
        this.state = { ...this.getInitialState(), ...importedState };
        this.persistState();
        this.emit('state:imported', { state: this.state });
        console.log('[StateManager] State imported');
    }

    /**
     * Helper: Get nested value from object
     */
    getNestedValue(obj, path) {
        return path.split('.').reduce((current, prop) => current?.[prop], obj);
    }

    /**
     * Helper: Set nested value in object
     */
    setNestedValue(obj, path, value) {
        const parts = path.split('.');
        const last = parts.pop();
        const target = parts.reduce((current, prop) => {
            if (!(prop in current)) current[prop] = {};
            return current[prop];
        }, obj);
        target[last] = value;
    }

    /**
     * Get memory usage (for debugging)
     */
    getMemoryUsage() {
        const stateSize = JSON.stringify(this.state).length;
        const historySize = this.history.reduce((sum, s) => sum + JSON.stringify(s).length, 0);
        return {
            stateSize: (stateSize / 1024).toFixed(2) + ' KB',
            historySize: (historySize / 1024).toFixed(2) + ' KB',
            historyItems: this.history.length,
            totalSize: ((stateSize + historySize) / 1024).toFixed(2) + ' KB',
        };
    }

    /**
     * Debug logging
     */
    debug() {
        console.group('[StateManager] Debug Info');
        console.log('Current State:', this.state);
        console.log('History:', this.history);
        console.log('History Index:', this.historyIndex);
        console.log('Subscribers:', Object.fromEntries(this.subscribers));
        console.log('Memory Usage:', this.getMemoryUsage());
        console.groupEnd();
    }
}

export default StateManager;
