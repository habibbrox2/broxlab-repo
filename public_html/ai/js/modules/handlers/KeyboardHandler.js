/**
 * KeyboardHandler - Keyboard Shortcuts & Accessibility
 * Path: /public_html/ai/js/modules/handlers/KeyboardHandler.js
 *
 * Handles:
 *  - Keyboard shortcut registration and dispatch
 *  - Accessibility features (focus management, ARIA)
 *  - Navigation (Tab, arrows, Enter)
 *  - Modifier key combinations (Ctrl, Shift, Alt)
 */

export class KeyboardHandler {
    constructor(config = {}) {
        this.config = {
            enabled: true,
            debugMode: false,
            ...config,
        };

        this.shortcuts = new Map();
        this.eventHandlers = new Map();
        this.focusableElements = [];
        this.currentFocusIndex = 0;

        this._registerDefaultShortcuts();
        this._initializeKeyListeners();
    }

    /**
     * Register default shortcuts
     */
    _registerDefaultShortcuts() {
        // Message sending
        this.registerShortcut('Ctrl+Enter', () => this._emit('send:message'), {
            description: 'Send message',
            context: 'input',
        });

        // Focus chat input
        this.registerShortcut('Ctrl+Shift+M', () => this._emit('focus:input'), {
            description: 'Focus chat input',
        });

        // Voice input toggle
        this.registerShortcut('Ctrl+Shift+V', () => this._emit('voice:toggle'), {
            description: 'Toggle voice input',
        });

        // Command menu
        this.registerShortcut('Ctrl+Shift+K', () => this._emit('command:open'), {
            description: 'Open command menu',
        });

        // Clear chat
        this.registerShortcut('Ctrl+Shift+C', () => this._emit('chat:clear'), {
            description: 'Clear chat history',
        });

        // Toggle dark mode
        this.registerShortcut('Ctrl+Shift+D', () => this._emit('theme:toggle'), {
            description: 'Toggle dark mode',
        });

        // Export conversation
        this.registerShortcut('Ctrl+Shift+E', () => this._emit('export:conversation'), {
            description: 'Export conversation',
        });

        // Search
        this.registerShortcut('Ctrl+F', () => this._emit('search:open'), {
            description: 'Open search',
            preventDefault: true,
        });

        // Focus management
        this.registerShortcut('Tab', (e) => this._handleTabNavigation(e), {
            description: 'Tab navigation',
            context: 'navigation',
        });

        this.registerShortcut('Shift+Tab', (e) => this._handleTabNavigation(e), {
            description: 'Reverse tab navigation',
            context: 'navigation',
        });

        // Arrow keys for message navigation
        this.registerShortcut('ArrowUp', () => this._emit('message:previous'), {
            description: 'Previous message',
            context: 'input',
        });

        this.registerShortcut('ArrowDown', () => this._emit('message:next'), {
            description: 'Next message',
            context: 'input',
        });

        // Escape to close modals/menus
        this.registerShortcut('Escape', () => this._emit('modal:close'), {
            description: 'Close modal/menu',
        });

        console.log('[KeyboardHandler] Default shortcuts registered');
    }

    /**
     * Initialize key event listeners
     */
    _initializeKeyListeners() {
        document.addEventListener('keydown', (e) => this._handleKeyDown(e));
        document.addEventListener('keyup', (e) => this._handleKeyUp(e));
    }

    /**
     * Handle keydown event
     */
    _handleKeyDown(event) {
        if (!this.config.enabled) return;

        const shortcut = this._buildShortcutString(event);

        if (this.config.debugMode) {
            console.log('[KeyboardHandler] Keydown:', shortcut);
        }

        const handler = this.shortcuts.get(shortcut);
        if (handler) {
            handler.callback(event);

            if (handler.options.preventDefault) {
                event.preventDefault();
            }
        }
    }

    /**
     * Handle keyup event
     */
    _handleKeyUp(event) {
        if (!this.config.enabled) return;

        const shortcut = this._buildShortcutString(event);
        this._emit('key:up', { shortcut, event });
    }

    /**
     * Build shortcut string from keyboard event
     */
    _buildShortcutString(event) {
        const parts = [];

        if (event.ctrlKey || event.metaKey) parts.push('Ctrl');
        if (event.shiftKey) parts.push('Shift');
        if (event.altKey) parts.push('Alt');

        const key = this._getKeyName(event.key, event.code);
        if (key && key !== 'Control' && key !== 'Shift' && key !== 'Alt' && key !== 'Meta') {
            parts.push(key);
        }

        return parts.join('+') || event.key;
    }

    /**
     * Get readable key name
     */
    _getKeyName(key, code) {
        const keyMap = {
            Enter: 'Enter',
            ' ': 'Space',
            ArrowUp: 'ArrowUp',
            ArrowDown: 'ArrowDown',
            ArrowLeft: 'ArrowLeft',
            ArrowRight: 'ArrowRight',
            Backspace: 'Backspace',
            Delete: 'Delete',
            Escape: 'Escape',
            Tab: 'Tab',
        };

        if (keyMap[key]) {
            return keyMap[key];
        }

        // Letter or number
        if (key.length === 1) {
            return key.toUpperCase();
        }

        return key;
    }

    /**
     * Register custom shortcut
     */
    registerShortcut(shortcut, callback, options = {}) {
        this.shortcuts.set(shortcut, {
            callback,
            options: {
                preventDefault: false,
                context: null,
                description: '',
                ...options,
            },
        });

        if (this.config.debugMode) {
            console.log('[KeyboardHandler] Shortcut registered:', shortcut);
        }
    }

    /**
     * Unregister shortcut
     */
    unregisterShortcut(shortcut) {
        this.shortcuts.delete(shortcut);
        console.log('[KeyboardHandler] Shortcut unregistered:', shortcut);
    }

    /**
     * Get all shortcuts
     */
    getAllShortcuts() {
        return Array.from(this.shortcuts.entries()).map(([shortcut, handler]) => ({
            shortcut,
            description: handler.options.description,
            context: handler.options.context,
        }));
    }

    /**
     * Get shortcuts by context
     */
    getShortcutsByContext(context) {
        return Array.from(this.shortcuts.entries())
            .filter(([_, handler]) => handler.options.context === context)
            .map(([shortcut, handler]) => ({
                shortcut,
                description: handler.options.description,
            }));
    }

    /**
     * Handle Tab navigation for accessibility
     */
    _handleTabNavigation(event) {
        const focusableElements = this._getFocusableElements();

        if (focusableElements.length === 0) return;

        const currentElement = document.activeElement;
        const currentIndex = focusableElements.indexOf(currentElement);

        let nextIndex;
        if (event.shiftKey) {
            nextIndex = currentIndex <= 0 ? focusableElements.length - 1 : currentIndex - 1;
        } else {
            nextIndex = currentIndex >= focusableElements.length - 1 ? 0 : currentIndex + 1;
        }

        focusableElements[nextIndex].focus();
        event.preventDefault();
    }

    /**
     * Get all focusable elements
     */
    _getFocusableElements() {
        const selector =
            'a[href], button, input, textarea, select, [tabindex]:not([tabindex="-1"])';
        return Array.from(document.querySelectorAll(selector)).filter((el) => {
            return !el.hasAttribute('disabled') && el.offsetParent !== null;
        });
    }

    /**
     * Focus next element
     */
    focusNext() {
        const focusable = this._getFocusableElements();
        const current = document.activeElement;
        const currentIndex = focusable.indexOf(current);

        if (currentIndex < focusable.length - 1) {
            focusable[currentIndex + 1].focus();
        }
    }

    /**
     * Focus previous element
     */
    focusPrevious() {
        const focusable = this._getFocusableElements();
        const current = document.activeElement;
        const currentIndex = focusable.indexOf(current);

        if (currentIndex > 0) {
            focusable[currentIndex - 1].focus();
        }
    }

    /**
     * Set focus to element
     */
    focusElement(element) {
        if (element instanceof HTMLElement) {
            element.focus();
            this._emit('focus:changed', { element });
        }
    }

    /**
     * Enable keyboard handler
     */
    enable() {
        this.config.enabled = true;
        console.log('[KeyboardHandler] Enabled');
        this._emit('handler:enabled');
    }

    /**
     * Disable keyboard handler
     */
    disable() {
        this.config.enabled = false;
        console.log('[KeyboardHandler] Disabled');
        this._emit('handler:disabled');
    }

    /**
     * Toggle keyboard handler
     */
    toggle() {
        this.config.enabled ? this.disable() : this.enable();
    }

    /**
     * Enable debug mode
     */
    enableDebug() {
        this.config.debugMode = true;
        console.log('[KeyboardHandler] Debug mode enabled');
    }

    /**
     * Disable debug mode
     */
    disableDebug() {
        this.config.debugMode = false;
        console.log('[KeyboardHandler] Debug mode disabled');
    }

    /**
     * Get debug info
     */
    getDebugInfo() {
        return {
            enabled: this.config.enabled,
            debugMode: this.config.debugMode,
            shortcutCount: this.shortcuts.size,
            shortcuts: this.getAllShortcuts(),
            focusableElements: this._getFocusableElements().length,
        };
    }

    /**
     * Event handler registration
     */
    on(eventName, callback) {
        if (!this.eventHandlers.has(eventName)) {
            this.eventHandlers.set(eventName, []);
        }
        this.eventHandlers.get(eventName).push(callback);

        return () => {
            const handlers = this.eventHandlers.get(eventName);
            const index = handlers.indexOf(callback);
            if (index > -1) {
                handlers.splice(index, 1);
            }
        };
    }

    /**
     * Emit event
     */
    _emit(eventName, data = {}) {
        const handlers = this.eventHandlers.get(eventName);
        if (handlers) {
            handlers.forEach((callback) => {
                try {
                    callback(data);
                } catch (e) {
                    console.error('[KeyboardHandler] Event handler error:', e);
                }
            });
        }
    }

    /**
     * Reset to default shortcuts
     */
    reset() {
        this.shortcuts.clear();
        this.eventHandlers.clear();
        this._registerDefaultShortcuts();
        console.log('[KeyboardHandler] Reset to default shortcuts');
    }
}

export default KeyboardHandler;
